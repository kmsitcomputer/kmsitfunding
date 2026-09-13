# Multi-Model AI Implementation Ownership Governance Amendment

## Status

```
Base Amendment (V1):   FINAL / LOCKED
Amendment V2:          PROPOSED — AWAITING INDEPENDENT CODEX AUDIT
```

The base amendment (V1 — Primary Implementation Owner as a per-IMP role, the original Kimi/Qwen/
DeepSeek/Claude/Codex/ChatGPT/Human model baseline, GOV-MM-001, GOV-MM-002) is materialized under
explicit Human authorization to create it, subsequently remediated against Codex's independent
findings, and is `FINAL / LOCKED` under explicit Human Final Approval following a Codex PASS
re-audit — see "Approval" below for both records verbatim. It is a Level 6 (Governance /
Operating Instructions) document under [DOCUMENT-AUTHORITY.md](DOCUMENT-AUTHORITY.md) — it
changes only the operational assignment of AI implementation/review responsibilities described in
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md), and does not itself constitute a
change to Level 1-4 (Human Decisions, Master Requirements, Locked Architecture Baseline, or
Approved ADR/Amendments). It is not an ADR: it does not change locked architecture, business
rules, financial semantics, or security boundaries — see "Source-of-Truth Preservation" below.

**Amendment V2** (see "Amendment V2 — Command Code Model Generation Replacement" below) patches
this same document to replace the prospective Command Code implementation-agent generation (Kimi
K3 / Qwen 3.8 Max 0902 / DeepSeek V4 Pro -> DeepSeek V4.1 Flash / Qwen 3.8 Flash / Kimi K2.7 Code)
and the resulting ownership matrix. **Amendment V2 remains `PROPOSED` — it is not self-declared
`FINAL / LOCKED` by this patch** — until an independent Codex audit and a separate Human Final
Approval for V2 specifically are both recorded in "Approval" below, mirroring exactly how V1
itself was locked. Everything V1 already established that V2 does not touch (Primary
Implementation Owner as a role, GOV-MM-001/IMP-030 Audit-Only Stage, GOV-MM-002/Claude Per-IMP
Model Binding, Non-Concurrent Ownership, Codex Independence, Human Authority) remains `FINAL /
LOCKED` and unaffected.

`FINAL / LOCKED` (V1) and `PROPOSED` (V2) together govern this document's own operational rules as
the authoritative Level 6 baseline. Neither by itself authorizes IMP-004 (or later) implementation
work, and neither authorizes IMP-030 work — those each require their own separate Human
authorization at their own gate. See [IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md)
and [CHANGE-CONTROL.md](CHANGE-CONTROL.md) for how this amendment relates to the existing
governance hierarchy, and "IMP-004 Boundary" below for the explicit readiness-vs-implementation
distinction, now including the Amendment-V2-lock + valid-binding hold described there.

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
- Exact Model ID — for Kimi/Qwen/DeepSeek this is the pinned baseline ID from "Model Version
  Pinning" below; for Claude Code on a mission-critical stage, this field is satisfied **only**
  by a completed `BOUND` GOV-MM-002 Per-IMP Model Binding record (see "Claude Model Binding"
  under "Execution Evidence") — never by a placeholder, a general "Claude Code" label, or an
  unbound/unresolved state
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

**As of Amendment V2 (PROPOSED — see "Amendment V2" below for the full record), the Command Code
implementation-agent generation is DeepSeek V4.1 Flash / Qwen 3.8 Flash / Kimi K2.7 Code.** The
original V1 generation (Kimi K3 / Qwen 3.8 Max 0902 / DeepSeek V4 Pro) is retired from prospective
ownership — see "Retired Models (V1 Generation)" below. No IMP has yet been implemented under
either generation of this multi-model governance (IMP-004 is the first, and its implementation
remains not authorized regardless of Amendment V2's status), so this retirement rewrites no
historical execution record.

### DeepSeek (Primary Implementation Owner)

- Primary Model: `DeepSeek V4.1 Flash`
- Exact Model ID: `deepseek/deepseek-v4.1-flash`
- Execution Environment: `Command Code`
- Primary use: backend; security; governance implementation; database; integration;
  reconciliation; REST API; deployment.

### Qwen (Primary Implementation Owner)

- Primary Model: `Qwen 3.8 Flash`
- Exact Model ID: `qwen/qwen3.8-flash`
- Execution Environment: `Command Code`
- Primary use: CMS; Theme Engine; presentation/application features; reporting; search;
  performance.

### Kimi (Primary Implementation Owner)

- Primary Model: `Kimi K2.7 Code`
- Exact Model ID: `moonshotai/kimi-k2.7-code`
- Execution Environment: `Command Code`
- Primary use: repository-level implementation; domain implementation; long-horizon coding;
  complex application modules; cross-file implementation.

### Retired Models (V1 Generation, Superseded by Amendment V2)

Retained here for traceability only — never used for new prospective IMP ownership once Amendment
V2 is `FINAL / LOCKED`:

```
Kimi K3            moonshotai/Kimi-K3          — retired, superseded by Kimi K2.7 Code
Qwen 3.8 Max 0902  Qwen/Qwen3.8-Max-0902       — retired, superseded by Qwen 3.8 Flash
DeepSeek V4 Pro    deepseek/deepseek-v4-pro    — retired, superseded by DeepSeek V4.1 Flash
```

Specialist role note: unlike V1 (where DeepSeek V4 Pro was a dedicated READ-ONLY specialist
distinct from Kimi/Qwen's Primary Owner role), Amendment V2's three Command Code models are all
Primary Implementation Owners for their respective domains — see "Specialists" below for the
(now default-none) specialist model.

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
amendment (at the time they were added, the amendment was still provisional; both clauses were
subsequently carried into the Codex targeted re-audit and the Final Human Lock Approval recorded
in "Approval" below, and are now part of the `FINAL / LOCKED` baseline). They are recorded here
exactly and then restated operatively in the sections they affect (IMP-030 ownership matrix row
and Codex role; Mission-Critical Claude Stages).

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
- **Codex PASS at IMP-030 does not equal Human Final System Approval.** These are two distinct
  gates. A Codex PASS on the final audit means only that Codex found no unresolved gate-impact
  finding — it is a necessary input to Human Final System Approval, never a substitute for it.
  Human approval of the final system release remains mandatory regardless of the audit result.
- IMP-030 does **not** require, and must never be represented as requiring, a fictional second
  Codex reviewer to review Codex's own IMP-030 audit — no such role exists in this governance.
  Codex's IMP-030 deliverable is an independent final audit of the entire implementation program
  (IMP-001 through IMP-029), not a review of a single stage's diff, and Human is the sole final
  approval authority over the resulting system-release/final-system gate.
- If additional independent review of IMP-030's own audit is ever required for any reason, it
  must be separately Human-authorized as an explicit exception, naming a genuinely independent
  reviewer — it must never be represented as "Codex reviewing itself."

The full IMP-030 authority boundary:

```
IMP-001 ... IMP-029
        |
        v
IMP-030 Final Implementation Audit
        |
        v
Codex = Primary Audit Owner
        |
        v
     Findings?
        |
   +----+----+
   |         |
  YES        NO
   |         |
   v         |
Return finding to the responsible
Primary Implementation Owner
   |
   v
Remediation
   |
   v
Codex Re-Audit ---------+
                         |
   +---------------------+
   |
   v
No unresolved gate-impact findings
   |
   v
HUMAN FINAL SYSTEM APPROVAL   <- mandatory; Codex PASS alone never satisfies this
```

This changes the "030 | Final Implementation Audit | Codex | ..." matrix row's semantics: "Codex"
in the Primary Owner column for IMP-030 means **Primary Audit Owner**, never "Primary
Implementation Owner" — see the matrix row's note below. The matrix row's "Specialist / Review"
cell for IMP-030 names Human Final System Approval explicitly for this same reason — it is not an
optional or implied step.

### GOV-MM-002 — Per-IMP Model Binding for Claude Code Mission-Critical Stages

For each of the mission-critical stages assigned to Claude Code (see "Mission-Critical Claude
Stages" below), Claude Code uses **Per-IMP Model Binding**, not a single fixed model identifier
for all of them. No Claude model/generation is invented, guessed, or silently selected on behalf
of Human by this amendment — see "Mission-Critical Claude Stages" below, which assigns none now.

- Before implementation of that specific IMP begins, the exact Claude model name/identifier
  actually available for use (the actual product-visible model name and its exact identifier at
  execution time) must be recorded and must receive explicit Human approval.
- That exact model is then **pinned/bound** to that IMP only — not to Claude Code's role in
  general, and not automatically carried over to the next mission-critical IMP.
- Silent substitution is prohibited — the same rule as "Model Version Pinning"/"Model Change
  Control" above, applied per-IMP rather than once for the whole baseline.
- Changing the bound model after binding (e.g. a newer Claude generation becomes available mid-
  stage) requires a Model Change Request and fresh Human approval — see "Model Change After
  Binding" below. It does not get a lighter-weight process merely because it is Claude rather
  than a Command Code model.
- This requirement is **prospective only**. It does not retroactively apply to IMP-003, which is
  already `FINAL / LOCKED` under the prior single-owner governance — its historical
  implementation evidence (`docs/audits/IMP-003-*.md`) is not rewritten or reinterpreted to add a
  binding record it never required at the time. GOV-MM-002 governs IMP-009, IMP-010, IMP-011,
  IMP-014, IMP-015, IMP-016, and IMP-027 going forward, once each reaches its own implementation
  gate.

#### Binding State Model

```
UNBOUND
   |
   v
MODEL RESOLVED               (exact Claude model name/identifier determined)
   |
   v
AWAITING HUMAN MODEL APPROVAL
   |
   v
BOUND                         (Human-approved; pinned to this IMP only)
   |
   v
IMPLEMENTATION MAY PROCEED
```

Only `BOUND` is an acceptable state for a Claude-owned governed implementation to begin. If the
exact model identity cannot be determined at any point in this sequence: **STOP** — implementation
must not begin, and this is reported rather than guessed.

#### Silent Substitution (Explicitly Prohibited)

The following are all prohibited under a `BOUND` binding, with no exception:

- automatic Claude model upgrades;
- automatic Claude model downgrades;
- a provider-selected fallback that changes the governed model identity;
- switching Claude generations during implementation without a Model Change Request;
- describing a materially different Claude model merely as generic "Claude Code" (the exact
  bound model name/identifier is what governs, not the product label).

If the execution environment silently changes the actual model away from the approved `BOUND`
binding and that change is detected at any point: **STOP**. Implementation must not continue
under the new, unapproved model — the discrepancy is reported and a Model Change Request is
raised before any further governed work resumes.

#### Model Change After Binding

```
Model Change Request
        |
        v
Reason / availability issue
        |
        v
Compatibility assessment
        |
        v
Impact assessment
        |
        v
Human approval
        |
        v
Binding update
        |
        v
Resume implementation
```

No Human approval means **NO MODEL CHANGE** — the prior `BOUND` model remains the only approved
one, and if it is genuinely unavailable, implementation stops rather than substituting silently.
Provider outage or deprecation does not, by itself, authorize silent substitution.

Each mission-critical IMP's execution evidence (see "Execution Evidence" below) must therefore
carry a "Claude Model Binding" record in addition to the standard fields.

## Amendment V2 — Command Code Model Generation Replacement

**Status: `PROPOSED` — not self-declared `FINAL / LOCKED` by this patch.** Materialized under
Human authorization to replace the Command Code implementation-agent generation, following the
exact "Model Change Control" process this document itself already requires (Model Change Request
-> compatibility/impact review -> Human approval -> governance amendment/update -> new binding) —
this section and the operative updates elsewhere in this document ARE that governance
amendment/update step; the "new binding" step (Model Version Pinning below, plus IMP-004's own
transition in "IMP-004 Boundary") completes it once Amendment V2 itself is locked.

This authorization instructs replacing the prospective Command Code generation and updating
governance documentation accordingly; it is recorded here descriptively rather than as a single
short verbatim quote (unlike GOV-MM-001/002 above, the instruction was supplied as a full
structured governance-patch task, not a short quoted sentence) — see "Approval" below for the
exact authorization-scope record, following the same "do not invent approval data" discipline as
every other Approval entry in this document.

### GOV-MM-003 — Replace Command Code Implementation-Agent Generation

```
OLD (retired from prospective ownership, per "Retired Models" above):
  Kimi K3            moonshotai/Kimi-K3
  Qwen 3.8 Max 0902  Qwen/Qwen3.8-Max-0902
  DeepSeek V4 Pro    deepseek/deepseek-v4-pro

NEW (Amendment V2 baseline, per "Approved Model Baseline" above):
  DeepSeek V4.1 Flash  deepseek/deepseek-v4.1-flash
  Qwen 3.8 Flash       qwen/qwen3.8-flash
  Kimi K2.7 Code       moonshotai/kimi-k2.7-code

This replacement is PROSPECTIVE ONLY. No historical record is rewritten: IMP-000 through IMP-003
were completed under the prior single-owner (Claude Code) governance, before either Command Code
generation existed in this document at all, and are unaffected. No IMP has been implemented under
the V1 Command Code generation either — IMP-004 remains the first candidate, and its Primary
Implementation Owner simply changes from Kimi K3 (V1) to DeepSeek V4.1 Flash (V2) as a matter of
prospective assignment, not a historical correction.
```

### Exact Model ID Verification

Every exact model ID in "Approved Model Baseline" above was verified directly against
`cmdc --list-models` output at the time this amendment was drafted — none was invented, guessed,
assumed from a prior naming pattern, or taken from an alias/short-name form:

```
"DeepSeek V4.1 Flash"  ->  deepseek/deepseek-v4.1-flash   (exact match in cmdc --list-models)
"Qwen 3.8 Flash"       ->  qwen/qwen3.8-flash             (exact match in cmdc --list-models)
"Kimi K2.7 Code"       ->  moonshotai/kimi-k2.7-code      (exact match in cmdc --list-models;
                                                            distinct from the also-listed
                                                            "kimi-k2.7-code-highspeed" variant —
                                                            the plain "Code" variant was the one
                                                            named, and is the one used)
```

No `latest`, `auto`, generation-ambiguous alias, or silent-fallback identifier is used for any of
the three. If any of these exact IDs becomes unavailable in a future `cmdc --list-models` listing,
that is a Model Change Request event under "Model Change Control" — not a silent substitution.

### New Primary Ownership Matrix (Amendment V2, Prospective)

Restated in full in "Fixed IMP Ownership Matrix" below (the single authoritative table — this is
not a second, competing matrix): IMP-004/012/017/021/024/025/029 -> DeepSeek V4.1 Flash;
IMP-005/006/022/026/028 -> Qwen 3.8 Flash; IMP-007/008/013/018/019/020/023 -> Kimi K2.7 Code;
IMP-009/010/011/014/015/016/027 -> Claude Code (unchanged — mission-critical, GOV-MM-002 Per-IMP
Model Binding still applies, still no generation assigned); IMP-030 -> Codex, Primary Audit Owner
(unchanged — GOV-MM-001 Audit-Only Stage still applies). IMP-003 remains historically Claude-owned
and `FINAL / LOCKED`, unaffected.

### Specialists Under Amendment V2

Per "Specialists" (§13 of the originating task, restated operatively below): a specialist is
**optional**, default **NONE**, added only when technically justified for a specific IMP, and
`READ ONLY` when used. Amendment V2 does not assign a standing specialist to any IMP the way V1
assigned DeepSeek V4 Pro as a default specialist — each IMP's specification decides, at
specification time, whether a specialist is technically justified for that stage. A model serving
as Primary Owner for an IMP cannot simultaneously be its own specialist/independent reviewer for
that same IMP. Codex remains the Independent Formal Reviewer for every implementation IMP unless
governance explicitly states otherwise (unchanged from V1).

### IMP-004 Transition (Amendment V2)

IMP-004's specification already passed independent Codex specification audit (BLOCKER 0, MAJOR 0,
MINOR 0, EDITORIAL 0, HUMAN DECISION REQUIRED 0) and Human has stated implementation is
authorized for IMP-004 specifically. This amendment does not reopen or redo that specification,
and does not reopen Q26/Q27/Q28. However:

```
IMP-004 IMPLEMENTATION EXECUTION — HOLD

Hold released only when BOTH:
  1. Amendment V2 itself is FINAL / LOCKED (independent Codex audit PASS + separate Human Final
     Approval for V2, recorded in "Approval" below — not yet done); AND
  2. The DeepSeek V4.1 Flash Primary Implementation Owner assignment for IMP-004 is valid under
     that locked baseline (i.e. Amendment V2's Model Version Pinning entry for DeepSeek is in
     effect — no separate per-IMP BOUND state machine is introduced for Command Code models here;
     that stronger mechanism remains GOV-MM-002's Claude-specific one).

Until both conditions hold, IMP-004 implementation execution does not proceed, regardless of the
Human's prior "IMP-004 Implementation Authorized" statement — that statement authorized
IMP-004's implementation in principle; it did not, and could not, bind it to an owner this
amendment had not yet finalized.
```

IMP-004's Primary Implementation Owner, once the hold above is released: `DeepSeek V4.1 Flash`
(`deepseek/deepseek-v4.1-flash`) — see "IMP-004 Boundary" below for the updated intended-ownership
record.

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

**Per Amendment V2 (PROPOSED — see above)**, superseding the V1 matrix's Primary Owner
assignments for IMP-004 through IMP-029 (IMP-003, IMP-009/010/011/014/015/016/027, and IMP-030 are
unchanged from V1). Amendment V2 does not assign a standing default specialist per IMP the way V1
did — the Specialist column now reads "none by default" per "Specialists Under Amendment V2"
above; a specific IMP's own specification may add a technically-justified, READ-ONLY specialist.

| IMP | Domain | Primary Owner | Specialist |
|---|---|---|---|
| 003 | RBAC + Scope + Business Authority | Claude Code | — (Completed prior to this amendment) |
| 004 | Audit + Governance Foundation | DeepSeek V4.1 Flash | none by default |
| 005 | CMS | Qwen 3.8 Flash | none by default |
| 006 | Theme Engine | Qwen 3.8 Flash | none by default |
| 007 | Campaign + Program + Fund | Kimi K2.7 Code | none by default |
| 008 | Donation | Kimi K2.7 Code | none by default |
| 009 | Payment Hub | Claude Code | none by default |
| 010 | Ledger Foundation | Claude Code | none by default |
| 011 | Financial Consequence Posting | Claude Code | none by default |
| 012 | Operational Fee | DeepSeek V4.1 Flash | none by default |
| 013 | Fundraiser + Attribution | Kimi K2.7 Code | none by default |
| 014 | Commission | Claude Code | none by default |
| 015 | Withdrawal | Claude Code | none by default |
| 016 | Refund | Claude Code | none by default |
| 017 | Reconciliation + Moota | DeepSeek V4.1 Flash | none by default |
| 018 | Partner | Kimi K2.7 Code | none by default |
| 019 | Zakat/Wakaf/Fidyah/Qurban | Kimi K2.7 Code | none by default |
| 020 | Beneficiary | Kimi K2.7 Code | none by default |
| 021 | Distribution | DeepSeek V4.1 Flash | none by default |
| 022 | Impact | Qwen 3.8 Flash | none by default |
| 023 | Receipt + Compliance Documents | Kimi K2.7 Code | none by default |
| 024 | Notifications + WhatsApp | DeepSeek V4.1 Flash | none by default |
| 025 | REST API | DeepSeek V4.1 Flash | none by default |
| 026 | Reporting + Search | Qwen 3.8 Flash | none by default |
| 027 | Security Hardening | Claude Code | none by default |
| 028 | Performance | Qwen 3.8 Flash | none by default |
| 029 | Deployment | DeepSeek V4.1 Flash | none by default |
| 030 | Final Implementation Audit (Audit-Only Stage) | Codex — **Primary Audit Owner**, not Primary Implementation Owner (see GOV-MM-001) | **Human Final System Approval** (mandatory; Codex PASS does not satisfy it); a Command Code model READ ONLY only if explicitly authorized |

For IMP-004 onward, Codex remains the Independent Formal Reviewer unless an explicitly approved
exception is recorded. IMP-000 through IMP-003 were completed under the prior single-owner
(Claude Code) governance and this table does not retroactively alter their FINAL/LOCKED status or
evidence. IMP-009/010/011/014/015/016/027 remain Claude Code, each still subject to its own
GOV-MM-002 Per-IMP Model Binding (no generation assigned) — Amendment V2 does not touch these
seven rows.

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

**No Claude generation/model is assigned to any of these seven stages by this amendment.** This
document does not name, guess, or pre-select a model for IMP-009, IMP-010, IMP-011, IMP-014,
IMP-015, IMP-016, or IMP-027 — each independently goes through the full Binding State Model
(`UNBOUND -> MODEL RESOLVED -> AWAITING HUMAN MODEL APPROVAL -> BOUND -> IMPLEMENTATION MAY
PROCEED`, see GOV-MM-002 above) only when that specific IMP reaches its own implementation gate.

## Model Version Pinning

Exact model identifiers are governance-controlled. Current Command Code baseline (Amendment V2,
`PROPOSED`, verified against `cmdc --list-models`):

```
DeepSeek: deepseek/deepseek-v4.1-flash
Qwen:     qwen/qwen3.8-flash
Kimi:     moonshotai/kimi-k2.7-code
```

Retired baseline (V1, superseded — see "Retired Models" above, kept for traceability only):

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
Primary Implementation Owner: DEEPSEEK
Primary Model: DeepSeek V4.1 Flash
Exact Model ID: deepseek/deepseek-v4.1-flash
Execution Environment: Command Code
Specialist Reviewer: none by default (Amendment V2)
Independent Formal Reviewer: Codex
Human Approval Authority: Human
Concurrent Editing: PROHIBITED
```

### Claude Model Binding (Mission-Critical Stages Only, GOV-MM-002)

In addition to the standard fields above, each of the seven Mission-Critical Claude Stages must
record, before implementation begins, a Claude Per-IMP Model Binding record — at minimum:

```
IMP ID:
Execution Environment:               Claude Code
Actual Claude Model Name:
Exact Model Identifier:              (exact product-visible model identifier at execution time)
Resolution/Verification Date:
Human Model Binding Approval:        (approver + date)
Binding Status:                      UNBOUND / MODEL RESOLVED / AWAITING HUMAN MODEL APPROVAL /
                                      BOUND
Execution/Version Metadata:          (any additional detail required by repository governance)
Model Change Requests against this binding (if any): list, each with date + Human approval
```

Only a record whose Binding Status is `BOUND` authorizes implementation to proceed for that IMP.

Example:

```
IMP-009 Payment Hub
Primary Implementation Owner: Claude Code
Execution Environment: Claude Code
Actual Claude Model Name: <exact model name available at binding time>
Exact Model Identifier: <exact product-visible model identifier available at binding time>
Resolution/Verification Date: <date>
Human Model Binding Approval: Human, <date>
Binding Status: BOUND
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

Sequence required after this amendment was created — now complete:

```
1. Amendment created (this document).                                   DONE
2. Independent Codex review (initial: FAIL; targeted re-audit: PASS).    DONE
3. Human approval of the amendment itself (Final Human Lock Approval).   DONE
4. Amendment committed and declared FINAL / LOCKED.                      DONE
5. IMP-004 readiness/specification may now proceed.                      AUTHORIZED
```

This amendment's `FINAL / LOCKED` status and the Human Final Approval recorded in "Approval"
above together authorize **IMP-004 readiness/specification work** to begin. They do **not**
authorize **IMP-004 implementation** — that remains a separate future gate requiring its own
explicit Human authorization once IMP-004's specification, Definition of Ready, and (per
"Implementation Ownership" in `docs/implementation/IMPLEMENTATION-SPEC-TEMPLATE.md`) its Primary
Implementation Owner/model/specialist/reviewer fields are recorded and approved.

IMP-004 intended ownership once its own implementation gate is reached (updated by Amendment V2 —
see "Amendment V2 — Command Code Model Generation Replacement" -> "IMP-004 Transition" above for
the full, currently-in-effect HOLD condition; this block records the resulting assignment, not a
separate authorization):

```
Primary:                    DeepSeek V4.1 Flash
Exact ID:                   deepseek/deepseek-v4.1-flash
Specialist:                 none by default (Amendment V2)
Independent Formal Reviewer: Codex
Human:                       Final Stage Gate authority
```

IMP-004's specification has since passed independent Codex specification audit (0 BLOCKER/MAJOR/
MINOR/EDITORIAL/HUMAN DECISION REQUIRED) and Human has separately stated IMP-004 implementation is
authorized in principle — but execution remains on **HOLD** until Amendment V2 itself reaches
`FINAL / LOCKED` (see "IMP-004 Transition" above); that Human statement authorized implementing
IMP-004, not implementing it under an owner assignment this document had not yet finalized.

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
  Scope:            Approved incorporating GOV-MM-001 and GOV-MM-002 into the amendment. At the
                     time of this statement it did not by itself move the document's Status out
                     of PROPOSED, and did not authorize IMP-004 (or IMP-030) work — the amendment
                     was subsequently remediated, Codex-re-audited to PASS, and locked by the
                     separate Final Human Lock Approval recorded immediately below, which is what
                     moved the overall Status to FINAL / LOCKED.

Independent Codex Review (Targeted Re-Audit, post-remediation commit 1f9238d):
  Status:            PASS — GOVERNANCE AMENDMENT ELIGIBLE FOR FINAL HUMAN APPROVAL
  Reviewer:          Codex
  Findings:          GOV-MM-001: RESOLVED; GOV-MM-002: RESOLVED;
                     BLOCKER: 0; MAJOR: 0; MINOR: 0; EDITORIAL: 0; HUMAN DECISION REQUIRED: 0
  Repository Mutation by Codex: NO
  Push by Codex:     NO
  Evidence/Reference: docs/audits/MULTI-MODEL-GOVERNANCE-FINALIZATION.md

Final Human Lock Approval:
  Status:            APPROVED
  Human Approver:    Human
  Statement:         "Saya setuju.

                     Multi-Model AI Implementation Ownership Governance Amendment
                     Final Human Approval diberikan.

                     GOV-MM-001: RESOLVED / APPROVED.
                     GOV-MM-002: RESOLVED / APPROVED.

                     Governance Amendment dapat ditetapkan FINAL / LOCKED.

                     Ownership model IMP-004-IMP-030, model pinning, Claude Per-IMP Model
                     Binding, specialist READ ONLY, Codex independence, IMP-030 Audit-Only
                     Stage, dan Human approval authority ditetapkan sebagai governance
                     baseline.

                     IMP-004 READINESS dapat dimulai.
                     IMP-004 IMPLEMENTATION belum diotorisasi."
  Scope:             Locks this amendment (Amendment Clauses GOV-MM-001/GOV-MM-002 included) as
                     the FINAL / LOCKED Level 6 governance baseline. Separately and explicitly
                     authorizes IMP-004 readiness/specification work to begin. Does NOT authorize
                     IMP-004 implementation, IMP-030 work, application source changes,
                     architecture/database/security/financial/business-rule changes, or git push
                     — each remains gated on its own future, separate Human authorization.
  Approval Evidence: docs/audits/MULTI-MODEL-GOVERNANCE-FINALIZATION.md

Amendment V2 Creation Authorization:
  Authority:        Human
  Statement:        Human instructed replacement of the prospective Command Code implementation-
                     agent generation (Kimi K3 / Qwen 3.8 Max 0902 / DeepSeek V4 Pro -> DeepSeek
                     V4.1 Flash / Qwen 3.8 Flash / Kimi K2.7 Code) and the resulting ownership
                     matrix, supplied as a full structured governance-patch task ("MULTI-MODEL AI
                     IMPLEMENTATION OWNERSHIP GOVERNANCE — AMENDMENT V2") in the session that
                     produced this patch — not a single short verbatim quoted sentence like the
                     GOV-MM-001/002 and V1 Final Lock approvals above, so none is fabricated here;
                     this describes the authorization's scope and substance accurately instead.
  Scope:             Authorizes drafting Amendment V2 (this section, "Approved Model Baseline,"
                     "Fixed IMP Ownership Matrix," "Model Version Pinning," "IMP-004 Boundary,"
                     and "Execution Evidence" updates above) as a `PROPOSED`, not self-declared
                     `FINAL / LOCKED`, governance patch; verifying exact model IDs against
                     `cmdc --list-models`; and updating governance documentation to reflect the
                     new baseline. Does NOT authorize: IMP-004 (or any) implementation execution
                     (still separately gated — see "IMP-004 Transition" above); application
                     source/migration/test/dependency changes; architecture/business-rule/
                     security/financial baseline changes; modifying Q26/Q27/Q28 or any other
                     Human Decision; rewriting IMP-000/001/002/003 historical evidence; or git
                     push. Declaring Amendment V2 itself `FINAL / LOCKED` requires the separate
                     Independent Codex Audit and Final Human Lock Approval (V2) records below,
                     both still PENDING, mirroring exactly how V1 was locked.

Independent Codex Audit (Amendment V2):
  Status:            PENDING
  Reviewer:
  Findings:
  Evidence/Reference:

Final Human Lock Approval (Amendment V2):
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
