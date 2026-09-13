# IMP-XXX — Title

## Status

READY / IN PROGRESS / REVIEW / BLOCKED / PASS

## Implementation Ownership

Per [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) —
no implicit/default model substitution is permitted; if the assigned model/owner is unavailable,
stop and report rather than substituting silently.

**First determine the Stage Type — this decides which block below applies. Never fill both.**

```
Stage Type:   NORMAL IMPLEMENTATION STAGE  /  AUDIT-ONLY STAGE (IMP-030 only, see GOV-MM-001)
```

### If Stage Type = NORMAL IMPLEMENTATION STAGE (every IMP except IMP-030)

```
Primary Implementation Owner:
Primary Model:
Exact Model ID:
Execution Environment:
Specialist Reviewer(s):
Independent Formal Reviewer:   Codex (default, unless an explicitly approved exception exists)
Human Approval Authority:      Human
Concurrent Editing:            PROHIBITED
```

If this IMP is one of the Mission-Critical Claude Stages (see MULTI-MODEL-OWNERSHIP.md), also
record the GOV-MM-002 Claude Per-IMP Model Binding before implementation begins — implementation
may not proceed unless Binding Status below is `BOUND`:

```
Execution Environment:               Claude Code
Actual Claude Model Name:
Exact Model Identifier:
Resolution/Verification Date:
Human Model Binding Approval:
Binding Status:                      UNBOUND / MODEL RESOLVED / AWAITING HUMAN MODEL APPROVAL /
                                      BOUND
Model Change Requests against this binding: none / list
```

### If Stage Type = AUDIT-ONLY STAGE (IMP-030 only)

Use ONLY this block. Do not also fill the "NORMAL IMPLEMENTATION STAGE" block above — IMP-030 has
no Primary Implementation Owner, and `Independent Formal Reviewer: Codex` must never be recorded
for IMP-030 (Codex cannot independently review its own audit — see GOV-MM-001).

```
Primary Audit Owner:                Codex
Independent Formal Reviewer:        N/A — audit-only exception, see GOV-MM-001
Findings returned to:               <Primary Implementation Owner of each affected prior IMP>
Final Approval Authority:           Human Final System Approval (mandatory; a Codex PASS on the
                                     final audit does not by itself satisfy this)
```

## Objective

What must be achieved.

## Source Requirements

References to Master Requirements.

## Architecture References

Relevant locked architecture.

## Scope

What is included.

## Out of Scope

What must not be implemented.

## Affected Domains

Modules involved.

## Business Rules

Authoritative rules.

## Security Requirements

Authentication, authorization, scope, sensitive data.

## Database Impact

Schema/data implications.

## API Impact

Endpoints/contracts if applicable.

## UI Impact

Relevant frontend behavior.

## Financial Impact

Ledger/Payment/Commission etc.

## Idempotency

Required protections.

## Concurrency

Required protections.

## Error Handling

Expected failure behavior.

## Tests Required

Unit / feature / integration / security / financial.

## Acceptance Criteria

Exact pass conditions.

## Forbidden Changes

Architecture decisions that must not change.

## External Verification

Anything deliberately deferred.

## Definition of Done

Checklist — see [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md).
