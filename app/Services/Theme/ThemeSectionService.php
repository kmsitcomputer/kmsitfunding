<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-006 — Section CRUD + Template placement (docs/implementation/
 * IMP-006-theme-engine.md section 10/24). Placement/ordering always locks
 * the owning Template row FIRST (lockForUpdate) before writing the
 * theme_template_sections pivot, mirroring IMP-005's own
 * lock-before-write discipline for path claims.
 */
class ThemeSectionService
{
    public function __construct(private readonly ThemeAuditLogger $auditLogger) {}

    /**
     * Create a Section and place it into a Template at the next available
     * position, in one transaction.
     *
     * @param  array{is_reusable?:bool,layout_variant?:string}  $payload
     */
    public function createAndPlace(ThemeTemplate $template, array $payload, Principal $actor): ThemeSection
    {
        return DB::transaction(function () use ($template, $payload, $actor) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            $section = new ThemeSection;
            $section->forceFill([
                'theme_id' => $lockedTemplate->theme_id,
                'is_reusable' => $payload['is_reusable'] ?? false,
                'layout_variant' => $payload['layout_variant'] ?? 'default',
                'visible' => true,
            ]);
            $section->save();

            $nextPosition = (int) ($lockedTemplate->sections()->max('theme_template_sections.position') ?? 0) + 1;
            $lockedTemplate->sections()->attach($section->id, ['position' => $nextPosition, 'created_at' => now(), 'updated_at' => now()]);

            $this->auditLogger->recordSectionUpdated($section->id, [
                'theme_id' => $lockedTemplate->theme_id,
                'fields_changed' => ['created', 'placed'],
            ], $actor);

            return $section->fresh();
        });
    }

    /**
     * Place an EXISTING reusable Section into another Template — the
     * "referenced, never copied" placement (section 10).
     */
    public function placeReusable(ThemeTemplate $template, ThemeSection $section, Principal $actor): void
    {
        DB::transaction(function () use ($template, $section, $actor) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $lockedSection = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();

            if (! $lockedSection->is_reusable) {
                throw new ThemeValidationException('section_not_reusable', "Section {$lockedSection->id} is not flagged reusable.");
            }

            if ($lockedSection->theme_id !== $lockedTemplate->theme_id) {
                throw new ThemeValidationException('cross_theme_section', 'A Section may only be placed into a Template of the same Theme.');
            }

            $nextPosition = (int) ($lockedTemplate->sections()->max('theme_template_sections.position') ?? 0) + 1;
            $lockedTemplate->sections()->syncWithoutDetaching([
                $lockedSection->id => ['position' => $nextPosition, 'created_at' => now(), 'updated_at' => now()],
            ]);

            $this->auditLogger->recordSectionUpdated($lockedSection->id, [
                'theme_id' => $lockedTemplate->theme_id,
                'fields_changed' => ['placed'],
            ], $actor);
        });
    }

    /**
     * @param  array{visible?:bool,layout_variant?:string}  $payload
     */
    public function update(ThemeSection $section, array $payload, Principal $actor): ThemeSection
    {
        return DB::transaction(function () use ($section, $payload, $actor) {
            $locked = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $fieldsChanged = [];

            foreach (['visible', 'layout_variant'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== $locked->{$field}) {
                    $locked->{$field} = $payload[$field];
                    $fieldsChanged[] = $field;
                }
            }

            $locked->save();

            $this->auditLogger->recordSectionUpdated($locked->id, [
                'theme_id' => $locked->theme_id,
                'fields_changed' => $fieldsChanged,
            ], $actor);

            return $locked;
        });
    }

    /**
     * Reorder Sections within a Template. $orderedSectionIds is the FULL,
     * final ordered list of Section ids currently placed in this Template —
     * positions are reassigned 1..N under a single lock (section 24).
     *
     * @param  array<int, int>  $orderedSectionIds
     */
    public function reorder(ThemeTemplate $template, array $orderedSectionIds, Principal $actor): void
    {
        DB::transaction(function () use ($template, $orderedSectionIds, $actor) {
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            $currentIds = $lockedTemplate->sections()->pluck('theme_sections.id')->sort()->values()->all();
            $requestedIds = collect($orderedSectionIds)->sort()->values()->all();

            if ($currentIds !== $requestedIds) {
                throw new ThemeValidationException(
                    'reorder_set_mismatch',
                    'The reorder request must name exactly the Sections currently placed in this Template.'
                );
            }

            // Detach-then-reattach avoids the UNIQUE(theme_template_id, position)
            // constraint colliding with itself mid-reorder.
            $lockedTemplate->sections()->detach();

            foreach (array_values($orderedSectionIds) as $index => $sectionId) {
                $lockedTemplate->sections()->attach($sectionId, [
                    'position' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->auditLogger->recordSectionUpdated($lockedTemplate->id, [
                'theme_id' => $lockedTemplate->theme_id,
                'fields_changed' => ['reordered'],
            ], $actor);
        });
    }

    /**
     * Remove a Section's placement from a Template. If the Section is
     * reusable and still placed elsewhere, the Section row itself survives;
     * if this was its last placement and it is NOT reusable, the Section
     * row is deleted too (it can never exist un-placed).
     */
    public function removeFromTemplate(ThemeTemplate $template, ThemeSection $section, Principal $actor): void
    {
        DB::transaction(function () use ($template, $section, $actor) {
            $lockedSection = ThemeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $lockedTemplate = ThemeTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            $lockedTemplate->sections()->detach($lockedSection->id);

            $remainingPlacements = $lockedSection->templates()->count();

            if ($remainingPlacements === 0 && ! $lockedSection->is_reusable) {
                $lockedSection->delete();
            }

            $this->auditLogger->recordSectionUpdated($lockedSection->id, [
                'theme_id' => $lockedTemplate->theme_id,
                'fields_changed' => ['removed_from_template'],
            ], $actor);
        });
    }
}
