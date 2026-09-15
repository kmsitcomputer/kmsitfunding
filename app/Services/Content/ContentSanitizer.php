<?php

namespace App\Services\Content;

use App\Services\Content\Exceptions\ContentSanitizationException;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * IMP-005 — the stored-XSS wall (docs/implementation/IMP-005-cms.md section
 * 20). Implemented as a native DOMDocument allow-list walker rather than a
 * third-party library (e.g. HTMLPurifier) — the PHP `dom` extension is
 * already available in this environment and this avoids the change-control
 * dependency-justification step section 20 requires for adding one; the
 * token contract this class enforces IS the specification either way.
 *
 * Reject-only for security-critical violations (never silently strip-and-
 * keep): a dangerous element (script/style/iframe/object/embed/form/input/
 * base/link/meta/svg/math), an author-supplied `src` on `<img>` (it is
 * simply never in the allow-list so it is stripped — the REJECT case is a
 * missing/malformed `data-media`, since a silently-imageless `<img>` would
 * hide an authoring mistake), `href` and `data-media` together on `<a>`, a
 * disallowed `href` scheme, or a malformed/unknown `data-media` token.
 * Every other disallowed tag is unwrapped (removed, children promoted) and
 * every other disallowed attribute is silently stripped — ordinary
 * sanitization, not a security failure.
 */
class ContentSanitizer
{
    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'base', 'link', 'meta', 'svg', 'math',
    ];

    private const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'img',
        'strong', 'em', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'figure', 'figcaption', 'code', 'pre', 'hr', 'span',
    ];

    private const GLOBAL_ATTRIBUTES = ['class', 'id'];

    private const TAG_ATTRIBUTES = [
        'img' => ['data-media', 'alt', 'title', 'width', 'height'],
        'a' => ['href', 'data-media', 'title', 'target'],
    ];

    private const ALLOWED_HREF_SCHEMES = ['https', 'http', 'mailto', 'tel'];

    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    private const MAX_BYTES = 200 * 1024;

    public function __construct(private readonly PathService $pathService) {}

    /**
     * Sanitize author-supplied HTML into the stored canonical representation.
     * Idempotent: sanitizing an already-sanitized body is a no-op (section
     * 20 "Sanitizer/resolver round-trip guarantee").
     */
    public function sanitize(string $html): string
    {
        $dom = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $dom->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        // The encoding processing-instruction node loadHTML() leaves behind.
        if ($dom->firstChild !== null && $dom->firstChild->nodeType === XML_PI_NODE) {
            $dom->removeChild($dom->firstChild);
        }

        $this->walk($dom);

        $output = '';
        foreach (iterator_to_array($dom->childNodes) as $node) {
            $output .= $dom->saveHTML($node);
        }

        if (strlen($output) > self::MAX_BYTES) {
            throw new ContentSanitizationException(
                'body_too_large',
                'Sanitized body exceeds the '.self::MAX_BYTES.'-byte bound.'
            );
        }

        return $output;
    }

    private function walk(DOMNode $context): void
    {
        foreach (iterator_to_array($context->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $context->removeChild($child);

                continue;
            }

            if ($child instanceof DOMElement) {
                $this->handleElement($child);
            }

            // DOMText / DOMCdataSection: kept as-is (DOMDocument escapes on save).
        }
    }

    private function handleElement(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);

        if (in_array($tag, self::DANGEROUS_TAGS, true)) {
            throw new ContentSanitizationException(
                'disallowed_element',
                "Element <{$tag}> is never permitted in stored CMS content."
            );
        }

        // Depth-first: validate/clean children before deciding this node's fate.
        $this->walk($el);

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            // Unwrap: promote already-processed children, drop the tag itself.
            while ($el->firstChild !== null) {
                $el->parentNode->insertBefore($el->firstChild, $el);
            }
            $el->parentNode->removeChild($el);

            return;
        }

        $this->filterAttributes($el, $tag);
    }

    private function filterAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::TAG_ATTRIBUTES[$tag] ?? []);

        foreach (iterator_to_array($el->attributes ?? []) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                $el->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'img') {
            $this->enforceImgContract($el);
        }

        if ($tag === 'a') {
            $this->enforceAnchorContract($el);
        }

        if ($el->hasAttribute('class')) {
            $el->setAttribute('class', preg_replace('/[^a-zA-Z0-9_\- ]/', '', $el->getAttribute('class')));
        }

        if ($el->hasAttribute('id')) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $el->getAttribute('id')));
            $slug = trim($slug, '-');
            $slug === '' ? $el->removeAttribute('id') : $el->setAttribute('id', $slug);
        }
    }

    private function enforceImgContract(DOMElement $el): void
    {
        $token = $el->getAttribute('data-media');

        if ($token === '' || preg_match(self::ULID_PATTERN, $token) !== 1) {
            throw new ContentSanitizationException(
                'media_token_invalid',
                "<img> requires a valid data-media ULID token, got '{$token}'."
            );
        }

        if ($el->hasAttribute('width') && ! ctype_digit($el->getAttribute('width'))) {
            $el->removeAttribute('width');
        }

        if ($el->hasAttribute('height') && ! ctype_digit($el->getAttribute('height'))) {
            $el->removeAttribute('height');
        }
    }

    private function enforceAnchorContract(DOMElement $el): void
    {
        $hasDataMedia = $el->hasAttribute('data-media');
        $hasHref = $el->hasAttribute('href');

        if ($hasDataMedia && $hasHref) {
            throw new ContentSanitizationException(
                'href_and_data_media',
                '<a> may carry data-media OR href, never both — the token IS the destination.'
            );
        }

        if ($hasDataMedia) {
            $token = $el->getAttribute('data-media');

            if (preg_match(self::ULID_PATTERN, $token) !== 1) {
                throw new ContentSanitizationException(
                    'media_token_invalid',
                    "<a data-media> requires a valid ULID token, got '{$token}'."
                );
            }
        } elseif ($hasHref) {
            $this->validateHrefScheme($el->getAttribute('href'));
        }

        if ($el->hasAttribute('target') && $el->getAttribute('target') !== '_blank') {
            $el->removeAttribute('target');
        }
    }

    private function validateHrefScheme(string $href): void
    {
        if (str_starts_with($href, '/')) {
            if ($href === '/') {
                throw new ContentSanitizationException('invalid_href_scheme', 'href may not be the root path.');
            }

            if ($this->pathService->isReserved($href)) {
                throw new ContentSanitizationException(
                    'invalid_href_scheme',
                    "href '{$href}' targets a reserved application route."
                );
            }

            return;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($scheme, self::ALLOWED_HREF_SCHEMES, true)) {
            throw new ContentSanitizationException(
                'invalid_href_scheme',
                "href scheme '{$scheme}' is not permitted."
            );
        }
    }
}
