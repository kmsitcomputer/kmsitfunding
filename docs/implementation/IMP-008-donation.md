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
Donation aggregate (one-time donation to a Campaign).
Guest and authenticated donor donation entry points.
Public anonymity contract (Q6).
Donation lifecycle (PENDING/SUCCEEDED/FAILED/CANCELLED/EXPIRED) and its state-transition contract
  — including the abstract, authorization-gated transition surface IMP-009 (Payment) will later
  invoke, WITHOUT implementing IMP-009 itself.
Recurring Plan / Recurring Occurrence schema and ownership boundary (module-owned by IMP-008 per
  MODULE-OWNERSHIP.md §4), to the extent authoritative evidence supports it — see "Open Human
  Decisions" for what is deliberately NOT decided here.
Fund/Campaign reference (read-only consumption of IMP-007 contracts).
Authorization (RBAC) for donor-owned and admin/staff-facing Donation actions.
Audit events for Donation lifecycle transitions.
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
                             Recurring Occurrence generation SCHEDULING/EXECUTION mechanism (cron
                             job, retry/backoff policy) — see "Open Human Decisions" OQ-008-02/03.
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
                            exactly one Campaign. Program- or Fund-level direct donation (bypassing
                            a specific Campaign) is OUT OF SCOPE: IMP-007 defines donation
                            eligibility (CampaignEligibilityResolver) only at Campaign granularity
                            — Program has no starts_at/ends_at/eligibility contract of its own —
                            so there is no authoritative resolver a Program- or Fund-level Donation
                            could consume without IMP-008 inventing one. See OQ-008-01 if
                            Program/Fund-level donation is actually required.

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
                            pre-success states; the exact expiration TIMEOUT duration is NOT
                            invented here (no authoritative value exists) — see OQ-008-04.

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

`donation_recurring_plans`:

```
id (BIGINT PK), ulid (CHAR 26, unique)
donor_principal_id (FK principals, RESTRICT, NOT NULL — NOT nullable; a Recurring Plan requires an
  authenticated donor, because a guest has no persistent identity across sessions for a future
  occurrence to be meaningfully associated back to — this is a structural/technical necessity
  derived from "guest" having no durable identity, not an invented business policy; see
  OQ-008-01 if guest recurring is actually required)
campaign_id (FK campaigns, RESTRICT, NOT NULL)
amount_minor (BIGINT UNSIGNED, NOT NULL), currency (CHAR 3, NOT NULL) — via Money/CurrencyMinorUnits
frequency (VARCHAR 16, NOT NULL) — allowed value set NOT authoritatively established; see
  OQ-008-02
status (VARCHAR 16, NOT NULL) — ACTIVE|PAUSED|CANCELLED|COMPLETED (minimal derivable set; exact
  transition triggers/authorization for PAUSE and COMPLETED NOT authoritatively established; see
  OQ-008-03)
is_anonymous (BOOLEAN, NOT NULL, DEFAULT FALSE) — propagates to generated Occurrences' Donations
starts_at (DATETIME, NOT NULL), ends_at (DATETIME, NULLABLE — open-ended by default)
next_occurrence_at (DATETIME, NULLABLE)
cancelled_at / cancelled_by_principal_id (DATETIME / FK principals RESTRICT, NULLABLE)
timestamps
```

`donation_recurring_occurrences`:

```
id (BIGINT PK), ulid (CHAR 26, unique)
recurring_plan_id (FK donation_recurring_plans, RESTRICT, NOT NULL)
scheduled_at (DATETIME, NOT NULL)
donation_id (FK donations, RESTRICT, NULLABLE — set once this occurrence generates its Donation)
status (VARCHAR 16, NOT NULL) — SCHEDULED|GENERATED|SKIPPED|FAILED (minimal derivable set; exact
  retry/backoff policy for FAILED, and who/what may SKIP, NOT authoritatively established; see
  OQ-008-03)
generated_at (DATETIME, NULLABLE)
failure_reason (TEXT, NULLABLE)
timestamps
```

**This specification defines the schema and ownership boundary only.** The generation
scheduling/execution mechanism (what triggers moving a SCHEDULED Occurrence to GENERATED, i.e. an
actual cron/job that creates the child Donation) is OUT OF SCOPE pending OQ-008-02/03 — see "Open
Human Decisions." Creating the tables now (with FK integrity) without building the engine is a
deliberate, minimal foundation, matching HD-IMP007-02's own "minimum canonical foundation, no
premature capability" precedent.

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
                                 within a bounded window —
                                 window value: OQ-008-04)
```

```
PENDING    -> SUCCEEDED   ONLY via the authorized transition surface an Authorized Financial
                          Consequence (IMP-011, itself downstream of an IMP-009 Payment outcome)
                          invokes — see "Financial Boundary". IMP-008 does not decide WHEN this
                          happens; it defines the resulting state and its invariants.
PENDING    -> FAILED      same authorized-consequence surface, failure outcome.
PENDING    -> EXPIRED     system-initiated (no Payment outcome arrived within the configurable
                          timeout — see OQ-008-04); never donor/admin-initiated directly.
PENDING    -> CANCELLED   donor (own, unauthenticated-guest via a signed reference, or
                          authenticated) or an authorized admin, before any Payment outcome
                          arrives. See "Authorization / RBAC".
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
BR-6  A Recurring Plan requires an authenticated donor (donor_principal_id NOT NULL) — see
      "Domain Model" rationale.
BR-7  A terminal Donation state (SUCCEEDED/FAILED/CANCELLED/EXPIRED) is never mutated back to
      PENDING or to any other terminal state.
BR-8  Donation.campaign_id, donor_principal_id, guest_name, guest_email, amount_minor, currency,
      is_anonymous, recurring_occurrence_id are immutable after creation (see "Domain Model").
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
                                   resource state PENDING. A guest's own cancellation path (no
                                   account to authenticate) is NOT defined here — see OQ-008-05.

Admin cancelling a PENDING
  Donation:                        Authenticated AND permission donation.cancel AND scope
                                   ORGANIZATION AND resource state PENDING.

System/authorized-consequence
  transition (PENDING ->
  SUCCEEDED/FAILED, or ->
  EXPIRED):                        NOT reachable through any human-facing permission. Reserved for
                                   an authorized System Principal invocation only (mirrors
                                   Principal::principal_kind = System, IMP-003) — IMP-009/011
                                   define WHO/WHAT calls this; IMP-008 defines only that the
                                   surface exists and is inaccessible to ordinary Human Principals.

Recurring Plan create/pause/
  cancel (donor, own):              Authenticated AND permission
                                   donation.recurring_plan.manage AND scope OWN. Exact PAUSE
                                   semantics: see OQ-008-03.
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
  idempotency_key (VARCHAR 100, NULLABLE, UNIQUE) — see OQ-008-05 for its authoritative contract
  succeeded_at / failed_at / cancelled_at / expired_at (DATETIME, NULLABLE)
  cancelled_by_principal_id (FK principals.id, RESTRICT, NULLABLE)
  timestamps

  Indexes: campaign_id, donor_principal_id, status, recurring_occurrence_id
  Unique: ulid, idempotency_key (where not null)
  CHECK (app-level guard always; DB-level CHECK where the driver supports it, mirroring
    `principals`' own migration comment on SQLite not carrying the CHECK — the ONLY enforcement
    layer on SQLite is therefore the application-level guard, same as Principal::isConsistent):
    (donor_principal_id IS NOT NULL) OR (guest_name IS NOT NULL AND guest_email IS NOT NULL)

donation_recurring_plans
  id (BIGINT PK), ulid (CHAR 26, unique)
  donor_principal_id (FK principals.id, RESTRICT, NOT NULL)
  campaign_id (FK campaigns.id, RESTRICT, NOT NULL)
  amount_minor (BIGINT UNSIGNED, NOT NULL), currency (CHAR 3, NOT NULL)
  frequency (VARCHAR 16, NOT NULL) — value set: OQ-008-02
  status (VARCHAR 16, NOT NULL, DEFAULT 'ACTIVE')
  is_anonymous (BOOLEAN, NOT NULL, DEFAULT FALSE)
  starts_at (DATETIME, NOT NULL), ends_at (DATETIME, NULLABLE)
  next_occurrence_at (DATETIME, NULLABLE)
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

Observable invariant (not an implementation mechanism):

```
INV-1  Two donation-creation requests that represent the SAME donor intent (e.g. a network retry
       or a double form submit) MUST NOT result in two independent Donation rows both capable of
       independently reaching SUCCEEDED and triggering two separate financial consequences.
```

**Mechanism NOT invented here.** No client-generated idempotency-key contract exists anywhere in
this repository as of IMP-007 (grepped; none found). This specification defines the storage slot
(`donations.idempotency_key`, nullable unique) an implementer MAY populate from a
client-supplied header if the Human authorizes one, but does **not** mandate a specific header
name, TTL, or scope — see **OQ-008-05**. Until that Human Decision is made, IMP-008's own
acceptance criteria (see "Acceptance Criteria") test only the narrower, server-side-only invariant
that is fully determinable without inventing a client contract: submitting the identical
`idempotency_key` value twice within its uniqueness window is rejected (standard unique-constraint
behavior), while the broader "duplicate double-click with no idempotency key supplied" case
remains an accepted, explicitly flagged gap pending OQ-008-05 — never silently declared solved.

## Concurrency

```
Donation status transitions (PENDING -> *) use a single-row, single-writer transaction (SELECT ...
  FOR UPDATE or the Laravel-equivalent locking read) mirroring the transactional discipline Q26
  already canonized for critical audit-adjacent mutations, applied here by analogy since a
  Donation's transition to SUCCEEDED is financial-reference-adjacent even though it is not itself
  a Ledger post. Two concurrent transition attempts on the same Donation MUST NOT both succeed —
  the second observes the already-terminal state and is rejected per BR-7.
Recurring Occurrence generation (when built, per OQ-008-02/03) must not generate two Donations for
  the same Occurrence — enforced by the `donation_recurring_occurrences.donation_id` unique
  constraint (see "Database Impact").
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
Duplicate idempotency_key:                                     typed conflict exception (409-style),
                                                              no second Donation row created.
```

## Audit Requirements

Reuses IMP-004's `AuditEventRegistry`/`AuditEventDefinition` exclusively — no parallel mechanism.
Mirrors `CampaignAuditEventRegistrar`'s exact shape (NonCritical, GENERAL visibility, ORGANIZATION
scope, `hasFinancialReference: true` where the event carries amount/currency, actor kind Human for
donor-facing events and System for the authorized-consequence transition):

```
donation.created            actor: Human (or unauthenticated/guest — see below) | financial
                             reference: true | payload: campaign_id, amount_minor, currency,
                             is_anonymous, is_guest (bool)
donation.succeeded          actor: System | financial reference: true | payload: donation_ulid
donation.failed             actor: System | financial reference: true | payload: donation_ulid
donation.expired             actor: System | financial reference: true | payload: donation_ulid
donation.cancelled          actor: Human | financial reference: true | payload: donation_ulid,
                             cancelled_by_principal_id
donation.recurring_plan.created   actor: Human | financial reference: true
donation.recurring_plan.cancelled actor: Human | financial reference: true
```

`donation.created` for a guest (no Principal) requires an actor-kind decision the existing
`AuditActorKind` enum may not yet cover (Human/System/Integration — an unauthenticated public
visitor is none of these cleanly). **This is a gap, not invented here** — see OQ-008-06.

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
Idempotency:              duplicate idempotency_key rejected (where supplied) — see "Idempotency"
                          for the explicit scope limit of this test given OQ-008-05.
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
                          behavioral generation-engine tests, since that engine is OUT OF SCOPE
                          pending OQ-008-02/03.
```

## Acceptance Criteria

```
AC-008-001
Given an unauthenticated guest visitor and a PUBLISHED Campaign for which
  CampaignEligibilityResolver::isDonationEligible() is true
When they submit a donation with a valid amount/currency, guest_name, and guest_email
Then a Donation row exists in PENDING status, ulid-identified, donor_principal_id null,
  guest_name/guest_email populated, and a donation.created audit event is recorded.

AC-008-002
Given an authenticated donor and the same eligible Campaign
When they submit a donation with a valid amount/currency
Then a Donation row exists in PENDING status with donor_principal_id set to their own Principal,
  guest_name/guest_email both null, and a donation.created audit event is recorded.

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
Given two donation-creation requests carrying the identical idempotency_key
When both are submitted
Then only the first creates a Donation row; the second is rejected with a typed conflict exception
  (scope limited to the case where a key was actually supplied — see OQ-008-05).

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
```

## External Verification

```
Recurring donation operational rules (frequency values, "hybrid" semantics, pause/resume,
  retry/backoff for failed occurrence generation) — EXTERNAL VERIFICATION / Human Decision
  required; see OQ-008-02, OQ-008-03.
Client idempotency-key contract (header name, format, TTL) — EXTERNAL VERIFICATION / Human
  Decision required; see OQ-008-05.
Donation expiration timeout duration — EXTERNAL VERIFICATION / Human Decision required (or an
  explicit "configurable, no default asserted" resolution mirroring Q17's retention-policy
  pattern); see OQ-008-04.
```

## Open Human Decisions

```
OQ-008-01
Question:              Must a Donation ever target a Program or Fund directly, without a specific
                        Campaign (a "general fund" donation), or is Campaign-only correct for v1?
                        Relatedly, must a Recurring Plan ever support a guest (non-authenticated)
                        donor?
Why required:           IMP-007 only built a donation-eligibility contract at Campaign granularity
                        (CampaignEligibilityResolver); Program has no starts_at/ends_at/eligibility
                        of its own. Guest Recurring Plans have no durable cross-session identity to
                        resume against under the current Identity model (IMP-002).
Existing evidence:      MASTER-REQUIREMENTS.md §6 lists "Campaign, Program, Donation, Recurring
                        Donation" as separate domains without specifying which of Program/Fund a
                        Donation may target directly; MODULE-OWNERSHIP.md §3/§4 keep Campaign,
                        Program, and Donation as distinct modules without resolving this
                        specifically.
Options if useful:      (a) Campaign-only for v1 (this spec's current design); (b) also allow
                        Program-level donation once/if Program gains its own eligibility contract;
                        (c) also allow Fund-level ("general") donation.
Architecture impact:    (b)/(c) would require either extending Program with its own eligibility
                        contract (an IMP-007 change, out of this IMP's authority) or defining a new
                        Fund-level eligibility rule (new architecture, needs an ADR).
Implementation impact:  (a) requires no schema change beyond what this spec already defines; (b)/
                        (c) would add nullable program_id/fund_id columns and additional BR/AC.

OQ-008-02
Question:               What are the allowed Recurring Plan frequency values (e.g. WEEKLY,
                        MONTHLY, others), and what specifically does "Hybrid" (Q5) add beyond a
                        fixed-schedule recurring donation?
Why required:           Q5 in the Human Decision Register is a one-line label ("C Hybrid Recurring
                        Donation") with no further detail materialized anywhere in this repository
                        (checked HUMAN-DECISION-REGISTER.md's own "Extended Decisions" sections,
                        which only elaborate Q21-Q33; MASTER-ARCHITECTURE.md has no further
                        mention). Guessing an enum or a "hybrid" mechanism would violate this
                        task's explicit "do not guess" instruction.
Existing evidence:      Q5 = "C Hybrid Recurring Donation" (one line); this task's own §11 supplies
                        the entity chain (Recurring Plan -> Occurrence -> Donation -> Payment) but
                        not the frequency/hybrid operational detail.
Options if useful:      none proposed — the Human is the sole source for what "Hybrid" means here.
Architecture impact:    Determines whether `frequency` is a small fixed enum or needs a richer
                        "plan configuration" concept.
Implementation impact:  Blocks building the Recurring Occurrence generation engine (schema exists
                        regardless — see "Domain Model").

OQ-008-03
Question:               What are Recurring Plan PAUSE/RESUME/COMPLETED transition triggers and
                        authorization, and what retry/backoff policy applies to a FAILED
                        Occurrence?
Why required:           Same gap as OQ-008-02 — no authoritative source defines these operational
                        rules.
Existing evidence:      None beyond the entity chain already cited.
Options if useful:      none proposed.
Architecture impact:    Determines whether pause/resume is donor-self-service, admin-only, or both.
Implementation impact:  Blocks building the Recurring Occurrence generation engine.

OQ-008-04
Question:               What is the Donation PENDING -> EXPIRED timeout duration (or should it, per
                        Q17's precedent for retention, remain "configurable, no default asserted
                        here" until a future authorized policy sets it)?
Why required:           No authoritative value exists anywhere in the reviewed documents.
Existing evidence:      None. Q17 (Configurable Retention Policy Matrix) is the closest structural
                        precedent for "configurable, not invented here."
Options if useful:      (a) Human supplies an exact default (e.g. 24 hours); (b) treat as
                        Q17-style: configurable, no default asserted, EXPIRED transition mechanism
                        built but never fires without an explicit configured value.
Architecture impact:    None either way — only which of (a)/(b) governs whether AC-008 acceptance
                        testing can assert a concrete duration.
Implementation impact:  (b) is safely implementable now without further Human input; (a) requires
                        the exact value before AC can be finalized.

OQ-008-05
Question:               Should Donation creation support/require a client-generated idempotency
                        key (header name, format, TTL/uniqueness window), or is the narrower
                        server-side-only guarantee (unique constraint when a key happens to be
                        supplied) acceptable for v1? Relatedly, how does an unauthenticated guest
                        cancel their own PENDING Donation (no account to authenticate against)?
Why required:           No client idempotency-key contract exists anywhere in this repository as
                        of IMP-007 (grepped for one; none found). "Do not invent implementation
                        mechanisms prematurely... flag it explicitly" (this task's §16) applies
                        directly. The guest-cancel gap is a direct consequence of the "do not
                        force account creation" instruction combined with "Authorization / RBAC"
                        requiring OWN-scope ownership, which a guest cannot satisfy without a
                        session/token mechanism this spec does not invent.
Existing evidence:      None for either sub-question.
Options if useful:      Idempotency: (a) mandate a client Idempotency-Key header now; (b) defer
                        entirely to IMP-009 (Payment), since a real duplicate-charge risk only
                        materializes once Payment exists; (c) accept the narrower guarantee this
                        spec already defines as sufficient for v1. Guest cancel: (a) no guest-
                        cancel capability in v1 (guest Donations self-resolve via EXPIRED if
                        abandoned); (b) a signed, single-use cancellation link emailed at creation.
Architecture impact:    A signed-link mechanism (guest cancel option b) would need its own token/
                        signing contract — new, not currently established anywhere.
Implementation impact:  (c)/(a) for guest-cancel are both implementable without further design;
                        (b) requires new infrastructure.

OQ-008-06
Question:               What AuditActorKind applies to a donation.created event authored by an
                        unauthenticated guest? The existing enum (per IMP-004) models Human/
                        System/Integration; an anonymous public visitor is not cleanly any of
                        these.
Why required:           IMP-004's AuditActorKind is a closed, IMP-004-owned enum (Level 3/4
                        architecture) — IMP-008 is not authorized to add a value to it unilaterally
                        without either Human Decision or an ACR against IMP-004's own registry.
Existing evidence:      app/Enums/AuditActorKind.php (as extended by CampaignAuditEventRegistrar's
                        usage) currently only demonstrates Human; System/Integration exist per
                        Principal::principal_kind but a guest-attributed audit actor kind is not
                        evidenced anywhere.
Options if useful:      (a) record guest donation.created events as actor kind Human with a null/
                        guest-marker subject (if the registry's schema permits); (b) add a new
                        AuditActorKind (e.g. Guest/Anonymous) via IMP-004 change control; (c) record
                        as System (the platform accepting an anonymous submission), with guest_name/
                        guest_email captured in the payload instead of the actor.
Architecture impact:    (b) changes a locked IMP-004 enum — requires an ACR/ADR, not a unilateral
                        IMP-008 decision.
Implementation impact:  Blocks finalizing the exact donation.created AuditEventDefinition actor-kind
                        list until resolved; (a)/(c) are implementable without an architecture
                        change if the Human selects one of them.

OPEN HUMAN DECISIONS: 6
```

## Traceability

```
Requirement/Decision                    Spec Section(s)              Test/AC
Q5  Hybrid Recurring Donation           §Domain Model (Recurring),   OQ-008-02/03 (blocks full AC)
                                         §Open Human Decisions
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
DATABASE-INVARIANTS.md financial        §Idempotency, §Concurrency   AC-008-014, AC-008-017
  idempotency
```

## Specification Self-Review

Performed before submission for Human Spec Approval, per this task's §24. Findings classified
BLOCKER/MAJOR/MINOR/EDITORIAL; all findings below were patched into the specification text above
before this document was finalized (none are left open in the spec body — only genuine Human
Decisions remain open, tracked in "Open Human Decisions").

```
Requirement traceability:        PASS — see "Traceability" above; every Human Decision cited in
                                  this document (Q5/Q6/Q20/Q21/Q22) and every IMP-007 contract
                                  reused (CampaignEligibilityResolver, Money, CurrencyMinorUnits)
                                  maps to a spec section and at least one AC or an explicit OQ.
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
Authorization:                    PASS (structurally) — full AND-chain applied per operation in
                                  "Authorization / RBAC"; the one intentional exception (public
                                  guest creation, no Authenticated term) is explicitly called out
                                  as intentional, not an oversight, mirroring how this task's own
                                  §14 anticipated it ("distinguish public/guest Donation entry
                                  points where authentication is intentionally not applicable").
                                  MINOR gap (not fixed, tracked as OQ-008-05): guest self-service
                                  cancellation has no defined mechanism — flagged, not silently
                                  dropped.
Audit:                            PASS (structurally), with one explicit gap (OQ-008-06: guest
                                  actor-kind) — flagged rather than guessed, since AuditActorKind
                                  is a locked IMP-004 enum this document may not unilaterally
                                  extend.
Idempotency:                      PASS (scoped) — observable invariant defined; the mechanism gap
                                  is explicitly flagged as OQ-008-05 rather than either invented or
                                  silently ignored.
Concurrency:                      PASS — locking discipline specified by analogy to Q26's existing
                                  transactional pattern; MySQL-only test requirement stated
                                  explicitly per this task's §19 instruction.
Database invariants:               PASS — RESTRICT FKs throughout (no CASCADE on financial-adjacent
                                  references, mirroring IMP-007's own campaigns.program_id/
                                  fund_id RESTRICT choice), CHECK-or-app-guard for the guest/
                                  authenticated XOR invariant mirroring Principal's own pattern,
                                  unique donation_id on Occurrence preventing double-generation.
Shared-hosting compatibility:      PASS — no new infrastructure requirement introduced (no queue-
                                  only mechanism mandated beyond what "Tests Required" defers to
                                  the not-yet-built generation engine, which is itself out of
                                  scope pending OQ-008-02/03).
Future IMP boundaries:             PASS — "Out of Scope" explicitly enumerates every later IMP
                                  (009-017 and beyond) and exactly what each owns instead.

BLOCKER:   0
MAJOR:     0
MINOR:     1  (guest self-service cancellation mechanism undefined — tracked as part of OQ-008-05,
              not a silent gap)
EDITORIAL: 0
```

## Definition of Done

Checklist — see [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md).
This document satisfies "Definition of Ready" (objective/scope/out-of-scope/architecture/business
rules/security impact/DB impact/acceptance criteria are all defined above); "No unresolved Human
Decision" is the one Definition-of-Ready item this document does NOT yet satisfy — 6 Open Human
Decisions remain, by design, pending Human Spec Approval and resolution. Implementation
("Definition of Done" proper) does not begin until Human Spec Approval is recorded and, for any AC
that depends on an open decision above, that decision is resolved.
