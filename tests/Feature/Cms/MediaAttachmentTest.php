<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsMediaReference;
use App\Models\Cms\CmsPage;
use App\Services\Content\Exceptions\RevisionValidationException;
use App\Services\Content\MediaService;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 14 (media attachment protocol) coverage — docs/implementation/
 * IMP-005-cms.md section 19. Mirrors the M7/M8/M16-style intent: reference
 * rows commit atomically with the payload, drops are scoped to the editing
 * revision only, and a superseded revision's reference is never released by
 * a later draft's edit.
 */
class MediaAttachmentTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media.disk'));
    }

    private function makePage(): CmsPage
    {
        $actor = $this->makeUnauthorizedActor();

        return CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
    }

    public function test_embedding_a_token_creates_an_active_body_reference(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $actor);
        $page = $this->makePage();

        $revision = app(RevisionService::class)->createDraft($page, [
            'title' => 'x',
            'body_html' => "<p>hi <img data-media=\"{$asset->ulid}\" alt=\"x\"></p>",
        ], $actor);

        $reference = CmsMediaReference::where('media_asset_id', $asset->id)
            ->where('owner_revision_id', $revision->id)
            ->where('field_path', 'body_html')
            ->first();

        $this->assertNotNull($reference);
        $this->assertSame('ACTIVE', $reference->status);
        $this->assertSame('BODY_TOKEN', $reference->reference_kind);
        $this->assertSame($page->id, $reference->owner_page_id);
    }

    public function test_og_image_token_resolves_to_internal_id_and_creates_seo_reference(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('og.jpg', 10, 10), $actor);
        $page = $this->makePage();

        $revision = app(RevisionService::class)->createDraft($page, [
            'title' => 'x',
            'body_html' => '<p>no images here</p>',
            'og_image_token' => $asset->ulid,
        ], $actor);

        $this->assertSame($asset->id, $revision->og_image_asset_id);

        $reference = CmsMediaReference::where('media_asset_id', $asset->id)
            ->where('owner_revision_id', $revision->id)
            ->where('field_path', 'og_image_asset_id')
            ->first();

        $this->assertNotNull($reference);
        $this->assertSame('SEO_IMAGE', $reference->reference_kind);
    }

    public function test_unknown_token_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $this->expectException(RevisionValidationException::class);
        app(RevisionService::class)->createDraft($page, [
            'title' => 'x',
            'body_html' => '<p><img data-media="01J9ZK3V7Q4XW2N8M5R6T7B1C2" alt="x"></p>',
        ], $actor);
    }

    public function test_archived_asset_token_is_rejected_as_not_attachable(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $mediaService = app(MediaService::class);
        $asset = $mediaService->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $actor);
        $mediaService->archive($asset, $actor);
        $page = $this->makePage();

        $this->expectException(RevisionValidationException::class);
        app(RevisionService::class)->createDraft($page, [
            'title' => 'x',
            'body_html' => "<p><img data-media=\"{$asset->ulid}\" alt=\"x\"></p>",
        ], $actor);
    }

    public function test_removing_a_token_on_edit_releases_that_revisions_reference_only(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $actor);
        $page = $this->makePage();
        $revisionService = app(RevisionService::class);

        $draft = $revisionService->createDraft($page, [
            'title' => 'x',
            'body_html' => "<p><img data-media=\"{$asset->ulid}\" alt=\"x\"></p>",
        ], $actor);

        $updated = $revisionService->editDraft($draft, ['body_html' => '<p>no image anymore</p>'], expectedEditVersion: 0);

        $reference = CmsMediaReference::where('media_asset_id', $asset->id)
            ->where('owner_revision_id', $updated->id)
            ->first();

        $this->assertSame('RELEASED', $reference->status);
        $this->assertNotNull($reference->released_at);
    }

    public function test_a_superseded_revisions_reference_is_never_released_by_a_later_drafts_edit(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $actor);
        $page = $this->makePage();
        $revisionService = app(RevisionService::class);
        $publicationService = app(PublicationService::class);

        $v1 = $revisionService->createDraft($page, [
            'title' => 'v1',
            'body_html' => "<p><img data-media=\"{$asset->ulid}\" alt=\"x\"></p>",
        ], $actor);
        $publicationService->publish($page, $v1, $actor, '/x');

        $v2 = $revisionService->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>no image</p>'], $actor);
        $revisionService->editDraft($v2, [], expectedEditVersion: 0); // no-op edit, media untouched (body_html not in payload)

        $v1Reference = CmsMediaReference::where('media_asset_id', $asset->id)
            ->where('owner_revision_id', $v1->id)
            ->first();

        $this->assertSame('ACTIVE', $v1Reference->status, "v1's (now PUBLISHED) reference must stay ACTIVE forever");
    }

    public function test_media_free_edit_touches_no_media_locks_or_references(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $revisionService = app(RevisionService::class);
        $draft = $revisionService->createDraft($page, ['title' => 'x', 'body_html' => '<p>plain text</p>'], $actor);

        $updated = $revisionService->editDraft($draft, ['title' => 'x edited'], expectedEditVersion: 0);

        $this->assertSame('x edited', $updated->title);
        $this->assertSame(0, CmsMediaReference::where('owner_revision_id', $updated->id)->count());
    }

    public function test_embedding_the_same_asset_twice_creates_only_one_reference_row(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $actor);
        $page = $this->makePage();

        $revision = app(RevisionService::class)->createDraft($page, [
            'title' => 'x',
            'body_html' => "<p><img data-media=\"{$asset->ulid}\" alt=\"1\"><img data-media=\"{$asset->ulid}\" alt=\"2\"></p>",
        ], $actor);

        $this->assertSame(
            1,
            CmsMediaReference::where('media_asset_id', $asset->id)->where('owner_revision_id', $revision->id)->count()
        );
    }
}
