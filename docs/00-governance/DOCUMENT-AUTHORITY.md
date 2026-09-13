# Document Authority

## Purpose

Defines the ONE canonical source-of-truth hierarchy for this repository and how conflicts
between documents, code, and tests are resolved. This replaces any other hierarchy diagram
elsewhere in the repository — if another document appears to describe a different ordering, this
file is authoritative and the other document should be read as referring to the same levels
below.

## Canonical Hierarchy

```
LEVEL 1 — HUMAN DECISIONS
- Human Decision Register
- explicit approved Human changes

LEVEL 2 — MASTER REQUIREMENTS
- Master Requirements

LEVEL 3 — LOCKED ARCHITECTURE BASELINE
- Master Architecture
- Database Architecture
- Security Architecture
- Authentication/RBAC Architecture
- approved locked domain architecture documents

LEVEL 4 — APPROVED DECISIONS / AMENDMENTS
- accepted ADRs
- approved architecture amendments

LEVEL 5 — IMPLEMENTATION SPECIFICATIONS
- IMP-XXX specifications

LEVEL 6 — GOVERNANCE / OPERATING INSTRUCTIONS
- AGENTS.md
- IMPLEMENTATION-GOVERNANCE.md
- AI-WORKFLOW.md
- MULTI-MODEL-OWNERSHIP.md
- BRANCHING-POLICY.md
- DEFINITION-OF-DONE.md
- CHANGE-CONTROL.md
- CONTRIBUTING.md

LEVEL 7 — IMPLEMENTATION ARTIFACTS
- Code
- Tests
- generated operational documentation
```

Governance documents (Level 6) control implementation *process* — how work is specified,
reviewed, and merged — but cannot override higher-level product, requirement, architecture,
security, or RBAC decisions (Levels 1-4). Not:

```
AI IDEA -> architecture change -> coding
```

## Conflict Resolution — Different Levels

If a conflict exists between levels, the higher authority (lower level number) wins.

Example: if code (Level 7) contradicts Master Requirements (Level 2), the code is wrong — the
requirement is not automatically changed to match the code.

If an implementation reveals that a lower-authority document (e.g. a domain README) is stale
relative to a higher-authority document, update the lower-authority document, not the other way
around.

If an implementation reveals that a *higher*-authority document itself must change, this is an
architecture change, not a documentation fix. Follow [CHANGE-CONTROL.md](CHANGE-CONTROL.md),
which requires Human approval for any change to Levels 1-3.

## Conflict Resolution — Same Level

When two documents at the same authority level appear to conflict:

```
1. apply the more specific document if its scope is unambiguous;
2. if conflict still exists, STOP;
3. record the conflict (e.g. as an issue or ACR under docs/decisions/);
4. escalate to the authority required for that decision;
5. an AI agent MUST NOT silently choose one interpretation.
```
