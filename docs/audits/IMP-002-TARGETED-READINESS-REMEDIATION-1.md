# IMP-002 Targeted Readiness Remediation 1

Task:
IMP-002 Targeted Readiness Remediation

Branch:
impl/002-identity-authentication (verified via `git branch --show-current` before any change)

Coding Performed:
NO

## Human Decision Materialization

```text
Q21 — FINAL / LOCKED — CANONICAL REGISTER: YES (docs/01-requirements/HUMAN-DECISION-REGISTER.md)
Q22 — FINAL / LOCKED — CANONICAL REGISTER: YES
Q23 — FINAL / LOCKED — CANONICAL REGISTER: YES
Q24 — FINAL / LOCKED — CANONICAL REGISTER: YES (newly materialized this pass)
Q25 — FINAL / LOCKED — CANONICAL REGISTER: YES (newly materialized this pass)
```

Q21-Q23 had previously been recorded only in the IMP-002 specification itself (not in the
canonical Level 1 register) — this was finding B01. Q24 and Q25 were supplied directly to this
task by the Human Authority and are new to the register.

## Finding-by-Finding Resolution

### IMP002-RDY-B01 — Q21-Q23 not materialized in canonical Level-1 register

```text
Authority:    Human-approved decisions supplied to prior IMP-002 tasks, previously recorded only
              in docs/implementation/IMP-002-identity-authentication.md
Resolution:   Added Q21-Q25 to docs/01-requirements/HUMAN-DECISION-REGISTER.md ("Register" one-
              line entries plus a new "Extended Decisions — Detailed Rules (Q21-Q25)" section).
              Also updated docs/01-requirements/MASTER-REQUIREMENTS.md's Q1-Q20 reference to
              Q1-Q25 (targeted reference update only, not a rewrite).
Files changed: docs/01-requirements/HUMAN-DECISION-REGISTER.md,
              docs/01-requirements/MASTER-REQUIREMENTS.md
New semantics? NO — no rule was reinterpreted; content matches what was supplied to the prior
              and current tasks.
Human decision source: Prior IMP-002 tasks (Q21-Q23) and this task (Q24-Q25).
Status:        RESOLVED
```

### IMP002-RDY-M01 — Canonical email-change lifecycle incomplete

```text
Authority:    Q21 (email is canonical login identifier) makes an email change a sensitive,
              security-relevant operation; no prior version of this specification defined its
              lifecycle beyond "verification lifecycle... on... email change."
Resolution:   Added "Canonical Email Change Lifecycle" section: pending_email field, fresh-
              credential (+ ELEVATED where policy requires) confirmation to initiate, normalization
              and uniqueness (application check + final database-constraint authority), no
              promotion until new-email verification succeeds, atomic promotion (session rotation,
              other-session invalidation, stale reset-token invalidation, audit), rollback/
              cancellation leaves the current email unchanged, and explicit concurrency handling.
              Also added the password-reset interaction (task item 19) and cross-referenced it
              from "Password Reset."
Files changed: docs/implementation/IMP-002-identity-authentication.md
New semantics? NO — this elaborates the mechanics of an already-scoped concern (email is the
              canonical identifier); it does not change Q21 or any other locked decision.
Status:        RESOLVED
```

### IMP002-RDY-M02 — Invitation issuer/revocation state incomplete

```text
Authority:    Actors table already required invitation for Partner Representative/Internal
              Administrative Identity (Q22); no prior version specified issuer/revocation fields.
Resolution:   Expanded "Invitation Boundary" with a full field list (id, public_id, intended_email,
              token_hash, issued_at, expires_at, accepted_at, revoked_at, issuer reference, revoker
              reference, timestamps), explicit revocation-before-expiry support, and the
              issuer-reference-is-attribution-only boundary (recording who issued/revoked is not
              itself role authority). Added the invitation/registration email-uniqueness race
              (task item 20): the database's unique constraint is the final authority; acceptance
              either fails safely with a generic error pending reconciliation, or binds to the
              already-existing identity without granting any business authority — IMP-002 does not
              choose between those two for the owning domain workflow, only requires neither ever
              produces duplicate identities.
Files changed: docs/implementation/IMP-002-identity-authentication.md
New semantics? NO — elaborates invitation mechanics already in scope; does not grant any new
              authority.
Status:        RESOLVED
```

### IMP002-RDY-M03 — Authentication assurance lifecycle not executable

```text
Authority:    docs/05-rbac/AUTHENTICATION-ASSURANCE.md establishes STANDARD/ELEVATED conceptually
              but (correctly) does not fix mechanics — IMP-002 must make it executable.
Resolution:   Added session-scoped representation (assurance_level, elevated_at, elevated_until —
              explicitly NOT a User column), STANDARD/ELEVATED creation conditions, a finite
              configurable lifetime with automatic fallback, an explicit invalidation-trigger list
              (logout, password change, email-change completion, MFA reset/disable/re-enrollment,
              SUSPENDED/DISABLED transition, any session invalidation), and the rule that a new
              session never inherits prior ELEVATED.
Files changed: docs/implementation/IMP-002-identity-authentication.md
New semantics? NO — AUTHENTICATION-ASSURANCE.md's own STANDARD/ELEVATED states and the "may
              satisfy ELEVATED" language for MFA (Q23) are preserved unchanged; only the
              executable mechanics were added, as that document itself directs IMP-002 to do.
Status:        RESOLVED
```

### IMP002-RDY-M04 — MFA security contract ambiguous

```text
Authority:    AGENTS.md "Never": "No custom cryptography." An earlier revision of this
              specification offered a hand-rolled hash_hmac-based RFC 6238 implementation as an
              acceptable alternative to a maintained library — on reconsideration this created
              ambiguity about whether custom TOTP code was permitted.
Resolution:   "MFA > Implementation Contract" now PROHIBITS a custom/minimal direct
              implementation outright and REQUIRES a maintained, security-reviewed, RFC-
              6238-compliant third-party library, with explicit selection criteria (maintenance,
              compatibility, no mandatory external service/daemon, secure secret generation,
              configurable verification window, security history, license, pinned/reviewed at
              implementation time). Also added an explicit, narrowly-scoped TOTP replay-protection
              requirement for enrollment confirmation, ELEVATED step-up, and reset confirmation
              (task item 12), and tightened MFA reset/disable to require BOTH fresh credential
              confirmation AND ELEVATED assurance.
Files changed: docs/implementation/IMP-002-identity-authentication.md
New semantics? NO — Q23 (configurable MFA, TOTP baseline) is unchanged; this narrows an
              implementation-contract ambiguity, it does not alter the Human Decision.
Status:        RESOLVED
```

### IMP002-RDY-M05 — Account/security status authority unresolved

```text
Authority:    Q24 (this task) — Separated Identity Lifecycle + Verification + Security Restriction
              Model.
Resolution:   Replaced the single "active/pending_verification/suspended/disabled/locked" proposal
              with Q24's three-part model: Identity Lifecycle (ACTIVE/DISABLED, persistent),
              Verification (email_verified_at, a field not a lifecycle value — no persistent
              "pending_verification"), and Security Restriction (NONE/SUSPENDED, persistent,
              separate from Identity Lifecycle). Transient brute-force lockout is explicitly
              handled via rate limiting/counters only, never a persistent "LOCKED" status. Added
              explicit transition rules (ACTIVE<->DISABLED, NONE<->SUSPENDED), their required
              effects (deny auth, invalidate sessions/assurance), and the authority boundary
              (IMP-002 does not define who may transition state — that is IMP-003+ or an
              explicitly authorized deterministic mechanism). Reaffirmed the business-status
              boundary (Fundraiser/Partner/Beneficiary/Financial approval and Campaign authority
              are never a `User` status).
Files changed: docs/implementation/IMP-002-identity-authentication.md,
              docs/01-requirements/HUMAN-DECISION-REGISTER.md (Q24 detail)
New semantics? NO relative to Q24 itself (this task supplied Q24 directly); this resolves the
              finding by applying it in place of the prior ad hoc proposal.
Status:        RESOLVED (via Q24)
```

### IMP002-RDY-M06 — First Super Admin bootstrap undefined

```text
Authority:    Q25 (this task) — Controlled One-Time CLI Bootstrap for First Super Admin.
Resolution:   Added "First Super Admin Bootstrap" section: non-public, one-time, operator-
              initiated Artisan/CLI provisioning; no default identity/credential; password hashed
              through the framework hasher; no plaintext credential anywhere; auditable bootstrap
              evidence; durable one-time guard rejecting a repeat initial bootstrap; shared-
              hosting-compatible (one-shot command, not a daemon); explicit scope boundary
              (IMP-002 creates the Identity only; IMP-003 owns canonical Super Admin role/
              permission/scope/authority; IMP-002 does not invent a parallel role system). Added a
              dedicated "First Super Admin bootstrap evidence" table to "Database Design
              Requirements" (a durable guard/audit marker, storing no credential).
Files changed: docs/implementation/IMP-002-identity-authentication.md,
              docs/01-requirements/HUMAN-DECISION-REGISTER.md (Q25 detail)
New semantics? NO relative to Q25 itself (supplied directly by this task).
Status:        RESOLVED (via Q25)
```

### IMP002-RDY-m01 — Remember-me unresolved

```text
Authority:    No materialized document requires remember-me for the IMP-002 baseline; leaving it
              ambiguous (previously: "whether to enable it is an implementation-time UX decision")
              was itself the finding.
Resolution:   Explicitly set REMEMBER-ME = DEFERRED/DISABLED for the IMP-002 baseline.
              `remember_token` removed from the required `users` field list. Reasoning recorded
              (persistent authentication introduces its own revocation/security semantics not
              needed for the initial foundation); future enablement requires a specification
              update, since every session-invalidation trigger in this document would then need an
              explicit remember-me-credential invalidation step too — each such point in this
              specification now already notes that it "would" cover a remember-me credential "if
              ever enabled," so a future update has a checklist to work from.
Files changed: docs/implementation/IMP-002-identity-authentication.md
New semantics? NO — no architecture or Human Decision required this determination; it is an
              IMP-002-scope decision that removes an ambiguity, consistent with governance's
              instruction not to leave implementation-time discretion where it creates security
              ambiguity.
Status:        RESOLVED
```

### IMP002-RDY-m02 — Password-change session consequences incomplete

```text
Authority:    General security-architecture principle (session state must not silently survive a
              credential change) already implied by SECURITY-INVARIANTS.md; no prior version of
              this specification made the full consequence list explicit for an *ordinary*
              password change (only password reset had "other sessions ARE invalidated").
Resolution:   Added "Credential Model > Password Change — Session Consequences": rehash, current-
              session rotation, ALL other sessions invalidated, ALL ELEVATED assurance invalidated,
              all outstanding password-reset tokens invalidated, remember-me credential
              invalidated if ever enabled, audit event. Cross-referenced from "Password Reset"
              ("same or stronger" consequences) and from "Authentication Assurance" (password
              change listed among ELEVATED's immediate invalidation triggers).
Files changed: docs/implementation/IMP-002-identity-authentication.md
New semantics? NO — elaborates mechanics already implied by SECURITY-INVARIANTS.md's "a security
              decision is never delegated to the client" and general session-integrity principles.
Status:        RESOLVED
```

## Summary

```text
IMP002-RDY-B01   RESOLVED
IMP002-RDY-M01   RESOLVED
IMP002-RDY-M02   RESOLVED
IMP002-RDY-M03   RESOLVED
IMP002-RDY-M04   RESOLVED
IMP002-RDY-M05   RESOLVED via Q24
IMP002-RDY-M06   RESOLVED via Q25
IMP002-RDY-m01   RESOLVED
IMP002-RDY-m02   RESOLVED
```

## Governance Check

```text
application code changed:   NO
migration created:           NO
dependencies changed:        NO
routes created:               NO
model created:                 NO
controller created:            NO
Vue page created:              NO
```

Only: Level 1 Human Decision materialization (HUMAN-DECISION-REGISTER.md), a targeted reference
update (MASTER-REQUIREMENTS.md), IMP-002 specification remediation, and this audit record.

## Definition of Ready

PASS — see the specification's own "Definition of Ready" section.

## Result

```text
IMP-002 Specification:        READY FOR TARGETED READINESS RE-AUDIT
IMP-002 Definition of Ready:   PASS
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
Coding Performed:               NO
```
