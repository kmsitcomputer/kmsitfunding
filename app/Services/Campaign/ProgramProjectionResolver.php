<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Program;

/**
 * IMP-007 — the canonical public read projection for Program, mirroring
 * CampaignProjectionResolver exactly. Added under the targeted, additive
 * IMP-006 Theme content-projection amendment (Human change control) so the
 * locked Theme Engine's content_list/card_grid components can consume
 * Program data WITHOUT IMP-006 ever querying the `programs` table
 * directly or Program business truth ever being copied into Theme
 * tables/JSON. Read-only.
 */
final class ProgramProjectionResolver
{
    /**
     * @return array<int, array{ulid:string,title:string,summary:?string,image_url:?string,url:string,metadata:array<string,mixed>}>
     */
    public function latestPublished(int $limit = 6): array
    {
        return Program::query()
            ->where('status', 'PUBLISHED')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Program $program) => [
                'ulid' => $program->ulid,
                'title' => $program->name,
                'summary' => $program->summary,
                'image_url' => $this->coverImageUrl($program),
                'url' => route('public.programs.show', $program),
                'metadata' => [],
            ])
            ->all();
    }

    private function coverImageUrl(Program $program): ?string
    {
        $asset = $program->mediaAssets()->where('status', 'ACTIVE')->oldest('id')->first();

        return $asset !== null ? app(ProgramMediaTokenResolver::class)->resolveUrl($asset->ulid) : null;
    }
}
