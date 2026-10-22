# CR-001-D — Visual Page Builder — Implementation Specification

## 1. Status

SPECIFICATION — HUMAN DECISIONS RESOLVED — READY FOR HUMAN SPEC GATE.

HD-CR001D-01 (APPROVED — additive/allowlisted/sanitized), HD-CR001D-02
(DEFER — no Video block in CR-001-D), and HD-CR001D-03 (APPROVED —
shared-per-content-kind canvas) have been incorporated below. See §54.

Not implemented. No production source, migration, or test created by this
document. Read-only repository analysis only.

## 2. Purpose

Give normal (non-technical) website operators an approachable, block-based
way to compose the visible structure of the site's Home page, generic
Pages, and generic Articles — without needing to understand the underlying
Theme Engine vocabulary (`Theme → Template → Section → Component`,
`navigation_menu_slot`, `destination_type` polymorphism, raw JSON `config`).

CR-001-D is an **operator-facing orchestration layer**, not a new content
or rendering domain. It wraps the existing, already-implemented IMP-006
Theme Engine services exactly as they exist today.

## 3. Scope

- A new admin surface, `Admin/PageBuilder/*`, presenting an ordered list of
  "Blocks" per editable canvas (Home / Page / Article — see §7) with
  Add / Remove / Enable / Disable / Reorder / Configure / Preview actions.
- A new thin service, `PageBuilderBlockService`, that composes the
  existing `ThemeSectionService` + `ThemeComponentService` calls needed to
  create/mutate a "Block" (defined precisely in §9) inside one transaction.
- A new read-only, code-level `PageBuilderBlockRegistry` describing each
  block type's operator-facing metadata (name, icon, category, whether
  domain-backed, whether repeatable) layered over the existing closed
  `ComponentConfigValidator::SCHEMAS` set — it does not replace that
  validator, which remains the single source of truth for what is a valid
  component `type`/`config`.
- A bundle of additive, backward-compatible `ComponentConfigValidator`
  schema extensions needed for the block library (see §11–§13), gated by
  Human Decision HD-CR001D-01 (§54), following the exact precedent of
  ADR-001 (which additively extended `content_list`/`card_grid`
  `content_kind` from `page|article` to `page|article|program|campaign`).
- Publish continues to mean exactly what it means today: activating the
  edited DRAFT Theme via the existing `ThemeActivationService::activate()`,
  reached only through the existing CR-001-C `SiteDesignController::publish`
  contract (or an equivalent Page Builder "Publish" button that calls the
  identical route — see §25).

## 4. Explicit Non-Scope

- No new domain model, no new database table, no new column (see §45–§46).
- No modification to `PublicRenderer.php` (CR-001-E ownership — READ ONLY
  for D; see §26).
- No modification to CMS (IMP-005) page/article editorial workflow
  (`Cms\PageController`, `Cms\ArticleController`, `PageService`,
  `RevisionService`, `PublicationService`) — CmsPage/CmsArticle content
  (title, body, SEO) continues to be authored exactly as it is today.
- No modification to `ThemeController` (Advanced/Debug surface) — it
  remains the permanent technical fallback per locked Human Decision
  HD-CR001-05 (§5).
- No Zakat/Fidyah calculation logic (CR-001-G), no responsive public UX
  (CR-001-F), no Admin Experience V2 IA shell (CR-001-H), no
  `CODEX-FE009-01` remediation (CR-001-J).
- No real-time collaboration, no WebSocket requirement, no persistent
  Node runtime in production, no Redis/Supervisor/PM2/Docker.
- No inline video embedding (deferred — HD-CR001D-02, §54).
- No per-individual-CmsPage distinct block canvas (architecturally not
  supported without a schema change — see §7 and HD-CR001D-03, §54).

## 5. Locked Upstream Contracts

CR-001-D must consume, not rewrite, the following CR-001-C / CR-001-B /
IMP-005 / IMP-006 contracts (all verified directly against the current
repository, `HEAD eadc1131270ef59bbf46337f31b7d92b6dd69c81`):

| Contract | Owner | File |
|---|---|---|
| Theme status lifecycle (`DRAFT→ACTIVE→INACTIVE→ARCHIVED`), singleton activation | ThemeActivationService (canonical) | `app/Services/Theme/ThemeActivationService.php` |
| ACTIVE→DRAFT copy-on-write clone, faithful reusable-section cloning, asset compensation | CR-001-C | `app/Services/Theme/SiteDesignCloneService.php` |
| DRAFT-only mutation gate pattern (`abortUnlessDraft()`, 409) | CR-001-C | `app/Http/Controllers/Admin/SiteDesignController.php` |
| `ThemeNavigationService::createItem/updateItem` incl. `visible_desktop`/`visible_mobile` | CR-001-B / CR-001-C | `app/Services/Theme/ThemeNavigationService.php` |
| `THEME_PREVIEW` permission + `ThemePolicy::preview()` | CR-001-B | `app/Policies/ThemePolicy.php` |
| Advanced/Debug technical Theme admin retained permanently, coexisting | HD-CR001-05 (Human-approved, cited in `docs/ai-handoff/CR-001/C-RECON.md`) | `app/Http/Controllers/Theme/ThemeController.php` (existing) |
| Single-actor publish, no second-person approval | HD-CR001-02 (Human-approved) | `ThemeActivationService::activate()` |
| No schema changes, zero migrations in C | CR-001-C | `docs/audits/CR-001-C-FINALIZATION.md` |
| `PublicRenderer` not owned/modified by C | CR-001-C | `docs/audits/CR-001-C-FINALIZATION.md` |
| Closed `ComponentConfigValidator::SCHEMAS` (9 types), additive-extension precedent | ADR-001 (Human-approved) | `docs/adr/ADR-001-theme-content-projection-amendment.md`, `app/Services/Theme/ComponentConfigValidator.php` |
| CMS content sanitization allowlist (`DOMDocument` walker, no HTMLPurifier) | IMP-005 | `app/Services/Content/ContentSanitizer.php` |
| FE-CHK-009 protected worktree preservation | Global | current `git status` |

CR-001-D does not amend any of these; it calls them.

## 6. Operator Mental Model

```
SITE DESIGN (CR-001-C, existing)          PAGE BUILDER (CR-001-D, this spec)
─────────────────────────────────         ──────────────────────────────────
Appearance / Branding                     PAGE (Home / Page / Article canvas)
Menus                                        ↓
                                           BLOCKS (ordered list)
                                              ↓
                                           ADD · CONFIGURE · REORDER ·
                                           ENABLE/DISABLE · REMOVE
                                              ↓
                                           PREVIEW (existing THEME_PREVIEW gate)
                                              ↓
                                           PUBLISH (existing ThemeActivationService)
```

The operator never sees "Template", "Section", "Component", `type` strings,
or raw JSON `config`. Every Block row is presented with a human label
(from `PageBuilderBlockRegistry`), an icon, and a plain-language
configuration form.

## 7. Canonical Page Mapping

**Critical finding (governs the entire spec):** `theme_templates` carries
**`UNIQUE(theme_id, content_kind)`**, and `content_kind` is a closed
3-value enum (`home`, `page`, `article`) enforced in
`ThemeTemplateService::CONTENT_KINDS`. This means a Theme owns **exactly
one** Template for each of Home, Page, and Article — the Section/Component
tree is a **shared canvas per content type**, not a canvas per individual
`CmsPage` row.

Therefore, "editing a Page's blocks" in the Visual Page Builder means
editing the **one shared `page` ThemeTemplate's** block list (its hero
banner, its generic content sections, its CTA blocks, etc. — the chrome
that surrounds every generic CMS Page), not composing a distinct block
layout per `CmsPage`. The actual textual content of an individual
`CmsPage` (title, body, SEO) continues to be authored exactly as today,
through the existing `Cms\PageController` + `Cms/Pages/Edit.vue` editorial
flow (IMP-005), completely unaffected by CR-001-D. The same applies to
`article` content_kind and `CmsArticle`.

This is not an invented interpretation: it is the explicit, Human-approved
direction already recorded in the locked change-request document
`docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md`
§26 ("One Active Site Design" — KEEP/ADAPT/WRAP table), which states that
`ThemeTemplate`/`ThemeSection`/`ThemeComponent` are **KEPT as-is** and
**WRAPPED** by a new operator-facing Page Builder layer — i.e. the CR
itself anticipates exactly this shared-canvas model, and does not describe
per-`CmsPage` template forking.

**Mapping table:**

| Operator-facing "Page" | Canonical aggregate | Editable by |
|---|---|---|
| Home | `ThemeTemplate(content_kind='home')` block canvas | CR-001-D (this spec) |
| Home's designated content (optional) | `CmsHomepageAssignment` → `CmsPage` | Existing `Cms\HomepageController` (out of scope) |
| "Pages" chrome (shared by all generic CMS pages) | `ThemeTemplate(content_kind='page')` block canvas | CR-001-D (this spec) |
| An individual CMS Page's title/body/SEO | `CmsPage` + `CmsContentRevision` | Existing `Cms\PageController` (out of scope) |
| "Articles" chrome (shared by all articles) | `ThemeTemplate(content_kind='article')` block canvas | CR-001-D (this spec) |
| An individual Article's title/body/SEO | `CmsArticle` + `CmsContentRevision` | Existing `Cms\ArticleController` (out of scope) |
| Any other system/domain landing page (e.g. a future ZISWAF landing route) | Not a Theme Template today; no evidence of a 4th `content_kind` | Not in D scope — would require its own `content_kind` addition via ADR, out of scope until such a page is defined by a later phase |

This scope boundary is formalized as **HD-CR001D-03 — APPROVED** (§54): the
Human has confirmed the shared-per-content-kind canvas model (Home/Page/
Article — exactly three canvases per Theme) as the locked scope for
CR-001-D. It materially changes the operator's mental model from "I am
editing this one page" to "I am editing the layout shared by all pages of
this type" — the UI copy must make this explicit (e.g. "Page Layout"
rather than bare "Pages" in the Page Builder canvas picker) to avoid
operator confusion. Any future per-individual-CmsPage/CmsArticle unique
layout capability is explicitly out of scope and requires its own
separate governed change-control / architecture decision (§54).

## 8. Home Mapping

**Locked per HD-CR001D-03 (APPROVED):** Home is the
`ThemeTemplate(content_kind='home')` for the DRAFT Theme being edited —
one of exactly three shared canvases (Home / Page / Article) CR-001-D
provides per Theme (naturally singular per Theme, so no per-page ambiguity
exists here the way it does for generic Pages/Articles).

- **Selecting Home**: there is nothing to "select" in Page Builder terms —
  Home is always the one `home` Template on the currently-edited DRAFT
  Theme (created automatically for every new Theme by
  `SiteDesignCloneService` during ACTIVE→DRAFT clone, or by `ThemeService`
  seeding for a brand-new Theme — both out of D's scope to change).
- **Blocks listing/ordering/visibility/preview**: identical mechanism to
  Page/Article (§9–§21) — Home is simply one of the three canvases the
  Page Builder Index screen offers.
- **Designated Home content** (`CmsHomepageAssignment`) is a **separate,
  pre-existing, out-of-scope concern** (IMP-005, `Cms\HomepageController`)
  that optionally supplies body content into a `rich_text(source=
  cms_content)` Home block if the operator chooses to add one, pointing at
  a specific published `CmsPage` ulid. D does not create, resolve, or
  validate the homepage designation itself — it only lets the operator
  configure a `rich_text` block's `content_kind`/`content_ulid` fields
  exactly as `ComponentConfigValidator` already validates them.
- **Active design stability**: unaffected by D — inherited unchanged from
  the existing copy-on-write invariant (editing the DRAFT Home canvas never
  touches the ACTIVE Theme's `home` Template; only `ThemeActivationService
  ::activate()` flips the singleton pointer, per §5).

## 9. Page Builder Architecture

### 9.1 Core mapping: one Block = one Section containing exactly one Component

This is the central architectural decision of this specification, and it
is the only mapping that requires **zero schema changes** while correctly
satisfying every constraint in the "Before Coding" governance:

| Operator action | Canonical state owned by |
|---|---|
| Block **ordering** | `theme_template_sections.position` (the placement pivot) — i.e. **Section-level** placement order within the Template |
| Block **enabled/disabled** | `theme_sections.visible` (boolean) — i.e. **Section-level** visibility |
| Block **content/settings** | The single `ThemeComponent` row placed inside that Section — `type` + `config` |

Rationale, directly evidenced from the schema (verified via migration
files and model casts):

- `theme_components` has **no** `visible`/`enabled` column. Only
  `theme_sections.visible` exists. The specification instructions
  explicitly require reusing existing Section visibility rather than
  inventing a second flag — this is only possible if "enable/disable" is
  defined at the Section level, which in turn requires each operator
  Block to correspond to exactly one Section.
- `theme_template_sections` (the pivot) is what carries ordering
  (`position`, unique per `(theme_template_id, position)`); "Reorder
  Block" therefore is exactly `ThemeSectionService::reorder()`, not
  `ThemeComponentService::reorder()` (which would only matter if a Section
  ever held more than one Component — see §9.2).
- A Section can be `is_reusable` and shared across Templates via
  `ThemeSectionService::placeReusable()` — this exactly satisfies "Reuse
  Block" (§22) with zero new code beyond a controller action, by treating
  a reusable single-component Section as a reusable Block (e.g. a
  "Newsletter Signup" banner placed on both Home and Page).

### 9.2 Coexistence with Advanced/Debug multi-component sections

Sections created through the existing `ThemeController` (Advanced/Debug,
HD-CR001-05) may legitimately contain more than one Component (nothing in
the schema forbids it; only Page Builder's own `PageBuilderBlockService`
constrains itself to the 1:1 rule for Sections **it** creates).

Page Builder's block list, when it encounters an existing Section with
`components()->count() !== 1` (created by Advanced/Debug or a future
technical operator), must render it as a **read-only "Technical Section —
edit in Advanced/Debug"** row: it is still enable/disable-able (Section
`visible`) and reorderable (Section `position`) through Page Builder
(both are Section-level and safe), but Configure is not offered inline —
the row instead links to the corresponding `Theme/Show.vue` Advanced/Debug
screen for that Section. This satisfies "must remain fully functional
alongside C/D's simplified UI" without hiding or breaking pre-existing
technical Sections.

### 9.3 New application service: `PageBuilderBlockService`

One new service, `app/Services/Theme/PageBuilderBlockService.php`,
composing the two already-canonical services inside its own
`DB::transaction()` (the same nesting pattern `SiteDesignCloneService`
already uses when it calls `ThemeNavigationService::createItem()` inside
its own transaction):

```php
final class PageBuilderBlockService
{
    public function addBlock(ThemeTemplate $template, string $blockType, array $config, Principal $actor): ThemeSection;
    public function updateBlockConfig(ThemeSection $section, array $config, Principal $actor): ThemeComponent;
    public function removeBlock(ThemeTemplate $template, ThemeSection $section, Principal $actor): void;
    public function duplicateBlock(ThemeTemplate $template, ThemeSection $source, Principal $actor): ThemeSection;
}
```

- `addBlock()`: validates `$blockType` against `ComponentConfigValidator
  ::componentTypes()` (fail-closed on unknown type — §30), then
  `ThemeSectionService::createAndPlace($template, ['is_reusable' => false,
  'layout_variant' => 'default'], $actor)` followed by
  `ThemeComponentService::create($section, $blockType, $config, $actor)`,
  both inside one transaction (compensating: if the Component create
  fails validation, the Section create must roll back — a nested
  transaction/savepoint achieves this automatically under Laravel).
- `updateBlockConfig()`: resolves the Section's sole Component (fail
  closed with `ThemeValidationException('not_a_single_block_section', …)`
  if the Section does not contain exactly one Component — protects the
  §9.2 boundary) and delegates to `ThemeComponentService::update()`.
- `removeBlock()`: delegates directly to `ThemeSectionService
  ::removeFromTemplate()` (already deletes the Section when unplaced and
  non-reusable, cascading the sole Component via its FK).
- `duplicateBlock()`: reads the source Section's Component `type`+`config`
  and calls `addBlock()` with the same values — no new canonical service
  method required; a straightforward orchestration reuse (see §22).
- Reordering and enable/disable do **not** need a Page Builder–specific
  service method — the controller calls `ThemeSectionService::reorder()`
  and `ThemeSectionService::update($section, ['visible' => bool], $actor)`
  directly, exactly as `SiteDesignController` already calls
  `ThemeNavigationService` methods directly for Menus.

No canonical Theme service (`ThemeSectionService`, `ThemeComponentService`,
`ThemeTemplateService`, `ThemeActivationService`, `PublicRenderer`) is
modified.

## 10. Block Registry

`app/Services/Theme/PageBuilderBlockRegistry.php` — a **read-only, static,
code-level** registry (no database table; this is presentation metadata
only, analogous to how `ComponentConfigValidator::SCHEMAS` is a static
PHP array, not a DB-driven table). One entry per registered block key:

| Field | Purpose |
|---|---|
| `key` | The underlying `ThemeComponent.type` string (must exist in `ComponentConfigValidator::componentTypes()`) |
| `operator_label` | Human-facing name, e.g. "Campaign Grid" |
| `description` | One-sentence operator help text |
| `category` | `Content` \| `Fundraising` \| `Layout` \| `Navigation` \| `Social Proof` |
| `icon` | Icon identifier from the existing curated Lucide subset (`resources/js/Components/UI/Icon.vue`) |
| `default_config` | Safe default values pre-filling the Configure form |
| `allowed_canvases` | Subset of `{home, page, article}` this block may be added to |
| `domain_backed` | `true` if it queries a canonical domain projection (Campaign/Program) |
| `repeatable` | `true` unless a business rule caps it (none identified — all repeatable) |
| `removable` | `true` for all Page Builder–created blocks; `false` for a legacy multi-component technical Section shown per §9.2 |
| `configurable_inline` | `false` for the §9.2 technical-section fallback |
| `preview_supported` | `true` for all — Preview always renders the whole DRAFT Template, not per-block |

`PageBuilderBlockRegistry::forCanvas(string $contentKind): array` filters
by `allowed_canvases` and is the sole data source for the "+ Add Block"
picker's category-grouped list.

The registry defines **no executable behavior** — no PHP closures, no
Blade/Vue snippets, no SQL, stored in configuration. It is a plain
associative array of scalar/array metadata, matching the "Avoid storing
arbitrary executable behavior" requirement by construction (it is code,
reviewed and deployed like any other class, not operator-editable data).

## 11. Block Classification Matrix

| Block | Classification | Underlying `type` | Notes |
|---|---|---|---|
| Hero | **A — Implement** | `hero` (existing) | Direct wrap, no schema change |
| Banner | **A — Implement** | `banner` (existing) | Direct wrap |
| Rich Text (CMS excerpt) | **A — Implement** | `rich_text`, `source=cms_content` (existing) | Direct wrap |
| Rich Text (short caption) | **A — Implement** | `rich_text`, `source=caption` (existing) | Direct wrap, 1000-char plain caption already supported |
| Campaign Grid | **A — Implement** | `content_list`, `content_kind=campaign` (existing, ADR-001) | Direct wrap |
| Campaign Carousel | **A — Implement (HD-CR001D-01 APPROVED)** | `content_list` + new optional `display_mode` field (`grid`\|`carousel`, default `grid`) | Same projection as Grid; only Vue rendering differs. Additive optional field, not a new `type` — approved under HD-CR001D-01; implementation still requires a governance ADR before Muse writes code (§54) |
| Program Grid | **A — Implement** | `content_list`, `content_kind=program` (existing, ADR-001) | Direct wrap |
| Articles / News | **A — Implement** | `content_list`, `content_kind=article` (+ existing `article_type` filter) | Direct wrap |
| Statistics (hand-entered) | **A — Implement** | `stats` (existing) | Direct wrap |
| ZISWAF Services (presentation cards) | **A — Implement** | `card_grid`, `mode=authored` (existing) | Existing authored-card contract already covers 6 static service cards with destinations; no new type needed |
| Quick Donation / Donation CTA | **A — Implement (HD-CR001D-01 APPROVED)** | `cta_button` + new optional `intent` field — **closed enum, `in:general,donation,zakat`**, presentation label only | Destination remains a canonical `SYSTEM_ROUTE`/`EXTERNAL_URL`/`CMS_CONTENT` reference, validated exactly as today; `intent` is allowlisted server-side (Laravel `in:` rule against the fixed 3-value list in §12.1), affects only button styling/icon, never routing or business logic, and is not operator-extensible |
| Gallery (authored images) | **A — Implement** | `card_grid`, `mode=authored` (images only, text/destination omitted by the operator form) | Reuses existing type; Page Builder simply presents a Gallery-flavored form over the same schema |
| Partners (logos + links) | **A — Implement** | `card_grid`, `mode=authored` | Same reuse as Gallery/ZISWAF, different operator-facing form labels |
| Safe Custom Content | **A — Implement (HD-CR001D-01 APPROVED)** | `rich_text` + new `source=custom_html` option + `body_html` field, sanitized via existing `ContentSanitizer` at save time | See §14 for the full security boundary |
| Zakat Calculator CTA | **B — Structural placeholder** | `cta_button` (existing) | Config contract usable today; destination `SYSTEM_ROUTE` name will not resolve (`Route::has()` fails closed, §30) until CR-001-G defines the calculator route — safe by construction, not a security gap |
| Testimonials | **C — Defer** | none | No existing schema fits (needs quote/author/role/avatar shape); requires a genuinely new `type` beyond an additive field — defer to a later CR-001-D remediation round or CR-001-I, propose via its own ADR when scheduled |
| FAQ | **C — Defer** | none | Same reasoning as Testimonials (question/answer list shape not covered by any existing type) |
| Contact | **C — Defer** | none | A static address/phone/email block is low-risk but has no existing type; more importantly, a map embed (the obvious operator expectation) is an `<iframe>`, which `ContentSanitizer::DANGEROUS_TAGS` explicitly rejects — cannot be safely implemented without a dedicated, reviewed embed mechanism. Defer entire block rather than ship a partial "address only" version that doesn't match operator expectations |
| Video | **E — Excluded from CR-001-D (HD-CR001D-02: DEFER)** | none | Not implemented, not registered in `PageBuilderBlockRegistry`, not offered anywhere in the operator block library — no self-hosted behavior, no iframe, no external oEmbed, no provider allowlist, no new CSP/security policy. Excluded entirely rather than shipped as a partial/fake entry. Future implementation requires its own governed mechanism/decision (§53) |
| Impact (live aggregate metrics) | **C — Defer** | none | Requires a defined metric/domain owner (which service computes "total impact"?) that does not exist yet; hand-entered numbers are already covered by the Statistics block (A) |
| Navigation Menu | Not a Page Builder "block" | `navigation_menu_slot` (existing) | Already fully owned by CR-001-C's Menus screen; Page Builder does not re-expose it as an addable block to avoid two competing surfaces for the same primitive |

**Classification key** (amended): A = Implement in D; B = Structural
placeholder/presentation contract only; C = Defer to a later phase; D was
the pre-decision "reject/requires Human Decision" marker used prior to
HD-CR001D-02 and no longer applies to any row (Video is now marked **E —
Excluded**, a stronger and more final status than a pending "D", reflecting
that the Human Decision has been made, not merely requested).

## 12. Block Configuration Contract

Every block's configuration form is generated from
`ComponentConfigValidator::SCHEMAS[$type]` — the Laravel validation rules
are the single source of truth for both server-side enforcement (already
existing) and the Vue form's field list (new, generated/maintained by
hand in `PageBuilderBlockRegistry` metadata, not auto-derived from the
Laravel rule DSL, to keep the Vue layer simple and reviewable).

### 12.1 CTA intent — closed allowlist (HD-CR001D-01 APPROVED)

`cta_button.intent` (and any button-style field on `banner`/`card_grid`
that reuses the same destination union) is validated with a Laravel
`nullable|string|in:general,donation,zakat` rule — a **fixed, closed
3-value enum**, not a free-form string. The value carries **presentation
meaning only** (which icon/color variant the Vue button renders); it is
never interpreted as a route, a business rule, or a routing decision by
any backend code. Extending this enum with a 4th value in the future
requires its own additive schema change through the same governance path
as this one (an ADR, per §54), not an ad hoc operator-supplied string.

## 13. Presentation vs Domain Data Boundary

Strictly preserved from the existing architecture (ADR-001 established
this precedent for `content_list`/`card_grid`):

- **Presentation config** (stored in `theme_components.config` JSON):
  heading/subheading text, alignment, layout variant, column counts,
  display mode, CTA label, image asset reference, `limit`, `order`/`sort`
  mode, `content_kind` filter, `article_type` filter.
- **Domain reference** (never copied, only referenced): a `content_kind`
  + `limit`/`order` pair that `PublicRenderer::renderContentList()`
  resolves at render time via `ProgramProjectionResolver`/
  `CampaignProjectionResolver` (IMP-007-owned, read-only). No Campaign,
  Program, Fund, Donation, Payment, Zakat, Fidyah, Qurban, Wakaf,
  Ledger, Commission, Withdrawal, Refund, Distribution, Beneficiary, or
  Impact business record is ever written into `theme_components.config`
  or any other Theme table.
- No arbitrary SQL/filter expression is ever accepted from an operator —
  `content_kind` remains a closed enum validated server-side.

## 14. Safe Custom Content Contract (HD-CR001D-01 APPROVED)

"Safe" is defined precisely as: **exactly the same allowlist already
enforced by `App\Services\Content\ContentSanitizer`** — no new sanitizer,
no new allowlist, no HTMLPurifier or other new dependency (consistent with
CHANGE-CONTROL.md's Dependency Governance, and with the CR document's own
§30 requirement that Page Builder rich content "continues to pass through
the same server-side sanitization IMP-005/IMP-006 already establish").
This is an explicit, non-negotiable condition of the Human's approval of
HD-CR001D-01 — no separate/new sanitizer or library may be substituted
without its own separate authorization.

### 14.1 Explicit security boundary (Human-mandated, HD-CR001D-01)

Every one of the following is rejected, not silently stripped, by the
existing `ContentSanitizer::DANGEROUS_TAGS`/allowlist mechanism reused
here — none is newly built for D, and none is weakened by D:

| Forbidden | Enforcement |
|---|---|
| `<script>` / arbitrary JavaScript | `DANGEROUS_TAGS` rejects `<script>`; no attribute allowlist ever includes an inline JS attribute |
| Event handlers (`onclick`, `onerror`, etc.) | Not in `GLOBAL_ATTRIBUTES`/`TAG_ATTRIBUTES` allowlist — silently stripped even on otherwise-allowed tags |
| PHP | Never evaluated — `body_html` is rendered as static markup only, never passed through `eval`, `Blade::render`, or any PHP templating engine |
| Blade | Not rendered as a Blade template at any point in the pipeline (stored/served as sanitized HTML string) |
| Vue (SFC/directives) | Not rendered as a Vue template; inserted via `v-html` as inert markup only, never compiled as a component |
| SQL | `body_html` is never interpolated into a query; it is a validated/sanitized string column value only |
| Arbitrary `<iframe>` | `DANGEROUS_TAGS` explicitly rejects `iframe`, `object`, `embed`, `form`, `input` |
| Unsafe URLs (`javascript:`, `data:`) | `ContentSanitizer`'s href scheme allowlist (`https, http, mailto, tel`) rejects any other scheme |

No new mechanism is invented for any of the above — this table documents
that the reused `ContentSanitizer` already satisfies every constraint the
Human's approval placed on Safe Custom Content.

Contract:

- New `rich_text` config variant: `source = 'custom_html'`,
  `body_html` (required_if, string, ≤ 200KB — the existing `MAX_BYTES`
  constant already enforced by `ContentSanitizer`).
- `PageBuilderBlockService::addBlock()`/`updateBlockConfig()` MUST call
  `ContentSanitizer::sanitize($config['body_html'])` and persist the
  **sanitized** output back into `config['body_html']` before saving —
  never the raw operator input. `ContentSanitizationException` (any
  `disallowed_element`/`href_and_data_media`/`media_token_invalid`/
  `body_too_large` code) is fail-closed: `422`, block not saved.
- `<script>`, `<style>`, `<iframe>`, `<object>`, `<embed>`, `<form>`,
  `<input>`, event handlers, and any element/attribute outside
  `ContentSanitizer::ALLOWED_TAGS`/`TAG_ATTRIBUTES` are rejected, not
  silently stripped for dangerous tags (per existing sanitizer behavior).
- Image references inside custom HTML must use the existing
  `data-media` ULID token contract (`enforceImgContract`), never a raw
  `src` — this is already enforced by the reused sanitizer, requiring no
  new code.

This is classified **A — Implement, pending HD-CR001D-01** because the
mechanism is already fully proven (IMP-005 uses it today for CMS body
HTML); only the closed-enum extension (`source` gaining a third value)
needs the same governance sign-off ADR-001 obtained for its own enum
extension.

## 15. Asset Contract

Image-bearing blocks (`hero.background_theme_asset_ulid`,
`image.theme_asset_ulid`, `card_grid.cards[].theme_asset_ulid`) continue
to reference `ThemeAsset` rows exclusively, exactly as
`ComponentConfigValidator` already requires. No new media storage system.
The asset picker Vue component reuses the same `theme_assets` listing
already exposed by `SiteDesignController`/`ThemeAssetService` (list
assets scoped to the DRAFT Theme being edited — cross-theme asset
reference must be rejected server-side, mirroring the existing
cross-theme rejection pattern in `SiteDesignCloneService`/
`ThemeBrandingService`). No raw filesystem path is ever exposed to the
operator or the frontend — only `ulid` + a resolved public URL via the
existing `ThemeAssetTokenResolver`/equivalent.

## 16. CTA Destination Contract

Unchanged from the existing, already-safe contract: `destination_type`
closed to `SYSTEM_ROUTE | CMS_CONTENT | EXTERNAL_URL`, validated at save
time by the existing `NavigationDestinationValidator` (`Route::has()` for
SYSTEM_ROUTE; `page|article` + valid ULID for CMS_CONTENT;
`^https?://` + `filter_var(FILTER_VALIDATE_URL)` for EXTERNAL_URL — no
`javascript:`/`data:` scheme is ever possible). Page Builder's CTA/Banner
configuration forms call this same validator through the same
`ComponentConfigValidator::assertValid('cta_button', …)` /
`assertValid('banner', …)` path already wired into
`ThemeComponentService::create()`/`update()` — no new destination model.

## 17. Add Block Contract

`POST /admin/page-builder/{template}/blocks` → `PageBuilderController
::store()` → `abortUnlessDraft($theme)` (409 if template's owning Theme
is not `DRAFT`) → `ThemePolicy::update($actor, $theme)` (403) →
`PageBuilderBlockService::addBlock($template, $blockType, $config,
$actor)` → `ThemeAuditLogger::recordSectionUpdated()` +
`recordComponentUpdated()` (both already emitted internally by the two
composed canonical services — no new audit call needed at the
Page Builder layer). Unknown `$blockType` (not in `componentTypes()`):
fail-closed 422 before any row is created.

## 18. Edit Block Contract

`PATCH /admin/page-builder/blocks/{section}` → same DRAFT + authorization
guards → `PageBuilderBlockService::updateBlockConfig($section, $config,
$actor)` → stale-edit check (§29) compares client-submitted
`expected_updated_at` against the Component's current `updated_at` inside
the same `lockForUpdate()` transaction already performed by
`ThemeComponentService::update()`.

## 19. Remove Block Contract

`DELETE /admin/page-builder/blocks/{section}` → same guards →
`PageBuilderBlockService::removeBlock($template, $section, $actor)`.
Confirmation dialog required client-side (destructive action, §33).
Cross-template ownership is verified (the Section must belong to the
Template in the URL) before delegating, mirroring the existing
cross-menu/cross-theme rejection pattern used throughout CR-001-C.

## 20. Enable/Disable Contract

`PATCH /admin/page-builder/blocks/{section}/visibility` → same guards →
`ThemeSectionService::update($section, ['visible' => $bool], $actor)`
called directly (no wrapper service needed — see §9.1/§9.3). This is the
existing, already-audited (`theme.section.updated`) mechanism; no new
column, no new audit event type.

## 21. Reorder Contract

`PUT /admin/page-builder/{template}/blocks/order` → same guards →
body carries the **full ordered list of Section ULIDs** currently placed
in the Template (matching `ThemeSectionService::reorder()`'s existing
exact-set contract) → `ThemeSectionService::reorder($template,
$orderedSectionIds, $actor)`, which already fails closed
(`reorder_set_mismatch`) if the client's set doesn't exactly match the
server's current placement set (protects against a stale client omitting
a block another operator just added). Legacy multi-component technical
Sections (§9.2) are included in the reorderable set (Section-level
reorder is safe regardless of how many Components a Section holds) but
are visually marked read-only for Configure.

## 22. Duplicate/Reuse Decision

- **Duplicate Block**: **Implemented** (`PageBuilderBlockService
  ::duplicateBlock()`, §9.3) — pure orchestration over existing
  `create()` calls, no new canonical service method, no schema change.
  Placed immediately after the source Block in the ordering.
- **Reuse (Reusable) Block**: **Implemented** via the existing
  `is_reusable` Section flag + `ThemeSectionService::placeReusable()`.
  Page Builder exposes an "Add Existing Reusable Block" picker (listing
  Sections on the same Theme with `is_reusable = true`) alongside "+ Add
  Block". A Block is only offered as reusable if the operator explicitly
  marks it so at creation (`is_reusable` defaults `false`, matching
  today's `createAndPlace()` default) — this is a deliberate, low-risk
  v1 default; making every new Block automatically reusable is not
  necessary and is explicitly not assumed.

## 23. DRAFT Lifecycle Enforcement

Every mutating Page Builder endpoint enforces, in this exact order
(matching `SiteDesignController`'s existing pattern precisely):

1. Resolve acting Principal.
2. Canonical `ThemePolicy::update($actor, $theme)` (or `::preview`/
   `::publish` for those specific actions) — permission + scope check.
   403 on failure.
3. `abort_if($theme->status !== 'DRAFT', 409, …)` — resource-state guard.
   409 on failure, evaluated strictly server-side; a hidden/removed
   frontend button is not a control.
4. Only then does the canonical service (`ThemeSectionService`/
   `ThemeComponentService` via `PageBuilderBlockService`) execute, itself
   wrapped in `DB::transaction()` + `lockForUpdate()`.

Direct HTTP calls bypassing the Vue UI (Postman, curl, a modified form)
hit the identical controller code path — there is no separate
"UI-only" enforcement layer.

## 24. Authorization

No new permission is introduced. Reused exactly as-is:

| Action | Permission (via `ThemePolicy`) |
|---|---|
| View Page Builder canvas list | `THEME_VIEW` |
| Add/Edit/Remove/Reorder/Enable/Disable a Block | `THEME_UPDATE` |
| Preview a DRAFT canvas | `THEME_PREVIEW` |
| Publish | `THEME_PUBLISH` |

No role-name check anywhere (`hasRole()` or equivalent is never used —
confirmed absent from `ThemePolicy`/`SiteDesignController` and will not
be introduced here). Super Admin receives no implicit bypass — every
Page Builder action still resolves through `AuthorizesUsingRbac` +
`ThemeScopeResolver` + `ScopeType::Organization`, identical to every
other Theme-domain action. Builder UI visibility (e.g. hiding the "Page
Builder" nav item from an unauthorized admin) is a UX convenience only
and grants no authority — the server-side checks above are authoritative
independent of what the sidebar shows.

If, during Qwen recon or Muse implementation, a genuinely new permission
need is discovered, it must be raised as its own Human Decision at that
time — none is invented here because none was found necessary.

## 25. Preview Boundary

D reuses the existing `ThemePolicy::preview()` gate and the existing
`SiteDesignController::preview()` route/contract exactly as CR-001-C built
it (`GET /admin/site-design/{theme}/preview`, 404 unless
`status === 'DRAFT'`). Page Builder's "Preview" button on a Template's
block-list screen simply links to that same existing route/page — **no
new preview route, no new preview controller action, no new preview Vue
page** is created by D. This satisfies "distinguish builder editor
preview from canonical public renderer responsibility": D's Preview is
literally CR-001-C's Preview, which itself renders the DRAFT aggregate
using the same component-render dispatch `PublicRenderer` uses for public
requests (read-only reuse, not a rewrite — `PublicRenderer.php` is not
modified).

## 26. PublicRenderer Boundary

READ-ONLY for CR-001-D, with zero exceptions. `PublicRenderer.php`
(`app/Services/Theme/PublicRenderer.php`) is CR-001-E's ownership per
`docs/ai-handoff/CR-001/EXECUTION-ROUTING-C-TO-J.md`. Any new
`ComponentConfigValidator` schema field approved under HD-CR001D-01 will
need a corresponding **CR-001-E** render-time change (e.g. rendering
`display_mode=carousel` differently, or rendering `body_html` for
`source=custom_html`) — this is explicitly listed as a CR-001-E handoff
item (§52), not implemented here.

## 27. Domain-backed Block Contract

Campaign Grid/Carousel, Program Grid, and Articles/News blocks store only
a presentation/query config (`content_kind`, `limit`, `order`,
`article_type`, `display_mode`) inside `theme_components.config`. At
render time (CR-001-E's existing, unmodified `PublicRenderer
::renderContentList()`), the actual Campaign/Program/Article records are
resolved live via the existing `CampaignProjectionResolver`/
`ProgramProjectionResolver` (IMP-007) or the existing CMS query path —
never duplicated into Theme tables. D does not implement or alter this
resolution; it only lets the operator configure the query parameters
already supported by the closed schema.

## 28. ZISWAF Block Boundary

The ZISWAF Services block (Implemented, §11) is **purely presentational**
— it renders a fixed set of operator-authored cards (label, icon/asset,
CTA destination) for Zakat/Infaq/Sedekah/Wakaf/Fidyah/Qurban. It contains
no business rule, no eligibility logic, no calculation. The existing
responsive IA lock (HD-CR001-04: desktop keeps Infaq/Sedekah distinct,
mobile consolidates into one ZISWAF entry) governs the **Navigation Menu**
item structure only (already implemented in CR-001-B/C via
`visible_desktop`/`visible_mobile`) — it does not constrain how many
ZISWAF service cards this Page Builder block presents on a Home/Page
canvas, since that is a different UI surface (a content block, not a nav
menu). CR-001-F owns the final public responsive rendering of both
surfaces.

## 29. Concurrency / Stale-Edit Protection

**No optimistic-lock version column exists** on `themes`,
`theme_templates`, `theme_sections`, or `theme_components` (verified
directly against all four migrations — only `created_at`/`updated_at`).
All four canonical Theme services rely exclusively on
`lockForUpdate()`-inside-`DB::transaction()` (pessimistic locking at
write time), which prevents lost updates under true concurrent writes but
gives the operator no "someone else already changed this" signal before
they submit.

**Minimum safe D behavior (no schema change):** the Page Builder edit
form loads a Block with its current `updated_at` timestamp and submits it
back as `expected_updated_at` on every mutating request
(add/edit/remove/reorder/visibility). Inside the same locked transaction
the canonical service already opens, `PageBuilderBlockService`/the
controller compares the row's **current** `updated_at` (post-lock) to the
client's `expected_updated_at`; a mismatch throws a
`ThemeValidationException('stale_edit', …)` → `409 Conflict` with a
"this was changed by someone else — reload to see the latest version"
message, without persisting the operator's change. This reuses an
existing column (`updated_at`), requires no migration, and does not
invent distributed locking — it is a thin, additive check layered on top
of the lock Laravel already takes. Reorder's existing exact-set
validation (§21) already provides an equivalent guarantee for the
ordering operation specifically.

## 30. Validation

| Case | Behavior |
|---|---|
| Unknown block type | FAIL CLOSED (422, before any row created) — `ComponentConfigValidator::assertValid()` |
| Unknown config field | Rejected — Laravel validation rules use no wildcard/`sometimes`-catch-all; unrecognized keys are dropped by `Validator::validated()` semantics, never persisted |
| Invalid canonical reference (bad route name, wrong content_kind, malformed ULID, unreachable external URL scheme) | FAIL CLOSED (422) — `NavigationDestinationValidator` |
| Cross-theme asset reference | FAIL CLOSED (422) — mirrors existing `SiteDesignCloneService`/`ThemeBrandingService` cross-theme rejection |
| Cross-theme/cross-template Section manipulation | FAIL CLOSED (422/404) — Section's `theme_id`/placement verified against the URL's Template before any mutation |
| Non-DRAFT target Theme | FAIL CLOSED (409) — §23 |
| Unauthorized operation | DENY (403) — §24 |
| Stale edit | FAIL CLOSED (409) — §29 |
| Oversized custom HTML (>200KB) | FAIL CLOSED (422) — existing `ContentSanitizer::MAX_BYTES` |
| Disallowed HTML element/attribute in custom content | FAIL CLOSED (422) — existing `ContentSanitizer` |

## 31. Audit Semantics

No new audit event type is introduced. Every Page Builder mutation
resolves to an existing, already-registered IMP-004 event, emitted by the
canonical service it delegates to:

| Page Builder action | Emitted event (unchanged) |
|---|---|
| Add Block (Section+Component create) | `theme.section.updated` (metadata `['created']`) + `theme.component.updated` (metadata `['created']`) |
| Edit Block config | `theme.component.updated` (metadata: `fields_changed`) |
| Remove Block | `theme.section.updated` (metadata `['removed']`, or `['deleted']` if the Section itself was deleted) |
| Enable/Disable Block | `theme.section.updated` (metadata `fields_changed: ['visible']`) |
| Reorder Blocks | `theme.section.updated` (metadata `['reordered']`) |
| Duplicate Block | Same as Add Block |

Adding a genuinely new `theme.*` event type is out of scope for D (IMP-004
event registration is itself a governed registry) — none is needed since
every operation maps onto an existing event.

## 32. Security Threat Model

| Threat | Mitigation |
|---|---|
| Arbitrary HTML/script injection | `ContentSanitizer` allowlist (§14); no new unsanitized `v-html` surface |
| Unsafe URLs (`javascript:`, `data:`) | `NavigationDestinationValidator` scheme allowlist (§16); `ContentSanitizer` href scheme allowlist |
| Cross-theme asset reference | Server-side theme-ownership check before every asset FK write |
| Cross-theme/cross-template Section manipulation | Ownership verified before every mutation (§30) |
| Non-DRAFT mutation | §23, server-enforced, fail-closed |
| Unauthorized builder access | §24, canonical RBAC, no bypass |
| IDOR (guessing another org's Theme/Section ULID) | `ThemeScopeResolver` Organization scope already denies cross-org resources at the policy layer |
| Mass assignment | Controllers pass an explicit whitelisted array to each service method, never `$request->all()` (matches existing `SiteDesignController` pattern) |
| Arbitrary block keys | Fail-closed unknown-type rejection (§30) |
| Unbounded block config | Laravel validation `max:` rules already present per type (e.g. `stats.items` capped at 8, `card_grid.cards` capped at 12); custom HTML capped at 200KB |
| Oversized rich content | §14/§30 |
| Stale updates | §29 |
| Reorder tampering (submitting an incomplete/foreign set) | Existing exact-set validation in `ThemeSectionService::reorder()` |
| Hidden-field bypass | Every field is explicitly validated server-side; nothing is trusted from a hidden form field alone |
| Direct HTTP mutation bypassing the Vue UI | Identical controller/service code path regardless of client (§23) |

## 33. Accessibility

- Every Block row action (Configure, Enable/Disable, Move Up, Move Down,
  Remove, Duplicate) is a semantic `<button>` with a visible text label
  and an `aria-label` restating the block's operator name (e.g. "Move
  Campaign Grid up").
- Reorder is available via **Move Up / Move Down** buttons as the
  baseline, keyboard-operable mechanism (Tab + Enter/Space) — this is a
  hard requirement carried over from the locked CR document §27, not an
  optional nicety.
- Drag-and-drop, if added at all, is an additive enhancement layered on
  top of the working Move Up/Down baseline, never a replacement for it.
- Enabled/disabled state is exposed via `aria-pressed`/a visible
  "Disabled" `StatusBadge` (reusing the existing `UI/StatusBadge.vue`
  component), not color alone.
- Remove and Duplicate require a confirmation step (native `confirm()`-
  equivalent modal, consistent with destructive-action conventions
  elsewhere in the admin UI) before the request is sent.
- Focus returns to a sensible location (the block list, focused on the
  affected row) after any action completes, so keyboard/screen-reader
  users are not stranded on a removed element.

## 34. Mobile Admin UX

The Page Builder canvas (block list + Move Up/Down + Configure) is
usable at mobile/tablet width without relying on precision drag gestures,
consistent with §33's keyboard/button-first baseline. The Configure
side-panel collapses to a full-screen modal below a standard tablet
breakpoint, matching the existing `AdminLayout.vue` responsive drawer
pattern already used for the sidebar.

## 35. Shared Hosting Compatibility

No new infrastructure requirement. Page Builder is server-rendered
Laravel + Inertia/Vue, compiled via the existing Vite build, served by
Apache/LiteSpeed/PHP/MySQL — identical operational profile to CR-001-C.
No Redis, no Supervisor/PM2, no WebSocket, no persistent Node process, no
Docker requirement introduced.

## 36. Performance

- The Block picker (`PageBuilderBlockRegistry::forCanvas()`) is a small,
  fixed, in-memory PHP array — no query cost.
- The CTA/Content-list destination pickers reuse existing paginated
  listing endpoints (`Cms\PageController`/`ArticleController` index
  queries) rather than loading entire domain datasets into a select box.
- A Template's block canvas loads at most the Sections/Components placed
  on that one Template (bounded by whatever an operator has actually
  added) — no unbounded query.
- No numeric cap on block count is invented here (e.g. "max 30 blocks per
  page") since no evidence of a required limit exists; if operational
  experience later shows a need for one, that is a product/business
  decision requiring its own Human Decision at that time, not invented
  now.

## 37. Transaction Boundaries

| Operation | Transaction |
|---|---|
| Add Block | One `DB::transaction()` in `PageBuilderBlockService::addBlock()`, itself calling `ThemeSectionService::createAndPlace()` (which opens its own nested transaction/savepoint) then `ThemeComponentService::create()` (same) — if either inner call throws, the whole operation rolls back |
| Edit Block | The existing `ThemeComponentService::update()` transaction, unchanged |
| Remove Block | The existing `ThemeSectionService::removeFromTemplate()` transaction, unchanged |
| Enable/Disable | The existing `ThemeSectionService::update()` transaction, unchanged |
| Reorder | The existing `ThemeSectionService::reorder()` transaction, unchanged |
| Duplicate Block | Same as Add Block |

No filesystem I/O is involved in any Page Builder operation (unlike
`SiteDesignCloneService`'s asset-copy compensation strategy), so no new
compensation logic is required.

## 38. Error Semantics

Every `ThemeValidationException` thrown by a composed canonical service
(or by `PageBuilderBlockService` itself for the two new codes
`not_a_single_block_section` and `stale_edit`) is caught by
`PageBuilderController` and re-thrown as Laravel's
`ValidationException::withMessages([...])`, exactly matching
`SiteDesignController`'s existing catch pattern — no new error-response
shape is introduced. `abort_if`/`abort_unless` calls (403/404/409) follow
the same convention already used throughout `SiteDesignController`.

## 39. Service/API Design

New service: `App\Services\Theme\PageBuilderBlockService` (§9.3). No
other new service. `PageBuilderBlockRegistry` (§10) is a plain read-only
metadata provider, not a mutating service, and needs no interface beyond
its two static-style query methods (`all()`, `forCanvas()`).

## 40. Controller Design

New controller: `App\Http\Controllers\Admin\PageBuilderController`
(single controller, thin, mirroring `SiteDesignController`'s
resolve-actor → authorize → guard-DRAFT → delegate → catch-exception
shape exactly):

```
index(Theme $theme)                                    // canvas picker (home/page/article)
show(ThemeTemplate $template)                           // block list for one canvas
store(ThemeTemplate $template, Request $request)         // add block
update(ThemeSection $section, Request $request)          // edit block config
destroy(ThemeTemplate $template, ThemeSection $section)   // remove block
duplicate(ThemeTemplate $template, ThemeSection $section) // duplicate block
setVisibility(ThemeSection $section, Request $request)    // enable/disable
reorder(ThemeTemplate $template, Request $request)        // reorder
placeReusable(ThemeTemplate $template, Request $request)  // add existing reusable block
```

## 41. Vue Page/Component Design

New files under `resources/js/Pages/Admin/PageBuilder/`:

- `Index.vue` — canvas picker for the DRAFT Theme (Home / Page / Article
  cards, matching `Overview.vue`'s `Card`/`PageHeader`/`Breadcrumb`
  component reuse).
- `Show.vue` — the block-list canvas for one `ThemeTemplate`: ordered
  rows (label, icon, enabled toggle, Move Up/Down, Configure, Duplicate,
  Remove), a persistent "+ Add Block" trigger opening a category-grouped
  picker sourced from `PageBuilderBlockRegistry::forCanvas()`, and a
  "Preview" link to the existing CR-001-C preview route (§25).

New shared component:

- `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue` — a
  single generic form host that renders the correct field set per block
  `type` (a `v-if`/`<component :is>` dispatch over the same 9(+ additive)
  closed types), reusing existing `UI/{Input,Select,Textarea,FormField,
  Button}.vue` primitives — no new form library.

All mutations use the existing plain `router.post/patch/delete()`
Inertia pattern already used by `Overview.vue` (no new state-management
library).

## 42. Route Design

New route group, additive, in `routes/web.php` (**SHARED file — see
§44**):

```php
Route::prefix('admin/page-builder')->name('page-builder.')->group(function () {
    Route::get('/{theme}', [PageBuilderController::class, 'index'])->name('index');
    Route::get('/templates/{template}', [PageBuilderController::class, 'show'])->name('show');
    Route::post('/templates/{template}/blocks', [PageBuilderController::class, 'store'])->name('blocks.store');
    Route::patch('/blocks/{section}', [PageBuilderController::class, 'update'])->name('blocks.update');
    Route::delete('/templates/{template}/blocks/{section}', [PageBuilderController::class, 'destroy'])->name('blocks.destroy');
    Route::post('/templates/{template}/blocks/{section}/duplicate', [PageBuilderController::class, 'duplicate'])->name('blocks.duplicate');
    Route::patch('/blocks/{section}/visibility', [PageBuilderController::class, 'setVisibility'])->name('blocks.visibility');
    Route::put('/templates/{template}/blocks/order', [PageBuilderController::class, 'reorder'])->name('blocks.reorder');
    Route::post('/templates/{template}/blocks/place-reusable', [PageBuilderController::class, 'placeReusable'])->name('blocks.place-reusable');
});
```

`{theme}`/`{template}`/`{section}` bound by ULID, never internal BIGINT
id, matching every existing Theme route.

## 43. File Ownership Map

**CREATE:**

- `app/Http/Controllers/Admin/PageBuilderController.php`
- `app/Services/Theme/PageBuilderBlockService.php`
- `app/Services/Theme/PageBuilderBlockRegistry.php`
- `resources/js/Pages/Admin/PageBuilder/Index.vue`
- `resources/js/Pages/Admin/PageBuilder/Show.vue`
- `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue`
- `tests/Feature/Theme/PageBuilderDraftOnlyTest.php`
- `tests/Feature/Theme/PageBuilderBlockServiceTest.php`
- `tests/Feature/Theme/PageBuilderControllerTest.php`
- `tests/Feature/Theme/PageBuilderReorderTest.php`
- `tests/Feature/Theme/PageBuilderLeastPrivilegeTest.php`
- `tests/Feature/Theme/PageBuilderConcurrencyTest.php`
- `tests/Feature/Theme/PageBuilderSafeContentTest.php` (HD-CR001D-01 APPROVED)

**MODIFY (HD-CR001D-01 APPROVED — additive/allowlisted/sanitized only):**

- `app/Services/Theme/ComponentConfigValidator.php` — additive optional
  fields on existing types only (`cta_button.intent` closed enum,
  `content_list.display_mode` closed enum, `rich_text.source=custom_html`
  + `body_html` routed through the existing `ContentSanitizer`). No
  existing field removed or made stricter; fully backward compatible
  with every existing persisted component config. `ComponentConfigValidator`
  remains the sole, authoritative validator — nothing in Page Builder
  bypasses it or duplicates its role. Per CHANGE-CONTROL.md, this closed-
  enum extension still requires its own ADR (mirroring `ADR-001-theme-
  content-projection-amendment.md`) recorded under `docs/adr/` before
  Muse implements it — the Human's HD-CR001D-01 approval authorizes this
  specification to proceed and authorizes that ADR to be raised; it does
  not itself constitute the ADR, and no ADR is created by this patch.

**SHARED / SELECTIVE:**

- `routes/web.php` (§44)

**NOT MODIFIED:** everything else, explicitly including
`app/Services/Theme/PublicRenderer.php`, `ThemeSectionService.php`,
`ThemeComponentService.php`, `ThemeTemplateService.php`,
`ThemeActivationService.php`, `ThemeNavigationService.php`,
`app/Http/Controllers/Theme/ThemeController.php`, and every CMS
controller/service.

## 44. Shared/Collision Files

`routes/web.php` is shared with FE-CHK-009's protected, still-dirty
worktree changes (per `git status` at the time of this specification —
authoritative status must be re-checked immediately before D's
implementation begins, not assumed from this document). Implementation
must use the same selective/hunk-level staging discipline CR-001-C's
finalization used (`git apply --cached` against a hand-built patch
containing only the new `admin/page-builder/*` hunk, or equivalent
`git add -p` review) — never a whole-file `git add routes/web.php`.

No other collision is anticipated: every other file D creates is new.

## 45. Schema Assessment

**Zero schema changes required for the D v1 scope defined in §11 as
Classification A.** HD-CR001D-01 (§54, APPROVED) authorizes only
additive `ComponentConfigValidator` field extensions, which are PHP-level
validation-rule changes, not database schema changes — no migration is
implied or required by HD-CR001D-01.

Two areas were evaluated and found to **not** require a schema change,
each documented here per the "STOP and identify" instruction rather than
silently assumed:

1. **Per-page block canvases** (§7): would require a schema change (a new
   join surface between `CmsPage`/`CmsArticle` and per-page Template/
   Section ownership) if pursued — **not pursued in this specification**;
   the shared-per-content-kind canvas model requires no schema change and
   matches the CR document's own locked KEEP/WRAP direction. Flagged as
   HD-CR001D-03 for explicit Human confirmation of this scope boundary,
   not because a schema gap must be filled now.
2. **Component-level enable/disable**: would require a new
   `theme_components.visible` (or similar) column if Blocks were mapped
   1:1 to bare Components without the Section wrapper — **not needed**,
   because §9.1's Section-wrapping mapping reuses the existing
   `theme_sections.visible` column instead. No migration proposed.

## 46. Migration Assessment

**Zero migrations.** No `database/migrations/*` file is created or
proposed by this specification.

## 47. Test Matrix

| # | Case | Test file |
|---|---|---|
| 1 | Authorized DRAFT builder access | `PageBuilderControllerTest` |
| 2 | Unauthorized builder access (403) | `PageBuilderLeastPrivilegeTest` |
| 3 | ACTIVE Theme mutation denied (409) | `PageBuilderDraftOnlyTest` |
| 4 | INACTIVE Theme mutation denied (409) | `PageBuilderDraftOnlyTest` |
| 5 | ARCHIVED Theme mutation denied (403/409, either fail-closed path accepted per CR-001-C precedent) | `PageBuilderDraftOnlyTest` |
| 6 | Add block | `PageBuilderBlockServiceTest` |
| 7 | Edit block | `PageBuilderBlockServiceTest` |
| 8 | Remove block | `PageBuilderBlockServiceTest` |
| 9 | Enable/disable | `PageBuilderControllerTest` |
| 10 | Reorder | `PageBuilderReorderTest` |
| 11 | Persisted ordering (re-fetch confirms) | `PageBuilderReorderTest` |
| 12 | Invalid block type rejected | `PageBuilderBlockServiceTest` |
| 13 | Invalid config rejected | `PageBuilderBlockServiceTest` |
| 14 | Cross-theme Section manipulation rejected | `PageBuilderLeastPrivilegeTest` |
| 15 | Cross-theme asset reference rejected | `PageBuilderBlockServiceTest` |
| 16 | Source ACTIVE Theme unchanged after DRAFT edits | `PageBuilderBlockServiceTest` |
| 17 | DRAFT mutation succeeds | `PageBuilderControllerTest` |
| 18 | Reusable-block semantics preserved (`placeReusable`) | `PageBuilderBlockServiceTest` |
| 19 | Transaction rollback (Component create fails after Section create) | `PageBuilderBlockServiceTest` |
| 20 | Stale/concurrent edit protection (409) | `PageBuilderConcurrencyTest` |
| 21 | Safe content sanitization (HD-CR001D-01 APPROVED) | `PageBuilderSafeContentTest` |
| 22 | Safe CTA destination validation | `PageBuilderBlockServiceTest` |
| 23 | Home canvas mapping | `PageBuilderControllerTest` |
| 24 | Generic CMS Page canvas mapping (shared-template, §7) | `PageBuilderControllerTest` |
| 25 | Preview contract (links to existing CR-001-C preview, no new render logic) | `PageBuilderControllerTest` |
| 26 | Mobile/accessibility fallback contract (Move Up/Down present, keyboard-operable) | `PageBuilderReadOnlyTemplateTest`-style SFC-template assertion, matching CR-001-C's `SiteDesignReadOnlyTemplateTest` precedent (no Vue component-test framework exists in this repo — same limitation CR-001-C recorded) |
| 27 | No `PublicRenderer` modification | Verified by code review / diff check, not a runtime test |
| 28 | Schema/migration expectation (0/0) | Verified by `git diff --check` + migration count in finalization evidence, not a runtime test |
| 29 | FE-CHK-009 preservation | Verified by `git status` at finalization time (same discipline as CR-001-C), not a runtime test |

## 48. MySQL-specific Tests

**None required.** All Page Builder concurrency protection is either
(a) `lockForUpdate()`-inside-transaction, already exercised correctly
under SQLite by the existing Theme test suite, or (b) the additive
`updated_at`-comparison stale-edit check (§29), which is pure application
logic requiring no MySQL-specific locking primitive. This mirrors
CR-001-C's own recorded position ("MySQL NOT REQUIRED FOR C CLOSURE").

## 49. Frontend Progress Checkpoint

CR-001-D is **admin/editor tooling only**. No public-facing surface is
added or changed.

- **FRONTEND STATUS**: Admin-only; public frontend unaffected.
- **HOME STATUS**: Admin editing capability added (block canvas); public
  Home rendering unchanged (owned by unmodified `PublicRenderer`).
- **HEADER / FOOTER**: Unaffected — owned by CR-001-C Menus/Branding
  (Navigation `navigation_menu_slot` block is not re-exposed by D, §11).
- **NEW USER-FACING UI**: None (no public UI change).
- **NEW ADMIN UI**: `Admin/PageBuilder/{Index,Show}.vue` +
  `BlockConfigPanel.vue`.
- **MOBILE CHECK / DESKTOP CHECK**: Applies to the new **admin** Page
  Builder screens only (§34); public mobile/desktop rendering is CR-001-F
  scope, explicitly deferred.
- **BUILD STATUS / TYPE-CHECK STATUS**: To be recorded at implementation
  time (`npm run build`, `npm run type-check`), following the identical
  gate CR-001-C used.
- **SCREENSHOT/EVIDENCE**: To be captured at implementation time for the
  new admin screens only.
- **DEFERRED UI**: All public rendering of any new/changed block
  configuration (Carousel display mode, Safe Custom Content HTML,
  Testimonials/FAQ/Contact/Video once designed) is explicitly deferred to
  CR-001-E.

## 50. FE-CHK-009 Preservation

Implementation must not touch any file in the FE-CHK-009 protected list
(`PublicCampaignController.php`, `PublicDonationController.php`,
`PublicPaymentController.php`, `PublicProgramController.php`,
`PublicRenderer.php`, public Vue pages/components, `DonationHttpTest.php`,
`PaymentHttpTest.php`, and any other file the *current* `git status`
shows as dirty and unrelated to Page Builder at implementation time).
`routes/web.php` staging must follow §44's selective discipline.

## 51. CODEX-FE009-01 Boundary

Unaffected and untouched. Remains **OPEN / VALID / UNRESOLVED**, owner
**CR-001-J**. D touches no file in the affected set
(`PublicDonationController.php`, `PublicPaymentController.php`,
`DonationService.php`, `DonationHttpTest.php`, `PaymentHttpTest.php`).

## 52. CR-001-E Handoff Contract

CR-001-E must, when it integrates:

1. Render `content_list`/`card_grid` blocks with `display_mode='carousel'`
   using carousel markup instead of grid markup (same resolved dataset).
2. Render `rich_text` blocks with `source='custom_html'` by outputting
   the already-sanitized `config['body_html']` (no additional
   sanitization needed at render time — it was sanitized at save time by
   `ContentSanitizer`, and `ContentSanitizer` is documented as idempotent).
3. Render `cta_button.intent` as a styling/icon hint only — it carries no
   routing or business-logic meaning.
4. Continue rendering every block type D does not add — no interface
   change to `PublicRenderer::renderForContentKind()`'s existing
   `match($component->type)` dispatch is required by D beyond adding the
   two new `content_list -> carousel` and `rich_text -> custom_html`
   arms once HD-CR001D-01 is approved and implemented.

## 53. Deferred to F/G/H/I/J

| Item | Deferred to |
|---|---|
| Responsive public rendering of any block (mobile/desktop layout) | CR-001-F |
| Zakat/Fidyah Calculator business logic behind the Zakat Calculator CTA | CR-001-G |
| Admin IA shell reorganization (`WEBSITE` sidebar grouping from CR doc §24) | CR-001-H |
| Any cross-phase UX consolidation | CR-001-I |
| `CODEX-FE009-01` remediation | CR-001-J |
| Testimonials / FAQ / Contact / Impact blocks | A later CR-001-D remediation round or a later phase, once their own schema/config contracts are designed |
| Video block | **Excluded from CR-001-D entirely per HD-CR001D-02 (DEFER, resolved).** No self-hosted behavior, no iframe, no external oEmbed, no provider allowlist, no new CSP/security policy is implemented, and no Video entry appears in `PageBuilderBlockRegistry`. Operators wanting to link to video use the existing, already-Implemented CTA Button block pointing to an `EXTERNAL_URL`. Any future Video capability requires its own new governed mechanism/decision at that time — this specification proposes none |

## 54. Open Human Decisions

**OPEN HUMAN DECISIONS: 0.** All three Human Decisions raised by the
original specification have been resolved by explicit Human authority.
No decision remains open or blocking for the Human Spec Gate.

### HD-CR001D-01 — Additive `ComponentConfigValidator` schema extensions

**STATUS: RESOLVED — APPROVED (additive / allowlisted / sanitized).**

**Decision**: The three additive, backward-compatible field extensions to
the existing closed component-type schemas are approved: `content_list.
display_mode` (`grid`\|`carousel`, default `grid`), `cta_button.intent`
(closed enum `in:general,donation,zakat`, presentation label only — §12.1),
and `rich_text.source='custom_html'` + `body_html` (sanitized via the
existing `ContentSanitizer`, no new library — §14).

**Human-mandated constraints on this approval** (binding, carried through
to §11/§12.1/§14/§43):

- Additive only — no existing field removed, renamed, or made stricter.
- The existing `ComponentConfigValidator` remains the sole authoritative
  validator; nothing in Page Builder bypasses or duplicates it.
- Server-side validation is mandatory for every field (no client-only
  enforcement).
- Configuration values are allowlisted/closed-enum only — no arbitrary
  executable configuration of any kind.
- `cta_button.intent` MUST be an explicit allowed enum value (§12.1) —
  never a free-form operator-typed string.
- Safe Custom Content MUST reuse the existing `ContentSanitizer` — no new
  sanitizer or library unless separately authorized in the future.
- No arbitrary script, JavaScript, event handlers, PHP, Blade, Vue, SQL,
  or arbitrary `<iframe>` is permitted anywhere in this contract (§14.1
  documents exactly how the reused sanitizer already enforces every one
  of these).
- No database schema change, no migration (§45–§46 unaffected).

**ADR requirement (documented, not created by this patch)**: Per
CHANGE-CONTROL.md, extending the closed `ComponentConfigValidator::SCHEMAS`
set — even additively — is a Locked-Architecture-level change requiring
its own ADR, exactly as `ADR-001-theme-content-projection-amendment.md`
did for its own additive `content_kind` extension. **This Human Decision
authorizes that ADR to be raised and gives it a pre-approved content
scope; it does not itself constitute the ADR.** Implementation (Muse) must
not write the `ComponentConfigValidator` changes until the corresponding
ADR is recorded under `docs/adr/` following the existing template and
process — this specification creates no such ADR file, per the
instruction not to make an unapproved architecture change here.

**Implementation status**: UNBLOCKED for specification purposes; the
governance ADR step remains a prerequisite at implementation time (§57).

### HD-CR001D-02 — Video block embed mechanism

**STATUS: RESOLVED — DEFER. No Video block in CR-001-D.**

**Decision**: CR-001-D does not implement any Video block behavior —
no self-hosted `ThemeAsset`-backed video, no arbitrary iframe embed, no
external oEmbed integration, no provider domain allowlist, and no new
video-related security/CSP policy. No Video entry is registered in
`PageBuilderBlockRegistry`, and no partial or placeholder Video block is
exposed to operators as if it were functional (§11, §53). Operators
wanting to reference video content in this phase use the existing,
already-Implemented CTA Button block pointing to an `EXTERNAL_URL`.

**Future path**: Any future Video capability requires its own new,
separately governed mechanism and its own decision at that time — this
specification proposes none and reserves no interim scaffolding for it.

**Implementation status**: RESOLVED — no further action needed in D.

### HD-CR001D-03 — Per-Page vs. shared-per-content-kind canvas scope

**STATUS: RESOLVED — APPROVED. Shared-per-content-kind canvas, locked.**

**Decision**: CR-001-D's Page Builder uses the existing canonical
`ThemeTemplate` model exactly as described in §7: each Theme has exactly
three shared Visual Page Builder canvases — **Home**, **Page**, and
**Article** — corresponding to the theme's `home`/`page`/`article`
`ThemeTemplate` rows. The builder does **not** create per-individual-
`CmsPage` or per-individual-`CmsArticle` layout canvases. Individual
`CmsPage`/`CmsArticle` content (title, body/content, SEO, and all other
existing canonical CMS fields) remains owned entirely by IMP-005; the
Page Builder owns only the presentation composition (Blocks) surrounding
that canonical content. No schema change, no migration.

**Future path**: Any future per-page/per-article unique layout capability
is explicitly out of CR-001-D's scope and requires its own separate,
governed change-control process / architecture decision (an ACR under
CHANGE-CONTROL.md, per §45's schema-gap analysis) before any such
capability may be designed or built.

**Implementation status**: RESOLVED — this specification proceeds exactly
as written in §7–§9 with no further confirmation required.

## 55. Acceptance Criteria

- [ ] All Classification-A blocks not gated by HD-CR001D-01 are
      addable/editable/removable/reorderable/enable-disable-able through
      the new admin UI, with zero schema changes.
- [ ] All mutations are DRAFT-only, server-enforced (§23), verified by
      negative-path tests (§47 #3–#5).
- [ ] No new permission introduced; all authorization reuses `THEME_VIEW/
      UPDATE/PREVIEW/PUBLISH` (§24).
- [ ] `PublicRenderer.php` has zero diff.
- [ ] Zero migrations, zero schema changes (§45–§46).
- [ ] FE-CHK-009 protected files have zero diff; `routes/web.php` change
      is limited to the new `admin/page-builder/*` group.
- [ ] Full existing Theme regression suite (146 tests at CR-001-C
      baseline) plus all new Page Builder tests (§47) pass.
- [ ] `npm run type-check`, `npm run build`, `vendor/bin/pint --test`,
      `composer audit`, `git diff --check` all PASS.
- [ ] Preview reuses the existing CR-001-C preview route with no new
      render logic (§25).
- [x] Every Human Decision in §54 is resolved (HD-CR001D-01 APPROVED,
      HD-CR001D-02 DEFERRED, HD-CR001D-03 APPROVED) — 0 open.

## 56. Definition of Done

Matches AGENTS.md's global Completion criteria: code implemented, tests
pass, lint/static checks pass, documentation updated (this spec kept
current; a Recon file produced by Qwen; an Evidence file produced during
implementation; a Finalization file produced at closure — the same
four-document trail CR-001-C used), implementation spec acceptance
criteria (§55) satisfied, no architecture regression (verified against
§5's locked-contract table).

## 57. Implementation Sequence

Per `docs/ai-handoff/CR-001/EXECUTION-ROUTING-C-TO-J.md`'s standard C–J
flow, applied to D:

1. This document, with all §54 Human Decisions resolved → **Human Spec/
   Architecture Gate**.
2. Governance ADR raised for the HD-CR001D-01 additive
   `ComponentConfigValidator` extensions (mirroring `ADR-001-theme-
   content-projection-amendment.md`), recorded under `docs/adr/` — a
   prerequisite for step 5, not for the Spec Gate itself.
3. Qwen — Governed Recon / File Map (`D-RECON.md`, not created here per
   the "Recon Discipline" instruction).
3. Claude — Consolidated Recon Review.
4. Human — Recon Gate.
5. Muse — Implementation (per §43's file map), scoped strictly to
   whichever blocks HD-CR001D-01/02 actually approved.
6. Automated tests (§47).
7. DeepSeek — Diff-first review.
8. Bundled remediation (only if required).
9. Full regression.
10. Codex — Final semantic/closure audit.
11. Human — Phase Gate.
12. Finalization / selective commit / push, following the exact
    selective-staging discipline `docs/audits/CR-001-C-FINALIZATION.md`
    established for the shared `routes/web.php` file.

## 58. Rollback/Failure Safety

Every Page Builder mutation is transactional (§37); a failed request
leaves the DRAFT Theme's Section/Component tree exactly as it was before
the request (no partial Block, no orphaned Section). Because all edits
target only the DRAFT Theme, the ACTIVE Theme (the live public site) is
never at risk regardless of any Page Builder defect — the existing
copy-on-write isolation invariant (§5) already provides this safety net
independent of anything D adds.

## 59. Evidence Requirements

Implementation must produce, mirroring CR-001-C's own evidence trail:

- `docs/ai-handoff/CR-001/D-RECON.md` (Qwen, after Human Spec Gate).
- `docs/ai-handoff/CR-001/D-EVIDENCE.md` (Muse, during/after
  implementation — files created/modified, test results, verification
  table, boundary confirmation — same shape as `C-EVIDENCE.md`).
- `docs/audits/CR-001-D-FINALIZATION.md` (at Human Phase Gate, same shape
  as `docs/audits/CR-001-C-FINALIZATION.md`).

## 60. Final Specification Verdict

CR-001-D SPECIFICATION HUMAN DECISIONS RESOLVED —
READY FOR HUMAN SPEC GATE

HD-CR001D-01: APPROVED (additive / allowlisted / sanitized).
HD-CR001D-02: DEFERRED (no Video block in CR-001-D).
HD-CR001D-03: APPROVED (shared Home/Page/Article canvas, locked).
Open Human Decisions: 0.
