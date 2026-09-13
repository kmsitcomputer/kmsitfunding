<?php

namespace Tests\Feature\Rbac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Structural regression assertions for IMP-003 (see "Static security review"
 * and "Architecture/Database/Security regression reviews" in the
 * implementation authorization). Proves, by inspecting the actual migrated
 * schema, that authorization state was never encoded onto `users` and that
 * the canonical RBAC tables exist with the columns the specification
 * requires.
 */
class SchemaAndSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_carries_no_authorization_columns(): void
    {
        $columns = Schema::getColumnListing('users');

        $forbidden = [
            'role', 'role_id', 'is_admin', 'is_super_admin', 'permissions',
            'scope', 'scope_type', 'scope_id', 'business_authority',
            'financial_authority', 'approval_authority',
        ];

        foreach ($forbidden as $column) {
            $this->assertNotContains($column, $columns, "users.$column must not exist — IMP-003 authorization state lives on principals/assignments, never on users.");
        }
    }

    public function test_canonical_rbac_tables_exist(): void
    {
        foreach ([
            'roles', 'permissions', 'authority_types', 'system_principals',
            'integration_principals', 'principals', 'role_permissions',
            'principal_role_assignments', 'authority_assignments',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "$table must exist.");
        }
    }

    public function test_principals_table_has_the_specified_lifecycle_columns(): void
    {
        $columns = Schema::getColumnListing('principals');

        foreach (['principal_kind', 'human_user_id', 'system_principal_id', 'integration_principal_id', 'tombstoned_at', 'disabled_at'] as $column) {
            $this->assertContains($column, $columns);
        }
    }

    public function test_assignment_tables_use_principal_based_attribution_not_user_based(): void
    {
        foreach (['principal_role_assignments', 'authority_assignments'] as $table) {
            $columns = Schema::getColumnListing($table);

            $this->assertContains('assigned_by_principal_id', $columns);
            $this->assertContains('revoked_by_principal_id', $columns);
            $this->assertNotContains('assigned_by_user_id', $columns, "$table must attribute to a Principal, never directly to a User (grantor history must survive User deletion).");
            $this->assertNotContains('revoked_by_user_id', $columns);
        }
    }

    public function test_assignment_tables_carry_the_generated_active_assignment_key(): void
    {
        $this->assertContains('active_assignment_key', Schema::getColumnListing('principal_role_assignments'));
        $this->assertContains('active_assignment_key', Schema::getColumnListing('authority_assignments'));
        $this->assertContains('active_grant_key', Schema::getColumnListing('role_permissions'));
    }
}
