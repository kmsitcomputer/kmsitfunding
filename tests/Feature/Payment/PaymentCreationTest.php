<?php

namespace Tests\Feature\Payment;

use App\Models\Payment\Payment;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\PaymentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — Payment creation + idempotency (docs/implementation/
 * IMP-009-payment-hub.md "Domain Model" / "Idempotency" / BR-1/BR-2/
 * BR-11/BR-19, HD-IMP009-01/03, AC-009-001/002/008/009/021):
 * guest/authenticated creation against a PENDING Donation, rejection
 * against a non-PENDING Donation, mandatory Idempotency-Key, same-key
 * replay vs conflict, and the one-ACTIVE-attempt invariant (second
 * creation while ACTIVE rejected; new creation after terminal
 * succeeds; no lifetime count limit).
 */
class PaymentCreationTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    public function test_guest_creates_a_manual_transfer_payment_against_a_pending_donation(): void
    {
        $donation = $this->makePendingGuestDonation();

        $payment = app(PaymentCreationService::class)->create(
            $donation,
            ['provider' => 'manual_transfer'],
            null,
            'create-guest-'.uniqid()
        );

        $this->assertSame('PENDING', $payment->status);
        $this->assertSame($donation->id, $payment->donation_id);
        $this->assertSame('manual_transfer', $payment->provider);
        $this->assertSame($donation->amount_minor, $payment->amount_minor);
        $this->assertSame($donation->currency, $payment->currency);
        $this->assertNotNull($payment->ulid);
        $this->assertNotNull($payment->provider_reference);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.attempt_created',
            'subject_id' => $payment->id,
            'actor_principal_kind' => 'unauthenticated',
            'actor_principal_id' => null,
            'execution_context' => 'http:payment:guest_attempt_created',
        ]);
    }

    public function test_authenticated_donor_creates_a_payment_for_their_own_donation(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $donation = $this->makePendingOwnedDonation($owner);

        $payment = app(PaymentCreationService::class)->create(
            $donation,
            ['provider' => 'manual_transfer'],
            $owner,
            'create-owned-'.uniqid()
        );

        $this->assertSame('PENDING', $payment->status);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.attempt_created',
            'subject_id' => $payment->id,
            'actor_principal_kind' => 'human',
            'actor_principal_id' => $owner->id,
        ]);
    }

    public function test_creation_against_a_non_pending_donation_is_rejected(): void
    {
        $donation = $this->makePendingGuestDonation();
        $donation->forceFill(['status' => 'SUCCEEDED'])->save();

        try {
            app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], null, 'create-np-'.uniqid());
            $this->fail('Creation against a non-PENDING Donation must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('donation_not_pending', $e->reason);
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_creation_for_another_donors_donation_is_rejected(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $intruder = $this->makeUnauthorizedActor();
        $donation = $this->makePendingOwnedDonation($owner);

        try {
            app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], $intruder, 'create-intr-'.uniqid());
            $this->fail('Creation for another donor\'s Donation must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('donation_not_owned', $e->reason);
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_missing_or_malformed_idempotency_key_is_rejected_first(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);

        foreach ([null, '', '   ', str_repeat('k', 129), "key\x01with-control"] as $badKey) {
            try {
                $service->create($donation, ['provider' => 'manual_transfer'], null, $badKey);
                $this->fail('A missing/malformed Idempotency-Key must be rejected.');
            } catch (PaymentValidationException $e) {
                $this->assertContains($e->reason, ['missing_idempotency_key', 'invalid_idempotency_key']);
            }
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_same_key_matching_payload_returns_the_existing_payment(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);
        $key = 'replay-same-'.uniqid();

        $first = $service->create($donation, ['provider' => 'manual_transfer'], null, $key);
        $second = $service->create($donation, ['provider' => 'manual_transfer'], null, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->where('idempotency_key', $key)->count());
    }

    public function test_same_key_different_payload_is_a_typed_conflict(): void
    {
        $donation = $this->makePendingGuestDonation();
        $other = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);
        $key = 'replay-diff-'.uniqid();

        $service->create($donation, ['provider' => 'manual_transfer'], null, $key);

        try {
            $service->create($other, ['provider' => 'manual_transfer'], null, $key);
            $this->fail('Same key + different donation must be a typed conflict.');
        } catch (PaymentTransitionConflictException $e) {
            $this->assertSame('idempotency_conflict', $e->reason);
        }

        $this->assertSame(1, Payment::query()->where('idempotency_key', $key)->count());
    }

    public function test_second_creation_while_an_attempt_is_active_is_rejected(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);

        $service->create($donation, ['provider' => 'manual_transfer'], null, 'active-1-'.uniqid());

        try {
            $service->create($donation, ['provider' => 'manual_transfer'], null, 'active-2-'.uniqid());
            $this->fail('A second ACTIVE attempt must be rejected (HD-IMP009-01).');
        } catch (PaymentTransitionConflictException $e) {
            $this->assertSame('active_attempt_exists', $e->reason);
        }

        $this->assertSame(1, Payment::query()->where('donation_id', $donation->id)->count());
    }

    public function test_new_attempt_succeeds_after_the_prior_attempt_reaches_terminal(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);

        $first = $service->create($donation, ['provider' => 'manual_transfer'], null, 'seq-1-'.uniqid());
        $first->forceFill(['status' => 'FAILED', 'failed_at' => now()])->save();

        $second = $service->create($donation, ['provider' => 'manual_transfer'], null, 'seq-2-'.uniqid());

        $this->assertSame('PENDING', $second->status);
        $this->assertSame(2, Payment::query()->where('donation_id', $donation->id)->count());
    }

    public function test_no_lifetime_maximum_attempt_count_applies(): void
    {
        $donation = $this->makePendingGuestDonation();
        $service = app(PaymentCreationService::class);

        for ($i = 0; $i < 3; $i++) {
            $attempt = $service->create($donation, ['provider' => 'manual_transfer'], null, 'nolimit-'.$i.'-'.uniqid());
            $attempt->forceFill(['status' => 'FAILED', 'failed_at' => now()])->save();
        }

        $final = $service->create($donation, ['provider' => 'manual_transfer'], null, 'nolimit-final-'.uniqid());

        $this->assertSame('PENDING', $final->status);
        $this->assertSame(4, Payment::query()->where('donation_id', $donation->id)->count());
    }

    public function test_unknown_provider_is_rejected_with_no_row_created(): void
    {
        $donation = $this->makePendingGuestDonation();

        try {
            app(PaymentCreationService::class)->create($donation, ['provider' => 'midtrans'], null, 'badprov-'.uniqid());
            $this->fail('An unapproved provider must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('invalid_provider', $e->reason);
        }

        $this->assertSame(0, Payment::query()->count());
    }
}
