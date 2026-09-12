# IMP-003 — RBAC + Scope + Business Authority — Implementation Evidence

## Human Implementation Authorization

"Saya setuju. IMP-003 Implementation Authorized." — 2026-09-12T16:47:47Z.

## Reviewed Specification Baseline

- Commit: `39065278658304a2a5a28bd7c5a908c1dd01e2a5` (`3906527`), `master`.
- Codex Targeted Readiness Re-Audit Pass 3: PASS across all dimensions. 0 BLOCKER / MAJOR / MINOR
  / HUMAN-DECISION findings open. One non-blocking EDITORIAL (`IMP003-READY-e01` — dedicated Actor
  Catalog artifact absent) explicitly not addressed by inventing actor semantics during this pass.
- Primary implementation authority: `docs/implementation/IMP-003-rbac-scope-business-authority.md`.

## Git

- Starting branch: `master`, starting commit `3906527` (clean working tree verified before branching).
- Implementation branch: `impl/003-rbac-scope-business-authority`.
- Ending commit: implementation commit created immediately after this evidence file (see repository
  history — this document is committed alongside/immediately after the code commit).
- Working Tree: clean after commit (verified via `git status`).
- Merged: **NO**.
- Pushed: **NO**.

## Implementation Summary

| Area | Result |
|---|---|
| Identity/Permission/Scope/Ownership/Business Authority/Financial Authority/Approval Authority/Assurance/Security Restriction kept as separate, non-overlapping primitives | PASS |
| No authorization column added to `users` | PASS |
| Canonical Principal Registry (HUMAN/SYSTEM/INTEGRATION) with strong referential integrity | PASS |
| Human Principal LIVE/TOMBSTONED lifecycle, DB-level CHECK (MySQL) + application-level guard (every driver) | PASS |
| `principals.human_user_id` FK is RESTRICT/NO ACTION (direct User deletion while referenced is rejected) | PASS |
| Atomic Canonical User Deletion Transaction (lock, revoke, tombstone, delete, single transaction) | PASS |
| Roles/Permissions normalized, DB-backed, no role/permission inferred from email/URL/invitation/registration | PASS |
| Role -> Permission durable grant/revocation history (never hard-deleted) | PASS |
| Principal -> Role / Principal -> Authority assignments: scope, temporal validity, revocation, history, Principal-based attribution, stable lock order, uniqueness via generated `active_assignment_key`/`active_grant_key` | PASS |
| Business/Financial Authority Types: 7 approved primitives seeded, extensible registry, Super Admin != automatic Financial Authority | PASS |
| Registration Boundary / Invitation Boundary: identity-only, no automatic role/permission/scope/authority | PASS (unchanged — enforced by IMP-002's existing services; no new grant path introduced) |
| Scope Model: GLOBAL_PLATFORM/ORGANIZATION/OWN + 7 concrete-target types, Partner != Tenant (no tenant table, no independent settlement/payment infra introduced) | PASS |
| Scope Type Contract: unknown `scope_type` value rejected via strict backed-enum cast (application layer) + `chk_pra_scope_matrix`/`chk_aa_scope_matrix` (MySQL DB layer); scope_id-vs-type matrix enforced at both layers | PASS |
| Polymorphic Scope Target resolver contract (`ScopeResolver`) + the one IMP-003-owned concrete resolver (`OwnUserScopeResolver`) | PASS |
| MySQL 8 Active Assignment Uniqueness: generated STORED columns, NULL while revoked, unique-indexed, no `NOW()`-dependence, no cleanup job | PASS |
| Temporal Authorization: `starts_at`/`ends_at`/`revoked_at` respected by `isActive()` and by the evaluator's query filter | PASS |
| Principal-based attribution (`*_by_principal_id`, never `*_by_user_id`) on both assignment tables | PASS |
| System Principal / Integration Principal: explicit, no god-mode, default DENY, catalog `deactivated_at` now also consulted by `Principal::canAuthorize()` (fixed during this pass — see Known Limitations/Fixes) | PASS |
| API Authority primitive (`ApiAuthority::effective()`): Principal Permission Set INTERSECT Token Capability Set, never union — contract/primitive only, no token issuance model implemented | PASS |
| Ownership as an independent AND-term (`AuthorizationRequest::$ownershipCheck`), never encoded as Role | PASS |
| Resource State / Approval State: contract-only (`$resourceStatePredicate`, `ApprovalCandidateResolver`), no premature domain workflow implemented | PASS |
| Authentication Assurance: consumes IMP-002's `AssuranceService::isElevated()` directly, no new assurance column | PASS |
| Security Restriction: re-checks IMP-002's `User::canAuthenticate()` on every evaluation, no competing state | PASS |
| Central `AuthorizationEvaluator` implementing the exact Step-0-plus-9-term AND-chain, short-circuiting DENY | PASS |
| Base Policy trait (`AuthorizesUsingRbac`) + one concrete `RbacManagementPolicy` for `rbac.*`/`identity.security.transition` | PASS |
| Default DENY posture: every unresolved/missing/unknown term denies; no permissive fallback found in review | PASS |
| Self-Escalation Protection: Self Role Assignment, Self Authority Grant (including Financial) denied unconditionally, at both service layer and (for Authority) model layer | PASS |
| Q25 Bridge (`rbac:bridge-first-super-admin`): two independent guards, re-validated under lock, grants ONLY `super_admin` Role at GLOBAL_PLATFORM scope, `assigned_by_principal_id = NULL`, no Business/Financial/Approval Authority ever granted, audit event `super_admin_canonically_authorized` emitted | PASS |
| Migration Order: `roles, permissions, authority_types, system_principals, integration_principals -> principals -> role_permissions, principal_role_assignments -> authority_assignments`, matches the specification's dependency graph exactly | PASS |
| Package Decision: no third-party RBAC package installed; Laravel Policies/Gates + custom normalized schema only | PASS |

## Database

| Item | Result |
|---|---|
| 9 new migrations created, FK-safe order | PASS |
| SQLite fresh migration (`migrate:fresh --force`, `DB_DATABASE=:memory:`) | PASS |
| SQLite fresh migration + seed (`--seed`) | PASS |
| Generated `active_assignment_key`/`active_grant_key` STORED columns, driver-conditional expression (CONCAT for MySQL, `||` for SQLite) | PASS |
| MySQL-only CHECK constraints (`chk_principals_kind_consistency`, `chk_pra_scope_matrix`, `chk_aa_scope_matrix`, `chk_aa_no_self_grant`) applied via raw `DB::statement` ALTER TABLE, guarded to `getDriverName() === 'mysql'` | PASS (verified present in migration source; not exercised against a live MySQL instance — see MySQL Runtime below) |
| Application-level guards mirroring every CHECK (on every driver, including SQLite) — `Principal::booted()` (`saving`), `AuthorityAssignment::booted()` (`creating`) | PASS |
| Fresh MySQL Migration | **NOT RUN** — see "MySQL Runtime" below |

## Tests

- PHP Test Suite: **PASS**
- Test Count: **165**
- Assertion Count: **462**
- All pre-existing IMP-001/IMP-002 tests continue to pass unmodified in behavior; two IMP-002-era
  tests (`BootstrapSuperAdminTest::test_bootstrap_introduces_no_role_or_permission_schema`,
  `SchemaBoundaryTest::test_no_roles_or_permissions_tables_exist`) asserted "no RBAC schema exists
  yet" — now that IMP-003 has shipped that schema, both were updated to their enduring intent (no
  `role_user`/direct-User-scoped pivot; bootstrap itself still grants no role) rather than deleted
  or weakened. See `tests/Feature/Identity/BootstrapSuperAdminTest.php` and
  `tests/Feature/Identity/SchemaBoundaryTest.php`.

| Test area | Result |
|---|---|
| Principal creation idempotency (Human/System/Integration), grants nothing | PASS |
| Application-level kind-consistency guard (every driver) | PASS |
| Canonical User Deletion Transaction — success path (User absent, Principal TOMBSTONED, `human_user_id` NULL, assignments revoked, history retained) | PASS |
| Direct User deletion outside canonical lifecycle — FK REJECT | PASS |
| Canonical User Deletion Transaction — forced-failure rollback (User remains, Principal remains LIVE, `human_user_id` unchanged, assignments unchanged) | PASS |
| Principal attribution survives grantor's own User deletion | PASS |
| Self Role Assignment — DENY | PASS |
| Self Authority Grant (incl. Financial) — DENY, unconditional, including at the model layer | PASS |
| Scope matrix enforcement (GLOBAL_PLATFORM/ORGANIZATION/OWN null; others non-null) for both Role and Authority assignments | PASS |
| Renewal/replacement pattern (revoke-then-insert in one transaction) | PASS |
| Active-assignment uniqueness at the DB level (no `NOW()` dependence) | PASS |
| Temporal tests (future/active/expired/revoked) | PASS |
| Role-Permission grant history durability (revoked grant row retained, not hard-deleted) | PASS |
| Tombstoned Principal cannot receive Role/Authority, cannot act as grantor | PASS |
| Q25 Bridge: refuses with no bootstrap; grants only `super_admin`/GLOBAL_PLATFORM/NULL-attributed; cannot run twice; refuses if bootstrapped identity deleted; grants zero Authority Assignments | PASS |
| Super Admin without Financial Authority — DENY on a financial-authority-requiring action | PASS |
| Fundraiser without Business Authority — DENY | PASS |
| Partner Representative cross-scope (fixture resource) — DENY, no tenancy introduced | PASS |
| Donor cross-ownership (OWN scope, real `User` resource) — DENY | PASS |
| Security Restriction: DISABLED — DENY; SUSPENDED — DENY (even with an otherwise-valid active Role assignment) | PASS |
| Authentication Assurance: ELEVATED required + STANDARD session — DENY; ELEVATED session — ALLOW | PASS |
| Invalid/unresolved Principal — DENY, no fallback | PASS |
| Invalid Scope: unknown `scope_type` value — REJECT at write (ValueError from the strict enum cast); missing scope target resource with a scope resolver supplied — DENY at Step 0 | PASS |
| System Principal overreach (narrow/no grant, unrelated capability attempt) — DENY | PASS |
| Integration Principal overreach (granted one narrow permission, unrelated capability attempt) — DENY | PASS |
| Disabled System Principal (catalog `deactivated_at` set) cannot authorize | PASS |
| API Authority intersection (`ApiAuthority::effective()`): Principal ∩ Token, never union, in both directions | PASS |
| Migration test (fresh migrate against disposable SQLite, dependency order verified) | PASS |
| Schema/security regression (`users` carries no authorization column; canonical RBAC tables exist with Principal-based, not User-based, attribution columns; generated key columns present) | PASS |

## MySQL Runtime

| Item | Result | Details |
|---|---|---|
| Fresh MySQL Migration | **NOT RUN** | The project's `.env` (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, `DB_PASSWORD=` empty) does not authenticate against the local ServBay MySQL instance (`Access denied for user 'root'@'localhost' (using password: NO)`). No other disposable MySQL credential/instance was available in this environment. Per instruction, credential guessing/probing was not attempted, and the real configured database (`philanthropy_platform`) was never touched. SQLite fresh-migration evidence above is NOT substituted as MySQL evidence. |
| MySQL Runtime Authorization Tests | **NOT RUN** | Same reason as above. |
| MySQL Concurrency Tests | **NOT RUN** | Same reason as above. The stable lock order (Grantor Principal -> Target Principal (PK-ascending) -> Mutation -> Commit) is implemented exactly per specification and covered by a static/transactional code review (`RoleAssignmentService`/`AuthorityAssignmentService`/`PrincipalService::deleteUser()` — each wraps its locking + mutation in one `DB::transaction()`), but true concurrent-process MySQL verification was not performed. This is a reported limitation, not a claimed pass. |

## Quality

| Check | Result |
|---|---|
| `php artisan about` | PASS (boots; MySQL configured as default driver, SQLite used for automated tests per `phpunit.xml`, unchanged from IMP-002) |
| `vendor/bin/pint --test` | Initially FAIL (8 files, cosmetic: import ordering, brace/docblock alignment, `new Class()` parens) — fixed via `vendor/bin/pint`; re-verified PASS |
| `npm run type-check` | PASS (no output, no errors) |
| `npm run build` | PASS (584 modules transformed, built in 2.73s) |
| `composer audit` | PASS ("No security vulnerability advisories found.") |
| `git diff --check` | PASS (no whitespace errors) |

## Security

| Check | Result |
|---|---|
| Default DENY | PASS — every AND-chain term defaults to DENY on missing/unresolved input; verified by test and by code review (`AuthorizationEvaluator::evaluate()` returns `false` at the first failing check, `true` only after every supplied term passes) |
| IDOR / cross-scope | PASS — `AuthorizationContext`/`ScopeResolver` contract requires server-side resolution before any resource is exposed; Donor cross-ownership and Partner cross-scope tests both DENY |
| Self-Escalation | PASS — unconditional DENY for self-role and self-authority grant, at service layer (both) and model layer (authority) |
| Privilege Escalation (broader-token, System/Integration overreach) | PASS |
| Confused Deputy (grantor cannot smuggle authority via a target's own request) | PASS — grantor/target are always distinct resolved Principals, locked and validated independently before mutation |
| Security Restriction | PASS — DISABLED/SUSPENDED denies even with an otherwise-valid active Role assignment |
| Authentication Assurance | PASS — STANDARD session cannot satisfy an ELEVATED requirement |

## Regressions

| Item | Result |
|---|---|
| Architecture (Partner != Tenant, no tenant table/settlement/payment infra introduced) | PASS |
| Database (Human Principal FK RESTRICT, direct User deletion blocked, canonical deletion atomic, historical Principal retained, Principal-based attribution, migration order valid, active-assignment uniqueness deterministic, history durable) | PASS |
| Security (see above) | PASS |
| IMP-002 (no regression — full IMP-002 test suite re-run, all pass; two obsolete "RBAC doesn't exist yet" assertions updated to their enduring intent) | PASS |
| Registration != Authorization / Invitation != Authorization | PASS (unchanged — no new grant path added to IMP-002's registration/invitation services) |
| Super Admin != Financial Authority | PASS |
| System Principal != God Mode | PASS |
| API Authority = Principal ∩ Token | PASS |

## Scope Control

- Later Business Domain Implemented: **NO**.
- New Human Decision Introduced: **NO**.
- Locked Baseline Changed: **NO**.

## Evidence Path

- This document: `docs/audits/IMP-003-IMPLEMENTATION.md`.
- Implementation branch: `impl/003-rbac-scope-business-authority`.

## Known Limitations

1. **MySQL runtime validation NOT RUN** (fresh migration, authorization tests, concurrency) — no
   safely-available disposable MySQL credential/instance in this environment (see "MySQL Runtime").
   All schema/CHECK-constraint/generated-column logic has been verified by direct source review of
   the migration SQL and by full SQLite-driven functional test coverage, but this does not
   substitute for MySQL-specific runtime proof.
2. **`IMP003-READY-e01`** (dedicated Actor Catalog artifact) remains open, as instructed — not
   addressed by inventing actor semantics during this implementation pass.
3. Only one concrete `ScopeResolver` (`OwnUserScopeResolver`) is implemented, per specification —
   every other scope type's resolver is deferred to its owning future domain stage.
4. `RbacManagementPolicy` covers only the `rbac.*`/`identity.security.transition` capabilities
   IMP-003 itself owns; no route/controller wiring was added, since no admin UI/route surface for
   RBAC management exists yet in this codebase and none was requested — the Policy is available for
   a later stage to wire into actual routes.
5. During implementation, a genuine gap was found and fixed: `Principal::canAuthorize()` for
   System/Integration kinds originally checked only the `principals.disabled_at` column and never
   consulted the referenced catalog row's own `deactivated_at` — meaning retiring a
   `system_principals`/`integration_principals` catalog entry would not, by itself, deny
   authorization. Fixed to consult both; `SystemPrincipal`/`IntegrationPrincipal` also had
   `deactivated_at` missing from `$fillable` (silently dropped on `create()`), fixed alongside.
   Covered by `AuthorizationEvaluatorTest::test_disabled_system_principal_cannot_authorize`.

## Findings

None (BLOCKER / MAJOR / MINOR) — no unresolved implementation-time finding is being carried forward
beyond the pre-existing, explicitly-accepted `IMP003-READY-e01` EDITORIAL item.

## Recommendation

Implementation complete and internally verified (SQLite-driven functional/database/security
coverage full-green; MySQL-specific runtime evidence outstanding per "Known Limitations" #1).
Recommend a Codex independent review pass before any Stage Gate approval or merge decision.

## Stage Gate

**NOT YET APPROVED**

## Merge Authorization

**NOT GRANTED**

STOP.
