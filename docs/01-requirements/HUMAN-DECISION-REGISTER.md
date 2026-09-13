# Human Decision Register

```
Source Status:        Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy
Semantic Change:       NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. Q1-Q20 were materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization), from the Human
Decision Register content supplied directly in that authorization. Q21-Q25 were materialized
during IMP-002 specification/readiness work, from Human Decision content supplied directly to
those tasks (Q21-Q23 during the "IMP-002 Targeted Specification Remediation" task; Q24-Q25 during
the "IMP-002 Targeted Readiness Remediation" task) — see
[docs/audits/IMP-002-SPECIFICATION-REMEDIATION-1.md](../audits/IMP-002-SPECIFICATION-REMEDIATION-1.md)
and
[docs/audits/IMP-002-TARGETED-READINESS-REMEDIATION-1.md](../audits/IMP-002-TARGETED-READINESS-REMEDIATION-1.md).
No entry below was invented, reinterpreted, or simplified beyond what was supplied.

Q26-Q28 were materialized during the "IMP-004 — Human Decision Materialization + Specification"
task, resolving HD-IMP004-01/HD-IMP004-02/HD-IMP004-03 — the three Human Decisions this same task
identified as required by
[docs/implementation/IMP-004-audit-governance-readiness.md](../implementation/IMP-004-audit-governance-readiness.md)
— from Human Decision content ("Saya setuju dengan HD-IMP004-01, HD-IMP004-02, dan HD-IMP004-03.")
supplied directly to that task. See
[docs/implementation/IMP-004-audit-governance-foundation.md](../implementation/IMP-004-audit-governance-foundation.md)
for the specification these decisions govern. Unlike Q1-Q25 (a repository copy of a pre-existing
external baseline), Q26-Q28 are new Human Decisions made directly within this repository's
implementation process — they carry the same Level 1 authority per
[docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md) ("Human Decision
Register — explicit approved Human changes").

Q29-Q33 materialize the explicit Human final decisions supplied in the task
"IMP-005 CMS - HUMAN DECISION MATERIALIZATION PATCH", resolving HD-IMP005-01 through
HD-IMP005-05. These are FINAL / LOCKED Human decisions with Level 1 authority, not engineering
choices or AI approvals. The Human authorized continuing from the existing local documentation
patch in this session. Earlier option references are superseded by the final baseline below.
These decisions grant no implementation, merge, push or IMP-006 authorization.

```
HD-IMP005-01 - FINAL / LOCKED (Q29)
HD-IMP005-02 - FINAL / LOCKED (Q30)
HD-IMP005-03 - FINAL / LOCKED (Q31)
HD-IMP005-04 - FINAL / LOCKED (Q32)
HD-IMP005-05 - FINAL / LOCKED (Q33)
Human Decisions Required: 0 (IMP-005)
OPEN HUMAN DECISIONS: 0 (IMP-005)
```

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this
register is Level 1 — the highest authority in this repository. No Master Requirement,
architecture document, ADR, implementation specification, governance document, or code may
override an entry here without an approved change to the entry itself.

## Register

```
Q1  — A  Single Organization
Q2  — A  Centralized Settlement
Q3  — D  Configurable Commission
Q4  — D  Configurable Operational Fee
Q5  — C  Hybrid Recurring Donation
Q6  — A  Public Anonymity Only
Q7  — D  Hybrid Beneficiary Registration
Q8  — D  Fully Configurable Document Policy
Q9  — B  Designated Zakat Administrator
Q10 — A  Wakaf as Standard Donation Product
Q11 — D  Hybrid Qurban Model
Q12 — D  Configurable Refund Approval Matrix
Q13 — D  Configurable Financial Approval Matrix
Q14 — D  Configurable Distribution Approval Matrix
Q15 — D  Configurable Withdrawal + Approval Matrix
Q16 — D  Configurable Privacy Model
Q17 — D  Configurable Retention Policy Matrix
Q18 — D  Configurable Versioned Receipt System
Q19 — D  Configurable Versioned Compliance Document System
Q20 — C  IDR Base + Limited Multi-Currency
Q21 — A  Email as Canonical Login Identifier
Q22 — A  Hybrid Registration by Actor
Q23 — A  Configurable MFA with TOTP Baseline
Q24 — A  Separated Identity Lifecycle + Verification + Security Restriction Model
Q25 — A  Controlled One-Time CLI Bootstrap for First Super Admin
Q26 — A  Per-Category Audit Write Failure Semantics (Critical Fail-Closed, Default Fail-Closed)
Q27 — A  Explicit Permission + Domain-Aware Scope for Audit Read (No Automatic Super Admin Access)
Q28 — A  Append-Only Audit Operational Model + Governed Retention Purge
Q29 - FINAL / LOCKED  No Editorial Approval Workflow in CMS v1 (Authoring/Publishing Permission Separation)
Q30 - FINAL / LOCKED  CMS Menu/Navigation Presentation Configuration Deferred to IMP-006 Theme Engine
Q31 - FINAL / LOCKED  CMS v1 Single-Locale Content Baseline (Additive-Ready, No Translation Engine)
Q32 - FINAL / LOCKED  Minimal Scheduled Publish/Unpublish Supported in CMS v1 (Scheduler + Cron Only)
Q33 - FINAL / LOCKED  News Is a Classification of Article, Not an Independent CMS Entity
```

Q21-Q33 are detailed in "Extended Decisions — Detailed Rules (Q21-Q33)" below; Q1-Q20 above
retain their original one-line form as supplied during IMP-001 Readiness Remediation Pass 1.

## Extended Decisions — Detailed Rules (Q21-Q33)

### Q21 — Email as Canonical Login Identifier

```
Email is the canonical human login identifier.
Baseline authentication = email + password.
Phone may exist as contact/verification data.
Phone is not a baseline login identifier.
Username is not a baseline login identifier.
Multi-identifier login is outside the baseline.
Adding a second login identifier later requires change control (ACR/ADR) where it would alter
  this locked identity contract.
```

### Q22 — Hybrid Registration by Actor

```
Donor                              -> self-registration allowed
Fundraiser                         -> self-registration allowed; no automatic Fundraiser
                                       business authority
Partner Representative             -> invitation / domain-driven provisioning; no public
                                       self-registration baseline
Internal Administrative Identity   -> invitation/provisioning only; no public self-registration
Super Admin                        -> provisioning only; no public self-registration
```

Other actors follow the existing authoritative actor identification in
[docs/01-requirements/MASTER-REQUIREMENTS.md](MASTER-REQUIREMENTS.md) §3/§6 and
[docs/05-rbac/DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md) (e.g. Beneficiary registration
follows Q7, not Q22).

### Q23 — Configurable MFA with TOTP Baseline

```
MFA capability is configurable, not universally mandatory per identity.
TOTP is the baseline supported mechanism.
MFA may satisfy ELEVATED Authentication Assurance.
MFA enrollment/success grants no role, permission, business authority, or financial authority.
Secure recovery and re-enrollment lifecycle is required.
SMS OTP is not the baseline MFA mechanism.
```

### Q24 — Separated Identity Lifecycle + Verification + Security Restriction Model

```
Identity Lifecycle (persistent):        ACTIVE, DISABLED
Verification (separate field/state):    email_verified_at (not a lifecycle enum value;
                                         "pending_verification" is NOT a persistent User
                                         lifecycle state)
Persistent Security Restriction:        NONE, SUSPENDED
Transient Brute-Force Lockout:          NOT a persistent User lifecycle state — handled via
                                         rate limiting/throttling only, never a stored "LOCKED"
                                         status
```

Authority: IMP-002 does not define which role/authority may change persistent security state.
Persistent transitions (ACTIVE<->DISABLED, NONE<->SUSPENDED) require a future authorized security
authority under IMP-003 (or later approved security workflow), or an explicitly authorized
deterministic security mechanism — not invented by IMP-002.

Business boundary: the following are never stored as `User` identity status — they are Business
Authority concepts (docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md), not Identity concepts:

```
Fundraiser approval
Partner approval
Beneficiary approval
Financial approval
Campaign authority
```

### Q25 — Controlled One-Time CLI Bootstrap for First Super Admin

```
No public bootstrap web endpoint.
No public Super Admin self-registration.
Controlled Artisan/CLI provisioning only, operator-initiated.
Operator supplies identity and credentials interactively (no default email/password).
No plaintext credential in code, config, seeder, log, or audit payload.
Password hashed through the approved framework hasher.
An auditable bootstrap event/evidence is recorded.
Repeat initial bootstrap is refused once completed (durable one-time guard).
Mechanism is shared-hosting-compatible (an Artisan command run during deployment/maintenance,
  not a permanently running service).
IMP-002 does not invent a separate/parallel Super Admin role system.
Canonical RBAC Super Admin authority (role/permission/scope/business authority) remains owned by
  IMP-003.
Later Super Admin provisioning (beyond the first one) does not use this first-bootstrap
  mechanism — it uses whatever ordinary provisioning path IMP-003+ establishes.
```

### Q26 — Per-Category Audit Write Failure Semantics

```
Security/authority/governance-critical audit events use one atomic transaction: business/
  authority mutation + audit append together; if the critical audit append fails, the mutation
  ROLLS BACK. No silent audit loss for a critical event.
Critical categories include at minimum: identity lifecycle; persistent security restriction;
  Principal lifecycle; role assignment/revocation; permission grant/revoke; scope mutation;
  business-authority mutation; privileged governance/security operations; future approval/
  financial authority operations when applicable.
Default for any event whose failure category has not yet been explicitly classified: FAIL-CLOSED.
A future event may use less restrictive (non-critical/fail-open-with-alert) failure semantics
  only when its category is explicitly classified and authorized — never silently, and never
  merely for availability.
```

This canonizes, as a Level 1 rule, the fail-closed/transactional behavior IMP-003 already
implemented for RBAC mutations (proven by tests) and extends it as the IMP-004 default for every
current and future critical category, resolving readiness finding HD-IMP004-01. See
[docs/implementation/IMP-004-audit-governance-foundation.md](../implementation/IMP-004-audit-governance-foundation.md)
for the exact criticality classification table.

### Q27 — Explicit Permission + Domain-Aware Scope for Audit Read

```
Audit-read authorization is never inferred merely from role name. It follows the canonical
  authorization architecture (docs/05-rbac/RBAC-ARCHITECTURE.md "Canonical Authorization
  Evaluation"): Authenticated AND Applicable Subject Context AND Permission AND Domain-Aware
  Scope AND Ownership/Subject Rule where applicable AND Business Authority where applicable AND
  Authentication Assurance where required AND No Security Restriction.
Default: DENY.
Super Admin does NOT automatically receive unrestricted audit access — this preserves "Super
  Admin != automatic Financial Authority" (docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md), applied to
  audit visibility.
Audit visibility must never become a mechanism for bypassing the source domain's own authority or
  confidentiality boundary. Financial, security, and privacy-sensitive audit information remains
  subject to the applicable domain's own authority/visibility restrictions.
IMP-004 creates the generic audit-read authorization foundation only — it does not invent
  unrelated domain permissions or prematurely implement future financial/domain authority.
```

Resolves readiness finding HD-IMP004-02.

### Q28 — Append-Only Audit Operational Model + Governed Retention Purge

```
Ordinary audit record behavior: CREATE/APPEND allowed only through the authorized canonical audit
  sink; UPDATE prohibited; arbitrary DELETE prohibited; manual/administrative DELETE prohibited.
Q17 (Configurable Retention Policy Matrix) remains possible: a future retention purge may exist
  ONLY through an authorized retention policy -> a versioned policy -> eligibility determination
  -> an authorized controlled purge process -> purge governance evidence of its own.
IMP-004 does not invent retention durations — retention values remain configurable through a
  future authorized policy, per Q17.
Purge must never be usable to: conceal activity; rewrite audit history; perform discretionary
  administrator deletion; bypass an investigation/security/financial hold; or destroy records
  merely because they are inconvenient.
Where an applicable authorized hold exists, purge is PROHIBITED until the hold is legitimately
  released.
IMP-004 provides structural compatibility/foundation only — it does not build a complete
  retention-policy engine.
```

Resolves readiness finding HD-IMP004-03.

### Q29 — No Editorial Approval Workflow in CMS v1

```
No dedicated editorial approval workflow exists in CMS v1.
Authoring/editing authority and publishing authority are separated by distinct permissions;
  a principal allowed to create or edit content does NOT automatically hold publish rights.
No reviewer queue, no multi-step editorial approval, no editorial approval matrix on the IMP-005
  baseline; no workflow engine is introduced by IMP-005.
Any future editorial approval workflow requires a new Human Decision / change control before it
  may be specified or implemented.
```

Resolves HD-IMP005-01 - FINAL / LOCKED (Human decision).

### Q30 — CMS Menu/Navigation Presentation Configuration Deferred to IMP-006

```
Menu/navigation presentation configuration is deferred to IMP-006 Theme Engine.
IMP-005 provides only canonical CMS content plus the link/content destination contract that
  navigation needs as its target (published content paths / public identifiers).
IMP-005 builds no menu builder, navigation composition engine, theme navigation configuration
  or presentation-specific menu layout.
Preserved (unchanged, applies to every stage): navigation visibility NEVER replaces backend
  authorization.
```

Resolves HD-IMP005-02 - FINAL / LOCKED (Human decision).

### Q31 — CMS v1 Single-Locale Content Baseline

```
CMS v1 uses a single-locale content baseline.
Multilingual CMS content is NOT implemented in IMP-005: no translation tables, no locale-specific
  publication workflow/status, localized revision tables, localized-slug framework, fallback engine
  or language-switching business logic.
Additive-ready means stable content IDs, explicit text-field ownership and non-language-specific
  internal IDs; no speculative locale columns, translation joins, locale indexes or fallback trees.
Any future change to multilingual content requires an appropriate stage / change control with its
  own Human Decision.
```

Resolves HD-IMP005-03 - FINAL / LOCKED (Human decision).

### Q32 — Minimal Scheduled Publish/Unpublish in CMS v1

```
Scheduled publishing and unpublishing ARE supported in CMS v1 in minimal form.
Baseline uses timestamp-based scheduling (publish_at and/or unpublish_at per the final IMP-005
  specification design); scheduling is not an independent mutable queue.
Execution uses Laravel Scheduler + Cron and must remain shared-hosting compatible.
It MUST NOT require Redis server, Supervisor, PM2, WebSocket, Node production runtime, or any
  additional infrastructure service.
Scheduled transitions remain fully subject to the applicable authorization, lifecycle, transaction,
  and canonical audit semantics — the scheduler executes through the ordinary guarded service
  paths, never a bypass.
```

Resolves HD-IMP005-04 - FINAL / LOCKED (Human decision).

### Q33 — News Is a Classification of Article, Not an Independent Entity

```
News does NOT become an independent CMS entity on the IMP-005 baseline.
Article is the canonical editorial content entity; News is represented through the
  classification/type/category mechanism of the final IMP-005 specification design.
Revision, publication, authorization, audit, slug, scheduling, SEO and content lifecycle must NOT be duplicated
  merely for News.
This unification applies only to generic editorial CMS content. Campaign, Partner, Fundraiser,
Beneficiary, Distribution, Impact, Donation and financial domains keep canonical data in
  their own domains; business-domain content is not moved into Article/CMS.
```

Resolves HD-IMP005-05 - FINAL / LOCKED (Human decision).

## Superseded Decisions

```
Old Q3-A — No Commission — SUPERSEDED by Q3-D (Configurable Commission)
```

Superseded decisions are retained for traceability only. Do not resurrect a superseded decision
without a new explicit Human Decision.

## Change Control

Any change to an entry in this register is a Human Decision change. Per
[docs/00-governance/CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md), Human Decision
Required is fixed at YES without exception for such a change — no AI agent may approve it.
