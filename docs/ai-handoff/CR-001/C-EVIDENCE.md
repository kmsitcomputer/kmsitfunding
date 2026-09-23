# CR-001-C Implementation Evidence — CMS / Site Design Foundation

> **Model:** meta/muse-spark-1.3-contributor (PRIMARY IMPLEMENTATION WRITER)
> **Routing:** HUMAN-VERIFIED IN COMMAND CODE / MODEL SELF-INTROSPECTION: NOT USED
> **Date:** 2026-09-21
> **Baseline:** `6806bf2` (CR-001-B FINAL) + protected FE-CHK-009 dirty work (preserved)

## Baseline

- `git status` at start: 16 modified + 8 untracked (FE-CHK-009 protected). All preserved.
- Protected FE-CHK-009 paths untouched except append-only admin-route delta in `routes/web.php`
  (new `/admin/site-design/*` group; FE-CHK-009 public-region lines unmodified).

## Files Created (5)

1. `app/Http/Controllers/Admin/SiteDesignController.php` — thin orchestration:
   index, showBrand, saveBrand (→ThemeBrandingService), uploadLogo (→ThemeAssetService),
   indexMenus, showMenu, saveMenu (→ThemeNavigationService), cloneToDraft
   (→SiteDesignCloneService), preview (ThemePolicy::preview + DRAFT gate), publish
   (→ThemeActivationService::activate).
2. `app/Services/Theme/SiteDesignCloneService.php` — copy-on-write aggregate clone per
   FINAL C-RECON §24.1A: source validation → fresh DRAFT via ThemeService → asset clone
   (source validation, fresh `theme/{yyyy}/{mm}/{$ulid}.{$ext}` destination, checked
   `Storage::copy`, compensation registration, complete C-14 row contract with
   `$actor->id` attribution) → template/section/component clone → root-first navigation
   remap via canonical `createItem()` → branding remap to cloned asset ULIDs. Fail-closed
   with filesystem compensation; source aggregate never mutated.
3. `resources/js/Pages/Admin/SiteDesign/Overview.vue`
4. `resources/js/Pages/Admin/SiteDesign/Branding.vue`
5. `resources/js/Pages/Admin/SiteDesign/Menus.vue`
6. `resources/js/Pages/Admin/SiteDesign/Preview.vue` (structure baseline; rendered draft
   output deferred to CR-001-E per §27 boundary).

## Files Modified (2)

1. `app/Services/Theme/ThemeNavigationService.php` — C-11 contract only: `createItem()`
   accepts/persists `visible_desktop`/`visible_mobile` from payload (default TRUE when
   omitted; existing `visible => true` preserved). No schema change.
2. `routes/web.php` — import + new `admin/site-design` route group (10 routes). Append-only.

## Tests Created (3 files, 28 tests)

- `tests/Feature/Theme/SiteDesignCloneServiceTest.php` (11): aggregate clone, source
  immutability, parent remap, asset physical copy + row contract, branding remap,
  navigation linkage, visibility persistence, missing-source fail-closed, null-logo,
  no-branding, archived-source rejection.
- `tests/Feature/Theme/SiteDesignControllerTest.php` (14): guest redirect, unauthorized
  403s, index/menus/preview OK, non-DRAFT preview 404, publish delegation (DRAFT→ACTIVE),
  clone orchestration (source stays ACTIVE), branding delegation.
- `tests/Feature/Theme/ThemeNavigationVisibilityTest.php` (3): explicit flags, omitted
  defaults TRUE, both-false.

Covers C-RECON §24.1A cases 1–16 (incl. 17-compensation logging path, 18-null branding,
19–24 visibility) and Test 25 (complete asset row contract assertion).

## Verification

| Check | Result |
|---|---|
| New C tests (28) | PASS (28/28, 77 assertions) |
| Full Theme suite | PASS (78/78, 315 assertions) |
| `npm run type-check` | PASS |
| `npm run build` | PASS (14.49s) |
| `vendor/bin/pint --test` (7 files) | PASS |
| `composer audit` | PASS (no advisories) |
| `git diff --check` | PASS |
| MySQL verification | sqlite-backed RefreshDatabase in this env; MySQL-specific semantics NOT VERIFIED (recorded, non-blocking for C scope) |

## Boundary Confirmation

- PublicRenderer: UNCHANGED by C (diff present is pre-existing FE-CHK-009 protected work).
- Migrations: 0. Schema changes: 0.
- FE-CHK-009: PRESERVED.
- CR-001-D: NOT STARTED. CODEX-FE009-01: OPEN / OWNER CR-001-J. IMP-010: BLOCKED.
- Commit/push/merge: NOT PERFORMED.

## Consolidated Remediation (G-01 + G-02 + N-06 + N-07) — 2026-09-21

PATCH — DO NOT REWRITE. Existing closed implementation preserved.

### G-01 Branding — CLOSED

- `SiteDesignController::showBrand` now passes `logoAssetUlid`/`faviconAssetUlid`
  (resolved via canonical `logoAsset`/`faviconAsset` relations). Form initializes from
  CURRENT configuration; colors-only saves preserve existing logo/favicon.
- `Branding.vue` gained operator-friendly Logo/Favicon selectors bound to the existing
  theme assets collection, with "No logo / No favicon" explicit-clear options.
  Null = intentional clear through canonical `ThemeBrandingService::save` (which already
  maps null → null); cross-theme asset rejected by canonical `resolveAssetId`.
- Tests: `SiteDesignBrandingTest` (5) — preservation, logo link, favicon link,
  explicit clear, cross-theme rejection. PASS.

### G-02 Navigation Item Management — CLOSED

- `ThemeNavigationService::updateItem()` (canonical edit: label, destination, parent,
  visible_desktop/mobile; cross-menu/too-deep/self-parent rejected; destination
  re-validated). No second domain, no schema change.
- `SiteDesignController`: `createNavigationItem` / `updateNavigationItem` /
  `deleteNavigationItem`, all guarded by canonical `THEME_UPDATE`; no new permissions.
- Routes (append-only): `menus.items.store` (POST), `menus.items.update` (PATCH),
  `menus.items.delete` (DELETE).
- `Menus.vue`: item create form (destination picker + desktop/mobile toggles), inline
  edit, delete; parent selects use same-menu items only.
- Tests: `SiteDesignNavigationItemTest` (12) — create, visibility persistence, edit,
  delete, unauthorized create/edit/delete, preview-only insufficiency, cross-menu and
  child-of-child rejection, view-only insufficiency, cross-theme service rejection. PASS.

### N-06 Test Coverage Debt — CLOSED

- `SiteDesignCloneFailureTest` (5): unresolved parent fail-closed + full rollback;
  copy-false fail-closed + rollback + clone-only cleanup; compensation-delete failure
  tolerated + logged (reflection-invoked, source untouched); branding-unresolved source
  untouched; late-failure filesystem parity (no orphan clone-only files). PASS.
- Note: `branding_asset_unresolved` is reachable only via ARCHIVED (not missing) assets
  since branding FKs are real constraints — test seeds ARCHIVED logo asset accordingly.

### N-07 Authorization Test Quality — CLOSED

- `SiteDesignLeastPrivilegeTest` (6) with least-privilege actors (single-permission
  roles): preview-only previews but cannot mutate/publish; view-only cannot preview;
  update edits but cannot preview/publish; publish-only publishes; update mutates nav
  but cannot clone without create; view+update+preview cannot publish. PASS.
- Canonical RBAC unchanged.

### Remediation Verification

| Check | Result |
|---|---|
| Full Theme suite | PASS (106/106, 412 assertions) |
| Remediation-only re-run (post-Pint) | PASS (28/28, 97 assertions) |
| `npm run type-check` | PASS |
| `npm run build` | PASS |
| `vendor/bin/pint --test` (8 files) | PASS (after 2 auto-fixed style issues) |
| `composer audit` | PASS (no advisories) |
| `git diff --check` | PASS |
| MySQL | NOT REQUIRED FOR C CLOSURE (per review); not run. Dev DB never touched. |

### Remaining Non-Gating (N-01..N-05, N-08, N-09) — recorded, untouched

- N-01: transaction/file-I/O architecture unchanged (no redesign).
- N-02: legacy `visible` semantics unchanged.
- N-03: no CR-001-E rendering implemented.
- N-04: no publish concurrency expansion.
- N-05: no broad UI polish.
- N-08: clone asset scope unchanged (ACTIVE-only copy per §24.1A.D).
- N-09: general navigation-tree hardening out of scope for this pass.

## Codex Final Gating Remediation (CODEX-CR001C-01/02/03) — 2026-09-22

PATCH — DO NOT REWRITE. Prior G-01/G-02/N-06/N-07 closures preserved (verified by
full Theme regression, 136/136).

### CODEX-CR001C-01 DRAFT-only mutations — CLOSED

- `SiteDesignController::abortUnlessDraft()` guard (409) applied AFTER canonical
  `ThemePolicy` authorization on: saveBrand, uploadLogo, saveMenu, create/update/
  deleteNavigationItem, publish. Owning Theme resolved through menu/item relations,
  never request-supplied identifiers. No new permission, no role-name checks.
- UI: Overview exposes Appearance/Menus/Preview + Publish only for DRAFT rows
  (gated by `canPublish` capability prop); ACTIVE rows offer View-menus + New draft
  only. Advanced/Debug untouched.
- Tests: `SiteDesignDraftOnlyTest` (18) — DRAFT branding/nav success; ACTIVE/INACTIVE/
  ARCHIVED branding/menu/nav/logo denied (409) with persisted-state-unchanged
  assertions; non-DRAFT publish denied; Overview Inertia assertions. PASS.
- Note: ARCHIVED publish denial surfaces as 403 (canonical policy state predicate)
  before the 409 guard — both are fail-closed; test accepts either.

### CODEX-CR001C-02 Faithful ACTIVE → DRAFT cloning — CLOSED

- A. `cloneFromActive()` now enforces `source.status === ACTIVE` (`source_not_active`
  for DRAFT/INACTIVE/ARCHIVED); rejection occurs before any file copy or DB write.
- B. Reusable section identity: `old_section_id → new_section` map; each unique
  section cloned once with its components; repeat placements use canonical
  `placeReusable()` preserving per-template position order.
- C. Section visibility: hidden source sections persisted via canonical
  `ThemeSectionService::update(['visible' => false])` (no raw Eloquent; `createAndPlace`
  hardcodes visible=true, so the canonical edit path is used instead).
- Atomicity/compensation/N-06 coverage preserved (16/16 prior clone tests green).
- Tests: `SiteDesignCloneFidelityTest` (6) — ACTIVE allowed; DRAFT/INACTIVE/ARCHIVED
  rejected with no theme/children/files side effects; reusable-shared-once across two
  templates with identical placement; visibility + order fidelity; source unchanged. PASS.

### CODEX-CR001C-03 Operator publish action — CLOSED

- Overview: Publish button for DRAFT rows when `canPublish`; Preview: Publish design
  button when `canPublish`. Both invoke the existing canonical Site Design publish
  route → `ThemeActivationService::activate`. No new route, service, or status.
- Capability props (`canPublish`) derived from canonical `ThemePolicy::publish`;
  UI flags grant nothing — backend remains authoritative.
- Tests: `SiteDesignPublishActionTest` (6) — authorized activation, unauthorized denial,
  preview/Overview `canPublish` true/false Inertia prop assertions. PASS.

### Codex Remediation Verification

| Check | Result |
|---|---|
| Codex tests (30) | PASS (141 assertions) |
| Full Theme suite | PASS (136/136, 553 assertions) |
| `npm run type-check` | PASS |
| `npm run build` | PASS |
| `vendor/bin/pint --test` (8 files) | PASS (after 2 auto-fixed style issues) |
| `composer audit` | PASS (no advisories) |
| `git diff --check` | PASS |
| MySQL | NOT REQUIRED FOR C CLOSURE; not run. Dev DB never touched. |
| G-01/G-02 | CLOSED (no regression — branding/nav suites green) |

## CODEX-CR001C-01 Final UI Micro-Remediation — 2026-09-22

Backend DRAFT guards preserved untouched. This pass is Vue-presentation only.

- `Menus.vue`: derived `isDraft = computed(() => props.theme.status === 'DRAFT')`;
  New-menu card, item Edit/Delete actions, inline edit form, and New-item form render
  only under `isDraft`; non-DRAFT shows `data-testid="site-design-readonly"` notice
  while menu/item/destination/visibility data remains displayed read-only.
- `Branding.vue`: same `isDraft` concept; mutation cards (`branding-form`,
  `logo-upload-card`) render only for DRAFT; non-DRAFT renders `branding-readonly`
  card displaying current colors, typography, logo, favicon + the read-only notice.
- `Overview.vue`: inspected — no new direct mutation affordance for non-DRAFT; ACTIVE
  clone-to-DRAFT workflow preserved.
- Render verification: `SiteDesignReadOnlyTemplateTest` (10) asserts the SFC template
  contract (mutation controls nested in `v-if="isDraft"`, read-only notice + current
  config for non-DRAFT, `isDraft` strictly `status === 'DRAFT'`). No Vue component-test
  framework exists in this repo (no vitest/jest/@vue/test-utils); per task scope no new
  test stack was introduced — LIMITATION REPORTED: assertions verify template source
  structure, not a mounted-component render.
- Verification: template tests 10/10; lifecycle + G-01/G-02 35/35; Theme regression
  146/146 (578 assertions); type-check PASS; build PASS; Pint PASS; diff-check PASS.

Semua patch selesai dan verifikasi lolos.
