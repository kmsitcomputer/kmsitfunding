<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 8 (PublicationService::archive()) coverage — section 27
 * item 1: archive releases nothing, and clears a dangling homepage
 * designation in the same transaction.
 */
class PublicationArchiveTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeDraftPage(): CmsPage
    {
        $actor = $this->makeUnauthorizedActor();

        return CmsPage::create([
            'title' => 'x',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    public function test_archive_from_draft_succeeds(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makeDraftPage();

        $archived = app(PublicationService::class)->archive($page, $actor);

        $this->assertSame('ARCHIVED', $archived->status);
    }

    public function test_archive_from_retired_succeeds_and_retains_path_reservation(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makeDraftPage();
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'x', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($page, $draft, $actor, '/archive-me');
        app(PublicationService::class)->unpublish($page->fresh(), $actor);

        $archived = app(PublicationService::class)->archive($page->fresh(), $actor);

        $this->assertSame('ARCHIVED', $archived->status);

        $claim = CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->first();
        $this->assertSame('ACTIVE', $claim->status, 'archive releases NOTHING (section 27)');
    }

    public function test_archive_from_published_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makeDraftPage();
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'x', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($page, $draft, $actor, '/live');

        $this->expectException(PublicationValidationException::class);
        app(PublicationService::class)->archive($page->fresh(), $actor);
    }

    public function test_archiving_the_homepage_designee_clears_the_designation(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makeDraftPage();
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'x', 'body_html' => '<p>x</p>'], $actor);
        $published = app(PublicationService::class)->publish($page, $draft, $actor, '/home');
        app(PublicationService::class)->setHomepage($published, $actor);
        app(PublicationService::class)->unpublish($published->fresh(), $actor);

        app(PublicationService::class)->archive($page->fresh(), $actor);

        $assignment = CmsHomepageAssignment::find(1);
        $this->assertNull($assignment->page_id);
    }

    public function test_archiving_a_page_that_is_not_the_homepage_designee_leaves_designation_untouched(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $homepagePage = $this->makeDraftPage();
        $homepageDraft = app(RevisionService::class)->createDraft($homepagePage, ['title' => 'home', 'body_html' => '<p>h</p>'], $actor);
        $publishedHomepage = app(PublicationService::class)->publish($homepagePage, $homepageDraft, $actor, '/home-'.uniqid());
        app(PublicationService::class)->setHomepage($publishedHomepage, $actor);

        $otherPage = $this->makeDraftPage();
        app(PublicationService::class)->archive($otherPage, $actor);

        $assignment = CmsHomepageAssignment::find(1);
        $this->assertSame($publishedHomepage->id, $assignment->page_id);
    }
}
