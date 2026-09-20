<?php

namespace App\Services\Payment;

use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Support\Money\CurrencyMinorUnits;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\Money;
use App\Support\Money\ProviderAmountConverter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-009 — Payment (Attempt) creation (docs/implementation/
 * IMP-009-payment-hub.md "Domain Model" / "Idempotency" / "Concurrency",
 * BR-1/BR-2/BR-3/BR-11/BR-19, HD-IMP009-01/03).
 *
 * Sequencing per "Failure Semantics": (1) validate the
 * client-provided Idempotency-Key FIRST (before any Donation lookup —
 * BR-11, mirroring Donation's own BR-12 ordering); (2) lock the
 * Donation row FIRST (SELECT ... FOR UPDATE) before evaluating
 * whether an ACTIVE attempt already exists (HD-IMP009-01 lock
 * discipline, fixed Donation-then-Payment lock order); (3) insert the
 * payments row in transient PENDING with provider_reference NULL and
 * commit; (4) call the provider adapter's createTransaction() OUTSIDE
 * that transaction; (5) on success, a second transaction populates
 * provider_reference/instructions_payload/expires_at; (6) on failure
 * the Payment moves to FAILED (PROVIDER_CREATE_FAILED) in its own
 * transaction — never left indefinitely PENDING with no provider
 * reference and no path to resolution.
 *
 * Idempotency: the UNIQUE constraint is the deterministic backstop — a
 * caught violation is handled as a replay (payload match -> return
 * existing, no second provider call; payload differ -> typed conflict),
 * never a check-then-insert race. The key namespace is deliberately
 * SEPARATE from donations.idempotency_key.
 *
 * F-01: a provider createTransaction() call is issued ONLY when THIS
 * call inserted the Payment row. An idempotent replay (same key +
 * matching payload, whether resolved inside the creation transaction
 * or through the unique-violation catch path) returns the EXISTING
 * row with NO second provider call — never an orphan provider
 * transaction.
 *
 * Money: amount_minor/currency are copied from and validated EQUAL to
 * the owning Donation's own values via Money::ofMinorUnits() (BR-2) —
 * never independently entered.
 *
 * $actor NULL means guest (mirroring DonationService): ownership is
 * not checked for guests, but Donation PENDING + one-ACTIVE +
 * idempotency gates apply identically. No guest bearer-token/resume
 * mechanism is created here or anywhere (HD-IMP009-04).
 */
class PaymentCreationService
{
    public function __construct(
        private readonly PaymentAdapterFactory $adapters,
        private readonly PaymentAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{provider:string, channel?:?string}  $payload
     */
    public function create(Donation $donation, array $payload, ?Principal $actor, mixed $idempotencyKey): Payment
    {
        $key = self::normalizeIdempotencyKey($idempotencyKey);

        $provider = $payload['provider'] ?? null;

        if (! is_string($provider) || $provider === '') {
            throw new PaymentValidationException('invalid_provider', 'A provider is required to create a payment.');
        }

        $adapter = $this->adapters->for($provider);

        try {
            $created = null;

            $payment = DB::transaction(function () use ($donation, $provider, $payload, $actor, $key, $adapter, &$created) {
                $lockedDonation = Donation::query()->whereKey($donation->id)->lockForUpdate()->firstOrFail();

                // Idempotent replay takes precedence over the
                // one-ACTIVE-attempt guard: a retried request (same key +
                // matching payload) returns the EXISTING row — it is the
                // same donor intent, not a second concurrent attempt
                // (INV-1). Same key + differing payload is a typed
                // conflict. The UNIQUE constraint remains the
                // deterministic backstop for cross-donation races via
                // the catch path below.
                $existingByKey = Payment::query()->where('idempotency_key', $key)->lockForUpdate()->first();

                if ($existingByKey !== null) {
                    if ($existingByKey->donation_id !== $lockedDonation->id
                        || $existingByKey->provider !== $provider
                        || $existingByKey->amount_minor !== $lockedDonation->amount_minor
                        || $existingByKey->currency !== $lockedDonation->currency) {
                        throw new PaymentTransitionConflictException(
                            'idempotency_conflict',
                            'This idempotency key was already used for a materially different payment.'
                        );
                    }

                    // F-01: replay of THIS key — the row was created by
                    // an earlier call, so this call provisions nothing.
                    $created = false;

                    return $existingByKey;
                }

                if ($lockedDonation->status !== 'PENDING') {
                    throw new PaymentValidationException(
                        'donation_not_pending',
                        "Payment may only be created against a PENDING Donation (observed {$lockedDonation->status})."
                    );
                }

                if ($actor !== null && $lockedDonation->donor_principal_id !== $actor->id) {
                    throw new PaymentValidationException(
                        'donation_not_owned',
                        'A payment may only be created for your own donation.'
                    );
                }

                $this->assertValidMoney($lockedDonation->amount_minor, $lockedDonation->currency);

                // F-07: provider + currency support is validated BEFORE
                // the Payment row is created and before any provider API
                // call. An unsupported pairing is a typed rejection with
                // NO row and NO provider call — never PENDING-then-
                // FAILED. The representability conversion below doubles
                // as the HD-IMP009-13 pre-row amount-unit gate: a
                // canonical value the provider unit cannot represent
                // exactly is rejected here, not rounded downstream.
                // manual_transfer is exempt from the static-list gate:
                // its supported list is operational config (seeded bank
                // accounts), not a static provider capability — an empty
                // catalog must not block creation (baseline behavior:
                // instructions render with an empty account list).
                // Currency validity for manual_transfer is still enforced
                // by the converter gate below (registered currency only).
                if ($provider !== 'manual_transfer' && ! in_array($lockedDonation->currency, $adapter->supportedCurrencies(), true)) {
                    throw new PaymentValidationException(
                        'provider_currency_unsupported',
                        "Provider '{$provider}' does not support currency '{$lockedDonation->currency}'."
                    );
                }

                ProviderAmountConverter::toProviderUnits($lockedDonation->amount_minor, $lockedDonation->currency, $provider);

                if (Payment::query()->where('donation_id', $lockedDonation->id)
                    ->whereIn('status', ['PENDING', 'REQUIRES_ACTION'])
                    ->lockForUpdate()
                    ->exists()) {
                    throw new PaymentTransitionConflictException(
                        'active_attempt_exists',
                        'An active payment attempt already exists for this donation.'
                    );
                }

                $payment = new Payment;
                $payment->forceFill([
                    'donation_id' => $lockedDonation->id,
                    'provider' => $provider,
                    'channel' => $payload['channel'] ?? null,
                    'amount_minor' => $lockedDonation->amount_minor,
                    'currency' => $lockedDonation->currency,
                    'status' => 'PENDING',
                    'idempotency_key' => $key,
                ]);
                $payment->save();

                // F-01: THIS call inserted the row — only this path
                // authorizes a provider provisioning operation.
                $created = true;

                $this->auditLogger->recordAttemptCreated($payment->id, [
                    'donation_ulid' => $lockedDonation->ulid,
                    'provider' => $provider,
                    'amount_minor' => $lockedDonation->amount_minor,
                    'currency' => $lockedDonation->currency,
                    'idempotency_key' => $key,
                ], $actor);

                return $payment->fresh();
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $donationRow = Donation::query()->whereKey($donation->id)->first();

            // F-15: TWO distinct unique constraints guard creation —
            // UNIQUE(idempotency_key) and UNIQUE(donation_id,
            // active_slot). A violation with NO row carrying THIS key
            // came from the one-active-attempt backstop, not from an
            // idempotent replay: report the approved typed conflict
            // active_attempt_exists, never idempotency_unresolved.
            if ($donationRow !== null && ! Payment::query()->where('idempotency_key', $key)->exists()) {
                $activeSibling = Payment::query()->where('donation_id', $donationRow->id)
                    ->whereIn('status', ['PENDING', 'REQUIRES_ACTION'])
                    ->first();

                if ($activeSibling !== null) {
                    throw new PaymentTransitionConflictException(
                        'active_attempt_exists',
                        'An active payment attempt already exists for this donation.'
                    );
                }
            }

            return $this->resolveIdempotentReplay($key, $donationRow, $provider, $payload['channel'] ?? null);
        }

        // F-01: the in-transaction replay path resolved an EXISTING
        // row (created by an earlier call) — return it WITHOUT another
        // provider createTransaction() call. Only a row THIS call
        // inserted is provisioned, per the safe contract: one canonical
        // Payment, one provider provisioning operation.
        if ($created !== true) {
            return $payment->fresh();
        }

        $this->provisionProviderTransaction($payment);

        return $payment->fresh();
    }

    public static function normalizeIdempotencyKey(mixed $key): string
    {
        if (! is_string($key)) {
            throw new PaymentValidationException('missing_idempotency_key', 'An Idempotency-Key header is required to create a payment.');
        }

        $normalized = trim($key);

        if ($normalized === '' || strlen($normalized) > 128 || preg_match('/[^\x20-\x7E]/', $normalized) !== 0) {
            throw new PaymentValidationException(
                'invalid_idempotency_key',
                'The Idempotency-Key must be printable ASCII, 1-128 characters.'
            );
        }

        return $normalized;
    }

    private function provisionProviderTransaction(Payment $payment): void
    {
        $adapter = $this->adapters->for($payment->provider);

        try {
            $result = $adapter->createTransaction($payment->fresh());
        } catch (\Throwable) {
            DB::transaction(function () use ($payment) {
                $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'PENDING' && $locked->provider_reference === null) {
                    $locked->forceFill([
                        'status' => 'FAILED',
                        'failed_at' => now(),
                        'failure_reason' => 'PROVIDER_CREATE_FAILED',
                    ])->save();
                }
            });

            return;
        }

        DB::transaction(function () use ($payment, $result) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'PENDING' && $locked->provider_reference === null) {
                $locked->forceFill(array_filter([
                    'provider_reference' => $result->providerReference,
                    'channel' => $result->channel ?? $locked->channel,
                    'instructions_payload' => $result->instructionsPayload,
                    'expires_at' => $result->expiresAt,
                ], fn ($value) => $value !== null))->save();
            }
        });
    }

    private function assertValidMoney(mixed $amount, mixed $currency): void
    {
        if (! is_string($currency) || ! CurrencyMinorUnits::isRegistered($currency)) {
            throw new UnknownCurrencyException(is_string($currency) ? $currency : '');
        }

        Money::ofMinorUnits(0, $currency);

        if (! is_int($amount) || $amount <= 0) {
            throw new PaymentValidationException('invalid_amount', 'amount_minor must be a positive integer.');
        }
    }

    private function resolveIdempotentReplay(string $key, ?Donation $donation, string $provider, ?string $channel): Payment
    {
        $existing = Payment::query()->where('idempotency_key', $key)->first();

        if ($existing === null) {
            throw new PaymentTransitionConflictException(
                'idempotency_unresolved',
                'The idempotency key collided but no existing payment could be resolved.'
            );
        }

        // Replay semantics per "Idempotency": identical key + matching
        // payload (donation_id, provider, amount_minor, currency) returns
        // the existing row; identical key + differing payload is a typed
        // conflict. Amount/currency ride along with the Donation row
        // (BR-2 immutable copy), so they are compared through it.
        $payloadMatches = $donation !== null
            && $existing->donation_id === $donation->id
            && $existing->provider === $provider
            && $existing->amount_minor === $donation->amount_minor
            && $existing->currency === $donation->currency;

        if (! $payloadMatches) {
            throw new PaymentTransitionConflictException(
                'idempotency_conflict',
                'This idempotency key was already used for a materially different payment.'
            );
        }

        return $existing;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return in_array($driverCode, [1062, 19, 2067], true);
    }
}
