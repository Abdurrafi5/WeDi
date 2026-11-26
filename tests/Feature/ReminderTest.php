<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\PushSubscription;
use App\Models\ReminderDelivery;
use App\Models\ReminderSetting;
use App\Models\User;
use App\Services\DailyReminderService;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /*  Controller: updateSettings                                        */
    /* ------------------------------------------------------------------ */

    public function test_update_settings_saves_preferences(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateReminderSetting();

        $this->actingAs($user)
            ->post('/settings/reminders', [
                'enabled' => '1',
                'reminder_time' => '08:30',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertRedirect(route('settings'));

        $this->assertDatabaseHas('reminder_settings', [
            'user_id' => $user->id,
            'enabled' => true,
            'reminder_time' => '08:30',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    public function test_update_settings_validates_time_format(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateReminderSetting();

        $this->actingAs($user)
            ->post('/settings/reminders', [
                'enabled' => '1',
                'reminder_time' => 'not-a-time',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertSessionHasErrors('reminder_time');
    }

    public function test_update_settings_returns_json_when_expected(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateReminderSetting();

        $this->actingAs($user)
            ->postJson('/settings/reminders', [
                'enabled' => '0',
                'reminder_time' => '20:00',
                'timezone' => 'UTC',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('setting.enabled', false)
            ->assertJsonPath('setting.reminder_time', '20:00');
    }

    /* ------------------------------------------------------------------ */
    /*  Controller: status                                                */
    /* ------------------------------------------------------------------ */

    public function test_status_returns_correct_json_structure(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateReminderSetting();

        $this->actingAs($user)
            ->getJson('/reminders/status')
            ->assertOk()
            ->assertJsonStructure([
                'setting' => ['enabled', 'reminder_time', 'timezone'],
                'completed_today',
                'unread_count',
                'deliveries',
                'newly_triggered',
                'vapid_public_key',
                'today_url',
            ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Controller: subscribePush                                         */
    /* ------------------------------------------------------------------ */

    public function test_subscribe_push_creates_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/reminders/push-subscribe', [
                'endpoint' => 'https://push.example.com/sub/abc123',
                'keys' => [
                    'p256dh' => str_repeat('A', 87),
                    'auth' => str_repeat('B', 24),
                ],
                'content_encoding' => 'aes128gcm',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/sub/abc123',
        ]);
    }

    public function test_subscribe_push_validates_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/reminders/push-subscribe', [
                'endpoint' => 'not-a-url',
                'keys' => ['p256dh' => 'x', 'auth' => 'y'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');
    }

    /* ------------------------------------------------------------------ */
    /*  Controller: unsubscribePush                                       */
    /* ------------------------------------------------------------------ */

    public function test_unsubscribe_push_removes_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://push.example.com/sub/to-delete';

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => str_repeat('A', 87),
            'auth_token' => str_repeat('B', 24),
        ]);

        $this->actingAs($user)
            ->deleteJson('/reminders/push-unsubscribe', ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }

    /* ------------------------------------------------------------------ */
    /*  Controller: markRead                                              */
    /* ------------------------------------------------------------------ */

    public function test_mark_read_marks_delivery_as_read(): void
    {
        $user = User::factory()->create();

        $delivery = ReminderDelivery::create([
            'user_id' => $user->id,
            'reminder_date' => now()->toDateString(),
            'channel' => 'in_app',
            'title' => 'Test',
            'body' => 'Body',
            'target_url' => '/journal/create',
            'sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/reminders/{$delivery->id}/read")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['unread_count']);

        $delivery->refresh();
        $this->assertNotNull($delivery->read_at);
    }

    public function test_mark_read_rejects_other_users_delivery(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $delivery = ReminderDelivery::create([
            'user_id' => $owner->id,
            'reminder_date' => now()->toDateString(),
            'channel' => 'in_app',
            'title' => 'Test',
            'body' => 'Body',
            'target_url' => '/journal/create',
            'sent_at' => now(),
        ]);

        $this->actingAs($other)
            ->postJson("/reminders/{$delivery->id}/read")
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /*  Service: isDueForUser                                             */
    /* ------------------------------------------------------------------ */

    public function test_is_due_returns_false_when_disabled(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['enabled' => false]);

        $service = app(DailyReminderService::class);
        $this->assertFalse($service->isDueForUser($user, $setting));
    }

    public function test_is_due_returns_false_before_scheduled_time(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['reminder_time' => '14:00', 'timezone' => 'UTC']);

        $service = app(DailyReminderService::class);
        $earlyMorning = Carbon::parse('2026-11-26 09:00:00', 'UTC');

        $this->assertFalse($service->isDueForUser($user, $setting, $earlyMorning));
    }

    public function test_is_due_returns_false_when_diary_completed(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['reminder_time' => '12:00', 'timezone' => 'UTC']);

        $diary = $user->getOrCreateActiveDiary();
        Journal::create([
            'user_id' => $user->id,
            'weekly_diary_id' => $diary->id,
            'entry_date' => '2026-11-26',
            'content' => 'I wrote today!',
        ]);

        $service = app(DailyReminderService::class);
        $afterNoon = Carbon::parse('2026-11-26 13:00:00', 'UTC');

        $this->assertFalse($service->isDueForUser($user, $setting, $afterNoon));
    }

    public function test_is_due_returns_false_when_already_sent(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['reminder_time' => '12:00', 'timezone' => 'UTC']);

        ReminderDelivery::create([
            'user_id' => $user->id,
            'reminder_date' => '2026-11-26',
            'channel' => 'in_app',
            'title' => 'Test',
            'body' => 'Body',
            'target_url' => '/journal/create',
            'sent_at' => now(),
        ]);

        $service = app(DailyReminderService::class);
        $afterNoon = Carbon::parse('2026-11-26 13:00:00', 'UTC');

        $this->assertFalse($service->isDueForUser($user, $setting, $afterNoon));
    }

    public function test_is_due_returns_true_when_conditions_met(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['reminder_time' => '12:00', 'timezone' => 'UTC']);

        $service = app(DailyReminderService::class);
        $afterNoon = Carbon::parse('2026-11-26 13:00:00', 'UTC');

        $this->assertTrue($service->isDueForUser($user, $setting, $afterNoon));
    }

    /* ------------------------------------------------------------------ */
    /*  Service: deliverForUser                                           */
    /* ------------------------------------------------------------------ */

    public function test_deliver_creates_delivery_record(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['reminder_time' => '12:00', 'timezone' => 'UTC']);

        $service = app(DailyReminderService::class);
        $afterNoon = Carbon::parse('2026-11-26 13:00:00', 'UTC');

        $delivery = $service->deliverForUser($user, $setting, $afterNoon);

        $this->assertNotNull($delivery);
        $this->assertDatabaseHas('reminder_deliveries', [
            'user_id' => $user->id,
            'reminder_date' => '2026-11-26',
        ]);
    }

    public function test_deliver_prevents_duplicates(): void
    {
        $user = User::factory()->create();
        $setting = $user->getOrCreateReminderSetting();
        $setting->update(['reminder_time' => '12:00', 'timezone' => 'UTC']);

        $service = app(DailyReminderService::class);
        $afterNoon = Carbon::parse('2026-11-26 13:00:00', 'UTC');

        $first = $service->deliverForUser($user, $setting, $afterNoon);
        $second = $service->deliverForUser($user, $setting, $afterNoon);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $user->reminderDeliveries()->count());
    }

    /* ------------------------------------------------------------------ */
    /*  Service: pickMessage                                              */
    /* ------------------------------------------------------------------ */

    public function test_pick_message_returns_english_for_en_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $service = app(DailyReminderService::class);
        $message = $service->pickMessage($user, Carbon::parse('2026-11-26'));

        $this->assertArrayHasKey('title', $message);
        $this->assertArrayHasKey('body', $message);
        $this->assertStringContainsString('WeDi', $message['title']);
    }

    public function test_pick_message_returns_indonesian_for_id_locale(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $service = app(DailyReminderService::class);
        $message = $service->pickMessage($user, Carbon::parse('2026-11-26'));

        $this->assertArrayHasKey('title', $message);
        $this->assertArrayHasKey('body', $message);
    }

    /* ------------------------------------------------------------------ */
    /*  Console: SendDailyReminders                                       */
    /* ------------------------------------------------------------------ */

    public function test_send_daily_reminders_command_runs_without_error(): void
    {
        $this->artisan('wedi:send-daily-reminders')
            ->assertExitCode(0);
    }

    /* ------------------------------------------------------------------ */
    /*  Auth: guests cannot access reminder endpoints                     */
    /* ------------------------------------------------------------------ */

    public function test_guest_cannot_access_reminder_routes(): void
    {
        $this->getJson('/reminders/status')->assertUnauthorized();
        $this->postJson('/settings/reminders', [])->assertUnauthorized();
        $this->postJson('/reminders/push-subscribe', [])->assertUnauthorized();
        $this->deleteJson('/reminders/push-unsubscribe', [])->assertUnauthorized();
    }
}
