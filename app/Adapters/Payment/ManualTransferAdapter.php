<?php

namespace App\Adapters\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Contracts\Payment\PaymentProviderAdapter;
use App\Contracts\Payment\ProviderTransactionResult;
use App\Contracts\Payment\VerifiedCallbackResult;
use App\Models\Payment\ManualTransferBankAccount;
use App\Models\Payment\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * IMP-009 — Manual Bank Transfer adapter
 * (docs/implementation/IMP-009-payment-hub.md "Manual Transfer" /
 * "State Machines"). The one provider with NO webhook/callback —
 * resolution is entirely human/admin-driven (AWAITING_PROOF ->
 * PENDING, UNDER_REVIEW -> PENDING, APPROVED -> SUCCEEDED, REJECTED
 * -> FAILED, AMOUNT_MISMATCH_HOLD leaves payments.status PENDING at
 * the evidence layer only).
 *
 * Transfer instructions render from the active
 * manual_transfer_bank_accounts row(s) matching the Payment's
 * currency. No unique payment/reference code is invented (no
 * authoritative source requires automated matching — Moota is
 * IMP-017's own concern). No provider secret exists for this adapter.
 */
class ManualTransferAdapter implements PaymentProviderAdapter
{
    public function providerCode(): string
    {
        return 'manual_transfer';
    }

    public function createTransaction(Payment $payment): ProviderTransactionResult
    {
        $accounts = ManualTransferBankAccount::query()
            ->where('currency', $payment->currency)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (ManualTransferBankAccount $account) => [
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_holder_name' => $account->account_holder_name,
                'currency' => $account->currency,
            ])
            ->all();

        return new ProviderTransactionResult(
            providerReference: 'MANUAL-'.$payment->ulid,
            channel: null,
            instructionsPayload: [
                'type' => 'manual_bank_transfer',
                'accounts' => $accounts,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
            ],
            expiresAt: null,
        );
    }

    public function verifyCallback(Request $request): VerifiedCallbackResult
    {
        return VerifiedCallbackResult::failed('manual_transfer_has_no_callback');
    }

    public function normalizeStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'AWAITING_PROOF', 'UNDER_REVIEW' => 'PENDING',
            'APPROVED' => 'SUCCEEDED',
            'REJECTED' => 'FAILED',
            default => throw new PaymentAdapterError(
                'unknown_provider_status',
                "Manual Transfer has no mappable status '{$providerStatus}' (AMOUNT_MISMATCH_HOLD never transitions payments.status)."
            ),
        };
    }

    public function normalizeExpiration(mixed $providerData): ?\DateTimeImmutable
    {
        return null;
    }

    public function supportedCurrencies(): array
    {
        return ManualTransferBankAccount::query()
            ->where('is_active', true)
            ->distinct()
            ->pluck('currency')
            ->all();
    }

    public function supportsRefundCall(): bool
    {
        return false;
    }

    public static function freshReference(): string
    {
        return 'MANUAL-'.(string) Str::ulid();
    }
}
