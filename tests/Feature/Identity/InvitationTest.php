<?php

namespace Tests\Feature\Identity;

use App\Models\Invitation;
use App\Models\User;
use App\Services\Identity\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_can_be_accepted_and_grants_no_authority(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'rep@example.com', null);

        $result = $service->accept(
            $issued['invitation'],
            $issued['plain_token'],
            'rep@example.com',
            'password12345',
        );

        $this->assertSame('accepted', $result['status']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertNotNull($issued['invitation']->fresh()->accepted_at);

        // No role/permission/authority column exists to check — the absence
        // of such a schema at all is the guarantee (see SchemaBoundaryTest).
    }

    public function test_wrong_intended_email_is_rejected(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_INTERNAL_ADMINISTRATIVE_IDENTITY, 'staff@example.com', null);

        $result = $service->accept($issued['invitation'], $issued['plain_token'], 'attacker@example.com', 'password12345');

        $this->assertSame('invalid', $result['status']);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_SUPER_ADMIN, 'admin@example.com', null);
        $issued['invitation']->forceFill(['expires_at' => now()->subDay()])->save();

        $result = $service->accept($issued['invitation'], $issued['plain_token'], 'admin@example.com', 'password12345');

        $this->assertSame('invalid', $result['status']);
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'rep@example.com', null);

        $revoked = $service->revoke($issued['invitation'], null);
        $this->assertTrue($revoked);

        $result = $service->accept($issued['invitation'], $issued['plain_token'], 'rep@example.com', 'password12345');
        $this->assertSame('invalid', $result['status']);
    }

    public function test_invitation_cannot_be_accepted_twice(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'rep@example.com', null);

        $service->accept($issued['invitation'], $issued['plain_token'], 'rep@example.com', 'password12345');
        $second = $service->accept($issued['invitation'], $issued['plain_token'], 'rep@example.com', 'password67890');

        $this->assertSame('invalid', $second['status']);
    }

    public function test_uniqueness_race_never_creates_duplicate_identity(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'race@example.com', null);

        // A User self-registers the same email before the invitation is accepted.
        User::create(['email' => 'race@example.com', 'password' => Hash::make('password12345')]);

        $result = $service->accept($issued['invitation'], $issued['plain_token'], 'race@example.com', 'password12345');

        $this->assertSame('invalid', $result['status']);
        $this->assertSame(1, User::where('email', 'race@example.com')->count());
    }

    /**
     * IMP002-IMPL-M08 — revoke() now locks the row exactly like accept() does.
     * Acceptance committing first must make a subsequent revocation attempt
     * fail safely, and the invitation must never end up with both accepted_at
     * and revoked_at populated.
     */
    public function test_acceptance_wins_and_subsequent_revocation_is_rejected(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'rep@example.com', null);

        $accepted = $service->accept($issued['invitation'], $issued['plain_token'], 'rep@example.com', 'password12345');
        $this->assertSame('accepted', $accepted['status']);

        $revoked = $service->revoke($issued['invitation'], null);

        $this->assertFalse($revoked);

        $fresh = $issued['invitation']->fresh();
        $this->assertNotNull($fresh->accepted_at);
        $this->assertNull($fresh->revoked_at);
    }

    /**
     * IMP002-IMPL-M08 — the reverse ordering: revocation committing first
     * must make a subsequent acceptance attempt fail safely, with the same
     * mutual-exclusivity guarantee.
     */
    public function test_revocation_wins_and_subsequent_acceptance_is_rejected(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'rep@example.com', null);

        $revoked = $service->revoke($issued['invitation'], null);
        $this->assertTrue($revoked);

        $accepted = $service->accept($issued['invitation'], $issued['plain_token'], 'rep@example.com', 'password12345');

        $this->assertSame('invalid', $accepted['status']);

        $fresh = $issued['invitation']->fresh();
        $this->assertNull($fresh->accepted_at);
        $this->assertNotNull($fresh->revoked_at);
        $this->assertSame(0, User::where('email', 'rep@example.com')->count());
    }

    public function test_accepted_at_and_revoked_at_can_never_both_be_populated(): void
    {
        $service = app(InvitationService::class);

        // Order 1: accept then attempt revoke.
        $first = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'first@example.com', null);
        $service->accept($first['invitation'], $first['plain_token'], 'first@example.com', 'password12345');
        $service->revoke($first['invitation'], null);
        $firstFresh = $first['invitation']->fresh();
        $this->assertFalse($firstFresh->accepted_at !== null && $firstFresh->revoked_at !== null);

        // Order 2: revoke then attempt accept.
        $second = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'second@example.com', null);
        $service->revoke($second['invitation'], null);
        $service->accept($second['invitation'], $second['plain_token'], 'second@example.com', 'password12345');
        $secondFresh = $second['invitation']->fresh();
        $this->assertFalse($secondFresh->accepted_at !== null && $secondFresh->revoked_at !== null);
    }

    public function test_public_route_can_accept_invitation(): void
    {
        $service = app(InvitationService::class);
        $issued = $service->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'rep@example.com', null);

        $response = $this->post("/invitations/{$issued['invitation']->public_id}/accept", [
            'token' => $issued['plain_token'],
            'email' => 'rep@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }
}
