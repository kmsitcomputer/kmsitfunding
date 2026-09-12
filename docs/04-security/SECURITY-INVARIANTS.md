# Security Invariants

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy
Semantic Change:        NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization).

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline, layered on
[docs/04-security/SECURITY-ARCHITECTURE.md](SECURITY-ARCHITECTURE.md).

## Invariants

```
A security decision is never delegated to the client/frontend.
A sensitive file is never reachable through a permanent public URL.
A webhook/callback is never trusted without verification and replay protection.
A System Principal action and an Integration Principal action are never conflated.
A token's effective authority is never wider than the intersection of the token grant and its
  principal's own authority.
Duties that must be separated for financial integrity are never collapsed into one actor/role
  without an explicit, approved exception.
```

These invariants apply during every later stage, including IMP-002 (Identity + Authentication)
and IMP-003 (RBAC + Scope + Business Authority). IMP-001 implements no authentication or RBAC —
see [AGENTS.md](../../AGENTS.md) "Security".
