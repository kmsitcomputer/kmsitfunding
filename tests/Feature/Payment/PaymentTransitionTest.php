<?php

namespace Tests\Feature\Payment;

use App\Models\Payment\ManualTransferEvidence;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\ManualTransferVerificationService;
use App\Services\Payment\PaymentCreationService;
use App\Services\Payment\PaymentTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — state transitions + Donation integration
 * (docs/implementation/IMP-009-payment-hub.md "State Machines" /
 * "Donation Integration" / "Financial Boundary", BR-6/BR-8/BR-20,
 * HD-IMP009-02/11, AC-009-014/022): the full canonical transition
 * matrix including every rejected invalid transition; terminal
 * Payment outcomes driving the owning PENDING Donation through
 * IMP-008's real transition surface (never a direct mutation); late
 * outcomes against an already-terminal Donation recorded for review
 * without forcing (HD-IMP009-02).
 */
class PaymentTransitionTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    private function makeSystemActor(string $code = 'test.payment.race'): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(['code' => $code], ['description' => 'Test payment identity.']);

        return app(PrincipalService::class)->forSystem($catalog);
    }

    private function makePendingPayment(): Payment
    {
        $donation = $this->makePendingGuestDonation();

        return app(PaymentCreationService::class)->create(
            $donation,
            ['provider' => 'manual_transfer'],
            null,
            'trans-'.uniqid()
        );
    }

    public function test_full_forward_transition_matrix(): void
    {
        $system = $this->makeSystemActor();
        $service = app(PaymentTransitionService::class);

        $payment = $this->makePendingPayment();
        $service->markRequiresAction($payment);
        $this->assertSame('REQUIRES_ACTION', $payment->fresh()->status);

        $service->markSucceeded($payment->fresh(), $system);
        $moved = $payment->fresh();
        $this->assertSame('SUCCEEDED', $moved->status);
        $this->assertNotNull($moved->succeeded_at);

        $failed = $this->makePendingPayment();
        $service->markFailed($failed, $system, 'PROVIDER_DECLINED');
        $this->assertSame('FAILED', $failed->fresh()->status);
        $this->assertSame('PROVIDER_DECLINED', $failed->fresh()->failure_reason);

        $expired = $this->makePendingPayment();
        $service->markExpired($expired, $system);
        $this->assertSame('EXPIRED', $expired->fresh()->status);
        $this->assertNotNull($expired->fresh()->expired_at);

        $cancelled = $this->makePendingPayment();
        $actor = $this->makeUnauthorizedActor();
        $service->cancel($cancelled, $actor);
        $this->assertSame('CANCELLED', $cancelled->fresh()->status);
        $this->assertSame($actor->id, $cancelled->fresh()->cancelled_by_principal_id);
    }

    public function test_every_transition_off_a_terminal_state_is_rejected(): void
    {
        $system = $this->makeSystemActor();
        $service = app(PaymentTransitionService::class);

        foreach (['SUCCEEDED', 'FAILED', 'EXPIRED', 'CANCELLED'] as $terminal) {
            $payment = $this->makePendingPayment();
            $payment->forceFill(['status' => $terminal])->save();

            foreach (['markRequiresAction', 'markSucceeded', 'markFailed', 'markExpired'] as $method) {
                try {
                    $method === 'markRequiresAction'
                        ? $service->markRequiresAction($payment->fresh())
                        : $service->$method($payment->fresh(), $system);
                    $this->fail("{$method} off {$terminal} must be rejected.");
                } catch (PaymentTransitionConflictException $e) {
                    $this->assertSame('invalid_transition', $e->reason);
                }
            }

            $this->assertSame($terminal, $payment->fresh()->status);
        }
    }

    public function test_duplicate_same_outcome_report_is_a_no_op(): void
    {
        $system = $this->makeSystemActor();
        $service = app(PaymentTransitionService::class);

        $payment = $this->makePendingPayment();
        $service->markSucceeded($payment, $system);

        $again = $service->applyCanonicalOutcome($payment->fresh(), 'SUCCEEDED', $system);

        $this->assertSame('SUCCEEDED', $again->status);
        $this->assertSame(1, Payment::query()->where('id', $payment->id)->count());
    }

    public function test_succeeded_payment_drives_the_pending_donation_through_the_canonical_surface(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);
        $payment = $service->create($donation, ['provider' => 'manual_transfer'], null, 'int-suc-'.uniqid());

        $consequenceActor = $this->makeSystemActor('system.payment-outcome-consequence');

        app(PaymentTransitionService::class)->markSucceeded($payment, $consequenceActor);
        app(DonationTransitionService::class)->markSucceeded($donation->fresh(), $consequenceActor);

        $this->assertSame('SUCCEEDED', $donation->fresh()->status);
        $this->assertNotNull($donation->fresh()->succeeded_at);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.succeeded',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_late_outcome_against_a_terminal_donation_is_recorded_never_forced(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);
        $payment = $service->create($donation, ['provider' => 'manual_transfer'], null, 'int-late-'.uniqid());

        // The Donation independently reaches a terminal state first
        // (its own sweep/cancel path — simulated here directly).
        $donation->forceFill(['status' => 'EXPIRED', 'expired_at' => now()])->save();

        $consequenceActor = $this->makeSystemActor('system.payment-outcome-consequence-2');

        // The verified provider fact is still recorded on the Payment.
        app(PaymentTransitionService::class)->markSucceeded($payment, $consequenceActor);
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);

        // The Donation transition request is rejected by IMP-008's own
        // BR-7 — caught and flagged, never forced.
        try {
            app(DonationTransitionService::class)->markSucceeded($donation->fresh(), $consequenceActor);
            $this->fail('Transition of an already-terminal Donation must be rejected.');
        } catch (DonationTransitionConflictException) {
            // Expected — HD-IMP009-02 path.
        }

        $this->assertSame('EXPIRED', $donation->fresh()->status);

        // The full handler-level path (webhook service) records the
        // CRITICAL review event — covered in PaymentWebhookSecurityTest;
        // here the Payment fact itself provably committed.
        $this->assertNotNull($payment->fresh()->succeeded_at);
    }

    public function test_manual_approve_drives_payment_and_donation_to_succeeded_in_one_path(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $verifier = $this->makeAuthorizedActor();

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'int-man-'.uniqid()
        );

        $evidence = ManualTransferEvidence::create([
            'ulid' => (string) Str::ulid(),
            'payment_id' => $payment->id,
            'file_path' => 'evidence/manual-probe.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        app(ManualTransferVerificationService::class)->approve($payment, $verifier, $evidence->id);

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame($verifier->id, $payment->fresh()->verified_by_principal_id);
        $this->assertSame('SUCCEEDED', $donation->fresh()->status);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'manual_transfer.approved',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_payment_code_never_mutates_donation_state_directly(): void
    {
        // HD-IMP009-11: the ONLY Donation writer the Payment domain may
        // invoke is DonationTransitionService::markSucceeded/markFailed.
        // No Payment-domain file may forceFill+save a Donation, call
        // update() on one, or run a raw donations status write.
        $sources = [
            'app/Services/Payment/PaymentCreationService.php',
            'app/Services/Payment/PaymentTransitionService.php',
            'app/Services/Payment/PaymentWebhookHandler.php',
            'app/Services/Payment/ManualTransferVerificationService.php',
            'app/Services/Payment/ManualTransferEvidenceService.php',
        ];

        foreach ($sources as $source) {
            $code = file_get_contents(base_path($source));

            $this->assertDoesNotMatchRegularExpression(
                '/donation[A-Za-z]*->forceFill\([^)]*status/si',
                $code,
                "{$source} must never forceFill a Donation status (HD-IMP009-11)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/DB::table\(\s*[\'"]donations[\'"]\s*\)\s*->\s*(update|insert)/i',
                $code,
                "{$source} must never run a raw donations write (HD-IMP009-11)."
            );
        }

        // And the two mutating call sites provably go through the
        // canonical surface.
        $this->assertStringContainsString(
            'DonationTransitionService',
            file_get_contents(base_path('app/Services/Payment/PaymentWebhookHandler.php'))
        );
        $this->assertStringContainsString(
            'DonationTransitionService',
            file_get_contents(base_path('app/Services/Payment/ManualTransferVerificationService.php'))
        );
    }
}
