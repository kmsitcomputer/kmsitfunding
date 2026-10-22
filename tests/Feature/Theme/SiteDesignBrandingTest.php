<?php

namespace Tests\Feature\Theme;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-C remediation G-01 — Site Design branding asset management.
 * Proves: existing logo/favicon survive a colors-only save; selecting an
 * existing asset links it; explicit clear works; cross-theme asset rejected.
 */
class SiteDesignBrandingTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function makePrincipal(array $permissionCodes): Principal
    {
        $this->sequence++;
        $user = User::create([
            'email' => "branding-test-{$this->sequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create([
            'code' => 'branding-test-role-'.$this->sequence,
            'name' => 'Branding Test Role '.$this->sequence,
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

    private function makeTheme(Principal $actor): Theme
    {
        Storage::fake('public');
        config()->set('theme.disk', 'public');

        $theme = app(ThemeService::class)->create(['name' => 'Brand Theme '.uniqid()], $actor);

        return $theme->fresh();
    }

    private function makeAsset(Theme $theme, Principal $actor): ThemeAsset
    {
        $ulid = (string) Str::ulid();
        $stored = $ulid.'.png';
        Storage::disk('public')->put('theme/'.now()->format('Y/m').'/'.$stored, 'fake-png-bytes');

        $asset = new ThemeAsset;
        $asset->forceFill([
            'ulid' => $ulid,
            'theme_id' => $theme->id,
            'disk' => 'public',
            'stored_filename' => $stored,
            'original_filename' => 'logo.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 15,
            'width' => 10,
            'height' => 10,
            'sha256' => str_repeat('c', 64),
            'status' => 'ACTIVE',
            'uploaded_by_principal_id' => $actor->id,
        ]);
        $asset->save();

        return $asset->fresh();
    }

    private function brandingPayload(?string $logoUlid = null, ?string $faviconUlid = null): array
    {
        return [
            'color_tokens' => [
                'primary' => '#111111',
                'secondary' => '#222222',
                'accent' => '#333333',
                'neutral_bg' => '#ffffff',
                'neutral_text' => '#000000',
            ],
            'font_family' => 'system',
            'logo_theme_asset_ulid' => $logoUlid,
            'favicon_theme_asset_ulid' => $faviconUlid,
        ];
    }

    public function test_colors_only_save_preserves_existing_logo_and_favicon(): void
    {
        $owner = $this->makePrincipal([]);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $theme = $this->makeTheme($owner);
        $logo = $this->makeAsset($theme, $owner);
        $favicon = $this->makeAsset($theme, $owner);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload($logo->ulid, $favicon->ulid))
            ->assertRedirect();

        $this->assertSame($logo->id, $theme->fresh()->branding->logo_theme_asset_id);
        $this->assertSame($favicon->id, $theme->fresh()->branding->favicon_theme_asset_id);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload($logo->ulid, $favicon->ulid))
            ->assertRedirect();

        $this->assertSame($logo->id, $theme->fresh()->branding->fresh()->logo_theme_asset_id);
        $this->assertSame($favicon->id, $theme->fresh()->branding->fresh()->favicon_theme_asset_id);
    }

    public function test_selecting_existing_asset_as_logo_links_it(): void
    {
        $owner = $this->makePrincipal([]);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $theme = $this->makeTheme($owner);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload())
            ->assertRedirect();
        $this->assertNull($theme->fresh()->branding->logo_theme_asset_id);

        $logo = $this->makeAsset($theme, $owner);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload($logo->ulid))
            ->assertRedirect();

        $this->assertSame($logo->id, $theme->fresh()->branding->fresh()->logo_theme_asset_id);
    }

    public function test_selecting_existing_asset_as_favicon_links_it(): void
    {
        $owner = $this->makePrincipal([]);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $theme = $this->makeTheme($owner);
        $favicon = $this->makeAsset($theme, $owner);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload(null, $favicon->ulid))
            ->assertRedirect();

        $this->assertSame($favicon->id, $theme->fresh()->branding->favicon_theme_asset_id);
    }

    public function test_explicit_clear_removes_logo_only_when_intended(): void
    {
        $owner = $this->makePrincipal([]);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $theme = $this->makeTheme($owner);
        $logo = $this->makeAsset($theme, $owner);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload($logo->ulid))
            ->assertRedirect();
        $this->assertSame($logo->id, $theme->fresh()->branding->logo_theme_asset_id);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload(null))
            ->assertRedirect();

        $this->assertNull($theme->fresh()->branding->fresh()->logo_theme_asset_id);
    }

    public function test_cross_theme_asset_is_rejected(): void
    {
        $owner = $this->makePrincipal([]);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $theme = $this->makeTheme($owner);
        $otherTheme = $this->makeTheme($owner);
        $foreignAsset = $this->makeAsset($otherTheme, $owner);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload())
            ->assertRedirect();
        $this->assertNull($theme->fresh()->branding->logo_theme_asset_id);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload($foreignAsset->ulid))
            ->assertSessionHasErrors();

        $this->assertNull($theme->fresh()->branding->fresh()->logo_theme_asset_id);
    }
}
