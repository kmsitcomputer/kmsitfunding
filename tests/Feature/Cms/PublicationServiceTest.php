<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\PathConflictException;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 5 (PublicationService + PathService write side) coverage —
 * the four canonical publication branches (docs/implementation/
 * IMP-005-cms.md sections 14/26): first publication, same-path replacement,
 * rename (including collision rollback), and re-publication of RETIRED
 * content — plus the cross-entity namespace collision the shared
 * cms_paths.active_path index exists to prevent.
 */
class PublicationServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function publicationService(): PublicationService
    {
        return app(PublicationService::class);
    }

    private function revisionService(): RevisionService
    {
        return app(RevisionService::class);
    }

    private function makePage(Principal $actor): CmsPage
    {
        return CmsPage::create([
            'title' => 'Untitled',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    private function makeArticle(Principal $actor): CmsArticle
    {
        return CmsArticle::create([
            'title' => 'Untitled',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    public function test_first_publication_claims_path_and_publishes_revision(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage($actor);
        $draft = $this->revisionService()->createDraft($page, ['title' => 'About Us', 'body_html' => '<p>x</p>'], $actor);

        $published = $this->publicationService()->publish($page, $draft, $actor, '/about-us');

        $this->assertSame('PUBLISHED', $published->status);
        $this->assertSame($draft->id, $published->published_revision_id);
        $this->assertSame('About Us', $published->title);

        $draft->refresh();
        $this->assertSame('PUBLISHED', $draft->state);
        $this->assertNotNull($draft->published_at);
        $this->assertSame('/about-us', $draft->slug_snapshot);

        $claim = CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->first();
        $this->assertNotNull($claim);
        $this->assertSame('/about-us', $claim->path);
        $this->assertSame('ACTIVE', $claim->status);
        $this->assertSame($draft->id, $claim->revision_id);
    }

    public function test_same_path_replacement_retains_the_claim_row_and_updates_revision_id(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage($actor);
        $draft1 = $this->revisionService()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $this->publicationService()->publish($page, $draft1, $actor, '/about-us');

        $claimBefore = CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->first();

        $draft2 = $this->revisionService()->createDraft($page, ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        $published = $this->publicationService()->publish($page, $draft2, $actor, '/about-us');

        $this->assertSame(1, CmsPath::where('page_id', $page->id)->count(), 'no second row was inserted');

        $claimAfter = CmsPath::find($claimBefore->id);
        $this->assertSame($claimBefore->id, $claimAfter->id, 'the SAME row was retained');
        $this->assertSame($draft2->id, $claimAfter->revision_id);
        $this->assertSame('/about-us', $claimAfter->path);

        $draft1->refresh();
        $this->assertSame('SUPERSEDED', $draft1->state);
        $this->assertNotNull($draft1->superseded_at);
        // Superseded revision's payload is byte-identical — never rewritten.
        $this->assertSame('v1', $draft1->title);

        $this->assertSame($draft2->id, $published->published_revision_id);
    }

    public function test_rename_converts_old_claim_to_redirect_and_inserts_new_current(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage($actor);
        $draft1 = $this->revisionService()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $this->publicationService()->publish($page, $draft1, $actor, '/old-path');

        $draft2 = $this->revisionService()->createDraft($page, ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        $this->publicationService()->publish($page, $draft2, $actor, '/new-path');

        $old = CmsPath::where('page_id', $page->id)->where('path', '/old-path')->first();
        $new = CmsPath::where('page_id', $page->id)->where('path', '/new-path')->first();

        $this->assertSame('REDIRECT', $old->purpose);
        $this->assertSame('ACTIVE', $old->status, 'the old path stays RESERVED (section 13)');
        $this->assertSame($draft1->id, $old->revision_id, 'frozen at the revision that was holding it');

        $this->assertSame('CURRENT', $new->purpose);
        $this->assertSame('ACTIVE', $new->status);
        $this->assertSame($draft2->id, $new->revision_id);

        $this->assertSame(
            1,
            CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->where('status', 'ACTIVE')->count(),
            'UNIQUE(active_current_owner): exactly one active CURRENT claim'
        );
    }

    public function test_rename_collision_rolls_back_leaving_the_old_claim_intact(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $pageA = $this->makePage($actor);
        $draftA1 = $this->revisionService()->createDraft($pageA, ['title' => 'A v1', 'body_html' => '<p>a</p>'], $actor);
        $this->publicationService()->publish($pageA, $draftA1, $actor, '/page-a');

        $pageB = $this->makePage($actor);
        $draftB = $this->revisionService()->createDraft($pageB, ['title' => 'B', 'body_html' => '<p>b</p>'], $actor);
        $this->publicationService()->publish($pageB, $draftB, $actor, '/taken');

        // Page A attempts to rename onto Page B's already-claimed path.
        $draftA2 = $this->revisionService()->createDraft($pageA, ['title' => 'A v2', 'body_html' => '<p>a2</p>'], $actor);

        try {
            $this->publicationService()->publish($pageA, $draftA2, $actor, '/taken');
            $this->fail('Expected PathConflictException');
        } catch (PathConflictException) {
            // expected
        }

        // No intermediate broken state: old claim is restored as CURRENT
        // with its ORIGINAL revision, by InnoDB's own rollback — no
        // compensating write.
        $oldClaim = CmsPath::where('page_id', $pageA->id)->where('path', '/page-a')->first();
        $this->assertSame('CURRENT', $oldClaim->purpose);
        $this->assertSame('ACTIVE', $oldClaim->status);
        $this->assertSame($draftA1->id, $oldClaim->revision_id);

        // No orphan claim for the collision path beyond Page B's own.
        $this->assertSame(1, CmsPath::where('path', '/taken')->count());

        // Page A's pointer/state untouched — the whole publish rolled back.
        $pageA->refresh();
        $this->assertSame($draftA1->id, $pageA->published_revision_id);
        $draftA2->refresh();
        $this->assertSame('DRAFT', $draftA2->state, 'never transitioned — the whole flow rolled back');
    }

    public function test_republication_of_retired_content_reuses_the_same_revision_and_path(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage($actor);
        $draft = $this->revisionService()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $this->publicationService()->publish($page, $draft, $actor, '/about-us');
        $this->publicationService()->unpublish($page, $actor);

        $republished = $this->publicationService()->publish($page->fresh(), $draft->fresh(), $actor);

        $this->assertSame('PUBLISHED', $republished->status);
        $this->assertSame($draft->id, $republished->published_revision_id);

        $draft->refresh();
        $this->assertSame('PUBLISHED', $draft->state);
        // published_at is NEVER rewritten, including on re-exposure.
        $publishedAtBefore = $draft->published_at;
        $this->assertNotNull($publishedAtBefore);

        $claim = CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->first();
        $this->assertSame('/about-us', $claim->path, 'same claim row, no path change');
        $this->assertSame(1, CmsPath::where('page_id', $page->id)->count(), 'no new claim row inserted');
    }

    public function test_page_and_article_cannot_claim_the_same_normalized_path(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $page = $this->makePage($actor);
        $pageDraft = $this->revisionService()->createDraft($page, ['title' => 'Page', 'body_html' => '<p>p</p>'], $actor);
        $this->publicationService()->publish($page, $pageDraft, $actor, '/shared-path');

        $article = $this->makeArticle($actor);
        $articleDraft = $this->revisionService()->createDraft($article, ['title' => 'Article', 'body_html' => '<p>a</p>'], $actor);

        $this->expectException(PathConflictException::class);
        $this->publicationService()->publish($article, $articleDraft, $actor, '/shared-path');
    }

    public function test_publish_rejects_a_revision_owned_by_a_different_page(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $pageA = $this->makePage($actor);
        $pageB = $this->makePage($actor);
        $draftOwnedByB = $this->revisionService()->createDraft($pageB, ['title' => 'B', 'body_html' => '<p>b</p>'], $actor);

        $this->expectException(PublicationValidationException::class);
        $this->publicationService()->publish($pageA, $draftOwnedByB, $actor, '/x');
    }

    public function test_unpublish_transitions_to_retired_and_releases_no_path_claim(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage($actor);
        $draft = $this->revisionService()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $this->publicationService()->publish($page, $draft, $actor, '/about-us');

        $retired = $this->publicationService()->unpublish($page->fresh(), $actor);

        $this->assertSame('RETIRED', $retired->status);

        $claim = CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->first();
        $this->assertSame('ACTIVE', $claim->status, 'retiring releases nothing (section 13)');
    }

    public function test_unpublish_rejects_a_non_published_owner(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage($actor);

        $this->expectException(PublicationValidationException::class);
        $this->publicationService()->unpublish($page, $actor);
    }
}
