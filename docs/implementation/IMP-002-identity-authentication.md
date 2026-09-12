# IMP-002 — IDENTITY + AUTHENTICATION

## Document Control

```text
Task ID: IMP-002
Stage: IMPLEMENTATION 02
Title: Identity + Authentication
Document Type: Implementation Specification
Status: DRAFT — READY FOR READINESS REVIEW
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
Q21 — Login Identifier                = EMAIL AS CANONICAL LOGIN IDENTIFIER — FINAL / LOCKED
Q22 — Registration Model              = HYBRID REGISTRATION BY ACTOR — FINAL / LOCKED
Q23 — MFA Policy                      = CONFIGURABLE MFA, TOTP BASELINE — FINAL / LOCKED
```

These decisions, together with the Q1-Q20 register in
[docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md),
are now authoritative Level 1 input to this specification. Q21-Q23 are recorded here because they
were supplied directly to this specification task; the canonical register file itself is not
edited by this document (see "Evidence" in the accompanying remediation audit record for where
they were communicated).

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
```

**Actor Catalog reconciliation (corrects the prior draft):** the prior draft of this
specification treated the absence of a dedicated `docs/06-domains/identity/` Actor Catalog file as
a blocking Human Decision. On reconciliation, this was the wrong classification. No repository
document is titled "Actor Catalog," but
[docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §3 (route
prefixes `/donor/*`, `/fundraiser/*`, `/partner/*`, `/admin/*`) and §6 (domain names "Donor
Portal," "Fundraiser Portal," "Partner Portal") already named the actor set relevant to
authentication, and Q22 (this task) explicitly locks the registration model for exactly that
actor set (Donor, Fundraiser, Partner Representative, Internal Administrative Identity, Super
Admin). Combined, these are now sufficient authoritative identification of the actors IMP-002 must
support — see "Actors" below. This is reconciliation from existing authority, not invention of a
new actor, removal of one, or renaming one semantically. The one remaining open item (Beneficiary
— named as a business domain in MASTER-REQUIREMENTS.md §6 but not addressed by Q22) is recorded as
a non-blocking deferred item, not a Human Decision — see "Actors" and "Deferred Items."

The absence of a dedicated domain-level Actor Catalog *artifact* under `docs/06-domains/identity/`
is still true and is still worth closing, but it is correctly classified as a **documentation /
materialization gap**, not a Human Decision — see "Actor Catalog Artifact Gap" below.

---

## Purpose

Establish:

```text
1. Who is an authenticated principal (Identity).
2. How a principal proves control of that identity (Authentication).
3. How authenticated state is maintained (Session).
4. How credentials are securely managed.
5. How authentication-sensitive lifecycle events are handled (registration, invitation,
   verification, password reset, MFA, account security state).
6. How authentication assurance (STANDARD / ELEVATED, per
   docs/05-rbac/AUTHENTICATION-ASSURANCE.md) is represented for later authorization stages to
   consume.
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
Password credential handling (hash, update, reset)
Email verification lifecycle (canonical, per Q21)
Optional phone as contact data only (never a login identifier)
Configurable TOTP-baseline MFA (enrollment, verification, recovery)
Authentication Assurance state exposure (STANDARD / ELEVATED) for IMP-003 to consume
Account/security lifecycle states needed for authentication (active/suspended/disabled/locked)
Invitation lifecycle for non-self-registering actors (Partner Representative, Internal
  Administrative Identity, Super Admin)
Principal exposure to web requests, API requests, audit
Rate limiting / abuse control on authentication endpoints
Authentication-relevant audit events
Privacy-safe error responses (no account enumeration)
Minimal authentication UI (login, registration where decided, invitation acceptance, forgot/reset
  password, verification, account security, MFA enrollment/verification)
```

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
```

Rationale for the Fundraiser/Partner/Beneficiary profile exclusion: per
[docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md), "Fundraiser
Profile" is owned by Fundraising & Attribution (§5), "Partner Profile" by Partner (§7), and
Beneficiary "Profile" by Beneficiary & Distribution (§14) — not by Identity & Organization (§1),
which owns only "User identity, Organization membership, Principal organizational context." IMP-002
therefore implements only the shared credential-bearing identity; each business module's own
profile record (created in that module's later stage) references this identity by foreign key.

**Identity creation is never authorization.** Account creation (self-registered, invited, or
provisioned) is explicitly NOT equivalent to: role assignment, permission assignment, data scope,
business authority, financial authority, Partner authority, or Fundraiser approval. A Fundraiser
who self-registers (see "Registration Flows") receives only an authenticated Identity/Auth
subject — no Fundraiser business authority, no attribution eligibility, no Commission
entitlement. Those remain entirely IMP-003+/domain-stage concerns.

---

## Actors

Reconciled from [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md)
§3/§6 and Q22, plus [docs/05-rbac/DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md) "Ownership
Examples" and [docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md) (Super Admin
mention). No actor is added, removed, or renamed beyond aligning terminology with Q22's own
wording (e.g. Q22's "Partner Representative" corresponds to DATA-SCOPE-MODEL.md's "Partner User";
Q22's "Internal Administrative Identity" corresponds to DATA-SCOPE-MODEL.md's "Operational
Staff"/MASTER-REQUIREMENTS.md's `/admin/*`).

| Actor | Can Hold Human Identity? | Authentication Expected? | Registration / Provisioning Model | Public Representation Separate? | Authorization Owner | Notes |
|---|---|---|---|---|---|---|
| Donor | YES | YES | **Self-registration** (Q22) | YES — public donor representation is separate per Q6 (Human Decision Register: "Public Anonymity Only") and AGENTS.md ("Public donor representation != internal identity") | IMP-003+ (no authority granted at registration) | Baseline actor; `/donor/*` (MASTER-REQUIREMENTS.md §3) |
| Fundraiser | YES | YES | **Self-registration** (Q22); creates Identity/Auth subject only — no automatic Fundraiser business authority | YES — Fundraiser Profile is a separate record owned by Fundraising & Attribution (MODULE-OWNERSHIP.md §5) | IMP-003+ / Fundraising & Attribution module (attribution eligibility, Commission entitlement, etc. are never granted by registration) | `/fundraiser/*` |
| Partner Representative | YES | YES | **Invitation or domain-driven provisioning only** — no public self-registration baseline (Q22) | YES — Partner Profile owned by Partner module (MODULE-OWNERSHIP.md §7) | IMP-003+ / Partner module (Partner Verification, relationship ownership) | `/partner/*` |
| Internal Administrative Identity | YES | YES | **Invitation/provisioning only** — no public self-registration (Q22) | Not applicable in the same sense (internal identity; no public-facing representation is implied by this stage) | IMP-003+ (roles/permissions) | `/admin/*` |
| Super Admin | YES | YES | **Provisioning only** — no public self-registration (Q22) | Not applicable | IMP-003+ | `/admin/*`; RBAC-ARCHITECTURE.md explicitly notes "Super Admin != automatic Financial Authority" — reaffirmed: Super Admin identity provisioning grants no financial or business authority in IMP-002 or later without an explicit Authority Assignment |
| Beneficiary | Likely YES (DATA-SCOPE-MODEL.md: "Beneficiary -> own application/profile where permitted") | **UNDECIDED — deferred, non-blocking** | **UNDECIDED** — not addressed by Q22 | YES — Beneficiary data is HIGHLY_SENSITIVE per MASTER-REQUIREMENTS.md §10; Profile owned by Beneficiary & Distribution (MODULE-OWNERSHIP.md §14) | IMP-003+ / Beneficiary & Distribution module | Not routed yet (`/beneficiary/*` does not appear in MASTER-REQUIREMENTS.md §3); this gap does not block IMP-002 baseline, which can be extended with a Beneficiary registration model later without reworking the `User` entity — see "Deferred Items" |

Do NOT create role authorization rules, business authority, or per-actor permission logic from
this table — it governs authentication/registration eligibility only.

### Actor Catalog Artifact Gap

`docs/06-domains/identity/` remains an empty placeholder (`.gitkeep` only). Materializing a formal
Actor Catalog document there (consolidating the table above plus any future actor) would be
useful and is recommended, but its absence is a **documentation/materialization gap**, not a
blocking Human Decision — reconciliation above already supplies everything IMP-002 needs from
existing authority.

---

## Identity Model

### Canonical Identity Entity

Per [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §1
("Identity & Organization" owns "User identity"), the canonical credential-bearing entity remains
named **User** in this specification — unchanged by Q21-Q23, since no authoritative higher-level
source contradicts it. This is a naming choice, not an architecture decision.

`User` owns exactly:

```text
entity purpose:       authentication + shared identity, nothing business-domain-specific
primary identifier:   BIGINT unsigned internal key (docs/03-database/DATABASE-ARCHITECTURE.md
                       "Identity Conventions")
public identifier:    ULID (same source). NOT exposed in every authentication response by
                       default — see "Public ID" below.
login identifier:     email (Q21 — canonical, exclusive baseline; see "Email as Canonical Login
                       Identifier")
password credential:  hashed only (see "Credential Model")
contact (optional):   phone — contact data only, never a login identifier (see "Contact Phone")
status/lifecycle:     neutral identity/security states only — see "Security Restrictions"
verification state:   email_verified_at (baseline); phone_verified_at (only if phone is
                       collected, and only for contact purposes, not as a login gate)
MFA state:            mfa_enabled flag + reference to MFA secret storage (see "MFA")
security metadata:    failed-login counters, lock timestamps, last-login metadata (standard
                       Laravel-compatible fields; no invented mechanism)
timestamps:            created_at / updated_at (framework standard)
soft-delete/retention: NOT soft-deleted by default — see "Retention Boundary"
```

`User` does NOT own: Donor-specific fields, Fundraiser Profile, Partner Profile, Beneficiary
Profile/Application, Admin business preferences, roles, permissions, scopes, or business
authority. Each owning module (per MODULE-OWNERSHIP.md) references `User` by its internal ID when
that module is implemented in its own stage.

### Organization Membership

Unchanged from the prior draft: per
[docs/02-architecture/MASTER-ARCHITECTURE.md](../02-architecture/MASTER-ARCHITECTURE.md)'s locked
"Single Organization" principle, "Organization membership" (MODULE-OWNERSHIP.md §1) is interpreted
minimally as implicit single-organization membership for every `User`; no separate membership
table is required by this stage.

### Internal Identity vs. Public Representation

Unchanged principle, reaffirmed against Q21-Q23: `User` (internal identity, keyed by email) MUST
NOT be conflated with any public-facing representation:

```text
public fundraiser display name    -> owned by Fundraiser Profile (Fundraising & Attribution)
partner public profile            -> owned by Partner Profile (Partner)
donor display name / anonymity    -> owned by the future Donation/Donor-facing module, governed
                                      by Q6 (Human Decision Register: "Public Anonymity Only")
beneficiary public identity       -> owned by Beneficiary & Distribution; beneficiary data is
                                      HIGHLY_SENSITIVE per MASTER-REQUIREMENTS.md §10
campaign public author            -> owned by Campaign & Program / Content Experience
```

An email address in particular MUST NOT be exposed as any actor's public identity/handle.

---

## Email as Canonical Login Identifier (Q21)

```text
Email is the canonical login identifier for all actors in "Actors" above.
Authentication baseline is email + password (see "Credential Model").
Phone MAY exist as contact and/or verification data but is NOT a baseline login identifier.
Username is NOT a baseline login identifier.
Multi-identifier login (choosing email OR phone OR username at login time) is NOT part of the
  IMP-002 baseline.
Adding a second login identifier in the future requires change control (an ACR/ADR) where it
  would alter this locked identity contract — it is not an IMP-002 implementation detail.
```

### Email Normalization

```text
Leading/trailing whitespace is removed before storage and comparison.
The local part and domain are compared using a single, consistent canonical representation
  (e.g. lowercase) for uniqueness — the exact case-folding rule is an implementation detail as
  long as it is applied consistently at both write and comparison time.
The unique constraint is enforced on the canonical stored representation, at the database level
  (see "Database Design Requirements"), not only in application validation.
```

Explicitly NOT specified or authorized: Gmail-style dot-removal, plus-alias stripping, or any
other provider-specific mailbox rewriting. Email identity semantics remain provider-neutral —
two addresses that differ only by such provider-specific conventions are treated as distinct
identifiers unless a future Human Decision says otherwise.

---

## Credential Model

Password hashing MUST use Laravel's framework-standard hashing (bcrypt/argon2 via the `Hash`
facade) — per [CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md) "Dependency Governance": "Use
framework capabilities before building custom equivalents for ... hashing." No custom
cryptography (AGENTS.md "Never").

```text
baseline:                 email + password (Q21)
password hashing:        framework Hash facade only; never plaintext, never reversible
password verification:    via the framework hasher (`Hash::check()` equivalent); the framework
                          hasher rehashes automatically when its configured algorithm/cost policy
                          changes (Laravel's "needs rehash" capability) — use this framework
                          behavior rather than a custom rehash mechanism
password update:         requires current-password confirmation OR an authenticated reset flow;
                          invalidates other active sessions where "Session Model" requires it
password reset:           see "Password Reset" below — token-based, single-use, expiring
password confirmation:    supported as one mechanism to step up from STANDARD to ELEVATED
                          assurance for a bounded window (see "Authentication Assurance")
credential invalidation:  changing a password invalidates existing password-reset tokens for that
                          identity
password history:         NOT required — no materialized document specifies password-history
                          reuse prevention; do not invent it
```

Never store: plaintext password, reversibly-encrypted password, or a plaintext password-reset
token (the reset token is stored hashed — see "Password Reset").

---

## Registration Flows (Q22 — Hybrid Registration by Actor)

Per-actor eligibility is now locked (see "Actors"). Every registration/provisioning/invitation
path, regardless of actor, MUST:

```text
validate the email format and uniqueness
hash the password immediately (never hold plaintext beyond the request lifecycle)
create the User record inside a single transaction (see "Transaction Boundaries")
NOT seed a default/known password (IMP-001's targeted remediation already established this
  as a hard requirement — see docs/audits/IMP-001-TARGETED-REMEDIATION-PASS-1.md)
NOT grant any role, permission, scope, or business authority (IMP-003 concern)
emit an audit event (see "Audit Events")
```

### Donor Self-Registration

```text
submit email + set password
create Identity (User) record
begin email verification lifecycle (see "Verification")
establish an authenticated session per "Session Model" / "Login"
```

Does NOT create any donor-domain record beyond the Identity itself — donor-specific business data
belongs to the future Donation-facing module.

### Fundraiser Self-Registration

```text
create Identity (User) record (same mechanism as Donor)
authenticate the identity
grant NO automatic Fundraiser business authority, attribution eligibility, or Commission
  entitlement
```

Whether a neutral, non-authoritative "pending Fundraiser application" record is created alongside
the Identity is a Fundraising & Attribution (MODULE-OWNERSHIP.md §5) concern for that module's own
stage — IMP-002 does not implement Fundraiser domain logic, and does not decide whether such a
record exists.

### Partner Representative

```text
NO public self-registration baseline (Q22)
identity is created via invitation (see "Invitation Boundary") or as a side effect of a
  domain-driven Partner-module workflow (e.g. Partner Verification) — the domain workflow itself
  is out of scope; only the resulting Identity creation touches IMP-002
```

### Internal Administrative Identity

```text
invited or provisioned only — no public self-registration (Q22)
```

### Super Admin

```text
provisioned only — no public self-registration (Q22)
provisioning is itself a privileged administrative action; IMP-002 does not define who is
  authorized to provision a Super Admin identity (an Authority/Approval concern — IMP-003+), only
  that the resulting Identity follows the same credential/security rules as any other User
```

### Beneficiary

Deferred — see "Actors" and "Deferred Items." No registration flow is specified for this actor by
IMP-002.

---

## Invitation Boundary

Required for Partner Representative and Internal Administrative Identity (and usable, if needed,
for Super Admin provisioning). Neutral Identity/Auth-level requirements only:

```text
invitation token:          opaque, cryptographically random
intended-identity binding: the invitation is bound to a specific target email — acceptance MUST
                           use that same (or an explicitly re-confirmed) email, not an arbitrary
                           one, to prevent an invitation being redeemed by an unintended recipient
expiration:                 configurable, framework-standard token-expiry mechanics (no fixed
                           numeric value mandated by any materialized document)
single use:                 an invitation is consumed on successful acceptance; reuse fails safely
generic safe errors:        an invalid/expired/already-used invitation returns a generic error
                           that does not confirm whether a different, valid invitation exists for
                           that email
credential establishment:   acceptance requires the invitee to set a password, hashed per
                           "Credential Model," at which point a normal User record is created
audit event:                invitation issued, invitation accepted, invitation rejected/expired
                           (see "Audit Events")
```

An accepted invitation establishes an Identity/Auth subject ONLY. It must NOT silently establish
any future business authority, Partner relationship ownership, or administrative role — those are
granted, if at all, by an explicit later IMP-003+/domain workflow, not as a side effect of
invitation acceptance.

---

## Authentication Flows

### Login

```text
credential submission:    email + password
credential validation:    constant-time-safe password verification via the framework hasher;
                          generic failure message regardless of whether the email existed (see
                          "Privacy")
session regeneration:     session ID MUST be regenerated on successful authentication (session
                          fixation protection)
rate limiting/throttling: required on the login endpoint (see "Rate Limiting / Abuse Control")
suspended/disabled/locked account: authentication MUST fail with a neutral security-restriction
                          response — see "Security Restrictions" and "Privacy"
MFA challenge:             if the identity has MFA enabled (see "MFA"), a successful password
                          check is followed by a TOTP challenge before the session is considered
                          fully authenticated; failing the TOTP challenge does not reveal whether
                          the password step succeeded beyond what the flow already implies
security audit event:     both success and failure are audited (see "Audit Events")
```

No business-role redirect logic belongs here. Post-login routing may go to a single neutral
authenticated landing route; portal-specific routing (donor/fundraiser/partner/admin dashboards)
is a later-stage concern once IMP-003 can express "what dashboard may this principal see."

### Logout

```text
invalidates the current session (session data is destroyed, not merely marked)
session ID is regenerated/rotated after logout (prevents reuse of the old session identifier)
"logout other sessions" is a reasonable feature given IMP-001's database-backed sessions table
  already supports per-user session lookup, but is NOT required by any materialized document —
  implementation-time discretion, not a Human Decision
```

---

## Session Model

IMP-001 already provides a database-backed `sessions` table (see
[docs/implementation/IMP-001-core-project-foundation.md](IMP-001-core-project-foundation.md) and
[docs/audits/IMP-001-IMPLEMENTATION-PASS-1.md](../audits/IMP-001-IMPLEMENTATION-PASS-1.md)). IMP-002
MUST reuse it, not replace it. Its `user_id` column is nullable and carries no foreign-key
constraint (verified against the migration itself), so it can reference the future internal
`User.id` once `User` exists without requiring any change to the existing IMP-001 migration — no
schema change is anticipated for `sessions`.

```text
session fixation protection:  regenerate session ID on login and on privilege-relevant transitions
                               (e.g. password change, successful MFA challenge)
session rotation:              periodic ID rotation is a Laravel framework capability
                               (`session.php` config); use framework defaults
logout:                        see "Authentication Flows > Logout" above
expiration:                    framework-standard SESSION_LIFETIME (already configured in
                               .env.example per IMP-001)
CSRF protection:                see "CSRF / Cookie Security"
cookie expectations:            see "CSRF / Cookie Security"
remember-me:                   Laravel's framework "remember token" capability is available;
                               whether to enable it is an implementation-time UX decision — if
                               enabled, the remember token MUST be hashed at rest
```

---

## Password Reset

Bound to the canonical email identity (Q21):

```text
request:                       accepts an email address; ALWAYS returns a generic "if an account
                               exists, instructions were sent" response — never confirms/denies
                               account existence (see "Privacy")
token generation:               cryptographically random token
token storage:                   stored HASHED, not plaintext — Laravel's framework
                                 `PasswordBroker` hashes reset tokens by default; use that
                                 framework capability rather than a custom table/mechanism, and
                                 verify (at implementation time) that the hashing behavior is
                                 actually in effect rather than assuming it
expiration:                      framework-standard broker expiry (config value, not invented here)
single use:                      token is invalidated immediately upon successful use
password replacement:            new password is hashed per "Credential Model"; the identity's
                                  other active sessions ARE invalidated (a password reset is a
                                  strong signal the prior session state may be compromised)
audit:                           reset requested, reset completed, and reset-token-invalid/expired
                                  attempts are all audited
```

---

## Verification

Distinguish states that MUST NOT be conflated:

```text
identity exists:          a User record was created (registration/invitation/provisioning)
email verified:            the canonical login email has been confirmed reachable/owned by the
                          principal (verification link/code lifecycle)
authenticated:             a login (email + password, plus TOTP if MFA-enabled) succeeded for
                          this request/session
business authorized:       an Authority Assignment exists (docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md)
                          — entirely an IMP-003+ concern, never established by IMP-002
```

Since email is the canonical login identifier (Q21), email verification is part of the IMP-002
baseline for every actor. No materialized document states whether login itself is blocked until
email is verified, or only certain elevated/sensitive actions are; in the absence of an explicit
rule, this specification takes the more conservative, security-architecture-consistent default:
**login succeeds on valid credentials regardless of email-verification state, but
`pending_verification` is tracked as a distinct, visible security-restriction-adjacent status**
(see "Security Restrictions"), and any future ELEVATED-assurance-gated action may additionally
require a verified email — that mapping belongs to whichever later stage defines such an action,
not to IMP-002. This is a specification-time default, not a Human Decision, since it introduces no
business-authorization consequence.

Verification lifecycle: send verification (email, on registration/invitation acceptance/email
change), confirm via a signed/token link or code, re-send with rate limiting, mark verified, audit.

---

## Contact Phone

If collected at all, phone is contact data only:

```text
phone != login identifier (Q21)
phone verification, if implemented, exists only for contact/notification purposes, not as a
  prerequisite for baseline login
SMS OTP is explicitly NOT the MFA baseline (Q23) and is not introduced here for any purpose
```

Whether phone is collected at all, and whether phone verification is required for any purpose, is
not decided by any materialized document; IMP-002 does not require it, and implementation may omit
the phone column entirely if no consuming requirement exists yet.

---

## Authentication Assurance

Per [docs/05-rbac/AUTHENTICATION-ASSURANCE.md](../05-rbac/AUTHENTICATION-ASSURANCE.md), IMP-002
establishes the neutral STANDARD/ELEVATED state that IMP-003+ later consumes as the "Required
Authentication Assurance" AND-term of the canonical authorization formula
([docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md)). IMP-002 does NOT decide
which specific business operations require ELEVATED.

```text
STANDARD:   the ordinary state after a normal email+password login (with a successful TOTP
            challenge already folded in in general request, if MFA is enabled for that identity —
            see "MFA").
ELEVATED:   achieved via a fresh/recent re-authentication (password confirmation within a short
            window) OR a fresh, successful TOTP challenge performed specifically to step up
            assurance (not merely the one performed at login time, if a stronger, more recent
            proof is required by a later stage's policy).
```

Explicitly reaffirmed: a valid password/session makes a principal *authenticated*. A completed
MFA/recent-auth mechanism may additionally satisfy *ELEVATED assurance*. Neither authentication
nor ELEVATED assurance itself grants any permission — the authorization layer (IMP-003+) consumes
these states; IMP-002 does not attach financial or business rights to either state.

---

## MFA (Q23 — Configurable, TOTP Baseline)

```text
MFA capability belongs to IMP-002's authentication foundation (not deferred to a later stage).
MFA is NOT universally mandatory for every identity — enablement is per-identity/configurable.
TOTP (RFC 6238) is the baseline supported mechanism.
A successful TOTP challenge may satisfy ELEVATED Authentication Assurance (see above).
MFA success/enrollment grants NO role, permission, data scope, business authority, financial
  authority, or approval authority — identical boundary to registration/invitation.
SMS OTP is explicitly NOT the baseline mechanism (Q23).
```

### Enrollment

```text
principal must already be authenticated (STANDARD assurance at minimum) to begin enrollment
a TOTP secret is generated and presented (e.g. via QR code) for the principal to add to an
  authenticator app
enrollment completes only after the principal proves possession by submitting one valid TOTP code
  generated from the new secret
completion is transactional (see "Transaction Boundaries") and audited
```

### Verification (Challenge)

```text
required as a second factor after a successful password check, for any identity with MFA enabled
a narrow, rate-limited time window is allowed per RFC 6238 clock-drift tolerance — exact tolerance
  is an implementation-time configuration value
repeated failed challenges are rate-limited/lockable the same way login failures are (see "Rate
  Limiting / Abuse Control")
```

### Recovery

```text
a set of single-use recovery codes is generated at enrollment time (secure regeneration
  available on demand, which invalidates all prior recovery codes)
using a recovery code completes authentication in place of a TOTP challenge, exactly once per code
recovery-code regeneration and MFA reset/disable are both audited events
```

### Reset / Disable

```text
disabling MFA, or resetting it (e.g. after device loss), is a sensitive operation — it SHOULD
  itself require ELEVATED assurance (e.g. a fresh password confirmation) to perform
whether an administrative party (rather than the identity itself) may force-reset another
  identity's MFA is an authority/approval question — if such an administrative reset capability
  is needed, the authority to invoke it is an IMP-003+ (Business Authority) concern; IMP-002 only
  defines the identity-initiated self-service path plus the boundary that any administrative path
  requires an Authority Assignment it does not itself grant
every reset/disable is audited
```

### MFA Secret Storage

```text
TOTP secrets are never stored in plaintext logs and never exposed through any public API/response
  (including to the identity's own authenticated session, after initial enrollment display)
secrets are encrypted/protected at rest using Laravel's framework encryption capability (the
  `Crypt` facade / encrypted Eloquent cast) — no custom cryptographic scheme (AGENTS.md "Never":
  "No custom cryptography")
access to the secret (for verification) is limited to the authentication domain's own
  verification logic — no other module reads it
recovery-code material is stored hashed (each code, like a password, is checked via the framework
  hasher), not stored in plaintext or reversibly
```

### Dependency Note

Laravel's framework core does not ship a built-in RFC 6238 TOTP implementation. Implementing TOTP
therefore requires either (a) a well-maintained, narrowly-scoped third-party package implementing
RFC 6238 exactly, or (b) a minimal direct implementation using PHP's built-in `hash_hmac` (already
available via the `ext-hash` extension Laravel already requires) that implements the published
RFC 6238 algorithm precisely, not an invented variant — hand-rolling a *novel* one-time-password
scheme would violate AGENTS.md's "No custom cryptography," but implementing a published, standard
algorithm via a well-reviewed library or a faithful direct implementation is the accepted pattern
for TOTP in the Laravel ecosystem. Which of (a)/(b) is used, and if (a), which specific package, is
an implementation-time Dependency Governance decision (per
[CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)) — not decided by this specification.

---

## Security Restrictions (Account Status)

Neutral, authentication/security-relevant states only — explicitly NOT business-approval states:

```text
active                normal authenticatable state
pending_verification  email not yet verified (see "Verification")
suspended              temporarily blocked from authenticating (e.g. abuse/fraud signal); reason
                       not exposed in the public error response
disabled               permanently deactivated by an administrative action (out-of-scope trigger
                       for IMP-002; only the effect on authentication is defined here)
locked                 temporary lockout from repeated failed login/MFA attempts (see "Rate
                       Limiting")
```

This state set remains a specification-time proposal built from generic, universally-applicable
authentication concepts (not repository-invented business states); the readiness reviewer may
confirm or adjust it. It is explicitly kept separate from business-approval states: **"Fundraiser
approval" and "Partner approval" (or verification decisions) are Business Authority concepts
(docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md) and MUST NOT become a `User.status` value.** A
Fundraiser or Partner Representative whose business approval is pending is still, from IMP-002's
perspective, simply `active` (or `pending_verification`) — their lack of business authority is
enforced entirely by IMP-003+, not by an identity-level status.

---

## Principal Exposure

```text
web requests:  the framework's authenticated-user resolution (`Auth::user()` / request-bound
               principal) exposes the `User` plus its Authentication Assurance state.
API requests:  same principal concept; exact API authentication mechanism is undecided (see "API
               Authentication Boundary") but whatever mechanism is chosen must resolve to the same
               `User` + assurance state shape, not a parallel identity concept.
jobs:          IMP-002 does not implement System Principal job plumbing — assigning "System
               Principal"/"Integration Principal" (docs/04-security/SECURITY-ARCHITECTURE.md) to a
               specific implementation stage is not decided by any materialized document. Flagged
               as a deferred item, not implemented here.
audit:         every audited authentication event (see "Audit Events") records the acting
               `User` (or "system"/"anonymous" where applicable) as its principal.
future policies: IMP-003's canonical authorization formula consumes "Authenticated" (a boolean
               derived from principal presence) and "Required Authentication Assurance" (the
               STANDARD/ELEVATED state) directly from what IMP-002 exposes here.
```

Distinguish, only where the architecture already names them (no invention): Human Principal (a
`User` acting directly), System Principal, and Anonymous Principal (an unauthenticated request/
guest). Integration Principal is not addressed by IMP-002.

---

## API Authentication Boundary

Unchanged conclusion from the prior draft: no `/api/v1` business routes exist yet (IMP-001 §9), and
no materialized document decides whether future API authentication will use session/cookie auth,
personal access tokens, OAuth, or another mechanism. This remains a **deferred, non-blocking
item** for IMP-002, whose own scope is web (session-based) authentication; the underlying `User` +
credential + assurance model is transport-agnostic and does not need to be redesigned once an API
authentication mechanism is chosen.

The locked rule applies unchanged whenever that later decision is made: "API token authority
intersection (a token's effective authority is the intersection of the token's own grant and its
principal's authority, never a union)" (SECURITY-ARCHITECTURE.md). No token scope or OAuth grant
flow is invented or decided here.

---

## CSRF / Cookie Security

Standard Laravel web-guard defaults, not weakened:

```text
CSRF protection:    Laravel's VerifyCsrfToken middleware on all state-changing web routes
Secure cookie:       session cookie `secure` flag enabled in production (HTTPS-only)
HttpOnly:            session cookie HttpOnly (framework default)
SameSite:            framework default (`lax`) unless a specific flow requires otherwise
session regeneration: on login/logout/password change/MFA enrollment completion, per "Session
                     Model"
HTTPS production:    expected in production; shared-hosting deployment terminates TLS per normal
                     hosting practice
```

---

## Rate Limiting / Abuse Control

Required on each of the following flows, using Laravel's framework rate limiter (`RateLimiter`
facade / `throttle` middleware against the database/cache store IMP-001 already configured) — no
custom implementation, no mandatory Redis:

```text
login
donor self-registration
fundraiser self-registration
password reset request
verification resend
TOTP verification (challenge attempts)
invitation acceptance
```

Exact numeric limits are NOT fixed by any materialized document; configurable, secure defaults are
required at implementation time — configurable never means unrestricted.

---

## Audit Events

Emission points only; the audit *sink* (storage/query mechanism, owned by Governance & Platform
Services per MODULE-OWNERSHIP.md §16) is a later stage's responsibility — none currently exists.

```text
identity created
self-registration completed
invitation issued
invitation accepted
login succeeded
login failed
logout
password changed
password reset requested
password reset completed
email verified
MFA enrolled
MFA challenge succeeded
MFA challenge failed
MFA recovery code used
MFA reset / disabled
account locked / unlocked
account suspended / disabled / reactivated
security-sensitive profile change (e.g. login-email change, if supported)
```

---

## Privacy

Authentication responses must not allow account enumeration:

```text
login failure:            identical generic message whether the email does not exist or the
                           password (or MFA code) is wrong
password-reset request:    identical generic "if an account exists..." response regardless of
                           existence
registration (self-service, email must be unique): a "this email is already in use" message
                           during registration is an accepted, common trade-off, but is not paired
                           with further account detail
verification resend:        generic response regardless of current verification state, where
                           practical
invitation edge cases:      an invalid/expired/already-used invitation returns a generic error
                           that does not confirm whether a different valid invitation exists for
                           that email
```

Do not expose: email/phone existence beyond the unavoidable registration-uniqueness trade-off
above, internal identifiers (expose ULID publicly, never the internal BIGINT, and not automatically
even then — see "Public ID"), or security-state detail.

---

## Retention Boundary

Unchanged from the prior draft. Per
[docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §10 and
[docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §16:

```text
Deleting/closing a User account MUST NOT cascade-delete or orphan any immutable financial/Ledger/
  audit record that references that identity.
Anonymization/pseudonymization of a closed identity (rather than hard deletion) is the likely
  mechanism, but the exact mechanism, retention period, and legal-hold interaction are NOT decided
  by any materialized document.
IMP-002 does not implement retention/anonymization logic — it documents this boundary only.
```

---

## Public ID

Following the locked database convention (BIGINT internal / ULID public,
DATABASE-ARCHITECTURE.md "Identity Conventions"):

```text
the internal BIGINT id is never exposed as a stable public identifier
public_id (ULID) is used for any external-safe reference (e.g. a future /api/v1 resource
  identifier)
public_id is NOT automatically included in every authentication response by default — it is
  exposed only where a consuming context (e.g. a future API resource) actually needs a stable
  external reference; the authenticated web session itself has no need to expose it beyond what
  Inertia/Vue already receives as the authenticated user's minimal profile
```

---

## Database Design Requirements

Specification only — no migration is created by this document.

### `users` (or the eventual entity name)

```text
purpose:            canonical Identity + Authentication record (see "Identity Model")
ownership:           Identity & Organization (MODULE-OWNERSHIP.md §1)
key fields:          id (BIGINT unsigned PK), public_id (ULID), email, email_verified_at
                     (nullable timestamp), password (hashed), status (string/enum),
                     mfa_enabled (boolean), failed_login_attempts (int), locked_until (nullable
                     timestamp), last_login_at (nullable timestamp), remember_token (nullable,
                     hashed, only if "remember me" is enabled), phone (nullable, optional),
                     phone_verified_at (nullable, optional), created_at, updated_at
unique constraints:  email (on its canonical normalized representation), public_id
security-sensitive:  password, remember_token (both hashed at rest; never logged)
indexes:              email (unique), public_id (unique), status (for administrative queries)
retention concerns:   no default soft-delete; closure/anonymization mechanism deferred (see
                     "Retention Boundary")
```

### Password reset support

```text
purpose:            single-use, time-limited password-reset token storage
ownership:           Identity & Organization, using Laravel's framework password-broker
                     capability
key fields:          email, token (hashed), created_at
unique constraints:  framework-standard (typically one active token per email, superseded by a
                     newer request)
security-sensitive:  token (hashed, never plaintext)
indexes:              email
retention concerns:   expired/consumed tokens may be pruned; no financial/audit-history
                     implication
```

### MFA secret / recovery storage

```text
purpose:            TOTP secret and recovery-code storage, separate from the primary identity
                     row to limit the blast radius of any read access to the users table
ownership:           Identity & Organization (authentication domain only — see "MFA Secret
                     Storage")
key fields:          user_id (FK to users.id), secret (encrypted at rest), recovery_codes
                     (each stored hashed, individually markable as used), created_at, updated_at
unique constraints:  one active secret per user_id (baseline; multiple-device/backup-secret
                     support is not specified here)
security-sensitive:  secret (encrypted), recovery_codes (hashed) — the most sensitive columns in
                     the entire IMP-002 schema
indexes:              user_id
retention concerns:   removed on MFA disable/reset; no financial/audit-history implication
```

### Invitation storage

```text
purpose:            invitation lifecycle for Partner Representative / Internal Administrative
                     Identity / Super Admin provisioning (see "Invitation Boundary")
ownership:           Identity & Organization for the generic invitation mechanism; the *decision*
                     to issue a Partner invitation still belongs to the Partner module once it
                     exists — IMP-002 only owns the token/acceptance mechanics
key fields:          token (hashed), intended_email, expires_at, accepted_at (nullable),
                     created_at
unique constraints:  token (hashed, effectively unique by construction)
security-sensitive:  token (hashed, never plaintext)
indexes:              intended_email, expires_at
retention concerns:   expired/consumed invitations may be pruned; no financial/audit-history
                     implication
```

### `sessions` (existing, IMP-001)

```text
ALREADY EXISTS. user_id becomes populated once User exists; no schema change anticipated. Any
future schema change to sessions must be a new migration, per BRANCHING-POLICY.md "Migration
Immutability" — never an edit to the IMP-001 migration.
```

### Cross-cutting

```text
Foreign keys:       any later module referencing User (Donor context, Fundraiser Profile, Partner
                    Profile, Beneficiary Profile, MFA/invitation storage above) does so via User's
                    internal BIGINT id, per DATABASE-ARCHITECTURE.md "Identity Conventions."
Case/normalization: email normalized (see "Email Normalization") before uniqueness comparison and
                    storage.
Money/DECIMAL:      not applicable — IMP-002 has no monetary columns.
```

---

## Transaction Boundaries

```text
account/identity creation (self-registration, invitation acceptance, or provisioning) —
  transactional
password reset completion (token consumption + password update + token invalidation + other
  session invalidation) — transactional
email verification completion (mark verified + invalidate verification token) — transactional
MFA enrollment completion (secret confirmation + recovery-code generation) — transactional
MFA reset/disable (secret/recovery removal + audit event) — transactional
invitation acceptance (token consumption + User creation + credential establishment) —
  transactional
security lock/unlock (state change + audit event) — transactional
```

---

## Concurrency / Idempotency

```text
duplicate registration:          unique index on email prevents a duplicate row; the application
                                  layer must handle the resulting constraint violation as a normal
                                  "identifier already in use" outcome, not a 500 error
simultaneous password reset:      a consumed/expired token used a second time MUST fail safely
                                  (covered by "single use" in "Password Reset")
verification token replay:        a consumed verification token/link MUST be rejected on reuse
multiple reset requests:          generating a new reset token may invalidate prior outstanding
                                  ones (reasonable default)
invitation replay:                a consumed/expired invitation token MUST be rejected on reuse,
                                  with a generic error (see "Privacy")
double MFA enrollment:            re-running enrollment while already enrolled either requires an
                                  explicit reset first, or transactionally replaces the prior
                                  secret and invalidates prior recovery codes — implementation may
                                  choose either as long as no window exists where two secrets are
                                  simultaneously valid
concurrent account updates:        standard optimistic handling (e.g. `updated_at` check) is
                                  sufficient; the Financial Idempotency invariant in
                                  docs/03-database/DATABASE-INVARIANTS.md does not apply to
                                  non-financial Identity records
```

---

## Error Model

```text
validation error:              structured field-level errors (framework standard), safe to
                                display
authentication failure:         generic message (see "Privacy") — no field-level distinction
                                between "unknown email," "wrong password," and "wrong MFA code"
account security restriction:   generic, non-account-confirming message (see "Privacy")
rate limit:                     standard HTTP 429-equivalent with a safe retry-after hint
expired token:                  generic "this link/code has expired" (applies to verification,
                                password-reset, and invitation tokens alike)
invalid token:                  generic "this link/code is invalid"
verification required:          a distinct, safe message shown only to an already-authenticated
                                principal, since it inherently confirms account existence to the
                                one party who already proved control of the credential
```

---

## Route Ownership

Categories only — no route is implemented by this document:

```text
guest authentication:        login, donor/fundraiser self-registration
invitation acceptance:        accept-invitation (credential establishment for Partner
                              Representative / Internal Administrative Identity / Super Admin)
authenticated account security: password change, account security state view, MFA
                              enrollment/verification/reset management, logout, "logout other
                              sessions"
verification:                 verify-email confirmation endpoint, resend
password recovery:            forgot-password request, reset-password completion
```

All routes remain same-origin under the single root domain, per MASTER-ARCHITECTURE.md's locked
"Single root domain" principle — no separate auth subdomain. The exact routing shape (portal-scoped
vs. a shared `/auth/*` path) remains an implementation-time decision.

---

## UI Boundary

May implement, using the existing Inertia/Vue/Tailwind foundation from IMP-001:

```text
login UI
donor registration UI
fundraiser registration UI
invitation acceptance UI (Partner Representative / Internal Administrative Identity / Super Admin)
forgot password UI
reset password UI
email verification UI
account security UI (password change, session list if "logout other sessions" is built)
MFA enrollment UI (QR/secret display, confirmation)
MFA challenge UI (login-time TOTP entry, recovery-code entry)
```

Must NOT implement: donor dashboard, fundraiser dashboard, partner dashboard, or admin business
dashboard. A single neutral authenticated landing page is acceptable if needed to prove the
authenticated flow renders end-to-end — it must not become a business dashboard.

---

## Testing Requirements

At minimum, before IMP-002 implementation may be considered complete:

```text
donor self-registration succeeds and creates an Identity only
fundraiser self-registration succeeds and grants no business authority
partner representative / internal administrative identity / super admin CANNOT self-register via
  the public registration endpoint
invitation issuance, acceptance (correct email), rejection (wrong email), expiry, and replay
credential hashing (password and MFA secret/recovery codes never stored/logged in plaintext)
login success (with and without MFA enabled)
login failure (wrong password) -> generic message, no enumeration
login failure (unknown email) -> identical generic message
MFA challenge success/failure; recovery-code single use
session fixation protection (session ID changes on login)
logout (session invalidated; ID rotated)
account restriction (suspended/disabled/locked) -> authentication denied, generic message
password reset: request -> generic response regardless of existence
password reset: token single-use (second use fails); expired token fails
password reset: other active sessions invalidated after completion
email verification: valid token verifies; expired/invalid token fails; replay fails
rate limiting: login, donor/fundraiser registration, password-reset request, verification resend,
  TOTP verification, invitation acceptance — all throttled
account enumeration resistance: timing/response-shape parity across "unknown email"/"wrong
  password"/"wrong MFA code," and across "account exists"/"account does not exist" for
  password-reset request and invitation acceptance
CSRF: state-changing auth routes reject requests without a valid CSRF token
database constraints: duplicate email rejected at the database level, not only in application
  validation
audit events: each event in "Audit Events" is actually emitted at its corresponding action
guest/authenticated middleware behavior: guest-only routes reject an authenticated principal
  appropriately; authenticated-only routes reject a guest
```

Explicitly required negative coverage: no test may assert or rely on a role, permission, scope, or
business-authority check — IMP-002 has none, and its test suite must demonstrate the absence of
authorization leakage into authentication (e.g. a Fundraiser's self-registered account is denied
any Fundraiser-authority action, because no such authority exists yet at all — not because of a
role check).

---

## Security Testing

```text
plaintext credential prevention:  password/MFA-secret/recovery-code columns never contain a
                                   plaintext value in test assertions or database inspection
credential leakage:                password/token/MFA-secret values never appear in logs, error
                                   responses, or serialized API/Inertia payloads
token leakage:                     password-reset, verification, and invitation tokens never
                                   appear in logs or are exposed via a non-owning request
MFA secret protection:             the TOTP secret is never re-displayed after initial enrollment
                                   confirmation, and never returned by any endpoint other than the
                                   enrollment flow itself
session fixation:                  covered above
CSRF:                               covered above
brute-force controls:              repeated failed logins/MFA challenges trigger rate
                                   limiting/lockout
expired token reuse:                covered above
credential reset invalidation:      other active sessions are invalidated after a password reset
security-restricted account authentication: covered above
```

Penetration testing is NOT claimed or required at this stage.

---

## Shared Hosting Compatibility

Unchanged from the prior draft, reaffirmed for MFA specifically:

```text
Apache/LiteSpeed        unchanged
PHP                      unchanged (8.3 baseline)
MySQL                    unchanged
database sessions        unchanged (reused, not replaced)
database queue           unchanged
cron                     unchanged
Laravel Storage          unchanged
compiled frontend assets  unchanged
```

MFA (TOTP) requires NO mandatory SMS provider (SMS is explicitly not the baseline, Q23), no
mandatory external MFA SaaS, no mandatory Redis, and no mandatory WebSocket — TOTP verification is
a stateless, local HMAC computation against a stored secret, not an external service call. Email
delivery for verification/password-reset/invitation MUST use Laravel's standard mail
configuration/transport, with no mandatory dependency on a specific transport service.

---

## Dependency Policy

No new Composer/npm package is proposed for the non-MFA baseline (`Auth`, `Hash`, session,
password broker, `RateLimiter`, `Crypt`, Blade/Inertia mail views are all framework capabilities).
For TOTP specifically, see "MFA > Dependency Note" above — a narrowly-scoped addition may be
warranted, but its selection is deferred to implementation-time Dependency Governance review, not
decided here.

---

## Human Decisions Required

```text
NONE.
```

Q21 (login identifier), Q22 (registration model), and Q23 (MFA policy) — the three items the prior
draft correctly flagged as blocking — are now FINAL/LOCKED and fully incorporated above. The
Actor Catalog artifact gap is reclassified as a non-blocking documentation/materialization gap
(see "Actor Catalog Artifact Gap"), not a Human Decision. The one remaining open item (Beneficiary
registration/authentication model) is recorded as non-blocking and deferred (see "Actors" and
"Deferred Items") because it does not affect the `User` entity design or any baseline flow already
specified — it only adds a new actor row to the eligibility table when decided.

---

## Definition of Ready

```text
[x] Authoritative sources available and read
[x] Identity model unambiguous               -- PASS (User entity; email as sole login column;
                                                see "Identity Model")
[x] Login identifier unambiguous              -- PASS (Q21 — email)
[x] Registration policy unambiguous            -- PASS (Q22 — hybrid by actor; Beneficiary
                                                deferred, non-blocking)
[x] Password/reset model unambiguous          -- PASS (framework password-broker direction)
[x] Verification rules unambiguous            -- PASS (email verification lifecycle specified;
                                                pre-verification login behavior given an explicit,
                                                reasoned default — see "Verification")
[x] MFA requirement unambiguous                -- PASS (Q23 — configurable, TOTP baseline)
[x] Authentication assurance model mapped     -- PASS (STANDARD/ELEVATED, TOTP as one ELEVATED
                                                path)
[x] Security restrictions mapped               -- PASS (neutral state set; explicitly separated
                                                from business-approval states)
[x] Database requirements defined              -- PASS (users, password-reset, MFA, invitation
                                                tables all specified at requirement level)
[x] Stage boundaries clear                     -- PASS
[x] Tests defined                              -- PASS
[x] Shared-hosting compatibility preserved     -- PASS
[x] No architecture conflicts                  -- PASS
[x] No unresolved implementation blocker        -- PASS
```

**Definition of Ready: PASS.** All three Human Decisions this specification depended on are
FINAL/LOCKED and incorporated; the Actor Catalog concern was resolved by reconciliation, not by a
new decision; the single remaining open item (Beneficiary) is non-blocking and deferred without
affecting anything already specified.

---

## Definition of Done

Restated for forward reference (per
[docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)), to apply once
implementation is separately authorized:

```text
identity schema implemented (users, password-reset, MFA, invitation tables)
secure email + password authentication implemented
approved registration models implemented per actor (Donor/Fundraiser self-registration;
  Partner Representative/Internal Administrative Identity/Super Admin invitation/provisioning
  only)
email verification implemented as specified
password reset implemented securely
TOTP MFA capability implemented (enrollment, verification, recovery, reset)
assurance state (STANDARD/ELEVATED) exposed
session security verified (fixation, rotation, logout)
rate limiting verified across all listed flows
enumeration resistance tested
audit events emitted
RBAC/business authorization absent (no roles/permissions/scopes/authority tables or logic)
shared-hosting compatibility preserved
tests passing (functional + security, per "Testing Requirements" / "Security Testing")
independent review passing
```

Not required at this stage: Beneficiary registration/authentication, `/api/v1` authentication
mechanism, Audit domain storage, retention/anonymization mechanism, System/Integration Principal
job plumbing — all recorded under "Deferred Items."

---

## Risks

```text
Retrofitting a second login identifier later (per Q21's own change-control note) would require an
  ACR/ADR and a database/validation rework — expected to be rare given Q21 is locked, not
  contingent.
The TOTP dependency choice (package vs. minimal direct implementation) should be resolved early in
  implementation to avoid rework of the MFA secret-storage schema.
An eventual Beneficiary registration decision may require adding a new actor row and a
  registration flow, but should not require changing the User entity itself, given the module
  boundaries already established.
```

---

## Deferred Items (Non-Blocking)

```text
Beneficiary registration/authentication model (actor named in MASTER-REQUIREMENTS.md §6; not
  addressed by Q22)
API authentication mechanism (session vs. token vs. OAuth) for future /api/v1
System Principal / Integration Principal implementation ownership
Audit domain storage/query mechanism (event emission points are specified; the sink is not built)
Retention/anonymization mechanism for closed identities
Exact route paths for authentication (categories specified; paths not fixed)
Administrative MFA reset authority (requires an IMP-003+ Authority Assignment; the self-service
  path is specified now)
Actor Catalog artifact under docs/06-domains/identity/ (documentation gap, not blocking)
```

---

## Implementation Gate

```text
IMP-002 Specification:        READY FOR READINESS REVIEW
IMP-002 Definition of Ready:   PASS
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
```

STOP.

Do not implement IMP-002. Do not create migrations, models, controllers, middleware, routes, UI,
factories, seeders, or tests for this stage. Do not install any authentication/TOTP package.
Implementation authorization requires an independent Codex readiness review and, if that passes,
explicit Human/stage authorization, per
[docs/00-governance/IMPLEMENTATION-GOVERNANCE.md](../00-governance/IMPLEMENTATION-GOVERNANCE.md).
