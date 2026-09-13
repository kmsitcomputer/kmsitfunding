# IMP-003 — Targeted Implementation Remediation Pass 3

## Reviewed Starting Commit

`0b6015cc03ba88725e6344e4b991794e2ad5de15` — Codex Targeted Re-Audit Pass 2 result: residual
implementation findings `R3-M01` (temporal target-Role membership) and `R3-M02` (stale catalog
model bypassing canonical Principal lifecycle sync).

## Human Authorization

"Saya setuju. IMP-003 Implementation Authorized." — 2026-09-12T16:47:47Z (unchanged; continues to
authorize targeted remediation only — not merge, push, or new business decisions). No new Human
Decision was required for either residual — both are narrow engineering defects inside the already
approved conditional self-expansion rule and non-human Principal lifecycle contract.

Confirmed NOT reopened this pass: original `IMP003-IMPL-M01`, `IMP003-IMPL-M02`, and Pass 2's
`IMP003-REAUDIT1-m01` (audit tests) — `RbacMutationGuard`, `RoleAssignmentService`,
`AuthorityAssignmentService`, `ScopeResolverRegistry`, `ScopeResolver`, `OwnUserScopeResolver`,
`RbacAuditLogger`, and `AuthorizationEvaluator` were NOT modified this pass.

---

## R3-M01 — Expired/Revoked/Future Target-Role Membership Incorrectly Triggered Self-Expansion Denial

**Status: RESOLVED**

### Root Cause

`RolePermissionService::grant()`'s conditional self-expansion check determined whether the actor
"currently holds" the target Role with:

```php
PrincipalRoleAssignment::where('principal_id', $lockedActor->id)
    ->where('role_id', $lockedRole->id)
    ->whereNull('revoked_at')
    ->exists();
```

This only excluded *revoked* assignments — it did not check `starts_at`/`ends_at` — so an
**expired** (`ends_at` in the past) or **not-yet-active** (`starts_at` in the future) assignment
was incorrectly treated as "currently held," triggering a self-expansion DENY the specification
never intends (the approved rule requires the membership to be effective *right now*).

### Patch (`app/Services/Rbac/RolePermissionService.php`)

Reused the existing canonical temporal predicate — `PrincipalRoleAssignment::isActive()`
(`revoked_at === null && starts_at <= now() && (ends_at === null || ends_at->isFuture())`), the
same predicate `AuthorizationContext::activeRoleAssignments()` already encodes as a query — instead
of inventing a second, duplicate date-comparison. No new date logic was written:

```php
$actorHoldsTargetRole = PrincipalRoleAssignment::where('principal_id', $lockedActor->id)
    ->where('role_id', $lockedRole->id)
    ->whereNull('revoked_at')
    ->get()
    ->contains(fn (PrincipalRoleAssignment $assignment) => $assignment->isActive());
```

The `whereNull('revoked_at')` pre-filter is retained (cheap, correct) and `isActive()` covers the
remaining `starts_at`/`ends_at` boundary exactly as the canonical model method already defines it.
No history is deleted or altered — this only changes what counts as *effective right now* for the
self-expansion determination.

### Tests (`tests/Feature/Rbac/RolePermissionServiceTest.php`, 4 new)

- `test_expired_target_role_membership_does_not_trigger_self_expansion_denial` — `ends_at` in the
  past → grant succeeds (self-expansion branch does not fire).
- `test_revoked_target_role_membership_does_not_trigger_self_expansion_denial` — `revoked_at` set
  (via `forceFill`, since it is deliberately not mass-assignable — mirrors
  `RoleAssignmentService::revoke()`'s own pattern) → grant succeeds.
- `test_future_target_role_membership_does_not_trigger_self_expansion_denial` — `starts_at` in the
  future → grant succeeds.
- `test_currently_active_target_role_membership_without_existing_permission_is_denied` — an
  ordinary active assignment (no lifecycle edge) still correctly triggers the DENY, proving the
  fix did not weaken the core protection.

The pre-existing `test_actor_holding_target_role_but_already_possessing_permission_via_another_role_may_grant_it`
(Pass 2) already covers "current membership + Permission already effectively held → allowed" and
was re-verified unchanged — no duplicate test was added for that case.

---

## R3-M02 — Stale Catalog Model Could Leave Canonical Principal Enabled

**Status: RESOLVED**

### Root Cause

`PrincipalService::forSystem()` / `forIntegration()` decided whether a freshly-created Principal
should be born disabled using the **caller-supplied** `SystemPrincipal`/`IntegrationPrincipal`
instance's own `deactivated_at` field:

```php
if ($principal->wasRecentlyCreated && $systemPrincipal->deactivated_at !== null) { ... }
```

If the caller held a **stale** in-memory copy of the catalog row (loaded before some other
request/process deactivated it), `$systemPrincipal->deactivated_at` was still `null` locally even
though the authoritative database row was already deactivated — producing a nominally-enabled
canonical Principal for a retired catalog identity. The check was also gated on
`wasRecentlyCreated`, so an *existing* Principal left enabled by an out-of-band inconsistency (e.g.
a catalog row deactivated by any path other than `deactivateSystem()`) was never reconciled either.

### Patch (`app/Services/Rbac/PrincipalService.php`)

Both methods now reload the **authoritative** catalog row from the DB by its stable identifier
inside the transaction, and decide lifecycle state from that reload — never from the caller's
instance:

```php
public function forSystem(SystemPrincipal $systemPrincipal): Principal
{
    return DB::transaction(function () use ($systemPrincipal) {
        $authoritativeCatalog = SystemPrincipal::whereKey($systemPrincipal->id)->firstOrFail();

        $principal = Principal::firstOrCreate(
            ['system_principal_id' => $authoritativeCatalog->id],
            ['principal_kind' => PrincipalKind::System],
        );

        if ($authoritativeCatalog->deactivated_at !== null && $principal->disabled_at === null) {
            $principal->forceFill(['disabled_at' => $authoritativeCatalog->deactivated_at])->save();
        }

        return $principal;
    });
}
```

(identical shape for `forIntegration()`). Two changes from Pass 2's version, both required by the
finding:

1. The lifecycle decision now reads `$authoritativeCatalog` (freshly queried by id), not the
   caller-supplied `$systemPrincipal`/`$integrationPrincipal` parameter — a stale object can no
   longer override the DB's own state.
2. The sync condition is no longer gated on `$principal->wasRecentlyCreated` — it is
   `$principal->disabled_at === null` (i.e. "not yet reflecting deactivation"), so an
   **already-existing** Principal that is out of sync with an authoritatively-deactivated catalog
   row is also reconciled to disabled on the next resolution, not only a brand-new one. This still
   implements the same approved "ensure canonical Principal exists and synchronize `disabled_at`"
   contract chosen in Pass 2 (§18/§19 of that pass) — no new policy was invented, and no
   reactivation path was added (the condition only ever moves `disabled_at` from `null` to set,
   never the reverse).

`deactivateSystem()`/`deactivateIntegration()` were not touched — they already reload+lock the
catalog row by id (`SystemPrincipal::whereKey($systemPrincipal->id)->lockForUpdate()->firstOrFail()`),
so they were never vulnerable to a stale caller model.

### Tests (`tests/Feature/Rbac/NonHumanPrincipalLifecycleTest.php`, 4 new)

- `test_for_system_with_stale_catalog_object_and_no_prior_principal_cannot_produce_enabled_principal`
  — loads a copy of the catalog row, deactivates the (separate, freshly-loaded) row directly, then
  calls `forSystem()` with the **stale** copy (which still shows `deactivated_at === null` in
  memory) → the newly-created Principal is still born disabled.
- `test_for_integration_with_stale_catalog_object_and_no_prior_principal_cannot_produce_enabled_principal`
  — identical for Integration.
- `test_for_system_with_existing_enabled_principal_and_stale_catalog_object_ends_disabled` — an
  enabled Principal already exists; the catalog row is then deactivated out from under a stale
  in-memory copy; resolving via the stale copy still ends with the Principal disabled (proves the
  fix also reconciles the *existing*-Principal path, not only first-creation).
- `test_for_integration_with_existing_enabled_principal_and_stale_catalog_object_ends_disabled` —
  identical for Integration.

All 7 pre-existing tests in this file (atomic transition, unauthorized/STANDARD-assurance DENY,
rollback, repeated-deactivation REJECT, fillable-boundary, Pass 2's no-prior-Principal and
after-deactivation coverage) re-verified unchanged.

---

## Regression

| Item | Result |
|---|---|
| Original `IMP003-IMPL-M01` (mutation authorization) | PASS — untouched this pass |
| Original `IMP003-IMPL-M02` (scope integrity) | PASS — untouched this pass |
| Role-Permission Lifecycle (Pass 2, minus the temporal residual) | PASS |
| Non-Human Principal Lifecycle (Pass 2, minus the stale-model residual) | PASS |
| `IMP003-REAUDIT1-m01` RBAC Audit tests | PASS — `RbacAuditLogger`/audit assertions untouched |
| Authorization Evaluator | PASS (untouched) |
| Human Principal Lifecycle | PASS (untouched) |
| Q25 | PASS (untouched) |
| IMP-002 | PASS (full suite re-run, unaffected) |
| API Principal ∩ Token | PASS (untouched) |
| Super Admin ≠ Financial Authority / System ≠ God Mode / Integration ≠ God Mode | PASS |
| Registration ≠ Authorization / Invitation ≠ Authorization / Partner ≠ Tenant | PASS |
| Shared Hosting | PASS (no new infrastructure dependency) |

## Tests

- PHP Suite: **PASS**
- Tests: **234** (226 baseline + 8 new: 4 for R3-M01, 4 for R3-M02)
- Assertions: **614**
- Failures: **0**
- Errors: **0**
- Skipped: **0**

## Quality

| Check | Result |
|---|---|
| `php artisan about` | PASS (boots, MySQL driver configured) |
| `vendor/bin/pint --test` | PASS (clean, no formatting fix needed) |
| `npm run type-check` | PASS |
| `npm run build` | PASS (584 modules, ~6.8s) |
| `composer audit` | PASS — "No security vulnerability advisories found." Network scope: read-only Packagist advisory metadata request only, as authorized. No `composer.lock`/`composer.json` mutation, no dependency install/update, no tracked-file change — confirmed via `git status` before/after. |
| `git diff --check` | PASS |

## MySQL

Re-attempted the same non-destructive, non-guessing connectivity check as Pass 2, per the safety
protocol: `.env`'s configured `root` / empty password against `127.0.0.1:3306` /
`philanthropy_platform` via `php artisan db:show`. Result unchanged:
`SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)`. No other
disposable MySQL 8 credential or instance is available in this environment. No credential
guessing/brute-forcing was attempted, no real database was touched or mutated, no destructive
command was run.

- Disposable MySQL: **NO**
- Fresh Migration: **NOT RUN**
- Constraint Validation: **NOT RUN**
- Authorization Tests: **NOT RUN**
- Concurrency: **NOT RUN**
- Evidence Classification: **GATE-IMPACT EVIDENCE GAP** (`IMP003-IMPL-m01`, carried over unchanged
  from Pass 2 — this pass's fixes did not touch migrations, constraints, or generated columns, so
  no new static-review delta applies either)
- Real DB Touched: **NO**
- Credential Guessing: **NO**

## Remaining Findings

- BLOCKER: none
- MAJOR: none
- MINOR: none
- EVIDENCE GAP: `IMP003-IMPL-m01` — MySQL 8 runtime validation remains NOT RUN; no safe disposable
  MySQL 8 instance available in this environment.
- HUMAN DECISION: none

## Recommendation

**READY FOR CODEX TARGETED IMP-003 RE-AUDIT PASS 3**

## Stage Gate

NOT APPROVED

## Merge Authorization

NOT GRANTED
