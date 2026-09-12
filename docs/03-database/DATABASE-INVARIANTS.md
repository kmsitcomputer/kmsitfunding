# Database Invariants

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
[docs/03-database/DATABASE-ARCHITECTURE.md](DATABASE-ARCHITECTURE.md).

## Invariants

```
Policy/version historical reproducibility
Financial idempotency
Immutable financial history
```

These invariants apply in addition to, and do not relax, the identity/monetary/referential-
integrity conventions in DATABASE-ARCHITECTURE.md. They govern later financial-domain schema work
(donation, payment, ledger, commission, etc.), none of which is implemented in IMP-001 — see
[AGENTS.md](../../AGENTS.md) "Database" and "Financial Rules", and
[docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md).
