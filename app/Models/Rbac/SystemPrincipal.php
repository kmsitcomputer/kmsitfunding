<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-003 — fixed, seeded, non-authenticatable System Principal catalog
 * (SECURITY-ARCHITECTURE.md). Empty at this stage; later stages add rows for
 * their own background-job identities. See "System Principal / Integration
 * Principal".
 */
class SystemPrincipal extends Model
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
