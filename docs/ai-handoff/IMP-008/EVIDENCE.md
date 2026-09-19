# IMP-008 Full Regression Evidence

> Evidence only. Introduces no business requirements, no specification change,
> no ADR change, no governance change.

## 1. Implementation HEAD

| Field | Value |
|---|---|
| Branch (baseline) | `master` |
| Implementation HEAD | `756bf20ff39f41bca9375c16ba31d25895f9d7be` |
| Baseline verification | branch=`master`, HEAD matches expected, `git status --short` empty at gate start |
| DeepSeek remediation re-review | PASS |
| Unresolved BLOCKER | 0 |
| Unresolved MAJOR | 0 |
| Unresolved MINOR | 0 |
| Unresolved GATE-IMPACT | 0 |
| Editorial findings (non-gating) | 4 — REVIEW-12, REVIEW-16, REVIEW-17, REVIEW-18 (accepted debt) |

## 2. MySQL environment identity (no secrets)

| Field | Value |
|---|---|
| Server | MySQL 8.4.11, `127.0.0.1:3306`, TCP |
| Disposable full-regression DB | `kmsitdonation_imp008_regression` (created by this gate; pre-existing: `kmsitdonation_imp008_test`) |
| Concurrency suite DB | `kmsitdonation_imp008_test` (hard-coded in `DonationConcurrencyTest::MYSQL_DATABASE`, separate contender connection `mysql_contender`) |
| DB_CONNECTION (regression runs) | `mysql` (process env override) |
| DB_HOST / DB_PORT | `127.0.0.1` / `3306` |
| DB_DATABASE (regression runs) | `kmsitdonation_imp008_regression` |
| Development DB | `kmsitdonation` — never targeted; read-only table count probes only |

Every error connection string captured in the MySQL regression log references
only `kmsitdonation_imp008_regression`. No error, migration, truncate, or
test connection string in any captured log references the development
database for a write path.

## 3. MySQL full regression (entire suite, real disposable MySQL)

- Command: `php artisan test` with `DB_CONNECTION=mysql`,
  `DB_DATABASE=kmsitdonation_imp008_regression` (process env; `.env` untouched).
- Result: **149 failed, 629 passed, 2055 assertions, duration 659.39s, 0 skipped.**
- Complete failed-test inventory (149 blocks; failed-class list identical
  between the MySQL and SQLite runs — see section 5):
  - `Tests\Feature\Audit\AuditFoundationTest` — 35
  - `Tests\Feature\Rbac\RolePermissionServiceTest` — 17
  - `Tests\Feature\Identity\IdentityAuditFailureRollbackTest` — 16
  - `Tests\Feature\Identity\MfaTest` — 13
  - `Tests\Feature\Rbac\RbacAuditTest` — 13
  - `Tests\Feature\Cms\ArticleControllerTest` — 10
  - `Tests\Feature\Cms\MediaControllerTest` — 8
  - `Tests\Feature\Cms\PageControllerTest` — 8
  - `Tests\Feature\Cms\HomepageControllerTest` — 5
  - `Tests\Feature\Identity\PasswordTest` — 8
  - `Tests\Feature\Identity\LoginTest` — 6
  - `Tests\Feature\Identity\RegistrationTest` — 3
  - `Tests\Feature\Rbac\RoleAssignmentTest` — 3
  - `Tests\Feature\Identity\AssuranceTest` — 2
  - `Tests\Feature\Identity\EmailChangeTest` — 1
  - `Tests\Feature\Identity\InvitationTest` — 1
- Failure signatures observed (read-only log forensics; nothing modified):
  - 80 `QueryException` blocks truncating a table named `about_blocks`
    (`SQLSTATE 42S02`, `truncate table about_blocks` against the disposable
    regression DB). No file in the repository defines an `about_blocks`
    table; the failure shows up only under MySQL truncation paths.
  - 69 non-truncation blocks, dominated by HTTP 419 responses
    (`Expected ... but received 419`, `TokenMismatchException: CSRF token
    mismatch`, `Session is missing expected key [errors]`), plus one
    `AuditFoundationTest::test_registry_inventory_matches_specification_counts`
    count assertion (actual 90 vs expected 81), two
    `ModelNotFoundException` (CmsArticle/CmsPage), two
    `Failed asserting that false is true` (Assurance/MFA), two
    `BadMethodCallException: ... Response::getSession does not exist`, and
    three `UniqueConstraintViolationException` duplicate
    `rbac-test-actor-1@example.com` entries in `RoleAssignmentTest`.
- IMP-008 donation scope inside the same run: **all 10 Donation test classes
  PASS** — `DonationAnonymityTest`, `DonationAuditRegistryTest`,
  `DonationAuthorizationTest`, `DonationConcurrencyTest` (4/4 genuine-overlap
  tests), `DonationExpirationTest`, `DonationHttpTest`, `DonationLifecycleTest`,
  `DonationSchemaConstraintsTest`, `DonationServiceTest`, `RecurringPlanTest`.

## 4. MySQL IMP-008 concurrency (separate explicit run)

- Command: `php artisan test --filter=DonationConcurrencyTest` against real
  MySQL (disposable `kmsitdonation_imp008_test`, distinct contender
  connection `mysql_contender`).
- Result: **4 passed, 0 failed, 0 skipped, 23 assertions, duration 112.43s.**
- Tests executed (none skipped; genuine lock-wait-timeout 1205 overlap
  evidence per test header):
  - `two_overlapping_transitions_on_one_pending_donation_exactly_one_succeeds`
  - `concurrent_same_key_create_creates_exactly_one_row`
  - `two_overlapping_workers_generate_exactly_one_occurrence`
  - `inactive_plan_generates_nothing_and_failed_occurrence_is_not_retried`

## 5. SQLite full regression (repository phpunit.xml configuration)

- Command: `php artisan test` with stock environment (no DB override;
  `phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- Result: **149 failed, 629 passed, 2055 assertions, duration 653.84s,
  0 skipped.**
- The failing-test inventory is **byte-identical** to the MySQL run (diff of
  the 149 sorted `Class > test` entries: empty). Error connection strings in
  this log reference `kmsitdonation` (the configured non-test database name
  interpolated into exception metadata), but the executed configuration is the
  repository's SQLite `:memory:` setup; no test run targeted the development
  database for writes, and no test file was modified.
- Expected MySQL-only concurrency skips: **none observed** — the concurrency
  suite reports PASS (not skipped) in this environment because the
  `kmsitdonation_imp008_test` disposable database is reachable; the class
  self-configures its MySQL connections when available.

## 6. Migration UP-DOWN-UP (disposable MySQL only)

Against `kmsitdonation_imp008_regression` only:

| Step | Command | Result |
|---|---|---|
| UP (fresh) | `php artisan migrate:fresh --force` | exit 0, all 47 migrations DONE incl. 4 IMP-008 `0001_08_01_*` |
| DOWN | `php artisan migrate:rollback --force` | exit 0, full rollback to empty |
| UP (again) | `php artisan migrate --force` | exit 0, `migrate:status` shows all `[1] Ran` |

**UP-DOWN-UP: PASS.**

## 7. Static / build / security

| Check | Result |
|---|---|
| `Pint --test` | PASS — 403 files, exit 0 |
| `vue-tsc --noEmit` (`npm run type-check`) | PASS — exit 0, no output |
| `Vite production build` (`npm run build`) | PASS — exit 0, built in 12.23s |
| `Composer Audit` | PASS — "No security vulnerability advisories found", exit 0 |
| `git diff --check` | PASS — exit 0, no whitespace errors |

Working tree remained clean after all checks (build artifacts ignored).

## 8. Locked boundary smoke check

Grep over `app/` and `database/migrations` for Payment Hub, gateway
processing, Ledger posting, Journal, Operational Fee, Commission, Withdrawal,
Refund, Reconciliation:

- `app/Services/Donation/*`: **no matches** — no Ledger/Journal/Payment/
  Commission/Withdrawal/Refund/Reconciliation references.
- `app/Models/Donation/*`: only a docblock stating the model is
  "Never a Ledger/Payment record" — no implementation leakage.
- `database/migrations/0001_08_01_*` (IMP-008): **no matches**.
- Only hit: `PermissionRegistry::AUDIT_READ_FINANCIAL_REFERENCE` description
  text explicitly stating the permission grants "NO financial-domain business
  authority, Payment/Ledger/Withdrawal/Refund/Reconciliation access" — a
  guardrail description, not an implementation.
- Payment boundary: **PASS.** Ledger boundary: **PASS.**

## 9. Editorial debt (accepted, non-gating)

REVIEW-12, REVIEW-16, REVIEW-17, REVIEW-18 remain recorded as
EDITORIAL / GATE-IMPACT NO. No code was modified to close them during this
gate. Regression reveals no functional impact traceable to them: all 10
Donation classes pass on both drivers and the concurrency evidence is green.

## 10. Development DB safety statement

- `kmsitdonation` was never selected as `DB_DATABASE` for any test or
  migration command in this gate (MySQL regression and UP-DOWN-UP used
  `kmsitdonation_imp008_regression`; concurrency used
  `kmsitdonation_imp008_test`; SQLite run used the stock `:memory:` config).
- The only operations touching `kmsitdonation` were read-only
  `information_schema` table-count probes during forensics.
- **Development database modified during regression: NO.**

## 11. Source / test modification statement

- Application source modified: **NO.**
- Tests modified: **NO.**
- Migrations modified: **NO.**
- Specification / ADR / governance / RECON modified: **NO** (this file only).
- Push: NOT PERFORMED. Merge: NOT PERFORMED.

## 12. Final regression verdict

**FULL REGRESSION FAIL — REMEDIATION REQUIRED** (entire-suite gate).

Rationale: the IMP-008 donation scope is fully green on both drivers
(10/10 classes incl. 4/4 genuine-overlap concurrency tests), migration
reversibility is proven, and all static/build/security checks pass — but the
entire-suite gate records 149 failures in 16 non-donation classes, identical
on MySQL and SQLite, so this gate cannot honestly claim a full-suite pass.
Per the gate's read-only mandate no failure was fixed, reclassified, or
hidden; the complete inventory above is handed to remediation and the Codex
final semantic/closure audit for disposition.
