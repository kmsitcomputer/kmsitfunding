<?php

namespace Tests\Feature\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\AuthorityAssignmentService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Scope Type / Scope Target Matrix ("scope_type deterministic; unknown
 * scope -> write REJECT, evaluation DENY; invalid target -> REJECT; missing
 * target during evaluation -> DENY").
 */
class ScopeContractTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makePrincipal(): Principal
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "scope-contract-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    public function test_unknown_scope_type_is_rejected_at_write_time(): void
    {
        $principal = $this->makePrincipal();
        $role = Role::create(['code' => 'scope_test_role', 'name' => 'Scope Test Role']);

        // The strict backed-enum cast on `scope_type` rejects an unknown
        // value before it ever reaches the database — the DB-level CHECK
        // (chk_pra_scope_matrix, MySQL only) is the second, independent
        // layer that would reject it were this application guard bypassed.
        $this->expectException(\ValueError::class);

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => 'NOT_A_REAL_SCOPE_TYPE',
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => $principal->id,
        ]);
    }

    #[DataProvider('nullRequiredScopeTypes')]
    public function test_scope_types_requiring_null_scope_id(ScopeType $scopeType): void
    {
        $this->assertTrue($scopeType->requiresNullScopeId());
    }

    public static function nullRequiredScopeTypes(): array
    {
        return [
            [ScopeType::GlobalPlatform],
            [ScopeType::Organization],
            [ScopeType::Own],
        ];
    }

    #[DataProvider('concreteTargetScopeTypes')]
    public function test_scope_types_requiring_a_concrete_target(ScopeType $scopeType): void
    {
        $this->assertFalse($scopeType->requiresNullScopeId());
    }

    public static function concreteTargetScopeTypes(): array
    {
        return [
            [ScopeType::Fundraiser],
            [ScopeType::Partner],
            [ScopeType::Campaign],
            [ScopeType::Program],
            [ScopeType::Fund],
            [ScopeType::BeneficiaryCase],
            [ScopeType::AssignedWork],
        ];
    }

    public function test_authority_assignment_scope_matrix_rejects_mismatched_scope_id(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $authorityType = AuthorityType::create([
            'code' => 'scope_test_authority', 'name' => 'Scope Test Authority', 'is_financial' => false,
        ]);

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->assign(
            $grantor, $target, $authorityType, ScopeType::Organization, 42,
        );
    }
}
