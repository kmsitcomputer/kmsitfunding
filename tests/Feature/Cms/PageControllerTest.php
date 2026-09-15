<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 21 — first HTTP-boundary coverage for the CMS admin UI
 * (Page management). Exercises the actual routes/controller, not just the
 * underlying services (this session's own history shows string-
 * interpolation and wiring bugs can hide from service-level tests alone).
 */
class PageControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makePage(Principal $owner, string $status = 'DRAFT'): CmsPage
    {
        return CmsPage::create([
            'title' => 'Test Page',
            'status' => $status,
            'created_by_principal_id' => $owner->id,
            'updated_by_principal_id' => $owner->id,
        ]);
    }

    public function test_authorized_user_can_list_pages(): void
    {
        $actor = $this->makeAuthorizedActor();
        $this->makePage($actor);

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/pages')
            ->assertOk();
    }

    public function test_unauthorized_user_is_forbidden_from_listing_pages(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/pages')
            ->assertForbidden();
    }

    public function test_authorized_user_can_create_a_page_with_a_draft(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/pages', [
            'title' => 'About Us',
            'body_html' => '<p>Hello world</p>',
        ]);

        $page = CmsPage::where('title', 'About Us')->firstOrFail();
        $response->assertRedirect("/admin/content/pages/{$page->ulid}");
        $this->assertNotNull($page->currentDraft()->first());
    }

    public function test_unauthorized_user_is_forbidden_from_creating_a_page(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)->post('/admin/content/pages', [
            'title' => 'About Us',
            'body_html' => '<p>Hello world</p>',
        ])->assertForbidden();
    }

    public function test_creating_a_page_rejects_a_script_tag_as_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/pages', [
            'title' => 'XSS attempt',
            'body_html' => '<p>hi</p><script>alert(1)</script>',
        ]);

        $response->assertSessionHasErrors('body_html');
        $this->assertDatabaseMissing('cms_pages', ['title' => 'XSS attempt']);
    }

    public function test_edit_page_is_bound_by_ulid_not_numeric_id(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor);

        $this->actingAs($actor->humanUser)->get("/admin/content/pages/{$page->ulid}")->assertOk();
        $this->actingAs($actor->humanUser)->get("/admin/content/pages/{$page->id}")->assertNotFound();
    }

    public function test_authorized_user_can_update_the_draft(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor);
        app(RevisionService::class)->createDraft($page, [
            'title' => 'v1', 'body_html' => '<p>1</p>',
        ], $actor);

        $response = $this->actingAs($actor->humanUser)->patch("/admin/content/pages/{$page->ulid}", [
            'title' => 'v1 edited',
            'expected_edit_version' => 0,
        ]);

        $response->assertRedirect("/admin/content/pages/{$page->ulid}");
        $this->assertSame('v1 edited', $page->currentDraft()->first()->title);
    }

    public function test_update_with_a_stale_expected_edit_version_returns_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor);
        app(RevisionService::class)->createDraft($page, [
            'title' => 'v1', 'body_html' => '<p>1</p>',
        ], $actor);

        $this->actingAs($actor->humanUser)->patch("/admin/content/pages/{$page->ulid}", [
            'title' => 'v2', 'expected_edit_version' => 0,
        ])->assertRedirect();

        $response = $this->actingAs($actor->humanUser)->patch("/admin/content/pages/{$page->ulid}", [
            'title' => 'v3 (loser)', 'expected_edit_version' => 0,
        ]);

        $response->assertSessionHasErrors('expected_edit_version');
    }

    public function test_unauthorized_user_is_forbidden_from_updating(): void
    {
        $owner = $this->makeAuthorizedActor();
        $page = $this->makePage($owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)->patch("/admin/content/pages/{$page->ulid}", [
            'title' => 'hijacked', 'expected_edit_version' => 0,
        ])->assertForbidden();
    }

    public function test_authorized_user_can_publish_unpublish_and_archive_a_page(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor);
        app(RevisionService::class)->createDraft($page, [
            'title' => 'v1', 'body_html' => '<p>1</p>',
        ], $actor);

        $this->actingAs($actor->humanUser)
            ->post("/admin/content/pages/{$page->ulid}/publish", ['path' => '/about'])
            ->assertRedirect("/admin/content/pages/{$page->ulid}");
        $this->assertSame('PUBLISHED', $page->fresh()->status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/content/pages/{$page->ulid}/unpublish")
            ->assertRedirect("/admin/content/pages/{$page->ulid}");
        $this->assertSame('RETIRED', $page->fresh()->status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/content/pages/{$page->ulid}/archive")
            ->assertRedirect('/admin/content/pages');
        $this->assertSame('ARCHIVED', $page->fresh()->status);
    }

    public function test_unauthorized_user_is_forbidden_from_publishing(): void
    {
        $owner = $this->makeAuthorizedActor();
        $page = $this->makePage($owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)
            ->post("/admin/content/pages/{$page->ulid}/publish", ['path' => '/about'])
            ->assertForbidden();
    }
}
