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
```

Q21-Q25 are detailed in "Extended Decisions — Detailed Rules (Q21-Q25)" below; Q1-Q20 above
retain their original one-line form as supplied during IMP-001 Readiness Remediation Pass 1.

## Extended Decisions — Detailed Rules (Q21-Q25)

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
