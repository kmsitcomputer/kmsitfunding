<?php

namespace App\Models\Fidyah;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use App\Support\Database\GovernedPolicyBuilder;
use App\Support\Database\GovernedPolicyQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CR-001-B (Schema #10) — versioned Fidyah rate-per-missed-fast-day policy
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 20/55). `rate_amount_minor` is a monetary amount, integer minor
 * units per Section 64 — actual values belong to IMP-019, never invented
 * here.
 *
 * CODEX-CR001B-02 remediation (Round 1-3): mirrors
 * App\Models\Zakat\ZakatPolicy's fail-closed governance exactly — see
 * that class's docblock for the full rationale, including Round 3's
 * `newBaseQueryBuilder()` closing the `toBase()`/`getQuery()` escape.
 * Writes go exclusively through App\Services\Fidyah\FidyahPolicyVersioningService.
 */
class FidyahPolicy extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'rate_amount_minor' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $policy): void {
            if (($policy->status ?? null) !== 'DRAFT') {
                throw new \LogicException(
                    'FidyahPolicy rows may only be created with status DRAFT through ordinary persistence; '.
                    'activation/publication is exclusively performed by FidyahPolicyVersioningService.'
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
            throw new \LogicException('FidyahPolicy rows are immutable once published (non-DRAFT); create a new version via FidyahPolicyVersioningService instead.');
        }

        if ($this->exists && $this->getOriginal('status') === 'DRAFT' && $this->status !== 'DRAFT') {
            throw new \LogicException('Transitioning a FidyahPolicy out of DRAFT through ordinary persistence is not permitted; use FidyahPolicyVersioningService.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        if ($this->getOriginal('status') !== 'DRAFT') {
            throw new \LogicException('FidyahPolicy rows are immutable once published (non-DRAFT) and cannot be deleted.');
        }

        return parent::delete();
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(FidyahCalculationSnapshot::class);
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
