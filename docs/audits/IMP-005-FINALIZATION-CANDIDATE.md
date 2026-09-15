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
