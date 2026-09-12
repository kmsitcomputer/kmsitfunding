<?php

namespace Database\Seeders;

use App\Models\Rbac\Permission;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Database\Seeder;

/**
 * Idempotent (upsert-by-code) sync of the code-defined PermissionRegistry
 * into the `permissions` table. Never hard-deletes a permission — retiring
 * one is a deliberate, separate `deprecated_at` action.
 */
class RbacPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::definitions() as $code => $definition) {
            Permission::updateOrCreate(
                ['code' => $code],
                ['description' => $definition['description'], 'module' => $definition['module']],
            );
        }
    }
}
