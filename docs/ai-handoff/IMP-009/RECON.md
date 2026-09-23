# IMP-009 — Payment Hub: Governed Implementation Recon

**Recon Agent:** qwen/qwen3.7-flash
**Recon Date:** 2026-09-20
**Baseline HEAD:** 73c786d732e328a1f07706bc1106a275a98675da (master, CLEAN)

---

## 1. Baseline Verification

| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Branch | master | master | PASS |
| HEAD | 73c786d... | 73c786d... | PASS |
| Working Tree | CLEAN | CLEAN | PASS |

## 2. Model Verification

**qwen/qwen3.7-flash — ASSERTED** (session model identity confirmed at runtime).

## 3. Authoritative Inputs Read

- `docs/implementation/IMP-009-payment-hub.md` — FULLY READ (3197 lines)
- `docs/adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md` — FULLY READ (199 lines)
- `app/Models/Donation/Donation.php` — STRUCTURE VERIFIED
- `app/Services/Donation/DonationTransitionService.php` — FULLY READ
- `app/Enums/AuditActorKind.php` — FULLY READ
- `app/Services/Audit/AuditEventRegistry.php` — FULLY READ
- `app/Support/Money/Money.php` — FULLY READ
- `app/Support/Money/CurrencyMinorUnits.php` — FULLY READ
- `app/Models/Rbac/Principal.php` — FULLY READ
- `app/Models/Rbac/SystemPrincipal.php` — FULLY READ
- `app/Services/Rbac/PermissionRegistry.php` — FULLY READ
- `config/money.php` — FULLY READ
- `config/donation.php` — FULLY READ
- `database/migrations/0001_08_01_000002_create_donations_table.php` — FULLY READ
- `database/seeders/DonationSystemPrincipalSeeder.php` — FULLY READ
- `routes/web.php` — EXISTING ROUTES MAPPED
- `bootstrap/app.php` — MIDDLEWARE ARCHITECTURE MAPPED
- `tests/TestCase.php`, `tests/**/*Test.php` — TEST PATTERNS MAPPED

---

## 4. Existing Reusable Surfaces

### 4.1 Donation Integration Surface

**CRITICAL:** IMP-009 MUST use ONLY this surface to transition Donations on Payment outcomes.

| Component | Location | Details |
|-----------|----------|---------|
| Donation model | `app/Models/Donation/Donation.php` | `ulid`, `status` enum/string, `campaign_id` FK, `donor_principal_id` FK, `amount_minor` (unsignedBigInteger), `currency` (char,3), `succeeded_at`, `failed_at`, `cancelled_at`, `expired_at`, `guest_name`, `guest_email`, `donor_display_name`, `is_anonymous`, `recurring_occurrence_id` |
| Donation status values | DB varchar(16) column, DEFAULT `'PENDING'` | `PENDING`, `SUCCEEDED`, `FAILED`, `CANCELLED`, `EXPIRED` |
| Transition service | `app/Services/Donation/DonationTransitionService.php` | The ONLY canonical writer of Donation lifecycle status |
| `markSucceeded()` | `DonationTransitionService::markSucceeded(Donation $donation, Principal $systemActor)` | Locks donation row (`lockForUpdate`), verifies PENDING status, transitions to SUCCEEDED + sets succeeded_at, emits audit event. **This is the method IMP-009 calls on Payment.success.** |
| `markFailed()` | `DonationTransitionService::markFailed(Donation $donation, Principal $systemActor)` | Same pattern -> FAILED + failed_at. **Called on Payment.failed.** |
| `cancel()` | `DonationTransitionService::cancel(Donation $donation, Principal $actor)` | For donor/admin cancellation (human actor). Not used by IMP-009 for payment outcomes. |
| Conflict exception | `App\Services\Donation\Exceptions\DonationTransitionConflictException` | Thrown when status != PENDING during transition. **Must be caught and handled per HD-IMP009-02.** |
| Donation policy | `app/Policies/DonationPolicy.php` | Governance layer; IMP-009's system-principal invocation bypasses Policy (scheduler commands do not consult Policies) |
| Donation idempotency key | `donations.idempotency_key` UNIQUE constraint | Separate namespace from Payment's own (HD-IMP008-05A scope); DON'T reuse for Payments |
| Donation expiration config | `config('donation.pending_expiry_minutes')`, default null | Payment's own expiry is INDEPENDENT clock; don't gate on this value |

**Exact transition contract for IMP-009:**

```php
// On terminal Payment outcome (SUCCEEDED or FAILED):
$transitionService = app(DonationTransitionService::class);
try {
    if ($payment->status === 'SUCCEEDED') {
        $transitionService->markSucceeded($donation, $principal);
    } elseif ($payment->status === 'FAILED') {
        $transitionService->markFailed($donation, $principal);
    }
} catch (DonationTransitionConflictException $e) {
    // HD-IMP009-02: Record donation_transition_rejected audit event (CRITICAL)
    // DO NOT retry, DO NOT force, DO NOT mutate Donation directly
    // Flag for controlled exception/manual review
}
```

**No column is added to `donations`.** Zero schema modification to existing tables.

### 4.2 Authorization Infrastructure (IMP-003)

| Component | Location | Details |
|-----------|----------|---------|
| Permission registry | `app/Services/Rbac/PermissionRegistry.php` | Canonical source of truth. All new permissions MUST be registered here as constants + in `definitions()` array |
| Permission seeder | `database/seeders/RbacPermissionSeeder.php` | Seeds all permissions from `PermissionRegistry::definitions()`, idempotent upsert-by-code |
| Principal model | `app/Models/Rbac/Principal.php` | Three kinds: `Human`, `System`, `Integration`. Relations: `systemPrincipal()`, `integrationPrincipal()`, `roleAssignments()`, `authorityAssignments()`, `canAuthorize()` |
| SystemPrincipal catalog | `app/Models/Rbac/SystemPrincipal.php` | Table with `code` + `description`. Seeded rows like `donation.scheduler`, `content.scheduler` |
| IntegrationPrincipal catalog | `app/Models/Rbac/IntegrationPrincipal.php` | Similar structure for webhook-attributed actions |
| Role model | Used by seeder, has `permissions()` relation, `is_system` flag | New roles seeded with `firstOrCreate(['code' => ...])` |
| PrincipalService | `app\Services\Rbac\PrincipalService.php` | `forSystem(SystemPrincipal)`, `forIntegration(IntegrationPrincipal)` — idempotent Principal creation from catalog |
| ScopeType enum | `app\Enums\ScopeType` | OWN, FUNDRAISER, PARTNER, CAMPAIGN, PROGRAM, FUND, BENEFICIARY_CASE, ASSIGNED_WORK, ORGANIZATION, GLOBAL_PLATFORM — CLOSED taxonomy, NO new values allowed |
| Authority types table | `database/migrations/0001_03_01_000002_create_authority_types_table.php` | `financial_approver`, `refund_approver`, `withdrawal_approver`, `distribution_approver`, `zakat_authority`, `partner_verifier`, `beneficiary_verifier` — CLOSED taxonomy |
| Business authority | Uses `financial_approver` for Manual Transfer verification (closest fit, no new type invented) | Via `AuthorityAssignment` model |

**NEW PERMISSIONS REQUIRED BY IMP-009** (to add to PermissionRegistry):

| Constant | Code | Description | Module |
|----------|------|-------------|--------|
| `PAYMENT_VIEW` | `payment.view` | Read/view Payment records | payment |
| `PAYMENT_CREATE` | `payment.create` | Create a Payment Attempt | payment |
| `PAYMENT_CANCEL` | `payment.cancel` | Cancel a PENDING/REQUIRES_ACTION Payment | payment |
| `PAYMENT_MANUAL_TRANSFER_SUBMIT_EVIDENCE` | `payment.manual_transfer.submit_evidence` | Submit proof-of-transfer evidence | payment |
| `PAYMENT_MANUAL_TRANSFER_VERIFY` | `payment.manual_transfer.verify` | Approve/reject Manual Bank Transfer evidence | payment |
| `PAYMENT_PROVIDER_CONFIG_MANAGE` | `payment.provider_config.manage` | Manage provider credentials/configuration | payment |

**New ScopeTypes:** NONE required. Existing scopes (OWN, ORGANIZATION, GLOBAL_PLATFORM) sufficient.

**New AuthorityTypes:** NONE required. Reusing `financial_approver` for manual transfer verification.

### 4.3 Audit Infrastructure (IMP-004)

| Component | Location | Details |
|-----------|----------|---------|
| AuditActorKind enum | `app/Enums/AuditActorKind.php` | `Human`, `System`, `Integration`, `Unauthenticated`, `PrePrincipalSystem` |
| AuditCriticality enum | `app/Enums/AuditCriticality.php` | `Critical`, `NonCritical` |
| AuditPersistenceStrategy enum | `app/Enums/AuditPersistenceStrategy.php` | `MutationAtomic` (CRITICAL), `DenialDurable` (non-CRITICAL denial evidence), `null` (NC default) |
| AuditVisibilityClass enum | `app/Enums/AuditVisibilityClass.php` | `General`, `Security` |
| AuditEventRegistry | `app/Services/Audit/AuditEventRegistry.php` | Single registry via `AuditEventDefinition` objects. Registered in constructor via `registerCanonicalEvents()`. Pattern: `new AuditEventDefinition(type, version, criticality, persistenceStrategy, visibilityClass, sourceDomain, hasFinancialReference, metadataAllowList, actorKinds, executionContext, isDenied, scopeType)` |
| AuditWriter | `app/Services/Audit/AuditWriter.php` | Sole audit sink, uses registry lookups, handles criticality-based rollback |
| AuditRecord model | `app/Models/Audit/AuditRecord.php` | Table-backed record with `event_type`, `actor_principal_kind`, `payload`, etc. |

**NEW AUDIT EVENTS REQUIRED BY IMP-009** (to register):

| Event Type | Version | Criticality | Persistence | Financial Ref | Actor Kinds | Exec Context |
|------------|---------|-------------|-------------|---------------|-------------|--------------|
| `payment.attempt_created` | 1 | NonCritical | null | true | Human, Unauthenticated | (depends on auth state) |
| `payment.succeeded` | 1 | Critical | MutationAtomic | true | Integration, System | null |
| `payment.failed` | 1 | Critical | MutationAtomic | true | Integration, System | null |
| `payment.expired` | 1 | NonCritical | null | true | System | null |
| `payment.cancelled` | 1 | NonCritical | null | true | Human | null |
| `payment.donation_transition_rejected` | 1 | Critical | MutationAtomic | true | System | null |
| `webhook.received` | 1 | NonCritical | null | false | Integration | null |
| `webhook.verification_failed` | 1 | NonCritical | DenialDurable | false | Integration | null |
| `manual_transfer.evidence_submitted` | 1 | NonCritical | null | true | Human, Unauthenticated | (see ADR-003) |
| `manual_transfer.approved` | 1 | Critical | MutationAtomic | true | Human | null |
| `manual_transfer.rejected` | 1 | Critical | MutationAtomic | true | Human | null |
| `manual_transfer.amount_mismatch_held` | 1 | Critical | MutationAtomic | true | Human | null |
| `provider_config.credential_changed` | 1 | Critical | MutationAtomic | false | Human | null |

**ADR-003 guest actor registration:**

| Event Type | Execution Context |
|------------|-------------------|
| `payment.attempt_created` | `http:payment:guest_attempt_created` |
| `manual_transfer.evidence_submitted` | `http:payment:guest_manual_transfer_evidence_submitted` |

**Source domain for provider events:** `payment_provider` (per IMP-004 spec reserve pattern).

### 4.4 Money / Currency

| Component | Location | Usage |
|-----------|----------|-------|
| Money VO | `app\Support\Money\Money.php` | Immutable value object, `ofMinorUnits(int $amountMinor, string $currency)`, `amountMinor()`, `currency()`, `equals()`, `format()` |
| CurrencyMinorUnits | `app\Support\Money\CurrencyMinorUnits.php` | `digitsFor(string $currency)`, `isRegistered(string $currency)` — reads from config |
| Config | `config/money.php` | `'minor_units' => ['IDR' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KWD' => 3]` |
| UnknownCurrencyException | `app\Support\Money\Exceptions\UnknownCurrencyException.php` | Throw on unregistered currency |

**Payment amounts ALWAYS copied from Donation's own `amount_minor`/`currency`.** Never independently entered. Use `Money::ofMinorUnits()` for validation/construction. Provider adapters handle unit conversion (e.g., Tripay whole-IDR API).

### 4.5 Database Conventions

| Convention | Pattern | Evidence |
|------------|---------|----------|
| Primary key | `$table->id()` = BIGINT UNSIGNED AUTO_INCREMENT | All migrations |
| Public identifier | `$table->char('ulid', 26)->unique()` | `donations`, `principals`, `campaigns` |
| Foreign keys | `$table->foreignId('X')->constrained('Y')->restrictOnDelete()` | All FK references use RESTRICT |
| Monetary columns | `$table->unsignedBigInteger('amount_minor')` + `$table->char('currency', 3)` | donations table |
| Status column | `$table->string('status', 16)->default('PENDING')` | varchar(16), string default |
| Timestamps | `$table->dateTime('created_at', 6)` + `$table->dateTime('updated_at', 6)` | precision 6 microseconds |
| Terminal datetime | `$table->dateTime('succeeded_at', 6)->nullable()` etc. | At most one non-null |
| JSON column | Stored as LONGTEXT equivalent in MySQL | Used sparingly, bounded shape |
| CHECK constraints | MySQL-only via `DB::statement('ALTER TABLE ... ADD CONSTRAINT ...')` | SQLite gets application-layer enforcement in model `saving` hook |
| Migration naming | `NNNN_NN_NN_NNNNNN_create_xxx_table.php` | Sequential, domain-prefixed |
| Indexes | Explicit `$table->index('col')` on FK columns | campaigns, donors, status, etc. |

### 4.6 Routing / Middleware Architecture

| Aspect | Current State |
|--------|---------------|
| Route file | Single `routes/web.php` — NO separate api.php |
| Bootstrap | Laravel 12 style via `bootstrap/app.php` — NO Kernel.php files |
| CSRF middleware | Laravel's default `PreventRequestForgery` applied to `web` group |
| Custom middleware | `EnsureIdentityIsActive`, `RequireElevatedAssurance` in `app/Http/Middleware/` |
| Request handling | `HandleInertiaRequests` appended to web group |
| Rate limiting | Two inline `throttle:6,1` usages only, no custom RateLimiter |
| Webhook routes | NONE currently exist — must be added |
| Admin routes convention | `/admin/{module}/{action}` prefix groups in web.php |

**Webhook integration points:**
- Need separate CSRF-exempt route group
- One route per provider: `/webhooks/payments/tripay`, `/webhooks/payments/xendit`, `/webhooks/payments/stripe`
- Must NOT weaken global CSRF security
- Signature verification in adapter before controller touches any business logic

### 4.7 Scheduler / Background Jobs

| Command | Signature | Schedule | File |
|---------|-----------|----------|------|
| Donation Expiry | `donation:expire-pending` | `everyMinute()->withoutOverlapping()` | `app/Console/Commands/ExpirePendingDonations.php` |
| CMS Transitions | `content:run-scheduled-transitions` | `everyMinute()->withoutOverlapping()` | `app/Console/Commands/RunScheduledContentTransitions.php` |
| Media Cleanup | `content:cleanup-media` | `hourly()->withoutOverlapping()` | `app/Console/Commands/CleanupMedia.php` |

**Registration:** `routes/console.php` uses `Schedule` facade (no Console/Kernel.php).

**Queue config:** Defaults to database driver, test uses `sync`.

### 4.8 Testing Conventions

| Aspect | Pattern |
|--------|---------|
| Base class | `tests/TestCase.php` (thin wrapper, empty body) |
| Traits-heavy testing | All infrastructure customization via traits |
| Key trait | `tests\Support\TruncatesInMemorySqlite.php` — custom DB trait for :memory: SQLite |
| RABC actors | `tests\Support\Rabbic\RbbicTestActors.php` — `makeAuthorizedActor()` / `makeUnauthorizedActor()` |
| Audit capture | `tests\Support\Rrbic\CapturesRfccAudit.php` — watermark-based audit log assertions |
| Campaign/Donation factories | `tests\Support\Donation\MakesDonationCampaigns.php` — `makeEligibleCampaign()`, `makeFund()` |
| MySQL concurrency | `tests\Feature\Donation\DonationConcurrencyTest.php` — disposable DB `kmsitdonation_imp008_test`, dual PDO connections, real row-lock overlap |
| HTTP testing | Direct `$this->post()` / `$this->get()` without Http facade, `withoutMiddleware(PreventRequestForgery::class)` in tests |
| Storage fake | `Storage::fake()` used extensively for media/upload tests |
| Notification fake | `Notification::fake()`, `Event::fake()` used |
| Test directory structure | `tests\Unit\{Module}\`, `tests\Feature\{Module}\{Module}{Subject}Test.php` |
| MySQL test DB | `.env.testing` overrides to dedicated test DB (separate from development) |

---

## 5. Payment Domain File Map (Proposed)

### 5.1 Models

| File | Purpose | Reference |
|------|---------|-----------|
| `app/Models/Payment/Payment.php` | Payment (Attempt) aggregate — ulid, donation_id, provider, amount_minor, currency, status, idempotency_key, active_slot (generated), expires_at, succeeded_at, failed_at, expired_at, cancelled_at, verified_by_principal_id, cancelled_by_principal_id, failure_reason, instructions_payload (JSON) | Spec "Domain Model" |
| `app/Models/Payment/PaymentProviderEvent.php` | Provider event forensics — payment_id nullable, provider, provider_event_id, event_type, signature_valid, processing_result, raw_payload_ciphertext, received_at | Spec "Domain Model" |
| `app/Models/Payment/ManualTransferEvidence.php` | Evidence upload — payment_id, submitted_by_principal_id, file_path, mime_type, size_bytes, declared_*, reviewed_*, review_outcome | Spec "Domain Model" |
| `app/Models/Payment/ManualTransferBankAccount.php` | Bank account routing config — ulid, bank_name, account_number, account_holder_name, currency, is_active | Spec "Domain Model" |
| `app/Models/Payment/PaymentProviderCredential.php` | Provider secret config — provider (UNIQUE), mode, encrypted_secret, is_enabled | Spec "Domain Model" |

### 5.2 Enums

| File | Purpose | Values |
|------|---------|--------|
| `app/Enums/PaymentStatus.php` | Canonical Payment state | `PENDING`, `REQUIRES_ACTION`, `SUCCEEDED`, `FAILED`, `EXPIRED`, `CANCELLED` |
| `app/Enums/PaymentProvider.php` | Closed provider allow-list | `MANUAL_TRANSFER`, `TRIPAY`, `XENDIT`, `STRIPE` |
| `app/Enums/WebhookProcessingResult.php` | Provider event processing outcomes | `ACCEPTED`, `REJECTED_INVALID_SIGNATURE`, `REJECTED_UNKNOWN_REFERENCE`, `REJECTED_AMOUNT_MISMATCH`, `REJECTED_CURRENCY_MISMATCH`, `REJECTED_MALFORMED`, `DUPLICATE` |
| `app/Enums/ManualTransferReviewOutcome.php` | Manual review decisions | `APPROVED`, `REJECTED`, `AMOUNT_MISMATCH_HOLD` |

### 5.3 Value Objects

| File | Purpose |
|------|---------|
| `app\Support\Money\PaymentAmount.php` (if needed beyond Money VO) | Reuse `Money::ofMinorUnits()` directly — no new Money abstraction (spec explicitly forbids this) |

**NOTE:** Do NOT create competing money primitives. Use `Money` VO directly.

### 5.4 Services

| File | Purpose |
|------|---------|
| `app\Services\Payment\PaymentCreationService.php` | Create Payment Attempt — validates Donation is PENDING, copies amount/currency, generates idempotency_key, acquires Donation-row lock, enforces one-ACTIVE-attempt invariant, calls provider adapter, emits audit event |
| `app\Services\Payment\PaymentTransitionService.php` | Handle Payment state transitions — manages status machine state changes, companion *_at fields, failure_reason |
| `app\Services\Payment\PaymentWebhookHandler.php` | Canonical webhook processing pipeline (steps 1-13 from spec) |
| `app\Services\Payment\PaymentExpirySweepService.php` | Laravel Scheduler command handler — finds overdue PENDING/REQUIRES_ACTION Payments, transitions to EXPIRED under lock |
| `app\Services\Payment\PaymentStatusPollService.php` | Optional status-poll fallback for providers where webhook delivery unreliable |
| `app\Services\ManualTransfer\ManualTransferEvidenceService.php` | Evidence upload/validation/storage, resubmission logic (append-only), eligibility checks |
| `app\Services\ManualTransfer\ManualTransferVerificationService.php` | Admin approve/reject/hold — checks financial_approver authority, requires evidence for APPROVE |
| `app\Services\Payment\PaymentExpirationConfigService.php` | Reads `config('payment.pending_expiry_minutes')`, applies fallback rules |
| `app\Services\Payment\ProviderAdapterFactory.php` | Factory returning correct adapter based on `payments.provider` |

### 5.5 Adapters (Provider Interface + Implementations)

| File | Purpose |
|------|---------|
| `app\Contracts\PaymentProviderAdapter.php` | Provider-neutral interface: `createTransaction()`, `retrieveStatus()`, `verifyCallback()`, `normalizeStatus()`, `normalizeExpiration()`, `providerReference()`, `paymentUrlOrInstructions()`, `paymentMethodMetadata()`, `normalizeError()`, `retryBehavior`, `providerIdempotency` |
| `app\Adapters\Payment\ManualTransferAdapter.php` | No-webhook adapter, returns bank account instructions, handles provider_reference generation |
| `app\Adapters\Payment\TripayAdapter.php` | Tripay transaction creation, HMAC signature verification, callback processing |
| `app\Adapters\Payment\XenditAdapter.php` | Xendit PaymentRequest API, x-callback-token verification, PaymentRequest status mapping |
| `app\Adapters\Payment\StripeAdapter.php` | Stripe PaymentIntent, Stripe-Signature header verification, Idempotency-Key header pass-through, Elements integration note |

### 5.6 Policies

| File | Purpose |
|------|---------|
| `app\Policies\PaymentPolicy.php` | RBAC authorization for Payment operations — donor ownership, admin organization scope, payment.create/view/cancel/manual_transfer.verify/provider_config.manage |

### 5.7 Scope Resolvers

| File | Purpose |
|------|---------|
| `app\Services\Payment\PaymentOwnScopeResolver.php` | OWN scope — matches donor_principal_id of owning Donation |
| `app\Services\Payment\PaymentOrganizationScopeResolver.php` | ORGANIZATION scope — all Payments within platform |
| `app\Services\Payment\PaymentGlobalPlatformScopeResolver.php` | GLOBAL_PLATFORM scope — provider configuration management |

### 5.8 Controllers

| File | Purpose |
|------|---------|
| `app\Http\Controllers\PaymentController.php` | Public payment initiation: `POST /donations/{ulid}/payments` |
| `app\Http\Controllers\MePaymentController.php` | Donor-owned views: `GET /me/donations/{ulid}/payments`, `GET /me/donations/{ulid}/payments/{payment_ulid}`, `POST /me/donations/{ulid}/payments/{payment_ulid}/cancel` |
| `app\Http\Controllers\ManualTransferEvidenceController.php` | Evidence submission: `POST /me/donations/{ulid}/payments/{payment_ulid}/manual-transfer/evidence` |
| `app\Http\Controllers\Webhooks\TripayWebhookController.php` | Tripay callback receipt, delegates to adapter verifyCallback |
| `app\Http\Controllers\Webhooks\XenditWebhookController.php` | Xendit webhook receipt |
| `app\Http\Controllers\Webhooks\StripeWebhookController.php` | Stripe webhook receipt |
| `app\Http\Controllers\Admin\PaymentController.php` | Admin list/detail views, manual transfer approve/reject |
| `app\Http\Controllers\Admin\PaymentProviderConfigController.php` | Provider credential management, bank account CRUD |

### 5.9 Requests

| File | Purpose |
|------|---------|
| `app\Http\Requests\Payment\StorePaymentRequest.php` | Validation for Payment creation (donation_id, provider, idempotency_key header) |
| `app\Http\Requests\ManualTransfer\StoreEvidenceRequest.php` | Validation for evidence upload (file MIME, size limits) |

### 5.10 Commands

| File | Purpose |
|------|---------|
| `app\Console\Commands\ExpirePendingPayments.php` | Laravel Scheduler command for Payment expiry sweep, runs under `scheduler.payment-expiry-sweep` System Principal |
| `app\Console\Commands\PollPaymentStatus.php` | Optional status-poll fallback job, runs under `scheduler.payment-status-poll` |

### 5.11 Configuration

| File | Purpose |
|------|---------|
| `config/payment.php` | New config file: `pending_expiry_minutes` (default null), provider enable/disable flags, manual transfer settings |
| Seeder | New seeder for `payment_provider_credentials` initial row(s), `manual_transfer_bank_accounts` sample data |

### 5.12 Migrations (Ordered)

| # | Migration Name | Creates | Dependencies |
|---|----------------|---------|--------------|
| 1 | `0001_09_01_000001_create_manual_transfer_bank_accounts_table.php` | `manual_transfer_bank_accounts` | None |
| 2 | `0001_09_01_000002_create_payment_provider_credentials_table.php` | `payment_provider_credentials` | None |
| 3 | `0001_09_01_000003_create_payments_table.php` | `payments` (FK to donations.id RESTRICT, FK to principals.id RESTRICT) | Needs IMP-008 donations table |
| 4 | `0001_09_01_000004_create_payment_provider_events_table.php` | `payment_provider_events` (FK to payments.id RESTRICT) | Needs payments table |
| 5 | `0001_09_01_000005_create_manual_transfer_evidence_table.php` | `manual_transfer_evidence` (FK to payments.id RESTRICT, FK to principals.id RESTRICT) | Needs payments table |

### 5.13 Seeders / Registries

| File | Purpose |
|------|---------|
| `Database\Seeders\PaymentSystemPrincipalsSeeder.php` | Seeds integration_principals (payment.tripay-webhook, payment.xendit-webhook, payment.stripe-webhook) + system_principals (scheduler.payment-expiry-sweep, scheduler.payment-status-poll, system.payment-outcome-consequence) + associated Roles, Principal assignments |
| `Database\Seeders\PaymentPermissionsSeeder.php` | Seeds the six new payment.* permissions from PaymentRegistry into permissions table |
| `Database\Seeders\PaymentAuditEventRegistrar.php` | Registers all 13 payment.* / manual_transfer.* / webhook.* events into AuditEventRegistry |
| `Database\Seeders\PaymentProviderCredentialsSeeder.php` | Optionally seeds initial provider credential rows (encrypted secrets) |
| `Database\Seeders\PaymentBankAccountsSeeder.php` | Seeds sample bank accounts for Manual Transfer |

### 5.14 Tests

| Test File | Coverage Category | DB Required |
|-----------|-------------------|-------------|
| `tests\Unit\Payment\MoneyTest.php` | Unit: MoneyVO usage paths | SQLite-safe |
| `tests\Unit\Payment\PaymentStatusStateMachineTest.php` | Unit: state transition matrix | SQLite-safe |
| `tests\Unit\Payment\ProviderAdapterNormalizeStatusTest.php` | Unit: each adapter's normalizeStatus() mapping table completeness | SQLite-safe |
| `tests\Feature\Payment\PaymentCreationTest.php` | Feature: guest/authenticated Payment creation against PENDING Donation, rejection against non-PENDING | SQLite-safe |
| `tests\Feature\Payment\PaymentIdempotencyTest.php` | Feature: idempotency key semantics (matching payload -> return existing, different payload -> 409) | SQLite-safe |
| `tests\Feature\Payment\PaymentAuthorizationTest.php` | Feature: every RBAC row in spec (allow/deny across all permission/scope combinations) | SQLite-safe |
| `tests\Feature\Payment\PaymentWebhookSecurityTest.php` | Feature: signature verification valid/invalid, malformed payload, unknown reference, amount mismatch, duplicate event | SQLite-safe + HTTP fake |
| `tests\Feature\Payment\PaymentProviderNormalizationTest.php` | Feature: each provider's status normalization + callback processing | SQLite-safe + provider fakes |
| `tests\Feature\Payment\PaymentExpirationTest.php` | Feature: expiry sweep behavior, configurable window, provider-supplied vs fallback | SQLite-safe |
| `tests\Feature\Payment\PaymentDonationIntegrationTest.php` | Feature: Payment SUCCEEDED -> Donation SUCCEEDED (same TX), Payment FAILED -> Donation FAILED, late success/failure (HD-IMP009-02) | SQLite-safe |
| `tests\Feature\Payment\ManualTransferEvidenceTest.php` | Feature: evidence submission, resubmission (append-only), terminal/ineligible rejection | SQLite-safe + Storage fake |
| `tests\Feature\Payment\ManualTransferVerificationTest.php` | Feature: approve/reject/amount_mismatch_hold, financial_approver check, evidence-mandatory for APPROVE | SQLite-safe |
| `tests\Feature\Payment\PaymentSchemaConstraintsTest.php` | Migration: DB-level FK RESTRICT, UNIQUE(idempotency_key), UNIQUE(donation_id, active_slot), composite unique (provider, provider_event_id) | **MySQL-required** |
| `tests\Feature\Payment\PaymentConcurrencyTest.php` | Concurrency: concurrent webhook dedup, concurrent creation one-active-attempt, webhook vs expiry race | **MySQL-required** |
| `tests\Feature\Payment\PaymentAuditTest.php` | Audit: event criticality classification, fail-closed CRITICAL rollback, guest actor Unauthenticated | SQLite-safe |
| `tests\Feature\Payment\PaymentFileSecurityTest.php` | Security: evidence on private disk, no public URL, authorization-gated retrieval | SQLite-safe + Storage fake |
| `tests\Feature\Payment\PaymentInventoryTest.php` | Inventory: permission count assertion, audit event count assertion, system principal count assertion | SQLite-safe |
| `tests\Feature\Payment\SystemPrincipalAttributionTest.php` | Attribution: webhook -> Integration Principal, sweep -> System Principal, consequence -> distinct System Principal | SQLite-safe |
| `tests\Feature\Payment\PaymentMigrationReversibilityTest.php` | Migration: UP, DOWN, UP cycle on MySQL | **MySQL-required** |

**MySQL-required summary:** SchemaConstraints, Concurrency, MigrationReversibility — same convention as `DonationConcurrencyTest.php` using disposable MySQL DB.

### 5.15 Frontend Components (only if spec-requiring)

None explicitly required by spec for implementation phase. Routes return JSON/views as Laravel standard. Future IMP-025 REST API will handle frontend concerns.

---

## 6. Files That MUST NOT Be Modified

These files belong to other IMPs. IMP-009 reads them, NEVER modifies them:

| File / Directory | Reason |
|------------------|--------|
| `app/Models/Donation/Donation.php` | IMP-008 owns Donation — no column changes, no behavior changes |
| `app/Services/Donation/DonationTransitionService.php` | IMP-008 — IMP-009 CALLS it, does NOT modify its code |
| `app/Services/Donation/DonationAuditLogger.php` | IMP-008 — read-only consumption |
| `app/Policies/DonationPolicy.php` | IMP-008 — read-only |
| `app/Models/Campaign/Campaign.php` and related | IMP-007 — read-only transitive via Donation.campaign_id |
| `app/Support/Money/Money.php` | IMP-007 — consume, don't redefine |
| `app/Support/Money/CurrencyMinorUnits.php` | IMP-007 — consume |
| `app/Enums/AuditActorKind.php` | IMP-004 — NO new enum case added (BR-17) |
| `app/Enums/ScopeType.php` | IMP-003 — NO new scope value (BR-17) |
| `app/Services/Audit/AuditEventRegistry.php` | IMP-004 — NEW events are ADDED via a registration mechanism, but the enum/catalog itself isn't restructured |
| `app/Services/Rbac/PermissionRegistry.php` | IMP-003 — new permissions ARE added here (that IS IMP-009's responsibility) |
| `app/Models/Rbac/Principal.php` | IMP-003 — read-only for new System/Integration Principals |
| `app/Models/Rbac/SystemPrincipal.php` | IMP-003 — seeding new rows (INSERT), not modifying model code |
| `app/Models/Rbac/IntegrationPrincipal.php` | IMP-003 — seeding new rows |
| `config/donation.php` | IMP-008 — never modified |
| `config/money.php` | IMP-007 — never modified |
| Any migration prior to `0001_09_01_*` | Other IMPs — never modified |
| `tests\Feature\Donation\*` | IMP-008 tests — never modified |
| `tests\Feature\Campaign\*` | IMP-007 tests — never modified |
| `tests\Feature\Rbbic\*` | IMP-003 tests — inventory tests may need UPDATE for new counts |

---

## 7. Implementation Sequence (Dependency Order)

```
Phase 1 — Foundation (enums + value objects)
  ├── app/Enums/PaymentStatus.php
  ├── app/Enums/PaymentProvider.php
  ├── app/Enums/WebhookProcessingResult.php
  └── app/Enums/ManualTransferReviewOutcome.php

Phase 2 — Database Schema (migration order enforced by FK dependencies)
  ├── 0001_09_01_000001_create_manual_transfer_bank_accounts_table.php
  ├── 0001_09_01_000002_create_payment_provider_credentials_table.php
  ├── 0001_09_01_000003_create_payments_table.php
  ├── 0001_09_01_000004_create_payment_provider_events_table.php
  └── 0001_09_01_000005_create_manual_transfer_evidence_table.php

Phase 3 — Models
  ├── app/Models/Payment/Payment.php
  ├── app/Models/Payment/PaymentProviderEvent.php
  ├── app/Models/Payment/ManualTransferEvidence.php
  ├── app/Models/Payment/ManualTransferBankAccount.php
  └── app/Models/Payment/PaymentProviderCredential.php

Phase 4 — Core Domain Services
  ├── app/Contracts/PaymentProviderAdapter.php (interface)
  ├── app/Services/Payment/PaymentCreationService.php
  ├── app/Services/Payment/PaymentTransitionService.php
  ├── app/Services/Payment/PaymentWebhookHandler.php
  ├── app/Services/Payment/PaymentExpirySweepService.php
  ├── app/Services/ManualTransfer/ManualTransferEvidenceService.php
  └── app/Services/ManualTransfer/ManualTransferVerificationService.php

Phase 5 — Registries & Configuration
  ├── config/payment.php (new file)
  ├── Permission additions to Payment module concept (or standalone PaymentPermissionRegistry)
  ├── Database\Seeders\PaymentPermissionsSeeder.php
  └── Database\Seeders\PaymentAuditEventRegistrar.php

Phase 6 — Provider Adapters
  ├── app/Adapters/Payment/ManualTransferAdapter.php
  ├── app/Adapters/Payment/TripayAdapter.php
  ├── app/Adapters/Payment/XenditAdapter.php
  └── app/Adapters/Payment/StripeAdapter.php

Phase 7 — Authorization
  ├── app/Policies/PaymentPolicy.php
  ├── app/Services/Payment/PaymentOwnScopeResolver.php
  ├── app/Services/Payment/PaymentOrganizationScopeResolver.php
  └── app/Services/Payment/PaymentGlobalPlatformScopeResolver.php

Phase 8 — System Principal Registration
  └── Database\Seeders\PaymentSystemPrincipalsSeeder.php

Phase 9 — Webhooks / Controllers (inbound)
  ├── app/Http/Controllers/Webhooks/TripayWebhookController.php
  ├── app/Http/Controllers/Webhooks/XenditWebhookController.php
  └── app/Http/Controllers/Webhooks/StripeWebhookController.php

Phase 10 — Application Controllers
  ├── app/Http/Controllers/PaymentController.php
  ├── app/Http/Controllers/MePaymentController.php
  ├── app/Http/Controllers/ManualTransferEvidenceController.php
  ├── app/Http/Controllers/Admin/PaymentController.php
  └── app/Http/Controllers/Admin/PaymentProviderConfigController.php

Phase 11 — Requests
  ├── app/Http/Requests/Payment/StorePaymentRequest.php
  └── app/Http/Requests/ManualTransfer/StoreEvidenceRequest.php

Phase 12 — Routes
  └── Additions to routes/web.php:
      ├── POST /donations/{ulid}/payments (public)
      ├── GET /me/donations/{ulid}/payments (auth)
      ├── GET /me/donations/{ulid}/payments/{payment_ulid} (auth)
      ├── POST /me/donations/{ulid}/payments/{payment_ulid}/cancel (auth)
      ├── POST /me/donations/{ulid}/payments/{payment_ulid}/manual-transfer/evidence (guest/auth)
      ├── POST /webhooks/payments/tripay (CSRF-exempt)
      ├── POST /webhooks/payments/xendit (CSRF-exempt)
      ├── POST /webhooks/payments/stripe (CSRF-exempt)
      ├── GET /admin/payment/payments (admin)
      ├── GET /admin/payment/payments/{ulid} (admin)
      ├── POST /admin/payment/payments/{ulid}/manual-transfer/approve (admin)
      ├── POST /admin/payment/payments/{ulid}/manual-transfer/reject (admin)
      ├── GET /admin/payment/provider-config (admin)
      └── POST /admin/payment/provider-config/{provider} (admin)

Phase 13 — Scheduler Commands
  ├── app/Console/Commands/ExpirePendingPayments.php
  ├── app/Console/Commands/PollPaymentStatus.php (optional)
  └── Register in routes/console.php

Phase 14 — Banking Account Seeder (operational setup)
  └── Database\Seeders\PaymentBankAccountsSeeder.php

Phase 15 — Tests (parallel with implementation)
  ├── Unit tests: Money, StateMachine, Adapter normalizations
  ├── Feature tests: Creation, Transition, Authorization, Webhooks, Manual Transfer
  ├── MySQL-required tests: Schema constraints, Concurrency, Migration reversibility
  └── Audit attribution tests

Phase 16 — Static analysis / lint
  └── phpstan, pint, etc. per project convention
```

---

## 8. Risk Register

| ID | Risk | Affected Surfaces | Mitigation | Test |
|----|------|-------------------|------------|------|
| R1 | Payment/Donation state race | PaymentTransitionService + DonationTransitionService | Both acquire locks in Donation-then-Payment order (HD-IMP009-01 lock discipline) | Concurrency test |
| R2 | Duplicate provider callback | PaymentWebhookHandler, payment_provider_events | Composite-unique (provider, provider_event_id) + DUPLICATE processing path | Duplicate event test |
| R3 | Webhook spoofing/replay | Webhook controllers, adapter verifyCallback() | Constant-time HMAC comparison (Tripay), x-callback-token (Xendit), Stripe-Signature + timestamp tolerance (Stripe) | Signature verification test |
| R4 | Idempotency collision | PaymentCreationService | Unique(idempotency_key) DB constraint + matching-payload replay, different-payload conflict | Idempotency test |
| R5 | Multiple ACTIVE attempts | payments.active_slot generated column | `UNIQUE(donation_id, active_slot)` + Donation-row lock pre-check | One-active-invariant DB test |
| R6 | Late success after Donation terminal | DonationTransitionService call site | Catch DonationTransitionConflictException, emit CRITICAL donation_transition_rejected audit event | HD-IMP009-02 integration test |
| R7 | Amount/currency mismatch at webhook | PaymentWebhookHandler step 6 | Validate amount/currency BEFORE state transition -> REJECTED_AMOUNT_MISMATCH/CURRENCY_MISMATCH | Amount mismatch test |
| R8 | Provider-specific state leakage | Provider adapters | All provider statuses normalized through abstract interface; no raw provider strings in domain code | Adapter isolation test |
| R9 | Secret exposure | Provider credentials, webhook responses | encrypted_secret column, Laravel encrypter, masked at serialization, never logged, never returned | File security test |
| R10 | Guest authorization weakness | PaymentCreationService (guest path) | Guest creation gated by Donation.PENDING status + one-ACTIVE check + idempotency key (same as authenticated, just no ownership check) | Guest creation test |
| R11 | Manual evidence exposure | ManualTransferEvidenceService, file routes | Private disk storage, randomized filename, signed/short-lived token access, authorization gate | File security test |
| R12 | Audit fail-open | AuditWriter, CRITICAL events | MUTATION_ATOMIC strategy: audit append failure rolls back accompanying mutation | Fail-closed audit test |
| R13 | Development DB accidental use | Migration runner | Follow Disposable DB convention from DonationConcurrencyTest — dedicated test DB name | MySQL test isolation |
| R14 | Payment/Ledger boundary violation | PaymentTransitionService | IMP-009 does NOT import Ledger classes, does NOT know Ledger exists, emits domain events only | Architectural boundary test |
| R15 | Recurring auto-charge scope creep | PaymentCreationService, Stripe adapter | Manual-per-cycle explicit: no stored credentials, no off-session charges, no automatic charging logic | Recurring boundary review |

---

## 9. Open Contradictions

**NONE FOUND.** Repository reality matches specification design at all checked surfaces:

- DonationTransitionService structure confirms the exact System-Principal-gated transition contract the spec describes
- PermissionRegistry pattern matches the spec's new-permission-registration convention
- AuditEventRegistry pattern matches the spec's event-registration approach
- Database conventions match spec requirements (BIGINT PK, ULID unique, DECIMAL-equivalent unsignedBigInteger for minor units, RESTRICT FKs, MySQL-only CHECK via DB::statement)
- No unexpected conflicting codepaths found

---

## 10. Human Decision Status

**OPEN HUMAN DECISIONS: 0** (all twelve HD-IMP009-01 through HD-IMP009-12 are FINAL/LOCKED, ADR-003 ACCEPTED)

**NO NEW HUMAN DECISION REQUIRED.**

---

## 11. System Principal Plan

**New Integration Principals (3):**
| Code | Description | Role |
|------|-------------|------|
| `payment.tripay-webhook` | Tripay callback attribution | webhook.receive + payment view |
| `payment.xendit-webhook` | Xendit webhook attribution | webhook.receive + payment view |
| `payment.stripe-webhook` | Stripe webhook attribution | webhook.receive + payment view |

**New System Principals (3):**
| Code | Description | Role |
|------|-------------|------|
| `scheduler.payment-expiry-sweep` | Payment expiry sweep | Payment transition (PENDING/REQUIRES_ACTION -> EXPIRED) only |
| `scheduler.payment-status-poll` | Provider status poll fallback | Payment transition (terminal outcomes via poll) only |
| `system.payment-outcome-consequence` | Donation transition invocation | markSucceeded/markFailed on Payment terminal outcomes |

**Distinct separation:** `*webhook` (Integration Principal) is never conflated with `system.payment-outcome-consequence` (System Principal) — documented in SECURITY-INVARIANTS.md.

**Seeding mechanism:** New `PaymentSystemPrincipalsSeeder.php` following `DonationSystemPrincipalSeeder.php` pattern.

**Tests:** Verify PrincipalKind == Integration for webhook codes, SystemPrincipal for scheduler/consequence codes.

---

## 12. One Active Attempt DB Strategy

**Implementation:** Generated column `active_slot` paired with composite unique constraint.

```sql
-- In payments migration (MySQL-only DDL):
active_slot TINYINT GENERATED ALWAYS AS (
  CASE WHEN status IN ('PENDING', 'REQUIRES_ACTION') THEN 1 ELSE NULL END
) STORED,
UNIQUE INDEX ux_payments_donation_active_slot (donation_id, active_slot)
```

**Mechanism:** MySQL's NULL-distinct unique index semantics — multiple terminal rows (active_slot=NULL) coexist per donation_id, but at most one row with active_slot=1 per donation_id.

**Application-layer guard:** PaymentCreationService performs `SELECT ... FOR UPDATE` on the Donation row AND checks for existing ACTIVE attempts BEFORE inserting (complements the DB backstop against race conditions that bare unique constraints can't prevent alone).

**SQLite compatibility:** Same technique works because SQLite supports generated columns and unique indexes on expressions (SQLite 3.31+).

---

## 13. Payment Idempotency

**SEPARATE FROM DONATION IDEMPOTENCY.** Confirmed by:
- `payments.idempotency_key` UNIQUE VARCHAR(128) — independent namespace
- `donations.idempotency_key` UNIQUE VARCHAR(128) — Donation-creation scoped (HD-IMP008-05A)
- Spec HD-IMP009-11 rationale: "deliberately a SEPARATE key namespace... explicitly does not anticipate a Payment-level key"

**Patterns inherited:**
- Same transport: `Idempotency-Key` HTTP header (REQUIRED, 1-128 chars printable ASCII)
- Same DB enforcement: UNIQUE constraint as deterministic backstop
- Same replay semantics: same key + matching payload -> return existing; same key + different payload -> 409 conflict

---

## 14. Provider Adapters

| Adapter | HTTP Client | Config | Signing | Fake Pattern |
|---------|-------------|--------|---------|-------------|
| Manual Transfer | N/A (internal-only) | `manual_transfer_bank_accounts` table | N/A | No network calls |
| Tripay | Guzzle HTTP Client (Laravel `Http` facade) | `payment_provider_credentials` encrypted_secret = merchant_private_key | HMAC over callback body, constant-time compare (`hash_equals`) | `Http::fake()` for outbound; mock adapter for inbound |
| Xendit | Guzzle HTTP Client | `payment_provider_credentials` encrypted_secret = api_key + callback_token | `x-callback-token` header vs configured token, constant-time compare | `Http::fake()` |
| Stripe | Guzzle HTTP Client | `payment_provider_credentials` encrypted_secret = secret_key + webhook_signing_secret | `Stripe-Signature` header (HMAC over body+timestamp), timestamp tolerance | `Http::fake()` |

**Common patterns:**
- Laravel `Illuminate\Support\Facades\Http` for HTTP requests
- `config('payment.encryption_key')` backed by Laravel APP_KEY encryption
- Error normalization: `normalizeError(mixed)` -> typed exception, never raw provider dump in response
- Logging redaction: provider secrets, raw card data, signatures never logged

---

## 15. Webhook Security

**Integration point:** New route group in `routes/web.php`:

```php
Route::prefix('webhooks/payments')->name('webhooks.payments.')->group(function () {
    Route::post('/tripay', [TripayWebhookController::class, 'handle'])->name('tripay');
    Route::post('/xendit', [XenditWebhookController::class, 'handle'])->name('xendit');
    Route::post('/stripe', [StripeWebhookController::class, 'handle'])->name('stripe');
});
```

**CSRF exemption:** Must be implemented via middleware group or route attribute WITHOUT weakening global security. Options:
- Custom `SkipCorsCsrf` or `WebhookCsrf` middleware applied only to these three routes
- Or use Laravel's `$except` array in `app/Http/Middleware/VerifyCsrfToken.php` restricted to exactly these URI patterns

**Signature verification placement:** Inside the controller, delegating to `adapter->verifyCallback($request)`, which returns `VerifiedCallbackResult` (pass/fail). Pipeline stops at verification failure — no Payment state change.

**Raw request body:** Controller reads `$request->getContent()` (raw bytes) for signature verification before parsing JSON.

---

## 16. Concurrency Testing Plan

Following `DonationConcurrencyTest.php` pattern:

**Disposable MySQL DB:** Named `kmsitdonation_imp009_test` (consistent naming convention).

**Two-connection approach:** Primary connection + secondary "contender" PDO connection to same DB.

**Test scenarios:**
1. Two identical webhook deliveries -> one state transition + one DUPLICATE record
2. Concurrent webhook vs status-poll -> one winner, loser observes state change
3. Concurrent webhook vs expiry sweep -> one winner, loser sees already-terminal
4. Concurrent Payment creation against same Donation -> one succeeds, loser blocked by `UNIQUE(donation_id, active_slot)`
5. Callback vs late success race -> Payment fact recorded, Donation transition rejected (HD-IMP009-02)

**Lock ordering enforced:** Donation row locked FIRST, then Payment row. Consistent across webhook handler, creation service, and expiry sweep.

---

## 17. Migration Reversibility

**UP/DOWN guarantee:** Each migration's `down()` drops tables in REVERSE dependency order if run individually. However, since Laravel runs migrations sequentially, proper `down()` implementations:

| Migration | DOWN safety | Notes |
|-----------|-------------|-------|
| manual_transfer_evidence | Drop table | Depends on payments.id |
| payment_provider_events | Drop table | Depends on payments.id |
| payments | Drop table | FK to donations.id (RESTRICT) |
| payment_provider_credentials | Drop table | Independent |
| manual_transfer_bank_accounts | Drop table | Independent |

**Recommended DOWN strategy:** Run individual `down()` calls in reverse order: evidence -> events -> payments -> credentials -> bank_accounts.

**Potential hazards:**
- `active_slot` generated column + unique index DROP order: must drop table (not individual index)
- MySQL foreign key RESTRICT prevents dropping referenced table first — individual migration down must respect FK order
- Best practice: `Schema::disableForeignKeyConstraints()` at start, restore at end of `down()`

**MySQL-down hazard mitigation:** Disable FK constraints during `down()` to avoid ordering issues.

---

## 18. Test Inventory Impacts

**Tests that assert inventory counts and WILL require update:**

| Test File | What It Counts | Impact |
|-----------|----------------|--------|
| `tests/Feature/Rbac/PrincipalLifecycleTest.php` | Permissions, roles, authorities | +6 payment permissions, +1 role (per system principal), potentially more |
| Audit foundation test | Known event types | +13 payment/manaul_transfer/webhook events |
| System principal tests | system_principals/integration_principals row count | +3 integration, +3 system principals |
| Any seeder completion test | Total seeded rows | Adjust expected counts |

**Explicitly:** The `PaymentPermissionsSeeder` will add 6 new permissions. Any test asserting `Permission::count()` or iterating known permissions will fail unless updated.

---

## 19. Recurring Boundary

**PRESERVED.** Repository has NO recurring charge/auto-charge code currently. The `DonationRecurringPlan` and `DonationRecurringOccurrence` models exist but their generation engine is deferred (IMP-008 Out of Scope). IMP-009 adds no:
- Stored/reusable payment credentials
- Mandates
- Off-session authorizations
- Automatic charging schedulers
- Provider subscription endpoints

**Confirmed by:** No credit-card-on-file infrastructure, no tokens table, no `PaymentMethod` entity anywhere in the codebase.

---

## 20. Financial Boundary Verification

**PRESERVED.** Payment != Donation (state machines independent). Payment != Ledger (no Ledger code exists yet; IMP-009 emits domain events only).

Files/Modes that MUST NOT be touched (confirmed above):
- Ledger-related: NONE EXIST YET
- Financial Consequence Posting: DOES NOT EXIST
- Operational Fee: DOES NOT EXIST  
- Commission: DOES NOT EXIST
- Withdrawal: DOES NOT EXIST
- Refund: DOES NOT EXIST
- Reconciliation/Moota: DOES NOT EXIST

IMP-009 boundaries are structurally clear: no future-module code exists yet, so accidental boundary violation is simply a matter of coding discipline.

---

## 21. Implementation Summary

**Files to CREATE:** ~45-55 files total (models, enums, services, adapters, policies, scope resolvers, controllers, requests, commands, config, migrations, seeders, tests)

**Files to MODIFY:** ~8 files total (PermissionRegistry, AuditEventRegistry, routes/web.php, routes/console.php, bootstrap/app.php for middleware, .env.example for new config vars, composer.json if any new package needed)

**Files to NOT MODIFY:** All IMP-003/004/007/008 files, all prior migrations, all prior test files (except inventory-affecting tests)

**MySQL-required tests:** 3 test classes minimum (schema constraints, concurrency, migration reversibility)

**Risks identified:** 15 risks, all mitigable with existing repository patterns

**VERDICT:** RECON COMPLETE — READY FOR HUMAN IMPLEMENTATION AUTHORIZATION
