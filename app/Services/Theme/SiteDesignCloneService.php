<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CR-001-C — copy-on-write aggregate clone (docs/ai-handoff/CR-001/
 * C-RECON.md §24.1A). Orchestrates the complete mutable presentation
 * aggregate clone from a source (ACTIVE) Theme into a NEW independent DRAFT
 * Theme using ONLY existing canonical Theme domain services — no new
 * business logic, no schema change, no PublicRenderer write.
 *
 * Source aggregate MUST remain unchanged. Clone becomes an independent DRAFT
 * aggregate. Activation remains exclusively ThemeActivationService's job.
 */
class SiteDesignCloneService
{
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly ThemeTemplateService $templateService,
        private readonly ThemeSectionService $sectionService,
        private readonly ThemeComponentService $componentService,
        private readonly ThemeNavigationService $navigationService,
        private readonly ThemeBrandingService $brandingService,
    ) {}

    public function cloneFromActive(Theme $source, Principal $actor): Theme
    {
        $source->refresh();

        if ($source->status !== 'ACTIVE') {
            throw new ThemeValidationException('source_not_active', "Theme {$source->id} is status={$source->status}; only an ACTIVE theme may be cloned into a draft.");
        }

        $compensationPaths = [];

        try {
            $draft = DB::transaction(function () use ($source, $actor, &$compensationPaths) {
                $draft = $this->themeService->create([
                    'name' => $source->name.' (Draft)',
                    'slug' => $source->slug.'-draft-'.strtolower((string) Str::ulid()),
                ], $actor);

                $assetMap = $this->cloneAssets($source, $draft, $actor, $compensationPaths);
                $this->cloneTemplates($source, $draft, $actor);
                $this->cloneNavigation($source, $draft, $actor);
                $this->cloneBranding($source, $draft, $actor, $assetMap);

                return $draft->fresh();
            });
        } catch (\Throwable $e) {
            $this->compensateFilesystem($compensationPaths);

            throw $e;
        }

        return $draft;
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $compensationPaths
     * @return array<int, int> old asset id → new asset id
     */
    private function cloneAssets(Theme $source, Theme $draft, Principal $actor, array &$compensationPaths): array
    {
        $map = [];
        $sourceAssets = ThemeAsset::query()->where('theme_id', $source->id)->where('status', 'ACTIVE')->get();

        foreach ($sourceAssets as $sourceAsset) {
            $disk = config('theme.disk');
            $sourcePath = 'theme/'.$sourceAsset->created_at->format('Y/m').'/'.$sourceAsset->stored_filename;

            if (! Storage::disk($disk)->exists($sourcePath)) {
                throw new ThemeValidationException(
                    'source_asset_missing',
                    "Source theme asset '{$sourceAsset->ulid}' physical object is missing and cannot be cloned."
                );
            }

            $newUlid = (string) Str::ulid();
            $newDestinationPath = 'theme/'.now()->format('Y/m').'/'.$newUlid.'.'.$sourceAsset->extension;

            $copyResult = Storage::disk($disk)->copy($sourcePath, $newDestinationPath);

            if ($copyResult === false) {
                $compensationPaths[] = ['disk' => $disk, 'path' => $newDestinationPath];

                throw new ThemeValidationException(
                    'asset_copy_failed',
                    "Physical copy of theme asset '{$sourceAsset->ulid}' failed."
                );
            }

            $compensationPaths[] = ['disk' => $disk, 'path' => $newDestinationPath];

            $cloned = new ThemeAsset;
            $cloned->forceFill([
                'ulid' => $newUlid,
                'theme_id' => $draft->id,
                'disk' => $disk,
                'stored_filename' => $newUlid.'.'.$sourceAsset->extension,
                'original_filename' => $sourceAsset->original_filename,
                'mime_type' => $sourceAsset->mime_type,
                'extension' => $sourceAsset->extension,
                'size_bytes' => $sourceAsset->size_bytes,
                'width' => $sourceAsset->width,
                'height' => $sourceAsset->height,
                'sha256' => $sourceAsset->sha256,
                'status' => 'ACTIVE',
                'uploaded_by_principal_id' => $actor->id,
            ]);
            $cloned->save();

            $map[$sourceAsset->id] = $cloned->id;
        }

        return $map;
    }

    private function cloneTemplates(Theme $source, Theme $draft, Principal $actor): void
    {
        $sourceTemplates = $source->templates()->with('sections.components')->get();
        $sectionMap = [];

        foreach ($sourceTemplates as $sourceTemplate) {
            $newTemplate = $this->templateService->create($draft, [
                'name' => $sourceTemplate->name,
                'content_kind' => $sourceTemplate->content_kind,
                'slug' => $sourceTemplate->slug.'-draft-'.strtolower(substr((string) Str::ulid(), 0, 8)),
            ], $actor);

            foreach ($sourceTemplate->sections as $sourceSection) {
                if (array_key_exists($sourceSection->id, $sectionMap)) {
                    $this->sectionService->placeReusable($newTemplate, $sectionMap[$sourceSection->id], $actor);

                    continue;
                }

                $newSection = $this->sectionService->createAndPlace($newTemplate, [
                    'is_reusable' => $sourceSection->is_reusable,
                    'layout_variant' => $sourceSection->layout_variant,
                ], $actor);

                if (! (bool) $sourceSection->visible) {
                    $newSection = $this->sectionService->update($newSection, ['visible' => false], $actor);
                }

                foreach ($sourceSection->components as $sourceComponent) {
                    $this->componentService->create(
                        $newSection,
                        $sourceComponent->type,
                        $sourceComponent->config ?? [],
                        $actor
                    );
                }

                $sectionMap[$sourceSection->id] = $newSection->fresh();
            }
        }
    }

    private function cloneNavigation(Theme $source, Theme $draft, Principal $actor): void
    {
        $sourceMenus = $source->navigationMenus()->with('items')->get();

        foreach ($sourceMenus as $sourceMenu) {
            $newMenu = $this->navigationService->createMenu($draft, [
                'code' => $sourceMenu->code,
                'name' => $sourceMenu->name,
            ], $actor);

            $itemMap = [];

            foreach ($sourceMenu->items->where('parent_id', null) as $rootItem) {
                $newRoot = $this->navigationService->createItem($newMenu, [
                    'label' => $rootItem->label,
                    'destination_type' => $rootItem->destination_type,
                    'destination_route' => $rootItem->destination_route,
                    'destination_content_kind' => $rootItem->destination_content_kind,
                    'destination_content_ulid' => $rootItem->destination_content_ulid,
                    'destination_external_url' => $rootItem->destination_external_url,
                    'parent_id' => null,
                    'visible_desktop' => (bool) $rootItem->visible_desktop,
                    'visible_mobile' => (bool) $rootItem->visible_mobile,
                ], $actor);

                $itemMap[$rootItem->id] = $newRoot->id;
            }

            foreach ($sourceMenu->items->whereNotNull('parent_id') as $childItem) {
                if (! array_key_exists($childItem->parent_id, $itemMap)) {
                    throw new ThemeValidationException(
                        'unresolved_navigation_parent',
                        "Navigation item '{$childItem->ulid}' references an unresolvable parent."
                    );
                }

                $this->navigationService->createItem($newMenu, [
                    'label' => $childItem->label,
                    'destination_type' => $childItem->destination_type,
                    'destination_route' => $childItem->destination_route,
                    'destination_content_kind' => $childItem->destination_content_kind,
                    'destination_content_ulid' => $childItem->destination_content_ulid,
                    'destination_external_url' => $childItem->destination_external_url,
                    'parent_id' => $itemMap[$childItem->parent_id],
                    'visible_desktop' => (bool) $childItem->visible_desktop,
                    'visible_mobile' => (bool) $childItem->visible_mobile,
                ], $actor);
            }
        }
    }

    /**
     * @param  array<int, int>  $assetMap
     */
    private function cloneBranding(Theme $source, Theme $draft, Principal $actor, array $assetMap): void
    {
        $sourceBranding = $source->branding;

        if ($sourceBranding === null) {
            return;
        }

        $logoUlid = null;
        $faviconUlid = null;

        if ($sourceBranding->logo_theme_asset_id !== null) {
            $newLogoId = $assetMap[$sourceBranding->logo_theme_asset_id] ?? null;

            if ($newLogoId === null) {
                throw new ThemeValidationException(
                    'branding_asset_unresolved',
                    'Source branding logo asset has no cloned counterpart.'
                );
            }

            $logoUlid = ThemeAsset::query()->whereKey($newLogoId)->value('ulid');
        }

        if ($sourceBranding->favicon_theme_asset_id !== null) {
            $newFaviconId = $assetMap[$sourceBranding->favicon_theme_asset_id] ?? null;

            if ($newFaviconId === null) {
                throw new ThemeValidationException(
                    'branding_asset_unresolved',
                    'Source branding favicon asset has no cloned counterpart.'
                );
            }

            $faviconUlid = ThemeAsset::query()->whereKey($newFaviconId)->value('ulid');
        }

        $this->brandingService->save($draft, [
            'color_tokens' => $sourceBranding->color_tokens,
            'font_family' => $sourceBranding->font_family,
            'logo_theme_asset_ulid' => $logoUlid,
            'favicon_theme_asset_ulid' => $faviconUlid,
        ], $actor);
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $paths
     */
    private function compensateFilesystem(array $paths): void
    {
        foreach ($paths as $entry) {
            try {
                Storage::disk($entry['disk'])->delete($entry['path']);
            } catch (\Throwable $e) {
                Log::warning('site-design-clone.compensation-failed', [
                    'disk' => $entry['disk'],
                    'path' => $entry['path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
