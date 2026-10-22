<?php

namespace Database\Seeders;

use App\Enums\ScopeType;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Rbac\PrincipalService;
use Illuminate\Database\Seeder;

/**
 * IMP-009 — seeds the bounded non-human Payment identities
 * (docs/implementation/IMP-009-payment-hub.md "System Principal").
 *
 * integration_principals (webhook-attributed actions, one per external
 * integration): payment.tripay-webhook, payment.xendit-webhook,
 * payment.stripe-webhook. system_principals (scheduler/cron-attributed
 * actions, one per distinct background-job identity):
 * scheduler.payment-expiry-sweep, scheduler.payment-status-poll,
 * system.payment-outcome-consequence (the Donation-transition
 * invocation — deliberately DISTINCT from the webhook's own Integration
 * Principal, so "a webhook was received" and "a Donation state
 * transition was applied" are never conflated in the audit trail).
 *
 * Each holds NO payment.* permission grants: webhook handlers, the
 * expiry sweep, and the consequence invocation execute their domain
 * services directly (thin per-row drivers that never consult Policies),
 * so payment.view/payment.manual_transfer.verify authority is not
 * required for execution and is deliberately not granted (least
 * privilege — mirroring DonationSystemPrincipalSeeder's own
 * IMP008-REVIEW-10 disposition).
 *
 * Idempotent (safe to re-run): catalog rows via firstOrCreate,
 * PrincipalService::forSystem()/forIntegration() are themselves
 * idempotent, and the role/assignment steps each check for an existing
 * row first. Grants are direct Eloquent inserts — the same
 * "internal-setup bypass... never an exposed runtime path" carve-out
 * CmsSystemPrincipalSeeder already documents and relies on, with
 * assigned_by_principal_id null (system-seeded, no Human grantor).
 */
class PaymentSystemPrincipalSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const INTEGRATION_PRINCIPALS = [
        'payment.tripay-webhook' => 'IMP-009 Payment — Tripay callback attribution.',
        'payment.xendit-webhook' => 'IMP-009 Payment — Xendit webhook attribution.',
        'payment.stripe-webhook' => 'IMP-009 Payment — Stripe webhook attribution.',
    ];

    /**
     * @var array<string, array{description: string, role_code: string, role_name: string}>
     */
    private const SYSTEM_PRINCIPALS = [
        'scheduler.payment-expiry-sweep' => [
            'description' => 'IMP-009 Payment — expires pending payments (payment:expire-pending).',
            'role_code' => 'payment_expiry_sweeper',
            'role_name' => 'Payment Expiry Sweeper',
        ],
        'scheduler.payment-status-poll' => [
            'description' => 'IMP-009 Payment — provider status-poll fallback identity.',
            'role_code' => 'payment_status_poller',
            'role_name' => 'Payment Status Poller',
        ],
        'system.payment-outcome-consequence' => [
            'description' => 'IMP-009 Payment — invokes the Donation transition surface on terminal Payment outcomes.',
            'role_code' => 'payment_outcome_consequence',
            'role_name' => 'Payment Outcome Consequence',
        ],
    ];

    public function run(): void
    {
        $principals = app(PrincipalService::class);

        foreach (self::INTEGRATION_PRINCIPALS as $code => $description) {
            $catalog = IntegrationPrincipal::firstOrCreate(['code' => $code], ['description' => $description]);
            $principal = $principals->forIntegration($catalog);

            $role = Role::firstOrCreate(
                ['code' => "integration_{$code}"],
                ['name' => "Integration {$code}", 'description' => $description, 'is_system' => true],
            );

            $this->assignRole($principal->id, $role->id);
        }

        foreach (self::SYSTEM_PRINCIPALS as $code => $definition) {
            $catalog = SystemPrincipal::firstOrCreate(['code' => $code], ['description' => $definition['description']]);
            $principal = $principals->forSystem($catalog);

            $role = Role::firstOrCreate(
                ['code' => $definition['role_code']],
                ['name' => $definition['role_name'], 'description' => $definition['description'], 'is_system' => true],
            );

            $this->assignRole($principal->id, $role->id);
        }
    }

    private function assignRole(int $principalId, int $roleId): void
    {
        $alreadyAssigned = PrincipalRoleAssignment::where('principal_id', $principalId)
            ->where('role_id', $roleId)
            ->where('scope_type', ScopeType::Organization->value)
            ->whereNull('scope_id')
            ->whereNull('ends_at')
            ->exists();

        if (! $alreadyAssigned) {
            PrincipalRoleAssignment::create([
                'principal_id' => $principalId,
                'role_id' => $roleId,
                'scope_type' => ScopeType::Organization->value,
                'scope_id' => null,
                'starts_at' => now(),
                'assigned_by_principal_id' => null,
            ]);
        }
    }
}
