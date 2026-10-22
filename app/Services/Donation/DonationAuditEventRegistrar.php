<?php

namespace App\Services\Donation;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventRegistry;

/**
 * IMP-008 — registers the canonical donation.* events
 * (docs/implementation/IMP-008-donation.md "Audit Requirements", BR-14)
 * into the locked IMP-004 AuditEventRegistry, mirroring
 * CampaignAuditEventRegistrar exactly. All NonCritical, persistence
 * strategy null, visibility GENERAL, scope ORGANIZATION,
 * subjectIsFinancialReference true on every event (all carry
 * amount/currency context — AUDIT_READ_FINANCIAL_REFERENCE gates reads).
 *
 * donation.created's guest case is FINAL / LOCKED per HD-IMP008-06 /
 * ADR-002: actorKinds [Human, Unauthenticated] with the fixed registered
 * execution_context "http:donation:guest_created" (NULL for Human-actor
 * rows, the fixed constant for Unauthenticated-actor rows — see
 * AuditWriter::resolveActor). No other donation.* event uses the widened
 * Category 2 scope; no new AuditActorKind value is added.
 *
 * Boolean flags (is_anonymous, is_guest) are encoded as int 0/1 in the
 * metadata payload: the registry's allow-list type system supports only
 * int|string|array (see AuditWriter::assertValueShape) — widening it
 * would modify the locked IMP-004 contract, so the encoding stays here.
 */
final class DonationAuditEventRegistrar
{
    public const GUEST_CREATED_EXECUTION_CONTEXT = 'http:donation:guest_created';

    public function register(AuditEventRegistry $registry): void
    {
        $NC = AuditCriticality::NonCritical;
        $GENERAL = AuditVisibilityClass::General;
        $ORG = ScopeType::Organization;
        $H = [AuditActorKind::Human];
        $S = [AuditActorKind::System];

        $definitions = [
            new AuditEventDefinition('donation.created', 1, $NC, null, $GENERAL, 'donation', false, [
                'campaign_id' => 'int', 'amount_minor' => 'int', 'currency' => 'string',
                'is_anonymous' => 'int', 'is_guest' => 'int',
            ], [AuditActorKind::Human, AuditActorKind::Unauthenticated], self::GUEST_CREATED_EXECUTION_CONTEXT, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.succeeded', 1, $NC, null, $GENERAL, 'donation', false, [
                'donation_ulid' => 'string',
            ], $S, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.failed', 1, $NC, null, $GENERAL, 'donation', false, [
                'donation_ulid' => 'string',
            ], $S, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.expired', 1, $NC, null, $GENERAL, 'donation', false, [
                'donation_ulid' => 'string',
            ], $S, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.cancelled', 1, $NC, null, $GENERAL, 'donation', false, [
                'donation_ulid' => 'string', 'cancelled_by_principal_id' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.recurring_plan.created', 1, $NC, null, $GENERAL, 'donation_recurring_plan', false, [
                'campaign_id' => 'int', 'amount_minor' => 'int', 'currency' => 'string',
                'frequency' => 'string', 'is_anonymous' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.recurring_plan.paused', 1, $NC, null, $GENERAL, 'donation_recurring_plan', false, [
                'donation_recurring_plan_ulid' => 'string', 'paused_by_principal_id' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.recurring_plan.resumed', 1, $NC, null, $GENERAL, 'donation_recurring_plan', false, [
                'donation_recurring_plan_ulid' => 'string',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),

            new AuditEventDefinition('donation.recurring_plan.cancelled', 1, $NC, null, $GENERAL, 'donation_recurring_plan', false, [
                'donation_recurring_plan_ulid' => 'string', 'cancelled_by_principal_id' => 'int',
            ], $H, null, false, $ORG, subjectIsFinancialReference: true),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }
}
