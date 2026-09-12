<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Services\Identity\AssuranceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_establishes_standard_only(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password12345']);

        $this->assertFalse(app(AssuranceService::class)->isElevated());
    }

    public function test_password_confirmation_establishes_elevated(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $this->actingAs($user)->post('/account/confirm-password', ['password' => 'password12345']);

        $this->assertTrue(app(AssuranceService::class)->isElevated());
    }

    public function test_elevated_falls_back_to_standard_after_ttl(): void
    {
        $assurance = app(AssuranceService::class);
        $assurance->elevate();

        $this->assertTrue($assurance->isElevated());

        $this->travel((int) config('identity.elevated_assurance_ttl_minutes') + 1)->minutes();

        $this->assertFalse($assurance->isElevated());
    }

    public function test_invalidate_clears_elevated_immediately(): void
    {
        $assurance = app(AssuranceService::class);
        $assurance->elevate();
        $this->assertTrue($assurance->isElevated());

        $assurance->invalidate();

        $this->assertFalse($assurance->isElevated());
    }

    public function test_logout_invalidates_elevated_assurance(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $this->actingAs($user)->post('/account/confirm-password', ['password' => 'password12345']);
        $this->assertTrue(app(AssuranceService::class)->isElevated());

        $this->post('/logout');

        $this->assertFalse(app(AssuranceService::class)->isElevated());
    }
}
