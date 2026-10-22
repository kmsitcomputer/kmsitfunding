<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Policies\ContentArticlePolicy;
use App\Policies\ContentPagePolicy;
use App\Policies\ThemePolicy;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\PageBuilderBlockRegistry;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CR-001-D — operator-facing Page Builder boundary
 * (docs/implementation/CR-001-D-visual-page-builder.md §40). Thin wrapper
 * over PageBuilderBlockService + ThemeSectionService, mirroring
 * SiteDesignController's resolve-actor → authorize → guard-DRAFT →
 * delegate → catch-exception shape exactly. No business logic, no new
 * permission, no new lifecycle — publish/preview reuse the existing
 * SiteDesign contracts. {theme}/{template}/{section} bound by ULID.
 */
class PageBuilderController extends Controller
{
    private function abortUnlessDraft(Theme $theme): void
    {
        abort_if($theme->status !== 'DRAFT', 409, 'Page Builder editing targets DRAFT themes only — clone the active design first.');
    }

    private function canvasLabel(string $contentKind): string
    {
        return match ($contentKind) {
            'home' => 'Home Layout',
            'page' => 'Page Layout',
            'article' => 'Article Layout',
            default => ucfirst($contentKind).' Layout',
        };
    }

    public function index(Request $request, ThemePolicy $policy, Theme $theme): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $theme), 403);

        $templates = $theme->templates()->whereIn('content_kind', ['home', 'page', 'article'])->get();

        return Inertia::render('Admin/PageBuilder/Index', [
            'theme' => $theme,
            'canvases' => $templates->map(fn (ThemeTemplate $template): array => [
                'label' => $this->canvasLabel($template->content_kind),
                'contentKind' => $template->content_kind,
                'templateUlid' => $template->ulid,
                'blockCount' => $template->sections()->count(),
            ])->values(),
            'isDraft' => $theme->status === 'DRAFT',
            'canUpdate' => $policy->update($actor, $theme),
            'previewUrl' => "/admin/site-design/{$theme->ulid}/preview",
        ]);
    }

    public function show(Request $request, ThemePolicy $policy, ContentPagePolicy $pagePolicy, ContentArticlePolicy $articlePolicy, PageBuilderBlockService $service, ThemeTemplate $template): Response
    {
        $theme = $template->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $theme), 403);

        $sections = $template->sections()->with('components')->get();

        $blocks = $sections->map(function (ThemeSection $section) use ($service): array {
            $components = $section->components;
            $component = $components->count() === 1 ? $components->first() : null;
            // RA-03/N-03: the SAME canonical eligibility check the service
            // enforces before persistence — never a naive `count() > 1`
            // heuristic, which misses a single-component Advanced/Debug-only
            // type (navigation_menu_slot, image, ...). UI and server can
            // never disagree because both call this one method.
            $managed = $service->isSectionBuilderManaged($section);
            $technical = ! $managed;

            return [
                'ulid' => $section->ulid,
                'visible' => $section->visible,
                'isReusable' => $section->is_reusable,
                'isTechnical' => $technical,
                'type' => $component?->type,
                'config' => $component?->config ?? null,
                'operatorLabel' => $technical
                    ? 'Technical Section'
                    : PageBuilderBlockRegistry::resolveOperatorLabel($component->type, $component->config ?? []),
                'canConfigure' => $managed,
                'canDuplicate' => $managed,
                // RA-03: Remove is refused server-side for a technical
                // Section (see PageBuilderBlockService::removeBlock) —
                // the control is hidden to match, not merely disabled.
                'canRemove' => $managed,
                'updatedAt' => $technical
                    ? $section->updated_at?->format('Y-m-d\TH:i:s.uP')
                    : $component->updated_at?->format('Y-m-d\TH:i:s.uP'),
                'sectionUpdatedAt' => $section->updated_at?->format('Y-m-d\TH:i:s.uP'),
            ];
        })->values();

        $reusableBlocks = $theme->sections()->where('is_reusable', true)->with('components')->get()
            ->map(fn (ThemeSection $section): array => [
                'sectionUlid' => $section->ulid,
                'operatorLabel' => $section->components->count() === 1
                    ? PageBuilderBlockRegistry::resolveOperatorLabel($section->components->first()->type, $section->components->first()->config ?? [])
                    : 'Technical Section',
            ])->values();

        $cmsPagesPage = $pagePolicy->view($actor)
            ? CmsPage::query()->select(['ulid', 'title'])->latest('updated_at')->paginate(20)
            : null;
        $cmsArticlesPage = $articlePolicy->view($actor)
            ? CmsArticle::query()->select(['ulid', 'title'])->latest('updated_at')->paginate(20)
            : null;

        return Inertia::render('Admin/PageBuilder/Show', [
            'theme' => $theme,
            'template' => $template,
            'canvasLabel' => $this->canvasLabel($template->content_kind),
            'contentKind' => $template->content_kind,
            'blocks' => $blocks,
            'blockLibrary' => collect(PageBuilderBlockRegistry::forCanvas($template->content_kind))
                ->map(fn (array $entry, string $registryKey): array => array_merge($entry, ['registryKey' => $registryKey]))
                ->values(),
            'reusableBlocks' => $reusableBlocks,
            'assets' => $theme->assets()->where('status', 'ACTIVE')->get(['ulid', 'original_filename', 'mime_type']),
            // CODEX-CR001D-01 / RA-01: bounded CMS selector data. Same
            // recent-20 bound as the canonical CMS index pages — not an
            // invented limit; page 2+ is reached through the small
            // authenticated `cmsContent()` endpoint below, never by
            // widening this initial page. Each list is fail-closed behind
            // its canonical view policy: without content.view the selector
            // is empty, never bypassed. Only `ulid`/`title` are selected —
            // no other CMS column is exposed. No new permission, no new CMS
            // API surface, no public route.
            'cmsPages' => $cmsPagesPage?->items() ?? [],
            'cmsPagesHasMore' => $cmsPagesPage?->hasMorePages() ?? false,
            'cmsArticles' => $cmsArticlesPage?->items() ?? [],
            'cmsArticlesHasMore' => $cmsArticlesPage?->hasMorePages() ?? false,
            'cmsContentUrl' => route('page-builder.cms-content'),
            // RA-02: title disclosure now requires the SAME per-resource
            // canonical policy as the selector lists themselves — a known
            // ULID already referenced by a block is not authority to see
            // its title (see referencedContentTitles() below).
            'cmsTitles' => $this->referencedContentTitles($sections, $actor, $pagePolicy, $articlePolicy),
            // N-03: escape hatch to the existing Advanced/Debug surface.
            'advancedUrl' => route('theme.show', $theme),
            'errors' => session()->get('errors')?->getBag('default')?->messages() ?? [],
            'isDraft' => $theme->status === 'DRAFT',
            'canUpdate' => $policy->update($actor, $theme),
            'previewUrl' => "/admin/site-design/{$theme->ulid}/preview",
            'publishUrl' => "/admin/site-design/{$theme->ulid}/publish",
        ]);
    }

    /**
     * Resolve display titles for CMS ULIDs already referenced by blocks on
     * this canvas, so the selector can keep the current selection visible
     * even when it falls outside the recent-20 lists. Batched per kind —
     * only referenced ULIDs are queried, never whole tables.
     *
     * RA-02 (Codex second-pass finding) — existence of a reference is NOT
     * authorization to see its title. Every resolved row is checked against
     * the SAME per-resource canonical policy (`viewPage`/`viewArticle`) the
     * selector lists themselves already use; an unauthorized actor simply
     * gets no entry for that ULID (the frontend then shows a neutral
     * "Restricted content" placeholder — see CmsContentSelect.vue — never
     * the raw ULID, and never the title/body/SEO of a resource the actor
     * cannot view). No THEME_VIEW/THEME_UPDATE/role-name inference, no
     * Super Admin bypass — the same rule applies uniformly.
     *
     * @param  Collection<int, ThemeSection>  $sections
     * @return array<string, string>
     */
    private function referencedContentTitles($sections, Principal $actor, ContentPagePolicy $pagePolicy, ContentArticlePolicy $articlePolicy): array
    {
        $pageUlids = [];
        $articleUlids = [];

        foreach ($sections as $section) {
            foreach ($section->components as $component) {
                $config = is_array($component->config) ? $component->config : [];

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
                        continue;
                    }
                    if ($kind === 'page') {
                        $pageUlids[] = $ulid;
                    } elseif ($kind === 'article') {
                        $articleUlids[] = $ulid;
                    }
                }
            }
        }

        $titles = [];

        if ($pageUlids !== []) {
            foreach (CmsPage::query()->whereIn('ulid', array_unique($pageUlids))->get(['ulid', 'title']) as $page) {
                if ($pagePolicy->viewPage($actor, $page)) {
                    $titles[$page->ulid] = $page->title;
                }
            }
        }

        if ($articleUlids !== []) {
            foreach (CmsArticle::query()->whereIn('ulid', array_unique($articleUlids))->get(['ulid', 'title']) as $article) {
                if ($articlePolicy->viewArticle($actor, $article)) {
                    $titles[$article->ulid] = $article->title;
                }
            }
        }

        return $titles;
    }

    /**
     * RA-01 (Codex second-pass finding) — bounded, authenticated, canonically
     * authorized CMS selector pagination. Same query shape as the canonical
     * `Cms\PageController::index()`/`ArticleController::index()`
     * (`latest('updated_at')->paginate(20)`) — not a new CMS API, not an
     * unbounded load, not a raw-ULID input path. Only `ulid`/`title` ever
     * leave this endpoint. Read-only, same-origin, same admin route group
     * and middleware as the rest of Page Builder.
     */
    public function cmsContent(Request $request, ContentPagePolicy $pagePolicy, ContentArticlePolicy $articlePolicy): JsonResponse
    {
        $actor = $this->resolveActingPrincipal($request);

        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:page,article'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $policy = $validated['kind'] === 'article' ? $articlePolicy : $pagePolicy;
        abort_unless($policy->view($actor), 403);

        $query = $validated['kind'] === 'article' ? CmsArticle::query() : CmsPage::query();
        $paginator = $query->select(['ulid', 'title'])
            ->latest('updated_at')
            ->paginate(20, ['ulid', 'title'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'items' => $paginator->items(),
            'hasMore' => $paginator->hasMorePages(),
        ]);
    }

    public function store(Request $request, ThemePolicy $policy, ThemeTemplate $template, PageBuilderBlockService $service): RedirectResponse
    {
        $theme = $template->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate([
            'block_key' => ['required', 'string', 'max:64'],
            'config' => ['required', 'array'],
        ]);

        try {
            $service->addBlock($template, $validated['block_key'], $validated['config'], $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['block_key' => $e->getMessage()]);
        }

        return redirect()->route('page-builder.show', $template)->with('status', 'block-added');
    }

    public function update(Request $request, ThemePolicy $policy, ThemeSection $section, PageBuilderBlockService $service): RedirectResponse
    {
        $theme = $section->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate([
            'config' => ['required', 'array'],
            // CODEX-CR001D-03: mandatory — a missing/null token must fail
            // closed, never silently skip the freshness check.
            'expected_updated_at' => ['required', 'string', 'max:64'],
            // N-02: explicit canvas context for the redirect. Verified as a
            // real placement below — never trusted blindly.
            'template_ulid' => ['nullable', 'string', 'size:26'],
        ]);

        try {
            $service->updateBlockConfig($section, $validated['config'], $actor, $validated['expected_updated_at']);
        } catch (ThemeValidationException $e) {
            // RA-04/N-04: a raw abort(409, message) is never guaranteed to
            // reach the operator — with APP_DEBUG=false in production,
            // Laravel renders its own generic conflict page and the message
            // never leaves the server. ValidationException::withMessages()
            // is the SAME repository-consistent, Inertia-native mechanism
            // every other rejection on this endpoint already uses (see the
            // non-stale branch below): it redirects back with a flashed
            // `errors` bag that Show.vue/BlockConfigPanel.vue already
            // render inline, works identically in every environment, and
            // requires no new error-page architecture.
            throw ValidationException::withMessages([
                'config' => $e->reason === 'stale_edit' ? $this->staleConflictMessage() : $e->getMessage(),
            ]);
        }

        return redirect()->route('page-builder.show', $this->resolveTemplateContext($section, $validated['template_ulid'] ?? null) ?? $section)->with('status', 'block-updated');
    }

    public function destroy(Request $request, ThemePolicy $policy, ThemeTemplate $template, ThemeSection $section, PageBuilderBlockService $service): RedirectResponse
    {
        $theme = $template->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate([
            // CODEX-CR001D-03: the Section token is always required; the
            // Component token is required only when the Section resolves to
            // exactly one Block (enforced service-side, since the controller
            // cannot cheaply know that without a query the service already
            // performs under lock).
            'expected_section_updated_at' => ['required', 'string', 'max:64'],
            'expected_component_updated_at' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $service->removeBlock($template, $section, $actor, $validated['expected_section_updated_at'], $validated['expected_component_updated_at'] ?? null);
        } catch (ThemeValidationException $e) {
            // RA-04/N-04 — see update() above for the full rationale.
            throw ValidationException::withMessages([
                'section' => $e->reason === 'stale_edit' ? $this->staleConflictMessage() : $e->getMessage(),
            ]);
        }

        return redirect()->route('page-builder.show', $template)->with('status', 'block-removed');
    }

    public function duplicate(Request $request, ThemePolicy $policy, ThemeTemplate $template, ThemeSection $section, PageBuilderBlockService $service): RedirectResponse
    {
        $theme = $template->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        try {
            $service->duplicateBlock($template, $section, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['section' => $e->getMessage()]);
        }

        return redirect()->route('page-builder.show', $template)->with('status', 'block-duplicated');
    }

    public function setVisibility(Request $request, ThemePolicy $policy, ThemeSection $section, PageBuilderBlockService $service): RedirectResponse
    {
        $theme = $section->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate([
            'visible' => ['required', 'boolean'],
            // CODEX-CR001D-03: mandatory — see update() above.
            'expected_updated_at' => ['required', 'string', 'max:64'],
            // N-02: explicit canvas context — see update() above.
            'template_ulid' => ['nullable', 'string', 'size:26'],
        ]);

        try {
            $service->setVisibility($section, $validated['visible'], $actor, $validated['expected_updated_at']);
        } catch (ThemeValidationException $e) {
            // RA-04/N-04 — see update() above for the full rationale.
            throw ValidationException::withMessages([
                'section' => $e->reason === 'stale_edit' ? $this->staleConflictMessage() : $e->getMessage(),
            ]);
        }

        return redirect()->route('page-builder.show', $this->resolveTemplateContext($section, $validated['template_ulid'] ?? null) ?? $section)->with('status', 'block-visibility-updated');
    }

    public function reorder(Request $request, ThemePolicy $policy, ThemeTemplate $template, ThemeSectionService $service): RedirectResponse
    {
        $theme = $template->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate([
            'ordered_section_ulids' => ['required', 'array', 'min:1'],
            'ordered_section_ulids.*' => ['required', 'string', 'size:26'],
        ]);

        $orderedIds = $template->sections()->whereIn('ulid', $validated['ordered_section_ulids'])
            ->pluck('theme_sections.id', 'ulid');

        try {
            if ($orderedIds->count() !== count($validated['ordered_section_ulids'])) {
                throw new ThemeValidationException('reorder_set_mismatch', 'The reorder request must name exactly the Sections currently placed in this Template.');
            }

            $service->reorder($template, collect($validated['ordered_section_ulids'])->map(fn (string $ulid): int => (int) $orderedIds[$ulid])->all(), $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['ordered_section_ulids' => $e->getMessage()]);
        }

        return redirect()->route('page-builder.show', $template)->with('status', 'blocks-reordered');
    }

    public function placeReusable(Request $request, ThemePolicy $policy, ThemeTemplate $template, PageBuilderBlockService $service): RedirectResponse
    {
        $theme = $template->theme;
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate(['section_ulid' => ['required', 'string', 'size:26']]);

        $section = ThemeSection::query()->where('ulid', $validated['section_ulid'])->firstOrFail();

        try {
            $service->placeReusable($template, $section, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['section_ulid' => $e->getMessage()]);
        }

        return redirect()->route('page-builder.show', $template)->with('status', 'block-placed');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }

    /**
     * N-04/RA-04 — operator-visible stale-conflict wording (Indonesian). No
     * silent retry, no overwrite: the message tells the operator to reload
     * and re-apply. Delivered via ValidationException::withMessages() (see
     * update()/destroy()/setVisibility()) rather than a raw abort(409, ...)
     * — a production abort message is not guaranteed to reach the browser
     * (Laravel renders its own generic error page when APP_DEBUG=false),
     * whereas a flashed validation error bag always does, in every
     * environment, through the exact same inline rendering every other
     * Page Builder rejection already uses.
     */
    private function staleConflictMessage(): string
    {
        return 'Konten telah berubah sejak halaman ini dibuka. Muat ulang untuk mengambil versi terbaru sebelum menyimpan perubahan.';
    }

    /**
     * N-02 — resolve the redirect canvas deterministically. A caller-supplied
     * template ULID is only honored when the Section is actually placed on
     * that Template (same-theme placement check); otherwise fall back to the
     * first current placement. Mutations themselves always act on the Section
     * row (canonical placement model) — this only fixes where the operator
     * lands afterwards, never which canvas is mutated.
     */
    private function resolveTemplateContext(ThemeSection $section, ?string $templateUlid): ?ThemeTemplate
    {
        if ($templateUlid !== null) {
            $placed = $section->templates()->where('theme_templates.ulid', $templateUlid)->first();

            if ($placed instanceof ThemeTemplate) {
                return $placed;
            }
        }

        return $section->templates()->first();
    }
}
