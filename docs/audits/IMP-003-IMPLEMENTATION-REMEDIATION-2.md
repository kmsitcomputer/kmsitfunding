# IMP-003 — Targeted Implementation Remediation Pass 2

## Reviewed Starting Commit

`8e9d2f918bfec813dda370e6d9aea85ea8f2c75d` — Codex Targeted Implementation Re-Audit Pass 1 result:
`NEEDS CORRECTION` (BLOCKER: 0, MAJOR: 2 [`IMP003-REAUDIT1-M01`, `IMP003-REAUDIT1-M02`], MINOR: 1
[`IMP003-REAUDIT1-m01`], EVIDENCE GAP: 1 [`IMP003-IMPL-m01`, carried over], HUMAN DECISION: 0).

## Human Authorization

"Saya setuju. IMP-003 Implementation Authorized." — 2026-09-12T16:47:47Z (unchanged; continues to
authorize targeted remediation only — not merge, push, or new business decisions).

Confirmed NOT reopened this pass: original `IMP003-IMPL-M01` (mutation authorization) and
`IMP003-IMPL-M02` (scope integrity), both already Codex-confirmed RESOLVED — `RbacMutationGuard`,
`ScopeResolverRegistry`, `ScopeResolver`, `OwnUserScopeResolver` were NOT modified this pass (only
`RoleAssignmentService`/`AuthorityAssignmentService` from that prior work remain untouched here too
— see `git status`, below).

---

## IMP003-REAUDIT1-M01 — RolePermissionService: Unlocked Target, No Lifecycle Check, Overbroad Self-Expansion Rule

**Status: RESOLVED**

### Root Cause

`RolePermissionService::grant()`/`revoke()` never reloaded/locked the caller-supplied `Permission`
model (only the `Role`), never checked either `Role::retired_at` or `Permission::deprecated_at`
before a NEW grant, and its self-expansion guard was unconditional ("actor holds target Role" alone
→ always DENY) — broader than the specification's actual conditional rule.

### Changes (`app/Services/Rbac/RolePermissionService.php`)

- **Role Lock**: `Role::whereKey($role->id)->lockForUpdate()->firstOrFail()` (was already present;
  unchanged) — now checked for `retired_at !== null` → REJECT new grant, immediately after locking.
- **Permission Lock** (new): `Permission::whereKey($permission->id)->lockForUpdate()->firstOrFail()`
  — reloaded/locked from the DB rather than trusting the caller's instance; checked for
  `deprecated_at !== null` → REJECT new grant.
- **Approved conditional Self-Escalation rule** (exact text from "Role -> Permission Assignment":
  *"a Principal may never assign to a Role a Permission that expands that Principal's OWN effective
  authority beyond what they already hold... unless... the specific target permission is one they
  themselves already effectively have (directly or via an assigned Role)"*): the self-expansion
  check now first computes `$actorAlreadyHasPermission` via
  `(new AuthorizationContext($lockedActor))->activeRoleAssignmentsGranting($lockedPermission->code)`
  — reusing the SAME, unchanged, Codex-confirmed-PASS effective-permission logic the
  `AuthorizationEvaluator` itself uses (respects `starts_at`/`ends_at`/`revoked_at` and the
  active-grant-only `Role::permissions()` relation) — and DENIES the "actor holds target Role" case
  ONLY when `$actorAlreadyHasPermission` is false. If the actor already effectively holds the
  Permission (via ANY of their active Role assignments, not necessarily the target Role), the grant
  is allowed to proceed (subject to every other control).
- **Revocation is unaffected by retirement/deprecation**, per the specification's own qualifier
  that those states only ever block a NEW assignment: `revoke()` still reloads+locks both Role and
  Permission (defense against a stale caller model / consistent locking discipline), but performs no
  lifecycle REJECT — an existing historical grant involving a since-retired Role or since-deprecated
  Permission remains revocable.

### Tests (`tests/Feature/Rbac/RolePermissionServiceTest.php`, 7 new)

Grant to retired Role → DENY; grant of deprecated Permission → DENY; revocation of a grant whose
Role was retired AFTER the grant → still succeeds; revocation of a grant whose Permission was
deprecated AFTER the grant → still succeeds; actor holds target Role but already effectively has the
Permission via a DIFFERENT Role → grant ALLOWED; a stale caller-supplied Role model cannot bypass a
retirement that happened after the caller loaded it (the service's own reload+lock catches it);
same for a stale Permission model and deprecation. All 6 previously-passing tests in this file
(no-permission/no-ELEVATED/original-self-expansion/durable-history/idempotent-grant) re-verified
unchanged.

---

## IMP003-REAUDIT1-M02 — Non-Human Principal/Catalog Lifecycle Drift via forSystem()/forIntegration()

**Status: RESOLVED**

### Root Cause

`deactivateSystem()`/`deactivateIntegration()` silently no-op'd the Principal side when no linked
`principals` row existed yet (`if ($lockedPrincipal !== null) { ...disable... }`), leaving the
catalog deactivated but no linked, disabled Principal. A LATER call to `forSystem()`/
`forIntegration()` for that same catalog row would then `firstOrCreate` a BRAND NEW Principal with
`disabled_at = NULL` — a nominally-enabled Principal for an already-retired catalog identity.

### Changes (`app/Services/Rbac/PrincipalService.php`)

Chose engineering approach **A** (deactivation ensures/creates the canonical Principal and disables
it atomically) — this is the approach consistent with the specification's own established "lazy but
idempotent" Principal-creation philosophy (`firstOrCreate`, already used identically for
`forUser()`/`forSystem()`/`forIntegration()`) and with "Delete Semantics for System/Integration
Principals"'s framing of deactivation as always operating on "the linked `principals` row" — rather
than inventing a NEW rejection policy the specification never describes (approach B would require
deciding new REJECT semantics with no specification text to ground them in).

- **`deactivateSystem()`/`deactivateIntegration()`**: after locking the catalog row and rejecting an
  already-deactivated one, now ALWAYS `Principal::firstOrCreate(...)` (the identical keyed lookup
  `forSystem()`/`forIntegration()` themselves use) THEN locks that row with
  `lockForUpdate()->firstOrFail()` — no longer conditional — before setting
  `catalog.deactivated_at`/`principal.disabled_at` together. The audit payload's `principal_id` is
  now always non-null.
- **`forSystem()`/`forIntegration()`** (the other half of the same finding, `§20`/`§21`): both now
  wrap their `firstOrCreate` in `DB::transaction()` and, ONLY when the row was JUST created
  (`$principal->wasRecentlyCreated`) AND the catalog is ALREADY deactivated, immediately
  `forceFill(['disabled_at' => $systemPrincipal->deactivated_at])->save()` — closing the exact race
  the finding describes: a catalog identity deactivated (by whatever path) before any Principal was
  ever resolved for it can never come back from `forSystem()`/`forIntegration()` as
  authorization-enabled. An EXISTING Principal row (already found, not created) is left untouched by
  this branch — its state is whatever the canonical deactivation transaction (or nothing) already
  set.
- No reactivation capability was added or implied anywhere — the specification defines none.

### Tests (`tests/Feature/Rbac/NonHumanPrincipalLifecycleTest.php`, 7 new)

System/Integration catalog with NO existing Principal → `deactivateSystem()`/`deactivateIntegration()`
creates it already disabled; `forSystem()` called again afterward still returns a disabled Principal
(regression-safety); a catalog row force-deactivated with no prior Principal (simulating the exact
described race), then resolved for the FIRST time via `forSystem()`/`forIntegration()` → the
newly-created Principal is born disabled, never enabled; an active (non-deactivated) catalog row
still resolves a normally-enabled Principal (no regression to the ordinary path). All 6
previously-passing tests in this file (atomic transition, unauthorized/STANDARD-assurance DENY,
rollback, repeated-deactivation REJECT, fillable-boundary) re-verified unchanged.

---

## IMP003-REAUDIT1-m01 — Audit Behavior Insufficiently Tested

**Status: RESOLVED**

### Root Cause

Every RBAC mutation service already emitted the required audit events (Codex confirmed the
mechanism exists, "implemented but insufficiently tested") — no automated test actually inspected
the emitted log content, attribution, atomicity, or payload safety; the prior pass's tests only
verified the MUTATION'S own side effects, never the audit sink.

### Changes

- **New `tests/Support/Rbac/CapturesRbacAudit.php`** — a test-only trait that redirects the
  `rbac_audit` log channel's `path` to a private, per-test file (`config([...]); app('log')->
  forgetChannel('rbac_audit');`) and parses real emitted Monolog lines (`event`, JSON `context`) —
  no mock/fake substituted for `RbacAuditLogger` itself; this exercises the REAL sink end to end.
- **New `tests/Feature/Rbac/RbacAuditTest.php`** (13 tests) covering:
  - All 8 required mutation families each emit their exact event name with correct Principal
    attribution (`*_principal_id` keys) and confirm NO `*_user_id` key is ever present (`§27`):
    `role_assigned`, `role_revoked`, `authority_assigned`, `authority_revoked`,
    `role_permission_granted`, `role_permission_revoked`, and `non_human_principal_deactivated`
    (both for `kind: system` and `kind: integration`).
  - **Sensitive-payload safety** (`§29`): scans every captured event's JSON-encoded context for
    `password`, `password_hash`, `mfa_secret`, `totp_secret`, `session_token`, `api_token`,
    `credential`, `secret` — none present (the payloads only ever carry numeric IDs and
    enum/string codes, by construction of each `record()` call site).
  - **Audit-failure => mutation rollback** (`§28`): a Principal-bound anonymous subclass of
    `RbacAuditLogger` that always throws is bound into the container for four representative
    mutation families (Role assignment, Authority assignment, Role-Permission grant, non-human
    deactivation) — in each case the forced failure propagates and NO mutation row (or, for
    deactivation, neither the catalog's `deactivated_at` nor any Principal row) persists,
    confirming the audit emission and the mutation genuinely share one transaction.

---

## IMP003-IMPL-m01 — MySQL Runtime Validation (Gate-Impact Evidence Gap, carried over)

**Status: EVIDENCE GAP (unchanged)**

Re-attempted this pass per the safety protocol: the project's `.env` (`root`/empty password against
`127.0.0.1:3306`) was tried again and rejected with the same `Access denied for user
'root'@'localhost' (using password: NO)` error. No other disposable MySQL 8 credential or instance
is available in this environment. No credential guessing/brute-forcing was attempted, no real
database was touched, no destructive command was run. A full STATIC review (below) was performed
instead of runtime evidence — reported honestly as `NOT RUN`, never substituted.

---

## Role-Permission Lifecycle

| Item | Result |
|---|---|
| Role Reloaded + Locked | PASS |
| Permission Reloaded + Locked | PASS |
| Retired Role Mutation (new grant) | DENY |
| Deprecated Permission Mutation (new grant) | DENY |
| Conditional Self-Expansion | PASS |
| Temporal Effective Permission | PASS (reuses `AuthorizationContext::activeRoleAssignmentsGranting()`, unchanged) |

## Non-Human Principal Lifecycle

| Item | Result |
|---|---|
| System | PASS |
| Integration | PASS |
| No Enabled Principal From Deactivated Catalog | PASS |
| Atomicity | PASS |
| Authorization After Deactivation | DENY |

## RBAC Audit

| Item | Result |
|---|---|
| All Required Mutation Families | PASS |
| Principal Attribution | PASS |
| Transactional Audit | PASS |
| Audit Failure Rollback | PASS |
| Sensitive Credential Exposure | NONE |

## MySQL Static

| Item | Result | Notes |
|---|---|---|
| Migration Order | PASS | Unchanged this pass — `git status --short database/migrations/` confirms zero diff. |
| Foreign Keys | PASS | Unchanged. |
| CHECK | STATIC PASS | Unchanged. |
| Generated Columns | STATIC PASS | Unchanged. |
| Active Assignment Uniqueness | STATIC PASS | Unchanged. |
| Active Grant Uniqueness | STATIC PASS | Unchanged (`role_permissions.active_grant_key`) — the new Permission lock in `RolePermissionService::grant()` happens BEFORE the existing active-grant-slot inspection, so it does not alter the uniqueness mechanism. |
| Lock Order | STATIC PASS | `RolePermissionService::grant()`'s order is now: actor → Role → Permission → active grant slot → mutation → audit → commit, matching this pass's required order; `PrincipalService::deactivateSystem()/deactivateIntegration()`'s order is: actor → catalog → (ensure+)Principal → mutation → audit → commit. |

## MySQL Runtime

| Item | Result |
|---|---|
| Disposable MySQL Used | NO |
| Fresh Migration | NOT RUN |
| Constraint Validation | NOT RUN |
| Authorization Tests | NOT RUN |
| Concurrency | NOT RUN |
| Reason | No disposable MySQL 8 credential/instance available; `.env` credentials re-tried and rejected; no guessing attempted. |
| Real DB Touched | NO |
| Credential Guessing | NO |

## Tests

- PHP Suite: **PASS**
- Tests: **226**
- Assertions: **595**
- Failures: **0**
- Errors: **0**
- Skipped: **0**
- New Targeted Tests: **PASS** (27 new tests this pass: 7 in `RolePermissionServiceTest.php`, 7 in `NonHumanPrincipalLifecycleTest.php`, 13 in new `RbacAuditTest.php`)

## Quality

| Check | Result |
|---|---|
| `php artisan about` | PASS |
| `vendor/bin/pint --test` | PASS (clean on first run — no formatting fix needed this pass) |
| `npm run type-check` | PASS |
| `npm run build` | PASS (584 modules, ~7.2s) |
| `composer audit` | PASS (no advisories) |
| `git diff --check` | PASS |

## Regression

| Item | Result |
|---|---|
| Original M01 (`IMP003-IMPL-M01`, mutation authorization) | PASS — `RbacMutationGuard`/`RoleAssignmentService`/`AuthorityAssignmentService` untouched this pass; full `MutationAuthorizationTest.php` (16 tests) re-verified |
| Original M02 (`IMP003-IMPL-M02`, scope integrity) | PASS — `ScopeResolverRegistry`/`ScopeResolver`/`OwnUserScopeResolver` untouched this pass; full `ScopeIntegrityTest.php` (6 tests) re-verified |
| Authorization Evaluator | PASS (untouched) |
| Human Principal Lifecycle | PASS (untouched — `deleteUser()` logic unmodified this pass) |
| Q25 | PASS (untouched) |
| IMP-002 | PASS (full suite re-run, unaffected) |
| Super Admin ≠ Financial Authority | PASS |
| System Principal ≠ God Mode | PASS |
| Integration Principal ≠ God Mode | PASS |
| API Principal ∩ Token | PASS (`ApiAuthority` untouched) |
| Registration ≠ Authorization | PASS |
| Invitation ≠ Authorization | PASS |
| Partner ≠ Tenant | PASS |
| Shared Hosting | PASS (no new infrastructure dependency) |

## Scope Control

- Later Business Domain Implemented: **NO**
- Locked Baseline Changed: **NO**
- New Human Decision: **NO**

## Evidence

`docs/audits/IMP-003-IMPLEMENTATION-REMEDIATION-2.md` (this document).

## Remaining Findings

- BLOCKER: none
- MAJOR: none
- MINOR: none
- EVIDENCE GAP: `IMP003-IMPL-m01` — MySQL 8 runtime validation (fresh migration, constraint,
  authorization, concurrency) remains NOT RUN; no safe disposable MySQL 8 instance available in
  this environment; full static review performed and reported separately.
- HUMAN DECISION: none

## Recommendation

**READY FOR CODEX TARGETED IMP-003 IMPLEMENTATION RE-AUDIT PASS 2**

## Stage Gate

NOT APPROVED

## Merge Authorization

NOT GRANTED

STOP.
