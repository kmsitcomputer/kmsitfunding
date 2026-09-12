<?php

namespace Tests\Feature\Identity;

use App\Enums\IdentityLifecycle;
use App\Enums\SecurityRestriction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        $user = User::create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        if ($overrides !== []) {
            $user->forceFill($overrides)->save();
        }

        return $user;
    }

    /**
     * Session-serialized error bags come back as a plain nested array (not a
     * ViewErrorBag instance) once JSON session serialization round-trips them,
     * so extract the first message for a field directly from that shape.
     */
    private function firstSessionError(TestResponse $response, string $field): string
    {
        $errors = $response->getSession()->get('errors');

        return $errors['default']['messages'][$field][0];
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $this->makeUser();

        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse-battery-staple']);

        $response->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password_generic_message(): void
    {
        $this->makeUser();

        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email_identical_generic_message(): void
    {
        $this->makeUser();

        $wrongPassword = $this->from('/login')->post('/login', ['email' => 'user@example.com', 'password' => 'wrong-password']);
        $unknownEmail = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $wrongPasswordMessage = $this->firstSessionError($wrongPassword, 'email');
        $unknownEmailMessage = $this->firstSessionError($unknownEmail, 'email');

        $this->assertSame($wrongPasswordMessage, $unknownEmailMessage);
    }

    public function test_disabled_account_cannot_authenticate(): void
    {
        $this->makeUser(['lifecycle_state' => IdentityLifecycle::Disabled]);

        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse-battery-staple']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_account_cannot_authenticate(): void
    {
        $this->makeUser(['security_restriction' => SecurityRestriction::Suspended]);

        $response = $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse-battery-staple']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_session_id_is_regenerated_on_success(): void
    {
        $this->makeUser();

        $this->withSession([]);
        $before = session()->getId();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse-battery-staple']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_logout_invalidates_session_and_logs_out(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }
}
