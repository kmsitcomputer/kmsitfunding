# Multi-Model AI Implementation Ownership Governance Amendment

## Status

```
Base Amendment (V1):   FINAL / LOCKED
Amendment V2:          PROPOSED — AWAITING INDEPENDENT CODEX AUDIT
Amendment V3 (full
  prospective program): FINAL / LOCKED — Human Final Lock Approval received (see "Approval"
                          below). Codex Final Re-Audit (Pass 3) result: PASS — V3-GOV-B01,
                          V3-GOV-M01, V3-GOV-M02, V3-GOV-m01, and V3-REAUDIT-M01 all CLOSED;
                          BLOCKER 0 / MAJOR 0 / MINOR 0 / EDITORIAL 0 / GATE-IMPACT 0. Audited
                          baseline: commit ba44336. Effective IMP-008 forward, subject to the
                          documented Mission-Critical Claude Stages and IMP-030 exceptions (see
                          "Amendment V3" -> "Precedence Over the Legacy Matrix" below). This lock
                          does not itself authorize IMP-008 readiness, specification, or
                          implementation work — that remains a separate, future Human
                          authorization at IMP-008's own gate (see "Authorization Scope" below).
GOV-MM-005 (V3 Pipeline
  Activation, Human
  Decision):             APPROVED / ACTIVE — effective IMP-008 forward; superseded in effect by
                          the full Amendment V3 lock above, which now covers the same pipeline
                          under Final Lock rather than the narrower direct-Human-Decision
                          activation this record originally used.
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

**Amendment V3** (see "Amendment V3 — Pipeline Write-Ownership Model" below) is a *separate,
later* patch, drafted under explicit Human authorization received at the start of a governance
session that named IMP-008 as the first prospectively-affected stage. It restructures how a
single IMP stage's implementation work is divided across models (recon -> implement -> check ->
audit) rather than changing which model owns which *domain* the way V2 does. **Amendment V3 also
remains `PROPOSED`** — it does not touch, resolve, or accelerate Amendment V2's own still-pending
lock, does not change the "Fixed IMP Ownership Matrix" table itself (that table is not amended
until V3 itself locks — see "Amendment V3" below for exactly what does and does not change), and
has an additional, V1/V2 did not have: unresolved **Exact Model ID Verification** for its own
proposed roster (see "Amendment V3" -> "Exact Model ID Verification Gap"). V1 and V2's own
locked/proposed content is otherwise unaffected by V3.

`FINAL / LOCKED` (V1), `PROPOSED` (V2), and `PROPOSED` (V3) together govern this document's own
operational rules as the authoritative Level 6 baseline. None of the three, by itself, authorizes
IMP-004 (or later) implementation work, IMP-008 implementation work, or IMP-030 work — those each
require their own separate Human authorization at their own gate. See
[IMPLEMENTATION-GOVERNANCE.md](IMPLEMENTATION-GOVERNANCE.md) and
[CHANGE-CONTROL.md](CHANGE-CONTROL.md) for how this amendment relates to the existing governance
hierarchy, and "IMP-004 Boundary" below for the explicit readiness-vs-implementation distinction,
now including the Amendment-V2-lock + valid-binding hold described there. Amendment V3 does not
add or change an "IMP-008 Boundary" section — no IMP-008 readiness or implementation work is
authorized by this patch (see "Amendment V3" -> "Authorization Scope" below).

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
ownership — see "Retired Models (V1 Generation)" below. No IMP has completed or finalized governed
implementation evidence under either generation of this multi-model governance — IMP-004 is the
first candidate, its implementation execution has never been authorized to run to completion under
this governance (see `docs/implementation/IMP-004-audit-governance-foundation.md` "Status" for its
exact current authorization/hold state), and this retirement rewrites no such finalized historical
execution record. This is distinct from whether any UNCOMMITTED, pre-finalization implementation
artifact exists in the working tree — one does (see "Pre-Existing Implementation Artifacts /
Ownership-Provenance Review" in the IMP-004 specification linked above), and it remains outside
this or any Amendment V2 acceptance pending its own ownership-provenance review; this document does
not claim it does not exist.

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
generation existed in this document at all, and are unaffected. No IMP has completed or finalized
governed implementation evidence under the V1 Command Code generation either — IMP-004 remains the
first candidate, and its Primary Implementation Owner simply changes from Kimi K3 (V1) to DeepSeek
V4.1 Flash (V2) as a matter of prospective assignment, not a historical correction. This is
distinct from the pre-existing, uncommitted, pre-Amendment-V2 IMP-004 implementation artifacts
that already exist in the working tree, whose provenance is not fully known and which remain
outside acceptance under either generation until the ownership-provenance review required by
`docs/implementation/IMP-004-audit-governance-foundation.md` is completed.
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

Hold released only when ALL of the following are true:
  1. Amendment V2 itself is FINAL / LOCKED (independent Codex audit PASS + separate Human Final
     Approval for V2, recorded in "Approval" below — not yet done);
  2. The DeepSeek V4.1 Flash Primary Implementation Owner assignment for IMP-004 is valid under
     that locked baseline (i.e. Amendment V2's Model Version Pinning entry for DeepSeek is in
     effect — no separate per-IMP BOUND state machine is introduced for Command Code models here;
     that stronger mechanism remains GOV-MM-002's Claude-specific one);
  3. This document's IMP-004 specification
     (`docs/implementation/IMP-004-audit-governance-foundation.md`) has its owner metadata
     synchronized to this baseline (done — see that document's "Implementation Ownership");
  4. The ownership-provenance review of the pre-existing, uncommitted, pre-Amendment-V2 IMP-004
     implementation artifacts already present in the working tree is completed and recorded (see
     that same specification's "Pre-Existing Implementation Artifacts / Ownership-Provenance
     Review" and `docs/audits/IMP-004-OWNERSHIP-HANDOFF.md` — not yet done); and
  5. DeepSeek V4.1 Flash explicitly accepts ownership of the continuing implementation following
     that review.

Until all conditions hold, IMP-004 implementation execution does not proceed, regardless of the
Human's prior "IMP-004 Implementation Authorized" statement — that statement authorized
IMP-004's implementation in principle; it did not, and could not, bind it to an owner this
amendment had not yet finalized, and it did not resolve the separate provenance question raised by
artifacts that already exist outside this governance's own acceptance process.
```

IMP-004's Primary Implementation Owner, once the hold above is released: `DeepSeek V4.1 Flash`
(`deepseek/deepseek-v4.1-flash`) — see "IMP-004 Boundary" below for the updated intended-ownership
record. **This is superseded, for IMP-004 only, by GOV-MM-004 immediately below.**

### GOV-MM-004 — IMP-004 Temporary Completion Ownership Exception

**Status: `PROPOSED` (part of Amendment V2's still-`PROPOSED` state) — a Human-authorized one-time
exception, not a change to the Amendment V2 prospective program itself.**

> **Human, verbatim:**
>
> "Saya setuju. Claude Code ditetapkan sebagai Temporary Completion Owner untuk IMP-004 saja.
> Setelah IMP-004 FINAL/LOCKED, ownership kembali mengikuti Multi-Model Governance V2 mulai
> IMP-005."

```
Authority:                    Explicit Human Decision
Previous prospective owner:   Kimi K3 (Amendment V1, superseded)
Intermediate V2 proposed owner: DeepSeek V4.1 Flash (Amendment V2, still the standing prospective
                               assignment for every OTHER purpose this document defines)
Temporary Completion Owner:   Claude Code
Scope:                        IMP-004 ONLY — no other IMP's ownership is affected by this clause
Purpose:                      controlled review of the pre-existing, uncommitted IMP-004
                               implementation work already found in the working tree (per
                               docs/audits/IMP-004-OWNERSHIP-HANDOFF.md), and completion of
                               IMP-004 under Claude's existing single-owner PATCH-DO-NOT-REWRITE
                               discipline
Expiration:                   AUTOMATIC, the moment IMP-004 reaches FINAL / LOCKED — no separate
                               Human action is required to end this exception
IMP-005 onward:                Amendment V2's prospective matrix applies exactly as already
                               recorded in "Fixed IMP Ownership Matrix" below — UNCHANGED by this
                               clause
Independent Formal Reviewer:  Codex (unchanged — Claude does not become IMP-004's own reviewer)
Human Stage Gate:              Required (unchanged)
```

This clause does not erase the DeepSeek V4.1 Flash transition history recorded above and in
`docs/audits/IMP-004-OWNERSHIP-HANDOFF.md` — it records a THIRD link in that same chain (Kimi K3 ->
DeepSeek V4.1 Flash -> Claude Code, IMP-004 only), not a replacement of the prior record. Once
IMP-004 is FINAL/LOCKED, this exception expires automatically and does not carry forward to any
other IMP — DeepSeek V4.1 Flash remains IMP-004's own historical prospective-transition record for
traceability, and remains the Amendment V2 assignment for every IMP this clause does not name.

#### GOV-MM-004 Model Binding (Per GOV-MM-002's Same Discipline)

Claude Code remains subject to Per-IMP Model Binding (GOV-MM-002) even under this temporary
exception — being named Temporary Completion Owner is not, by itself, approval of any specific
Claude model generation for IMP-004. The two controls are separate:

```
UNBOUND
   |
   v
MODEL RESOLVED               <- this task's evidence reaches this state, no further, below
   |
   v
AWAITING HUMAN MODEL APPROVAL
   |
   v
BOUND
   |
   v
IMPLEMENTATION MAY PROCEED
```

Resolved (not guessed, not inferred from a generic `claude` invocation), from this session's own
runtime/configuration evidence, and now Human-approved:

```
Provider:                 Anthropic
Model Display Name:       Claude Sonnet 5
Exact Model Identifier:   claude-sonnet-5
Claude Code Version:      2.1.269
Execution Environment:    Claude Code (VS Code extension; entrypoint claude-vscode)
Resolution/Verification Date: 2026-09-13
Human Model Binding Approval: "Claude Sonnet 5 (claude-sonnet-5) is BOUND as the Claude Code
                           model for Temporary Completion Owner IMP-004." — Human, 2026-09-13
Binding Status:            BOUND
```

Human's approval of Claude Code as Temporary Completion Owner (GOV-MM-004's owner designation) and
this separate, explicit approval of `claude-sonnet-5` as the exact bound model identifier for
IMP-004 (this section) are two distinct controls, per GOV-MM-002's own "owner approval and exact
model binding approval are separate controls" principle — both are now satisfied for IMP-004.
Silent substitution remains prohibited: if the execution environment departs from `claude-sonnet-5`
during IMP-004 work, that is detected and reported per "Silent Substitution" above, not silently
continued under a different model.

## Amendment V3 — Pipeline Write-Ownership Model

**Status: `FINAL / LOCKED`** — Human Final Lock Approval received following a passing Codex Final
Re-Audit (Pass 3: BLOCKER 0 / MAJOR 0 / MINOR 0 / EDITORIAL 0 / GATE-IMPACT 0, all five findings
across Pass 1 and Pass 2 CLOSED); see "Approval" below for the verbatim record. Materialized under
explicit Human authorization, received at the start of a governance-only session, to establish a
token-efficient multi-agent pipeline "starting from IMP-008"; first activated for its
role/precedence structure by a direct Human Decision (GOV-MM-005) ahead of the full amendment
lock, then remediated across two Codex findings passes (see "Codex Findings Remediation (Pass 1)"
and "(Pass 2)" below), and now locked in full by the same Codex-audit-then-Human-Final-Lock
sequence V1 used. Locking the amendment does not, by itself, authorize IMP-008 readiness,
specification, or implementation work — that remains a separate, future Human authorization at
IMP-008's own gate (see "Authorization Scope" below, unchanged by this lock).

Unlike V2 (which reassigns which model owns which *domain*), V3 proposes restructuring *how a
single IMP's implementation is divided across models* into a fixed pipeline, with a roster now
VERIFIED against current `cmdc --list-models` evidence (see "Exact Model ID Verification Gap —
RESOLVED (V3-GOV-B01)" and "Model Binding Contract (V3 Roster)" below):

```
CLAUDE CODE            Lead Architect — specification, contract, acceptance criteria,
                        security/financial invariants, architecture-level remediation. Not the
                        default implementation write owner under this amendment (Mission-Critical
                        Claude Stages are the sole, already-locked exception — see HD-V3-R2-03
                        under "Precedence Over the Legacy Matrix" above).
QWEN 3.7 FLASH          Recon / cheap worker (qwen/qwen3.7-flash, VERIFIED) — repository
                        reconnaissance, file/dependency mapping, existing-contract discovery, test
                        inventory. Read-only against implementation source.
MUSE SPARK 1.3
  CONTRIBUTOR           Main Developer / default implementation write owner
                        (meta/muse-spark-1.3-contributor, VERIFIED, provider Meta; role ACTIVE per
                        GOV-MM-005) — implementation from approved spec + Qwen's file map, targeted
                        remediation, implementation tests.
DEEPSEEK V4.1 FLASH     Independent Technical Checker (deepseek/deepseek-v4.1-flash, VERIFIED,
                        display name corrected from shorthand "DeepSeek V4.1" — see "Exact Model ID
                        Verification Gap") — diff-first review: specification compliance, logic,
                        security, transaction/concurrency/idempotency, database-constraint review.
                        Read-only by default.
CODEX                   Independent Final Semantic / Closure Auditor — final semantic contract
                        audit, cross-file invariant verification, Stage Gate readiness. Read-only;
                        does not remediate its own findings (unchanged from V1/V2's Codex role).
HUMAN                   Final Authority — unchanged from every prior amendment.
```

### GOV-MM-005 — Human-Approved V3 Pipeline Activation (IMP-008 Forward)

> **Human, verbatim:**
>
> "Saya setuju.
>
> MULTI-AGENT WORKFLOW V3 APPROVED.
>
> The "Amendment V3 — Pipeline Write-Ownership Model" is approved for use prospectively beginning
> with IMP-008.
>
> Human approval authorizes activation of V3 governance.
>
> IMP-000 through IMP-007 remain historical FINAL / LOCKED.
>
> This approval does NOT authorize IMP-008 implementation."

This is a direct Human Decision activating V3's **pipeline structure and role assignment**,
following the same pattern GOV-MM-004 already established in this document: a Human Decision can
activate a specific, scoped piece of a still-`PROPOSED` amendment without waiting for that
amendment's own full Codex-audit-and-lock sequence (see "Amendment V2" -> "GOV-MM-004" above for
the precedent). It does **not** retroactively declare the full Amendment V3 program
`FINAL / LOCKED` — no Independent Codex Audit of V3 has occurred, and none is fabricated here (see
"Approval" below, where that field remains `PENDING`, exactly as it was left before this
activation).

**What GOV-MM-005 settles, effective IMP-008 forward:**

- V3's pipeline (Lead Architect -> Recon -> Main Developer -> Automated Tests -> Independent
  Technical Checker -> targeted remediation -> Full Regression -> Final Semantic/Closure Audit ->
  Human Stage Gate) is the active execution workflow for IMP-008 and, per "Precedence Over the
  Legacy Matrix" below, every subsequent stage it applies to.
- Role assignment: Claude Code = Lead Architect / Spec Contract; Qwen 3.7 Flash = Recon; Muse
  Spark 1.3 Contributor = Main Developer / default implementation write owner; DeepSeek V4.1 =
  Independent Technical Checker; Codex = Independent Final Semantic/Closure Auditor; Human = Final
  Authority.
- Resolves Open Item 2 (below, retained for traceability): V3's Main Developer role **replaces**
  the legacy matrix's domain-owner as IMP-008's Primary Implementation Owner going forward — it
  does not run underneath it. IMP-008's Primary Implementation Owner is therefore Muse Spark 1.3
  Contributor under V3, not Kimi K2.7 Code (Amendment V2's prospective, still-`PROPOSED`
  assignment for that row), from GOV-MM-005's effective date onward.

**What GOV-MM-005 does NOT settle:**

- Open Item 1 (Exact Model ID Verification for Qwen 3.7 Flash, Muse Spark 1.3 Contributor,
  DeepSeek V4.1) is **not addressed by this Human statement** and remains unresolved — see "Exact
  Model ID Verification Gap" below, now restated as a precondition to *using* the activated
  pipeline rather than to activating it. Per GOV-MM-002's own "owner/role approval and exact model
  binding approval are separate controls" principle (already established in this document for
  Claude's mission-critical stages), Human approving the pipeline's *structure and role
  assignment* is not the same control as verifying the *exact identity* of the models filling
  Qwen/Muse/DeepSeek's roles. Until that separate verification happens, those three role slots are
  defined but not yet actionable for governed work.
- IMP-008 readiness or implementation work — the Human statement says this explicitly ("This
  approval does NOT authorize IMP-008 implementation").
- Independent Codex Audit and Final Human Lock Approval of the full Amendment V3 program — both
  remain `PENDING` in "Approval" below.

### Relationship to the Existing Fixed IMP Ownership Matrix

**This patch does not delete, rewrite, or retroactively alter the "Fixed IMP Ownership Matrix"
table above** — it remains the historical/traceability record of the V1/V2 domain-based
assignments, and IMP-000 through IMP-007's entries in it are unaffected and `FINAL / LOCKED` where
already completed. What changes, per GOV-MM-005, is which assignment is *operative* for IMP-008
forward — see "Precedence Over the Legacy Matrix" immediately below.

#### Precedence Over the Legacy Matrix

Effective GOV-MM-005 (this activation), **every Fixed IMP Ownership Matrix row from IMP-008
forward is governed by the V3 pipeline** (Claude Spec -> Human Spec Approval -> Qwen Recon ->
Implementation Write Owner -> Automated Tests -> DeepSeek Independent Technical Review ->
remediation -> Full Regression -> Codex Closure Audit -> Human Stage Gate) — **not excluded from
it**. What the two carve-outs below change is narrower and role-specific: *which model holds the
Implementation Write Owner role*, not whether V3's gates apply (see HD-V3-R2-03, remediation Pass
2, correcting a prior draft of this section that incorrectly described Mission-Critical Claude
Stages and IMP-030 as outside V3's pipeline rather than as a role-specific exception within it):

```
Mission-Critical Claude Stages (IMP-009, 010, 011, 014, 015, 016, 027):
  GOVERNED BY THE FULL V3 PIPELINE — Human Spec Approval, Qwen Recon, Automated Tests, DeepSeek
  Independent Technical Review, remediation routing, Full Regression, Codex Closure Audit, and
  Human Stage Gate all apply exactly as they do to every other IMP-008-forward stage. The ONLY
  change from the V3 default: the Implementation Write Owner role is Claude Code (not Muse Spark
  1.3 Contributor), still subject to GOV-MM-002 Per-IMP Model Binding for that implementation
  role specifically, exactly as "Mission-Critical Claude Stages" below already locks. Remediation
  findings route to Claude as the implementation owner (architecture/security/financial findings)
  or per the standard Finding Routing table, as appropriate — this does not remove DeepSeek's
  independent technical review or Codex's closure audit.

IMP-030 (Audit-Only Stage):
  The sole FULL-PIPELINE EXCEPTION, because it implements nothing — see "GOV-MM-001 — IMP-030
  Audit-Only Stage" above. Codex remains Primary Audit Owner; IMP-030 has no Implementation Write
  Owner (Muse, Claude, or otherwise) for the V3 pipeline to assign, so the pipeline's
  implementation-side steps (Recon through remediation/regression) do not apply — not because V3
  is switched off for IMP-030, but because there is no implementation stage for them to attach to.

Every other row IMP-008 forward (008, 012, 013, 017, 018, 019, 020, 021, 022, 023, 024, 025, 026,
028, 029):
  Default V3: the table's listed model (Kimi K2.7 Code / Qwen 3.8 Flash / DeepSeek V4.1 Flash, per
  Amendment V2's still-PROPOSED prospective assignment) is superseded, for the Implementation
  Write Owner question specifically, by V3's Main Developer, Muse Spark 1.3 Contributor
  (meta/muse-spark-1.3-contributor, VERIFIED — see "Model Binding Contract (V3 Roster)"). The
  table's original model names are retained unmodified in the table itself for
  historical/traceability continuity — they are not the active assignment going forward, per this
  precedence note.
```

There is therefore no contradictory *active* ownership rule for IMP-008 forward: the table
records history/traceability; this precedence note, not the table, governs which assignment is
operative. (Open Item 2 from the original draft of this section — replace-vs-run-underneath — is
resolved by GOV-MM-005 above: replace. It is retained in "Open Items" below only for traceability
of how the question was raised and settled, not as a still-open question.)

### Exact Model ID Verification Gap — RESOLVED (V3-GOV-B01)

V1 and V2 each recorded every model's exact identifier verified directly against `cmdc
--list-models` output before being treated as a real, invocable model (see "Exact Model ID
Verification" under Amendment V2). A prior draft of this section recorded Qwen 3.7 Flash, Muse
Spark 1.3 Contributor, and DeepSeek V4.1 as `NOT VERIFIED` because that earlier session had no
`cmdc --list-models` access. This session does, and ran it directly — output captured below,
verbatim identifiers only, no invention or inference from naming convention:

```
$ cmdc --list-models   (72 models listed; relevant entries only)

qwen/qwen3.7-flash                     fast low-cost agentic coding & reasoning
deepseek/deepseek-v4.1-flash           V4.1 hybrid-attention reasoning with vision
meta/muse-spark-1.3-contributor        Muse Spark 1.3 at up to 95% off
```

**Qwen 3.7 Flash** — exact, unambiguous match: `qwen/qwen3.7-flash`. One candidate only; nothing
inferred.

**Muse Spark 1.3 Contributor** — exact, unambiguous match: `meta/muse-spark-1.3-contributor`.
Provider is **Meta** (not previously established in this document — Muse Spark is not present in
any V1/V2 baseline). One candidate only; nothing inferred.

**DeepSeek V4.1** — the listing has no bare "DeepSeek V4.1" (non-Flash) entry; the sole model
whose version is V4.1 is `deepseek/deepseek-v4.1-flash`, identical to V2's already-established
"DeepSeek V4.1 Flash." This is not "reusing an old identifier merely because it was historically
used" — it is the current, freshly-run listing independently confirming the *same* identifier is
still the only V4.1 DeepSeek model available, with zero competing candidates. The display name is
corrected here from the shorthand "DeepSeek V4.1" to the full, listing-accurate "DeepSeek V4.1
Flash" for this reason.

**Verification status: all three VERIFIED.** None fabricated, none inferred from a naming
pattern, none assumed from history without fresh current confirmation.

### Model Binding Contract (V3 Roster)

```
ROLE:                     Recon
DISPLAY NAME:             Qwen 3.7 Flash
EXACT MODEL IDENTIFIER:   qwen/qwen3.7-flash
PROVIDER:                 Alibaba (Qwen)
EXECUTION ENVIRONMENT:    Command Code (cmdc)
VERIFICATION METHOD:      cmdc --list-models, run directly this session
STATUS:                   VERIFIED

ROLE:                     Main Developer / default implementation write owner
DISPLAY NAME:             Muse Spark 1.3 Contributor
EXACT MODEL IDENTIFIER:   meta/muse-spark-1.3-contributor
PROVIDER:                 Meta
EXECUTION ENVIRONMENT:    Command Code (cmdc)
VERIFICATION METHOD:      cmdc --list-models, run directly this session
STATUS:                   VERIFIED

ROLE:                     Independent Technical Checker
DISPLAY NAME:             DeepSeek V4.1 Flash (corrected from V3's original shorthand
                          "DeepSeek V4.1" — see above; identical identifier to V2's existing
                          baseline entry)
EXACT MODEL IDENTIFIER:   deepseek/deepseek-v4.1-flash
PROVIDER:                 DeepSeek
EXECUTION ENVIRONMENT:    Command Code (cmdc)
VERIFICATION METHOD:      cmdc --list-models, run directly this session
STATUS:                   VERIFIED

ROLE:                     Lead Architect
DISPLAY NAME:             Claude Code (exact bound model per-IMP, GOV-MM-002 unaffected)
STATUS:                   Already established elsewhere in this document — unaffected by V3

ROLE:                     Independent Final Semantic / Closure Auditor
DISPLAY NAME:             Codex
STATUS:                   Already established elsewhere in this document — unaffected by V3
```

**Fail-closed rule (unchanged principle, restated for V3):** if, at the point any of these three
roles is actually invoked for governed IMP-008+ work, the execution environment cannot confirm the
exact identifier above is the model actually running — the discrepancy is detected before
governed work proceeds, not glossed over. Invocation **fails closed**: implementation/recon/review
under that role does not proceed on an unconfirmed identity. No silent fallback to a different
model, no silent substitution. Any replacement of a verified identifier requires the same
governance mechanism already established in "Model Change Control" above (Model Change Request ->
compatibility assessment -> impact assessment -> Human approval -> governance amendment), applied
to the V3 roster exactly as it already applies to the V1/V2 roster. This verification record does
not expire silently — if a future `cmdc --list-models` no longer lists one of these three exact
identifiers, that is itself a Model Change Request event, not a silent continuation.

**V3-GOV-B01: CLOSED.** All three previously-unresolved identities are now VERIFIED against
current authoritative local evidence, using the same method (`cmdc --list-models`) this document
already treats as authoritative for V1 and V2. Claude Code and Codex were already
verified/established elsewhere in this document and are unaffected.

### Human Spec Approval Gate (V3-GOV-M01; ordering corrected in Pass 2 — V3-REAUDIT-M01)

A prior draft of this document named "Human Spec Approval" as a step in V3's canonical pipeline
(Claude Spec -> Human Spec Approval -> Qwen Recon -> Muse Implementation -> ...) without making it
a discrete, durable, auditable control — indistinguishable from an informal or assumed approval.
This subsection makes it one, following the same discipline this document already applies to
Human Stage Gate and Human Final Lock Approval elsewhere.

**Rule:** for every IMP-008-forward stage run under the V3 pipeline, implementation writing (the
Implementation Write Owner role — Muse Spark 1.3 Contributor by default, Claude Code for
Mission-Critical Claude Stages) **must not begin** until Human Spec Approval is recorded for that
IMP's specification, at the exact revision approved. This is a hard gate, not a formality.

**Ordering (HD-V3-R2-01):** governed Qwen Recon **must not occur before or concurrently with**
Human Spec Approval — a prior draft of this section stated the opposite ("Qwen Recon may run
before or in parallel with seeking approval"); that statement is corrected here, not merely
softened, because it conflicted with the canonical sequence this same document states elsewhere
(Spec -> Human Spec Approval -> Qwen Recon -> Implementation). Recon is derived from the *approved*
specification and maps only the repository context required by that approved contract — running it
against an unapproved or still-changing spec risks mapping the wrong contract entirely. This does
**not** prohibit Claude, while preparing the specification, from inspecting the minimum repository
context necessary to write it — that is Claude's own spec-preparation activity, not governed Qwen
Recon, and is unaffected by this rule.

**Durable evidence contract** — every IMP-008-forward specification must record, alongside
"Implementation Ownership" (see `IMPLEMENTATION-SPEC-TEMPLATE.md`, patched below):

```
Human Spec Approval:      APPROVED / NOT APPROVED
Approval Statement:       <exact Human approval statement, or a durable reference to it — never
                          fabricated, never inferred from silence or from an unrelated approval>
Approval Scope:           <IMP identifier> + <specification revision/commit this approval covers>
Approval Evidence:        <repository evidence/reference — e.g. the commit that records this
                          section, or a linked docs/audits/ record>
```

This reuses the exact verbatim-quote-plus-scope discipline this document already uses for every
other Human Decision (GOV-MM-001/002/004/005, Final Human Lock Approval) — no new evidence format
is invented. A specification revision that changes materially after approval requires a fresh
Human Spec Approval record scoped to the new revision; the old approval does not silently carry
forward to different spec content, the same principle "Model Change After Binding" already applies
to model identity.

**V3-GOV-M01: CLOSED.** Human Spec Approval is now a named, mandatory, evidence-bearing gate
between specification and implementation, with a durable recording contract — not merely a step
label in a diagram.

### V3 Claude Lead Architect Identity Binding (V3-REAUDIT-M01 Remediation)

Codex's Pass 2 re-audit found that Claude's V3 Lead Architect / Specification Owner role had no
auditable identity mechanism of its own for ordinary IMP-008-forward stages: this document's only
existing Claude identity control, GOV-MM-002 Per-IMP Model Binding, is scoped specifically to the
seven Mission-Critical Claude Stages' **implementation** ownership (see "Mission-Critical Claude
Stages" below) — extending it to describe *every* IMP's Lead Architect role, including ordinary
stages that never touch GOV-MM-002 at all, would misstate what GOV-MM-002 actually covers. This
subsection defines a **distinct** identity contract for that different role, so ordinary stages
(IMP-008 included) have their own auditable mechanism without borrowing GOV-MM-002's semantics.

**Rule:** before any IMP-008-forward specification can be submitted for Human Spec Approval, the
specification must record:

```
Role:                       Claude Lead Architect / Specification Owner
Execution Environment:      Claude Code
Display Model:              <actual current model>
Exact Model Identifier:     <exact identifier, when authoritative runtime/tool evidence exposes
                            it>
Verification Method:        <runtime/tool evidence, or Human-confirmed external evidence>
Verification Status:        VERIFIED / EXTERNALLY HUMAN-CONFIRMED
Specification Revision:     <commit/hash/reference>
```

**Fail-closed:** if the required Claude identity cannot be established under this contract, the
specification does not advance to Human Spec Approval. No guessed identifier. No silent model
substitution. A prior IMP's recorded binding is never reused as proof of the current IMP's
binding — each specification records its own, fresh.

**Relationship to GOV-MM-002:** this identity contract is separate from, and does not replace,
GOV-MM-002. GOV-MM-002 continues to govern the Mission-Critical Claude Stages'
**implementation-write-owner** binding specifically (see "Mission-Critical Claude Stages" below
and "HD-V3-R2-03" under "Precedence Over the Legacy Matrix" above) — a Mission-Critical stage
therefore records *both* this identity binding (for its Lead Architect / spec role) *and*
GOV-MM-002's binding (for its Claude-as-Implementation-Write-Owner role), since Claude holds both
roles on those stages. An ordinary stage (e.g. IMP-008) records only this identity binding, since
its Implementation Write Owner is Muse Spark 1.3 Contributor under the "Model Binding Contract
(V3 Roster)" above, not Claude.

**V3-REAUDIT-M01: CLOSED.** Ordinary IMP-008-forward stages now have an auditable mechanism for
their Claude Specification Owner identity, distinct from and not conflated with GOV-MM-002's
narrower Mission-Critical implementation-binding scope. This document does not invent or guess a
current binding value here — the field is defined; each IMP's own execution record fills it in
with evidence available at that time (see `IMPLEMENTATION-SPEC-TEMPLATE.md`, patched below).

### Write Ownership Under V3 (Active, IMP-008 Forward — GOV-MM-005)

Restates — and does not weaken — the existing "ONE FILE / ONE ACTIVE OWNER" and "ONE IMP / ONE
PRIMARY IMPLEMENTATION OWNER" rules (see "Non-Concurrent Ownership Rule" below): Qwen 3.7 Flash,
DeepSeek V4.1 Flash, and Codex are always read-only against implementation source code; the
Implementation Write Owner role is exclusive per IMP — **Muse Spark 1.3 Contributor by default**
(IMP-008 forward, outside the Mission-Critical Claude Stages and IMP-030), or **Claude Code** for
the seven Mission-Critical Claude Stages specifically (see HD-V3-R2-03 under "Precedence Over the
Legacy Matrix" above — Claude is read-only against implementation source on every *other*
IMP-008-forward stage, where it holds only the Lead Architect role). Whichever model holds the
Implementation Write Owner role for a given IMP writes implementation code only after the Human
Spec Approval gate below is satisfied (see "Human Spec Approval Gate"). This rule is structurally
active now; it has no governed IMP to apply to yet, since IMP-008 implementation itself remains
unauthorized (see "Authorization Scope") — the model-identity gate is no longer the blocker for
that (all three V3-roster roles are VERIFIED, see "Model Binding Contract (V3 Roster)"), but
IMP-008 readiness/specification/implementation authorization is a separate, still-outstanding
Human gate.

### Finding Routing (Active, IMP-008 Forward — GOV-MM-005)

```
Technical implementation defect     DeepSeek / Codex  -> Main Developer   (PATCH, not rewrite)
Architecture / contract defect      DeepSeek / Codex  -> Claude Code
New/unresolved business decision    any agent          -> STOP -> HUMAN DECISION (never inferred)
```

### DeepSeek / Codex Review Boundary (V3-GOV-m01 Remediation)

A prior draft left the line between DeepSeek's and Codex's review scope stated but not
executable — both "review the implementation," without a distinguishing question or default
context, inviting either duplicated effort or a gap neither covers. This subsection makes the
boundary concrete without narrowing either reviewer's authority to escalate.

```
DEEPSEEK V4.1 FLASH — IMPLEMENTATION TECHNICAL REVIEW

Primary question:    "Is this implementation technically correct and safe relative to the
                      approved specification?"
Primary focus:        changed implementation; local/cross-file logic required by the diff;
                      specification compliance; authorization implementation; security
                      implementation; transactions; locking/concurrency; idempotency; DB
                      constraints; failure paths; relevant tests.
Default context:      approved spec + RECON + implementation diff + tests.
Output:                actionable technical findings, routed per "Finding Routing" above.

CODEX — FINAL SEMANTIC / CLOSURE AUDIT

Primary question:    "Is the final remediated state eligible for Human Stage Gate?"
Primary focus:         final implementation vs approved contract; unresolved or incorrectly
                      closed findings; cross-domain invariants; architecture boundary
                      preservation; locked decision preservation; evidence completeness;
                      regression evidence; scope leakage; semantic inconsistencies individual
                      technical findings may miss.
Default context:      approved spec + final diff + DeepSeek findings + remediation evidence +
                      full regression evidence.
```

Codex may inspect technical, security, or financial detail when necessary to validate closure —
this is independent final verification, not prohibited duplication of DeepSeek's pass. Both
reviewers start from their stated default context but **either may expand context when concrete
evidence requires it**; the CE-01..CE-10 token-efficiency rules below constrain default behavior,
never investigation of security, financial correctness, authorization, audit, transactions,
concurrency, locked architecture, or cross-domain invariants when evidence calls for it. Neither
reviewer's independence is weakened by this clarification — DeepSeek remains read-only by default,
Codex remains read-only always, and Codex still does not remediate its own findings (unchanged
from "Codex Independence" below).

**V3-GOV-m01: CLOSED.** The boundary is now a stated primary question, focus list, and default
context per reviewer, with an explicit, non-narrowing escalation rule — executable, not merely
descriptive.

### Context-Efficiency Rules (Active, CE-01..CE-10 — GOV-MM-005)

Restates, for this pipeline specifically, principles already implicit in this document's existing
"Specialist Authority," "Remediation Ownership," and "Codex Independence" sections — RECON ONCE
per IMP; MINIMUM SUFFICIENT CONTEXT per handoff; IMPLEMENT BY FILE MAP; REVIEW BY DIFF; REMEDIATE
BY FINDING (do not resend full implementation context for ordinary remediation); PATCH — DO NOT
REWRITE; no duplicate audit scope between DeepSeek (technical) and Codex (semantic/closure);
escalate context only on concrete dependency evidence; one active write owner; Human Decisions are
never inferred. These do not change any existing rule elsewhere in this document — they formalize
the same discipline for the V3 pipeline's own handoffs.

### Handoff Artifacts (Active — GOV-MM-005)

Reuses the existing `docs/implementation/IMP-XXX-*.md` specification convention (already
established by every prior IMP in this repository) and, only for IMPs run under the V3 pipeline,
adds `docs/ai-handoff/IMP-XXX/RECON.md` and `docs/ai-handoff/IMP-XXX/EVIDENCE.md` as a new,
narrowly-scoped convention (this directory does not exist yet). RECON.md is a file/dependency/test
map, not a duplicate specification. EVIDENCE.md is cumulative (Implementation / Tests / DeepSeek
Findings / Remediation / Full Regression / Codex Closure / Human Stage Gate), not a full command
log. This does not replace or duplicate `docs/audits/IMP-XXX-FINALIZATION.md`, which remains the
canonical Stage Gate record for every IMP regardless of which pipeline built it.

### Codex Findings Remediation (Pass 1)

Codex Final Governance Audit result against the pre-remediation state of this Amendment:
`BLOCKER 1 / MAJOR 2 / MINOR 1 / GATE-IMPACT 3` — `NOT ELIGIBLE FOR HUMAN FINAL LOCK`. This
remediation pass addresses all four findings using PATCH — DO NOT REWRITE, minimum sufficient
context, no application source or database touched, IMP-008 not started:

```
V3-GOV-B01  BLOCKER / GATE-IMPACT  Model identity/execution evidence unresolved
            -> CLOSED — see "Exact Model ID Verification Gap — RESOLVED (V3-GOV-B01)" and
               "Model Binding Contract (V3 Roster)" above.

V3-GOV-M01  MAJOR / GATE-IMPACT    Human Spec Approval not a discrete durable auditable gate
            -> CLOSED — see "Human Spec Approval Gate (V3-GOV-M01 Remediation)" above.

V3-GOV-M02  MAJOR / GATE-IMPACT    Operational governance surfaces inconsistent with V3
            -> CLOSED — see companion patches to docs/00-governance/AI-WORKFLOW.md,
               docs/00-governance/IMPLEMENTATION-GOVERNANCE.md, and
               docs/implementation/IMPLEMENTATION-SPEC-TEMPLATE.md (same commit as this patch).

V3-GOV-m01  MINOR                  DeepSeek/Codex review boundary insufficiently executable
            -> CLOSED — see "DeepSeek / Codex Review Boundary (V3-GOV-m01 Remediation)" above.
```

This remediation pass does not itself constitute the Codex re-audit these findings require before
Amendment V3 (full program) can be declared `FINAL / LOCKED` — that re-audit is a separate,
still-outstanding step (see "Approval" below).

### Codex Findings Remediation (Pass 2)

Codex's Pass 2 re-audit of the Pass 1 remediation found: `BLOCKER 0 / MAJOR 3 / MINOR 0 /
GATE-IMPACT 3` — `V3 FINAL LOCK: NOT ELIGIBLE`. This narrow pass addresses only the three named
findings; V3-GOV-B01 and V3-GOV-m01 are confirmed still `CLOSED` and were not reopened or
re-litigated:

```
V3-REAUDIT-M01  MAJOR / GATE-IMPACT   Claude's V3 Lead Architect identity had no auditable
                                     mechanism for ordinary stages, distinct from GOV-MM-002
            -> CLOSED — see "V3 Claude Lead Architect Identity Binding (V3-REAUDIT-M01
               Remediation)" above, plus the matching V3 PIPELINE STAGE field in
               IMPLEMENTATION-SPEC-TEMPLATE.md (same commit as this patch).

V3-GOV-M01 residual  MAJOR / GATE-IMPACT   Prior remediation still stated governed Qwen Recon
                                     "may run before or in parallel with seeking approval,"
                                     conflicting with the canonical Spec -> Approval -> Recon
                                     order stated elsewhere in this document
            -> CLOSED — see "Human Spec Approval Gate" -> "Ordering (HD-V3-R2-01)" above; the
               conflicting sentence is corrected, not merely softened.

V3-GOV-M02 residual  MAJOR / GATE-IMPACT   "Precedence Over the Legacy Matrix" described
                                     Mission-Critical Claude Stages and IMP-030 as outside V3's
                                     pipeline ("V3's Main Developer role does not apply,"
                                     "V3's pipeline does not apply to it"), when the intended
                                     exception is role-specific (write-owner only), not a
                                     pipeline exclusion
            -> CLOSED — see "Precedence Over the Legacy Matrix" above (rewritten per HD-V3-R2-03)
               and the matching corrections to "Write Ownership Under V3" above.

V3-GOV-B01 (previously CLOSED):     PRESERVED — Qwen 3.7 Flash (qwen/qwen3.7-flash), Muse Spark
                                     1.3 Contributor (meta/muse-spark-1.3-contributor), and
                                     DeepSeek V4.1 Flash (deepseek/deepseek-v4.1-flash) remain
                                     VERIFIED, unchanged and untouched by this pass.

V3-GOV-m01 (previously CLOSED):     PRESERVED — the DeepSeek/Codex review boundary (primary
                                     question, focus, default context per reviewer, non-narrowing
                                     escalation) is unchanged by this pass.
```

This pass does not redesign V3, does not start IMP-008, and does not touch application source,
database, or IMP-000..007 evidence. A further Codex re-audit (Pass 3) of this pass is still
required before Amendment V3 (full program) is eligible for Final Human Lock.

### Codex Findings Remediation (Pass 3 — Final Re-Audit) and Human Final Lock

Codex's Pass 3 re-audit of the Pass 2 remediation result: `BLOCKER 0 / MAJOR 0 / MINOR 0 /
EDITORIAL 0 / GATE-IMPACT 0` — `ELIGIBLE FOR HUMAN FINAL LOCK`. Every finding raised across both
prior passes is confirmed CLOSED at this baseline (commit `ba44336`):

```
V3-GOV-B01        CLOSED  (Pass 1 — model identity, VERIFIED)
V3-GOV-M01        CLOSED  (Pass 1 — Human Spec Approval discrete gate)
V3-GOV-M02        CLOSED  (Pass 1 — operational surfaces synchronized)
V3-GOV-m01        CLOSED  (Pass 1 — DeepSeek/Codex boundary)
V3-REAUDIT-M01    CLOSED  (Pass 2 — Claude Lead Architect identity contract)
```

Following this PASS result, Human Final Lock Approval was given:

> **Human, verbatim:**
>
> "Saya setuju. MULTI-AGENT WORKFLOW V3 — HUMAN FINAL LOCK APPROVED."

**Amendment V3 (full prospective program) is `FINAL / LOCKED`** as of this record, per the same
Codex-audit-then-Human-Final-Lock sequence "IMP-004 Boundary" documents V1 having used. This
finalization step is governance materialization only — it does not itself change pipeline
semantics, model bindings, or finding definitions (see "Preserve Final Pipeline" discipline
implicit throughout this document; nothing in this subsection alters any prior CLOSED finding's
substance), and it does not authorize IMP-008 readiness, specification, or implementation work,
which remains gated on its own separate future Human authorization (see "Authorization Scope"
below).

### Authorization Scope

Governance drafting (original patch), GOV-MM-005 activation, the Pass 1/Pass 2 remediation passes,
and the Pass 3 Final Lock materialization above together still do NOT authorize: IMP-008
readiness/specification work, IMP-008 implementation work, application source changes, or database
changes. The model-identity precondition on invoking Qwen 3.7 Flash / Muse Spark 1.3 Contributor /
DeepSeek V4.1 Flash for governed work is resolved (see "Model Binding Contract (V3 Roster)"), and
Amendment V3 itself is now `FINAL / LOCKED` — but locking the *governance framework* is not the
same control as authorizing a *specific future IMP's* work under it; IMP-008 remains gated on its
own separate Human authorization exactly as before, and remains NOT STARTED as of this
finalization. Neither reopens or reinterprets Amendment V2's own still-`PROPOSED` state or its
IMP-004 hold conditions. IMP-000 through IMP-007 remain historical, `FINAL / LOCKED`, and
unmodified. Git push of this finalization commit itself is a separate, explicit Human instruction
recorded in "Approval" below — this paragraph governs what the governance *content* authorizes,
not the push mechanics.

### Open Items

```
1. RESOLVED (V3-GOV-B01 remediation) — Exact model ID verification for Qwen 3.7 Flash, Muse
   Spark 1.3 Contributor, and DeepSeek V4.1 (display-corrected to "DeepSeek V4.1 Flash," the
   only current V4.1 DeepSeek model) is now VERIFIED against fresh `cmdc --list-models` evidence
   — see "Exact Model ID Verification Gap — RESOLVED (V3-GOV-B01)" and "Model Binding Contract
   (V3 Roster)" above. Actual invocation of these three roles is now unblocked on this ground;
   the fail-closed rule in "Model Binding Contract" still governs runtime identity confirmation.
2. RESOLVED by GOV-MM-005 (see above): V3's Implementation Write Owner role REPLACES the Fixed IMP
   Ownership Matrix's domain-owner assignment for IMP-008 forward (Muse Spark 1.3 Contributor by
   default; Claude Code for the Mission-Critical Claude Stages specifically — both roles still
   inside V3's full pipeline, per HD-V3-R2-03), rather than running underneath it.
3. RESOLVED — Independent Codex audit of the full Amendment V3 program. Pass 1 found BLOCKER 1 /
   MAJOR 2 / MINOR 1 (remediated); Pass 2 re-audit found BLOCKER 0 / MAJOR 3 / MINOR 0
   (V3-REAUDIT-M01, V3-GOV-M01 residual, V3-GOV-M02 residual — remediated); Pass 3 re-audit
   result: PASS, BLOCKER 0 / MAJOR 0 / MINOR 0 / EDITORIAL 0 / GATE-IMPACT 0, all findings CLOSED
   — see "Approval" below.
4. RESOLVED — Final Human Lock Approval declaring the full Amendment V3 program `FINAL / LOCKED`
   given following the Pass 3 PASS result — see "Approval" below for the verbatim statement.
```

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
| 004 | Audit + Governance Foundation | **Claude Code — Temporary Completion Owner** (GOV-MM-004; IMP-004 only, expires at FINAL/LOCKED; standing V2 assignment is DeepSeek V4.1 Flash) | none by default |
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
the full, currently-in-effect HOLD condition, and "GOV-MM-004 — IMP-004 Temporary Completion
Ownership Exception" for the current actual owner; this block records the resulting assignment,
not a separate authorization):

```
Primary (current, GOV-MM-004,      Claude Code — Temporary Completion Owner (IMP-004 only)
  IMP-004 only, expires at
  FINAL/LOCKED):
Exact Claude Model Identifier:      claude-sonnet-5 — MODEL RESOLVED, AWAITING HUMAN MODEL
                                     APPROVAL (not yet BOUND — see GOV-MM-004 above)
Standing Amendment V2 assignment
  (resumes automatically at
  FINAL/LOCKED, and applies to
  IMP-005 onward unaffected):       DeepSeek V4.1 Flash (deepseek/deepseek-v4.1-flash)
Specialist:                         none by default (Amendment V2)
Independent Formal Reviewer:        Codex
Human:                               Final Stage Gate authority
```

IMP-004's specification has since passed independent Codex specification audit (0 BLOCKER/MAJOR/
MINOR/EDITORIAL/HUMAN DECISION REQUIRED) and Human has separately stated IMP-004 implementation is
authorized in principle, and separately still has designated Claude Code as IMP-004's Temporary
Completion Owner (GOV-MM-004) — but execution remains on **HOLD**: Amendment V2 itself has not yet
reached `FINAL / LOCKED`, and Claude's own Per-IMP Model Binding for IMP-004 is only
`MODEL RESOLVED`, not yet `BOUND` (a separate, still-outstanding Human approval of the exact
`claude-sonnet-5` identifier — see GOV-MM-004). Neither the "IMP-004 Implementation Authorized"
statement nor the Temporary Completion Owner designation is itself approval of a specific model
generation; those remain separate controls.

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

GOV-MM-004 Authorization (IMP-004 Temporary Completion Ownership Exception):
  Authority:         Human
  Statement:         "Saya setuju. Claude Code ditetapkan sebagai Temporary Completion Owner
                     untuk IMP-004 saja. Setelah IMP-004 FINAL/LOCKED, ownership kembali
                     mengikuti Multi-Model Governance V2 mulai IMP-005."
  Scope:             Designates Claude Code as IMP-004's Temporary Completion Owner, for IMP-004
                     only, automatically expiring at IMP-004 FINAL/LOCKED, with the Amendment V2
                     prospective matrix (IMP-005 onward, and IMP-004's own standing DeepSeek
                     V4.1 Flash assignment for every other purpose) explicitly unaffected. Does
                     NOT approve any specific Claude model generation for IMP-004 by itself — see
                     "Claude Model Binding" below for that separate, since-completed approval.
                     Does NOT authorize application source/migration/test/dependency changes to
                     the pre-existing uncommitted IMP-004 artifacts beyond what the required
                     ownership-provenance review (docs/audits/IMP-004-OWNERSHIP-HANDOFF.md)
                     itself governs, and does NOT authorize git push.

Execution Release (explicit Human instruction, distinct from and subsequent to the two approvals
above): Human explicitly instructed IMP-004 execution to proceed under GOV-MM-004 ("You may now
proceed with IMP-004 ownership handoff and implementation"). This is the Human directly exercising
final authority over this narrow, scoped exception (IMP-004 only) — it does NOT retroactively
declare Amendment V2 or GOV-MM-004 themselves `FINAL / LOCKED` (both remain `PROPOSED`, Codex
re-audit still pending for the base replacement program), and it does NOT affect IMP-005 onward,
which still requires the ordinary Stage Gate sequence (Codex independent review -> Human Stage
Gate) with no execution-ahead-of-audit precedent implied by this one exception.

Amendment V3 Creation Authorization:
  Authority:        Human
  Statement:        Human authorized, at the start of a governance-only session, establishing a
                     token-efficient multi-agent pipeline workflow "starting from IMP-008" and
                     explicitly instructed that IMP-008 itself must NOT be started as part of that
                     same session — supplied as a full structured governance-initialization task,
                     not a single short verbatim quoted sentence, so none is fabricated here; this
                     describes the authorization's scope and substance accurately instead.
  Scope:            Authorizes drafting Amendment V3 (this section) as a `PROPOSED`, not
                     self-declared `FINAL / LOCKED`, governance patch describing a pipeline
                     write-ownership model and a proposed new model roster. Does NOT authorize:
                     verifying or inventing exact model IDs for Qwen 3.7 Flash / Muse Spark 1.3
                     Contributor / DeepSeek V4.1 (left UNRESOLVED — see "Exact Model ID
                     Verification Gap"); changing the Fixed IMP Ownership Matrix's IMP-008 row;
                     IMP-008 readiness or implementation work; application source changes;
                     architecture/business-rule/security/financial baseline changes; rewriting
                     IMP-000 through IMP-007 historical evidence; or git push. Declaring Amendment
                     V3 `FINAL / LOCKED` requires a separate Independent Codex Audit and Final
                     Human Lock Approval (V3), both below, still PENDING, and resolution of the
                     Open Items recorded under "Amendment V3" above.

GOV-MM-005 Authorization (V3 Pipeline Activation, IMP-008 Forward):
  Authority:         Human
  Statement:         "Saya setuju.

                     MULTI-AGENT WORKFLOW V3 APPROVED.

                     The "Amendment V3 — Pipeline Write-Ownership Model" is approved for use
                     prospectively beginning with IMP-008.

                     Human approval authorizes activation of V3 governance.

                     IMP-000 through IMP-007 remain historical FINAL / LOCKED.

                     This approval does NOT authorize IMP-008 implementation."
  Scope:             Activates V3's pipeline structure and role assignment (Claude = Lead
                     Architect; Qwen 3.7 Flash = Recon; Muse Spark 1.3 Contributor = Main
                     Developer/default implementation write owner; DeepSeek V4.1 = Independent
                     Technical Checker; Codex = Final Semantic/Closure Auditor; Human = Final
                     Authority) as `APPROVED / ACTIVE`, effective IMP-008 forward, and resolves
                     Open Item 2 (V3's Main Developer replaces, not runs underneath, the legacy
                     matrix's domain-owner for IMP-008 forward, excluding the Mission-Critical
                     Claude Stages and IMP-030 carve-outs — see "Precedence Over the Legacy
                     Matrix" above). Does NOT authorize IMP-008 readiness or implementation work
                     (per the statement's own final line); does NOT verify or resolve Open Item 1
                     (Exact Model ID Verification for Qwen 3.7 Flash / Muse Spark 1.3 Contributor
                     / DeepSeek V4.1, which this statement does not address and which is not
                     inferred here); and does NOT itself constitute, and is not recorded as, an
                     Independent Codex Audit or a Final Human Lock Approval of the full Amendment
                     V3 program — those remain separate, still-PENDING controls immediately below,
                     exactly as GOV-MM-004 (IMP-004) previously activated a scoped exception via
                     direct Human Decision without first requiring Amendment V2's own full lock.
  Repository Mutation by Human: NO (governance-document patch performed by Claude Code per this
                     authorization; no application source touched)
  Push by this authorization: NOT AUTHORIZED (see "Authorization Scope" above)

Independent Codex Audit (Amendment V3, full program, Pass 1):
  Status:            FAIL — NOT ELIGIBLE FOR HUMAN FINAL LOCK (not required for, and not
                     satisfied by, GOV-MM-005's narrower activation above)
  Reviewer:          Codex
  Findings:          BLOCKER: 1 (V3-GOV-B01); MAJOR: 2 (V3-GOV-M01, V3-GOV-M02); MINOR: 1
                     (V3-GOV-m01); GATE-IMPACT: 3
  Evidence/Reference: Findings as relayed in this session's remediation request; this document
                     records the findings and their remediation exactly as received, without
                     independently re-deriving or embellishing the audit itself.

Codex Findings Remediation (Pass 1):
  Authority:         Claude Code (governance-drafting patch), per this session's explicit
                     instruction to remediate under PATCH — DO NOT REWRITE
  Result:            V3-GOV-B01 CLOSED, V3-GOV-M01 CLOSED, V3-GOV-M02 CLOSED (via companion
                     patches to AI-WORKFLOW.md, IMPLEMENTATION-GOVERNANCE.md, and
                     IMPLEMENTATION-SPEC-TEMPLATE.md), V3-GOV-m01 CLOSED — see "Codex Findings
                     Remediation (Pass 1)" under "Amendment V3" above for the full mapping
  Scope:             Governance documentation only. Does NOT authorize IMP-008 readiness/
                     implementation, application source changes, database changes, or git push.
                     Does NOT itself constitute Independent Codex Audit Pass 2 (re-audit) or Final
                     Human Lock Approval — both remain separate, still-PENDING controls below.

Independent Codex Audit (Amendment V3, full program, Pass 2 — re-audit of Pass 1 remediation):
  Status:            FAIL — NOT ELIGIBLE FOR HUMAN FINAL LOCK
  Reviewer:          Codex
  Findings:          BLOCKER: 0; MAJOR: 3 (V3-REAUDIT-M01, V3-GOV-M01 residual, V3-GOV-M02
                     residual); MINOR: 0; GATE-IMPACT: 3. V3-GOV-B01 and V3-GOV-m01 confirmed
                     still CLOSED (not reopened by Codex).
  Evidence/Reference: Findings as relayed in this session's Pass 2 remediation request; recorded
                     exactly as received, without independently re-deriving or embellishing the
                     audit itself.

Codex Findings Remediation (Pass 2):
  Authority:         Claude Code (governance-drafting patch), per this session's explicit
                     instruction (HD-V3-R2-01/02/03) to remediate under PATCH — DO NOT REWRITE
  Result:            V3-REAUDIT-M01 CLOSED, V3-GOV-M01 residual CLOSED, V3-GOV-M02 residual
                     CLOSED — see "Codex Findings Remediation (Pass 2)" under "Amendment V3"
                     above for the full mapping. V3-GOV-B01 and V3-GOV-m01 PRESERVED, not
                     reopened or re-litigated.
  Scope:             Governance documentation only (MULTI-MODEL-OWNERSHIP.md and
                     IMPLEMENTATION-SPEC-TEMPLATE.md). Does NOT authorize IMP-008
                     readiness/implementation, application source changes, database changes, or
                     git push. Does NOT itself constitute Independent Codex Audit Pass 3 or Final
                     Human Lock Approval — both remain separate, still-PENDING controls below.

Independent Codex Audit (Amendment V3, full program, Pass 3 — re-audit of Pass 2 remediation):
  Status:            PASS — ELIGIBLE FOR HUMAN FINAL LOCK
  Reviewer:          Codex
  Findings:          V3-GOV-B01 CLOSED; V3-GOV-M01 CLOSED; V3-GOV-M02 CLOSED; V3-GOV-m01 CLOSED;
                     V3-REAUDIT-M01 CLOSED. BLOCKER: 0; MAJOR: 0; MINOR: 0; EDITORIAL: 0;
                     GATE-IMPACT: 0.
  Audited Baseline:  commit ba44336
  Evidence/Reference: Findings as relayed in this session's finalization request; recorded exactly
                     as received, without independently re-deriving or embellishing the audit
                     itself — see "Codex Findings Remediation (Pass 3 — Final Re-Audit) and Human
                     Final Lock" under "Amendment V3" above.

Final Human Lock Approval (Amendment V3, full program):
  Status:            APPROVED
  Human Approver:    Human
  Statement:         "Saya setuju. MULTI-AGENT WORKFLOW V3 — HUMAN FINAL LOCK APPROVED."
  Scope:             Locks Amendment V3 (full prospective program) as `FINAL / LOCKED`, following
                     the Pass 3 Codex PASS result immediately above. Effective IMP-008 forward,
                     subject to the documented Mission-Critical Claude Stages and IMP-030
                     exceptions (unchanged by this lock — see "Precedence Over the Legacy
                     Matrix"). Does NOT authorize IMP-008 readiness, specification, or
                     implementation work, application source changes, or database changes — each
                     remains gated on its own separate future Human authorization (see
                     "Authorization Scope" above). Does NOT reopen, redesign, or change the
                     semantics of any already-CLOSED finding, model binding, or pipeline step
                     recorded in Pass 1/Pass 2 above. Separately authorizes pushing this
                     finalization commit to origin/master, per this session's explicit push
                     instruction (distinct from the governance-content authorization above, per
                     this document's established practice of treating push as its own control).

Claude Model Binding for IMP-004 (GOV-MM-004, distinct control from owner designation above):
  Status:            BOUND
  Exact Model Identifier: claude-sonnet-5
  Model Display Name: Claude Sonnet 5
  Claude Code Version: 2.1.269
  Execution Environment: Claude Code (VS Code extension; entrypoint claude-vscode)
  Resolution Evidence: this session's own runtime/configuration context; not guessed, not
                     inferred from a generic invocation
  Human Approval Statement: "Claude Sonnet 5 (claude-sonnet-5) is BOUND as the Claude Code model
                     for Temporary Completion Owner IMP-004."
  Human Approver:    Human
  Approval Date:     2026-09-13
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
