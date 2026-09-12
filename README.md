# Modern Digital Philanthropy Platform

Laravel modular monolith for a single primary organization, covering campaigns, donations,
payments, ledger accounting, commission, fundraisers, partners, and beneficiary distribution.

## Status

```
Architecture Baseline:    Externally established / treated as locked input to implementation.
                          See AGENTS.md and authoritative architecture documents when present.
Implementation Program:   IN PROGRESS
Current Stage:            IMP-000 — remediation / governance gate pending
IMP-001:                  NOT AUTHORIZED
```

The architecture decisions listed in [AGENTS.md](AGENTS.md) under "Locked Decisions" are treated
as locked input to implementation and require an approved ACR/ADR to change. This repository does
not yet contain the authoritative architecture/requirements documents themselves (docs/02-
architecture/, docs/03-database/, etc. are currently scaffolded but not authored), nor any
accepted ADR or recorded Human approval — see
[docs/audits/IMP-000-INDEPENDENT-AUDIT.md](docs/audits/IMP-000-INDEPENDENT-AUDIT.md). No business
feature (donation, payment, ledger, commission, partner, beneficiary, CMS, theme, or API business
logic) has been implemented. IMP-000 itself has not passed its final gate and IMP-001 is not
authorized to begin — see
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

```
Human Decision Register
  -> Master Requirements
    -> Locked Architecture Documents
      -> ADR / Approved Implementation Decisions
        -> Implementation Specification
          -> Code
            -> Tests / Generated Documentation
```

Higher authority always wins over lower authority. See
[docs/00-governance/DOCUMENT-AUTHORITY.md](docs/00-governance/DOCUMENT-AUTHORITY.md).

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
