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

Cannot merge with `BLOCKER > 0` or `MAJOR > 0` unless explicitly accepted through a Human
Decision / ADR.

## Small Task Exception

Tiny implementation fixes do not require the full implementation-specification template (see
`docs/implementation/`). But any task involving finance, Payment, Ledger, Commission, RBAC,
Privacy, sensitive files, Partner scope, beneficiary, or API security requires an explicit
written specification regardless of size.
