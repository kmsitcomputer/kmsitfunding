# CR-001 — Public Experience, Visual CMS, ZISWAF UX & Admin Experience Architecture V2

## Status

`ARCHITECTURE / RECON / SPECIFICATION — HUMAN ARCHITECTURE GATE: APPROVED
("Saya APPROVE CR-001 Architecture Final Candidate.") — CR-001 ARCHITECTURE BASELINE
LOCKED (no implementation performed)`

```
CR:            CR-001
Precondition:  Occurs BEFORE IMP-010. IMP-010 MUST NOT start while CR-001 is unresolved.
Scope:         Architecture, recon, UX/IA, data-model impact, migration planning, spec only.
Not in scope:  Application code, migrations, seeders, DB mutation, frontend/backend
               implementation, CODEX-FE009-01 remediation, commit, push, merge, IMP-010.
```

## Human Architecture Gate

```
HUMAN ARCHITECTURE GATE:  APPROVED
Approved by:               Human
Approval statement:        "Saya APPROVE CR-001 Architecture Final Candidate."
Approval basis:             Explicit Human approval after all seven Human Decisions
                            (HD-CR001-01 through HD-CR001-07 — Section 76) were resolved
                            and the full architecture consistency review (Q29/Q31/Payment
                            Hub/RBAC/Admin Financial Authority/shared-hosting boundaries)
                            passed.
HD-CR001-01..07:            ALL APPROVED (Section 76)
OPEN HUMAN DECISIONS:       0 (Section 76.3)
Status:                     CR-001 ARCHITECTURE BASELINE LOCKED
Next authorized stage:      Qwen governed implementation recon (per-phase, Section 72/73)
                            — NOT run by this document; implementation itself remains
                            NOT AUTHORIZED until its own phase's Human Spec Approval.
```

## 1. Executive Summary

The Human is not satisfied with the operator/admin usability of the current Theme/
Template/Section/Component CMS, and wants the public experience redesigned around
proven ZISWAF (Zakat-Infaq-Sedekah-Wakaf) philanthropy UX patterns (mobile inspired by
Kitabisa; desktop inspired by Dompet Dhuafa, with secondary patterns from Rumah Zakat
and BAZNAS), a dramatically simplified "Site Design" CMS for non-technical operators,
and a modern dark fixed Admin backoffice inspired by a KMSIT Computer dashboard
reference. This document is the CR-001 architecture: it maps the current repository
reality, proposes a target architecture, classifies every relevant existing model/file,
plans a non-destructive migration path, and lists every Human Decision this scope
genuinely requires. It implements nothing. IMP-005 through IMP-009 remain FINAL/LOCKED;
this CR proposes presentation-layer and CMS-authoring evolution on top of them, plus new
governed ZISWAF calculator/product architecture, without reopening their business
semantics.

## 2. Human Change Request

Restated from the assignment (Section 0 of the task): a major presentation/CMS UX
redesign is requested before IMP-010, covering (1) mobile public UX, (2) desktop public
UX, (3) ZISWAF transaction UX, (4) a dramatically simplified CMS, (5) a fixed dark
Admin/Super Admin backoffice, (6) a governed Zakat Calculator, (7) a planned Fidyah
Calculator, and (8) a dedicated Qurban product experience. The current Theme Engine is
technically valid (IMP-006, FINAL/LOCKED) but is judged too technical for non-technical
operators.

## 3. Current Governance State

```
TAHAP 0-19:                    LOCKED (architecture program)
IMP-001..IMP-009:              FINAL / LOCKED
FE-CHK-009:                    PAUSED BY HUMAN CHANGE REQUEST (this CR)
CODEX-FE009-01:                OPEN / VALID / UNRESOLVED (Section 54 — not touched here)
IMP-010:                       NOT STARTED (remains not started after this task)
Source authority order:        Human Decisions > Master Requirements > Locked Architecture
                                > Approved ADR > Implementation Specifications >
                                Governance Instructions > Code/Tests (unchanged)
```

No locked decision is silently changed anywhere in this document. Every place this CR's
direction brushes against a locked decision (Q29 no editorial workflow, Q31 single
locale, Theme Engine authority, Super Admin != automatic Financial Authority, Wakaf as a
standard donation product, Payment Hub boundary) is called out explicitly, either as
preserved-as-is or as a Human Decision — never a silent override. All seven Human
Decisions this document originally raised (HD-CR001-01 through HD-CR001-07) are now
APPROVED — see Section 76 for the full resolution record.

## 4. Repository Recon

Performed directly against the working tree at HEAD `e82c89c21283bcc363fbac9a6612065839bcef93`
(the same dirty FE-CHK-009 implementation state already on disk, untouched by this task).

### 4.1 Stack

```
Backend:    Laravel ^13.17, PHP 8.3, MySQL (shared-hosting: Apache/LiteSpeed + PHP + MySQL + Cron)
Frontend:   Vue ^3.5, Inertia (inertiajs/inertia-laravel ^3.3, @inertiajs/vue3 ^3.7), TypeScript
CSS:        Tailwind ^4.0
Build:      Vite ^8.0
Icons:      @lucide/vue ^1.46 (already the approved icon system — confirms Section 43's
            "use the repository's approved icon system" instruction: it is Lucide)
```

### 4.2 CMS models (`app/Models/Cms/`)

```
CmsPage.php               — static page content (IMP-005)
CmsArticle.php             — article/news content (IMP-005)
CmsPath.php                 — canonical current URL path per content owner (page/article)
CmsMediaAsset.php           — media library asset record
CmsMediaReference.php       — usage-tracking join (which content references which asset)
CmsContentRevision.php      — content revision history
CmsHomepageAssignment.php  — the "which PublishedContent, if any, is the homepage" pointer
                              (singleton-style row, mirrors ThemeActivation's own pattern)
```

### 4.3 Theme Engine models (`app/Models/Theme/`)

```
Theme.php                  — a named presentation configuration (status ACTIVE/ARCHIVED,
                              is_system_default flag)
ThemeActivation.php        — singleton "current active Theme" pointer (id=1, CHECK id=1)
ThemeTemplate.php           — one row per (theme_id, content_kind) — content_kind is
                              'home' | 'page' | 'article' in the current seeder
ThemeSection.php             — belongs to theme_id (NOT template_id) directly;
                              theme_template_sections is the join/ordering table
ThemeComponent.php          — belongs to theme_section_id; typed (`hero`, `rich_text`,
                              `image`, `cta_button`, `content_list`, `stats`, `banner`,
                              `card_grid`, `navigation_menu_slot`), JSON `config`
ThemeBrandingConfig.php     — one-per-theme; color_tokens (JSON), font_family,
                              logo_theme_asset_id / favicon_theme_asset_id (FK to
                              ThemeAsset — NOT a plain URL column)
ThemeAsset.php               — uploaded theme media (logo/favicon/etc.), resolved to a
                              public URL via a token resolver service
ThemeNavigationMenu.php    — one-per-(theme_id, code) named menu (e.g. "primary")
ThemeNavigationItem.php    — tree (parent_id self-FK), polymorphic destination
                              (`destination_type`: SYSTEM_ROUTE | CMS_CONTENT |
                              EXTERNAL_URL), resolved to a real URL at render time by
                              `NavigationDestinationResolver` (never a stored raw URL for
                              internal destinations — this is the correct, safe pattern;
                              Section 31 must preserve this resolution model even while
                              hiding its vocabulary from operators)
```

### 4.4 Rendering pipeline

```
PublicContentController::home()/show()
  -> HomepageContentResolver (home: CmsHomepageAssignment lookup; show: CmsPath lookup)
  -> PublicRenderer::renderForContentKind($contentKind, $content)
       -> resolves active Theme (ThemeActivation), falls back to the system-default
          Theme if the active theme has no template for that content_kind
       -> resolves ONE ThemeTemplate for (theme, content_kind)
       -> buildSections(): walks theme_template_sections -> ThemeSection -> ThemeComponent,
          renders each component type to a props array (renderHero/renderRichText/
          renderImage/renderCtaButton/renderContentList/renderBanner/renderCardGrid/
          renderNavigationMenuSlot — each wrapped in try/catch, a failed component
          degrades to "absent", never a fatal error, per IMP-006 section 22)
       -> buildBranding(): resolves color_tokens/font_family/logo_url/favicon_url
  -> Inertia::render('Public/ThemeRender', ['content' => ..., 'template' => ...])
```

`PublicRenderer::siteChrome($theme, $contentKind = 'home')` (added under FE-CHK-009,
DEEPSEEK-FE009-03) is the one existing precedent for a *simplified*, cross-page
projection of Theme data (branding + primary navigation only) — this is directly
reusable as the seam CR-001's simplified "Site Design" reads from.

### 4.5 Frontend components/pages already present

```
resources/js/Pages/Public/ThemeRender.vue     — full Theme/Template/Section/Component
                                                  renderer; the canonical Home page
resources/js/Pages/Public/CampaignIndex.vue   — campaign listing
resources/js/Pages/Public/CampaignShow.vue    — campaign detail (has a real "Donate"
                                                  link since FE-CHK-009, gated on
                                                  is_donation_eligible)
resources/js/Pages/Public/ProgramShow.vue     — program detail
resources/js/Pages/Public/DonationCreate.vue  — bare donation form (amount/currency/
                                                  display name/guest name/guest email/
                                                  anonymous checkbox) — a single-step,
                                                  non-Kitabisa-style form today
resources/js/Pages/Public/PaymentCreate.vue   — provider selector (manual_transfer/
                                                  tripay/xendit/stripe), FE-CHK-009 new
resources/js/Pages/Public/PaymentShow.vue     — payment status/instructions/evidence
                                                  upload, FE-CHK-009 new
resources/js/Components/Public/PublicHeader.vue,
resources/js/Components/Public/PublicFooter.vue,
resources/js/Components/Public/PublicShell.vue — FE-CHK-009 new, generic composition,
                                                  theme-data-driven only (no hard-coded
                                                  nav/branding) — directly reusable as
                                                  the seam for a V2 Header/Footer
resources/js/Components/Admin/AdminLayout.vue — the ONE existing admin shell: fixed
                                                  light sidebar (Overview/Content/
                                                  Fundraising/Presentation groups,
                                                  FE-CHK-009 adds Donations/Payments),
                                                  collapsible (icon-only) desktop
                                                  sidebar with a `localStorage`-persisted
                                                  collapse preference, mobile off-canvas
                                                  drawer, topbar with a profile menu
                                                  (email + logout only — no avatar/role
                                                  badge/breadcrumb today)
resources/js/Components/UI/Icon.vue           — a curated ~22-icon subset of Lucide,
                                                  referenced by name (not the full
                                                  library imported ad hoc) — this is the
                                                  correct pattern to keep extending, not
                                                  replace
```

### 4.6 Admin controllers/routes actually present

```
Cms\PageController, Cms\ArticleController, Cms\MediaController, Cms\HomepageController
Theme\ThemeController              (templates/sections/components/navigation/branding/assets)
Campaign\ProgramController, Campaign\CampaignController, Campaign\FundController
Donation\AdminDonationController, Donation\DashboardDonationController
Admin\AdminPaymentController, Admin\AdminPaymentProviderConfigController
DashboardPaymentController
```

Plus the inline `/dashboard` route in `routes/web.php`: a placeholder Inertia page
(`Dashboard`) with six raw `count()`s (pages, articles, programs, campaigns, funds,
themes) — explicitly commented "IMP-008+ metrics (donations, donors, payments, ledger)
do not exist and are never fabricated here." This is the exact gap Section 46 of this CR
must close, and it already documents the correct discipline (never fabricate a metric)
to preserve.

### 4.7 Authorization surface

```
Policies present: CampaignPolicy, ContentArticlePolicy, ContentPagePolicy,
  DonationPolicy, FundPolicy, MediaPolicy, PaymentPolicy, ProgramPolicy,
  RbacManagementPolicy, RecurringPlanPolicy, ThemePolicy
  (+ AuthorizesUsingRbac concern all of them share)
PermissionRegistry: ~50 registered permission constants (confirmed count from source)
No existing policy for: Fundraiser, Zakat, Infaq, Sedekah, Wakaf, Fidyah, Qurban,
  Ledger, Withdrawal, Refund, Distribution, Commission — all future-IMP-owned, none
  invented here.
```

### 4.8 Locked decisions directly relevant to this CR (verified by citation, not memory)

```
Q29 — docs/implementation/IMP-005-cms.md: "Editorial review/approval workflow — OUT
      — Q29 (permission separation is the baseline)" / "no editorial approval workflow
      in v1" — LOCKED. A Draft/Preview/Publish flow for Site Design/Page Builder does
      NOT become a disguised editorial *approval* workflow — HD-CR001-02 (APPROVED,
      Option A) confirms single-actor publish, consistent with Q29.
Q31 — same file: "Multilingual content — OUT — Q31 (single-locale baseline,
      additive-ready)" — LOCKED. CR-001 does not introduce multi-language CMS as a
      business requirement — HD-CR001-01 (APPROVED, Option A) confirms Q31 remains
      locked for this CR.
```

## 5. Current Architecture (Summary)

A code-defined, database-backed Theme Engine: one active Theme at a time
(`ThemeActivation` singleton), each Theme owning per-content-kind Templates, each
Template composed of an ordered list of reusable Sections, each Section holding an
ordered list of typed Components, each Component's `config` JSON driving one of eight
renderer functions. Navigation is a separate per-Theme menu/item tree resolved through a
polymorphic destination resolver. This is a legitimate, safely-designed content
management data model — the pain point is entirely at the *operator-facing vocabulary
and workflow* layer, not at the underlying data model's soundness (see Section 6).

## 6. Current Pain Points

1. **Operator vocabulary is engineering vocabulary.** "Template", "Section",
   "Component", "navigation_menu_slot", "content_kind" are all real column/class names an
   operator would have to understand to use the CMS admin UI today (no dedicated
   "friendly" admin UI for Theme/Section/Component authoring exists yet in the recon'd
   frontend beyond `ThemeController`'s raw CRUD endpoints — there is no
   `Theme/Index.vue`-style rich page-builder UI in `resources/js/Pages` today).
2. **No visual/drag-free block reordering UX exists yet** — Section/Component ordering
   is a `position` integer manipulated via `reorderSections`/positional inserts at the
   API level; there is no admin page surfacing an ordered "Add Block / Reorder / Enable"
   list as described in Section 27-28.
3. **No dedicated ZISWAF product surfaces exist** — Donation is the only transaction
   product today; Zakat/Infaq/Sedekah/Wakaf/Fidyah/Qurban have no models, routes,
   controllers, or UI at all (confirmed absent from every recon list above). They are
   entirely IMP-019+ future scope, not something this CR can implement.
4. **DonationCreate.vue is a single flat form**, not a staged Kitabisa-style funnel
   (amount → identity → payment as one page, not three).
5. **Admin dashboard is a placeholder** with no widgets, no chart, no action-required
   list, no dark theme, no profile/role badge, no breadcrumb.
6. **Admin navigation has no groups beyond four flat labels** and no permission-aware
   filtering visible in the component itself (routes are Policy-guarded server-side, but
   the sidebar always renders the same static array to every viewer today).
7. **No governed calculator architecture exists** for Zakat or Fidyah — any calculator
   today would have to be invented from scratch, which is exactly what Sections 15-17 and
   20 require to NOT be an "ungoverned Vue-only calculator."

## 7. External UX Pattern Analysis

Based on established, general public knowledge of how these platforms are structured
(not a live scrape; no logos, copy, or pixel layouts are reproduced — patterns only, per
the "inspired by, not cloned" instruction).

### 7.1 Kitabisa (mobile pattern source)

Donation-first, campaign-first, transaction-first mobile experience: a compact header
(logo + search + notification/account), horizontally-scrollable ZISWAF/category
shortcuts near the top of Home, campaign cards with a progress bar + amount raised +
days-left, a persistent bottom tab bar oriented around fast action (Home / Explore /
Give-or-ZISWAF / Activity / Account), and campaign-detail pages with a large cover
image, a sticky bottom "Donate" CTA that survives scroll, and social-proof elements
(donor count, recent donor list) placed prominently. Donation funnels are short:
amount selection (with quick-pick presets) → identity/anonymity → payment method,
each usually its own screen/step rather than one long form.

### 7.2 Dompet Dhuafa (desktop pattern source)

Institutional-portal desktop experience: a utility topbar (language/contact/donor
login) above a main navigation bar exposing distinct top-level entries for
Zakat/Infaq/Wakaf/Qurban/Program alongside Campaign, a wide hero carousel, curated
"featured programs" and "featured campaigns" grids below the fold, a dedicated
Zakat-calculator entry point surfaced prominently (often in the main nav or as a
persistent CTA), impact/statistics bands, and a content-rich footer with institutional
information (legal/foundation credentials, contact, partner logos, social links) —
appropriate for a desktop audience that expects to research an institution before
transacting, distinct from mobile's transaction-first bias.

### 7.3 Rumah Zakat (secondary patterns)

Program-oriented navigation (grouping campaigns under named superprograms/impact
themes rather than a flat campaign list), a visible "impact report" / transparency
section, and donor-service oriented navigation entries (e.g. a "layanan donatur" /
donor-services area) — useful as a secondary information-architecture input for
Section 11's "Donor Services" and "Impact" entries.

### 7.4 BAZNAS (secondary patterns)

As Indonesia's official national Zakat body, BAZNAS's site emphasizes regulatory/
institutional trust signals (official body indicators, published financial reports)
and a Zakat-calculator-first navigation treatment (a calculator is typically one of the
most prominent top-level entries, not buried inside a generic "donate" flow) — this
directly informs Section 16's requirement that the Zakat Calculator be a first-class,
discoverable public surface, not a buried form field.

### 7.5 Synthesis for this repository

None of these patterns require a new business domain: they are entirely presentation/
information-architecture choices layered on canonical Campaign/Donation/(future
ZISWAF)/Payment domain data. This is directly compatible with Section 7's requirement
("same canonical backend/domain content... presentation may adapt substantially by
viewport") and this repository's existing separation of `PublicRenderer` (backend
projection) from Vue pages (presentation).

## 8. Design Principles

1. **One canonical domain, many presentations.** Mobile/desktop/ZISWAF-product
   variation is presentation-layer composition, never a forked business domain.
2. **Operator vocabulary, not engineering vocabulary.** The CMS-facing product is named
   and organized around what a philanthropy-org content editor thinks about (Home,
   Pages, Menus, Header & Footer, Banner) — the existing Theme/Template/Section/
   Component model may remain the *implementation* underneath (see Section 26).
3. **Server-authoritative money and policy.** Every calculator, every donation amount,
   every payment amount is computed and re-validated server-side; the browser proposes,
   the server disposes — already this repository's existing Money/idempotency
   discipline, extended (not reinvented) to Zakat/Fidyah.
4. **Fail-closed authorization, presentation-independent.** Navigation visibility is
   never a substitute for the Policy/RBAC check the underlying route already performs
   (already this repository's own documented invariant, restated for the new admin nav
   groups and the new Site Design/Page Builder surfaces).
5. **Progressive, non-destructive migration.** No existing CMS/Theme content is
   discarded; every new concept either wraps, adapts, or provides a compatibility path
   for the old one, with an explicit, later removal point (Section 58-59).
6. **Shared-hosting compatible, always.** No new mandatory infrastructure.

## 9. Target Experience Overview

```
PUBLIC MOBILE   -> Kitabisa-inspired: donation/ZISWAF-first, bottom nav, sticky CTA
PUBLIC DESKTOP  -> Dompet-Dhuafa-inspired institutional portal, secondary patterns from
                   Rumah Zakat (program grouping, impact/transparency) and BAZNAS
                   (calculator prominence)
CMS             -> "Site Design" + "Page Builder" vocabulary wrapping the existing
                   Theme Engine data model (Section 25-26)
ADMIN           -> Fixed dark backoffice, grouped/collapsible/permission-aware
                   navigation, redesigned dashboard, inspired by the supplied KMSIT
                   Computer reference's visual language only (not its business modules)
ZISWAF          -> Contextual per-product journeys (Section 12) sharing one canonical
                   Payment Hub initiation step
CALCULATORS     -> Server-authoritative, versioned-policy Zakat and Fidyah calculators
                   (Section 15-17, 20-21) — new governed architecture, IMP-019-owned
```

## 10. Mobile Public UX Architecture

**Header (compact):** logo (from Site Design branding) + search entry (Section 65
classifies as FUTURE CAPABILITY unless MySQL full-text is wired — see below) +
account/notification entry point (routes to existing donor login/dashboard; no new
notification backend is implied — PRESENTATION PLACEHOLDER unless a future IMP adds
one).

**Home composition (Page Builder blocks, Section 27):** Hero, a single consolidated
**ZISWAF** quick-shortcut row (Zakat / Infaq / Sedekah / Wakaf / Fidyah / Qurban — each a
`EditorialUX-configured` link into its own IA node, Section 11) — per **HD-CR001-04
(APPROVED, Responsive Hybrid)**, mobile presents these six philanthropy services under
ONE consolidated "ZISWAF" entry, never six separate top-level mobile shortcuts and never
a generic "Beri"/"Give" label — Quick Donation block, Featured Campaigns (domain-query
block against canonical Campaign projections — identical data source `content_list`
already uses today), Zakat Calculator CTA, Impact/statistics, Articles.

**Bottom navigation (persistent, mobile only) — HD-CR001-04 (APPROVED) final labels:**

```
Home | Campaign | ZISWAF | Aktivitas | Akun
```

Selecting **ZISWAF** exposes the six philanthropy service choices (Zakat, Infaq,
Sedekah, Wakaf, Fidyah, Qurban) as a consolidated entry point — never as separate bottom-
nav tabs (avoids overcrowding a 5-slot mobile bar, per the Human's explicit instruction).
Exact final visual treatment (sheet/menu/sub-nav) is presentation design detail, not
locked by this document. **Aktivitas** routes to the existing authenticated donor
dashboard (`me/donations`, `me/recurring-plans`) for a signed-in donor; for a guest it
degrades to a login/register prompt — no new guest "activity" data source is invented.
This is a NEW component (`resources/js/Components/Public/MobileBottomNav.vue`),
theme-configurable via the same `siteChrome()`-style projection (Section 32).

**Sticky CTA:** on Campaign Detail and ZISWAF product pages, a persistent bottom
"Donate"/"Bayar Zakat"/etc. bar — a thin, new, presentation-only Vue component with no
business logic (mirrors the existing `PaymentCreate.vue` "form submits to the existing
route" discipline).

## 11. Desktop Public UX Architecture

**Utility topbar:** language selector — **HD-CR001-01 (APPROVED, Option A): Q31
remains LOCKED**; the selector is either omitted entirely for v1 or, if shown at all,
honestly offers exactly the one currently-supported language (never implies
multilingual content that does not exist), contact/donor-login links.

**Main navigation — HD-CR001-04 (APPROVED, Responsive Hybrid, desktop side):**
Campaigns, Programs, Zakat, **Infaq**, **Sedekah** (distinct top-level entries, not a
combined "Infaq/Sedekah" or generic "Give" entry), Wakaf, **Fidyah**, Qurban, Impact,
Articles, About, Donor Services — each an entry in the existing `ThemeNavigationMenu`/
`ThemeNavigationItem` model (Section 31), desktop-visibility-flagged (Section 32
proposes adding a `visible_desktop`/`visible_mobile` pair of columns — MIGRATION
REQUIRED, Section 55). Exact final ordering remains presentation design detail, per the
Human's own clarification.

**Home composition:** wide hero, featured programs, featured campaigns, ZISWAF service
tiles, impact statistics, articles/news, partner logos, trust/institutional footer
content — same Page Builder block set as mobile (Section 9's "same canonical domain,
many presentations" principle), different block *selection and layout* per breakpoint
(Section 33).

## 12. Public Information Architecture

```
Home
Campaigns (index, detail)
Programs (index, detail)
Zakat Center (index) -> Zakat Penghasilan / Maal / Emas / Fitrah (each its own node)
  -> Zakat Calculator
Infaq (general, campaign-based)
Sedekah (general, campaign-based)
Wakaf (index, product/campaign detail)
Fidyah -> Fidyah Calculator
Qurban Center -> package/animal selection
Donation (funnel, reached from Campaign/ZISWAF entry points, never a standalone nav item)
Impact / Transparency
Articles / News
About (institutional)
Donor Services (donor dashboard entry, donor-facing help/FAQ)
Search
Donor Portal entry (login/register/dashboard)
Contact
Custom CMS Pages (operator-created, via Site Design Pages)
```

Every node above that represents a transaction product (Zakat/Infaq/Sedekah/Wakaf/
Fidyah/Qurban) is **IA and presentation architecture only** in this CR — none of them
have an approved business/domain model yet; they route to a "coming soon" or
IMP-019-gated placeholder state until their owning IMP lands (see Section 77 Non-Goals
and the Impact Matrix, Section 55).

## 13. Campaign UX

Preserves IMP-007 Campaign/Program/Fund semantics entirely. Presentation evolution only:
Campaign Detail gains (per Section 9 of the task) an image/media-forward layout, trust/
verification indicator (**PRESENTATION PLACEHOLDER** — no verification *business* concept
exists in IMP-007 today; a purely visual "official campaign" badge tied to
`Campaign::status === 'PUBLISHED'` plus organizer identity is honest; a deeper
verification tier is a Human Decision if wanted, not invented here), progress bar off the
existing `formatted_target_amount`/`is_donation_eligible` projections (no new financial
field), share action, and the sticky Donate CTA from Section 10. **Supporter/donor
*count* — HD-CR001-06 (APPROVED, Option A): permanently OMITTED from CR-001 v1.** No
donor-count aggregate (exact, coarse, guest, or anonymous) is displayed on public
Campaign Detail; no new public donor-count aggregate query exists in this CR's file map
(Section 70). A future public donor aggregate requires its own explicit Human Decision
plus a dedicated privacy review of IMP-008's guest/anonymous donation data — not decided
or revisited by this document again.

## 14. Donation UX

Kitabisa-inspired staged funnel, replacing `DonationCreate.vue`'s single flat form with a
multi-step presentation over the SAME `POST /campaigns/{slug}/donations` contract:

```
Campaign Detail -> "Donasi Sekarang" -> Step 1: Amount (presets + custom)
  -> Step 2: Donor Identity (+ optional anonymity, already a supported field) -> Step 3:
  review -> submit (same POST, same Idempotency-Key discipline) -> [HD-FE009-01 flow,
  UNCHANGED] Payment Creation/Selection -> Payment Attempt -> provider flow
```

**Explicitly preserved, restated verbatim:** Donation != Payment; Donation creation MUST
NOT automatically create a Payment Attempt; the multi-step funnel is client-side
presentation state only (e.g. a local Vue `ref` step index) — it still submits ONE POST
at the end, identical to today's single-page form, so no new endpoint, no partial-save
business state, no IMP-008 contract change. This is a PRESENTATION CHANGE, not a
CONTRACT AMENDMENT.

## 15. Zakat Center

IA node grouping four zakat types for v1: **Zakat Penghasilan, Zakat Maal, Zakat Emas,
Zakat Fitrah** (Section 14 of the task's list), each its own presentation entry point
into the shared Zakat Calculator (Section 16) with type-specific input fields. Zakat
Perdagangan/Perusahaan and other types are explicitly future extensibility, not v1 scope
— the `ZakatType` model (Section 16) must be a registry/table, not a hard-coded enum, so
adding a fifth type later is a data addition, not a schema migration (see Section 71
Model Impact Matrix). No religious/legal formula is invented anywhere in this CR — see
Section 17 and HD-CR001-03.

## 16. Zakat Calculator (Required Architecture)

**This must be server-authoritative — never an ungoverned Vue-only calculator.**
Proposed architecture (names indicative, not final, per the task's own instruction):

```
ZakatType                 registry row: code (penghasilan|maal|emas|fitrah|...), name,
                           calculation_method reference, is_active
ZakatPolicy                one row per (zakat_type, policy_version): rate (e.g. 2.5%),
                           nisab_basis description, source_reference, effective_from,
                           effective_until (nullable = current), status
                           (DRAFT/ACTIVE/RETIRED). Per **HD-CR001-03 (APPROVED, Option
                           C)**: `effective_from` MUST NOT be settable to a value earlier
                           than "creation time + a configured minimum lead time" — the
                           lead-time DURATION itself is governance/configuration data
                           (see Section 63's `PolicyLeadTimeConfig` concept), never a
                           value this architecture hard-codes.
NisabPolicy / GoldPriceReference
                           the nisab threshold basis — for gold-pegged types, a
                           versioned gold-price reference row (amount_minor + currency +
                           as_of_date + source_reference), NEVER a live/floating
                           external API value trusted without an admin-reviewed
                           snapshot (Section 64 money-safety requirement)
ZakatCalculatorService     stateless application service: given (zakat_type,
                           policy_version|"current", CalculationInput) -> CalculationResult.
                           Re-validates on the server; the browser's own arithmetic
                           (even if shown for UX responsiveness) is NEVER trusted as the
                           authoritative figure that prefills a payment amount.
CalculationInput           value object: raw user-supplied figures (income, asset
                           values, gold weight, etc.) — validated, never persisted raw
                           beyond the resulting snapshot below unless the donor opts to
                           save/reuse it
CalculationResult          value object: threshold_met (bool), zakat_due_minor,
                           currency, nisab_amount_minor, basis explanation
CalculationSnapshot        the PERSISTED, immutable record of one calculation: input,
                           result, policy_version_id referenced (not copied), computed_at,
                           acting principal (or null for guest) — this is what makes a
                           historical calculation reproducible/explainable per Section 64
                           of the task, and is the record a "BAYAR ZAKAT" CTA references
                           when prefilling the eventual Donation/Payment amount
```

**Non-negotiable requirements carried into architecture, restated:**
- Server computes and re-validates; client cannot override the authoritative result.
- Policy is versioned with an effective date and a source reference; a policy change
  never mutates a past `CalculationSnapshot`'s referenced version.
- Money uses this repository's existing `amount_minor`/currency-minor-unit discipline
  (Section 64) — no floats anywhere in `ZakatPolicy`/`CalculationResult`/gold-price
  math; gold weight (a physical quantity, not money) is the one place a decimal
  quantity type is appropriate, and it must be a fixed-precision decimal (e.g. MySQL
  `DECIMAL`), never a PHP float, exactly mirroring how this repository already treats
  money.
- A `CalculationSnapshot` may prefill a Donation/Payment amount, but creating that
  Donation/Payment is still the existing IMP-008/IMP-009 flow — the calculator NEVER
  itself creates a Payment Attempt (Section 22 boundary, restated).

This is new domain surface, owned by a future IMP (see Section 55 — "IMP-019
Zakat/Wakaf/Fidyah/Qurban" is the working label the task itself already uses); CR-001
defines its architecture contract now so the public/CMS/Admin UX designed here has a
real seam to integrate against later, without inventing the religious/legal rate or
nisab values themselves (Section 17, HD-CR001-03).

## 17. Zakat Policy Safety

`ZakatPolicy` rows are versioned, dated, and sourced (Section 16). Historical
`CalculationSnapshot` rows reference a specific `policy_version_id`, never a "current"
pointer, so a calculation performed under an old rate remains explainable forever even
after the rate changes. Admin policy changes should be treated as CRITICAL/audited
events (Section 62) — creating a new `ZakatPolicy` version is an auditable admin action.

**HD-CR001-03 (APPROVED, Option C):** no second-person approval state machine is
introduced. Instead, a new policy version becomes ACTIVE only after a **configurable
minimum effective-date lead time** has elapsed since its creation — the version cannot
take effect immediately/same-day when that governance rule applies. Per the Human's
explicit clarification: **the exact lead-time duration (e.g. "N days") is NOT decided by
this CR and MUST NOT be hard-coded anywhere** (not 1, not 2, not 3, not 7 days) — it is
governed, admin-configurable data (Section 63), so it can be tuned by policy owners
without a code change. Everything else about historical immutability (a version is
never edited in place; a correction is a NEW version), audit trail, effective date, and
source reference remains exactly as originally specified.

## 18. Infaq / Sedekah UX

Presentation-only "fast flow" pages sharing the SAME staged-funnel component built for
Donation (Section 14): quick nominal presets, custom amount, identity, optional public
anonymity display preference, payment. Both general (no Campaign) and campaign-attached
variants are IA nodes (Section 11); this CR does **not** decide whether Infaq/Sedekah are
their own IMP-008-sibling domain model or a Donation sub-type — that is a business/
accounting decision belonging to their owning future IMP, restated as a Non-Goal
(Section 77).

**Navigation placement — HD-CR001-04 (APPROVED, Responsive Hybrid):** on **desktop**,
Infaq and Sedekah are distinct top-level navigation entries (Section 11). On **mobile**,
they are not separate bottom-nav tabs at all — they are two of the six choices exposed
inside the single consolidated **ZISWAF** entry (Section 10). Neither surface ever uses
a generic **"Beri"/"Give"** label — that label is explicitly rejected by the Human; the
approved architecture label for the mobile consolidation point is **ZISWAF**.

## 19. Wakaf UX

Dedicated presentation for Wakaf campaigns/projects (money amount, Wakif identity,
program impact information, "WAKAF SEKARANG" CTA) — **preserving the existing locked
decision that Wakaf is a standard donation product** unless a later governed requirement
changes that. No legal Wakaf structure (cash waqf vs. waqf-linked-sukuk vs. any other
instrument) is invented here; the UX simply presents Wakaf as a distinct, brand-consistent
storefront over the same Donation/Payment mechanics used everywhere else in this CR.

## 20. Fidyah UX + Calculator

Same governed-calculator pattern as Zakat (Section 16), scoped down: `FidyahPolicy`
(rate/reference per missed-day unit, versioned, dated, sourced), `CalculationInput`
(number of days), `CalculationResult` (computed amount), `CalculationSnapshot`
(reproducible historical record), "BAYAR FIDYAH" CTA into the same Donation/Payment
mechanics. No formula/rate is invented — the exact per-day rate is external-verification/
Human-decision territory (mirrors this repository's own `EXTERNAL VERIFICATION` pattern
for unresolved external policy, per `IMPLEMENTATION-GOVERNANCE.md`'s "External
Verification Rule"). **HD-CR001-03 (APPROVED, Option C) applies identically to
`FidyahPolicy`**: the same configurable minimum effective-date lead time (no hard-coded
duration, Section 17) gates a new Fidyah policy version becoming ACTIVE — one shared
governance mechanism for both calculators, not two.

## 21. Qurban UX

Presentation architecture only, per the task's explicit instruction not to invent
prices/eligibility/share/distribution/religious rules. IA shape: Qurban Center ->
animal/package selection (Kambing/Domba, Sapi-share, Sapi-whole, or a generic "Package"
abstraction if the eventual IMP-019 domain model prefers that) -> quantity -> participant
(Pequrban) name(s) -> distribution program/area (where a future governed distribution
model exists) -> summary -> Payment. This CR fixes the *page flow shape* a future IMP-019
Qurban module will fill with real animal/package/price/distribution data — it creates no
model, no price, no rule.

## 22. Payment Hub Boundary (Restated, Preserved)

Every ZISWAF product above terminates in the SAME canonical Payment Hub initiation step
already built under IMP-009/FE-CHK-009: Donation != Payment, Zakat Calculation !=
Payment, Fidyah Calculation != Payment, Qurban Selection != Payment, Wakaf != Payment.
None of them creates a Payment Attempt directly; all of them hand off to the existing
`PublicPaymentController::create()`/`store()` pair (or its future ZISWAF-domain
equivalents, which MUST follow the identical pattern: a domain-owned "ready for payment"
state, never a domain-triggered provider redirect). Provider adapters, manual transfer,
idempotency, one-ACTIVE-attempt, amount validation, guest security, ownership, and
payment expiry are all preserved untouched.

## 23. Site Design V2

Renames the operator-facing surface of Theme/Branding/Navigation to a single **"Site
Design"** concept (Section 25 detail): Branding, Logo, Favicon, Colors, Typography,
Desktop Header, Mobile Header, Desktop Navigation, Mobile Navigation, Footer, Default
Layouts, responsive behavior. The internal `Theme`/`ThemeBrandingConfig`/
`ThemeNavigationMenu` tables are **ADAPTED** (new operator-facing controller/Vue layer,
same underlying schema — see Section 26 KEEP/ADAPT/WRAP decision) rather than replaced.

## 24. CMS V2

Renames the content-authoring surface into human-friendly groups (Section 24 of the
task, adopted essentially as proposed):

```
WEBSITE     — Dashboard Website, Home, Pages, Menus, Header & Footer, Banner, Media,
              Appearance, Page Builder, Preview
FUNDRAISING — Campaign, Program, Fund (Fundraiser: FUTURE CAPABILITY — no Fundraiser
              model/policy exists in the repository today; do not expose as active)
ZISWAF      — Zakat, Infaq, Sedekah, Wakaf, Fidyah, Qurban (all FUTURE CAPABILITY until
              their owning IMP lands — presentation IA only in CMS today, disabled/
              "coming soon" states, never fake-active)
CONTENT     — Articles, News classification (NEW — CmsArticle has no category/
              classification field today, MIGRATION REQUIRED if adopted), Video (NEW —
              no video-reference model exists; Section 66 Media Architecture), Media
DESIGN      — the Site Design surface from Section 23 (Branding/Colors/Typography/
              Headers/Navigation/Footer/Page Builder)
```

"Appearance" (WEBSITE group) and "Design" (DESIGN group) must not become two competing
places to edit the same Branding row — this CR resolves that by making "Appearance"
under WEBSITE a *shortcut/alias entry point* into the one canonical Site Design surface,
not a second data-editing surface (avoids Section 24's "do not duplicate menu concepts"
instruction).

## 25. Site Design V2 (Detail)

See Section 23. `logo_theme_asset_id`/`favicon_theme_asset_id` (existing FK-to-ThemeAsset
pattern) is KEPT as the storage mechanism — the operator UI simply presents an "Upload
Logo" control that resolves to the same asset-upload endpoint `ThemeController::
uploadAsset()` already provides; no new storage model needed.

## 26. One Active Site Design

Adopts the proposed model: **ACTIVE SITE DESIGN with Draft / Preview / Publish**, rather
than a WordPress-style Theme marketplace. Repository-evidence-based classification:

```
Theme (model)                  KEEP — the "ACTIVE SITE DESIGN" concept the operator sees
                                is presentation naming over this same row; is_system_default
                                and status (ACTIVE/ARCHIVED) already support "one active,
                                others archived" — no schema change required for the
                                *activation* semantics themselves.
ThemeActivation                KEEP — already exactly the "one active" singleton pointer
                                this CR needs; ADAPT only to add an optional
                                "assigned_by_principal_id"-driven audit trail surfaced in
                                the new UI (the column already exists, unused by the UI).
ThemeTemplate/Section/Component KEEP the schema; WRAP with a new operator-facing
                                Page Builder controller/Vue layer (Section 27) that never
                                shows "Template"/"Section"/"Component" vocabulary.
                                **HD-CR001-05 (APPROVED, Option A):** an "Advanced/Debug"
                                admin view exposing the raw technical hierarchy is
                                RETAINED for this initial V2, for power-user/support
                                diagnosis, on these conditions: clearly labeled
                                "Advanced"; not part of the normal operator workflow;
                                gated by the existing `ThemePolicy` authorization; not
                                automatically visible to every admin. Page Builder V2
                                remains the normal operator interface. Permanent removal
                                of this Advanced view is explicitly NOT committed to now —
                                that is a later, evidence-based decision (operator
                                adoption, support needs, migration completion, stability
                                evidence) requiring its own future governed decision.
ThemeBrandingConfig            KEEP.
ThemeNavigationMenu/Item        ADAPT — add `visible_desktop`/`visible_mobile` boolean
                                columns (MIGRATION REQUIRED, Section 55) so operators can
                                target Section 31/32's desktop/mobile navigation
                                independently without needing two separate menus.
ThemeAsset                     KEEP.
Draft/Preview/Publish workflow  NEW, additive: this requires either (a) a `draft_of_
                                theme_id`/copy-on-write approach (clone the active Theme's
                                full subtree into a DRAFT-status Theme row, edit the
                                clone, then "Publish" flips `ThemeActivation` to point at
                                it and archives the old one), or (b) a per-row
                                `is_draft`/`published_at` flag threaded through Template/
                                Section/Component. (a) is lower-risk (reuses existing
                                Theme-switching machinery verbatim) and is this CR's
                                RECOMMENDATION; (b) is more storage-efficient but touches
                                every table. **HD-CR001-02 (APPROVED, Option A):**
                                single-actor publish is confirmed — any
                                `ThemePolicy`-authorized operator publishes directly, with
                                no second-person approval step, consistent with the
                                locked Q29 baseline.
```

## 27. Page Builder V2

A new operator-facing Vue admin surface (`Admin/PageBuilder/*.vue`, new files) presenting
an ordered list of Blocks (= existing `ThemeComponent` rows) per page/section, with
Add Block / Remove / Enable-Disable / Reorder / Configure / Preview / Publish controls,
exactly as sketched in the task's own Section 27-28 example. This is a NEW frontend
surface (and a thin new set of admin endpoints wrapping the ALREADY-EXISTING
`ThemeController` component CRUD — `createComponent`/`updateComponent`/`deleteComponent`/
`reorderSections`), not a new domain model. **Reorder controls must be keyboard-operable
buttons (up/down, or a "move to position N" control) — drag-and-drop, if added at all, is
an enhancement layered on top of a working non-drag baseline, never a replacement for it**
(Section 28's own accessibility instruction, restated as a hard requirement here per
Section 34 Accessibility).

## 28. Page Builder UX

Adopts the task's own conceptual sketch (Section 28) directly: a linear block list per
page, each row showing a human label + [Settings] + [Move Up/Down] + [Enable/Disable]
controls, a persistent "+ Add Block" affordance, and Preview/Publish actions gated by the
existing `ThemePolicy` authorization. No change to that sketch is proposed — it already
matches this CR's principles.

## 29. Block Library

```
Block type            Classification   Notes
Hero                   EDITORIAL        existing `hero` component type — KEEP
Banner                 EDITORIAL        existing `banner` component type — KEEP
Rich Text              EDITORIAL        existing `rich_text` — KEEP
Campaign Grid          DOMAIN-QUERY     existing `content_list` (content_kind=campaign) —
                                        already queries IMP-007's own
                                        CampaignProjectionResolver — KEEP, rename in UI
Campaign Carousel      DOMAIN-QUERY     NEW component type, same projection as above,
                                        different Vue rendering (carousel vs grid) —
                                        ADDITIVE, no new query surface
Program Grid           DOMAIN-QUERY     existing `content_list` (content_kind=program) — KEEP
ZISWAF Services         EDITORIAL        NEW — links out to IA nodes (Section 11), no
                                        domain query until IMP-019 exists
Quick Donation          HYBRID           EDITORIAL layout + a DOMAIN-QUERY campaign picker
                                        (reuses Campaign eligibility projection)
Donation CTA / Zakat CTA / Zakat Calculator CTA / Wakaf CTA / Qurban CTA
                       EDITORIAL        existing `cta_button` component type, extended
                                        with a semantic "intent" config field for the
                                        colored-icon/analytics layer (Section 43) — no
                                        new component type strictly required
Statistics / Impact     HYBRID           existing `stats` component type is EDITORIAL
                                        (hand-entered numbers) today; an "Impact" variant
                                        sourced from real Donation/Payment aggregates
                                        would be DOMAIN-QUERY and requires a defined
                                        metric owner (Section 46) — NOT built until that
                                        exists; hand-entered `stats` remains available
Testimonials / FAQ / Gallery / Video / Articles / News / Partners / Contact
                       EDITORIAL/DOMAIN-QUERY (Articles/News are DOMAIN-QUERY against
                                        CmsArticle, same pattern as content_list; the rest
                                        are EDITORIAL) — Video is NEW (Section 66)
Safe Custom Content     EDITORIAL        a governed rich-text/HTML block — MUST go through
                                        the existing sanitization discipline
                                        (Section 30), never raw unescaped HTML
```

**Hard rule preserved:** DOMAIN-QUERY blocks always call the existing canonical
projection resolvers (`CampaignProjectionResolver`, `ProgramProjectionResolver`, or their
future ZISWAF equivalents) — a Page Builder block is never a second, competing
Campaign/Donation read path, exactly as IMP-006's own locked design already requires for
`content_list`.

## 30. Page Builder Security

- **Safe HTML / XSS:** `rich_text`/"Safe Custom Content" content is rendered via Vue's
  `v-html` today (confirmed in `ThemeRender.vue`) — this CR requires that ANY
  operator-supplied HTML continues to pass through the same server-side sanitization
  IMP-005/IMP-006 already establish for CMS body HTML before it ever reaches `v-html`;
  no new unsanitized HTML surface is introduced by Page Builder V2.
- **URL validation:** navigation/CTA destinations continue to use the existing
  `NavigationDestinationResolver`'s closed enum (SYSTEM_ROUTE/CMS_CONTENT/EXTERNAL_URL) —
  never a raw operator-typed `javascript:`-capable field without validation.
- **Media authorization:** reuses the existing `MediaPolicy`.
- **Preview/draft visibility:** a DRAFT Site Design (Section 26) must only be viewable
  through an authorized Preview action (existing `ThemePolicy`-gated), never a public URL
  guessable by an anonymous visitor — this is a NEW authorization surface requiring its
  own explicit Policy method (`ThemePolicy::preview()`), not an accidental extension of
  the public rendering path.
- **No arbitrary executable JavaScript** in any block config, ever.

## 31. Navigation Architecture

Operator-facing concepts: Menu Name, Menu Items (Label, Destination, Order, Visibility,
Parent/Child, Desktop Visibility, Mobile Visibility). Internally this maps directly onto
existing `ThemeNavigationMenu`/`ThemeNavigationItem` (Section 26 ADAPT: add the two
visibility columns). "navigation_menu_slot" remains an internal Component *type* — the
Page Builder UI (Section 27) presents it to the operator as a "Navigation Menu" block
with a Menu picker dropdown, never the raw type string.

## 32. Header / Footer Architecture

Independently configurable Desktop Header, Mobile Header, and Footer, each composed
from: logo, primary navigation (menu picker), utility links, search toggle, Donate CTA
toggle, social links, contact info, legal links, organization info. Implementation seam:
extend the existing `PublicRenderer::siteChrome()` (already contentKind-scoped and
already the FE-CHK-009-approved single source of chrome data — Section 4.4) to return a
richer, still-presentation-only payload; `PublicHeader.vue`/`PublicFooter.vue`
(FE-CHK-009-new, already generic) are ADAPTED to consume the richer shape, never
hard-coding organization-specific content (existing discipline, preserved).

## 33. Responsive Strategy

```
Breakpoints (Tailwind defaults, already in use throughout):  base(<640) / sm / lg
Mobile acceptance target:   375px  (existing FE-CHK-009 Section 12 baseline, kept)
Tablet:                     no dedicated tablet-only layout — content reflows between
                            the mobile and desktop compositions via existing `sm`/`lg`
                            breakpoints; a true tablet-specific Page Builder composition
                            is NOT proposed (scope control) unless evidence later shows
                            it's needed
Desktop acceptance target:  1280px (existing baseline, kept)
```

Same domain data, different Page Builder block *selection* per breakpoint context (a
Site Design may configure "show this block on mobile only" / "desktop only" — an
extension of the same visibility flags proposed in Section 26/31, generalized to blocks
too) — no duplicate backend content, per the task's explicit instruction.

## 34. Accessibility

Builds on FE-CHK-009's own already-established baseline (Section 13 of that
checkpoint's spec) and extends it project-wide: semantic landmarks (`<header>`/`<nav>`/
`<main>`/`<footer>`, already the pattern), keyboard navigation and focus visibility
(already required), screen reader support + ARIA on any new interactive component
(bottom nav, drawer, Page Builder reorder controls, admin sidebar), form labels + error
association (already flagged as a fix-forward item for `DonationCreate.vue` under
FE-CHK-009 — the new staged funnel must not regress this), touch target sizing (≥44px,
new requirement for the mobile bottom nav and sticky CTA), color contrast (reuse existing
Tailwind tokens, no new unvetted palette), reduced-motion support (`prefers-reduced-
motion` respected for any carousel/animation), responsive zoom (never disable
pinch-zoom), skip-navigation link (NEW — not present today, should be added to the
shared shell), accessible menus/mobile-drawer (Escape dismissal + focus trap — already
flagged as a gap in `AdminLayout.vue`'s existing drawer, carried into both the admin
drawer and the new public mobile nav), and accessible Page Builder controls (Section 27's
keyboard-operable reorder requirement). **Mobile navigation must never be gesture-only**
— every swipe/carousel interaction needs a tappable/keyboard equivalent.

## 35. SEO

Server-visible metadata (already partially present: `CmsPage`/`CmsArticle` have
`metaTitle`/`metaDescription`/`noIndex` per the existing `PublishedContent` projection
seen in `ThemeRender.vue`'s own prop shape) is extended to Campaign/Program pages
(currently NOT confirmed to emit meta tags — GAP, Section 70) and to the future ZISWAF
product pages. Canonical URL, Open Graph, and structured data (Organization + possibly
DonateAction/campaign-specific schema.org types where accurate — never fabricated claims)
are NEW, additive `<head>` composition in the shared Blade/Inertia head management
(`@inertiaHead` already present in `app.blade.php`). Sitemap/robots: NEW, a simple
server-rendered route reading canonical PUBLISHED content — no new infrastructure.

## 36. Performance

Preserves shared-hosting compatibility absolutely — nothing in this CR requires Redis,
WebSockets, a Node production runtime, PM2, Supervisor, or Docker; Vite-compiled assets
remain the deployment model (unchanged from FE-CHK-009). Specific analysis:
- **Block rendering query count:** `buildSections()` already iterates Section→Component
  per request; DOMAIN-QUERY blocks (Campaign/Program grids) already go through
  eager-loading-aware projection resolvers (IMP-006/IMP-007 discipline) — Page Builder V2
  must not add a new N+1 surface; each new DOMAIN-QUERY block type gets exactly one
  resolver call per render, mirroring `content_list`.
- **Image optimization/lazy loading/responsive images:** NEW discipline to establish for
  Campaign covers, Theme assets, and future Media blocks — `loading="lazy"` + `srcset`
  where the Media Manager (Section 66) can produce variants; no new image-processing
  service is mandated (a simple on-upload resize using PHP's existing GD/Imagick,
  already available on typical shared hosting, is sufficient — no ImageMagick-as-a-
  service dependency).
- **Pagination/campaign-listing performance:** `CampaignIndex.vue` already receives a
  paginated `campaigns` prop — preserved, extended to any new ZISWAF listing pages.
- **Cache opportunities:** Site Design/Page Builder reads are prime cache candidates
  (rarely change, read on every page load) — file/array cache (already shared-hosting
  compatible, no Redis requirement) is sufficient; this is a FUTURE OPTIMIZATION, not a
  blocking requirement for CR-001's own architecture.

## 37. Admin Experience V2

Adopts a **modern dark fixed backoffice**, inspired by the visual language and
interaction hierarchy of the supplied KMSIT Computer dashboard reference — explicitly
NOT its LMS/shop business modules, which are irrelevant to a philanthropy platform.
"Fixed design" is restated and preserved: **Admin/Super Admin is never theme-controlled
by Site Design** (locked decision, `AGENTS.md`) — the dark backoffice is a hard-coded
design system (Section 49), not a Theme row.

## 38. Admin Visual Character

Dark shell, left sidebar, topbar, cards with subtle borders, modern typography, compact
information hierarchy, colored icon accents (Section 43), status badges, profile/avatar,
expandable menu groups, sidebar toggle, responsive drawer — adopted directly from the
task's own Section 38 description. Avoid excessive gradients/decoration; prioritize
readability and operational clarity (a backoffice, not a marketing page).

## 39. Collapsible Sidebar

`AdminLayout.vue` ALREADY implements exactly this today (confirmed by recon, Section
4.5): expanded mode (icon + label + group title, active state) and collapsed mode
(icons-only, `lg:w-[4.5rem]`), a topbar toggle button, and a `localStorage`-persisted
preference (`admin.sidebar.collapsed`) explicitly correctly treated as a UI preference,
not business configuration. **Classification: ADAPT, not replace** — restyle to the dark
palette and add tooltips-on-collapsed-icon-hover (a genuine gap: today's collapsed mode
has no tooltip, only a `title` attribute) — the underlying collapse mechanism is sound
and stays.

## 40. Admin Mobile Sidebar

Also already implemented today as an off-canvas drawer with an overlay
(`AdminLayout.vue`, Section 4.5) — **ADAPT**: the existing overlay's `@click` close
handler has no Escape-key equivalent (a gap already flagged during FE-CHK-009 recon,
QWEN-FE009-05) and no explicit focus trap — both are NEW requirements to close under
Section 34 Accessibility, layered onto the existing, structurally-sound drawer.

## 41. Grouped Admin Navigation

Adopts the task's proposed grouping (Section 41) as the target IA, cross-checked against
what actually has a working route TODAY (Section 4.6):

```
DASHBOARD                                            ACTIVE (route exists, placeholder content)
FUNDRAISING   Campaign, Program, Fund                ACTIVE (all three have working admin routes)
              Fundraiser                              FUTURE CAPABILITY (no model/policy/route)
ZISWAF        Zakat/Infaq/Sedekah/Wakaf/Fidyah/Qurban FUTURE CAPABILITY (none exist) — must render
                                                      as a visibly-disabled/"coming soon" group,
                                                      never a dead link pretending to work
TRANSACTIONS  Donations, Payments                     ACTIVE (both have working admin routes)
              Manual Transfer, Refund, Reconciliation  Manual Transfer is folded into the existing
                                                      Payment admin surface (approve/reject/hold)
                                                      today — ACTIVE under Payments, not a separate
                                                      nav entry yet. Refund/Reconciliation: FUTURE
                                                      CAPABILITY (IMP-010+/Ledger-owned)
FINANCE       Ledger, Commission, Withdrawal, Distribution   FUTURE CAPABILITY (all IMP-010+ owned
                                                      — this group must not exist as a visible,
                                                      navigable entry before IMP-010 defines it;
                                                      see Section 77 Non-Goals)
WEBSITE       Home, Pages, Page Builder, Menus, Header & Footer, Appearance, Media
                                                      ACTIVE (Pages/Media exist today; Page
                                                      Builder/Menus/Header&Footer/Appearance are
                                                      this CR's own new operator-facing wrapper
                                                      around existing Theme endpoints)
CONTENT       Articles, News, Video                   Articles: ACTIVE. News (as a distinct
                                                      classification): FUTURE (MIGRATION
                                                      REQUIRED if adopted). Video: FUTURE (NEW
                                                      model needed, Section 66)
USERS         Donors, Fundraisers, Partners, Beneficiaries, Admin Users
                                                      NONE have a dedicated admin list page today
                                                      (donor/user data exists via IMP-002, but no
                                                      "Donors" admin index route was found in
                                                      recon) — ALL FUTURE CAPABILITY for this CR;
                                                      do not fabricate
REPORTING                                             FUTURE CAPABILITY (no reporting module exists)
SETTINGS                                              PARTIAL — Payment Provider Config exists
                                                      today (`admin/payment/provider-config`);
                                                      a general "Settings" group can start by
                                                      housing exactly that ACTIVE entry, expand later
INTEGRATIONS & GATEWAYS                               ACTIVE — same Payment Provider Config surface;
                                                      may be the SAME entry as Settings' payment
                                                      config, not a duplicate — resolve at
                                                      implementation time, not invented as two here
ACCOUNT                                               ACTIVE — maps to existing account/password/
                                                      MFA routes already in `routes/web.php`
```

**Hard rule:** every group/entry marked FUTURE CAPABILITY renders as visually present
but disabled (or entirely omitted, per Site Design configuration) — never as a clickable
link to a 404 or a fabricated working feature (Section 41 of the task, restated).

## 42. Permission-Aware Admin Navigation

`AdminLayout.vue` today renders the SAME static `navGroups` array to every viewer
(Section 4.5) — routes are Policy-guarded server-side, but the sidebar itself performs no
visibility filtering. **ADAPT requirement:** the Vue component must receive an
authorization-aware prop (e.g. a `can: Record<string, boolean>` map, or per-item
`visible` booleans computed server-side from the SAME Policies each route already uses —
never a client-side re-derivation of authorization logic) so a nav entry a viewer cannot
use is hidden, while the underlying route remains the sole authoritative gate. Restated,
verbatim, as this repository's own already-locked invariant: **hidden menu != denial;
visible menu != authorization; Super Admin != automatic Financial Authority.** The
FINANCE group (Section 41) in particular must never appear "visible because Super Admin"
— it must not exist as a real navigable group until IMP-010+ defines its own Business/
Financial Authority-gated Policies, and even then must respect them exactly.

## 43. Colored Icon System

Extends the existing curated Lucide subset (`Icon.vue`, ~22 icons today, already extended
under FE-CHK-009 with `heart`/`creditCard`/`settings`) — **no new icon library**. Adopts
the task's semantic color-direction table as a starting palette (Campaign/Donation=cyan,
Zakat=green, Wakaf=violet, Qurban=amber, Payment=blue, Refund=orange, Finance=yellow,
Security=red, Content=purple, Website=cyan/teal, Users=indigo, Integration=teal) as a
DESIGN DIRECTION, not final tokens — exact hex values belong in the Site Design/Admin
Design System implementation phase, using colors already present in this repository's
existing Tailwind palette usage (stone/emerald/slate/amber, per FE-CHK-009 Section 13)
extended minimally rather than importing an unrelated new color system.

## 44. Admin Topbar

```
Sidebar toggle             ACTIVE FUNCTIONALITY (already exists)
Breadcrumb/page context    NEW — PRESENTATION (derivable from route name, no backend needed)
Global search              FUTURE CAPABILITY unless/until an admin search backend exists
                            (Section 65) — until then, PRESENTATION PLACEHOLDER at most,
                            or omitted entirely (recommended: omit until real)
Language selector           PRESENTATION PLACEHOLDER (Q31 single-locale — HD-CR001-01
                            APPROVED, Option A: Q31 stays locked)
Notification entry          FUTURE CAPABILITY (no notification backend exists) — omit
                            until real, per the task's own "avoid fake functionality" rule
Profile/avatar menu         ACTIVE FUNCTIONALITY (ADAPT existing email+logout menu, Section 45)
```

## 45. Profile Dropdown

ADAPTS the existing minimal profile menu (`AdminLayout.vue` today: email + logout only)
into: Avatar/Initial (derivable from email, no new field required), User Name (requires
a `name` field on `User` if the product wants a real display name — currently `User`
only has `email`; **MIGRATION REQUIRED if adopted**, or fall back to email-derived
initial/label, which needs NO migration — this CR recommends the no-migration path for
v1 and flags a display-name field as a nice-to-have, not a requirement), Email, a
**presentation-only** canonical Role Badge (reads the viewer's actual highest-scope Role
from the existing RBAC read model — never invents a role, never grants anything by being
displayed), Profile, Account Security (maps to existing MFA/password routes), Language
(placeholder, see above), Logout (existing).

## 46. Admin Dashboard Overview

Every widget MUST cite its canonical data owner — no fabricated metric, per the task's
explicit instruction and this repository's own existing dashboard-route discipline
(Section 4.6, already correctly conservative):

```
Widget                          Canonical owner                    Status today
Total Donations                 IMP-008 donations table            NOT on dashboard yet — ADD
Zakat Collected                 future IMP-019                     FUTURE — omit until real
Donors (count)                  IMP-008 distinct donor principals  NOT on dashboard yet — ADD
Payments                        IMP-009 payments table             NOT on dashboard yet — ADD
Active Campaigns                IMP-007 campaigns (status=PUBLISHED) partially present (raw count
                                                                    of ALL campaigns exists today,
                                                                    not filtered to active) — ADAPT
Manual Transfers Awaiting Verification  IMP-009 payments (manual_transfer, PENDING, unreviewed)
                                                                    NOT present — ADD (this is
                                                                    exactly what Section 48's
                                                                    Action Required widget needs)
Pending Refunds / Pending Withdrawals / Distribution
                                 future IMP-010+/Ledger/Withdrawal  FUTURE — omit until real
Qurban statistics                future IMP-019                    FUTURE — omit until real
Recent Transactions             IMP-008/IMP-009                    NOT present — ADD
Content Statistics              IMP-005 (pages/articles/media counts) ALREADY present today
                                                                    (pages/articles counts) — KEEP,
                                                                    extend with media count
Action Required                 see Section 48                     NOT present — ADD
Collection Chart                 see Section 47                     NOT present — ADD
```

**Every "ADD" item above is a NEW read-only aggregation query against already-existing
canonical tables — no new business logic, no new write path.**

## 47. Collection Chart

Periods: 7 days / 30 days / custom. Filters: All / Donation / Zakat (future) / Infaq
(future) / Sedekah (future) / Wakaf (future) / Fidyah (future) / Qurban (future) — until
those domains exist, the filter list itself should only show "All" and "Donation" (the
only real transaction type today); adding disabled/future filter chips is a presentation
choice, not a requirement. **Hard rule, restated:** this chart aggregates **Donation
transaction counts/amounts** (IMP-008) and, separately, **Payment outcomes** (IMP-009) —
it never computes a *Ledger*-truth figure, since Ledger does not exist yet (IMP-010+).
Any dashboard figure that could be mistaken for "money the org actually has" must be
clearly labeled as a Donation/Payment-record metric, not an accounting fact, until Ledger
exists and becomes the one accounting source of truth (locked decision, `AGENTS.md`).

## 48. Action Required Widget

```
Candidate item                                Permission/scope/authority gate
Manual transfers awaiting verification         existing PaymentPolicy verify-capable check
                                                (ORGANIZATION scope + financial_approver-style
                                                authority, per IMP-009's existing admin route
                                                middleware)
Refund approvals / Withdrawal approvals /
Distribution approvals                         FUTURE (IMP-010+) — omit until those Policies exist
Campaign approvals                             existing CampaignPolicy (submit/approve/reject
                                                already IMP-007 states) — ACTIVE, includable now
Documents awaiting verification                maps to the same Manual Transfer evidence review
                                                queue above, not a separate concept yet
```

**No global unauthorized count leakage:** the widget's query MUST be scoped through the
viewing principal's own actual authority (same Policy the underlying admin action uses),
never a raw `count()` shown to every Admin regardless of their actual approval authority
— restated as a hard requirement, matching Section 48's own instruction.

## 49. Admin Design System

Proposed reusable primitives (new components, Vue, dark-theme-only per Section 37):
`AdminShell`, `AdminSidebar`, `AdminSidebarGroup`, `AdminSidebarItem`, `AdminTopbar`,
`AdminProfileMenu`, `AdminBreadcrumb`, `AdminPageHeader`, `AdminStatCard`,
`AdminActionCard`, `AdminChartCard`, `AdminTable`, `AdminFilterBar`, `AdminEmptyState`,
`AdminBadge`, `AdminModal`, `AdminDrawer`, `AdminPagination`, `AdminSearch`,
`AdminFormSection`. `AdminShell`/`AdminSidebar`/`AdminTopbar`/`AdminProfileMenu` are
**ADAPT** (evolve `AdminLayout.vue`'s existing, structurally-sound implementation into
these smaller named pieces + dark palette); the rest are **NEW** — none are implemented
in this task.

## 50. Admin Table Responsiveness

For data-heavy pages (Donations/Payments admin lists, future Users/Reporting), avoid
naive table-shrinking: evaluate horizontal-scroll-with-sticky-first-column, priority-
column hiding at narrow widths, a responsive-card fallback below a breakpoint, and a
detail drawer/panel for row actions instead of a wide inline action-button row. Filters/
pagination/bulk-actions must remain reachable and keyboard-operable at every width
(Section 34).

## 51. Current Admin Impact Analysis

```
Element                        Classification   Rationale
AdminLayout.vue (shell/sidebar/topbar/drawer)  ADAPT   structurally sound (Section 39-40);
                                                        restyle to dark palette, decompose
                                                        into the Section 49 primitives, add
                                                        permission-aware filtering (Section 42)
                                                        and accessibility fixes (Section 34)
Admin dashboard (inline route)  REPLACE (as a route/controller; data discipline KEPT)
                                                        the placeholder counts move into the
                                                        richer widget set (Section 46), same
                                                        "never fabricate" discipline preserved
Admin navigation array          ADAPT                   restructure into the grouped IA
                                                        (Section 41) + permission-aware props
Icon system (Icon.vue)          KEEP, extend             already the correct pattern (Section 43)
Authorization (Policies/RBAC)   KEEP, untouched          no business logic change — presentation
                                                        only reads existing Policy results
Profile UI                      ADAPT                    Section 45
Responsive behavior              ADAPT                    Sections 39-40, 50
Admin routes (Cms/Theme/Campaign/Donation/Payment controllers)
                                KEEP                     zero backend change required; Page
                                                        Builder/Site Design/Dashboard widgets
                                                        are NEW thin controllers/read
                                                        endpoints layered on top, not
                                                        replacements
```

## 52. Current CMS / Theme Impact

```
Element                          Classification  Rationale
Theme                            KEEP            Section 26
Template                         KEEP            Section 26 (wrapped by Page Builder UI)
Section                          KEEP            Section 26
Component/Block                  KEEP, extend     new component TYPES (carousel, video, etc.)
                                                  are additive; existing 8 types untouched
navigation_menu_slot architecture KEEP, hide      internal type name never shown to operators
                                                  (Section 31)
HomepageContentResolver           KEEP            untouched — home content-kind resolution
                                                  is orthogonal to this CR's changes
PublicRenderer                    ADAPT           extend siteChrome()'s payload richness
                                                  (Section 32); renderForContentKind()'s
                                                  core algorithm untouched
ThemeRender.vue                    ADAPT           consumes PublicHeader/PublicFooter already
                                                  (FE-CHK-009); gains new component-type
                                                  rendering branches additively
PublicShell/PublicHeader/PublicFooter  ADAPT       already generic (FE-CHK-009) — extended
                                                  with the richer chrome payload (Section 32)
                                                  and the new MobileBottomNav (Section 10)
Admin CMS pages (Page/Article/Media controllers)  KEEP  zero change; Page Builder is additive
```

No element in the current CMS/Theme stack requires MIGRATE, DEPRECATE, or REPLACE at the
data-model level — every gap this CR identifies is closed by an ADAPT (additive column/
richer projection) or a new WRAP layer (operator-facing naming/UI), never a rebuild.

## 53. Existing Backend Protection (Restated)

This CR does not restart the project. Preserved untouched: Identity, Authentication,
RBAC, Audit, Campaign, Program, Fund, Donation, Payment Hub, Payment Providers, Manual
Transfer, idempotency, guest security, ownership, financial boundaries. Nothing in
Sections 1-52 above proposes touching any of these domains' business logic — every
change identified is presentation, CMS-authoring-UX, or net-new ZISWAF/calculator
architecture that sits beside (never inside) these locked domains.

## 54. Open Codex Security Finding (Restated, Not Touched)

`CODEX-FE009-01` (MAJOR, OPEN, VALID, UNRESOLVED): guest Donation idempotent replay may
transfer PaymentCreate possession across anonymous sessions — a fresh anonymous session
replaying the same idempotency material against an already-created Donation may
incorrectly gain guest possession, when only an ACTUAL fresh creation should establish
it. **Not remediated, not designed around, not silently fixed here.** See Section 64 for
its placement in the phasing plan.

## 55. Database Migration Strategy

No destructive migration is required for anything KEEP/ADAPT-classified above (Sections
26, 51-52). New tables are additive:

```
NEW tables (additive, no data loss):
  zakat_types, zakat_policies, nisab_policies / gold_price_references,
  zakat_calculation_snapshots
  fidyah_policies, fidyah_calculation_snapshots
  policy_lead_time_configs (HD-CR001-03, Section 63 — no default duration value
  specified by this CR; the value itself is set later, by an authorized admin, as
  ordinary configuration data, not by this architecture)
  (Qurban/Infaq/Sedekah/Wakaf tables: deferred to their owning future IMP — not created here)

ADAPTED tables (additive columns only, no destructive change):
  theme_navigation_items  + visible_desktop, visible_mobile (nullable/defaulted booleans)
  users                    + name (nullable, optional — only if Section 45's display-name
                             path is chosen; the no-migration path needs nothing)
  theme_components         (no schema change — new `type` values are just new strings in
                             an existing VARCHAR column, per the existing migration's
                             `string('type', 32)`)

Draft/Preview/Publish (Section 26): if the copy-on-write approach is adopted, no new
column is needed at all (a DRAFT Theme is just another `themes` row with `status=DRAFT`,
not yet pointed to by `ThemeActivation`) — this is the "no schema change" reason it is
this CR's recommendation.
```

No existing row is ever deleted or destructively altered by any change proposed here.

## 56. Compatibility Strategy

No dual-read/adapter/compatibility-renderer layer is required, because nothing existing
is being replaced — every change is additive (new columns, new component types, new
wrapping UI). The one place a genuine "old vs new" coexistence question arises is Draft/
Preview/Publish (Section 26): during the transition, `ThemeActivation` continues to point
at exactly one Theme at all times (its existing invariant), so there is no ambiguous
intermediate state to reconcile — the existing activation-pointer model already IS the
compatibility mechanism. **Clean removal point:** none needed; there is no legacy code
path being retired.

## 57. Content Migration

Existing `CmsPage`/`CmsArticle`/`Theme`/`ThemeTemplate`/`ThemeSection`/`ThemeComponent`/
`ThemeNavigationMenu`/`ThemeBrandingConfig` rows are **all canonical content that must
remain available** — none are presentation-only scratch data. No record maps
automatically to a NEW concept because no existing concept is being replaced (Section
56); the only "manual review" ever implied is an operator manually reorganizing their
existing Sections/Components into the new Page Builder's clearer block-list presentation
— a UI/authoring-experience change, not a data transformation. **No destructive cleanup
is performed or proposed anywhere in this CR.**

## 58. Design Versioning

Draft / Preview / Publish for Site Design and Page Builder compositions: see Section 26's
copy-on-write recommendation. For individual CMS Pages, IMP-005's existing scheduled
publish/unpublish contract (draft → scheduled → published → unpublished, with
`content:run-scheduled-transitions`) is **preserved untouched** and remains the
authority for page-level lifecycle; Site-Design-level Draft/Preview/Publish is a
*separate, new* concept layered on the Theme/Activation model, not a replacement for
IMP-005's page lifecycle. **Q29 boundary:** a Draft→Publish button-press by an authorized
operator is NOT an editorial *approval* workflow (no second-person review/reviewer queue
is introduced) — it is the same single-actor publish action Section 8 (Theme Lifecycle)
of IMP-006 already supports. **HD-CR001-02 (APPROVED, Option A)** confirms exactly this:
single-actor publish, no second-person approval gate, for Site Design changes.

## 59. CMS Locale (Restated)

Q31 (single-locale CMS v1) remains LOCKED. **HD-CR001-01 (APPROVED, Option A)** confirms
this explicitly: multilingual CMS is NOT opened as part of this V2 effort. This CR's
"language selector" UI element (Sections 10, 44) is presentation-only chrome for a
single active locale — it does not imply, promise, or architect multi-language
*content*, and per the Human's explicit instruction must either be omitted or must
honestly expose only the currently-supported single language. No fake multilingual
functionality is created anywhere in this CR.

## 60. Shared Hosting (Restated)

Every proposal in this document — Site Design Draft/Preview/Publish, Page Builder, the
dark Admin shell, the Zakat/Fidyah calculators, the mobile bottom nav, the Collection
Chart, image variant generation — is deployable under Apache/LiteSpeed + PHP + MySQL +
Cron + compiled frontend assets, with no new mandatory Redis, Supervisor, PM2,
WebSocket, Node production runtime, or Docker dependency.

## 61. Security Boundaries (Restated)

XSS/unsafe HTML: Section 30. CSRF: unchanged (Laravel's existing CSRF middleware covers
every new form the same way it covers every existing one). Preview/draft leakage:
Section 30 (new `ThemePolicy::preview()` gate required). Unauthorized menu visibility:
Section 42 (presentation-only, never authoritative). Unsafe media access: existing
`MediaPolicy`, unchanged. Unsafe redirects: `NavigationDestinationResolver`'s closed enum
(Section 30) prevents this by construction. Guest possession/ownership/payment
authorization/admin authority/auditability: Sections 22, 42, 53, 54, 62 — all preserved,
none weakened for UX convenience anywhere in this document.

## 62. Audit Requirements

New actions this CR identifies as CRITICAL/auditable, classified per IMP-004's existing
criticality model (restated, not redefined): publishing a Site Design change
(MUTATION_ATOMIC-class, mirrors existing Theme activation's own audit treatment), a Site
Design Draft→Publish transition, a navigation change, a Zakat/Fidyah policy version
change (Section 17 — likely CRITICAL, given it affects historical-calculation
integrity), a branding change. **No retention policy is invented here** — that follows
whatever IMP-004's existing retention governance already establishes for CRITICAL
events. Implementation of these events is deferred to CR-001-J/K (Section 63 phasing),
not performed now.

## 63. Zakat Policy Admin UX

Admin surface fields: Zakat Type, Policy Version, Effective Date, Nisab Basis, Rate,
Reference Source, Status (Draft/Active/Retired). **No arbitrary untracked edits to
historical policy** — a published `ZakatPolicy` version is immutable once any
`CalculationSnapshot` references it (append-only versioning, mirrors this repository's
own Ledger-adjacent "corrections use reversal/adjustment, never in-place edit"
philosophy, applied here to policy rather than money).

**HD-CR001-03 (APPROVED, Option C) — materialized:** no second-person approval step is
added. Instead, a new, separately-governed configuration value —
**`PolicyLeadTimeConfig`** (indicative name; e.g. a single admin-settable "minimum
effective-date lead time" duration, scoped per policy family or platform-wide) —
determines the earliest `effective_from` a newly-created `ZakatPolicy`/`FidyahPolicy`
version may take. This CR does **not** set, recommend, or hard-code that duration
anywhere (not 1/2/3/7 days or any other value) — it is admin-configurable governance
data, itself presumably auditable when changed, separate from any individual policy
version. The admin UX for a new policy version therefore additionally surfaces: the
computed earliest-allowed effective date (derived from creation time + the current
`PolicyLeadTimeConfig` value), and a clear, non-overridable rejection if an operator
attempts to set an earlier date.

## 64. Calculator Money Semantics (Restated)

No floats anywhere in Zakat/Fidyah calculation. `amount_minor` + currency-minor-unit
semantics (this repository's existing `Support\Money` discipline) apply identically to
calculator results. Gold price/weight: weight is a physical quantity (fixed-precision
DECIMAL, never float); the referenced gold *price* itself is money and follows the same
`amount_minor` discipline as everything else. A `GoldPriceReference` row is an
admin-entered, dated, sourced snapshot — never a live/uncontrolled external float value
consumed directly into a calculation.

## 65. Search

```
Public search           FUTURE CAPABILITY — MySQL FULLTEXT indexing on CmsPage/
                         CmsArticle/Campaign/Program name+summary columns is sufficient
                         for v1 scale and is shared-hosting compatible; no Elasticsearch/
                         dedicated search service is proposed or needed.
Admin search             FUTURE CAPABILITY — same FULLTEXT approach, scoped to
                         admin-authorized records only.
Global command/search    FUTURE CAPABILITY — a keyboard-triggered admin command palette
                         is a nice-to-have UX layer over the same admin search backend,
                         not a separate infrastructure requirement.
```

Nothing here is proposed as ACTIVE for this CR's own implementation phases (Section 63);
it's scoped and classified so a later phase doesn't reach for an unnecessary search
service.

## 66. Media

Media Manager (existing `CmsMediaAsset`/`CmsMediaReference`/`MediaPolicy`) is extended,
not replaced: alt text (confirm/add a required field if not already present — GAP to
verify at implementation time), responsive variants (NEW — on-upload resize via PHP
GD/Imagick, Section 36), video references (**NEW** — no video model exists today; the
simplest, shared-hosting-safe approach is an external-URL reference field, e.g. a
YouTube/Vimeo embed URL, not self-hosted video transcoding/streaming infrastructure),
private/public asset distinction and file authorization (existing `MediaPolicy`,
unchanged — Manual Transfer evidence files, which ARE private per IMP-009, must never
become reachable through any CMS Media Manager listing; this CR does not touch that
existing separation).

## 67. Frontend Evidence Strategy

Extends FE-CHK-009's own hard-won evidence discipline (this session's own established
practice) forward to CR-001's implementation phases: 375px + 1280px runtime screenshots
(via an already-installed system browser in headless one-shot mode, or a properly
authorized interactive session/automation tool if the FE-CHK-009-discovered limitations
recur) for Home, Campaign listing/detail, Donation funnel, Zakat/Calculator, Wakaf,
Fidyah, Qurban, Payment transition, Admin dashboard, Admin sidebar (expanded/collapsed/
mobile drawer), Profile dropdown, Page Builder, Header/Footer. Evidence must explicitly
distinguish: (a) functional automated tests (PHPUnit/Feature), (b) actual runtime browser
verification (screenshots + DOM inspection, not static code reading), (c) security tests
(guest possession, ownership, authorization). **Static Tailwind-class inspection is
never accepted as runtime evidence** — restated as a hard lesson from this session's own
FE-CHK-009 evidence work (where a real tooling artifact was only caught by actually
rendering a page, and a previously-reported "PASS" had to be corrected after deeper
verification).

## 68. Testing Strategy

```
CMS/Site Design model tests           new model + policy tests, mirroring existing
                                       ThemePolicy/CampaignPolicy test patterns
Page Builder block validation/render   new Feature tests, mirroring existing
                                       renderComponent try/catch-degrades-to-null pattern
Navigation visibility/desktop-mobile   new tests for the visible_desktop/visible_mobile
                                       columns (Section 55)
Publish/Draft/Preview authorization    new ThemePolicy::preview()/publish() tests,
                                       including a negative-path "guest cannot preview
                                       an unpublished Draft" test
Zakat calculator                       CalculationInput -> CalculationResult unit tests
                                       across policy versions; a historical-reproducibility
                                       test (old snapshot + old policy version still
                                       produces the same figure after a new policy is added)
Fidyah calculator                      same pattern, scoped down
Campaign/Donation/Payment flow          existing tests, re-run as regression only — NOT
                                       rewritten for this CR's presentation changes
Admin permission-aware navigation       new tests asserting a nav item's visibility flag
                                       matches the SAME Policy the underlying route uses
Accessibility                          where feasible, automated checks (e.g. axe-core
                                       equivalent) layered onto existing Feature tests;
                                       manual runtime verification for anything automated
                                       tooling can't cover (Section 67)
Migration/legacy compatibility          a regression test asserting existing Theme/Page/
                                       Article content renders identically before and
                                       after any additive migration in Section 55
```

No test is implemented in this task.

## 69. Impact Matrix (IMP-Level)

```
IMP-005 CMS                    PRESENTATION CHANGE (CMS V2 operator UX) +
                                CONTRACT AMENDMENT (video/news-classification if adopted,
                                Section 24/66) — business semantics (publish/unpublish,
                                revisions, Q29/Q31) untouched
IMP-006 Theme Engine            PRESENTATION CHANGE (Site Design/Page Builder UX) +
                                MIGRATION REQUIRED (additive nav visibility columns,
                                Section 55) — rendering pipeline algorithm untouched
IMP-007 Campaign/Program/Fund  PRESENTATION CHANGE ONLY — no model/contract change
IMP-008 Donation                PRESENTATION CHANGE ONLY (staged funnel UI, Section 14) —
                                idempotency/state-machine contract untouched
IMP-009 Payment Hub             PRESENTATION CHANGE ONLY — provider/possession/security
                                contract untouched; CODEX-FE009-01 is a pre-existing
                                DEFECT in that contract's implementation, not something
                                this CR changes (Section 54)
FE-CHK-009                      PAUSED — its own remaining scope (frontend runtime
                                evidence gaps, MySQL evidence gaps) resumes after CR-001's
                                architecture is approved and sequenced (Section 72)
Future IMP-019 (Zakat/Wakaf/
Fidyah/Qurban)                   FUTURE INTEGRATION — this CR defines the UX/IA and the
                                calculator architecture contract IMP-019 will implement
                                against; it does not implement IMP-019 itself
Future IMP-020+ (Ledger,
Withdrawal, Refund, Distribution,
Reconciliation, Commission)     FUTURE INTEGRATION — Admin nav groups reference their
                                eventual existence (Section 41) but expose nothing active
                                before they land
IMP-010 boundary                 UNCHANGED — remains NOT STARTED; nothing in this CR
                                implements or requires Ledger/Financial-Consequence
                                posting logic
```

## 70. File Impact Matrix (Likely, Not Modified Now)

```
NEW (Vue):    resources/js/Components/Public/MobileBottomNav.vue
              resources/js/Components/Public/StickyDonateCta.vue
              resources/js/Pages/Public/Donation/{AmountStep,IdentityStep,Review}.vue (or
                a single DonationCreate.vue evolved into a stepper — implementation detail)
              resources/js/Pages/Admin/PageBuilder/*.vue (new operator Page Builder UI)
              resources/js/Pages/Admin/SiteDesign/*.vue (new Branding/Header/Footer/Nav UI)
              resources/js/Pages/Admin/Dashboard/*.vue (widgets, chart, action-required)
              resources/js/Components/Admin/{AdminShell,AdminSidebar,AdminSidebarGroup,
                AdminSidebarItem,AdminTopbar,AdminProfileMenu,AdminBreadcrumb,
                AdminPageHeader,AdminStatCard,AdminActionCard,AdminChartCard,AdminTable,
                AdminFilterBar,AdminEmptyState,AdminBadge,AdminModal,AdminDrawer,
                AdminPagination,AdminSearch,AdminFormSection}.vue
ADAPT (Vue):   resources/js/Components/Public/{PublicHeader,PublicFooter,PublicShell}.vue
              resources/js/Pages/Public/{ThemeRender,CampaignShow,DonationCreate}.vue
              resources/js/Components/Admin/AdminLayout.vue (decomposed into the above
                primitives, dark palette, permission-aware nav)
              resources/js/Components/UI/Icon.vue (new icon entries as needed)
NEW (PHP):     app/Services/Zakat/{ZakatCalculatorService,...}.php (future IMP-019, not
                this CR's implementation, but this CR's architecture contract)
              app/Http/Controllers/Admin/{PageBuilderController,SiteDesignController,
                DashboardController}.php
              app/Policies/SiteDesignPolicy.php (or ADAPT ThemePolicy with new methods)
ADAPT (PHP):   app/Services/Theme/PublicRenderer.php (richer siteChrome() payload)
              app/Models/Theme/ThemeNavigationItem.php (+ visibility columns)
              app/Models/User.php (only if display-name is adopted, Section 45)
NEW (migrations, future):  visibility columns on theme_navigation_items; zakat_types/
                zakat_policies/nisab_policies or gold_price_references/
                zakat_calculation_snapshots; fidyah_policies/fidyah_calculation_snapshots
NEW (tests):   mirrors every NEW file above, per Section 68
```

## 71. Model Impact Matrix

```
Theme                     KEEP     sound, sufficient for "Active Site Design" (Section 26)
ThemeActivation           KEEP     already the correct singleton-pointer pattern
ThemeTemplate             KEEP     wrapped, not changed
ThemeSection              KEEP     wrapped, not changed
ThemeComponent            ADAPT    new `type` string values only, no schema change
ThemeBrandingConfig       KEEP     sufficient for Site Design branding
ThemeAsset                KEEP     sufficient for logo/favicon/media storage
ThemeNavigationMenu       KEEP     sufficient
ThemeNavigationItem       ADAPT    + visible_desktop/visible_mobile (additive)
CmsPage/CmsArticle        KEEP     no change required by this CR
CmsMediaAsset/Reference   ADAPT    + responsive-variant metadata, alt-text confirmation
CmsHomepageAssignment     KEEP     no change required
User                      ADAPT (optional)  + name, only if Section 45's richer profile
                          is adopted; otherwise KEEP unchanged
NEW: ZakatType, ZakatPolicy, NisabPolicy/GoldPriceReference, ZakatCalculationSnapshot,
     FidyahPolicy, FidyahCalculationSnapshot, PolicyLeadTimeConfig (HD-CR001-03,
     Section 63 — the governed minimum effective-date lead-time setting, duration not
     specified by this CR) — all future-IMP-019-owned, architecture only in this document
```

No model above is proposed for REPLACE — every replacement candidate this task's own
template anticipates (Section 71) turns out, on actual repository evidence, to be
sound enough to KEEP or ADAPT. This CR does not recommend replacement solely for
aesthetic cleanliness, per its own instruction.

## 72. Implementation Phasing

Adjusted from the task's own suggested sequence, based on actual dependency evidence
found during recon (e.g., Admin Experience V2 does not depend on CMS V2 data-model
changes and can run in parallel once Site Design's read contract is stable; Security
Remediation for CODEX-FE009-01 has no dependency on ANY of this CR's UX work and could
run earlier, but is intentionally sequenced late here only to avoid contending for the
same Payment/Donation controller files Section G also touches):

```
CR-001-A  Architecture / Contracts            (THIS document; Human Spec Approval gate)
CR-001-B  CMS / Site Design Data Model         (Section 55 migrations: nav visibility
                                                columns; Draft/Preview/Publish mechanism)
CR-001-C  CMS Admin UX (Page Builder, Site Design UI)
CR-001-D  Admin Experience V2 (dark shell, grouped nav, dashboard, design system) —
                                                may run CONCURRENTLY with C (different
                                                files: Admin/* vs Cms/Theme controllers),
                                                per Section 73's "no concurrent
                                                free-writing on the SAME files" rule
CR-001-E  Public Responsive Shell (Header/Footer/mobile bottom nav/sticky CTA)
CR-001-F  Campaign / Donation UX (staged funnel)
CR-001-G  ZISWAF Public UX (IA/placeholder pages) + Calculator Foundation
                                                (Zakat/Fidyah architecture — genuinely new
                                                domain surface, kept as one phase since
                                                both calculators share one pattern)
CR-001-H  (merged into G above — task's own numbering compressed since Calculator
          Foundation and ZISWAF Public UX are the same dependency wave)
CR-001-I  Legacy/Content Migration verification (Section 57's regression proof — before/
                                                after identical rendering)
CR-001-J  Security Remediation, including CODEX-FE009-01 (Section 54 of this doc)
CR-001-K  Regression / Evidence / Audit (full FE-CHK-009-style evidence pass across
                                                every new surface, Section 67)
```

Each phase gets its OWN Human Spec Approval before its own Qwen recon begins, per the
existing Amendment V3 workflow — this document is only CR-001-A.

## 73. Multi-Agent Workflow (Restated, Unchanged)

```
Claude -> Human Spec Approval -> Qwen Recon -> Muse Implementation -> Automated Tests
  -> DeepSeek -> Muse/Claude remediation -> Full Regression -> Codex -> Human Stage Gate
```

Principle: RECON ONCE -> IMPLEMENT BY FILE MAP -> REVIEW BY DIFF -> REMEDIATE BY FINDING.
Minimum sufficient context; no concurrent free-writing on the same files (directly
informed this CR's own phasing choice to run C/D concurrently only because they touch
disjoint file sets).

## 74. Ownership

```
Claude    architecture/contracts/security-sensitive design (this document; CODEX-FE009-01
          remediation design in CR-001-J; Zakat/Fidyah calculator server-authoritative
          contract)
Qwen      governed read-only recon/file map for each phase
Muse      default implementation writer for each phase's approved file map
DeepSeek  independent technical/diff reviewer for each phase
Codex     final semantic/closure auditor for each phase
Human     all Human Decisions (Section 76), architecture approval, final Stage Gate,
          merge/push authority
```

## 75. Risk Analysis

```
Risk                              Mitigation
CMS migration data loss           No destructive migration anywhere in this CR (Section 55)
Theme compatibility break         Every change is additive/wrapping (Section 52) — a
                                   before/after rendering regression test gates CR-001-I
Frontend regression                Full existing Feature-test suite re-run as regression
                                   at the end of every phase (existing discipline, unchanged)
Campaign/Donation UX regression    Staged funnel submits the SAME endpoint (Section 14) —
                                   existing DonationHttpTest suite is the regression gate
Payment boundary erosion           Every ZISWAF product explicitly re-states the Section 22
                                   boundary; CR-001-J closes CODEX-FE009-01 under the SAME
                                   boundary, not a new one
Guest possession weakening         No session/possession mechanism is touched by any UX
                                   phase; CODEX-FE009-01's fix (CR-001-J) is scoped exactly
                                   to the flaw already identified, nothing broader
Zakat/Fidyah calculation error     Server-authoritative + versioned policy + immutable
                                   snapshot (Sections 16-17, 20) makes every calculation
                                   traceable to an exact policy version; no live/floating
                                   external value trusted directly
Admin authorization leakage        Permission-aware nav is presentation-only; every
                                   underlying route keeps its existing Policy gate
                                   unchanged (Section 42) — a nav-visibility bug can at
                                   worst show/hide a link, never grant access
Responsive/accessibility gaps      Explicit acceptance criteria + runtime evidence
                                   requirement (Sections 34, 67) — static inspection is
                                   explicitly disallowed as sufficient evidence
Shared hosting violation           Every new capability re-checked against the Section 60
                                   constraint before being marked ACTIVE in any phase
SEO regression                     New meta/OG/structured-data work is additive; nothing
                                   removes existing CmsPage/CmsArticle meta fields
Performance regression (N+1)       DOMAIN-QUERY blocks must reuse existing projection
                                   resolvers (Section 29); new block types reviewed for
                                   query count before merge
Data migration risk                Minimized to zero destructive operations (Section 55);
                                   only additive columns/tables
Existing content loss               Section 57 — no automatic discard, ever
Scope explosion                    Explicit phasing (Section 72) + explicit Non-Goals
                                   (Section 77) + Open Human Decisions (Section 76) drawn
                                   as hard boundaries before any implementation phase begins
```

## 76. Human Decisions

**Human authorization (verbatim):** "Saya setujui HD-CR001-01 sampai HD-CR001-07 sesuai
rekomendasi." All seven decisions below are **APPROVED**. Their original question/
options/architectural-impact/recommendation history is preserved unedited below the
summary table, per this repository's governance practice of never erasing a decision's
reasoning trail — only the final resolution is new.

### 76.0 Resolution Summary

```
ID           DECISION                          STATUS     ARCHITECTURAL CONSEQUENCE
HD-CR001-01  Option A                          APPROVED   Q31 stays LOCKED; language
                                                           selector omitted or honestly
                                                           single-option only (Sections
                                                           4.8, 11, 44, 59)
HD-CR001-02  Option A                          APPROVED   Site Design Draft->Preview->
                                                           Publish uses single-actor
                                                           publish; no approval-state
                                                           machine; Q29 preserved
                                                           (Sections 4.8, 26, 58)
HD-CR001-03  Option C, with Human               APPROVED   New ZakatPolicy/FidyahPolicy
             clarification: lead-time                      versions gated by a
             DURATION is configurable/                     configurable minimum
             governed, NOT hard-coded                      effective-date lead time
             by this CR                                    (new `PolicyLeadTimeConfig`
                                                           concept, Sections 16, 17, 20,
                                                           63, 55, 71) — no duration
                                                           value invented anywhere
HD-CR001-04  Responsive Hybrid: desktop         APPROVED   Desktop: Infaq + Sedekah
             Infaq/Sedekah distinct;                       distinct top-level entries
             mobile consolidated                          (Section 11). Mobile: single
             "ZISWAF" entry (not "Beri"/                    consolidated "ZISWAF" bottom-
             "Give")                                       nav entry exposing Zakat/
                                                           Infaq/Sedekah/Wakaf/Fidyah/
                                                           Qurban (Section 10). "Give"/
                                                           "Beri" label rejected
                                                           everywhere (Sections 10, 18)
HD-CR001-05  Option A (initial V2)              APPROVED   Advanced/Debug Theme admin
                                                           view retained, clearly
                                                           labeled, Policy-gated, not
                                                           default-visible; permanent
                                                           removal NOT committed to now
                                                           (Section 26)
HD-CR001-06  Option A                          APPROVED   No public donor/supporter
                                                           count of any kind (exact,
                                                           coarse, guest, or anonymous)
                                                           on CR-001 v1 Campaign Detail;
                                                           no new aggregate query exists
                                                           (Section 13)
HD-CR001-07  Option A                          APPROVED   Admin/Super Admin backoffice
                                                           is dark-only; no light-mode
                                                           toggle, no dual palette;
                                                           Public Site Design never
                                                           controls Admin appearance
                                                           (Sections 37, 49)
```

### 76.1 Preserved Decision History

```
HD-CR001-01
Question:   Does CR-001's language-selector UI element (Sections 10, 44) remain a
            single-locale-only presentation placeholder, or does the Human actually
            want to open multilingual CMS scope as part of this V2 effort?
Why it matters: Q31 (single-locale CMS v1) is LOCKED. A visible language selector with
            only one option is honest UI; building it as if multiple languages exist
            invites scope creep into a locked decision.
Option A:   Keep Q31 locked — the selector (if shown at all) offers exactly one language,
            or is omitted entirely for v1.
Option B:   Formally reopen Q31 for CR-001 — define multilingual CMS as new V2 business
            scope (a large, separate architecture effort, not absorbed silently here).
Architectural impact: Option A = zero schema/architecture change. Option B = a
            content-translation data model, locale-fallback rendering, and a materially
            larger CR entirely.
Recommendation: Option A — keep Q31 locked; omit or single-option the selector.

HD-CR001-02
Question:   Does Site Design's Draft -> Preview -> Publish workflow require a
            second-person APPROVAL step before Publish takes effect, or is it a
            single-actor publish action (mirroring IMP-006's existing Theme activation)?
Why it matters: Q29 (no editorial approval workflow in CMS v1) is LOCKED. A second-person
            approval gate would be a new editorial-approval-shaped workflow.
Option A:   Single-actor publish (any ThemePolicy-authorized operator publishes
            directly) — consistent with Q29, zero new workflow state.
Option B:   Add a second-person approval step specific to Site Design publish only —
            requires treating Q29 as scoped to CONTENT (Pages/Articles) rather than
            PRESENTATION (Site Design), a boundary interpretation the Human must confirm.
Architectural impact: Option A = the copy-on-write Draft mechanism (Section 26) as
            described. Option B = an additional approval-state machine + reviewer
            assignment + its own audit events.
Recommendation: Option A — single-actor publish, consistent with the existing locked
            Q29 baseline.

HD-CR001-03
Question:   Does creating a new ACTIVE ZakatPolicy/FidyahPolicy version require a
            second-person approval step, or is admin-authorized creation sufficient
            (with full audit trail)?
Why it matters: An incorrect Zakat/Fidyah rate directly affects real donor payment
            amounts — this is a business/governance risk decision, not an architecture
            fact this document can infer.
Option A:   Single authorized-admin action creates and activates a new policy version
            immediately (fully audited, versioned, reversible via a NEW version, never
            an in-place edit).
Option B:   New policy versions require a second approver before becoming ACTIVE
            (DRAFT -> pending-approval -> ACTIVE state machine).
Option C:   New policy versions require ACTIVE status plus a mandatory effective-date
            delay (e.g. cannot take effect same-day), giving a review window without a
            formal second approver.
Architectural impact: A/C need no new approval-state machine; B needs one, plus its own
            audit/notification design.
Recommendation: Option C — no new approval workflow (stays consistent with Q29's
            single-actor spirit), but a mandatory minimum effective-date lead time adds a
            real safety margin without inventing an editorial approval concept.

HD-CR001-04
Question:   Do Infaq and Sedekah get distinct top-level public navigation entries, or do
            they share one "Beri" / "Give" entry with an internal choice step?
Why it matters: Pure information-architecture choice with real navigation-complexity
            and future-domain-model implications (Section 18) — not decided by
            architecture evidence alone.
Option A:   Distinct top-level entries for Infaq and Sedekah (matches Dompet-Dhuafa-style
            institutional navigation depth).
Option B:   One shared "Give"/"Beri" entry, with Infaq/Sedekah as a choice inside it
            (matches Kitabisa-style simplified mobile navigation).
Architectural impact: Both are pure IA/routing choices — no backend impact either way.
Recommendation: Option A for desktop navigation (Dompet-Dhuafa-inspired depth), Option B
            for the mobile bottom-nav/ZISWAF-shortcut row (Kitabisa-inspired brevity) —
            i.e., not a single global answer, but a per-breakpoint one, consistent with
            Section 7's "same domain, different presentation" principle.

HD-CR001-05
Question:   What is the deprecation/removal timeline, if any, for exposing the raw
            Theme/Template/Section/Component vocabulary through an "Advanced/Debug"
            admin view (Section 26), versus removing that technical view entirely once
            Page Builder V2 is stable?
Why it matters: Determines whether ThemeController's existing raw CRUD endpoints need a
            long-term direct-access UI or can eventually become internal-only,
            Page-Builder-mediated endpoints.
Option A:   Keep an Advanced/Debug view indefinitely for power users/support diagnosis.
Option B:   Remove direct technical access once Page Builder V2 is proven stable
            (a defined future removal point, per Section 58's "define a clean removal
            point" instruction).
Architectural impact: Option A keeps `ThemeController`'s existing routes as a supported,
            documented (if power-user-only) surface indefinitely. Option B eventually
            retires the raw admin UI for those routes (the routes/services underneath
            can remain, just unreachable via UI).
Recommendation: Option A initially (keep it available, clearly labeled "Advanced"), with
            Option B as a later, evidence-based decision once Page Builder V2 has real
            operator adoption data — do not pre-commit to removal now.

HD-CR001-06
Question:   Should Campaign Detail display a public "N donors" / supporter-count
            aggregate (Section 9 of the task's own list, "supporter/donor count where
            permitted")?
Why it matters: IMP-008 donation records include guest and anonymous donations; a public
            aggregate count has donor-privacy and scope implications IMP-007/IMP-008
            never explicitly approved for public display.
Option A:   Do not display any donor-count aggregate in CR-001's v1 scope — revisit only
            with an explicit future Human Decision once IMP-008's own privacy posture is
            reviewed for this specific public-facing use.
Option B:   Display a coarse count only (e.g. "50+ donors"), never an exact figure or any
            donor-identifying detail, sourced from a clearly-defined, privacy-reviewed
            aggregate query.
Architectural impact: Option A = zero new query surface. Option B = a new, carefully
            scoped read aggregate (never joined to guest email/name) plus a privacy
            review.
Recommendation: Option A for CR-001 — omit until a dedicated privacy review authorizes
            Option B.

HD-CR001-07
Question:   Should Admin ever support a light-mode toggle, or is the backoffice
            permanently dark-only, per the KMSIT Computer reference?
Why it matters: Section 37 says "modern dark fixed backoffice"; the task's own Section 76
            candidate list separately asks "Admin dark-only vs optional light mode" —
            these are in tension and the Human's actual intent should be confirmed
            explicitly rather than assumed either way.
Option A:   Dark-only, permanently — simplest, matches "FIXED DESIGN" instruction most
            literally (no theme-switching logic to build/maintain in the backoffice).
Option B:   Dark-default with an optional light-mode toggle (a per-viewer UI preference,
            analogous to the existing sidebar-collapse preference — never a Site-Design-
            driven setting, preserving "Admin is not theme-controlled by Site Design").
Architectural impact: Option A = zero extra work. Option B = a small, presentation-only
            preference toggle (same `localStorage` pattern already used for sidebar
            collapse) — not a material architecture change either way, but changes the
            Section 49 Design System's token structure (needs both palettes defined).
Recommendation: Option A — dark-only for v1, matching the reference and the task's own
            "FIXED DESIGN" framing most directly; Option B can be added later at low cost
            if the Human wants it, without having blocked anything in the meantime.
```

### 76.2 Final Human Resolutions (verbatim consequence, materialized)

```
HD-CR001-01 — APPROVED (Option A)
  CMS remains SINGLE-LOCALE for this V2 effort. Q31 remains LOCKED. No multilingual
  CMS architecture is introduced. Any language selector is either omitted, or honestly
  exposes only the currently supported single language. No fake multilingual
  functionality. Future multilingual CMS requires a separate future Human Decision/
  change request.

HD-CR001-02 — APPROVED (Option A)
  Site Design uses SINGLE-ACTOR PUBLISH. Any operator authorized by canonical Theme/
  Site Design policy may publish per existing authorization rules. No second-person
  approval, reviewer assignment, or editorial approval state machine is created for
  Site Design publishing. Q29 preserved. Draft -> Preview -> Publish remains the
  workflow.

HD-CR001-03 — APPROVED (Option C) WITH HUMAN CLARIFICATION
  ZakatPolicy and FidyahPolicy use VERSIONED POLICY + a MANDATORY MINIMUM EFFECTIVE-DATE
  LEAD TIME. The Human explicitly required: do NOT invent a concrete duration (not 1, 2,
  3, or 7 days, or any other value) — the minimum lead-time value MUST be configurable/
  governed data, not architecture-decided. A newly-created policy version must not
  become effective immediately when the configured minimum-lead-time rule applies. No
  second-person approval state machine is introduced. Immutable historical policy
  versions, new-version correction instead of in-place mutation, audit trail, effective
  date, reference/source, and historical calculation reproducibility are all preserved.

HD-CR001-04 — APPROVED (Responsive Hybrid)
  Different navigation composition by breakpoint. DESKTOP: Infaq and Sedekah may be
  distinct top-level public navigation entries (conceptual order: Home, Campaign,
  Program, Zakat, Infaq, Sedekah, Wakaf, Qurban, ... — exact final ordering is
  presentation design detail). MOBILE: bottom navigation is NOT overcrowded with
  separate Infaq/Sedekah entries — a consolidated **ZISWAF** entry/shortcut is used
  instead (conceptual bottom nav: Home, Campaign, ZISWAF, Aktivitas, Akun). Selecting
  ZISWAF exposes Zakat/Infaq/Sedekah/Wakaf/Fidyah/Qurban as internal choices. A vague
  generic "Beri" label is explicitly NOT the approved architecture — the approved label
  is **ZISWAF**. Same canonical domains/data; different responsive presentation; no
  backend-domain duplication.

HD-CR001-05 — APPROVED (Option A for initial V2)
  Retain an ADVANCED/DEBUG administrative view for the existing technical Theme/
  Template/Section/Component concepts where operationally useful. Requirements: clearly
  labeled Advanced; not part of the normal operator workflow; protected by canonical
  authorization; not automatically visible to every admin; Page Builder V2 remains the
  normal operator interface. No commitment to permanent removal now — future removal
  requires a later governed decision based on operator adoption, support needs,
  migration completion, and stability evidence.

HD-CR001-06 — APPROVED (Option A)
  CR-001 does NOT expose a public donor/supporter aggregate count. No exact, coarse
  ("50+ donors"), guest, or anonymous donor count is displayed on CR-001 v1 public
  Campaign Detail. No new public donor-count aggregate query is required. A future
  public donor aggregate requires an explicit future Human Decision plus a privacy
  review. No donor-identifying information may be exposed.

HD-CR001-07 — APPROVED (Option A)
  Admin/Super Admin backoffice is DARK-ONLY for this V2. No light-mode toggle, no dual
  Admin palettes. Admin remains a fixed dark backoffice inspired by the supplied KMSIT
  Computer admin interaction reference. Public Site Design MUST NOT control Admin
  appearance. Dark-only is a presentation decision, not an authorization or business
  setting.
```

### 76.3 Open Human Decision Re-Scan (post-materialization)

The complete architecture was re-scanned after integrating all seven resolutions above,
specifically checking whether materializing them created any NEW unavoidable decision
(e.g., the HD-CR001-03 lead-time-duration clarification could have forced a new gate,
but it did not — the Human's own instruction already resolves it as "configurable, value
decided later by an authorized admin as ordinary configuration," which is a complete,
actionable architecture statement, not an open fork).

```
OPEN HUMAN DECISIONS: 0
```

No new question is manufactured to create an artificial gate. If a genuinely new,
unavoidable decision surfaces during CR-001-A's own phase specifications (Section 72),
it will be raised then, as HD-CR001-08 or higher, following this same format.

## 77. Explicit Non-Goals

- No Ledger implementation (IMP-010+ territory, untouched).
- No IMP-010 implementation of any kind.
- No payment provider rewrite (Tripay/Xendit/Stripe adapters untouched).
- No RBAC rewrite (permission-aware nav is presentation-only, reads existing RBAC).
- No Identity/Authentication rewrite.
- No Audit rewrite (new events classified per existing IMP-004 model, not a new model).
- No Qurban business-rule invention (prices, eligibility, share, distribution, religious
  rules — all deferred to governed IMP-019).
- No Zakat/Fidyah religious-rule or rate invention (Sections 16-17, 20; external
  verification required, per this repository's own `External Verification Rule`).
- No hard-coded Zakat/Fidyah policy minimum effective-date lead-time duration — per
  HD-CR001-03 (APPROVED), that value is configurable/governed data, decided later by an
  authorized admin, never a number this architecture invents (Sections 16-17, 20, 63).
- No production data mutation (this task touched nothing but this one document).
- No arbitrary WordPress-style Theme marketplace (Section 26 explicitly rejects this in
  favor of One Active Site Design).
- No exact cloning of Kitabisa/Dompet Dhuafa/Rumah Zakat/BAZNAS layouts, copy, logos, or
  proprietary content — patterns only (Section 7).
- No CODEX-FE009-01 remediation in this task (Section 54/64 placement only).
- No FE-CHK-009 specification file modified by this task.
- No application code, migration, or seeder created or modified by this task.

## 78. Deliverable (Restated)

The only repository file created or modified by this task is this document:
`docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md`. The FE-CHK-009
specification (`docs/implementation/POST-IMP-009-frontend-integration.md`) was not
opened for writing and is unmodified by this task.

## 79. Document Structure (Self-Check)

All 69 requested sections (Executive Summary through Recommended Next Step) are present
above, mapped 1:1 onto this document's own numbered sections (this document's Section N
corresponds to the task's requested Section N throughout).

## 80. Acceptance Criteria (Self-Check)

```
[x] repository recon completed                              Section 4
[x] current Theme/CMS architecture mapped                    Sections 4.2-4.4, 52
[x] current Admin architecture mapped                        Sections 4.5-4.7, 51
[x] Kitabisa mobile pattern analyzed                          Section 7.1
[x] Dompet Dhuafa desktop pattern analyzed                    Section 7.2
[x] Rumah Zakat pattern analyzed                               Section 7.3
[x] BAZNAS pattern analyzed                                    Section 7.4
[x] Mobile V2 architecture defined                             Section 10
[x] Desktop V2 architecture defined                            Section 11
[x] Donation UX defined                                        Section 14
[x] Zakat UX defined                                           Sections 15-17
[x] Zakat Calculator architecture defined                      Section 16
[x] Infaq/Sedekah UX defined                                   Section 18
[x] Wakaf UX defined                                           Section 19
[x] Fidyah architecture defined                                 Section 20
[x] Qurban UX defined                                          Section 21
[x] CMS V2 defined                                             Section 24
[x] Site Design V2 defined                                     Sections 23, 25-26
[x] Page Builder defined                                       Sections 27-30
[x] Admin Experience V2 defined                                 Sections 37-38, 49
[x] Admin grouped navigation defined                             Section 41
[x] sidebar toggle architecture defined                          Section 39
[x] profile dropdown defined                                    Section 45
[x] colored icon design system defined                          Section 43
[x] permission-aware navigation defined                          Section 42
[x] migration strategy defined                                  Sections 55-59
[x] security boundaries preserved                                Section 61
[x] Payment Hub preserved                                       Section 22
[x] CODEX-FE009-01 remains open and accounted for                Sections 54, 64, 72(J)
[x] IMP-010 remains not started                                  Sections 3, 69, 77
[x] Open Human Decisions explicitly listed and resolved            Section 76 (7 raised,
                                                                    all 7 APPROVED by
                                                                    Human; 0 new decisions
                                                                    after re-scan, Section
                                                                    76.3)
[x] No application code modified                                Section 81
[x] No database modified                                        Section 81
```

## 81. Final Repository Safety Check

```
git status --short   ->  exactly ONE new file:
                          docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
                          plus the pre-existing FE-CHK-009 dirty working tree (15 modified +
                          8 untracked implementation files + 1 modified specification file),
                          UNCHANGED and untouched by this task.
git diff --check      ->  no whitespace/conflict-marker issues.
Database              ->  untouched (no command executed against any database in this task).
```

(Executed and verified before this document was finalized — see the task's closing
verification pass; nothing beyond this one new file was created or modified.)

## 82. Recommended Next Step

1. ~~Human reviews this document and resolves HD-CR001-01 through HD-CR001-07~~ —
   **DONE**: all seven decisions are APPROVED (Section 76); the re-scan found zero new
   decisions (Section 76.3).
2. Human explicitly approves (or amends) the phasing in Section 72.
3. Claude produces the CR-001-A → -B → ... individual phase specifications (each its own
   Human Spec Approval gate, per Section 73's workflow), starting with whichever phase
   the Human confirms should go first — this document recommends CR-001-B (CMS/Site
   Design data model) and CR-001-D (Admin Experience V2) as the lowest-risk, most
   evidence-grounded starting points, since both are ADAPT-classified against
   already-sound existing models (Sections 26, 39-40) with zero destructive migration.
4. FE-CHK-009's own remaining evidence gaps (Payment/Admin runtime screenshots, the 16
   environment-blocked MySQL tests) and CODEX-FE009-01 remain tracked, separately, for
   resolution at CR-001-J/K (Section 72) — neither is abandoned by this pause, both are
   explicitly resequenced, not forgotten.
