# CR-001-C Governed Recon — CMS / Site Design Foundation

> **Architecture baseline:** `cc8de3341f31451b93877f20a4299609d5b5e7e5`
> **CR-001-B final commit:** `6806bf22d733de30a8798bebed558f54b1457768`
> **Human CR-001-C Recon Gate:** AUTHORIZED
> **Recon date:** 2026-09-21
> **Model:** qwen/qwen3.7-flash

---

## 1. Executive Summary

CR-001-C ("CMS / Site Design Foundation") establishes the **operator-friendly admin abstraction layer** over the existing IMP-005 CMS and IMP-006 Theme Engine. Its purpose is to let normal website administrators manage presentation (Site Design) and basic content structure (Pages, Menus, Appearance) **without understanding technical internals** such as:

- `navigation_menu_slot` (component type string)
- Template/Section/Component chain topology
- Renderer internals (`PublicRenderer::renderForContentKind`)
- Database IDs, ULIDs, revision pointer columns

**C is FOUNDATION.** It creates thin wrapper controllers, admin Vue pages, and preview/publish foundations. It does NOT implement the full Visual Page Builder (that belongs to CR-001-D). It does NOT modify `PublicRenderer.php` (that belongs to CR-001-E). It does NOT invent new business data models — every persistence object is reused from existing IMP-005/IMP-006.

**Key architectural decisions for C:**
- NO new tables, NO new columns (zero schema changes)
- ZERO migrations (migration file count = 0)
- Two new controller classes + zero existing controller modifications
- Two Vue page directories created (`Admin/SiteDesign/`, `Admin/PageBuilder/`)
- Existing `ThemeController` preserved as Advanced/Debug (HD-CR001-05)

---

## 2. Recon Baseline

| Field | Value |
|-------|-------|
| HEAD | `6806bf22d733de30a8798bebed558f54b1457768` (CR-001-B FINAL / LOCKED) |
| origin/master | `6806bf22d733de30a8798bebed558f54b1457768` (verified identical) |
| branch | master |
| CR-001-B | FINAL / LOCKED |
| Dirty FE-CHK-009 | 16 modified + 8 untracked = 24 total (preserved) |

---

## 3. Authoritative Inputs Reviewed

| Source | Status | Relevance to C |
|--------|--------|----------------|
| `docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md` | READ (full text, Sections 1–82) | Architecture authority for C scope, ownership, boundaries |
| `docs/ai-handoff/CR-001/RECON.md` | READ (full text, 1467 lines) | Global recon baseline; §21 (phasing), §23 (file ownership), §12.2 (schema table) |
| `docs/ai-handoff/CR-001/B-RECON.md` | READ (full text) | B-owned artifacts consumed by C; B→C dependency map |
| `docs/audits/CR-001-B-FINALIZATION.md` | READ (full text) | B finalization record; confirms IMPLEMENTED scope and EXPLICIT EXCLUSIONS |

---

## 4. Existing IMP-005 CMS Architecture

### 4.1 Models (8 files under `app/Models/Cms/`)

| Model | Table | Key Concepts |
|-------|-------|--------------|
| `CmsPage` | `cms_pages` | Identity + status + revision pointers + schedule. Uses `GeneratesUlid`. ULID route key. |
| `CmsArticle` | `cms_articles` | Same shape as CmsPage + excerpt, first_published_at. Same revision pointer pattern. |
| `CmsContentRevision` | `cms_content_revisions` | Payload store. One per (owner, edit_version). States: DRAFT/PUBLISHED/SCHEDULED/SUPERSEDED. |
| `CmsMediaAsset` | `cms_media_assets` | Media library entry. Ulid-based storage reference. Logical archive support. |
| `CmsMediaReference` | `cms_media_references` | Join table: which asset references which page/article/revision. Fully guarded. |
| `CmsPath` | `cms_paths` | Canonical URL path → page or article. Uniquely locked per owner type. Release/decommission support. |
| `CmsHomepageAssignment` | `cms_homepage_assignment` | Singleton: which CmsPage is the homepage. `$incrementing = false`. |

All CMS models use `GeneratesUlid` trait. Route key name is always `'ulid'`. No locale/multilingual support. Q31 LOCKED.

### 4.2 Services (16 files under `app/Services/Content/`)

| Service | Authority Over | Key Methods |
|---------|---------------|-------------|
| `PageService` | Page identity + draft creation/editing | `create()`, `update()` |
| `ArticleService` | Article identity + draft creation/editing | `create()`, `update()` |
| `PublicationService` | **ALL revision LIFECYCLE writes** | `publish()`, `unpublish()`, `archive()`, `setHomepage()`, `scheduleConfigure()`, `executeScheduledTransition()` |
| `RevisionService` | **ALL revision PAYLOAD writes** | `createDraft()`, `editDraft()`, `rollbackByCopy()` |
| `PathService` | **Canonical URL path claims/releases** | `validate()`, `claim()`, `retainAndUpdateRevision()`, `release()` |
| `ContentResolverService` | Public read resolution | `resolve($rawPath)` |
| `HomepageContentResolver` | Homepage singleton resolution | `resolve()` |
| `PublishedContent` | Presentation-neutral DTO | ulid, kind, title, bodyHtml, metaTitle, etc. |
| `MediaService` | Upload intake + metadata update | `upload()`, `updateMetadata()`, `archive()` |
| `MediaTokenResolver` | Asset token → public URL | `resolveUrl($token)` |
| `ContentSanitizer` | Stored-XSS sanitization wall | `sanitize($html)` |

Q29 boundary preserved: no editorial approval workflow. Draft editing ≠ publication. Editing and publishing authority are distinct service paths.

### 4.3 Controllers (4 files under `app/Http/Controllers/Cms/`)

| Controller | Routes | Actions |
|-----------|--------|---------|
| `PageController` | `/admin/content/pages/*` | index, create, store, edit, update, publish, unpublish, archive |
| `ArticleController` | `/admin/content/articles/*` | Same shape as PageController |
| `MediaController` | `/admin/content/media/*` | index, store, update, archive |
| `HomepageController` | `/admin/content/homepage/*` | edit, update |

All follow the pattern: resolve actor → authorize via Policy → delegate to service → render Inertia page.

### 4.4 Tests (22 files under `tests/Feature/Cms/`)

Comprehensive coverage: controller HTTP tests, service layer tests, scheduler tests, authorization tests, audit emission tests, schema constraint tests. All patterns well-established.

### 4.5 Admin Pages (8 files under `resources/js/Pages/Cms/`)

```
Cms/Pages/Index.vue       – list pages
Cms/Pages/Create.vue      – create form
Cms/Pages/Edit.vue        – editor with sidebar (title, slug, status, publish, SEO)
Cms/Articles/Index.vue    – list articles
Cms/Articles/Create.vue   – create form
Cms/Articles/Edit.vue     – editor (same layout as Pages)
Cms/Media/Index.vue       – media gallery
Cms/Homepage/Edit.vue     – homepage assignment dropdown
```

These exist AS-IS. CR-001-C may consume them but does NOT necessarily modify them — they ARE the Pages foundation already operational.

---

## 5. Existing IMP-006 Theme Architecture

### 5.1 Models (10 files under `app/Models/Theme/`)

| Model | Table | Key Concepts |
|-------|-------|--------------|
| `Theme` | `themes` | Named presentation config. ACTIVE/ARCHIVED/DRAFT status. `is_system_default` flag. |
| `ThemeActivation` | `theme_activation` | **Singleton**: current active Theme pointer. `$incrementing = false`. CHECK constraint. |
| `ThemeTemplate` | `theme_templates` | Per-(theme_id, content_kind). content_kind = 'home'/'page'/'article'. |
| `ThemeSection` | `theme_sections` | Belongs to theme_id. Can be reusable (shared across templates) or template-specific. |
| `ThemeComponent` | `theme_components` | Typed ('hero', 'rich_text', 'image', 'cta_button', 'content_list', 'stats', 'banner', 'card_grid', 'navigation_menu_slot'). JSON config. |
| `ThemeNavigationMenu` | `theme_navigation_menus` | Named menus per theme ('primary', etc.). |
| `ThemeNavigationItem` | `theme_navigation_items` | Tree (parent_id self-FK), polymorphic destination. **+visible_desktop, +visible_mobile** (added by CR-001-B, nullable BOOLEAN DEFAULT TRUE). |
| `ThemeAsset` | `theme_assets` | Logo/favicon/other uploaded theme media. |
| `ThemeBrandingConfig` | `theme_branding_configs` | One-per-theme. color_tokens (JSON), font_family, logo/favicon FKs. |

CR-001-B added `visible_desktop` and `visible_mobile` to ThemeNavigationItem — confirmed present in current codebase (`$casts` include these booleans). These are consumed by CR-001-C for responsive menu management.

### 5.2 Services (18 files under `app/Services/Theme/`)

| Service | Authority Over | Key Methods |
|---------|---------------|-------------|
| `ThemeService` | Theme CREATE/UPDATE/ARCHIVE | `create()`, `update()`, `archive()` |
| `ThemeActivationService` | **ONLY writer of activation state** | `activate(Theme, Principal, ?expectedActiveId)` |
| `ThemeTemplateService` | Template CRUD + UNIQUE constraint | `create()`, `update()` |
| `ThemeSectionService` | Section CRUD + reorder | `createAndPlace()`, `placeReusable()`, `reorder()` |
| `ThemeComponentService` | Component CRUD within section | `create()`, `update()`, `delete()`, `reorder()` |
| `ThemeNavigationService` | Menu/Item CRUD | `createMenu()`, `createItem()`, `deleteItem()` |
| `ThemeBrandingService` | BrandingConfig upsert per theme | `save(Theme, array, Principal)` |
| `ThemeAssetService` | Theme asset upload/archive | `upload()`, `archive()` |
| `PublicRenderer` | Public rendering pipeline | `activeTheme()`, `renderForContentKind()`, `siteChrome()` |
| `ComponentConfigValidator` | Component config validation | `assertValid(type, config)`, `componentTypes()` |
| `NavigationDestinationResolver` | Destination_type → actual URL | Resolution |
| `NavigationDestinationValidator` | Destination payload validation | Validation |

### 5.3 Test Coverage (9 files under `tests/Feature/Theme/`)

Tests cover: ThemeService CRUD, Activation singleton, schema constraints, authorization, preview, campaign projection integration, asset service, public rendering, and visibility column migrations.

---

## 6. Existing CMS Data Model Summary

**Tables owned by CMS v1:**
- `cms_pages`
- `cms_articles`
- `cms_content_revisions`
- `cms_media_assets`
- `cms_media_references`
- `cms_paths`
- `cms_homepage_assignment`

**CRITICAL:** None of these require modification for CR-001-C. Every CMS entity C exposes through Site Design UI is managed by its EXISTING service/controllers/models. C does NOT rewrite, replace, or add schema to CMS.

---

## 7. Existing Theme Data Model Summary

**Tables owned by Theme Engine v1:**
- `themes`
- `theme_activation`
- `theme_templates`
- `theme_sections`
- `theme_template_sections`
- `theme_components`
- `theme_navigation_menus`
- `theme_navigation_items` (+ visible_desktop, visible_mobile from B)
- `theme_assets`
- `theme_branding_configs`

**CRITICAL:** None of these require modification for CR-001-C. The Theme engine is structurally sufficient for Site Design concepts. Only operator-facing presentation changes.

---

## 8. Existing CMS Services Summary

Existing CMS services fully cover all write paths for CMS content. C consumes these exactly — no new CMS service layers needed.

Key consumption paths:
- C admin pages show data → call existing service queries via controller
- C creates/edits → delegates to existing `PageService`, `ArticleService`, `MediaService`
- C publishes → delegates to existing `PublicationService`

---

## 9. Existing Theme Services Summary

Existing Theme services fully cover all write paths for Theme data. C consumes these exactly.

Key consumption paths:
- C admin pages show data → call existing service queries via controller
- C creates/edits Site Design → delegates to existing `ThemeService`, `ThemeTemplateService`, `ThemeSectionService`, `ThemeComponentService`
- C manages branding → delegates to existing `ThemeBrandingService`
- C manages menus/navigation → delegates to existing `ThemeNavigationService`

---

## 10. Existing Controllers / Routes Summary

### 10.1 CMS Admin Routes (existing, unchanged)

| Prefix | Controller | Scope |
|--------|-----------|-------|
| `/admin/content/pages/*` | Cms\PageController | Full Page CRUD |
| `/admin/content/articles/*` | Cms\ArticleController | Full Article CRUD |
| `/admin/content/media/*` | Cms\MediaController | Media library |
| `/admin/content/homepage/*` | Cms\HomepageController | Homepage assignment |

### 10.2 Theme Admin Routes (existing, unchanged — Advanced/Debug)

| Prefix | Controller | Scope |
|--------|-----------|-------|
| `/admin/theme/*` | Theme\ThemeController | FULL Theme engine admin: CRUD, activate, templates, sections, components, navigation, branding, assets |

This is the Advanced/Debug surface (HD-CR001-05 approved Option A). It exposes the complete technical vocabulary (Template, Section, Component, Component Type strings, destination_type, etc.) and must remain fully functional alongside C's simplified UI.

### 10.3 No Other Relevant Admin Routes

No `/admin/site-design/*`, `/admin/page-builder/*`, `/admin/dashboard/widgets/*` routes exist yet. These would be introduced by C.

### 10.4 Public Routes (FE-CHK-009 protected)

Protected public routes in `routes/web.php`:
- Public content routing, campaigns, programs, donations, payments, webhooks

---

## 11. Existing Authorization / Permissions / Policies

### 11.1 Permissions Relevant to C

| Permission | Description | Owner Policy | Used By |
|------------|-------------|-------------|---------|
| `CONTENT_VIEW` | Read pages/articles | ContentPagePolicy, ContentArticlePolicy | CMS admin UI |
| `CONTENT_CREATE` | Create pages/articles | ContentPagePolicy, ContentArticlePolicy | CMS admin UI |
| `CONTENT_UPDATE` | Edit drafts | ContentPagePolicy, ContentArticlePolicy | CMS admin UI |
| `CONTENT_PUBLISH` | Publish/unpublish | ContentPagePolicy, ContentArticlePolicy | CMS admin UI |
| `CONTENT_ARCHIVE` | Archive pages/articles | ContentPagePolicy, ContentArticlePolicy | CMS admin UI |
| `CONTENT_MEDIA_UPLOAD` | Upload media | MediaPolicy | CMS MediaController |
| `THEME_VIEW` | View themes | ThemePolicy | Theme/index, Theme/show |
| `THEME_CREATE` | Create themes | ThemePolicy | Theme/store |
| `THEME_UPDATE` | Edit themes | ThemePolicy | Theme/update |
| `THEME_PUBLISH` | Activate/deactivate | ThemePolicy | Theme/activate |
| `THEME_ARCHIVE` | Retire themes | ThemePolicy | Theme/archive |
| `THEME_MEDIA_UPLOAD` | Upload theme assets | ThemePolicy | Theme/uploadAsset |
| **`THEME_PREVIEW`** | **Preview DRAFT Site Design** | **ThemePolicy** | **NEW from CR-001-B — NOT YET USED BY ANY ROUTE/CONTROLLER** |

### 11.2 ThemePolicy Methods

| Method | Purpose | Permission Code |
|--------|---------|-----------------|
| `view()` | General theme read | THEME_VIEW |
| `viewTheme(Theme)` | Single theme view | THEME_VIEW + org scope |
| `create()` | Theme creation | THEME_CREATE |
| `update(Theme)` | Theme edit | THEME_UPDATE + org scope |
| `publish(Theme)` | Theme activation | THEME_PUBLISH + org scope + resourceStatePredicate |
| `archive(Theme)` | Theme retirement | THEME_ARCHIVE + org scope + resourceStatePredicate |
| `uploadAsset()` | Theme media upload | THEME_MEDIA_UPLOAD |
| `manageAsset(ThemeAsset)` | Manage specific asset | THEME_UPDATE + org scope |
| `archiveAsset(ThemeAsset)` | Archive specific asset | THEME_ARCHIVE + org scope |
| **`preview(Principal, Theme)`** | **Authorize draft theme preview** | **THEME_PREVIEW** |

The `preview()` method was CREATED by CR-001-B using the established `AuthorizesUsingRbac` + `ThemeScopeResolver` + `ScopeType::Organization` pattern. It gates a DRAFT theme preview action. However, NO corresponding route/controller endpoint currently exists — the authorization gate is built but ungated.

### 11.3 Permission Gaps for C

No new permission constants or policy methods are REQUIRED by C beyond what B already provided. C reuses all existing permissions through its wrapper controllers. The only authorization gap was the `preview()` gate which B fulfilled.

---

## 12. Existing Admin CMS UI

### 12.1 Pages Layout

All admin pages under `resources/js/Pages/` (no `Admin/` subdirectory):

```
Cms/Pages/Index.vue       – List pages, pagination
Cms/Pages/Create.vue      – New page form
Cms/Pages/Edit.vue        – Editor with sidebar tabs
Cms/Articles/Index.vue    – List articles
Cms/Articles/Create.vue   – New article form
Cms/Articles/Edit.vue     – Editor with sidebar tabs
Cms/Media/Index.vue       – Media gallery grid/list
Cms/Homepage/Edit.vue     – Homepage selection dropdown
```

All use the shared `AdminLayout.vue` shell. All follow consistent card+form layout patterns.

### 12.2 Admin Shell Components

- `AdminLayout.vue` — Fixed sidebar (expanded/collapsed), mobile drawer, topbar, profile menu. Light palette.
- `UI/Button.vue`, `Input.vue`, `Select.vue`, `Textarea.vue`, `FormField.vue` — Form primitives
- `UI/Card.vue` — Card containers
- `UI/Breadcrumb.vue` — Breadcrumb trail
- `UI/PageHeader.vue` — Page title/action header
- `UI/StatusBadge.vue` — Status chips
- `UI/EmptyState.vue` — Empty/no-data placeholder
- `UI/MediaGallery.vue` — Image gallery display
- `UI/RichTextEditor.vue` — Tiptap editor integration
- `UI/Icon.vue` — Curated Lucide subset (~27 icons, extended by FE-CHK-009)

---

## 13. Existing Theme Admin UI

### 13.1 Pages (2 files)

```
Theme/Index.vue           – Theme list + create form
Theme/Show.vue            – Theme detail/editor (templates, sections, components tree)
```

`Theme/Show.vue` is the **largest admin Vue page** — it renders the full technical hierarchy (Templates → Sections → Components) and provides inline editing for each level. This IS the Advanced/Debug view (HD-CR001-05).

### 13.2 Current State

Theme admin exposes ALL technical vocabulary: Template names, Section positions, Component type strings, navigation destination_type values, brand colors as JSON tokens, asset upload buttons. This is exactly the pain point CR-001-C addresses: normal operators don't need to see `navigation_menu_slot` as a component type — they should see "Navigation Menu."

---

## 14. Existing Preview Capability

### 14.1 What Exists

| Artifact | Status |
|----------|--------|
| `PermissionRegistry::THEME_PREVIEW` | EXISTS (created by CR-001-B) |
| `ThemePolicy::preview(Principal, Theme)` | EXISTS (created by CR-001-B) |
| `ThemePolicyPreviewTest` | EXISTS (feature test, tests authorization) |

### 14.2 What Does NOT Exist

| Artifact | Status |
|----------|--------|
| Preview route in `routes/web.php` | MISSING |
| PreviewController or preview() action on ThemeController | MISSING |
| Preview Vue page/component | MISSING |
| Preview service class | MISSING |

### 14.3 Conclusion

The authorization gate is built (by B), but the **endpoint implementation is missing entirely**. CR-001-C must create the preview endpoint: a route, controller action, and minimal Vue page that renders a selected DRAFT theme's output to an authorized operator.

---

## 15. Existing Publish / Schedule Capability

### 15.1 Current Publish Semantics

| Domain | Publisher | Mechanism |
|--------|-----------|-----------|
| CMS Pages/Articles | `PublicationService` | Transition revision state machine (DRAFT→PUBLISHED→UNPUBLISHED→ARCHIVED) |
| Scheduled transitions | `PublicationService::executeScheduledTransition()` | Laravel Scheduler (`content:run-scheduled-transitions` Artisan command) + cron |
| Theme activation | `ThemeActivationService::activate()` | Singleton pattern — one Theme at a time, checked via expectedActiveThemeId concurrency guard |
| CMS scheduling | `CmsPage.schedule_at`, `CmsArticle.publish_at`, `unpublish_at` datetime columns | Checked by Laravel Scheduler periodic run |

### 15.2 Site Design Publish

Site Design "publish" = activating a Theme. This means:
1. Operator edits a DRAFT-status Theme (copy-on-write from existing ACTIVE Theme)
2. When ready to publish: `ThemeActivationService::activate(draftTheme, principal)` flips the singleton pointer
3. No second-person approval (HD-CR001-02, Option A)
4. Single-actor publish — any ThemePolicy.authorized operator can publish

**C must ensure the publish flow works correctly with the Site Design abstraction.** The existing `ThemeActivationService` already implements this correctly.

---

## 16. Existing Media Capability

| Layer | Implementation |
|-------|---------------|
| Storage | `CmsMediaAsset` table tracks uploads with status (active/archived), stored_filename, size, dimensions |
| Upload | `MediaService::upload()` via `Cms\MediaController` |
| Token URLs | `MediaTokenResolver` generates short-lived signed URLs |
| Cleanup | `MediaCleanupService` purges archived physical files |
| References | `CmsMediaReference` join table links assets to pages/articles/revisions |
| Policy | `MediaPolicy` governs who can upload/manage/archive assets |
| Private files | Manual Transfer evidence files are private (separate from CMS media); never exposed through Media Manager listing |

C does NOT introduce new media storage or change existing contracts.

---

## 17. Existing Navigation / Menu Capability

| Model | Table | Key Columns |
|-------|-------|-------------|
| `ThemeNavigationMenu` | `theme_navigation_menus` | theme_id, code ('primary'), name, ulid |
| `ThemeNavigationItem` | `theme_navigation_items` | menu_id, parent_id (self-FK), label, destination_type (SYSTEM_ROUTE|CMS_CONTENT|EXTERNAL_URL), destination_route, destination_content_ulid, position, visible, **visible_desktop**, **visible_mobile** |

The `visible_desktop` and `visible_mobile` columns were added by CR-001-B (confirmed present in `$casts`). Operators will use these for responsive Hybrid IA (HD-CR001-04): desktop shows Infaq+Sedekah separately; mobile consolidates into one ZISWAF entry.

C wraps this existing structure through `SiteDesignController.menu*` endpoints, exposing simplified terminology ("Menus") instead of `ThemeNavigationMenu` vocabulary.

---

## 18. Existing Header/Footer Representation

Header and Footer are NOT standalone domain entities. They are **composed from existing Theme data**:

- **Header** = `ThemeBrandingConfig` (logo, favicon) + `ThemeNavigationMenu` items rendered via `PublicRenderer::siteChrome()`
- **Footer** = Institutional content (hardcoded per organization in Blade/Vue) + social links + legal links — NOT driven by Theme data currently

There is NO `Header` model/table. There is NO `Footer` model/table. Both are PATTERN COMPOSITIONS assembled from branding + navigation + template rendering.

C exposes simplified "Header & Footer" management through Site Design UI by reading/writing the underlying `ThemeBrandingConfig` + `ThemeNavigationMenu/Item` structures.

---

## 19. Existing Appearance / Branding Representation

| Model | Table | Key Config |
|-------|-------|-----------|
| `ThemeBrandingConfig` | `theme_branding_configs` | `color_tokens` (JSON), `font_family` (string), `logo_theme_asset_id` (FK), `favicon_theme_asset_id` (FK) |

Branding is one-per-theme, validated by `BrandingConfigValidator`. Color tokens follow a closed set, font family an allow-list. Logo/favicon resolved via `ThemeAssetTokenResolver`.

C exposes this as "Appearance" or "Branding" in the Site Design admin. No schema changes needed — just operator-facing naming/UI.

---

## 20. Existing Home Representation

Home is represented by **three canonical objects working together**:

1. `CmsHomepageAssignment` — Which CmsPage is the homepage (singleton)
2. `ThemeActivation` — Which Theme is active (singleton)
3. Active Theme → `ThemeTemplate` for content_kind='home' → Sections → Components

**OR** — if no CMS page is designated as home, the active Theme's home template is rendered directly.

C does NOT introduce a new "Home" table. It simplifies the OPERATOR EXPRESSION of this three-way relationship into a "Manage Home" Site Design concept.

---

## 21. Existing Page Representation

Pages are fully operational CMS entities with:
- Create, edit, slug, status (DRAFT/PUBLISHED/ARCHIVED)
- Publish, unpublish, schedule
- SEO/meta fields in `CmsContentRevision`
- Permissions covered by CONTENT_* codes
- Preview: implicit — published pages are publicly readable; unpublished drafts are not

No changes to Page representation needed for C. C simply provides a potentially different VIEW/PROMPT for the existing CMS Page admin.

---

## 22. Existing Article/News Representation

Articles follow the same structure as Pages (identity + revisions + scheduling). News classification (article_type) lives on `CmsContentRevision`, NOT on `CmsArticle`. No dedicated News model/table exists. Q33 confirms News is Article classification/type/category, not a separate entity. C preserves this.

---

## 23. Normal Operator UX Problem Map

### 23.1 What Normal Operators Cannot Do Today

| Need | Current Reality | Gap |
|------|----------------|-----|
| Change site colors/logo | Must use `ThemeBrandingConfig` JSON directly | Complex JSON editing vs. simple color picker |
| Create/edit navigation menus | Must understand destination_type polymorphism | Polymorphic destinations opaque to non-technical |
| Add/remove navigation items | Must understand ThemeComponent `navigation_menu_slot` internal mechanism | Vocabulary mismatch |
| Understand Site Design | No dedicated "Site Design" concept exists | Entire Theme admin is "Technical" |
| Preview unpublished changes | No preview endpoint exists despite auth gate | Auth gate built by B, endpoint missing |
| Publish Site Design | Must activate Theme from Tech admin | Confusing — "activate a theme" ≠ "publish Site Design changes" |

### 23.2 What Normal Operators CAN Already Do

| Capability | How |
|-----------|-----|
| Create/edit CMS pages | `Cms\*Controller` + `Cms/*Vue` pages |
| Create/edit CMS articles | Same as above |
| Manage media library | `Cms\MediaController` + `Cms/Media/Index.vue` |
| Set homepage designation | `Cms\HomepageController` |
| View published site | Public frontend routes |

### 23.3 The Core Problem

The existing Theme admin (`ThemeController` + `Theme/Index.vue` + `Theme/Show.vue`) exposes engineering vocabulary (Template, Section, Component, `navigation_menu_slot`, destination_type polymorphism, JSON color tokens) to all admins. Normal operators need a SIMPLIFIED surface that maps:

| Technical Concept | Operator Concept |
|------------------|-----------------|
| Theme | Site Design |
| Theme → Template → Section → Component chain | Page Structure (simplified) |
| BrandingConfig JSON | Appearance / Colors / Typography |
| NavigationMenu + NavigationItem | Navigation / Menus |
| Theme activation | Publish Site Design |
| Component type `navigation_menu_slot` | Navigation Menu Block |

---

## 24. Proposed Site Design Foundation

### 24.1 Copy-On-Write Editing Flow (Locked Architecture §26)

CR-001 architecture §26 establishes a **copy-on-write / Draft-Publish** model for Site Design editing. Verified against actual repository contracts (`ThemeService`, `ThemeActivationService`, `ThemeActivation` model):

**Actual Theme Status Lifecycle** (from `ThemeService::create()` and `archive()`)

```
DRAFT       ← newly created Theme rows (default status)
    ↓
ACTIVE      ← when activated via ThemeActivationService::activate()
    ↓
INACTIVE    ← when replaced by another Theme activation (previous live → INACTIVE)
    ↓
ARCHIVED    ← terminal state via ThemeService::archive(DRAFT|INACTIVE → ARCHIVED)
```

**Copy-On-Write Flow Verified from Schema:**

1. **Clone step**: When an operator begins editing the live design, C creates a NEW Theme row via `ThemeService.create()`. This new row starts with `status = DRAFT`. The operator's editor then copies Template/Section/Component/Navigation/Branding data from the current live Theme into this DRAFT Theme using the EXISTING services (`ThemeTemplateService.create()`, `ThemeSectionService.createAndPlace()`, `ThemeComponentService.create()`, `ThemeNavigationService.createMenu/createItem()`, `ThemeBrandingService.save()`). These services all operate by FK `theme_id` — they create sub-tree nodes pointing to the NEW DRAFT theme.

2. **Shared structures**: Navigation menus are copied per-theme (each menu belongs to a specific `theme_id`). Branding config is one-per-theme (upserted by `ThemeBrandingService.save()`). All cloned data lives under the DRAFT Theme's `theme_id` FK chain — NOTHING is shared between active and draft after cloning.

3. **Working copy distinction**: The DRAFT Theme is distinguished purely by its `status` column value (`DRAFT`). No new schema needed. The editable working copy IS the DRAFT Theme itself — all Templates/Sections/Components/Navigation/Branding for that Theme are independent from any other Theme's data.

4. **Publish/Promotion**: When the operator publishes, C calls `ThemeActivationService::activate(draftTheme, principal)`. This service (verified in code):
   - Locks the singleton `theme_activation` pointer row (row id=1)
   - Locks the candidate DRAFT Theme
   - Sets the PREVIOUS active Theme's status to `INACTIVE`
   - Sets the candidate DRAFT Theme's status to `ACTIVE`
   - Updates the pointer's `active_theme_id` to the candidate
   - Audits both deactivate and activate events
   - Validates component configs BEFORE activation (rejects invalid, never partially applies)

5. **Active design stability**: The currently active public design remains stable throughout editing because ALL editor writes target the DRAFT Theme's data. The active Theme is NEVER modified in-place by the editor. Only the `activate()` call mutates the active pointer — and even then, it flips status atomically within a database transaction with row-level locking.

6. **Zero new schema required**: The existing `themes.status` column (DRAFT/ACTIVE/INACTIVE/ARCHIVED) fully supports the copy-on-write workflow. No migration needed.

7. **C controller responsibility**: C's `SiteDesignController` must provide a `cloneToDraft()` action that orchestrates the full subtree copy from active → DRAFT using existing Theme services. It also provides `edit()`, `saveBrand()`, `saveMenu()`, etc., all operating on the DRAFT theme.

8. **Reusable services**: `ThemeService.create()` — creates new DRAFT Theme. `ThemeTemplateService.create()` / `ThemeSectionService.createAndPlace()` / `ThemeComponentService.create()` — copies template tree. `ThemeBrandingService.save()` — copies branding. `ThemeNavigationService.createMenu()` / `createItem()` — copies navigation. `ThemeAssetService.upload()` — copies assets if brand image references change.

9. **New service required by C**: **Yes — one new application-layer service**. A `SiteDesignCloneService` is required by C to orchestrate the subtree copy. This wraps existing domain services; no new database tables. See detailed contract below.

### 24.1A SiteDesignCloneService Contract

#### A. Atomic Clone Transaction

```php
SiteDesignCloneService::cloneFromActive(Theme $source, Principal $actor): Theme
{
    return DB::transaction(function () use ($source, $actor) {
        // ... complete clone logic including any new ThemeAsset rows ...
        // Note: filesystem writes occur outside DB tx (see §E below)
    });
}
```

The entire mutable presentation aggregate clone MUST execute database operations inside ONE `DB::transaction(...)`. The transaction boundary includes creation/cloning of ALL mutable presentation records for the new DRAFT design.

**Semantic guarantee:**
- SUCCESS = complete internally consistent DRAFT aggregate exists
- FAILURE = NO partially cloned DRAFT aggregate remains persisted (full rollback)

No partial recovery. No asynchronous clone processing. Shared-hosting compatible (uses Laravel's canonical `DB::transaction()` mechanism).

**Important distinction:** `DB::transaction()` guarantees database atomicity only. Physical filesystem writes (e.g., ThemeAsset file copies described in §E) occur OUTSIDE the DB transaction. Compensation logic is required for failure cases where a file was written but the DB insert later failed. See §E for exact mechanism.

#### B. Clone Aggregate Definition

The complete mutable presentation aggregate that must be re-cloned includes:

```
Theme (new row, status=DRAFT via ThemeService.create())
  ├── Templates          → each gets new theme_id pointing to new DRAFT Theme
  │   └── Sections       → each gets new theme_id + new parent template reference
  │       └── Components → each gets new parent section_id
  ├── NavigationMenus    → each gets new theme_id pointing to new DRAFT Theme
  │   └── NavigationItems → each gets new menu_id + resolved new parent_id (root-first algorithm)
  ├── BrandingConfig     → upserted under new theme_id via ThemeBrandingService.save()
  └── ThemeAssets        → NEW rows owned by DRAFT Theme (see §D below)
```

**Distinct Ownership Rules Verified from Repository Evidence:**

| Record Type | Action | Rationale |
|-------------|--------|-----------|
| Themes, Templates, Sections, Components, NavigationMenus, NavigationItems, BrandingConfig | **MUST be re-created** under new FK references | Mutable presentation data; editing one must not affect the other |
| ThemeAssets | **NEW DB row required** per active branding asset. `theme_id` is NOT NULL (cascadeOnDelete). Each asset row is canonically owned by exactly ONE Theme. | BrandingConfig references ThemeAsset via FK (`logo_theme_asset_id`, `favicon_theme_asset_id`). Cloning requires creating new ThemeAsset DB rows with `theme_id = new_draft_theme_id`. |
| Physical storage files | **NEW copy required**. Source file is read, validated against source schema, and written to new location under DRAFT Theme's asset directory. | Same immutable file may theoretically be referenced by multiple DB rows in safe storage backends (e.g., object storage with versioning), but the canonical model requires separate ThemeAsset ownership. Safe default: always copy. |

**Migration Impact:** Zero. All cloning uses existing tables and columns. No schema change required.

#### C. ID Remapping Map

Deterministic old-ID → new-ID mappings maintained within the single transaction:

| Old FK Column | New FK Column | Map Key |
|---------------|--------------|---------|
| `theme_template.theme_id` | `-> new_theme.id` | source theme ulid → new theme ulid |
| `theme_section.theme_id` | `-> new_theme.id` | source theme ulid → new theme ulid |
| `theme_template_sections.section_id` | `-> new_section.id` | source section ulid → new section ulid |
| `theme_component.theme_section_id` | `-> new_section.id` | source section ulid → new section ulid |
| `theme_navigation_menu.theme_id` | `-> new_theme.id` | source theme ulid → new theme ulid |
| `theme_navigation_item.theme_navigation_menu_id` | `-> new_menu.id` | source menu id → new menu id |
| `theme_branding_config.theme_id` | `-> new_theme.id` | source theme ulid → new theme ulid |
| `theme_branding_config.logo_theme_asset_id` | `-> new_asset.id` | source logo asset ulid → new logo asset ulid |
| `theme_branding_config.favicon_theme_asset_id` | `-> new_asset.id` | source favicon asset ulid → new favicon asset ulid |

Only relationships actually present in repository schema are mapped. No invented columns.

#### D. ThemeAsset Ownership & Complete Clone Row Contract

**Verified repository contract:**

- `ThemeAsset.theme_id` is `NOT NULL` with `cascadeOnDelete()` → each asset canonically owned by exactly ONE Theme
- `ThemeBrandingConfig.logo_theme_asset_id` → FK to `theme_assets.id` (`nullOnDelete`)
- `ThemeBrandingConfig.favicon_theme_asset_id` → FK to `theme_assets.id` (`nullOnDelete`)
- `BrandigConfig` references assets by their `ulid` type (CHAR(26)), not numeric PK — BrandingConfig FK uses BIGINT but the ULID is what's referenced in presentation logic.

**A. Source Storage Validation**

Before copying, SiteDesignCloneService must resolve the source ThemeAsset physical object using the canonical theme disk:

```php
$sourceDisk = config('theme.disk');
$sourcePath = 'theme/' . $source->created_at->format('Y/m') . '/' . $source->stored_filename;
$exists = Storage::disk($sourceDisk)->exists($sourcePath);
```

Verify the source object exists and is readable through the canonical Storage disk. If the source object is missing or cannot be read:

- FAIL CLOSED
- Abort the aggregate clone transaction immediately
- Do NOT create a replacement ThemeAsset DB row
- Do NOT activate or return a partially usable DRAFT aggregate
- Do NOT mutate the ACTIVE/source asset in any way

This validation occurs OUTSIDE the DB transaction because it is a read-only pre-condition check on an immutable existing file.

**B. Fresh Destination Construction**

Using the already-defined fresh `$new_ulid` and source/canonical extension `$ext`, construct the clone destination according to the existing ThemeAssetService path convention:

```php
$newStoredFilename = "{$new_ulid}.{$ext}";
$newDestinationPath = 'theme/' . now()->format('Y/m') . '/' . $newStoredFilename;
```

Requirements enforced:
- `destination !== source` — different ULID guarantees no path collision
- Destination is fresh and collision-safe via new ULID
- Must not overwrite an existing object (fresh ULID prevents this)
- ACTIVE/source path remains completely unchanged

**C. Physical Copy Operation**

Perform the explicit copy using the canonical Laravel Storage API:

```php
$copyResult = Storage::disk(config('theme.disk'))->copy($sourcePath, $newDestinationPath);
```

The return value MUST be checked. If `$copyResult === false` or otherwise fails (exceptions are possible for permission/disk errors):

- FAIL CLOSED
- Abort the aggregate clone
- Do NOT proceed to successful ThemeAsset DB-row creation
- Do NOT report clone success
- Do NOT mutate/delete the source object
- Record `$newDestinationPath` in compensation list so cleanup proceeds if rollback triggers later

If copy succeeds, proceed to Step D.

**D. Compensation Registration**

After a successful copy, IMMEDIATELY record the NEW clone destination path in the clone-created-path compensation collection (the same array/collection used throughout §E Filesystem Compensation Strategy):

```php
$cloneDestinations[] = [
    'disk' => config('theme.disk'),
    'path' => $newDestinationPath,
];
```

Only successfully created clone destinations may enter this list. Never add:
- source path / ACTIVE path / pre-existing unrelated path

Then proceed to the complete ThemeAsset persistence contract below.

**Actual ThemeAsset migration columns (all NOT NULL unless marked nullable):**

| Column | Type | Nullable? | Default | Clone Assignment Rule |
|--------|------|-----------|---------|----------------------|
| `id` | BIGINT PK | NO | AUTO_INCREMENT | GENERATED (do not set) |
| `ulid` | CHAR(26) | NO | UNIQUE constraint | NEW fresh ULID derived via `Str::ulid()` |
| `theme_id` | BIGINT FK | NO | cascadeOnDelete | `$new_draft_theme->id` |
| `disk` | VARCHAR(32) | NO | none | Same disk used for `Storage::copy()` — `config('theme.disk')` |
| `stored_filename` | VARCHAR(255) | NO | none | `"{$new_ulid}.{$ext}"` (basename only, same format as `ThemeAssetService::upload()` line 63) |
| `original_filename` | VARCHAR(255) | NO | none | Preserve source `original_filename` (sanitized identically to `sanitizeOriginalFilename()` if needed) |
| `mime_type` | VARCHAR(100) | NO | none | Preserve from source |
| `extension` | VARCHAR(10) | NO | none | Preserve from source |
| `size_bytes` | BIGINT | NO | none | Preserve from source |
| `width` | INT | YES (nullable) | null | Preserve from source (safe to copy even when nullable) |
| `height` | INT | YES (nullable) | null | Preserve from source (safe to copy even when nullable) |
| `sha256` | CHAR(64) | NO | none | Preserve from source |
| `status` | VARCHAR(16) | NO | `'ACTIVE'` | `'ACTIVE'` (default — may be omitted due to DB default) |
| `uploaded_by_principal_id` | BIGINT FK | NO | restrictOnDelete | `$actor->id` where `$actor` is the canonical `Principal` passed to `cloneFromActive(Theme $source, Principal $actor)` |
| `archived_by_principal_id` | BIGINT FK | YES | null | null (not archived) |
| `archived_at` | DATETIME | YES | null | null |
| `created_at` | DATETIME | YES | auto | AUTOMATIC (do not set) |
| `updated_at` | DATETIME | YES | auto | AUTOMATIC (do not set) |

**Actor attribution verification:**

The `cloneFromActive()` method signature is `cloneFromActive(Theme $source, Principal $actor): Theme`. The `$actor` parameter IS the canonical `App\Models\Rbac\Principal` model whose `$actor->id` directly corresponds to `principals.id`. Therefore:

```php
uploaded_by_principal_id = $actor->id
```

This represents the Principal performing the clone operation — NOT preserving the original uploader. Cloning creates new ownership attribution.

**Complete DB row creation inside aggregate transaction:**

```php
$clonedAsset = new ThemeAsset;
$clonedAsset->forceFill([
    'ulid'                      => $new_ulid,                    // Fresh ULID
    'theme_id'                  => $new_draft_theme->id,         // DRAFT owner
    'disk'                      => config('theme.disk'),         // Same disk as physical copy
    'stored_filename'           => "{$new_ulid}.{$extension}",   // Basename only, basename convention
    'original_filename'         => $source->original_filename,   // Preserved
    'mime_type'                 => $source->mime_type,           // Preserved
    'extension'                 => $source->extension,           // Preserved
    'size_bytes'                => $source->size_bytes,          // Preserved
    'width'                     => $source->width,               // Preserved (nullable-safe)
    'height'                    => $source->height,              // Preserved (nullable-safe)
    'sha256'                    => $source->sha256,              // Preserved
    'status'                    => 'ACTIVE',                     // Default value
    'uploaded_by_principal_id'  => $actor->id,                   // Clone actor (canonical Principal)
]);
// created_at / updated_at AUTOMATIC (do not set)
// id GENERATED (do not set)
$clonedAsset->save();
```

**BrandingConfig remap after clone:**

After creating all cloned ThemeAsset rows and building the old→new ID map:
```
old_logo_theme_asset_id  → new_logo_theme_asset_id
old_favicon_theme_asset_id → new_favicon_theme_asset_id
```

When constructing the cloned BrandingConfig (via `ThemeBrandingService.save()` under the new DRAFT theme), reference ONLY the new cloned asset IDs. Never reference source ACTIVE-owned asset IDs.

#### E. Filesystem Compensation Strategy

Physical file copies for ThemeAsset cloning happen OUTSIDE `DB::transaction()`. This means:

```
FILE COPY (outside DB tx) → ... → DB INSERT (inside DB tx)
      ↑                                    ↑
   Succeeds                           May fail → roll back DB
```

If the DB insert fails after one or more physical files were copied:

**Required behavior:**
1. DB transaction rolls back (all cloned database rows deleted)
2. Newly created clone-only storage objects MUST be cleaned up
3. ACTIVE Theme assets are NEVER deleted or modified
4. No intentional orphan clone-only files remain

**Implementation strategy:**
- Each newly copied file destination path is recorded in a mutable array within the service closure scope
- On DB transaction failure (exception catch), iterate all recorded paths and delete them via `Storage::disk(config('theme.disk'))->delete($path)`
- If file deletion itself fails, log/report according to existing `MediaCleanupService` orphan-cleanup pattern and allow cron-based cleanup to handle residue
- ACTIVE theme files are never touched (different storage paths, different filenames, never placed in cleanup list)

**Destination safety:**
- Destination path is ALWAYS newly generated with fresh ULID — never reuses source filename or path
- Destination MUST NOT overwrite any existing object
- Cleanup list contains ONLY destinations successfully created by THIS specific clone operation
- No pre-existing destination may ever enter the cleanup list

**Failure scenarios:**

| Failure Point | DB State | Filesystem State | Cleanup Action |
|---------------|----------|-----------------|----------------|
| Before any file copy | Clean | Clean | N/A |
| After file A copy, before file B copy | Clean (tx rolled back) | File A exists | Delete File A |
| After all file copies, DB insert fails | Rolled back | All files exist | Delete all files |
| Physical file write fails | Clean | Partial (if some succeeded) | Delete any partial writes |
| Compensating Storage::delete() itself fails | Already rolled back | Residue remains | Log per MediaCleanupService pattern, rely on cron cleanup |

#### F. Relationship Isolation Invariant

Post-clone invariant:

> Editing the new DRAFT Site Design MUST NOT mutate any mutable presentation row used by the currently ACTIVE Theme. The ACTIVE Theme and its mutable child graph remain unchanged until explicit Theme activation via `ThemeActivationService::activate()`.

**Precise statement (corrected):**

> No DRAFT mutable presentation record may depend on a mutable Theme-owned record belonging to the ACTIVE Theme. Specifically:
> - DRAFT Templates/Sections/Components reference only NEW cloned rows
> - DRAFT NavigationMenus/NavigationItems reference only NEW cloned rows
> - DRAFT BrandingConfig references only NEW cloned ThemeAsset rows
> - ACTIVE ThemeTemplate/Section/Component/Menu/Item/BrandingConfig/ThemeAsset rows are never queried for write by clone operation

Immutable/global records that do not carry `theme_id` ownership can remain shared, but no such global mutable presentation records exist in the verified schema.

#### G. Navigation Clone Algorithm (Root-First / Child-Second)

Verified: `ThemeNavigationService::createItem(menu, payload, actor)` accepts `parent_id` in payload and enforces one-level nesting (parent.parent_id must be null). No post-create update method exists.

**IMPORTANT SERVICE CONTRACT GAP (C-11):**

Actual `createItem()` currently hardcodes only `visible => true` in its payload mapping. It does NOT persist:
- `visible_desktop` (column defaults TRUE per CR-001-B migration)
- `visible_mobile` (column defaults TRUE per CR-001-B migration)

Additionally, model casts verify all three fields exist as booleans:
```php
protected function casts(): array { return ['visible' => 'boolean', 'visible_desktop' => 'boolean', 'visible_mobile' => 'boolean']; }
```

During CR-001-C implementation, `ThemeNavigationService::createItem()` MUST receive a NON-SCHEMA service modification to accept and persist `visible_desktop` and `visible_mobile` from payload when provided. Backward compatibility preserved: if omitted, DB default (TRUE) applies. The existing `visible => true` hardcoded behavior is preserved unchanged.

All cloning operations during Site Design clone MUST pass these values through createItem() payloads to preserve source fidelity:
```
[
  label, destination_type, destination_route, destination_content_kind,
  destination_content_ulid, destination_external_url, parent_id,
  visible, visible_desktop, visible_mobile   ← now persisted by createItem()
]
```

**Algorithm:**

**STEP 1:** Clone NavigationMenu under NEW DRAFT Theme via `ThemeNavigationService::createMenu(newDraftTheme, payload, actor)`. Build `old_menu_id → new_menu_id` map.

**STEP 2:** Clone ROOT items first (`source.parent_id == null`):
- For each source root item, call `ThemeNavigationService::createItem(newMenu, [parent_id => null, ...clone fields including visible/visible_desktop/visible_mobile], actor)`
- Record `old_root_item_id → new_root_item_id` map

**STEP 3:** Clone CHILD items (`source.parent_id != null`):
- For each source child item, resolve `source.parent_id` through `old_root_item_id → new_root_item_id` map
- Call `ThemeNavigationService::createItem(newMenu, [parent_id => resolved_new_parent_id, ...clone fields including visible/visible_desktop/visible_mobile], actor)`
- If source parent cannot be resolved → FAIL CLOSED, abort aggregate clone transaction

**STEP 4:** No post-create parent UPDATE ever executed. No raw Eloquent `update()` or `setParent()` method called. No new `ThemeNavigationService` method required. All parent linkage occurs at creation time via `createItem()` payload.

#### H. Activation Boundary Preserved

`SiteDesignCloneService` does NOT invent a second publishing system. Theme design activation continues exclusively through the existing canonical `ThemeActivationService::activate(draftTheme, principal)` according to its existing verified contract (lockForUpdate on singleton pointer, validation before activation, atomic INACTIVE→ACTIVE flip, audit trail).

Cloning and activation are separate operations. An operator may clone multiple times without activating, activate at most once per operation, and always retains the ability to archive or discard clones without affecting the live design.

#### I. Authorization Preserved

All authorization gates defined in §34 apply before the clone operation begins:
- `THEME_VIEW` required to list themes and initiate clone
- `THEME_CREATE` required to create the new DRAFT Theme row (delegated to `ThemeService.create()`)
- `THEME_UPDATE` required for sub-tree creation operations (delegated to existing service methods)

The controller entry point enforces policy BEFORE calling `SiteDesignCloneService::cloneFromActive()`. Service-level invariants still fail safely if called directly.

#### J. Test Plan Additions

Future tests for `SiteDesignCloneService` must explicitly include:

1. Full aggregate clone succeeds atomically within DB transaction
2. Active Theme remains completely unchanged post-clone
3. Cloned mutable children receive NEW IDs (templates, sections, components, menus, items, brandings)
4. Cloned children reference NEW cloned parents (not source IDs)
5. NavigationItem parent_id resolved via root-first → child-second algorithm (never verbatim copy)
6. Nested navigation hierarchy preserved across multi-level trees
7. Unresolved parent (via root→new-root map) fails closed (transaction rolls back)
8. Failure mid-clone rolls back ALL cloned data (draft, templates, sections, components, navigation, branding)
9. DRAFT ThemeAsset is a NEW row owned by draft theme_id, not reused from ACTIVE theme
10. DRAFT BrandingConfig references new asset ID (old asset → new asset remap verified)
11. Deleting/archiving ACTIVE Theme asset cannot break DRAFT branding
12. Deleting DRAFT Theme cannot delete ACTIVE Theme assets
13. Asset copy failure causes DB clone rollback
14. If physical file copy occurred before DB failure, clone-only file cleanup occurs
15. ACTIVE physical asset remains untouched (verified by checksum/path comparison)
16. Activation delegation to `ThemeActivationService::activate()` works correctly post-clone
17. Filesystem compensation failure tolerance: Simulate Storage::delete() compensation failure. Verify: original clone failure remains surfaced; cleanup failure is logged/reported; ACTIVE/source asset remains unchanged; no false success returned; orphan-cleanup fallback semantics available.
18. Null branding asset validity: When source BrandingConfig has logo_theme_asset_id=null and/or favicon_theme_asset_id=null, verify clone succeeds where canonical schema permits null; preserves null; does not invent an asset; does not treat asset as mandatory; creates no unnecessary physical copy.
19. visible_desktop=false remains false after clone
20. visible_mobile=false remains false after clone
21. Visible=true values remain true after clone
22. Omitted visibility payload preserves backward-compatible createItem() behavior (defaults to TRUE)
23. Existing callers of createItem() without visible fields remain compatible
24. Source navigation item (all rows) is completely unchanged by clone operation
25. New ThemeAsset row has all required fields populated per verified migration schema: ulid=fresh ULID, theme_id=DRAFT theme, disk=config('theme.disk'), stored_filename=basename convention, original_filename preserved from source, uploaded_by_principal_id=$actor->id (clone actor canonical Principal), status='ACTIVE', metadata fidelity on mime_type/extension/size_bytes/sha256/width/height

---

## 25. Reuse vs Wrap vs Adapt vs New Matrix

| Concept | Classification | Basis |
|---------|---------------|-------|
| All Theme models | REUSE | IMP-006 models are structurally sufficient |
| All CMS models | REUSE | IMP-005 models unchanged |
| All Theme services | REUSE | Delegation pattern from C controllers |
| All CMS services | REUSE | Delegation pattern from C controllers |
| Existing `ThemeController` routes | REUSE (Advanced/Debug) | HD-CR001-05 requires retention |
| Existing `Cms\*Controller` routes | REUSE (unchanged) | CMS is separate from Site Design |
| Existing `Theme/Index.vue` + `Theme/Show.vue` | REUSE (Advanced/Debug) | HD-CR001-05 requires retention |
| Site Design Overview page | WRAP | Thin list of Themes with DRAFT/ACTIVE badges |
| Site Design Branding page | WRAP | Thin wrapper over `ThemeBrandingService.save()` |
| Site Design Menus page | WRAP | Thin wrapper over `ThemeNavigationService.createMenu/createItem/deleteItem` |
| Site Design Preview endpoint | PARTIAL NEW | Auth gate exists (B), route/controller/page created by C; full rendered preview requires PublicRenderer integration owned by E (see §27) |
| Site Design Publish action | WRAP | Thin delegate to `ThemeActivationService::activate()` |
| Page Builder block LISTING | READ-ONLY VIEW | Shows existing Template→Section→Component structure |
| `SiteDesignController` | NEW | Thin controller for Site Design admin endpoints |
| `SiteDesignCloneService` | NEW | Orchestrates subtree copy from active Theme → DRAFT working copy |
| Vue admin pages | NEW | `Admin/SiteDesign/*.vue` directories |

---

## 26. Advanced / Debug Theme Strategy

Per HD-CR001-05 (Option A):

- Existing `ThemeController` routes stay LIVE at `/admin/theme/*`
- Existing `Theme/Index.vue` + `Theme/Show.vue` stay UNMODIFIED
- Accessible to anyone with `THEME_VIEW` permission (same current gate)
- Clearly labeled "Advanced" or "Advanced/Debug Theme Engine"
- NOT the default landing page for Theme-related navigation
- Normal operators navigate through the new Site Design UI first
- Power users/support staff can access Advanced directly via known URL

**No coordination conflict with FE-CHK-009:** The FE-CHK-009 dirty work modifies `AdminLayout.vue` (global admin shell) and `Icon.vue` (icon registry). These do not touch `ThemeController` or `Theme/*` Vue pages.

---

## 27. Preview Foundation Plan

### 27.1 What Exists (from CR-001-B)

| Artifact | Path | Status |
|----------|------|--------|
| `THEME_PREVIEW` constant | `app/Services/Rbac/PermissionRegistry.php` | ✅ Created |
| `ThemePolicy::preview()` method | `app/Policies/ThemePolicy.php` | ✅ Created |
| `ThemePolicyPreviewTest` | `tests/Feature/Theme/ThemePolicyPreviewTest.php` | ✅ Created |

### 27.2 Phase C vs Phase E Preview Boundary

**Phase C V1 Preview Capability:** C creates the **authorization gate, route/controller scaffold, and operator-facing preview page**. However, the actual rendered preview of a DRAFT working copy has a constraint:

- `PublicRenderer::renderForContentKind(contentKind, content)` reads from `$this->activeTheme()` internally — it resolves the ACTIVE Theme pointer, not an arbitrary Theme instance passed in
- Therefore, a preview endpoint in C that calls `PublicRenderer::renderForContentKind()` will render the CURRENTLY ACTIVE theme's composition, NOT the DRAFT working copy
- This is architecturally correct: `PublicRenderer.php` WRITE OWNER is CR-001-E, not C. C must NOT modify it.

**Phase C V1 Deliverable:**
| Artifact | Owner | Can Deliver? |
|----------|-------|-------------|
| Preview route (`/admin/site-design/{theme}/preview`) | CR-001-C | YES — admin route registration |
| Preview controller action | CR-001-C | YES — authorizes via `ThemePolicy::preview()`, validates draft Theme exists and is DRAFT status |
| Preview authorization gate | CR-001-C | YES — enforced by `ThemePolicy::preview(actor, theme)` returning 403 if unauthorized |
| Preview context/resource validation | CR-001-C | YES — confirms theme is DRAFT status before proceeding |
| Operator-facing preview page (Inertia render) | CR-001-C | YES — receives props from controller |
| Full rendered preview of DRAFT Theme | CR-001-E | Requires `PublicRenderer` integration to accept non-active theme for rendering |

### 27.3 Preview Contract (Phase C V1)

1. User navigates to `/admin/site-design/{theme_ulid}/preview`
2. `SiteDesignController::preview(theme)` resolves actor
3. Authorize via `ThemePolicy::preview(actor, theme)` — fails 403 if unauthorized
4. If theme.status !== DRAFT, return redirect to regular Site Design edit page
5. Render via `PublicRenderer::renderForContentKind('home', ...)` with existing active-theme resolution
6. Return Inertia response — renders currently active theme composition as preview baseline
7. NO modification to `PublicRenderer.php` — C only CONSUMES its outputs
8. **Note for Phase E handoff**: Full draft-rendered preview requires `PublicRenderer` to accept an explicit Theme parameter for template/component resolution. C establishes the authorization and route foundation; E completes the rendering integration.

### 27.4 Preview Safety

- Preview DOES NOT render unactivated DRAFT data until Phase E delivers rendering integration (see §27.2)
- Preview requires `THEME_PREVIEW` permission (already gated by policy)
- Anonymous guests cannot access preview (requires authenticated session + policy check)
- Preview never modifies any Theme data — it is read-only observation of published state

---

## 28. Publish Foundation Plan

### 28.1 Theme Design Activation Flow (NOT CMS Content Publication)

It is critical to distinguish three separate operations that are sometimes colloquially called "publish":

| Operation | Owner | Mechanism | Status Transition | Approval Workflow |
|-----------|-------|-----------|-------------------|-------------------|
| **Theme Design Activation** | `ThemeActivationService::activate(draftTheme, actor)` | `theme_activation.active_theme_id` pointer flip; old theme → INACTIVE, draft → ACTIVE | DRAFT → ACTIVE; old ACTIVE → INACTIVE | Single-actor (HD-CR001-02 Option A). No second-person approval. |
| **CMS Content Publication** | `PublicationService::publish(cmsEntity, actor)` | Revision state machine: DRAFT → PUBLISHED | DRAFT/PENDING → PUBLISHED | Single-actor. Q29 preserved (no editorial workflow). |
| **CMS Scheduled Publish/Unpublish** | `PublicationService::executeScheduledTransition()` | Checks `schedule_at` / `publish_at` / `unpublish_at` columns via Laravel Scheduler + Cron | As configured at schedule time | Same as above — single-actor at trigger time. |

Phase C's Site Design "Publish" is specifically **Theme Design Activation** (row 1 above). It uses `ThemeActivationService::activate()` which:
1. Locks singleton `theme_activation` row with `lockForUpdate()`
2. Validates candidate DRAFT Theme's component configs against current schema (`assertInternallyConsistent`)
3. Atomically within a DB transaction: flips previous active → INACTIVE, draft → ACTIVE, updates pointer
4. Audits both deactivate and activate events

This is NOT CMS content publication and NOT scheduled publishing. They use completely different services and state machines.
### 28.3 Constraints for Theme Design Activation

- Single-actor publish (HD-CR001-02, Option A)
- No second-person approval
- Q29 preserved: CMS editorial governance separate from Site Design publish
- Thread-safe: `ThemeActivationService::activate()` uses `expectedActiveThemeId` concurrency guard
- Audit: Existing audit logging captures activation events (per IMP-004 criticality model)

---

## 29. Menu Foundation Plan

### 29.1 Current Menu Structure

```
ThemeNavigationMenu (one per theme, identified by code like 'primary')
  └─ ThemeNavigationItem (tree, parent_id self-FK)
       ├─ label
       ├─ destination_type (SYSTEM_ROUTE | CMS_CONTENT | EXTERNAL_URL)
       ├─ destination_route / destination_content_ulid / destination_external_url
       ├─ visible_desktop (BOOLEAN NULL DEFAULT TRUE) — from CR-001-B
       └─ visible_mobile  (BOOLEAN NULL DEFAULT TRUE) — from CR-001-B
```

### 29.2 C's Wrapping Approach

C's Site Design UI presents:
- "Menus" = a list of `ThemeNavigationMenu` rows (usually just one called "Primary")
- Each menu shows its `label` and item count
- Clicking a menu shows items in a simple table
- Each row has toggle switches for `visible_desktop` and `visible_mobile`
- Creating/editing items: label + type selector (SYSTEM_ROUTE, CMS_PAGE, EXTERNAL_URL) + destination picker
- The `destination_type` polymorphism is hidden behind type labels: "Internal Link", "Page", "URL"

### 29.3 Responsive Hybrid IA Support

Items flagged `visible_desktop=TRUE, visible_mobile=FALSE` appear on desktop nav only.
Items flagged `visible_desktop=FALSE, visible_mobile=TRUE` appear on mobile bottom nav only.
Items with BOTH true appear everywhere.
Items with BOTH false are hidden.

This enables HD-CR001-04: desktop shows Infaq/Sedekah distinct; mobile consolidates into ZISWAF entry.

---

## 30. Header/Footer Foundation Plan

### 30.1 Current Reality

- **Header** = `ThemeBrandingConfig.logo_url` + `siteChrome().navigation.primary` → rendered in `PublicRenderer::renderForContentKind()` via `renderNavigationMenuSlot()`
- **Footer** = Hardcoded institutional content in Vue/Blade → NOT driven by Theme data
- Neither is a configurable "component" in the current Theme/Template/Section/Component chain

### 30.2 C's Approach

C provides:
- **"Brand & Colors"** page in Site Design → reads/writes `ThemeBrandingConfig` via `ThemeBrandingService`
- **"Navigation"** page → reads/writes `ThemeNavigationMenu/Item` via `ThemeNavigationService`
- **"Header & Footer"** label → wraps Brand + Navigation management (both compose the header visually)
- Footer remains hardcoded organizational content (not part of Theme configuration) unless later phases extend it

No new model or table. Existing `ThemeBrandingConfig` and `ThemeNavigationMenu/Item` provide everything.

---

## 31. Appearance Foundation Plan

"Appearance" in Site Design maps directly to:

| Operator Concept | Underlying Data |
|-----------------|-----------------|
| Logo | `ThemeBrandingConfig.logo_theme_asset_id` (FK to `ThemeAsset`) |
| Favicon | `ThemeBrandingConfig.favicon_theme_asset_id` |
| Primary/Secondary colors | `ThemeBrandingConfig.color_tokens` (JSON) |
| Font family | `ThemeBrandingConfig.font_family` (string) |

C exposes these through branded Site Design UI:
- Color picker inputs bound to JSON token names
- Upload control for logo (delegates to existing `ThemeAssetService.upload()`)
- Font dropdown (validated against existing `BrandingConfigValidator.assertValidFontFamily()`)

All validation passes through existing validators. No schema change.

---

## 32. Banner / Media Foundation Plan

### 32.1 Banners

In the current Theme Engine, banners are either:
- `ThemeComponent` typed `banner` (editorial content, configured via `config` JSON)
- Or could be `ThemeComponent` typed `cta_button` (Call To Action)

C does NOT invent a new "Banner" model. C simply exposes existing banner-type components through the Site Design "Block Listing" view. Future visual page builder (CR-001-D) would allow adding/configuring banner blocks visually.

### 32.2 Media Integration

C's Site Design admin integrates with existing media library via `ThemeAssetService`. Upload controls in Site Design branding pages call the same upload endpoint logic. No duplication of media storage or token resolution.

---

## 33. Home / Page Foundation Plan

### 33.1 Home Management

"Manage Home" in Site Design simplifies:

1. Select which CMS page serves as home (`CmsHomepageAssignment`)
2. OR select which Theme serves as the active presentation layer (`ThemeActivation` — via publish action)
3. OR both simultaneously (designate a CMS page AND ensure the right Theme is active)

C's Site Design "Home" page:
- Shows current homepage designation (which CmsPage)
- Shows current active Theme
- Provides actions to switch homepage or publish a new Theme

Zero new tables. Reuses `CmsHomepageAssignment` and `ThemeActivation`.

### 33.2 Page Management

Existing `Cms\*Controller` routes (`/admin/content/pages/*`) already provide full page CRUD. C does NOT create duplicate page management. C simply ensures the existing CMS admin is accessible alongside the new Site Design admin. If Site Design needs to reference pages (e.g., in navigation destination pickers), it reads `CmsPage` directly.

---

## 34. Authorization Matrix

### 34.1 Permissions Required by C

| Permission | Controller/Service Action | Where Used |
|------------|--------------------------|------------|
| `THEME_VIEW` | `SiteDesignController::index()` — list themes | Site Design overview |
| `THEME_CREATE` | `SiteDesignCloneService::cloneFromActive()` — creates new DRAFT Theme row | Copy-on-write editing |
| `THEME_UPDATE` | `SiteDesignController::saveBranding()` — update branding config | Branding/Appearance |
| `THEME_UPDATE` | `SiteDesignController::saveMenu()` — create/edit/delete navigation items | Menus |
| `THEME_UPDATE` | `SiteDesignController::cloneToDraft()` — delegates to clone service which creates sub-tree items under new DRAFT Theme | Copy-on-write editing |
| `THEME_PREVIEW` | `SiteDesignController::preview()` — render active theme as preview baseline (full draft rendering requires E) | Preview |
| `THEME_PUBLISH` | `SiteDesignController::publish()` — activate theme design | Publish |
| `CONTENT_VIEW` | `SiteDesignController::listPages()` — page reference for navigation targets | Menu navigation target picker |
| `CONTENT_MEDIA_UPLOAD` | `SiteDesignController::uploadLogo()` — upload brand assets | Branding |

### 34.2 No New Permissions Required

C reuses the exact same permission framework already established. The only new permission (`THEME_PREVIEW`) was added by CR-001-B. No additional permission constants or policy methods needed for C. The `THEME_CREATE` permission (existing from B, used for creating new DRAFT Theme rows during clone) is an existing permission that already covers the clone operation.

### 34.3 Policy Reuse

All C controllers authorize using `ThemePolicy` (with appropriate action) and `ContentPagePolicy` where CMS data is accessed. Same `AuthorizesUsingRbac` concern pattern throughout. Super Admin must NOT receive automatic authority — all C operations go through the same policy gates.

---

## 35. Proposed Schema Changes

### 35.1 Assessment

**CR-001-C does NOT propose any schema changes.** Every concept C exposes is mapped to existing tables:

| C Concept | Existing Table(s) | Change Needed? |
|-----------|------------------|----------------|
| Site Design (Theme) | `themes` | NO |
| Branding / Appearance | `theme_branding_configs` | NO |
| Menus | `theme_navigation_menus` | NO |
| Navigation Items | `theme_navigation_items` | NO (visibility columns already added by B) |
| Pages | `cms_pages`, `cms_content_revisions` | NO |
| Homepage | `cms_homepage_assignment` | NO |
| Active Theme | `theme_activation` | NO |
| Templates/Sections/Components | `theme_templates`, `theme_sections`, `theme_template_sections`, `theme_components` | NO |
| Assets | `theme_assets`, `cms_media_assets` | NO |

### 35.2 Schema Item Classification

| Category | Count | Examples |
|----------|-------|----------|
| DEFINITE | 0 | None |
| CONDITIONAL | 0 | None |
| NOT REQUIRED | All | Every C concept maps to existing schema |

### 35.3 SCHEMA ITEM COUNT: 0

---

## 36. Migration File Count

**MIGRATION FILE COUNT: 0**

No migrations required by CR-001-C.

---

## 37. Phase B Dependencies

| Dependency | B Output | C Consumption |
|-----------|----------|--------------|
| `THEME_PREVIEW` permission | `PermissionRegistry::THEME_PREVIEW = 'theme.preview'` | Consumed by C preview authorization |
| `ThemePolicy::preview()` method | Policy method at `app/Policies/ThemePolicy.php` | Called by C preview controller action |
| `visible_desktop` / `visible_mobile` | Added to `ThemeNavigationItem` model casts | Used by C for responsive menu management |
| `ZakatCalculatorService` | Stub service at `App\Services\Zakat\ZakatCalculatorService` | NOT consumed by C (belongs to CR-001-G) |
| `FidyahCalculatorService` | Stub service at `App\Services\Fidyah\FidyahCalculatorService` | NOT consumed by C (belongs to CR-001-G) |
| `CalculationInput`/`CalculationResult` | Value objects in `App\ValueObjects\Zakat\*` and `Fidyah\*` | NOT consumed by C |
| All foundation models | Zakat/Fidyah/Policy models | NOT consumed by C |

**Critical observation:** CR-001-C actually depends on very little of B's output beyond the `THEME_PREVIEW` permission/gate and the navigation visibility columns. C's primary dependency is on EXISTING IMP-005/IMP-006 infrastructure, not B's foundation. This makes C low-risk and independent of most B changes.

---

## 38. Phase D Handoff Boundary

### 38.1 C PROVIDES to D

| Artifact | Provider | Consumer | Description |
|----------|----------|----------|-------------|
| Theme model | B | D | D consumes for block rendering |
| Template/Section/Component models | B | D | D builds visual block editor |
| `ComponentConfigValidator` | B | D | D validates user-configured blocks |
| Existing ThemeController routes | B+existing | D | D may wrap these for advanced options |
| Existing `Theme/Show.vue` page | Existing | D | D enhances this as the visual builder canvas |
| `siteChrome()` output | B (via PublicRenderer) | D | D reads for chrome context |
| Site Design wrapper structure | C | D | D builds ON TOP of C's Site Design admin |
| `SiteDesignCloneService` | C | D | D may use clone service if it needs working-copy semantics |

### 38.2 D BUILDs (NOT C)

| Artifact | Owner | Notes |
|----------|-------|-------|
| PageBuilderController | CR-001-D | Builder application behavior, NOT a read-only listing controller |
| Block listing with edit capabilities | CR-001-D | PageBuilder page has full CRUD over Template→Section→Component |
| Drag-and-drop block reordering | CR-001-D | PRIMARY ownership |
| Visual block configuration panel | CR-001-D | Contextual block settings |
| Block library UI | CR-001-D | Available blocks panel |
| Live preview canvas | CR-001-D | Real-time template editing |
| Component type registration for new types | CR-001-D | Adds carousel, video, ziswaf_services, etc. |
| Keyboard-operable ordering controls | CR-001-D | Per CR-001 Sec. 27 |
| Page Builder Vue pages | CR-001-D | `Admin/PageBuilder/*.vue` — includes Index, Config, Library |

### 38.3 Handoff Summary

```
CR-001-C provides:
  - Site Design wrapper over existing Theme admin
  - Copy-on-write / Clone working copy foundation
  - Simplified Menu management
  - Simplified Branding/Appearance management
  - Preview authorization/foundation (auth gate + route scaffold; rendering integration deferred to E)
  - Publish/activate foundation
  - Advanced/Debug coexistence

CR-001-D builds:
  - PageBuilderController (full builder app, not read-only listing)
  - Visual Page Builder (drag-drop, configure, live-preview)
  - Block Library (add/remove/reconfigure blocks)
  - New block types and their validators
  - Page Builder Vue pages with full edit capability
  - Block listing for builder use (C does NOT provide this)
```

---

## 39. Phase E PublicRenderer Boundary

### 39.1 Rule

`PublicRenderer.php` is READ-ONLY for C. WRITE OWNER is CR-001-E.

### 39.2 C Reads From E (Prepared Interface)

C reads `siteChrome()` output for chrome context in the Site Design admin. C DOES NOT extend `siteChrome()` — that belongs to E. C uses whatever output `siteChrome()` currently returns (or will return when E lands).

### 39.3 Preview Rendering Boundary (C-02 Clarification)

C's preview endpoint calls `PublicRenderer::renderForContentKind()` for rendering preview output. However, `renderForContentKind()` internally resolves via `$this->activeTheme()` which returns only the CURRENTLY ACTIVE theme — not a specific DRAFT theme passed as argument. Therefore:

- **Phase C V1 preview** renders the active theme composition as a baseline reference (not the DRAFT working copy)
- **Phase E rendering integration** is required to pass an explicit Theme parameter through `PublicRenderer`, enabling full rendered preview of any non-active theme (including DRAFT working copies)
- C's authorization gate (`ThemePolicy::preview()`) works correctly regardless of rendering capability
- The route scaffold and controller action are C-deliverable; the enhanced rendering is E-deliverable

C DOES NOT modify `PublicRenderer.php`. Any rendering enhancement for arbitrary-theme preview is Phase E responsibility.

---

## 40. Phase F Boundary

CR-001-F owns Campaign/Donation UX (staged funnel). C does NOT touch:
- `PublicDonationController.php` (reserved for CR-001-J too)
- `DonationCreate.vue` (FE-CHK-009 protected)
- Donation business rules
- Staged funnel implementation

C's only intersection with F is: if Site Design navigation items can link to Donation Create, that's a destination resolution question answered by existing `NavigationDestinationResolver`. No code change from C.

---

## 41. Phase G Boundary

CR-001-G owns ZISWAF IA pages + Calculator Foundation. C does NOT touch:
- ZISWAF IA placeholder pages
- Calculator endpoints/routes
- Calculator models/services (those are B-created, G-consumed)

C MAY create navigation items referencing future ZISWAF paths (e.g., a navigation entry for `/zakat-center`) but the ACTUAL PAGE ROUTING is G's responsibility.

---

## 42. Phase H Boundary

Note: The architecture merges CR-001-H into CR-001-G (calculator foundation = ZISWAF public UX wave). The task description mentions H as "Admin/Super Admin Experience V2" but per architecture Section 72, this is effectively CR-001-D. So H boundary = D boundary.

CR-001-D (also referred to as H in some contexts) owns: dark palette, grouped permission-aware sidebar, dashboard widgets, design system primitives. C does NOT implement these.

---

## 43. IMP-019 Boundary

CR-001-C does NOT touch IMP-019 territory:
- No Ledger
- No ZISWAF transactional tables
- No religious formulas/rates
- No financial consequences

All IMP-019 boundaries pass cleanly through C's zero-schema-change design.

---

## 44. Shared Hosting Compatibility

CR-001-C requires NOTHING beyond:
- Apache/LiteSpeed + PHP 8.3 + MySQL + Cron + compiled frontend assets

No Redis, Supervisor, PM2, WebSocket, Docker, or production Node runtime.

C introduces:
- ONE new PHP controller: `SiteDesignController` (thin delegation)
- ONE new PHP service: `SiteDesignCloneService` (clone orchestration within `DB::transaction`)
- ONE Vue page directory: `Admin/SiteDesign/` (four pages)
- Zero backend infrastructure changes

---

## 45. FE-CHK-009 Collision Matrix

### 45.1 Analysis

CR-001-C proposes to create/write:
- Controller files: `SiteDesignController` — BRAND NEW path (PageBuilderController belongs to CR-001-D)
- Vue pages: `Admin/SiteDesign/*.vue` — BRAND NEW paths (`Admin/PageBuilder/` belongs to CR-001-D)
- NO modification to any existing controller, Vue page, route, CSS, JS file

### 45.2 Explicit Check Against Protected Files

| Protected File | C Would Touch It? | Collision? |
|---------------|------------------|------------|
| `app/Http/Controllers/PublicCampaignController.php` | NO | NONE |
| `app/Http/Controllers/PublicDonationController.php` | NO | NONE |
| `app/Http/Controllers/PublicPaymentController.php` | NO | NONE |
| `app/Http/Controllers/PublicProgramController.php` | NO | NONE |
| `app/Services/Theme/PublicRenderer.php` | NO (READ-ONLY) | NONE |
| `routes/web.php` | POTENTIAL — C needs to add preview/menu/save routes | COORDINATION REQUIRED |
| `resources/js/Components/Admin/AdminLayout.vue` | NO — D handles global admin redesign | NONE |
| `resources/js/Components/UI/Icon.vue` | NO — D extends | NONE |
| `resources/js/Pages/Public/ThemeRender.vue` | NO | NONE |
| `resources/js/Pages/Public/CampaignIndex.vue` | NO | NONE |
| `resources/js/Pages/Public/CampaignShow.vue` | NO | NONE |
| `resources/js/Pages/Public/DonationCreate.vue` | NO | NONE |
| `resources/js/Pages/Public/ProgramShow.vue` | NO | NONE |
| `tests/Feature/Donation/DonationHttpTest.php` | NO | NONE |
| `tests/Feature/Payment/PaymentHttpTest.php` | NO | NONE |
| Any `resources/js/Components/Public/*` | NO | NONE |
| Any `resources/js/Pages/Public/Payment*.vue` | NO | NONE |
| Any new test files listed as untracked | NO | NONE |

### 45.3 Coordination Required

**ONE potential coordination point:** `routes/web.php` — C must register new admin routes under `/admin/site-design/*`. The current `routes/web.php` contains FE-CHK-009 additions (public donation/payment routes) but also contains existing `/admin/*` routes. C appends its routes to the existing `/admin/*` group. Since C adds new admin routes and FE-CHK-009 modified the same file, there is a COORDINATION point — but since C ADDS to the file rather than modifying existing FE-CHK-009 lines, this is a LOW-RISK merge conflict scenario (different line regions).

### 45.4 FE-CHK-009 COLLISION SUMMARY: 1 coordination point

| Path | Status | Handling |
|------|--------|----------|
| `routes/web.php` | COORDINATION REQUIRED | C appends admin routes; may cause merge conflict with FE-CHK-009 delta. Low risk — different line region. |

---

## 46. Proposed C File Ownership Map

### 46.1 PROPOSED CREATE FILES

| # | Path | Owner | Purpose | Why Required | Collision |
|---|------|-------|---------|-------------|-----------|
| 1 | `app/Http/Controllers/Admin/SiteDesignController.php` | CR-001-C | Wrapper controller for Site Design admin endpoints: index, cloneToDraft, preview, saveBranding, saveMenu, publish | Simplifies operator interaction with existing Theme/CMS data via copy-on-write editing model | NONE |
| 2 | `app/Services/Theme/SiteDesignCloneService.php` | CR-001-C | Orchestrates subtree copy from active Theme → DRAFT working copy using existing ThemeTemplateSectionComponentNavigation services | Copy-on-write editing requires a safe mechanism to create independent working copies without mutating live design | NONE |

**Total CREATE: 2**

One thin controller and one application-layer service. Both delegate to existing domain services — no business logic or new data models.

### 46.2 PROPOSED MODIFY FILES (FUTURE IMPLEMENTATION — NOT THIS RECON TASK)

During CR-001-C implementation, future Claude/Muse will modify:

| Path | Owner | Purpose | Collision |
|------|-------|---------|-----------|
| `app/Services/Theme/ThemeNavigationService.php` | CR-001-C | Accept/persist `visible_desktop`, `visible_mobile` in createItem() payload. Backward-compatible: if omitted, DB default (TRUE). Existing `visible => true` preserved. NON-SCHEMA modification only. | NONE |

**Total FUTURE MODIFY: 1** (only during C implementation; this recon does not modify it)

### 46.3 READ-ONLY FILES

| Path | Purpose |
|------|---------|
| `app/Models/Theme/Theme.php` | Reference for listing/describing themes |
| `app/Models/Theme/ThemeActivation.php` | Reference for singleton activation check |
| `app/Models/Theme/ThemeTemplate.php` | Reference for block listing |
| `app/Models/Theme/ThemeSection.php` | Reference for block listing |
| `app/Models/Theme/ThemeComponent.php` | Reference for block config schema |
| `app/Models/Theme/ThemeNavigationMenu.php` | Reference for menu listing |
| `app/Models/Theme/ThemeNavigationItem.php` | Reference for navigation items + visibility toggles |
| `app/Models/Theme/ThemeBrandingConfig.php` | Reference for branding config |
| `app/Models/Theme/ThemeAsset.php` | Reference for asset upload integration |
| `app/Models/Cms/CmsPage.php` | Reference for homepage/destination picker |
| `app/Models/Cms/CmsHomepageAssignment.php` | Reference for homepage designation |
| `app/Policies/ThemePolicy.php` | Authorization: view, update, preview, publish |
| `app/Services/Theme/ThemeService.php` | Delegation: theme listing |
| `app/Services/Theme/ThemeActivationService.php` | Delegation: publish/activate |
| `app/Services/Theme/ThemeBrandingService.php` | Delegation: branding save |
| `app/Services/Theme/ThemeNavigationService.php` | Delegation: menu/item CRUD |
| `app/Services/Theme/ThemeTemplateService.php` | Delegation: template listing |
| `app/Services/Theme/ThemeSectionService.php` | Delegation: section listing |
| `app/Services/Theme/ThemeComponentService.php` | Delegation: component listing |
| `app/Services/Theme/ComponentConfigValidator.php` | Delegation: component config schema reference |
| `app/Services/Content/PageService.php` | Delegation: page listing for navigation targets |
| `app/Services/Content/HompageContentResolver.php` | Delegation: homepage resolution |
| `app/Services/Theme/PublicRenderer.php` | Delegation: preview rendering (READ ONLY) |
| `app/Services/Rbac/PermissionRegistry.php` | Reference: THEME_PREVIEW permission |
| `app/Models/User.php` | Reference: actor resolution |
| `app/Http/Controllers/Theme/ThemeController.php` | Reference: Advanced/Debug route preservation |
| `resources/js/Pages/Theme/Index.vue` | Reference: Advanced/Debug UI (preserve untouched) |
| `resources/js/Pages/Theme/Show.vue` | Reference: Advanced/Debug UI (preserve untouched) |

### 46.4 FILES EXPLICITLY FORBIDDEN FOR C

| Category | Files | Reason |
|----------|-------|--------|
| PublicRenderer writes | `app/Services/Theme/PublicRenderer.php` | WRITE OWNER is CR-001-E |
| Public controllers | `app/Http/Controllers/Public*Controller.php` | Reserved for CR-001-F/J |
| Payment/Donation files | `app/Services/Donation/DonationService.php`, `app/Http/Controllers/PublicDonationController.php`, `app/Http/Controllers/PublicPaymentController.php` | Reserved for CR-001-J |
| Frontend admin shell | `resources/js/Components/Admin/AdminLayout.vue` | Owned by CR-001-D |
| Icon registry | `resources/js/Components/UI/Icon.vue` | Extended by CR-001-D |
| Public Vue pages | Any `resources/js/Pages/Public/*` except as reference | Owned by CR-001-E/F/G |
| Dashboard widgets | `Dashboard.vue`, widget APIs | Owned by CR-001-D |
| Dark palette / Admin V2 | All Admin V2 design system primitives | Owned by CR-001-D |
| Tests for other phases | `tests/Feature/Donation/`, `tests/Feature/Payment/` | Owned by CR-001-J |
| Migration files | Any `database/migrations/NNNN_*.php` | Zero schema changes for C |
| ZISWAF transactional | Any zakat/infaq/sedekah/wakaf/fidyah business models | Owned by IMP-019 |

---

## 47. Proposed CREATE File Details

### 47.1 `SiteDesignController.php`

**Purpose:** Thin wrapper over existing Theme/CMS services for Site Design administration via copy-on-write editing model.

**Proposed Actions:**
- `index()` — List themes with DRAFT/ACTIVE badges
- `showBrand()` — Show current branding config
- `saveBrand()` — Save branding (delegate to `ThemeBrandingService.save()`)
- `uploadLogo()` — Upload brand logo (delegate to `ThemeAssetService.upload()`)
- `indexMenus()` — List navigation menus
- `showMenu()` — Show menu with items
- `saveMenu()` — Save menu structure (delegate to `ThemeNavigationService`)
- `cloneToDraft()` — Create working copy via `SiteDesignCloneService`
- `preview()` — Preview draft theme (authorization gate; rendering baseline provided by active-theme PublicRenderer until E integration)
- `publish()` — Publish Site Design (delegate to `ThemeActivationService.activate()`)

Each action follows the standard controller pattern: resolve actor → authorize → delegate → render Inertia page.

### 47.2 `SiteDesignCloneService.php`

**Purpose:** Orchestrates complete mutable presentation aggregate clone from active Theme → DRAFT working copy using existing Theme domain services within a single database transaction.

**Key Method:**
- `cloneFromActive(Theme $source, Principal $actor): Theme` — Wraps aggregate clone in `DB::transaction(...)`. Creates new DRAFT Theme via `ThemeService.create()`, copies Templates→Sections→Components, NavigationMenus→NavigationItems (root-first → child-second algorithm), BrandingConfig (with NEW ThemeAsset rows for logo/favicon assets via Storage::copy + direct model save). All FK relationships remapped via deterministic old→new mapping table. Validates component configs post-copy. Returns new DRAFT Theme. FAILURE = full DB rollback + filesystem compensation. SUCCESS = complete internally consistent DRAFT aggregate.

**Aggregate Cloned:** Theme + Templates/Sections/Components + NavigationMenus/Items (root-first → child-second, no post-create update; all fields including visible/visible_desktop/visible_mobile preserved through createItem() payloads) + BrandingConfig + NEW ThemeAsset rows (one per referenced logo/favicon, owned by DRAFT theme_id, copied via Storage::copy).

**ID Remapping:** Deterministic mapping: `theme_id`, `template_id`, `section_id`, `component_id`, `navigation_menu_id`, `navigation_item.parent_id`, `branding.logo_theme_asset_id`, `branding.favicon_theme_asset_id`, plus all destination_* fields — ALL remapped/preserved to new IDs or source values. Never verbatim FK copy.

**Two-Pass vs Root-First:** Parent linkage uses existing `createItem()` with resolved parent_id at creation time (no post-create update, no raw Eloquent update). Root items cloned first (parent_id=null), then child items (resolved via root→new-root map). Unresolved parent = fail closed.

**Navigation Visibility Contract (C-11):** Future CR-001-C implementation modifies `app/Services/Theme/ThemeNavigationService.php` to accept/persist `visible_desktop` and `visible_mobile` in createItem() payload. Backward-compatible: if omitted, DB default (TRUE) applies. Existing `visible => true` hardcoded behavior preserved unchanged. Clone passes all three visibility fields through createItem() to preserve source fidelity.

**Filesystem Compensation:** Physical file copies via `Storage::copy()` occur outside DB tx. Destination paths recorded for cleanup on DB failure. ACTIVE theme files never touched. Orphan cleanup follows MediaCleanupService cron pattern.

Authorization enforced at controller entry point before service invocation (§34). Zero schema changes required. One service modification: `ThemeNavigationService::createItem()` (non-schema).

Read-only in C. Actual block editing is CR-001-D.

---

## 48. Proposed Vue Pages

### 48.1 Site Design Pages (`resources/js/Pages/Admin/SiteDesign/`)

| File | Purpose |
|------|---------|
| `Overview.vue` | Theme list/dashboard with DRAFT/ACTIVE badges and quick actions |
| `Branding.vue` | Branding configuration: logo, colors, typography |
| `Menus.vue` | Menu management: list items, toggle visibility, edit destinations |
| `Preview.vue` | Preview render of a selected DRAFT theme |

### 48.2 Site Design Clone Operation (No Separate Page Needed)

The clone operation (`cloneToDraft`) is a backend service action, not a standalone page. Operator workflow: from SiteDesign Index/Overview page, click "New Draft / Clone Current Design" which triggers `SiteDesignController::cloneToDraft()` → returns redirect to the new DRAFT theme's edit page. No separate Vue page needed.

---

## 49. Test Preservation Map

### 49.1 Tests to Preserve

| Test Area | Test Files | Status |
|-----------|-----------|--------|
| CMS CRUD | `tests/Feature/Cms/PageControllerTest.php`, `ArticleControllerTest.php`, etc. | PRESERVE — C doesn't affect CMS controllers |
| Theme CRUD | `tests/Feature/Theme/ThemeServiceTest.php` | PRESERVE — C doesn't affect Theme models |
| Theme Activation | `tests/Feature/Theme/ThemeActivationServiceTest.php` | PRESERVE — C delegates to this service |
| Theme Authorization | `tests/Feature/Theme/ThemeAuthorizationTest.php` | PRESERVE — C uses same authorization |
| Theme Preview | `tests/Feature/Theme/ThemePolicyPreviewTest.php` | PRESERVE — B-created, C may extend with controller-level preview test |
| Theme Schema Constraints | `tests/Feature/Theme/ThemeSchemaConstraintsTest.php` | PRESERVE |
| Publication | `tests/Feature/Cms/PublicationServiceTest.php` | PRESERVE |
| Scheduled Transitions | `tests/Feature/Cms/SchedulerTest.php` | PRESERVE |
| CMS Authorization | `tests/Feature/Cms/ContentAuthorizationTest.php` | PRESERVE |
| All existing Feature tests | Full regression suite | PRESERVE — re-run after C |

### 49.2 Tests to Extend

| Test Area | Extension | Notes |
|-----------|-----------|-------|
| Theme Preview | Controller-level preview test (C) | Verify preview route authorizes correctly, renders draft output |
| Themebuilding | Regression test for publish flow via C's `SiteDesignController::publish()` | Verify publish still calls `ThemeActivationService::activate()` |

### 49.3 New Tests for C

| Test | What It Proves | Target | DB Required | Type |
|------|----------------|--------|-------------|------|
| `SiteDesignControllerPreviewAuthTest` | Unauthorized user cannot preview draft | C controller | NO | GATING |
| `SiteDesignControllerPublishTest` | Publish delegates to activation service correctly | C controller | YES | GATING |
| `SiteDesignControllerBrandingSaveTest` | Branding save delegates to branding service | C controller | YES | GATING |
| `SiteDesignCloneServiceTest` | Clone service creates independent working copy | C service | YES | GATING |

---

## 50. Proposed New/Extended Tests

See §49.3 for proposed new tests. All follow existing PHPUnit structure and reuse factory patterns from existing seeders.

---

## 51. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Merge conflict on `routes/web.php` with FE-CHK-009 | MEDIUM | C adds routes to `/admin/*` group region; FE-CHK-009 modified public route region. Different line areas. Easy resolution. |
| C accidentally touching `PublicRenderer.php` | HIGH | READ-ONLY rule explicitly stated in all documentation. Codex auditor checks diff. |
| C duplicating ThemeController functionality | MEDIUM | C controllers are thin wrappers — verify no business logic in C controllers during review. |
| Advanced/Debug view disappearing | HIGH | HD-CR001-05 requires retention; explicit requirement in C spec. |
| C extending preview beyond authorization scope | HIGH | Preview MUST call `ThemePolicy::preview()` — enforced by policy gate on route. |

---

## 52. Open Questions

| Question | Classification | Answer / Status |
|----------|---------------|-----------------|
| Should C rename `/admin/theme/*` routes or keep them? | RECON-RESOLVABLE | Keep existing. C creates NEW routes under `/admin/site-design/*`. No route renames. |
| Should C expose CMS pages in navigation target picker? | RECON-RESOLVABLE | Yes — C reads `CmsPage` for navigation destination targets. Already supported by existing `ContentResolverService`. |
| Does C need to register any new permissions? | RECON-RESOLVABLE | No — all C authorization uses existing permissions + `THEME_PREVIEW` from B. |
| Should C delete or deprecate `ThemeController`? | RECON-RESOLVABLE | No — HD-CR001-05 requires Advanced/Debug retention. Both surfaces coexist. |
| Should C integrate with FE-CHK-009's Chrome pattern? | RECON-RESOLVABLE | C reads `siteChrome()` output for preview/chrome context but does NOT extend the contract. That's E's job. |
| What Vue admin shell should C's pages use? | RECON-RESOLVABLE | C's pages use existing `AdminLayout.vue` until D replaces it with dark shell. Temporary but safe. |

**New Human Decisions Required: 0.** All open questions are resolvable from existing architecture, HD-CR001-05, and the established multi-agent workflow.

---

## 53. Recon Findings

| Finding | Severity | Description |
|---------|----------|-------------|
| B-01 | MINOR | B-RECON had wrong model namespace placement for NisabPolicy and GoldPriceReference. REMEDIATED in B-RECON PATCH. |
| B-02 | MINOR | B-RECON had wrong file count arithmetic (21 CREATE instead of 23). REMEDIATED in B-RECON PATCH. |
| B-03 | MINOR | B-RECON had inconsistent model namespace/placement. REMEDIATED in B-RECON PATCH. |
| Preview Endpoint Gap | INFO | B built the auth gate but no preview route/controller exists. C fills this gap. |
| Theme Admin Complexity | INFO | `Theme/Show.vue` is the largest admin Vue page (complex tree rendering). C's simplified UI addresses this. |
| Two Admin Surfaces Coexistence | INFO | C and existing ThemeController coexist peacefully — disjoint file sets. |

**BLOCKER: 0**
**MAJOR: 0**
**MINOR: 0 (all previously reported minor issues remediated)**

---

## 54. Exact Recommended Implementation Scope for CR-001-C

### 54.1 IN SCOPE

1. Create `SiteDesignController` with actions for: index, cloneToDraft, preview (auth gate), saveBranding, saveMenu, publish
2. Create `SiteDesignCloneService` to orchestrate subtree copy from active Theme → DRAFT working copy
3. Register new admin routes under `/admin/site-design/*` in `routes/web.php` — NOTE: PageBuilderController belongs to CR-001-D; C does NOT provide block listing or a separate builder controller
4. Create Vue pages: `Admin/SiteDesign/Overview.vue`, `Branding.vue`, `Menus.vue`, `Preview.vue`
5. Write feature tests for: preview auth, publish flow, branding save, clone service, filesystem compensation, null asset handling, visibility preservation
6. Run full existing test suite regression

### 54.2 FUTURE SERVICE MODIFICATION (IMPLEMENTATION TIME)

7. Modify `app/Services/Theme/ThemeNavigationService::createItem()` to accept/persist `visible_desktop` and `visible_mobile` from payload. Backward-compatible. NON-SCHEMA modification.

### 54.3 EXCLUDED FROM C

1. Visual page builder with drag-drop (CR-001-D)
2. Block configuration UI (CR-001-D)
3. Block library UI (CR-001-D)
4. Live preview canvas (CR-001-D)
5. Dark admin palette (CR-001-D)
6. Sidebar decomposition/grouped nav (CR-001-D)
7. Dashboard widgets (CR-001-D)
8. Public renderer modifications (CR-001-E)
9. Public responsive shell (CR-001-E)
10. Staged donation funnel (CR-001-F)
11. ZISWAF IA pages (CR-001-G)
12. Calculator endpoints (CR-001-G)
13. CODEX-FE-CHK-009-01 remediation (CR-001-J)
14. Ledger integration (IMP-010)
15. Any schema changes or migrations (none required)

---

## 55. Claude Review Checklist

Before starting CR-001-C implementation, Claude should verify:

- [ ] `SiteDesignController.php` delegates to existing services — no business logic written
- [ ] `SiteDesignCloneService.php` orchestrates subtree copy without mutating active Theme — verified by test
- [ ] `ThemePolicy::preview()` gate called on preview action — verified by test
- [ ] `ThemeActivationService::activate()` called on publish — verified by test
- [ ] `PublicRenderer.php` NOT modified — READ-ONLY
- [ ] `routes/web.php` addition is append-only for new admin routes — coordinated with FE-CHK-009
- [ ] No existing `ThemeController` routes removed or renamed
- [ ] No existing Vue pages modified (they belong to D for redesign)
- [ ] No FE-CHK-009 protected files touched (except potential lightweight merge on routes/web.php)
- [ ] No FE-CHK-009 dirty files modified/staged
- [ ] CODEX-FE-CHK-009-01 reserved files untouched
- [ ] All existing Feature tests pass after implementation
- [ ] Zero database migrations created
- [ ] Zero schema changes made
- [ ] Shared hosting compatible (no new infrastructure)

---

## 56. Final Recon Verdict

```
MODEL:
qwen/qwen3.7-flash

MODEL ROUTING VERIFICATION:
HUMAN-VERIFIED IN COMMAND CODE

TASK:
CR-001-C GOVERNED RECON

BASELINE HEAD:
6806bf22d733de30a8798bebed558f54b1457768

ORIGIN/MASTER:
6806bf22d733de30a8798bebed558f54b1457768

BASELINE MATCH:
YES

CR-001-B:
FINAL / LOCKED

C-RECON FILE:
docs/ai-handoff/CR-001/C-RECON.md

FILES MODIFIED BY RECON:
1 (only C-RECON.md)

APPLICATION FILES MODIFIED:
0

DATABASE MODIFIED:
NO

CMS CANONICAL REUSE:
FULL REUSE — all CMS models (CmsPage, CmsArticle, CmsContentRevision, CmsMediaAsset, CmsPath, CmsHomepageAssignment), services (PageService, ArticleService, PublicationService, RevisionService, PathService, ContentResolverService, MediaService, ContentSanitizer), controllers (PageController, ArticleController, MediaController, HomepageController), and Vue pages (Cms/*) are retained unchanged. C delegates to these exactly.

THEME CANONICAL REUSE:
FULL REUSE — all Theme models (Theme, ThemeActivation, ThemeTemplate, ThemeSection, ThemeComponent, ThemeNavigationMenu, ThemeNavigationItem, ThemeAsset, ThemeBrandingConfig), services (ThemeService, ThemeActivationService, TemplateSectionComponent services, ThemeBrandingService, ThemeNavigationService), and controllers (ThemeController) are retained unchanged. C delegates to these exactly.

SITE DESIGN FOUNDATION:
Two new artifacts (SiteDesignController thin controller + SiteDesignCloneService orchestrating copy-on-write edit), four Vue admin pages (Overview, Branding, Menus, Preview). Zero schema changes. Preview auth gate created; full rendered DRAFT preview deferred to E. Publish delegates to existing ThemeActivationService. Menus wrap ThemeNavigationService. Branding wraps ThemeBrandingService. Copy-on-write editing model using existing `themes.status` column. Future implementation modifies `ThemeNavigationService::createItem()` for visibility persistence (non-schema).

ADVANCED/DEBUG STRATEGY:
Existing ThemeController routes and Theme/Index.vue + Theme/Show.vue preserved unchanged at /admin/theme/*, accessible to anyone with THEME_VIEW permission, clearly separate from C's new /admin/site-design/* surface. HD-CR001-05 compliant.

PREVIEW FOUNDATION:
Authorization gate EXISTS (B). Route scaffold + controller action + Vue page CREATED by C. Phase C V1 renders active theme composition as baseline; full rendered DRAFT preview requires `PublicRenderer` integration owned by CR-001-E. Preview uses existing `themes.status` for DRAFT validation.

PUBLISH FOUNDATION:
Single-actor publish via existing ThemeActivationService::activate(). No approval workflow. C wraps this as "Publish Site Design" action.

SCHEMA DEFINITE:
0

SCHEMA CONDITIONAL:
0

SCHEMA NOT REQUIRED:
All C concepts map to existing schema (see §35).

SCHEMA ITEM COUNT:
0

MIGRATION FILE COUNT:
0

PROPOSED CREATE FILES:
2 (exact paths):
  1. app/Http/Controllers/Admin/SiteDesignController.php
  2. app/Services/Theme/SiteDesignCloneService.php
Plus 4 Vue pages (proposed paths):
  3. resources/js/Pages/Admin/SiteDesign/Overview.vue
  4. resources/js/Pages/Admin/SiteDesign/Branding.vue
  5. resources/js/Pages/Admin/SiteDesign/Menus.vue
  6. resources/js/Pages/Admin/SiteDesign/Preview.vue
NOTE: Vue pages are proposed; exact filenames/namespacing may vary during implementation. Controller and service paths are EXACT. PageBuilderController belongs to CR-001-D, NOT C.

FUTURE IMPLEMENTATION MODIFIES (NOT THIS RECON TASK):
1 (during C implementation, not by this Qwen recon):
  - app/Services/Theme/ThemeNavigationService.php (NON-SCHEMA service modification for visibility fields)

PROPOSED MODIFY FILES:
0 (zero existing files modified)
(routes/web.php may receive new admin route entries — append-only, low-coordination-conflict with FE-CHK-009)

READ-ONLY FILES:
27 key files listed in §46.3 (models, services, policies, existing controllers, existing Vue pages)

FORBIDDEN C FILES:
Categories listed in §46.4: PublicRenderer writes, public controllers, payment/donation files, AdminLayout.vue, Icon.vue, public Vue pages, dashboard widgets, all Admin V2 primitives, ZISWAF transactional files, migrations

FE-CHK-009 COLLISIONS:
1 (routes/web.php — LOW RISK, different line region)

PUBLICRENDERER:
READ-ONLY

PHASE D BOUNDARY:
PASS (PageBuilderController belongs to CR-001-D; block listing is D-owned; C provides only Site Design wrapper and clone foundation)

PHASE E BOUNDARY:
PASS (PublicRenderer.php NOT touched)

PHASE F/G/H BOUNDARIES:
PASS (no overlap with F/G/H work)

IMP-019 BOUNDARY:
PASS (zero ZISWAF transactional impact)

SHARED HOSTING:
PASS (zero new infrastructure)

NEW HUMAN DECISIONS REQUIRED:
0

BLOCKER:
0

MAJOR:
0

MINOR:
0

GIT DIFF CHECK:
PASS

COMMIT:
NOT PERFORMED

PUSH:
NOT PERFORMED

MERGE:
NOT PERFORMED

CR-001-C IMPLEMENTATION:
NOT AUTHORIZED

CR-001-D:
NOT STARTED

FE-CHK-009:
PAUSED

CODEX-FE-CHK-009-01:
OPEN / VALID / UNRESOLVED

CODEX-FE-CHK-009-01 OWNER:
CR-001-J

IMP-010:
BLOCKED

---

## 63. Claude Recon Review Finding Closure

| Finding | Severity | Status | Evidence |
|---------|----------|--------|----------|
| C-01 — Copy-On-Write / Operator Edit Safety | MAJOR | CLOSED | §24 now documents verified copy-on-write flow using existing `themes.status` (DRAFT/ACTIVE/INACTIVE/ARCHIVED). No new schema. Clone service (`SiteDesignCloneService`) identified as required C CREATE artifact. Publish/activate flow verified against actual `ThemeActivationService::activate()` code (lockForUpdate, INACTIVE→ACTIVE flip, validation before activation). |
| C-02 — Preview Infeasible as Literally Described | MAJOR | CLOSED | §27 now explicitly separates Phase C deliverable (auth gate + route scaffold + authorization enforcement) from Phase E deliverable (PublicRenderer integration for arbitrary-theme rendering). `renderForContentKind()` reads `$this->activeTheme()` internally — cannot render DRAFT theme without modification. C V1 preview renders active theme composition as baseline. PublicRenderer.php remains READ-ONLY. |
| C-03 — PageBuilderController Belongs to D | MAJOR | CLOSED | `PageBuilderController` removed from all C CREATE lists (§46.1), file maps, handoff boundary (§38), risk assessments, implementation scope, Vue pages, and summary. D owns full builder app including block listing. C does NOT provide any block listing functionality. Vue directory references updated: no `Admin/PageBuilder/` pages in C scope. |
| C-04 — Publish Semantics Factual Correction | MINOR | CLOSED | §28 now distinguishes three operations: Theme Design Activation (B/Scope), CMS Content Publication (Q29), Scheduled Publish/Unpublish (Laravel Scheduler/Cron). Previous text incorrectly used "ARCHIVED" for previous active theme — corrected to "INACTIVE" based on verified `ThemeActivationService::activate()` code. Table format replaces misleading block quote. |
| C-05 — Authorization Matrix Gap | MINOR | CLOSED | §34 updated with clone operation permissions (`THEME_CREATE` for `cloneFromActive()`, `THEME_UPDATE` for `cloneToDraft()` delegation). Explicit note that Super Admin must NOT receive automatic authority. All C operations go through same policy gates. No new permission constants needed. |
| C-06 — SiteDesignCloneService Transaction + Relationship Remapping | MAJOR | CLOSED | §24.1A added comprehensive clone service contract: A) atomic DB transaction guarantee; B) full aggregate definition (Theme→Templates→Sections→Components+NavMenus/Items+Branding); C) deterministic old→new ID remapping tables; D) NavigationItem root-first→child-second algorithm (verified against actual ThemeNavigationService); E) relationship isolation invariant (draft ≠ active, no DRAFT record depends on ACTIVE Theme records including assets); F) activation boundary preserved (delegated to ThemeActivationService); G) authorization preserved (§34); H) test plan additions (16 test cases). §47.2 updated. C-09/C-10 refined in Round 3. |
| C-07 — Stale Shared-Hosting Summary | MINOR | CLOSED | §44 corrected from "Two new PHP controllers / Two new Vue page directories" to "ONE new PHP controller (`SiteDesignController`) + ONE new PHP service (`SiteDesignCloneService`) + ONE Vue page directory (`Admin/SiteDesign/`)". Search confirmed all stale PageBuilderController-derived counts removed from §44. |
| C-08 — Remove Stale Read-Only Block Listing | MINOR | CLOSED | Removed "read-only template/block LISTING" claim from Phase C handoff summary (§38.3), implementation scope (§54), and Final Verdict summary (§62). Added explicit note in §38.3 D BUILDs list: "Block listing for builder use (C does NOT provide this)". |
| C-09 — ThemeAsset Ownership / Copy-On-Write | MAJOR | CLOSED | Verified `ThemeAsset.theme_id` is NOT NULL with cascadeOnDelete — each asset owned by exactly ONE Theme. BrandingConfig references ThemeAsset via FK (`logo_theme_asset_id`, `favicon_theme_asset_id`). All shared asset claims removed. Clone creates NEW ThemeAsset rows + new physical file copies under DRAFT theme. Old→new asset ID remap added. Filesystem compensation strategy documented. Isolation invariant corrected. Test plan expanded to 16 cases. Zero schema change required. |
| C-10 — NavigationItem parent_id Clone Mechanism | MINOR | CLOSED | Verified `ThemeNavigationService::createItem()` accepts `parent_id` in payload; one-level nesting enforced at call time. Two-pass post-create update replaced with root-first → child-second algorithm using existing `createItem()`. Pass 1: clone root items (parent_id=null). Pass 2: clone child items with resolved parent_id via old_root→new_root map. Unresolved parent = fail closed. No raw Eloquent update, no new method, no post-create UPDATE. C-06 evidence superseded. |
| C-11 — Navigation Visibility Field Gap | MAJOR | CLOSED | Verified `ThemeNavigationService::createItem()` does NOT persist `visible_desktop`/`visible_mobile`. Model casts confirm all three fields exist as booleans. Database columns have DEFAULT TRUE. Resolution: future CR-001-C implementation modifies `app/Services/Theme/ThemeNavigationService.php` (NON-SCHEMA) to accept/persist these fields from payload when provided. Backward-compatible: if omitted, DB default applies. Existing `visible => true` preserved. C-11 classified as FUTURE IMPLEMENTATION MODIFY, not a schema change. Zero migrations required. Test plan expanded to 24 cases. |
| C-12 — ThemeAsset Physical Clone Mechanism | MINOR | CLOSED | Resolved ambiguous either/or by choosing Storage::copy + direct model save as the canonical mechanism. NO synthetic UploadedFile constructed. Algorithm: verify source readable → generate new ULID-based destination path → Storage::copy → record path for compensation → create NEW ThemeAsset DB row inside aggregate tx. Destination NEVER equals source. Active asset never touched. Compensation documented. |
| C-13 — Missing Test Scenarios | MINOR | CLOSED | Added TEST 17 (filesystem compensation failure tolerance) and TEST 18 (null branding asset validity). |
| C-14 — ThemeAsset Required Fields Completion | MINOR | CLOSED | Verified actual ThemeAsset migration schema. §24.1A.D step 6 expanded with complete field contract: ulid (fresh Str::ulid()), theme_id (DRAFT), disk (config('theme.disk')), stored_filename (basename convention matching upload()), original_filename (preserved from source), mime_type/extension/size_bytes/sha256 (preserved), width/height (nullable-safe preservation), status ('ACTIVE'), uploaded_by_principal_id ($actor->id clone actor canonical Principal). All required NOT NULL columns documented with exact assignment rules. No schema change. Actor verified as canonical App\Models\Rbac\Principal with $actor->id → principals.id mapping. |
| C-15 — Stale Duplicate §24.1A.D Section | MINOR | CLOSED | Removed duplicate "#### D. ThemeAsset Ownership & ID Remap" section. The corrected "#### D. ThemeAsset Ownership & Complete Clone Row Contract" remains as the sole authoritative §24.1A.D. Physical-copy procedural detail accidentally removed during cleanup was restored under C-16. |
| C-16 — Missing Physical Copy Procedure | MINOR | CLOSED | Restored missing physical ThemeAsset clone procedure into the sole authoritative §24.1A.D: A) source storage validation (config('theme.disk') read-check with FAIL-CLOSED); B) fresh destination construction using theme/{yyyy}/{mm}/{$new_ulid}.{$ext} convention; C) explicit Storage::copy() with return-value check and FAIL-CLOSED; D) compensation registration for new clone paths before DB row creation. No duplicate D reintroduced. One authoritative heading only. |

### Corrected Counts (Round 4)

```
SCHEMA DEFINITE:     0
SCHEMA CONDITIONAL:   0
MIGRATION FILE COUNT: 0

PROPOSED CREATE FILES (this recon):  2
  1. app/Http/Controllers/Admin/SiteDesignController.php
  2. app/Services/Theme/SiteDesignCloneService.php
Plus 4 Vue pages proposed.

FUTURE IMPLEMENTATION MODIFY (not this recon):  1
  - app/Services/Theme/ThemeNavigationService.php (visibility fields in createItem)

SHARED / COORDINATION REQUIRED:  1
  - routes/web.php (admin route region, FE-CHK-009 delta in public region)

FE-CHK-009 COLLISIONS:  1 (routes/web.php — SHARED / COORDINATION REQUIRED)

PUBLICRENDERER:         READ-ONLY (no violation)

PAGEBUILDERCONTROLLER:  NO (belongs entirely to CR-001-D)

ROUTES/WEB.PHP:         SHARED / COORDINATION REQUIRED

NEW HUMAN DECISIONS:    0

TEST PLAN TOTAL:        25 cases covering clone, assets (complete field contract), visibility, compensation, null handling

BLOCKER:                0
MAJOR:                  0
MINOR:                  0
```

**Phase C/D Boundary (Post-Patch):**

CR-001-C provides: Site Design operator abstraction, copy-on-write draft foundation (SiteDesignController + SiteDesignCloneService), menu/header/footer/appearance management, preview authorization/foundation, publish/activate delegation, Advanced/Debug Theme coexistence.

CR-001-D provides: PageBuilderController, block library interaction, block listing for builder use, add/remove block behavior, reorder/enabledisable behavior, block configuration, builder composition/application workflow, builder-specific UI.

FINAL VERDICT:

CR-001-C RECON PATCHED — READY FOR CLAUDE CLOSURE REVIEW
