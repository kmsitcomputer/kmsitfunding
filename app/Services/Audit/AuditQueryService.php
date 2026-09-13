<?php

namespace App\Services\Audit;

use App\Models\Audit\AuditRecord;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Builder;

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
    /**
     * IMP004-IMPL-m01: a hard bound on how many raw candidate rows a single
     * search() call may ever scan while filling one page of AUTHORIZED
     * records — protects against an unbounded scan when a reader is
     * authorized for very few rows deep in a large table. A page that hits
     * this bound is returned short (never wrong, never blocked) rather than
     * degrading into an unbounded query.
     */
    private const MAX_SCAN_RECORDS = 5000;

    private const SCAN_BATCH_SIZE = 200;

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

        $baseQuery = $this->baseQuery($filter);

        // IMP004-IMPL-m01: authorization must be resolved BEFORE pagination
        // is applied, never after a fixed offset/limit fetch — a raw
        // skip()/take() page can contain any mix of authorized and
        // unauthorized rows, silently shrinking the returned page (an
        // unauthorized row must never occupy a "slot" that could otherwise
        // have surfaced a later authorized row) and making page boundaries
        // depend on the reader's own authorization, which is never stable
        // or deterministic across readers/requests. This performs a bounded,
        // cursor-based (keyset) scan that skips PAST already-authorized rows
        // belonging to earlier pages, then collects exactly one page's worth
        // of records the reader is actually authorized to read.
        $toSkip = ($page - 1) * $perPage;
        $authorized = [];
        $scanned = 0;
        $lastOccurredAt = null;
        $lastId = null;

        while (count($authorized) < $perPage && $scanned < self::MAX_SCAN_RECORDS) {
            $batchQuery = (clone $baseQuery);

            if ($lastId !== null) {
                $batchQuery->where(function ($q) use ($lastOccurredAt, $lastId) {
                    $q->where('occurred_at', '<', $lastOccurredAt)
                        ->orWhere(function ($q2) use ($lastOccurredAt, $lastId) {
                            $q2->where('occurred_at', $lastOccurredAt)->where('id', '<', $lastId);
                        });
                });
            }

            $batch = $batchQuery->take(self::SCAN_BATCH_SIZE)->get();

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $record) {
                $scanned++;
                $lastOccurredAt = $record->occurred_at;
                $lastId = $record->id;

                if (! $this->authorizer->canRead($reader, $record)) {
                    continue;
                }

                if ($toSkip > 0) {
                    $toSkip--;

                    continue;
                }

                $authorized[] = $record;

                if (count($authorized) >= $perPage) {
                    break 2;
                }
            }

            if ($scanned >= self::MAX_SCAN_RECORDS) {
                break;
            }
        }

        return [
            'records' => $authorized,
            'page' => $page,
            'per_page' => $perPage,
            'authorized_count' => count($authorized),
        ];
    }

    private function baseQuery(AuditQueryFilter $filter): Builder
    {
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

        return $query;
    }
}
