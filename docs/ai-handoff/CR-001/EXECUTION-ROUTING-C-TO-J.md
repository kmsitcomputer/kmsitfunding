# CR-001 Execution Routing — Phases C Through J

## Status

FINAL / HUMAN-APPROVED

## Scope

This document records the Human-approved multi-agent execution routing for
`CR-001-C` through `CR-001-J` only:

- CR-001-C — CMS / Site Design Foundation
- CR-001-D — Visual Page Builder
- CR-001-E — PublicRenderer Integration
- CR-001-F — Public Experience / Responsive UX
- CR-001-G — Zakat/Fidyah Calculator Experience
- CR-001-H — Admin Experience V2
- CR-001-I — Integration & UX Consolidation
- CR-001-J — Security / Compatibility / CODEX-FE009-01 Remediation

This is a **scoped execution-routing amendment**. It does **not** replace,
rewrite, or silently amend the global Multi-Agent Workflow V3. It changes
execution *efficiency and role assignment* for CR-001-C through CR-001-J
only — it does not change product architecture, locked decisions, or any
governance authority defined elsewhere (see `AGENTS.md`,
`docs/00-governance/DOCUMENT-AUTHORITY.md`,
`docs/00-governance/CHANGE-CONTROL.md`).

CR-001-A and CR-001-B are unaffected by this document. CR-001-B remains
FINAL / LOCKED under its own prior routing and finalization record
(`docs/audits/CR-001-B-FINALIZATION.md`).

## Human Authorization Basis

- CR-001-C Recon: **FINAL / HUMAN-APPROVED** (C-01 through C-16 CLOSED;
  0 BLOCKER, 0 MAJOR, 0 new Human Decisions; Implementation Contract:
  READY — per the CR-001-C recon closure review chain culminating in
  `docs/ai-handoff/CR-001/C-RECON.md`).
- This routing document formalizes the agent-role amendment the Human
  Authority already made during the CR-001-C recon closure review, now
  materialized as its own standalone artifact per explicit Human
  instruction — not created earlier, since recon review was read-only.

## Agent Roles (CR-001-C through CR-001-J)

### Claude Sonnet 5 — Lead Architect

Owns: architecture, specification, contracts, boundaries, acceptance
criteria, architecture/security/financial remediation, and consolidated
closure review where appropriate.

Claude is **not** the default implementation writer for C–J.

### Qwen 3.7 Flash (`qwen/qwen3.7-flash`) — Governed Recon / File Map

Role: Governed Recon / File Map / Cheap Verification.

Default: READ-ONLY, except explicitly authorized handoff/recon
documentation (e.g. `*-RECON.md` files, patched only in the scope
explicitly assigned). Qwen receives minimum sufficient context per task.

### Muse Spark 1.3 Contributor (`meta/muse-spark-1.3-contributor`) — Primary Implementation Writer

Role: PRIMARY IMPLEMENTATION WRITER for C–J.

Muse owns ordinary implementation changes after the applicable Human
Spec/Architecture Gate and Human Recon Gate have been satisfied. Ordinary
implementation defects return to Muse as **PATCH — DO NOT REWRITE**.

### DeepSeek V4.1 Flash (`deepseek/deepseek-v4.1-flash`) — Independent Technical Checker

Role: Independent Technical Checker / Diff-First Reviewer.

Default: READ-ONLY. Reviews the implementation **diff**, not a full
repository rediscovery. Must not become a second implementation writer by
default.

### Codex — Independent Final Semantic / Closure Auditor

Role: Independent Final Semantic / Closure Auditor. READ-ONLY.

Codex must not fix its own findings. Any Codex finding routes back to
Muse or Claude depending on finding type, then: targeted tests → bounded
Codex re-audit, only where gating remediation actually requires it.

### Human — Final Authority

Only Human may approve: new Human Decisions, locked architecture changes,
business-rule changes, final phase gates, and merge/push authorization
where governance requires it.

## Standard C–J Flow

```
Claude          Architecture / Specification
   ↓
Human           Spec / Architecture Gate (where applicable)
   ↓
Qwen            Governed Recon / File Map
   ↓
Claude          Consolidated Recon Review
   ↓
Human           Recon Gate
   ↓
Muse            Implementation
   ↓
Automated Tests
   ↓
DeepSeek        Diff-First Independent Review
   ↓
Bundled Remediation (only when required)
   ↓
Full Regression
   ↓
Codex           Final Semantic / Closure Audit
   ↓
Bundled Remediation (only for genuine gating findings)
   ↓
Human           Phase Gate
   ↓
Finalization / Commit / Push (when explicitly authorized)
```

## Efficiency / Anti-Ping-Pong Rule

CR-001-C through CR-001-J optimize for:

**RECON ONCE → IMPLEMENT BY FILE MAP → REVIEW BY DIFF → REMEDIATE BY FINDING.**

Use minimum sufficient context. Do not repeatedly rediscover already-closed
architecture. Do not repeatedly audit unchanged closed sections.

## Severity / Gating Rule

**GATING** (blocks Human Phase Gate until remediated):
BLOCKER; MAJOR; a new Human Decision; a security boundary defect; an
authorization defect; a data-integrity defect; a financial/accounting
contract defect; an architecture contradiction; a schema/contract defect
preventing safe implementation; a material phase-boundary violation.

**NON-GATING** (recorded, but does not itself trigger another
agent remediation/re-review cycle): ordinary editorial documentation
issues, wording, formatting, optional refactoring, style preferences,
non-material documentation duplication, future enhancements, and other
MINOR findings that do not affect correctness, security, authorization,
data integrity, financial correctness, architecture, business semantics,
or implementation safety.

Non-gating findings must be recorded and may be bundled into the next
appropriate remediation/finalization pass.

## Consolidated Remediation Rule

Do not run finding → patch → audit → tiny finding → patch → audit for
every individual issue. Instead: reviewer produces consolidated findings,
then one bundled remediation pass where practical, then one bounded
closure review. Additional loops are allowed only when a genuine gating
defect remains.

## Finding Routing

- Ordinary implementation defect → Muse, **PATCH — DO NOT REWRITE**.
- Architecture / contract / security / financial defect → Claude,
  **PATCH — DO NOT REWRITE**.
- New business decision required → STOP → Human.
- Codex finding → Muse or Claude → targeted tests → bounded Codex
  re-audit.

## One File / One Active Owner

Maintain one file, one active writer at a time. No concurrent
free-writing by Qwen, Muse, and Claude on the same source file. Reviews
are READ-ONLY unless remediation ownership is explicitly transferred.

## Testing

Use targeted tests during implementation/remediation. Use full regression
before final Codex closure. Use real MySQL only when required for
locking, concurrency, database semantics, or MySQL-specific behavior.
Never use the development database destructively — disposable test
database only.

## Anti-Stall

The existing global anti-stall rule is preserved unchanged: STALL ≠ STOP
THE WHOLE WORKFLOW. NO OUTPUT ≠ PASS. NO OUTPUT ≠ AUTOMATIC FAILURE.
Record PASS / FAIL / NOT VERIFIED / INDETERMINATE / SKIPPED — ANTI-STALL
accurately. A stalled non-gating verification must not terminate
unrelated work. A gating verification gets one safe alternative attempt;
if still unverified, record INDETERMINATE and continue independent work
while preserving the unresolved gate.

## Special Sensitivity

- **CR-001-G** (Zakat/Fidyah calculator): policy/version/provenance
  boundaries require strict review — no invented rates, approval limits,
  or accounting treatment (per `AGENTS.md` "Never" and "Financial
  Rules").
- **CR-001-J** (Security/Compatibility/final remediation): requires
  strict DeepSeek + Codex closure. `CODEX-FE009-01` remains
  **OPEN / VALID / UNRESOLVED**, owner **CR-001-J**, until resolved
  there.

## Phase Boundaries

Sequence: B → C → D → E → F → G → H → I → J.

- CR-001-B: FINAL / LOCKED.
- CR-001-C Recon: FINAL / HUMAN-APPROVED.
- CR-001-D: NOT STARTED.

A later phase must not start before the current phase's required Human
Gate/finalization.

## CR-001-C Current Implementation Authority

After this routing artifact is verified, Muse Spark 1.3 Contributor may
be authorized as PRIMARY IMPLEMENTATION WRITER for CR-001-C. Implementation
must follow the Human-approved `docs/ai-handoff/CR-001/C-RECON.md` and
locked CR-001 architecture.

Future CR-001-C implementation map (per C-RECON.md):

- CREATE: `app/Http/Controllers/Admin/SiteDesignController.php`
- CREATE: `app/Services/Theme/SiteDesignCloneService.php`
- MODIFY: `app/Services/Theme/ThemeNavigationService.php`
- SHARED / COORDINATION REQUIRED: `routes/web.php`
- Vue surfaces: the exact approved Site Design admin pages recorded in
  C-RECON.md §48.

`PublicRenderer.php` remains READ-ONLY for CR-001-C. FE-CHK-009 protected
work must be preserved throughout.

## Model Routing Pre-Flight

For each agent, Human selects the exact model externally; Human's
external selection is authoritative. Each task record states:

```
ASSIGNED MODEL: <exact model>
MODEL ROUTING VERIFICATION: HUMAN-VERIFIED IN COMMAND CODE
MODEL SELF-INTROSPECTION: NOT USED
```

An agent's internal model self-report must not cause a false routing
failure.

## Governance

This routing document changes execution efficiency and role assignment
only. It does not weaken and does not change: Human authority, locked
architecture, RBAC, audit, financial invariants, security default-deny,
the shared-hosting requirement, the Theme presentation-only boundary, or
canonical domain ownership. It does not amend global Multi-Agent Workflow
V3.
