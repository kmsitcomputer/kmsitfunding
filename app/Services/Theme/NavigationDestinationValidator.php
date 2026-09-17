<?php

namespace App\Services\Theme;

use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\Route;

/**
 * IMP-006 — validates a destination at SAVE time (docs/implementation/
 * IMP-006-theme-engine.md section 14): the closed union SYSTEM_ROUTE |
 * CMS_CONTENT | EXTERNAL_URL, exactly one of the destination_* fields
 * populated per the declared type, SYSTEM_ROUTE must name a route that
 * actually exists, EXTERNAL_URL must pass the http(s)-only scheme
 * allow-list. Render-time resolution (does the route still exist, is the
 * CMS content still published) is NavigationDestinationResolver's job.
 */
class NavigationDestinationValidator
{
    private const TYPES = ['SYSTEM_ROUTE', 'CMS_CONTENT', 'EXTERNAL_URL'];

    /**
     * @param  array{destination_type:string,destination_route?:?string,destination_content_kind?:?string,destination_content_ulid?:?string,destination_external_url?:?string}  $destination
     */
    public function assertValid(array $destination): void
    {
        $type = $destination['destination_type'] ?? null;

        if (! in_array($type, self::TYPES, true)) {
            throw new ThemeValidationException('invalid_destination_type', "Destination type '{$type}' is not one of ".implode('|', self::TYPES).'.');
        }

        match ($type) {
            'SYSTEM_ROUTE' => $this->assertValidSystemRoute($destination['destination_route'] ?? null),
            'CMS_CONTENT' => $this->assertValidCmsContent($destination['destination_content_kind'] ?? null, $destination['destination_content_ulid'] ?? null),
            'EXTERNAL_URL' => $this->assertValidExternalUrl($destination['destination_external_url'] ?? null),
        };
    }

    private function assertValidSystemRoute(?string $routeName): void
    {
        if ($routeName === null || $routeName === '') {
            throw new ThemeValidationException('missing_destination_route', 'A SYSTEM_ROUTE destination requires a route name.');
        }

        if (! Route::has($routeName)) {
            throw new ThemeValidationException('unknown_system_route', "Route '{$routeName}' is not registered.");
        }
    }

    private function assertValidCmsContent(?string $contentKind, ?string $contentUlid): void
    {
        if (! in_array($contentKind, ['page', 'article'], true)) {
            throw new ThemeValidationException('invalid_content_kind', "Content kind '{$contentKind}' must be 'page' or 'article'.");
        }

        if ($contentUlid === null || strlen($contentUlid) !== 26) {
            throw new ThemeValidationException('invalid_content_ulid', 'A CMS_CONTENT destination requires a valid content ULID.');
        }
    }

    private function assertValidExternalUrl(?string $url): void
    {
        if ($url === null || ! preg_match('#^https?://#i', $url)) {
            throw new ThemeValidationException('unsafe_external_url_scheme', 'An EXTERNAL_URL destination must use http:// or https:// only.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ThemeValidationException('invalid_external_url', "'{$url}' is not a valid URL.");
        }
    }
}
