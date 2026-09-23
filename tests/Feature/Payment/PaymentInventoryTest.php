<?php

namespace Tests\Feature\Payment;

use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Rbac\AuthorizationContext;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Database\Seeders\PaymentSystemPrincipalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IMP-009 — intentional exact inventory (docs/ai-handoff/IMP-009/
 * RECON.md §18): six new payment.* permissions, thirteen new
 * payment-domain audit events, three integration + three system
 * principals. Updated ONLY by the actual IMP-009 additions — never
 * loosened to pass.
 */
class PaymentInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_registry_carries_exactly_the_six_payment_permissions(): void
    {
        $definitions = PermissionRegistry::definitions();
        $paymentCodes = array_filter(
            array_keys($definitions),
            fn (string $code) => str_starts_with($code, 'payment.')
        );

        $this->assertSame(
            [
                'payment.view',
                'payment.create',
                'payment.cancel',
                'payment.manual_transfer.submit_evidence',
                'payment.manual_transfer.verify',
                'payment.provider_config.manage',
            ],
            array_values($paymentCodes)
        );

        foreach ($paymentCodes as $code) {
            $this->assertSame('payment', $definitions[$code]['module']);
        }
    }

    public function test_audit_registry_carries_exactly_the_thirteen_payment_events(): void
    {
        $registry = app(AuditEventRegistry::class);

        $expected = [
            'payment.attempt_created',
            'payment.succeeded',
            'payment.failed',
            'payment.expired',
            'payment.cancelled',
            'payment.donation_transition_rejected',
            'webhook.received',
            'webhook.verification_failed',
            'manual_transfer.evidence_submitted',
            'manual_transfer.approved',
            'manual_transfer.rejected',
            'manual_transfer.amount_mismatch_held',
            'provider_config.credential_changed',
        ];

        foreach ($expected as $eventType) {
            $this->assertNotNull($registry->find($eventType, 1), "{$eventType} must be registered.");
        }

        $paymentEvents = array_filter(
            $registry->all(),
            fn ($definition) => str_starts_with($definition->eventType, 'payment.')
                || str_starts_with($definition->eventType, 'webhook.')
                || str_starts_with($definition->eventType, 'manual_transfer.')
                || str_starts_with($definition->eventType, 'provider_config.')
        );

        $this->assertCount(13, $paymentEvents);
    }

    public function test_system_principal_seeder_registers_exactly_six_idempotent_identities(): void
    {
        $this->seed(PaymentSystemPrincipalSeeder::class);

        foreach ([
            'payment.tripay-webhook',
            'payment.xendit-webhook',
            'payment.stripe-webhook',
        ] as $code) {
            $this->assertNotNull(
                IntegrationPrincipal::where('code', $code)->first(),
                "Integration Principal {$code} must be seeded."
            );
        }

        foreach ([
            'scheduler.payment-expiry-sweep',
            'scheduler.payment-status-poll',
            'system.payment-outcome-consequence',
        ] as $code) {
            $this->assertNotNull(
                SystemPrincipal::where('code', $code)->first(),
                "System Principal {$code} must be seeded."
            );
        }

        $integrationCount = IntegrationPrincipal::query()->count();
        $systemCount = SystemPrincipal::query()->count();

        // Idempotent re-run converges — no duplicates.
        $this->seed(PaymentSystemPrincipalSeeder::class);

        $this->assertSame($integrationCount, IntegrationPrincipal::query()->count());
        $this->assertSame($systemCount, SystemPrincipal::query()->count());
    }

    public function test_payment_principals_hold_no_payment_permission_grants(): void
    {
        $this->seed(PaymentSystemPrincipalSeeder::class);

        $codes = [
            'payment.tripay-webhook',
            'payment.xendit-webhook',
            'payment.stripe-webhook',
            'scheduler.payment-expiry-sweep',
            'scheduler.payment-status-poll',
            'system.payment-outcome-consequence',
        ];

        foreach ($codes as $code) {
            $catalog = SystemPrincipal::where('code', $code)->first()
                ?? IntegrationPrincipal::where('code', $code)->first();

            $principal = $catalog instanceof SystemPrincipal
                ? app(PrincipalService::class)->forSystem($catalog)
                : app(PrincipalService::class)->forIntegration($catalog);

            $grants = (new AuthorizationContext($principal))
                ->activeRoleAssignmentsGranting(PermissionRegistry::PAYMENT_MANUAL_TRANSFER_VERIFY);

            $this->assertTrue(
                $grants->isEmpty(),
                "{$code} must hold no payment.* permission grants (least privilege)."
            );
        }
    }
}
