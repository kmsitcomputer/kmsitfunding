# IMP-005 Specification Remediation 1

Task:
IMP-005 CMS — SPECIFICATION REMEDIATION ONLY (Patch 1)

Branch:
`spec/005-cms` (verified via `git rev-parse --abbrev-ref HEAD` before any change)

Starting HEAD:
`556cd565ce29a1672af16368a10a5a7f1ddb6e33` (verified; matches the expected baseline)

Target document:
[docs/implementation/IMP-005-cms.md](../implementation/IMP-005-cms.md)

Coding Performed:
NO — no application code, no model, no service, no policy, no controller, no route, no Vue,
no migration, no test file was created or modified by this pass.

## Working Tree Condition At Start — DISCLOSED

This pass did **not** start from a clean working tree, and that deviation is recorded rather than
smoothed over:

```text
Expected:                 clean tree at 556cd56
Actual at start:          M docs/implementation/IMP-005-cms.md
                          (+855 / -141 lines, uncommitted, unstaged)
Any other dirty path:     NONE — the only modified file was the IMP-005 specification itself
HEAD commit content:      `git show 556cd56` contains NO remediation markers; every
                          IMP005-SPEC-NN section existed only in the uncommitted working copy
```

Characterisation of the pre-existing uncommitted content: it was an **interrupted partial run of
this same remediation task**, not unrelated work — patch-shaped (855 insertions against only 141
deletions, i.e. additive rather than a rewrite), confined to the one file this task targets, and
organised around the same finding IDs. It was therefore continued rather than discarded, because
discarding it would have destroyed correct work on the identical contract.

What that left behind was the real problem: the partial pass had **appended** corrected sections
without **removing** the superseded ones, so the document contained paired old/new contracts
asserting opposite things. Completing the pass therefore meant eliminating those pairs, which is
what most of this pass did. Specific contradictions that were live in the working copy before this
pass finished:

| Surviving stale contract | Contradicted |
|---|---|
| A second `### cms_articles` block declaring `is_homepage`, `homepage_uniq`, and a per-entity `active_slug` with a config article prefix | the singleton-assignment and shared-namespace designs directly above it |
| `UNIQUE(...) WHERE purpose='CURRENT'` and `page_key`/`article_key` in `cms_paths` | columns that only exist on the revisions table, and partial-index syntax MySQL 8 does not have |
| §18 `MediaService`: "reference = media ULID token present in body_html — token scan" | §13 `cms_media_references`, i.e. the exact defect IMP005-SPEC-05 removes |
| §26: "homepage assign: lock both rows and flip flags", "slug move + slug_history write" | the singleton row and `cms_paths` designs |
| §27: "freeing the old path is allowed because the unique index ignores inactive rows" | §14's redirect-reservation policy |
| §12 registering `content.media.deleted` while §19 audited `content.media.archived` | each other |

Nothing in this record should be read as the earlier run being wrong in its direction; its design
choices were sound and are retained. The defect was incompleteness, and incompleteness in a
specification is more dangerous than an empty document because it reads as settled.

## Human Decisions — Unchanged

```text
HD-IMP005-01  PASS  (unchanged)  Q29 — no editorial approval workflow; authoring/publish separated
HD-IMP005-02  PASS  (unchanged)  Q30 — menu/navigation presentation deferred to IMP-006
HD-IMP005-03  PASS  (unchanged)  Q31 — single-locale content baseline
HD-IMP005-04  PASS  (unchanged)  Q32 — minimal scheduled publish/unpublish via Laravel Scheduler/Cron
HD-IMP005-05  PASS  (unchanged)  Q33 — Article canonical; News is a classification
```

New Human Decisions Raised: **0**
Open Human Decisions: **0**

Cleanup grace periods introduced in this pass (`cms.media.orphan_grace_hours`,
`cms.media.purge_grace_days`) are **configuration**, explicitly not legal or compliance retention
durations, and deliberately not raised as Human Decisions. No legal retention duration is asserted
anywhere in the specification.

## Per-Finding Disposition

Severity per finding is not restated here: the Pass-0 report supplied aggregate counts
(BLOCKER 0 / MAJOR 7 / MINOR 3 / EDITORIAL 0 / HUMAN DECISION 0) without re-tagging the individual
IMP005-SPEC-NN identifiers, and inventing a mapping would be fabrication. Findings are tracked by ID.

```text
IMP005-SPEC-01  PATCHED / PENDING CODEX RE-AUDIT   homepage contract vs system route
IMP005-SPEC-02  PATCHED / PENDING CODEX RE-AUDIT   homepage singleton invariant
IMP005-SPEC-03  PATCHED / PENDING CODEX RE-AUDIT   global path namespace
IMP005-SPEC-04  PATCHED / PENDING CODEX RE-AUDIT   revision immutability contradiction
IMP005-SPEC-05  PATCHED / PENDING CODEX RE-AUDIT   media reference model completeness
IMP005-SPEC-06  PATCHED / PENDING CODEX RE-AUDIT   media cleanup model / concurrency / actor
IMP005-SPEC-07  PATCHED / PENDING CODEX RE-AUDIT   complete audit event inventory
IMP005-SPEC-08  PATCHED / PENDING CODEX RE-AUDIT   nested slug normalization + path length + registry
IMP005-SPEC-09  PATCHED / PENDING CODEX RE-AUDIT   media token + sanitizer contract
IMP005-SPEC-10  PATCHED / PENDING CODEX RE-AUDIT   media index column name
```

No finding is marked RESOLVED by this document. Self-marking a finding resolved is the audited
party's own privilege and this pass is the audited party; only Codex re-audit closes them.

### IMP005-SPEC-01 — Homepage contract vs system route

Contradiction removed by keeping exactly one contract, in the direction the remediation instruction
preferred: `/` stays application-owned and CMS never owns a route.

- §8 rewritten: `/` is registered by application code only; CMS owns a **content designation**, which
  is data, never a route; `/` is additionally a reserved member of the registry so it is
  structurally unclaimable independently of the homepage question.
- Single resolution chain stated: Application Router → `/` → `HomepageContentResolver` → designated
  page identity → current published revision → neutral `PublishedContent` → presentation by IMP-006.
- Explicit non-goals recorded: not route ownership, not router shadowing, not a Theme Engine, not a
  second payload contract.
- "Feature removed instead" was considered and rejected: the designation store plus the read
  contract is a small, self-contained, auditable surface, and IMP-006 needs a defined thing to render
  rather than an invented one. The read contract existing early does not make CMS a route owner.
- Note kept deliberately: in IMP-005 the rendered output of `/` is unchanged — only the contract lands.

### IMP005-SPEC-02 — Homepage singleton

Pattern B (single-row assignment table `cms_homepage_assignment`, `CHECK (id = 1)`) chosen over
Pattern A (generated discriminator). The instruction's warning was accepted precisely: a
discriminator generated from `is_homepage` enforces "at most one" but has **no row to lock while the
slot is empty**, so a swap can leave the slot momentarily free and admit a third assignment. The
singleton row always exists, so assign / replace / clear all serialize on it.

Concurrent-assignment contract defined for the empty-slot case: lock the singleton → re-read after
acquiring → reject the loser with `409 homepage_assignment_conflict` → no automatic retry, with
`expected_page_id` as the only deliberate-replacement path. Two active designations are impossible
by row-count invariant plus lock, never by pre-check.

### IMP005-SPEC-03 — Global path namespace

`cms_paths` is the one reservation registry; the per-table `active_slug` uniques and
`cms_slug_history` are gone. Claim / rename / release / lock-order / unique-index /
concurrent-collision semantics are each defined (§13, §14), and redirect shadowing is impossible by
construction because a REDIRECT claim occupies the same unique slot as a CURRENT claim.

Path history reuse policy (the instruction's §7) resolved to the safest v1 baseline: **an old path
stays reserved while its redirect exists**; reuse only after an explicit governed `RELEASE`; no
silent expiry and no invented retention duration.

Also fixed inside this finding, which the earlier partial run had gotten wrong: the constraint is
expressed with STORED generated columns (`active_path`, `active_current_owner`) rather than
`WHERE purpose='CURRENT'`, because MySQL 8 has no partial index — and the owner key is a `'P'/'A'`
-prefixed string because `page_id` and `article_id` come from independent sequences and would
otherwise produce false collisions on a shared numeric key.

### IMP005-SPEC-04 — Revision immutability

Resolved with the payload/lifecycle split the instruction preferred: payload columns freeze at the
moment a revision leaves DRAFT; lifecycle columns (`state`, `published_at`, `superseded_at`,
`state_changed_by_principal_id`) move only through `PublicationService`. Draft→published,
published→superseded, replacement publication, scheduled replacement, rollback-by-copy and the
forbidden direct mutations are each enumerated, and the per-state writable sets are stated
exhaustively rather than by example. AuditRecord's unconditional no-UPDATE rule is explicitly **not**
copied onto revisions, with the reason given. Enforcement is application-level (service + model
guard), stated as a deliberate non-trigger choice, and the raw-SQL boundary is named as a deployment
concern rather than claimed as an application defense.

### IMP005-SPEC-05 — Media reference model

`cms_media_references` records every media relationship, with a **closed** `field_path` vocabulary
(`body_html`, `og_image_asset_id`) so completeness is by construction: a new media-bearing field must
be added to the vocabulary and its omission is a review blocker. `og_image_asset_id` and
historical-revision usage — the two things a body-token scan cannot see — are named as the specific
blind spot removed. Attachment protocol (lock asset → verify attachable → write reference → persist
normalized rows → commit together) and the shared five-tier lock order are defined for attachment,
publication, reference mutation, archive and purge alike, with deletion rechecking under lock rather
than check-then-act. Historical rendering is reconciled with deletion: media referenced by any
PUBLISHED or SUPERSEDED revision is never physically purged, and logical archive is distinguished
from purge everywhere.

### IMP005-SPEC-06 — Media cleanup model

The rule keyed on a nonexistent `owner content` column is gone. Three cases are separated with
distinct strategies: **A** orphan filesystem file with no row (bounded scan, generated-name grammar
as the join key, configurable grace, unexpected names *reported not deleted*, log-recorded since no
DB row exists to audit); **B** unreferenced asset (candidate → lock → recheck → eligibility →
unlink **before** DB transition → audit; filesystem failure keeps the row as retry evidence);
**C** referenced asset (never purged). Actor authority reuses the IMP-004/IMP-003 System Principal
catalog with an explicit operation identifier (`content.media_cleanup`), a single explicit
`content.archive` grant, canonical evaluator checks, no Human impersonation, and **no new actor
kind**.

### IMP005-SPEC-07 — Complete audit event inventory

Twenty events, each with actor kinds, subject type/ID, criticality, persistence strategy,
transaction semantics, closed metadata allow-list, visibility/read scope, and source-event /
idempotency behavior — no "metadata TBD" anywhere. Added by this pass because the partial run had
prose that referenced events the registry never registered: `content.media.archived` (replacing the
incoherent `content.media.deleted`), `content.media.purged`, `content.media.updated`,
`content.homepage.assigned`, `content.path.released`. A mutation→event map proves coverage and, per
the instruction's warning against duplicates, deliberately gives **no** standalone event for path
claim / rename / redirect creation / auto-release-on-archive, each of which rides the publish or
archive event that already describes it. Criticality was **not** inflated — publication, archive and
media removal stay NON_CRITICAL with per-row justification, and the pre-existing
`security.authorization.denied` CRITICAL/DENIAL_DURABLE path continues to cover unauthorized
attempts. NON_CRITICAL failure semantics are quoted from IMP-004 "Non-Critical Events" (commit
proceeds; reported through the application log tagged `audit_write_failed`; never queued; never
replayed to recover the event) rather than paraphrased as "ignore audit failure".

### IMP005-SPEC-08 — Nested normalization, path length, reserved registry

Normalization is per segment (`/news/My First Story` → `/news/my-first-story`, explicitly **not**
`news-my-first-story`), with empty/dot/traversal segments rejected rather than laundered. The
"191 **segments**" mistake is corrected to 191 **characters**, mapped 1:1 onto
`VARCHAR(191) utf8mb4` (764 bytes, inside InnoDB's 3072-byte index limit) so no second
`path_hash` representation needs keeping in sync, plus per-segment and depth bounds as configuration.
The reserved registry is now **derived** from the live Laravel route table plus protected prefixes,
framework/ops paths, and a deny-overrides list, with write-time, boot-time and test-time enforcement;
`/forgot-password` (a real route the handwritten list missed) is reconciled, and the enumeration is
labelled illustrative with the derivation normative. A test registers a new dummy application route
and proves rejection with no CMS-side edit.

### IMP005-SPEC-09 — Media token + sanitizer

One placeholder contract: `data-media="<ULID>"` on `img` (and `a` for non-inline documents), with the
ULID grammar given and invalid tokens **rejected loudly** rather than stripped — stripping silently
loses an author's image. `src` is forbidden in stored content and the renderer is the only producer of
media URLs. The sanitizer allow-list names the media attributes explicitly, and the round trip
(input → sanitizer → stored → resolver → rendered output) plus sanitizer idempotence are stated as
testable invariants. Token validation covers syntax, existence, attachable state, reference creation,
unknown-asset rejection and `og_image_asset_id` as an equal path, not a lesser one.

### IMP005-SPEC-10 — Media index column

`(uploaded_by, archived_at)` → `(uploaded_by_principal_id, archived_at)`; `uploaded_by` was not a
column on the table.

## Additional Structural Work (not findings, but required by the remediation sections)

```text
§26  transaction model rewritten into 7 named flows (publish, path change, attach, archive/purge,
     scheduled publish, upload intake, homepage assignment) with one shared lock order, plus a
     9-row concurrency matrix giving lock / constraint / loser behavior / retry for every
     scenario the instruction listed
§27  five concerns separated (withdraw, archive, logical archive, purge, release) and "delete"
     ruled a non-term for any CMS row
§29  42 named counterexample tests added across the H/P/R/M/A/S/T series, with the tests that
     SQLite cannot evidence marked as requiring real MySQL 8 — SQLite serializes database-wide
     writes, so a SQLite green on a row-lock race would be false evidence
§32  AC-19..AC-24 added so each remediated contract is independently verifiable at review
§18  service names made normative and the stale ones removed (SlugService → PathService;
     MediaService reference semantics; HomepageContentResolver / MediaTokenResolver /
     MediaCleanupService added)
```

## Passed Areas — Not Regressed

Authority consistency; the CMS domain boundary; the Theme boundary (IMP-006 still owns rendering and
this pass added no presentation); the Campaign boundary; the Partner/Fundraiser boundary; the
Article/News decision; permissions; authorization; the System Principal model (consumed, not
extended — no new actor kind); the audit namespace (`content.*`, never `cms.*`); the criticality
principle (not inflated); Q26; Q27; Q28; upload security; single locale; the API boundary; shared
hosting (still no Redis/Kafka/Elasticsearch/worker requirement); admin/theme isolation;
authoring/publish separation; SVG rejection; editor independence (still no editor library named).

## Deliberately Not Done

- No finding is marked RESOLVED. All ten are PATCHED / PENDING CODEX RE-AUDIT.
- No implementation code, migration, model, service, policy, controller, route, Vue component or
  test was written.
- No IMP-006 work was begun.
- No merge and no push.
- No new Human Decision was raised; none proved unavoidable.

## Gate Status After This Pass

```text
IMP-005 specification:   PATCHED — PENDING CODEX RE-AUDIT
Transaction model:       READY (for re-audit)
Concurrency model:       READY (for re-audit)
Deletion/archive:        READY (for re-audit)
Test strategy:           READY (for re-audit)
Open Human Decisions:    0
Implementation:          NOT AUTHORIZED
Merge:                   NOT AUTHORIZED
Push:                    NOT AUTHORIZED
```

"READY" here means "complete enough to be audited", and is this pass's own opinion of its own work.
It is not a resolution: Codex re-audit is the gate, and implementation remains unauthorized until
that audit is clean **and** the Human Authority separately authorizes it.
