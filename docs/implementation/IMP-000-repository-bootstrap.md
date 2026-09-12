# IMP-000 — Repository Bootstrap & Implementation Governance

## Status

PASS

## Objective

Establish repository foundation, governance, and documentation authority before any AI coding
agent begins building the application.

## Source Requirements

Program-level directive: bootstrap governance prior to feature work. No Master Requirements
entries are implemented by this task.

## Architecture References

None yet locked beyond the baseline decisions captured in [AGENTS.md](../../AGENTS.md) under
"Locked Decisions."

## Scope

- Repository initialization (git).
- Top-level repository layout (`docs/`, placeholders for `app/`, `bootstrap/`, `config/`,
  `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `.github/`).
- `docs/` directory structure across governance, requirements, architecture, database, security,
  RBAC, domains, api, testing, deployment, ADR, decisions, implementation, audits.
- `AGENTS.md` baseline.
- Branching/worktree policy, Definition of Ready/Done, testing gate, review severity, merge gate,
  architecture change-control documents.
- Implementation specification template.
- Pull request and issue templates.

## Out of Scope

- Laravel application bootstrap (PHP, Vue, Inertia, TypeScript, Tailwind, Vite, MySQL baseline) —
  this is IMPLEMENTATION 01.
- Any business feature, migration, or business logic.
- Master Requirements content itself (Q1-Q20) — to be authored under
  `docs/01-requirements/MASTER-REQUIREMENTS.md` as a follow-up.

## Affected Domains

None (governance/repository scaffolding only).

## Business Rules

None implemented at this stage.

## Security Requirements

None implemented at this stage. `.gitignore` must exclude `.env` and other secret material once
IMPLEMENTATION 01 introduces them.

## Database Impact

None.

## API Impact

None.

## UI Impact

None.

## Financial Impact

None.

## Idempotency

Not applicable.

## Concurrency

Not applicable — establishes the no-concurrent-ownership rule for future tasks (see
[BRANCHING-POLICY.md](../00-governance/BRANCHING-POLICY.md)).

## Error Handling

Not applicable.

## Tests Required

None (no code). Future stages introduce the testing gate defined in
[DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md).

## Acceptance Criteria

```
[x] Source-of-truth hierarchy defined
[x] Repository structure defined
[x] AGENTS.md baseline defined
[x] AI responsibilities defined
[x] Branch/worktree rules defined
[x] Task specification format defined
[x] Definition of Ready defined
[x] Definition of Done defined
[x] Test gate defined
[x] Review severity defined
[x] Merge gate defined
[x] Architecture change-control defined
[x] Financial implementation rules preserved
[x] Security/RBAC rules preserved
[x] Human decision boundary preserved
```

## Forbidden Changes

None — this task establishes the rules rather than operating under pre-existing locked
architecture.

## External Verification

None.

## Definition of Done

```
[x] Governance documents committed under docs/00-governance/
[x] AGENTS.md committed at repository root
[x] Implementation specification template committed under docs/implementation/
[x] PR and issue templates committed under .github/
[x] Repository initialized as a git repository
```

## Gate

```
BLOCKER            0
MAJOR              0
GATE MINOR         0
HUMAN DECISION     0
```

**IMPLEMENTATION 00 — PASS. REPOSITORY GOVERNANCE — LOCKED. IMPLEMENTATION PROGRAM — AUTHORIZED.**

## Next Stage

IMPLEMENTATION 01 — Core Project Foundation: Laravel project bootstrap, PHP/Laravel
compatibility, Vue 3, Inertia 3, TypeScript, Tailwind 4, Vite, MySQL baseline, environment
example, testing baseline, modular application folder convention, base CI/static-check
conventions where practical, shared-hosting compatibility. No business feature implementation.
