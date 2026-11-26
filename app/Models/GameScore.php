<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameScore extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'score',
        'duration_seconds',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'score' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public const VALID_GAMES = [
        'memory-match',
        'zen-tap',
        'word-bloom',
        'bubble-pop',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
