<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-003 canonical Role catalog. Global, not per-organization/partner (Q1).
 * See docs/implementation/IMP-003-rbac-scope-business-authority.md "Role Model".
 */
class Role extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'retired_at' => 'datetime',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->wherePivotNull('revoked_at')
            ->withPivot(['granted_at', 'granted_by_principal_id', 'revoked_at', 'revoked_by_principal_id']);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PrincipalRoleAssignment::class);
    }
}
