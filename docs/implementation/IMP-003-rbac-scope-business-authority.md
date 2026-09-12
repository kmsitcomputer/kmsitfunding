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

| Actor | Identity Creation (IMP-002) | Portal | Default Role Model | Default Scope | Business Authority Boundary | Financial Authority Boundary | Assurance Notes |
|---|---|---|---|---|---|---|---|
| Donor | Self-registration (Q22) | `/donor/*` | `donor` role (system role, auto-assigned at registration — identity-level, no business meaning) | `OWN` | None by default | None | STANDARD for ordinary use; ELEVATED for account-security actions (IMP-002 owns those triggers) |
| Fundraiser | Self-registration (Q22); no automatic business authority | `/fundraiser/*` | `fundraiser` role (system role, auto-assigned at registration) | `OWN` until an Authority Assignment grants `FUNDRAISER`/`CAMPAIGN` scope by a later stage | **NOT granted by registration** — an approved-Fundraiser Authority Type (owned by the Fundraising & Attribution domain stage, registered into IMP-003's extensible Authority Type registry — see "Business Authority > Extensibility") is required before any fundraiser-operational capability | None by default | Same as Donor |
| Partner Representative | Invitation only (Q22) | `/partner/*` | `partner_representative` role, assigned at invitation acceptance by the inviting workflow (never automatically by IMP-002) | `PARTNER` scope, bound to the specific Partner the invitation was issued for | **NOT implied by role alone** — requires an active `PARTNER` scope assignment; Partner Verification authority (`partner_verifier`) is separate and not implied | None by default | STANDARD baseline; ELEVATED for partner-configuration-sensitive actions per later Partner-domain policy |
| Internal Administrative Identity | Invitation/provisioning only (Q22) | `/admin/*` | one or more internal operational roles (e.g. `finance_operator`, `beneficiary_operator`, `support_operator` — exact set is an later, domain-populated catalog, not invented here), assigned by an authorized Super Admin/authorized administrator, never self-assigned | `ORGANIZATION` or `ASSIGNED_WORK`, per assignment | None implied by the identity itself — each operational role's permissions are scoped narrowly per its own function | None implied — `financial_approver`/`refund_approver`/etc. are separate Authority Assignments | ELEVATED required for role/authority assignment actions (see "Self-Escalation Protection") |
| Super Admin | Provisioning only; first identity via Q25 CLI bootstrap | `/admin/*` | `super_admin` role — see "Super Admin Canonical Authorization" | `GLOBAL_PLATFORM` | Broad platform administrative capability by role/permission — but see next column | **NEVER automatic** — `financial_approver` etc. remain separate Authority Assignments even for Super Admin (RBAC-ARCHITECTURE.md: "Super Admin != automatic Financial Authority") | ELEVATED required for canonical role/authority assignment and any security-sensitive configuration change |
| Beneficiary | Governed by Q7 (mechanics deferred to Beneficiary & Distribution domain stage); IMP-002 does not create Beneficiary-specific identity mechanics beyond its general-purpose registration/invitation/provisioning primitives | Not yet routed | `beneficiary` role if/when a `User` is created for this actor (per Q7) | `OWN` / `BENEFICIARY_CASE` | Verification/eligibility authority (`beneficiary_verifier`) is separate, assigned only to authorized Operational Staff, never to the Beneficiary itself | None | Same general model as Donor; sensitive beneficiary data additionally requires explicit permission + scope + applicable business authority (MASTER-REQUIREMENTS.md §10) |
| System Principal | Not a `User` — a fixed, seeded, non-authenticatable Principal (SECURITY-ARCHITECTURE.md) | N/A (no portal; invoked only by scheduled/queued jobs) | Fixed, narrowly-scoped system roles per job family — see "System Principal" | `GLOBAL_PLATFORM` scoped narrowly per capability, never unrestricted | None beyond what the specific job explicitly requires | None by default; any financial-consequence job must still go through the same Authorized Financial Consequence chain (`docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md`) with an explicit Authority Assignment for that job identity, not an implicit bypass | N/A — assurance is a human-session concept; System Principal actions are audited by principal identity instead |
| Integration Principal | Not a `User` — a fixed, seeded, non-authenticatable Principal (SECURITY-ARCHITECTURE.md), one per external integration (e.g. a specific payment provider webhook identity) | N/A | Fixed, narrowly-scoped per integration | Narrow, per-integration scope only | None beyond the specific integration's declared capability | None — no Integration Principal may post directly to Ledger (`docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md` "Never... provider callback... write directly to Ledger") | N/A |

**Materialized Without New Semantics: YES.** **New Actor Invented: NO.**

---

## Canonical Authorization Formula (implementation form)

Restated from `docs/05-rbac/RBAC-ARCHITECTURE.md` as the exact evaluation sequence IMP-003's
authorization service executes, short-circuiting DENY at the first failing term:

```
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
`partner_representative`, `donor`, `fundraiser`). A Role itself carries no scope — scope is bound
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

System Roles (`donor`, `fundraiser`, `super_admin`, and other roles IMP-003 itself seeds) are
marked `is_system = true` and cannot be deleted or renamed through ordinary role-management
actions — only through an explicitly authorized migration/ADR-governed change, consistent with
`CHANGE-CONTROL.md`.

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
has no meaning under this model). `scope_id` is a polymorphic target reference (e.g. a specific
Partner's id, Campaign's id) whose MEANING is domain-aware (see next section); `scope_type` values
`ORGANIZATION` and `GLOBAL_PLATFORM` carry `scope_id = null` (they are not bound to a specific
target row — they mean "the whole organization"/"the whole platform" respectively).

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
authority_assignments (authority_type_id FK, principal_type, principal_id, scope_type, scope_id,
  starts_at, ends_at nullable, revoked_at nullable, status, assigned_by, revoked_by, timestamps)
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

Both participate in `principal_role_assignments`/`authority_assignments` via a `principal_type`
discriminator (`user` | `system` | `integration`) alongside `principal_id` — never given a
blanket/global Role; each is assigned only the narrow Role(s)/Authority(ies) its specific job or
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
  2. It assigns the `super_admin` Role (GLOBAL_PLATFORM scope) to the bootstrapped identity
     (resolved via the retained `super_admin_bootstraps.user_id`, or, if that identity has since
     been deleted, refused — the bridge cannot invent a target).
  3. It does NOT assign any Financial Authority Type — that remains a separate, explicit,
     post-bridge Authority Assignment action (consistent with "Super Admin != automatic Financial
     Authority"), performed afterward by whoever the assigned `super_admin` Role's own permission
     set (`rbac.authority.assign`) allows to grant it, which — for the FIRST such grant — is that
     same bootstrapped Super Admin, acting under ELEVATED assurance.
  4. An audit event is emitted (`super_admin_canonically_authorized`).
Any subsequent Super Admin does NOT use this bridge — it is granted the `super_admin` Role through
  ordinary Role assignment by an existing Super Admin (§8/§35 — "any subsequent Super Admin
  identity does NOT use the first-bootstrap mechanism"), exactly as IMP-002's own specification
  already states.
```

---

## Invitation Intent Bridge

IMP-002's `Invitation.invited_actor` field (already implemented: `partner_representative` |
`internal_administrative_identity` | `super_admin`) is ATTRIBUTION/INTENT ONLY — confirmed by
reading `app/Services/Identity/InvitationService.php`: acceptance creates a `User` row and marks
the invitation accepted; it assigns no Role, Permission, or Authority (verified — no such call
exists in that service). IMP-003 defines the conversion step explicitly:

```
An authorized provisioning workflow (invoked by whoever holds `rbac.role.assign` for the relevant
  target Role — e.g. a Super Admin, for `internal_administrative_identity` intent) — NOT
  automatically upon Invitation::accept() — reads the accepted invitation's `invited_actor` value
  as a HINT for which Role to offer/assign, and then performs an ORDINARY, independently-audited
  Role assignment.
This is a deliberate two-step design (accept identity, THEN separately authorize it) — merging
  them would make `invited_actor` implicitly grant authority, which Q22/IMP-002 explicitly
  prohibit.
```

---

## Self-Escalation Protection

```
A Principal may never, through any RBAC action, cause their OWN effective authority to increase
  beyond what it was immediately before that action. Concretely:
  - A Principal assigning a Role to themselves is refused, UNLESS that Principal already holds
    `rbac.role.assign` scoped GLOBAL_PLATFORM (i.e. only a Super Admin-equivalent identity may
    self-assign, and even then the action is fully audited and still requires ELEVATED assurance)
    — ordinary role management targets OTHER principals only.
  - A Principal assigning a Permission to a Role they themselves hold, where that Permission is
    not ALREADY held by that Principal through some OTHER active Role, is refused.
  - A Principal assigning themselves an Authority Assignment is refused UNLESS an already-
    existing, different Principal's assignment authorized it (i.e. self-assignment of Authority
    Types always requires a SECOND, distinct authorizing Principal — a hard separation-of-duties
    rule, per SECURITY-INVARIANTS.md "Duties that must be separated... are never collapsed...
    without an explicit, approved exception"), with the single documented exception of the
    Super Admin Bridge step 3 above (which is itself only a REVOCABLE bootstrap convenience, not
    a standing rule, and is fully audited).
  - A Principal expanding their OWN scope (widening an existing assignment's `scope_type`/
    `scope_id`, e.g. from PARTNER to ORGANIZATION) is treated identically to a new assignment for
    self-escalation purposes — never permitted as a mere "update."
```

This directly satisfies §36 without inventing a Human policy beyond the separation-of-duty
principle SECURITY-INVARIANTS.md already locks.

---

## Database Contract (specification only — no migration created)

All tables: `id` BIGINT unsigned PK; ULID `public_id` only where an external-safe reference is
actually needed (none of these tables are directly exposed by public routes, so none currently
need one — added later if that changes). MySQL 8.x target, strong FK integrity, per
`DATABASE-ARCHITECTURE.md`.

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

### `role_permissions`

```
purpose:      Role -> Permission grant
key fields:   role_id FK->roles, permission_id FK->permissions, granted_by_user_id FK->users
              nullable, timestamps
unique:       (role_id, permission_id)
indexes:      role_id, permission_id
deletion:     hard-delete on revoke IS acceptable here (the grant itself has no independent
              historical value beyond "was granted/revoked," which the audit event already
              captures) — `ENGINEERING CHOICE`, revisit if a later domain needs point-in-time
              reconstruction of role capability (not required by any locked document today)
audit:        permission granted-to-role / revoked-from-role
```

### `principal_role_assignments`

```
purpose:      binds a Role to a Principal within a Scope, for a bounded effective period
key fields:   principal_type (enum-like string: 'user'|'system'|'integration'), principal_id
              (BIGINT, nullable interpretation depends on principal_type — see below), role_id
              FK->roles, scope_type (nullable string, one of the DATA-SCOPE-MODEL taxonomy or
              null for GLOBAL_PLATFORM/ORGANIZATION), scope_id (nullable BIGINT, polymorphic
              target), starts_at, ends_at (nullable), revoked_at (nullable), status (active|
              revoked|expired — derived/cached from the timestamps, not an independent source of
              truth), assigned_by_user_id FK->users nullable, revoked_by_user_id FK->users
              nullable, timestamps
FK:           principal_id has NO single-table FK (polymorphic across users/system_principals/
              integration_principals) — enforced at the application layer, consistent with how
              IMP-002's own `sessions.user_id` is already a nullable, non-FK-constrained column
              for comparable polymorphic-adjacent reasons
unique:       (principal_type, principal_id, role_id, scope_type, scope_id) WHERE revoked_at IS
              NULL AND (ends_at IS NULL OR ends_at > NOW()) — "at most one ACTIVE identical
              assignment" — enforced at the application/transaction level (a partial/conditional
              unique index is MySQL-8-compatible via a generated column or application-level
              locking; exact mechanism is an implementation-time engineering choice, analogous to
              IMP-002's own "One Active Request Per User" pattern)
indexes:      (principal_type, principal_id), role_id, (scope_type, scope_id)
deletion:     never hard-deleted (revoked_at marks revocation) — historical reproducibility
audit:        role assigned / role revoked
```

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
key fields:   authority_type_id FK->authority_types, principal_type, principal_id (same
              polymorphic convention as principal_role_assignments), scope_type (nullable),
              scope_id (nullable), starts_at, ends_at (nullable), revoked_at (nullable), status,
              assigned_by_user_id FK->users nullable (nullable only for the one documented Super
              Admin Bridge exception — see below), revoked_by_user_id FK->users nullable,
              timestamps
unique:       (principal_type, principal_id, authority_type_id, scope_type, scope_id) WHERE
              revoked_at IS NULL AND (ends_at IS NULL OR ends_at > NOW()) — same pattern as above
indexes:      (principal_type, principal_id), authority_type_id, (scope_type, scope_id)
deletion:     never hard-deleted
audit:        authority assigned / authority revoked
constraint:   `assigned_by_user_id` MUST differ from the assignment's own `principal_id` (when
              principal_type='user') EXCEPT for the one documented Super Admin Bridge case, which
              is itself logged with a distinct audit event name (`super_admin_canonically_
              authorized`) so it is never mistaken for an ordinary self-assignment in later
              audit review (see "Self-Escalation Protection")
```

### `system_principals` / `integration_principals`

```
purpose:      fixed, seeded, non-authenticatable Principal catalogs (see "System Principal")
key fields:   code (unique), description, timestamps
unique:       code
deletion:     never hard-deleted once ever assigned
audit:        principal registered
```

### No changes to `users`

No `role`, `role_id`, `permission`, `is_admin`, `is_super_admin`, `financial_authority`,
`partner_authority`, `fundraiser_authority`, or `beneficiary_approval` column is added to `users`
(§22) — every authorization fact lives in the normalized tables above, referencing `users.id` only
as a `principal_id` foreign-key target when `principal_type='user'`.

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
Role/Authority it granted, or WHAT scope it covered — only `ends_at`/`revoked_at`/`revoked_by`
may be set (once). A changed assignment is always a NEW row plus a revocation of the old one, never
a mutation in place — this mirors IMP-002's own EmailChangeRequest immutable-except-terminal-
columns pattern and satisfies `DATABASE-INVARIANTS.md` "Policy/version historical reproducibility."

---

## Concurrency

```
Role assignment vs revocation (same principal+role+scope):     lock the target row (or the
  candidate unique-key tuple) under a DB transaction before insert/update, mirroring IMP-002's
  EmailChangeRequest "lock the parent, then the candidate row" pattern — lock the Principal
  "row" (for principal_type='user', the `users` row; for 'system'/'integration', their own
  catalog row) FIRST, then the assignment row/candidate insert, establishing ONE consistent lock
  order (Principal -> Assignment) across every RBAC write path, exactly as IMP-002's Remediation
  Pass 2 established for EmailChangeRequest (User -> EmailChangeRequest) to avoid an opposing-
  order deadlock.
Scope assignment vs revocation:                                  same pattern (scope lives on the
  same assignment row, so this is the same lock, not a separate one).
Business Authority assignment vs revocation:                     same pattern, Principal ->
  authority_assignments.
Authorization evaluation vs revocation (a request is mid-evaluation when a revocation commits):
  the evaluator reads assignment rows FRESH per request (no long-lived cross-request cache is the
  default — see "Cache") — a revocation that commits before evaluation's read is honored
  immediately; one that commits mid-request-after-the-read is honored on the NEXT request, which
  is the same acceptable latency window IMP-002 already accepts for its own session-based
  Security Restriction check (re-checked per request via middleware, not sub-request-atomic).
First Super Admin canonical assignment (the Bridge):             the Bridge's own guard (no
  existing `super_admin` Role assignment) is itself checked under a row lock exactly like Q25's
  own `super_admin_bootstraps` unique-constraint guard, for the same "concurrent double-invocation
  fails deterministically" reason.
```

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
                                     Authority Type.
Financial authority conflation:      Business Authority (step 6) is evaluated as a term
                                     structurally separate from Permission (step 3) — a caller
                                     cannot satisfy the financial-authority check merely by having
                                     a permission that happens to be named similarly.
```

---

## Test Contract

Restates and extends `docs/00-governance/DEFINITION-OF-DONE.md`'s "Mandatory RBAC Tests" into the
concrete contract IMP-003's implementation must satisfy (test file organization is an
implementation-time engineering choice, not fixed here):

### Permission

```
permission missing on every held Role                        -> DENY
permission present via at least one held, active Role          -> evaluation continues
permission present only via a REVOKED/expired Role assignment  -> DENY (not "present")
```

### Scope

```
correct permission + no active scope assignment covering the resource -> DENY
correct permission + scope assignment for a DIFFERENT target of the same scope type -> DENY
correct permission + scope assignment covering the resource -> evaluation continues
```

### Ownership

```
correct permission + scope + wrong owner/subject -> DENY
correct permission + scope + correct owner/subject -> evaluation continues
```

### Business Authority

```
permission + scope, action requires Business Authority, none assigned -> DENY
permission + scope + an assigned but WRONG Authority Type -> DENY
permission + scope + correct, active Authority Assignment -> evaluation continues
permission + scope + Authority Assignment that has EXPIRED (ends_at in the past) -> DENY
permission + scope + Authority Assignment that is REVOKED -> DENY
```

### Assurance

```
action requiring ELEVATED + STANDARD session -> DENY
action requiring ELEVATED + ELEVATED session -> evaluation continues
action NOT requiring ELEVATED + STANDARD session -> evaluation continues (assurance term is
  satisfied trivially when no elevation is required)
```

### Restriction

```
principal lifecycle DISABLED -> DENY (before any Role/Permission evaluation runs)
principal security_restriction SUSPENDED -> DENY (before any Role/Permission evaluation runs)
```

### Super Admin

```
Super Admin role + missing financial_approver authority -> financial action DENY
Super Admin role + assigned financial_approver authority, correct scope -> financial action
  evaluation continues (subject to remaining AND-terms)
```

### Fundraiser

```
self-registered Fundraiser identity + no Authority Assignment for the (later-domain-defined)
  fundraiser-operating capability -> fundraiser-privileged action DENY
```

(This test is a CONTRACT statement for the later Fundraiser-domain stage to implement against —
IMP-003 itself has no fundraiser-privileged action to test, since it owns no Fundraiser domain
logic; the test is specified here so that stage's Definition of Ready inherits it.)

### Partner

```
Partner Representative A (PARTNER scope = Partner 1) -> Partner 2's protected resource -> DENY
```

(Same status as Fundraiser above — contract for the Partner-domain stage.)

### Donor

```
Donor A -> Donor B's private resource (OWN scope mismatch) -> DENY
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
unknown/unresolved scope type, unresolved principal_type, or any evaluator internal error ->
  DENY (never a silent ALLOW; an internal error is NOT translated into permissive behavior)
```

### Self-Escalation (IMP-003-specific, beyond the DoD's generic list)

```
Principal without GLOBAL_PLATFORM rbac.role.assign attempting to assign a Role to THEMSELVES
  -> DENY
Principal attempting to assign a Permission to a Role they hold, where they do not already
  effectively hold that Permission through another active Role -> DENY
Principal attempting to create an Authority Assignment where principal_id == assigned_by_user_id
  (outside the one documented Super Admin Bridge case) -> DENY
```

---

## Database Test Contract

```
roles.code unique constraint enforced
permissions.code unique constraint enforced
principal_role_assignments: FK integrity (role_id -> roles.id) enforced; the "at most one
  ACTIVE identical assignment" constraint enforced (a second concurrent/sequential identical
  active assignment is rejected, not silently duplicated)
authority_assignments: same FK + uniqueness pattern for authority_type_id
revocation: revoking an assignment sets revoked_at/revoked_by without deleting the row; a
  revoked row is excluded from "active assignment" queries used by the evaluator
temporal: an assignment with ends_at in the past is excluded from "active" even without an
  explicit revocation
Super Admin Bridge: running the bridge command TWICE is refused the second time (guard #2 in
  "Super Admin Canonical Authorization"); running it when NO bootstrap row exists is refused
  (nothing to bridge); running it when a `super_admin` Role assignment ALREADY exists is refused
  even if a NEW/different bootstrap row somehow existed (defense in depth for guard #2)
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

## Implementation Order (for the future implementation-authorized pass)

```
1.  Database schema (roles, permissions, role_permissions, principal_role_assignments,
    authority_types, authority_assignments, system_principals, integration_principals)
2.  Models (with the immutable-except-terminal-columns pattern IMP-002 established)
3.  Permission Registry (code-defined) + idempotent seeder + Authority Type seeder (7 approved
    types) + System/Integration Principal seeders (empty catalog initially — later stages add
    their own rows)
4.  Principal Role/Authority assignment services (assign/revoke, with the lock-order +
    uniqueness + self-escalation rules specified above)
5.  ScopeResolver contract + the ONE concrete resolver IMP-003 owns (`OWN` against `User` itself)
6.  AuthorizationContext / evaluator service implementing the 9-step AND-chain
7.  Base Policy class encapsulating the AND-chain sequencing for domain Policies to extend
8.  Assurance integration (declarative "requires ELEVATED" marking + evaluator wiring to IMP-002's
    AssuranceService)
9.  Security Restriction integration (re-confirm IMP-002's User::canAuthenticate() at the start
    of every evaluation)
10. Super Admin Bridge command (the two-guard, one-time canonical-authorization command)
11. `identity.security.transition` permission wiring for IMP-002's deferred persistent-state
    transitions (ACTIVE<->DISABLED, NONE<->SUSPENDED) — the first CONSUMER of this whole system
12. Tests (per "Test Contract" and "Database Test Contract" above)
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
Exact mechanism for the MySQL 8-compatible "conditional unique active assignment" constraint
  (generated column + unique index, vs. application-level transactional lock only) — both are
  MySQL-8-compatible; the choice does not affect the locked model above.
Whether `role_permissions` revocation should ALSO retain history (currently specified as hard-
  delete-on-revoke, an engineering choice) — revisit only if a later domain's Definition of Ready
  identifies an actual need to reconstruct "what a Role's permission set looked like at time T,"
  which no current locked document requires.
```

None of the above requires a Human Decision — each is a reversible, non-architecture-affecting
implementation detail per AGENTS.md "Before Coding" step 7 ("make the smallest coherent change").

## Human Decisions Required

**NONE.** Every design choice in this document either (a) directly restates an already-locked
Level 1-3 document, or (b) is explicitly labeled `ENGINEERING CHOICE` with a justification that
does not touch business rules, financial semantics, legal/compliance policy, or the Human Decision
Register — per `docs/00-governance/IMPLEMENTATION-GOVERNANCE.md` "Human Approval Rule," none of
which applies to the choices made here.
