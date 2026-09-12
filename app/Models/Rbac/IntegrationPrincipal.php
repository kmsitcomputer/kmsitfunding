<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-003 — fixed, seeded, non-authenticatable Integration Principal catalog
 * (SECURITY-ARCHITECTURE.md). Empty at this stage; later stages add rows for
 * their own external integrations. See "System Principal / Integration
 * Principal".
 */
class IntegrationPrincipal extends Model
{
    protected $fillable = [
        'code',
        'description',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
        ];
    }
}
