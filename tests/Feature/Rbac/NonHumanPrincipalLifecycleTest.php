<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Models\User;
use App\Services\Identity\AssuranceService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * `IMP003-IMPL-M04` — System/Integration Principal deactivation must be
 * atomic: the catalog row's `deactivated_at` and the linked canonical
 * `principals` row's `disabled_at` transition together, in one transaction,
 * under authorization + ELEVATED assurance, or neither transitions at all.
 */
class NonHumanPrincipalLifecycleTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makePrincipal(): Principal
    {
        $user = User::create([
            'email' => 'nonhuman-lifecycle-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    public function test_system_deactivation_transitions_both_rows_atomically(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_1']);
        $principal = app(PrincipalService::class)->forSystem($systemRow);

        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);

        $systemRow->refresh();
        $principal->refresh();

        $this->assertNotNull($systemRow->deactivated_at);
        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_integration_deactivation_transitions_both_rows_atomically(): void
    {
        $actor = $this->makeAuthorizedActor();
        $integrationRow = IntegrationPrincipal::create(['code' => 'lifecycle_integration_1']);
        $principal = app(PrincipalService::class)->forIntegration($integrationRow);

        app(PrincipalService::class)->deactivateIntegration($actor, $integrationRow);

        $integrationRow->refresh();
        $principal->refresh();

        $this->assertNotNull($integrationRow->deactivated_at);
        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_unauthorized_actor_cannot_deactivate_system_principal(): void
    {
        $unauthorized = $this->makePrincipal();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_2']);
        app(PrincipalService::class)->forSystem($systemRow);

        $this->expectException(\RuntimeException::class);

        app(PrincipalService::class)->deactivateSystem($unauthorized, $systemRow);
    }

    public function test_standard_assurance_cannot_deactivate_system_principal(): void
    {
        $actor = $this->makeAuthorizedActor();
        app(AssuranceService::class)->invalidate();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_3']);
        app(PrincipalService::class)->forSystem($systemRow);

        $this->expectException(\RuntimeException::class);

        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);
    }

    public function test_rollback_after_forced_failure_leaves_neither_row_changed(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_4']);
        $principal = app(PrincipalService::class)->forSystem($systemRow);

        DB::listen(function ($query) {
            // Identifier quoting is driver-specific (SQLite/Postgres use
            // double quotes, MySQL uses backticks) — strip both so this
            // match works under any grammar.
            $normalizedSql = str_replace(['"', '`'], '', $query->sql);

            if (str_contains($normalizedSql, 'update principals') && str_contains($normalizedSql, 'disabled_at')) {
                throw new \RuntimeException('forced failure before final commit');
            }
        });

        try {
            app(PrincipalService::class)->deactivateSystem($actor, $systemRow);
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure before final commit', $e->getMessage());
        }

        $systemRow->refresh();
        $principal->refresh();

        $this->assertNull($systemRow->deactivated_at, 'Catalog row must remain unchanged after rollback.');
        $this->assertNull($principal->disabled_at, 'Linked Principal must remain unchanged after rollback.');
    }

    public function test_repeated_deactivation_is_rejected_not_silently_idempotent(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_5']);
        app(PrincipalService::class)->forSystem($systemRow);

        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);

        $this->expectException(\RuntimeException::class);

        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);
    }

    public function test_direct_mass_assignment_of_deactivated_at_is_blocked(): void
    {
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_6']);

        // deactivated_at is deliberately NOT fillable — a plain create()/
        // update() call must silently drop it, never mutate it.
        $systemRow->update(['deactivated_at' => now()]);

        $this->assertNull($systemRow->fresh()->deactivated_at);
    }

    // --- IMP003-REAUDIT1-M02 ---

    public function test_system_deactivation_with_no_existing_principal_creates_it_disabled(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_7']);

        // Deliberately never call forSystem() first — no principals row
        // exists yet for this catalog identity.
        $this->assertNull(Principal::where('system_principal_id', $systemRow->id)->first());

        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);

        $systemRow->refresh();
        $principal = Principal::where('system_principal_id', $systemRow->id)->first();

        $this->assertNotNull($systemRow->deactivated_at);
        $this->assertNotNull($principal, 'Deactivation must ensure a canonical Principal exists.');
        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_integration_deactivation_with_no_existing_principal_creates_it_disabled(): void
    {
        $actor = $this->makeAuthorizedActor();
        $integrationRow = IntegrationPrincipal::create(['code' => 'lifecycle_integration_7']);

        $this->assertNull(Principal::where('integration_principal_id', $integrationRow->id)->first());

        app(PrincipalService::class)->deactivateIntegration($actor, $integrationRow);

        $integrationRow->refresh();
        $principal = Principal::where('integration_principal_id', $integrationRow->id)->first();

        $this->assertNotNull($integrationRow->deactivated_at);
        $this->assertNotNull($principal);
        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_for_system_after_catalog_deactivation_cannot_produce_an_enabled_principal(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_8']);

        // Deactivate BEFORE any Principal has ever been resolved for it.
        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);
        $this->assertSame(
            1,
            Principal::where('system_principal_id', $systemRow->id)->count(),
            'Deactivation itself already created the Principal — sanity check.'
        );

        // A later forSystem() call (e.g. an incoming job event still
        // referencing this now-retired identity) must not resurrect it as
        // authorization-enabled.
        $principal = app(PrincipalService::class)->forSystem($systemRow->fresh());

        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_for_system_first_resolution_after_deactivation_with_no_prior_principal_is_disabled(): void
    {
        $actor = $this->makeAuthorizedActor();
        $bootstrapUser = User::create(['email' => 'bootstrap-deactivate@example.com', 'password' => Hash::make('correct-horse-battery-staple')]);
        app(PrincipalService::class)->forUser($bootstrapUser);

        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_9']);
        // Simulate a catalog identity that was deactivated via some path
        // that guarantees the catalog row's own state (deactivateSystem
        // always creates the Principal — this directly forces the raw
        // catalog-deactivated-with-no-Principal precondition instead).
        $systemRow->forceFill(['deactivated_at' => now()])->save();

        $this->assertNull(Principal::where('system_principal_id', $systemRow->id)->first());

        $principal = app(PrincipalService::class)->forSystem($systemRow);

        $this->assertNotNull($principal->disabled_at, 'A Principal first created for an already-deactivated catalog row must be born disabled.');
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_for_integration_first_resolution_after_deactivation_with_no_prior_principal_is_disabled(): void
    {
        $integrationRow = IntegrationPrincipal::create(['code' => 'lifecycle_integration_9']);
        $integrationRow->forceFill(['deactivated_at' => now()])->save();

        $this->assertNull(Principal::where('integration_principal_id', $integrationRow->id)->first());

        $principal = app(PrincipalService::class)->forIntegration($integrationRow);

        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_active_system_catalog_still_resolves_an_enabled_principal(): void
    {
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_10']);

        $principal = app(PrincipalService::class)->forSystem($systemRow);

        $this->assertNull($principal->disabled_at);
        $this->assertTrue($principal->canAuthorize());
    }

    // --- R3-M02: stale caller-supplied catalog state must never override the authoritative DB row ---

    public function test_for_system_with_stale_catalog_object_and_no_prior_principal_cannot_produce_enabled_principal(): void
    {
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_stale_1']);

        // The caller holds an in-memory copy loaded BEFORE the row was
        // deactivated (by some other request/process) — it still looks
        // active locally.
        $staleSystemRow = SystemPrincipal::find($systemRow->id);

        $systemRow->forceFill(['deactivated_at' => now()])->save();

        $this->assertNull($staleSystemRow->deactivated_at, 'Sanity: the stale in-memory copy must still look active.');
        $this->assertNull(Principal::where('system_principal_id', $systemRow->id)->first());

        $principal = app(PrincipalService::class)->forSystem($staleSystemRow);

        $this->assertNotNull(
            $principal->disabled_at,
            'forSystem() must decide lifecycle from the authoritative DB row, not the stale caller-supplied model.'
        );
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_for_integration_with_stale_catalog_object_and_no_prior_principal_cannot_produce_enabled_principal(): void
    {
        $integrationRow = IntegrationPrincipal::create(['code' => 'lifecycle_integration_stale_1']);

        $staleIntegrationRow = IntegrationPrincipal::find($integrationRow->id);

        $integrationRow->forceFill(['deactivated_at' => now()])->save();

        $this->assertNull($staleIntegrationRow->deactivated_at);
        $this->assertNull(Principal::where('integration_principal_id', $integrationRow->id)->first());

        $principal = app(PrincipalService::class)->forIntegration($staleIntegrationRow);

        $this->assertNotNull($principal->disabled_at);
        $this->assertFalse($principal->canAuthorize());
    }

    public function test_for_system_with_existing_enabled_principal_and_stale_catalog_object_ends_disabled(): void
    {
        $systemRow = SystemPrincipal::create(['code' => 'lifecycle_system_stale_2']);
        app(PrincipalService::class)->forSystem($systemRow);

        $staleSystemRow = SystemPrincipal::find($systemRow->id);

        $systemRow->forceFill(['deactivated_at' => now()])->save();

        $this->assertNull($staleSystemRow->deactivated_at);

        $resolved = app(PrincipalService::class)->forSystem($staleSystemRow);

        $this->assertNotNull(
            $resolved->disabled_at,
            'An already-existing (previously enabled) Principal must end disabled once the authoritative catalog row is deactivated, even when resolved via a stale object.'
        );
        $this->assertFalse($resolved->canAuthorize());
    }

    public function test_for_integration_with_existing_enabled_principal_and_stale_catalog_object_ends_disabled(): void
    {
        $integrationRow = IntegrationPrincipal::create(['code' => 'lifecycle_integration_stale_2']);
        app(PrincipalService::class)->forIntegration($integrationRow);

        $staleIntegrationRow = IntegrationPrincipal::find($integrationRow->id);

        $integrationRow->forceFill(['deactivated_at' => now()])->save();

        $this->assertNull($staleIntegrationRow->deactivated_at);

        $resolved = app(PrincipalService::class)->forIntegration($staleIntegrationRow);

        $this->assertNotNull($resolved->disabled_at);
        $this->assertFalse($resolved->canAuthorize());
    }
}
