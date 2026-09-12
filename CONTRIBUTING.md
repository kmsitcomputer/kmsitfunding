# Contributing

This repository follows an AI-assisted, specification-driven implementation workflow. Read
[AGENTS.md](AGENTS.md) and [docs/00-governance/IMPLEMENTATION-GOVERNANCE.md](docs/00-governance/IMPLEMENTATION-GOVERNANCE.md)
before making any change.

## Workflow Summary

1. Every non-trivial change is backed by an implementation specification under
   `docs/implementation/IMP-###-*.md` (see
   [docs/00-governance/IMPLEMENTATION-GOVERNANCE.md](docs/00-governance/IMPLEMENTATION-GOVERNANCE.md)
   for the template and the small-task exception).
2. Work happens on a task branch: `impl/###-description`, `fix/IMP-XXX-description`, or
   `docs/ADR-XXX-description`. Never commit experimental work directly to `main`.
3. A task is not done until it satisfies
   [docs/00-governance/DEFINITION-OF-DONE.md](docs/00-governance/DEFINITION-OF-DONE.md).
4. Independent review happens before merge. Findings are classified BLOCKER / MAJOR / MINOR /
   EDITORIAL. No merge with open BLOCKER or MAJOR findings.
5. If implementation reveals that a locked architecture decision must change, stop coding and
   raise an Architecture Change Request (ACR) — see
   [docs/00-governance/CHANGE-CONTROL.md](docs/00-governance/CHANGE-CONTROL.md).

## Commits

Prefer small, coherent, conventional commits (`feat(scope): ...`, `fix(scope): ...`,
`test(scope): ...`, `docs(scope): ...`) over large multi-purpose commits.

## Secrets

Never commit `.env`, provider secrets, or credentials. Only `.env.example` belongs in the
repository. Any detected secret is treated as a BLOCKER finding.
