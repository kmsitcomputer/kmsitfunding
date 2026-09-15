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
 * This slice implements publish() (covering ALL THREE branches — first
 * publication, same-path replacement, rename — plus re-publication of
 * RETIRED content, since it is the same branch-B/C selection against the
 * same still-PUBLISHED revision) and unpublish() (withdraw). archive() and
 * homepage assignment are a separate, later slice (section 26 flows 1 and 7
 * are independent of this one). Scheduled execution (flow 5) reuses this
 * same publish()/unpublish() — that reuse is enforced by construction (there
 * is no second implementation to diverge), and lands with the scheduler
 * slice.
 *
 * NOT YET IN THIS SLICE (documented, not silently dropped): the media
 * asset/reference tiers (section 19/26 tiers 1-2) — no MediaService exists
 * yet, so publish() does not lock or verify media attachability. Once the
 * media pipeline slice lands, those tiers must be added to this method
 * BEFORE the identity lock, per the section 19 shared lock order.
 */
class PublicationService
{
    public function __construct(private readonly PathService $pathService) {}

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

            if (! in_array($lockedOwner->status, ['DRAFT', 'RETIRED'], true)) {
                throw new PublicationValidationException(
                    'invalid_archive_state',
                    "Owner {$lockedOwner->id} is status={$lockedOwner->status}; only DRAFT or RETIRED may be archived."
                );
            }

            $lockedOwner->forceFill([
                'status' => 'ARCHIVED',
                'updated_by_principal_id' => $actor->id,
            ])->save();

            if ($lockedOwner instanceof CmsPage) {
                $assignment = CmsHomepageAssignment::query()->whereKey(1)->lockForUpdate()->first();

                if ($assignment !== null && $assignment->page_id === $lockedOwner->id) {
                    $assignment->update([
                        'page_id' => null,
                        'assigned_by_principal_id' => $actor->id,
                        'assigned_at' => now(),
                    ]);
                }
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
}
