<?php

namespace Tests\Feature\Payment;

use App\Enums\AuditActorKind;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Payment\PaymentAuditEventRegistrar;
use App\Services\Payment\PaymentAuditLogger;
use App\Services\Payment\PaymentCreationService;
use App\Services\Payment\PaymentTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — audit (docs/implementation/IMP-009-payment-hub.md "Audit",
 * HD-IMP009-12 / ADR-003): every payment-domain event registered with
 * its documented criticality; CRITICAL/MUTATION_ATOMIC fail-closed
 * (forced audit-append failure rolls the mutation back); only the two
 * ADR-003 guest events admit Unauthenticated with their fixed
 * execution contexts; no new AuditActorKind introduced.
 */
class PaymentAuditTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    private const CRITICAL_EVENTS = [
        'payment.succeeded',
        'payment.failed',
        'payment.donation_transition_rejected',
        'manual_transfer.approved',
        'manual_transfer.rejected',
        'manual_transfer.amount_mismatch_held',
        'provider_config.credential_changed',
    ];

    private const NON_CRITICAL_EVENTS = [
        'payment.attempt_created',
        'payment.expired',
        'payment.cancelled',
        'webhook.received',
        'webhook.verification_failed',
        'manual_transfer.evidence_submitted',
    ];

    public function test_every_payment_event_is_registered_with_its_documented_criticality(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (self::CRITICAL_EVENTS as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertNotNull($definition, "{$eventType} must be registered.");
            $this->assertTrue($definition->isCritical(), "{$eventType} must be CRITICAL.");
            $this->assertSame('mutation_atomic', $definition->persistenceStrategy->value, "{$eventType} must be MUTATION_ATOMIC.");
            $this->assertTrue($definition->subjectIsFinancialReference || $eventType === 'provider_config.credential_changed');
        }

        foreach (self::NON_CRITICAL_EVENTS as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertNotNull($definition, "{$eventType} must be registered.");
            $this->assertFalse($definition->isCritical(), "{$eventType} must be NonCritical.");
            $this->assertNull($definition->persistenceStrategy, "{$eventType} must declare no persistence strategy.");
        }
    }

    public function test_only_the_two_adr003_events_admit_the_unauthenticated_actor_kind(): void
    {
        $registry = app(AuditEventRegistry::class);

        $created = $registry->find('payment.attempt_created', 1);
        $this->assertContains(AuditActorKind::Human, $created->actorKinds);
        $this->assertContains(AuditActorKind::Unauthenticated, $created->actorKinds);
        $this->assertSame(
            PaymentAuditEventRegistrar::GUEST_ATTEMPT_CREATED_EXECUTION_CONTEXT,
            $created->executionContext
        );
        $this->assertSame('http:payment:guest_attempt_created', $created->executionContext);

        $submitted = $registry->find('manual_transfer.evidence_submitted', 1);
        $this->assertContains(AuditActorKind::Human, $submitted->actorKinds);
        $this->assertContains(AuditActorKind::Unauthenticated, $submitted->actorKinds);
        $this->assertSame('http:payment:guest_manual_transfer_evidence_submitted', $submitted->executionContext);

        foreach (array_merge(self::CRITICAL_EVENTS, array_diff(self::NON_CRITICAL_EVENTS, [
            'payment.attempt_created',
            'manual_transfer.evidence_submitted',
        ])) as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertNotContains(
                AuditActorKind::Unauthenticated,
                $definition->actorKinds,
                "{$eventType} must not admit the Unauthenticated actor kind (ADR-003 is two-event-only)."
            );
        }
    }

    public function test_succeeded_and_failed_admit_only_integration_and_system_actors(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (['payment.succeeded', 'payment.failed'] as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertEqualsCanonicalizing(
                [AuditActorKind::Integration, AuditActorKind::System],
                $definition->actorKinds,
                "{$eventType} admits exactly Integration + System."
            );
        }
    }

    public function test_no_payment_allow_list_contains_a_secret_or_card_key(): void
    {
        $registry = app(AuditEventRegistry::class);

        $allPaymentEvents = array_merge(self::CRITICAL_EVENTS, self::NON_CRITICAL_EVENTS);

        foreach ($registry->all() as $definition) {
            if (! in_array($definition->eventType, $allPaymentEvents, true)) {
                continue;
            }

            foreach (array_keys($definition->metadataAllowList) as $key) {
                $this->assertStringNotContainsStringIgnoringCase('secret', $key);
                $this->assertStringNotContainsStringIgnoringCase('card', $key);
                $this->assertStringNotContainsStringIgnoringCase('token', $key);
                $this->assertStringNotContainsStringIgnoringCase('password', $key);
            }
        }
    }

    public function test_forced_critical_audit_failure_rolls_back_the_payment_mutation(): void
    {
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'failclosed-'.uniqid()
        );

        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'test.payment.failclosed'],
            ['description' => 'Test fail-closed identity.']
        );
        $systemActor = app(PrincipalService::class)->forSystem($catalog);

        // Force the CRITICAL payment.succeeded append to fail — the
        // Payment status write must roll back with it (fail-closed,
        // Q26, MUTATION_ATOMIC).
        $this->app->bind(PaymentAuditLogger::class, fn () => new class extends PaymentAuditLogger
        {
            public function __construct() {}

            public function recordSucceeded(int $paymentId, array $metadata, Principal $actor): void
            {
                throw new \RuntimeException('forced critical-audit failure');
            }
        });

        try {
            app(PaymentTransitionService::class)->markSucceeded($payment, $systemActor);
            $this->fail('A forced CRITICAL audit failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced critical-audit failure', $e->getMessage());
        }

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->succeeded_at);
    }

    public function test_webhook_and_sweep_transitions_carry_distinct_non_human_attribution(): void
    {
        $integration = IntegrationPrincipal::firstOrCreate(
            ['code' => 'payment.tripay-webhook'],
            ['description' => 'Test webhook attribution.']
        );
        $system = SystemPrincipal::firstOrCreate(
            ['code' => 'system.payment-outcome-consequence'],
            ['description' => 'Test consequence attribution.']
        );

        $integrationPrincipal = app(PrincipalService::class)->forIntegration($integration);
        $systemPrincipal = app(PrincipalService::class)->forSystem($system);

        $this->assertSame('integration', $integrationPrincipal->principal_kind->value);
        $this->assertSame('system', $systemPrincipal->principal_kind->value);
        $this->assertNotSame($integrationPrincipal->id, $systemPrincipal->id);
    }

    public function test_no_new_audit_actor_kind_was_introduced(): void
    {
        $this->assertSame(
            ['human', 'system', 'integration', 'unauthenticated', 'pre_principal_system'],
            array_map(fn (AuditActorKind $kind) => $kind->value, AuditActorKind::cases())
        );
    }
}
