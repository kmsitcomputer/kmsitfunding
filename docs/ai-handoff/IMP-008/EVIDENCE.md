# IMP-008 Regression Evidence (Pass 3 — Codex final audit remediation)

> Evidence only. Introduces no business requirements, no specification
> semantic amendment, no ADR change, no governance change. One
> documentation-fidelity correction to the spec's literal CHECK example
> (section 13) — see "DOCUMENTATION FIDELITY CORRECTION ONLY" below.

## 0. Codex final audit input and remediation HEAD

| Field | Value |
|---|---|
| Branch (baseline) | `master` |
| Starting HEAD (expected `ab55355`) | `ab55355034b741deeb847d899327a8115b68c412` — matched, clean tree |
| Codex final audit | FAIL — CODEX-IMP008-FINAL-01 (MAJOR, gate), -02 (MAJOR, gate), -03 (MINOR, non-gate) |
| Claude reconciliation | EXISTING CONTRACT SUFFICIENT — route patch to Muse; no new human decision, no spec amendment, no ADR |
| Remediation commit | single `fix(imp-008): close Codex final audit findings` commit on `master` (hash in final report; no push/merge) |

Sections 1–12 below preserve the Pass 2 record verbatim (earlier
invalid-regression history is NOT rewritten or hidden). Pass 3 evidence
follows in sections 13+.

---

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

---

## 13. Pass 3 — Codex final audit remediation (FINAL-01 / FINAL-02 / FINAL-03)

### 13.1 FINAL-01 — donor authorization (CLOSED)

Codex found donor-owned listing relied on ownership filtering without an
explicit policy authorization. Patched (minimum sufficient change, existing
IMP-003 `AuthorizationEvaluator` chain — no parallel auth system):

- `DonationPolicy::viewOwnList()` (new): `donation.view` at OWN scope via
  the canonical evaluator, anchored on a transient self-owned Donation.
- `RecurringPlanPolicy::viewOwnList()` (new): `donation.recurring_plan.manage`
  at OWN scope, same anchoring.
- `RecurringPlanPolicy::create()` (patched in place — creation is a
  donor-only path, no admin create endpoint exists): now requires
  `donation.recurring_plan.manage` at OWN scope. An ORGANIZATION-only grant
  MUST NOT authorize donor-owned creation; GLOBAL_PLATFORM passes only where
  the existing IMP-003 scope contract allows (no invented scope semantics).
- `DashboardDonationController::index()` / `indexPlans()`: `abort_unless`
  on the new list gates BEFORE querying (ownership filtering is not the
  authorization decision). `storePlan()` already called `$policy->create()`.
- Incidental defect fixed (FINAL-01 test G was otherwise unprovable):
  `StoreRecurringPlanRequest` required `frequency` `size:16`, which rejects
  the approved `MONTHLY` value (7 chars) on every HTTP create — relaxed to
  `max:16`; the service allow-list (BR-9) remains authoritative.

New HTTP-level proof (`tests/Feature/Donation/DonationDashboardAuthorizationTest.php`,
11 tests / 20 assertions — PASS):

- A. permissionless principal → 403 on GET /me/donations.
- B. ORGANIZATION-only `donation.view` → 403 on GET /me/donations.
- C. OWN `donation.view` → 200, sees own ULID only.
- D. suspended (`SecurityRestriction::Suspended`) and disabled
  (`IdentityLifecycle::Disabled`) principals → 403 despite a valid grant.
- E. permissionless principal → 403 on plan list AND plan create.
- F. ORGANIZATION-only `recurring_plan.manage` → 403 on plan create.
- G. OWN `recurring_plan.manage` → redirect, ACTIVE plan owned by the donor.
- H. cross-donor read of another donor's donation/plan → 403.

No existing test weakened.

### 13.2 FINAL-02 — deferred generation engine removed (CLOSED)

Per Claude reconciliation the spec explicitly defers the concrete recurring
occurrence scheduling/execution engine ("Out of Scope"), so the engine was
REMOVED, not completed. No ends_at behavior, no COMPLETED transitions, no
retry engine, no new scheduling/failure semantics added.

Removed (verified not required by any approved IMP-008 acceptance criterion):

- `app/Console/Commands/GenerateRecurringOccurrences.php` (deleted).
- `routes/console.php`: `donation:generate-occurrences` schedule line
  (expiry sweep + CMS schedulers untouched; comment narrowed).
- `RecurringPlanService::generateOccurrence()` + exclusive helpers
  `resolvePlanDonor()` / `nextMonthlyOccurrence()` + now-unused imports.
- `RecurringPlanService::markOccurrenceFailed()` — execution capability
  serving only the deferred flow; approved lifecycle is
  create/pause/resume/cancel, and the BR-11 FAILED-terminal rule is now
  proven schema/state-only per the spec's own "Tests Required".
- Stale generation references in `DonationSystemPrincipalSeeder`
  (expiry role, least-privilege grants, and assignment logic untouched).

Preserved: recurring plan/occurrence tables + FK/unique contracts,
create/pause/resume/cancel, expiry sweep, `donation.scheduler` identity,
BR-9 allow-list, BR-11 no-retry state rule, `donation_id` UNIQUE contract.

Test classification (documented per mandate):

- REMOVED (solely engine): `RecurringPlanTest` — generation_creates,
  generation_inactive, marking_non_scheduled, money_anonymity,
  repeated_sweep, cancelled_generates (6); `DonationConcurrencyTest` —
  two_overlapping_workers, inactive_plan (2).
- PATCHED (mixed): `failed_occurrence_is_never_retried…` →
  `a_failed_occurrence_is_terminal_state_only` (schema/state-only);
  `occurrence_donation_id_uniqueness…` now sources its Donation via
  `DonationService` (approved persistence contract, no engine).
- KEPT: all create/pause/resume/cancel/frequency/currency/no-donation-on-plan tests.
- `DonationConcurrencyTest::disposableMysqlAvailable()`: same
  connect-to-disposable-DB-first probe fix as the XOR test (the surrounding
  process may carry `DB_DATABASE=:memory:`).

Scheduler proof: `grep -rn "generate-occurrences\|GenerateRecurringOccurrences"
routes/ app/Console/` → no hits outside historical RECON notes.

Prior editorial REVIEW-17 / REVIEW-18: CLOSED BY SCOPE REMEDIATION (they
described the deferred engine surface, which no longer exists in IMP-008).
REVIEW-12 / REVIEW-16: unchanged accepted debt.

### 13.3 FINAL-03 — donor-path XOR (CLOSED)

BR-2 is authoritative (full XOR). Release-state check: the IMP-008
migration (`1fa2aee`) is ahead of `origin/master` (unpushed) and IMP-008
has NOT passed Human Stage Gate — governance confirms the migration is
still unreleased and mutable within IMP-008. Patched in place (no
follow-up migration needed):

- `database/migrations/0001_08_01_000002_create_donations_table.php`:
  `chk_donations_donor_path` now enforces the full XOR (authenticated XOR
  guest; never both, never neither). `donor_display_name` untouched.
- `Donation::isDonorPathConsistent()` inspected: ALREADY the full XOR —
  NOT modified (per mandate).

Raw MySQL proof bypassing Eloquent
(`tests/Feature/Donation/DonationDonorPathCheckTest.php`, disposable
`kmsitdonation_imp008_xor`, NEVER `kmsitdonation` — 5 tests / 12 assertions,
PASS):

- authenticated-only row: ACCEPTED. Guest-only row: ACCEPTED.
- NEITHER: REJECTED by MySQL (error 3819, check-constraint-violated).
- BOTH: REJECTED by MySQL (error 3819) — the critical new proof.
- Migration UP → DOWN → UP: PASS (table gone after DOWN, re-created after
  UP, re-created CHECK still rejects BOTH).

SQLite skips this DB-CHECK test per existing driver convention; the
app-guard half is covered by `DonationSchemaConstraintsTest`.

### 13.4 DOCUMENTATION FIDELITY CORRECTION ONLY — NO SPECIFICATION SEMANTIC AMENDMENT

`docs/implementation/IMP-008-donation.md` "Database Impact" literal CHECK
example encoded only "not neither" while BR-2/domain model approve the full
XOR — a transcription defect. Corrected ONLY that literal expression to the
already-approved BR-2 invariant. BR-2, business semantics, Human Decisions,
and scope are UNCHANGED.

### 13.5 Pass 3 targeted tests (SQLite :memory:, APP_ENV=testing)

- `tests/Feature/Donation` (full dir): **82 tests, 286 assertions, 0 failed,
  2 skipped** (MySQL-only concurrency self-skips) — PASS.
- New `DonationDashboardAuthorizationTest`: **11 tests, 20 assertions** — PASS.
- `RecurringPlanTest` + `DonationAuthorizationTest` + `DonationHttpTest` +
  `DonationExpirationTest`: **33 tests, 93 assertions** — PASS.

### 13.6 Pass 3 genuine SQLite full regression

Clean child-process env (`APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`, array cache/session, sync queue, array mail;
`Application::runningUnitTests()=true`):

- **Tests: 786, Assertions: 2528, Failed: 0, Skipped: 4, Duration: ~129s,
  Exit code: 0 — PASS.**
- The 4 skips are the expected MySQL-only tests (concurrency + XOR CHECK).
- Delta vs Pass 2 (778/6): +11 auth tests, +5 XOR tests, −8 removed engine
  tests (6 RecurringPlan + 2 concurrency), −2 engine skips. Net:
  778 + 11 + 5 − 8 = 786. (The FAILED-terminal state test
  `a_failed_occurrence_is_terminal_state_only` was a rename/patch of the
  existing `failed_occurrence_is_never_retried…` test, NOT a newly added
  test — see section 13.2. DEEPSEEK-IMP008-POSTCODEX-01: CLOSED —
  EVIDENCE WORDING CORRECTED.)

### 13.7 Pass 3 MySQL IMP-008 tests (disposable DBs only, never `kmsitdonation`)

- XOR CHECK + reversibility (`kmsitdonation_imp008_xor`): **5 tests,
  12 assertions — PASS** (incl. BOTH-rejected + UP-DOWN-UP).
- Approved concurrency (`kmsitdonation_imp008_test`): **2 tests,
  9 assertions — PASS** (engine concurrency tests removed with the engine;
  NOT recreated).
- Repository-wide MySQL full regression: NOT attempted (pre-existing
  harness not MySQL-safe — unchanged).

### 13.8 Pass 3 static validation

| Check | Result |
|---|---|
| `Pint --test` | PASS — 404 files, exit 0 |
| `vue-tsc --noEmit` | PASS — exit 0 |
| `Vite production build` | PASS — built in ~4.1s |
| `Composer Audit` | PASS — no advisories |
| `git diff --check` | PASS — no whitespace errors |

### 13.9 Development DB safety

DEVELOPMENT DB HISTORICAL SAFETY: **INDETERMINATE** (unchanged — the Pass 2
statement stands and is not rewritten). During THIS pass no
`kmsitdonation` write was performed: SQLite regression used `:memory:`
only; MySQL runs targeted disposable DBs (`kmsitdonation_imp008_xor`,
`kmsitdonation_imp008_test`); the only `kmsitdonation`-adjacent operations
were read-only connection probes.

## 14. Pass 3 change-boundary classification

- FINAL-01: `app/Policies/DonationPolicy.php`, `app/Policies/RecurringPlanPolicy.php`,
  `app/Http/Controllers/Donation/DashboardDonationController.php`,
  `app/Http/Requests/Donation/StoreRecurringPlanRequest.php` (frequency
  `size:16` → `max:16` — authorization-enabling fix, no semantic change).
- FINAL-02: `app/Console/Commands/GenerateRecurringOccurrences.php` (deleted),
  `routes/console.php`, `app/Services/Donation/RecurringPlanService.php`,
  `database/seeders/DonationSystemPrincipalSeeder.php` (description/comments only).
- FINAL-03: `database/migrations/0001_08_01_000002_create_donations_table.php`.
- TEST: `tests/Feature/Donation/DonationDashboardAuthorizationTest.php` (new),
  `tests/Feature/Donation/DonationDonorPathCheckTest.php` (new),
  `tests/Feature/Donation/RecurringPlanTest.php`,
  `tests/Feature/Donation/DonationConcurrencyTest.php`.
- EVIDENCE: `docs/ai-handoff/IMP-008/EVIDENCE.md` (this file).
- SPEC-FIDELITY: `docs/implementation/IMP-008-donation.md` (CHECK literal only).

No unrelated file changed. Global test harness untouched (`TruncatesInMemorySqlite`,
`phpunit.xml`).

## 15. Pass 3 finding status

- Codex FINAL-01: CLOSED. FINAL-02: CLOSED. FINAL-03: CLOSED.
- BLOCKER 0, MAJOR 0, MINOR 0, GATE-IMPACT 0.
- Prior editorial REVIEW-12 / REVIEW-16: unchanged accepted debt.
  REVIEW-17 / REVIEW-18: CLOSED BY SCOPE REMEDIATION.
- Architectural escalation: NO. New human decision: NO.
- Application source modified: YES. Global test harness modified: NO.
- Development DB modified: NO (historical safety: INDETERMINATE).
- Push: NOT PERFORMED. Merge: NOT PERFORMED.

---

## 16. Pass 4 — final regression evidence refresh (DeepSeek post-Codex closure)

> Evidence only. No application source, test, migration, specification,
> ADR, governance, RECON, or harness change. The ONLY file modified in
> this pass is this EVIDENCE.md.

| Field | Value |
|---|---|
| Baseline HEAD | `54565911c40af61307680efe7101605739caa6c7` on `master`, clean tree — matched |
| Model | `meta/muse-spark-1.3-contributor` |
| DeepSeek post-Codex re-review | PASS — BLOCKER 0, MAJOR 0, MINOR 0, GATE-IMPACT 0 |
| Final implementation commit | `54565911c40af61307680efe7101605739caa6c7` (unchanged — evidence-only pass) |

### 16.1 DEEPSEEK-IMP008-POSTCODEX-01 — CLOSED (evidence wording corrected)

DeepSeek flagged the Pass 3 delta narrative ("+11 auth tests, +5 XOR
tests, −8 removed engine tests, +1 FAILED-terminal state test") as
arithmetically implying 787. The FAILED-terminal state test
(`RecurringPlanTest::test_a_failed_occurrence_is_terminal_state_only`)
was a rename/patch of the existing
`failed_occurrence_is_never_retried…` test (see section 13.2), NOT a
newly added test. Correct net: 778 + 11 + 5 − 8 = 786. The wording in
section 13.6 is corrected accordingly. The actual regression result is
UNCHANGED. **DEEPSEEK-IMP008-POSTCODEX-01: CLOSED — EVIDENCE WORDING
CORRECTED.**

### 16.2 True SQLite environment proof (clean child-process env)

Inherited process environment was verified polluted
(`APP_ENV=local`, `DB_CONNECTION=mysql`, `DB_DATABASE=kmsitdonation`)
so every test process below ran with scrubbed + forced values. A
temporary probe test (deleted afterwards) confirmed from inside the
application runtime:

- `APP_ENV = testing`
- `DB_CONNECTION = sqlite`
- `DB_DATABASE = :memory:`
- `CACHE_STORE = array`, `SESSION_DRIVER = array`,
  `QUEUE_CONNECTION = sync`, `MAIL_MAILER = array`
- `Application::runningUnitTests() = true`

Probe: **PASS** (1 test, 4 assertions). The development database
`kmsitdonation` was NEVER selected as a target by any process in this
pass.

### 16.3 Genuine SQLite full regression (sequential shards, same clean env)

A single-process full run was attempted but the shared host stalled
under parallel load; each suite directory was therefore executed
sequentially in the foreground under the identical clean
child-process environment, plus a single-process full-suite
confirmation run (exit 0). No failure anywhere.

| Shard | Tests | Assertions | Failed | Skipped |
|---|---|---|---|---|
| `tests/Unit` + `tests/Feature/FoundationSmokeTest.php` | 96 | 137 | 0 | 0 |
| `tests/Feature/Rbac` | 130 | 282 | 0 | 0 |
| `tests/Feature/Theme` | 46 | 233 | 0 | 0 |
| `tests/Feature/Identity` | 114 | 363 | 0 | 0 |
| `tests/Feature/Audit` | 35 | 259 | 0 | 1 (MySQL-only concurrency) |
| `tests/Feature/Campaign` | 74 | 226 | 0 | 1 (`CampaignLifecycleServiceTest::test_concurrent_publish_attempts_yield_exactly_one_success` — MySQL-only) |
| `tests/Feature/Cms` | 209 | 742 | 0 | 0 |
| `tests/Feature/Donation` | 82 | 295 | 0 | 0 (MySQL XOR + concurrency EXECUTED — see 16.4) |
| **Total** | **786** | **2537** | **0** | **2** |

Suite total: **786 tests, 0 failures**. Skips are 2 here (not 4 as in
Pass 3) because the disposable MySQL databases were reachable in this
environment, so the 2 Donation MySQL-gated tests executed and passed
instead of self-skipping. No failure hidden, reclassified, or patched.

### 16.4 MySQL IMP-008 final check (disposable DBs only, never `kmsitdonation`)

A. Donor-path XOR (`DonationDonorPathCheckTest`,
`kmsitdonation_imp008_xor`): **5 tests, 12 assertions — PASS**
(authenticated-only ACCEPTED, guest-only ACCEPTED, neither REJECTED
with MySQL error 3819, both REJECTED with 3819).
B. Approved concurrency (`DonationConcurrencyTest`,
`kmsitdonation_imp008_test`): **2 tests, 9 assertions — PASS**.
C. Migration UP → DOWN → UP: **PASS** (table gone after DOWN,
re-created after UP, re-created CHECK still rejects BOTH-populated
rows — proven inside the XOR test).
Repository-wide MySQL regression: NOT attempted (pre-existing harness
not MySQL-safe — unchanged, already documented).

### 16.5 Authorization HTTP smoke (final)

`DonationDashboardAuthorizationTest`: **11 tests, 20 assertions —
PASS** — permissionless denial (donation list, plan list, plan
create), ORGANIZATION-only denial (list + create where OWN required),
suspended/disabled denial despite valid grants, valid OWN success
(list sees own ULID only; create yields ACTIVE donor-owned plan),
cross-donor denial.

### 16.6 Deferred engine absence — confirmed

- `GenerateRecurringOccurrences`: absent (no hits in `app/`, `routes/`).
- `donation:generate-occurrences`: absent from `routes/console.php`
  (only approved sweeps scheduled: `content:run-scheduled-transitions`,
  `content:cleanup-media`, `donation:expire-pending`).
- `generateOccurrence` runtime method: absent
  (`RecurringPlanService.php` documents create/pause/resume/cancel
  only, "no automatic occurrence generation").
- Automatic occurrence-generation job/listener/endpoint: absent
  (no `app/Jobs` directory; no scheduler/generator references).
**DEFERRED ENGINE ABSENT: YES.** Nothing modified.

### 16.7 Static validation — all PASS

| Check | Result |
|---|---|
| `Pint --test` | PASS — 404 files, exit 0 |
| `vue-tsc --noEmit` (`npm run type-check`) | PASS — exit 0, no output |
| `Vite production build` (`npm run build`) | PASS — exit 0, built in 4.43s |
| `Composer Audit` | PASS — "No security vulnerability advisories found", exit 0 |
| `git diff --check` | PASS — exit 0, no whitespace errors |

### 16.8 Pass 4 finding status

- CODEX FINAL-01: CLOSED. FINAL-02: CLOSED. FINAL-03: CLOSED.
- DeepSeek post-Codex: PASS. DEEPSEEK-IMP008-POSTCODEX-01: CLOSED.
- REVIEW-17 / REVIEW-18: CLOSED BY SCOPE REMOVAL.
- REVIEW-12 / REVIEW-16: accepted non-gating editorial debt (NOT
  closed, NOT claimed closed).
- BLOCKER 0, MAJOR 0, MINOR 0, GATE-IMPACT 0.
- Application source modified: NO. Tests modified: NO. Migrations: NO.
  Spec/ADR/governance/RECON/harness: NO. Evidence modified: YES (this
  file only).
- Development DB touched during this run: NO. Historical safety:
  INDETERMINATE (unchanged, not rewritten).
- Push: NOT PERFORMED. Merge: NOT PERFORMED.
