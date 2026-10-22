<?php

namespace App\Services\Payment;

use App\Models\Payment\Payment;

/**
 * IMP-009 R-01 — explicit creation outcome (docs/implementation/
 * IMP-009-payment-hub.md "Idempotency", HD-IMP009-04).
 *
 * The PaymentCreationService MUST tell its caller whether THIS call
 * inserted the Payment row (CREATED) or returned an EXISTING row
 * from an idempotent replay (IDEMPOTENT_REPLAY) — never inferred
 * from timestamps or another race-prone heuristic. Only CREATED may
 * establish new guest session possession; a replay MUST NOT grant a
 * fresh anonymous session possession of the existing Payment (the
 * Idempotency-Key is request deduplication, never a recovery
 * credential).
 */
final class PaymentCreationOutcome
{
    public function __construct(
        public readonly Payment $payment,
        public readonly bool $created,
    ) {}
}
