<?php

namespace Database\Seeders;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeActivation;
use App\Models\Theme\ThemeBrandingConfig;
use App\Models\Theme\ThemeTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * IMP-006 — seeds the code-shipped, never-deletable "System Default Theme"
 * (docs/implementation/IMP-006-theme-engine.md section 8 "Fallback
 * behavior"): a minimal, functional theme with a template per content kind
 * and default branding tokens, activated as the platform's active theme so
 * a fresh install renders something rather than nothing. Idempotent
 * (firstOrCreate-style) — safe to re-run.
 */
class ThemeSystemDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $theme = Theme::firstOrCreate(
            ['is_system_default' => true],
            [
                'ulid' => (string) Str::ulid(),
                'name' => 'System Default',
                'slug' => 'system-default',
                'status' => 'ACTIVE',
                'created_by_principal_id' => null,
                'updated_by_principal_id' => null,
            ]
        );

        foreach (['home', 'page', 'article'] as $kind) {
            ThemeTemplate::firstOrCreate(
                ['theme_id' => $theme->id, 'content_kind' => $kind],
                ['ulid' => (string) Str::ulid(), 'slug' => $kind, 'name' => ucfirst($kind)]
            );
        }

        ThemeBrandingConfig::firstOrCreate(
            ['theme_id' => $theme->id],
            [
                'color_tokens' => [
                    'primary' => '#1f2937',
                    'secondary' => '#4b5563',
                    'accent' => '#2563eb',
                    'neutral_bg' => '#fafafa',
                    'neutral_text' => '#171717',
                ],
                'font_family' => 'system',
            ]
        );

        $activation = ThemeActivation::query()->whereKey(1)->first();

        if ($activation !== null && $activation->active_theme_id === null) {
            $activation->update([
                'active_theme_id' => $theme->id,
                'assigned_at' => now(),
            ]);
        }
    }
}
