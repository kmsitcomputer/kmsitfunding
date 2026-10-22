<?php

namespace App\Contracts\Payment;

use App\Models\Payment\Payment;
use Illuminate\Http\Request;

/**
 * IMP-009 — provider-neutral adapter contract
 * (docs/implementation/IMP-009-payment-hub.md "Provider Adapter
 * Architecture"). Implemented once per approved provider
 * (manual_transfer, tripay, xendit, stripe). The canonical domain
 * (state machine, Donation integration, audit) never imports a
 * provider SDK or branches on a provider value — all provider-specific
 * behavior lives inside that provider's own adapter.
 *
 * Creating a NEW Payment Attempt is ALWAYS a new createTransaction()
 * call producing a NEW provider transaction — an adapter never
 * "resumes" a terminal provider-side transaction by re-issuing the
 * SAME provider_reference. Where the provider supports its own native
 * idempotency key on the create call, the adapter passes one derived
 * deterministically from payments.idempotency_key — never a fresh
 * random value per retry of the SAME internal request.
 */
interface PaymentProviderAdapter
{
    public function providerCode(): string;

    public function createTransaction(Payment $payment): ProviderTransactionResult;

    public function verifyCallback(Request $request): VerifiedCallbackResult;

    public function normalizeStatus(string $providerStatus): string;

    public function normalizeExpiration(mixed $providerData): ?\DateTimeImmutable;

    /**
     * @return array<string>
     */
    public function supportedCurrencies(): array;

    public function supportsRefundCall(): bool;
}
