# Financial Posting Boundary / Ledger Posting Contract

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy
Semantic Change:        NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization).

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline (financial posting sub-domain of Master Architecture).

## Canonical Posting Chain

```
Business / Provider / Reconciliation Event
        v
Normalize / Validate
        v
Owning Application Use Case
        v
Domain Validation
        v
Business State Transition
        v
Authorized Financial Consequence
        v
Ledger Posting Contract
        v
Double-Entry Ledger
```

No step may be skipped. In particular, no component may post directly to the Ledger by
shortcutting this chain — see [AGENTS.md](../../AGENTS.md) "Never": "Make provider callbacks
write directly to Ledger."

## Preserved Distinctions

```
Donation             != Payment
Payment              != Ledger
Reconciliation       != Ledger
Financial Consequence != Ledger
Fund                 != authoritative balance
Commission           != Wallet
```

## Preserved Invariants

- Posted Ledger records remain immutable.
- Corrections occur through reversal/adjustment, never through mutation of a posted record.
- Moota remains reconciliation-only — it is a bank-mutation matching input, not a Ledger writer.

## Relationship to Other Materialized Documents

- [docs/02-architecture/MASTER-ARCHITECTURE.md](MASTER-ARCHITECTURE.md)
- [docs/03-database/DATABASE-ARCHITECTURE.md](../03-database/DATABASE-ARCHITECTURE.md)
- [AGENTS.md](../../AGENTS.md) "Financial Rules"
