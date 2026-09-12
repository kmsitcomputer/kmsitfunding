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

    /**
     * Invokes EmailChangeService's private finalizeConflict() directly via
     * reflection. IMP002-IMPL-m02: this is how a terminal-state interleaving
     * that a single-process PHPUnit run cannot produce via genuine concurrency
     * (e.g. "the request was cancelled/superseded/verified by something else
     * between conflict detection and finalization") is exercised against the
     * REAL production code path, rather than re-implementing its logic in the
     * test. Production code never calls this directly — only verify() does.
     */
    private function callFinalizeConflict(EmailChangeService $service, int $requestId): void
    {
        $method = new \ReflectionMethod($service, 'finalizeConflict');
        $method->invoke($service, $requestId);
    }

    public function test_new_request_supersedes_prior_active_request_and_old_token_fails(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $first = $service->requestChange($user, 'new-a@example.com');
        $second = $service->requestChange($user, 'new-b@example.com');

        $this->assertSame('requested', $first['status']);
        $this->assertSame('requested', $second['status']);
        $this->assertNotNull($first['request']->fresh()->superseded_at);

        $resultA = $service->verify($this->requestWithSession(), $user, $first['request']->id, $first['plain_token']);
        $this->assertSame('failed', $resultA['status']);
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
        $this->assertSame('failed', $result['status']);
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
        $this->assertSame('failed', $replay['status']);

        // Expired.
        $expired = $service->requestChange($user, 'expired@example.com');
        $expired['request']->forceFill(['expires_at' => now()->subMinute()])->save();
        $result = $service->verify($this->requestWithSession(), $user, $expired['request']->id, $expired['plain_token']);
        $this->assertSame('failed', $result['status']);

        // Cancelled (simulated directly, since no cancellation endpoint is
        // required by the baseline spec).
        $cancelled = $service->requestChange($user, 'cancelled@example.com');
        $cancelled['request']->forceFill(['cancelled_at' => now()])->save();
        $result = $service->verify($this->requestWithSession(), $user, $cancelled['request']->id, $cancelled['plain_token']);
        $this->assertSame('failed', $result['status']);
    }

    /**
     * IMP002-IMPL-M01 (section 4) — a target email already occupied at
     * REQUEST-creation time must be rejected up front: no EmailChangeRequest
     * row is created at all, and (per M02) the outward response must not
     * reveal that occupancy — verified separately at the controller level in
     * EmailChangeControllerTest-equivalent coverage below.
     */
    public function test_request_time_availability_precheck_rejects_already_occupied_target_without_creating_a_request(): void
    {
        $user = $this->makeUser();
        User::create(['email' => 'taken@example.com', 'password' => Hash::make('password12345')]);

        $service = app(EmailChangeService::class);
        $result = $service->requestChange($user, 'taken@example.com');

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame(0, EmailChangeRequest::where('user_id', $user->id)->count());
    }

    /**
     * IMP002-IMPL-M01 (sections 5-6) — the real race: the request is created
     * while the target is AVAILABLE; only afterward does another identity
     * claim it. Promotion must roll back completely and the request must
     * reach the durable CONFLICTED terminal state via the classified
     * uniqueness violation, not the request-time pre-check (which no longer
     * applies once the request already exists).
     */
    public function test_uniqueness_conflict_detected_at_promotion_time_becomes_durable_conflicted_state(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, 'taken@example.com');
        $this->assertSame('requested', $change['status']);

        // The email is claimed by another identity AFTER the request was
        // created — the exact race this remediation targets.
        User::create(['email' => 'taken@example.com', 'password' => Hash::make('password12345')]);

        $result = $service->verify($this->requestWithSession(), $user, $change['request']->id, $change['plain_token']);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('old@example.com', $user->fresh()->email);

        $fresh = $change['request']->fresh();
        $this->assertNotNull($fresh->conflicted_at);
        $this->assertSame(EmailChangeRequest::CONFLICT_TARGET_EMAIL_ALREADY_IN_USE, $fresh->conflict_reason_code);
        $this->assertNull($fresh->verified_at);

        // The conflicted request can never be retried.
        $retry = $service->verify($this->requestWithSession(), $user, $change['request']->id, $change['plain_token']);
        $this->assertSame('failed', $retry['status']);

        // A brand-new, independent request may still be created afterward.
        $newAttempt = $service->requestChange($user, 'different-target@example.com');
        $this->assertSame('requested', $newAttempt['status']);
        $this->assertTrue($newAttempt['request']->fresh()->isActive());
    }

    public function test_expiry_wins_over_conflict_when_request_expires_before_finalization(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, 'taken@example.com');
        User::create(['email' => 'taken@example.com', 'password' => Hash::make('password12345')]);

        // Simulate the request having expired by the time conflict
        // finalization would run.
        $change['request']->forceFill(['expires_at' => now()->subSecond()])->save();

        $result = $service->verify($this->requestWithSession(), $user, $change['request']->id, $change['plain_token']);

        $this->assertSame('failed', $result['status']);

        $fresh = $change['request']->fresh();
        $this->assertNull($fresh->conflicted_at);
        $this->assertNull($fresh->conflict_reason_code);
        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    /**
     * IMP002-IMPL-m02 — "conflict vs cancellation": a request already
     * detected as conflicted-eligible (still ACTIVE) is cancelled by
     * something else before finalization actually runs. The pre-existing
     * CANCELLED terminal state must win; finalizeConflict() must no-op.
     */
    public function test_conflict_finalization_never_overwrites_a_concurrently_cancelled_request(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, 'target@example.com');
        $change['request']->forceFill(['cancelled_at' => now()])->save();

        $this->callFinalizeConflict($service, $change['request']->id);

        $fresh = $change['request']->fresh();
        $this->assertNull($fresh->conflicted_at);
        $this->assertNull($fresh->conflict_reason_code);
        $this->assertNotNull($fresh->cancelled_at);
    }

    /**
     * IMP002-IMPL-m02 — "conflict vs supersession": a newer request replaces
     * this one between conflict detection and finalization.
     */
    public function test_conflict_finalization_never_overwrites_a_concurrently_superseded_request(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, 'target@example.com');
        $change['request']->forceFill(['superseded_at' => now()])->save();

        $this->callFinalizeConflict($service, $change['request']->id);

        $fresh = $change['request']->fresh();
        $this->assertNull($fresh->conflicted_at);
        $this->assertNull($fresh->conflict_reason_code);
        $this->assertNotNull($fresh->superseded_at);
    }

    /**
     * IMP002-IMPL-m02 — "conflict vs verification": the request is somehow
     * verified/promoted by something else before finalization runs. VERIFIED
     * must win; conflicted_at must never be set on an already-consumed row.
     */
    public function test_conflict_finalization_never_overwrites_an_already_verified_request(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, 'target@example.com');
        $change['request']->forceFill(['verified_at' => now()])->save();

        $this->callFinalizeConflict($service, $change['request']->id);

        $fresh = $change['request']->fresh();
        $this->assertNull($fresh->conflicted_at);
        $this->assertNull($fresh->conflict_reason_code);
        $this->assertNotNull($fresh->verified_at);
    }

    /**
     * IMP002-IMPL-m01 — the User row is locked before the generation lookup/
     * supersede/create sequence, so two requests for the same User in
     * immediate succession (the closest a single-process PHPUnit run can
     * approximate real concurrency — see docs/audits/
     * IMP-002-IMPLEMENTATION-REMEDIATION-1.md "MySQL") never leave two
     * simultaneously ACTIVE rows.
     */
    public function test_two_requests_in_immediate_succession_never_leave_two_active_requests(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $first = $service->requestChange($user, 'first@example.com');
        $second = $service->requestChange($user, 'second@example.com');

        $this->assertSame(
            1,
            EmailChangeRequest::where('user_id', $user->id)
                ->whereNull('verified_at')->whereNull('superseded_at')
                ->whereNull('cancelled_at')->whereNull('conflicted_at')
                ->where('expires_at', '>', now())
                ->count(),
        );
        $this->assertTrue($second['request']->fresh()->isActive());
        $this->assertNotNull($first['request']->fresh()->superseded_at);
    }

    public function test_email_change_is_normalized(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        $change = $service->requestChange($user, '  New.Address@Example.COM ');

        $this->assertSame('new.address@example.com', $change['request']->normalized_pending_email);
    }

    /**
     * IMP002-IMPL-M02 — the outward HTTP response for a uniqueness conflict
     * must be indistinguishable from an ordinary invalid/expired token. Both
     * must produce the exact same flashed status string; only the internal
     * request row/audit log may know which one actually happened.
     */
    public function test_verification_failure_response_does_not_disclose_email_occupancy(): void
    {
        $user = $this->makeUser();
        $service = app(EmailChangeService::class);

        // Invalid-token case.
        $invalidTokenChange = $service->requestChange($user, 'invalid-case@example.com');
        $invalidResponse = $this->actingAs($user)
            ->get("/account/email/{$invalidTokenChange['request']->id}/verify?token=not-the-right-token");

        // Uniqueness-conflict case.
        $conflictChange = $service->requestChange($user, 'conflict-case@example.com');
        User::create(['email' => 'conflict-case@example.com', 'password' => Hash::make('password12345')]);
        $conflictResponse = $this->actingAs($user)
            ->get("/account/email/{$conflictChange['request']->id}/verify?token={$conflictChange['plain_token']}");

        $invalidStatus = $invalidResponse->getSession()->get('status');
        $conflictStatus = $conflictResponse->getSession()->get('status');

        $this->assertSame($invalidStatus, $conflictStatus);
        $this->assertNotSame('email-change-conflicted', $conflictStatus);

        // Internal state remains precise — the durable CONFLICTED terminal
        // state and its reason code are still recorded, just never exposed.
        $this->assertNotNull($conflictChange['request']->fresh()->conflicted_at);
    }

    /**
     * IMP002-IMPL-M02 — requesting a change to an email that is already taken
     * gets the SAME generic "requested" response as a normal, successful
     * request — never a distinguishable "unavailable" signal.
     */
    public function test_request_response_does_not_disclose_email_occupancy(): void
    {
        $user = $this->makeUser();
        User::create(['email' => 'taken@example.com', 'password' => Hash::make('password12345')]);

        $unavailableResponse = $this->actingAs($user)->post('/account/email', [
            'email' => 'taken@example.com',
            'current_password' => 'password12345',
        ]);

        $availableResponse = $this->actingAs($user)->post('/account/email', [
            'email' => 'available@example.com',
            'current_password' => 'password12345',
        ]);

        $this->assertSame(
            $unavailableResponse->getSession()->get('status'),
            $availableResponse->getSession()->get('status'),
        );
    }
}
