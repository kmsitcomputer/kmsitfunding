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

## Explicit Non-Scope for This Materialization

This document materializes the authorization *evaluation model* (the AND-chain and the preserved
distinctions above) as supplied. It does not materialize separate, detailed models for:

- Data Scope (the concrete scope taxonomy referenced as "Applicable Data Scope" above);
- Business Authority (the concrete authority catalog referenced as "Required Business Authority"
  above);
- Authentication Assurance (the concrete assurance-level model referenced as "Required
  Authentication Assurance" above).

That detailed content was not supplied for materialization in this pass. Per the Human
authorization's instruction to STOP FOR THAT ARTIFACT rather than reconstruct missing Level 1-3
authority from guesses, no `DATA-SCOPE-MODEL.md`, `BUSINESS-AUTHORITY-MODEL.md`, or
`AUTHENTICATION-ASSURANCE.md` file has been created. See
[docs/audits/IMP-001-READINESS-REMEDIATION.md](../audits/IMP-001-READINESS-REMEDIATION.md) for the
record of which artifacts were and were not materialized. A future stage (expected: IMP-003 —
RBAC + Scope + Business Authority) must source that detail from the Human Authority or the
external approved baseline before implementing scope/authority/assurance logic — it must not be
invented at implementation time.

No RBAC implementation exists yet — see
[docs/implementation/IMP-001-core-project-foundation.md](../implementation/IMP-001-core-project-foundation.md)
"§24 RBAC BOUNDARY" (deferred to IMP-003).
