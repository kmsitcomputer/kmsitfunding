# Modern Digital Philanthropy Platform

Laravel modular monolith for a single primary organization, covering campaigns, donations,
payments, ledger accounting, commission, fundraisers, partners, and beneficiary distribution.

## Status

```
Architecture Baseline:    Externally established / treated as locked input to implementation.
                          See AGENTS.md and authoritative architecture documents when present.
Implementation Program:   IN PROGRESS
IMPLEMENTATION 00:        FINAL / LOCKED (Human-approved 2026-09-12)
Next Planned Stage:       IMPLEMENTATION 01 — Core Project Foundation
IMP-001 Authorization:    PENDING SPECIFICATION / DEFINITION OF READY
```

The architecture decisions listed in [AGENTS.md](AGENTS.md) under "Locked Decisions" are treated
as locked input to implementation and require an approved ACR/ADR to change. This repository does
not yet contain the authoritative architecture/requirements documents themselves (docs/02-
architecture/, docs/03-database/, etc. are currently scaffolded but not authored). No business
feature (donation, payment, ledger, commission, partner, beneficiary, CMS, theme, or API business
logic) has been implemented. IMP-000 (repository bootstrap and governance) has passed its final
gate with recorded Human approval — see
[docs/audits/IMP-000-STAGE-GATE.md](docs/audits/IMP-000-STAGE-GATE.md) and
[docs/audits/IMP-000-INDEPENDENT-AUDIT.md](docs/audits/IMP-000-INDEPENDENT-AUDIT.md). That
approval closes IMP-000 only — it does not by itself authorize IMP-001, which still needs its own
implementation specification and Definition of Ready evaluation before work begins — see
[docs/implementation/IMP-000-repository-bootstrap.md](docs/implementation/IMP-000-repository-bootstrap.md).

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
IMP-000 Repository Bootstrap & Governance   <- current
IMP-001 Core Project Foundation
IMP-002 Identity + Authentication
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

No application code exists yet. IMPLEMENTATION 01 is the first stage where actual repository
coding begins.
