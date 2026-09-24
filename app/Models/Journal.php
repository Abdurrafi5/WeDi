<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    protected $fillable = ['user_id', 'weekly_diary_id', 'entry_date', 'title', 'content', 'mood'];
    protected function casts(): array { return ['entry_date' => 'date']; }
    public const MOODS = ['senang' => 'ri-emotion-happy-line', 'biasa' => 'ri-emotion-normal-line', 'sedih' => 'ri-emotion-sad-line', 'lelah' => 'ri-moon-line', 'semangat' => 'ri-sparkling-line'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function weeklyDiary(): BelongsTo { return $this->belongsTo(WeeklyDiary::class); }
    public function getMoodEmojiAttribute(): string { return self::MOODS[$this->mood] ?? 'ri-emotion-line'; }
    public function getMoodLabelAttribute(): string { return __($this->mood ? ucfirst($this->mood) : 'No mood'); }
}
