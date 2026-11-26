<?php

namespace App\Services;

use App\Models\ReminderDelivery;
use App\Models\ReminderSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class DailyReminderService
{
    public function __construct(private readonly WebPushService $webPush)
    {
    }

    public function hasCompletedDiaryForDate(User $user, string $date): bool
    {
        return $user->journals()
            ->whereDate('entry_date', $date)
            ->where('content', '!=', '')
            ->exists();
    }

    public function isDueForUser(User $user, ?ReminderSetting $setting = null, ?Carbon $nowLocal = null): bool
    {
        $setting = $setting ?: $user->getOrCreateReminderSetting();
        if (! $setting->enabled) {
            return false;
        }

        $nowLocal = $nowLocal ?: $this->nowInTimezone($setting->timezone);
        $today = $nowLocal->toDateString();

        if ($nowLocal->format('H:i') < $setting->reminder_time) {
            return false;
        }

        if ($this->hasCompletedDiaryForDate($user, $today)) {
            return false;
        }

        $alreadySent = $user->reminderDeliveries()
            ->whereDate('reminder_date', $today)
            ->exists();

        return ! $alreadySent;
    }

    public function deliverForUser(User $user, ?ReminderSetting $setting = null, ?Carbon $nowLocal = null): ?ReminderDelivery
    {
        $setting = $setting ?: $user->getOrCreateReminderSetting();
        $nowLocal = $nowLocal ?: $this->nowInTimezone($setting->timezone);

        if (! $this->isDueForUser($user, $setting, $nowLocal)) {
            return null;
        }

        $today = $nowLocal->toDateString();
        $copy = $this->pickMessage($user, $nowLocal);
        $targetUrl = route('journal.create', ['day' => $today], false);

        try {
            $delivery = ReminderDelivery::create([
                'user_id' => $user->id,
                'reminder_date' => $today,
                'channel' => $user->pushSubscriptions()->exists() ? 'push' : 'in_app',
                'title' => $copy['title'],
                'body' => $copy['body'],
                'target_url' => $targetUrl,
                'sent_at' => now(),
            ]);
        } catch (QueryException) {
            return null;
        }

        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isNotEmpty()) {
            $payload = [
                'title' => $delivery->title,
                'body' => $delivery->body,
                'url' => $delivery->target_url,
                'tag' => 'wedi-daily-reminder-' . $today,
                'icon' => asset('images/wedi-logo-mark.png'),
            ];

            foreach ($subscriptions as $subscription) {
                $this->webPush->send($subscription, $payload);
            }
        }

        return $delivery;
    }

    public function nowInTimezone(?string $timezone): Carbon
    {
        $tz = $this->normalizeTimezone($timezone);
        return Carbon::now($tz);
    }

    public function normalizeTimezone(?string $timezone): string
    {
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return config('app.timezone', 'Asia/Jakarta');
    }

    public function pickMessage(User $user, Carbon $date): array
    {
        $locale = $user->locale ?: app()->getLocale();

        $pool = $locale === 'id'
            ? [
                [
                    'title' => 'Pengingat kecil dari WeDi',
                    'body' => 'Halaman harianmu sudah menunggu. Luangkan sejenak untuk dirimu sendiri.',
                ],
                [
                    'title' => 'Waktunya jeda sebentar',
                    'body' => 'Ingin sedikit waktu untuk berefleksi? Diari harianmu sudah siap.',
                ],
                [
                    'title' => 'Pengingat kecil dari WeDi',
                    'body' => 'Jangan lupa simpan pikiran dan cerita hari ini di WeDi.',
                ],
                [
                    'title' => 'Satu halaman untuk hari ini',
                    'body' => 'Harimu layak mendapatkan satu halaman cerita tersendiri.',
                ],
            ]
            : [
                [
                    'title' => 'A Little Reminder from WeDi',
                    'body' => 'Your daily pages are waiting. Take a moment for yourself.',
                ],
                [
                    'title' => 'A Little Reminder from WeDi',
                    'body' => 'A little time for reflection? Your diary is ready.',
                ],
                [
                    'title' => 'A Little Reminder from WeDi',
                    'body' => "Don't forget to capture today's thoughts in WeDi.",
                ],
                [
                    'title' => 'A Little Reminder from WeDi',
                    'body' => 'Your day deserves a page of its own.',
                ],
            ];

        $index = ($date->dayOfYear + $user->id) % count($pool);
        return $pool[$index];
    }
}
