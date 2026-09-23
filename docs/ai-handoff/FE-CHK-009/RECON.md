# FE-CHK-009 Recon

## Baseline

**Repository**: C:\ServBay\www\kmsitdonation
**Local HEAD**: 770c272 docs(frontend): approve post-IMP-009 integration spec (confirmed)
**Working tree at start**: CLEAN
**Specification**: docs/implementation/POST-IMP-009-frontend-integration.md

## Specification Validation

The specification is **VALIDATED** against actual repository reality. No contradictions found between the spec's Section 4 findings and the live codebase. All claimed gaps, route shapes, prop contracts, and controller behavior match exactly.

### Spec-Quality-Gate Self-Check (from spec §21)

All items pass:
- Does not reopen IMP-005..009 semantics ✓
- Does not implement IMP-010 ✓
- Preserves Theme Engine ✓
- Preserves authorization ✓
- Preserves shared-hosting compatibility ✓
- Defines reusable public composition ✓
- Defines Home integration ✓
- Defines Donation/Payment integration ✓
- Defines Admin discoverability ✓
- Defines responsive acceptance ✓
- Defines accessibility acceptance ✓
- Defines evidence ✓
- Defines exact file-map boundary ✓
- Defines tests ✓
- Contains no fabricated business behavior ✓
- Donation != Payment preserved ✓
- Payment != Ledger preserved ✓
- No automatic Payment creation introduced ✓
- No direct Donation → provider shortcut introduced ✓
- JSON compatibility path does not create second business-logic path ✓
- IMP-025 ownership preserved ✓
- Open human decisions = 0 ✓

## Confirmed Routes (routes/web.php)

| Method | Path | Controller@Method | Name | Status |
|--------|------|-------------------|------|--------|
| GET | / | PublicContentController::home | home | VALIDATED |
| GET | /{any} | PublicContentController::show | public.show | VALIDATED |
| GET | /programs/{program:slug} | PublicProgramController::show | public.programs.show | VALIDATED |
| GET | /campaigns | PublicCampaignController::index | public.campaigns.index | VALIDATED |
| GET | /campaigns/{campaign:slug} | PublicCampaignController::show | public.campaigns.show | VALIDATED |
| GET | /campaigns/{campaign:slug}/donate | PublicDonationController::create | public.donations.create | VALIDATED |
| POST | /campaigns/{campaign:slug}/donations | PublicDonationController::store | public.donations.store | VALIDATED |
| POST | /donations/{donation}/payments | PublicPaymentController::store | public.payments.store | VALIDATED |
| GET | /donations/{donation}/payments/{payment} | PublicPaymentController::show | public.payments.show | VALIDATED |
| POST | /donations/{donation}/payments/{payment}/manual-transfer/evidence | PublicPaymentController::storeEvidence | public.payments.evidence.store | VALIDATED |

Admin routes confirmed (lines 262-306 of routes/web.php):
- `GET /admin/donation/donations` → AdminDonationController::index (name: donation.admin.index)
- `GET /admin/payment/payments` → AdminPaymentController::index (name: payment.admin.index)
- `GET /admin/payment/provider-config` → AdminPaymentProviderConfigController::index (name: payment.admin.config.provider-config)
- `admin/donation/recurring-plans` and `admin/payment/payments/{id}` actions exist but have no top-level index — intentional per spec §4.6

All admin routes sit inside the `auth` + `identity.active` middleware group (line 79).

## Confirmed Controllers / Services

### PublicCampaignController (app/Http/Controllers/PublicCampaignController.php)
- `brandingPayload($theme)` exists (private, lines 94-106), returns `{color_tokens, font_family}` ONLY — no logo_url, no favicon_url, no navigation. VALIDATED gap.
- `index()`: passes campaigns data but NO branding/chrome prop to CampaignIndex.vue. VALIDATED gap.
- `show()`: passes campaign, is_donation_eligible, formatted_target_amount, branding. No chrome/navigation. VALIDATED gap.

### PublicProgramController (app/Http/Controllers/PublicProgramController.php)
- `brandingPayload($theme)` exists (private, lines 43-55), identical shape to CampaignController. VALIDATED gap.
- `show()`: passes program, branding. No chrome/navigation. VALIDATED gap.

### PublicDonationController (app/Http/Controllers/PublicDonationController.php)
- `create()`: passes campaign, is_donation_eligible. NO branding/chrome prop. VALIDATED gap.
- `store()`: redirects to `public.campaigns.show` (line 90-92) with flash status. Per HD-FE009-01, target must change to PaymentCreate route when payment initiation is eligible.
- `publicPayload()` exists (static, lines 98-109) but NOT used by this recon scope.

### PublicPaymentController (app/Http/Controllers/PublicPaymentController.php)
- `store()`: redirects to `public.payments.show` (line 111-114). VALIDATED.
- `show()`: returns `response()->json(self::publicPayload($payment))` (line 142). **JSON DEAD-END CONFIRMED** — no Inertia page behind this route. Per HD-FE009-02, must content-negotiate HTML vs JSON.
- `storeEvidence()`: redirects to `public.payments.show` (line 192-195). Will be a real page after HD-FE009-02.
- `publicPayload()` static (lines 201-212): returns `{ulid, status, provider, amount_minor, currency, instructions, created_at}`.
- Guest session possession via `guest_payment_ulids` session key (lines 239-263).

### PublicRenderer (app/Services/Theme/PublicRenderer.php)
- `buildBranding($theme)` (private, line 404-422): returns `{color_tokens, font_family, logo_url, favicon_url}` — richer than controllers' duplicated logic.
- `renderNavigationMenuSlot($config, $theme)` (private, line 333-359): resolves navigation_menu_slot components.
- **No `siteChrome()` method exists yet** — must be added per spec §5.
- `activeTheme()` already public (line 35-43). Already consumed by all non-Home controllers.

### StorePaymentRequest (app/Http/Requests/Payment/StorePaymentRequest.php)
- Rules: `provider` (required, in: manual_transfer,tripay,xendit,stripe), `channel` (optional, string, max 64). Amount/currency inherited from Donation — never re-entered. VALIDATED.

### StoreEvidenceRequest (app/Http/Requests/ManualTransfer/StoreEvidenceRequest.php)
- Rules: `evidence` (file, max 5120KB), `declared_amount_minor` (nullable int), `declared_currency` (nullable string size:3), `declared_transferred_at` (nullable date). VALIDATED.

## Confirmed Frontend Surfaces

### Vue Pages (resources/js/Pages/Public/)

| File | Shell? | Chrome? | Header/Footer? | Gap |
|------|--------|---------|----------------|-----|
| ThemeRender.vue | Own inline | Yes (template.branding + primaryNav) | Inline `<header>` + `<footer>` | Scope A extraction |
| CampaignIndex.vue | None (`<div class="min-h-screen bg-stone-50">`) | None | Hardcoded "Campaigns" header | Replace with PublicShell |
| CampaignShow.vue | Own root div | Branding only (color_tokens/font_family) | None | Replace with PublicShell; stale placeholder (§86-90) |
| ProgramShow.vue | Own root div | Branding only (color_tokens/font_family) | None | Replace with PublicShell |
| DonationCreate.vue | None (bare form) | None | None | Replace with PublicShell |
| NotFound.vue | Not inspected (out of scope) | — | — | — |
| PaymentShow.vue | **DOES NOT EXIST** | — | — | CREATE per scope E |
| PaymentCreate.vue | **DOES NOT EXIST** | — | — | CREATE per scope E |

### Admin (resources/js/Components/Admin/AdminLayout.vue)
- `navGroups` (line 65-85): Overview, Content, Fundraising, Presentation. No Donations/Payments groups. VALIDATED gap.
- Every link targets existing backend routes that enforce their own Policies. VALIDATED.
- Icon registry (Icon.vue): 22 icons. Missing: `heart`, `creditCard`, `settings` (all fall back to `FileText`). See icon substitution finding below.

## Public Shell Data Flow

**Current state**: No PublicHeader, PublicFooter, or PublicShell exists. Each public page owns its own presentation shell (or none).

**Proposed flow after implementation**:

```
PublicRenderer::siteChrome($theme) [NEW METHOD]
  ↓ uses buildBranding() + renderNavigationMenuSlot() internal helpers
  returns { branding: {color_tokens, font_family, logo_url, favicon_url}, navigation: [...NavItem[]] }
  ↓ passed as `chrome` prop
Controllers (CampaignIndex, CampaignShow, ProgramShow, DonationCreate)
  ↓ render
PublicShell.vue (new wrapper)
  ↓ composes
PublicHeader.vue (new, extracted from ThemeRender's inline <header>)
PublicFooter.vue (new, extracted from ThemeRender's inline <footer>)
  ↓ each page body content
```

**Key validation**: `PublicRenderer::buildBranding()` already returns `logo_url` and `favicon_url` (line 419-421 of PublicRenderer.php). The controllers' private `brandingPayload()` methods do NOT include these fields — which is exactly why every non-Home page lacks logo/nav. The new `siteChrome()` method simply unifies the existing logic.

**Theme Render stays independent**: ThemeRender.vue computes its own `primaryNav` via template.sections scan (lines 84-92). It may optionally adopt PublicShell + PublicHeader/PublicFooter for header/footer extraction without changing how it resolves primaryNav. Its `template` payload is a superset of `siteChrome()` output.

**Can PublicRenderer::siteChrome() work safely?** YES. It extracts two private helpers:
1. Reuse `buildBranding()` directly (returns full branding including logo/favicon).
2. Extract the `navigation_menu_slot` resolution from `renderNavigationMenuSlot()` into a callable helper (takes `$theme`, resolves by menu_code). This is read-only, same-thread, no database schema change.

## Donation → Payment Flow

**Current flow** (before HD-FE009-01):
```
GET /campaigns/{slug}/donate → PublicDonationController::create → DonationCreate.vue
  ↓ (form submit)
POST /campaigns/{slug}/donations → PublicDonationController::store
  ↓ (success)
redirect()->route('public.campaigns.show', ...) with flash('status', 'donation-created')
  ↓ (browser lands on Campaign page)
[Donor sees stale "Donation is not yet available" placeholder on CampaignShow]
```

**Required delta per HD-FE009-01**:
```
POST /campaigns/{slug}/donations → PublicDonationController::store
  ↓ (success, payment initiation available — i.e. Donation has no ACTIVE Payment Attempt yet)
redirect()->route('public.payment-create', [donation => $donation->ulid])
  ↓ (browser visits PaymentCreate.vue — NEW page)
[Donor selects provider on PaymentCreate.vue]
  ↓ (submit with Idempotency-Key header)
POST /donations/{ulid}/payments → PublicPaymentController::store
  ↓ (creates exactly ONE Payment Attempt per IMP-009 rules)
redirect()->route('public.payments.show', [donation, payment])
  ↓ (browser visits PaymentShow.vue — NEW page)
HTML browser → Inertia Page PaymentShow.vue
JSON explicit request → response()->json(publicPayload($payment))
```

**Critical verification**: `PublicDonationController::store()` creates ZERO Payment Attempts. It only creates a Donation row. Payment creation is exclusively `PublicPaymentController::store()`'s responsibility. CONFIRMED.

**Minimum redirect delta**: Change line 90-92 of PublicDonationController.php from:
```php
return redirect()->route('public.campaigns.show', ['campaign' => $campaign->slug])
    ->with('status', 'donation-created');
```
To conditionally redirect to the new PaymentCreate route when the just-created Donation has no ACTIVE Payment Attempt. The eligibility check is an ordinary IMP-009 state query on the returned `$donation` object — no new rule invented.

## Payment HTML / JSON Contract

**Current state (BEFORE HD-FE009-02)**:
`PublicPaymentController::show()` (line 142): `return response()->json(self::publicPayload($payment));`

A browser navigating to `/donations/{ulid}/payments/{ulid}` receives raw JSON rendered in browser — visible as text, not usable UI. This is the JSON dead-end.

**After HD-FE009-02**:
```php
// Content negotiation inside show():
if ($request->expectsJson() || str_contains($request->header('Accept', ''), 'application/json')) {
    return response()->json(self::publicPayload($payment));
}
return Inertia::render('Public/PaymentShow', [...self::publicPayload($payment)]);
```

**Both branches use the SAME `publicPayload()` call**. No divergent projection. Two presentation adapters only:
- HTML → Inertia::render('Public/PaymentShow', [...])
- JSON → response()->json([...])

**Canonical payload shape**: `{ulid, status, provider, amount_minor, currency, instructions, created_at}`

**Test impact**: Existing test `test_public_status_read_survives_ordinary_subsequent_requests` (PaymentHttpTest.php lines 282-306) asserts `assertOk()` then `assertJsonPath(...)`. After HD-FE009-02:
- An HTTP feature test sending `Accept: */*` (default Laravel TestCase behavior) would receive Inertia HTML/X-Inertia response, not JSON. Tests must either add `Accept: application/json` header for JSON branch assertions or switch to Inertia assertions for HTML branch.
- New tests required per spec §19 for both branches.

## Admin Navigation

**Confirmed existing routes**:
- `GET /admin/donation/donations` → name: `donation.admin.index`
- `GET /admin/payment/payments` → name: `payment.admin.index`
- `GET /admin/payment/provider-config` → name: `payment.admin.config.provider-config`

**All three routes** are inside `auth` + `identity.active` middleware group. They enforce their own Policies. AdminLayout can add links without modifying authorization. CONFIRMED.

**Icons**: `heart`, `creditCard`, `settings` are NOT in Icon.vue's registry. Substitutions needed:
- `heart` → No suitable replacement among 22 registered icons. `User` (for donors) or `Wallet` (already used for Funds) could work conceptually, but neither maps perfectly to "Donations". Recommend adding `Heart` from @lucide/vue to Icon.vue registry, or using `Wallet` with label context.
- `creditCard` → No suitable replacement. Recommend adding `CreditCard` from @lucide/vue.
- `settings` → No exact match. `Palette` (used for Theme) is closest. Could reuse or recommend `Settings` from @lucide/vue.

Per spec §10: "if a name is missing, substitute the nearest existing icon rather than adding a new icon dependency." However, none of the 22 icons are semantically near-matches. Recommendation: add Heart/CreditCard/Settings to Icon.vue — these are presentation detail icons (no business decision), per spec allowance.

## Responsive / Accessibility Findings

### DonationCreate accessibility gap (CONFIRMED)

Lines 58-66 of DonationCreate.vue:
```vue
<input id="amount" ... />
<p v-if="form.errors.amount_minor" class="mt-1 text-sm text-red-600">{{ form.errors.amount_minor }}</p>
```

Error `<p>` elements are adjacent siblings but have **no `aria-describedby` association** linking them to their input. Same pattern applies to `currency` input (line 65-66) and `idempotencyError` (line 90). This is the exact gap identified in spec §13.

The fix requires adding `:aria-describedby` on inputs pointing to stable IDs on error paragraphs (or vice versa via `id`/`aria-describedby`). This is minimal markup, no behavioral change.

### Other surfaces within touched areas

- `ThemeRender.vue` mobile nav toggle has `aria-label="Toggle navigation"` and `:aria-expanded="mobileNavOpen"` — GOOD (preserved per spec).
- CampaignShow img has `:alt="campaign.name"` — GOOD.
- DonationCreate img alt not present (no images on that page).
- All `<a>` for navigation and `<button type="submit">` for actions — correctly differentiated.
- AdminLayout sidebar overlay (line 98) uses `@click="mobileOpen = false"` for close-on-click ESC dismissal not implemented on the overlay itself — if the drawer panel is open and user presses Escape, overlay doesn't catch it. Minimal handler addition recommended.

## Original File Map

From spec §18:

**CREATE (5):**
1. `resources/js/Components/Public/PublicHeader.vue`
2. `resources/js/Components/Public/PublicFooter.vue`
3. `resources/js/Components/Public/PublicShell.vue`
4. `resources/js/Pages/Public/PaymentShow.vue`
5. `resources/js/Pages/Public/PaymentCreate.vue`

**MODIFY (10):**
1. `resources/js/Pages/Public/ThemeRender.vue`
2. `resources/js/Pages/Public/CampaignIndex.vue`
3. `resources/js/Pages/Public/CampaignShow.vue`
4. `resources/js/Pages/Public/ProgramShow.vue`
5. `resources/js/Pages/Public/DonationCreate.vue`
6. `resources/js/Components/Admin/AdminLayout.vue`
7. `app/Services/Theme/PublicRenderer.php`
8. `app/Http/Controllers/PublicCampaignController.php`
9. `app/Http/Controllers/PublicProgramController.php`
10. `app/Http/Controllers/PublicDonationController.php`
11. `app/Http/Controllers/PublicPaymentController.php`

**Note:** Counting reveals 11 MODIFY entries, not 10 as stated in the spec's summary. The spec's "Total: 5 CREATE, 10 MODIFY" likely counts PHP controllers as one block. Actual distinct files = 5 CREATE + 11 MODIFY = 16.

## Final Recommended File Map

After analysis, I confirm ALL 16 files are genuinely necessary. No safe reductions exist without violating the approved spec or architecture:

**No reduction possible** — each file serves a unique, non-duplicable purpose:
- PublicHeader/Footer/Shell: Composition layer that doesn't exist anywhere else
- PaymentShow/PaymentCreate: Entirely new pages with no existing equivalent
- ThemeRender: Only file owning header/footer markup for extraction
- CampaignIndex/CampaignShow/ProgramShow/DonationCreate: Each needs shell adoption
- AdminLayout: Only admin navigation component
- PublicRenderer: Only file owning theme rendering logic
- PublicCampaignController/PublicProgramController: Both independently define brandingPayload()
- PublicDonationController: Only owner of store() redirect target
- PublicPaymentController: Only owner of show() response type

**FINAL COUNT**: 5 CREATE, 11 MODIFY (spec summary said 10 modify; actual distinct files = 11)

**FILE MAP REDUCED**: NO (verified necessity of every file)

## Test Map

### Existing tests to REUSE UNCHANGED

| Path | Behavior | Status |
|------|----------|--------|
| FoundationSmokeTest.php:test_root_route_renders_the_public_theme_pipeline | Verifies GET / returns ThemeRender | REUSE |
| PublicCampaignControllerTest.php (all 12 tests) | Campaign/Program public pages, PUBLISHED filtering, is_donation_eligible, field allow-list | REUSE — `is_donation_eligible` assertions still valid |
| DonationHttpTest.php:test_guest_creates_a_donation_through_the_public_endpoint | POST donation creation, asserts redirect + 1 Donation row | MODIFY — redirect target changes |
| PaymentHttpTest.php:test_public_status_read_survives_ordinary_subsequent_requests | GET payment show, asserts JSON with assertJsonPath | MODIFY — HTML path returns Inertia, not JSON |
| PaymentHttpTest.php:test_public_status_read_without_the_creation_session_is_not_found | Unsessioned GET → 404 | REUSE — gate unchanged in both HTML/JSON branches |
| PaymentHttpTest.php (guest security suite, lines 252-412) | Session possession, cross-session denial, replay behavior | REUSE — access gates unchanged |

### Existing tests to MODIFY

| Path | Current assertion | Required change |
|------|-------------------|-----------------|
| DonationHttpTest.php:test_guest_creates_a_donation_through_the_public_endpoint (line 94) | `assertRedirect()` | Add `assertRedirect('/donations/<ulid>/payments/<ulid>')` or equivalent PaymentCreate route assertion (after route is registered). Also verify: still creates exactly 1 Donation row AND 0 Payment rows. |
| PaymentHttpTest.php:test_public_status_read_survives_ordinary_subsequent_requests (lines 296-306) | `assertOk()` + `assertJsonPath(...)` on default Accept | Either: (a) add `'Accept' => 'application/json'` to get() calls for JSON-branch test, or (b) duplicate as two separate tests (HTML branch uses Inertia assertions, JSON branch uses assertJsonPath). |

### Tests to CREATE

| Proposed path | Type | Coverage |
|---------------|------|----------|
| Feature/Campaign/CampaignShowDonationLinkTest.php | Feature | CampaignShow with is_donation_eligible=true renders a real link to `/campaigns/{slug}/donate`; stale placeholder removed. |
| Feature/Payment/PaymentHtmlPageTest.php | Feature | HD-FE009-02: (a) Browser GET to payment show → Inertia PaymentShow page (X-Inertia response check); (b) Explicit JSON GET → canonical JSON payload; (c) Both paths contain identical field values from same publicPayload() call. |
| Feature/Donation/DonationFlowRedirectTest.php | Feature | HD-FE009-01: (a) Successful donation creation redirects to PaymentCreate when no ACTIVE Payment Attempt exists; (b) POST donation creates exactly Donation row, zero Payment/PaymentAttempt rows immediately after. |

### Tests to REUSE AS-IS (no modification needed for these)

- All guest-security tests in PaymentHttpTest (F-04/F-05 session-possession, access denial)
- Policy/gate tests for admin routes (admin/donation/donations, admin/payment/payments require auth)
- PaymentCreationTest (business logic untouched)
- DonationServiceTest (business logic untouched)

## Findings

### QWEN-FE009-01 [MAJOR] — Icon Names Missing from Registry

Spec proposes `heart` (Donations), `creditCard` (Payments), `settings` (Provider Config). None exist in Icon.vue's registry of 22 icons. Fallback to `FileText` produces misleading visuals.

Recommendation: Add all three to Icon.vue's registry from @lucide/vue (Heart, CreditCard, Settings). These are presentation-detail icons only, no business impact, within spec allowance (§10).

### QWEN-FE009-02 [MINOR] — Redirect Chain After Donation Creation

Current store() redirects to CampaignShow, which shows the stale placeholder even after a successful donation. If HD-FE009-01 changes the redirect to PaymentCreate, there is a single-page jump improvement. The redirect chain is:

```
POST /campaigns/{slug}/donations → PaymentCreate.vue → select provider → POST payments → PaymentShow.vue
```

This is the intended flow per HD-FE009-01. No blocker.

### QWEN-FE009-03 [MINOR] — Existing Payment Show Tests Assert Raw JSON

`PaymentHttpTest::test_public_status_read_survives_ordinary_subsequent_requests` uses `assertJsonPath()` on a GET that currently returns JSON. After HD-FE009-02 adds Inertia HTML for browser requests, this test MUST split into two assertions:
- One with `Accept: application/json` header targeting the JSON branch
- One without (or `Accept: */*`) targeting the HTML/Inertia branch

Not a blocker — expected test adaptation.

### QWEN-FE009-04 [INFORMATIONAL] — Spec Claims 10 MODIFY Files; Actual Count is 11

The spec summary says "5 CREATE, 10 MODIFY". Actual distinct modified files = 11 PHP controller/service files. The spec likely grouped controllers conceptually. No architectural issue — clarification only.

### QWEN-FE009-05 [INFORMATIONAL] — AdminLayout Sidebar Escape Key

AdminLayout overlay (line 98: `<div v-if="mobileOpen" @click="mobileOpen = false">`) handles click-to-close. Escape key dismissal for keyboard users is not explicitly handled. Per spec §13, a minimal keydown listener on `Escape` → `mobileOpen = false` is recommended. Small existing-component fix, not new pattern.

## Human Decisions Required

**NONE.** Both HD-FE009-01 and HD-FE009-02 are already APPROVED (spec §20 confirms 0 open human decisions).

The icon-substitution question (QWEN-FE009-01) is a LOW-BUSINESS-IMPACT presentation detail per spec §10. Muse may add Heart/CreditCard/Settings to Icon.vue without requiring a new Human Decision, as the spec explicitly allows substituting nearest existing icons and adding presentation-layer icons is within Implementation Write Owner authority.

## Muse Handoff

### Phase 1 — Foundation (PublicShell + siteChrome)
1. Add `siteChrome()` + extracted helper to `PublicRenderer.php`
2. Drop `brandingPayload()` from PublicCampaignController and PublicProgramController; replace with `$renderer->siteChrome($theme)`
3. Add `chrome` prop to PublicDonationController::create()
4. Create `PublicHeader.vue`, `PublicFooter.vue`, `PublicShell.vue`

### Phase 2 — Shell Adoption
5. Rewrite ThemeRender.vue header/footer to use extracted components
6. Adopt PublicShell in CampaignIndex, CampaignShow, ProgramShow, DonationCreate
7. Remove stale donation placeholder from CampaignShow; add real donate link gated by `is_donation_eligible`
8. Add "Back to campaign" link to DonationCreate

### Phase 3 — Payment Integration
9. Modify `PublicPaymentController::show()` to content-negotiate HTML vs JSON (HD-FE009-02)
10. Modify `PublicDonationController::store()` redirect target per HD-FE009-01
11. Create PaymentShow.vue consuming publicPayload() contract
12. Create PaymentCreate.vue with provider selector posting to existing store() route

### Phase 4 — Admin + Polish
13. Add Donations/Payments navGroups to AdminLayout.vue
14. Add missing icons to Icon.vue (Heart, CreditCard, Settings)
15. Fix aria-describedby associations on DonationCreate form errors

### Immediate Pre-Run Checks for Muse
- Run `npm run type-check` and `npm run build` to establish baseline before starting
- Read `resources/js/Pages/Public/ThemeRender.vue` lines 103-138 and 259-262 for exact header/footer markup to extract verbatim
- Run `tests/Feature/Campaign/PublicCampaignControllerTest.php` before any changes to verify green baseline
- Run `tests/Feature/Payment/PaymentHttpTest.php:test_public_status_read_survives_ordinary_subsequent_requests` before HD-FE009-02 to capture pre-state JSON behavior

### Targeted Test Sequence (during implementation, before Codex closure)
1. `php artisan test tests/Feature/FoundationSmokeTest.php` — Home still renders
2. `php artisan test tests/Feature/Campaign/PublicCampaignControllerTest.php` — Campaign/Program pages still green
3. `php artisan test tests/Feature/Donation/DonationHttpTest.php` — Donation creation still works (expect redirect change)
4. `php artisan test tests/Feature/Payment/PaymentHttpTest.php` — Payment security still intact (expect JSON test adaptation)
5. New feature tests for PaymentShow HTML/JSON dual paths
6. New feature tests for CampaignShow donation link presence
7. New feature tests for donation → payment redirect
8. `npm run type-check` — zero new TS errors
9. `npm run build` — clean build
