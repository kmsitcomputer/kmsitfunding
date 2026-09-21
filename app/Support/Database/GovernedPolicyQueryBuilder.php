<?php

namespace App\Support\Database;

use Illuminate\Database\Query\Builder;

/**
 * CODEX-CR001B-02 remediation (Round 3) — closes the `toBase()`/
 * `getQuery()` escape hatch for versioned policy tables (ZakatPolicy,
 * FidyahPolicy), mirroring App\Support\Database\AppendOnlyQueryBuilder's
 * rationale exactly (see that class's docblock for why overriding the
 * public `toBase()`/`getQuery()` accessors themselves would break reads
 * and `create()`).
 *
 * `App\Models\Zakat\ZakatPolicy`/`App\Models\Fidyah\FidyahPolicy` override
 * `Model::newBaseQueryBuilder()` to hand back an instance of this class.
 * Every Eloquent-level write (`update`, `delete`, `insert`/`insertGetId`
 * via `create()`, `updateOrInsert`, …) ultimately reaches one of the
 * primitives guarded here — closing this one place closes
 * `ZakatPolicy::query()->toBase()->...`/`->getQuery()->...` the same way
 * it closes the ordinary Eloquent-level calls Round 1/2 already covered.
 *
 * Round 2 found `ZakatPolicy::query()->insert([...ACTIVE...])` bypasses
 * every Eloquent model event (no `creating` fires for a raw builder
 * insert) — closing THAT is why, unlike AppendOnlyQueryBuilder, this
 * class also value-checks every insert-family method: an insert whose
 * `status` is present and not `'DRAFT'` is refused. A model's own
 * `create()` always reaches this having already had `status` forced to
 * `'DRAFT'` by ZakatPolicy::booted()'s `creating` event (Round 2), so
 * this check never fires on that legitimate path — it only closes the
 * bypass that skips the model layer entirely.
 */
class GovernedPolicyQueryBuilder extends Builder
{
    public function insert(array $values): bool
    {
        $this->denyIfAnyRowIsNotDraft($values, 'INSERT');

        return parent::insert($values);
    }

    public function insertGetId(array $values, $sequence = null): int
    {
        $this->denyIfAnyRowIsNotDraft($values, 'INSERT');

        return parent::insertGetId($values, $sequence);
    }

    public function insertOrIgnore(array $values): int
    {
        $this->denyIfAnyRowIsNotDraft($values, 'INSERT');

        return parent::insertOrIgnore($values);
    }

    /**
     * CODEX-CR001B-02 remediation (Round 4): matches
     * Illuminate\Database\Query\Builder::insertOrIgnoreReturning()'s
     * installed Laravel 13.31.0 signature exactly, including the
     * `array|string|null $uniqueBy` parameter type. The Round 3 version
     * of this override declared `: array` as its return type, but the
     * parent method actually returns `\Illuminate\Support\Collection` —
     * a real INSERT could have already been committed by `parent::`
     * before PHP's return-type check threw a TypeError on the way back
     * out, which would have looked like a silent partial success. No
     * return type is declared here now, matching the parent (which also
     * declares none, only a docblock `@return \Illuminate\Support\Collection`).
     */
    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        $this->denyIfAnyRowIsNotDraft($values, 'INSERT');

        return parent::insertOrIgnoreReturning($values, $returning, $uniqueBy);
    }

    public function insertUsing(array $columns, $query): int
    {
        throw new \LogicException($this->message('insertUsing'));
    }

    public function insertOrIgnoreUsing(array $columns, $query): int
    {
        throw new \LogicException($this->message('insertOrIgnoreUsing'));
    }

    public function update(array $values): int
    {
        if (array_key_exists('status', $values) && $values['status'] !== 'DRAFT') {
            throw new \LogicException(
                "[{$this->from}] changing status to a non-DRAFT value through the model-derived application ".
                'persistence API is not permitted; use the authoritative versioning service.'
            );
        }

        $this->denyIfAnyMatchedRowIsNotDraft('UPDATE');

        return parent::update($values);
    }

    public function updateFrom(array $values): int
    {
        throw new \LogicException($this->message('updateFrom'));
    }

    public function delete($id = null): mixed
    {
        if ($id !== null) {
            $this->where($this->from.'.id', '=', $id);
        }

        $this->denyIfAnyMatchedRowIsNotDraft('DELETE');

        return parent::delete();
    }

    public function truncate(): void
    {
        throw new \LogicException($this->message('TRUNCATE'));
    }

    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw new \LogicException($this->message('upsert'));
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        throw new \LogicException($this->message('increment'));
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        throw new \LogicException($this->message('decrement'));
    }

    public function incrementEach(array $columns, array $extra = []): int
    {
        throw new \LogicException($this->message('incrementEach'));
    }

    public function decrementEach(array $columns, array $extra = []): int
    {
        throw new \LogicException($this->message('decrementEach'));
    }

    /**
     * @param  array<int|string, mixed>  $values  a single row (associative) or a list of rows
     */
    private function denyIfAnyRowIsNotDraft(array $values, string $operation): void
    {
        $rows = (array_is_list($values) && isset($values[0]) && is_array($values[0])) ? $values : [$values];

        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists('status', $row) && $row['status'] !== 'DRAFT') {
                throw new \LogicException(
                    "[{$this->from}] {$operation} with a non-DRAFT status through the model-derived application ".
                    'persistence API is not permitted; use the authoritative versioning service.'
                );
            }
        }
    }

    private function denyIfAnyMatchedRowIsNotDraft(string $operation): void
    {
        $nonDraftMatch = (clone $this)->where('status', '!=', 'DRAFT')->exists();

        if ($nonDraftMatch) {
            throw new \LogicException(
                "[{$this->from}] {$operation} matches at least one non-DRAFT (published/historical) row, which ".
                'is immutable; create a new version via the authoritative versioning service instead.'
            );
        }
    }

    private function message(string $operation): string
    {
        return "[{$this->from}] rows are governed by an authoritative versioning service; {$operation} through ".
            'the model-derived application persistence API is not permitted.';
    }
}
