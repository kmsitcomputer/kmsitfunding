<?php

namespace Tests\Feature\Cms;

use App\Models\Audit\AuditRecord;
use App\Models\Cms\CmsPage;
use App\Services\Content\ArticleService;
use App\Services\Content\MediaService;
use App\Services\Content\PageService;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 13 (audit emission wiring) coverage — proves actual
 * AuditRecord rows are written with the correct shape at the existing
 * PageService/ArticleService/PublicationService/MediaService call sites,
 * mirroring docs/implementation/IMP-005-cms.md section 29's A-series intent
 * (A1 table-driven mapping, A4/A4b/A4c conditional pairing, A7 no-null).
 */
class ContentAuditEmissionTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media.disk'));
    }

    private function latestEvent(string $type): AuditRecord
    {
        return AuditRecord::where('event_type', $type)->latest('id')->firstOrFail();
    }

    public function test_page_create_emits_content_page_created_with_no_null_values(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'About', 'body_html' => '<p>x</p>'], $actor);

        $event = $this->latestEvent('content.page.created');

        $this->assertSame('cms_page', $event->subject_type);
        $this->assertSame($page->id, $event->subject_id);
        $this->assertSame($actor->id, $event->actor_principal_id);
        $this->assertIsInt($event->metadata['revision_id']);
        foreach ($event->metadata as $value) {
            $this->assertNotNull($value);
        }
    }

    public function test_article_create_emits_article_type_in_metadata(): void
    {
        $actor = $this->makeUnauthorizedActor();
        app(ArticleService::class)->create(['title' => 'News', 'body_html' => '<p>x</p>', 'article_type' => 'NEWS'], $actor);

        $event = $this->latestEvent('content.article.created');

        $this->assertSame('NEWS', $event->metadata['article_type']);
    }

    public function test_first_publication_omits_previous_revision_id_and_previous_path(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'x', 'body_html' => '<p>x</p>'], $actor);
        $draft = $page->currentDraft()->first();

        app(PublicationService::class)->publish($page, $draft, $actor, '/first-pub');

        $event = $this->latestEvent('content.page.published');

        $this->assertSame('NEW', $event->metadata['path_change']);
        $this->assertArrayNotHasKey('previous_revision_id', $event->metadata);
        $this->assertArrayNotHasKey('previous_path', $event->metadata);
        $this->assertArrayNotHasKey('redirect_created', $event->metadata);
    }

    public function test_same_path_replacement_carries_previous_revision_id_and_no_path_keys(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $draft1 = $page->currentDraft()->first();
        app(PublicationService::class)->publish($page, $draft1, $actor, '/same');

        $draft2 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft2, $actor, '/same');

        $event = $this->latestEvent('content.page.published');

        $this->assertSame('UNCHANGED', $event->metadata['path_change']);
        $this->assertSame($draft1->id, $event->metadata['previous_revision_id']);
        $this->assertArrayNotHasKey('previous_path', $event->metadata);
        $this->assertArrayNotHasKey('redirect_created', $event->metadata);
    }

    public function test_rename_carries_previous_path_and_redirect_created(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $draft1 = $page->currentDraft()->first();
        app(PublicationService::class)->publish($page, $draft1, $actor, '/old-path');

        $draft2 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft2, $actor, '/new-path');

        $event = $this->latestEvent('content.page.published');

        $this->assertSame('RENAMED', $event->metadata['path_change']);
        $this->assertSame('/old-path', $event->metadata['previous_path']);
        $this->assertSame(1, $event->metadata['redirect_created']);
    }

    public function test_two_renames_emit_the_correct_previous_path_each_time(): void
    {
        // Regression case for the "ORDER BY id DESC" bug this slice fixed:
        // a SECOND rename must report the path it was JUST renamed from,
        // not the owner's oldest redirect row.
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/path-a');

        $draft2 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft2, $actor, '/path-b');

        $draft3 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v3', 'body_html' => '<p>3</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft3, $actor, '/path-c');

        $event = $this->latestEvent('content.page.published');

        $this->assertSame('/path-b', $event->metadata['previous_path']);
    }

    public function test_publish_at_designated_homepage_reports_homepage_designated_true(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $published = app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/home');
        app(PublicationService::class)->setHomepage($published, $actor);

        $draft2 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft2, $actor, '/home');

        $event = $this->latestEvent('content.page.published');

        $this->assertSame(1, $event->metadata['homepage_designated']);
    }

    public function test_article_published_event_never_carries_homepage_designated(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $article = app(ArticleService::class)->create(['title' => 'x', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($article, $article->currentDraft()->first(), $actor, '/article-x');

        $event = $this->latestEvent('content.article.published');

        $this->assertArrayNotHasKey('homepage_designated', $event->metadata);
        $this->assertSame('ARTICLE', $event->metadata['article_type']);
    }

    public function test_unpublish_emits_unchanged_path_change(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $published = app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/x');

        app(PublicationService::class)->unpublish($published->fresh(), $actor);

        $event = $this->latestEvent('content.page.unpublished');

        $this->assertSame('PUBLISHED', $event->metadata['from_status']);
        $this->assertSame('RETIRED', $event->metadata['to_status']);
        $this->assertSame('UNCHANGED', $event->metadata['path_change']);
        $this->assertSame('/x', $event->metadata['path']);
    }

    public function test_archive_of_homepage_designee_emits_cleared_by_archive(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $published = app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/x');
        app(PublicationService::class)->setHomepage($published, $actor);
        app(PublicationService::class)->unpublish($published->fresh(), $actor);

        app(PublicationService::class)->archive($page->fresh(), $actor);

        $event = $this->latestEvent('content.page.archived');

        $this->assertSame('CLEARED_BY_ARCHIVE', $event->metadata['homepage_designation']);
        $this->assertSame('/x', $event->metadata['path_claim_retained']);
    }

    public function test_archive_of_a_never_published_draft_omits_path_claim_retained(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);

        app(PublicationService::class)->archive($page, $actor);

        $event = $this->latestEvent('content.page.archived');

        $this->assertArrayNotHasKey('path_claim_retained', $event->metadata);
        $this->assertSame('NOT_DESIGNATED', $event->metadata['homepage_designation']);
    }

    public function test_media_upload_emits_expected_metadata(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);

        $event = $this->latestEvent('content.media.uploaded');

        $this->assertSame($asset->ulid, $event->metadata['asset_ulid']);
        $this->assertSame('image/jpeg', $event->metadata['mime_type']);
        $this->assertArrayNotHasKey('duplicate_asset_ulid', $event->metadata);
    }

    public function test_media_upload_duplicate_carries_duplicate_asset_ulid(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $service = app(MediaService::class);
        $first = $service->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        $service->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);

        $event = $this->latestEvent('content.media.uploaded');

        $this->assertSame($first->ulid, $event->metadata['duplicate_asset_ulid']);
    }

    public function test_media_archive_emits_none_when_no_references_exist(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $service = app(MediaService::class);
        $asset = $service->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        $service->archive($asset, $uploader);

        $event = $this->latestEvent('content.media.archived');

        $this->assertSame('NONE', $event->metadata['prior_references']);
    }

    public function test_re_archiving_does_not_emit_a_duplicate_event(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $service = app(MediaService::class);
        $asset = $service->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        $service->archive($asset, $uploader);
        $countAfterFirst = AuditRecord::where('event_type', 'content.media.archived')->count();

        $service->archive($asset->fresh(), $uploader);

        $this->assertSame($countAfterFirst, AuditRecord::where('event_type', 'content.media.archived')->count());
    }

    public function test_homepage_first_assignment_is_assigned_operation(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $published = app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/x');

        app(PublicationService::class)->setHomepage($published, $actor);

        $event = $this->latestEvent('content.homepage.assigned');

        $this->assertSame('ASSIGNED', $event->metadata['operation']);
        $this->assertSame($published->id, $event->metadata['page_id']);
        $this->assertArrayNotHasKey('previous_page_id', $event->metadata);
    }

    public function test_homepage_replace_is_replaced_operation_with_previous_page_id(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $pageA = app(PageService::class)->create(['title' => 'a', 'body_html' => '<p>a</p>'], $actor);
        $pubA = app(PublicationService::class)->publish($pageA, $pageA->currentDraft()->first(), $actor, '/a');
        $pageB = app(PageService::class)->create(['title' => 'b', 'body_html' => '<p>b</p>'], $actor);
        $pubB = app(PublicationService::class)->publish($pageB, $pageB->currentDraft()->first(), $actor, '/b');

        app(PublicationService::class)->setHomepage($pubA, $actor);
        app(PublicationService::class)->setHomepage($pubB, $actor, expectedPageId: $pubA->id);

        $event = $this->latestEvent('content.homepage.assigned');

        $this->assertSame('REPLACED', $event->metadata['operation']);
        $this->assertSame($pubA->id, $event->metadata['previous_page_id']);
        $this->assertSame($pubA->id, $event->metadata['expected_page_id']);
    }

    public function test_homepage_clear_omits_page_id_and_page_ulid(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        $published = app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/x');
        app(PublicationService::class)->setHomepage($published, $actor);

        app(PublicationService::class)->setHomepage(null, $actor, expectedPageId: $published->id);

        $event = $this->latestEvent('content.homepage.assigned');

        $this->assertSame('CLEARED', $event->metadata['operation']);
        $this->assertArrayNotHasKey('page_id', $event->metadata);
        $this->assertArrayNotHasKey('page_ulid', $event->metadata);
    }

    public function test_no_content_event_ever_contains_a_null_metadata_value(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);
        app(PublicationService::class)->publish($page, $page->currentDraft()->first(), $actor, '/null-check');
        app(PublicationService::class)->unpublish($page->fresh(), $actor);
        app(PublicationService::class)->archive($page->fresh(), $actor);

        $events = AuditRecord::where('event_type', 'like', 'content.%')->get();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            foreach ($event->metadata ?? [] as $key => $value) {
                $this->assertNotNull($value, "{$event->event_type}.{$key} must never be null");
            }
        }
    }
}
