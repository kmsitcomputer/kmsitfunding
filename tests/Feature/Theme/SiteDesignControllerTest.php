<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-C — SiteDesignController authorization + orchestration coverage
 * (C-RECON §34, §49.3). Proves: guest redirect, unauthorized 403, preview
 * gate, publish delegation, branding delegation, clone orchestration.
 */
class SiteDesignControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'Site Design '.$status.' '.uniqid()], $actor);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    public function test_guest_cannot_access_site_design_index(): void
    {
        $this->get('/admin/site-design')->assertRedirect('/login');
    }

    public function test_unauthorized_actor_is_forbidden_from_site_design_index(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)->get('/admin/site-design')->assertForbidden();
    }

    public function test_authorized_actor_can_view_site_design_index(): void
    {
        $actor = $this->makeAuthorizedActor();

        $this->actingAs($actor->humanUser)->get('/admin/site-design')->assertOk();
    }

    public function test_unauthorized_actor_cannot_preview_draft(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertForbidden();
    }

    public function test_authorized_actor_can_preview_draft(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertOk();
    }

    public function test_preview_rejects_non_draft_theme(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme('ACTIVE');

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertNotFound();
    }

    public function test_unauthorized_actor_cannot_publish(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertForbidden();
        $this->assertSame('DRAFT', $theme->fresh()->status);
    }

    public function test_authorized_publish_delegates_to_activation_service(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertRedirect('/admin/site-design');

        $this->assertSame('ACTIVE', $theme->fresh()->status);
    }

    public function test_unauthorized_actor_cannot_clone_to_draft(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme('ACTIVE');

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/clone")
            ->assertForbidden();
    }

    public function test_authorized_clone_creates_draft_without_mutating_source(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme('ACTIVE');

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/clone")
            ->assertRedirect();

        $this->assertSame('ACTIVE', $theme->fresh()->status);
        $this->assertSame(1, Theme::where('status', 'DRAFT')->where('id', '!=', $theme->id)->count());
    }

    public function test_unauthorized_actor_cannot_save_branding(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", [
                'color_tokens' => [
                    'primary' => '#111111',
                    'secondary' => '#222222',
                    'accent' => '#333333',
                    'neutral_bg' => '#ffffff',
                    'neutral_text' => '#000000',
                ],
            ])
            ->assertForbidden();
    }

    public function test_authorized_branding_save_delegates_to_branding_service(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", [
                'color_tokens' => [
                    'primary' => '#111111',
                    'secondary' => '#222222',
                    'accent' => '#333333',
                    'neutral_bg' => '#ffffff',
                    'neutral_text' => '#000000',
                ],
                'font_family' => 'system',
            ])
            ->assertRedirect();

        $this->assertNotNull($theme->fresh()->branding);
        $this->assertSame('#111111', $theme->fresh()->branding->color_tokens['primary']);
    }

    public function test_unauthorized_actor_cannot_view_menus(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/menus")
            ->assertForbidden();
    }

    public function test_authorized_actor_can_view_menus(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/menus")
            ->assertOk();
    }
}
