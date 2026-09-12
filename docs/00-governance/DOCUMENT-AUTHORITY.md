# Document Authority

## Purpose

Defines the source-of-truth hierarchy for this repository and how conflicts between documents,
code, and tests are resolved.

## Principle

```
HUMAN DECISION
      |
MASTER REQUIREMENTS
      |
ARCHITECTURE
      |
DATABASE ARCHITECTURE
      |
SECURITY / RBAC
      |
IMPLEMENTATION SPEC
      |
CODE
      |
TEST
      |
REVIEW
```

Not:

```
AI IDEA -> architecture change -> coding
```

## Hierarchy

```
LEVEL 1  Human Decision Register
LEVEL 2  Master Requirements
LEVEL 3  Locked Architecture Documents
LEVEL 4  ADR / Approved Implementation Decisions
LEVEL 5  Implementation Specification
LEVEL 6  Code
LEVEL 7  Tests / Generated Documentation
```

## Conflict Resolution

If a conflict exists between levels, the higher authority wins.

Example: if code contradicts Master Requirements, the code is wrong — the requirement is not
automatically changed to match the code.

If an implementation reveals that a lower-authority document (e.g. a domain README) is stale
relative to a higher-authority document, update the lower-authority document, not the other way
around.

If an implementation reveals that a *higher*-authority document itself must change, this is an
architecture change, not a documentation fix. Follow
[CHANGE-CONTROL.md](CHANGE-CONTROL.md).
