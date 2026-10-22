<?php

namespace App\Models\Zakat;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use App\Support\Database\AppendOnlyBuilder;
use App\Support\Database\AppendOnlyQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CR-001-B (Schema #9) — immutable Zakat calculation record
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 20/55). Append-only: no `updated_at` column exists, and `save()`/
 * `delete()` are guarded here to reject any attempt to mutate or remove an
 * already-persisted row — the only legal operation is a fresh INSERT via
 * `create()`. `acting_principal_id` is nullable: the calculator is
 * guest-usable and never itself creates a Payment Attempt (Section 22
 * boundary).
 *
 * CODEX-CR001B-01 remediation (defense-in-depth, Round 1-3): `save()`/
 * `delete()` cover every instance-level path (save, saveQuietly, update,
 * updateQuietly, touch — all funnel through `save()`/`delete()`
 * internally). `newEloquentBuilder()` returns App\Support\Database\
 * AppendOnlyBuilder, which blocks every Eloquent-level mutation method
 * (`update`, `updateOrInsert`, `delete`, `forceDelete`, `touch`,
 * `increment`/`decrement`/`incrementEach`/`decrementEach`, `upsert`).
 * Round 3: `newBaseQueryBuilder()` additionally returns
 * App\Support\Database\AppendOnlyQueryBuilder, closing the `toBase()`/
 * `getQuery()` escape hatch that returned an UNGUARDED base query
 * builder in Round 1/2 (`Eloquent\Builder::getQuery()`/`toBase()` hand
 * back `$this->query` verbatim — see AppendOnlyQueryBuilder's docblock
 * for why overriding those two public accessors directly would have
 * broken ordinary reads and `create()` instead of just closing the
 * escape). A MySQL/SQLite trigger pair in this table's migration
 * provides an OPTIONAL, non-gating database-layer backstop for any
 * write that reaches the connection outside this model's persistence
 * API altogether (see 2026_09_21_000008_create_zakat_calculation_snapshots_table).
 *
 * CODEX-CR001B-03 remediation: `nisab_policy_id` / `gold_price_reference_id`
 * are typed, nullable (not every Zakat type prices against gold),
 * RESTRICT-on-delete provenance references — arbitrary JSON alone is not
 * sufficient historical provenance.
 */
class ZakatCalculationSnapshot extends Model
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
            throw new \LogicException('ZakatCalculationSnapshot rows are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new \LogicException('ZakatCalculationSnapshot rows are append-only historical evidence and cannot be deleted.');
    }

    public function zakatType(): BelongsTo
    {
        return $this->belongsTo(ZakatType::class);
    }

    public function zakatPolicy(): BelongsTo
    {
        return $this->belongsTo(ZakatPolicy::class);
    }

    public function nisabPolicy(): BelongsTo
    {
        return $this->belongsTo(NisabPolicy::class);
    }

    public function goldPriceReference(): BelongsTo
    {
        return $this->belongsTo(GoldPriceReference::class);
    }

    public function actingPrincipal(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'acting_principal_id');
    }
}
