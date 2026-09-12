<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Q23 / M04 — TOTP secret and recovery-code storage, separate from `users` to
 * limit the blast radius of any read access to the identity table.
 *
 * `secret`/`pending_secret` are RECOVERABLE (encrypted at rest via Laravel's
 * framework `encrypted` cast) — never hashed, since TOTP verification needs the
 * plaintext value. `recovery_codes` are the opposite: one-way HASHED, like a
 * password, since they only ever need to be checked, never recovered.
 * `accepted_steps` is the mandatory TOTP replay-protection guard (M04): a map of
 * challenge-context => last accepted Unix timestamp, so the same accepted code
 * can never be reused within its own time step for the same context.
 */
#[Hidden(['secret', 'pending_secret', 'recovery_codes'])]
class MfaSecret extends Model
{
    protected $table = 'mfa_secrets';

    protected $fillable = [
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'pending_secret' => 'encrypted',
            'pending_secret_expires_at' => 'datetime',
            'recovery_codes' => 'array',
            'accepted_steps' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPendingEnrollment(): bool
    {
        return $this->pending_secret !== null
            && $this->pending_secret_expires_at !== null
            && $this->pending_secret_expires_at->isFuture();
    }
}
