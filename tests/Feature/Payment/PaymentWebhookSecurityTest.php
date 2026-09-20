<?php

namespace Tests\Feature\Payment;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Models\Payment\PaymentProviderEvent;
use App\Services\Payment\PaymentCreationService;
use App\Services\Payment\ProviderCredentialPayload;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — webhook/callback security (docs/implementation/
 * IMP-009-payment-hub.md "Webhook Security" / "Tripay" / "Xendit" /
 * "Stripe", AC-009-003/004/005/006/007): valid-signature acceptance
 * through the full pipeline (Payment transition + Donation
 * invocation + CRITICAL audit in one transaction); invalid signature,
 * malformed payload, unknown reference, amount/currency mismatch
 * rejections with no state change; duplicate-event idempotency;
 * out-of-order non-regression. Signed synthetic payloads only —
 * never a real provider charge.
 */
class PaymentWebhookSecurityTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        // Outbound provider createTransaction() calls are faked: webhook
        // tests prove the INBOUND pipeline, never a real provider
        // charge. Fixtures overwrite provider_reference explicitly after
        // creation, so the canned reference value never matters.
        Http::fake([
            '*transaction/create*' => Http::response(['data' => ['reference' => 'FAKE-REF']], 200),
            '*payment_requests*' => Http::response(['id' => 'FAKE-REQ'], 200),
            '*payment_intents*' => Http::response(['id' => 'FAKE-PI'], 200),
            '*' => Http::response([], 200),
        ]);
    }

    private function seedTripayCredential(): string
    {
        $privateKey = 'tripay-test-private-key';

        PaymentProviderCredential::create([
            'provider' => 'tripay',
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString(ProviderCredentialPayload::encode('tripay', [
                'merchant_code' => 'T0001',
                'api_key' => 'tripay-test-api-key',
                'private_key' => $privateKey,
            ])),
            'is_enabled' => true,
        ]);

        return $privateKey;
    }

    private function seedXenditCredential(): string
    {
        $token = 'xendit-test-callback-token';

        PaymentProviderCredential::create([
            'provider' => 'xendit',
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString(ProviderCredentialPayload::encode('xendit', [
                'api_key' => 'xnd_test_key',
                'callback_token' => $token,
            ])),
            'is_enabled' => true,
        ]);

        return $token;
    }

    private function seedStripeCredential(): string
    {
        $secret = 'whsec_test_signing_secret';

        PaymentProviderCredential::create([
            'provider' => 'stripe',
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString(ProviderCredentialPayload::encode('stripe', [
                'secret_key' => 'sk_test_key',
                'webhook_secret' => $secret,
            ])),
            'is_enabled' => true,
        ]);

        return $secret;
    }

    private function makeTripayPayment(string $reference): Payment
    {
        $donation = $this->makePendingGuestDonation();

        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'tripay', 'channel' => 'BRIVA'], null, 'hook-tripay-'.uniqid()
        );
        $payment->forceFill(['provider_reference' => $reference])->save();

        return $payment->fresh();
    }

    private function tripayCallback(string $privateKey, array $payload): array
    {
        $body = json_encode($payload);

        return [
            'body' => $body,
            'headers' => ['X-Callback-Signature' => hash_hmac('sha256', $body, $privateKey)],
        ];
    }

    /**
     * Posts the EXACT signed bytes: provider signature verification
     * runs over the raw request body, so parsed-array helpers (which
     * re-serialize) would invalidate every signature by construction.
     */
    private function postRaw(string $uri, string $body, array $headers = []): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $body);
    }

    public function test_valid_tripay_callback_runs_the_full_pipeline(): void
    {
        $privateKey = $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-001');

        $callback = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-001',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);

        $response = $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers']);

        $response->assertStatus(200);
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame('SUCCEEDED', $payment->fresh()->donation()->first()->status);

        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'payment_id' => $payment->id,
            'processing_result' => 'ACCEPTED',
        ]);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.succeeded',
            'subject_id' => $payment->id,
            'actor_principal_kind' => 'integration',
        ]);

        // F-14: webhook.received carries the actual provider-event row
        // as its subject (registry demands non-null), never null.
        $eventId = PaymentProviderEvent::query()
            ->where('payment_id', $payment->id)
            ->where('processing_result', 'ACCEPTED')
            ->value('id');

        $this->assertNotNull($eventId);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'webhook.received',
            'subject_type' => 'payment_provider_event',
            'subject_id' => $eventId,
        ]);
    }

    public function test_forged_tripay_signature_changes_nothing(): void
    {
        $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-002');

        $body = json_encode([
            'reference' => 'TRIPAY-REF-002',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);

        $response = $this->postRaw('/webhooks/payments/tripay', $body, [
            'X-Callback-Signature' => hash_hmac('sha256', $body, 'wrong-key'),
        ]);

        $response->assertStatus(400);
        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertSame('PENDING', $payment->fresh()->donation()->first()->status);

        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'processing_result' => 'REJECTED_INVALID_SIGNATURE',
            'signature_valid' => false,
        ]);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'webhook.verification_failed',
        ]);

        $response->assertJsonMissing(['exception' => true]);
    }

    public function test_tripay_amount_mismatch_is_rejected_before_any_transition(): void
    {
        $privateKey = $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-003');

        $callback = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-003',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100) - 1,
        ]);

        $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers'])
            ->assertStatus(400);

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'processing_result' => 'REJECTED_AMOUNT_MISMATCH',
            'signature_valid' => true,
        ]);
    }

    public function test_unknown_reference_creates_no_payment_row(): void
    {
        $privateKey = $this->seedTripayCredential();
        $before = Payment::query()->count();

        $callback = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-NOPE-'.uniqid(),
            'merchant_ref' => 'nope',
            'status' => 'PAID',
            'total_amount' => 100,
        ]);

        $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers'])
            ->assertStatus(400);

        $this->assertSame($before, Payment::query()->count());
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'payment_id' => null,
            'processing_result' => 'REJECTED_UNKNOWN_REFERENCE',
            'signature_valid' => true,
        ]);
    }

    public function test_malformed_payload_after_a_valid_signature_preserves_signature_valid(): void
    {
        $privateKey = $this->seedTripayCredential();

        $body = 'this is not json';
        $this->postRaw('/webhooks/payments/tripay', $body, [
            'X-Callback-Signature' => hash_hmac('sha256', $body, $privateKey),
        ])->assertStatus(400);

        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'processing_result' => 'REJECTED_MALFORMED',
            'signature_valid' => true,
        ]);
    }

    public function test_duplicate_callback_is_recorded_without_a_second_transition(): void
    {
        $privateKey = $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-004');

        $callback = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-004',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);

        $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers'])->assertStatus(200);
        $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers'])->assertStatus(200);

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame(1, PaymentProviderEvent::query()
            ->where('payment_id', $payment->id)
            ->where('processing_result', 'ACCEPTED')
            ->count());
        $this->assertSame(1, PaymentProviderEvent::query()
            ->where('payment_id', $payment->id)
            ->where('processing_result', 'DUPLICATE')
            ->count());
    }

    public function test_out_of_order_earlier_signal_never_moves_status_backward(): void
    {
        $privateKey = $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-005');

        $paid = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-005',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);
        $this->postRaw('/webhooks/payments/tripay', $paid['body'], $paid['headers'])->assertStatus(200);
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);

        $late = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-005',
            'merchant_ref' => $payment->ulid,
            'status' => 'UNPAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);
        $this->postRaw('/webhooks/payments/tripay', $late['body'], $late['headers'])->assertStatus(200);

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
    }

    public function test_valid_xendit_webhook_succeeds_a_payment(): void
    {
        $token = $this->seedXenditCredential();
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'xendit', 'channel' => 'QRIS'], null, 'hook-xendit-'.uniqid()
        );
        $payment->forceFill(['provider_reference' => 'xnd-req-001'])->save();

        // Official PaymentRequest webhook envelope: data.request_amount
        // is denominated in MAJOR units (100 = Rp 100 for the 10000
        // canonical minor fixture), correlated via
        // data.payment_request_id.
        $body = json_encode([
            'event' => 'payment.capture',
            'business_id' => 'test-business',
            'created' => gmdate('Y-m-d\TH:i:s\Z'),
            'data' => [
                'payment_request_id' => 'xnd-req-001',
                'reference_id' => $payment->ulid,
                'currency' => $payment->currency,
                'request_amount' => (int) ($payment->amount_minor / 100),
                'status' => 'SUCCEEDED',
            ],
        ]);

        $this->postRaw('/webhooks/payments/xendit', $body, [
            'x-callback-token' => $token,
        ])->assertStatus(200);

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame('SUCCEEDED', $payment->fresh()->donation()->first()->status);
    }

    public function test_xendit_wrong_callback_token_is_rejected(): void
    {
        $this->seedXenditCredential();

        $body = json_encode(['id' => 'xnd-nope', 'status' => 'SUCCEEDED']);

        $this->postRaw('/webhooks/payments/xendit', $body, [
            'x-callback-token' => 'wrong-token',
        ])->assertStatus(400);
    }

    public function test_valid_stripe_webhook_succeeds_a_payment(): void
    {
        $secret = $this->seedStripeCredential();
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'stripe'], null, 'hook-stripe-'.uniqid()
        );
        $payment->forceFill(['provider_reference' => 'pi_test_001'])->save();

        $body = json_encode([
            'id' => 'evt_test_001',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_001',
                'status' => 'succeeded',
                'amount' => $payment->amount_minor,
                'currency' => strtolower($payment->currency),
            ]],
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        // Laravel test helpers send parsed arrays; drive the handler
        // with a real raw body via call() so signature verification
        // sees the exact bytes it will verify.
        $response = $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(200);
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame('SUCCEEDED', $payment->fresh()->donation()->first()->status);
    }

    public function test_stripe_expired_tolerance_window_is_rejected(): void
    {
        $secret = $this->seedStripeCredential();

        $body = json_encode(['id' => 'evt_old', 'data' => ['object' => []]]);
        $oldTimestamp = (string) (time() - 3600);
        $signature = hash_hmac('sha256', $oldTimestamp.'.'.$body, $secret);

        $response = $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$oldTimestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(400);
    }

    public function test_stripe_amount_mismatch_is_rejected_before_any_transition(): void
    {
        $secret = $this->seedStripeCredential();
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'stripe'], null, 'hook-stripe-mm-'.uniqid()
        );
        $payment->forceFill(['provider_reference' => 'pi_test_mm'])->save();

        $body = json_encode([
            'id' => 'evt_test_mm',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_mm',
                'status' => 'succeeded',
                'amount' => $payment->amount_minor - 1,
                'currency' => strtolower($payment->currency),
            ]],
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'stripe',
            'processing_result' => 'REJECTED_AMOUNT_MISMATCH',
            'signature_valid' => true,
        ]);
    }

    public function test_late_success_after_donation_terminal_records_review_event(): void
    {
        $privateKey = $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-LATE');

        // The Donation independently terminal before the late webhook.
        $donation = $payment->donation()->first();
        $donation->forceFill(['status' => 'EXPIRED', 'expired_at' => now()])->save();

        $callback = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-LATE',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);

        $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers'])
            ->assertStatus(200);

        // Payment fact recorded; Donation untouched; review flagged.
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
        $this->assertSame('EXPIRED', $donation->fresh()->status);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.donation_transition_rejected',
            'subject_id' => $payment->id,
            'actor_principal_kind' => 'system',
        ]);
    }

    public function test_late_success_against_an_expired_payment_records_fact_without_regression(): void
    {
        $privateKey = $this->seedTripayCredential();
        $payment = $this->makeTripayPayment('TRIPAY-REF-EXPIRED');
        $donation = $payment->donation()->first();

        $payment->forceFill(['status' => 'EXPIRED', 'expired_at' => now()])->save();

        $callback = $this->tripayCallback($privateKey, [
            'reference' => 'TRIPAY-REF-EXPIRED',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => (int) ($payment->amount_minor / 100),
        ]);

        $this->postRaw('/webhooks/payments/tripay', $callback['body'], $callback['headers'])
            ->assertStatus(200);

        // Provider fact recorded; Payment never regressed out of
        // EXPIRED; Donation never forced; controlled review flagged.
        $this->assertSame('EXPIRED', $payment->fresh()->status);
        $this->assertSame('PENDING', $donation->fresh()->status);
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'payment_id' => $payment->id,
            'processing_result' => 'ACCEPTED',
            'signature_valid' => true,
        ]);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.donation_transition_rejected',
            'subject_id' => $payment->id,
            'actor_principal_kind' => 'system',
        ]);
    }
}
