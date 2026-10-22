<?php

namespace App\Enums;

/**
 * IMP-009 — closed provider allow-list
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model" / BR-3).
 * Midtrans is never a valid value without an approved ACR
 * (MASTER-REQUIREMENTS.md §9).
 */
enum PaymentProvider: string
{
    case ManualTransfer = 'manual_transfer';
    case Tripay = 'tripay';
    case Xendit = 'xendit';
    case Stripe = 'stripe';
}
