<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Services\Identity\SessionInvalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_request_returns_generic_response_regardless_of_existence(): void
    {
        Notification::fake();

        $existing = $this->post('/forgot-password', ['email' => 'user@example.com']);
        $nonExisting = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $existing->assertSessionHas('status');
        $nonExisting->assertSessionHas('status');
        $this->assertSame(
            $existing->getSession()->get('status'),
            $nonExisting->getSession()->get('status'),
        );
    }

    public function test_password_reset_completes_and_invalidates_token(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('old-password-123')]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));

        // Token is single-use: attempting again with the same token must fail.
        $replay = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'another-password-789',
            'password_confirmation' => 'another-password-789',
        ]);
        $replay->assertSessionHasErrors('email');
    }

    public function test_password_change_invalidates_other_sessions(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('old-password-123')]);

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('old-password-123')]);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong-current-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_session_invalidator_leaves_current_session_untouched(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        DB::table('sessions')->insert([
            ['id' => 'current', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'other-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'other-2', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);

        app(SessionInvalidator::class)->invalidateAllExcept($user, 'current');

        $this->assertDatabaseHas('sessions', ['id' => 'current']);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-2']);
    }
}
