<?php

namespace Tests\Feature\Identity;

use App\Models\MfaSecret;
use App\Models\User;
use App\Services\Identity\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);
    }

    public function test_enrollment_is_not_enabled_until_confirmed(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);

        $enrollment = $mfa->startEnrollment($user);

        $this->assertFalse($user->fresh()->mfa_enabled);
        $this->assertNotEmpty($enrollment['secret']);
    }

    public function test_wrong_code_does_not_activate_enrollment(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $mfa->startEnrollment($user);

        $result = $mfa->confirmEnrollment($user, '000000');

        $this->assertFalse($result['success']);
        $this->assertFalse($user->fresh()->mfa_enabled);
    }

    public function test_correct_code_activates_enrollment_and_issues_recovery_codes(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);

        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);
        $result = $mfa->confirmEnrollment($user, $code);

        $this->assertTrue($result['success']);
        $this->assertTrue($user->fresh()->mfa_enabled);
        $this->assertCount(8, $result['recovery_codes']);

        $stored = MfaSecret::where('user_id', $user->id)->first();
        $this->assertNotNull($stored->secret);
        $this->assertNull($stored->pending_secret);

        foreach ($stored->recovery_codes as $entry) {
            $this->assertArrayHasKey('hash', $entry);
            $this->assertNull($entry['used_at']);
        }
    }

    public function test_same_totp_code_cannot_activate_enrollment_twice(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);

        $mfa->confirmEnrollment($user, $code);

        // Restart a new enrollment with the SAME secret to attempt replay of
        // the exact same accepted code/time-step against the same context.
        // Go through the Eloquent model (not a raw query builder update) so
        // the 'encrypted' cast on pending_secret is applied correctly.
        $mfaSecret = MfaSecret::where('user_id', $user->id)->first();
        $mfaSecret->pending_secret = $enrollment['secret'];
        $mfaSecret->pending_secret_expires_at = now()->addMinutes(15);
        $mfaSecret->save();

        $replay = $mfa->confirmEnrollment($user, $code);

        $this->assertFalse($replay['success']);
    }

    public function test_same_totp_code_cannot_be_used_twice_for_login_challenge(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $confirmCode = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);
        $mfa->confirmEnrollment($user, $confirmCode);

        $loginCode = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);

        $first = $mfa->verifyChallenge($user, 'login', $loginCode);
        $second = $mfa->verifyChallenge($user, 'login', $loginCode);

        $this->assertTrue($first);
        $this->assertFalse($second, 'The same accepted TOTP code must not verify twice for the same context.');
    }

    public function test_recovery_code_is_single_use(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);
        $confirmation = $mfa->confirmEnrollment($user, $code);
        $recoveryCode = $confirmation['recovery_codes'][0];

        $first = $mfa->consumeRecoveryCode($user, $recoveryCode);
        $second = $mfa->consumeRecoveryCode($user, $recoveryCode);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function test_regenerating_recovery_codes_invalidates_old_ones(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);
        $confirmation = $mfa->confirmEnrollment($user, $code);
        $oldCode = $confirmation['recovery_codes'][0];

        $mfa->regenerateRecoveryCodes($user);

        $this->assertFalse($mfa->consumeRecoveryCode($user, $oldCode));
    }

    public function test_disable_requires_fresh_password_and_elevated_assurance(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);
        $mfa->confirmEnrollment($user, $code);

        // No ELEVATED assurance established yet — must be denied.
        $response = $this->actingAs($user)->delete('/account/mfa', ['current_password' => 'password12345']);
        $response->assertForbidden();
        $this->assertTrue($user->fresh()->mfa_enabled);
    }

    public function test_disable_succeeds_with_fresh_password_and_elevated_assurance(): void
    {
        $user = $this->makeUser();
        $mfa = app(MfaService::class);
        $enrollment = $mfa->startEnrollment($user);
        $code = app(Google2FA::class)->getCurrentOtp($enrollment['secret']);
        $mfa->confirmEnrollment($user, $code);

        $this->actingAs($user);
        $this->post('/account/confirm-password', ['password' => 'password12345']);

        $response = $this->delete('/account/mfa', ['current_password' => 'password12345']);

        $response->assertRedirect();
        $this->assertFalse($user->fresh()->mfa_enabled);
        $this->assertNull(MfaSecret::where('user_id', $user->id)->first());
    }
}
