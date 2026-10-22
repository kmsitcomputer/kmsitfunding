# POST-IMP-009 — Frontend Integration Checkpoint (Specification)

## Status

`SPECIFICATION APPROVED — HUMAN SPEC GATE CLOSED (HD-FE009-01, HD-FE009-02 approved; open human
decisions = 0) — AUTHORITATIVE BASELINE FOR QWEN GOVERNED RECON`

## Task Identity

```
Task ID:        FE-CHK-009
Governance:     GOV-FRONTEND-001 (docs/00-governance/DEFINITION-OF-DONE.md,
                "Post-IMP-009 Frontend Integration Checkpoint")
Type:           One-time checkpoint (not an IMP)
Precondition:   IMP-005, IMP-006, IMP-007, IMP-008, IMP-009 — FINAL / LOCKED
                Super Admin Bootstrap — VERIFIED / CHECKPOINT CLOSED
Gate:           IMP-010 MUST NOT start until this checkpoint passes Human
                Stage Gate (Human-activated, per GOV-FRONTEND-001 activation).
```

## 1. Authority

This document is Level 5 (Implementation Specification) under
[DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md). It does not create, reopen, or
amend any locked business semantics owned by IMP-005 through IMP-009. Where this document
references a locked rule (Donation, Payment, Fund, RBAC, Theme), that rule is restated for
integration context only — the owning IMP document remains authoritative.

This checkpoint operates under [DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)
"Frontend Progress Checkpoint" section, which GOV-FRONTEND-001 added, and under the workflow in
[IMPLEMENTATION-GOVERNANCE.md](../00-governance/IMPLEMENTATION-GOVERNANCE.md) / Amendment V3
(Qwen recon → Muse implementation → DeepSeek review → Codex audit → Human Stage Gate).

## 2. Objective

Bring already-implemented IMP-005..009 backend capabilities into a coherent, discoverable,
minimum-functional public and Admin frontend baseline, closing concrete integration gaps found
during read-only repository recon (Section 4). This is integration wiring — Vue components,
presentation-data plumbing, and Admin navigation — not new business logic.

## 3. Explicit Non-Goals

- No change to Donation, Payment, Fund, Campaign, Program, RBAC, or Theme Engine business
  semantics, validation rules, state machines, or database schema.
- No Ledger, Journal, double-entry, or Financial Consequence posting UI (IMP-010+ ownership).
- No new permissions, roles, authority types, or scope rules.
- No visual redesign. No new design system. No new CSS framework.
- No demo/seeded financial data.

## 4. Recon Findings (read-only, confirmed against HEAD `2e7baae9ce414b8df4cca99c49bccd07b17f4eb0`)

### 4.1 Public routes (routes/web.php)

```
GET  /                                                    PublicContentController::home
GET  /{any}                                               PublicContentController::show (IMP-006 catch-all, LAST)
GET  /programs/{program:slug}                             PublicProgramController::show
GET  /campaigns                                           PublicCampaignController::index
GET  /campaigns/{campaign:slug}                           PublicCampaignController::show
GET  /campaigns/{campaign:slug}/donate                    PublicDonationController::create
POST /campaigns/{campaign:slug}/donations                 PublicDonationController::store
POST /donations/{donation}/payments                       PublicPaymentController::store
GET  /donations/{donation}/payments/{payment}             PublicPaymentController::show
POST /donations/{donation}/payments/{payment}/manual-transfer/evidence   PublicPaymentController::storeEvidence
```

### 4.2 Public Vue pages (resources/js/Pages/Public/)

```
ThemeRender.vue     — full Theme/Template/Section/Component renderer; owns its OWN inline
                      header/footer + navigation_menu_slot resolution (only page that does).
CampaignIndex.vue   — static, non-theme header ("Campaigns" hardcoded); receives NO branding prop.
CampaignShow.vue    — receives `branding` (color_tokens/font_family) but no header/nav/logo;
                      contains a STALE comment "Donation is IMP-008 (NOT STARTED)" and renders a
                      disabled placeholder ("Donation is not yet available on this platform")
                      even though IMP-008 is FINAL/LOCKED and a working donation flow exists at
                      /campaigns/{slug}/donate.
ProgramShow.vue     — receives `branding` only, no header/nav/logo.
DonationCreate.vue  — receives NO branding/chrome at all; bare form, no shell.
NotFound.vue        — not inspected further (out of scope; no integration gap implied).
```

No `Public/Payment*.vue` page exists at all.

### 4.3 Confirmed Payment UX dead-end (the most severe gap)

`PublicPaymentController::store()` and `::storeEvidence()` both `redirect()->route('public.payments.show', ...)`,
and `PublicPaymentController::show()` returns `response()->json(self::publicPayload($payment))` —
**there is no Inertia page behind that route.** A real donor who creates a Payment or submits
Manual Transfer evidence is redirected to a URL that renders raw JSON in the browser, not a
usable page. `publicPayload()`'s existing, canonical field set is:

```
ulid, status, provider, amount_minor, currency, instructions (Payment::instructions_payload), created_at
```

`StorePaymentRequest` requires only `provider` (`in:manual_transfer,tripay,xendit,stripe`) and an
optional `channel` — amount/currency are inherited from the Donation, never re-entered.
`StoreEvidenceRequest` requires `evidence` (file, max 5120KB) plus optional
`declared_amount_minor` / `declared_currency` / `declared_transferred_at`.

### 4.4 Confirmed Donation → Payment flow gap

`PublicDonationController::store()` redirects to `public.campaigns.show` (back to the Campaign
page) with `flash('status', 'donation-created')` on success — it does **not** carry the donor
into Payment creation. There is currently no page offering "choose how to pay" for a just-created
Donation.

### 4.5 Confirmed theme-data inconsistency across public pages

`PublicCampaignController` and `PublicProgramController` each independently implement a private
`brandingPayload($theme)` method returning only `color_tokens`/`font_family` (no logo, no
navigation). `PublicRenderer` (the Theme Engine's own service) already resolves branding and
`navigation_menu_slot` components internally for `ThemeRender`, but exposes no public method other
Public controllers can reuse — which is why every non-Home public page duplicates its own ad hoc,
inconsistent header instead of the Theme Engine's own canonical presentation.

### 4.6 Admin navigation gap (resources/js/Components/Admin/AdminLayout.vue)

Current `navGroups`: `Overview`, `Content`, `Fundraising`, `Presentation`. No `Donations` or
`Payments` group, despite these routes already existing and being authorized:

```
GET  /admin/donation/donations            AdminDonationController::index   (name: donation.admin.index)
GET  /admin/payment/payments              AdminPaymentController::index    (name: payment.admin.index)
GET  /admin/payment/provider-config       AdminPaymentProviderConfigController::index
                                           (name: payment.admin.config.provider-config)
```

`admin/donation/recurring-plans` and the Payment manual-transfer approve/reject/hold actions have
no `index` route (`{plan}`/`{payment}`-scoped only) — these are reached from a Donation/Payment's
own show page, not a top-level sidebar entry, and this checkpoint does not add one.

## 5. Scope A — Reusable Public Composition

**Component ownership.** New, generic (non-Campaign/Donation-coupled) presentation components,
mirroring how `AdminLayout.vue` is already generic relative to Admin's business pages:

- `resources/js/Components/Public/PublicHeader.vue` — renders logo + primary navigation
  (desktop inline nav, mobile disclosure), extracted from `ThemeRender.vue`'s existing inline
  `<header>` markup verbatim (behavior-preserving extraction, not a rewrite).
- `resources/js/Components/Public/PublicFooter.vue` — renders the existing minimal footer
  (logo + copyright), extracted from `ThemeRender.vue`'s existing `<footer>` verbatim.
- `resources/js/Components/Public/PublicShell.vue` — thin wrapper providing the shared
  `min-h-screen` / brand CSS-variable container + slots for header/main/footer, used by
  `CampaignIndex`, `CampaignShow`, `ProgramShow`, `DonationCreate`, and the two new Payment pages
  (Scope E). `ThemeRender.vue` may keep its own root markup unchanged (it already owns the richest
  presentation contract); using `PublicShell` there is optional, not required.

**Props/contract (Header/Footer — theme data boundary).**

```ts
interface PublicChromeNavItem { label: string; url: string; children: PublicChromeNavItem[] }
interface PublicChrome {
    logoUrl: string | null;
    faviconUrl: string | null;
    navigation: PublicChromeNavItem[]; // [] when no navigation_menu_slot is configured
}
```

`PublicHeader`/`PublicFooter` accept this shape as props ONLY. They perform no data fetching, no
theme lookup, and no fallback business logic beyond "render nothing extra when a field is
null/empty" (mirrors `ThemeRender`'s existing `v-if="template.branding.logo_url || primaryNav"`
degrade-to-nothing behavior). This is the full theme data boundary: Theme Engine (backend) decides
what chrome exists; these components only render it.

**Backend plumbing (minimal, presentation-only).** Add one public method to the existing Theme
Engine service, reusing its own already-private logic (no new resolver, no competing authority):

```
MODIFY app/Services/Theme/PublicRenderer.php
  + public function siteChrome(?Theme $theme): array
    returns ['branding' => [...same shape as existing buildBranding()...],
             'navigation' => [...same shape ThemeRender's primaryNav already computes...]]
    by extracting the navigation_menu_slot lookup already inlined in renderForContentKind()'s
    section walk into a small private helper both call — no behavior change to ThemeRender's
    own rendering path.
```

Then each non-Home public controller calls `$renderer->siteChrome($theme)` once and passes it as
a `chrome` prop, **replacing** its own duplicated `brandingPayload()`:

```
MODIFY app/Http/Controllers/PublicCampaignController.php   (index + show: drop brandingPayload(), use siteChrome())
MODIFY app/Http/Controllers/PublicProgramController.php    (show: drop brandingPayload(), use siteChrome())
MODIFY app/Http/Controllers/PublicDonationController.php   (create: add chrome prop — it currently has none)
```

`PublicContentController` (Home/ThemeRender) is **not modified** — it already receives the full
`template` object, which is a superset of `siteChrome()`'s data; `ThemeRender.vue` keeps computing
its own `primaryNav`/branding exactly as today, just rendering it through the extracted
`PublicHeader`/`PublicFooter` components instead of inline markup, so its behavior is unchanged
byte-for-byte from the visitor's perspective.

**Fallback behavior.** No logo configured → header renders nothing but nav (unchanged from
today). No navigation configured → header renders logo only, or nothing (unchanged). This mirrors
`ThemeRender.vue`'s existing `v-if` conditions exactly; the extraction must not change them.

**Responsive behavior.** Preserve `ThemeRender.vue`'s existing Tailwind breakpoints verbatim
(`sm:flex` desktop nav, `sm:hidden` mobile toggle + disclosure panel) — this is a proven, already
loosely-accessible pattern (see Scope I), not a new one.

**Theme Engine boundary (restated).** `PublicHeader`/`PublicFooter`/`PublicShell` are composition,
not a second theme authority: they render exactly what `PublicRenderer::siteChrome()` /
`ThemeRender`'s own template payload supplies, never a hard-coded nav/logo/brand value.

## 6. Scope B — Home Product Surface

`ThemeRender.vue` remains the canonical Home renderer. `HomepageContentResolver` → `PublicRenderer`
→ active `Theme`/`Template`/`Sections`/`Components` is **unchanged**. The only permitted change to
`ThemeRender.vue` is the Scope A header/footer extraction (Section 5) — no new component types, no
new props beyond what it already receives (`content`, `template`).

Home's visible product surfaces (Hero, `content_list` campaign/program discovery, `cta_button`,
`stats`, `card_grid`, etc.) are entirely CMS/Theme-configuration-driven already (IMP-005/IMP-006
component types) — this checkpoint does not add a component type, it only ensures Home's existing,
already-approved component types (`content_list` already renders `is_donation_eligible` /
`formatted_target_amount` per item, per the file read in Section 4.2) continue to link correctly
into the now-integrated Campaign → Donation → Payment path (Scopes C/D/E), and that Home's own
Header/Footer are the same reusable components used everywhere else (Scope A).

No content or business state is fabricated: what Home shows is exactly what CMS/Theme
configuration + `content_list`'s existing eligibility/target-amount metadata already produce.

## 7. Scope C — Campaign / Program / Fund Integration

No Campaign/Program/Fund semantics change. Fund remains non-mutable/non-authoritative-balance per
locked architecture — nothing in this checkpoint reads or displays a Fund balance field (none is
displayed today either; `CampaignShow`/`content_list` only ever show `formatted_target_amount`,
which is Campaign target data, not a Fund balance).

Required integration work, all presentation-only:

- `CampaignIndex.vue`, `CampaignShow.vue`, `ProgramShow.vue` adopt `PublicShell` +
  `PublicHeader`/`PublicFooter` via the new `chrome` prop (Scope A), replacing their current
  inconsistent/absent headers.
- `CampaignShow.vue`: remove the stale "Donation is IMP-008 (NOT STARTED)" comment and its
  disabled placeholder block; replace with a real link to the existing, working
  `/campaigns/{slug}/donate` route when `is_donation_eligible` is true (using the exact same
  `is_donation_eligible` prop the page already receives — no new eligibility logic invented,
  no new prop). When `is_donation_eligible` is false, keep an equivalent non-interactive message
  (reusing `availabilityMessage`, already computed in this file).

## 8. Scope D — Donation Integration

No Donation semantics change. Preserved verbatim: Donation != Payment; Campaign-only donation
target; guest one-time donation rules; authenticated recurring rules; MONTHLY-only recurring v1
(BR per IMP-008 §"Recurring"); Donation expiration semantics; `idempotency_key` (client-supplied,
required, BR-12) rules; guest security rules (ADR-003 unauthenticated attribution).

Required integration work:

- `DonationCreate.vue` adopts `PublicShell` + `PublicHeader`/`PublicFooter` via the new `chrome`
  prop added to `PublicDonationController::create()` (Scope A) — currently this page has no shell
  at all.
- Add a "Back to campaign" link (existing `campaign.slug`, already a prop) for navigation/back-flow.
- No change to the existing form fields, validation display, or idempotency-key generation logic
  (`crypto.randomUUID()` per-mount) — these are correct and unrelated to shell integration.
- Discoverability of this page from a valid Campaign is delivered by Scope C's `CampaignShow.vue`
  change (the real donate link).

**Post-creation flow — resolved by HD-FE009-01 (see Section 20).** On a successful Donation,
`PublicDonationController::store()`'s redirect target changes from `public.campaigns.show` to the
new canonical Payment Creation / Selection UI (`PaymentCreate.vue`, Scope E) **only when payment
initiation is available/eligible for that Donation** (i.e. it exists and has no ACTIVE Payment
Attempt yet — an ordinary, already-governed IMP-009 state check, not a new eligibility rule
invented here). The canonical flow is:

```
Campaign -> Donation Creation -> successful Donation -> Payment Creation / Selection UI
  -> canonical Payment Attempt creation (donor-initiated, on PaymentCreate.vue)
  -> provider-specific flow
```

`PublicDonationController::store()` itself performs **no** Payment Attempt creation and **no**
redirect to an external provider — it only changes which of the application's own pages the
browser is sent to next. Payment Attempt creation remains exclusively `PublicPaymentController::store()`'s
responsibility, invoked only by an explicit donor action on `PaymentCreate.vue`. All IMP-009 rules
governing that creation call are unaffected and fully authoritative: maximum one ACTIVE Payment
Attempt per Donation, provider boundaries, Manual Transfer behavior, Payment expiry, amount
validation (inherited from the Donation, never re-entered), idempotency (`Idempotency-Key`
required), guest security (session-possession binding), and the recurring MANUAL-PER-CYCLE model.

## 9. Scope E — Payment Integration

No Payment Hub semantics change. Preserved verbatim: Payment != Donation; Payment != Ledger; one
ACTIVE Payment Attempt per Donation; Manual Transfer proof rules (evidence required before
approval, per IMP-009 "no Manual Transfer approval without at least one submitted evidence row");
amount-mismatch/manual-review semantics; guest security rules (session-possession binding, F-04/
F-05, never a bearer token); provider boundaries (`manual_transfer`/`tripay`/`xendit`/`stripe`,
`assertProviderAvailable()`); Money representation (`amount_minor`/`currency`, never float); late
success behavior; recurring manual-per-cycle model.

This is the highest-priority gap (Section 4.3). Required work:

**New page: `resources/js/Pages/Public/PaymentShow.vue` (CREATE).**
Consumes exactly `PublicPaymentController::publicPayload()`'s existing fields
(`ulid, status, provider, amount_minor, currency, instructions, created_at`) — no new backend
field is invented for this page. Renders:
- formatted amount/currency and status (read-only display of canonical backend state, per
  GOV-FRONTEND-001 Financial Domains rule — this page never computes or stores a financial value,
  it displays what the backend already returns);
- `instructions` verbatim (opaque JSON payload already produced by the owning provider-specific
  service — this page must render it generically, e.g. key/value or provider-declared shape, and
  MUST NOT assume or invent a specific instructions schema beyond what already exists);
- when `provider === 'manual_transfer'` and `status` is still awaiting proof: an evidence upload
  form posting to the existing `POST .../manual-transfer/evidence` route with exactly the existing
  fields (`evidence` file, optional `declared_amount_minor`/`declared_currency`/
  `declared_transferred_at`) — no new field.

**Backend change required to make this page reachable as an Inertia page rather than raw JSON**
(resolved by **HD-FE009-02** — see Section 20):

```
MODIFY app/Http/Controllers/PublicPaymentController.php
  show(): content-negotiate on the request —
          HTML/browser navigation  -> Inertia::render('Public/PaymentShow', [...publicPayload()...])
          explicit JSON request    -> response()->json(publicPayload($payment))   (UNCHANGED)
          Both branches call the SAME existing publicPayload($payment) — one canonical
          projection, two presentation adapters. No new business logic, no new endpoint, no new
          API surface. This is a presentation dispatch inside the existing route/controller
          method, NOT an implementation of IMP-025 (REST API) — /api/v1/* remains separately
          owned by IMP-025 and is untouched by this checkpoint.
```

**New page: `resources/js/Pages/Public/PaymentCreate.vue` (CREATE)** — resolved by **HD-FE009-01**
(see Section 20): this page is the canonical Payment Creation / Selection UI a donor reaches
immediately after a successful Donation. Its contract is fixed by the existing, unmodified
`StorePaymentRequest`: a `provider` selector (`manual_transfer` / `tripay` / `xendit` / `stripe`)
and optional `channel`, posting to the existing `POST /donations/{donation}/payments` route with
the existing required `Idempotency-Key` header (same per-mount `crypto.randomUUID()` pattern
`DonationCreate.vue` already uses). This page performs the Payment Attempt creation call — the
Donation-creation step (Section 8) itself never calls this endpoint and never redirects straight
to a provider; the browser visits this page as an intermediate, donor-driven step, preserving
"Donation != Payment" as two distinct, separately-authorized actions.

## 10. Scope F — Admin Navigation

No new permission, role, or authority. Navigation entries are added to
`resources/js/Components/Admin/AdminLayout.vue`'s existing `navGroups` array only — every link
still lands on an existing, already-Policy-guarded backend route (see the file's own comment:
"this sidebar grants nothing by itself"). Exact routes (Section 4.6), added as two new groups
after `Fundraising`:

```ts
{ label: 'Donations', items: [
    { label: 'Donations', href: '/admin/donation/donations', icon: 'heart', match: '/admin/donation/donations' },
] },
{ label: 'Payments', items: [
    { label: 'Payments', href: '/admin/payment/payments', icon: 'creditCard', match: '/admin/payment/payments' },
    { label: 'Provider Config', href: '/admin/payment/provider-config', icon: 'settings', match: '/admin/payment/provider-config' },
] },
```

Icon names (`heart`, `creditCard`, `settings`) must be verified against
`resources/js/Components/UI/Icon.vue`'s existing icon set during Qwen recon; if a name is
missing, substitute the nearest existing icon rather than adding a new icon dependency (icons are
presentation detail, not a business decision — no OHD needed).

Recurring-plan admin actions and Manual Transfer approve/reject/hold remain reachable only from
their owning Donation/Payment show pages (no index route exists for either) — no sidebar entry is
added for them, per Section 4.6's finding; this is not a gap, it matches the backend's own route
shape.

## 11. Scope G — Super Admin Experience

The bootstrapped Super Admin (Principal 11, canonical `super_admin` Role at `GLOBAL_PLATFORM`
scope — see prior PRE-IMP-010 checkpoint closure) uses the same `AdminLayout.vue` every other
backoffice user uses. No separate Super Admin theme, layout, or navigation set is created — Admin/
Super Admin remains one fixed backoffice interface (locked, AGENTS.md). This checkpoint's Scope F
navigation additions are the only additions Super Admin sees beyond any other appropriately-scoped
admin user; visibility of a nav entry still depends on nothing but the same Policy/RBAC gate the
underlying route already enforces (UI visibility != authorization, restated in Section 13). No
`audit.*`, Financial, Business, or Approval Authority is granted by this checkpoint, by frontend
behavior or otherwise — none of Scope F's changes touch `permissions`, `roles`, or
`principal_role_assignments`.

## 12. Scope H — Responsive Baseline (acceptance criteria)

No visual redesign required. Minimum functional-usability acceptance, at 375px (mobile) and
1280px (desktop) viewport widths, using existing Tailwind breakpoints (`sm`/`lg`, already in use
throughout the codebase):

```
[ ] PublicHeader: logo + nav visible at desktop width; mobile shows toggle + working
    disclosure panel (reuses ThemeRender's existing pattern — no new breakpoint behavior)
[ ] PublicFooter: renders without horizontal overflow at both widths
[ ] Home (ThemeRender): all existing section/component types remain usable at both widths
    (already Tailwind-responsive per Section 4.2 read — verify no regression from header/footer
    extraction)
[ ] CampaignIndex / CampaignShow / ProgramShow: grid/card layouts remain usable at both widths
    (already responsive per existing `sm:grid-cols-*` classes — verify no regression from shell
    adoption)
[ ] DonationCreate: form fields stack correctly at mobile width (existing `sm:grid-cols-2` pattern)
[ ] PaymentCreate / PaymentShow (new): provider selector and evidence form usable at both widths
[ ] AdminLayout sidebar: existing mobile drawer / desktop fixed-sidebar behavior unaffected by
    the two new nav groups (verify no overflow/scroll regression with the added entries)
[ ] Forms/validation/error states: error messages remain visible and associated with their field
    at both widths (no truncation/overlap)
```

No new responsive framework, breakpoint system, or CSS methodology is introduced.

## 13. Scope I — Accessibility Baseline (acceptance criteria)

Practical, stack-consistent (Tailwind + plain HTML semantics), no new accessibility framework:

```
[ ] PublicHeader nav is a <nav> landmark; mobile toggle button has aria-label + aria-expanded
    (ThemeRender's existing toggle already does this — preserve verbatim in extraction)
[ ] Focus is visible on all interactive elements (links, buttons, form fields) — verify Tailwind's
    default/`focus-visible` outline is not suppressed by any new component's classes
[ ] All form inputs (DonationCreate, PaymentCreate, evidence upload) have an associated <label>
    with a matching for/id pair (DonationCreate already does this — match the pattern)
[ ] Validation errors are associated with their field (e.g. aria-describedby or adjacent <p> with
    a stable id) — DonationCreate currently renders errors as a plain adjacent <p> with no
    aria-describedby link; this checkpoint SHOULD add the association for new/touched forms
    (DonationCreate, PaymentCreate, evidence form) as a minimum accessibility improvement, not a
    redesign
[ ] Every <img> (logo, campaign cover, program cover) has a meaningful `alt` (already true in all
    files read in Section 4.2 — preserve)
[ ] Buttons that navigate use <a>/<Link>; buttons that submit/act use <button type="button|submit">
    (already the existing pattern — preserve)
[ ] AdminLayout sidebar mobile drawer: verify the existing overlay/close-on-click pattern remains
    keyboard-dismissible (Escape) — if not already true, add a minimal keydown handler; this is a
    small, existing-component fix, not a new pattern
[ ] Color contrast: reuse existing Tailwind color tokens (stone/emerald/slate/amber already used
    throughout) — no new palette introduced, so no new contrast audit surface
```

## 14. Financial Domains (restated boundary)

`PaymentShow.vue` and `PaymentCreate.vue` (Scope E) display and submit only fields already defined
by the existing, unmodified `publicPayload()` / `StorePaymentRequest` / `StoreEvidenceRequest`
contracts. No page in this checkpoint computes, stores, or displays a Ledger balance, Journal
entry, double-entry line, Fund balance, Withdrawal amount, Refund amount, or Distribution amount —
none of those concepts exist in any file touched by this specification. The locked flow (Business
Event → Domain Validation → Business State Transition → Authorized Financial Consequence →
Registry → Ledger Posting → Journal → Double-Entry Entries) is entirely unaffected; this checkpoint
never reaches past "Business State Transition" in the UI layer, it only renders read-model output
already produced by IMP-008/IMP-009 services.

## 15. Security (restated boundary)

No file in this specification's file map (Section 18) touches: `app/Policies/*`,
`app/Services/Rbac/*`, any migration, any middleware, or any authorization gate. Admin nav
additions (Scope F) are pure link additions to an already-generic component; every added link's
target route already enforces its own Policy (verified in Section 4.6 — routes are inside the
existing `auth`/`identity.active` middleware group, per `routes/web.php` structure read during
IMP-009/PRE-IMP-010 recon). Hidden vs. visible UI is explicitly not treated as a security boundary
anywhere in this document.

## 16. Shared Hosting (restated boundary)

All new files are `.vue` (compiled by the existing Vite pipeline) or minor PHP controller/service
methods (no new package, no new PHP extension, no new service process). `npm run build` remains
the only build step; no Node runtime, Redis, Supervisor, PM2, WebSocket server, or Docker
dependency is introduced.

## 17. Evidence Contract (checkpoint closure)

Per GOV-FRONTEND-001, the implementation stage must report:

```
[ ] FRONTEND STATUS        — build + type-check pass/fail summary
[ ] HOME STATUS             — confirm ThemeRender.vue renders unchanged content correctly
                               post-Header/Footer-extraction (before/after screenshot)
[ ] HEADER                  — PublicHeader.vue screenshot on >=2 public pages (desktop + mobile)
[ ] FOOTER                  — PublicFooter.vue screenshot on >=2 public pages
[ ] NEW USER-FACING UI      — PaymentCreate.vue + PaymentShow.vue screenshots (desktop + mobile),
                               covering at least the manual_transfer evidence-upload state
[ ] NEW ADMIN UI            — AdminLayout.vue sidebar screenshot showing the new Donations/
                               Payments groups
[ ] MOBILE CHECK            — Section 12 checklist, all items checked, at 375px
[ ] DESKTOP CHECK           — Section 12 checklist, all items checked, at 1280px
[ ] BUILD STATUS            — `npm run build` output (pass/fail, no new warnings introduced)
[ ] TYPE-CHECK STATUS       — `npm run type-check` output (pass/fail)
[ ] SCREENSHOT / EVIDENCE   — all screenshots above, using existing development data only
                               (no seeded/fabricated financial records — an empty Payment
                               list/Donations list is an acceptable, honest screenshot)
[ ] DEFERRED UI             — explicit list of anything visually deferred (e.g. provider-specific
                               `instructions` rendering may stay generic/JSON-shaped rather than a
                               bespoke layout per provider, if time-boxed — must be declared, not
                               silently dropped)
```

No demo Donation/Payment/Fund/Ledger data may be seeded to make a screenshot look populated.

## 18. File Ownership Plan

```
CREATE  resources/js/Components/Public/PublicHeader.vue
        Owner: Muse (Implementation Write Owner)
        Purpose: reusable public header, theme-data-driven only.
        Delta: new file; markup extracted verbatim from ThemeRender.vue's existing <header>.

CREATE  resources/js/Components/Public/PublicFooter.vue
        Owner: Muse
        Purpose: reusable public footer.
        Delta: new file; markup extracted verbatim from ThemeRender.vue's existing <footer>.

CREATE  resources/js/Components/Public/PublicShell.vue
        Owner: Muse
        Purpose: shared page container (brand CSS vars + header/footer slots).
        Delta: new file; thin wrapper, no business logic.

CREATE  resources/js/Pages/Public/PaymentShow.vue
        Owner: Muse
        Purpose: render existing publicPayload() contract; manual-transfer evidence form.
        Delta: new file; no new backend field consumed.

CREATE  resources/js/Pages/Public/PaymentCreate.vue
        Owner: Muse
        Purpose: provider selector, posts to existing POST /donations/{donation}/payments.
        Delta: new file; fields limited to existing StorePaymentRequest shape.

MODIFY  resources/js/Pages/Public/ThemeRender.vue
        Owner: Muse
        Purpose: use PublicHeader/PublicFooter instead of inline markup.
        Delta: behavior-preserving extraction only; primaryNav computation stays in this file.

MODIFY  resources/js/Pages/Public/CampaignIndex.vue
        Owner: Muse
        Purpose: adopt PublicShell/Header/Footer via new `chrome` prop; remove static header.
        Delta: shell/header swap only; card grid markup unchanged.

MODIFY  resources/js/Pages/Public/CampaignShow.vue
        Owner: Muse
        Purpose: adopt PublicShell/Header/Footer; replace stale Donation placeholder with real
                 link to /campaigns/{slug}/donate using existing is_donation_eligible prop.
        Delta: shell/header swap + placeholder-to-real-link swap; no new prop invented.

MODIFY  resources/js/Pages/Public/ProgramShow.vue
        Owner: Muse
        Purpose: adopt PublicShell/Header/Footer via new `chrome` prop.
        Delta: shell/header swap only.

MODIFY  resources/js/Pages/Public/DonationCreate.vue
        Owner: Muse
        Purpose: adopt PublicShell/Header/Footer via new `chrome` prop; add back-to-campaign link.
        Delta: shell/header addition + one link; form fields/logic unchanged.

MODIFY  resources/js/Components/Admin/AdminLayout.vue
        Owner: Muse
        Purpose: add Donations/Payments navGroups (Section 10).
        Delta: array literal addition only; no template/logic change.

MODIFY  app/Services/Theme/PublicRenderer.php
        Owner: Muse (mission-critical? NO — presentation-only; ordinary Implementation Write
               Owner, per MULTI-MODEL-OWNERSHIP.md's carve-out being limited to payment/ledger/
               financial-posting/commission/withdrawal/refund/security-hardening stages)
        Purpose: add public siteChrome() reusing existing private branding/nav-slot logic.
        Delta: one new public method + one small extracted private helper; no change to
               renderForContentKind()'s existing output shape or behavior.

MODIFY  app/Http/Controllers/PublicCampaignController.php
        Owner: Muse
        Purpose: replace duplicated brandingPayload() with PublicRenderer::siteChrome().
        Delta: index()/show() prop source swap; response shape gains `navigation`, keeps
               existing `branding` shape unchanged for backward compatibility.

MODIFY  app/Http/Controllers/PublicProgramController.php
        Owner: Muse
        Purpose: same as above.
        Delta: show() prop source swap.

MODIFY  app/Http/Controllers/PublicDonationController.php
        Owner: Muse
        Purpose: add `chrome` prop to create(); per HD-FE009-01, change store()'s success
                 redirect target from public.campaigns.show to the new PaymentCreate route
                 (Section 8) when payment initiation is eligible for the created Donation.
        Delta: create() prop addition; store() redirect-target change only — no Payment
               Attempt creation call, no provider redirect, added to this method.

MODIFY  app/Http/Controllers/PublicPaymentController.php
        Owner: Muse
        Purpose: per HD-FE009-02, show() content-negotiates: Inertia page for a browser
                 navigation, existing JSON response (unchanged contract) for an explicit JSON
                 request — both branches call the same existing publicPayload(). storeEvidence()
                 success redirect continues to public.payments.show, now a real page for browsers.
        Delta: show() gains a request-type branch calling the SAME existing publicPayload();
               no change to store()/storeEvidence() business logic, validation, or authorization
               checks; no new route, no /api/v1/* surface (IMP-025 remains unaffected/separately
               owned).

READ-ONLY REFERENCE (consulted, not modified):
        routes/web.php
        resources/js/Pages/Public/NotFound.vue
        app/Http/Requests/Payment/StorePaymentRequest.php
        app/Http/Requests/ManualTransfer/StoreEvidenceRequest.php
        app/Models/Payment/Payment.php
        resources/js/Components/UI/Icon.vue
        docs/implementation/IMP-006-theme-engine.md
        docs/implementation/IMP-007-campaign-program-fund.md
        docs/implementation/IMP-008-donation.md
        docs/implementation/IMP-009-payment-hub.md
```

Total: 5 CREATE, 10 MODIFY, all frontend-integration-scoped; no migration, no seeder, no Policy
file, no RBAC file.

## 19. Test Strategy

Minimum sufficient, per [DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md):

```
Targeted (during implementation):
[ ] npm run type-check                          — must pass, zero new errors
[ ] npm run build                                — must pass, no new warnings
[ ] Existing Feature tests touching modified controllers/routes (PublicCampaignController,
    PublicProgramController, PublicDonationController, PublicPaymentController) — run and
    confirm still green; these controllers' business assertions (eligibility, idempotency,
    ownership, session-possession) must be unaffected by prop/response-shape additions
[ ] New Feature test: GET a Payment show page as (a) the owning authenticated donor and
    (b) a guest with valid session possession — asserts an Inertia page renders (not raw JSON)
    when navigated as a browser (HD-FE009-02)
[ ] New Feature test: CampaignShow with is_donation_eligible=true renders a real link to
    the donate route (regression guard against the stale-placeholder gap recurring)
[ ] New Feature test (HD-FE009-01, item 1): a successful browser Donation-creation request is
    redirected to the canonical Payment Creation / Selection UI route when payment initiation is
    eligible for that Donation
[ ] New Feature test (HD-FE009-01, item 2): asserts that PublicDonationController::store() creates
    exactly the Donation row and NOTHING else — zero Payment/Payment-Attempt rows exist
    immediately after a successful Donation-creation request, before any PaymentCreate action
[ ] New Feature test (HD-FE009-02, item 3): an HTML/browser request to the Payment show route
    returns the Inertia PaymentShow page (X-Inertia response / Inertia page component assertion)
[ ] New Feature test (HD-FE009-02, item 4): an explicit JSON request (Accept: application/json)
    to the same Payment show route returns the existing, unchanged JSON contract
    (ulid/status/provider/amount_minor/currency/instructions/created_at)
[ ] New Feature test (HD-FE009-02, item 5): both the HTML and JSON responses for the same Payment
    are asserted to contain identical canonical field values (same underlying publicPayload()
    call, no divergent projection)
[ ] Existing guest-security regression (item 6): re-run the existing session-possession/ownership
    Feature tests for PublicPaymentController::show()/storeEvidence() unchanged — a guest without
    valid session possession still receives 404 on both the HTML and JSON branches; an
    authenticated non-owner still receives 404 via the existing Policy check
```

```
Before Codex final closure (mandatory full regression, per workflow):
[ ] Full PHPUnit suite against real MySQL (never SQLite for Donation/Payment/RBAC semantics,
    per this repository's established pattern) — no destructive action against the development
    database; use the existing test database configuration
[ ] npm run type-check + npm run build, clean
[ ] Manual smoke: Home → Campaign → Donate → choose payment method → Payment status page,
    end-to-end, using existing development records only
```

Do not run the full historical suite speculatively before implementation begins — targeted tests
first, full regression only ahead of Codex's audit, per workflow.

## 20. Human Decisions

**OPEN HUMAN DECISIONS: 0** — both decisions below are resolved and APPROVED.

```
HD-FE009-01 — RESOLVED / APPROVED
Question resolved:  After a Donation is successfully created, where does the donor's browser go?
Decision:  Proceed to the canonical Payment Creation / Selection UI for that Donation, when
           payment initiation is available/eligible:
             Campaign -> Donation Creation -> successful Donation -> Payment Creation/Selection UI
             -> canonical Payment Attempt creation -> provider-specific flow
Binding constraints (restated as implementation MUSTs, see Sections 8-9, 18):
  - Donation creation MUST NOT automatically create a Payment Attempt.
  - Donation creation MUST NOT redirect directly to an external payment provider.
  - Payment creation remains exclusively owned by the canonical IMP-009 Payment Hub
    (PublicPaymentController::store(), invoked only by an explicit donor action on
    PaymentCreate.vue).
  - All existing IMP-009 rules remain fully authoritative: one ACTIVE Payment Attempt per
    Donation, provider boundaries, Manual Transfer behavior, Payment expiry, amount validation,
    idempotency, guest security, recurring MANUAL-PER-CYCLE behavior.
  - Donation != Payment is preserved: two distinct, separately-authorized actions, never merged
    into one call.
Implementation impact: PublicDonationController::store()'s redirect target changes (Section 18);
           new PaymentCreate.vue page (Section 9); test coverage added (Section 19).

HD-FE009-02 — RESOLVED / APPROVED
Question resolved:  Does PublicPaymentController::show() keep its existing JSON response, or
           does the new Inertia page replace it outright?
Decision:  Preserve the existing JSON compatibility path for requests that explicitly request
           JSON. Browser/HTML requests render the new Inertia PaymentShow surface instead:
             HTML/browser request       -> Inertia -> PaymentShow.vue
             Explicit JSON request      -> existing canonical JSON representation (unchanged)
Binding constraints (restated as implementation MUSTs, see Section 9, 18):
  - Both paths MUST derive from the same canonical existing Payment projection/payload
    (publicPayload()) — no duplicated or divergent business logic between the two branches.
  - This dispatch MUST NOT be treated as, or grow into, a new API architecture — it is a
    presentation-layer branch inside the existing show() method and route, nothing more.
  - This is explicitly NOT the implementation of IMP-025 (REST API). /api/v1/* remains
    separately owned by IMP-025 and is untouched by this checkpoint.
Implementation impact: PublicPaymentController::show() gains a request-type branch (Section 9,
           18); test coverage for both branches plus cross-branch payload equivalence added
           (Section 19).
```

## 21. Spec Quality Gate (self-check)

```
[x] does not reopen IMP-005..009 semantics — restated boundaries in Sections 7-9, 14
[x] does not implement IMP-010 — Section 3, Section 14 explicit exclusion
[x] preserves Theme Engine — Section 5 "Theme Engine boundary (restated)"
[x] preserves authorization — Section 15
[x] preserves shared-hosting compatibility — Section 16
[x] defines reusable public composition — Section 5
[x] defines Home integration — Section 6
[x] defines Donation/Payment integration — Sections 8-9
[x] defines Admin discoverability — Section 10
[x] defines responsive acceptance — Section 12
[x] defines accessibility acceptance — Section 13
[x] defines evidence — Section 17
[x] defines exact file-map boundary — Section 18
[x] defines tests — Section 19
[x] contains no fabricated business behavior — Sections 3, 14, 17
[x] Donation != Payment preserved — Sections 8, 9, 14, HD-FE009-01 (Section 20)
[x] Payment != Ledger preserved — Section 14
[x] no automatic Payment creation introduced — HD-FE009-01 binding constraint (Section 20)
[x] no direct Donation -> provider shortcut introduced — HD-FE009-01 binding constraint (Section 20)
[x] JSON compatibility path does not create a second business-logic path — HD-FE009-02 binding
    constraint (Section 20): both branches call the same publicPayload()
[x] IMP-025 (REST API / /api/v1/*) ownership preserved, not implemented here — HD-FE009-02
    binding constraint (Section 20)
[x] open human decisions = 0 — Section 20
```

## 22. Workflow After Spec Approval

Per [IMPLEMENTATION-GOVERNANCE.md](../00-governance/IMPLEMENTATION-GOVERNANCE.md) Amendment V3:
Qwen 3.7 Flash (governed recon confirming this file map against live HEAD) → Muse Spark 1.3
Contributor (implementation) → Automated Tests → DeepSeek V4.1 Flash (independent diff-first
review) → targeted remediation (Muse for ordinary defects; Claude Code for any architecture/
security/contract issue) → re-test → Codex (final semantic/closure audit, full regression per
Section 19) → Human Stage Gate. No agent self-approves.
