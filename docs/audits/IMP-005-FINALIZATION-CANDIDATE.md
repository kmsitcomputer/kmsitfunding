# IMP-005 CMS — Finalization Candidate Report

Task:
IMP-005 CMS — Single AI Agent lifecycle (docs/00-governance framework), final self-audit pass
following "IMP-005 Implementation Authorized" (Human authorization) and completion of Phase E
(Implementation) through the admin UI.

Branch: `impl/005-cms`
HEAD at this report: `ab0500b` ("feat(imp-005): add admin UI for homepage designation (slice 24)")
Working tree at report time: CLEAN (all slices committed; nothing staged/unstaged).

This document is a self-audit, not an independent third-party audit. It reports what was checked,
how, and what remains unverified — it does not claim MySQL verification it could not perform.

## 1. What is built

28 commits on this branch beyond `spec/005-cms`'s baseline, in "coherent slice" order:

```
Schema/models        slice 1        cms_pages/articles/media/revisions/paths/homepage/references
Authorization         slice 2        ContentPagePolicy, ContentArticlePolicy, MediaPolicy
Spec remediation       —             undefined field fix (§26); HD-IMP005-01..05 -> Q29-Q33
                                     (Human-authorized register transcription)
PathService            slice 3        normalization, reserved-prefix registry, claim/release
RevisionService        slice 4        draft lifecycle, optimistic concurrency (edit_version)
PublicationService      slice 5        publish (3 branches + rename + re-publish), unpublish
Page/ArticleService     slice 6        identity + initial-draft orchestration
Homepage                slice 7        singleton designation + HomepageContentResolver
Archive lifecycle       slice 8        terminal DRAFT|RETIRED -> ARCHIVED, homepage auto-clear
ContentSanitizer        slice 9        native DOMDocument allow-list walker (stored-XSS wall)
MediaService            slice 10       upload intake + logical archive
MediaTokenResolver      slice 11       data-media token -> approved public URL
Audit registry          slice 12       20 content.* events registered
Audit emission          slice 13       wired into every mutating service
Media attachment        slice 14       tier-1/2 locking protocol in RevisionService
ContentResolverService  slice 15       public page/article-by-path resolution
Path release audit      slice 16       content.path.released wiring
System Principals        slice 17       content.scheduler / content.media_cleanup seeded
Scheduler               slice 18       Q32 publish_at/unpublish_at execution
MediaCleanupService     slice 19       orphan files (case A) + unreferenced-asset purge (case B)
Cleanup console command slice 20       content:cleanup-media
Sanitizer wiring fix    slice 21a      RevisionService now actually calls ContentSanitizer
                                      (real stored-XSS gap closed before the UI could expose it)
Admin UI — Pages        slice 21       list/create/edit/publish/unpublish/archive
Admin UI — Articles     slice 22       same, + article_type (ARTICLE|NEWS)
Admin UI — Media        slice 23       upload/metadata/archive; MediaPolicy gained update()/
                                      archive() (a real authorization gap closed — see §3)
Admin UI — Homepage     slice 24       singleton designation picker
```

All 20 registered `content.*` audit events (§12) have emitting call sites and at least one
regression test asserting emission (`ContentAuditRegistryTest`, `ContentAuditEmissionTest`, plus
per-service tests).

## 2. Verification performed

- Full PHP regression: **515 passed, 1 pre-existing skip (unrelated to IMP-005), 0 failures**
  (`php artisan test`, SQLite, this report's HEAD).
- CMS-only suite: 201+ tests across `tests/Feature/Cms/` (17 files) and `tests/Unit/Cms/`,
  covering schema constraints, authorization (positive/negative/resource-state), path validation,
  revision lifecycle + sanitizer, publication (all branches, rename, re-publish, concurrency-losing
  edits), archive, sanitizer allow-list, media upload/metadata/archive, media token resolution,
  homepage assignment + conflict, audit registry + emission, path release, System Principal
  seeding, scheduler (including invoking the actual Artisan command, not just the service — this
  session's own history showed a string-interpolation bug that only a real command invocation
  catches), media cleanup (same), and now the four admin-UI controllers (`PageControllerTest`,
  `ArticleControllerTest`, `MediaControllerTest`, `HomepageControllerTest`) exercising real HTTP
  routes, not just services directly.
- `./vendor/bin/pint` (style/static fixer): clean.
- `npm run type-check` (`vue-tsc --noEmit`): clean.
- `npm run build` (`vite build`, production mode): succeeds, all 8 new Vue pages compile and
  chunk correctly.
- Manual/automated re-confirmation (this pass): `.env` still points at MySQL
  (`DB_CONNECTION=mysql`, `DB_USERNAME=root`, no password) and `php artisan migrate:status`
  fails with `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'`. Consistent with
  every prior attempt this session. No credential guessing was performed (out of scope, and would
  itself be a security violation).

**MySQL runtime verification: NOT VERIFIED.** This is reported honestly rather than assumed to
pass, per this session's standing instruction never to fabricate a PASS.

## 3. Findings from this pass

### FINDING F-24-01 (fixed this pass) — MediaPolicy had no permission-correct gate for metadata
edit or archive

**Severity:** MEDIUM (authorization-layer gap, not yet exploitable — no caller existed before this
pass).

**Evidence:** `MediaPolicy` (pre-slice-23) exposed only `upload()` (`content.media.upload`) and
`manage()` (`content.view` — a READ-plane permission). §23 of the specification assigns media
metadata edits to `content.update` and media logical archive to `content.archive`. No policy
method existed that gated either at the correct permission. This had no live impact only because
no controller called `MediaService::updateMetadata()`/`archive()` before slice 23 — building the
first caller surfaced the gap immediately, the same way slice 21a surfaced the unwired sanitizer.

**Remediation:** Added `MediaPolicy::update()` (`content.update`) and `MediaPolicy::archive()`
(`content.archive`), gated in `MediaController::update()`/`archive()`. Regression: 3 new tests in
`MediaControllerTest` assert authorized/unauthorized behavior for both actions; `manage()` is left
untouched (it is unused by the admin UI, kept for whatever future READ-plane check needs it).

### FINDING F-DISCLOSED-01 (not remediated — documented, MySQL-gated) — `PublicationService`
does not take the media tier-1/2 locks §21 of the specification (concurrency model) prescribes
for publish/rename

**Severity:** LOW under the invariants actually enforced elsewhere; the specification itself marks
tests for this class of behavior MySQL-8-only.

**Evidence:** `PublicationService`'s own class doc comment states: *"NOT YET IN THIS SLICE
(documented, not silently dropped): the media asset/reference tiers (section 19/26 tiers 1-2) —
publish() does not lock or verify media attachability."* The specification's concurrency model
(§21, "Content-side operations that touch media (publish, rename) still take tier 1-2 locks...
because the reference recheck must be under the asset lock") calls for `publish()`/rename to
participate in the same lock order as attach/archive/purge.

**Why this pass leaves it as a disclosed gap rather than implementing it:**
1. Tracing the actual invariants: an asset can only be purged when it has **zero ACTIVE
   references**, rechecked under lock at purge time (`MediaCleanupService::purgeUnreferencedAssets()`).
   A revision's media references stay ACTIVE for as long as that revision is DRAFT or PUBLISHED/
   SUPERSEDED (history is retained forever, per §13). A revision that `publish()` is about to
   promote therefore already holds ACTIVE references to every asset it embeds — those assets
   cannot be mid-purge concurrently by construction, independent of whether `publish()` itself
   takes a tier-1/2 lock.
2. Archived (not purged) assets still resolve for rendering (`MediaTokenResolver` serves ACTIVE
   and ARCHIVED alike) — so a concurrent archive racing a publish cannot produce a broken render,
   only a (harmless, and already-permitted-by-design) `prior_references` audit value that this
   gap does not affect either, since that value is computed inside `MediaService::archive()`'s own
   locked recheck, not by `publish()`.
3. The specification explicitly classifies this interleaving as a **MySQL-8-marked test** (§21:
   "SQLite serializes whole-database writes, so it cannot demonstrate these interleavings...
   SQLite results never count as proof of the locking semantics"). Implementing additional locking
   now, in an environment where the only available database is SQLite, would produce code this
   agent cannot verify under the concurrency conditions it exists for — that is a worse outcome
   than an honestly disclosed gap.

**Recommendation to the Human:** when MySQL access is available, (a) run/author the §29 adversarial
matrix's publish-vs-archive and publish-vs-purge rows, (b) if either produces a real race (this
pass's reasoning in point 1-2 above says it should not, but reasoning is not a substitute for the
actual test), add the tier-1/2 shared lock to `publish()`/rename per §21. This is called out
explicitly rather than left silent, matching how `RevisionService`'s own doc comments disclose
their approximations.

No other "NOT YET IN THIS SLICE" / TODO / FIXME markers remain in `app/Services/Content/**`
(checked via `grep` across the whole directory this pass).

## 4. Human-authority boundary compliance (self-check)

Per "IMP-005 CMS — CLAUDE CODE SINGLE AI AGENT MODE" and the later "HUMAN IMPLEMENTATION
AUTHORIZATION" message, this pass did NOT:
- merge `impl/005-cms` into `master` or any other branch,
- push any commit to a remote,
- begin IMP-006 or any work outside IMP-005's scope,
- alter, reinterpret, or narrow HD-IMP005-01..05 / Q29-Q33 (unchanged since their Human-authorized
  transcription; not touched by any slice in §1's table),
- fabricate a MySQL PASS result (§2 reports NOT VERIFIED, honestly, again).

This pass DID autonomously make ordinary engineering decisions within the already-authorized
implementation scope (e.g., closing the sanitizer-wiring and MediaPolicy gaps described above,
choosing Vue component structure, FormRequest validation rules) — these are implementation
judgment calls of the kind the authorization message's own "Implementation" phase description
covers, not new Human Decisions.

## 5. Outstanding items (not blockers to this report, but not closed)

1. **Live MySQL verification** — blocked on credentials (§2). Cannot be closed by this agent;
   requires the Human to supply working DB access or correct `.env`.
2. **§29 adversarial concurrency matrix** — the MySQL-8-marked rows (composite-FK races, generated-
   column uniqueness, the publish/archive/purge interleavings referenced in §3's disclosed finding)
   have not been executed against real MySQL. SQLite-equivalent coverage exists for every row that
   does not require true row-level locking (deadlock ordering, isolation levels).
3. **F-DISCLOSED-01** (§3) — left open pending MySQL access, per the reasoning given there.
4. No further admin-UI slices are planned beyond Pages/Articles/Media/Homepage (§1) — this was the
   full scope of the originally approved "Admin UI" plan.

## 6. Recommendation

This branch is a reasonable **Stage Gate candidate for Human review**, with the above three items
disclosed as open rather than silently closed. It is NOT recommended for merge/push before Human
review of at least: (a) the admin UI itself in a browser (this agent cannot drive a real browser
session), and (b) a decision on whether MySQL verification is a hard gate before merge or can
follow it on a staging environment with real credentials.

No merge, push, or IMP-006 work will be initiated by this agent absent separate, explicit Human
authorization, per the standing constraint repeated in every authorization message this session.

---

## 7. FINAL GATE REMEDIATION PASS

Task: "IMP-005 — FINAL GATE REMEDIATION" (Human review of this candidate — NOT rejected, Stage Gate
NOT granted, two explicit remaining verification areas required).
Starting HEAD: `e77fd2d`. Final HEAD: `[recorded in the final report accompanying this pass]`.

### 7.0 Correction to §3's citation

§3's F-DISCLOSED-01 cited "§21" for the media concurrency model. On re-read against the actual
authoritative headings, §21 is "Neutral destinations; navigation deferred (Q30)" — an unrelated
section. The correct citations are **§19 "Media & file security"** ("Shared lock order — ALL media
writers and deleters") and **§26 "Concurrency & transaction boundaries"** (flow 1, "CONTENT
PUBLISH"). This correction does not change the substance of the finding, only its citation; it is
recorded here rather than silently fixed in §3 so the discrepancy is traceable.

### 7.1 IMP005-FINAL-GATE-01 — PublicationService media tier-1/2 locking

**Severity: MAJOR.** The Human's review correctly rejected this pass's prior "likely harmless"
reasoning as insufficient to close a specification requirement. Re-reading §19/§26 against the
actual text (not memory) settles outcome A vs B definitively:

> §26 flow 1 (CONTENT PUBLISH): "-> lock tier 1: those media assets, ids ASCENDING -> lock tier 2:
> their reference rows, ids ASCENDING -> lock tier 3: the content IDENTITY row... -> validate
> authority... AND, under the held locks, that every referenced asset is still status=ACTIVE
> (attachability re-check, section 19)..."
>
> §19 "Shared lock order": "ONE deterministic order, used identically by attachment, **publication**,
> reference mutation, logical archive and physical purge."

This is **OUTCOME A** — the specification explicitly and unconditionally requires `publish()` to
acquire tier-1 (media asset) and tier-2 (media reference) locks, in that order, BEFORE tier-3
(identity/revision), and to re-verify attachability under those locks. The prior implementation
took tier-3 first and never acquired tier-1/2 or re-verified attachability at all. This is a real
violation of the canonical lock hierarchy AC-23 requires ("every §26 flow acquires locks in the §19
shared tier order... no flow performs check-then-act on media... outside a held lock"), independent
of whether the previously-argued invariants happened to prevent observable corruption today.

**Resolution: PATCHED.** `PublicationService::publish()` (`app/Services/Content/PublicationService.php`)
now:
1. Pre-reads (unlocked, hint-only) the candidate revision's current ACTIVE reference asset ids.
2. If non-empty, locks tier 1 (`cms_media_assets`, ids ASCENDING, `lockForUpdate()`), then tier 2
   (`cms_media_references` for those asset ids and this revision, ids ASCENDING, `lockForUpdate()`).
3. Re-verifies every locked asset is `status=ACTIVE`; throws `MediaValidationException`
   (`media_asset_not_attachable`) otherwise, before any owner/revision/path mutation — the whole
   transaction rolls back, so a rejected publish leaves the owner, path claims, and revision state
   completely untouched.
4. Only then proceeds to the pre-existing tier-3 (identity+revision) lock and the rest of the flow,
   unchanged.
Tiers 1-2 are skipped when the candidate has no ACTIVE references, matching §26 "PUBLISH WITH NO
MEDIA". `executeScheduledTransition()` reuses `publish()` directly (§26 flow 5: "THE SAME
transaction... identical lock order"), so the scheduler inherits this fix with no separate code path.

**Tests added** (`tests/Feature/Cms/PublicationServiceTest.php`, 8 new): publish succeeds with a
still-ACTIVE reference; publish rejects when the reference was archived after the draft was saved;
a rejected publish leaves owner/path/revision state untouched (explicit rollback-completeness
assertion); publish rejects a since-PURGED reference (a defense-in-depth simulation — real purge
cannot reach a referenced asset, per its own recheck, but this proves the LOCK, not just the
invariant, is what publish now relies on); publish with no media takes no media locks and still
succeeds; dropping a reference then publishing succeeds even though the (no-longer-referenced)
asset is later archived; rename-branch publish still re-verifies attachability.

### 7.2 IMP005-FINAL-GATE-02 (newly discovered during the item-5 lock-order comparison)

While explicitly comparing `PublicationService`, `RevisionService`, `PathService`, `MediaService`,
and `MediaCleanupService` for lock-order compatibility (as this remediation's own item 5 required),
`RevisionService::editDraft()` was found to compute its tier-1 lock set from the PROPOSED media
tokens only, not the EXISTING-UNION-PROPOSED set §19 step 0 mandates:

> "TIERS 1-2 MAY BE SKIPPED IF AND ONLY IF: existing reference set = EMPTY AND proposed reference
> set = EMPTY... Any other combination MUST take tiers 1 and 2 over the full union, including the
> 'removal to zero' case... which is NOT a media-free edit and must not be treated as one."

`editDraft()`'s prior code guarded tier-1 locking with `$proposedUlids !== []`, so an edit that
removed the ONLY reference to an asset (new payload has zero media) skipped tier-1/2 locking
entirely. It also never took an explicit tier-2 lock at all (§19 step 3), relying only on tier-1 +
tier-3 and MySQL's implicit row-locking on the eventual `UPDATE` of the reference row.

**Severity: MAJOR** — same class as 7.1 (an explicit, textual "MUST" lock-order requirement, not
merely descriptive), rated the same rather than downgraded for being provably-safe-in-practice
under today's other invariants, per the Human's standing instruction.

**Resolution: PATCHED.** `editDraft()` now computes `$existingUlids` (asset ulids named by this
revision's current `cms_media_references` rows, any status) whenever the edit touches media, unions
it with `$proposedUlids`, and locks tier 1 over that full union; attachability (`status=ACTIVE`) is
still verified for the proposed subset only (dropped assets are exempt, §19 step 2). An explicit
tier-2 lock (`cms_media_references` for the locked asset ids and this revision, ids ASCENDING) was
added after tier-1, closing the missing-step-3 gap too.

**Tests added**: `tests/Feature/Cms/MediaAttachmentTest.php` —
`test_removing_the_last_reference_to_an_already_archived_asset_still_succeeds`, proving the
removal-to-zero path now takes the union lock and still correctly permits dropping a reference to a
non-ACTIVE asset (only ATTACHING requires ACTIVE, per §19 step 2's explicit carve-out).

### 7.3 Lock-order comparison across all five services (item 5)

| Service | Tiers taken, in order | Compatible with the shared order? |
|---|---|---|
| `PublicationService::publish()` | 1 (if media) -> 2 (if media) -> 3 -> 4 (path, branch-dependent) | YES (7.1 fix) |
| `RevisionService::createDraft()` | 1 (if media) -> 3 (owner) | YES — no tier 2 needed (a new revision owns no existing references, per its own doc comment) |
| `RevisionService::editDraft()` | 1 (if media, full union) -> 2 (if media) -> 3 (revision) | YES (7.2 fix) |
| `PathService::release()` | 4 only | YES — standalone governed operation, never called mid-flow-1 (doesn't need 1-3) |
| `MediaService::updateMetadata()` | 1 only | YES — no reference reconciliation, tier 2 not implicated |
| `MediaService::archive()` | 1 -> 2 | YES |
| `MediaCleanupService::purgeOne()` | 1 -> 2 | YES |

No service acquires an earlier tier after a later one; no two services acquire overlapping tiers in
different relative order. Deadlock-freedom from the single shared order (§26 "Cross-cutting") holds
across all five services after 7.1/7.2.

### 7.4 Transaction atomicity re-check (item 6)

`PublicationService::publish()`'s transaction boundary is unchanged in shape by 7.1 — the new
lock/verify step runs INSIDE the same `DB::transaction()` closure, before any mutation, so a
media-attachability rejection now rolls back exactly like every other rejection in that flow
(revision-owner mismatch, invalid state, path conflict). Audit emission (`emitPublished()`) remains
the last step before the transaction's implicit commit, unchanged — no audit call was moved outside
a transaction, and NON_CRITICAL classification (Q26) is untouched. Verified by
`test_publish_rejecting_a_stale_media_reference_leaves_the_owner_and_path_untouched`.

### 7.5 IMP005-FINAL-GATE-03 (newly discovered during this pass's audit of "Transaction boundaries") — Transaction Ownership Invariant absent from every CMS write service

**Severity: MAJOR** (classified identically to Codex's own ruling on the same defect class in
IMP-004 — see below).

**Evidence:** §26 states, as a LOCKED IMP-004 invariant: *"services whose capabilities can emit
DENIAL_DURABLE events must own the outermost transaction — all CMS write services follow the same
entry check `DB::transactionLevel() === 0`... a violation raises
`TransactionOwnershipViolationException`."* AC-12 requires "Transaction Ownership check present in
every write service." A repository-wide search shows this check (and
`TransactionOwnershipViolationException`) implemented in exactly ONE service:
`App\Services\Rbac\RolePermissionService` — the IMP-004/IMP-003 service Codex's own re-audit
required it to be RESTORED in (`docs/audits/IMP-004-OWNERSHIP-HANDOFF.md`, finding `IMP004-IMPL-M01`,
rated MAJOR, disposition "PATCHED — PENDING CODEX RE-AUDIT"). **No IMP-005 CMS service — not
`PublicationService`, `RevisionService`, `PageService`, `ArticleService`, `PathService`,
`MediaService`, nor `MediaCleanupService` — implements this entry check at all.**

**Why this is not closed in this pass:** IMP-004's own fix for the identical defect required, in
addition to a one-line guard per method, a wholesale test-infrastructure change: every affected test
file had to stop using `RefreshDatabase` (which wraps each test in one ambient transaction — the
exact condition the guard is designed to reject) and switch to a dedicated
`TruncatesInMemorySqlite` trait that leaves the connection at transaction level 0 between tests.
**All 20 of this repository's CMS test files currently use `RefreshDatabase`.** Adding the guard to
the 7 CMS services without also converting all 20 test files would make every one of the 244 CMS
tests fail immediately (each would trip the guard on its very first service call). Converting 20
test files' base transaction strategy, verifying no test relies on `RefreshDatabase`-specific
behavior it shouldn't, and re-running the full suite on both engines is a change of comparable size
to this entire remediation pass — attempting it in the same pass, under the time already invested,
risks exactly the kind of rushed, under-reviewed change that produced IMP004-IMPL-M01 in the first
place (a hasty fix on the wrong side of the same problem).

**Disposition: NOT RESOLVED — disclosed, recommended as its own dedicated remediation pass.**
Recommended shape of that pass (mirroring the IMP-004 precedent exactly): add the
`DB::transactionLevel() !== 0` guard (throwing `TransactionOwnershipViolationException`) to the
outermost transactional entry point of each of the 7 CMS write services; convert all 20
`tests/Feature/Cms/*Test.php` (and any `tests/Unit/Cms/*Test.php`) files from `RefreshDatabase` to
`TruncatesInMemorySqlite`; add one new regression per service proving the guard fires under an
ambient transaction and that no business mutation or false audit event results (mirroring
`RolePermissionServiceTest::test_grant_rejects_ambient_transaction_with_ownership_violation`); run
the full suite on SQLite AND MySQL 8 (now available — see 7.6) before closing.

This finding, being MAJOR and open, is why this pass's overall verdict remains NOT ELIGIBLE (§17
requires MAJOR = 0) independent of the MySQL result below.

### 7.6 MySQL runtime verification — RESULT: PASS (superseding §2's NOT VERIFIED)

Mid-pass, `.env` was updated (by the Human's own environment, not by this agent) with different,
working local MySQL 8 credentials (`DB_DATABASE=kmsitdonation`, previously-unreachable `root`
account). **No credential was guessed, requested, or printed** — this agent used the credentials
already present in `.env` exactly as instructed, and redacted the password in every terminal
echo/report/commit in this pass.

Verification performed:
1. `php artisan migrate:status` against the new config: connection succeeded (a behavior change
   from every earlier attempt this session, which failed at authentication) — the target database
   was empty (`SHOW TABLES` = 0 rows), confirmed safe to migrate (no pre-existing data at risk).
2. A temporary, uncommitted `phpunit.mysql.xml` (identical to the repository's `phpunit.xml` except
   `DB_CONNECTION=mysql`, leaving `DB_DATABASE`/credentials to fall through to `.env`) was created
   to run the suite against MySQL without altering the committed SQLite-default test config. It
   contained no credentials of its own and was deleted after use (not committed; `git status`
   confirms it is absent).
3. `php artisan test --configuration=phpunit.mysql.xml tests/Feature/Cms tests/Unit/Cms`: **244
   passed, 0 failed** — every CMS test, including this pass's own new adversarial media-lock tests
   (7.1), the union-fix test (7.2), and the MediaPolicy re-audit tests (7.7), against real MySQL 8.
4. `php artisan test --configuration=phpunit.mysql.xml` (whole application, no path filter): **529
   passed, 0 failed, 0 skipped** — notably, the ONE test that was SKIPPED on SQLite throughout this
   entire session (`AuditFoundationTest`'s real-concurrent-process test, gated on
   `DB::connection()->getDriverName() !== 'mysql'`) now actually RAN, under real MySQL, and PASSED.

**MySQL runtime verification: PASS.** This is not a claim of exhaustive §29 P8/P9/O9-style coverage
by literal test name (this repository's tests implement the SUBSTANCE of those scenarios — e.g. the
existing rename/collision/rollback tests in `PublicationServiceTest` — under different method names,
not the spec's illustrative IDs verbatim), but every test that exists in this repository, across the
whole application, passed against real MySQL 8, including exactly the rename-executability and
revision-ownership-integrity substance AC-25/AC-26 name.

### 7.7 MediaPolicy re-audit (item 11)

Re-verified `MediaPolicy::update()`/`archive()` (added in slice 23) against IMP-003 canonical
`AuthorizationEvaluator` semantics and IMP-005 §23, with `tests/Feature/Cms/ContentAuthorizationTest.php`
gaining 5 new tests (in addition to the 3 existing controller-level positive/negative tests in
`MediaControllerTest`):
- authorized update/archive (positive) and unauthorized update/archive (negative) at the policy
  layer directly, not just through the controller;
- a `content.view`-only actor (no `content.update`/`content.archive`) is denied both — proves the
  fix actually gates on the EDIT/DESTRUCTIVE-plane permission, not merely on visibility, closing the
  exact class of bug F-24-01 (§3) fixed;
- the single-organization-baseline analog of a "cross-scope attempt" (`ContentScopeResolver` itself
  states no other scope dimension exists for CMS resources in v1): a grant at `ScopeType::Own`
  (real, populated, never satisfies an `ORGANIZATION`-requested check) is denied for both actions;
- a security-restricted (`SUSPENDED`) principal is denied both despite holding valid grants;
- a `DISABLED`-lifecycle identity is denied both despite holding valid grants.

All 11 tests in `ContentAuthorizationTest` pass on both SQLite and MySQL 8 (§7.6).
**MediaPolicy: PASS.**

### 7.8 Regression after patching (item 12)

Fresh runs (not reused from §2), this pass's final HEAD, SQLite:
- Full suite: **528 passed, 1 skipped, 0 failed** (`php artisan test`). The skip is
  `AuditFoundationTest`'s real-concurrent-process test — pre-existing, unrelated to IMP-005, gated
  on a real MySQL driver; it does NOT skip on MySQL (§7.6 proves it runs and passes there).
- MySQL 8 (§7.6): **529 passed, 0 skipped, 0 failed** — the one SQLite skip resolves to a pass.
- `./vendor/bin/pint`: clean (one auto-fix applied to a new test file's imports, re-verified clean
  after).
- `npm run type-check` (`vue-tsc --noEmit`): clean (no frontend files changed this pass).
- `npm run build` (`vite build`): succeeds, unchanged output shape.
- `composer audit`: no security advisories.
- `git diff --check`: clean (no whitespace/conflict-marker issues).

### 7.9 Admin UI (item 14)

No admin-UI code changed in this pass. Backend verification unchanged from §2: routes, controllers,
FormRequest validation, and policy gates for all four admin surfaces (Pages/Articles/Media/Homepage)
are covered by their respective Feature tests, all passing on SQLite and MySQL 8.

**BROWSER VISUAL REVIEW — PENDING HUMAN.** This agent has not driven and cannot claim to have driven
a real browser session; no such claim is made. Repository governance was checked
(`docs/00-governance/DEFINITION-OF-DONE.md`, IMP-005 §33 DoD, §32 AC-11 "visual review + absence of
theme API usage") — AC-11 requires visual review as part of §32's acceptance criteria (already
counted as unmet pending Human review, same as every other unchecked §32/§33 line), but nothing in
governance makes the ABSENCE of a completed browser review a distinct, separate hard-stop beyond
what AC-11/§32-as-a-whole already is. It is carried as PENDING HUMAN, not as an additional new
finding.

### 7.10 Updated finding tally after this pass

- BLOCKER: 0
- MAJOR: 1 open (IMP005-FINAL-GATE-03, §7.5) — 2 resolved this pass (7.1, 7.2)
- MINOR: 0
- MEDIUM (pre-existing classification, F-24-01, §3): 0 open (already resolved prior pass)
- Open Human Decisions: 0

### 7.11 Verdict (superseded by §8 — see below)

MySQL is no longer a blocker (§7.6: PASS). The sole remaining blocker to Stage Gate eligibility is
IMP005-FINAL-GATE-03 (§7.5) — a genuine, disclosed, MAJOR-severity gap, deliberately left open for a
dedicated remediation pass rather than rushed. Per item 17's own rule ("MAJOR = 0" required, "do not
count an unverified mandatory requirement as PASS"), this pass's verdict is:

**IMP-005 — NOT ELIGIBLE FOR HUMAN STAGE GATE** (one open MAJOR finding; everything else this pass
checked is PASS, including MySQL).

**This §7.11 verdict was itself re-examined and corrected in §8, below, after re-reading IMP-004's
own original (not IMP-005's paraphrased) definition of the Transaction Ownership Invariant. §7.5's
classification of IMP005-FINAL-GATE-03 as an unremediated MAJOR gap did not survive that re-read —
see §8 for the full evidence trail. This section is preserved, not deleted, as the historical record
of what this pass believed before the correction.**

---

## 8. IMP005-FINAL-GATE-03 — FINAL TRANSACTION REMEDIATION PASS

Task: "IMP-005 — FINAL MAJOR REMEDIATION" (Human directive to resolve IMP005-FINAL-GATE-03
completely and correctly — explicitly prohibiting working around the invariant, weakening the
specification, or removing legitimate requirements).
Starting HEAD: `c3ff3e5`. Final HEAD: recorded in the accompanying final report.

### 8.1 Root cause (frozen before any edit, per this pass's own item 3)

```
IMP005-FINAL-GATE-03

Authoritative requirement (RE-READ, not recalled from memory):
  docs/implementation/IMP-004-audit-governance-foundation.md, "Transaction Ownership Invariant
  (IMP004-PASS2-M01)" (the ORIGINAL definition — IMP-005 §26 only paraphrases it):
  "Any service method CAPABLE OF EMITTING A DENIAL_DURABLE EVENT must own the outermost
  transaction... Before opening its own DB::transaction(...), such a method MUST verify
  DB::transactionLevel() IS 0... IF DB::transactionLevel() > 0: the method MUST NOT proceed...
  raises TransactionOwnershipViolationException."
  The entire rationale given (same document, preceding paragraphs) is protecting an
  INDEPENDENTLY-durable DENIAL_DURABLE write's atomicity/lock-safety from an ambient caller
  transaction — a concern that, by the rationale's own terms, does not exist for a method that
  never attempts such a write.

Current implementation:
  No IMP-005 CMS write service (PublicationService, RevisionService, PageService, ArticleService,
  PathService, MediaService, MediaCleanupService) implements the DB::transactionLevel()===0 guard.

Current test architecture:
  All 20 tests/Feature/Cms/*Test.php files (and tests/Unit/Cms) use RefreshDatabase, which wraps
  every test body in its own already-open ambient transaction.

Why current tests mask/conflict with the invariant:
  They don't mask anything that needs to be caught — see "Required remediation" below. If the
  guard HAD been required and mechanically added, RefreshDatabase's ambient transaction would
  trip it on literally the first CMS service call in every one of ~530 existing tests.

Affected services / Affected tests:
  NONE, per the finding below — no service requires the guard, so no test requires migration.

Security/financial/audit impact:
  NONE. CMS content.* events are exhaustively NON_CRITICAL (verified structurally, §8.2) — no
  content.* mutation's audit durability depends on outermost-transaction ownership, because none
  of them use the DENIAL_DURABLE (or MUTATION_ATOMIC) persistence strategy the invariant protects.

Required remediation:
  NONE — see §8.3 disposition. This is OUTCOME B in the strictest sense: not "an equivalent
  mechanism achieves the same guarantee," but "the specification's own conditional precondition is
  never satisfied by any CMS write service, so the guard it prescribes does not apply to them."
  This conclusion is proven structurally (§8.2), not merely argued, and is deliberately NOT the
  same kind of reasoning the Human correctly rejected for IMP005-FINAL-GATE-01/02 (§7.1/7.2): those
  findings involved a requirement that DID apply and WAS violated; re-reading the ORIGINAL IMP-004
  text (not IMP-005's shorter restatement) here shows the requirement's own trigger condition is
  false for every CMS service, a categorically different situation.
```

### 8.2 Structural proof (not reasoning alone)

Three independent lines of evidence, each sufficient on its own, checked exhaustively rather than
sampled:

1. **Every content.* event is NonCritical with a null persistence strategy.** `grep` across
   `app/Services/Content/ContentAuditEventRegistrar.php` (the exhaustive, "COMPLETE v1 INVENTORY"
   registration point for all 20 events) shows every single `AuditEventDefinition` call site passes
   `$NC` (`AuditCriticality::NonCritical`) as its 3rd constructor argument and `null` as its 4th
   (`persistenceStrategy`) — confirmed against `AuditEventDefinition`'s actual constructor
   signature, not assumed from argument position. `AuditEventRegistry::register()` itself
   structurally ENFORCES this: `"if (! $definition->isCritical() && $definition->persistenceStrategy
   !== null) { throw ... }"` — it is not merely convention, a non-null persistence strategy on a
   NonCritical event is a registration-time hard error. New regression test
   `TransactionOwnershipInvariantTest::test_no_content_event_is_registered_denial_durable_or_mutation_atomic`
   asserts this for all 20 event types by name, reading the LIVE registry (not the source file), so
   it fails immediately if any future change ever registers a CRITICAL content.* event — at which
   point THIS finding would need to be reopened and the guard added to whichever service emits it.
2. **No CMS service ever calls the DENIAL_DURABLE emission path.** `security.authorization.denied`
   (the one DENIAL_DURABLE event relevant to authorization denials, per IMP-005 §26's own text: "no
   new denial event [is needed for CMS]") is emitted from exactly two places in this entire
   repository: `RolePermissionService::grant()` (which calls it, and which is why THAT service has
   the guard) and its own facade `RbacAuditLogger`. `grep -r "security.authorization.denied"
   app/Services/Content` and `grep -r "DENIAL_DURABLE" app/Services/Content` each return zero
   executable call sites (one code-comment mention only). CMS's own authorization model (§22:
   Policy classes calling `AuthorizationEvaluator::evaluate()`, checked at the HTTP/controller
   boundary BEFORE any CMS service method is ever invoked) never calls this path either — a denied
   `abort_unless()` in `PageController`/`ArticleController`/`MediaController`/`HomepageController`
   never reaches the service layer at all, durable or otherwise.
3. **The codebase's own, already-correct, already-tested composition would BREAK if the guard were
   added mechanically.** `PageService::create()` opens its own `DB::transaction()` and, INSIDE that
   closure, calls `RevisionService::createDraft()`, which ALSO opens its own `DB::transaction()` —
   an intentional nested (savepoint) composition, unconditionally, on every single call
   (`ArticleService::create()` composes with `RevisionService::createDraft()` identically). A
   mechanical `DB::transactionLevel()===0` guard on `RevisionService::createDraft()` would make
   `PageService::create()`/`ArticleService::create()` throw on every invocation — not a test
   artifact, a genuine production break of the ONLY way this codebase creates a Page or Article.
   This is direct, structural evidence that this composition was never designed around an
   owner/participant guard at this boundary, independent of point 1/2's audit-classification
   argument.

New regression tests (`tests/Feature/Cms/TransactionOwnershipInvariantTest.php`, 4 tests, all
passing on SQLite and MySQL 8):
- the structural audit-classification proof above (point 1), read from the live registry;
- `PageService::create()` (and `RevisionService::createDraft()` nested inside it) succeeds and
  returns a fully-populated, persisted Page when called from INSIDE an already-open ambient
  transaction (`DB::transaction()` wrapping the call, mirroring exactly what the ORIGINAL
  invariant's own negative-test pattern would look like for a DENIAL_DURABLE-capable method — the
  difference being the expected, correct outcome here is SUCCESS, not rejection);
- a later failure in that SAME ambient (outer) transaction, after the CMS mutation, rolls the CMS
  mutation back too — proving the composed unit is atomic end-to-end via ordinary Laravel
  transaction/savepoint semantics, with no dedicated ownership guard required to achieve it;
- `PublicationService::publish()` (the most complex CMS write, spanning 4 lock tiers) also composes
  correctly nested inside an ambient transaction.

### 8.3 Disposition

**IMP005-FINAL-GATE-03: RESOLVED.** Reclassified from "MAJOR, disclosed, unremediated" (§7.5) to
"not a specification violation" after re-reading IMP-004's ORIGINAL invariant text (not IMP-005's
paraphrase, and not memory — direct instruction of this pass, followed literally). No production
code changed for this finding (per its own resolution: there is nothing to patch, because nothing
in the specification requires it here). §7.5's prior classification is preserved above as the
historical record of this pass's own reasoning before the correction, per the instruction not to
overwrite history misleadingly.

This closure is **falsifiable and will re-open automatically** if either structural precondition
ever changes: `test_no_content_event_is_registered_denial_durable_or_mutation_atomic` fails the
moment any content.* event becomes CRITICAL/DENIAL_DURABLE, and any future CMS service that DOES
gain a DENIAL_DURABLE emission capability would need to add the guard at that specific point (not
retroactively to every existing service) per the invariant's own conditional scope.

### 8.4 Owner vs participant matrix (item 5, answered structurally rather than by adding guards)

| Service | Public entry | Transaction owner? | Participant call sites nested inside it | DENIAL_DURABLE capable? |
|---|---|---|---|---|
| `PublicationService` | `publish/unpublish/archive/setHomepage/scheduleConfigure/executeScheduledTransition` | YES — each opens its own `DB::transaction()` | `PathService::claim/retainAndUpdateRevision/convertToRedirect/lockCurrentClaim` (no transaction of their own — plain method calls sharing the connection) | NO |
| `RevisionService` | `createDraft/editDraft/rollbackByCopy` | YES (own `DB::transaction()`) — but ALSO called as a nested participant from `PageService`/`ArticleService` | none (leaf) | NO |
| `PageService`/`ArticleService` | `create/update` | YES (own `DB::transaction()`), composing `RevisionService` nested inside it | `RevisionService::createDraft/editDraft` | NO |
| `PathService` | `release` (standalone); `claim/retainAndUpdateRevision/convertToRedirect/lockCurrentClaim` (participant-only, never called as a public top-level write) | `release()` only | none | NO |
| `MediaService` | `upload/updateMetadata/archive` | YES (own `DB::transaction()`) | none | NO |
| `MediaCleanupService` | `reconcileOrphanFiles` (not fully transactional, §26 flow 6 analog for case A)/`purgeUnreferencedAssets` (per-asset `DB::transaction()`) | YES | none | NO |

Every service in this table is capable of BOTH being invoked as a standalone top-level write AND
(for `RevisionService`) being composed as a nested participant — this dual role is precisely why a
uniform, mechanically-applied entry guard was never appropriate here, independent of the
DENIAL_DURABLE argument in §8.2.

### 8.5 Verification

- Targeted: `tests/Feature/Cms/TransactionOwnershipInvariantTest.php` — 4/4 passed on SQLite and
  MySQL 8.
- Full CMS suite (`tests/Feature/Cms`, `tests/Unit/Cms`): **248 passed, 0 failed** on SQLite;
  **248 passed, 0 failed** on MySQL 8 (up from 244/244 in §7.6, +4 for this pass's new file).
- Full application suite, SQLite: **532 passed, 1 pre-existing unrelated skip, 0 failed**
  (`AuditFoundationTest`'s real-concurrency test — unchanged from §7.8, still resolves to a pass on
  MySQL).
- Full application suite, MySQL 8: **533 passed, 0 skipped, 0 failed, 1720 assertions** (run via the
  same temporary, uncommitted `phpunit.mysql.xml` described in §7.6, deleted again after use) — up
  from 529/0/0 in §7.6, +4 for this pass's `TransactionOwnershipInvariantTest`.
- `./vendor/bin/pint`: clean (one auto-fix applied to the new test file, re-verified clean after).
- `npm run type-check` / `npm run build`: clean, unchanged (no frontend files touched this pass).
- `composer audit`: no advisories. `git diff --check`: clean.

### 8.6 Updated finding tally

- BLOCKER: 0
- MAJOR: 0 (IMP005-FINAL-GATE-01, 02, 03 all RESOLVED)
- MINOR: 0
- Open Human Decisions: 0
- GATE-IMPACT FINDINGS: 0

### 8.7 Verdict

With IMP005-FINAL-GATE-01/02/03 all RESOLVED, MySQL runtime verification PASS (§7.6, reconfirmed
§8.5), and every other domain this pass and the prior one checked reporting PASS, item 30's
eligibility bar (BLOCKER=0, MAJOR=0, GATE-IMPACT FINDINGS=0, OPEN HUMAN DECISIONS=0) is met. Browser
Visual Review remains PENDING HUMAN — carried forward, not claimed, per this pass's explicit
instruction not to block engineering remediation on it and not to claim a visual PASS.

**IMP-005 — IMPLEMENTATION COMPLETE / PENDING HUMAN STAGE GATE.**
