# IMP-003 Readiness Remediation Pass 2

Task:
IMP-003 Targeted Specification Remediation Pass 2 (Codex Targeted Re-Audit Pass 1:
NEEDS CORRECTION — 4 MAJOR, 1 MINOR; 1 EDITORIAL unchanged)

## Review Base / Ending Commit

```
Review Base:    7297214423c7fd209dc4d90c1bf67e0556a0f6ea
Branch:         master (specification-only work continues on master, matching the established
                precedent for IMP-000/001/002 spec/readiness commits and Pass 1 of this stage)
```

Mode: SPECIFICATION REMEDIATION ONLY. No application code, migration, model, policy, gate, or
dependency was created or modified — confirmed via `git status --short` after this pass touches
exactly one file: `docs/implementation/IMP-003-rbac-scope-business-authority.md`.

All findings resolved in Remediation Pass 1 (`IMP003-READY-B01`, `M01`-`M06`, `m01`) remain
resolved — none was reopened; the sections touched in this pass (`principals`, the Role Model's
registration boundary, the Actor Materialization table, Invitation Intent Bridge, Scope Target
Integrity, Concurrency, Open Questions) are extended/corrected, not reverted.

---

## IMP003-REAUDIT-M01 — Human Principal Deletion Contract

**RESOLVED**

Root cause: the `principals` table's CHECK constraint required `human_user_id NOT NULL`
unconditionally for `principal_kind='human'`, while the same table's "Deletion protection" text
described `human_user_id` becoming null after User deletion (via `nullOnDelete`) — a direct,
unresolvable contradiction. Pass 1 introduced the real FK but did not reconcile it with the
"principal survives deletion" requirement.

Fix — a tombstone-compatible lifecycle, per the task's own required design:

```
principals gains a new column: tombstoned_at (nullable timestamp, meaningful only for
  principal_kind='human')

CHECK (four branches, all mutually exclusive and exhaustive):
  principal_kind='human' AND tombstoned_at IS NULL
    -> human_user_id NOT NULL, system_principal_id NULL, integration_principal_id NULL
  principal_kind='human' AND tombstoned_at IS NOT NULL
    -> human_user_id NULL, system_principal_id NULL, integration_principal_id NULL
  principal_kind='system'     -> human_user_id NULL, tombstoned_at NULL, system_principal_id NOT NULL
  principal_kind='integration' -> human_user_id NULL, tombstoned_at NULL, integration_principal_id NOT NULL
```

A live human principal (User exists) satisfies the first branch; once the User is deleted, the
principal transitions to the second branch — TOMBSTONED, not merely "orphaned." The transition
itself is specified as a single, MySQL-8-valid transaction:

```
1. SELECT ... FOR UPDATE the principals row (lock first).
2. Revoke every active principal_role_assignments/authority_assignments row referencing this
   principal_id (set revoked_at/revoked_by_user_id).
3. ONE UPDATE statement sets BOTH tombstoned_at = now() AND human_user_id = NULL together — the
   row satisfies the CHECK constraint's tombstoned branch the instant this single statement takes
   effect; no intermediate CHECK-violating state exists.
4. Commit, THEN proceed with the actual `DELETE FROM users` — since human_user_id was already
   nulled in step 3, the FK's nullOnDelete action is a no-op by the time the User row is deleted
   (no reliance on cascade-vs-application-code ordering).
```

`nullOnDelete()` is retained as a backstop only (in case a future deletion path bypasses the
tombstone transition), not as the primary mechanism. A tombstoned principal MUST NOT authenticate,
authorize, receive new Role/Authority assignments, or act as grantor — all four are stated as
explicit invariants and covered by new tests (see below). No automatic restoration exists; a future
authorized restoration workflow is explicitly deferred, not invented (per §8).

Sections changed: `principals` table definition (new `tombstoned_at` column, corrected CHECK),
"Principal Lifecycle" (full rewrite distinguishing DISABLED-while-User-exists from TOMBSTONED-
after-deletion, plus the transaction sequence above), "Concurrency" (tombstone transition added
to the per-write-path list), Test Contract (new "Tombstoned Principal" subsection), Database Test
Contract (principals CHECK/tombstone-transition tests).

Authority basis: pure internal consistency correction — no locked document is affected; the fix
makes an already-intended requirement (`principals` "never hard-delete... survives deletion")
actually satisfiable alongside the FK/CHECK design Pass 1 introduced.

---

## IMP003-REAUDIT-M02 — Permission Term Must Never Be Optional

**RESOLVED**

Root cause: Pass 1's "No Automatic Role at Registration" section (correctly closing M05) went
further than necessary and stated Donor/Fundraiser baseline capability was "gated ONLY by
Authenticated + Ownership... with the Permission... AND-term[] trivially satisfied because the
action does not require [it]" — this described an IMP-003-governed action with no Permission
requirement, contradicting RBAC-ARCHITECTURE.md's own AND-chain (Permission is not conditionally
required the way Business Authority/Resource State/Approval State legitimately are per-action —
every IMP-003-governed capability requires SOME resolvable Permission).

Fix: the section was rewritten to draw the boundary correctly. Donor/Fundraiser's baseline
operations (login, logout, password change/reset, MFA enrollment/challenge, email verification/
change) are not "IMP-003 actions with no permission requirement" — **they are IMP-002-owned
account-security operations that never pass through the IMP-003 evaluator at all**, exactly as
`docs/implementation/IMP-002-identity-authentication.md` already specifies and implements them.
IMP-003 has no jurisdiction over them. The invariant is now stated explicitly and unconditionally:
**Permission is never optional for any capability IMP-003's evaluator actually governs** — an
unresolvable/missing Permission is DENY, with no exception.

Sections changed: "No Automatic Role at Registration" (rewritten), "Actor Materialization" table
(Donor/Fundraiser cells reworded to match), Test Contract's "Permission" subsection (new
regression-check tests: an IMP-003-protected capability with an unresolvable Permission code
DENIES; a boundary test confirms IMP-002's account-security routes never invoke the IMP-003
evaluator at all, rather than "passing" it trivially).

Authority basis: `docs/05-rbac/RBAC-ARCHITECTURE.md`'s AND-chain — Permission is one of the nine
locked terms and is not described anywhere in Level 1-3 authority as conditionally waivable; this
fix removes an incorrect implication Pass 1 introduced while still correctly closing M05 (no
automatic Role at registration remains true and unchanged).

---

## IMP003-REAUDIT-M03 — Invitation Acceptance Must Not Grant Role

**RESOLVED**

Root cause: while the "Invitation Intent Bridge" section (Pass 1) correctly described a two-step
accept-then-authorize design, the "Actor Materialization" table's Partner Representative row
directly contradicted it: `"partner_representative role, assigned at invitation acceptance by the
inviting workflow"` — reading that row in isolation implies automatic role assignment at
acceptance time.

Fix:
- The Actor Materialization table was restructured with a new, explicit **"Authorization
  Transition"** column, separate from "Identity Creation," for every actor. Partner
  Representative, Internal Administrative Identity, and both Super Admin rows (first and
  subsequent) now explicitly state identity creation grants NOTHING, and that Role/Scope/Authority
  is granted ONLY by a later, separate, independently-audited transition.
- "Invitation Intent Bridge" was expanded with an explicit, unconditional list — acceptance grants
  NO Role, NO Permission, NO Scope, NO Business Authority, NO Financial Authority, NO Approval
  Authority, simultaneously — plus dedicated subsections for Partner Representative (§19 — up to
  three INDEPENDENT grants, never auto-inferred together), Internal Administrative Identity (§20),
  and Subsequent Super Admin (§21 — explicitly NOT reopening Q25's first-bootstrap semantics,
  which remain the Bridge's exclusive, one-time concern).
- New Test Contract subsection "Invitation Acceptance Authorization Boundary" directly tests that
  an accepted invitation's resulting identity holds NO Role/Permission/Scope/Authority immediately
  after acceptance, and that a subsequent, separate Authorization Transition grants exactly (and
  only) what it explicitly grants.

Sections changed: "Actor Materialization" (table restructured), "Invitation Intent Bridge"
(expanded with per-actor subsections), Test Contract (new subsection).

Authority basis: restores conformance to IMP-002's own already-locked boundary ("Acceptance
establishes an Identity/Auth subject... ONLY... must NOT automatically grant... role, permission,
data scope, business authority, or financial authority") and to Q22 — the fix removes an
inconsistency Pass 1's own table introduced, it does not create new registration policy.

---

## IMP003-REAUDIT-M04 — Polymorphic Scope Validation Must Be Race-Safe

**RESOLVED**

Root cause: "Scope Target Integrity" (Pass 1) validated target existence but explicitly allowed
"the domain's own re-validation immediately before commit where a native FK is not yet possible"
as a substitute for locking — precisely the "validate, then insert without a lock in between"
pattern the reaudit correctly identifies as insufficient (a concurrent deletion/deactivation could
still commit in the gap). Additionally, "Concurrency"'s stable lock order locked only the target
Principal's `principals` row and never the concrete scope target, and did not specify ordering
when two different principals (grantor and target) are both involved.

Fix — one reconciled, single lock order (no longer split across two sections):

```
1. Grantor Principal: lock principals row (SELECT ... FOR UPDATE)
2. Target Principal: lock principals row — if same as grantor, lock once; if different, lock
   BOTH in ascending principal_id (PK) order, regardless of which is "grantor"/"target"
   (deadlock-avoidance for two concurrent transactions naming the same two principals in
   opposite roles)
3. Concrete Scope Target (where the scope type requires one): resolve scope_type -> resolve the
   owning domain's concrete table -> SELECT ... FOR UPDATE that row -> verify existence + lifecycle
4. Existing Assignment / Active-Slot Parent: inspect active_assignment_key
5. Mutation: create/revoke
6. Commit
```

"Scope Target Integrity" was rewritten to require this same lock (via a new "Polymorphic Resolver
Contract" — every scope-type resolver must declare its lock strategy as one of its six required
elements) for assignment CREATION, and to require the owning domain's target delete/deactivate
path to ALSO lock the identical concrete row before proceeding — the two paths then serialize
against each other automatically (whichever acquires the lock first completes; the other observes
a consistent, non-racing state). Deletion protection is specified as a domain-chosen rule
(RESTRICT destructive deletion, or soft-deactivate-and-let-evaluation-DENY) applied under that same
lock — not a fire-and-forget pre-check either way.

New "Concurrency Test Contract" subsection added (conceptual, mirroring the Test Contract's
posture) covering: assignment-transaction-holds-lock blocks a concurrent target deletion attempt;
target-deletion-commits-first rejects a subsequent assignment attempt; assignment-commits-first
blocks/restricts a subsequent destructive deletion. Database Test Contract gained a corresponding
"scope target lock" entry.

Sections changed: "Scope Target Integrity" (rewritten — Polymorphic Resolver Contract added,
locking requirement replaces "revalidation... where a native FK is not yet possible"),
"Concurrency" (stable lock order rewritten to 6 steps including ascending-PK principal ordering
and the concrete target lock; per-write-path list updated; new Concurrency Test Contract
subsection), Test Contract (unaffected structurally — the concurrency tests live in their own new
subsection rather than duplicating into the main Test Contract), Database Test Contract (new scope
target lock entry).

Authority basis: pure engineering/concurrency-correctness fix — no locked document specifies lock
granularity; the fix satisfies `docs/03-database/DATABASE-ARCHITECTURE.md`'s "strong referential
integrity" and this repository's own established transactional-locking precedent (IMP-002's
EmailChangeRequest pattern), applied consistently rather than partially.

---

## IMP003-REAUDIT-m01 — Remove Stale Open Questions

**RESOLVED**

Root cause: "Open Questions" still listed "generated column vs. application-level locking
undecided" for active-assignment uniqueness, and "whether role_permissions revocation should
retain history," as open — both had ALREADY been decided and fully specified elsewhere in the same
document (by Remediation Pass 1, closing `IMP003-READY-M03`/`m01` respectively). Leaving them
listed as open/undecided was stale and directly contradicted the normative sections, creating a
real risk that an implementer would treat the already-closed design as still negotiable.

Fix: both stale bullets were removed outright (not merely reworded), with an explicit note that
neither is actually open — the `active_assignment_key` generated-column technique and durable
`role_permissions` history are each the SOLE normative design, not one option among alternatives.
The one remaining open item (exact Artisan command name for the Super Admin Bridge) is unaffected
— it is genuinely non-normative and does not reopen any decided design.

Section changed: "Open Questions" (two stale bullets removed, remaining bullet's non-reopening
status reaffirmed).

Authority basis: internal document-consistency correction; no locked document or prior decision is
altered — the fix removes an editorial hazard, it does not change the normative design itself
(which Pass 1 already fixed).

---

## EDITORIAL — IMP003-READY-e01 — Actor Catalog

**UNCHANGED EDITORIAL.** Consistent with Pass 1's disposition and this pass's own instruction (no
separate `docs/06-domains/identity/` artifact required) — the Actor Catalog remains embedded in
this specification's "Actor Materialization" section, now further corrected by M03 above.

---

## Database Contract Consistency Pass (`§35`)

Re-read every schema section after all fixes above. Confirmed ONE consistent answer for each of:

```
Human Principal deletion:    tombstone transition (M01) — single, unambiguous mechanism
principal lifecycle:          DISABLED (live User, IMP-002-owned state) vs. TOMBSTONED (User
                              deleted) are now distinct, non-conflicting concepts
FK behavior:                   human_user_id nullOnDelete is a backstop; the tombstone
                              transition is the primary, deterministic mechanism
scope target validation:      always paired with a row lock (M04) — no bare "validate" step
                              remains anywhere in the document
scope target locking:         one single lock order, defined once in "Concurrency," referenced
                              (not re-defined) everywhere else
active assignment uniqueness: `active_assignment_key` generated column (Pass 1's design,
                              unchanged and reaffirmed as normative by this pass's Open Questions
                              cleanup)
role-permission history:      durable granted_at/revoked_at (Pass 1's design, unchanged and
                              reaffirmed as normative)
```

No contradiction found between table definitions, CHECK constraints, lifecycle rules, the
Concurrency section, Open Questions, or the Test Contract, after a full-document grep for the
specific stale phrases the reaudit findings named (`principal_type` bare references, "trivially
satisfied," "assigned at invitation acceptance," "revalidation immediately before commit," an
unconditional "human_user_id NOT NULL") — all remaining matches are historical "root cause"
explanations of the DEFECT being fixed, not live contradictions (verified via grep, results
recorded during this pass).

## Authorization Formula / Invitation / Principal / Polymorphic-Scope Consistency Passes (`§36`-`§39`)

Performed as part of the searches above; no additional defect found beyond the four MAJOR findings
already disposed of.

---

## Preserve Previously Resolved (`§40`-`§45`) — Regression Check

Re-verified unregressed:

```
Self-Escalation:            still absolute, no exceptions (Self-Escalation Protection section
                            untouched by this pass; re-read in full, confirmed unchanged)
Q25 Bridge:                  still grants ONLY super_admin Role, no Authority Type, to anyone,
                            including itself (Super Admin Canonical Authorization untouched;
                            re-read, confirmed unchanged; Actor Materialization table's Super
                            Admin rows updated for clarity but state the identical rule)
Financial Separation:        still "Super Admin != automatic Financial Authority," still a
                            separate explicit Authority Assignment
MySQL Active Uniqueness:     still the Pass 1 generated-column design — reaffirmed, not reopened,
                            by this pass's Open Questions cleanup
Test Contract:               no prior test removed or weakened — only new subsections added
                            (Tombstoned Principal, Invitation Acceptance Authorization Boundary,
                            Concurrency Test Contract) and two existing subsections (Permission,
                            Partner) extended with additional entries
Shared Hosting:              unaffected — no new infrastructure dependency introduced by any fix
                            in this pass
```

---

## Human Decision

**NONE required.** Every fix in this pass is either an internal consistency correction (M01, m01),
a boundary clarification that removes an over-broad statement without changing any actual locked
policy (M02), a correction restoring conformance to an already-locked IMP-002 boundary (M03), or a
concurrency/locking-granularity engineering fix (M04). None invents a new actor, business rule,
financial power, approval policy, or registration policy, and none reopens a previously resolved
finding.

---

## Definition of Ready (post-remediation)

```
BLOCKER:                  0
MAJOR:                    0
MINOR (gate-impact):      0
EDITORIAL:                1 (e01, unchanged, non-blocking)
Human Decision:            0
```

**PASS — specification is implementation-deterministic**, per §51's exit criteria.

## Recommendation

READY FOR CODEX TARGETED IMP-003 READINESS RE-AUDIT PASS 2.
