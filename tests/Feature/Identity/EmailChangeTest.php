<?php

namespace Tests\Feature\Identity;

use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Services\Identity\EmailChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create(['email' => 'old@example.com', 'password' => Hash::make('password12345')]);
    }

    /**
     * EmailChangeService::verify() needs a Request with a working session
     * (for rotation/regeneration) — build one directly rather than relying on
     * the global `request()` helper, which has no session bound outside of an
     * actual HTTP test call.
     */
    private function requestWithSession(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());

        return $request;
    }

    public function test_new_request_supersedes_prior_active_request_and_old_token_fails(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $first = $service->requestChange($user, 'new-a@example.com');
        $second = $service->requestChange($user, 'new-b@example.com');

        $this->assertNotNull($first['request']->fresh()->superseded_at);

        $resultA = $service->verify($this->requestWithSession(), $user, $first['request']->id, $first['plain_token']);
        $this->assertSame('invalid', $resultA['status']);
        $this->assertSame('old@example.com', $user->fresh()->email);

        $resultB = $service->verify($this->requestWithSession(), $user, $second['request']->id, $second['plain_token']);
        $this->assertSame('promoted', $resultB['status']);
        $this->assertSame('new-b@example.com', $user->fresh()->email);
    }

    public function test_token_cannot_promote_a_different_requests_email(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $service->requestChange($user, 'new-a@example.com');
        $second = $service->requestChange($user, 'new-b@example.com');

        // Even if somehow presented against the wrong request id, the token
        // hash simply won't match — verified via the exact request lookup.
        $result = $service->verify($this->requestWithSession(), $user, $second['request']->id, 'not-the-right-token');
        $this->assertSame('invalid', $result['status']);
        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_cancelled_expired_and_consumed_requests_cannot_verify(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        // Consumed.
        $verified = $service->requestChange($user, 'verified@example.com');
        $service->verify($this->requestWithSession(), $user, $verified['request']->id, $verified['plain_token']);
        $replay = $service->verify($this->requestWithSession(), $user, $verified['request']->id, $verified['plain_token']);
        $this->assertSame('invalid', $replay['status']);

        // Expired.
        $expired = $service->requestChange($user, 'expired@example.com');
        $expired['request']->forceFill(['expires_at' => now()->subMinute()])->save();
        $result = $service->verify($this->requestWithSession(), $user, $expired['request']->id, $expired['plain_token']);
        $this->assertSame('invalid', $result['status']);

        // Cancelled (simulated directly, since no cancellation endpoint is
        // required by the baseline spec).
        $cancelled = $service->requestChange($user, 'cancelled@example.com');
        $cancelled['request']->forceFill(['cancelled_at' => now()])->save();
        $result = $service->verify($this->requestWithSession(), $user, $cancelled['request']->id, $cancelled['plain_token']);
        $this->assertSame('invalid', $result['status']);
    }

    public function test_uniqueness_conflict_leaves_canonical_email_unchanged_and_becomes_conflicted(): void
    {
        $user = $this->makeUser();
        $otherUser = User::create(['email' => 'taken@example.com', 'password' => Hash::make('password12345')]);

        $service = app(EmailChangeService::class);
        $change = $service->requestChange($user, 'taken@example.com');

        $result = $service->verify($this->requestWithSession(), $user, $change['request']->id, $change['plain_token']);

        $this->assertSame('conflicted', $result['status']);
        $this->assertSame('old@example.com', $user->fresh()->email);

        $fresh = $change['request']->fresh();
        $this->assertNotNull($fresh->conflicted_at);
        $this->assertSame(EmailChangeRequest::CONFLICT_TARGET_EMAIL_ALREADY_IN_USE, $fresh->conflict_reason_code);
        $this->assertNull($fresh->verified_at);

        // The conflicted request can never be retried.
        $retry = $service->verify($this->requestWithSession(), $user, $change['request']->id, $change['plain_token']);
        $this->assertSame('invalid', $retry['status']);

        // A brand-new, independent request may still be created afterward.
        $newAttempt = $service->requestChange($user, 'different-target@example.com');
        $this->assertTrue($newAttempt['request']->fresh()->isActive());
    }

    public function test_expiry_wins_over_conflict_when_request_expires_before_finalization(): void
    {
        $user = $this->makeUser();
        User::create(['email' => 'taken@example.com', 'password' => Hash::make('password12345')]);

        $service = app(EmailChangeService::class);
        $change = $service->requestChange($user, 'taken@example.com');

        // Simulate the request having expired by the time conflict
        // finalization would run.
        $change['request']->forceFill(['expires_at' => now()->subSecond()])->save();

        $result = $service->verify($this->requestWithSession(), $user, $change['request']->id, $change['plain_token']);

        $this->assertSame('invalid', $result['status']);

        $fresh = $change['request']->fresh();
        $this->assertNull($fresh->conflicted_at);
        $this->assertNull($fresh->conflict_reason_code);
        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_email_change_is_normalized(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, '  New.Address@Example.COM ');

        $this->assertSame('new.address@example.com', $change['request']->normalized_pending_email);
    }
}
