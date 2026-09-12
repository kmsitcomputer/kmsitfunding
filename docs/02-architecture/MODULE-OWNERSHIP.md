# Module Ownership

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
Level 3 — Locked Architecture Baseline, a sub-document of
[docs/02-architecture/MASTER-ARCHITECTURE.md](MASTER-ARCHITECTURE.md) (which previously noted this
file as not yet materialized — that gap is closed by this document).

This document defines which module *owns* which state and concepts. It is an ownership map, not
an implementation plan — no module listed here is implemented by IMP-001 or by this
materialization pass. See [AGENTS.md](../../AGENTS.md) "Locked Decisions" for the cross-cutting
rules (Donation != Payment, Payment != Ledger, etc.) that these ownership boundaries exist to
enforce.

## Module Catalog

```
1.  Identity & Organization
2.  Content Experience
3.  Campaign & Program
4.  Donation
5.  Fundraising & Attribution
6.  Commission
7.  Partner
8.  Payment
9.  Finance & Fund
10. Ledger
11. Reconciliation
12. Approval
13. Philanthropy Products
14. Beneficiary & Distribution
15. Documents & Communication
16. Governance & Platform Services
```

## Core Semantics

### 1. Identity & Organization

Owns:

```
User identity
Organization membership
Principal organizational context
```

Does NOT own:

```
financial approval semantics
business authority semantics belonging to authorization governance
```

### 2. Content Experience

Owns:

```
Page
Article
News
Content Revision
Theme presentation configuration
```

Does NOT own authoritative business-domain state. CMS blocks may reference controlled public
projections of business-domain data, never the authoritative record itself.

### 3. Campaign & Program

Owns:

```
Campaign
Program
Campaign-program relationships
Campaign lifecycle
```

Campaign != Program != Fund. Campaign progress is not an accounting balance.

### 4. Donation

Owns:

```
Donation
Recurring Plan
Recurring Occurrence
Donation lifecycle
```

Donation does NOT own Payment or Ledger.

### 5. Fundraising & Attribution

Owns:

```
Fundraiser Profile
Referral Link
Referral Code
Attribution Evidence
Attribution Resolution
Attribution Correction
```

Attribution does not itself create Commission entitlement.

### 6. Commission

Owns:

```
Commission Policy
Policy Version
Eligibility
Evaluation
Entitlement
Hold
Adjustment
Reversal
Derived Availability
```

Does NOT own a generic wallet.

### 7. Partner

Owns:

```
Partner Profile
Partner User relationship
Partner Verification
Partner operational participation
```

Partner is NOT:

```
tenant
payment infrastructure owner
settlement owner
independent Ledger owner
```

### 8. Payment

Owns:

```
Payment lifecycle
Payment Intent
Payment Attempt
Provider Event
Payment Verification
```

Does NOT own:

```
Donation semantics
Commission
Ledger accounting truth
```

### 9. Finance & Fund

Finance owns:

```
Business financial orchestration
Refund
Withdrawal
Operational Fee
Fund restriction/designation context
Financial approval integration
```

Fund does NOT own an authoritative mutable balance.

### 10. Ledger

Owns:

```
Ledger Account
Ledger Journal
Ledger Entry
Accounting representation
```

Ledger is the financial source of truth. Posted journals/entries are immutable.

### 11. Reconciliation

Owns:

```
Reconciliation Source
Bank Mutation
Reconciliation Match
Matching evidence
```

Does NOT own accounting truth. Moota belongs here as a reconciliation input, not as a Ledger
writer.

### 12. Approval

Owns:

```
Approval Policy
Policy Version
Approval Instance
Approval Step
Approval Decision
Approval mechanics
```

Does NOT own underlying business semantics.

### 13. Philanthropy Products

Owns domain-specific context for:

```
Zakat
Wakaf
Fidyah
Qurban
```

while reusing Donation/Payment/Ledger infrastructure rather than duplicating it.

### 14. Beneficiary & Distribution

Beneficiary owns:

```
Application
Profile
Verification
Assessment
Eligibility
```

Distribution owns:

```
Distribution Plan
Execution
Evidence
Evidence Verification
```

Impact owns measurement/result semantics.

### 15. Documents & Communication

Documents own:

```
Receipt
Receipt Version
Compliance Document
Compliance Document Version
```

Communication owns:

```
Notification Intent
Notification Attempt
Delivery Result
```

### 16. Governance & Platform Services

Owns governance/platform concerns including:

```
Audit
Privacy
Retention
Search
Reporting
Currency Governance
```

## Related Materialized Documents

- [docs/02-architecture/MASTER-ARCHITECTURE.md](MASTER-ARCHITECTURE.md)
- [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §6
- [docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](FINANCIAL-POSTING-BOUNDARY.md)
