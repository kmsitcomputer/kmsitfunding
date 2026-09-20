# IMP-009 — Payment Hub

## Status

READY (pending Human Spec Approval — implementation is NOT authorized by this document)

## Implementation Ownership

Per [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) —
no implicit/default model substitution is permitted; if the assigned model/owner is unavailable,
stop and report rather than substituting silently.

```
Stage Type:   V3 PIPELINE STAGE (IMP-008 forward, see MULTI-MODEL-OWNERSHIP.md "Amendment V3")
```

IMP-009 is one of the seven **Mission-Critical Claude Stages**
(MULTI-MODEL-OWNERSHIP.md:1276-1287 — "IMP-009 Payment Hub" listed first). Per GOV-MM-002, its
**Implementation Write Owner is Claude Code**, not the default Muse Spark 1.3 Contributor, and a
separate Per-IMP Model Binding record is required — in addition to the V3 block below — before
implementation begins (MULTI-MODEL-OWNERSHIP.md:1376-1408). That binding is **not** established by
this specification document; it is recorded at the implementation gate, after Human Spec Approval,
exactly as MULTI-MODEL-OWNERSHIP.md's example for "IMP-009 Payment Hub" shows.

```
V3 Claude Lead Architect Identity Binding (distinct from GOV-MM-002 — see
MULTI-MODEL-OWNERSHIP.md "V3 Claude Lead Architect Identity Binding"):
  Role:                    Claude Lead Architect / Specification Owner
  Execution Environment:   Claude Code (VS Code extension; entrypoint claude-vscode)
  Display Model:           Claude Sonnet 5
  Exact Model Identifier:  claude-sonnet-5
  Verification Method:     this session's system-declared model identity
                            (see conversation "System" block: "You are powered by the model named
                            Sonnet 5. The exact model ID is claude-sonnet-5."), not guessed, not
                            reused from a prior IMP's binding.
  Verification Status:     VERIFIED
  Specification Revision:  this document's own initial commit (see git history for exact hash;
                            baseline branch state at authoring time was master @
                            fa5e4009faeb177c0d1b6e793e220908be62a4a3)

FAIL CLOSED: the identity above was established before this specification was submitted for
Human Spec Approval, per the rule this binding exists to enforce.

Human Spec Approval:                     NOT YET APPROVED — see "Status" above. This document is
                                          the artifact submitted for that gate; the Approval
                                          fields below are intentionally left blank until Human
                                          Spec Approval is actually given (no fabricated
                                          approval — see MULTI-MODEL-OWNERSHIP.md "Human Spec
                                          Approval Gate").
Approval Statement:
Approval Scope:                          IMP-009 + this specification revision (see above)
Approval Evidence:

Qwen Recon Binding:                      qwen/qwen3.8-flash (current Command Code baseline — see
                                          MULTI-MODEL-OWNERSHIP.md "Model Version Pinning").
                                          NOT YET INVOKED — governed Recon must not occur before
                                          or concurrently with Human Spec Approval (HD-V3-R2-01).
                                          This document was authored using only the minimum
                                          repository context Claude needed to design the
                                          specification correctly (see "Authority" and inline
                                          citations throughout), not a governed, repository-wide
                                          Qwen Recon pass.
RECON Evidence:                          N/A — not started

Implementation Write Owner:              Claude Code — Mission-Critical Claude Stage
                                          (IMP-009/010/011/014/015/016/027, MULTI-MODEL-OWNERSHIP.md
                                          "Mission-Critical Claude Stages"). Requires its own
                                          GOV-MM-002 Claude Per-IMP Model Binding record (BOUND
                                          status) before implementation may proceed — not yet
                                          recorded (see above).
Implementation Commit/Diff:              N/A — not started
Test Evidence:                           N/A — not started

DeepSeek Independent Review Binding:     deepseek/deepseek-v4.1-flash (current baseline)
Findings:                                N/A — not started
Remediation Owner:                       N/A
Remediation Evidence:                    N/A
Full Regression:                         N/A

Codex Closure Audit:                     N/A — not started
Human Stage Gate:                        N/A — not started
Finalization Status:                     READY (specification phase only)
```

## Objective

Define the authoritative Payment domain and provider-integration boundary: what a Payment
(Attempt) is, its provider-neutral lifecycle, its relationship to Donation (IMP-008, consumed
as-is, never redefined), the four approved provider adapters (Manual Bank Transfer, Tripay,
Xendit, Stripe), webhook/callback security, idempotency, concurrency, expiration, money handling,
authorization, System/Integration Principal usage, audit, configuration, file security, and its
explicit boundary against Ledger (IMP-010), Financial Consequence Posting (IMP-011), Refund
(IMP-016), and Reconciliation (IMP-017) — so a later implementation stage can build IMP-009 from
this document alone, without inventing business rules.

This document does **not** authorize implementation. It is the artifact submitted for Human Spec
Approval (see "Implementation Ownership" above).

## Authority

Source Requirements:

- [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §6
  (Payment Hub / Manual Bank Transfer / Tripay / Xendit / Stripe / Moota recognized as domains),
  §7 (Financial Requirements — "Payment is NOT Ledger", canonical lifecycle), §9 (Payment
  Requirements — "Donation != Payment. Payment may have multiple intents/attempts. Provider
  redirect is not authoritative payment confirmation.", approved provider set, Midtrans excluded).
- [docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md)
  — Q3/Q4 (Configurable Commission/Operational Fee — later IMPs consume Payment outcomes), Q20
  (IDR Base + Limited Multi-Currency), Q26 (Per-Category Audit Write Failure Semantics —
  fail-closed default), Q27 (audit-read authorization), Q28 (append-only audit + governed
  retention). No Q-numbered entry resolves Payment provider selection, Payment idempotency, or
  webhook idempotency specifically — those remain this document's own responsibility to define
  (see "Idempotency", "Webhook Security").
- [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §8 (Payment
  module: owns Payment lifecycle, Payment Intent, Payment Attempt, Provider Event, Payment
  Verification; does NOT own Donation semantics, Commission, or Ledger accounting truth), §9
  (Finance & Fund owns Refund/Withdrawal/Operational Fee), §10 (Ledger), §11 (Reconciliation —
  Moota is a reconciliation input, never a Ledger writer), §15 (Communication owns Notification
  Intent/Attempt/Delivery Result).
- [docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md)
  — canonical posting chain; Payment sits at "Business / Provider / Reconciliation Event" through
  "Business State Transition" and stops before "Authorized Financial Consequence" (IMP-011's).
- [docs/03-database/DATABASE-ARCHITECTURE.md](../03-database/DATABASE-ARCHITECTURE.md) and
  [docs/03-database/DATABASE-INVARIANTS.md](../03-database/DATABASE-INVARIANTS.md) — BIGINT
  unsigned internal PKs, ULID public identifiers, DECIMAL/never-FLOAT monetary rule, financial
  idempotency, immutable financial history.
- [docs/04-security/SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md) and
  [docs/04-security/SECURITY-INVARIANTS.md](../04-security/SECURITY-INVARIANTS.md) — System
  Principal / Integration Principal distinction; "A webhook/callback is never trusted without
  verification and replay protection"; "A sensitive file is never reachable through a permanent
  public URL"; separation-of-duties invariant.
- [docs/05-rbac/RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md),
  [docs/05-rbac/DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md),
  [docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md](../05-rbac/BUSINESS-AUTHORITY-MODEL.md) — canonical
  authorization AND-chain, closed scope taxonomy, closed Business Authority Type taxonomy
  (`financial_approver`, `refund_approver`, `withdrawal_approver`, `distribution_approver`,
  `zakat_authority`, `partner_verifier`, `beneficiary_verifier` — no `payment_verifier`/
  `payment_approver` type exists; see "Authorization").
- [docs/implementation/IMP-003-rbac-scope-business-authority.md](IMP-003-rbac-scope-business-authority.md)
  §"System Principal / Integration Principal" — `system_principals` (example code
  `scheduler.commission-recalc`) and `integration_principals` (example code
  `payment.tripay-webhook`) catalogs, consumed as-is (see "System Principal").
- [docs/implementation/IMP-004-audit-governance-foundation.md](IMP-004-audit-governance-foundation.md)
  — `AuditWriter`/`AuditEventRegistry`, actor kinds (`human`/`system`/`integration`/
  `unauthenticated`/`pre_principal_system`), CRITICAL/NON_CRITICAL persistence-strategy axis,
  `source_domain`/`source_event_id`/`event_type` composite-unique idempotency primitive
  (reserved `payment_provider:<name>`-style `source_domain` value — IMP-004-audit-governance-foundation.md:342).
- [docs/implementation/IMP-007-campaign-program-fund.md](IMP-007-campaign-program-fund.md) — Money
  value object (`Money::ofMinorUnits()`), `CurrencyMinorUnits::digitsFor()`, non-authoritative
  `target_amount_minor`, Fund has no balance column — consumed, never redefined.
- [docs/implementation/IMP-008-donation.md](IMP-008-donation.md) — the LOCKED Donation contract
  this specification integrates against without modification (see "Donation Integration").
- [AGENTS.md](../../AGENTS.md) "Locked Decisions", "Never", "Financial Rules", "Security".

Architecture References (locked, consumed as-is):

```
Donation != Payment
Payment != Ledger
Reconciliation != Ledger
Fund != mutable balance
Commission != Wallet
Payment may have multiple intents/attempts.
Provider redirect is not authoritative payment confirmation.
Canonical posting chain (FINANCIAL-POSTING-BOUNDARY.md): Business/Provider/Reconciliation Event ->
  Normalize/Validate -> Owning Application Use Case -> Domain Validation -> Business State
  Transition -> Authorized Financial Consequence -> Ledger Posting Contract -> Double-Entry Ledger.
Canonical authorization AND-chain (RBAC-ARCHITECTURE.md): Authenticated AND Permission AND
  Applicable Data Scope AND Ownership/Subject Access Rule AND Required Business Authority AND
  Valid Resource State AND Required Approval State AND Required Authentication Assurance AND No
  Security Restriction. Default DENY.
No provider callback may write directly to Ledger (MASTER-REQUIREMENTS.md §9; AGENTS.md "Never").
```

Reused directly (not re-implemented):

```
Donation.status transition surface (IMP-008) — PENDING -> SUCCEEDED/FAILED is "NOT reachable
  through any human-facing permission. Reserved for an authorized System Principal invocation
  only... IMP-009/011 define WHO/WHAT calls this" (IMP-008-donation.md:605-614). IMP-009 IS that
  caller for SUCCEEDED/FAILED (see "Donation Integration" / "Financial Boundary").
Donation.expired_at / PENDING -> EXPIRED sweep (IMP-008) — Donation's OWN clock, entirely separate
  from a Payment Attempt's expiration (IMP-008-donation.md:463-468, HD-IMP008-04, `FINAL/LOCKED`).
  IMP-009 MUST NOT gate its own Payment expiry on `donations.pending_expiry_minutes`, and MUST NOT
  cause Donation's own sweep to fire early or late.
Money::ofMinorUnits(int $amountMinor, string $currency): self / CurrencyMinorUnits::digitsFor() —
  app/Support/Money/* (IMP-007). IMP-009 constructs/validates every Payment amount exclusively
  through this value object; no second Money abstraction.
Principal (app/Models/Rbac/Principal.php, IMP-003) — donor_principal_id / verified_by_principal_id
  / cancelled_by_principal_id etc. reference this table, never `users` directly.
system_principals / integration_principals (IMP-003) — see "System Principal".
AuditEventRegistry / AuditWriter (IMP-004) — the sole audit sink; no parallel mechanism.
CampaignEligibilityResolver::isDonationEligible() — checked ONLY at Donation-creation time per
  IMP-008 BR-1; IMP-009 does NOT re-check Campaign eligibility at Payment-success time (this
  preserves IMP-008's own explicit design choice — see "Donation Integration").
```

## Scope

```
Payment (Attempt) aggregate — one row per concrete provider transaction/session against a
  PENDING Donation (see "Domain Model").
Provider-neutral canonical Payment state machine and per-provider status-normalization adapters
  (see "State Machines").
Provider Adapter architecture for exactly four approved providers: Manual Bank Transfer, Tripay,
  Xendit, Stripe (see "Provider Adapter Architecture" and per-provider sections). Midtrans
  excluded (MASTER-REQUIREMENTS.md §9).
Webhook/callback receipt, signature verification, replay protection, and normalization pipeline
  for Tripay/Xendit/Stripe; manual verification workflow for Manual Bank Transfer (see "Webhook
  Security", "Manual Transfer").
Payment (Attempt) creation idempotency (client-provided key) and provider-side/webhook
  idempotency (see "Idempotency").
Payment expiration, independent from Donation expiration (see "Expiration").
The System-Principal-gated invocation of Donation's existing PENDING -> SUCCEEDED/FAILED
  transition surface (IMP-008) upon a terminal Payment outcome (see "Donation Integration",
  "Financial Boundary") — WITHOUT constructing an Authorized Financial Consequence or touching
  Ledger (IMP-010/011's own, later concern).
Authorization (RBAC) for donor-owned, guest, admin/staff, and system/integration Payment actions.
Audit events for the full Payment lifecycle, webhook verification outcomes, and manual-transfer
  verification decisions.
Database schema: `payments`, `payment_provider_events`, `manual_transfer_evidence`,
  `payment_provider_credentials`, `manual_transfer_bank_accounts`.
Minimum public/donor-owned/admin/system route contracts needed to create, observe, retry, and
  (for Manual Transfer) submit/verify a Payment — not the full IMP-025 REST API.
Shared-hosting-compatible execution for every scheduled/background Payment concern (expiry sweep,
  status-poll fallback where a provider requires it) via Laravel Scheduler + Cron + DB queue.
```

## Out of Scope

Explicitly deferred to later IMPs — this specification defines only the contract surface those
stages will consume, never their implementation:

```
IMP-010 Ledger Foundation:        Ledger Account, Ledger Journal, Double-Entry Entries. IMP-009
                                   never writes to a Ledger table (none exists yet).
IMP-011 Financial Consequence
  Posting:                         Authorized Financial Consequence construction/posting itself.
                                   IMP-009 exposes a Payment-outcome event/trigger surface that a
                                   later IMP-011 consumes; it does not construct or post a
                                   Consequence object itself.
IMP-012 Operational Fee:          Fee calculation/deduction from a settled Payment.
IMP-013 Fundraiser + Attribution: Referral/attribution capture. IMP-009 adds no attribution column
                                   to `payments` or `donations`.
IMP-014 Commission:               Commission entitlement/calculation from a succeeded Payment.
IMP-015 Withdrawal:               Withdrawal of any balance.
IMP-016 Refund:                   Refund business workflow of a succeeded Payment. IMP-009 exposes
                                   the provider-capability interface a future Refund workflow may
                                   need (e.g. "does this provider adapter support a refund call"),
                                   but never a Refund entity, approval flow, or Ledger reversal.
IMP-017 Reconciliation + Moota:   Bank-mutation matching. Payment status synchronization via a
                                   provider webhook/callback is NOT reconciliation — Moota remains
                                   reconciliation-only, never a Payment Gateway, Ledger, or
                                   settlement source of truth (MASTER-REQUIREMENTS.md §7/§9,
                                   MODULE-OWNERSHIP.md §11).
IMP-024 Notifications + WhatsApp: The complete notification system. IMP-009 emits canonical
                                   domain events (e.g. `payment.succeeded`) a future IMP-024
                                   subscriber may consume; it does not send a WhatsApp message,
                                   email, or any other notification itself.
IMP-025 REST API:                 Full `/api/v1` Payment surface. Only the minimum
                                   application/web route contracts needed for the web donation
                                   flow are defined here (see "Routes / API Boundary").
Any later IMP:                    Chart-of-Accounts / accounting treatment mapping for a
                                   succeeded Payment (EXTERNAL VERIFICATION — IMPLEMENTATION-GOVERNANCE.md
                                   "A coding agent... must not invent external/legal/accounting
                                   truth"). Partner settlement/distribution. Receipt/compliance
                                   document generation. Flutter/mobile client implementation.
                                   Stored/tokenized payment methods ("card on file") for recurring
                                   auto-charge — HD-IMP009-10, `FINAL / LOCKED`, explicitly
                                   excludes this from IMP-009; a future auto-charge capability
                                   requires its own separate, governed specification.
```

## Affected Domains

```
Payment (new — this IMP; module 8 per MODULE-OWNERSHIP.md)
Donation (IMP-008, read/invoke — Payment reads donations.amount_minor/currency/status at
  creation time and invokes the existing System-Principal-gated transition surface on a terminal
  Payment outcome; Payment never mutates any other Donation column)
Campaign / Fund (IMP-007, read-only, transitive via Donation.campaign_id — never duplicated onto
  Payment; see "Authority" — Reused directly)
RBAC / Principal (IMP-003, read-only for Principal; write for `integration_principals`/
  `system_principals` seed rows this IMP registers for its own webhook/scheduler identities)
Audit (IMP-004, write — new payment.* / manual_transfer.* event definitions registered into the
  existing registry)
```

## Locked Inputs

Non-negotiable inputs this specification treats as already decided (restated here for a single
point of reference; not re-derived, not re-opened):

```
Payment providers: Manual Bank Transfer, Tripay, Xendit, Stripe. Midtrans explicitly excluded
  (MASTER-REQUIREMENTS.md §9; requires an approved ACR to add).
Moota: reconciliation-only, IMP-017's own module, never a Payment Gateway/Ledger/settlement
  source of truth (MASTER-REQUIREMENTS.md §7; MODULE-OWNERSHIP.md §11).
Donation != Payment != Ledger (AGENTS.md "Locked Decisions"; FINANCIAL-POSTING-BOUNDARY.md).
No provider callback may write directly to Ledger (MASTER-REQUIREMENTS.md §9; AGENTS.md "Never").
Payment may have multiple intents/attempts; provider redirect is not authoritative confirmation
  (MASTER-REQUIREMENTS.md §9).
Shared-hosting-compatible production is mandatory: no mandatory Redis, Supervisor, PM2, WebSocket
  runtime, Kafka, RabbitMQ, Elasticsearch, Node runtime, or separate application server. Laravel
  Scheduler + Cron + database-backed queue is the allowed job-processing model
  (MASTER-REQUIREMENTS.md §4/§5; MASTER-ARCHITECTURE.md:37).
DECIMAL (never FLOAT/DOUBLE) is the locked monetary column rule (DATABASE-ARCHITECTURE.md:34-39);
  IMP-007/IMP-008 already established `amount_minor` (BIGINT UNSIGNED, minor-unit integer) +
  `currency` (CHAR 3) via `Money`/`CurrencyMinorUnits` as the concrete column-level pattern this
  platform actually uses for monetary values — IMP-009 follows that already-established pattern
  for consistency (see "Money / Currency"), not a fresh DECIMAL column design.
BIGINT unsigned internal PKs, ULID public/API-safe identifiers (DATABASE-ARCHITECTURE.md:27-32).
System Principal / Integration Principal are never conflated (SECURITY-INVARIANTS.md:23).
A webhook/callback is never trusted without verification and replay protection
  (SECURITY-INVARIANTS.md:22).
A sensitive file is never reachable through a permanent public URL (SECURITY-INVARIANTS.md:21).
```

## Human Decisions

Twelve Human Decisions govern this specification — the original ten genuine business/architecture
ambiguities this document raised as OHD-IMP009-01 through OHD-IMP009-10 (none invented, none
resolved by any authoritative document at initial-draft time — see "Authority"), plus two further
decisions (HD-IMP009-11, HD-IMP009-12) the Human issued to resolve this document's own
"Architectural Escalation" and "New ADR Required" findings. All twelve are now `FINAL / LOCKED`.
Full text, materialization pointers, and rationale for each are recorded under "Human Decisions
(Resolved)" below. One-line index:

```
HD-IMP009-01  Maximum ONE ACTIVE Payment Attempt per Donation, enforced at DB/domain level.
HD-IMP009-02  A late provider outcome is never discarded; Payment records the verified fact
              regardless; the Donation-transition request is honored only if IMP-008's own state
              machine permits it, otherwise flagged for controlled exception/manual review.
HD-IMP009-03  No hard-coded lifetime maximum Payment Attempts per Donation; HD-IMP009-01's
              one-ACTIVE-at-a-time invariant still applies; abuse/rate controls may be
              configurable.
HD-IMP009-04  No guest self-service resume/retry mechanism keyed on any identifier/email; no new
              guest bearer-token recovery path; an abandoned attempt is recovered only by natural
              expiry (HD-IMP009-09) or an authorized support-controlled process.
HD-IMP009-05  Xendit adapter baseline targets the PaymentRequest API (not the Invoice API),
              isolated behind the provider adapter; exact endpoint/version verified against
              Xendit's own documentation before implementation.
HD-IMP009-06  Proof-of-transfer evidence is MANDATORY for a Manual Transfer Payment to be
              manually approved.
HD-IMP009-07  Manual Transfer amount mismatch (over/under-payment) never auto-produces SUCCEEDED
              Donation treatment; Payment amount stays immutable; mismatch enters a controlled
              exception/manual-review path; no refund/credit/balance/allocation/amount-mutation
              is invented in IMP-009.
HD-IMP009-08  Manual Transfer evidence MAY be resubmitted while the Payment remains in an
              evidence-eligible state; each submission is immutable/auditable; terminal/
              ineligible states reject resubmission.
HD-IMP009-09  Payment expiry fallback is configurable, no hard-coded business duration; provider-
              supplied expiry is preserved/normalized when present.
HD-IMP009-10  Recurring Donation v1 is MANUAL-PER-CYCLE; no stored/reusable payment credentials,
              mandates, or off-session/automatic charging in IMP-009.
HD-IMP009-11  Payment success/failure requests a Donation transition ONLY through IMP-008's
              existing canonical transition surface, via the applicable registered System
              Principal; Payment never bypasses or directly mutates Donation state; this does not
              authorize Ledger posting (IMP-010/IMP-011 boundaries intact).
HD-IMP009-12  A new, narrowly-scoped ADR (ADR-003) — not a generic ADR-002 broadening — governs
              AuditActorKind::Unauthenticated for exactly two IMP-009 guest events.
```

No business rule in this document depends on an unresolved decision — all twelve are now binding.

## Domain Model

### Payment aggregate — field-by-field derivation

Per MODULE-OWNERSHIP.md §8, the Payment module owns "Payment lifecycle, Payment Intent, Payment
Attempt, Provider Event, Payment Verification." This specification deliberately does **not**
create a separate `payment_intents` table distinct from `payments` (Payment Attempt): Donation
(IMP-008) already plays the role of the durable "intent to give" — a Donation is created once, in
PENDING status, before any payment method is chosen — so a second "Intent" layer between Donation
and Payment Attempt would duplicate Donation's own role without new information (per §7's own
instruction: "Do NOT create unnecessary entities merely because gateways use them"). **One
`payments` row = one concrete Payment Attempt = one provider transaction/session.** "Payment
Intent" in MODULE-OWNERSHIP.md's catalog is satisfied by Donation (the intent) plus `payments`
(each concrete attempt to fulfil that intent) together, not by a fourth table.

```
Identity:                  BIGINT PK (internal) + ulid CHAR(26) unique (public-safe reference),
                            mirroring every other IMP-005/006/007/008 aggregate.

Donation relationship:     donation_id (FK donations.id, RESTRICT, NOT NULL). Every Payment
                            targets exactly one Donation. A Donation may have MANY Payments over
                            its lifetime (MASTER-REQUIREMENTS.md §9: "Payment may have multiple
                            intents/attempts"), but at most ONE may be ACTIVE (PENDING or
                            REQUIRES_ACTION) at any moment — `FINAL / LOCKED`, HD-IMP009-01 — see
                            "Concurrency" and "Database Impact" for the DB-level enforcement
                            mechanism.

Provider relationship:     provider (VARCHAR 32, NOT NULL) — one of 'manual_transfer' | 'tripay'
                            | 'xendit' | 'stripe', validated against a closed, registry-driven
                            allow-list (mirrors IMP-008 BR-9's recurring-frequency allow-list
                            pattern: structured so an authorized future adapter can be added
                            without redesigning this column). Midtrans is never a valid value
                            without an approved ACR (MASTER-REQUIREMENTS.md §9).
                            provider_reference (VARCHAR 191, NULLABLE) — the provider's own
                            transaction/invoice/payment-intent identifier, populated once the
                            provider adapter's create-transaction call returns. NULL only in the
                            brief window between row creation and the provider API call returning
                            (see "Concurrency" for how this window is bounded).
                            channel (VARCHAR 64, NULLABLE) — provider-specific payment
                            method/channel code (e.g. a Tripay channel code, a Xendit payment
                            method type, a Stripe payment_method_type). Presentation/routing
                            metadata only, never interpreted as canonical Payment state.

Amount / currency:         amount_minor (BIGINT UNSIGNED, NOT NULL) + currency (CHAR 3, NOT NULL),
                            copied from the owning Donation at Payment-creation time via
                            Money::ofMinorUnits()/CurrencyMinorUnits::digitsFor() (IMP-007) and
                            validated EQUAL to donations.amount_minor/currency (BR-2 below) — a
                            Payment is never created for an amount different from its Donation's
                            own locked, immutable amount (IMP-008 BR-8). No float column, no
                            second Money abstraction.

Payment state:              status (VARCHAR 16, NOT NULL, DEFAULT 'PENDING') — see "State
                            Machines" for the canonical provider-neutral state set.

Idempotency:                idempotency_key (VARCHAR 128, NOT NULL, UNIQUE) — see "Idempotency".
                             Deliberately a SEPARATE key namespace from donations.idempotency_key
                             (HD-IMP008-05A explicitly scopes the Donation key to Donation
                             creation only and explicitly does not anticipate a Payment-level
                             key — IMP-008-donation.md:798-801). Reusing the Donation key here
                             would conflate two independently-retriable operations (a donor can
                             legitimately retry "create a Payment for my already-created Donation"
                             many times across the Donation's lifetime; the Donation itself is
                             created exactly once).

Payment window:              expires_at (DATETIME, NULLABLE) — see "Expiration". Distinct clock
                             from donations.expired_at (IMP-008, HD-IMP008-04, `FINAL/LOCKED`
                             — the two clocks are independently owned and MUST NOT be conflated,
                             per IMP-008-donation.md:463-468, restated here as a hard boundary).

Provider-facing display
  data:                     instructions_payload (JSON, NULLABLE) — a bounded, provider-adapter-
                             normalized structure carrying ONLY presentation/instruction data a
                             donor needs to complete payment (e.g. a virtual-account number, a
                             QRIS string, a redirect URL, a Stripe client_secret opaque token).
                             This is NOT the same thing as the raw webhook/callback payload (see
                             `payment_provider_events` below) — it is intentionally the ONE
                             narrowly-scoped, justified JSON column on this table (per §28's
                             instruction to avoid "JSON dumping as substitute for domain
                             modeling"): its shape is adapter-normalized (a small closed set of
                             known keys per provider, not an arbitrary dump of the provider's raw
                             API response), it is non-authoritative (status/amount/currency/
                             provider_reference remain the authoritative typed columns), and it
                             exists because provider-specific payment instructions genuinely have
                             no shared canonical shape across Manual Transfer/Tripay/Xendit/Stripe
                             (a bank account number, a QR string, and a client_secret are not the
                             same kind of thing). MUST NEVER contain a provider secret/credential
                             or full card data (see "File Security" / "Security Requirements").

Timestamps:                 created_at/updated_at (standard) plus succeeded_at, failed_at,
                             expired_at, cancelled_at (all DATETIME, NULLABLE — at most one
                             non-null, mirroring Donation's own *_at pattern, enforced at the
                             service layer).

Verification/cancellation
  attribution:               verified_by_principal_id (FK principals.id, RESTRICT, NULLABLE) —
                             the admin who approved a Manual Bank Transfer (see "Manual Bank
                             Transfer"). cancelled_by_principal_id (FK principals.id, RESTRICT,
                             NULLABLE) — set when a Payment is cancelled by a human actor (donor
                             abandoning an attempt, or admin action); NULL for a system/provider-
                             initiated terminal transition.

Failure detail:              failure_reason (VARCHAR 255, NULLABLE) — a short, normalized,
                             non-sensitive reason code/string (e.g. 'PROVIDER_DECLINED',
                             'EXPIRED_NO_ACTION', 'MANUAL_REJECTED'). Never a verbatim provider
                             error dump (that belongs in `payment_provider_events`, private/
                             encrypted — see "File Security").

Immutable vs mutable:       donation_id, provider, amount_minor, currency, idempotency_key are set
                             at creation and NEVER mutated afterward. provider_reference, channel,
                             instructions_payload, status (+ companion *_at), verified_by_
                             principal_id, cancelled_by_principal_id, failure_reason, expires_at
                             transition/populate over the Payment's lifecycle per "State Machines".

One-ACTIVE-attempt
  enforcement (HD-IMP009-01,
  `FINAL / LOCKED`):          active_slot (TINYINT, NULLABLE, GENERATED — see "Database Impact"
                             for the exact generated-column expression) — a DB-level mechanism,
                             not merely a service-layer rule, since HD-IMP009-01 explicitly
                             requires enforcement "at DB/domain level." MySQL 8 has no native
                             partial-unique-index syntax (unlike PostgreSQL), so the same effect is
                             achieved via a generated column that evaluates to a constant (`1`)
                             while `status IN ('PENDING','REQUIRES_ACTION')` and to `NULL`
                             otherwise, paired with `UNIQUE(donation_id, active_slot)` — MySQL's
                             standard NULL-distinct unique-index semantics mean any number of
                             terminal (NULL-`active_slot`) rows may coexist per `donation_id`, but
                             at most one row with `active_slot = 1` (i.e. one ACTIVE attempt) may
                             exist per `donation_id` at a time. This is the SAME NULL-distinct
                             technique IMP-004 already relies on for its own
                             `source_domain`/`source_event_id`/`event_type` composite uniqueness
                             (see "Authority"), applied here to a single-column generated-value
                             case instead of a composite key.
```

### `payment_provider_events` (Provider Event, per MODULE-OWNERSHIP.md §8)

The raw, forensic record of every inbound webhook/callback and its verification/processing
outcome — kept SEPARATE from `payments` because it is security/audit evidence, not business
state, and because one Payment may receive many events (retries, duplicates, out-of-order
deliveries) over its lifetime:

```
Identity:                  BIGINT PK + ulid CHAR(26) unique.
Payment relationship:       payment_id (FK payments.id, RESTRICT, NULLABLE) — NULLABLE because an
                            inbound event referencing an unknown provider_reference (no matching
                            Payment row) must still be recorded for forensics/audit before it can
                            be resolved (or permanently rejected) — see "Webhook Security".
Provider identity:          provider (VARCHAR 32, NOT NULL), provider_event_id (VARCHAR 191,
                            NULLABLE) — the provider's own event/notification identifier, when the
                            provider supplies one (Xendit and Stripe both do; Tripay's callback
                            has no independent event ID beyond the transaction reference itself —
                            adapter-specific, see per-provider sections).
Processing outcome:         signature_valid (BOOLEAN, NOT NULL), processing_result (VARCHAR 32,
                            NOT NULL) — one of: ACCEPTED, REJECTED_INVALID_SIGNATURE,
                            REJECTED_UNKNOWN_REFERENCE, REJECTED_AMOUNT_MISMATCH,
                            REJECTED_CURRENCY_MISMATCH, REJECTED_MALFORMED, DUPLICATE.
Evidence:                   raw_payload_ciphertext (LONGTEXT, NULLABLE) — the verbatim inbound
                            payload, application-layer encrypted at rest (see "Configuration" /
                            "File Security"), retained strictly for security-forensics/dispute-
                            evidence purposes. NEVER read by any business-logic code path as an
                            authoritative source — the normalized, typed columns on `payments`
                            remain the sole authoritative business state (mirrors IMP-004's own
                            "audit may reference a future financial resource by ID but never
                            substitutes for the authoritative record" principle, applied here to
                            provider payloads specifically).
received_at (DATETIME NOT NULL), timestamps.

Unique: (provider, provider_event_id) WHERE provider_event_id IS NOT NULL — mirrors IMP-004's own
  `UNIQUE (source_domain, source_event_id, event_type)` composite-uniqueness pattern
  (IMP-004-audit-governance-foundation.md:337-343), reusing the SAME concept (a scoped, trusted-
  producer-supplied identifier, NULL-distinct, not globally unique by itself) at the Payment
  domain's own table rather than overloading the audit table for business-state dedupe — audit
  remains a record OF this table's idempotency decision, never the mechanism enforcing it (see
  "Idempotency" / "Webhook Security" for the full contract).
Indexes: payment_id, provider, processing_result, received_at.
```

### `manual_transfer_evidence`

```
Identity:                  BIGINT PK + ulid CHAR(26) unique.
Payment relationship:       payment_id (FK payments.id, RESTRICT, NOT NULL) — a Manual Bank
                            Transfer Payment only.
Submitter:                  submitted_by_principal_id (FK principals.id, RESTRICT, NULLABLE) —
                            NULL for a guest submission, mirroring Donation's own guest pattern.
Uploaded file:               file_path (VARCHAR 255, NOT NULL) — private-disk path, randomized
                            storage name (see "File Security"). mime_type (VARCHAR 100, NOT
                            NULL), size_bytes (BIGINT UNSIGNED, NOT NULL).
Donor-declared evidence:     declared_amount_minor (BIGINT UNSIGNED, NULLABLE), declared_currency
                            (CHAR 3, NULLABLE), declared_transferred_at (DATETIME, NULLABLE) — the
                            donor's own claim about what/when they transferred, distinct from and
                            never assumed equal to payments.amount_minor/currency (see
                            "Manual Bank Transfer" for the reconciliation-at-review contract).
Review outcome:              reviewed_by_principal_id (FK principals.id, RESTRICT, NULLABLE),
                            reviewed_at (DATETIME, NULLABLE), review_outcome (VARCHAR 24,
                            NULLABLE) — APPROVED | REJECTED | AMOUNT_MISMATCH_HOLD (the third
                            value is `FINAL / LOCKED` per HD-IMP009-07: a declared/observed
                            transferred amount that does not exactly equal `payments.amount_minor`
                            MUST NOT be resolved as an ordinary APPROVED outcome — it is routed to
                            this distinct, escalated outcome, never silently approved or
                            auto-corrected; see "Manual Transfer" — "Amount mismatch"),
                            review_notes (VARCHAR 1000, NULLABLE).
timestamps.

Immutable vs mutable:       file_path, mime_type, size_bytes, declared_* fields are set at
                            submission and never mutated. A resubmission is ALWAYS a NEW,
                            additional row — `FINAL / LOCKED` per HD-IMP009-08: resubmission is
                            permitted while the owning Payment remains in an evidence-eligible
                            state (PENDING, no recorded review_outcome yet); a Payment already
                            terminal or already carrying a recorded review_outcome rejects any
                            further submission (see "Manual Transfer" — "Duplicate proof"). No
                            historical `manual_transfer_evidence` row is ever overwritten.
                            reviewed_by_principal_id/reviewed_at/review_outcome/review_notes are
                            set exactly once per row, at review time, and never mutated afterward
                            (mirrors Donation's own single-transition-per-field rule).

Unique: ulid. Indexes: payment_id, reviewed_by_principal_id.
```

### `manual_transfer_bank_accounts` (operational configuration, not a financial ledger)

```
id (BIGINT PK), ulid (CHAR 26, unique)
bank_name (VARCHAR 100, NOT NULL), account_number (VARCHAR 64, NOT NULL),
account_holder_name (VARCHAR 150, NOT NULL), currency (CHAR 3, NOT NULL)
is_active (BOOLEAN, NOT NULL, DEFAULT TRUE)
timestamps

This table is DISPLAY/ROUTING configuration only (which bank account a donor is instructed to
transfer to) — it is never a balance, never a Ledger account, and holds no secret (an account
number/holder name is not a credential). See "Configuration" for the secret/non-secret boundary.
```

### `payment_provider_credentials` (secret configuration — see "Configuration")

```
id (BIGINT PK)
provider (VARCHAR 32, UNIQUE, NOT NULL)
mode (VARCHAR 16, NOT NULL) — SANDBOX | PRODUCTION
encrypted_secret (TEXT, NOT NULL) — application-layer encrypted (Laravel's encrypter, itself
  backed by APP_KEY — no plaintext secret column, ever)
is_enabled (BOOLEAN, NOT NULL, DEFAULT FALSE)
timestamps

Never serialized through any read API/admin response (masked/omitted unconditionally — see
"Configuration").
```

## Data Ownership

```
Payment, payment_provider_events, manual_transfer_evidence,
  manual_transfer_bank_accounts, payment_provider_credentials:   owned entirely by IMP-009.
Donation:                     read-only consumption of IMP-008's existing table + its System-
                              Principal-gated transition surface. IMP-009 adds NO column to
                              `donations` (no `payment_status`, `payment_reference`, or similar —
                              confirmed absent from IMP-008's locked schema; see "Authority").
Campaign / Fund:               read-only, transitively via Donation.campaign_id. IMP-009 adds no
                              column to `campaigns`, `programs`, or `funds`.
Principal:                    read-only consumption of IMP-003's `principals` table; write-once
                              seeding of this IMP's own `system_principals`/`integration_principals`
                              catalog rows (see "System Principal").
```

## State Machines

### Canonical Payment state machine (provider-neutral)

A concrete provider status name (Tripay's `PAID`, Xendit's `SETTLED`, Stripe's `succeeded`) is
NEVER stored in `payments.status` or exposed to any non-adapter code — this is an explicit
architecture decision (task instruction §8: "Do NOT simply copy Tripay/Xendit/Stripe status
names"), not a Human Decision, because it is a technical translation-layer choice with no business
ambiguity: every provider's status vocabulary must be normalized before it reaches domain logic,
or Payment/Donation/future-Ledger code would need provider-specific branches throughout, which
directly contradicts the canonical posting chain's "Normalize / Validate" step
(FINANCIAL-POSTING-BOUNDARY.md).

```
                    +-----------+
   create -------->|  PENDING  |
                    +-----------+
                     |   |    |
      (provider      |   |    | (donor/admin cancels before
       requires      |   |    |  any provider outcome, or
       extra donor   |   |    |  Manual Transfer proof
       action, e.g.  v   |    |  rejected before submission
       3DS/OTP)  +-----------+ |  window closes)
                 |REQUIRES_  | |
                 | ACTION    | |
                 +-----------+ |
                     |    |    |
      (provider      |    |    v
       reports       |    | +-----------+
       success)      |    | | CANCELLED |
                     v    |  +-----------+
              +-----------+
              | SUCCEEDED |     (provider reports failure, OR
              +-----------+      Manual Transfer proof rejected
                     ^            after admin review)
                     |                  |
              +-----------+      +-----------+       +-----------+
              |  (from     |     |  FAILED   |       |  EXPIRED  |
              |  PENDING/  |     +-----------+       +-----------+
              |  REQUIRES_ |                     (PENDING or REQUIRES_ACTION with
              |  ACTION)   |                      no terminal outcome within the
              +-----------+                       Payment's own window — see
                                                    "Expiration")
```

```
PENDING          -> REQUIRES_ACTION   provider adapter reports the provider needs additional
                                       donor action (e.g. 3-D Secure challenge, OTP) before it can
                                       resolve. Stripe-relevant primarily (see "Stripe"); Tripay/
                                       Xendit/Manual Transfer adapters MAY skip this state entirely
                                       if the provider has no such intermediate step.
PENDING/
REQUIRES_ACTION -> SUCCEEDED          provider adapter normalizes a provider "paid/settled/
                                       succeeded" signal (webhook OR, where supported, an explicit
                                       status poll) into this transition. For Manual Transfer: an
                                       authorized admin APPROVES submitted evidence (see "Manual
                                       Bank Transfer"). Triggers the Donation Integration surface
                                       (see "Donation Integration" / "Financial Boundary").
PENDING/
REQUIRES_ACTION -> FAILED             provider adapter normalizes a provider "declined/failed"
                                       signal. For Manual Transfer: an authorized admin REJECTS
                                       submitted evidence. Triggers the Donation Integration
                                       surface with a failure outcome.
PENDING/
REQUIRES_ACTION -> EXPIRED            system-initiated (no terminal provider outcome arrived
                                       within the Payment's own configured window — see
                                       "Expiration"); never donor/admin-initiated directly.
PENDING/
REQUIRES_ACTION -> CANCELLED          donor/guest abandons the attempt before any provider outcome
                                       (e.g. explicitly cancels on the payment page), or an admin
                                       cancels an attempt for operational reasons, before any
                                       terminal provider outcome has been recorded.
SUCCEEDED, FAILED,
EXPIRED, CANCELLED -> (terminal)      no further transition, ever. A donor who wants to try again
                                       creates a NEW Payment row against the same (still-PENDING)
                                       Donation, now that this attempt's terminal state has freed
                                       HD-IMP009-01's one-ACTIVE-attempt slot (see "Idempotency" /
                                       "Concurrency") — never resurrects a terminal Payment row,
                                       mirroring IMP-008's own terminal-Donation-state immutability
                                       (BR-7) applied here by the same principle. No lifetime
                                       maximum attempt count applies (HD-IMP009-03).
```

All other transitions (e.g. SUCCEEDED -> anything, EXPIRED -> SUCCEEDED, any transition FROM a
terminal state) are rejected with a typed conflict exception, mirroring IMP-007/008's identical
pattern (AC-007-007, AC-008-010).

**Duplicate-event behavior**: a provider event that reports the SAME terminal outcome the Payment
already recorded (e.g. a second "paid" webhook after the Payment is already SUCCEEDED) is
accepted at the `payment_provider_events` layer (`processing_result = DUPLICATE`, recorded for
forensics) and produces NO further Payment/Donation state change — it is not an error, it is the
expected behavior of an at-least-once webhook delivery contract.

**Out-of-order-event behavior**: a provider event reporting an EARLIER-stage status than the
Payment's current status (e.g. a "pending" webhook arriving after a "paid" webhook already moved
the Payment to SUCCEEDED — genuinely possible with asynchronous provider delivery) is accepted at
the `payment_provider_events` layer but MUST NOT move `payments.status` backward. The canonical
state machine only ever advances toward a terminal state or moves PENDING <-> REQUIRES_ACTION; an
out-of-order "earlier" signal received after a terminal state is reached is recorded and ignored
for state-transition purposes (`processing_result = ACCEPTED`, but no Payment mutation occurs
beyond the event record itself).

### Per-provider status-normalization mapping (adapter responsibility — see "Provider Adapter Architecture")

```
Manual Bank Transfer (no provider callback — internal-only, admin-driven):
  AWAITING_PROOF (no evidence submitted yet)      -> canonical PENDING
  UNDER_REVIEW (evidence submitted, admin has not
    yet decided)                                  -> canonical PENDING (no REQUIRES_ACTION use —
                                                      there is no donor-facing "extra action" step
                                                      beyond the original proof submission)
  APPROVED (admin decision)                       -> canonical SUCCEEDED
  REJECTED (admin decision)                       -> canonical FAILED
  AMOUNT_MISMATCH_HOLD (admin decision,
    HD-IMP009-07, `FINAL / LOCKED`)                -> canonical PENDING, UNCHANGED — this outcome
                                                      is recorded at the `manual_transfer_evidence.
                                                      review_outcome` layer only (see "Manual
                                                      Transfer" — "Amount mismatch"); it does NOT
                                                      transition `payments.status`, since neither a
                                                      SUCCEEDED nor a FAILED canonical outcome is
                                                      accurate for a genuine-but-mismatched
                                                      transfer. The Payment remains PENDING,
                                                      escalated for later governed resolution, and
                                                      the owning Donation correspondingly remains
                                                      untouched (no premature SUCCEEDED/FAILED
                                                      transition is requested — see "Donation
                                                      Integration").
  (window closed, no evidence ever submitted, or
    submitted but never reviewed, or held at
    AMOUNT_MISMATCH_HOLD past the Payment's own
    expiry window)                                 -> canonical EXPIRED

Tripay (reference: Tripay's own status vocabulary as commonly documented — UNPAID/PAID/EXPIRED/
  FAILED/REFUND; adapter MUST verify the exact current vocabulary against Tripay's own API
  documentation at implementation time, not assume this list is exhaustive):
  UNPAID           -> canonical PENDING
  PAID             -> canonical SUCCEEDED
  EXPIRED          -> canonical EXPIRED
  FAILED           -> canonical FAILED
  REFUND            -> NOT mapped to any canonical Payment state by this specification — a refund
                       signal on an already-SUCCEEDED Payment is IMP-016's concern entirely (see
                       "Refund Boundary"); the adapter MUST record the raw event
                       (`payment_provider_events`) but MUST NOT transition `payments.status` in
                       response to it.

Xendit (adapter baseline is the PaymentRequest API — `FINAL / LOCKED`, HD-IMP009-05, NOT the
  Invoice API; the mapping below reflects the PaymentRequest API's own documented `status`
  vocabulary as commonly published at drafting time — the adapter MUST re-verify the exact current
  field/value set against Xendit's own authoritative documentation before implementation,
  per HD-IMP009-05's own explicit instruction, since Xendit may revise this vocabulary):
  PENDING                                          -> canonical PENDING (PaymentRequest created,
                                                       awaiting completion)
  REQUIRES_ACTION                                  -> canonical REQUIRES_ACTION (e.g. a channel
                                                       requiring redirect/OTP/3-D-Secure-equivalent
                                                       completion)
  SUCCEEDED                                        -> canonical SUCCEEDED
  FAILED                                           -> canonical FAILED
  EXPIRED                                          -> canonical EXPIRED
  CANCELED                                         -> canonical CANCELLED
  (the PaymentRequest API's status vocabulary maps far more directly onto this specification's own
    canonical state names than the older Invoice API's did — this is a consequence of HD-IMP009-05's
    choice, not a coincidence this specification relies on; the adapter still MUST implement this
    as an explicit, reviewable allow-list, never a bare pass-through of the provider's own string,
    since Xendit's exact value set remains subject to "External Verification" at implementation
    time)

Stripe (PaymentIntent status vocabulary):
  requires_payment_method,
  requires_confirmation,
  processing                                      -> canonical PENDING
  requires_action                                  -> canonical REQUIRES_ACTION (3-D Secure/OTP)
  succeeded                                        -> canonical SUCCEEDED
  canceled                                         -> canonical CANCELLED
  (Stripe PaymentIntents have no native "expired" status equivalent to Tripay/Xendit's own expiry
    field — EXPIRED is reached exclusively through IMP-009's OWN internal expiry sweep, per
    HD-IMP009-09's confirmed configurable-fallback mechanism, see "Expiration", never inferred
    from a Stripe-reported status)
```

## Provider Adapter Architecture

A single provider-neutral interface, implemented once per approved provider, isolated from core
Payment domain logic (the canonical state machine, Donation Integration, and audit code never
import a provider SDK or branch on `provider === 'tripay'` etc. — all provider-specific behavior
lives inside that provider's own adapter):

```
Adapter responsibilities (conceptual contract, not a code signature):

createTransaction(Payment $payment, Donation $donation): ProviderTransactionResult
  — calls the provider's own transaction/invoice/payment-intent creation API using
  payment.amount_minor/currency, returns a normalized result: provider_reference, channel
  (nullable), instructions_payload (adapter-normalized), provider-reported expires_at (nullable —
  see "Expiration"). MUST use the provider's own native request-idempotency mechanism when one
  exists (Stripe supports an `Idempotency-Key` request header on its own API; where a provider has
  no such mechanism, the adapter relies solely on this platform's own idempotency_key/duplicate-
  attempt prevention — see "Idempotency").

retrieveStatus(Payment $payment): ProviderStatusResult (OPTIONAL per provider)
  — an explicit status-poll fallback for a provider/scenario where webhook delivery cannot be
  assumed reliable (see "Shared Hosting" for the Scheduler+Cron-based fallback poll job). Returns
  the SAME normalized status vocabulary a webhook would produce — poll and webhook paths converge
  on one normalization function per adapter, never two.

verifyCallback(Request $rawRequest): VerifiedCallbackResult
  — provider-specific signature/authenticity verification (see "Webhook Security" for the shared
  pipeline every adapter's verifyCallback() feeds into). Returns a typed pass/fail result; never
  throws a generic exception a controller would need to interpret.

normalizeStatus(string $providerStatus): CanonicalPaymentStatus
  — the per-provider mapping table in "State Machines" above, implemented as an explicit,
  reviewable allow-list per adapter (never a generic string-transform/heuristic).

normalizeExpiration(ProviderTransactionResult|ProviderStatusResult $result): ?DateTimeImmutable
  — extracts a provider-reported expiry when present (Tripay/Xendit); returns null when absent
  (Stripe, Manual Transfer — see "Expiration").

providerReference(...): string — the stable identifier used for `payments.provider_reference` and
  for correlating an inbound webhook to its Payment row.

paymentUrlOrInstructions(...): array — feeds `instructions_payload` (see "Domain Model" for its
  bounded, normalized shape).

paymentMethodMetadata(...): array — feeds `payments.channel` plus any adapter-specific display
  metadata folded into `instructions_payload`.

normalizeError(mixed $providerError): PaymentAdapterError — a typed, non-sensitive error
  normalization (see "Error Handling" / "Failure Semantics") — a raw provider exception/response
  body is NEVER surfaced to the donor-facing API response; it is recorded (encrypted) in
  `payment_provider_events` only.

retryBehavior: creating a NEW Payment Attempt is ALWAYS a new `createTransaction()` call producing
  a NEW provider transaction/invoice/payment-intent — an adapter never "resumes" a terminal
  provider-side transaction by re-issuing the SAME provider_reference (mirrors "posted financial
  records are immutable / corrections via a new record" applied here to a terminal Payment
  Attempt).

providerIdempotency: where the provider supports its own native idempotency key on the create-
  transaction call, the adapter MUST pass one derived deterministically from
  `payments.idempotency_key` (e.g. reusing that exact value or a stable, documented derivation) —
  never a fresh random value per retry of the SAME internal request, which would defeat the
  provider's own dedupe.
```

Each of the four adapters below is isolated behind this interface; no adapter's internal detail
(a Tripay signature algorithm, a Stripe webhook secret format) leaks into the canonical Payment
domain model, the Donation Integration surface, or the audit event schema.

## Manual Transfer

The one provider with NO webhook/callback at all — resolution is entirely human/admin-driven.

```
Bank account configuration:   `manual_transfer_bank_accounts` (see "Domain Model") — an admin-
                              managed, non-secret catalog of destination accounts. A Payment does
                              not store which bank_account_id was shown to the donor as a separate
                              FK in v1 (no authoritative requirement evidences a need for
                              per-Payment bank-account attribution beyond what
                              `manual_transfer_evidence` and admin review already capture); if a
                              future requirement needs it, that is a schema addition via change
                              control, not invented here.

Transfer instructions:        Rendered from the active `manual_transfer_bank_accounts` row(s)
                              matching the Payment's currency, via `instructions_payload` (see
                              "Domain Model").

Unique payment/reference
  code:                       NOT invented by this specification as a mandatory mechanism — no
                              authoritative source requires a per-Payment unique transfer
                              reference code (e.g. appending a random suffix to the transferred
                              amount for automated matching), and Manual Transfer is explicitly
                              NOT automated reconciliation (Moota is IMP-017's own, separate
                              concern — see "Reconciliation Boundary"). If a future automated-
                              matching capability is authorized, it is IMP-017's to design against
                              Moota, not a mechanism this spec invents for a manual, human-verified
                              flow.

Proof-of-transfer upload:     MANDATORY — `FINAL / LOCKED`, HD-IMP009-06. A Payment cannot be
                              manually APPROVED through the verification flow without at least one
                              `manual_transfer_evidence` row present. The Payment-creation-to-
                              verification flow requires at least one such row to exist before an
                              admin's APPROVE action is even offered; an admin holding
                              `payment.manual_transfer.verify` may still REJECT with zero evidence
                              present (e.g. an abandoned Payment with no evidence ever submitted,
                              swept toward FAILED/EXPIRED per "Expiration" rather than reviewed).

Manual verification /
  approval authority:         An authorized admin/staff actor holding `payment.manual_transfer.verify`
                              in ORGANIZATION scope AND the `financial_approver` Business Authority
                              Type (the closest fit in the CLOSED Authority Type taxonomy —
                              BUSINESS-AUTHORITY-MODEL.md; no new `payment_verifier`/
                              `payment_approver` Authority Type is invented — see "Authorization").
                              Reviews the required `manual_transfer_evidence` row(s) (HD-IMP009-06)
                              plus out-of-band bank statement information, then records
                              review_outcome = APPROVED, REJECTED, or AMOUNT_MISMATCH_HOLD (see
                              "Amount mismatch" below, HD-IMP009-07).

Rejection:                    Payment -> FAILED, failure_reason = 'MANUAL_REJECTED',
                              review_notes captured. Donor/guest is not automatically offered a
                              retry path beyond creating a new Payment Attempt against the still-
                              PENDING Donation, once this attempt's FAILED terminal state has freed
                              the HD-IMP009-01 one-ACTIVE-attempt slot; no lifetime attempt limit
                              applies (HD-IMP009-03).

Expiration:                   See "Expiration" — Manual Transfer has no provider-supplied expiry;
                              per HD-IMP009-09, the internal fallback config value is REQUIRED for
                              this provider specifically (there is no other signal that would ever
                              move an unreviewed Manual Transfer Payment to EXPIRED) — while that
                              configuration is unset, an unreviewed Manual Transfer Payment remains
                              PENDING indefinitely, exactly mirroring HD-IMP008-04's own posture.

Duplicate proof:              Resubmission IS PERMITTED — `FINAL / LOCKED`, HD-IMP009-08 — while
                              the owning Payment remains in an evidence-eligible state (PENDING,
                              no recorded review_outcome yet). `manual_transfer_evidence` is an
                              append-only table: a resubmission is always a NEW row, NEVER an
                              UPDATE of a prior row's file_path/declared_*/reviewed_* fields (all
                              immutable per "Domain Model"). Once the Payment reaches a terminal
                              status, OR already carries a recorded review_outcome
                              (APPROVED/REJECTED/AMOUNT_MISMATCH_HOLD), further evidence submission
                              is rejected — there is no "reopen review" path for a Payment already
                              decided.

Amount mismatch (over/under-
  payment):                   `FINAL / LOCKED`, HD-IMP009-07. When a reviewing admin observes that
                              the donor-declared or bank-statement-confirmed transferred amount
                              does NOT exactly equal `payments.amount_minor`, the admin records
                              review_outcome = AMOUNT_MISMATCH_HOLD — a THIRD, distinct outcome
                              from ordinary APPROVED/REJECTED. This outcome does NOT drive the
                              Payment to SUCCEEDED (no automatic PAID/successful Donation
                              treatment for a mismatched amount) and does NOT drive it to FAILED
                              either (a genuine transfer clearly occurred, just not for the exact
                              expected amount — treating it as an ordinary rejection would be
                              inaccurate). The Payment instead remains in a held, escalated state
                              pending a LATER, governed resolution this specification does not
                              itself define — no refund, credit, balance, allocation, or
                              donation-amount mutation is invented here (HD-IMP009-07 explicitly
                              forbids it); resolving an AMOUNT_MISMATCH_HOLD case is out of
                              IMP-009's own scope, deferred to whichever later governed domain
                              (e.g. a future Refund/Reconciliation-adjacent mechanism) is
                              authorized to handle it. `payments.amount_minor` and
                              `donations.amount_minor` are NEVER mutated to match the actual
                              transferred amount — both remain immutable exactly as their own
                              domain models require. The mismatch itself is recorded via
                              `manual_transfer_evidence.declared_amount_minor` (the donor's claim)
                              versus `payments.amount_minor` (the authoritative expected amount)
                              for the later governed resolution to consult.

Security of uploaded
  evidence:                    See "File Security".

Audit trail:                   `manual_transfer.evidence_submitted`, `manual_transfer.approved`,
                              `manual_transfer.rejected` — see "Audit".
```

Manual Transfer MUST NOT silently become automatic reconciliation — no code path in this
specification matches an incoming Moota bank-mutation feed to a Manual Transfer Payment
automatically; that capability, if ever authorized, belongs entirely to IMP-017 (see
"Reconciliation Boundary").

## Tripay

```
Merchant configuration:       merchant_code + a private API key, stored exclusively in
                              `payment_provider_credentials` (provider='tripay') — see
                              "Configuration". Never in source, `.env` committed to VCS, logs, or
                              any audit payload.

Signature generation/
  verification:                Tripay's create-transaction request is itself HMAC-signed using the
                              merchant's private key (outbound); Tripay's callback is verified via
                              its documented `X-Callback-Signature` header, an HMAC over the raw
                              callback body using the SAME private key (inbound). The adapter's
                              `verifyCallback()` MUST perform this HMAC comparison using a
                              constant-time comparison function — never a plain `===` string
                              compare (timing-attack resistance, standard webhook-verification
                              practice, consistent with SECURITY-INVARIANTS.md's "never trusted
                              without verification").

Transaction creation:         `createTransaction()` calls Tripay's transaction-creation endpoint
                              with amount_minor (converted to Tripay's expected unit — Tripay's
                              API operates in whole IDR, not minor units with decimal digits, since
                              IDR has 0 minor-unit digits per CurrencyMinorUnits — the adapter MUST
                              verify this conversion is a no-op for IDR and MUST reject/refuse
                              constructing a Tripay transaction for any non-IDR currency the
                              provider does not support, per "Money / Currency").

Callback verification:        Per "Webhook Security" pipeline, provider-specific step: HMAC
                              signature check above.

Provider reference:           Tripay's own `reference` field -> `payments.provider_reference`.

Payment method/channel:       Tripay's `payment_method`/`payment_name` -> `payments.channel`.

Expiration:                   Tripay returns its own `expired_time` on transaction creation ->
                              `payments.expires_at` directly (see "Expiration").

Status normalization:         See "State Machines" per-provider mapping.

Duplicate callback:            Tripay may redeliver the same callback; `payment_provider_events`
                              dedupe applies (see "Idempotency" / "Webhook Security") — Tripay's
                              callback has no independent event ID distinct from its transaction
                              `reference` + the callback's own `merchant_ref`, so the adapter's
                              dedupe key is (provider='tripay', provider_event_id = a stable
                              derivation such as `reference + ':' + status`, documented at
                              implementation time) rather than a provider-native event UUID.

Invalid signature:             `processing_result = REJECTED_INVALID_SIGNATURE`, HTTP 4xx response
                              to Tripay per its own documented contract, NO Payment state change,
                              audit event `webhook.verification_failed` (see "Audit").

Amount verification:           the callback's reported amount MUST equal `payments.amount_minor`
                              (after unit conversion) before any state transition is accepted;
                              mismatch -> `processing_result = REJECTED_AMOUNT_MISMATCH`, no state
                              change, audit event.

Donation/Payment reference
  verification:                 the callback's `merchant_ref`/`reference` MUST resolve to exactly
                              one existing `payments` row via `provider_reference`; no match ->
                              `processing_result = REJECTED_UNKNOWN_REFERENCE`, no state change,
                              audit event — never silently create a Payment row from a webhook.

Provider outage/error:         `createTransaction()` failure (network/5xx) does not create a
                              `payments` row in a state that implies a provider transaction
                              exists — see "Error Handling" / "Concurrency" for the exact
                              transactional boundary.
```

## Xendit

```
Credential configuration:     Xendit secret API key in `payment_provider_credentials`
                              (provider='xendit'), SANDBOX/PRODUCTION mode flag — see
                              "Configuration".

Provider transaction
  identifier:                  Xendit's PaymentRequest `id` -> `payments.provider_reference`
                              (`FINAL / LOCKED` adapter baseline, HD-IMP009-05 — not an Invoice
                              `id`).

Payment creation:              `createTransaction()` calls Xendit's PaymentRequest creation
                              endpoint. The exact request/response field shape MUST be verified
                              against Xendit's own current authoritative documentation before
                              implementation (HD-IMP009-05, "External Verification") — this
                              specification does not freeze a field-level contract that belongs to
                              Xendit's own API surface.

Callback/webhook
  verification:                 Xendit signs webhooks via an `x-callback-token` header (or its
                              PaymentRequest-API-current equivalent — verified at implementation
                              time) compared against a configured callback verification token (a
                              second secret, distinct from the API key, stored alongside it in
                              `payment_provider_credentials` or a dedicated column — implementation
                              detail, but MUST be a secret, never a request-supplied value trusted
                              at face value). Constant-time comparison, same requirement as Tripay.

Status mapping:                See "State Machines" per-provider mapping — the PaymentRequest
                              API's own `status` vocabulary (PENDING/REQUIRES_ACTION/SUCCEEDED/
                              FAILED/EXPIRED/CANCELED), re-verified against Xendit's current
                              documentation before implementation.

Amount/currency
  verification:                 same contract as Tripay: webhook-reported amount/currency MUST
                              equal `payments.amount_minor`/`currency` before any transition;
                              mismatch -> `REJECTED_AMOUNT_MISMATCH`/`REJECTED_CURRENCY_MISMATCH`.

Expiration:                    the PaymentRequest API's own expiry field (verified against current
                              documentation at implementation time — the exact field name may
                              differ from the Invoice API's `expiry_date`) -> `payments.expires_at`
                              directly, per the same "provider-supplied expiry preserved/
                              normalized when present" contract HD-IMP009-09 confirms.

Duplicate webhook:              Xendit's webhook payload carries its own event/notification `id` —
                              used as `provider_event_id` for the `payment_provider_events`
                              composite-unique dedupe (see "Idempotency").

Invalid webhook:                same rejection contract as Tripay (`REJECTED_INVALID_SIGNATURE`,
                              no state change, audit event, non-2xx or documented-safe response
                              per Xendit's own retry contract).

Provider error:                 same contract as Tripay's "Provider outage/error."

Sensitive payload handling:      the callback token and API key are never logged, never included
                              in `instructions_payload`, never returned in any API response — see
                              "Configuration" / "Security Requirements."
```

The Xendit adapter's baseline is the **PaymentRequest API** — `FINAL / LOCKED`, HD-IMP009-05 (not
the hosted Invoice API this document's initial draft had recommended as a default). This
specification's architecture (provider-neutral canonical states, adapter-isolated provider-
specific detail, `channel` as a free-form display field) required no redesign to accommodate this
choice — only the adapter's internal `createTransaction()`/`normalizeStatus()` implementation
detail and the "State Machines" per-provider mapping table (updated above) differ from what an
Invoice-API-targeted adapter would have looked like.

## Stripe

```
Secret/public configuration
  boundary:                     Stripe secret key in `payment_provider_credentials`
                              (provider='stripe'); a Stripe PUBLISHABLE key (not secret) may be
                              exposed to the frontend where Stripe.js/Elements requires it — this
                              is Stripe's own documented public/secret key split, not a boundary
                              this specification invents; the SECRET key is never exposed via any
                              API response or frontend-reachable configuration endpoint.

Webhook signature
  verification:                 Stripe's own documented `Stripe-Signature` header verification
                              (HMAC over the raw request body + timestamp, using a webhook signing
                              secret distinct from the API secret key, also stored in
                              `payment_provider_credentials`). MUST include Stripe's own timestamp-
                              tolerance check (Stripe's official verification library provides
                              this) as the "timestamp/tolerance if supported" replay-protection
                              layer (see "Webhook Security").

Provider identifier:            Stripe PaymentIntent `id` -> `payments.provider_reference`.

Payment creation:               `createTransaction()` creates a Stripe PaymentIntent for
                              amount_minor/currency, using Stripe's OWN native `Idempotency-Key`
                              request header (see "Provider Adapter Architecture" —
                              providerIdempotency) derived from `payments.idempotency_key`.

Status normalization:           See "State Machines" per-provider mapping.

Currency/amount
  verification:                  the webhook event's PaymentIntent amount/currency MUST equal
                              `payments.amount_minor`/`currency`; mismatch -> rejection, same
                              contract as the other providers.

Duplicate webhook:               Stripe Event `id` -> `provider_event_id`, same composite-unique
                              dedupe contract as Xendit.

Out-of-order webhook:            Stripe explicitly documents that webhook delivery order is not
                              guaranteed — the canonical "out-of-order-event behavior" rule under
                              "State Machines" applies without adapter-specific exception.

Expiration/cancellation
  where applicable:               Stripe PaymentIntents do not natively "expire" the way
                              Tripay/Xendit's own expiry fields do (see "State Machines" note) —
                              EXPIRED is reached only via IMP-009's own internal fallback sweep,
                              per HD-IMP009-09's confirmed configurable-fallback mechanism. A
                              Stripe-side `canceled` PaymentIntent status maps to canonical
                              CANCELLED, never EXPIRED.

Provider idempotency:            see "Payment creation" above — this is Stripe's one native,
                              first-class idempotency mechanism among the four providers; the
                              adapter MUST use it.

No card data storage:            the platform NEVER collects, transmits through its own backend,
                              or stores raw card PAN/CVV/expiry — Stripe Elements/Stripe.js (or
                              an equivalent Stripe-hosted, PCI-SAQ-A-eligible integration pattern)
                              tokenizes card data directly in the donor's browser to Stripe; this
                              platform's backend only ever sees a Stripe-generated token/
                              PaymentIntent client_secret, never raw card data. This specification
                              treats "no card data storage" as a hard architectural constraint
                              (per the task's own explicit instruction, restated here as binding on
                              the eventual implementation, not merely aspirational).
```

## Webhook / Callback Security

This is security-critical (SECURITY-INVARIANTS.md: "A webhook/callback is never trusted without
verification and replay protection"). Canonical processing pipeline, identical shape for
Tripay/Xendit/Stripe (Manual Transfer has no webhook — see "Manual Transfer"):

```
1. Receive         raw HTTP request at a provider-specific route (see "Routes / API Boundary").
                    CSRF-exempt (a provider cannot supply a platform CSRF token) — this is the
                    one explicitly authorized CSRF exemption in this specification, scoped to
                    exactly the three provider webhook routes, never generalized to any other
                    route.
2. Identify        provider resolved from the route itself (one route per provider — never a
   provider          single shared "generic webhook" endpoint that infers the provider from
                    payload shape, which would weaken signature-verification specificity).
3. Authenticate/    the provider adapter's verifyCallback() (see per-provider sections) —
   verify            constant-time HMAC/signature comparison against a secret from
                    `payment_provider_credentials`, never against a request-supplied value.
                    FAILURE HERE STOPS THE PIPELINE: processing_result =
                    REJECTED_INVALID_SIGNATURE, event recorded, no further step runs, no Payment/
                    Donation state changes, a generic non-revealing error response returned.
4. Validate         payload deserialized/validated against the adapter's expected shape;
   payload           malformed payload -> processing_result = REJECTED_MALFORMED, same stop-here
                    contract as step 3.
5. Resolve          resolve `payments` row via provider_reference (see per-provider "Donation/
   Payment            Payment reference verification"); no match -> processing_result =
                    REJECTED_UNKNOWN_REFERENCE, event recorded with payment_id NULL, pipeline
                    stops — NEVER create a Payment row from an inbound webhook.
6. Validate         amount/currency comparison against the resolved Payment's own
   amount/currency   amount_minor/currency; mismatch -> processing_result =
                    REJECTED_AMOUNT_MISMATCH or REJECTED_CURRENCY_MISMATCH, pipeline stops, no
                    state change (this is a security control, not merely a data-quality one — a
                    mismatched amount webhook could otherwise be used to mark an underpaid
                    Payment SUCCEEDED).
7. Idempotency/     (provider, provider_event_id) composite-unique check against
   duplicate          `payment_provider_events` (see "Idempotency"). A duplicate of an ALREADY-
   detection          processed event -> processing_result = DUPLICATE, recorded, no further
                    processing (this is not a rejection — it is the expected outcome of at-least-
                    once delivery).
8. Lock applicable  the resolved Payment row (and, when the outcome is terminal, the owning
   aggregate          Donation row) are locked within a single database transaction (SELECT ... FOR
                    UPDATE or the Laravel-equivalent locking read) BEFORE any state is read for
                    the transition decision — see "Concurrency" for lock ordering.
9. Validate         the canonical state machine's transition rules (see "State Machines") are
   transition          applied under that lock; an invalid transition (e.g. webhook reports
                    "succeeded" for an already-EXPIRED Payment) is handled per "State Machines"'
                    duplicate/out-of-order rules, never forced through.
10. Persist         `payments.status` (+ companion *_at) updated within the SAME transaction as
    transition          step 8's lock.
11. Append audit    the relevant `payment.*` audit event appended in the SAME transaction (see
                    "Audit" for criticality classification per event).
12. Emit authorized  a terminal SUCCEEDED/FAILED outcome invokes Donation's existing System-
    downstream          Principal-gated transition surface (IMP-008) — see "Donation Integration" /
    consequence/event   "Financial Boundary" — within the SAME transaction as steps 8-11 (see
                    "Concurrency"), plus a `payment.succeeded`/`payment.failed` domain event a
                    future IMP-011/024 subscriber may consume (never itself posting to Ledger or
                    sending a notification — see "Out of Scope").
13. Acknowledge     the provider's own documented success response (Tripay: a specific JSON body;
    provider           Xendit/Stripe: HTTP 200) is returned ONLY after the transaction in steps
                    8-12 commits — never acknowledged before the transaction is durable, which
                    would risk an unprocessed webhook being incorrectly treated as delivered if
                    the process crashed between acknowledgment and commit.
```

```
Signature verification:      see per-provider sections; constant-time comparison mandatory.
Replay protection:            (a) the (provider, provider_event_id) composite-unique constraint
                              (step 7) is the primary replay defense; (b) Stripe's own timestamp-
                              tolerance check (see "Stripe") adds a second, provider-native layer
                              where available; (c) an event with NO provider-native event ID
                              (Tripay) relies on the adapter's documented stable derivation (see
                              "Tripay") as its replay-detection key — this is a narrower defense
                              than a true provider-issued event ID, acknowledged as such, not
                              silently assumed equivalent.
Provider event ID uniqueness: see "Domain Model" `payment_provider_events` unique constraint.
Raw payload retention
  policy boundary:              retained (encrypted) indefinitely by this specification's own
                              scope — a future, separately authorized retention/purge policy
                              (mirroring Q17's pattern for other retained records) governs when it
                              may be deleted; this specification does not invent a retention
                              duration.
Timestamp/tolerance:           see "Stripe" (the one provider with a documented, verifiable
                              tolerance mechanism among the four).
Duplicate callbacks:           see step 7/12 above and "State Machines" duplicate-event rule.
Out-of-order callbacks:        see "State Machines" out-of-order-event rule.
Unknown payment references:    see step 5.
Amount mismatch:               see step 6.
Currency mismatch:             see step 6.
Invalid signature:             see step 3.
Malformed payload:              see step 4.
Provider retries:               handled transparently by steps 7 (dedupe) and 13 (idempotent
                              acknowledgment) — a provider's own retry-on-non-2xx behavior is
                              respected: any REJECTED_* outcome that is NOT a permanent business
                              rejection (e.g. a transient internal error before step 3 even runs)
                              returns a 5xx so the provider retries; a REJECTED_INVALID_SIGNATURE/
                              REJECTED_UNKNOWN_REFERENCE/REJECTED_AMOUNT_MISMATCH/
                              REJECTED_MALFORMED is a permanent rejection and returns a
                              provider-appropriate non-retry status per that provider's own
                              documented contract.
```

No sensitive diagnostic information (raw provider error text, internal exception messages, stack
traces, secret/key fragments) is ever returned in any webhook response body — every response is a
minimal, generic acknowledgment or rejection, mirroring "Error Handling."

## Idempotency

**Payment (Attempt) creation — client-provided idempotency key, MANDATORY**, following the exact
same contract shape IMP-008 established for Donation creation (HD-IMP008-05A), deliberately NOT
reusing `donations.idempotency_key` (see "Domain Model" rationale):

```
INV-1  Two Payment-creation requests representing the SAME donor intent to attempt payment (e.g.
       a network retry, a double button-click) MUST NOT result in two independent provider
       transactions/invoices/PaymentIntents both capable of independently reaching SUCCEEDED.

Transport:        HTTP header `Idempotency-Key` on the Payment-creation request, mirroring
                   Donation's own convention (IMP-008-donation.md:805-809) and Stripe's own
                   idiom.
Format:            Caller-generated opaque printable-ASCII string, 1-128 characters, not
                   interpreted server-side beyond that bound.
Presence:          REQUIRED. A request without this header is rejected before any `payments` row
                   is created and before any provider API call is made.
Scope:             `payments.idempotency_key`'s own database-level UNIQUE constraint, exactly
                   mirroring Donation's scope mechanism.
Replay semantics:  identical key + matching payload (donation_id, provider, amount_minor,
                   currency) -> return the EXISTING Payment row, no second row created, no second
                   provider API call made. Identical key + differing payload -> typed conflict
                   exception (409-style), nothing created.
Enforcement:       DB-level UNIQUE constraint as the deterministic backstop; the application wraps
                   Payment-row creation AND the provider adapter's createTransaction() call in a
                   single logical operation such that a caught unique-constraint violation is
                   handled by re-fetching and comparing per the replay semantics above — never a
                   "check-then-insert-then-call-provider" race.
```

**Provider-side/gateway idempotency**: see "Provider Adapter Architecture" (`providerIdempotency`)
— used where the provider natively supports it (Stripe; verify at implementation time whether
Tripay/Xendit offer an equivalent create-transaction dedupe key), as a SECOND, provider-native
layer on top of this platform's own key, never a substitute for it (a provider without native
support relies solely on this platform's own key + HD-IMP009-01's one-ACTIVE-attempt-at-a-time
invariant).

**Webhook/callback idempotency**: see "Webhook Security" step 7 and the `payment_provider_events`
composite-unique constraint — a DIFFERENT idempotency concept from Payment-creation idempotency
(this one dedupes INBOUND provider notifications, not outbound creation requests), deliberately
modeled on IMP-004's own `source_domain`/`source_event_id`/`event_type` pattern rather than
reusing the audit table itself for business-state dedupe (see "Domain Model").

```
Same key + same payload:        legitimate replay — see "Replay semantics" above.
Same key + different payload:    typed conflict, rejected.
Guest behavior:                  identical contract — the Idempotency-Key header is required
                                 regardless of guest/authenticated donor status, mirroring
                                 Donation's own guest-inclusive requirement.
Authenticated behavior:           identical contract; donor_principal_id is never derived from the
                                 idempotency key itself, only from the acting Principal (same
                                 anti-spoofing rule as Donation creation).
Callback idempotency:             see "Webhook Security" step 7 (a wholly separate mechanism from
                                 this section's client-key contract).
```

## Concurrency

```
Payment (Attempt) creation:    a Donation-row lock (SELECT ... FOR UPDATE) is acquired FIRST,
                                before evaluating whether an ACTIVE Payment Attempt already exists
                                against it — `FINAL / LOCKED`, HD-IMP009-01: at most ONE row per
                                donation_id may be ACTIVE (PENDING/REQUIRES_ACTION) at a time,
                                enforced BOTH by this locked pre-check AND, as HD-IMP009-01
                                explicitly requires, at the DB level via the `active_slot`
                                generated column + `UNIQUE(donation_id, active_slot)` constraint
                                (see "Domain Model" / "Database Impact") — the lock prevents the
                                race a bare unique constraint alone would still let through (two
                                concurrent requests both observing "no active attempt exists" a
                                moment before either commits); the unique constraint is the
                                deterministic backstop if the lock discipline is ever
                                circumvented. This fixed lock ORDER (Donation, then Payment) is
                                mandatory throughout this specification's every multi-row
                                operation, to prevent deadlock between a concurrent
                                webhook-processing transaction (which also locks Donation-then-
                                Payment, per "Webhook Security" step 8) and a concurrent creation
                                request.
Payment status transition
  (webhook/poll/manual review): per "Webhook Security" step 8 — Payment row locked, and (only
                                when the outcome is terminal) the owning Donation row locked in
                                the SAME transaction, Donation-then-Payment lock order preserved
                                even though Payment is the row being primarily mutated (the
                                Donation lock is acquired first regardless of which row's mutation
                                "matters more" for this operation, to keep the global lock order
                                consistent system-wide).
Two identical callbacks:        the second transaction blocks on the Payment-row lock until the
                                first commits, then observes the already-processed
                                provider_event_id (step 7 dedupe) and takes the DUPLICATE path —
                                never a lost-update race.
Callback vs status polling:     both paths converge on the SAME locked-transition code path (see
                                "Provider Adapter Architecture" — retrieveStatus() normalizes to
                                the identical vocabulary a webhook would); whichever acquires the
                                lock first proceeds, the other observes the resulting state and
                                takes the appropriate duplicate/no-op path.
Callback vs expiration:         the expiry sweep job (see "Expiration") acquires the SAME
                                Donation-then-Payment lock order before moving a Payment to
                                EXPIRED; a webhook that arrives and acquires the lock first wins
                                (Payment reaches its real terminal state, sweep's later attempt on
                                that now-terminal row is a no-op per "State Machines"); a sweep
                                that acquires the lock first wins (Payment -> EXPIRED; a
                                subsequently-arriving "success" webhook is handled per
                                HD-IMP009-02 — the fact is recorded, the Donation is left
                                untouched, the case is flagged for controlled exception/manual
                                review; see "Failure Semantics").
Payment retry vs late success:   HD-IMP009-01 makes this race structurally impossible in the
                                normal case: a new Payment Attempt cannot be CREATED while an
                                older one is still ACTIVE (the `active_slot` unique constraint
                                blocks it). A "late success" therefore only ever arrives for an
                                attempt that is either still the sole ACTIVE attempt (ordinary
                                path, handled by "Callback vs expiration" above) or one that has
                                ALREADY reached a terminal state before a newer attempt was
                                created (HD-IMP009-02's late-outcome case, not a live race against
                                a concurrently-active sibling).
Manual verification vs
  expiration:                    an admin's APPROVE/REJECT/AMOUNT_MISMATCH_HOLD decision (see
                                "Manual Transfer") acquires the same Donation-then-Payment lock
                                order; if the expiry sweep already moved the Payment to EXPIRED
                                before the admin's decision commits, the admin's action is
                                rejected as an invalid transition (see "State Machines") and the
                                admin UI must surface this as a stale-state conflict, not silently
                                overwrite EXPIRED.
Duplicate payment creation
  request:                       see "Idempotency" — the idempotency-key UNIQUE constraint is the
                                primary defense for a request REPLAY; the Donation-row lock
                                acquired during creation (above), combined with the
                                `UNIQUE(donation_id, active_slot)` constraint, is the primary
                                defense for a DIFFERENTLY-keyed concurrent creation request racing
                                against HD-IMP009-01's one-ACTIVE-attempt invariant — both
                                requests cannot simultaneously observe "no active attempt exists"
                                and both succeed; the loser observes the now-active sibling and is
                                rejected with a typed conflict.
Donation expiration vs
  Payment success:                Donation's OWN sweep (IMP-008, entirely separate job/config from
                                Payment's own expiry sweep) acquires ITS OWN Donation-row lock
                                independently. A genuine race between Donation's sweep and a
                                Payment success reaching the Donation Integration surface (see
                                "Donation Integration") is resolved by ordinary row-level locking:
                                whichever transaction commits first wins; the loser observes the
                                Donation already in a terminal state and is handled per BR-7
                                (IMP-008) / HD-IMP009-02 (the Payment fact is still recorded, no
                                forced Donation transition, flagged for review) as applicable.
```

All of the above requires REAL MySQL row-level locking/transaction-isolation semantics — SQLite is
explicitly insufficient for verifying this behavior, mirroring IMP-007/008's own established
testing precedent (see "Testing Requirements").

## Expiration

Donation expiration (IMP-008, `donations.expired_at`/`pending_expiry_minutes`) and Payment
expiration (this specification's own `payments.expires_at`) are two INDEPENDENT clocks — this is
`FINAL/LOCKED` by IMP-008 itself (HD-IMP008-04, IMP-008-donation.md:463-468) and is restated here
as a hard boundary IMP-009 may not weaken:

```
Payment expiration source:     provider-supplied (Tripay `expired_time`, Xendit's PaymentRequest
                                expiry field) is authoritative when present -> `payments.expires_at`
                                set directly from the provider's own value at creation time —
                                `FINAL / LOCKED`, HD-IMP009-09 ("preserve/normalize" the
                                provider-supplied value).
Internal fallback expiration:   REQUIRED for Manual Transfer (no provider signal exists at all) and
                                for any provider/scenario where no provider-supplied expiry is
                                returned (Stripe PaymentIntents — see "Stripe"). `FINAL / LOCKED`,
                                HD-IMP009-09: configurable, no hard-coded business duration in the
                                domain contract — mirrors IMP-008's own HD-IMP008-04 pattern
                                exactly: a single, nullable configuration value (e.g.
                                `config('payment.pending_expiry_minutes')`, default `null`) gates
                                the sweep — while unset, no Payment of that kind/provider is ever
                                swept to EXPIRED. No numeric default is asserted by this
                                specification; deployment MUST supply valid configuration before
                                the fallback sweep can act on affected flows/providers.
Late callback after Payment
  expiration:                    a provider webhook reporting SUCCEEDED/FAILED for an
                                ALREADY-EXPIRED Payment is an invalid transition per "State
                                Machines" (EXPIRED is terminal) — this is the SAME shape of problem
                                HD-IMP009-02 resolves at the Donation layer, applied here one layer
                                down (a late success at the PAYMENT layer, arriving after
                                Payment-level expiry, rather than at the DONATION layer, arriving
                                after Donation-level expiry): the fact is still recorded
                                (`payment_provider_events`, `processing_result = ACCEPTED` but no
                                state mutation per "State Machines" duplicate/terminal rules), no
                                forced reopening of the EXPIRED Payment occurs, and the case is
                                flagged consistently with HD-IMP009-02's controlled-exception
                                posture.
Late success after Donation
  expiration:                    see HD-IMP009-02 directly (§"Human Decisions (Resolved)",
                                §"Donation Integration") — this IS that scenario.
Manual transfer expiration:      see "Manual Transfer" — governed by the same internal-fallback
                                config value (HD-IMP009-09); while unset, an unreviewed Manual
                                Transfer Payment never auto-expires (an admin must still act, or it
                                remains PENDING indefinitely — the same "no invented default"
                                posture IMP-008 took for Donation).
Retry after expiration:          creating a NEW Payment Attempt against a Donation that is STILL
                                PENDING (Donation itself has not expired) after a PRIOR Payment
                                Attempt reached EXPIRED is always permitted, now that the prior
                                attempt's terminal state has freed HD-IMP009-01's one-ACTIVE-
                                attempt slot; no lifetime attempt limit applies (HD-IMP009-03).
                                Payment-level expiration never terminates the Donation itself; only
                                Donation's OWN sweep (IMP-008) can do that.
```

The Payment expiry sweep job (Laravel Scheduler + Cron, DB queue — see "Shared Hosting") runs
under a **System Principal** (see "System Principal"), acquiring the Donation-then-Payment lock
order per "Concurrency" for each candidate row it processes.

## Money / Currency

```
Locked platform baseline:      IDR base + limited multi-currency (Q20, HUMAN-DECISION-REGISTER.md,
                                `FINAL/LOCKED`) — not re-litigated here.
amount_minor / currency:        every `payments.amount_minor`/`currency` value is constructed via
                                Money::ofMinorUnits()/CurrencyMinorUnits::digitsFor() (IMP-007),
                                copied from and validated EQUAL to the owning Donation's own
                                amount_minor/currency at Payment-creation time (BR-2 below) — never
                                independently entered by a donor at the Payment-creation step (the
                                amount is Donation's, not re-negotiable at payment time).
Provider supported currency
  validation:                    each adapter MUST validate, before calling createTransaction(),
                                that the requested currency is one the provider actually supports
                                (Tripay: IDR-only per its own platform scope; Xendit/Stripe:
                                provider-documented supported-currency lists, verified at
                                implementation time, not assumed). A Donation in a currency a
                                chosen provider does not support is a typed rejection at Payment-
                                creation time (see "Error Handling"), never a silent currency
                                substitution.
Provider amount comparison:      see "Webhook Security" step 6 — exact-equality comparison against
                                `payments.amount_minor` in the SAME minor-unit representation
                                (adapter-specific unit-conversion, e.g. Tripay's whole-IDR API, is
                                normalized on the way IN and OUT of the adapter, never left as an
                                ambiguous comparison across differing units).
Rounding prohibition/handling:    no rounding ever occurs on a `payments.amount_minor` value after
                                it is copied from `donations.amount_minor` — it is an exact integer
                                copy. Any unit conversion a specific provider's API requires (e.g.
                                Tripay expecting whole IDR units for a 0-minor-digit currency) is a
                                LOSSLESS conversion for every currency this platform's
                                CurrencyMinorUnits registry currently supports (IDR has 0 minor
                                digits, so amount_minor IS whole IDR already — no rounding is ever
                                needed for the one currency Tripay actually supports). If a future
                                provider/currency combination would require lossy rounding, that
                                combination MUST be rejected at adapter-validation time, never
                                silently rounded.
Foreign-currency transaction
  context:                        out of scope beyond "reject an unsupported provider/currency
                                pairing" — FX conversion/accounting is not this specification's
                                concern (no FX rate table, no cross-currency settlement logic).
Deterministic accounting basis
  boundary:                        Payment's amount_minor/currency is evidence of what was
                                attempted/confirmed at the PAYMENT layer — it is NOT itself an
                                accounting record (mirrors IMP-008's identical "Financial Impact"
                                posture for Donation: display/intent/confirmation evidence, not a
                                Ledger entry). The eventual Ledger posting (IMP-010/011) determines
                                its own accounting-basis currency handling independently; IMP-009
                                does not pre-decide it.
```

No FX accounting is implemented in Payment Hub (per task instruction, restated here as binding).

## Authorization

Canonical AND-chain applied to every non-webhook, non-scheduled-job Payment operation (webhook/
scheduled-job attribution is System/Integration Principal — see "System Principal" — which is a
DIFFERENT, non-human-facing authorization path, not exempted from authorization entirely):

```
Authenticated AND Permission AND Applicable Data Scope AND Ownership/Subject Access Rule AND
Required Business Authority AND Valid Resource State AND Required Approval State AND Required
Authentication Assurance AND No Security Restriction. Default DENY.
```

```
Public/guest Payment
  creation (for a guest-
  owned PENDING Donation):     NO authentication required — mirrors Donation's own guest-creation
                                exception (IMP-008-donation.md:558-564) applied consistently: a
                                guest who was allowed to create the Donation without an account
                                must also be able to attempt payment for it without one. Still
                                subject to: the target Donation must exist, be PENDING, and (per
                                HD-IMP009-01) have no other ACTIVE Payment Attempt already. Once
                                the guest leaves the provider's own hosted checkout page, no
                                platform-level guest resume/retry mechanism exists — `FINAL /
                                LOCKED`, HD-IMP009-04: no identifier (Donation ID, Payment ID,
                                ULID, email) ever functions as a resume credential. Recovery is
                                only via the abandoned attempt's own natural expiry
                                (HD-IMP009-09), which frees the HD-IMP009-01 slot for a genuinely
                                new attempt, or an authorized organization-scoped support-
                                controlled process (see "Donor/admin cancelling" below).

Authenticated donor Payment
  creation (own Donation):      Authenticated AND permission `payment.create` AND scope OWN
                                (ownership rule: the target Donation's donor_principal_id == acting
                                Principal) AND resource state: target Donation is PENDING.

Donor/guest viewing own
  Payment(s):                    same ownership pattern as Donation viewing (IMP-008) — an
                                authenticated donor: Authenticated AND permission `payment.view`
                                AND scope OWN (via the owning Donation's donor_principal_id). A
                                guest has the same "no durable way to list past guest activity"
                                limitation IMP-008 already established (no account) — viewing a
                                guest Payment's live status is only possible within the original
                                response/redirect flow, never through a bearer-token/identifier-
                                as-credential mechanism (`FINAL / LOCKED`, HD-IMP009-04, mirroring
                                HD-IMP008-05B's identical prohibition by the same principle).

Admin/staff viewing Payments:    Authenticated AND permission `payment.view` AND scope
                                ORGANIZATION.

Payment retry (new Attempt
  against an existing PENDING
  Donation):                     same authorization shape as Payment creation above (guest or
                                authenticated, ownership-gated), additionally gated by
                                HD-IMP009-01: the prior Payment Attempt against this Donation MUST
                                already be in a terminal (non-ACTIVE) state — enforced at the DB
                                level (see "Concurrency" / "Database Impact"). No lifetime attempt
                                limit applies (HD-IMP009-03).

Donor/admin cancelling a
  PENDING/REQUIRES_ACTION
  Payment:                       Authenticated AND permission `payment.cancel` AND scope OWN
                                (donor) or ORGANIZATION (admin) AND resource state PENDING or
                                REQUIRES_ACTION — mirrors Donation's own cancel-authorization shape
                                (IMP-008 §Authorization/RBAC) exactly. Guest self-service
                                cancellation of a Payment is NOT supported, mirroring HD-IMP008-05B
                                by the same principle. This ORGANIZATION-scoped admin path is also
                                the authorized support-controlled process HD-IMP009-04 permits for
                                freeing an abandoned guest Payment's ACTIVE slot before its own
                                natural expiry — no guest bearer-token/identifier-as-credential
                                mechanism is introduced for this or any other purpose.

Manual transfer evidence
  submission (donor/guest,
  own Payment):                   same ownership/guest-access shape as Payment creation.
                                Mandatory per HD-IMP009-06 (at least one evidence row is required
                                before an admin may APPROVE); resubmission is permitted per
                                HD-IMP009-08 while the Payment remains evidence-eligible (see
                                "Manual Transfer").

Manual transfer verification
  (approve/reject/amount-
  mismatch-hold):                 Authenticated AND permission `payment.manual_transfer.verify`
                                AND scope ORGANIZATION AND Business Authority `financial_approver`
                                (the closest fit in the CLOSED Authority Type taxonomy —
                                BUSINESS-AUTHORITY-MODEL.md lists `financial_approver`,
                                `refund_approver`, `withdrawal_approver`, `distribution_approver`,
                                `zakat_authority`, `partner_verifier`, `beneficiary_verifier`; NO
                                new Authority Type is invented for Payment verification — reusing
                                `financial_approver` is the documented, no-new-taxonomy choice,
                                exactly as IMP-008 reused OWN/ORGANIZATION scope rather than
                                inventing a new ScopeType) AND resource state: the target Payment
                                is PENDING with at least one `manual_transfer_evidence` row present
                                (`FINAL / LOCKED`, HD-IMP009-06 — mandatory evidence). The
                                AMOUNT_MISMATCH_HOLD outcome (HD-IMP009-07) uses the identical
                                authorization gate as APPROVED/REJECTED — it is not a lesser- or
                                greater-privileged action.

System/provider-outcome
  transition (Payment ->
  SUCCEEDED/FAILED/EXPIRED
  via webhook/poll/sweep):        NOT reachable through any human-facing permission — reserved for
                                the Integration Principal (webhook) or System Principal (poll/
                                sweep) invocation only (see "System Principal"). Mirrors IMP-008's
                                identical rule for the Donation-side transition surface
                                (IMP-008-donation.md:605-614).

Provider configuration
  management (credentials,
  enable/disable, bank
  accounts):                      Authenticated AND permission `payment.provider_config.manage`
                                AND scope GLOBAL_PLATFORM (a provider is a platform-wide, not
                                per-organization/per-campaign, concern — the Data Scope Model's
                                broadest scope applies). Super Admin does NOT automatically imply
                                this authority (Q27's "Super Admin != automatic Financial
                                Authority" principle, applied here identically to IMP-008's own
                                restatement of it) — an explicit permission + scope grant is
                                required regardless of Super Admin status.
```

New permissions (naming mirrors `donation.*`/`campaign.*` convention):

```
payment.view
payment.create
payment.cancel
payment.manual_transfer.submit_evidence
payment.manual_transfer.verify
payment.provider_config.manage
```

No existing permission is reused for a different meaning; no new ScopeType is added to the closed
taxonomy (OWN, FUNDRAISER, PARTNER, CAMPAIGN, PROGRAM, FUND, BENEFICIARY_CASE, ASSIGNED_WORK,
ORGANIZATION, GLOBAL_PLATFORM — DATA-SCOPE-MODEL.md); no new Business Authority Type is added to
the closed taxonomy (`financial_approver` is reused for manual-transfer verification, per above).

## System Principal

Per IMP-003's already-established, and here directly-anticipated, catalog design
(IMP-003-rbac-scope-business-authority.md:770-792 — its own worked example for
`integration_principals` is literally `'payment.tripay-webhook'`):

```
integration_principals (webhook/callback-attributed actions — one per external integration):
  payment.tripay-webhook
  payment.xendit-webhook
  payment.stripe-webhook

system_principals (scheduler/cron-attributed actions — one per distinct background-job identity):
  scheduler.payment-expiry-sweep      — the Payment-level expiry sweep (see "Expiration").
  scheduler.payment-status-poll       — the optional status-poll fallback (see "Provider Adapter
                                         Architecture" retrieveStatus(), "Shared Hosting").
  system.payment-outcome-consequence  — the internal invocation of Donation's PENDING ->
                                         SUCCEEDED/FAILED transition surface upon a terminal
                                         Payment outcome (see "Donation Integration" /
                                         "Financial Boundary") — deliberately a DISTINCT System
                                         Principal identity from the webhook's own Integration
                                         Principal, so "a webhook was received" (Integration
                                         Principal's action) and "a Donation state transition was
                                         applied" (System Principal's action) are never conflated
                                         in the audit trail, per SECURITY-INVARIANTS.md's explicit
                                         "A System Principal action and an Integration Principal
                                         action are never conflated" and the separation-of-duties
                                         invariant.
```

Each is a narrowly, explicitly assigned Role/Authority scoped to exactly its own job family —
never a blanket/global Role, and never inheriting the authority of whatever human request
originally queued a background job that later runs as one of these principals (confused-deputy
protection, per IMP-003's own "System Principal Least Privilege" precedent,
IMP-003-rbac-scope-business-authority.md:1785-1791). A background job/webhook handler that
produces a financial-adjacent consequence still goes through the full canonical AND-chain with its
OWN explicit Authority Assignment — it is never given an implicit bypass merely for being a
System/Integration Principal (IMP-003-rbac-scope-business-authority.md:789-792).

## Audit

Reuses IMP-004's `AuditWriter`/`AuditEventRegistry` exclusively — no parallel mechanism.

**Criticality classification** (CRITICAL is the default per Q26 for any unclassified event; this
specification classifies every `payment.*`/`manual_transfer.*`/`webhook.*` event explicitly, never
leaving one to the implicit default):

```
payment.attempt_created           NonCritical | financial reference: true | actor: Human |
                                   Unauthenticated (guest) | payload: donation_ulid, provider,
                                   amount_minor, currency, idempotency_key — mirrors donation.created's
                                   own "intent record" classification (Donation/Payment amount at
                                   this stage is intent/attempt evidence, not yet a confirmed
                                   financial outcome).
payment.succeeded                 CRITICAL / MUTATION_ATOMIC | financial reference: true | actor:
                                   Integration (webhook) or System (poll/sweep-driven resolution) |
                                   payload: payment_ulid, donation_ulid, provider,
                                   provider_reference, amount_minor, currency. Classified CRITICAL
                                   — DEPARTING from IMP-008's NonCritical precedent for Donation's
                                   own events — because this is the exact event that triggers the
                                   Donation-side Business State Transition and is the closest thing
                                   in this specification's scope to Q26's explicitly-listed
                                   critical category "future approval/financial authority
                                   operations when applicable"
                                   (HUMAN-DECISION-REGISTER.md Q26): if the audit append fails, the
                                   Payment status write AND the Donation transition invocation
                                   (same transaction, see "Webhook Security" step 8-12) roll back
                                   together — no silent, unaudited money-movement acknowledgment.
payment.failed                    CRITICAL / MUTATION_ATOMIC | financial reference: true | actor:
                                   Integration or System | same same-transaction rollback
                                   guarantee as payment.succeeded, for the symmetric reason.
payment.expired                   NonCritical | financial reference: true | actor: System |
                                   mirrors donation.expired's own classification — no money
                                   actually moved, no financial consequence triggered.
payment.cancelled                 NonCritical | financial reference: true | actor: Human |
                                   payload: payment_ulid, cancelled_by_principal_id.
payment.donation_transition_rejected CRITICAL / MUTATION_ATOMIC | financial reference: true |
                                   actor: System | payload: payment_ulid, donation_ulid,
                                   attempted_outcome (SUCCEEDED|FAILED), donation_status_observed
                                   — `FINAL / LOCKED` per HD-IMP009-02: recorded whenever a
                                   terminal Payment outcome's requested Donation transition is
                                   rejected because the Donation is already in an incompatible
                                   terminal state (the "late success/failure" case). CRITICAL
                                   because it is the durable trigger for the "mandatory human/
                                   admin review" HD-IMP009-02 requires — an audit-append failure
                                   here must not silently drop the ONE record that flags this case
                                   for review.
webhook.received                  NonCritical | financial reference: false | actor: Integration |
                                   payload: provider, processing_result (see
                                   `payment_provider_events` — this event is the audit-layer
                                   RECORD of that table's own row, not a duplicate authoritative
                                   store).
webhook.verification_failed       NonCritical | financial reference: false | actor: Integration |
                                   payload: provider, reason (REJECTED_INVALID_SIGNATURE /
                                   REJECTED_MALFORMED / REJECTED_UNKNOWN_REFERENCE /
                                   REJECTED_AMOUNT_MISMATCH / REJECTED_CURRENCY_MISMATCH). Uses
                                   IMP-004's DENIAL_DURABLE persistence pattern (a rejection is
                                   security-relevant evidence that should not be lost, but it is
                                   NOT itself a mutation to roll back anything around).
manual_transfer.evidence_submitted NonCritical | financial reference: true | actor: Human |
                                   Unauthenticated (guest) | payload: payment_ulid,
                                   manual_transfer_evidence_ulid.
manual_transfer.approved          CRITICAL / MUTATION_ATOMIC | financial reference: true | actor:
                                   Human | payload: payment_ulid, verified_by_principal_id — a
                                   direct human financial-approval decision, squarely within Q26's
                                   "future approval/financial authority operations" critical
                                   category.
manual_transfer.rejected          CRITICAL / MUTATION_ATOMIC | financial reference: true | actor:
                                   Human | payload: payment_ulid, verified_by_principal_id,
                                   review_notes — same reasoning as approved.
manual_transfer.amount_mismatch_held CRITICAL / MUTATION_ATOMIC | financial reference: true |
                                   actor: Human | payload: payment_ulid, verified_by_principal_id,
                                   payments.amount_minor, declared_amount_minor — `FINAL / LOCKED`
                                   per HD-IMP009-07: recorded when an admin records the
                                   AMOUNT_MISMATCH_HOLD review outcome; same critical-category
                                   reasoning as manual_transfer.approved/rejected (a human
                                   financial-approval-adjacent decision).
provider_config.credential_changed CRITICAL / MUTATION_ATOMIC | financial reference: false | actor:
                                   Human | payload: provider, mode, is_enabled (NEVER the secret
                                   value itself) — a "privileged governance/security operation"
                                   per Q26's explicit critical-category list.
```

Guest-actor events (`payment.attempt_created`, `manual_transfer.evidence_submitted`) use
`AuditActorKind::Unauthenticated`, `actor_principal_id = NULL`, and a fixed registered
`execution_context`, following ADR-002's established mechanism in SHAPE — but ADR-002's own text
explicitly scopes its widening to "the failed/incomplete authentication cases... plus
`donation.created`'s guest case only... no other IMP-008 event uses this widened category... any
further use requires its own future ADR, not silent reuse" (IMP-008-donation.md:1197-1199).
**`FINAL / LOCKED` per HD-IMP009-12: this authorization is granted by
[ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md) — a new, narrowly-
scoped ADR enumerating exactly these two (event_type, execution_context) pairs
(`http:payment:guest_attempt_created`, `http:payment:guest_manual_transfer_evidence_submitted`) —
never a generic broadening of ADR-002 itself.** ADR-003 is a prerequisite recorded under
"Implementation Handoff Requirements", alongside Human Spec Approval and the GOV-MM-002 model
binding.

No `payment.*`/`manual_transfer.*`/`webhook.*` event ever logs a provider secret, API key, webhook
signing secret, or raw card data (see "Security Requirements" / "Configuration").

## Configuration

```
Non-secret operational
  configuration:                 `manual_transfer_bank_accounts` (bank name/account number/holder
                                name — not secrets, merely routing display data), `payments.channel`
                                display metadata, per-provider enable/disable flags
                                (`payment_provider_credentials.is_enabled`), SANDBOX/PRODUCTION
                                mode flags, the Payment expiry fallback config key (see
                                "Expiration").
Secret credentials:              `payment_provider_credentials.encrypted_secret` — Tripay private
                                key, Xendit secret key + callback verification token, Stripe secret
                                key + webhook signing secret. Application-layer encrypted at rest
                                (Laravel's encrypter, APP_KEY-backed) — no plaintext secret column
                                anywhere. Never exposed through any admin API/frontend response —
                                unconditionally masked/omitted at serialization, mirroring Q25's
                                "no plaintext credential in code, config, seeder, log, or audit
                                payload" principle (HUMAN-DECISION-REGISTER.md, applied here by
                                the same reasoning to provider credentials).
Provider enable/disable:         `payment_provider_credentials.is_enabled` — an adapter whose
                                provider row is disabled MUST refuse to create a new transaction
                                (typed rejection, see "Error Handling"), independent of whether its
                                credentials are otherwise valid; webhook receipt for a disabled
                                provider is still processed for forensics (an in-flight Payment
                                created before disablement must still be resolvable) but no NEW
                                Payment may be created against it.
Sandbox/production mode:         `payment_provider_credentials.mode` — a hard switch per provider;
                                mixing sandbox credentials with a PRODUCTION-mode flag (or vice
                                versa) is a configuration error the adapter MUST detect and refuse
                                to operate under, never silently proceed.
Merchant/account identifiers:     Tripay merchant_code, Xendit/Stripe account identifiers — treated
                                as non-secret operational configuration (they identify WHICH
                                account, not a credential granting access) unless a specific
                                provider documents otherwise.
Webhook secrets:                  Xendit callback verification token, Stripe webhook signing
                                secret — secret configuration, same encrypted-at-rest treatment as
                                API keys above.
API keys:                         see "Secret credentials" above.
Bank accounts:                    `manual_transfer_bank_accounts` — non-secret.
Payment channels:                  `payments.channel` — non-secret, display/routing only.
Currency capabilities:             each adapter's own supported-currency allow-list (see "Money /
                                Currency") — non-secret, but authoritative for the
                                provider/currency validation gate at Payment creation.
```

## File Security

Applies to `manual_transfer_evidence` uploads (the only file-upload surface in this
specification):

```
Private storage only:           uploaded evidence is stored on a PRIVATE Laravel Storage disk,
                                never the `public` disk, never served by a direct static-file
                                route.
No permanent public URL:         retrieval is exclusively through an authorized, signed/
                                short-lived, ownership-and-permission-gated application route
                                (donor viewing their OWN evidence, or an admin holding
                                `payment.manual_transfer.verify` in ORGANIZATION scope) — never a
                                bare, permanently-guessable storage path (SECURITY-INVARIANTS.md:
                                "A sensitive file is never reachable through a permanent public
                                URL").
Authorized access:                same canonical AND-chain as any other Payment operation, scoped
                                to the owning Payment's Donation ownership (donor) or ORGANIZATION
                                scope (admin/verifier).
MIME/type validation:            server-side MIME-type validation against an explicit allow-list
                                (e.g. image/jpeg, image/png, application/pdf — exact list an
                                implementation-time parameter, not invented here as a specific
                                enumerated set without evidence); never trusting a client-supplied
                                Content-Type header alone.
Size limits:                     an explicit maximum upload size (implementation-time parameter);
                                oversized uploads rejected before any storage write.
Randomized storage name:          the stored `file_path` uses a randomized/opaque filename, never
                                the donor-supplied original filename or any guessable/sequential
                                identifier.
No user-controlled executable
  path:                           the upload path is never donor-influenced beyond the file
                                content/MIME itself — no donor-supplied path component, extension
                                spoofing is rejected by the MIME allow-list, and the storage disk
                                is configured to never execute uploaded content.
Audit access where required:      viewing a stored evidence file by an admin is itself an
                                auditable read (mirrors the existing "financial reference" audit-
                                read gating pattern established for Fund-adjacent events in
                                IMP-007/008) — an implementation-time decision on whether EVERY
                                read is individually audited or only the review decision is,
                                bounded by whatever the eventual retention/audit-read policy
                                requires; not over-specified here beyond the storage/access-control
                                contract above.
Retention:                        governed by a future, separately authorized retention policy
                                (mirrors Q17's pattern) — no retention duration is invented by this
                                specification.
```

## Donation Integration

Precise relationship between Donation (IMP-008) and Payment (this IMP), derived from IMP-008's
LOCKED contract plus the twelve Human Decisions resolved under "Human Decisions (Resolved)":

```
One Donation may have MULTIPLE Payment attempts:      YES, over its LIFETIME — MASTER-REQUIREMENTS.md
                                                        §9 ("Payment may have multiple intents/
                                                        attempts") is directly authoritative;
                                                        `payments.donation_id` is a plain FK with
                                                        no uniqueness constraint (see "Domain
                                                        Model"). AT MOST ONE may be ACTIVE at a
                                                        time — see below.
Payment retry creates a new Payment record:            YES, always a NEW `payments` row (see "State
                                                        Machines" terminal-state rule, "Provider
                                                        Adapter Architecture" retryBehavior) —
                                                        never mutates a terminal Payment row.
How many attempts may be simultaneously ACTIVE:        EXACTLY ONE — `FINAL / LOCKED`, HD-IMP009-01.
                                                        A new Payment Attempt may be created only
                                                        after the prior attempt against the same
                                                        Donation reaches a terminal (non-ACTIVE)
                                                        state; enforced at the DB level via the
                                                        `active_slot` generated column + unique
                                                        constraint (see "Domain Model" /
                                                        "Concurrency"). No lifetime maximum COUNT of
                                                        (sequential) attempts applies (HD-IMP009-03).
Payment status vs Donation status:                     INDEPENDENT state machines. Donation's
                                                        PENDING/SUCCEEDED/FAILED/CANCELLED/EXPIRED
                                                        (IMP-008) is the donor-facing intent
                                                        lifecycle; Payment's PENDING/
                                                        REQUIRES_ACTION/SUCCEEDED/FAILED/EXPIRED/
                                                        CANCELLED (this IMP) is the per-attempt
                                                        provider-transaction lifecycle. A Donation
                                                        remains PENDING through zero, one, or many
                                                        SEQUENTIAL (never concurrent, per
                                                        HD-IMP009-01) terminal Payment attempts,
                                                        until exactly ONE Payment attempt reaches a
                                                        terminal outcome that IMP-009 forwards to
                                                        Donation's transition surface (see below) —
                                                        or until Donation's OWN independent expiry
                                                        sweep (IMP-008) fires first.
How a successful Payment affects
  Donation:                                              `FINAL / LOCKED`, HD-IMP009-02/HD-IMP009-11.
                                                        A Payment reaching SUCCEEDED always FIRST
                                                        records that verified provider fact on the
                                                        Payment row itself (`payments.status =
                                                        SUCCEEDED`, `succeeded_at` set) — this write
                                                        happens regardless of the Donation's current
                                                        state. Within the SAME database transaction
                                                        (see "Concurrency"), IMP-009 then REQUESTS a
                                                        Donation transition ONLY through Donation's
                                                        existing System-Principal-gated
                                                        PENDING -> SUCCEEDED transition surface
                                                        (IMP-008-donation.md:605-614), using the
                                                        `system.payment-outcome-consequence` System
                                                        Principal (see "System Principal") — Payment
                                                        never bypasses this surface or mutates
                                                        `donations` directly (HD-IMP009-11). If the
                                                        Donation IS in PENDING status at that
                                                        moment, the transition succeeds normally. If
                                                        the Donation is NOT in PENDING status
                                                        (already terminal, e.g. already EXPIRED or
                                                        CANCELLED, or already SUCCEEDED/FAILED from
                                                        a PRIOR sequential Payment attempt), the
                                                        transition invocation is rejected by
                                                        Donation's own BR-7 — this rejection is
                                                        caught and recorded, NEVER forced, reopened,
                                                        or worked around, and the case is flagged
                                                        for controlled exception/manual review
                                                        (HD-IMP009-02) — see "Failure Semantics".
                                                        This does not authorize any Ledger posting
                                                        (HD-IMP009-11; IMP-010/IMP-011 boundaries
                                                        intact).
Failed Payment behavior:                                symmetric to the above: a Payment reaching
                                                        FAILED always records that fact first, then
                                                        requests the SAME transition surface with a
                                                        failure outcome (Donation
                                                        PENDING -> FAILED), same same-transaction/
                                                        same-Principal/same HD-IMP009-02
                                                        record-first-request-second discipline, same
                                                        BR-7 rejection-and-flag behavior if the
                                                        Donation is already terminal. A FAILED
                                                        Payment Attempt does NOT, by itself,
                                                        necessarily drive the Donation to FAILED —
                                                        see "Failure Semantics" for the exact
                                                        boundary between "this specific attempt
                                                        failed" (Payment-level fact, always
                                                        recorded) and "the Donation itself should be
                                                        considered FAILED" (a decision the owning
                                                        application flow makes about whether the
                                                        donor is offered a retry — HD-IMP009-01/03
                                                        permit an unlimited number of SEQUENTIAL
                                                        retry attempts, so a single FAILED attempt
                                                        is ordinarily NOT sufficient by itself to
                                                        drive Donation to FAILED unless the Donation
                                                        itself independently expires or the donor/
                                                        admin cancels it).
Expired Donation interaction:                            see "Expiration" — the two clocks are
                                                        independent; a Payment attempt in flight
                                                        when its Donation independently expires is
                                                        handled per HD-IMP009-02's record-first,
                                                        flag-if-terminal resolution, and no NEW
                                                        Payment attempt may ever be created against
                                                        an already-EXPIRED (or otherwise terminal)
                                                        Donation (validated at Payment-creation
                                                        time, resource state PENDING required — see
                                                        "Authorization").
Recurring Donation occurrence
  interaction:                                            EACH Recurring Occurrence's generated
                                                        Donation (IMP-008 — the generation ENGINE
                                                        itself remains unbuilt per IMP-008's own
                                                        "Out of Scope"/finalization record) is an
                                                        ordinary Donation from Payment's point of
                                                        view — it goes through the identical
                                                        Payment-creation/lifecycle contract as any
                                                        one-time Donation. `FINAL / LOCKED`,
                                                        HD-IMP009-10: Recurring Donation v1 is
                                                        MANUAL-PER-CYCLE — no Recurring Plan
                                                        pre-selects or durably stores a payment
                                                        method; IMP-009 stores no reusable/tokenized
                                                        payment credential, mandate, or off-session
                                                        charge authorization, and implements no
                                                        automatic/background charging. A future
                                                        auto-charge capability requires its own
                                                        separate, governed specification.
Idempotency boundary:                                    see "Idempotency" — Donation's own
                                                        idempotency key (HD-IMP008-05A) and
                                                        Payment's own idempotency key (this IMP)
                                                        are deliberately separate, non-overlapping
                                                        mechanisms at two different creation
                                                        points.
Guest Donation payment behavior:                          see "Authorization" — guest Payment
                                                        creation is allowed (mirrors guest Donation
                                                        creation), subject to HD-IMP009-04's
                                                        no-self-service-resume boundary and
                                                        HD-IMP009-01's one-ACTIVE-attempt invariant.
Authenticated Donation payment
  behavior:                                                Authenticated AND OWN-scope ownership,
                                                        identical shape to Donation's own
                                                        authenticated-donor pattern.
Campaign eligibility re-check
  at Payment-success time:                                  NOT performed — IMP-008's BR-1 checks
                                                        eligibility ONLY at Donation-creation time
                                                        and explicitly states "if this ordering is
                                                        wrong, raise via change control, not
                                                        silently here" (IMP-008-donation.md:490-494).
                                                        IMP-009 preserves this exactly, adding no
                                                        re-check of its own (see "Forbidden
                                                        Changes").
```

## Financial Boundary

This specification's initial submission (commit `21fef30`) flagged its own reading of the
canonical posting chain — that IMP-009 is the correct caller of IMP-008's Donation transition
surface — as an "Architectural Escalation" requiring Human confirmation before being relied upon.
**HD-IMP009-11 (`FINAL / LOCKED`) resolves this explicitly**: the design below is Human-authorized,
not merely an AI-authored interpretation.

```
IMP-009 MUST NOT own double-entry Ledger posting, Journal Entries, financial accounting balances,
  commission accounting, withdrawal accounting, or refund accounting (restated as binding by
  HD-IMP009-11's own text: "This decision does NOT authorize Ledger posting... IMP-010/IMP-011
  boundaries remain intact").
IMP-009 DOES own: the Payment aggregate itself, its state machine, and the invocation of
  Donation's (IMP-008's) already-defined System-Principal-gated Business State Transition surface
  upon a terminal Payment outcome (see "Donation Integration") — `FINAL / LOCKED`, HD-IMP009-11:
  "Payment success/failure may request Donation state transition through the EXISTING CANONICAL
  IMP-008 transition surface using the applicable registered System Principal/execution context.
  Payment MUST NOT directly update Donation state or bypass the IMP-008 state machine." This IS
  the "Business Event -> Domain Validation -> Business State Transition" portion of the canonical
  posting chain (FINANCIAL-POSTING-BOUNDARY.md) — a webhook/callback IS the "Provider Event," its
  verification pipeline (see "Webhook Security") IS "Normalize / Validate," the Payment status
  write IS the "Owning Application Use Case" + "Domain Validation," and the resulting REQUESTED
  Donation transition (always attempted, honored only when Donation's own state permits it per
  HD-IMP009-02) IS the "Business State Transition." The chain STOPS there for IMP-009 —
  "Authorized Financial Consequence" onward (Ledger Posting Contract, Double-Entry Ledger) is
  entirely IMP-011/IMP-010's own, later concern, exactly as HD-IMP009-11 confirms.
IMP-009 emits a `payment.succeeded`/`payment.failed` domain event (see "Audit") that a FUTURE
  IMP-011 subscriber consumes to construct its own Authorized Financial Consequence — IMP-009 does
  not construct that Consequence object, does not know its shape, and does not call into any
  Ledger code (none exists yet).
No component in this specification posts directly to a Ledger table by shortcutting this chain
  (AGENTS.md "Never": "Make provider callbacks write directly to Ledger" — there is, in fact, no
  Ledger table for a provider callback to write to at this stage; this restates the rule as a
  forward-looking constraint on IMP-010/011's eventual integration with this IMP's event surface,
  not merely a currently-vacuous statement).
```

## Ledger Boundary

IMP-010 (Ledger Foundation) and IMP-011 (Financial Consequence Posting) own Ledger Account, Ledger
Journal, Double-Entry Entries, and Authorized Financial Consequence construction/posting entirely.
IMP-009 creates no Ledger-related table, writes no Ledger-related row, and references no
Ledger-related concept beyond emitting the domain event described in "Financial Boundary" for a
future IMP-011 to consume.

## Refund Boundary

IMP-016 (Refund) owns the Refund business workflow (initiation, approval via `refund_approver`
Business Authority, Ledger reversal) entirely. IMP-009 exposes only a provider-CAPABILITY
interface point — i.e. each provider adapter MAY expose whether the underlying provider API
supports a refund call at all (a fact about the provider, not a workflow) — for a FUTURE IMP-016
to consume; IMP-009 implements no Refund entity, no refund-initiation route, no refund approval
flow, and (per "Tripay" above) explicitly does NOT map a provider's own REFUND status signal to
any canonical Payment state — a refund event on an already-SUCCEEDED Payment is recorded
(`payment_provider_events`) for forensic completeness only and left entirely for IMP-016 to
interpret and act on.

## Reconciliation Boundary

IMP-017 (Reconciliation + Moota) owns Reconciliation Source, Bank Mutation, Reconciliation Match,
and matching evidence entirely (MODULE-OWNERSHIP.md §11). Payment status synchronization via a
provider webhook/callback (this IMP) is explicitly NOT reconciliation — it is the Payment domain
resolving its OWN state from its OWN provider's own notification channel. Moota remains
reconciliation-only: never a Payment Gateway, never a Ledger, never a settlement source of truth,
and this specification introduces no code path that matches an incoming Moota bank-mutation feed
to a `payments` row (see "Manual Transfer" — Manual Transfer's admin-verification workflow is
deliberately NOT automatic reconciliation either, despite being the provider most superficially
similar to what Moota eventually automates).

## Notifications Boundary

IMP-024 (Notifications + WhatsApp) owns the complete notification system, including the locked
requirement that WhatsApp Cloud API is used ONLY for successful donation notifications. IMP-009
implements no notification-sending code of any kind. The `payment.succeeded`/`payment.failed`
domain events this specification emits (see "Audit" / "Financial Boundary") are available for a
future IMP-024 subscriber to consume when it is built; IMP-009 does not call into IMP-024, does
not assume IMP-024 exists, and does not build any notification-adjacent scaffolding beyond that
plain domain event.

## Database Impact

```
payments
  id (BIGINT PK), ulid (CHAR 26, unique)
  donation_id (FK donations.id, RESTRICT, NOT NULL)
  provider (VARCHAR 32, NOT NULL)
  provider_reference (VARCHAR 191, NULLABLE)
  channel (VARCHAR 64, NULLABLE)
  amount_minor (BIGINT UNSIGNED, NOT NULL), currency (CHAR 3, NOT NULL)
  status (VARCHAR 16, NOT NULL, DEFAULT 'PENDING')
  idempotency_key (VARCHAR 128, NOT NULL, UNIQUE)
  instructions_payload (JSON, NULLABLE)
  expires_at (DATETIME, NULLABLE)
  succeeded_at / failed_at / expired_at / cancelled_at (DATETIME, NULLABLE)
  verified_by_principal_id (FK principals.id, RESTRICT, NULLABLE)
  cancelled_by_principal_id (FK principals.id, RESTRICT, NULLABLE)
  failure_reason (VARCHAR 255, NULLABLE)
  active_slot (TINYINT, NULLABLE, GENERATED ALWAYS AS (CASE WHEN status IN ('PENDING',
    'REQUIRES_ACTION') THEN 1 ELSE NULL END) STORED) — `FINAL / LOCKED`, HD-IMP009-01; see
    "Domain Model"
  timestamps

  Indexes: donation_id, provider, status, provider_reference, expires_at
  Unique: ulid, idempotency_key, (provider, provider_reference) WHERE provider_reference IS NOT NULL,
    (donation_id, active_slot) — the DB-level "at most one ACTIVE Payment Attempt per Donation"
    enforcement (HD-IMP009-01); NULL-distinct MySQL unique-index semantics mean terminal rows
    (active_slot NULL) never collide, only concurrently-ACTIVE rows for the same donation_id would
    — which this constraint makes impossible
  CHECK (app-level guard always; DB-level CHECK where the driver supports it, mirroring
    `donations`' own migration pattern): amount_minor equal to the owning Donation's amount_minor
    at creation time (enforced at the service layer at INSERT time, since a cross-table CHECK
    constraint is not portably expressible — documented as an application-level invariant, not a
    DB-level one, consistent with `donations`' own SQLite-exception precedent)

payment_provider_events
  id (BIGINT PK), ulid (CHAR 26, unique)
  payment_id (FK payments.id, RESTRICT, NULLABLE)
  provider (VARCHAR 32, NOT NULL)
  provider_event_id (VARCHAR 191, NULLABLE)
  event_type (VARCHAR 64, NOT NULL)
  signature_valid (BOOLEAN, NOT NULL)
  processing_result (VARCHAR 32, NOT NULL)
  raw_payload_ciphertext (LONGTEXT, NULLABLE)
  received_at (DATETIME, NOT NULL)
  timestamps

  Indexes: payment_id, provider, processing_result, received_at
  Unique: (provider, provider_event_id) WHERE provider_event_id IS NOT NULL

manual_transfer_evidence
  id (BIGINT PK), ulid (CHAR 26, unique)
  payment_id (FK payments.id, RESTRICT, NOT NULL)
  submitted_by_principal_id (FK principals.id, RESTRICT, NULLABLE)
  file_path (VARCHAR 255, NOT NULL), mime_type (VARCHAR 100, NOT NULL), size_bytes (BIGINT
    UNSIGNED, NOT NULL)
  declared_amount_minor (BIGINT UNSIGNED, NULLABLE), declared_currency (CHAR 3, NULLABLE),
    declared_transferred_at (DATETIME, NULLABLE)
  reviewed_by_principal_id (FK principals.id, RESTRICT, NULLABLE), reviewed_at (DATETIME,
    NULLABLE), review_outcome (VARCHAR 24, NULLABLE) — APPROVED | REJECTED |
    AMOUNT_MISMATCH_HOLD (HD-IMP009-07), review_notes (VARCHAR 1000, NULLABLE)
  timestamps

  Indexes: payment_id, reviewed_by_principal_id
  Unique: ulid

manual_transfer_bank_accounts
  id (BIGINT PK), ulid (CHAR 26, unique)
  bank_name (VARCHAR 100, NOT NULL), account_number (VARCHAR 64, NOT NULL),
    account_holder_name (VARCHAR 150, NOT NULL), currency (CHAR 3, NOT NULL)
  is_active (BOOLEAN, NOT NULL, DEFAULT TRUE)
  timestamps

  Unique: ulid
  Indexes: currency, is_active

payment_provider_credentials
  id (BIGINT PK)
  provider (VARCHAR 32, UNIQUE, NOT NULL)
  mode (VARCHAR 16, NOT NULL)
  encrypted_secret (TEXT, NOT NULL)
  is_enabled (BOOLEAN, NOT NULL, DEFAULT FALSE)
  timestamps
```

No `donations`/`campaigns`/`programs`/`funds` column is added or modified. No Ledger/Commission/
Withdrawal/Refund/Reconciliation table is created (see "Out of Scope").

Configuration (not a table): `config('payment.pending_expiry_minutes')`, default `null` — see
"Expiration". No config value is pre-populated with an invented number.

Migration naming mirrors IMP-007/008's `0001_09_01_NNNNNN` convention. Ordering constraint: since
`payments` references `donations.id` (already created by IMP-008) and `payment_provider_events`/
`manual_transfer_evidence` both reference `payments.id`, the migration order is: (1)
`manual_transfer_bank_accounts`, (2) `payment_provider_credentials`, (3) `payments`, (4)
`payment_provider_events`, (5) `manual_transfer_evidence` — no circular-dependency problem exists
here (unlike IMP-008's own recurring-table ordering issue), since nothing in this IMP is referenced
by an earlier-created table of its own.

## Routes / API Boundary

Minimum internal application actions needed for the web flow (not the full IMP-025 REST API),
mirroring IMP-008's own `admin/donation/*` / `/me/donations` convention:

```
Public/donor payment initiation:
  POST /donations/{ulid}/payments                    — create a Payment Attempt against an
                                                         existing PENDING Donation (guest or
                                                         authenticated, ownership/state-gated per
                                                         "Authorization"). Requires
                                                         `Idempotency-Key` header.

Donor-owned payment views:
  GET  /me/donations/{ulid}/payments                 — authenticated donor's own Payment attempts
                                                         for one Donation (scope OWN).
  GET  /me/donations/{ulid}/payments/{payment_ulid}  — single own Payment detail.
  POST /me/donations/{ulid}/payments/{payment_ulid}/cancel — donor-initiated cancel while
                                                         PENDING/REQUIRES_ACTION.

Manual transfer evidence:
  POST /me/donations/{ulid}/payments/{payment_ulid}/manual-transfer/evidence — submit proof
                                                         (guest-or-authenticated, ownership-gated).

Provider webhooks (one route per provider, CSRF-exempt — see "Webhook Security"):
  POST /webhooks/payments/tripay
  POST /webhooks/payments/xendit
  POST /webhooks/payments/stripe

Admin/backoffice views/actions:
  GET  /admin/payment/payments                         — organization-scoped list.
  GET  /admin/payment/payments/{ulid}                   — organization-scoped detail.
  POST /admin/payment/payments/{ulid}/manual-transfer/approve — `financial_approver` gated.
  POST /admin/payment/payments/{ulid}/manual-transfer/reject  — `financial_approver` gated.
  GET  /admin/payment/provider-config                    — GLOBAL_PLATFORM scoped, secrets masked.
  POST /admin/payment/provider-config/{provider}          — GLOBAL_PLATFORM scoped credential
                                                           update (write-only for the secret value
                                                           — never echoed back).
```

Route/controller naming mirrors IMP-007/008's `admin/{module}/*` convention (`admin/payment/*`). No
public route exposes internal BIGINT ids (ulid only, mirroring AC-007-011/IMP-008's identical
rule). Webhook routes are the ONE explicit, narrowly-scoped CSRF exemption in this specification
(see "Webhook Security" step 1) — never generalized.

## Shared Hosting

All required Payment Hub behavior works with Apache/LiteSpeed, PHP, MySQL, Cron, DB-backed queue,
and compiled frontend assets. No mandatory Redis, Supervisor, PM2, WebSocket runtime, Kafka,
RabbitMQ, Elasticsearch, or Node.js production runtime (MASTER-REQUIREMENTS.md §4/§5;
MASTER-ARCHITECTURE.md:37).

```
Provider callbacks:            ordinary synchronous HTTPS POST endpoints (see "Routes / API
                                Boundary") — no persistent socket/listener needed.
Payment expiry sweep:           Laravel Scheduler + Cron, running under
                                `scheduler.payment-expiry-sweep` (System Principal), processing a
                                bounded batch per invocation (never an unbounded full-table sweep
                                per invocation) via the DB-backed queue for the actual per-row work
                                if the batch is large enough to warrant queueing.
Status-poll fallback (where a
  provider's webhook delivery
  cannot be assumed reliable
  enough alone):                  same Scheduler + Cron + DB queue model, under
                                `scheduler.payment-status-poll` — never a long-running always-on
                                poller process.
```

## Security Requirements

Apply the canonical authorization AND-chain (see "Authorization") to every non-webhook,
non-scheduled-job Payment operation. Public/guest Payment CREATION and manual-transfer evidence
submission are the explicit, narrow exceptions where `Authenticated` is intentionally not
required (mirroring Donation's own identical exception) — bounded exactly as described in
"Authorization"/"Donation Integration." Default DENY for every protected operation. Super Admin
does NOT automatically imply Payment/financial authority (see "Authorization").

```
Webhook/callback trust:         never trusted without verification and replay protection (see
                                "Webhook Security") — SECURITY-INVARIANTS.md's own invariant,
                                restated as this specification's binding contract.
Sensitive file delivery:         no permanent public URL for manual-transfer evidence (see "File
                                Security").
System/Integration Principal
  separation:                     never conflated (see "System Principal").
Separation of duties:            the actor who submits manual-transfer evidence (donor/guest) is
                                NEVER the same actor who approves/rejects it (an admin holding
                                `financial_approver`) — this is inherent to the authorization model
                                above (a donor never holds ORGANIZATION-scoped
                                `payment.manual_transfer.verify` over their own Payment) and is
                                explicitly never collapsed "without an explicit, approved
                                exception" (SECURITY-INVARIANTS.md).
No card data storage:            see "Stripe" — binding platform-wide, not merely a Stripe-adapter
                                detail.
No secret in source/logs:        every provider credential, webhook signing secret, and callback
                                verification token is stored exclusively in
                                `payment_provider_credentials`, encrypted at rest, never logged,
                                never included in an audit payload, never returned in any API
                                response (see "Configuration" / "Audit").
```

## Failure Semantics

```
Provider create-transaction call
  fails (network/5xx/timeout):    the `payments` row and the provider API call are NOT treated as
                                  atomic in the classic sense (an external HTTP call cannot
                                  participate in a DB transaction) — the specification's required
                                  sequencing is: (1) begin transaction, (2) insert the `payments`
                                  row in a transient PENDING state with provider_reference NULL,
                                  (3) commit, (4) call the provider adapter's createTransaction()
                                  OUTSIDE that transaction, (5) on success, a SECOND, separate
                                  transaction updates provider_reference/instructions_payload/
                                  expires_at; (6) on failure, the Payment is moved directly to
                                  FAILED (failure_reason = 'PROVIDER_CREATE_FAILED') in its own
                                  transaction — it is NEVER left indefinitely in a PENDING state
                                  with no provider_reference and no path to resolution; a donor
                                  retry (new Payment Attempt, new idempotency key) is always
                                  available.
Donation transition invocation
  fails (Donation already
  terminal — `FINAL / LOCKED`,
  HD-IMP009-02):                   the Payment's own status write (SUCCEEDED/FAILED) still
                                  commits (it accurately records what the PROVIDER reported — a
                                  late provider outcome is NEVER discarded, per HD-IMP009-02); the
                                  Donation transition invocation's rejection is caught, and the
                                  outcome is recorded distinctly (a
                                  `payment.donation_transition_rejected` audit event, CRITICAL,
                                  never silently swallowed) for the mandatory admin/manual review
                                  HD-IMP009-02 requires — this is NOT treated as a
                                  Payment-processing error (the Payment truthfully succeeded/
                                  failed at the provider); it is a Donation-state conflict, flagged
                                  for human attention. No accounting treatment (refund, credit,
                                  reinstatement) is invented here — HD-IMP009-02 explicitly leaves
                                  resolution to a later governed domain.
Invalid/ineligible currency for
  chosen provider (Money /
  Currency violation):              typed validation exception, Payment NOT created.
Amount mismatch at creation
  (Payment amount != Donation
  amount — should never occur
  given BR-2, but guarded
  regardless):                       typed validation exception, Payment NOT created — defense in
                                  depth against a service-layer bug, not an expected user-facing
                                  path.
Malformed webhook payload:          see "Webhook Security" step 4.
Invalid webhook signature:          see "Webhook Security" step 3.
Missing Idempotency-Key header:      typed validation exception, checked first, before any other
                                  validation, mirroring Donation's own BR-12 ordering.
Idempotency-Key reused, matching
  payload:                            NOT an error — existing Payment returned (see "Idempotency").
Idempotency-Key reused, different
  payload:                            typed conflict exception (409-style); no row created.
Invalid Payment state transition:    typed conflict exception, no state change (mirrors AC-007-007/
                                  AC-008-010).
Provider disabled
  (payment_provider_credentials.
  is_enabled = false):                typed rejection at Payment-creation time, provider name
                                  surfaced generically (never a raw "credentials missing"-style
                                  message that could leak configuration state to an unauthenticated
                                  caller).
Unauthorized manual-transfer
  verification attempt:               standard AND-chain DENY (see "Authorization"), no state
                                  change, no evidence file exposed.
```

No sensitive diagnostic information (raw provider errors, stack traces, secret fragments,
internal exception messages) is ever returned in any donor/guest/admin-facing API response.

## Business Rules

```
BR-1   A Payment MUST reference exactly one Donation, and MUST NOT be created unless that
       Donation is currently in PENDING status (IMP-008's own resource-state gate) — checked
       under the Donation-row lock at creation time (see "Concurrency").
BR-2   A Payment's amount_minor/currency MUST equal the owning Donation's own amount_minor/
       currency at creation time, exactly (no partial-amount Payment, no currency substitution) —
       validated via Money::ofMinorUnits()/CurrencyMinorUnits::digitsFor() (IMP-007).
BR-3   payments.provider MUST be one of the four approved values (manual_transfer, tripay,
       xendit, stripe), validated against a closed, registry-driven allow-list. Midtrans is never
       a valid value without an approved ACR (MASTER-REQUIREMENTS.md §9).
BR-4   A Payment's canonical status is derived EXCLUSIVELY through each provider adapter's
       normalizeStatus() mapping (see "State Machines") — no code outside an adapter ever
       branches on a raw provider status string.
BR-5   Payment creation, by itself, MUST NOT create a Ledger journal/entry, Commission
       entitlement, Withdrawal balance, or Reconciliation entry (see "Financial Boundary" —
       restates AGENTS.md "Never: invent accounting entries" and the Financial Posting Boundary
       for this specific IMP, mirroring IMP-008 BR-5 exactly).
BR-6   A terminal Payment state (SUCCEEDED/FAILED/EXPIRED/CANCELLED) is never mutated back to
       PENDING/REQUIRES_ACTION or to any other terminal state (mirrors IMP-008 BR-7).
BR-7   payments.donation_id, provider, amount_minor, currency, idempotency_key are immutable
       after creation (see "Domain Model").
BR-8   A terminal Payment outcome (SUCCEEDED/FAILED) always records that outcome on the Payment
       row first, then REQUESTS Donation's existing System-Principal-gated transition surface
       (IMP-008) within the SAME database transaction as the Payment's own status write and audit
       append — never as a separate, later, best-effort step, and never a direct mutation of
       `donations` bypassing that surface (`FINAL / LOCKED`, HD-IMP009-02/HD-IMP009-11 — see
       "Concurrency" / "Webhook Security" step 8-12 / "Donation Integration").
BR-9   A webhook/callback is processed only after successful, constant-time signature/authenticity
       verification specific to its provider (see "Webhook Security" step 3) — no exception, no
       "trust in development mode" bypass may exist in the shipped implementation.
BR-10  An inbound webhook/callback event with an amount/currency mismatch against the resolved
       Payment's own amount_minor/currency is rejected before any state transition is considered
       (see "Webhook Security" step 6) — this is a security control, not merely data validation.
BR-11  Payment creation MUST include a client-provided idempotency key, scoped independently from
       Donation's own idempotency key (see "Idempotency"); a request without one is rejected
       before any Payment row is created.
BR-12  No manual-transfer review decision (APPROVED/REJECTED/AMOUNT_MISMATCH_HOLD) may mutate
       payments.amount_minor or donations.amount_minor (`FINAL / LOCKED`, HD-IMP009-07 — see
       "Manual Transfer") — both remain immutable regardless of any donor-declared or
       admin-observed transferred amount.
BR-13  No `payments`/`payment_provider_events`/`manual_transfer_evidence` row is ever created or
       mutated by an inbound webhook for a `provider_reference` that does not resolve to exactly
       one existing Payment row (see "Webhook Security" step 5) — a webhook NEVER creates a
       Payment.
BR-14  No provider adapter maps a provider-native REFUND signal to any canonical Payment state
       transition (see "Tripay" / "Refund Boundary") — Refund is IMP-016's own, later domain.
BR-15  No component in this specification writes directly to any Ledger-related table (none exist
       yet at this IMP) — restated as a forward-binding constraint on the implementation (see
       "Financial Boundary" / "Ledger Boundary").
BR-16  A guest-originated `payment.attempt_created`/`manual_transfer.evidence_submitted` audit
       event records actor kind `AuditActorKind::Unauthenticated`, `actor_principal_id = NULL`,
       and its own fixed, registered `execution_context`, authorized by
       [ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md)
       (`FINAL / LOCKED`, HD-IMP009-12) — never any other IMP-009 event, and never a generic
       broadening of ADR-002 itself (see "Audit").
BR-17  No new Business Authority Type, ScopeType, or AuditActorKind value is invented by this
       specification (see "Authorization" / "Audit" / "Forbidden Changes").
BR-18  Every Payment expiry/window value (per-provider fallback config, Manual Transfer's own
       window) defaults to `null`/unset and MUST NOT act while unset (`FINAL / LOCKED`,
       HD-IMP009-09 — mirrors HD-IMP008-04's identical "no invented default" contract; see
       "Expiration").
BR-19  At most ONE Payment Attempt per Donation may be ACTIVE (PENDING or REQUIRES_ACTION) at any
       moment (`FINAL / LOCKED`, HD-IMP009-01), enforced at the DB level via the `active_slot`
       generated column + `UNIQUE(donation_id, active_slot)` constraint (see "Domain Model" /
       "Database Impact"), not merely a service-layer check. No lifetime maximum COUNT of
       sequential Payment Attempts per Donation applies (`FINAL / LOCKED`, HD-IMP009-03).
BR-20  A terminal Payment outcome whose requested Donation transition is rejected because the
       Donation is already in an incompatible terminal state MUST NOT be forced, retried against
       Donation directly, or silently dropped — it is recorded via a
       `payment.donation_transition_rejected` audit event (CRITICAL) for mandatory human/admin
       review, and no refund/credit/reinstatement/accounting treatment is invented in response
       (`FINAL / LOCKED`, HD-IMP009-02 — see "Donation Integration" / "Failure Semantics").
BR-21  A Manual Bank Transfer Payment cannot be recorded with review_outcome = APPROVED unless at
       least one `manual_transfer_evidence` row exists against it (`FINAL / LOCKED`, HD-IMP009-06
       — see "Manual Transfer").
BR-22  A Manual Bank Transfer amount mismatch (declared/observed transferred amount !=
       payments.amount_minor) is recorded as review_outcome = AMOUNT_MISMATCH_HOLD, never as an
       automatic APPROVED or REJECTED outcome (`FINAL / LOCKED`, HD-IMP009-07 — see "Manual
       Transfer").
BR-23  `manual_transfer_evidence` resubmission is permitted only while the owning Payment remains
       in an evidence-eligible state (PENDING, no recorded review_outcome); a terminal or
       already-reviewed Payment rejects further evidence submission, and no existing
       `manual_transfer_evidence` row is ever overwritten (`FINAL / LOCKED`, HD-IMP009-08 — see
       "Manual Transfer").
```

## Testing Requirements

```
Unit:                     Money/CurrencyMinorUnits construction paths reused correctly for
                           Payment; each provider adapter's normalizeStatus()/normalizeExpiration()
                           mapping table (every documented input value in "State Machines" per-
                           provider section produces the documented canonical output, including
                           unmapped/unknown provider status values being rejected rather than
                           silently defaulted).
Feature:                  Payment creation (guest + authenticated) against a PENDING Donation;
                           full canonical state-transition matrix (including every rejected
                           invalid transition per BR-6); Payment creation rejected against a
                           non-PENDING Donation.
Webhook Security:         Tripay/Xendit/Stripe signature verification: valid signature accepted,
                           invalid signature rejected (constant-time comparison — timing-safe
                           test where practical); malformed payload rejected; unknown
                           provider_reference rejected, no Payment created; amount mismatch
                           rejected; currency mismatch rejected; duplicate provider_event_id
                           accepted as DUPLICATE with no state change; out-of-order event does not
                           move status backward.
Idempotency:               missing Payment Idempotency-Key rejected; identical key + matching
                           payload returns existing Payment (no second row, no second provider
                           API call — verified via a mocked/faked adapter call-count assertion);
                           identical key + differing payload rejected with typed conflict;
                           provider-native idempotency key (Stripe) passed through correctly on
                           retry of the same internal request.
Concurrency (REAL MySQL
  required, not SQLite):    two concurrent identical webhook deliveries for the same Payment ->
                           exactly one state transition, one DUPLICATE record; concurrent webhook
                           vs status-poll resolving the same Payment -> exactly one winner;
                           concurrent webhook vs expiry sweep on the same Payment -> exactly one
                           winner, loser's action is a documented no-op/conflict, never a lost
                           update; concurrent Payment-creation requests against the same Donation
                           -> Donation-row lock ordering verified (see "Concurrency").
Expiration:                Payment with provider-supplied expiry moves to EXPIRED only after that
                           moment passes and no terminal outcome arrived; Payment relying on the
                           internal fallback config does NOT expire while that config is unset;
                           Donation's own independent expiry sweep does not affect
                           payments.expires_at and vice versa (two-clock independence, direct
                           test of the HD-IMP008-04 boundary).
Money:                     unregistered-currency rejection at Payment creation; provider/currency
                           unsupported-pairing rejection (e.g. attempting a non-IDR Tripay
                           Payment); Payment amount_minor/currency proven equal to the owning
                           Donation's own values, never independently settable.
Donation Integration:      a SUCCEEDED Payment drives the owning PENDING Donation to SUCCEEDED
                           (same transaction, verified via a single-transaction integration test
                           against IMP-008's real transition surface, not a mock); a FAILED
                           Payment drives the owning PENDING Donation to FAILED; a terminal Payment
                           outcome against an already-terminal Donation is rejected by IMP-008's
                           BR-7, the Payment's own status write still commits, and a
                           `payment.donation_transition_rejected` audit event is recorded
                           (`FINAL / LOCKED` HD-IMP009-02 scenario — test asserts exactly this
                           "recorded for review, never silently dropped or forced" behavior); the
                           Donation transition is requested only through IMP-008's System-
                           Principal-gated surface, never a direct `donations` mutation
                           (HD-IMP009-11, direct test that no other write path exists).
Authorization:             every RBAC row in "Authorization" — authorized ALLOW, unauthenticated
                           DENY (where auth is required), wrong permission DENY, correct
                           permission wrong scope DENY (donor A cannot view/cancel/retry donor
                           B's Payment), wrong resource state DENY, missing Business Authority
                           DENY (a permission-holder without `financial_approver` cannot approve
                           manual transfer evidence).
System Principal:          webhook-driven and sweep-driven transitions are correctly attributed to
                           the documented Integration/System Principal identities, never a human
                           Principal, never conflated with each other (direct test of the
                           SECURITY-INVARIANTS.md separation rule).
Audit:                     every payment.*/manual_transfer.*/webhook.* event recorded with the
                           documented criticality; a forced audit-append failure on a CRITICAL
                           event (payment.succeeded/failed, manual_transfer.approved/rejected,
                           provider_config.credential_changed) DOES roll back the accompanying
                           mutation (fail-closed, Q26, tested exactly as IMP-004's own
                           CRITICAL/MUTATION_ATOMIC contract requires); a forced failure on a
                           NonCritical event does NOT roll back its accompanying mutation.
File Security:             manual-transfer evidence upload rejects disallowed MIME types and
                           oversized files; stored file is not reachable via any public/static
                           URL; only the owning donor or an ORGANIZATION-scoped
                           `payment.manual_transfer.verify` holder can retrieve it; the stored
                           filename is not the original/donor-supplied name.
Manual Transfer:           evidence submission -> admin approval -> Payment SUCCEEDED -> Donation
                           SUCCEEDED, full path; evidence submission -> admin rejection -> Payment
                           FAILED -> Donation FAILED, full path; unauthorized (non-
                           `financial_approver`) verification attempt denied; APPROVE attempted
                           with zero evidence rows present is rejected (HD-IMP009-06); a second
                           evidence row submitted while the Payment remains eligible succeeds,
                           neither row is overwritten (HD-IMP009-08); evidence submission attempted
                           against a terminal or already-reviewed Payment is rejected; a
                           declared_amount_minor != payments.amount_minor is recorded as
                           review_outcome = AMOUNT_MISMATCH_HOLD, Payment remains PENDING, Donation
                           is untouched, and `payments.amount_minor`/`donations.amount_minor` are
                           unchanged (HD-IMP009-07).
One-ACTIVE-attempt
  invariant (HD-IMP009-01):   creating a second Payment Attempt while a PENDING/REQUIRES_ACTION
                           sibling exists against the same Donation is rejected at the DB level
                           (raw query proving the `UNIQUE(donation_id, active_slot)` constraint,
                           bypassing the service layer, mirroring AC-007-009/010); creating a new
                           attempt succeeds once the prior attempt reaches any terminal state; no
                           lifetime count limit rejects an Nth sequential attempt (HD-IMP009-03).
Xendit PaymentRequest
  mapping (HD-IMP009-05):     every documented PaymentRequest API status value in "State Machines"
                           normalizes to its documented canonical state; an unmapped/unexpected
                           Xendit status value is rejected, never silently defaulted.
Guest audit actor
  (HD-IMP009-12 / ADR-003):    a guest `payment.attempt_created` event records
                           AuditActorKind::Unauthenticated, actor_principal_id null, and
                           execution_context = "http:payment:guest_attempt_created"; a guest
                           `manual_transfer.evidence_submitted` event records the same actor kind
                           with execution_context =
                           "http:payment:guest_manual_transfer_evidence_submitted"; no other
                           IMP-009 event ever uses AuditActorKind::Unauthenticated.
Duplicate provider event:  mandatory per DEFINITION-OF-DONE.md's Financial Test list — same
                           provider_event_id delivered twice produces one state change.
Duplicate job retry:        the expiry sweep job processing the same candidate Payment twice
                           (e.g. an overlapping cron invocation) produces one expiry, not two
                           conflicting audit rows.
Duplicate financial
  consequence:                a Payment cannot be reported SUCCEEDED twice in a way that would
                           cause Donation's transition surface to be invoked twice for the same
                           outcome (idempotent invocation, verified directly).
Provider callback out-of-
  order:                       see "Webhook Security" out-of-order rule, direct test.
Payment retry:              mandatory per DEFINITION-OF-DONE.md's Financial Test list — a FAILED
                           or EXPIRED Payment attempt does not block a NEW Payment attempt against
                           the still-PENDING Donation once HD-IMP009-01's one-ACTIVE-attempt slot
                           is freed; an attempt WHILE the prior one is still ACTIVE is rejected
                           (see "One-ACTIVE-attempt invariant" above).
MySQL uniqueness/locking/
  constraint behavior:         RESTRICT on donation_id/payment_id/etc. deletion attempts (raw
                           query, bypassing the service layer, mirroring AC-007-009/010/
                           AC-008-011); (provider, provider_reference) and (provider,
                           provider_event_id) unique constraints proven at the DB level.
Migration UP/DOWN/UP:       every migration in this IMP runs UP, DOWN, UP cleanly against a
                           disposable MySQL schema.
Shared-hosting scheduler
  behavior:                    the expiry sweep and status-poll jobs run correctly under Laravel
                           Scheduler + Cron invocation (not merely a queue-worker-dependent path
                           that would fail without a persistently running worker process).
```

## Acceptance Criteria

```
AC-009-001
Given a PENDING Donation and a valid Idempotency-Key header
When a guest or authenticated donor creates a Payment for provider 'tripay' (or 'xendit'/'stripe')
Then a `payments` row exists in PENDING status, ulid-identified, amount_minor/currency equal to
  the Donation's own, the provider adapter's createTransaction() was called exactly once, and a
  `payment.attempt_created` audit event is recorded with the correct actor kind (Human or
  Unauthenticated).

AC-009-002
Given a Donation NOT in PENDING status (e.g. already SUCCEEDED, EXPIRED, or CANCELLED)
When any actor attempts to create a Payment against it
Then the request is rejected with a typed validation exception and no Payment row is created.

AC-009-003
Given a valid Tripay/Xendit/Stripe webhook with a correct signature, a known provider_reference,
  and matching amount/currency
When it is received
Then the pipeline in "Webhook Security" runs to completion, the Payment transitions per "State
  Machines," a `payment.succeeded` or `payment.failed` audit event is recorded (CRITICAL), and (on
  success or failure) the owning Donation's transition surface is invoked in the same transaction.

AC-009-004
Given a webhook with an invalid/forged signature
When it is received
Then `processing_result = REJECTED_INVALID_SIGNATURE`, the event is recorded, NO Payment state
  changes, a `webhook.verification_failed` audit event is recorded, and no sensitive diagnostic
  detail is returned in the response.

AC-009-005
Given a webhook whose reported amount does not equal the resolved Payment's own amount_minor
When it is received
Then `processing_result = REJECTED_AMOUNT_MISMATCH`, no state change, audit event recorded.

AC-009-006
Given a webhook whose provider_reference does not match any existing Payment row
When it is received
Then `processing_result = REJECTED_UNKNOWN_REFERENCE`, the event is recorded with payment_id
  NULL, and NO Payment row is created.

AC-009-007
Given a Payment already in SUCCEEDED status
When a duplicate webhook reporting the same success is received
Then `processing_result = DUPLICATE`, the event is recorded, and no further state change or
  duplicate Donation-transition invocation occurs.

AC-009-008
Given two Payment-creation requests carrying the identical Idempotency-Key header and identical
  donation_id/provider/amount_minor/currency
When both are submitted
Then only the first creates a Payment row and calls the provider adapter; the second returns the
  SAME existing Payment (no second row, no second provider API call).

AC-009-009
Given two Payment-creation requests carrying the identical Idempotency-Key header but a different
  donation_id or provider
When both are submitted
Then only the first creates a Payment row; the second is rejected with a typed conflict exception.

AC-009-010
Given a Manual Bank Transfer Payment with submitted evidence
When an actor holding `payment.manual_transfer.verify` + ORGANIZATION scope + `financial_approver`
  Business Authority approves it
Then the Payment transitions to SUCCEEDED, verified_by_principal_id is set, a
  `manual_transfer.approved` audit event (CRITICAL) is recorded, and the owning Donation
  transitions to SUCCEEDED in the same transaction.

AC-009-011
Given the same Manual Bank Transfer Payment
When an actor WITHOUT `financial_approver` Business Authority (but otherwise holding the
  permission/scope) attempts to approve it
Then the request is denied and the Payment remains unchanged.

AC-009-012
Given a Payment with a provider-supplied expires_at in the past, still in PENDING status
When the expiry sweep (`scheduler.payment-expiry-sweep`, System Principal) runs
Then the Payment transitions to EXPIRED, expired_at is set, and a `payment.expired` audit event
  is recorded; the owning Donation is UNAFFECTED (its own independent clock).

AC-009-013
Given a Payment relying on the internal fallback expiry config, while that config value is unset
When the expiry sweep runs
Then the Payment does NOT transition to EXPIRED, regardless of how much time has passed.

AC-009-014
Given a Payment in SUCCEEDED, FAILED, EXPIRED, or CANCELLED status
When any actor or provider event attempts any further status transition
Then the transition is rejected with a typed conflict exception and no state change occurs.

AC-009-015
Given a Donation still referenced by at least one Payment
When any actor attempts to delete that Donation
Then the deletion is rejected at the database level (RESTRICT), proven by a raw query bypassing
  the service layer.

AC-009-016
Given a Payment created for provider 'stripe' with a currency Stripe does not support for this
  platform's configuration
When creation is attempted
Then the request is rejected with a typed validation exception and no Payment row is created, no
  provider API call is made.

AC-009-017
Given a manual-transfer evidence file uploaded by a donor
When any unauthenticated or unauthorized actor attempts to retrieve it directly
Then the request is denied; no permanent public URL ever resolves to the file.

AC-009-018
Given a forced audit-append failure during a `payment.succeeded` write
When the failure occurs
Then the Payment status write AND the Donation transition invocation both roll back (CRITICAL/
  MUTATION_ATOMIC fail-closed behavior) — the Payment remains in its PRE-transition status.

AC-009-019
Given two concurrent, identical webhook deliveries for the same Payment racing against real MySQL
When both execute
Then exactly one produces the state transition; the other observes DUPLICATE; no lost update, no
  double-invocation of the Donation transition surface.

AC-009-020
Given a webhook-driven or sweep-driven Payment transition
When the resulting audit event's actor is inspected
Then it records the correct, distinct Integration Principal (webhook) or System Principal
  (sweep/consequence-invocation) identity — never a human Principal, never the two conflated.

AC-009-021
Given a Donation with an existing PENDING Payment Attempt
When any actor (donor, guest, admin, or a concurrent duplicate request) attempts to create a
  second Payment Attempt against the same Donation
Then the request is rejected at the DB level (`UNIQUE(donation_id, active_slot)` — HD-IMP009-01,
  `FINAL / LOCKED`) and no second row is created; once the first attempt reaches ANY terminal
  state, a subsequent creation request succeeds.

AC-009-022
Given a Payment whose owning Donation has already reached a terminal state (EXPIRED, CANCELLED,
  or SUCCEEDED via a prior sequential Payment Attempt)
When a provider (webhook or poll) reports a late SUCCEEDED or FAILED outcome for this Payment
Then the Payment's own status is updated to reflect the verified provider fact, the Donation is
  NOT mutated, and a `payment.donation_transition_rejected` audit event (CRITICAL) is recorded for
  mandatory human/admin review (`FINAL / LOCKED`, HD-IMP009-02) — no refund, credit, or
  reinstatement is invented.

AC-009-023
Given a Manual Bank Transfer Payment with zero `manual_transfer_evidence` rows
When an actor holding `payment.manual_transfer.verify` attempts to record review_outcome =
  APPROVED
Then the request is rejected (`FINAL / LOCKED`, HD-IMP009-06 — evidence is mandatory) and the
  Payment remains unchanged; the same actor MAY still record REJECTED with zero evidence rows
  present.

AC-009-024
Given a Manual Bank Transfer Payment with submitted evidence whose declared_amount_minor does not
  equal payments.amount_minor
When an authorized actor reviews it
Then the only valid outcome recorded is AMOUNT_MISMATCH_HOLD (`FINAL / LOCKED`, HD-IMP009-07); the
  Payment remains PENDING, the owning Donation is untouched, and neither payments.amount_minor
  nor donations.amount_minor is mutated.

AC-009-025
Given a Manual Bank Transfer Payment still in an evidence-eligible state (PENDING, no recorded
  review_outcome)
When the same donor/guest submits a SECOND `manual_transfer_evidence` row
Then the submission succeeds as a new, additional row (`FINAL / LOCKED`, HD-IMP009-08); the first
  row is not overwritten; once the Payment reaches a terminal state or already carries a recorded
  review_outcome, a further submission attempt is rejected.
```

## Forbidden Changes

```
No change to Donation's own locked schema, lifecycle, BR-1..BR-14, or idempotency contract
  (IMP-008) — consumed as-is (see "Authority").
No change to CampaignEligibilityResolver, Money, or CurrencyMinorUnits (IMP-007) — consumed as-is.
No change to `campaigns`, `programs`, `funds`, or `donations` schema.
No Ledger, Commission, Withdrawal, Refund, or Reconciliation table or write path.
No new ScopeType value added to the closed Data Scope taxonomy.
No new Business Authority Type added to the closed taxonomy (`financial_approver` reused for
  manual-transfer verification, not a new `payment_verifier`/`payment_approver` type).
No new `AuditActorKind` enum value; no use of `AuditActorKind::System` to represent a guest actor.
No unauthorized reuse of `AuditActorKind::Unauthenticated` beyond
[ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md)'s own explicitly
  enumerated two-event scope (`payment.attempt_created`, `manual_transfer.evidence_submitted`) —
  `FINAL / LOCKED`, HD-IMP009-12; a third IMP-009 event, or reuse by any other future IMP, requires
  its own further ADR amendment, never silent analogy. ADR-002 itself is NOT broadened generically
  (see "Audit" — BR-16).
No re-introduction of a Campaign-eligibility re-check at Payment-success time — IMP-008's BR-1
  (creation-time-only check) is preserved exactly.
No Midtrans adapter or reference without an approved ACR (MASTER-REQUIREMENTS.md §9).
No automated Moota-to-Payment matching mechanism (IMP-017's own, later concern).
No card PAN/CVV/expiry collection, transmission, or storage through this platform's own backend.
No mandatory Redis/Supervisor/PM2/WebSocket/Node runtime introduced for any Payment Hub concern.
No provider secret/credential ever stored in plaintext, logged, or returned via any API response.
No more than ONE ACTIVE (PENDING/REQUIRES_ACTION) Payment Attempt per Donation, ever (`FINAL /
  LOCKED`, HD-IMP009-01) — no service-layer-only enforcement without the DB-level constraint.
No guest bearer-token, magic-link, or identifier-as-credential resume/retry mechanism of any kind
  (`FINAL / LOCKED`, HD-IMP009-04, mirroring HD-IMP008-05B).
No stored/tokenized payment method, payment mandate, or off-session/automatic recurring charge
  capability (`FINAL / LOCKED`, HD-IMP009-10).
No automatic APPROVED/REJECTED resolution of a Manual Transfer amount mismatch, and no refund/
  credit/balance/allocation/amount-mutation invented in response to one (`FINAL / LOCKED`,
  HD-IMP009-07).
No Manual Transfer approval without at least one submitted evidence row (`FINAL / LOCKED`,
  HD-IMP009-06).
No forced, reopened, or directly-mutated Donation transition when Donation is already in an
  incompatible terminal state at the time a terminal Payment outcome arrives (`FINAL / LOCKED`,
  HD-IMP009-02/HD-IMP009-11) — the Payment fact is still always recorded.
No Ledger posting, Journal Entry, or Authorized Financial Consequence construction authorized by
  HD-IMP009-11 — IMP-010/IMP-011 boundaries remain fully intact.
```

## External Verification

```
Chart-of-Accounts / accounting treatment mapping for a succeeded Payment — EXTERNAL VERIFICATION,
  IMP-010/011's own concern, not invented here (IMPLEMENTATION-GOVERNANCE.md).
Exact per-provider status vocabulary (Tripay/Xendit/Stripe) — this specification's mapping tables
  in "State Machines" are illustrative and MUST be verified against each provider's own current,
  authoritative API documentation at implementation time; they are not asserted as exhaustive or
  permanently accurate given providers may change their own vocabulary.
Payment expiry fallback DURATION VALUE — by design (mirroring HD-IMP008-04's own precedent), no
  default is asserted; the mechanism is fully specified, only the eventual numeric config value is
  a future, separate operational decision.
File upload MIME allow-list and size-limit exact values — implementation-time parameters, not
  invented here without evidence.
```

## Human Decisions (Resolved)

All twelve Human Decisions raised by this specification (the original ten Open Human Decisions,
OHD-IMP009-01 through OHD-IMP009-10, from this document's initial submission for Human Spec
Approval, commit `21fef30`) are now resolved, `FINAL / LOCKED`, by explicit Human Decision. None
were decided by Claude.

```
HD-IMP009-01 — FINAL / LOCKED (resolves OHD-IMP009-01)
Decision:    Maximum ONE ACTIVE Payment Attempt (status PENDING or REQUIRES_ACTION) per Donation.
             A new Payment Attempt may be created only after the previous attempt reaches an
             eligible terminal/non-active state (SUCCEEDED, FAILED, EXPIRED, or CANCELLED).
             Concurrency MUST enforce this invariant at DB/domain level, not merely a
             service-layer check.
Materialized in:  §Domain Model ("Donation relationship", "One-ACTIVE-attempt enforcement" —
             the `active_slot` generated column + `UNIQUE(donation_id, active_slot)` mechanism),
             §Database Impact (`payments` schema), §Concurrency, §Authorization ("Payment retry"),
             §Donation Integration, §Business Rules BR-19, §Testing Requirements, AC-009-021.

HD-IMP009-02 — FINAL / LOCKED (resolves OHD-IMP009-02)
Decision:    A late provider success or failure MUST NOT be discarded. The Payment records the
             verified provider fact (SUCCEEDED or FAILED) regardless of the owning Donation's
             current state. The Donation-transition request is made ONLY through the canonical
             IMP-008 transition surface, and succeeds only when Donation's own state machine
             permits it (i.e. Donation is still PENDING). If the Donation is already in an
             incompatible terminal state, Payment MUST NOT force, reopen, or directly mutate
             Donation — the condition is recorded and raised for controlled exception/manual
             review instead. No accounting treatment (refund, credit, reinstatement, ledger
             adjustment) is invented by IMP-009 for this case.
Materialized in:  §State Machines (duplicate/out-of-order-event rules), §Donation Integration
             ("How a successful Payment affects Donation", "Failed Payment behavior"),
             §Financial Boundary, §Failure Semantics ("Donation transition invocation fails"),
             §Audit (`payment.donation_transition_rejected`), §Business Rules BR-8/BR-20,
             §Testing Requirements, AC-009-018/022.

HD-IMP009-03 — FINAL / LOCKED (resolves OHD-IMP009-03)
Decision:    No hard-coded lifetime maximum number of Payment Attempts per Donation. Abuse/rate
             controls MAY be configurable (an operational, not domain-contract, concern).
             HD-IMP009-01's one-ACTIVE-attempt-at-a-time invariant still applies regardless of how
             many total (terminal) attempts have accumulated.
Materialized in:  §Domain Model, §Donation Integration, §Authorization, §Business Rules BR-19.

HD-IMP009-04 — FINAL / LOCKED (resolves OHD-IMP009-04)
Decision:    No guest self-service resume/retry mechanism based solely on Donation ID, Payment
             ID, ULID, email, or any other identifier — none of these are authentication secrets.
             IMP-009 SHALL NOT introduce a new guest bearer-token/magic-link recovery mechanism.
             An abandoned guest attempt is recovered only by: (a) the still-ACTIVE Payment Attempt
             naturally reaching EXPIRED via the Payment expiry sweep (HD-IMP009-09), which then
             frees the HD-IMP009-01 one-ACTIVE-attempt slot for a new attempt; or (b) an authorized
             organization-scoped support-controlled process (the SAME `payment.cancel` ORGANIZATION-
             scope admin path already defined in "Authorization"), where already supported —
             never a new guest-facing mechanism.
Materialized in:  §Authorization ("Public/guest Payment creation", "Donor/guest viewing own
             Payment(s)"), §Donation Integration, §Concurrency ("Payment retry vs late success"),
             §Forbidden Changes.

HD-IMP009-05 — FINAL / LOCKED (resolves OHD-IMP009-05)
Decision:    The Xendit adapter baseline targets the **PaymentRequest API** (Xendit's newer,
             unified, per-channel API), not the hosted Invoice API. Xendit-specific concepts
             (Payment Request, Payment Method, Payment Session, capture semantics) remain fully
             isolated behind the provider adapter interface — no Xendit-specific state or
             vocabulary leaks into the canonical Payment state machine. The concrete current
             endpoint/version/field requirements MUST be verified against Xendit's own
             authoritative API documentation at implementation time, not assumed frozen by this
             specification.
Materialized in:  §Xendit (status-normalization mapping revised — see below), §External
             Verification, §Provider Adapter Architecture.

HD-IMP009-06 — FINAL / LOCKED (resolves OHD-IMP009-06)
Decision:    Proof-of-transfer evidence is MANDATORY for any Manual Bank Transfer flow that
             requires human verification — a Payment cannot be manually APPROVED through that
             verification flow without at least one `manual_transfer_evidence` row present.
             Evidence remains private and access-controlled (see "File Security").
Materialized in:  §Manual Transfer, §Domain Model, §Authorization ("Manual transfer verification"),
             §Business Rules BR-21, AC-009-023.

HD-IMP009-07 — FINAL / LOCKED (resolves OHD-IMP009-07)
Decision:    Manual Transfer underpayment or overpayment (declared/observed transferred amount
             != `payments.amount_minor`) MUST NOT automatically produce canonical SUCCEEDED/PAID
             Donation treatment. `payments.amount_minor` (and `donations.amount_minor`) remain
             immutable — no amount is ever adjusted to match an actual transferred amount. An
             amount mismatch enters a controlled exception/manual-review path, distinct from an
             ordinary APPROVE/REJECT decision. IMP-009 MUST NOT invent refund, credit, balance,
             allocation, donation-amount mutation, or accounting treatment for this case — those
             consequences belong entirely to later governed domains (IMP-016 Refund and/or a
             future authorized reconciliation-adjacent mechanism).
Materialized in:  §Manual Transfer ("Amount mismatch"), §Domain Model
             (`manual_transfer_evidence.review_outcome` third value), §Business Rules BR-12/BR-22,
             §Failure Semantics, §Testing Requirements, AC-009-024.

HD-IMP009-08 — FINAL / LOCKED (resolves OHD-IMP009-08)
Decision:    Manual Transfer evidence MAY be resubmitted while the owning Payment remains in a
             state eligible for evidence submission (PENDING, evidence not yet reviewed/approved/
             rejected). Each submission is its own immutable, auditable
             `manual_transfer_evidence` row — a resubmission NEVER overwrites historical evidence.
             A Payment in a terminal or otherwise ineligible state (already SUCCEEDED, FAILED,
             EXPIRED, CANCELLED, or already under a recorded APPROVED/REJECTED review outcome)
             rejects any further evidence submission.
Materialized in:  §Manual Transfer ("Duplicate proof"), §Domain Model (`manual_transfer_evidence`
             — already an append-only, multi-row-per-Payment table by design), §Authorization,
             §Business Rules BR-23, AC-009-025.

HD-IMP009-09 — FINAL / LOCKED (resolves OHD-IMP009-09)
Decision:    Payment expiry fallback duration is CONFIGURABLE — no hard-coded business duration
             belongs in the domain contract. When a provider supplies an authoritative expiry
             (Tripay `expired_time`, Xendit's own PaymentRequest expiry field), it is preserved/
             normalized as-is. For flows/providers requiring the internal fallback (Manual
             Transfer; Stripe, which has no native expiry), the deployment MUST supply valid
             configuration before the sweep acts — no numeric default is asserted by this
             specification. Donation expiration (IMP-008) remains entirely separate from Payment
             expiration (this IMP) — the two clocks are never conflated.
Materialized in:  §Expiration (mechanism already specified exactly this way — this decision
             confirms the recommended default without change), §Manual Transfer, §Stripe,
             §Database Impact (`config('payment.pending_expiry_minutes')`).

HD-IMP009-10 — FINAL / LOCKED (resolves OHD-IMP009-10)
Decision:    Recurring Donation v1 is MANUAL-PER-CYCLE from the Payment Hub's perspective — every
             Recurring Occurrence's generated Donation goes through the ordinary donor-initiated
             Payment-creation flow, identical to a one-time Donation. IMP-009 SHALL NOT store
             reusable/tokenized payment credentials, payment mandates, or any off-session charge
             authorization, and SHALL NOT implement automatic/background recurring charging.
             Future auto-charge capability requires its own separate, explicitly governed
             specification and change control — it is not a silent future extension of this IMP.
Materialized in:  §Donation Integration ("Recurring Donation occurrence interaction"), §Out of
             Scope, §Forbidden Changes, §Stripe ("No card data storage" — reinforced, not
             loosened, by this decision).

HD-IMP009-11 — FINAL / LOCKED (new decision — resolves this specification's own "Architectural
Escalation" finding from its initial submission)
Decision:    Payment success/failure MAY request a Donation state transition ONLY through the
             EXISTING canonical IMP-008 transition surface, invoked using the applicable
             registered System Principal (`system.payment-outcome-consequence`) or, where the
             triggering event is itself a verified provider webhook, the applicable registered
             Integration Principal for the receipt step and the System Principal for the
             transition-invocation step, kept distinct per "System Principal". Payment MUST NOT
             directly update `donations.status` or any other Donation column, and MUST NOT bypass
             IMP-008's own state machine/validation. The canonical posting chain's ordering is
             confirmed exactly as this specification already described: Business State Transition
             (this invocation) remains strictly BEFORE Authorized Financial Consequence
             (IMP-011's own, later construct). This decision does NOT authorize Ledger posting of
             any kind — IMP-010/IMP-011's boundaries remain fully intact; IMP-009 continues to
             construct no Ledger-related object and call no Ledger-related code.
Materialized in:  §Financial Boundary (escalation formally resolved — see note below), §Donation
             Integration, §System Principal, §Ledger Boundary, §Business Rules BR-8.

HD-IMP009-12 — FINAL / LOCKED (new decision — resolves this specification's own "New ADR Required"
finding from its initial submission)
Decision:    A new, narrowly-scoped ADR — [ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md)
             — governs `AuditActorKind::Unauthenticated` for exactly two explicitly enumerated
             IMP-009 guest events (`payment.attempt_created`,
             `manual_transfer.evidence_submitted`), each with its own fixed, registered
             `execution_context` constant, `actor_principal_id = NULL`, consistent with IMP-004's
             existing mechanism and ADR-002's established Category-2 pattern. This is explicitly
             NOT a generic broadening of ADR-002 itself, and it MUST NOT become a generic
             unauthenticated-audit bucket applicable to unrelated domains — any further IMP-009
             event, or any other future IMP's event, needing this treatment requires its own
             further ADR amendment, never silent reuse.
Materialized in:  [ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md)
             (full decision record); §Audit (guest-actor events section), §Business Rules BR-16,
             §Forbidden Changes.
```

**Architectural Escalation — RESOLVED.** HD-IMP009-11 confirms, as an explicit Human Decision
rather than an AI-authored interpretation, that IMP-009 is the correct caller of IMP-008's
System-Principal-gated Donation transition surface (see "Financial Boundary", "Donation
Integration"). No change to this specification's already-designed mechanism was required — the
decision authorizes the design as drafted.

**New ADR Required — RESOLVED.** [ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md)
is created, narrowly scoped to exactly the two events HD-IMP009-12 enumerates, and is a
prerequisite recorded under "Implementation Handoff Requirements" alongside the other gating
steps.

## Open Human Decisions

**NONE.** All twelve Human Decisions raised by this specification (HD-IMP009-01 through
HD-IMP009-12) are resolved — see "Human Decisions (Resolved)" above. A full re-audit of this
document (per the integration task's required checklist: one-ACTIVE-attempt invariant,
late-success behavior, guest security, Payment idempotency, manual-transfer evidence lifecycle,
amount-mismatch behavior, Payment expiry, recurring manual-per-cycle boundary, Donation-transition
boundary, Donation != Payment, Payment != Ledger, Refund boundary, Reconciliation/Moota boundary,
audit actor semantics, shared-hosting compatibility) found no genuine NEW unresolved business
ambiguity introduced by integrating HD-IMP009-01..12 — each decision's own text was specific
enough to close the corresponding OHD without leaving a residual gap (see the cross-references in
each entry above, and the corresponding updates throughout "Domain Model", "Manual Transfer",
"Xendit", "Concurrency", "Donation Integration", "Business Rules", "Testing Requirements", and
"Acceptance Criteria"). This specification therefore satisfies
[docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)'s "Definition
of Ready" checklist item "No unresolved Human Decision" in full.

## Out of Scope

See "Out of Scope" above (positioned earlier per this document's section ordering; restated here
per the task's own required section list for completeness):

```
Ledger Foundation (IMP-010) and its implementation.
Financial Consequence Posting (IMP-011) implementation.
Operational Fee (IMP-012).
Commission (IMP-014).
Withdrawal (IMP-015).
Refund business workflow (IMP-016) — only a provider-capability interface point is exposed.
Moota reconciliation (IMP-017).
Partner settlement / distribution.
Receipt / compliance documents.
Notification implementation (IMP-024) — only canonical domain events are emitted.
REST API program beyond the required same-app route contracts (IMP-025).
Flutter/mobile implementation.
```

## Implementation Handoff Requirements

Before Muse/Claude Code implementation of this specification may proceed:

```
1. Human Spec Approval of this document (see "Implementation Ownership").
2. GOV-MM-002 Claude Per-IMP Model Binding for IMP-009 recorded as BOUND
   (MULTI-MODEL-OWNERSHIP.md "Mission-Critical Claude Stages").
3. DONE — all twelve Human Decisions (HD-IMP009-01 through -12) are resolved by explicit Human
   Decision (see "Human Decisions (Resolved)"); no unresolved Human Decision remains (see "Open
   Human Decisions": NONE).
4. DONE — [ADR-003](../adr/ADR-003-payment-hub-unauthenticated-actor-scope-amendment.md) is
   created and ACCEPTED, explicitly authorizing `AuditActorKind::Unauthenticated` for
   `payment.attempt_created` and `manual_transfer.evidence_submitted` (HD-IMP009-12, see "Audit" —
   BR-16), following the same Change-Control procedure ADR-002 itself followed
   (docs/00-governance/CHANGE-CONTROL.md).
5. Qwen Recon pass over the resulting BOUND/approved state (per V3 pipeline — MUST NOT run before
   or concurrently with Human Spec Approval, HD-V3-R2-01) — still pending; not performed by this
   integration task.
6. Exact per-provider status vocabulary (Tripay/Xendit's PaymentRequest API per HD-IMP009-05/
   Stripe) verified against each provider's own current API documentation at implementation time
   (see "External Verification").
7. File-upload MIME allow-list, size limit, and the Payment expiry fallback duration value(s)
   (HD-IMP009-09 — configurable, no default asserted) supplied as ordinary application
   configuration (see "External Verification").
```

## Definition of Done

Per [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md) — this
specification itself satisfies "Definition of Ready" (objective/scope/out-of-scope/architecture/
business-rules/security/DB-impact/acceptance-criteria all stated above); the ONE Definition-of-
Ready item NOT yet satisfied is "No unresolved Human Decision" (ten OHDs remain — see
"Implementation Handoff Requirements" item 3). Implementation itself is not DONE until every
"Definition of Done" checklist item is satisfied, including the Mandatory Financial Tests and
Mandatory RBAC Tests this specification's "Testing Requirements" section already maps onto.
