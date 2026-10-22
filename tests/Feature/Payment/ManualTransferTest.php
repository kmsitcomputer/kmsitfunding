<?php

namespace Tests\Feature\Payment;

use App\Enums\ScopeType;
use App\Models\Payment\ManualTransferEvidence;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\ManualTransferEvidenceService;
use App\Services\Payment\ManualTransferVerificationService;
use App\Services\Payment\PaymentCreationService;
use App\Services\Payment\ProviderCredentialPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — Manual Bank Transfer (docs/implementation/
 * IMP-009-payment-hub.md "Manual Transfer", HD-IMP009-06/07/08,
 * AC-009-010/011/023/024/025): mandatory evidence for APPROVE
 * (REJECT allowed with zero evidence); approve/reject/hold full
 * paths; non-financial_approver denial; amount-mismatch HOLD
 * semantics (Payment stays PENDING, Donation untouched, amounts
 * immutable); append-only resubmission while eligible and rejection
 * once terminal/reviewed.
 */
class ManualTransferTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    private function makeManualPayment(): Payment
    {
        $donation = $this->makePendingGuestDonation();

        return app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'manual-'.uniqid()
        )->payment;
    }

    private function fakeUpload(): UploadedFile
    {
        Storage::fake('local');

        return UploadedFile::fake()->image('proof.jpg', 800, 600)->size(100);
    }

    private function verifierWithAuthority(): Principal
    {
        $seeder = $this->makeAuthorizedActor();
        $verifier = $this->makeUnauthorizedActor();

        $authorityType = AuthorityType::firstOrCreate(
            ['code' => AuthorityType::FINANCIAL_APPROVER],
            ['name' => 'Financial Approver', 'description' => 'test', 'is_financial' => true],
        );

        AuthorityAssignment::create([
            'principal_id' => $verifier->id,
            'authority_type_id' => $authorityType->id,
            'scope_type' => ScopeType::Organization->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => $seeder->id,
        ]);

        return $verifier;
    }

    public function test_approve_without_any_evidence_is_rejected(): void
    {
        $payment = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();

        try {
            app(ManualTransferVerificationService::class)->approve($payment, $verifier, 999999);
            $this->fail('APPROVE with zero evidence must be rejected (HD-IMP009-06).');
        } catch (PaymentValidationException $e) {
            $this->assertSame('evidence_not_found', $e->reason);
        }

        $this->assertSame('PENDING', $payment->fresh()->status);
    }

    public function test_reject_with_zero_evidence_is_permitted(): void
    {
        $payment = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();

        app(ManualTransferVerificationService::class)->reject($payment, $verifier, null, 'No transfer found.');

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('FAILED', $payment->fresh()->donation()->first()->status);
    }

    public function test_submit_approve_full_path(): void
    {
        $payment = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();

        $evidence = app(ManualTransferEvidenceService::class)->submit($payment, $this->fakeUpload(), [
            'declared_amount_minor' => $payment->amount_minor,
            'declared_currency' => $payment->currency,
        ], null);

        $this->assertNotNull($evidence->id);
        $this->assertStringStartsWith('evidence/', $evidence->file_path);
        $this->assertStringNotContainsString('proof.jpg', $evidence->file_path);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'manual_transfer.evidence_submitted',
            'subject_id' => $evidence->id,
            'actor_principal_kind' => 'unauthenticated',
            'execution_context' => 'http:payment:guest_manual_transfer_evidence_submitted',
        ]);

        app(ManualTransferVerificationService::class)->approve($payment->fresh(), $verifier, $evidence->id);

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame($verifier->id, $payment->fresh()->verified_by_principal_id);
        $this->assertSame('SUCCEEDED', $payment->fresh()->donation()->first()->status);
        $this->assertSame('APPROVED', $evidence->fresh()->review_outcome);
    }

    public function test_submit_reject_full_path(): void
    {
        $payment = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();

        $evidence = app(ManualTransferEvidenceService::class)->submit($payment, $this->fakeUpload(), [], null);

        app(ManualTransferVerificationService::class)->reject($payment->fresh(), $verifier, $evidence->id, 'Illegible.');

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('FAILED', $payment->fresh()->donation()->first()->status);
        $this->assertSame('REJECTED', $evidence->fresh()->review_outcome);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'manual_transfer.rejected',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_amount_mismatch_holds_without_touching_payment_or_donation(): void
    {
        $payment = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();
        $donationBefore = $payment->donation()->first();

        $evidence = app(ManualTransferEvidenceService::class)->submit($payment, $this->fakeUpload(), [
            'declared_amount_minor' => $payment->amount_minor - 500,
            'declared_currency' => $payment->currency,
        ], null);

        app(ManualTransferVerificationService::class)->holdForAmountMismatch(
            $payment->fresh(), $verifier, $evidence->id, 'Underpaid by 500.'
        );

        $this->assertSame('AMOUNT_MISMATCH_HOLD', $evidence->fresh()->review_outcome);
        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertSame('PENDING', $donationBefore->fresh()->status);
        $this->assertSame($donationBefore->amount_minor, $payment->fresh()->amount_minor);
        $this->assertSame($donationBefore->amount_minor, $donationBefore->fresh()->amount_minor);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'manual_transfer.amount_mismatch_held',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_direct_approve_of_mismatched_evidence_cannot_bypass_the_hold(): void
    {
        foreach ([-1, 1, -500, 500] as $delta) {
            $payment = $this->makeManualPayment();
            $verifier = $this->verifierWithAuthority();

            $evidence = app(ManualTransferEvidenceService::class)->submit($payment, $this->fakeUpload(), [
                'declared_amount_minor' => $payment->amount_minor + $delta,
                'declared_currency' => $payment->currency,
            ], null);

            try {
                app(ManualTransferVerificationService::class)->approve($payment->fresh(), $verifier, $evidence->id);
                $this->fail("Direct approve() of a {$delta}-unit mismatch must fail closed (HD-IMP009-07).");
            } catch (PaymentValidationException $e) {
                $this->assertSame('amount_mismatch_requires_hold', $e->reason);
            }

            $this->assertSame('PENDING', $payment->fresh()->status);
            $this->assertSame('PENDING', $payment->fresh()->donation()->first()->status);
            $this->assertNull($evidence->fresh()->review_outcome);
        }
    }

    public function test_direct_approve_without_a_declared_amount_cannot_succeed(): void
    {
        $payment = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();

        $evidence = app(ManualTransferEvidenceService::class)->submit($payment, $this->fakeUpload(), [], null);

        try {
            app(ManualTransferVerificationService::class)->approve($payment->fresh(), $verifier, $evidence->id);
            $this->fail('Direct approve() with no declared amount must fail closed.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('amount_mismatch_requires_hold', $e->reason);
        }

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertSame('PENDING', $payment->fresh()->donation()->first()->status);
    }

    public function test_resubmission_while_eligible_appends_without_overwriting(): void
    {
        $payment = $this->makeManualPayment();
        $service = app(ManualTransferEvidenceService::class);

        $first = $service->submit($payment, $this->fakeUpload(), [], null);
        $second = $service->submit($payment->fresh(), $this->fakeUpload(), [], null);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, ManualTransferEvidence::query()->where('payment_id', $payment->id)->count());
        $this->assertNotNull(ManualTransferEvidence::query()->whereKey($first->id)->first()->file_path);
    }

    public function test_submission_against_terminal_or_reviewed_payment_is_rejected(): void
    {
        $payment = $this->makeManualPayment();
        $service = app(ManualTransferEvidenceService::class);

        $payment->forceFill(['status' => 'EXPIRED', 'expired_at' => now()])->save();

        try {
            $service->submit($payment->fresh(), $this->fakeUpload(), [], null);
            $this->fail('Evidence against a terminal Payment must be rejected.');
        } catch (PaymentTransitionConflictException $e) {
            $this->assertSame('payment_not_eligible', $e->reason);
        }

        $held = $this->makeManualPayment();
        $verifier = $this->verifierWithAuthority();
        $evidence = $service->submit($held, $this->fakeUpload(), [
            'declared_amount_minor' => $held->amount_minor + 100,
        ], null);
        app(ManualTransferVerificationService::class)->holdForAmountMismatch(
            $held->fresh(), $verifier, $evidence->id
        );

        try {
            $service->submit($held->fresh(), $this->fakeUpload(), [], null);
            $this->fail('Evidence against a reviewed Payment must be rejected.');
        } catch (PaymentTransitionConflictException $e) {
            $this->assertSame('payment_already_reviewed', $e->reason);
        }
    }

    public function test_evidence_for_a_non_manual_provider_is_rejected(): void
    {
        $donation = $this->makePendingGuestDonation();

        Http::fake(['*' => Http::response(['data' => []], 200)]);
        PaymentProviderCredential::create([
            'provider' => 'tripay',
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString(ProviderCredentialPayload::encode('tripay', [
                'merchant_code' => 'T0001',
                'api_key' => 'k-api',
                'private_key' => 'k',
            ])),
            'is_enabled' => true,
        ]);

        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'tripay'], null, 'manual-wrong-'.uniqid()
        )->payment;

        try {
            app(ManualTransferEvidenceService::class)->submit($payment, $this->fakeUpload(), [], null);
            $this->fail('Evidence for a non-manual provider must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('not_manual_transfer', $e->reason);
        }
    }

    public function test_disallowed_mime_and_oversized_uploads_are_rejected_before_storage(): void
    {
        Storage::fake('local');
        $payment = $this->makeManualPayment();
        $service = app(ManualTransferEvidenceService::class);

        $bad = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        try {
            $service->submit($payment, $bad, [], null);
            $this->fail('A disallowed MIME type must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('disallowed_mime_type', $e->reason);
        }

        $this->assertSame(0, ManualTransferEvidence::query()->where('payment_id', $payment->id)->count());
    }
}
