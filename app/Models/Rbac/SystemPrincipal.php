<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-003 — fixed, seeded, non-authenticatable System Principal catalog
 * (SECURITY-ARCHITECTURE.md). Empty at this stage; later stages add rows for
 * their own background-job identities. See "System Principal / Integration
 * Principal".
 *
 * `deactivated_at` is deliberately NOT mass-assignable (`IMP003-IMPL-M04`) —
 * it is a one-way transition that must go through
 * `PrincipalService::deactivateSystem()` (authorization + ELEVATED assurance
 * + atomic linked-Principal disablement + audit), never a bare
 * `create()`/`update()` call from arbitrary application code. The service
 * uses `forceFill()` internally, the same pattern already used for
 * `Principal::tombstoned_at`.
 */
class SystemPrincipal extends Model
{
    protected $fillable = [
        'code',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
        ];
    }
}
