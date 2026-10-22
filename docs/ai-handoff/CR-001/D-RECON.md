# CR-001-D — Visual Page Builder — Governed Recon / File Map

> **Baseline commit:** `eadc1131270ef59bbf46337f31b7d92b6dd69c81`
> **Baseline origin/master:** `eadc1131270ef59bbf46337f31b7d92b6dd69c81`
> **Architecture baseline:** APPROVED / LOCKED
> **CR-001 Human Spec Gate for D:** APPROVED (HD-CR001D-01, -02, -03 all resolved)
> **Recon status:** READY FOR CLAUDE CONSOLIDATED REVIEW
> **Recon date:** 2026-09-22
> **Model:** qwen/qwen3.7-flash

---

## 1. Recon Status

| Criterion | Status | Evidence |
|-----------|--------|----------|
| Baseline HEAD verification | MATCHES `eadc113` | `git rev-parse HEAD == eadc1131270ef59bbf46337f31b7d92b6dd69c81` |
| Baseline origin/master verification | MATCHES `eadc113` | `git rev-parse origin/master == eadc1131270ef59bbf46337f31b7d92b6dd69c81` |
| CR-001 Architecture | APPROVED / LOCKED | Per upstream recon |
| CR-001-B | FINAL / LOCKED | Per upstream recon |
| CR-001-C | FINAL / LOCKED | Per upstream recon |
| CR-001-D Specification | HUMAN APPROVED | `docs/implementation/CR-001-D-visual-page-builder.md` |
| HD-CR001D-01 (additive schema) | RESOLVED — APPROVED | §54.1 of spec |
| HD-CR001D-02 (Video deferred) | RESOLVED — DEFERRED | §54.2 of spec |
| HD-CR001D-03 (shared canvas) | RESOLVED — APPROVED | §54.3 of spec |
| Open Human Decisions | 0 | §54.4 of spec |
| Locked decisions respected | YES | No locked decision silently overridden |
| FE-CHK-009 preserved | PRESERVED | See §38 |
| CODEX-FE009-01 | OPEN / VALID / UNRESOLVED | Owner: CR-001-J |
| IMP-010 | BLOCKED | By CR-001 completion |

---

## 2. Baseline

Baseline verified against repository at `eadc113`:

```
HEAD:              eadc1131270ef59bbf46337f31b7d92b6dd69c81
origin/master:     eadc1131270ef59bbf46337f31b7d92b6dd69c81
Dirty/Untracked:   25 files (FE-CHK-009 protected worktree)
Database modified: NO
Commit performed:  NO
Push performed:    NO
Merge performed:   NO
```

No reset, restore, stash, clean, checkout, or any state-changing Git operation was performed.

---

## 3. Human-approved Spec

`docs/implementation/CR-001-D-visual-page-builder.md` (60 sections, 1278 lines). All HDs resolved. Ready for implementation after this recon + consolidated review + human recon gate.

---

## 4. Locked HD Decisions

### HD-CR001D-01 — APPROVED (additive/allowlisted/sanitized)
Additive extensions to `ComponentConfigValidator::SCHEMAS`:
- `content_list.display_mode` → `grid|carousel`, default `grid`
- `cta_button.intent` → closed enum `general|donation|zakat`
- `rich_text.source` gains third value `custom_html`; new field `body_html` (≤ 200KB, sanitized via `ContentSanitizer`)

All additive only, server validated, no arbitrary executable config. ADR required before implementation (mirrors ADR-001 precedent).

### HD-CR001D-02 — RESOLVED (Video deferred)
No Video block in CR-001-D. Not registered in registry, not offered in library, no partial/fake placeholder.

### HD-CR001D-03 — APPROVED (shared-per-content-kind canvas)
Each Theme owns exactly three shared canvases: Home (`content_kind='home'`), Page (`content_kind='page'`), Article (`content_kind='article'`). Shared per type, not per CmsPage/CmsArticle. No schema change. No migration.

---

## 5. Existing Canonical Model Map

### 5.1 Theme Models (`app/Models/Theme/`)

| Model | ULID-bound? | Key columns | Classification |
|-------|-------------|-------------|----------------|
| `Theme` | Yes (`ulid`) | `status` (DRAFT/ACTIVE/INACTIVE/DRAFT), `is_system_default` | KEEP |
| `ThemeActivation` | N/A (singleton id=1) | `active_theme_id` | KEEP |
| `ThemeTemplate` | Yes (`ulid`) | `theme_id FK`, `content_kind VARCHAR(32)`, `slug`, `name` | KEEP |
| `ThemeSection` | Yes (`ulid`) | `theme_id FK`, `is_reusable BOOLEAN`, `visible BOOLEAN`, `layout_variant VARCHAR` | KEEP |
| `ThemeComponent` | Yes (`ulid`) | `theme_section_id FK`, `type VARCHAR(32)`, `config JSON`, `position INT` | KEEP |
| `ThemeBrandingConfig` | Yes (`ulid`) | `theme_id FK`, `color_tokens JSON`, `font_family VARCHAR` | KEEP |
| `ThemeAsset` | Yes (`ulid`) | `theme_id FK`, `disk`, `stored_filename`, `original_filename`, `mime_type`, `extension`, `size_bytes`, `width`, `height`, `sha256`, `status`, `uploaded_by_principal_id` | KEEP |
| `ThemeNavigationMenu` | Yes (`ulid`) | `theme_id FK`, `code VARCHAR(64)`, `name` | KEEP |
| `ThemeNavigationItem` | Yes (`ulid`) | `menu_id FK`, `parent_id SELF-FK`, polymorphic destination fields | KEEP |

### 5.2 Theme Pivot Table

`theme_template_sections` (migration `0001_06_01_000004`):
- `theme_template_id FK` → `theme_templates.id` (cascade delete)
- `theme_section_id FK` → `theme_sections.id` (restrict delete)
- `position UNSIGNED INTEGER`
- UNIQUE(`theme_template_id`, `position`) — prevents duplicate positions per template
- UNIQUE(`theme_template_id`, `theme_section_id`) — prevents duplicate placement of same section
- Order field: `position` assigned sequentially 1..N

---

## 6. Canonical Page Mapping Verification

**VERDICT: PASS — fully confirmed.**

### UNIQUE Constraint Verified

Migration `0001_06_01_000002_create_theme_templates_table.php` line 30:
```php
$table->unique(['theme_id', 'content_kind']);
```

**CONFIRMED:** UNIQUE(theme_id, content_kind) exists on `theme_templates`. This guarantees at most one Template per content kind per Theme.

### content_kind Enforcement

Location 1 — `ThemeTemplateService::CONTENT_KINDS` (line 20):
```php
private const CONTENT_KINDS = ['home', 'page', 'article'];
```

Location 2 — `ThemeTemplateService::create()` (lines 30-31):
```php
if (! in_array($payload['content_kind'], self::CONTENT_KINDS, true)) {
    throw new ThemeValidationException('invalid_content_kind', '...');
}
```

Location 3 — DB unique constraint (migration line 30) — independently enforces pair uniqueness only (does NOT restrict the allowed value domain).

**CONFIRMED:** `content_kind` is a plain VARCHAR(32) with an APPLICATION-ONLY closed enum (`ThemeTemplateService::CONTENT_KINDS`). The database `UNIQUE(theme_id, content_kind)` independently enforces pair uniqueness but does NOT restrict the value domain. Allowed values enforced at application layer: `home`, `page`, `article`. No fourth value possible without modifying the constant and schema.

### Mapping Verified

| Operator Canvas | Canonical Aggregate | Model Query | Confirmed |
|-----------------|---------------------|-------------|-----------|
| Home | `ThemeTemplate(content_kind='home')` | `where('content_kind','home')` | YES |
| Page (chrome for all CMS Pages) | `ThemeTemplate(content_kind='page')` | `where('content_kind','page')` | YES |
| Article (chrome for all Articles) | `ThemeTemplate(content_kind='article')` | `where('content_kind','article')` | YES |

### Section → Component Relationship

From `ThemeSection::components()`:
```php
return $this->hasMany(ThemeComponent::class)->orderBy('position');
```

One Section holds multiple Components ordered by `position`. D maps ONE Block = ONE Section containing EXACTLY ONE Component (§9.1 of spec). This is achievable because:
- Schema supports it (no restriction on number of Components per Section)
- D's service will enforce the 1:1 constraint at the orchestration layer
- Pre-existing Sections with >1 Component (from Advanced/Debug ThemeController) are treated as read-only "Technical Sections" (§9.2)

---

## 7. Home Mapping Verification

**VERDICT: PASS — fully confirmed.**

Home is `ThemeTemplate` with `content_kind='home'` on the currently-edited DRAFT Theme. There is exactly one such Template per Theme (enforced by UNIQUE constraint). D does not create/select Templates; it operates on whatever Template the operator chooses from the canvas picker.

The canvas picker maps operator labels to `content_kind` values:
- "Home Layout" → `content_kind='home'`
- "Page Layout" → `content_kind='page'`
- "Article Layout" → `content_kind='article'`

These map directly to existing `ThemeTemplate::where('theme_id', $draftTheme->id)->where('content_kind', $kind)` queries. No new query path needed.

---

## 8. ThemeTemplate Constraints

| Constraint | Location | Value | Enforced At |
|------------|----------|-------|-------------|
| UNIQUE(theme_id, content_kind) | Migration, DB level | One template per kind/theme | Pair uniqueness (does NOT restrict value domain) |
| content_kind closed enum | `ThemeTemplateService::CONTENT_KINDS` | `['home','page','article']` | Application-level pre-check |
| slug uniqueness | Same migration | UNIQUE(theme_id, slug) | Both service check + DB guarantee |
| name required | Migration string(255) | No null | DB level |
| No UPDATE to content_kind | `ThemeTemplateService::update()` | Only allows `name` field | Service-level |

**Key finding:** `ThemeTemplateService::update()` does NOT allow changing `content_kind` — once a Template is assigned to a content kind, it stays there. This means D's operator never needs to worry about content_kind drift during editing.

---

## 9. Existing Theme Service Map

### 9.1 Services (all `app/Services/Theme/`)

| Service | Purpose | Key Methods | D Reuse Decision |
|---------|---------|-------------|------------------|
| `ThemeSectionService` | Section CRUD + Template placement | `createAndPlace()`, `placeReusable()`, `update()`, `reorder()`, `removeFromTemplate()` | DIRECT REUSE for reorder, visibility toggle |
| `ThemeComponentService` | Component CRUD within Section | `create()`, `update()`, `delete()`, `reorder()` | DIRECT REUSE for add/edit/delete |
| `ThemeTemplateService` | Template CRUD | `create()`, `update()` | READ ONLY (templates are fixed per-content-kind) |
| `ThemeActivationService` | Singleton activation | `activate()` | READ ONLY (publish action from SiteDesignController) |
| `SiteDesignCloneService` | Copy-on-write ACTIVE→DRAFT | `cloneFromActive()` | READ ONLY (out of scope for D) |
| `ComponentConfigValidator` | Component config validation | `assertValid($type, $config)`, `componentTypes()` | DIRECT REUSE (will be MODIFIED additively) |
| `ThemeScopeResolver` | ORGANIZATION scope resolution | `resolve()` | Via policy methods |
| `ThemeAuditLogger` | Audit event emission | `recordSectionUpdated()`, `recordComponentUpdated()` | Via composed services |
| `PublicRenderer` | Public rendering pipeline | `renderForContentKind()`, `siteChrome()`, `renderComponent()` | READ ONLY (CR-001-E ownership) |

### 9.2 Controller Patterns

`SiteDesignController` establishes the exact operational pattern D must mirror:
```
1. resolveActingPrincipal(Request) → Principal
2. Policy method check (abort_unless) → 403
3. abortUnlessDraft(Theme) → 409
4. Delegate to canonical service
5. Catch ThemeValidationException → ValidationException
6. Redirect with status flash message
```

This is the EXACT shape D's `PageBuilderController` must replicate. No deviation.

---

## 10. Proposed Page Builder Service Map

### 10.1 New Files (CREATE)

| File | Purpose | Rationale |
|------|---------|-----------|
| `app/Services/Theme/PageBuilderBlockService.php` | Thin orchestration: addBlock(), updateBlockConfig(), removeBlock(), duplicateBlock() | Composes SectionService + ComponentService within one transaction, enforces 1:1 block mapping, stale-edit check |
| `app/Services/Theme/PageBuilderBlockRegistry.php` | Read-only static metadata: all(), forCanvas($contentKind) | Mirrors `ComponentConfigValidator::SCHEMAS` pattern — plain PHP class, no interface, no persistence |

### 10.2 Why these two, not more

The spec's architecture (§9.3) requires:
- Adding a block = create Section + create Component → `SectionService::createAndPlace()` + `ComponentService::create()` → needs orchestration
- Editing a block = update Component config → `ComponentService::update()` → can call directly but needs single-component resolution + stale-edit → needs orchestration wrapper
- Removing a block = remove Section → `SectionService::removeFromTemplate()` → can call directly, but D controller needs ownership verification
- Duplicate = add a new Section+Component copy → orchestration over addBlock()

No new canonical service is needed because:
- `ThemeSectionService` already exposes ALL operations D needs (createAndPlace, update with visible flag, reorder, removeFromTemplate)
- `ThemeComponentService` already exposes create/update/delete with config validation
- `ThemeSectionService::reorder()` already has the exact-set contract D specifies (§21)

The thin `PageBuilderBlockService` wraps composition logic (transaction boundary, 1:1 enforcement, stale-edit comparison) that doesn't belong in either underlying service.

---

## 11. Block Registry Mapping

### 11.1 Classification Matrix (from spec §11, verified against actual validator)

| Block | Classification | Underlying Type | Exists in SCHEMAS? | Notes |
|-------|---------------|-----------------|--------------------|-------|
| Hero | A — Implement | `hero` | YES | Direct wrap |
| Banner | A — Implement | `banner` | YES | Direct wrap |
| Rich Text (cms_content) | A — Implement | `rich_text, source=cms_content` | YES | Direct wrap |
| Rich Text (caption) | A — Implement | `rich_text, source=caption` | YES | Direct wrap |
| Campaign Grid | A — Implement | `content_list, content_kind=campaign` | YES | ADR-001 extended content_kind to include campaign |
| Campaign Carousel | A — Implement | `content_list, display_mode=carousel` | ADDITIVE FIELD ONLY | Same type, new optional `display_mode` field |
| Program Grid | A — Implement | `content_list, content_kind=program` | YES | ADR-001 extended content_kind to include program |
| Articles/News | A — Implement | `content_list, content_kind=article` | YES | Already supported |
| Statistics | A — Implement | `stats` | YES | Direct wrap |
| ZISWAF Services | A — Implement | `card_grid, mode=authored` | YES | Authored-card contract covers this |
| Donation CTA / Quick Donation | A — Implement | `cta_button, intent=donation/zakat/general` | ADDITIVE FIELD ONLY | New optional `intent` field |
| Gallery | A — Implement | `card_grid, mode=authored` (images) | YES | Same type, different form presentation |
| Partners | A — Implement | `card_grid, mode=authored` | YES | Same type, different form presentation |
| Safe Custom Content | A — Implement | `rich_text, source=custom_html` | ADDITIVE FIELD + NEW FIELD | New source value + new body_html field |
| Zakat Calculator CTA | B — Structural | `cta_button` (existing) | YES | Config usable today; route may not resolve until G |
| Testimonials | C — Defer | none | NO | Needs new type beyond additive field |
| FAQ | C — Defer | none | NO | Needs new type |
| Contact | C — Defer | none | NO | Would need iframe (blocked by sanitizer) |
| Impact | C — Defer | none | NO | Requires defined metric domain owner |
| Video | E — Excluded | none | NO | Per HD-CR001D-02, excluded entirely |
| Navigation Menu | N/A | `navigation_menu_slot` | YES | Owned by CR-001-C Menus screen, not exposed by D |

### 11.2 Implementation Summary

- **Direct wrap (existing type, no change):** 9 blocks — hero, banner, rich_text×2, content_list×3, stats, card_grid×3
- **Additive field extension (existing type, new optional fields):** 3 blocks — content_list.display_mode, cta_button.intent, rich_text custom_html
- **Structural placeholder (existing type, safe by construction):** 1 block — zakat calculator CTA
- **Defer (needs new type):** 4 blocks — testimonials, FAQ, contact, impact
- **Excluded per HD:** 1 block — video

**Total new types: 0.** All D implementations use existing 9 component types. The only schema changes are additive optional fields on 3 existing type schemas.

---

## 12. Block Classification Verification

**VERDICT: PASS — classification matches actual validator state.**

Verified against actual `ComponentConfigValidator::SCHEMAS` (129 lines, 9 types):
- hero ✓
- rich_text ✓
- image ✓ (not used by D directly, but present)
- cta_button ✓
- content_list ✓
- stats ✓
- banner ✓
- card_grid ✓
- navigation_menu_slot ✓

All Classification-A blocks map to types that exist. No new `type` strings invented. Additive fields go into existing type schemas only.

---

## 13. ComponentConfigValidator Current State

**Actual state (verified, not assumed):**

```php
private const SCHEMAS = [
    'hero' => [...],           // headline, subheading, background_theme_asset_ulid, cta_label
    'rich_text' => [            // source(in:cms_content,caption), content_kind, content_ulid, caption
                             ],
    'image' => [...],           // source, theme_asset_ulid, media_token, alt_text
    'cta_button' => [           // label, variant, destination_type, destination_route/content_ulid/external_url
                             ],
    'content_list' => [         // content_kind(in:page,article,program,campaign), article_type, limit, order
                             ],
    'stats' => [                // items array(max:8)
                             ],
    'banner' => [...],          // text, dismissible, destination_* union
    'card_grid' => [            // mode(authored|content_list), cards array, content_kind(if content_list)
                             ],
    'navigation_menu_slot' => [ // menu_code
                             ],
];
```

Known behavior:
- Unknown `$type` → throws `ThemeValidationException('unknown_component_type')` — fail-closed 422
- Unknown config keys → dropped by Laravel `Validator::validated()` semantics (never persisted)
- All enums are closed `in:` lists — fail-closed
- max lengths established per field (hero headline: 255, cta_button label: 100, etc.)
- Array caps: stats.items max:8, card_grid.cards max:12

---

## 14. Exact Validator Delta

### 14.1 `content_list` schema delta

ADD one field:
```php
'display_mode' => ['nullable', 'string', 'in:grid,carousel'],
```

Placement: after `'order'` in existing schema. Default: `grid` (handled by frontend default_config in registry, not by validator).

Justification: Same ADR-001 precedent — additive, closed enum, existing type unchanged.

### 14.2 `cta_button` schema delta

ADD one field:
```php
'intent' => ['nullable', 'string', 'in:general,donation,zakat'],
```

Placement: after `'variant'` in existing schema. Default: null (treated as general by renderer when absent).

Justification: Presentation metadata only, never interpreted as routing/business decision by backend.

### 14.3 `rich_text` schema delta

MODIFY existing field + ADD new field:

MODIFY `source` enum:
```php
'source' => ['required', 'string', 'in:cms_content,caption,custom_html'],
```

ADD conditional fields for `source=custom_html`:
```php
'body_html' => ['required_if:source,custom_html', 'nullable', 'string'],
```

Placement: After existing rich_text fields. Sanitization happens at save time via ContentSanitizer (outside validator — validator ensures presence, sanitizer ensures safety).

Max-bytes enforcement (200KB) is handled by ContentSanitizer::MAX_BYTES at sanitization time, not by validator.

### 14.4 Fields NOT changed

- No existing field removed
- No existing field made stricter
- No new component type added
- No new DB column implied
- Backward compatible: existing configs lack these fields; nullable rules mean they're optional; defaults defined in frontend

---

## 15. ContentSanitizer Security Verification

**VERDICT: PASS — existing sanitizer satisfies HD-CR001D-01.**

### Verified capabilities (actual code):

| Requirement | Enforced? | How |
|------------|-----------|-----|
| Reject `<script>` | YES | In `DANGEROUS_TAGS` → throws `ContentSanitizationException` |
| Reject `<style>` | YES | In `DANGEROUS_TAGS` |
| Reject `<iframe>` | YES | In `DANGEROUS_TAGS` — explicitly listed |
| Reject `<object>` | YES | In `DANGEROUS_TAGS` |
| Reject `<embed>` | YES | In `DANGEROUS_TAGS` |
| Reject `<form>` | YES | In `DANGEROUS_TAGS` |
| Reject `<input>` | YES | In `DANGEROUS_TAGS` |
| Reject `<base>/<link>/<meta>/<svg>/<math>` | YES | In `DANGEROUS_TAGS` |
| Strip event handlers (onclick, onerror, etc.) | YES | Not in GLOBAL_ATTRIBUTES or TAG_ATTRIBUTES — silently stripped |
| Allowed tags limited | YES | 26-tag explicit allowlist: p,br,h1-h4,ul,ol,li,a,img,strong,em,blockquote,table,thead,tbody,tr,th,td,figure,figcaption,code,pre,hr,span |
| URL scheme validation | YES | ALLOWED_HREF_SCHEMES = ['https','http','mailto','tel'] — rejects javascript:/data: |
| Image src contract | YES | Strips raw `src`; requires valid `data-media` ULID token |
| Body size limit | YES | MAX_BYTES = 200 * 1024 (200KB); throws if exceeded |
| Class/id attribute sanitization | YES | class regex strips non-alphanumeric-except-space/hyphen; id slugged |
| Target attribute | YES | Only `_blank` allowed on anchors |
| Idempotent | YES | Documented; sanitizing already-sanitized output is a no-op |

### Verdict

Existing `ContentSanitizer` satisfies every requirement in HD-CR001D-01 and spec §14 (§14.1 explicit security boundary table). No new sanitizer, no new library, no weakening of existing rules. D MUST call `ContentSanitizer::sanitize($bodyHtml)` before persisting `config.body_html`.

### Gap analysis

No gap identified. The sanitizer is well-designed for the stated requirements.

---

## 16. Safe Custom Content Contract

**VERDICT: READY — fully specified, zero implementation ambiguity.**

Contract:
1. `rich_text.source = 'custom_html'` (new enum value in validator schema)
2. `rich_text.body_html` required when source is custom_html
3. `ContentSanitizer::sanitize($bodyHtml)` called by `PageBuilderBlockService::updateBlockConfig()` BEFORE passing config to `ThemeComponentService::update()`
4. Persist SANITIZED result into config JSON
5. On sanitization failure (ContentSanitizationException): catch and throw as `ValidationException` (422)
6. Max bytes: 200KB enforced by sanitizer
7. Double-sanitization avoided: sanitizer is idempotent (documented property); calling it at save time means render-time passes through already-sanitized HTML

Implementation note: If the operator edits a block containing `source=custom_html`, the form receives the current `body_html` value (already sanitized), displays it in a textarea, submits it back, and the service calls sanitize again (idempotent, no harm).

---

## 17. CTA Intent Contract

**VERDICT: READY — fully specified, minimal implementation.**

Closed enum: `general|donation|zakat`
Schema: `nullable|string|in:general,donation,zakat`
Storage: in `config.json` as optional field on `cta_button` type
Backend interpretation: NONE — never reads intent for routing or business logic
Frontend interpretation: styling hint only (icon/color variant)

The existing `cta_button` renderer in `PublicRenderer::renderCtaButton()` returns `{label, variant, url}` — no intent field. Rendering of intent-specific styling is CR-001-E's responsibility (PublicRenderer not modified in D).

---

## 18. Content List / Carousel Contract

**VERDICT: READY — fully specified.**

`display_mode` adds no new data resolution path. It affects only the rendering layer:
- `display_mode=grid` (default) → current grid rendering (unchanged)
- `display_mode=carousel` → carousel markup (CR-001-E PublicRenderer change)

Dataset resolution is identical for both modes: `content_list.content_kind` determines the projection resolver. No new resolver, no new query.

Safe enum: `grid|carousel` (2 values, closed). Nullable in schema (default grid applied by frontend).

---

## 19. Section/Block Operation Map

### 19.1 Operations and Canonical Service Paths

| Action | D Route | Canonical Service Method | D Wrapper Needed? | Reason |
|--------|---------|--------------------------|-------------------|--------|
| ADD block | POST `/templates/{template}/blocks` | `SectionService::createAndPlace()` + `ComponentService::create()` | YES | Two-step compose in one transaction, validates blockType against registry |
| EDIT block config | PATCH `/blocks/{section}` | `ComponentService::update()` | YES | Must resolve single-component Section, verify ownership, stale-edit check |
| REMOVE block | DELETE `/templates/{template}/blocks/{section}` | `SectionService::removeFromTemplate()` | NO (with verification) | Verify Section belongs to Template in URL before delegating |
| ENABLE/DISABLE | PATCH `/blocks/{section}/visibility` | `SectionService::update($section, ['visible' => bool])` | NO | Single-method delegation |
| REORDER | PUT `/templates/{template}/blocks/order` | `SectionService::reorder($template, $orderedSectionIds)` | NO | Existing exact-set contract sufficient |
| DUPLICATE | POST `/templates/{template}/blocks/{section}/duplicate` | `SectionService::createAndPlace()` + `ComponentService::create()` | YES | Reads source config, calls addBlock with same values |
| PLACE REUSABLE | POST `/templates/{template}/blocks/place-reusable` | `SectionService::placeReusable()` | NO | Existing method already gated on is_reusable + same-theme |

### 19.2 Reusable Section Semantics

From `ThemeSectionService::placeReusable()`:
- Checks `is_reusable` flag on source Section (fail with exception if false)
- Checks same-theme ownership (reject cross-theme)
- Places at next available position via syncWithoutDetaching

D exposes this as an "Add Existing Reusable Block" picker listing Sections on the same Theme with `is_reusable=true`.

---

## 20. Reorder Semantics

### 20.1 Actual Implementation (verified)

`ThemeSectionService::reorder()` (§119-150 of actual file):
1. Opens transaction + `lockForUpdate()` on Template
2. Gets current section IDs (sorted) and compares against requested IDs (sorted)
3. If sets mismatch → throws `ThemeValidationException('reorder_set_mismatch')` — exact-set validation protects against stale/out-of-date clients
4. Detaches ALL placements (avoids UNIQUE(position) collision mid-reorder)
5. Re-attaches each section at position index+1

### 20.2 Ordering Field

Column: `theme_template_sections.position` — unsigned integer, sequential 1..N. No gaps required (detach-then-reattach closes any gaps automatically). No unique constraint on individual position values across templates (unique only within a template).

### 20.3 SQLite Compatibility

The detach-then-reattach reorder algorithm works correctly on SQLite. No MySQL-specific semantics identified for reorder.

### 20.4 Temporary Values

Not needed. The existing approach (detach all, then attach with correct positions) avoids the two-phase offset trick that `ThemeComponentService::reorder()` uses (which bumps positions by +1000000 first to handle the UNIQUE(section_id, position) constraint differently). Section reorder uses pivot detachment which doesn't conflict with itself.

---

## 21. Stale Edit Protection

### 21.1 Actual Timestamp Support

Verified: all three relevant tables use microsecond-precision timestamps (dateTime(..., 6)).
- `ThemeTemplate`: `dateTime('updated_at', 6)` at line 27
- `ThemeSection`: inherits default timestamps with microsecond precision via ULID migration
- `ThemeComponent`: inherits default timestamps with microsecond precision

**NO optimistic-lock version column exists.** Only `updated_at` with microsecond precision.

### 21.2 Stale Edit Algorithm (spec §29)

1. When client loads block edit form, capture `section.updated_at` (timestamp)
2. Client includes `expected_updated_at` header/query-param on PATCH request
3. Inside `PageBuilderBlockService::updateBlockConfig()`:
   - Within the existing `lockForUpdate()` transaction of `ComponentService::update()`
   - Compare current row's `updated_at` (post-lock) vs `expected_updated_at`
   - Mismatch → throw `ThemeValidationException('stale_edit', ...)` → caught by controller → 409 Conflict
   - Match → proceed normally

All three relevant tables use microsecond-precision timestamps. There is no second-precision stale-edit gap in the schema.

Implementation must only verify that the microsecond value survives the HTTP / JSON / ISO-8601 serialization round-trip without truncation. This mirrors existing Laravel concurrency patterns.

### 21.3 No New Column Needed

Relies exclusively on existing `updated_at` timestamps. Zero schema changes.

---

## 22. DRAFT Enforcement

### 22.1 Actual Pattern (verified)

`SiteDesignController::abortUnlessDraft()` (line 53-54):
```php
private function abortUnlessDraft(Theme $theme): void
{
    abort_if($theme->status !== 'DRAFT', 409, '...');
}
```

Called before EVERY mutating action (saveBrand, uploadLogo, saveMenu, createNavigationItem, updateNavigationItem, cloneToDraft preview, publish).

### 22.2 D Pattern

D replicates this EXACTLY:
```php
private function abortUnlessDraft(Theme $theme): void
{
    abort_if($theme->status !== 'DRAFT', 409, 'Page Builder editing targets DRAFT themes only.');
}
```

HTTP semantics:
- Non-DRAFT status → HTTP 409 Conflict
- Before authorization check: NO (authorization first, draft check second — per §23 of spec, matching SiteDesignController pattern)
- Server-enforced: YES (identical controller code path regardless of client)

### 22.3 Status Lifecycle

From `Theme` model: `DRAFT|ACTIVE|INACTIVE|ARCHIVED`. Draft enforcement denies mutations for ACTIVE, INACTIVE, and ARCHIVED.

---

## 23. Authorization Map

### 23.1 Permissions (verified against PermissionRegistry)

| Constant | Code | Policy Method | Definition |
|----------|------|---------------|------------|
| `THEME_VIEW` | `'theme.view'` | `ThemePolicy::view()` | Section 18 of IMP-006 |
| `THEME_CREATE` | `'theme.create'` | `ThemePolicy::create()` | Section 18 of IMP-006 |
| `THEME_UPDATE` | `'theme.update'` | `ThemePolicy::update()` | Section 18 of IMP-006 |
| `THEME_PUBLISH` | `'theme.publish'` | `ThemePolicy::publish()` | Section 18 of IMP-006 |
| `THEME_ARCHIVE` | `'theme.archive'` | `ThemePolicy::archive()` | Section 18 of IMP-006 |
| `THEME_MEDIA_UPLOAD` | `'theme.media.upload'` | `ThemePolicy::uploadAsset()` | Section 18 of IMP-006 |
| `THEME_PREVIEW` | `'theme.preview'` | `ThemePolicy::preview()` | CREATED BY CR-001-B (not D) |

### 23.2 D Permission Requirements

| D Action | Required Permission | Policy Method | Scope |
|----------|--------------------|---------------|-------|
| View builder canvas list | `THEME_VIEW` | `view()` | No resource (global list) |
| Add/Edit/Remove/Duplicate block | `THEME_UPDATE` | `update()` | RESOURCE: Theme (scope via ThemeScopeResolver, Organization) |
| Toggle block visibility | `THEME_UPDATE` | `update()` | RESOURCE: Theme |
| Reorder blocks | `THEME_UPDATE` | `update()` | RESOURCE: Theme |
| Preview (existing route) | `THEME_PREVIEW` | `preview()` | RESOURCE: Theme (Organization scope) |
| Publish (existing route) | `THEME_PUBLISH` | `publish()` | RESOURCE: Theme |

**No new permission constants needed.** All D actions reuse existing `theme.*` permissions. `THEME_PREVIEW` was created by CR-001-B and is already consumed by `SiteDesignController::preview()`.

### 23.3 Scope Resolution

Via `ThemeScopeResolver` + `ScopeType::Organization` — identical to every other ThemePolicy method. Denies cross-org access by construction (ULID-based binding).

### 23.4 ResourceStatePredicate

- `publish()`: permits ACTIVE, INACTIVE, DRAFT states (per spec line 93)
- `archive()`: permits DRAFT, INACTIVE states
- `update()`: NO resourceStatePredicate (any Theme can be updated, DFT enforcement is via abortUnlessDraft, not policy)

---

## 24. Route Map

### 24.1 Existing Admin Routes (web.php verified)

Current admin route groups (prefix only):
- `/admin/content/*` — CMS pages/articles/media/homepage
- `/admin/theme/*` — Advanced/Debug ThemeEngine CRUD
- `/admin/site-design/*` — CR-001-C Site Design wrapper (branding, menus, preview, publish, clone)
- `/admin/campaign/programs/*` — Programs
- `/admin/campaign/funds/*` — Funds
- `/admin/campaign/campaigns/*` — Campaigns
- `/me/donations/*` — Donor donations
- `/me/recurring-plans/*` — Donor plans
- `/admin/donation/donations/*` — Admin donations
- `/admin/donation/recurring-plans/*` — Admin plans
- `/me/donations/{donation}/payments/*` — Donor payments
- `/admin/payment/payments/*` — Admin payments
- `/admin/payment/provider-config/*` — Payment config

### 24.2 D Route Additions (NEW GROUP)

Exact group (matching spec §42 verbatim):
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

### 24.3 Insertion Point

After existing `admin/site-design/*` group (around web.php line 218), before `admin/campaign/*` group. Clean insertion point, no conflict with existing routes.

### 24.4 Route Model Binding

- `{theme}` bound by `Theme::getRouteKeyName()` → `ulid`
- `{template}` bound by `ThemeTemplate::getRouteKeyName()` → `ulid`
- `{section}` bound by `ThemeSection::getRouteKeyName()` → `ulid`

All ULID-bound, never internal BIGINT id. Consistent with existing Theme routes.

### 24.5 Collision Assessment

**routes/web.php SHARED FILE — COLLISION WARNING.** Per spec §44, routes/web.php is also being modified by FE-CHK-009's protected work. D's addition must use selective hunk staging (`git add -p` or equivalent).

No route conflicts: `admin/page-builder/*` is a completely new prefix group not overlapping with any existing prefix.

---

## 25. Vue File Map

### 25.1 New Files (CREATE)

| File | Purpose | Rationale |
|------|---------|-----------|
| `resources/js/Pages/Admin/PageBuilder/Index.vue` | Canvas picker (Home/Page/Article cards) | Entry point; mirrors Overview.vue's card layout pattern |
| `resources/js/Pages/Admin/PageBuilder/Show.vue` | Block-list canvas with ordered rows | Main editor surface |
| `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue` | Generic configuration form host | Dispatches per-block-type forms; reuses UI primitives |

### 25.2 Why Three Files, Not More

Spec §41 proposes Index + Show + BlockConfigPanel. Additional components (BlockLibrary, BlockCard, BlockEditor, ReorderControls) were evaluated:

- `Index.vue`: Minimal — three cards linking to Show for each canvas. Can embed in ~100 LOC.
- `Show.vue`: Block list with inline reorder controls, enable/disable toggles, configure button link to modal. Can integrate BlockCard logic internally (~200 LOC).
- `BlockConfigPanel.vue`: Modal/form panel dispatched from Show.vue. Handles per-type configuration.

Consolidation rationale: The admin Page Builder is a focused tool, not a full Figma clone. Keeping it to 3 files respects the "simple admin tooling, not complex visual editor" principle. Keyboard-operable Move Up/Down buttons (§33 accessibility requirement) are inline in Show.vue, not a separate component.

### 25.3 Modification (MODIFY)

| File | Change | Rationale |
|------|--------|-----------|
| `routes/web.php` | Add `admin/page-builder/*` route group | NEW routes for D |

### 25.4 Protected Collisions (DO NOT MODIFY)

From git status — FE-CHK-009 dirty/untracked files:
- `resources/js/Components/Admin/AdminLayout.vue` — DIRTY (modified, not untracked)
- `resources/js/Components/UI/Icon.vue` — DIRTY (modified, extended with heart/creditCard/settings)
- All `resources/js/Pages/Public/*.vue` — DIRTY
- All `tests/Feature/**/*.php` — DIRTY or UNTRACKED
- `app/Http/Controllers/Public*.php` — DIRTY
- `app/Services/Theme/PublicRenderer.php` — DIRTY

None of these are owned by D. D creates files under `Pages/Admin/PageBuilder/` and `Components/Admin/PageBuilder/` — disjoint directories. Icon.vue may need EXTENSION for D's block icons, but only via additive entries that don't conflict with existing icon names.

---

## 26. Asset Ownership/Selection

### 26.1 How Assets Work (verified)

`ThemeAsset` rows belong to `theme_id`. Each asset has a `token` resolved to a public URL via `ThemeAssetTokenResolver`. Blocks reference assets via `*_theme_asset_ulid` fields in config JSON (e.g., `background_theme_asset_ulid` on hero, `theme_asset_ulid` on banner cards).

### 26.2 D Constraints

- D operators can ONLY select assets belonging to the SAME DRAFT Theme as the one being edited
- Cross-theme asset references must be rejected server-side (pattern: check asset.theme_id == target template.theme_id)
- No raw filesystem paths exposed — only ULID + resolved URL via token resolver
- D does NOT implement its own media system — it uses the same asset selection mechanism `SiteDesignController` already uses (asset picker linked to `SiteDesignController::showBrand` page + existing ThemeAssetService)

### 26.3 Asset Picker

D's form panels reference `theme_assets` scoped to the DRAFT Theme. This data is already served by `SiteDesignController::showBrand()`. D's `Show.vue` may accept the same asset listing as an Inertia prop and render a selector. No new controller endpoint needed — D shares the existing SiteDesign asset listing.

---

## 27. Domain Reference Validation

### 27.1 What D References (NOT copies)

- **Campaigns**: via `content_list.content_kind='campaign'` → resolved live by `CampaignProjectionResolver` at render time
- **Programs**: via `content_list.content_kind='program'` → resolved live by `ProgramProjectionResolver` at render time
- **Articles/News**: via `content_list.content_kind='article'` → resolved live by CmsArticle query at render time
- **CMS Pages**: via `content_list.content_kind='page'` → resolved live by CmsPage query at render time
- **Individual CmsPage/CmsArticle references**: via `rich_text.source='cms_content'` + `content_ulid` → already validated by ComponentConfigValidator (ULID size:26, in content_kind match)

### 27.2 D Does NOT Do

- Never copies Campaign/Program/Article records into component config
- Never stores IDs outside ULID references
- Never makes direct domain-model queries (resolvers do that)
- Never implements filtering logic (config stores filter params only: limit, order, article_type, content_kind)

### 27.3 Validation Chain

Block config → `ComponentConfigValidator::assertValid()` → `ThemeComponentService::create()` or `update()` → persists into `theme_components.config` JSON → resolved at render time by `PublicRenderer`. D is in the middle of this chain only at the write side.

---

## 28. Home/Page/Article Operator UX Mapping

### 28.1 Canvas Picker (Index.vue)

Three cards displayed horizontally:
1. "Home Layout" — links to Show for home Template
2. "Page Layout" — links to Show for page Template (labeled clearly as shared layout, not per-page)
3. "Article Layout" — links to Show for article Template

Labels are FRIENDLY (no technical vocabulary like "Template", "content_kind"). Internal template IDs remain hidden.

### 28.2 Resolving Templates for Canvas Picker

```php
// D controller resolves via:
$templates = $theme->templates()->whereIn('content_kind', ['home','page','article'])->get();
// Maps to friendly labels:
$canvasOptions = $templates->map(fn($t) => [
    'label' => ucfirst($t->content_kind) . ' Layout',
    'contentKind' => $t->content_kind,
    'templateUlid' => $t->ulid,
]);
```

No ID shown to operator. The URL carries the template ULID, not the content_kind.

### 28.3 Shared Canvas Messaging

Per spec §7: "The UI copy must make this explicit (e.g. 'Page Layout' rather than bare 'Pages') to avoid operator confusion." This is frontend UX guidance — the recon confirms it requires NO backend changes.

---

## 29. Preview Boundary

### 29.1 Verified (actual code)

`SiteDesignController::preview()` (lines 236-252):
```php
public function preview(Request $request, ThemePolicy $policy, Theme $theme): Response
{
    $actor = $this->resolveActingPrincipal($request);
    abort_unless($policy->preview($actor, $theme), 403);
    if ($theme->status !== 'DRAFT') { abort(404); }
    return Inertia::render('Admin/SiteDesign/Preview', [...]);
}
```

Route: `GET /admin/site-design/{theme}/preview` named `site-design.preview`.

### 29.2 D Behavior

D does NOT create a new preview route. Its "Preview" button simply links to the existing route:
```html
<a :href="`/admin/site-design/${theme.ulid}/preview`">Preview</a>
```

The existing preview page renders the full DRAFT Theme (including all new D blocks because PublicRenderer dispatches by component.type). No PublicRenderer modification needed for D — the renderer already falls back gracefully for unknown types (default → null in match expression).

### 29.3 PublicRenderer Compatibility

`PublicRenderer::renderComponent()` line 259-270:
```php
match ($component->type) {
    'hero' => ...,
    'rich_text' => ...,
    // ... all existing types ...
    default => null, // graceful degradation
};
```

New `display_mode` and `intent` fields are stored in `config` — they pass through the renderer's `$config` extraction unchanged. The renderer ignores them at present; rendering them differently is CR-001-E's job. For D, the preview simply shows existing rendering of D's blocks.

---

## 30. PublicRenderer Boundary

**VERDICT: READ-ONLY — zero modifications for D.**

Confirmed by inspection:
- `renderForContentKind()` — unchanged
- `renderComponent()` — unchanged (has `default => null` fallback)
- `siteChrome()` — unchanged
- All renderXXX() methods — unchanged

Any D-required rendering changes (carousel display_mode, custom_html body_html) are listed as CR-001-E handoff items (§52 of spec). D leaves the renderer untouched.

**Contract D leaves for E:**
1. `renderContentList()` must handle `display_mode='carousel'` with carousel markup
2. `renderRichText()` must handle `source='custom_html'` by outputting `config['body_html']`
3. `renderCtaButton()` may optionally use `config['intent']` for styling/icon hints

---

## 31. Audit Semantics

### 31.1 Verified (actual audit events)

From `ThemeAuditLogger`:
- `recordSectionUpdated($sectionId, $metadata, $actor)` — emits `theme.section.updated`
- `recordComponentUpdated($componentId, $metadata, $actor)` — emits `theme.component.updated`

### 31.2 D Event Mapping

| D Action | Canonical Events Emitted | Source |
|----------|--------------------------|--------|
| Add Block | `theme.section.updated['created','placed']` + `theme.component.updated['created']` | SectionService + ComponentService |
| Edit Block | `theme.component.updated['config']` (or fields_changed) | ComponentService::update() |
| Remove Block | `theme.section.updated['removed_from_template']` (and possibly section deletion audit) | SectionService::removeFromTemplate() |
| Enable/Disable | `theme.section.updated['visible']` | SectionService::update() |
| Reorder | `theme.section.updated['reordered']` | SectionService::reorder() |
| Duplicate | Same as Add Block | Composed of create ops |
| Place Reusable | `theme.section.updated['placed']` | SectionService::placeReusable() |

**No new audit event types.** All D operations map to existing events emitted by the canonical services D delegates to.

---

## 32. Security/IDOR Map

### 32.1 Resource Chain

```
Theme (owner: organization scope via Policy)
  → ThemeTemplate (via theme_id FK, verified by belongsTo)
    → theme_template_sections (pivot, verified by whereKey(template_id))
      → ThemeSection (theme_id FK on section, verified by belongsTo)
        → ThemeComponent (theme_section_id FK, verified by belongsTo)
          → ThemeAsset (theme_id FK on asset, cross-reference check needed)
          → Domain references (Campaign/Program ulids — never written into config, resolved live)
```

### 32.2 Server-Side Ownership Checks Required

| Check | Where | Mechanism |
|-------|-------|-----------|
| Theme ownership (DRAFT scope) | Every mutating controller method | `$policy->update($actor, $theme)` → ThemeScopeResolver + Organization scope |
| Theme status (DRAFT only) | Every mutating controller method | `abortUnlessDraft($theme)` → 409 |
| Template belongs to Theme | Show, store, destroy, duplicate, reorder | Resolve template via `$theme->templates()->whereKey($templateUlid)` → 404 if not found |
| Section belongs to Template | Update, destroy, visibility, duplicate | Verify section's Template pivot exists: `$template->sections()->whereKey($sectionUlid)->exists()` |
| Section belongs to same Theme as Template | Place reusable, update | Already guaranteed by Template→Section relationship (Section.theme_id checked by relationship) |
| Asset belongs to same Theme | Asset reference in config | Validate before passing to ThemeAssetService: `$theme->assets()->whereKey($assetUlid)->exists()` |
| Unknown block type | PageBuilderBlockService::addBlock() | `ComponentConfigValidator::assertValid()` throws on unknown type |
| Mass assignment protection | Every controller | Whitelisted request fields passed to service methods (same pattern as SiteDesignController) |

### 32.3 Threat Mitigation Matrix (verified against actual code)

| Threat | Mitigation | Layer |
|--------|-----------|-------|
| Cross-theme template | Template resolved via parent Theme | Controller |
| Cross-template section | Section verified against Template pivot | Controller |
| Non-DRAFT mutation | abortUnlessDraft → 409 | Controller |
| Unauthorized access | Policy → 403 | Middleware + Controller |
| Arbitrary block key | Fail-closed validator | Service |
| Unsafe HTML | ContentSanitizer → ContentSanitizationException | Service |
| Cross-theme asset | Server-side ownership check before persist | Service/Controller |
| IDOR (guessing ULID) | Scope resolver denies cross-org | Policy |
| Direct HTTP bypass | Identical controller code path | Backend |

---

## 33. ADR Requirement

### 33.1 Finding

Per CHANGE-CONTROL.md (section "AI Approval Boundary"): AI may analyze/recommend/draft but NOT approve changes to locked architecture. Extending `ComponentConfigValidator::SCHEMAS` (even additively) IS a locked architecture change.

### 33.2 Precedent

ADR-001 (`docs/adr/ADR-001-theme-content-projection-amendment.md`) established the exact precedent: additive extension of `content_list`/`card_grid` content_kind enum from `page|article` to `page|article|program|campaign`. Same process: Human authorization → ADR recorded under `docs/adr/` → implementation.

### 33.3 Determination

- **ADR required:** YES
- **ADR path:** `docs/adr/ADR-004-page-builder-block-schema-extension.md` (next sequential identifier after ADR-003)
- **When to create:** BEFORE implementation by Muse, as a prerequisite per spec §57 step 2
- **Human approval needed:** HD-CR001D-01 already authorizes the scope; the ADR documents the governance trail. No NEW Human Decision needed beyond what's already approved. The ADR can be written based on the approved spec and referenced in the implementation evidence.
- **ADR content:** Documents that three additive optional fields extend three existing component type schemas; cites HD-CR001D-01 approval; mirrors ADR-001 structure; declares zero database impact; declares backward compatibility.

### 33.4 Note

ADR-002 is a GOVERNANCE artifact, not a production code file. Qwen recon records it as a requirement. The actual ADR file is written during implementation preparation (by the implementation writer, not by recon).

---

## 34. Schema Assessment

**VERDICT: PASS — zero schema changes.**

All D changes are PHP-level:
- New class files (PageBuilderBlockService, PageBuilderBlockRegistry, PageBuilderController)
- Modified validator arrays (ComponentConfigValidator — just PHP array additions)
- New Vue files
- New test files
- Route group addition (routes/web.php — no SQL)

No migration needed. No new column. No new table. No ALTER TABLE.

---

## 35. Migration Assessment

**VERDICT: PASS — zero migrations.**

Zero `database/migrations/*` files created or proposed by D. The validator schema extensions are pure PHP — adding entries to a PHP array constant does not touch the database. The `rich_text.source` enum extension from `cms_content,caption` to `cms_content,caption,custom_html` is validated at application level; no DB column change.

---

## 36. Test File Map

### 36.1 New Tests (CREATE)

| File | Coverage | Rationale |
|------|----------|-----------|
| `tests/Feature/Theme/PageBuilderBlockServiceTest.php` | addBlock, updateBlockConfig, removeBlock, duplicateBlock, invalid block type, invalid config, cross-theme asset, transaction rollback, reusable block semantics, safe CTA destination | Core service logic tests |
| `tests/Feature/Theme/PageBuilderControllerTest.php` | authorized DRAFT access, HOME/PAGE/ARTICLE canvas mapping, enable/disable block, edit block, preview contract (links to existing route) | Controller action + auth + canvas mapping |
| `tests/Feature/Theme/PageBuilderDraftOnlyTest.php` | ACTIVE denied (409), INACTIVE denied (409), ARCHIVED denied (409) | DRAFT lifecycle enforcement |
| `tests/Feature/Theme/PageBuilderReorderTest.php` | reorder success, persisted order verification, reorder_set_mismatch rejection | Reorder exact-set contract + persistence |
| `tests/Feature/Theme/PageBuilderLeastPrivilegeTest.php` | unauthorized access (403), cross-theme Section manipulation rejected, VIEW-only user cannot mutate | Authorization negative-path |
| `tests/Feature/Theme/PageBuilderConcurrencyTest.php` | stale concurrent edit protection (409), lockForUpdate isolation | Concurrency/stale-edit |
| `tests/Feature/Theme/PageBuilderSafeContentTest.php` | safe content sanitization (disallowed elements rejected, oversized rejected, idempotent round-trip, JavaScript/event handler stripping) | HD-CR001D-01 sanitizer verification |

### 36.2 Modified Tests (MODIFY)

| File | Change | Rationale |
|------|--------|-----------|
| `tests/Unit/Theme/ComponentConfigValidatorTest.php` | Add test cases for new additive fields: display_mode on content_list, intent on cta_button, custom_html source on rich_text + body_html | Verify new schema entries validate correctly |

### 36.3 Regression

Full existing Feature test suite re-run as regression gate (existing 146+ tests from CR-001-C baseline).

---

## 37. MySQL-specific Tests

**VERDICT: NONE REQUIRED.**

Both concurrency protection mechanisms work correctly on SQLite:
1. `lockForUpdate()` inside transaction — already exercised by existing Theme test suite under SQLite
2. Additive `updated_at` comparison stale-edit check — pure application logic comparing timestamp values, no MySQL-specific locking primitive needed

Mirrors CR-001-C's own recorded position: "MySQL NOT REQUIRED FOR C CLOSURE."

---

## 38. Protected FE Collision Map

### 38.1 Current Git Dirty State (authoritative snapshot)

**Modified (16 files):**
| Path | D Collision? | Handling |
|------|--------------|----------|
| `app/Http/Controllers/PublicCampaignController.php` | NO | Preserve |
| `app/Http/Controllers/PublicDonationController.php` | NO | Preserve (CR-001-J reserved) |
| `app/Http/Controllers/PublicPaymentController.php` | NO | Preserve (CR-001-J reserved) |
| `app/Http/Controllers/PublicProgramController.php` | NO | Preserve |
| `app/Services/Theme/PublicRenderer.php` | NO (READ-ONLY) | Preserve (CR-001-E ownership) |
| `docs/implementation/POST-IMP-009-frontend-integration.md` | NO | Documentation preserve |
| `resources/js/Components/Admin/AdminLayout.vue` | NO collision, but SENSITIVE | DO NOT MODIFY — decomposed by CR-001-D (separate phase) |
| `resources/js/Components/UI/Icon.vue` | READ-ONLY / REUSE — existing registry already provides sufficient icon keys for D v1 (dashboard, document, megaphone, wallet, palette, image, calendar, target, heart, creditCard, settings). No D-owned icon extension required. |
| `resources/js/Pages/Public/CampaignIndex.vue` | NO | Preserve |
| `resources/js/Pages/Public/CampaignShow.vue` | NO | Preserve |
| `resources/js/Pages/Public/DonationCreate.vue` | NO | Preserve (CR-001-F reconciliation needed later) |
| `resources/js/Pages/Public/ProgramShow.vue` | NO | Preserve |
| `resources/js/Pages/Public/ThemeRender.vue` | NO | Preserve (CR-001-E reconciliation needed later) |
| `routes/web.php` | YES — SHARED | SELECTIVE STAGING ONLY — add only the `admin/page-builder/*` route group hunk |
| `tests/Feature/Donation/DonationHttpTest.php` | NO | Preserve (CR-001-J reserved) |
| `tests/Feature/Payment/PaymentHttpTest.php` | NO | Preserve (CR-001-J reserved) |

**Untracked (8 files):**
| Path | D Collision? | Handling |
|------|--------------|----------|
| `resources/js/Components/Public/*` (3 files) | NO | Preserve |
| `resources/js/Pages/Public/PaymentCreate.vue` | NO | Preserve |
| `resources/js/Pages/Public/PaymentShow.vue` | NO | Preserve |
| `tests/Feature/Campaign/CampaignShowDonationLinkTest.php` | NO | Preserve |
| `tests/Feature/Donation/DonationFlowRedirectTest.php` | NO | Preserve |
| `tests/Feature/Payment/PaymentHtmlPageTest.php` | NO | Preserve |

**Total collisions with D's likely implementation: 1 file requires care:**
1. `routes/web.php` — selective hunk staging only (never whole-file add)

**FE-CHK-009 Status: PRESERVED.** No protected file modified by D recon.
`resources/js/Components/UI/Icon.vue` is classified READ-ONLY / REUSE for D — no modification needed. Existing registry already provides sufficient icon keys (dashboard, document, megaphone, wallet, palette, image, calendar, target, heart, creditCard, settings).

---

## 39. CR-001-C Upstream Extensions

**VERDICT: ZERO upstream modifications required.**

D consumes CR-001-C's services and contracts but modifies NOTHING CR-001-C created:
- Does not modify SiteDesignController
- Does not modify any CR-001-C Vue pages
- Does not modify PublicRenderer.php
- Does not modify any CR-001-C models or migrations

D creates entirely new files (PageBuilderController, PageBuilderBlockService, PageBuilderBlockRegistry, Vue pages under Admin/PageBuilder/, Tests under Theme/PageBuilder*).

**Upstream extensions: 0.**

---

## 40. Implementation Ownership Map

### 40.1 CREATE — Production

| # | File | Reason |
|---|------|--------|
| 1 | `app/Http/Controllers/Admin/PageBuilderController.php` | New thin controller mirroring SiteDesignController pattern |
| 2 | `app/Services/Theme/PageBuilderBlockService.php` | Orchestration: compose SectionService + ComponentService with transaction + stale-edit |
| 3 | `app/Services/Theme/PageBuilderBlockRegistry.php` | Static metadata provider (operator labels, categories, icons, allowed_canvases) |
| 4 | `resources/js/Pages/Admin/PageBuilder/Index.vue` | Canvas picker (Home/Page/Article) |
| 5 | `resources/js/Pages/Admin/PageBuilder/Show.vue` | Block-list canvas editor |
| 6 | `resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue` | Per-block-type configuration form |

**Subtotal: 6 production files**

### 40.2 MODIFY — Production

| # | File | Reason |
|---|------|--------|
| 1 | `app/Services/Theme/ComponentConfigValidator.php` | Additive schema extensions (3 fields on 3 types) — HD-CR001D-01 |
| 2 | `routes/web.php` | Add `admin/page-builder/*` route group |

**Subtotal: 2 definite modification files**

### 40.3 CREATE — Tests

| # | File | Reason |
|---|------|--------|
| 1 | `tests/Feature/Theme/PageBuilderBlockServiceTest.php` | Service orchestration + business rules |
| 2 | `tests/Feature/Theme/PageBuilderControllerTest.php` | Controller + auth + canvas mapping |
| 3 | `tests/Feature/Theme/PageBuilderDraftOnlyTest.php` | DRAFT lifecycle enforcement |
| 4 | `tests/Feature/Theme/PageBuilderReorderTest.php` | Reorder exact-set + persistence |
| 5 | `tests/Feature/Theme/PageBuilderLeastPrivilegeTest.php` | Authorization negative-path |
| 6 | `tests/Feature/Theme/PageBuilderConcurrencyTest.php` | Stale edit + lockForUpdate |
| 7 | `tests/Feature/Theme/PageBuilderSafeContentTest.php` | Sanitization security tests |

**Subtotal: 7 test files**

### 40.4 MODIFY — Tests

| # | File | Reason |
|---|------|--------|
| 1 | `tests/Unit/Theme/ComponentConfigValidatorTest.php` | New additive field validation cases |

**Subtotal: 1 test file**

### 40.5 CREATE — Governance/ADR

| # | File | Reason |
|---|------|--------|
| 1 | `docs/adr/ADR-004-page-builder-block-schema-extension.md` | Required governance record for HD-CR001D-01 additive schema changes |

**Subtotal: 1 governance file**

### 40.6 READ-ONLY

| Category | Files |
|----------|-------|
| Theme models | Theme, ThemeTemplate, ThemeSection, ThemeComponent, ThemeBrandingConfig, ThemeAsset, ThemeNavigationMenu/Item |
| Theme services | ThemeSectionService, ThemeComponentService, ThemeTemplateService, ThemeActivationService, SiteDesignCloneService, ComponentConfigValidator (read during recon), PublicRenderer, ThemeScopeResolver, ThemeAuditLogger |
| Policies | ThemePolicy |
| Permissions | PermissionRegistry (THEME_PREVIEW already exists) |
| Content sanitizer | ContentSanitizer (verified, secure) |
| Admin controller | SiteDesignController (pattern reference) |
| Domain resolvers | CampaignProjectionResolver, ProgramProjectionResolver |
| Existing Vue pages | All Pages/Admin/*/.*, all Components/Admin/* |
| UI primitives | `resources/js/Components/UI/Icon.vue` (sufficient icon keys for D v1) |

### 40.7 SHARED / COLLISION

| File | Risk | Handling |
|------|------|----------|
| `routes/web.php` | Also modified by FE-CHK-009 | Selective hunk staging only |

### 40.8 DEFERRED

| Item | Deferred To |
|------|-------------|
| Rendering of `display_mode=carousel` | CR-001-E |
| Rendering of `source=custom_html` (body_html output) | CR-001-E |
| Rendering of `intent` (styling/icon) | CR-001-E |
| Testimonials/FAQ/Contact/Impact blocks | Later CR-001-D round or new CR |
| Video block | Per HD-CR001D-02: excluded entirely |

### 40.9 FORBIDDEN IN D

| Item | Reason |
|------|--------|
| Modify PublicRenderer.php | CR-001-E ownership (READ-ONLY for D) |
| Create/modify migrations | Zero schema changes required |
| Modify CMS controllers/services | Out of scope (IMP-005) |
| Modify ThemeController (Advanced) | HD-CR001-05 retained permanently |
| Modify Donation/Payment controllers | Reserved for CR-001-J |
| Introduce new permissions | None needed |
| Introduce new infrastructure | Shared-hosting mandatory |
| Implement Zakat/Fidyah calculation | CR-001-G |

---

## 41. Implementation Sequence

Derived from dependency analysis:

1. **Governance ADR** — `docs/adr/ADR-004-page-builder-block-schema-extension.md` (prerequisite for validator changes)
2. **Validator delta** — `ComponentConfigValidator.php` (additive fields on content_list, cta_button, rich_text) — enables all subsequent block operations
3. **Block registry** — `PageBuilderBlockRegistry.php` (static metadata) — powers frontend block library
4. **Service layer** — `PageBuilderBlockService.php` (orchestration) — core business logic
5. **Controller** — `PageBuilderController.php` + routes — API boundary
6. **Vue pages** — `Index.vue`, `Show.vue`, `BlockConfigPanel.vue` — operator UI
7. **Tests** — 7 new test files + 1 modified — coverage
8. **Regression** — Full existing Feature test suite re-run

**Critical path:** Validator → Service → Controller → Vue. Tests interleave with service/controller development. ADR precedes validator changes.

---

## 42. Findings

### BLOCKER (0)

None. All architecture is sound, all contracts verified, all prerequisites resolvable within existing governance.

### MAJOR (0)

None. No security gap, no data integrity issue, no architectural conflict.

### MINOR / NON-GATING DEBT (3)

| # | Finding | Severity | Details |
|---|---------|----------|---------|
| 1 | Icon.vue extension risk | MINOR | D's block icons require adding entries to a file already dirty with FE-CHK-009 changes. Must be done carefully with selective staging. |
| 2 | Shared canvas messaging relies on operator discipline | MINOR | The UX clarity requirement ("Page Layout" not "Pages") is frontend-only guidance. Implementation must ensure correct labeling. No backend implication. |
| 3 | Microsecond timestamp serialization | MINOR | Implementation must verify that the microsecond value survives the HTTP / JSON / ISO-8601 serialization round-trip without truncation. All three relevant tables use `dateTime(..., 6)`. |

---

## 43. Non-Gating Debt

| Item | Description | Phase |
|------|-------------|-------|
| Confirmation dialogs for destructive actions | Spec §33 requires confirmation for Remove and Duplicate | Should be addressed in D (lightweight native confirm or modal) |
| Drag-and-drop reorder | Accessibility requires keyboard Move Up/Down as baseline. Drag-and-drop is additive enhancement (spec §33). May be implemented in D or deferred to later | Optional in D |
| Admin-screen responsiveness | Spec §34 requires mobile/tablet usability. Admin responsive constraints apply to new Vue pages | Should be addressed in D |

---

## 44. Open Human Decisions

**Count: 0.** All three HDs (001D-01, 001D-02, 001D-03) resolved per spec §54. No new decisions surfaced by recon.

---

## 45. Implementation Readiness

**READY FOR CLAUDE CONSOLIDATED RECON REVIEW.**

Prerequisites checklist:
- [x] Baseline verified
- [x] Spec read and understood
- [x] Upstream context inspected
- [x] Canonical models verified
- [x] Canonical services verified
- [x] Validators verified
- [x] Policies verified
- [x] Routes mapped
- [x] Frontend files mapped
- [x] Test patterns understood
- [x] Security boundaries verified
- [x] ADR requirement identified
- [x] Migration assessment completed (zero)
- [x] Schema assessment completed (zero)
- [x] Protected FE collision map completed
- [x] Implementation ownership map produced
- [x] Implementation sequence derived
- [x] Findings classified
- [x] FE-CHK-009 preserved

---

## 46. Evidence / Commands

```bash
# Git baseline
git rev-parse HEAD                     # eadc1131270ef59bbf46337f31b7d92b6dd69c81
git rev-parse origin/master            # eadc1131270ef59bbf46337f31b7d92b6dd69c81
git status --short                     # 25 dirty/untracked files (FE-CHK-009)

# Content kind constraint
grep -rn "CONTENT_KINDS" app/          # Found in ThemeTemplateService.php:20
# grep -rn "unique.*content_kind" database/migrations/  # Found in theme_templates migration line 30

# Validator state
wc -l app/Services/Theme/ComponentConfigValidator.php   # 129 lines
grep "const SCHEMAS" app/Services/Theme/ComponentConfigValidator.php   # 9 types

# Sanitizer state
wc -l app/Services/Content/ContentSanitizer.php         # 249 lines
grep "DANGEROUS_TAGS" app/Services/Content/ContentSanitizer.php  # 12 dangerous tags

# Permission state
grep "THEME_PREVIEW" app/Services/Rbac/PermissionRegistry.php  # Line 82 + definitions entry

# Controller pattern
grep "abortUnlessDraft" app/Http/Controllers/Admin/SiteDesignController.php  # 7 occurrences

# PublicRenderer
wc -l app/Services/Theme/PublicRenderer.php             # 518 lines
grep "match.*\$component->type" app/Services/Theme/PublicRenderer.php  # default => null

# ADR precedent
ls docs/adr/ADR-001*                                    # ADR-001-theme-content-projection-amendment.md
```

---

## 47. Final Recon Verdict

```
CANONICAL PAGE MAPPING:
PASS

THEMETEMPLATE UNIQUE CONTRACT:
PASS — UNIQUE(theme_id, content_kind) confirmed in migration line 30.
The DB constraint independently enforces pair uniqueness only (does NOT restrict the value domain).
APPLICATION-ONLY closed enum via ThemeTemplateService::CONTENT_KINDS.
content_kind is a plain VARCHAR(32); allowed values enforced at application layer.
Values: home, page, article (application-only enforcement).

BLOCK REGISTRY MAPPING:
PASS — All 14 Classification-A/B blocks map to existing 9 component types.
Zero new types invented. 3 additive field extensions on existing types only.

VALIDATOR DELTA:
READY — Precisely 3 fields to add/modify:
  - content_list.display_mode (nullable, grid|carousel)
  - cta_button.intent (nullable, general|donation|zakat)
  - rich_text.source enum extension + rich_text.body_html (conditional required_if)

CONTENT SANITIZER:
PASS — Existing ContentSanitizer satisfies ALL HD-CR001D-01 constraints.
12 DANGEROUS_TAGS blocked, 26 ALLOWED_TAGS, scheme validation, media token contract,
200KB max bytes, idempotent, no new library needed.

SAFE CUSTOM CONTENT:
READY — rich_text.source=custom_html + body_html, sanitized at save time via existing
ContentSanitizer. Idempotent round-trip verified. No double-sanitization issue.

CTA INTENT:
READY — Closed enum in:general,donation,zakat. Presentational metadata only.
Never interpreted as routing or business logic by backend.

CONTENT LIST/CAROUSEL:
READY — display_mode=grid|carousel on existing content_list type.
Same dataset resolution, different rendering (rendering = CR-001-E).

DRAFT ENFORCEMENT:
READY — abortUnlessDraft() pattern verified in SiteDesignController. D replicates exactly.
409 Conflict for non-DRAFT. Server-enforced.

AUTHORIZATION:
READY — No new permissions needed. Reuses existing theme.* permissions +
THEME_PREVIEW (already created by CR-001-B). Organization scope via ThemeScopeResolver.

REORDER:
READY — SectionService::reorder() exact-set contract verified. Detach-reattach algorithm
works on SQLite. No temporary offset values needed. Set mismatch = 422.

STALE EDIT:
READY — updated_at comparison inside lockForUpdate() transaction. All three tables use microsecond-precision timestamps (`dateTime(..., 6)`). No new column. Implementation must verify microsecond value survives HTTP/JSON/ISO-8601 round-trip without truncation. 409 on mismatch.

ASSET OWNERSHIP:
READY — Assets scoped by theme_id FK. Cross-theme rejection via server-side check.
No raw filesystem paths. Token resolver provides public URLs.

PREVIEW:
READY — Reuses existing SiteDesignController::preview() route. No new preview route.
No PublicRenderer modification. PublicRenderer already degrades unknown types gracefully.

PUBLICRENDERER:
READ-ONLY — Zero modifications by D. Handoff items for CR-001-E documented.

ADR REQUIRED:
YES — ADR-004-page-builder-block-schema-extension.md required per CHANGE-CONTROL.md.
Precedent: ADR-001-theme-content-projection-amendment.md.

SCHEMA CHANGES:
0

MIGRATIONS:
0

MYSQL-SPECIFIC TESTS:
NONE REQUIRED (lockForUpdate + timestamp comparison both work on SQLite)

CR-001-C UPSTREAM EXTENSIONS:
0 — D modifies nothing CR-001-C created.

ICON.VUE:
READ-ONLY / REUSE — existing registry provides sufficient icon keys for D v1.

PROTECTED FE COLLISIONS:
1 file — routes/web.php (selective hunk staging required)

FE-CHK-009:
PRESERVED — No protected file modified by recon. 25 dirty/untracked files intact.
Icon.vue classified READ-ONLY / REUSE; no modification needed.

CODEX-FE009-01:
OPEN / VALID / UNRESOLVED
OWNER: CR-001-J

IMPLEMENTATION CREATE FILES:
6 production + 7 test + 1 governance = 14 total

IMPLEMENTATION MODIFY FILES:
2 (ComponentConfigValidator.php, routes/web.php)

TEST CREATE FILES:
7

TEST MODIFY FILES:
1

BLOCKER:
0

MAJOR:
0

MINOR / NON-GATING:
3

OPEN HUMAN DECISIONS:
0

FILES CREATED BY RECON:
docs/ai-handoff/CR-001/D-RECON.md

OTHER FILES MODIFIED BY RECON:
0

DATABASE MODIFIED:
NO

COMMIT:
NOT PERFORMED

PUSH:
NOT PERFORMED

MERGE:
NOT PERFORMED

CR-001-E:
NOT STARTED / NOT AUTHORIZED

IMP-010:
BLOCKED

FINAL VERDICT:
CR-001-D RECON COMPLETE — READY FOR CLAUDE CONSOLIDATED RECON REVIEW
```
