<?php

namespace Tests\Feature\Rbac;

use App\Enums\PrincipalKind;
use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Rbac\SystemPrincipal;
use App\Models\User;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

class PrincipalLifecycleTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makeUser(): User
    {
        $this->userSequence++;

        return User::create([
            'email' => "principal-test-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
    }

    public function test_principal_creation_is_idempotent_and_grants_nothing(): void
    {
        $user = $this->makeUser();
        $service = app(PrincipalService::class);

        $first = $service->forUser($user);
        $second = $service->forUser($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(PrincipalKind::Human, $first->principal_kind);
        $this->assertSame(0, PrincipalRoleAssignment::where('principal_id', $first->id)->count());
        $this->assertSame(0, AuthorityAssignment::where('principal_id', $first->id)->count());
    }

    public function test_system_and_integration_principal_creation_is_idempotent(): void
    {
        $system = SystemPrincipal::create(['code' => 'scheduler']);
        $integration = IntegrationPrincipal::create(['code' => 'payment_webhook']);
        $service = app(PrincipalService::class);

        $p1 = $service->forSystem($system);
        $p2 = $service->forSystem($system);
        $this->assertSame($p1->id, $p2->id);
        $this->assertSame(PrincipalKind::System, $p1->principal_kind);

        $p3 = $service->forIntegration($integration);
        $this->assertSame(PrincipalKind::Integration, $p3->principal_kind);
    }

    public function test_application_level_guard_rejects_inconsistent_kind_on_every_driver(): void
    {
        $this->expectException(\RuntimeException::class);

        Principal::create(['principal_kind' => PrincipalKind::Human, 'human_user_id' => null]);
    }

    public function test_application_level_guard_rejects_dual_source_principal(): void
    {
        $user = $this->makeUser();
        $system = SystemPrincipal::create(['code' => 'scheduler']);

        $this->expectException(\RuntimeException::class);

        Principal::create([
            'principal_kind' => PrincipalKind::Human,
            'human_user_id' => $user->id,
            'system_principal_id' => $system->id,
        ]);
    }

    public function test_canonical_user_deletion_transaction_is_atomic_on_success(): void
    {
        $admin = $this->makeAuthorizedActor();
        $target = $this->makeUser();
        $service = app(PrincipalService::class);
        $principal = $service->forUser($target);

        $role = Role::create(['code' => 'test_role', 'name' => 'Test Role']);
        app(RoleAssignmentService::class)->assign($admin, $principal, $role, ScopeType::GlobalPlatform, null);

        $service->deleteUser($target, $admin);

        $this->assertNull(User::find($target->id));

        $principal->refresh();
        $this->assertTrue($principal->isTombstoned());
        $this->assertNull($principal->human_user_id);

        $this->assertSame(
            0,
            PrincipalRoleAssignment::where('principal_id', $principal->id)->whereNull('revoked_at')->count(),
            'Every active assignment must be revoked as part of the atomic deletion.'
        );

        $historical = PrincipalRoleAssignment::where('principal_id', $principal->id)->first();
        $this->assertNotNull($historical, 'History must remain reconstructable after tombstoning.');
        $this->assertNotNull($historical->revoked_at);
    }

    public function test_direct_user_deletion_outside_canonical_lifecycle_is_rejected(): void
    {
        $user = $this->makeUser();
        app(PrincipalService::class)->forUser($user);

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->delete();
    }

    public function test_canonical_user_deletion_rolls_back_entirely_on_forced_failure(): void
    {
        $admin = $this->makeAuthorizedActor();
        $target = $this->makeUser();
        $principal = app(PrincipalService::class)->forUser($target);

        $role = Role::create(['code' => 'test_role_rollback', 'name' => 'Test Role Rollback']);
        app(RoleAssignmentService::class)->assign($admin, $principal, $role, ScopeType::GlobalPlatform, null);

        DB::listen(function ($query) {
            // Identifier quoting is driver-specific (SQLite/Postgres use
            // double quotes, MySQL uses backticks) — strip both so this
            // match works under any grammar.
            $normalizedSql = str_replace(['"', '`'], '', $query->sql);

            if (str_contains($normalizedSql, 'delete from users')) {
                throw new \RuntimeException('forced failure before final commit');
            }
        });

        try {
            app(PrincipalService::class)->deleteUser($target, $admin);
            $this->fail('Expected the forced failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure before final commit', $e->getMessage());
        }

        $this->assertNotNull(User::find($target->id), 'User must remain after rollback.');

        $principal->refresh();
        $this->assertFalse($principal->isTombstoned(), 'Principal must remain LIVE after rollback.');
        $this->assertSame($target->id, $principal->human_user_id);

        $this->assertSame(
            1,
            PrincipalRoleAssignment::where('principal_id', $principal->id)->whereNull('revoked_at')->count(),
            'Assignments must remain unchanged after rollback.'
        );
    }

    public function test_principal_attribution_survives_grantor_user_deletion(): void
    {
        $grantor = $this->makeAuthorizedActor();
        $grantorUser = $grantor->humanUser;
        $bystander = app(PrincipalService::class)->forUser($this->makeUser());
        $target = app(PrincipalService::class)->forUser($this->makeUser());

        $role = Role::create(['code' => 'test_role_attribution', 'name' => 'Test Role Attribution']);
        $assignment = app(RoleAssignmentService::class)->assign($grantor, $target, $role, ScopeType::GlobalPlatform, null);

        // A distinct Principal performs the canonical deletion of the grantor.
        app(PrincipalService::class)->deleteUser($grantorUser, $bystander);

        $assignment->refresh();
        $this->assertSame($grantor->id, $assignment->assigned_by_principal_id);

        $grantor->refresh();
        $this->assertTrue($grantor->isTombstoned());

        // The grantor Principal row itself is retained (not orphaned) even though its User is gone.
        $this->assertNotNull(Principal::find($grantor->id));
    }
}
