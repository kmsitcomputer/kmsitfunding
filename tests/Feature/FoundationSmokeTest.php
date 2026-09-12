<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    /**
     * The application boots and the root route renders the foundation Inertia page.
     */
    public function test_root_route_renders_foundation_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Foundation'));
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
