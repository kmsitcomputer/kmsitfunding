# Modern Digital Philanthropy Platform

Laravel modular monolith for a single primary organization, covering campaigns, donations,
payments, ledger accounting, commission, fundraisers, partners, and beneficiary distribution.

## Status

```
Architecture Baseline:    Materialized under docs/01-requirements/ through docs/05-rbac/ as a
                          repository copy of the approved external baseline. See AGENTS.md and
                          docs/00-governance/DOCUMENT-AUTHORITY.md.
Implementation Program:   IN PROGRESS
IMPLEMENTATION 00:        FINAL / LOCKED — Repository Bootstrap & Governance
IMPLEMENTATION 01:        FINAL / LOCKED — Core Project Foundation
IMPLEMENTATION 02:        FINAL / LOCKED — Identity + Authentication
IMPLEMENTATION 03:        FINAL / LOCKED — RBAC + Scope + Business Authority
IMPLEMENTATION 04:        FINAL / LOCKED — Audit + Governance Foundation
IMPLEMENTATION 05:        SPECIFICATION IN PROGRESS — CMS (Content Experience)
                          Document state: remediation pass 2 applied, pending Codex re-audit.
                          Implementation NOT authorized yet.
Next Planned Stage:       IMPLEMENTATION 05 — CMS (spec authorization), then IMPLEMENTATION 06 —
                          Theme Engine
```

The architecture decisions listed in [AGENTS.md](AGENTS.md) under "Locked Decisions" are treated
as locked input to implementation and require an approved ACR/ADR to change. The authoritative
Level 1-3 baseline (Human Decision Register, Master Requirements, Master Architecture, Database
Architecture, Security Architecture, RBAC Architecture) has been materialized into this repository
under `docs/01-requirements/` through `docs/05-rbac/` — see
[docs/audits/IMP-001-READINESS-REMEDIATION.md](docs/audits/IMP-001-READINESS-REMEDIATION.md) and
[docs/audits/IMP-001-B01-MATERIALIZATION-PASS-2.md](docs/audits/IMP-001-B01-MATERIALIZATION-PASS-2.md)
for provenance. A Laravel 13 + Vue 3 + Inertia 3 + TypeScript + Tailwind 4 + Vite application
foundation exists (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`,
`routes/`, `tests/`) — see
[docs/implementation/IMP-001-core-project-foundation.md](docs/implementation/IMP-001-core-project-foundation.md)
and [docs/audits/IMP-001-FINALIZATION.md](docs/audits/IMP-001-FINALIZATION.md). Identity +
Authentication (IMP-002), RBAC + Scope + Business Authority (IMP-003), and Audit + Governance
Foundation (IMP-004) have each been implemented, remediated, and finalized on `master` — see
[docs/audits/IMP-002-FINALIZATION.md](docs/audits/IMP-002-FINALIZATION.md),
[docs/audits/IMP-003-FINALIZATION.md](docs/audits/IMP-003-FINALIZATION.md), and
[docs/audits/IMP-004-FINALIZATION.md](docs/audits/IMP-004-FINALIZATION.md). No business-domain
feature (donation, payment, ledger, commission, partner, beneficiary, CMS, theme, or public API
business logic) has been implemented yet — those remain out of scope until IMP-005 and later
stages. The CMS specification (IMP-005) is currently in remediation on branch `spec/005-cms` —
see [docs/implementation/IMP-005-cms.md](docs/implementation/IMP-005-cms.md); it is
documentation-only and does not itself authorize implementation. IMP-000 through IMP-004 have
each passed their final gate with recorded Human approval; that approval closes those stages
only — it does not by itself authorize IMP-005, which still needs its own Codex re-audit pass and
a separate Human implementation-authorization act before implementation work begins.

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
IMP-002 Identity + Authentication           FINAL / LOCKED
IMP-003 RBAC + Scope + Business Authority   FINAL / LOCKED
IMP-004 Audit + Governance Foundation       FINAL / LOCKED
IMP-005 CMS                                 <- next (specification in remediation, not authorized)
IMP-006 Theme Engine
IMP-007 Campaign + Program + Fund
IMP-008 Donation
IMP-009 Payment Hub
IMP-010 Ledger Foundation
IMP-011 Financial Consequence Posting
...
```

The Laravel application foundation, Identity/Authentication, RBAC/Scope/Business Authority, and
the Audit/Governance Foundation (IMP-001 through IMP-004) all exist on `master`. No business-domain
code (CMS, theme, campaign, donation, payment, ledger, or later stages) exists yet — IMPLEMENTATION
05 (CMS) is the next stage; its specification is still under remediation and has not been
authorized for implementation.
