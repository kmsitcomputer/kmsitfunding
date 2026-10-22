<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Policies\ThemePolicy;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\Exceptions\ThemeActivationConflictException;
use App\Services\Theme\Exceptions\ThemeAssetValidationException;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\SiteDesignCloneService;
use App\Services\Theme\ThemeActivationService;
use App\Services\Theme\ThemeAssetService;
use App\Services\Theme\ThemeBrandingService;
use App\Services\Theme\ThemeNavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CR-001-C — operator-facing Site Design orchestration boundary
 * (docs/ai-handoff/CR-001/C-RECON.md §47.1). Thin wrapper over canonical
 * IMP-005/IMP-006 services. No business logic, no new lifecycle, no new
 * permission — delegates to ThemeService/ThemeActivationService/
 * ThemeBrandingService/ThemeNavigationService/ThemeAssetService via
 * SiteDesignCloneService. Existing ThemeController remains the
 * Advanced/Debug surface (HD-CR001-05).
 */
class SiteDesignController extends Controller
{
    public function index(Request $request, ThemePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        $themes = Theme::query()->latest('updated_at')->paginate(20);
        $canPublish = $policy->publish($actor, new Theme(['status' => 'DRAFT']));

        return Inertia::render('Admin/SiteDesign/Overview', [
            'themes' => $themes,
            'canPublish' => $canPublish,
        ]);
    }

    private function abortUnlessDraft(Theme $theme): void
    {
        abort_if($theme->status !== 'DRAFT', 409, 'Site Design editing targets DRAFT themes only — clone the active design first.');
    }

    public function showBrand(Request $request, ThemePolicy $policy, Theme $theme): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $theme), 403);

        $branding = $theme->branding;

        return Inertia::render('Admin/SiteDesign/Branding', [
            'theme' => $theme,
            'branding' => $branding,
            'assets' => $theme->assets()->where('status', 'ACTIVE')->get(),
            'logoAssetUlid' => $branding?->logoAsset?->ulid,
            'faviconAssetUlid' => $branding?->faviconAsset?->ulid,
        ]);
    }

    public function saveBrand(Request $request, ThemePolicy $policy, Theme $theme, ThemeBrandingService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

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

        return redirect()->route('site-design.branding.show', $theme)->with('status', 'branding-saved');
    }

    public function uploadLogo(Request $request, ThemePolicy $policy, Theme $theme, ThemeAssetService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->uploadAsset($actor), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate(['file' => ['required', 'file', 'max:10240']]);

        try {
            $service->upload($theme, $validated['file'], $actor);
        } catch (ThemeAssetValidationException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('site-design.branding.show', $theme)->with('status', 'asset-uploaded');
    }

    public function indexMenus(Request $request, ThemePolicy $policy, Theme $theme): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $theme), 403);

        return Inertia::render('Admin/SiteDesign/Menus', [
            'theme' => $theme,
            'menus' => $theme->navigationMenus()->with('items')->get(),
        ]);
    }

    public function showMenu(Request $request, ThemePolicy $policy, ThemeNavigationMenu $menu): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $menu->theme), 403);

        return Inertia::render('Admin/SiteDesign/Menus', [
            'theme' => $menu->theme,
            'menus' => $menu->theme->navigationMenus()->with('items')->get(),
            'activeMenu' => $menu->load('items'),
        ]);
    }

    public function saveMenu(Request $request, ThemePolicy $policy, Theme $theme, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $service->createMenu($theme, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        return redirect()->route('site-design.menus.index', $theme)->with('status', 'menu-created');
    }

    public function createNavigationItem(Request $request, ThemePolicy $policy, ThemeNavigationMenu $menu, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $menu->theme), 403);
        $this->abortUnlessDraft($menu->theme);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'destination_type' => ['required', 'string', 'in:SYSTEM_ROUTE,CMS_CONTENT,EXTERNAL_URL'],
            'destination_route' => ['nullable', 'string', 'max:255'],
            'destination_content_kind' => ['nullable', 'string', 'in:page,article'],
            'destination_content_ulid' => ['nullable', 'string', 'size:26'],
            'destination_external_url' => ['nullable', 'string', 'max:2048'],
            'parent_id' => ['nullable', 'integer'],
            'visible_desktop' => ['sometimes', 'boolean'],
            'visible_mobile' => ['sometimes', 'boolean'],
        ]);

        try {
            $service->createItem($menu, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['destination_type' => $e->getMessage()]);
        }

        return redirect()->route('site-design.menus.show', $menu)->with('status', 'navigation-item-created');
    }

    public function updateNavigationItem(Request $request, ThemePolicy $policy, ThemeNavigationItem $item, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $item->menu->theme), 403);
        $this->abortUnlessDraft($item->menu->theme);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'destination_type' => ['sometimes', 'string', 'in:SYSTEM_ROUTE,CMS_CONTENT,EXTERNAL_URL'],
            'destination_route' => ['nullable', 'string', 'max:255'],
            'destination_content_kind' => ['nullable', 'string', 'in:page,article'],
            'destination_content_ulid' => ['nullable', 'string', 'size:26'],
            'destination_external_url' => ['nullable', 'string', 'max:2048'],
            'parent_id' => ['nullable', 'integer'],
            'visible_desktop' => ['sometimes', 'boolean'],
            'visible_mobile' => ['sometimes', 'boolean'],
        ]);

        try {
            $service->updateItem($item, $validated, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['destination_type' => $e->getMessage()]);
        }

        return redirect()->route('site-design.menus.show', $item->menu)->with('status', 'navigation-item-updated');
    }

    public function deleteNavigationItem(Request $request, ThemePolicy $policy, ThemeNavigationItem $item, ThemeNavigationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        $menu = $item->menu;
        abort_unless($policy->update($actor, $menu->theme), 403);
        $this->abortUnlessDraft($menu->theme);

        $service->deleteItem($item, $actor);

        return redirect()->route('site-design.menus.show', $menu)->with('status', 'navigation-item-deleted');
    }

    public function cloneToDraft(Request $request, ThemePolicy $policy, Theme $theme, SiteDesignCloneService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewTheme($actor, $theme), 403);
        abort_unless($policy->create($actor), 403);
        abort_unless($policy->update($actor, $theme), 403);

        try {
            $draft = $service->cloneFromActive($theme, $actor);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['theme' => $e->getMessage()]);
        }

        return redirect()->route('site-design.branding.show', $draft)->with('status', 'draft-cloned');
    }

    public function preview(Request $request, ThemePolicy $policy, Theme $theme): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->preview($actor, $theme), 403);

        if ($theme->status !== 'DRAFT') {
            abort(404);
        }

        return Inertia::render('Admin/SiteDesign/Preview', [
            'theme' => $theme,
            'templates' => $theme->templates()->with('sections.components')->get(),
            'navigationMenus' => $theme->navigationMenus()->with('items')->get(),
            'branding' => $theme->branding,
            'canPublish' => $policy->publish($actor, $theme),
        ]);
    }

    public function publish(Request $request, ThemePolicy $policy, Theme $theme, ThemeActivationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $theme), 403);
        $this->abortUnlessDraft($theme);

        try {
            $service->activate($theme, $actor);
        } catch (ThemeActivationConflictException $e) {
            throw ValidationException::withMessages(['theme' => 'The active theme changed since you loaded this page — reload and try again.']);
        } catch (ThemeValidationException $e) {
            throw ValidationException::withMessages(['theme' => $e->getMessage()]);
        }

        return redirect()->route('site-design.index')->with('status', 'site-design-published');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
