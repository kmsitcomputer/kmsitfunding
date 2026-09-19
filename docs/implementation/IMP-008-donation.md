# IMP-008 — Donation

## Status

READY (pending Human Spec Approval — implementation is NOT authorized by this document)

## Implementation Ownership

Per [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) —
no implicit/default model substitution is permitted; if the assigned model/owner is unavailable,
stop and report rather than substituting silently.

```
Stage Type:   V3 PIPELINE STAGE (IMP-008 forward, see MULTI-MODEL-OWNERSHIP.md "Amendment V3")
```

```
V3 Claude Lead Architect Identity Binding (distinct from GOV-MM-002 — see
MULTI-MODEL-OWNERSHIP.md "V3 Claude Lead Architect Identity Binding"):
  Role:                    Claude Lead Architect / Specification Owner
  Execution Environment:   Claude Code (VS Code extension; entrypoint claude-vscode)
  Display Model:           Claude Sonnet 5
  Exact Model Identifier:  claude-sonnet-5
  Verification Method:     this session's own runtime/environment-variable evidence
                            (CLAUDE_AGENT_SDK_VERSION=0.3.274, CLAUDE_CODE_ENTRYPOINT=claude-vscode)
                            plus the system-declared model identity for this session; not
                            guessed, not reused from a prior IMP's binding
  Verification Status:     VERIFIED
  Specification Revision:  this document's own initial commit (see git history for exact hash;
                            baseline branch state at authoring time was master @ e4d9de6)

FAIL CLOSED: the identity above was established before this specification was submitted for
Human Spec Approval, per the rule this binding exists to enforce.

Human Spec Approval:                     NOT YET APPROVED — see "Status" above. This document is
                                          the artifact submitted for that gate; the Approval
                                          fields below are intentionally left blank until Human
                                          Spec Approval is actually given (no fabricated
                                          approval — see MULTI-MODEL-OWNERSHIP.md "Human Spec
                                          Approval Gate").
Approval Statement:
Approval Scope:                          IMP-008 + this specification revision (see above)
Approval Evidence:

Qwen Recon Binding:                      qwen/qwen3.7-flash (VERIFIED — see
                                          MULTI-MODEL-OWNERSHIP.md "Model Binding Contract").
                                          NOT YET INVOKED — governed Recon must not occur before
                                          or concurrently with Human Spec Approval (HD-V3-R2-01).
                                          This document was authored using only the minimum
                                          repository context Claude needed to design the
                                          specification correctly (see "Architecture References"
                                          and inline citations throughout), not a governed,
                                          repository-wide Qwen Recon pass.
RECON Evidence:                          N/A — not started

Implementation Write Owner:              Muse Spark 1.3 Contributor
                                          (meta/muse-spark-1.3-contributor, VERIFIED) — DEFAULT.
                                          IMP-008 (Donation) is not one of the Mission-Critical
                                          Claude Stages (IMP-009/010/011/014/015/016/027 — see
                                          MULTI-MODEL-OWNERSHIP.md "Mission-Critical Claude
                                          Stages"), so no GOV-MM-002 Claude Implementation Binding
                                          block applies to this IMP's implementation role.
Implementation Commit/Diff:              N/A — not started
Test Evidence:                           N/A — not started

DeepSeek Independent Review Binding:     deepseek/deepseek-v4.1-flash (VERIFIED)
Findings:                                N/A — not started
Remediation Owner:                       N/A
Remediation Evidence:                    N/A
Full Regression:                         N/A

Codex Closure Audit:                     N/A — not started
Human Stage Gate:                        N/A — not started
Finalization Status:                     READY (specification phase only)
```

## Objective

Define the authoritative Donation domain: what a Donation is, its lifecycle, its relationship to
Campaign/Fund (IMP-007), its guest/authenticated/anonymity/recurring contracts, its database
schema, its authorization and audit requirements, and its explicit boundary against Payment,
Ledger, and every other later financial-domain stage — so that Muse Spark 1.3 Contributor can
implement IMP-008 from this document alone (plus Qwen's Recon file map) without inventing business
rules.

This document does **not** authorize implementation. It is the artifact submitted for Human Spec
Approval (see "Implementation Ownership" above).

## Source Requirements

- [docs/01-requirements/HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md)
  — Q5 (Hybrid Recurring Donation), Q6 (Public Anonymity Only), Q20 (IDR Base + Limited
  Multi-Currency), Q21 (Email as Canonical Login Identifier), Q22 (Hybrid Registration by Actor).
- [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §6
  (Donation and Recurring Donation are recognized domains; no detailed business rules supplied
  there — "not part of the source content supplied ... must not be invented here"), §7 (Financial
  Requirements — Ledger/Fund/Payment boundary), §9 (Payment Requirements — "Donation != Payment").
- [docs/02-architecture/MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §4 (Donation
  module: owns Donation, Recurring Plan, Recurring Occurrence, Donation lifecycle; does NOT own
  Payment or Ledger).
- [docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md](../02-architecture/FINANCIAL-POSTING-BOUNDARY.md)
  — canonical posting chain; Donation must stop before "Authorized Financial Consequence."
- [docs/03-database/DATABASE-INVARIANTS.md](../03-database/DATABASE-INVARIANTS.md) — Financial
  idempotency and immutable financial history apply to Donation's own schema.
- [docs/implementation/IMP-007-campaign-program-fund.md](IMP-007-campaign-program-fund.md) §8
  (Domain Model: Campaign/Fund schema), §8a (Money value object, HD-IMP007-02), §8b (Campaign
  donation-eligibility contract, HD-IMP007-03) — consumed, not redefined (see "Architecture
  References").
- [AGENTS.md](../../AGENTS.md) "Locked Decisions" and "Financial Rules".

## Architecture References

Locked, consumed as-is:

```
Donation != Payment
Payment != Ledger
Reconciliation != Ledger
Fund != mutable balance
Commission != Wallet
Canonical posting chain: Business Event -> Domain Validation -> Business State Transition ->
  Authorized Financial Consequence -> Registry -> Ledger Posting -> Journal -> Double-Entry Entries
Canonical authorization AND-chain (docs/05-rbac/RBAC-ARCHITECTURE.md): Authenticated AND
  Permission AND Applicable Data Scope AND Ownership/Subject Access Rule AND Required Business
  Authority AND Valid Resource State AND Required Approval State AND Required Authentication
  Assurance AND No Security Restriction. Default DENY.
```

Reused directly (not re-implemented):

```
CampaignEligibilityResolver::isDonationEligible(Campaign $campaign, ?DateTimeInterface $at = null):
  bool — app/Services/Campaign/CampaignEligibilityResolver.php (IMP-007, HD-IMP007-03). IMP-008
  MUST call this resolver at Donation-creation time and MUST NOT re-derive campaign.status /
  starts_at / ends_at logic independently.

Money::ofMinorUnits(int $amountMinor, string $currency): self — app/Support/Money/Money.php
  (IMP-007, HD-IMP007-02). IMP-008 stores and constructs monetary values exclusively through this
  value object; no second Money abstraction is created.

CurrencyMinorUnits::digitsFor(string $currency): int — app/Support/Money/CurrencyMinorUnits.php,
  backed by config/money.php (currently IDR, USD, EUR, GBP, JPY, KWD). An unregistered currency
  is rejected, never assumed to have 2 digits.

Principal (app/Models/Rbac/Principal.php, IMP-003) — the canonical authorization-identity row.
  donor_principal_id (see "Domain Model") references this table, never `users` directly, mirroring
  every other IMP-005/006/007 *_by_principal_id column.

AuditEventRegistry / AuditEventDefinition (app/Services/Audit/*, IMP-004) — the sole audit sink.
  No parallel audit mechanism is introduced (see "Audit Requirements").
```

## Scope

```
Donation aggregate (one-time donation to a Campaign — CAMPAIGN-ONLY, HD-IMP008-01A).
Guest (one-time only, HD-IMP008-01B) and authenticated donor donation entry points.
Public anonymity contract (Q6).
Donation lifecycle (PENDING/SUCCEEDED/FAILED/CANCELLED/EXPIRED) and its state-transition contract
  — including the abstract, authorization-gated transition surface IMP-009 (Payment) will later
  invoke, WITHOUT implementing IMP-009 itself.
Recurring Plan / Recurring Occurrence — authenticated-donor-only (HD-IMP008-01B), MONTHLY
  frequency only in v1 (HD-IMP008-02), donor self-service pause/resume/cancel + admin override
  (HD-IMP008-03), module-owned by IMP-008 per MODULE-OWNERSHIP.md §4. The Occurrence generation
  SCHEDULING/EXECUTION engine itself remains a separate build task (schema/ownership boundary only
  in this spec — see "Recurring Plan / Recurring Occurrence" and "Out of Scope").
Mandatory client-provided idempotency key for Donation creation (HD-IMP008-05A).
Fund/Campaign reference (read-only consumption of IMP-007 contracts).
Authorization (RBAC) for donor-owned and admin/staff-facing Donation actions.
Audit events for Donation lifecycle transitions, including the guest-actor
  `AuditActorKind::Unauthenticated` contract (HD-IMP008-06, ADR-002).
Database schema: `donations`, `donation_recurring_plans`, `donation_recurring_occurrences`.
Minimum public/donor-owned/admin web/application contracts needed to create and observe a
  Donation (not the full IMP-025 REST API).
```

## Out of Scope

Explicitly deferred to later IMPs — IMP-008 defines only the contract surface those stages will
consume, never their implementation:

```
IMP-009 Payment Hub:        Payment Intent/Attempt, provider adapters (Manual Bank Transfer,
                             Tripay, Xendit, Stripe), provider callback handling, payment
                             verification, retries/expiry of a payment ATTEMPT (as opposed to a
                             Donation's own PENDING->EXPIRED transition, which is IMP-008's).
IMP-010 Ledger Foundation:  Ledger Account, Ledger Journal, Double-Entry Entries.
IMP-011 Financial
  Consequence Posting:      Authorized Financial Consequence construction/posting itself — IMP-008
                             only exposes the Donation-side trigger surface a later consequence
                             may call; it does not post anything.
IMP-012 Operational Fee:    Fee calculation/deduction.
IMP-013 Fundraiser +
  Attribution:               Referral/attribution capture on a Donation. IMP-008 does not add a
                             referral/fundraiser column to `donations` — that relationship, if
                             required, is IMP-013's schema addition against this table via its own
                             migration, not invented here.
IMP-014 Commission:          Commission entitlement/calculation from a succeeded Donation.
IMP-015 Withdrawal:           Withdrawal of any balance.
IMP-016 Refund:               Refund of a succeeded Donation.
IMP-017 Reconciliation +
  Moota:                      Bank-mutation matching.
Any later IMP:                Campaign progress/collected-amount display aggregation and public
                             wiring (CampaignProjectionResolver currently explicitly excludes
                             collected amount/donor count "because IMP-008 does not exist" — this
                             document makes querying succeeded Donations possible, but wiring that
                             into the public Campaign page's presentation layer is a follow-on,
                             not part of this spec's acceptance criteria).
                             Full IMP-025 REST API surface for Donation.
                             Program-level and Fund-level direct donation (HD-IMP008-01A —
                             CAMPAIGN-ONLY for v1; a future authorized extension may add this).
                             Guest recurring donation and any guest bearer-token/magic-link
                             authorization architecture (HD-IMP008-01B — explicitly prohibited in
                             v1, not merely undecided).
                             Recurring frequencies other than MONTHLY (HD-IMP008-02 — the schema
                             must remain extensible to future frequencies without redesign, but no
                             non-MONTHLY value is implemented now).
                             Automatic occurrence-generation retry (HD-IMP008-03 — a FAILED
                             Occurrence is never auto-retried; only the plan's normal next
                             SCHEDULED occurrence proceeds).
                             Recurring Occurrence generation SCHEDULING/EXECUTION mechanism itself
                             (the actual cron/job implementation) — the schema and its now-locked
                             operational rules (HD-IMP008-02/03) are defined by this spec, but
                             building the engine is deferred as a separate, later implementation
                             task, not blocked on any remaining unknown.
```

## Affected Domains

```
Donation (new — this IMP)
Campaign (IMP-007, read-only consumption via CampaignEligibilityResolver + Campaign.fund_id)
Fund (IMP-007, read-only reference by campaign_id -> campaigns.fund_id; Donation never stores
  fund_id directly — see "Domain Model")
RBAC / Principal (IMP-003, read-only — donor_principal_id references principals.id)
Identity (IMP-002, read-only — guest email/name captured on Donation itself, since `users` has no
  display-name column to reuse; see "Domain Model" rationale)
Audit (IMP-004, write — new donation.* event definitions registered into the existing registry)
```

## Domain Model

### Donation aggregate — field-by-field derivation

```
Identity:                  BIGINT PK (internal) + ulid CHAR(26) unique (public-safe reference),
                            mirroring every other IMP-005/006/007 aggregate.

Donor relationship:        donor_principal_id (FK principals, RESTRICT, NULLABLE). NULL means a
                            guest donation. Guest allowed per this task's explicit instruction not
                            to force account creation, and per Q22 ("Donor -> self-registration
                            allowed", not required).

Guest/authenticated
  donor semantics:         guest_name (string 150, nullable), guest_email (string 255, nullable).
                            CHECK invariant (app-level guard, mirroring Principal::isConsistent's
                            own SQLite-exception pattern): donor_principal_id IS NOT NULL, OR
                            (guest_name IS NOT NULL AND guest_email IS NOT NULL). Exactly one path
                            is populated — never both, never neither.

Public display name:       donor_display_name (string 150, nullable), captured at donation time
                            for BOTH guest and authenticated donors. Rationale (not invented for
                            its own sake): `users` (IMP-002) has NO display-name column at all
                            (fillable is exactly ['email','password'] — verified against
                            app/Models/User.php) and IMP-008 is not authorized to add one to the
                            Identity domain. A guest donor needs a name captured somewhere
                            regardless (for receipt/reference purposes even before IMP-023
                            Receipt exists). Capturing a per-donation display name uniformly (not
                            a persistent identity attribute) avoids inventing an Identity-domain
                            change while still giving every Donation a way to render "from
                            <name>" publicly. When is_anonymous is true, this field is never
                            rendered publicly regardless of its value (see "Public Anonymity").

Campaign relationship:     campaign_id (FK campaigns, RESTRICT, NOT NULL). Every Donation targets
                            exactly one Campaign — CAMPAIGN-ONLY, `FINAL / LOCKED` per
                            HD-IMP008-01A. Program- or Fund-level direct donation (bypassing a
                            specific Campaign) is explicitly OUT OF SCOPE for v1: IMP-007 defines
                            donation eligibility (CampaignEligibilityResolver) only at Campaign
                            granularity — Program has no starts_at/ends_at/eligibility contract of
                            its own — so there is no authoritative resolver a Program- or
                            Fund-level Donation could consume without IMP-008 inventing one. A
                            future direct Program/Fund donation capability requires its own
                            explicit future authorized extension (HD-IMP008-01A), not a unilateral
                            IMP-008 addition.

Fund relationship:         NOT a direct column. Reached transitively via
                            Donation.campaign_id -> Campaign.fund_id (IMP-007). Storing fund_id
                            redundantly on Donation would duplicate Campaign's own reference and
                            risk drift if a Campaign's fund_id ever legitimately changes pre-PUBLISH
                            (IMP-007 BR-2) — avoided per "do not duplicate campaign eligibility
                            logic inside Donation," extended here to Fund reference.

Amount / currency:         amount_minor (BIGINT UNSIGNED, NOT NULL) + currency (CHAR 3, NOT NULL),
                            constructed and validated exclusively through
                            Money::ofMinorUnits()/CurrencyMinorUnits::digitsFor() (IMP-007,
                            HD-IMP007-02). No float column, no second Money abstraction, no
                            assumption of 2 decimal digits for every currency.

Donation intent/state:     status (VARCHAR 16) — see "State / Lifecycle".

Anonymity/public-display
  semantics:                is_anonymous (BOOLEAN, NOT NULL, DEFAULT FALSE) — see "Public
                            Anonymity" (Q6).

Recurring relationship:    recurring_occurrence_id (FK donation_recurring_occurrences, RESTRICT,
                            NULLABLE) — set only when this Donation was generated by a Recurring
                            Occurrence (see "Recurring Plan / Recurring Occurrence"). NULL for an
                            ordinary one-time Donation.

Timestamps:                created_at/updated_at (standard) plus succeeded_at, failed_at,
                            cancelled_at, expired_at (all DATETIME, NULLABLE — at most one is ever
                            non-null, since status is a single terminal-or-pending value; enforced
                            at the service layer, mirroring Campaign's own
                            submitted_at/approved_at/published_at/closed_at pattern from IMP-007).

Immutable vs mutable:      campaign_id, donor_principal_id, guest_name, guest_email, amount_minor,
                            currency, is_anonymous, recurring_occurrence_id are set at creation and
                            NEVER mutated afterward (a Donation's donated amount/target/identity is
                            not editable — there is no "edit Donation" use case; only status and its
                            *_at companion field transition, exactly once, through the lifecycle in
                            "State / Lifecycle"). donor_display_name is set at creation and not
                            mutated (no "rename after the fact" use case is evidenced).

Cancellation/expiration
  boundaries:                see "State / Lifecycle" — CANCELLED and EXPIRED are both terminal,
                            pre-success states. The PENDING->EXPIRED mechanism itself is
                            `FINAL / LOCKED` (HD-IMP008-04): the exact expiration TIMEOUT duration
                            remains configurable, with NO default value asserted by this
                            specification — the mechanism MUST NOT invent or silently assume a
                            duration (see "State / Lifecycle" for the exact configuration
                            contract). Donation intent expiration (this mechanism) and Payment
                            expiration (IMP-009's own, separate concern) remain distinct — see
                            "State / Lifecycle".

Metadata boundaries:        no free-form metadata/message/comment/dedication field is included —
                            no authoritative requirement evidences one (grepped
                            MASTER-REQUIREMENTS.md; no match). Not invented.

Audit requirements:         see "Audit Requirements".

Authorization:               see "Authorization / RBAC".

Ownership/data scope:        donor_principal_id is the OWN-scope anchor (a donor sees their own
                            Donations); ORGANIZATION scope covers staff/admin visibility — see
                            "Authorization / RBAC".
```

### Recurring Plan / Recurring Occurrence

Per MODULE-OWNERSHIP.md §4, Recurring Plan and Recurring Occurrence are IMP-008-owned entities.
This task's own instructions (§11) state the locked entity chain:

```
Recurring Plan -> Occurrence -> Donation -> Payment
```

Per HD-IMP008-02, "Hybrid Recurring Donation" (Q5) means: (1) a donor may make a ONE-TIME
Donation; OR (2) an authenticated donor may establish a RECURRING Donation Plan — i.e. "hybrid"
describes the *platform* offering both modes side by side, not a single donation that is itself
part-recurring/part-one-time. This resolves OQ-008-02.

`donation_recurring_plans`:

```
id (BIGINT PK), ulid (CHAR 26, unique)
donor_principal_id (FK principals, RESTRICT, NOT NULL — `FINAL / LOCKED`, HD-IMP008-01B: a
  Recurring Plan requires an authenticated donor; guest recurring is explicitly NOT supported in
  v1, and no bearer-token/magic-link authorization architecture is introduced for it)
campaign_id (FK campaigns, RESTRICT, NOT NULL)
amount_minor (BIGINT UNSIGNED, NOT NULL), currency (CHAR 3, NOT NULL) — via Money/CurrencyMinorUnits
frequency (VARCHAR 16, NOT NULL) — `FINAL / LOCKED`, HD-IMP008-02: the only value accepted/
  enforced in v1 is `MONTHLY` (validated-list check, not a boolean/hard-coded monthly-only code
  path, so additional authorized frequency values can be added later without redesigning this
  column or the Donation/Recurring Plan aggregates — see "Business Rules" BR-9)
status (VARCHAR 16, NOT NULL) — ACTIVE|PAUSED|CANCELLED|COMPLETED. PAUSE/RESUME/CANCEL authority
  `FINAL / LOCKED` per HD-IMP008-03 — see "Authorization / RBAC"
is_anonymous (BOOLEAN, NOT NULL, DEFAULT FALSE) — propagates to generated Occurrences' Donations
starts_at (DATETIME, NOT NULL), ends_at (DATETIME, NULLABLE — open-ended by default)
next_occurrence_at (DATETIME, NULLABLE)
paused_at / paused_by_principal_id (DATETIME / FK principals RESTRICT, NULLABLE)
cancelled_at / cancelled_by_principal_id (DATETIME / FK principals RESTRICT, NULLABLE)
timestamps
```

`donation_recurring_occurrences`:

```
id (BIGINT PK), ulid (CHAR 26, unique)
recurring_plan_id (FK donation_recurring_plans, RESTRICT, NOT NULL)
scheduled_at (DATETIME, NOT NULL)
donation_id (FK donations, RESTRICT, NULLABLE — set once this occurrence generates its Donation)
status (VARCHAR 16, NOT NULL) — SCHEDULED|GENERATED|SKIPPED|FAILED. `FINAL / LOCKED` per
  HD-IMP008-03: NO automatic retry of a FAILED occurrence — it remains FAILED permanently; the
  next independently SCHEDULED occurrence proceeds on the plan's normal monthly schedule
  regardless (see "Business Rules" BR-11). Payment-ATTEMPT retry (once a Payment attempt exists
  against a GENERATED occurrence's Donation) is exclusively IMP-009's concern, never IMP-008's.
generated_at (DATETIME, NULLABLE)
failure_reason (TEXT, NULLABLE)
timestamps
```

**This specification defines the schema, ownership boundary, AND the now-locked operational rules
(monthly-only frequency, pause/resume/cancel authority, no auto-retry).** The generation
scheduling/execution mechanism itself (what triggers moving a SCHEDULED Occurrence to GENERATED —
i.e. the actual cron/job that creates the child Donation on schedule) remains a separate,
deferred implementation task (see "Out of Scope") — its OPERATIONAL RULES are no longer
undetermined (HD-IMP008-02/03 resolved that), only its concrete build is sequenced later. Creating
the tables now (with FK integrity) ahead of that build is a deliberate, minimal foundation,
matching HD-IMP007-02's own "minimum canonical foundation, no premature capability" precedent.

## Data Ownership

```
Donation:                    owned entirely by IMP-008. References Campaign (IMP-007) by
                              campaign_id only; never duplicates Campaign/Fund/eligibility state.
Recurring Plan / Occurrence: owned entirely by IMP-008.
Campaign / Fund:              read-only consumption of IMP-007's existing tables/services. IMP-008
                              adds no column to `campaigns` or `funds`.
Principal:                    read-only consumption of IMP-003's `principals` table.
```

## State / Lifecycle

**Donation lifecycle (distinct from Payment lifecycle — IMP-009's, not built here):**

```
                    +-----------+
   create  -------->|  PENDING  |
                    +-----------+
                     |   |   |
      (Payment       |   |   | (donor/admin cancels,
       consequence   |   |   |  see Authorization)
       succeeds)      |   |   |
                     v   |   v
              +-----------+  +-----------+
              | SUCCEEDED |  | CANCELLED |
              +-----------+  +-----------+
                     ^
                     | (Payment consequence fails)
                     |
              +-----------+          +-----------+
              |  FAILED   |          |  EXPIRED  |
              +-----------+          +-----------+
                                (PENDING with no resolution
                                 within a CONFIGURABLE window —
                                 see below, HD-IMP008-04)
```

```
PENDING    -> SUCCEEDED   ONLY via the authorized transition surface an Authorized Financial
                          Consequence (IMP-011, itself downstream of an IMP-009 Payment outcome)
                          invokes — see "Financial Boundary". IMP-008 does not decide WHEN this
                          happens; it defines the resulting state and its invariants.
PENDING    -> FAILED      same authorized-consequence surface, failure outcome.
PENDING    -> EXPIRED     system-initiated (no Payment outcome arrived within the configurable
                          timeout — HD-IMP008-04); never donor/admin-initiated directly.
                          Configuration contract (`FINAL / LOCKED`): a single nullable config
                          value (e.g. `config('donation.pending_expiry_minutes')`, default `null`)
                          gates the sweep — the scheduled sweep job MUST NOT act, and no Donation
                          ever transitions to EXPIRED, while this value is unset. No numeric
                          default is asserted by this specification (HD-IMP008-04 explicitly
                          prohibits inventing or silently assuming a duration); an operator/Human
                          sets the value through ordinary application configuration once a
                          duration is authorized. Donation intent expiration (this mechanism) is
                          distinct from Payment expiration (a Payment ATTEMPT's own validity
                          window/session timeout, e.g. a bank-transfer instruction's expiry) —
                          IMP-009 owns the latter entirely; the two clocks are independent and
                          neither this document nor IMP-009 may conflate them.
PENDING    -> CANCELLED   donor (own, authenticated only — guest self-service cancellation is
                          explicitly NOT supported, HD-IMP008-05B) or an authorized admin, before
                          any Payment outcome arrives. See "Authorization / RBAC".
SUCCEEDED  -> (terminal)  no further transition from IMP-008's own domain. A later Refund
                          (IMP-016) records ITS OWN state against Payment/Ledger; it does not
                          mutate a SUCCEEDED Donation row.
FAILED, CANCELLED,
  EXPIRED  -> (terminal)  no further transition. A donor who wants to try again creates a NEW
                          Donation row (never resurrects a terminal one) — consistent with
                          "posted financial records are immutable / corrections via
                          reversal/adjustment, never mutation" applied by analogy to Donation's
                          own terminal states.
```

All other transitions (e.g. SUCCEEDED -> anything, EXPIRED -> SUCCEEDED, any transition from a
terminal state) are rejected with a typed conflict exception — mirroring IMP-007's
`CampaignLifecycleService` pattern (AC-007-007).

## Business Rules

```
BR-1  A Donation MUST reference exactly one Campaign for which
      CampaignEligibilityResolver::isDonationEligible() returns true AT THE MOMENT OF CREATION.
      Eligibility is re-checked at creation time only — IMP-008 does not invent a re-check at
      "succeeded" time (Payment's own timing is IMP-009's concern); if this ordering is wrong,
      raise via change control, not silently here.
BR-2  A Donation's donor path is exactly one of: donor_principal_id (authenticated) XOR
      guest_name + guest_email (guest). Enforced by application-level guard (mirroring
      Principal::isConsistent) and, where the database driver supports it, a CHECK constraint
      (see "Database Impact" for the SQLite-exception note, mirroring `principals`').
BR-3  amount_minor and currency are set exclusively via Money::ofMinorUnits() +
      CurrencyMinorUnits::digitsFor() — an unregistered currency is rejected at creation, never
      silently coerced.
BR-4  is_anonymous governs PUBLIC representation only (see "Public Anonymity") — it never removes
      donor_principal_id/guest_name/guest_email from the row itself; internal/audit visibility of
      donor identity is unaffected by this flag.
BR-5  Donation creation, by itself, MUST NOT create a Ledger journal/entry, Commission
      entitlement, Withdrawal balance, or Reconciliation entry (see "Financial Boundary" — this
      restates AGENTS.md "Never: invent accounting entries" and the Financial Posting Boundary
      for this specific IMP).
BR-6  A Recurring Plan requires an authenticated donor (donor_principal_id NOT NULL) — HD-IMP008-01B,
      `FINAL / LOCKED`. Guest recurring donation is not supported in v1, and no bearer-token/
      magic-link authorization architecture is introduced to work around this.
BR-7  A terminal Donation state (SUCCEEDED/FAILED/CANCELLED/EXPIRED) is never mutated back to
      PENDING or to any other terminal state.
BR-8  Donation.campaign_id, donor_principal_id, guest_name, guest_email, amount_minor, currency,
      is_anonymous, recurring_occurrence_id are immutable after creation (see "Domain Model").
BR-9  A Recurring Plan's `frequency` accepts exactly one value in v1 — `MONTHLY` — via a
      validated-list check (HD-IMP008-02, `FINAL / LOCKED`). The check MUST be structured so that
      additional authorized frequency values can be added later without redesigning the Donation
      or Recurring Plan aggregates (e.g. a config-driven or DB-lookup allow-list, never a
      hard-coded single-value boolean check that would itself need replacing to extend).
BR-10 A Recurring Plan may be PAUSED and RESUMED by its owning donor (OWN scope,
      donation.recurring_plan.manage) or by an authorized ORGANIZATION-scoped admin override
      (HD-IMP008-03, `FINAL / LOCKED`) — see "Authorization / RBAC".
BR-11 A FAILED Recurring Occurrence is never automatically retried by IMP-008 (HD-IMP008-03,
      `FINAL / LOCKED`); the next independently SCHEDULED occurrence proceeds per the plan's
      normal monthly schedule regardless of a prior FAILED occurrence. Payment-attempt retry
      belongs exclusively to IMP-009.
BR-12 Donation creation MUST include a client-provided idempotency key; a request without one is
      rejected before any Donation row is created (HD-IMP008-05A, `FINAL / LOCKED`) — see
      "Idempotency" for the exact contract.
BR-13 No guest self-service Donation cancellation exists in v1 (HD-IMP008-05B, `FINAL / LOCKED`).
      Guest cancellation, when legitimately required, is handled exclusively through the
      authorized organization/admin support path (see "Authorization / RBAC"). No Donation
      identifier (internal id or ulid), email address, or other guessable/knowable value may ever
      function as an authorization credential for a guest action. Abandoned guest PENDING
      Donations resolve through the Donation expiration mechanism (HD-IMP008-04), not through a
      guest-initiated cancellation.
BR-14 A guest-originated `donation.created` audit event records actor kind
      `AuditActorKind::Unauthenticated`, `actor_principal_id = NULL`, and the fixed registered
      `execution_context = "http:donation:guest_created"` (HD-IMP008-06, `FINAL / LOCKED`, per
      [ADR-002](../adr/ADR-002-donation-unauthenticated-actor-scope-amendment.md)). Never
      `AuditActorKind::System` (would misrepresent a human-originated action as automation), and
      no new actor kind is introduced.
```

## Security Requirements

Apply the canonical authorization AND-chain (see "Architecture References") to every non-public
Donation operation. Public/guest Donation CREATION is the one explicit entry point where
`Authenticated` is intentionally not required (see "Authorization / RBAC" below for the exact
boundary of that exception). Default DENY for every protected operation. Super Admin does NOT
automatically imply financial/Donation authority (mirrors Q27's "Super Admin != automatic Financial
Authority", applied here by the same principle already locked for audit visibility).

## Authorization / RBAC

```
Public/guest Donation creation:   NO authentication required. Still subject to: valid Campaign
                                   eligibility (BR-1), valid Money construction (BR-3), guest-path
                                   completeness (BR-2), rate limiting (see "Error Handling" /
                                   Idempotency). This is the intentional exception to
                                   "Authenticated" in the AND-chain — a donor is not required to
                                   have an account to give, per Q22 and this task's explicit
                                   instruction.

Authenticated donor creation:      donor_principal_id is derived from the acting Principal — a
                                   caller can never set an arbitrary donor_principal_id.

Donor viewing own Donations:       Authenticated AND permission donation.view AND scope OWN
                                   (ownership rule: donor_principal_id == acting Principal). A
                                   guest has no durable way to list past guest Donations in v1 (no
                                   account) — this is a direct consequence of not forcing account
                                   creation, not a gap this spec silently papers over; if a
                                   guest-lookup-by-reference capability is required, raise via
                                   change control.

Admin/staff viewing Donations:     Authenticated AND permission donation.view AND scope
                                   ORGANIZATION (role-assigned, per the existing Data Scope Model
                                   — no new ScopeType value is invented; ORGANIZATION is already
                                   in the closed taxonomy).

Donor cancelling own PENDING
  Donation:                        Authenticated AND permission donation.cancel AND scope OWN AND
                                   resource state PENDING. Only an authenticated donor may
                                   self-service cancel — a guest has no self-service cancellation
                                   path at all in v1 (HD-IMP008-05B, `FINAL / LOCKED`, not merely
                                   undefined).

Guest cancellation
  (support path only):              NOT self-service. When legitimately required, handled
                                   exclusively through the admin/organization support path below
                                   (Authenticated staff AND donation.cancel AND scope ORGANIZATION
                                   AND resource state PENDING) — same permission/scope as ordinary
                                   admin cancellation, no separate guest-specific mechanism.
                                   Abandoned guest PENDING Donations otherwise resolve via the
                                   Donation expiration mechanism (HD-IMP008-04), never via a
                                   bearer-token/magic-link/identifier-as-credential path
                                   (HD-IMP008-05B).

Admin cancelling a PENDING
  Donation:                        Authenticated AND permission donation.cancel AND scope
                                   ORGANIZATION AND resource state PENDING. This is the SAME path
                                   used for guest cancellation support requests above.

System/authorized-consequence
  transition (PENDING ->
  SUCCEEDED/FAILED, or ->
  EXPIRED):                        NOT reachable through any human-facing permission. Reserved for
                                   an authorized System Principal invocation only (mirrors
                                   Principal::principal_kind = System, IMP-003) — IMP-009/011
                                   define WHO/WHAT calls this; IMP-008 defines only that the
                                   surface exists and is inaccessible to ordinary Human Principals.
                                   EXPIRED specifically fires only from the configured sweep (see
                                   "State / Lifecycle") — never a human-facing action either.

Recurring Plan create (donor,
  own):                             Authenticated AND permission donation.recurring_plan.manage
                                   AND scope OWN. Authenticated-donor-only, per HD-IMP008-01B.

Recurring Plan pause/resume
  (donor, own):                     Authenticated AND permission donation.recurring_plan.manage
                                   AND scope OWN AND resource state ACTIVE (pause) or PAUSED
                                   (resume) — HD-IMP008-03, `FINAL / LOCKED`.

Recurring Plan cancel
  (donor, own):                     Authenticated AND permission donation.recurring_plan.manage
                                   AND scope OWN AND resource state ACTIVE or PAUSED.

Recurring Plan admin override
  (pause/resume/cancel, support/
  compliance):                      Authenticated AND permission donation.recurring_plan.manage
                                   AND scope ORGANIZATION — HD-IMP008-03, `FINAL / LOCKED`. Same
                                   permission as donor self-service; scope distinguishes OWN
                                   (donor) from ORGANIZATION (admin), exactly as every other
                                   OWN/ORGANIZATION pair in this document already does — no new
                                   permission invented for the override.
```

New permissions (naming mirrors `campaign.*`/`fund.*` convention from IMP-007's
`PermissionRegistry`):

```
donation.view
donation.cancel
donation.recurring_plan.manage
```

No existing permission is reused for a different meaning; no new ScopeType is added to the closed
taxonomy (`docs/05-rbac/DATA-SCOPE-MODEL.md`) — OWN and ORGANIZATION, already defined, are
sufficient.

## Database Impact

```
donations
  id (BIGINT PK), ulid (CHAR 26, unique)
  campaign_id (FK campaigns.id, RESTRICT, NOT NULL)
  donor_principal_id (FK principals.id, RESTRICT, NULLABLE)
  recurring_occurrence_id (FK donation_recurring_occurrences.id, RESTRICT, NULLABLE)
  guest_name (VARCHAR 150, NULLABLE), guest_email (VARCHAR 255, NULLABLE)
  donor_display_name (VARCHAR 150, NULLABLE)
  amount_minor (BIGINT UNSIGNED, NOT NULL), currency (CHAR 3, NOT NULL)
  status (VARCHAR 16, NOT NULL, DEFAULT 'PENDING')
  is_anonymous (BOOLEAN, NOT NULL, DEFAULT FALSE)
  idempotency_key (VARCHAR 128, NOT NULL, UNIQUE) — `FINAL / LOCKED`, HD-IMP008-05A; see
    "Idempotency" for the exact contract
  succeeded_at / failed_at / cancelled_at / expired_at (DATETIME, NULLABLE)
  cancelled_by_principal_id (FK principals.id, RESTRICT, NULLABLE)
  timestamps

  Indexes: campaign_id, donor_principal_id, status, recurring_occurrence_id
  Unique: ulid, idempotency_key
  CHECK (app-level guard always; DB-level CHECK where the driver supports it, mirroring
    `principals`' own migration comment on SQLite not carrying the CHECK — the ONLY enforcement
    layer on SQLite is therefore the application-level guard, same as Principal::isConsistent):
    (donor_principal_id IS NOT NULL) OR (guest_name IS NOT NULL AND guest_email IS NOT NULL)

donation_recurring_plans
  id (BIGINT PK), ulid (CHAR 26, unique)
  donor_principal_id (FK principals.id, RESTRICT, NOT NULL)
  campaign_id (FK campaigns.id, RESTRICT, NOT NULL)
  amount_minor (BIGINT UNSIGNED, NOT NULL), currency (CHAR 3, NOT NULL)
  frequency (VARCHAR 16, NOT NULL) — `FINAL / LOCKED` (HD-IMP008-02): validated against an
    allow-list currently containing only 'MONTHLY' (BR-9); the check/list is structured to be
    extended later without a schema redesign
  status (VARCHAR 16, NOT NULL, DEFAULT 'ACTIVE') — ACTIVE|PAUSED|CANCELLED|COMPLETED
  is_anonymous (BOOLEAN, NOT NULL, DEFAULT FALSE)
  starts_at (DATETIME, NOT NULL), ends_at (DATETIME, NULLABLE)
  next_occurrence_at (DATETIME, NULLABLE)
  paused_at (DATETIME, NULLABLE), paused_by_principal_id (FK principals.id, RESTRICT, NULLABLE)
  cancelled_at (DATETIME, NULLABLE), cancelled_by_principal_id (FK principals.id, RESTRICT,
    NULLABLE)
  timestamps

  Indexes: donor_principal_id, campaign_id, status
  Unique: ulid

donation_recurring_occurrences
  id (BIGINT PK), ulid (CHAR 26, unique)
  recurring_plan_id (FK donation_recurring_plans.id, RESTRICT, NOT NULL)
  scheduled_at (DATETIME, NOT NULL)
  donation_id (FK donations.id, RESTRICT, NULLABLE)
  status (VARCHAR 16, NOT NULL, DEFAULT 'SCHEDULED')
  generated_at (DATETIME, NULLABLE)
  failure_reason (TEXT, NULLABLE)
  timestamps

  Indexes: recurring_plan_id, status, scheduled_at
  Unique: ulid, donation_id (where not null — one Occurrence generates at most one Donation)
```

No Payment/Ledger/Commission/Refund/Reconciliation table is created (see "Out of Scope"). No
existing IMP-007 table (`campaigns`, `programs`, `funds`) is modified.

Configuration (not a table): `config('donation.pending_expiry_minutes')`, default `null` — see
"State / Lifecycle" (HD-IMP008-04). No config file value is pre-populated with an invented number
by this specification; the key exists, unset, until an authorized duration is supplied.

Migration naming (mirrors IMP-007's `0001_07_01_NNNNNN` convention):
`0001_08_01_000000_create_donations_table.php`,
`0001_08_01_000001_create_donation_recurring_plans_table.php`,
`0001_08_01_000002_create_donation_recurring_occurrences_table.php` — the two-migration ordering
problem (donations references donation_recurring_occurrences, which references
donation_recurring_plans, which is fine; but donations.recurring_occurrence_id is a forward
reference to a table not yet created if donations migrates first) is resolved by creating
`donation_recurring_plans` and `donation_recurring_occurrences` BEFORE `donations`, then adding
`donations.recurring_occurrence_id`'s FK — i.e. actual file order:
`0001_08_01_000000_create_donation_recurring_plans_table.php`,
`0001_08_01_000001_create_donation_recurring_occurrences_table.php`,
`0001_08_01_000002_create_donations_table.php`; the FK from `donation_recurring_occurrences` to
`donations.id` (donation_id) is then added via a fourth migration
(`0001_08_01_000003_add_donation_fk_to_donation_recurring_occurrences_table.php`) after
`donations` exists, breaking the circular dependency — implementers must not reorder this without
re-verifying the FK cycle.

## API Impact

Minimum internal application actions needed for the web flow (not the full IMP-025 REST API):

```
Public donation initiation:
  POST /campaigns/{slug}/donations   — public/guest-or-authenticated entry point (BR-1..BR-3).

Donor-owned donation views:
  GET  /me/donations                 — authenticated donor's own Donation list (scope OWN).
  GET  /me/donations/{ulid}          — single own Donation detail.
  POST /me/donations/{ulid}/cancel   — donor-initiated cancel while PENDING.

Admin/backoffice views/actions:
  GET  /admin/donation/donations             — organization-scoped list.
  GET  /admin/donation/donations/{ulid}      — organization-scoped detail.
  POST /admin/donation/donations/{ulid}/cancel — admin-initiated cancel while PENDING.
```

Route/controller naming mirrors IMP-007's `admin/campaign/*` convention (`admin/donation/*`). No
public route exposes internal BIGINT ids (ulid only), mirroring AC-007-011.

## UI Impact

```
Public Campaign page: a donation entry point (form) becomes reachable when
  CampaignEligibilityResolver::isDonationEligible() is true, reusing that same server-computed
  is_donation_eligible flag already passed to the public Theme render (IMP-007 §"Eligibility
  display") — no re-derivation in Vue.
Donor-owned "my donations" view (new, minimal — list + detail, reusing existing admin-shell/public
  page primitives from IMP-006/007 presentation work rather than introducing new design system
  elements).
Admin donation list/detail view (new, minimal, mirrors the existing admin Campaign/Fund workspace
  patterns from IMP-007's presentation remediation).
```

No Campaign-progress ("collected amount") widget is added to the public Campaign page in this
IMP — see "Out of Scope."

## Financial Impact

```
Donation creation itself has ZERO Ledger/Commission/Withdrawal/Reconciliation impact (BR-5).
Donation persists amount_minor/currency as a DISPLAY/INTENT figure only — the same
  "non-authoritative" status IMP-007 already established for Campaign.target_amount_minor
  (section 8a) applies here: a Donation row is evidence of donor INTENT and, once SUCCEEDED,
  evidence that a Payment outcome was reported — it is NOT itself an accounting record. The
  actual Ledger posting (if/when it happens) is IMP-011's Authorized Financial Consequence, fed by
  IMP-009's Payment outcome, never derived by re-reading `donations` directly from Ledger code.
```

## Idempotency

`FINAL / LOCKED` per HD-IMP008-05A. Observable invariant:

```
INV-1  Two donation-creation requests that represent the SAME donor intent (e.g. a network retry
       or a double form submit) MUST NOT result in two independent Donation rows both capable of
       independently reaching SUCCEEDED and triggering two separate financial consequences.
```

**Client-provided idempotency key — MANDATORY, scoped to Donation creation only.** IMP-009
Payment/gateway idempotency semantics (provider-side dedupe, gateway retry tokens, etc.) are a
wholly separate contract, owned exclusively by IMP-009 — this section does not define, constrain,
or anticipate them.

```
Contract:
  Transport:        HTTP header `Idempotency-Key` on the Donation-creation request (both the
                     guest and authenticated entry points, POST /campaigns/{slug}/donations).
                     This mirrors the widely-used HTTP idempotency-key convention (e.g. Stripe's
                     `Idempotency-Key` header) rather than inventing a bespoke mechanism — the
                     platform adopts an established pattern, not a new one.
  Format:            Caller-generated opaque string. Printable ASCII, 1-128 characters. The
                     server does NOT interpret its internal structure (a UUID/ULID is a
                     reasonable client-side choice but is not mandated or validated as such
                     beyond the length/charset bound) — it is a comparison key, nothing more.
  Presence:          REQUIRED. A request without this header is rejected before any Donation row
                     is created or any Campaign-eligibility check runs (BR-12) — a missing key is
                     a request-shape error, checked first.
  Scope:             Unique per the `donations.idempotency_key` column itself — i.e. scoped to
                     Donation creation as a whole (not per-donor, not per-campaign; the column's
                     own database-level UNIQUE constraint is the entire scope mechanism, enforced
                     transactionally: an INSERT that violates the unique constraint is caught and
                     handled as a replay, never surfaced as a raw database error to the caller).
  Replay semantics:  If a request arrives with a key that already exists on a prior Donation:
                       - If the new request's campaign_id, amount_minor, currency, and donor
                         identity (donor_principal_id, or guest_name+guest_email) MATCH the
                         existing Donation's — this is a legitimate retry (e.g. a network
                         timeout that actually succeeded server-side). The EXISTING Donation is
                         returned (HTTP 200-equivalent success), and NO second row is created —
                         this is the deterministic duplicate-request protection HD-IMP008-05A
                         requires.
                       - If the key matches but any of those fields DIFFER — the caller is
                         reusing a key for a materially different donation, which indicates a
                         client-side bug (key collision or incorrect reuse), never a legitimate
                         retry. Rejected with a typed conflict exception (409-style); no row
                         created or returned.
  Enforcement:       Database-level UNIQUE constraint on `donations.idempotency_key` (NOT NULL,
                     unique — see "Database Impact") is the deterministic backstop; the
                     application wraps Donation creation in a single transaction that either (a)
                     inserts a genuinely new row, or (b) on a caught unique-constraint violation,
                     re-fetches and compares the existing row per the replay semantics above —
                     never a "check-then-insert" race (BR-12, "Concurrency").
```

This resolves OQ-008-05-A in full: the mechanism is now specified, not deferred. It does not
define or weaken any future IMP-009 Payment/gateway-level idempotency contract, which remains
entirely IMP-009's own, separate concern (see "Out of Scope").

## Concurrency

```
Donation status transitions (PENDING -> *) use a single-row, single-writer transaction (SELECT ...
  FOR UPDATE or the Laravel-equivalent locking read) mirroring the transactional discipline Q26
  already canonized for critical audit-adjacent mutations, applied here by analogy since a
  Donation's transition to SUCCEEDED is financial-reference-adjacent even though it is not itself
  a Ledger post. Two concurrent transition attempts on the same Donation MUST NOT both succeed —
  the second observes the already-terminal state and is rejected per BR-7.
Recurring Occurrence generation (when the deferred generation engine is built, per HD-IMP008-02/03's
  now-locked operational rules) must not generate two Donations for the same Occurrence — enforced
  by the `donation_recurring_occurrences.donation_id` unique constraint (see "Database Impact").
```

## Error Handling

```
Invalid/ineligible Campaign at creation (BR-1 fails):        typed validation exception, Donation
                                                              NOT created, no audit event beyond a
                                                              rejected-attempt log if the security
                                                              architecture already requires one
                                                              (not invented beyond that).
Malformed guest path (BR-2 fails):                            typed validation exception.
Unregistered currency (BR-3 fails):                            typed validation exception
                                                              (Money/CurrencyMinorUnits' own
                                                              UnknownCurrencyException, reused).
Invalid state transition (BR-7):                               typed conflict exception, no state
                                                              change (mirrors AC-007-007).
Missing Idempotency-Key header (BR-12):                        typed validation exception, checked
                                                              first, before any other validation;
                                                              no Donation row created.
Idempotency-Key reused with matching
  payload (legitimate replay):                                  NOT an error — existing Donation
                                                              returned, no second row created (see
                                                              "Idempotency").
Idempotency-Key reused with different
  payload:                                                      typed conflict exception
                                                              (409-style); no row created or
                                                              returned (see "Idempotency").
```

## Audit Requirements

Reuses IMP-004's `AuditEventRegistry`/`AuditEventDefinition` exclusively — no parallel mechanism.
Mirrors `CampaignAuditEventRegistrar`'s exact shape (NonCritical, GENERAL visibility, ORGANIZATION
scope, `hasFinancialReference: true` where the event carries amount/currency, actor kind Human for
donor-facing events and System for the authorized-consequence transition):

```
donation.created            actor kinds: [Human, Unauthenticated] | financial reference: true |
                             payload: campaign_id, amount_minor, currency, is_anonymous,
                             is_guest (bool) | execution_context: NULL for Human-actor rows;
                             "http:donation:guest_created" (fixed, registered) for
                             Unauthenticated-actor rows (HD-IMP008-06, ADR-002 — see BR-14)
donation.succeeded          actor: System | financial reference: true | payload: donation_ulid
donation.failed             actor: System | financial reference: true | payload: donation_ulid
donation.expired             actor: System | financial reference: true | payload: donation_ulid
donation.cancelled          actor: Human | financial reference: true | payload: donation_ulid,
                             cancelled_by_principal_id (no Unauthenticated actor kind for this
                             event — no guest-actor cancellation exists, HD-IMP008-05B)
donation.recurring_plan.created   actor: Human | financial reference: true
donation.recurring_plan.paused    actor: Human | financial reference: true | payload:
                             donation_recurring_plan_ulid, paused_by_principal_id
donation.recurring_plan.resumed   actor: Human | financial reference: true | payload:
                             donation_recurring_plan_ulid
donation.recurring_plan.cancelled actor: Human | financial reference: true
```

`donation.created`'s guest-actor case is `FINAL / LOCKED` per HD-IMP008-06: a guest-originated
event (no Principal — `donor_principal_id` null on the Donation) records
`AuditActorKind::Unauthenticated`, `actor_principal_id = NULL`, and the fixed registered
`execution_context = "http:donation:guest_created"` — per
[ADR-002](../adr/ADR-002-donation-unauthenticated-actor-scope-amendment.md)'s controlled,
narrowly-scoped widening of IMP-004's "Canonical Actor (Pre-Principal Cases)" (previously
restricted to failed-authentication-attempt evidence only; now also covers this one explicitly
registered, legitimate-successful-guest-action case — no other IMP-008 event uses this widened
category, and no new `AuditActorKind` value was added). An authenticated-donor `donation.created`
event continues to use `AuditActorKind::Human` exactly as before, execution_context NULL, per the
ordinary human/system/integration case IMP-004 already locked.

All Donation events are classified NonCritical (they are business events, not the
identity/security/RBAC/governance categories Q26 lists as Critical), consistent with how
IMP-007's own financially-adjacent events (`fund.*`, `campaign.fund_assigned`) were classified.
`hasFinancialReference: true` on every event, since all carry amount/currency context, gating read
access behind `AUDIT_READ_FINANCIAL_REFERENCE` exactly as IMP-007 already established for
Fund-adjacent events.

## Tests Required

```
Unit:                    Money/CurrencyMinorUnits construction paths reused correctly; BR-2 guest-
                          path CHECK guard (mirrors PrincipalTest's isConsistent tests).
Feature:                 Donation creation (guest + authenticated), full lifecycle transition
                          matrix (including every rejected invalid transition per BR-7).
Authorization:           every RBAC row in "Authorization / RBAC" — authorized ALLOW, unauthenticated
                          DENY (where auth is required), wrong permission DENY, correct permission
                          wrong scope DENY (donor A cannot view/cancel donor B's Donation), wrong
                          resource state DENY (cancel on non-PENDING), missing business authority
                          DENY.
Audit:                   every donation.* event actually recorded with correct criticality/
                          visibility/financial-reference flag; a forced audit-append failure does
                          NOT roll back a NonCritical Donation event (Q26 default applies only to
                          Critical categories — Donation events are NonCritical, so this test
                          documents the boundary rather than asserting rollback).
Database constraint:     RESTRICT on campaign_id/donor_principal_id/recurring_occurrence_id
                          deletion attempts (raw query, bypassing the service layer, mirroring
                          AC-007-009/010); guest-path CHECK constraint at the DB level where the
                          driver supports it (MySQL) and the app-level guard on SQLite.
State transition:         the full matrix in "State / Lifecycle", including concurrent-transition
                          rejection (see "Concurrency" — requires REAL MySQL, not SQLite, for
                          genuine row-locking/transaction-isolation behavior; SQLite alone is
                          explicitly insufficient for this per this task's own instruction and
                          DATABASE-INVARIANTS.md).
Money:                   unregistered-currency rejection; minor-unit correctness across at least
                          one non-2-digit currency (JPY, 0 digits) and one 3-digit currency (KWD),
                          proving IMP-008 does not assume 2 decimal places.
Campaign eligibility
  integration:            a Donation cannot be created against a Campaign for which
                          isDonationEligible() is false at creation time (DRAFT/REVIEW/APPROVED/
                          CLOSED status, or PUBLISHED but outside starts_at/ends_at).
Guest/authenticated
  behavior:                both paths produce a valid Donation; cross-path field population (guest
                          fields set for authenticated donor, or vice versa) is rejected.
Public anonymity:         is_anonymous=true never leaks donor_display_name/guest_name/
                          donor identity through any PUBLIC-facing read path, while
                          organization-scoped admin/audit visibility is unaffected.
Idempotency:              missing Idempotency-Key header rejected; identical key + matching
                          payload returns the existing Donation (no second row); identical key +
                          differing payload rejected with a typed conflict (see "Idempotency" for
                          the full contract, HD-IMP008-05A).
Recurring frequency:      Recurring Plan creation with `frequency != 'MONTHLY'` rejected
                          (HD-IMP008-02, BR-9).
Recurring pause/resume/
  cancel:                  owning donor (OWN scope) may pause/resume/cancel; another donor
                          (wrong ownership) DENY; ORGANIZATION-scoped admin override succeeds
                          (HD-IMP008-03).
Occurrence no-retry:      a FAILED Occurrence is never automatically retried; the plan's next
                          independently SCHEDULED occurrence is unaffected by a prior FAILED one
                          (HD-IMP008-03, BR-11) — schema/state-only test, since the generation
                          engine itself is out of scope.
Guest cancellation
  blocked:                  no public/guest-facing cancellation endpoint exists; any attempt via
                          the admin support path without ORGANIZATION-scoped donation.cancel is
                          denied; the admin path itself succeeds (HD-IMP008-05B).
Guest audit actor:        a guest `donation.created` event records
                          AuditActorKind::Unauthenticated, actor_principal_id null, and
                          execution_context = "http:donation:guest_created"; an authenticated
                          donor's `donation.created` event records AuditActorKind::Human with
                          execution_context null (HD-IMP008-06, BR-14).
Concurrency:              REAL disposable MySQL required (locking/transaction isolation is not
                          SQLite-portable) — two concurrent transition attempts on the same
                          Donation, only one succeeds. Use an explicitly disposable test database,
                          never `kmsitdonation` (the live/development database) or
                          `kmsitdonation_imp003_test` for anything destructive beyond its own
                          existing scope — a fresh, IMP-008-scoped disposable database/schema per
                          this repository's existing testing convention (see .env.testing's
                          existing `_imp003_test`-suffixed pattern as precedent for a similarly
                          named, separately disposable IMP-008 test database if a dedicated one is
                          needed for MySQL-only concurrency tests).
Recurring boundary:       schema/FK integrity tests only (RESTRICT, unique donation_id) — no
                          behavioral generation-engine tests, since building that engine remains a
                          separate, deferred implementation task (its operational rules are now
                          locked per HD-IMP008-02/03, but the engine itself is out of this IMP's
                          scope).
```

## Acceptance Criteria

```
AC-008-001
Given an unauthenticated guest visitor and a PUBLISHED Campaign for which
  CampaignEligibilityResolver::isDonationEligible() is true
When they submit a donation with a valid amount/currency, guest_name, guest_email, and an
  Idempotency-Key header
Then a Donation row exists in PENDING status, ulid-identified, donor_principal_id null,
  guest_name/guest_email populated, idempotency_key stored, and a donation.created audit event is
  recorded with actor kind Unauthenticated, actor_principal_id null, and execution_context
  "http:donation:guest_created" (HD-IMP008-06).

AC-008-002
Given an authenticated donor and the same eligible Campaign
When they submit a donation with a valid amount/currency and an Idempotency-Key header
Then a Donation row exists in PENDING status with donor_principal_id set to their own Principal,
  guest_name/guest_email both null, idempotency_key stored, and a donation.created audit event is
  recorded with actor kind Human and execution_context null.

AC-008-003
Given a Campaign in DRAFT, REVIEW, APPROVED, or CLOSED status, or PUBLISHED but outside its
  starts_at/ends_at window
When any visitor (guest or authenticated) attempts to submit a donation against it
Then the request is rejected with a typed validation exception and no Donation row is created.

AC-008-004
Given a donation amount in a currency not present in the CurrencyMinorUnits registry
When a donation is submitted
Then the request is rejected with a typed UnknownCurrencyException and no Donation row is created.

AC-008-005
Given a donation request missing both donor_principal_id (unauthenticated) and one or both of
  guest_name/guest_email
Then the request is rejected with a typed validation exception (BR-2) and no Donation row is
  created.

AC-008-006
Given a PENDING Donation
When the authorized system transition surface reports a succeeded Payment outcome for it
Then the Donation transitions to SUCCEEDED, succeeded_at is set, and a donation.succeeded audit
  event is recorded; no human-facing permission can trigger this transition directly.

AC-008-007
Given a PENDING Donation
When the authorized system transition surface reports a failed Payment outcome for it
Then the Donation transitions to FAILED, failed_at is set, and a donation.failed audit event is
  recorded.

AC-008-008
Given a PENDING Donation owned by an authenticated donor
When that donor, holding donation.cancel in OWN scope, cancels it
Then the Donation transitions to CANCELLED, cancelled_at/cancelled_by_principal_id are set, and a
  donation.cancelled audit event is recorded.

AC-008-009
Given a PENDING Donation owned by a different donor
When another authenticated donor (without ORGANIZATION-scoped donation.cancel) attempts to cancel
  it
Then the request is denied (wrong ownership / scope) and the Donation remains PENDING.

AC-008-010
Given a Donation in SUCCEEDED, FAILED, CANCELLED, or EXPIRED status
When any actor attempts any further status transition
Then the transition is rejected with a typed conflict exception and no state change occurs (BR-7).

AC-008-011
Given a Campaign still referenced by at least one Donation
When any actor attempts to delete that Campaign
Then the deletion is rejected at the database level (RESTRICT), proven by a raw query bypassing
  the service layer.

AC-008-012
Given a Donation with is_anonymous = true
When any unauthenticated/public read path renders it
Then donor_display_name, guest_name, and any authenticated-donor identity are never present in
  that public response, while the Donation's amount/campaign context (if otherwise publicly
  shown) is unaffected.

AC-008-013
Given a Donation with is_anonymous = false and a non-null donor_display_name
When a public read path renders it
Then donor_display_name is present in that public response.

AC-008-014
Given two donation-creation requests carrying the identical Idempotency-Key header and identical
  campaign_id/amount_minor/currency/donor-identity payload
When both are submitted
Then only the first creates a Donation row; the second returns the SAME existing Donation (no
  second row created, no error) — legitimate replay per "Idempotency".

AC-008-015
Given an authenticated donor viewing GET /me/donations
Then only Donations where donor_principal_id equals their own Principal are returned; another
  donor's guest or authenticated Donations never appear.

AC-008-016
Given a staff/admin actor holding donation.view in ORGANIZATION scope
When they request the admin donation list
Then Donations across all donors/campaigns are visible, subject to no additional undocumented
  filtering.

AC-008-017
Given two concurrent authorized transition attempts on the same PENDING Donation (one succeed, one
  fail outcome, racing)
When both execute against a real MySQL test database
Then exactly one transition succeeds and the Donation ends in exactly one terminal-appropriate
  state; the loser observes a typed conflict exception, never a silently overwritten state.

AC-008-018
Given a donation-creation request with no Idempotency-Key header
When it is submitted
Then the request is rejected with a typed validation exception (BR-12) and no Donation row is
  created.

AC-008-019
Given two donation-creation requests carrying the identical Idempotency-Key header but DIFFERENT
  campaign_id, amount_minor, currency, or donor identity
When both are submitted
Then only the first creates a Donation row; the second is rejected with a typed conflict exception
  and no row is created or returned.

AC-008-020
Given an authenticated donor with an ACTIVE Recurring Plan they own
When they pause it (donation.recurring_plan.manage, OWN scope)
Then the Plan transitions to PAUSED, paused_at/paused_by_principal_id are set, and a
  donation.recurring_plan.paused audit event is recorded.

AC-008-021
Given an authenticated donor with a PAUSED Recurring Plan they own
When they resume it
Then the Plan transitions to ACTIVE, and a donation.recurring_plan.resumed audit event is
  recorded.

AC-008-022
Given a Recurring Plan owned by a different donor
When another authenticated donor (without ORGANIZATION-scoped donation.recurring_plan.manage)
  attempts to pause/resume/cancel it
Then the request is denied (wrong ownership/scope) and the Plan's state is unchanged.

AC-008-023
Given a staff/admin actor holding donation.recurring_plan.manage in ORGANIZATION scope
When they pause, resume, or cancel a Recurring Plan they do not own
Then the action succeeds (admin override, HD-IMP008-03) and the correct audit event is recorded.

AC-008-024
Given a Recurring Plan creation request with `frequency` set to any value other than `MONTHLY`
When it is submitted
Then the request is rejected with a typed validation exception (BR-9) and no Plan is created.

AC-008-025
Given a Recurring Occurrence in FAILED status
When the system evaluates the Plan's next SCHEDULED occurrence
Then no automatic retry of the FAILED occurrence is attempted, and the next SCHEDULED occurrence
  proceeds unaffected on the Plan's normal monthly schedule (BR-11).

AC-008-026
Given a guest-owned PENDING Donation
When any unauthenticated request attempts to cancel it via a guest-facing endpoint
Then no such endpoint exists / the request is rejected (404 or 403); only the ORGANIZATION-scoped
  admin cancellation path can cancel a guest Donation (HD-IMP008-05B, BR-13).
```

## Forbidden Changes

```
No change to CampaignEligibilityResolver, Money, or CurrencyMinorUnits (IMP-007, HD-IMP007-02/03)
  — consumed as-is.
No change to `campaigns`, `programs`, or `funds` schema.
No Ledger, Payment, Commission, Withdrawal, Refund, or Reconciliation table or write path.
No new ScopeType value added to the closed Data Scope taxonomy.
No display-name column added to `users` (IMP-002) — solved within Donation's own schema instead
  (see "Domain Model" rationale).
No direct Program-level or Fund-level Donation (HD-IMP008-01A).
No guest recurring donation; no bearer-token/magic-link authorization architecture for it
  (HD-IMP008-01B).
No recurring frequency other than MONTHLY implemented in v1; the allow-list mechanism itself must
  remain extensible (HD-IMP008-02, BR-9).
No automatic occurrence-generation retry mechanism (HD-IMP008-03, BR-11).
No Donation creation without a client-provided Idempotency-Key (HD-IMP008-05A, BR-12).
No guest self-service Donation cancellation endpoint or bearer-token/identifier-as-credential
  mechanism (HD-IMP008-05B, BR-13).
No new `AuditActorKind` enum value; no use of `AuditActorKind::System` to represent a guest actor
  (HD-IMP008-06, BR-14, ADR-002).
No unauthorized widening of `AuditActorKind::Unauthenticated` beyond ADR-002's exact, registered
  scope (`identity.session.login_failed`'s original use, plus `donation.created`'s guest case
  only) — any further use requires its own future ADR, not silent reuse.
```

## External Verification

```
Donation expiration timeout DURATION VALUE — by explicit Human Decision (HD-IMP008-04), this
  remains deliberately deferred/configurable, no default asserted, mirroring Q17's
  retention-policy pattern. This is a resolved design choice, not a pending gap: the mechanism is
  fully specified (see "State / Lifecycle"); only the eventual numeric config value is left to a
  future, separate operational decision, exactly as HD-IMP008-04 intends.
```

Every other item previously listed here (recurring operational rules, idempotency-key contract)
is now resolved by HD-IMP008-01A/01B/02/03/05A and fully specified above — no longer pending
external verification.

## Human Decisions (Resolved)

All six Open Human Decisions raised in the original draft of this specification (commit `883f889`)
are now resolved, `FINAL / LOCKED`, by explicit Human Decision. None were decided by Claude.

```
HD-IMP008-01A — FINAL / LOCKED (resolves OQ-008-01, target-scope half)
Decision:    Donation target scope for v1 is CAMPAIGN-ONLY. Every Donation MUST reference an
             eligible Campaign. Program and Fund are reached only through the Campaign's existing
             relationships. IMP-008 MUST NOT introduce direct Program-level or Fund-level
             donations. Future direct Program/Fund donation support requires an explicit future
             authorized extension.
Materialized in:  §Domain Model ("Campaign relationship"), §Out of Scope, §Forbidden Changes.

HD-IMP008-01B — FINAL / LOCKED (resolves OQ-008-01, guest-recurring half)
Decision:    Recurring Donation requires an AUTHENTICATED DONOR. Guest one-time Donation remains
             allowed. Guest recurring Donation is NOT supported in v1. No guest recurring
             bearer-token/magic-link authorization architecture is introduced.
Materialized in:  §Domain Model ("Recurring Plan / Recurring Occurrence"), §BR-6, §Out of Scope,
             §Forbidden Changes.

HD-IMP008-02 — FINAL / LOCKED (resolves OQ-008-02)
Decision:    "Hybrid Recurring Donation" means: (1) a donor may make a ONE-TIME Donation; OR (2)
             an authenticated donor may establish a RECURRING Donation Plan. For v1, the supported
             recurring frequency is MONTHLY only. The architecture MUST permit future additional
             frequencies through an authorized extension without redesigning the fundamental
             Donation aggregate. Weekly/custom frequencies are NOT implemented in v1.
Materialized in:  §Domain Model ("Recurring Plan / Recurring Occurrence"), §BR-9, §Database Impact,
             §Out of Scope, §Forbidden Changes, AC-008-024.

HD-IMP008-03 — FINAL / LOCKED (resolves OQ-008-03)
Decision:    Recurring Plan management: owning donor may PAUSE/RESUME/CANCEL their own plan;
             authorized organization/admin authority may override for support/compliance,
             following existing authorization/scope/audit contracts. Occurrence-generation
             failure: NO automatic retry in v1 — a failed occurrence remains FAILED; the next
             independently scheduled occurrence proceeds on the plan's normal monthly schedule.
             Payment-attempt retry belongs exclusively to IMP-009.
Materialized in:  §Domain Model, §BR-10/BR-11, §Authorization / RBAC, §Out of Scope, §Forbidden
             Changes, AC-008-020..023/025.

HD-IMP008-04 — FINAL / LOCKED (resolves OQ-008-04)
Decision:    Donation supports an independent PENDING -> EXPIRED mechanism. The expiration
             duration MUST be configurable. No authoritative default duration is established by
             this specification. Until an authorized configuration/policy supplies a value, the
             expiration mechanism must not invent or silently assume a timeout. Donation
             expiration and Payment expiration remain separate concepts.
Materialized in:  §Domain Model ("Cancellation/expiration boundaries"), §State / Lifecycle,
             §Database Impact (config contract), §External Verification.

HD-IMP008-05A — FINAL / LOCKED (resolves OQ-008-05, idempotency half)
Decision:    Donation creation requires a CLIENT-PROVIDED IDEMPOTENCY KEY, enforced through a
             database uniqueness invariant and transactional application behavior, deterministic,
             scoped to Donation creation only. IMP-008 does not define Payment/gateway idempotency
             semantics (IMP-009's own concern).
Materialized in:  §Idempotency (full HTTP/application contract, normalization, scope, replay
             semantics), §BR-12, §Database Impact, §Error Handling, AC-008-001/002/014/018/019.

HD-IMP008-05B — FINAL / LOCKED (resolves OQ-008-05, guest-cancel half)
Decision:    Guest self-service Donation cancellation is NOT supported in v1. A guest Donation
             MUST NOT use its ID/ULID, email knowledge, or another identifier as an authorization
             secret. Guest cancellation, when legitimately required, is handled through the
             authorized organization/admin support path. Abandoned guest PENDING Donations are
             handled by the Donation expiration policy/mechanism. No guest cancellation
             bearer-token architecture is introduced.
Materialized in:  §Authorization / RBAC, §BR-13, §State / Lifecycle, §Forbidden Changes,
             AC-008-026.

HD-IMP008-06 — FINAL / LOCKED (resolves OQ-008-06)
Decision:    Guest-originated successful Donation actions use AuditActorKind::Unauthenticated,
             actor_principal_id = NULL, and a fixed registry-controlled execution_context
             appropriate to the registered Donation entry point. This explicitly authorizes a
             controlled, narrowly-scoped amendment to the locked IMP-004 audit contract so
             Unauthenticated may represent BOTH the already-authorized failed/incomplete
             authentication cases AND legitimate successful actions through explicitly registered
             public unauthenticated entry points — never a generic bucket, never System
             misrepresenting the guest, never a new GuestSubmission actor kind. The required
             governance/change-control procedure was applied (see below) before this scope is
             relied upon.
Materialized in:  §BR-14, §Audit Requirements, and
             [ADR-002](../adr/ADR-002-donation-unauthenticated-actor-scope-amendment.md), which
             records the controlled amendment applied directly to
             [IMP-004-audit-governance-foundation.md](IMP-004-audit-governance-foundation.md)'s
             own "Canonical Actor (Pre-Principal Cases)" section — the contract amendment this
             decision explicitly authorized.
```

OPEN HUMAN DECISIONS: 0

## Traceability

```
Requirement/Decision                    Spec Section(s)              Test/AC
Q5  Hybrid Recurring Donation           §Domain Model (Recurring),   AC-008-024
  (resolved by HD-IMP008-02)            §BR-9
Q6  Public Anonymity Only               §Domain Model, §BR-4,        AC-008-012, AC-008-013
                                         §Authorization
Q20 IDR Base + Limited Multi-Currency   §Architecture References,    AC-008-004, Money tests
                                         §BR-3
Q21 Email as Canonical Login            §Domain Model (guest path)   AC-008-001/002
Q22 Hybrid Registration by Actor        §Domain Model (guest path)   AC-008-001
Donation != Payment                     §Out of Scope, §State/       AC-008-006/007
                                         Lifecycle, §Financial Impact
Fund != mutable balance                 §Domain Model (Fund          n/a (no Fund write)
                                         relationship)
CampaignEligibilityResolver reuse       §Architecture References,    AC-008-003
  (HD-IMP007-03)                        §BR-1
Money/CurrencyMinorUnits reuse          §Architecture References,    AC-008-004, Money tests
  (HD-IMP007-02)                        §BR-3
Canonical authorization AND-chain       §Authorization / RBAC        AC-008-008/009/015/016
Q26 audit fail-closed critical          §Audit Requirements          Audit tests
  categories (Donation is NonCritical,
  explicitly not in that list)
Financial Posting Boundary              §Financial Impact, §BR-5     n/a (negative — no Ledger write
                                                                     exists to test yet)
DATABASE-INVARIANTS.md financial        §Idempotency, §Concurrency   AC-008-014, AC-008-017/018/019
  idempotency
HD-IMP008-01A (Campaign-only)           §Domain Model, §Out of       n/a (structural — no
                                         Scope, §Forbidden Changes    Program/Fund column exists)
HD-IMP008-01B (auth-only recurring)     §Domain Model, §BR-6         n/a (structural)
HD-IMP008-02 (MONTHLY only)             §Domain Model, §BR-9,        AC-008-024
                                         §Database Impact
HD-IMP008-03 (pause/resume/no-retry)    §Domain Model, §BR-10/11,    AC-008-020..023/025
                                         §Authorization / RBAC
HD-IMP008-04 (expiration, no default)   §State / Lifecycle,          n/a (config-gated; no test
                                         §Database Impact             can assert a default that
                                                                     does not exist)
HD-IMP008-05A (mandatory idempotency)   §Idempotency, §BR-12         AC-008-001/002/014/018/019
HD-IMP008-05B (no guest cancel)         §Authorization / RBAC,       AC-008-026
                                         §BR-13
HD-IMP008-06 (guest audit actor,        §BR-14, §Audit               AC-008-001, guest audit
  ADR-002)                              Requirements                 actor test
```

## Specification Self-Review

Performed at initial submission (commit `883f889`) and re-performed after materializing
HD-IMP008-01A..06 (this revision). Findings classified BLOCKER/MAJOR/MINOR/EDITORIAL; all findings
below were patched into the specification text above before this document was finalized (none are
left open in the spec body — zero Human Decisions remain open, per "Human Decisions (Resolved)").

```
Requirement traceability:        PASS — see "Traceability" above; every Human Decision cited in
                                  this document (Q5/Q6/Q20/Q21/Q22, HD-IMP008-01A..06) and every
                                  IMP-007 contract reused (CampaignEligibilityResolver, Money,
                                  CurrencyMinorUnits) maps to a spec section and at least one AC.
Locked architecture preservation: PASS — Donation!=Payment, Payment!=Ledger, Fund!=balance,
                                  Reconciliation!=Ledger, Commission!=Wallet all explicitly
                                  restated and enforced by BR-5/"Financial Impact"/"Out of Scope";
                                  no Forbidden Changes item is violated by any AC.
IMP-007 compatibility:           PASS — no `campaigns`/`programs`/`funds` schema change; eligibility
                                  and Money are consumed, not reimplemented (verified against the
                                  actual IMP-007 spec text and, for Money/eligibility, would be
                                  verified against source at implementation time by whoever
                                  implements this — this spec cites the exact class/method
                                  signatures already established).
Money consistency:               PASS — no float column, BIGINT UNSIGNED minor units throughout,
                                  CurrencyMinorUnits used for every currency, JPY (0 digits) and
                                  KWD (3 digits) both explicitly required in "Tests Required" to
                                  prove no 2-digit assumption.
Donation/Payment separation:      PASS — the PENDING->SUCCEEDED/FAILED transition is explicitly an
                                  authorized-consequence surface Donation exposes but IMP-009 is
                                  never implemented or assumed here; "Out of Scope" is explicit
                                  about every Payment-owned concept excluded.
Donation/Ledger separation:      PASS — BR-5 + Financial Posting Boundary citation; no
                                  Ledger/Journal/Entry table created.
Authorization:                    PASS — full AND-chain applied per operation in "Authorization /
                                  RBAC"; the one intentional exception (public guest creation, no
                                  Authenticated term) is explicitly called out as intentional, not
                                  an oversight. Guest self-service cancellation — previously a
                                  tracked MINOR gap — is now RESOLVED, not merely defined:
                                  HD-IMP008-05B explicitly decides no such capability exists in
                                  v1, with an explicit prohibition on using any identifier as an
                                  authorization credential; this is a decided exclusion, not an
                                  undefined mechanism.
Audit:                            PASS — the guest actor-kind gap is now RESOLVED (HD-IMP008-06 /
                                  ADR-002): AuditActorKind::Unauthenticated, scope explicitly
                                  widened by Human-approved, narrowly-registered amendment (see
                                  BR-14, "Audit Requirements"). No unilateral IMP-008 decision was
                                  made — the amendment required and received explicit Human
                                  approval and its own governance record (ADR-002).
Idempotency:                      PASS — mechanism now fully specified (HD-IMP008-05A): mandatory
                                  client Idempotency-Key header, database-level uniqueness,
                                  transactional replay-or-reject semantics. No longer scoped-down
                                  or deferred.
Concurrency:                      PASS — locking discipline specified by analogy to Q26's existing
                                  transactional pattern; MySQL-only test requirement stated
                                  explicitly; idempotency-key enforcement explicitly specified as
                                  transactional insert-or-refetch, never check-then-insert.
Database invariants:               PASS — RESTRICT FKs throughout (no CASCADE on financial-adjacent
                                  references, mirroring IMP-007's own campaigns.program_id/
                                  fund_id RESTRICT choice), CHECK-or-app-guard for the guest/
                                  authenticated XOR invariant mirroring Principal's own pattern,
                                  unique donation_id on Occurrence preventing double-generation,
                                  idempotency_key now NOT NULL + unique (was nullable).
Recurring contract:               PASS — frequency constrained to MONTHLY via an explicitly
                                  extensible allow-list (BR-9), not a hard-coded single-value
                                  check; pause/resume/cancel authorization and no-auto-retry both
                                  fully specified (HD-IMP008-03).
Shared-hosting compatibility:      PASS — no new infrastructure requirement introduced; the
                                  idempotency mechanism (a header + a unique DB constraint) and
                                  the expiration sweep (cron/Scheduler-compatible, config-gated)
                                  both require no Redis/Supervisor/PM2/WebSocket/mandatory
                                  worker infrastructure.
Future IMP boundaries:             PASS — "Out of Scope" explicitly enumerates every later IMP
                                  (009-017 and beyond) and exactly what each owns instead; the
                                  Payment-vs-Donation idempotency and retry boundaries are now
                                  explicit (HD-IMP008-03/05A) rather than implicit.
IMP-004 amendment discipline:      PASS — ADR-002 followed the locked CHANGE-CONTROL.md AI Approval
                                  Boundary (Human decision recorded, AI did not self-approve);
                                  amendment is additive/narrow (no enum value added or removed, no
                                  existing registered event's behavior changed); IMP-004's own
                                  locked text is patched, not rewritten, with the amendment
                                  clearly marked as such.

BLOCKER:   0
MAJOR:     0
MINOR:     0
EDITORIAL: 0
```

## Definition of Done

Checklist — see [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md).
This document now satisfies every "Definition of Ready" item, including "No unresolved Human
Decision" (0 Open Human Decisions remain — see "Human Decisions (Resolved)"). The one remaining
gate before implementation ("Definition of Done" proper) may begin is Human Spec Approval itself
(see "Status" and "Implementation Ownership" above) — a distinct, separate control from resolving
individual Open Human Decisions, per MULTI-MODEL-OWNERSHIP.md "Human Spec Approval Gate." Qwen
Recon and Muse implementation remain NOT STARTED and must not begin before that gate closes.
