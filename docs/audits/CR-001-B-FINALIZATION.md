# CR-001-B Data Model & Foundation — Finalization

## Status

FINAL / LOCKED

## CR

CR-001 — Public Experience, Visual CMS, ZISWAF UX & Admin Experience Architecture V2

## Phase

B — Data Model & Foundation

## Human CR-001-B Phase Gate

APPROVED

## Upstream Gates (pre-existing, not renegotiated by this finalization)

| Gate | Status | Commit |
|---|---|---|
| Architecture Gate | APPROVED / LOCKED | `cc8de3341f31451b93877f20a4299609d5b5e7e5` |
| Global Recon Gate | APPROVED / LOCKED | `018e2146719b5a7c4a3d10acd15730315a42061a` |
| CR-001-B Recon (Qwen) | APPROVED | Included in this finalization commit (`docs/ai-handoff/CR-001/B-RECON.md` — approved but remained uncommitted through implementation and all four remediation rounds) |

This finalization does not amend, rewrite, or squash either upstream commit.

## Implementation Commit

This finalization commit itself (see `git log` at the repository for the exact SHA recorded immediately after creation — this document is committed together with the CR-001-B implementation surface it describes, not as a separate follow-up).

## Final Codex Audit

PASS

## Findings

| Severity | Count |
|---|---|
| BLOCKER | 0 |
| MAJOR | 0 |
| MINOR | 0 |

| Finding | Status |
|---|---|
| CODEX-CR001B-01 (snapshot append-only boundary) | CLOSED |
| CODEX-CR001B-02 (policy governance boundary) | CLOSED |
| CODEX-CR001B-03 (Zakat snapshot provenance) | CLOSED |
| CODEX-CR001B-04 (numeric/currency value-object safety) | CLOSED |

Mandatory Contracts Unverified: **0**

## Test Results (final, post-Round-4)

```
SQLite targeted:   202 total, 198 passed,  4 skipped, 0 failed
MySQL:               9 total,   6 passed,  3 skipped, 0 failed
Full regression:  1176 total, 1154 passed, 22 skipped, 0 failed
```

MySQL concurrency: **PASS** (real disposable MySQL, genuinely separate PDO connection holding `GET_LOCK`, `ZakatPolicyVersioningService` fails closed under contention).

Optional MySQL triggers (BEFORE UPDATE/DELETE backstop on the two calculation-snapshot tables): **INDETERMINATE / NON-GATING** — could not be installed on the disposable `kmsitdonation_imp003_test` host (MySQL error 1419, no `SUPER`/`SYSTEM_VARIABLES_ADMIN` privilege). No elevated privilege, root credential, or grant change was requested or used to work around this. The application-layer append-only boundary (model instance guards + Eloquent-builder guards + base-query-builder guards, all enumerated against the installed `laravel/framework 13.31.0` source) is independently sufficient and does not depend on the trigger.

Laravel: `laravel/framework ^13.17`, `Application::VERSION` `13.31.0`.

Test Quality: **PASS** (every substantial persistence-denial test proves both the thrown exception and the unchanged persisted state — see `docs/ai-handoff/CR-001/B-EVIDENCE.md` §14.1).

Git diff check: **PASS**.

## Implemented Scope

- Additive schema: `theme_navigation_items.visible_desktop`/`visible_mobile`; `policy_lead_time_configs`; `zakat_types`; `zakat_policies` (versioned, per-type); `nisab_policies`; `gold_price_references`; `zakat_calculation_snapshots` (append-only, with typed `NisabPolicy`/`GoldPriceReference` provenance); `fidyah_policies` (versioned, org-wide); `fidyah_calculation_snapshots` (append-only)
- Domain models: `App\Models\Zakat\{ZakatType,ZakatPolicy,NisabPolicy,GoldPriceReference,ZakatCalculationSnapshot}`, `App\Models\Fidyah\{FidyahPolicy,FidyahCalculationSnapshot}`, `App\Models\PolicyLeadTimeConfig`
- Calculator service contracts (structural only, no formulas): `App\Services\Zakat\ZakatCalculatorService`, `App\Services\Fidyah\FidyahCalculatorService` — `resolveApplicablePolicy()` is real date-range/status lookup logic that fails closed on ambiguous (>1 applicable ACTIVE) history; `calculate()` deliberately throws, formula ownership deferred to IMP-019
- Authoritative policy-version write boundary: `App\Services\Zakat\ZakatPolicyVersioningService`, `App\Services\Fidyah\FidyahPolicyVersioningService` — lead-time fail-closed (NULL never treated as zero/immediate), effective-range validation, overlap prevention under transaction + MySQL advisory lock, deterministic per-family `version` identity, new-version-not-mutation correction
- Value objects: `App\ValueObjects\Zakat\{CalculationInput,CalculationResult}`, `App\ValueObjects\Fidyah\{CalculationInput,CalculationResult}` — overflow-safe integer parsing (no float-based bounds check), canonical `CurrencyMinorUnits` registry validation, structural-only Zakat explainability fields (`thresholdMet`/`nisabAmountMinor`/`basisExplanation`, all nullable/uncomputed)
- Fail-closed persistence governance enumerated against the installed Laravel 13.31.0 source at every layer a caller can reach: model instance (`save`/`delete`/`creating`/`saving` events), Eloquent builder (`App\Support\Database\{AppendOnlyBuilder,GovernedPolicyBuilder}`), and base query builder (`App\Support\Database\{AppendOnlyQueryBuilder,GovernedPolicyQueryBuilder}`), closing `toBase()`/`getQuery()` and forwarded low-level insert escapes
- `THEME_PREVIEW` permission constant (`App\Services\Rbac\PermissionRegistry`) and `ThemePolicy::preview(Principal $actingPrincipal, Theme $theme): bool`, using the existing `AuthorizesUsingRbac` + `ThemeScopeResolver` + `ScopeType::Organization` pattern
- `ThemeNavigationItem` visibility casts (`visible_desktop`/`visible_mobile`)

No functionality beyond this implemented CR-001-B scope is claimed final by this record.

## Explicit Exclusions

- Zakat/Fidyah calculation formulas, rates, nisab values, or gold-price defaults (IMP-019 — never invented here)
- Any Ledger/Payment/Donation/financial-consequence posting or integration
- Admin/Public Vue UI for Site Design, Page Builder, or ZISWAF IA (CR-001-C/D/E/F/G)
- Calculator API endpoints/routes (CR-001-G)
- CODEX-FE-CHK-009-01 remediation (reserved for CR-001-J — untouched, OPEN/VALID/UNRESOLVED)
- Any FE-CHK-009 dirty working-tree file (reserved for that checkpoint's own finalization — untouched, collision confirmed NONE across all four remediation rounds)
- `PublicRenderer.php` (write owner CR-001-E — not modified by any CR-001-B round)
- Redis/Supervisor/PM2/Docker/WebSockets/production Node runtime, or any elevated MySQL privilege, as a requirement for correctness (shared-hosting compatibility preserved)

## Other Status

CODEX-FE009-01: **OPEN / VALID / UNRESOLVED** — owner **CR-001-J**. This is **not** a CR-001-B blocker; CR-001-B has zero intersection with the affected files (`PublicDonationController.php`, `PublicPaymentController.php`, `DonationService.php`, `DonationHttpTest.php`, `PaymentHttpTest.php`), confirmed across all implementation and remediation rounds.

FE-CHK-009: **PAUSED** — its dirty working-tree files are preserved untouched by this finalization.

CR-001-C: **NOT STARTED** — not authorized by this gate.

IMP-010 (Ledger): **BLOCKED** — unaffected by this finalization.

## Evidence Trail

Full implementation and remediation history: `docs/ai-handoff/CR-001/B-EVIDENCE.md` (§1-2 pre-write verification and file inventory; §5-8 Round 0 test results and acceptance criteria; §10-11 Round 1; §12 Round 2; §13 Round 3; §14 Round 4; §16 this Human Gate approval).

Recon file map (Qwen, Human-approved, committed in this same finalization): `docs/ai-handoff/CR-001/B-RECON.md`.
