# IMP-005 Specification Remediation — Single AI Agent Pass

Task:
IMP-005 CMS — SPECIFICATION REMEDIATION under Human-designated "Single AI Agent — IMP-005 CMS" mode
(patch, not rewrite).

Branch:
`impl/005-cms` (verified via `git branch --show-current` before any change; the specification text
on this branch is byte-identical to `spec/005-cms`'s current tip, independently verified with
`git diff spec/005-cms:docs/implementation/IMP-005-cms.md impl/005-cms:docs/implementation/IMP-005-cms.md`
— zero output).

Starting HEAD:
`b8d8e4af78b808fb5ef6d8bf96aecceb526299f8` ("feat(imp-005): CMS authorization — permissions +
policies (slice 2 of 6+)")

Working tree at start:
CLEAN.

Target documents:
[docs/implementation/IMP-005-cms.md](../implementation/IMP-005-cms.md) (edited this pass)
[docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md)
(evaluated, NOT edited this pass — see Finding SPEC-AUDIT-01 below)

Preceding evidence (this session, same agent, prior turn):
An independent read-only formal audit was performed against this same specification text before
this remediation pass, producing two findings (there reported as `IMP005-CLAUDE-AUDIT-01/02`,
renumbered `SPEC-AUDIT-01/02` below for this pass's frozen finding set — same substance, same
evidence, no new derivation).

## Human Decisions — Unchanged

HD-IMP005-01 through HD-IMP005-05 (§25 of the specification) are NOT reopened, NOT reinterpreted,
and NOT altered by this pass. Neither finding below touches their content.

## Frozen Finding Set (audit phase, prior turn; re-confirmed against disk immediately before this
pass, not re-derived)

```
SPEC-AUDIT-01
Severity: BLOCKER
Evidence: docs/implementation/IMP-005-cms.md §25 states HD-IMP005-01..05 are "materialized as
  Q29-Q33 in docs/01-requirements/HUMAN-DECISION-REGISTER.md". Independently read that file in
  full: its Register table and Extended Decisions section stop at Q28. No Q29-Q33 entry exists.
  Re-confirmed immediately before this pass: `grep -c "Q29\|Q30\|Q31\|Q32\|Q33"
  docs/01-requirements/HUMAN-DECISION-REGISTER.md` = 0.
Authority: docs/00-governance/DOCUMENT-AUTHORITY.md — the Human Decision Register is Level 1.
Impact: the specification's own Level-1 provenance claim does not currently hold against
  repository state.
Required Remediation: add Q29-Q33 to the register (content already exists verbatim in
  IMP-005-cms.md §25, with stated Human provenance — this would be transcription, not new
  authorship).
Human Decision Required: YES — see "Escalation" below. This reclassifies the original assessment:
  the DECISION CONTENT needs no new Human input, but docs/01-requirements/HUMAN-DECISION-
  REGISTER.md's own "Change Control" section states, without exception, "Human Decision Required
  is fixed at YES... no AI agent may approve it" for any change to that file. That governance text
  overrides this pass's own initial judgment that the edit was a safe mechanical transcription.
Implementation Gate Impact: YES (BLOCKER).
Remediation status this pass: NOT REMEDIATED — escalated, not bypassed.

SPEC-AUDIT-02
Severity: MAJOR
Evidence: docs/implementation/IMP-005-cms.md, former line 3407 (§26 concurrency matrix), row
  "Human publish vs Human publish (same identity)": named the decider as "optimistic
  `expected_current_revision_id`" — an identifier appearing nowhere else in the 4,168-line
  document (not in §10, §18, §26 flow 1, or §29), unlike `edit_version`/`expected_edit_version`
  which are fully specified (field, init, increment, compare mechanism, transaction boundary).
  §26 flow 1's actual publish procedure instead re-verifies, UNDER the tier-3 lock, that the
  candidate revision is still the identity's live draft or a valid RETIRED re-publish target — a
  pessimistic lock-and-reread, not an optimistic client-supplied token.
Authority: internal document consistency; §26's own header states "each row is a §29 test", and
  §29 lists "every §26 concurrency-matrix row's test" as MySQL-8-required — so this row is
  mandatory, executable scope, not descriptive color.
Impact: an implementer could not write this row's test without inventing a request parameter this
  specification never defines.
Required Remediation: restate the row's decider using flow 1's own already-specified mechanism.
Human Decision Required: NO — pure Level-5 specification-text correction, the same class of fix
  the prior Codex remediation passes (REMEDIATION-1, REMEDIATION-2) made autonomously.
Implementation Gate Impact: YES (MAJOR) before this pass.
Remediation status this pass: REMEDIATED.
```

## Remediation Applied

`docs/implementation/IMP-005-cms.md`, §26 concurrency matrix, the "Human publish vs Human publish
(same identity)" row: replaced the undefined `expected_current_revision_id` token with a restatement
of flow 1's own pessimistic lock-and-reread mechanism ("UNDER that lock, re-verify the candidate
revision is still the identity's live draft (or a valid RETIRED re-publish target) — the same
pessimistic re-read flow 1 (section 26) already performs, not a client-supplied optimistic token").
No other text on the page was touched. Post-edit verification:
`grep -c "expected_current_revision_id" docs/implementation/IMP-005-cms.md` = 0.

No code, no migrations, no tests were changed in this pass. `docs/01-requirements/HUMAN-DECISION-
REGISTER.md` was read but not written.

## Escalation — HUMAN DECISION REQUIRED

```
HUMAN DECISION REQUIRED

Question: May Q29-Q33 (HD-IMP005-01..05, already FINAL/LOCKED per IMP-005-cms.md §25 with stated
  Human provenance) be transcribed into docs/01-requirements/HUMAN-DECISION-REGISTER.md's Register
  table and Extended Decisions section, mirroring exactly how Q26-Q28 were added for IMP-004?

Why engineering cannot resolve it: docs/01-requirements/HUMAN-DECISION-REGISTER.md's own "Change
  Control" section states, without exception: "Human Decision Required is fixed at YES... no AI
  agent may approve it" for any change to that file. This is a repository-authored governance rule,
  not an engineering judgment call, and this task's own instructions (§20) direct: "If repository
  governance contains a stricter explicit Human Implementation Authorization requirement that
  cannot be superseded by this instruction, STOP at that checkpoint... Do not bypass repository
  governance."

Affected authority: Level 1 — Human Decision Register (docs/00-governance/DOCUMENT-AUTHORITY.md).

Available options:
  (a) Human explicitly authorizes this transcription now (content is already fixed by §25; this
      would be a same-session approval, not a new decision).
  (b) Human performs or separately authorizes the register edit outside this session.
  (c) IMP-005-cms.md §25's own provenance claim is corrected instead (e.g., to describe where the
      decisions are CURRENTLY recorded, without asserting register materialization that has not
      happened), deferring the register update to a later, separately-authorized pass.

Recommended option: (a) — the decision content is not in dispute (§25 already states it with clear
  Human provenance from the originating task), so authorizing the transcription costs nothing
  Human hasn't already committed to, and it is the more efficient path to closing this BLOCKER.

Impact if left unresolved: BLOCKER count remains 1; per this task's own §18 "Specification Pass
  Condition" (BLOCKER = 0 required) the specification cannot legitimately reach PASS, and per §40
  the overall Stage Gate eligibility condition also cannot be met, regardless of how much further
  implementation work is completed.
```

## Gate Status After This Pass

```
BLOCKER:                      1 (SPEC-AUDIT-01 — escalated to Human, not remediated)
MAJOR:                        0 (SPEC-AUDIT-02 remediated)
MINOR:                        1 (carried forward informationally from the prior audit turn —
                                  pre-existing IMP-004 doc/test count drift, out of IMP-005 scope,
                                  not gate-impacting)
EDITORIAL:                    1 (carried forward informationally — `stale_publication` used across
                                  three phrasings with no single glossary entry; not gate-impacting)
IMPLEMENTATION-GATE FINDINGS: 1 (SPEC-AUDIT-01)
HUMAN DECISION REQUIRED:      1 (SPEC-AUDIT-01 — see Escalation above)

SPECIFICATION PASS CONDITION (this task's §18): NOT MET.
  BLOCKER = 0?              NO (1 open, pending Human)
  MAJOR = 0?                YES
  IMPLEMENTATION-GATE = 0?  NO
  HUMAN DECISION = 0?       NO
  => Per this task's own rules, this halts the automatic Remediate -> Re-Audit cycle at this
     checkpoint. Not a repository-safety stop; a Human-authority stop.
```
