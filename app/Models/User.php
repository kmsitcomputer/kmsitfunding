<?php

namespace App\Models;

use App\Enums\IdentityLifecycle;
use App\Enums\SecurityRestriction;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * IMP-002 canonical Identity + Authentication entity.
 *
 * Owns only authentication + shared identity concerns (see
 * docs/implementation/IMP-002-identity-authentication.md "Identity Model"). Deliberately
 * does NOT own: role/permission/scope/business-authority fields, a business-approval
 * status, or transient rate-limiter state (failed_login_attempts/locked_until) — the
 * latter lives in the RateLimiter/cache infrastructure, never on this model.
 */
#[Hidden(['password'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
    ];

    /**
     * Mirrors the migration column defaults so a freshly-constructed instance
     * (e.g. immediately after User::create(), before any refresh()) reflects
     * the same state the database will actually persist.
     */
    protected $attributes = [
        'lifecycle_state' => 'active',
        'security_restriction' => 'none',
        'mfa_enabled' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
            'lifecycle_state' => IdentityLifecycle::class,
            'security_restriction' => SecurityRestriction::class,
        ];
    }

    public function mfaSecret(): HasOne
    {
        return $this->hasOne(MfaSecret::class);
    }

    public function emailChangeRequests(): HasMany
    {
        return $this->hasMany(EmailChangeRequest::class);
    }

    public function activeEmailChangeRequest(): ?EmailChangeRequest
    {
        return $this->emailChangeRequests()
            ->whereNull('verified_at')
            ->whereNull('superseded_at')
            ->whereNull('cancelled_at')
            ->whereNull('conflicted_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Q24 authentication effect: DISABLED or SUSPENDED denies authentication. A
     * transient rate-limiter lockout is checked separately (see AuthenticationService)
     * and is never represented here.
     */
    public function canAuthenticate(): bool
    {
        return $this->lifecycle_state === IdentityLifecycle::Active
            && $this->security_restriction === SecurityRestriction::None;
    }

    /**
     * Route key for public-safe references (never the internal BIGINT id).
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
