# IMP-003 Readiness Remediation Pass 1

Task:
IMP-003 Targeted Specification Remediation Pass 1 (Codex Independent Readiness Review:
NEEDS CORRECTION — 1 BLOCKER, 6 MAJOR, 1 MINOR, 1 EDITORIAL)

## Review Base / Ending Commit

```
Review Base:    ae3388a4b7ae9a34d73d484c313dc23a90b2700b
Branch:         master (specification-only work continues on master, matching the established
                precedent for IMP-000/001/002 spec/readiness commits)
```

Mode: SPECIFICATION REMEDIATION ONLY. No application code, migration, model, policy, gate, or
dependency was created or modified — confirmed via `git status --short` after this pass touches
exactly one file: `docs/implementation/IMP-003-rbac-scope-business-authority.md`.

---

## IMP003-READY-B01 — Self-Grant of Financial Authority

**RESOLVED**

Root cause: the "Super Admin Canonical Authorization (Q25 bridge)" section's step 3 let the
freshly-bridged Super Admin identity grant itself a Financial Authority Type immediately, "acting
under ELEVATED assurance" — an unauthorized self-grant with no distinct authorizing Principal.

Fix: step 3 rewritten to grant NOTHING beyond the `super_admin` Role. Financial/regulated
authority for that identity (or any identity) is explicitly left UNASSIGNED until either (a) a
distinct, already-authorized Principal grants it through the ordinary Authority Assignment
mechanism, or (b) a later stage specifies its own deterministic provisioning mechanism — neither
is invented by this remediation, per its own instruction. The Bridge's audit event
(`super_admin_canonically_authorized`) now explicitly documents that it names ONLY the Role grant,
so no later audit review could mistake this for a broader grant.

Section changed: "Super Admin Canonical Authorization (Q25 bridge)" (step 3 rewritten; idempotency/
concurrency note added). Cross-referenced from "Self-Escalation Protection" (the absolute rule
now states there is no Bridge exception for authority grants at all) and the Database Contract's
`authority_assignments.assigned_by_user_id` (now `NOT NULL`, no nullable exception).

Authority basis: `docs/05-rbac/RBAC-ARCHITECTURE.md` / `BUSINESS-AUTHORITY-MODEL.md` — "Super Admin
!= automatic Financial Authority" is a LOCKED distinction; removing an unauthorized exception to it
restores conformance, it does not create new policy.

---

## IMP003-READY-M01 — Prohibit Self-Role Assignment

**RESOLVED**

Root cause: "Self-Escalation Protection" carved out "UNLESS that Principal already holds
`rbac.role.assign` scoped GLOBAL_PLATFORM" as an exception to the self-role-assignment DENY rule.

Fix: the entire section was rewritten as an ABSOLUTE invariant with NO ordinary-action exception —
self-role assignment, self-scope expansion, self-business-authority grant, self-financial-
authority grant, and self-approval-step assignment are ALL unconditional DENY, regardless of what
the acting Principal holds. The section explicitly clarifies that the Q25 Bridge is NOT an
exception to this rule (it is not a Principal exercising an ordinary RBAC action on themselves — it
is a one-time, non-interactive, CLI-only, deterministic conversion of an already-Human-authorized
event, gated by its own independent guards, and it grants ONLY a Role, never any Authority).

Section changed: "Self-Escalation Protection" (full rewrite). Cross-referenced from the Role ->
Permission Assignment section (unaffected — that rule concerns a Role gaining a NEW permission the
grantor doesn't hold, a different, still-valid vector, left unchanged) and the Test Contract's
"Self-Escalation" subsection (expanded — see M06 below).

Authority basis: `docs/04-security/SECURITY-INVARIANTS.md` — "Duties that must be separated... are
never collapsed into one actor/role without an explicit, approved exception" — no exception is
approved by any Level 1-3 document, so none is specified here.

---

## Self-Escalation Rule (§5, folded into M01's disposition)

The general invariant requested by §5 is now stated explicitly at the top of the rewritten
"Self-Escalation Protection" section, covering Role, Scope, Business Authority, Financial
Authority, and Approval-step assignment uniformly, with no generalized self-service authorization
system introduced (§5's explicit prohibition honored — no new mechanism was invented for
"recovery/provisioning," which the section notes remains a future, separately-controlled concern).

---

## IMP003-READY-M02 — Fix Scope Representation

**RESOLVED**

Root cause: the original Data Scope Model section stated `scope_type` itself could be null "for
GLOBAL_PLATFORM/ORGANIZATION" — conflating "this scope type has no concrete target row" (true) with
"no scope type was recorded" (never intended, and a real ambiguity risk).

Fix: a new "Scope Type / Scope Target Matrix" subsection makes `scope_type` NEVER null — every
assignment row always states which of the 10 taxonomy values applies — and defines, per scope
type, whether `scope_id` is required (non-null) or forbidden (must be null): `GLOBAL_PLATFORM`,
`ORGANIZATION`, and `OWN` require `scope_id = NULL` (no concrete row exists for any of the three —
`OWN` is resolved dynamically against the acting principal, never a stored target); every other
scope type (`PARTNER`, `CAMPAIGN`, `PROGRAM`, `FUND`, `FUNDRAISER`, `BENEFICIARY_CASE`,
`ASSIGNED_WORK`) requires a non-null `scope_id`. Write-time validation (a MySQL 8 `CHECK`
constraint + application validation) rejects any row violating this matrix. Unknown scope types
and invalid scope targets are both explicitly DENY/REJECT, restated in "Default Deny" and the
Test Contract.

Section changed: "Data Scope Model" (new "Scope Type / Scope Target Matrix" subsection). New
"Scope Target Integrity" subsection added (see M04 disposition below — it also closes part of
§15/§16). Database Contract's `principal_role_assignments`/`authority_assignments` field
descriptions updated to reference the matrix instead of the old ambiguous nullable `scope_type`.

Authority basis: `docs/05-rbac/DATA-SCOPE-MODEL.md`'s 10-value taxonomy is reused verbatim; the
non-null requirement is a data-integrity correction to an implementation error, not a new scope
value or business rule.

---

## Scope Uniqueness Normalization (§7, folded into M02/M03's disposition)

Resolved together with M03 below — see "Scope Uniqueness Normalization + MySQL 8 Active-Assignment
Uniqueness" in the Database Contract section. `scope_id` is normalized via `IFNULL(scope_id, 0)`
inside the generated `active_assignment_key` expression — a deterministic, row-local
transformation that never depends on SQL's own NULL-never-collides behavior for `scope_id`
specifically (that behavior is instead deliberately harnessed, correctly, at the `revoked_at`
level — see M03).

---

## IMP003-READY-M03 — MySQL 8 Active Assignment Uniqueness

**RESOLVED**

Root cause: the original `principal_role_assignments`/`authority_assignments` uniqueness
specification used a `WHERE revoked_at IS NULL AND (ends_at IS NULL OR ends_at > NOW())` partial-
index condition. MySQL 8 does not support filtered/partial unique indexes, and a generated column
cannot be a deterministic function of `NOW()` (generated columns must depend only on other columns
in the same row) — this design was not actually implementable on the target platform. Separately,
relying on plain nullable-column composite uniqueness for `scope_id` was unsafe in the opposite
direction: MySQL never treats multiple NULLs in an indexed column as duplicates of each other, so a
naive composite unique key across a nullable `scope_id` would have silently ALLOWED duplicate
"active" GLOBAL_PLATFORM/ORGANIZATION/OWN assignments.

Fix — concrete, MySQL-8-compatible, deterministic design (new "Scope Uniqueness Normalization +
MySQL 8 Active-Assignment Uniqueness" subsection in the Database Contract):

```
1. normalized_scope_id = IFNULL(scope_id, 0)                          (deterministic, row-local)
2. active_assignment_key = CASE WHEN revoked_at IS NULL
     THEN CONCAT(principal_id, ':', role_id, ':', scope_type, ':', normalized_scope_id)
     ELSE NULL END                                                     (STORED generated column,
                                                                        depends only on other
                                                                        columns in the same row —
                                                                        NEVER on NOW())
3. UNIQUE INDEX on active_assignment_key                                (the entire uniqueness
                                                                        contract; race-safe via
                                                                        InnoDB's own atomic insert-
                                                                        conflict detection)
```

A revoked row's key is NULL (MySQL correctly never collides multiple NULLs — deliberately
harnessed here, for the state where non-collision IS correct); an active row's key is a concrete
string (MySQL correctly rejects a second row with the same key — collision-prevention where it IS
required). Expiration (`ends_at`) deliberately does NOT participate in the uniqueness key — it
only affects live evaluation (`WHERE ... AND (ends_at IS NULL OR ends_at > NOW())`, a normal query
predicate, never a stored/indexed value). §9/§12's "how does renewal work" is answered explicitly:
renewing a (principal, role-or-authority, scope) tuple that currently has an expired-but-unrevoked
row REQUIRES, within ONE transaction, first revoking that row, then inserting the new one — the
same "supersede, then create" pattern already reviewed and accepted for IMP-002's
`EmailChangeRequest`.

Section changed: Database Contract (`principal_role_assignments` uniqueness entry rewritten; new
subsection added; `authority_assignments` cross-references the same technique rather than
duplicating it). Concurrency section updated to describe the stable lock order this design
depends on (see M04 disposition — lock order now begins with the `principals` row).

Authority basis: pure engineering/database-integrity correction; no locked document specifies a
storage mechanism, so `docs/00-governance/CHANGE-CONTROL.md`'s "least-complex implementation-
compatible design" standard governs, same as originally invoked for the Permission Registry.

---

## IMP003-READY-M04 — Strong Principal Referential Integrity

**RESOLVED**

Root cause: `principal_role_assignments`/`authority_assignments` referenced a bare
`principal_type` (string) + `principal_id` (BIGINT) pair with explicitly NO foreign key
("enforced at the application layer") — an assignment could reference a nonexistent
User/System/Integration identity with nothing but application code preventing it, and a
User/System/Integration source could be deleted out from under an assignment with no defined
behavior.

Fix — new canonical `principals` table (Database Contract, new subsection):

```
principals (id, principal_kind ('human'|'system'|'integration'), human_user_id nullable UNIQUE
  FK->users.id nullOnDelete, system_principal_id nullable UNIQUE FK->system_principals.id,
  integration_principal_id nullable UNIQUE FK->integration_principals.id, disabled_at nullable
  (system/integration only), timestamps) — CHECK: exactly one of the three identity columns is
  non-null and matches principal_kind.
```

Every `principal_role_assignments.principal_id` / `authority_assignments.principal_id` is now a
REAL foreign key to `principals.id` — replacing the unconstrained polymorphic pair entirely. A new
"Principal Lifecycle" subsection specifies creation (lazy, idempotent, granting nothing by
itself), linkage (one `principals` row per human User, enforced by the UNIQUE constraint),
human disable/deactivate behavior (a human principal has NO independent status column — its live
state is ALWAYS read from IMP-002's own `users.lifecycle_state`/`security_restriction`, never a
second competing source of truth; `disabled_at` applies only to system/integration kinds, which
have no IMP-002 lifecycle of their own), deletion protection (`human_user_id` uses `nullOnDelete`,
mirroring IMP-002 Remediation Pass 2's own `super_admin_bootstraps.user_id` pattern — the
`principals` row survives User deletion as an orphan, never hard-deleted, never re-linked), and the
explicit invariant that an orphaned human principal (post-deletion) MUST fail authorization
deterministically (Applicable Subject Context Resolution denies it — not a secondary check that
could be forgotten).

Section changed: Database Contract (new `principals` subsection, `principal_role_assignments`/
`authority_assignments` field lists updated to the real FK), "System Principal / Integration
Principal" (updated to reference `principals` linkage instead of a bare discriminator), "Business
Authority > Authority Assignment" (conceptual field list updated), Concurrency (lock order now
begins with the `principals` row), Implementation Order (schema step reordered — `principals`
first, since every assignment table's FK depends on it), Test Contract (new "Invalid Principal"
subsection), Database Test Contract (new principals FK/CHECK/orphan tests).

Authority basis: `docs/03-database/DATABASE-ARCHITECTURE.md` "Strong referential integrity" is a
LOCKED baseline principle this fix restores conformance to; it does not create new architecture —
a lookup/identity-registry table is the standard, unavoidable way to give a polymorphic reference
a real FK target, and IMP-002's own `super_admin_bootstraps.user_id` pattern is reused directly for
deletion protection rather than inventing a new one.

---

## Principal Lifecycle (§14, folded into M04's disposition)

Covered fully above and in the "Principal Lifecycle" subsection: creation, linkage, disable/
deactivate, deletion protection, orphan-denial, and audit-identity behavior are all specified
concretely, not left as "application enforced" alone.

---

## Scope Target Integrity (§15/§16, folded into M02/M04's disposition)

New "Scope Target Integrity" subsection (Data Scope Model) specifies, for the polymorphic
`scope_id` (which cannot have a single native FK today since most target domains don't exist yet):
type whitelist (scope_id is only ever interpreted under the scope_type on the SAME row), target
existence validation (checked transactionally at assignment-creation time), transactional
validation (one transaction, not a fire-and-forget pre-check), deletion protection (a future
domain's own FK/deletion-restriction once its target table exists), inactive/deleted target
behavior (resolver returns "no match," never "unrestricted"), authorization default (DENY), and an
orphan-detection test. This replaces the earlier "application enforced" statement with a complete
contract, per §15's explicit instruction not to leave it at that.

---

## IMP003-READY-M05 — Registration Must Not Auto-Grant Role

**RESOLVED**

Root cause: the Actor Catalog table and Role Model section both described `donor`/`fundraiser` as
"system roles, auto-assigned at registration."

Fix: both rows in the Actor Catalog table now state "NO Role assigned at registration." A new
"No Automatic Role at Registration" subsection (Role Model) states the corrected rule explicitly:
IMP-003 does not seed, and no workflow automatically assigns, a `donor`/`fundraiser` Role, ever, as
a side effect of IMP-002 identity creation. Baseline self-service (view/update own identity) needs
no Role/Permission at all — it is authorized by Authenticated + Ownership alone, with the
Permission/Business-Authority/Resource-State/Approval-State AND-terms trivially satisfied because
such an action does not require them. If a later domain stage needs a Role-gated Donor/Fundraiser
capability, that stage defines and assigns it — never automatically, never invented here.

Section changed: "Actor Materialization" table (Donor/Fundraiser rows), "Role Model" (new
subsection, example Role list no longer includes `donor`/`fundraiser`), Test Contract (new
regression-check tests: "Donor/Fundraiser identity has NO Role at all immediately after
registration").

Authority basis: IMP-002's own specification already states "Identity Creation != Role Assignment"
and "a self-registered Fundraiser receives no automatic Fundraiser business authority" — this fix
restores conformance to an already-locked IMP-002 boundary, it does not create new policy.

---

## Donor / Fundraiser / Partner / Internal / Super Admin Registration Boundary (§18-20, folded into M05's disposition)

Explicitly restated in the corrected Role Model subsection and unchanged in "Invitation Intent
Bridge" (which already correctly described a two-step accept-then-authorize design for Partner
Representative / Internal Administrative Identity / Super Admin intent — no change was needed
there; it was already conformant, re-verified during this pass).

---

## IMP003-READY-M06 — Complete Authorization Test Contract

**RESOLVED**

Root cause: the Test Contract had no explicit test for "Authenticated" itself, no test
reconciling DATA-SCOPE-MODEL.md's "Applicable Subject Context" concept, no "Resource State" or
"Approval State" tests (two of the nine locked AND-terms had zero test coverage), no System/
Integration Principal least-privilege tests, no Invalid Principal / Invalid Scope Reference tests,
and the Self-Escalation tests did not cover approval-step self-assignment or restate the
now-absolute (no-exception) self-role/financial-authority rules.

Fix: the Canonical Authorization Formula gained an explicit "Step 0: Applicable Subject Context
Resolution" precondition (reconciling DATA-SCOPE-MODEL.md's context-gathering concept with
RBAC-ARCHITECTURE.md's locked 9-term AND-chain, without adding a 10th AND-term to that locked
formula — Step 0 is a precondition to evaluating the 9 terms, not an additional term itself). The
Test Contract section was rewritten with explicit subsections for every term: Authentication,
Applicable Subject Context, Permission, Scope (+ unknown-scope-type + invalid-scope-target),
Ownership (+ resolver-unavailable -> DENY, no fail-open), Business Authority, Resource State (new),
Approval State/Step Assignment (new), Assurance, Security Restriction (+ unrecognized-value ->
DENY), System Principal Least Privilege (new), Integration Principal Least Privilege (new — NOT
deferred, since Integration Principal is already in this specification's scope), Invalid Principal
(new), Invalid Scope Reference (new), Super Admin (+ explicit self-grant-attempt -> DENY test),
Fundraiser/Partner/Donor (+ M05 regression checks), API, Revocation, Default, and an expanded
Self-Escalation subsection (self-role, self-scope, self-permission-to-role, self-business/
financial-authority, self-approval-step — all DENY, unconditionally).

Section changed: "Canonical Authorization Formula" (Step 0 added), "Test Contract" (full rewrite).

Authority basis: `docs/00-governance/DEFINITION-OF-DONE.md`'s "Mandatory RBAC Tests" is the Level 6
baseline this extends; the additional tests trace directly to RBAC-ARCHITECTURE.md's own 9 AND-
terms (Resource State, Approval State were already locked terms with zero prior test coverage —
this is a completeness fix, not new policy) and to SECURITY-ARCHITECTURE.md's System/Integration
Principal / token-intersection principles (already locked, previously untested).

---

## IMP003-READY-m01 — Role-Permission History Must Not Depend on IMP-004

**RESOLVED**

Root cause: `role_permissions` specified hard-delete-on-revoke, reasoning that IMP-004's future
audit sink would preserve history — making IMP-003's own correctness depend on a stage that does
not exist yet, which the remediation instruction explicitly forbids ("Do not require IMP-004 to
make IMP-003 correct").

Fix: `role_permissions` now includes `granted_at`, `revoked_at`, `revoked_by_user_id` alongside the
existing `granted_by_user_id`; a grant is NEVER hard-deleted — revocation sets `revoked_at`/
`revoked_by_user_id`, and "active" grant = `revoked_at IS NULL`, enforced via the identical
`active_assignment_key`-style generated-column uniqueness technique used for the assignment
tables (so the same role/permission pair can be re-granted later as a new row while the original
revoked row remains queryable). This durable history exists independently of whatever IMP-004
eventually builds — IMP-004 is still the eventual SINK for audit events, but IMP-003 no longer
depends on it for its OWN correctness/reconstructability.

Section changed: Database Contract's `role_permissions` subsection (full rewrite). Database Test
Contract (new "role_permissions history" test).

Authority basis: `docs/03-database/DATABASE-INVARIANTS.md` "Policy/version historical
reproducibility" — this fix brings `role_permissions` into line with the SAME invariant already
correctly applied to every other table in this specification; it was an inconsistency within the
document, not a new requirement.

---

## Assignment History (§40, folded into m01's disposition)

`principal_role_assignments` and `authority_assignments` already used the durable revoked_at
pattern in the original draft and needed no change; only `role_permissions` had the destructive-
mutation defect. Confirmed by re-reading both tables' "deletion" entries during this pass — both
already state "never hard-deleted."

---

## IMP-004 Boundary (§41)

Unaffected — IMP-003 still emits audit events at the same points as before (this pass only fixed
role_permissions' OWN durable state; the "Audit Contract" section's emission-point list was not
changed, since it was not the source of the m01 finding). IMP-003 keeps enough canonical history
to remain correct/auditable/reconstructable on its own, per §41 — IMP-004 remains the eventual
sink for a broader audit/governance platform, not a dependency for IMP-003's own correctness.

---

## Financial Authority Separation (§42)

Reviewed the entire specification for hidden financial conflation after all above fixes were
applied. Searched for and confirmed absent:

```
super_admin bypass                    ABSENT (B01 fix removed the one case that existed)
admin all authority                    ABSENT (no permission grants blanket authority; Business
                                       Authority is always a separate AND-term)
GLOBAL scope implies financial authority  ABSENT (GLOBAL_PLATFORM is a Role/Permission scope value
                                       only; Financial Authority is a wholly separate Authority
                                       Assignment mechanism, never implied by any scope value)
permission implies financial approval  ABSENT (no `role_permissions` seed IMP-003 creates includes
                                       a permission named `finance.*.approve`; Permission (step 3)
                                       and Business Authority (step 6) are structurally separate
                                       AND-terms)
role implies financial posting          ABSENT (same structural separation)
```

---

## Q25 Super Admin Bridge (§43) / Bridge Concurrency (§44)

Rewritten per B01's disposition above. Idempotency/transactionality/auditability/non-public/
determinism are all restated explicitly in the corrected section, and the concurrency behavior
(lock the resolved identity's `principals` row first, re-validate guard (1b) under that lock, then
perform the single Role-assignment insert, then commit) is specified in both "Super Admin
Canonical Authorization" and "Concurrency." Repeat execution is guarded twice (Q25's own bootstrap-
row guard, and the Bridge's own "no existing `super_admin` Role assignment" guard) and neither
creates a duplicate assignment nor broadens authority on a second run — it is refused outright.

---

## Database Contract — Required Concrete Output (§45) / Principal Table (§46)

Every table now specifies purpose, PK, FK, unique constraints, indexes, state/revocation columns,
temporal fields, deletion semantics, history semantics, and (where applicable) its role in the
stable lock order — see the full "Database Contract" section, including the new `principals`
table specified per §46's exact required shape (principal_kind, human_user_id nullable/unique,
system/integration identifier columns, disabled_at for lifecycle, timestamps) with no password/
MFA/credential field of any kind — human authentication remains entirely IMP-002's.

---

## No User Pollution (§47)

Re-confirmed unchanged: no `role`, `role_id`, `permission`, `is_admin`, `is_super_admin`,
`business_authority`, or `financial_authority` column exists on `users` anywhere in this
specification — restated explicitly in "Database Contract > No changes to `users`".

---

## Concurrency Contract (§48) / Authorization vs Revocation (§49)

Fully rewritten "Concurrency" section: stable lock order (`principals` row -> validate grantor ->
validate references -> inspect active assignment -> create/revoke -> commit) applied uniformly to
every RBAC write path, including the Q25 Bridge; transactions and deterministic unique constraints
(the `active_assignment_key` generated column) replace the earlier ambiguous design; no MySQL
runtime concurrency is claimed as verified (unchanged, honest limitation, consistent with IMP-002's
own accepted precedent); database deadlocks are explicitly never converted into a business state.
Revocation-vs-cache: unchanged from the original draft (no cross-request cache by default; a
revocation is honored on the very next request otherwise) — this was already correct and needed no
fix.

---

## EDITORIAL — IMP003-READY-e01 — Actor Catalog

**UNCHANGED EDITORIAL.** Per the remediation instruction's own guidance (§50: "You may leave the
standalone Actor Catalog artifact absent if the authoritative actor semantics remain clear...
Do not let this remediation expand scope unnecessarily"), no separate `docs/06-domains/identity/`
artifact was created. The Actor Catalog remains embedded/extended in this specification's own
"Actor Materialization" section (now corrected per M05).

---

## Preserve Pass Areas (§51) — Regression Check

Re-verified unregressed after all fixes above: Authenticated requirement, Ownership separation,
Approval authority separation, Assurance, Security Restriction, Default DENY, Partner != tenant,
Fundraiser registration != business authority (strengthened, not merely preserved, by M05), API
principal INTERSECT token, no separate API RBAC system, no RBAC package requirement, shared
hosting, no application code written during this pass.

---

## Self-Readiness Review (§56)

Independently re-read the entire specification end-to-end after patching. Checked: Authority
(every restated principle still traces to a cited Level 1-3 document), Architecture (no new
architecture — the `principals` table and generated-column uniqueness are schema/engineering
detail, not architecture), Database (internally consistent — no remaining reference to the old
polymorphic principal_type or NOW()-dependent index anywhere, confirmed via full-document grep),
Security (threat model updated with the self-grant/self-escalation entry), Self-Escalation
(absolute, no exceptions, confirmed consistent across all 4 sections that mention it), Financial
Authority (structurally separate everywhere, confirmed via the §42 search above), Scope (matrix
complete, non-null scope_type everywhere), Principal Integrity (real FK, lifecycle fully
specified), Concurrency (single stable lock order, deterministic uniqueness), Test Contract
(every AND-term covered), Shared Hosting (unaffected, still PASS), IMP-002 Boundary (unaffected,
still consumed not redefined), Q25 (Bridge corrected, still non-public, still one-time).

---

## Human Decision

**NONE required.** Every fix in this pass either removes an unauthorized exception the original
draft incorrectly introduced (B01, M01) or corrects an internal engineering/database-integrity
defect (M02, M03, M04, m01) or a boundary the specification had itself already locked but failed
to apply consistently (M05) or completes test coverage already implied by locked AND-terms (M06).
None required inventing a new business rule, financial power, approval policy, registration
semantic, or actor.

---

## Definition of Ready (post-remediation)

```
BLOCKER:                 0
MAJOR:                   0
MINOR (gate-impact):     0
EDITORIAL:               1 (e01, explicitly accepted as unchanged per the remediation's own
                          instruction — does not block readiness)
Human Decision:           0
```

**PASS — specification is implementation-deterministic**, per §57's exit criteria.

## Recommendation

READY FOR CODEX TARGETED IMP-003 READINESS RE-AUDIT.
