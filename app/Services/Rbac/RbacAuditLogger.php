<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;
use App\Services\Audit\Exceptions\AuditActorAttributionException;
use App\Services\Audit\Exceptions\UnregisteredAuditEventException;

/**
 * RBAC/Scope/Business-Authority audit emission points (see "Audit Contract").
 *
 * IMP-004: compatibility FACADE over the canonical AuditWriter — the existing
 * record() signature is preserved so already-tested IMP-003 call sites
 * require minimal change beyond the sink substitution itself. The canonical
 * database sink is now the ONE durable audit source; the log channel is no
 * longer canonical.
 *
 * MUST NEVER be called with a credential, token, or raw exception detail in
 * $context — the canonical writer hard-rejects any metadata key outside the
 * event's registry-declared allow-list.
 */
class RbacAuditLogger
{
    /**
     * Legacy event => [canonical event_type, subject_type, actor context key
     * (null = registry-declared pre-principal attribution), subject-id
     * context key, nullable-actor fallback context key].
     *
     * @var array<string, array{0: string, 1: string, 2: ?string, 3: string, 4: ?string}>
     */
    private const EVENT_MAP = [
        'role_assigned' => ['rbac.role.assigned', 'principal_role_assignment', 'grantor_principal_id', 'assignment_id', null],
        'role_revoked' => ['rbac.role.revoked', 'principal_role_assignment', 'revoker_principal_id', 'assignment_id', null],
        'authority_assigned' => ['rbac.authority.assigned', 'authority_assignment', 'grantor_principal_id', 'assignment_id', null],
        'authority_revoked' => ['rbac.authority.revoked', 'authority_assignment', 'revoker_principal_id', 'assignment_id', null],
        'role_permission_granted' => ['rbac.role_permission.granted', 'role_permission', 'actor_principal_id', 'role_permission_id', null],
        'role_permission_revoked' => ['rbac.role_permission.revoked', 'role_permission', 'actor_principal_id', 'role_permission_id', null],
        // Self-service deletion passes a null acting principal — the actor is
        // then the Principal being tombstoned itself (self-action), which
        // legitimately exists at emission time.
        'human_principal_tombstoned_user_deleted' => ['rbac.principal.human_tombstoned', 'principal', 'acting_principal_id', 'principal_id', 'principal_id'],
        'non_human_principal_deactivated' => ['rbac.principal.non_human_deactivated', 'principal', 'actor_principal_id', 'principal_id', null],
        'super_admin_canonically_authorized' => ['rbac.super_admin.canonically_authorized', 'principal', null, 'principal_id', null],
    ];

    public function __construct(
        private readonly AuditWriter $writer,
    ) {}

    public function record(string $event, array $context = []): void
    {
        $mapping = self::EVENT_MAP[$event] ?? throw new UnregisteredAuditEventException(
            "RBAC audit event '{$event}' has no canonical registry mapping."
        );

        [$eventType, $subjectType, $actorKey, $subjectIdKey, $actorFallbackKey] = $mapping;

        $actor = null;

        if ($actorKey !== null) {
            $actorId = $context[$actorKey] ?? ($actorFallbackKey !== null ? ($context[$actorFallbackKey] ?? null) : null);

            if ($actorId === null) {
                throw new AuditActorAttributionException(
                    "RBAC audit event '{$event}' requires a resolvable canonical Principal actor in context key '{$actorKey}'."
                );
            }

            $actor = Principal::find($actorId);

            if ($actor === null) {
                throw new AuditActorAttributionException(
                    "RBAC audit event '{$event}' references Principal #{$actorId}, which does not exist — a forged actor identifier is never trusted."
                );
            }
        }

        $subjectId = $context[$subjectIdKey] ?? null;

        unset($context[$actorKey], $context[$subjectIdKey]);

        $this->writer->record(new AuditEventInput(
            eventType: $eventType,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: $context,
        ));
    }

    /**
     * The F-01 denial event (security.authorization.denied — CRITICAL /
     * DENIAL_DURABLE). Emitted by an already-rolled-back denying call stack
     * (see RolePermissionService::grant()); a persistence failure propagates
     * to that caller, whose own reporting path handles it — DENY always
     * remains DENY regardless.
     */
    public function recordAuthorizationDenied(Principal $actor, ?int $subjectId, string $attemptedAction, string $denialReason): void
    {
        $this->writer->record(new AuditEventInput(
            eventType: 'security.authorization.denied',
            actor: $actor,
            subjectType: 'role',
            subjectId: $subjectId,
            metadata: [
                'attempted_action' => $attemptedAction,
                'denial_reason' => $denialReason,
            ],
        ));
    }
}
