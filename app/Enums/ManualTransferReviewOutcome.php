<?php

namespace App\Enums;

/**
 * IMP-009 — manual-transfer review decisions
 * (docs/implementation/IMP-009-payment-hub.md "Manual Transfer" /
 * HD-IMP009-07, FINAL / LOCKED). AMOUNT_MISMATCH_HOLD is a third,
 * escalated outcome — never an automatic APPROVED/REJECTED — recorded
 * at the evidence layer only, leaving payments.status PENDING.
 */
enum ManualTransferReviewOutcome: string
{
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case AmountMismatchHold = 'AMOUNT_MISMATCH_HOLD';
}
