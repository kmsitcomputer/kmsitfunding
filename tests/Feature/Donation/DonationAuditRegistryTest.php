<?php

namespace Tests\Feature\Donation;

use App\Enums\AuditActorKind;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Donation\DonationAuditEventRegistrar;
use App\Services\Donation\DonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — audit contract (docs/implementation/IMP-008-donation.md
 * "Audit Requirements" / BR-14, HD-IMP008-06, ADR-002): every
 * donation.* event registered NonCritical/GENERAL/ORGANIZATION with
 * financial reference; donation.created admits Human + Unauthenticated
 * with the fixed guest execution context; no other event uses the
 * widened Category 2 scope; guest rows carry NULL principal.
 */
class DonationAuditRegistryTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private const ALL_DONATION_EVENTS = [
        'donation.created',
        'donation.succeeded',
        'donation.failed',
        'donation.expired',
        'donation.cancelled',
        'donation.recurring_plan.created',
        'donation.recurring_plan.paused',
        'donation.recurring_plan.resumed',
        'donation.recurring_plan.cancelled',
    ];

    public function test_every_donation_event_is_registered_noncritical_general_organization_financial_reference(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (self::ALL_DONATION_EVENTS as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertNotNull($definition, "{$eventType} must be registered.");
            $this->assertFalse($definition->isCritical(), "{$eventType} must be NonCritical.");
            $this->assertSame('general', $definition->visibilityClass->value, "{$eventType} must be GENERAL visibility.");
            $this->assertSame('ORGANIZATION', $definition->scopeType->value, "{$eventType} must be ORGANIZATION scope.");
            $this->assertTrue($definition->subjectIsFinancialReference, "{$eventType} must be flagged as a financial reference.");
            $this->assertNull($definition->persistenceStrategy, "{$eventType} must declare no persistence strategy.");
            $this->assertFalse($definition->requiresElevatedAssuranceToRead, "{$eventType} must not require elevated assurance.");
        }
    }

    public function test_only_donation_created_admits_the_unauthenticated_actor_kind(): void
    {
        $registry = app(AuditEventRegistry::class);

        $created = $registry->find('donation.created', 1);

        $this->assertContains(AuditActorKind::Human, $created->actorKinds);
        $this->assertContains(AuditActorKind::Unauthenticated, $created->actorKinds);
        $this->assertSame(
            DonationAuditEventRegistrar::GUEST_CREATED_EXECUTION_CONTEXT,
            $created->executionContext
        );
        $this->assertSame('http:donation:guest_created', $created->executionContext);

        foreach (array_diff(self::ALL_DONATION_EVENTS, ['donation.created']) as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertNotContains(
                AuditActorKind::Unauthenticated,
                $definition->actorKinds,
                "{$eventType} must not admit the Unauthenticated actor kind (ADR-002 Category 2 is donation.created-only)."
            );
        }
    }

    public function test_guest_donation_created_row_carries_null_principal_and_fixed_context(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ], null, 'audit-guest-'.uniqid());

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.created',
            'subject_id' => $donation->id,
            'actor_principal_kind' => 'unauthenticated',
            'actor_principal_id' => null,
            'execution_context' => 'http:donation:guest_created',
        ]);
    }

    public function test_authenticated_donation_created_row_carries_human_actor_and_null_context(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 2500,
            'currency' => 'USD',
        ], $actor, 'audit-auth-'.uniqid());

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.created',
            'subject_id' => $donation->id,
            'actor_principal_kind' => 'human',
            'actor_principal_id' => $actor->id,
            'execution_context' => null,
        ]);
    }

    public function test_no_new_audit_actor_kind_was_introduced(): void
    {
        $this->assertSame(
            ['human', 'system', 'integration', 'unauthenticated', 'pre_principal_system'],
            array_map(fn (AuditActorKind $kind) => $kind->value, AuditActorKind::cases())
        );
    }
}
