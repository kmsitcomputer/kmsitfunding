<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeActivation;
use App\Models\Theme\ThemeComponent;
use App\Services\Theme\Exceptions\ThemeActivationConflictException;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-006 — the ONLY writer of Theme activation state (docs/implementation/
 * IMP-006-theme-engine.md section 8/24 "Safe switching"). Locks the
 * singleton `theme_activation` pointer row FIRST, mirroring
 * App\Services\Content\PublicationService::setHomepage()'s exact pattern
 * against CmsHomepageAssignment.
 */
class ThemeActivationService
{
    public function __construct(
        private readonly ComponentConfigValidator $componentValidator,
        private readonly ThemeAuditLogger $auditLogger,
    ) {}

    public function activate(Theme $theme, Principal $actor, ?int $expectedActiveThemeId = null): Theme
    {
        return DB::transaction(function () use ($theme, $actor, $expectedActiveThemeId) {
            $pointer = ThemeActivation::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            if ($expectedActiveThemeId !== null && $pointer->active_theme_id !== $expectedActiveThemeId) {
                throw new ThemeActivationConflictException(
                    "Expected current active theme {$expectedActiveThemeId} but it is ".
                    ($pointer->active_theme_id ?? 'none').'.'
                );
            }

            $candidate = Theme::query()->whereKey($theme->id)->lockForUpdate()->firstOrFail();

            if ($candidate->status === 'ARCHIVED') {
                throw new ThemeValidationException(
                    'theme_archived',
                    "Theme {$candidate->id} is ARCHIVED and may not be activated."
                );
            }

            $this->assertInternallyConsistent($candidate);

            $previousThemeId = $pointer->active_theme_id;
            $previous = $previousThemeId !== null
                ? Theme::query()->whereKey($previousThemeId)->lockForUpdate()->first()
                : null;

            if ($previous !== null && $previous->id !== $candidate->id) {
                $previous->forceFill(['status' => 'INACTIVE'])->save();
                $this->auditLogger->recordThemeDeactivated($previous->id, [
                    'replaced_by_theme_id' => $candidate->id,
                ], $actor);
            }

            $candidate->forceFill([
                'status' => 'ACTIVE',
                'updated_by_principal_id' => $actor->id,
            ])->save();

            $pointer->forceFill([
                'active_theme_id' => $candidate->id,
                'assigned_by_principal_id' => $actor->id,
                'assigned_at' => now(),
            ])->save();

            $this->auditLogger->recordThemeActivated($candidate->id, [
                'previous_theme_id' => $previousThemeId,
            ], $actor);

            return $candidate->fresh();
        });
    }

    /**
     * Section 8 "the theme's Templates/Sections/Components/Navigation/
     * Branding are validated as internally consistent... BEFORE activation
     * — activation of an invalid theme is rejected, never partially
     * applied." Re-validates every Component's config against its type's
     * CURRENT schema (not merely trusting the value it was saved with,
     * which may predate a schema tightening).
     */
    private function assertInternallyConsistent(Theme $theme): void
    {
        $componentIds = ThemeComponent::query()
            ->whereIn('theme_section_id', $theme->sections()->pluck('id'))
            ->get(['id', 'type', 'config']);

        foreach ($componentIds as $component) {
            $this->componentValidator->assertValid($component->type, $component->config ?? []);
        }
    }
}
