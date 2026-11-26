<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Artwork extends Model
{
    public const DISK = 'local'; // privat: storage/app/private

    protected $fillable = ['user_id', 'title', 'path', 'width', 'height', 'size_bytes'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
