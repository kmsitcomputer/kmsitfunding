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
Conflict Detected
  -> STOP IMPLEMENTATION
  -> ACR Drafted (docs/decisions/)
  -> AI / Technical Analysis (recommendation only, not approval)
  -> HUMAN DECISION
  -> if approved: ADR + authoritative baseline update (docs/adr/)
  -> implementation resumes
  -> independent re-review
```

Draft ACRs live under `docs/decisions/`. Once approved by a Human, the decision is recorded as an
ADR under `docs/adr/`.

## AI Approval Boundary

AI agents, including ChatGPT, Claude Code, Codex, Command Code, or any other automated system,
MAY analyze, recommend, draft, or review a proposed change. AI agents MUST NOT approve a change
to locked architecture, business rules, financial semantics, security boundaries, or Human
Decision Register items. Approval of such a change requires a Human decision, recorded on the ACR
and reflected in the resulting ADR's Approval section.

For any ACR classified as LOCKED ARCHITECTURE, BUSINESS RULE, SECURITY, or FINANCIAL (see
[docs/decisions/ARCHITECTURE-CHANGE-REQUEST-TEMPLATE.md](../decisions/ARCHITECTURE-CHANGE-REQUEST-TEMPLATE.md)
"Change Classification"), **Human Decision Required = YES without exception.**

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
