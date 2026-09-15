<?php

namespace Tests\Feature\Theme;

use App\Models\Cms\CmsHomepageAssignment;
use App\Services\Content\PageService;
use App\Services\Content\PublicationService;
use App\Services\Theme\ThemeActivationService;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 — the public rendering pipeline end to end
 * (docs/implementation/IMP-006-theme-engine.md section 13), the piece
 * IMP-005 §8 described but left unbuilt.
 */
class PublicContentControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_homepage_renders_with_no_designation(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/ThemeRender')->where('content', null));
    }

    public function test_homepage_renders_the_designated_published_page(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'Home', 'body_html' => '<p>Welcome</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $page->fresh()->currentDraft, $actor, '/home-slug');
        CmsHomepageAssignment::query()->whereKey(1)->update(['page_id' => $page->id, 'assigned_at' => now()]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/ThemeRender')->where('content.title', 'Home'));
    }

    public function test_a_published_page_resolves_through_the_catch_all_route(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'About', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $page->fresh()->currentDraft, $actor, '/about-us');

        $response = $this->get('/about-us');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->component('Public/ThemeRender')->where('content.title', 'About'));
    }

    public function test_an_unknown_path_renders_the_not_found_page(): void
    {
        $response = $this->get('/this-path-does-not-exist');

        $response->assertNotFound();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/NotFound'));
    }

    public function test_a_renamed_path_redirects_to_the_current_path(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'Renamed', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $page->fresh()->currentDraft, $actor, '/old-path');
        app(PublicationService::class)->publish($page->fresh(), $page->fresh()->publishedRevision, $actor, '/new-path');

        $response = $this->get('/old-path');

        $response->assertRedirect('/new-path');
        $response->assertStatus(301);
    }

    public function test_the_catch_all_route_never_shadows_a_system_route(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_admin_theme_routes_remain_protected_behind_authentication(): void
    {
        $this->get('/admin/theme')->assertRedirect('/login');
    }

    public function test_public_rendering_reflects_a_configured_theme_component(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'Custom'], $actor);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'hero', ['headline' => 'Custom Hero'], $actor);
        app(ThemeActivationService::class)->activate($theme, $actor);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('template.template_slug', 'home')
            ->where('template.sections.0.components.0.props.headline', 'Custom Hero')
        );
    }
}
