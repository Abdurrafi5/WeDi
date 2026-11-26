<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\ReminderDelivery;
use App\Services\DailyReminderService;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function updateSettings(Request $request, DailyReminderService $service)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reminder_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        $setting = $request->user()->getOrCreateReminderSetting();
        $setting->update([
            'enabled' => (bool) $validated['enabled'],
            'reminder_time' => $validated['reminder_time'],
            'timezone' => $service->normalizeTimezone($validated['timezone']),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => __('Reminder preferences saved.'),
                'setting' => $setting->only(['enabled', 'reminder_time', 'timezone']),
            ]);
        }

        return redirect()->route('settings')->with('success', __('Reminder preferences saved.'));
    }

    public function subscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:32'],
        ]);

        $endpointHash = hash('sha256', $validated['endpoint']);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
        ]);

        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, DailyReminderService $service, WebPushService $webPush): JsonResponse
    {
        $user = $request->user();
        $setting = $user->getOrCreateReminderSetting();
        $nowLocal = $service->nowInTimezone($setting->timezone);
        $today = $nowLocal->toDateString();
        $completedToday = $service->hasCompletedDiaryForDate($user, $today);

        if ($completedToday) {
            $user->reminderDeliveries()
                ->whereDate('reminder_date', $today)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $newlyTriggered = $service->deliverForUser($user, $setting, $nowLocal);

        $deliveries = $user->reminderDeliveries()
            ->orderByDesc('reminder_date')
            ->limit(5)
            ->get(['id', 'reminder_date', 'channel', 'title', 'body', 'target_url', 'sent_at', 'read_at']);

        $unreadCount = $user->reminderDeliveries()->whereNull('read_at')->count();

        return response()->json([
            'setting' => $setting->only(['enabled', 'reminder_time', 'timezone']),
            'completed_today' => $completedToday,
            'unread_count' => $unreadCount,
            'deliveries' => $deliveries,
            'newly_triggered' => $newlyTriggered?->only(['id', 'title', 'body', 'target_url', 'reminder_date']),
            'vapid_public_key' => $webPush->getPublicKey(),
            'today_url' => route('journal.create', ['day' => $today], false),
        ]);
    }

    public function markRead(Request $request, ReminderDelivery $delivery): JsonResponse
    {
        abort_unless($delivery->user_id === $request->user()->id, 404);

        if (! $delivery->read_at) {
            $delivery->update(['read_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $request->user()->reminderDeliveries()->whereNull('read_at')->count(),
        ]);
    }
}
