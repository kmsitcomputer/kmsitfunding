# Implementation Governance

## Purpose

Governs how implementation work is specified, assigned, executed, reviewed, and merged in this
repository. See also [AGENTS.md](../../AGENTS.md), [DOCUMENT-AUTHORITY.md](DOCUMENT-AUTHORITY.md),
[BRANCHING-POLICY.md](BRANCHING-POLICY.md), [DEFINITION-OF-DONE.md](DEFINITION-OF-DONE.md), and
[CHANGE-CONTROL.md](CHANGE-CONTROL.md).

## AI Role Assignment

```
CHATGPT                       Chief Architect / PM / Prompt Engineer
COMMAND CODE                  Repository Intelligence / Specialist Orchestrator; execution
                               environment for Kimi/Qwen/DeepSeek
PRIMARY IMPLEMENTATION OWNER  Assigned explicitly per IMP stage (Claude Code, Kimi K3, or
                               Qwen 3.8 Max 0902) — see MULTI-MODEL-OWNERSHIP.md
SPECIALIST REVIEWER           DeepSeek V4 Pro (READ ONLY by default) for database/transaction/
                               concurrency/payment/finance/security-sensitive domains — see
                               MULTI-MODEL-OWNERSHIP.md
CODEX                         Independent Reviewer / Auditor
HUMAN                         Final Approval / Merge Authority
```

> **Multi-Model Implementation Ownership Amendment.** As of
> [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md), Claude Code is no longer the permanent,
> universal Primary Implementation Owner — that role is assigned explicitly per IMP stage from an
> approved model baseline (Claude Code, Kimi K3, Qwen 3.8 Max 0902), with DeepSeek V4 Pro as a
> READ-ONLY specialist reviewer for database/finance/security-sensitive domains. See that
> document for the full model baseline, the fixed per-IMP ownership matrix, mission-critical
> Claude stages, model version pinning, and model change control. This section's role
> descriptions below remain accurate for each named AI's responsibilities; only the *assignment*
> of "Primary Implementation Owner" per stage has changed from implicitly-always-Claude to
> explicit-per-IMP.

### ChatGPT

Responsible for implementation specification, architecture interpretation, decomposition, task
ordering, ambiguity resolution, stage gates, prompt generation, remediation planning. Not a
primary repository editor.

### Command Code

Used for repository exploration, dependency mapping, architecture impact analysis, multi-model
specialist analysis, locating relevant implementation, optional focused code review. Also the
execution environment for the Kimi/Qwen/DeepSeek model baseline when one of them is the assigned
Primary Implementation Owner or Specialist Reviewer for a stage — see
[MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md). Not an uncontrolled concurrent editor.

### Primary Implementation Owner (Claude Code, Kimi K3, or Qwen 3.8 Max 0902)

Whichever model is assigned as Primary Implementation Owner for a given IMP stage (see
[MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) for the fixed ownership matrix) implements
approved specification, migrations, backend, frontend, automated tests, and documentation
updates, and performs local verification. It owns the implementation branch exclusively during
active build. Claude Code remains the assigned owner for the mission-critical stages listed in
that document (payment, ledger, financial posting, commission, withdrawal, refund, security
hardening).

### Codex

Independent reviewer: inspects the resulting change, finds architecture regressions, security
issues, test adequacy gaps, edge cases, financial invariant violations, code quality problems.
Default is `Coding: NO` and Codex does not edit the Primary Implementation Owner's active branch
unless ownership is explicitly transferred during remediation.

### Human

Final approval and merge authority. Required for anything listed below under "Human Approval
Rule" in this document. See also [CHANGE-CONTROL.md](CHANGE-CONTROL.md) for the ACR/ADR
architecture-change workflow that Human approval feeds into.

## No Concurrent Ownership

```
ONE FILE / ONE ACTIVE OWNER
ONE TASK / ONE PRIMARY IMPLEMENTATION AGENT
```

Forbidden: the Primary Implementation Owner editing a feature branch while Codex, a specialist, or
any other AI simultaneously edits the same branch. This causes conflicts, undocumented decisions,
unstable code, and unclear responsibility. See
[MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) for the strengthened multi-model version of
this rule.

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
Primary Implementation Owner Implementation
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

### Primary Implementation Owner Prompt Rule

Applies regardless of which model is assigned as Primary Implementation Owner for the stage (see
[MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md)). Must include: exact objective, required
documents to read, scope, out-of-scope, files likely affected if known, acceptance criteria,
required tests, explicit stop conditions. Never merely "continue coding" for high-risk features.

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
