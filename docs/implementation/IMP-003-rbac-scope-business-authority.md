# IMP-003 — RBAC + Scope + Business Authority

## Status

READY (readiness/specification only — implementation NOT authorized by this document)

## Authority

```
Level 1  Human Decision Register (Q1-Q25) — docs/01-requirements/HUMAN-DECISION-REGISTER.md
Level 2  Master Requirements — docs/01-requirements/MASTER-REQUIREMENTS.md
Level 3  Locked Architecture Baseline:
         - docs/02-architecture/MASTER-ARCHITECTURE.md
         - docs/02-architecture/MODULE-OWNERSHIP.md
         - docs/03-database/DATABASE-ARCHITECTURE.md / DATABASE-INVARIANTS.md
         - docs/04-security/SECURITY-ARCHITECTURE.md / SECURITY-INVARIANTS.md
         - docs/05-rbac/RBAC-ARCHITECTURE.md
         - docs/05-rbac/DATA-SCOPE-MODEL.md
         - docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md
         - docs/05-rbac/AUTHENTICATION-ASSURANCE.md
Level 5  docs/implementation/IMP-002-identity-authentication.md (approved, FINAL — see
         docs/audits/IMP-002-FINALIZATION.md) — IMP-003 builds on top of this, does not
         reopen it.
Level 6  AGENTS.md, docs/00-governance/*
```

This document translates the already-locked authorization architecture (Level 3, materialized
verbatim in `docs/05-rbac/`) into an implementation-ready contract for IMP-003. It does not create
new architecture, business rules, or Human Decisions. Every design choice not directly dictated by
locked authority is explicitly labeled `ENGINEERING CHOICE` with its justification, per
`docs/00-governance/CHANGE-CONTROL.md` "Dependency Governance" and this task's own instruction
(§12/§44).

## Objective

Establish the canonical RBAC + Data Scope + Business Authority evaluation and storage model —
Role, Permission, Scope, Ownership, Business Authority, Financial Authority foundation, and
Approval Authority primitive — as an executable Laravel implementation contract, consuming IMP-002
(Identity, Authentication Assurance, Security Restriction) without rewriting it, and providing the
extensible foundation that later domain stages (Fundraiser, Partner, Beneficiary, Finance,
Approval, etc.) populate with their own concrete permissions, scopes, and authority types.

## In Scope

```
Canonical authorization evaluation (the AND-chain, docs/05-rbac/RBAC-ARCHITECTURE.md)
Role model + storage
Permission model + registry + storage
Role -> Permission assignment
Principal -> Role assignment (with scope)
Data Scope model + domain-aware scope resolution (docs/05-rbac/DATA-SCOPE-MODEL.md)
Ownership resolution contract (domain-aware)
Business Authority Type registry + Authority Assignment (docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md)
Financial Authority foundation (as a specialization of Business Authority — no domain workflow)
Approval Authority evaluation primitive (no Approval module implementation)
Authentication Assurance integration (consumes IMP-002, does not redefine it)
Security Restriction integration (consumes IMP-002, does not redefine it)
Default-DENY authorization evaluation service
Policy/Gate/query-scope integration pattern
System Principal / Integration Principal foundation
Q25 Super Admin canonical-authorization bridge
Self-escalation protection rules
Audit event contracts (emission points only — sink is IMP-004)
Security threat model for this authorization layer
Test contract
Database contract (specification only — no migrations created by this document)
```

## Out of Scope

```
Roles/permissions/scopes/authority for domains that do not exist yet in this repository
  (Fundraiser business approval workflow, Partner verification workflow, Beneficiary
  eligibility/verification workflow, Financial/Refund/Withdrawal/Distribution approval matrices,
  Commission, Ledger, Payment, Campaign, Donation, etc.) — IMP-003 provides the MECHANISM those
  stages populate; it does not populate it with their business rules.
Any migration, model, policy, gate, middleware, seeder, or authorization service code
  (this is a specification-only stage; see "Do Not Implement" governance for this task).
Rewriting or reopening any part of IMP-002 (identity, authentication, MFA, assurance,
  lifecycle/restriction) — IMP-003 consumes it as-is.
Multi-tenancy of any kind (single organization — Q1; Partner != tenant).
API token issuance/management implementation (only the authority-intersection CONTRACT is
  specified; token issuance belongs to whichever later stage implements /api/v1 authentication).
IMP-004 Audit + Governance Foundation implementation (only emission-point contracts are defined
  here, per this task's own instruction).
Configuration values, rates, thresholds, approval routing rules for any business domain (none of
  these are supplied by any Level 1-3 document — inventing them here would violate
  MASTER-REQUIREMENTS.md §6).
```

## Locked Invariants (restated, not reinterpreted)

```
Authorization = Authenticated AND Permission AND Applicable Data Scope AND Ownership/Subject
  Access Rule AND Required Business Authority AND Valid Resource State AND Required Approval
  State AND Required Authentication Assurance AND No Security Restriction.
Default is DENY.
Role != Permission; Permission != Scope; Permission != Authority; Authority != Approval Decision;
  Object Access != Full Field Disclosure; Super Admin != automatic Financial Authority.
Permission alone is insufficient. Permission + applicable scope are required. Scope containment
  is domain-aware. Contexts from unrelated assignments MUST NOT be unioned automatically.
An Authority Assignment binds an Authority Type to a Principal, within a Scope, for a bounded
  effective period, with an explicit status — never implied by role membership.
Assurance is STANDARD/ELEVATED, session-scoped, never a User (or Role/Permission) column.
Single Organization; Partner != tenant; Partner != settlement owner.
A System Principal action and an Integration Principal action are never conflated. A token's
  effective authority is never wider than the intersection of the token grant and its principal's
  own authority. Duties requiring separation are never collapsed without an approved exception.
```

---

## Actor Materialization

**Authoritative Actor Catalog Found: YES** — already fully materialized in
`docs/implementation/IMP-002-identity-authentication.md` §"Actors" (the table at lines 196-203),
itself sourced from Q22 (actor list + registration model), Q7 (Beneficiary), and
`docs/05-rbac/DATA-SCOPE-MODEL.md` "Ownership Examples". IMP-003 extends that same table with the
authorization-specific columns this stage owns, inventing no new actor:

`IMP003-REAUDIT-M03`: the table below now separates **Identity Creation** from **Authorization
Transition** as two distinct columns for every invitation/provisioning-based actor — Identity
Creation (IMP-002's `Invitation::accept()` or the Q25 bootstrap) NEVER itself performs the
Authorization Transition; the transition is always a SEPARATE, later, independently-audited
action performed by a principal who already holds the relevant `rbac.*` grant authority (see
"Invitation Intent Bridge" for the full mechanism this column summarizes).

| Actor | Identity Creation (IMP-002) | Authorization Transition | Portal | Role (post-transition) | Default Scope | Business Authority Boundary | Financial Authority Boundary | Assurance Notes |
|---|---|---|---|---|---|---|---|---|
| Donor | Self-registration (Q22) | None — no IMP-003-governed capability is granted by registration; see "No Automatic Role at Registration" | `/donor/*` | **NO Role** — baseline account-security operations (login, password, MFA, email verification/change) are entirely IMP-002-owned, never evaluated by IMP-003's AND-chain; any FUTURE IMP-003-governed Donor capability always requires an explicit Permission (never trivially satisfied) | `OWN` | None by default | None | STANDARD for ordinary use; ELEVATED for account-security actions (IMP-002 owns those triggers) |
| Fundraiser | Self-registration (Q22); no automatic business authority | None at registration — a LATER, separate, explicitly-authorized transition (owned by the Fundraising & Attribution domain stage) is required before any fundraiser-operational Role/Permission/Authority exists | `/fundraiser/*` | **NO Role** at registration (same rule as Donor) | `OWN` until an Authority Assignment grants `FUNDRAISER`/`CAMPAIGN` scope by a later stage | **NOT granted by registration** — an approved-Fundraiser Authority Type (owned by the Fundraising & Attribution domain stage, registered into IMP-003's extensible Authority Type registry — see "Business Authority > Extensibility") is required before any fundraiser-operational capability | None by default | Same as Donor |
| Partner Representative | Invitation acceptance creates the identity ONLY (Q22) — grants NO role, permission, scope, or business authority (see "Invitation Intent Bridge") | A SEPARATE, later, independently-audited Role assignment, performed by whoever already holds `rbac.role.assign` for `partner_representative` (e.g. an authorized Partner-module workflow) — never automatic upon acceptance | `/partner/*` | `partner_representative` Role, ONLY once the Authorization Transition above has actually run | `PARTNER` scope, bound to the specific Partner — established BY the same Authorization Transition, never inferred from invitation intent alone | **NOT implied by role alone** — requires an active `PARTNER` scope assignment; Partner Verification authority (`partner_verifier`) is separate and not implied | None by default | STANDARD baseline; ELEVATED for partner-configuration-sensitive actions per later Partner-domain policy |
| Internal Administrative Identity | Invitation/provisioning creates the identity ONLY (Q22) — grants nothing | A SEPARATE, later, independently-audited Role assignment by an authorized Super Admin/authorized administrator — never self-assigned, never automatic upon acceptance | `/admin/*` | one or more internal operational roles (e.g. `finance_operator`, `beneficiary_operator`, `support_operator` — exact set is a later, domain-populated catalog, not invented here), ONLY once assigned | `ORGANIZATION` or `ASSIGNED_WORK`, per assignment | None implied by the identity itself — each operational role's permissions are scoped narrowly per its own function | None implied — `financial_approver`/`refund_approver`/etc. are separate Authority Assignments | ELEVATED required for role/authority assignment actions (see "Self-Escalation Protection") |
| Super Admin (first) | Q25 CLI bootstrap creates the identity ONLY — grants nothing (see Q25 in IMP-002) | The Q25 Bridge — a distinct, one-time, non-principal-initiated mechanism (NOT an ordinary "Authorization Transition" performed by another principal; see "Super Admin Canonical Authorization") | `/admin/*` | `super_admin` Role, ONLY once the Bridge has run | `GLOBAL_PLATFORM` | Broad platform administrative capability by role/permission — but see next column | **NEVER automatic**, including via the Bridge — `financial_approver` etc. remain separate Authority Assignments granted by a DISTINCT Principal (RBAC-ARCHITECTURE.md: "Super Admin != automatic Financial Authority") | ELEVATED required for canonical role/authority assignment and any security-sensitive configuration change |
| Super Admin (subsequent) | N/A — not a first bootstrap | An ORDINARY Role assignment by an EXISTING Super Admin (a distinct, already-authorized Principal) — never the Q25 Bridge, never self-assigned (§21 — Q25 first-bootstrap semantics are not reopened for this case) | `/admin/*` | `super_admin` Role, ONLY once assigned | `GLOBAL_PLATFORM` | Same as first Super Admin | Same as first Super Admin — never automatic | Same as first Super Admin |
| Beneficiary | Governed by Q7 (mechanics deferred to Beneficiary & Distribution domain stage); IMP-002 does not create Beneficiary-specific identity mechanics beyond its general-purpose registration/invitation/provisioning primitives | Deferred to the Beneficiary & Distribution domain stage, following the same "identity creation never implies authorization" rule as every other actor | Not yet routed | `beneficiary` Role if/when a `User` is created for this actor (per Q7), ONLY once that stage's own transition assigns it | `OWN` / `BENEFICIARY_CASE` | Verification/eligibility authority (`beneficiary_verifier`) is separate, assigned only to authorized Operational Staff, never to the Beneficiary itself | None | Same general model as Donor; sensitive beneficiary data additionally requires explicit permission + scope + applicable business authority (MASTER-REQUIREMENTS.md §10) |
| System Principal | Not a `User` — a fixed, seeded, non-authenticatable Principal (SECURITY-ARCHITECTURE.md) | Seeded directly (no "acceptance" step exists for a non-human Principal) | N/A (no portal; invoked only by scheduled/queued jobs) | Fixed, narrowly-scoped system roles per job family — see "System Principal" | `GLOBAL_PLATFORM` scoped narrowly per capability, never unrestricted | None beyond what the specific job explicitly requires | None by default; any financial-consequence job must still go through the same Authorized Financial Consequence chain (`docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md`) with an explicit Authority Assignment for that job identity, not an implicit bypass | N/A — assurance is a human-session concept; System Principal actions are audited by principal identity instead |
| Integration Principal | Not a `User` — a fixed, seeded, non-authenticatable Principal (SECURITY-ARCHITECTURE.md), one per external integration (e.g. a specific payment provider webhook identity) | Seeded directly (same as System Principal) | N/A | Fixed, narrowly-scoped per integration | Narrow, per-integration scope only | None beyond the specific integration's declared capability | None — no Integration Principal may post directly to Ledger (`docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md` "Never... provider callback... write directly to Ledger") | N/A |

**Materialized Without New Semantics: YES.** **New Actor Invented: NO.**

---

## Canonical Authorization Formula (implementation form)

Restated from `docs/05-rbac/RBAC-ARCHITECTURE.md` as the exact evaluation sequence IMP-003's
authorization service executes, short-circuiting DENY at the first failing term. `IMP003-READY-M06`
adds an explicit precondition step reconciling `docs/05-rbac/DATA-SCOPE-MODEL.md`'s "Canonical
Authorization Context" (`Principal + Requested Capability + Target Resource + Applicable Subject
Context`) with RBAC-ARCHITECTURE.md's 9-term AND-chain: that context must be SUCCESSFULLY RESOLVED
before any AND-term is evaluated — an unresolved Principal, unrecognized Requested Capability, or
missing/unresolvable Target Resource denies immediately, before Permission/Scope/Ownership are ever
consulted (this is not an additional AND-term beyond RBAC-ARCHITECTURE.md's locked 9; it is the
context-resolution precondition DATA-SCOPE-MODEL.md already requires those terms to be fed from):

```
0. Applicable Subject Context Resolution  (Principal resolved to an active `principals` row —
                                            see "Principal Model" — Requested Capability recognized
                                            as a registered Permission code, Target Resource
                                            resolved to a concrete, existing domain record; ANY
                                            failure here -> DENY before step 1)
1. Authenticated                         (IMP-002: Auth::check() / identity.active middleware)
2. No Security Restriction                (IMP-002: User::canAuthenticate() — DISABLED/SUSPENDED
                                            denies immediately; re-checked here, not re-implemented)
3. Permission                             (IMP-003: principal holds the required Permission via
                                            an active Role assignment)
4. Applicable Data Scope                  (IMP-003: the Permission's grant is scoped, and the
                                            target resource falls within an active scope
                                            assignment covering it)
5. Ownership / Subject Access Rule         (IMP-003: domain-aware ownership resolver — see
                                            "Ownership Model")
6. Required Business Authority             (IMP-003: an active, applicable Authority Assignment
                                            exists when the action requires one)
7. Valid Resource State                    (owning domain module: IMP-003 evaluates a resource-
                                            state predicate the owning domain supplies; IMP-003
                                            does not define domain resource states itself)
8. Required Approval State                 (owning domain module via the Approval Authority
                                            primitive — see "Approval Authority")
9. Required Authentication Assurance       (IMP-002 AssuranceService::isElevated(), consumed
                                            not redefined)
```

Steps 7 and 8 are evaluated via predicates/contracts the OWNING domain module supplies (Campaign,
Donation, Approval, etc. do not exist yet) — IMP-003 defines the contract shape only, per "Out of
Scope."

---

## Role Model

**Global, not organization-scoped, not per-tenant.** Per Q1 (Single Organization) and
`MASTER-ARCHITECTURE.md` ("Partner != tenant"), there is exactly one organization; a `roles` table
is a single flat catalog, never duplicated per-organization or per-partner.

A Role is a named, reusable BUNDLE of Permissions (e.g. `super_admin`, `finance_operator`,
`partner_representative`). A Role itself carries no scope — scope is bound
at ASSIGNMENT time (a `partner_representative` Role assigned to Principal X is additionally bound
to a specific Partner via the assignment's scope columns, not via a separate
`partner_representative_for_partner_7` Role). This directly implements DATA-SCOPE-MODEL's
"Scope containment is domain-aware" without multiplying Role rows per scope target.

`ENGINEERING CHOICE`: Roles are DB-backed (not a code-only enum), because Role -> Permission
assignment must itself be auditable, revocable, and independently assignable per
`docs/00-governance/DEFINITION-OF-DONE.md`'s RBAC test contract — a hardcoded enum cannot express
"who assigned this permission to this role, and when." Justification: Laravel provides no built-in
DB-backed role storage; this is unavoidable custom modeling, not a framework capability being
bypassed (see "Package Decision").

System Roles (`super_admin` and any other role IMP-003 itself seeds) are marked `is_system = true`
and cannot be deleted or renamed through ordinary role-management actions — only through an
explicitly authorized migration/ADR-governed change, consistent with `CHANGE-CONTROL.md`.

### No Automatic Role at Registration (`IMP003-READY-M05`)

Root cause of the finding: the original draft auto-assigned a `donor`/`fundraiser` Role at
IMP-002 registration time, purely to give those identities SOME Role row — this violated
`Identity Creation != Role Assignment` (already stated in IMP-002's own specification and restated
in "Invitation Intent Bridge" below) by making registration ITSELF an implicit authorization grant.

**Corrected rule: IMP-003 does not seed, and no workflow automatically assigns, a `donor` or
`fundraiser` Role at registration, or ever, as a side effect of IMP-002 identity creation.**

`IMP003-REAUDIT-M02`: the earlier draft additionally implied that a Donor/Fundraiser's baseline
capability was "an IMP-003 action with its Permission term trivially satisfied" — this is
corrected. **Permission is NEVER optional or trivially satisfied for any capability that IMP-003's
authorization evaluator actually governs — every such capability requires a resolvable Permission,
full stop; there is no "Authenticated + Ownership is enough" pathway through the IMP-003
AND-chain.** The baseline operations available to a freshly-registered Donor/Fundraiser
(logging in and out, verifying email, changing/resetting password, enrolling in and challenging
MFA, requesting/confirming an email change) are not "IMP-003 capabilities with no Permission
requirement" at all — **they are entirely IMP-002-owned account-security operations**, already
specified and implemented by `docs/implementation/IMP-002-identity-authentication.md`, governed
by IMP-002's own rules (current-password confirmation, ELEVATED assurance, session ownership),
and they never pass through IMP-003's Role/Permission/Scope/Authority evaluator at all — IMP-003
simply has no jurisdiction over them (see "In Scope"/"Out of Scope": IMP-003 builds authorization
ON TOP of IMP-002, it does not re-govern IMP-002's own account-security surface).

If/when a later domain stage introduces a genuine Donor- or Fundraiser-facing capability that IS
governed by IMP-003 (e.g. `donation.create` once the Donation domain exists), that capability
ALWAYS requires a resolvable Permission — an unknown or missing Permission is DENY, exactly like
every other IMP-003-protected action (see "Default Deny" and "Test Contract"). That stage defines
the Role/Permission and its OWN explicit assignment mechanism; IMP-003 does not pre-grant it at
registration, and does not describe any IMP-003-governed action as needing "no Permission."

---

## Permission Model

**Naming convention** (`ENGINEERING CHOICE`, tracing to `MODULE-OWNERSHIP.md`'s module catalog,
not invented independently): `<domain>.<capability>`, lowercase, snake_case within segments,
dot-separated domain prefix matching the owning module (e.g. `identity`, `campaign`, `donation`,
`fundraiser`, `commission`, `partner`, `payment`, `finance`, `ledger`, `reconciliation`, `approval`,
`beneficiary`, `distribution`, `document`, `communication`, `governance`, `rbac`). Examples (none
of these are business rules — they are capability NAMES, not thresholds/rates/workflows):

```
rbac.role.assign
rbac.role.revoke
rbac.permission.assign
rbac.authority.assign
rbac.authority.revoke
identity.security.transition        (ACTIVE<->DISABLED, NONE<->SUSPENDED — IMP-002 defers
                                      "who" to IMP-003; this is that permission)
campaign.view / campaign.create / campaign.update / campaign.publish
donation.view
payment.review
partner.manage
beneficiary.view_sensitive
finance.refund.review
```

Only the `rbac.*` and `identity.security.transition` permissions are actually SEEDED by IMP-003
itself (they are the permissions IMP-003's own capabilities require). Every domain-specific
permission shown above is illustrative of the NAMING CONVENTION a later domain stage will register
under — IMP-003 does not seed permissions for domains it does not own (Out of Scope).

### Permission Registry (`ENGINEERING CHOICE`, per §12)

**Hybrid: code-defined canonical registry, DB-backed storage.** Laravel ships no DB-backed
permission model — Policies/Gates are code, not rows. Locked architecture requires FK-relational
Role -> Permission assignment, audit, and historical reproducibility
(`docs/03-database/DATABASE-INVARIANTS.md`), which a pure code-enum cannot provide. Design:

```
A PHP-side "Permission Registry" class (per owning module, e.g. RbacPermissions,
  IdentityPermissions) is the CANONICAL SOURCE — a plain array of {code, description} constants,
  reviewed/versioned in git like any other code.
A seeder (idempotent, upsert-by-code) synchronizes the registry into the `permissions` table.
The `permissions` table is what `role_permissions` foreign-keys against — this is what makes
  assignment/audit/history possible.
A permission is never hard-deleted once it has ever been assigned (preserves history) — retiring
  a permission sets `deprecated_at`, which the assignment UI/validation refuses for NEW
  assignments but does not retroactively revoke existing ones (that is a deliberate revocation
  action, audited separately).
```

This is the "least-complex implementation-compatible design" available given no locked document
mandates a specific storage mechanism (§12) — a pure code-enum was rejected because it cannot
satisfy the audit/history/FK requirements above; a fully dynamic admin-UI-authored permission
system was rejected as over-engineering not justified by any locked requirement.

---

## Role -> Permission Assignment

```
role_permissions (role_id, permission_id) — many-to-many, unique(role_id, permission_id).
```

```
Who can assign/revoke:   a Principal holding `rbac.permission.assign` (or `rbac.permission.revoke`)
                         AND ELEVATED assurance (this is itself a security-sensitive configuration
                         change per AUTHENTICATION-ASSURANCE.md).
Audit:                   every assignment/revocation emits an audit event (see "Audit Contract").
Transaction:             assignment/revocation is a single DB transaction (insert/delete +
                         audit-event emission), matching the transactional pattern IMP-002
                         established for its own security-sensitive writes.
Self-escalation:         see "Self-Escalation Protection" — a Principal may never assign to a
                         Role a Permission that expands that Principal's OWN effective authority
                         beyond what they already hold, unless they hold `rbac.permission.assign`
                         AND the specific target permission is one they themselves already
                         effectively have (directly or via an assigned Role) — see full rule below.
```

No business approval authority is invented for WHO holds `rbac.permission.assign` initially —
that is a provisioning decision (see "Super Admin Canonical Authorization").

---

## Data Scope Model

Reuses `docs/05-rbac/DATA-SCOPE-MODEL.md`'s taxonomy verbatim — no new scope value is invented:

```
OWN, FUNDRAISER, PARTNER, CAMPAIGN, PROGRAM, FUND, BENEFICIARY_CASE, ASSIGNED_WORK,
ORGANIZATION, GLOBAL_PLATFORM
```

Scope is carried directly on the assignment row (`principal_role_assignments.scope_type` /
`scope_id`, and `authority_assignments.scope_type` / `scope_id`) rather than a separate
scope-assignment table (`ENGINEERING CHOICE` — avoids a redundant join with no locked requirement
for scope to exist independently of an assignment; a scope with no Role/Authority attached to it
has no meaning under this model).

### Scope Type / Scope Target Matrix (`IMP003-READY-M02`)

Root cause of the finding: the original draft let `scope_type` itself be null "for
GLOBAL_PLATFORM/ORGANIZATION," which is wrong — it conflated "this scope type has no concrete
target ROW" with "no scope type was recorded at all." **Corrected rule: `scope_type` is NEVER
null — every assignment row always states which of the 10 taxonomy values applies.** Only
`scope_id` (the polymorphic TARGET reference) is nullable, and only for the three scope types that
have no concrete domain row to point at:

```
scope_type          scope_id            target type                    resolver
GLOBAL_PLATFORM      MUST be NULL        (none — the whole platform)     scope_type match only
ORGANIZATION         MUST be NULL        (none — Q1: exactly one         scope_type match only
                                          organization, so no per-org
                                          row exists to reference)
OWN                  MUST be NULL        (none — resolved dynamically    OWN resolver compares the
                                          against the ACTING principal's  target resource to the
                                          own identity at evaluation      principal_id at
                                          time, never a stored target)    evaluation time
PARTNER              MUST be NON-NULL    Partner.id (future Partner       Partner scope resolver
                                          module)                         (future stage)
CAMPAIGN             MUST be NON-NULL    Campaign.id (future)             Campaign resolver (future)
PROGRAM              MUST be NON-NULL    Program.id (future)              Program resolver (future)
FUND                 MUST be NON-NULL    Fund.id (future)                 Fund resolver (future)
FUNDRAISER           MUST be NON-NULL    Fundraiser Profile id (future)   Fundraiser resolver
                                                                          (future)
BENEFICIARY_CASE     MUST be NON-NULL    Beneficiary Case id (future)     Beneficiary resolver
                                                                          (future)
ASSIGNED_WORK        MUST be NON-NULL    a concrete work-item reference   owning-domain resolver
                                          (exact entity type is the       (future) — IMP-003 only
                                          owning domain's own concern     requires that it be
                                          once it exists)                 non-null and validated
```

**Validation (write-time, not merely evaluation-time):** an assignment INSERT is REJECTED — never
silently accepted — if `scope_id` is non-null for `GLOBAL_PLATFORM`/`ORGANIZATION`/`OWN`, or null
for any other scope type. This is enforced by a MySQL 8 `CHECK` constraint on the table (deterministic,
row-local, no `NOW()` or cross-row dependency — fully compatible with generated-column/CHECK-
constraint restrictions) PLUS an application-level validation pass before the insert is attempted
(defense in depth, matching this repository's established pattern of enforcing invariants at both
the application and database layer).

**Unknown scope type:** a `scope_type` value outside the 10 taxonomy values is REJECTED at
assignment-write time (the `CHECK` constraint / application validation whitelist both enumerate
exactly these 10 values) and, as a second layer of defense, DENIED at evaluation time if one is
ever somehow encountered (e.g. data drift, a future migration bug) — the evaluator never treats an
unrecognized scope type as "no restriction"/allow.

**Invalid scope target:** creating an assignment whose `scope_id` does not resolve to an existing,
active row of the expected target type is REJECTED at write time (validated transactionally,
inside the same transaction as the insert — see "Concurrency"). If a target row is later
deleted/deactivated AFTER a valid assignment was created, evaluation against that assignment
DENIES from that point forward (never falls back to treating a dangling reference as unrestricted
access) — see "Scope Target Integrity" below.

### Domain-Aware Scope Resolution

Per §15, `OWN` (and every other scope type) means something different per resource domain — no
generic `owner_id` column is assumed. IMP-003 defines a `ScopeResolver` CONTRACT (interface), one
implementation per domain, resolved by the domain's own model:

```php
interface ScopeResolver
{
    public function scopeType(): string;                       // e.g. 'OWN', 'PARTNER'
    public function resourceMatchesScope(mixed $resource, ScopeAssignment $assignment): bool;
    public function applyToQuery(Builder $query, ScopeAssignment $assignment): Builder;
}
```

IMP-003 implements this contract only for what it OWNS today: `OWN` resolution against the
`User` identity itself (e.g. "a Donor may view/update their own `User`/profile record" —
`resource->id === assignment->principal_id`). Every other domain-specific resolver (Donation OWN,
Campaign OWN-as-creator, Partner scope containment, Fundraiser scope containment, Beneficiary
Case scope) is implemented BY that domain's own later stage, against this same contract — IMP-003
does not invent Donation/Campaign/Partner internals it does not own (Out of Scope, §7 boundary
with IMP-002 extended to every later domain module).

**Enforcement layers, both required (§27):**

```
Query-level:  a domain's Eloquent query for a list/search/report endpoint MUST apply the
              resolved scope's `applyToQuery()` BEFORE executing — never "fetch all, then filter
              in PHP/the view." This is a Policy-adjacent concern (a global/local Eloquent scope
              driven by the resolved ScopeAssignment set for the current Principal+Permission).
Resource-level: a Policy method additionally re-checks `resourceMatchesScope()` for a single-
              resource show/update/delete action — defense in depth against a resource reached by
              ID directly (IDOR), not solely relying on the list query having filtered correctly.
```

### Scope Target Integrity (`IMP003-READY-M04`/`§15-16`)

`scope_id` is polymorphic (its target TABLE depends on `scope_type`, and most target domains —
Partner, Campaign, Fundraiser Profile, Beneficiary Case — do not exist yet in this repository), so
a single native FK cannot be declared for it today. This is NOT treated as "application enforced,
nothing more" — the complete integrity contract is:

```
Type whitelist:            scope_id is only ever interpreted against the scope_type recorded on
                            the SAME row (see "Scope Type / Scope Target Matrix") — a resolver is
                            never asked to interpret a scope_id under the wrong scope_type.
Polymorphic Resolver Contract: each scope type that requires a concrete target (every one except
                            GLOBAL_PLATFORM/ORGANIZATION/OWN) is backed by a per-domain resolver
                            declaring: scope_type, concrete table/model, an existence check, a
                            lifecycle (active/assignable) check, its lock strategy (below), its
                            deletion-protection behavior, and its authorization resolver — a
                            resolver missing any of these six is incomplete and must not be
                            registered.
```

`IMP003-REAUDIT-M04`: the earlier draft allowed "the domain's own re-validation immediately before
commit where a native FK is not yet possible" as a substitute for locking — this is exactly the
"validate, then insert without a lock in between" race the reaudit correctly rejected (a
concurrent deletion/deactivation could still commit in the gap between that revalidation and the
assignment insert). **Corrected: target existence/lifecycle validation is never a lone read — it
is always paired with locking the concrete target row, inside the SAME transaction, per the
stable lock order below.**

```
Target existence + lock (assignment CREATION):    resolve scope_type -> resolve the concrete
                            domain table/model -> SELECT ... FOR UPDATE the concrete target row
                            (via the domain's OWN resolver, which knows its own table) -> verify
                            it exists -> verify its lifecycle permits assignment (active, not
                            deleted/deactivated) -> only THEN create/mutate the
                            principal_role_assignment / authority_assignment row -> commit. This
                            lock is acquired within the SAME transaction as steps 1-2 of the
                            stable lock order in "Concurrency" (Grantor/Target Principal locks
                            already held) — never a separate, earlier transaction whose lock is
                            released before the assignment write.
Target deletion/deactivation (participates in the SAME locking):    the owning domain's own
                            delete/deactivate path for a scope target MUST ALSO lock that exact
                            target row (SELECT ... FOR UPDATE) before proceeding, then inspect
                            (and, per the domain's own policy, revoke or block) active
                            authorization assignments referencing it, then commit. Because both
                            paths (assignment creation, and target deletion/deactivation) lock the
                            SAME concrete row before doing anything else, they SERIALIZE against
                            each other — whichever transaction acquires the lock first completes
                            fully (either creating a valid assignment against a target that is
                            provably still active, or deleting/deactivating a target and handling
                            its existing assignments per domain policy) before the other proceeds;
                            neither can "sneak in" to observe a target as valid immediately before
                            it stops being so.
Deletion protection:        where a scope target has ACTIVE authorization assignments referencing
                            it, the owning domain's chosen rule (RESTRICT destructive deletion
                            until those references are revoked/closed, OR — where the domain uses
                            soft-deactivation/status rather than hard delete — mark it inactive and
                            rely on evaluation to DENY against it) is a domain-specific policy
                            choice this specification does not pre-select (no target-table domain
                            exists yet); either choice satisfies this contract as long as it is
                            applied under the SAME lock described above, and as long as an
                            inactive/deleted target is NEVER silently treated as "unrestricted."
Inactive/deleted target
  behavior:                 an assignment whose target has become inactive/deleted is NEVER treated
                            as "no longer scoped, therefore unrestricted" — the resolver's
                            `resourceMatchesScope()`/`applyToQuery()` must return "no match" for a
                            target it cannot positively confirm is active, which the evaluator
                            then treats as ordinary scope failure -> DENY (see "Default Deny").
Authorization default:      missing / deleted / unresolved scope target -> DENY (explicit
                            invariant, restated in "Default Deny" and "Test Contract").
Orphan detection test:      see "Database Test Contract" — a scheduled or CI-time integrity check
                            (implementation detail, not fixed here) confirms no active assignment
                            references a scope_id that no longer resolves for its scope_type, and
                            a unit test proves the evaluator denies such a row rather than allowing
                            it.
```

The reconciled, single stable lock order (Grantor Principal -> Target Principal -> Concrete Scope
Target -> Existing Assignment/Active-Slot -> Mutation -> Commit) is specified ONCE, in
"Concurrency" below, and applies identically here — this section does not define a second,
competing lock order.

---

## Ownership Model

Ownership is evaluated as ONE term of the AND-chain (distinct from Scope, per RBAC-ARCHITECTURE.md
listing them as separate terms) via the same `ScopeResolver` contract's
`resourceMatchesScope()` method when the applicable scope is `OWN` (or another subject-bound
scope). Examples restated from DATA-SCOPE-MODEL.md, not invented:

```
Donor          -> own Donation records            (Donation domain's own resolver, later stage)
Fundraiser     -> own/scoped attribution           (Fundraising & Attribution's own resolver)
Beneficiary    -> own application/profile           (Beneficiary domain's own resolver)
Partner User   -> linked Partner resources only     (Partner domain's own resolver)
Operational Staff -> assigned work / authorized org scope (ASSIGNED_WORK / ORGANIZATION)
```

Generic read permission never implies private-field disclosure (RBAC-ARCHITECTURE.md: "Object
Access != Full Field Disclosure") — field-level redaction is a Policy/Resource-transformation
concern each domain implements against its own sensitive fields (e.g. Beneficiary
`beneficiary.view_sensitive` as a DISTINCT, narrower permission from `beneficiary.view`).

---

## Business Authority

Reuses `docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md`'s conceptual structure exactly:

```
Authority Type
        v
Authority Assignment
        +-- Principal
        +-- Scope
        +-- Effective Dates
        +-- Status
```

### Authority Type Registry (`ENGINEERING CHOICE`, extensibility mechanism)

`authority_types` is a DB-backed, seeded, EXTENSIBLE lookup table — not a closed code enum —
because later domain stages (Fundraiser, Partner, Beneficiary, Finance) will need to register
their OWN Authority Types (e.g. an "approved Fundraiser operating capability" type) that no
Level 1-3 document names today. IMP-003 seeds ONLY the 7 types `BUSINESS-AUTHORITY-MODEL.md`
explicitly names:

```
financial_approver, refund_approver, withdrawal_approver, distribution_approver,
zakat_authority, partner_verifier, beneficiary_verifier
```

A later domain stage adds its own Authority Type row via its own migration + implementation
specification (the same governance path as adding a new Permission) — this is NOT a new
architecture decision each time (the extensibility mechanism itself is the one architectural
decision, made here), so it does not require a fresh Human Decision per new type, provided the new
type does not itself encode a business rule beyond naming a capability (rates/thresholds/routing
remain that domain's own concern, per MASTER-REQUIREMENTS.md §6).

This directly answers §16/§31/§33's "establish the mechanism, do not populate later-domain
semantics": "approved Fundraiser" and "approved Beneficiary Case verifier" are NOT invented as
Authority Types by this document — the registry that will hold them is.

### Authority Assignment

```
authority_assignments (authority_type_id FK, principal_id FK->principals.id, scope_type
  (non-null), scope_id, starts_at, ends_at nullable, revoked_at nullable,
  assigned_by_principal_id FK->principals.id, revoked_by_principal_id FK->principals.id,
  timestamps) — see "Database Contract > `authority_assignments`" for the full, corrected
  contract (principal_id is a real FK per IMP003-READY-M04; scope_type is never null per
  IMP003-READY-M02)
```

Never implied by Role membership — restated explicitly:

```
Super Admin          != automatic Financial Approver
Finance Operator     != Financial Approver
Beneficiary Operator != Beneficiary Verifier unless assigned
Permission "approval.decide" alone is insufficient
```

### Financial Authority Boundary

Financial Authority is NOT a separate mechanism — it is simply the subset of Authority Types that
are financial (`financial_approver`, `refund_approver`, `withdrawal_approver`,
`distribution_approver`). IMP-003 provides the foundation (registry + assignment + evaluation) for
these; it does NOT implement:

```
refund/withdrawal/distribution approval workflow or matrix (Q12/Q14/Q15 — Finance & Approval
  domain stages)
the "Authorized Financial Consequence" step itself (docs/02-architecture/FINANCIAL-POSTING-
  BOUNDARY.md) — IMP-003 only provides the primitive that step's owning module will call to check
  "does this Principal hold financial_approver for this scope/resource-state?"
```

`role/permission alone must not imply financial authority` is enforced structurally: no
`role_permissions` seed IMP-003 creates ever includes a permission literally named
`finance.*.approve`, and the authorization evaluation service treats "Required Business Authority"
(step 6) as a SEPARATE AND-term from "Permission" (step 3) — a caller cannot satisfy step 6 by
having step 3 pass.

---

## Approval Authority (primitive only)

The Approval module (MODULE-OWNERSHIP.md §12: Approval Policy, Policy Version, Approval Instance,
Approval Step, Approval Decision) does not exist yet. IMP-003 provides ONLY the evaluation
primitive `BUSINESS-AUTHORITY-MODEL.md`'s "Approval Candidate Resolution" already specifies:

```
is this principal assigned/authorized for this approval step?
  = active Authority Assignment (matching the step's required Authority Type)
  AND permission
  AND scope
  AND authentication assurance
  AND assigned approval step         <- supplied by the Approval module (not IMP-003) once it
                                          exists; IMP-003 defines this as an injectable predicate
  AND resource state                  <- supplied by the owning domain module
```

IMP-003 defines this as an interface (`ApprovalCandidateResolver`) the future Approval module
implements against; it implements no Approval Policy/Instance/Step/Decision model itself.

---

## Authentication Assurance (integration, not redefinition)

IMP-003 consumes IMP-002's `App\Services\Identity\AssuranceService::isElevated()` /
`AssuranceService::level()` directly — it does not add an assurance column to any RBAC table.
Permissions/Roles/Authority Assignments never carry an assurance requirement as STORED state on
themselves in a way that could be confused with a User property; instead, specific ACTIONS
(assign role, assign permission, assign authority, revoke any of the above, transition
`identity.security.transition`, and any later-domain action a domain marks high-risk per
AUTHENTICATION-ASSURANCE.md's categories) declare in code (e.g. a Policy method or a dedicated
`RequiresElevatedAssurance` action attribute/trait) that they require ELEVATED — evaluated at
request time against the CURRENT session, exactly as IMP-002's own `elevated.assurance` middleware
already does for its own actions.

---

## Security Restriction (integration, not redefinition)

Every authorization evaluation begins by re-confirming `User::canAuthenticate()` (IMP-002) is still
true for the acting Principal — DISABLED or SUSPENDED denies before any Role/Permission/Scope
evaluation runs, consistent with Q24's "Authentication Effect" table. IMP-003 does not add a
second, competing restriction concept; it is a consumer of IMP-002's single Security Restriction
state.

`identity.security.transition` (the permission gating WHO may flip ACTIVE<->DISABLED /
NONE<->SUSPENDED, which IMP-002 explicitly deferred — see Q24 "Authority" note) is the ONE new
permission IMP-003 must seed to close that deferral. IMP-003 does not decide the default holder of
this permission beyond what "Super Admin Canonical Authorization" below establishes.

---

## Default Deny

The authorization evaluation service (`AuthorizationContext`/`AuthorizationEvaluator` — naming is
an engineering choice for the Policy/Gate integration layer below) returns DENY, never a silent
ALLOW or an exception swallowed into "probably fine," whenever ANY of the following is true:

```
principal not authenticated
security restriction active (DISABLED/SUSPENDED)
permission missing
scope unresolved (no active assignment covers the target resource)
ownership unresolved
business authority missing (when the action requires one)
resource state invalid (per the owning domain's predicate)
approval assignment missing (when the action requires one)
assurance insufficient (action requires ELEVATED, session is STANDARD)
```

No implicit allow path exists — every Policy method's default return is `false`/`Response::deny()`
before any positive check runs (a defensive coding pattern specified here as a REQUIREMENT for
IMP-003's own policies, not merely a suggestion).

---

## Policy / Gate Integration

```
Request
  v
Authentication (IMP-002: auth middleware)
  v
Security Restriction (IMP-002: identity.active middleware — already exists, reused)
  v
Route/Middleware coarse gate (IMP-003: a coarse "authenticated administrative area" style
  middleware where useful, e.g. gating the whole /admin/* prefix to identities holding ANY
  internal role — NOT a substitute for the fine-grained checks below)
  v
Policy (Laravel Policy per domain Model — the framework mechanism explicitly preferred per
  CHANGE-CONTROL.md "Dependency Governance": use framework capabilities before custom
  equivalents — "policies" is literally named there)
  v
Authorization Context resolution (IMP-003 service: resolves the Principal's active Role/
  Permission/Scope/Authority Assignments for this request into a lightweight, request-scoped
  value object — NOT cached across requests by default, see "Cache")
  v
Permission check
  v
Domain Scope check (query-level AND resource-level, per "Query-Level Enforcement")
  v
Ownership check
  v
Business Authority check
  v
Resource/Approval State check (predicate supplied by owning domain)
  v
Assurance check
  v
ALLOW / DENY
```

Middleware is used ONLY for the coarse authenticated/restriction/administrative-area gate — never
for the fine-grained AND-chain (§26 "Avoid putting all logic in middleware"). Policies own the
fine-grained chain; a shared `AuthorizesRequest`-style trait/base Policy class implements the
common AND-chain sequencing so each domain Policy only supplies its OWN permission code, scope
resolver, ownership check, and resource-state predicate — not re-implementing the sequencing.

---

## Query-Level Enforcement

Every list/search/report Eloquent query for a scoped resource MUST apply a scope-driven query
constraint (a local/global Eloquent scope parameterized by the resolved `ScopeAssignment` set)
BEFORE the query executes — "fetch everything then hide unauthorized rows" is explicitly
prohibited (§27). This is enforced by convention (a base `ScopedQueryBuilder`/trait IMP-003
provides) plus a MANDATORY test per domain (later stages) proving an out-of-scope row never
appears in a list result even when a Policy would separately deny direct access to it.

---

## API Contract (authority intersection only — no token implementation)

Per SECURITY-ARCHITECTURE.md, a future token's effective authority is the INTERSECTION of the
token's own grant and its Principal's authority — never a union, never an expansion. IMP-003
defines the contract this implies for the authorization evaluator:

```
effective_permission_set(principal, token) = principal_permission_set(principal)
                                              INTERSECT token_capability_set(token)
```

No separate "API RBAC system" is created (§28) — the SAME Role/Permission/Scope/Authority model
and the SAME evaluator are used; a token, when one exists, is simply an additional narrowing input
to the same evaluation. Token issuance/management/storage is NOT implemented here (Out of Scope) —
this is a contract for whichever stage adds `/api/v1` token authentication to consume.

---

## System Principal / Integration Principal

Per SECURITY-ARCHITECTURE.md's explicit, named concepts. Neither is a `User` row (never
authenticated via password/MFA/session) and neither may hold unrestricted access:

```
system_principals       (id, code UNIQUE e.g. 'scheduler.commission-recalc', description,
                         timestamps) — a fixed, seeded catalog, one row per distinct background-
                         job identity that needs its own auditable authorization context.
integration_principals   (id, code UNIQUE e.g. 'payment.tripay-webhook', description, timestamps)
                         — one row per external integration.
```

Both participate in `principal_role_assignments`/`authority_assignments` via a canonical
`principals` row (`principal_kind='system'`/`'integration'`, linked via `system_principal_id`/
`integration_principal_id` — see "Database Contract > `principals`", `IMP003-READY-M04`) — never
given a blanket/global Role; each is assigned only the narrow Role(s)/Authority(ies) its specific job or
integration actually requires, and every action it takes is audited under its own principal
identity, never attributed to "the system" generically and never conflated with a human User or
with each other (SECURITY-INVARIANTS.md). A background job that produces a financial consequence
still goes through the full `docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md` chain with its
own explicit Authority Assignment — it is never given an implicit bypass of that chain merely for
being a System Principal.

---

## Super Admin Canonical Authorization (Q25 bridge)

Q25 created ONLY the first Super Admin `User` row plus a durable one-time bootstrap guard
(`super_admin_bootstraps`, IMP-002) — deliberately no role/permission/authority. IMP-003 must
establish canonical authorization for that identity (and for any subsequent Super Admin) WITHOUT
reopening the public bootstrap mechanism (no new public route, no default credential, §35).

```
Bridge mechanism (ENGINEERING CHOICE, no new Human Decision needed — this is how an ALREADY-
  authorized event (the recorded bootstrap) is converted into an authorization assignment, not a
  new grant of authority):
  1. A dedicated, one-time, CLI-only Artisan command (e.g. `rbac:bridge-first-super-admin`,
     exact name not locked) is authorized to run ONLY when:
       a. exactly one `super_admin_bootstraps` row exists (Q25's guard), AND
       b. NO `principal_role_assignments` row already assigns the `super_admin` Role to ANY
          principal (a second, independent guard specific to this bridge — prevents re-running
          the bridge after canonical authority already exists, distinct from Q25's own guard
          which only prevents a second IDENTITY bootstrap).
  2. It ensures a canonical `principals` row exists for the bootstrapped identity (see "Principal
     Model"), then assigns ONLY the `super_admin` Role (`GLOBAL_PLATFORM` scope) to that principal
     (resolved via the retained `super_admin_bootstraps.user_id`, or, if that identity has since
     been deleted, refused — the bridge cannot invent a target).
  3. It grants NOTHING ELSE. `IMP003-READY-B01`: the Bridge MUST NOT assign any Business Authority
     Type, Financial Authority Type, or Approval Authority to the bootstrapped identity — not even
     to itself, not even under ELEVATED assurance, and not as a "first grant must come from
     somewhere" special case. The earlier draft's step 3 (letting the bootstrapped Super Admin
     grant itself a Financial Authority Type immediately after the Bridge) is REMOVED without
     replacement — it was an unauthorized self-grant of financial authority (see
     "Self-Escalation Protection" — self-financial-authority-grant is an absolute DENY with no
     exception, including for this Bridge). Financial/regulated authority for this identity (or
     any identity) remains UNASSIGNED until EITHER (a) a distinct, already-authorized Principal
     grants it (ordinary Authority Assignment, per "Self-Escalation Protection" — always requires
     a second, distinct authorizing Principal), OR (b) a later, explicitly approved deterministic
     provisioning mechanism is specified by a future stage. IMP-003 does NOT invent mechanism (b)
     now — per this remediation's own instruction (§3: "Do NOT invent such a mechanism now if none
     is already authorized... leave financial authority unassigned until the later authorized
     workflow exists"), an unassigned state is the correct, safe, DEFAULT-DENY outcome, not a gap
     to be patched with an exception.
  4. An audit event is emitted (`super_admin_canonically_authorized`), naming exactly what WAS
     granted (`super_admin` Role only) so no later audit review could mistake this for a broader
     grant.
Any subsequent Super Admin does NOT use this bridge — it is granted the `super_admin` Role through
  ordinary Role assignment by an EXISTING Super Admin (a DIFFERENT, already-authorized Principal —
  never itself) (§8/§35 — "any subsequent Super Admin identity does NOT use the first-bootstrap
  mechanism"), exactly as IMP-002's own specification already states.
Idempotency/concurrency: see "Concurrency" — the Bridge locks the canonical `principals` row for
  the resolved bootstrapped identity FIRST, then re-validates guard (1b) under that lock, then
  performs the single Role-assignment insert, then commits — a concurrent double-invocation can
  produce at most ONE effective `super_admin` Role assignment, never two, and never a broadened
  one (re-running the command after success is refused by guard (1b), not silently re-executed).
```

---

## Invitation Intent Bridge

IMP-002's `Invitation.invited_actor` field (already implemented: `partner_representative` |
`internal_administrative_identity` | `super_admin`) is ATTRIBUTION/INTENT ONLY — confirmed by
reading `app/Services/Identity/InvitationService.php`: acceptance creates a `User` row and marks
the invitation accepted; it assigns no Role, Permission, or Authority (verified — no such call
exists in that service).

`IMP003-REAUDIT-M03`: every section of this specification that touches invitation acceptance MUST
state the same contract — the earlier draft's Actor Materialization table contradicted this
section by describing Partner Representative's Role as "assigned at invitation acceptance." That
table has been corrected (see "Actor Materialization" — now split into separate "Identity
Creation"/"Authorization Transition" columns). The canonical contract, restated in full here:

```
Invitation ACCEPTED
  -> Human identity provisioned/activated exactly as IMP-002 already specifies (a `User` row
     exists, the invitation is marked accepted)
  -> the invitation's `invited_actor` value is RETAINED as context/evidence only (it is not
     deleted or overwritten — it remains available as an INPUT to the later transition below)
  -> NO Role
  -> NO Permission
  -> NO Scope
  -> NO Business Authority
  -> NO Financial Authority
  -> NO Approval Authority
  (all six are simultaneously and unconditionally absent the instant acceptance completes — there
  is no partial or "identity-implied" grant of any of them)
```

The actual grant of any Role/Scope/Authority happens ONLY through a SEPARATE, later, independently
authorized, audited, transactional **IMP-003 Authorization Transition**:

```
performed by a Principal who ALREADY holds the required grant authority (e.g. `rbac.role.assign`
  for the target Role) — never the newly-accepted identity itself, and never an automatic
  consequence of Invitation::accept() completing
may read the accepted invitation's `invited_actor` value as a HINT for which Role to offer — this
  is the ONLY thing `invited_actor` is ever used for; it is input to a human/authorized decision,
  never a self-executing grant
is an ORDINARY Role/Scope/Authority assignment in every other respect — subject to the same
  Self-Escalation Protection, stable lock order, and active-assignment-uniqueness rules as any
  other assignment in this specification
```

### Partner Representative (`§19`)

Invitation acceptance never implies Partner authorization. A later, explicit Authorization
Transition may create the `partner_representative` Role, a `PARTNER` scope assignment bound to the
specific Partner, and/or a `partner_verifier` Authority Assignment — but these are THREE
INDEPENDENT grants, each requiring its own explicit action; accepting an invitation never
auto-infers all three (or any of them) together. Which of them (if any) an authorized workflow
grants, and when, is a decision made by that workflow at that later time — not decided or
pre-ordained by this specification or by the invitation itself.

### Internal Administrative Identity (`§20`)

Identical rule: invitation/provisioning creates the identity ONLY. Every internal operational
Role (`finance_operator`, `beneficiary_operator`, etc.) is granted exclusively through a separate,
later Authorization Transition performed by an already-authorized administrator — never
automatically, never self-assigned (this is also required by "Self-Escalation Protection").

### Subsequent Super Admin (`§21`)

Q25's first-bootstrap semantics are NOT reopened by this rule — the Bridge remains the sole,
one-time mechanism for the FIRST Super Admin (see "Super Admin Canonical Authorization"). For
every Super Admin identity AFTER the first: invitation/provisioning creates the identity only;
canonical `super_admin` Role assignment is an ORDINARY Authorization Transition performed by an
EXISTING Super Admin (a distinct, already-authorized Principal) — never the Bridge, never
self-assigned, never automatic upon acceptance.

---

## Self-Escalation Protection

`IMP003-READY-M01`/`B01`: the earlier draft carved out a "Super Admin-equivalent may self-assign"
exception to self-role-assignment, and a matching self-financial-authority-grant exception in the
Q25 Bridge. Both are REMOVED. **Corrected, absolute invariant — no ordinary-action exception of
any kind, for any Principal, regardless of what they hold:**

```
A Principal may never, through any ORDINARY RBAC action performed via the authorization service,
  cause their OWN effective authority to increase, broaden, restore, or otherwise materially
  change — whether by Role, Scope, Business Authority, Financial Authority, or Approval-step
  assignment. Concretely, ALL of the following are DENY, with NO exception based on which
  Permission/Role/scope the acting Principal happens to hold:
  - Self-role assignment: a Principal assigning ANY Role to themselves is DENIED, unconditionally
    — holding `rbac.role.assign`, even scoped GLOBAL_PLATFORM, does NOT authorize self-assignment.
    Ordinary role management targets OTHER principals only, always.
  - Self-scope expansion: a Principal widening their OWN existing assignment's `scope_type`/
    `scope_id` (e.g. PARTNER -> ORGANIZATION) is treated identically to a new self-assignment —
    DENIED, never permitted as a mere "update."
  - Self-business-authority grant: a Principal creating an Authority Assignment where
    `principal_id` resolves to themselves is DENIED, unconditionally.
  - Self-financial-authority grant: same rule, with NO Super-Admin exception (this closes
    `IMP003-READY-B01` — see "Super Admin Canonical Authorization," which no longer grants any
    Authority Type at all, to anyone, including itself).
  - Self-approval-step assignment: a Principal assigning themselves as the authorized actor for an
    approval step is DENIED, unconditionally.
  Every one of the above always requires a SECOND, distinct Principal to act — a hard
  separation-of-duties rule per SECURITY-INVARIANTS.md ("Duties that must be separated... are
  never collapsed into one actor/role without an explicit, approved exception") — and NO exception
  is approved by this specification for any ORDINARY action.
```

**This absolute rule does NOT describe the Q25 Super Admin Bridge**, which is not a Principal
exercising an ordinary RBAC action on themselves — it is a one-time, non-interactive, CLI-only,
deterministic conversion of an ALREADY Human-authorized event (the recorded Q25 bootstrap) into
exactly one Role assignment, gated by its own independent guards (see "Super Admin Canonical
Authorization"). The Bridge is the SOLE mechanism in this specification permitted to create a
Role assignment whose target coincides with "the identity the bootstrap evidence names" — and even
the Bridge grants ONLY the `super_admin` Role, NEVER any Business/Financial/Approval Authority
(that restriction is unconditional, per `IMP003-READY-B01` above). If a recovery/re-provisioning
mechanism is ever needed beyond the Bridge, it requires its own separately controlled mechanism —
not invented by this remediation, per its own explicit instruction (§4/§5).

This directly satisfies §36 without inventing a Human policy beyond the separation-of-duty
principle SECURITY-INVARIANTS.md already locks, and without creating a generalized self-service
authorization system (§5's explicit prohibition).

---

## Database Contract (specification only — no migration created)

All tables: `id` BIGINT unsigned PK; ULID `public_id` only where an external-safe reference is
actually needed (none of these tables are directly exposed by public routes, so none currently
need one — added later if that changes). MySQL 8.x target, strong FK integrity, per
`DATABASE-ARCHITECTURE.md`.

### `principals` (`IMP003-READY-M04` — new canonical authorization-identity registry)

Root cause of the finding: the original draft referenced a bare `principal_type` + `principal_id`
pair with NO foreign key ("enforced at the application layer") on every assignment table — an
authorization grant could reference a nonexistent principal with nothing but application code
preventing it. **Corrected: a single canonical `principals` table is the one real FK target for
every assignment table below.**

```
purpose:      canonical authorization-identity registry — every Role/Authority assignment
              references exactly one row here via a REAL foreign key; this is what makes
              "assignment references nonexistent principal" a database-level impossibility, not
              merely an application-level convention
key fields:   principal_kind (string enum: 'human' | 'system' | 'integration'),
              human_user_id (BIGINT unsigned, nullable, UNIQUE, FK -> users.id, `IMP003-REAUDIT2-M02`:
                RESTRICT (NO ACTION), NOT nullOnDelete — see "Human User FK Strategy" below),
              system_principal_id (BIGINT unsigned, nullable, UNIQUE, FK -> system_principals.id),
              integration_principal_id (BIGINT unsigned, nullable, UNIQUE,
                FK -> integration_principals.id),
              tombstoned_at (nullable timestamp — see "Principal Lifecycle"; meaningful ONLY for
                principal_kind='human' — see that section for why),
              disabled_at (nullable timestamp — see "Principal Lifecycle"; meaningful ONLY for
                principal_kind IN ('system','integration')),
              timestamps
CHECK (`IMP003-READY-M01` — corrected; the earlier draft's CHECK and its own "Deletion protection"
  text directly contradicted each other, requiring human_user_id NOT NULL unconditionally for
  principal_kind='human' while also describing it becoming null after User deletion):
              principal_kind='human' AND tombstoned_at IS NULL
                -> human_user_id NOT NULL AND system_principal_id NULL AND integration_principal_id NULL
              principal_kind='human' AND tombstoned_at IS NOT NULL
                -> human_user_id NULL AND system_principal_id NULL AND integration_principal_id NULL
              principal_kind='system'
                -> human_user_id NULL AND tombstoned_at NULL AND system_principal_id NOT NULL
                   AND integration_principal_id NULL
              principal_kind='integration'
                -> human_user_id NULL AND tombstoned_at NULL AND system_principal_id NULL
                   AND integration_principal_id NOT NULL
              A MySQL 8 CHECK constraint (a row-local boolean expression over these five columns,
              fully deterministic, no cross-row/NOW() dependency) plus application-level
              validation before every insert/update.
unique:       human_user_id, system_principal_id, integration_principal_id (each independently —
              at most one principals row per underlying identity; prevents a duplicate/competing
              authorization identity for the same User/System/Integration source; NULL values are
              correctly excluded from uniqueness comparison by MySQL for these three columns,
              which is the WANTED behavior here — a tombstoned human principal's null
              human_user_id must not block a genuinely different, later User from getting its
              own principals row)
indexes:      principal_kind, human_user_id, system_principal_id, integration_principal_id,
              tombstoned_at
deletion:     never hard-deleted once ever referenced by an assignment (mirrors every other
              table's "never hard-delete once assigned" rule) — see "Principal Lifecycle"
audit:        principal created / principal tombstoned (human) / principal disabled (system/
              integration only)
```

Never stored here: password, MFA secret, email, or any other IMP-002 credential/identity field —
human authentication remains entirely owned by IMP-002's `users` table; `human_user_id` is the
ONLY link, and it is a plain reference, never a duplicate of identity data.

#### Principal Lifecycle (`§14`, tombstone design per `IMP003-READY-M01`)

```
Creation:     a `principals` row is created lazily but IDEMPOTENTLY (firstOrCreate keyed on
              human_user_id / system_principal_id / integration_principal_id, inside the same
              transaction as the first assignment that needs it) the first time a User/System/
              Integration identity needs an authorization identity — never eagerly for every User
              at registration (consistent with "No Automatic Role at Registration" — creating the
              principals row itself grants NOTHING; it is an empty identity shell until an
              assignment references it).
Linkage:      exactly one principals row per human_user_id — enforced by the UNIQUE constraint
              above, so no duplicate/competing authorization identity can exist for one User.
Human disable/deactivate (while the User still exists): a 'human' principal (tombstoned_at IS
              NULL) has NO independent status column of its own for this case — its live status
              is ALWAYS read from IMP-002's OWN `users.lifecycle_state`/`security_restriction` at
              evaluation time (see "Security Restriction" — never a second, competing source of
              truth for the same fact). DISABLED/SUSPENDED is NOT the same thing as TOMBSTONED —
              a DISABLED User's principal remains a live, non-tombstoned row (it simply fails
              authorization via the existing Security Restriction check, and can become
              authorizable again if the User is re-enabled); TOMBSTONED is permanent and only
              follows actual User deletion (see below).
Canonical User Deletion Transaction (`IMP003-REAUDIT2-M01`/`M02` — atomic, replaces the earlier
              two-transaction "tombstone, commit, then delete" sequence, which left a window where
              the tombstone could commit while the subsequent `DELETE FROM users` still failed,
              leaving an inconsistent half-deleted identity):

```
BEGIN TRANSACTION
  1. SELECT ... FOR UPDATE the `users` row being deleted (lock first).
  2. SELECT ... FOR UPDATE the linked `principals` row for this human_user_id (lock second).
  3. Validate deletion preconditions (at minimum: the principal is not already tombstoned; any
     additional precondition a later stage requires — e.g. an outstanding financial hold — is
     that stage's own concern, not invented here).
  4. Revoke every currently-active `principal_role_assignments` row referencing this principal_id
     (set revoked_at = now(), revoked_by_principal_id = the acting administrator's Principal, or a
     distinguished System Principal reference where no human administrator directly triggered it
     — e.g. a self-service account-deletion flow).
  5. Revoke every currently-active `authority_assignments` row referencing this principal_id, same
     rule.
  6. ONE UPDATE statement sets BOTH `tombstoned_at = now()` AND `human_user_id = NULL` on the
     `principals` row together — the row satisfies the CHECK constraint's "tombstoned" branch the
     instant this single statement takes effect; there is no intermediate state where
     tombstoned_at is set but human_user_id is still populated (which the CHECK would reject).
  7. `DELETE FROM users WHERE id = ...` — this now succeeds cleanly: human_user_id was already
     nulled in step 6, so by the time this DELETE runs, no `principals` row references this
     User's id any more, and the FK (RESTRICT/NO ACTION — see below) has nothing left to restrict.
  8. COMMIT.
ROLLBACK the ENTIRE transaction if ANY step fails — steps 1-7 either all take effect together, or
  none of them do. There is no intermediate commit between the tombstone mutation and the User
  deletion; they are the SAME transaction.
```

Failure behavior (`§5`): if step 7 (`DELETE FROM users`) fails for any reason (an unrelated FK
elsewhere, a database error, an application-level abort), the transaction ROLLS BACK entirely —
the User row remains live, the `principals` row remains live and NOT tombstoned (its `revoked_at`/
`tombstoned_at`/`human_user_id` mutations from steps 4-6 are undone by the rollback exactly like
any other transactional write), and every role/authority assignment that steps 4-5 revoked is
restored to its prior state. No half-deleted identity, no orphaned tombstone, no detached
principal can result from this transaction.

Human User FK Strategy (`IMP003-REAUDIT2-M02`): `human_user_id -> users.id` uses **RESTRICT (NO
ACTION)**, not `nullOnDelete`. The earlier draft's `nullOnDelete` claim was invalid on its own
terms — an FK-triggered `SET NULL` can only touch the `human_user_id` column; it cannot also set
`tombstoned_at` in the same automatic action, so a bypass deletion relying on that cascade would
leave the row violating its own CHECK constraint (human_user_id null while tombstoned_at is still
null) rather than producing a valid tombstone. With RESTRICT/NO ACTION instead:

```
A direct `DELETE FROM users` for a User whose linked `principals` row is still LIVE (non-
  tombstoned, human_user_id populated) is REJECTED by the database itself (an ordinary FK
  violation) — the canonical transaction above is the ONLY path that can ever remove that link,
  because it is the only path that nulls human_user_id (inside the SAME transaction, before the
  DELETE) rather than relying on the database to do it automatically.
This is a deliberate, explicit, and honestly-documented invariant — "bypass deletion -> database
  REJECT" — rather than a claim that the database can silently produce a valid tombstoned
  principal on its own, which it cannot.
```

Deletion protection: once tombstoned, the `principals` row is NEVER hard-deleted and NEVER
              re-linked to a different User afterward — its historical/attribution value (see
              "Attribution" below) is permanent.
Orphan / tombstoned principal cannot authorize: a principal with `tombstoned_at` set (equivalently,
              principal_kind='human' with human_user_id null) MUST fail Applicable Subject
              Context Resolution (Step 0) deterministically — DENY, never "no restriction". It
              MUST NOT receive any NEW role or authority assignment (the assignment-creation path
              rejects any attempt to reference a tombstoned principal as a TARGET), and it MUST
              NOT act as a grantor (the same path rejects it as an ACTING principal). No automatic
              restoration exists — if a tombstoned principal is ever legitimately restored (e.g. a
              new User record is later associated with the same real-world identity), that requires
              an explicitly authorized FUTURE workflow, not invented here (§8) — the tombstoned row
              itself is never reactivated in place.
Audit identity: every audited RBAC event records the acting principals.id (or, for a
              System/Integration action, that principal's own id) — never "the system" as an
              unstructured string.
```

### `roles`

```
purpose:      canonical Role catalog
key fields:   code (unique, e.g. 'super_admin'), name, description, is_system (bool), timestamps
unique:       code
indexes:      code
deletion:     soft-delete or `retired_at`, never hard-delete once ever assigned (history)
audit:        role created/updated/retired events
```

### `permissions`

```
purpose:      canonical Permission catalog, synced from the code-defined registry
key fields:   code (unique, e.g. 'campaign.view'), description, module (nullable string tag),
              deprecated_at (nullable), timestamps
unique:       code
indexes:      code, module
deletion:     never hard-deleted once ever assigned; deprecated_at marks retirement
audit:        permission registered/deprecated (system-seeded, not usually human-audited, but
              the seeder run itself is logged)
```

### `role_permissions` (`IMP003-READY-m01` — history corrected)

Root cause of the finding: the original draft allowed hard-delete on revoke, reasoning that the
(not-yet-built) IMP-004 audit sink would preserve history — this makes IMP-003's OWN correctness
depend on a future stage that does not exist yet, which the remediation instruction explicitly
forbids. **Corrected: durable, self-contained history, independent of IMP-004:**

```
purpose:      Role -> Permission grant
key fields:   role_id FK->roles, permission_id FK->permissions, granted_at, granted_by_principal_id
              FK->principals.id (nullable ONLY for system-seeded grants — the Permission Registry
              seeder itself is not attributed to any acting Principal, since it runs as part of
              deployment/migration, not an RBAC action taken by anyone; every grant performed
              through the ordinary assignment service has a non-null grantor), revoked_at
              (nullable), revoked_by_principal_id FK->principals.id nullable, timestamps
unique:       (role_id, permission_id) WHERE revoked_at IS NULL — enforced via the same
              generated-column technique specified for the assignment tables below (a grant that
              has been revoked no longer occupies the uniqueness slot, so the SAME role/permission
              pair can be re-granted later as a NEW row, while history is preserved)
indexes:      role_id, permission_id
deletion:     NEVER hard-deleted — revocation sets `revoked_at`/`revoked_by_principal_id`; "active"
              grant = revoked_at IS NULL; this alone (not an audit-log entry elsewhere) is what
              lets IMP-003 answer "what did this Role's permission set look like historically"
              without depending on IMP-004 existing
audit:        permission granted-to-role / revoked-from-role (emitted in ADDITION to, not instead
              of, this table's own durable columns)
```

`IMP003-REAUDIT2-M03`: `granted_by_principal_id`/`revoked_by_principal_id` reference `principals.id`,
not `users.id` — a canonical Principal survives its underlying human User's deletion (via the
Canonical User Deletion Transaction's tombstone step above), so this attribution remains
historically reconstructable even after the acting administrator's own User row is gone. See
"Attribution" below for the full rule applied consistently across every authorization table.

### `principal_role_assignments`

```
purpose:      binds a Role to a Principal within a Scope, for a bounded effective period
key fields:   principal_id (BIGINT unsigned, FK -> principals.id — see "IMP003-READY-M04" above;
              REPLACES the earlier unconstrained principal_type+principal_id pair), role_id
              FK->roles, scope_type (string, NEVER null — one of the 10 DATA-SCOPE-MODEL taxonomy
              values, see "Scope Type / Scope Target Matrix"), scope_id (nullable BIGINT,
              polymorphic target — null ONLY for GLOBAL_PLATFORM/ORGANIZATION/OWN, per that same
              matrix), starts_at, ends_at (nullable), revoked_at (nullable), assigned_by_principal_id
              FK->principals.id (nullable ONLY for the one documented Q25 Bridge exception — see
              "Super Admin Canonical Authorization"; every ordinary Role assignment requires a
              non-null grantor Principal), revoked_by_principal_id FK->principals.id nullable,
              timestamps
FK:           principal_id -> principals.id (real FK, enforced by the database — closes
              IMP003-READY-M04); role_id -> roles.id (real FK); assigned_by_principal_id/
              revoked_by_principal_id -> principals.id (real FK — `IMP003-REAUDIT2-M03`, replaces
              the earlier direct `-> users.id` attribution, which would have been orphaned by a
              grantor's own later User deletion)
CHECK:        scope_id NULL/NOT-NULL matches scope_type per the "Scope Type / Scope Target Matrix"
              (see "Scope Uniqueness Normalization" immediately below for how this feeds the
              uniqueness key)
indexes:      principal_id, role_id, (scope_type, scope_id)
deletion:     never hard-deleted (revoked_at marks revocation) — historical reproducibility
audit:        role assigned / role revoked
```

#### Scope Uniqueness Normalization + MySQL 8 Active-Assignment Uniqueness (`IMP003-READY-M02`/`M03`)

Root cause of both findings: the original draft specified a unique index with a `WHERE revoked_at
IS NULL AND (ends_at IS NULL OR ends_at > NOW())` condition. MySQL 8 does NOT support partial/
filtered unique indexes, and a generated column cannot be a function of `NOW()` (generated columns
must be deterministic functions of OTHER COLUMNS IN THE SAME ROW, not of wall-clock time) — this
design was not actually implementable on the target platform. Separately, relying on `scope_id`'s
SQL `NULL` inside a plain composite unique index is unsafe in the opposite direction: MySQL never
treats two rows with NULL in an indexed column as duplicates of each other, so a naive composite
unique key across a nullable `scope_id` would silently ALLOW multiple simultaneously-"active" rows
for GLOBAL_PLATFORM/ORGANIZATION/OWN scopes — exactly the duplicate-active-grant bug uniqueness is
supposed to prevent.

**Corrected, concrete, MySQL-8-compatible design — deterministic, keyed only on stored columns,
never on `NOW()`:**

```
1. Scope normalization: a STORED, deterministic expression normalizes scope_id for the uniqueness
   key only (never for the real scope_id column, which stays NULL where the matrix requires NULL):
     normalized_scope_id = IFNULL(scope_id, 0)
   0 is a safe sentinel because scope_id, where non-null, is always a real BIGINT UNSIGNED primary
   key starting at 1 — 0 can never collide with a genuine target id.
2. Active-slot generated column (STORED, deterministic, a function of scope_type/scope_id and
   revoked_at ONLY — no NOW(), no ends_at):
     active_assignment_key = CASE WHEN revoked_at IS NULL
       THEN CONCAT(principal_id, ':', role_id, ':', scope_type, ':', normalized_scope_id)
       ELSE NULL END
   A revoked row's active_assignment_key is NULL — and MySQL correctly never treats multiple NULLs
   in a unique index as duplicates, which is EXACTLY the wanted behavior here (revoked/historical
   rows must NOT collide with each other or with anything else). An active (non-revoked) row's key
   is a concrete, non-null string — and MySQL's unique index DOES correctly reject a second row
   with the same non-null key, which is EXACTLY the wanted behavior (no two simultaneously-active
   identical assignments). This uses the SAME "NULL never collides" MySQL behavior that caused the
   original bug, but now deliberately, for the state (revoked) where non-collision is correct,
   while producing a real deterministic value for the state (active) where collision-prevention is
   required — no NOW()-dependence anywhere in the expression.
3. UNIQUE INDEX on `active_assignment_key` (a single-column unique index over the generated
   column) is the entire uniqueness contract: "at most one ACTIVE identical assignment," fully
   MySQL-8-enforceable, fully deterministic, race-safe under InnoDB's own unique-index insert
   conflict detection (no window between "check" and "insert" — the INSERT itself fails atomically
   if a conflicting active row exists, which the assignment service then handles as an application-
   level conflict, not a silent duplicate).
```

**Expiration (`ends_at`) does NOT participate in this uniqueness key at all** — it only affects
EVALUATION (a live `WHERE` clause: `revoked_at IS NULL AND starts_at <= NOW() AND (ends_at IS NULL
OR ends_at > NOW())`, evaluated fresh per authorization check, never baked into a stored/indexed
value). This means an assignment that has EXPIRED but was never explicitly revoked still occupies
its active uniqueness slot (`active_assignment_key` remains non-null, since `revoked_at` is still
null) — this is intentional and deterministic, not an oversight: **renewal/replacement of an
expired-but-unrevoked assignment for the same (principal, role, scope) MUST, within ONE
transaction, first REVOKE the existing row (`revoked_at = now()`, `revoked_by_principal_id` = the
renewing Principal, or a distinguished System Principal reference where the renewal is itself
system-initiated) and only THEN insert the new row** — this is the exact same "supersede, then
create" pattern IMP-002's `EmailChangeRequest`
already uses and that this repository's governance has already reviewed and accepted (see
`docs/audits/IMP-002-IMPLEMENTATION-REMEDIATION-1.md`/`-2.md`, m01). No ambiguity is left open:
there is no "cleanup job" or implicit expiration-to-revocation conversion outside of this explicit,
transactional renewal path — an expired-and-never-revoked row simply stops being usable for
NEW authorization (evaluation excludes it) while correctly continuing to block a duplicate
active grant until it is explicitly revoked or superseded.

`authority_assignments` (below) uses the IDENTICAL `active_assignment_key` technique, keyed on
`(principal_id, authority_type_id, scope_type, normalized_scope_id)` instead of `role_id`.

### `authority_types`

```
purpose:      extensible registry of Business/Financial Authority Types
key fields:   code (unique), name, description, is_financial (bool, for the reporting/threat-
              model convenience of separating financial authority types without re-deriving it
              from the code string each time), timestamps
unique:       code
seed:         financial_approver, refund_approver, withdrawal_approver, distribution_approver
              (is_financial=true); zakat_authority, partner_verifier, beneficiary_verifier
              (is_financial=false)
deletion:     never hard-deleted once ever assigned
audit:        authority type registered (system-seeded initially; later domain-stage additions
              are audited as part of that stage's own migration/deploy record)
```

### `authority_assignments`

```
purpose:      binds an Authority Type to a Principal, within a Scope, for a bounded period
key fields:   authority_type_id FK->authority_types, principal_id FK->principals.id (real FK —
              see "IMP003-READY-M04"), scope_type (string, NEVER null — same matrix as
              principal_role_assignments), scope_id (nullable, same rule), starts_at, ends_at
              (nullable), revoked_at (nullable), assigned_by_principal_id FK->principals.id NOT
              NULL (no nullable exception exists here — `IMP003-READY-B01` already removed the one
              case, the Q25 Bridge's self-grant, that used to require nullability; the Bridge never
              creates an authority_assignments row at all, so this table's grantor is always a
              genuine, distinct, already-authorized Principal), revoked_by_principal_id
              FK->principals.id nullable, timestamps
FK:           principal_id -> principals.id; authority_type_id -> authority_types.id;
              assigned_by_principal_id/revoked_by_principal_id -> principals.id (`IMP003-REAUDIT2-M03`
              — replaces the earlier direct `-> users.id` attribution)
unique:       active_assignment_key (same generated-column technique as
              principal_role_assignments, keyed on principal_id/authority_type_id/scope_type/
              normalized_scope_id) — see "Scope Uniqueness Normalization + MySQL 8 Active-
              Assignment Uniqueness" above
indexes:      principal_id, authority_type_id, (scope_type, scope_id)
deletion:     never hard-deleted
audit:        authority assigned / authority revoked
constraint:   `assigned_by_principal_id` MUST always differ from the assignment's own
              `principal_id` — UNCONDITIONALLY, with NO exception (the Q25 Bridge grants only a
              Role, never an Authority Type — see "Super Admin Canonical Authorization" and
              "Self-Escalation Protection" — so no exception is needed or granted here). This is
              enforced by a CHECK/application validation that REJECTS the insert outright, not
              merely audited after the fact.
```

### Attribution (`IMP003-REAUDIT2-M03` — canonical Principal-based actor attribution)

Root cause of the finding: `role_permissions.granted_by_user_id`/`revoked_by_user_id`,
`principal_role_assignments.assigned_by_user_id`/`revoked_by_user_id`, and
`authority_assignments.assigned_by_user_id`/`revoked_by_user_id` all referenced `users.id`
directly. Since a User can be deleted (via the Canonical User Deletion Transaction above), this
would have orphaned the historical record of WHO granted/revoked an authorization the moment the
acting administrator's own account was later deleted — directly conflicting with this
specification's own durable-history requirement (`IMP003-READY-m01`).

**Corrected rule, applied consistently across every IMP-003 authorization table (and ONLY these
— this does not touch any unrelated business-domain table's own `created_by`/`approved_by`
columns, which are out of scope for this specification): every actor-attribution column
references `principals.id`, never `users.id` directly.**

```
role_permissions.granted_by_principal_id / revoked_by_principal_id       -> principals.id
principal_role_assignments.assigned_by_principal_id / revoked_by_principal_id -> principals.id
authority_assignments.assigned_by_principal_id / revoked_by_principal_id  -> principals.id
```

This works precisely because a canonical Principal is designed to OUTLIVE its underlying human
User (it becomes TOMBSTONED, never hard-deleted) — so a grant/revocation performed by an
administrator who is later deleted remains attributed to a real, permanent, resolvable row, not a
dangling reference. This also uniformly supports Human, System, and Integration grantors without
three separate nullable attribution-column sets — the grantor is always "a principal_id," and its
`principal_kind` (plus, for a live human principal, its linked `users` row) is what
audit/display logic resolves for human-readable presentation; the underlying authorization
HISTORY itself never depends on that resolution succeeding.

Where an assignment requires a grantor, the attribution column is `NOT NULL` (acceptable BECAUSE
the Principal survives — see `principal_role_assignments`/`authority_assignments` above); it is
nullable ONLY in the two specifically-documented, narrow cases where no acting Principal exists at
all: the Permission Registry seeder's system-seeded `role_permissions` grants (a deployment-time
action, not an RBAC action taken by any Principal), and the Q25 Bridge's `super_admin` Role
assignment (a one-time, non-principal-initiated, deterministic bootstrap event — see "Super Admin
Canonical Authorization" and "Q25 Bootstrap Attribution" immediately below). Neither nullable case
exists "to make deletion easier" — both reflect a genuine absence of any acting Principal.

#### Q25 Bootstrap Attribution (`§20`)

The Bridge's resulting `principal_role_assignments` row (the bootstrapped identity's `super_admin`
Role grant) has `assigned_by_principal_id = NULL` — attributed instead to the deterministic Q25
bootstrap event itself (the audit event `super_admin_canonically_authorized`, which independently
records that this specific, one-time, already-Human-authorized mechanism performed the grant, not
an ordinary Principal). This is NOT a self-escalation exception: no Principal — including the
bootstrapped identity itself — ever appears as the grantor of its own Role through the ordinary
authorization service; the Bridge is not "the Super Admin granting itself a role through normal
RBAC," it is a distinct, independently-guarded, non-principal-initiated provisioning mechanism
whose own guards (see "Super Admin Canonical Authorization") are what make it safe, not an
attribution value. Q25's own bootstrap-identification semantics (the `super_admin_bootstraps`
table, IMP-002) are not reopened or altered by this rule.

### `system_principals` / `integration_principals`

```
purpose:      fixed, seeded, non-authenticatable Principal catalogs (see "System Principal");
              each row here is what a `principals` row's system_principal_id/
              integration_principal_id links to
key fields:   code (unique), description, deactivated_at (nullable — see "Delete Semantics" below),
              timestamps
unique:       code
FK:           referenced BY `principals.system_principal_id`/`integration_principal_id` — a
              catalog row must exist BEFORE any `principals` row can reference it (see "Migration
              Order" below)
deletion:     never hard-deleted once ever assigned — see "Delete Semantics for System/Integration
              Principals"
audit:        principal registered / principal deactivated
```

#### Delete Semantics for System / Integration Principals (`§35`)

A `system_principals`/`integration_principals` catalog row is never destructively deleted while a
`principals` row (or any assignment referencing that `principals` row) still exists for it — the
same "never hard-delete once referenced" rule already applied to every other table in this
specification. Retiring a discontinued scheduled job or a decommissioned integration sets
`deactivated_at` (a permanent, one-way marker — no automatic reactivation) and, per "Principal
Lifecycle," the linked `principals` row's own `disabled_at` is set in the same transaction; neither
row is ever deleted, so no orphan canonical Principal can result from retiring a system/integration
identity. This is an engineering-integrity rule, not a new Human Decision.

### No changes to `users`

No `role`, `role_id`, `permission`, `is_admin`, `is_super_admin`, `financial_authority`,
`partner_authority`, `fundraiser_authority`, or `beneficiary_approval` column is added to `users`
(§22/§47) — every authorization fact lives in the normalized tables above; `users.id` is referenced
only indirectly, via `principals.human_user_id`.

---

## Temporal Authorization

`starts_at`/`ends_at`/`revoked_at` are included on BOTH assignment tables (§24) because:

```
Business Authority Assignments are explicitly "for a bounded effective period" per
  BUSINESS-AUTHORITY-MODEL.md's own conceptual structure — this is not invented, it is restated.
Role assignments carry the same shape for symmetry and because a Partner Representative's
  assignment is naturally bounded by their relationship with the Partner (a later domain concern,
  but the column exists now so that stage does not need a schema migration to add temporal bounds
  retroactively).
```

No numeric default duration, grace period, or renewal policy is invented — `ends_at` is nullable
(open-ended by default) unless a later domain stage's own specification requires bounding it.

Historical reproducibility: an assignment row is NEVER edited to change WHO it was for, WHAT
Role/Authority it granted, or WHAT scope it covered — only `ends_at`/`revoked_at`/
`revoked_by_principal_id` may be set (once). A changed assignment is always a NEW row plus a
revocation of the old one, never
a mutation in place — this mirors IMP-002's own EmailChangeRequest immutable-except-terminal-
columns pattern and satisfies `DATABASE-INVARIANTS.md` "Policy/version historical reproducibility."

---

## Concurrency

**Stable lock order (`IMP003-REAUDIT-M04`/`§24`-`§30`) — the ONE canonical sequence used by EVERY
RBAC write path without exception; no other section defines a competing order:**

```
1. Grantor Principal: lock the canonical `principals` row for the ACTING (granting/revoking)
   Principal (SELECT ... FOR UPDATE).
2. Target Principal: lock the canonical `principals` row for the Principal being granted to/
   revoked from. If grantor and target are the SAME principal (relevant only for the narrow,
   still-DENIED self-escalation attempt path, which is rejected before any mutation — see
   "Self-Escalation Protection" — this lock step still runs so the rejection itself is evaluated
   under a consistent lock), lock that ONE row once, not twice. If grantor and target are
   DIFFERENT principals, lock their `principals` rows in stable ASCENDING principal_id (primary
   key) order — never in "grantor first" or "target first" order based on role — so that two
   concurrent transactions naming the same two principals in opposite grantor/target roles cannot
   deadlock against each other.
3. Concrete Scope Target (where the scope type requires one): resolve scope_type -> resolve the
   owning domain's concrete table/model -> SELECT ... FOR UPDATE that concrete target row (per
   "Scope Target Integrity"'s Polymorphic Resolver Contract) -> verify it exists and its lifecycle
   permits assignment. Skipped entirely for GLOBAL_PLATFORM/ORGANIZATION/OWN (no concrete row).
4. Existing Assignment / Active-Slot Parent: inspect the current active assignment (via
   `active_assignment_key`, see "Scope Uniqueness Normalization") for this exact (principal,
   role-or-authority-type, scope) tuple.
5. Mutation: create the new assignment (or revoke the existing one, or both in the renewal/
   replacement case — see below) — the database's own unique index on `active_assignment_key` is
   the final, race-safe authority, not merely the step-4 read.
6. Commit.
```

Steps 1-2 additionally validate the grantor's OWN authorization to perform the write (holds the
required `rbac.*` permission + ELEVATED assurance) and every Self-Escalation Protection invariant,
under the same locks — a rejected self-escalation attempt never proceeds to step 3.

Applied per write path:

```
Role assignment vs revocation (same principal+role+scope):  the stable lock order above; the
  UNIQUE INDEX on `active_assignment_key` (not an application-level check alone) is what makes
  step 5 race-safe under InnoDB — a concurrent second INSERT attempting the same active tuple
  fails atomically at the database, which the assignment service surfaces as an ordinary
  application-level conflict (never silently ignored, never converted into a duplicate grant).
Scope assignment vs revocation:      same pattern (scope lives on the same assignment row, so
  this is the same lock/uniqueness key, not a separate mechanism) — additionally serialized
  against the concrete scope target's own delete/deactivate path via step 3's lock (see
  "Scope Target Integrity").
Business Authority assignment vs revocation:  identical pattern, against `authority_assignments`.
Renewal/replacement of an expired-but-unrevoked assignment:  steps 4-5 become "revoke the existing
  row, THEN insert the new row," both inside the SAME transaction opened after steps 1-3's locks —
  never two separate transactions, so no window exists where neither row is "active" nor where
  both could transiently coexist as active (see "Scope Uniqueness Normalization" for why this is
  necessary: expiration alone does not release the uniqueness slot).
Scope target deletion/deactivation vs scope assignment: the owning domain's delete/deactivate path
  locks the SAME concrete target row (step 3's lock) before proceeding — whichever transaction
  (a new assignment being created, or the target being deleted/deactivated) acquires that lock
  first completes fully before the other is allowed to proceed; the loser either sees the target
  already gone (assignment creation is REJECTED) or sees an active assignment already referencing
  it (deletion is RESTRICTED/blocked per the domain's own chosen deletion-protection rule) — never
  an orphaned assignment referencing a target that vanished mid-transaction.
Authorization evaluation vs revocation (a request is mid-evaluation when a revocation commits):
  the evaluator reads assignment rows FRESH per request (no long-lived cross-request cache is the
  default — see "Cache") — a revocation that commits before evaluation's read is honored
  immediately; one that commits mid-request-after-the-read is honored on the NEXT request, which
  is the same acceptable latency window IMP-002 already accepts for its own session-based
  Security Restriction check (re-checked per request via middleware, not sub-request-atomic).
First Super Admin canonical assignment (the Bridge):  locks the resolved bootstrapped identity's
  `principals` row FIRST (creating it if it does not yet exist, idempotently), THEN re-validates
  guard (1b) — "no existing `super_admin` Role assignment" — under that lock, exactly like Q25's
  own `super_admin_bootstraps` unique-constraint guard, for the same "concurrent double-invocation
  yields exactly one effective canonical Role assignment, never two, never broadened" reason (see
  "Super Admin Canonical Authorization"). The Bridge has no "grantor principal" in the ordinary
  sense (it is not a Principal acting through the authorization service), so it collapses steps
  1-2 into locking only the one target `principals` row.
Canonical User Deletion Transaction (`IMP003-REAUDIT2-M01`/`M02`):  this lifecycle path has no
  separate "grantor" — it locks the `users` row FIRST, then the linked `principals` row (a
  specialization of the general order above: there is only ONE principal involved here, not a
  grantor/target pair, so there is nothing to order by ascending PK against), revokes every active
  assignment referencing that principal (the same "existing assignment" step, applied in bulk
  rather than to one tuple), nulls `human_user_id`/sets `tombstoned_at` in one statement, THEN
  deletes the `users` row, all inside the SAME transaction, committing once at the end — see
  "Principal Lifecycle > Canonical User Deletion Transaction" for the full 8-step sequence and its
  rollback behavior. This does not conflict with the general Grantor/Target Principal ordering
  above; it is simply the specialization of that order for a write path with exactly one principal
  and no distinct grantor.
```

### Concurrency Test Contract (`§31`, conceptual — mirrors the Test Contract's posture)

```
a scope-assignment transaction has locked the concrete target row, and a concurrent scope-target
  deletion/deactivation attempt is made -> the deletion attempt SERIALIZES behind the assignment
  transaction (blocks until it commits or rolls back), never proceeds concurrently against the
  same row
scope target deletion/deactivation commits FIRST -> a subsequent assignment-creation attempt
  against that now-inactive/gone target is REJECTED (never silently creates an orphan reference)
scope assignment creation commits FIRST -> a subsequent destructive deletion attempt against that
  target is BLOCKED/RESTRICTED (or, for a soft-deactivation domain, the target becomes inactive
  and evaluation against the still-existing assignment DENIES) per the owning domain's chosen
  deletion-protection rule — never a scenario where both "the assignment is still active" and
  "the target no longer exists/is inactive" are simultaneously true without evaluation denying
```

Database deadlocks are never converted into a business state (`§30`/`§49`) — an unexpected
InnoDB deadlock (which the single consistent lock order above is specifically designed to make
rare) propagates as an ordinary database exception for the caller to retry, exactly like every
other transactional write in this repository; it is never silently reinterpreted as, for example,
`CONFLICTED` or `INVALID`, which remain reserved for the specific, already-defined business
outcomes IMP-002 established for its own domain.

**No MySQL runtime concurrency is claimed as verified by this specification** — this is a
readiness/specification document; concurrency correctness will be verified by the same
static-review-plus-deterministic-test-plus-documented-limitation pattern IMP-002's Remediation
Passes 1-2 already established and that this repository's governance has already accepted (see
`docs/audits/IMP-002-IMPLEMENTATION-REMEDIATION-2.md` "MySQL").

---

## Shared Hosting

IMP-003 requires nothing beyond what IMP-001/IMP-002 already established: Laravel + MySQL +
database-backed cache/session/queue. No Redis, no Supervisor, no Docker, no WebSocket runtime, no
external policy server. If authorization caching is ever added (see "Cache"), it MUST use the
same database/file cache store already configured — never a new mandatory infrastructure
dependency.

---

## Cache

No authorization cache is REQUIRED by this specification (correctness is prioritized over
premature optimization, per §38). IF a later implementation pass introduces one (e.g. caching a
Principal's resolved effective-permission set for the lifetime of a single request only — not
across requests), it MUST satisfy:

```
Cache is never the source of truth — the DB tables above always are.
Cache scope is REQUEST-LOCAL only (e.g. a single resolved-once-per-request value object) unless
  an explicit, short, TTL-bound cross-request cache is later justified AND a revocation path is
  proven to invalidate it synchronously (not "wait for TTL") — no such cross-request cache is
  specified or authorized by this document.
Shared-hosting-compatible store only (database/file), consistent with "Shared Hosting" above.
```

---

## Audit Contract (emission points only — IMP-004 owns the sink)

Restating the pattern IMP-002 already established (`IdentityAuditLogger`, a channel-based emission
abstraction pending IMP-004's real sink) — IMP-003 emits, via the SAME or an equivalent logger
abstraction, at minimum:

```
role_assigned / role_revoked
permission_granted_to_role / permission_revoked_from_role
role_registered / role_retired (system role catalog changes)
permission_registered / permission_deprecated
scope_assigned / scope_revoked         (in practice: identical events to role_assigned/
                                        authority_assigned, since scope travels WITH the
                                        assignment row — no separate scope-only event exists)
business_authority_assigned / business_authority_revoked
authority_type_registered
super_admin_canonically_authorized     (the one-time Bridge event — see "Super Admin Canonical
                                        Authorization")
authorization_denied_security_critical (emitted only for security-CRITICAL denials — e.g. a
                                        self-escalation attempt, a Financial Authority check
                                        failure on an actually-attempted financial action — not
                                        for ordinary/expected DENYs like an anonymous visitor
                                        hitting a login-required page, to avoid audit-log noise
                                        drowning out signal)
```

Never logged: any raw permission-check internals that would leak a target resource's sensitive
field values; any credential; anything IMP-002's own `IdentityAuditLogger` already prohibits.

---

## Security Threat Model

```
Privilege escalation (vertical):    Self-Escalation Protection (above); every Role/Permission/
                                     Authority write requires ELEVATED assurance + the specific
                                     rbac.* permission; no implicit allow.
Privilege escalation (horizontal):  covered by Scope + Ownership terms — a Partner Representative
                                     for Partner A can never satisfy PARTNER scope for Partner B's
                                     resources; enforced at BOTH query and resource level.
IDOR:                                resource-level Policy re-check (`resourceMatchesScope()`) is
                                     mandatory even when a list endpoint already scoped the query —
                                     defense in depth against a directly-addressed resource ID.
Scope bypass:                        "Contexts from unrelated assignments MUST NOT be unioned
                                     automatically" (DATA-SCOPE-MODEL.md) — the evaluator resolves
                                     scope per REQUESTED capability + target resource, never a
                                     Principal's "any assignment anywhere" superset.
Partner cross-access:                see Horizontal, above — PARTNER scope is bound to a specific
                                     Partner id per assignment.
Fundraiser cross-access:             same pattern, FUNDRAISER scope.
Donor data exposure:                 OWN scope + Object Access != Full Field Disclosure — a Donor
                                     permission never implies another Donor's data, and even own-
                                     data field sets are governed by explicit field-transformation
                                     policy, not a blanket serializer.
Stale authorization cache:           addressed by "Cache" (no cross-request cache by default;
                                     revocation is honored on the very next request otherwise).
Role mutation race / revocation race: addressed by "Concurrency" (consistent Principal ->
                                     Assignment lock order, unique-active-assignment constraint).
Confused deputy:                     a System/Integration Principal never inherits or is granted
                                     the authority of the human Principal who triggered the job
                                     that queued it — the job's OWN Authority Assignment (or lack
                                     thereof) governs what it may do, per "System Principal".
Background-job over-privilege:       System Principal is narrowly, explicitly assigned per job
                                     family — never a blanket/global Role.
Super Admin overreach:               "Super Admin != automatic Financial Authority" enforced
                                     structurally (Business Authority is never implied by Role);
                                     the Bridge grants ONLY the `super_admin` Role, never any
                                     Authority Type — including to itself (IMP003-READY-B01).
Self-grant / self-escalation:        absolute, unconditional DENY for self-role-assignment,
                                     self-scope-expansion, self-business/financial-authority-
                                     grant, and self-approval-step-assignment, for every Principal
                                     including Super Admin and the Q25-bootstrapped identity — no
                                     "first grant has to come from somewhere" exception exists
                                     anywhere in this model (IMP003-READY-M01/B01).
Financial authority conflation:      Business Authority (step 6) is evaluated as a term
                                     structurally separate from Permission (step 3) — a caller
                                     cannot satisfy the financial-authority check merely by having
                                     a permission that happens to be named similarly.
```

---

## Test Contract

Restates and extends `docs/00-governance/DEFINITION-OF-DONE.md`'s "Mandatory RBAC Tests" into the
concrete contract IMP-003's implementation must satisfy (test file organization is an
implementation-time engineering choice, not fixed here). `IMP003-READY-M06`: every term of the
AND-chain (including the Step 0 "Applicable Subject Context Resolution" precondition and the
Resource State / Approval State terms this specification only defines as contracts) now has an
explicit negative test, and System/Integration Principal least-privilege, invalid-principal, and
invalid-scope-reference tests are added.

### Authentication (`§22`)

```
unauthenticated request -> DENY (before any other term is evaluated)
```

### Applicable Subject Context (`§23`)

```
authenticated + Principal fails to resolve to an active `principals` row -> DENY
authenticated + Requested Capability is not a recognized/registered Permission code -> DENY
authenticated + Target Resource does not exist / cannot be resolved -> DENY
(all three: DENY occurs at Step 0, before Permission/Scope/Ownership are ever evaluated)
```

### Permission (`§24`, mandatory per `IMP003-REAUDIT-M02`)

```
permission missing on every held Role                        -> DENY
permission present via at least one held, active Role          -> evaluation continues
permission present only via a REVOKED/expired Role assignment  -> DENY (not "present")
an IMP-003-protected capability with an UNRESOLVABLE/unregistered required Permission code
  -> DENY (Permission is never optional; there is no "no permission required" IMP-003 capability)
a Donor/Fundraiser's baseline IMP-002 account-security operation (login, password change, MFA,
  email verification/change) is NOT routed through the IMP-003 evaluator at all — asserted by a
  boundary test confirming none of these IMP-002 controllers/routes invoke the IMP-003
  authorization service, rather than asserting they pass with a trivially-satisfied Permission
  (IMP003-REAUDIT-M02 regression check — the earlier "trivially satisfied" framing must not
  return)
```

### Scope (`§25`)

```
correct permission + no active scope assignment covering the resource -> DENY
correct permission + scope assignment for a DIFFERENT target of the same scope type -> DENY
correct permission + scope assignment covering the resource -> evaluation continues
scope_type outside the 10 canonical taxonomy values (unknown scope type) -> DENY
scope assignment whose scope_id does not resolve to an existing/active target row (invalid scope
  target) -> DENY, AND the write that would have created such an assignment is REJECTED outright
  (tested at the write path, not only the read/evaluation path)
```

### Ownership (`§26`)

```
correct role/permission/scope + ownership resolver says NOT owner -> DENY
correct role/permission/scope + ownership resolver returns unresolved/unavailable (e.g. the
  resolver throws, times out, or cannot determine a definitive answer) -> DENY — never fail-open;
  an indeterminate ownership result is treated identically to a negative one
correct role/permission/scope + ownership resolver confirms owner -> evaluation continues
```

### Business Authority (`§27`)

```
permission + scope + ownership, action requires Business Authority, none assigned -> DENY
permission + scope + ownership + an assigned but WRONG Authority Type -> DENY
permission + scope + ownership + correct, active Authority Assignment -> evaluation continues
permission + scope + ownership + Authority Assignment that has EXPIRED (ends_at in the past)
  -> DENY
permission + scope + ownership + Authority Assignment that is REVOKED -> DENY
```

### Resource State (`§28`)

```
every prior AND-term satisfied, BUT the owning domain's resource-state predicate returns invalid
  -> DENY
(domain-neutral: IMP-003 tests this against its OWN injectable-predicate contract using a
  synthetic always-false predicate, since it owns no concrete domain resource state itself; a
  later domain stage substitutes its real predicate and inherits this same test shape)
```

### Approval State / Step Assignment (`§29`)

```
principal has role/permission/scope/ownership/business-authority satisfied, BUT is not assigned
  to the specific required approval step -> DENY
(same domain-neutral contract-test posture as Resource State — tested here against the
  `ApprovalCandidateResolver` interface with a synthetic resolver; a real Approval module
  implementation inherits this test)
```

### Assurance (`§30`)

```
action requiring ELEVATED + STANDARD session -> DENY
action requiring ELEVATED + ELEVATED session -> evaluation continues
action NOT requiring ELEVATED + STANDARD session -> evaluation continues (assurance term is
  satisfied trivially when no elevation is required)
```

### Security Restriction (`§31`)

```
principal lifecycle DISABLED -> DENY (before any Role/Permission evaluation runs)
principal security_restriction SUSPENDED -> DENY (before any Role/Permission evaluation runs)
principal security_restriction holds an unrecognized/unexpected value (data drift / future enum
  value not yet handled) -> DENY, never treated as equivalent to NONE
```

### System Principal Least Privilege (`§32`)

```
System Principal seeded with capability A (a specific Role/Authority scoped to job family A)
  attempting capability B (an unrelated job family's action) -> DENY
System Principal action never inherits the authority of the human Principal whose request queued
  the job that is now running as that System Principal (confused-deputy regression test)
```

### Integration Principal Least Privilege (`§33`)

Integration Principal remains IN SCOPE (this specification's "System Principal / Integration
Principal" section defines both, per SECURITY-ARCHITECTURE.md naming both explicitly) — its test
is NOT deferred:

```
Integration Principal (e.g. a specific payment-provider webhook identity) scoped to its own
  narrow, declared capability, attempting an unrelated domain capability -> DENY
Integration Principal attempting to post directly to Ledger (bypassing the Authorized Financial
  Consequence chain) -> DENY (docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md)
```

### Invalid Principal (`§34`)

```
an assignment write referencing a `principal_id` that does not resolve to an existing
  `principals` row -> REJECTED at the database level (real FK violation, not merely an
  application-level check) — IMP003-READY-M04
```

### Attribution (`IMP003-REAUDIT2-M03`, `§22`)

```
an authorization mutation's grantor `assigned_by_principal_id`/`granted_by_principal_id` resolves
  to an existing `principals` row -> the write PASSES (assuming every other check also passes)
an authorization mutation referencing a grantor `principal_id` that does not resolve to an
  existing `principals` row -> REJECTED (real FK violation)
a human grantor's OWN User is later deleted (Canonical User Deletion Transaction runs) -> the
  grantor's `principals` row survives, TOMBSTONED — every `role_permissions`/
  `principal_role_assignments`/`authority_assignments` row that grantor ever created or revoked
  still resolves its `..._by_principal_id` to that (now-tombstoned) row; no attribution becomes
  orphaned or unresolvable
```

### Tombstoned Principal (`IMP003-REAUDIT-M01`, `§9`)

```
active Human Principal + valid, live User -> eligible for further authorization evaluation
  (Step 0 resolves successfully; evaluation proceeds to Permission/Scope/etc.)
Human Principal with human_user_id NULL + tombstoned_at set -> DENY (Step 0 fails deterministically
  — never treated as "no restriction")
attempting a NEW Role assignment targeting a TOMBSTONED principal -> REJECTED at the write path
  (the assignment-creation service refuses a tombstoned target, not merely evaluation-time DENY)
attempting a NEW Authority assignment targeting a TOMBSTONED principal -> REJECTED, same rule
a TOMBSTONED principal attempting to act as GRANTOR of any Role/Authority assignment -> REJECTED
  (a tombstoned principal can never authorize anything, including as the acting party)
tombstoning a principal that currently holds active Role/Authority assignments -> those
  assignments are revoked as PART OF the same tombstone transaction (never left dangling as
  "active" against a tombstoned target)
```

### Canonical User Deletion (`IMP003-REAUDIT2-M01`/`M02`, `§13`/`§14`)

```
a direct `DELETE FROM users` for a User whose linked `principals` row is still LIVE (non-
  tombstoned) -> REJECTED by the FK (RESTRICT/NO ACTION) — the database itself blocks any
  deletion path that bypasses the Canonical User Deletion Transaction
the Canonical User Deletion Transaction, run against a User with active Role/Authority
  assignments and a live linked principal -> SUCCEEDS: the User row is gone, the principals row
  is TOMBSTONED (tombstoned_at set, human_user_id null), every previously-active assignment
  referencing that principal is now revoked, and authorization evaluation against that principal
  denies
a forced failure injected AFTER the assignment-revocation and tombstone-mutation steps but BEFORE
  the transaction commits (e.g. the `DELETE FROM users` step itself is made to fail) -> the ENTIRE
  transaction rolls back: the User row still exists, the principals row is still LIVE (not
  tombstoned, human_user_id still populated), and every assignment that would have been revoked is
  still active exactly as it was before the attempt (mandatory test — mirrors the failure-
  injection pattern IMP-002's own Remediation Pass 2 already established for its own security
  transitions)
```

### Invalid Scope Reference (`§35`)

```
an assignment write whose scope_id does not resolve to an existing, active target row for its
  scope_type -> REJECTED at write time (transactional validation, see "Scope Target Integrity")
a target row that is later deleted/deactivated after a valid assignment was created -> subsequent
  authorization evaluation against that assignment DENIES (never falls back to "unrestricted")
```

### Super Admin (`§37`)

```
Super Admin role + missing financial_approver authority -> financial action DENY
Super Admin role + assigned financial_approver authority (granted by a DISTINCT Principal),
  correct scope -> financial action evaluation continues (subject to remaining AND-terms)
Super Admin (including the Q25-bootstrapped identity immediately after the Bridge runs) attempts
  to self-grant ANY Authority Type (financial or otherwise) -> DENY, unconditionally
  (IMP003-READY-B01 — no exception, including immediately post-Bridge)
```

### Fundraiser

```
self-registered Fundraiser identity + no Authority Assignment for the (later-domain-defined)
  fundraiser-operating capability -> fundraiser-privileged action DENY
self-registered Fundraiser identity has NO Role at all immediately after registration
  (IMP003-READY-M05 regression check — asserts the auto-assignment bug does not return)
```

(The first of these is a CONTRACT statement for the later Fundraiser-domain stage to implement
against — IMP-003 itself has no fundraiser-privileged action to test, since it owns no Fundraiser
domain logic; the test is specified here so that stage's Definition of Ready inherits it. The
second is directly testable by IMP-003 itself, against IMP-002's real registration flow.)

### Partner

```
Partner Representative A (PARTNER scope = Partner 1) -> Partner 2's protected resource -> DENY
```

(Contract for the Partner-domain stage, same posture as Fundraiser above.)

### Invitation Acceptance Authorization Boundary (`IMP003-REAUDIT-M03`, regression check)

```
Partner Representative / Internal Administrative Identity / Super Admin invitation accepted
  -> the resulting identity has NO Role, NO Permission, NO Scope, NO Business Authority, and NO
  Financial Authority immediately after acceptance (directly testable by IMP-003 itself, against
  IMP-002's real `InvitationService::accept()` — asserts the earlier "role assigned at invitation
  acceptance" framing does not return)
the SAME accepted identity, after a SEPARATE, explicit Authorization Transition is performed by an
  already-authorized Principal -> now holds exactly the Role/Scope/Authority that transition
  granted, no more (proves the transition is additive/explicit, not implicit/automatic)
```

### Donor

```
Donor A -> Donor B's private resource (OWN scope mismatch) -> DENY
Donor identity has NO Role at all immediately after registration (IMP003-READY-M05 regression
  check, directly testable by IMP-003 itself)
```

### API

```
Principal permission set INTERSECT token capability set -> only the intersection is effective;
  a permission present in the Principal's set but ABSENT from the token's capability set -> DENY
  for that specific action even though the Principal alone would be authorized
```

(Contract for the future API-token-implementing stage.)

### Revocation

```
authorization (role/authority/scope) revoked -> the VERY NEXT request evaluating that
  permission/authority is denied (no stale-allow window beyond "already in flight before
  revocation committed" — see "Concurrency")
```

### Default

```
unknown/unresolved scope type, unresolvable principal, or any evaluator internal error ->
  DENY (never a silent ALLOW; an internal error is NOT translated into permissive behavior)
```

### Self-Escalation (`§36`, IMP-003-specific, beyond the DoD's generic list)

```
Principal (of ANY kind, holding ANY Role/Permission, including GLOBAL_PLATFORM rbac.role.assign)
  attempting to assign a Role to THEMSELVES via the ordinary authorization service -> DENY,
  unconditionally (IMP003-READY-M01 — the earlier "GLOBAL_PLATFORM exception" is removed; this
  test specifically re-proves it stays removed)
Principal attempting to widen their OWN existing assignment's scope (e.g. PARTNER -> ORGANIZATION)
  -> DENY, treated identically to a new self-assignment
Principal attempting to assign a Permission to a Role they hold, where they do not already
  effectively hold that Permission through another active Role -> DENY
Principal attempting to create a Business/Financial Authority Assignment where principal_id
  resolves to themselves -> DENY, unconditionally, with NO Super Admin/Bridge exception
Principal attempting to assign themselves as the authorized actor for a privileged approval step
  -> DENY
```

---

## Database Test Contract

```
fresh migration (`IMP003-REAUDIT2-M04`): running every IMP-003 migration, in the exact "Migration
  Order" sequence specified above, against a clean/empty MySQL 8 database completes without error
  — this is what catches a referenced-table-does-not-exist ordering mistake, an incorrect FK
  direction, or a CHECK constraint referencing a not-yet-created column, before it becomes a real
  deployment failure
direct User deletion blocked (`IMP003-REAUDIT2-M02`): a raw `DELETE FROM users` for a User whose
  linked `principals` row is still LIVE is rejected by the FK (RESTRICT/NO ACTION) — confirmed
  directly, not merely assumed from the FK's declared action
canonical deletion transaction (`IMP003-REAUDIT2-M01`): running the full Canonical User Deletion
  Transaction against a User with active Role/Authority assignments succeeds atomically — User
  removed, principal TOMBSTONED, human_user_id NULL, every previously-active assignment now
  revoked, historical rows preserved, grantor attribution on those historical rows still resolves
canonical deletion rollback (`IMP003-REAUDIT2-M01`): a forced failure late in the same transaction
  (e.g. the final `DELETE FROM users` statement itself fails) rolls back EVERY step — the User
  still exists, the principal is still live (not tombstoned), and every assignment that would
  have been revoked is unchanged
principals CHECK constraint (`IMP003-REAUDIT-M01`, corrected): a 'human' + non-tombstoned row
  REQUIRES human_user_id NOT NULL (rejected otherwise); a 'human' + tombstoned row REQUIRES
  human_user_id NULL (rejected if still populated); 'system'/'integration' rows REQUIRE
  human_user_id and tombstoned_at both NULL — all four branches tested directly, proving the
  contradiction the reaudit found (NOT NULL required unconditionally vs. becoming null after
  deletion) no longer exists
principals unique constraints: each of human_user_id/system_principal_id/integration_principal_id
  enforced independently (no duplicate authorization identity for the same User/System/Integration
  source); a tombstoned row's NULL human_user_id does NOT block a genuinely different, later User
  from obtaining its own principals row (MySQL's NULL-never-collides behavior confirmed to be the
  WANTED behavior here, not an oversight)
principals FK integrity: principal_role_assignments.principal_id and
  authority_assignments.principal_id both REJECT a reference to a nonexistent principals.id (real
  FK violation, tested directly — not merely "the application happened not to construct one") —
  IMP003-READY-M04
orphan/tombstoned principal denied: a `principals` row that is tombstoned (human_user_id null)
  still exists (not hard-deleted) but authorization evaluation against it is proven to DENY, never
  ALLOW; a write attempting to target it with a NEW assignment, or to use it as a grantor, is
  proven to be REJECTED
scope target lock (`IMP003-REAUDIT-M04`): a scope-assignment-creation transaction that has locked
  a concrete target row is proven to block a concurrent target deletion/deactivation attempt
  against that same row until the first transaction commits or rolls back (tested via the
  repository's standard technique for simulating lock contention — e.g. two connections/
  transactions in a controlled test, or a documented static-review-plus-deterministic-logic-test
  limitation consistent with IMP-002's own accepted MySQL-concurrency posture where genuine
  multi-connection testing is not available)
roles.code unique constraint enforced
permissions.code unique constraint enforced
active_assignment_key generated column: proven to be NULL for any revoked row (regardless of how
  many revoked rows share the same underlying principal/role-or-authority/scope tuple — no unique
  violation among revoked rows) and to be a concrete, colliding value for two attempted
  simultaneously-ACTIVE rows with the same tuple (the second INSERT is rejected by the unique
  index on active_assignment_key, not by an application-level pre-check alone) — IMP003-READY-M03
no NOW()-dependent constraint: a direct test confirms the generated column's definition contains
  no reference to NOW()/CURRENT_TIMESTAMP (a static assertion against the migration/schema, since
  MySQL would reject such a column definition outright if attempted)
revocation: revoking an assignment sets revoked_at/revoked_by_principal_id without deleting the
  row; a revoked row is excluded from "active assignment" queries used by the evaluator, and its
  active_assignment_key is confirmed NULL
temporal: an assignment with ends_at in the past is excluded from evaluation's "active" result
  even without an explicit revocation, AND is confirmed to still occupy its uniqueness slot (a
  second identical assignment attempt is still rejected until the expired row is explicitly
  revoked/superseded) — proves expiration and uniqueness are correctly decoupled
renewal transaction: creating a new assignment for a (principal, role-or-authority, scope) tuple
  that currently has an expired-but-unrevoked row is proven to, within one transaction, revoke the
  old row and insert the new one — never leaving two simultaneously non-revoked rows, never
  leaving a gap where neither is active
role_permissions history: revoking a Role's Permission never hard-deletes the row (revoked_at/
  revoked_by_principal_id set instead); the SAME role/permission pair can be re-granted afterward
  as a NEW row while the original revoked row remains queryable — IMP003-READY-m01
Super Admin Bridge: running the bridge command TWICE is refused the second time (guard #2 in
  "Super Admin Canonical Authorization"); running it when NO bootstrap row exists is refused
  (nothing to bridge); running it when a `super_admin` Role assignment ALREADY exists is refused
  even if a NEW/different bootstrap row somehow existed (defense in depth for guard #2); the
  resulting `authority_assignments` table contains ZERO rows for the bridged identity immediately
  after the Bridge runs (IMP003-READY-B01 regression check)
scope CHECK constraint: an insert attempt with scope_id NON-NULL for GLOBAL_PLATFORM/ORGANIZATION/
  OWN, or NULL for any other scope_type, is rejected at the database level — IMP003-READY-M02
no authorization columns on `users`: re-run IMP-002's own SchemaBoundaryTest-equivalent assertion
  after IMP-003's migrations to confirm no regression was introduced to that table
```

---

## Package Decision

**RBAC Package Required: NO.**

Evaluated per §44:

```
Laravel native Policies/Gates:  the framework capability for the ENFORCEMENT/evaluation layer
  (where the AND-chain sequencing lives) — used directly, per CHANGE-CONTROL.md's explicit
  instruction to prefer framework capabilities ("policies" named there verbatim).
Custom normalized permission model: REQUIRED regardless of package choice, because the locked
  model needs domain-aware Scope, Business/Financial Authority, Approval-primitive integration,
  and Authentication-Assurance/Security-Restriction integration that NO general-purpose RBAC
  package expresses natively (§44's own test: "If a package cannot express: domain-aware scope,
  business authority, financial authority, approval assignment, assurance, security restriction —
  it may only serve as a low-level permission primitive").
Maintained RBAC package (e.g. spatie/laravel-permission, evaluated as the most common candidate
  in the Laravel ecosystem): would only replace the `roles`/`permissions`/`role_permissions`/
  `principal_role_assignments` layer — roughly the SMALLEST, least complex part of this contract
  — while contributing NOTHING toward Scope/Business-Authority/Approval/Assurance/Restriction
  integration, which is the majority of this specification's actual complexity and risk surface.
  Adopting one would ALSO require conforming its assignment-model shape (typically no built-in
  scope/temporal-assignment/authority-assignment concept) to this repository's locked
  requirements anyway, likely via the same custom tables this document already specifies, making
  the package redundant rather than simplifying.
```

**Recommendation: no third-party RBAC package.** Laravel Policies/Gates (framework, zero new
dependency) for enforcement + the custom schema above (unavoidable, since no locked document nor
any known package expresses the full AND-chain) for storage. This keeps `composer.json` free of
a new runtime dependency for this stage, consistent with `CHANGE-CONTROL.md`'s "Does Laravel
already provide this?" test and IMP-002's own precedent (using framework capabilities — Hash,
Password broker, RateLimiter, MustVerifyEmail — before any new package, and installing exactly one
new dependency, `pragmarx/google2fa`, only because NO framework capability existed for TOTP and a
locked requirement (Q23/M04) made a maintained library mandatory). No comparable "no framework
capability exists and a locked requirement mandates it" condition applies to RBAC storage itself.

---

## Migration Order (`IMP003-REAUDIT2-M04` — FK-valid; distinct from Implementation Coding Order below)

Root cause of the finding: the earlier draft's step 1 listed `principals` before
`system_principals`/`integration_principals`, while `principals.system_principal_id`/
`integration_principal_id` are FKs INTO those two catalogs — the referenced tables were ordered
AFTER the table that references them, which a real migration run would reject outright
(`referenced table does not exist`). **Corrected — the one rule this order follows throughout:
a referenced table MUST exist before the referencing FK table is created.**

```
0.  `users` (prerequisite — already exists from IMP-002; NOT created or altered by IMP-003 beyond
    being the FK target for principals.human_user_id; no IMP-002 identity semantics are reopened)

1.  roles                     (no FK dependency)
2.  permissions                (no FK dependency)
3.  authority_types             (no FK dependency)
4.  system_principals           (no FK dependency)
5.  integration_principals       (no FK dependency)

6.  principals                 (FK -> users.id, system_principals.id, integration_principals.id —
    ALL FOUR referenced tables (users, system_principals, integration_principals — plus its own
    CHECK constraint referencing no other table) now exist)

7.  role_permissions            (FK -> roles.id, permissions.id, principals.id x2 for
    granted_by_principal_id/revoked_by_principal_id — requires roles, permissions, AND
    principals to already exist, i.e. AFTER step 6, not merely after steps 1-2)
8.  principal_role_assignments  (FK -> principals.id, roles.id, principals.id x2 for
    assigned_by_principal_id/revoked_by_principal_id)
9.  authority_assignments       (FK -> principals.id, authority_types.id, principals.id x2 for
    assigned_by_principal_id/revoked_by_principal_id)
```

Dependency graph (arrows point FROM a referenced table TO the table whose FK depends on it):

```
users ────────────────────────────────┐
                                       ▼
system_principals ──────┐         principals
integration_principals ─┤              │
                         └─────────────┤
                                       │
roles ──────┐                         │
permissions ┤                         │
             ▼                        ▼
        role_permissions      principal_role_assignments
                                       │
authority_types ───────────────────────┤
                                       ▼
                            authority_assignments
```

`principals` is the single common dependency every assignment table needs (both as the target
of `principal_id` and, for `role_permissions`/`principal_role_assignments`/
`authority_assignments`, as the target of their `..._by_principal_id` attribution columns per
"Attribution") — it must be created immediately after its own three prerequisites
(`users`, `system_principals`, `integration_principals`) and strictly before every table that
attributes an action to a Principal.

### Fresh-Database Migration Contract

Running every migration above, in this exact order, against a clean/empty MySQL 8 database MUST
complete without error — this is itself a mandatory test (see "Test Contract"/"Database Test
Contract"): it is what actually catches a referenced-table-does-not-exist ordering mistake, an
incorrect FK direction, or a CHECK constraint referencing a column that does not exist yet at
that point in the migration sequence, before any of those become a runtime failure during a real
deployment.

## Implementation Order (for the future implementation-authorized pass)

`§29`: this is the CODING order (services/tests/etc.) — it is NOT the same sequence as the
Migration Order above, and does not need to be; schema creation (step 1 below) simply follows the
Migration Order wholesale as one unit.

```
1.  Database schema — apply the Migration Order above in full (users is already present; steps
    1-9 of that order create every IMP-003 table)
2.  Models (with the immutable-except-terminal-columns pattern IMP-002 established; the
    active_assignment_key generated column defined per "Scope Uniqueness Normalization")
3.  Permission Registry (code-defined) + idempotent seeder + Authority Type seeder (7 approved
    types) + System/Integration Principal seeders (empty catalog initially — later stages add
    their own rows)
4.  Principal lifecycle service (idempotent `principals` row creation/lookup per User/System/
    Integration source, per "Principal Lifecycle"; also implements the Canonical User Deletion
    Transaction)
5.  Principal Role/Authority assignment services (assign/revoke, with the stable lock order +
    active_assignment_key uniqueness + self-escalation rules specified above)
6.  ScopeResolver contract + the ONE concrete resolver IMP-003 owns (`OWN` against `User` itself)
7.  AuthorizationContext / evaluator service implementing the Step-0-plus-9-step AND-chain
8.  Base Policy class encapsulating the AND-chain sequencing for domain Policies to extend
9.  Assurance integration (declarative "requires ELEVATED" marking + evaluator wiring to IMP-002's
    AssuranceService)
10. Security Restriction integration (re-confirm IMP-002's User::canAuthenticate() at the start
    of every evaluation)
11. Super Admin Bridge command (the two-guard, one-time canonical-authorization command — grants
    ONLY the `super_admin` Role, per IMP003-READY-B01)
12. `identity.security.transition` permission wiring for IMP-002's deferred persistent-state
    transitions (ACTIVE<->DISABLED, NONE<->SUSPENDED) — the first CONSUMER of this whole system
13. Tests (per "Test Contract" and "Database Test Contract" above)
```

---

## Definition of Ready

```
[x] Objective clear
[x] Scope clear
[x] Out-of-scope clear
[x] Architecture known (docs/05-rbac/*, fully materialized, read directly)
[x] Business rules known (none invented; every Authority Type/scope/actor traced to a locked
    document)
[x] Security impact known (full threat model above)
[x] DB impact known (full contract above, no migration created)
[x] Acceptance criteria known (Test Contract + Database Test Contract above)
[x] No unresolved Human Decision (see "Human Decisions Required" below — none found)
```

**PASS.**

## Definition of Done (for the FUTURE implementation pass, not this document)

Restated from `docs/00-governance/DEFINITION-OF-DONE.md` — unchanged, not redefined here; this
specification's job is to make each checklist item concretely achievable against a real,
non-invented contract, which the sections above establish.

## Open Questions (not blocking; implementation-time engineering choices)

```
Exact Artisan command name for the Super Admin Bridge (not locked by any document; §35's own
  instruction: "exact command name is an implementation detail").
```

`IMP003-REAUDIT-m01`: this section previously ALSO listed "generated column vs. application-level
locking undecided" for the active-assignment uniqueness constraint, and "whether `role_permissions`
revocation should retain history" as open — both are REMOVED. Neither is actually open: Remediation
Pass 1 already selected and fully specified the normative design for both (the `active_assignment_key`
generated-column technique in "Scope Uniqueness Normalization + MySQL 8 Active-Assignment
Uniqueness," and durable `granted_at`/`revoked_at` history for `role_permissions`, respectively) —
leaving them listed here as "undecided"/"may be used" was stale and actively contradicted the
normative sections elsewhere in this same document, which is what this pass corrects. There is no
alternative implementation path for either; both are the SOLE normative design, not one option
among several.

The one remaining item above requires no Human Decision — it is a reversible, non-architecture-
affecting implementation detail per AGENTS.md "Before Coding" step 7 ("make the smallest coherent
change").

## Human Decisions Required

**NONE.** Every design choice in this document either (a) directly restates an already-locked
Level 1-3 document, or (b) is explicitly labeled `ENGINEERING CHOICE` with a justification that
does not touch business rules, financial semantics, legal/compliance policy, or the Human Decision
Register — per `docs/00-governance/IMPLEMENTATION-GOVERNANCE.md` "Human Approval Rule," none of
which applies to the choices made here.

**On `IMP003-READY-B01` specifically:** leaving the first (and every) Super Admin's Financial
Authority UNASSIGNED until a distinct, already-authorized Principal grants it, or until a later
stage specifies its own deterministic provisioning mechanism, is NOT a gap requiring a Human
Decision — it is the correct, safe DEFAULT-DENY outcome this specification's own locked invariant
already requires ("Super Admin != automatic Financial Authority"). Removing the earlier draft's
unauthorized self-grant exception does not need Human approval; it needed to simply not exist.
