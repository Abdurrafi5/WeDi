<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'nickname', 'avatar_path', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function weeklyDiaries(): HasMany { return $this->hasMany(WeeklyDiary::class); }
    public function journals(): HasMany { return $this->hasMany(Journal::class); }
    public function targets(): HasMany { return $this->hasMany(Target::class); }
    public function recaps(): HasMany { return $this->hasMany(Recap::class); }
    public function gameScores(): HasMany { return $this->hasMany(GameScore::class); }
    public function aiConversations(): HasMany { return $this->hasMany(AiConversation::class); }
    public function aiUsageLogs(): HasMany { return $this->hasMany(AiUsageLog::class); }
    public function reflectionGenerations(): HasMany { return $this->hasMany(WeeklyReflectionGeneration::class); }
    public function artworks(): HasMany { return $this->hasMany(Artwork::class); }
    public function reminderSetting(): HasOne { return $this->hasOne(ReminderSetting::class); }
    public function pushSubscriptions(): HasMany { return $this->hasMany(PushSubscription::class); }
    public function reminderDeliveries(): HasMany { return $this->hasMany(ReminderDelivery::class); }

    public function getOrCreateReminderSetting(): ReminderSetting
    {
        return $this->reminderSetting()->firstOrCreate(
            ['user_id' => $this->id],
            [
                'enabled' => true,
                'reminder_time' => '12:00',
                'timezone' => config('app.timezone', 'Asia/Jakarta'),
            ]
        );
    }

    public function getOrCreateActiveDiary(): WeeklyDiary
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);
        $diary = $this->weeklyDiaries()->whereDate('week_start', $weekStart->toDateString())->first();
        if ($diary) {
            if ($diary->status !== 'active') $diary->update(['status' => 'active']);
            return $diary;
        }
        $this->weeklyDiaries()->where('status', 'active')->update(['status' => 'completed']);
        return $this->weeklyDiaries()->create(['week_start' => $weekStart->toDateString(), 'week_end' => $weekEnd->toDateString(), 'status' => 'active']);
    }

    public function getGreetingNameAttribute(): string
    {
        $name = trim((string) $this->nickname);
        if ($name !== '') return $name;
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY);
        return $parts[0] ?? __('there');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset(ltrim($this->avatar_path, '/')) . '?v=' . ((int) $this->updated_at?->timestamp) : null;
    }

    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return 'W';
        return mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
    }
}
