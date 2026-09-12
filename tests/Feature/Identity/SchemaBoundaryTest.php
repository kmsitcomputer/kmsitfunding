<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Structural assertion that IMP-002's `users` table never introduces
 * premature authorization fields or persistent brute-force lockout state
 * (Q24 / M05). See "Database Design Requirements" in
 * docs/implementation/IMP-002-identity-authentication.md.
 */
class SchemaBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_no_premature_authorization_or_lockout_fields(): void
    {
        $columns = Schema::getColumnListing('users');

        $forbidden = [
            'role', 'role_id', 'permission', 'permission_id', 'actor_type',
            'is_admin', 'is_super_admin', 'financial_authority', 'partner_authority',
            'fundraiser_authority', 'beneficiary_approval',
            'failed_login_attempts', 'locked_until', 'pending_email',
        ];

        foreach ($forbidden as $column) {
            $this->assertNotContains($column, $columns, "users.$column must not exist (IMP-002 boundary).");
        }
    }

    public function test_no_roles_or_permissions_tables_exist(): void
    {
        foreach (['roles', 'permissions', 'role_user', 'permission_user'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "$table must not exist — RBAC is IMP-003+.");
        }
    }

    public function test_email_change_requests_table_has_conflict_terminal_state(): void
    {
        $columns = Schema::getColumnListing('email_change_requests');

        foreach (['conflicted_at', 'conflict_reason_code', 'superseded_at', 'cancelled_at', 'verified_at', 'expires_at'] as $column) {
            $this->assertContains($column, $columns);
        }
    }
}
