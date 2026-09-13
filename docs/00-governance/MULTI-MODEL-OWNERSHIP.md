# Multi-Model AI Implementation Ownership Governance Amendment

## Status

`PROPOSED — AWAITING INDEPENDENT CODEX REVIEW`

This amendment is materialized under explicit Human authorization to create it. It is a Level 6
(Governance / Operating Instructions) document under
[DOCUMENT-AUTHORITY.md](DOCUMENT-AUTHORITY.md) — it changes only the operational assignment of AI
implementation/review responsibilities described in
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md), and does not itself constitute a
change to Level 1-4 (Human Decisions, Master Requirements, Locked Architecture Baseline, or
Approved ADR/Amendments). It is not an ADR: it does not change locked architecture, business
rules, financial semantics, or security boundaries — see "Source-of-Truth Preservation" below.

This status remains `PROPOSED` until an independent Codex review has run against it and the
result is recorded in "Approval" below. Until then, this document governs on a provisional basis
for planning purposes only — it does **not** by itself authorize any IMP-004 (or later)
implementation work. See [IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md) and
[CHANGE-CONTROL.md](CHANGE-CONTROL.md) for how this amendment relates to the existing governance
hierarchy.

## Purpose

Replaces the operational assumption that Claude Code is the permanent, universal Primary
Implementation Engineer with a per-IMP-stage, explicitly assigned **Primary Implementation
Owner** role, drawn from an approved multi-model baseline. This is a process/operational change
only.

## Source-of-Truth Preservation

The canonical hierarchy defined in [DOCUMENT-AUTHORITY.md](DOCUMENT-AUTHORITY.md) is unchanged:

```
1. Human Decisions
2. Master Requirements
3. Locked Architecture Baseline
4. Approved ADR / Architecture Amendments
5. Implementation Specifications
6. Governance / Operating Instructions   <- this amendment lives here
7. Implementation Artifacts — Code and Tests
```

The Human remains the final approval authority for everything this amendment does not change:
Level 1-4 decisions, Stage Gate approval, and exceptional ownership transfer. No AI model — under
this amendment or any prior governance — may approve its own change to a locked baseline. AI
consensus across multiple models is not Human approval (see "Human Authority" below).

## Primary Implementation Owner (Role Definition)

**Primary Implementation Owner** is now a governance role assigned explicitly, per IMP stage —
not a fixed identity. Claude Code remains an approved implementation engine, particularly for
mission-critical stages (see "Mission-Critical Claude Stages"), but is no longer the sole/default
Primary Implementation Owner for every stage.

Each IMP specification must explicitly record, before implementation begins:

- Primary Implementation Owner
- Primary Model
- Exact Model ID where applicable
- Execution Environment
- Specialist Reviewer(s)
- Independent Formal Reviewer
- Human Approval Authority

No implicit or default model substitution is permitted. If the assigned model/owner is
unavailable, implementation **stops** and this is reported — it is never silently substituted
(see "Model Change Control").

`docs/implementation/IMPLEMENTATION-SPEC-TEMPLATE.md` carries an "Implementation Ownership"
section for this purpose (see that file for the exact fields).

## Approved Model Baseline

### Kimi

- Primary Model: `Kimi K3`
- Exact Model ID: `moonshotai/Kimi-K3`
- Execution Environment: `Command Code GOAT`
- Primary use: backend/domain implementation; multi-file implementation; long-horizon
  implementation; domain workflows.

### Qwen

- Primary Model: `Qwen 3.8 Max 0902`
- Exact Model ID: `Qwen/Qwen3.8-Max-0902`
- Execution Environment: `Command Code GOAT`
- Primary use: Vue/Inertia frontend; CMS; Theme Engine; reporting; presentation-heavy
  implementation; suitable refactoring.

### DeepSeek

- Specialist Model: `DeepSeek V4 Pro`
- Exact Model ID: `deepseek/deepseek-v4-pro`
- Execution Environment: `Command Code GOAT`
- Primary specialist domains: database; transactions; concurrency; state machines; payment;
  finance; security-sensitive logic.
- Default specialist mode: `READ ONLY`. DeepSeek does not automatically become a concurrent
  implementation editor merely because it is assigned as specialist for a stage — see
  "Specialist Authority".

### Claude Code

- Role: Approved Primary Implementation Owner for designated mission-critical stages (see
  "Mission-Critical Claude Stages"). Remains preferred where financial/security correctness and
  tightly controlled remediation justify the existing single-owner, PATCH-DO-NOT-REWRITE
  implementation/remediation workflow already used for IMP-002 and IMP-003.

### Codex

- Role: Independent Formal Reviewer / Auditor.
- Default formal-review mode: `CODING: NO`.
- Codex must remain independent from the Primary Implementation Owner for the same Stage Gate,
  and must not silently remediate code during an independent formal audit. If remediation is
  required, findings return to the authorized Primary Implementation Owner unless Human-approved
  ownership transfer occurs. This preserves and does not weaken the existing
  [IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md) Codex role.

### ChatGPT

- Role: Chief Architect support; requirements/specification; governance coordination; stage
  planning; prompt preparation; Human decision facilitation. Does not replace Human approval
  authority. Unchanged from the existing ChatGPT role in
  [IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md).

## Amendment Clauses (Human-Approved Additions)

The following clauses were explicitly approved by Human, verbatim, as additions to this
provisional amendment. They are recorded here exactly and then restated operatively in the
sections they affect (IMP-030 ownership matrix row and Codex role; Mission-Critical Claude
Stages). They do not change this document's overall `PROPOSED — AWAITING INDEPENDENT CODEX
REVIEW` status.

> **Human, verbatim:**
>
> "Saya setuju.
>
> GOV-MM-001:
> IMP-030 adalah Audit-Only Stage.
> Codex menjadi Primary Audit Owner, bukan Primary Implementation Owner.
> Codex tidak menjadi Independent Reviewer atas auditnya sendiri.
> Hasil final IMP-030 memerlukan Human Final System Approval.
> Remediation dikembalikan kepada Primary Implementation Owner IMP terkait dan kemudian Codex
> melakukan re-audit.
>
> GOV-MM-002:
> Claude Code menggunakan Per-IMP Model Binding untuk tahap mission-critical.
> Sebelum implementasi setiap IMP yang dimiliki Claude Code, exact Claude model/name/identifier
> yang tersedia harus dicatat dan mendapat Human approval.
> Model tersebut kemudian dipin untuk IMP tersebut.
> Silent substitution dilarang.
> Perubahan model setelah binding memerlukan Model Change Request dan Human approval."

### GOV-MM-001 — IMP-030 Audit-Only Stage

IMP-030 is an **Audit-Only Stage**, not an implementation stage:

- Codex is the **Primary Audit Owner** of IMP-030 — this is a distinct role from "Primary
  Implementation Owner" used everywhere else in this document. IMP-030 has no Primary
  Implementation Owner; nothing is implemented under IMP-030 itself.
- Codex does **not** act as Independent Reviewer of its own IMP-030 audit — a formal reviewer
  cannot independently review itself. There is therefore no Codex-independent-review step
  between the IMP-030 audit output and Human Stage Gate for IMP-030 specifically (contrast with
  every other IMP, where Codex reviews the Primary Implementation Owner's work).
- The final result of IMP-030 requires **Human Final System Approval** directly — this is
  additional to, and stronger than, the ordinary per-IMP Human Stage Gate: it is a whole-system
  approval, not a single-stage one.
- Any remediation finding that IMP-030's audit surfaces against a specific prior IMP is returned
  to that IMP's own Primary Implementation Owner (per "Remediation Ownership" above) — Codex does
  not remediate it itself. Once remediated, Codex performs a **re-audit** of that finding as part
  of IMP-030, not a fresh independent review of a different owner's unrelated new work.

This changes the "030 | Final Implementation Audit | Codex | ..." matrix row's semantics: "Codex"
in the Primary Owner column for IMP-030 means **Primary Audit Owner**, never "Primary
Implementation Owner" — see the matrix row's note below.

### GOV-MM-002 — Per-IMP Model Binding for Claude Code Mission-Critical Stages

For each of the mission-critical stages assigned to Claude Code (see "Mission-Critical Claude
Stages" below), Claude Code uses **Per-IMP Model Binding**, not a single fixed model identifier
for all of them:

- Before implementation of that specific IMP begins, the exact Claude model name/identifier
  actually available for use must be recorded and must receive explicit Human approval.
- That exact model is then **pinned/bound** to that IMP only — not to Claude Code's role in
  general, and not automatically carried over to the next mission-critical IMP.
- Silent substitution is prohibited — the same rule as "Model Version Pinning"/"Model Change
  Control" above, applied per-IMP rather than once for the whole baseline.
- Changing the bound model after binding (e.g. a newer Claude generation becomes available mid-
  stage) requires a Model Change Request and fresh Human approval, exactly as "Model Change
  Control" describes — it does not get a lighter-weight process merely because it is Claude
  rather than a Command Code model.

Each mission-critical IMP's execution evidence (see "Execution Evidence" below) must therefore
carry a "Claude Model Binding" record in addition to the standard fields.

## Non-Concurrent Ownership Rule

Preserves and strengthens the existing rule from
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md) and
[BRANCHING-POLICY.md](BRANCHING-POLICY.md):

```
ONE FILE / ONE ACTIVE OWNER
ONE IMP / ONE PRIMARY IMPLEMENTATION OWNER
```

Multiple AI systems must not concurrently edit the same implementation scope. Prohibited:

```
Kimi + Qwen + DeepSeek + Claude simultaneously editing the same files/branch.
```

Preferred sequence:

```
Specification
  -> Primary Owner implementation
    -> Specialist READ-ONLY analysis where authorized
      -> Primary Owner remediation
        -> Codex independent audit
          -> Human Stage Gate
```

Specialists may recommend patches but do not edit unless formally transferred ownership for a
clearly bounded task, with governance evidence of that transfer (see "Remediation Ownership").

## Remediation Ownership

Findings discovered by Codex or a specialist return to the same Primary Implementation Owner by
default. Example:

```
Kimi implementation -> DeepSeek read-only specialist review -> Kimi remediation
  -> Codex formal audit
```

The implementation owner must not be silently switched during remediation. Ownership transfer
requires explicit governance evidence (recorded in the IMP's execution evidence, see below) and
must never result in concurrent editing.

## Fixed IMP Ownership Matrix

| IMP | Domain | Primary Owner | Specialist |
|---|---|---|---|
| 003 | RBAC + Scope + Business Authority | Claude Code | — (Completed prior to this amendment) |
| 004 | Audit + Governance Foundation | Kimi K3 | DeepSeek V4 Pro |
| 005 | CMS | Qwen 3.8 Max 0902 | Kimi K3 |
| 006 | Theme Engine | Qwen 3.8 Max 0902 | Kimi K3 |
| 007 | Campaign + Program + Fund | Kimi K3 | DeepSeek V4 Pro |
| 008 | Donation | Kimi K3 | DeepSeek V4 Pro |
| 009 | Payment Hub | Claude Code | DeepSeek V4 Pro |
| 010 | Ledger Foundation | Claude Code | DeepSeek V4 Pro |
| 011 | Financial Consequence Posting | Claude Code | DeepSeek V4 Pro |
| 012 | Operational Fee | Kimi K3 | DeepSeek V4 Pro |
| 013 | Fundraiser + Attribution | Kimi K3 | DeepSeek V4 Pro |
| 014 | Commission | Claude Code | DeepSeek V4 Pro |
| 015 | Withdrawal | Claude Code | DeepSeek V4 Pro |
| 016 | Refund | Claude Code | DeepSeek V4 Pro |
| 017 | Reconciliation + Moota | Kimi K3 | DeepSeek V4 Pro |
| 018 | Partner | Kimi K3 | Qwen 3.8 Max 0902 |
| 019 | Zakat/Wakaf/Fidyah/Qurban | Kimi K3 | DeepSeek V4 Pro |
| 020 | Beneficiary | Kimi K3 | Qwen 3.8 Max 0902 |
| 021 | Distribution | Kimi K3 | DeepSeek V4 Pro |
| 022 | Impact | Qwen 3.8 Max 0902 | Kimi K3 |
| 023 | Receipt + Compliance Documents | Kimi K3 | Qwen 3.8 Max 0902 |
| 024 | Notifications + WhatsApp | Kimi K3 | DeepSeek V4 Pro |
| 025 | REST API | Kimi K3 | DeepSeek V4 Pro |
| 026 | Reporting + Search | Qwen 3.8 Max 0902 | Kimi K3 |
| 027 | Security Hardening | Claude Code | DeepSeek V4 Pro |
| 028 | Performance | Qwen 3.8 Max 0902 | DeepSeek V4 Pro |
| 029 | Deployment | Kimi K3 | DeepSeek V4 Pro |
| 030 | Final Implementation Audit (Audit-Only Stage) | Codex — **Primary Audit Owner**, not Primary Implementation Owner (see GOV-MM-001) | Kimi/DeepSeek READ ONLY if explicitly required |

For IMP-004 onward, Codex remains the Independent Formal Reviewer unless an explicitly approved
exception is recorded. IMP-000 through IMP-003 were completed under the prior single-owner
(Claude Code) governance and this table does not retroactively alter their FINAL/LOCKED status or
evidence.

IMP-030 is the sole exception to "one Primary Implementation Owner per IMP": it has no Primary
Implementation Owner at all, because it implements nothing — see "GOV-MM-001 — IMP-030 Audit-Only
Stage" above for the full ownership/review/remediation contract that applies to it instead.

## Mission-Critical Claude Stages

The following remain assigned to Claude Code and must not be automatically transferred to a
Command Code model:

- IMP-009 Payment Hub
- IMP-010 Ledger Foundation
- IMP-011 Financial Consequence Posting
- IMP-014 Commission
- IMP-015 Withdrawal
- IMP-016 Refund
- IMP-027 Security Hardening

Per **GOV-MM-002** (see "Amendment Clauses" above), each of these stages uses **Per-IMP Model
Binding**: the exact Claude model available is recorded and Human-approved before that specific
IMP's implementation begins, then pinned to that IMP only. It is not a single binding that
carries across all seven stages, and it is not satisfied by this document's general "Claude Code"
baseline entry alone.

## Model Version Pinning

Exact model identifiers are governance-controlled. Current Command Code baseline:

```
Kimi:     moonshotai/Kimi-K3
Qwen:     Qwen/Qwen3.8-Max-0902
DeepSeek: deepseek/deepseek-v4-pro
```

For governed Primary Implementation execution, do NOT use `latest`, `auto`, unspecified
replacement models, or silent fallback models. Each execution evidence record must preserve the
exact model identity actually used.

## Model Change Control

Future model upgrades or replacements (a future Kimi/Qwen/DeepSeek generation, or a different
Claude generation) must not silently replace the approved baseline. Required process:

```
Model Change Request
  -> compatibility assessment
    -> coding/reasoning/security benchmark where appropriate
      -> governance impact assessment
        -> Human approval
          -> governance amendment/update
            -> use in governed implementation
```

Provider deprecation or outage does not authorize silent substitution. If an approved model is
unavailable for a governed stage: **stop and report** — do not substitute.

## Execution Evidence

Each governed IMP implementation must record, where applicable:

```
IMP ID
Primary Implementation Owner
Model name
Exact Model ID
Execution Environment
Command Code version (when Command Code is used)
Implementation branch
Implementation commit
Specialist model/reviewer
Independent reviewer
Review commit
Test results
Stage Gate result
```

Example:

```
IMP-004
Primary Implementation Owner: KIMI
Primary Model: Kimi K3
Exact Model ID: moonshotai/Kimi-K3
Execution Environment: Command Code GOAT
Specialist Reviewer: DeepSeek V4 Pro — READ ONLY
Independent Formal Reviewer: Codex
Human Approval Authority: Human
Concurrent Editing: PROHIBITED
```

### Claude Model Binding (Mission-Critical Stages Only, GOV-MM-002)

In addition to the standard fields above, each of the seven Mission-Critical Claude Stages must
record, before implementation begins:

```
Bound Model Name/Identifier:
Binding Recorded Date:
Human Approver (binding):
Human Approval Date (binding):
Model Change Requests against this binding (if any): list, each with date + Human approval
```

Example:

```
IMP-009 Payment Hub
Primary Implementation Owner: Claude Code
Bound Model Name/Identifier: <exact Claude model identifier available at binding time>
Binding Recorded Date: <date>
Human Approver (binding): Human
Human Approval Date (binding): <date>
Model Change Requests against this binding: none
```

### IMP-030 Execution Evidence (GOV-MM-001)

IMP-030 records a variant of the standard fields, reflecting its Audit-Only Stage / Primary Audit
Owner contract instead of a Primary Implementation Owner:

```
IMP-030
Primary Audit Owner: Codex
Independent Reviewer of this audit: NONE (Codex cannot review its own audit — see GOV-MM-001)
Findings returned to: <Primary Implementation Owner of each affected prior IMP>
Remediation commit(s): <per affected IMP>
Re-audit result: <per affected IMP>
Human Final System Approval: <status>
```

## Specialist Authority

A Specialist Reviewer is advisory by default. Specialist review must not:

- expand permissions;
- change requirements;
- modify locked architecture;
- make financial-policy decisions;
- approve its own recommendations;
- silently edit Primary Owner files.

Expected specialist output (DeepSeek especially): finding, risk, invariant affected, evidence,
recommended remediation, suggested tests. The Primary Owner implements authorized remediation —
the specialist does not implement it directly.

## Codex Independence

Unchanged and reaffirmed: Codex formal review remains separate from implementation. Formal audit
sequence:

```
Primary implementation complete
  -> repository clean
    -> tests/quality evidence
      -> Codex independent review
        -> findings
          -> Primary Owner remediation if necessary
            -> Codex re-audit
              -> Human Stage Gate
```

During formal review: `CODING: NO`, unless Human explicitly authorizes a separate ownership
transition.

**Exception:** IMP-030 (see GOV-MM-001 under "Amendment Clauses"). Because Codex is IMP-030's own
Primary Audit Owner, this sequence's "Codex independent review" step does not apply to IMP-030
itself — Codex cannot independently review its own audit. IMP-030's output goes directly to
**Human Final System Approval** instead of an intermediate Codex-review step. This exception is
scoped to IMP-030 alone; Codex remains the Independent Formal Reviewer for every implementation
IMP (004 and onward) exactly as described elsewhere in this document.

## Human Authority

Unchanged and reaffirmed. Human remains final authority for: locked business decisions;
architecture baseline changes; security-policy changes; financial-policy changes;
model-governance changes; Stage Gate approval; exceptional ownership transfer. AI consensus is
not Human approval — three models agreeing with each other does not alter a locked baseline.

## IMP-004 Boundary

This amendment does **not** authorize IMP-004 implementation. Sequence required after this
amendment is created:

```
1. Amendment created (this document).
2. Independent Codex review.
3. Human approval of the amendment itself.
4. Amendment committed and declared FINAL / LOCKED.
5. Only then: IMP-004 readiness/specification may proceed.
```

IMP-004 intended ownership after this governance amendment is locked:

```
Primary:                    Kimi K3
Exact ID:                   moonshotai/Kimi-K3
Specialist:                 DeepSeek V4 Pro — READ ONLY
Independent Formal Reviewer: Codex
Human:                       Final Stage Gate authority
```

## Approval

Do not fill these fields with invented approval data — leave them blank/PENDING until the actual
event occurs.

```
Creation Authorization:
  Authority:        Human
  Statement:        Human approved proceeding with formal materialization of the previously
                     agreed multi-model implementation governance (recorded in the session that
                     produced this document; not a verbatim quoted approval string — distinct
                     from the exact quoted Stage Gate approvals recorded for IMP-000 through
                     IMP-003, e.g. "Saya setuju. IMP-003 Stage Gate Approved.").
  Scope:            Creating this amendment, updating governance documentation to reference it,
                     defining AI implementation ownership rules, recording the approved model
                     baseline and ownership matrix. Does NOT authorize IMP-004 implementation,
                     application source changes, architecture/database/security/financial/
                     business-rule changes, or git push.

Amendment Clause Approval (GOV-MM-001, GOV-MM-002):
  Authority:        Human
  Statement:        "Saya setuju.

                     GOV-MM-001:
                     IMP-030 adalah Audit-Only Stage.
                     Codex menjadi Primary Audit Owner, bukan Primary Implementation Owner.
                     Codex tidak menjadi Independent Reviewer atas auditnya sendiri.
                     Hasil final IMP-030 memerlukan Human Final System Approval.
                     Remediation dikembalikan kepada Primary Implementation Owner IMP terkait
                     dan kemudian Codex melakukan re-audit.

                     GOV-MM-002:
                     Claude Code menggunakan Per-IMP Model Binding untuk tahap mission-critical.
                     Sebelum implementasi setiap IMP yang dimiliki Claude Code, exact Claude
                     model/name/identifier yang tersedia harus dicatat dan mendapat Human
                     approval. Model tersebut kemudian dipin untuk IMP tersebut. Silent
                     substitution dilarang. Perubahan model setelah binding memerlukan Model
                     Change Request dan Human approval."
  Scope:            Approves incorporating GOV-MM-001 and GOV-MM-002 into this still-provisional
                     amendment. Does not by itself move this document's overall Status out of
                     PROPOSED, and does not authorize IMP-004 (or IMP-030) work — see
                     "Independent Codex Review" and "Final Human Lock Approval" below, both still
                     PENDING for the amendment as a whole (GOV-MM-001/002 included).

Independent Codex Review:
  Status:            PENDING
  Reviewer:
  Review Date:
  Findings:
  Evidence/Reference:

Final Human Lock Approval:
  Status:            PENDING
  Human Approver:
  Approval Date:
  Approval Evidence:
```

## References

- [IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md) — AI Role Assignment (amended to
  forward-reference this document)
- [AI-WORKFLOW.md](AI-WORKFLOW.md) — per-task operational loop (amended to forward-reference this
  document)
- [DOCUMENT-AUTHORITY.md](DOCUMENT-AUTHORITY.md) — canonical hierarchy (unchanged; this document
  added to the Level 6 list)
- [CHANGE-CONTROL.md](CHANGE-CONTROL.md) — ACR/ADR workflow for any future change that would touch
  Levels 1-4 (not applicable to this amendment itself)
- `docs/implementation/IMPLEMENTATION-SPEC-TEMPLATE.md` — "Implementation Ownership" section added
  for future IMP specs to record the fields required by this amendment
- `docs/audits/IMP-003-FINALIZATION.md` — most recent Stage Gate prior to this amendment;
  unchanged by this amendment
