<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    private const P256_SPKI_PREFIX_HEX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function getPublicKey(): ?string
    {
        $keys = $this->resolveVapidKeys();
        return $keys['public'] ?? null;
    }

    /**
     * Send an encrypted Web Push notification to a single subscription.
     */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        $keys = $this->resolveVapidKeys();
        if (! $keys) {
            return false;
        }

        try {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $encrypted = $this->encryptPayload(
                $json,
                $subscription->public_key,
                $subscription->auth_token
            );

            $parsed = parse_url($subscription->endpoint);
            if (! $parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
                return false;
            }
            $audience = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

            $jwt = $this->signVapidJwt($audience, $keys['private_pem']);

            $response = Http::timeout(10)
                ->withHeaders([
                    'TTL' => '86400',
                    'Urgency' => 'normal',
                    'Content-Encoding' => 'aes128gcm',
                    'Authorization' => 'vapid t=' . $jwt . ', k=' . $keys['public'],
                ])
                ->withBody($encrypted, 'application/octet-stream')
                ->post($subscription->endpoint);

            if (in_array($response->status(), [404, 410], true)) {
                $subscription->delete();
                return false;
            }

            if ($response->successful()) {
                $subscription->forceFill(['last_used_at' => now()])->save();
                return true;
            }

            Log::warning('WeDi WebPush delivery failed', [
                'subscription_id' => $subscription->id,
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('WeDi WebPush exception', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function resolveVapidKeys(): ?array
    {
        $envPublic = config('services.wedi.vapid_public');
        $envPrivate = config('services.wedi.vapid_private');
        if ($envPublic && $envPrivate) {
            return ['public' => $envPublic, 'private_pem' => $envPrivate];
        }

        $path = storage_path('app/wedi-vapid.json');
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (! empty($data['public']) && ! empty($data['private_pem'])) {
                return $data;
            }
        }

        if (! function_exists('openssl_pkey_new')) {
            return null;
        }

        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (! $key) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        openssl_pkey_export($key, $pem);
        if (empty($details['ec']['x']) || empty($details['ec']['y']) || ! $pem) {
            return null;
        }

        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $publicRaw = "\x04" . $x . $y;
        $data = [
            'public' => $this->base64UrlEncode($publicRaw),
            'private_pem' => $pem,
        ];

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }

    private function encryptPayload(string $payload, string $userPublicB64, string $userAuthB64): string
    {
        $uaPublicRaw = $this->base64UrlDecode($userPublicB64);
        $authSecret = $this->base64UrlDecode($userAuthB64);

        if (strlen($uaPublicRaw) !== 65 || strlen($authSecret) < 16) {
            throw new \RuntimeException('Invalid push subscription key material.');
        }

        $ephemeral = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (! $ephemeral) {
            throw new \RuntimeException('Unable to generate ephemeral EC key.');
        }

        $ephDetails = openssl_pkey_get_details($ephemeral);
        $asPublicRaw = "\x04"
            . str_pad($ephDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($ephDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        $spkiDer = hex2bin(self::P256_SPKI_PREFIX_HEX) . $uaPublicRaw;
        $spkiPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spkiDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $peerKey = openssl_pkey_get_public($spkiPem);
        if (! $peerKey) {
            throw new \RuntimeException('Invalid peer public key.');
        }

        $sharedSecret = openssl_pkey_derive($peerKey, $ephemeral, 32);
        if (! $sharedSecret) {
            throw new \RuntimeException('ECDH key derivation failed.');
        }

        $salt = random_bytes(16);

        $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw . "\x01";
        $ikm = hash_hmac('sha256', $keyInfo, $prkKey, true);

        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        $padded = $payload . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new \RuntimeException('AES-128-GCM encryption failed.');
        }

        return $salt . pack('N', 4096) . chr(strlen($asPublicRaw)) . $asPublicRaw . $ciphertext . $tag;
    }

    private function signVapidJwt(string $audience, string $privatePem): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = $this->base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => config('services.wedi.vapid_subject', 'mailto:hello@wedi.app'),
        ]));

        $input = $header . '.' . $claims;
        $derSig = '';
        if (! openssl_sign($input, $derSig, $privatePem, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign VAPID JWT.');
        }

        $rawSig = $this->derToRawEcdsa($derSig);

        return $input . '.' . $this->base64UrlEncode($rawSig);
    }

    private function derToRawEcdsa(string $der): string
    {
        $offset = 2;
        if (ord($der[1]) > 0x80) {
            $offset += ord($der[1]) - 0x80;
        }
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature.');
        }
        $rLen = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLen);
        $offset += 2 + $rLen;

        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid DER signature.');
        }
        $sLen = ord($der[$offset + 1]);
        $s = substr($der, $offset + 2, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return substr($r, -32) . substr($s, -32);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + 4 - (strlen($data) % 4), '=', STR_PAD_RIGHT);
        return base64_decode($padded, true) ?: '';
    }
}
