<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = ['user_id', 'feature', 'model', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'status', 'error_code'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
