<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M01 / P2-m01 — immutable, request-versioned email-change challenge.
 *
 * A request is promotable only while ACTIVE: not expired, not superseded, not
 * cancelled, not conflicted, and not already verified/consumed. See
 * docs/implementation/IMP-002-identity-authentication.md "Canonical Email Change
 * Lifecycle" for the full terminal-state contract this model must satisfy.
 */
#[Hidden(['verification_token_hash'])]
class EmailChangeRequest extends Model
{
    public const CONFLICT_TARGET_EMAIL_ALREADY_IN_USE = 'TARGET_EMAIL_ALREADY_IN_USE';

    protected $fillable = [
        'user_id',
        'normalized_pending_email',
        'verification_token_hash',
        'generation',
        'requested_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'superseded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'conflicted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * ACTIVE per the canonical definition: promotable, not yet resolved to any
     * terminal outcome, and not expired. Expiry is checked live (not a stored
     * flag), so an expired-but-unflagged row is correctly treated as inactive.
     */
    public function isActive(): bool
    {
        return $this->verified_at === null
            && $this->superseded_at === null
            && $this->cancelled_at === null
            && $this->conflicted_at === null
            && ! $this->isExpired();
    }
}
