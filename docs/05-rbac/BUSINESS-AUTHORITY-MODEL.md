# Business Authority Model

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy
Semantic Change:        NONE
Authority:               Existing locked architecture / requirement program.
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 B01 Materialization Pass 2, under
the same Human authorization that governed Pass 1.

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline, closing the gap previously noted in
[docs/05-rbac/RBAC-ARCHITECTURE.md](RBAC-ARCHITECTURE.md) ("Required Business Authority"
referenced there without a separate authority catalog).

## Preserved Distinctions

```
Role != Permission
Permission != Scope
Permission != Authority
Authority != Approval Decision
```

## Conceptual Structure

```
Authority Type
        v
Authority Assignment
        +-- Principal
        +-- Scope
        +-- Effective Dates
        +-- Status
```

An Authority Assignment binds an Authority Type to a Principal, within a Scope (see
[docs/05-rbac/DATA-SCOPE-MODEL.md](DATA-SCOPE-MODEL.md)), for a bounded effective period, with an
explicit status (e.g. active/revoked) — it is not implied by role membership.

## Approval Candidate Resolution

An approval policy requires an Authority Type. Resolving whether a given principal may act on a
given approval step requires ALL of:

```
active authority assignment
AND permission
AND scope
AND authentication assurance
AND assigned approval step
AND resource state
```

This is a specialization of the canonical authorization formula in
[docs/05-rbac/RBAC-ARCHITECTURE.md](RBAC-ARCHITECTURE.md) §"Canonical Authorization Evaluation",
applied to the Approval module (see
[docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §12).

## Authority Types

The following Authority Types are approved. No approval threshold, limit, or routing rule for any
of them is defined by this document — only their existence and the rule that they are not
implied by ordinary roles:

```
financial_approver
refund_approver
withdrawal_approver
distribution_approver
zakat_authority
partner_verifier
beneficiary_verifier
```

These are NOT automatically implied by ordinary roles. In particular:

```
Super Admin              != automatic Financial Approver
Finance Operator         != Financial Approver
Beneficiary Operator     != Beneficiary Verifier unless assigned
Permission "approval.decide" alone is insufficient
```
