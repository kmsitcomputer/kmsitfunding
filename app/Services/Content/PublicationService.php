<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\HomepageAssignmentConflictException;
use App\Services\Content\Exceptions\PathConflictException;
use App\Services\Content\Exceptions\PublicationValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-005 — the ONLY writer of revision LIFECYCLE columns and of the
 * identity revision pointers (docs/implementation/IMP-005-cms.md section 18).
 * publish()/supersede() never write a payload column (section 11 layer-2
 * whitelist) — RevisionService owns the payload, this service owns the
 * transition.
 *
 * publish() covers ALL THREE branches (first publication, same-path
 * replacement, rename) plus re-publication of RETIRED content.
 * unpublish()/archive()/setHomepage() are also implemented. Every mutation
 * emits its canonical content.* audit event (section 12) inside the SAME
 * transaction via ContentAuditLogger.
 *
 * NOT YET IN THIS SLICE (documented, not silently dropped): the media
 * asset/reference tiers (section 19/26 tiers 1-2) — publish() does not lock
 * or verify media attachability. Scheduled execution (flow 5) reusing this
 * same publish()/unpublish() — and the resulting schedule-key metadata,
 * actor=system — lands with the scheduler slice; every audit call below
 * therefore currently omits the five schedule-provenance keys, which is
 * correct for a manual (Human) transition but not yet exercised for a
 * scheduled one.
 */
class PublicationService
{
    public function __construct(
        private readonly PathService $pathService,
        private readonly ContentAuditLogger $auditLogger,
    ) {}

    /**
     * @param  string|null  $rawTargetPath  the path to publish at, raw (un-normalized) input.
     *                                      Required on first publication (no existing CURRENT
     *                                      claim to default to). Omit (or pass the identical
     *                                      current path) for a same-path replacement /
     *                                      re-publication; pass a DIFFERENT path to rename.
     */
    public function publish(
        CmsPage|CmsArticle $owner,
        CmsContentRevision $candidateRevision,
        Principal $actor,
        ?string $rawTargetPath = null,
    ): CmsPage|CmsArticle {
        return DB::transaction(function () use ($owner, $candidateRevision, $actor, $rawTargetPath) {
            $lockedOwner = $owner::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $lockedOwner->status;
            $lockedCandidate = CmsContentRevision::query()->whereKey($candidateRevision->id)->lockForUpdate()->firstOrFail();

            $ownerColumn = $lockedOwner instanceof CmsPage ? 'page_id' : 'article_id';

            if ($lockedCandidate->{$ownerColumn} !== $lockedOwner->id) {
                throw new PublicationValidationException(
                    'revision_owner_mismatch',
                    "Revision {$lockedCandidate->id} does not belong to this owner."
                );
            }

            $isRepublishOfSameRevision = $lockedCandidate->state === 'PUBLISHED'
                && $lockedOwner->published_revision_id === $lockedCandidate->id;

            if ($lockedCandidate->state !== 'DRAFT' && ! $isRepublishOfSameRevision) {
                throw new PublicationValidationException(
                    'invalid_candidate_state',
                    "Revision {$lockedCandidate->id} is state={$lockedCandidate->state} and is not a valid publish target."
                );
            }

            $previousPublished = null;

            if ($lockedOwner->published_revision_id !== null && $lockedOwner->published_revision_id !== $lockedCandidate->id) {
                $previousPublished = CmsContentRevision::query()
                    ->whereKey($lockedOwner->published_revision_id)
                    ->lockForUpdate()
                    ->first();
            }

            $existingClaim = $this->pathService->lockCurrentClaim($lockedOwner);

            if ($rawTargetPath !== null) {
                $normalizedTarget = $this->pathService->validate($rawTargetPath);
            } elseif ($existingClaim !== null) {
                $normalizedTarget = $existingClaim->path;
            } else {
                throw new PublicationValidationException(
                    'target_path_required',
                    'A target path is required for first publication.'
                );
            }

            $branch = match (true) {
                $existingClaim === null => 'A',
                $existingClaim->path === $normalizedTarget => 'B',
                default => 'C',
            };

            if ($lockedCandidate->state === 'DRAFT') {
                $lockedCandidate->forceFill([
                    'state' => 'PUBLISHED',
                    'published_at' => now(),
                    'slug_snapshot' => $normalizedTarget,
                ])->save();
            }
            // Already PUBLISHED (re-publish of RETIRED content, same revision):
            // published_at and slug_snapshot are frozen and must not be rewritten.

            if ($previousPublished !== null) {
                $previousPublished->forceFill([
                    'state' => 'SUPERSEDED',
                    'superseded_at' => now(),
                ])->save();
            }

            try {
                match ($branch) {
                    'A' => $this->pathService->claim($lockedOwner, $normalizedTarget, $lockedCandidate),
                    'B' => $this->pathService->retainAndUpdateRevision($existingClaim, $lockedCandidate),
                    'C' => $this->renameClaim($existingClaim, $lockedOwner, $normalizedTarget, $lockedCandidate),
                };
            } catch (QueryException $e) {
                throw new PathConflictException(
                    "Path '{$normalizedTarget}' is already claimed by another owner.", previous: $e
                );
            }

            $lockedOwner->forceFill([
                'published_revision_id' => $lockedCandidate->id,
                'status' => 'PUBLISHED',
                'title' => $lockedCandidate->title,
                'updated_by_principal_id' => $actor->id,
            ]);

            if ($lockedOwner instanceof CmsArticle) {
                $lockedOwner->excerpt = $lockedCandidate->excerpt;

                if ($lockedOwner->first_published_at === null) {
                    $lockedOwner->first_published_at = now();
                }
            }

            $lockedOwner->save();

            $this->emitPublished($lockedOwner, $lockedCandidate, $fromStatus, $branch, $previousPublished, $existingClaim, $actor);

            return $lockedOwner->fresh();
        });
    }

    /**
     * Withdraw: PUBLISHED -> RETIRED. Releases NOTHING (section 13
     * RESERVATION POLICY) — the published revision, its path claim, and its
     * pointer are all untouched; only the identity's status changes.
     */
    public function unpublish(CmsPage|CmsArticle $owner, Principal $actor): CmsPage|CmsArticle
    {
        return DB::transaction(function () use ($owner, $actor) {
            $lockedOwner = $owner::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if ($lockedOwner->status !== 'PUBLISHED') {
                throw new PublicationValidationException(
                    'not_published',
                    "Owner {$lockedOwner->id} is status={$lockedOwner->status}, not PUBLISHED."
                );
            }

            $lockedOwner->forceFill([
                'status' => 'RETIRED',
                'updated_by_principal_id' => $actor->id,
            ])->save();

            $claim = $this->pathService->lockCurrentClaim($lockedOwner);

            $metadata = [
                'revision_id' => $lockedOwner->published_revision_id,
                'from_status' => 'PUBLISHED',
                'to_status' => 'RETIRED',
                'path' => $claim->path ?? '',
                'path_change' => 'UNCHANGED',
            ];

            if ($lockedOwner instanceof CmsArticle) {
                $metadata['article_type'] = CmsContentRevision::find($lockedOwner->published_revision_id)?->article_type;
                $this->auditLogger->recordArticleUnpublished($lockedOwner->id, $metadata, $actor);
            } else {
                $this->auditLogger->recordPageUnpublished($lockedOwner->id, $metadata, $actor);
            }

            return $lockedOwner->fresh();
        });
    }

    /**
     * Terminal lifecycle transition (DRAFT|RETIRED -> ARCHIVED). Releases
     * NOTHING — path claims and media references are unaffected (section
     * 27 item 1); only the identity's status changes. If the owner is the
     * current homepage designee, the designation is CLEARED in the SAME
     * transaction (an archived page can never be silently left as a
     * dangling designation, section 27).
     */
    public function archive(CmsPage|CmsArticle $owner, Principal $actor): CmsPage|CmsArticle
    {
        return DB::transaction(function () use ($owner, $actor) {
            $lockedOwner = $owner::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $lockedOwner->status;

            if (! in_array($fromStatus, ['DRAFT', 'RETIRED'], true)) {
                throw new PublicationValidationException(
                    'invalid_archive_state',
                    "Owner {$lockedOwner->id} is status={$lockedOwner->status}; only DRAFT or RETIRED may be archived."
                );
            }

            $lockedOwner->forceFill([
                'status' => 'ARCHIVED',
                'updated_by_principal_id' => $actor->id,
            ])->save();

            $homepageDesignation = 'NOT_DESIGNATED';

            if ($lockedOwner instanceof CmsPage) {
                $assignment = CmsHomepageAssignment::query()->whereKey(1)->lockForUpdate()->first();

                if ($assignment !== null && $assignment->page_id === $lockedOwner->id) {
                    $assignment->update([
                        'page_id' => null,
                        'assigned_by_principal_id' => $actor->id,
                        'assigned_at' => now(),
                    ]);
                    $homepageDesignation = 'CLEARED_BY_ARCHIVE';
                }
            }

            $ownerColumn = $lockedOwner instanceof CmsPage ? 'page_id' : 'article_id';
            $redirectClaimsRetained = CmsPath::query()
                ->where($ownerColumn, $lockedOwner->id)
                ->where('purpose', 'REDIRECT')
                ->where('status', 'ACTIVE')
                ->count();
            $currentClaim = CmsPath::query()
                ->where($ownerColumn, $lockedOwner->id)
                ->where('purpose', 'CURRENT')
                ->where('status', 'ACTIVE')
                ->first();

            $metadata = [
                'from_status' => $fromStatus,
                'to_status' => 'ARCHIVED',
                'redirect_claims_retained' => $redirectClaimsRetained,
            ];

            if ($currentClaim !== null) {
                $metadata['path_claim_retained'] = $currentClaim->path;
            }

            if ($lockedOwner instanceof CmsArticle) {
                $metadata['article_type'] = $lockedOwner->currentDraft()->first()?->article_type
                    ?? CmsContentRevision::find($lockedOwner->published_revision_id)?->article_type;
                $this->auditLogger->recordArticleArchived($lockedOwner->id, $metadata, $actor);
            } else {
                $metadata['homepage_designation'] = $homepageDesignation;
                $this->auditLogger->recordPageArchived($lockedOwner->id, $metadata, $actor);
            }

            return $lockedOwner->fresh();
        });
    }

    /**
     * Assign, replace, or clear the singleton homepage designation (section
     * 26 flow 7). Pass $page = null to clear. The singleton row (id=1)
     * always exists (seeded by its migration), so there is never an empty
     * slot to race for — the lock target is always present.
     */
    public function setHomepage(?CmsPage $page, Principal $actor, ?int $expectedPageId = null): CmsHomepageAssignment
    {
        return DB::transaction(function () use ($page, $actor, $expectedPageId) {
            $assignment = CmsHomepageAssignment::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $previousPageId = $assignment->page_id;

            if ($expectedPageId !== null && $assignment->page_id !== $expectedPageId) {
                throw new HomepageAssignmentConflictException(
                    "Expected current designee {$expectedPageId} but it is ".
                    ($assignment->page_id ?? 'none').'.'
                );
            }

            if ($page !== null && $page->status === 'ARCHIVED') {
                throw new PublicationValidationException(
                    'homepage_target_archived',
                    "Page {$page->id} is ARCHIVED and may not be designated as homepage."
                );
            }

            $assignment->update([
                'page_id' => $page?->id,
                'assigned_by_principal_id' => $actor->id,
                'assigned_at' => now(),
            ]);

            $operation = match (true) {
                $page === null => 'CLEARED',
                $previousPageId === null => 'ASSIGNED',
                default => 'REPLACED',
            };

            $metadata = [
                'operation' => $operation,
                'previous_expected_matched' => 1,
            ];

            if ($page !== null) {
                $metadata['page_id'] = $page->id;
                $metadata['page_ulid'] = $page->ulid;
            }

            if ($previousPageId !== null) {
                $metadata['previous_page_id'] = $previousPageId;
            }

            if ($expectedPageId !== null) {
                $metadata['expected_page_id'] = $expectedPageId;
            }

            $this->auditLogger->recordHomepageAssigned($metadata, $actor);

            return $assignment->fresh();
        });
    }

    /**
     * Branch C (rename): convert the old claim to REDIRECT FIRST, then
     * insert the new CURRENT row. This order is FORCED by
     * UNIQUE(active_current_owner) — inserting first would collide with the
     * still-CURRENT old row (section 14).
     */
    private function renameClaim(CmsPath $existingClaim, CmsPage|CmsArticle $owner, string $normalizedTarget, CmsContentRevision $revision): void
    {
        $this->pathService->convertToRedirect($existingClaim);
        $this->pathService->claim($owner, $normalizedTarget, $revision);
    }

    private function emitPublished(
        CmsPage|CmsArticle $owner,
        CmsContentRevision $candidate,
        string $fromStatus,
        string $branch,
        ?CmsContentRevision $previousPublished,
        ?CmsPath $convertedClaim,
        Principal $actor,
    ): void {
        $pathChange = match ($branch) {
            'A' => 'NEW',
            'B' => 'UNCHANGED',
            'C' => 'RENAMED',
        };

        $metadata = [
            'revision_id' => $candidate->id,
            'from_status' => $fromStatus,
            'to_status' => 'PUBLISHED',
            'path' => $candidate->slug_snapshot,
            'path_change' => $pathChange,
        ];

        if ($previousPublished !== null) {
            $metadata['previous_revision_id'] = $previousPublished->id;
        }

        if ($branch === 'C') {
            // $convertedClaim is the EXACT row renameClaim() just flipped to
            // REDIRECT — its path is read from that object directly rather
            // than re-queried, because a re-query ordered by id would be
            // WRONG for an owner renamed more than once: converting a claim
            // never changes its id, so "most recently converted" is not
            // "highest id" once a second or later rename has happened.
            $metadata['previous_path'] = $convertedClaim->path;
            $metadata['redirect_created'] = 1;
        }

        if ($owner instanceof CmsPage) {
            $isHomepage = CmsHomepageAssignment::query()->whereKey(1)->value('page_id') === $owner->id;
            $metadata['homepage_designated'] = $isHomepage ? 1 : 0;
            $this->auditLogger->recordPagePublished($owner->id, $metadata, $actor);
        } else {
            $metadata['article_type'] = $candidate->article_type;
            $this->auditLogger->recordArticlePublished($owner->id, $metadata, $actor);
        }
    }
}
