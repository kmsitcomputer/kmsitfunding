# IMP-008 Donation — Recon Handoff

## 1. Recon Metadata

| Field | Value |
|---|---|
| **Branch** | master |
| **HEAD Baseline** | 4c41149c7d88846051b4a6055975c3467e10970f |
| **Model** | qwen/qwen3.7-flash |
| **Role** | V3 Recon / Cheap Worker |
| **Approved Specification** | docs/implementation/IMP-008-donation.md |
| **ADR Reference** | docs/adr/ADR-002-donation-unauthenticated-actor-scope-amendment.md |
| **Human Spec Approval** | NOT YET APPROVED (specification phase only) |
| **Open Human Decisions** | 0 |

## 2. Authority / Scope

The approved IMP-008 specification is authoritative. This RECON is an implementation-navigation artifact only. It introduces no new business decisions, requires no locked-contract modifications, and generates no architectural escalation. If any RECON wording conflicts with the approved specification, the specification wins.

## 3. Implementation File Map

### Money

**app/Support/Money/Money.php** — REUSE — Immutable value object: `ofMinorUnits(int $amountMinor, string $currency)` factory; `amountMinor()`, `currency()`, `equals()`, `format()` display-only via integer/string arithmetic. Contract: construct exclusively through `Money::ofMinorUnits()`. Never a float. No arithmetic operations implemented.

**app/Support/Money/CurrencyMinorUnits.php** — REUSE — Centralized per-currency minor-unit digit count registry sourced from `config/money.php`. `digitsFor($currency)` throws `UnknownCurrencyException` if unregistered. `isRegistered($currency)` boolean check. Contract: every donation.amount_minor/currency pair validated through this service at creation time.

**app/Support/Money/Exceptions/UnknownCurrencyException.php** — REUSE — Typed RuntimeException constructor receives `$currency` string. Contract: thrown by `CurrencyMinorUnits::digitsFor()` for unknown ISO codes; used as typed validation exception in Error Handling.

**config/money.php** — REFERENCE — Registered currencies: IDR(2), USD(2), EUR(2), GBP(2), JPY(0), KWD(3). Contract: additive extension only; no existing entries modified.

### Campaign Eligibility

**app/Services/Campaign/CampaignEligibilityResolver.php** — REUSE — Canonical one-time eligibility check: `isDonationEligible(Campaign $campaign, ?DateTimeInterface $at = null): bool`. Contract: PURE READ — never mutates Campaign.status; status must be PUBLISHED; starts_at/ends_at boundaries inclusive; null bounds mean unbounded. Accepts ANY DateTimeInterface. IMP-008 MUST call this at Donation creation and MUST NOT re-derive status/date logic independently.

**app/Models/Campaign/Campaign.php** — REFERENCE — Eloquent model using GeneratesUlid trait; status enum DRAFT|REVIEW|APPROVED|PUBLISHED|CLOSED; BelongsTo Program, Fund, Principal (by/created/updated/etc.). Route key is `ulid`. `target_amount_minor` unsigned big int. Contract: donations.campaign_id references this table; RESTRICT-on-delete prevents deletion while referenced.

**tests/Unit/Campaign/CampaignEligibilityResolverTest.php** — REFERENCE TEMPLATE — Full permutation of status/date-boundary tests; DateTimeInterface acceptance; boundary-inclusive checks; mutation-protection. Uses RbacTestActors + RefreshDatabase.

### Identity / Principal

**app/Models/User.php** — REFERENCE — fillable = ['email','password'] ONLY. No display_name column exists. This validates IMP-008's donor_display_name captured at Donation level. public_id is ULID; route key is 'public_id'.

**app/Models/Rbac/Principal.php** — REUSE — Canonical authorization identity; principal_kind enum (Human/System/Integration); CHECK invariant via isConsistent(); canAuthorize() evaluates tombstoned/disabled/active state. Contract: donor_principal_id FK->principals.id; NULL = guest; NOT NULL = authenticated; RESTRICT-on-delete. System Principal used for automated consequence transitions (PENDING->SUCCEEDED/FAILED/EXPIRED).

**app/Services/Rbac/PrincipalService.php** — REFERENCE — Resolves User -> Principal via `forUser(User)`. Donor identity resolution maps authenticated donor to their Principal.

### Authorization

**app/Policies/Concerns/AuthorizesUsingRbac.php** — REUSE — Shared AND-chain sequencing trait providing `authorizeRbac()`: principal, permissionCode, resource, scopeResolver, requestedScopeType, requestedScopeId, ownershipCheck closure, requiredAuthorityType, resourceStatePredicate closure, approvalResolver/closure, requiresElevatedAssurance. Delegates to AuthorizationEvaluator::evaluate(). Default DENY everywhere except explicit ALLOW path. Contract: each domain Policy only supplies its OWN permission code, scope resolver, ownership check, resource-state predicate.

**app/Policies/CampaignPolicy.php** — REFERENCE TEMPLATE — Example Policy structure using AuthorizesUsingRbac, PermissionRegistry constants, ScopeType, domain-specific ScopeResolver, resourceStatePredicate closures. Contract: IMP-008 DonationPolicy will follow this exact shape.

**app/Services/Rbac/PermissionRegistry.php** — EXTEND — Add `donation.view`, `donation.cancel`, `donation.recurring_plan.manage`. Convention: module.action (e.g., campaign.view). Included in super_admin bulk grant like other non-audit permissions. definitions() returns [code => [description, module]].

**app/Enums/ScopeType.php** — REFERENCE — CLOSED taxonomy; OWN and ORGANIZATION already defined. IMP-008 must NOT add new values. donation.view uses ORGANIZATION; donor-owned views use OWN; recurring_plan.manage uses both OWN (donor) and ORGANIZATION (admin override).

### Audit

**app/Enums/AuditActorKind.php** — REUSE — Already includes Unauthenticated ('unauthenticated') case. No modification needed. `requiresPrincipal()` returns true only for Human/System/Integration.

**app/Services/Audit/AuditEventDefinition.php** — EXTEND — Register new donation.* events following CampaignAuditEventRegistrar shape. NonCritical, GENERAL visibility, ORGANIZATION scope, hasFinancialReference=true on all events. Actor kinds vary: Human for donor-initiated, System for consequence transitions, Unauthenticated for guest-created (via ADR-002 amendment). execution_context null for Human/System; "http:donation:guest_created" for Unauthenticated guest.

**app/Services/Audit/AuditEventRegistry.php** — EXTEND — New donation.* events registered via registrar pattern (not editing the file directly). All NonCritical (persistenceStrategy=null). HARD_PROHIBITED metadata keys enforced at registration.

**app/Services/Audit/AuditWriter.php** — REUSE — Single durable persistence contract. CRITICAL failures propagate; NON_CRITICAL failures log+swallow (never propagate). METADATA_MAX_BYTES=8192. Never opens/manages enclosing transaction (caller owns it). Source-event idempotency (source_domain + source_event_id) is internal producer dedupe — NOT applicable to IMP-008 client-request idempotency.

**app/Services/Campaign/CampaignAuditEventRegistrar.php** — REFERENCE TEMPLATE — Registration loop: `$registry->register(new AuditEventDefinition(...))`. One class per domain registering multiple event definitions. Constructor-free pattern.

**app/Services/Campaign/CampaignAuditLogger.php** — REFERENCE TEMPLATE — Thin sink wrapper over AuditWriter; one method per registered event calling `emit(eventType, subjectType, subjectId, metadata, Principal)`. Private emit() normalizes parameters into standard AuditEventInput shape.

**docs/adr/ADR-002-donation-unauthenticated-actor-scope-amendment.md** — CONSUME — Human-approved widening of AuditActorKind::Unauthenticated from one category (failed auth) to two (added: legitimate successful guest action). First registered Category 2 use: donation.created (IMP-008 guest) with execution_context="http:donation:guest_created". No new enum case added.

**docs/implementation/IMP-004-audit-governance-foundation.md** §Canonical Actor (Pre-Principal Cases) — CONSUME — Amended by ADR-002 (line 506-575). Confirms widened scope integration into locked spec.

**tests/Feature/Audit/AuditFoundationTest.php** — REFERENCE TEMPLATE — Proves AuditWriter behavior against real sink (no mocks): registry validation, metadata allow-list/redaction, pre-principal actor attribution, source_event_id idempotency, immutability, read authorization. Uses TruncatesInMemorySqlite.

**tests/Support/Rbac/CapturesRbacAudit.php** — REFERENCE TEMPLATE — Captures audit rows via watermark ID tracking; read/assertLogged/assertNot logged pattern for end-to-end audit assertions without mocking.

### Database / Migrations

**database/migrations/0001_07_01_000000_create_programs_table.php** — REFERENCE — Convention: $table->id(); char('ulid', 26)->unique(); foreignId()->constrained()->restrictOnDelete(); index on status; dateTime with precision 6.

**database/migrations/0001_07_01_000001_create_funds_table.php** — REFERENCE — Same PK-ulid-FK-index conventions. code VARCHAR(50) UNIQUE. No amount/balance column.

**database/migrations/0001_07_01_000002_create_campaigns_table.php** — REFERENCE — Composite eligibility window index [status, starts_at, ends_at]. program_id/fund_id nullable FKs with RESTRICT. All *_by_principal_id FKs -> principals RESTRICT.

**app/Models/Campaign/Concerns/GeneratesUlid.php** — REUSE — Boot trait assigning ulid via Str::ulid() on creating event. Duplicated per domain. Contract: `$model->ulid ??= (string) Str::ulid()`.

**tests/Feature/Campaign/CampaignSchemaConstraintsTest.php** — REFERENCE TEMPLATE — DB-level uniqueness enforcement via raw INSERT bypassing service layer; asserts QueryException on constraint violation. Pattern applies to verifying donations.idempotency_key unique constraint.

### Idempotency

No existing Donation-request idempotency pattern found. AuditWriter source_event_id dedupe is internal producer trust — not applicable to IMP-008's client-provided ID header requirement. The specification correctly identifies the need for a separate mechanism: HTTP header `Idempotency-Key` + `donations.idempotency_key` UNIQUE column + transactional insert-or-refetch semantics. Contract: BD-12 specifies full replay semantics (match payload = return existing Donation; differing payload = 409 conflict).

### Transaction / Concurrency

**app/Services/Campaign/CampaignLifecycleService.php** — DIRECT TEMPLATE for Donation status transitions. DB::transaction() wrapping lockForUpdate()->firstOrFail() + status re-check under lock + transition throw on mismatch + audit append inside same transaction. Mirrors PublicationService exactly. Used for: PENDING->SUCCEEDED/FAILED (authorized consequence surface), PENDING->EXPIRED (configured sweep), PENDING->CANCELLED (donor/admin cancel).

**app/Services/Campaign/CampaignService.php** — REFERENCE PATTERN for Donation creation/service wrap (insert + audit in DB::transaction). Update uses lockForUpdate + edit_version optimistic concurrency.

**tests/Support/Rbac/CapturesRbacAudit.php** + **tests/Feature/Identity/IdentityAuditFailureRollbackTest.php** — REFERENCE PATTERN — Forced audit-write-failure rollback evidence for CRITICAL events. IMP-008 events are NonCritical (persistenceStrategy=null), so audit failure does NOT roll back the business mutation — but the test discipline documents the boundary.

### Scheduler

**routes/console.php** — EXTEND — Current convention: `Schedule::command('...')->everyMinute()->withoutOverlapping()` and `hourly()->withoutOverlapping()`. Shared-hosting compatible — no Redis/Supervisor/PM2/WebSocket. IMP-008 expiration sweep and monthly occurrence generation fit this exact pattern. Schedule command name: `donation:expire-pending` (cron-based, config-gated, default null). Recurring occurrence generation: `recurring:generate-occurrences` (monthly cadence when engine is later built).

### HTTP / Inertia

**routes/web.php** (lines 234-236 public Campaign routes) — REFERENCE TEMPLATE — Public routes bound by slug/{ulid} parameter binding; Inertia::render() returns field allow-lists (never raw models exposing BIGINT ids); abort_unless(404) visibility gates; controller delegates to service.

**routes/web.php** (lines 44-66 guest auth; lines 69-227 authenticated admin) — REFERENCE TEMPLATE — Guest middleware group for unauthenticated actions; auth+identity.active for protected routes; prefix-based admin grouping (admin/campaign/* => admin/donation/*); /me/* for donor-owned views; ULID-bound route keys throughout. Name prefixes follow domain.* convention.

**app/Http/Controllers/PublicCampaignController.php** — REFERENCE TEMPLATE — Controller delegates eligibility computation to CampaignEligibilityResolver; returns field allow-lists via explicit array literals; Money::ofMinorUnits() for formatted amounts. Contract: no business logic in controllers — all delegation to application services.

**app/Http/Requests/Auth/RegisterUserRequest.php** + **app/Http/Requests/Cms/StorePageRequest.php** — REFERENCE TEMPLATE — FormRequest validation convention in app/Http/Requests/* namespace. IMP-008 StoreDonationRequest / StoreRecurringPlanRequest follow this convention.

### Tests

**tests/TestCase.php** — BASE — Abstract base class extending Laravel BaseTestCase.

**tests/Support/Rbac/RbacTestActors.php** — REFERENCE — Test actor bootstrap: makeAuthorizedActor() (every permission at GLOBAL_PLATFORM scope + ELEVATED assurance) and makeUnauthorizedActor() (no permissions, STANDARD assurance). Creates User, resolves to Principal via app(PrincipalService::class)->forUser().

**.env.testing** — MySQL connection target: kmsitdonation_imp003_test. Per AC-008-017 concurrency tests require REAL disposable MySQL (SQLite insufficient for genuine row-locking/transaction-isolation).

**tests/Unit/Campaign/MoneyTest.php** — TEMPLATE — Direct Money::ofMinorUnits() construction/equality/format assertions across IDR(2)/JPY(0)/KWD(3)/unregistered currencies; 2**53+1 precision proof; UnknownCurrencyException expected.

**tests/Unit/Campaign/CampaignEligibilityResolverTest.php** — TEMPLATE — Status/date-boundary permutation tests; makeCampaign() factory pattern.

## 4. New IMP-008 Files / Extension Points

### CREATE

- `database/migrations/0001_08_01_000000_create_donation_recurring_plans_table.php`
- `database/migrations/0001_08_01_000001_create_donation_recurring_occurrences_table.php`
- `database/migrations/0001_08_01_000002_create_donations_table.php`
- `database/migrations/0001_08_01_000003_add_donation_fk_to_donation_recurring_occurrences_table.php`
- `app/Models/Donation/Donation.php`
- `app/Models/Donation/Concerns/GeneratesUlid.php` (or copy from Campaign/Concerns)
- `app/Models/Donation/DonationStatus.php` (enum or string constant list)
- `app/Models/Donation/DonationFrequency.php` (enum for frequency allow-list)
- `app/Services/Donation/DonationService.php`
- `app/Services/Donation/DonationTransitionService.php`
- `app/Services/Donation/DonationAuditEventRegistrar.php`
- `app/Services/Donation/DonationAuditLogger.php`
- `app/Policies/DonationPolicy.php`
- `app/Services/Donation/DonationIdempotencyService.php`
- `app/Http/Controllers/PublicDonationController.php`
- `app/Http/Controllers/DashboardDonationController.php`
- `app/Http/Controllers/AdminDonationController.php`
- `app/Http/Requests/StoreDonationRequest.php`
- `app/Http/Requests/StoreRecurringPlanRequest.php`
- `app/Console/Commands/ExpirePendingDonations.php` (PROPOSED PATH)
- `app/Console/Commands/GenerateRecurringOccurrences.php` (PROPOSED PATH)

### EXTEND

- `app/Services/Rbac/PermissionRegistry.php` — Add donation.view, donation.cancel, donation.recurring_plan.manage constants + definitions entries
- `app/Services/Audit/AuditEventRegistry.php` — Extend via DonationAuditEventRegistrar (called during bootstrapping)
- `routes/console.php` — Add Schedule::command('donation:expire-pending') and Schedule::command('recurring:generate-occurrences') entries
- `routes/web.php` — Add public donation entry point + donor-owned + admin groups

### REFERENCE ONLY

- app/Support/Money/Money.php
- app/Support/Money/CurrencyMinorUnits.php
- app/Support/Money/Exceptions/UnknownCurrencyException.php
- config/money.php
- app/Services/Campaign/CampaignEligibilityResolver.php
- app/Models/Campaign/Campaign.php
- app/Models/Campaign/Concerns/GeneratesUlid.php
- app/Models/Rbac/Principal.php
- app/Services/Rbac/PrincipalService.php
- app/Policies/Concerns/AuthorizesUsingRbac.php
- app/Policies/CampaignPolicy.php
- app/Services/Campaign/CampaignAuditLogger.php
- app/Services/Campaign/CampaignAuditEventRegistrar.php
- app/Services/Audit/AuditWriter.php
- app/Services/Audit/AuditEventDefinition.php
- app/Enums/AuditActorKind.php
- app/Enums/ScopeType.php
- database/migrations/0001_07_01_000002_create_campaigns_table.php

## 5. Locked / High-Risk Boundaries

- Donation != Payment (IMP-009 owns payment hub — IMP-008 defines transition surface only)
- Donation != Ledger (IMP-011 owns authorized financial consequence posting — BR-5)
- Fund != mutable balance (IMP-007 locked decision; Donation never stores fund_id directly)
- Commission != Wallet (IMP-014 owns commission entitlement; no generic mutable wallet)
- CampaignEligibilityResolver::isDonationEligible() MUST be called at creation time; NEVER re-derive independently
- Money::ofMinorUnits() + CurrencyMinorUnits::digitsFor() MUST be used exclusively; no float columns; no second Money abstraction
- Guest recurring donation PROHIBITED in v1 (HD-IMP008-01B, BR-6)
- Recurring frequency v1 restricted to MONTHLY via allow-list (HD-IMP008-02, BR-9) — schema remains extensible
- Guest self-service cancellation PROHIBITED in v1 (HD-IMP008-05B, BR-13) — admin support path only via ORGANIZATION scope
- Donation expiration timeout CONFIGURABLE, no invented default (HD-IMP008-04) — mechanism gated by config('donation.pending_expiry_minutes'), default null
- Donation idempotency (client Header Idempotency-Key + unique column + transactional replay) is NOT AuditWriter source_event_id idempotency (internal trusted-producer dedupe)
- Guest donation audit uses ADR-002 narrow Unauthenticated actor path (HR-IMP008-06): actor_principal_id=NULL, execution_context="http:donation:guest_created", actor kind AuditActorKind::Unauthenticated — NO new enum value added
- Super Admin does NOT automatically imply business/financial authority (mirrors Q27)
- Audit event criticality: IMP-008 specification explicitly states ALL donation.* events are NonCritical (NonCritical = business events, not Critical = identity/security/governance). Financial-reference classification (hasFinancialReference=true) does NOT change criticality. Implementer must NOT elevate any donation.* event to Critical.
- Audit visibility: ALL donation.* events use GENERAL visibility (non-SECURITY) — confirmed by spec section "Audit Requirements" → "All Donation events are classified NonCritical... consistent with how IMP-007's own financially-adjacent events were classified."

## 6. Implementation Order

1. **Database migrations** — Recurring plans, occurrences, then donations (FK ordering per spec BD-12)
2. **Domain primitives** — GeneratesUlid trait (copy/reuse), DonationStatus/Frequency enums if created
3. **Models** — Donation.php, DonationRecurringPlan.php, DonationRecurringOccurrence.php
4. **Idempotency service** — Client ID header + unique column enforcement + replay semantics
5. **Application services** — DonationService (create/cancel), DonationTransitionService (authorized consequence surface)
6. **Audit registration/logger** — DonationAuditEventRegistrar + DonationAuditLogger (mimics Campaign* pattern)
7. **Authorization** — DonationPolicy (follows CampaignPolicy structure), extend PermissionRegistry with new permissions
8. **HTTP/controllers/routes** — PublicDonationController, DashboardDonationController, AdminDonationController; route groups; FormRequests
9. **Scheduler commands** — ExpirePendingDonations, GenerateRecurringOccurrences (deferred engine build; schema/ownership boundary only)
10. **Tests** — Unit (Money/path guards), Feature (full lifecycle matrix, RBAC rows, audit emission, schema constraints, concurrency)

## 7. Test Map

**Unit:**
- Money/CurrencyMinorUnits construction paths (template: tests/Unit/Campaign/MoneyTest.php)
- BR-2 guest-path CHECK guard (template: Principal::isConsistent tests in tests/Feature/Rbac/)

**Feature:**
- Donation creation (guest + authenticated) with full lifecycle transition matrix (template: tests/Feature/Campaign/CampaignServiceTest.php + CampaignLifecycleServiceTest.php)
- Rejected invalid transitions per BR-7 (template: CampaignLifecycleServiceTest.php invalid_transition tests)
- Campaign eligibility rejection (template: tests/Feature/Campaign/PublicCampaignControllerTest.php + AC-008-003)
- Idempotency: missing key rejected, identical+matching returns existing, identical+differing rejected (NEW — template: CampaignSchemaConstraintsTest.php for DB-level constraint patterns, plus CampaignServiceTest.php for replay semantics)

**Authorization (RBAC):**
- Every "Authorization / RBAC" spec row verified: authorized ALLOW, unauthenticated DENY where auth required, wrong permission DENY, correct permission wrong scope DENY, wrong resource-state DENY, cross-donor DENY (template: tests/Feature/Campaign/CampaignAuthorizationTest.php + tests/Feature/Rbac/)

**Audit:**
- Every donation.* event recorded with correct criticality/visibility/financial-reference flag (template: tests/Feature/Audit/AuditFoundationTest.php)
- Guest donation.created records AuditActorKind::Unauthenticated, actor_principal_id null, execution_context="http:donation:guest_created"; authenticated donor records AuditActorKind::Human, execution_context null (template: same, with actor-kind assertion)
- Forced audit-append failure does NOT roll back NonCritical Donation event (template: tests/Feature/Identity/IdentityAuditFailureRollbackTest.php — note difference: IMP-008 NonCritical means audit failure is swallowed, business mutation commits independently)

**Database constraint:**
- RESTRICT on campaign_id/donor_principal_id/recurring_occurrence_id deletion (template: tests/Feature/Campaign/CampaignSchemaConstraintsTest.php)
- Guest-path CHECK constraint at DB level where driver supports (MySQL), app-level guard on SQLite

**Money:**
- Unregistered-currency rejection; minor-unit correctness across JPY(0), KWD(3), plus at least one 2-digit currency (template: tests/Unit/Campaign/MoneyTest.php)

**Concurrency (REAL DISPOSABLE MYSQL REQUIRED):**
- Two concurrent transition attempts on same PENDING Donation — exactly one succeeds (template: tests/Feature/Campaign/CampaignLifecycleServiceTest.php + PublicationService scheduler concurrency patterns)
- Requires real MySQL, not SQLite, for genuine row-locking/transaction-isolation behavior (per AC-008-017)

**MySQL Disposable DB Convention:**
- Use dedicated IMP-008-scoped disposable database/schema following existing _imp{N}_test naming convention (e.g., kmsitdonation_imp008_test)
- NEVER use kmsitdonation (live/development database) for destructive testing
- .env.testing currently targets kmsitdonation_imp003_test; tests may override connection/database as needed per test class

## 8. Review Hotspots

- **Client-provided idempotency key race/replay semantics**: Insert-or-fetch (never check-then-insert); unique constraint violation caught → re-fetch → compare payload → either return existing or reject with 409
- **lockForUpdate/state re-check under lock**: Two concurrent transitions on same Donation — second observes terminal state, rejects with typed conflict exception (mirrors CampaignLifecycleService/PublicationService)
- **Recurring Occurrence concurrency**: Unique donation_id constraint on donation_recurring_occurrences prevents double-generation; tested via FK integrity, no behavioral engine tests (engine deferred)
- **Donation expiration concurrency**: Sweep job iterates PENDING donations within timeout window; each row locked individually; config-gated (default null means nothing expires until configured)
- **Campaign eligibility at Donation creation**: Must consume CampaignEligibilityResolver::isDonationEligible() exactly; never re-derive status/date logic independently
- **OWN vs ORGANIZATION scope**: Donor viewing own Donations = OWN scope; admin/staff viewing all = ORGANIZATION scope; recurring plan management has both OWN (donor self-service) and ORGANIZATION (admin override) paths — same permission code, different scope
- **Guest unauthenticated audit attribution**: ADR-002 narrow widening — AuditActorKind::Unauthenticated with fixed execution_context = "http:donation:guest_created" for donation.created only; actor_principal_id ALWAYS NULL; no new enum case
- **Audit criticality**: Strictly NonCritical per approved spec — do NOT elevate any donation.* event to Critical just because hasFinancialReference=true. NonCritical means audit write failure does NOT roll back the business mutation.
- **Transaction/audit failure semantics**: CRITICAL events = atomic commit-or-roll-back; IMP-008 donation.* events are NonCritical = audit write failure is logged+swallowed, business mutation proceeds
- **Money precision/currency registry**: Zero assumption about 2 decimal digits; JPY(0 digits), KWD(3 digits) explicitly tested; no float ever authoritative; format() uses pure integer/string arithmetic
- **No Payment/Ledger leakage**: Donation creation MUST NOT create Ledger journal/entry, Commission entitlement, Withdrawal balance, or Reconciliation entry (BR-5)

## 9. Recon Closure

OPEN HUMAN DECISIONS: 0
SPEC / REPOSITORY CONFLICTS: 0
LOCKED CONTRACT MODIFICATION REQUIRED: NO
ARCHITECTURAL ESCALATION: NO
IMPLEMENTATION AUTHORIZED BY RECON: NO
RECON STATUS: COMPLETE
