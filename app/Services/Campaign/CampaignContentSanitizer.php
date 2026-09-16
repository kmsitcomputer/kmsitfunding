<?php

namespace App\Services\Campaign;

use App\Services\Campaign\Exceptions\CampaignValidationException;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * IMP-007's own stored-XSS wall for description_html (docs/implementation/
 * IMP-007-campaign-program-fund.md section 13: "own sanitizer instance,
 * same allow-list approach as ContentSanitizer — no shared class
 * dependency on IMP-005"). Mirrors App\Services\Content\ContentSanitizer's
 * DOM-walking algorithm and tag allow-list exactly, trimmed for Campaign's
 * needs: no CMS media-token (`data-media`) contract and no `<img>` support
 * (Campaign/Program media stays in the separate Media Library gallery, not
 * inline in the editor — RichTextEditor.vue's toolbar has no image
 * extension, so an `<img>` here can only come from a hand-crafted request,
 * which this sanitizer strips like any other disallowed element).
 *
 * REMEDIATION NOTE: this class did not exist before the presentation
 * foundation remediation pass — description_html was previously persisted
 * completely unsanitized (a real stored-XSS gap, since it is later
 * rendered via `v-html` on the public Campaign/Program pages). Wiring
 * this in is a security fix to IMP-007's own file, not a locked-contract
 * change to IMP-005.
 */
class CampaignContentSanitizer
{
    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'base', 'link', 'meta', 'svg', 'math', 'img',
    ];

    private const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a',
        'strong', 'em', 'blockquote', 'code', 'pre', 'hr', 'span',
    ];

    private const GLOBAL_ATTRIBUTES = ['class'];

    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'title', 'target'],
    ];

    private const ALLOWED_HREF_SCHEMES = ['https', 'http', 'mailto', 'tel'];

    private const MAX_BYTES = 200 * 1024;

    public function sanitize(string $html): string
    {
        $dom = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if ($dom->firstChild !== null && $dom->firstChild->nodeType === XML_PI_NODE) {
            $dom->removeChild($dom->firstChild);
        }

        $this->walk($dom);

        $output = '';
        foreach (iterator_to_array($dom->childNodes) as $node) {
            $output .= $dom->saveHTML($node);
        }

        if (strlen($output) > self::MAX_BYTES) {
            throw new CampaignValidationException('body_too_large', 'Sanitized description exceeds the '.self::MAX_BYTES.'-byte bound.');
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
        }
    }

    private function handleElement(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);

        if (in_array($tag, self::DANGEROUS_TAGS, true)) {
            throw new CampaignValidationException('disallowed_element', "Element <{$tag}> is never permitted in stored campaign/program content.");
        }

        $this->walk($el);

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
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

        if ($tag === 'a') {
            $this->enforceAnchorContract($el);
        }

        if ($el->hasAttribute('class')) {
            $el->setAttribute('class', preg_replace('/[^a-zA-Z0-9_\- ]/', '', $el->getAttribute('class')));
        }
    }

    private function enforceAnchorContract(DOMElement $el): void
    {
        if ($el->hasAttribute('href')) {
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
                throw new CampaignValidationException('invalid_href_scheme', 'href may not be the root path.');
            }

            return;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($scheme, self::ALLOWED_HREF_SCHEMES, true)) {
            throw new CampaignValidationException('invalid_href_scheme', "href scheme '{$scheme}' is not permitted.");
        }
    }
}
