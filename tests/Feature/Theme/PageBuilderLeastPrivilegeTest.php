<?php

namespace Tests\Feature\Theme;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Theme\Theme;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * CR-001-D — least-privilege authorization for Page Builder. Proves:
 * THEME_VIEW grants read only; THEME_UPDATE grants mutation but not publish;
 * cross-theme Section manipulation rejected; view-only actor cannot mutate.
 */
class PageBuilderLeastPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function makePrincipal(array $permissionCodes): Principal
    {
        $this->sequence++;
        $user = User::create([
            'email' => "pb-leastpriv-{$this->sequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create([
            'code' => 'pb-leastpriv-role-'.$this->sequence,
            'name' => 'PB LeastPriv Role '.$this->sequence,
        ]);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $principal;
    }

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $owner = $this->makePrincipal([]);

        $theme = app(ThemeService::class)->create(['name' => 'PB LeastPriv '.uniqid()], $owner);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    private function ctaConfig(): array
    {
        return [
            'label' => 'Donate',
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/donate',
        ];
    }

    public function test_view_only_actor_can_read_but_cannot_mutate(): void
    {
        $theme = $this->makeTheme();
        $seeder = $this->makePrincipal([]);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $seeder);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW]);

        $this->actingAs($actor->humanUser)->get("/admin/page-builder/{$theme->ulid}")->assertOk();
        $this->actingAs($actor->humanUser)->get("/admin/page-builder/templates/{$template->ulid}")->assertOk();

        $this->actingAs($actor->humanUser)
            ->post("/admin/page-builder/templates/{$template->ulid}/blocks", [
                'block_key' => 'donation_cta',
                'config' => $this->ctaConfig(),
            ])
            ->assertForbidden();

        $this->assertSame(0, $template->fresh()->sections()->count());
    }

    public function test_update_actor_can_mutate_but_cannot_publish(): void
    {
        $theme = $this->makeTheme();
        $seeder = $this->makePrincipal([]);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $seeder);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/page-builder/templates/{$template->ulid}/blocks", [
                'block_key' => 'donation_cta',
                'config' => $this->ctaConfig(),
            ])
            ->assertRedirect();
        $this->assertSame(1, $template->fresh()->sections()->count());

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertForbidden();
        $this->assertSame('DRAFT', $theme->fresh()->status);
    }

    public function test_cross_theme_section_update_rejected(): void
    {
        $themeA = $this->makeTheme();
        $themeB = $this->makeTheme();
        $seeder = $this->makePrincipal([]);
        $templateA = app(ThemeTemplateService::class)->create($themeA, ['name' => 'Home A', 'content_kind' => 'home'], $seeder);
        $templateB = app(ThemeTemplateService::class)->create($themeB, ['name' => 'Home B', 'content_kind' => 'home'], $seeder);
        $section = app(PageBuilderBlockService::class)->addBlock($templateB, 'donation_cta', $this->ctaConfig(), $seeder);

        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->delete("/admin/page-builder/templates/{$templateA->ulid}/blocks/{$section->ulid}", [
                'expected_section_updated_at' => $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP'),
            ])
            ->assertSessionHasErrors('section');

        $this->assertTrue($templateB->fresh()->sections()->whereKey($section->id)->exists());
    }
}
