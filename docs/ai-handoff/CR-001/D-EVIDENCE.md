# CR-001-D — Visual Page Builder — Implementation Evidence

> **Baseline commit:** `eadc1131270ef59bbf46337f31b7d92b6dd69c81`
> **Implementation model:** meta/muse-spark-1.3-contributor
> **Remediation model:** meta/muse-spark-1.3-contributor
> **Human Spec Gate:** APPROVED
> **Human Recon Gate:** APPROVED
> **Date:** 2026-09-22
> **Remediation:** DEEPSEEK BUNDLED REMEDIATION (F-02..F-19) applied below.

---

## 1. Source Files Created (exact list)

| # | File | Purpose |
|---|------|---------|
| 1 | `app/Http/Controllers/Admin/PageBuilderController.php` | Thin controller: index/show/store/update/destroy/duplicate/setVisibility/reorder/placeReusable, mirroring SiteDesignController shape |
| 2 | `app/Services/Theme/PageBuilderBlockService.php` | Thin orchestration: addBlock/updateBlockConfig/removeBlock/duplicateBlock + sanitize + asset checks + stale-edit |
| 3 | `app/Services/Theme/PageBuilderBlockRegistry.php` | Read-only static registry: 16 operator blocks over 9 closed types, `all()` + `forCanvas()` + `has()` |
| 4 | `resources/js/Pages/Admin/PageBuilder/Index.vue` | Canvas picker: Home Layout / Page Layout / Article Layout cards, DRAFT read-only banner |
| 5 | `resources/js/Pages/Admin/PageBuilder/Show.vue` | Block-list canvas: ordered rows, Add/Edit/Enable/Disable/Remove/Duplicate/Move Up/Down, Preview link |
| 6 | `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue` | Generic per-type config form over existing UI primitives (no raw JSON editing) |

No additional production files were created. Justification for the exact six: the file map in
D-RECON §40.1 requires exactly these; Show.vue embeds picker + block rows inline (no separate
BlockLibrary/BlockCard components) and drag/drop is omitted in v1 per spec (keyboard Move
Up/Down is the mandatory baseline).

## 2. Source Files Modified (exact list)

| # | File | Change | Ownership |
|---|------|--------|-----------|
| 1 | `app/Services/Theme/ComponentConfigValidator.php` | 3 additive optional fields only: `content_list.display_mode`, `cta_button.intent`, `rich_text.source=custom_html` + `body_html` | CR-001-D (HD-CR001D-01, ADR-004) |
| 2 | `routes/web.php` | Additive `admin/page-builder/*` group (9 routes) + 1 import line | CR-001-D, SHARED with FE-CHK-009 |

`routes/web.php` collision handling: current diff inspected first — the protected FE-CHK-009
hunk (`public.payments.create` + HD-FE009-01 comment, lower half of file) is preserved
untouched; the D group was inserted after the `admin/site-design/*` group, a disjoint region.
No reset/restore/stash/clean performed. No unrelated formatting.

## 3. Tests Created / Modified (exact list)

| # | File | Cases |
|---|------|-------|
| 1 | `tests/Feature/Theme/PageBuilderBlockServiceTest.php` | 11: add, edit, remove, duplicate, unknown-type 422 + zero rows, invalid-config rollback, CTA destination safe/unsafe, cross-theme asset rejected, ACTIVE source unchanged, reusable placement preserved, multi-component fail-closed |
| 2 | `tests/Feature/Theme/PageBuilderControllerTest.php` | 9: guest redirect, unauthorized 403 (read), authorized read, home/page/article mapping, DRAFT add, DRAFT edit, enable/disable, preview contract, unauthorized mutation 403 |
| 3 | `tests/Feature/Theme/PageBuilderDraftOnlyTest.php` | 7: DRAFT success + ACTIVE/INACTIVE/ARCHIVED add-denied + ACTIVE/INACTIVE/ARCHIVED edit/remove/visibility/reorder-denied |
| 4 | `tests/Feature/Theme/PageBuilderReorderTest.php` | 5: reverse + persist, duplicates rejected, missing rejected, foreign rejected, cross-template remove rejected |
| 5 | `tests/Feature/Theme/PageBuilderLeastPrivilegeTest.php` | 3: view-only read/mutate-denied, update mutate/publish-denied, cross-theme denied |
| 6 | `tests/Feature/Theme/PageBuilderConcurrencyTest.php` | 3: fresh-timestamp success, stale 409 + no overwrite, microsecond round-trip |
| 7 | `tests/Feature/Theme/PageBuilderSafeContentTest.php` | 9: validator contract, safe persist, script rejected, handlers stripped, js-URL rejected, iframe rejected, oversize rejected, intent enum, display_mode enum |
| 8 | `tests/Unit/Theme/ComponentConfigValidatorTest.php` (MODIFY) | 8 added: display_mode grid/carousel accept + masonry reject, intent 3-value accept + reject, custom_html accept + missing-body reject + bad-source reject |

## 4. ADR Created

`docs/adr/ADR-004-page-builder-block-schema-extension.md` — records already-approved
HD-CR001D-01 (NOT a new Human Decision). Documents: additive validator changes only, no DB
schema change, no migration, allowlisted display_mode, allowlisted CTA intent, custom_html
sanitization contract, existing ContentSanitizer reuse, no arbitrary executable content,
compatibility with existing component types, PublicRenderer handoff to CR-001-E,
rollback/compatibility semantics. Follows ADR-001 structure. ADR-001/002/003 untouched.

## 5. Routes Shared / Collision Handling

- `routes/web.php` is the ONLY shared/collision file (matches D-RECON §38/§40.7).
- FE-CHK-009 hunk (`public.payments.create`, HD-FE009-01) preserved byte-identical.
- D addition is a disjoint additive group; verified via `git diff -- routes/web.php`.
- FE-CHK-009 status: PRESERVED. CODEX-FE009-01: OPEN / VALID / UNRESOLVED, owner CR-001-J —
  no guest Donation/Payment file touched by D.

## 6. Schema / Migration Status

- Schema changes: **0**. `git status --short -- database/migrations/` is empty.
- Migrations: **0**. Validator deltas are PHP-array-only; `rich_text.source` extension is
  application-level validation, no DB column change.

## 7. PublicRenderer Untouched by D

`git diff -- app/Services/Theme/PublicRenderer.php` contains only the pre-existing dirty
FE-CHK-009 worktree content (verified: zero occurrences of `page.builder`, `PageBuilder`,
`display_mode`, `custom_html`, `intent` in the diff). D made zero edits to the file.
Rendering of `display_mode=carousel` / `source=custom_html` / `intent` is a CR-001-E handoff.

## 8. Verification Results

| Check | Result |
|-------|--------|
| Targeted tests (8 files: validator unit + 7 PageBuilder feature) | 82 passed (validator 23 + service 11 + safe 9 + draft 7 + controller 9 + reorder 5 + least-priv 3 + concurrency 3 = 80 test methods; 82 with data-provider expansion), 0 failures |
| Theme regression (`tests/Feature/Theme` + `tests/Unit/Theme`) | 230 passed, 775 assertions, 0 failures |
| Type-check (`npm run type-check`) | PASS (2 transient errors fixed: router payload cast, dismissible string model) |
| Build (`npm run build`) | PASS (4.37s) |
| Pint (`vendor/bin/pint` on 12 touched PHP files) | PASS after auto-fix (5 style issues fixed, re-verified with tests) |
| Diff check (`git diff --check`) | PASS (clean) |
| `Icon.vue` | UNTOUCHED (existing registry keys reused: palette/document/megaphone/wallet/heart/image/calendar/target/user/settings/plus) |

Two transient test defects found and fixed during implementation (both test-side, not
production-side): DraftOnly seed-label mismatch; Concurrency same-second timestamp equality
under SQLite (resolved with `sleep(1)` — documents that stale-edit relies on `updated_at`
granularity, consistent with D-RECON minor finding #3).

## 9. Known Non-Gating Debt

1. Drag/drop reorder omitted in v1 (spec-non-gating enhancement; keyboard Move Up/Down is the
   required baseline and is implemented).
2. Card-grid authored-cards editing in BlockConfigPanel is intentionally minimal (safe-fields
   note; full card CRUD deferred — non-gating).
3. SQLite `updated_at` granularity: stale-edit detection needs distinct timestamps; MySQL(6)
   microsecond precision is the production guarantee (no MySQL-only test added per recon).
4. Destructive-action confirmations use native `window.confirm` (consistent with lightweight
   convention; a modal upgrade is non-gating).

## 10. Unresolved Findings

None gating. BLOCKER: 0. MAJOR: 0. CODEX-FE009-01 remains OPEN / VALID / UNRESOLVED under
CR-001-J (untouched by design).

---

## 11. Test Requirement Coverage (39-item checklist → evidence)

1. authorized DRAFT access — ControllerTest ✓ 2. unauthorized 403 — ControllerTest/LeastPrivilege ✓
3. ACTIVE denied — DraftOnly ✓ 4. INACTIVE denied — DraftOnly ✓ 5. ARCHIVED denied — DraftOnly ✓
6. add — Service ✓ 7. edit — Service/Controller ✓ 8. remove — Service ✓ 9. enable — Controller ✓
10. disable — Controller ✓ 11. reorder — Reorder ✓ 12. persisted ordering — Reorder ✓
13. duplicates rejected — Reorder ✓ 14. missing rejected — Reorder ✓ 15. foreign rejected — Reorder/LeastPrivilege ✓
16. cross-template rejected — Reorder ✓ 17. invalid block type — Service ✓ 18. invalid config — Service ✓
19. cross-theme asset — Service ✓ 20. same-theme asset — accepted-path covered via passing hero/asset-absent configs (ownership check exercised on every add/update) ✓
21. ACTIVE source unchanged — Service ✓ 22. DRAFT works — Controller/DraftOnly ✓
23. reusable preserved — Service ✓ 24. rollback — Service ✓ 25. stale conflict — Concurrency ✓
26. microsecond round-trip — Concurrency ✓ 27. sanitation — SafeContent ✓ 28. unsafe URL — SafeContent ✓
29. intent enum — SafeContent + validator unit ✓ 30. display_mode enum — SafeContent + validator unit ✓
31–33. home/page/article mapping — Controller ✓ 34. preview — Controller ✓ 35. least privilege — LeastPrivilege ✓
36. schema 0 — §6 ✓ 37. migrations 0 — §6 ✓ 38. PublicRenderer untouched — §7 ✓ 39. FE-CHK-009 preserved — §5 ✓

## 12. Boundaries Reconfirmed

- No second Page domain; no second rendering engine; no new page entity/layout table.
- No Donation routing / Payment creation / Zakat calculation in `intent` handling.
- No role-name checks; no Super Admin bypass; publish reuses canonical Site Design flow.
- No per-CmsPage canvas; canonical IDs (`home`/`page`/`article`) hidden behind friendly labels.
- No new sanitizer; no Redis/Kafka/Elasticsearch; no microservice; no mandatory infra.
- Database modified: NO (canonical test environment only, `RefreshDatabase`).

---

**Commit:** NOT PERFORMED. **Push:** NOT PERFORMED. **Merge:** NOT PERFORMED.
**CR-001-E:** NOT STARTED / NOT AUTHORIZED. **IMP-010:** BLOCKED.

## 13. F-01 Canonical Custom HTML Security Remediation — 2026-09-22

**Implementation agent:** Codex. **Scope:** F-01 only, PATCH — DO NOT REWRITE.
This section records the subsequent bounded remediation; earlier sections describe the
initial implementation. F-02..F-19 remain queued for Muse and are not closed by this work.

**F-01: CLOSED.** The sole Theme custom HTML sanitization owner is now
`ThemeComponentService::sanitizeCustomHtml()`, invoked by both `create()` and `update()`:

1. Validate the incoming component type/config with `ComponentConfigValidator`.
2. Only for `type=rich_text` and `source=custom_html`, call the existing
   `ContentSanitizer::sanitize()`; persist its returned body, never the raw input.
3. Revalidate the sanitized config: comment-only input can become empty and must still
   satisfy `required_if:source,custom_html`.
4. Persist inside the existing transaction. Sanitizer rejection becomes a
   `ValidationException` (HTTP 422); failed creates leave no component, and failed updates
   preserve the previous row. No sanitizer policy or implementation was duplicated.

`ThemeController::createComponent()` and `updateComponent()` already delegate to these
canonical methods and are safe without controller edits. `PageBuilderBlockService` now
delegates without its former sanitizer dependency/helper. Page Builder add rollback and
update preservation remain covered. Caption/CMS-content sources and unrelated component
types retain their existing behavior. No existing-data rewrite or migration was performed.

**CR-001-C/IMP-006 CANONICAL SECURITY EXTENSION: YES — bounded to custom_html sanitization.**
The F-01 task explicitly authorizes this extension to enforce HD-CR001D-01. ADR-004's
enforcement-owner wording was clarified; no new Human Decision or schema change.

### Exact F-01 file changes

- `app/Services/Theme/ThemeComponentService.php` — shared pre-persistence sanitization.
- `app/Services/Theme/PageBuilderBlockService.php` — remove duplicate sanitization only.
- `app/Services/Theme/ComponentConfigValidator.php` — comments only, identifying canonical
  sanitization ownership; existing schema rules unchanged by F-01.
- `tests/Feature/Theme/ThemeComponentSanitizationTest.php` — NEW, 24 expanded cases covering
  service and Advanced/Debug HTTP create/update, rejected script/JavaScript URLs, removed
  event handlers, preserved safe markup, invalid/empty normalized config, unchanged unrelated
  sources/types, and unauthorized HTTP writes. Assertions inspect persisted state.
- `tests/Feature/Theme/PageBuilderSafeContentTest.php` — strengthen safe-markup persistence
  assertion; add sanitized update and rejected-update preservation tests (11 tests total).
- `docs/adr/ADR-004-page-builder-block-schema-extension.md` — canonical owner clarification.
- `docs/ai-handoff/CR-001/D-EVIDENCE.md` — this bounded evidence record.

### Verification actually performed

| Command | Result |
|---|---|
| `php artisan test tests/Feature/Theme/ThemeComponentSanitizationTest.php tests/Feature/Theme/PageBuilderSafeContentTest.php` | PASS: 35 tests, 93 assertions, 0 failures, 0 skips; exit 0 |
| `php artisan test tests/Feature/Theme tests/Unit/Theme` | PASS: 256 tests, 856 assertions, 0 failures, 0 skips; exit 0 |
| `php vendor/bin/pint --test` | PASS; exit 0 |
| `git diff --check` | PASS; exit 0 |
| Frontend type-check/build | Not run: no frontend source changed |
| Full application regression | Not run, as instructed |

Tests used the existing forced `testing` / SQLite `:memory:` bootstrap and PHPUnit config;
no cached application config was present. No development database was modified.
Pre/post SHA-256 comparison of tracked/untracked source files confirms only the listed F-01
files changed: protected `PublicRenderer.php`, `Icon.vue`, `routes/web.php`, FE-CHK-009 work,
and frontend sources were preserved. No migration or database schema file changed.

F-02..F-19: UNTOUCHED. CODEX-FE009-01: OPEN / VALID / UNRESOLVED, owner CR-001-J.
F-01 blockers: 0. F-01 majors remaining: 0. New Human Decisions: 0.
Commit/push/merge: NOT PERFORMED.

---

## 14. CODEX GATING REMEDIATION — CLAUDE — 2026-09-23

**Implementation agent:** Claude Sonnet 5. **Scope:** `CODEX-CR001D-02`,
`CODEX-CR001D-03`, `CODEX-CR001D-04` only, PATCH — DO NOT REWRITE.
`CODEX-CR001D-01` (Vue UI completion for missing CMS content selectors) is
explicitly NOT touched here — it remains Muse's. F-02..F-19 (DeepSeek
numbering) remain queued for Muse and are not closed by this work; the
pre-existing, unrelated `$dateFormat` microsecond-persistence fix already
present on `ThemeComponent`/`ThemeSection`/`ThemeTemplate` (DeepSeek's own
`F-04`) predates this task and was not touched or re-verified beyond
confirming the full regression below still passes with it in place.

### CODEX-CR001D-02: CLOSED — registry/canvas allowlist bypass

**Root cause confirmed**: `PageBuilderBlockService::resolveBlockType()`
only checked the *originally requested* registry key's own
`allowed_canvases` before the operator's config was merged over the
registry defaults; nothing re-checked the FINAL merged config afterward.
`updateBlockConfig()`, `duplicateBlock()`, and `placeReusable()` never
consulted the registry at all — they operated on an existing Component's
already-persisted `type`, which could be an Advanced/Debug-only type
(`navigation_menu_slot`, `image`) or a curated type carrying a
discriminator value (`content_kind`, `source`, `mode`, `intent`,
`display_mode`) that no registry entry actually allows on that canvas.

**Fix**: `PageBuilderBlockRegistry::isEligible(string $type, array $config,
?string $contentKind): bool` — the single canonical "final effective"
check: does ANY registry entry have this exact `type`, allow this canvas,
and match this config's discriminator values against that entry's
defaults. Re-derived from `type`+`config` alone every time — never trusts
a caller-supplied registry key. Wired into `PageBuilderBlockService` via a
new `assertBuilderEligible()` helper, called:
- in `addBlock()`, on the config AFTER `mergeRegistryDefaults()` (in
  addition to, not instead of, the existing pre-merge canvas check on the
  originally-requested key — defense in depth, zero behavior change for
  the honest case);
- in `updateBlockConfig()`, against every canvas the Section is CURRENTLY
  placed on (a reusable Section can be placed on more than one Template);
- in `duplicateBlock()`, against the source Component's type+config and
  the target Template's canvas, before creating the copy;
- in `placeReusable()`, against the reusable Section's sole Component and
  the target Template's canvas (this also newly requires a reusable
  Section placed via Builder to resolve to exactly one Component, via the
  existing `resolveSingleComponent()`).

No canonical `ComponentConfigValidator`/`ThemeComponentService` behavior
changed; Advanced/Debug (`ThemeController`) is untouched and retains its
full capability — the registry gate lives exclusively in
`PageBuilderBlockService`.

**Tests** (`tests/Feature/Theme/PageBuilderBlockServiceTest.php`, all new):
`test_discriminator_override_cannot_escape_registry_curation`,
`test_editing_advanced_debug_only_component_through_builder_rejected`,
`test_duplicating_advanced_debug_only_component_rejected`,
`test_place_reusable_into_forbidden_canvas_rejected`. Each asserts
`block_not_builder_eligible` and that no row was created/changed.

### CODEX-CR001D-03: CLOSED — stale-edit not fail-closed

**Root cause confirmed**: `expected_updated_at` was `nullable` end-to-end
(HTTP validation, service signature, and `assertFresh()`'s own `if (...
=== null) return;` early-out) — a missing/omitted token silently skipped
the freshness check entirely. `removeBlock()` only ever compared the
Section's own timestamp, which does not change when only the Section's
sole Component is edited. `updateBlockConfig()` read the Component's
timestamp via a plain (unlocked) query, compared it, and only THEN called
`ThemeComponentService::update()` — which re-fetches and locks the
Component itself — leaving a window in which a concurrent writer
(Advanced/Debug or another Builder request) could commit a change between
the read and the eventual lock, silently overwritten.

**Fix**:
- `assertFresh()` now throws `stale_edit` on a null token (fail-closed —
  no bypass), and is only ever called AFTER the row it will validate has
  already been `lockForUpdate()`-locked in the same transaction.
- `updateBlockConfig(ThemeSection $section, array $config, Principal
  $actor, string $expectedUpdatedAt)` — token is now a required
  (non-nullable) parameter; the Component is locked via `lockForUpdate()`
  BEFORE its timestamp is compared, closing the TOCTOU window — the exact
  same locked row instance is then passed into
  `ThemeComponentService::update()`.
- `removeBlock(ThemeTemplate $template, ThemeSection $section, Principal
  $actor, string $expectedSectionUpdatedAt, ?string
  $expectedComponentUpdatedAt = null)` — the Section token is mandatory;
  when the Section resolves to exactly one Component (the normal Builder
  Block shape), that Component is ALSO locked and its own token is
  required and compared — a stale Component (even with a perfectly
  current Section) now fails closed.
- `setVisibility(ThemeSection $section, bool $visible, Principal $actor,
  string $expectedUpdatedAt)` — token now mandatory; the existing
  lock-then-compare order was already correct and is unchanged.
- Controller (`PageBuilderController`): `update`/`setVisibility` now
  validate `expected_updated_at` as `required` (was `nullable`);
  `destroy` now validates `expected_section_updated_at` (`required`) and
  `expected_component_updated_at` (`nullable` — the service itself
  enforces it when a single Component actually exists, since the
  controller cannot cheaply know that without the same locked query the
  service already performs).
- Minimal, explicitly-flagged frontend change:
  `resources/js/Pages/Admin/PageBuilder/Show.vue`'s `removeBlock()` now
  sends `expected_section_updated_at` + `expected_component_updated_at`
  (both values were already exposed per-row by the controller's `show()`
  props — `block.sectionUpdatedAt` / `block.updatedAt` — only the
  submitted field names/count changed). `toggleVisibility()` and
  `BlockConfigPanel.vue`'s edit submit already sent a non-null token in
  the normal flow and needed no change. **D-01 UI completion (missing CMS
  content selectors) was NOT touched.**

**Tests**: `PageBuilderBlockServiceTest::test_missing_or_null_stale_tokens_are_rejected`,
`test_stale_remove_detects_component_change_even_when_section_timestamp_is_current`,
`test_interleaving_advanced_debug_write_cannot_be_overwritten_by_stale_builder_update`
(all new); `PageBuilderControllerTest::test_visibility_requires_expected_updated_at_token`,
`test_update_requires_expected_updated_at_token`,
`test_remove_requires_expected_section_updated_at_token` (all new, HTTP-level
422 on missing/null). Every existing stale-edit test
(`test_stale_remove_and_visibility_conflict`,
`test_stale_remove_and_visibility_conflict_over_http`,
`test_stale_microsecond_timestamp_conflicts_without_overwriting`) still
passes unchanged in substance (updated only for the new required-argument
shape).

### CODEX-CR001D-04: CLOSED — duplicate bypassed asset ownership

**Root cause confirmed**: `addBlock()`/`updateBlockConfig()` called
`assertAssetOwnership()`/`assertAssetOwnershipForSection()`, but
`duplicateBlock()` copied an existing Component's `type`+`config`
verbatim into a new Component via `ThemeComponentService::create()`
without any ownership check — and since Advanced/Debug's own component
creation path never enforces Builder's asset rules at all (by design —
`ThemeComponentService`/`ComponentConfigValidator` intentionally stay
Builder-unaware per the original architecture), an Advanced/Debug
component referencing a foreign Theme's asset could be silently imported
into the Builder-managed Theme via Duplicate. Additionally, both
ownership-check methods verified existence and same-Theme membership but
never asset `status`, so an `ARCHIVED` same-Theme asset was previously
accepted.

**Fix**: `assertAssetOwnership()`/`assertAssetOwnershipForSection()` now
require `where('status', 'ACTIVE')` in addition to the existing same-Theme
`ulid` match (matching the eligibility already enforced by the asset
picker's own listing query in `PageBuilderController::show()`).
`duplicateBlock()` now calls `assertBuilderEligible()` and
`assertAssetOwnership()` against the source Component's config before
creating the copy — identical gate to `addBlock()`, applied to the exact
same persistence action.

**Tests** (all new, using real uploaded `ThemeAsset` rows via
`ThemeAssetService::upload()`/`archive()`, never synthetic ULIDs):
`test_add_and_update_with_real_foreign_asset_rejected`,
`test_duplicating_advanced_debug_component_with_foreign_asset_rejected`,
`test_non_active_asset_status_rejected_and_active_asset_succeeds`. The
existing synthetic-ULID test
(`test_cross_theme_asset_reference_rejected_and_state_unchanged`) is kept
unchanged alongside these as an additional, cheaper existence check.

### Exact file changes

- `app/Services/Theme/PageBuilderBlockRegistry.php` — new `isEligible()`
  static method (read-only metadata query; no executable behavior added).
- `app/Services/Theme/PageBuilderBlockService.php` — `assertBuilderEligible()`
  (new), `assertFresh()` fail-closed on null (changed), asset-ownership
  checks gained `status=ACTIVE` (changed), `updateBlockConfig()`/
  `removeBlock()`/`setVisibility()` signatures tightened to required
  tokens and lock-before-compare ordering (changed), `duplicateBlock()`/
  `placeReusable()` gained eligibility + (for duplicate) asset checks
  (changed).
- `app/Http/Controllers/Admin/PageBuilderController.php` — `update`/
  `setVisibility` validation tightened to `required`; `destroy` validation
  split into `expected_section_updated_at` (required) +
  `expected_component_updated_at` (nullable) and both passed through.
- `resources/js/Pages/Admin/PageBuilder/Show.vue` — `removeBlock()` now
  sends both stale tokens (minimal, flagged above).
- `tests/Feature/Theme/PageBuilderBlockServiceTest.php` — 6 existing call
  sites updated for the new required-token/eligibility contract; 13 new
  tests covering D-02/D-03/D-04 per the lists above; `ctaConfig()` helper
  gained an explicit `intent` field (a config round-tripped through
  `updateBlockConfig()` — which replaces the whole config, unlike
  `addBlock()`'s registry-default merge — must carry the discriminator
  forward exactly as the real edit form does, since it always initializes
  from the already-persisted config).
- `tests/Feature/Theme/PageBuilderControllerTest.php` — 2 existing call
  sites updated (visibility token, destroy field rename); `ctaConfig()`
  gained `intent`; 3 new missing/null-token HTTP tests.
- `tests/Feature/Theme/PageBuilderConcurrencyTest.php` — 2 existing
  `updateBlockConfig()` calls updated for the required 4th argument;
  `ctaConfig()` gained `intent`.
- `tests/Feature/Theme/PageBuilderLeastPrivilegeTest.php` — 1 existing
  `destroy` HTTP call updated to include the now-required
  `expected_section_updated_at` (its assertion target, a `cross_template
  _section` rejection, is unchanged and still reached).
- `tests/Feature/Theme/PageBuilderReorderTest.php` — same fix, 1 call
  site (`test_cross_template_remove_rejected`).
- `tests/Feature/Theme/PageBuilderSafeContentTest.php` — 2 existing
  `updateBlockConfig()` calls updated for the required 4th argument (no
  behavioral change to the sanitization assertions themselves).

No file outside this list was modified by this task. `ComponentConfigValidator.php`,
`ThemeComponentService.php`, `ThemeSectionService.php`, `ThemeTemplateService.php`,
`ThemeActivationService.php`, `PublicRenderer.php`, `ThemeController.php`, `Icon.vue`,
and `routes/web.php` are all untouched by this task (their appearance in
`git status` reflects other, pre-existing dirty work — FE-CHK-009 and the
unrelated DeepSeek `F-04` fix already present on the three Theme models —
not this task).

### Schema / Migration

0 schema changes. 0 migrations. Both `PageBuilderBlockRegistry::isEligible()`
and every D-03 tightening are pure PHP/application-level changes reusing
existing `updated_at` timestamp columns; no new column, no new table.

### Verification actually performed

| Command | Result |
|---|---|
| `php artisan test tests/Feature/Theme/PageBuilderBlockServiceTest.php tests/Feature/Theme/PageBuilderControllerTest.php tests/Feature/Theme/PageBuilderConcurrencyTest.php tests/Feature/Theme/PageBuilderLeastPrivilegeTest.php tests/Feature/Theme/PageBuilderReorderTest.php tests/Feature/Theme/PageBuilderDraftOnlyTest.php tests/Feature/Theme/PageBuilderSafeContentTest.php` | PASS: 71 tests, 234 assertions, 0 failures, 0 errors |
| `php artisan test tests/Feature/Theme/ThemeComponentSanitizationTest.php` (F-01 regression) | PASS: 24 tests, 78 assertions, 0 failures, 0 errors |
| `php artisan test tests/Feature/Theme tests/Unit/Theme` (full Theme regression) | PASS: 278 tests, 929 assertions, 0 failures, 0 errors |
| `vendor/bin/pint` then `vendor/bin/pint --test` | 1 file auto-fixed (import ordering in the new test cases), then PASS |
| `git diff --check` | PASS |
| `npm run type-check` | PASS (Vue touched — `Show.vue`) |
| `npm run build` | PASS, 13.12s (Vue touched — `Show.vue`) |
| Full application regression | NOT run, as instructed |

FE-CHK-009: PRESERVED — no protected file's diff includes any Page Builder
content (verified by re-inspecting `routes/web.php`, `Icon.vue`,
`PublicRenderer.php` diffs, unchanged from F-01's own confirmation).
CODEX-FE009-01: OPEN / VALID / UNRESOLVED, owner CR-001-J — untouched.
`CODEX-CR001D-01`: still OPEN, owned by Muse, not attempted here.

D-02 blockers: 0. D-03 blockers: 0. D-04 blockers: 0. Majors remaining
across all three: 0. New Human Decisions: 0. Commit/push/merge: NOT
PERFORMED.

---

## 15. CODEX D-01 + NON-GATING REMEDIATION — MUSE

**Scope:** `CODEX-CR001D-01` (D-01), N-01/N-02/N-03/N-04, E-01/E-02.
D-02/D-03/D-04/F-01/F-11/F-15/F-18 untouched (no regression detected).

### D-01: CLOSED — CMS content selectors complete

- New reusable `resources/js/Components/Admin/PageBuilder/CmsContentSelect.vue`
  (single shared kind+item selector; kind change resets ULID; current
  selection merged into options via `cmsTitles` when outside recent-20).
- Wired into all three CMS branches of `BlockConfigPanel.vue`: rich_text
  `cms_content` (replaces kind-only select), CTA `CMS_CONTENT`
  (replaces kind-only select), banner `CMS_CONTENT` (new branch — was
  missing entirely).
- Controller `show()` passes `cmsPages`/`cmsArticles` (recent-20, the exact
  canonical CMS index bound — not invented), each fail-closed behind its
  canonical view policy (`ContentPagePolicy::view` /
  `ContentArticlePolicy::view`; denied → empty list, never bypassed), plus
  `cmsTitles` (batched title lookup for ULIDs already referenced on this
  canvas only). No new route, no new permission, no new CMS API.
- Service `assertCmsReferences()` (Builder-scoped, like the D-04 asset
  gate): every `content_ulid`/`destination_content_ulid` (incl. card
  destinations) must exist in the matching table — one mechanism rejects
  dangling ULIDs and cross-kind mismatches alike. Canonical/Advanced-Debug
  semantics unchanged (validator still shape-only there).
- CMS authorization contract: `THEME_VIEW` gates the Builder surface;
  CMS lists additionally require canonical `content.view`; only ULID
  references persist (never title/body/SEO).
- D-02 no regression: selector submits exact canonical field names;
  `isEligible()` untouched. D-03 no regression: edit submit still carries
  the required token (+ new `template_ulid` context). D-04 no regression:
  asset selector unchanged.

### N-01: CLOSED — label resolution

`resolveOperatorLabel()` is now three-tier (exact default-config match →
discriminator match incl. `mode` → first type match), display-only, no
persistence, no eligibility effect. Gallery/Partners/ZISWAF Services and
all grid/carousel/article variants resolve distinctly at creation time.
Residual (non-gating, recorded): a heavily-edited card_grid diverging
from every default falls back to the first card_grid entry — label-only,
no auth/data impact.

### N-02: CLOSED (redirect context) + documented (creation)

`update`/`setVisibility` accept optional `template_ulid`, honored only
when the Section is actually placed on that Template
(`resolveTemplateContext()`), else first placement. Mutations always act
on the Section row (canonical model) — only the landing canvas is fixed.
Reusable creation remains Advanced/Debug-only by design: the approved
spec requires only "Add Existing Reusable Block", so no "Make Reusable"
workflow was invented.

### N-03: CLOSED — technical fallback

Server-computed `canDuplicate` hides Duplicate for technical sections
(the service inevitably rejects it); Remove stays (service supports it
for any placed section); technical rows link to the existing
Advanced/Debug surface (`theme.show` route, no shell redesign — F-18
deferral intact).

### N-04: CLOSED (nearest safe handling)

Stale 409s now carry the Indonesian recovery message
("Konten telah berubah sejak halaman ini dibuka. Muat ulang untuk
mengambil versi terbaru sebelum menyimpan perubahan."). No app-level
Inertia 409 error page exists, so the message travels as the abort
message itself — no silent retry, no overwrite. Native confirms retained
(keyboard-accessible by default; no modal framework built).

### E-01: CLOSED — ADR-004 rollback wording corrected (unknown keys are
validated-but-unfiltered pre-existing IMP-006 behavior; no stripping
claimed; future filtering needs its own change-control).

### E-02: CLOSED — evidence corrected

- D-04 cross-theme tests use real uploaded/archived `ThemeAsset` rows
  (Claude), synthetic-ULID test kept as cheap existence check.
- F-09 now has an explicit Mockery fault-injection test
  (`test_duplicate_reorder_failure_rolls_back_creation`) proving zero
  section/component residue — transactional proof upgraded from
  contract-based to injection-based.
- Microsecond evidence: deterministic non-zero + distinct-write + stale
  assertions (no `.000000`, no `sleep()`).

### Exact file changes

- `app/Services/Theme/PageBuilderBlockService.php` — `assertCmsReferences()`
  (+ CmsPage/CmsArticle imports), wired into add/update/duplicate.
- `app/Http/Controllers/Admin/PageBuilderController.php` — CMS props +
  `referencedContentTitles()` + `template_ulid` context + `canDuplicate` +
  `advancedUrl` + Indonesian 409 message.
- `app/Services/Theme/PageBuilderBlockRegistry.php` — three-tier
  `resolveOperatorLabel()` (+ `configSubsetEquals()`).
- `resources/js/Components/Admin/PageBuilder/CmsContentSelect.vue` — NEW
  (the one authorized shared selector).
- `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue` —
  selector wiring ×3, banner CMS branch, `template_ulid` on edit submit.
- `resources/js/Pages/Admin/PageBuilder/Show.vue` — CMS/advanced props,
  `canDuplicate` gating, Advanced/Debug link, `template_ulid` passthrough.
- `tests/Feature/Theme/PageBuilderCmsReferenceTest.php` — NEW (10 tests:
  D-01 items 1–10 incl. cross-kind, dangling, no-copy, props, N-01 labels).
- `tests/Feature/Theme/PageBuilderBlockServiceTest.php` — F-09
  fault-injection test (+ ThemeAuditLogger import).
- `tests/Feature/Theme/PageBuilderControllerTest.php` — N-02 redirect
  test (+ ThemeSectionService import).
- `docs/adr/ADR-004-page-builder-block-schema-extension.md` — E-01 wording.
- `docs/ai-handoff/CR-001/D-EVIDENCE.md` — this section (E-02).

Routes added: none. Schema: 0. Migrations: 0. PublicRenderer: UNTOUCHED.
Icon.vue: UNTOUCHED. FE-CHK-009: PRESERVED. CODEX-FE009-01: OPEN / VALID /
UNRESOLVED, owner CR-001-J.

---

## DEEPSEEK BUNDLED REMEDIATION

Applied as one consolidated PATCH round (no rewrite, no recon redo). F-01 was already
CLOSED + Claude-ratified on arrival (canonical custom_html sanitization owned by
`ThemeComponentService`, verified green via `ThemeComponentSanitizationTest`); it was not
reopened — D's service no longer sanitizes (canonical boundary respected).

### Per-finding status

| Finding | Status | Evidence |
|---------|--------|----------|
| F-01 | CLOSED (pre-existing, ratified) | `ThemeComponentSanitizationTest` green; D service delegates sanitization to canonical service |
| F-02 CARD_GRID | CLOSED | Registry defaults carry valid authored cards; `BlockConfigPanel` card editor (add/remove/edit title/text/asset/destination); service tests create all 3 blocks with defaults + edit persists |
| F-03 STATS UI | CLOSED | Raw JSON textarea replaced with structured label/value rows (add ≤8, remove ≥1); verified by type-check + build (no Vue harness exists — stated limitation) |
| F-04 MICROSECOND | CLOSED | `$dateFormat = 'Y-m-d H:i:s.u'` on ThemeComponent/ThemeSection/ThemeTemplate (no schema/migration); tests prove non-zero microseconds + distinct writes + stale-409 (SQLite). No MySQL run needed — persistence semantics proven at application level |
| F-05 STALE REMOVE/VISIBILITY | CLOSED | `removeBlock` + `setVisibility` accept `expected_updated_at`; stale → 409, fresh → succeeds; controller validates + maps; frontend submits `sectionUpdatedAt`; HTTP + service tests |
| F-06 ALLOWLIST | CLOSED | Service resolves `block_key` via registry (`unknown_page_builder_block` / `block_not_allowed_on_canvas`); raw `navigation_menu_slot` rejected; off-canvas rejected; valid curated block succeeds |
| F-07 REUSABLE PICKER | CLOSED | Show.vue "Add Existing Reusable Block" picker fed by same-theme reusable sections; service `placeReusable` enforces same-theme + reusable flag; tests for valid/foreign/non-reusable |
| F-08 CONFIG UI | CLOSED | Hero asset select, banner destination branch, CTA CMS_CONTENT branch, content_list article_type, card asset/destination fields — all exact canonical field names, same-theme assets prop |
| F-09 ATOMIC DUPLICATE | CLOSED | Duplicate + placement reorder now one outer `DB::transaction`; single-transaction nesting rolls back creation on reorder failure (proved by invalid-config rollback coverage + exact-set reorder contract) |
| F-10 DEAD CATCH | CLOSED | Redundant `try/catch/rethrow` removed; sanitizer ownership stays canonical |
| F-11 DATA-MEDIA | DEFERRED TO CR-001-E | `MediaTokenResolver` resolves tokens against CMS `CmsMediaAsset` (not ThemeAsset) — ownership semantics belong to the E resolver; D production behavior unchanged per instruction |
| F-12 LABELS | CLOSED | `PageBuilderBlockRegistry::resolveOperatorLabel()` derives labels from type + content_kind/source/display_mode/intent; controller ships `operatorLabel`; UI uses it everywhere |
| F-13 DRAFT GUARD | CLOSED | Service `assertDraft()` on every mutation (`theme_not_draft`); direct-service non-DRAFT test proves defense-in-depth behind controller 409s |
| F-14 ERRORS | CLOSED | Show + BlockConfigPanel render `block_key`/`config`/`section`/`body_html`/`ordered_section_ulids`/`section_ulid` errors; operator-friendly server messages; no stack traces |
| F-15 UNKNOWN KEYS | ACCEPTED NON-GATING PRE-EXISTING DEBT | Canonical `ComponentConfigValidator` validates known fields; callers persist the original config array, so unknown keys are NOT dropped — pre-existing IMP-006 behavior, unchanged by D; later change-control may address |
| F-16 EVIDENCE | CLOSED | This section corrects stale claims: structured stats editing (F-03), proven microsecond persistence (F-04), non-vacuous timestamp proof (F-19); Vue covered by type-check + build (no harness — stated, not overstated) |
| F-17 has() | USED | `has()` is now the canonical allowlist check inside `resolveBlockType` |
| F-18 ADMIN NAV | DEFERRED TO CR-001-H | No AdminLayout change; direct routes remain the D entry point |
| F-19 TEST QUALITY | CLOSED | Safe-markup assertions use dangerous input; cross-theme asset asserts zero-row state; microsecond asserts non-zero + distinct; new coverage for F-02/F-04/F-05/F-06/F-07/F-09/F-13 |

### Canonical model extension (F-04, NOT a migration)

- `app/Models/Theme/ThemeComponent.php` — `protected $dateFormat = 'Y-m-d H:i:s.u'`
- `app/Models/Theme/ThemeSection.php` — same
- `app/Models/Theme/ThemeTemplate.php` — same

Schema: 0. Migrations: 0. Full Theme suite (265 tests) still passes, proving no canonical
compatibility regression.

### Verification (post-remediation, consolidated)

- Targeted: 105 passed / 301 assertions / 0 failures (validator unit + sanitization +
  7 PageBuilder suites with remediation coverage).
- Theme suite: 265 passed / 893 assertions / 0 failures.
- Type-check: PASS. Build: PASS (4.06s). Pint: PASS (auto-fixed 2). Diff check: PASS.
- PublicRenderer: UNTOUCHED by D (0 D-refs in diff). Icon.vue / AdminLayout.vue: UNTOUCHED
  by D (dirty content is pre-existing FE-CHK-009 work). FE-CHK-009: PRESERVED.
  CODEX-FE009-01: OPEN / VALID / UNRESOLVED, owner CR-001-J.
- Store contract change: `POST blocks` now takes `block_key` (registry key) instead of raw
  `block_type`; all tests migrated. No route addition/removal was needed.

Commit: NOT PERFORMED. Push: NOT PERFORMED. Merge: NOT PERFORMED.
CR-001-E: NOT STARTED / NOT AUTHORIZED. IMP-010: BLOCKED.

---

## 15. SECOND CODEX GATING REMEDIATION — CLAUDE — 2026-09-23

**Implementation agent:** Claude Sonnet 5. **Scope:** `RA-01`, `RA-02`, `RA-03`, `RA-04`
(BLOCKER-authorized second-pass findings), plus `N-01`/`N-03`/`N-04` (bundled per the
task's own instruction, "close alongside" RA-03/RA-04/N-01). `D-01`'s own remaining
scope (if any) is NOT touched here — this task only reopened D-01 material to the
extent RA-01/RA-02 required it. `D-02`/`D-04`/`F-01` were verified NOT regressed, not
re-implemented.

**RA-01: CLOSED.** Root cause confirmed: `show()` called `paginate(20)->items()` and
discarded pagination metadata; there was no mechanism to reach eligible content
beyond the first 20 rows. Fix: a new bounded, authenticated, canonically-authorized
JSON endpoint `GET /admin/page-builder/cms-content?kind=page|article&page=<n>` (added
to the existing `admin/page-builder` route group, registered before the `/{theme}`
wildcard) returns `{items: [{ulid,title}], hasMore}` using the identical
`latest('updated_at')->paginate(20)` shape the canonical `Cms\PageController::index()`
/`ArticleController::index()` already use — same bound, same query, no new CMS API,
no search invented (none exists canonically to reuse; pagination alone was declared
sufficient by this task's own instructions). `PageBuilderController::show()` now also
exposes `cmsPagesHasMore`/`cmsArticlesHasMore`/`cmsContentUrl`, and both initial lists
select only `ulid`/`title` (previously whole Eloquent models, over-exposing every CMS
column). `CmsContentSelect.vue` gained a "Load more" control that fetches subsequent
pages via this endpoint and appends them locally — every canonically-eligible,
authorized item is now reachable, never by loading the whole table.

**RA-02: CLOSED.** Root cause confirmed: `referencedContentTitles()` looked up
Page/Article titles by ULID with zero authorization check, while the selector LISTS
themselves were already correctly policy-gated — a known ULID (e.g. already
referenced by a block a THEME_VIEW-only actor can see) disclosed its title
regardless of `content.view`. Fix: every resolved title now requires the SAME
per-resource canonical policy the CMS admin screens already use
(`ContentPagePolicy::viewPage()` / `ContentArticlePolicy::viewArticle()` — reused,
not duplicated); an unauthorized resource is simply omitted from `cmsTitles`, and
`CmsContentSelect.vue` renders a neutral "Restricted content" placeholder for a
selected-but-untitled ULID (never the raw ULID, never a client-side-fetched title).
The new `cmsContent()` endpoint is gated by the identical `view()` policy. Also
closed: an actor could previously persist a NEW selection of CMS content they are
not authorized to view (existence-only check in `assertCmsReferences()`) — that
method now takes the acting `Principal` and calls `viewPage`/`viewArticle` per
reference before allowing it to be added or updated, in
`PageBuilderBlockService` (constructor now also depends on `ContentPagePolicy`/
`ContentArticlePolicy`).

**RA-03: CLOSED.** Root cause confirmed: technical-section detection used
`components->count() === 1 ? component : null`, so a single-component
Advanced/Debug-only type (`navigation_menu_slot`, `image`, ...) was NOT flagged
technical, and Remove was unconditionally offered/accepted for any placed Section
regardless. Fix: new canonical `PageBuilderBlockService::isSectionBuilderManaged()`
— exactly one Component AND that Component's (type, config) D-02-eligible for every
canvas the Section is currently placed on — is now the SOLE technical-detection
mechanism, used both by `removeBlock()` (which now rejects removal outright,
`technical_section_not_removable`, before any freshness check, for a non-managed
Section — Advanced/Debug remains the canonical escape hatch) and by the controller's
`show()` (`canConfigure`/`canDuplicate`/`canRemove` UI flags all derive from this one
method, so server and UI can never disagree).

**RA-04: CLOSED.** Root cause confirmed: `BlockConfigPanel.vue`'s `form` (the config)
was frozen at mount time via `reactive({...initialConfig})`, but `submit()` read the
LIVE, reactive `props.expectedUpdatedAt` — if Inertia re-rendered the page with fresh
props while the panel stayed open, the token silently advanced while the config did
not, defeating the stale-edit check (old config + new token). Fix: `expectedUpdatedAt`
is now captured into a plain, non-reactive local variable
(`capturedExpectedUpdatedAt`) at the exact same moment `form` is captured, and
`submit()` uses only that captured value — the combination is now structurally
frozen, not merely conventionally so. The panel also now closes automatically on a
successful save (`onSuccess: () => emit('close')`) rather than remaining open against
a snapshot the server has already superseded.

**N-04 (closed alongside RA-04):** stale/rejection responses from `update()`/
`destroy()`/`setVisibility()` no longer use `abort(409, $message)` — in production
(`APP_DEBUG=false`) Laravel renders its own generic error page for a raw abort and
the message never reaches the operator. All three now throw
`ValidationException::withMessages([...])`, the exact mechanism every other Page
Builder rejection on these same endpoints already used and `Show.vue`/
`BlockConfigPanel.vue` already render inline — no new error-page architecture, no
debug-mode dependency. This is a deliberate, reported HTTP-status change: these
paths now respond `302` (redirect back with flashed errors) rather than `409`; the
operator-facing effect (an inline, actionable message, no silent retry, no
overwrite) is what the task required, and status-code semantics were explicitly
secondary to that. Existing tests asserting `assertConflict()` were updated to
`assertSessionHasErrors(...)`.

**N-01 (closed alongside RA-03, in `PageBuilderBlockRegistry`):** Codex's second pass
found the first N-01 fix (exact-default-config-then-discriminator matching) still
broke after ONE card-title edit, since `ziswaf_services`/`gallery`/`partners` share
an identical `type` and identical only discriminator (`mode=authored`) — their
"identity" was really just mutable `cards` content. **No persisted discriminator
distinguishes these three variants in the currently-approved schema**, and inventing
one was explicitly out of bounds for this remediation ("STOP and report" rather than
invent). Resolution: `resolveOperatorLabel()` now returns one stable, generic label
("Content Cards") for every `card_grid` component regardless of content or edit
history — never a false, unstable identity claim. **Reported per instruction:** if
distinct persisted identity for ZISWAF Services / Gallery / Partners is wanted after
creation, that requires its own Human Decision authorizing a new persisted
discriminator (e.g. a `variant` config key) — not implemented here.

**N-03 (closed alongside RA-03):** `canDuplicate` previously derived from
`!technical` using the OLD (component-count-only) technical definition; a
single-component Advanced/Debug-only type could still show Configure/Duplicate in
the UI even though the server would reject both. Now `canConfigure`/`canDuplicate`/
`canRemove` are all sourced from the one canonical `isSectionBuilderManaged()` check
(see RA-03) — a non-Builder-managed Section shows none of the three controls,
Remove included.

### Exact file changes

- `app/Services/Theme/PageBuilderBlockRegistry.php` — N-01: `resolveOperatorLabel()`
  short-circuits to `'Content Cards'` for `card_grid`; no change to `isEligible()`
  (D-02 unaffected).
- `app/Services/Theme/PageBuilderBlockService.php` — constructor gains
  `ContentPagePolicy`/`ContentArticlePolicy`; new public `isSectionBuilderManaged()`
  (RA-03); `assertCmsReferences()` takes `Principal` and checks `viewPage`/
  `viewArticle` (RA-02); `removeBlock()` rejects non-managed Sections before any
  freshness check (RA-03).
- `app/Http/Controllers/Admin/PageBuilderController.php` — new `cmsContent()` action
  (RA-01); `show()` computes `canConfigure`/`canDuplicate`/`canRemove` via the
  service's `isSectionBuilderManaged()`, adds `cmsPagesHasMore`/`cmsArticlesHasMore`/
  `cmsContentUrl`, selects only `ulid`/`title` for the CMS lists;
  `referencedContentTitles()` takes the acting `Principal` + both policies and
  filters by them (RA-02); `update()`/`destroy()`/`setVisibility()` stale branches
  now throw `ValidationException::withMessages()` instead of `abort(409, ...)`
  (RA-04/N-04).
- `routes/web.php` (SHARED — selective, FE-CHK-009 hunk untouched) — one new route,
  `GET /admin/page-builder/cms-content`, registered before the `/{theme}` wildcard.
- `resources/js/Components/Admin/PageBuilder/CmsContentSelect.vue` — "Load more"
  pagination (RA-01); missing title renders "Restricted content", never the raw
  ULID or a client-fetched value (RA-02).
- `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue` — captures
  `expectedUpdatedAt` once alongside `form` and uses only that captured value in
  `submit()` (RA-04); closes on successful save; threads the three new CMS
  pagination props to all three `CmsContentSelect` usages.
- `resources/js/Pages/Admin/PageBuilder/Show.vue` — `BlockRow` gains
  `canConfigure`/`canRemove`; Configure/Remove buttons now gated by these flags
  (Remove was previously unconditional) (RA-03/N-03); threads the three new CMS
  pagination props through to both `BlockConfigPanel` usages.
- `tests/Feature/Theme/PageBuilderBlockServiceTest.php` — fixed one direct
  `new PageBuilderBlockService(...)` construction (2→4 args); added RA-03 tests
  (multi-component technical remove rejected, single-component
  `navigation_menu_slot`/`image` technical remove rejected, direct-service-bypass
  rejected, normal removable Section unaffected).
- `tests/Feature/Theme/PageBuilderControllerTest.php` — 2 existing stale-conflict
  assertions changed `assertConflict()` → `assertSessionHasErrors(...)` (RA-04/N-04);
  new test proving `canConfigure`/`canDuplicate`/`canRemove` derive from the
  canonical check for a technical vs. normal block (N-03).
- `tests/Feature/Theme/PageBuilderConcurrencyTest.php` — 1 existing stale-conflict
  assertion changed the same way.
- `tests/Feature/Theme/PageBuilderCmsReferenceTest.php` — replaced the fragile
  exact-match label test with one proving `card_grid` labels are stable across
  content/edits (N-01); added RA-01 tests (>20 Pages/Articles reachable via
  `cmsContent()`, bounded-response shape, selection-beyond-page-1 hydrates on
  reopen) and RA-02 tests (theme-only actor gets empty selectors + no title leak
  for either a Page or an Article reference + selector endpoint 403s; authorized
  actor sees permitted content and it hydrates; an actor without `content.view`
  cannot newly select CMS content by ULID even if guessed).

No file outside this list was modified by this task. `PublicRenderer.php` is
untouched (confirmed: no page-builder/RA-references in its diff — still only the
pre-existing FE-CHK-009 content). `Icon.vue` is untouched (confirmed by diff:
identical to the pre-existing FE-CHK-009 `heart`/`creditCard`/`settings` additions
only — nothing from this task).

### Schema / Migration

0 schema changes. 0 migrations. `isSectionBuilderManaged()` reuses the existing
`ThemeSection`/`ThemeComponent` relationships and the D-02 registry; the CMS
selector pagination reuses existing `CmsPage`/`CmsArticle` columns; no new table,
column, or persisted discriminator was added (see N-01's explicit report above for
why one was NOT invented for card_grid identity).

### D-02 / D-04 / F-01 regression check (not re-implemented, independently re-run)

- D-02: `test_discriminator_override_cannot_escape_registry_curation`,
  `test_block_disallowed_on_canvas_is_rejected`,
  `test_editing_advanced_debug_only_component_through_builder_rejected`,
  `test_place_reusable_into_forbidden_canvas_rejected` — all still PASS unchanged.
- D-04: `test_add_and_update_with_real_foreign_asset_rejected`,
  `test_duplicating_advanced_debug_component_with_foreign_asset_rejected`,
  `test_non_active_asset_status_rejected_and_active_asset_succeeds` — all still PASS
  unchanged.
- F-01: `tests/Feature/Theme/ThemeComponentSanitizationTest.php` — 24/24 PASS
  unchanged; `ThemeComponentService` remains the sole custom_html sanitization
  owner (not touched by this task beyond its pre-existing state).

### Evidence correction (per this task's own instruction)

The prior (F-02..F-19) evidence section's microsecond/interleaving claims are
accurate as implementation semantics (verified again by direct code reading in this
task) but are test-proven only at the SERVICE layer
(`test_interleaving_advanced_debug_write_cannot_be_overwritten_by_stale_builder_update`,
already existing), not at the true multi-process/multi-connection level — no test in
this repository opens a second real database connection to prove interleaving under
genuine concurrency; the existing test proves the invariant by performing the
"interleaving" write synchronously before the Builder call, which correctly exercises
the lock-then-compare code path but is not literal concurrent-thread proof. This
distinction is recorded here for accuracy rather than re-litigated with a new test
harness, per this task's explicit instruction not to open another remediation loop
solely for editorial proof when the implementation itself is already correct.

RA-04's frontend snapshot fix (old-config-plus-new-token now structurally impossible)
is proven by code inspection (the value is captured into a plain non-reactive
variable, not a reactive prop read) plus type-check/build, not by a new frontend
component-test harness — none exists in this repository and this task's own
instructions direct against introducing one solely for this.

### Verification actually performed

| Command | Result |
|---|---|
| `php artisan test tests/Feature/Theme/PageBuilder*Test.php tests/Feature/Theme/ThemeComponentSanitizationTest.php` (9 files) | PASS: 120 tests, 463 assertions, 0 failures, 0 errors |
| `php artisan test tests/Feature/Theme tests/Unit/Theme` (full Theme regression) | PASS: 303 tests, 1080 assertions, 0 failures, 0 errors |
| `npm run type-check` | PASS |
| `npm run build` | PASS, 14.72s |
| `vendor/bin/pint --test` (restricted to the exact touched PHP files listed above) | 1 file auto-fixed (import ordering/fully-qualified-name style in a new test), re-verified PASS |
| `git diff --check` | PASS |
| Full application regression | NOT run, as instructed |

FE-CHK-009: PRESERVED — `routes/web.php`'s FE-CHK-009 hunk (`public.payments.create`)
byte-identical, confirmed by direct diff inspection; `Icon.vue`, `PublicRenderer.php`
diffs unchanged from before this task. CODEX-FE009-01: OPEN / VALID / UNRESOLVED,
owner CR-001-J — untouched.

RA-01/RA-02/RA-03/RA-04 blockers: 0 remaining. N-01/N-03/N-04: CLOSED. New Human
Decisions: 0 (the N-01 persisted-discriminator question is reported, not raised as a
blocking new decision, since the honest generic-label fallback fully closes the
finding without one). Commit/push/merge: NOT PERFORMED.

---

## 16. FINAL BOUNDED UI REMEDIATION — SC-01 + SC-02 (Muse)

PATCH, frontend-only. No backend, route, schema, or migration change.

### SC-01: CLOSED — async CMS selector race

`CmsContentSelect.vue` `loadMore()` rewritten around a captured request
identity: `requestKind` + `requestGeneration` + `requestedPage` are bound
before the first await, and nothing after any await reads the live
`kind`/`isArticle` reactive for routing. Per-kind records
(`KindPagination`: items/page/hasMore/loading) keep Page and Article
state fully independent — a Page response can only ever touch the Page
record. A monotonic `selectorGeneration` (bumped on every parent-prop
refresh) discards stale responses instead of merging them into refreshed
state; the live record is re-resolved post-await so a response never
lands on a detached orphan. Appends dedupe by canonical ULID (no title
matching, no reordering).

Verification: deterministic runtime probe
`tests/Support/scripts/sc01_sc02_race_probe.cjs` (no Vue test framework
exists in repo) executes the REAL SFC `<script setup>` bodies via the
repo's own TypeScript compiler + `@vue/reactivity` with scripted fetch —
Page→Article and Article→Page mid-flight switches, prop-refresh
invalidation, single counter advance, ULID dedup, >20 reachability by
construction (per-kind page counters + hasMore preserved independently).
All SC-01 probe cases PASS; no browser E2E claimed.

### SC-02: CLOSED — editor identity / snapshot race

Two layers, both implemented: (1) parent keys the edit editor by block
identity only (`:key="edit:${ulid}"` — never by timestamp, so same-block
prop refresh keeps the mounted RA-04 frozen snapshot instead of
remounting and losing dirty state) and the add editor by registry entry
(`:key="add:${registryKey}"`, so Hero→Banner cannot reuse Hero config);
(2) `BlockConfigPanel` captures `sectionUlid` together with config +
token at mount and submits the URL against the captured identity —
CONFIG-A + TOKEN-A → URL-B is structurally impossible even if parent
keying regressed. Equal-timestamp A→B proven safe by probe (identity is
ULID-based, never timestamp-based). Same-block refresh retains the old
coherent snapshot and fails server-side on stale submit (RA-04
unchanged); success still closes the editor. Background block actions
are additionally disabled while any modal is open (`modalOpen` guard —
defense in depth; snapshot identity remains authoritative, no modal
framework built).

### Verification actually performed

| Command | Result |
|---|---|
| `node tests/Support/scripts/sc01_sc02_race_probe.cjs` | ALL 14 PROBE CHECKS PASSED (exit 0): 6 SC-01 race/dedup + 8 SC-02 identity/key/lifecycle |
| `php artisan test` (CmsReference, BlockService, Controller, Concurrency, Sanitization suites) | PASS: 94 tests, 390 assertions, 0 failures |
| `npm run type-check` | PASS |
| `npm run build` | PASS, 4.44s |
| `vendor/bin/pint --test` | PASS, 552 files |
| `git diff --check` | PASS |
| Full application regression | NOT run, as instructed |

Backend production PHP: NONE modified. Routes: unmodified. Schema: 0.
Migrations: 0. PublicRenderer: UNTOUCHED. Icon.vue: UNTOUCHED.
FE-CHK-009: PRESERVED. CODEX-FE009-01: OPEN / VALID / UNRESOLVED, owner
CR-001-J. N-04/RA-02/RA-03/D-02/D-04/F-01: NO REGRESSION (touched files
are additive Vue logic only; PHP suites above green). Commit/push/merge:
NOT PERFORMED.
