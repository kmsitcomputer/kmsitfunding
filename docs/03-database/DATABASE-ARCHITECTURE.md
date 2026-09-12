# Database Architecture

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy (locked baseline principles only)
Semantic Change:        NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization).

This document captures the locked database baseline principles as supplied. It is not a claim
that this is the complete external Database Architecture document (e.g. no full schema/ERD was
supplied) — further schema-level detail must come from the Human Authority or external approved
baseline when a later stage requires it, not from guessing.

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline.

## Target Platform

```
MySQL 8.x
```

## Identity Conventions

```
BIGINT unsigned      internal primary/foreign keys
ULID                 public/API-safe identifiers, where specified
```

## Monetary Value Rule

```
DECIMAL for all monetary values
Never FLOAT/DOUBLE for money
```

## Referential Integrity

```
Strong referential integrity
```

See [docs/03-database/DATABASE-INVARIANTS.md](DATABASE-INVARIANTS.md) for the financial and
historical invariants layered on top of this baseline.
