# Master Architecture

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy (minimum locked content only)
Semantic Change:        NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization).

This document captures only the **minimum locked architectural content** explicitly supplied for
materialization. It is not a claim that this is the complete external Master Architecture
document — additional detail beyond what is stated below has not been supplied to this
repository and MUST NOT be invented. If a future task needs architectural detail not present
here, that detail must be sourced from the Human Authority or the external approved baseline,
not guessed.

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline.

## Locked Architecture Principles

```
Single Organization
Centralized Settlement
Single Laravel Application
Single Root Domain
Modular Monolith
Same-origin /api/v1
Web/API shared business logic (no independent business implementations per surface)
Partner != tenant
Partner != settlement owner
Theme = presentation only
Admin/Super Admin = fixed backoffice (not theme-controlled)
Shared-hosting-compatible production (mandatory)
```

## Application Architecture Shape

```
Single Organization
        v
Single Laravel Application
        v
Modular Monolith
        v
Single Root Domain
        |
        +-- Web
        +-- Admin
        +-- Portals
        +-- /api/v1/*
```

## Module Ownership

A full Module Ownership document (detailed semantic ownership boundaries per module/domain) was
not supplied for materialization in this pass. Per the Human authorization's instruction to STOP
FOR THAT ARTIFACT rather than reconstruct missing Level 1-3 authority from guesses, no
`MODULE-OWNERSHIP.md` file has been created — see
[docs/audits/IMP-001-READINESS-REMEDIATION.md](../audits/IMP-001-READINESS-REMEDIATION.md) for the
record of which artifacts were and were not materialized. Do not infer module ownership
boundaries from this document beyond the shared Web/API logic principle stated above.

## Related Materialized Documents

- [docs/03-database/DATABASE-ARCHITECTURE.md](../03-database/DATABASE-ARCHITECTURE.md)
- [docs/04-security/SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md)
- [docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md)
- [docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](FINANCIAL-POSTING-BOUNDARY.md)
