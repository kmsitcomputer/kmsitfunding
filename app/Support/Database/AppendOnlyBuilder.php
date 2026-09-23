<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;

/**
 * CODEX-CR001B-01 remediation (Round 1 + Round 2) — a COMPLETE
 * application-layer append-only boundary for historical tables
 * (ZakatCalculationSnapshot, FidyahCalculationSnapshot), enumerated
 * directly against the installed framework source
 * (vendor/laravel/framework, laravel/framework ^13.17, Application::VERSION
 * 13.31.0 — `Illuminate\Database\Eloquent\Builder`).
 *
 * Round 1 blocked `update()`, `delete()`, `increment()`, `decrement()`,
 * `upsert()` — but reading Builder.php directly (rather than guessing
 * from memory of another Laravel version) showed several of THOSE
 * methods, and several more, delegate straight to `$this->toBase()` or
 * are forwarded through `__call()` to the underlying base query builder
 * WITHOUT ever calling `$this->update()`/`$this->delete()` internally:
 *
 *   - `touch()`, `incrementEach()`, `decrementEach()` call
 *     `$this->toBase()->update(...)` / `->incrementEach(...)` /
 *     `->decrementEach(...)` DIRECTLY — never `$this->update()`.
 *   - `forceDelete()` calls `$this->query->delete()` DIRECTLY — never
 *     `$this->delete()`. (Reachable even though these models don't use
 *     SoftDeletes: `Model::query()->forceDelete()` is unconditionally
 *     defined on Eloquent\Builder itself.)
 *   - `updateOrInsert()` isn't defined on Eloquent\Builder at all; an
 *     undefined-method call falls through `__call()`'s final branch
 *     (`forwardCallTo($this->query, $method, $parameters)`), which
 *     actually executes it on the base query builder — none of the
 *     Round 1 overrides intercept a call routed through `__call()`.
 *
 * Every one of the above is overridden directly below so the mutation
 * never reaches the base query builder at all, regardless of which
 * Eloquent Builder method a caller happens to use.
 *
 * NOT overridden (verified safe by reading their source):
 *   - `firstOrCreate()` / `createOrFirst()`: read-then-insert-only,
 *     never touch an existing row.
 *   - `updateOrCreate()` / `incrementOrCreate()`: when a row already
 *     exists, both call the MODEL INSTANCE's own `save()`/`increment()`
 *     (not a builder method) — already blocked by
 *     ZakatCalculationSnapshot::save() and this class's own
 *     increment()/decrement() (reached via the model's `__call`
 *     delegation) respectively. Proven by test, not assumed — see
 *     tests/Feature/Zakat/*ImmutabilityTest.php.
 *   - `insert()` / `insertGetId()` / `insertOrIgnore()` / `insertUsing()`
 *     (Eloquent Builder's `$passthru` list): INSERT-only, cannot mutate
 *     an existing row.
 *
 * This is an APPLICATION-LEVEL append-only boundary for this codebase's
 * supported Eloquent persistence API. It does not and cannot prevent a
 * raw `DB::statement()`/`DB::table()` call, direct DBA access, or any
 * write that bypasses this model's builder entirely — that residual gap
 * is covered, where MySQL privileges allow it, by the optional
 * BEFORE UPDATE/DELETE triggers in this table's migration (§10.1 of
 * B-EVIDENCE.md); the trigger is NOT required to close this finding
 * (Codex: "TRIGGER REQUIREMENT: NOT REQUIRED BY CONTRACT") and this
 * class's guarantees do not depend on it.
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values): int
    {
        throw new \LogicException($this->immutabilityMessage('UPDATE'));
    }

    public function updateOrInsert(array $attributes, $values = []): bool
    {
        throw new \LogicException($this->immutabilityMessage('updateOrInsert'));
    }

    public function delete(): mixed
    {
        throw new \LogicException($this->immutabilityMessage('DELETE'));
    }

    public function forceDelete(): mixed
    {
        throw new \LogicException($this->immutabilityMessage('forceDelete'));
    }

    public function touch($column = null): int|false
    {
        throw new \LogicException($this->immutabilityMessage('touch'));
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        throw new \LogicException($this->immutabilityMessage('increment'));
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        throw new \LogicException($this->immutabilityMessage('decrement'));
    }

    public function incrementEach(array $columns, array $extra = []): int
    {
        throw new \LogicException($this->immutabilityMessage('incrementEach'));
    }

    public function decrementEach(array $columns, array $extra = []): int
    {
        throw new \LogicException($this->immutabilityMessage('decrementEach'));
    }

    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw new \LogicException($this->immutabilityMessage('upsert'));
    }

    private function immutabilityMessage(string $operation): string
    {
        $model = $this->getModel();
        $modelClass = $model === null ? 'this' : $model::class;

        return "{$modelClass} rows are append-only historical evidence; {$operation} is not permitted. Create a new record instead.";
    }
}
