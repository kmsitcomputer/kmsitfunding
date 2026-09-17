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
 * IMP-005 — seeds the two bounded System Principals the scheduler and media
 * cleanup job authorize as (docs/implementation/IMP-005-cms.md section 12
 * "Scheduled transition attribution" / section 19 "Cleanup actor /
 * authority"): "Explicit content.publish + ORGANIZATION grant is required;
 * catalog code alone is not authority" / "the principal holds an explicit,
 * separately granted ORGANIZATION-scope content.archive permission... It
 * holds NO content.publish, NO content.update, NO audit.read, and no other
 * domain's permissions."
 *
 * Idempotent (safe to re-run): catalog rows via firstOrCreate,
 * PrincipalService::forSystem() is itself idempotent, and the role/grant/
 * assignment steps below each check for an existing row first.
 *
 * Grants are direct Eloquent inserts, deliberately NOT through
 * RoleAssignmentService — the same "internal-setup bypass... never an
 * exposed runtime path" carve-out Tests\Support\Rbac\RbacTestActors already
 * documents and relies on for seeding the super_admin bootstrap grant.
 * `assigned_by_principal_id: null` mirrors that same precedent (system-
 * seeded, no Human grantor).
 */
class CmsSystemPrincipalSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSystemPrincipal(
            code: 'content.scheduler',
            description: 'IMP-005 CMS — executes due scheduled publish/unpublish transitions (content:run-scheduled-transitions).',
            roleCode: 'cms_scheduler',
            roleName: 'CMS Scheduler',
            permissionCode: PermissionRegistry::CONTENT_PUBLISH,
        );

        $this->seedSystemPrincipal(
            code: 'content.media_cleanup',
            description: 'IMP-005 CMS — reconciles orphan files and purges unreferenced archived media (content:cleanup-media).',
            roleCode: 'cms_media_cleanup',
            roleName: 'CMS Media Cleanup',
            permissionCode: PermissionRegistry::CONTENT_ARCHIVE,
        );
    }

    private function seedSystemPrincipal(string $code, string $description, string $roleCode, string $roleName, string $permissionCode): void
    {
        $systemPrincipal = SystemPrincipal::firstOrCreate(['code' => $code], ['description' => $description]);
        $principal = app(PrincipalService::class)->forSystem($systemPrincipal);

        $role = Role::firstOrCreate(
            ['code' => $roleCode],
            ['name' => $roleName, 'description' => $description, 'is_system' => true],
        );

        $permission = Permission::where('code', $permissionCode)->firstOrFail();

        if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
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
