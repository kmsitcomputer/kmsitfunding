<?php

namespace App\Models\Rbac;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-003 — binds a Role to a Principal within a Scope, for a bounded
 * effective period. Never mutated in place except to set
 * ends_at/revoked_at/revoked_by_principal_id once — see "Temporal
 * Authorization".
 */
class PrincipalRoleAssignment extends Model
{
    protected $fillable = [
        'principal_id',
        'role_id',
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

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
