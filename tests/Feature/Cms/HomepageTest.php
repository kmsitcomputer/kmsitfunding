<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;
use App\Services\Content\Exceptions\HomepageAssignmentConflictException;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\HomepageContentResolver;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 7 (homepage assignment + resolver) coverage — mirrors
 * docs/implementation/IMP-005-cms.md section 29's H-series intent (H1b, H2,
 * H3; H1's true-concurrency case and H4's archive-clear are deferred —
 * H1 needs a real multi-connection MySQL harness per section 29's own
 * engine marking, and H4 needs archive(), which this slice does not build).
 */
class HomepageTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function publishedPage(string $status = 'PUBLISHED'): CmsPage
    {
        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'Home Candidate',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'Home Candidate', 'body_html' => '<p>x</p>'], $actor);

        if ($status === 'DRAFT') {
            return $page->fresh();
        }

        $published = app(PublicationService::class)->publish($page, $draft, $actor, '/home-candidate-'.uniqid());

        if ($status === 'RETIRED') {
            return app(PublicationService::class)->unpublish($published, $actor);
        }

        return $published;
    }

    public function test_singleton_row_exists_with_no_designee_by_default(): void
    {
        $assignment = CmsHomepageAssignment::find(1);
        $this->assertNotNull($assignment);
        $this->assertNull($assignment->page_id);
    }

    public function test_assign_a_published_page_succeeds(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage();

        $assignment = app(PublicationService::class)->setHomepage($page, $actor);

        $this->assertSame($page->id, $assignment->page_id);
        $this->assertSame($actor->id, $assignment->assigned_by_principal_id);
        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_replace_with_correct_expected_page_id_succeeds(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $pageA = $this->publishedPage();
        $pageB = $this->publishedPage();
        $service = app(PublicationService::class);

        $service->setHomepage($pageA, $actor);
        $assignment = $service->setHomepage($pageB, $actor, expectedPageId: $pageA->id);

        $this->assertSame($pageB->id, $assignment->page_id);
    }

    public function test_replace_with_stale_expected_page_id_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $pageA = $this->publishedPage();
        $pageB = $this->publishedPage();
        $pageC = $this->publishedPage();
        $service = app(PublicationService::class);

        $service->setHomepage($pageA, $actor);

        $this->expectException(HomepageAssignmentConflictException::class);
        // Caller still thinks nobody (or pageB) is designated — stale read.
        $service->setHomepage($pageC, $actor, expectedPageId: $pageB->id);
    }

    public function test_designating_an_archived_page_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage();
        $page->forceFill(['status' => 'ARCHIVED'])->save(); // no archive() service yet this slice

        $this->expectException(PublicationValidationException::class);
        app(PublicationService::class)->setHomepage($page, $actor);
    }

    public function test_designating_an_unpublished_page_is_allowed_but_resolves_to_null(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage('DRAFT');

        // A DRAFT page is a legitimate designee (a designation grants no
        // visibility, section 8) — assignment itself must not reject it.
        app(PublicationService::class)->setHomepage($page, $actor);

        $this->assertNull(app(HomepageContentResolver::class)->resolve());
    }

    public function test_resolver_returns_null_when_undesignated(): void
    {
        $this->assertNull(app(HomepageContentResolver::class)->resolve());
    }

    public function test_resolver_returns_the_page_when_designee_is_published(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage();
        app(PublicationService::class)->setHomepage($page, $actor);

        $resolved = app(HomepageContentResolver::class)->resolve();

        $this->assertNotNull($resolved);
        $this->assertSame($page->id, $resolved->id);
        $this->assertNotNull($resolved->publishedRevision);
    }

    public function test_resolver_returns_null_when_designee_is_retired(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage();
        app(PublicationService::class)->setHomepage($page, $actor);

        app(PublicationService::class)->unpublish($page->fresh(), $actor);

        $this->assertNull(app(HomepageContentResolver::class)->resolve());
    }

    public function test_clear_sets_designee_to_null(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage();
        $service = app(PublicationService::class);
        $service->setHomepage($page, $actor);

        $assignment = $service->setHomepage(null, $actor, expectedPageId: $page->id);

        $this->assertNull($assignment->page_id);
        $this->assertNull(app(HomepageContentResolver::class)->resolve());
    }
}
