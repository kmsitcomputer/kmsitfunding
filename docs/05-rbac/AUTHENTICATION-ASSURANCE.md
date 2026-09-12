# Authentication Assurance

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
[docs/05-rbac/RBAC-ARCHITECTURE.md](RBAC-ARCHITECTURE.md) ("Required Authentication Assurance")
and [docs/04-security/SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md)
("privileged authentication assurance") referenced there without a separate assurance model.

## Assurance States

```
STANDARD
ELEVATED
```

### STANDARD

A normal authenticated session/token meeting ordinary security requirements.

### ELEVATED

A fresh/re-authenticated or otherwise stronger assurance, required for privileged/high-risk
operations.

No specific MFA vendor, mechanism, or step-up protocol is defined by this document — only the
existence of these two conceptual states and the rule that certain operations require ELEVATED.
Do not invent a specific mechanism when implementing; that belongs to the stage that implements
authentication (IMP-002) and must be sourced from the Human Authority or external approved
baseline at that time.

## Where Elevated Assurance Is Required

Elevated assurance is required conceptually for high-risk operations such as:

```
critical financial actions
privileged approvals
security-sensitive configuration changes
critical authority assignment/change
sensitive exports where policy requires
other operations classified high-risk by security policy
```

The exact list of which specific operations are "critical" / "high-risk" is not enumerated
exhaustively by this document — that classification belongs to security policy at implementation
time, applying the categories above rather than inventing new ones.

## Relationship to Other Materialized Documents

- [docs/05-rbac/RBAC-ARCHITECTURE.md](RBAC-ARCHITECTURE.md) §"Canonical Authorization Evaluation"
  — "Required Authentication Assurance" is one AND-term of the canonical formula.
- [docs/04-security/SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md) —
  "Privileged authentication assurance" principle.
- [docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md](BUSINESS-AUTHORITY-MODEL.md) — Approval Candidate
  Resolution includes an authentication assurance condition.
