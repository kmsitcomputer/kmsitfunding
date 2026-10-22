<?php

namespace App\Support\Database;

use Illuminate\Database\Query\Builder;

/**
 * CODEX-CR001B-01 remediation (Round 3) — closes the `toBase()`/`getQuery()`
 * escape hatch Round 2's Eloquent-level `AppendOnlyBuilder` could not
 * reach. `Illuminate\Database\Eloquent\Builder::getQuery()` returns
 * `$this->query` verbatim, and `toBase()` is just
 * `$this->applyScopes()->getQuery()` — both hand back the RAW base
 * `Illuminate\Database\Query\Builder` with none of the Eloquent-level
 * overrides applied. `ZakatCalculationSnapshot::query()->toBase()->update(...)`
 * therefore bypassed every Round 1/2 guard entirely.
 *
 * The fix is NOT to override the public `toBase()`/`getQuery()` methods
 * to throw: `Eloquent\Builder::__call()` forwards every `$passthru`
 * method (`count`, `exists`, `insert`, `insertGetId`, `avg`, `sum`, ...)
 * through `$this->toBase()->{$method}(...)`, and
 * `Eloquent\Builder::getModels()` reads `$this->query->get(...)`
 * directly — read paths and `create()` would break immediately if
 * `toBase()`/`getQuery()` themselves refused to return a working query
 * builder (verified by reading both methods' call sites in
 * vendor/laravel/framework before writing this class).
 *
 * Instead, `App\Models\Zakat\ZakatCalculationSnapshot`/
 * `App\Models\Fidyah\FidyahCalculationSnapshot` override
 * `Model::newBaseQueryBuilder()` (a `protected` hook Eloquent already
 * calls to construct the object that becomes `$this->query`/
 * `toBase()`/`getQuery()`'s return value) to hand back an instance of
 * THIS class instead of a plain `Illuminate\Database\Query\Builder`.
 * Every Eloquent-level mutation method (`update`, `delete`, `touch`,
 * `forceDelete`, `incrementEach`, `decrementEach`, `updateOrInsert`, …)
 * ultimately calls one of the primitives overridden here — verified in
 * vendor/laravel/framework rather than assumed, so there is exactly one
 * place left to guard, not a growing list of Eloquent-level convenience
 * methods to chase.
 *
 * INSERT is deliberately untouched — snapshots have no "invalid create"
 * concept, only "no mutation of an existing row" — see
 * App\Support\Database\GovernedPolicyQueryBuilder for the policy tables'
 * different (value-aware) insert rule.
 */
class AppendOnlyQueryBuilder extends Builder
{
    public function update(array $values): int
    {
        throw new \LogicException($this->message('UPDATE'));
    }

    public function updateFrom(array $values): int
    {
        throw new \LogicException($this->message('updateFrom'));
    }

    public function delete($id = null): mixed
    {
        throw new \LogicException($this->message('DELETE'));
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

    private function message(string $operation): string
    {
        return "[{$this->from}] rows are append-only historical evidence; {$operation} is not permitted ".
            'through the model-derived application persistence API. Create a new record instead.';
    }
}
