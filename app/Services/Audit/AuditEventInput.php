<?php

namespace App\Services\Audit;

use App\Models\Rbac\Principal;

/**
 * The occurrence-specific data a trusted emitting service supplies to the
 * canonical AuditWriter for one audit emission. Registry-owned security
 * attributes (criticality, persistence strategy, visibility class, metadata
 * allow-list, actor-kind constraints, execution context) are deliberately NOT
 * here — no caller can supply or override them.
 *
 * $actor is always container/context-resolved by the emitting service (the
 * authenticated Principal, the current job's System Principal, etc.) — never
 * an unvalidated request-supplied identifier. A null $actor is legitimate
 * only for events whose registry entry declares a pre-principal actor kind.
 */
final class AuditEventInput
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $eventType,
        public readonly ?Principal $actor,
        public readonly string $subjectType,
        public readonly ?int $subjectId,
        public readonly array $metadata = [],
        public readonly ?string $sourceDomain = null,
        public readonly ?string $sourceEventId = null,
        public readonly ?string $policyVersionRef = null,
        public readonly int $eventVersion = 1,
    ) {}
}
