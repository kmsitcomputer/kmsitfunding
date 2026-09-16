<?php

namespace App\Services\Theme;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use Illuminate\Support\Facades\Route;

/**
 * IMP-006 — resolves a stored destination to a real, current public URL at
 * RENDER time (docs/implementation/IMP-006-theme-engine.md section 14).
 * Read-only, never mutates. A CMS_CONTENT destination whose content is not
 * currently PUBLISHED resolves to null (section 14: "resolves to
 * 'unavailable' and is hidden from the rendered menu... never a bypass of
 * IMP-005's own PUBLISHED-only resolution").
 */
class NavigationDestinationResolver
{
    /**
     * @param  array{destination_type:string,destination_route?:?string,destination_content_kind?:?string,destination_content_ulid?:?string,destination_external_url?:?string}  $destination
     */
    public function resolve(array $destination): ?string
    {
        return match ($destination['destination_type'] ?? null) {
            'SYSTEM_ROUTE' => $this->resolveSystemRoute($destination['destination_route'] ?? null),
            'CMS_CONTENT' => $this->resolveCmsContent($destination['destination_content_kind'] ?? null, $destination['destination_content_ulid'] ?? null),
            'EXTERNAL_URL' => $destination['destination_external_url'] ?? null,
            default => null,
        };
    }

    private function resolveSystemRoute(?string $routeName): ?string
    {
        if ($routeName === null || ! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName);
        } catch (\Throwable) {
            // A route requiring bound parameters this navigation item never
            // supplies is not a valid navigation destination — degrade to
            // hidden rather than a fatal error (section 22).
            return null;
        }
    }

    private function resolveCmsContent(?string $contentKind, ?string $contentUlid): ?string
    {
        if ($contentUlid === null) {
            return null;
        }

        $owner = match ($contentKind) {
            'page' => CmsPage::where('ulid', $contentUlid)->first(),
            'article' => CmsArticle::where('ulid', $contentUlid)->first(),
            default => null,
        };

        if ($owner === null || $owner->status !== 'PUBLISHED') {
            return null;
        }

        $ownerColumn = $owner instanceof CmsPage ? 'page_id' : 'article_id';

        // CmsPath::path is already normalized with a leading slash
        // (App\Services\Content\PathService::normalize()).
        return CmsPath::query()
            ->where($ownerColumn, $owner->id)
            ->where('purpose', 'CURRENT')
            ->where('status', 'ACTIVE')
            ->value('path');
    }
}
