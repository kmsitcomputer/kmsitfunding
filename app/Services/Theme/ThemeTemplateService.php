<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IMP-006 — Template CRUD (docs/implementation/IMP-006-theme-engine.md
 * section 9). UNIQUE(theme_id, content_kind) IS the content-kind assignment
 * — enforced here as a pre-check for a clean 422, and by the DB unique
 * index as the real guarantee.
 */
class ThemeTemplateService
{
    private const CONTENT_KINDS = ['home', 'page', 'article'];

    public function __construct(private readonly ThemeAuditLogger $auditLogger) {}

    /**
     * @param  array{name:string,content_kind:string,slug?:string}  $payload
     */
    public function create(Theme $theme, array $payload, Principal $actor): ThemeTemplate
    {
        return DB::transaction(function () use ($theme, $payload, $actor) {
            if (! in_array($payload['content_kind'], self::CONTENT_KINDS, true)) {
                throw new ThemeValidationException('invalid_content_kind', 'content_kind must be one of '.implode('|', self::CONTENT_KINDS).'.');
            }

            $slug = $payload['slug'] ?? Str::slug($payload['name']);

            if (ThemeTemplate::where('theme_id', $theme->id)->where('content_kind', $payload['content_kind'])->exists()) {
                throw new ThemeValidationException(
                    'content_kind_already_assigned',
                    "Theme {$theme->id} already has a template assigned to content kind '{$payload['content_kind']}'."
                );
            }

            if (ThemeTemplate::where('theme_id', $theme->id)->where('slug', $slug)->exists()) {
                throw new ThemeValidationException('slug_taken', "Template slug '{$slug}' is already in use in this theme.");
            }

            $template = new ThemeTemplate;
            $template->forceFill([
                'theme_id' => $theme->id,
                'slug' => $slug,
                'name' => $payload['name'],
                'content_kind' => $payload['content_kind'],
            ]);
            $template->save();

            $this->auditLogger->recordTemplateUpdated($template->id, [
                'theme_id' => $theme->id,
                'fields_changed' => ['created'],
            ], $actor);

            return $template;
        });
    }

    /**
     * @param  array{name?:string}  $payload
     */
    public function update(ThemeTemplate $template, array $payload, Principal $actor): ThemeTemplate
    {
        return DB::transaction(function () use ($template, $payload, $actor) {
            $locked = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $fieldsChanged = [];

            if (array_key_exists('name', $payload) && $payload['name'] !== $locked->name) {
                $locked->name = $payload['name'];
                $fieldsChanged[] = 'name';
            }

            $locked->save();

            $this->auditLogger->recordTemplateUpdated($locked->id, [
                'theme_id' => $locked->theme_id,
                'fields_changed' => $fieldsChanged,
            ], $actor);

            return $locked;
        });
    }
}
