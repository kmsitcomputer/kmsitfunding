<?php

namespace Tests\Feature\Cms;

use App\Models\Audit\AuditRecord;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Services\Content\Exceptions\PathConflictException;
use App\Services\Content\PathService;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 16 (PathService::release() audit wiring) coverage —
 * docs/implementation/IMP-005-cms.md section 13 RESERVATION POLICY: the
 * ONLY operation that un-reserves a path, always explicit and audited.
 */
class PathReleaseTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_release_sets_status_and_emits_content_path_released(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'x', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($page, $draft, $actor, '/to-release');

        $claim = CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->first();

        app(PathService::class)->release($claim, $actor);

        $claim->refresh();
        $this->assertSame('RELEASED', $claim->status);
        $this->assertNotNull($claim->released_at);

        $event = AuditRecord::where('event_type', 'content.path.released')->latest('id')->firstOrFail();
        $this->assertSame('/to-release', $event->metadata['path']);
        $this->assertSame('CURRENT', $event->metadata['purpose']);
        $this->assertSame('page', $event->metadata['owner_type']);
        $this->assertSame($page->id, $event->metadata['owner_id']);
        $this->assertSame('GOVERNED_RELEASE', $event->metadata['reason']);
        $this->assertSame($actor->id, $event->actor_principal_id);
    }

    public function test_path_is_claimable_again_only_after_release(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $pageA = CmsPage::create([
            'title' => 'a', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
        $draftA = app(RevisionService::class)->createDraft($pageA, ['title' => 'a', 'body_html' => '<p>a</p>'], $actor);
        app(PublicationService::class)->publish($pageA, $draftA, $actor, '/contested');
        app(PublicationService::class)->unpublish($pageA->fresh(), $actor);
        app(PublicationService::class)->archive($pageA->fresh(), $actor);

        $pageB = CmsPage::create([
            'title' => 'b', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
        $draftB = app(RevisionService::class)->createDraft($pageB, ['title' => 'b', 'body_html' => '<p>b</p>'], $actor);

        // Before release: still reserved, even though pageA is ARCHIVED.
        try {
            app(PublicationService::class)->publish($pageB, $draftB, $actor, '/contested');
            $this->fail('Expected a path conflict before release');
        } catch (PathConflictException) {
            // expected
        }

        $claim = CmsPath::where('page_id', $pageA->id)->where('purpose', 'CURRENT')->first();
        app(PathService::class)->release($claim, $actor);

        $published = app(PublicationService::class)->publish($pageB->fresh(), $draftB->fresh(), $actor, '/contested');
        $this->assertSame('PUBLISHED', $published->status);
    }
}
