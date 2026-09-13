<?php

namespace App\Services\Audit;

use App\Enums\AuditVisibilityClass;
use App\Models\Audit\AuditRecord;
use App\Models\Rbac\Principal;
use App\Services\Rbac\AuthorizationEvaluator;
use App\Services\Rbac\AuthorizationRequest;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\ScopeResolverRegistry;

/**
 * IMP-004 audit-read authorization foundation (Q27 / IMP004-SPEC-M05).
 * Every record read passes the full canonical formula — Authenticated AND
 * Applicable Subject Context AND Permission AND Domain-Aware Scope AND
 * Ownership/Subject Rule where applicable AND Business Authority where
 * applicable AND Authentication Assurance where required AND No Security
 * Restriction — evaluated exclusively through the existing
 * AuthorizationEvaluator. Default: DENY.
 *
 * Every read requirement (visibility class, required permission, scope,
 * assurance) is resolved from the immutable registry entry for the record's
 * own persisted (event_type, event_version) — never supplied or chosen by
 * the reading caller, so a future registry edit can never silently broaden
 * how an already-written record is read ("Historical Reproducibility of
 * Visibility").
 *
 * audit.read.financial_reference is NOT financial business authority: it only
 * participates in authorizing otherwise-authorized audit evidence containing
 * protected financial references — it grants no access to the referenced
 * financial resource itself, and it never bypasses scope.
 */
final class AuditReadAuthorizer
{
    public function __construct(
        private readonly AuditEventRegistry $registry,
        private readonly AuthorizationEvaluator $evaluator,
        private readonly ScopeResolverRegistry $scopes,
    ) {}

    public function canRead(Principal $reader, AuditRecord $record): bool
    {
        // Fail-closed: a record whose (event_type, event_version) is not
        // registered can never be read.
        $definition = $this->registry->find($record->event_type, $record->event_version);

        if ($definition === null) {
            return false;
        }

        // Fail-closed: a registry-classified scope type with no registered
        // resolver denies, exactly like assignment write-time.
        $scopeResolver = $this->scopes->get($definition->scopeType);

        if ($scopeResolver === null) {
            return false;
        }

        $requiredPermissions = [$definition->visibilityClass->requiredPermission()];

        if ($this->hasFinancialReference($definition, $record)) {
            $requiredPermissions[] = PermissionRegistry::AUDIT_READ_FINANCIAL_REFERENCE;
        }

        foreach (array_unique($requiredPermissions) as $permission) {
            $allowed = $this->evaluator->evaluate(new AuthorizationRequest(
                principal: $reader,
                permissionCode: $permission,
                resource: $record,
                scopeResolver: $scopeResolver,
                requiresElevatedAssurance: $definition->requiresElevatedAssuranceToRead,
            ));

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uniform financial-reference detection (IMP004-SPEC-M05): the
     * requirement applies regardless of WHERE the protected reference
     * appears — primary subject, secondary subject, or any allow-listed
     * registry-declared metadata field. There is no code path that only
     * checks the primary subject_type.
     */
    private function hasFinancialReference(AuditEventDefinition $definition, AuditRecord $record): bool
    {
        if ($definition->visibilityClass === AuditVisibilityClass::FinancialReference) {
            return true;
        }

        if ($definition->subjectIsFinancialReference && $record->subject_id !== null) {
            return true;
        }

        $metadata = $record->metadata ?? [];

        foreach ($definition->financialReferenceFields as $field) {
            if (array_key_exists($field, $metadata) && $metadata[$field] !== null) {
                return true;
            }
        }

        return false;
    }
}
