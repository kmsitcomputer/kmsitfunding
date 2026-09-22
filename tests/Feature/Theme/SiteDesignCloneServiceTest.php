<?php

namespace Tests\Feature\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Models\Theme\ThemeBrandingConfig;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\SiteDesignCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-C — SiteDesignCloneService copy-on-write coverage
 * (C-RECON §24.1A test plan). Proves: atomic aggregate clone, source
 * immutability, asset physical copy + fail-closed + compensation, branding
 * remap, navigation root-first remap, visibility persistence, null branding.
 */
class SiteDesignCloneServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function service(): SiteDesignCloneService
    {
        return app(SiteDesignCloneService::class);
    }

    private function seedActiveAggregate(Principal $actor, array $overrides = []): Theme
    {
        Storage::fake('public');

        $theme = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Live',
            'slug' => 'live-'.uniqid(),
            'status' => 'ACTIVE',
            'is_system_default' => false,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        $template = ThemeTemplate::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $theme->id,
            'slug' => 'home-'.uniqid(),
            'name' => 'Home',
            'content_kind' => 'home',
        ]);

        $section = ThemeSection::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $theme->id,
            'is_reusable' => false,
            'layout_variant' => 'default',
            'visible' => true,
        ]);
        $template->sections()->attach($section->id, ['position' => 1, 'created_at' => now(), 'updated_at' => now()]);

        ThemeComponent::create([
            'ulid' => (string) Str::ulid(),
            'theme_section_id' => $section->id,
            'type' => 'hero',
            'config' => ['headline' => 'Hello'],
            'position' => 1,
        ]);

        $menu = ThemeNavigationMenu::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $theme->id,
            'code' => 'primary',
            'name' => 'Primary',
        ]);

        $root = ThemeNavigationItem::create([
            'ulid' => (string) Str::ulid(),
            'theme_navigation_menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Home',
            'destination_type' => 'SYSTEM_ROUTE',
            'destination_route' => 'home',
            'position' => 1,
            'visible' => true,
            'visible_desktop' => $overrides['visible_desktop'] ?? true,
            'visible_mobile' => $overrides['visible_mobile'] ?? true,
        ]);

        ThemeNavigationItem::create([
            'ulid' => (string) Str::ulid(),
            'theme_navigation_menu_id' => $menu->id,
            'parent_id' => $root->id,
            'label' => 'Sub',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/sub',
            'position' => 1,
            'visible' => true,
            'visible_desktop' => true,
            'visible_mobile' => true,
        ]);

        if (! ($overrides['skip_branding'] ?? false)) {
            $this->seedBrandingAsset($theme, $actor, $overrides['null_logo'] ?? false);
        }

        return $theme->fresh();
    }

    private function seedBrandingAsset(Theme $theme, Principal $actor, bool $nullLogo = false): void
    {
        $asset = null;

        if (! $nullLogo) {
            $ulid = (string) Str::ulid();
            $stored = $ulid.'.png';
            $dir = 'theme/'.now()->format('Y/m');
            Storage::disk('public')->put($dir.'/'.$stored, 'fake-png-bytes');

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
                'sha256' => str_repeat('b', 64),
                'status' => 'ACTIVE',
                'uploaded_by_principal_id' => $actor->id,
            ]);
            $asset->save();
            $asset->created_at = now();
            $asset->save();
        }

        $branding = new ThemeBrandingConfig;
        $branding->theme_id = $theme->id;
        $branding->color_tokens = [
            'primary' => '#111111',
            'secondary' => '#222222',
            'accent' => '#333333',
            'neutral_bg' => '#ffffff',
            'neutral_text' => '#000000',
        ];
        $branding->font_family = 'system';
        $branding->logo_theme_asset_id = $asset?->id;
        $branding->favicon_theme_asset_id = null;
        $branding->save();
    }

    public function test_clone_creates_independent_draft_aggregate(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $this->assertSame('DRAFT', $draft->status);
        $this->assertNotSame($source->id, $draft->id);
        $this->assertCount(1, $draft->templates);
        $this->assertCount(1, $draft->sections);
        $this->assertCount(1, $draft->navigationMenus);
        $this->assertSame(2, $draft->navigationMenus->first()->items()->count());
        $this->assertNotNull($draft->branding);
    }

    public function test_source_aggregate_remains_unchanged(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);
        $before = [
            'templates' => $source->templates()->count(),
            'sections' => $source->sections()->count(),
            'items' => ThemeNavigationItem::whereIn(
                'theme_navigation_menu_id',
                $source->navigationMenus()->pluck('id')
            )->count(),
            'assets' => $source->assets()->count(),
        ];

        $this->service()->cloneFromActive($source, $actor);
        $source->refresh();

        $this->assertSame('ACTIVE', $source->status);
        $this->assertSame($before['templates'], $source->templates()->count());
        $this->assertSame($before['sections'], $source->sections()->count());
        $this->assertSame($before['items'], ThemeNavigationItem::whereIn(
            'theme_navigation_menu_id',
            $source->navigationMenus()->pluck('id')
        )->count());
        $this->assertSame($before['assets'], $source->assets()->count());
    }

    public function test_cloned_children_reference_new_parents_only(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $sourceTemplateIds = $source->templates()->pluck('id')->all();
        $sourceSectionIds = $source->sections()->pluck('id')->all();

        foreach ($draft->templates as $template) {
            $this->assertNotContains($template->id, $sourceTemplateIds);
            $this->assertSame($draft->id, $template->theme_id);
        }

        foreach ($draft->sections as $section) {
            $this->assertNotContains($section->id, $sourceSectionIds);
            $this->assertSame($draft->id, $section->theme_id);
        }

        $sourceMenuIds = $source->navigationMenus()->pluck('id')->all();

        foreach ($draft->navigationMenus as $menu) {
            $this->assertNotContains($menu->id, $sourceMenuIds);
            $this->assertSame($draft->id, $menu->theme_id);
        }
    }

    public function test_cloned_asset_is_new_row_owned_by_draft_with_physical_copy(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);
        $sourceAsset = $source->assets()->firstOrFail();

        $draft = $this->service()->cloneFromActive($source, $actor);

        $clonedAsset = $draft->assets()->firstOrFail();
        $this->assertNotSame($sourceAsset->id, $clonedAsset->id);
        $this->assertNotSame($sourceAsset->ulid, $clonedAsset->ulid);
        $this->assertSame($draft->id, $clonedAsset->theme_id);
        $this->assertSame('public', $clonedAsset->disk);
        $this->assertSame($actor->id, $clonedAsset->uploaded_by_principal_id);
        $this->assertSame('ACTIVE', $clonedAsset->status);
        $this->assertSame($sourceAsset->original_filename, $clonedAsset->original_filename);
        $this->assertSame($sourceAsset->mime_type, $clonedAsset->mime_type);
        $this->assertSame($sourceAsset->extension, $clonedAsset->extension);
        $this->assertSame((int) $sourceAsset->size_bytes, (int) $clonedAsset->size_bytes);
        $this->assertSame($sourceAsset->sha256, $clonedAsset->sha256);

        $sourcePath = 'theme/'.$sourceAsset->created_at->format('Y/m').'/'.$sourceAsset->stored_filename;
        $clonePath = 'theme/'.$clonedAsset->created_at->format('Y/m').'/'.$clonedAsset->stored_filename;
        $this->assertNotSame($sourcePath, $clonePath);
        $this->assertTrue(Storage::disk('public')->exists($sourcePath));
        $this->assertTrue(Storage::disk('public')->exists($clonePath));
    }

    public function test_branding_asset_references_are_remapped_to_clones(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $sourceLogoId = $source->branding->logo_theme_asset_id;
        $draftLogoId = $draft->branding->fresh()->logo_theme_asset_id;

        $this->assertNotNull($sourceLogoId);
        $this->assertNotNull($draftLogoId);
        $this->assertNotSame($sourceLogoId, $draftLogoId);
        $this->assertSame($draft->id, ThemeAsset::query()->whereKey($draftLogoId)->value('theme_id'));
    }

    public function test_navigation_parent_linkage_uses_resolved_clone_ids(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $menu = $draft->navigationMenus()->firstOrFail();
        $child = $menu->items()->whereNotNull('parent_id')->firstOrFail();
        $parent = $menu->items()->whereKey($child->parent_id)->firstOrFail();

        $this->assertSame($menu->id, $parent->theme_navigation_menu_id);
        $this->assertNull($parent->parent_id);
    }

    public function test_visibility_flags_persist_through_clone(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor, ['visible_desktop' => false, 'visible_mobile' => true]);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $clonedRoot = $draft->navigationMenus()->firstOrFail()->items()->whereNull('parent_id')->firstOrFail();
        $this->assertFalse((bool) $clonedRoot->visible_desktop);
        $this->assertTrue((bool) $clonedRoot->visible_mobile);
    }

    public function test_missing_source_physical_asset_fails_closed(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);
        $sourceAsset = $source->assets()->firstOrFail();
        Storage::disk('public')->delete('theme/'.$sourceAsset->created_at->format('Y/m').'/'.$sourceAsset->stored_filename);

        $draftCountBefore = Theme::where('status', 'DRAFT')->count();

        try {
            $this->service()->cloneFromActive($source, $actor);
            $this->fail('Expected clone to fail closed on missing source asset.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('source_asset_missing', $e->reason);
        }

        $this->assertSame($draftCountBefore, Theme::where('status', 'DRAFT')->count());
        $this->assertSame(1, $source->assets()->count());
    }

    public function test_null_branding_asset_clones_without_inventing_asset(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor, ['null_logo' => true]);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $this->assertNull($draft->branding->fresh()->logo_theme_asset_id);
        $this->assertSame(0, $draft->assets()->count());
    }

    public function test_clone_without_branding_succeeds(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor, ['skip_branding' => true]);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $this->assertNull($draft->branding);
        $this->assertSame('DRAFT', $draft->status);
    }

    public function test_clone_rejects_archived_source(): void
    {
        config()->set('theme.disk', 'public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedActiveAggregate($actor);
        $source->forceFill(['status' => 'ARCHIVED'])->save();

        $this->expectException(ThemeValidationException::class);
        $this->service()->cloneFromActive($source, $actor);
    }
}
