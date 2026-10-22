<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;

/**
 * CODEX-CR001B-02 remediation — query-builder-level governance for
 * versioned policy tables (ZakatPolicy, FidyahPolicy). "Service exists"
 * is not the same as "authoritative write boundary": ordinary Eloquent
 * calls that never touch ZakatPolicyVersioningService/
 * FidyahPolicyVersioningService must still fail closed.
 *
 * Design (enumerated against the same installed framework source as
 * App\Support\Database\AppendOnlyBuilder — see that class's docblock for
 * the full method-by-method framework analysis):
 *
 *   - `update()` is allowed ONLY when (a) every row matched by the
 *     current WHERE clause is currently `status = 'DRAFT'`, AND (b) the
 *     values being written do not set `status` to anything other than
 *     `'DRAFT'`. This is what makes a legitimate DRAFT edit via
 *     `$model->save()` work (Eloquent's own `performUpdate()` narrows the
 *     query to the model's primary key before calling `update()` — the
 *     SAME method this class intercepts) while closing:
 *       - `$policy->status = 'ACTIVE'; $policy->save();` (still routed
 *         through this same `update()` — condition (b) denies it),
 *       - `Model::where(...)->update(['status' => 'ACTIVE'])` (condition
 *         (b) denies it regardless of the row's current status),
 *       - any update — status-changing or not — against a row that has
 *         already left DRAFT (condition (a) denies it), independently of
 *         the model's own `save()`/`delete()` instance guard.
 *   - `delete()` is allowed ONLY when every matched row is `status =
 *     'DRAFT'` — mirrors `update()`'s condition (a). This lets
 *     `$draftPolicy->delete()` keep working while closing
 *     `Model::where(...)->delete()` against historical rows.
 *   - `upsert()`, `increment()`, `decrement()`, `incrementEach()`,
 *     `decrementEach()`, `touch()`, `forceDelete()`, `updateOrInsert()`
 *     are denied unconditionally — no legitimate policy-governance
 *     workflow ever needs any of them.
 *
 * The one authorized way to transition a DRAFT row to ACTIVE (or any
 * other non-DRAFT status) is the versioning service's own internal,
 * validated step, which deliberately uses `DB::table(...)->update(...)`
 * — the RAW base query builder, never this Eloquent builder — exactly
 * the kind of direct-database write this finding's own scope excludes
 * ("Database::table(...) ... does not need to be made impossible solely
 * to satisfy this finding"). That is a controlled internal detail of the
 * trusted service, not a publicly reachable flag or bypassable option.
 */
class GovernedPolicyBuilder extends Builder
{
    public function update(array $values): int
    {
        if (array_key_exists('status', $values) && $values['status'] !== 'DRAFT') {
            throw new \LogicException($this->governanceMessage(
                'Changing status to a non-DRAFT value through ordinary persistence is not permitted; '.
                'use the authoritative versioning service.'
            ));
        }

        $this->denyIfAnyMatchedRowIsNotDraft('UPDATE');

        return parent::update($values);
    }

    public function updateOrInsert(array $attributes, $values = []): bool
    {
        throw new \LogicException($this->governanceMessage('updateOrInsert'));
    }

    public function delete(): mixed
    {
        $this->denyIfAnyMatchedRowIsNotDraft('DELETE');

        return parent::delete();
    }

    public function forceDelete(): mixed
    {
        throw new \LogicException($this->governanceMessage('forceDelete'));
    }

    public function touch($column = null): int|false
    {
        throw new \LogicException($this->governanceMessage('touch'));
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        throw new \LogicException($this->governanceMessage('increment'));
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        throw new \LogicException($this->governanceMessage('decrement'));
    }

    public function incrementEach(array $columns, array $extra = []): int
    {
        throw new \LogicException($this->governanceMessage('incrementEach'));
    }

    public function decrementEach(array $columns, array $extra = []): int
    {
        throw new \LogicException($this->governanceMessage('decrementEach'));
    }

    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw new \LogicException($this->governanceMessage('upsert'));
    }

    private function denyIfAnyMatchedRowIsNotDraft(string $operation): void
    {
        $nonDraftMatch = (clone $this)->where('status', '!=', 'DRAFT')->exists();

        if ($nonDraftMatch) {
            throw new \LogicException($this->governanceMessage(
                "{$operation} matches at least one non-DRAFT (published/historical) row, which is immutable; ".
                'create a new version via the authoritative versioning service instead.'
            ));
        }
    }

    private function governanceMessage(string $operation): string
    {
        $model = $this->getModel();
        $modelClass = $model === null ? 'this' : $model::class;

        return "{$modelClass} rows are governed by an authoritative versioning service; {$operation} through ".
            'ordinary persistence is not permitted.';
    }
}
