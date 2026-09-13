<?php

namespace Tests\Feature\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Rbac\SystemPrincipal;
use App\Models\User;
use App\Services\Rbac\AuthorityAssignmentService;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RbacAuditLogger;
use App\Services\Rbac\RoleAssignmentService;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\CapturesRbacAudit;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * `IMP003-REAUDIT1-m01` — automated proof that every required RBAC mutation
 * family actually emits its audit event (not merely code inspection),
 * attributes it to a Principal (never a User), keeps the mutation and its
 * audit emission transactionally atomic, and never leaks sensitive material.
 */
class RbacAuditTest extends TestCase
{
    use CapturesRbacAudit;
    use RbacTestActors;
    use RefreshDatabase;

    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->captureRbacAuditLog();
    }

    protected function tearDown(): void
    {
        $this->tearDownRbacAuditCapture();
        parent::tearDown();
    }

    private function makePrincipal(): Principal
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "audit-test-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    private function financialApprover(): AuthorityType
    {
        return AuthorityType::firstOrCreate(
            ['code' => AuthorityType::FINANCIAL_APPROVER],
            ['name' => 'Financial Approver', 'description' => 'test', 'is_financial' => true],
        );
    }

    // --- 1/2: Role assignment / revocation ---

    public function test_role_assignment_emits_audit_event_attributed_to_principal(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'audit_role_1', 'name' => 'Audit Role 1']);

        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        $record = $this->assertRbacAuditEventLogged('rbac.role.assigned');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertSame('human', $record->actor_principal_kind);
        $this->assertSame($assignment->id, $record->subject_id);
        $this->assertSame($target->id, $record->metadata['target_principal_id']);
        $this->assertSame($role->id, $record->metadata['role_id']);
        $this->assertArrayNotHasKey('grantor_user_id', $record->metadata);
        $this->assertArrayNotHasKey('target_user_id', $record->metadata);
    }

    public function test_role_revocation_emits_audit_event_attributed_to_principal(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'audit_role_2', 'name' => 'Audit Role 2']);
        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        app(RoleAssignmentService::class)->revoke($actor, $assignment);

        $record = $this->assertRbacAuditEventLogged('rbac.role.revoked');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertSame($assignment->id, $record->subject_id);
        $this->assertArrayNotHasKey('revoker_user_id', $record->metadata ?? []);
    }

    // --- 3/4: Authority assignment / revocation ---

    public function test_authority_assignment_emits_audit_event_attributed_to_principal(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();

        $assignment = app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);

        $record = $this->assertRbacAuditEventLogged('rbac.authority.assigned');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertSame($assignment->id, $record->subject_id);
        $this->assertSame($target->id, $record->metadata['target_principal_id']);
        $this->assertArrayNotHasKey('grantor_user_id', $record->metadata);
    }

    public function test_authority_revocation_emits_audit_event_attributed_to_principal(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $assignment = app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);

        app(AuthorityAssignmentService::class)->revoke($actor, $assignment);

        $record = $this->assertRbacAuditEventLogged('rbac.authority.revoked');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertArrayNotHasKey('revoker_user_id', $record->metadata ?? []);
    }

    // --- 5/6: Role-Permission grant / revoke ---

    public function test_role_permission_grant_emits_audit_event(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_rp_role_1', 'name' => 'Audit RP Role 1']);
        $permission = Permission::create(['code' => 'audit.test.perm1', 'description' => 'test']);

        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $record = $this->assertRbacAuditEventLogged('rbac.role_permission.granted');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertSame($role->id, $record->metadata['role_id']);
        $this->assertSame($permission->id, $record->metadata['permission_id']);
        $this->assertArrayNotHasKey('actor_user_id', $record->metadata);
    }

    public function test_role_permission_revoke_emits_audit_event(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_rp_role_2', 'name' => 'Audit RP Role 2']);
        $permission = Permission::create(['code' => 'audit.test.perm2', 'description' => 'test']);
        app(RolePermissionService::class)->grant($actor, $role, $permission);

        app(RolePermissionService::class)->revoke($actor, $role, $permission);

        $record = $this->assertRbacAuditEventLogged('rbac.role_permission.revoked');
        $this->assertSame($actor->id, $record->actor_principal_id);
    }

    // --- 7/8: Non-human Principal deactivation ---

    public function test_system_principal_deactivation_emits_audit_event(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'audit_system_1']);

        app(PrincipalService::class)->deactivateSystem($actor, $systemRow);

        $record = $this->assertRbacAuditEventLogged('rbac.principal.non_human_deactivated');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertSame('system', $record->metadata['kind']);
        $this->assertArrayNotHasKey('actor_user_id', $record->metadata);
    }

    public function test_integration_principal_deactivation_emits_audit_event(): void
    {
        $actor = $this->makeAuthorizedActor();
        $integrationRow = IntegrationPrincipal::create(['code' => 'audit_integration_1']);

        app(PrincipalService::class)->deactivateIntegration($actor, $integrationRow);

        $record = $this->assertRbacAuditEventLogged('rbac.principal.non_human_deactivated');
        $this->assertSame($actor->id, $record->actor_principal_id);
        $this->assertSame('integration', $record->metadata['kind']);
    }

    // --- Sensitive payload safety ---

    public function test_no_audit_event_contains_sensitive_material(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'audit_sensitive_role', 'name' => 'Audit Sensitive Role']);
        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        $forbidden = ['password', 'password_hash', 'mfa_secret', 'totp_secret', 'session_token', 'api_token', 'credential', 'secret'];

        foreach ($this->readRbacAuditEvents() as $record) {
            $flattened = strtolower(json_encode($record->metadata));

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $flattened, "Audit metadata must never contain '{$needle}'.");
            }
        }
    }

    // --- Audit failure => mutation rollback ---

    private function bindFailingAuditLogger(): void
    {
        $this->app->bind(RbacAuditLogger::class, fn () => new class extends RbacAuditLogger
        {
            public function __construct() {}

            public function record(string $event, array $context = []): void
            {
                throw new \RuntimeException('forced audit failure');
            }
        });
    }

    public function test_role_assignment_audit_failure_rolls_back_the_assignment(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'audit_rollback_role_1', 'name' => 'Audit Rollback Role 1']);

        $this->bindFailingAuditLogger();

        try {
            app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced audit failure', $e->getMessage());
        }

        $this->assertSame(
            0,
            PrincipalRoleAssignment::where('principal_id', $target->id)->where('role_id', $role->id)->count(),
            'No assignment row may exist if the audit emission failed inside the same transaction.'
        );
    }

    public function test_authority_assignment_audit_failure_rolls_back_the_assignment(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();

        $this->bindFailingAuditLogger();

        try {
            app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced audit failure', $e->getMessage());
        }

        $this->assertSame(
            0,
            AuthorityAssignment::where('principal_id', $target->id)->count(),
        );
    }

    public function test_role_permission_grant_audit_failure_rolls_back_the_grant(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'audit_rollback_rp_role', 'name' => 'Audit Rollback RP Role']);
        $permission = Permission::create(['code' => 'audit.test.rollback', 'description' => 'test']);

        $this->bindFailingAuditLogger();

        try {
            app(RolePermissionService::class)->grant($actor, $role, $permission);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced audit failure', $e->getMessage());
        }

        $this->assertSame(
            0,
            DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->count(),
        );
    }

    public function test_non_human_deactivation_audit_failure_rolls_back_lifecycle_state(): void
    {
        $actor = $this->makeAuthorizedActor();
        $systemRow = SystemPrincipal::create(['code' => 'audit_rollback_system']);

        $this->bindFailingAuditLogger();

        try {
            app(PrincipalService::class)->deactivateSystem($actor, $systemRow);
            $this->fail('Expected the forced audit failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced audit failure', $e->getMessage());
        }

        $systemRow->refresh();
        $this->assertNull($systemRow->deactivated_at, 'Catalog row must remain unchanged if the audit emission failed.');

        $principal = Principal::where('system_principal_id', $systemRow->id)->first();
        $this->assertNull($principal, 'No Principal should have been created/persisted either, since the whole transaction rolled back.');
    }
}
