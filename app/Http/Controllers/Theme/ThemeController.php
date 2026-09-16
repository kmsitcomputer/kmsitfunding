<?php

namespace App\Http\Controllers\Theme;

use App\Http\Controllers\Controller;
use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Policies\ThemePolicy;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\Exceptions\ThemeActivationConflictException;
use App\Services\Theme\Exceptions\ThemeAssetValidationException;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\ThemeActivationService;
use App\Services\Theme\ThemeAssetService;
use App\Services\Theme\ThemeBrandingService;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeNavigationService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-006 admin UI — thin controller mirroring the IMP-005 Cms/*Controller
 * pattern exactly: resolve the acting Principal, authorize via ThemePolicy,
 * delegate to the Theme services, surface their exceptions as
 * validation-style errors. No business logic lives here.
 */
class ThemeController extends Controller
{
    public function index(Request $request, ThemePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        return Inertia::render('Theme/Index', [
            'themes' => Theme::query()->latest('updated_at')->paginate(20),
        ]);
    }

    public function store(Request $request, ThemePolicy $policy, ThemeService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $theme = $service->create($validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'theme-created');
    }

    public function show(Request $request, ThemePolicy $policy, Theme $theme): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $theme), 403);

        return Inertia::render('Theme/Show', [
            'theme' => $theme,
            'templates' => $theme->templates()->with('sections.components')->get(),
            'navigationMenus' => $theme->navigationMenus()->with('items')->get(),
            'branding' => $theme->branding,
            'assets' => $theme->assets()->where('status', 'ACTIVE')->get(),
        ]);
    }

    public function update(Request $request, ThemePolicy $policy, Theme $theme, ThemeService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);

        $validated = $request->validate(['name' => ['sometimes', 'string', 'max:255']]);

        try {
            $service->update($theme, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'theme-updated');
    }

    public function activate(Request $request, ThemePolicy $policy, Theme $theme, ThemeActivationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $theme), 403);

        $expected = $request->input('expected_active_theme_ulid');
        $expectedId = $expected !== null ? Theme::where('ulid', $expected)->value('id') : null;

        try {
            $service->activate($theme, $actor, $expectedId);
        } catch (ThemeActivationConflictException $e) {
            throw ValidationException::withMessages(['theme' => 'The active theme changed since you loaded this page — reload and try again.']);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['theme' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'theme-activated');
    }

    public function archive(Request $request, ThemePolicy $policy, Theme $theme, ThemeService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archive($actor, $theme), 403);

        try {
            $service->archive($theme, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['theme' => $e->getMessage()]);
        }

        return redirect()->route('theme.index')->with('status', 'theme-archived');
    }

    public function storeTemplate(Request $request, ThemePolicy $policy, Theme $theme, ThemeTemplateService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content_kind' => ['required', 'string', 'in:home,page,article'],
            'slug' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $service->create($theme, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['content_kind' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'template-created');
    }

    public function createSection(Request $request, ThemePolicy $policy, ThemeTemplate $template, ThemeSectionService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $template->theme), 403);

        $validated = $request->validate([
            'is_reusable' => ['sometimes', 'boolean'],
            'layout_variant' => ['sometimes', 'string', 'max:64'],
        ]);

        $service->createAndPlace($template, $validated, $actor);

        return redirect()->route('theme.show', $template->theme)->with('status', 'section-created');
    }

    public function reorderSections(Request $request, ThemePolicy $policy, ThemeTemplate $template, ThemeSectionService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $template->theme), 403);

        $validated = $request->validate(['section_ids' => ['required', 'array']]);

        try {
            $service->reorder($template, $validated['section_ids'], $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['section_ids' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $template->theme)->with('status', 'sections-reordered');
    }

    public function createComponent(Request $request, ThemePolicy $policy, ThemeSection $section, ThemeComponentService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $section->theme), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:32'],
            'config' => ['required', 'array'],
        ]);

        try {
            $service->create($section, $validated['type'], $validated['config'], $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['config' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $section->theme)->with('status', 'component-created');
    }

    public function updateComponent(Request $request, ThemePolicy $policy, ThemeComponent $component, ThemeComponentService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $component->section->theme), 403);

        $validated = $request->validate(['config' => ['required', 'array']]);

        try {
            $service->update($component, $validated['config'], $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['config' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $component->section->theme)->with('status', 'component-updated');
    }

    public function deleteComponent(Request $request, ThemePolicy $policy, ThemeComponent $component, ThemeComponentService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        $theme = $component->section->theme;
        abort_unless($policy->update($actor, $theme), 403);

        $service->delete($component, $actor);

        return redirect()->route('theme.show', $theme)->with('status', 'component-deleted');
    }

    public function createMenu(Request $request, ThemePolicy $policy, Theme $theme, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $service->createMenu($theme, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'menu-created');
    }

    public function createNavigationItem(Request $request, ThemePolicy $policy, ThemeNavigationMenu $menu, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $menu->theme), 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'destination_type' => ['required', 'string', 'in:SYSTEM_ROUTE,CMS_CONTENT,EXTERNAL_URL'],
            'destination_route' => ['nullable', 'string', 'max:255'],
            'destination_content_kind' => ['nullable', 'string', 'in:page,article'],
            'destination_content_ulid' => ['nullable', 'string', 'size:26'],
            'destination_external_url' => ['nullable', 'string', 'max:2048'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        try {
            $service->createItem($menu, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['destination_type' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $menu->theme)->with('status', 'navigation-item-created');
    }

    public function deleteNavigationItem(Request $request, ThemePolicy $policy, ThemeNavigationItem $item, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        $theme = $item->menu->theme;
        abort_unless($policy->update($actor, $theme), 403);

        $service->deleteItem($item, $actor);

        return redirect()->route('theme.show', $theme)->with('status', 'navigation-item-deleted');
    }

    public function saveBranding(Request $request, ThemePolicy $policy, Theme $theme, ThemeBrandingService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);

        $validated = $request->validate([
            'color_tokens' => ['required', 'array'],
            'font_family' => ['nullable', 'string', 'max:100'],
            'logo_theme_asset_ulid' => ['nullable', 'string', 'size:26'],
            'favicon_theme_asset_ulid' => ['nullable', 'string', 'size:26'],
        ]);

        try {
            $service->save($theme, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['color_tokens' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'branding-saved');
    }

    public function uploadAsset(Request $request, ThemePolicy $policy, Theme $theme, ThemeAssetService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->uploadAsset($actor), 403);

        $validated = $request->validate(['file' => ['required', 'file', 'max:10240']]);

        try {
            $service->upload($theme, $validated['file'], $actor);
        } catch (ThemeAssetValidationException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('theme.show', $theme)->with('status', 'asset-uploaded');
    }

    public function archiveAsset(Request $request, ThemePolicy $policy, ThemeAsset $asset, ThemeAssetService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archiveAsset($actor, $asset), 403);

        $service->archive($asset, $actor);

        return redirect()->route('theme.show', $asset->theme)->with('status', 'asset-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
