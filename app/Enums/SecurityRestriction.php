<?php

namespace App\Enums;

/**
 * Q24 — persistent Security Restriction. Never conflated with a transient
 * rate-limiter lockout (which is not stored on the User at all) and never
 * conflated with a business-approval status (Fundraiser/Partner/Beneficiary
 * approval, Financial approval, Campaign authority) — those are Business
 * Authority concepts owned by IMP-003+, not Identity concepts.
 */
enum SecurityRestriction: string
{
    case None = 'none';
    case Suspended = 'suspended';
}
