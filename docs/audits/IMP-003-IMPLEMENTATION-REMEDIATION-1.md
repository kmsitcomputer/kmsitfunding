# IMP-003 — Targeted Implementation Remediation Pass 1

## Reviewed Implementation Commit

`77b9499e3014a2d4ef45d57078d78637ace97fce` — Codex Independent Implementation Audit result:
`NEEDS CORRECTION` (BLOCKER: 0, MAJOR: 4, MINOR: 1 gate-impact evidence gap, HUMAN DECISION: 0).

## Human Authorization

"Saya setuju. IMP-003 Implementation Authorized." — 2026-09-12T16:47:47Z (unchanged; authorizes
remediation within the approved specification only — not merge, push, or new business decisions).

---

## IMP003-IMPL-M01 — Mutation Authorization Not Enforced at the Service Layer

**Status: RESOLVED**

### Root Cause

`RoleAssignmentService::assign()`/`revoke()` and `AuthorityAssignmentService::assign()`/`revoke()`
validated only the acting Principal's own eligibility (`canAuthorize()`), never that it actually held
the operation's `rbac.role.assign`/`rbac.role.revoke`/`rbac.authority.assign`/`rbac.authority.revoke`
permission under ELEVATED assurance. `revoke()` additionally never locked the revoker Principal at
all — attribution was recorded from an unlocked, unvalidated caller-supplied object.

### Changes

- **New `App\Services\Rbac\RbacMutationGuard`** — wraps the already-Codex-confirmed
  `AuthorizationEvaluator` (not reimplemented) behind one method,
  `ensureAuthorized(Principal $actor, string $permissionCode)`, which builds an
  `AuthorizationRequest` with `requiresElevatedAssurance: true` and throws `\RuntimeException` on
  DENY. This is the one enforcement point every mutation service now calls.
- **`RoleAssignmentService`**: `assign()` now locks grantor+target (as before), then calls
  `guard->ensureAuthorized($lockedGrantor, RBAC_ROLE_ASSIGN)` before any mutation;
  `revoke()` now locks the revoker Principal FIRST, calls
  `guard->ensureAuthorized($lockedRevoker, RBAC_ROLE_REVOKE)`, THEN locks and revokes the
  assignment, attributing to the locked revoker.
- **`AuthorityAssignmentService`**: identical pattern with `RBAC_AUTHORITY_ASSIGN`/
  `RBAC_AUTHORITY_REVOKE`.
- Both permission codes are the exact, pre-existing `PermissionRegistry` constants — no new
  taxonomy invented for assign/revoke.
- Self-Escalation Protection (`grantor->id === target->id`) is preserved as an unconditional
  pre-check, unaffected by and independent of this permission check.

### Tests

`tests/Feature/Rbac/MutationAuthorizationTest.php` (16 tests): role assignment/revocation and
authority assignment/revocation each tested for: no permission → DENY; permission but STANDARD
assurance → DENY; DISABLED actor → DENY; SUSPENDED actor → DENY; tombstoned actor → DENY;
deactivated System actor → DENY; deactivated Integration actor → DENY; authorized + ELEVATED →
ALLOW (with attribution verified).

---

## IMP003-IMPL-M02 — Concrete Scope Accepted Without a Resolver/Target

**Status: RESOLVED**

### Root Cause

`assertScopeIdMatchesType()` only checked null-vs-non-null shape (`scope_id != null` for a concrete
scope type) — any positive integer was accepted with no proof a corresponding target row exists, is
active, or that ANY resolver for that scope type is even registered.

### Changes

- **`ScopeResolver` interface** gained one new method,
  `lockAndValidateTarget(int $scopeId): ?object` — resolves AND locks the concrete target row
  within the caller's open transaction, returning `null` if it doesn't exist or is no longer
  active/assignable. `OwnUserScopeResolver` implements it as a no-op (OWN never carries a concrete
  `scope_id`).
- **New `App\Services\Rbac\ScopeResolverRegistry`** — a `ScopeType -> ScopeResolver` map. Bound as a
  singleton in `AppServiceProvider`, seeded with only `OwnUserScopeResolver` (the one resolver
  IMP-003 itself implements). A concrete scope type with no entry here means that domain does not
  exist yet.
- **`RoleAssignmentService`/`AuthorityAssignmentService`**: for any scope type where
  `requiresNullScopeId()` is false, `assign()` now (as lock-order step 3, between Principal locks
  and the existing-assignment inspection): looks up the resolver — `null` → REJECT
  ("No registered ScopeResolver..."); calls `lockAndValidateTarget($scopeId)` — `null` → REJECT
  ("...does not exist or is not active/assignable"). Only then does the renewal/insert proceed.
- No future domain (Partner/Campaign/Fund/etc.) was implemented to "supply" a resolver — the
  fail-closed REJECT for every currently-nonexistent concrete scope type is itself the correct,
  specification-consistent behavior.

### Delete/Assignment Race (`§18`)

The only concrete-target resolver that exists in production code today is `OwnUserScopeResolver`,
whose "target" IS the `users` row itself — and that row's deletion path is already the atomic
Canonical User Deletion Transaction (`PrincipalService::deleteUser()`), which locks the Principal
and revokes every active assignment in the SAME transaction before the User is deleted. There is
currently no OTHER concrete-scope domain table to race against. The race-protection CONTRACT for
every future concrete scope type is `ScopeResolver::lockAndValidateTarget()` itself: a future
domain's own delete/deactivate path MUST lock the identical target row before deleting/deactivating
it, which serializes it against any concurrent `assign()` call attempting the same
`lockAndValidateTarget()` on that row. This is a contract obligation on the future domain, not
something IMP-003 can enforce today without inventing that domain.

### Tests

`tests/Feature/Rbac/ScopeIntegrityTest.php` (6 tests): unknown resolver → REJECT (both Role and
Authority assignment); nonexistent target → REJECT; inactive target → REJECT; valid target → PASS;
GLOBAL_PLATFORM (no resolver needed) → PASS/deterministic. Uses the existing
`Tests\Support\Rbac\FakePartnerScopeResolver` test fixture (extended with
`lockAndValidateTarget()` backed by an explicit in-memory "active id" set) — no premature domain
table introduced.

---

## IMP003-IMPL-M03 — Ungoverned Role-Permission Mutation, Missing Audit

**Status: RESOLVED**

### Root Cause

No service existed for Role-Permission grant/revoke — the only code path
(`RbacRoleSeeder`) used raw `$role->permissions()->attach()`, which any other application code
could equally call at runtime with no authorization, assurance, self-expansion, or audit check.
Additionally, none of the RBAC mutation services emitted any audit event.

### Changes

- **New `App\Services\Rbac\RolePermissionService`** — `grant()`/`revoke()`, each: locks the acting
  Principal; calls `RbacMutationGuard::ensureAuthorized()` with the PRE-EXISTING
  `RBAC_PERMISSION_ASSIGN`/`RBAC_PERMISSION_REVOKE` codes (no new permission taxonomy); locks the
  target Role; grant additionally checks `PrincipalRoleAssignment` for an unrevoked row binding the
  ACTOR itself to the target Role — if found, throws (indirect self-expansion via Role-Permission
  grant, the same absolute rule as Self Role/Authority Grant); mutates `role_permissions` via the
  query builder (preserving the exact durable-history schema, `granted_at`/`granted_by_principal_id`/
  `revoked_at`/`revoked_by_principal_id`) inside the same transaction; emits an audit event.
- **New `App\Services\Rbac\RbacAuditLogger`-backed audit events**, emitted from inside the same
  transaction that performs the mutation (so a failure while auditing rolls the mutation back too —
  the pragmatic atomicity contract available without a persistent Audit domain, matching IMP-002's
  own `IdentityAuditLogger` precedent): `role_assigned`, `role_revoked`, `authority_assigned`,
  `authority_revoked`, `role_permission_granted`, `role_permission_revoked`,
  `non_human_principal_deactivated`, `human_principal_tombstoned_user_deleted`. The pre-existing
  `super_admin_canonically_authorized` (Q25 Bridge) was already emitting correctly and required no
  change.
- **Bypass review** (`§29`): grepped `app/` for `->permissions()->attach|detach|sync|
  syncWithoutDetaching|updateExistingPivot` and any raw `role_permissions` table write — the ONLY
  hit outside `RolePermissionService` itself is `database/seeders/RbacRoleSeeder.php`'s initial
  `super_admin` permission grants (internal setup, no acting Principal, `granted_by_principal_id =
  null`, not a runtime-reachable path) — documented, not a bypass.

### Tests

`tests/Feature/Rbac/RolePermissionServiceTest.php` (6 tests): grant without permission → DENY;
grant without ELEVATED → DENY; revoke without permission → DENY; self-expansion via Role-Permission
grant → DENY; authorized grant+revoke → PASS with durable history row retained (not hard-deleted)
and correct Principal attribution on both `granted_by_principal_id`/`revoked_by_principal_id`;
grant is idempotent (a second grant of an already-active permission is a no-op, not a duplicate
row).

---

## IMP003-IMPL-M04 — Non-Human Principal Catalog/Principal Lifecycle Could Drift

**Status: RESOLVED**

### Root Cause

No service existed to deactivate a `SystemPrincipal`/`IntegrationPrincipal`; `deactivated_at` was
mass-assignable directly on both catalog models (an artifact of the PREVIOUS implementation pass's
own fix for `Principal::canAuthorize()` — see that pass's "Known Limitations" #5), meaning any
caller could set `deactivated_at` on the catalog row without ever touching the linked
`principals.disabled_at`, producing exactly the drift Codex flagged.

### Changes

- **`SystemPrincipal`/`IntegrationPrincipal`**: `deactivated_at` REMOVED from `$fillable` — it is no
  longer mass-assignable by any ordinary `create()`/`update()` call, mirroring how
  `Principal::tombstoned_at` is already protected (fillable omission + `forceFill()`-only internal
  mutation).
- **`PrincipalService::deactivateSystem()`/`deactivateIntegration()`** (new): within ONE transaction —
  lock the acting Principal; `RbacMutationGuard::ensureAuthorized()` against the new
  `rbac.principal.deactivate` permission (added to `PermissionRegistry` — no existing distinct
  permission covered this capability, and the naming follows the established `rbac.*` convention;
  automatically seeded to `super_admin` since `RbacRoleSeeder` grants every `PermissionRegistry`
  code); lock the catalog row, reject if already deactivated (repeated deactivation is REJECTED, not
  silently idempotent — the specification defines no reactivation and no idempotent-deactivation
  semantics, so REJECT is the conservative, spec-consistent default); lock the linked `principals`
  row; set `deactivated_at`/`disabled_at` together via `forceFill()`; emit
  `non_human_principal_deactivated`. No reactivation capability was added — the specification does
  not define one.
- `PrincipalService::deleteUser()` now also emits `human_principal_tombstoned_user_deleted` (closing
  the remaining `§26` audit-event gap for the already-atomic, already-Codex-confirmed-PASS User
  deletion transaction — no change to that transaction's own logic).

### Tests

`tests/Feature/Rbac/NonHumanPrincipalLifecycleTest.php` (7 tests): System/Integration deactivation
transitions both rows atomically and `canAuthorize()` immediately returns false; unauthorized actor
→ DENY; STANDARD assurance → DENY; forced-failure rollback leaves BOTH rows unchanged; repeated
deactivation → REJECTED (deterministic, not silently idempotent); direct mass-assignment of
`deactivated_at` is silently dropped (proves the fillable boundary holds).

---

## IMP003-IMPL-m01 — MySQL Runtime Validation (Gate-Impact Evidence Gap)

**Status: EVIDENCE GAP (unchanged from the prior pass — reported, not fabricated)**

Attempted again this pass per the safety protocol: the project's own `.env`
(`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, `DB_PASSWORD=` empty) was tried
against the local ServBay MySQL instance and rejected (`Access denied for user 'root'@'localhost'
(using password: NO)`). No other disposable MySQL 8 credential or instance is available in this
environment. Per instruction, no credential guessing/brute-forcing was attempted, no real database
(`philanthropy_platform` or otherwise) was touched, and no destructive command was run against any
database. A full STATIC review of every MySQL-specific mechanism was performed instead (below) —
this is reported honestly as `NOT RUN` for runtime, not silently substituted with SQLite evidence.

---

## Mutation Authorization

| Item | Result |
|---|---|
| Role Assignment | PASS |
| Role Revocation | PASS |
| Authority Assignment | PASS |
| Authority Revocation | PASS |
| Role-Permission Grant | PASS |
| Role-Permission Revoke | PASS |
| ELEVATED Required | PASS |
| Grantor Principal Locked | PASS |

## Scope Integrity

| Item | Result |
|---|---|
| Resolver Required for Concrete Scope | PASS |
| Target Lock | PASS |
| Target Lifecycle Validation | PASS |
| Missing Resolver | REJECT |
| Delete/Assignment Race | STATIC PASS (only concrete resolver today is OWN, whose target-deletion path is the already-atomic Canonical User Deletion Transaction; race contract for future resolvers is `lockAndValidateTarget()` itself — see M02 above) |

## RBAC Audit

| Item | Result |
|---|---|
| Role Assignment Audit | PASS |
| Role Revocation Audit | PASS |
| Authority Assignment Audit | PASS |
| Authority Revocation Audit | PASS |
| Role-Permission Grant Audit | PASS |
| Role-Permission Revoke Audit | PASS |
| Non-Human Principal Deactivation Audit | PASS |
| Transactional Consistency | PASS (audit emission occurs inside the same `DB::transaction()` closure as the mutation — an exception there rolls back the mutation too) |

## Non-Human Principal Lifecycle

| Item | Result |
|---|---|
| System Deactivation | PASS |
| Integration Deactivation | PASS |
| Atomic Catalog + Principal Transition | PASS |
| Rollback | PASS |
| Authorization After Deactivation | DENY |

## MySQL Static

| Item | Result | Notes |
|---|---|---|
| Migration Order | PASS | Unchanged this pass — no migration file was modified (`git status` confirms zero diff under `database/migrations/`); order remains `roles, permissions, authority_types, system_principals, integration_principals -> principals -> role_permissions, principal_role_assignments -> authority_assignments`. |
| CHECK | STATIC PASS | `chk_principals_kind_consistency`, `chk_pra_scope_matrix`, `chk_aa_scope_matrix`, `chk_aa_no_self_grant` all present in migration source, unchanged, MySQL-driver-guarded. |
| Generated Columns | STATIC PASS | `active_assignment_key`/`active_grant_key`, driver-conditional CONCAT/`\|\|`, unchanged. |
| Active Uniqueness | STATIC PASS | Unique index on each generated column, unchanged; new M02 scope-target lock happens BEFORE the renewal/insert step, so it does not alter the uniqueness mechanism itself. |
| Lock Order | STATIC PASS | Now explicitly 1. Grantor Principal → 2. Target Principal (PK-ascending) → 3. Concrete Scope Target (`ScopeResolver::lockAndValidateTarget()`, new this pass) → 4. Active-slot inspection → 5. Mutation → 6. Commit — documented in each service's class docblock. |

## MySQL Runtime

| Item | Result |
|---|---|
| Disposable MySQL Used | NO |
| Fresh Migration | NOT RUN |
| Constraint Validation | NOT RUN |
| Authorization Tests | NOT RUN |
| Concurrency | NOT RUN |
| Reason if NOT RUN | No disposable MySQL 8 credential/instance available; project `.env` credentials rejected by the local instance; no guessing attempted. |
| Real Production Database Touched | NO |
| Credential Guessing | NO |

## Tests

- PHP Suite: **PASS**
- Tests: **200**
- Assertions: **515**
- Failures: **0**
- Errors: **0**
- Mandatory Negative Tests: **PASS** (every M01-M04 negative case listed in the remediation prompt is covered — see per-finding sections above)

## Quality

| Check | Result |
|---|---|
| `php artisan about` | PASS |
| `vendor/bin/pint --test` | Initially FAIL (2 files, cosmetic: brace/operator spacing, phpdoc alignment) → fixed via `vendor/bin/pint` → re-verified PASS |
| `npm run type-check` | PASS |
| `npm run build` | PASS (584 modules, 2.70s) |
| `composer audit` | PASS (no advisories) |
| `git diff --check` | PASS |

## Regression

| Item | Result |
|---|---|
| IMP-002 | PASS (full suite re-run, unaffected — no IMP-002 file touched this pass) |
| Human Principal Lifecycle | PASS (Canonical User Deletion Transaction logic itself unchanged; only a new audit-emission line added inside the existing transaction) |
| Q25 | PASS (Bridge command untouched; still bypasses `RoleAssignmentService` entirely, so M01's new permission gate does not affect it) |
| Authorization Evaluator | PASS (unchanged — reused, not rewritten, per instruction) |
| Super Admin ≠ Financial Authority | PASS |
| System Principal ≠ God Mode | PASS |
| API Principal ∩ Token | PASS (`ApiAuthority` unchanged) |
| Registration ≠ Authorization | PASS |
| Invitation ≠ Authorization | PASS |
| Partner ≠ Tenant | PASS (no tenant/domain table introduced; `FakePartnerScopeResolver` remains a test-only fixture) |
| Shared Hosting | PASS (no new infrastructure dependency introduced) |

## Scope Control

- Later Business Domain Implemented: **NO**
- Locked Baseline Changed: **NO**
- New Human Decision: **NO**

## Evidence

`docs/audits/IMP-003-IMPLEMENTATION-REMEDIATION-1.md` (this document).

## Remaining Findings

- BLOCKER: none
- MAJOR: none
- MINOR: none
- EVIDENCE GAP: `IMP003-IMPL-m01` — MySQL runtime validation (fresh migration, constraint, authorization,
  concurrency) remains NOT RUN; no safe disposable MySQL 8 instance available in this environment.
  Full static review performed instead (see "MySQL Static" above).
- HUMAN DECISION: none

## Recommendation

**READY FOR CODEX TARGETED IMP-003 IMPLEMENTATION RE-AUDIT**

## Stage Gate

NOT APPROVED

## Merge Authorization

NOT GRANTED

STOP.
