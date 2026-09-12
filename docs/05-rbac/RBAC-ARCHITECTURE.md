# Authentication + RBAC Architecture

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy (authorization evaluation model only)
Semantic Change:        NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization).

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline.

## Canonical Authorization Evaluation

Effective access requires ALL of the following to hold — this is an AND-chain, not a
role-only check:

```
Authenticated
AND Permission
AND Applicable Data Scope
AND Ownership / Subject Access Rule
AND Required Business Authority
AND Valid Resource State
AND Required Approval State
AND Required Authentication Assurance
AND No Security Restriction
```

Default is DENY — see [AGENTS.md](../../AGENTS.md) "Security".

## Preserved Distinctions

```
Role                != Permission
Permission          != Scope
Permission          != Authority
Authority           != Approval Decision
Object Access       != Full Field Disclosure
Super Admin         != automatic Financial Authority
```

## Related Detailed Models

This document materializes the authorization *evaluation model* (the AND-chain and the preserved
distinctions above). The concrete detailed models referenced by three of its AND-terms were
materialized separately during IMP-001 B01 Materialization Pass 2:

- "Applicable Data Scope" — see [docs/05-rbac/DATA-SCOPE-MODEL.md](DATA-SCOPE-MODEL.md);
- "Required Business Authority" — see
  [docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md](BUSINESS-AUTHORITY-MODEL.md);
- "Required Authentication Assurance" — see
  [docs/05-rbac/AUTHENTICATION-ASSURANCE.md](AUTHENTICATION-ASSURANCE.md).

This document does not duplicate their content — see those files directly.

No RBAC implementation exists yet — see
[docs/implementation/IMP-001-core-project-foundation.md](../implementation/IMP-001-core-project-foundation.md)
"§24 RBAC BOUNDARY" (deferred to IMP-003).
