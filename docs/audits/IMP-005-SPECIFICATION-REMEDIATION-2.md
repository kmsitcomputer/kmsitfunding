# IMP-005 Specification Remediation 2

Task:
IMP-005 CMS — SPECIFICATION REMEDIATION PASS 2 ONLY (patch, not rewrite)

Branch:
`spec/005-cms` (verified via `git rev-parse --abbrev-ref HEAD` before any change)

Starting HEAD:
`b61c0f9646a9ad30068359ccef3a08c260746d3c` (verified; matches the expected baseline exactly)

Working tree at start:
CLEAN. No disclosure required this pass — unlike Pass 1, this pass began from the state the
expected baseline describes, with no pre-existing uncommitted work to characterise.

Target document:
[docs/implementation/IMP-005-cms.md](../implementation/IMP-005-cms.md)

Previous remediation evidence:
[docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md](./IMP-005-SPECIFICATION-REMEDIATION-1.md)

Coding Performed:
NO — no application code, model, service, policy, controller, route, migration, seeder, Vue
component or test file was created, modified or deleted. `app/`, `routes/`, `database/`,
`resources/`, `tests/`, `config/` and `bootstrap/` are untouched; `database/migrations/` does not
exist in this repository and no migration was introduced.

---

## Trigger

Codex Re-Audit Pass 1 verdict:

```text
FAIL — IMP-005 SPECIFICATION REQUIRES FURTHER REMEDIATION
BLOCKER: 0   MAJOR: 5   MINOR: 0   EDITORIAL: 0   HUMAN DECISION REQUIRED: 0
IMPLEMENTATION-GATE FINDINGS: 5
```

All five findings were remediated in place. No new Human Decision proved necessary.

---

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

Every one of the five fixes stayed inside the ENGINEERING CHOICE boundary the decisions already
grant: transaction ordering, referential integrity, write-boundary enforcement, media state
semantics and audit payload shape are representation and mechanism questions, not business-policy
questions. None of them required a new product decision, and none reopened an existing one.

---

## Per-Finding Disposition

```text
IMP005-REAUDIT-R1-01  PATCHED / PENDING CODEX RE-AUDIT  executable path rename transaction
IMP005-REAUDIT-R1-02  PATCHED / PENDING CODEX RE-AUDIT  revision ownership integrity
IMP005-REAUDIT-R1-03  PATCHED / PENDING CODEX RE-AUDIT  Eloquent bulk-update false claim
IMP005-REAUDIT-R1-04  PATCHED / PENDING CODEX RE-AUDIT  media archive rule / lock order / concurrency
IMP005-REAUDIT-R1-05  PATCHED / PENDING CODEX RE-AUDIT  audit metadata contract
```

No finding is marked RESOLVED by this document. Self-marking a finding resolved is the auditor's
privilege and this pass is the audited party; only Codex re-audit closes them.

---

### IMP005-REAUDIT-R1-01 — Executable path rename transaction

The finding was correct: the specification's rename order could not execute. `UNIQUE(active_current_owner)`
is an immediate constraint over the owner key, and the documented order inserted the new CURRENT claim
while the old row was still CURRENT — two rows, one identical non-NULL owner key, guaranteed duplicate-key
failure before the conversion step could ever run. MySQL 8 has no deferred unique constraint to excuse
it, so this was not a subtlety to work around but an impossible instruction.

Sections changed:

- **§14 "Claim / rename / release mechanics"** — rewritten. RENAME now runs: lock identity → lock the
  owner's ACTIVE CURRENT claim → validate target → **convert** old CURRENT→REDIRECT → **insert** new
  CURRENT → move identity pointers → supersede old revision → emit parent audit → commit. A
  step-by-step constraint audit proves no intermediate state violates either unique index (after the
  conversion the owner-key column is NULL on the old row, so zero rows carry the key; after the
  insert exactly one does, and the two `active_path` values differ). The transient owner-less window
  is uncommitted, and the old row is write-locked throughout, so the resolver's non-locking reads
  keep seeing the pre-rename snapshot until commit.
- **§14 "FAILURE SAFETY"** (new, normative) — a step-5 failure rolls the whole transaction back, and
  the rollback *is* the restoration of the old CURRENT claim: the storage engine's undo, not
  application compensating logic. Explicitly prohibits splitting the rename across transactions and
  explicitly rejects catch-and-manually-restore, because a second route back to the same row is how
  a "repaired" namespace diverges from its own history. No intermediate externally visible broken
  state exists; the redirect-to-nothing state is unrepresentable because nothing commits it.
- **§14 "AVAILABILITY IS DECIDED BY THE CONSTRAINT, NOT BY THE PRE-CHECK"** (new) — step-3
  validation is retained for clean 422 classification and is stated as non-authoritative; the
  pattern is `application validation + database unique constraint`, with the insert as the
  reservation. Both shortcut paths (dropping pre-check entirely; adding a reservation row to make it
  authoritative) are named and rejected with reasons.
- **§13 `cms_paths`** — a `RESERVATION POLICY` block now states the single rule: a path is reserved
  while an ACTIVE row holds it and only while such a row exists; reservation depends on the row,
  never on the owner's lifecycle state. `revision_id` became `NOT NULL` with an ownership
  constraint (see R1-02).
- **§13 "Path history & reuse policy"** — the three coexisting rules consolidated into one.
  **No automatic path reuse in IMP-005 v1.** Retiring releases nothing; archiving releases nothing;
  redirects and history stay reserved; an explicit governed release is the only un-reservation, and
  no bulk/expiry reclaim tooling exists or is implied. The rejected alternatives (release on
  RETIRED, automatic release on ARCHIVED) are recorded with the reasons, including the
  retired-URL-repointing hazard that made the "safe-looking" archive release the unsafe choice.
- **§13 superseded `cms_slug_history` note**, **§14 resolution/404 table**, **§27 items 1 and 5 and
  the term table** — all restated to the same rule. §14 now separates RESERVATION from RESOLUTION:
  200 for a CURRENT claim whose owner is PUBLISHED, 301 only for a REDIRECT whose owner is PUBLISHED
  *and* holds a CURRENT claim, and 404 for everything else including RETIRED and ARCHIVED owners —
  which also removes the "404 at the end of a 301" case without needing to release anything.
- **§26 flows 1 and 2** — rewritten to the executable order; flow 2 now states that a rename is the
  path portion of flow 1 rather than a separate transaction, so the two cannot drift.
- **§9 domain rules**, **§18 PathService**, **§28 threat model** (namespace-squatting row),
  **§12 mutation→event map** (auto-release row removed) — reconciled.

MySQL test requirement added: **P8** (rename commits under the real unique set — the test the old
order would fail), **P9/P9b** (collision rolls back and restores old CURRENT with no compensating
write), **P10/P11/P12** (retire/archive reservation consistency, release as the only un-reservation),
plus concurrency-matrix rows for rename-vs-claim, rename-vs-rename and rename-vs-release. **AC-25**
added.

### IMP005-REAUDIT-R1-02 — Revision ownership integrity

The finding was correct and this was the most substantive fix: five pointer columns were plain
single-column FKs, so "a pointer naming another identity's revision" was a legal database state that
only disciplined code prevented.

**Option A (composite FK) was chosen as the authority, with Option B's service check retained as
the diagnostic layer rather than as the mechanism.** A candidate key was rejected only after it was
established that the composite form works on both target engines, including the NULL semantics that
make it correct (an unset pointer is not a violation; a wrong pointer is).

- **§13 `cms_content_revisions`** — a `CANONICAL OWNER` block (the existing `page_id`/`article_id`
  FK + CHECK XOR, now named as *the* canonical ownership relation) and four candidate keys
  `UNIQUE(id, page_id)`, `UNIQUE(id, article_id)`, `UNIQUE(page_id, id)`, `UNIQUE(article_id, id)`
  existing solely as composite-FK targets. Two orders are needed because each child lists the columns
  differently; that is stated rather than tidied away. The generated `page_key`/`article_key`
  columns are explicitly **not** FK targets, because `COALESCE(…, 0)` would let a bogus owner value
  of 0 satisfy the constraint.
- **§13 `cms_pages` / `cms_articles`** — `REVISION-POINTER OWNERSHIP`: all three pointers on each
  identity are composite FKs `(id, pointer) -> revisions(id, <owner>)`, so Page→Article and
  Article→Page mixing is unrepresentable (an Article-owned revision has NULL `page_id`, which can
  never equal a non-NULL page id) as cleanly as cross-owner mixing within a kind.
- **§13 `cms_paths.revision_id`** — `NOT NULL` + two composite FKs keyed on the claim's own owner
  column; **§13 `cms_media_references.owner_revision_id`** — same pattern, with the owner columns
  documented as *constrained denormalization* (retained for one-index lookup, proved by the FK)
  rather than trusted input, plus `reference_kind`/`field_path` CHECK pairing so the vocabulary
  cannot drift into free text.
- **§13 DDL ordering consequence** — recorded because it is a real trap: mutual RESTRICT references
  mean the tables must be created in three steps and identity rows must be inserted with NULL
  pointers then updated inside the same transaction.
- **§11 "REVISION POINTER OWNERSHIP"** — the invariant stated once with all four prohibited mixing
  classes; **§13 end of section** — a **Relational ownership recheck** table (the §46 audit)
  listing every pointer, its mechanism, whether the engine enforces it, and its test, plus the three
  cross-table facts FKs cannot express and where each is instead enforced. Deliberate non-FKs have
  their reason stated rather than omitted (`audit_records.subject_id` is IMP-004's locked decision
  and is not reopened).
- **§29 O-series** — O1–O8 for the four mixing classes plus paths/media/schedule binding, **O9** as
  the raw-SQL DB-bypass variant on MySQL 8 (the test that distinguishes "structurally
  unrepresentable" from "validated by the code that was supposed to validate it"), and **O10** as
  the positive control so the series cannot pass by rejecting everything. Matrix rows added for each
  class; **AC-26** added.

### IMP005-REAUDIT-R1-03 — Eloquent bulk-update false claim

The finding was factually right and this was a correctness fix to the specification's own
engineering claims, not a design change. `Model::query()->update([...])` issues one UPDATE without
hydrating models, so `saving`/`updating` never fire and a model-level guard is unreachable from that
path. The Pass-1 text claimed otherwise, and §29's R4 was written to assert it.

- **§11** — the enforcement list is rewritten as nine defense-in-depth layers with each layer's scope
  stated honestly. Layer 1 is the **write boundary** (RevisionService/PublicationService only; no
  generic repository, no public update, no bulk surface; exactly four named operations). Layer 2 is
  the **per-operation field whitelist** (`createDraft`/`editDraft`/`publish`/`supersede` with closed
  writable sets). Layer 3 keeps the model guard but names what it can and cannot reach. Layer 5 is
  the **structural source-scan test** — the only control that can catch a bulk update. A dedicated
  "Bulk updates" block quotes the removed false claim and explains why Laravel behaves that way.
- **§11 field lists** — IMMUTABLE-AFTER-PUBLISH and LIFECYCLE-MUTABLE are now exhaustive by schema
  name, including the derived facts frozen with them (a revision's `cms_media_references` rows) and
  the new `edit_version`.
- **§13 immutability row** — rewritten to state what the schema does and does not enforce, and to
  point at `Revision::query()->update(...)` succeeding at engine level.
- **§29** — UNIT wording corrected; R4 replaced with **R4a** (no prohibited call site exists, by
  source scan), **R4b** (no supported service sequence changes a published payload), **R4c**
  (lifecycle transitions write only their whitelisted columns), **R4d** (replacement uses a new
  revision), **R4e** (a planted fixture call site *is* reported — the control that stops R4a passing
  by matching nothing). **§28** gained the corresponding threat row; **§32 AC-27** added.

### IMP005-REAUDIT-R1-04 — Media archive rule, lock order, draft concurrency

The two contradicting statements were both live in normative text (§19's archive gate versus test
M2), and the attachment flow genuinely omitted the tier-3 lock, so a draft edit could commit payload
and reference rows out of step with a concurrent edit of the same revision.

- **§19 "Media ARCHIVE"** — one rule: **logical archive is not blocked by references.** Archive ends
  attachability and never breaks rendering; existing references stay resolvable; purge remains
  blocked by any ACTIVE reference. The rejected alternative (block archive on references) is recorded
  with why it looked safer and was worse: it forces operators to either rewrite published content as
  cleanup churn or leave dead assets presenting as live.
- **§19 "What a reference status means"** (new) — reference *existence* is the preservation claim and
  *status* is a payload fact only. A reference of a PUBLISHED or SUPERSEDED revision is ACTIVE
  forever; supersession, retirement and archive never release one; the enumeration is exactly
  {ACTIVE, RELEASED}. This is what makes "zero ACTIVE references" imply "no preserved history needs
  it", which in turn let the case-B clause that restated it be removed rather than kept as a
  second rule.
- **§19 MEDIA STATE EFFECT TABLE** (new) — attachability / renderability / archive permission /
  purge eligibility across ACTIVE, ARCHIVED, PURGED. `PURGE_PENDING` explicitly rejected, with the
  reason (`purge_attempts`/`last_purge_error` already encode retry state better than a status).
- **§13** — `cms_media_assets.status_rules` summarises and defers to that table instead of
  duplicating it; `cms_media_references.status` narrowed to ACTIVE|RELEASED (ARCHIVED there was a
  category error); **`archived_by_principal_id` column added**, because `content.media.purged` was
  required to name the archiving Human and had no column to read it from — a NON_CRITICAL audit
  event cannot be the source of another audit event's assertion.
- **§19 attachment protocol** — rewritten to eight steps that *are* the tier order, adding explicit
  **tier-2 and tier-3 locks**, an under-lock owner re-verification, and a media-free draft edit still
  taking tier 3 and the version check (the concurrency control concerns the revision, not the media).
- **§19 draft concurrency** — optimistic `edit_version` compare-and-swap, with the pessimistic
  alternative rejected on the grounds that an edit session spans human think-time and the
  specification prohibits long-held row locks elsewhere. Payload, reference reconcile and version
  bump are one transaction, which is precisely what stops last-write-wins desynchronising references
  from payload.
- **§19 deletion/purge under lock**, **§26 flows 3 and 4**, **§18 MediaService** (purge moved off it
  to MediaCleanupService, since mixing them is how archive and purge get confused again) —
  reconciled.
- **§29** — M1/M3 rewritten to the single rule; **M9–M16** added (historical-only archive allowed,
  historical-only purge rejected, archived-still-renders-and-cannot-be-re-attached, two concurrent
  draft edits on MySQL 8, payload/reference/version atomicity, archive-vs-edit lock order, attach-vs-
  purge, reference-status-is-not-a-lifecycle). Matrix rows split archive from purge and added the
  draft-edit and rename rows; **AC-23** extended and **AC-28** added.

### IMP005-REAUDIT-R1-05 — Audit metadata contract

The ambiguity was real: §12's registry shape said required-everything-and-optional-nothing while its
allow-lists carried conditional and nullable keys.

Before writing any type, this pass verified the contract against the **locked IMP-004
implementation** (`AuditEventDefinition`, `AuditWriter::assertValueShape()`,
`HARD_PROHIBITED_METADATA_KEYS`, `METADATA_MAX_BYTES`, `canonicalMetadataJson`) rather than against
IMP-004's prose, because the prose does not constrain what the code accepts. Three findings shaped
the result:

1. The metadata type vocabulary is closed at `'int' | 'string' | 'array'` (flat scalar list only).
   **No bool, no float, no nested array, no object.** So every `(0/1)` in the Pass-1 inventory
   became an explicit int, and every timestamp (`publish_at`, `scheduled_at`) must be pre-serialized
   to an ISO-8601 UTC string — a Carbon value would be an object and would *raise at write time*.
2. `AuditWriter` **skips** type validation when a value is null, so `null` is a validation gap
   rather than a supported shape. Combined with IMP-004's canonical-JSON idempotency comparison,
   `{"k":null}` and `{}` are different payloads, so inconsistently-emitted nulls could manufacture
   `AuditIdempotencyConflictException` on a replay that should have matched. Absence is therefore
   **omission**, uniformly, and the reasoning is recorded in the spec rather than asserted.
3. The registry inventory is pinned by an existing IMP-004 test's counts. Adding 20 `content.*`
   events will require that assertion to be updated during implementation. This is disclosed in §12
   as a forward-looking consequence and **no test was modified here**.

- **§12** — the event table and its metadata column are separated; a **per-event metadata contract**
  table now covers all 20 events with REQUIRED / OPTIONAL / CONDITIONAL / types / omission trigger,
  plus blocks for the type vocabulary, the absence rule, and a content-specific prohibited-key list
  (bodies, HTML, bytes, filesystem paths, financial identifiers, titles-by-value). Derived keys are
  defined (`path_change`, `redirect_created`, `homepage_designation`, `outcome`, per-kind
  `fields_changed` vocabularies) so the table is executable without invention. The registry-entry
  shape is corrected to per-event required/conditional/optional/prohibited/nullability axes.
- **Expired-window semantics** (new §12 table) — one canonical event per scheduled operation: an
  executed transition emits its own `published`/`unpublished` with schedule provenance and **no**
  `schedule_expired` companion; a consumed window with no transition emits `schedule_expired` with an
  `outcome`; a stale re-run emits nothing. `retired` (0/1) is removed as the key that could only
  express the duplicate-event case. Both rejected extremes are recorded with reasons.
- **Exactly-one-event rule** — wording pinned to "one canonical business-mutation event per committed
  mutation", explicitly *not* one audit record of any kind, since IMP-004's denial evidence and the
  `audit_write_failed` log report legitimately coexist. §29's A-series is written against the
  content.* count, not a whole-table count, because the naive assertion tests the wrong property.
- **§10** — the scheduler paragraph that produced two events for one mutation is replaced by a
  pointer to the §12 table.
- **Audit composition preserved as required**: path claim/rename/redirect remain *inside* the parent
  publication event, now carrying `previous_path` → `path`, `path_change` and `redirect_created` so
  the parent record is self-describing.
- **Criticality untouched** — all 20 events remain NON_CRITICAL with per-row justification, no
  content event inflated, Q26/Q27/Q28 unchanged, and `security.authorization.denied` still the
  unchanged cover for unauthorized attempts.
- **§29** — **A4b/A4c** (first publish omits; same-path replacement omits previous_path only),
  **A6–A13** (required-key presence per event, conditional pairing, global no-null post-condition,
  manual-publish-carries-no-schedule-keys, cancel omissions, event-count determinism, type
  conformance including no-bool/no-Carbon, flat-list rule, size bounds, prohibited-value scan that
  IMP-004's key-name-only check cannot perform). **AC-29** added.

---

## Cross-Cutting Reconciliation (remediation §43–§46)

- **Transaction flows** — §26 rewritten where needed (flows 1, 2, 3, 4, 5) and re-checked against
  §11/§13/§14/§19 so each canonical transaction has one definition; the scheduled flow now states
  its event selection rather than duplicating §12.
- **Concurrency matrix** — extended from 9 to 20 data rows, adding rename-under-unique-constraint,
  rename-vs-claim, rename-vs-rename, rename-vs-release, the four revision-mismatch classes,
  paths/media reference mismatch, scheduled-publish-with-mismatched-revision, two concurrent draft
  edits, draft-edit-vs-publish, and archive-vs-purge separated into their own rows. Every row names
  its deciding lock/constraint and its deterministic loser behavior.
- **Test strategy** — §29 updated for all five findings; MySQL-8 marking revised (P8/P9, O9,
  M12/M14/M15 added; R4 removed from the both-engines list; O-series split between service-layer and
  engine-refusal variants), plus a **SQLite `PRAGMA foreign_keys` parity assertion** so the new
  composite-FK tests cannot be vacuously green on the default test engine.
- **Database architecture** — §13's relational ownership recheck table is the §46 deliverable: no
  pointer is semantically unconstrained, and each mechanism is labelled engine-enforced or
  transaction-enforced with its test.

---

## Passed Areas — Not Regressed

Read before patching and re-checked after: authority consistency; the CMS domain boundary; the Theme
boundary (no presentation added, IMP-006 still owns rendering); the Campaign boundary; the
Partner/Fundraiser boundary; Article/News as classification; the lifecycle table and its edges
(untouched — only the *namespace* consequences of retire/archive changed); permissions and their four
planes; the canonical authorization formula; the System Principal model (consumed, not extended);
the `content.*` audit namespace; audit criticality (not inflated); Q26; Q27; Q28; media upload
security; sanitization and the media placeholder contract; SEO minimalism; single locale; the search
and API boundaries; shared-hosting compatibility (no new infrastructure of any kind was introduced —
the two added columns and four added candidate keys are ordinary MySQL/SQLite DDL); admin/theme
isolation; authoring/publish separation; SVG rejection; editor independence; the homepage-as-
designation contract; the reserved-route derivation; the deletion-vocabulary separation.

Nothing in this pass changed a locked decision, added mandatory infrastructure, touched financial
domains, or invented a business rule, rate, approval limit, accounting entry or legal retention
duration. The two cleanup grace-period config values remain operational bounds and remain **not**
Human Decisions.

---

## Deliberately Not Done

- No finding marked RESOLVED; all five are PATCHED / PENDING CODEX RE-AUDIT.
- No implementation code, migration, model, service, policy, controller, route, Vue component,
  seeder or test written or modified.
- No IMP-006 work begun; the content payload contract §24 is unchanged.
- No Human Decision reopened, none raised.
- No merge, no push.
- Q29–Q33 and the §12 criticality classifications left exactly as they were.

## Known Residual Limits (stated, not hidden)

- Payload immutability remains application-enforced; a direct SQL client write is bounded by
  deployment grants, not by this specification. Pass 2 removed the claim that model events cover
  bulk updates and replaced it with a boundary that is real; it did not create a defense that
  Laravel cannot provide.
- Choosing "no automatic path reuse in v1" trades a growing set of reserved-but-unresolvable paths
  for the absence of a namespace-hijacking class. That is a considered cost, recorded in §13.
- The composite-FK approach adds four trivially-unique candidate keys to the revisions table purely
  as constraint targets. The storage cost is negligible at CMS scale; the alternative (Option B
  alone) was rejected because it degrades to whatever the most recent caller remembers to check.

---

## Gate Status After This Pass

```text
IMP-005 specification:   PATCHED — PENDING CODEX RE-AUDIT
Path rename executable:  YES (as specified, pending P8/P9 proof on MySQL 8)
Path reservation policy: UNIFIED (one rule, §13/§14/§27)
Revision ownership:      STRUCTURALLY ENFORCED (composite FKs + O-series)
Revision immutability:   CLAIM CORRECTED (write boundary + field whitelists + source scan)
Media archive/purge:     ONE RULE EACH (archive unrestricted, purge needs zero ACTIVE refs)
Lock order:              COMPLETE (tier 3 present in attachment; purge documented tier-less)
Concurrent draft editing: DEFINED (edit_version compare-and-swap, atomic with references)
Audit metadata:          PER-EVENT CONTRACT FOR ALL 20 EVENTS, null-free
Transaction model:       READY (for re-audit)
Concurrency model:       READY (for re-audit)
Test strategy:           READY (for re-audit)
Database architecture:   READY (for re-audit)
Open Human Decisions:    0
Implementation:          NOT AUTHORIZED
Merge:                   NOT AUTHORIZED
Push:                    NOT AUTHORIZED
```

"READY" here means "complete enough to be audited", and is this pass's own opinion of its own work.
It is not a resolution: Codex re-audit is the gate, and implementation remains unauthorized until
that audit is clean **and** the Human Authority separately authorizes it.
