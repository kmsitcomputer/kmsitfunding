<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Q25 — durable one-time guard/evidence for the first Super Admin bootstrap.
 * Stores no credential. The unique constraint on `lock_key` is what makes a
 * concurrent repeat bootstrap attempt fail deterministically.
 */
class SuperAdminBootstrap extends Model
{
    public const LOCK_KEY = 'first_super_admin';

    protected $fillable = [
        'lock_key',
        'user_id',
        'bootstrapped_at',
    ];

    protected function casts(): array
    {
        return [
            'bootstrapped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
