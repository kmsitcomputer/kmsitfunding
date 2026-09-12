# IMP-002 Targeted Readiness Remediation Pass 4

Task:
IMP-002 Targeted Readiness Remediation Pass 4

Branch:
`impl/002-identity-authentication`

Starting Commit:
`8ccd24f`

## Finding

```text
IMP002-RDY-P2-m01
```

Remaining Root Cause:
Conflict finalization protected against verified, superseded, cancelled, and conflicted states,
but omitted expiry. The earlier "one active request" definition also omitted
`conflicted_at IS NULL`.

## Resolution

```text
ACTIVE now requires:
  verified_at IS NULL
  superseded_at IS NULL
  cancelled_at IS NULL
  conflicted_at IS NULL
  now < expires_at

Conflict finalization may transition only ACTIVE -> CONFLICTED.
If expiry becomes effective before conflict finalization, EXPIRED is preserved and no conflict
marker is written.
Conflict-versus-expiry deterministic test coverage is required alongside conflict races against
cancellation, supersession, and verification.
```

The positive conflict path remains defined: when the request is still ACTIVE at finalization,
`conflicted_at` and controlled reason `TARGET_EMAIL_ALREADY_IN_USE` are written durably, the old
request becomes permanently non-promotable, and a new independent request may be created.

## Governance

```text
Human Decision:      NO
Architecture Change: NO
Implementation:      NO
Migration:           NO
Dependency Change:   NO
Documentation Only:  YES
```

## Regression Check

```text
Previous findings regressed: NONE
Q21-Q25 semantics changed:   NO
RBAC/business authority:     NOT INTRODUCED
Shared-hosting constraints:  UNCHANGED
```

## Status

```text
IMP002-RDY-P2-m01: RESOLVED
Open Human Decisions: NONE
Definition of Ready: PASS
Implementation: NOT AUTHORIZED
Merge: NOT AUTHORIZED
```
