<?php

namespace App\Models\Fidyah;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use App\Support\Database\AppendOnlyBuilder;
use App\Support\Database\AppendOnlyQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CR-001-B (Schema #11) — immutable Fidyah calculation record, mirroring
 * ZakatCalculationSnapshot's immutability guard exactly
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 20/55).
 *
 * CODEX-CR001B-01 remediation (defense-in-depth, Round 1-3): see
 * App\Models\Zakat\ZakatCalculationSnapshot's docblock for the full
 * rationale — `save()`/`delete()` cover instance-level paths,
 * `newEloquentBuilder()` (App\Support\Database\AppendOnlyBuilder) covers
 * every Eloquent-level mutation method, and `newBaseQueryBuilder()`
 * (App\Support\Database\AppendOnlyQueryBuilder, Round 3) closes the
 * `toBase()`/`getQuery()` escape hatch. A MySQL/SQLite trigger pair in
 * this table's migration is an OPTIONAL, non-gating database-layer
 * backstop.
 */
class FidyahCalculationSnapshot extends Model
{
    use GeneratesUlid;

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function newEloquentBuilder($query): AppendOnlyBuilder
    {
        return new AppendOnlyBuilder($query);
    }

    protected function newBaseQueryBuilder(): AppendOnlyQueryBuilder
    {
        return new AppendOnlyQueryBuilder($this->getConnection());
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('FidyahCalculationSnapshot rows are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('FidyahCalculationSnapshot rows are append-only historical evidence and cannot be deleted.');
    }

    public function fidyahPolicy(): BelongsTo
    {
        return $this->belongsTo(FidyahPolicy::class);
    }

    public function actingPrincipal(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'acting_principal_id');
    }
}
