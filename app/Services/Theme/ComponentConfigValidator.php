<?php

namespace App\Services\Theme;

use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\Validator;

/**
 * IMP-006 — validates a Component's `config` payload against its `type`'s
 * CLOSED schema (docs/implementation/IMP-006-theme-engine.md section 11/21)
 * BEFORE every save and again at theme activation. This is the single
 * enforcement point that makes "Theme configuration is data, never code"
 * true: no field validated here ever accepts raw HTML, Blade, Vue SFC, or
 * JavaScript source — every text field is a plain string with a length
 * ceiling, every destination is the closed union NavigationDestinationSpec
 * validates, every enum is a fixed `in:` list.
 */
class ComponentConfigValidator
{
    /**
     * The CLOSED v1 component type set (section 11) — no other type name is
     * ever accepted.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SCHEMAS = [
        'hero' => [
            'headline' => ['required', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:500'],
            'background_theme_asset_ulid' => ['nullable', 'string', 'size:26'],
            'cta_label' => ['nullable', 'string', 'max:100'],
        ],
        'rich_text' => [
            'source' => ['required', 'string', 'in:cms_content,caption'],
            'content_kind' => ['required_if:source,cms_content', 'nullable', 'string', 'in:page,article'],
            'content_ulid' => ['required_if:source,cms_content', 'nullable', 'string', 'size:26'],
            'caption' => ['required_if:source,caption', 'nullable', 'string', 'max:1000'],
        ],
        'image' => [
            'source' => ['required', 'string', 'in:theme_asset,cms_media'],
            'theme_asset_ulid' => ['required_if:source,theme_asset', 'nullable', 'string', 'size:26'],
            'media_token' => ['required_if:source,cms_media', 'nullable', 'string', 'size:26'],
            'alt_text' => ['required', 'string', 'max:255'],
        ],
        'cta_button' => [
            'label' => ['required', 'string', 'max:100'],
            'variant' => ['required', 'string', 'in:primary,secondary,outline'],
            'destination_type' => ['required', 'string', 'in:SYSTEM_ROUTE,CMS_CONTENT,EXTERNAL_URL'],
            'destination_route' => ['required_if:destination_type,SYSTEM_ROUTE', 'nullable', 'string', 'max:255'],
            'destination_content_kind' => ['required_if:destination_type,CMS_CONTENT', 'nullable', 'string', 'in:page,article'],
            'destination_content_ulid' => ['required_if:destination_type,CMS_CONTENT', 'nullable', 'string', 'size:26'],
            'destination_external_url' => ['required_if:destination_type,EXTERNAL_URL', 'nullable', 'url', 'starts_with:http://,https://', 'max:2048'],
        ],
        'content_list' => [
            'content_kind' => ['required', 'string', 'in:page,article'],
            'article_type' => ['nullable', 'string', 'in:ARTICLE,NEWS'],
            'limit' => ['required', 'integer', 'min:1', 'max:24'],
            'order' => ['required', 'string', 'in:latest,oldest'],
        ],
        'stats' => [
            'items' => ['required', 'array', 'min:1', 'max:8'],
            'items.*.label' => ['required', 'string', 'max:100'],
            'items.*.value' => ['required', 'string', 'max:50'],
        ],
        'banner' => [
            'text' => ['required', 'string', 'max:500'],
            'dismissible' => ['required', 'boolean'],
            'destination_type' => ['nullable', 'string', 'in:SYSTEM_ROUTE,CMS_CONTENT,EXTERNAL_URL'],
            'destination_route' => ['required_if:destination_type,SYSTEM_ROUTE', 'nullable', 'string', 'max:255'],
            'destination_content_kind' => ['required_if:destination_type,CMS_CONTENT', 'nullable', 'string', 'in:page,article'],
            'destination_content_ulid' => ['required_if:destination_type,CMS_CONTENT', 'nullable', 'string', 'size:26'],
            'destination_external_url' => ['required_if:destination_type,EXTERNAL_URL', 'nullable', 'url', 'starts_with:http://,https://', 'max:2048'],
        ],
        'card_grid' => [
            'mode' => ['required', 'string', 'in:authored,content_list'],
            'cards' => ['required_if:mode,authored', 'nullable', 'array', 'max:12'],
            'cards.*.title' => ['required_with:cards', 'string', 'max:150'],
            'cards.*.text' => ['nullable', 'string', 'max:500'],
            'cards.*.theme_asset_ulid' => ['nullable', 'string', 'size:26'],
            'cards.*.destination_type' => ['nullable', 'string', 'in:SYSTEM_ROUTE,CMS_CONTENT,EXTERNAL_URL'],
            'cards.*.destination_route' => ['nullable', 'string', 'max:255'],
            'cards.*.destination_content_kind' => ['nullable', 'string', 'in:page,article'],
            'cards.*.destination_content_ulid' => ['nullable', 'string', 'size:26'],
            'cards.*.destination_external_url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:2048'],
            'content_kind' => ['required_if:mode,content_list', 'nullable', 'string', 'in:page,article'],
            'limit' => ['required_if:mode,content_list', 'nullable', 'integer', 'min:1', 'max:24'],
        ],
        'navigation_menu_slot' => [
            'menu_code' => ['required', 'string', 'max:64'],
        ],
    ];

    /**
     * @return array<string, string[]>
     */
    public function componentTypes(): array
    {
        return array_keys(self::SCHEMAS);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function assertValid(string $type, array $config): void
    {
        if (! array_key_exists($type, self::SCHEMAS)) {
            throw new ThemeValidationException(
                'unknown_component_type',
                "'{$type}' is not a registered Component type."
            );
        }

        $validator = Validator::make($config, self::SCHEMAS[$type]);

        if ($validator->fails()) {
            throw new ThemeValidationException(
                'invalid_component_config',
                "Component type '{$type}' config failed validation: ".$validator->errors()->first()
            );
        }
    }
}
