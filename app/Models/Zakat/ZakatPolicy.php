<?php

namespace App\Models\Zakat;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use App\Support\Database\GovernedPolicyBuilder;
use App\Support\Database\GovernedPolicyQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CR-001-B (Schema #6) — versioned Zakat policy per Zakat type
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 20/55). `rate` is a dimensionless ratio (DECIMAL), never a
 * monetary amount — actual values belong to IMP-019, never invented here.
 *
 * CODEX-CR001B-02 remediation (Round 1 + Round 2) — fail-closed
 * governance across every supported Eloquent persistence path, not an
 * optional convention:
 *   - `creating`: a row may ONLY ever be inserted with `status = 'DRAFT'`
 *     through ordinary persistence (`Model::create()`, `new self`+`save()`).
 *     There is no flag or parameter that lifts this — the ONLY way a row
 *     becomes ACTIVE (or any other status) is
 *     App\Services\Zakat\ZakatPolicyVersioningService's own internal,
 *     validated `DB::table(...)` transition step (a controlled detail of
 *     the trusted service, not a publicly reachable bypass).
 *   - `saving`: `effective_until` (if set) must not be before
 *     `effective_from` — enforced for every create/update, not only the
 *     service's own validation.
 *   - `save()`: once a row has left DRAFT, it is immutable outright; while
 *     still DRAFT, ordinary edits are allowed EXCEPT changing `status`
 *     away from `'DRAFT'` — that transition is exclusively authoritative.
 *   - `newEloquentBuilder()` returns GovernedPolicyBuilder, which closes
 *     the identical set of bypasses at the query-builder level (mass
 *     `update()`/`delete()` against historical rows, `status` changes via
 *     `update()`, `upsert()`/`increment()`/etc.) — see that class's
 *     docblock for the full framework-source-verified method enumeration.
 *   - `newBaseQueryBuilder()` (Round 3) returns GovernedPolicyQueryBuilder,
 *     closing the `toBase()`/`getQuery()` escape that returned an
 *     UNGUARDED base query builder in Round 1/2 — including the
 *     forwarded `insert()`/`insertGetId()`/`insertOrIgnore()` bulk-insert
 *     paths Round 2 missed, which bypass the `creating` event entirely
 *     and could otherwise create an ungoverned ACTIVE row directly.
 */
class ZakatPolicy extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'rate' => 'decimal:6',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $policy): void {
            if (($policy->status ?? null) !== 'DRAFT') {
                throw new \LogicException(
                    'ZakatPolicy rows may only be created with status DRAFT through ordinary persistence; '.
                    'activation/publication is exclusively performed by ZakatPolicyVersioningService.'
                );
            }
        });

        static::saving(function (self $policy): void {
            if ($policy->effective_until !== null && $policy->effective_until->lt($policy->effective_from)) {
                throw new \InvalidArgumentException('effective_until must not be before effective_from.');
            }
        });
    }

    public function newEloquentBuilder($query): GovernedPolicyBuilder
    {
        return new GovernedPolicyBuilder($query);
    }

    protected function newBaseQueryBuilder(): GovernedPolicyQueryBuilder
    {
        return new GovernedPolicyQueryBuilder($this->getConnection());
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function save(array $options = []): bool
    {
        if ($this->exists && $this->getOriginal('status') !== 'DRAFT') {
            throw new \LogicException('ZakatPolicy rows are immutable once published (non-DRAFT); create a new version via ZakatPolicyVersioningService instead.');
        }

        if ($this->exists && $this->getOriginal('status') === 'DRAFT' && $this->status !== 'DRAFT') {
            throw new \LogicException('Transitioning a ZakatPolicy out of DRAFT through ordinary persistence is not permitted; use ZakatPolicyVersioningService.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        if ($this->getOriginal('status') !== 'DRAFT') {
            throw new \LogicException('ZakatPolicy rows are immutable once published (non-DRAFT) and cannot be deleted.');
        }

        return parent::delete();
    }

    public function zakatType(): BelongsTo
    {
        return $this->belongsTo(ZakatType::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ZakatCalculationSnapshot::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'created_by_principal_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'updated_by_principal_id');
    }
}
