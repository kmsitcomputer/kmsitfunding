<?php

namespace Tests\Feature\Theme;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Policies\ThemePolicy;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 slice 2 (authorization) coverage — mirrors IMP-005's
 * ContentAuthorizationTest exactly (docs/implementation/
 * IMP-006-theme-engine.md section 18/21).
 */
class ThemeAuthorizationTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        return Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Test Theme',
            'slug' => 'test-theme-'.uniqid(),
            'status' => $status,
            'is_system_default' => false,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    private function makeThemeAsset(): ThemeAsset
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $asset = new ThemeAsset;
        $asset->forceFill([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $theme->id,
            'disk' => 'public',
            'stored_filename' => 'test-'.uniqid().'.png',
            'original_filename' => 'test.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 1024,
            'sha256' => str_repeat('a', 64),
            'uploaded_by_principal_id' => $actor->id,
        ])->save();

        return $asset;
    }

    public function test_theme_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new ThemePolicy;
        $theme = $this->makeTheme();

        $this->assertTrue($policy->view($authorized));
        $this->assertFalse($policy->view($unauthorized));

        $this->assertTrue($policy->viewTheme($authorized, $theme));
        $this->assertFalse($policy->viewTheme($unauthorized, $theme));

        $this->assertTrue($policy->create($authorized));
        $this->assertFalse($policy->create($unauthorized));

        $this->assertTrue($policy->update($authorized, $theme));
        $this->assertFalse($policy->update($unauthorized, $theme));

        $this->assertTrue($policy->publish($authorized, $theme));
        $this->assertFalse($policy->publish($unauthorized, $theme));

        $this->assertTrue($policy->archive($authorized, $theme));
        $this->assertFalse($policy->archive($unauthorized, $theme));
    }

    public function test_theme_policy_rejects_archive_of_an_active_theme_even_for_authorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $policy = new ThemePolicy;
        $activeTheme = $this->makeTheme('ACTIVE');

        $this->assertFalse($policy->archive($authorized, $activeTheme));
    }

    public function test_theme_policy_media_methods_allow_authorized_and_deny_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new ThemePolicy;
        $asset = $this->makeThemeAsset();

        $this->assertTrue($policy->uploadAsset($authorized));
        $this->assertFalse($policy->uploadAsset($unauthorized));

        $this->assertTrue($policy->manageAsset($authorized, $asset));
        $this->assertFalse($policy->manageAsset($unauthorized, $asset));

        $this->assertTrue($policy->archiveAsset($authorized, $asset));
        $this->assertFalse($policy->archiveAsset($unauthorized, $asset));
    }

    public function test_theme_policy_denies_a_grant_at_a_scope_type_theme_never_requests(): void
    {
        // The single-organization-baseline analog of "cross-scope attempt"
        // (ThemeScopeResolver: identical single-scope-dimension shape to
        // IMP-005's ContentScopeResolver) — mirrors IMP-005's own
        // re-audit test for this exact class of gap.
        $actor = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'theme_wrong_scope', 'name' => 'Theme Wrong Scope']);
        foreach ([PermissionRegistry::THEME_UPDATE, PermissionRegistry::THEME_ARCHIVE] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        }
        PrincipalRoleAssignment::create([
            'principal_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Own->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
        $theme = $this->makeTheme();

        $this->assertFalse((new ThemePolicy)->update($actor, $theme));
        $this->assertFalse((new ThemePolicy)->archive($actor, $theme));
    }
}
