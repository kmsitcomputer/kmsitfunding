<?php

namespace App\Models\Audit;

use App\Services\Audit\Exceptions\AuditRecordImmutableException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent builder for AuditRecord with the append-only contract enforced:
 * mass update() and delete() through the ordinary Eloquent path are blocked.
 * Read/query operations are unaffected.
 */
class AuditRecordQueryBuilder extends Builder
{
    public function update(array $values): int
    {
        throw new AuditRecordImmutableException(
            'Canonical audit records are append-only — mass UPDATE is prohibited (Q28).'
        );
    }

    public function delete(): mixed
    {
        throw new AuditRecordImmutableException(
            'Canonical audit records are append-only — mass DELETE is prohibited outside an authorized governed retention purge (Q28).'
        );
    }
}
