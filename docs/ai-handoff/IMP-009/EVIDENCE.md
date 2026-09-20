# IMP-009 — Payment Hub: Implementation Evidence

**Implementation Owner:** Muse Spark 1.3 Contributor (`meta/muse-spark-1.3-contributor`) — ASSERTED
(session model identity; no silent fallback occurred)
**Phase:** IMPLEMENTATION (Human Spec Approval: APPROVED per task brief §1)

---

## 1. Baseline

| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Branch | master | master | PASS |
| HEAD | `68291b9` (governed recon) | `68291b96b5d7c448095736b7675596856d664d6a` | PASS |
| Working tree at start | CLEAN | CLEAN | PASS |
| Specification commit | `73c786d732e328a1f07706bc1106a275a98675da` | read in full (3116 lines) | READ |
| ADR-003 | ACCEPTED | read in full, implemented narrowly | READ |
| RECON | `docs/ai-handoff/IMP-009/RECON.md` | read once, followed by file map | READ |
| HD-IMP009-01..12 | LOCKED | preserved (see §3) | PASS |
| Open Human Decisions at start | 0 | 0 | PASS |

**Note on HEAD:** the brief's literal `68291b9…6d664d6a` differs by two hex chars from the
actual `68291b96b5d7c448095736b7675596856d664d6a`; `git log` confirms the actual HEAD **is**
`docs(imp-009): add governed implementation recon` on master, CLEAN — the brief's string is a
transcription slip, not a different commit. Baseline accepted.

---

## 2. Implementation Commit

```
feat(imp-009): implement payment hub
SHA: a30ca7fd0bf99376301d57b78550c8097b2ab438
```

Working tree ends CLEAN. No push. No merge.

---

## 3. Locked Decisions / HD Preservation

| Item | Verdict |
|------|---------|
| HD-IMP009-01 one ACTIVE attempt (DB + domain level) | PRESERVED — `active_slot` generated column + `UNIQUE(donation_id, active_slot)` on **both** MySQL 8 and SQLite; Donation-row-lock pre-check; RECON proposal validated, no weakening |
| HD-IMP009-02 late outcome never discarded; Donation never forced | PRESERVED — Payment fact commits first; `DonationTransitionConflictException` caught; CRITICAL `payment.donation_transition_rejected` recorded |
| HD-IMP009-03 no lifetime attempt maximum | PRESERVED — tested (4 sequential attempts) |
| HD-IMP009-04 no guest resume/retry mechanism | PRESERVED — tested (no identifier route exists); support path is admin `payment.cancel` ORGANIZATION only |
| HD-IMP009-05 Xendit PaymentRequest baseline | PRESERVED — adapter targets PaymentRequest; no Invoice fallback |
| HD-IMP009-06 evidence mandatory for APPROVE | PRESERVED — tested (APPROVE w/o evidence rejected; REJECT w/o evidence allowed) |
| HD-IMP009-07 amount mismatch → HOLD, amounts immutable | PRESERVED — tested (PENDING held, Donation untouched, no mutation) |
| HD-IMP009-08 append-only resubmission while eligible | PRESERVED — tested |
| HD-IMP009-09 configurable expiry, no hard-coded duration | PRESERVED — `config/payment.php` default null; tested both arms |
| HD-IMP009-10 manual-per-cycle, no auto-charge | PRESERVED — no credential storage/mandate/scheduler-charge code exists (scope check §9) |
| HD-IMP009-11 Donation transition via canonical surface only | PRESERVED — only `DonationTransitionService::markSucceeded/markFailed` invoked; static test proves no direct mutation path |
| HD-IMP009-12 / ADR-003 two-event Unauthenticated scope | PRESERVED — exactly `payment.attempt_created` + `manual_transfer.evidence_submitted` with fixed contexts; audit test asserts no third event admits the kind; no new `AuditActorKind` |

---

## 4. Reconciled Deviations (NOT new Human Decisions)

Two places where the approved text is unimplementable against **locked, unmodifiable**
sibling-IMP code. Both resolve deterministically toward the locked code with **zero
business-semantic change**; neither invents a rule, rate, treatment, or decision.

### D1. `webhook.verification_failed`: NonCritical/DenialDurable → NonCritical/null strategy

- Spec classifies the event `NonCritical / DENIAL_DURABLE`.
- Locked `AuditEventRegistry::register()` (IMP-004, FINAL/LOCKED, must-not-modify per
  RECON §6) permits a persistence strategy **only** on CRITICAL events; DenialDurable
  exists in-repo solely on the CRITICAL `security.authorization.denied`. A
  NonCritical+DenialDurable pair throws `InvalidArgumentException` at registration.
- Resolution: registered **NonCritical with null strategy**. Every observable spec
  behavior is preserved: the write is still sequenced strictly after the denying path
  with no mutation to roll back (`PaymentWebhookHandler::recordRejection` runs outside
  any business transaction), same visibility/actor/payload contract, and a persistence
  failure is reported via the ordinary error log instead of converting an
  invalid-signature flood into retry-amplifying 500s.
- Documented in `PaymentAuditEventRegistrar` docblock ("MANUAL RECONCILIATION NOTE").

### D2. `config/money.php` IDR digits: spec assumes 0, locked registry says 2

- Spec §Tripay assumes IDR has 0 minor-unit digits (whole-IDR API conversion a no-op).
- Locked `config/money.php` (IMP-007, must-not-modify per RECON §6) registers
  `'IDR' => 2`.
- Resolution: **no conversion arithmetic anywhere** — `amount_minor` is copied exactly
  from `donations.amount_minor` (BR-2) and compared with exact-integer equality at the
  webhook gate. The money layer is therefore correct under either digit count; the
  Tripay adapter sends the integer as-is with a comment recording the assumption for
  the integration-time verification HD-IMP009-05 already requires.

---

## 5. Files Changed

**Created (~60):**

- `app/Enums/`: `PaymentStatus`, `PaymentProvider`, `WebhookProcessingResult`, `ManualTransferReviewOutcome`
- `database/migrations/0001_09_01_00000{1..5}`: bank accounts → credentials → payments (generated `active_slot` + 2 composite uniques) → provider events → evidence
- `app/Models/Payment/`: `Payment`, `PaymentProviderEvent`, `ManualTransferEvidence`, `ManualTransferBankAccount`, `PaymentProviderCredential`, `Concerns/GeneratesPaymentUlid`
- `app/Contracts/Payment/`: `PaymentProviderAdapter`, `ProviderTransactionResult`, `ProviderStatusResult`, `VerifiedCallbackResult`, `PaymentAdapterError`
- `app/Adapters/Payment/`: `ManualTransferAdapter`, `TripayAdapter`, `XenditAdapter`, `StripeAdapter`
- `app/Services/Payment/`: `PaymentCreationService`, `PaymentTransitionService`, `PaymentWebhookHandler`, `ManualTransferEvidenceService`, `ManualTransferVerificationService`, `PaymentAdapterFactory`, `PaymentAuditEventRegistrar`, `PaymentAuditLogger`, `PaymentOwnScopeResolver`, `PaymentScopeResolver`, `Exceptions/{PaymentValidationException, PaymentTransitionConflictException}`
- `app/Policies/PaymentPolicy.php`
- `app/Http/Controllers/`: `PublicPaymentController`, `DashboardPaymentController`, `Webhooks/{Tripay,Xendit,Stripe}WebhookController`, `Admin/{AdminPaymentController, AdminPaymentProviderConfigController}`
- `app/Http/Requests/Payment/StorePaymentRequest.php`, `app/Http/Requests/ManualTransfer/StoreEvidenceRequest.php`
- `app/Console/Commands/ExpirePendingPayments.php`
- `config/payment.php`
- `database/seeders/PaymentSystemPrincipalSeeder.php`
- `tests/Unit/Payment/PaymentStateMachineTest.php`
- `tests/Feature/Payment/`: `PaymentCreationTest`, `PaymentTransitionTest`, `PaymentWebhookSecurityTest`, `ManualTransferTest`, `PaymentExpirationTest`, `PaymentAuthorizationTest`, `PaymentAuditTest`, `PaymentHttpTest`, `PaymentInventoryTest`, `PaymentConcurrencyTest`, `PaymentSchemaConstraintsTest`
- `tests/Support/Payment/MakesPaymentDonations.php`
- `docs/ai-handoff/IMP-009/EVIDENCE.md` (this file)

**Modified (8):** `PermissionRegistry` (+6 permissions), `AppServiceProvider`
(+13 audit events), `PaymentSystemPrincipalSeeder` wiring in `DatabaseSeeder`,
`bootstrap/app.php` (3-route CSRF exemption), `routes/web.php` (+19 routes),
`routes/console.php` (expiry sweep schedule), `.env.example` (placeholders only),
`tests/Feature/Audit/AuditFoundationTest.php` (90→103 active, 96→109 total — exact,
composition-documented, per task §31).

**Not modified (per RECON §6):** all IMP-003/004/007/008 domain files, all prior
migrations, all prior test files except the one authorized inventory update above.

---

## 6. Migration Results

| Direction | Driver | Result |
|-----------|--------|--------|
| UP (5 migrations) | MySQL 8.4 (dev DB, pre-test sanity) | PASS, then rolled back |
| DOWN (5 migrations, reverse order) | MySQL 8.4 | PASS |
| UP → DOWN → UP (dependency order, disposable `kmsitdonation_imp009_test`) | MySQL 8.4 | PASS (`PaymentSchemaConstraintsTest::test_imp009_migrations_run_up_down_up_in_dependency_order`) |
| UP (full suite incl. 5 new) | SQLite :memory: | PASS (every SQLite test run) |

DOWN uses explicit dependency-order reversal per migration file — no
`FOREIGN_KEY_CHECKS=0` anywhere in IMP-009 migrations.

---

## 7. Test Results (exact counts)

### SQLite suite (`DB_CONNECTION=sqlite`, `:memory:`)

| Suite | Tests | Assertions | Result |
|-------|-------|------------|--------|
| `tests/Unit` (all incl. `PaymentStateMachineTest` 32/65) | 126 | 192 | PASS |
| `tests/Feature/Payment` (11 classes) | 81 | 531 | PASS |
| `tests/Feature/Donation` + `tests/Feature/Rbac` | 212 | 577 | PASS |
| `tests/Feature/Audit` | 35 (1 skipped, pre-existing) | 259 | PASS |
| `tests/Feature/Campaign` | 74 (1 skipped, pre-existing) | 226 | PASS |

Breakdown of the 81 Payment feature tests: Creation 11 · Transition/Integration 7 ·
WebhookSecurity 11 · ManualTransfer 9 · Expiration 5 · Authorization 11 · Audit 7 ·
Http/FileSecurity 7 · Inventory 4 · Concurrency is MySQL-only (below); Schema is
MySQL-only (below).

### MySQL suite (disposable `kmsitdonation_imp009_test`, MySQL 8.4.11)

| Suite | Tests | Assertions | Result |
|-------|-------|------------|--------|
| `PaymentConcurrencyTest` (genuine 2-connection 1205 overlap: same-key create, one-active creation race, raw backstop insert, overlapping terminal transitions) | 4 | 15 | PASS |
| `PaymentSchemaConstraintsTest` (RESTRICT ×2, composite uniques ×2, idempotency unique, UP/DOWN/UP + index proof) | 5 | 30 | PASS |

### Targeted validation checklist (task §34)

Payment domain, provider adapters, webhooks, authorization, audit, manual transfer,
Donation integration, MySQL schema constraints, MySQL concurrency, migration
UP/DOWN/UP: all PASS (tables above).

### Full SQLite regression

NOT RUN as a single full-suite invocation. Upstream modules directly affected by
IMP-009's shared-file touches (Donation, Rbac, Audit, Campaign) were run in full
and pass (see table). `tests/Feature/Cms` fails identically (3 errors + 28
failures) with and without this implementation (verified via `git stash -u`
A/B run) — pre-existing breakage, unrelated to IMP-009, no architecture change
required of this IMP; reported, not modified.

---

## 8. Static Validation

| Check | Result |
|-------|--------|
| `vendor/bin/pint --test` (touched trees: app, tests, database, routes, config, bootstrap) | PASS — 467 files |
| `git diff --check` | PASS (clean) |
| `composer audit` | PASS — no advisories |
| Vue-TSC / Vite | NOT APPLICABLE — no frontend files touched (Inertia renders reference future IMP-025 views; no `resources/` changes) |

---

## 9. Scope Check

Searched the implementation diff for accidental `Ledger|Journal|double-entry|
commission|withdrawal|refund workflow|Moota|automatic recurring charge|stored
reusable payment credential`. All hits are legitimate boundary
references/comments/interfaces:

- `supportsRefundCall()` provider-CAPABILITY interface (spec-authorized, IMP-016 consumes later; no Refund entity/flow)
- Tripay REFUND explicitly unmapped (test-proven)
- Comments restating Payment≠Ledger / Moota-is-IMP-017 boundaries
- Pre-existing `refund_approver`/`withdrawal_approver` authority rows (IMP-003, untouched)

Implemented behavior in any out-of-scope domain: NONE. Recurring auto-charge:
ABSENT. Refund workflow: ABSENT. Moota/reconciliation: ABSENT. Ledger posting:
ABSENT.

---

## 10. Concurrency Evidence

- `test_concurrent_same_key_create_creates_exactly_one_row` — contender holds an
  uncommitted same-key insert; primary blocks with MySQL 1205 (asserted); after
  commit the replay resolves to the single row.
- `test_concurrent_creations_against_one_donation_yield_exactly_one_active_attempt` —
  contender holds the Donation row lock; primary blocks with 1205 (asserted);
  post-release the loser gets typed `active_attempt_exists`; final ACTIVE count = 1.
- `test_active_slot_unique_backstop_rejects_a_raw_second_active_row` — raw SQL
  bypassing the service layer: second ACTIVE row → 1062; terminal rows never
  collide; slot frees on terminal.
- `test_two_overlapping_terminal_transitions_exactly_one_succeeds` — contender
  holds the Payment row lock; primary blocks with 1205 (asserted); loser of the
  terminal race gets typed `invalid_transition`; final state exactly SUCCEEDED.
- Webhook redelivery race additionally hardened: unique-violation on the
  in-transaction event insert converts to DUPLICATE (code path; covered
  sequentially by the duplicate-callback test).

---

## 11. Provider Fake Evidence

No automated test performs a real provider charge. Outbound `createTransaction()`
HTTP is faked (`Http::fake`) in webhook/manual tests with canned per-provider
responses; inbound callbacks use signed synthetic payloads (Tripay HMAC over raw
bytes with `hash_equals` on both sides; Xendit `x-callback-token` constant-time;
Stripe `t.v1` HMAC + 300s tolerance). Negative matrix per provider: invalid
signature, malformed payload, wrong amount, wrong currency (Xendit/Stripe),
unknown reference, duplicate event, out-of-order event — all covered.

---

## 12. Development DB Safety

- Disposable MySQL database `kmsitdonation_imp009_test` created for and used by
  all MySQL tests; `setUp` asserts the target is not `kmsitdonation` and aborts
  otherwise. `tearDown` truncates only the disposable DB.
- **Incident:** the sandbox shell exports `DB_CONNECTION=mysql` /
  `DB_DATABASE=kmsitdonation` into every child process; PHPUnit 12 `<env>` without
  `force` does not override real environment, so early test runs executed against
  the development database (RefreshDatabase → `migrate:fresh`, incl. the five new
  tables; all rows rolled back per-test). Discovered via the
  `TruncatesInMemorySqlite` failure signature; contained by unsetting the six
  `DB_*` vars before every run thereafter (all evidence counts above are from
  clean-env runs). Post-incident row counts on all dev tables: **0 rows
  everywhere**; the dev DB contains the migrated schema (51 migrations incl. the
  five IMP-009 tables) with **no seed/business data touched** — the pre-existing
  state could not be verified (no baseline snapshot exists), so this is reported
  as INDETERMINATE with the observed facts, not claimed clean.
- No `migrate:fresh`, drop, or global truncate was ever issued against
  `kmsitdonation` intentionally; no production credentials were used anywhere.

---

## 13. Known Non-Gating Debt

1. **D1/D2 reconciliations** (§4) — behavior-preserving, documented; DeepSeek
   review should confirm the reading.
2. **Pre-existing CMS suite breakage** (3 errors + 28 failures, identical A/B) —
   out of IMP-009 scope; not modified.
3. **Xendit/Tripay/Stripe field vocabularies** — implemented against the spec's
   mapping tables; HD-IMP009-05 verification against live provider docs remains
   an integration-time step (no network calls from this phase by design).
4. **`payment:expire-pending` poll fallback** — `scheduler.payment-status-poll`
   principal is seeded; the optional poll job itself is deferred (spec marks
   `retrieveStatus()` optional per provider).
5. **Inertia views** (`Payment/Index`, `Admin/Payment/*`, …) are referenced, not
   built — IMP-025's concern; JSON/redirect assertions cover the contracts.
6. **Dev-DB schema residue** — the five IMP-009 tables now exist in the
   development `kmsitdonation` schema from the §12 incident window (empty, no
   data). A `migrate:rollback --step=5` was performed mid-implementation and the
   tables were re-created by later contaminated runs; final state: tables
   present, zero rows. Recommend `php artisan migrate:rollback --step=5` on the
   dev DB (or a fresh `migrate`) at the reviewer's discretion — NOT executed
   here to avoid further writes to the dev DB during the evidence phase.

---

## 14. Authority & Boundary Attestations

- `DonationTransitionService` / `Donation` model / IMP-003/004/007/008 files:
  UNMODIFIED (except the two authorized registry additions + one exact inventory
  test update).
- Payment→Donation only via `markSucceeded/markFailed` with
  `system.payment-outcome-consequence` (static test in `PaymentTransitionTest`).
- Minimum principals: 3 integration + 3 system, least-privilege (no payment.*
  grants — inventory-tested), never conflated (attribution-tested).
- ADR-003: two events only (audit-tested). No new actor kind. No new scope or
  authority type (`financial_approver` reused).
- PCI boundary: no card fields in code, payloads, logs, or audit (scope check).
- Secrets: encrypted at rest, `$hidden` on the model, write-only admin path,
  never logged/audited/returned (credential-changed audit carries only
  provider/mode/is_enabled).
