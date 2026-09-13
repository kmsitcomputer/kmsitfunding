<?php

namespace App\Models\Rbac;

use App\Enums\PrincipalKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-003 canonical authorization-identity registry. Every Role/Authority
 * assignment references exactly one row here. See "Database Contract >
 * `principals`" and "Principal Lifecycle".
 *
 * Never stores password, MFA secret, email, or any other IMP-002 credential
 * field — human authentication remains entirely owned by `users`.
 */
class Principal extends Model
{
    protected $fillable = [
        'principal_kind',
        'human_user_id',
        'system_principal_id',
        'integration_principal_id',
    ];

    protected function casts(): array
    {
        return [
            'principal_kind' => PrincipalKind::class,
            'tombstoned_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Application-level guard for the CHECK constraint's invariant — the
        // second, mandatory enforcement layer per "Scope Type / Scope Target
        // Matrix"'s "defense in depth" pattern, and the ONLY enforcement
        // layer on SQLite (see the `principals` migration's own comment on
        // why SQLite cannot carry this CHECK constraint at the DB level).
        static::saving(function (self $principal) {
            if (! self::isConsistent($principal)) {
                throw new \RuntimeException(
                    'Principal kind/human_user_id/tombstoned_at/system_principal_id/'.
                    'integration_principal_id combination violates the canonical CHECK invariant.'
                );
            }
        });
    }

    private static function isConsistent(self $principal): bool
    {
        $kind = $principal->principal_kind instanceof PrincipalKind
            ? $principal->principal_kind
            : PrincipalKind::tryFrom((string) $principal->principal_kind);

        return match ($kind) {
            PrincipalKind::Human => $principal->tombstoned_at === null
                ? ($principal->human_user_id !== null
                    && $principal->system_principal_id === null
                    && $principal->integration_principal_id === null)
                : ($principal->human_user_id === null
                    && $principal->system_principal_id === null
                    && $principal->integration_principal_id === null),
            PrincipalKind::System => $principal->human_user_id === null
                && $principal->tombstoned_at === null
                && $principal->system_principal_id !== null
                && $principal->integration_principal_id === null,
            PrincipalKind::Integration => $principal->human_user_id === null
                && $principal->tombstoned_at === null
                && $principal->system_principal_id === null
                && $principal->integration_principal_id !== null,
            null => false,
        };
    }

    public function humanUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_user_id');
    }

    public function systemPrincipal(): BelongsTo
    {
        return $this->belongsTo(SystemPrincipal::class);
    }

    public function integrationPrincipal(): BelongsTo
    {
        return $this->belongsTo(IntegrationPrincipal::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(PrincipalRoleAssignment::class);
    }

    public function authorityAssignments(): HasMany
    {
        return $this->hasMany(AuthorityAssignment::class);
    }

    public function isTombstoned(): bool
    {
        return $this->tombstoned_at !== null;
    }

    /**
     * Q24: for a live human principal, status is ALWAYS read live from
     * IMP-002's own User state — never a second, competing source of truth.
     * A tombstoned or disabled (system/integration) principal can never
     * authorize regardless of what the underlying source says. For
     * System/Integration kinds, both this row's OWN `disabled_at` and the
     * referenced catalog row's `deactivated_at` are consulted — retiring a
     * catalog entry (e.g. decommissioning a scheduled-job identity) must
     * deny immediately without requiring a second, separate edit to the
     * `principals` row.
     */
    public function canAuthorize(): bool
    {
        if ($this->principal_kind === PrincipalKind::Human) {
            if ($this->isTombstoned()) {
                return false;
            }

            $user = $this->humanUser;

            return $user !== null && $user->canAuthenticate();
        }

        if ($this->disabled_at !== null) {
            return false;
        }

        if ($this->principal_kind === PrincipalKind::System) {
            return $this->systemPrincipal !== null && $this->systemPrincipal->deactivated_at === null;
        }

        return $this->integrationPrincipal !== null && $this->integrationPrincipal->deactivated_at === null;
    }
}
