<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 24 — HTTP-boundary coverage for the homepage designation
 * admin UI (section 26 flow 7).
 */
class HomepageControllerTest extends TestCase
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

    public function test_authorized_user_can_view_the_homepage_designation(): void
    {
        $actor = $this->makeAuthorizedActor();

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/homepage')
            ->assertOk();
    }

    public function test_unauthorized_user_is_forbidden_from_viewing(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/homepage')
            ->assertForbidden();
    }

    public function test_authorized_user_can_assign_a_page_as_homepage(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor);

        $response = $this->actingAs($actor->humanUser)->patch('/admin/content/homepage', [
            'page_ulid' => $page->ulid,
        ]);

        $response->assertRedirect('/admin/content/homepage');
        $this->assertSame($page->id, CmsHomepageAssignment::find(1)->page_id);
    }

    public function test_authorized_user_can_clear_the_homepage_designation(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor);
        $this->actingAs($actor->humanUser)->patch('/admin/content/homepage', ['page_ulid' => $page->ulid]);

        $response = $this->actingAs($actor->humanUser)->patch('/admin/content/homepage', [
            'page_ulid' => null,
            'expected_page_ulid' => $page->ulid,
        ]);

        $response->assertRedirect('/admin/content/homepage');
        $this->assertNull(CmsHomepageAssignment::find(1)->page_id);
    }

    public function test_assigning_an_archived_page_is_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();
        $page = $this->makePage($actor, 'ARCHIVED');

        $response = $this->actingAs($actor->humanUser)->patch('/admin/content/homepage', [
            'page_ulid' => $page->ulid,
        ]);

        $response->assertSessionHasErrors('page_ulid');
    }

    public function test_a_stale_expected_designee_returns_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();
        $pageA = $this->makePage($actor);
        $pageB = $this->makePage($actor);
        $this->actingAs($actor->humanUser)->patch('/admin/content/homepage', ['page_ulid' => $pageA->ulid]);

        $response = $this->actingAs($actor->humanUser)->patch('/admin/content/homepage', [
            'page_ulid' => $pageB->ulid,
            'expected_page_ulid' => $pageB->ulid,
        ]);

        $response->assertSessionHasErrors('page_ulid');
        $this->assertSame($pageA->id, CmsHomepageAssignment::find(1)->page_id);
    }

    public function test_unauthorized_user_is_forbidden_from_assigning(): void
    {
        $owner = $this->makeAuthorizedActor();
        $page = $this->makePage($owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)->patch('/admin/content/homepage', [
            'page_ulid' => $page->ulid,
        ])->assertForbidden();
    }
}
