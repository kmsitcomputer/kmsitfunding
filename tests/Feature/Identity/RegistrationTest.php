<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Services\Identity\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_can_self_register_via_http(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'email' => 'Donor@Example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'donor@example.com']);
    }

    public function test_registration_creates_identity_only_no_business_fields(): void
    {
        $service = app(RegistrationService::class);
        $user = $service->register('fundraiser@example.com', 'correct-horse-battery-staple');

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
        $this->assertFalse($user->mfa_enabled);
        $this->assertSame('active', $user->lifecycle_state->value);
        $this->assertSame('none', $user->security_restriction->value);
        $this->assertNull($user->email_verified_at);
    }

    public function test_duplicate_normalized_email_is_rejected(): void
    {
        app(RegistrationService::class)->register('duplicate@example.com', 'correct-horse-battery-staple');

        $response = $this->post('/register', [
            'email' => 'Duplicate@Example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'duplicate@example.com')->count());
    }

    public function test_email_is_normalized_before_storage(): void
    {
        $user = app(RegistrationService::class)->register('  Mixed.Case@Example.COM  ', 'correct-horse-battery-staple');

        $this->assertSame('mixed.case@example.com', $user->email);
    }

    /**
     * IMP002-IMPL-M03 — self-registration must be rate-limited. Pre-fill the
     * exact bucket the controller itself keys on (normalized email + IP)
     * rather than relying on FormRequest's own duplicate-email rejection
     * (which would short-circuit before the limiter is ever reached), so this
     * proves the limiter wiring itself, not just uniqueness validation.
     */
    public function test_registration_is_rate_limited(): void
    {
        $limit = (int) config('identity.rate_limits.registration');
        $key = 'registration:brand-new@example.com|127.0.0.1';

        for ($i = 0; $i < $limit; $i++) {
            RateLimiter::hit($key, 60);
        }

        $response = $this->post('/register', [
            'email' => 'brand-new@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'brand-new@example.com']);
    }
}
