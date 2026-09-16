<?php

namespace App\Http\Controllers;

use App\Models\Campaign\Program;
use App\Services\Campaign\ProgramMediaTokenResolver;
use App\Services\Theme\PublicRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * IMP-007 — public Program page (docs/implementation/
 * IMP-007-campaign-program-fund.md sections 6/20/21). Consumes
 * PublicRenderer::activeTheme() (already public) and Theme::branding (an
 * ordinary public Eloquent relation) read-only — NO IMP-006 file is
 * modified for this. Own dedicated route, not funneled through IMP-005's
 * ContentResolverService catch-all.
 */
class PublicProgramController extends Controller
{
    public function show(Request $request, Program $program, PublicRenderer $renderer, ProgramMediaTokenResolver $tokenResolver)
    {
        abort_unless($program->status === 'PUBLISHED', 404);

        $theme = $renderer->activeTheme();
        $coverAsset = $program->mediaAssets()->where('status', 'ACTIVE')->oldest('id')->first();

        return Inertia::render('Public/ProgramShow', [
            // Explicit field allow-list, not the raw model — the internal
            // BIGINT id must never reach a public response.
            'program' => [
                'ulid' => $program->ulid,
                'name' => $program->name,
                'slug' => $program->slug,
                'summary' => $program->summary,
                'description_html' => $program->description_html,
                'cover_image_url' => $coverAsset !== null ? $tokenResolver->resolveUrl($coverAsset->ulid) : null,
            ],
            'branding' => $this->brandingPayload($theme),
        ]);
    }

    private function brandingPayload(mixed $theme): array
    {
        $branding = $theme?->branding;

        if ($branding === null) {
            return ['color_tokens' => [], 'font_family' => 'system'];
        }

        return [
            'color_tokens' => $branding->color_tokens,
            'font_family' => $branding->font_family,
        ];
    }
}
