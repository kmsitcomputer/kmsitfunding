<?php

namespace Tests\Feature\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Models\Theme\ThemeBrandingConfig;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\SiteDesignCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-C remediation N-06 — clone failure-path coverage.
 * Proves: unresolved navigation parent fails closed with full rollback,
 * Storage::copy() false fails closed with rollback + clone-only cleanup,
 * compensation-delete failure is tolerated (original failure surfaced,
 * source untouched, warning logged).
 */
class SiteDesignCloneFailureTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function service(): SiteDesignCloneService
    {
        return app(SiteDesignCloneService::class);
    }

    private function seedMinimalTheme(Principal $actor, string $status = 'ACTIVE'): Theme
    {
        Storage::fake('public');
        config()->set('theme.disk', 'public');

        $theme = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Failure Path Live',
            'slug' => 'failure-live-'.uniqid(),
            'status' => $status,
            'is_system_default' => false,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        return $theme->fresh();
    }

    private function seedAsset(Theme $theme, Principal $actor): ThemeAsset
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
            'sha256' => str_repeat('d', 64),
            'status' => 'ACTIVE',
            'uploaded_by_principal_id' => $actor->id,
        ]);
        $asset->save();

        return $asset->fresh();
    }

    public function test_unresolved_navigation_parent_fails_closed_with_full_rollback(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedMinimalTheme($actor);

        $menu = ThemeNavigationMenu::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'code' => 'primary',
            'name' => 'Primary',
        ]);

        $otherMenu = ThemeNavigationMenu::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'code' => 'other',
            'name' => 'Other',
        ]);

        $foreignParent = new ThemeNavigationItem;
        $foreignParent->forceFill([
            'ulid' => (string) Str::ulid(),
            'theme_navigation_menu_id' => $otherMenu->id,
            'parent_id' => null,
            'label' => 'Foreign Parent',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/foreign',
            'position' => 1,
            'visible' => true,
            'visible_desktop' => true,
            'visible_mobile' => true,
        ]);
        $foreignParent->save();

        $orphan = new ThemeNavigationItem;
        $orphan->forceFill([
            'ulid' => (string) Str::ulid(),
            'theme_navigation_menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Orphan',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/orphan',
            'position' => 1,
            'visible' => true,
            'visible_desktop' => true,
            'visible_mobile' => true,
        ]);
        $orphan->save();

        ThemeNavigationItem::query()->whereKey($orphan->id)->update(['parent_id' => $foreignParent->id]);

        $sourceTemplateCount = $source->templates()->count();
        $draftCountBefore = Theme::where('status', 'DRAFT')->count();

        try {
            $this->service()->cloneFromActive($source, $actor);
            $this->fail('Expected clone to fail closed on unresolved navigation parent.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('unresolved_navigation_parent', $e->reason);
        }

        $this->assertSame($draftCountBefore, Theme::where('status', 'DRAFT')->count());
        $this->assertSame('ACTIVE', $source->fresh()->status);
        $this->assertSame($sourceTemplateCount, $source->fresh()->templates()->count());
        $this->assertSame(1, $menu->fresh()->items()->count());
    }

    public function test_copy_failure_fails_closed_with_rollback_and_clone_only_cleanup(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedMinimalTheme($actor);
        $sourceAsset = $this->seedAsset($source, $actor);
        $sourcePath = 'theme/'.$sourceAsset->created_at->format('Y/m').'/'.$sourceAsset->stored_filename;

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('copy')->once()->andReturn(false);
        Storage::shouldReceive('delete')->once()->andReturn(true);

        $draftCountBefore = Theme::where('status', 'DRAFT')->count();

        try {
            $this->service()->cloneFromActive($source, $actor);
            $this->fail('Expected clone to fail closed on copy failure.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('asset_copy_failed', $e->reason);
        }

        $this->assertSame($draftCountBefore, Theme::where('status', 'DRAFT')->count());
        $this->assertSame('ACTIVE', $source->fresh()->status);
        $this->assertSame(1, $source->fresh()->assets()->count());
        Storage::clearResolvedInstances();
        $this->assertTrue(Storage::disk('public')->exists($sourcePath));
    }

    public function test_compensation_delete_failure_is_tolerated_and_logged(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedMinimalTheme($actor);
        $sourceAsset = $this->seedAsset($source, $actor);
        $sourcePath = 'theme/'.$sourceAsset->created_at->format('Y/m').'/'.$sourceAsset->stored_filename;

        $realDisk = Storage::disk('public');

        $brokenDisk = \Mockery::mock();
        $brokenDisk->shouldReceive('delete')->once()->andThrow(new \RuntimeException('disk unavailable'));
        Storage::shouldReceive('disk')->andReturnUsing(
            fn (string $name) => $name === 'compensation-broken' ? $brokenDisk : $realDisk
        );

        $spy = Log::spy();

        $method = new \ReflectionMethod(SiteDesignCloneService::class, 'compensateFilesystem');
        $method->invoke(app(SiteDesignCloneService::class), [
            ['disk' => 'compensation-broken', 'path' => 'theme/2026/09/deadbeef.png'],
        ]);

        $spy->shouldHaveReceived('warning', ['site-design-clone.compensation-failed', \Mockery::any()]);

        $this->assertSame('ACTIVE', $source->fresh()->status);
        $this->assertSame(1, $source->fresh()->assets()->count());
        Storage::clearResolvedInstances();
        $this->assertTrue(Storage::disk('public')->exists($sourcePath));
    }

    public function test_branding_unresolved_failure_leaves_source_aggregate_untouched(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedMinimalTheme($actor);
        $sourceAsset = $this->seedAsset($source, $actor);

        $archivedAsset = $this->seedAsset($source, $actor);
        $archivedAsset->forceFill(['status' => 'ARCHIVED'])->save();

        $branding = new ThemeBrandingConfig;
        $branding->theme_id = $source->id;
        $branding->color_tokens = [
            'primary' => '#111111',
            'secondary' => '#222222',
            'accent' => '#333333',
            'neutral_bg' => '#ffffff',
            'neutral_text' => '#000000',
        ];
        $branding->font_family = 'system';
        $branding->logo_theme_asset_id = $archivedAsset->id;
        $branding->favicon_theme_asset_id = null;
        $branding->save();

        $spy = Log::spy();

        $draftCountBefore = Theme::where('status', 'DRAFT')->count();

        try {
            $this->service()->cloneFromActive($source, $actor);
            $this->fail('Expected clone to fail on unresolvable branding asset.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('branding_asset_unresolved', $e->reason);
        }

        $this->assertSame($draftCountBefore, Theme::where('status', 'DRAFT')->count());
        $this->assertSame('ACTIVE', $source->fresh()->status);
        $this->assertSame(2, $source->fresh()->assets()->count());
        $this->assertSame(
            $archivedAsset->id,
            $source->fresh()->branding->logo_theme_asset_id
        );
    }

    public function test_late_clone_failure_triggers_clone_only_filesystem_cleanup(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedMinimalTheme($actor);
        $sourceAsset = $this->seedAsset($source, $actor);
        $sourcePath = 'theme/'.$sourceAsset->created_at->format('Y/m').'/'.$sourceAsset->stored_filename;

        $archivedAsset = $this->seedAsset($source, $actor);
        $archivedAsset->forceFill(['status' => 'ARCHIVED'])->save();

        $branding = new ThemeBrandingConfig;
        $branding->theme_id = $source->id;
        $branding->color_tokens = [
            'primary' => '#111111',
            'secondary' => '#222222',
            'accent' => '#333333',
            'neutral_bg' => '#ffffff',
            'neutral_text' => '#000000',
        ];
        $branding->font_family = 'system';
        $branding->logo_theme_asset_id = $archivedAsset->id;
        $branding->favicon_theme_asset_id = null;
        $branding->save();

        $filesBefore = collect(Storage::disk('public')->allFiles('theme'))->sort()->values()->all();

        try {
            $this->service()->cloneFromActive($source, $actor);
            $this->fail('Expected clone to fail on unresolvable branding asset.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('branding_asset_unresolved', $e->reason);
        }

        $filesAfter = collect(Storage::disk('public')->allFiles('theme'))->sort()->values()->all();
        $this->assertSame($filesBefore, $filesAfter);
        $this->assertTrue(Storage::disk('public')->exists($sourcePath));
        $this->assertSame(0, Theme::where('status', 'DRAFT')->count());
    }
}
