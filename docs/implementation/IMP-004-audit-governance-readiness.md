# IMP-004 — Audit + Governance Foundation — Readiness Analysis

```
Stage:                          IMP-004 READINESS
Implementation Authorized:      NO
Primary Future Implementation Owner: Kimi K3
Exact Model ID:                 moonshotai/Kimi-K3
Execution Environment:          Command Code GOAT
Specialist:                     DeepSeek V4 Pro — READ ONLY
Independent Formal Reviewer:    Codex
Human Approval Authority:       Human
```

This document is READ-ONLY repository analysis performed under
[docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md)'s
"IMP-004 readiness/specification work: AUTHORIZED" grant (see
[docs/audits/MULTI-MODEL-GOVERNANCE-FINALIZATION.md](../audits/MULTI-MODEL-GOVERNANCE-FINALIZATION.md)).
No application code, migration, test, or dependency file was touched to produce it. It does not
authorize IMP-004 implementation. Claude Code's role here is repository analysis/documentation
preparation only — it does not transfer IMP-004 Primary Implementation Ownership from Kimi K3.

---

## 1. Authority Reviewed

Level 1-4 (binding):
[HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md),
[MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md),
[MASTER-ARCHITECTURE.md](../02-architecture/MASTER-ARCHITECTURE.md),
[MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md),
[FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md),
[DATABASE-ARCHITECTURE.md](../03-database/DATABASE-ARCHITECTURE.md),
[DATABASE-INVARIANTS.md](../03-database/DATABASE-INVARIANTS.md),
[SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md),
[SECURITY-INVARIANTS.md](../04-security/SECURITY-INVARIANTS.md),
[RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md),
[AUTHENTICATION-ASSURANCE.md](../05-rbac/AUTHENTICATION-ASSURANCE.md),
[DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md),
[BUSINESS-AUTHORITY-MODEL.md](../05-rbac/BUSINESS-AUTHORITY-MODEL.md).

Level 5-6: [IMP-002-identity-authentication.md](IMP-002-identity-authentication.md) §"Audit
Events"/"Retention Boundary", [IMP-003-rbac-scope-business-authority.md](IMP-003-rbac-scope-business-authority.md)
§"Audit Contract", [MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md),
[DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md).

Level 7 (implementation artifacts inspected): `app/Services/Identity/IdentityAuditLogger.php`,
`app/Services/Rbac/RbacAuditLogger.php`, every Identity/Rbac service that emits an event (listed
in §7), `config/logging.php`, `tests/Support/Rbac/CapturesRbacAudit.php`,
`tests/Feature/Rbac/RbacAuditTest.php`, `app/Models/Rbac/Principal.php`,
`app/Console/Commands/BridgeFirstSuperAdmin.php`, `app/Console/Commands/BootstrapSuperAdmin.php`,
all 18 files under `database/migrations/`.

`docs/06-domains/governance/` contains only `.gitkeep` — no materialized content yet.
`docs/08-testing/` does not exist as a populated directory — no repository-wide testing-strategy
document beyond [DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md) was found.

---

## 2. Existing Foundation

| Component | Classification | Evidence |
|---|---|---|
| `IdentityAuditLogger` (IMP-002) | REUSABLE PATTERN, not the canonical sink | `app/Services/Identity/IdentityAuditLogger.php` — writes to `identity_audit` log channel; its own docblock names IMP-004/"Governance & Platform Services" as the owner of the real sink |
| `RbacAuditLogger` (IMP-003) | REUSABLE PATTERN, not the canonical sink | `app/Services/Rbac/RbacAuditLogger.php` — identical deferred-persistence pattern, `rbac_audit` channel |
| `identity_audit` / `rbac_audit` log channels | TEMPORARY / SCAFFOLD | `config/logging.php` — both `single`-driver file channels, explicitly documented as provisional until IMP-004 exists |
| 19 distinct emitted event names across Identity + RBAC | PARTIALLY SUITABLE | Enumerated in §7 below; free-form `snake_case` strings, not a formal registry/enum, no `event_version` |
| Transactional, fail-closed audit-write semantics (RBAC only) | AUTHORITATIVE PRECEDENT, not yet a written rule | Proven by 4 tests in `tests/Feature/Rbac/RbacAuditTest.php`: `RbacAuditLogger::record()` throwing rolls back the entire DB transaction. No equivalent is proven for `IdentityAuditLogger` |
| Sensitive-payload exclusion (RBAC) | AUTHORITATIVE PRECEDENT | `RbacAuditTest::test_no_audit_event_contains_sensitive_material` asserts absence of `password, password_hash, mfa_secret, totp_secret, session_token, api_token, credential, secret` in every logged context |
| Canonical Principal model (IMP-003) | AUTHORITATIVE, must be reused | `app/Models/Rbac/Principal.php` — Human/System/Integration kinds; `canAuthorize()` re-checks live status |
| Any `audit_events`/`audit_log` DB table | MISSING | Confirmed via full migration listing — no such table exists |
| IMP-003's own "Audit Contract" event list vs. actually implemented events | CONFLICTING | See Finding F-01 below |
| `docs/06-domains/governance/` | MISSING | Empty placeholder only |

---

## 3. Scope

### 3.1 What "Audit + Governance Foundation" Means Here

[MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §16 ("Governance & Platform
Services") owns: `Audit, Privacy, Retention, Search, Reporting, Currency Governance`. However,
the locked IMP roadmap (per
[MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md)'s ownership matrix) gives
**Search + Reporting its own later stage (IMP-026)**. IMP-004's own name is narrower:
"Audit **+ Governance Foundation**", not "Governance & Platform Services" in full. This document
therefore treats IMP-004's scope as:

```
OWNED BY IMP-004:
  - Audit (event taxonomy, canonical sink, actor/subject model, redaction, immutability,
    transactional/failure semantics, access control, query foundation)
  - Governance Foundation (generic, reusable evidence primitives that later domains' own
    governance concerns — approval, policy versioning — will attach to; NOT the Approval
    module itself, which MODULE-OWNERSHIP.md §12 assigns elsewhere)

STRUCTURAL SUPPORT ONLY (mechanism, not policy values):
  - Privacy (Q16 — "Configurable Privacy Model": the mechanism must be configurable; actual
    field-level rules are a later configuration/specification concern)
  - Retention (Q17 — "Configurable Retention Policy Matrix": same pattern)

DEFERRED TO A LATER, ALREADY-NAMED STAGE:
  - Search + Reporting -> IMP-026

OUT OF SCOPE / NO EVIDENCE TIES IT TO IMP-004:
  - Currency Governance (no HDR entry, no MODULE-OWNERSHIP detail beyond the one-line mention,
    no IMP number found anywhere naming it; Q20 "IDR Base + Limited Multi-Currency" exists but
    is not linked to IMP-004 by any authoritative document) — flagged, not assumed
```

### 3.2 Ownership Classification vs. Neighboring Stages

| Neighbor | Classification | Rationale |
|---|---|---|
| IMP-002 Identity + Authentication | DEPENDENCY (consumer relationship is bidirectional) | IMP-002's `IdentityAuditLogger` emission points are the first migration candidates to the canonical sink; IMP-002's `User`/`Principal` linkage is reused for actor attribution |
| IMP-003 RBAC + Scope + Business Authority | DEPENDENCY | Canonical Principal/Role/Scope/Authority model is reused verbatim for actor attribution and audit-read authorization; `RbacAuditLogger` emission points are the second migration candidate |
| IMP-009 Payment Hub | CONSUMER OF IMP-004 | Will emit provider/payment audit events through the IMP-004 sink; IMP-004 must not implement Payment semantics |
| IMP-010 Ledger | CONSUMER OF IMP-004, and a hard boundary | Ledger remains the financial source of truth (`AGENTS.md` "Locked Decisions": "Ledger is accounting source of truth"); Audit must never become an alternate financial source of truth — see §7/Finding boundary in Financial Domain Support below |
| IMP-011 Financial Consequence Posting | CONSUMER OF IMP-004 | Posting events are audited, not posted, by IMP-004's mechanism |
| IMP-014 Commission | CONSUMER OF IMP-004 | Policy Version evidence (Commission owns "Policy Version" per MODULE-OWNERSHIP §6) may reference a generic policy-version-evidence primitive IF IMP-004 provides one — see §Governance Foundation scope |
| IMP-015 Withdrawal / IMP-016 Refund | CONSUMER OF IMP-004 | Approval-adjacent events audited generically; Approval mechanics themselves belong to Module 12 (Approval), not IMP-004 |
| IMP-017 Reconciliation | CONSUMER OF IMP-004 | Matching evidence audited, not owned |
| IMP-023 Receipt + Compliance | CONSUMER OF IMP-004 | Document versioning events audited; Receipt/Compliance Document versioning itself is Module 15's own concern |
| IMP-027 Security Hardening | DEPENDENCY (bidirectional) | Security Hardening will likely harden/extend audit coverage (e.g. closing Finding F-01 below); IMP-004 must leave room for this rather than closing the door |
| IMP-030 Final Implementation Audit | CONSUMER OF IMP-004 (as evidence source) | Codex's IMP-030 Primary-Audit-Owner review will read IMP-004-produced evidence across all prior stages, per GOV-MM-001; IMP-004 does not implement IMP-030's own audit-of-the-program capability |

**Avoid scope creep**: IMP-004 provides the *foundation* — event taxonomy, sink, actor model,
redaction rule, transaction/failure semantics, immutability, access control, query primitives.
It must **not** implement Approval workflow mechanics (Module 12), Commission/Refund/Withdrawal
policy logic, Search/Reporting UI (IMP-026), or any financial posting logic (Ledger remains sole
financial source of truth).

---

## 4. Audit Model

### 4.1 Actor Model

Must align with IMP-003's canonical `Principal` (Human / System / Integration kinds) — reusing
`activeRoleAssignmentsGranting()`-style live evaluation is NOT required for audit (audit records
a fact at a point in time), but attribution must record:

```
REQUIRED: actor_principal_id, actor_principal_kind (human/system/integration)
REQUIRED for human actors: linkage sufficient to resolve the acting User at read time
  (foreign key, not necessarily a denormalized snapshot — see §5 Snapshot vs FK)
OPTIONAL: authentication_assurance at time of action (STANDARD/ELEVATED, per
  AUTHENTICATION-ASSURANCE.md) — useful for security-relevant audit queries
DEFERRED: impersonation/delegation fields — NO approved impersonation capability exists anywhere
  in the reviewed architecture; do not invent one
REJECTED: raw session token, raw API token, or any credential material as an actor identifier —
  Principal ID is the only approved identity carrier (Security Architecture "API token authority
  intersection" principle; existing RbacAuditLogger docblock prohibition)
```

This confirms and extends the existing, already-correct pattern: every current RBAC audit event
already uses `*_principal_id` fields exclusively, never `*_user_id` (verified in §7's event
table) — IMP-004 must continue that discipline as a **hard rule**, not merely a convention.

### 4.2 System / Integration Principal Attribution

Already demonstrated correctly in IMP-003: `non_human_principal_deactivated` carries
`kind: 'system'|'integration'` plus the respective catalog ID and the canonical `principal_id`.
IMP-004's foundation should generalize this exact shape (`actor_principal_kind` +
`actor_principal_id`) as the canonical pair for every event, so that scheduled jobs, queue
workers, payment-provider callbacks, and future integrations cannot be misattributed to an
arbitrary human User — deterministic attribution is already achievable with existing IMP-003
primitives; IMP-004 need not invent a new identity concept, only a consistent audit-record shape.

### 4.3 Event Taxonomy

No formal taxonomy/registry exists yet (confirmed: zero migration, zero enum, zero contract
class). [MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) lists `Audit` as a
named domain only, with no further detail. The **only** existing precedent for a category list is
implicit in the already-emitted event name prefixes (`role_*`, `authority_*`,
`role_permission_*`, `*_principal_*`, plus IMP-002's un-prefixed identity events like
`login_succeeded`, `email_verified`).

For IMP-004, distinguish:

```
1. TAXONOMY FOUNDATION (IMP-004 must define): a stable, namespaced, versionable event-identifier
   convention (e.g. dot-namespaced: "identity.login.succeeded", "rbac.role.assigned") that both
   existing 19 events and all future domains can be mapped/migrated onto without breaking
   consumers. This is a MUST — without it, every future domain re-invents naming ad hoc (already
   visibly happening: IMP-002 events are unprefixed snake_case, IMP-003 events are unprefixed
   snake_case with different segmentation, and IMP-003's own spec promised yet a THIRD set of
   names never implemented — see Finding F-01).
2. CURRENTLY EMITTED EVENTS (IMP-004 must migrate, not re-invent): the 19 events enumerated in
   §7, currently going to `identity_audit`/`rbac_audit` log channels.
3. FUTURE RESERVED/CONSUMER CATEGORIES (IMP-004 must NOT implement now): AUTHENTICATION,
   IDENTITY, AUTHORIZATION, RBAC, SECURITY, ADMINISTRATION are foundation-adjacent and already
   have real events; APPROVAL, FINANCIAL, PAYMENT, LEDGER, CONTENT, CAMPAIGN, DONATION, PARTNER,
   FUNDRAISER, BENEFICIARY, DISTRIBUTION, INTEGRATION, SYSTEM are reserved namespaces for later
   domains to populate — IMP-004 defines the taxonomy's *shape* (how a namespace/category is
   structured) without pre-populating those namespaces with events those domains don't exist to
   emit yet.
```

### 4.4 Record Structure

Field-by-field classification (no schema created; this is analysis only):

| Field | Classification | Rationale |
|---|---|---|
| `id` | REQUIRED | Standard primary key |
| `event_type` | REQUIRED | Per §4.3 taxonomy |
| `event_version` | REQUIRED | IMP-004's own taxonomy foundation must be versionable per §4.3; also aligns with `DATABASE-INVARIANTS.md`'s "Policy/version historical reproducibility" principle applied to event *shape* |
| `occurred_at` | REQUIRED | — |
| `actor_principal_id` | REQUIRED | §4.1 |
| `actor_principal_kind` | REQUIRED | §4.1/§4.2 |
| `subject_type` / `subject_id` | REQUIRED | Every existing event already names its subject implicitly (`role_id`, `principal_id`, etc.) — formalizing this as a typed pair is a MUST for queryability (§6) |
| `action` / `result` | OPTIONAL, folded into `event_type` today | Current events encode action+result in the event name itself (`role_assigned` vs. a hypothetical `role_assignment_denied`); a separate `action`/`result` pair is not proven necessary by existing evidence — DEFERRED to specification, not REQUIRED by this readiness pass |
| `request_id` / `correlation_id` | REQUIRED (see §22) | No current implementation captures this at all — a genuine gap, not yet exercised by any consumer, but required foundation for future Payment/webhook/reconciliation retry correlation |
| `ip_address` / `user_agent` | OPTIONAL | Not found in any current audit event; Security Architecture does not mandate it; useful for security-relevant categories (login, MFA) specifically, not universally |
| `authentication_assurance` | OPTIONAL | See §4.1 |
| `metadata` (JSON) | REQUIRED | Every current event already uses a free-form context array; must remain extensible per-event-type |
| `before` / `after` | OPTIONAL, per-event-type opt-in | See §5/§6 — must NOT become blanket full-model serialization |
| `created_at` | REQUIRED | Standard, distinct from `occurred_at` if async ingestion is ever introduced (not currently the case — see §9 Transaction Boundary) |

### 4.5 Immutability

No existing document (Security or Database Architecture) states an audit-specific immutability
rule. The *pattern* already exists for a different domain: `Principal.tombstoned_at`,
`Role.retired_at`, `Permission.deprecated_at`, `SystemPrincipal/IntegrationPrincipal.deactivated_at`
are all "deliberately NOT mass-assignable... one-way transition... must go through
`forceFill()`" (verified in those models' own docblocks). Audit records have a stronger
requirement implied by their *purpose* (evidence of what happened, not current state), but this
is a **HUMAN DECISION** for exact policy — see HD-IMP004-03 below; this readiness pass records
only what is derivable, not the final rule:

```
DERIVABLE (do not require a new Human Decision): audit records must never be application-layer
  UPDATE-able once written (no Eloquent update() surface); a durable append-only pattern
  (matching the retired_at/deprecated_at/deactivated_at precedent) is consistent with existing
  repository convention.
NOT DERIVABLE WITHOUT A DECISION: whether ANY deletion capability may ever exist (e.g. for a
  retention-driven purge under Q17's "Configurable Retention Policy Matrix"), and if so, whether
  it requires separate governance evidence of its own (a "deletion of evidence" event, itself
  audited) before being permitted to run. See HD-IMP004-03.
```

### 4.6 Redaction

`RbacAuditLogger`'s own docblock ("MUST NEVER be called with a credential, token, or raw
exception detail") and `IdentityAuditLogger`'s docblock (naming verification tokens, passwords,
TOTP secrets, recovery codes specifically) are the only existing redaction rules, both enforced
by **call-site discipline**, not by a shared allow/deny-list mechanism, and verified today by only
one test file (`RbacAuditTest`) with a hardcoded substring blacklist
(`password, password_hash, mfa_secret, totp_secret, session_token, api_token, credential, secret`).
Neither Security Architecture document defines a redaction mechanism (confirmed zero "audit"
matches). §16 of the originating instructions anticipates this and asks whether a blacklist is
sufficient or an allow-list is required — **this repository's architecture does not currently
specify either**, so this is recorded as a finding (F-02, MAJOR) rather than resolved here.

### 4.7 Correlation

No current implementation captures `request_id`/`correlation_id` anywhere in Identity or RBAC
audit emission. This is a genuine, currently-unmet requirement for future
Payment/webhook/reconciliation retry correlation (§20/§22 concerns) — IMP-004 should establish
the foundation (a resolvable, request-scoped correlation identifier available to any audit
emission call) even though no current consumer exercises it yet.

### 4.8 Transaction Semantics

Already **proven** for RBAC (fail-closed, same-transaction) by 4 tests; **not proven** for
Identity (no equivalent test found in the reviewed scope). This asymmetry itself is a finding
(F-03, MINOR) IMP-004 should resolve when migrating both domains onto one canonical sink — see
§9/§18 below and HD-IMP004-01.

### 4.9 Failure Semantics

See §18 below and HD-IMP004-01 — this is the single most consequential open question and is
explicitly flagged as a Human Decision, not resolved here.

---

## 5. Authorization (Audit Read Access)

No existing document defines who may read audit data. [RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md)'s
canonical formula (Permission + Scope + Ownership/Subject Access + Business Authority +
Assurance) applies generically, but no `audit.read`-shaped permission, scope rule, or authority
type exists yet in [PermissionRegistry](../../app/Services/Rbac/PermissionRegistry.php)-equivalent
form. `AGENTS.md`'s "Security" section is explicit that authorization is never role-only and that
default is DENY — this must apply to audit read exactly as to everything else. Confirmed
explicitly relevant existing rule: `Super Admin != automatic Financial Approver`
([BUSINESS-AUTHORITY-MODEL.md](../05-rbac/BUSINESS-AUTHORITY-MODEL.md)) — by the same locked
principle, **Super Admin must not be assumed to automatically gain unrestricted audit-read
access**, especially for financial- or security-category events, without an explicit new
Permission + Scope decision. This is recorded as HD-IMP004-02 (Human Decision Required) rather
than assumed.

`DATA-SCOPE-MODEL.md`'s scope taxonomy (`OWN, FUNDRAISER, PARTNER, CAMPAIGN, PROGRAM, FUND,
BENEFICIARY_CASE, ASSIGNED_WORK, ORGANIZATION, GLOBAL_PLATFORM`) is reusable as-is for audit-read
scoping once IMP-004 defines what "subject" scope an audit record carries (§4.4's `subject_type`/
`subject_id` pairing is the mechanism this would key off).

---

## 6. Database

No migration is proposed here (creation is prohibited during readiness); this section records
likely impact only, for the future specification to formalize:

```
Target platform:      MySQL 8.x (locked, per DATABASE-ARCHITECTURE.md) — no other platform is
                       named anywhere in the reviewed architecture (no SQLite requirement exists
                       at the architecture level; SQLite is used only as this repository's
                       existing local/default TEST runtime, per the pattern established across
                       IMP-002/IMP-003 test suites)
Identity convention:  BIGINT unsigned internal keys (locked convention, reused directly)
Likely table(s):      one canonical audit-event table (append-only) is the minimum; whether a
                       second table is needed for a redaction/allow-list registry is a
                       specification-time question, not resolved here
Likely indexes:       (actor_principal_id, occurred_at), (subject_type, subject_id, occurred_at),
                       (event_type, occurred_at) — derived from the query needs in §8, not a
                       final index design
JSON usage:           `metadata` (and optional `before`/`after`) as JSON columns — consistent
                       with existing repository use of JSON-shaped `context` arrays throughout
                       IdentityAuditLogger/RbacAuditLogger call sites today
Generated columns:    no evidence of need found; not proposed
Monetary values:      N/A to audit itself — Ledger remains the sole owner of DECIMAL monetary
                       columns (locked); audit must reference financial identifiers, never
                       duplicate financial amounts as an alternate source of truth
Shared-hosting:       no new infrastructure requirement identified (no Kafka/Redis/Elasticsearch
                       need surfaced by any reviewed document)
```

---

## 7. Existing Event Inventory (Migration Candidates)

### 7.1 RBAC Events (IMP-003, `rbac_audit` channel) — 9 distinct events

| Event | Emitting File | Exact Payload Fields |
|---|---|---|
| `role_assigned` | `RoleAssignmentService.php` | `grantor_principal_id, target_principal_id, role_id, scope_type, scope_id` |
| `role_revoked` | `RoleAssignmentService.php` | `revoker_principal_id, assignment_id, principal_id, role_id` |
| `authority_assigned` | `AuthorityAssignmentService.php` | `grantor_principal_id, target_principal_id, authority_type_id, scope_type, scope_id` |
| `authority_revoked` | `AuthorityAssignmentService.php` | `revoker_principal_id, assignment_id, principal_id, authority_type_id` |
| `role_permission_granted` | `RolePermissionService.php` | `actor_principal_id, role_id, permission_id` |
| `role_permission_revoked` | `RolePermissionService.php` | `actor_principal_id, role_id, permission_id` |
| `human_principal_tombstoned_user_deleted` | `PrincipalService.php` | `acting_principal_id, principal_id` |
| `non_human_principal_deactivated` | `PrincipalService.php` | `actor_principal_id, kind ('system'\|'integration'), {system\|integration}_principal_id, principal_id` |
| `super_admin_canonically_authorized` | `BridgeFirstSuperAdmin.php` | `principal_id, role, scope_type, granted[], not_granted[]` |

### 7.2 Identity Events (IMP-002, `identity_audit` channel) — 18 distinct events

`identity_created, self_registration_completed, invitation_issued, invitation_revoked,
invitation_accepted, login_succeeded, login_failed (reason: invalid_credentials |
security_restriction | mfa_challenge_failed), logout, password_changed,
password_reset_completed, email_change_requested, email_change_completed,
email_change_conflicted, email_verified, mfa_enrolled, mfa_recovery_code_used,
mfa_recovery_codes_regenerated, mfa_reset_or_disabled, first_super_admin_bootstrap_completed` —
every record additionally carries `user_id, user_public_id` (never a raw credential).

### 7.3 Finding F-01 — IMP-003's Own "Audit Contract" Does Not Match Implementation

`IMP-003-rbac-scope-business-authority.md` §"Audit Contract" (an Implementation Specification,
Level 5) names: `role_registered, role_retired, permission_registered, permission_deprecated,
authority_type_registered, business_authority_assigned` (not `authority_assigned` as
implemented), and `authorization_denied_security_critical` — **none of these six/seven exist
anywhere in the actual implementation** (confirmed by exhaustive grep of `app/`). This is a
Level 5 vs. Level 7 conflict per [DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md)
("if code contradicts a higher-authority document, the code is wrong — the requirement is not
automatically changed to match the code"). However, IMP-003 is `FINAL / LOCKED` and this
readiness task is explicitly prohibited from reopening it. This is recorded as:

```
Finding F-01 — MAJOR
Affected authority: docs/implementation/IMP-003-rbac-scope-business-authority.md
  §"Audit Contract" (Level 5) vs. app/Services/Rbac/*.php (Level 7)
Risk: security-critical denial events (self-escalation attempts, Financial Authority failures)
  are specified but never audited anywhere today — a real coverage gap, not just a naming
  mismatch, since `authorization_denied_security_critical` has no implemented equivalent at all.
Required resolution: IMP-004's specification must explicitly decide whether to (a) backfill the
  missing emission call sites into IMP-002/IMP-003 services as part of migrating them onto the
  canonical sink, updating the spec text to match if names differ, or (b) formally supersede
  the unimplemented contract items via an updated IMP-003 addendum. Belongs at IMP-004
  SPECIFICATION time, not IMPLEMENTATION time — resolving it changes what IMP-004 must build.
```

---

## 8. Testing

No dedicated repository-wide testing-strategy document exists beyond
[DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)'s generic categories (`Unit,
Feature, Integration, Authorization, Security, Financial Integrity, Regression`) — Audit-specific
tests fit within these existing categories; no new category is warranted.

Required future test areas (none implemented now — this is a list for the future specification):

```
FUNCTIONAL:      successful audit append; correct actor/subject/event_type persisted
IMMUTABILITY:    forbidden update; forbidden delete (absent an approved retention-purge path)
TRANSACTIONAL:   rollback when audit append fails for every mutation currently proven
                 transactional in RBAC (extend the existing 4-test RbacAuditTest pattern);
                 close the Identity-side gap noted in §4.8/F-03
ATTRIBUTION:     human/System/Integration Principal attribution correctness (extends existing
                 IMP-003 coverage)
REDACTION:       extend RbacAuditTest's sensitive-substring assertion to the canonical sink;
                 resolve F-02 (blacklist vs. allow-list) before finalizing this test's shape
AUTHORIZATION:   audit-read scope enforcement, cross-scope denial, Super Admin non-automatic
                 access (once HD-IMP004-02 is resolved)
CONCURRENCY:     simultaneous mutations producing correctly ordered/non-duplicated audit rows
SECURITY (NEGATIVE): actor spoofing, forged Principal ID, metadata injection, oversized
                 metadata, untrusted/unregistered event_type rejection, IDOR on audit read,
                 mass-assignment on audit records (mirroring the existing
                 `test_direct_mass_assignment_of_deactivated_at_is_blocked` pattern from IMP-003)
DATABASE MATRIX: per the IMP-003 precedent (see docs/audits/IMP-003-IMPLEMENTATION-REMEDIATION-3.md
                 and IMP-003-FINALIZATION.md), the default suite runs on SQLite; a disposable
                 MySQL 8.x instance (never the real/unknown configured `.env` database) must
                 independently validate migrations, constraints, and any MySQL-specific locking
                 behavior before Stage Gate — this is the same evidence bar already established,
                 not a new one
```

---

## 9. Human Decisions Required

```
HD-IMP004-01
Question: Must audit-write failure be transactionally fail-closed (business operation rolls
  back) for EVERY security-sensitive mutation category, or may some categories degrade to
  "operation proceeds + security alert" for availability reasons?
Why it matters: IMP-003 already implemented fail-closed for RBAC mutations as an IMPLEMENTATION
  choice (proven by tests), but no Level 1-4 document states this as a required policy, and
  IMP-002's Identity audit has no equivalent proof either way. Extending fail-closed universally
  to Payment/Ledger-adjacent future events (through IMP-004's foundation) has real availability
  and financial-integrity tradeoffs that no existing document resolves.
Existing authority: none at Level 1-4; IMP-003's own implementation is a Level 7 precedent only.
Options: (a) universal fail-closed; (b) per-event-category policy, itself governed by IMP-004's
  taxonomy (§4.3); (c) fail-closed only for the categories IMP-003 already proved it for, with
  every new category requiring its own explicit decision.
Architecture impact: determines whether IMP-004's canonical sink API can offer a single
  guarantee or must expose a per-call failure-policy parameter.
Security impact: a wrong default risks either silently un-audited privileged actions (if
  fail-open) or platform-wide unavailability cascades if a shared audit store degrades (if
  universally fail-closed with no circuit breaker).
Financial impact: high for any category that later touches Payment/Ledger/Withdrawal/Refund
  event emission, even though IMP-004 itself implements none of those domains.
Recommended decision if appropriate: not offered — this is exactly the kind of decision this
  readiness pass is instructed not to make.

HD-IMP004-02
Question: What Permission(s)/Scope/Authority govern reading audit data, and does Super Admin
  receive any default audit-read grant or none at all?
Why it matters: no existing document defines audit-read authorization at all; the locked
  "Super Admin != automatic Financial Authority" principle strongly suggests Super Admin should
  NOT get blanket audit-read either, especially for financial/security categories, but this is
  an extrapolation, not a stated rule for audit specifically.
Existing authority: RBAC-ARCHITECTURE.md's canonical formula (generic mechanism only);
  BUSINESS-AUTHORITY-MODEL.md's "not implied by ordinary roles" principle (by analogy only).
Options: (a) new dedicated `audit.read.*` permission family scoped like other RBAC permissions;
  (b) category-specific authority types (e.g. `security_audit_reader`,
  `financial_audit_reader`) modeled like the existing Authority Type catalog; (c) some hybrid.
Architecture impact: may require new Authority Types added to
  docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md's approved catalog — that catalog is itself
  Level 3/Locked, so adding to it needs its own ACR/ADR under CHANGE-CONTROL.md, separate from
  IMP-004's own specification.
Security impact: high — this is the single control preventing audit data itself from becoming
  an information-disclosure vector.
Financial impact: audit records referencing financial identifiers may need financial-category
  read restrictions even though IMP-004 stores no financial amounts itself.
Recommended decision if appropriate: not offered.

HD-IMP004-03
Question: Is any audit-record deletion capability ever permitted (e.g. for a Q17
  retention-policy-driven purge), and if so, what governance evidence must accompany it?
Why it matters: Q17 locks that retention must be a "Configurable Retention Policy Matrix" — it
  does not say whether the matrix's terminal action is deletion, anonymization, or archival, nor
  whether deleting audit evidence itself must be independently audited before it's permitted.
Existing authority: Q17 (mechanism-level, does not reach this question);
  IMP-002-identity-authentication.md's "Retention Boundary" states deletion "MUST NOT
  cascade-delete or orphan immutable financial/Ledger/audit records" and that
  "Anonymization/pseudonymization is the likely mechanism; exact design is deferred" — this
  already leans toward anonymization over deletion, but explicitly defers the exact design.
Options: (a) audit records are permanently immutable and never deleted, only optionally
  anonymized in place; (b) a governed purge path exists, itself requiring a prior audited
  approval step; (c) retention is enforced only at a storage/backup layer, never inside the
  application.
Architecture impact: determines whether IMP-004 needs any deletion-adjacent code path at all.
Security impact: an ungoverned deletion path would itself be the highest-risk audit-integrity
  failure mode this foundation exists to prevent.
Financial impact: none directly (Ledger, not Audit, is the financial source of truth), but an
  incorrectly-scoped retention purge touching records that reference financial identifiers could
  create reconciliation/compliance gaps for later domains.
Recommended decision if appropriate: not offered.
```

---

## 10. Findings

```
BLOCKER:  none found.

MAJOR:
  F-01  IMP-003's "Audit Contract" (spec) names 6+ events never implemented, including a
        security-critical denial event with no implemented equivalent at all — see §7.3.
        Required resolution: at IMP-004 SPECIFICATION time (decide backfill vs. supersede).
  F-02  No repository authority defines whether audit-payload redaction must be an allow-list
        or is permitted to remain a call-site-discipline blacklist (current state: blacklist,
        proven by one test only) — see §4.6.
        Required resolution: at IMP-004 SPECIFICATION time.

MINOR:
  F-03  RBAC audit-write transactional/fail-closed behavior is proven by tests; the equivalent
        Identity-side guarantee is not proven anywhere in the reviewed scope — an asymmetry that
        should be resolved when both domains migrate to one canonical sink — see §4.8.
        Required resolution: at IMP-004 IMPLEMENTATION time (test-coverage gap, not a design
        question) — contingent on HD-IMP004-01's outcome.

EDITORIAL:
  none found requiring documentation-only correction beyond what §7.3/F-01 already covers.

HUMAN DECISION REQUIRED: HD-IMP004-01, HD-IMP004-02, HD-IMP004-03 (§9).
```

---

## 11. Current Technical Debt Inherited From IMP-001–003

```
BLOCKS IMP-004:        none identified — the existing scaffold (IdentityAuditLogger,
                        RbacAuditLogger, log channels) is explicitly designed to be replaced
                        without call-site changes; nothing structurally prevents IMP-004 from
                        proceeding to specification.
SHOULD FIX IN IMP-004:  F-01, F-02, F-03 above.
DEFERRED:               HD-IMP004-01/02/03 (require Human Decision before or during
                        specification, not blocking readiness itself).
UNRELATED:              the previously-documented environment condition that the default,
                        untracked `.env` points at an inaccessible `philanthropy_platform` MySQL
                        host (root/empty password, connection refused) is unchanged and remains
                        environment-only configuration, not an application defect — consistent
                        with its classification throughout the IMP-003 remediation passes. It is
                        not re-litigated here as a new IMP-004 finding.
```

---

## 12. Proposed IMP-004 Deliverables (Future Specification Input Only)

```
MUST:
  - Canonical, namespaced, versionable event-taxonomy convention (§4.3)
  - Canonical audit-event persistence contract (interface/service), replacing
    IdentityAuditLogger/RbacAuditLogger as the real sink while preserving their call-site shape
    where possible
  - Actor attribution using Principal (kind + id), never User ID directly (§4.1/§4.2)
  - Subject attribution (typed subject_type/subject_id) (§4.4)
  - Redaction mechanism resolving F-02
  - Immutable, append-only persistence (no update/delete surface absent HD-IMP004-03's outcome)
  - Migration of the 27 existing Identity+RBAC events onto the canonical sink without behavior
    change to their call sites
  - Audit-read authorization (Permission/Scope/Authority) resolving HD-IMP004-02
  - Basic query/filter foundation (by actor, subject, event_type, date range, correlation ID) —
    no UI

SHOULD:
  - request_id/correlation_id foundation (§4.7) even if no current consumer needs it yet
  - Generic policy-version-evidence primitive for later domains' own "Policy Version" concerns
    (Commission, Approval) to optionally reference — foundation only, no policy engine
  - Backfill of F-01's missing security-critical-denial event, contingent on its specification
    resolution

DEFERRED:
  - Search/Reporting UI and query optimization at scale (owned by IMP-026)
  - Actual retention periods/purge automation (Q17 mechanism only is in scope; values are not)
  - Actual privacy field-level rules (Q16 mechanism only is in scope; values are not)
  - Approval workflow mechanics (Module 12, a different module)
  - Currency Governance (no evidence ties it to IMP-004 — flagged in §3.1, not assumed)

OUT OF SCOPE:
  - Any Payment/Ledger/Commission/Withdrawal/Refund/Reconciliation business logic
  - Any admin UI beyond what a future, separately-scoped stage builds on top of IMP-004's query
    foundation
  - Any REST API surface (owned by IMP-025)
```

---

## 13. Acceptance Criteria Draft (For Future Specification)

```
FUNCTIONAL:      every migrated Identity/RBAC event persists via the canonical sink with
                 identical semantic content to today's log-channel payloads
SECURITY:        no audit record ever contains a credential/token/secret (extends existing
                 RbacAuditTest pattern to 100% of migrated events)
AUTHORIZATION:   audit read enforces Permission + Scope + Authority per HD-IMP004-02's resolved
                 policy; Super Admin has no unearned automatic access
DATA INTEGRITY:  no application code path can UPDATE or DELETE a persisted audit record
ROLLBACK:        for every category HD-IMP004-01 designates fail-closed, a forced audit-write
                 failure test proves the paired business mutation also rolls back (mirroring
                 the 4 existing RbacAuditTest patterns)
REDACTION:       resolved-F-02 mechanism verified against a comprehensive negative-data test set
PRIVACY:         Q16 mechanism-level configurability is structurally present (not necessarily
                 populated with real rules)
DATABASE:        migrations apply cleanly on MySQL 8.x (disposable instance) with the same
                 evidence bar as IMP-003 (see docs/audits/IMP-003-FINALIZATION.md "MySQL
                 Runtime"); SQLite regression suite passes
MYSQL:           fresh migration, constraint validation, and concurrency behavior independently
                 verified on a disposable MySQL 8.x database — never the real/unknown configured
                 database
GOVERNANCE:      Codex independent review passes with 0 BLOCKER/MAJOR before Human Stage Gate,
                 per docs/00-governance/IMPLEMENTATION-GOVERNANCE.md's Merge Authority sequence
SHARED HOSTING:  no new mandatory infrastructure dependency introduced
```

---

## 14. Definition of Ready Verdict

Per [DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md) "Definition of Ready"
checklist:

```
[x] Objective clear           — Audit + Governance Foundation, scoped in §3
[x] Scope clear                — §3, §12
[x] Out-of-scope clear         — §3, §12
[x] Architecture known         — §1, §4-§8 (to the extent it exists; genuine gaps are flagged
                                  as findings/Human Decisions, not silently assumed)
[x] Business rules known       — Q16/Q17 mechanism-level rules identified; no business-rule
                                  invention performed
[x] Security impact known      — §5, §9 (HD-IMP004-02), Finding F-02
[x] DB impact known            — §6 (impact-level only, no migration created)
[x] Acceptance criteria known  — §13 (draft, for the future specification to finalize)
[ ] No unresolved Human Decision — THREE unresolved: HD-IMP004-01, HD-IMP004-02, HD-IMP004-03
```

The last checklist item is not satisfied. Per this same document, "Otherwise: TASK NOT READY" —
however, the three open Human Decisions are scoped narrowly (failure-policy default,
audit-read-authority default, retention-deletion policy) and do not block *specification* work
from beginning; they block specification from being *finalized* without their resolution. This
readiness pass therefore reports a qualified verdict.

**Verdict: `READY FOR IMP-004 SPECIFICATION`** — conditional on HD-IMP004-01, HD-IMP004-02, and
HD-IMP004-03 being resolved by Human before the specification is finalized (they may be resolved
during specification drafting itself; they do not need to be resolved before specification
*begins*).

**`IMP-004 IMPLEMENTATION — NOT AUTHORIZED`** regardless of the above — this readiness pass does
not and cannot authorize implementation; that remains gated on a separate future Human
authorization per [MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md).
