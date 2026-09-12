# IMP-002 — IDENTITY + AUTHENTICATION

## Document Control

```text
Task ID: IMP-002
Stage: IMPLEMENTATION 02
Title: Identity + Authentication
Document Type: Implementation Specification
Status: DRAFT — HUMAN DECISION REQUIRED BEFORE READINESS REVIEW
Implementation Authorization: NOT AUTHORIZED
Coding Authorization: NO
Predecessor: IMP-001 FINAL / LOCKED
Working Branch: impl/002-identity-authentication (not merged to master)
```

This specification establishes platform Identity and Authentication foundations only. It does
NOT implement the broader authorization system (roles, permissions, data scope, business
authority) assigned to IMP-003, and it does NOT implement any business/financial domain.

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

**Documentation gap found:** no Actor Catalog exists anywhere in this repository.
`docs/06-domains/identity/` contains only a `.gitkeep` placeholder. Every actor name used in this
specification (Donor, Fundraiser, Partner User, Beneficiary, Operational Staff, Admin/Super Admin)
is one already named in a materialized Level 1-3 document (cited inline below) — none is invented
for this specification. But no materialized document defines, per actor, whether that actor
self-registers, is invited, or is provisioned, nor which login identifier it uses, nor whether/how
MFA applies to it. Those gaps are recorded as Human Decisions Required in the relevant sections
and summarized in "Human Decisions Required" below, per this task's explicit instruction not to
infer them.

---

## Purpose

Establish:

```text
1. Who is an authenticated principal (Identity).
2. How a principal proves control of that identity (Authentication).
3. How authenticated state is maintained (Session).
4. How credentials are securely managed.
5. How authentication-sensitive lifecycle events are handled (registration, verification,
   password reset, MFA where decided, account security state).
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
Authentication (password-based login, logout)
Session use of IMP-001's existing sessions table
Password credential handling (hash, update, reset)
Contact verification lifecycle (email and/or phone, per Human Decision below)
Authentication Assurance state exposure (STANDARD / ELEVATED) for IMP-003 to consume
Account/security lifecycle states needed for authentication (active/suspended/disabled/locked)
Principal exposure to web requests, API requests, audit
Rate limiting / abuse control on authentication endpoints
Authentication-relevant audit events
Privacy-safe error responses (no account enumeration)
Minimal authentication UI (login, registration where decided, forgot/reset password,
  verification, account security)
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
Partner scope authorization
Fundraiser business rules (Fundraiser Profile itself — owned by Fundraising & Attribution,
  see docs/02-architecture/MODULE-OWNERSHIP.md §5)
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
profile record (created in that module's later stage) references this identity by foreign key. This
is not a new decision — it follows directly from the already-materialized module boundaries.

---

## Actors

No Actor Catalog is materialized in this repository (see "Authority Sources" above). The table
below uses only actor names that already appear in materialized Level 1-3 documents, and marks as
undecided anything not stated there. This is a survey of what can and cannot yet be specified — it
does not authorize any actor-specific implementation.

| Actor | Named in | Can authenticate? | Login identifier | Self-registration eligibility | Portal/context |
|---|---|---|---|---|---|
| Donor | DATA-SCOPE-MODEL.md ("Donor -> own Donation records") | Implied YES (scope ownership presupposes an authenticated identity) | **UNDECIDED** | **UNDECIDED** | `/donor/*` (MASTER-REQUIREMENTS.md §3) |
| Fundraiser | DATA-SCOPE-MODEL.md; MODULE-OWNERSHIP.md §5 | Implied YES | **UNDECIDED** | **UNDECIDED** | `/fundraiser/*` |
| Partner User | DATA-SCOPE-MODEL.md ("Partner User -> linked Partner resources only"); MODULE-OWNERSHIP.md §7 | Implied YES | **UNDECIDED** | **UNDECIDED** — likely invited/provisioned given "Partner Verification" exists as a concept, but not stated | `/partner/*` |
| Beneficiary | DATA-SCOPE-MODEL.md ("Beneficiary -> own application/profile where permitted") | Implied YES | **UNDECIDED** | **UNDECIDED** | Not routed yet — no `/beneficiary/*` prefix appears in MASTER-REQUIREMENTS.md §3 |
| Operational Staff | DATA-SCOPE-MODEL.md ("Operational Staff -> assigned work / authorized organizational scope") | Implied YES | **UNDECIDED** | Provisioned by administration (reasonable default for internal staff; not explicitly stated) | `/admin/*` (assumed; not explicitly separated from Admin/Super Admin routing) |
| Admin / Super Admin | RBAC-ARCHITECTURE.md ("Super Admin != automatic Financial Authority") | Implied YES | **UNDECIDED** | Provisioned by administration (reasonable default; not explicitly stated) | `/admin/*` |

"Implied YES" is derived from [docs/05-rbac/DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md)
"Ownership Examples," which presupposes each actor is an authenticated principal that owns/holds a
scope — it is not itself a statement that these are the only authenticatable actors, nor that no
other actor category exists. See "Human Decisions Required."

Do NOT create role authorization rules, business authority, or per-actor permission logic from
this table — it is an authentication-eligibility survey only.

---

## Identity Model

### Canonical Identity Entity

Per [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §1
("Identity & Organization" owns "User identity"), the canonical credential-bearing entity is named
**User** in this specification, consistent with that document's own language (also used in
"Partner User relationship," §7). This is a naming choice for the shared identity record, not an
architecture decision — a future ADR could rename it (e.g. to `Identity` or `Account`) without
changing the underlying model.

`User` (or the eventual name) owns exactly:

```text
entity purpose:       authentication + shared identity, nothing business-domain-specific
primary identifier:   BIGINT unsigned internal key (docs/03-database/DATABASE-ARCHITECTURE.md
                       "Identity Conventions")
public identifier:    ULID (same source, "public/API-safe identifiers, where specified" — a
                       User is referenced by /api/v1 and by other modules' foreign keys, so a
                       ULID is warranted; the exact usage surface is for implementation, not this
                       spec, to finalize)
login identifier(s):  UNDECIDED — see "Email / Phone Identity" below
password credential:  hashed only (see "Credential Architecture")
status/lifecycle:     UNDECIDED exact state set — see "Account Status / Security Restriction";
                       neutral states only (no business-authorization state here)
verification state:   contact-verification flags — see "Email / Contact Verification"
security metadata:    failed-login counters, lock timestamps, last-login metadata (standard
                       Laravel-compatible fields; no invented mechanism)
timestamps:            created_at / updated_at (framework standard)
soft-delete/retention: NOT soft-deleted by default — see "Retention Boundary" (identity deletion
                       must never cascade-delete or orphan immutable financial/audit history;
                       exact retention mechanism is a later, Human-decided policy, not invented
                       here)
```

`User` does NOT own: Donor-specific fields, Fundraiser Profile, Partner Profile, Beneficiary
Profile/Application, Admin business preferences, roles, permissions, scopes, or business
authority. Each owning module (per MODULE-OWNERSHIP.md) references `User` by its internal ID when
that module is implemented in its own stage.

### Organization Membership

MODULE-OWNERSHIP.md §1 lists "Organization membership" and "Principal organizational context" as
owned by Identity & Organization, alongside "User identity." Given
[docs/02-architecture/MASTER-ARCHITECTURE.md](../02-architecture/MASTER-ARCHITECTURE.md)'s locked
"Single Organization" principle, there is exactly one platform organization — this specification
does NOT introduce multi-organization membership modeling. "Organization membership" here is
interpreted minimally as: every `User` is implicitly a member of the single platform organization;
no separate membership table is required by this stage. If a future stage needs Partner-level
sub-organization membership, that is a Partner-module concern (MODULE-OWNERSHIP.md §7), not an
Identity concern, and is out of scope here.

### Internal Identity vs. Public Representation

Per [AGENTS.md](../../AGENTS.md) "Locked Decisions" ("Public donor representation != internal
identity") and
[docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §10
("Internal donor identity != public donor representation... Fundraiser attribution does not grant
unrestricted donor identity"), the `User` record (internal identity) MUST NOT be conflated with any
public-facing representation:

```text
public fundraiser display name    -> owned by Fundraiser Profile (Fundraising & Attribution)
partner public profile            -> owned by Partner Profile (Partner)
donor display name / anonymity    -> owned by the future Donation/Donor-facing module, governed
                                      by Q6 (Human Decision Register: "Public Anonymity Only")
beneficiary public identity       -> owned by Beneficiary & Distribution; beneficiary data is
                                      HIGHLY_SENSITIVE per MASTER-REQUIREMENTS.md §10
campaign public author            -> owned by Campaign & Program / Content Experience
```

`User` exposes only what authentication and cross-cutting identity require (e.g. internal name for
account-recovery correspondence) — never a business-domain public projection.

---

## Credential Model

Password hashing MUST use Laravel's framework-standard hashing (bcrypt/argon2 via the `Hash`
facade) — per [CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md) "Dependency Governance": "Use
framework capabilities before building custom equivalents for ... hashing." No custom
cryptography (AGENTS.md "Never").

```text
password hashing:        framework Hash facade only; never plaintext, never reversible
password update:         requires current-password confirmation OR an authenticated reset flow;
                          invalidates other active sessions where the security architecture
                          requires it (see "Session Model")
password reset:           see "Password Reset" below — token-based, single-use, expiring
password confirmation:    supported as the mechanism to step up from STANDARD to ELEVATED
                          assurance for a bounded window (see "Authentication Assurance"); exact
                          window/timeout is an implementation-time configuration value, not a new
                          architecture decision
credential invalidation:  changing a password invalidates existing password-reset tokens for that
                          identity
password history:         NOT required — no materialized document specifies password-history
                          reuse prevention; do not invent it
```

Never store: plaintext password, reversibly-encrypted password, or a plaintext password-reset
token (the reset token is stored hashed — see "Password Reset").

---

## Authentication Flows

### Registration

Per-actor self-registration eligibility is **UNDECIDED** (see "Actors" above and "Human Decisions
Required"). This specification documents the mechanism categories that MAY exist, without
assigning them to specific actors:

```text
self-registration        (public sign-up form)
invitation-based          (an existing principal issues an invitation the recipient accepts)
administrative provisioning (an Admin/Operational Staff account creates the identity directly)
domain onboarding          (a business-domain flow, e.g. Partner Verification, creates the
                           identity as a side effect — the domain flow itself is out of scope;
                           only the resulting identity creation touches IMP-002)
```

Whichever mechanism(s) are decided per actor, registration (where self-service) MUST:

```text
validate the chosen login identifier's format and uniqueness
hash the password immediately (never hold plaintext beyond the request lifecycle)
create the User record inside a single transaction (see "Transaction Boundaries")
NOT seed a default/known password (IMP-001's targeted remediation already established this
  as a hard requirement — see docs/audits/IMP-001-TARGETED-REMEDIATION-PASS-1.md)
NOT grant any role, permission, scope, or business authority (IMP-003 concern)
emit an audit event (see "Audit Events")
```

### Login

```text
credential submission:    login identifier + password
credential validation:    constant-time-safe password verification via Hash::check() equivalent;
                          generic failure message regardless of whether the identifier existed
                          (see "Privacy")
session regeneration:     session ID MUST be regenerated on successful authentication (session
                          fixation protection)
rate limiting/throttling: required on the login endpoint (see "Rate Limiting / Abuse Control")
suspended/disabled/locked account: authentication MUST fail with a neutral security-restriction
                          response, not a generic "invalid credentials" response that would leak
                          the account's existence beyond what "invalid credentials" already risks
                          — exact wording is an implementation detail balancing "Privacy" (below)
                          against user support needs; do not over-disclose account state
security audit event:     both success and failure are audited (see "Audit Events")
```

No business-role redirect logic belongs here. Post-login routing may go to a single neutral
authenticated landing route; portal-specific routing (donor/fundraiser/partner/admin dashboards)
is a later-stage concern once IMP-003 can express "what dashboard may this principal see."

### Logout

```text
invalidates the current session (session data is destroyed, not merely marked)
session ID is regenerated/rotated after logout (prevents reuse of the old session identifier)
"logout other sessions" (i.e. invalidate all of a User's other active sessions) is a reasonable
  feature given IMP-001's database-backed sessions table already supports per-user session
  lookup, but is NOT required by any materialized document — implementation-time discretion,
  not a Human Decision
```

---

## Session Model

IMP-001 already provides a database-backed `sessions` table (see
[docs/implementation/IMP-001-core-project-foundation.md](IMP-001-core-project-foundation.md) and
[docs/audits/IMP-001-IMPLEMENTATION-PASS-1.md](../audits/IMP-001-IMPLEMENTATION-PASS-1.md)). IMP-002
MUST reuse it, not replace it — its `user_id` column (nullable, unconstrained) is exactly where an
authenticated session attaches its `User` foreign key once `User` exists.

```text
session fixation protection:  regenerate session ID on login and on privilege-relevant transitions
                               (e.g. password change)
session rotation:              periodic ID rotation is a Laravel framework capability
                               (`session.php` config); use framework defaults, do not build a
                               custom rotation mechanism
logout:                        see "Authentication Flows > Logout" above
expiration:                    framework-standard SESSION_LIFETIME (already configured in
                               .env.example per IMP-001); no override decided by any materialized
                               document
remember-me:                   Laravel's framework "remember token" capability is available;
                               whether to enable it is an implementation-time UX decision, not an
                               architecture requirement — if enabled, the remember token MUST be
                               hashed at rest, consistent with "Credential Model"
```

---

## Password Reset

```text
request:                       accepts the login identifier; ALWAYS returns a generic
                               "if an account exists, instructions were sent" response — never
                               confirms/denies account existence (see "Privacy")
token generation:               cryptographically random token
token storage:                   stored HASHED, not plaintext — the stock Laravel
                                 `password_reset_tokens` table design (email + plaintext-ish
                                 token column with an expiry) is NOT to be restored as-is without
                                 verifying its hashing behavior; Laravel's framework
                                 `PasswordBroker` already hashes reset tokens by default — use
                                 that framework capability rather than a custom table/mechanism
expiration:                      framework-standard broker expiry (config value, not invented here)
single use:                      token is invalidated immediately upon successful use
password replacement:            new password is hashed per "Credential Model"; all of the
                                  identity's other active sessions SHOULD be invalidated (a
                                  password reset is a strong signal the prior session state may be
                                  compromised)
audit:                           reset requested, reset completed, and reset-token-invalid/expired
                                  attempts are all audited
```

This reuses Laravel's framework password-broker capability (table name/shape decided at
implementation time to match framework conventions and the hashing requirement above) rather than
inventing a bespoke reset mechanism — consistent with
[CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md) "Dependency Governance."

---

## Verification

Distinguish three states that MUST NOT be conflated (this distinction is required by this task's
own instruction and is consistent with "Preserved Distinctions" patterns already established in
docs/05-rbac/RBAC-ARCHITECTURE.md, e.g. "Object Access != Full Field Disclosure"):

```text
identity verified:       the User record exists and its credential was validated (i.e. login
                          succeeded) — this is authentication, not contact verification
contact verified:        the claimed email and/or phone has been confirmed reachable/owned by the
                          principal (verification link/code lifecycle)
business authority approved: an Authority Assignment exists (docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md)
                          — entirely an IMP-003+ concern, never established by IMP-002
```

Whether contact verification is required, and for which identifier (email and/or phone), depends
on the undecided login-identifier question (see "Email / Phone Identity"). IMP-002 specifies the
lifecycle shape (send verification, confirm via signed/token link or code, re-send with rate
limiting, mark verified, audit) without deciding which identifier(s) require it until that
Human Decision is made.

---

## Authentication Assurance

Per [docs/05-rbac/AUTHENTICATION-ASSURANCE.md](../05-rbac/AUTHENTICATION-ASSURANCE.md), IMP-002
establishes the neutral STANDARD/ELEVATED state that IMP-003+ later consumes as the "Required
Authentication Assurance" AND-term of the canonical authorization formula
([docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md)). IMP-002 does NOT decide
which specific business operations require ELEVATED — that mapping belongs to the stage that
implements each such operation, per AUTHENTICATION-ASSURANCE.md itself.

```text
STANDARD:   the ordinary state after a normal login.
ELEVATED:   achieved via a fresh/recent re-authentication (e.g. password confirmation within a
            short window) or, if MFA is decided (see "MFA"), a completed MFA challenge. The exact
            mechanism is intentionally not fixed by the materialized architecture — do not invent
            one beyond "fresh re-authentication," which is the one mechanism the architecture
            document itself gestures at ("fresh/re-authenticated ... session").
```

IMP-002 must expose this state (e.g. as an attribute of the authenticated session/principal)
neutrally — it must not itself gate any business action, since IMP-002 implements no business
action.

---

## MFA

**HUMAN DECISION REQUIRED.** No materialized document states whether MFA is required, optional,
configurable, or deferred, nor which method (TOTP, SMS, passkeys, email code, etc.) would be used.
[docs/05-rbac/AUTHENTICATION-ASSURANCE.md](../05-rbac/AUTHENTICATION-ASSURANCE.md) explicitly says:
"No specific MFA vendor, mechanism, or step-up protocol is defined by this document... Do not
invent a specific mechanism when implementing; that belongs to the stage that implements
authentication (IMP-002) and must be sourced from the Human Authority."

This specification therefore does NOT decide MFA policy. If the Human Authority defers MFA
entirely, IMP-002 may proceed using password-confirmation-based ELEVATED assurance only (see
"Authentication Assurance"), leaving an MFA enrollment/verification/recovery/disable design for a
later, explicitly authorized addition.

---

## Security Restrictions (Account Status)

Neutral, authentication-relevant states only — no business-authorization state:

```text
active                normal authenticatable state
pending_verification  contact not yet verified (if verification is required — see "Verification")
suspended              temporarily blocked from authenticating (e.g. abuse/fraud signal); reason
                       not exposed in the public error response
disabled               permanently deactivated by an administrative action (out-of-scope trigger;
                       IMP-002 only defines the state's effect on authentication)
locked                 temporary lockout from repeated failed login attempts (see "Rate Limiting")
```

This state set is a specification-time proposal built from generic, universally-applicable
authentication concepts (active/locked/suspended/disabled are standard Laravel-ecosystem
authentication states, not repository-invented business states) — no materialized document
enumerates an authoritative state list. It should be treated as a reasonable default the
readiness reviewer may confirm or adjust, not as itself a Human Decision, since it introduces no
business/financial/authorization semantics — only "No Security Restriction" (an existing AND-term
of the canonical authorization formula) needs a concrete state to check against.

---

## Principal Exposure

```text
web requests:  the framework's authenticated-user resolution (`Auth::user()` / request-bound
               principal) exposes the `User` plus its Authentication Assurance state.
API requests:  same principal concept; exact API authentication mechanism is undecided (see "API
               Authentication Boundary") but whatever mechanism is chosen must resolve to the same
               `User` + assurance state shape, not a parallel identity concept.
jobs:          IMP-002 does not implement System Principal job plumbing — per
               docs/04-security/SECURITY-ARCHITECTURE.md, "System Principal" and "Integration
               Principal" are architecture concepts already established; assigning them to a
               specific implementation stage is not decided by any materialized document, and
               this specification does not claim that assignment for IMP-002. Flagged as a
               deferred item, not implemented here.
audit:         every audited authentication event (see "Audit Events") records the acting
               `User` (or "system"/"anonymous" where applicable) as its principal.
future policies: IMP-003's canonical authorization formula consumes "Authenticated" (a boolean derived
               from principal presence) and "Required Authentication Assurance" (the STANDARD/
               ELEVATED state) directly from what IMP-002 exposes here.
```

Distinguish, only where the architecture already names them (no invention): Human Principal (a
`User` acting directly), System Principal, and Anonymous Principal (an unauthenticated request/
guest). Integration Principal (external-integration-initiated actions,
per SECURITY-ARCHITECTURE.md) is not addressed by IMP-002 — it belongs to whichever stage first
introduces an external integration (e.g. a payment provider webhook), not to Identity.

---

## API Authentication Boundary

Per [docs/implementation/IMP-001-core-project-foundation.md](IMP-001-core-project-foundation.md)
§9 ("Routing Foundation"), no `/api/v1` business routes exist yet, and IMP-001 deliberately did not
add an API placeholder. No materialized document decides whether future `/api/v1` authentication
will use session/cookie auth, personal access tokens, OAuth, or another mechanism.

**This is not treated as a blocking Human Decision for IMP-002**, because IMP-002's own scope is
the web (session-based) authentication foundation; API authentication mechanism selection can be
made later, by whichever stage first implements `/api/v1` business routes, without invalidating
anything IMP-002 builds (the underlying `User` + credential + assurance model is
transport-agnostic). It is recorded here as a **deferred, non-blocking item**.

If/when API tokens are introduced, the locked rule applies unchanged: "API token authority
intersection (a token's effective authority is the intersection of the token's own grant and its
principal's authority, never a union)" (SECURITY-ARCHITECTURE.md) — token *authorization* is an
IMP-003+ concern layered on top of whatever `User` IMP-002 establishes.

---

## CSRF / Cookie Security

Standard Laravel web-guard defaults, not weakened:

```text
CSRF protection:    Laravel's VerifyCsrfToken middleware on all state-changing web routes
                     (already part of the framework's default web middleware group)
Secure cookie:       session cookie `secure` flag enabled in production (HTTPS-only)
HttpOnly:            session cookie HttpOnly (framework default)
SameSite:            framework default (`lax`) unless a specific flow requires otherwise —
                     no materialized document overrides this
session regeneration: on login/logout/password change, per "Session Model"
HTTPS production:    expected in production; shared-hosting deployment (Apache/LiteSpeed) is
                     assumed to terminate TLS per normal hosting practice — not a new requirement
                     invented here, consistent with docs/00-governance and IMP-001's shared-hosting
                     baseline
```

---

## Rate Limiting / Abuse Control

Required on: login, registration (where self-service), password-reset request, verification
resend, and MFA verification (if MFA is later decided). Use Laravel's framework rate limiter
(`RateLimiter` facade / `throttle` middleware) — no custom implementation, no mandatory Redis (the
framework's rate limiter works against the database/cache store IMP-001 already configured).
Exact numeric limits are NOT fixed by any materialized document; treat them as configurable
(environment/config-driven) but never unlimited, per
[CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md) "Dependency Governance" (configurable !=
unrestricted, this specification's own extension of that principle to rate limits).

---

## Audit Events

The following authentication/security events SHOULD become auditable, subject to whatever the
(currently unmaterialized in detail) Audit architecture requires structurally —
[docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §16 assigns
"Audit" to Governance & Platform Services, not to Identity. IMP-002 emits the events; it does not
build the Audit domain's storage/query mechanism beyond whatever minimal, already-approved
foundation IMP-001/IMP-000 established (none currently — no Audit table exists yet). This is
recorded as a deferred, non-blocking item: event *emission points* are specified now; the audit
*sink* is a later stage's responsibility.

```text
identity created
login succeeded
login failed
logout
password changed
password reset requested
password reset completed
contact verification requested / completed
MFA enrolled / removed (if MFA is later decided)
account locked / unlocked
account suspended / disabled / reactivated
security-sensitive profile change (e.g. login-identifier change, if supported)
```

---

## Privacy

Authentication responses must not allow account enumeration:

```text
login failure:            identical generic message whether the identifier does not exist or the
                           password is wrong
password-reset request:    identical generic "if an account exists..." response regardless of
                           existence
registration (if self-service and identifier must be unique): a "this identifier is already in
                           use" message during registration is an accepted, common trade-off
                           (uniqueness must be enforced somewhere), but should not be paired with
                           further account detail
verification resend:        generic response regardless of current verification state, where
                           practical
```

Do not expose: email/phone existence beyond the unavoidable registration-uniqueness trade-off
above, internal identifiers (expose ULID publicly, never the internal BIGINT), or security-state
detail (e.g. do not say "this account is suspended" in a pre-authentication response — say
"invalid credentials" or an equally neutral message, and rely on audit logs / authenticated
account-security UI for the account holder to learn more, if architecture later decides that is
appropriate).

---

## Retention Boundary

Per [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §10
("Retention cannot silently destroy financial, Ledger or audit evidence") and
[docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §16 (Retention
owned by Governance & Platform Services, not Identity):

```text
Deleting/closing a User account MUST NOT cascade-delete or orphan any immutable financial/Ledger/
  audit record that references that identity.
Anonymization/pseudonymization of a closed identity (rather than hard deletion) is the likely
  mechanism, consistent with the retention principle above, but the exact mechanism, retention
  period, and legal-hold interaction are NOT decided by any materialized document.
IMP-002 does not implement retention/anonymization logic. It documents this boundary so that the
  User table's design (e.g. avoiding hard foreign-key CASCADE DELETE from financial tables to
  User) does not need later, disruptive rework.
```

This is a deferred, non-blocking documentation boundary, not a Human Decision required to start
IMP-002 — it constrains *how* later stages must reference `User`, not what IMP-002 itself builds.

---

## Database Design Requirements

Specification only — no migration is created by this document.

```text
users (or the eventual entity name) table:
  id                    BIGINT unsigned, primary key, auto-increment (DATABASE-ARCHITECTURE.md
                        "Identity Conventions")
  public_id             ULID, unique, indexed (public/API-safe identifier)
  <login identifier>    UNDECIDED column(s) — depends on "Email / Phone Identity" Human Decision;
                        whichever is chosen must be UNIQUE and normalized (e.g. lowercase email)
                        at the database level, not only in application validation
  password              string, hashed at rest
  status                string/enum — the neutral security-restriction states above
  <verification state>  nullable timestamp(s) per verified identifier (e.g. email_verified_at),
                        shape depends on the same undecided identifier question
  security metadata     failed_login_attempts (int), locked_until (nullable timestamp),
                        last_login_at (nullable timestamp) — standard, non-invented fields
  remember_token        nullable string, hashed, ONLY if "remember me" is enabled (see "Session
                        Model")
  timestamps            created_at, updated_at
  NO soft-delete column by default (see "Retention Boundary" — closure/anonymization is a
    separate, later-decided mechanism, not a blanket soft-delete)

password reset support:
  Use Laravel's framework password-broker table shape (hashed token, identifier, created_at) —
  do not restore the stock unmodified password_reset_tokens table without verifying it stores the
  token hashed (framework's default broker does hash it; verify at implementation time, do not
  assume).

sessions table:
  ALREADY EXISTS (IMP-001). Its user_id column becomes populated once User exists. No schema
  change is anticipated; if one becomes necessary, it must be a new migration, per
  [BRANCHING-POLICY.md](../00-governance/BRANCHING-POLICY.md) "Migration Immutability" — never an
  edit to the IMP-001 migration.

Foreign keys:      any later module referencing User (Donor context, Fundraiser Profile, Partner
                    Profile, Beneficiary Profile, etc.) does so via User's internal BIGINT id, per
                    DATABASE-ARCHITECTURE.md "Identity Conventions." No such foreign-keyed table is
                    created by IMP-002 itself.
Unique indexes:     login identifier (whichever is chosen), public_id.
Case/normalization: login identifier normalized (e.g. lowercased email) before uniqueness
                    comparison and storage.
Money/DECIMAL:      not applicable — IMP-002 has no monetary columns.
```

---

## Transaction Boundaries

```text
account/identity creation (registration or provisioning)   — transactional
password reset completion (token consumption + password update + token invalidation) —
  transactional
contact verification completion (mark verified + invalidate verification token) — transactional
MFA enrollment completion (if later decided) — transactional
security lock/unlock (state change + audit event) — transactional
```

---

## Concurrency / Idempotency

```text
duplicate registration:          unique index on the login identifier prevents a duplicate row;
                                  the application layer must handle the resulting constraint
                                  violation as a normal "identifier already in use" outcome, not a
                                  500 error
simultaneous password reset:      a new reset request should not necessarily invalidate an
                                  in-flight one before it's known to be needed; at minimum, using
                                  a consumed/expired token a second time MUST fail safely (already
                                  covered by "single use" in "Password Reset")
verification token replay:        a consumed verification token/link MUST be rejected on reuse
multiple reset requests:          generating a new reset token may invalidate prior outstanding
                                  ones (reasonable default; not mandated by any materialized
                                  document)
double MFA enrollment:            not applicable until MFA is decided
concurrent account updates:        standard optimistic handling (e.g. `updated_at` check) is
                                  sufficient; no materialized document requires stronger
                                  concurrency control for identity records specifically (contrast
                                  with the Financial Idempotency invariant in
                                  docs/03-database/DATABASE-INVARIANTS.md, which does not apply to
                                  non-financial Identity records)
```

---

## Error Model

```text
validation error:              structured field-level errors (framework standard), safe to
                                display
authentication failure:         generic message (see "Privacy") — no field-level distinction
                                between "unknown identifier" and "wrong password"
account security restriction:   generic, non-account-confirming message (see "Privacy")
rate limit:                     standard HTTP 429-equivalent with a safe retry-after hint; no
                                account-identifying detail
expired token:                  generic "this link/code has expired" — does not confirm whether
                                the underlying account exists
invalid token:                  generic "this link/code is invalid"
verification required:          a distinct, safe message shown only to an already-authenticated
                                principal (not to a pre-authentication requester), since it
                                inherently confirms account existence to the one party who already
                                proved control of the credential
```

---

## Route Ownership

Categories only — no route is implemented by this document:

```text
guest authentication:        login, (registration where decided)
authenticated account security: password change, account security state view, (MFA management
                              where decided), logout, "logout other sessions"
verification:                 verify-contact confirmation endpoint, resend
password recovery:            forgot-password request, reset-password completion
```

All routes remain same-origin under the single root domain (`/donor/*`, `/fundraiser/*`,
`/partner/*`, `/admin/*` per MASTER-REQUIREMENTS.md §3, or a neutral shared auth path such as
`/auth/*` or `/login` depending on whether authentication is portal-scoped or shared — **this
exact routing shape is an implementation-time decision, not fixed by any materialized document**,
but it MUST remain same-origin and MUST NOT introduce a separate auth subdomain, per
MASTER-ARCHITECTURE.md's locked "Single root domain" principle.

---

## UI Boundary

May implement, using the existing Inertia/Vue/Tailwind foundation from IMP-001:

```text
login UI
registration UI (only for actor(s) later decided to self-register)
forgot password UI
reset password UI
contact verification UI
account security UI (password change, session list if "logout other sessions" is built)
MFA UI (only if MFA is later decided)
```

Must NOT implement: donor dashboard, fundraiser dashboard, partner dashboard, or admin business
dashboard. A single neutral authenticated landing page (in the spirit of IMP-001's neutral
"Foundation" page) is acceptable if needed to prove the authenticated flow renders end-to-end —
it must not become a business dashboard.

---

## Testing Requirements

At minimum, before IMP-002 implementation may be considered complete:

```text
registration eligibility (for whichever actor(s) are decided to self-register)
credential hashing (password never stored/logged in plaintext)
login success
login failure (wrong password) -> generic message, no enumeration
login failure (unknown identifier) -> identical generic message
session fixation protection (session ID changes on login)
logout (session invalidated; ID rotated)
account restriction (suspended/disabled/locked) -> authentication denied, generic message
password reset: request -> generic response regardless of existence
password reset: token single-use (second use fails)
password reset: expired token fails
contact verification: valid token verifies; expired/invalid token fails; replay fails
rate limiting: login, registration, password-reset request, verification resend all throttled
MFA (only if decided): enrollment, verification, recovery, disable
account enumeration resistance: timing/response-shape parity between "unknown identifier" and
  "wrong password" cases, and between "account exists" and "account does not exist" for
  password-reset request
CSRF: state-changing auth routes reject requests without a valid CSRF token
database constraints: duplicate login identifier rejected at the database level, not only in
  application validation
audit events: each event in "Audit Events" is actually emitted at its corresponding action
guest/authenticated middleware behavior: guest-only routes reject an authenticated principal
  appropriately; authenticated-only routes reject a guest
```

Explicitly required negative coverage: no test may assert or rely on a role, permission, scope, or
business-authority check — IMP-002 has none, and its test suite must demonstrate the absence of
authorization leakage into authentication (e.g. a suspended account is denied at authentication,
not "allowed in but with no permissions").

---

## Security Testing

```text
plaintext credential prevention:  password column never contains a plaintext value in test
                                   assertions or database inspection
credential leakage:                password/token values never appear in logs, error responses,
                                   or serialized API/Inertia payloads
token leakage:                     password-reset and verification tokens never appear in logs or
                                   are exposed via a non-owning request
session fixation:                  covered above
CSRF:                               covered above
brute-force controls:              repeated failed logins trigger rate limiting/lockout per
                                   "Rate Limiting / Abuse Control"
expired token reuse:                covered above
credential reset invalidation:      other active sessions are invalidated after a password reset,
                                   per "Password Reset"
security-restricted account authentication: covered above
```

Penetration testing is NOT claimed or required at this stage — this is automated test coverage
only, consistent with [DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)'s testing
gate.

---

## Shared Hosting Compatibility

IMP-002 must function on IMP-001's existing approved production foundation without adding any
mandatory daemon:

```text
Apache/LiteSpeed        unchanged
PHP                      unchanged (8.3 baseline)
MySQL                    unchanged
database sessions        unchanged (reused, not replaced)
database queue           unchanged — if any authentication email (verification, password reset)
                        is queued, it uses the existing database queue, not a new requirement
cron                     unchanged
Laravel Storage          unchanged
compiled frontend assets  unchanged
```

Email delivery for verification/password-reset MUST use Laravel's standard mail
configuration/transport (already scaffolded via `MAIL_*` env vars in IMP-001's `.env.example`,
currently `log` driver for local development) — no mandatory dependency on a specific transport
service; the actual production transport (SMTP, a provider API, etc.) is a deployment-time
configuration choice, not an architecture decision for this specification to make.

Redis remains NOT mandatory for session, queue, cache, or rate limiting — the framework's database/
cache-store-backed rate limiter is used instead.

---

## Dependency Policy

No new Composer/npm package is proposed by this specification. Laravel's framework capabilities
(`Auth`, `Hash`, session, password broker, `RateLimiter`, Blade/Inertia mail views) are believed
sufficient for everything specified above. If implementation later finds a framework capability
insufficient, it must document why (framework capability insufficiency, security implications,
maintenance implications, shared-hosting impact) per
[CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md) "Dependency Governance" before adding
anything — that determination is deferred to implementation, not decided here.

---

## Human Decisions Required

```text
1. Login identifier: email, phone, username, or a combination — and whether it is uniform across
   all actors or varies per actor. Blocks: identity table's unique-identifier column(s),
   verification lifecycle shape, registration validation.

2. Self-registration eligibility per actor: which of Donor / Fundraiser / Partner User /
   Beneficiary / Operational Staff / Admin (or others, if the Actor Catalog turns out to be
   incomplete here) may self-register vs. must be invited/provisioned. Blocks: registration UI
   scope, registration eligibility tests.

3. MFA policy: required / optional / configurable / deferred, and if not deferred, which
   method(s). Blocks: MFA enrollment/verification/recovery design, database columns for MFA
   secrets, UI scope. Per docs/05-rbac/AUTHENTICATION-ASSURANCE.md, this is explicitly flagged by
   the architecture itself as something IMP-002 must source from the Human Authority, not invent.

4. Actor Catalog itself: no authoritative Actor Catalog exists in this repository
   (docs/06-domains/identity/ is empty). The actor table in this specification is built only from
   actor names incidentally present in other materialized documents and may be incomplete (e.g. it
   does not know whether other actor categories exist, or whether "Operational Staff" should be
   subdivided). Materializing (or authoring, if genuinely new) an Actor Catalog would resolve this
   and is recommended before or alongside resolving items 1-3.
```

None of these are invented answers in this document — each is a distinct, load-bearing gap that a
Definition of Ready cannot pass without.

---

## Definition of Ready

```text
[x] Authoritative sources available and read
[ ] Identity model unambiguous               -- entity ownership boundary IS clear (User only;
                                                see "Identity Model"); exact column set for the
                                                login identifier is NOT (Human Decision 1)
[ ] Login identifier unambiguous              -- FAIL (Human Decision 1)
[ ] Registration policy unambiguous            -- FAIL (Human Decision 2)
[ ] Password/reset model unambiguous          -- PASS (framework password-broker direction is
                                                clear and does not depend on the open decisions)
[ ] Verification rules unambiguous            -- PARTIAL (lifecycle shape is clear; which
                                                identifier(s) require it depends on Human
                                                Decision 1)
[ ] MFA requirement unambiguous                -- FAIL (Human Decision 3)
[x] Authentication assurance model mapped     -- PASS (STANDARD/ELEVATED consumed as specified)
[x] Security restrictions mapped               -- PASS (neutral state set proposed; not itself
                                                blocking)
[x] Database requirements defined              -- PASS at the level this stage requires
                                                (columns depending on Decision 1 are explicitly
                                                marked undecided, not guessed)
[x] Stage boundaries clear                     -- PASS
[x] Tests defined                              -- PASS
[x] Shared-hosting compatibility preserved     -- PASS
[x] No architecture conflicts                  -- PASS (no Level 1-3 document was found in
                                                conflict with another; see "Authority Review" in
                                                the final report)
[ ] No unresolved implementation blocker        -- FAIL (Human Decisions 1-3 above)
```

**Definition of Ready: FAIL.** Three essential questions (login identifier, self-registration
eligibility, MFA policy) and one documentation gap (Actor Catalog) remain open. This specification
is otherwise structurally complete and internally consistent with every materialized Level 1-3
document it cites.

---

## Definition of Done

Unreachable until Definition of Ready passes and implementation is separately authorized. Restated
for forward reference (per
[docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)):
implementation matches this (then-finalized) spec; tests added and passing, including the negative
paths above; no debug code; no secrets committed; documentation updated; no architecture
regression; independent review completed; BLOCKER/MAJOR findings resolved; acceptance criteria
satisfied.

---

## Risks

```text
Deciding the login identifier late (after partial implementation) would require a database/
  validation rework — recommend resolving Human Decision 1 before implementation begins, not
  during it.
Retrofitting MFA after initial login/session implementation is a moderate rework but not a
  destructive one, since Authentication Assurance is already modeled as a separate concept from
  the login mechanism itself.
An incomplete Actor Catalog risks a registration-eligibility decision that later needs revisiting
  when a previously-unknown actor category is discovered.
```

---

## Deferred Items (Non-Blocking)

```text
API authentication mechanism (session vs. token vs. OAuth) for future /api/v1
System Principal / Integration Principal implementation ownership (architecture concepts exist;
  no stage has claimed their implementation yet)
Audit domain storage/query mechanism (event emission points are specified; the sink is not built)
Retention/anonymization mechanism for closed identities (boundary documented; mechanism not
  designed)
Exact route paths for authentication (categories specified; paths not fixed)
```

---

## Implementation Gate

```text
IMP-002 Specification:        DRAFT
IMP-002 Definition of Ready:   FAIL — Human Decisions Required (see above)
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
```

STOP.

Do not implement IMP-002. Do not create migrations, models, controllers, middleware, routes, UI,
factories, seeders, or tests for this stage. Do not install any authentication package. Route the
Human Decisions above to the Human Authority before requesting a readiness review.
