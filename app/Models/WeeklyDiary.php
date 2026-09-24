<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WeeklyDiary extends Model
{
    protected $fillable = [
        'user_id',
        'week_start',
        'week_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
        ];
    }

    /** Localized week label, e.g. "21 – 27 September 2026" / "Sep 21 – 27, 2026". */
    public function getRangeLabelAttribute(): string
    {
        $start = $this->week_start;
        $end = $this->week_end;
        $id = app()->getLocale() === 'id';
        $sameMonth = $start->month === $end->month && $start->year === $end->year;

        if ($id) {
            return $sameMonth
                ? $start->translatedFormat('j') . ' – ' . $end->translatedFormat('j F Y')
                : $start->translatedFormat('j M') . ' – ' . $end->translatedFormat('j M Y');
        }

        return $sameMonth
            ? $start->translatedFormat('M j') . ' – ' . $end->translatedFormat('j, Y')
            : $start->translatedFormat('M j') . ' – ' . $end->translatedFormat('M j, Y');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    public function recap(): HasOne
    {
        return $this->hasOne(Recap::class);
    }

    public function completedTargets(): HasMany
    {
        return $this->targets()->where('is_completed', true);
    }

    public function getProgressAttribute(): int
    {
        $total = $this->targets()->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $this->targets()->where('is_completed', true)->count();
        return (int) round(($completed / $total) * 100);
    }
}
