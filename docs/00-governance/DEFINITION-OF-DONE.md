# Definition of Ready / Done, Testing Gate, Review Severity

## Definition of Ready

A task is READY only if:

```
[ ] Objective clear
[ ] Scope clear
[ ] Out-of-scope clear
[ ] Architecture known
[ ] Business rules known
[ ] Security impact known
[ ] DB impact known
[ ] Acceptance criteria known
[ ] No unresolved Human Decision
```

Otherwise: TASK NOT READY.

## Definition of Done

A task is DONE only if:

```
[ ] Implementation matches spec
[ ] Tests added
[ ] Tests pass
[ ] Negative-path tests added where appropriate
[ ] Authorization tested
[ ] Financial invariants tested if applicable
[ ] No debug code
[ ] No secrets committed
[ ] Documentation updated
[ ] Architecture unaffected or approved ADR exists
[ ] Independent review completed
[ ] Review MAJOR/BLOCKER findings resolved
[ ] Acceptance criteria satisfied
```

## Testing Gate

No merge based only on "looks correct." Minimum automated testing depends on the task.

### Test Categories

```
Unit
Feature
Integration
Authorization
Security
Financial Integrity
Regression
```

Financial features must include failure and duplicate-processing tests.

### Mandatory Financial Tests (where relevant)

```
duplicate provider event
duplicate job retry
duplicate reconciliation
duplicate financial consequence
double withdrawal
partial withdrawal
refund after Commission
provider callback out-of-order
payment retry
failed payout
ledger reversal
```

### Mandatory RBAC Tests (at least)

```
authorized user -> ALLOW
unauthenticated -> DENY
wrong permission -> DENY
correct permission wrong scope -> DENY
wrong ownership -> DENY
missing authority -> DENY
wrong resource state -> DENY
cross-context attempt -> DENY
```

## Review Severity

```
BLOCKER    Security, financial integrity, data loss, fundamental architecture violation.
MAJOR      Serious correctness or architecture defect.
MINOR      Non-critical correctness/maintainability problem.
EDITORIAL  Documentation/style only.
```

## Merge Gate

Canonical rule:

```
A task, branch, or pull request MUST NOT merge while any unresolved BLOCKER or MAJOR finding
exists against the currently approved baseline.
```

A Human Decision or ADR is not a waiver for defective implementation. A Human Decision/ADR
changes the approved requirement or architecture baseline — it does not convert an unresolved
defect into an acceptable defect measured against that baseline.

If a finding reveals that the approved requirement or architecture itself should change:

```
1. stop implementation;
2. process the requirement/architecture change (ACR);
3. obtain required Human approval;
4. update the authoritative baseline (ADR);
5. patch implementation to conform to the new baseline;
6. perform independent re-review;
7. close the original finding against the approved baseline.
```

Only then may merge proceed. See
[CHANGE-CONTROL.md](CHANGE-CONTROL.md) for the full ACR/ADR flow.

## Frontend Progress Checkpoint (mandatory, IMP-010 onward)

**Human Decision (GOV-FRONTEND-001).** Starting with IMP-010 and continuing through IMP-030,
every applicable IMP's Definition of Done additionally requires a **Frontend Progress
Checkpoint**, reported alongside the existing Done checklist. This section does not reopen or
alter the Definition of Done for IMP-000 through IMP-009 — it applies prospectively only.

### Purpose

Backend implementation must produce visible, minimum, functional product integration as each IMP
lands — not a deferred, big-bang frontend pass at the end of the program. The checkpoint is:

```
BACKEND IMPLEMENTATION + VISIBLE PRODUCT INTEGRATION + MINIMUM FUNCTIONAL UI
  + RESPONSIVE CHECK + EVIDENCE
```

Visual refinement may be deferred. Functional integration must not be silently deferred when the
current IMP already owns the required backend capability.

### Checkpoint Report (required fields)

```
[ ] FRONTEND STATUS
[ ] HOME STATUS
[ ] HEADER
[ ] FOOTER
[ ] NEW USER-FACING UI
[ ] NEW ADMIN UI
[ ] MOBILE CHECK
[ ] DESKTOP CHECK
[ ] BUILD STATUS
[ ] TYPE-CHECK STATUS
[ ] SCREENSHOT / EVIDENCE
[ ] DEFERRED UI
```

### Global Requirements

1. The application still renders successfully.
2. The public Home page remains functional.
3. Header and Footer remain functional and progressively reusable.
4. Public frontend development must remain compatible with the IMP-006 Theme Engine — this
   checkpoint does not create a second, competing presentation authority. UI visibility is never a
   substitute for the Theme Engine's own authority over public presentation, and this checkpoint
   MUST NOT mandate hard-coded global navigation/branding that bypasses theme configuration.
5. Public / Donor / Fundraiser / Partner presentation remains themeable where required by locked
   architecture.
6. Admin / Super Admin remains a fixed backoffice interface (not theme-controlled, per the locked
   decision in [AGENTS.md](../../AGENTS.md)).
7. Each IMP progressively exposes user-facing functionality owned by that IMP when such
   functionality is appropriate.
8. Each IMP progressively exposes minimum Admin/Super Admin UI for administrative capabilities
   owned by that IMP when appropriate. A backend route or capability that ships with no
   discoverable Admin UI is a frontend integration gap unless intentionally non-navigable by
   specification.
9. Mobile layout must be checked.
10. Desktop layout must be checked.
11. Build must be checked where frontend assets are affected.
12. Type-check must be checked where TypeScript/Vue code is affected.
13. Evidence must be recorded.
14. Screenshots should be captured where meaningful UI changed.

### No Fake Business Functionality

Future-domain UI MUST NOT fabricate transactions, donations, payments, ledger balances,
withdrawals, refunds, distributions, financial values, business state, or approval state before
the owning IMP implements those capabilities. Presentation placeholders may be used only when they
are clearly identified as presentation-only, do not persist fake business state, do not imply
implemented backend behavior, and do not bypass future domain ownership.

### Financial Domains

For financial IMPs, UI may only display canonical backend state — it must never become an
alternate financial source of truth. The locked flow is unaffected by this checkpoint:

```
Business Event -> Domain Validation -> Business State Transition
  -> Authorized Financial Consequence -> Registry -> Ledger Posting -> Journal
    -> Double-Entry Entries
```

Donation != Payment, Payment != Ledger, and Reconciliation != Ledger remain locked (see
[AGENTS.md](../../AGENTS.md)).

### Security

The checkpoint MUST NOT weaken authentication, RBAC, Policies/Gates, scope, ownership, business
authority, approval state, authentication assurance, or security restrictions. Hidden UI is not a
security control; UI visibility is not authorization. Policies/RBAC/scope/business authority
remain authoritative regardless of what the UI shows or hides.

### Shared Hosting

Frontend progress must remain compatible with the locked shared-hosting-compatible production
constraint. This checkpoint does not introduce a mandatory Redis server, Supervisor, PM2,
WebSockets, a Node runtime in production, or Docker. Compiled frontend assets remain acceptable.

### Historical Scope

IMP-000 through IMP-009 are not reopened, not rewritten, and their locked implementation semantics
are not retroactively altered by this checkpoint.

### Post-IMP-009 Frontend Integration Checkpoint (one-time, optional)

A one-time **POST-IMP-009 FRONTEND INTEGRATION CHECKPOINT** may be performed before IMP-010
begins, to bring already-implemented capabilities into a coherent visible frontend baseline
without changing their business semantics. It may address known integration gaps such as reusable
public Header/Footer composition, Home integration, discoverable Admin navigation for
already-implemented domains, responsive integration, and frontend evidence. It MUST NOT change
Donation semantics, Payment semantics, RBAC semantics, or Theme Engine semantics; MUST NOT
introduce Ledger behavior; and MUST NOT implement IMP-010 early.

## Small Task Exception

Tiny implementation fixes do not require the full implementation-specification template (see
`docs/implementation/`). But any task involving finance, Payment, Ledger, Commission, RBAC,
Privacy, sensitive files, Partner scope, beneficiary, or API security requires an explicit
written specification regardless of size.
