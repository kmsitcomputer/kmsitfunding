<?php

namespace Tests\Feature\Theme;

use App\Enums\ScopeType;
use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeTemplate;
use App\Models\User;
use App\Services\Content\ArticleService;
use App\Services\Content\PageService;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\PageBuilderBlockRegistry;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CODEX-CR001D-01 — canonical CMS content references through Page Builder.
 * Proves: rich_text/CTA/banner CMS selections persist kind+ulid only (no
 * business content copied), exist in the matching table, cross-kind and
 * dangling ULIDs fail closed, edits preserve selection, and the show props
 * carry sufficient selector data.
 */
class PageBuilderCmsReferenceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        return app(ThemeService::class)->create(['name' => 'PB CMS '.uniqid()], $actor)->fresh();
    }

    private function makeTemplate(Theme $theme): ThemeTemplate
    {
        return app(ThemeTemplateService::class)->create(
            $theme,
            ['name' => 'Home '.uniqid(), 'content_kind' => 'home'],
            $this->makeUnauthorizedActor()
        );
    }

    private function makePage(string $title = 'Landing'): CmsPage
    {
        return app(PageService::class)->create(
            ['title' => $title, 'body_html' => '<p>Body</p>'],
            $this->makeUnauthorizedActor()
        );
    }

    private function makeArticle(string $title = 'News item'): CmsArticle
    {
        return app(ArticleService::class)->create(
            ['title' => $title, 'body_html' => '<p>Body</p>'],
            $this->makeUnauthorizedActor()
        );
    }

    private function ctaCmsConfig(string $kind, string $ulid): array
    {
        return [
            'label' => 'Read more',
            'variant' => 'primary',
            'destination_type' => 'CMS_CONTENT',
            'destination_content_kind' => $kind,
            'destination_content_ulid' => $ulid,
        ];
    }

    private int $cmsRefSequence = 0;

    /**
     * A least-privilege actor holding exactly the given permission codes at
     * GLOBAL_PLATFORM scope — same pattern as PageBuilderLeastPrivilegeTest.
     */
    private function makePrincipal(array $permissionCodes): Principal
    {
        $this->cmsRefSequence++;
        $user = User::create([
            'email' => "pb-cmsref-{$this->cmsRefSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create([
            'code' => 'pb-cmsref-role-'.$this->cmsRefSequence,
            'name' => 'PB CmsRef Role '.$this->cmsRefSequence,
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

    public function test_rich_text_cms_page_reference_persists(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage();
        $template = $this->makeTemplate($this->makeTheme());

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content',
            'content_kind' => 'page',
            'content_ulid' => $page->ulid,
        ], $actor);

        $config = $section->components()->first()->config;
        $this->assertSame('page', $config['content_kind']);
        $this->assertSame($page->ulid, $config['content_ulid']);
        $this->assertArrayNotHasKey('title', $config);
        $this->assertArrayNotHasKey('body_html', $config);
    }

    public function test_rich_text_cms_article_reference_persists(): void
    {
        $actor = $this->makeAuthorizedActor();
        $article = $this->makeArticle();
        $template = $this->makeTemplate($this->makeTheme());

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content',
            'content_kind' => 'article',
            'content_ulid' => $article->ulid,
        ], $actor);

        $config = $section->components()->first()->config;
        $this->assertSame('article', $config['content_kind']);
        $this->assertSame($article->ulid, $config['content_ulid']);
        $this->assertArrayNotHasKey('title', $config);
    }

    public function test_edit_preserves_current_cms_selection(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage();
        $template = $this->makeTemplate($this->makeTheme());
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content',
            'content_kind' => 'page',
            'content_ulid' => $page->ulid,
        ], $actor);

        $updated = $service->updateBlockConfig($section, [
            'source' => 'cms_content',
            'content_kind' => 'page',
            'content_ulid' => $page->ulid,
        ], $actor, $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP'));

        $this->assertSame($page->ulid, $updated->fresh()->config['content_ulid']);
    }

    public function test_cross_kind_ulid_is_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage();
        $article = $this->makeArticle();
        $template = $this->makeTemplate($this->makeTheme());
        $service = app(PageBuilderBlockService::class);

        foreach ([
            ['page', $article->ulid],
            ['article', $page->ulid],
        ] as [$kind, $ulid]) {
            try {
                $service->addBlock($template, 'rich_text_cms', [
                    'source' => 'cms_content',
                    'content_kind' => $kind,
                    'content_ulid' => $ulid,
                ], $actor);
                $this->fail('Expected ThemeValidationException.');
            } catch (ThemeValidationException $e) {
                $this->assertSame('invalid_content_reference', $e->reason);
            }
        }

        $this->assertSame(0, $template->fresh()->sections()->count());
    }

    public function test_cta_cms_destination_persists(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage();
        $template = $this->makeTemplate($this->makeTheme());

        $section = app(PageBuilderBlockService::class)->addBlock(
            $template, 'donation_cta', $this->ctaCmsConfig('page', $page->ulid), $actor
        );

        $config = $section->components()->first()->config;
        $this->assertSame('CMS_CONTENT', $config['destination_type']);
        $this->assertSame('page', $config['destination_content_kind']);
        $this->assertSame($page->ulid, $config['destination_content_ulid']);
    }

    public function test_banner_cms_destination_persists(): void
    {
        $actor = $this->makeAuthorizedActor();
        $article = $this->makeArticle();
        $template = $this->makeTemplate($this->makeTheme());

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'banner', [
            'text' => 'New article out',
            'dismissible' => false,
            'destination_type' => 'CMS_CONTENT',
            'destination_content_kind' => 'article',
            'destination_content_ulid' => $article->ulid,
        ], $actor);

        $config = $section->components()->first()->config;
        $this->assertSame('CMS_CONTENT', $config['destination_type']);
        $this->assertSame($article->ulid, $config['destination_content_ulid']);
    }

    public function test_nonexistent_content_ulid_is_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $template = $this->makeTemplate($this->makeTheme());

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta',
                $this->ctaCmsConfig('page', str_repeat('Z', 26)), $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('invalid_content_reference', $e->reason);
            $this->assertSame(0, $template->fresh()->sections()->count());
            $this->assertSame(0, ThemeComponent::query()->count());
        }
    }

    public function test_show_props_carry_cms_selector_data(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $page = $this->makePage('Selector Page');

        app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content',
            'content_kind' => 'page',
            'content_ulid' => $page->ulid,
        ], $actor);

        $this->actingAs($actor->humanUser)
            ->get("/admin/page-builder/templates/{$template->ulid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PageBuilder/Show')
                ->has('cmsPages')
                ->has('cmsArticles')
                ->has('cmsTitles'));
    }

    /**
     * N-01 (Codex second-pass finding) — ziswaf_services/gallery/partners
     * share the identical type AND the identical only discriminator
     * (mode=authored); nothing in the approved schema distinguishes them
     * once persisted, since the prior "distinct" labels were actually
     * matched against mutable `cards` CONTENT, which one card-title edit
     * could — and, per Codex, did — break, silently relabeling a Gallery as
     * "ZISWAF Services". The honest fix is one stable label for every
     * card_grid/authored component, regardless of content or edits — see
     * PageBuilderBlockRegistry::resolveOperatorLabel()'s docblock for why a
     * persisted discriminator was not invented instead.
     */
    public function test_card_grid_label_is_stable_regardless_of_card_content_or_edits(): void
    {
        $freshGallery = ['mode' => 'authored', 'cards' => [['title' => 'Photo 1', 'text' => null]]];
        $freshPartners = ['mode' => 'authored', 'cards' => [['title' => 'Partner 1', 'text' => null]]];
        $freshZiswaf = ['mode' => 'authored', 'cards' => [
            ['title' => 'Zakat', 'text' => null],
            ['title' => 'Infaq', 'text' => null],
            ['title' => 'Sedekah', 'text' => null],
        ]];

        $this->assertSame('Content Cards', PageBuilderBlockRegistry::resolveOperatorLabel('card_grid', $freshGallery));
        $this->assertSame('Content Cards', PageBuilderBlockRegistry::resolveOperatorLabel('card_grid', $freshPartners));
        $this->assertSame('Content Cards', PageBuilderBlockRegistry::resolveOperatorLabel('card_grid', $freshZiswaf));

        // The regression this replaces: editing exactly one card title on a
        // freshly-created Gallery must NOT cause it to be labeled as
        // ZISWAF Services (or anything else it never was).
        $editedGallery = ['mode' => 'authored', 'cards' => [['title' => 'Zakat', 'text' => null]]];
        $this->assertSame('Content Cards', PageBuilderBlockRegistry::resolveOperatorLabel('card_grid', $editedGallery));
    }

    public function test_variant_grids_resolve_distinct_labels(): void
    {
        $this->assertSame('Campaign Grid', PageBuilderBlockRegistry::resolveOperatorLabel('content_list', [
            'content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'grid',
        ]));
        $this->assertSame('Program Carousel', PageBuilderBlockRegistry::resolveOperatorLabel('content_list', [
            'content_kind' => 'program', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'carousel',
        ]));
        $this->assertSame('Articles / News', PageBuilderBlockRegistry::resolveOperatorLabel('content_list', [
            'content_kind' => 'article', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'grid',
        ]));
        $this->assertSame('Safe Custom Content', PageBuilderBlockRegistry::resolveOperatorLabel('rich_text', [
            'source' => 'custom_html', 'body_html' => '<p>x</p>',
        ]));
    }

    // ================================================================
    // RA-01 — CMS selector reachability beyond the initial bounded page.
    // ================================================================

    public function test_cms_content_endpoint_reaches_pages_beyond_first_bounded_page(): void
    {
        $actor = $this->makeAuthorizedActor();

        $ulids = [];
        for ($i = 0; $i < 25; $i++) {
            $ulids[] = $this->makePage("Bulk page {$i}")->ulid;
        }
        // Oldest-created page (by updated_at) sorts LAST under latest(), so
        // it is the one guaranteed to fall outside the first bounded page.
        $beyondFirstPage = $ulids[0];

        $firstPage = $this->actingAs($actor->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=page&page=1')
            ->assertOk()
            ->json();

        $this->assertCount(20, $firstPage['items']);
        $this->assertTrue($firstPage['hasMore']);
        $this->assertFalse(collect($firstPage['items'])->contains('ulid', $beyondFirstPage));

        $secondPage = $this->actingAs($actor->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=page&page=2')
            ->assertOk()
            ->json();

        $this->assertTrue(collect($secondPage['items'])->contains('ulid', $beyondFirstPage));
        $this->assertFalse($secondPage['hasMore']);
    }

    public function test_cms_content_endpoint_reaches_articles_beyond_first_bounded_page(): void
    {
        $actor = $this->makeAuthorizedActor();

        $ulids = [];
        for ($i = 0; $i < 25; $i++) {
            $ulids[] = $this->makeArticle("Bulk article {$i}")->ulid;
        }
        $beyondFirstPage = $ulids[0];

        $firstPage = $this->actingAs($actor->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=article&page=1')
            ->assertOk()->json();
        $this->assertCount(20, $firstPage['items']);
        $this->assertTrue($firstPage['hasMore']);

        $secondPage = $this->actingAs($actor->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=article&page=2')
            ->assertOk()->json();
        $this->assertTrue(collect($secondPage['items'])->contains('ulid', $beyondFirstPage));
    }

    public function test_cms_content_endpoint_response_is_bounded_never_the_whole_table(): void
    {
        $actor = $this->makeAuthorizedActor();

        for ($i = 0; $i < 45; $i++) {
            $this->makePage("Bound check {$i}");
        }

        $response = $this->actingAs($actor->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=page&page=1')
            ->assertOk()->json();

        $this->assertLessThanOrEqual(20, count($response['items']));
        foreach ($response['items'] as $item) {
            $this->assertSame(['ulid', 'title'], array_keys($item));
        }
    }

    public function test_selected_item_beyond_bounded_page_hydrates_after_reopening_editor(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $ulids = [];
        for ($i = 0; $i < 25; $i++) {
            $ulids[] = $this->makePage("Reach page {$i}")->ulid;
        }
        $beyondFirstPage = $ulids[0];

        app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content',
            'content_kind' => 'page',
            'content_ulid' => $beyondFirstPage,
        ], $actor);

        // Reopening the editor (a fresh show() props load) must still be
        // able to display the currently-selected item's title even though
        // it is not among the first bounded 20 — via cmsTitles, not by
        // widening cmsPages itself.
        $this->actingAs($actor->humanUser)
            ->get("/admin/page-builder/templates/{$template->ulid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PageBuilder/Show')
                ->where("cmsTitles.{$beyondFirstPage}", 'Reach page 0'));
    }

    // ================================================================
    // RA-02 — CMS title/selector authorization.
    // ================================================================

    public function test_theme_only_actor_without_content_view_sees_empty_selectors_and_no_title_leak(): void
    {
        $themeOnly = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $seeder = $this->makeAuthorizedActor();
        $page = $this->makePage('Confidential Page');
        $article = $this->makeArticle('Confidential Article');
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content', 'content_kind' => 'page', 'content_ulid' => $page->ulid,
        ], $seeder);
        app(PageBuilderBlockService::class)->addBlock($template, 'banner', [
            'text' => 'x', 'dismissible' => false,
            'destination_type' => 'CMS_CONTENT', 'destination_content_kind' => 'article', 'destination_content_ulid' => $article->ulid,
        ], $seeder);

        $this->actingAs($themeOnly->humanUser)
            ->get("/admin/page-builder/templates/{$template->ulid}")
            ->assertOk()
            ->assertInertia(function (Assert $inertia) use ($page, $article) {
                $inertia->component('Admin/PageBuilder/Show')
                    ->where('cmsPages', [])
                    ->where('cmsArticles', []);
                // Neither the referenced Page's nor the referenced Article's
                // title may be disclosed to an actor without content.view,
                // even though both are directly referenced by blocks this
                // actor CAN see on this canvas.
                $inertia->where('cmsTitles', function ($titles) use ($page, $article) {
                    $titles = collect($titles)->all();

                    return ! array_key_exists($page->ulid, $titles) && ! array_key_exists($article->ulid, $titles);
                });
            });

        // The bounded selector endpoint itself denies the same actor.
        $this->actingAs($themeOnly->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=page&page=1')
            ->assertForbidden();
        $this->actingAs($themeOnly->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=article&page=1')
            ->assertForbidden();
    }

    public function test_authorized_actor_sees_permitted_content_and_selection_hydrates(): void
    {
        $authorized = $this->makePrincipal([
            PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE, PermissionRegistry::CONTENT_VIEW,
        ]);
        $page = $this->makePage('Visible Page');
        $article = $this->makeArticle('Visible Article');
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
            'source' => 'cms_content', 'content_kind' => 'page', 'content_ulid' => $page->ulid,
        ], $authorized);

        $this->actingAs($authorized->humanUser)
            ->get("/admin/page-builder/templates/{$template->ulid}")
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('Admin/PageBuilder/Show')
                ->where("cmsTitles.{$page->ulid}", 'Visible Page')
                ->where('cmsPages', fn ($pages) => collect($pages)->contains('ulid', $page->ulid)));

        $this->actingAs($authorized->humanUser)
            ->getJson('/admin/page-builder/cms-content?kind=article&page=1')
            ->assertOk()
            ->assertJsonFragment(['ulid' => $article->ulid, 'title' => 'Visible Article']);
    }

    // ================================================================
    // RA-04 (mutation-time authorization) — an actor cannot newly select
    // CMS content they are not authorized to view, even if they can guess
    // its ULID.
    // ================================================================

    public function test_actor_without_content_view_cannot_newly_select_cms_content_by_ulid(): void
    {
        $themeOnly = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $page = $this->makePage('Guessable Page');
        $template = $this->makeTemplate($this->makeTheme());

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'rich_text_cms', [
                'source' => 'cms_content', 'content_kind' => 'page', 'content_ulid' => $page->ulid,
            ], $themeOnly);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('invalid_content_reference', $e->reason);
        }

        $this->assertSame(0, $template->fresh()->sections()->count());
    }
}
