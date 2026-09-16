# IMP-007 — Campaign + Program + Fund

```
Status:                 SPECIFICATION PASS (patched per HD-IMP007-01..04; self-audited, Round 1
                         and Round 2, 0 BLOCKER / 0 MAJOR / 0 OPEN HUMAN DECISIONS)
Authority:               Human Decision — "HUMAN DECISION — IMP-007" (external approved business
                         baseline), applied against existing repository architecture (IMP-001..006,
                         all FINAL/LOCKED).
Baseline commit:         master @ f017cc7
Standing model owner:    Kimi K2.7 Code (docs/00-governance/MULTI-MODEL-OWNERSHIP.md) — OVERRIDDEN
                         for this stage only.
```

## Implementation Ownership (IMP-007-specific override)

Per Human Decision — IMP-007 §1 ("OWNERSHIP OVERRIDE"), Claude Code (model: Claude Sonnet 5,
`claude-sonnet-5`) is authorized as the primary working agent for IMP-007: repository audit,
specification drafting, architecture analysis, security/RBAC analysis, test planning,
implementation after Human Stage Gate approval, and finalization work when separately authorized.
This override applies to IMP-007 ONLY and does not alter
`docs/00-governance/MULTI-MODEL-OWNERSHIP.md`'s standing assignment for any other IMP (recorded
there as `IMP-007/008/013/018/019/020/023 -> Kimi K2.7 Code`). The standing document is left
unmodified, mirroring the IMP-006 precedent of recording a stage-specific override in the spec's
own ownership section rather than editing the governance matrix.

## Human Decisions Applied (HD-IMP007-01 .. HD-IMP007-04)

Recorded here as this specification's own evidence, mirroring the IMP-005 precedent (Human
Decisions were first recorded in `IMP-005-cms.md`'s own specification text, and only later
transcribed into `docs/01-requirements/HUMAN-DECISION-REGISTER.md` under a separate, explicit
Human authorization for that transcription specifically). No edit is made to
`HUMAN-DECISION-REGISTER.md` by this patch — that register's own Change Control section requires
its own distinct authorization, not yet given for IMP-007.

```
HD-IMP007-01  CAMPAIGN APPROVAL / PUBLICATION — Option B selected. APPROVED is a distinct
              persisted lifecycle state (DRAFT -> REVIEW -> APPROVED -> PUBLISHED -> CLOSED).
              CAMPAIGN_APPROVE (REVIEW -> APPROVED) and CAMPAIGN_PUBLISH (APPROVED -> PUBLISHED)
              are independently enforceable permissions and independent service transitions.
              Distinct audit events per transition. No automatic expiry/unapproval of APPROVED
              Campaigns. IMP-008 must treat only PUBLISHED Campaigns satisfying the canonical
              eligibility contract (HD-IMP007-03) as donation-eligible.
HD-IMP007-02  MONEY REPRESENTATION — Option C selected. A canonical Money value object is
              established from IMP-007 onward. Persisted representation: amount_minor (integer
              minor-unit) + currency (ISO 4217 code). Floating-point is never the authoritative
              monetary representation. Minor-unit digit count per currency is centralized, not
              assumed to be 2 for every currency. IMP-007 implements only the minimum Money
              foundation needed for Campaign.target_amount_minor — no Donation/Payment/Ledger/
              Financial Consequence Posting logic is implemented here.
HD-IMP007-03  CAMPAIGN PERIOD / ELIGIBILITY — Option B selected. Campaign donation-availability
              requires BOTH status = PUBLISHED AND satisfaction of starts_at/ends_at period
              constraints, evaluated through one canonical, reusable eligibility contract (not
              duplicated per-caller). No automatic status mutation occurs from time passing alone
              (no scheduler introduced in IMP-007). Administrative status (PUBLISHED) and
              effective donation-availability are distinguishable concepts.
HD-IMP007-04  DEFAULT BUSINESS DATA — Option A selected (confirmed, unchanged from the
              specification's original assumption). No default Program/Campaign/Fund is seeded.
              No implicit "General Fund" or equivalent placeholder is created. IMP-006's
              ThemeSystemDefaultSeeder is a technical rendering-fallback precedent only, not a
              precedent for seeding business-domain records. Every Fund reference must be
              explicit; no code may depend on a hard-coded seeded Fund name or id.
```

---

## 1. Overview

IMP-007 introduces the first business-domain data this platform will hold: **Program**, a
higher-level organizational/content grouping for a charitable initiative; **Campaign**, a specific
fundraising initiative that can (once IMP-008 exists) receive Donations; and **Fund**, the
financial designation/restriction context a Campaign is attached to. IMP-007 establishes these
three entities, their lifecycle, their authorization boundaries, and their audit trail — but does
**not** implement any donation, payment, or ledger behavior. It is the first "real" business-domain
stage built on top of the platform foundation (IMP-001..004) and the presentation stages (IMP-005
CMS, IMP-006 Theme Engine).

## 2. Problem Statement

Before Donations (IMP-008) can exist, the platform needs a stable, authorized, auditable way to
define *what* a donation would be given to: a named Campaign, optionally grouped under a Program,
with an unambiguous Fund designation. None of this exists yet — `docs/06-domains/campaign/`,
`docs/06-domains/finance/` are empty placeholders, and no Campaign/Program/Fund code exists
anywhere in the repository (confirmed by `git branch -a` and repository-wide search — see the
prior IMP-007 Stage-Gate discovery report).

## 3. Goals

```
Establish Program, Campaign, Fund as first-class, ULID-identified, audited domain entities.
Establish a Campaign lifecycle (DRAFT -> REVIEW -> APPROVED -> PUBLISHED -> CLOSED, per
  HD-IMP007-01) with a rejection path back to DRAFT, enforced authoritatively at the backend.
Establish a canonical Money value object (integer minor-unit + currency code, per HD-IMP007-02)
  as the first monetary representation convention in this codebase.
Establish one canonical, reusable Campaign donation-eligibility contract (per HD-IMP007-03) that
  distinguishes administrative status from effective donation-availability, for IMP-008 to
  consume later without re-deriving the formula.
Establish Program <-> Campaign (one-to-many, optional) and Campaign <-> Fund (many-to-one,
  mandatory before publish) relationships with data-integrity guarantees.
Reuse IMP-003 RBAC (permissions, policies, PrincipalService) — no second authorization system.
Reuse IMP-004 audit event registration/logging pattern — no second audit system.
Reuse IMP-005/006 architectural conventions (ULID trait, actor-tracking FK pattern, media
  upload/validation pipeline shape, optimistic-concurrency "expected_version" idiom) without
  touching any IMP-005/006 locked file.
Expose a stable, minimal read-only projection contract that a later stage (Theme/CMS or IMP-008)
  can consume for "latest campaigns"-style presentation, per IMP-005 §16's explicit deferral of
  that projection contract to "that domain's stage (IMP-007+)".
Protect against orphaned Campaigns, ambiguous Fund designation, unauthorized publication, and
  destructive deletion of historically-referenced records.
```

## 4. Non-Goals

```
Donation transaction lifecycle, payment gateways, callbacks, reconciliation, refunds,
  settlement, disbursement, receipts, ledger/accounting — all explicitly IMP-008+ (Human
  Decision §12/§16).
Multi-fund allocation per Campaign (one Campaign has exactly one Fund at a time — see section 8).
Slug-rename redirect history for Campaign/Program (unlike CMS's cms_paths REDIRECT mechanism) —
  the Human business baseline did not request SEO-continuity infrastructure for this stage;
  Campaign/Program slugs are simple unique columns with no rename-tracking in v1. Flagged as a
  documented future enhancement, not silently dropped.
Full Theme-composable section/component authoring for Campaign/Program public pages (i.e.
  extending IMP-006's closed `content_kind` set). v1 renders Campaign/Program through
  IMP-007-owned Vue pages that consume the active Theme's branding tokens read-only (see
  section 20) — this requires NO IMP-006 file changes. Full section/component composability for
  these content types is deferred; seesection 29 "Risks" and LOCKED-CONTRACT IMPACT below.
Any new authorization Role (e.g. "reviewer", "campaign manager") or new scoped-authority
  assignment mechanism — none exists in the repository yet, and Human Decision §8 explicitly
  forbids inventing new roles.
```

## 5. Authoritative Requirements

Restated from the Human Decision (source of truth; not paraphrased into new obligations):

```
Domain model:    PROGRAM (0..N) -> CAMPAIGN (0..1 PROGRAM) -> FUND (1, mandatory before publish)
Program:         organizational/content context; not a payment transaction; identity, public
                 description/content, publication visibility, associated Campaigns, media where
                 supported by existing architecture, auditability.
Campaign:        identity, title/name, description/story/content, fundraising purpose, target
                 amount where applicable, campaign period where applicable, public
                 presentation/media, publication/lifecycle state, relationship to Program,
                 relationship to Fund, ownership/management authority, audit history.
Fund:            designation/restriction context for money; conceptually separate from
                 Donation/Payment/Payment Attempt/Gateway Transaction/Settlement/Refund/
                 Disbursement; a Campaign needs an unambiguous Fund before accepting Donations.
Lifecycle:       DRAFT -> REVIEW -> APPROVED -> PUBLISHED -> CLOSED (HD-IMP007-01), with a
                 non-public/rejected path back to DRAFT during REVIEW. Approve and publish are
                 independent transitions gated by independent permissions.
Authorization:   reuse IMP-003; SUPER_ADMIN full management; ADMIN scope/permissions derived
                 from existing permission architecture, not invented; DONOR/public users get
                 read-only access to published information only; backend policy is authoritative,
                 frontend hiding is not a security boundary.
Approval split:  CREATE/EDIT, SUBMIT FOR REVIEW, APPROVE, PUBLISH, CLOSE are distinguishable
                 operations — not every editor may approve/publish.
IMP-008 boundary: no donations/payments/gateway schema; expose stable contracts IMP-008 can
                 reference later.
CMS/Theme:       reuse presentation capabilities where appropriate; do not build a second CMS,
                 media system, or theme system; report LOCKED-CONTRACT IMPACT before modifying
                 any IMP-005/006 file.
Audit:           integrate creation/material edit/submission/approval/publication/closure/Fund
                 assignment with IMP-004; no duplicate audit system.
Data integrity:  no orphan Campaigns, no ambiguous Fund, no unauthorized publication, no scope
                 bypass, no destructive deletion of historically-referenced records, no race
                 conditions in lifecycle transitions, no duplicate-transition side effects.
```

## 6. Existing Architecture Findings

(Full detail in the repository-audit evidence trail; summarized here as the load-bearing facts
this specification builds on.)

```
RBAC (IMP-003, LOCKED):
  PermissionRegistry: flat string-constant registry, module-grouped, seeded by code
    (`database/seeders/RbacPermissionSeeder.php`). Existing modules: rbac, identity, audit,
    content, theme.
  Policies (ContentPagePolicy, ThemePolicy): every method delegates to a shared
    `AuthorizesUsingRbac::authorizeRbac()` trait call with (permissionCode, scopeResolver,
    requestedScopeType, requestedScopeId, optional resourceStatePredicate).
  ScopeResolver: interface with scopeType(), resourceMatchesScope(), applyToQuery(),
    lockAndValidateTarget(). CMS/Theme both implement a trivial ScopeType::Organization
    resolver ("exactly one organization in this baseline").
  Only Role seeded: `super_admin` (bulk-granted every permission except `audit.*`). No
    donor/fundraiser/reviewer/manager role exists (RbacRoleSeeder's own comment: "Identity
    Creation != Role Assignment... no other operational Role is invented — those are later
    domain stages' own concern").
  App\Enums\ScopeType (LOCKED, Level 3, materialized from docs/05-rbac/DATA-SCOPE-MODEL.md)
    ALREADY reserves CAMPAIGN, PROGRAM, and FUND as scope-type values (alongside OWN,
    FUNDRAISER, PARTNER, BENEFICIARY_CASE, ASSIGNED_WORK, ORGANIZATION, GLOBAL_PLATFORM).
    requiresNullScopeId() puts Campaign/Program/Fund in the "needs a concrete scope_id" branch —
    i.e. the locked taxonomy anticipates per-Campaign/Program/Fund scoped staff authority, but
    "no further subdivision... is defined by this document" (DATA-SCOPE-MODEL.md) — no
    assignment mechanism, no resolver, no consuming role exists yet. This is a real, locked
    contract IMP-007 could start filling in, but doing so meaningfully requires a scoped role/
    assignment mechanism that does not exist and that the Human Decision forbids inventing.
    DECISION (this spec): v1 uses ScopeType::Organization (mirroring CMS/Theme's own choice,
    for the same reason — no scoped role/assignment table exists to populate a real scope_id).
    The reserved CAMPAIGN/PROGRAM/FUND scope types are left untouched for a later stage once a
    scoped staff-assignment mechanism is authorized. This is additive-only and breaks nothing.

CMS publishing (IMP-005, LOCKED):
  PublicationService: lockForUpdate() + DB::transaction() on every mutating method; a
    "REVIEW" or "PENDING_APPROVAL"-like intermediate state does NOT exist anywhere in this
    codebase today — CmsPage/CmsArticle.status is DRAFT|PUBLISHED|RETIRED|ARCHIVED only.
    IMP-007's REVIEW state has no existing precedent to copy verbatim; it is new lifecycle
    territory, designed in section 12 below from the Human's explicit instruction, not
    discovered by reuse.
  Optimistic concurrency idiom: `expected_*_version` parameter compared under lock, throwing a
    dedicated exception on mismatch (PageService::update()'s expected_edit_version,
    PublicationService::scheduleConfigure()'s expected_schedule_version,
    ::setHomepage()'s expected_page_id). IMP-007 reuses this exact idiom (section 13).
  cms_paths (path-claim table) is CMS-owned, FK'd only to page_id/article_id — extending it for
    Campaign/Program would touch a locked table. IMP-007 gets its own simple unique `slug`
    column per entity instead (section 9), not a shared path-claim system — scoped, not
    duplicated, since it isn't "a second CMS", just an ordinary unique-slug column, the same
    pattern countless non-CMS Laravel domains use.
  ContentResolverService::resolve()/PublicContentController are hardcoded to CmsPage/CmsArticle
    with no pluggable extension point — Campaign/Program public pages get their OWN routes
    (section 21), not funneled through IMP-005's catch-all resolver. No IMP-005 file is touched.

Theme (IMP-006, LOCKED):
  ThemeTemplateService::CONTENT_KINDS is a closed ['home','page','article'] list. Extending it
    to include 'program'/'campaign' would be a LOCKED-CONTRACT change (see below) — v1 avoids
    needing it: `PublicRenderer::activeTheme(): ?Theme` is already PUBLIC, and `Theme::branding()`
    is an ordinary public Eloquent relation. IMP-007's own public controllers call
    `app(PublicRenderer::class)->activeTheme()` and read `$theme->branding` directly to keep
    Campaign/Program pages visually consistent with the active Theme's color tokens/font,
    WITHOUT modifying any IMP-006 file. This is read-only consumption of an already-public
    contract, exactly like IMP-006 consumed IMP-005's ContentResolverService.

Audit (IMP-004, LOCKED):
  Domain-owned AuditEventRegistrar pattern: a plain class with one register(AuditEventRegistry)
    method building AuditEventDefinition instances, explicitly "NOT added inside
    AuditEventRegistry::registerCanonicalEvents() itself... the domain-owned extension point".
    Domain-owned AuditLogger: one record*() method per event, thin wrapper over AuditWriter,
    called inside the same transaction as the business mutation (append-only, non-critical
    failure semantics unless AuditCriticality says otherwise).
  AuditEventDefinition carries a `hasFinancialReference` flag, and a dedicated
  `AUDIT_READ_FINANCIAL_REFERENCE` permission (excluded from the super_admin bulk grant) already
  exists specifically to gate read access to financially-adjacent audit events. IMP-007 uses
  this flag on Fund-related events (section 17).

Models/migrations conventions:
  GeneratesUlid trait (creating-hook, `$model->ulid ??= (string) Str::ulid()`) — internal
    BIGINT PK stays server-side, `ulid` is the public identifier, `getRouteKeyName()` returns
    'ulid' for admin route-model binding.
  created_by_principal_id / updated_by_principal_id: non-nullable, `foreignId(...)->
    constrained('principals')->restrictOnDelete()`. Optional actor columns (e.g. an approver)
    follow the same FK-restrict shape but nullable.
  Principal / PrincipalService::forUser(User $user): Principal — idempotent
    firstOrCreate-by-human_user_id, resolved at the HTTP boundary by every controller in this
    codebase; IMP-007 controllers do the same.

Money/currency:
  NOTHING like a Money value object, decimal-currency column, or integer-cents convention existed
  anywhere in the repository prior to this specification. Per HD-IMP007-02, IMP-007 establishes
  the FIRST money representation in this codebase: a canonical `Money` value object over an
  integer minor-unit amount + ISO 4217 currency code, with per-currency minor-unit digit counts
  centralized in one registry rather than assumed — see section 8a.

Test infrastructure:
  tests/Support/TruncatesInMemorySqlite.php and the temporary, uncommitted phpunit.mysql.xml
  dual-database-verification approach (documented in docs/audits/IMP-005-FINALIZATION*.md) both
  remain the required verification path for IMP-007 (section 27).
```

## 7. Proposed Architecture

```
app/Models/Campaign/
    Program.php, Campaign.php, Fund.php
    Concerns/GeneratesUlid.php            (own copy — module-boundary discipline, per IMP-006's
                                            own precedent of NOT importing another domain's trait)
    ProgramMediaAsset.php, CampaignMediaAsset.php
app/Support/Money/
    Money.php                             (immutable VO — amount_minor + currency; HD-IMP007-02)
    CurrencyMinorUnits.php                (centralized per-currency minor-unit digit registry)
    Exceptions/UnknownCurrencyException.php
app/Services/Campaign/
    ProgramService.php, CampaignService.php, FundService.php
    CampaignLifecycleService.php          (submit/approve/reject/publish/close — five distinct
                                            transition methods per HD-IMP007-01, kept SEPARATE
                                            from CampaignService's plain field CRUD, mirroring
                                            IMP-005's PageService/PublicationService split)
    CampaignEligibilityResolver.php       (the canonical donation-eligibility contract,
                                            HD-IMP007-03 — status + period evaluation, consumed
                                            later by IMP-008 without re-deriving the formula)
    ProgramScopeResolver.php, CampaignScopeResolver.php, FundScopeResolver.php
                                            (ORGANIZATION-only, mirroring ContentScopeResolver)
    CampaignAuditEventRegistrar.php / CampaignAuditLogger.php
    ProgramMediaService.php, CampaignMediaService.php   (mirrors MediaService/ThemeAssetService)
    ProgramMediaTokenResolver.php, CampaignMediaTokenResolver.php
    CampaignProjectionResolver.php        (the "IMP-007+ projection contract" IMP-005 §16
                                            deferred to this stage — read-only, minimal DTO;
                                            not yet wired into any Theme component)
    Exceptions/ (CampaignValidationException, CampaignTransitionConflictException,
                 FundValidationException, CampaignMediaValidationException)
app/Policies/
    ProgramPolicy.php, CampaignPolicy.php, FundPolicy.php
app/Http/Controllers/Campaign/
    ProgramController.php, CampaignController.php, FundController.php   (admin)
app/Http/Controllers/
    PublicProgramController.php, PublicCampaignController.php            (public)
resources/js/Pages/Campaign/       (admin UI, mirrors Theme/Cms admin page shape)
resources/js/Pages/Public/ProgramShow.vue, CampaignShow.vue, CampaignIndex.vue
config/money.php                   (per-currency minor-unit registry data — HD-IMP007-02)
database/migrations/0001_07_01_NNNNNN_create_*_table.php
database/seeders/ (NONE add business data — HD-IMP007-04, no system-default Campaign/Fund)
```

Dependency map:

```
IMP-007
├── existing domain:      Principal/PrincipalService (IMP-002), nothing else reused directly
├── database:             principals FK target only; no other cross-domain FK
├── authorization:        PermissionRegistry, AuthorizesUsingRbac, ScopeResolver, ScopeType (IMP-003)
├── services:              new, module-scoped; no reuse of ContentResolverService/MediaService
│                          internals (only the PATTERN is mirrored, per the locked-file boundary)
├── money:                new, IMP-007-owned foundation (app/Support/Money) — no existing
│                          precedent reused, since none existed (HD-IMP007-02)
├── frontend:              new Vue pages; reads Theme::branding (IMP-006) read-only
├── audit:                AuditEventRegistry, AuditWriter, AuditEventDefinition (IMP-004)
└── tests:                TruncatesInMemorySqlite (test infra, additive change — see section 27)
```

## 8. Domain Model

```
Program
  id (BIGINT PK), ulid (char26, unique)
  name (string 150), slug (string 150, unique)
  summary (string 500, nullable), description_html (text, nullable — sanitized, mirrors
    ContentSanitizer's byte-limit discipline but IMP-007 owns its own sanitizer call, not
    IMP-005's ContentSanitizer class instance, to avoid a cross-domain runtime dependency on
    locked CMS internals; same sanitization LIBRARY/allow-list approach, separately instantiated)
  status (string 16: DRAFT|PUBLISHED|ARCHIVED)
  edit_version (unsigned int, default 0 — optimistic concurrency)
  created_by_principal_id / updated_by_principal_id (FK principals, restrict, NOT nullable)
  timestamps

Campaign
  id (BIGINT PK), ulid (char26, unique)
  program_id (FK programs, nullable, RESTRICT on delete)
  fund_id (FK funds, NOT nullable once PUBLISHED — nullable at DRAFT/REVIEW to allow authoring
    before a Fund is finalized; RESTRICT on delete)
  name (string 150), slug (string 150, unique)
  summary (string 500, nullable), description_html (text, nullable, sanitized)
  purpose (string 500, nullable — "fundraising purpose", distinct free-text field per Human §5)
  target_amount_minor (bigint, unsigned, nullable — integer minor-unit amount; "where applicable";
    see section 8a — HD-IMP007-02, no decimal/float column)
  currency (char 3, default from config('campaign.default_currency'), ISO-4217 — see section 8a)
  starts_at / ends_at (datetime, nullable — "campaign period where applicable"; see section 8b for
    the eligibility contract these two columns feed, per HD-IMP007-03)
  status (string 16: DRAFT|REVIEW|APPROVED|PUBLISHED|CLOSED — HD-IMP007-01 adds APPROVED)
  edit_version (unsigned int, default 0)
  submitted_at / approved_at / published_at / closed_at (datetime, nullable — audit-adjacent
    timestamps, mirrors CMS's own published_at-style bookkeeping; approved_at now marks entry to
    APPROVED and published_at separately marks entry to PUBLISHED — these were already modeled as
    distinct columns even before HD-IMP007-01, so no schema addition was needed to support the
    now-independent transitions, only a semantic clarification of when each is set)
  created_by_principal_id / updated_by_principal_id (FK principals, restrict, NOT nullable)
  submitted_by_principal_id / approved_by_principal_id / published_by_principal_id /
    closed_by_principal_id (FK principals, restrict, nullable — populated only once that
    transition has occurred, mirroring PublicationService's scheduled_by_principal_id pattern)
  timestamps

Fund
  id (BIGINT PK), ulid (char26, unique)
  name (string 150), code (string 50, unique — short reference code)
  restriction_note (text, nullable — free-text designation/restriction description)
  status (string 16: ACTIVE|ARCHIVED)
  created_by_principal_id / updated_by_principal_id (FK principals, restrict, NOT nullable)
  timestamps

ProgramMediaAsset / CampaignMediaAsset  (mirrors ThemeAsset's shape exactly, scoped per domain)
  id (BIGINT PK), ulid (char26, unique)
  program_id / campaign_id (FK, cascade — an asset has no independent existence outside its owner)
  disk, stored_filename, original_filename, mime_type, extension, size_bytes,
  width/height (nullable), sha256, status (ACTIVE|ARCHIVED)
  uploaded_by_principal_id (FK principals, restrict), archived_by_principal_id (FK, restrict,
    nullable), archived_at (nullable)
  timestamps
```

### 8a. Money representation (HD-IMP007-02 — canonical Money value object)

Per HD-IMP007-02, no money/currency convention existed anywhere in this repository, and IMP-007
now establishes the canonical one: `app/Support/Money/Money.php`, an immutable value object
constructed as `Money::ofMinorUnits(int $amountMinor, string $currency): self`, exposing
`amountMinor(): int`, `currency(): string`, and `format(): string` (a display-only formatted
string — e.g. `"Rp 10.000"` — computed by dividing `amountMinor` by `10 ** minorUnitDigits`, never
exposing a float as the authoritative value). Two Money instances are equal iff both `amountMinor`
and `currency` are equal; no arithmetic operations (`add`/`subtract`/`multiply`) are implemented in
IMP-007 — the minimum foundation needed for `Campaign.target_amount_minor` display and storage,
per HD-IMP007-02's explicit "minimum canonical Money foundation" instruction. No arithmetic means
no rounding-mode decision is needed yet; that remains a later stage's (IMP-009/010/011) concern
once real computation over Money values is required.

`app/Support/Money/CurrencyMinorUnits.php` centralizes the per-currency minor-unit digit count in
one place (`config/money.php`, an array of `ISO4217_CODE => digits`, sourced from the published
ISO 4217 standard minor-unit table — not invented business data), with a
`digitsFor(string $currency): int` lookup that throws `UnknownCurrencyException` for any currency
code not present in the registry, rather than silently defaulting to 2 digits (HD-IMP007-02:
"Do NOT assume every currency has exactly two decimal/minor-unit digits"). `config/money.php` is
seeded with entries for the currencies actually referenced elsewhere in this project's
documentation (at minimum `IDR`, `USD`) plus any other currency an implementer adds explicitly —
the registry is deliberately NOT exhaustive of all ~180 ISO 4217 currencies at v1 to avoid
importing unverified digit-count data wholesale; it is designed to be extended additively.

`campaigns.target_amount_minor` (bigint unsigned, nullable) + `campaigns.currency` (char 3,
defaulted from `config('campaign.default_currency')`) are the persisted columns (section 8).
These remain explicitly **non-authoritative display/goal figures**, not ledger entries —
consistent with Human Decision §6 ("Fund... NOT an authoritative mutable balance") and
MODULE-OWNERSHIP.md §3 ("Campaign progress is not an accounting balance"). No currency-conversion
logic is implemented; multi-currency handling beyond storing a code and its registered minor-unit
count is out of scope (Master Requirements lists "Multi-Currency" as a distinct future domain).
IMP-007 does NOT implement Donation, Payment, Ledger, or Financial Consequence Posting logic —
this section establishes only the representation contract those later stages will consume
(HD-IMP007-02).

### 8b. Campaign donation-eligibility contract (HD-IMP007-03)

`app/Services/Campaign/CampaignEligibilityResolver.php` — the ONE canonical, reusable contract
answering "is this Campaign currently donation-eligible", so IMP-008 never re-derives this formula
independently. Public method: `isDonationEligible(Campaign $campaign, ?DateTimeInterface $at =
null): bool` (defaults `$at` to `now()`).

```
isDonationEligible(campaign, at) :=
    campaign.status === PUBLISHED
    AND (campaign.starts_at IS NULL OR at >= campaign.starts_at)   -- inclusive lower bound
    AND (campaign.ends_at   IS NULL OR at <= campaign.ends_at)     -- inclusive upper bound
```

Null semantics (explicit, per HD-IMP007-03's instruction to define exact null/boundary
semantics): a null `starts_at` means no lower bound (eligible immediately upon PUBLISHED); a null
`ends_at` means no upper bound (eligible indefinitely once started); both null means eligible for
the entire time the Campaign remains PUBLISHED. Boundary semantics: both comparisons are
inclusive (`at == starts_at` and `at == ends_at` are both within the eligible window) — chosen for
simplicity and symmetry; there is no authoritative source requiring exclusive bounds.

This resolver does **not** mutate `status` — "no automatic status mutation is required merely
because time passes" (HD-IMP007-03) is satisfied structurally: the resolver is a pure read/query
function, never a writer. Administrative status (what an admin/actor set via
`CampaignLifecycleService`) and effective donation-availability (what
`CampaignEligibilityResolver` computes at query time) are therefore fully distinguishable: a
Campaign can be persisted as `PUBLISHED` while `isDonationEligible()` returns `false` (before
`starts_at`, or after `ends_at`). No scheduler is introduced to reconcile the two (HD-IMP007-03
explicitly forbids one in IMP-007). `PublicCampaignController`/`ProgramShow.vue`-equivalent pages
use this resolver's result only to decide DISPLAY (e.g. "opens on {date}" / "campaign has ended"
messaging) — it does NOT gate whether the page itself is publicly reachable (that remains
status-based, section 14).

## 9. Data Ownership

```
Program:  owned entirely by IMP-007. CMS/Theme own nothing about it (IMP-005 §16 restated).
Campaign: owned entirely by IMP-007 — title/description/goals/progress/lifecycle content per
  IMP-005 §16's explicit carve-out ("Campaign (IMP-007): campaign title/description/goals/
  progress/lifecycle content = Campaign-owned columns on campaign tables. CMS does NOT store
  campaign business content").
Fund:     owned entirely by IMP-007. Not touched by Ledger/Payment/Donation (those are IMP-008+
  and reference Fund by ulid/id later, never the reverse).
Slugs:    Campaign/Program each own a simple unique `slug` column — NOT the CMS cms_paths table.
Media:    ProgramMediaAsset/CampaignMediaAsset are IMP-007-owned tables, structurally mirroring
  (not reusing) ThemeAsset/CmsMediaAsset — no cross-domain FK into cms_media_assets or
  theme_assets, and no read of IMP-007 tables by IMP-005/006 services.
Branding read: IMP-007 reads Theme::branding (public Eloquent relation) and
  PublicRenderer::activeTheme() (public method) — read-only consumption, zero IMP-006 ownership
  claim over Theme data.
```

## 10. Data Flow

```
Admin creates Program (optional) -> Admin creates Campaign (optionally attached to Program) ->
Admin assigns/creates Fund -> Campaign authored in DRAFT -> submitted for REVIEW -> approved
(-> APPROVED, via CAMPAIGN_APPROVE) or rejected (-> back to DRAFT, with reason recorded in audit
metadata) -> published (APPROVED -> PUBLISHED, via CAMPAIGN_PUBLISH, requiring a valid ACTIVE
fund_id; see HD-IMP007-01) -> published Campaign is publicly visible at /campaigns/{slug}
regardless of its period, though its donation-eligibility (section 8b) may independently be
false before starts_at or after ends_at -> Campaign closed when the fundraising period ends
(manually, by an authorized actor — no automatic time-based closure in v1; HD-IMP007-03
explicitly forbids introducing a scheduler for this in IMP-007).
```

## 11. State / Lifecycle

```
Program:   DRAFT --publish--> PUBLISHED --archive--> ARCHIVED
           DRAFT --archive--> ARCHIVED   (a Program can be archived without ever publishing)
           PUBLISHED --unpublish--> DRAFT  (mirrors CMS's publish/unpublish symmetry)

Campaign:  DRAFT --submit (CAMPAIGN_SUBMIT)--> REVIEW
           REVIEW --reject (CAMPAIGN_APPROVE)--> DRAFT
                          (rejection reason recorded in audit metadata, no new persisted state)
           REVIEW --approve (CAMPAIGN_APPROVE)--> APPROVED
                          (HD-IMP007-01: a distinct persisted state, independent of publish; does
                          NOT require a valid Fund — content/story approval is separable from
                          go-live readiness)
           APPROVED --publish (CAMPAIGN_PUBLISH)--> PUBLISHED
                          (requires a valid, ACTIVE fund_id — BR-1; independently permissioned
                          from approve — an actor holding only CAMPAIGN_APPROVE cannot perform
                          this transition, and vice versa)
           PUBLISHED --close (CAMPAIGN_CLOSE)--> CLOSED
                          (terminal — no reopen path in v1, section 4 Non-Goals)
           DRAFT/REVIEW/APPROVED/PUBLISHED --update (CAMPAIGN_UPDATE)--> (same status)
                          (content edits do not force a state change at any pre-CLOSED status;
                          editing an APPROVED Campaign does NOT automatically revert it to REVIEW
                          or DRAFT — HD-IMP007-01 explicitly forbids inventing an automatic
                          expiry/unapproval policy; matching CMS's own "edit-while-published"
                          precedent, extended here to "edit-while-approved" by the same logic)

Fund:      ACTIVE --archive--> ARCHIVED
           ARCHIVED cannot be assigned to a new Campaign (validated at assignment time); an
           already-assigned ARCHIVED Fund does not retroactively invalidate the Campaign
           (historical integrity — Human §15 "no destructive... of historically referenced
           records").
```

## 12. Business Rules

```
BR-1  A Campaign cannot transition PUBLISHED unless it has a fund_id (a valid, ACTIVE Fund).
BR-2  A Campaign's fund_id may be changed at DRAFT/REVIEW freely; changing it once PUBLISHED
      requires CAMPAIGN_UPDATE (same as any content edit) and is audited with both old and new
      fund ulid in metadata (Human §9/§14 "Fund assignment/change" is explicitly listed as an
      auditable action).
BR-3  A Campaign's program_id is optional at every status. Detaching a Campaign from a Program
      (setting program_id to null) is allowed via CAMPAIGN_UPDATE; deleting a Program while any
      Campaign still references it is rejected at the DATABASE level (RESTRICT), not only by
      application pre-check (mirrors IMP-006's ThemeSchemaConstraintsTest discipline).
BR-4  DRAFT, REVIEW, and APPROVED campaigns may all be edited via CAMPAIGN_UPDATE alone (no
      further lifecycle permission required). Editing a REVIEW or APPROVED campaign does NOT
      reset it to an earlier state and does NOT require re-approval — a reviewer/approver
      evaluates the content as it stands at the moment they act (locked-row read at that moment);
      an APPROVED campaign edited after approval stays APPROVED until an actor with
      CAMPAIGN_PUBLISH explicitly publishes it or an actor with CAMPAIGN_APPROVE explicitly
      rejects it back to DRAFT (rejection is only reachable from REVIEW in v1 — there is no
      "un-approve" transition from APPROVED, since HD-IMP007-01 forbids inventing an automatic
      unapproval policy and no manual one was requested either).
CLOSED campaigns reject ALL field mutation (CAMPAIGN_UPDATE included) — CLOSED is
      content-frozen, historical record only. Enforced in the service layer AND as a
      resourceStatePredicate in CampaignPolicy::update() (defense-in-depth, mirrors
      ContentPagePolicy's publish()/archive() predicate pattern).
BR-5  Fund archival always succeeds and is idempotent, regardless of any existing Campaign
      reference (including a PUBLISHED one) — protection against an ARCHIVED Fund being used
      lives entirely at ASSIGNMENT time (a Campaign cannot select an ARCHIVED Fund) and at
      PUBLISH time (BR-1: a Campaign cannot move APPROVED -> PUBLISHED against a non-ACTIVE
      Fund), never as an archival-blocking check. This corrects an internally-inconsistent
      earlier draft of this rule (identified and fixed during implementation — see section 32
      SPEC-007-AUDIT-06); the corrected rule matches AC-007-016 and the Fund lifecycle note in
      section 11, both of which required archival to succeed regardless of existing references.
BR-6  Program/Campaign/Fund slugs are NOT frozen at any lifecycle status in v1 (no redirect/
      history mechanism exists — see Non-Goals). A slug may be changed at any status; a changed
      slug simply serves the new URL going forward, with no redirect preserved for the old one.
      This is a deliberate, documented v1 limitation, not an oversight (see Risks, R-3).
BR-7  Two simultaneous "publish" or "approve" requests for the same Campaign must not both
      succeed — enforced by `lockForUpdate()` + a status-predicate re-check inside the
      transaction (identical shape to PublicationService).
BR-8  Public page visibility (can this Campaign's page be reached at all) and donation
      eligibility (can it currently receive a donation) are two distinct concepts and must never
      be conflated (HD-IMP007-03). Visibility is governed by `status = PUBLISHED` alone (section
      14/21). Eligibility is governed exclusively by `CampaignEligibilityResolver` (section 8b).
      No code path may re-implement the eligibility date-window formula independently — every
      caller (including the future IMP-008) must call the resolver.
BR-9  All Campaign monetary values are stored and manipulated exclusively via the `Money` value
      object over an integer minor-unit amount (HD-IMP007-02). No float/double arithmetic is
      performed on `target_amount_minor` at any layer (validation, service, or presentation) —
      display formatting divides by the currency's registered minor-unit factor only at the
      final render step, never earlier, and never for storage or comparison.
```

## 13. Validation Rules

```
Program.name:            required, string, max 150
Program.slug:             required, string, max 150, unique, [a-z0-9-]+ pattern
Program.description_html: nullable, string, max 200KB (mirrors ContentSanitizer's MAX_BYTES),
                          sanitized before persistence (own sanitizer instance, same allow-list
                          approach as ContentSanitizer — no shared class dependency on IMP-005)
Campaign.name/slug:       same shape as Program
Campaign.target_amount_minor: nullable, integer, min 0, max PHP_INT_MAX-safe bigint ceiling
                          (no float accepted at the request-validation layer — a decimal-looking
                          input such as "10.50" is rejected, not silently truncated; the admin UI
                          collects a major-unit amount + currency and converts to minor units
                          server-side via `CurrencyMinorUnits::digitsFor()`, never client-side)
Campaign.currency:        nullable (defaults from config), exactly 3 uppercase letters, AND must
                          exist in the `CurrencyMinorUnits` registry (config/money.php) — an
                          unregistered currency code is rejected at validation time, never
                          silently assumed to have 2 minor-unit digits (HD-IMP007-02)
Campaign.starts_at/ends_at: nullable date; if both present, ends_at must be >= starts_at
Campaign.fund_id:         must reference an existing Fund; must be ACTIVE at time of PUBLISH
                          transition (checked at transition time, not merely at save time — a
                          Fund archived between DRAFT-save and PUBLISH-attempt is caught then)
Campaign.program_id:      must reference an existing Program if present (no status restriction —
                          a Campaign may attach to a DRAFT Program; only PUBLIC VISIBILITY of the
                          combination is affected, not the FK validity)
Fund.name/code:           required; code unique, max 50, [A-Z0-9_-]+ pattern
Media uploads:            same MIME-sniff + extension allow-list + dimension-bomb-guard pipeline
                          shape as MediaService/ThemeAssetService (own config/campaign.php
                          allowed_types list — jpg/jpeg/png/webp, mirrors Theme's tighter set
                          rather than CMS's broader one, since campaign imagery is presentation,
                          not document-attachment)
```

## 14. Authorization / RBAC

New `PermissionRegistry` constants (additive — no existing constant renamed or removed):

```
module 'program':  PROGRAM_VIEW, PROGRAM_CREATE, PROGRAM_UPDATE, PROGRAM_PUBLISH,
                    PROGRAM_ARCHIVE, PROGRAM_MEDIA_UPLOAD
module 'campaign':  CAMPAIGN_VIEW, CAMPAIGN_CREATE, CAMPAIGN_UPDATE, CAMPAIGN_SUBMIT,
                    CAMPAIGN_APPROVE, CAMPAIGN_PUBLISH, CAMPAIGN_CLOSE, CAMPAIGN_MEDIA_UPLOAD
module 'fund':      FUND_VIEW, FUND_CREATE, FUND_UPDATE, FUND_ARCHIVE
```

Per HD-IMP007-01, `CAMPAIGN_APPROVE` and `CAMPAIGN_PUBLISH` gate two genuinely independent
`CampaignLifecycleService` transitions (`approve()`: REVIEW -> APPROVED, and `publish()`: APPROVED
-> PUBLISHED) — an actor holding only one of the two permissions can perform only that one
transition; neither permission implies or grants the other. This is a real separation-of-duties
control, not merely forward-compatible plumbing.

`ProgramPolicy`, `CampaignPolicy`, `FundPolicy` mirror `ContentPagePolicy`/`ThemePolicy` exactly:
every method calls `authorizeRbac()` with `scopeResolver: new {Program|Campaign|Fund}ScopeResolver`,
`requestedScopeType: ScopeType::Organization`, `requestedScopeId: null` (see section 6 for why
Organization, not the locked Campaign/Program/Fund ScopeType values, is used in v1).
`CampaignPolicy::update()` carries a `resourceStatePredicate` rejecting CLOSED (BR-4).
`CampaignPolicy::submit()` requires DRAFT. `CampaignPolicy::approve()` (gated by CAMPAIGN_APPROVE)
requires REVIEW. `CampaignPolicy::publish()` (gated by CAMPAIGN_PUBLISH, a distinct method from
`approve()`) requires APPROVED. `CampaignPolicy::close()` requires PUBLISHED.

Only `super_admin` exists as a Role today (section 6) — in practice, for v1, one Role holds every
new permission (RbacRoleSeeder's existing "grant all non-audit permissions" bulk loop already
covers new module permissions with no seeder change needed, mirroring how IMP-006's permissions
were auto-granted). The distinct-permission design above exists so a future ADMIN-vs-SUPER_ADMIN
split (Human §8's "exact approval/publish authority must be derived from existing permission
architecture") is possible without another migration — RBAC's own architecture (Role
holds-many-Permissions) already supports narrowing this later purely through data (revoking one
permission from a future non-super_admin Role), no code change needed.

Public/donor access: `PublicProgramController`/`PublicCampaignController` require no
authentication and query only `status = 'PUBLISHED'` for visibility (section 8b/BR-8: visibility
is status-only, by design). Separately, per HD-IMP007-03, `PublicCampaignController` also calls
`CampaignEligibilityResolver::isDonationEligible()` and passes its boolean result (plus the raw
`starts_at`/`ends_at`) to the Vue page as display data — the page can then show "opens on
{date}"/"campaign has ended" messaging for a PUBLISHED-but-not-currently-eligible Campaign,
without ever hiding the page itself. `public.campaigns.index` filters to `status = 'PUBLISHED'`
only (not eligibility) — a full, still-visible list of published campaigns, consistent with
"administrative status and effective ... availability must be distinguishable" (HD-IMP007-03).

## 15. Scope Enforcement

`ScopeType::Organization` (see section 6/14) — `resourceMatchesScope()` returns true
unconditionally (single-organization baseline, identical to CMS/Theme). `applyToQuery()` is a
no-op. `lockAndValidateTarget()` returns null (no concrete scope-target row to lock for
ORGANIZATION, identical to CMS/Theme's own resolvers). No cross-tenant, cross-organization, or
cross-scope leakage is possible in v1 because there is exactly one scope in play — the same
structural argument IMP-005/006 finalization used, not a new claim.

## 16. Security Requirements

```
Backend authorization is authoritative — every state transition and every mutation is gated by
  a Policy method backed by PermissionRegistry + AuthorizesUsingRbac, never by frontend-only
  control hiding (mirrors ContentPagePolicy/ThemePolicy precedent exactly).
No mass assignment: models use $guarded (not $fillable-everything), mirroring
  CmsPage/ThemeAsset's guard pattern — server-computed/actor columns (status transition
  timestamps, *_by_principal_id, edit_version) are never client-writable.
IDOR: all admin routes bind by ULID (never internal BIGINT id) exposed to the client; internal
  id never appears in any HTTP response payload (mirrors IMP-005/006's own "internal BIGINT PKs
  never leave the server layer" rule).
File upload: same MIME-sniff (not client Content-Type), extension allow-list, dimension-bomb
  guard, SVG rejection as MediaService/ThemeAssetService (section 13) — no PHP-disguised-as-image
  vector, mirrors the exact adversarial test already proven for Theme (ThemeAssetServiceTest).
XSS: description_html sanitized before persistence (same allow-list-based sanitization approach
  as ContentSanitizer, own instance — see section 13); rendered via the same escape-on-output
  discipline Theme/CMS Vue pages use (no `v-html` on unsanitized input at any point).
CSRF: standard Laravel/Inertia CSRF token handling, no exception.
Race conditions: lockForUpdate() + status-predicate re-check inside DB::transaction() on every
  lifecycle transition (BR-7); edit_version optimistic-concurrency guard on plain field updates
  (mirrors PageService::update()'s expected_edit_version).
Duplicate submission: submit()/approve()/publish()/close() each re-verify current status under
  lock before mutating — a second concurrent identical request finds the status already changed
  and is rejected with a typed conflict exception, not a silent no-op or a double-audit-event.
Monetary integrity: `target_amount_minor` is an integer column manipulated exclusively through
  the `Money` value object (BR-9) — no floating-point representation exists at any layer, closing
  off an entire class of rounding/precision-drift defects before any real financial computation
  (IMP-008+) is built on top of it.
Audit-log bypass: every mutating service method calls the audit logger inside the same
  transaction as the business write (mirrors IMP-004/005/006 "append-inside" discipline) — no
  code path can mutate Program/Campaign/Fund state without also writing an audit event.
Not applicable to IMP-007 (no matching attack surface exists in this stage): horizontal/vertical
  privilege escalation beyond what RBAC already guards structurally (no per-user OWN-scope data
  is introduced here — see section 15), unsafe redirects (no redirect-issuing endpoint is added),
  SQL injection (Eloquent parameter binding throughout, no raw interpolation).
```

## 17. Audit Requirements

New domain-owned registrar/logger pair (`CampaignAuditEventRegistrar`/`CampaignAuditLogger`,
covering Program+Campaign+Fund+media events in one file grouping, mirroring how IMP-006 grouped
all theme.* events in one registrar despite spanning 8 sub-areas):

```
program.created, program.updated, program.published, program.unpublished, program.archived,
  program.media.uploaded, program.media.archived
campaign.created, campaign.updated, campaign.submitted, campaign.rejected, campaign.approved,
  campaign.published (HD-IMP007-01: two distinct events, one per independent transition — see
  section 11), campaign.closed, campaign.fund_assigned, campaign.media.uploaded,
  campaign.media.archived
fund.created, fund.updated, fund.archived
```

All `AuditCriticality::NonCritical`, persistence strategy `null`, `AuditVisibilityClass::General`,
`ScopeType::Organization`, actor kind `Human` (no System-actor events in v1 — no scheduler is
introduced, section 4 Non-Goals). `hasFinancialReference: true` is set specifically on
`fund.created`, `fund.updated`, `fund.archived`, and `campaign.fund_assigned` (these reference the
financial-designation context even though they post nothing to a Ledger), requiring
`AUDIT_READ_FINANCIAL_REFERENCE` to read them — consistent with that permission's existing,
locked purpose. All other events use `hasFinancialReference: false`.

## 18. Database Impact

```
New tables: programs, campaigns, funds, program_media_assets, campaign_media_assets.
No existing table is altered. No existing column is renamed, dropped, or retyped.
Foreign keys: campaigns.program_id -> programs.id (RESTRICT), campaigns.fund_id -> funds.id
  (RESTRICT), {program,campaign}_media_assets.{program,campaign}_id -> parent (CASCADE — an
  asset has no life independent of its owner, mirrors ThemeAsset's own FK-to-owner shape where
  applicable), all *_by_principal_id -> principals.id (RESTRICT).
Unique constraints: programs.slug, programs.ulid, campaigns.slug, campaigns.ulid, funds.code,
  funds.ulid, and the two media tables' ulid columns.
Indexes: campaigns(status), campaigns(program_id), campaigns(fund_id) — supporting admin list
  filtering and the FK lookups; campaigns(status, starts_at, ends_at) additionally supports the
  eligibility-window read pattern (section 8b) without a full table scan; no other index proposed
  without a demonstrated query need.
Money columns: campaigns.target_amount_minor is `bigint unsigned nullable` (not decimal — see
  section 8a, HD-IMP007-02); campaigns.currency is `char(3) nullable`. Neither column has a
  database-level CHECK against the `config/money.php` registry — currency validity against the
  registry is an application-layer validation concern (section 13), matching how every other
  closed-set string column in this codebase (e.g. `status`) is validated at the application layer,
  not via a DB-level enum/check constraint.
Nullable rules: see section 8 field list — every nullable column is nullable because the
  business rule genuinely allows absence (optional Program attachment, Fund only mandatory at
  PUBLISH, optional target/period), never nullable "just in case".
Rollback: each migration has a symmetric down() dropping the table it created, in reverse
  dependency order (media tables and campaigns before programs/funds) — standard Laravel
  migration reversibility, nothing bespoke.
No data lifecycle/retention job is introduced (no purge, no scheduled deletion) — matches
  Human §7's explicit "do not hard-delete financially referenced Campaigns" and the absence of
  any requested retention policy for this stage.
```

## 19. Backend Impact

New models, services, policies, controllers, migrations, seeders — none — per section 7, plus the
new `app/Support/Money` foundation (section 8a) and `config/money.php` (HD-IMP007-02). No change
to `AppServiceProvider`'s existing IMP-005/006 registrations beyond ADDING the three new
`ScopeResolverRegistry` entries and the one new `AuditEventRegistrar` registration call — additive
lines only, same pattern as the IMP-006 additions to that file.

## 20. Frontend Impact

```
Admin: resources/js/Pages/Campaign/{Programs,Campaigns,Funds}/{Index,Show}.vue — same plain
  Tailwind, useForm(), no UI kit conventions as every prior admin page in this codebase (no
  Layout component exists anywhere in the codebase even after IMP-006 — none introduced here).
Public: resources/js/Pages/Public/ProgramShow.vue, CampaignShow.vue, CampaignIndex.vue — apply
  the active Theme's branding color tokens/font via the same `--brand-*` CSS custom-property
  mechanism ThemeRender.vue already established, read via a small controller-side call to
  PublicRenderer::activeTheme()->branding (section 6), NOT via a shared Vue component (no shared
  Layout/component exists to import, per this repository's own standing convention).
No rich-text editor library is introduced (mirrors IMP-005's own deferral — plain textarea for
  description_html, matching CMS's own "editor library itself OUT of scope/undecided" precedent).
Money display: every Vue page receives an already-formatted display string computed server-side
  via `Money::format()` (section 8a) — the frontend never performs minor-unit-to-major-unit
  arithmetic itself, so currency/rounding logic is never duplicated or drifted between backend
  and frontend.
Eligibility display: `PublicCampaignController` passes `is_donation_eligible` (boolean) plus the
  raw `starts_at`/`ends_at` to `CampaignShow.vue`, which renders "opens on"/"ended on" messaging
  when `false` — computed via `CampaignEligibilityResolver` (section 8b), never re-derived in Vue.
```

## 21. API / Route Impact

```
Admin (auth + identity.active, prefix /admin/campaign):
  GET/POST   /admin/campaign/programs[/create|/{program}]     campaign.programs.*
  PATCH/POST /admin/campaign/programs/{program}/publish|archive|unpublish
  GET/POST   /admin/campaign/funds[/create|/{fund}]           campaign.funds.*
  POST       /admin/campaign/funds/{fund}/archive
  GET/POST   /admin/campaign/campaigns[/create|/{campaign}]   campaign.campaigns.*
  POST       /admin/campaign/campaigns/{campaign}/submit|approve|publish|reject|close
                                          (approve and publish are separate endpoints/permissions
                                          per HD-IMP007-01 — no combined "approve-publish" action)
  POST       /admin/campaign/campaigns/{campaign}/media       (upload)
  POST       /admin/campaign/{program|campaign}/{id}/media/{asset}/archive

Public (no auth):
  GET /programs/{program:slug}          public.programs.show
  GET /campaigns                        public.campaigns.index   (PUBLISHED only, paginated)
  GET /campaigns/{campaign:slug}        public.campaigns.show
```

All admin routes bind by ULID (implicit route-model binding via `getRouteKeyName()`); all public
routes bind by `slug`. These are entirely NEW routes — `routes/web.php`'s existing IMP-006
catch-all (`/{any}`) is registered LAST (per its own comment) and these new, more specific routes
are registered before it, so no route-ordering conflict exists; no change to the catch-all itself.

## 22. Error Handling

Every service throws a typed exception (`CampaignValidationException`,
`CampaignTransitionConflictException`, `FundValidationException`,
`CampaignMediaValidationException`) mirroring IMP-005/006's `reason + message` exception shape;
controllers catch these into `ValidationException::withMessages()`, identical to
`ThemeController`'s established pattern. No fatal error is permitted for a recoverable
configuration/data problem (mirrors IMP-006 §22's fallback rule) — e.g. a public Campaign page
whose active Theme has no branding row renders with default/empty branding rather than 500ing
(reusing `PublicRenderer`'s already-null-safe `activeTheme()`/branding chain read-only).

## 23. Concurrency / Transaction Safety

`lockForUpdate()` inside `DB::transaction()` on every lifecycle transition and every plain
field-update (guarded additionally by `edit_version`), exactly mirroring `PublicationService`'s
established idiom (section 6). No new locking primitive, no application-level mutex, no
queue/job — none needed at this stage's actual concurrency profile (single-organization,
admin-driven mutation rate, no public write path in v1).

## 24. Idempotency

Media upload is NOT idempotent by design (mirrors `MediaService`: duplicate uploads are detected
via SHA-256 and surfaced to the caller as a `findActiveDuplicate()`-style hint, never silently
deduped, never silently rejected). Lifecycle transitions are idempotent-by-rejection: a repeated
`submit`/`approve`/`publish`/`close` call against an already-transitioned Campaign is rejected
with a typed conflict exception (current status no longer matches the required predicate) rather
than silently re-applying — this is the same "deterministic, not silently repeatable" behavior
Phase 7's test-strategy instruction requires. `CampaignEligibilityResolver::isDonationEligible()`
is a pure function with no side effects — calling it any number of times never mutates state,
trivially idempotent.

## 25. Compatibility with IMP-001..006

No file under `app/Models/Cms`, `app/Models/Theme`, `app/Services/Content`, `app/Services/Theme`,
`app/Services/Rbac`, `app/Services/Audit` (or equivalent locked namespaces), any IMP-001..006
migration, or any locked governance document is modified by this specification. The only
cross-cutting file touched is `app/Providers/AppServiceProvider.php` (additive registrations,
section 19) and `tests/Support/TruncatesInMemorySqlite.php` (additive reseed entries if IMP-007
introduces any singleton-row table — **it does not**: Program/Campaign/Fund are ordinary
multi-row tables, so no reseed-hook change is actually needed; noted here only to confirm the
question was considered, not overlooked).

**LOCKED-CONTRACT IMPACT: NONE.** No IMP-001..006 locked contract requires modification for this
specification's v1 scope (see sections 6, 9, 20 for how the CMS/Theme integration points that
might have required a locked-contract change were each resolved instead through read-only
consumption of an already-public interface). If a future stage wants full Theme
section/component composability for Campaign/Program pages (extending
`ThemeTemplateService::CONTENT_KINDS`), that remains a distinct, separately-proposed
LOCKED-CONTRACT IMPACT against IMP-006 — not exercised here.

## 26. Migration / Rollback Strategy

Standard additive Laravel migrations, each with a symmetric `down()`. No existing migration is
edited (mirrors this repository's "Migration Immutability" branching policy, section on
migrations reaching shared history). Per HD-IMP007-04 (confirmed, Option A), no seeder inserts
default business data — no "system-default Campaign", no implicit "General Fund" or equivalent
placeholder, unlike IMP-006's System Default Theme, since there is no analogous "must always have
at least one" technical-rendering requirement here — an empty Program/Campaign/Fund set is a
valid, normal starting state, and every Fund reference in later code must be explicit (never a
hard-coded seeded name/id). Rollback of the whole IMP-007 migration set (`migrate:rollback` to the
pre-IMP-007 point) is safe and non-destructive to any IMP-001..006 data, since no existing table
is touched.

## 27. Test Strategy

```
Unit:        ComponentConfigValidator-equivalent is not needed (no closed-schema JSON config in
             this domain); CampaignMediaValidator-equivalent adversarial tests (SVG rejection,
             oversized rejection, PHP-disguised-as-image rejection) mirroring
             ThemeAssetServiceTest exactly. MoneyTest (construction, equality, format() output for
             at least one 2-digit currency and one registered non-2-digit currency if present in
             config/money.php, rejection of an unregistered currency via
             UnknownCurrencyException, confirmation no float ever appears in amountMinor()'s
             return type). CampaignEligibilityResolverTest (HD-IMP007-03 — both starts_at/ends_at
             null; only starts_at set, before/at/after boundary; only ends_at set, before/at/after
             boundary; both set, before window/at lower boundary/inside/at upper boundary/after
             window; non-PUBLISHED status always false regardless of dates).
Feature:     ProgramServiceTest, CampaignServiceTest, FundServiceTest (CRUD + validation),
             CampaignLifecycleServiceTest (every valid transition — submit, approve, publish,
             reject, close — as independent methods per HD-IMP007-01; every invalid transition
             rejected, including APPROVED -> PUBLISHED without a valid Fund and REVIEW ->
             PUBLISHED skipping APPROVED; an actor holding only CAMPAIGN_APPROVE cannot call
             publish() and vice versa; the stale-status/duplicate-transition race test using the
             corrupt-then-assert-rejected pattern proven in ThemeActivationServiceTest, exercised
             against both approve() and publish() independently), ProgramPolicyTest/
             CampaignPolicyTest/FundPolicyTest (mirrors ThemeAuthorizationTest exactly, including
             the AUTHORIZED/UNAUTHORIZED-ROLE/OUT-OF-SCOPE/UNAUTHENTICATED matrix required by
             Phase 7), PublicProgramControllerTest/PublicCampaignControllerTest (published-only
             visibility, DRAFT/REVIEW/APPROVED/CLOSED never publicly reachable, slug-based
             routing, branding tokens present in the Inertia payload, is_donation_eligible
             correctly reflects the eligibility resolver for a published-but-not-yet-open and a
             published-but-ended Campaign, both of which remain reachable/200 per HD-IMP007-03).
Negative:    invalid transition attempts (e.g. DRAFT -> PUBLISHED or REVIEW -> PUBLISHED skipping
             APPROVED; CLOSED -> anything) rejected with the typed conflict exception, not a 500.
Database:    a CampaignSchemaConstraintsTest mirroring ThemeSchemaConstraintsTest exactly — raw
             inserts/deletes proving RESTRICT on program_id/fund_id and slug/code uniqueness are
             real database invariants, not just application pre-checks.
Regression:  full existing suite (all IMP-001..006 tests) must remain green, unchanged, after
             IMP-007's additions — no existing test is modified except (if genuinely needed)
             AuditFoundationTest's registry-count assertion, updated the same way IMP-006 updated
             it for its own new events (an expected, disclosed, additive change, not a
             regression).
MySQL:       required — full suite run against real local MySQL 8 via the same temporary,
             uncommitted phpunit.mysql.xml mechanism used for IMP-005/006, before any
             implementation-complete claim.
SQLite:      required — the default committed test configuration.
```

## 28. Acceptance Criteria

```
AC-007-001
Given an authorized actor holding PROGRAM_CREATE
When they create a Program with a valid name and unique slug
Then a Program row exists in DRAFT status, ulid-identified, with created_by_principal_id set to
  the acting Principal, and a program.created audit event is recorded.

AC-007-002
Given an authorized actor holding CAMPAIGN_CREATE
When they create a Campaign with a valid name/slug, optionally attached to an existing Program
Then a Campaign row exists in DRAFT status with fund_id null, and a campaign.created audit event
  is recorded.

AC-007-003
Given a DRAFT Campaign with no fund_id
When an actor holding CAMPAIGN_SUBMIT submits it for review
Then the Campaign transitions to REVIEW, submitted_at and submitted_by_principal_id are set, and
  a campaign.submitted audit event is recorded.

AC-007-004
Given a Campaign in REVIEW status
When an actor holding CAMPAIGN_APPROVE approves it
Then the Campaign transitions to APPROVED, approved_at/approved_by_principal_id are set, and a
  campaign.approved audit event is recorded (no fund_id check at this step — HD-IMP007-01).

AC-007-004B
Given a Campaign in APPROVED status with a valid, ACTIVE fund_id
When an actor holding CAMPAIGN_PUBLISH publishes it
Then the Campaign transitions to PUBLISHED, published_at/published_by_principal_id are set, and a
  campaign.published audit event is recorded.

AC-007-005
Given a Campaign in APPROVED status with no fund_id (or an ARCHIVED fund_id)
When an actor attempts to publish it
Then the transition is rejected with a typed validation exception and the Campaign remains
  APPROVED.

AC-007-006
Given a Campaign in REVIEW status
When an actor holding CAMPAIGN_APPROVE rejects it (with a reason)
Then the Campaign transitions back to DRAFT, and a campaign.rejected audit event is recorded
  with the reason in its metadata.

AC-007-007
Given a Campaign in DRAFT, REVIEW, APPROVED, or CLOSED status
When any actor attempts an invalid transition for that status (e.g. DRAFT -> PUBLISHED directly,
  REVIEW -> PUBLISHED skipping APPROVED, or any transition FROM CLOSED)
Then the transition is rejected with a typed conflict exception and no state change occurs.

AC-007-008
Given a PUBLISHED Campaign
When an actor holding CAMPAIGN_CLOSE closes it
Then the Campaign transitions to CLOSED, closed_at/closed_by_principal_id are set, and a
  campaign.closed audit event is recorded; the Campaign becomes immutable to further field edits.

AC-007-009
Given a Program still referenced by at least one Campaign
When any actor attempts to delete that Program
Then the deletion is rejected at the database level (RESTRICT), proven by a raw query bypassing
  the service layer.

AC-007-010
Given a Fund still referenced by at least one Campaign
When any actor attempts to delete that Fund
Then the deletion is rejected at the database level (RESTRICT).

AC-007-011
Given an unauthenticated visitor
When they request GET /campaigns/{slug} for a PUBLISHED Campaign (regardless of whether it is
  currently within its starts_at/ends_at window — HD-IMP007-03)
Then the page renders successfully with the Campaign's public fields, its
  is_donation_eligible flag, and the active Theme's branding tokens, and internal BIGINT ids
  never appear in the response payload.

AC-007-012
Given an unauthenticated visitor
When they request GET /campaigns/{slug} for a DRAFT, REVIEW, APPROVED, or CLOSED Campaign
Then the response is a 404 (not found), never the Campaign's content.

AC-007-013
Given a Principal holding no Campaign/Program/Fund permissions
When they attempt any admin create/update/transition/media-upload action
Then the request is rejected with 403, verified for every policy method (the
  AUTHORIZED/UNAUTHORIZED-ROLE/OUT-OF-SCOPE/UNAUTHENTICATED matrix).

AC-007-014
Given two concurrent requests attempting to approve-and-publish the same REVIEW Campaign
When both execute against a real MySQL connection
Then exactly one succeeds and the other is rejected with a typed conflict exception — no
  double-publish, no duplicate audit event pair for the same transition.

AC-007-015
Given a Campaign media upload of a PHP file renamed with a .jpg extension (magic-byte mismatch)
When an actor attempts to upload it via CAMPAIGN_MEDIA_UPLOAD
Then the upload is rejected before any file is persisted to storage.

AC-007-016
Given a Fund currently ACTIVE and referenced by a PUBLISHED Campaign
When an actor archives the Fund
Then the archival succeeds (Funds may be archived independent of existing references — BR-5
  concerns NEW Campaign attachment, not existing historical reference), the existing Campaign's
  fund_id and historical data remain intact and unaffected, and a fund.archived audit event
  (hasFinancialReference: true) is recorded.

AC-007-017
Given the full application test suite
When run against both the default SQLite configuration and a real local MySQL 8 instance via the
  temporary phpunit.mysql.xml mechanism
Then all tests pass on both, with zero regression to any pre-existing IMP-001..006 test.

AC-007-018 (HD-IMP007-03)
Given a PUBLISHED Campaign with starts_at in the future
When CampaignEligibilityResolver::isDonationEligible() is evaluated at the current time
Then it returns false; and once evaluated at a time >= starts_at (and <= ends_at, or ends_at
  null), it returns true — with no change to the Campaign's persisted status at any point.

AC-007-019 (HD-IMP007-03)
Given a PUBLISHED Campaign with both starts_at and ends_at null
When CampaignEligibilityResolver::isDonationEligible() is evaluated at any arbitrary time
Then it returns true; and given the same Campaign evaluated exactly at starts_at or exactly at
  ends_at (when set), the boundary is inclusive and eligibility is true at that exact instant.

AC-007-020 (HD-IMP007-01)
Given an actor holding CAMPAIGN_APPROVE but not CAMPAIGN_PUBLISH
When they attempt to publish an APPROVED Campaign
Then the request is rejected with 403; and given an actor holding CAMPAIGN_PUBLISH but not
  CAMPAIGN_APPROVE, when they attempt to approve a REVIEW Campaign, the request is rejected with
  403 — the two permissions never substitute for each other.

AC-007-021 (HD-IMP007-02)
Given a Campaign created with target_amount_minor = 1000000 and currency = "IDR"
When its Money representation is formatted for display
Then the formatting is computed via Money::format() using CurrencyMinorUnits' registered digit
  count for IDR, with no floating-point arithmetic performed at any point in the request
  lifecycle; and given a currency code not present in config/money.php is submitted, the request
  is rejected at validation time with a typed exception, never silently defaulting to 2 digits.
```

## 29. Risks

```
R-1  No REVIEW-state precedent exists anywhere in this codebase (section 6) — the lifecycle
     design in sections 11/12 is new territory, not a proven-in-production pattern like CMS's
     publish/unpublish. Mitigated by close adherence to the Human's own explicit lifecycle
     diagram and by the adversarial concurrency test (AC-007-014) proving it under real MySQL
     locking before any FINAL claim.
R-2  (RESOLVED by HD-IMP007-02) The Money value object + centralized `CurrencyMinorUnits`
     registry is now the ratified representation. Residual risk: `config/money.php` is
     deliberately non-exhaustive of all ISO 4217 currencies at v1 (section 8a) — a future
     Campaign in an unregistered currency will be rejected at validation time until an
     implementer adds that currency's entry; this is intentional fail-closed behavior, not a
     defect, but is worth surfacing operationally.
R-3  No slug-rename redirect history (Non-Goals) means a renamed Campaign/Program slug silently
     breaks previously-shared links with no redirect, unlike CMS's cms_paths mechanism. Accepted
     as a documented v1 limitation per the Human baseline's silence on this requirement, not an
     oversight.
R-4  ScopeType::Organization (not the locked CAMPAIGN/PROGRAM/FUND scope values) is used in v1
     (section 6) — if a later stage introduces scoped staff assignment (e.g. "Campaign Manager"
     role restricted to specific Campaigns), IMP-007's Policies will need a resolver swap, not a
     schema change (ScopeResolver is already an injected strategy per policy call) — low
     migration cost, flagged for awareness.
R-5  (RESOLVED by HD-IMP007-01) APPROVED is now a real, persisted, independently-permissioned
     state. Residual risk: an APPROVED Campaign has no expiry — it can sit approved indefinitely
     without being published, and HD-IMP007-01 explicitly forbids inventing an automatic
     expiry/unapproval policy for this. If operational experience later shows stale-APPROVED
     campaigns are a real problem, that would be a new, separate Human Decision, not something
     this specification should pre-empt.
R-6  (new, from HD-IMP007-03) A PUBLISHED-but-not-currently-eligible Campaign remains fully
     publicly reachable (section 8b/14) — the public page's "opens on"/"ended on" messaging is a
     UX responsibility of IMP-007's own Vue pages, not a security boundary; a missing or
     incorrect display of this messaging would confuse donors but does not itself constitute an
     authorization defect (donation processing does not exist yet in this stage to be bypassed).
     Must still be covered by tests (AC-007-018/019) since it is a genuine, specified behavior.
R-7  (new, from HD-IMP007-02) Every future amount-bearing column introduced by IMP-008+ must
     follow the same Money/minor-unit convention established here to avoid a mixed
     decimal-and-integer money landscape across the codebase. Enforcing this consistently across
     future, separately-authorized stages is a code-review/governance responsibility beyond what
     this specification alone can guarantee — flagged for future stage authors and reviewers.
```

## 30. Open Questions / Human Decisions

All four Open Questions raised in the prior specification pass have been resolved by explicit
Human Decision (recorded in full in "Human Decisions Applied" near the top of this document) and
materialized throughout this patched specification:

```
OQ-007-01  RESOLVED -> HD-IMP007-01 (Option B: APPROVED is a distinct persisted state; approve
           and publish are independent transitions/permissions). Materialized in sections 3, 5,
           7, 8, 11, 12 (BR-4), 14, 17, 21, 24, 28 (AC-007-004/004B/005/006/007/020), 29 (R-5).
OQ-007-02  RESOLVED -> HD-IMP007-02 (Option C: canonical Money value object, integer minor-unit +
           currency, centralized minor-unit registry). Materialized in sections 3, 6, 7, 8, 8a,
           12 (BR-9), 13, 16, 18, 20, 27, 28 (AC-007-021), 29 (R-2, R-7).
OQ-007-03  RESOLVED -> HD-IMP007-03 (Option B: donation-eligibility requires PUBLISHED status AND
           satisfying starts_at/ends_at, via one canonical reusable contract; no scheduler).
           Materialized in sections 3, 8b (new), 10, 12 (BR-8), 14, 20, 21, 27, 28
           (AC-007-011/012/018/019), 29 (R-6).
OQ-007-04  RESOLVED -> HD-IMP007-04 (Option A, confirmed: no seeded Program/Campaign/Fund, no
           implicit "General Fund"). Materialized in sections 7, 26.
```

No further open decision was identified while applying these four patches. If a genuinely new
ambiguity is discovered during a later implementation-phase self-audit, it will be raised as a new
`OQ-007-NN` at that time rather than resolved speculatively.

## 31. Traceability Matrix

```
Requirement                          Spec Section        Impl Area                    Test
--------------------------------------------------------------------------------------------------
Program entity + lifecycle           §5, §8, §11          ProgramService, Program      ProgramServiceTest
Campaign entity + fields             §5, §8               CampaignService, Campaign    CampaignServiceTest
Fund entity + designation role       §5, §6, §8            FundService, Fund            FundServiceTest
Campaign lifecycle DRAFT..CLOSED     §7, §11, §12         CampaignLifecycleService      CampaignLifecycleServiceTest,
                                                                                        AC-007-003..008
Program<->Campaign relationship      §5 (§10), §8, §12    campaigns.program_id (FK)     CampaignSchemaConstraintsTest,
                                                                                        AC-007-009
Campaign<->Fund relationship         §5 (§6, §11), §8, §12 campaigns.fund_id (FK)       CampaignSchemaConstraintsTest,
                                                                                        AC-007-004/005/010/016
RBAC reuse, no new role              §5 (§8), §14         PermissionRegistry, Policies  ProgramPolicyTest, CampaignPolicyTest,
                                                                                        FundPolicyTest, AC-007-013
Approve/publish permission split     §5 (§9), §14         CAMPAIGN_APPROVE/PUBLISH      AC-007-004
Public/backend authorization only    §5 (§8), §16         Policy + Controller layer     AC-007-013
Audit integration                    §5 (§14), §17        CampaignAuditEventRegistrar/  AuditFoundationTest (count update),
                                                            Logger                       AC-007-001..008/016
IMP-008 boundary (no donation code)  §4, §5 (§12/§16)      (absence of donations table)  N/A (negative — verified by
                                                                                        git diff review at finalization)
CMS/Theme reuse without locked edit  §5 (§13), §6, §9,     PublicRenderer::activeTheme() Manual review + git diff (no
                                       §20, §25             read only                    IMP-005/006 file touched)
Data integrity (orphan/ambiguous     §5 (§15), §12, §18    FK RESTRICT, service checks  CampaignSchemaConstraintsTest,
  Fund/destructive delete)                                                              AC-007-009/010
Race conditions in transitions       §5 (§15), §12 (BR-7), lockForUpdate() +            AC-007-014
                                       §23                  DB::transaction()
Money representation (HD-IMP007-02)  §8a, §12 (BR-9), §13,  Money VO, CurrencyMinorUnits, MoneyTest, AC-007-021
                                       §16, §18, §20         target_amount_minor col
Campaign donation-eligibility        §8b, §12 (BR-8), §14,  CampaignEligibilityResolver   CampaignEligibilityResolverTest,
  contract (HD-IMP007-03)             §20, §21              (public, canonical)            AC-007-018/019
Independent approve/publish          §11, §12 (BR-4), §14,  CampaignLifecycleService::    CampaignLifecycleServiceTest,
  permissions (HD-IMP007-01)          §17, §21               approve()/publish()           AC-007-004/004B/005/020
No seeded business data              §7, §26                (absence of seeder entries)   Manual review + git diff (no
  (HD-IMP007-04)                                                                          seeder inserts Program/Campaign/Fund)
MySQL + SQLite compatibility         §27                   (all of the above)           full suite run both drivers
```

## 32. Self-Audit Findings (Frozen Before Human Review)

Per single-agent audit-separation discipline (specification -> stop editing -> read-only re-read
against the Human Decision and existing architecture -> freeze findings here without silently
patching them out of the narrative):

```
ID              SPEC-007-AUDIT-01
Severity:       EDITORIAL
Evidence:       Section 19 originally contained a typo fragment ("seeYou-nothing") from a
                mid-edit slip.
Impact:         Cosmetic only — no semantic ambiguity survives since the sentence's meaning is
                still clear from context, but it should be corrected before Human review.
Required action: Correct to "seeders — nothing" or remove the stray fragment. PATCHED below.

ID              SPEC-007-AUDIT-02
Severity:       MINOR
Evidence:       Section 12, BR-6, contains a self-correcting sentence structure ("...are
                immutable once the parent is not DRAFT... actually NO: v1 does not freeze
                slugs...") — an artifact of reasoning left visible in the frozen draft rather
                than resolved into a single clean statement.
Impact:         Reduces spec readability; does not change the actual rule (the final resolved
                position — slugs are NOT frozen post-publish — is what's stated and is correct
                per Non-Goals section 4/Risk R-3). Not a BLOCKER or MAJOR since the substantive
                rule is unambiguous by the end of the sentence.
Required action: Rewrite BR-6 as a single clean statement. PATCHED below.

ID              SPEC-007-AUDIT-03
Severity:       MINOR
Evidence:       Section 11's Campaign state diagram note about combining APPROVE+PUBLISH into a
                single v1 transition is a genuine interpretive decision this specification makes
                rather than the Human Decision stating it explicitly line-for-line.
Impact:         If the Human's intent was a distinct persisted APPROVED state, sections 8, 11,
                14, 17, and AC-007-004/005/006 would need revision before implementation.
Required action: Already escalated as OQ-007-01 (section 30) — no further action needed beyond
                ensuring it is genuinely surfaced at Stage Gate, not buried. Confirmed present.

ID              SPEC-007-AUDIT-04
Severity:       MINOR
Evidence:       Section 8a's money representation (decimal(15,2) + currency code) is a new
                convention proposed without any existing precedent to validate it against.
Impact:         Could require a future migration if IMP-008/010/011 standardize on something
                else (e.g. integer cents).
Required action: Already escalated as OQ-007-02 (section 30). Confirmed present.

BLOCKER: 0
MAJOR:   0
MINOR:   2  (SPEC-007-AUDIT-02, SPEC-007-AUDIT-03/04 — the latter two are OPEN HUMAN DECISIONS,
             not defects; counted here for completeness per the audit format, not double-counted
             as OPEN HUMAN DECISIONS AND findings in the stage-gate report below)
EDITORIAL: 1 (SPEC-007-AUDIT-01)
```

All findings above are PATCHED in the sections they reference (this document reflects the patched
state; the findings are preserved here per this repository's own disclosure discipline, not
removed once fixed).

## 33. Self-Audit Findings — Round 2 (After HD-IMP007-01..04 Patch)

Per single-agent audit-separation discipline, re-applied after patching the specification to
materialize HD-IMP007-01 through HD-IMP007-04: stop editing -> read-only re-read of the entire
patched document, cross-checking every section touched by the four decisions for internal
consistency, plus a full-text search for stale pre-decision terminology
(`approve-publish`/`approved_published`/bare `target_amount`/`decimal(15,2)`/four-state lifecycle
enumerations) -> freeze findings here.

```
ID              SPEC-007-AUDIT-05
Severity:       EDITORIAL
Evidence:       "Human Decisions Applied" (HD-IMP007-02 summary paragraph) referred to
                "Campaign.target_amount" instead of the patched column name
                "Campaign.target_amount_minor".
Impact:         Cosmetic inconsistency within the document's own summary of its own decision;
                the authoritative field definition in section 8 was already correct.
Required action: Corrected to "Campaign.target_amount_minor". PATCHED.

BLOCKER: 0
MAJOR:   0
MINOR:   0
EDITORIAL: 1 (SPEC-007-AUDIT-05)
```

Full-text search confirmed no remaining references to the pre-decision combined
`approve-publish`/`approved_published` transition/event, no remaining bare `target_amount`
column references outside intentional historical record (the frozen Round-1 audit findings, left
unmodified per disclosure discipline), no remaining `decimal(15,2)` schema references outside
that same historical record, and no remaining 4-state (DRAFT/REVIEW/PUBLISHED/CLOSED, omitting
APPROVED) Campaign status enumerations anywhere in the live (non-historical) text. HD-IMP007-01
through HD-IMP007-04 are each traced to every section listed in their "RESOLVED ->" entries in
section 30, cross-checked present.

```
BLOCKER: 0
MAJOR:   0
MINOR:   0
EDITORIAL: 1 (cumulative with Round 1: 2 total across both rounds)
OPEN HUMAN DECISIONS: 0
```

## 34. Self-Audit Findings — Round 3 (After Implementation)

Per single-agent audit-separation discipline, re-applied after full implementation, test-writing,
and a full SQLite + MySQL run: stop editing -> read-only re-read of the implementation against
this specification, HD-IMP007-01..04, and the locked architecture -> freeze findings.

```
ID              SPEC-007-AUDIT-06
Severity:       MINOR (implementation-phase specification defect, PATCHED)
Evidence:       BR-5 (section 12), as originally drafted in Round 1/2, stated a Fund "cannot be
                archived while any non-CLOSED Campaign currently references it" — directly
                contradicting AC-007-016 (archival must succeed even with an existing PUBLISHED
                reference) and the Fund lifecycle note in section 11 (archiving never
                retroactively invalidates an existing Campaign). This was caught while
                implementing FundService::archive() and would have produced code that failed
                its own acceptance criterion had it followed the stale BR-5 prose literally.
Impact:         None on shipped behavior — FundService::archive() was implemented to match
                AC-007-016 and section 11 (archival always succeeds, unconditionally), and
                BR-5's prose has been corrected to match. No Human Decision was required — this
                was an internal drafting inconsistency within the already-approved
                specification, not a new business-rule choice.
Required action: BR-5 rewritten (section 12) to state the corrected rule. PATCHED.

ID              SPEC-007-AUDIT-07
Severity:       MINOR (disclosed scope limitation, not fixed)
Evidence:       FundController::update() and its route exist and are exercised directly by
                FundServiceTest, but no admin Vue page exposes an edit form for an existing
                Fund's name/restriction_note — Funds/Index.vue offers create + archive only.
Impact:         An admin cannot rename a Fund or edit its restriction note through the built
                UI in v1 (the backend/API path is fully correct and authorized either way).
                Does not affect any Acceptance Criterion — none references Fund editing via UI.
Required action: Disclosed here; not fixed in this pass. A future increment can add a Fund
                edit form using the exact same pattern as Programs/Show.vue.

ID              SPEC-007-AUDIT-08
Severity:       MINOR (disclosed scope limitation, partially fixed)
Evidence:       Program/Campaign media upload+archive (ProgramMediaService/CampaignMediaService,
                including the full adversarial validation pipeline) are implemented and directly
                tested (CampaignMediaServiceTest, AC-007-015), and their HTTP routes/controller
                actions now exist (added during this same implementation pass after a
                self-audit catch — upload was originally wired but archive was not). However,
                no admin Vue page yet renders a media upload widget or an asset gallery/archive
                button for Program or Campaign — `mediaAssets` is passed to
                Programs/Show.vue's and Campaigns/Show.vue's Inertia props but not yet
                rendered in either template.
Impact:         An admin cannot upload or archive Program/Campaign media through the built UI
                in v1, though the backend is fully correct, authorized, and tested at the
                service layer. AC-007-015 tests the validation pipeline directly (as specified)
                and is unaffected.
Required action: Disclosed here; not fixed in this pass (UI-only gap). A future increment can
                add an upload form + asset list mirroring Theme/Show.vue's asset section.

ID              SPEC-007-AUDIT-09
Severity:       MINOR (environmental, not an IMP-007 code defect)
Evidence:       During MySQL verification, `Schema::getTableListing(schemaQualified: false)` on
                this local development machine's MySQL server was found to return table names
                from multiple, entirely unrelated databases hosted on the same server (not just
                the connected `kmsitdonation`/test database) — reproduced deterministically via
                a minimal standalone script, unrelated to any IMP-007 file (this trait and its
                call site are pre-existing IMP-004 test infrastructure, untouched by this
                implementation). This causes every test class using
                `tests/Support/TruncatesInMemorySqlite.php` (AuditFoundationTest, RbacAuditTest,
                RolePermissionServiceTest, IdentityAuditFailureRollbackTest, RoleAssignmentTest —
                none of them IMP-007 tests) to fail immediately when truncation hits the first
                phantom (non-existent-in-the-current-schema) table name.
Impact:         Confirmed NO destructive action occurred: the failing TRUNCATE statement targets
                the connected database's unqualified table name, and since the phantom table
                does not exist there, MySQL rejects it immediately (error 1146) before any real
                table — belonging to this project or any other — is touched. Verified by
                inspecting both the shared dev database and a disposable isolated test database
                before and after: both retained their full, correct table sets. This blocks a
                strict MySQL-verified claim for those 5 pre-existing, non-IMP-007 test classes
                specifically in this session; it does NOT affect any IMP-007 test (all of which
                use RefreshDatabase, not this trait) or any other pre-existing test in the suite
                (577 non-TruncatesInMemorySqlite tests, including all new IMP-007 tests, passed
                cleanly against real MySQL).
Required action: Disclosed here as a machine/environment-level finding for the Human's
                infrastructure awareness — not an IMP-007 implementation defect, and out of
                this specification's authority to fix (it is pre-existing IMP-004 test
                infrastructure interacting with this specific local MySQL server's
                information_schema behavior). No code change made. GATE-IMPACT: NONE, since it
                affects zero IMP-007-scoped acceptance criteria or tests.

BLOCKER: 0
MAJOR:   0
MINOR:   4 (SPEC-007-AUDIT-06 patched, 07/08 disclosed-not-fixed, 09 environmental-disclosed)
EDITORIAL: 0
OPEN HUMAN DECISIONS: 0
GATE-IMPACT FINDINGS: 0
```
