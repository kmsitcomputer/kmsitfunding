<?php

namespace App\Enums;

/**
 * IMP-009 — canonical provider-neutral Payment state
 * (docs/implementation/IMP-009-payment-hub.md "State Machines"). A provider
 * status name (Tripay's PAID, Xendit's SUCCEEDED, Stripe's succeeded) is
 * NEVER stored in payments.status — every adapter normalizes through its
 * own allow-list before domain logic runs (BR-4).
 */
enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case RequiresAction = 'REQUIRES_ACTION';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::RequiresAction;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Expired, self::Cancelled], true);
    }
}
