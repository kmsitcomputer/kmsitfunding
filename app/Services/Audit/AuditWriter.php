<?php

namespace App\Services\Audit;

use App\Enums\AuditActorKind;
use App\Models\Audit\AuditRecord;
use App\Services\Audit\Exceptions\AuditActorAttributionException;
use App\Services\Audit\Exceptions\AuditIdempotencyConflictException;
use App\Services\Audit\Exceptions\AuditMetadataViolationException;
use App\Services\Audit\Exceptions\AuditSourceEventException;
use App\Services\Identity\AssuranceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * IMP-004 canonical AuditWriter/AuditSink — the ONE durable persistence
 * contract for security/governance audit evidence, replacing the deferred
 * log-channel scaffolding (IdentityAuditLogger/RbacAuditLogger now delegate
 * here). Synchronous, in-request/in-job DB write on the caller's connection;
 * the writer never opens/manages the enclosing transaction (the calling
 * service owns it, per Q26 and the existing IMP-003 pattern).
 *
 * Failure semantics are registry-derived:
 *  - CRITICAL (MUTATION_ATOMIC or DENIAL_DURABLE): persistence failure
 *    propagates to the caller (whose own transaction/reporting path handles
 *    it) — never silent.
 *  - NON_CRITICAL: failure is caught here, reported through the ordinary
 *    application error log tagged 'audit_write_failed', and never propagates,
 *    retries indefinitely, or queues for replay.
 */
final class AuditWriter
{
    /**
     * Implementation-time bound for the serialized metadata payload
     * ("oversized metadata" negative test; the exact byte limit is an
     * implementation-time parameter per the specification).
     */
    public const METADATA_MAX_BYTES = 8192;

    public function __construct(
        private readonly AuditEventRegistry $registry,
        private readonly CorrelationContext $correlation,
    ) {}

    public function record(AuditEventInput $input): ?AuditRecord
    {
        $definition = $this->registry->get($input->eventType, $input->eventVersion);

        [$actorPrincipalId, $actorKind, $executionContext] = $this->resolveActor($definition, $input);
        $metadata = $this->validateMetadata($definition, $input->metadata);
        [$sourceDomain, $sourceEventId] = $this->validateSourceEvent($definition, $input);

        $attributes = [
            'event_type' => $definition->eventType,
            'event_version' => $definition->eventVersion,
            'criticality' => $definition->criticality->value,
            'occurred_at' => now(),
            'actor_principal_id' => $actorPrincipalId,
            'actor_principal_kind' => $actorKind->value,
            'execution_context' => $executionContext,
            'subject_type' => $input->subjectType,
            'subject_id' => $input->subjectId,
            'request_id' => $this->correlation->requestId(),
            'correlation_id' => $this->correlation->correlationId(),
            'authentication_assurance' => $this->resolveAssurance($actorKind),
            'policy_version_ref' => $input->policyVersionRef,
            'source_domain' => $sourceDomain,
            'source_event_id' => $sourceEventId,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ];

        if ($sourceEventId !== null) {
            $existing = $this->findBySourceKey($sourceDomain, $sourceEventId, $definition->eventType);

            if ($existing !== null) {
                return $this->resolveIdempotentReplay($existing, $attributes);
            }
        }

        try {
            $record = new AuditRecord($attributes);
            $record->save();

            return $record;
        } catch (QueryException $e) {
            if ($sourceEventId !== null && $this->isUniqueViolation($e)) {
                // Legitimate concurrent retry resolved by the database's own
                // composite uniqueness — re-select and apply the same
                // immutable-payload comparison (never a pre-insert check alone).
                $existing = $this->findBySourceKey($sourceDomain, $sourceEventId, $definition->eventType);

                if ($existing !== null) {
                    return $this->resolveIdempotentReplay($existing, $attributes);
                }
            }

            return $this->handlePersistenceFailure($definition, $e);
        } catch (\Throwable $e) {
            return $this->handlePersistenceFailure($definition, $e);
        }
    }

    /**
     * @return array{0: ?int, 1: AuditActorKind, 2: ?string}
     */
    private function resolveActor(AuditEventDefinition $definition, AuditEventInput $input): array
    {
        if ($input->actor !== null) {
            if (! $definition->allowsPrincipalActor()) {
                throw new AuditActorAttributionException(
                    "Event '{$definition->eventType}' does not permit a Principal actor — its registry entry declares pre-principal attribution only."
                );
            }

            if (! $input->actor->exists || $input->actor->id === null) {
                throw new AuditActorAttributionException(
                    "Event '{$definition->eventType}' requires a resolved, persisted canonical Principal — an unpersisted instance is never trusted."
                );
            }

            $kind = AuditActorKind::from($input->actor->principal_kind->value);

            if (! in_array($kind, $definition->actorKinds, true)) {
                throw new AuditActorAttributionException(
                    "Event '{$definition->eventType}' does not permit actor kind '{$kind->value}'."
                );
            }

            return [$input->actor->id, $kind, null];
        }

        $prePrincipalKind = $definition->prePrincipalKind();

        if ($prePrincipalKind === null) {
            throw new AuditActorAttributionException(
                "Event '{$definition->eventType}' requires a canonical Principal actor — deterministic execution-context attribution cannot be bypassed into an ordinary NULL."
            );
        }

        return [null, $prePrincipalKind, $definition->executionContext];
    }

    /**
     * Allow-list validation: any key outside the event's declared allow-list
     * RAISES (never silently dropped); values are bounded scalars/arrays of
     * scalars only — never Request/Model/exception/header/credential material.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function validateMetadata(AuditEventDefinition $definition, array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $definition->metadataAllowList)) {
                throw new AuditMetadataViolationException(
                    "Metadata key '{$key}' is not in the allow-list for event '{$definition->eventType}'."
                );
            }

            if ($value !== null) {
                $this->assertValueShape($key, $value, $definition->metadataAllowList[$key]);
            }
        }

        $serialized = json_encode($metadata);

        if ($serialized === false) {
            throw new AuditMetadataViolationException(
                "Metadata for event '{$definition->eventType}' is not JSON-serializable."
            );
        }

        if (strlen($serialized) > self::METADATA_MAX_BYTES) {
            throw new AuditMetadataViolationException(
                "Metadata for event '{$definition->eventType}' exceeds the ".self::METADATA_MAX_BYTES.'-byte bound.'
            );
        }

        return $metadata;
    }

    private function assertValueShape(string $key, mixed $value, string $type): void
    {
        $valid = match ($type) {
            'int' => is_int($value),
            'string' => is_string($value),
            'array' => is_array($value) && $this->isFlatScalarList($value),
            default => false,
        };

        if (! $valid) {
            throw new AuditMetadataViolationException(
                "Metadata key '{$key}' must be of type '{$type}' — objects, models, requests, exceptions, and unrestricted (nested/associative) structures are never persisted."
            );
        }
    }

    /**
     * IMP004-IMPL-M02: an `'array'`-typed allow-list value is permitted ONLY
     * as a flat, sequential (never associative) list of scalars/null —
     * strictest behavior compatible with the approved specification, per
     * "prefer bounded list of permitted scalar values rather than arbitrary
     * associative nested objects unless specification explicitly allows
     * structured nested metadata" (it does not). Rejecting ANY nested array
     * outright — regardless of its own keys — closes the prior bypass where
     * a hard-prohibited key (e.g. 'password') could be smuggled inside a
     * nested associative element, since HARD_PROHIBITED_METADATA_KEYS is
     * only ever checked against the top-level allow-list's own keys.
     *
     * @param  array<mixed>  $value
     */
    private function isFlatScalarList(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Conditional source_domain requirement (IMP004-REAUDIT-m01): enforced
     * pre-insert by the writer, never left to DB NULL-uniqueness semantics.
     * source_domain is validated against the event's own registry-declared
     * idempotency policy — untrusted input can never assign an arbitrary one.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function validateSourceEvent(AuditEventDefinition $definition, AuditEventInput $input): array
    {
        $sourceEventId = $input->sourceEventId !== null ? trim($input->sourceEventId) : null;
        $sourceEventId = $sourceEventId === '' ? null : $sourceEventId;
        $sourceDomain = $input->sourceDomain !== null ? trim($input->sourceDomain) : null;
        $sourceDomain = $sourceDomain === '' ? null : $sourceDomain;

        if ($sourceEventId !== null && $sourceDomain === null) {
            throw new AuditSourceEventException(
                "source_event_id requires source_domain — an invalid partial pair is rejected before any insert (event '{$definition->eventType}')."
            );
        }

        if ($sourceEventId !== null && mb_strlen($sourceEventId) > 191) {
            throw new AuditSourceEventException('source_event_id exceeds its bounded maximum length.');
        }

        if ($sourceDomain !== null) {
            if ($definition->sourceDomain === null) {
                throw new AuditSourceEventException(
                    "Event '{$definition->eventType}' declares no source-domain/idempotency policy — a source namespace cannot be supplied for it."
                );
            }

            if ($sourceDomain !== $definition->sourceDomain || ! in_array($sourceDomain, $this->registry->knownSourceDomains(), true)) {
                throw new AuditSourceEventException(
                    "source_domain '{$sourceDomain}' is not the registry-declared trusted producer namespace for event '{$definition->eventType}'."
                );
            }
        }

        return [$sourceDomain, $sourceEventId];
    }

    private function findBySourceKey(string $sourceDomain, string $sourceEventId, string $eventType): ?AuditRecord
    {
        return AuditRecord::query()
            ->where('source_domain', $sourceDomain)
            ->where('source_event_id', $sourceEventId)
            ->where('event_type', $eventType)
            ->first();
    }

    /**
     * Legitimate retry: same scoped key + same immutable canonical fact →
     * return the EXISTING record, no duplicate row. Conflicting reuse →
     * reject loudly; the original row is never overwritten.
     *
     * IMP004-IMPL-M04: the comparison must cover every immutable canonical
     * field the specification defines as part of "the fact", not a partial
     * subset — event identity/version, actor/principal attribution,
     * subject/resource, source-event scoping, the metadata's canonical
     * (JSON) representation, and policy/version + execution-context
     * attribution. A payload that differs in ANY of these is a conflicting
     * reuse of the scoped key, never a silent return of the existing row.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveIdempotentReplay(AuditRecord $existing, array $attributes): AuditRecord
    {
        $immutableMatch = $existing->event_type === $attributes['event_type']
            && $existing->event_version === $attributes['event_version']
            && $existing->source_domain === $attributes['source_domain']
            && $existing->source_event_id === $attributes['source_event_id']
            && $existing->actor_principal_id === $attributes['actor_principal_id']
            && $existing->actor_principal_kind === $attributes['actor_principal_kind']
            && $existing->execution_context === $attributes['execution_context']
            && $existing->subject_type === $attributes['subject_type']
            && $existing->subject_id === $attributes['subject_id']
            && $existing->policy_version_ref === $attributes['policy_version_ref']
            && $this->canonicalMetadataJson($existing->getRawOriginal('metadata')) === $this->canonicalMetadataJson(
                $attributes['metadata'] === null ? null : json_encode($attributes['metadata'])
            );

        if (! $immutableMatch) {
            throw new AuditIdempotencyConflictException(
                "Idempotency conflict for ({$attributes['source_domain']}, {$attributes['source_event_id']}, {$attributes['event_type']}) — the scoped key was reused with incompatible core attribution."
            );
        }

        return $existing;
    }

    /**
     * Normalizes a possibly-differently-ordered JSON metadata payload to a
     * canonical comparable form — key ORDER must never matter for the
     * idempotency comparison, only the actual content.
     */
    private function canonicalMetadataJson(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return $json;
        }

        ksort($decoded);

        return json_encode($decoded);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        // MySQL 1062 (duplicate entry); SQLite 19/2067 (SQLITE_CONSTRAINT /
        // SQLITE_CONSTRAINT_UNIQUE) — driver-agnostic, mirroring the IMP-003
        // remediation pattern for driver-specific SQL detail.
        return in_array($driverCode, [1062, 19, 2067], true);
    }

    private function resolveAssurance(AuditActorKind $actorKind): ?string
    {
        if ($actorKind !== AuditActorKind::Human) {
            return null;
        }

        return app(AssuranceService::class)->level();
    }

    /**
     * @return never-return — CRITICAL failure always propagates; NON_CRITICAL
     *                      failure is reported (never silent) and swallowed by contract.
     */
    private function handlePersistenceFailure(AuditEventDefinition $definition, \Throwable $e): ?AuditRecord
    {
        if ($definition->isCritical()) {
            throw $e;
        }

        Log::error('audit_write_failed', [
            'event_type' => $definition->eventType,
            'event_version' => $definition->eventVersion,
            'criticality' => $definition->criticality->value,
            'persistence_strategy' => $definition->persistenceStrategy?->value,
            'exception' => $e::class,
        ]);

        return null;
    }
}
