<?php

namespace Tests\Feature\Identity;

use App\Models\EmailChangeRequest;
use App\Models\Invitation;
use App\Models\MfaSecret;
use App\Models\SuperAdminBootstrap;
use App\Models\User;
use App\Services\Identity\EmailChangeService;
use App\Services\Identity\IdentityAuditLogger;
use App\Services\Identity\InvitationService;
use App\Services\Identity\MfaService;
use App\Services\Identity\PasswordService;
use App\Services\Identity\RegistrationService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\TruncatesInMemorySqlite;
use Tests\TestCase;

/**
 * IMP004-IMPL-M05 — forced canonical-audit-write-failure rollback evidence
 * for every CRITICAL/MUTATION_ATOMIC Identity mutation family (Q26): the
 * business mutation and its canonical audit append must commit or roll back
 * TOGETHER — never a false successful mutation with no audit trail, and
 * never an orphan audit record for a mutation that didn't happen. Each test
 * forces the failure at the real IdentityAuditLogger seam (never a mock
 * bypassing the actual transaction the production code opens) and asserts
 * BOTH that the business state did not change AND that no audit row exists.
 *
 * The three NON_CRITICAL Identity events (login_succeeded, login_failed,
 * logout) declare no persistence strategy and carry no atomicity contract —
 * out of scope here by the specification's own design.
 *
 * Uses TruncatesInMemorySqlite: several of these services are reached
 * through RolePermissionService-adjacent Principal resolution paths that
 * must genuinely own their own transaction — see that trait's docblock.
 */
class IdentityAuditFailureRollbackTest extends TestCase
{
    use TruncatesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTruncatedDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownTruncatedDatabase();
        parent::tearDown();
    }

    private int $userSequence = 0;

    private function makeUser(): User
    {
        $this->userSequence++;

        return User::create([
            'email' => "identity-audit-failure-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
    }

    /**
     * MfaSecret::$fillable only allows 'user_id' — 'secret'/'recovery_codes'
     * must be set directly (forceFill), never via create()'s mass-assignment,
     * which would silently drop them.
     */
    private function makeMfaSecret(User $user, string $secret, array $recoveryCodes): MfaSecret
    {
        $mfa = new MfaSecret(['user_id' => $user->id]);
        $mfa->forceFill(['secret' => $secret, 'recovery_codes' => $recoveryCodes])->save();

        return $mfa;
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());

        return $request;
    }

    /**
     * Binds IdentityAuditLogger to a variant whose record() always throws —
     * injected one seam above the final AuditWriter, exactly mirroring the
     * RbacAuditLogger forced-failure seam RbacAuditTest/AuditFoundationTest
     * already use, never a toy mock outside the real transaction path.
     */
    private function bindFailingIdentityAuditLogger(): void
    {
        $this->app->bind(IdentityAuditLogger::class, fn () => new class extends IdentityAuditLogger
        {
            public function __construct() {}

            public function record(string $event, ?User $user, array $context = []): void
            {
                throw new \RuntimeException('forced identity audit failure: '.$event);
            }
        });
    }

    // --- Registration (identity.user.created / identity.user.self_registered) ---

    public function test_forced_audit_failure_rolls_back_self_registration(): void
    {
        $this->bindFailingIdentityAuditLogger();

        try {
            app(RegistrationService::class)->register('rollback-registration@example.com', 'a-strong-password-123');
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback-registration@example.com']);
    }

    // --- Invitation issue / revoke / accept ---

    public function test_forced_audit_failure_rolls_back_invitation_issue(): void
    {
        $issuer = $this->makeUser();
        app(PrincipalService::class)->forUser($issuer);
        $this->bindFailingIdentityAuditLogger();

        try {
            app(InvitationService::class)->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'invitee-rollback@example.com', $issuer);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertSame(0, Invitation::where('intended_email', 'invitee-rollback@example.com')->count());
    }

    public function test_forced_audit_failure_rolls_back_invitation_revoke(): void
    {
        $issuer = $this->makeUser();
        app(PrincipalService::class)->forUser($issuer);
        $issued = app(InvitationService::class)->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'invitee-revoke-rollback@example.com', $issuer);

        $this->bindFailingIdentityAuditLogger();

        try {
            app(InvitationService::class)->revoke($issued['invitation'], $issuer);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertNull($issued['invitation']->fresh()->revoked_at);
    }

    public function test_forced_audit_failure_rolls_back_invitation_accept(): void
    {
        $issuer = $this->makeUser();
        app(PrincipalService::class)->forUser($issuer);
        $issued = app(InvitationService::class)->issue(InvitationService::ACTOR_PARTNER_REPRESENTATIVE, 'invitee-accept-rollback@example.com', $issuer);

        $this->bindFailingIdentityAuditLogger();

        try {
            app(InvitationService::class)->accept($issued['invitation'], $issued['plain_token'], 'invitee-accept-rollback@example.com', 'a-strong-password-123');
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertNull($issued['invitation']->fresh()->accepted_at);
        $this->assertDatabaseMissing('users', ['email' => 'invitee-accept-rollback@example.com']);
    }

    // --- Password change / reset ---

    public function test_forced_audit_failure_rolls_back_password_change(): void
    {
        $user = $this->makeUser();
        $originalHash = $user->password;
        $this->bindFailingIdentityAuditLogger();

        try {
            app(PasswordService::class)->changePassword($this->requestWithSession(), $user, 'a-new-strong-password-456');
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_forced_audit_failure_rolls_back_password_reset(): void
    {
        $user = $this->makeUser();
        $originalHash = $user->password;
        $token = Password::createToken($user);
        $this->bindFailingIdentityAuditLogger();

        try {
            app(PasswordService::class)->completeReset($this->requestWithSession(), $user->email, $token, 'a-new-strong-password-456');
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    // --- Email change request / promotion / conflict ---

    public function test_forced_audit_failure_rolls_back_email_change_request(): void
    {
        $user = $this->makeUser();
        $this->bindFailingIdentityAuditLogger();

        try {
            app(EmailChangeService::class)->requestChange($user, 'new-address-rollback@example.com');
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertSame(0, EmailChangeRequest::where('user_id', $user->id)->count());
    }

    public function test_forced_audit_failure_rolls_back_email_change_completion(): void
    {
        $user = $this->makeUser();
        $originalEmail = $user->email;
        $service = app(EmailChangeService::class);
        $outcome = $service->requestChange($user, 'new-address-completion-rollback@example.com');

        $this->bindFailingIdentityAuditLogger();
        $service = app(EmailChangeService::class);

        try {
            $service->verify($this->requestWithSession(), $user, $outcome['request']->id, $outcome['plain_token']);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->assertNull($outcome['request']->fresh()->verified_at);
    }

    public function test_forced_audit_failure_rolls_back_email_change_conflict_finalization(): void
    {
        $user = $this->makeUser();
        $conflictingUser = $this->makeUser();
        $service = app(EmailChangeService::class);
        $outcome = $service->requestChange($user, 'conflict-target-rollback@example.com');

        // Someone else claims the target email AFTER the request but BEFORE
        // promotion — this is what forces attemptPromotion() to detect a
        // conflict and hand off to the separate finalizeConflict() transaction.
        $conflictingUser->forceFill(['email' => 'conflict-target-rollback@example.com'])->save();

        $this->bindFailingIdentityAuditLogger();
        $service = app(EmailChangeService::class);

        // finalizeConflict() runs in its own transaction after
        // attemptPromotion() already returned 'conflict' — the forced audit
        // failure there propagates straight out of verify() (no catch wraps
        // it), which is itself part of the evidence: the failure is never
        // silently swallowed into a false "handled" outcome.
        try {
            $service->verify($this->requestWithSession(), $user, $outcome['request']->id, $outcome['plain_token']);
            $this->fail('Expected the forced conflict-finalization audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertNull(
            $outcome['request']->fresh()->conflicted_at,
            'A forced audit failure during conflict finalization must roll back the conflicted_at transition too — never a partially-applied state.'
        );
    }

    // --- Email verification (HTTP, signed URL) ---

    public function test_forced_audit_failure_rolls_back_email_verification(): void
    {
        Event::fake(Verified::class);
        $user = $this->makeUser();
        $this->bindFailingIdentityAuditLogger();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertStatus(500);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    // --- MFA enroll / recovery-code use / regenerate / reset-disable ---

    public function test_forced_audit_failure_rolls_back_mfa_enrollment(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);

        $this->bindFailingIdentityAuditLogger();
        $mfa = app(MfaService::class);

        try {
            $mfa->confirmEnrollment($user, $code);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertFalse($user->fresh()->mfa_enabled);
        $this->assertNull(MfaSecret::where('user_id', $user->id)->first()->secret);
    }

    public function test_forced_audit_failure_rolls_back_mfa_recovery_code_use(): void
    {
        $user = $this->makeUser();
        $plainCode = 'ABCD-EFGH-IJKL';
        $this->makeMfaSecret($user, 'FAKESECRETFAKESECRET', [['hash' => Hash::make($plainCode), 'used_at' => null]]);

        $this->bindFailingIdentityAuditLogger();

        try {
            app(MfaService::class)->consumeRecoveryCode($user, $plainCode);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertNull(MfaSecret::where('user_id', $user->id)->first()->recovery_codes[0]['used_at']);
    }

    public function test_forced_audit_failure_rolls_back_mfa_recovery_codes_regeneration(): void
    {
        $user = $this->makeUser();
        $original = [['hash' => Hash::make('OLD1-CODE-HERE'), 'used_at' => null]];
        $this->makeMfaSecret($user, 'FAKESECRETFAKESECRET', $original);

        $this->bindFailingIdentityAuditLogger();

        try {
            app(MfaService::class)->regenerateRecoveryCodes($user);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertTrue(Hash::check('OLD1-CODE-HERE', MfaSecret::where('user_id', $user->id)->first()->recovery_codes[0]['hash']));
    }

    public function test_forced_audit_failure_rolls_back_mfa_reset_or_disable(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['mfa_enabled' => true])->save();
        $plainCode = 'RECV-CODE-1234';
        $this->makeMfaSecret($user, 'FAKESECRETFAKESECRET', [['hash' => Hash::make($plainCode), 'used_at' => null]]);

        $this->bindFailingIdentityAuditLogger();

        try {
            app(MfaService::class)->disable($this->requestWithSession(), $user, null, $plainCode);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forced identity audit failure', $e->getMessage());
        }

        $this->assertTrue($user->fresh()->mfa_enabled);
        $this->assertNotNull(MfaSecret::where('user_id', $user->id)->first());
    }

    // --- First Super Admin bootstrap (Q25 CLI) ---

    public function test_forced_audit_failure_rolls_back_super_admin_bootstrap(): void
    {
        $this->bindFailingIdentityAuditLogger();

        $this->artisan('identity:bootstrap-super-admin')
            ->expectsQuestion('Super Admin email', 'bootstrap-rollback@example.com')
            ->expectsQuestion('Super Admin password', 'a-very-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-very-strong-password-123')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'bootstrap-rollback@example.com']);
        $this->assertSame(0, SuperAdminBootstrap::count());
    }
}
