<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * IMP-006 — the root route now renders the public Theme Engine pipeline
     * (docs/implementation/IMP-006-theme-engine.md section 13), not the
     * retired static Foundation.vue placeholder. With no homepage
     * designated, HomepageContentResolver resolves null and the page
     * renders with an empty content payload — never a fatal error.
     */
    public function test_root_route_renders_the_public_theme_pipeline(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Public/ThemeRender'));
    }

    /**
     * The framework health/readiness endpoint responds without exposing sensitive details.
     */
    public function test_health_endpoint_responds_successfully(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
        $response->assertDontSee(config('app.key'));
    }
}
