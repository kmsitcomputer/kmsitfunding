<?php

namespace App\Services\Theme;

/**
 * CR-001-D — read-only, code-level Page Builder block registry
 * (docs/implementation/CR-001-D-visual-page-builder.md §10). Presentation
 * metadata only: operator labels, categories, icons, allowed canvases, and
 * safe default configs layered over the closed
 * ComponentConfigValidator::SCHEMAS set — which remains the single source of
 * truth for what is a valid component `type`/`config`.
 *
 * No executable behavior is stored here — plain scalar/array metadata only,
 * reviewed and deployed like any other class, never operator-editable data.
 */
class PageBuilderBlockRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'hero' => [
                'key' => 'hero',
                'operator_label' => 'Hero',
                'description' => 'Large headline banner with optional call to action.',
                'category' => 'Content',
                'icon' => 'megaphone',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['headline' => '', 'subheading' => null, 'cta_label' => null],
            ],
            'banner' => [
                'key' => 'banner',
                'operator_label' => 'Banner',
                'description' => 'Notice bar with optional link destination.',
                'category' => 'Content',
                'icon' => 'megaphone',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['text' => '', 'dismissible' => false],
            ],
            'rich_text_cms' => [
                'key' => 'rich_text',
                'operator_label' => 'Rich Text',
                'description' => 'Excerpt from a published page or article.',
                'category' => 'Content',
                'icon' => 'document',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['source' => 'cms_content'],
            ],
            'rich_text_caption' => [
                'key' => 'rich_text',
                'operator_label' => 'Short Text',
                'description' => 'Short hand-entered caption up to 1000 characters.',
                'category' => 'Content',
                'icon' => 'document',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['source' => 'caption', 'caption' => ''],
            ],
            'campaign_grid' => [
                'key' => 'content_list',
                'operator_label' => 'Campaign Grid',
                'description' => 'Grid of live campaigns resolved at render time.',
                'category' => 'Fundraising',
                'icon' => 'target',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => true,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'grid'],
            ],
            'campaign_carousel' => [
                'key' => 'content_list',
                'operator_label' => 'Campaign Carousel',
                'description' => 'Carousel of live campaigns; same data as the grid.',
                'category' => 'Fundraising',
                'icon' => 'target',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => true,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'carousel'],
            ],
            'program_grid' => [
                'key' => 'content_list',
                'operator_label' => 'Program Grid',
                'description' => 'Grid of live programs resolved at render time.',
                'category' => 'Fundraising',
                'icon' => 'wallet',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => true,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['content_kind' => 'program', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'grid'],
            ],
            'program_carousel' => [
                'key' => 'content_list',
                'operator_label' => 'Program Carousel',
                'description' => 'Carousel of live programs; same data as the grid.',
                'category' => 'Fundraising',
                'icon' => 'wallet',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => true,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['content_kind' => 'program', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'carousel'],
            ],
            'articles_news' => [
                'key' => 'content_list',
                'operator_label' => 'Articles / News',
                'description' => 'Latest articles and news resolved at render time.',
                'category' => 'Content',
                'icon' => 'document',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => true,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['content_kind' => 'article', 'limit' => 6, 'order' => 'latest', 'display_mode' => 'grid'],
            ],
            'statistics' => [
                'key' => 'stats',
                'operator_label' => 'Statistics',
                'description' => 'Hand-entered figures such as totals and counts.',
                'category' => 'Social Proof',
                'icon' => 'dashboard',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['items' => [['label' => '', 'value' => '']]],
            ],
            'ziswaf_services' => [
                'key' => 'card_grid',
                'operator_label' => 'ZISWAF Services',
                'description' => 'Presentation cards linking to Zakat, Infaq, Sedekah, Wakaf, Fidyah, Qurban.',
                'category' => 'Fundraising',
                'icon' => 'heart',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['mode' => 'authored', 'cards' => [
                    ['title' => 'Zakat', 'text' => null],
                    ['title' => 'Infaq', 'text' => null],
                    ['title' => 'Sedekah', 'text' => null],
                ]],
            ],
            'gallery' => [
                'key' => 'card_grid',
                'operator_label' => 'Gallery',
                'description' => 'Authored image cards without destinations.',
                'category' => 'Content',
                'icon' => 'image',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['mode' => 'authored', 'cards' => [
                    ['title' => 'Photo 1', 'text' => null],
                ]],
            ],
            'partners' => [
                'key' => 'card_grid',
                'operator_label' => 'Partners',
                'description' => 'Partner logos with optional links.',
                'category' => 'Social Proof',
                'icon' => 'user',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['mode' => 'authored', 'cards' => [
                    ['title' => 'Partner 1', 'text' => null],
                ]],
            ],
            'donation_cta' => [
                'key' => 'cta_button',
                'operator_label' => 'Donation CTA',
                'description' => 'Call-to-action button with a donation styling hint.',
                'category' => 'Fundraising',
                'icon' => 'heart',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['label' => '', 'variant' => 'primary', 'intent' => 'donation'],
            ],
            'zakat_calculator_cta' => [
                'key' => 'cta_button',
                'operator_label' => 'Zakat Calculator CTA',
                'description' => 'Link to the zakat calculator once its route exists; safe presentation only.',
                'category' => 'Fundraising',
                'icon' => 'calendar',
                'allowed_canvases' => ['home', 'page'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['label' => '', 'variant' => 'secondary', 'intent' => 'zakat'],
            ],
            'safe_custom_content' => [
                'key' => 'rich_text',
                'operator_label' => 'Safe Custom Content',
                'description' => 'Custom markup sanitized at save time; no scripts or embeds.',
                'category' => 'Layout',
                'icon' => 'settings',
                'allowed_canvases' => ['home', 'page', 'article'],
                'domain_backed' => false,
                'repeatable' => true,
                'removable' => true,
                'configurable_inline' => true,
                'preview_supported' => true,
                'default_config' => ['source' => 'custom_html', 'body_html' => ''],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function forCanvas(string $contentKind): array
    {
        return array_filter(
            self::all(),
            fn (array $block): bool => in_array($contentKind, $block['allowed_canvases'], true)
        );
    }

    public static function has(string $blockKey): bool
    {
        return array_key_exists($blockKey, self::all());
    }

    /**
     * CODEX-CR001D-02 — the canonical FINAL EFFECTIVE eligibility check.
     * Given a canonical component `type`, its FULLY MERGED `config` (after
     * defaults + operator input), and a target canvas, determines whether
     * that exact combination corresponds to a curated Page Builder block on
     * that canvas — regardless of which registry key an operator originally
     * requested. This is deliberately re-derived from the type+config alone
     * (never trusted from a caller-supplied registry key) so that no
     * discriminator override (e.g. swapping `content_kind` after merge) can
     * transform a curated block into an unsupported/Advanced-Debug-only
     * variant or move it onto a canvas the matching entry does not allow.
     *
     * A `$contentKind` of null means "canvas-agnostic" (used only when a
     * Section currently has no template placement at all — nothing to
     * validate a canvas against).
     *
     * @param  array<string, mixed>  $config
     */
    public static function isEligible(string $type, array $config, ?string $contentKind): bool
    {
        foreach (self::all() as $entry) {
            if ($entry['key'] !== $type) {
                continue;
            }

            if ($contentKind !== null && ! in_array($contentKind, $entry['allowed_canvases'], true)) {
                continue;
            }

            $defaults = is_array($entry['default_config'] ?? null) ? $entry['default_config'] : [];
            $matchesDiscriminators = true;

            foreach (['content_kind', 'source', 'mode', 'intent', 'display_mode'] as $discriminator) {
                if (! array_key_exists($discriminator, $defaults)) {
                    continue;
                }

                if (($config[$discriminator] ?? null) !== $defaults[$discriminator]) {
                    $matchesDiscriminators = false;
                    break;
                }
            }

            if ($matchesDiscriminators) {
                return true;
            }
        }

        return false;
    }

    /**
     * N-01 (Codex second-pass finding) — resolve the operator-facing label
     * for a persisted component.
     *
     * `card_grid` (ziswaf_services / gallery / partners) is a DELIBERATE
     * exception: all three registry entries share the identical `type` and
     * the identical only discriminator (`mode=authored`) — the ONLY thing
     * that ever differed between them was the *content* of the `cards`
     * array, which is ordinary mutable operator data, not an identity
     * discriminator. The first Codex pass matched on that content and broke
     * after any edit that made `cards` no longer resemble a default (e.g.
     * one card-title change), silently relabeling a Gallery as "ZISWAF
     * Services" — a false, unstable identity claim.
     *
     * No field in the currently-approved schema (ADR-001/ADR-004,
     * `docs/implementation/CR-001-D-visual-page-builder.md` §11) persists a
     * distinct identity for these three variants — by design, per the CR
     * document itself, they are "the same reuse, different operator-facing
     * form labels" (form labels shown ONLY at creation time via the Add
     * Block picker, never persisted). Inventing a persisted discriminator
     * now to recover per-variant identity was explicitly out of bounds for
     * this remediation ("if distinguishing variants requires a new
     * persisted discriminator not already authorized: STOP and report" —
     * see this task's own instructions). Reported here instead of invented:
     * if distinct post-creation identity for ZISWAF Services / Gallery /
     * Partners is required, that needs its own Human Decision authorizing a
     * persisted discriminator (e.g. a `variant` config key), which this
     * remediation does not add.
     *
     * The safe, honest fix: every `card_grid` component (regardless of
     * origin or edits) gets ONE stable, generic label that never claims an
     * identity the data cannot support. This is a label-only change — D-02's
     * `isEligible()` remains the sole authorization/persistence gate and is
     * completely unaffected.
     *
     * For every other type, the prior two-tier match (exact default-config
     * match, then discriminator match) is retained — `rich_text`
     * (`source`) and `cta_button` (`intent`) both have a REQUIRED or
     * structurally-stable discriminator that ordinary content edits cannot
     * remove, so they do not share this failure mode.
     *
     * @param  array<string, mixed>  $config
     */
    public static function resolveOperatorLabel(string $type, array $config): string
    {
        if ($type === 'card_grid') {
            return 'Content Cards';
        }

        $candidates = [];

        foreach (self::all() as $entry) {
            if ($entry['key'] !== $type) {
                continue;
            }

            $candidates[] = $entry;
        }

        if ($candidates === []) {
            return $type;
        }

        foreach ($candidates as $entry) {
            $defaults = is_array($entry['default_config'] ?? null) ? $entry['default_config'] : [];

            if ($defaults !== [] && self::configSubsetEquals($defaults, $config)) {
                return $entry['operator_label'];
            }
        }

        foreach ($candidates as $entry) {
            $defaults = is_array($entry['default_config'] ?? null) ? $entry['default_config'] : [];
            $matches = true;

            foreach (['content_kind', 'source', 'mode', 'intent', 'display_mode'] as $discriminator) {
                if (array_key_exists($discriminator, $defaults)
                    && array_key_exists($discriminator, $config)
                    && $config[$discriminator] !== $defaults[$discriminator]) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $entry['operator_label'];
            }
        }

        return $candidates[0]['operator_label'];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $config
     */
    private static function configSubsetEquals(array $defaults, array $config): bool
    {
        foreach ($defaults as $field => $expected) {
            if (! array_key_exists($field, $config) || $config[$field] !== $expected) {
                return false;
            }
        }

        return true;
    }
}
