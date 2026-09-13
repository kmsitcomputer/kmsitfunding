<?php

namespace App\Services\Audit;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditPersistenceStrategy;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\Exceptions\UnregisteredAuditEventException;

/**
 * IMP-004 canonical, versioned audit event registry — the single, explicit
 * registry every canonical event identifier must be registered in before it
 * can be emitted (mirroring the PermissionRegistry pattern). Keyed by
 * (event_type, event_version); an unregistered pair is a hard error at write
 * time and at read time (fail-closed).
 *
 * Inventory (IMP004-SPEC-M01): 19 Identity + 9 RBAC = 28 migrated events,
 * + 1 new denial event = 29 canonical active/target events, + 5 reserved
 * catalog events (registered, never emitted by any current runtime path),
 * + governance.audit.purged (the purge-evidence event shape the specification's
 * Retention/Terminal-Purge-Rule sections require IMP-004 to register; no
 * runtime purge path emits it yet) = 35 registry entries.
 */
final class AuditEventRegistry
{
    /**
     * Hard-prohibited metadata categories (IMP-004 "Redaction / Safe
     * Serialization"): never permitted in ANY event's allow-list, enforced
     * here at registration time as a registry-level constraint — a hashed
     * derivative is NOT exempt.
     *
     * @var array<int, string>
     */
    private const HARD_PROHIBITED_METADATA_KEYS = [
        'password',
        'password_confirmation',
        'password_hash',
        'current_password',
        'token',
        'plain_token',
        'verification_token',
        'csrf_token',
        'session_id',
        'session_token',
        'cookie',
        'cookies',
        'authorization',
        'authorization_header',
        'bearer_token',
        'api_token',
        'api_key',
        'api_credential',
        'api_credentials',
        'client_secret',
        'provider_secret',
        'webhook_secret',
        'private_key',
        'totp_secret',
        'mfa_secret',
        'secret',
        'recovery_code',
        'recovery_codes',
        'payment_credential',
        'payment_credentials',
        'card_number',
        'card_cvv',
    ];

    /** @var array<string, AuditEventDefinition> */
    private array $definitions = [];

    public function __construct()
    {
        $this->registerCanonicalEvents();
    }

    public function register(AuditEventDefinition $definition): void
    {
        $prohibited = array_intersect(
            array_map('strtolower', array_keys($definition->metadataAllowList)),
            self::HARD_PROHIBITED_METADATA_KEYS,
        );

        if ($prohibited !== []) {
            throw new \InvalidArgumentException(
                "Event '{$definition->eventType}' declares hard-prohibited metadata keys: ".implode(', ', $prohibited)
            );
        }

        foreach ($definition->financialReferenceFields as $field) {
            if (! array_key_exists($field, $definition->metadataAllowList)) {
                throw new \InvalidArgumentException(
                    "Event '{$definition->eventType}' declares financial_reference field '{$field}' that is not in its own metadata allow-list."
                );
            }
        }

        if ($definition->prePrincipalKind() !== null && $definition->executionContext === null) {
            throw new \InvalidArgumentException(
                "Event '{$definition->eventType}' declares a pre-principal actor kind without a fixed execution_context."
            );
        }

        if ($definition->isCritical() && $definition->persistenceStrategy === null) {
            throw new \InvalidArgumentException(
                "Event '{$definition->eventType}' is CRITICAL but declares no persistence_strategy."
            );
        }

        if (! $definition->isCritical() && $definition->persistenceStrategy !== null) {
            throw new \InvalidArgumentException(
                "Event '{$definition->eventType}' is NON_CRITICAL — it uses neither persistence strategy."
            );
        }

        $this->definitions[$this->key($definition->eventType, $definition->eventVersion)] = $definition;
    }

    public function find(string $eventType, int $eventVersion): ?AuditEventDefinition
    {
        return $this->definitions[$this->key($eventType, $eventVersion)] ?? null;
    }

    /**
     * @throws UnregisteredAuditEventException
     */
    public function get(string $eventType, int $eventVersion): AuditEventDefinition
    {
        return $this->find($eventType, $eventVersion)
            ?? throw new UnregisteredAuditEventException(
                "Audit event '{$eventType}' v{$eventVersion} is not registered in the canonical AuditEventRegistry — unregistered canonical events are rejected."
            );
    }

    /**
     * @return array<int, AuditEventDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * The closed set of trusted producer namespaces known to the registry
     * (derived from registered entries — never caller-extensible at runtime).
     *
     * @return array<int, string>
     */
    public function knownSourceDomains(): array
    {
        $domains = [];

        foreach ($this->definitions as $definition) {
            if ($definition->sourceDomain !== null) {
                $domains[$definition->sourceDomain] = true;
            }
        }

        return array_keys($domains);
    }

    private function key(string $eventType, int $eventVersion): string
    {
        return $eventType.'@'.$eventVersion;
    }

    private function registerCanonicalEvents(): void
    {
        $C = AuditCriticality::Critical;
        $NC = AuditCriticality::NonCritical;
        $MA = AuditPersistenceStrategy::MutationAtomic;
        $DD = AuditPersistenceStrategy::DenialDurable;
        $GENERAL = AuditVisibilityClass::General;
        $SEC = AuditVisibilityClass::Security;
        $GP = ScopeType::GlobalPlatform;
        $H = [AuditActorKind::Human];
        $HSI = [AuditActorKind::Human, AuditActorKind::System, AuditActorKind::Integration];

        $events = [
            // --- Identity (19 migrated events, IMP-002) ---
            new AuditEventDefinition('identity.user.created', 1, $C, $MA, $GENERAL, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.user.self_registered', 1, $C, $MA, $GENERAL, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            // Issuer/revoker may legitimately be NULL (Q22 — e.g. a
            // system-issued invitation with no administering Human) —
            // PrePrincipalSystem is the fallback actor kind IdentityAuditLogger
            // uses when $user is null; the fixed execution_context names this
            // specific, already-permitted no-issuer path.
            new AuditEventDefinition('identity.invitation.issued', 1, $C, $MA, $GENERAL, 'invitation', false, [
                'invitation_public_id' => 'string',
                'invited_actor' => 'string',
                'user_public_id' => 'string',
            ], [AuditActorKind::Human, AuditActorKind::PrePrincipalSystem], 'system:invitation_no_issuer', false, $GP),
            new AuditEventDefinition('identity.invitation.revoked', 1, $C, $MA, $GENERAL, 'invitation', false, [
                'invitation_public_id' => 'string',
                'user_public_id' => 'string',
            ], [AuditActorKind::Human, AuditActorKind::PrePrincipalSystem], 'system:invitation_no_issuer', false, $GP),
            new AuditEventDefinition('identity.invitation.accepted', 1, $C, $MA, $GENERAL, 'invitation', false, [
                'invitation_public_id' => 'string',
                'invited_actor' => 'string',
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.session.login_succeeded', 1, $NC, null, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            // IMP004-SPEC-M02: actor is UNAUTHENTICATED regardless of whether the
            // attempted email resolves to a real user — authentication failed, so
            // nothing proves who was typing. Subject may be NULL (unknown email).
            new AuditEventDefinition('identity.session.login_failed', 1, $NC, null, $SEC, 'user', true, [
                'reason' => 'string',
                'user_public_id' => 'string',
            ], [AuditActorKind::Unauthenticated], 'http:login_attempt', false, $GP),
            new AuditEventDefinition('identity.session.logout', 1, $NC, null, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.credential.password_changed', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.credential.password_reset_completed', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.email.change_requested', 1, $C, $MA, $GENERAL, 'user', false, [
                'generation' => 'int',
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.email.change_completed', 1, $C, $MA, $GENERAL, 'user', false, [
                'generation' => 'int',
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.email.change_conflicted', 1, $C, $MA, $SEC, 'user', false, [
                'generation' => 'int',
                'conflict_reason_code' => 'string',
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            // IMP004-SPEC-M03: reclassified CRITICAL/MUTATION_ATOMIC — the
            // email_verified_at state mutation and the audit append are atomic.
            new AuditEventDefinition('identity.email.verified', 1, $C, $MA, $GENERAL, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.mfa.enrolled', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.mfa.recovery_code_used', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.mfa.recovery_codes_regenerated', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            new AuditEventDefinition('identity.mfa.reset_or_disabled', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], $H, null, false, $GP),
            // IMP004-SPEC-M02 ‡: actor is PRE_PRINCIPAL_SYSTEM — no canonical
            // Principal exists for anyone at this point in the Q25 bootstrap
            // sequence. The newly-created user is the SUBJECT, never the actor.
            new AuditEventDefinition('identity.bootstrap.first_super_admin_completed', 1, $C, $MA, $SEC, 'user', false, [
                'user_public_id' => 'string',
            ], [AuditActorKind::PrePrincipalSystem], 'cli:identity:bootstrap-super-admin', false, $GP),

            // --- RBAC (9 migrated events, IMP-003) ---
            new AuditEventDefinition('rbac.role.assigned', 1, $C, $MA, $SEC, 'principal_role_assignment', false, [
                'target_principal_id' => 'int',
                'role_id' => 'int',
                'scope_type' => 'string',
                'scope_id' => 'int',
            ], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.role.revoked', 1, $C, $MA, $SEC, 'principal_role_assignment', false, [
                'principal_id' => 'int',
                'role_id' => 'int',
            ], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.authority.assigned', 1, $C, $MA, $SEC, 'authority_assignment', false, [
                'target_principal_id' => 'int',
                'authority_type_id' => 'int',
                'scope_type' => 'string',
                'scope_id' => 'int',
            ], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.authority.revoked', 1, $C, $MA, $SEC, 'authority_assignment', false, [
                'principal_id' => 'int',
                'authority_type_id' => 'int',
            ], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.role_permission.granted', 1, $C, $MA, $SEC, 'role_permission', false, [
                'role_id' => 'int',
                'permission_id' => 'int',
            ], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.role_permission.revoked', 1, $C, $MA, $SEC, 'role_permission', false, [
                'role_id' => 'int',
                'permission_id' => 'int',
            ], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.principal.human_tombstoned', 1, $C, $MA, $SEC, 'principal', false, [], $HSI, null, false, $GP),
            new AuditEventDefinition('rbac.principal.non_human_deactivated', 1, $C, $MA, $SEC, 'principal', false, [
                'kind' => 'string',
                'system_principal_id' => 'int',
                'integration_principal_id' => 'int',
            ], $HSI, null, false, $GP),
            // The bridge runs inside the locked Q25 bootstrap sequence, invoked
            // by an operator for whom no canonical Principal exists; IMP-003
            // attributes the assignment to the bootstrap event itself
            // ("Deliberately NULL ... not to any self-grant"), so the actor is
            // PRE_PRINCIPAL_SYSTEM — never the target Principal.
            new AuditEventDefinition('rbac.super_admin.canonically_authorized', 1, $C, $MA, $SEC, 'principal', false, [
                'role' => 'string',
                'scope_type' => 'string',
                'granted' => 'array',
                'not_granted' => 'array',
            ], [AuditActorKind::PrePrincipalSystem], 'cli:rbac:bridge-first-super-admin', false, $GP),

            // --- New denial event (F-01 disposition; IMP004-SPEC-M04) ---
            new AuditEventDefinition('security.authorization.denied', 1, $C, $DD, $SEC, 'role', true, [
                'attempted_action' => 'string',
                'denial_reason' => 'string',
            ], $HSI, null, true, $GP),

            // --- Reserved catalog events (F-01: registered, never emitted by
            // any current runtime path — a future admin catalog-management
            // capability may emit them without a taxonomy change) ---
            new AuditEventDefinition('rbac.role.registered', 1, $C, $MA, $SEC, 'role', false, [], $HSI, null, false, $GP, [], false, null, true),
            new AuditEventDefinition('rbac.role.retired', 1, $C, $MA, $SEC, 'role', false, [], $HSI, null, false, $GP, [], false, null, true),
            new AuditEventDefinition('rbac.permission.registered', 1, $C, $MA, $SEC, 'permission', false, [], $HSI, null, false, $GP, [], false, null, true),
            new AuditEventDefinition('rbac.permission.deprecated', 1, $C, $MA, $SEC, 'permission', false, [], $HSI, null, false, $GP, [], false, null, true),
            new AuditEventDefinition('rbac.authority_type.registered', 1, $C, $MA, $SEC, 'authority_type', false, [], $HSI, null, false, $GP, [], false, null, true),

            // --- Purge governance evidence (Q28 / Terminal Purge-Evidence
            // Rule; the event shape IMP-004 must provide — no runtime purge
            // path exists yet, so it is registered as reserved/unemitted) ---
            new AuditEventDefinition('governance.audit.purged', 1, $C, $MA, $SEC, 'audit_record', true, [
                'purge_batch_id' => 'string',
                'purged_count' => 'int',
            ], $HSI, null, false, $GP, [], false, null, true),
        ];

        foreach ($events as $event) {
            $this->register($event);
        }
    }
}
