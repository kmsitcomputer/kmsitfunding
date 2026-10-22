# CR-001-B Governed Phase Recon / Implementation File Map

> **Architecture baseline:** `cc8de3341f31451b93877f20a4299609d5b5e7e5`
> **Recon baseline:** `018e2146719b5a7c4a3d10acd15730315a42061a`
> **Human Architecture Gate:** APPROVED
> **Human Recon Gate:** APPROVED
> **Open Human Decisions:** 0
> **Recon date:** 2026-09-21
> **Model:** qwen/qwen3.7-flash

---

## 1. CR-001-B Purpose

CR-001-B ("CMS / Site Design Data Model") establishes the complete data-layer foundation that subsequent phases depend on. It is the first actionable phase — zero dependencies.

**Purpose summary:** Additive schema changes (columns + new tables), new domain models, new services, and new value objects for Site Design visibility targeting, Zakat/Fidyah calculator foundations, and policy lead-time governance. No admin UX, no frontend, no business rules invented.

**Canonical sources:**
- Architecture: `docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md` — Sections 16, 20, 23, 26, 55, 63, 64; Phase map Section 21 (CR-001-B)
- Locked Recon: `docs/ai-handoff/CR-001/RECON.md` — Sections 12.2, 13, 21, 23 (CR-001-B subsections), 24 (Phase B context)
- Existing IMP: `docs/implementation/IMP-006-theme-engine.md`, `docs/implementation/IMP-005-cms.md`

**Not owned by B (deferred or forbidden):**
- Admin/Vue frontend (CR-001-C/D/E/F)
- ZISWAF IA placeholder pages (CR-001-G)
- Calculator API endpoints (CR-001-G)
- CALCULATOR BUSINESS RULES / RATES / FORMULAS (IMP-019)
- CODEX-FE-CHK-009-01 remediation (CR-001-J)
- Ledger integration (IMP-010)

---

## 2. CR-001-B Boundaries

### IN SCOPE
- Database schema migrations (additive only)
- Eloquent model classes (new or ADAPT)
- Domain services (stateless calculator contracts)
- Value objects (CalculationInput, CalculationResult)
- Permission constant addition (`THEME_PREVIEW`)
- Policy method addition (`ThemePolicy::preview()`)
- Feature/unit tests for B artifacts

### OUT OF SCOPE
- Admin Vue pages (CR-001-C for SiteDesign/PageBuilder UI)
- Public Vue pages/components (CR-001-E for Shell/Header/Footer/MobileBottomNav/StickyCTA)
- Calculator business formulas/rates/nisab values (IMP-019)
- Calculator API routes/endpoints (CR-001-G)
- Dashboard widgets (CR-001-D)
- Permission-aware navigation (CR-001-D)
- Dark admin palette (CR-001-D)
- Migration of existing Theme/Page/Article content (CR-001-I)
- CODEX-FE-CHK-009-01 security fix (CR-001-J)

---

## 3. File Ownership Matrix

### 3.1 Writable Files — CREATE

| Path | Why | Collision |
|------|-----|-----------|
| `database/migrations/NNNN_create_theme_navigation_item_visibility_columns.php` | Adds `visible_desktop` / `visible_mobile` to `theme_navigation_items` (Schema #1, #2) | NONE |
| `database/migrations/NNNN_create_zakat_types_table.php` | New `zakat_types` table (Schema #5) | NONE |
| `database/migrations/NNNN_create_zakat_policies_table.php` | New `zakat_policies` table (Schema #6) | NONE |
| `database/migrations/NNNN_create_nisab_policies_table.php` | New `nisab_policies` table (Schema #7) | NONE |
| `database/migrations/NNNN_create_gold_price_references_table.php` | New `gold_price_references` table (Schema #8) | NONE |
| `database/migrations/NNNN_create_zakat_calculation_snapshots_table.php` | New `zakat_calculation_snapshots` table (Schema #9) | NONE |
| `database/migrations/NNNN_create_fidyah_policies_table.php` | New `fidyah_policies` table (Schema #10) | NONE |
| `database/migrations/NNNN_create_fidyah_calculation_snapshots_table.php` | New `fidyah_calculation_snapshots` table (Schema #11) | NONE |
| `database/migrations/NNNN_create_policy_lead_time_configs_table.php` | New `policy_lead_time_configs` table (Schema #12) | NONE |
| `app/Models/Zakat/ZakatType.php` | Registry model for Schema #5 | NEW DIR — safe, no conflict |
| `app/Models/Zakat/ZakatPolicy.php` | Versioned policy model for Schema #6 | NEW DIR — safe, no conflict |
| `app/Models/Zakat/NisabPolicy.php` | Nisab threshold reference model for Schema #7; grouped with Zakat family (closely tied to zakat_policies FK relationship) | NEW DIR — safe, no conflict |
| `app/Models/Zakat/GoldPriceReference.php` | Dated gold price snapshot model for Schema #8; grouped with Zakat family (primarily consumed by Zakat calculator) | NEW DIR — safe, no conflict |
| `app/Models/Zakat/ZakatCalculationSnapshot.php` | Immutable calculation record for Schema #9 | NEW DIR — safe, no conflict |
| `app/Models/Fidyah/FidyahPolicy.php` | Versioned Fidyah policy model for Schema #10 | NEW DIR — safe, no conflict |
| `app/Models/Fidyah/FidyahCalculationSnapshot.php` | Immutable Fidyah calculation record for Schema #11 | NEW DIR — safe, no conflict |
| `app/Models/PolicyLeadTimeConfig.php` | Lead-time governance config for Schema #12; standalone cross-cutting policy family concept (not scoped to Zakat or Fidyah alone) | SAFE |
| `app/Services/Zakat/ZakatCalculatorService.php` | Stateless application service (Schema #5–9 contract) | SAFE |
| `app/Services/Fidyah/FidyahCalculatorService.php` | Stateless application service (Schema #10–11 contract) | SAFE |
| `app/ValueObjects/Zakat/CalculationInput.php` | Validated calculation input value object | SAFE |
| `app/ValueObjects/Zakat/CalculationResult.php` | Validated calculation result value object | SAFE |
| `app/ValueObjects/Fidyah/CalculationInput.php` | Validated Fidyah calculation input value object | SAFE |
| `app/ValueObjects/Fidyah/CalculationResult.php` | Validated Fidyah calculation result value object | SAFE |

**Total CREATE files: 23** (9 migration + 8 model + 2 service + 4 value-object)

Exact migration file names and grouping are NOT YET DETERMINED. Multiple compatible schema changes may be consolidated in one migration. The count above shows individual proposed files; the exact file count belongs to B implementation planning.

### 3.2 Writable Files — MODIFY

| Path | Why | Collision |
|------|-----|-----------|
| `app/Policies/ThemePolicy.php` | ADD `preview()` method for Draft Site Design authorization (Section 30, RECON §23 B) | SAFE |
| `app/Services/Rbac/PermissionRegistry.php` | ADD `THEME_PREVIEW` constant = `'theme.preview'` (Section 30, RECON §18.2) | SAFE |
| `app/Models/Theme/ThemeNavigationItem.php` | MIGRATION ONLY — add `visible_desktop` / `visible_mobile` to `$casts()` if desired; model code otherwise unchanged | SAFE |

**Total MODIFY files: 3**

### 3.3 READ-ONLY Files (Integration Context Only)

| Path | Reason |
|------|--------|
| `app/Services/Theme/PublicRenderer.php` | WRITE OWNER is CR-001-E. B reads for integration point understanding only. NO modifications. |
| `app/Models/Theme/Theme.php` | Reference for Theme activation/status pattern |
| `app/Models/Theme/ThemeActivation.php` | Reference for singleton activation pattern |
| `app/Models/Theme/ThemeNavigationMenu.php` | Reference for navigation FK relationships |
| `app/Models/Theme/Concerns/GeneratesUlid.php` | Reference for ULID generation trait pattern |
| `app/Support/Money/Money.php` | Reference for money discipline (Section 64) |
| `app/Support/Money/CurrencyMinorUnits.php` | Reference for currency minor units |
| `app/Enums/ScopeType.php` | Reference for scope enum used by policy patterns |
| `app/Policies/Concerns/AuthorizesUsingRbac.php` | Reference for AuthorizesUsingRbac concern pattern |
| `app/Services/Rbac/PermissionRegistry.php` | Reference for existing permission constant pattern |
| `app/Services/Theme/ThemeScopeResolver.php` | Reference for scope resolver pattern used by ThemePolicy |
| `app/Models/User.php` | Reference for users.table — conditional Schema #3 assessed as DEFERRED (see §4.2) |
| `app/Models/Cms/CmsArticle.php` | Reference for cms_articles — conditional Schema #4 assessed as DEFERRED (see §4.3) |

### 3.4 Forbidden Files

| Path | Why |
|------|-----|
| Any `resources/js/**/*.vue` file | Frontend/UI belongs to CR-001-C/D/E/F |
| `routes/web.php` | Route additions belong to CR-001-G (calculator endpoints, IA pages) |
| `app/Http/Controllers/*` | Controllers belong to CR-001-C (SiteDesignController, PageBuilderController) or CR-001-G |
| `tests/Feature/Payment/PaymentHttpTest.php` | RESERVED for CR-001-J (CODEX-FE-CHK-009-01 area) |
| `tests/Feature/Donation/DonationHttpTest.php` | RESERVED for CR-001-J |
| `app/Services/Donation/DonationService.php` | DO NOT MODIFY per instructions (CR-001-J area) |
| `app/Http/Controllers/PublicDonationController.php` | RESERVED for CR-001-J |
| `app/Http/Controllers/PublicPaymentController.php` | RESERVED for CR-001-J |

---

## 4. Schema Allocation to CR-001-B

CR-001-B does NOT own all 12 global schema items. Allocation is based on the approved phase map (RECON §21):

### 4.1 DEFINITE Schema Items Owned by B (10 total)

| # | Item | Table | Classification | Phase Map Owner | Notes |
|---|------|-------|---------------|-----------------|-------|
| 1 | `theme_navigation_items.visible_desktop` | `theme_navigation_items` | DEFINITE | B | Column, nullable BOOLEAN DEFAULT TRUE |
| 2 | `theme_navigation_items.visible_mobile` | `theme_navigation_items` | DEFINITE | B | Column, nullable BOOLEAN DEFAULT TRUE |
| 5 | `zakat_types` table | `zakat_types` | DEFINITE | B | Registry: code, name, calculation_method_ref, is_active |
| 6 | `zakat_policies` table | `zakat_policies` | DEFINITE | B | Versioned: rate, nisab_basis, source_ref, effective_from, effective_until, status |
| 7 | `nisab_policies` table | `nisab_policies` | DEFINITE | B | Threshold basis reference |
| 8 | `gold_price_references` table | `gold_price_references` | DEFINITE | B | Dated gold price snapshot: amount_minor, currency, as_of_date, source_ref |
| 9 | `zakat_calculation_snapshots` table | `zakat_calculation_snapshots` | DEFINITE | B | Immutable: input, result, policy_version_ref, computed_at, acting_principal |
| 10 | `fidyah_policies` table | `fidyah_policies` | DEFINITE | B | Versioned: rate per day, versioned, dated |
| 11 | `fidyah_calculation_snapshots` table | `fidyah_calculation_snapshots` | DEFINITE | B | Immutable Fidyah calculation record |
| 12 | `policy_lead_time_configs` table | `policy_lead_time_configs` | DEFINITE | B | Configurable minimum effective-date lead time duration (HD-CR001-03, Section 63) |

**Note:** Items #5–#12 are assigned to B in the phase map despite the "G" label in the Recon table's CR-001 Phase column. The phase map Section 21 states B creates all these models and services, while CR-001-G depends on B having them ready first (dependencies arrow points from B to G). The "G" column in Recon §12.2 appears to indicate "used by G" rather than "owned by G." B creates the models/services; G uses them.

### 4.2 CONDITIONAL Schema Items — STATUS PER B

| # | Item | Classification | B Decision | Rationale |
|---|------|---------------|------------|-----------|
| 3 | `users.name` | CONDITIONAL | **DEFERRED / NOT ADOPTED BY B** | Architecture Section 45 makes this optional. The recommended no-migration path for v1: derive initial/email-label from existing `email` column, NO migration required. Not triggered by HD-CR001-07 (Admin dark-only design). Conditional, not mandatory. If adopted later, requires its own governed decision. |
| 4 | `cms_articles` classification | CONDITIONAL | **DEFERRED / NOT OWNED BY B** | News classification is a CONTENT MODEL EXTENSION tied to CMS V2 operator UX (CR-001-C). Not required for B's data foundation. If adopted, CR-001-C owns the migration decision. |

### 4.3 Summary Arithmetic

```
B-OWNED DEFINITE: 10 (#1, #2, #5, #6, #7, #8, #9, #10, #11, #12)
B-OWNED CONDITIONAL: 0 (both deferred — #3 DEFERRED, #4 DEFERRED)
TOTAL SCHEMA SURFACE: 12 (10 definite + 2 conditional, but B implements 10 definite)
```

SCHEMA ITEM COUNT != MIGRATION FILE COUNT. Exact migration file count is NOT YET DETERMINED.

---

## 5. Permissions / Policies for B

### 5.1 New Permission

| Constant | String | Method | Policy | Status |
|----------|--------|--------|--------|--------|
| `THEME_PREVIEW` | `'theme.preview'` | `ThemePolicy::preview(Principal $actingPrincipal): bool` | `ThemePolicy` | **CREATED BY B** |

Follows canonical `theme.*` naming convention (same pattern as `THEME_VIEW`, `THEME_PUBLISH`, etc.). Required by Section 30: Preview/draft visibility must be gated through `ThemePolicy::preview()`.

**Ownership note:** The *permission constant* and *policy method* are created by B. The *controller endpoint* that calls `preview()` belongs to CR-001-C (SiteDesignController). B provides the auth gate; CR-001-C uses it.

### 5.2 Modified Policy

- `ThemePolicy::preview()` — returns true only if authorized via `PermissionRegistry::THEME_PREVIEW`. Implements Section 30 requirement: "DRAFT Site Design must only be viewable through an authorized Preview action, never a public URL guessable by an anonymous visitor."
- Uses same `AuthorizesUsingRbac` concern + `ThemeScopeResolver` with `ScopeType::Organization` pattern as all other ThemePolicy methods.

---

## 6. Impacts on Existing IMP-005 / IMP-006

### 6.1 IMP-005 (CMS)

| Element | Impact | Classification |
|---------|--------|---------------|
| `CmsPage` / `CmsArticle` / `CmsContentRevision` | None. Zero schema changes to these tables by B. | UNCHANGED |
| `CmsPath` | None. | UNCHANGED |
| `CmsMediaAsset` / `CmsMediaReference` | None. | UNCHANGED |
| `CmsHomepageAssignment` | None. | UNCHANGED |
| Services (PageService, ArticleService, PublicationService, etc.) | None. | UNCHANGED |
| `cms_articles` classification column (Schema #4) | Conditional. B defers. CR-001-C decides. | DEFERRED |

### 6.2 IMP-006 (Theme Engine)

| Element | Impact | Classification |
|---------|--------|---------------|
| `Theme` model | None directly. New `status=DRAFT` rows become possible via copy-on-write (Section 26). No schema change to existing columns. | UNCHANGED |
| `ThemeActivation` | None. Already exactly the "one active" singleton pointer B needs. | UNCHANGED |
| `ThemeTemplate` / `ThemeSection` / `ThemeComponent` | No schema change. Component type VARCHAR already supports new string values additively. | UNCHANGED |
| `ThemeBrandingConfig` | None. | UNCHANGED |
| `ThemeAsset` | None. | UNCHANGED |
| `ThemeNavigationMenu` | None. | UNCHANGED |
| `ZakatType`, `ZakatPolicy` models | None directly. These are NEW models B creates under `app/Models/Zakat/`. No schema conflict with existing IMP-006 tables. | UNCHANGED |
| `NisabPolicy`, `GoldPriceReference` models | None directly. Placed under `app/Models/Zakat/` per repository domain-subdirectory convention for cohesion with Zakat calculator family. | UNCHANGED |
| `ZakatCalculationSnapshot` model | None directly. Placed under `app/Models/Zakat/`. Immutable record, no FK to IMP-006 tables. | UNCHANGED |
| `FidyahPolicy`, `FidyahCalculationSnapshot` models | None directly. Placed under `app/Models/Fidyah/`. | UNCHANGED |
| `PolicyLeadTimeConfig` model | None directly. Standalone at `app/Models/`. Cross-cutting policy governance, not scoped to Zakat only. | UNCHANGED |
| `ThemeNavigationItem` | ADDITIVE columns: `visible_desktop` (BOOLEAN NULL DEFAULT TRUE), `visible_mobile` (BOOLEAN NULL DEFAULT TRUE). These do not affect existing behavior — old rows default to TRUE for both. Model namespace `App\Models\Theme\` is consistent with repository convention. | ADAPT |
| `PublicRenderer::renderForContentKind()` | Core algorithm untouched. READ-ONLY for B. | UNCHANGED |
| `PublicRenderer::siteChrome()` | Extend payload richness? NO — belongs to CR-001-E. READ-ONLY for B. | UNCHANGED |
| `ThemePolicy` | ADD `preview()` method. ADD `THEME_PREVIEW` permission constant. | MODIFY |
| Existing Theme CRUD controllers | None. WRAPPED by CR-001-C (SiteDesignController). | UNCHANGED |

---

## 7. FE-CHK-009 Collision Matrix

FE-CHK-009 state (confirmed current working tree):
- 16 modified tracked files
- 8 untracked files
- 24 total dirty files

**CRITICAL: None of CR-001-B's writable files appear in either the modified or untracked FE-CHK-009 list.**

| Category | B Files | In FE-CHK-009 Dirty List? | Collision? |
|----------|---------|--------------------------|------------|
| Migrations | 9 (visibility + 8 new tables) | NO | NONE |
| Models | 8 (new Zakat/Fidyah + PolicyLeadTimeConfig) | NO | NONE |
| Services | 2 (calculator services) | NO | NONE |
| Value Objects | 4 (CalculationInput/Result x2 families) | NO | NONE |
| Policy modification | `ThemePolicy.php` | NO | NONE |
| Permission registry | `app/Services/Rbac/PermissionRegistry.php` constant | NO | NONE |

**All CR-001-B writes target brand-new files in previously unused paths (`Zakat/`, `Fidyah/`, `PolicyLeadTimeConfig.php`, new migrations).**

**FE-CHK-009 COLLISION: NONE**

---

## 8. CODEX-FE-CHK-009-01 Safety

CODEX-FE-CHK-009-01 remains OPEN / VALID / UNRESOLVED.

**OWNER:** CR-001-J

CR-001-B has ZERO intersection with the affected files:
- `PublicDonationController.php` — reserved for J
- `PublicPaymentController.php` — reserved for J
- `DonationService.php` — DO NOT MODIFY (reserved for J)
- `DonationHttpTest.php` — reserved for J
- `PaymentHttpTest.php` — reserved for J

CODEX-FE-CHK-009-01 boundary: PASS. B neither fixes nor touches it.

---

## 9. ZISWAF Boundary Check

| Check | Result |
|-------|--------|
| ZISWAF transactional tables | NOT CREATED (deferred to IMP-019) |
| Religious formulas / rates / nisab values | NOT INVENTED (architecture contracts only, no values set) |
| Gold prices | ADDED as admin-entered historical snapshots (never live float API trust) |
| Qurban / Infaq / Sedekah / Wakaf tables | NOT CREATED (deferred to their owning IMP) |
| ZISWAF IA pages | NOT CREATED (belongs to CR-001-G) |
| Calculator computation endpoints | NOT CREATED (belongs to CR-001-G) |
| Calculator business rules | CONTRACT ONLY (service skeleton; actual formulas deferred to IMP-019) |

**ZISWAF BOUNDARY: PASS**

---

## 10. Calculator Boundary Check

| Check | Result |
|-------|--------|
| CalculationInput / CalculationResult | Created as VALUE OBJECTS (structural types, no business logic) |
| ZakatCalculatorService / FidyahCalculatorService | STUB IMPLEMENTATION (accepted signature, delegates to policy lookup; no formula invented) |
| Policy versions | TABLE STRUCTURE CREATED (rate, nisab_basis, effective dates, source ref columns) but NO default rate values |
| Lead-time config | CONFIGURATION TABLE CREATED (Section 63, HD-CR001-03) but NO hard-coded duration |
| Gold price | ADDED as admin-managed historical snapshots only (not live external API consumer) |
| Calculation results prefill Donation/Payment | Contract preserved — calculator NEVER creates Payment Attempt directly (Section 22 boundary) |

**CALCULATOR BOUNDARY: PASS**

---

## 11. Test Map

### 11.1 Unit Tests

| Test | What It Proves | Target | DB Required | Type |
|------|----------------|--------|-------------|------|
| `Unit\ZakatTypeTest` | Factory creates valid row; unique code constraint | `\App\Models\Zakat\ZakatType` model | YES (migration run) | NON-GATING |
| `Unit\ZakatPolicyTest` | Factory creates valid row; composite key on (type, version); effective date validation | `\App\Models\Zakat\ZakatPolicy` model | YES | NON-GATING |
| `Unit\FidyahPolicyTest` | Factory creates valid row; version uniqueness per policy family | `\App\Models\Fidyah\FidyahPolicy` model | YES | NON-GATING |
| `Unit\NisabPolicyTest` | Factory creates valid row | `\App\Models\Zakat\NisabPolicy` model | YES | NON-GATING |
| `Unit\GoldPriceReferenceTest` | Factory creates valid row; money fields use DECIMAL (no float) | `\App\Models\Zakat\GoldPriceReference` model | YES | NON-GATING |
| `Unit\ZakatCalculationSnapshotTest` | Factory creates valid row; immutability enforced via guards | `\App\Models\Zakat\ZakatCalculationSnapshot` model | YES | NON-GATING |
| `Unit\FidyahCalculationSnapshotTest` | Factory creates valid row; immutability enforced via guards | `\App\Models\Fidyah\FidyahCalculationSnapshot` model | YES | NON-GATING |
| `Unit\PolicyLeadTimeConfigTest` | Factory creates single-row singleton; overwrite protection | `\App\Models\PolicyLeadTimeConfig` model | YES | NON-GATING |
| `Unit\ZakatCalculationInputValueObjectTest` | Validation rejects floats; accepts DECIMAL/string inputs | `\App\ValueObjects\Zakat\CalculationInput` VO | NO | NON-GATING |
| `Unit\ZakatCalculationResultValueObjectTest` | Constructs valid result; amount_minor is integer | `\App\ValueObjects\Zakat\CalculationResult` VO | NO | NON-GATING |
| `Unit\FidyahCalculationInputValueObjectTest` | Same validation pattern | `\App\ValueObjects\Fidyah\CalculationInput` VO | NO | NON-GATING |
| `Unit\FidyahCalculationResultValueObjectTest` | Same construction pattern | `\App\ValueObjects\Fidyah\CalculationResult` VO | NO | NON-GATING |

### 11.2 Feature / Integration Tests

| Test | What It Proves | Target | DB Required | Type |
|------|----------------|--------|-------------|------|
| `Feature\MigrationVisibilityColumnsTest` | `visible_desktop` / `visible_mobile` exist, nullable, default TRUE | Migration | YES | GATING |
| `Feature\MigrationZakatTablesTest` | All 8 new tables created with correct column types | Migration | YES | GATING |
| `Feature\ThemePolicyPreviewTest` | Authorized principal can preview draft; guest cannot; unauthorized cannot | `ThemePolicy::preview()` | NO | GATING |
| `Feature\PermissionRegistryTest` | `THEME_PREVIEW` constant exists and equals `'theme.preview'` | PermissionRegistry | NO | GATING |

### 11.3 Regression Tests

| Test | What It Proves | Target | DB Required | Type |
|------|----------------|--------|-------------|------|
| Re-run existing `Theme/ThemeAuthorizationTest` | New `preview()` method doesn't break existing policy gates | Existing test file | YES | GATING |
| Re-run existing `Theme/ThemeServiceTest` | New models don't interfere with existing Theme CRUD | Existing test file | YES | GATING |

---

## 12. Implementation Order Recommendation

Smallest safe sequence for Claude:

### Step B1 — Foundation Models + Migrations
Create migrations and models for schema items owned by B. Follow:
- Existing ULID pattern (`GeneratesUlid` concern)
- Existing `$guarded` / `$fillable` conventions
- Existing cast patterns (boolean, datetime, integer)
- Existing migration naming convention (`{year}_{month}_{day}_NNNNN_{description}`)

Sub-order within B1:
1. `theme_navigation_items` visibility columns (simplest — additive on existing table)
2. `policy_lead_time_configs` (single-row singleton, simplest new table)
3. `zakat_types`, `nisab_policies`, `gold_price_references` (registry tables, no complex relations)
4. `zakat_policies`, `fidyah_policies` (versioned policy tables with FKs)
5. `zakat_calculation_snapshots`, `fidyah_calculation_snapshots` (immutable snapshot tables)

### Step B2 — Value Objects
Create `CalculationInput` / `CalculationResult` VOs for both Zakat and Fidyah families. Structural types only — no business formulas.

### Step B3 — Services
Create stub implementations of `ZakatCalculatorService` and `FidyahCalculatorService` with accepted signatures matching the architecture contract (Section 16, 20). Empty / delegate-to-policy bodies acceptable. Actual formula logic belongs to IMP-019.

### Step B4 — Authorization Extension
Add `THEME_PREVIEW` constant to PermissionRegistry. Add `ThemePolicy::preview()` method using established pattern.

### Step B5 — Tests
Run all unit + feature + regression tests. All must pass before phase completion.

---

## 13. Acceptance Criteria for CR-001-B

Claude must satisfy ALL of the following:

1. [ ] All 10 definitive schema items created via migrations (columns + tables)
2. [ ] Both conditional items remain UNTOUCHED by B (`users.name` NOT added, `cms_articles` classification NOT added)
3. [ ] `ThemePolicy::preview()` method implemented using `AuthorizesUsingRbac` + `PermissionRegistry::THEME_PREVIEW`
4. [ ] `THEME_PREVIEW` constant registered in PermissionRegistry with value `'theme.preview'`
5. [ ] `PublicRenderer.php` is NOT modified by B (WRITE OWNER is CR-001-E)
6. [ ] No ZISWAF transactional business rules invented
7. [ ] No religious/legal formulas, nisab values, or lead-time durations hard-coded
8. [ ] Money discipline preserved: `amount_minor` as integer/fixed-decimal, never float
9. [ ] All new models follow existing conventions (ULID, guarded/casts, relationships)
10. [ ] No destructive migration performed
11. [ ] All new unit tests pass
12. [ ] All relevant feature tests pass
13. [ ] All relevant regression tests pass
14. [ ] Shared hosting compatibility preserved (no new infrastructure)
15. [ ] No FE-CHK-009 dirty files touched
16. [ ] CODEX-FE-CHK-009-01 reserved files untouched

---

## 14. Git Safety

Do NOT perform:
- `git add`, `git commit`, `git push`, `git merge`
- `git reset`, `git restore`, `git clean`, `git stash`, `git checkout`

At completion, run:
```
git status --short
git diff --check
```

---

## 15. Stop Conditions

STOP and report (do not silently resolve):

- Architecture conflict discovered during implementation
- B-owned task requiring modification of `PublicRenderer.php`
- Requirement to implement CODEX-FE-CHK-009-01
- Need to invent religious/legal formulas, nisab values, or rates
- New Human Decision genuinely required
- Ownership collision preventing B from completing
- Unresolved FE-CHK-009 collision (unlikely — none identified above)

---

## 16. CR-001 Execution Routing

- **Qwen (this recon):** Produced B-RECON.md (governed phase recon + file map + minimum-sufficient context)
- **Claude (B implementation):** Sole implementation/remediation writer for CR-001-B
- **Tests:** Automated verification (PHPUnit)
- **Codex:** Independent READ-ONLY auditor
- **Human:** Final authority / spec approval before B starts

**Workflow after this recon:**
```
Qwen B Recon (this document)
→ [Human Spec Approval]
→ Claude B Implementation
→ Automated Tests
→ Codex B Audit
→ If findings:
   Claude PATCH — DO NOT REWRITE
   → Tests
   → Codex Re-audit
→ Human CR-001-B Gate
→ Next phase begins
```

---

## 17. Minimum Sufficient Context for Claude

### 17.1 Authoritative Docs

- `docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md` — Sections 16, 20, 21 (CR-001-B phase), 23, 26, 30, 55, 63, 64
- `docs/ai-handoff/CR-001/RECON.md` — Sections 12.2, 13, 21 (CR-001-B), 23 (B subsection), 24 (Phase B), 18.2, 25.1
- `docs/implementation/IMP-005-cms.md` (reference only — no changes)
- `docs/implementation/IMP-006-theme-engine.md` (reference only — no changes)

### 17.2 B-Owned Writable Files (Summary)

- Migration files (proposed individual paths; exact grouping NOT YET DETERMINED): 9 (visibility columns + 8 new tables). Architectural/final migration file count is NOT YET DETERMINED.
- Model classes: 8 (`ZakatType`, `ZakatPolicy`, `NisabPolicy`, `GoldPriceReference` under `app/Models/Zakat/`; `FidyahPolicy`, `FidyahCalculationSnapshot` under `app/Models/Fidyah/`; `PolicyLeadTimeConfig` at root `app/Models/`)
- Service classes: 2 (`ZakatCalculatorService` under `app/Services/Zakat/`; `FidyahCalculatorService` under `app/Services/Fidyah/`)
- Value objects: 4 (`CalculationInput`, `CalculationResult` for Zakat under `app/ValueObjects/Zakat/`; same for Fidyah under `app/ValueObjects/Fidyah/`)
- Modify existing: 3 (`ThemePolicy.php`, `app/Services/Rbac/PermissionRegistry.php`, `ThemeNavigationItem.php`)
- Total CREATE: 23 | Total MODIFY: 3 | Total WRITABLE: 26

### 17.3 Essential Read-Only Integration Files

- `app/Models/Theme/ThemeNavigationItem.php` — pattern reference for ULID, casts, relationships (namespace `App\Models\Theme`)
- `app/Policies/ThemePolicy.php` — pattern reference for authorization structure
- `app/Services/Rbac/PermissionRegistry.php` — pattern reference for permission constants (namespace `App\Services\Rbac`)
- `app/Support/Money/Money.php` — pattern reference for money discipline
- `app/Services/Theme/PublicRenderer.php` — READ ONLY (CR-001-E write owner)

### 17.4 Relevant Tests

- `tests/Theme/ThemeAuthorizationTest.php` — regression
- `tests/Theme/ThemeServiceTest.php` — regression
- Existing PHPUnit structure / Feature test patterns in repo

### 17.5 Locked Decisions for B

- Money discipline: `amount_minor` + `CurrencyMinorUnits`, never float (Section 64)
- No Ledger tables, no IMP-019 business rules (Section 55)
- HD-CR001-03: lead-time configurable, value not invented, duration never hard-coded (Section 63)
- Draft/Preview/Publish via copy-on-write approach (Section 26) — no schema change needed
- Single-actor publish confirmed (HD-CR001-02 Option A) — no second-person approval (Section 58)
- Preview requires authorized `ThemePolicy::preview()` — never guessable public URL (Section 30)
- Additive only — never DELETE, DROP, ALTER (existing column types) (Section 55)
- Existing Theme/Page/Article content preserved (Section 57)

---

## 19. Model Placement Convention (B-03 Resolution)

Repository convention verified by inspection of existing domain models (`Cms/CmsPage.php` → `App\Models\Cms`, `Campaign/Campaign.php` → `App\Models\Campaign`, `Donation/Donation.php` → `App\Models\Donation`, `Payment/` → `App\Models\Payment`, `Theme/` → `App\Models\Theme`): **Eloquent models live in domain-specific subdirectories under `app/Models/` with namespace `App\Models\<Domain>\<ModelName>`**.

### Eight Foundation Models — Consolidated Paths & Namespaces

| Class | Path | Namespace | Rationale |
|-------|------|-----------|-----------|
| ZakatType | `app/Models/Zakat/ZakatType.php` | `App\Models\Zakat` | Repository domain-subdirectory convention; grouped with other Zakat foundation models |
| ZakatPolicy | `app/Models/Zakat/ZakatPolicy.php` | `App\Models\Zakat` | FK relationship to ZakatType; cohesive family |
| NisabPolicy | `app/Models/Zakat/NisabPolicy.php` | `App\Models\Zakat` | Closely tied to zakat_policies FK; cohesive with Zakat calculator subsystem |
| GoldPriceReference | `app/Models/Zakat/GoldPriceReference.php` | `App\Models\Zakat` | Primarily consumed by Zakat calculator; gold-pegged nisab basis |
| ZakatCalculationSnapshot | `app/Models/Zakat/ZakatCalculationSnapshot.php` | `App\Models\Zakat` | Immutable record referencing ZakatPolicy version; tightly coupled |
| FidyahPolicy | `app/Models/Fidyah/FidyahPolicy.php` | `App\Models\Fidyah` | Repository domain-subdirectory convention; Fidyah family |
| FidyahCalculationSnapshot | `app/Models/Fidyah/FidyahCalculationSnapshot.php` | `App\Models\Fidyah` | Immutable record referencing FidyahPolicy version; tightly coupled |
| PolicyLeadTimeConfig | `app/Models/PolicyLeadTimeConfig.php` | `App\Models` | Cross-cutting policy governance; not scoped to one domain; standalone placement consistent with repository root-level models (User, Invitation, etc.) |

### Service & Value Object Consistency

| Class | Path | Namespace |
|-------|------|-----------|
| ZakatCalculatorService | `app/Services/Zakat/ZakatCalculatorService.php` | `App\Services\Zakat` |
| FidyahCalculatorService | `app/Services/Fidyah/FidyahCalculatorService.php` | `App\Services\Fidyah` |
| CalculationInput (Zakat) | `app/ValueObjects/Zakat/CalculationInput.php` | `App\ValueObjects\Zakat` |
| CalculationResult (Zakat) | `app/ValueObjects/Zakat/CalculationResult.php` | `App\ValueObjects\Zakat` |
| CalculationInput (Fidyah) | `app/ValueObjects/Fidyah/CalculationInput.php` | `App\ValueObjects\Fidyah` |
| CalculationResult (Fidyah) | `app/ValueObjects/Fidyah/CalculationResult.php` | `App\ValueObjects\Fidyah` |

**Path/Namespace consistency: PASS.** Every proposed path corresponds exactly to its namespace per Laravel PSR-4 autoloading and the repository's established domain-subdirectory convention.

---

## 20. FINAL REPORT

| Field | Value |
|-------|-------|
| MODEL | qwen/qwen3.7-flash |
| MODEL ROUTING | HUMAN-VERIFIED IN COMMAND CODE |
| TASK | CR-001-B GOVERNED PHASE RECON |
| ARCHITECTURE BASELINE | cc8de3341f31451b93877f20a4299609d5b5e7e5 |
| RECON BASELINE | 018e2146719b5a7c4a3d10acd15730315a42061a |
| CR-001-B PURPOSE | Establish data-model foundation: visibility columns on navigation items, 8 new ZISWAF/calculator tables, model classes, calculator service skeletons, value objects, ThemePolicy preview authorization. Zero business rules invented. First actionable phase. |
| CR-001-B WRITABLE FILES | 26 (3 modify + 23 create) |
| CR-001-B CREATE FILES | 23 (9 migration proposed paths + 8 model + 2 service + 4 value-object; architectural migration file count NOT YET DETERMINED) |
| CR-001-B MODIFY FILES | 3 (`ThemePolicy.php`, `app/Services/Rbac/PermissionRegistry.php`, `ThemeNavigationItem.php`) |
| CR-001-B READ-ONLY FILES | 13 |
| CR-001-B FORBIDDEN FILES | 9 categories listed (§3.4) |
| B-OWNED DEFINITE SCHEMA ITEMS | 10 (#1 visible_desktop, #2 visible_mobile, #5 zakat_types, #6 zakat_policies, #7 nisab_policies, #8 gold_price_references, #9 zakat_calculation_snapshots, #10 fidyah_policies, #11 fidyah_calculation_snapshots, #12 policy_lead_time_configs) |
| B-OWNED CONDITIONAL SCHEMA ITEMS | 0 (#3 users.name DEFERRED, #4 cms_articles classification DEFERRED) |
| EXACT MIGRATION FILE COUNT | NOT YET DETERMINED |
| PUBLICRENDERER | READ-ONLY |
| PUBLICRENDERER WRITE OWNER | CR-001-E |
| THEME_PREVIEW OWNERSHIP | PERMISSION CONSTANT + POLICY METHOD CREATED BY B (CR-001-B). CONTROLLER ENDPOINT OWNS CR-001-C. |
| USERS.NAME | DEFERRED (conditional, optional, Section 45 no-migration path recommended for v1) |
| CMS_ARTICLE CLASSIFICATION | DEFERRED (conditional, belongs to CR-001-C operator-UX decision) |
| FE-CHK-009 COLLISION | NONE (all B files are new paths: Zakat/, Fidyah/, PolicyLeadTimeConfig, new migrations) |
| CODEX-FE-CHK-009-01 | OPEN / VALID / UNRESOLVED |
| CODEX-FE-CHK-009-01 OWNER | CR-001-J |
| ZISWAF BOUNDARY | PASS |
| CALCULATOR BOUNDARY | PASS |
| NEW HUMAN DECISIONS | 0 |
| BLOCKER | 0 |
| MAJOR | 0 |
| B-RECON ARTIFACT | docs/ai-handoff/CR-001/B-RECON.md |
| APPLICATION FILES MODIFIED | NONE (recon produced documentation only) |
| TEST FILES MODIFIED | NONE |
| MIGRATIONS CREATED | NONE |
| DATABASE MODIFIED | NO |
| GIT DIFF CHECK | PASS (documentation-only output) |
| COMMIT | NOT PERFORMED |
| PUSH | NOT PERFORMED |
| MERGE | NOT PERFORMED |
| CR-001-B IMPLEMENTATION | NOT STARTED |
| IMP-010 | NOT STARTED / BLOCKED |
| FINAL VERDICT | CR-001-B RECON COMPLETE — READY FOR CLAUDE IMPLEMENTATION REVIEW |
