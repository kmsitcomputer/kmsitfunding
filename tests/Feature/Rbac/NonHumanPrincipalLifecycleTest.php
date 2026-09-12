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
            if (str_contains($query->sql, 'update "principals"') && str_contains($query->sql, 'disabled_at')) {
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
}
