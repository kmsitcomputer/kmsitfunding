<?php

namespace App\Models\Rbac;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-003 — binds an Authority Type to a Principal within a Scope, for a
 * bounded period. Never implied by Role membership. See "Business Authority
 * > Authority Assignment".
 */
class AuthorityAssignment extends Model
{
    protected $fillable = [
        'authority_type_id',
        'principal_id',
        'scope_type',
        'scope_id',
        'starts_at',
        'ends_at',
        'assigned_by_principal_id',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Self-Escalation Protection: a Principal may never grant itself an
        // Authority Assignment — unconditionally, no exception. Enforced
        // here as the application-level guard (MySQL additionally enforces
        // this via a CHECK constraint — see the migration).
        static::creating(function (self $assignment) {
            if ($assignment->assigned_by_principal_id === $assignment->principal_id) {
                throw new \RuntimeException(
                    'A principal may never grant itself an Authority Assignment (self-escalation).'
                );
            }
        });
    }

    public function authorityType(): BelongsTo
    {
        return $this->belongsTo(AuthorityType::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'assigned_by_principal_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'revoked_by_principal_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->starts_at->lessThanOrEqualTo(now())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
