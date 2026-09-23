<?php

namespace Tests\Unit\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Enums\ManualTransferReviewOutcome;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WebhookProcessingResult;
use App\Services\Payment\PaymentAdapterFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * IMP-009 — canonical enum/state contracts (docs/implementation/
 * IMP-009-payment-hub.md "State Machines" / "Domain Model"): the
 * provider-neutral status set, the closed provider allow-list, the
 * webhook processing outcomes, the manual-review outcomes, and every
 * adapter's normalizeStatus() mapping table — including unmapped
 * values rejected rather than silently defaulted (BR-4).
 */
class PaymentStateMachineTest extends TestCase
{
    public function test_canonical_status_active_and_terminal_classification(): void
    {
        $this->assertTrue(PaymentStatus::Pending->isActive());
        $this->assertTrue(PaymentStatus::RequiresAction->isActive());
        $this->assertFalse(PaymentStatus::Succeeded->isActive());
        $this->assertFalse(PaymentStatus::Failed->isActive());
        $this->assertFalse(PaymentStatus::Expired->isActive());
        $this->assertFalse(PaymentStatus::Cancelled->isActive());

        $this->assertTrue(PaymentStatus::Succeeded->isTerminal());
        $this->assertTrue(PaymentStatus::Failed->isTerminal());
        $this->assertTrue(PaymentStatus::Expired->isTerminal());
        $this->assertTrue(PaymentStatus::Cancelled->isTerminal());
        $this->assertFalse(PaymentStatus::Pending->isTerminal());
        $this->assertFalse(PaymentStatus::RequiresAction->isTerminal());
    }

    public function test_provider_allow_list_is_exactly_the_four_approved_values(): void
    {
        $this->assertSame(
            ['manual_transfer', 'tripay', 'xendit', 'stripe'],
            array_map(fn (PaymentProvider $provider) => $provider->value, PaymentProvider::cases())
        );
    }

    public function test_webhook_processing_result_rejection_classification(): void
    {
        $this->assertFalse(WebhookProcessingResult::Accepted->isRejection());
        $this->assertFalse(WebhookProcessingResult::Duplicate->isRejection());
        $this->assertTrue(WebhookProcessingResult::RejectedInvalidSignature->isRejection());
        $this->assertTrue(WebhookProcessingResult::RejectedUnknownReference->isRejection());
        $this->assertTrue(WebhookProcessingResult::RejectedAmountMismatch->isRejection());
        $this->assertTrue(WebhookProcessingResult::RejectedCurrencyMismatch->isRejection());
        $this->assertTrue(WebhookProcessingResult::RejectedMalformed->isRejection());
    }

    public function test_manual_transfer_review_outcomes_are_exactly_the_three_approved_values(): void
    {
        $this->assertSame(
            ['APPROVED', 'REJECTED', 'AMOUNT_MISMATCH_HOLD'],
            array_map(fn (ManualTransferReviewOutcome $outcome) => $outcome->value, ManualTransferReviewOutcome::cases())
        );
    }

    public function test_adapter_factory_registers_exactly_the_four_approved_providers(): void
    {
        $factory = new PaymentAdapterFactory;

        $this->assertSame(['manual_transfer', 'tripay', 'xendit', 'stripe'], $factory->providers());

        foreach ($factory->providers() as $provider) {
            $this->assertSame($provider, $factory->for($provider)->providerCode());
        }
    }

    public function test_adapter_factory_rejects_midtrans_and_unknown_providers(): void
    {
        $factory = new PaymentAdapterFactory;

        foreach (['midtrans', 'paypal', '', 'TRIPAY'] as $provider) {
            try {
                $factory->for($provider);
                $this->fail("Provider '{$provider}' must be rejected.");
            } catch (\RuntimeException $e) {
                $this->assertSame('invalid_provider', $e->reason ?? $e->getMessage());
            }
        }
    }

    /**
     * @dataProvider manualTransferMapping
     */
    #[DataProvider('manualTransferMapping')]
    public function test_manual_transfer_normalize_status(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PaymentAdapterFactory)->for('manual_transfer')->normalizeStatus($input));
    }

    public static function manualTransferMapping(): array
    {
        return [
            ['AWAITING_PROOF', 'PENDING'],
            ['UNDER_REVIEW', 'PENDING'],
            ['APPROVED', 'SUCCEEDED'],
            ['REJECTED', 'FAILED'],
        ];
    }

    /**
     * @dataProvider tripayMapping
     */
    #[DataProvider('tripayMapping')]
    public function test_tripay_normalize_status(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PaymentAdapterFactory)->for('tripay')->normalizeStatus($input));
    }

    public static function tripayMapping(): array
    {
        return [
            ['UNPAID', 'PENDING'],
            ['PAID', 'SUCCEEDED'],
            ['EXPIRED', 'EXPIRED'],
            ['FAILED', 'FAILED'],
        ];
    }

    /**
     * @dataProvider xenditMapping
     */
    #[DataProvider('xenditMapping')]
    public function test_xendit_payment_request_normalize_status(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PaymentAdapterFactory)->for('xendit')->normalizeStatus($input));
    }

    public static function xenditMapping(): array
    {
        return [
            ['PENDING', 'PENDING'],
            ['REQUIRES_ACTION', 'REQUIRES_ACTION'],
            ['SUCCEEDED', 'SUCCEEDED'],
            ['FAILED', 'FAILED'],
            ['EXPIRED', 'EXPIRED'],
            ['CANCELED', 'CANCELLED'],
        ];
    }

    /**
     * @dataProvider stripeMapping
     */
    #[DataProvider('stripeMapping')]
    public function test_stripe_normalize_status(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PaymentAdapterFactory)->for('stripe')->normalizeStatus($input));
    }

    public static function stripeMapping(): array
    {
        return [
            ['requires_payment_method', 'PENDING'],
            ['requires_confirmation', 'PENDING'],
            ['processing', 'PENDING'],
            ['requires_action', 'REQUIRES_ACTION'],
            ['succeeded', 'SUCCEEDED'],
            ['canceled', 'CANCELLED'],
        ];
    }

    public function test_unmapped_provider_status_values_are_rejected_never_defaulted(): void
    {
        $factory = new PaymentAdapterFactory;

        foreach (['tripay', 'xendit', 'stripe', 'manual_transfer'] as $provider) {
            try {
                $factory->for($provider)->normalizeStatus('SOMETHING_UNEXPECTED_'.uniqid());
                $this->fail("Unmapped status for {$provider} must be rejected.");
            } catch (PaymentAdapterError $e) {
                $this->assertSame('unknown_provider_status', $e->reason);
            }
        }
    }

    public function test_tripay_refund_signal_is_never_mapped(): void
    {
        try {
            (new PaymentAdapterFactory)->for('tripay')->normalizeStatus('REFUND');
            $this->fail('Tripay REFUND must never map to a canonical Payment state (IMP-016).');
        } catch (PaymentAdapterError $e) {
            $this->assertSame('unknown_provider_status', $e->reason);
        }
    }

    public function test_stripe_and_manual_transfer_have_no_provider_supplied_expiry(): void
    {
        $factory = new PaymentAdapterFactory;

        $this->assertNull($factory->for('stripe')->normalizeExpiration([]));
        $this->assertNull($factory->for('manual_transfer')->normalizeExpiration([]));
    }

    public function test_tripay_normalize_expiration_extracts_expired_time(): void
    {
        $expiresAt = (new PaymentAdapterFactory)->for('tripay')->normalizeExpiration(['expired_time' => 1893456000]);

        $this->assertNotNull($expiresAt);
        $this->assertSame(1893456000, $expiresAt->getTimestamp());
        $this->assertNull((new PaymentAdapterFactory)->for('tripay')->normalizeExpiration([]));
    }

    public function test_tripay_supports_idr_only(): void
    {
        $this->assertSame(['IDR'], (new PaymentAdapterFactory)->for('tripay')->supportedCurrencies());
    }

    public function test_only_manual_transfer_lacks_a_refund_call(): void
    {
        $factory = new PaymentAdapterFactory;

        $this->assertFalse($factory->for('manual_transfer')->supportsRefundCall());
        $this->assertTrue($factory->for('tripay')->supportsRefundCall());
        $this->assertTrue($factory->for('xendit')->supportsRefundCall());
        $this->assertTrue($factory->for('stripe')->supportsRefundCall());
    }
}
