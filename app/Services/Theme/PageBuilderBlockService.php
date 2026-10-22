<?php

namespace App\Services\Theme;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Policies\ContentArticlePolicy;
use App\Policies\ContentPagePolicy;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CR-001-D — thin Page Builder orchestration over the canonical Theme
 * services (docs/implementation/CR-001-D-visual-page-builder.md §9.3). One
 * Block equals one Section containing exactly one Component: ordering lives
 * on the placement pivot, visibility on the Section, content/settings on the
 * single Component. Custom HTML sanitization is owned by
 * ThemeComponentService at the shared canonical write boundary.
 *
 * Page Builder–specific invariants enforced here (defense-in-depth behind
 * the controller's own authorization + DRAFT checks):
 *
 * - only operator blocks present in PageBuilderBlockRegistry, on an allowed
 *   canvas, may be created/updated through this service (F-06);
 * - every mutation requires the owning Theme to be DRAFT (F-13);
 * - remove/enable/disable honor expected_updated_at stale tokens (F-05);
 * - duplicate + final placement commit atomically (F-09);
 * - CODEX-CR001D-02: every write path re-validates the FINAL EFFECTIVE
 *   (type, merged config, target canvas) against PageBuilderBlockRegistry —
 *   never only the originally-requested registry key — so a discriminator
 *   override cannot smuggle in an unsupported/Advanced-Debug-only variant,
 *   and editing/duplicating/reusing an Advanced-Debug-only component through
 *   Builder fails closed;
 * - CODEX-CR001D-03: update/remove/enable/disable require a non-null,
 *   current `expected_*_updated_at` token, compared only AFTER the row that
 *   will actually be written is locked (never before), so no interleaving
 *   writer — Builder or Advanced/Debug — can slip between the freshness
 *   read and the write; removal additionally checks the single Component's
 *   own timestamp (not just the Section's), since Component content can
 *   change independently of Section state;
 * - CODEX-CR001D-04: every path that persists config re-validates Builder
 *   asset ownership (same Theme, ACTIVE status) against the FINAL config,
 *   including duplicate — which previously copied an Advanced/Debug
 *   component's config verbatim.
 */
class PageBuilderBlockService
{
    public function __construct(
        private readonly ThemeSectionService $sectionService,
        private readonly ThemeComponentService $componentService,
        private readonly ContentPagePolicy $pagePolicy,
        private readonly ContentArticlePolicy $articlePolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function addBlock(ThemeTemplate $template, string $blockKey, array $config, Principal $actor): ThemeSection
    {
        return DB::transaction(function () use ($template, $blockKey, $config, $actor) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($lockedTemplate->theme);
            $blockType = $this->resolveBlockType($lockedTemplate, $blockKey);
            $config = $this->mergeRegistryDefaults($blockKey, $config);
            // CODEX-CR001D-02: re-validate the FINAL merged config against the
            // registry — resolveBlockType() only checked the originally
            // requested key's own canvas list, which a discriminator override
            // inside $config (e.g. content_kind) could otherwise escape.
            $this->assertBuilderEligible($blockType, $config, [$lockedTemplate->content_kind]);
            $this->assertAssetOwnership($lockedTemplate, $config);
            $this->assertCmsReferences($config, $actor);

            $section = $this->sectionService->createAndPlace($lockedTemplate, [
                'is_reusable' => false,
                'layout_variant' => 'default',
            ], $actor);

            $this->componentService->create($section, $blockType, $config, $actor);

            return $section->fresh() ?? $section;
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateBlockConfig(ThemeSection $section, array $config, Principal $actor, string $expectedUpdatedAt): ThemeComponent
    {
        return DB::transaction(function () use ($section, $config, $actor, $expectedUpdatedAt) {
            $locked = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked->theme);
            $component = $this->resolveSingleComponent($locked);

            // CODEX-CR001D-03: lock the exact row we are about to write
            // BEFORE comparing its timestamp — comparing against an earlier,
            // unlocked read left a window where a concurrent writer (Builder
            // or Advanced/Debug) could commit a change between the read and
            // ThemeComponentService's own lock, silently overwritten by us.
            $lockedComponent = ThemeComponent::query()->whereKey($component->id)->lockForUpdate()->firstOrFail();
            $this->assertFresh($lockedComponent, $expectedUpdatedAt);

            // CODEX-CR001D-02: a reusable Section may be placed on several
            // Templates; the final config must remain Builder-eligible on
            // every canvas it is currently placed on, not just one.
            $contentKinds = $locked->templates()->pluck('content_kind')->unique()->all();
            $this->assertBuilderEligible($lockedComponent->type, $config, $contentKinds);
            $this->assertAssetOwnershipForSection($locked, $config);
            $this->assertCmsReferences($config, $actor);

            return $this->componentService->update($lockedComponent, $config, $actor);
        });
    }

    public function removeBlock(ThemeTemplate $template, ThemeSection $section, Principal $actor, string $expectedSectionUpdatedAt, ?string $expectedComponentUpdatedAt = null): void
    {
        DB::transaction(function () use ($template, $section, $actor, $expectedSectionUpdatedAt, $expectedComponentUpdatedAt) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $lockedSection = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($lockedTemplate->theme);
            $this->assertSectionPlaced($lockedTemplate, $lockedSection);

            // RA-03: a technical (non-Builder-managed) Section — including a
            // single-component one whose type/config the registry does not
            // recognize — is never removable through Page Builder. Checked
            // BEFORE any freshness comparison: removal is refused outright,
            // not merely gated behind a token. Advanced/Debug remains the
            // canonical escape hatch for removing such content.
            if (! $this->isSectionBuilderManaged($lockedSection)) {
                throw new ThemeValidationException(
                    'technical_section_not_removable',
                    'This section is managed in Advanced/Debug and cannot be removed from Page Builder.'
                );
            }

            $this->assertFresh($lockedSection, $expectedSectionUpdatedAt);

            // CODEX-CR001D-03: the Section's own timestamp does not change
            // when only its single Component's config is edited, so a
            // Section-only freshness check can miss content changed since
            // the operator last viewed this block. When exactly one
            // Component exists, its own (locked) timestamp must also match.
            $components = $lockedSection->components()->orderBy('position')->get();

            if ($components->count() === 1) {
                $lockedComponent = ThemeComponent::query()->whereKey($components->first()->id)->lockForUpdate()->firstOrFail();
                $this->assertFresh($lockedComponent, $expectedComponentUpdatedAt);
            }

            $this->sectionService->removeFromTemplate($lockedTemplate, $lockedSection, $actor);
        });
    }

    public function setVisibility(ThemeSection $section, bool $visible, Principal $actor, string $expectedUpdatedAt): ThemeSection
    {
        return DB::transaction(function () use ($section, $visible, $actor, $expectedUpdatedAt) {
            $locked = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked->theme);
            $this->assertFresh($locked, $expectedUpdatedAt);

            return $this->sectionService->update($locked, ['visible' => $visible], $actor);
        });
    }

    public function duplicateBlock(ThemeTemplate $template, ThemeSection $source, Principal $actor): ThemeSection
    {
        return DB::transaction(function () use ($template, $source, $actor) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $lockedSource = ThemeSection::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($lockedTemplate->theme);
            $this->assertSectionPlaced($lockedTemplate, $lockedSource);

            $component = $this->resolveSingleComponent($lockedSource);
            $sourceConfig = $component->config ?? [];

            // CODEX-CR001D-02 / CODEX-CR001D-04: duplicate previously copied
            // an existing Component's type+config verbatim, including one
            // created via Advanced/Debug — bypassing both the registry
            // eligibility gate and the Builder asset-ownership gate that
            // addBlock() already enforces for the exact same persistence.
            $this->assertBuilderEligible($component->type, $sourceConfig, [$lockedTemplate->content_kind]);
            $this->assertAssetOwnership($lockedTemplate, $sourceConfig);
            $this->assertCmsReferences($sourceConfig, $actor);

            $duplicate = $this->sectionService->createAndPlace($lockedTemplate, [
                'is_reusable' => false,
                'layout_variant' => 'default',
            ], $actor);

            $this->componentService->create($duplicate, $component->type, $sourceConfig, $actor);

            $this->sectionService->reorder($lockedTemplate, $this->orderedIdsWithDuplicateAfter($lockedTemplate, $lockedSource, $duplicate), $actor);

            return $duplicate->fresh() ?? $duplicate;
        });
    }

    public function placeReusable(ThemeTemplate $template, ThemeSection $section, Principal $actor): void
    {
        DB::transaction(function () use ($template, $section, $actor) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $lockedSection = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($lockedTemplate->theme);

            if ($lockedSection->theme_id !== $lockedTemplate->theme_id) {
                throw new ThemeValidationException(
                    'cross_theme_section',
                    'A reusable block may only be placed within its own theme.'
                );
            }

            // CODEX-CR001D-02: placing an existing reusable Section onto a
            // Builder-managed canvas must be gated by the SAME registry
            // eligibility check as adding a new block — otherwise an
            // Advanced/Debug-only reusable Section (or one whose content is
            // simply not allowed on this canvas) could be imported into
            // Builder without ever passing through the registry.
            $component = $this->resolveSingleComponent($lockedSection);
            $this->assertBuilderEligible($component->type, $component->config ?? [], [$lockedTemplate->content_kind]);

            $this->sectionService->placeReusable($lockedTemplate, $lockedSection, $actor);
        });
    }

    public function resolveSingleComponent(ThemeSection $section): ThemeComponent
    {
        $components = $section->components()->orderBy('position')->get();

        if ($components->count() !== 1 || ! $components->first() instanceof ThemeComponent) {
            throw new ThemeValidationException(
                'not_a_single_block_section',
                'This section holds more than one component — edit it in Advanced/Debug.'
            );
        }

        return $components->first();
    }

    private function resolveBlockType(ThemeTemplate $template, string $blockKey): string
    {
        if (! PageBuilderBlockRegistry::has($blockKey)) {
            throw new ThemeValidationException(
                'unknown_page_builder_block',
                "Block '{$blockKey}' is not part of the Page Builder library."
            );
        }

        $entry = PageBuilderBlockRegistry::all()[$blockKey];

        if (! in_array($template->content_kind, $entry['allowed_canvases'], true)) {
            throw new ThemeValidationException(
                'block_not_allowed_on_canvas',
                "Block '{$blockKey}' may not be added to this layout."
            );
        }

        return $entry['key'];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mergeRegistryDefaults(string $blockKey, array $config): array
    {
        $defaults = PageBuilderBlockRegistry::all()[$blockKey]['default_config'] ?? [];

        return array_replace(is_array($defaults) ? $defaults : [], $config);
    }

    private function assertDraft(Theme $theme): void
    {
        if ($theme->status !== 'DRAFT') {
            throw new ThemeValidationException(
                'theme_not_draft',
                'Page Builder editing targets DRAFT themes only.'
            );
        }
    }

    /**
     * CODEX-CR001D-02 — validate the FINAL EFFECTIVE (type, config)
     * combination against every canvas it must remain valid on. Called only
     * after the config a caller actually intends to persist has been fully
     * resolved (registry defaults merged for create; the submitted config
     * as-is for update, since update always replaces the whole config) —
     * never against the originally-requested registry key alone.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $contentKinds
     */
    private function assertBuilderEligible(string $type, array $config, array $contentKinds): void
    {
        $kinds = $contentKinds === [] ? [null] : $contentKinds;

        foreach ($kinds as $contentKind) {
            if (! PageBuilderBlockRegistry::isEligible($type, $config, $contentKind)) {
                throw new ThemeValidationException(
                    'block_not_builder_eligible',
                    'This configuration is not an allowed Page Builder block for this layout.'
                );
            }
        }
    }

    /**
     * CODEX-CR001D-03 — fail closed on a missing/null token: the caller MUST
     * prove it observed the current state before mutating it. $model MUST
     * already be the row locked (via lockForUpdate, in the same transaction)
     * for the write that is about to happen — comparing against an earlier,
     * unlocked read would leave a race window for an interleaving writer.
     */
    private function assertFresh(ThemeSection|ThemeComponent $model, ?string $expectedUpdatedAt): void
    {
        if ($expectedUpdatedAt === null) {
            throw new ThemeValidationException(
                'stale_edit',
                'A current version token is required to modify this block — reload to get the latest version.'
            );
        }

        $current = $model->updated_at?->format('Y-m-d\TH:i:s.uP');
        $expected = $this->normalizeExpectedTimestamp($expectedUpdatedAt);

        if ($current !== $expected) {
            throw new ThemeValidationException(
                'stale_edit',
                'This block was changed by someone else — reload to see the latest version.'
            );
        }
    }

    /**
     * CODEX-CR001D-01 — Builder-level CMS reference integrity. The canonical
     * validator checks ULID *shape* only; Page Builder additionally requires
     * every referenced CMS row to actually exist in the matching table, so an
     * operator cannot persist a dangling or cross-kind reference
     * (page kind + article ULID, or vice versa — the ULID simply is not found
     * in the claimed kind's table). Scoped to Builder orchestration only, the
     * same boundary as the D-04 asset gate; canonical/Advanced-Debug
     * semantics are unchanged. Only reference ULIDs are stored — never title,
     * body, SEO, or other business content.
     *
     * RA-02 (Codex second-pass finding) — existence alone is not authority.
     * An actor must be canonically authorized (`ContentPagePolicy::viewPage`
     * / `ContentArticlePolicy::viewArticle` — the same per-resource policy
     * IMP-005's own CMS admin screens use, reused here rather than
     * duplicated) to view the SPECIFIC referenced resource before this
     * Builder write path may newly persist that reference. A known ULID is
     * not authorization to select it. This does not touch canonical CMS
     * authorization itself and does not affect Advanced/Debug.
     *
     * @param  array<string, mixed>  $config
     */
    private function assertCmsReferences(array $config, Principal $actor): void
    {
        $pairs = [];

        if (($config['source'] ?? null) === 'cms_content') {
            $pairs[] = [$config['content_kind'] ?? null, $config['content_ulid'] ?? null];
        }

        if (($config['destination_type'] ?? null) === 'CMS_CONTENT') {
            $pairs[] = [$config['destination_content_kind'] ?? null, $config['destination_content_ulid'] ?? null];
        }

        foreach ($config['cards'] ?? [] as $card) {
            if (is_array($card) && ($card['destination_type'] ?? null) === 'CMS_CONTENT') {
                $pairs[] = [$card['destination_content_kind'] ?? null, $card['destination_content_ulid'] ?? null];
            }
        }

        foreach ($pairs as [$kind, $ulid]) {
            if (! is_string($ulid) || $ulid === '') {
                throw new ThemeValidationException(
                    'invalid_content_reference',
                    'The selected content no longer exists — reselect it before saving.'
                );
            }

            if ($kind === 'page') {
                $page = CmsPage::query()->where('ulid', $ulid)->first();

                if (! $page instanceof CmsPage || ! $this->pagePolicy->viewPage($actor, $page)) {
                    throw new ThemeValidationException(
                        'invalid_content_reference',
                        'The selected content no longer exists — reselect it before saving.'
                    );
                }
            } elseif ($kind === 'article') {
                $article = CmsArticle::query()->where('ulid', $ulid)->first();

                if (! $article instanceof CmsArticle || ! $this->articlePolicy->viewArticle($actor, $article)) {
                    throw new ThemeValidationException(
                        'invalid_content_reference',
                        'The selected content no longer exists — reselect it before saving.'
                    );
                }
            } else {
                throw new ThemeValidationException(
                    'invalid_content_reference',
                    'The selected content no longer exists — reselect it before saving.'
                );
            }
        }
    }

    /**
     * RA-03 (Codex second-pass finding) — canonical technical-section
     * detection, reused for BOTH the server-side Remove guard below and the
     * controller's UI flags, so they can never disagree. A Section is
     * Builder-managed (removable/configurable/duplicatable through Page
     * Builder) only when it resolves to EXACTLY one Component AND that
     * Component's (type, config) is D-02-eligible for every canvas the
     * Section is currently placed on — the identical eligibility rule
     * addBlock()/updateBlockConfig()/duplicateBlock() already enforce for
     * persistence. A single-component Section of an Advanced/Debug-only
     * type (e.g. `navigation_menu_slot`, `image`) is therefore correctly
     * "technical" even though a naive `count() > 1` check would miss it.
     */
    public function isSectionBuilderManaged(ThemeSection $section): bool
    {
        $components = $section->components()->orderBy('position')->get();

        if ($components->count() !== 1) {
            return false;
        }

        $component = $components->first();
        $contentKinds = $section->templates()->pluck('content_kind')->unique()->all();
        $kinds = $contentKinds === [] ? [null] : $contentKinds;

        foreach ($kinds as $contentKind) {
            if (! PageBuilderBlockRegistry::isEligible($component->type, $component->config ?? [], $contentKind)) {
                return false;
            }
        }

        return true;
    }

    /**
     * CODEX-CR001D-04 — Builder-specific asset eligibility: same Theme AND
     * ACTIVE status. Deliberately scoped to Page Builder orchestration only
     * (not added to the canonical ThemeComponentService/ComponentConfig
     * Validator), since Advanced/Debug may have different canonical
     * permissions/semantics for asset selection.
     *
     * @param  array<string, mixed>  $config
     */
    private function assertAssetOwnership(ThemeTemplate $template, array $config): void
    {
        foreach ($this->extractAssetUlids($config) as $ulid) {
            $eligible = $template->theme->assets()->where('ulid', $ulid)->where('status', 'ACTIVE')->exists();

            if (! $eligible) {
                throw new ThemeValidationException(
                    'cross_theme_asset',
                    'An asset reference does not belong to the theme being edited or is not eligible for selection.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertAssetOwnershipForSection(ThemeSection $section, array $config): void
    {
        foreach ($this->extractAssetUlids($config) as $ulid) {
            $eligible = $section->theme()->whereHas('assets', fn ($query) => $query->where('ulid', $ulid)->where('status', 'ACTIVE'))->exists();

            if (! $eligible) {
                throw new ThemeValidationException(
                    'cross_theme_asset',
                    'An asset reference does not belong to the theme being edited or is not eligible for selection.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function extractAssetUlids(array $config): array
    {
        $ulids = [];

        foreach (['background_theme_asset_ulid', 'theme_asset_ulid'] as $field) {
            if (is_string($config[$field] ?? null) && $config[$field] !== '') {
                $ulids[] = $config[$field];
            }
        }

        foreach ($config['cards'] ?? [] as $card) {
            if (is_array($card) && is_string($card['theme_asset_ulid'] ?? null) && $card['theme_asset_ulid'] !== '') {
                $ulids[] = $card['theme_asset_ulid'];
            }
        }

        return array_values(array_unique($ulids));
    }

    private function assertSectionPlaced(ThemeTemplate $template, ThemeSection $section): void
    {
        $placed = $template->sections()->whereKey($section->id)->exists();

        if (! $placed) {
            throw new ThemeValidationException(
                'cross_template_section',
                'This block does not belong to the page being edited.'
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function orderedIdsWithDuplicateAfter(ThemeTemplate $template, ThemeSection $source, ThemeSection $duplicate): array
    {
        $ordered = $template->sections()->orderByPivot('position')->pluck('theme_sections.id')->all();
        $result = [];

        foreach ($ordered as $id) {
            $result[] = $id;

            if ((int) $id === (int) $source->id) {
                $result[] = $duplicate->id;
            }
        }

        if (! in_array($duplicate->id, $result, true)) {
            $result[] = $duplicate->id;
        }

        return array_values(array_unique($result));
    }

    private function normalizeExpectedTimestamp(string $expected): string
    {
        try {
            return Carbon::parse($expected)->format('Y-m-d\TH:i:s.uP');
        } catch (\Throwable) {
            return $expected;
        }
    }
}
