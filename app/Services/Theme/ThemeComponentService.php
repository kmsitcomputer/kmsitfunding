<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeSection;
use App\Services\Content\ContentSanitizer;
use App\Services\Content\Exceptions\ContentSanitizationException;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * IMP-006 — Component CRUD within a Section (docs/implementation/
 * IMP-006-theme-engine.md section 11/24). Every config write passes through
 * ComponentConfigValidator BEFORE persistence — never persisted
 * unvalidated (section 21). ADR-004 custom_html additionally passes through
 * ContentSanitizer here, for every caller including Advanced/Debug.
 */
class ThemeComponentService
{
    public function __construct(
        private readonly ComponentConfigValidator $configValidator,
        private readonly ThemeAuditLogger $auditLogger,
        private readonly ContentSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function create(ThemeSection $section, string $type, array $config, Principal $actor): ThemeComponent
    {
        return DB::transaction(function () use ($section, $type, $config, $actor) {
            $this->configValidator->assertValid($type, $config);
            $config = $this->sanitizeCustomHtml($type, $config);

            $lockedSection = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $nextPosition = (int) ($lockedSection->components()->max('position') ?? 0) + 1;

            $component = new ThemeComponent;
            $component->forceFill([
                'theme_section_id' => $lockedSection->id,
                'type' => $type,
                'config' => $config,
                'position' => $nextPosition,
            ]);
            $component->save();

            $this->auditLogger->recordComponentUpdated($component->id, [
                'theme_section_id' => $lockedSection->id,
                'type' => $type,
                'fields_changed' => ['created'],
            ], $actor);

            return $component;
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function update(ThemeComponent $component, array $config, Principal $actor): ThemeComponent
    {
        return DB::transaction(function () use ($component, $config, $actor) {
            $locked = ThemeComponent::query()->whereKey($component->id)->lockForUpdate()->firstOrFail();

            $this->configValidator->assertValid($locked->type, $config);
            $config = $this->sanitizeCustomHtml($locked->type, $config);

            $locked->config = $config;
            $locked->save();

            $this->auditLogger->recordComponentUpdated($locked->id, [
                'theme_section_id' => $locked->theme_section_id,
                'type' => $locked->type,
                'fields_changed' => ['config'],
            ], $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function sanitizeCustomHtml(string $type, array $config): array
    {
        if ($type !== 'rich_text' || ($config['source'] ?? null) !== 'custom_html') {
            return $config;
        }

        try {
            $config['body_html'] = $this->sanitizer->sanitize($config['body_html']);
        } catch (ContentSanitizationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        }

        // Sanitization can remove all content (e.g. a comment-only body).
        // The stored config must still satisfy required_if:source,custom_html.
        $this->configValidator->assertValid($type, $config);

        return $config;
    }

    public function delete(ThemeComponent $component, Principal $actor): void
    {
        DB::transaction(function () use ($component, $actor) {
            $locked = ThemeComponent::query()->whereKey($component->id)->lockForUpdate()->firstOrFail();
            $sectionId = $locked->theme_section_id;
            $type = $locked->type;
            $locked->delete();

            $this->auditLogger->recordComponentUpdated($component->id, [
                'theme_section_id' => $sectionId,
                'type' => $type,
                'fields_changed' => ['deleted'],
            ], $actor);
        });
    }

    /**
     * @param  array<int, int>  $orderedComponentIds
     */
    public function reorder(ThemeSection $section, array $orderedComponentIds, Principal $actor): void
    {
        DB::transaction(function () use ($section, $orderedComponentIds, $actor) {
            $lockedSection = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();

            $currentIds = $lockedSection->components()->pluck('id')->sort()->values()->all();
            $requestedIds = collect($orderedComponentIds)->sort()->values()->all();

            if ($currentIds !== $requestedIds) {
                throw new ThemeValidationException(
                    'reorder_set_mismatch',
                    'The reorder request must name exactly the Components currently in this Section.'
                );
            }

            // Push every position out of the unique range first, then
            // reassign 1..N, avoiding a self-collision on the
            // UNIQUE(theme_section_id, position) constraint mid-reorder.
            ThemeComponent::query()->whereIn('id', $orderedComponentIds)
                ->update(['position' => DB::raw('position + 1000000')]);

            foreach (array_values($orderedComponentIds) as $index => $componentId) {
                ThemeComponent::query()->whereKey($componentId)->update(['position' => $index + 1]);
            }

            $this->auditLogger->recordComponentUpdated($lockedSection->id, [
                'theme_section_id' => $lockedSection->id,
                'type' => 'n/a',
                'fields_changed' => ['reordered'],
            ], $actor);
        });
    }
}
