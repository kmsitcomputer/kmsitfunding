# IMP-003 Readiness Remediation Pass 3

Task:
IMP-003 Targeted Specification Remediation Pass 3 (Codex Targeted Re-Audit Pass 2:
NEEDS CORRECTION — 4 MAJOR; 1 EDITORIAL unchanged) — DATABASE LIFECYCLE CONSISTENCY ONLY

## Review Base / Ending Commit

```
Review Base:    ee90027035815683d0f79725a4b594754a5e3e39
Branch:         master (specification-only work continues on master, matching established
                precedent for IMP-000/001/002 spec/readiness commits and Passes 1-2 of this stage)
```

Mode: SPECIFICATION REMEDIATION ONLY. No application code, migration, model, policy, gate, or
dependency was created or modified — confirmed via `git status --short` after this pass touches
exactly one file: `docs/implementation/IMP-003-rbac-scope-business-authority.md`.

All findings resolved in Pass 1 (`IMP003-READY-B01`, `M01`-`M06`, `m01`) and Pass 2
(`IMP003-REAUDIT-M01`-`M04`, `m01`) remain resolved — none was reopened. This pass is scoped
strictly to database lifecycle/FK consistency, per the task's own explicit instruction.

---

## IMP003-REAUDIT2-M01 — User Deletion Must Be Atomic

**RESOLVED**

Root cause: the "Human tombstone transition" (established in Pass 2 to fix `IMP003-REAUDIT-M01`)
committed the tombstone mutation (revoke assignments, set `tombstoned_at`, null `human_user_id`)
as ONE transaction, then stated "Commit this transaction, THEN proceed with the actual `DELETE
FROM users`" — a SEPARATE, later transaction. If that second transaction (the actual User
deletion) failed for any reason, the tombstone had already committed, leaving a permanently
inconsistent state: a tombstoned principal whose underlying User row still exists, with every one
of that principal's authorizations already revoked even though the User was never actually
deleted.

Fix — the "Canonical User Deletion Transaction" (`principals` section, "Principal Lifecycle")
merges both phases into ONE atomic transaction, per the task's own required 9-step structure,
adapted to this specification's actual schema names:

```
BEGIN TRANSACTION
  1. SELECT ... FOR UPDATE the `users` row (lock first).
  2. SELECT ... FOR UPDATE the linked `principals` row (lock second).
  3. Validate deletion preconditions (at minimum: not already tombstoned).
  4. Revoke every active principal_role_assignments row for this principal.
  5. Revoke every active authority_assignments row for this principal.
  6. ONE UPDATE sets BOTH tombstoned_at = now() AND human_user_id = NULL together (satisfies the
     CHECK constraint's tombstoned branch atomically — no intermediate violating state).
  7. DELETE FROM users WHERE id = ... (succeeds cleanly — see M02 below for why).
  8. COMMIT.
ROLLBACK the entire transaction on any failure at any step.
```

Failure behavior is stated explicitly: if step 7 fails, the WHOLE transaction rolls back — the
User remains live, the principal remains live (NOT tombstoned, `human_user_id` still populated),
and every assignment steps 4-5 would have revoked is restored to its prior state. No intermediate
commit exists anywhere in this sequence.

Sections changed: `principals` > "Principal Lifecycle" (the two-transaction "Human tombstone
transition" replaced by the single "Canonical User Deletion Transaction," with explicit failure
behavior); "Concurrency" (the corresponding bullet rewritten to describe the atomic sequence and
its relationship to the general lock order — see the reconciliation note below); Test Contract
(new "Canonical User Deletion" subsection with a mandatory rollback test); Database Test Contract
(new "canonical deletion transaction" and "canonical deletion rollback" entries).

Lock order reconciliation (`§7`): this lifecycle path locks `users` then the linked `principals`
row — a specialization of the general Grantor Principal -> Target Principal order (Concurrency),
not a competing rule: there is only ONE principal involved (no separate grantor), so there is
nothing to order by ascending principal_id against; the `users` row is locked first simply because
it is the row whose deletion triggers the whole transaction. This is stated explicitly in
"Concurrency" so no contradictory lock order is left implied.

Authority basis: pure engineering/transactional-integrity correction — no locked document is
affected; this satisfies `docs/03-database/DATABASE-ARCHITECTURE.md`'s "strong referential
integrity" and general transactional-consistency expectations already established by IMP-002's own
precedent (fail-closed, atomic security transitions).

---

## IMP003-REAUDIT2-M02 — Remove Invalid `nullOnDelete` Backstop Claim

**RESOLVED**

Root cause: the `principals.human_user_id` FK was declared `nullOnDelete`, with "Deletion
protection" describing this as a BACKSTOP in case the tombstone transition were ever bypassed.
This claim was invalid on its own terms: an FK-triggered `SET NULL` can only touch the
`human_user_id` column — it cannot also set `tombstoned_at` in the same automatic action — so a
bypass deletion relying on that cascade would leave the row violating its own CHECK constraint
(human_user_id null while tombstoned_at is still null), not produce a valid tombstone. The
database cannot silently do what the earlier draft claimed it could.

Fix: `human_user_id -> users.id` now uses **RESTRICT (NO ACTION)**, not `nullOnDelete`. A new
"Human User FK Strategy" subsection states the resulting, honest invariant: a direct `DELETE FROM
users` for a User whose linked `principals` row is still LIVE is REJECTED by the database itself
(an ordinary FK violation) — the Canonical User Deletion Transaction (M01) is the ONLY path that
can ever remove the link, because it is the only path that nulls `human_user_id` itself, inside
the same transaction, BEFORE the `DELETE` runs (at which point the FK has nothing left to
restrict, since no row currently references that User's id any more). This is a deliberate,
explicit "bypass deletion -> database REJECT" invariant, not a claim about automatic cascade
behavior the database cannot actually provide.

Sections changed: `principals` table definition (FK action changed), new "Human User FK Strategy"
subsection, Test Contract (new "direct User deletion blocked" test), Database Test Contract (new
"direct User deletion blocked" entry).

Authority basis: pure engineering correction — no locked document specifies FK delete-action
semantics; RESTRICT is the standard, unsurprising MySQL/InnoDB choice for "this reference must be
explicitly cleared by the owning transaction before the referenced row can be removed," which is
exactly the invariant required here.

---

## IMP003-REAUDIT2-M03 — User Attribution FKs Must Not Block Deletion

**RESOLVED**

Root cause: `role_permissions.granted_by_user_id`/`revoked_by_user_id`,
`principal_role_assignments.assigned_by_user_id`/`revoked_by_user_id`, and
`authority_assignments.assigned_by_user_id`/`revoked_by_user_id` all referenced `users.id`
directly. Since a User can now be deleted (via the Canonical User Deletion Transaction), this
would have orphaned the historical record of WHO granted/revoked an authorization the moment the
acting administrator's own account was later deleted — directly conflicting with the durable-
history requirement Pass 1 already established (`IMP003-READY-m01`).

Fix: every actor-attribution column across all three IMP-003 authorization tables now references
`principals.id`, never `users.id` directly:

```
role_permissions.granted_by_principal_id / revoked_by_principal_id       -> principals.id
principal_role_assignments.assigned_by_principal_id / revoked_by_principal_id -> principals.id
authority_assignments.assigned_by_principal_id / revoked_by_principal_id  -> principals.id
```

A new "Attribution" subsection (Database Contract) states the general rule and why it works: a
canonical Principal OUTLIVES its underlying human User (it becomes TOMBSTONED, never hard-
deleted), so a grant/revocation performed by an administrator who is later deleted remains
attributed to a real, permanent, resolvable row. This also uniformly supports Human, System, and
Integration grantors without three separate nullable attribution-column sets. Nullability is
retained ONLY in the two specifically-documented cases where no acting Principal exists at all —
the Permission Registry's system-seeded `role_permissions` grants (a deployment action, not an
RBAC action taken by any Principal), and the Q25 Bridge's `super_admin` Role assignment (a
one-time, non-principal-initiated, deterministic bootstrap event, explained in a new "Q25
Bootstrap Attribution" subsection that explicitly reaffirms this is NOT a self-escalation
exception — the Bridge is not "Super Admin granting itself a role through normal RBAC"; Q25's own
bootstrap-identification semantics are not reopened). No other IMP-003 table's attribution was
touched beyond nullability review, and no business-domain table outside IMP-003 was touched at
all, per the task's own scope limit.

Sections changed: "Business Authority > Authority Assignment" (conceptual field list),
`role_permissions`, `principal_role_assignments`, `authority_assignments` (all three tables' field
lists and FK lists), new "Attribution" subsection (with "Q25 Bootstrap Attribution" sub-
subsection), "Temporal Authorization" (revoked-by column name), Test Contract (new "Attribution"
subsection), Database Test Contract (attribution column names updated throughout).

Authority basis: pure engineering/database-integrity correction restoring conformance to Pass 1's
own already-locked durable-history requirement; no new actor, role, permission, or business rule is
introduced.

---

## IMP003-REAUDIT2-M04 — Migration Order Must Respect FKs

**RESOLVED**

Root cause: the "Implementation Order" section's step 1 listed `principals` BEFORE
`system_principals`/`integration_principals`, while `principals.system_principal_id`/
`integration_principal_id` are FKs INTO those two catalogs — the referenced tables were ordered
after the table that references them, which a real migration run against MySQL would reject
outright.

Fix: a new, dedicated **"Migration Order"** section (distinct from "Implementation Order," per the
task's own §29 instruction to separate migration order from coding order) specifies the exact,
FK-valid sequence:

```
0. users (prerequisite, already exists from IMP-002)
1. roles          2. permissions          3. authority_types
4. system_principals      5. integration_principals
6. principals (FK -> users, system_principals, integration_principals — all three now exist)
7. role_permissions (FK -> roles, permissions, principals)
8. principal_role_assignments (FK -> principals, roles)
9. authority_assignments (FK -> principals, authority_types)
```

A dependency graph accompanies the ordered list, showing `principals` as the single common
dependency every assignment table needs (both as the `principal_id` target and, per the M03 fix
above, as the `..._by_principal_id` attribution target) — it must be created immediately after its
own three prerequisites and strictly before every table that attributes an action to a Principal.
A new "Fresh-Database Migration Contract" states that running this exact sequence against a clean
MySQL 8 database is itself a mandatory test (see Test Contract/Database Test Contract), since that
is what actually catches an ordering mistake before a real deployment does.

"Implementation Order" (the coding sequence: schema, models, services, policies, tests) now
explicitly says step 1 = "apply the Migration Order above in full," making clear the two orders
are related but distinct, and no longer duplicates (or contradicts) the FK sequence itself.

Sections changed: new "Migration Order" section (with dependency graph and Fresh-Database
Migration Contract), "Implementation Order" (step 1 now references Migration Order instead of
re-listing tables), Database Test Contract (new "fresh migration" entry).

Authority basis: pure engineering correction — no locked document specifies migration ordering;
the fix simply makes the already-intended schema buildable.

---

## Full Consistency Passes (`§30`-`§32`)

Performed full-document greps for every stale phrase named by the task, after all four fixes
above:

```
nullOnDelete:                 only 2 matches remain, both explicitly stating it is REMOVED/
                              replaced by RESTRICT (no live nullOnDelete claim remains)
_by_user_id (attribution):    only 3 matches remain, all inside the M03 "root cause" paragraph
                              describing the DEFECT being fixed (past tense, not a live claim)
"Commit this transaction,
  THEN proceed with...":       0 matches — the two-transaction sequence no longer exists anywhere
"as a BACKSTOP":               0 matches — the invalid backstop claim was removed, not reworded
```

No contradiction found between the `principals` table definition, its CHECK constraint, the
Principal Lifecycle text, the Concurrency section, and the Test Contract — all describe the same
single atomic transaction and the same RESTRICT-based FK strategy.

---

## Preserve Previously Resolved (`§36`-`§45`) — Regression Check

Re-verified unregressed by direct re-reading of each section:

```
Permission Chain:            "Permission (§24, mandatory per IMP003-REAUDIT-M02)" section
                             untouched by this pass — still mandatory, no optional path
Invitation Boundary:          "Invitation Intent Bridge" and Actor Materialization table
                             untouched by this pass — acceptance still grants nothing
Self-Escalation:               "Self-Escalation Protection" section untouched by this pass — still
                             absolute, no exceptions
Q25 Bridge:                    "Super Admin Canonical Authorization" untouched in its own grant
                             logic by this pass (only its principals-row-creation wording was
                             already consistent with M04's new principals design) — still grants
                             ONLY the super_admin Role, still no Authority Type, ever
Financial Authority
  Separation:                  unaffected — "Super Admin != automatic Financial Authority"
                             restated, unchanged
Polymorphic Scope Integrity:   "Scope Target Integrity" and its Concurrency lock order (Pass 2's
                             fix) untouched by this pass
Active Assignment Uniqueness:  "Scope Uniqueness Normalization + MySQL 8 Active-Assignment
                             Uniqueness" untouched — still the generated-column design, still no
                             NOW()-dependence
History Model:                 role_permissions/principal_role_assignments/authority_assignments
                             all still durable (revoked_at, never hard-deleted) — only the
                             attribution COLUMN TYPE changed (user -> principal), not the
                             history-preservation behavior itself
Shared Hosting:                 unaffected — no new infrastructure dependency introduced
```

---

## Human Decision

**NONE required.** Every fix in this pass is a database lifecycle/FK consistency correction:
atomic transaction boundaries (M01), FK delete-action semantics (M02), attribution column target
(M03), and migration creation order (M04). None invents a new actor, role, permission, financial
authority rule, approval policy, registration policy, or Human Decision Register entry.

---

## Definition of Ready (post-remediation)

```
BLOCKER:                  0
MAJOR:                    0
GATE-IMPACT MINOR:        0
EDITORIAL:                1 (e01, unchanged, non-blocking, per prior passes' disposition)
Human Decision:            0
```

**PASS — database lifecycle is executable without implementation-time invention.**

## Recommendation

READY FOR CODEX TARGETED IMP-003 READINESS RE-AUDIT PASS 3.
