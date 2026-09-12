# Data Scope Model

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
[docs/05-rbac/RBAC-ARCHITECTURE.md](RBAC-ARCHITECTURE.md) ("Applicable Data Scope" referenced
there without a separate scope taxonomy).

## Scope Taxonomy

```
OWN
FUNDRAISER
PARTNER
CAMPAIGN
PROGRAM
FUND
BENEFICIARY_CASE
ASSIGNED_WORK
ORGANIZATION
GLOBAL_PLATFORM
```

No further subdivision, numeric range, or configuration value for any of these scopes is defined
by this document — only the taxonomy itself is materialized here.

## Principles

```
Permission alone is insufficient.
Permission + applicable scope are required.
Scope containment is domain-aware.
Contexts from unrelated assignments MUST NOT be unioned automatically.
```

## Canonical Authorization Context

```
Principal
+ Requested Capability
+ Target Resource
+ Applicable Subject Context
```

This context feeds the "Applicable Data Scope" and "Ownership / Subject Access Rule" terms of the
canonical authorization formula in
[docs/05-rbac/RBAC-ARCHITECTURE.md](RBAC-ARCHITECTURE.md) §"Canonical Authorization Evaluation".

## Ownership Examples

```
Donor          -> own Donation records
Fundraiser     -> own/scoped attribution and fundraiser operations
Beneficiary    -> own application/profile where permitted
Partner User   -> linked Partner resources only
Operational Staff -> assigned work / authorized organizational scope
```

Cross-context access is denied unless explicitly authorized — this is a restatement of "default
DENY" (see [AGENTS.md](../../AGENTS.md) "Security") applied specifically to scope evaluation, not
a separate exception mechanism.
