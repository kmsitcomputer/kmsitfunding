<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\PathValidationException;
use Illuminate\Support\Facades\Route;

/**
 * IMP-005 — THE single path authority (docs/implementation/IMP-005-cms.md
 * section 14). normalize()/bounds/reserved-registry (read/validate) plus
 * claim()/retainAndUpdateRevision()/convertToRedirect()/release() (write).
 *
 * claim()/retainAndUpdateRevision()/convertToRedirect() are NOT safe to call
 * standalone — section 14: "CLAIM and RENAME occur ONLY inside the
 * publication transaction that also moves the identity pointer". They are
 * building blocks PublicationService::publish() composes under its own
 * locks, in the exact branch order section 14/26 require; this class does
 * not open its own transaction or take its own locks for them, and does not
 * itself decide the branch (A/B/C) — the caller must have already locked the
 * owner and its existing CURRENT claim (if any) and decided the branch
 * before calling one of these.
 *
 * release() IS independently callable — it is explicitly "a separate,
 * explicitly authorized mutation" (section 14), not part of the publish
 * flow.
 *
 * Reject-only: an invalid path is never silently sanitized into a claimable
 * one (section 14 "Reject-only"). Normalization is deterministic and PER
 * SEGMENT — nested paths are legal, a path is never flattened into one slug.
 */
class PathService
{
    /**
     * Hard structural limit — maps 1:1 onto cms_paths.path VARCHAR(191).
     * NOT configurable: widening it would silently desync from the column.
     */
    private const MAX_TOTAL_LENGTH = 191;

    private const WINDOWS_RESERVED_NAMES = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /** @var array<string, bool>|null memoized per-instance; routes don't change mid-request */
    private ?array $reservedPrefixCache = null;

    /**
     * normalize -> bounds -> reserved, in that order (section 14 "Validation
     * order"). Returns the normalized, leading-'/', never-trailing-'/' path.
     * Throws PathValidationException on the first violation.
     */
    public function validate(string $raw): string
    {
        $normalized = $this->normalize($raw);

        if (! $this->isWithinBounds($normalized)) {
            throw new PathValidationException(
                'path_too_long',
                "Path '{$normalized}' exceeds the ".self::MAX_TOTAL_LENGTH.'-character bound.'
            );
        }

        if ($this->isReserved($normalized)) {
            throw new PathValidationException(
                'path_reserved',
                "Path '{$normalized}' collides with a reserved route or prefix."
            );
        }

        return $normalized;
    }

    /**
     * Per-segment normalization pipeline (section 14). Rejects rather than
     * silently sanitizes any segment that looks like path traversal, so
     * traversal-shaped input can never be laundered into a claimable path.
     */
    public function normalize(string $raw): string
    {
        if (str_contains($raw, "\0")) {
            throw new PathValidationException('path_traversal', 'Path contains a NUL byte.');
        }

        if (str_contains($raw, '\\')) {
            throw new PathValidationException('path_traversal', 'Path contains a backslash.');
        }

        $trimmed = trim(trim($raw), '/');

        if ($trimmed === '') {
            throw new PathValidationException('path_empty', 'Path is empty after trimming.');
        }

        $rawSegments = explode('/', $trimmed);
        $normalizedSegments = [];

        foreach ($rawSegments as $rawSegment) {
            $normalizedSegments[] = $this->normalizeSegment($rawSegment);
        }

        if (count($normalizedSegments) > (int) config('cms.path.max_depth')) {
            throw new PathValidationException(
                'path_too_deep',
                'Path exceeds max_depth of '.config('cms.path.max_depth').' segments.'
            );
        }

        return '/'.implode('/', $normalizedSegments);
    }

    public function isWithinBounds(string $normalized): bool
    {
        return strlen($normalized) <= self::MAX_TOTAL_LENGTH;
    }

    /**
     * The derived reserved registry (section 14 "Reserved route registry —
     * derived, not handwritten"): every live route's first URI segment,
     * UNION the configured protected prefixes, UNION framework/ops paths,
     * UNION the deny-overrides list.
     *
     * @return array<string, bool>
     */
    public function reservedPrefixes(): array
    {
        if ($this->reservedPrefixCache !== null) {
            return $this->reservedPrefixCache;
        }

        $reserved = [];

        foreach (Route::getRoutes() as $route) {
            $uri = trim($route->uri(), '/');

            if ($uri === '') {
                continue;
            }

            $firstSegment = explode('/', $uri)[0];

            // Route parameter placeholders (e.g. "{token}") are not real
            // reservable segments.
            if ($firstSegment === '' || str_starts_with($firstSegment, '{')) {
                continue;
            }

            $reserved[strtolower($firstSegment)] = true;
        }

        foreach (config('cms.path.protected_prefixes', []) as $prefix) {
            $reserved[strtolower($prefix)] = true;
        }

        foreach (config('cms.path.framework_paths', []) as $path) {
            $reserved[strtolower($path)] = true;
        }

        foreach (config('cms.path.deny_overrides', []) as $path) {
            $reserved[strtolower($path)] = true;
        }

        return $this->reservedPrefixCache = $reserved;
    }

    /**
     * A protected prefix reserves every depth beneath it, so checking the
     * normalized path's FIRST segment against the derived set is sufficient:
     * once the first segment is reserved, everything nested under it is
     * rejected too, which is exactly the "at every depth" rule.
     */
    public function isReserved(string $normalized): bool
    {
        $firstSegment = explode('/', trim($normalized, '/'))[0] ?? '';

        return isset($this->reservedPrefixes()[strtolower($firstSegment)]);
    }

    /**
     * The owner's ACTIVE CURRENT claim, if any (section 14 branch selection
     * predicate). Caller is expected to have already locked the owner row;
     * this additionally locks the claim row itself (FOR UPDATE) since branch
     * selection must be decided from locked rows, never a pre-lock read.
     */
    public function lockCurrentClaim(CmsPage|CmsArticle $owner): ?CmsPath
    {
        $ownerColumn = $owner instanceof CmsPage ? 'page_id' : 'article_id';

        return CmsPath::query()
            ->where($ownerColumn, $owner->id)
            ->where('purpose', 'CURRENT')
            ->where('status', 'ACTIVE')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Branch A (first publication): INSERT one ACTIVE CURRENT row. The
     * UNIQUE(active_path)/UNIQUE(active_current_owner) indexes are the race
     * authority — a duplicate-key failure here is the caller's 409
     * path_conflict, not something this method pre-checks and cannot
     * guarantee (section 14 "Availability is decided by the constraint").
     */
    public function claim(CmsPage|CmsArticle $owner, string $normalizedPath, CmsContentRevision $revision): CmsPath
    {
        $claim = new CmsPath;
        $claim->forceFill([
            'path' => $normalizedPath,
            'purpose' => 'CURRENT',
            $owner instanceof CmsPage ? 'page_id' : 'article_id' => $owner->id,
            'revision_id' => $revision->id,
            'status' => 'ACTIVE',
        ]);
        $claim->save();

        return $claim;
    }

    /**
     * Branch B (same-path replacement / re-publication of RETIRED content):
     * retain the SAME row, UPDATE its revision_id only — never an INSERT
     * (section 14 branch B: "no key race... has no insert to race").
     */
    public function retainAndUpdateRevision(CmsPath $currentClaim, CmsContentRevision $revision): void
    {
        $currentClaim->forceFill(['revision_id' => $revision->id])->save();
    }

    /**
     * Branch C rename, step 4: CONVERT purpose CURRENT -> REDIRECT. revision_id
     * is left untouched — it is already the revision that was holding this
     * path, which becomes its FROZEN historical value the instant purpose
     * flips (section 14: "A REDIRECT claim's revision_id is FROZEN"). MUST be
     * called, and committed, BEFORE claim() inserts the new CURRENT row for
     * the same owner — reversing the order collides with
     * UNIQUE(active_current_owner) (section 14 "the ONLY branch... ordering
     * is FORCED").
     */
    public function convertToRedirect(CmsPath $currentClaim): void
    {
        $currentClaim->forceFill(['purpose' => 'REDIRECT'])->save();
    }

    /**
     * The ONLY operation that un-reserves a path (section 13 RESERVATION
     * POLICY) — never a side effect of retire/archive/any lifecycle
     * transition. Independently callable, NOT part of the publish flow.
     */
    public function release(CmsPath $claim, Principal $principal): void
    {
        $claim->forceFill([
            'status' => 'RELEASED',
            'released_at' => now(),
        ])->save();
    }

    private function normalizeSegment(string $rawSegment): string
    {
        if ($rawSegment === '') {
            throw new PathValidationException('path_empty_segment', 'Path contains an empty segment.');
        }

        if (str_contains($rawSegment, '.')) {
            throw new PathValidationException(
                'path_traversal',
                "Segment '{$rawSegment}' contains a '.' path-traversal element."
            );
        }

        if (preg_match('/%[0-9A-Fa-f]{2}/', $rawSegment) === 1) {
            throw new PathValidationException(
                'path_traversal',
                "Segment '{$rawSegment}' contains a percent-encoded sequence."
            );
        }

        if (in_array(strtoupper($rawSegment), self::WINDOWS_RESERVED_NAMES, true)) {
            throw new PathValidationException(
                'path_reserved_device_name',
                "Segment '{$rawSegment}' is a Windows-reserved device name."
            );
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rawSegment);

        if ($transliterated === false) {
            $transliterated = preg_replace('/[^\x00-\x7F]/', '', $rawSegment);
        }

        $lower = strtolower($transliterated);
        $slugged = preg_replace('/[^a-z0-9]+/', '-', $lower);
        $collapsed = preg_replace('/-+/', '-', $slugged);
        $trimmedSegment = trim($collapsed, '-');

        if ($trimmedSegment === '') {
            throw new PathValidationException(
                'path_empty_segment',
                "Segment '{$rawSegment}' normalizes to empty."
            );
        }

        if (strlen($trimmedSegment) > (int) config('cms.path.max_segment_length')) {
            throw new PathValidationException(
                'path_segment_too_long',
                "Segment '{$trimmedSegment}' exceeds max_segment_length of ".
                config('cms.path.max_segment_length').' characters.'
            );
        }

        return $trimmedSegment;
    }
}
