<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaReference;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Models\Rbac\Principal;
use App\Policies\ContentArticlePolicy;
use App\Policies\ContentPagePolicy;
use App\Services\Content\Exceptions\HomepageAssignmentConflictException;
use App\Services\Content\Exceptions\MediaValidationException;
use App\Services\Content\Exceptions\PathConflictException;
use App\Services\Content\Exceptions\PublicationValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
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
 * scheduleConfigure()/executeScheduledTransition() (Q32, section 10) reuse
 * this SAME publish()/unpublish() via the optional $scheduleContext
 * parameter — there is no second implementation to diverge. A manual
 * (Human) call always omits $scheduleContext (actor=human, no schedule
 * keys); executeScheduledTransition() always supplies it (actor=system,
 * the four schedule-provenance keys present) — never the other way
 * around.
 *
 * MEDIA TIERS 1-2 (IMP005-FINAL-GATE-01, section 19 "Shared lock order" /
 * section 26 flow 1): publish() locks the candidate revision's referenced
 * media assets (tier 1, ids ASCENDING) and their reference rows (tier 2)
 * BEFORE locking the content identity/revision (tier 3), and re-verifies
 * every referenced asset is still status=ACTIVE under those locks —
 * exactly the same shared order attachment/archive/purge use, so publish
 * can never deadlock against them and can never publish a revision whose
 * media was archived/purged out from under it after its draft was last
 * saved. Tiers 1-2 are skipped when the candidate has no ACTIVE
 * references (section 26 "PUBLISH WITH NO MEDIA").
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
     * @param  array{schedule_version:int,scheduled_at:Carbon,scheduled_revision_id:int,scheduled_by_principal_id:int}|null  $scheduleContext
     *                                                                                                                                         non-null ONLY when called from executeScheduledTransition() — carries the four
     *                                                                                                                                         schedule-provenance audit keys and clears publish_at (this transition consumed it).
     *                                                                                                                                         $actor must be the content.scheduler System Principal in that case (section 12).
     */
    public function publish(
        CmsPage|CmsArticle $owner,
        CmsContentRevision $candidateRevision,
        Principal $actor,
        ?string $rawTargetPath = null,
        ?array $scheduleContext = null,
    ): CmsPage|CmsArticle {
        // Pre-read (UNLOCKED, hint only — section 19 step 0 / section 26 flow 1):
        // publish() proposes no payload change, so the "proposed" set is simply
        // the candidate's own current ACTIVE reference set.
        $hintAssetIds = CmsMediaReference::query()
            ->where('owner_revision_id', $candidateRevision->id)
            ->where('status', 'ACTIVE')
            ->pluck('media_asset_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return DB::transaction(function () use ($owner, $candidateRevision, $actor, $rawTargetPath, $scheduleContext, $hintAssetIds) {
            $this->lockAndVerifyMediaTiers($candidateRevision, $hintAssetIds);

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

            if ($scheduleContext !== null) {
                // This transition consumed the publish_at deadline; leave
                // unpublish_at (a still-pending later withdraw) untouched.
                $lockedOwner->publish_at = null;
            } elseif ($lockedOwner->publish_at !== null || $lockedOwner->unpublish_at !== null) {
                // Section 10: "Immediate Human publish... cancels pending
                // intent atomically; stale cron cannot override that action."
                $this->cancelPendingScheduleSilently($lockedOwner);
            }

            $lockedOwner->save();

            $this->emitPublished($lockedOwner, $lockedCandidate, $fromStatus, $branch, $previousPublished, $existingClaim, $actor, $scheduleContext);

            return $lockedOwner->fresh();
        });
    }

    /**
     * Withdraw: PUBLISHED -> RETIRED. Releases NOTHING (section 13
     * RESERVATION POLICY) — the published revision, its path claim, and its
     * pointer are all untouched; only the identity's status changes.
     *
     * @param  array{schedule_version:int,scheduled_at:Carbon,scheduled_revision_id:int,scheduled_by_principal_id:int}|null  $scheduleContext
     *                                                                                                                                         see publish()'s identical parameter doc.
     */
    public function unpublish(CmsPage|CmsArticle $owner, Principal $actor, ?array $scheduleContext = null): CmsPage|CmsArticle
    {
        return DB::transaction(function () use ($owner, $actor, $scheduleContext) {
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
            ]);

            if ($scheduleContext !== null) {
                $lockedOwner->unpublish_at = null;
            } elseif ($lockedOwner->publish_at !== null || $lockedOwner->unpublish_at !== null) {
                $this->cancelPendingScheduleSilently($lockedOwner);
            }

            $lockedOwner->save();

            $claim = $this->pathService->lockCurrentClaim($lockedOwner);

            $metadata = [
                'revision_id' => $lockedOwner->published_revision_id,
                'from_status' => 'PUBLISHED',
                'to_status' => 'RETIRED',
                'path' => $claim->path ?? '',
                'path_change' => 'UNCHANGED',
            ];

            if ($scheduleContext !== null) {
                $metadata += [
                    'schedule_version' => $scheduleContext['schedule_version'],
                    'scheduled_at' => $this->formatUtc($scheduleContext['scheduled_at']),
                    'scheduled_revision_id' => $scheduleContext['scheduled_revision_id'],
                    'scheduled_by_principal_id' => $scheduleContext['scheduled_by_principal_id'],
                    'system_operation' => 'content.scheduler',
                ];
            }

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
     * Tiers 1-2 of the shared lock order (section 19 "Shared lock order" /
     * section 26 flow 1), taken BEFORE this transaction's tier-3 identity/
     * revision lock. $hintAssetIds is the unlocked pre-read (may be stale by
     * a race that itself needs the same asset's tier-1 lock to resolve — see
     * this method's caller-side doc comment); an empty hint means no media
     * is involved and both tiers are skipped entirely, matching "PUBLISH
     * WITH NO MEDIA" (section 26).
     *
     * @param  array<int, int>  $hintAssetIds
     */
    private function lockAndVerifyMediaTiers(CmsContentRevision $candidateRevision, array $hintAssetIds): void
    {
        if ($hintAssetIds === []) {
            return;
        }

        $lockedAssets = CmsMediaAsset::query()
            ->whereIn('id', $hintAssetIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Tier 2: the reference rows themselves, ids ASCENDING — taken (not
        // merely read) so a concurrent reconcile of this same revision's
        // references cannot interleave with this re-verification.
        CmsMediaReference::query()
            ->where('owner_revision_id', $candidateRevision->id)
            ->whereIn('media_asset_id', $hintAssetIds)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lockedAssets as $asset) {
            if ($asset->status !== 'ACTIVE') {
                throw new MediaValidationException(
                    'media_asset_not_attachable',
                    "Media asset '{$asset->ulid}' is not attachable (status={$asset->status}); this revision cannot be published while it references a non-ACTIVE asset."
                );
            }
        }
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

    /**
     * Section 10: "Immediate Human publish, unpublish or archive cancels
     * pending intent atomically; stale cron cannot override that action."
     * Clears every schedule column and bumps schedule_version so a
     * concurrently-in-flight scheduler execution (holding a stale version)
     * safely no-ops. Deliberately silent — no separate content.*.
     * schedule_updated event: the manual transition this accompanies
     * already emits its own canonical event for this same commit, and nothing
     * in section 12's inventory calls for a second one here.
     */
    private function cancelPendingScheduleSilently(CmsPage|CmsArticle $lockedOwner): void
    {
        $lockedOwner->publish_at = null;
        $lockedOwner->unpublish_at = null;
        $lockedOwner->schedule_version = $lockedOwner->schedule_version + 1;
        $lockedOwner->scheduled_revision_id = null;
        $lockedOwner->scheduled_by_principal_id = null;
        $lockedOwner->scheduled_at = null;
    }

    private function formatUtc(\DateTimeInterface $dt): string
    {
        // Second-precision, explicit 'Z' (section 12 type vocabulary).
        return Carbon::instance($dt)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Human-side schedule configuration/change/cancellation (section 10 Q32
     * "Human intent"). Pass both $publishAt and $unpublishAt as null to
     * CANCEL. New deadlines must be future; if both are set, $unpublishAt
     * must be later than $publishAt. A publish intent binds to an owned
     * DRAFT, or the retained published revision for RETIRED re-publication;
     * a standalone unpublish intent binds to the current published revision
     * and requires the owner to currently be PUBLISHED.
     */
    public function scheduleConfigure(
        CmsPage|CmsArticle $owner,
        Principal $actor,
        ?\DateTimeInterface $publishAt,
        ?\DateTimeInterface $unpublishAt,
        ?CmsContentRevision $targetRevision,
        int $expectedScheduleVersion,
        ?string $rawTargetPath = null,
    ): CmsPage|CmsArticle {
        return DB::transaction(function () use ($owner, $actor, $publishAt, $unpublishAt, $targetRevision, $expectedScheduleVersion, $rawTargetPath) {
            $locked = $owner::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if ($locked->schedule_version !== $expectedScheduleVersion) {
                throw new PublicationValidationException(
                    'stale_schedule_version',
                    "Expected schedule_version {$expectedScheduleVersion} but owner {$locked->id} is at {$locked->schedule_version}."
                );
            }

            $isCancel = $publishAt === null && $unpublishAt === null;
            $now = now();

            if (! $isCancel) {
                if ($publishAt !== null && $publishAt->lte($now)) {
                    throw new PublicationValidationException('schedule_not_future', 'publish_at must be in the future.');
                }

                if ($unpublishAt !== null && $unpublishAt->lte($now)) {
                    throw new PublicationValidationException('schedule_not_future', 'unpublish_at must be in the future.');
                }

                if ($publishAt !== null && $unpublishAt !== null && $unpublishAt->lte($publishAt)) {
                    throw new PublicationValidationException(
                        'schedule_order_invalid',
                        'unpublish_at must be later than publish_at when both are set.'
                    );
                }
            }

            $resolvedTargetRevisionId = null;

            if (! $isCancel) {
                if ($publishAt !== null) {
                    if ($targetRevision === null) {
                        throw new PublicationValidationException(
                            'schedule_target_required',
                            'A target revision is required to schedule a publish.'
                        );
                    }

                    $ownerColumn = $locked instanceof CmsPage ? 'page_id' : 'article_id';

                    if ($targetRevision->{$ownerColumn} !== $locked->id) {
                        throw new PublicationValidationException(
                            'revision_owner_mismatch',
                            "Revision {$targetRevision->id} does not belong to this owner."
                        );
                    }

                    $validState = $targetRevision->state === 'DRAFT'
                        || ($targetRevision->state === 'PUBLISHED' && $locked->published_revision_id === $targetRevision->id);

                    if (! $validState) {
                        throw new PublicationValidationException(
                            'invalid_candidate_state',
                            "Revision {$targetRevision->id} is state={$targetRevision->state} and is not a valid schedule target."
                        );
                    }

                    // section 13: "slug_snapshot ... frozen with a scheduled
                    // draft" — a DRAFT being bound to a schedule gets its
                    // path frozen NOW, not deferred to execution time (there
                    // is no other point at which a first-time scheduled
                    // publish could otherwise learn its own target path).
                    if ($targetRevision->state === 'DRAFT' && $targetRevision->slug_snapshot === null) {
                        if ($rawTargetPath === null) {
                            throw new PublicationValidationException(
                                'schedule_target_path_required',
                                'A target path is required to schedule a first-time publish.'
                            );
                        }

                        $targetRevision->forceFill(['slug_snapshot' => $this->pathService->validate($rawTargetPath)])->save();
                    }

                    $resolvedTargetRevisionId = $targetRevision->id;
                } else {
                    if ($locked->status !== 'PUBLISHED') {
                        throw new PublicationValidationException(
                            'not_published',
                            'Standalone unpublish scheduling requires the owner to currently be PUBLISHED.'
                        );
                    }

                    $resolvedTargetRevisionId = $locked->published_revision_id;
                }
            }

            $operation = match (true) {
                $isCancel => 'CANCELLED',
                $locked->publish_at === null && $locked->unpublish_at === null => 'CONFIGURED',
                default => 'CHANGED',
            };

            $locked->publish_at = $publishAt;
            $locked->unpublish_at = $unpublishAt;
            $locked->schedule_version = $locked->schedule_version + 1;
            $locked->scheduled_revision_id = $resolvedTargetRevisionId;
            $locked->scheduled_by_principal_id = $isCancel ? null : $actor->id;
            $locked->scheduled_at = $isCancel ? null : $now;
            $locked->save();

            $metadata = [
                'operation' => $operation,
                'schedule_version' => $locked->schedule_version,
            ];

            if ($publishAt !== null) {
                $metadata['publish_at'] = $this->formatUtc($publishAt);
            }

            if ($unpublishAt !== null) {
                $metadata['unpublish_at'] = $this->formatUtc($unpublishAt);
            }

            if (! $isCancel) {
                $metadata['scheduled_revision_id'] = $resolvedTargetRevisionId;
                $metadata['scheduled_at'] = $this->formatUtc($locked->scheduled_at);
                $metadata['scheduled_by_principal_id'] = $actor->id;
            }

            if ($locked instanceof CmsArticle) {
                $metadata['article_type'] = ($locked->currentDraft()->first() ?? CmsContentRevision::find($locked->published_revision_id))?->article_type;
                $this->auditLogger->recordArticleScheduleUpdated($locked->id, $metadata, $actor);
            } else {
                $this->auditLogger->recordPageScheduleUpdated($locked->id, $metadata, $actor);
            }

            return $locked->fresh();
        });
    }

    /**
     * Execution (section 10 "Execution" / section 12 "Expired-window
     * semantics"): called once per due identity by the
     * content:run-scheduled-transitions command, selected by `publish_at <=
     * cutoff OR unpublish_at <= cutoff` (a pre-lock HINT only). Re-derives
     * due-ness under the lock — a stale/already-consumed row is a silent
     * no-op, which is what makes overlapping cron runs and missed-then-
     * caught-up runs both safe.
     */
    public function executeScheduledTransition(CmsPage|CmsArticle $owner, \DateTimeInterface $cutoff, Principal $systemActor): void
    {
        DB::transaction(function () use ($owner, $cutoff, $systemActor) {
            $locked = $owner::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            $cutoffCarbon = Carbon::instance($cutoff);
            $publishDue = $locked->publish_at !== null && $locked->publish_at->lte($cutoffCarbon);
            $unpublishDue = $locked->unpublish_at !== null && $locked->unpublish_at->lte($cutoffCarbon);

            if (! $publishDue && ! $unpublishDue) {
                return; // stale re-run / already consumed — no mutation, no event
            }

            $scheduleContext = [
                'schedule_version' => $locked->schedule_version,
                'scheduled_at' => $locked->scheduled_at,
                'scheduled_revision_id' => $locked->scheduled_revision_id,
                'scheduled_by_principal_id' => $locked->scheduled_by_principal_id,
            ];

            $target = $locked->scheduled_revision_id !== null
                ? CmsContentRevision::query()->whereKey($locked->scheduled_revision_id)->first()
                : null;
            $ownerColumn = $locked instanceof CmsPage ? 'page_id' : 'article_id';
            $targetInvalid = $target === null
                || $target->{$ownerColumn} !== $locked->id
                || $target->state === 'SUPERSEDED';

            $sourceHuman = $locked->scheduled_by_principal_id !== null
                ? Principal::find($locked->scheduled_by_principal_id)
                : null;
            $policy = $locked instanceof CmsPage ? new ContentPagePolicy : new ContentArticlePolicy;
            $sourceEligible = $sourceHuman !== null && $policy->publish($sourceHuman, $locked);

            if (! $sourceEligible) {
                // "Block until a currently authorized Human cancels/replaces
                // the schedule" — leave the window pending (not consumed) for
                // an operator to correct; report, never force a transition.
                logger()->warning('CMS scheduled transition blocked: source Human no longer eligible.', [
                    'owner_type' => $ownerColumn,
                    'owner_id' => $locked->id,
                ]);

                return;
            }

            if ($publishDue && $unpublishDue) {
                $this->consumeExpiredWindow($locked, $systemActor, $scheduleContext, publishConsumed: true, unpublishConsumed: true, outcome: 'PUBLISH_SUPERSEDED_BY_UNPUBLISH');

                return;
            }

            if ($unpublishDue && $locked->status !== 'PUBLISHED') {
                $this->consumeExpiredWindow($locked, $systemActor, $scheduleContext, publishConsumed: false, unpublishConsumed: true, outcome: 'NO_RETIREMENT_TARGET');

                return;
            }

            if ($targetInvalid) {
                $this->consumeExpiredWindow(
                    $locked, $systemActor, $scheduleContext,
                    publishConsumed: $publishDue, unpublishConsumed: $unpublishDue, outcome: 'TARGET_INVALID'
                );

                return;
            }

            if ($publishDue) {
                // $target->slug_snapshot is already frozen — either from this
                // schedule's own configuration time (first-time publish,
                // section 13) or from the identity's original publication
                // (re-publish of RETIRED content, same revision).
                $this->publish($locked, $target, $systemActor, $target->slug_snapshot, $scheduleContext);

                return;
            }

            // unpublishDue, owner PUBLISHED, target valid.
            $this->unpublish($locked, $systemActor, $scheduleContext);
        });
    }

    private function consumeExpiredWindow(
        CmsPage|CmsArticle $locked,
        Principal $systemActor,
        array $scheduleContext,
        bool $publishConsumed,
        bool $unpublishConsumed,
        string $outcome,
    ): void {
        $metadata = [
            'schedule_version' => $scheduleContext['schedule_version'],
            'outcome' => $outcome,
        ];

        if ($publishConsumed && $locked->publish_at !== null) {
            $metadata['publish_at'] = $this->formatUtc($locked->publish_at);
        }

        if ($unpublishConsumed && $locked->unpublish_at !== null) {
            $metadata['unpublish_at'] = $this->formatUtc($locked->unpublish_at);
        }

        if ($publishConsumed) {
            $locked->publish_at = null;
        }

        if ($unpublishConsumed) {
            $locked->unpublish_at = null;
        }

        $locked->save();

        if ($locked instanceof CmsArticle) {
            $metadata['article_type'] = ($locked->currentDraft()->first() ?? CmsContentRevision::find($locked->published_revision_id))?->article_type;
            $this->auditLogger->recordArticleScheduleExpired($locked->id, $metadata, $systemActor);
        } else {
            $this->auditLogger->recordPageScheduleExpired($locked->id, $metadata, $systemActor);
        }
    }

    private function emitPublished(
        CmsPage|CmsArticle $owner,
        CmsContentRevision $candidate,
        string $fromStatus,
        string $branch,
        ?CmsContentRevision $previousPublished,
        ?CmsPath $convertedClaim,
        Principal $actor,
        ?array $scheduleContext = null,
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

        if ($scheduleContext !== null) {
            $metadata += [
                'schedule_version' => $scheduleContext['schedule_version'],
                'scheduled_at' => $this->formatUtc($scheduleContext['scheduled_at']),
                'scheduled_revision_id' => $scheduleContext['scheduled_revision_id'],
                'scheduled_by_principal_id' => $scheduleContext['scheduled_by_principal_id'],
                'system_operation' => 'content.scheduler',
            ];
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
