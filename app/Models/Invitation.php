<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * M02 — invitation for non-self-registering actors (Partner Representative,
 * Internal Administrative Identity, Super Admin per Q22). Issuer/revoker are
 * attribution only — they do not themselves imply role authority (IMP-003+
 * concern).
 */
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    protected $table = 'invitations';

    protected $fillable = [
        'intended_email',
        'token_hash',
        'invited_actor',
        'issued_at',
        'expires_at',
        'issuer_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            $invitation->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoker_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && ! $this->isExpired();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
