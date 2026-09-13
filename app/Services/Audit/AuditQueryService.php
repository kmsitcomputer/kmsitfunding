<?php

namespace App\Services\Audit;

use App\Models\Audit\AuditRecord;
use App\Models\Rbac\Principal;

/**
 * IMP-004 audit query foundation (backend only — no UI, no REST endpoint).
 * Every row returned has passed the full Q27 audit-read authorization formula
 * via AuditReadAuthorizer — there is no authorization-bypassing "internal"
 * query path. Results are paginated with a bounded page size; no unbounded
 * "return everything" path exists, and no existence/count metadata about
 * records the reader cannot see is exposed.
 *
 * This foundation exists so IMP-026 (Search/Reporting) and any future admin
 * UI can build on it; it implements neither.
 */
final class AuditQueryService
{
    public function __construct(
        private readonly AuditReadAuthorizer $authorizer,
    ) {}

    /**
     * @return array{records: array<int, AuditRecord>, page: int, per_page: int, authorized_count: int}
     */
    public function search(Principal $reader, AuditQueryFilter $filter): array
    {
        $perPage = max(1, min($filter->perPage, AuditQueryFilter::MAX_PER_PAGE));
        $page = max(1, $filter->page);

        $query = AuditRecord::query()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($filter->eventType !== null) {
            if (str_ends_with($filter->eventType, '.*')) {
                $query->where('event_type', 'like', substr($filter->eventType, 0, -1).'%');
            } else {
                $query->where('event_type', $filter->eventType);
            }
        }

        if ($filter->actorPrincipalId !== null) {
            $query->where('actor_principal_id', $filter->actorPrincipalId);
        }

        if ($filter->subjectType !== null) {
            $query->where('subject_type', $filter->subjectType);
        }

        if ($filter->subjectId !== null) {
            $query->where('subject_id', $filter->subjectId);
        }

        if ($filter->occurredFrom !== null) {
            $query->where('occurred_at', '>=', $filter->occurredFrom);
        }

        if ($filter->occurredTo !== null) {
            $query->where('occurred_at', '<=', $filter->occurredTo);
        }

        if ($filter->correlationId !== null) {
            $query->where('correlation_id', $filter->correlationId);
        }

        if ($filter->criticality !== null) {
            $query->where('criticality', $filter->criticality);
        }

        $candidates = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $authorized = [];

        foreach ($candidates as $record) {
            if ($this->authorizer->canRead($reader, $record)) {
                $authorized[] = $record;
            }
        }

        return [
            'records' => $authorized,
            'page' => $page,
            'per_page' => $perPage,
            'authorized_count' => count($authorized),
        ];
    }
}
