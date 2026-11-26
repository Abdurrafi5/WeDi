<?php

namespace App\Console\Commands;

use App\Models\ReminderSetting;
use App\Services\DailyReminderService;
use Illuminate\Console\Command;

class SendDailyReminders extends Command
{
    protected $signature = 'wedi:send-daily-reminders';

    protected $description = 'Send due daily diary reminders to users who have not written today';

    public function handle(DailyReminderService $service): int
    {
        $sent = 0;

        ReminderSetting::query()
            ->where('enabled', true)
            ->with('user')
            ->chunkById(100, function ($settings) use ($service, &$sent) {
                foreach ($settings as $setting) {
                    if (! $setting->user) {
                        continue;
                    }

                    $delivery = $service->deliverForUser($setting->user, $setting);
                    if ($delivery) {
                        $sent++;
                    }
                }
            });

        $this->info("Daily reminders delivered: {$sent}");

        return self::SUCCESS;
    }
}
