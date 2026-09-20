<?php

namespace App\Services\Payment;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditPersistenceStrategy;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventRegistry;

/**
 * IMP-009 — registers the canonical payment, manual_transfer,
 * webhook, and provider_config domain events (docs/implementation/
 * IMP-009-payment-hub.md "Audit") into the locked IMP-004
 * AuditEventRegistry, mirroring DonationAuditEventRegistrar exactly —
 * AuditEventRegistry's own file is never touched.
 *
 * payment.succeeded/failed, payment.donation_transition_rejected,
 * manual_transfer.approved/rejected/amount_mismatch_held and
 * provider_config.credential_changed are CRITICAL/MUTATION_ATOMIC: an
 * append failure rolls the accompanying mutation back (fail-closed,
 * Q26). webhook.verification_failed is NonCritical/DENIAL_DURABLE: a
 * rejection is security-relevant evidence sequenced after the denying
 * path rolls back, never itself a mutation to roll back. All remaining
 * events are NonCritical with null strategy.
 *
 * MANUAL RECONCILIATION NOTE (spec vs locked IMP-004 registry):
 * the specification classifies webhook.verification_failed as
 * "NonCritical / DENIAL_DURABLE". The LOCKED AuditEventRegistry
 * invariant (unmodifiable — RECON §6) permits a persistence strategy
 * ONLY on CRITICAL events (DenialDurable exists in-repo solely on the
 * CRITICAL security.authorization.denied). A NonCritical + DenialDurable
 * pair is therefore unregistrable. This registrar records the event as
 * NonCritical with null strategy — preserving every observable spec
 * behavior: the write is still sequenced strictly after the denying
 * path with no mutation to roll back (the handler's recordRejection
 * runs outside any business transaction), the same visibility/actor/
 * payload contract holds, and a persistence failure is reported via
 * the ordinary error log rather than converting an invalid-signature
 * flood into retry-amplifying 500s. No business semantic changes;
 * logged as a reconciled deviation in EVIDENCE.md.
 *
 * Guest-actor events (payment.attempt_created,
 * manual_transfer.evidence_submitted) admit Human + Unauthenticated with
 * their own fixed registered execution contexts per ADR-003
 * (HD-IMP009-12, FINAL / LOCKED) — never any other IMP-009 event, never
 * a generic broadening of ADR-002.
 *
 * Metadata allow-lists carry no secret/credential/card key — enforced by
 * the registry's own HARD_PROHIBITED_METADATA_KEYS at registration time.
 */
final class PaymentAuditEventRegistrar
{
    public const GUEST_ATTEMPT_CREATED_EXECUTION_CONTEXT = 'http:payment:guest_attempt_created';

    public const GUEST_EVIDENCE_SUBMITTED_EXECUTION_CONTEXT = 'http:payment:guest_manual_transfer_evidence_submitted';

    public function register(AuditEventRegistry $registry): void
    {
        $C = AuditCriticality::Critical;
        $NC = AuditCriticality::NonCritical;
        $MA = AuditPersistenceStrategy::MutationAtomic;
        $DD = AuditPersistenceStrategy::DenialDurable;
        $GENERAL = AuditVisibilityClass::General;
        $ORG = ScopeType::Organization;
        $GP = ScopeType::GlobalPlatform;
        $H = [AuditActorKind::Human];
        $HU = [AuditActorKind::Human, AuditActorKind::Unauthenticated];
        $IS = [AuditActorKind::Integration, AuditActorKind::System];
        $S = [AuditActorKind::System];
        $I = [AuditActorKind::Integration];

        $definitions = [
            new AuditEventDefinition('payment.attempt_created', 1, $NC, null, $GENERAL, 'payment', false, [
                'donation_ulid' => 'string', 'provider' => 'string',
                'amount_minor' => 'int', 'currency' => 'string', 'idempotency_key' => 'string',
            ], $HU, self::GUEST_ATTEMPT_CREATED_EXECUTION_CONTEXT, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('payment.succeeded', 1, $C, $MA, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'donation_ulid' => 'string', 'provider' => 'string',
                'provider_reference' => 'string', 'amount_minor' => 'int', 'currency' => 'string',
            ], $IS, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('payment.failed', 1, $C, $MA, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'donation_ulid' => 'string', 'provider' => 'string',
                'provider_reference' => 'string', 'amount_minor' => 'int', 'currency' => 'string',
            ], $IS, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('payment.expired', 1, $NC, null, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string',
            ], $S, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('payment.cancelled', 1, $NC, null, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'cancelled_by_principal_id' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('payment.donation_transition_rejected', 1, $C, $MA, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'donation_ulid' => 'string',
                'attempted_outcome' => 'string', 'donation_status_observed' => 'string',
            ], $S, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('webhook.received', 1, $NC, null, $GENERAL, 'payment_provider_event', false, [
                'provider' => 'string', 'processing_result' => 'string',
            ], $I, null, false, $ORG),

            new AuditEventDefinition('webhook.verification_failed', 1, $NC, null, $GENERAL, 'payment_provider_event', true, [
                'provider' => 'string', 'reason' => 'string',
            ], $I, null, false, $ORG),

            new AuditEventDefinition('manual_transfer.evidence_submitted', 1, $NC, null, $GENERAL, 'manual_transfer_evidence', false, [
                'payment_ulid' => 'string', 'manual_transfer_evidence_ulid' => 'string',
            ], $HU, self::GUEST_EVIDENCE_SUBMITTED_EXECUTION_CONTEXT, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('manual_transfer.approved', 1, $C, $MA, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'verified_by_principal_id' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('manual_transfer.rejected', 1, $C, $MA, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'verified_by_principal_id' => 'int', 'review_notes' => 'string',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('manual_transfer.amount_mismatch_held', 1, $C, $MA, $GENERAL, 'payment', false, [
                'payment_ulid' => 'string', 'verified_by_principal_id' => 'int',
                'amount_minor' => 'int', 'declared_amount_minor' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('provider_config.credential_changed', 1, $C, $MA, $GENERAL, 'payment_provider_credential', true, [
                'provider' => 'string', 'mode' => 'string', 'is_enabled' => 'int',
            ], $H, null, false, $GP),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }
}
