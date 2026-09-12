# AI Workflow

Practical day-to-day workflow for using ChatGPT, Command Code, Claude Code, and Codex together
on this repository. Roles and hard rules are defined in
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md); this document is the operational
checklist.

## Per-Task Loop

1. ChatGPT (or human) writes/confirms the implementation specification in
   `docs/implementation/IMP-###-*.md`.
2. Command Code (optional) explores the repository to map affected files, dependencies, and
   architecture impact, and reports back — it does not implement.
3. Claude Code implements on a dedicated branch (`impl/###-description`), owning that branch
   exclusively for the duration of the task.
4. Claude Code runs local tests and updates documentation as part of the same task.
5. Codex reviews the resulting diff independently (`Coding: NO` by default), producing a
   severity-classified findings list (BLOCKER/MAJOR/MINOR/EDITORIAL).
6. If BLOCKER or MAJOR findings exist, remediation happens — either Claude Code resumes with
   explicit findings to address, or ownership is explicitly transferred to another agent for the
   remediation only.
7. Re-review confirms findings are resolved.
8. Human reviews and merges.

## Do Not

- Do not let two agents write to the same branch/files concurrently.
- Do not skip the specification step for finance, RBAC, security, or partner/beneficiary-scoped
  work, even for "small" changes.
- Do not treat a review agent's silence as approval — an explicit pass/fail report is required.
