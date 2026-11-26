<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReflectionGeneration extends Model
{
    public const SECTIONS = ['summary', 'highlights', 'mood_overview', 'personal_growth', 'reflection_questions', 'next_week_suggestions'];

    protected $fillable = ['user_id', 'weekly_diary_id', 'journal_ids', 'result', 'edited_result', 'model', 'saved_at'];

    protected function casts(): array
    {
        return ['journal_ids' => 'array', 'result' => 'array', 'edited_result' => 'array', 'saved_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function weeklyDiary(): BelongsTo { return $this->belongsTo(WeeklyDiary::class); }
}
