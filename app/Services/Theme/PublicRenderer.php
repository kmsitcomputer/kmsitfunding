<?php

namespace App\Services\Theme;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeActivation;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Models\Theme\ThemeTemplate;
use App\Services\Campaign\CampaignProjectionResolver;
use App\Services\Campaign\ProgramProjectionResolver;
use App\Services\Content\MediaTokenResolver;
use App\Services\Content\PublishedContent;
use Illuminate\Support\Facades\Log;

/**
 * IMP-006 — resolves the active Theme's Template/Section/Component tree
 * into a plain, already-resolved props array for the Inertia public
 * rendering pipeline (docs/implementation/IMP-006-theme-engine.md section
 * 13). Read-only. Implements section 22's rendering-failure fallback
 * matrix: a missing/invalid Template/Component/binding degrades gracefully,
 * never a fatal error.
 */
class PublicRenderer
{
    public function __construct(
        private readonly MediaTokenResolver $mediaTokenResolver,
        private readonly ThemeAssetTokenResolver $themeAssetTokenResolver,
        private readonly NavigationDestinationResolver $destinationResolver,
    ) {}

    public function activeTheme(): ?Theme
    {
        $pointer = ThemeActivation::query()->whereKey(1)->first();
        $active = $pointer?->active_theme_id !== null
            ? Theme::find($pointer->active_theme_id)
            : null;

        return $active ?? $this->systemDefaultTheme();
    }

    /**
     * Section 22 ("Inactive/misconfigured Theme... should be structurally
     * impossible... but defensively"): an unseeded environment (no System
     * Default Theme row at all) is the ultimate edge of that same rule —
     * degrades to null, never a fatal error, rather than throwing.
     */
    public function systemDefaultTheme(): ?Theme
    {
        $default = Theme::where('is_system_default', true)->first();

        if ($default === null) {
            Log::warning('theme.system_default_missing');
        }

        return $default;
    }

    /**
     * Homepage rendering (section 8/13): HomepageContentResolver returns the
     * raw CmsPage (its own doc comment declines to build the neutral DTO
     * itself — "belongs to the public ContentResolverService slice... not
     * invented early here"). Converts it here, mirroring
     * ContentResolverService::toPublishedContent()'s exact construction
     * (that method is private and this specification does not modify
     * IMP-005 to expose it).
     */
    public function toPublishedContent(CmsPage $page): PublishedContent
    {
        $revision = $page->publishedRevision;

        return new PublishedContent(
            ulid: $page->ulid,
            kind: 'page',
            title: $revision->title,
            excerpt: $revision->excerpt,
            bodyHtml: $revision->body_html,
            metaTitle: $revision->meta_title,
            metaDescription: $revision->meta_description,
            ogTitle: $revision->og_title,
            ogDescription: $revision->og_description,
            ogImageUrl: $revision->og_image_asset_id !== null
                ? $this->mediaTokenResolver->resolveUrl($revision->ogImageAsset->ulid)
                : null,
            noIndex: (bool) $revision->no_index,
            canonicalPath: $revision->slug_snapshot,
            articleType: null,
        );
    }

    /**
     * @return array{template_slug: ?string, sections: array<int, array<string, mixed>>, branding: array<string, mixed>}
     */
    public function renderForContentKind(string $contentKind, ?PublishedContent $content = null): array
    {
        $theme = $this->activeTheme();
        $template = $theme !== null
            ? ThemeTemplate::where('theme_id', $theme->id)->where('content_kind', $contentKind)->first()
            : null;

        if ($template === null && ($theme === null || ! $theme->is_system_default)) {
            $default = $this->systemDefaultTheme();
            $template = $default !== null
                ? ThemeTemplate::where('theme_id', $default->id)->where('content_kind', $contentKind)->first()
                : null;
            $theme = $template !== null ? $default : $theme;

            if ($template === null) {
                Log::warning('theme.template_missing_for_content_kind', ['content_kind' => $contentKind]);
            }
        }

        return [
            'template_slug' => $template?->slug,
            'sections' => $template !== null ? $this->buildSections($template, $content, $theme) : [],
            'branding' => $this->buildBranding($theme),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSections(ThemeTemplate $template, ?PublishedContent $content, ?Theme $theme): array
    {
        $sections = [];

        foreach ($template->sections as $section) {
            if (! $section->visible) {
                continue;
            }

            $components = [];

            foreach ($section->components as $component) {
                $rendered = $this->renderComponent($component, $content, $theme);

                if ($rendered !== null) {
                    $components[] = $rendered;
                }
            }

            $sections[] = [
                'ulid' => $section->ulid,
                'layout_variant' => $section->layout_variant,
                'components' => $components,
            ];
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>|null null means "skip this component"
     *                                   (section 22 fallback).
     */
    private function renderComponent(ThemeComponent $component, ?PublishedContent $content, ?Theme $theme = null): ?array
    {
        $config = $component->config ?? [];

        try {
            $props = match ($component->type) {
                'hero' => $this->renderHero($config),
                'rich_text' => $this->renderRichText($config, $content),
                'image' => $this->renderImage($config),
                'cta_button' => $this->renderCtaButton($config),
                'content_list' => $this->renderContentList($config),
                'stats' => $config['items'] ?? [],
                'banner' => $this->renderBanner($config),
                'card_grid' => $this->renderCardGrid($config),
                'navigation_menu_slot' => $this->renderNavigationMenuSlot($config, $theme),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('theme.component_render_failed', [
                'component_ulid' => $component->ulid,
                'type' => $component->type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($props === null) {
            return null;
        }

        return ['ulid' => $component->ulid, 'type' => $component->type, 'props' => $props];
    }

    private function renderHero(array $config): array
    {
        return [
            'headline' => $config['headline'] ?? '',
            'subheading' => $config['subheading'] ?? null,
            'background_url' => isset($config['background_theme_asset_ulid'])
                ? $this->themeAssetTokenResolver->resolveUrl($config['background_theme_asset_ulid'])
                : null,
            'cta_label' => $config['cta_label'] ?? null,
        ];
    }

    private function renderRichText(array $config, ?PublishedContent $content): ?array
    {
        if (($config['source'] ?? null) === 'caption') {
            return ['html' => e($config['caption'] ?? '')];
        }

        // source === cms_content: bind to the CURRENT resolved content only
        // when this component's Template IS the one rendering that content
        // (a content_list-bound rich_text referencing a DIFFERENT identity
        // is out of v1 scope — section 12 "read exclusively through
        // ContentResolverService").
        if ($content === null) {
            return null;
        }

        return ['html' => $this->resolveMediaTokens($content->bodyHtml)];
    }

    private function renderImage(array $config): ?array
    {
        $url = ($config['source'] ?? null) === 'theme_asset'
            ? $this->themeAssetTokenResolver->resolveUrl($config['theme_asset_ulid'] ?? '')
            : $this->mediaTokenResolver->resolveUrl($config['media_token'] ?? '');

        if ($url === null) {
            return null;
        }

        return ['url' => $url, 'alt' => $config['alt_text'] ?? ''];
    }

    private function renderCtaButton(array $config): ?array
    {
        $url = $this->destinationResolver->resolve($config);

        if ($url === null) {
            return null;
        }

        return ['label' => $config['label'] ?? '', 'variant' => $config['variant'] ?? 'primary', 'url' => $url];
    }

    private function renderContentList(array $config): array
    {
        $kind = $config['content_kind'] ?? 'page';

        // IMP-006 amendment (Human change control, targeted/additive):
        // 'program' and 'campaign' are IMP-007-owned domain truth, never
        // duplicated into Theme tables — resolved here via IMP-007's own
        // canonical read projections (ProgramProjectionResolver/
        // CampaignProjectionResolver), consumed read-only exactly like
        // ContentResolverService/MediaTokenResolver already are for IMP-005.
        // The original page/article branch below is UNCHANGED.
        if ($kind === 'program') {
            return app(ProgramProjectionResolver::class)->latestPublished((int) ($config['limit'] ?? 6));
        }

        if ($kind === 'campaign') {
            return app(CampaignProjectionResolver::class)->latestPublished((int) ($config['limit'] ?? 6));
        }

        $limit = (int) ($config['limit'] ?? 6);
        $order = ($config['order'] ?? 'latest') === 'oldest' ? 'asc' : 'desc';

        $query = $kind === 'article'
            ? CmsArticle::query()->where('status', 'PUBLISHED')
            : CmsPage::query()->where('status', 'PUBLISHED');

        if ($kind === 'article' && ! empty($config['article_type'])) {
            $query->whereHas('publishedRevision', fn ($q) => $q->where('article_type', $config['article_type']));
        }

        $items = $query->orderBy('updated_at', $order)->limit($limit)->get();

        return $items->map(fn ($item) => [
            'ulid' => $item->ulid,
            'title' => $item->title,
            'url' => $this->destinationResolver->resolve([
                'destination_type' => 'CMS_CONTENT',
                'destination_content_kind' => $kind,
                'destination_content_ulid' => $item->ulid,
            ]),
        ])->all();
    }

    private function renderBanner(array $config): array
    {
        $url = isset($config['destination_type']) ? $this->destinationResolver->resolve($config) : null;

        return ['text' => $config['text'] ?? '', 'dismissible' => (bool) ($config['dismissible'] ?? false), 'url' => $url];
    }

    private function renderCardGrid(array $config): array
    {
        if (($config['mode'] ?? null) === 'content_list') {
            return $this->renderContentList($config);
        }

        $cards = $config['cards'] ?? [];

        return array_map(function ($card) {
            $url = isset($card['destination_type']) ? $this->destinationResolver->resolve($card) : null;

            return [
                'title' => $card['title'] ?? '',
                'text' => $card['text'] ?? null,
                'image_url' => isset($card['theme_asset_ulid']) ? $this->themeAssetTokenResolver->resolveUrl($card['theme_asset_ulid']) : null,
                'url' => $url,
            ];
        }, $cards);
    }

    /**
     * IMP-006's own doc comment on the original stub read: "menu items are
     * resolved server-side per menu_code at a future slice; this slot is a
     * placeholder position." Completes that already-designed, already
     * schema-validated extension point — the closed component-type set,
     * ComponentConfigValidator's schema, and the navigation_menu_slot
     * config shape are unchanged; only the previously-stubbed rendering is
     * filled in, using ThemeNavigationMenu/ThemeNavigationItem/
     * NavigationDestinationResolver exactly as IMP-006 itself built them
     * for this purpose (section 14). An item whose destination resolves to
     * null is hidden, never a broken link (same rule
     * NavigationDestinationResolver's own doc comment states). One level
     * of nesting only, matching the locked schema.
     *
     * @return array{menu_code: ?string, items: array<int, array<string, mixed>>}
     */
    private function renderNavigationMenuSlot(array $config, ?Theme $theme): array
    {
        $menuCode = $config['menu_code'] ?? null;

        if ($menuCode === null || $theme === null) {
            return ['menu_code' => $menuCode, 'items' => []];
        }

        $menu = ThemeNavigationMenu::where('theme_id', $theme->id)
            ->where('code', $menuCode)
            ->first();

        if ($menu === null) {
            return ['menu_code' => $menuCode, 'items' => []];
        }

        $items = $menu->topLevelItems()
            ->with('children')
            ->get()
            ->filter(fn ($item) => $item->visible)
            ->map(fn ($item) => $this->renderNavigationItem($item))
            ->filter(fn (?array $rendered) => $rendered !== null)
            ->values()
            ->all();

        return ['menu_code' => $menuCode, 'items' => $items];
    }

    /**
     * @return array{label: string, url: string, children: array<int, array<string, mixed>>}|null
     */
    private function renderNavigationItem(ThemeNavigationItem $item): ?array
    {
        $url = $this->destinationResolver->resolve([
            'destination_type' => $item->destination_type,
            'destination_route' => $item->destination_route,
            'destination_content_kind' => $item->destination_content_kind,
            'destination_content_ulid' => $item->destination_content_ulid,
            'destination_external_url' => $item->destination_external_url,
        ]);

        if ($url === null) {
            return null;
        }

        $children = $item->children
            ->filter(fn ($child) => $child->visible)
            ->map(fn ($child) => $this->renderNavigationItem($child))
            ->filter(fn (?array $rendered) => $rendered !== null)
            ->values()
            ->all();

        return ['label' => $item->label, 'url' => $url, 'children' => $children];
    }

    private function resolveMediaTokens(string $bodyHtml): string
    {
        return preg_replace_callback(
            '/data-media="([0-9A-HJKMNP-TV-Z]{26})"/',
            function (array $matches) {
                $url = $this->mediaTokenResolver->resolveUrl($matches[1]);

                return $url !== null ? 'src="'.e($url).'"' : '';
            },
            $bodyHtml
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBranding(?Theme $theme): array
    {
        $branding = $theme?->branding()->first();

        if ($branding === null && ($theme === null || ! $theme->is_system_default)) {
            $branding = $this->systemDefaultTheme()?->branding()->first();
        }

        if ($branding === null) {
            return ['color_tokens' => [], 'font_family' => 'system', 'logo_url' => null, 'favicon_url' => null];
        }

        return [
            'color_tokens' => $branding->color_tokens,
            'font_family' => $branding->font_family,
            'logo_url' => $branding->logoAsset ? $this->themeAssetTokenResolver->resolveUrl($branding->logoAsset->ulid) : null,
            'favicon_url' => $branding->faviconAsset ? $this->themeAssetTokenResolver->resolveUrl($branding->faviconAsset->ulid) : null,
        ];
    }
}
