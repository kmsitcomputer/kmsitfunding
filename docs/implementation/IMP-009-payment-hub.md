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
                                   auto-charge — see OHD-IMP009-10.
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

Ten genuine business/architecture ambiguities were identified that no authoritative document
resolves (Master Requirements, locked architecture, IMP-003/004/007/008, RBAC/security docs, and
the Human Decision Register were all checked — see "Authority"). Each is raised in full under
"Open Human Decisions" below (OHD-IMP009-01 through OHD-IMP009-10) with a recommended default that
this specification does NOT itself adopt as binding. No business rule in this document depends on
an unresolved OHD being decided a particular way — where an OHD exists, the surrounding
architecture is deliberately built to accommodate either resolution without redesign (e.g. the
`payments` schema does not assume single-vs-multi concurrent attempts at the column level; the
enforcement lives in a service-layer rule keyed to OHD-IMP009-01's eventual answer).

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
                            time (MASTER-REQUIREMENTS.md §9: "Payment may have multiple
                            intents/attempts") — see "Concurrency" for how many may be
                            simultaneously ACTIVE (OHD-IMP009-01).

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
Processing outcome:         signature_valid (BOOLEAN, NOT NULL), processing_result (VARCHAR 24,
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
                            reviewed_at (DATETIME, NULLABLE), review_outcome (VARCHAR 16,
                            NULLABLE) — APPROVED | REJECTED, review_notes (VARCHAR 1000,
                            NULLABLE).
timestamps.

Immutable vs mutable:       file_path, mime_type, size_bytes, declared_* fields are set at
                            submission and never mutated (a resubmission creates a NEW row — see
                            OHD-IMP009-08). reviewed_by_principal_id/reviewed_at/review_outcome/
                            review_notes are set exactly once, at review time, and never mutated
                            afterward (mirrors Donation's own single-transition-per-field rule).

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
                                       Donation (see "Idempotency" / "Concurrency" /
                                       OHD-IMP009-01/03) — never resurrects a terminal Payment row,
                                       mirroring IMP-008's own terminal-Donation-state immutability
                                       (BR-7) applied here by the same principle.
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
  (window closed, no evidence ever submitted, or
    submitted but never reviewed)                 -> canonical EXPIRED

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

Xendit (adapter shape depends on OHD-IMP009-05 — Invoice API vs PaymentRequest API; mapping below
  assumes the Invoice API's documented status vocabulary as the illustrative default per the
  recommended-default direction in OHD-IMP009-05, NOT a locked choice):
  PENDING          -> canonical PENDING
  PAID / SETTLED   -> canonical SUCCEEDED
  EXPIRED          -> canonical EXPIRED
  (Invoice API has no explicit "FAILED" for a card decline within the hosted page — a declined
    card retry stays PENDING until the invoice itself expires; the adapter MUST NOT invent a
    FAILED mapping the provider does not actually emit)

Stripe (PaymentIntent status vocabulary):
  requires_payment_method,
  requires_confirmation,
  processing                                      -> canonical PENDING
  requires_action                                  -> canonical REQUIRES_ACTION (3-D Secure/OTP)
  succeeded                                        -> canonical SUCCEEDED
  canceled                                         -> canonical CANCELLED
  (Stripe PaymentIntents have no native "expired" status equivalent to Tripay/Xendit's invoice
    expiry — EXPIRED is reached exclusively through IMP-009's OWN internal expiry sweep, see
    "Expiration" / OHD-IMP009-09, never inferred from a Stripe-reported status)
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

Proof-of-transfer upload:     See OHD-IMP009-06 (mandatory vs optional) — NOT decided by this
                              specification. The schema (`manual_transfer_evidence`) supports
                              EITHER outcome without redesign: if mandatory, the Payment-creation
                              flow simply requires at least one `manual_transfer_evidence` row
                              before the Payment can move out of PENDING toward admin review; if
                              optional, an admin may approve directly against bank-statement
                              matching alone.

Manual verification /
  approval authority:         An authorized admin/staff actor holding `payment.manual_transfer.verify`
                              in ORGANIZATION scope AND the `financial_approver` Business Authority
                              Type (the closest fit in the CLOSED Authority Type taxonomy —
                              BUSINESS-AUTHORITY-MODEL.md; no new `payment_verifier`/
                              `payment_approver` Authority Type is invented — see "Authorization").
                              Reviews `manual_transfer_evidence` (if present) plus out-of-band bank
                              statement information, then records review_outcome = APPROVED or
                              REJECTED (see "Domain Model").

Rejection:                    Payment -> FAILED, failure_reason = 'MANUAL_REJECTED',
                              review_notes captured. Donor/guest is not automatically offered a
                              retry path beyond creating a new Payment Attempt against the still-
                              PENDING Donation (subject to OHD-IMP009-01/03).

Expiration:                   See "Expiration" / OHD-IMP009-09 — Manual Transfer has no provider-
                              supplied expiry; the internal fallback config value is REQUIRED for
                              this provider specifically (there is no other signal that would ever
                              move an unreviewed Manual Transfer Payment to EXPIRED).

Duplicate proof:              See OHD-IMP009-08 — NOT decided by this specification (resubmission-
                              allowed vs one-shot-immutable-evidence). The schema supports either:
                              `manual_transfer_evidence` is an append-only table (a resubmission is
                              always a NEW row, never an UPDATE of a prior row's file_path/
                              declared_* fields — those are immutable per "Domain Model" already);
                              OHD-IMP009-08 only decides whether the SERVICE LAYER permits creating
                              a second row against the same Payment at all.

Amount mismatch (over/under-
  payment):                   See OHD-IMP009-07 — NOT decided by this specification. Whatever the
                              resolution, the mechanism MUST NOT mutate `payments.amount_minor`
                              or `donations.amount_minor` (both immutable per their own domain
                              models) — a mismatch is recorded via
                              `manual_transfer_evidence.declared_amount_minor` (the donor's claim)
                              versus `payments.amount_minor` (the authoritative expected amount),
                              and resolved exclusively through the APPROVED/REJECTED review
                              decision, never a silent amount correction.

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
  identifier:                  Xendit's invoice/payment-request `id` -> `payments.provider_reference`.

Payment creation:              `createTransaction()` calls Xendit's create-transaction endpoint
                              (exact endpoint shape depends on OHD-IMP009-05).

Callback/webhook
  verification:                 Xendit signs callbacks via an `x-callback-token` header compared
                              against a configured callback verification token (a second secret,
                              distinct from the API key, stored alongside it in
                              `payment_provider_credentials` or a dedicated column — implementation
                              detail, but MUST be a secret, never a request-supplied value trusted
                              at face value). Constant-time comparison, same requirement as Tripay.

Status mapping:                See "State Machines" per-provider mapping (illustrative, pending
                              OHD-IMP009-05).

Amount/currency
  verification:                 same contract as Tripay: callback-reported amount/currency MUST
                              equal `payments.amount_minor`/`currency` before any transition;
                              mismatch -> `REJECTED_AMOUNT_MISMATCH`/`REJECTED_CURRENCY_MISMATCH`.

Expiration:                    Xendit invoices carry their own `expiry_date` ->
                              `payments.expires_at` directly.

Duplicate webhook:              Xendit's callback carries its own `id` (the invoice/event id) —
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

**OHD-IMP009-05** governs which Xendit API generation (hosted Invoice API vs the newer
per-channel Payment Methods/PaymentRequest API) this adapter targets — see "Open Human Decisions."
This specification's architecture (provider-neutral canonical states, adapter-isolated
provider-specific detail, `channel` as a free-form display field) accommodates either choice
without redesign; only the adapter's internal `createTransaction()`/`normalizeStatus()`
implementation differs.

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
                              Tripay/Xendit invoices do (see "State Machines" note) — EXPIRED is
                              reached only via IMP-009's own internal sweep (OHD-IMP009-09). A
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
support relies solely on this platform's own key + the single-active-attempt policy per
OHD-IMP009-01).

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
                                before evaluating how many ACTIVE Payment Attempts already exist
                                against it (see OHD-IMP009-01) and before the Payment row itself
                                is inserted — this fixed lock ORDER (Donation, then Payment) is
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
                                OHD-IMP009-02, the orphaned-payment case).
Payment retry vs late success:   creating a new Payment Attempt (retry) while an OLDER Payment
                                Attempt against the same Donation is still capable of reaching
                                SUCCEEDED is exactly the scenario OHD-IMP009-01 governs — the
                                locking discipline above is identical regardless of that policy's
                                eventual resolution; only the BUSINESS RULE of how many
                                simultaneously-PENDING/REQUIRES_ACTION Payments may exist changes.
Manual verification vs
  expiration:                    an admin's APPROVE/REJECT decision (see "Manual Transfer")
                                acquires the same Donation-then-Payment lock order; if the expiry
                                sweep already moved the Payment to EXPIRED before the admin's
                                decision commits, the admin's action is rejected as an invalid
                                transition (see "State Machines") and the admin UI must surface
                                this as a stale-state conflict, not silently overwrite EXPIRED.
Duplicate payment creation
  request:                       see "Idempotency" — the idempotency-key UNIQUE constraint is the
                                primary defense; the Donation-row lock acquired during creation
                                (above) additionally prevents a genuine race between two
                                DIFFERENTLY-keyed concurrent creation requests from both passing
                                an OHD-IMP009-01 "how many active attempts" check simultaneously.
Donation expiration vs
  Payment success:                Donation's OWN sweep (IMP-008, entirely separate job/config from
                                Payment's own expiry sweep) acquires ITS OWN Donation-row lock
                                independently. A genuine race between Donation's sweep and a
                                Payment success reaching the Donation Integration surface (see
                                "Donation Integration") is resolved by ordinary row-level locking:
                                whichever transaction commits first wins; the loser observes the
                                Donation already in a terminal state and is handled per BR-7
                                (IMP-008) / OHD-IMP009-02 (orphaned payment) as applicable.
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
Payment expiration source:     provider-supplied (Tripay `expired_time`, Xendit `expiry_date`) is
                                authoritative when present -> `payments.expires_at` set directly
                                from the provider's own value at creation time.
Internal fallback expiration:   REQUIRED for Manual Transfer (no provider signal exists at all) and
                                for any provider/scenario where no provider-supplied expiry is
                                returned (Stripe PaymentIntents — see "Stripe"). Mirrors IMP-008's
                                own HD-IMP008-04 pattern EXACTLY: a single, nullable configuration
                                value (e.g. `config('payment.pending_expiry_minutes')`, default
                                `null`) gates the sweep — while unset, no Payment of that
                                kind/provider is ever swept to EXPIRED. No numeric default is
                                asserted by this specification (see OHD-IMP009-09).
Late callback after Payment
  expiration:                    a provider webhook reporting SUCCEEDED/FAILED for an
                                ALREADY-EXPIRED Payment is an invalid transition per "State
                                Machines" (EXPIRED is terminal) — this is exactly the same shape of
                                problem as OHD-IMP009-02's Donation-level orphaned-payment case,
                                one layer down (a late success at the PAYMENT layer, arriving after
                                Payment-level expiry, rather than at the DONATION layer, arriving
                                after Donation-level expiry). Both cases are governed by
                                OHD-IMP009-02's eventual resolution, applied consistently at
                                whichever layer the lateness actually occurs.
Late success after Donation
  expiration:                    see OHD-IMP009-02 directly — this IS that scenario.
Manual transfer expiration:      see "Manual Transfer" — governed by the same internal-fallback
                                config value; while unset, an unreviewed Manual Transfer Payment
                                never auto-expires (an admin must still act, or it remains PENDING
                                indefinitely — the same "no invented default" posture IMP-008 took
                                for Donation).
Retry after expiration:          creating a NEW Payment Attempt against a Donation that is STILL
                                PENDING (Donation itself has not expired) after a PRIOR Payment
                                Attempt reached EXPIRED is always permitted, subject to
                                OHD-IMP009-01/03 — Payment-level expiration never terminates the
                                Donation itself; only Donation's OWN sweep (IMP-008) can do that.
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
                                subject to: the target Donation must exist, be PENDING, and (for a
                                guest Donation) the request must be within the SAME response/
                                redirect cycle the Donation-creation flow produced — see
                                OHD-IMP009-04 for the exact resume/retry boundary this
                                specification does NOT invent unilaterally.

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
                                response/redirect flow (see OHD-IMP009-04), never through a
                                bearer-token/identifier-as-credential mechanism (mirrors
                                HD-IMP008-05B's identical prohibition, applied here by the same
                                principle).

Admin/staff viewing Payments:    Authenticated AND permission `payment.view` AND scope
                                ORGANIZATION.

Payment retry (new Attempt
  against an existing PENDING
  Donation):                     same authorization shape as Payment creation above (guest or
                                authenticated, ownership-gated), subject to OHD-IMP009-01/03's
                                eventual resolution for HOW MANY/how often.

Donor/admin cancelling a
  PENDING/REQUIRES_ACTION
  Payment:                       Authenticated AND permission `payment.cancel` AND scope OWN
                                (donor) or ORGANIZATION (admin) AND resource state PENDING or
                                REQUIRES_ACTION — mirrors Donation's own cancel-authorization shape
                                (IMP-008 §Authorization/RBAC) exactly. Guest self-service
                                cancellation of a Payment is NOT supported, mirroring HD-IMP008-05B
                                by the same principle (no guest bearer-token/identifier-as-
                                credential mechanism is introduced here either).

Manual transfer evidence
  submission (donor/guest,
  own Payment):                   same ownership/guest-access shape as Payment creation — subject
                                to OHD-IMP009-06 (mandatory or not) and OHD-IMP009-08 (resubmission
                                policy).

Manual transfer verification
  (approve/reject):               Authenticated AND permission `payment.manual_transfer.verify`
                                AND scope ORGANIZATION AND Business Authority `financial_approver`
                                (the closest fit in the CLOSED Authority Type taxonomy —
                                BUSINESS-AUTHORITY-MODEL.md lists `financial_approver`,
                                `refund_approver`, `withdrawal_approver`, `distribution_approver`,
                                `zakat_authority`, `partner_verifier`, `beneficiary_verifier`; NO
                                new Authority Type is invented for Payment verification — reusing
                                `financial_approver` is the documented, no-new-taxonomy choice,
                                exactly as IMP-008 reused OWN/ORGANIZATION scope rather than
                                inventing a new ScopeType) AND resource state: the target Payment
                                is PENDING with at least the mandatory evidence present (if
                                OHD-IMP009-06 resolves to mandatory).

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
provider_config.credential_changed CRITICAL / MUTATION_ATOMIC | financial reference: false | actor:
                                   Human | payload: provider, mode, is_enabled (NEVER the secret
                                   value itself) — a "privileged governance/security operation"
                                   per Q26's explicit critical-category list.
```

Guest-actor events (`payment.attempt_created`, `manual_transfer.evidence_submitted`) follow
EXACTLY ADR-002's established mechanism (`AuditActorKind::Unauthenticated`, `actor_principal_id =
NULL`, a fixed registered `execution_context`) — but ADR-002's own text explicitly scopes its
widening to "the failed/incomplete authentication cases... plus `donation.created`'s guest case
only... no other IMP-008 event uses this widened category... any further use requires its own
future ADR, not silent reuse" (IMP-008-donation.md:1197-1199). **IMP-009 therefore requires its
OWN new ADR (or an amendment to ADR-002) authorizing `Unauthenticated` for
`payment.attempt_created` and `manual_transfer.evidence_submitted` before implementation** — this
specification does not perform that amendment itself (see "Implementation Handoff Requirements"
and the final report's "New ADR Required" field).

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

Precise relationship between Donation (IMP-008) and Payment (this IMP), derived exclusively from
what IMP-008's LOCKED contract already determines, with every genuinely undetermined point raised
as an OHD rather than invented:

```
One Donation may have MULTIPLE Payment attempts:      YES — MASTER-REQUIREMENTS.md §9 ("Payment
                                                        may have multiple intents/attempts") is
                                                        directly authoritative; `payments.donation_id`
                                                        is a plain FK with no uniqueness
                                                        constraint (see "Domain Model").
Payment retry creates a new Payment record:            YES, always a NEW `payments` row (see "State
                                                        Machines" terminal-state rule, "Provider
                                                        Adapter Architecture" retryBehavior) —
                                                        never mutates a terminal Payment row.
How many attempts may be simultaneously ACTIVE:        NOT determined by any authoritative source
                                                        — see OHD-IMP009-01.
Payment status vs Donation status:                     INDEPENDENT state machines. Donation's
                                                        PENDING/SUCCEEDED/FAILED/CANCELLED/EXPIRED
                                                        (IMP-008) is the donor-facing intent
                                                        lifecycle; Payment's PENDING/
                                                        REQUIRES_ACTION/SUCCEEDED/FAILED/EXPIRED/
                                                        CANCELLED (this IMP) is the per-attempt
                                                        provider-transaction lifecycle. A Donation
                                                        remains PENDING through zero, one, or many
                                                        non-terminal-for-Donation-purposes Payment
                                                        attempts, until exactly ONE Payment attempt
                                                        reaches a terminal outcome that IMP-009
                                                        forwards to Donation's transition surface
                                                        (see below) — or until Donation's OWN
                                                        independent expiry sweep (IMP-008) fires
                                                        first.
How a successful Payment affects
  Donation:                                              a Payment reaching SUCCEEDED, within the
                                                        SAME database transaction (see
                                                        "Concurrency"), invokes Donation's existing
                                                        System-Principal-gated
                                                        PENDING -> SUCCEEDED transition surface
                                                        (IMP-008-donation.md:605-614), using the
                                                        `system.payment-outcome-consequence`
                                                        System Principal (see "System Principal").
                                                        If the Donation is NOT in PENDING status at
                                                        that moment (already terminal, e.g. already
                                                        EXPIRED or CANCELLED), the transition
                                                        invocation is rejected by Donation's own
                                                        BR-7 — this is exactly OHD-IMP009-02's
                                                        orphaned-payment scenario, NOT silently
                                                        overridden here.
Failed Payment behavior:                                symmetric: a Payment reaching FAILED
                                                        invokes the SAME transition surface with a
                                                        failure outcome (Donation
                                                        PENDING -> FAILED), same same-transaction/
                                                        same-Principal/same OHD-IMP009-02 caveat.
                                                        Critically: a FAILED Payment attempt does
                                                        NOT, by itself, mean the Donation should
                                                        fail if the donor may still retry — see
                                                        "Failure Semantics" for the exact boundary
                                                        (a Payment FAILED does not automatically
                                                        drive the Donation to FAILED unless/until
                                                        the donor has no further retry path; this
                                                        is governed jointly by OHD-IMP009-01/03).
Expired Donation interaction:                            see "Expiration" — the two clocks are
                                                        independent; a Payment attempt created
                                                        against a Donation that expires mid-attempt
                                                        is handled per OHD-IMP009-02's late-outcome
                                                        resolution, and no NEW Payment attempt may
                                                        ever be created against an already-EXPIRED
                                                        (or otherwise terminal) Donation (validated
                                                        at Payment-creation time, resource state
                                                        PENDING required — see "Authorization").
Recurring Donation occurrence
  interaction:                                            EACH Recurring Occurrence's generated
                                                        Donation (IMP-008 — the generation ENGINE
                                                        itself remains unbuilt per IMP-008's own
                                                        "Out of Scope"/finalization record) is an
                                                        ordinary Donation from Payment's point of
                                                        view — it goes through the identical
                                                        Payment-creation/lifecycle contract as any
                                                        one-time Donation. Whether a Recurring
                                                        Plan pre-selects/stores a payment method for
                                                        automatic future charging (vs requiring
                                                        manual per-cycle payment) is NOT determined
                                                        by any authoritative source — see
                                                        OHD-IMP009-10.
Idempotency boundary:                                    see "Idempotency" — Donation's own
                                                        idempotency key (HD-IMP008-05A) and
                                                        Payment's own idempotency key (this IMP)
                                                        are deliberately separate, non-overlapping
                                                        mechanisms at two different creation
                                                        points.
Guest Donation payment behavior:                          see "Authorization" — guest Payment
                                                        creation is allowed (mirrors guest Donation
                                                        creation), subject to OHD-IMP009-04's
                                                        resume/retry boundary.
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

```
IMP-009 MUST NOT own double-entry Ledger posting, Journal Entries, financial accounting balances,
  commission accounting, withdrawal accounting, or refund accounting (per task instruction,
  restated as binding).
IMP-009 DOES own: the Payment aggregate itself, its state machine, and the invocation of
  Donation's (IMP-008's) already-defined System-Principal-gated Business State Transition surface
  upon a terminal Payment outcome (see "Donation Integration"). This IS the "Business Event ->
  Domain Validation -> Business State Transition" portion of the canonical posting chain
  (FINANCIAL-POSTING-BOUNDARY.md) — a webhook/callback IS the "Provider Event," its verification
  pipeline (see "Webhook Security") IS "Normalize / Validate," the Payment status write IS the
  "Owning Application Use Case" + "Domain Validation," and the resulting Donation transition IS
  the "Business State Transition." The chain STOPS there for IMP-009 — "Authorized Financial
  Consequence" onward (Ledger Posting Contract, Double-Entry Ledger) is entirely IMP-011/IMP-010's
  own, later concern.
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
  timestamps

  Indexes: donation_id, provider, status, provider_reference, expires_at
  Unique: ulid, idempotency_key, (provider, provider_reference) WHERE provider_reference IS NOT NULL
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
  processing_result (VARCHAR 24, NOT NULL)
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
    NULLABLE), review_outcome (VARCHAR 16, NULLABLE), review_notes (VARCHAR 1000, NULLABLE)
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
  terminal — OHD-IMP009-02):       the Payment's own status write (SUCCEEDED/FAILED) still
                                  commits (it accurately records what the PROVIDER reported); the
                                  Donation transition invocation's rejection is caught, and the
                                  outcome is recorded distinctly (e.g. a
                                  `payment.donation_transition_rejected` audit event, CRITICAL,
                                  never silently swallowed) for the mandatory admin/manual review
                                  OHD-IMP009-02's recommended default calls for — this is NOT
                                  treated as a Payment-processing error (the Payment truthfully
                                  succeeded/failed at the provider); it is a Donation-state
                                  conflict, flagged for human attention.
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
BR-8   A terminal Payment outcome (SUCCEEDED/FAILED) invokes Donation's existing System-Principal-
       gated transition surface (IMP-008) within the SAME database transaction as the Payment's
       own status write and audit append — never as a separate, later, best-effort step (see
       "Concurrency" / "Webhook Security" step 8-12).
BR-9   A webhook/callback is processed only after successful, constant-time signature/authenticity
       verification specific to its provider (see "Webhook Security" step 3) — no exception, no
       "trust in development mode" bypass may exist in the shipped implementation.
BR-10  An inbound webhook/callback event with an amount/currency mismatch against the resolved
       Payment's own amount_minor/currency is rejected before any state transition is considered
       (see "Webhook Security" step 6) — this is a security control, not merely data validation.
BR-11  Payment creation MUST include a client-provided idempotency key, scoped independently from
       Donation's own idempotency key (see "Idempotency"); a request without one is rejected
       before any Payment row is created.
BR-12  No manual-transfer approval/rejection may mutate payments.amount_minor or
       donations.amount_minor (see "Manual Transfer" / OHD-IMP009-07) — both remain immutable
       regardless of any donor-declared or admin-observed transferred amount.
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
       event records actor kind `AuditActorKind::Unauthenticated`, consistent with ADR-002's
       mechanism — contingent on IMP-009's own required ADR amendment authorizing this specific,
       new use of that mechanism (see "Audit" — this is a Change-Control precondition, not an
       Open Human Decision on substance).
BR-17  No new Business Authority Type, ScopeType, or AuditActorKind value is invented by this
       specification (see "Authorization" / "Audit" / "Forbidden Changes").
BR-18  Every Payment expiry/window value (per-provider fallback config, Manual Transfer's own
       window) defaults to `null`/unset and MUST NOT act while unset — mirrors HD-IMP008-04's
       identical "no invented default" contract (see "Expiration" / OHD-IMP009-09).
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
                           Payment drives the owning PENDING Donation to FAILED; an attempted
                           transition against an already-terminal Donation is rejected and
                           recorded distinctly (OHD-IMP009-02 scenario — test asserts the
                           documented "recorded for review, not silently dropped or forced"
                           behavior, whatever OHD-IMP009-02's eventual resolution specifies).
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
                           `financial_approver`) verification attempt denied.
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
                           the still-PENDING Donation (subject to OHD-IMP009-01/03's eventual
                           resolution — test asserts whatever the resolved policy specifies).
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
No unauthorized reuse of ADR-002's `Unauthenticated`-widening beyond its own explicitly stated
  scope — IMP-009's guest-actor events require their OWN new ADR/amendment before implementation
  (see "Audit" — BR-16, "Implementation Handoff Requirements").
No re-introduction of a Campaign-eligibility re-check at Payment-success time — IMP-008's BR-1
  (creation-time-only check) is preserved exactly.
No Midtrans adapter or reference without an approved ACR (MASTER-REQUIREMENTS.md §9).
No automated Moota-to-Payment matching mechanism (IMP-017's own, later concern).
No card PAN/CVV/expiry collection, transmission, or storage through this platform's own backend.
No mandatory Redis/Supervisor/PM2/WebSocket/Node runtime introduced for any Payment Hub concern.
No provider secret/credential ever stored in plaintext, logged, or returned via any API response.
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

## Open Human Decisions

Ten genuine, unresolved business/architecture ambiguities. None is decided by this specification.
Each recommended default is a suggestion only — the Human decides.

```
OHD-IMP009-01
QUESTION:              How many Payment Attempts against the SAME Donation may be simultaneously
                        ACTIVE (PENDING or REQUIRES_ACTION, i.e. not yet terminal)?
WHY IT MATTERS:          Determines whether creating a new Payment Attempt must first
                        cancel/supersede any existing active attempt, or whether multiple
                        concurrent attempts (e.g. a donor opening the payment page in two tabs, or
                        switching provider mid-flow) may race to succeed independently — directly
                        shapes the Payment-creation service logic and the "Concurrency" locking
                        contract's business-rule layer (the locking MECHANISM is the same either
                        way; only the rule it enforces differs).
EXISTING CONTRACT:       MASTER-REQUIREMENTS.md §9 states only "Payment may have multiple
                        intents/attempts" — over the DONATION'S lifetime, not a statement about
                        simultaneous concurrency.
OPTIONS:                 (a) exactly one ACTIVE attempt at a time — creating a new one
                        auto-cancels any prior active attempt; (b) multiple concurrent active
                        attempts permitted, first terminal outcome to arrive wins, the rest are
                        auto-cancelled at that point; (c) unlimited, no automatic cancellation,
                        left to donor/UI discipline.
ARCHITECTURAL CONSEQUENCE: (a) is simplest and lowest financial risk (impossible for two provider
                        transactions to both succeed for one Donation) but requires an explicit
                        auto-cancel step on new-attempt creation; (b)/(c) require the
                        Donation-transition invocation (BR-8) to correctly handle "the Donation is
                        already SUCCEEDED from a DIFFERENT sibling Payment" as a first-class,
                        expected outcome, not merely the OHD-IMP009-02 orphaned-payment edge case.
RECOMMENDED DEFAULT:      (a) — single active attempt at a time, auto-cancelling a prior active
                        attempt when a new one is created.

OHD-IMP009-02
QUESTION:              What happens when a provider reports a terminal SUCCEEDED (or FAILED)
                        outcome for a Payment whose owning Donation has ALREADY reached a
                        different terminal state (e.g. Donation already EXPIRED via its own
                        independent sweep, or already SUCCEEDED via a sibling Payment)?
WHY IT MATTERS:          This is the single highest financial-risk ambiguity in this
                        specification — it is the exact scenario where real money may have moved
                        at the provider but the platform's own Donation record can no longer
                        reflect it through the normal transition path (Donation is terminal, BR-7
                        forbids further transition).
EXISTING CONTRACT:       IMP-008 BR-7 makes Donation terminal states absolutely immutable — IMP-009
                        may not violate this. No authoritative source decides what happens to the
                        MONEY/record in this case.
OPTIONS:                 (a) record the Payment as SUCCEEDED/FAILED truthfully (it reflects what
                        the PROVIDER reported), leave the Donation in its terminal state
                        unchanged, and flag the case for MANDATORY human/admin review (a distinct
                        audit event + admin worklist item) — no automatic reinstatement, no
                        automatic refund; (b) automatically reinstate the Donation to SUCCEEDED
                        regardless of its prior terminal state (violates BR-7, rejected as an
                        option by this specification's own constraints, listed only for
                        completeness); (c) automatically trigger a refund-initiation signal
                        (premature — Refund is IMP-016's own workflow).
ARCHITECTURAL CONSEQUENCE: (a) requires an explicit "orphaned payment" audit event/admin surface;
                        does not touch Ledger/Refund. (c) would require IMP-009 to reach into
                        IMP-016's scope prematurely.
RECOMMENDED DEFAULT:      (a).

OHD-IMP009-03
QUESTION:              Is there a maximum number of Payment Attempts allowed against a single
                        PENDING Donation over its lifetime?
WHY IT MATTERS:          Bounds both abuse risk (unlimited retry attempts against one Donation)
                        and the operational cost of unlimited provider-transaction creation.
EXISTING CONTRACT:       None. HD-IMP008-04 mirrors this exact "no invented default" posture for a
                        different value (Donation expiry duration).
OPTIONS:                 (a) unlimited attempts, implicitly bounded only by the Donation's own
                        expiry (IMP-008); (b) a configurable maximum count (unset by default,
                        mirroring HD-IMP008-04's pattern exactly).
ARCHITECTURAL CONSEQUENCE: (b) requires an additional counter/check at Payment-creation time; (a)
                        requires none beyond what "Authorization"/"Donation Integration" already
                        specify (Donation must be PENDING).
RECOMMENDED DEFAULT:      (a) — unlimited, since no authoritative source names a limit and
                        Donation's own expiry already bounds the total abuse window.

OHD-IMP009-04
QUESTION:              How does a GUEST donor resume or retry a Payment Attempt after leaving the
                        original response/redirect flow (e.g. closing the browser mid-Tripay
                        checkout), given HD-IMP008-05B's absolute prohibition on any
                        bearer-token/identifier-as-credential guest access mechanism?
WHY IT MATTERS:          Without SOME resume mechanism, an abandoned guest Payment Attempt is
                        permanently unrecoverable by that guest (they would need to create an
                        entirely new Donation), which may be an acceptable trade-off or may be a
                        real product gap — this is a genuine, unresolved product/security
                        trade-off, not an engineering detail.
EXISTING CONTRACT:       HD-IMP008-05B (IMP-008, `FINAL/LOCKED`) forbids any guest
                        bearer-token/magic-link/identifier-as-credential mechanism for Donation
                        cancellation; this specification treats the same prohibition as applying
                        by the same principle to Payment resume/retry, absent a Human decision
                        saying otherwise.
OPTIONS:                 (a) no durable resume path exists at the platform level — a guest who
                        abandons the flow must create a brand-new Payment Attempt (new
                        idempotency key) against the still-PENDING Donation, discovered only by
                        navigating the original page flow again from the Donation's own
                        (equally non-durable) reference; (b) the PROVIDER's own hosted checkout
                        URL/session (Tripay/Xendit's own redirect URL, Stripe's own client_secret-
                        backed page) is treated as the resume mechanism, since it is a
                        provider-issued, time-boxed artifact, not a platform-issued credential —
                        the platform itself still exposes no separate guest bearer token; (c) a
                        future, explicitly authorized extension (e.g. an email-delivered
                        magic-link) is designed later, out of this IMP's scope entirely.
ARCHITECTURAL CONSEQUENCE: (b) requires no new platform-level auth mechanism at all — it relies
                        entirely on providers' own existing session handling; (c) would require
                        its own dedicated security review before any implementation.
RECOMMENDED DEFAULT:      (b).

OHD-IMP009-05
QUESTION:              Which Xendit API generation does the Xendit adapter target — the
                        hosted Invoice API (single hosted checkout page) or the newer, more
                        granular Payment Methods / PaymentRequest API (per-channel
                        token/charge flow)?
WHY IT MATTERS:          These are materially different integration shapes (hosted-page redirect
                        vs granular per-channel API calls with different webhook event
                        vocabularies) — the choice affects the Xendit adapter's internal design
                        substantially, though not this specification's provider-neutral canonical
                        model (see "Xendit").
EXISTING CONTRACT:       None — MASTER-REQUIREMENTS.md §9 names "Xendit" only, no API generation.
OPTIONS:                 (a) Invoice API (hosted checkout page, closer in integration shape to
                        Tripay's own hosted-checkout model, minimizes UI-surface exposure); (b)
                        Payment Methods/PaymentRequest API (more granular per-channel control,
                        closer in shape to Stripe's own PaymentIntent model, more implementation
                        surface).
ARCHITECTURAL CONSEQUENCE: (a) is simpler to implement and audit given this platform's
                        shared-hosting/minimal-surface constraints; (b) offers more UX control at
                        the cost of significantly more adapter complexity and a wider webhook
                        event vocabulary to normalize.
RECOMMENDED DEFAULT:      (a) — Invoice API.

OHD-IMP009-06
QUESTION:              Is proof-of-transfer (evidence) upload MANDATORY before a Manual Bank
                        Transfer Payment can be submitted for admin verification, or OPTIONAL
                        (an admin may verify by bank-statement matching alone)?
WHY IT MATTERS:          Directly shapes the Manual Transfer donor-facing flow and the admin
                        verification workflow's required inputs; the task's own instructions
                        explicitly caution against inventing this as mandatory without raising it.
EXISTING CONTRACT:       None.
OPTIONS:                 (a) mandatory — the strongest evidentiary anchor for a human-verified
                        flow; (b) optional — admin may approve/reject using out-of-band bank
                        statement information alone; (c) configurable per bank account/currency.
ARCHITECTURAL CONSEQUENCE: (a) requires the Payment-creation-to-verification flow to gate on at
                        least one `manual_transfer_evidence` row existing; (b)/(c) require the
                        admin verification UI to support approval with zero evidence rows present.
                        The schema (see "Domain Model") supports all three without redesign.
RECOMMENDED DEFAULT:      (a) — mandatory.

OHD-IMP009-07
QUESTION:              When a Manual Bank Transfer's donor-declared or bank-statement-observed
                        transferred amount does NOT match payments.amount_minor (over- or
                        under-payment), what does the admin verification action do?
WHY IT MATTERS:          This is a genuine financial-integrity decision directly touching
                        Donation's own LOCKED immutable amount_minor (IMP-008 BR-8) — no resolution
                        may silently "fix" either amount, so the decision materially shapes the
                        admin workflow and what a mismatch even MEANS operationally.
EXISTING CONTRACT:       IMP-008 BR-8 makes donations.amount_minor immutable; this specification's
                        own BR-2/BR-12 make payments.amount_minor immutable too — neither may be
                        adjusted to match an actual transferred amount.
OPTIONS:                 (a) reject/hold — an admin CANNOT approve a mismatched amount; the
                        Payment must be REJECTED and the donor/guest referred to support (a new
                        Payment Attempt, or a support-mediated resolution outside this
                        specification's scope, would be needed); (b) approve at the Payment's
                        OWN amount_minor regardless of the actual transferred amount (treats a
                        minor discrepancy as immaterial — risks silently absorbing a real
                        shortfall/overage with no accounting trail for the difference); (c)
                        approve only within a configurable tolerance band (e.g. rounding/fee
                        differences), reject outside it.
ARCHITECTURAL CONSEQUENCE: (a) is simplest and safest but may create donor-support friction for
                        trivial rounding differences; (c) requires a new configuration value and
                        explicit tolerance-comparison logic in the admin approval path.
RECOMMENDED DEFAULT:      (a) — reject/hold outside exact match, refer to support.

OHD-IMP009-08
QUESTION:              May a donor/guest submit MULTIPLE proof-of-transfer evidence rows against
                        the SAME Manual Transfer Payment (e.g. correcting a blurry photo), or is
                        evidence submission a one-shot action (a resubmission requires a NEW
                        Payment Attempt)?
WHY IT MATTERS:          Affects the donor-facing UX and the admin review queue's shape (reviewing
                        one vs potentially many evidence rows per Payment); also has a support-
                        workload dimension.
EXISTING CONTRACT:       None.
OPTIONS:                 (a) resubmission allowed — multiple `manual_transfer_evidence` rows may
                        exist per Payment while it is still PENDING/unreviewed, admin reviews the
                        most recent (or all); (b) one-shot — the first submission is final;
                        correcting a mistake requires a NEW Payment Attempt (mirrors "posted
                        financial evidence is immutable, corrections via a new record" applied
                        here).
ARCHITECTURAL CONSEQUENCE: (a) requires the admin UI to handle multiple evidence rows per Payment
                        and a "which one is current" convention; (b) is simpler but pushes minor
                        correction friction onto the donor (a whole new Payment Attempt for a
                        photo re-upload).
RECOMMENDED DEFAULT:      (b) — one-shot, mirroring the platform's general immutable-evidence
                        posture.

OHD-IMP009-09
QUESTION:              What Payment expiry fallback DURATION applies for Manual Transfer and any
                        provider without a native expiry signal (Stripe)?
WHY IT MATTERS:          Directly mirrors HD-IMP008-04's own resolved pattern for Donation expiry
                        — the MECHANISM is fully specified by this document (see "Expiration"),
                        only the numeric value is undetermined, and inventing one would violate
                        this platform's own established "no invented default" precedent.
EXISTING CONTRACT:       HD-IMP008-04 (IMP-008) establishes the exact precedent pattern this OHD
                        mirrors at the Payment layer.
OPTIONS:                 any specific duration is a Human/operator decision; the mechanism itself
                        (a single nullable config value, gating the sweep, `null` by default,
                        never acting while unset) is not itself in question.
ARCHITECTURAL CONSEQUENCE: none beyond what "Expiration" already specifies — this OHD exists
                        purely to avoid this specification silently asserting a number.
RECOMMENDED DEFAULT:      no default asserted (mirrors HD-IMP008-04 exactly) — value remains
                        unset until an operator/Human supplies one via ordinary configuration.

OHD-IMP009-10
QUESTION:              Does a Recurring Plan (IMP-008) pre-select and durably store a payment
                        method (e.g. a tokenized/saved card via Stripe) for automatic future
                        charging of each generated Occurrence's Donation, or does every
                        Occurrence require the donor to manually complete payment each cycle (no
                        stored payment method, no auto-charge)?
WHY IT MATTERS:          This is a materially different architecture — a stored/tokenized payment
                        method introduces card-on-file storage, its own PCI-scope considerations
                        (even tokenized), a "charge on schedule" background job, and failure/
                        retry semantics for an AUTOMATIC charge attempt with no donor present to
                        react to a REQUIRES_ACTION (3-D Secure) challenge. IMP-008 explicitly
                        deferred ALL Payment-attempt concerns for recurring Donations to IMP-009
                        without deciding this question itself.
EXISTING CONTRACT:       None. IMP-008's Recurring Occurrence model (IMP-008-donation.md:354,
                        "Recurring Plan -> Occurrence -> Donation -> Payment") only guarantees a
                        Donation is generated on schedule — it says nothing about how that
                        Donation's Payment gets completed.
OPTIONS:                 (a) manual-per-cycle in v1 — no stored payment method, no auto-charge;
                        each generated Occurrence's Donation goes through the ordinary donor-
                        initiated Payment-creation flow like any one-time Donation (the donor is
                        notified — via a future IMP-024 mechanism — and completes payment
                        manually each cycle); (b) stored payment method / card-on-file auto-charge
                        (Stripe-only realistically, since Tripay/Xendit/Manual Transfer have no
                        equivalent tokenization model for this platform), with its own background
                        charge job, retry policy, and 3-D-Secure-challenge-with-no-donor-present
                        handling.
ARCHITECTURAL CONSEQUENCE: (a) requires no new entity beyond what this specification already
                        defines and no PCI-tokenization scope expansion; (b) requires a new stored-
                        payment-method entity, explicit tokenization-scope authorization, and a
                        materially larger adapter surface for Stripe specifically (and an explicit
                        decision that Recurring Donation via Tripay/Xendit/Manual Transfer simply
                        cannot auto-charge, if (b) is chosen only for Stripe).
RECOMMENDED DEFAULT:      (a) — manual-per-cycle, no stored payment method, since no authorization
                        exists for the additional tokenization/PCI scope (b) would introduce.
```

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
3. All ten Open Human Decisions (OHD-IMP009-01 through -10) resolved by explicit Human Decision —
   mirroring IMP-008's own precedent of resolving every OHD before implementation began. This
   specification's architecture accommodates either resolution of each OHD without structural
   redesign (see each OHD's "Architectural Consequence"), but the SERVICE-LAYER business rules
   these OHDs govern cannot be implemented against an unresolved question.
4. A new ADR (or an amendment to ADR-002) explicitly authorizing `AuditActorKind::Unauthenticated`
   for `payment.attempt_created` and `manual_transfer.evidence_submitted` (see "Audit" — BR-16),
   following the same Change-Control procedure ADR-002 itself followed
   (docs/00-governance/CHANGE-CONTROL.md).
5. Qwen Recon pass over the resulting BOUND/approved state (per V3 pipeline — MUST NOT run before
   or concurrently with Human Spec Approval, HD-V3-R2-01).
6. Exact per-provider status vocabulary (Tripay/Xendit/Stripe) verified against each provider's
   own current API documentation at implementation time (see "External Verification").
7. File-upload MIME allow-list, size limit, and the Payment expiry fallback duration value(s)
   supplied as ordinary application configuration (see "External Verification").
```

## Definition of Done

Per [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md) — this
specification itself satisfies "Definition of Ready" (objective/scope/out-of-scope/architecture/
business-rules/security/DB-impact/acceptance-criteria all stated above); the ONE Definition-of-
Ready item NOT yet satisfied is "No unresolved Human Decision" (ten OHDs remain — see
"Implementation Handoff Requirements" item 3). Implementation itself is not DONE until every
"Definition of Done" checklist item is satisfied, including the Mandatory Financial Tests and
Mandatory RBAC Tests this specification's "Testing Requirements" section already maps onto.
