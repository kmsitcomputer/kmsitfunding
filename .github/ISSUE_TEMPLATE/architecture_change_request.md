---
name: Architecture Change Request
about: Propose a change to a locked architecture decision (ACR-###)
title: "ACR-### — "
labels: architecture-change
---

This GitHub issue template is an intake interface only. It MUST NOT override or weaken the
canonical Change Control or Architecture Change Request governance defined in
[docs/00-governance/CHANGE-CONTROL.md](../../docs/00-governance/CHANGE-CONTROL.md) and
[docs/decisions/ARCHITECTURE-CHANGE-REQUEST-TEMPLATE.md](../../docs/decisions/ARCHITECTURE-CHANGE-REQUEST-TEMPLATE.md).
If this template and those documents ever appear to disagree, the documents under `docs/`
govern.

## Problem

Why existing architecture cannot satisfy implementation.

## Existing Rule

What locked decision is affected.

## Proposed Change

New behavior.

## Alternatives

Other approaches evaluated.

## Impact

Requirements / Database / Security / API / Finance / Testing / Deployment

## Migration Impact

If applicable.

## Change Classification

Select one:

- [ ] IMPLEMENTATION
- [ ] ARCHITECTURE
- [ ] LOCKED ARCHITECTURE
- [ ] BUSINESS RULE
- [ ] SECURITY BOUNDARY
- [ ] FINANCIAL SEMANTICS
- [ ] HUMAN DECISION CHANGE
- [ ] OTHER

## Human Decision Requirement

Human Decision Required is automatically **YES** when the Change Classification above is any of:

- LOCKED ARCHITECTURE
- BUSINESS RULE
- SECURITY BOUNDARY
- FINANCIAL SEMANTICS
- HUMAN DECISION CHANGE

For those classifications this value MUST NOT be changed to NO — do not present or select a free
YES/NO choice; it is fixed at YES.

Only for **IMPLEMENTATION** or **OTHER** classification does this field take a free value,
decided per the canonical change-control policy in
[docs/00-governance/CHANGE-CONTROL.md](../../docs/00-governance/CHANGE-CONTROL.md) — when in
doubt, set it to YES.

Human Decision Required (fill in only if classification is IMPLEMENTATION or OTHER; otherwise
YES, fixed): YES / NO

## Recommendation

AI-drafted recommendation only. This section does not constitute approval — see "Human Approval
Record" below.

AI agents may analyze, recommend, draft, and review this request. AI agents MUST NOT approve
changes to locked architecture, Human Decisions, business rules, security boundaries, or
financial semantics.

## Human Approval Record

Do not fill these fields with invented approval data — leave them blank/PENDING until a real
Human approval occurs.

```
Status: PENDING / APPROVED / REJECTED

Human Approver:
Decision Date:
Evidence / Reference:
Approved Baseline / Commit:
```
