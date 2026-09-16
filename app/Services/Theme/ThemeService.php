<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IMP-006 — Theme identity CRUD (docs/implementation/IMP-006-theme-engine.md
 * section 8). Activation/deactivation is exclusively ThemeActivationService's
 * job (never this class) — mirrors PageService/PublicationService's own
 * write-boundary split. Authorization is a caller-side (HTTP boundary)
 * concern, matching every other service in this codebase.
 */
class ThemeService
{
    public function __construct(private readonly ThemeAuditLogger $auditLogger) {}

    /**
     * @param  array{name:string,slug?:string}  $payload
     */
    public function create(array $payload, Principal $actor): Theme
    {
        return DB::transaction(function () use ($payload, $actor) {
            $slug = $payload['slug'] ?? Str::slug($payload['name']);

            if ($slug === '') {
                throw new ThemeValidationException('invalid_slug', 'A theme slug must not be empty.');
            }

            if (Theme::where('slug', $slug)->exists()) {
                throw new ThemeValidationException('slug_taken', "Theme slug '{$slug}' is already in use.");
            }

            $theme = new Theme;
            $theme->forceFill([
                'name' => $payload['name'],
                'slug' => $slug,
                'status' => 'DRAFT',
                'is_system_default' => false,
                'created_by_principal_id' => $actor->id,
                'updated_by_principal_id' => $actor->id,
            ]);
            $theme->save();

            $this->auditLogger->recordThemeCreated($theme->id, [
                'name' => $theme->name,
                'slug' => $theme->slug,
            ], $actor);

            return $theme->fresh();
        });
    }

    /**
     * @param  array{name?:string}  $payload
     */
    public function update(Theme $theme, array $payload, Principal $actor): Theme
    {
        return DB::transaction(function () use ($theme, $payload, $actor) {
            $locked = Theme::query()->whereKey($theme->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_system_default) {
                throw new ThemeValidationException(
                    'system_default_immutable',
                    'The system default theme\'s identity cannot be edited.'
                );
            }

            $fieldsChanged = [];

            if (array_key_exists('name', $payload) && $payload['name'] !== $locked->name) {
                $locked->name = $payload['name'];
                $fieldsChanged[] = 'name';
            }

            $locked->updated_by_principal_id = $actor->id;
            $locked->save();

            $this->auditLogger->recordThemeUpdated($locked->id, [
                'fields_changed' => $fieldsChanged,
            ], $actor);

            return $locked;
        });
    }

    /**
     * Terminal transition (DRAFT|INACTIVE -> ARCHIVED). An ACTIVE theme must
     * be deactivated first (ThemeActivationService) — mirrors IMP-005's own
     * PUBLISHED-before-ARCHIVED discipline (section 18).
     */
    public function archive(Theme $theme, Principal $actor): Theme
    {
        return DB::transaction(function () use ($theme, $actor) {
            $locked = Theme::query()->whereKey($theme->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_system_default) {
                throw new ThemeValidationException(
                    'system_default_immutable',
                    'The system default theme can never be archived (section 8 fallback guarantee).'
                );
            }

            if (! in_array($locked->status, ['DRAFT', 'INACTIVE'], true)) {
                throw new ThemeValidationException(
                    'invalid_archive_state',
                    "Theme {$locked->id} is status={$locked->status}; only DRAFT or INACTIVE may be archived."
                );
            }

            $fromStatus = $locked->status;
            $locked->forceFill([
                'status' => 'ARCHIVED',
                'updated_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordThemeArchived($locked->id, [
                'from_status' => $fromStatus,
            ], $actor);

            return $locked;
        });
    }
}
