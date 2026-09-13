<?php

namespace App\Services\Audit;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditPersistenceStrategy;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;

/**
 * One canonical registry entry, keyed by (event_type, event_version). The
 * registry — never a runtime caller — owns every security attribute here:
 * criticality, persistence strategy, metadata allow-list, visibility class,
 * actor constraints, execution context, scope, assurance requirement,
 * financial-reference classification, and source-domain/idempotency policy
 * (IMP-004 "Registry Contract Consistency").
 *
 * Entries are immutable code. An incompatible contract change requires a NEW
 * event_version entry; an existing (event_type, event_version) entry is never
 * edited in place ("Historical Reproducibility of Visibility").
 */
final class AuditEventDefinition
{
    /**
     * @param  array<string, 'int'|'string'|'array'>  $metadataAllowList  permitted metadata keys and their scalar shapes
     * @param  array<int, AuditActorKind>  $actorKinds  actor kinds this event may legitimately use
     * @param  array<int, string>  $financialReferenceFields  allow-listed metadata keys that, when populated, additionally require audit.read.financial_reference
     */
    public function __construct(
        public readonly string $eventType,
        public readonly int $eventVersion,
        public readonly AuditCriticality $criticality,
        public readonly ?AuditPersistenceStrategy $persistenceStrategy,
        public readonly AuditVisibilityClass $visibilityClass,
        public readonly string $subjectType,
        public readonly bool $subjectIdNullable,
        public readonly array $metadataAllowList,
        public readonly array $actorKinds,
        public readonly ?string $executionContext,
        public readonly bool $requiresElevatedAssuranceToRead,
        public readonly ScopeType $scopeType,
        public readonly array $financialReferenceFields = [],
        public readonly bool $subjectIsFinancialReference = false,
        public readonly ?string $sourceDomain = null,
        public readonly bool $reserved = false,
    ) {}

    public function isCritical(): bool
    {
        return $this->criticality === AuditCriticality::Critical;
    }

    public function allowsPrincipalActor(): bool
    {
        foreach ($this->actorKinds as $kind) {
            if ($kind->requiresPrincipal()) {
                return true;
            }
        }

        return false;
    }

    public function prePrincipalKind(): ?AuditActorKind
    {
        foreach ($this->actorKinds as $kind) {
            if (! $kind->requiresPrincipal()) {
                return $kind;
            }
        }

        return null;
    }
}
