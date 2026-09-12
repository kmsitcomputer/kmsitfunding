# Human Decision Register

```
Source Status:        Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy
Semantic Change:       NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization), from the Human
Decision Register content supplied directly in that authorization. No entry below was invented,
reinterpreted, or simplified beyond what was supplied.

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this
register is Level 1 — the highest authority in this repository. No Master Requirement,
architecture document, ADR, implementation specification, governance document, or code may
override an entry here without an approved change to the entry itself.

## Register

```
Q1  — A  Single Organization
Q2  — A  Centralized Settlement
Q3  — D  Configurable Commission
Q4  — D  Configurable Operational Fee
Q5  — C  Hybrid Recurring Donation
Q6  — A  Public Anonymity Only
Q7  — D  Hybrid Beneficiary Registration
Q8  — D  Fully Configurable Document Policy
Q9  — B  Designated Zakat Administrator
Q10 — A  Wakaf as Standard Donation Product
Q11 — D  Hybrid Qurban Model
Q12 — D  Configurable Refund Approval Matrix
Q13 — D  Configurable Financial Approval Matrix
Q14 — D  Configurable Distribution Approval Matrix
Q15 — D  Configurable Withdrawal + Approval Matrix
Q16 — D  Configurable Privacy Model
Q17 — D  Configurable Retention Policy Matrix
Q18 — D  Configurable Versioned Receipt System
Q19 — D  Configurable Versioned Compliance Document System
Q20 — C  IDR Base + Limited Multi-Currency
```

## Superseded Decisions

```
Old Q3-A — No Commission — SUPERSEDED by Q3-D (Configurable Commission)
```

Superseded decisions are retained for traceability only. Do not resurrect a superseded decision
without a new explicit Human Decision.

## Change Control

Any change to an entry in this register is a Human Decision change. Per
[docs/00-governance/CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md), Human Decision
Required is fixed at YES without exception for such a change — no AI agent may approve it.
