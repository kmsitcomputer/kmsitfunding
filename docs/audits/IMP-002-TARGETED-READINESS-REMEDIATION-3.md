# IMP-002 Targeted Readiness Remediation 3

Task:
IMP-002 Targeted Readiness Remediation Pass 3

Branch:
impl/002-identity-authentication (verified via `git branch --show-current` before any change)

Coding Performed:
NO

## IMP002-RDY-P2-m01 — Email-change uniqueness-conflict state missing from conceptual schema

```
Finding:            Email-change uniqueness-conflict state is missing from the conceptual schema.

Severity:            MINOR — GATE IMPACT

Root Cause:          The specification described the uniqueness-collision-at-promotion outcome
                     only behaviorally ("the request is marked in a failed/conflicted state...
                     and can never be retried"), but the conceptual EmailChangeRequest schema
                     exposed no durable field to hold that outcome — only verified_at,
                     superseded_at, cancelled_at, and expires_at existed. This left it ambiguous,
                     after a rolled-back promotion transaction, exactly what durable, persisted
                     state (if any) distinguished a conflicted request from one that simply had
                     not yet been attempted.

Resolution:          Added `conflicted_at` (nullable timestamp) and `conflict_reason_code`
                     (nullable, controlled internal value; baseline: TARGET_EMAIL_ALREADY_IN_USE)
                     to the conceptual EmailChangeRequest model. Defined conflicted_at != NULL as
                     a sixth, permanently terminal, non-promotable, non-retryable outcome
                     (alongside ACTIVE, VERIFIED, SUPERSEDED, CANCELLED, EXPIRED) in a new "Email
                     Change Terminal States" subsection that supersedes and folds in the former
                     "Cancellation / Expiry / Supersession" subsection. Specified the exact
                     two-step durable flow: the promotion transaction ROLLS BACK completely on a
                     uniqueness conflict (users.email/email_verified_at are left untouched, and a
                     rolled-back transaction never persists its own conflict marker), then a
                     SEPARATE, durable "conflict finalization" transaction conditionally sets
                     conflicted_at/conflict_reason_code only if the request has not already reached
                     a different terminal state in the meantime (race-safe — an already-terminal
                     request's state is never overwritten). Specified that conflict_reason_code
                     holds only a controlled internal code, never a raw database/exception message,
                     and that the public-facing error remains the same generic response used for
                     any other invalid/expired/superseded token. A CONFLICTED request does not
                     count as "active," so the User may immediately create a new, independent
                     request; the conflicted request itself remains untouched as immutable
                     historical evidence.

Human Decision:      NO

Architecture Change: NO

Migration:           NO

Implementation:      NO

Status:              RESOLVED
```

## Files Changed

```
docs/implementation/IMP-002-identity-authentication.md
  - "Canonical Email Change Lifecycle": EmailChangeRequest field list (+conflicted_at,
    +conflict_reason_code, +created_at/updated_at); "Verification Token Binding" (added the
    not-conflicted check); replaced the one-paragraph conflict mention with new "Uniqueness
    Conflict — Durable Terminal State" and "Email Change Terminal States" subsections (the latter
    supersedes "Cancellation / Expiry / Supersession"); "Concurrency" (added conflict-finalization
    race-safety statement)
  - "Audit Events": added "email change conflicted (IDENTITY_EMAIL_CHANGE_CONFLICTED...)"
  - "Database Design Requirements > EmailChangeRequest": added conflicted_at/conflict_reason_code
    fields, updated the "at most one active row" unique-constraint description, and a retention
    note distinguishing a CONFLICTED row (kept as evidence) from ordinary prunable rows
  - "Testing Requirements": added a dedicated uniqueness-conflict test line (conflict persistence,
    canonical-email preservation, retry rejection, race-safety against
    cancellation/supersession/verification, generic outward error) alongside the existing
    email-change test line (updated to mention "conflicted" among the rejected token states)
  - "Definition of Ready" and "Definition of Done": updated the email-change lines to mention the
    durable CONFLICTED terminal state
```

No migration, model, controller, route, UI, or dependency file was touched.

## Regression Check

Previously resolved findings were NOT reopened:

```
IMP002-RDY-B01   unchanged
IMP002-RDY-M01   extended only to add the missing conflict state (this pass's own target); its
                 prior resolution (request-versioned EmailChangeRequest replacing
                 users.pending_email, mandatory supersession, exact token binding) is preserved
                 verbatim
IMP002-RDY-M02   unchanged (Invitation Boundary not touched)
IMP002-RDY-M03   unchanged (Authentication Assurance not touched)
IMP002-RDY-M04   unchanged (MFA replay contract not touched)
IMP002-RDY-M05   unchanged (Account/Security Model, rate-limiter separation not touched)
IMP002-RDY-M06   unchanged (First Super Admin Bootstrap not touched)
IMP002-RDY-m01   unchanged (Remember-Me (Deferred) not touched)
IMP002-RDY-m02   unchanged (Password Change — Session Consequences not touched)
```

Regression of previously resolved findings: NONE.

## Human Decisions

```
Q21 = FINAL / LOCKED (unchanged)
Q22 = FINAL / LOCKED (unchanged)
Q23 = FINAL / LOCKED (unchanged)
Q24 = FINAL / LOCKED (unchanged)
Q25 = FINAL / LOCKED (unchanged)
```

Open Human Decisions: NONE. No new Human Decision was required or introduced by this pass — the
conflict-state design is a data-integrity/concurrency mechanic of an already-scoped concern (Q21),
not a business or architecture decision.

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
IMP-002 Specification:        READY FOR CODEX FINAL TARGETED READINESS RE-AUDIT
IMP-002 Definition of Ready:   PASS
IMP-002 Implementation:       NOT AUTHORIZED
IMP-003:                       NOT AUTHORIZED
Coding Performed:               NO
```
