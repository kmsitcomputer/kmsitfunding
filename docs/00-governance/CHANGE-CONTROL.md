# Architecture Change Control

## Rule

If implementation reveals that architecture must change: **stop coding**. Do not silently
rewrite architecture because it is easier to code a different way (e.g. a new payment model, a
mutable wallet balance, tenant architecture, a microservice split, a new mandatory Redis
dependency, different Commission logic). Ease of coding is not sufficient justification.

Raise an **ACR — Architecture Change Request** instead, using
[docs/decisions/ARCHITECTURE-CHANGE-REQUEST-TEMPLATE.md](../decisions/ARCHITECTURE-CHANGE-REQUEST-TEMPLATE.md).

## ACR to ADR Flow

```
ACR (docs/decisions/) -> Review -> Human / Architecture Approval
   -> ADR (docs/adr/, using docs/adr/ADR-TEMPLATE.md)
   -> Updated authoritative docs
   -> Implementation resumes
```

Draft ACRs live under `docs/decisions/`. Once approved, the decision is recorded as an ADR under
`docs/adr/`.

## Database Migration Policy

```
Schema Change -> Migration -> Test -> Review
```

No undocumented schema modifications. Never edit production schema manually.

## Seeders and Fixtures

Seeders distinguish:

```
SYSTEM REFERENCE DATA
DEVELOPMENT DATA
TEST FIXTURES
```

Production must not depend on random demo seed data. Financial tests require deterministic
factories — do not generate unpredictable amounts/state combinations that make tests unreliable.

## Dependency Governance

Adding a dependency requires justification. Ask:

```
Does Laravel already provide this?
Is the package maintained?
Does it create mandatory infrastructure?
Does it touch security/finance?
Can it run on shared hosting?
```

Use framework capabilities before building custom equivalents for authentication, validation,
hashing, encryption, queue, scheduler, storage, policies, rate limiting. No custom cryptography.
