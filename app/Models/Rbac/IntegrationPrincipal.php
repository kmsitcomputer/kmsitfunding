<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-003 — fixed, seeded, non-authenticatable Integration Principal catalog
 * (SECURITY-ARCHITECTURE.md). Empty at this stage; later stages add rows for
 * their own external integrations. See "System Principal / Integration
 * Principal".
 *
 * `deactivated_at` is deliberately NOT mass-assignable (`IMP003-IMPL-M04`) —
 * see `SystemPrincipal`'s equivalent note; the canonical path is
 * `PrincipalService::deactivateIntegration()`.
 */
class IntegrationPrincipal extends Model
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
