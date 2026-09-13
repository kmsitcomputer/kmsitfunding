# Modern Digital Philanthropy Platform

Laravel modular monolith for a single primary organization, covering campaigns, donations,
payments, ledger accounting, commission, fundraisers, partners, and beneficiary distribution.

## Status

```
Architecture Baseline:    Materialized under docs/01-requirements/ through docs/05-rbac/ as a
                          repository copy of the approved external baseline. See AGENTS.md and
                          docs/00-governance/DOCUMENT-AUTHORITY.md.
Implementation Program:   IN PROGRESS
IMPLEMENTATION 00:        FINAL / LOCKED (Human-approved 2026-09-12)
IMPLEMENTATION 01:        FINAL / LOCKED (Core Project Foundation — merged to master)
Next Planned Stage:       IMPLEMENTATION 02 — Identity + Authentication
IMP-002 Authorization:    NOT AUTHORIZED — PENDING SPECIFICATION / DEFINITION OF READY
```

The architecture decisions listed in [AGENTS.md](AGENTS.md) under "Locked Decisions" are treated
as locked input to implementation and require an approved ACR/ADR to change. The authoritative
Level 1-3 baseline (Human Decision Register, Master Requirements, Master Architecture, Database
Architecture, Security Architecture, RBAC Architecture) has been materialized into this repository
under `docs/01-requirements/` through `docs/05-rbac/` — see
[docs/audits/IMP-001-READINESS-REMEDIATION.md](docs/audits/IMP-001-READINESS-REMEDIATION.md) and
[docs/audits/IMP-001-B01-MATERIALIZATION-PASS-2.md](docs/audits/IMP-001-B01-MATERIALIZATION-PASS-2.md)
for provenance. A Laravel 13 + Vue 3 + Inertia 3 + TypeScript + Tailwind 4 + Vite application
foundation now exists (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`,
`routes/`, `tests/`) — see
[docs/implementation/IMP-001-core-project-foundation.md](docs/implementation/IMP-001-core-project-foundation.md)
and [docs/audits/IMP-001-FINALIZATION.md](docs/audits/IMP-001-FINALIZATION.md). No business
feature (donation, payment, ledger, commission, partner, beneficiary, CMS, theme, or API business
logic) and no Identity/Authentication/RBAC has been implemented — those remain out of scope until
IMP-002/IMP-003 and later stages. IMP-000 and IMP-001 have each passed their final gate with
recorded Human approval; that approval closes those stages only — it does not by itself authorize
IMP-002, which still needs its own implementation specification and Definition of Ready evaluation
before work begins.

## Where to Start

- [AGENTS.md](AGENTS.md) — mandatory context for any coding agent working in this repository.
- [docs/00-governance/](docs/00-governance/) — implementation governance, workflow, branching,
  Definition of Done, change control, document authority.
- [docs/01-requirements/](docs/01-requirements/) — Master Requirements, Human Decision Register,
  traceability matrix.
- [docs/implementation/](docs/implementation/) — implementation specifications, one per task
  (`IMP-###`).

## Source of Truth Hierarchy

The canonical authority hierarchy is defined by
[docs/00-governance/DOCUMENT-AUTHORITY.md](docs/00-governance/DOCUMENT-AUTHORITY.md). This
section is a summary only — if it and DOCUMENT-AUTHORITY.md ever appear to disagree,
DOCUMENT-AUTHORITY.md governs the implementation process, subject to the higher-level Human
Decision, Requirement, and Locked Architecture authorities defined within it.

```
LEVEL 1 — Human Decisions

LEVEL 2 — Master Requirements

LEVEL 3 — Locked Architecture Baseline
           ├─ Master Architecture
           ├─ Database Architecture
           ├─ Security Architecture
           └─ Authentication / RBAC Architecture

LEVEL 4 — Approved Decisions / Amendments
           └─ Accepted ADR

LEVEL 5 — Implementation Specifications
           └─ IMP-XXX

LEVEL 6 — Governance / Operating Instructions
           ├─ AGENTS.md
           ├─ Governance documents (docs/00-governance/)
           └─ CONTRIBUTING.md

LEVEL 7 — Implementation Artifacts
           ├─ Code
           ├─ Tests
           └─ Generated operational artifacts
```

Higher authority always wins over lower authority (lower level number wins). Code and Tests are
both Level 7 Implementation Artifacts — neither has authority over the other.

## Implementation Sequence

```
IMP-000 Repository Bootstrap & Governance   FINAL / LOCKED
IMP-001 Core Project Foundation             FINAL / LOCKED
IMP-002 Identity + Authentication           <- next (not authorized)
IMP-003 RBAC + Scope + Business Authority
IMP-004 Audit + Governance Foundation
IMP-005 CMS
IMP-006 Theme Engine
IMP-007 Campaign + Program + Fund
IMP-008 Donation
IMP-009 Payment Hub
IMP-010 Ledger Foundation
IMP-011 Financial Consequence Posting
...
```

The Laravel application foundation (IMP-001) exists on `master`. No Identity/Authentication,
RBAC, or business-domain code exists yet — IMPLEMENTATION 02 is the next stage, pending its own
specification and authorization.
