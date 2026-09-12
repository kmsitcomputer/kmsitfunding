<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Services\Identity\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
}
