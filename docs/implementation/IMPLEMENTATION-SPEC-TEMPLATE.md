# IMP-XXX — Title

## Status

READY / IN PROGRESS / REVIEW / BLOCKED / PASS

## Implementation Ownership

Per [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) —
no implicit/default model substitution is permitted; if the assigned model/owner is unavailable,
stop and report rather than substituting silently.

**First determine the Stage Type — this decides which block below applies. Never fill more than
one.**

```
Stage Type:   NORMAL IMPLEMENTATION STAGE (historical, pre-V3)  /
              V3 PIPELINE STAGE (IMP-008 forward, see MULTI-MODEL-OWNERSHIP.md "Amendment V3")  /
              AUDIT-ONLY STAGE (IMP-030 only, see GOV-MM-001)
```

### If Stage Type = NORMAL IMPLEMENTATION STAGE (historical, pre-V3)

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

### If Stage Type = V3 PIPELINE STAGE (IMP-008 forward)

Applies to **every** IMP-008-forward stage, including the Mission-Critical Claude Stages — V3's
pipeline governs those too; they are not excluded from it (see MULTI-MODEL-OWNERSHIP.md
"Precedence Over the Legacy Matrix," HD-V3-R2-03). Only IMP-030 uses the separate AUDIT-ONLY STAGE
block below, because it implements nothing. Compact — do not expand this into a transcript; each
field is a pointer to durable evidence, not the evidence itself.

```
V3 Claude Lead Architect Identity Binding (see MULTI-MODEL-OWNERSHIP.md "V3 Claude Lead Architect
Identity Binding" — distinct from GOV-MM-002, required for EVERY IMP-008-forward stage before
Human Spec Approval, regardless of Mission-Critical status):
  Role:                    Claude Lead Architect / Specification Owner
  Execution Environment:   Claude Code
  Display Model:
  Exact Model Identifier:
  Verification Method:
  Verification Status:     VERIFIED / EXTERNALLY HUMAN-CONFIRMED
  Specification Revision:

FAIL CLOSED: if this identity cannot be established, do not submit for Human Spec Approval below.

Human Spec Approval:                     APPROVED / NOT APPROVED
Approval Statement:                      <exact statement or durable reference — never fabricated>
Approval Scope:                          <this IMP ID> + <specification revision above>
Approval Evidence:                       <repository reference>

Qwen Recon Binding:                      qwen/qwen3.7-flash (VERIFIED — see
                                          MULTI-MODEL-OWNERSHIP.md "Model Binding Contract").
                                          MUST NOT run before or concurrently with Human Spec
                                          Approval above (HD-V3-R2-01).
RECON Evidence:                          <docs/ai-handoff/IMP-XXX/RECON.md reference>

Implementation Write Owner:              Muse Spark 1.3 Contributor
                                          (meta/muse-spark-1.3-contributor, VERIFIED) — DEFAULT,
                                          every stage except Mission-Critical Claude Stages
                                          / Claude Code — MISSION-CRITICAL CLAUDE STAGES ONLY;
                                          also record the GOV-MM-002 Claude Per-IMP Model Binding
                                          block from the NORMAL IMPLEMENTATION STAGE section above
                                          in addition to this V3 block when this applies
Implementation Commit/Diff:              <commit(s)>
Test Evidence:                           <summary + reference>

DeepSeek Independent Review Binding:     deepseek/deepseek-v4.1-flash (VERIFIED)
Findings:                                <reference>
Remediation Owner:                       Implementation Write Owner above (default) / Claude
                                          (architecture findings only, per Finding Routing)
Remediation Evidence:                    <commit(s)/reference>
Full Regression:                         <result + reference>

Codex Closure Audit:                     <PASS / FAIL + reference>
Human Stage Gate:                        <status>
Finalization Status:                     <READY / IN PROGRESS / REVIEW / BLOCKED / PASS>
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
