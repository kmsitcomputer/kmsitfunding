# IMP-002 Specification Remediation 1

Task:
IMP-002 Targeted Specification Remediation

Branch:
impl/002-identity-authentication (verified via `git branch --show-current` before any change)

Coding Performed:
NO

## Human Decisions Incorporated

```text
Q21 — Login Identifier    = EMAIL AS CANONICAL LOGIN IDENTIFIER — FINAL / LOCKED
Q22 — Registration Model  = HYBRID REGISTRATION BY ACTOR — FINAL / LOCKED
Q23 — MFA Policy          = CONFIGURABLE MFA, TOTP BASELINE — FINAL / LOCKED
```

These were supplied directly to this remediation task by the Human Authority and are recorded in
[docs/implementation/IMP-002-identity-authentication.md](../implementation/IMP-002-identity-authentication.md)
"Human Decisions Incorporated." They are not yet reflected in
[docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md)
(which currently holds only Q1-Q20) — that register is a separate Level 1 artifact and was not
edited by this task; recording Q21-Q23 there, if desired, is a governance action independent of
this specification patch.

## Actor Catalog Reconciliation

Authoritative Source:
[docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §3 (route
prefixes `/donor/*`, `/fundraiser/*`, `/partner/*`, `/admin/*`) and §6 (domain names "Donor
Portal," "Fundraiser Portal," "Partner Portal"), combined with
[docs/05-rbac/DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md) "Ownership Examples" (Donor,
Fundraiser, Beneficiary, Partner User, Operational Staff) and
[docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md) (Super Admin mention), and
Q22 itself (which names Donor, Fundraiser, Partner Representative, Internal Administrative
Identity, Super Admin explicitly).

Reconciled:
YES

New Actor Invented:
NO — every actor in the reconciled table (Donor, Fundraiser, Partner Representative/Partner User,
Internal Administrative Identity/Operational Staff, Super Admin, Beneficiary) was already named in
one of the documents above. "Partner Representative" and "Internal Administrative Identity" are
Q22's own terms for the same actors DATA-SCOPE-MODEL.md calls "Partner User" and "Operational
Staff" respectively — terminology aligned, not a new actor introduced.

Actor Removed:
NO

Actor Renamed Semantically:
NO (terminology aligned across documents; no actor's meaning changed)

Human Decision Required (Actor Catalog itself):
NO — reclassified as a documentation/materialization gap (no `docs/06-domains/identity/` artifact
exists), not a blocking Human Decision. See "Actor Catalog Artifact Gap" in the specification.

Genuinely Missing Classification Found After Reconciliation:
Beneficiary's registration/authentication model is not addressed by Q22. This is recorded as a
non-blocking deferred item (see specification "Deferred Items"), not a new Human Decision blocking
IMP-002, because it does not affect the `User` entity design or any baseline flow already
specified.

## Prior Open Questions — Closed

```text
1. Login identifier                          -- CLOSED (Q21: email)
2. Self-registration eligibility per actor    -- CLOSED (Q22, for Donor/Fundraiser/Partner
                                                 Representative/Internal Administrative
                                                 Identity/Super Admin; Beneficiary deferred,
                                                 non-blocking)
3. MFA policy                                  -- CLOSED (Q23: configurable, TOTP baseline)
4. Actor Catalog gap                           -- RECLASSIFIED (documentation gap, not a Human
                                                 Decision) after reconciliation
```

## Remaining Open Items (Non-Blocking)

```text
Beneficiary registration/authentication model
API authentication mechanism for future /api/v1
System Principal / Integration Principal implementation ownership
Audit domain storage/query mechanism
Retention/anonymization mechanism for closed identities
Exact route paths for authentication
Administrative MFA reset authority (self-service path is specified; admin path requires an
  IMP-003+ Authority Assignment)
Actor Catalog artifact under docs/06-domains/identity/
TOTP dependency selection (package vs. minimal direct RFC 6238 implementation) — deferred to
  implementation-time Dependency Governance review
```

None of these block IMP-002's Definition of Ready — each is either scoped to a later stage or does
not affect anything IMP-002 itself specifies.

## Specification Patch Summary

The following sections of
[docs/implementation/IMP-002-identity-authentication.md](../implementation/IMP-002-identity-authentication.md)
were added or substantively rewritten to incorporate Q21-Q23:

```text
Document Control (Status)
Human Decisions Incorporated (new)
Authority Sources (Actor Catalog reconciliation note)
Stage Boundary (in-scope list; identity-creation-is-not-authorization strengthened)
Actors (table rebuilt against Q22; Actor Catalog Artifact Gap subsection added)
Identity Model (login identifier fixed to email; MFA state field added)
Email as Canonical Login Identifier (new — Q21 rules + normalization requirements)
Registration Flows (renamed from "Authentication Flows > Registration"; per-actor flows added)
Invitation Boundary (new)
Authentication Flows > Login (MFA challenge step added)
Password Reset (bound to email; other-session invalidation now mandatory, not merely "should")
Verification (email-specific; explicit default for pre-verification login behavior)
Contact Phone (new — phone is contact-only, never login)
Authentication Assurance (TOTP as an ELEVATED path)
MFA (fully specified: enrollment, verification, recovery, reset, secret storage, dependency note)
Security Restrictions (explicit separation from business-approval states)
Rate Limiting / Abuse Control (flows list expanded: registration per actor, TOTP, invitation)
Audit Events (expanded: self-registration, invitation, email verified, MFA events)
Privacy (invitation edge cases added)
Public ID (new — when public_id is/isn't exposed)
Database Design Requirements (rewritten as a per-table inventory: users, password-reset, MFA,
  invitation, sessions)
Transaction Boundaries / Concurrency / Error Model / Route Ownership / UI Boundary / Testing
  Requirements / Security Testing (all extended for registration-per-actor, invitation, and MFA)
Shared Hosting Compatibility (MFA-specific reaffirmation: no SMS/Redis/WebSocket requirement)
Human Decisions Required (now NONE)
Definition of Ready (re-evaluated: PASS)
Definition of Done (restated with MFA/registration specifics)
Risks / Deferred Items (updated)
Implementation Gate (Specification: READY FOR READINESS REVIEW; DoR: PASS; Implementation: NOT
  AUTHORIZED; IMP-003: NOT AUTHORIZED)
```

No section was removed. No previously-correct, decision-independent content (Session Model reuse
of IMP-001's `sessions` table, CSRF/cookie defaults, shared-hosting baseline, RBAC/financial
out-of-scope boundary) was altered beyond re-affirming it against the new decisions.

## Definition of Ready Result

PASS — see the specification's own "Definition of Ready" section for the full checklist.

## Architecture Change Required

NO. Q21-Q23 are Human Decisions answering previously-open specification questions; they do not
change any Level 1-3 locked architecture document, and no materialized document was edited by
this task.

## Verification

```text
git status            -- only docs/implementation/IMP-002-identity-authentication.md (modified)
                          and this audit record (new)
git diff --stat        -- documentation only; no application code, migration, configuration, or
                          package file touched
git diff --check       -- clean
```

## Result

```text
IMP-002 Specification:        READY FOR READINESS REVIEW
IMP-002 Definition of Ready:   PASS
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
Coding Performed:               NO
```
