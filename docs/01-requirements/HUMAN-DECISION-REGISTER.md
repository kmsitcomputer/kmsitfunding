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

Q29-Q33 were materialized during the "IMP-005 CMS — Single AI Agent" specification audit/
remediation task, resolving HD-IMP005-01/HD-IMP005-02/HD-IMP005-03/HD-IMP005-04/HD-IMP005-05 — the
five Human Decisions
[docs/implementation/IMP-005-cms.md](../implementation/IMP-005-cms.md) section 25 records as
FINAL/LOCKED with Human provenance ("the Human supplied all five final decisions directly in the
task 'IMP-005 CMS - HUMAN DECISION MATERIALIZATION PATCH'"). This register entry closes a
materialization gap identified during the IMP-005 Single AI Agent specification audit: the
decisions existed in IMP-005-cms.md §25 with clear Human provenance, but had not yet been
transcribed into this Level 1 register. Per this register's own Change Control section, that
transcription itself required explicit Human authorization; it was given directly: "I explicitly
authorize Claude Code to materialize the already FINAL / LOCKED HD-IMP005-01 through HD-IMP005-05
into the authoritative Human Decision Register, without changing their semantics, and to resume the
IMP-005 Single AI Agent specification re-audit workflow afterward." Like Q26-Q28, Q29-Q33 are new
Human Decisions made directly within this repository's implementation process — they carry the
same Level 1 authority per
[docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md).

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
Q29 — A  No Dedicated Editorial Approval Workflow in CMS v1 (Editing != Publishing Authority)
Q30 — A  Menu/Navigation Presentation Belongs to IMP-006 Theme Engine
Q31 — A  Single-Locale CMS v1 (No Speculative Multilingual Infrastructure)
Q32 — A  Minimal Scheduled Publish/Unpublish via Laravel Scheduler + Cron
Q33 — A  Article Is Canonical; News Is Article Classification (No Duplicate Entity/Mechanisms)
```

Q21-Q28 are detailed in "Extended Decisions — Detailed Rules (Q21-Q28)" below; Q29-Q33 are detailed
in "Extended Decisions — Detailed Rules (Q29-Q33)" further below; Q1-Q20 above retain their
original one-line form as supplied during IMP-001 Readiness Remediation Pass 1.

## Extended Decisions — Detailed Rules (Q21-Q28)

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

## Extended Decisions — Detailed Rules (Q29-Q33)

Source: Human authorization message during the "IMP-005 CMS — Single AI Agent" task ("HUMAN
DECISION — IMP-005 GOVERNANCE REGISTER AUTHORIZATION"), transcribed verbatim below without
reinterpretation, broadening, or narrowing. Each resolves the correspondingly-numbered HD-IMP005-0N
recorded in [docs/implementation/IMP-005-cms.md](../implementation/IMP-005-cms.md) section 25.

### Q29 — No Dedicated Editorial Approval Workflow in CMS v1 (resolves HD-IMP005-01)

```
No dedicated editorial approval workflow in CMS v1.
Editing/authoring authority is separate from publishing authority.
Creator/editor does not automatically receive publishing authority.
No reviewer queue, multi-step editorial approval workflow, or editorial approval matrix is
  introduced in CMS v1.
```

### Q30 — Menu / Navigation Ownership (resolves HD-IMP005-02)

```
Menu/navigation presentation configuration belongs to IMP-006 Theme Engine.
IMP-005 owns canonical CMS content and destination/link contracts only.
IMP-005 does NOT implement: menu builder; navigation composition engine; Theme Engine.
Navigation visibility never replaces backend authorization.
```

### Q31 — Multilingual Scope (resolves HD-IMP005-03)

```
CMS v1 is SINGLE LOCALE.
Future localization may remain additive-ready.
IMP-005 does NOT implement: translation tables; locale publication workflow; localized slug
  engine; locale fallback engine.
A future multilingual capability requires appropriate change control.
```

### Q32 — Scheduled Publication (resolves HD-IMP005-04)

```
CMS v1 supports minimal timestamp-based scheduled publish and scheduled unpublish.
Execution must remain compatible with Laravel Scheduler + Cron and shared-hosting deployment.
No mandatory Redis server; Supervisor; PM2; WebSocket server; Node.js production runtime;
  additional always-running worker infrastructure.
Scheduled transitions remain subject to canonical authorization, lifecycle, transaction,
  concurrency, System Principal, and audit semantics.
```

### Q33 — Article / News Model (resolves HD-IMP005-05)

```
News is NOT an independent canonical CMS entity.
Canonical editorial content remains Article.
News is represented through authorized Article classification/type/category semantics.
Do not duplicate: revision model; publication lifecycle; path model; authorization; audit;
  scheduling; content ownership.
```

Resolves readiness findings HD-IMP005-01 through HD-IMP005-05.

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
