<?php

namespace App\Services\Identity;

use App\Models\Rbac\Principal;
use App\Models\User;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;
use App\Services\Audit\Exceptions\AuditActorAttributionException;
use App\Services\Audit\Exceptions\UnregisteredAuditEventException;
use App\Services\Rbac\PrincipalService;

/**
 * Identity/Authentication audit emission points (see "Audit Events" in
 * docs/implementation/IMP-002-identity-authentication.md).
 *
 * IMP-004: this class is now a compatibility FACADE over the canonical
 * AuditWriter (docs/implementation/IMP-004-audit-governance-foundation.md
 * "Migration / Backward Compatibility") — the existing record() signature is
 * preserved so already-tested IMP-002 call sites require no change beyond the
 * sink substitution itself. It maps each legacy event name onto its canonical
 * registered counterpart; the log channel is no longer the canonical source.
 *
 * MUST NEVER be called with a verification token, password, TOTP secret,
 * recovery code, or raw database/exception detail in $context — the canonical
 * writer additionally hard-rejects any metadata key outside the event's
 * registry-declared allow-list.
 */
class IdentityAuditLogger
{
    /**
     * Legacy event => [canonical event_type, subject_type, actor mode].
     * Actor modes: 'principal' (resolve the canonical Principal for $user,
     * fail-closed if $user is null — IMP004-IMPL-M03: the pre-principal
     * actor catalog is NOT expanded to cover a null invitation issuer/
     * revoker; ordinary invitation issuance requires canonical Human
     * attribution, per InvitationService::issue()/revoke()'s non-nullable
     * $issuer/$revoker parameters — see docs/audits/IMP-004-OWNERSHIP-HANDOFF.md
     * "Remediation Pass 2" for the independent audit disposition);
     * 'unauthenticated' / 'pre_principal_system' (registry-declared
     * pre-principal attribution, actor always NULL, for the two
     * specifically pre-approved cases only — see IMP004-SPEC-M02).
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const EVENT_MAP = [
        'identity_created' => ['identity.user.created', 'user', 'principal'],
        'self_registration_completed' => ['identity.user.self_registered', 'user', 'principal'],
        'invitation_issued' => ['identity.invitation.issued', 'invitation', 'principal'],
        'invitation_revoked' => ['identity.invitation.revoked', 'invitation', 'principal'],
        'invitation_accepted' => ['identity.invitation.accepted', 'invitation', 'principal'],
        'login_succeeded' => ['identity.session.login_succeeded', 'user', 'principal'],
        'login_failed' => ['identity.session.login_failed', 'user', 'unauthenticated'],
        'logout' => ['identity.session.logout', 'user', 'principal'],
        'password_changed' => ['identity.credential.password_changed', 'user', 'principal'],
        'password_reset_completed' => ['identity.credential.password_reset_completed', 'user', 'principal'],
        'email_change_requested' => ['identity.email.change_requested', 'user', 'principal'],
        'email_change_completed' => ['identity.email.change_completed', 'user', 'principal'],
        'email_change_conflicted' => ['identity.email.change_conflicted', 'user', 'principal'],
        'email_verified' => ['identity.email.verified', 'user', 'principal'],
        'mfa_enrolled' => ['identity.mfa.enrolled', 'user', 'principal'],
        'mfa_recovery_code_used' => ['identity.mfa.recovery_code_used', 'user', 'principal'],
        'mfa_recovery_codes_regenerated' => ['identity.mfa.recovery_codes_regenerated', 'user', 'principal'],
        'mfa_reset_or_disabled' => ['identity.mfa.reset_or_disabled', 'user', 'principal'],
        'first_super_admin_bootstrap_completed' => ['identity.bootstrap.first_super_admin_completed', 'user', 'pre_principal_system'],
    ];

    public function __construct(
        private readonly AuditWriter $writer,
        private readonly PrincipalService $principals,
    ) {}

    public function record(string $event, ?User $user, array $context = []): void
    {
        $mapping = self::EVENT_MAP[$event] ?? throw new UnregisteredAuditEventException(
            "Identity audit event '{$event}' has no canonical registry mapping."
        );

        [$eventType, $subjectType, $actorMode] = $mapping;

        $actor = match ($actorMode) {
            'principal' => $this->resolvePrincipalActor($event, $user),
            default => null,
        };

        $subjectId = $subjectType === 'invitation'
            ? ($context['invitation_id'] ?? null)
            : $user?->id;
        unset($context['invitation_id']);

        if ($user !== null) {
            $context['user_public_id'] = $user->public_id;
        }

        $this->writer->record(new AuditEventInput(
            eventType: $eventType,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: $context,
        ));
    }

    /**
     * Human-decision (IMP-004 implementation): the registry requires a
     * resolved canonical Principal actor for these CRITICAL events — an
     * unattributed (null-user) emission is rejected fail-closed.
     */
    private function resolvePrincipalActor(string $event, ?User $user): Principal
    {
        if ($user === null) {
            throw new AuditActorAttributionException(
                "Identity audit event '{$event}' requires an attributed User to resolve a canonical Principal actor — an unattributed emission is rejected fail-closed."
            );
        }

        return $this->principals->forUser($user);
    }
}
