<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Services\Identity\SessionInvalidator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * IMP002-IMPL-M06 — the reset REQUEST must normalize via the canonical
     * EmailNormalizer, both for the broker lookup and the rate-limit key, not
     * an ad hoc lowercase. A whitespace/case variant of an existing canonical
     * email must resolve to the same identity (the notification fires) and
     * key the SAME limiter bucket as the canonical form.
     */
    public function test_reset_link_request_normalizes_email_for_lookup_and_rate_limit_key(): void
    {
        Notification::fake();

        User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $this->post('/forgot-password', ['email' => '  User@Example.COM ']);

        Notification::assertSentTo(User::where('email', 'user@example.com')->first(), ResetPassword::class);

        $canonicalAttempts = RateLimiter::attempts('password-reset-request:user@example.com');
        $this->assertGreaterThan(0, $canonicalAttempts);

        $this->post('/forgot-password', ['email' => 'user@example.com']);

        // The whitespace/case variant and the canonical form share the exact
        // same limiter bucket — attempts accumulate under one key, not two.
        $this->assertSame(
            $canonicalAttempts + 1,
            RateLimiter::attempts('password-reset-request:user@example.com'),
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

    /**
     * IMP002-IMPL-M06 — completion must normalize the submitted email exactly
     * like the request step, so " user@example.com " (or any case variant)
     * resolves to the SAME canonical identity as stored (Q21).
     */
    public function test_password_reset_completion_normalizes_submitted_email(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('old-password-123')]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => '  User@Example.COM ',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
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

    /**
     * IMP002-IMPL-M07 — the credential mutation and the other-session
     * invalidation are one DB transaction. Force a failure inside the
     * transaction (via a mocked SessionInvalidator, which PasswordService
     * calls from inside DB::transaction()) and prove the password is NOT
     * left committed — the whole transaction rolls back together.
     */
    public function test_password_change_does_not_commit_a_partial_security_transition(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('old-password-123')]);

        $this->mock(SessionInvalidator::class, function ($mock) {
            $mock->shouldReceive('invalidateAllExcept')->andThrow(new \RuntimeException('injected failure'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->put('/account/password', [
                'current_password' => 'old-password-123',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ]);
            $this->fail('Expected the injected failure to propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('injected failure', $exception->getMessage());
        }

        $this->assertTrue(
            Hash::check('old-password-123', $user->fresh()->password),
            'The password must remain unchanged when the same-transaction session invalidation fails.',
        );
    }

    public function test_password_reset_does_not_commit_a_partial_security_transition(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('old-password-123')]);
        $token = Password::createToken($user);

        $this->mock(SessionInvalidator::class, function ($mock) {
            $mock->shouldReceive('invalidateAllExcept')->andThrow(new \RuntimeException('injected failure'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->post('/reset-password', [
                'token' => $token,
                'email' => 'user@example.com',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ]);
            $this->fail('Expected the injected failure to propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('injected failure', $exception->getMessage());
        }

        $this->assertTrue(
            Hash::check('old-password-123', $user->fresh()->password),
            'The password must remain unchanged when the same-transaction session invalidation fails.',
        );
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
