# AI Workflow

Practical day-to-day workflow for using ChatGPT, Command Code, the assigned Primary
Implementation Owner, and Codex together on this repository. Roles and hard rules are defined in
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md); the per-stage model baseline and
ownership matrix — including Amendment V3's pipeline, which is the operative execution workflow
for IMP-008 forward — are defined in [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md); this
document is the operational checklist.

> **IMP-008 forward.** The "Per-Task Loop" below is the historical loop this repository used
> through IMP-007 (Claude Code / Kimi K3 / Qwen 3.8 Max 0902 / DeepSeek V4 Pro) and remains
> accurate for any stage still governed by that baseline (e.g. the Mission-Critical Claude Stages
> and IMP-030, which V3 does not reassign). **For IMP-008 and every subsequent stage V3 applies
> to, the operative loop is V3's pipeline** (Claude Spec -> **Human Spec Approval** -> Qwen 3.7
> Flash Recon -> Muse Spark 1.3 Contributor Implementation -> Automated Tests -> DeepSeek V4.1
> Flash Independent Technical Check -> targeted remediation -> Full Regression -> Codex Final
> Semantic/Closure Audit -> Human Stage Gate), per
> [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) "Amendment V3" and GOV-MM-005. Agents must
> not use step 3 below's legacy roster (Claude Code / Kimi K3 / Qwen 3.8 Max 0902) to select an
> implementation owner for IMP-008+ — the current Fixed IMP Ownership Matrix + V3 precedence note
> in that document governs instead. This history is preserved, not deleted, because it remains the
> accurate record of how IMP-000 through IMP-007 were actually built.

## Per-Task Loop (historical baseline; IMP-008+ uses V3 — see note above)

1. ChatGPT (or human) writes/confirms the implementation specification in
   `docs/implementation/IMP-###-*.md`, including the "Implementation Ownership" section (Primary
   Implementation Owner, exact model ID, execution environment, specialist reviewer(s)) per
   [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md).
1a. **Human Spec Approval (mandatory gate, all stages, discrete and durably recorded).** No
   implementation writing begins until Human Spec Approval is recorded for the exact specification
   revision, per [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) "Human Spec Approval Gate"
   (V3) and `IMPLEMENTATION-SPEC-TEMPLATE.md`'s approval fields. This was previously implicit in
   step 1 for pre-V3 stages; it is now explicit and durable for every stage.
2. Command Code (optional) explores the repository to map affected files, dependencies, and
   architecture impact, and reports back — it does not implement. (Under V3, this is Qwen 3.7
   Flash's Recon role for IMP-008+.)
3. The assigned Primary Implementation Owner for that IMP (Claude Code, Kimi K3, or
   Qwen 3.8 Max 0902 — see the ownership matrix; **superseded for IMP-008+ by V3's Main
   Developer, Muse Spark 1.3 Contributor, outside the Mission-Critical Claude Stages and
   IMP-030 — see note above**) implements on a dedicated branch (`impl/###-description`), owning
   that branch exclusively for the duration of the task. A Specialist Reviewer (e.g. DeepSeek V4
   Pro; DeepSeek V4.1 Flash under V3) may perform READ-ONLY analysis within that same task but
   does not edit the branch.
4. The Primary Implementation Owner runs local tests and updates documentation as part of the
   same task.
5. DeepSeek performs the Independent Technical Review (diff-first: spec compliance, logic,
   security, transactions/concurrency/idempotency, DB constraints — see
   [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) "DeepSeek / Codex Review Boundary" for the
   V3-IMP-008+ version of this step), then Codex reviews the resulting diff independently
   (`Coding: NO` by default) as the Final Semantic/Closure Audit, producing a severity-classified
   findings list (BLOCKER/MAJOR/MINOR/EDITORIAL).
6. If BLOCKER or MAJOR findings exist, remediation happens — the same Primary Implementation
   Owner resumes with explicit findings to address by default; ownership is transferred to
   another agent for the remediation only with explicit governance evidence (see
   [MULTI-MODEL-OWNERSHIP.md](MULTI-MODEL-OWNERSHIP.md) "Remediation Ownership").
7. Re-review confirms findings are resolved.
8. Human reviews and merges (Human Stage Gate).

## Do Not

- Do not let two agents write to the same branch/files concurrently.
- Do not skip the specification step for finance, RBAC, security, or partner/beneficiary-scoped
  work, even for "small" changes.
- Do not treat a review agent's silence as approval — an explicit pass/fail report is required.
