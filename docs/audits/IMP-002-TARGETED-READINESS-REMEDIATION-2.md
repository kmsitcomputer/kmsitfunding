# IMP-002 Targeted Readiness Remediation 2

Task:
IMP-002 Targeted Readiness Remediation Pass 2

Branch:
impl/002-identity-authentication (verified via `git branch --show-current` before any change)

Coding Performed:
NO

Previously Resolved Findings Regressed:
NO — B01, M02, M03, M06, m01, m02 were not reopened; only IMP002-RDY-M01, M04, M05 were targeted
this pass, per instruction.

## IMP002-RDY-M01 — Email-change request versioning

```
Original Finding:  the prior design kept a single `pending_email` column on `User` and only said
                   an application "MAY choose to invalidate a prior unconfirmed pending-email
                   token" when a new request was made — this left a stale-token race: an old,
                   still-valid verification token could potentially be checked against whatever
                   `users.pending_email` currently held, rather than the exact email it was
                   originally issued for.
Root Cause:        the design used one mutable field (`pending_email`) as both the request state
                   and the verification target, instead of an immutable, versioned request record
                   that a token is permanently bound to.
Specification
Correction:        replaced `users.pending_email` entirely with a new `EmailChangeRequest` entity
                   (id, user_id, normalized_pending_email, verification_token_hash, requested_at,
                   expires_at, verified_at, superseded_at, cancelled_at, generation). At most one
                   ACTIVE request exists per User; starting a new request unconditionally
                   supersedes any prior active request BEFORE/atomically WITH creating the new one
                   — never "may," always mandatory. Verification MUST check the token against the
                   exact request row it resolves to (user match, current/active, not superseded/
                   cancelled/expired/consumed, token match, and the email being promoted is that
                   exact request's own `normalized_pending_email`) — the specification now
                   explicitly prohibits the pattern "token valid -> read whatever
                   `users.pending_email` currently holds -> promote that," which was possible
                   (even if not intended) under the prior design. Success semantics (locking,
                   re-checks, atomic promotion, session/assurance/reset-token invalidation) and
                   cancellation/expiry/supersession semantics were fully respecified; a dedicated
                   Concurrency subsection covers the two-rapid-requests case, the
                   token-can-never-cross-generations guarantee, and the uniqueness-collision-at-
                   promotion failure mode.
Authority:         no Human Decision required — this is mechanics of an already-scoped concern
                   (Q21: email is the canonical login identifier).
Files Changed:     docs/implementation/IMP-002-identity-authentication.md (sections: "Canonical
                   Email Change Lifecycle," "Identity Model," "Password Reset > Interaction with
                   Email Change," "Database Design Requirements," "Transaction Boundaries,"
                   "Concurrency / Idempotency," "Testing Requirements," "Definition of Ready,"
                   "Definition of Done")
Test Requirements
Added:             new EmailChangeRequest immediately supersedes prior active request; superseded/
                   cancelled/expired/consumed tokens always rejected; a token can never promote a
                   different request's email; two rapid requests (A then B) leave A non-functional
                   while B may succeed; concurrent verification of the same request resolves to
                   exactly one success; uniqueness collision at promotion leaves the current email
                   unchanged and fails safely; successful promotion is atomic end-to-end.
Status:            RESOLVED
```

## IMP002-RDY-M04 — TOTP replay must be mandatory

```
Original Finding:  replay-protection language was conditional ("where the selected library/
                   application design supports it deterministically"), and an earlier revision
                   had offered a hand-rolled minimal hash_hmac TOTP implementation as an
                   acceptable alternative to a maintained library.
Root Cause:        hedged/conditional wording left both the replay guarantee and the
                   implementation-contract prohibition weaker than the security requirement
                   actually demands.
Specification
Correction:        "TOTP Replay Protection" rewritten as mandatory (not conditional) for MFA
                   enrollment confirmation, ELEVATED assurance TOTP step-up, MFA disable
                   confirmation, and MFA reset confirmation — the same accepted code cannot be
                   reused in the same time step for the same (User, challenge context) pair once
                   consumed. Added a "Concurrent TOTP Replay" subsection requiring exactly one
                   success under concurrent same-code submission, using the same
                   transaction/atomic-state pattern already required for recovery-code
                   consumption. "Implementation Contract"'s package selection criteria now
                   requires the chosen library to expose enough information/validation control to
                   make the mandatory replay policy enforceable, and states a package that makes
                   the guarantee impossible is not acceptable — package choice cannot weaken the
                   security contract. "Reset / Disable" rewritten to remove "where security policy
                   requires it" and make session/assurance/secret/recovery-code invalidation on
                   MFA reset/disable fully deterministic, including current-session rotation (self-
                   service) or all-sessions invalidation (administrative/security-recovery path).
Authority:         no Human Decision required — Q23 (configurable MFA, TOTP baseline) is
                   unchanged; this only tightens an implementation-contract ambiguity and closes a
                   security gap consistent with AGENTS.md "No custom cryptography" and defense-in-
                   depth principles already in docs/04-security/SECURITY-ARCHITECTURE.md.
Files Changed:     docs/implementation/IMP-002-identity-authentication.md (sections: "MFA >
                   Implementation Contract," "TOTP Replay Protection," new "Concurrent TOTP
                   Replay," "Reset / Disable," "Database Design Requirements > MFA secret /
                   recovery storage" (added `last_accepted_step`), "Testing Requirements",
                   "Concurrency / Idempotency", "Definition of Ready", "Risks")
Test Requirements
Added:             same TOTP cannot elevate/disable/reset/activate-enrollment twice; concurrent
                   submission of the same accepted code succeeds exactly once; a genuinely new
                   valid code in a later time-step still works; recovery-code single-use remains
                   independent of TOTP replay state.
Status:            RESOLVED
```

## IMP002-RDY-M05 — Remove User lockout fields (align with Q24)

```
Original Finding:  the `users` schema still proposed `failed_login_attempts` and `locked_until` as
                   User columns, even though Q24 separates persistent identity/security state from
                   transient brute-force protection.
Root Cause:        the field list had not yet been reconciled against Q24's model when Q24 was
                   first incorporated in the prior pass.
Specification
Correction:        removed `failed_login_attempts` and `locked_until` from the `users` field list
                   entirely (and from "Identity Model"'s field list). Added a new "Authentication
                   Rate-Limiter State" database-requirements entry stating this transient state
                   belongs to the Authentication Security / Rate Limiter infrastructure (Laravel's
                   `RateLimiter` / cache-backed throttling), not a migration-created `users`
                   column. Added a "Rate Limit vs. Suspension — Explicit Distinction" subsection to
                   "Account / Security Model" contrasting transient throttling (expires on its own,
                   never User schema state) against `SUSPENDED` (persistent, requires an authorized
                   transition) — too many failed logins must never automatically mutate
                   `security_restriction` to `SUSPENDED`. Added rate-limiter-keying guidance
                   (email/IP/flow/User combinations) without fixing exact limits.
Authority:         Q24 (already FINAL/LOCKED, from the prior pass) — this pass applies it
                   correctly rather than changing it.
Files Changed:     docs/implementation/IMP-002-identity-authentication.md (sections: "Identity
                   Model," "Account / Security Model" (new "Rate Limit vs. Suspension" subsection,
                   updated "Transient Brute-Force Lockout"), "Database Design Requirements" (`users`
                   table + new "Authentication Rate-Limiter State" entry), "Testing Requirements",
                   "Definition of Ready", "Definition of Done")
Test Requirements
Added:             `users` schema contains no `locked_until`/`failed_login_attempts`; failed-login
                   throttling works only through the limiter mechanism; limiter expiration never
                   mutates `identity_status`/`security_restriction`; SUSPENDED remains distinct
                   from, and is never auto-triggered by, transient throttling.
Status:            RESOLVED
```

## Summary

```
IMP002-RDY-M01 = RESOLVED
IMP002-RDY-M04 = RESOLVED
IMP002-RDY-M05 = RESOLVED
```

## Previously Resolved Findings — Not Reopened

```
IMP002-RDY-B01 = RESOLVED (unchanged this pass)
IMP002-RDY-M02 = RESOLVED (unchanged this pass)
IMP002-RDY-M03 = RESOLVED (unchanged this pass)
IMP002-RDY-M06 = RESOLVED (unchanged this pass)
IMP002-RDY-m01 = RESOLVED (unchanged this pass)
IMP002-RDY-m02 = RESOLVED (unchanged this pass)
```

Verified by inspecting each of these sections after the patch (Invitation Boundary, Authentication
Assurance, First Super Admin Bootstrap, Remember-Me (Deferred), Credential Model > Password
Change) — none were rewritten beyond incidental cross-reference updates where they referenced the
now-corrected M01/M05 material (e.g. "Password Reset > Interaction with Email Change" now refers
to `EmailChangeRequest` instead of `users.pending_email`).

## Human Decisions

```
Q21 = FINAL / LOCKED (unchanged)
Q22 = FINAL / LOCKED (unchanged)
Q23 = FINAL / LOCKED (unchanged)
Q24 = FINAL / LOCKED (unchanged; applied correctly this pass)
Q25 = FINAL / LOCKED (unchanged)
```

Open Human Decisions: NONE. No new Human Decision was required or introduced by this pass.

## Governance Check

```
application code changed:   NO
migration created:           NO
dependencies changed:        NO
routes created:               NO
model created:                 NO
controller created:            NO
Vue page created:              NO
```

## Definition of Ready

PASS — see the specification's own "Definition of Ready" section.

## Result

```
IMP-002 Specification:        READY FOR TARGETED READINESS RE-AUDIT (PASS 2)
IMP-002 Definition of Ready:   PASS
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
Coding Performed:               NO
```
