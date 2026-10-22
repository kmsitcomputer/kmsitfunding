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
and pass (see table).

Historical note on `tests/Feature/Cms`: the "3 errors + 28 failures
(pre-existing)" observation recorded during the implementation phase was a
contaminated/harness-era result — it predates the test-harness hardening
(`tests/bootstrap.php` hard-forcing testing/sqlite/:memory: plus the
`force="true"` phpunit.xml env, both landed in the remediation commit
`925bbba`). The independent re-review reproduced the current
hardened-harness result: Cms 209/209 PASS. History is NOT rewritten:
the earlier observation stands as the pre-hardening record; the current
result stands as the post-hardening record. No architecture change was
required of this IMP for either observation; CMS remains out of
IMP-009 scope and unmodified.

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

---

## 15. DeepSeek Remediation Evidence (recovery session, 2026-09-20)

**Remediation Owner:** Muse Spark 1.3 Contributor — ASSERTED (session model
identity; recovery brief EXPECTED MODEL `meta/muse-spark-1.3-contributor`).
**Mode:** PATCH — resumed the prior session's uncommitted working tree; no
`reset`/`restore`/`clean`/checkout performed at any point.
**Starting HEAD:** `c5499e0e6d07b48775debabf0cb6113fd8f78e54` (pre-remediation
evidence commit, working tree dirty as expected).
**Remediation commit:** `925bbba fix(imp-009): remediate independent review
findings` (this session). NO PUSH. NO MERGE.

### HD-IMP009-13 status: IMPLEMENTED (APPROVED decision, executed)

Canonical Money remains `amount_minor` + `CurrencyMinorUnits`; locked IDR
digits = 2, so Rp 10,000 = 1,000,000 canonical `amount_minor`. Explicit
integer-only canonical↔provider conversion at the adapter boundary
(`app/Support/Money/ProviderAmountConverter.php`): Tripay whole-IDR,
Xendit major units, Stripe smallest unit (sen for IDR — IDR is NOT a Stripe
zero-decimal currency), manual_transfer identity. No floats, no silent
rounding (exact-divisibility guard → typed
`provider_amount_not_representable`), never cross-unit comparison (oracle
tests prove a raw-canonical echo into a provider-unit field mismatches).

### F-01..F-16 closure (read from the actual patch + code/test markers)

| Finding | Disposition | Evidence |
|---------|-------------|----------|
| F-01 (BLOCKER: provider call only on inserting call) | CLOSED | `$created` flag in `PaymentCreationService`; replay-counting test |
| F-02 (auth creation policy) | CLOSED | `createOwn` check in `PublicPaymentController::store` + 4 HTTP denial tests |
| F-03 (guest creation guest-donation only) | CLOSED | `donor_principal_id === null` gate + denial test |
| F-04 (creation-flow status read, HD-IMP009-04) | CLOSED | persistent creation-session possession, 404 otherwise; client_secret test |
| F-05 (guest evidence, HD-IMP009-04) | CLOSED — stop condition NOT triggered | session-bound guest upload (same persistent possession concept as F-04), no bearer token invented; spec "Authorization" expressly gives evidence the creation guest-access shape |
| F-06 (canonical credential payload) | CLOSED | `ProviderCredentialPayload` + round-trip/malformed tests |
| F-07 (pre-row provider/currency gate) | CLOSED | gate + no-row/no-call tests; manual_transfer static-list exemption (regression found + fixed this session) |
| F-08 (canonical Money conversion) | CLOSED | converter unit (11 tests) + oracle feature (10 tests) |
| F-09 (forensic signature_valid) | CLOSED | `signatureValid` field; post-verification failures verified true, pre-verification paths verified false-by-construction |
| F-10 (OWN listing resolver) | CLOSED | `PaymentOwnScopeResolver` in `viewOwnList` + semantics test |
| F-11 (bank-accounts route) | CLOSED | route ordering + `where()` constraint + controller test |
| F-12 (unmarked minor — covered by adapter hardening) | CLOSED | per-provider required-field credential decode on every adapter path |
| F-13 (lock order + late outcome) | CLOSED | Donation-then-Payment sweep lock order; terminal-review tests |
| F-14 (webhook.received subject) | CLOSED | event-row subject assertion |
| F-15 (dual-constraint disambiguation) | CLOSED | `active_attempt_exists` vs replay test coverage |
| F-16 (unmarked minor — channel requirement) | CLOSED | `channel_required` typed rejection for Tripay/Xendit |

BLOCKER remaining: 0. MAJOR remaining: 0. MINOR remaining: 0.
EDITORIAL remaining: 0. GATE-IMPACT remaining: 0. New Human Decision
required: NONE.

Correction note (post-review micro-remediation): the prior revision of
this table carried the heading "F-01..F-21" and a catch-all
"F-17..F-21 (editorial/governance)" row. No F-17..F-21 findings exist
in the independent review record; the code anchors at most F-01..F-16
(F-12 and F-16 are unmarked minors closed by adapter hardening). The
heading and rows above are corrected to F-01..F-16. No historical test
evidence is altered by this correction.

### Recovery-session corrections to the inherited work

1. **F-07 regression (found by running the suite):** the new pre-row gate
   rejected `manual_transfer` creation whenever no bank accounts were
   seeded (55 errors + 3 failures). Fixed narrowly: manual_transfer is
   exempt from the static-list gate (operational config, baseline
   behavior); converter-gate currency validity still applies. Suite
   returned to green; no other file touched.
2. **Stripe webhook test field bug:** the inherited test sent
   `total_amount` while the adapter reads `amount`, silently skipping the
   amount gate. Corrected to `amount` + added
   `test_stripe_amount_mismatch_is_rejected_before_any_transition`.
3. **Workspace hygiene:** removed stray `100` file (103-byte accidental
   `php -r` shell artifact: a PHP parse-error string; no project
   purpose, not IMP-009 content).
4. **Pint:** 3 style issues in inherited files auto-fixed (imports/
   whitespace only); final `pint --test` PASS (473 files).

### Validation (this session, effective environment proven by
`TestHarnessEnvironmentTest`: testing / sqlite / :memory:)

| Check | Result |
|-------|--------|
| Feature suite (803 tests, excl. 2 MySQL payment files) | PASS, exit 0, 2 pre-existing SQLite skips (audit/campaign concurrency) |
| `PaymentSchemaConstraintsTest` (disposable `kmsitdonation_imp009_test`, UP/DOWN/UP) | 5/5 PASS |
| `PaymentConcurrencyTest` (disposable DB, genuine 1205 overlap ×3 + backstop) | 4/4 PASS |
| `tests/Unit` | 137/137 PASS |
| Payment feature incl. new tests | 118→119 PASS (Stripe mismatch added) |
| Donation + Rbac + Audit integration | 247 PASS (1 pre-existing skip) |
| Campaign | 74 PASS (1 pre-existing skip) |
| `vendor/bin/pint --test` | PASS |
| `composer audit` | no advisories |
| `git diff --check` | clean |
| Frontend checks | NOT APPLICABLE — no `resources/` changes |

Full-suite single-process note: `php vendor/bin/phpunit` as one
invocation stalls in this Windows shell (no CPU, no output; pipe-EOF
held open); the identical test set passes as documented chunks above
plus the file-redirected 803-test Feature run (exit 0). No test was
excluded from evidence except the 9 MySQL tests, which ran separately
against the disposable DB and pass.

Development DB: NOT TOUCHED DURING RECOVERY/REMEDIATION. Historical dev
DB: ENVIRONMENT SIDE EFFECT YES / DATA LOSS INDETERMINATE / SCHEMA
RESIDUE YES (unchanged, §12/§13.6).

Scope leakage: NONE (no Ledger/commission/refund/Moota/auto-charge code;
no Redis/Kafka/microservice/tenant additions; no invented rates, limits,
or accounting entries). Working tree after this commit: CLEAN except
this EVIDENCE.md update (committed separately below). PUSH: NOT
PERFORMED. MERGE: NOT PERFORMED.

FINAL VERDICT: REMEDIATION COMPLETE — READY FOR DEEPSEEK RE-REVIEW

---

## 16. Codex Final Semantic Audit — FAIL → Remediation (2026-09-20)

**Codex verdict on HEAD `b1938a4`:** FAIL — REMEDIATION REQUIRED
(BLOCKER 0 / MAJOR 2 / MINOR 0 / EDITORIAL 1). Gate-impacting:
R-01 (guest idempotent replay establishes session possession) and
CODEX-F-01 (Manual Transfer amount mismatch approvable to
SUCCEEDED). No new Human Decision required. This section records
the patch only; all §§1–15 history above is preserved unchanged.

### R-01 remediated: replay is not recovery

- `PaymentCreationService::create()` now returns the explicit
  `PaymentCreationOutcome { Payment $payment; bool $created }`
  contract (new file
  `app/Services/Payment/PaymentCreationOutcome.php`) — CREATED vs
  IDEMPOTENT_REPLAY is carried explicitly, never inferred from
  timestamps or heuristics. Both the in-transaction replay path and
  the unique-violation catch path return `created: false`.
- `PublicPaymentController::store()` calls `rememberGuestPayment()`
  ONLY when `$outcome->created` is true. Same-session replay stays
  usable through the possession already in `guest_payment_ulids`;
  a fresh anonymous session replaying Donation ULID + provider +
  valid Idempotency-Key receives the same Payment per idempotency
  semantics but gains NO session possession (status → 404,
  evidence → 403).
- Idempotency semantics preserved: same key → same Payment, one
  provider transaction, one Payment row.
- Tests: service-level created/replay flag test; same-session
  replay keeps possession without a second row; fresh-session
  replay gains no possession (status denied, evidence denied).

### R-01 audit metadata: STOP — locked contract conflict reported

Codex asked to minimize the raw Idempotency-Key in
`payment.attempt_created` audit metadata if no approved contract
requires it. The approved spec EXPLICITLY requires it
(IMP-009-payment-hub.md §Audit: "payload: donation_ulid, provider,
amount_minor, currency, idempotency_key") and the locked
`PaymentAuditEventRegistrar` allow-list encodes
`'idempotency_key' => 'string'`. Changing to hash/omission would
conflict with the locked audit contract — therefore NOT changed.
Compensating fact: after the R-01 fix the key is provably NOT a
credential (replay grants no possession), so its audited presence
creates no authorization capability.

### CODEX-F-01 remediated: approve() is fail-closed on amount

- `ManualTransferVerificationService::approve()` now rejects with
  typed `amount_mismatch_requires_hold` unless the evidence's
  `declared_amount_minor` exactly equals the immutable Payment
  `amount_minor` (canonical integer minor units; a null declared
  amount also fails closed). No SUCCEEDED transition, no Donation
  consequence, no amount mutation on mismatch — the verifier uses
  the existing `holdForAmountMismatch()` controlled-review path.
  No refund/credit/balance/allocation, no Ledger posting invented.
- Tests: direct-approve bypass across −1/+1/−500/+500 canonical
  units (Payment stays PENDING, Donation stays PENDING, evidence
  unreviewed); direct approve with no declared amount fails
  closed; exact-match path still succeeds (pre-existing
  `test_submit_approve_full_path` plus the corrected
  `test_manual_approve_drives_payment_and_donation_to_succeeded_in_one_path`,
  whose fixture now declares the exact amount).

### Validation (this session, testing / sqlite / :memory:)

| Check | Result |
|-------|--------|
| `tests/Feature/Payment` | 128/128 PASS (713 assertions) |
| `tests/Unit` | 137/137 PASS (217 assertions) |
| `vendor/bin/pint --test` | PASS (474 files) |
| `composer audit` | no advisories |
| `git diff --check` | clean |

MySQL concurrency NOT rerun: the patch changes no database
uniqueness, locking, transaction, migration, or schema behavior
(same transactions, same constraints, same lock order) — the
prior gate's 9/9 disposable-DB result stands.

Development DB `kmsitdonation`: NOT TOUCHED. R-02/R-03/R-04
residuals: untouched per instruction (no churn).

FINAL VERDICT: REMEDIATION COMPLETE — READY FOR INDEPENDENT RE-REVIEW
