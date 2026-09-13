<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Services\Audit\Exceptions\TransactionOwnershipViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The canonical, authoritative Role -> Permission grant/revoke mutation
 * service (`IMP003-IMPL-M03`, `IMP003-REAUDIT1-M01`). Application code must
 * never treat `$role->permissions()->attach()/detach()/sync()` or a raw
 * `role_permissions` table write as the authorized business interface for a
 * runtime request — this service is that interface. Direct pivot mutation
 * remains acceptable ONLY for internal setup/seeding code that runs with no
 * acting Principal at all (e.g. `RbacRoleSeeder`'s initial `super_admin`
 * grants, attributed to `granted_by_principal_id = null`), never as a
 * runtime-reachable path.
 */
class RolePermissionService
{
    public function __construct(
        private readonly RbacMutationGuard $guard,
        private readonly RbacAuditLogger $audit,
    ) {}

    /**
     * Lock order for a NEW grant: acting Principal -> target Role (reloaded
     * + lifecycle-validated) -> target Permission (reloaded + lifecycle-
     * validated) -> active grant slot -> mutation -> audit -> commit. A
     * retired Role or a deprecated Permission never receives a NEW grant
     * (`IMP003-REAUDIT1-M01`) — retirement/deprecation only ever blocks NEW
     * assignments, never retroactively revokes an existing one (see
     * "Permission Registry").
     */
    public function grant(Principal $actor, Role $role, Permission $permission): void
    {
        // IMP004-IMPL-M01 (restored per Codex — this method's own
        // transaction MUST be the outermost one before any lock, mutation,
        // or authorization evaluation runs, exactly as the specification's
        // "Transaction Ownership Invariant" requires). Rejected here as a
        // distinct calling-contract violation — never treated as, or
        // persisted as, an authorization-denial outcome; no
        // security.authorization.denied event is emitted for it. Test code
        // exercising this method must not rely on an ambient transaction
        // being open at test-body start — use
        // Illuminate\Foundation\Testing\DatabaseTruncation (never
        // RefreshDatabase, whose per-test wrapper transaction would
        // otherwise always trip this check) for those test classes.
        if (DB::connection()->transactionLevel() !== 0) {
            throw new TransactionOwnershipViolationException(
                'RolePermissionService::grant() must own the outermost DB transaction — '.
                'an ambient transaction is already open on this connection (level '.
                DB::connection()->transactionLevel().').'
            );
        }

        // Denial capture for the F-01 DENIAL_DURABLE event — populated ONLY
        // at the self-escalation throw site below; the DENY decision,
        // exception type, and message are unchanged from IMP-003.
        $denial = null;

        try {
            DB::transaction(function () use ($actor, $role, $permission, &$denial) {
                $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

                $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PERMISSION_ASSIGN);

                // Reload + lock the target Role from the DB — a caller-supplied
                // (possibly stale) Role instance must never be trusted for the
                // retirement check below.
                $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

                if ($lockedRole->retired_at !== null) {
                    throw new \RuntimeException(
                        "Role '{$lockedRole->code}' is retired — no new Permission grant is allowed."
                    );
                }

                // Same reload + lock discipline for the target Permission.
                $lockedPermission = Permission::whereKey($permission->id)->lockForUpdate()->firstOrFail();

                if ($lockedPermission->deprecated_at !== null) {
                    throw new \RuntimeException(
                        "Permission '{$lockedPermission->code}' is deprecated — no new grant is allowed."
                    );
                }

                // Approved conditional Self-Escalation rule (see "Role ->
                // Permission Assignment"): granting a Permission to a Role the
                // actor itself holds is denied ONLY when that grant would
                // actually expand the actor's own effective authority — i.e.
                // only when the actor does not ALREADY effectively hold that
                // Permission (directly or via any other currently-assigned
                // Role). If the actor already effectively has it, this grant
                // changes nothing about their own authority and may proceed.
                $actorAlreadyHasPermission = (new AuthorizationContext($lockedActor))
                    ->activeRoleAssignmentsGranting($lockedPermission->code)
                    ->isNotEmpty();

                if (! $actorAlreadyHasPermission) {
                    // `IMP003-REAUDIT1-M01`/`R3-M01`: "currently holds" means
                    // effective right now — the same canonical temporal
                    // predicate `PrincipalRoleAssignment::isActive()` already
                    // defines (not revoked, started, not yet ended). A merely
                    // historical/expired/revoked/future assignment must never
                    // trigger this branch.
                    $actorHoldsTargetRole = PrincipalRoleAssignment::where('principal_id', $lockedActor->id)
                        ->where('role_id', $lockedRole->id)
                        ->whereNull('revoked_at')
                        ->get()
                        ->contains(fn (PrincipalRoleAssignment $assignment) => $assignment->isActive());

                    if ($actorHoldsTargetRole) {
                        // F-01 / IMP004-SPEC-M04: capture the denial context for
                        // the DENIAL_DURABLE event BEFORE throwing — the throw
                        // itself (type/message/decision) is unchanged from
                        // IMP-003. The write happens in the catch below,
                        // strictly AFTER this transaction has rolled back and
                        // every lock is released (deadlock-safe sequencing,
                        // IMP004-REAUDIT-M01/M02).
                        $denial = [
                            'actor' => $lockedActor,
                            'role_id' => $lockedRole->id,
                            'attempted_action' => PermissionRegistry::RBAC_PERMISSION_ASSIGN,
                            'denial_reason' => 'self_escalation',
                        ];

                        throw new \RuntimeException(
                            "Granting Permission '{$lockedPermission->code}', which the acting Principal does ".
                            "not already effectively hold, to Role '{$lockedRole->code}' — a Role that Principal ".
                            'itself holds — is denied (Self-Escalation Protection: this grant would expand the '.
                            "actor's own effective authority)."
                        );
                    }
                }

                $alreadyActive = DB::table('role_permissions')
                    ->where('role_id', $lockedRole->id)
                    ->where('permission_id', $lockedPermission->id)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyActive) {
                    return;
                }

                $now = now();

                $rolePermissionId = DB::table('role_permissions')->insertGetId([
                    'role_id' => $lockedRole->id,
                    'permission_id' => $lockedPermission->id,
                    'granted_at' => $now,
                    'granted_by_principal_id' => $lockedActor->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->audit->record('role_permission_granted', [
                    'actor_principal_id' => $lockedActor->id,
                    'role_permission_id' => $rolePermissionId,
                    'role_id' => $lockedRole->id,
                    'permission_id' => $lockedPermission->id,
                ]);
            });
        } catch (\Throwable $e) {
            if ($denial !== null) {
                $this->persistDenialEvidence($denial);
            }

            throw $e;
        }
    }

    /**
     * DENIAL_DURABLE persistence (IMP004-SPEC-M04): runs strictly after the
     * denying transaction has fully rolled back and released its locks — a
     * single plain insert needing no transaction of its own. A persistence
     * failure here never converts the denial into an allow and never masks
     * the original exception: it is reported through the dedicated
     * security_audit_failures channel (never silently, and never by
     * recursively depending on the canonical audit sink that just failed).
     *
     * @param  array{actor: Principal, role_id: int, attempted_action: string, denial_reason: string}  $denial
     */
    private function persistDenialEvidence(array $denial): void
    {
        try {
            $this->audit->recordAuthorizationDenied(
                $denial['actor'],
                $denial['role_id'],
                $denial['attempted_action'],
                $denial['denial_reason'],
            );
        } catch (\Throwable $auditFailure) {
            try {
                Log::channel('security_audit_failures')->error('audit_write_failed', [
                    'event_type' => 'security.authorization.denied',
                    'attempted_action' => $denial['attempted_action'],
                    'denial_reason' => $denial['denial_reason'],
                    'exception' => $auditFailure::class,
                ]);
            } catch (\Throwable) {
                // The reporting path itself must never raise a second exception
                // that could replace or mask the original authorization denial.
            }
        }
    }

    /**
     * Revocation is NOT blocked by Role retirement or Permission
     * deprecation — those states only ever prevent a NEW grant (see
     * `grant()`); closing an existing historical grant (e.g. security
     * cleanup) must remain possible regardless of either lifecycle state.
     * Target Role/Permission are still reloaded + locked (defense against a
     * stale caller-supplied model), just never lifecycle-rejected here.
     */
    public function revoke(Principal $actor, Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($actor, $role, $permission) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PERMISSION_REVOKE);

            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $lockedPermission = Permission::whereKey($permission->id)->lockForUpdate()->firstOrFail();

            $now = now();

            $activeGrantId = DB::table('role_permissions')
                ->where('role_id', $lockedRole->id)
                ->where('permission_id', $lockedPermission->id)
                ->whereNull('revoked_at')
                ->value('id');

            $updated = DB::table('role_permissions')
                ->where('role_id', $lockedRole->id)
                ->where('permission_id', $lockedPermission->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_by_principal_id' => $lockedActor->id,
                    'updated_at' => $now,
                ]);

            if ($updated > 0) {
                $this->audit->record('role_permission_revoked', [
                    'actor_principal_id' => $lockedActor->id,
                    'role_permission_id' => $activeGrantId,
                    'role_id' => $lockedRole->id,
                    'permission_id' => $lockedPermission->id,
                ]);
            }
        });
    }
}
