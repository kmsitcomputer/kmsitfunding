# Implementation Governance

## Purpose

Governs how implementation work is specified, assigned, executed, reviewed, and merged in this
repository. See also [AGENTS.md](../../AGENTS.md), [DOCUMENT-AUTHORITY.md](DOCUMENT-AUTHORITY.md),
[BRANCHING-POLICY.md](BRANCHING-POLICY.md), [DEFINITION-OF-DONE.md](DEFINITION-OF-DONE.md), and
[CHANGE-CONTROL.md](CHANGE-CONTROL.md).

## AI Role Assignment

```
CHATGPT       Chief Architect / PM / Prompt Engineer
COMMAND CODE  Repository Intelligence / Specialist Orchestrator
CLAUDE CODE   Primary Implementation Engineer
CODEX         Independent Reviewer / Auditor
HUMAN         Final Approval / Merge Authority
```

### ChatGPT

Responsible for implementation specification, architecture interpretation, decomposition, task
ordering, ambiguity resolution, stage gates, prompt generation, remediation planning. Not a
primary repository editor.

### Command Code

Used for repository exploration, dependency mapping, architecture impact analysis, multi-model
specialist analysis, locating relevant implementation, optional focused code review. Not an
uncontrolled concurrent editor.

### Claude Code

Primary builder: implements approved specification, migrations, backend, frontend, automated
tests, documentation updates, local verification. Owns the implementation branch during active
build.

### Codex

Independent reviewer: inspects the resulting change, finds architecture regressions, security
issues, test adequacy gaps, edge cases, financial invariant violations, code quality problems.
Default is `Coding: NO` and Codex does not edit Claude's active branch unless ownership is
explicitly transferred during remediation.

### Human

Final approval and merge authority. Required for anything listed in
[CHANGE-CONTROL.md](CHANGE-CONTROL.md) under "Human Approval Rule".

## No Concurrent Ownership

```
ONE FILE / ONE ACTIVE OWNER
ONE TASK / ONE PRIMARY IMPLEMENTATION AGENT
```

Forbidden: Claude editing a feature branch while Codex simultaneously edits the same branch.
This causes conflicts, undocumented decisions, unstable code, and unclear responsibility.

## Task Identity

```
IMP-###      implementation task, e.g. IMP-001
IMP-###.N    subtask, e.g. IMP-003.1
BUG-###      bug
ADR-###      architecture decision record
ACR-###      architecture change request (pre-ADR)
```

## Stage Workflow

```
1. Specification
2. Repository impact analysis
3. Implementation
4. Local tests
5. Independent review
6. Remediation
7. Re-review
8. Human merge
9. Gate lock
```

## Merge Authority

```
Claude Implementation
  -> Automated Tests
    -> Codex Independent Review
      -> Remediation if required
        -> Re-review
          -> Human Approval
            -> Merge
```

AI does not decide production acceptance alone.

## Prompt Header Standard

Every prompt sent to a coding agent should start with:

```
Jalankan di:
AI Agent:
Mode:
Repository:
Branch/Worktree:
Coding:
Stage:
Task ID:
```

### Claude Code Prompt Rule

Must include: exact objective, required documents to read, scope, out-of-scope, files likely
affected if known, acceptance criteria, required tests, explicit stop conditions. Never merely
"continue coding" for high-risk features.

### Codex Review Prompt Rule

Default `Mode: Independent Review`, `Coding: NO`. Reviews implementation vs specification,
architecture, security, DB integrity, tests, regressions. Output a severity matrix (see
[DEFINITION-OF-DONE.md](DEFINITION-OF-DONE.md) for severities).

### Command Code Prompt Rule

Receives focused research tasks (e.g. "map all code paths that can transition Payment to
successful"), not vague instructions (e.g. "review everything").

## Human Approval Rule

Human approval is required for:

- changing Q1-Q20 (Human Decision Register entries);
- new business rules;
- financial semantics;
- legal/compliance choices;
- accounting decisions requiring external verification;
- architecture-breaking infrastructure.

## External Verification Rule

A coding agent may implement an approved abstraction around unresolved external policy. It must
not invent external/legal/accounting truth (e.g. final Chart of Accounts mapping is
`EXTERNAL VERIFICATION` until approved).
