<?php

namespace App\Services\Content;

use App\Services\Content\Exceptions\PathValidationException;
use Illuminate\Support\Facades\Route;

/**
 * IMP-005 — THE single path authority (docs/implementation/IMP-005-cms.md
 * section 14). This slice implements the READ/VALIDATE side only:
 * normalize() + bounds + reserved-registry membership. claim()/rename()/
 * release() are a later slice, wired into the publish transaction (section
 * 26 flow 1/2) once RevisionService/PublicationService exist.
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
