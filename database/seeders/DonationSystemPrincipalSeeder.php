<?php

namespace Database\Seeders;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Illuminate\Database\Seeder;

/**
 * IMP-008 — seeds the bounded System Principal the donation scheduler
 * commands authorize as (ExpirePendingDonations /
 * GenerateRecurringOccurrences). The principal holds an explicit,
 * separately granted ORGANIZATION-scope donation.* grant set sufficient
 * for sweeping expirations and generating occurrences — and NO audit.read,
 * NO rbac.*, NO unrelated domain permission.
 *
 * Idempotent (safe to re-run): catalog rows via firstOrCreate,
 * PrincipalService::forSystem() is itself idempotent, and the role/grant/
 * assignment steps each check for an existing row first. Grants are
 * direct Eloquent inserts — the same "internal-setup bypass... never an
 * exposed runtime path" carve-out CmsSystemPrincipalSeeder already
 * documents and relies on, with assigned_by_principal_id null (system-
 * seeded, no Human grantor).
 */
class DonationSystemPrincipalSeeder extends Seeder
{
    public function run(): void
    {
        $systemPrincipal = SystemPrincipal::firstOrCreate(
            ['code' => 'donation.scheduler'],
            ['description' => 'IMP-008 Donation — expires pending donations and generates monthly recurring occurrences (donation:expire-pending, donation:generate-occurrences).']
        );
        $principal = app(PrincipalService::class)->forSystem($systemPrincipal);

        $role = Role::firstOrCreate(
            ['code' => 'donation_scheduler'],
            ['name' => 'Donation Scheduler', 'description' => 'IMP-008 Donation scheduler identity.', 'is_system' => true],
        );

        foreach ([PermissionRegistry::DONATION_VIEW, PermissionRegistry::DONATION_CANCEL] as $permissionCode) {
            $permission = Permission::where('code', $permissionCode)->firstOrFail();

            if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
            }
        }

        $alreadyAssigned = PrincipalRoleAssignment::where('principal_id', $principal->id)
            ->where('role_id', $role->id)
            ->where('scope_type', ScopeType::Organization->value)
            ->whereNull('scope_id')
            ->whereNull('ends_at')
            ->exists();

        if (! $alreadyAssigned) {
            PrincipalRoleAssignment::create([
                'principal_id' => $principal->id,
                'role_id' => $role->id,
                'scope_type' => ScopeType::Organization->value,
                'scope_id' => null,
                'starts_at' => now(),
                'assigned_by_principal_id' => null,
            ]);
        }
    }
}
