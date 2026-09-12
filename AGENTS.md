# AGENTS.md

## Project

Modern Digital Philanthropy Platform.

Laravel modular monolith for a single primary organization.

## Authoritative Documents

Before modifying code, read applicable documents under:

- docs/01-requirements/
- docs/02-architecture/
- docs/03-database/
- docs/04-security/
- docs/05-rbac/
- docs/06-domains/
- docs/implementation/

Higher-level documents override lower-level assumptions.

## Locked Decisions

Do not change without explicit approved architecture change:

- Single Organization.
- Centralized Settlement.
- Modular Monolith.
- Single Laravel Application.
- Single root domain.
- Same-origin /api/v1.
- Ledger is accounting source of truth.
- Double-entry Ledger.
- Fund is not an authoritative mutable balance.
- Donation != Payment.
- Payment != Ledger.
- Reconciliation != Ledger.
- Moota is reconciliation only.
- Partner is not a tenant.
- Partner is not settlement owner.
- Commission uses configurable NONE/FIXED/PERCENTAGE policies.
- No generic mutable Fundraiser Commission Wallet.
- Withdrawal uses entitlement allocation.
- Financial posting follows Authorized Financial Consequence -> Ledger.
- Posted Ledger records are immutable.
- Corrections use reversal/adjustment.
- Public donor representation != internal identity.
- Theme is presentation-only.
- Admin/backoffice is not theme-controlled.
- Shared-hosting-compatible production remains mandatory.

## Before Coding

1. Read the implementation specification.
2. Read relevant architecture/domain documents.
3. Inspect existing implementation.
4. Identify affected modules.
5. Identify affected tests.
6. Verify no locked architecture decision is being changed.
7. Make the smallest coherent change.

## Never

- Invent business rules.
- Invent Commission rates.
- Invent approval limits.
- Invent accounting entries.
- Modify locked decisions silently.
- Add infrastructure that becomes mandatory without approval.
- Add Redis/Kafka/Elasticsearch as required infrastructure.
- Introduce microservices.
- Introduce an independent Partner tenant.
- Store accounting truth in mutable balance fields.
- Make provider callbacks write directly to Ledger.
- Bypass policies/authorization in API endpoints.
- Expose sensitive files through permanent public URLs.
- Disable tests to make a change pass.

## Financial Rules

Financial operations must follow:

Business Use Case
-> Business State Transition
-> Authorized Financial Consequence
-> Ledger Posting Contract
-> Ledger Journal
-> Ledger Entries

No direct provider/reconciliation/controller to Ledger writes.

## Security

Authorization is not role-only.

Effective access evaluates:

- authentication,
- applicable subject context,
- permission,
- domain-aware scope,
- ownership,
- business authority,
- resource state,
- approval state,
- authentication assurance,
- security restrictions.

Default is DENY.

## Database

- Use migrations for schema changes.
- Never edit production schema manually.
- Preserve financial history.
- Avoid soft delete on immutable financial truth where inappropriate.
- Use strong foreign-key semantics where practical.
- Use DECIMAL-style fixed precision for money.
- Never use FLOAT/DOUBLE for monetary values.

## Tests

Every implementation must include relevant automated tests.

Financial and authorization behavior requires negative-path tests.

## Completion

A task is not complete until:

- code is implemented,
- tests pass,
- lint/static checks pass where configured,
- documentation is updated,
- implementation spec acceptance criteria are satisfied,
- no architecture regression exists.

## Stop Conditions

Stop implementation and report instead of guessing when:

- requirement conflicts with architecture,
- a new business decision is required,
- accounting treatment is undefined,
- legal/compliance interpretation is required,
- changing a locked architecture decision appears necessary.
