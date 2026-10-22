<?php

namespace Tests\Feature\Payment;

use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Services\Donation\DonationService;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\PaymentCreationService;
use App\Services\Payment\ProviderCredentialPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 HD-IMP009-13 / F-06 / F-07 / F-08 — canonical Money and
 * provider-unit conversion at the adapter boundary.
 *
 * Oracle example used throughout: human Rp 10,000 = 1,000,000
 * canonical amount_minor (IDR digits = 2, locked IMP-007 contract).
 * Verified provider units (official docs, 2026-09-20):
 * - manual_transfer: 1,000,000 (identity — no provider)
 * - tripay: 10000 whole IDR (create `amount`, callback `total_amount`)
 * - xendit: 10000 major units (create `request_amount`, webhook
 *   `data.request_amount`)
 * - stripe: 1000000 sen (PaymentIntent smallest unit; IDR is NOT a
 *   Stripe zero-decimal currency)
 *
 * Oracle tests NEVER compare the same integer through both sides: a
 * callback echoing the raw canonical integer in a provider-unit field
 * MUST mismatch, proving cross-unit comparison is impossible.
 */
class PaymentMoneyConversionTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private const CANONICAL_RP_10K = 1000000;

    private function makeOracleDonation(int $amountMinor = self::CANONICAL_RP_10K, string $currency = 'IDR'): Donation
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'guest_name' => 'Oracle Giver',
            'guest_email' => 'oracle-'.uniqid().'@example.com',
        ], null, 'oracle-'.uniqid());
    }

    private function seedCredential(string $provider, array $fields): void
    {
        PaymentProviderCredential::create([
            'provider' => $provider,
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString(ProviderCredentialPayload::encode($provider, $fields)),
            'is_enabled' => true,
        ]);
    }

    public function test_manual_transfer_carries_canonical_units_unchanged(): void
    {
        $donation = $this->makeOracleDonation();

        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'oracle-manual-'.uniqid()
        )->payment;

        $this->assertSame(self::CANONICAL_RP_10K, $payment->instructions_payload['amount_minor']);
    }

    public function test_tripay_create_sends_whole_idr_with_official_signature(): void
    {
        $this->seedCredential('tripay', [
            'merchant_code' => 'T0001',
            'api_key' => 'tripay-oracle-key',
            'private_key' => 'tripay-oracle-private',
        ]);

        Http::fake([
            '*transaction/create*' => Http::response(['data' => ['reference' => 'T-ORACLE', 'expired_time' => time() + 3600]], 200),
        ]);

        $donation = $this->makeOracleDonation();

        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'tripay', 'channel' => 'BRIVA'], null, 'oracle-tripay-'.uniqid()
        )->payment;

        Http::assertSent(function ($request) use ($payment) {
            return $request['method'] === 'BRIVA'
                && $request['amount'] === 10000
                && $request['merchant_ref'] === $payment->ulid
                && $request['signature'] === hash_hmac('sha256', 'T0001'.$payment->ulid.'10000', 'tripay-oracle-private');
        });

        $this->assertSame('T-ORACLE', $payment->fresh()->provider_reference);
    }

    public function test_tripay_callback_converts_whole_idr_before_comparison(): void
    {
        $privateKey = 'tripay-oracle-cb-private';
        $this->seedCredential('tripay', [
            'merchant_code' => 'T0001',
            'api_key' => 'k',
            'private_key' => $privateKey,
        ]);

        Http::fake(['*transaction/create*' => Http::response(['data' => ['reference' => 'T-ORACLE-CB']], 200)]);

        $donation = $this->makeOracleDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'tripay', 'channel' => 'BRIVA'], null, 'oracle-tripay-cb-'.uniqid()
        )->payment;
        $payment->forceFill(['provider_reference' => 'T-ORACLE-CB'])->save();

        $send = function (array $payload) use ($privateKey) {
            $body = json_encode($payload);

            return $this->call('POST', '/webhooks/payments/tripay', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $body, $privateKey),
            ], $body);
        };

        $send([
            'reference' => 'T-ORACLE-CB',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => 10000,
        ])->assertStatus(200);

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
    }

    public function test_tripay_callback_echoing_raw_canonical_minor_mismatches(): void
    {
        $privateKey = 'tripay-oracle-raw-private';
        $this->seedCredential('tripay', [
            'merchant_code' => 'T0001',
            'api_key' => 'k',
            'private_key' => $privateKey,
        ]);

        Http::fake(['*transaction/create*' => Http::response(['data' => ['reference' => 'T-ORACLE-RAW']], 200)]);

        $donation = $this->makeOracleDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'tripay', 'channel' => 'BRIVA'], null, 'oracle-tripay-raw-'.uniqid()
        )->payment;
        $payment->forceFill(['provider_reference' => 'T-ORACLE-RAW'])->save();

        // 1000000 in the whole-IDR field converts to 100000000
        // canonical minor — never equal to the Payment's 1000000.
        $body = json_encode([
            'reference' => 'T-ORACLE-RAW',
            'merchant_ref' => $payment->ulid,
            'status' => 'PAID',
            'total_amount' => self::CANONICAL_RP_10K,
        ]);

        $this->call('POST', '/webhooks/payments/tripay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $body, $privateKey),
        ], $body)->assertStatus(400);

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'tripay',
            'processing_result' => 'REJECTED_AMOUNT_MISMATCH',
        ]);
    }

    public function test_xendit_create_sends_major_units_with_official_fields(): void
    {
        $this->seedCredential('xendit', [
            'api_key' => 'xnd_oracle_key',
            'callback_token' => 'xnd-oracle-token',
        ]);

        Http::fake([
            '*payment_requests*' => Http::response(['payment_request_id' => 'pr-oracle', 'status' => 'PENDING'], 200),
        ]);

        $donation = $this->makeOracleDonation();

        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'xendit', 'channel' => 'QRIS'], null, 'oracle-xendit-'.uniqid()
        )->payment;

        Http::assertSent(function ($request) use ($payment) {
            return $request['reference_id'] === $payment->ulid
                && $request['type'] === 'PAY'
                && $request['currency'] === 'IDR'
                && $request['request_amount'] === 10000;
        });

        $this->assertSame('pr-oracle', $payment->fresh()->provider_reference);
    }

    public function test_xendit_callback_converts_major_units_before_comparison(): void
    {
        $token = 'xnd-oracle-cb-token';
        $this->seedCredential('xendit', ['api_key' => 'k', 'callback_token' => $token]);

        Http::fake(['*payment_requests*' => Http::response(['payment_request_id' => 'pr-oracle-cb'], 200)]);

        $donation = $this->makeOracleDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'xendit', 'channel' => 'QRIS'], null, 'oracle-xendit-cb-'.uniqid()
        )->payment;
        $payment->forceFill(['provider_reference' => 'pr-oracle-cb'])->save();

        $send = function (int $requestAmount) use ($token) {
            $body = json_encode([
                'event' => 'payment.capture',
                'data' => [
                    'payment_request_id' => 'pr-oracle-cb',
                    'currency' => 'IDR',
                    'request_amount' => $requestAmount,
                    'status' => 'SUCCEEDED',
                ],
            ]);

            return $this->call('POST', '/webhooks/payments/xendit', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_TOKEN' => $token,
            ], $body);
        };

        $send(10000)->assertStatus(200);
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);

        // Raw canonical minor echoed as major units must mismatch.
        $payment->forceFill(['status' => 'PENDING'])->save();
        $send(self::CANONICAL_RP_10K)->assertStatus(400);
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'xendit',
            'processing_result' => 'REJECTED_AMOUNT_MISMATCH',
        ]);
    }

    public function test_stripe_create_sends_smallest_unit_sen_for_idr(): void
    {
        $this->seedCredential('stripe', [
            'secret_key' => 'sk_oracle',
            'webhook_secret' => 'whsec_oracle',
        ]);

        Http::fake([
            '*payment_intents*' => Http::response(['id' => 'pi_oracle', 'client_secret' => 'pi_oracle_secret_abc'], 200),
        ]);

        $donation = $this->makeOracleDonation();

        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'stripe'], null, 'oracle-stripe-'.uniqid()
        )->payment;

        Http::assertSent(function ($request) {
            return (int) $request['amount'] === 1000000
                && strtolower((string) $request['currency']) === 'idr';
        });

        $this->assertSame('pi_oracle', $payment->fresh()->provider_reference);
    }

    public function test_stripe_callback_compares_smallest_units(): void
    {
        $secret = 'whsec_oracle_cb';
        $this->seedCredential('stripe', ['secret_key' => 'sk', 'webhook_secret' => $secret]);

        Http::fake(['*payment_intents*' => Http::response(['id' => 'pi_oracle_cb'], 200)]);

        $donation = $this->makeOracleDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'stripe'], null, 'oracle-stripe-cb-'.uniqid()
        )->payment;
        $payment->forceFill(['provider_reference' => 'pi_oracle_cb'])->save();

        $send = function (int $amount) use ($secret) {
            $body = json_encode([
                'id' => 'evt_'.uniqid(),
                'data' => ['object' => [
                    'id' => 'pi_oracle_cb',
                    'status' => 'succeeded',
                    'amount' => $amount,
                    'currency' => 'idr',
                ]],
            ]);
            $timestamp = (string) time();

            return $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
                'HTTP_Stripe-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
                'CONTENT_TYPE' => 'application/json',
            ], $body);
        };

        $send(1000000)->assertStatus(200);
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);

        // 10000 sen (Rp 100.00) against a Rp 10,000.00 Payment mismatches.
        $payment->forceFill(['status' => 'PENDING'])->save();
        $send(10000)->assertStatus(400);
        $this->assertDatabaseHas('payment_provider_events', [
            'provider' => 'stripe',
            'processing_result' => 'REJECTED_AMOUNT_MISMATCH',
        ]);
    }

    public function test_unsupported_provider_currency_creates_no_row_and_makes_no_provider_call(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $donation = $this->makeOracleDonation(currency: 'USD');

        try {
            app(PaymentCreationService::class)->create(
                $donation, ['provider' => 'tripay', 'channel' => 'BRIVA'], null, 'oracle-cur-'.uniqid()
            );
            $this->fail('Tripay + USD must be a typed rejection with no Payment row.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('provider_currency_unsupported', $e->reason);
        }

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_non_representable_amount_creates_no_row(): void
    {
        $donation = $this->makeOracleDonation(amountMinor: 1000001);

        try {
            app(PaymentCreationService::class)->create(
                $donation, ['provider' => 'tripay', 'channel' => 'BRIVA'], null, 'oracle-rep-'.uniqid()
            );
            $this->fail('A non-representable amount must be rejected with no Payment row.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('provider_amount_not_representable', $e->reason);
        }

        $this->assertSame(0, Payment::query()->count());
    }
}
