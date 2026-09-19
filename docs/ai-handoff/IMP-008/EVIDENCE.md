# IMP-008 Regression Evidence (Pass 2 — corrected)

> Evidence only. Introduces no business requirements, no specification change,
> no ADR change, no governance change.

## 1. Implementation HEAD

| Field | Value |
|---|---|
| Branch (baseline) | `master` |
| Implementation HEAD | `756bf20ff39f41bca9375c16ba31d25895f9d7be` |
| Pass 2 remediation base | `238b27a23376459e51a670096da575258ef4af8c` (evidence-only) |
| Baseline verification | branch=`master`, HEAD `238b27a` matched expected, `git status --short` empty at gate start |
| DeepSeek remediation re-review | PASS |
| Unresolved BLOCKER | 0 |
| Unresolved MAJOR | 0 |
| Unresolved MINOR | 0 |
| Unresolved GATE-IMPACT | 0 |
| Editorial findings (non-gating) | 4 — REVIEW-12, REVIEW-16, REVIEW-17, REVIEW-18 (accepted debt) |

## 2. Correction notice: the previous "SQLite" regression was invalidly labelled

The regression evidence recorded in the prior pass under the label
"SQLite full regression (repository phpunit.xml configuration)" was
**INVALIDLY LABELLED**. It did not run under SQLite.

Root cause (independently established): the inherited process environment
carried committed `.env` values (`APP_ENV=local`, `DB_CONNECTION=mysql`,
`DB_DATABASE=kmsitdonation`, `CACHE_STORE=database`, `SESSION_DRIVER=database`,
`QUEUE_CONNECTION=database`), and PHPUnit's non-forced `<env>` entries do not
override already-present real environment variables. The run therefore
resolved to:

- `APP_ENV=local`
- `DB_CONNECTION=mysql`
- `DB_DATABASE=kmsitdonation`

Consequences:

1. The prior "MySQL run" and the alleged "SQLite run" were **not two valid
   driver-distinct regression runs**. Both resolved to MySQL against
   `kmsitdonation`-adjacent configuration, which is exactly why their
   149-failure inventories were byte-identical.
2. The prior claim "Development database modified during regression: NO" is
   **NOT supportable and is withdrawn** (see section 9).
3. The prior assertion of "identical on MySQL and SQLite" failure inventories
   is withdrawn: there was never a genuine SQLite run in that pass.

## 3. Regression triage: 149/149 accounted for

| Item | Count |
|---|---|
| Total failures in the invalid prior runs | 149 |
| Environment / pre-existing harness failures (OUTSIDE IMP-008 — see section 4) | 148 |
| Genuine IMP-008 test-expectation gap | 1 — **REMEDIATED in this pass** |

The genuine IMP-008 failure was
`AuditFoundationTest::test_registry_inventory_matches_specification_counts`:
the test expected 81 active / 87 total events, while IMP-008 legitimately
registers 9 approved Donation audit events
(`donation.created/succeeded/failed/expired/cancelled` +
4 `donation.recurring_plan.*`, per `DonationAuditEventRegistrar`, approved
under IMP-008 BR-14 / HD-IMP008-06 / ADR-002).

Before patching, the actual registry inventory was independently verified via
`tinker` against application boot:

- **ACTIVE = 90, TOTAL = 96** (reserved = 6, unchanged).

The patch updates ONLY the test expectation (`81 -> 90`, `87 -> 96`) plus the
inventory comment. No change to `DonationAuditEventRegistrar` semantics,
`AuditEventRegistry` semantics, `AuditActorKind`, criticality, scope,
persistence, actor kinds, or ADR-002. This is a test-expectation update, not
an audit-contract change.

## 4. Pre-existing harness issues (OUTSIDE IMP-008 — recorded as follow-up debt)

The following repository-wide / pre-existing test-infrastructure issues were
identified during triage and are **explicitly NOT patched in IMP-008**. No
change was made to `TruncatesInMemorySqlite`, `phpunit.xml`, global
environment loading, global `RefreshDatabase` behavior, or global test
architecture in this pass:

A. `TruncatesInMemorySqlite` is not MySQL-safe: schema-unqualified
   `getTableListing()` returns cross-schema tables.
B. Inherited process environment carries `.env` values and suppresses the
   `phpunit.xml` testing environment because the PHPUnit entries are not
   forced.
C. Persistent MySQL full-suite isolation is incompatible with several
   SQLite-shaped test helpers.

These are recorded as separate follow-up technical-debt / change-control
items only.

## 5. Genuine SQLite :memory: full regression (this pass)

Run in a clean child-process environment: inherited Laravel-relevant
variables scrubbed, then `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:` set for the test process only. No `phpunit.xml`
modification; no machine configuration changed. (Note: several PHPUnit
`<env>` testing values such as `DB_URL=""` reach the app as empty strings
and legitimately fall back to configured defaults; the probed runtime values
below are what the application actually resolved.)

Environment proof, captured from the SAME clean environment via a temporary
probe test (deleted afterwards; `git status` confirms only the two allowed
files changed):

- `APP_ENV = testing`
- `DB_CONNECTION = sqlite`
- `DB_DATABASE = :memory:`
- `CACHE_STORE = array`, `SESSION_DRIVER = array`,
  `QUEUE_CONNECTION = sync`, `MAIL_MAILER = array`, `BCRYPT_ROUNDS = 4`
- `Application::runningUnitTests() = true`

Smoke tests from the same clean environment (before the full suite):

- `PageControllerTest` (previously failed under the bad environment):
  **PASS** — 18 tests, 42 assertions.
- `AuditFoundationTest` (after the inventory patch): **PASS** —
  35 tests, 259 assertions, 1 skipped. The single skip is the explicitly
  expected driver-specific skip (`test_concurrent_same_source_key_insertion`
  requires MySQL; SQLite has no real concurrent writers).

Full suite (`vendor/bin/phpunit`, genuine SQLite `:memory:`,
`APP_ENV=testing`):

- **Tests: 778, Assertions: 2515, Failed: 0, Skipped: 6, Duration: 65.26s,
  Exit code: 0 — PASS.**
- The 6 skips are the expected MySQL-only concurrency skips (genuine
  concurrent-writer tests that self-skip on SQLite by design).

No failure was hidden, reclassified, or patched beyond the single authorized
audit-inventory expectation update.

## 6. MySQL IMP-008 evidence (preserved, not re-run as a full suite)

A repository-wide MySQL full regression was **NOT attempted** in this pass:
the current repo-wide test harness is not MySQL-safe (see section 4).
Existing independently verified IMP-008 MySQL evidence is preserved:

- **Genuine concurrency: 4/4 PASS** —
  `DonationConcurrencyTest` (`two_overlapping_transitions_on_one_pending_donation_exactly_one_succeeds`,
  `concurrent_same_key_create_creates_exactly_one_row`,
  `two_overlapping_workers_generate_exactly_one_occurrence`,
  `inactive_plan_generates_nothing_and_failed_occurrence_is_not_retried`),
  4 passed / 23 assertions / duration 112.43s against disposable
  `kmsitdonation_imp008_test` via the dedicated `mysql_contender`
  connection (evidence source: prior EVIDENCE.md section 4; test source:
  `tests/Feature/Donation/DonationConcurrencyTest.php`
  `MYSQL_DATABASE` / `CONTENDER_CONNECTION` constants).
- **Migration UP-DOWN-UP: PASS** — `migrate:fresh --force` (exit 0, all 47
  migrations incl. 4 IMP-008 `0001_08_01_*`), `migrate:rollback --force`
  (exit 0, full rollback), `migrate --force` (exit 0, all `[1] Ran`),
  against disposable `kmsitdonation_imp008_regression` only
  (evidence source: prior EVIDENCE.md section 6).

MYSQL FULL REGRESSION: **NOT VALID AS REPOSITORY-WIDE GATE —
PRE-EXISTING HARNESS INCOMPATIBILITY.** It is not reported as PASS.

The development database `kmsitdonation` was never selected as a target in
this pass. No MySQL IMP-008 re-run was necessary, so no disposable database
was touched either.

## 7. Static / build / security

| Check | Result |
|---|---|
| `Pint --test` | PASS — 403 files, exit 0 |
| `vue-tsc --noEmit` (`npm run type-check`) | PASS — exit 0, no output |
| `Vite production build` (`npm run build`) | PASS — exit 0, built in 4.50s |
| `Composer Audit` | PASS — "No security vulnerability advisories found", exit 0 |
| `git diff --check` | PASS — exit 0, no whitespace errors |

Working tree shows only the two allowed modified files after all checks
(build artifacts ignored).

## 8. Locked boundary smoke check (preserved from prior pass)

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

## 9. Development DB safety statement (corrected)

DEVELOPMENT DB SAFETY: **INDETERMINATE HISTORICAL SIDE EFFECT.**

The previous alleged SQLite run resolved to `mysql` / `kmsitdonation` /
`APP_ENV=local`. A previous regression run therefore MAY HAVE TOUCHED OR
RESET THE DEVELOPMENT DATABASE. The exact historical mutation cannot be
proven from available evidence, and actual historical data loss is NOT
claimed.

Current observed state: no development-database write was performed during
THIS pass — the genuine SQLite regression used `:memory:` only, no MySQL
full-suite run was attempted, no migration/seed/truncate touched
`kmsitdonation`, and the only operations near that database were the
inherited-environment reads during diagnosis.

Per the pass mandate, during this pass the development database was NOT
reset, seeded, restored, migrated, truncated, or otherwise modified.

## 10. Source / test modification statement

- Application source modified: **NO.**
- Tests modified: **YES — exactly one authorized expectation update:**
  `tests/Feature/Audit/AuditFoundationTest.php` (inventory `81 -> 90`,
  `87 -> 96` + comment; no registrar/registry/contract change).
- Migrations modified: **NO.**
- Global test harness modified: **NO** (`TruncatesInMemorySqlite`,
  `phpunit.xml`, environment loading, `RefreshDatabase` untouched).
- Specification / ADR / governance / RECON modified: **NO** (this file only).
- Push: NOT PERFORMED. Merge: NOT PERFORMED.

## 11. Finding status

- DeepSeek: BLOCKER 0, MAJOR 0, MINOR 0, GATE-IMPACT 0.
- Editorial: 4 — REVIEW-12, REVIEW-16, REVIEW-17, REVIEW-18 (accepted debt).
- Regression triage: **149/149 ACCOUNTED FOR** — 148 environment/harness,
  1 genuine IMP-008 expectation (**REMEDIATED / CLOSED**).

## 12. Final regression verdict

**FULL REGRESSION EVIDENCE CORRECTED — READY FOR CODEX FINAL SEMANTIC /
CLOSURE AUDIT.**

Rationale: the genuine SQLite `:memory:` full suite passes (778 tests,
0 failures, 6 expected MySQL-only concurrency skips); the single genuine
IMP-008 expectation gap is closed with independently verified inventory
(90 active / 96 total); IMP-008 MySQL concurrency (4/4) and migration
UP-DOWN-UP evidence stand; all static/build/security checks pass; and the
148 unrelated harness failures are dispositioned as out-of-scope follow-up
debt rather than hidden.
