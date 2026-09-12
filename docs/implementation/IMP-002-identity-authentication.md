# IMP-002 — IDENTITY + AUTHENTICATION

## Document Control

```text
Task ID: IMP-002
Stage: IMPLEMENTATION 02
Title: Identity + Authentication
Document Type: Implementation Specification
Status: DRAFT — READY FOR TARGETED READINESS RE-AUDIT
Implementation Authorization: NOT AUTHORIZED
Coding Authorization: NO
Predecessor: IMP-001 FINAL / LOCKED
Working Branch: impl/002-identity-authentication (not merged to master)
```

This specification establishes platform Identity and Authentication foundations only. It does
NOT implement the broader authorization system (roles, permissions, data scope, business
authority) assigned to IMP-003, and it does NOT implement any business/financial domain.

---

## Human Decisions Incorporated

```text
Q7  — Beneficiary Registration Model  = HYBRID BENEFICIARY REGISTRATION — FINAL / LOCKED
                                         (pre-existing Level 1 entry; referenced only)
Q21 — Login Identifier                = EMAIL AS CANONICAL LOGIN IDENTIFIER — FINAL / LOCKED
Q22 — Registration Model              = HYBRID REGISTRATION BY ACTOR — FINAL / LOCKED
Q23 — MFA Policy                      = CONFIGURABLE MFA, TOTP BASELINE — FINAL / LOCKED
Q24 — Account / Security Status Model = SEPARATED IDENTITY LIFECYCLE + VERIFICATION + SECURITY
                                         RESTRICTION MODEL — FINAL / LOCKED
Q25 — First Super Admin Bootstrap     = CONTROLLED ONE-TIME CLI BOOTSTRAP — FINAL / LOCKED
```

Q21-Q25 are now materialized in the canonical Level 1
[docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md)
("Register" and "Extended Decisions — Detailed Rules (Q21-Q25)"), alongside the pre-existing
Q1-Q20 (which already include Q7). This specification references, rather than duplicates, their
detailed rules — see the register for the authoritative wording; this document applies it.

---

## Authority Sources

Read before drafting and re-read before any future implementation of this stage:

```text
AGENTS.md
docs/00-governance/DOCUMENT-AUTHORITY.md
docs/00-governance/CHANGE-CONTROL.md
docs/00-governance/BRANCHING-POLICY.md
docs/00-governance/DEFINITION-OF-DONE.md
docs/01-requirements/HUMAN-DECISION-REGISTER.md
docs/01-requirements/MASTER-REQUIREMENTS.md
docs/02-architecture/MASTER-ARCHITECTURE.md
docs/02-architecture/MODULE-OWNERSHIP.md
docs/03-database/DATABASE-ARCHITECTURE.md
docs/03-database/DATABASE-INVARIANTS.md
docs/04-security/SECURITY-ARCHITECTURE.md
docs/04-security/SECURITY-INVARIANTS.md
docs/05-rbac/RBAC-ARCHITECTURE.md
docs/05-rbac/DATA-SCOPE-MODEL.md
docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md
docs/05-rbac/AUTHENTICATION-ASSURANCE.md
docs/implementation/IMP-001-core-project-foundation.md
docs/audits/IMP-001-IMPLEMENTATION-PASS-1.md
docs/audits/IMP-001-TARGETED-REMEDIATION-PASS-1.md
docs/audits/IMP-001-FINALIZATION.md
docs/audits/IMP-002-SPECIFICATION-REMEDIATION-1.md
docs/audits/IMP-002-AUTHORITY-CONSISTENCY-PATCH-1.md
```

**Actor Catalog reconciliation:** no repository document is titled "Actor Catalog," but
[docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §3/§6,
[docs/05-rbac/DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md), and Q22 together already
identify the actor set relevant to authentication (Donor, Fundraiser, Partner Representative,
Internal Administrative Identity, Super Admin) — see "Actors" below. Beneficiary registration
policy is separately governed by Q7 (not Q22); only Q7's implementation ownership is deferred, not
its policy — see "Actors" and "Deferred Items." The absence of a dedicated
`docs/06-domains/identity/` Actor Catalog artifact is a documentation/materialization gap, not a
Human Decision.

---

## Purpose

Establish:

```text
1. Who is an authenticated principal (Identity).
2. How a principal proves control of that identity (Authentication).
3. How authenticated state is maintained (Session).
4. How credentials are securely managed.
5. How authentication-sensitive lifecycle events are handled (registration, invitation,
   verification, email change, password reset, MFA, account/security state, first Super Admin
   bootstrap).
6. How authentication assurance (STANDARD / ELEVATED, per
   docs/05-rbac/AUTHENTICATION-ASSURANCE.md) is represented, with an executable lifecycle, for
   later authorization stages to consume.
```

IMP-002 does NOT answer what business data/action a principal may access — that is IMP-003
(RBAC + Scope + Business Authority) and later domain stages.

---

## Stage Boundary

### In Scope

```text
Identity data model (credential-bearing entity only)
Authentication (email + password login, logout)
Session use of IMP-001's existing sessions table
Password credential handling (hash, update, reset) and its session/assurance consequences
Canonical email change lifecycle (pending email, re-verification, atomic promotion)
Email verification lifecycle (canonical, per Q21)
Optional phone as contact data only (never a login identifier)
Configurable TOTP-baseline MFA (enrollment, verification, recovery, reset) using a maintained
  library — see "MFA"
Executable Authentication Assurance lifecycle (STANDARD / ELEVATED) with finite lifetime and
  explicit invalidation triggers, for IMP-003 to consume
Identity Lifecycle (ACTIVE/DISABLED) + Verification (email_verified_at) + persistent Security
  Restriction (NONE/SUSPENDED) per Q24 — transient brute-force lockout handled via rate limiting,
  never as persistent lifecycle state
Invitation lifecycle for non-self-registering actors (Partner Representative, Internal
  Administrative Identity, Super Admin), including issuer/revocation state
Controlled one-time CLI bootstrap of the first Super Admin identity per Q25
Principal exposure to web requests, API requests, audit
Rate limiting / abuse control on authentication endpoints
Authentication-relevant audit events
Privacy-safe error responses (no account enumeration)
Minimal authentication UI (login, registration where decided, invitation acceptance, forgot/reset
  password, email verification, email change, account security, MFA enrollment/verification)
```

Remember-me is explicitly DEFERRED/DISABLED for the IMP-002 baseline — see "Remember-Me
(Deferred)."

### Out of Scope

```text
Roles
Permissions
Role assignments
Permission assignments
Data scopes (docs/05-rbac/DATA-SCOPE-MODEL.md is consumed later, not implemented here)
Business Authority / Authority Types / Authority Assignments
  (docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md is consumed later, not implemented here)
Approval matrices
Financial authority
Campaign/Program/Fund ownership authorization
Partner scope authorization / actual Partner relationship record (Partner module owns this)
Fundraiser business rules / Fundraiser Profile (owned by Fundraising & Attribution, see
  docs/02-architecture/MODULE-OWNERSHIP.md §5)
Fundraiser approval / verification decision (a Business Authority concept, not Identity)
Beneficiary domain implementation (application, document, eligibility, verification workflow,
  distribution eligibility, business status, approval) — Q7's registration *policy* is locked;
  its mechanics belong to the Beneficiary & Distribution domain stage
Donation logic
Payment logic
Ledger
Commission
Withdrawal
Refund
Moota / Reconciliation
CMS
Theme Engine
Full REST API (only a boundary/direction is discussed in "API Authentication Boundary" below)
Business notifications
Canonical RBAC Super Admin role/permission/scope/authority (Q25 — owned by IMP-003; IMP-002 only
  bootstraps the first identity)
```

Rationale for the Fundraiser/Partner/Beneficiary profile exclusion: per
[docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md), "Fundraiser
Profile" is owned by Fundraising & Attribution (§5), "Partner Profile" by Partner (§7), and
Beneficiary "Profile" by Beneficiary & Distribution (§14) — not by Identity & Organization (§1),
which owns only "User identity, Organization membership, Principal organizational context." IMP-002
therefore implements only the shared credential-bearing identity; each business module's own
profile record (created in that module's later stage) references this identity by foreign key.

**Identity creation is never authorization.** Account creation (self-registered, invited,
provisioned, or bootstrapped per Q25) is explicitly NOT equivalent to: role assignment, permission
assignment, data scope, business authority, financial authority, Partner authority, or Fundraiser
approval. A Fundraiser who self-registers receives only an authenticated Identity/Auth subject —
no Fundraiser business authority, no attribution eligibility, no Commission entitlement. The first
bootstrapped Super Admin identity (Q25) likewise receives no RBAC authority from IMP-002 itself —
see "First Super Admin Bootstrap." All authority remains entirely IMP-003+/domain-stage concerns.

---

## Actors

| Actor | Can Hold Human Identity? | Authentication Expected? | Registration / Provisioning Model | Public Representation Separate? | Authorization Owner | Notes |
|---|---|---|---|---|---|---|
| Donor | YES | YES | **Self-registration** (Q22) | YES — separate per Q6 and AGENTS.md | IMP-003+ | `/donor/*` |
| Fundraiser | YES | YES | **Self-registration** (Q22); no automatic business authority | YES — Fundraiser Profile owned by Fundraising & Attribution (MODULE-OWNERSHIP.md §5) | IMP-003+ / Fundraising & Attribution | `/fundraiser/*` |
| Partner Representative | YES | YES | **Invitation or domain-driven provisioning only** (Q22) | YES — Partner Profile owned by Partner module (§7) | IMP-003+ / Partner module | `/partner/*` |
| Internal Administrative Identity | YES | YES | **Invitation/provisioning only** (Q22) | N/A | IMP-003+ | `/admin/*` |
| Super Admin | YES | YES | **Provisioning only** (Q22); the *first* Super Admin uses the controlled CLI bootstrap (Q25) | N/A | IMP-003+ (canonical role/permission/scope/authority) | `/admin/*`; RBAC-ARCHITECTURE.md: "Super Admin != automatic Financial Authority" |
| Beneficiary | Likely YES where Q7's hybrid model requires it | Governed by Q7 (FINAL/LOCKED); whether it entails a `User` is an implementation-ownership question | **LOCKED by Q7**; mechanics deferred to the Beneficiary & Distribution domain stage | YES — Beneficiary data is HIGHLY_SENSITIVE (MASTER-REQUIREMENTS.md §10) | IMP-003+ / Beneficiary & Distribution | Not routed yet; policy is not open, only implementation ownership is deferred |

Do NOT create role authorization rules, business authority, or per-actor permission logic from
this table — it governs authentication/registration eligibility only.

### Actor Catalog Artifact Gap

`docs/06-domains/identity/` remains an empty placeholder. This is a documentation/materialization
gap, not a blocking Human Decision — reconciliation above already supplies everything IMP-002
needs from existing authority.

---

## Identity Model

### Canonical Identity Entity

Per [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §1, the
canonical credential-bearing entity remains named **User** — a naming choice, not an architecture
decision.

`User` owns exactly:

```text
entity purpose:        authentication + shared identity, nothing business-domain-specific
primary identifier:    BIGINT unsigned internal key (DATABASE-ARCHITECTURE.md "Identity
                        Conventions")
public identifier:     ULID — see "Public ID"
login identifier:      email (Q21 — canonical, exclusive baseline)
password credential:   hashed only (see "Credential Model")
contact (optional):    phone — contact data only, never a login identifier
Identity Lifecycle:    ACTIVE | DISABLED (Q24 — persistent; see "Account / Security Model")
Security Restriction:  NONE | SUSPENDED (Q24 — persistent, separate from Identity Lifecycle)
verification state:    email_verified_at (Q24 — a timestamp, not a lifecycle enum value);
                        phone_verified_at (only if phone is collected)
MFA state:             mfa_enabled flag; TOTP secret and recovery codes live in separate storage
                        (see "MFA Secret Storage" / "Database Design Requirements")
last-login metadata:    last_login_at (nullable timestamp) — informational only, not a security
                        restriction
timestamps:            created_at / updated_at (framework standard)
soft-delete/retention: NOT soft-deleted by default — see "Retention Boundary"
```

`User` does NOT own a pending/candidate email — that lives entirely in the separate
`EmailChangeRequest` entity (see "Canonical Email Change Lifecycle"), precisely so a stale
verification token can never be checked against a mutable "current pending email" field on `User`.

`User` does NOT own transient brute-force/rate-limit state (no `failed_login_attempts`, no
`locked_until`) — per Q24 (M05), that state belongs to the authentication rate-limiter
infrastructure, not to persistent identity data. See "Account / Security Model."

No `remember_token` field is part of the IMP-002 baseline — see "Remember-Me (Deferred)."

`User` does NOT own: Donor-specific fields, Fundraiser Profile, Partner Profile, Beneficiary
Profile/Application, Admin business preferences, roles, permissions, scopes, or business
authority.

### Organization Membership

Unchanged: per [docs/02-architecture/MASTER-ARCHITECTURE.md](../02-architecture/MASTER-ARCHITECTURE.md)'s
locked "Single Organization" principle, every `User` is implicitly a member of the single platform
organization; no separate membership table is required by this stage.

### Internal Identity vs. Public Representation

Unchanged principle: `User` (internal identity, keyed by email) MUST NOT be conflated with any
public-facing representation (fundraiser display name, partner public profile, donor
display/anonymity per Q6, beneficiary public identity, campaign public author). An email address
in particular MUST NOT be exposed as any actor's public identity/handle.

---

## Email as Canonical Login Identifier (Q21)

```text
Email is the canonical login identifier for all actors in "Actors" above.
Authentication baseline is email + password.
Phone MAY exist as contact/verification data but is NOT a baseline login identifier.
Username is NOT a baseline login identifier.
Multi-identifier login is NOT part of the IMP-002 baseline.
Adding a second login identifier later requires change control (an ACR/ADR).
```

### Email Normalization

```text
Leading/trailing whitespace removed before storage and comparison.
Compared using a single, consistent canonical representation (e.g. lowercase) for uniqueness,
  applied consistently at both write and comparison time.
The unique constraint is enforced on the canonical stored representation at the database level.
```

Explicitly NOT authorized: Gmail-style dot-removal, plus-alias stripping, or other
provider-specific mailbox rewriting. Email identity semantics remain provider-neutral.

---

## Canonical Email Change Lifecycle (M01 — Request-Versioned)

Email is the login identifier (Q21), so changing it is a sensitive operation with its own
deterministic lifecycle. **This pass replaces the prior `users.pending_email` design**, which
allowed a stale-token race (a verification challenge was not bound to an exact, immutable request
version), **with a dedicated, immutable `EmailChangeRequest` entity.** No `pending_email` column
exists on `User` — see "Database Design Requirements."

### `EmailChangeRequest` (conceptual — no migration created)

```text
id                       internal identifier
user_id                  FK to users.id
normalized_pending_email the exact email this specific request is for (immutable once created)
verification_token_hash  the verification challenge for THIS request only, stored hashed
requested_at             timestamp
expires_at               timestamp
verified_at              nullable — set on successful promotion
superseded_at            nullable — set when a later request replaces this one before it verifies
cancelled_at             nullable — set on explicit cancellation
conflicted_at            nullable — set when canonical promotion fails because another User
                         acquired the target email first (see "Uniqueness Conflict — Durable
                         Terminal State" below); a distinct terminal outcome from all of the above
conflict_reason_code     nullable — a controlled, non-sensitive internal code explaining the
                         conflict (baseline value: `TARGET_EMAIL_ALREADY_IN_USE`); never a raw
                         database/exception message
generation               a monotonically increasing per-user version/sequence number
                         (e.g. an integer that increments with every new request for that user) —
                         used so a verifier can trivially confirm "is this the request's own,
                         unambiguous identity," independent of timestamps
created_at / updated_at  framework standard
```

Every field above except `verified_at`/`superseded_at`/`cancelled_at`/`conflicted_at`/
`conflict_reason_code` is immutable once the row is created — a request is never edited in place
to point at a different email or a different token; a changed email means a NEW request row.

### One Active Request Per User (baseline rule)

At most ONE active (`verified_at`, `superseded_at`, and `cancelled_at` all still null, and not
expired) `EmailChangeRequest` may exist per `User` at a time. Starting a new request MUST,
atomically:

```text
1. Normalize the new email (see "Email Normalization").
2. Validate it differs from the current canonical email.
3. Check application-level availability (uniqueness) against existing canonical emails.
4. Mark any existing active request for this User as SUPERSEDED (superseded_at = now),
   immediately and unconditionally — this happens BEFORE or atomically WITH creating the new
   request, never after, so there is never a moment with two simultaneously active requests.
5. Create the new EmailChangeRequest row with a new cryptographically secure verification token
   (stored hashed), the next `generation` value, and its own `expires_at`.
6. Send the verification message for THIS request's token to THIS request's
   normalized_pending_email only.
```

Once superseded, a request's token is immediately and permanently unusable — there is no grace
period during which both the old and new tokens verify successfully.

### Verification Token Binding (mandatory checks)

When a verification attempt is made, the verifier MUST check ALL of the following before
promoting anything — never "token hash matches something, therefore promote whatever
`users.pending_email` currently holds" (that pattern, possible under the prior design, is
explicitly prohibited):

```text
the request belongs to the intended User (the token lookup resolves to a specific
  EmailChangeRequest row, and that row's user_id is used — never inferred separately)
the request is the CURRENT active request for that User (compare against the latest
  non-superseded, non-cancelled, non-conflicted row, or equivalently check this request's own
  superseded_at/cancelled_at/conflicted_at are all null)
the request is not superseded
the request is not cancelled
the request is not conflicted (conflicted_at is null)
the request is not expired (now < expires_at)
the request is not already consumed (verified_at is null)
the presented token matches THIS exact request's verification_token_hash
the email being promoted is THIS exact request's normalized_pending_email — never a different
  email read from anywhere else (there is no "current pending email" to read separately; the
  request row IS the source of truth for which email a given token promotes)
```

A token that matches a *superseded, cancelled, conflicted, expired, or already-consumed* request
fails all of the above and MUST be rejected with the generic error described in "Privacy" — it can
never promote any email, including whatever the User's CURRENT active request (if any) is pending
toward. See "Email Change Terminal States" for the complete, mutually exclusive set of outcomes.

### Success Semantics (atomic promotion)

On successful verification, execute atomically (see "Transaction Boundaries"):

```text
BEGIN TRANSACTION
  lock the User row
  lock the EmailChangeRequest row
  re-check the request is still the current active, non-expired, non-consumed request for this
    User (defends against a race between the initial checks above and acquiring the lock)
  re-check the target canonical email is still unique across all Users (application check);
    the database's unique constraint on users.email remains the final authority regardless
  promote: users.email <- request.normalized_pending_email
  set users.email_verified_at <- now
  set request.verified_at <- now (the request becomes CONSUMED — a consumed request can never be
    used again, even if somehow re-presented)
  supersede/cancel any other still-active EmailChangeRequest row for this User, if one somehow
    exists (defensive — the "one active request" rule should already prevent this)
  rotate the current session's ID
  invalidate all of the User's OTHER active sessions
  invalidate ELEVATED Authentication Assurance
  invalidate any password-reset token outstanding against the OLD email (it is no longer a valid
    reset-lookup target for this identity after promotion)
  emit an audit event (email change completed)
COMMIT
```

### Uniqueness Conflict — Durable Terminal State

If the target email's uniqueness re-check (or the database's own unique constraint) fails during
promotion — another identity acquired the target email since the request was created — the
promotion transaction is ROLLED BACK in full:

```text
BEGIN TRANSACTION (promotion attempt)
  lock the EmailChangeRequest row; lock the User row
  verify the request is active (per "Verification Token Binding")
  verify the token/challenge
  re-check target-email uniqueness
  attempt canonical promotion
  -> unique constraint / re-check indicates the target email is already owned by another User
ROLLBACK
```

`users.email` and `users.email_verified_at` are left completely untouched by the rolled-back
transaction — a failed promotion transaction never persists its own conflict state; a rollback
undoes everything, including any conflict marker that attempt might otherwise have tried to write
inside the same transaction.

Conflict finalization then happens as its own, separate, durable transaction:

```text
BEGIN TRANSACTION (conflict finalization)
  lock the EmailChangeRequest row
  IF the request is still eligible for conflict finalization (verified_at, superseded_at,
     cancelled_at, and conflicted_at are all still null — i.e. no other action has already
     resolved it to a different terminal state in the meantime):
       set conflicted_at <- now
       set conflict_reason_code <- 'TARGET_EMAIL_ALREADY_IN_USE'
       (this makes the request permanently non-promotable and non-retryable — see "Email Change
       Terminal States")
       emit an audit event (conflict finalized)
  ELSE:
       do nothing — the request already reached a different terminal state (verified, superseded,
       cancelled, or already conflicted) through some other concurrent action; that pre-existing
       terminal state is authoritative and MUST NOT be overwritten
COMMIT
```

An equivalent implementation (e.g. a single conditional/atomic update guarded by "all terminal
columns are null") that provides the same durable, race-safe semantics is acceptable — the
two-transaction description above is the conceptual model, not a mandated literal sequence of SQL
statements.

`conflict_reason_code` holds only a controlled, internal, non-sensitive value (baseline:
`TARGET_EMAIL_ALREADY_IN_USE`) — never a raw database constraint name, exception message, or any
detail that could reveal which other account owns the target email. The public-facing response to
whatever action triggered verification remains the same generic safe error described in "Privacy,"
regardless of whether the underlying cause was an invalid token, an expired request, or a
uniqueness conflict.

This is a concurrency/data-integrity outcome, not a new Human Decision.

### Email Change Terminal States

An `EmailChangeRequest` has exactly one of the following mutually exclusive effective outcomes at
any point in time:

```text
ACTIVE       verified_at, superseded_at, cancelled_at, and conflicted_at are all null, and the
             request has not expired — the only state in which the request is promotable
VERIFIED     verified_at is set — promotion already succeeded; permanently terminal
SUPERSEDED   superseded_at is set — a later request replaced this one before it verified;
             permanently terminal
CANCELLED    cancelled_at is set — explicitly cancelled before verifying; permanently terminal
EXPIRED      now >= expires_at, and none of the above terminal fields is set — time-based,
             effectively terminal (a fresh request is required; nothing further needs to be
             written to "finalize" an expiry the way a conflict must be finalized, since expiry is
             already fully determined by comparing `now` to the immutable `expires_at`)
CONFLICTED   conflicted_at is set — promotion was attempted but the target email was already
             taken by another User by the time of promotion; permanently terminal and NOT
             retryable (a NEW request, targeting the same or a different email, is required)
```

A request is promotable ONLY when it is ACTIVE: not expired, not superseded, not cancelled, not
conflicted, and not already verified/consumed. Once any terminal state is reached — VERIFIED,
SUPERSEDED, CANCELLED, EXPIRED, or CONFLICTED — that request can never become promotable again,
and a CONFLICTED request specifically does NOT count as "active," so the User may immediately
initiate a brand-new email-change request (for the same or a different target email) once
conflicted; the old, conflicted request remains untouched as immutable historical evidence and is
never reused or retried.

None of these terminal outcomes — including CONFLICTED — ever mutates the User's canonical email
or `email_verified_at`. The existing canonical email remains active and (already) verified until
a new request successfully completes the "Success Semantics" promotion above; there is no partial
or provisional email state visible outside the `EmailChangeRequest` row itself, and no partial
promotion can ever survive a rollback.

Once `conflicted_at` is set, the verification token/challenge for that request becomes
unconditionally unusable — any later attempt to present it returns the same generic safe failure
described in "Privacy," without revealing which account holds the target email, any database
constraint detail, or any other internal identity information.

### Concurrency

```text
Two Users can never hold the same normalized canonical email — enforced by the database's unique
  constraint on users.email, authoritative regardless of any application-level pre-check.
Two concurrent verification attempts against the SAME request: transaction locking (see "Success
  Semantics") ensures exactly one succeeds; the second sees the request already CONSUMED (or the
  lock forces it to re-check and find verified_at already set) and fails safely.
A new email-change request ALWAYS supersedes (invalidates) any prior active request for that
  User, unconditionally — this is mandatory, not a "may," precisely to close the stale-token race
  that was IMP002-RDY-M01.
A token issued for request generation N can NEVER promote the email of request generation N+1, or
  vice versa — each token is permanently bound to the exact request it was issued for via the
  token-to-request lookup itself (the token hash is looked up against EmailChangeRequest rows, not
  against a mutable "current pending email" field).
Conflict finalization (see "Uniqueness Conflict — Durable Terminal State") is itself race-safe: if
  a request is concurrently cancelled, superseded, or has already been verified/conflicted by the
  time the conflict-finalization step runs, that pre-existing terminal state is authoritative and
  is never overwritten by a late-arriving conflict marker — the finalization step is a conditional
  write ("only if still eligible"), not an unconditional one.
```

No provider-specific email rewriting is introduced by this lifecycle (see "Email Normalization").

---

## Credential Model

Password hashing MUST use Laravel's framework-standard hashing (bcrypt/argon2 via the `Hash`
facade) — per [CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md) "Dependency Governance." No
custom cryptography (AGENTS.md "Never").

```text
baseline:                 email + password (Q21)
password hashing:         framework Hash facade only; never plaintext, never reversible
password verification:    via the framework hasher; the framework hasher rehashes automatically
                           when its configured algorithm/cost policy changes (Laravel's "needs
                           rehash" capability) — no custom rehash mechanism
password update:          requires current-password confirmation OR an authenticated reset flow
password reset:           see "Password Reset" — token-based, single-use, expiring
password confirmation:    supported as one mechanism to step up from STANDARD to ELEVATED
                           assurance for a bounded window (see "Authentication Assurance")
password history:         NOT required — not invented
```

Never store: plaintext password, reversibly-encrypted password, or a plaintext password-reset
token.

### Password Change — Session Consequences (m02)

On any successful *ordinary* password change (the identity supplies its current password to set a
new one), ALL of the following are required, atomically (see "Transaction Boundaries"):

```text
password is rehashed per the credential model above
the current session's ID is regenerated (rotation)
all of the identity's OTHER active sessions are invalidated
all ELEVATED Authentication Assurance is invalidated (both the current session's and any other
  session's, since those sessions are themselves being invalidated) — a new STANDARD-only state
  applies until re-earned
all outstanding password-reset tokens for the identity are invalidated
any persistent/remember-me credential is invalidated (moot under the current baseline — see
  "Remember-Me (Deferred)" — specified for forward compatibility)
an audit event is emitted
```

The current request's session may continue only after the rotation and assurance reset above have
been applied — it does not "keep" its pre-change ELEVATED state.

A password reset completed via the recovery flow (see "Password Reset") applies the *same or
stronger* invalidation semantics — every other session is invalidated there too, not just "some."

---

## Registration Flows (Q22 — Hybrid Registration by Actor)

Per-actor eligibility is locked (see "Actors"). Every registration/provisioning/invitation path,
regardless of actor, MUST:

```text
validate the email format and uniqueness (application check + final database unique constraint)
hash the password immediately
create the User record inside a single transaction
NOT seed a default/known password (see docs/audits/IMP-001-TARGETED-REMEDIATION-PASS-1.md)
NOT grant any role, permission, scope, or business authority
emit an audit event
```

### Donor Self-Registration

```text
submit email + set password -> create Identity -> begin email verification -> authenticated
  session per "Session Model"/"Login"
```

### Fundraiser Self-Registration

```text
create Identity (same mechanism as Donor) -> authenticate -> grant NO automatic Fundraiser
  business authority, attribution eligibility, or Commission entitlement
```

Whether a neutral "pending Fundraiser application" record accompanies the Identity is a
Fundraising & Attribution (MODULE-OWNERSHIP.md §5) concern for that module's own stage.

### Partner Representative

```text
NO public self-registration baseline (Q22) — invitation (see "Invitation Boundary") or
  domain-driven Partner-module provisioning only
```

### Internal Administrative Identity

```text
invited or provisioned only — no public self-registration (Q22)
```

### Super Admin

```text
provisioned only — no public self-registration (Q22)
the FIRST Super Admin identity uses the Q25 controlled CLI bootstrap — see "First Super Admin
  Bootstrap"
any subsequent Super Admin identity does NOT use the first-bootstrap mechanism; it uses whatever
  ordinary provisioning path IMP-003+ establishes for granting Super Admin authority to an
  already-existing or newly invited identity
```

### Beneficiary

Registration *policy* is locked by Q7 (Hybrid Beneficiary Registration). IMP-002 implements no
Beneficiary domain behavior. If/when the Beneficiary domain stage determines a Beneficiary needs a
`User`, it reuses IMP-002's existing mechanisms (self-registration, invitation, or provisioning) —
it does not require a different identity mechanism.

---

## Invitation Boundary (M02)

Required for Partner Representative and Internal Administrative Identity (and usable for Super
Admin provisioning beyond the first bootstrap).

```text
id                    internal identifier
public_id             ULID, if a consuming context needs an external-safe reference
intended_email         the email this invitation is bound to
token_hash             the invitation token, stored hashed — never plaintext
issued_at              timestamp
expires_at             timestamp — configurable, framework-standard expiry mechanics
accepted_at            nullable timestamp — set on successful acceptance
revoked_at             nullable timestamp — set on explicit revocation before expiry
issuer reference       records WHO/WHAT issued the invitation (attribution/context only)
revoker reference       records who revoked it, where available
created_at / updated_at
```

**Boundary:** the issuer/revoker reference records attribution only — it does NOT itself
implement or imply role authority. *Actual* authorization to issue or revoke an invitation (e.g.
"only a Partner Verifier may invite a Partner Representative") is a future RBAC/Business Authority
concern (IMP-003+); IMP-002 only records who did it, for audit and revocation purposes.

### Lifecycle Rules

```text
Explicit revocation is supported before expiry — a revoked invitation cannot later be accepted,
  even if not yet expired.
Acceptance requires ALL of: not expired, not already accepted, not revoked, token valid (matches
  the stored hash), and the presented email matches the invitation's intended_email (after
  normalization).
Acceptance is transaction-safe: exactly one acceptance may succeed for a given invitation, even
  under concurrent attempts (enforced via the single-consumption transaction, e.g. an atomic
  "accept once" update guarded by accepted_at IS NULL).
An invalid/expired/already-used/revoked invitation returns a generic error (see "Privacy") that
  does not confirm whether a different, valid invitation exists for that email.
Acceptance establishes an Identity/Auth subject (a `User`) ONLY. It must NOT automatically grant
  Partner authority, Admin role, Super Admin role, permission, data scope, business authority, or
  financial authority — those remain IMP-003+/domain-stage grants, performed (if at all) by the
  owning authorized workflow, never as a side effect of invitation acceptance.
```

### Invitation / Registration Email-Uniqueness Race (task item 20)

If an invitation exists for `X@example.com` and a User self-registers that same (normalized)
email before the invitation is accepted:

```text
The invitation MUST NEVER cause a duplicate identity to be created — the database's unique
  constraint on canonical email is the final authority (see "Canonical Email Change Lifecycle"
  for the same principle applied to changes; the same constraint also governs registration and
  invitation acceptance).
Acceptance in this situation either (a) fails safely with a generic error and requires an
  authorized reconciliation step (an IMP-003+/administrative concern for deciding what happens
  next), or (b) — only if the owning workflow explicitly permits it — binds the invitation to the
  already-existing identity that self-registered that email, WITHOUT automatically granting any
  business authority that acceptance would otherwise have implied. IMP-002 does not decide between
  (a) and (b) beyond requiring that neither option ever produces two `User` rows for the same
  canonical email; that choice belongs to the owning domain workflow (e.g. Partner module) that
  issued the invitation in the first place.
```

---

## Authentication Flows

### Login

```text
credential submission:    email + password
credential validation:    via the framework hasher; generic failure message regardless of
                           whether the email existed (see "Privacy")
session regeneration:      session ID MUST be regenerated on successful authentication
rate limiting/throttling: required on the login endpoint
account state check:      DISABLED or SUSPENDED (Q24) -> authentication denied, generic message;
                           a transient brute-force lockout (see "Account / Security Model") also
                           denies authentication for its duration, without being a persistent
                           lifecycle state
MFA challenge:             if the identity has MFA enabled, a successful password check is
                           followed by a TOTP challenge before the session is considered fully
                           authenticated
security audit event:     both success and failure are audited
```

No business-role redirect logic belongs here.

### Logout

```text
invalidates the current session (destroyed, not merely marked)
session ID is regenerated/rotated after logout
ELEVATED Authentication Assurance, if any, is invalidated as part of session invalidation
"logout other sessions" is optional, implementation-time discretion
```

---

## Session Model

IMP-001 already provides a database-backed `sessions` table. IMP-002 MUST reuse it, not replace
it. Its `user_id` column is nullable and carries no foreign-key constraint, so it can reference
the future internal `User.id` without any change to the existing IMP-001 migration.

```text
session fixation protection:  regenerate session ID on login and on privilege-relevant
                               transitions (password change, email-change promotion, successful
                               MFA challenge, ELEVATED step-up)
session rotation:              framework `session.php` config defaults
logout:                        see "Authentication Flows > Logout"
expiration:                    framework-standard SESSION_LIFETIME (IMP-001 .env.example)
CSRF / cookie expectations:    see "CSRF / Cookie Security"
```

### Remember-Me (Deferred) (m01)

```text
REMEMBER-ME = DEFERRED / DISABLED for the IMP-002 baseline.
```

Persistent (long-lived) authentication introduces additional revocation and security semantics
(a persistent credential that must be tracked, rotated, and invalidated consistently with every
session-invalidation trigger in this specification) that are not needed for the initial
authentication foundation. Therefore:

```text
`remember_token` is NOT a required IMP-002 `User` field (see "Identity Model" and "Database
  Design Requirements").
No "remember me" checkbox/flow is implemented by IMP-002.
Future enablement requires a specification update, not a silent implementation addition — every
  session-invalidation trigger elsewhere in this document (password change, email change, MFA
  reset, suspension, disablement, etc.) would then need to explicitly invalidate the persistent
  credential too, which this specification calls out at each relevant point precisely so that
  future enablement does not miss one.
```

---

## Password Reset

Bound to the canonical email identity (Q21):

```text
request:                accepts an email address; ALWAYS returns a generic "if an account
                         exists, instructions were sent" response
token generation:        cryptographically random
token storage:           stored HASHED — Laravel's framework `PasswordBroker` hashes reset
                         tokens by default; verify this at implementation time rather than
                         assuming it
expiration:              framework-standard broker expiry
single use:              invalidated immediately upon successful use
password replacement:     new password hashed per "Credential Model"; the SAME session/assurance
                         invalidation consequences as "Password Change — Session Consequences"
                         apply (all other sessions invalidated, ELEVATED reset, remember-me
                         credential invalidated if ever enabled)
audit:                    reset requested, reset completed, and invalid/expired attempts are all
                         audited
```

### Interaction with Email Change

```text
Password reset always targets the CURRENT canonical login email (users.email) — never a pending
  candidate held on an EmailChangeRequest row, which is not the login identifier until its
  promotion transaction completes (see "Canonical Email Change Lifecycle").
Once a canonical email change completes (promotion), the OLD email immediately stops being a
  valid password-reset lookup target for that identity, and any reset token that was outstanding
  against the old email is invalidated as part of the promotion transaction (already specified in
  "Canonical Email Change Lifecycle > Success Semantics").
```

---

## Verification

Distinguish states that MUST NOT be conflated:

```text
identity exists:      a User record was created (registration/invitation/provisioning/bootstrap)
email verified:        email_verified_at is set for the current canonical email (Q24 — a
                       timestamp field, not a lifecycle status value)
authenticated:         a login succeeded for this request/session
business authorized:   an Authority Assignment exists — entirely an IMP-003+ concern
```

Per Q24, `email_verified_at` is a separate field from the Identity Lifecycle
(ACTIVE/DISABLED) and the persistent Security Restriction (NONE/SUSPENDED) — there is no
`pending_verification` *lifecycle* value. Login succeeds on valid credentials regardless of
email-verification state (an unverified email is not itself a Q24 Identity Lifecycle or Security
Restriction value); a future ELEVATED-assurance-gated action MAY additionally require a verified
email, but that mapping belongs to whichever later stage defines such an action.

Verification lifecycle: send verification (on registration/invitation acceptance/email-change
request), confirm via a signed/token link or code, re-send with rate limiting, mark verified
(update `email_verified_at`), audit.

---

## Contact Phone

If collected at all, phone is contact data only:

```text
phone != login identifier (Q21)
phone verification, if implemented, exists only for contact/notification purposes
SMS OTP is explicitly NOT the MFA baseline (Q23)
```

---

## Authentication Assurance (M03 — Executable Lifecycle)

Per [docs/05-rbac/AUTHENTICATION-ASSURANCE.md](../05-rbac/AUTHENTICATION-ASSURANCE.md), IMP-002
establishes STANDARD/ELEVATED as session-scoped security metadata — NOT a permanent `User`
property.

```text
Conceptual representation (session-scoped, not a User column):
  assurance_level    STANDARD | ELEVATED
  elevated_at        timestamp the ELEVATED state was most recently earned
  elevated_until      timestamp ELEVATED expires and falls back to STANDARD
```

### STANDARD

Created after successful baseline authentication (email + password, plus TOTP if MFA is enabled
for that identity).

### ELEVATED

Created ONLY after a fresh, successful step-up action performed specifically for that purpose:

```text
a fresh password confirmation, or
a fresh TOTP challenge performed specifically to step up assurance (not merely reused from the
  login-time challenge, if a later policy requires a more recent proof)
```

### Lifetime

```text
ELEVATED has a finite, configurable lifetime (`elevated_until`) — no hard-coded duration is fixed
  by this specification, since no materialized document fixes one; implementation sets a secure
  configurable default.
When `elevated_until` passes, the session's assurance falls back to STANDARD automatically —
  ELEVATED is never permanent.
```

### Immediate Invalidation Triggers

ELEVATED MUST be invalidated immediately (not merely left to expire) on any of:

```text
logout
password change (ordinary or reset)
canonical email change completion
MFA reset / disable / re-enrollment
account entering SUSPENDED or DISABLED (Q24)
any session invalidation (e.g. "logout other sessions," or the session-invalidation consequences
  of the triggers above)
```

A NEW session never automatically inherits a prior session's ELEVATED assurance — each session
starts at STANDARD and must independently earn ELEVATED, unless a future, explicitly authorized
authentication architecture decision says otherwise (none does today).

### Authorization Boundary

```text
Authenticated  != role
ELEVATED        != permission, financial authority, or approval authority
```

IMP-003+ consumes both states; IMP-002 attaches no business/financial right to either.

---

## MFA (Q23 — Configurable, TOTP Baseline)

```text
MFA capability belongs to IMP-002's authentication foundation.
MFA is NOT universally mandatory — enablement is per-identity/configurable.
TOTP (RFC 6238) is the baseline mechanism.
A successful TOTP challenge may satisfy ELEVATED Authentication Assurance.
MFA success/enrollment grants NO role, permission, data scope, business, or financial authority.
SMS OTP is explicitly NOT the baseline mechanism.
```

### Implementation Contract (M04)

**TOTP implementation MUST use a maintained, security-reviewed, Laravel/PHP-compatible
third-party library implementing RFC 6238.** A hand-rolled/"minimal direct" TOTP implementation is
explicitly **PROHIBITED** for this specification, superseding any earlier suggestion that a direct
`hash_hmac`-based implementation was an acceptable alternative — TOTP's HMAC construction and
timing/window handling are exactly the kind of security-sensitive, easy-to-get-subtly-wrong logic
that AGENTS.md's "No custom cryptography" rule exists to keep out of bespoke code, and a
maintained library is the safer, ecosystem-standard choice.

Selection criteria for the library (applied at implementation time, per Dependency Governance —
[CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)):

```text
actively maintained
compatible with the approved PHP/Laravel versions
RFC 6238 compliant
no mandatory external service (must run locally, stateless)
no mandatory daemon
supports secure secret generation
supports a configurable verification window (clock-drift tolerance)
exposes enough information/validation control (e.g. the accepted time-step/counter, or an
  equivalent verification result detail) for the application to enforce the mandatory replay
  policy in "TOTP Replay Protection" below — a package that makes that guarantee impossible is
  NOT an acceptable choice, regardless of its other merits
acceptable security history (no known unpatched, unaddressed vulnerabilities)
license compatible with this project
pinned and reviewed during implementation authorization, not installed by this specification
```

Package choice cannot weaken this security contract: the replay-prevention requirement below is
mandatory for the IMP-002 baseline, not an optional enhancement contingent on what a chosen
library happens to support.

### Enrollment

```text
1. Principal is already authenticated (STANDARD at minimum).
2. A cryptographically secure TOTP secret is generated.
3. The secret is stored as a PENDING, encrypted enrollment secret — mfa_enabled remains false
   throughout this stage.
4. The secret (e.g. via QR code) is shown to the principal to add to an authenticator app.
5. The principal submits one valid TOTP code generated from the new secret, proving possession.
6. Only on that proof does enrollment atomically complete: mfa_enabled becomes true, the pending
   secret becomes the active secret, a set of recovery codes is generated, and the event is
   audited (see "Transaction Boundaries").
```

Abandoned enrollment: a pending secret that is never confirmed does not enable MFA; it expires or
is replaced if the principal restarts enrollment. Starting a new enrollment attempt invalidates
any previous unconfirmed pending secret — there is never a window with two pending secrets.

### Verification (Challenge)

```text
required as a second factor after a successful password check, for any identity with MFA enabled
a narrow, rate-limited verification window is allowed per the selected library's RFC 6238
  clock-drift tolerance
repeated failed challenges are rate-limited/throttled the same way login failures are
```

### TOTP Replay Protection (M04 — Mandatory)

For each of the following sensitive contexts, replay prevention is REQUIRED — not conditional on
what a chosen library happens to support (see "Implementation Contract," which requires the
selected library to expose enough information to make this enforceable):

```text
MFA enrollment confirmation
ELEVATED assurance step-up via TOTP
MFA disable confirmation
MFA reset confirmation
```

The implementation MUST prevent a successful re-use of the same accepted TOTP value within the
same accepted time step, for the same (User, security context/challenge) pair, once that code has
already been successfully consumed for that context. Conceptually, this requires tracking, per
User and per sensitive-challenge context, the most recently accepted time-step/counter (or an
equivalent challenge-specific consumption record / session-bound security nonce) — the exact
storage shape is an implementation-time decision (see "Database Design Requirements >
MFA secret / recovery storage," `last_accepted_step`), not fixed here. This is scoped narrowly to
the four contexts above — it is NOT a global, permanent, cross-context OTP ledger — and the design
must remain deterministic, transaction-safe where needed, testable, and shared-hosting-compatible
(no additional daemon or external state store beyond the database/cache already in use).

### Concurrent TOTP Replay

If two requests submit the SAME valid TOTP code concurrently for the same sensitive challenge
(enrollment activation, ELEVATED step-up, or MFA disable/reset), EXACTLY ONE may produce the
security transition (enable MFA, grant ELEVATED, or disable/reset MFA); the other MUST fail as
replay/already-consumed. This is enforced via transaction/atomic state semantics at implementation
time (e.g. an atomic "mark this step consumed" write guarded so a second concurrent writer sees it
already consumed) — the same pattern already required for recovery-code consumption above.

### Recovery

```text
a set of cryptographically secure, sufficiently high-entropy recovery codes is generated at
  enrollment time
codes are displayed to the principal exactly once, at generation/regeneration time — never
  re-displayed afterward
codes are stored ONLY as hashes (via the framework hasher, like a password) — never plaintext,
  never reversibly encrypted
using a recovery code completes authentication in place of a TOTP challenge, EXACTLY once per
  code — consumption is transaction-safe: under concurrent replay attempts, only one succeeds,
  and the consumed code's hash record is atomically marked used (or removed)
regeneration invalidates ALL previous recovery codes, generates a new set, displays it once, and
  is audited
```

### Reset / Disable

```text
disabling or resetting MFA (e.g. after device loss) requires BOTH fresh credential confirmation
  AND ELEVATED assurance (or the equivalent strongest mechanism the approved assurance
  architecture supports) — this is stricter than a single-factor check, consistent with MFA
  reset being one of the most sensitive operations in this specification; this requirement is not
  weakened for any self-service path
administrative (non-self-service) MFA reset authority belongs to a future IMP-003+ Authority
  Assignment; IMP-002 defines only the identity-initiated self-service path plus this boundary
```

On successful reset/disable, the following are ALL REQUIRED, deterministically (not conditionally
"where security policy requires it"):

```text
the current TOTP secret is invalidated
old recovery codes are invalidated
ELEVATED Authentication Assurance is invalidated
ALL of the User's OTHER active sessions are invalidated
if the current session is retained (i.e. this was a self-service action performed from within an
  active session), that session's ID is securely rotated and its assurance falls back to STANDARD
  — it does not simply "keep going" post-reset
for an administrative/security-recovery path where the acting requester's session is not the
  target User's own ordinary session (an IMP-003+-authorized scenario), ALL of the target User's
  sessions are invalidated, including what would otherwise have been "the current session" in the
  self-service case
an audit event is emitted
```

### MFA Secret Storage

```text
TOTP secrets are RECOVERABLE secrets (the verification algorithm needs the plaintext value to
  compute a comparison code) — they are therefore ENCRYPTED at rest using Laravel's framework
  encryption capability (the `Crypt` facade / an encrypted Eloquent cast), never hashed (hashing
  a TOTP secret would make verification impossible) and never stored plaintext.
Secrets are never logged, never included in any audit payload, never returned by any endpoint
  other than the enrollment flow itself, and never re-displayed after initial enrollment
  confirmation.
Access to the secret for verification purposes is limited to the authentication domain's own
  verification logic.
Recovery codes, unlike the TOTP secret, are one-way HASHED (like a password) — verification only
  ever needs to check "does this code match a stored hash," never recover the original value.
Encryption-key rotation compatibility follows whatever Laravel's framework encryption/key-rotation
  facilities already provide — no custom key-rotation mechanism is invented here; consult
  docs/04-security/SECURITY-ARCHITECTURE.md at implementation time for any additional constraint.
```

---

## Account / Security Model (Q24)

Q24 separates what an earlier draft of this specification incorrectly combined into a single
`User.status` enum into three independent concerns:

### 1. Identity Lifecycle (persistent)

```text
ACTIVE      normal state
DISABLED    permanently deactivated by an administrative action; authentication denied
```

### 2. Verification (a field, not a lifecycle value)

```text
email_verified_at   nullable timestamp — see "Verification." "pending_verification" is NOT a
                     persistent Identity Lifecycle value.
```

### 3. Persistent Security Restriction

```text
NONE        no restriction
SUSPENDED    temporarily blocked from authenticating (e.g. abuse/fraud signal); reason not
             exposed in the public error response
```

### 4. Transient Brute-Force Lockout (NOT persistent lifecycle state)

Repeated failed login/MFA attempts trigger rate limiting/throttling (see "Rate Limiting / Abuse
Control") — this is time-bounded, tracked via the Authentication Security / Rate Limiter
infrastructure (Laravel's `RateLimiter` / cache-backed throttling — see "Database Design
Requirements > Authentication Rate-Limiter State"), and is explicitly NOT a persistent `LOCKED`
lifecycle value stored on `User`. It denies authentication only for its own duration, then expires
on its own — no `failed_login_attempts` or `locked_until` column exists on `users` (M05; see
"Identity Model" and "Database Design Requirements").

Rate-limiter keys MAY combine, depending on the flow: the normalized login email, the requesting
IP/address context, the authentication flow being throttled (login, MFA challenge, etc.), and the
User identity once known. No specific key scheme or numeric limit is fixed by this specification;
implementation chooses secure, configurable defaults. This uses the existing database/file cache
store already configured by IMP-001 — no mandatory Redis.

### Rate Limit vs. Suspension — Explicit Distinction

```text
RATE LIMIT / THROTTLE                   SUSPENDED
= transient, automated protection       = persistent security restriction
= expires according to limiter policy   = requires an authorized transition/recovery to clear
= NOT User lifecycle/schema state       = an explicit `security_restriction` value on User
```

Too many failed logins (or failed MFA challenges) MUST NOT automatically mutate
`security_restriction` to `SUSPENDED` — that would silently convert a transient, self-expiring
protection into a persistent security state without an authorized decision to do so. No such
automatic policy is introduced by this specification; a future, explicitly authorized deterministic
security policy (per "Transition Rules" below) could choose to do so, but that is not decided here.

### Authentication Effect

```text
ACTIVE + Security Restriction = NONE  -> authentication potentially allowed (subject to
                                          transient lockout state and credential correctness)
DISABLED                               -> authentication denied
Security Restriction = SUSPENDED       -> authentication denied
transient brute-force lockout active    -> authentication denied for its duration
email unverified                       -> does not by itself deny authentication — see
                                          "Verification"
```

### Transition Rules

```text
ACTIVE -> DISABLED, DISABLED -> ACTIVE
NONE -> SUSPENDED, SUSPENDED -> NONE
```

Every persistent transition requires: an authorized future security authority (IMP-003+) or an
explicitly authorized deterministic security mechanism (not invented by IMP-002); an audit event;
a reason/context where required; a timestamp. IMP-002 does not define WHO (which role/authority)
may perform these transitions — only their effect and their audit/transaction requirements.

Entering DISABLED or SUSPENDED must, atomically:

```text
deny new authentication attempts
invalidate all active authenticated sessions for that identity
invalidate ELEVATED assurance
prevent any persistent/remember-me authentication (moot under the current deferred baseline)
apply appropriate protection to reset/recovery flows per "Password Reset"/"MFA" (e.g. a
  password-reset or MFA-recovery request for a SUSPENDED/DISABLED identity still returns the
  same generic response as any other request — it does not itself need to succeed in restoring
  access, since that requires the security-authority-gated transition back to ACTIVE/NONE first)
```

### Business Boundary (unchanged, reaffirmed)

The following are never stored as `User` identity status — they are Business Authority concepts
(docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md), not Identity concepts:

```text
Fundraiser approval
Partner approval
Beneficiary approval
Financial approval
Campaign authority
```

A Fundraiser or Partner Representative whose business approval is pending is, from IMP-002's
perspective, simply ACTIVE with Security Restriction NONE — their lack of business authority is
enforced entirely by IMP-003+, never by an identity-level status.

---

## First Super Admin Bootstrap (Q25)

```text
No public bootstrap web endpoint exists — there is no HTTP route that can create a Super Admin
  identity.
No public Super Admin self-registration.
Provisioning is via a controlled, one-time Artisan/CLI command (e.g. conceptually
  `platform:bootstrap-super-admin` — the exact command name is an implementation detail, not
  locked by this specification).
The command is operator-initiated and interactive: the operator supplies the identity's email
  and a password via secure interactive input (e.g. a masked prompt) — there is no default
  email and no default password.
No plaintext credential is ever persisted in code, configuration, a seeder, a log, or an audit
  payload — the password is hashed through the same framework hasher as any other `User`
  password before storage.
An auditable bootstrap event/evidence record is created (e.g. an audit entry noting that a
  bootstrap occurred, by which operator context, and when — without recording the credential
  itself).
A durable one-time guard prevents the command from creating a second bootstrap identity once the
  initial bootstrap has completed — repeating the command is refused, not silently re-run.
The mechanism is shared-hosting-compatible: it is a one-shot Artisan command runnable during
  deployment/maintenance (e.g. via SSH or a hosting control panel's command runner), not a
  permanently running service or a required daemon.
```

### Scope Boundary

```text
IMP-002 -> creates/bootstraps the initial human Identity (a `User` record) and records the
           bootstrap's security evidence. It does NOT create any role, permission, scope, or
           business-authority record for that identity.
IMP-003 -> owns the canonical Super Admin role/permission/scope/business-authority model. If the
           architecture requires the first Super Admin's authority to exist immediately upon
           bootstrap, IMP-003 (or a coordinated cross-stage step at that time) is responsible for
           consuming/authorizing the already-bootstrapped Identity — IMP-002 does not invent a
           parallel or provisional role system to bridge that gap itself.
```

The bootstrapped identity is, from IMP-002's own perspective, an ordinary `User` — ACTIVE, no
Security Restriction, `email_verified_at` set at bootstrap time (the operator is presumed to have
verified it out-of-band by directly entering it), MFA not enabled by default (may be enrolled
afterward through the normal MFA lifecycle above). No IMP-002 schema or logic distinguishes a
"bootstrap" `User` row from any other `User` row after creation — the distinction lives entirely
in the one-time bootstrap-guard mechanism and its audit trail, not in the `User` table itself.

---

## Principal Exposure

```text
web requests:     the framework's authenticated-user resolution exposes the `User` plus its
                  Authentication Assurance state (see "Authentication Assurance").
API requests:     same principal concept; API authentication mechanism is undecided (see "API
                  Authentication Boundary") but must resolve to the same `User` + assurance shape.
jobs:              IMP-002 does not implement System Principal job plumbing (deferred item).
audit:             every audited event records the acting `User` (or "system"/"anonymous").
future policies:   IMP-003's canonical authorization formula consumes "Authenticated" and
                  "Required Authentication Assurance" directly from what IMP-002 exposes here.
```

Distinguish, only where the architecture already names them: Human Principal, System Principal,
Anonymous Principal. Integration Principal is not addressed by IMP-002.

---

## API Authentication Boundary

Unchanged: no `/api/v1` business routes exist yet, and no materialized document decides the
future API authentication mechanism. Deferred, non-blocking. The locked "API token authority
intersection... never a union" rule (SECURITY-ARCHITECTURE.md) applies unchanged whenever that
decision is made.

---

## CSRF / Cookie Security

Standard Laravel web-guard defaults, not weakened: CSRF protection on all state-changing web
routes; secure/HttpOnly session cookie; framework-default SameSite; session regeneration on
login/logout/password change/email-change promotion/MFA enrollment completion; HTTPS expected in
production.

---

## Rate Limiting / Abuse Control

Required on: login, donor self-registration, fundraiser self-registration, password reset
request, verification resend, TOTP verification (challenge attempts), invitation acceptance. Use
Laravel's framework rate limiter — no custom implementation, no mandatory Redis. Exact numeric
limits are not fixed by any materialized document; configurable, secure defaults are required.

---

## Audit Events

Emission points only; the sink (owned by Governance & Platform Services per MODULE-OWNERSHIP.md
§16) is a later stage's responsibility.

```text
identity created
self-registration completed
invitation issued
invitation revoked
invitation accepted
login succeeded
login failed
logout
password changed
password reset requested
password reset completed
email change requested
email change completed (promotion)
email change conflicted (IDENTITY_EMAIL_CHANGE_CONFLICTED — target email already in use by
  another User; records the request reference, User identity, conflict_reason_code, timestamp,
  and security context; never the verification token, password, TOTP secret, recovery code, or
  raw database/exception detail)
email verified
MFA enrolled
MFA challenge succeeded
MFA challenge failed
MFA recovery code used
MFA recovery codes regenerated
MFA reset / disabled
account/security transition: ACTIVE <-> DISABLED
account/security transition: NONE <-> SUSPENDED
first Super Admin bootstrap completed
```

---

## Privacy

Authentication responses must not allow account enumeration: login failure, password-reset
request, registration-uniqueness message (unavoidable trade-off, not paired with further detail),
verification resend, and invitation edge cases (invalid/expired/revoked/already-used) all use
generic responses that do not confirm account existence beyond what is unavoidable. Never expose
the internal BIGINT id; expose ULID only where a consuming context needs it (see "Public ID").

---

## Retention Boundary

Unchanged: closing/deleting a `User` MUST NOT cascade-delete or orphan immutable financial/
Ledger/audit records. Anonymization/pseudonymization is the likely mechanism; exact design is
deferred.

---

## Public ID

The internal BIGINT id is never exposed as a stable public identifier. `public_id` (ULID) is used
for any external-safe reference, but is not automatically included in every authentication
response by default.

---

## Database Design Requirements

Specification only — no migration is created by this document.

### `users` (or the eventual entity name)

```text
purpose:            canonical Identity + Authentication record
ownership:           Identity & Organization
key fields:          id (BIGINT unsigned PK), public_id (ULID),
                     email (canonical, normalized, unique), email_verified_at (nullable
                     timestamp), password (hashed),
                     identity_status (ACTIVE | DISABLED), security_restriction (NONE |
                     SUSPENDED), last_login_at (nullable timestamp), mfa_enabled (boolean),
                     phone (nullable, optional), phone_verified_at (nullable, optional),
                     created_at, updated_at
unique constraints:  email (canonical normalized representation), public_id
security-sensitive:  password (hashed; never logged)
indexes:              email (unique), public_id (unique), identity_status, security_restriction
retention concerns:   no default soft-delete; closure/anonymization mechanism deferred
NOT included:         remember_token (see "Remember-Me (Deferred)"); pending_email (see
                     "EmailChangeRequest" below); failed_login_attempts, locked_until (M05 — see
                     "Authentication Rate-Limiter State" below — these are never persistent User
                     columns)
```

### `EmailChangeRequest` (M01 — separate table, not a `users` column)

```text
purpose:            immutable, per-request email-change challenge — see "Canonical Email Change
                     Lifecycle" for the full field list and lifecycle rules
ownership:           Identity & Organization
key fields:          id, user_id (FK to users.id), normalized_pending_email,
                     verification_token_hash, requested_at, expires_at, verified_at (nullable),
                     superseded_at (nullable), cancelled_at (nullable), conflicted_at (nullable —
                     durable terminal conflict marker; see "Uniqueness Conflict — Durable Terminal
                     State"), conflict_reason_code (nullable — controlled internal value, e.g.
                     TARGET_EMAIL_ALREADY_IN_USE; never a raw database/exception message),
                     generation (per-user sequence), created_at, updated_at
unique constraints:  at most one row per user_id with verified_at/superseded_at/cancelled_at/
                     conflicted_at all null (enforced at the application/transaction level — see
                     "One Active Request Per User"; CONFLICTED does not count as active)
security-sensitive:  verification_token_hash (hashed, never plaintext)
indexes:              user_id, expires_at
retention concerns:   superseded/cancelled/expired/consumed/conflicted rows may be pruned per a
                     retention policy, but a CONFLICTED row is otherwise kept as immutable
                     historical evidence of the conflict, distinct from ordinary pruning
                     candidates — exact retention timing is an implementation detail, not fixed
                     here
```

### Password reset support

```text
purpose:            single-use, time-limited password-reset token storage
key fields:          email, token (hashed), created_at
security-sensitive:  token (hashed, never plaintext)
indexes:              email
```

### MFA secret / recovery storage (separate table)

```text
purpose:            TOTP secret and recovery-code storage, separate from `users` to limit blast
                     radius
key fields:          user_id (FK to users.id), secret (encrypted at rest — recoverable, never
                     hashed), pending_secret (encrypted, nullable — see "Enrollment"),
                     recovery_codes (each stored hashed, individually markable as used),
                     last_accepted_step (per-challenge-context replay guard — see "TOTP Replay
                     Protection"; conceptual only, exact shape is an implementation-time,
                     library-informed decision), created_at, updated_at
unique constraints:  one active secret per user_id (baseline)
security-sensitive:  secret (encrypted), pending_secret (encrypted), recovery_codes (hashed) —
                     the most sensitive columns in the entire IMP-002 schema
indexes:              user_id
```

### Authentication Rate-Limiter State (M05 — not a database table by default)

```text
purpose:            transient brute-force/abuse protection counters (failed-login count,
                     temporary lockout window) for login, MFA challenges, and other throttled
                     flows
ownership:           Authentication Security / Rate Limiter infrastructure — explicitly NOT the
                     `users` table (see "Account / Security Model")
storage:             Laravel's framework `RateLimiter` / cache-backed throttling, using the
                     database or file cache store IMP-001 already configured — not a dedicated
                     migration-created table, and not a User column. If a durable store is later
                     needed beyond the cache's own TTL semantics, that is an implementation-time
                     detail, not a schema requirement of this specification.
retention concerns:   entries expire according to limiter policy; expiry never mutates
                     `users.identity_status` or `users.security_restriction`
```

### Invitation storage

```text
purpose:            invitation lifecycle for Partner Representative / Internal Administrative
                     Identity / Super Admin provisioning
key fields:          id, public_id (optional), intended_email, token_hash, issued_at, expires_at,
                     accepted_at (nullable), revoked_at (nullable), issuer reference, revoker
                     reference (nullable), created_at, updated_at
security-sensitive:  token_hash (hashed, never plaintext)
indexes:              intended_email, expires_at
```

### First Super Admin bootstrap evidence (Q25)

```text
purpose:            durable one-time guard + audit evidence that the initial bootstrap occurred
key fields:          a minimal marker (e.g. a single-row/singleton record or a dedicated flag) —
                     exact shape is an implementation detail; MUST NOT store the credential
security-sensitive:  none (no credential stored here)
retention concerns:   permanent — this is the guard that prevents a second initial bootstrap
```

### `sessions` (existing, IMP-001)

ALREADY EXISTS. `user_id` becomes populated once `User` exists; no schema change anticipated.

### Cross-cutting

```text
Foreign keys:        any later module referencing User does so via its internal BIGINT id.
Case/normalization:  users.email and EmailChangeRequest.normalized_pending_email are both
                     normalized before uniqueness comparison and storage (see "Email
                     Normalization").
Money/DECIMAL:       not applicable.
```

---

## Transaction Boundaries

```text
account/identity creation (self-registration, invitation acceptance, or provisioning, including
  the first Super Admin bootstrap) — transactional
password change / password reset completion (rehash + session rotation + other-session
  invalidation + assurance invalidation + reset-token invalidation) — transactional
canonical email change promotion: locks User + EmailChangeRequest rows, re-checks the request is
  still current/unexpired/unconsumed, re-checks target-email uniqueness, promotes
  users.email <- request.normalized_pending_email, sets email_verified_at, marks the request
  consumed, rotates the session, invalidates other sessions, invalidates ELEVATED assurance,
  invalidates stale reset tokens against the old email — transactional (see "Canonical Email
  Change Lifecycle > Success Semantics")
email-change request creation: supersedes any prior active request for the User and creates the
  new request atomically, so there is never a window with two simultaneously active requests —
  transactional
email verification completion (mark verified + invalidate verification token) — transactional
MFA enrollment completion (pending secret confirmation + activation + recovery-code generation)
  — transactional
MFA recovery-code consumption — transactional (exactly one consumer succeeds under concurrency)
MFA reset/disable (secret/recovery invalidation + assurance invalidation + other-session
  invalidation + current-session rotation + audit event) — transactional
invitation acceptance (token consumption + User creation or binding + credential establishment)
  — transactional
persistent security-state transition (ACTIVE<->DISABLED, NONE<->SUSPENDED) — transactional
first Super Admin bootstrap initialization (identity creation + durable one-time guard write) —
  transactional
```

---

## Concurrency / Idempotency

```text
duplicate registration:       unique index on email prevents a duplicate row; handled as a normal
                               "identifier already in use" outcome
simultaneous password reset:   a consumed/expired token used a second time MUST fail safely
verification/invitation token replay: a consumed token/link MUST be rejected on reuse
multiple reset requests:       a new reset token may invalidate prior outstanding ones
email-change race:              see "Canonical Email Change Lifecycle > Concurrency"
invitation acceptance race:      see "Invitation Boundary > Lifecycle Rules"
MFA enrollment race:             starting new enrollment invalidates any previous unconfirmed
                                pending secret; no window has two simultaneously valid secrets
MFA recovery-code race:          exactly one concurrent consumption attempt succeeds
TOTP replay race:                exactly one concurrent submission of the same accepted code
                                succeeds for enrollment activation, ELEVATED step-up, or MFA
                                disable/reset — see "TOTP Replay Protection" / "Concurrent TOTP
                                Replay"
first-bootstrap race:            the durable one-time guard ensures at most one bootstrap
                                succeeds even under a concurrent double-invocation of the command
concurrent account updates:      standard optimistic handling (`updated_at` check) is sufficient;
                                the Financial Idempotency invariant does not apply to
                                non-financial Identity records
```

---

## Error Model

Generic messages for: authentication failure (no distinction between unknown email/wrong
password/wrong MFA code), account security restriction, rate limit, expired/invalid token
(verification, reset, or invitation alike). "Verification required" is shown only to an
already-authenticated principal.

---

## Route Ownership

Categories only: guest authentication (login, donor/fundraiser self-registration); invitation
acceptance; authenticated account security (password change, email change, account security
state, MFA management, logout, "logout other sessions"); verification (verify-email, resend);
password recovery (forgot/reset). Same-origin, single root domain, no separate auth subdomain.

---

## UI Boundary

May implement: login, donor/fundraiser registration, invitation acceptance, forgot/reset
password, email verification, email change, account security (password change, MFA management),
MFA enrollment/challenge UI. Must NOT implement any business dashboard (donor/fundraiser/partner/
admin). A single neutral authenticated landing page is acceptable to prove the flow renders
end-to-end.

---

## Testing Requirements

At minimum, before IMP-002 implementation may be considered complete:

```text
donor/fundraiser self-registration; partner rep/internal admin/super admin CANNOT self-register
invitation issuance, revocation, acceptance (correct email), rejection (wrong email/revoked/
  expired), replay, and the email-uniqueness race (task item 20) resolved without duplicate
  identities
credential hashing (password, MFA secret encrypted not hashed, recovery codes hashed) never
  plaintext in logs/responses
login success/failure (with and without MFA); MFA challenge success/failure; recovery-code
  single-use
session fixation protection; logout invalidation and rotation
DISABLED and SUSPENDED accounts cannot authenticate; transient lockout (not a persistent status)
  denies authentication for its duration and only its duration
password change: other sessions invalidated, current session rotated, ELEVATED reset, reset
  tokens invalidated
password reset: generic response regardless of existence; single-use; expiry; same invalidation
  consequences as password change; old email stops being a valid reset target after email change
canonical email change (request-versioned, M01): a new EmailChangeRequest immediately supersedes
  the prior active request, and the prior request's token can no longer verify anything (not even
  the newer pending email); an expired/cancelled/superseded/already-consumed/conflicted request's
  token is always rejected; a token for one request can never promote a different request's email;
  two rapid successive change requests (A then B) leave A's token non-functional while B's may
  succeed; concurrent verification attempts against the same request resolve to exactly one
  success; successful promotion is atomic (session rotation, other-session invalidation, ELEVATED
  invalidation, stale reset-token invalidation, audit)
canonical email change — uniqueness conflict (P2-m01): a target email available at request
  creation but acquired by another User before promotion causes the promotion transaction to roll
  back completely, leaving `users.email`/`email_verified_at` unchanged; the request durably becomes
  CONFLICTED (`conflicted_at` set, `conflict_reason_code` = `TARGET_EMAIL_ALREADY_IN_USE`) in a
  separate transaction; the token can no longer be used afterward; the same conflicted request can
  never be retried, but the User may immediately create a new, independent request; conflict
  finalization is race-safe against a concurrent cancellation/supersession/verification of the
  same request — an already-reached terminal state is never overwritten; the outward error
  response reveals no database detail or which account owns the target email
email verification: valid/expired/invalid/replay cases
Authentication Assurance: STANDARD after login; ELEVATED only after fresh step-up; finite expiry
  with fallback to STANDARD; invalidated by every trigger listed in "Authentication Assurance";
  new session does not inherit prior ELEVATED
MFA: enrollment not enabled until TOTP-confirmed; abandoned enrollment safe; secret encrypted;
  recovery codes hashed and shown once; recovery replay rejected; regeneration invalidates old
  codes; TOTP challenge throttled; disable/reset requires fresh credential + ELEVATED and
  deterministically invalidates ALL other sessions, rotates the current session, invalidates
  ELEVATED assurance, and invalidates the TOTP secret and old recovery codes (not merely "where
  security policy requires it")
MFA replay (M04, mandatory — not conditional): the SAME accepted TOTP code cannot be reused to
  elevate assurance twice, disable MFA twice, reset MFA twice, or activate enrollment twice; of
  two concurrent submissions of the same accepted code for the same sensitive challenge, exactly
  one succeeds and the other fails as replay/already-consumed; a genuinely new valid TOTP code in
  a later permitted time-step works normally according to policy; a recovery code remains
  independently single-use regardless of TOTP replay state
Q24 account/security model: DISABLED/SUSPENDED deny auth; unverified email is not confused with
  lifecycle status; the `users` schema contains no `failed_login_attempts` and no `locked_until`;
  failed-login throttling works entirely through the rate-limiter/cache mechanism, never by
  mutating `users`; limiter expiration never changes `identity_status` or `security_restriction`;
  SUSPENDED remains distinct from, and is never auto-triggered by, transient throttling; business-
  approval status never appears on User
Q25 bootstrap: first bootstrap succeeds with no default credential; a second initial bootstrap
  attempt is rejected; no public route can create a Super Admin; no RBAC schema is introduced by
  IMP-002
remember-me: not enabled in baseline; no remember_token behavior required
rate limiting across every listed flow
account enumeration resistance across every relevant flow
CSRF on all state-changing auth routes
database constraints: duplicate email rejected at the database level
audit events: every listed event actually emitted
guest/authenticated middleware behavior
```

Explicitly required negative coverage: no test may assert or rely on a role, permission, scope,
or business-authority check.

---

## Security Testing

Plaintext-credential prevention (password/MFA-secret/recovery-codes); credential/token leakage
(logs, responses, payloads); MFA secret never re-displayed after enrollment; session fixation;
CSRF; brute-force controls; expired-token reuse; credential/session invalidation on password
change, password reset, and email-change promotion; security-restricted account authentication;
first-bootstrap credential never persisted in plaintext anywhere (code, config, seeder, log,
audit). Penetration testing is NOT claimed or required at this stage.

---

## Shared Hosting Compatibility

Unchanged: Apache/LiteSpeed, PHP 8.3, MySQL, database sessions/queue, cron, Laravel Storage,
compiled assets — all unchanged. MFA (TOTP via a maintained library) requires no mandatory SMS
provider, external MFA SaaS, Redis, or WebSocket — TOTP verification is a stateless, local HMAC
computation. The Q25 bootstrap command is a one-shot Artisan invocation, not a daemon. Email
delivery uses Laravel's standard mail configuration with no mandatory transport-service
dependency.

---

## Dependency Policy

No new package is proposed for the non-MFA baseline. For TOTP, a maintained RFC 6238 library is
now a REQUIRED addition (not merely "may be warranted" — see "MFA > Implementation Contract"); its
specific selection is deferred to implementation-time Dependency Governance review
([CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)), applying the selection criteria listed
under "MFA."

---

## Human Decisions Required

```text
NONE.
```

Q21-Q25 are all FINAL/LOCKED and materialized in the canonical
[docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md),
and fully incorporated above. Q7 (Beneficiary registration policy) is likewise FINAL/LOCKED and
referenced, not reopened. The Actor Catalog artifact gap remains a non-blocking documentation gap.

---

## Definition of Ready

```text
[x] Authoritative sources available and read
[x] Q21-Q25 canonical authority exists            -- PASS (materialized in
                                                    HUMAN-DECISION-REGISTER.md)
[x] Identity model unambiguous                     -- PASS
[x] Login identifier unambiguous                    -- PASS (Q21)
[x] Registration policy unambiguous                  -- PASS (Q22; Beneficiary via Q7, deferred
                                                    implementation only)
[x] Email-change lifecycle deterministic             -- PASS (request-versioned
                                                    EmailChangeRequest entity; exact
                                                    token-to-request binding; supersession is
                                                    mandatory, not optional; a durable
                                                    `conflicted_at`/`conflict_reason_code`
                                                    terminal state covers the uniqueness-collision
                                                    case with race-safe finalization — see
                                                    "Canonical Email Change Lifecycle")
[x] Invitation issuer/revocation model defined        -- PASS (see "Invitation Boundary")
[x] Password/reset model unambiguous                 -- PASS
[x] Password-change session consequences defined      -- PASS
[x] Verification rules unambiguous                   -- PASS
[x] Authentication Assurance lifecycle executable     -- PASS (fields, lifetime, invalidation
                                                    triggers all specified)
[x] MFA contract unambiguous                          -- PASS (maintained-library requirement
                                                    that must support mandatory, non-conditional
                                                    replay protection; enrollment/recovery/reset/
                                                    concurrent-replay all defined)
[x] Q24 account/security model fully applied          -- PASS (no failed_login_attempts/
                                                    locked_until on `users`; rate-limiter state
                                                    explicitly separated from persistent identity
                                                    state)
[x] Q25 first-bootstrap model fully applied            -- PASS
[x] Remember-me explicitly deferred                    -- PASS
[x] Security restrictions mapped                      -- PASS
[x] Database requirements defined                     -- PASS
[x] Stage boundaries clear                             -- PASS
[x] Tests defined                                      -- PASS
[x] Shared-hosting compatibility preserved              -- PASS
[x] No architecture conflicts                          -- PASS
[x] No RBAC implementation leakage                      -- PASS (explicit negative test
                                                    requirement; Q25's Super Admin bootstrap
                                                    creates no RBAC schema)
[x] No unresolved implementation blocker                -- PASS
```

**Definition of Ready: PASS.**

---

## Definition of Done

```text
identity schema implemented: `users` (identity_status/security_restriction, NO
  failed_login_attempts/locked_until/pending_email columns), a separate EmailChangeRequest table,
  password-reset, MFA secret/recovery (with replay-guard state), invitation, and bootstrap-guard
  tables
secure email + password authentication implemented
approved registration models implemented per actor
canonical email-change lifecycle implemented via request-versioned EmailChangeRequest rows
  (supersession on new request, exact request/token binding, atomic promotion, session/reset-token
  invalidation, and a durable race-safe CONFLICTED terminal state for target-email uniqueness
  collisions) — no `users.pending_email` column
email verification implemented as specified
password reset implemented securely, with full session/assurance invalidation
TOTP MFA capability implemented via a maintained library (enrollment, verification, recovery,
  reset, and MANDATORY replay protection for enrollment/step-up/reset confirmations, including
  under concurrent replay)
Authentication Assurance lifecycle implemented (finite ELEVATED lifetime, all invalidation
  triggers wired)
Q24 account/security model implemented (no persistent LOCKED status on `users`; brute-force
  lockout lives entirely in rate-limiter/cache infrastructure, never as a `users` column)
Q25 first Super Admin bootstrap CLI implemented, with durable one-time guard and no plaintext
  credential anywhere
remember-me NOT implemented (explicitly deferred)
session security verified (fixation, rotation, logout, all invalidation triggers)
rate limiting verified across all listed flows
enumeration resistance tested
audit events emitted
RBAC/business authorization absent
shared-hosting compatibility preserved
tests passing (functional + security)
independent review passing
```

---

## Risks

```text
The TOTP library selection should be resolved early in implementation to avoid rework of the MFA
  secret-storage schema. Since the library must also support the mandatory replay-protection
  contract (see "MFA > Implementation Contract"), not every popular TOTP package may qualify —
  this should be verified before committing to a specific dependency.
Retrofitting remember-me later requires re-auditing every session-invalidation trigger in this
  specification to add persistent-credential invalidation at each one — feasible, but should be
  treated as a specification update, not a silent addition.
When the Beneficiary domain stage designs Q7's detailed mechanics, it may add a new actor row and
  a registration flow without needing to change the `User` entity itself.
The Q25 bootstrap command's one-time guard must be implemented as a genuinely durable,
  race-safe mechanism (not merely an application-level check) to withstand a concurrent
  double-invocation during deployment automation.
```

---

## Deferred Items (Non-Blocking)

```text
Beneficiary domain implementation of Q7 (policy locked; mechanics/stage ownership deferred)
API authentication mechanism for future /api/v1
System Principal / Integration Principal implementation ownership
Audit domain storage/query mechanism (emission points specified; sink not built)
Retention/anonymization mechanism for closed identities
Exact route paths for authentication
Administrative MFA reset authority and administrative persistent-security-state transition
  authority (both require an IMP-003+ Authority Assignment; self-service/mechanical paths are
  specified now)
Actor Catalog artifact under docs/06-domains/identity/ (documentation gap, not blocking)
Remember-me (explicitly deferred per m01 — see "Remember-Me (Deferred)")
Subsequent (post-first) Super Admin provisioning mechanism (uses IMP-003+'s ordinary provisioning
  path, not the Q25 bootstrap command)
```

---

## Implementation Gate

```text
IMP-002 Specification:        READY FOR TARGETED READINESS RE-AUDIT
IMP-002 Definition of Ready:   PASS
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
```

STOP.

Do not implement IMP-002. Do not create migrations, models, controllers, middleware, routes, UI,
factories, seeders, or tests for this stage. Do not install any authentication/TOTP package.
Implementation authorization requires an independent Codex targeted readiness re-audit and, if
that passes, explicit Human/stage authorization, per
[docs/00-governance/IMPLEMENTATION-GOVERNANCE.md](../00-governance/IMPLEMENTATION-GOVERNANCE.md).
