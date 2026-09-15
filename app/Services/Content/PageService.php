<?php

namespace App\Services\Content;

use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\RevisionValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-005 — orchestrates Page identity creation + its initial draft
 * (docs/implementation/IMP-005-cms.md section 18). Delegates EVERY
 * revision-row write to RevisionService — this service never writes
 * cms_content_revisions itself and never assigns a revision pointer
 * directly (section 18 write-boundary rule).
 *
 * Authorization (content.create/content.update via ContentPagePolicy) is a
 * caller-side (HTTP boundary) concern, matching this codebase's existing
 * pattern — no domain service in this codebase re-checks permissions
 * internally (RbacManagementPolicy is likewise invoked at the boundary, not
 * from inside RoleAssignmentService). Editing authority vs publishing
 * authority separation (HD-IMP005-01/Q29) is therefore enforced by which
 * policy method the caller invokes before calling this service, not by
 * anything in here.
 */
class PageService
{
    /**
     * The closed fields_changed vocabulary (section 12 "Derived-key
     * definitions") — exactly section 13's revision payload columns minus
     * the identity/structural ones that are never edited.
     */
    private const TRACKED_PAYLOAD_FIELDS = [
        'title', 'excerpt', 'body_html', 'meta_title', 'meta_description',
        'og_title', 'og_description', 'og_image_asset_id', 'no_index', 'slug_snapshot',
    ];

    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly ContentAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $revisionPayload  see RevisionService::createDraft()
     */
    public function create(array $revisionPayload, Principal $actor): CmsPage
    {
        return DB::transaction(function () use ($revisionPayload, $actor) {
            $page = new CmsPage;
            $page->forceFill([
                'title' => $revisionPayload['title'],
                'status' => 'DRAFT',
                'created_by_principal_id' => $actor->id,
                'updated_by_principal_id' => $actor->id,
            ]);
            $page->save();

            $revision = $this->revisionService->createDraft($page, $revisionPayload, $actor);

            $this->auditLogger->recordPageCreated($page->id, ['revision_id' => $revision->id], $actor);

            return $page->fresh();
        });
    }

    /**
     * Edit the owner's current draft. Requires an active DRAFT to exist —
     * use RevisionService::rollbackByCopy() directly to start a fresh draft
     * from published/historical content; that is not duplicated here.
     */
    public function update(CmsPage $page, array $revisionPayload, int $expectedEditVersion, Principal $actor): CmsContentRevision
    {
        return DB::transaction(function () use ($page, $revisionPayload, $expectedEditVersion, $actor) {
            $draft = $page->currentDraft()->lockForUpdate()->first();

            if ($draft === null) {
                throw new RevisionValidationException(
                    'no_active_draft',
                    "Page {$page->id} has no active DRAFT revision to edit."
                );
            }

            $updated = $this->revisionService->editDraft($draft, $revisionPayload, $expectedEditVersion);

            $page->forceFill(['updated_by_principal_id' => $actor->id])->save();

            $fieldsChanged = array_values(array_intersect(array_keys($revisionPayload), self::TRACKED_PAYLOAD_FIELDS));
            $metadata = ['revision_id' => $updated->id, 'fields_changed' => $fieldsChanged];

            if ($updated->slug_snapshot !== null) {
                $metadata['slug_snapshot'] = $updated->slug_snapshot;
            }

            $this->auditLogger->recordPageUpdated($page->id, $metadata, $actor);

            return $updated;
        });
    }
}
