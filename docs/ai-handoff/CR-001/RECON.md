# CR-001 Governed Implementation Recon

> **Baseline commit:** `cc8de3341f31451b93877f20a4299609d5b5e7e5`
> **Architecture baseline:** Approved
> **CR-001 Human Recon Gate:** APPROVED
> **Human approval date:** 2026-09-21
> **Recon status:** CLOSED
> **Open Human Decisions (from architecture):** 0
> **CODEX-FE-CHK-009-01:** OPEN / VALID / UNRESOLVED
> **IMP-010:** NOT STARTED / BLOCKED
> **Implementation status:** NOT STARTED
> **RECON date:** 2026-09-21
> **Model:** qwen/qwen3.7-flash

---

## 1. Baseline Verification

| Criterion | Status | Evidence |
|-----------|--------|----------|
| Human Architecture Gate | APPROVED | `docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md` Sec. 1–2 |
| **CR-001 Human Recon Gate** | **APPROVED** | Human Authority: "I APPROVE CR-001 Human Recon Gate." (2026-09-21) |
| HD-CR001-01 (Q31 single-locale) | APPROVED Option A | Sec. 76.1–76.3 |
| HD-CR001-02 (single-actor publish) | APPROVED Option A | Sec. 76.2 |
| HD-CR001-03 (lead-time config, no hard-coded duration) | APPROVED Option C + clarification | Sec. 76.3 |
| HD-CR001-04 (responsive hybrid nav) | APPROVED Responsive Hybrid | Sec. 76.4 |
| HD-CR001-05 (Advanced view retained) | APPROVED Option A | Sec. 76.5 |
| HD-CR001-06 (no public donor count) | APPROVED Option A | Sec. 76.6 |
| HD-CR001-07 (dark-only admin) | APPROVED Option A | Sec. 76.7 |
| Open Human Decisions | 0 | Sec. 76.3 |
| CODEX-FE-CHK-009-01 | OPEN / VALID / UNRESOLVED | Sec. 54 |
| IMP-010 | NOT STARTED / BLOCKED | Sec. 3, 69, 77 |
| Locked decisions respected | YES | No locked decision silently overridden |

---

## 2. Repository Snapshot

### 2.1 Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | ^13.17 |
| Language | PHP | 8.3 |
| Database | MySQL | (shared-hosting Apache/LiteSpeed + PHP + MySQL + Cron) |
| Frontend Framework | Vue | ^3.5 |
| SSR Adapter | Inertia | ^3.3 (`inertiajs/inertia-laravel` ^3.3, `@inertiajs/vue3` ^3.7) |
| Type checking | TypeScript | ^5.9 |
| CSS | Tailwind | ^4.0 |
| Build | Vite | ^8.0 |
| Icons | @lucide/vue | ^1.46 |
| Rich text | Tiptap | ^3.31 (`@tiptap/starter-kit`, `@tiptap/vue-3`) |

### 2.2 Scale

- Migrations: **~50 files** (session → identity → rbac → cms → theme → campaign → donation → payment)
- App files: **~280 PHP files** (models, services, controllers, policies, requests, enums, adapters, contracts, middleware, support)
- Vue files: **~65 files** (Pages, Components, Layouts, composables/types)
- Tests: **93 test files** (80+ Feature, 12 Unit)
- Permissions: **~50 constants** in `PermissionRegistry`
- Policies: **12 classes**
- Seeders: **8 files**

### 2.3 Key Architectural Patterns Observed

- ULID-based route-model binding on all business entities
- Domain service layer (e.g. `CampaignService`, `ThemeService`, `DonationService`) abstracts all write operations
- `AuthorizesUsingRbac` concern shared by all policies
- `siteChrome()` (PublicRenderer) — single source of chrome data for public layout
- Domain projection resolvers (`CampaignProjectionResolver`, `ProgramProjectionResolver`) — canonical read path for PUBLIC grid data
- Try/catch-degrade-to-null for component rendering failures (PublicRenderer §22)
- Copy-on-write Draft/Publish via Theme clone (recommended by CR-001)
- Money discipline: `amount_minor` + `CurrencyMinorUnits`, never float/double

---

## 3. Existing CMS Map

### 3.1 Models (`app/Models/Cms/`)

| Model | Classification | Notes |
|-------|---------------|-------|
| `CmsPage` | KEEP | Static page content, published/revision lifecycle, meta SEO fields |
| `CmsArticle` | KEEP | Article content, same revision pattern; NO article_type/classification column on the article itself (lives on revision) |
| `CmsPath` | KEEP | Canonical URL path per content owner (page/article) |
| `CmsMediaAsset` | KEEP | Media library, needs alt-text confirmation and responsive variant metadata (future ADD) |
| `CmsMediaReference` | KEEP | Usage-tracking join table |
| `CmsContentRevision` | KEEP | Versioned payload, `article_type` lives here (classification), body_html, meta_*, og_* |
| `CmsHomepageAssignment` | KEEP | Singleton homepage pointer |

### 3.2 Services

| Service | Classification | Notes |
|---------|---------------|-------|
| `PageService` | KEEP | IMP-005 page CRUD |
| `ArticleService` (via `PageController` routing) | KEEP | IMP-005 article CRUD |
| `MediaService` / `MediaTokenResolver` / `MediaCleanupService` | KEEP | Media upload, token URLs, cleanup |
| `RevisionService` | KEEP | Draft creation/editing |
| `PublicationService` | KEEP | Publish/unpublish/schedule transitions |
| `PathService` | KEEP | URL path management |
| `PublishedContent` (value object) | KEEP | Published projection DTO |
| `HomepageContentResolver` | KEEP | Homepage singleton resolution |

### 3.3 Controllers

| Controller | Classification | Notes |
|-----------|---------------|-------|
| `PageController` | KEEP | Full CRUD + publish/unpublish/archive |
| `ArticleController` | KEEP | Full CRUD + publish/unpublish/archive |
| `MediaController` | KEEP | Upload, update metadata, archive |
| `HomepageController` | KEEP | Singleton assignment edit |

### 3.4 Routes

All under `/admin/content/*`, prefix-grouped:
- `/admin/content/pages` — index/create/store/edit/update/publish/unpublish/archive
- `/admin/content/articles` — same shape
- `/admin/content/media` — index/store/update/archive
- `/admin/content/homepage` — edit/update

### 3.5 Admin Pages (Vue)

| Page | Classification | Notes |
|------|---------------|-------|
| `Cms/Pages/Index.vue` | KEEP | List pages |
| `Cms/Pages/Create.vue` | KEEP | Create form |
| `Cms/Pages/Edit.vue` | KEEP | Edit with live published preview (v-html) |
| `Cms/Articles/Index.vue` | KEEP | List articles |
| `Cms/Articles/Create.vue` | KEEP | Create form |
| `Cms/Articles/Edit.vue` | KEEP | Edit with live published preview (v-html) |
| `Cms/Media/Index.vue` | KEEP | Media library |
| `Cms/Homepage/Edit.vue` | KEEP | Homepage designation |

### 3.6 Tests

| Test File | Classification | Notes |
|-----------|---------------|-------|
| `Cms/PageControllerTest` | KEEP | Feature tests for page CRUD |
| `Cms/ArticleControllerTest` | KEEP | Feature tests for article CRUD |
| `Cms/MediaControllerTest` | KEEP | Media upload/access tests |
| `Cms/PublicationServiceTest` | KEEP | Publication transition tests |
| `Cms/RevisionServiceTest` | KEEP | Revision tests |
| `Cms/PathReleaseTest` | KEEP | Path release tests |
| `Cms/ContentAuthorizationTest` | KEEP | Policy tests |
| `Cms/SchedulerTest` | KEEP | Scheduled publish/unpublish cron tests |
| Various Cms/**ServiceTest files | KEEP | Service-level unit tests |

### 3.7 ADAPT Needs

- **News classification**: `CmsArticle` has no `category`/`classification` column today. If adopted (Sec. 24), MIGRATION REQUIRED (`article_type` or equivalent column). Currently only `article_type` on revisions.
- **Video reference**: No video model exists (Sec. 66). NEW model needed if Video section entry is added.

---

## 4. Existing Theme Engine Map

### 4.1 Models (`app/Models/Theme/`)

| Model | Classification | Notes |
|-------|---------------|-------|
| `Theme` | KEEP | Named presentation config; ACTIVE/ARCHIVED/DRAFT status, `is_system_default` flag |
| `ThemeActivation` | KEEP | Singleton "current active Theme" (id=1 CHECK) |
| `ThemeTemplate` | KEEP | Per-(theme_id, content_kind); content_kind = 'home'/'page'/'article' |
| `ThemeSection` | KEEP | Belongs to theme_id directly; joined via `theme_template_sections` |
| `ThemeComponent` | KEEP | Belongs to theme_section_id; typed (`hero`, `rich_text`, `image`, `cta_button`, `content_list`, `stats`, `banner`, `card_grid`, `navigation_menu_slot`) |
| `ThemeBrandingConfig` | KEEP | One-per-theme; color_tokens (JSON), font_family, logo/favicon asset FKs |
| `ThemeAsset` | KEEP | Uploaded theme media (logo/favicon/etc.), resolved via token resolver |
| `ThemeNavigationMenu` | Keep | One-per-(theme_id, code) named menu |
| `ThemeNavigationItem` | ADAPT | Tree (parent_id self-FK); polymorphic destination; needs `visible_desktop`/`visible_mobile` columns (Sec. 31, Sec. 55) |
| `Concerns/GeneratesUlid` | KEEP | Shared trait |

### 4.2 Services

| Service | Classification | Notes |
|---------|---------------|-------|
| `ThemeService` | KEEP | CRUD for Theme |
| `ThemeActivationService` | KEEP | Singleton activation/deactivation |
| `ThemeTemplateService` | KEEP | Template CRUD |
| `ThemeSectionService` | KEEP | Section CRUD, reorder |
| `ThemeComponentService` | KEEP | Component CRUD |
| `ThemeBrandingService` | KEEP | Branding save/read |
| `ThemeAssetService` | KEEP | Asset upload/archive |
| `ThemeNavigationService` | KEEP | Menu/item CRUD |
| `ThemeScopeResolver` | KEEP | ORGANIZATION scope resolver |
| `ThemeAuditLogger` / `ThemeAuditEventRegistrar` | KEEP | Audit event emission |
| `ThemeAssetTokenResolver` | KEEP | Logo/favicon URL generation |
| `NavigationDestinationResolver` | KEEP | CLOSED enum resolution (SYSTEM_ROUTE/CMS_CONTENT/EXTERNAL_URL) |
| `NavigationDestinationValidator` | KEEP | Destination config validation |
| `ComponentConfigValidator` | KEEP | Validates component `config` JSON against type schema |
| `BrandingConfigValidator` | KEEP | Validates branding config |
| `Exceptions/*` (ValidationException, AssetValidationException, ActivationConflictException) | KEEP | Domain exception hierarchy |
| `PublicRenderer` | ADAPT | Rendering pipeline + `siteChrome()` (Sec. 32 proposes richer payload) |

### 4.3 Controllers

| Controller | Classification | Notes |
|-----------|---------------|-------|
| `Theme/ThemeController` | KEEP (WRAP) | Raw CRUD for template/section/component/navigation/branding/assets (Sec. 26: keep schema, wrap with operator-facing UI) |

### 4.4 Rendering Pipeline

```
PublicContentController::home()    -> HomepageContentResolver lookup -> PublicRenderer.renderForContentKind('home', $content)
PublicContentController::show()    -> ContentResolverService path lookup -> PublicRenderer.renderForContentKind($kind, $content)
   -> resolves active Theme (ThemeActivation, fallback to system default)
   -> resolves ONE ThemeTemplate for (theme, content_kind)
   -> buildSections(): walks theme_template_sections -> ThemeSection -> ThemeComponent
   -> renderComponent(): match($type) -> renderHero/renderRichText/renderImage/renderCtaButton/renderContentList/renderBanner/renderCardGrid/renderNavigationMenuSlot
        (each wrapped in try/catch; failure degrades to absent, never fatal)
   -> buildBranding(): resolves color_tokens/font_family/logo_url/favicon_url
   -> Inertia::render('Public/ThemeRender', [...])
PublicRenderer::siteChrome()       -> resolves effective template -> primaryNavigation() -> returns {branding, navigation}
```

### 4.5 Admin Pages

| Page | Classification | Notes |
|------|---------------|-------|
| `Theme/Index.vue` | KEEP | Theme list/creation |
| `Theme/Show.vue` | KEEP | Theme detail with templates/sections/components/view tree |

### 4.6 Tests

| Test File | Classification | Notes |
|-----------|---------------|-------|
| `Theme/ThemeServiceTest` | KEEP | Template/Section/Component CRUD tests |
| `Theme/ThemeAuthorizationTest` | KEEP | Policy tests |
| `Theme/ThemeActivationServiceTest` | KEEP | Activation singleton tests |
| `Theme/ThemeSchemaConstraintsTest` | KEEP | DB constraint tests |
| `Theme/ThemeCampaignProjectionTest` | KEEP | content_list with campaign projection tests |
| `Theme/ComponentConfigValidatorTest` (Unit) | KEEP | Schema validation unit tests |
| `Theme/BrandingConfigValidatorTest` (Unit) | KEEP | Branding validation unit tests |
| `Theme/NavigationDestinationValidatorTest` (Unit) | KEEP | Destination validation unit tests |
| `Theme/ThemeAssetServiceTest` | KEEP | Asset upload/tests |
| `Theme/PublicContentControllerTest` | KEEP | Public rendering integration tests |

---

## 5. Existing Public Frontend Map

### 5.1 Pages

| Page | Classification | Notes |
|------|---------------|-------|
| `Public/ThemeRender.vue` | ADAPT | Full Theme/Template/Section/Component renderer; uses v-html for rich_text; consumes `content` + `template` props from PublicRenderer |
| `Public/CampaignIndex.vue` | ADAPT | Campaign listing (paginated); needs responsive header/footer composition |
| `Public/CampaignShow.vue` | ADAPT | Campaign detail; has donate link since FE-CHK-009, gated on `is_donation_eligible`; uses v-html for description |
| `Public/ProgramShow.vue` | ADAPT | Program detail; uses v-html for description |
| `Public/DonationCreate.vue` | ADAPT | Single flat form (amount/currency/display_name/guest_name/guest_email/anonymous); needs staged funnel (Sec. 14) |
| `Public/PaymentCreate.vue` | KEEP (FE-CHK-009 new) | Provider selector (manual_transfer/tripay/xendit/stripe) |
| `Public/PaymentShow.vue` | KEEP (FE-CHK-009 new) | Payment status/instructions/evidence upload |
| `Public/NotFound.vue` | KEEP | 404 page rendered via theme engine |
| `Foundation.vue` | KEEP | Foundation wrapper for Inertia SPA entries |

### 5.2 Components (Public)

| Component | Classification | Notes |
|-----------|---------------|-------|
| `Public/PublicShell.vue` | ADAPT | Generic composition wrapping Header/Footer/Slot; theme-data-driven (color tokens as CSS vars) |
| `Public/PublicHeader.vue` | ADAPT | Branding-logo + desktop nav + mobile hamburger toggle + mobile nav; currently hardcoded width constraint `max-w-6xl` |
| `Public/PublicFooter.vue` | KEEP | Footer component (structure confirmed present) |

### 5.3 Missing Pieces vs. Approved Public V2

| Gap | Notes |
|-----|-------|
| Mobile bottom navigation | `MobileBottomNav.vue` — NEW component (Sec. 10) |
| Sticky donate CTA | `StickyDonateCta.vue` — NEW component (Sec. 10) |
| Desktop utility bar | Extension of PublicHeader or new component |
| Staged donation funnel steps | `DonationCreate.vue` evolution OR new AmountStep/IdentityStep/ReviewStep components |
| ZISWAF center placeholder pages | NEW pages (Sec. 11): `/zakat-center`, `/infaq`, `/sedekah`, `/wakaf`, `/fidyah`, `/quran-center` |
| Calculator pages | NEW pages (Sec. 16–20): Zakat Calculator, Fidyah Calculator |
| Impact/Transparency page | NEW page |
| Donor Services page | NEW page |
| Search | FUTURE (Sec. 65) |

---

## 6. Existing Admin Map

### 6.1 Shell: `AdminLayout.vue`

| Element | Classification | Evidence |
|---------|---------------|----------|
| Dark/light palette | Light (ADAPT to dark) | `bg-white`, `bg-slate-50`, `border-slate-200` throughout |
| Sidebar expanded/collapsed | ADAPT (mechanism sound) | `collapsed` ref + localStorage persistence (`admin.sidebar.collapsed`), icon-only collapsed mode |
| Mobile drawer | ADAPT (structural gap: Escape key works, no focus trap) | `mobileOpen` ref + overlay `@click` close + `handleEscapeKey` (FE-CHK-009 fix for Escape) |
| Topbar | ADAPT | Breadcrumb slot, empty slot, topbar slot available but minimal |
| Profile menu | ADAPT | Email text + avatar icon + logout only; no role badge, no name |
| Navigation array | ADAPT | Hardcoded `navGroups[]` with 6 groups; no permission-aware filtering (always renders same items) |
| Icons | KEEP + extend | Curated Lucide subset (~27 icons); pattern correct |

### 6.2 Admin Dashboard Route

```php
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', [
        'counts' => [
            'pages' => CmsPage::query()->count(),
            'articles' => CmsArticle::query()->count(),
            'programs' => Program::query()->count(),
            'campaigns' => Campaign::query()->count(),
            'funds' => Fund::query()->count(),
            'themes' => Theme::query()->count(),
        ],
    ]);
})->name('dashboard');
```

Explicitly commented: "IMP-008+ metrics (donations, donors, payments, ledger) do not exist and are never fabricated here."

### 6.3 Admin Pages (by module)

| Module | Pages | Classification |
|--------|-------|---------------|
| CMS | `Cms/Pages/*`, `Cms/Articles/*`, `Cms/Media/*`, `Cms/Homepage/*` | KEEP |
| Theme | `Theme/Index.vue`, `Theme/Show.vue` | KEEP (wrapping only) |
| Campaign | `Campaign/Campaigns/*`, `Campaign/Programs/*`, `Campaign/Funds/*` | KEEP |
| Donation | `Donation/Index.vue`, `Donation/Show.vue`, `Donation/Plans/*`, `Donation/Admin/*` | KEEP |
| Payment | `Payment/Index.vue` (implied), admin controllers present | KEEP |
| Auth | `Auth/Login.vue`, `Auth/Register.vue`, `Auth/ResetPassword.vue`, `Auth/ForgotPassword.vue`, `Auth/MfaEnroll.vue`, `Auth/MfaChallenge.vue` | KEEP (unchanged) |
| Dashboard | `Dashboard.vue` | REPLACE (new widget set, same "never fabricate" discipline) |

### 6.4 UI Components (existing admin)

| Component | Classification | Notes |
|-----------|---------------|-------|
| `UI/Button.vue` | KEEP | Primary button styles |
| `UI/Card.vue` | KEEP | Card containers |
| `UI/Input.vue` | KEEP | Text input |
| `UI/Select.vue` | KEEP | Dropdown select |
| `UI/Textarea.vue` | KEEP | Multi-line input |
| `UI/FormField.vue` | KEEP | Form field with label/error |
| `UI/Alert.vue` | KEEP | Alert messages |
| `UI/StatusBadge.vue` | KEEP | Status chips |
| `UI/EmptyState.vue` | KEEP | Empty/no-data placeholder |
| `UI/Breadcrumb.vue` | KEEP | Breadcrumb trail |
| `UI/MediaGallery.vue` | KEEP | Image gallery display |
| `UI/RichTextEditor.vue` | KEEP | Tiptap editor integration |
| `UI/PageHeader.vue` | KEEP | Page title/subtitle/actions |
| `Campaign/LifecycleStepper.vue` | KEEP | Campaign status progress indicator |

### 6.5 Admin Gaps vs. Approved Admin V2

| Gap | Notes |
|-----|-------|
| Dark palette | All colors are light/slate/emerald — must restyle to dark stone/slate |
| Permission-aware navigation | Sidebar always renders all groups to all viewers — client-side filtering needed using server-computed `can:` prop |
| Dashboard widgets | ActionRequired, CollectionChart, StatCards, Table, FilterBar — all NEW |
| Grouped expandable sidebar | Groups exist but are flat, not collapsible sections |
| Sidebar tooltips on collapsed | Only `title` attribute; tooltip component needed |
| AdminBreadcrumb component | Slot exists but no dedicated component |
| AdminSearch/Global search | PRESENTATION PLACEHOLDER or omit until real backend |
| Notification entry | OMIT (no notification backend) |
| Profile dropdown enrichment | Role badge, display name, account security link |

---

## 7. ZISWAF Current-State Map

| Product | Status | Evidence |
|---------|--------|----------|
| Zakat | NOT IMPLEMENTED | Zero models, zero controllers, zero routes, zero views found |
| Infaq | NOT IMPLEMENTED | Same — no trace in codebase |
| Sedekah | NOT IMPLEMENTED | Same |
| Wakaf | PARTIAL (as Donation product) | `Campaign::product_type` can include "Wakaf"; treated as standard donation via IMP-007; no dedicated Wakaf UX |
| Fidyah | NOT IMPLEMENTED | No trace |
| Qurban | NOT IMPLEMENTED | No trace |
| Zakat Calculator | NOT IMPLEMENTED | No calculator services, models, or UI |
| Fidyah Calculator | NOT IMPLEMENTED | No calculator services, models, or UI |

**Classification note:** All ZISWAF products are explicitly "FUTURE CAPABILITY" (CR-001 Sec. 41). They may appear as disabled/"coming soon" nav entries or IA placeholders, but NO business rules, routes, or models are created until IMP-019+ owns them.

---

## 8. Calculator Current-State Map

| Area | Status | Evidence |
|------|--------|----------|
| Zakat calculator | NOT IMPLEMENTED | No files found for zakat/calculator/nisab |
| Fidyah calculator | NOT IMPLEMENTED | No files found |
| Money values | EXISTING — REUSE | `App\Support\Money\Money` (amount_minor + currency), `CurrencyMinorUnits` |
| Money semantics | LOCKED DISCIPLINE | No floats anywhere for money; DECIMAL-style fixed precision for monetary; physical quantities (gold weight) use DECIMAL |
| Policy/version patterns | NOT IMPLEMENTED | No effective-date, audit/version, or lead-time patterns exist yet |
| CalculationInput/result/snapshot value objects | NOT IMPLEMENTED | Must be invented as part of IMP-019 architecture contract (CR-001 defines contract only) |

---

## 9. Payment Integration Map

### 9.1 Flow

```
Campaign Detail -> "Donasi Sekarang" -> DonationCreate.vue (POST /campaigns/{slug}/donations)
  -> (after successful donation) -> PaymentCreate.vue (GET /donations/{ulid}/payments/create)
  -> provider selection -> PublicPaymentController.store() (POST /donations/{ulid}/donations)
  -> PaymentShow.vue (GET /donations/{ulid}/payments/{payment})
  -> provider redirect OR manual transfer evidence upload
```

### 9.2 Controllers

| Controller | Classification | Notes |
|-----------|---------------|-------|
| `PublicDonationController` | RESERVED FOR CR-001-J | Handles guest donation creation + session-possession mechanism |
| `PublicPaymentController` | RESERVED FOR CR-001-J | Handles payment creation + evidence upload + possession check |
| `AdminDonationController` | KEEP | Admin donation list/cancel |
| `AdminPaymentController` | KEEP | Admin payment approve/reject/hold |
| `AdminPaymentProviderConfigController` | KEEP | Provider credential/bank account management |
| `DashboardDonationController` | KEEP | Authenticated donor donation views |
| `DashboardPaymentController` | KEEP | Authenticated donor payment views |
| Webhook controllers | KEEP | Tripay/Xendit/Stripe webhook handlers |

### 9.3 Providers

| Provider | Adapter | Classification |
|----------|---------|---------------|
| Manual Transfer | `ManualTransferAdapter` | KEEP |
| Tripay | `TripayAdapter` | KEEP |
| Xendit | `XenditAdapter` | KEEP |
| Stripe | `StripeAdapter` | KEEP |

### 9.4 Services

| Service | Classification | Notes |
|---------|---------------|-------|
| `DonationService` | RESERVED FOR CR-001-J | Core donation creation, idempotency, possession |
| `PaymentCreationService` | KEEP | Creates Payment Attempts |
| `PaymentTransitionService` | KEEP | State machine transitions |
| `PaymentAdapterFactory` | KEEP | Provider adapter selection |
| `PaymentCredentialPayload` | KEEP | Credential construction |
| `PaymentWebhookHandler` | KEEP | Webhook processing |
| `ManualTransferVerificationService` | KEEP | Evidence review workflow |
| `ManualTransferEvidenceService` | KEEP | Evidence storage |
| `PaymentAuditLogger` / `PaymentAuditEventRegistrar` | KEEP | Audit emission |
| `PaymentScopeResolver` / `PaymentOwnScopeResolver` | KEEP | Authorization scopes |

### 9.5 Guest Possession Mechanism (CODEX-FE-CHK-009-01 context)

- Donation: `guest_donation_ulids` session array (Sec. 115–124 of PublicDonationController)
- Payment: `guest_payment_ulids` session array (equivalent in PublicPaymentController)
- Idempotent replay issue: re-submitting same Donation with same idempotency_key may resolve to an existing Donation, and the session-ulid check in `store()` could grant possession incorrectly in a fresh anonymous session

---

## 10. CODEX-FE-CHK-009-01 Reserved Files

These files own the mechanisms related to CODEX-FE-CHK-009-01 and are **RESERVED FOR CR-001-J**.

| File | Ownership | Reason |
|------|-----------|--------|
| `app/Http/Controllers/PublicDonationController.php` | CR-001-J | Contains guest session-possession mechanism (line 115–124); security finding lives here |
| `app/Http/Controllers/PublicPaymentController.php` | CR-001-J | Contains payment creation + possession checks |
| `app/Services/Donation/DonationService.php` | CR-001-J | Core donation creation/idempotency; DO NOT MODIFY per instructions |
| `tests/Feature/Donation/DonationHttpTest.php` | CR-001-J | Regression tests for donation HTTP behavior |
| `tests/Feature/Payment/PaymentHttpTest.php` | CR-001-J | Regression tests for payment HTTP behavior |

**No earlier phase may modify these files for the security finding.** Their modification ownership belongs exclusively to CR-001-J once Human Spec Approval is granted.

---

## 11. FE-CHK-009 Dirty File Collision Matrix

Git status snapshot (confirmed):

### Modified (16 files)

| Path | Status Indicator | FE-CHK-009 Purpose | CR-001 Phase That Would Touch It | Collision | Handling |
|------|-----------------|-------------------|----------------------------------|-----------|----------|
| `app/Http/Controllers/PublicCampaignController.php` | ` M` (working-tree) | Added `chrome` prop to campaign index/detail | CR-001-E (public shell composition) | YES | **Preserve** — CR-001-E should reuse the `chrome` shape already passed here |
| `app/Http/Controllers/PublicDonationController.php` | ` M` (working-tree) | Session-possession for guest donations (CODEX-FE-CHK-009-01 area) | CR-001-J | YES | **Reserved for CR-001-J** |
| `app/Http/Controllers/PublicPaymentController.php` | ` M` (working-tree) | Payment creation UI + possession | CR-001-F/G | YES | **Preserve** — CR-001-F/G integrate with existing flow, do not rewrite |
| `app/Http/Controllers/PublicProgramController.php` | ` M` (working-tree) | Added `chrome` prop to program show | CR-001-E | YES | **Preserve** — reuse existing chrome passing pattern |
| `app/Services/Theme/PublicRenderer.php` | ` M` (working-tree) | `siteChrome()` method added | CR-001-E | YES | **Consume later** — CR-001-E extends payload richness; acknowledge existing method |
| `docs/implementation/POST-IMP-009-frontend-integration.md` | ` M` (working-tree) | FE-CHK-009 spec document | None | NO | **Preserve** — documentation artifact |
| `resources/js/Components/Admin/AdminLayout.vue` | ` M` (working-tree) | FE-CHK-009 nav additions (Donations/Payments groups, lucide icons) | CR-001-D | YES | **Requires controlled reconciliation** — CR-001-D decomposes AdminLayout.vue; FE-CHK-009 nav group additions must be merged into the new primitives |
| `resources/js/Components/UI/Icon.vue` | ` M` (working-tree) | Extended lucide registry (heart, creditCard, settings) | CR-001-D | YES | **Preserve** — CR-001-D extends Icon.vue compatibly |
| `resources/js/Pages/Public/CampaignIndex.vue` | ` M` (working-tree) | Added chrome consumption | CR-001-E | YES | **Preserve** — already consumes chrome correctly |
| `resources/js/Pages/Public/CampaignShow.vue` | ` M` (working-tree) | Added chrome consumption | CR-001-E | YES | **Preserve** — already consumes chrome correctly |
| `resources/js/Pages/Public/DonationCreate.vue` | ` M` (working-tree) | FE-CHK-009 changes to donation form | CR-001-F | YES | **Requires controlled reconciliation** — CR-001-F replaces this with staged funnel; reconcile FE-CHK-009 changes into new step components |
| `resources/js/Pages/Public/ProgramShow.vue` | ` M` (working-tree) | Added chrome consumption | CR-001-E | YES | **Preserve** — already consumes chrome correctly |
| `resources/js/Pages/Public/ThemeRender.vue` | ` M` (working-tree) | FE-CHK-009 PublicHeader/Footer integration | CR-001-E | YES | **Requires controlled reconciliation** — FE-CHK-009 made ThemeRender use PublicShell; CR-001-E further evolves shell/header/footer |
| `routes/web.php` | ` M` (working-tree) | Added donation/payment routes for FE-CHK-009 | CR-001-E/F/G | YES | **Preserve** — FE-CHK-009 routes remain valid for CR-001 phases |
| `tests/Feature/Donation/DonationHttpTest.php` | ` M` (working-tree) | FE-CHK-009 donation test updates | CR-001-F | YES | **Preserve** — regression gate for CR-001-F |
| `tests/Feature/Payment/PaymentHttpTest.php` | ` M` (working-tree) | FE-CHK-009 payment test updates | CR-001-F/G | YES | **Preserve** — regression gate for CR-001-F |

### Untracked (8 files)

| Path | FE-CHK-009 Purpose | CR-001 Phase That Would Touch It | Collision | Handling |
|------|-------------------|----------------------------------|-----------|----------|
| `resources/js/Components/Public/PublicFooter.vue` | Footer component (FE-CHK-009 new) | CR-001-E | YES | **Consume later** — already the seam CR-001-E targets; adapt rather than rebuild |
| `resources/js/Components/Public/PublicHeader.vue` | Header component (FE-CHK-009 new) | CR-001-E | YES | **Consume later** — already the seam CR-001-E targets; adapt rather than rebuild |
| `resources/js/Components/Public/PublicShell.vue` | Public composition shell (FE-CHK-009 new) | CR-001-E | YES | **Consume later** — already the seam CR-001-E targets; adapt rather than rebuild |
| `resources/js/Pages/Public/PaymentCreate.vue` | Payment provider selector page | CR-001-F/G | YES | **Preserve** — CR-001-F integrates with existing payment flow |
| `resources/js/Pages/Public/PaymentShow.vue` | Payment status/instructions/evidence upload page | CR-001-F/G | YES | **Preserve** — CR-001-F integrates with existing payment flow |
| `tests/Feature/Campaign/CampaignShowDonationLinkTest.php` | Tests donate link presence on campaign show | CR-001-F | YES | **Preserve** — regression test for CR-001-F |
| `tests/Feature/Donation/DonationFlowRedirectTest.php` | Tests donation post-redirect to payment/create | CR-001-F | YES | **Preserve** — regression test for CR-001-F |
| `tests/Feature/Payment/PaymentHtmlPageTest.php` | Tests payment HTML page structure | CR-001-F/G | YES | **Preserve** — regression test for CR-001-F |

**Total FE-CHK-009 dirty files:** 24 (16 modified + 8 untracked)
**Collisions with CR-001:** 17 files (collision YES)
**No collision (documentation preserved):** 1 (spec document)
**Reserved for CR-001-J:** 3 files
**Requires controlled reconciliation:** 3 files (AdminLayout.vue, DonationCreate.vue, ThemeRender.vue)

---

## 12. Database Impact Map

### 12.1 Migration Safety Plan (General)

- Additive columns only — never destructive
- Nullable with sensible defaults for visibility flags
- Never delete data
- Migration names follow existing convention: `{year}_{month}_{day}_NNNNN_{description}`

### 12.2 CR-001 Migration Impact (Eventually Required)

| # | Change | Type | Classification | Table | Column/Entity | Purpose | CR-001 Phase | Architecture Ref |
|---|--------|------|---------------|-------|--------------|---------|-------------|-----------------|
| 1 | Column | ADDITIVE | DEFINITE | `theme_navigation_items` | `visible_desktop BOOLEAN NULL DEFAULT TRUE` | Desktop/mobile nav targeting (Sec. 31, 55) | B | Sec. 26 ADAPT |
| 2 | Column | ADDITIVE | DEFINITE | `theme_navigation_items` | `visible_mobile BOOLEAN NULL DEFAULT TRUE` | Desktop/mobile nav targeting (Sec. 31, 55) | B | Sec. 26 ADAPT |
| 3 | Column | OPTIONAL ADDITIVE | CONDITIONAL | `users` | `name VARCHAR(255) NULL` | Optional display-name adoption (Sec. 45) | B | Architecture Sec. 45 optional display-name |
| 4 | Column | OPTIONAL ADDITIVE | CONDITIONAL | `cms_articles` | Classification/category field | News taxonomy if adopted (Sec. 24, 66) | B | Content model extension only |
| 5 | New Table | ADDITIVE | DEFINITE | `zakat_types` | Registry: code, name, calculation_method_ref, is_active | Zakat type registry (architecture contract) | G | Sec. 16 |
| 6 | New Table | ADDITIVE | DEFINITE | `zakat_policies` | Versioned policy: rate, nisab_basis, source_ref, effective_from, effective_until, status | Zakat policy versioning (architecture contract) | G | Sec. 16–17 |
| 7 | New Table | ADDITIVE | DEFINITE | `nisab_policies` | Nisab basis threshold reference | Nisab threshold management (architecture contract) | G | Sec. 16 |
| 8 | New Table | ADDITIVE | DEFINITE | `gold_price_references` | Dated gold price snapshot: amount_minor, currency, as_of_date, source_ref | Gold price historical record (no live float API trust) | G | Sec. 16, 64 |
| 9 | New Table | ADDITIVE | DEFINITE | `zakat_calculation_snapshots` | Immutable calculation record: input, result, policy_version_ref, computed_at, acting_principal | Historical calculation reproducibility (architecture contract) | G | Sec. 16 |
| 10 | New Table | ADDITIVE | DEFINITE | `fidyah_policies` | Versioned Fidyah policy (rate per day, versioned, dated) | Fidyah policy versioning (architecture contract) | G | Sec. 20 |
| 11 | New Table | ADDITIVE | DEFINITE | `fidyah_calculation_snapshots` | Immutable Fidyah calculation record | Historical calculation reproducibility (architecture contract) | G | Sec. 20 |
| 12 | New Table | ADDITIVE | DEFINITE | `policy_lead_time_configs` | Configurable minimum effective-date lead time duration | Lead-time governance setting (HD-CR001-03) | G | Sec. 63 |

**Summary:** 12 total schema items (10 definite + 2 conditional) in CR-001 scope, of which 10 are definitive and 2 are conditional column migrations that may or may not be created depending on implementation adoption decisions during CR-001-B. **Total confirmed schema surface: 12 items.** Note: Schema item count does not equal migration file count. Multiple compatible schema changes may be implemented in a single Laravel migration where appropriate. For example, `theme_navigation_items.visible_desktop` and `theme_navigation_items.visible_mobile` may reasonably be introduced by one migration file. The exact migration file count is NOT YET DETERMINED and belongs to CR-001-B implementation planning.

Note: Schema item count does not equal migration file count. Multiple compatible schema changes may be implemented in a single Laravel migration where appropriate. For example, `theme_navigation_items.visible_desktop` and `theme_navigation_items.visible_mobile` may reasonably be introduced by one migration file. The exact migration file count is NOT YET DETERMINED and belongs to CR-001-B implementation planning.

**None of these invent religious rates, formulas, nisab values, or lead-time durations.** All tables are architecture-contract foundations for IMP-019 business rules; no ZISWAF transactional tables (deferred to IMP-019).

### 12.3 Tables NOT Created by CR-001

Per Sec. 55 and Sec. 77: Qurban/Infaq/Sedekah/Wakaf transaction tables deferred to their owning future IMP. No Ledger tables (IMP-010). No Refund, Withdrawal, Distribution, Reconciliation tables (IMP-010+).

---

## 13. Site Design V2 File Map

### 13.1 Conceptual Scope

Branding, Logo, Favicon, Colors, Typography, Desktop Header, Mobile Header, Desktop Navigation, Mobile Navigation, Footer, Default Layouts, responsive behavior.

### 13.2 File Map

| Operation | File | Notes |
|-----------|------|-------|
| REUSE | `app/Models/Theme/Theme.php` | KEPT — becomes "Site Design" conceptually |
| REUSE | `app/Models/Theme/ThemeActivation.php` | KEPT — one-active-singleton |
| REUSE | `app/Models/Theme/ThemeBrandingConfig.php` | KEPT |
| REUSE | `app/Models/Theme/ThemeAsset.php` | KEPT |
| REUSE | `app/Models/Theme/ThemeNavigationMenu.php` | KEPT |
| ADAPT | `app/Models/Theme/ThemeNavigationItem.php` | +visible_desktop/+visible_mobile columns |
| REUSE | `app/Services/Theme/ThemeBrandingService.php` | KEPT |
| REUSE | `app/Services/Theme/ThemeAssetService.php` | KEPT |
| REUSE | `app/Services/Theme/ThemeNavigationService.php` | KEPT |
| READ-ONLY | `app/Services/Theme/PublicRenderer.php` | siteChrome() contract; modifications owned by CR-001-E exclusively |
| ADAPT | `app/Policies/ThemePolicy.php` | +preview() method (Sec. 30) |
| NEW | `app/Http/Controllers/Admin/SiteDesignController.php` | Thin wrapper over existing theme endpoints |
| NEW | `resources/js/Pages/Admin/SiteDesign/Overview.vue` | Site Design overview/dashboard |
| NEW | `resources/js/Pages/Admin/SiteDesign/Branding.vue` | Branding/color/typography/logo editing |
| NEW | `resources/js/Pages/Admin/SiteDesign/Navigation.vue` | Menu item editor with visibility toggles |
| NEW | `resources/js/Pages/Admin/SiteDesign/Header.vue` | Header/mobile-header/desktop-header composer |
| CREATE TESTS | Policy preview test, SiteDesign controller tests | Per Sec. 68 |
| MIGRATION | Nav visibility columns on `theme_navigation_items` | Per Sec. 12 |

---

## 14. Page Builder V2 File Map

### 14.1 Block Registry & Types

Existing 8 component types (KEEP): hero, rich_text, image, cta_button, content_list, stats, banner, card_grid, navigation_menu_slot

New types (ADDITIVE — just new string values in existing VARCHAR column):
- campaign_carousel
- video
- ziswaf_services
- quick_donation (hybrid)
- testimonials
- faq
- gallery
- articles_news
- partners
- contact
- safe_custom_content

### 14.2 File Map

| Operation | File | Notes |
|-----------|------|-------|
| REUSE | `app/Models/Theme/ThemeComponent.php` | KEPT |
| REUSE | `app/Services/Theme/ComponentConfigValidator.php` | ADAPT — add schemas for new types |
| REUSE | `app/Services/Theme/PublicRenderer.php` | READ-ONLY | For render integration point understanding; modifications owned by CR-001-E |
| NEW | `app/Http/Controllers/Admin/PageBuilderController.php` | Thin wrapper: reorderBlocks, toggleBlockVisibility, configureBlock |
| NEW | `resources/js/Pages/Admin/PageBuilder/Home.vue` | Home page block editor |
| NEW | `resources/js/Pages/Admin/PageBuilder/Page.vue` | CMS page block editor |
| NEW | `resources/js/Pages/Admin/PageBuilder/Article.vue` | Article page block editor |
| NEW | `resources/js/Components/Admin/PageBuilder/BlockList.vue` | Ordered block list with reorder controls |
| NEW | `resources/js/Components/Admin/PageBuilder/BlockRow.vue` | Individual block row (settings/move/enable/disable) |
| NEW | `resources/js/Components/Admin/PageBuilder/BlockLibrary.vue` | Available blocks panel |
| NEW | `resources/js/Components/Admin/PageBuilder/BlockConfigure.vue` | Contextual block configuration panel |
| CREATE TESTS | Block validation tests, Page Builder rendering tests | Per Sec. 68 |
| MIGRATION | N/A — new types are just VARCHAR strings | Per Sec. 55 |

### 14.3 Keyboard-Operable Ordering Requirement

Per CR-001 Sec. 27: drag-and-drop is NEVER the primary ordering mechanism. Reorder controls MUST be keyboard-operable buttons (up/down arrows, "move to position N"). Drag-and-drop, if implemented, is layered enhancement.

---

## 15. Admin Experience V2 File Map

### 15.1 Design System Primitives

| Component | Operation | File | Notes |
|-----------|-----------|------|-------|
| AdminShell | ADAPT | `AdminLayout.vue` | Decompose shell/sidebar/topbar into dark palette |
| AdminSidebar | ADAPT | extracted from AdminLayout.vue | Collapsible, grouped, permission-aware, tooltips on collapse |
| AdminSidebarGroup | NEW | `Components/Admin/AdminSidebarGroup.vue` | Expandable group container |
| AdminSidebarItem | ADAPT | extracted from AdminLayout.vue | Colored Lucide icons, active state, permission-aware |
| AdminTopbar | ADAPT | extracted from AdminLayout.vue | Sidebar toggle, breadcrumb, profile menu |
| AdminProfileMenu | ADAPT | extracted from AdminLayout.vue | Avatar/initial, name/email, role badge, account links |
| AdminBreadcrumb | NEW | `Components/Admin/AdminBreadcrumb.vue` | Derivable from route name |
| AdminPageHeader | ADAPT | reuse `UI/PageHeader.vue` | Dark-adapted |
| AdminStatCard | NEW | `Components/Admin/AdminStatCard.vue` | Dashboard stat card |
| AdminActionCard | NEW | `Components/Admin/AdminActionCard.vue` | Action-required widget item |
| AdminChartCard | NEW | `Components/Admin/AdminChartCard.vue` | Collection chart widget |
| AdminTable | NEW | `Components/Admin/AdminTable.vue` | Data table with responsive fallback |
| AdminFilterBar | NEW | `Components/Admin/AdminFilterBar.vue` | Filter/search controls |
| AdminEmptyState | ADAPT | reuse `UI/EmptyState.vue` | Dark-adapted |
| AdminBadge | ADAPT | reuse `UI/StatusBadge.vue` | Dark-adapted, extended colors |
| AdminModal | NEW | `Components/Admin/AdminModal.vue` | Modal dialog |
| AdminDrawer | ADAPT | enhance AdminLayout drawer | Focus trap + Escape dismissal |
| AdminPagination | ADAPT | Inertia pagination | Dark-adapted |
| AdminSearch | NEW | `Components/Admin/AdminSearch.vue` | Search input (placeholder until real backend) |
| AdminFormSection | ADAPT | reuse form components | Dark-adapted |

### 15.2 Admin Pages

| Page | Operation | Notes |
|------|-----------|-------|
| `Dashboard.vue` | REPLACE | New widget set (stat cards, action required, collection chart, recent transactions, content stats) |
| Existing CMS/Campaign/Theme/Donation/Payment admin pages | KEEP | Zero backend change; styling adaptation only via new design system components |

### 15.3 Navigation Groups (Sec. 41 final classification)

| Group | Items | Status |
|-------|-------|--------|
| Dashboard | Overview | ACTIVE |
| Fundraising | Programs, Campaigns, Funds | ACTIVE |
| Fundraising | Fundraiser | FUTURE (omit) |
| ZISWAF | Zakat, Infaq, Sedekah, Wakaf, Fidyah, Qurban | FUTURE (disabled/"coming soon") |
| Transactions | Donations, Payments | ACTIVE |
| Transactions | Manual Transfer (folded into Payments) | ACTIVE |
| Transactions | Refund, Reconciliation | FUTURE (omit) |
| Finance | Ledger, Commission, Withdrawal, Distribution | FUTURE (omit entirely) |
| Website | Home, Pages, Page Builder, Menus, Header & Footer, Appearance, Media | ACTIVE |
| Content | Articles, News (classification: FUTURE), Video (FUTURE) | PARTIAL |
| Users | Donors, Fundraisers, Partners, Beneficiaries, Admin Users | FUTURE (omit) |
| Reporting | | FUTURE (omit) |
| Settings | Payment Provider Config | ACTIVE |
| Integrations & Gateways | Payment Provider Config (same as Settings?) | ACTIVE (resolve duplication) |
| Account | Profile, Security, Language (placeholder) | ACTIVE |

### 15.4 Admin V2 Component Consolidation Note

The conceptual design system lists ~20 named primitives above. Implementation MAY consolidate related primitives where cohesion is higher and reuse does not justify separate files. Specifically:

- **Modal + Drawer** may share animation hooks; `AdminModal.vue` and `AdminDrawer.vue` could be implemented as one component (`AdminOverlay`) with mode variants if they share substantial presentation code.
- **AdminSearch** has no real backend yet — it is a styled input (extends existing `UI/Input.vue`), not a new abstraction layer. If a real search backend lands later, it can wrap this input. Until then, no fake route, no fake data, no fake capability.
- **AdminPagination** reuses Inertia's built-in pagination — dark adaptation only, no new implementation needed.
- **AdminFormSection** likely consolidates with existing form field primitives rather than being a standalone wrapper.

These are recommendations, not requirements. The guiding principle: do not create unnecessary component fragmentation when cohesion justifies keeping components together.

---

## 16. Public Experience V2 File Map

### 16.1 Header/Footer

| Component | Operation | Notes |
|-----------|-----------|-------|
| PublicHeader | ADAPT | Extend to support desktop utility bar + enriched nav from siteChrome(); brand-color variable propagation |
| PublicFooter | ADAPT | Institutional footer content (partner logos, trust signals, legal links) |
| New | `PublicShell.vue` extension | Skip-navigation link (NEW requirement, Sec. 34) |

### 16.2 Navigation

| Component | Operation | Notes |
|-----------|-----------|-------|
| New | `MobileBottomNav.vue` | NEW — home/campaign/ziswaf/aktivitas/akun (Sec. 10) |
| New | `DesktopNav.vue` | NEW — full desktop navigation with ZISWAF entries (Sec. 11) |
| New | `ZiswafMenu.vue` | NEW — internal ZISWAF choice sheet/menu when "ZISWAF" selected (Sec. 10) |
| ADAPT | PublicHeader mobile hamburger nav | Replace with MobileBottomNav pattern |

### 16.3 Sticky CTA

| Component | Operation | Notes |
|-----------|-----------|-------|
| New | `StickyDonateCta.vue` | NEW — persistent bottom bar on campaign/ZISWAF/product pages (Sec. 10) |

### 16.4 Journey Pages

| Page | Operation | Notes |
|------|-----------|-------|
| `DonationCreate.vue` | ADAPT | Staged funnel (amount presets + custom → identity → review → submit); same POST endpoint |
| NEW | `DonationAmountStep.vue` | Optional — preset amounts, custom input, currency |
| NEW | `DonationIdentityStep.vue` | Optional — display name, email, anonymous toggle |
| NEW | `DonationReviewStep.vue` | Optional — summary before submit |
| ZISWAF pages | NEW | Placeholder pages: `/zakat-center`, `/infaq`, `/sedekah`, `/wakaf`, `/fidyah`, `/qurban-center` — "coming soon" states |
| Calculator pages | NEW | Zakat Calculator, Fidyah Calculator — wire to server-authoritative services (future IMP-019) |
| NEW | `Home.vue` | Potentially separate from ThemeRender for Page Builder home composition |
| Impact page | NEW | Transparency/impact page (future data source) |
| Donor Services | NEW | FAQ/help page |

---

## 17. Responsive Composition Map

| Component | Mobile | Desktop | Shared Owner |
|-----------|--------|---------|--------------|
| Header | Compact (hamburger) | Utility bar + full nav | `siteChrome()` (PublicRenderer) |
| Footer | Simplified | Full institutional | Same component |
| Bottom Nav | Visible (MobileBottomNav) | Hidden | NEW component |
| Desktop Nav | Hidden | Visible (via PublicHeader) | SAME component, responsive CSS |
| ZISWAF shortcuts | Row in Home (Page Builder) | Row in Home (Page Builder) | SAME Page Builder block |
| Navigation items | Consolidated ZISWAF entry | Distinct top-level entries | `ThemeNavigationItem.visible_desktop/visible_mobile` |
| Admin Sidebar | Drawer (off-canvas) | Fixed left sidebar | AdminLayout.vue mechanism |
| Tables | Card fallback / horizontal scroll | Standard table | NEW AdminTable component |

**Breakpoints:** Tailwind defaults — base (<640) / sm / lg (≥1024)
**Acceptance targets:** 375px mobile, 1280px desktop (per FE-CHK-009 baseline)
**No dedicated tablet layout** — content reflows between mobile/desktop compositions

---

## 18. Permission / Policy Map

### 18.1 Existing Permissions (approx. 50)

| Family | Codes | Owner Policy |
|--------|-------|-------------|
| RBAC | `rbac.role.assign`, `rbac.role.revoke`, `rbac.permission.assign`, `rbac.permission.revoke`, `rbac.authority.assign`, `rbac.authority.revoke`, `rbac.principal.deactivate` | RbacManagementPolicy |
| Identity | `identity.security.transition` | (self-enforced) |
| Audit | `audit.read`, `audit.read.security`, `audit.read.financial_reference` | (self-enforced) |
| Content | `content.view`, `content.create`, `content.update`, `content.publish`, `content.archive`, `content.media.upload` | ContentPagePolicy, ContentArticlePolicy |
| Theme | `theme.view`, `theme.create`, `theme.update`, `theme.publish`, `theme.archive`, `theme.media.upload` | ThemePolicy |
| Program | `program.view`, `program.create`, `program.update`, `program.publish`, `program.archive`, `program.media.upload` | ProgramPolicy |
| Campaign | `campaign.view`, `campaign.create`, `campaign.update`, `campaign.submit`, `campaign.approve`, `campaign.publish`, `campaign.close`, `campaign.media.upload` | CampaignPolicy |
| Fund | `fund.view`, `fund.create`, `fund.update`, `fund.archive` | FundPolicy |
| Donation | `donation.view`, `donation.cancel`, `donation.recurring_plan.manage` | DonationPolicy |
| Payment | `payment.view`, `payment.create`, `payment.cancel`, `payment.manual_transfer.submit_evidence`, `payment.manual_transfer.verify`, `payment.provider_config.manage` | PaymentPolicy |

### 18.2 NEW Permissions Required by CR-001

| Permission | Description | Status |
|-----------|-------------|--------|
| `theme_preview` | Preview an unpublished Draft Site Design (new `ThemePolicy::preview()` method) | NEW — follows canonical Theme permission naming convention (`theme.*`) |
| (Possible) | `page_builder.*` — unlikely; wraps existing `theme.*` permissions | REUSE existing `theme.*` |
| (Future) | `zakat.*`, `infaq.*`, `sedekah.*`, `wakaf.*`, `fidyah.*`, `qurban.*` | FUTURE IMP-019 |
| (Future) | `ledger.*`, `withdrawal.*`, `refund.*`, `distribution.*` | FUTURE IMP-010 |

**No authorization change may be invented silently.** Only `theme_preview` appears necessary within CR-001 (a new `ThemePolicy::preview()` method). Exact final permission code MUST follow the canonical `PermissionRegistry` naming convention: a `THEME_PREVIEW` constant mapped to a string like `'theme.preview'`, following the same pattern as `THEME_VIEW`, `THEME_PUBLISH`, etc. This is NOT a human decision — it extends the established `theme.*` permission family for a distinctly different policy action (read-only preview vs. write/publish).

### 18.3 Policy Methods Needed

| Policy | Existing | NEW |
|--------|----------|-----|
| `ThemePolicy` | view, viewTheme, create, update, publish, archive, uploadAsset, manageAsset, archiveAsset | **preview** (Sec. 30) |
| All others | KEPT AS-IS | No changes — CR-001 does not alter existing authorization surface |

---

## 19. Route Map

### 19.1 Existing — Keep

| Method | Path | Name | Phase |
|--------|------|------|-------|
| GET | `/` | `home` | Existing |
| GET | `/programs/{slug}` | `public.programs.show` | Existing |
| GET | `/campaigns` | `public.campaigns.index` | Existing |
| GET | `/campaigns/{slug}` | `public.campaigns.show` | Existing |
| GET | `/campaigns/{slug}/donate` | `public.donations.create` | Existing |
| POST | `/campaigns/{slug}/donations` | `public.donations.store` | Existing |
| POST | `/donations/{ulid}/payments` | `public.payments.store` | Existing |
| GET | `/donations/{ulid}/payments/create` | `public.payments.create` | Existing (FE-CHK-009) |
| GET | `/donations/{ulid}/payments/{payment}` | `public.payments.show` | Existing (FE-CHK-009) |
| POST | `/donations/{ulid}/payments/{payment}/evidence` | `public.payments.evidence.store` | Existing (FE-CHK-009) |
| GET | `/{any}` | `public.show` | Existing (catch-all) |
| Webhooks | `/webhooks/payments/*` | Existing | Existing |
| Admin routes | `/admin/*` | Existing | Existing |
| Auth routes | Guest/authenticated routes | Existing | Existing |

### 19.2 New — CR-001 Phases

| Phase | Method | Path Pattern | Notes |
|-------|--------|-------------|-------|
| CR-001-C | GET/POST/PATCH | `/admin/site-design/*` | Site Design CRUD endpoints |
| CR-001-C | GET/POST/PATCH | `/admin/page-builder/*` | Page Builder endpoints |
| CR-001-D | GET | `/admin/widgets/dashboard` | Aggregated metrics for dashboard widgets |
| CR-001-G | GET | `/zakat-center/*` | ZISWAF Center IA nodes (placeholder) |
| CR-001-G | GET | `/infaq` | Infaq page (placeholder) |
| CR-001-G | GET | `/sedekah` | Sedekah page (placeholder) |
| CR-001-G | GET | `/wakaf` | Wakaf page (placeholder) |
| CR-001-G | GET | `/fidyah` | Fidyah page (placeholder) |
| CR-001-G | GET | `/qurban-center` | Qurban Center page (placeholder) |
| CR-001-G | POST | `/api/calculators/zakat` | Zakat Calculator computation |
| CR-001-G | POST | `/api/calculators/fidyah` | Fidyah Calculator computation |

### 19.3 Future — Not CR-001

| IMP | Method | Path Pattern | Notes |
|-----|--------|-------------|-------|
| IMP-019 | GET/POST | `/api/zakat/*` | Zakat transaction domain |
| IMP-019 | GET/POST | `/api/infaq/*` | Infaq transaction domain |
| IMP-019 | GET/POST | `/api/sedekah/*` | Sedekah transaction domain |
| IMP-019 | GET/POST | `/api/wakaf/*` | Wakaf transaction domain |
| IMP-019 | GET/POST | `/api/fidyah/*` | Fidyah transaction domain |
| IMP-019 | GET/POST | `/api/qurban/*` | Qurban transaction domain |
| IMP-010 | TBD | Various | Ledger, withdrawal, refund, distribution |

---

## 20. Test Map

### 20.1 By Phase

| Phase | Test Types | Scope |
|-------|-----------|-------|
| CR-001-B | Model factory tests, migration tests | New model schemas, column defaults |
| CR-001-C | Feature tests for Page Builder/Site Design controllers, Policy::preview() negative-path test ("guest cannot preview draft"), block validation tests, render tests for new component types | Admin authoring surfaces |
| CR-001-D | Feature tests for admin widget endpoints, permission-aware nav visibility tests, responsive screenshot evidence (headless browser), accessibility checks (axe-core equivalent) | Admin V2 UX |
| CR-001-E | Feature tests for public chrome, responsive screenshot evidence (375px + 1280px), header/footer rendering, MobileBottomNav behavior, sticky CTA placement | Public shell |
| CR-001-F | Feature tests for donation funnel flow (staged funnel submits same endpoint), PaymentCreate/PaymentShow unchanged behavior, existing DonationHttpTest re-run as regression | Donation journey |
| CR-001-G | Unit tests for calculator services (CalculationInput → CalculationResult), historical-reproducibility test (old snapshot + old policy = same figure after new policy added), IA page "coming soon" content tests | ZISWAF + Calculators |
| CR-001-I | Before/after identical-rendering regression tests for existing Theme/Page/Article content | Legacy verification |
| CR-001-J | Negative-path test: CODEX-FE-CHK-009-01 exploit vector blocked (fresh anonymous session cannot establish possession from idempotent replay) | Security remediation |
| CR-001-K | Full FE-CHK-009-style evidence pass: runtime screenshots for every new surface, automated accessibility, full Feature test suite regression | Evidence/audit |

---

## 21. Proposed Final Implementation Phases

### CR-001-A — Architecture / Contracts — COMPLETE

This CR-001-A document IS phase A. Approved, locked, baseline established.

### CR-001-B — CMS / Site Design Data Model

| What | Action |
|------|--------|
| ThemeNavigationItem | ADD `visible_desktop`, `visible_mobile` columns (nullable BOOLEAN, default TRUE) |
| Optional: User | ADD `name` column (only if display-name path chosen) |
| Optional: CmsArticle | ADD classification field (if News taxonomy adopted) |
| New models | ZakatType, ZakatPolicy, NisabPolicy, GoldPriceReference, ZakatCalculationSnapshot, FidyahPolicy, FidyahCalculationSnapshot, PolicyLeadTimeConfig |
| New migrations | One per new table/column |
| Services | ZakatCalculatorService (stateless), FidyahCalculatorService (same pattern) |
| Value objects | CalculationInput, CalculationResult (PHP) |

**Dependencies:** None. First actionable phase.

### CR-001-C — CMS Admin UX (Page Builder + Site Design)

| What | Action |
|------|--------|
| Controller | SiteDesignController, PageBuilderController (thin wrappers) |
| Vue pages | Admin/SiteDesign/*, Admin/PageBuilder/* |
| Vue components | PageBuilder/BlockList, BlockRow, BlockLibrary, BlockConfigure |
| Policy | Add `preview()` to ThemePolicy |
| Validator | Extend ComponentConfigValidator for new block types |
| PublicRenderer | READ-ONLY — integration point understanding; write modifications owned by CR-001-E |

**Dependencies:** CR-001-B (for new model imports, though Page Builder primarily wraps existing Theme models)

### CR-001-D — Admin Experience V2

| What | Action |
|------|--------|
| Vue components | Decompose AdminLayout.vue into AdminShell, AdminSidebar, AdminSidebarGroup, AdminSidebarItem, AdminTopbar, AdminProfileMenu, AdminBreadcrumb |
| NEW admin primitives | AdminStatCard, AdminActionCard, AdminChartCard, AdminTable, AdminFilterBar, AdminBadge, AdminModal, AdminDrawer, AdminPagination, AdminSearch, AdminFormSection |
| Dashboard | Complete replacement with widget set |
| Navigation | Permission-aware filtering via server-computed `can:` prop |
| Styling | Dark palette throughout |
| Accessibility | Escape-dismissal, focus-trap for drawer, visible focus indicators |

**Dependencies:** Independent after CR-001-C establishes design primitives (colors, component conventions). May run CONCURRENTLY with CR-001-C.

### CR-001-E — Public Responsive Shell

| What | Action |
|------|--------|
| PublicHeader | ADAPT to support desktop utility bar + enriched siteChrome() |
| PublicFooter | ADAPT for institutional content |
| PublicShell | Add skip-navigation link, brand-color CSS variable setup |
| New | MobileBottomNav.vue |
| New | StickyDonateCta.vue |
| PublicRouter | Integrate PublicShell around all public pages (currently only ThemeRender.vue uses it) |

**Dependencies:** CR-001-C (design primitives must be established first)

### CR-001-F — Campaign / Donation UX

| What | Action |
|------|--------|
| DonationCreate.vue | Evolve into staged funnel (single-step or multi-step components) |
| CampaignShow.vue | Enhanced layout with sticky CTA, progress bar, trust indicators |
| ProgramShow.vue | Minor adjustments |
| Integration | Connect to existing `public.donations.store` endpoint (identical contract) |

**Dependencies:** CR-001-E (shell/header/footer must be stable)

### CR-001-G — ZISWAF Public UX + Calculator Foundation

| What | Action |
|------|--------|
| IA pages | `/zakat-center`, `/infaq`, `/sedekah`, `/wakaf`, `/fidyah`, `/qurban-center` (placeholder "coming soon") |
| Calculators | Zakat Calculator, Fidyah Calculator — wired to server-authoritative services |
| Calculator API | `/api/calculators/zakat`, `/api/calculators/fidyah` POST endpoints |
| Integration | ZISWAF entries in desktop nav (visible_desktop), ZISWAF submenu in mobile bottom nav |

**Dependencies:** CR-001-B (calculator models/services must exist first)

### CR-001-H — MERGED INTO CR-001-G

Calculator Foundation and ZISWAF Public UX share the same dependency wave. No separate phase.

### CR-001-I — Legacy/Content Migration Verification

| What | Action |
|------|--------|
| Regression tests | Before/after identical rendering for existing Theme/Page/Article content |
| Validation | All existing seeded themes still render correctly |

**Dependencies:** After CR-001-B/C/E (all data-layer changes applied)

### CR-001-J — Security Remediation

| What | Action |
|------|--------|
| CODEX-FE-CHK-009-01 | Fix guest Donation idempotent replay establishing PaymentCreate possession in fresh anonymous session |
| Reserved files | PublicDonationController, PublicPaymentController, DonationService (DO NOT MODIFY per instructions — fix elsewhere in controller/service boundary) |

**Dependencies:** Explicitly sequenced late (to avoid contending for same Payment/Donation controller files). Has NO technical dependency on other UX phases.

### CR-001-K — Regression / Evidence / Audit

| What | Action |
|------|--------|
| Full evidence pass | Runtime screenshots (375px + 1280px) for all new surfaces |
| Accessibility | Automated + manual verification |
| Regression | Full existing Feature test suite re-run |
| Security | Guest possession, ownership, authorization negative-path tests |

**Dependencies:** After all CR-001-B through J implementations complete.

---

## 22. Phase Dependency Graph

```
CR-001-A (COMPLETE)
    |
    v
CR-001-B ──────────────────────────────┐
    |                                   │
    ├─► CR-001-C (CMS Admin UX)        │ (CR-001-G needs calculator models)
    │       │                          │
    │       ▼                          │
    │   CR-001-D (Admin V2) ◄──────────┘
    │       │           ▲
    │       │           │ runs concurrently with C
    │       ▼           │
    │   CR-001-E (Public Shell)          │
    │       │  PublicRenderer.php owns  │ ← sole write owner
    │       │  all modifications         │
    │       ▼                           │
    │   CR-001-F (Campaign/Donation)     │
    │                                   │
    │   CR-001-G (ZISWAF + Calc) ───────┘
    │       │
    │       v
    │   CR-001-I (Legacy Verification)
    │       │
    │       v (has no deps on UX phases; intentionally late to avoid contention)
    │   CR-001-J (Security Remediation)
    │       │
    │       v (after all implementations)
    │   CR-001-K (Full Evidence + Regression)
    │
    └── CR-001-I also depends on CR-001-B/C/E completed
```

**Parallelism window:** CR-001-C and CR-001-D may run concurrently (disjoint file sets: Admin/SiteDesign/* vs AdminExperience/*).

**File ownership constraint:** `app/Services/Theme/PublicRenderer.php` — single write owner is CR-001-E. CR-001-C reads it only for integration understanding; all render-branch additions for new component types are implemented sequentially in CR-001-E alongside other PublicRenderer modifications.

---

## 23. File Ownership Matrix

### CR-001-B (CMS / Site Design Data Model)

| Category | Files |
|----------|-------|
| PRIMARY | `database/migrations/..._create_zakat_*.php`, `database/migrations/..._create_fidyah_*.php`, `database/migrations/..._add_nav_visibility_to_theme_navigation_items.php`, `app/Models/Zakat/*.php`, `app/Models/Fidyah/*.php`, `app/Models/PolicyLeadTimeConfig.php` |
| MODIFY | `app/Policies/ThemePolicy.php` (+preview), `app/Models/Theme/ThemeNavigationItem.php` (migration only, not model code) |
| CREATE | `app/Services/Zakat/ZakatCalculatorService.php`, `app/Services/Fidyah/FidyahCalculatorService.php`, `app/ValueObjects/Zakat/*.php`, `app/ValueObjects/Fidyah/*.php`, test files |
| READ-ONLY | `app/Services/Theme/PublicRenderer.php` (will be adapted in CR-001-E; CR-001-B reads it only for the integration point) |
| RESERVED FOR OTHER PHASES | None in this phase |

### CR-001-C (CMS Admin UX)

| Category | Files |
|----------|-------|
| PRIMARY | `app/Http/Controllers/Admin/SiteDesignController.php`, `app/Http/Controllers/Admin/PageBuilderController.php`, `resources/js/Pages/Admin/SiteDesign/*.vue`, `resources/js/Pages/Admin/PageBuilder/*.vue` |
| MODIFY | `app/Policies/ThemePolicy.php` (add preview method), `app/Services/Theme/ComponentConfigValidator.php` (add new type schemas) |
| CREATE | PageBuilder Vue components (BlockList, BlockRow, BlockLibrary, BlockConfigure), SiteDesign Vue pages, test files |
| READ-ONLY | `app/Models/Theme/ThemeComponent.php`, `app/Models/Theme/ThemeSection.php`, `app/Models/Theme/ThemeTemplate.php`, `resources/js/Components/Admin/AdminLayout.vue` (will be decomposed in CR-001-D), `app/Services/Theme/PublicRenderer.php` (integration contract; write modifications owned by CR-001-E exclusively) |
| RESERVED FOR OTHER PHASES | `resources/js/Components/Admin/AdminLayout.vue` (owned by CR-001-D) |

### CR-001-D (Admin Experience V2)

| Category | Files |
|----------|-------|
| PRIMARY | `resources/js/Components/Admin/AdminSidebar.vue` (extracted), `resources/js/Components/Admin/AdminTopbar.vue` (extracted), `resources/js/Components/Admin/AdminProfileMenu.vue` (extracted), `resources/js/Components/Admin/AdminBreadcrumb.vue`, `resources/js/Components/Admin/AdminStatCard.vue`, `resources/js/Components/Admin/AdminActionCard.vue`, `resources/js/Components/Admin/AdminChartCard.vue`, `resources/js/Components/Admin/AdminTable.vue`, `resources/js/Components/Admin/AdminFilterBar.vue`, `resources/js/Components/Admin/AdminBadge.vue`, `resources/js/Components/Admin/AdminModal.vue`, `resources/js/Components/Admin/AdminDrawer.vue`, `resources/js/Components/Admin/AdminPagination.vue`, `resources/js/Components/Admin/AdminSearch.vue`, `resources/js/Components/Admin/AdminFormSection.vue`, `resources/js/Pages/Dashboard.vue` (replacement) |
| MODIFY | `resources/js/Components/Admin/AdminLayout.vue` (decomposed into primitives + dark palette), `resources/js/Components/UI/Icon.vue` (new icon entries), `resources/js/Components/UI/*.vue` (dark adaptations), `app/Http/Middleware/HandleInertiaRequests.php` (share auth role info) |
| CREATE | All NEW admin primitive components above, widget API endpoint/controller, test files |
| READ-ONLY | `resources/js/Pages/Admin/Cms/*`, `resources/js/Pages/Admin/Campaign/*`, `resources/js/Pages/Admin/Donation/*`, `resources/js/Pages/Admin/Payment/*` (style unchanged, consume new primitives) |
| RESERVED FOR OTHER PHASES | None |

### CR-001-E (Public Responsive Shell)

| Category | Files |
|----------|-------|
| PRIMARY | `resources/js/Components/Public/MobileBottomNav.vue` (NEW), `resources/js/Components/Public/StickyDonateCta.vue` (NEW), `resources/js/Components/Public/PublicHeader.vue` (ADAPT), `resources/js/Components/Public/PublicFooter.vue` (ADAPT), `resources/js/Components/Public/PublicShell.vue` (ADAPT + skip-nav) |
| MODIFY | `resources/js/app.ts` (route guards for PublicShell composition), `resources/js/Pages/Public/ThemeRender.vue` (ensure Shell usage), `resources/js/Pages/Public/CampaignIndex.vue`, `resources/js/Pages/Public/CampaignShow.vue`, `resources/js/Pages/Public/ProgramShow.vue` (ensure Shell + chrome usage), `app/Services/Theme/PublicRenderer.php` (extend siteChrome() payload richness) |
| CREATE | MobileBottomNav, StickyDonateCta, test files |
| READ-ONLY | Chrome data contract already established in PublicRenderer (write ownership confirmed above) |
| RESERVED FOR OTHER PHASES | None |

### CR-001-F (Campaign / Donation UX)

| Category | Files |
|----------|-------|
| PRIMARY | `resources/js/Pages/Public/DonationCreate.vue` (evolve into staged funnel), `resources/js/Pages/Public/CampaignShow.vue` (enhanced layout) |
| CREATE | Donation step components (AmountStep, IdentityStep, ReviewStep or single evolved file), test files |
| MODIFY | `resources/js/Pages/Public/DonationCreate.vue` — significant structural change |
| READ-ONLY | `app/Http/Controllers/PublicDonationController.php` (endpoint unchanged, same POST contract), `app/Services/Donation/DonationService.php` (DO NOT MODIFY per instructions) |
| RESERVED FOR OTHER PHASES | `app/Http/Controllers/PublicDonationController.php` (reserved for CR-001-J) |

### CR-001-G (ZISWAF Public UX + Calculator Foundation)

| Category | Files |
|----------|-------|
| PRIMARY | `resources/js/Pages/Public/ZakatCenter.vue`, `resources/js/Pages/Public/Infaq.vue`, `resources/js/Pages/Public/Sedekah.vue`, `resources/js/Pages/Public/Wakaf.vue`, `resources/js/Pages/Public/Fidyah.vue`, `resources/js/Pages/Public/QurbanCenter.vue`, `resources/js/Pages/Public/ZakatCalculator.vue`, `resources/js/Pages/Public/FidyahCalculator.vue` (NEW) |
| MODIFY | `routes/web.php` (add IA and calculator routes), `resources/js/Components/Public/PublicHeader.vue` (desktop nav entries), `resources/js/Components/Public/MobileBottomNav.vue` (ZISWAF submenu) |
| CREATE | All ZISWAF placeholder pages, calculator pages, controller endpoints, calculator services (contract), tests |
| READ-ONLY | `app/Services/Theme/PublicRenderer.php` (nav resolution via siteChrome) |
| RESERVED FOR OTHER PHASES | None |

### CR-001-I (Legacy Verification)

| Category | Files |
|----------|-------|
| PRIMARY | Test files asserting before/after identical rendering |
| READ-ONLY | All existing theme/page/article content |

### CR-001-J (Security Remediation)

| Category | Files |
|----------|-------|
| PRIMARY | `app/Http/Controllers/PublicDonationController.php` (possession fix), `app/Http/Controllers/PublicPaymentController.php` (if needed) |
| READ-ONLY | `app/Services/Donation/DonationService.php` (per instructions: DO NOT MODIFY) |
| RESERVED FOR THIS PHASE ONLY | These files are reserved for CR-001-J — no earlier phase touches them |

### CR-001-K (Regression / Evidence / Audit)

| Category | Files |
|----------|-------|
| PRIMARY | Comprehensive test suite re-run, evidence captures |
| READ-ONLY | All implemented files across all phases |

---

## 24. Minimum Sufficient Context Per Phase

Each phase's Muse implementation receives ONLY these inputs — NOT the full repository:

### Phase B

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 16 (Zakat architecture), Sec. 20 (Fidyah architecture), Sec. 55 (migration strategy), Sec. 63 (lead-time config), Sec. 64 (money semantics) |
| RECON SECTIONS | Sec. 3 (CMS map), Sec. 4 (Theme engine map), Sec. 8 (calculator current-state) |
| FILES REQUIRED | `app/Models/Theme/ThemeNavigationItem.php`, `app/Policies/ThemePolicy.php`, relevant migration files (Sec. 12.2), `app/Support/Money/Money.php`, `app/Support/Money/CurrencyMinorUnits.php` |
| TESTS REQUIRED | Factory patterns from existing seeders; PHPUnit test structure from existing Feature tests |
| LOCKED DECISIONS | Money discipline (amount_minor, never float), no Ledger tables, no IMP-019 business rules, HD-CR001-03 (lead-time configurable, value not invented) |

### Phase C

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 25–30 (Site Design, Page Builder, Block Library, Security) |
| RECON SECTIONS | Sec. 3 (CMS map), Sec. 4 (Theme engine map) |
| FILES REQUIRED | `app/Models/Theme/ThemeComponent.php`, `app/Services/Theme/ComponentConfigValidator.php`, `app/Policies/ThemePolicy.php`, existing `ThemeController` for pattern reference, existing admin Vue pages for style reference |
| TESTS REQUIRED | Existing PHPUnit structure from Feature tests |
| LOCKED DECISIONS | Existing Theme/Template/Section/Component schema kept, wrap-not-replace principle, advanced view retained (HD-CR001-05), XSS protection for v-html, preview requires authorized ThemePolicy |

### Phase D

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 37–45, 49–51 (Admin V2 requirements) |
| RECON SECTIONS | Sec. 6 (Admin map) |
| FILES REQUIRED | `resources/js/Components/Admin/AdminLayout.vue` (source for decomposition), `resources/js/Components/UI/*.vue` (existing primitives), `app/Http/Middleware/HandleInertiaRequests.php`, existing admin Vue pages |
| TESTS REQUIRED | Existing Feature test structure |
| LOCKED DECISIONS | Dark-only (HD-CR001-07), Admin not theme-controlled, hidden menu != denial, permission-aware nav uses server-computed `can:` prop, never fabricate metrics |

### Phase E

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 9–11 (Mobile/Desktop UX), Sec. 31–33 (Navigation, Header/Footer, Responsive), Sec. 34 (Accessibility) |
| RECON SECTIONS | Sec. 5 (Public frontend map) |
| FILES REQUIRED | `resources/js/Components/Public/PublicShell.vue`, `resources/js/Components/Public/PublicHeader.vue`, `resources/js/Components/Public/PublicFooter.vue`, `app/Services/Theme/PublicRenderer.php` (siteChrome contract), `resources/js/Pages/Public/ThemeRender.vue` |
| TESTS REQUIRED | Existing Feature test structure |
| LOCKED DECISIONS | Shared-hosting compatible, no new infrastructure, skip-nav link required, keyboard-operable nav, reduced-motion respected, ≥44px touch targets |

### Phase F

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 13–14 (Campaign UX, Donation UX) |
| RECON SECTIONS | Sec. 5 (Public frontend map), Sec. 9 (Payment boundary) |
| FILES REQUIRED | `resources/js/Pages/Public/DonationCreate.vue`, `resources/js/Pages/Public/CampaignShow.vue`, `app/Http/Controllers/PublicDonationController.php` (endpoint reference, do not modify), `app/Services/Donation/DonationService.php` (DO NOT MODIFY per instructions) |
| TESTS REQUIRED | Existing `tests/Feature/Donation/DonationHttpTest.php` (regression) |
| LOCKED DECISIONS | Donation ≠ Payment, staged funnel = client-side only, SAME POST endpoint/contract, idempotency discipline preserved, CODEX-FE-CHK-009-01 NOT fixed here |

### Phase G

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 12 (IA), Sec. 15–21 (ZISWAF UX, Calculators), Sec. 22 (Payment Hub boundary) |
| RECON SECTIONS | Sec. 7 (ZISWAF current-state), Sec. 8 (Calculator current-state) |
| FILES REQUIRED | Phase B outputs (calculator models/services), `routes/web.php`, `resources/js/Components/Public/PublicHeader.vue`, existing Public pages for style reference |
| TESTS REQUIRED | Unit tests for calculator services, IA page "coming soon" content tests |
| LOCKED DECISIONS | No religious/legal formula invented, server-authoritative calc, versioned policy immutable, Payment Hub boundary preserved, ZISWAF = IA/presentation only until IMP-019 |

### Phase I

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 55–57 (Migration safety, Compatibility, Content migration) |
| RECON SECTIONS | Sec. 4 (Theme engine), Sec. 3 (CMS) |
| FILES REQUIRED | All migrated files from B/C/E |
| TESTS REQUIRED | Before/after rendering comparison tests |
| LOCKED DECISIONS | No destructive migration, no content loss, identical rendering preserved |

### Phase J

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 54 (CODEX-FE-CHK-009-01 statement), Sec. 22 (Payment Hub boundary) |
| RECON SECTIONS | Sec. 9 (Payment integration map), Sec. 10 (Reserved files) |
| FILES REQUIRED | `app/Http/Controllers/PublicDonationController.php`, `app/Http/Controllers/PublicPaymentController.php` |
| TESTS REQUIRED | Negative-path: idempotent replay in fresh session does NOT establish possession |
| LOCKED DECISIONS | IDEMPOTENCY ≠ AUTHORIZATION, guest possession mechanism fix scoped exactly to identified flaw, DonationService DO NOT MODIFY |

### Phase K

| Input | Contents |
|-------|----------|
| SPEC SECTIONS | Sec. 67 (Frontend evidence), Sec. 68 (Testing strategy) |
| RECON SECTIONS | Full recon document |
| FILES REQUIRED | All implemented files across all phases |
| TESTS REQUIRED | Full Feature test regression, runtime evidence capture |
| LOCKED DECISIONS | Static class inspection NOT sufficient as evidence, runtime browser verification mandatory |

---

## 25. Compatibility / Deprecation Map

### 25.1 Advanced Theme View (HD-CR001-05)

| Element | Status | Removal Condition |
|---------|--------|-------------------|
| `ThemeController` raw CRUD endpoints | RETAINED | Later governed decision based on operator adoption data |
| Technical vocabulary exposure (Template/Section/Component) | RETAINED in "Advanced" labeled view | Permanent removal NOT committed to in V2 |
| Normal operator path | Page Builder V2 (clean vocabulary) | Becomes dominant interface |

### 25.2 Legacy Presentation

| Element | Status | Notes |
|---------|--------|-------|
| Existing Theme/Template/Section/Component content | KEPT AS-IS | Mapped transparently to Page Builder blocks |
| Existing `PublicRenderer` rendering algorithm | KEPT AS-IS | Only `siteChrome()` extended; core algorithm untouched |
| Existing CMS Pages/Articles | KEPT AS-IS | New operators see same content |
| Existing AdminLayout structure | ADAPTED (not replaced) | Shell/decomposition preserves behavior |

### 25.3 No Legacy Code Being Removed

Per Sec. 56: "No dual-read/adapter/compatibility-renderer layer is required." Every change is additive — new columns, new component types, new wrapping UI. Zero legacy code retirement scheduled.

---

## 26. Migration Safety Plan

| Principle | Application |
|-----------|-------------|
| Additive first | Every column is NULLABLE with a sensible default |
| Backfill | Where existing rows need non-null values, migration provides defaults (visible_desktop/visible_mobile = TRUE) |
| Dual-read/adapter | NOT required — no existing read path is replaced |
| Verification | CR-001-I regression tests assert identical before/after rendering |
| Switch canonical read | When Page Builder becomes canonical, PublicRenderer switches to reading from new block data — old block data remains harmless |
| Deprecate legacy presentation | Site Design v1 admin UI (raw ThemeController) continues accessible via "Advanced" view |
| No destructive migration | NEVER `DROP COLUMN`, NEVER `DROP TABLE`, NEVER delete data |
| Rollback safe | All migrations are forward-only with proper `down()` methods; no irreversible operations |

### Schema Change Order

Definite first, conditional when adopted:
1. `theme_navigation_items` visibility columns (`visible_desktop`, `visible_mobile`) (CR-001-B) — may be implemented in one or two migration files
2. New model tables: `zakat_types`, `zakat_policies`, `nisab_policies`, `gold_price_references`, `zakat_calculation_snapshots`, `fidyah_policies`, `fidyah_calculation_snapshots`, `policy_lead_time_configs` (CR-001-G) — may be spread across multiple migration files or consolidated where appropriate
3. Conditional `users.name` column (CR-001-B, only if display-name path adopted)
4. Conditional article classification column (CR-001-B, only if News taxonomy adopted)

Note: The exact number of Laravel migration FILES is NOT YET DETERMINED. Each numbered item above represents a schema change group that may map to one or more migration files depending on implementation choices.

---

## 27. Security Boundary Check

| Concern | Status | Notes |
|---------|--------|-------|
| RBAC integrity | PRESERVED | All new admin endpoints use existing RBAC; new `ThemePolicy::preview()` follows same AuthorizesUsingRbac pattern |
| Scope | PRESERVED | No new scope violations; new reads follow existing ORGANIZATION scope pattern |
| Business authority | PRESERVED | Financial Authority never implied by navigation visibility (Sec. 42); FINANCE nav group omitted |
| Owner control | PRESERVED | Existing donation/payment ownership unchanged |
| Guest possession | SECURED LATER | CODEX-FE-CHK-009-01 scoped to CR-001-J; no phase weakens it before then |
| Payment Hub boundary | PRESERVED | Sec. 22 restated for all ZISWAF products; all terminate in same canonical Payment initiation |
| Audit trail | EXTENDED | New auditable events classified under existing IMP-004 criticality model (Sec. 62) |
| Private media | PRESERVED | Manual Transfer evidence files remain private; new Media Manager extensions respect existing MediaPolicy |
| Page Builder XSS | PRESERVED | v-html content passes through existing sanitization; Safe Custom Content governed same way |
| Preview/draft auth | NEW SURFACE | `ThemePolicy::preview()` explicitly gates draft Site Design preview (Sec. 30) |
| Admin financial authority | PRESERVED | Super Admin != automatic Financial Authority; FINANCE nav group omitted until IMP-010 |
| URL unsafe redirects | PRESERVED | NavigationDestinationResolver closed enum enforced for all CTA destinations |
| No arbitrary JS in block config | PRESERVED | No executable JavaScript stored in component config JSON |

---

## 28. Shared Hosting Check

| Requirement | Status | Evidence |
|-------------|--------|----------|
| No Redis | PASS | All caching uses Laravel cache store (file/array driver acceptable) |
| No Supervisor/PM2 | PASS | No background workers required |
| No WebSocket | PASS | No real-time features |
| No Docker | PASS | Traditional Apache/LiteSpeed + PHP + MySQL deployment |
| Cron only | PASS | Scheduled transitions already use Laravel's scheduler (cron-based) |
| Compiled assets | PASS | Vite build → compiled JS/CSS (existing FE-CHK-009 pattern) |
| PHP 8.3 + MySQL | PASS | Already running |
| New image variants | SAFE | On-upload resize using PHP GD/Imagick (already available) — no external service |

**Conclusion:** Zero phases require mandatory infrastructure beyond PHP + MySQL + Cron + compiled frontend assets.

---

## 29. Risks

| Risk | Severity | Likelihood | Mitigation |
|------|----------|------------|------------|
| AdminLayout.vue decomposition breaking existing admin pages | HIGH | Medium | Phase-gated: decompose, test each sub-component individually, regression test existing pages |
| Staged donation funnel introducing contract drift | HIGH | Low | STAGED FUNNEL = CLIENT-SIDE ONLY; final POST is IDENTICAL to current single-page submission |
| New component types confusing existing PublicRenderer | MEDIUM | Low | Each new type wrapped in existing try/catch degrade-to-null pattern |
| Permission-aware navigation showing wrong items | MEDIUM | Medium | Server-computed `can:` prop (never client-side authorization derivation); route gate remains sole authority |
| CODEX-FE-CHK-009-01 fix delayed by phase sequencing | HIGH | LOW | Intentionally sequenced late ONLY to avoid contention for same controller files |
| Gallery of new admin primitives becoming too granular | MEDIUM | Medium | Recommend consolidation where appropriate (Sec. 15.1): group related primitives (e.g., modal+drawer may share animation hooks) |
| Operator confusion between Site Design v1 (advanced) and v2 (Page Builder) | LOW | Medium | Clear labeling, hierarchical navigation grouping |
| Calculator complexity exceeding Phase G scope | HIGH | Medium | Phase G implements ARCHITECTURE CONTRACT + simple working calculator; complex formulas deferred to IMP-019 |
| Responsive screenshot tooling unreliable (known from FE-CHK-009 experience) | MEDIUM | High | Document alternative approaches: DOM inspection, headless browser automation |
| Silent scope creep into ZISWAF business rules | HIGH | Medium | Strict "IA/presentation only" discipline; placeholder pages clearly marked |

---

## 30. Recon Findings

### Critical Findings

1. **AdminLayout.vue is the single largest shared mutable file.** Both CR-001-C (wrapping/admin UX) and CR-001-D (decomposition) need to reason about it. CR-001-D takes full ownership; CR-001-C reads it only for pattern reference.

2. **FE-CHK-009 changed the Chrome pattern surface.** `PublicRenderer::siteChrome()` was added by FE-CHK-009; CR-001-E extends it. The contract is: returns `{branding, navigation}`. CR-001-E enriches to add more branding/data.

3. **The curated Icon.vue registry has ~27 icons.** CR-001 phases will extend this with many more Lucide icons (dashboard variants, colored semantic icons per Sec. 43). Pattern is: import from `@lucide/vue`, add to registry object. No library change.

4. **v-html is used in 4 locations across the codebase.** All receive server-sanitized HTML. This CR-001 does not introduce new v-html surfaces, but Page Builder "Safe Custom Content" will — it MUST go through the same sanitization.

5. **The dashboard placeholder explicitly documents the "never fabricate" discipline.** This exact comment exists in the current `/dashboard` route closure. CR-001-D preserves this discipline for all new metrics.

6. **Existing 9 component types** (not 8 — `navigation_menu_slot` makes 9) are all accounted for in `PublicRenderer::renderComponent()`. New types are ADDITIVE only.

7. **Zero ZISWAF domain models exist.** All ZISWAF IA nodes are pure presentation routing. No business rules, no routes, no controllers, no views (other than placeholders).

### Positive Findings

1. **Theme engine data model is structurally sound.** No model replacement needed — everything is KEEP or ADAPT.
2. **siteChrome() is the perfect seam** for CR-001's public chrome (header/footer/nav).
3. **AdminLayout.vue's collapse mechanism is sound.** Only restyling + minor enhancements needed.
4. **FE-CHK-009's PublicShell/Header/Footer components** provide exactly the seam CR-001-E needs.
5. **PermissionRegistry is centralized.** Adding new permission codes is mechanical (constant + definition).
6. **ULID route-model binding** means all URL identifiers are opaque and secure.
7. **Money discipline is consistently applied.** `amount_minor` + `CurrencyMinorUnits` everywhere.

---

## 31. Human Decisions Required

Based on repository evidence scan: **0 new Human Decisions required.**

All architectural questions raised by CR-001-A were resolved in HD-CR001-01 through HD-CR001-07 (Section 76 of the architecture document). The re-scan in Sec. 76.3 confirmed zero new unavoidable decisions emerged from materializing those resolutions.

If during future phase specs (Claude producing CR-001-B through K specifications) genuine unresolved decisions surface, they will be recorded as HD-CR001-08+.

---

## 32. Recommended Next Step

Per CR-001-A Sec. 82:

1. **Human reviews and approves the phasing in Section 21** of this document (adjusted from original Sec. 72 of the architecture doc based on actual dependency evidence).

2. **Commence with CR-001-B** (CMS / Site Design Data Model) and/or **CR-001-D** (Admin Experience V2) as recommended — both are lowest-risk, most evidence-grounded starting points with ADAPT-classified existing models and zero destructive migration. These two may run CONCURRENTLY (disjoint file sets).

3. **FE-CHK-009's remaining evidence gaps and CODEX-FE-CHK-009-01** remain tracked for resolution at CR-001-J/K — neither abandoned by this pause, both resequenced.

4. **IMP-010 remains NOT STARTED / BLOCKED** until CR-001 completes to the point where its predecessor constraint lifts.

---

## Final Report

```
MODEL:
qwen/qwen3.7-flash

MODEL ROUTING:
HUMAN-VERIFIED IN COMMAND CODE

TASK:
CR-001 GOVERNED IMPLEMENTATION RECON

ARCHITECTURE BASELINE:
cc8de3341f31451b93877f20a4299609d5b5e7e5

ARCHITECTURE GATE:
APPROVED

RECON ARTIFACT:
docs/ai-handoff/CR-001/RECON.md

RECON COMPLETED:
YES

CR-001-A:
COMPLETE

PROPOSED IMPLEMENTATION PHASES:
CR-001-A Architecture/Contracts (COMPLETE)
CR-001-B CMS/Site Design Data Model
CR-001-C CMS Admin UX (Page Builder + Site Design UI)
CR-001-D Admin Experience V2 (dark shell, grouped nav, dashboard, design system)
CR-001-E Public Responsive Shell (Header/Footer/MobileBottomNav/StickyCTA)
CR-001-F Campaign/Donation UX (staged funnel)
CR-001-G ZISWAF Public UX + Calculator Foundation
CR-001-H (MERGED into CR-001-G)
CR-001-I Legacy/Content Migration Verification
CR-001-J Security Remediation (including CODEX-FE-CHK-009-01)
CR-001-K Regression/Evidence/Audit

FE-CHK-009 DIRTY FILES IDENTIFIED:
24 (16 modified + 8 untracked)

FE-CHK-009 COLLISIONS IDENTIFIED:
17 files (collision YES)

CODEX-FE-CHK-009-01:
OPEN / VALID / UNRESOLVED

CODEX-FE-CHK-009-01 RESERVED FOR:
CR-001-J

CMS CURRENT STATE:
Fully functional IMP-005 CMS: CmsPage, CmsArticle, CmsContentRevision, CmsMediaAsset, CmsMediaReference, CmsPath, CmsHomepageAssignment. Six controllers (Page, Article, Media, Homepage), four prefix groups under /admin/content/. Six Vue admin pages. All KEEP. Two ADAPT needs: article classification migration (news taxonomy), video reference model. No editorial workflow (Q29 locked). No multilingual (Q31 locked).

THEME ENGINE CURRENT STATE:
Mature IMP-006 Theme Engine: Theme, ThemeActivation (singleton), ThemeTemplate, ThemeSection, ThemeComponent (9 types), ThemeBrandingConfig, ThemeAsset, ThemeNavigationMenu, ThemeNavigationItem. Full service layer (15 services). Single ThemeController admin CRUD. PublicRenderer with renderForContentKind + siteChrome(). AdminVue: Index + Show. Fully KEEP with one ADAPT: ThemeNavigationItem visibility columns. Perfect structural foundation for Site Design/Page Builder wrapping.

ADMIN CURRENT STATE:
Single AdminLayout.vue: light theme, collapsible sidebar (localStorage-persisted), mobile off-canvas drawer (Escape key only, no focus trap), topbar with profile menu (email + logout), static navGroups (6 groups, no permission filtering). Inline /dashboard route with six raw count()s. Twelve UI component primitives. Full admin coverage for CMS/Campaign/Donation/Payment/Presentation. Heavy ADAPT needed: dark palette, decomposition, permission-aware nav, dashboard replacement, enhanced drawer.

PUBLIC FRONTEND CURRENT STATE:
Six public Vue pages (ThemeRender, CampaignIndex, CampaignShow, ProgramShow, DonationCreate, NotFound) + two FE-CHK-009 new pages (PaymentCreate, PaymentShow). Three FE-CHK-009 public components (PublicShell, PublicHeader, PublicFooter). DonationCreate is single flat form (needs staged funnel). No mobile bottom nav, no sticky CTA, no skip-nav, no enriched chrome. siteChrome() contract established.

ZISWAF CURRENT STATE:
ALL NOT IMPLEMENTED. Zero Zakat/Infaq/Sedekah/Wakaf/Fidyah/Qurban models, controllers, routes, or views. Wakaf partially exists as standard Donation product (Campaign product_type). No calculators. No calculator infrastructure. All future IMP-019.

CALCULATOR CURRENT_STATE:
NOT IMPLEMENTED. Money discipline exists (App\Support\Money\Money, CurrencyMinorUnits) — REUSE. No policy/version/effective-date/lead-time patterns. No CalculationInput/result/snapshot value objects.

DATABASE CHANGES EVENTUALLY REQUIRED:
12 total schema items (10 definite + 2 conditional). All additive. Exact migration file count NOT YET DETERMINED — multiple compatible schema changes may be consolidated in single Laravel migration files. No ZISWAF transactional tables — those are IMP-019 ownership.

NEW PERMISSIONS REQUIRED:
1 (theme_preview → new `ThemePolicy::preview()` method; follows canonical `theme.*` PermissionRegistry naming convention)

HUMAN DECISIONS REQUIRED:
0

LIST HUMAN DECISIONS:
NONE

APPLICATION FILES MODIFIED:
NONE (this recon created only docs/ai-handoff/CR-001/RECON.md)

TEST FILES MODIFIED:
NONE

MIGRATIONS CREATED:
NONE

DATABASE MODIFIED:
NO

GIT DIFF CHECK:
PASS (no diff performed — only one file created: RECON.md)

COMMIT:
NOT PERFORMED

PUSH:
NOT PERFORMED

MERGE:
NOT PERFORMED

FE-CHK-009:
PAUSED (untouched, 24 dirty files preserved)

CR-001 IMPLEMENTATION:
NOT STARTED

IMP-010:
NOT STARTED / BLOCKED

FINAL VERDICT:
CR-001 RECON READY FOR HUMAN REVIEW
```
