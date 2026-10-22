<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Policies\ThemePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-B (Section 30, B-RECON §11.2) — proves ThemePolicy::preview()
 * grants an authorized principal and denies both an unauthorized principal
 * and a guest (no Principal at all is never a valid call — the policy
 * always requires an authenticated acting Principal, matching every other
 * ThemePolicy method's shape).
 */
class ThemePolicyPreviewTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        return Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Draft Theme',
            'slug' => 'draft-theme-'.uniqid(),
            'status' => $status,
            'is_system_default' => false,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    public function test_authorized_principal_can_preview_a_draft_theme(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->assertTrue((new ThemePolicy)->preview($authorized, $theme));
    }

    public function test_unauthorized_principal_cannot_preview_a_draft_theme(): void
    {
        $unauthorized = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $this->assertFalse((new ThemePolicy)->preview($unauthorized, $theme));
    }
}
