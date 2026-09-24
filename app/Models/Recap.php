<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recap extends Model
{
    protected $fillable = [
        'user_id',
        'weekly_diary_id',
        'achievement',
        'reflection',
        'lesson_learned',
        'next_week_plan',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function weeklyDiary(): BelongsTo
    {
        return $this->belongsTo(WeeklyDiary::class);
    }
}
