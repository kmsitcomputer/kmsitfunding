<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-003 canonical Permission catalog, synced from the code-defined
 * PermissionRegistry. See "Permission Model" / "Permission Registry".
 */
class Permission extends Model
{
    protected $fillable = [
        'code',
        'description',
        'module',
    ];

    protected function casts(): array
    {
        return [
            'deprecated_at' => 'datetime',
        ];
    }
}
