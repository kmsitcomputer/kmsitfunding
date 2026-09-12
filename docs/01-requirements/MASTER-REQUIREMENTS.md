# Master Requirements

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy
Semantic Change:        NONE
Authority:               Existing locked architecture / requirement program.
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision, business rule, or Human Decision. It was materialized during IMP-001
B01 Materialization Pass 2, under the same Human authorization that governed Pass 1
(materialization only; no reinterpretation; STOP for any sub-topic without supplied content
rather than reconstructing it from guesses).

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 2 — Master Requirements, subordinate only to the
[Human Decision Register](HUMAN-DECISION-REGISTER.md) (Level 1).

## 1. Product Model

```
A single primary organization/foundation owns and operates the platform.
Partner is NOT an independent tenant.
All donation settlement is centralized through the primary organization's payment and
  financial infrastructure.
```

## 2. Human Decision Register

Q1-Q25 in [docs/01-requirements/HUMAN-DECISION-REGISTER.md](HUMAN-DECISION-REGISTER.md) are
authoritative Human Decisions and are not duplicated here except by direct reference, to avoid
inconsistent copies (Q1-Q20 were materialized alongside this document; Q21-Q25 were added later,
during IMP-002 specification/readiness work — see that register's own introduction for
provenance). In particular:

```
Q3-A  No Commission                 = SUPERSEDED
Q3-D  Configurable Commission       = FINAL / LOCKED
```

Any apparent requirement elsewhere in this document that conflicts with an entry in the Human
Decision Register is an error in this document, not a change to the register — see
[docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md) "Conflict
Resolution — Different Levels".

## 3. Platform Constraints

```
Single Laravel Application
Single root domain
```

Same-origin routes include at least:

```
/
/campaign/*
/zakat/*
/wakaf/*
/fidyah/*
/qurban/*
/donor/*
/fundraiser/*
/partner/*
/admin/*
/api/v1/*
```

No mandatory API/admin/app subdomain.

## 4. Technology Direction

```
Laravel 13.x direction
PHP 8.3 baseline
MySQL 8.x

Vue 3
Inertia 3
TypeScript
Tailwind CSS 4
Vite

Database-backed queue
File/database cache/session as applicable
Laravel Scheduler + Cron
Laravel Storage
MySQL Full-Text
REST /api/v1
Flutter later
```

Shared hosting remains a required deployment constraint — see §5.

## 5. Shared Hosting

Production MUST remain compatible with:

```
Apache / LiteSpeed
PHP
MySQL
Cron
compiled frontend assets
Laravel Storage
```

Production MUST NOT require:

```
Docker
Redis server
Supervisor
PM2
WebSocket runtime
Kafka
RabbitMQ
Elasticsearch
Node runtime
separate application server
```

## 6. Business Domain Requirements

The following business domains are recognized by the approved requirement program. This document
records that each domain exists as an approved area of future scope — it does not specify
detailed business rules, rates, thresholds, or workflows for any of them (those are not part of
the source content supplied for this materialization, and per the governing Human authorization
must not be invented here). Detailed requirements for each domain are to be developed in that
domain's own implementation specification and domain documentation
(`docs/06-domains/<domain>/`), at the appropriate stage, sourced from the Human Authority or the
external approved baseline — not guessed:

```
Public Website
CMS
Theme Engine

Campaign
Program
Donation
Recurring Donation

Fundraiser
Attribution
Commission
Withdrawal

Operational Fee
Refund

Financial Approval
Ledger
Fund

Partner

Payment Hub
Manual Bank Transfer
Tripay
Xendit
Stripe

Moota

Zakat
Wakaf
Fidyah
Qurban

Beneficiary
Distribution
Impact

Donor Portal
Fundraiser Portal
Partner Portal

Privacy
Retention

Receipt
Compliance Documents

Multi-Currency

Notification
Search
Reporting
Audit

Security / Fraud

REST API
Deployment
Historical Reproducibility
Policy Governance
```

No configuration value (rate, threshold, retention period, currency list, etc.) for any of the
above is defined by this document. See [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md)
for which module owns each domain's authoritative state once implemented.

## 7. Financial Requirements

```
Ledger is the financial source of truth.
Ledger uses double-entry accounting.
Posted financial records are immutable.
Corrections occur through reversal / adjustment.

Fund is designation/restriction context, NOT an authoritative mutable balance.
Payment is NOT Ledger.
Reconciliation is NOT Ledger.
Moota is reconciliation-only.
Commission is NOT a mutable wallet.
```

Canonical lifecycle:

```
Business State Transition
        v
Authorized Financial Consequence
        v
Ledger Posting Contract
        v
Double-Entry Ledger
```

See [docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md)
for the full posting chain (including the upstream normalize/validate/use-case/domain-validation
steps) and [docs/03-database/DATABASE-INVARIANTS.md](../03-database/DATABASE-INVARIANTS.md) for
the associated database invariants.

## 8. Commission Requirements

Preserves Q3-D. Configurable policy types:

```
NONE
FIXED
PERCENTAGE
```

Commission policy must be:

```
configurable
authorized
versioned
auditable
historically reproducible
```

No commission rate is invented or implied by this document.

Lifecycle:

```
Attributed
  -> Eligible
  -> Calculated / Earned
  -> Available
  -> Allocated
  -> Paid / Withdrawn
  -> Adjusted / Reversed
```

Fundraiser balance is derived, not stored as a mutable source of truth. There is no generic
mutable wallet source of truth — see [AGENTS.md](../../AGENTS.md) "Never": "No generic mutable
Fundraiser Commission Wallet."

## 9. Payment Requirements

```
Donation != Payment
Payment may have multiple intents/attempts.
Provider redirect is not authoritative payment confirmation.
```

Manual verification, provider callback, and Moota/reconciliation must converge through an owning
application use case. No provider callback may write directly to Ledger — see
[docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md).

Approved provider set:

```
Manual Bank Transfer
Tripay
Xendit
Stripe
future adapters
```

Midtrans is explicitly NOT part of the approved baseline — do not add it without an approved ACR.

## 10. Privacy / Beneficiary / Document Requirements

```
Internal donor identity != public donor representation.
Fundraiser attribution does not grant unrestricted donor identity.
Beneficiary data is HIGHLY_SENSITIVE.
Sensitive documents must not use permanent public URLs.
Retention cannot silently destroy financial, Ledger or audit evidence.
```

## Related Materialized Documents

- [docs/01-requirements/HUMAN-DECISION-REGISTER.md](HUMAN-DECISION-REGISTER.md)
- [docs/02-architecture/MASTER-ARCHITECTURE.md](../02-architecture/MASTER-ARCHITECTURE.md)
- [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md)
- [docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md)
- [docs/03-database/DATABASE-ARCHITECTURE.md](../03-database/DATABASE-ARCHITECTURE.md)
- [docs/04-security/SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md)
- [docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md)
