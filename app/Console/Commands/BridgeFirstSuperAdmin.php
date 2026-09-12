<?php

namespace App\Console\Commands;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\SuperAdminBootstrap;
use App\Models\User;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RbacAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Q25 -> IMP-003 Bridge: converts the ALREADY-authorized bootstrap event
 * (Q25's identity-only bootstrap) into the canonical `super_admin` Role
 * assignment. This is a controlled provisioning bridge, not an ordinary
 * self-role-assignment — see "Super Admin Canonical Authorization (Q25
 * bridge)". Grants ONLY the `super_admin` Role at GLOBAL_PLATFORM scope;
 * grants NO Business/Financial/Approval Authority, ever, under any
 * condition.
 *
 * Two independent guards, both required:
 *   (a) exactly one `super_admin_bootstraps` row exists (Q25's own guard);
 *   (b) no `principal_role_assignments` row has ever assigned `super_admin`
 *       to any Principal (this Bridge's own guard — prevents re-running
 *       after canonical authority already exists).
 * Guard (b) is re-validated AFTER locking the target Principal row, so a
 * concurrent double-invocation can produce at most one effective
 * assignment.
 */
class BridgeFirstSuperAdmin extends Command
{
    protected $signature = 'rbac:bridge-first-super-admin';

    protected $description = 'One-time bridge: grant the canonical super_admin Role to the Q25-bootstrapped identity.';

    public function handle(PrincipalService $principals, RbacAuditLogger $audit): int
    {
        $bootstrap = SuperAdminBootstrap::where('lock_key', SuperAdminBootstrap::LOCK_KEY)->first();

        if ($bootstrap === null) {
            $this->error('No Super Admin bootstrap event exists. Run identity:bootstrap-super-admin first.');

            return self::FAILURE;
        }

        $superAdminRole = Role::where('code', 'super_admin')->first();

        if ($superAdminRole === null) {
            $this->error('The super_admin Role is not seeded. Run the RBAC seeders first.');

            return self::FAILURE;
        }

        if (PrincipalRoleAssignment::where('role_id', $superAdminRole->id)->exists()) {
            $this->error('The super_admin Role has already been canonically assigned. This bridge may only run once.');

            return self::FAILURE;
        }

        if ($bootstrap->user_id === null) {
            $this->error('The bootstrapped identity no longer exists. The bridge cannot invent a target.');

            return self::FAILURE;
        }

        $user = User::find($bootstrap->user_id);

        if ($user === null) {
            $this->error('The bootstrapped identity no longer exists. The bridge cannot invent a target.');

            return self::FAILURE;
        }

        try {
            $assignment = DB::transaction(function () use ($principals, $user, $superAdminRole) {
                $principal = $principals->forUser($user);
                $lockedPrincipal = Principal::whereKey($principal->id)->lockForUpdate()->firstOrFail();

                // Re-validate guard (b) under lock — a concurrent
                // double-invocation must not produce two assignments.
                if (PrincipalRoleAssignment::where('role_id', $superAdminRole->id)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('super_admin already canonically assigned (concurrent bridge run).');
                }

                return PrincipalRoleAssignment::create([
                    'principal_id' => $lockedPrincipal->id,
                    'role_id' => $superAdminRole->id,
                    'scope_type' => ScopeType::GlobalPlatform->value,
                    'scope_id' => null,
                    'starts_at' => now(),
                    // Deliberately NULL — attributed to the bootstrap event
                    // itself, not to any self-grant (see "Principal-Based
                    // Attribution" and "Super Admin Canonical Authorization").
                    'assigned_by_principal_id' => null,
                ]);
            });
        } catch (\Throwable $e) {
            $this->error('Bridge failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $audit->record('super_admin_canonically_authorized', [
            'principal_id' => $assignment->principal_id,
            'role' => 'super_admin',
            'scope_type' => 'GLOBAL_PLATFORM',
            'granted' => ['role: super_admin'],
            'not_granted' => ['financial_authority', 'business_authority', 'approval_authority'],
        ]);

        $this->info('super_admin Role canonically granted to the bootstrapped identity (GLOBAL_PLATFORM scope only).');
        $this->line('No Business/Financial/Approval Authority was granted — that remains a separate, later Authority Assignment.');

        return self::SUCCESS;
    }
}
