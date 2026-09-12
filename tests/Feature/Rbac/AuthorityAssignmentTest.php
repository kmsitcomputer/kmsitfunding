<?php

namespace Tests\Feature\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use App\Models\User;
use App\Services\Rbac\AuthorityAssignmentService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorityAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makePrincipal(): Principal
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "authority-test-{$this->userSequence}@example.com",
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

    public function test_self_financial_authority_grant_is_denied_unconditionally(): void
    {
        $principal = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->assign(
            $principal,
            $principal,
            $this->financialApprover(),
            ScopeType::GlobalPlatform,
            null,
        );
    }

    public function test_authority_assignment_by_a_distinct_grantor_succeeds(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();

        $assignment = app(AuthorityAssignmentService::class)->assign(
            $grantor,
            $target,
            $this->financialApprover(),
            ScopeType::GlobalPlatform,
            null,
        );

        $this->assertTrue($assignment->isActive());
        $this->assertSame($grantor->id, $assignment->assigned_by_principal_id);
    }

    public function test_authority_assignment_requires_a_distinct_grantor_at_the_database_level(): void
    {
        // Bypassing the service (direct model create) still must not slip past the
        // model-level self-grant guard mirroring chk_aa_no_self_grant.
        $principal = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        AuthorityAssignment::create([
            'principal_id' => $principal->id,
            'authority_type_id' => $this->financialApprover()->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => $principal->id,
        ]);
    }

    public function test_scope_matrix_is_enforced_for_authority_assignments(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->assign(
            $grantor,
            $target,
            $this->financialApprover(),
            ScopeType::Campaign,
            null,
        );
    }

    public function test_tombstoned_principal_cannot_receive_authority(): void
    {
        $grantor = $this->makePrincipal();
        $targetUser = User::create(['email' => 'authority-tombstone@example.com', 'password' => Hash::make('correct-horse-battery-staple')]);
        $target = app(PrincipalService::class)->forUser($targetUser);
        app(PrincipalService::class)->deleteUser($targetUser, $grantor);

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->assign($grantor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);
    }

    public function test_revoking_authority_deactivates_it(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $service = app(AuthorityAssignmentService::class);

        $assignment = $service->assign($grantor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);
        $service->revoke($grantor, $assignment);

        $assignment->refresh();
        $this->assertFalse($assignment->isActive());
        $this->assertSame($grantor->id, $assignment->revoked_by_principal_id);
    }
}
