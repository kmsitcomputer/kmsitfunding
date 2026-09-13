# AI Workflow

Practical day-to-day workflow for using ChatGPT, Command Code, the assigned Primary
Implementation Owner, and Codex together on this repository. Roles and hard rules are defined in
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md); the per-stage model baseline and
ownership matrix are defined in [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md); this
document is the operational checklist.

## Per-Task Loop

1. ChatGPT (or human) writes/confirms the implementation specification in
   `docs/implementation/IMP-###-*.md`, including the "Implementation Ownership" section (Primary
   Implementation Owner, exact model ID, execution environment, specialist reviewer(s)) per
   [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md).
2. Command Code (optional) explores the repository to map affected files, dependencies, and
   architecture impact, and reports back — it does not implement.
3. The assigned Primary Implementation Owner for that IMP (Claude Code, Kimi K3, or
   Qwen 3.8 Max 0902 — see the ownership matrix) implements on a dedicated branch
   (`impl/###-description`), owning that branch exclusively for the duration of the task. A
   Specialist Reviewer (e.g. DeepSeek V4 Pro) may perform READ-ONLY analysis within that same
   task but does not edit the branch.
4. The Primary Implementation Owner runs local tests and updates documentation as part of the
   same task.
5. Codex reviews the resulting diff independently (`Coding: NO` by default), producing a
   severity-classified findings list (BLOCKER/MAJOR/MINOR/EDITORIAL).
6. If BLOCKER or MAJOR findings exist, remediation happens — the same Primary Implementation
   Owner resumes with explicit findings to address by default; ownership is transferred to
   another agent for the remediation only with explicit governance evidence (see
   [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) "Remediation Ownership").
7. Re-review confirms findings are resolved.
8. Human reviews and merges.

## Do Not

- Do not let two agents write to the same branch/files concurrently.
- Do not skip the specification step for finance, RBAC, security, or partner/beneficiary-scoped
  work, even for "small" changes.
- Do not treat a review agent's silence as approval — an explicit pass/fail report is required.
