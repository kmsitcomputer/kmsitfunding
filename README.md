# Modern Digital Philanthropy Platform

Laravel modular monolith for a single primary organization, covering campaigns, donations,
payments, ledger accounting, commission, fundraisers, partners, and beneficiary distribution.

## Status

```
Architecture Program:    COMPLETE / LOCKED
Implementation Program:  STARTED
Current Stage:           IMP-000 — Repository Bootstrap & Implementation Governance
```

"Architecture Program: COMPLETE / LOCKED" means the architecture decisions listed in
[AGENTS.md](AGENTS.md) under "Locked Decisions" are settled and require an approved ACR/ADR to
change — it does not mean any application feature has been built. No business feature (donation,
payment, ledger, commission, partner, beneficiary, CMS, theme, or API business logic) has been
implemented. This stage (IMP-000) covers repository bootstrap and governance only.

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
