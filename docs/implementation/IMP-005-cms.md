# IMP-005 — CMS (Content Experience)

## Status

```
Stage:                        IMP-005 — CMS
Document State:               SPECIFICATION — REMEDIATION PASS 2 APPLIED — PENDING CODEX RE-AUDIT
Codex Audit (Pass 0):         BLOCKER 0 / MAJOR 7 / MINOR 3 / EDITORIAL 0 / HUMAN DECISION 0 —
                              FAIL — IMP-005 SPECIFICATION REQUIRES REMEDIATION
                              (7 of the 10 findings are implementation-gate)
Findings IMP005-SPEC-01..10:  ALL PATCHED — NONE self-marked RESOLVED. Per-finding disposition:
                              docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md
Codex Re-Audit (Pass 1):      BLOCKER 0 / MAJOR 5 / MINOR 0 / EDITORIAL 0 / HUMAN DECISION 0 —
                              FAIL — IMP-005 SPECIFICATION REQUIRES FURTHER REMEDIATION
                              (all 5 findings are implementation-gate)
Findings IMP005-REAUDIT-R1-01..05:
                              ALL PATCHED — NONE self-marked RESOLVED. Per-finding disposition:
                              docs/audits/IMP-005-SPECIFICATION-REMEDIATION-2.md
Implementation Authorization: NOT GRANTED (remains gated on Codex re-audit + separate Human act)
Repository Baseline:          master @ f1aef3d59c2f5fa9361890bed8afa138f134680f
                              (IMP-000..IMP-004 FINAL / LOCKED — see
                              docs/audits/IMP-004-FINALIZATION.md and prior finalization records)
Human Decisions:              HD-IMP005-01..05 answered FINAL / LOCKED by the Human Authority and
                              materialized as Q29-Q33 in docs/01-requirements/HUMAN-DECISION-REGISTER.md
                              — this specification reflects those answers (section 25); NEITHER
                              remediation pass changed any of them, and Pass 2 raised no new one
Human Decisions Required:     0
OPEN HUMAN DECISIONS:         0
This document changes:        NO code, NO migrations, NO routes, NO tests, NO production config.
                              Documentation-only remediation (both passes raised no Human decision).
```

Provenance labels used throughout this specification:

```
LOCKED               — direct requirement/consequence of Level 1-4 authoritative documents
                       (Human Decision Register, Master Requirements, Locked Architecture Baseline,
                       accepted ADRs/decision records) or of an already-locked IMP-001..004 contract.
ENGINEERING CHOICE   — implementation detail consistent with the locked baseline, not itself a
                       business/architecture rule; follows the IMP-003 precedent of explicit
                       ENGINEERING CHOICE labeling.
HUMAN DECISION       — a product/business decision made by the Human Authority. ALL FIVE questions raised by
                       the first draft of this specification were answered by the Human Authority
                       and are now LOCKED Level 1 entries Q29-Q33 (section 25); nothing remains
                       open.
```

Nothing in this document may be treated as new locked architecture. HD-IMP005-01..05 have been
answered by the Human Authority (materialized as Q29-Q33 in the Level 1 register); with this
patched specification plus a clean independent review and a separate Human authorization act, this
document becomes the Level 5 implementation specification for IMP-005.

---

## 1. Authority

Read and reconciled under [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md):

```
Level 1  docs/01-requirements/HUMAN-DECISION-REGISTER.md            (Q1-Q33; Q29-Q33 are the
                                                                      IMP-005 CMS decisions)
Level 2  docs/01-requirements/MASTER-REQUIREMENTS.md                (§3 routes, §4 tech, §5 hosting, §6 domains)
Level 3  docs/02-architecture/MASTER-ARCHITECTURE.md                (locked principles incl.
         "Theme = presentation only", "Admin/Super Admin = fixed backoffice (not theme-controlled)")
         docs/02-architecture/MODULE-OWNERSHIP.md                   (§2 Content Experience, §16 Governance)
         docs/03-database/DATABASE-ARCHITECTURE.md + DATABASE-INVARIANTS.md
         docs/04-security/SECURITY-ARCHITECTURE.md + SECURITY-INVARIANTS.md
         docs/05-rbac/RBAC-ARCHITECTURE.md, DATA-SCOPE-MODEL.md,
         BUSINESS-AUTHORITY-MODEL.md, AUTHENTICATION-ASSURANCE.md
Level 5  docs/implementation/IMP-001..IMP-004 specifications (FINAL/LOCKED stage contracts:
         IMP-003 Permission/Authority/Scope mechanics + naming convention,
         IMP-004 canonical audit registry/sink/criticality semantics)
Level 6  AGENTS.md, CHANGE-CONTROL.md, IMPLEMENTATION-GOVERNANCE.md,
         MULTI-MODEL-OWNERSHIP.md, DEFINITION-OF-DONE.md, BRANCHING-POLICY.md
```

**Materialization gap (must be stated, not hidden):** `docs/06-domains/cms/`,
`docs/06-domains/theme/`, `docs/07-api/`, `docs/08-testing/`, `docs/09-deployment/` contain only
`.gitkeep` — no dedicated CMS / Theme Engine / Public Website / API / QA / Deployment architecture
documents exist in this repository. Per MASTER-REQUIREMENTS §6, domain detail "is to be developed
in that domain's own implementation specification... sourced from the Human Authority or the
external approved baseline — not guessed". This document is therefore the FIRST materialization of
CMS domain scope in-repository. Every LOCKED claim below traces to an existing Level 1-4 file or
to a locked IMP-001..004 contract; anything the baseline does not answer is labeled
ENGINEERING CHOICE or raised as a Human Decision (section 25). No Level 3 document is being
created or changed by this file.

### Implementation Ownership (prospective — per IMPLEMENTATION-SPEC-TEMPLATE.md)

```
Stage Type:                     NORMAL IMPLEMENTATION STAGE

Primary Implementation Owner:   Qwen 3.8 Flash
Primary Model:                  Qwen 3.8 Flash
Exact Model ID:                 qwen/qwen3.8-flash
Execution Environment:          Command Code GOAT
Specialist Reviewer(s):         NONE by default (Amendment V2; optional, READ ONLY, only if
                                 technically justified)
Independent Formal Reviewer:    Codex (CODING: NO)
Human Approval Authority:       Human
Concurrent Editing:             PROHIBITED
```

Authority: MULTI-MODEL-OWNERSHIP.md Amendment V2 fixed matrix — `IMP-005/006/022/026/028 ->
Qwen 3.8 Flash`. IMP-005 is NOT a Mission-Critical Claude Stage (IMP-009/010/011/014/015/016/027);
no GOV-MM-002 binding block applies. The GOV-MM-004 temporary Claude exception expired at IMP-004
FINAL/LOCKED and explicitly does not extend to IMP-005.

This task prepared the specification only. Implementation authorization is a separate Human act
(section 26).

---

## 2. Objective

Deliver the CMS (the managed-content half of the Module Ownership "Content Experience" module):
organization-controlled managed content — pages, articles, revisions, and media, with lifecycle,
slugs, minimal scheduling, and SEO metadata — safely manageable from the fixed admin backoffice and
consumable by the public website content contract, with zero authority over financial, payment,
ledger, RBAC, authentication, or business-domain state, and without implementing the Theme Engine
(IMP-006) or menu/navigation composition (deferred to IMP-006 by Q30).

## Source Requirements

```
MASTER-REQUIREMENTS §6: "Public Website / CMS / Theme Engine" are recognized approved domains.
MODULE-OWNERSHIP §2 (Content Experience) owns:
    Page
    Article
    News
    Content Revision
    Theme presentation configuration
  and states: "CMS blocks may reference controlled public projections of business-domain data,
  never the authoritative record itself." and "Does NOT own authoritative business-domain state."
MASTER-ARCHITECTURE: Theme = presentation only; Admin = fixed backoffice; single app; single root
  domain; same-origin.
```

---

## 3. Scope (locked baseline and engineering details)

```
IN SCOPE (LOCKED-derived):
  - Managed Page identity + lifecycle + revisions          (MODULE-OWNERSHIP §2 "Page", "Content Revision")
  - Managed Article identity + lifecycle + revisions       (MODULE-OWNERSHIP §2 "Article", "News"
      — per Q33, News is a CLASSIFICATION of Article, not an independent entity)
  - Append-only Content Revision model                     (MODULE-OWNERSHIP §2)
  - Media library for CMS content (upload/validation/serving of content imagery & documents)
      — required to render pages/articles at all; governed by SECURITY-ARCHITECTURE
        ("Sensitive file controlled delivery", "No permanent public URLs for sensitive files")
  - Slug management with protected-route exclusion         (MASTER-REQUIREMENTS §3 route list is LOCKED)
  - Minimal SEO metadata stored with content               (public-domain support; fields proposed as
        ENGINEERING CHOICE, section 15)
  - Canonical audit events for CMS mutations via the IMP-004 registry under the RESERVED
    `content` namespace                                    (IMP-004 Event Taxonomy reserves
        "content" for this domain — see section 12)
  - Permission registration under the IMP-003 registry + naming convention `<domain>.<capability>`
    with domain prefix `content`                           (section 22/23)
  - Same-origin Inertia admin backoffice screens for CMS management (fixed design system — LOCKED)
  - Public content-resolution contract consumed by web surfaces (section 18/24)

IN SCOPE (Human-Decision-resolved):
  - Scheduled publish / unpublish, minimal form             (Q32 LOCKED: publish_at/unpublish_at
      timestamps + Laravel Scheduler/Cron execution through the ordinary guarded service paths)

RESOLVED-OUT (Human-Decision-locked, recorded so their absence is decided, not overlooked):
  - Editorial review/approval workflow          OUT — Q29 (permission separation is the baseline)
  - Menu / navigation management                OUT — Q30 (deferred to IMP-006; IMP-005 supplies
      only the content/destination contract navigation targets)
  - Multilingual content                      OUT — Q31 (single-locale baseline, additive-ready)
  - Independent "News" entity                 OUT — Q33 (classification of Article via validated article_type)
```

## 4. Out of Scope (MUST NOT be implemented in IMP-005)

```
- Theme Engine: themes, templates, sections, components/blocks rendering, theme selection,
  theme presentation configuration, rendering pipeline, per-surface skin — ALL IMP-006 (LOCKED
  boundary, see section 6). Menu/navigation presentation configuration is explicitly IMP-006
  territory per Q30.
- Page-builder / drag-drop section composition UI (not present in any approved baseline content).
- Editorial approval workflow, reviewer queue, or editorial approval matrix (Q29 — none in v1).
- Translation tables, locale-specific publication workflow, localized-slug framework, or locale
  fallback engine (Q31 — none in v1).
- Any mutable/derived financial state; any write to ledger/payment/donation/commission/withdrawal/
  refund/approval domains (LOCKED — AGENTS.md "Financial Rules", "Locked Decisions").
- RBAC role/permission administration (IMP-003 owns the mechanism; IMP-005 only REGISTERS its own
  permission codes in the registry, exactly as IMP-004 registered audit.read.*).
- Authentication changes (IMP-002), audit foundation changes (IMP-004 — consume, never fork).
- Campaign/Program/Fund entities and campaign copy (IMP-007) — see boundary section 16.
- Fundraiser profile content (IMP-013), Partner profile content (IMP-018) — see section 16.
- The full public REST API (/api/v1 content endpoints) — IMP-025 owns REST (section 24).
- Platform search framework, index management, Elasticsearch — IMP-026 (section 24).
- Comments, ratings, contact/marketing forms, newsletters/analytics — no approved requirement.
- Private document workflows (receipts, compliance docs) — Documents module (MODULE-OWNERSHIP §15).
- Legal retention durations for content/media history — no invented durations (section 27).
- Localization fallback engines, SEO platform features (sitemaps, robots generation, redirect
  analytics) beyond the minimal fields specified here.
- Any Redis/WebSocket/Supervisor/PM2/Node-runtime mandatory dependency (LOCKED — §5 hosting).
```

## 5. Dependencies

```
IMP-001 (FINAL/LOCKED)  — app skeleton, Inertia/Vite foundation, shared-hosting shape.
IMP-002 (FINAL/LOCKED)  — authentication, AssuranceService (STANDARD/ELEVATED), identity.active
                          middleware, session security.
IMP-003 (FINAL/LOCKED)  — PermissionRegistry pattern, AuthorizationEvaluator (canonical AND-chain),
                          roles/permissions/principals tables, ScopeType taxonomy,
                          RbacMutationGuard-style DENIAL_DURABLE calling contract.
IMP-004 (FINAL/LOCKED)  — AuditEventRegistry + AuditWriter canonical sink, (event_type, event_version)
                          keying, criticality ownership by registry, MUTATION_ATOMIC /
                          DENIAL_DURABLE / NON_CRITICAL persistence semantics, Transaction Ownership
                          Invariant, metadata allow-list + hard-prohibited keys, visibility classes.
No new mandatory infrastructure. Possible new COMPOSER dependency: HTML sanitization library
(section 20) — subject to CHANGE-CONTROL "Dependency Governance" justification at implementation
time; nothing else.
```

---

## 6. CMS ↔ Theme Engine boundary (LOCKED, explicit)

```
Locked presentation chain (preserved unchanged):
  Theme -> Template -> Section -> Component / Block -> Content
```

| Concern | Owner |
|---|---|
| Content identities, lifecycles, revisions, bodies, slugs, media, SEO data, neutral destinations | **IMP-005 CMS** |
| Theme registry/selection, templates, sections, components/blocks, presentation configuration, menu/navigation presentation, rendering pipeline, per-surface skin | **IMP-006 Theme Engine** |
| Public portal shells / campaign pages / donor views UI | later domain stages + IMP-006 |

Interface between them (the only thing IMP-005 exposes):

```
ContentResolverService (read contract, defined in section 18/24):
  resolve(path)      -> PublishedContent | null   (identity, published revision data, meta)
  MediaTokenResolver -> maps embedded media ULID tokens in content to current public URLs
HomepageContentResolver (section 8; the SAME PublishedContent shape, never a second contract):
  resolve()          -> PublishedContent | null   (the designated homepage page, when published)
```

Rules:

```
LOCKED        IMP-005 must NOT implement themes/templates/sections or any rendering theming.
LOCKED        "Theme = presentation only" (MASTER-ARCHITECTURE) — presentation never gains
              content or business authority; content never stores theme/template decisions.
ENGINEERING   IMP-005 content bodies are theme-agnostic (semantic HTML,
              section 20); they must render acceptably inside the IMP-006 contract later without
              content rewrites.
LOCKED        Admin/backoffice CMS screens live in the FIXED design system and are NOT
              theme-controlled (MASTER-ARCHITECTURE; AGENTS.md Locked Decisions).
```

`MODULE-OWNERSHIP` §2 lists "Theme presentation configuration" inside the Content Experience
module; per Q30 the Human Authority confirmed that this — including menu/navigation presentation
configuration — is scheduled with IMP-006 (the Theme Engine stage). IMP-005 creates no theme or
menu tables and supplies only the canonical content/destination contract (section 21) that later
navigation composition targets. Recorded so this deferral is explicit and reviewable, not a silent
scope move.

## 7. CMS must not become authority (LOCKED restatement)

CMS data and screens have NO relationship to: donation business logic, payment state, ledger,
commission, withdrawal, refund, financial approval, RBAC, authentication, security restrictions,
Partner/Fundraiser authority, Beneficiary financial authority. Enforced structurally:

```
- No FK from any cms_* table to any financial/business-domain table (media rows are owned by CMS
  only for content use; other domains use their own document stores).
- No financial domain may read CMS state as truth; CMS may only later DISPLAY controlled public
  projections supplied BY business domains (MODULE-OWNERSHIP §2), and IMP-005 itself builds none
  (that mechanism arrives with the domain that needs it — see section 16).
- All CMS admin mutations flow through the canonical authorization evaluator (IMP-003) and the
  canonical audit sink (IMP-004) like any other privileged surface.
```

## 8. System page vs managed page (explicit boundary)

```
LOCKED route surface (MASTER-REQUIREMENTS §3): /, /campaign/*, /zakat/*, /wakaf/*, /fidyah/*,
  /qurban/*, /donor/*, /fundraiser/*, /partner/*, /admin/*, /api/v1/* — all SYSTEM-CONTROLLED.
Also system-controlled by existing implementation: /login /register /logout /mfa/* /password/*
  /reset-password/* /invitations/* /verify-email/* /account/* /dashboard (IMP-002) and any route
  a later stage registers in code.

 Categories:
   A. System page            — route + rendering owned by code (auth, portals, admin, `/`, later
                               campaign detail). CMS cannot create, replace, override, or wrap it.
   B. CMS-managed page       — identity exists ONLY in cms_pages; its public path is an ACTIVE
                               CURRENT claim in `cms_paths` (section 13) and is resolved at runtime
                               by the catch-all resolver registered LAST in web routes.
   C. Content in system page — CMS data supplied to a system page through a CMS read contract,
                               consumed BY application code (never overriding a system route).
                               The only category-C integration specified in IMP-005 is the homepage
                               (below). Any other instance (e.g. a later "about blurb" on a
                               campaign page) remains deferred — no approved requirement yet
                               (section 16); when it exists it flows business-domain -> code ->
                               view, never CMS overriding a system route.
   D. Fully dynamic page     — B rendered later through IMP-006 templates.
```

### Homepage contract (IMP005-SPEC-01)

Remediation of the former contradiction, in which `/` was simultaneously declared system-controlled
and CMS-assigned while integration was deferred. Exactly one contract now exists:

```
THE ROOT PATH — route ownership is NOT transferable
  - Route `/` is and remains APPLICATION-OWNED (registered in routes/web.php by application code;
    it exists today rendering the foundation page). IMP-005 does not create, replace, wrap,
    reorder, or conditionally override it.
  - CMS NEVER registers a route for `/`, never owns `/`, and cannot shadow the application router.
    `/` is a member of the reserved route registry (section 14) and is therefore also structurally
    unclaimable as a CMS path: no `cms_paths` row may hold path `/` (section 13 validation +
    reserved-registry rejection).
  - CMS owns only a HOMEPAGE CONTENT DESIGNATION: which CMS Page identity is the organization's
    designated homepage content. A designation is data, not a route.

The single resolution chain (category C, homepage instance):

  Application Router
    -> GET /                       (application-owned handler; unchanged by IMP-005)
    -> HomepageContentResolver     (CMS read contract; section 18)
         -> designated CMS page identity (cms_homepage_assignment, section 13)
         -> that page's CURRENT published revision
         -> neutral PublishedContent payload (section 24 contract; UNCHANGED shape)
    -> presentation / composition handled by the later rendering/theme layer (IMP-006)

  - HomepageContentResolver::resolve(): ?PublishedContent — read-only; returns null when no page
    is designated, when the designated page is not PUBLISHED, or when it has no current published
    revision. It never throws a public error and never leaks draft/retired/archive state
    (same 404-no-echo discipline as the public resolver, section 14).
  - IMP-005 delivers the designation store, the resolver read contract, admin assignment, and its
    tests. Wiring `/`'s RENDERING to the resolved payload is presentation composition and belongs
    to IMP-006 (section 6). In IMP-005 the `/` handler's rendered output is unchanged; the
    contract exists so IMP-006 consumes it instead of inventing its own.
  - Choosing a page as homepage grants it no route, changes no lifecycle, alters no visibility:
    an UNPUBLISHED designated page still does not resolve publicly anywhere.

Homepage is explicitly NOT:
  - CMS ownership of route `/`;
  - permission for CMS to shadow, reorder or bypass the application router;
  - any Theme Engine, template, section, block or composition mechanism (LOCKED, section 6);
  - a second content payload contract — the homepage returns the same neutral PublishedContent
    shape as any other managed page.
```

Hard guarantees (each test-enforced, section 29):

```
- Route registration order: the managed-content catch-all is registered after every system route
  and can only ever shadow nothing. Route `/` and every other application route is registered by
  application code and is never registered, replaced or wrapped by CMS code.
- Reserved-path validation from a canonical registry: no CMS path may begin with, or equal, a
  reserved route (section 14). The registry is DERIVED from the application's own route table plus
  protected prefixes and framework/ops paths — a single source that cannot drift from the routes
  that actually exist (section 14). Validated at write time AND asserted at boot; not UI-hinted.
- `/` is unclaimable: the root path is reserved, so the CMS path namespace can never contain it,
  independently of the homepage contract above.
- CMS redirects use only canonical internal published paths; they cannot proxy protected
  resources. CMS has no menu configuration (section 21).
```

## 9. Domain model / entity inventory

Derivation discipline: the ONLY content concepts named by the locked baseline are `Page`,
`Article`, `News`, `Content Revision` (MODULE-OWNERSHIP §2). Everything else in task-checklist
form is evaluated below and kept only where it is necessary to deliver those concepts safely.

```
LOCKED-derived entities:
  Page            managed page identity                       -> cms_pages
  Article         managed article identity (News = its
                  classification per Q33, no separate entity) -> cms_articles
  ContentRevision append-only revision of either              -> cms_content_revisions

Justified support entities (ENGINEERING CHOICE, required for the LOCKED entities to work):
  MediaAsset        uploaded content media                      -> cms_media_assets
  MediaReference    canonical record that content uses media     -> cms_media_references
                    (section 13; IMP005-SPEC-05 — replaces body-HTML parsing as the deletion
                    authority, and covers structured FK references such as og_image_asset_id)
  CmsPath           the ONE canonical claim registry of the public CMS path namespace, covering
                    both current paths and old-path redirects    -> cms_paths
                    (section 13/14; IMP005-SPEC-03 — supersedes the former cms_slug_history, whose
                    separate unique index could not protect a shared namespace)
  HomepageAssignment the single-row designation of which Page identity is homepage content
                                                                -> cms_homepage_assignment
                    (section 13; IMP005-SPEC-01/02 — a data designation, never a route)
  Article classification: validated article_type on its revision (ARTICLE | NEWS), exposed
                  through Article's selected revision; no taxonomy support entity.

Human-Decision-resolved removals (NOT built in IMP-005):
  Menu, MenuItem                    REMOVED — Q30 (deferred to IMP-006 presentation config)
  Approval gate states/tables       REMOVED — Q29 (no editorial approval workflow in v1)
  Translation rows / locale engine  REMOVED — Q31 (single-locale baseline; stable IDs and
                                      explicit text ownership preserve additive compatibility)
  Separate News entity              REMOVED — Q33 (classification of Article)

Explicitly NOT modeled (rejected derivations, with reason):
  Tag entity                     — no approved requirement; article_type provides the required NEWS classification.
  ContentStatus entity           — lifecycle is a column + validated transition rules, not a table.
  PublicationSchedule entity     — publish_at/unpublish_at columns on identity (Q32), not a table.
  SeoMetadata entity             — columns on the revision (versioned with content), not a table.
  Redirect entity (generic)      — only slug-change redirects are justified (CMS-owned source
                                   routes); no wildcard/regex/user-defined redirects (no baseline).
  Reusable content section store — page-builder territory; not authorized (section 4).
  Site/locale setting tables     — beyond v1 baseline.
```

Domain rules:

```
- Content identity (`page`/`article`) is the durable subject: public ULID, status, current pointers.
  The identity row stores NO path — the public path of a content identity is whatever its ACTIVE
  CURRENT `cms_paths` claim says (section 13), so the namespace has one source of truth.
  Every revision pointer on an identity is a COMPOSITE FK to the revision's own owner column
  (section 13 "Ownership-exact FKs"), so an identity can only ever name a revision it owns.
  Every `cms_paths` reservation persists until an explicit governed release: no lifecycle
  transition and no timer un-reserves a path (section 13 RESERVATION POLICY).
- Content BODY + SEO meta live on the revision. Payload is immutable once published, so what the
  public saw at any moment is reconstructable (DB invariants' historical reproducibility principle
  applied to content; CMS revisions are NOT financial records and carry no ledger semantics).
  Revision LIFECYCLE metadata is a separate, service-mutable concern — see section 11, which states
  the payload/lifecycle split that resolves IMP005-SPEC-04. "Append-only" in this document always
  means payload append-only (new revision rows are never edited into other revisions, and revision
  rows are never deleted), NOT row-UPDATE-forbidden.
- Exactly ONE active DRAFT revision per owner at a time (payload mutable); zero or one current
  published revision.
- Exactly ONE homepage designation exists at a time, enforced by a real singleton constraint
  (section 13), and it names a content identity — never a path or a route.
- Public identifiers are ULIDs (DATABASE-ARCHITECTURE identity conventions); internal BIGINT PKs
  and FKs never leave the server layer.
```

## 10. Lifecycle

Core lifecycle (LOCKED-derived minimum; states stored as enum string column `status`):

```
DRAFT        created; never publicly visible
PUBLISHED    current revision publicly visible at its slug path
RETIRED      previously published; withdrawn (unpublished); recoverable -> re-publish or -> ARCHIVED
ARCHIVED     terminal administrative state; excluded from public + default admin listings
```

```
DRAFT      -> PUBLISHED      (publish)         requires content.publish
DRAFT      -> ARCHIVED       (archive)         requires content.archive
PUBLISHED  -> RETIRED        (unpublish)       requires content.publish
PUBLISHED  -> ARCHIVED       not direct — RETIRE first (keeps one transition for withdrawal)
RETIRED    -> PUBLISHED      (re-publish)      requires content.publish
RETIRED    -> ARCHIVED       (archive)         requires content.archive
ARCHIVED   -> any            FORBIDDEN (restore = create new content; terminal, simple, audited)
```

Human-locked baseline; engineering representation:

```
Q29: No IN_REVIEW/APPROVED states, reviewer queue, multi-step editorial approval, approval matrix
     or workflow engine. content.create/content.update never imply content.publish. The approval
     predicate is inapplicable for CMS only, using the canonical evaluator's existing semantics.
Q31: SINGLE-LOCALE CONTENT BASELINE; no per-locale publication status or language switching.
Q32: Nullable publish_at/unpublish_at on Page/Article express pending intent. No persistent
     SCHEDULED state: timestamps avoid duplicating the existing lifecycle. This minimal state
     representation is an ENGINEERING CHOICE under the Human decision.
     Schedule creation/change/cancellation requires content.publish within scope; content edits
     separately require content.update. A publisher needs no editorial review step.
     PUBLISHED -> PUBLISHED replaces its current revision with a selected DRAFT through the same
     publication service; it is not a new lifecycle state.
```

### Scheduled execution contract (Q32; engineering mechanics)

```
Ownership: PublicationService owns schedule configuration/execution. Laravel Scheduler invokes
content:run-scheduled-transitions each minute through Cron, inline PHP in bounded batches.
No Redis, Supervisor, PM2, WebSocket, production Node runtime or separate workers are required.

Time: config/app.php uses UTC. Persist/compare UTC instants at database datetime precision.
Accept explicit-offset ISO-8601 input, normalize to UTC, reject ambiguous timezone-less input.
Admin input/display labels its timezone and converts explicitly; payloads serialize UTC. Capture
one UTC cutoff per run; first_published_at and a revision's published_at are display metadata,
never the scheduling clock.

Human intent: lock the identity, authorize content.publish, validate lifecycle and save timestamps,
schedule_version, scheduled_by_principal_id, scheduled_at and scheduled_revision_id. Publish binds
to an owned DRAFT, or the retained published revision for RETIRED re-publication. Standalone
unpublish requires PUBLISHED. New deadlines must be future; if both exist, unpublish_at must be
later than publish_at. Unpublish-only intent binds scheduled_revision_id to the current published
revision so its target/source is explicit; it cannot withdraw a later manual replacement. No scheduled archive. Increment schedule_version on configuration/change/
cancellation; require expected version/revision on Human requests, rejecting stale requests 409.

Revision binding: freeze a scheduled draft until an authorized publisher cancels/reschedules;
content.update alone cannot change its body/title/slug/classification/media dependencies. Target
must still belong to the identity and not be SUPERSEDED at execution. Immediate Human publish,
unpublish or archive cancels pending intent atomically; stale cron cannot override that action.

Execution: select due timestamps <= cutoff (not equality, so missed cron runs are caught).
For each identity, one service-owned outermost transaction: lockForUpdate, re-read schedule version,
state/timestamps, validate System and source Human eligibility (section 12), target and lifecycle,
perform authorized transition, consume timestamps, append canonical audit and commit.
Due publish: DRAFT/RETIRED -> PUBLISHED, or replace PUBLISHED with its bound new DRAFT.
Due unpublish: PUBLISHED -> RETIRED. If both deadlines were missed, consume the expired window
without briefly publishing: leave DRAFT/RETIRED nonpublic, or retire existing PUBLISHED content.
AUDIT FOR EACH OUTCOME — exactly one event, per section 12 "Expired-window semantics", which is the
normative table: a real transition emits its OWN transition event (`*.published` / `*.unpublished`,
actor=system, schedule keys present) and NO `schedule_expired` companion; a consumed window with no
transition emits `*.schedule_expired` with an `outcome`; a stale re-run emits nothing. The former
wording here ("emit schedule_expired, plus unpublished only when retirement occurred") would have
produced two content events for one mutation and is REMOVED.

Idempotency/concurrency: every Human/scheduler writer locks the same identity; stale scheduler
versions are no-ops. Consumed timestamps + state guards prevent duplicate mutation/event on rerun.
Overlapping cron runs serialize per row; no infrastructure-dependent mutex is needed for safety.
Canceled/archived/superseded/conflicting intent never forces publication. Invalid pending intent is
blocked and operationally reported for publisher correction; transient failures leave due work
pending. Business rollback preserves pending deadlines for next cron recovery. NON_CRITICAL audit
failure follows IMP-004: never replay a committed transition just to recover a missing audit event.
```

Transition discipline (LOCKED per IMP-003/004 patterns): invalid transitions are rejected in the
service layer inside the mutation transaction; state machine is code-defined (enum + allowed
transitions), never trusted from client input; every attempt is policy-gated first.

## 11. Revision model (IMP005-SPEC-04)

The prior contract was self-contradictory: publishing required the current PUBLISHED revision to be
flipped to SUPERSEDED (an UPDATE) while simultaneously forbidding UPDATEs to published/superseded
rows. Resolved by separating the two concerns on the same row, with different mutability rules.

```
Content identity      cms_pages / cms_articles row (ULID public id)
Revision identity     cms_content_revisions row (BIGINT internal id + per-owner revision_no)

IMMUTABLE-AFTER-PUBLISH FIELDS — EXHAUSTIVE (not "the payload fields"; these ARE the list, and it
   is the same list as `cms_content_revisions`' payload columns in section 13):
     revision_no, page_id, article_id, title, excerpt, article_type, slug_snapshot, body_html,
     meta_title, meta_description, og_title, og_description, og_image_asset_id, no_index,
     author_principal_id, authored_at
   Plus the derived facts that depend on them, which are consequently frozen too: the revision's
   `cms_media_references` rows for field_path body_html / og_image_asset_id (section 19), and the
   `slug_snapshot` those references were validated against.
   Once a revision's state leaves DRAFT, NONE of the above may change for the lifetime of the row:
   no service, no admin screen, no importer, no scheduled job, no bulk operation. Editing a
   published page means creating a NEW DRAFT revision and publishing it. Rollback means copying an
   old payload into a NEW revision. (A change to any of them is also, structurally, a change to
   what the public saw — which is the reproducibility property sections 9/11 exist to keep.)
   While state = DRAFT these columns are editable by content.update; DRAFT is the only state in
   which a payload write is ever legal.

CANONICAL FIELD OWNERSHIP — excerpt, published_on, published_at (IMP005-REAUDIT-R2-03)
   The previous text had `excerpt` and `published_on` living on `cms_articles` while the
   publication flow said the identity's display columns were "refreshed FROM the new revision",
   and the fields_changed vocabulary listed both as revision payload. Neither column existed on the
   revision table, so the specification named a source that did not exist. Resolved field by
   field, with the owner stated once:

     excerpt — CANONICAL OWNER: the REVISION. It is editorial prose that changes when the content
       changes, so it belongs to the versioned payload (added to the immutable list above and to
       the section 13 revision payload columns). The Article's identity-level `excerpt` is retained
       ONLY as an explicitly justified current PROJECTION of the currently published revision —
       see "Identity projections" below. Publication copies revision -> projection; it never
       copies projection -> revision, and no code may write the projection except through the
       publication transaction.

     published_on — REMOVED ENTIRELY as an ambiguous name. It was asked to mean two different
       things (the date the article first appeared, and the date of the current version) and could
       mean only one. Its two roles are now split into two unambiguously named columns:
         cms_articles.first_published_at  (DATE/datetime, nullable, identity level) — set ONCE by
           the identity's FIRST-EVER successful publication and NEVER updated again, by any later
           publication, replacement, re-exposure or rollback-by-copy. This is the canonical
           "published on" display value for an Article. Being identity-level, it is exactly the
           fact that is not about any one revision, and it can never drift because there is no
           operation that writes it twice.
         cms_content_revisions.published_at (per revision) — the moment THAT REVISION was
           published. See "Publication timestamps" below.
       No third "publication date" concept exists in IMP-005. Anything needing "when did this
       piece of content first go public" reads first_published_at; "when did this version go
       public" reads the published revision's published_at.

     PUBLICATION TIMESTAMPS — the re-exposure rule. `published_at` on a revision is written ONCE,
       by the transition that moves it DRAFT -> PUBLISHED, and is IMMUTABLE THEREAFTER, including
       across a retirement cycle. Concretely, re-publishing content that is RETIRED at a revision
       that is already PUBLISHED (the identity was withdrawn and comes back with no new draft)
       MUST NOT rewrite that revision's published_at — the original publication genuinely happened
       and the field records that fact, not current visibility. Visibility is carried by the
       IDENTITY's status, which is what the resolver checks (section 14 resolution table).
       A separate "current visibility started at" column was considered and REJECTED: nothing in
       IMP-005 reads it (no SLA, no ordering requirement, no UI surface names it), the audit trail
       already records every visibility transition with its timestamp and actor, and adding it
       would create a second mutable lifecycle field whose only job is to duplicate an audit
       event. If a later stage genuinely needs it, it is that stage's additive change, not a
       speculative column here. `first_published_at` covers the one display case that looked like
       a visibility-cycle timestamp.
     SUMMARY TABLE (normative; each row states who owns the value and what may write it):
       field                              | canonical owner | written by
       title, excerpt, body_html, SEO set,|    REVISION     | RevisionService::createDraft/editDraft
       article_type, slug_snapshot        |  (immutable after| while state = DRAFT only
                                          |   publication)  |
       identity title / excerpt           |  PROJECTION of  | PublicationService inside the
                                          |    revision     | publication transaction only
       cms_articles.first_published_at    |    IDENTITY     | PublicationService, ONCE, on the
                                          |                 | identity's first publication
       revision.published_at              |    REVISION     | PublicationService::publish, ONCE
       revision.superseded_at             |    REVISION     | PublicationService::supersede, ONCE
       identity status                    |    IDENTITY     | PublicationService transitions
       revision.edit_version              |    REVISION     | RevisionService::editDraft only

   IDENTITY PROJECTIONS, JUSTIFIED (section 16 of the remediation asks that denormalized identity
   fields exist only where justified, and that no field have two canonical owners): the identity's
   `title` and `excerpt` exist so admin listings and the neutral destination contract (section 21)
   can sort and display a content list without joining to the revision table for every row on
   every page of a listing. They are READ-ONLY PROJECTIONS: no writer outside PublicationService,
   never accepted from request input, and always copied FROM the newly published revision IN the
   same transaction that moves published_revision_id. Their canonical owner is the revision; the
   projection is derived data. Where this matters for testing: after any operation that does not
   publish, the projections are asserted unchanged; after any publication, they are asserted equal
   to the newly published revision's values (test O11).

LIFECYCLE-MUTABLE FIELDS — EXHAUSTIVE (schema names as in section 13; writable ONLY through the
   named authorized service operation, per the per-operation whitelists in enforcement layer 2):
     state                            (DRAFT | PUBLISHED | SUPERSEDED)
     published_at                     set once, by publish(); never changed afterwards, including
                                      by a re-exposure after retirement (see above)
     superseded_at                    set once, by supersede(); never changed afterwards
     state_changed_by_principal_id    who/what performed the most recent transition (a lifecycle
                                      fact, not origin attribution — authorship is the immutable
                                      author_principal_id, see "Editor / timestamp attribution")
   These four are the lifecycle set and they are PublicationService's alone. edit_version is
   DELIBERATELY NOT IN IT (IMP005-REAUDIT-R2-03): it is a CONCURRENCY CONTROL column written by
   RevisionService::editDraft(), not a lifecycle transition, and the previous text classified it
   as lifecycle while simultaneously requiring editDraft() to increment it and forbidding
   editDraft() from touching lifecycle columns — three statements that cannot all hold. It now has
   its own single-row classification, and the authoritative write permissions for every column are
   the operation matrix below, which supersedes any prose list anywhere else in this document:

 REVISION OPERATION MATRIX (THE ONE AUTHORITATIVE TABLE — layer 2's prose is a summary of this,
   and where they disagree THIS TABLE GOVERNS):
   | operation | payload columns | edit_version | state | published_at | superseded_at | state_changed_by_principal_id |
   |---|---|---|---|---|---|---|
   | createDraft   | WRITE (any)  | initialize to 0 | set DRAFT (only) | not writable | not writable | set (creation is a transition) |
   | editDraft     | WRITE (any) if locked state is DRAFT, else REJECT | INCREMENT (+1, exactly) | NOT writable | NOT writable | NOT writable | NOT writable |
   | publish       | READ ONLY    | NOT writable  | DRAFT->PUBLISHED or stays PUBLISHED on re-exposure | WRITE once, only if currently NULL | NOT writable | WRITE |
   | supersede     | READ ONLY    | NOT writable  | PUBLISHED->SUPERSEDED | NOT writable | WRITE once | WRITE |
   | retire (identity unpublish; no revision change) | READ ONLY | NOT writable | NOT writable | NOT writable | NOT writable | NOT writable |
   | archive (identity) | READ ONLY | NOT writable | NOT writable | NOT writable | NOT writable |
   Any cell not listed above is unwritable by that operation. `retire` and `archive` change the
   IDENTITY's status only and write NOTHING on the revision row — that is why re-exposing a
   previously published revision leaves its published_at intact. edit_version is writable by
   exactly one operation, publish() cannot touch it, and no operation may write a payload column
   and a lifecycle column in the same statement.

Revision identity vs pointer:
   The identity row's published_revision_id is the ONLY statement of "what is currently published".
   A revision's state column is derived bookkeeping kept in the same transaction as that pointer
   swap: exactly one revision per owner may be PUBLISHED, and it is the one the pointer names.
   If the two ever disagree, the pointer is authoritative and the disagreement is a defect.
 REVISION POINTER OWNERSHIP (IMP005-REAUDIT-R1-02 — normative, enforced by the schema of section
   13, not by goodwill):
   EVERY revision pointer must name a revision belonging to the SAME canonical identity:
     cms_pages.published_revision_id / latest_draft_revision_id / scheduled_revision_id
       -> a revision whose page_id = that page's id (and whose article_id is therefore NULL)
     cms_articles.*_revision_id
       -> a revision whose article_id = that article's id
     cms_paths.revision_id -> a revision owned by that claim's own page_id/article_id
     cms_media_references.owner_revision_id
       -> a revision owned by that reference's owner_page_id/owner_article_id
   Cross-type mixing is prohibited in all four directions:
     Page -> Article revision      REJECTED
     Article -> Page revision      REJECTED
     Page A -> Page B revision     REJECTED
     Article A -> Article B revision REJECTED
   Enforcement MECHANISM: composite foreign keys. A composite FK binds its column lists
   POSITIONALLY, first-to-first, second-to-second — the names do not matter and nothing is
   inferred. The canonical form used throughout this specification is therefore
   **(child owner column, child revision column) -> (parent owner column, parent id column)**:
     cms_pages(id, published_revision_id)
       -> cms_content_revisions(page_id, id)
         page.id                       <-> revisions.page_id   (owner match)
         page.published_revision_id    <-> revisions.id        (revision identity match)
   against the candidate keys UNIQUE(page_id, id) / UNIQUE(article_id, id) that section 13 adds
   to cms_content_revisions.
   (The reverse ordering — `-> revisions(id, page_id)` with the child written
   `(id, published_revision_id)` — is WRONG and was the IMP005-REAUDIT-R2-01 defect: it would
   bind page.id to revisions.id, i.e. require a page's own primary key to equal its published
   revision's primary key, and bind published_revision_id to page_id. A schema built that way
   would reject every legitimate pointer while accepting some mismatches. Every FK example in
   this document now states BOTH bindings on separate lines, so a reversal cannot hide.)
   This makes the mismatched-ownership state UNREPRESENTABLE at the storage layer (including via
   raw SQL), rather than merely disfavoured. Section 13 "Ownership-exact FKs" is the schema-side
   statement, including the DDL ordering consequence this creates; the service layer additionally
   validates under lock so the operator receives a classified error rather than a driver
   exception. Because a reversal of these two forms is only detectable by a fixture that can
   TELL them apart, section 29's O-series is required to use deliberately non-coinciding ids
   (O-positive-1 below): a fixture where page.id equals revision.id passes under BOTH the
   correct and the reversed mapping, and would therefore certify a broken schema.
```

Permitted lifecycle transitions (the only three UPDATEs any revision row ever receives):

```
   DRAFT      -> PUBLISHED      publish / re-publish / scheduled publish
                                sets published_at; identity pointer moves here
   PUBLISHED  -> SUPERSEDED     replacement publication (manual or scheduled)
                                sets superseded_at; identity pointer already moved to the new row
   DRAFT      -> SUPERSEDED     impossible; a never-published draft is either published or
                                replaced by a newer draft (which deletes nothing — see below)
   SUPERSEDED -> any            FORBIDDEN. History is never re-activated; re-publishing old
                                content uses rollback-by-copy (new revision).
   *          -> deleted        FORBIDDEN in every v1 path (section 27). Revision rows are never
                                deleted, so superseding always preserves historical content.
```

Enforcement layers (stated as defense-in-depth; the previous version of this list made a FALSE
technical claim and is corrected — see "Bulk updates" below. No layer here is described as
protecting against something it cannot protect against):

```
1. WRITE BOUNDARY — the structural control (primary, and the reason the other layers are
   backstops rather than the first line):
     - RevisionService and PublicationService are the ONLY code permitted to write
       cms_content_revisions. There is NO generic revision repository, NO public update() method,
       NO "save the model" helper, and NO bulk-update path exposed anywhere in the Content
       namespace. A caller that wants a revision row changed must go through one of exactly four
       named operations: createDraft(), editDraft(), publish(), supersede().
     - Anything else — an importer, a console command, a future stage, a repair script — either
       calls one of those four or is a review BLOCKER.
     - Each of the four re-reads the row under lock inside its own transaction and re-verifies
       state before writing, so the boundary is not merely "which class is calling" but "what the
       database says the row's state is at write time".
2. EXPLICIT FIELD WHITELISTS PER OPERATION (service-level, the mechanism that makes "payload
   immutable, lifecycle mutable" concrete). THE AUTHORITATIVE STATEMENT IS THE "REVISION OPERATION
   MATRIX" in this section — one table, every column, every operation. The prose below is a
   summary of that matrix and is not a second rule: where the two could be read differently, the
   matrix governs, and an earlier draft that let editDraft() write "payload columns ONLY" while
   separately requiring it to increment edit_version is corrected by including the concurrency
   token in editDraft()'s permitted write set explicitly:
     createDraft()   payload columns + state=DRAFT + revision_no + author/attribution
                     + edit_version initialized to 0
     editDraft()     payload columns + edit_version (increment by exactly 1), and only while the
                     locked row's state is DRAFT; it MUST NOT change state, published_at,
                     superseded_at, revision_no, or authorship
     publish()       lifecycle {state, published_at (only where currently NULL),
                     state_changed_by_principal_id}; NO payload column, and no edit_version write
     supersede()     lifecycle {state, superseded_at, state_changed_by_principal_id}; NO payload
                     column, no published_at change, no edit_version write
     SUPERSEDED rows: terminal. No operation writes them at all.
   No operation may write a payload column and a lifecycle column in the same statement, and the
   per-transition writes are one row each — section 11's bulk-write policy prohibits the builder
   forms outright, so these whitelists are enforced on the model path that actually exists.
   The whitelists are closed and exhaustive: an attribute that is not listed is not writable by
   that operation, and a proposed new column must be added to the matrix explicitly (a new payload
   column with no matrix row is a review BLOCKER, which is what stops the whitelist decaying into
   an allow-all).
3. MODEL GUARD — an immutability guard on the revision model rejects any dirty attribute outside
   the whitelist for the row's current state (mirroring IMP-004's application-level append-only
   guard for AuditRecord). SCOPE OF THIS GUARD, STATED HONESTLY: it protects instances that go
   through the model layer — `$revision->fill(...)`, `->save()`, `->update([...])` on a model
   instance, `Model::create()`, `->delete()`. It DOES NOT AND CANNOT protect against
   query-builder bulk updates (see "Bulk updates" below), nor against DB::table() writes, nor
   against raw SQL. Claiming otherwise was the IMP005-REAUDIT-R1-03 defect. It is a real defense
   for the accidental case (a developer with a hydrated model and a typo) and nothing more.
4. MASS ASSIGNMENT: every payload column is set only by services from validated DTOs;
   state/published_at/superseded_at/state_changed_by_principal_id/revision_no/author_principal_id
   are never fillable and never accepted from request input (section 28 mass-assignment row).
5. STRUCTURAL / STATIC TEST — because layers 3-4 are bypassable by design, the boundary is
   enforced by a test that SCANS THE APPLICATION CODE RATHER THAN EXECUTING IT: a test over
   app/ (and any future CMS-owning namespace) asserting that no call site performs a
   query-builder or Eloquent-builder mutation against cms_content_revisions / the Revision model
   outside the four whitelisted service methods, and that no Query Builder / DB::table() write to
   that table exists at all. A pattern-based source scan is a genuine, deterministic control here
   and is the ONLY thing that can catch a bulk update; the §29 R4 series specifies it.
6. DATABASE CONSTRAINTS WHERE THEY genuinely apply (the engine's own contribution, which is
   larger than layer 3's):
     - the composite ownership FKs of section 13 (IMP005-REAUDIT-R1-02) make a cross-owner or
       cross-type revision pointer unrepresentable even to raw SQL;
     - the CHECK on article_type (Page => NULL; Article => ARTICLE|NEWS) and the exactly-one-owner
       CHECK cannot be bypassed by any writer, bulk or otherwise;
     - UNIQUE(active_path) / UNIQUE(active_current_owner) / the active-draft uniques likewise.
   These constrain STRUCTURE, not payload mutation. Payload immutability against raw SQL remains
   outside what a constraint can express without a trigger.
7. FEATURE TESTS PROVE THE OUTCOME (section 29 R1/R2/R3/R6/R7): after every supported operation —
   replacement publish, scheduled publish, supersede, archive, draft edit, rollback-by-copy — the
   previously-published revision's payload is asserted BYTE-IDENTICAL to before. This is the layer
   that catches a regression in layers 1-5 rather than trusting them.
8. NOT a database trigger. Consistent with IMP-004's approach, payload immutability is an
   application invariant plus the structural tests above, not trigger code. A trigger is not
   prohibited, but it is not required either, and this specification makes no claim that the
   DATABASE enforces payload immutability.
9. Raw SQL / direct DB client writes are OUTSIDE application enforcement. The boundary is the DB
   account's grants (deployment), and the audit trail plus revision history make an out-of-band
   change DETECTABLE, not silently unrecoverable. This is the same boundary IMP-004 draws for
    AuditRecord and it is restated here rather than glossed.
```

Bulk updates — the corrected claim (IMP005-REAUDIT-R1-03):
```
The previous text asserted that `Revision::query()->update([...])` "is rejected by layer 2 [the
model guard]". THAT IS FALSE and is removed.

`Model::query()->update([...])` / `->where(...)->increment()` / `->bulkUpdate()` compile to a
single UPDATE statement executed by the QUERY builder. Eloquent does NOT hydrate model instances
for it, so the model's `saving` / `updating` events DO NOT FIRE, and the mutator/guard logic that
implements layer 3 IS NOT EXECUTED. `DB::table('cms_content_revisions')->update([...])` is the same
case with fewer moving parts. Neither call can be intercepted by model events, and no version of
Laravel changes this.

 THE POLICY, CHOSEN ONCE (IMP005-REAUDIT-R2-03 — the previous text said the prohibition was
 "absolute" in one paragraph while another allowed exceptions for internal lifecycle operations,
 which is two rules, not one):
   BUILDER / RAW / QUERY-LEVEL MUTATION OF cms_content_revisions IS PROHIBITED ENTIRELY, INCLUDING
   INSIDE THE SERVICES. Every revision write — payload AND lifecycle — goes through a HYDRATED
   MODEL INSTANCE, obtained by a locking read (`SELECT ... FOR UPDATE`) inside the service's own
   transaction, and saved through the model path. There is no allow-list of permitted bulk call
   sites; the set of permitted call sites is empty, which is a simpler thing to audit and to prove.
   The rejected alternative was to allow targeted query-level lifecycle writes from named service
   methods subject to field/owner/state/test conditions. It was rejected because:
     - it permanently bypasses layer 3 (the model guard) on exactly the path — lifecycle
       transition — where a mistake rewrites published history, buying a small performance win on a
       table with a handful of rows per content item;
     - "prohibited unless an approved internal operation exists" requires a reviewer to confirm the
       exception list is complete on every change, whereas "no builder writes exist" is a single
       greppable property (R4a), and R4a's whole design depends on there being nothing to except;
     - nothing needs a set-based write. Lifecycle transitions are per-row, each inside its own
       locked transaction, each emitting its own audit event. A batch would have to violate the
       audit contract to be faster than the correct implementation.
   What the policy does NOT prohibit, explicitly, so the boundary is unambiguous:
     - SELECTs of any shape, including through builders and DB::table — reads are unrestricted;
     - the `INSERT` of a NEW revision row (createDraft), which is a create, not a mutation of
       existing payload; a hydrated model `create()`/`save()` on a new instance is the required
       route, and an INSERT ... SELECT bulk copy is prohibited like any other bulk write;
     - writes to OTHER cms_* tables, which are governed by their own rules (identity pointers and
       path claims are also model-path writes; section 13 mass-assignment rules still apply).
   Consequence for optimistic concurrency: because writes are model-path and the row is already
   locked, the `edit_version` check is a LOCK-THEN-COMPARE-THEN-WRITE, not a
   `WHERE edit_version = ?` statement. Same guarantee (no lost update), one fewer way to bypass the
   guard — see section 19 step 5, which is stated in these terms.
   A reviewer finding any prohibited call site in the Content namespace is finding a BLOCKER.
```

The four payload/lifecycle operations named in layer 1 are the specification of the write
boundary; their transaction shapes are in section 26.

Editor / timestamp attribution: `author_principal_id` + `authored_at` are immutable PAYLOAD-frozen
on first write (who wrote this content, permanently); `state_changed_by_principal_id` and
`published_at`/`superseded_at` are lifecycle facts written by the transitioning service. The
identity row keeps `created_by_principal_id` / `updated_by_principal_id` for the durable subject.

Change history: revision rows (payload append-only) + canonical audit events (section 12).

## 12. Audit integration with IMP-004 (LOCKED sink)

Namespace reconciliation: the task examples used `cms.*`; the LOCKED IMP-004 taxonomy reserves the
`content` namespace for this domain ("content" appears in the reserved-namespace list —
IMP-004 spec, Event Taxonomy). Therefore canonical CMS events are `content.*`. No `cms.*` events
may be registered.

Registry rules inherited verbatim (LOCKED): registration in `AuditEventRegistry` keyed
`(event_type, event_version)`; registry (never caller) owns criticality, persistence strategy,
metadata allow-list, visibility class, actor constraints; unregistered event = hard error; Q26
default fail-closed for unclassified; `NON_CRITICAL` is explicit opt-out; `security.authorization.denied`
(DENIAL_DURABLE) already exists and covers unauthorized CMS attempts — no new denial event.

CMS event classification (ENGINEERING CHOICE applying Q26/IMP-004) — COMPLETE v1 INVENTORY.
Every authorized CMS mutation is mapped below; there is no "other" bucket and no TBD row.

Axes that are IDENTICAL for every event in this table (stated once, not repeated per row):

```
event_version         1
criticality           NON_CRITICAL for every content.* event (justification per row below; the
                      classification is owned by the REGISTRY, never by the caller)
persistence_strategy  neither MUTATION_ATOMIC nor DENIAL_DURABLE — NON_CRITICAL events use neither
                      strategy, per IMP-004 "Non-Critical Events" (IMP-004 pins this for the
                      migrated NON_CRITICAL events; content.* follows the same rule)
transaction semantics append inside the service-owned transaction that performs the mutation, in
                      the same connection, never queued, never deferred to after commit
visibility_class      GENERAL for every content.* event. None carries financial references, so
                      financial_reference_fields are NEVER populated, and audit.read.security /
                      audit.read.financial_reference are never implied by a CMS event.
read scope            audit.read (the base audit-read permission) + the Q27 formula. Registering
                      a content.* event grants no read capability of its own.
source_event_id       OMITTED for every content.* event (local producer — like the 28 migrated
                      events, there is no external producer identity to deduplicate against).
                      Business idempotency is carried by STATE + schedule_version + consumed
                      timestamps, NOT by audit deduplication.
assurance at write    STANDARD (AUTHENTICATION-ASSURANCE's high-risk categories are financial/
                      security/authority configuration; content work is not among them)
metadata discipline   hard-prohibited keys apply unchanged; content BODIES, file bytes, and raw
                      HTML NEVER enter metadata; every per-event contract in the metadata table
                      below is CLOSED (a key not listed for THAT event is rejected by the registry,
                      exactly as IMP-004 specifies), and absence is expressed by OMITTING the key,
                      never by sending null (see the absence rule under the metadata table)
```

| event_type | actor kinds | subject_type / subject_id |
|---|---|---|
| `content.page.created` | human | cms_page / pages.id |
| `content.page.updated` | human | cms_page / pages.id |
| `content.page.published` | human, system | cms_page / pages.id |
| `content.page.unpublished` | human, system | cms_page / pages.id |
| `content.page.archived` | human | cms_page / pages.id |
| `content.article.created` | human | cms_article / articles.id |
| `content.article.updated` | human | cms_article / articles.id |
| `content.article.published` | human, system | cms_article / articles.id |
| `content.article.unpublished` | human, system | cms_article / articles.id |
| `content.article.archived` | human | cms_article / articles.id |
| `content.page.schedule_updated` | human | cms_page / pages.id |
| `content.article.schedule_updated` | human | cms_article / articles.id |
| `content.page.schedule_expired` | system | cms_page / pages.id |
| `content.article.schedule_expired` | system | cms_article / articles.id |
| `content.media.uploaded` | human | cms_media_asset / media_assets.id |
| `content.media.updated` | human | cms_media_asset / media_assets.id |
| `content.media.archived` | human | cms_media_asset / media_assets.id |
| `content.media.purged` | system | cms_media_asset / media_assets.id |
| `content.homepage.assigned` | human | cms_homepage_assignment / the fixed singleton id (1) |
| `content.path.released` | human | cms_path / paths.id |

### Per-event metadata contract (IMP005-REAUDIT-R1-05)

The previous inventory declared each event's allow-list but then said, in the registry-entry shape,
"required metadata: exactly the row's closed allow-list" and "optional metadata: none" while the
allow-lists themselves contained conditionally-present and nullable keys. Those two statements
cannot both hold, and IMP-004's writer does not accept arbitrary nulls as a designed-in
representation of absence. Each event now has an explicit contract, and the contract is the
normative source for the registry entry.

Type vocabulary — constrained by what the LOCKED IMP-004 writer actually validates (verified
against `app/Services/Audit/AuditWriter.php` `assertValueShape()` and
`AuditEventDefinition`'s `array<string, 'int'|'string'|'array'>` allow-list shape):

```
  int      a PHP integer (is_int). IDs, counts, versions, and 0/1 flags.
  string   a PHP string. Paths, ULIDs, enum names.
  array    a FLAT sequential list of scalars (array_is_list, no nesting, no associative keys).
           An associative/array-shaped-map value is REJECTED by IMP-004.
  NOT AVAILABLE: bool, float, object, CarbonInterface, nested array, model, DTO.
     - Booleans are therefore encoded as int 0/1 (never PHP true/false — `is_int(true)` is false,
       so a real boolean FAILS validation and the write raises). This is a serialization choice
       forced by IMP-004's type vocabulary, not a preference; it is applied uniformly so no
       reviewer has to guess whether `redirect_created` or `homepage_designated` is a bool or an
       int. It does NOT touch Q26/Q27/Q28 or any criticality decision.
     - DATETIME FACTS ARE STRINGS. `publish_at`, `unpublish_at`, `scheduled_at` etc. MUST be
       serialized to ISO-8601 UTC strings (e.g. 2026-09-13T07:15:00Z) BEFORE being passed to
       AuditWriter::record(); a Carbon/DateTimeImmutable object is an object and is REJECTED.
       Strings are UTC, second-precision, and carry the explicit 'Z'. This also keeps the
       canonical-JSON idempotency comparison stable across writers.
     - Large sets are COUNTED, not enumerated, when enumeration could approach IMP-004's
       8192-byte serialized-metadata bound. Where a list is capped, the cap is stated.
```

Absence rule (replaces "nullable", applies to every event, and is the fix for the contradiction):

```
  A value that does not exist is OMITTED — the key is not present in the metadata array.
  NEVER `"previous_revision_id" => null`.
  Reason, stated rather than asserted: IMP-004's AuditWriter SKIPS type validation when a value is
  null (`if ($value !== null)`), so a null is persisted UNTYPED, and its registry allow-list
  declares no null type. Null-as-absence would therefore (a) escape validation instead of
  satisfying it, (b) make `{"a":null}` and `{}` DIFFERENT canonical JSON payloads — IMP-004
  compares canonical JSON for idempotency, so an inconsistently-emitted null can manufacture an
  AuditIdempotencyConflictException on a replay that should have matched, and (c) leave readers
  unable to tell "not applicable" from "deliberately unknown". Omission is the single representation
  of absence for all 20 CMS events. (IMP-004 tolerates nulls — `rbac.role.assigned`'s `scope_id`
  does exactly that — but tolerating a validation gap is not the same as adopting it as a contract,
  and IMP-005 does not.)
```

Prohibited keys for EVERY content.* event (in addition to IMP-004's closed HARD_PROHIBITED list,
which applies unchanged and cannot be extended by a CMS allow-list):

```
  body_html / any content body / any raw or sanitized HTML fragment / excerpts / captions as
  free text; file bytes; filesystem paths; URLs containing signed or tokenized material; password,
  token, secret, session, cookie or credential material of any kind (IMP-004's own list);
  financial identifiers of any kind (no ledger/donation/payment id may appear in a content event —
  these events are GENERAL visibility and must stay that way);
  and any key not declared for that event in the table below (registry-rejected, per IMP-004).
  Titles are referenced by IDENTITY (revision_id / page_id / article_id), never by value, so a
  renamed page cannot leak old title text into an audit payload that outlives the content.
```

The contract table. "Conditional" means: required in the stated circumstance, OMITTED otherwise —
never null. Every listed key is in that event's registry allow-list; no key is permitted that is
not listed for that event.

| event_type | REQUIRED | OPTIONAL | CONDITIONAL (and its trigger) | types |
|---|---|---|---|---|
| `content.page.created` | `revision_id` | — | — (`slug_snapshot` is NOT part of this event: a revision's slug_snapshot is NULL until a path is claimed, and claims exist only for published paths (sections 13/14), so create can never carry one. The earlier inventory listed it here, which under the no-null absence rule would have forced either a null value or a lie) | int |
| `content.page.updated` | `revision_id`, `fields_changed` | — | `slug_snapshot` (present only when the draft carries one, i.e. it is a scheduled draft whose snapshot was frozen at schedule time — sections 13/14); `article_type` never on a Page | int, array<string>, string |
| `content.page.published` | `revision_id`, `from_status`, `to_status`, `path`, `path_change`, `homepage_designated` | — | `previous_revision_id` (only a replacement publish — a FIRST publish OMITS it, never nulls it); `previous_path` AND `redirect_created` (= 1) — each ONLY when `path_change` = `RENAMED`, both OMITTED otherwise; the four schedule keys `schedule_version`, `scheduled_at`, `scheduled_revision_id`, `scheduled_by_principal_id` PLUS `system_operation` (`content.scheduler`) — ALL FIVE together, and only when actor = system; a manual publish omits all five | int, string, string, string, string, int, then int, string, int, string |
| `content.page.unpublished` | `revision_id`, `from_status`, `to_status`, `path` | — | `path_change` = `UNCHANGED` only when the withdrawn path is still its CURRENT claim (always true at withdraw, so in practice this key is present and equals `UNCHANGED`); the four schedule keys PLUS `system_operation` when actor = system | int, string, string, string, string |
| `content.page.archived` | `from_status`, `to_status`, `redirect_claims_retained`, `homepage_designation` | — | `path_claim_retained` (the identity's CURRENT path at archive time) is required when it holds an ACTIVE CURRENT claim and OMITTED when it does not (an archived never-published DRAFT has none); `article_type` never on a Page | string, string, int, string, then string |
| `content.article.created` | `revision_id`, `article_type` | — | — (same slug_snapshot exclusion as `page.created`) | int, string |
| `content.article.updated` | as `page.updated` PLUS `article_type` | — | as `page.updated` | int, array<string>, string, string |
| `content.article.published` | as `page.published` MINUS `homepage_designated` (Articles are never designated; the key is not in this event's allow-list) PLUS `article_type` | — | as `page.published` | as page.published, + string |
| `content.article.unpublished` | as `page.unpublished` PLUS `article_type` | — | as `page.unpublished` | as page.unpublished, + string |
| `content.article.archived` | as `page.archived` (no `homepage_designation` — see published) PLUS `article_type` | — | as `page.archived` | as page.archived, + string |
| `content.page.schedule_updated` | `operation`, `schedule_version` | — | exactly one of `publish_at` / `unpublish_at` / both, per what the new configuration actually sets (a cancelled field is OMITTED, not null; `CANCELLED` omits BOTH); `scheduled_revision_id`, `scheduled_at`, `scheduled_by_principal_id` present for `CONFIGURED`/`CHANGED` and OMITTED for `CANCELLED` (there is no longer a bound target) | string, int, then string, string, int, string, int |
| `content.article.schedule_updated` | same as `page.schedule_updated` PLUS `article_type` | — | same | as above, + string |
| `content.page.schedule_expired` | `schedule_version`, `outcome` | — | `publish_at` and/or `unpublish_at`: each present only if that deadline was the expired one being consumed; BOTH are omitted only when the event is the no-transition consumption of a fully-expired window whose timestamps were already cleared (see "Expired-window semantics" — in practice at least one is present, and the all-omitted case is legitimate and must not be padded with nulls); `retired` is NOT a key of this event (removed — see expired-window semantics) | int, string, then string, string |
| `content.article.schedule_expired` | same as `page.schedule_expired` PLUS `article_type` | — | same | as above, + string |
| `content.media.uploaded` | `asset_ulid`, `mime_type`, `extension`, `size_bytes`, `sha256` | `duplicate_asset_ulid` | `duplicate_asset_ulid` present ONLY when a same-hash ACTIVE asset already existed at upload time; otherwise OMITTED (the former "(nullable — set when…)" wording is replaced by this rule) | string, string, string, int, string, then string |
| `content.media.updated` | `asset_ulid`, `fields_changed` | — | — | string, array<string> |
| `content.media.archived` | `asset_ulid`, `prior_references` | — | — (`prior_references` is ALWAYS present and is the recheck result under the lock: `HAS_ACTIVE` or `NONE`. The former closed values `NONE` / `RELEASED_ONLY` and the note "ACTIVE references block the operation, so it can never appear here" are REMOVED with the rule they encoded — references no longer block archive: section 19) | string, string |
| `content.media.purged` | `asset_ulid`, `purge_attempts`, `active_references_verified_absent`, `previously_archived_by_principal_id`, `system_operation` | — | none — every key of this event is required, because this is the one event whose whole purpose is to prove a check happened. `active_references_verified_absent` is int 1 meaning "under the tier-2 lock, the ACTIVE reference count for this asset was 0"; since a PUBLISHED/SUPERSEDED revision's reference row is ACTIVE forever (section 19), that single assertion subsumes the historical case, which is why the former key name `historical_reference_verified_absent` was renamed rather than kept beside a second, separately-counted one. A value of 0 must never be emitted at all (the operation would have aborted), so the key is required AND its only lawful value is 1. `previously_archived_by_principal_id` is read from the asset row's `archived_by_principal_id` COLUMN (section 13) — never from the earlier archive audit event, which is NON_CRITICAL and may be lost | string, int, int, int, string |
| `content.homepage.assigned` | `operation`, `previous_expected_matched` | — | `page_id` + `page_ulid`: present for `ASSIGNED`/`REPLACED`, OMITTED for `CLEARED` (there is no new designee); `previous_page_id`: present when a prior designee existed, OMITTED when the slot was empty (a first assignment — never null); `expected_page_id`: present only when the request supplied one, OMITTED when it did not | string, int, then int, string, int, int |
| `content.path.released` | `path`, `purpose`, `owner_type`, `owner_id`, `reason` | — | none. `reason` is the closed value `GOVERNED_RELEASE` — the only release kind v1 has (section 13 RESERVATION POLICY: no automatic releases to describe) | string, string, string, int, string |

Derived-key definitions (so the table above is executable without invention):

```
  path_change           (string; the publication branch that ran — IMP005-REAUDIT-R2-02 makes
                         these distinct rather than folded together)
                           NEW        branch A: the identity had no ACTIVE CURRENT claim; one row
                                      was INSERTED for this path
                           UNCHANGED  branch B: the identity's existing ACTIVE CURRENT claim IS
                                      this path; no insert, its revision_id updated
                           RENAMED    branch C: the existing CURRENT claim was CONVERTED to
                                      REDIRECT and a new CURRENT row was INSERTED
                         One of the three is ALWAYS present on a published event; there is no
                         fourth value and no "unknown".
  redirect_created       CONDITIONAL, not required: int 1, emitted ONLY when
                         path_change = RENAMED. Same-path replacement and first publication OMIT
                         the key entirely rather than sending 0 — the transition it would assert
                         did not happen is fully described by path_change already, and an
                         always-present 0/1 on every publish duplicated a fact the sibling key
                         had just stated. Where present its only lawful value is 1. CHECKed by
                         test A6b: `redirect_created` present AND `previous_path` present
                         <=> `path_change` = `RENAMED`.
  homepage_designated    int 0 | 1 — whether THIS page identity is the singleton designee AFTER
                         this publication (read under the tier-5 order if the transaction takes
                         it; publish does not, so this is a post-commit-consistent read of the
                         singleton row inside the same transaction snapshot)
  path_claim_retained    the identity's CURRENT path string at archive time (see the archived
                         CONDITIONAL column)
  redirect_claims_retained  int — count of this identity's ACTIVE REDIRECT claims that REMAIN
                         reserved after the archive. The former `paths_released` array is REMOVED:
                         archiving releases nothing (section 13/27), so a list of "released paths"
                         on an archive event would describe an operation that does not happen.
                         This key replaces it, and it is the audit-side statement of the same rule
  homepage_designation   CLEARED_BY_ARCHIVE | NOT_DESIGNATED — on an archive event. A boolean
                         `homepage_designation_cleared` was the old shape; an enum states the same
                         fact without implying a separate event was emitted (there is none, and A3
                         asserts that)
  fields_changed         array<string> drawn from a CLOSED vocabulary, and the vocabulary is
                         PER-EVENT-KIND because two different tables are being described:
                           content.* (page/article updated): the REVISION payload set, exactly and
                             only — title, excerpt, body_html, meta_title, meta_description,
                             og_title, og_description, og_image_asset_id, no_index, slug_snapshot,
                             article_type (Article only). IMP005-REAUDIT-R2-03 removed
                             `published_on` from this list because it stopped being a column at
                             all: first_published_at is written once by publication and never
                             "changed" by a draft edit, so a vocabulary item naming it would have
                             been unmatchable. The list must equal section 13's payload columns
                             MINUS the identity/structural ones that are never edited
                             (revision_no, page_id, article_id, author_principal_id, authored_at),
                             and this correspondence is asserted structurally by test A14 rather
                             than trusted.
                           content.media.updated: alt_text, caption, original_filename
                             (the only three mutable columns on cms_media_assets — section 13)
                         A value outside the relevant vocabulary is a registry rejection. An EMPTY
                         list is legal and means "the operation audited state that is not a
                         payload change"; the key remains present as `[]` rather than being
                         omitted, because for an updated event "nothing in the tracked set
                         changed" is itself the fact being reported. IMP-004 accepts an empty
                         flat list (there is existing precedent).
  operation (schedule)   CONFIGURED | CHANGED | CANCELLED
  operation (homepage)   ASSIGNED | REPLACED | CLEARED
  outcome (expired)      see "Expired-window semantics" below
  system_operation       CLOSED set { content.scheduler, content.media_cleanup } — never free text
                         (section 19's cleanup actor rule)
  from_status / to_status  the identity lifecycle enum names (DRAFT|PUBLISHED|RETIRED|ARCHIVED),
                         as strings; the revision's own state is NOT reported here, so a reader
                         never has to guess which of the two `state` columns a status refers to
```

Expired-window semantics (IMP005-REAUDIT-R1-05 — one event per scheduled operation)

The prior text was ambiguous about whether an expired schedule emits one audit event or two:
section 10 said the both-deadlines-missed path should "clear both timestamps, emit schedule_expired,
plus unpublished only when retirement occurred", while this section's map presented
`schedule_expired` as the event FOR an expiry execution. Under the first reading a single due
unpublish could produce two content events for one mutation, and the map's own "each mutation maps
to EXACTLY ONE event" claim was then false. Resolved with one rule:

```
  THE CONTENT STATE TRANSITION IS THE CANONICAL AUDIT EVIDENCE.
  A schedule-lifecycle event is emitted ONLY for a schedule CONFIGURATION change or for a consumed
  window in which NO content state transition happened. It is never a second record of a
  transition that already has its own event.

  Scheduler outcome per identity           EVENTS EMITTED (exactly one, or exactly zero)
  -----------------------------------------------------------------------------------------------
  due publish executes                     content.<kind>.published   (actor=system, schedule
    DRAFT/RETIRED -> PUBLISHED, or            keys present)            — ONE event
    replacement with the bound draft
  due unpublish executes                   content.<kind>.unpublished (actor=system, schedule
    PUBLISHED -> RETIRED                      keys present)            — ONE event
  both deadlines missed and the            content.<kind>.schedule_expired
    correct action is NO transition           outcome = PUBLISH_SUPERSEDED_BY_UNPUBLISH
    (publishing then immediately                                     — ONE event
    retiring would expose content
    transiently, and it must not)
  unpublish deadline passed but the        content.<kind>.schedule_expired
    content is not PUBLISHED (nothing          outcome = NO_RETIREMENT_TARGET
    to retire)                                                       — ONE event
  bound revision no longer publishable     content.<kind>.schedule_expired
    (superseded, owner moved on, target          outcome = TARGET_INVALID
    already SUPERSEDED at execution)                                                     — ONE event
  stale re-run / timestamps already        NOTHING. NO-OP: no state change, no consumption,
    consumed / schedule_version moved         no event (section 10 idempotency; A7 asserts it)
  -----------------------------------------------------------------------------------------------
  The `retired` (0/1) metadata key on schedule_expired is REMOVED for exactly this reason: its
  only possible values were "a retirement also happened here", which is the duplicate-event case
  this rule eliminates. schedule_expired now asserts only what it can legitimately assert — that a
  schedule window was consumed without a content transition — and its `outcome` enum says why.
  WHY NOT "ALWAYS schedule_expired, NEVER the transition event": because then the audit record for
  a public visibility change would be a schedule bookkeeping row, and reading "when did this page
  go dark" would require joining on schedule semantics. The transition event is the fact;
  schedule provenance rides on it as metadata. WHY NOT "ALWAYS the transition event, drop
  schedule_expired": because three of the six outcomes above have no transition to report, and
  silently consuming a publisher's intent with no record is the gap that would need a compensating
  operational report. Both extremes were rejected; the table above is the middle that keeps one
  event per operation.
  Provenance of consumption on a transition event: the four schedule keys are REQUIRED when
  actor = system, so their presence plus `schedule_version` IS the evidence that this transition
  consumed that intent; no extra flag is added, and a manual transition must not fabricate them.
```

Exactly-one-event rule — precise wording (IMP005-REAUDIT-R1-05):

```
  "EXACTLY ONE EVENT" IN THIS SPECIFICATION MEANS:
      exactly one CANONICAL BUSINESS-MUTATION EVENT per committed business mutation.
  It does NOT mean:
      "only one audit record of any kind may exist in the transaction" — that is false and would be
      unsatisfiable. Audit evidence of a FAILURE or a DENIAL is a different kind of record,
      produced by a different path under IMP-004's own rules, and it may legitimately coexist:
        - security.authorization.denied (CRITICAL / DENIAL_DURABLE) is emitted by the canonical
          denial path when a CMS attempt is refused. In that case there is NO committed business
          mutation and NO content.* event — the denial record is the whole evidence. That
          compliance is IMP-004's, is not a CMS event, and is not counted against this rule.
        - an audit_write_failed REPORT for a NON_CRITICAL content event is an application-log
          record, not an audit row (section 12 failure semantics), so it is not a second event
          either.
  Scope of the rule, stated so it cannot be read two ways:
        one committed mutation  -> one canonical content.* event
        one refused attempt     -> zero content.* events (+ the IMP-004 denial record)
        one no-op re-run        -> zero events of any kind
        one expired window      -> one event, chosen by the table above, never two
  A test that asserts "exactly one audit row was written by this request" is therefore ASSERTING
  THE WRONG PROPERTY for any path that can also be denied, and the §29 audit tests are written
  against the content.* count, not against a whole-table count.
```

Mutation → event completeness map (this table is the audit-side proof for section 29; each
committed mutation maps to EXACTLY ONE canonical business-mutation event, per the rule above, so
there are no duplicate emissions to reconcile):

```
Page create                -> content.page.created          Article create      -> content.article.created
Page draft edit            -> content.page.updated          Article draft edit  -> content.article.updated
Page publish / re-publish  -> content.page.published        Article publish     -> content.article.published
Page replacement publish   -> content.page.published        Article replacement -> content.article.published
                             (previous_revision_id carries the superseded id — a replacement is
                              the SAME operation as a publish, so it is the SAME event, not a
                              second one)
Page unpublish (withdraw)  -> content.page.unpublished      Article unpublish   -> content.article.unpublished
Page archive               -> content.page.archived         Article archive     -> content.article.archived
Schedule set/change/cancel -> content.page.schedule_updated Article scheduling  -> content.article.schedule_updated
                             (operation distinguishes the three; one service operation, one event)
Schedule executed a        -> content.<kind>.published /    (the SAME event a manual transition
 transition                    content.<kind>.unpublished     emits, with actor=system and the
                             schedule keys; NO schedule_expired companion — see expired-window
                             semantics above)
Schedule consumed a no-    -> content.<kind>.schedule_expired (the only kind of expiry execution
 transition window             with an `outcome` value)         that produces this event)
Scheduler no-op (stale)    -> NO EVENT                      (idempotent re-run consumes nothing)
Media upload               -> content.media.uploaded
Media metadata edit        -> content.media.updated         (the previously-missing event:
                                                             alt/caption renames were mutating
                                                             audited state with no event)
Media logical archive      -> content.media.archived
Media physical purge       -> content.media.purged          (distinct from archive: different
                                                             actor kind, different effect,
                                                             different evidence — section 27)
Homepage designate/replace -> content.homepage.assigned     (a clear caused by an ARCHIVE is
                                                             recorded on the ARCHIVE event's
                                                             `homepage_designation` key, not as a
                                                             second event — see below and A3)
PATH CLAIM (first/current)-> NO separate event: it occurs only inside publish, so it is carried
                             by *.published (`path`, `path_change` = UNCHANGED). A claim with no
                             publication does not exist (section 14: claims exist only for
                             published paths).
PATH RENAME + REDIRECT     -> NO separate event: carried by *.published as
    CREATION                   `previous_path` -> `path`, `path_change` = RENAMED,
                             `redirect_created` = 1 — the three facts that together describe the
                             whole path transition, so the parent record is self-contained
                             (section 14 rename step 8; A4 + A6b assert it). A rename and its
                             redirect row are ONE operation, so one event; there is no companion
                             path.claimed / path.renamed / redirect.created event registered.
Path governed release      -> content.path.released         (the ONLY standalone path mutation)
ARCHIVE of an identity     -> NO path event: archive releases NO path (section 13 reservation
                             policy), so there is nothing to release and therefore no
                             content.path.released to emit. The retained path facts are carried by
                             *.archived (`path_claim_retained`, `redirect_claims_retained`). The
                             former "Auto-release on archive -> carried by *.archived
                             `paths_released`" row is REMOVED with the rule it described.
Homepage clear on archive  -> NO separate event: carried by *.archived
                             `homepage_designation` = CLEARED_BY_ARCHIVE.
Unauthorized attempt       -> NO content.* event: the existing security.authorization.denied
                             (CRITICAL / DENIAL_DURABLE) already covers it. No new denial event is
                             registered.
```

Criticality discipline (preserved from the audit's own conclusion; DO NOT inflate):

```
- Public visibility, terminal state, and media removal are NOT automatically CRITICAL. Every row
  above is NON_CRITICAL because no CMS event grants, moves, or removes authority; each is justified
  per row. Publishing a page is not comparable to granting a permission.
- The security-relevant CMS case is already covered and unchanged: an unauthorized attempt uses
  `security.authorization.denied` (CRITICAL / DENIAL_DURABLE), where DENY REMAINS DENY even if the
  audit write fails. IMP-004 is not modified to obtain any of the above.
- No content.* event may be the ONLY evidence of anything: the business row (identity state,
  revision pointer, asset status, path claim) is the durable record, and it persists independently.
  This is what makes NON_CRITICAL acceptable here rather than a convenience.
```

Audit-persistence-failure semantics (exact; per IMP-004 "Non-Critical Events", not paraphrased):

```
For every content.* event above, on failure of AuditWriter::record() persistence:
  1. the exception does NOT propagate to the caller — the business mutation COMMITS (the page
     publishes, the asset archives, the designation changes);
  2. the failure is REPORTED through the existing operational/application log (Laravel's default
     log stack) tagged distinctly with the `audit_write_failed` context, so it is visible to
     operations and observable by a test assertion. It is NOT silently swallowed and the phrase
     "ignore audit failure" does not describe this behavior anywhere in this specification;
  3. it is NOT queued for replay and NOT retried indefinitely (no Redis/Kafka — shared-hosting
     compatible). A lost non-critical event is an accepted, bounded, REPORTED risk;
  4. a committed transition is NEVER replayed in order to recover a missing audit event
     (restated in section 10 for the scheduler path, where replay would double-publish);
  5. the CMS therefore has no event whose loss would create an unrecoverable evidence gap — which
     is the precondition for using NON_CRITICAL at all, and the thing a reviewer should check
     before accepting any new content.* row into this table.
```

Explicitly NOT registered: `cms.*` under any name (the reserved namespace for this domain is
`content`); a hard-delete event (hard deletes of managed content do not exist, section 27);
`content.revision.*` beyond the publish flows (revision creation is covered by created/updated;
publication is the trust moment); content *view* telemetry (no requirement; avoids surveillance
noise); page/revision "restored" (ARCHIVED is terminal); a separate event for each of
claim/rename/redirect-creation (one service operation already maps cleanly to one event — emitting
companions would create reconciliation burden and double-audit risk with no new information);
`content.page.deleted` / `content.article.deleted` (no such operation).

Registry entry shape (what each of the 20 registrations above must carry). The metadata axes are
taken from the PER-EVENT METADATA CONTRACT table above, NOT from a single generic statement — the
former shape here declared "required metadata: exactly the row's closed allow-list" and "optional
metadata: none" in the same breath as conditional and nullable keys, which is what made the
contract undecidable. Corrected:

```
event_type             one of the 20 values in the table (registered; unregistered attempt = hard
                       error per IMP-004)
event_version          1
criticality            NON_CRITICAL
persistence_strategy   null / neither
visibility_class       GENERAL
subject_type           cms_page | cms_article | cms_media_asset | cms_homepage_assignment | cms_path
subject_id             the BIGINT internal id of that row; NOT nullable in any content.* event
                       (every content.* event has a real subject; there is no unknown-subject case)
actor_principal_kind   fixed by the row's "actor kinds" column; never supplied by the caller
required metadata      the event's REQUIRED column in the contract table — the writer MUST refuse
                       the event if any of these keys is absent. This is a per-event list, not a
                       shared one.
conditional metadata   the event's CONDITIONAL column: each key is required when its stated
                       trigger holds and OMITTED otherwise. A conditional key present without its
                       trigger, or absent with its trigger, is a defect in the emitting service
                       and is what the §29 A-series asserts on.
optional metadata      ONLY `content.media.uploaded.duplicate_asset_ulid`. Every other event's
                       optional column is empty, and "no key may be supplied for context unless it
                       is in that event's contract" holds exactly as before — an event carries
                       nothing "extra".
prohibited metadata    IMP-004's HARD_PROHIBITED_METADATA_KEYS (enforced at registration against
                       key names, unchanged) PLUS section 12's content-specific prohibition list
                       (bodies/HTML/file bytes/financial identifiers/titles-by-value)
nullability            NONE of the 20 events declares a null-valued key. Absence is omission
                       (see the absence rule). `AuditWriter` skips type validation for a null
                       value, so an allow-list that expected nulls would silently stop validating
                       those keys — which is the reason this is a hard rule rather than a style
                       preference.
value types            one of 'int' | 'string' | 'array' (flat scalar list) per key, as declared in
                       the contract table's types column; the registry is the only place a key's
                       type is stated, and no caller may vary it
metadata size          the serialized payload must stay inside IMP-004's
                       AuditWriter::METADATA_MAX_BYTES (8192) bound; this is why path/redirect
                       facts are carried as SINGLES or COUNTS rather than as unbounded arrays
                       (a 20-path array at 191 chars plus JSON overhead is ~4 KB and would sit
                       uncomfortably near a bound that every other key also competes for), and
                       `redirect_claims_retained` is an int count, not a list
```

IMP-004 REPERCUSSION (recorded, not silently absorbed, because it is a cross-stage fact a
reviewer must not be surprised by): `tests/Feature/Audit/AuditFoundationTest.php` pins the
registry inventory with `assertCount(29, $active) / assertCount(6, $reserved) / assertCount(35,
$all)`. Registering 20 `content.*` events ADDS to those counts, so IMP-005 implementation must
update that pinned inventory as part of registration. This is the additive-only case IMP-004's
"Registry Contract Consistency" anticipates, NOT a modification of IMP-004's behavior, and this
specification modifies no test itself (the change belongs to the implementation pass).

The former single-event example is removed: it listed `slug` and `is_homepage`, neither of which is
a CMS column any more (the path lives in `cms_paths`, the designation in `cms_homepage_assignment`),
and an example that drifts from the inventory is a worse guide than the inventory alone.

Scheduled transition attribution (Q32; IMP-004 Canonical Actor / IMP-003 catalog):

```
- Reference seeding registers content.scheduler in system_principals AND its linked principals
  row, using existing lifecycle/grant mechanisms. Explicit content.publish + ORGANIZATION grant
  is required; catalog code alone is not authority. No new actor kind or audit sink.
- actor_principal_kind = system on execution; actor_principal_id = principals.id, NEVER the
  system_principals catalog id. Schedule configuration records the real Human actor, without
  impersonation. Execution metadata links subject + schedule_version + scheduled_at + selected
  revision + scheduled_by_principal_id to the durable source intent.
- The active System Principal passes the canonical evaluator: explicit permission, scope,
  restriction and resource checks, with existing System context semantics (no fake user session).
  Revalidate source Human eligibility for content.publish in the same scope/restriction context;
  if no longer eligible, block until a currently authorized Human cancels/replaces the schedule.
  Both existing Human intent and current checks must hold; Cron creates no new business authority.
- Actual security-critical denials use the existing denial contract after releasing transaction
  locks. State no-ops are not denials; there is no blanket authorization exemption for execution.
- Schedule_updated has a Human actor and `operation` (CONFIGURED / CHANGED / CANCELLED); its
  metadata contract (section 12 table) lists the schedule version/timestamps/bound-target keys and
  makes each of them conditional on the operation, with absence expressed by omission.
  A SCHEDULED PUBLICATION or UNPUBLICATION (i.e. an executed transition) requires the four schedule
  source keys and is emitted as `content.<kind>.published` / `.unpublished` with actor=system;
  a SCHEDULE EXPIRY WITHOUT A TRANSITION is the `content.<kind>.schedule_expired` event and carries
  `schedule_version` + `outcome` (NOT the four transition-provenance keys — see the expired-window
  semantics for why the two cases are different events with different metadata). Unrelated manual
  events omit all of them. No service may fabricate schedule keys for a manual operation, and none
  may omit them for a scheduled one.
- State/version/consumed timestamps provide business idempotency (section 10). source_event_id
  is omitted: the durable source is not an audit-record FK, since a NON_CRITICAL intent event may
  be lost while its persisted source fields survive.
```

Audit-persistence failure for all 20 events is governed by "Audit-persistence-failure semantics"
above; it is not restated here, and no CMS code path may implement a different behavior for it.

## 13. Database architecture sketch (no migrations in this task)

Conventions (LOCKED): BIGINT unsigned PK/FK; ULID public identifiers; `utf8mb4`; DECIMAL never
needed (no money in CMS); strong referential integrity; MySQL 8.x primary target with SQLite test
parity (CHECK constraints are enforced on both modern engines; generated-column tricks noted
below are verified against both in the §29 matrix).

```
All tables: ENGINEERING layout choices; purpose/constraints traced to locked rules above.
Every FK uses RESTRICT unless stated. Every table below includes created_at/updated_at
(6-digit datetime, house convention) unless append-only is stated.
```

### `cms_pages`
```
purpose            managed page identity + lifecycle. NO path column, NO homepage column: both
                   moved to their authoritative single-purpose stores (cms_paths,
                   cms_homepage_assignment) so no namespace or singleton rule is encoded twice.
pk                 id BIGINT UNSIGNED AUTO_INCREMENT
ulid               CHAR(26) UNIQUE NOT NULL (public identifier)
columns            title (string 255 — identity-level display title, always mirroring the CURRENT
                      published revision's title; updated by the publish transaction only, never a
                      free-write column),
                    status (string 16: DRAFT|PUBLISHED|RETIRED|ARCHIVED),
                    latest_draft_revision_id (FK cms_content_revisions, nullable, RESTRICT —
                      draft rows are archived with the page, never orphan-deleted, so RESTRICT
                      holds for pointer columns),
                    published_revision_id (FK, nullable, RESTRICT),
                    publish_at / unpublish_at (nullable datetimes — Q32 LOCKED scheduling columns;
                      due-timestamp execution via scheduler, section 10),
                    schedule_version BIGINT UNSIGNED NOT NULL default 0,
                    scheduled_by_principal_id FK principals nullable RESTRICT, scheduled_at nullable,
                    scheduled_revision_id FK cms_content_revisions nullable RESTRICT; active intent
                    requires source Human/timestamp and publish intent requires owner-matching
                    target revision (service guards); consumed intent retains source fields until
                    the next authorized schedule change,
                    created_by_principal_id FK principals RESTRICT, updated_by_principal_id FK
                    principals RESTRICT
 REVISION-POINTER OWNERSHIP (IMP005-REAUDIT-R1-02; direction corrected per IMP005-REAUDIT-R2-01 —
                    each pointer is a COMPOSITE FK written (child owner, child revision) and
                    referenced as (parent owner, parent id), POSITIONALLY. All three pointers on
                    this table are shown with both bindings made explicit, because a reversed
                    column order is not detectable from the notation alone):
                      latest_draft_revision_id:
                        FK (cms_pages.id, cms_pages.latest_draft_revision_id)
                           REFERENCES cms_content_revisions(page_id, id)
                        binds  pages.id                    -> revisions.page_id
                        binds  pages.latest_draft_revision_id -> revisions.id
                      published_revision_id:
                        FK (cms_pages.id, cms_pages.published_revision_id)
                           REFERENCES cms_content_revisions(page_id, id)
                        binds  pages.id                   -> revisions.page_id
                        binds  pages.published_revision_id -> revisions.id
                      scheduled_revision_id:
                        FK (cms_pages.id, cms_pages.scheduled_revision_id)
                           REFERENCES cms_content_revisions(page_id, id)
                        binds  pages.id                   -> revisions.page_id
                        binds  pages.scheduled_revision_id -> revisions.id
                    Effect: `Page A.published_revision_id = <revision owned by Page B>` is an
                    ERRORED WRITE at the storage layer, not a state this schema can be left in. It
                    is unreachable by any code path, including raw SQL, an importer, or a manual
                    UPDATE, because the composite FK is checked by the engine on every statement.
                    WHY THE OWNER COLUMN COMES FIRST HERE: it makes the identity-pointer FKs, the
                    cms_paths revision FK and the cms_media_references revision FK all reference
                    the SAME two candidate keys, UNIQUE(page_id, id) / UNIQUE(article_id, id), so
                    the schema needs two candidate keys rather than four (section 13). The alternative
                    ordering is equally correct as a constraint but would require the mirrored
                    UNIQUE(id, page_id) set as well; carrying both sets to accommodate two notations
                    is redundancy with no enforcement benefit, and it is what this pass removed.
                    NULL semantics: a NULL pointer satisfies the FK trivially (MATCH SIMPLE, the
                    default on BOTH MySQL 8 and SQLite), which is correct — no pointer means no
                    claim about ownership. It is also why the positive fixtures in section 29 must
                    set the pointer to a real id rather than leaving it NULL.
path               NOT A COLUMN. The page's public path is its ACTIVE CURRENT row in cms_paths
                   (section 13). A page with no ACTIVE CURRENT claim is unreachable by path
                   (possible only transiently inside the publish transaction, never at commit).
homepage           NOT A COLUMN. Designation lives in cms_homepage_assignment (below), which is
                   the only place "which page is homepage" is recorded.
unique             none per-row on path (the namespace constraint belongs to cms_paths, where it can
                   cover Pages AND Articles AND redirects together — IMP005-SPEC-03)
indexes            (status), (updated_at), (publish_at, id), (unpublish_at, id)
soft-delete        NONE — ARCHIVED status is the lifecycle; no deleted_at duplication
security           ULID used in URLs/admin payloads; BIGINT id stays internal (IDOR surface reduced)
```

### `cms_homepage_assignment` (IMP005-SPEC-02)
```
purpose            designate WHICH page identity is the organization's homepage CONTENT. A
                   designation, not a route: route `/` stays application-owned (section 8).
pattern            dedicated single-row assignment table (Pattern B of the remediation options).
                   Why not the generated-discriminator alternative (Pattern A:
                   homepage_singleton_key = 'homepage' WHEN is_homepage = 1 ELSE NULL + UNIQUE):
                   Pattern A enforces "at most one" but cannot be swapped atomically. Moving the
                   homepage from A to B requires clearing A's discriminator and setting B's; when
                   the slot is momentarily empty, a concurrent third assignment can take it and the
                   swap then aborts on the unique index WHILE the other transaction has already
                   committed — producing two homepages after rollback of a transaction that had
                   already released the slot. There is no row to lock for an empty slot. A
                   dedicated singleton row always exists to lock, so every assignment, replacement
                   and clear serializes on it. Pattern B is therefore both the smaller and the
                   correct design here.
pk                 id TINYINT UNSIGNED NOT NULL  (CHECK id = 1 — exactly one row is representable)
columns            page_id BIGINT UNSIGNED NULL FK cms_pages RESTRICT — the designated identity;
                     NULL means "no homepage designated" (a legitimate state: v1 ships with none)
                   assigned_by_principal_id FK principals RESTRICT NULL
                   assigned_at TIMESTAMP NULL, updated_at
constraint         CHECK (id = 1) — SQLite and MySQL 8 both enforce CHECK, so this is a real
                   singleton invariant, not a convention. Combined with the BIGINT/ulid FK, the
                   database can never hold two homepage designations because it can never hold two
                   assignment rows.
concurrent assignment (no homepage exists; two requests assign different pages simultaneously)
                   1. Transaction A and B both SELECT the singleton row FOR UPDATE (row exists
                      from reference seeding, so the lock target always exists — no INSERT race);
                   2. the first to acquire it writes page_id = X and commits;
                   3. the second BLOCKS on the row lock, re-reads AFTER acquiring, and sees the
                      designation is now occupied;
                   4. default resolution: the later writer is REJECTED with 409
                      (homepage_assignment_conflict) reporting the current designee, because
                      silently winning a replacement race is a destructive surprise;
                   5. an explicit `expected_page_id` (or null) on the request is the documented
                      way to replace deliberately: it re-validates under the lock and fails 409 on
                      mismatch;
                   6. NO automatic retry: the loser reports the conflict to the operator.
                   Result: two active homepage assignments are impossible — established by the
                   one-row invariant plus the row lock, never by a pre-check.
clear              setting page_id = NULL through content.publish (homepage removal); same lock,
                   same expected-value guard, same audit event.
resolution         read by HomepageContentResolver (section 8/18) only; returns null unless the
                   designee is PUBLISHED with a current published revision.
```

### `cms_articles`
```
Same shape as cms_pages (title, status, revision pointers, scheduling timestamps/source/version
fields and due indexes; created_by/updated_by attribution). REVISION-POINTER OWNERSHIP applies
identically and is not a restatement-by-comment: each of `published_revision_id`,
`latest_draft_revision_id` and `scheduled_revision_id` on cms_articles is a COMPOSITE FK,
positionally bound owner-to-owner and revision-to-id:
  FK (cms_articles.id, cms_articles.<pointer>)
       REFERENCES cms_content_revisions(article_id, id)
  binds  articles.id       -> revisions.article_id
  binds  articles.<pointer> -> revisions.id
against UNIQUE(article_id, id) on the revisions table, so an Article can only ever point at a
revision it owns (IMP005-REAUDIT-R1-02; direction corrected per IMP005-REAUDIT-R2-01; see cms_pages
"REVISION-POINTER OWNERSHIP" and cms_content_revisions "Ownership-exact FKs"). The Page-side and
Article-side pointer FKs are SEPARATE constraints on SEPARATE tables referencing SEPARATE candidate
keys — neither can be satisfied by the other kind's revision, which is what makes Page↔Article
cross-type mixing unrepresentable rather than merely discouraged.
is_homepage and the path column are
ABSENT — Articles are never homepage-designated and their public path is likewise a cms_paths
claim. The former config-driven '<article_prefix>/' namespace scoping is REMOVED: with a single
shared cms_paths namespace there is no separate article namespace to prefix, and a config prefix
would be a second, unenforced copy of the path rule. An article's path is simply the claim the
publisher chose for it (e.g. /news/ramadan-appeal).
extra columns:   excerpt (string 511, nullable) — an explicit CURRENT PROJECTION of the published
                 revision's excerpt, NOT a second canonical owner (section 11 "Identity
                 projections"): maintained by the publish transaction alongside the pointer move,
                 never free-write, never request-assignable, and never copied in the reverse
                 direction.
                 first_published_at (datetime nullable; UTC) — identity-level, set ONCE by the
                 identity's FIRST-EVER successful publication and never written again by any later
                 publication, replacement, re-exposure or rollback-by-copy (section 11 "Canonical
                 field ownership"). It replaces the former `published_on`, whose name was removed
                 because it did not say WHICH of two real dates it meant; per-revision publication
                 time remains `cms_content_revisions.published_at`, also set once and never
                 rewritten. NULL means "never published", which is exactly the state of a DRAFT or
                 of content scheduled-but-not-yet-executed, so the column is genuinely nullable
                 rather than defaulted to a sentinel.
 ENGINEERING CHOICE minimal set; justified by admin-listing and destination-contract read cost,
 not by display habit.
```

### `cms_content_revisions`
```
purpose            versioned content PAYLOAD + the lifecycle columns that drive publication.
                   Payload is frozen once published; lifecycle moves by service transition only
                   (section 11). The former "append-only once PUBLISHED/SUPERSEDED" wording that
                   contradicted the SUPERSEDED flip is replaced by that split.
pk                 id BIGINT UNSIGNED AUTO_INCREMENT
 CANONICAL OWNER   (IMP005-REAUDIT-R1-02) Every revision belongs to EXACTLY ONE canonical CMS
                   identity, expressed with REAL FOREIGN KEYS (not a soft polymorphic type+id
                   pair, per DATABASE-ARCHITECTURE's strong-referential-integrity rule):
                     page_id    FK cms_pages    RESTRICT NULL
                     article_id FK cms_articles RESTRICT NULL
                     CHECK ((page_id IS NULL) <> (article_id IS NULL))   -- exactly one, non-null
                   This pair IS the canonical ownership relation for the whole CMS. Every other
                   revision-owning structure in this schema validates against it rather than
                   inventing a parallel notion of ownership.
                   The CHECK is the "exactly-one-owner invariant" the remediation requires and is
                   enforced identically on MySQL 8 and SQLite (both enforce CHECK).
 Ownership-exact FKs (the mechanism IMP005-REAUDIT-R1-02 asks to be defined, not asserted;
                   direction corrected per IMP005-REAUDIT-R2-01):
                   THE POSITIONAL RULE, stated once for the whole schema because it is the thing
                   that was gotten wrong: in
                     FOREIGN KEY (c1, c2) REFERENCES parent (p1, p2)
                   c1 is compared to p1 and c2 to p2. Column names are irrelevant to the matching,
                   the database does not align them by meaning, and a reversed pair is silently a
                   DIFFERENT constraint. Every composite FK in this specification therefore follows
                   ONE notation convention — child written (owner, revision), parent referenced
                   (owner, id) — and every restatement of one in prose or a test name spells out
                   both bindings on their own lines.
                   The identity→pointer FKs on cms_pages/cms_articles, the revision FK on cms_paths,
                   and the revision FK on cms_media_references are ALL COMPOSITE foreign keys that
                   carry the OWNER COLUMN ALONGSIDE the revision id, in that order. They work
                   because this table exposes the candidate keys declared under "candidate keys"
                   below, which exist SOLELY to be legal FK reference targets — a requirement of
                   both MySQL/InnoDB and SQLite. So:
                     cms_pages(id, published_revision_id)
                       -> cms_content_revisions(page_id, id)
                     binds  pages.id                   <-> revisions.page_id  (same owner)
                     binds  pages.published_revision_id <-> revisions.id       (that revision)
                   is satisfied only when the revision's page_id EQUALS the referencing page's id
                   AND the revision's id EQUALS the pointer. Consequences, all enforced by the
                   storage engine on every statement:
                     - Page A -> revision owned by Page B          : REJECTED (page_id mismatch)
                     - Page A -> revision owned by any Article     : REJECTED (revision.page_id is
                       NULL by the CHECK above, so it can never equal a non-null page id)
                     - Article A -> revision owned by any Page     : REJECTED (symmetric)
                     - Article A -> revision owned by Article B    : REJECTED
                   A reversed-parent form (`-> revisions(id, page_id)`) is NOT an equivalent
                   spelling of the above; it is a different, wrong constraint. It must never appear
                   with a child written (id, <pointer>). Section 29's O-series uses non-coinciding
                   page/revision ids precisely so a reversed constraint cannot pass the tests.
                   WHY THIS INSTEAD OF "THE SERVICE VALIDATES IT": a service check is correct only
                   until the first code path that forgets it — an importer, a repair command, a
                   future stage's direct write, or a bug in the check itself. The cross-type and
                   cross-owner classes are therefore made STRUCTURALLY UNREPRESENTABLE, which is
                   strictly stronger than a guard, costs no extra query, and cannot drift. A
                   service-level check is RETAINED on top for the fields the FK cannot see
                   (state, ownership at the moment of a pointer move, schedule eligibility) and for
                   producing a classified 422/409 instead of a raw driver exception — see section
                   11 "Write boundary" and section 26. The service check is the DIAGNOSTIC layer;
                   the composite FK is the AUTHORITY. Neither is described as optional.
                   DDL ORDERING CONSEQUENCE (normative, because it is a real implementation trap):
                   the two tables reference each other, so they must be created in this order —
                   (1) create cms_pages / cms_articles WITHOUT the revision FKs, (2) create
                   cms_content_revisions WITH its page_id/article_id FKs and its candidate keys,
                   (3) ALTER cms_pages / cms_articles to ADD the composite pointer FKs. Likewise a
                   Page cannot be INSERTed with its pointer columns already set (the revision does
                   not exist yet): identity rows are created with NULL pointers, the revision is
                   inserted, and the pointer is set in the same transaction. Any path that commits
                   between those writes is prohibited by the single-transaction rule (section 26).
                   The mutual RESTRICT is deliberate and harmless: a Page whose revisions exist can
                   never be deleted, and v1 deletes no rows at all (section 27).
payload columns    revision_no (BIGINT, per-owner increasing, immutable),
                   title (string 255 — versioned: titles change with revisions),
                   excerpt (string 511 NULLABLE — versioned editorial summary; canonical owner is
                     the REVISION (IMP005-REAUDIT-R2-03). Page rows may store it and the Page
                     surface simply never writes it; the column is shared because the table is
                     shared, and the section 11 ownership rule, not a table split, is what keeps
                     a Page from ever presenting one),
                   article_type (string 16; ARTICLE|NEWS for Article, NULL for Page),
                   CHECK: Page owner => NULL; Article owner => ARTICLE or NEWS; new Article
                   drafts default ARTICLE, service + DB validation rejects unknown values,
                   slug_snapshot (string 191; the normalized canonical path this revision was
                     published at — an immutable record, NOT the live routing authority, which is
                     cms_paths; NULL while DRAFT before a path is claimed; frozen with a scheduled
                     draft),
                   body_html (LONGTEXT, sanitized per section 20),
                   meta_title (string 255 null), meta_description (string 511 null),
                   og_title (string 255 null), og_description (string 511 null),
                   og_image_asset_id (FK cms_media_assets RESTRICT null — a STRUCTURED media
                     reference; it participates in the canonical media reference model, section 13
                     `cms_media_references` + section 19, and is never invisible to reference
                     checks the way a body-token-only scan would leave it),
                   no_index (TINYINT bool default 0),
                   author_principal_id FK principals RESTRICT + authored_at (immutable origin)
 candidate keys     UNIQUE(page_id, id)
                    UNIQUE(article_id, id)
                    (Both trivially unique — id alone is the PK. They exist SOLELY to be legal
                     composite-FK reference targets, which MySQL/InnoDB and SQLite both require,
                     and they are the ONLY such keys the schema needs: because every composite FK
                     in this table's direction convention lists (owner, revision), the same two
                     keys serve cms_pages, cms_articles, cms_paths AND cms_media_references
                     simultaneously. IMP005-REAUDIT-R2-01: the previous four — these plus mirrored
                     UNIQUE(id, page_id) / UNIQUE(id, article_id) — were needed only because the
                     identity-pointer FKs were written against the reversed parent ordering
                     (id, <owner>). With one consistent direction there is no second ordering to
                     key for, so the mirrors are REMOVED rather than kept "just in case"; a
                     redundant unique index is not free (write cost, migration surface, and a
                     future reader wondering which pair is authoritative). The generated
                     page_key/article_key columns below are NOT used as FK targets: coalescing an
                     absent owner to 0 would let a bogus owner value of 0 satisfy the constraint.)
lifecycle columns  state (string 16: DRAFT|PUBLISHED|SUPERSEDED), published_at (nullable; written
                  ONCE by publish() where currently NULL and never rewritten afterwards —
                  including on re-exposure after retirement, section 11 "Publication timestamps"),
                  superseded_at (nullable; written once), state_changed_by_principal_id FK
                  principals RESTRICT NULL — these FOUR and only these are written by
                  PublicationService (section 11 "LIFECYCLE-MUTABLE FIELDS")
concurrency column edit_version INT UNSIGNED NOT NULL default 0 — the DRAFT optimistic-locking
                  token. NOT a lifecycle column (IMP005-REAUDIT-R2-03: it was previously
                  classified as one while being required to be written by editDraft(), which the
                  lifecycle whitelist forbade — a contradiction). Its only writer is
                  RevisionService::editDraft() (increment by exactly 1, alongside the payload it
                  validates) and createDraft() (initialize to 0); createDraft/editDraft are the
                  ONLY operations permitted to write it. Meaningless once state != DRAFT, but
                  never reset and never reused as anything else. The authoritative per-operation
                  write permissions are section 11's REVISION OPERATION MATRIX.
generated keys     page_key    = COALESCE(page_id, 0)   STORED
                   article_key = COALESCE(article_id, 0) STORED
                   (THE ONLY declaration of these two columns — an earlier revision of this
                    document stated them twice, which is the IMP005-REAUDIT-R2-07 duplication;
                    this block is canonical and the duplicate is removed.
                    Needed because a UNIQUE index over a nullable FK column cannot detect
                    duplicates in MySQL/SQLite: two NULLs never collide. Coalescing to 0 makes the
                    owner dimension total, so the revision_no uniqueness below is real on BOTH
                    engines. These serve the per-owner revision_no uniqueness ONLY; the ownership
                    FKs reference the REAL page_id/article_id columns via the candidate keys above,
                    never these, because a COALESCE to 0 would let a bogus owner value of 0 match.)
unique             (page_key, article_key, revision_no) — per-owner revision numbering is unique;
                   single-active-draft: generated active_draft_page_id (= page_id WHEN
                   state='DRAFT' ELSE NULL) UNIQUE and active_draft_article_id (= article_id WHEN
                   state='DRAFT' ELSE NULL) UNIQUE — NULLs do not collide on a unique index, so
                   exactly one DRAFT per owner is DB-enforced on both engines, plus a service-level
                   guard inside the mutation transaction; validated in the §29 matrix
indexes            (state), (article_key, state), (published_at)
immutability       application-enforced and STATE-CONDITIONAL (section 11). WHAT THIS DOES NOT
                    CLAIM: it is NOT enforced by the storage engine, and the model-level guard
                    does NOT intercept query-builder/Eloquent-builder bulk updates, because those
                    do not hydrate models and do not fire model events. `Revision::query()->
                    update(...)` and `DB::table('cms_content_revisions')->update(...)` therefore
                    SUCCEED AT THE ENGINE LEVEL and are prohibited by CALL-SITE POLICY instead —
                    enforced by the section 11 write boundary (no generic update surface exists),
                    the per-operation field whitelists, and a structural test that greps the
                    application namespaces for such call sites (section 29, R4a). Payload
                    immutability for rows that do pass through the model layer is the guard;
                    ownership integrity is the composite FKs (which the engine DOES enforce
                    unconditionally); and the residual raw-SQL risk is a deployment-grants
                    boundary, named as such rather than papered over.
                    Every revision row rejects DELETE on every v1 path (section 27).
                    AuditRecord's unconditional no-UPDATE rule is deliberately NOT copied here:
                    publication requires lifecycle UPDATEs, which is the contradiction this schema
                    resolves rather than re-states.
```

### `cms_paths` (IMP005-SPEC-03 — the canonical public path namespace)
```
purpose            THE single reservation registry for the public CMS path namespace. Every
                   CMS-routable path — a Page's current path, an Article's current path, or an
                   old path kept alive as a redirect — is ONE ROW HERE and nowhere else. This
                   supersedes the former `cms_slug_history` plus per-table `active_slug` uniques,
                   which could not protect a shared namespace: three independent unique indexes
                   (pages, articles, history) permit Page /news/x and Article /news/x and a
                   redirect row /news/x to coexist, because no index spans all three.
columns            id BIGINT UNSIGNED AUTO_INCREMENT
                   path (string 191 NOT NULL — normalized per section 14, always stored WITH the
                     leading '/', lowercase, per-segment normalized)
                   purpose (string 16 NOT NULL: CURRENT | REDIRECT)
                   page_id    FK cms_pages    RESTRICT NULL  \
                   article_id FK cms_articles RESTRICT NULL  / exactly one set —
                   CHECK (page_id IS NULL) <> (article_id IS NULL)   owner is a content identity
                   revision_id FK cms_content_revisions RESTRICT NOT NULL — the revision whose
                     publication created this claim (provenance; for REDIRECT rows, the revision
                     that HELD this path before it was superseded).
                     OWNERSHIP CONSTRAINT (IMP005-REAUDIT-R1-02; direction restated per
                     IMP005-REAUDIT-R2-01 to make both bindings explicit): this is NOT an
                     unconstrained single-column FK. It is TWO composite FKs, one per owner kind,
                     each listing the OWNER FIRST and the revision SECOND, matching the identity
                     pointers exactly:
                       cms_paths(page_id, revision_id)
                           -> cms_content_revisions(page_id, id)
                         binds  paths.page_id     -> revisions.page_id
                         binds  paths.revision_id -> revisions.id
                       cms_paths(article_id, revision_id)
                           -> cms_content_revisions(article_id, id)
                         binds  paths.article_id   -> revisions.article_id
                         binds  paths.revision_id  -> revisions.id
                     Both reference the SAME two candidate keys the identity pointers use —
                     UNIQUE(page_id, id) / UNIQUE(article_id, id) — which is the payoff of keeping
                     one direction convention schema-wide.
                     InnoDB/SQLite skip an FK check when any child column is NULL, and the CHECK
                     XOR on this table guarantees exactly one owner column is NULL, so for every
                     row EXACTLY ONE of these two constraints is live — the one matching the
                     claim's owner kind. A cms_paths row whose revision belongs to another identity,
                     or to the other identity KIND, is therefore rejected by the engine on every
                     write path, including raw SQL. Making revision_id NOT NULL is what turns the
                     constraint from "vacuously satisfied by NULL" into an invariant, and it is
                     always satisfiable: a CURRENT claim names the revision PUBLISHED AT IT (and
                     that value is UPDATED on same-path replacement — section 14 provenance rule),
                     and a REDIRECT claim names the revision that held the path when it was
                     converted (section 14 rename step 4), so no claim has ever existed without one.
                   destination_owner_page_id / destination_owner_article_id — NOT stored. A
                     REDIRECT row's target is resolved by following ITS OWN owner to that owner's
                     ACTIVE CURRENT claim, so a redirect can never go stale when the owner is
                     published at a new path a second time. The replacement reference is therefore
                     the owner pair itself; a duplicated destination column would be a second
                     source of truth that must be resynced on every later rename.
                   status (string 16 NOT NULL: ACTIVE | RELEASED), released_at nullable,
                   created_at/updated_at
 generated          ALL generated columns are STORED (MySQL 8 cannot index a VIRTUAL column that
                    uses non-deterministic expressions, and STORED is portable to SQLite):
                      active_path         = CASE WHEN status = 'ACTIVE'
                                                 THEN path ELSE NULL END
                      active_current_owner = CASE WHEN status = 'ACTIVE' AND purpose = 'CURRENT'
                                                 THEN CONCAT(CASE WHEN page_id IS NOT NULL
                                                                  THEN 'P' ELSE 'A' END, ':',
                                                              COALESCE(page_id, article_id))
                                                 ELSE NULL END
                    (The owner key is a STRING tag, not a bare BIGINT: page_id and article_id come
                    from independent sequences, so a page id and an article id can legitimately be
                    the same integer — a shared numeric key would produce false collisions. The
                    'P'/'A' prefix makes the identity total across kinds. COALESCE is safe here
                    because the CHECK XOR above guarantees exactly one is non-NULL.)
                    These generated columns — not a filtered index — are how the invariants are
                    expressed: MySQL 8 has NO partial-index syntax, so `UNIQUE(...) WHERE
                    purpose='CURRENT'` is not implementable and MUST NOT be attempted. NULLs never
                    collide in a unique index on either engine, which is what makes the CASE-
                    wrapped form behave exactly like a partial index.
UNIQUE             UNIQUE(active_path)  <-- THE namespace invariant:
                      ONE canonical normalized path -> AT MOST ONE active claim in the public CMS
                      path namespace, across every owner kind and every purpose simultaneously.
                      Page vs Page, Page vs Article, Article vs Article, current content vs
                      old-path redirect, and two concurrent claims ALL collide on this one index,
                      which is exactly what the three separate indexes could not do.
                    UNIQUE(active_current_owner) — exactly one ACTIVE CURRENT claim per content
                      identity: an identity can never be published at two paths at once, and this
                      also covers the revision dimension (a revision is published through exactly
                      one owner, so one active CURRENT claim per identity implies one per revision;
                      no separate revision-scoped index is needed).
                    RELEASED rows are excluded from BOTH invariants by construction (their
                    generated columns are NULL), which is precisely what makes governed reuse of a
                    released path possible (section 14 reuse policy) without weakening either rule.
indexes            UNIQUE(active_path), UNIQUE(active_current_owner),
                    (purpose, status), (page_id), (article_id), (revision_id)
reservation kinds  CURRENT  — the live address of published content
                   REDIRECT — a formerly published path kept reserved and pointing at its owner's
                     current address (what `cms_slug_history` used to be, now inside the namespace)
 RESERVATION POLICY (IMP005-REAUDIT-R1-01 — THE ONE RULE; the retired/archived contradiction that
                   lived between this section, section 14, section 27 and the superseded
                   cms_slug_history note is removed and replaced by exactly this):
                   A path is RESERVED while ANY ACTIVE row (CURRENT or REDIRECT) holds it, and
                   ONLY while such a row exists. Reservation depends on the ROW, never on the
                   OWNER'S lifecycle state. Therefore:
                     - PUBLISHING at a new path reserves it and (via RENAME, section 14) keeps the
                       old path reserved as a REDIRECT;
                     - RETIRING content releases NOTHING. Its CURRENT claim stays ACTIVE and
                       reserved (and 404s, because the owner is no longer PUBLISHED); its REDIRECT
                       claims stay ACTIVE, reserved, and 404 for the same reason. A RETIRED
                       identity's own path therefore cannot be taken by anyone else — including by
                       itself under a different identity, which is the namespace-hijacking class
                       this rule closes;
                     - ARCHIVING content releases NOTHING either. Its CURRENT and REDIRECT claims
                       stay ACTIVE and stay reserved (404). Archiving is a content lifecycle act,
                       not a namespace act;
                     - the ONLY way a path stops being reserved is an EXPLICIT governed release
                       (PathService::release() under content.archive, audited as
                       content.path.released), which is a deliberate human decision per path;
                     - NO automatic path reuse in IMP-005 v1, at any lifecycle moment, after any
                       delay. No expiry, no grace period, no scheduled reclaim of a path.
                   Why the safe end of the design space was chosen over the alternatives:
                     - "release on RETIRED" would let another identity claim the path of content
                       that can legitimately be re-published, so re-publishing would fail on a
                       namespace collision created by the withdrawal itself;
                     - "release automatically on ARCHIVED" would let an organization's retired
                       URLs (which may be externally linked, printed, or cached) be re-pointed at
                       unrelated content with no human decision in the record — a link-farm and
                       phishing-shaped outcome that costs nothing to prevent;
                     - releasing the REDIRECT while the content stays live at its new path is
                       simply redirect shadowing, the defect IMP005-SPEC-03 exists to remove.
                   Consequence accepted knowingly: reserved-but-unresolvable paths accumulate, and
                   freeing one requires an explicit, audited release. In a single-organization CMS
                   with 191-character paths and human-authored content, that is a manageable
                   backlog and it is the correct default.
release            ACTIVE -> RELEASED (row RETAINED, never deleted) by an authorized explicit
                     governed release ONLY — see RESERVATION POLICY above. NOT triggered by
                     retire, NOT triggered by archive, NOT triggered by time.
validation         service rejects path '/' and every reserved route (section 14) before the insert;
                     the unique index is the backstop, not the only check
```

### `cms_media_references` (IMP005-SPEC-05 — the canonical media reference model)
```
purpose            The ONE authoritative answer to "is this media asset used?" for EVERY media
                   relationship CMS supports. Deletion, archival and purge decisions read THIS
                   table; they never parse stored HTML, because a body-token scan silently misses
                   structured references (og_image_asset_id) and historical-revision usage — the
                   exact defect this table removes.
columns            id BIGINT UNSIGNED AUTO_INCREMENT
                   media_asset_id FK cms_media_assets RESTRICT NOT NULL
                   owner_page_id    FK cms_pages    RESTRICT NULL \
                   owner_article_id FK cms_articles RESTRICT NULL / exactly one — CHECK XOR
                   owner_revision_id FK cms_content_revisions RESTRICT NOT NULL — the revision
                     that carries the reference (historical precision: a SUPERSEDED revision's
                     usage is a DIFFERENT fact from the current draft's usage, and section 19
                     treats them differently).
                   OWNERSHIP CONSISTENCY (IMP005-REAUDIT-R1-02 — no field of this row may be independently
                     assignable):
                     - EXACTLY ONE identity owner: the CHECK XOR above (page_id xor article_id).
                     - The revision relationship is NOT optional: owner_revision_id is NOT NULL,
                       because every media relationship in v1 is carried by a revision payload
                       field (both entries of the closed `field_path` vocabulary live on the
                       revision — section 19). An identity-level-only reference would be a
                       reference to nothing versioned, and no such relationship exists.
                     - REVISION-OWNER CONSISTENCY IS DB-ENFORCED, not service-hoped (owner listed
                       FIRST, revision SECOND, as everywhere in this schema —
                       IMP005-REAUDIT-R2-01):
                         cms_media_references(owner_page_id, owner_revision_id)
                             -> cms_content_revisions(page_id, id)
                           binds  refs.owner_page_id     -> revisions.page_id
                           binds  refs.owner_revision_id  -> revisions.id
                         cms_media_references(owner_article_id, owner_revision_id)
                             -> cms_content_revisions(article_id, id)
                           binds  refs.owner_article_id    -> revisions.article_id
                           binds  refs.owner_revision_id   -> revisions.id
                       Both reuse the same two candidate keys UNIQUE(page_id, id) /
                       UNIQUE(article_id, id).
                       Exactly one is live per row (the NULL owner column makes the other
                       vacuous), so a reference row naming a revision owned by a DIFFERENT
                       identity — or by the other identity KIND — cannot be written at all,
                       including by raw SQL. The owner columns are therefore a CONSTRAINTED
                       PROJECTION of the revision's own owner, and are retained (rather than
                       derived by joining through cms_content_revisions) purely to make
                       "which assets does identity X use" a single indexed lookup. A reviewer
                       must read them as denormalized-but-proved, not as trusted input.
                     - FIELD-PATH CONSISTENCY IS ENUMERATED, not free text: reference_kind is
                       BODY_TOKEN | SEO_IMAGE and is constrained by CHECK to pair with field_path
                       exactly as section 19's closed vocabulary defines it (body_html <->
                       BODY_TOKEN, og_image_asset_id <-> SEO_IMAGE). A mismatched pair is a
                       CHECK violation. This is what stops the vocabulary from drifting into a
                       string column that means whatever a caller says it means.
                     - LOCK/VALIDATION BOUNDARY: reference rows are written only inside the
                       service-owned transaction that writes the referencing revision, under the
                       section 19 tier order (asset -> references -> identity+revision), with the
                       revision re-read under lock and its owner re-verified before the reference
                       rows are inserted. The FKs are the authority; the service check is what
                       produces a classified error instead of a driver exception.
                   field_path (string 64 NOT NULL — which payload position refers: 'body_html',
                     'og_image_asset_id'; the closed vocabulary is defined in section 19 and any
                     new media-bearing field MUST be added to it, which is what makes the model
                     complete-by-construction rather than "whatever we remembered to scan")
                   reference_kind (string 16 NOT NULL: BODY_TOKEN | SEO_IMAGE; CHECK-constrained
                     to the field_path pairing above)
                   status (string 16 NOT NULL: ACTIVE | RELEASED — and ONLY these two. Reference
                     rows do not have an archive/purge lifecycle; those are ASSET states
                     (section 13 cms_media_assets / section 27). The former `ARCHIVED` value in
                     this position was a category error and is removed), released_at nullable
                   STATUS MEANING     A reference row's EXISTENCE is the preservation claim, and its status means
                     exactly one thing: does the named REVISION PAYLOAD currently refer to this
                     asset? (section 19 "What a reference status means".) It is NOT a lifecycle,
                     NOT an approval state, and NOT an asset disposition.
 generated          NONE. (The former draft declared `media_active_key = CASE WHEN status='ACTIVE'
                    THEN media_asset_id ELSE NULL END` and then used it in no constraint — dead
                    schema. It is removed, not left to be decoded. The status dimension is a LOOKUP
                    need, served by the non-unique `(media_asset_id, status)` index below, not a
                    uniqueness need, so no NULL-collision generated column is required here.)
unique             UNIQUE(media_asset_id, owner_revision_id, field_path) — a reference exists once
                      per (asset, revision, field). All three components are NOT NULL, so this is a
                      plain composite unique that behaves identically on MySQL 8 and SQLite with no
                      generated-column workaround. Multiple embeds of the same asset in one body are
                      ONE existence reference (deletion needs existence, not a count — see the
                      no-mutable-counter house principle retained from the prior design).
indexes            (media_asset_id, status) — the deletion/purge query,
                   (owner_revision_id, status), (owner_page_id), (owner_article_id)
invariant          reference rows are written ONLY by the service that writes the referencing
                   revision, INSIDE THE SAME TRANSACTION (section 19/26). An ACTIVE reference and
                   its revision payload can therefore never disagree at commit time.
queryability       "assets with zero active references", "assets used by revision R", "assets used
                   ONLY by preserved history" are each a single indexed query on this table —
                   no HTML parsing, no scanning bodies at delete time
```

### `cms_media_assets`
```
purpose            content media library
pk                 id BIGINT; ulid CHAR(26) UNIQUE (also embedded as the body reference token,
                   section 20)
columns            disk (string 32: 'public' default — all v1 CMS media public by definition;
                   private documents are other domains' stores, so NO private-delivery path is
                   built here; visibility column omitted until required),
                   stored_filename (string 191 UNIQUE — GENERATED, not user input, section 19),
                   original_filename (string 255, display only, sanitized),
                   mime_type (string 127, sniffed), extension (string 16, validated pair with mime),
                   size_bytes (BIGINT UNSIGNED), width/height (INT unsigned null — images),
                   alt_text (string 255 null), caption (string 511 null),
                   sha256 (CHAR(64), indexed — duplicate DETECTION prompt on upload; duplicates
                   allowed but surfaced, not silently deduped — ENGINEERING CHOICE),
                   status (string 16: ACTIVE | ARCHIVED | PURGED — three states, because logical
                     archive and physical purge are DIFFERENT facts and must not share one value;
                     see section 27),
                   uploaded_by_principal_id FK principals RESTRICT,
                   archived_at nullable, archived_by_principal_id FK principals RESTRICT NULL
                     — written by the logical-archive transition. REQUIRED BY THE AUDIT CONTRACT:
                     `content.media.purged` must record who archived the asset, and that value
                     must come from a COLUMN, not by reading the earlier archive audit event —
                     the archive event is NON_CRITICAL and may have been lost (section 12), so an
                     audit record must never be the only source of a fact another audit record
                     asserts. Before this column existed the purge event's
                     `previously_archived_by_principal_id` had no derivable source at all.
                   purged_at nullable,
                   purge_attempts INT UNSIGNED NOT NULL default 0, last_purge_error (string 511
                     nullable) — retry evidence for the filesystem-failure path (section 19);
                     these are operational counters on an asset row, NOT balances, and are
                     excluded from the no-mutable-balance concern
status_rules       The full behavioural matrix for these three states is section 19's "MEDIA STATE
                   EFFECT TABLE" (attachability / renderability / archive permission / purge
                   eligibility) — it is stated there once and not duplicated here. Summarised:
                   ACTIVE   attachable and publicly renderable
                   ARCHIVED logically retired: NEVER newly attachable, existing references remain
                     resolvable and RENDERABLE (public and admin) — archiving an asset does not
                     withdraw it from live content (section 19/27); files still on disk; reference
                     rows retained; purge-eligible only with zero ACTIVE references
                   PURGED   physically deleted: file gone, row RETAINED (never deleted) as evidence
                   there is NO transition ARCHIVED -> ACTIVE and none PURGED -> anything
indexes            (sha256), (status), (mime_type),
                   (uploaded_by_principal_id, archived_at)
                     ^ IMP005-SPEC-10: corrected. The former `(uploaded_by, archived_at)` named a
                       column that does not exist; the only attribution column on this table is
                       uploaded_by_principal_id and the index now matches it.
deletion           archive/purge lifecycle per section 19/27; usage is recorded in
                   cms_media_references, never derived from this row
```

### Article classification (Q33; minimal engineering representation)

Article exposes `article_type` through its selected revision. Public listing/filtering uses only
the published revision's ARTICLE/NEWS value; draft classification cannot change public listings.
News shares Article revisions, publication, authorization, audit, slugs, scheduling, lifecycle
and SEO. No categories/pivot, generic taxonomy engine, News table or NewsService is required.
Additional classification values need a justified specification/change-control update. This
unification covers generic editorial content only; classification carries no security authority.

### ~~`cms_slug_history`~~ — SUPERSEDED by `cms_paths` (IMP005-SPEC-03)
```
The former table is NOT built. Its purpose (preserve inbound links on slug change) is now a
purpose='REDIRECT' claim inside the single canonical namespace, which is the only place the
namespace rule can be enforced across Pages, Articles and redirects together. Its separate
"old_path UNIQUE among active rows" index was the specific gap Codex flagged: it protected
nothing against a Page or Article claiming the same path concurrently.

Mapping of the old columns into cms_paths: old_path -> path (purpose=REDIRECT); owner
page_id/article_id -> unchanged (same CHECK XOR); from_revision_id -> revision_id;
active -> status ACTIVE/RELEASED; replaced_at -> released_at.
Behavior retained: REDIRECT rows are written ONLY by the publication service (system-written —
never user-supplied old paths); resolution follows the owner's ACTIVE CURRENT claim (301).
Behavior NOT retained: the former claim here said "when the owner is RETIRED or ARCHIVED the claim
releases and the old path 404s". Release on RETIRED/ARCHIVED is REMOVED (IMP005-REAUDIT-R1-01) —
no lifecycle transition releases anything. The path stays reserved and the resolver returns 404
because the OWNER is not PUBLISHED, not because the claim is gone. Section 13 RESERVATION POLICY
and section 14 resolution table are normative.
```

### Path history & reuse policy (deterministic v1 baseline; IMP005-REAUDIT-R1-01)
```
Question answered: may an old path such as /old-article be reused later?

ANSWER: NOT AUTOMATICALLY, EVER. A path becomes reusable only when a human explicitly and
auditably releases its claim row. The prior text of this block simultaneously claimed three
different rules (release on RETIRED / automatic release on ARCHIVED / release only by governed
action); they are consolidated into the single rule below, which section 13 and section 14 now
restate identically.

  RESERVED  <=>  an ACTIVE row in cms_paths (purpose CURRENT or REDIRECT) holds that path.
  Nothing else reserves a path. Nothing but an explicit RELEASE un-reserves one.

  - An ACTIVE REDIRECT claim occupies the SAME UNIQUE(active_path) slot as a CURRENT claim, so
    while it exists nothing — not the original owner, not another Page, not an Article, not a
    concurrent transaction — can claim that path. Redirect shadowing is impossible by construction
    rather than by convention.
  - Reuse becomes possible ONLY after an explicitly governed RELEASE
    (`PathService::release()` under content.archive, audited as content.path.released). Release
    sets status=RELEASED and retains the row forever (rows are never deleted, section 27); the
    released path is then claimable by any CMS content, and a new claim is a NEW row (the old row
    keeps its identity as the historical record).
  - RETIRED content reserves both its CURRENT and its REDIRECT claims. Re-publication therefore
    returns to the same address without a race, and no other identity can slip into it.
  - ARCHIVED content ALSO reserves both its CURRENT and its REDIRECT claims. The prior "automatic
    release occurs when the owner is ARCHIVED" rule is REMOVED: archive is a content lifecycle
    decision, and silently converting it into a namespace decision would let an externally linked
    URL be re-pointed at unrelated content with no human act in the audit record. If the
    organization later wants a path back, it releases it deliberately.
  - No silent expiry: v1 has NO time-based auto-release of redirects and NO invented retention
    duration (section 27 — no legal durations asserted anywhere in this specification). A grace
    period for redirect expiry is therefore NOT specified; if one is ever wanted it is a config
    value plus its own change control, not an implementation-time guess.
  - Reuse after release is safe and deterministic: it can never resurrect a live redirect because
    live redirects cannot be double-claimed, and the historical RELEASED row for the same string is
    a distinct row that does not collide (UNIQUE applies to active_path, which is NULL when
    RELEASED).
  - SCOPE NOTE: no bulk/automatic reclaim tooling is specified or implied. The governed release of
    one named claim is the entire capability. Anything beyond it (mass release, expiry jobs,
    reclaim-by-age) is OUT OF SCOPE for IMP-005 and would need its own change control.
```

### Human-Decision-resolved exclusions (recorded; NOT built)
```
cms_menus / cms_menu_items   NOT created in IMP-005 — Q30 defers menu/navigation presentation
                             configuration to IMP-006, where the module's "Theme presentation
                             configuration" ownership lands. IMP-005 contributes only the stable
                             destination contract (section 21) that such composition will target.
Content approval storage     NOT created — Q29 (no editorial approval workflow in v1: no tables,
                             no reviewer queue, no Approval-module integration, no approval states).
Translation/locale tables    NOT created per Q31: no locale columns, localized revisions, locale
                             indexes/pointers, translation joins or fallback trees.
News / taxonomy tables       NOT created: Q33 uses Article revision classification.
```

### Full-text note
```
No FULLTEXT index in IMP-005. Search field contract is declared in section 24; index creation
belongs to the domain that consumes it (IMP-026), keeping the SQLite/MySQL migration matrix clean.
```

### Relational ownership recheck (IMP005-REAUDIT-R1-02 completion; the §46 audit)

Every semantically-constrained pointer in the CMS schema, with the mechanism that constrains it.
A pointer with NO row here would be an unfinished design; nothing is left as "the service will
check it" without a named lock, a named validation and a named negative test.

| pointer | meaning | mechanism (child -> parent, POSITIONAL) | engine-enforced? | service check + test |
|---|---|---|---|---|
| `cms_content_revisions.page_id` / `article_id` | the canonical revision owner (exactly one) | real FK + `CHECK` XOR | YES (both engines) | creation is by RevisionService only; O-positive/O10 control |
| `cms_pages.published_revision_id` | what is currently live | `(id, published_revision_id) -> revisions(page_id, id)` | YES | pre-write owner check -> `revision_owner_mismatch` 422; O1/O3/O5/O9 |
| `cms_pages.latest_draft_revision_id` | the live draft | `(id, latest_draft_revision_id) -> revisions(page_id, id)` | YES | same; O5/O9 |
| `cms_pages.scheduled_revision_id` | the frozen publish target | `(id, scheduled_revision_id) -> revisions(page_id, id)` | YES | validated at schedule config (the target must be a DRAFT of THIS identity); O6/O9 |
| `cms_articles` (three pointers) | as above, Article side | `(id, <pointer>) -> revisions(article_id, id)` | YES | O2/O4/O5/O9 |
| `cms_paths.page_id` / `article_id` | claim owner | real FK + `CHECK` XOR | YES | — |
| `cms_paths.revision_id` | which revision is responsible for this claim | `(page_id, revision_id) -> revisions(page_id, id)` and `(article_id, revision_id) -> revisions(article_id, id)`, NOT NULL; CURRENT rows are UPDATEd to the newly published revision on same-path publication (section 14 provenance rule) | YES | insert happens only inside publication branch A/C; UPDATE in branch B/D; O7/O9, P13 |
| `cms_media_references.owner_*` | reference identity owner | real FK + `CHECK` XOR | YES | — |
| `cms_media_references.owner_revision_id` | which payload refers | `(owner_page_id, owner_revision_id) -> revisions(page_id, id)` and the article twin, NOT NULL | YES | attach protocol step 4; O8/O9 |
| `cms_media_references.media_asset_id` | what is referred to | real FK RESTRICT + the reference IS the existence claim | YES | attach protocol step 2 (`media_asset_not_attachable`); M3/M15 |
| `cms_content_revisions.og_image_asset_id` | structured media ref (direction: content -> asset) | real FK RESTRICT + write-time token validation (section 20 rule 5) + paired `cms_media_references` row | YES (FK) / reference-row pairing enforced in the transaction | M1 |
| `cms_homepage_assignment.page_id` | the designee | real FK RESTRICT + `CHECK (id=1)` singleton | YES | H-series; a designation is data, never a route (section 8) |
| `*.created_by_principal_id` / `updated_by_principal_id` / `author_principal_id` / `state_changed_by_principal_id` / `scheduled_by_principal_id` / `uploaded_by_principal_id` / `archived_by_principal_id` | attribution | real FK to `principals` RESTRICT | YES | resolved from the authenticated/System context, never from request input (section 28) |

All four composite-FK sites above use ONE column-order convention — child `(owner, revision)`,
parent `(owner, id)` — so exactly two candidate keys exist on `cms_content_revisions` and every
site references one of them. There is no `(id, <owner>)` referenced form anywhere in this
schema; a reader who finds one is looking at a defect (IMP005-REAUDIT-R2-01).

No semantically unconstrained pointer remains. Where a relationship is deliberately not a
foreign key, the reason is stated rather than omitted:
`audit_records.subject_id` is not an FK — that is IMP-004's own locked decision (a subject's domain
table may have lifecycle rules IMP-004 must not constrain), so CMS does not re-open it; the CMS
events' `subject_id` values are nonetheless always real internal ids of rows that exist at emit
time because every emission happens inside the transaction that mutated them.

Cross-table consistency that FKs cannot express, and where it is therefore enforced:
```
  "exactly one PUBLISHED revision per owner, and it is the one the pointer names"
     -> two facts about different rows; no FK states it. Enforced by the single publication
        transaction (section 26 flow 1) which moves the pointer and the states together, and by
        the section 11 precedence rule (the pointer is authoritative; disagreement is a defect).
        The DB-enforced pieces are the active-draft uniques and the owner-composite FKs. Tested by
        R1/R5 and by the matrix row for concurrent replacement.
  "a claim's path equals its revision's slug_snapshot"
     -> the publish transaction writes both from ONE normalized value, so they cannot diverge at
        commit. The rename order (section 14) is the only place a path changes, and it writes the
        NEW claim from the NEW revision's own slug_snapshot. Tested by P8.
  "an identity with a published_revision_id has exactly one ACTIVE CURRENT claim"
     -> UNIQUE(active_current_owner) bounds it at ONE; the transaction bounds it at NOT-ZERO.
        Tested by P7 (upper bound) and P8/P9 (the transition never loses it at commit).
```

## 14. Paths, normalization & reserved routes (IMP005-SPEC-03 / IMP005-SPEC-08)

The public path namespace is owned by `cms_paths` (section 13). This section defines how a path
string is produced, bounded, and checked. **Everything in this section applies to Pages AND
Articles identically** — there is no per-entity slug rule and no separate article prefix.

```
Normalization (deterministic, code-owned PathService; PER SEGMENT — nested paths are legal, so a
path is never treated as one flat slug):

  input "/news/My First Story/"
    1. trim leading/trailing '/' and ASCII whitespace
    2. split on '/' into ordered segments            -> ["news", "My First Story"]
    3. per segment, normalize INDEPENDENTLY:
         lowercase; Unicode NFKD -> ASCII transliteration (fallback: drop non-ASCII);
         [^a-z0-9]+ -> '-'; collapse repeated hyphens; strip leading/trailing hyphen of the segment
       -> ["news", "my-first-story"]
    4. REJECT (never silently drop) any segment that is: empty after normalization, ".", "..",
       or contains any '.' path-traversal element; backslash, NUL, percent-encoded forms, or a
       Windows-reserved device name (CON, PRN, AUX, NUL, COM1-9, LPT1-9) — reject rather than
       sanitize, so input
       that looks like traversal can never be laundered into a claimable path
    5. rejoin with '/' and prepend '/'                 -> "/news/my-first-story"
    6. compare against the reserved route registry (below) AFTER normalization
    7. enforce length/depth bounds (below)

  CORRECT:   "/news/My First Story"  ->  "/news/my-first-story"
  WRONG (the behavior this fix removes):
             "/news/My First Story"  ->  "news-my-first-story"   <- flattening destroys the
                                                                    namespace structure
  Every segment keeps its own identity; the '/' separators are preserved in the stored, compared,
  reserved-checked and resolved forms. Stored paths in cms_paths are always normalized, always
  leading-'/', never trailing-'/', so path comparison is exact string equality with no ambiguity.

Reject-only: if the normalized path is empty, out of bounds, reserved, or collides with an
existing claim, the create/update returns 422 (bounds/reserved) or 409 (collision) with the
suggested alternative surfaced by the client. No silent auto-renaming ("did you mean") is saved.

Length & depth bounds (IMP005-SPEC-08: 191 is a CHARACTER budget, not a segment count):
  max normalized TOTAL path length  = 191 CHARACTERS (code points), inclusive of separators and
    the leading '/'. This maps 1:1 onto `cms_paths.path` VARCHAR(191) utf8mb4 and its unique
    index: 191 chars x 4 bytes = 764 bytes, inside InnoDB's 3072-byte index limit for MySQL 8
    DYNAMIC row format, and identical on SQLite (no index-length limit). No separate path_hash
    column is needed, so there is no second representation to keep in sync.
  max SINGLE SEGMENT length         = 100 characters (config `cms.path.max_segment_length`)
  max DEPTH                         = 3 segments (ENGINEERING CHOICE, validation only — config
    `cms.path.max_depth`)
  Validation order: normalize -> bounds -> reserved -> claim. Bounds are enforced by BOTH the
  service (clear 422) and the column width (hard 191), so an implementation-time slip cannot create
  an over-long row that later fails silently at index time.
```

### Reserved route registry — derived, not handwritten

The prior handwritten list was incomplete: it omitted `/forgot-password`, which exists today as a
real application route (`routes/web.php`, named `password.request`, plus a POST of the same URI).
A handwritten list guarantees drift the moment any later stage adds a route. The registry is
therefore DERIVED from the application's own route table.

```
Canonical source (single source of truth), in this precedence order:
  1. THE LIVE LARAVEL ROUTE TABLE — every registered route's URI, first segment and literal
     prefix, collected at boot from `Route::getRoutes()` (both method-registered and group-
     prefixed URIs). This is authoritative precisely because it cannot drift: a route that exists
     in the application is reserved by definition, whoever added it, in whichever stage.
     The managed-content catch-all that CMS itself registers is EXCLUDED from the derived set
     (it is the resolver, not a claimable route).
  2. PROTECTED PREFIXES explicitly named by MASTER-REQUIREMENTS §3 — admin, api, donor,
     fundraiser, partner, campaign, zakat, wakaf, fidyah, qurban — reserved as PREFIXES (every
     depth), not merely as literal first segments, because later stages will register routes
     under them that are not yet in the route table at IMP-005 time.
  3. FRAMEWORK / OPS PATHS that are not Laravel routes but are owned by the deploy: storage, up,
     _ignition, _debugbar, build, vendor, favicon.ico, robots.txt, sitemap.xml, manifest.webmanifest,
     .well-known, and the root path itself.
  4. AN EXPLICIT DENY OVERRIDES LIST for routes that must stay unclaimable even if unregistering
     them later (defence against a future stage deleting a route and unknowingly freeing its
     namespace, e.g. a retired auth endpoint).

Reconciled current membership (as of this specification, against routes/web.php) — reserved first
segments derived from source 1: login, logout, register, donor, fundraiser, mfa, dashboard,
account, verify-email, invitations, forgot-password, reset-password
  ^ `forgot-password` and `reset-password` are now present; the previous text had `/password/*`
    (which is not a route) and listed `password` as a segment while MISSING `forgot-password`.
    The registry is stated as derived rather than enumerated, so this class of error is closed:
    the enumeration above is ILLUSTRATIVE of what the derivation produces today, and the
    derivation is what is normative. Tests assert the derivation, not this list.

Enforcement, all three required:
  - WRITE TIME: every claim (create, rename, publish, scheduled publish) checks the normalized
    path's first segment AND every prefix of the path against the registry; 422 on hit.
  - BOOT TIME: an assertion that the registry is non-empty, contains every protected prefix, and
    that no existing `cms_paths` ACTIVE claim intersects it — a later stage adding a colliding
    route fails loudly at boot instead of shadowing content silently at request time.
  - TEST TIME: §29 compares CMS claims against the canonical registry, including a test that
    registers a NEW dummy application route and proves the derived registry rejects a CMS claim
    for it without any CMS-side edit (proves derivation, not handwriting).

Adding system routes in later stages requires NO CMS edit — that is the point of deriving the
registry. The obligation that remains is the reverse one: a later stage must never DELETE a route
whose name it wants CMS to be able to claim without deciding that explicitly.
```

```
Claim / rename / release mechanics (the cms_paths operations; CLAIM and RENAME occur ONLY inside
the publication transaction that also moves the identity pointer — section 26):

 IMP005-REAUDIT-R2-02: PUBLICATION IS NOT ONE PATH OPERATION. The previous text described
 "CLAIM" as covering both a first publication and a same-path replacement, and both as INSERTS.
 That is wrong: an identity that ALREADY owns an ACTIVE CURRENT claim at the target path must NOT
 insert a second row for that path — doing so collides with UNIQUE(active_path) against its own
 existing row and would fail every same-path re-publication. Three mutually exclusive branches now
 exist, selected by a single predicate evaluated AFTER the identity and its CURRENT claim are
 locked. NO OTHER CODE PATH WRITES cms_paths, and no branch may fall through into another.

   BRANCH SELECTION (evaluate under the tier-3 identity lock, from the LOCKED rows, never from a
   pre-lock read — a stale read here would select the wrong branch):
     existing     = the owner's ACTIVE CURRENT cms_paths row (0 or 1; 1 is guaranteed by
                    UNIQUE(active_current_owner))
     target       = normalize(revision.slug_snapshot of the revision being published)
     A. FIRST PUBLICATION      : existing IS NULL
     B. SAME-PATH REPLACEMENT  : existing IS NOT NULL AND existing.path = target
     C. PATH RENAME            : existing IS NOT NULL AND existing.path != target
   A re-publication of RETIRED content is NOT a fourth branch: retiring releases nothing
   (section 13 RESERVATION POLICY), so the identity still owns its CURRENT claim and the case
   selects B or C by the same predicate. Its extra effect is listed under B.

  A. FIRST PUBLICATION — owner holds no ACTIVE CURRENT claim.
        1. LOCK identity (tier 3, lockForUpdate)
        2. LOCK the candidate revision; VALIDATE revisions.page_id/article_id = this identity
           (the composite ownership FK makes a mismatch unwritable; the check exists to produce a
           classified error — section 13)
        3. NORMALIZE + validate target (bounds, reserved registry, non-authoritative pre-check)
        4. INSERT one ACTIVE CURRENT row (path = target, owner, revision_id = candidate)
        5. PUBLISH the revision (DRAFT|RETIRED-target -> PUBLISHED, section 11 whitelist)
        6. MOVE identity pointers + refresh identity display projections
        7. SUPERSEDE the previous PUBLISHED revision IF one exists (possible: the identity was
           published, retired, and its claim was governed-released meanwhile — then re-published)
        8. AUDIT content.<kind>.published: path_change = NEW, path = target, NO previous_path,
           NO redirect_created
        9. COMMIT
     Race authority is UNIQUE(active_path): a duplicate-key failure rolls the whole transaction
     back and is reported as 409 path_conflict. UNIQUE(active_current_owner) is NOT at risk here
     because the owner had no CURRENT row to compete with. A SECOND CURRENT row for one owner is
     impossible either way — that is what the unique index is for.

  B. SAME-PATH REPLACEMENT / RE-PUBLICATION — owner already holds the ACTIVE CURRENT claim at the
     target path.
        1. LOCK identity (tier 3)
        2. LOCK the owner's existing ACTIVE CURRENT claim row (tier 4, by id, FOR UPDATE)
        3. LOCK the candidate revision; VALIDATE revisions owner = this identity
        4. VALIDATE normalize(revision.slug_snapshot) = existing.path (this is the branch
           precondition re-asserted under lock; if it is false this is branch C, not B)
        5. *** NO INSERT. *** The existing ACTIVE CURRENT row IS the claim; it stays the SAME ROW
           with the SAME id. UPDATE only its revision provenance (step 6). Inserting a second
           CURRENT row for the same path is a guaranteed UNIQUE(active_path) violation against the
           identity's own row and MUST NOT be attempted.
        6. UPDATE the existing CURRENT claim: revision_id = the newly published revision.
           THIS IS THE CANONICAL PROVENANCE RULE, CHOSEN EXPLICITLY (IMP005-REAUDIT-R2-02):
             A CURRENT claim's revision_id identifies THE REVISION CURRENTLY RESPONSIBLE FOR THE
             ROUTE. It is therefore mutable-by-publication: every publication at that path —
             first, replacement, or re-publication — sets it to the revision now served there.
             A REDIRECT claim's revision_id is FROZEN: it is the historical revision that held the
             path at the moment of conversion, and it never changes afterwards (that immutability
             is the only thing distinguishing the two purposes' use of one column).
           The rejected alternative was to treat a CURRENT claim as identity-owned and
           revision-independent, which would make `revision_id` on a CURRENT row meaningless — and
           `revision_id` is NOT NULL here precisely so the ownership composite FK cannot be
           satisfied vacuously. Both readings cannot be held at once, so the column's meaning is
           now stated, and the NOT NULL choice in section 13 is justified by it rather than
           tolerated by it. No "keep path identity-level" ambiguity remains: the row is
           identity-owned (owner columns never change) while its revision pointer is
           publication-owned.
        7. PUBLISH the revision; SUPERSEDE the previous PUBLISHED revision; MOVE identity pointers
           + refresh display projections
        8. AUDIT content.<kind>.published: path_change = UNCHANGED, path = target,
           previous_revision_id = the superseded revision, NO previous_path, NO redirect_created
        9. COMMIT
     RE-PUBLICATION OF RETIRED CONTENT (a sub-case of B, not a separate branch): the claim already
     exists and was never released, so steps 5-6 are identical — the SAME row is retained, its
     revision_id is set to the revision now served, and content becomes routable again because the
     OWNER's status returned to PUBLISHED (resolution depends on owner status, not on the claim,
     per the RESOLUTION table below). Nothing is inserted, nothing is re-created, and no path event
     is emitted. If the previously-retired revision is republished unchanged (no new DRAFT), step 6
     writes the same revision_id it already holds — a legal no-op UPDATE, and the identity's
     visibility transition is still the audited event.
     Concurrency: two simultaneous same-path publications of one owner serialize on the identity
     lock and then on the same claim row lock; there is no unique-index race in branch B at all,
     because branch B performs no INSERT. The loser is decided by the revision pointer it expected
     to replace (409 stale_publication), not by a key collision.

  C. PATH RENAME — owner holds an ACTIVE CURRENT claim at a DIFFERENT path.
     (IMP005-REAUDIT-R1-01 — executable ordering; the order is FORCED by the constraints. This is
     the ONLY branch that inserts a row while another CURRENT row for the same owner exists.)
            The previous ordering (insert the new CURRENT claim first, convert the old one after) is
            NOT EXECUTABLE and is removed. Reason: UNIQUE(active_current_owner) is an IMMEDIATE
            constraint over the owner key ('P:<id>' / 'A:<id>'). At the instant the new row would be
            inserted, the old row is still purpose=CURRENT and status=ACTIVE, so the owner key is
            NON-NULL ON BOTH ROWS WITH THE SAME VALUE — the INSERT fails on duplicate key before any
            conversion can run. MySQL 8 provides NO deferrable or deferred unique constraints (no
            PostgreSQL-style SET CONSTRAINTS ... DEFERRABLE), so that window cannot be made legal by
            delaying the check. The only correct fix is an ordering in which NO intermediate state
            violates ANY constraint.
            The transaction, in this order:
              1. LOCK the content identity row (cms_pages / cms_articles, lockForUpdate) — the
                 serialization point for "which transaction is renaming this owner";
              2. LOCK the owner's existing ACTIVE CURRENT claim row (SELECT ... FOR UPDATE by id;
                 exactly one exists, guaranteed by UNIQUE(active_current_owner));
              3. LOCK the candidate revision; VALIDATE revisions owner = this identity;
                 VALIDATE the target path — normalize (this section), bounds, reserved-registry
                 check, and the non-authoritative availability pre-check (see below);
                 confirm existing.path != target (else this is branch B);
              4. CONVERT the old claim: UPDATE purpose CURRENT -> REDIRECT and set revision_id to
                 the revision that was holding it — FROZEN at that point thereafter. status stays
                 ACTIVE, so active_path remains non-NULL and the old path is CONTINUOUSLY
                 RESERVED; active_current_owner on that row becomes NULL (the generated CASE no
                 longer matches purpose='CURRENT'), which releases the OWNER SLOT — not the path;
              5. INSERT the new ACTIVE CURRENT claim row (new path, owner, new revision);
              6. MOVE the identity routing pointers: published_revision_id -> new revision,
                 latest_draft_revision_id cleared, identity display columns (title; Article
                 excerpt) refreshed from the new revision, and Article first_published_at set if
                 this is the identity's first-ever publication;
              7. SUPERSEDE the previous PUBLISHED revision (lifecycle columns only — section 11);
              8. EMIT the PARENT canonical audit event (content.<kind>.published) carrying the path
                 transition facts (previous_path, path, redirect_created = 1). There is NO
                 standalone path event — section 12;
              9. COMMIT.
           Constraint audit of every intermediate state (this is what makes the ordering legal, and
           is the proof the remediation requires instead of an assertion):
             after step 4: old row = ACTIVE / REDIRECT (active_path = old path, owner key NULL);
                           new row absent. Rows carrying a non-NULL owner key = 0, so
                           UNIQUE(active_current_owner) HOLDS (it forbids two, not zero);
                           UNIQUE(active_path) holds (one row, one path).
             after step 5: old row ACTIVE/REDIRECT + new row ACTIVE/CURRENT. The two active_path
                           values differ (old != new) -> UNIQUE(active_path) HOLDS. Exactly ONE row
                           carries the owner key -> UNIQUE(active_current_owner) HOLDS.
             at commit:    identical to the step-5 state.
           Between steps 4 and 5 the owner momentarily has no CURRENT claim. That is invisible
           outside this transaction: the old row is write-locked from step 2, and the public
           resolver uses non-locking consistent reads that continue to see the PRE-rename committed
           snapshot until this transaction commits. No externally observable state ever shows the
           owner with two current paths, or with none.

  FAILURE SAFETY (explicit — the reason all of the above is ONE transaction). Stated for branch C,
  where an INSERT can fail after a conversion; branches A and B cannot reach the described state
  (A inserts without converting, so a failure leaves nothing behind; B does not insert at all).
           If branch C step 5 fails — and the realistic cause IS the constraint: another identity
           already holds, or concurrently claimed, the target path — the duplicate-key exception
           propagates and the ENTIRE transaction rolls back:
             -> the step 4 UPDATE is rolled back, so the old claim is RESTORED as purpose=CURRENT
                with its original revision_id. Restoration is the storage engine's own undo, not
                application compensating logic — the row was never deleted and never released;
             -> no new CURRENT row exists; no identity pointer moved; no revision superseded; no
                audit event persisted (the append lives in the same transaction and rolls back with
                it, per IMP-004 Transaction Ownership Invariant);
             -> the request returns 409 path_conflict.
           THEREFORE NO INTERMEDIATE EXTERNALLY VISIBLE BROKEN STATE EXISTS. In particular the
           state "old path left as a REDIRECT while the new CURRENT claim does not exist" is
           UNREPRESENTABLE, because no operation commits it. Consequences that are normative:
             - the rename MUST NOT be split across transactions, and MUST NOT commit after step 4
               to "do the insert next";
             - catching the duplicate-key error and MANUALLY RESTORING the old claim with
               compensating writes is REJECTED: rollback already restored it, and a hand-repair
               path would create a second, divergent route to the same row — the classic source of
               a "fixed" namespace that no longer matches its own history;
             - a crash before COMMIT is the same case (InnoDB rolls the open transaction back);
             - a NON_CRITICAL audit failure does NOT propagate (section 12), so the business
               mutation still commits WITH its path transition — the rename is never half-applied
               because the audit write failed.

  AVAILABILITY IS DECIDED BY THE CONSTRAINT, NOT BY THE PRE-CHECK (branch A step 3 / branch C
  step 3)
           Step 3 exists to classify operator errors cleanly (422 for bounds/reserved) before any
           write begins. It is NOT the reservation mechanism and must never be relied on as
           sufficient: between a pre-check and the insert, another transaction can take the path.
           The reservation is made ATOMICALLY BY THE INSERT ITSELF against the canonical
           constraints, exactly as IMP005-SPEC-03 established for concurrent claims. This sentence
           applies to branches A and C, which insert; branch B holds its reservation already and
           has no insert to race, so its only concurrency exposure is the identity/claim lock
           order described under B.

             application validation  (normalize -> bounds -> reserved -> availability pre-check)
                          +
             database UNIQUE(active_path) / UNIQUE(active_current_owner)
                          as the FINAL, RACE-SAFE AUTHORITY whose duplicate-key error is the
                          transaction's actual answer

           Two rejected shortcuts: dropping step 3 would be functionally correct (the constraint
           still decides) but would surface raw duplicate-key errors instead of classified 422s;
           adding a separate "reservation row" to make the pre-check authoritative would reintroduce
           a second source of truth for the namespace — the exact defect IMP005-SPEC-03 removed.
           Neither is done. A claim reserved "in application state" and absent from cms_paths is
           worth nothing and is a BLOCKER if implemented.

  RELEASE  ACTIVE -> RELEASED with released_at, by an EXPLICIT governed release
           (PathService::release() under content.archive) ONLY. It is NEVER automatic and never a
           side effect of retire or archive (section 13 "Path reservation policy"; this is the
           contradiction IMP005-REAUDIT-R1-01 removes). Audited as content.path.released. RELEASED
           rows are retained permanently and are what makes "was this path ever used?" answerable.

 CONCURRENCY: two transactions claiming the same path — whichever commits second receives the
   duplicate-key error on UNIQUE(active_path) and rolls back its whole transaction (claim + pointer
   + lifecycle flip + audit together); the error is translated to 409 path_conflict. No
   check-then-insert window exists: the insert IS the check. Cross-entity (Page vs Article) and
   current-vs-redirect collisions are the SAME case, which is exactly why they are now covered.
   BRANCH-SPECIFIC (IMP005-REAUDIT-R2-02): this describes branches A and C, which insert. Two
   branch-B (same-path) publications of the SAME owner never collide on a key — neither inserts —
   and serialize on the identity lock, then on the single shared CURRENT claim row, with the loser
   decided by its expected-revision precondition (409 stale_publication). Two branch-B publications
   of DIFFERENT owners cannot occur at the same path, because only one owner can hold that ACTIVE
   CURRENT claim (UNIQUE(active_path)); the other owner's branch selection would have found no
   CURRENT claim of its own and taken branch A, then lost on the insert. That is the complete
   case split — A-vs-A, A-vs-C and C-vs-C are all decided by UNIQUE(active_path), and every
   same-owner pair is decided by the identity lock first.
   Two renames of the SAME owner concurrently: both serialize on the identity row lock (step 1);
   the second re-reads the owner's CURRENT claim AFTER acquiring, finds the path already moved, and
   either re-selects to branch B (the new path now equals its target — a legitimate same-path
   replacement, not an error) or fails its expected-revision precondition with 409
   stale_publication. It never converts a claim it no longer owns, and it never inserts into the
   slot another transaction just took without the unique index deciding the winner.

 RESOLUTION AND 404 BEHAVIOR (single rule for every lifecycle state; the retired/archived
   contradiction is removed):
   Reservation and resolution are DIFFERENT questions and are answered independently:
     - RESERVATION (does this string block a new claim?) is decided ONLY by the existence of an
       ACTIVE row for it — entirely independent of the owner's lifecycle state. Neither retiring
       nor archiving content changes any reservation. Section 13 "Path reservation policy".
     - RESOLUTION (what does a request get?) is decided by the claim purpose AND the owner state:
         200 + content   path matches an ACTIVE CURRENT claim whose owner is PUBLISHED — render
                         that owner's CURRENT published revision
         301             path matches an ACTIVE REDIRECT claim whose owner is PUBLISHED and holds
                         an ACTIVE CURRENT claim — redirect to that current path
         404             every other case: no ACTIVE claim (never published, or governed-released);
                         owner RETIRED; owner ARCHIVED; a REDIRECT whose owner has no CURRENT claim
       Every non-resolving case is 404 — never 410, never a status echo, never a disclosure that
       DRAFT/RETIRED/ARCHIVED content exists (section 28). Draft/visibility control is server-side
       only.
   Redirect targets are always internal and system-computed from published content; there is no
   user-authored redirect target (open-redirect class eliminated by construction).
   Stated deliberately: an ARCHIVED identity's paths still RESERVE (they simply do not resolve),
   so "the destination is gone, therefore release the path" is NOT a v1 rule. Release is a governed
   human act, never a lifecycle side effect.
```

## 15. SEO

```
Baseline supported fields ONLY (columns on revisions — section 13): meta_title, meta_description,
og_title, og_description, og_image (media FK), no_index flag. Canonical = resolved published path.
Fallbacks: meta_title -> title; meta_description -> excerpt -> first body text (render-time
ENGINEERING CHOICE). No sitemap generation, no robots management UI, no redirect analytics, no
per-entity SEO scoring — NOT built (avoid "unnecessary full SEO platform"). Structured-data
(JSON-LD) out of scope (no baseline requirement).
```

## 16. Domain content boundaries (LOCKED ownership preserved)

```
Campaign (IMP-007): campaign title/description/goals/progress/lifecycle content = Campaign-owned
  columns on campaign tables. CMS does NOT store campaign business content, does NOT become a
  campaign repository, and does NOT pre-create "campaign placeholders". Generic campaign-adjacent
  pages (e.g. /how-to-give) are ordinary managed pages.
Partner (IMP-018): Partner Profile content belongs to Partner domain (MODULE-OWNERSHIP §7).
  CMS manages only org-wide content about partners (e.g. a partner-program explainer page).
  No partner-owned content rows, no partner scope on cms_* tables.
Fundraiser (IMP-013): same rule via MODULE-OWNERSHIP §5 (Fundraiser Profile is Fundraising domain).
Beneficiary, Distribution, Impact, Donation and financial domains retain canonical data and
  lifecycle/authority in their own modules; generic Article classification cannot absorb them.
Donor-facing notices: global CMS content under ORGANIZATION ownership — allowed (pages/articles).
Public projections rule (MODULE-OWNERSHIP §2): any future "latest campaigns block" style feature
  consumes a controlled projection PROVIDED by the business domain — the projection contract is
  created by that domain's stage (IMP-007+), never by CMS reaching into domain tables. In IMP-005
  no projection mechanism is built at all.
```

## 17. Localization

```
RESOLVED — Q31 (LOCKED, Level 1): CMS v1 uses a SINGLE-LOCALE content baseline.
- No translation tables, no locale-specific publication workflow, no localized-slug framework,
  no fallback engine in IMP-005 (restated in section 4/13).
- The app UI locale stack (en/id via Laravel localization) remains an interface concern and is
  NOT a content-translation requirement.
- ADDITIVE-READY (Q31) means stable non-language-specific IDs and explicit revision text-field
  ownership. No speculative locale columns, localized revisions, translation joins, per-locale
  pointers/indexes, fallback trees or language-switching logic; future design is not prescribed.
- Any move to multilingual content requires its own future Human Decision + stage/change control
  (Q31); nothing here pre-empts it, and nothing here implements it.
```

## 18. Services

```
Conventions: app/Services/Content/* namespace, small focused services, all mutations
service-transactional with in-transaction audit append (house pattern from IMP-002/003/004).
Service names below are NORMATIVE — the patched sections 8/13/14/19/20 use them and an implementer
must not invent parallel ones.

  PageService / ArticleService   create/update identity + active draft revision (guard:
                                 permissions via policies, PATH validation via PathService,
                                 sanitization hook, media attachment protocol section 19). These
                                 services DELEGATE EVERY revision-row write to RevisionService /
                                 PublicationService — they do not write cms_content_revisions
                                 themselves, and they never assign a revision pointer directly
                                 (section 11 write boundary; IMP005-REAUDIT-R1-02/-03). A draft save
                                 REQUIRES expected_edit_version (section 19 attachment protocol
                                 step 5).
  PublicationService             publish / unpublish / retire / archive / republish; the revision
                                 pointer-swap + claim transaction (section 26); scheduled-transition
                                 executor invoked by the Laravel Scheduler (Q32; console command
                                 content:run-scheduled-transitions, running as the cataloged
                                 content.scheduler System Principal — section 12 attribution);
                                 homepage assignment here (singleton row guard, section 13).
                                 It is the ONLY writer of revision LIFECYCLE columns and of the
                                 identity revision pointers, and its publish()/supersede()
                                 operations cannot write a payload column at all (section 11
                                 layer-2 whitelists).
  RevisionService                the ONLY writer of revision PAYLOAD columns, through exactly
                                 createDraft() / editDraft() (state=DRAFT only); revision
                                 reads/history listing; rollback-by-copy (creates new draft
                                 revision, never rewrites history; payload-frozen rows are
                                 read-only, section 11). NO generic update(), save(), bulk or
                                 batch mutation is exposed by this service — that absence IS the
                                 boundary (section 11 layer 1).
  PathService                    THE single path authority: per-segment normalization + reserved-
                                 registry check + claim/rename/release against `cms_paths`
                                 (section 14). Used by the services above; no other service composes,
                                 compares, or writes paths. Renamed from the former "SlugService":
                                 the design no longer manages a per-entity slug column, it manages
                                 the shared namespace, and the name must not imply otherwise.
                                 release() is the ONLY operation that un-reserves a path, and it is
                                 invoked by nothing except an explicit operator action — no
                                 lifecycle transition calls it (section 13 RESERVATION POLICY).
  MediaService                   upload intake (validation pipeline section 19), logical archive,
                                 metadata update, attachment (section 19 protocol), and reference
                                 QUERIES. Physical purge is NOT here — it belongs to
                                 MediaCleanupService, because purge is a System-Principal operation
                                 with different authority, evidence and failure semantics, and
                                 putting both on one service is how "archive" and "purge" get
                                 confused again.
                                 References are read from `cms_media_references` (section 13) and
                                 written only via the attachment protocol (section 19). The former
                                 wording — "reference = media ULID token present in published/draft
                                 body_html, token scan service-local" — is REMOVED: it is precisely
                                 the IMP005-SPEC-05 defect (a token scan cannot see
                                 og_image_asset_id or historical-revision usage). No cached mutable
                                 counter column, per the "no mutable balance" house principle.
                                 ARCHIVE NEVER PARSES HTML AND NEVER CHECKS REFERENCE-ALLOWANCE:
                                 references are evidence, not a gate (section 19).
  MediaTokenResolver             stored placeholder token -> validated asset -> approved public URL
                                 (section 20). Read-only; the ONLY producer of media URLs in output.
  ContentSanitizer               input body -> allow-listed body (section 20); pure, unit-tested
  HomepageContentResolver        the category-C homepage read contract (section 8): singleton
                                 designation -> designated page -> current published revision ->
                                 neutral PublishedContent | null. Read-only; registers no route and
                                 owns no view.
  ContentResolverService         public read path: normalized path -> ACTIVE `cms_paths` claim ->
                                 CURRENT content (published payload) or REDIRECT (301 to the owner's
                                 ACTIVE CURRENT claim) -> presentation-neutral payload for the
                                 web/theme contract. Read-only; never mutates; 404s without echoing
                                 hidden state.
  MediaCleanupService            case A (orphan filesystem files) and case B (unreferenced assets)
                                 reconciliation (section 19), invoked by console command
                                 content:cleanup-media as the cataloged content.media_cleanup System
                                 Principal; holds no authority beyond that principal's explicit grant.
Controllers (thin): Http/Controllers/Admin/Content/* (Inertia, fixed backoffice) and
Http/Controllers/Content/PublicContentController (catch-all resolver + media token resolution).
Route ownership: CMS registers the public catch-all resolver route ONLY, and registers it LAST;
it never registers, replaces, wraps or conditions `/` or any other application route (section 8).
Policies: ContentPagePolicy, ContentArticlePolicy, MediaPolicy — each delegates to
the canonical IMP-003 AuthorizationEvaluator (never bare role checks).
```

Avoiding God services: lifecycle/publishing decisions are not in identity services; slug rules not
duplicated per service; sanitizer is the single write-path gate; resolver is read-only and never
mutates.

## 19. Media & file security

```
Validation pipeline (all server-side; client checks are UX only — LOCKED "client is never the
security authority"):
  1 extension allowlist (v1): images jpg,jpeg,png,gif,webp; documents pdf. Everything else
    rejected — explicitly including: php/phtml/phar/exe/bat/sh/htm/html/xhtml/xhtml/shtml,
    svg (see policy below), swf, js, json archives, and double-extension forms (filename is
    regenerated so user names never reach disk — step 6).
  2 MIME sniffing via finfo on actual bytes (never the client-sent Content-Type);
    image types additionally verified with getimagesize() decoding successfully;
    pdf verified by %PDF header + size ceiling.
  3 extension<->mime consistency matrix (both must agree, e.g. png image/png only).
  4 size ceilings: images 4 MiB, pdf 10 MiB (config values, `config/media.php` — defaults chosen
    as ordinary-content bounds, tunable; not a legal/policy claim). Uploads rate-limited per
    authenticated principal (throttle middleware).
  5 dimension bounds for images (max 8000x8000, rejects decompression-bomb shapes).
  6 storage: generated filename ONLY = ULID + validated canonical extension; stored under the
    'public' disk path `content/{yyyy}/{mm}/{ulid}.{ext}`; Laravel Storage/local driver —
    shared-hosting compatible. public/storage serving is the existing framework pattern; the web
    server must never execute files from the storage path (deployment note: disable PHP engine on
    storage dirs in the Apache/LiteSpeed config — deployment-doc obligation; documented here since
    the docs dir for deployment is empty).
  7 SVG policy (SECURITY): REJECT SVG uploads in v1 (active-content vector: script/foreignObject;
    sanitizers exist but SVG is not a baseline requirement). Recorded so its absence is a decision,
    not an oversight.
  8 image sanitization/normalization: v1 stores originals after validation (no mutation); no
    EXIF GPS scrubbing library mandatory, but a config-gated re-encode (GD) path is specified as
    the EXIF-privacy mechanism — re-encode default OFF, flagged in acceptance tests.
  (metadata: strip/ignore client metadata; alt/title/caption are DB columns, never IPTC-parsed)
Alt text: nullable string column; required by validation on PUBLIC articles' og_image and any image
  embedded in PUBLISHED content (accessibility rule — ENGINEERING CHOICE; enforced at publish
  guard, not upload).
Replacement semantics: replacing an asset = upload new asset + swap token in draft + publish; old
  asset enters reference-check flow below. In-place byte overwrite FORBIDDEN (published history
  must render as it was published).
Duplicate handling: sha256 computed; if an ACTIVE asset with same hash exists, the response notes
  the duplicate (operator chooses); no silent dedupe.
Media ARCHIVE (LOGICAL — the operation an admin performs; section 27 distinguishes it from
  physical purge, and "delete" is not used for either):
  THE RULE (IMP005-REAUDIT-R1-04 — one rule, replacing the previous pair of contradictory ones):
  LOGICAL ARCHIVE IS NOT BLOCKED BY REFERENCES. Archiving an asset is a statement about the
  FUTURE of that asset ("do not attach this to new content"), not about the present of the
  content that already uses it. The former text here — "blocked while any ACTIVE reference exists
  from non-ARCHIVED content (draft, published, or superseded)" — contradicted test M2 (which
  asserted an asset referenced only by a SUPERSEDED revision IS logically archivable) and is
  REMOVED.
  Archive therefore:
    - always permitted for an operator holding content.archive, on an ACTIVE asset (re-archiving an
      ARCHIVED asset is an idempotent no-op, section 26 flow 4);
    - NEVER breaks rendering: existing references stay in place and continue to resolve, so content
      that already embeds the asset keeps working, including the CURRENT published version of it
      (section 27 item 3 — "archiving is not an immediate withdrawal of an asset from live pages,
      and pretending otherwise would be a silent content break");
    - DOES end attachability: no NEW reference may be created to an ARCHIVED asset from this moment
      (steps 2/3 of the attachment protocol and section 20's write-time token validation), so the
      asset's footprint can only shrink;
    - records what it found, because the count is the operator's feedback and the evidence that the
      reference model was consulted rather than skipped: `prior_references` = `HAS_ACTIVE` /
      `NONE` (section 12), taken from `cms_media_references` under the lock;
    - is a single indexed query on `cms_media_references` (section 13) — it is NOT a body-HTML
      scan, which was the prior design's defect: a body-token-only scan could not see
      `og_image_asset_id` and could not see historical revisions at all, so an asset that was
      demonstrably in use could be judged unused.
  On success: NON_CRITICAL audit `content.media.archived`, row -> ARCHIVED; the file stays on disk
  and reference rows are RETAINED (they are the reason it stays); physical removal happens only via
  the purge rules below.
  Why "allow archive with references" over "block archive with references": blocking is the more
  conservative-looking choice but it is the one that produces a worse system — an operator cannot
  mark an asset retired without first editing every piece of content that ever used it, which
  means either editing published content (creating revisions purely as cleanup churn, each one a
  public-visible change to history-adjacent state) or leaving genuinely dead assets presenting as
  live in the picker. Allowing archive preserves the operator's intent as data and delegates the
  actual safety question — may the BYTES go? — to purge, where it was always answered.

 What a reference status means (IMP005-REAUDIT-R1-04 — the semantics the prior text left
  ambiguous, including Codex's observation that SUPERSEDED-revision references stay ACTIVE):
    Reference EXISTENCE is the preservation claim; reference STATUS is a payload fact, nothing
    more:
      ACTIVE    the named revision's payload CURRENTLY refers to this asset at this field_path.
                This is true for the active draft, for the PUBLISHED revision, and for every
                SUPERSEDED revision — all of them still carry the reference in their (frozen)
                payload, so all of their rows stay ACTIVE. Rendering need follows the payload.
      RELEASED  the named revision's payload NO LONGER refers to the asset, because a later edit
                of THAT SAME revision replaced it (section 20 write-time rule 6). The row is kept.
    Consequences, stated as rules:
      - A reference is NEVER released merely because its revision was SUPERSEDED, because its
        owner was retired, or because its owner was archived. Superseding changes which revision is
        current; it does not change what the old revision's body said. Doing so would delete the
        only evidence that the historical payload needs its asset and would make M2 unpassable.
      - The set of states is therefore exactly {ACTIVE, RELEASED}: the reference row has no
        archive/purge lifecycle of its own. (The prior `cms_media_references.status` enumeration is
        corrected to match — section 13.)
      - A reference becomes RELEASED only when the owning revision's own payload stops carrying it.
        Since IMP-005 never destroys revision history (section 27 item 2), a reference belonging to
        a PUBLISHED or SUPERSEDED revision is ACTIVE forever, and IMP-005 v1 never releases one.
      - PURGE therefore keys on ACTIVE reference COUNT, and the historical case is included by
        construction rather than by a special case.
    This is the simpler model the remediation prefers: existence expresses preservation, status
    expresses payload, and no third notion is introduced.

Historical rendering guarantee vs deletion (section 27 reconciliation):
  CMS preserves published history (sections 11/27: revision rows are never deleted, and
  SUPERSEDED revisions remain viewable to admins behind content.view). An asset required to render
  preserved history therefore MUST NOT be physically purged while that history exists.
  - media with at least ONE ACTIVE row in `cms_media_references` is NEVER physically purged in
    IMP-005, regardless of the status of its owner content and regardless of whether the
    referencing revision is the current one, a PUBLISHED one, or a SUPERSEDED one. Because
    references of published/superseded revisions are never released (above), this covers every
    asset that ever rendered publicly. It may still be LOGICALLY ARCHIVED (hidden from the picker,
    unattachable to new content) with its bytes retained.
  - physical purge in v1 is therefore limited to assets with ZERO ACTIVE references: abandoned
    uploads that were never attached, and assets whose only references were RELEASED by later
    draft edits.
  - the two operations are named differently everywhere in this specification — "archive" (logical,
    DB state + admin operation) and "purge" (physical, filesystem deletion + DB state PURGED).
    The word "delete" is not used for either as a technical term.
  - A future capability to purge history-bearing media requires its own retention/Human decision
    (Q28-governed flow) and a change-control amendment; it is explicitly NOT built here.
  MEDIA STATE EFFECT TABLE (normative; the four questions every media rule reduces to —
    IMP005-REAUDIT-R1-04 asks for these to be defined, and they are defined here once):
      state     newly attachable?   renderable for existing   logical archive   purge eligible
                                    refs (public + admin)?    permitted?        (with the other
      ACTIVE    YES                 YES (it is live)          YES (idempotent   NO — status must
                                                              no-op)            be ARCHIVED first
      ARCHIVED  NO — rejected       YES — existing refs       —                 YES, but ONLY if
                                `media_asset_not_            still resolve     zero ACTIVE
                                attachable`                                     references
      PURGED    NO                  file gone; the row        —                 — (already done)
                                    remains as evidence,
                                    and no ACTIVE ref can
                                    point at it (purge
                                    would have refused)
    Deliberately NOT a state: PURGE_PENDING. Purge is a synchronous attempt whose failure leaves
    the row ARCHIVED with purge_attempts/last_purge_error as the retry evidence (section 19 case B
    and section 26 flow 4), so a fourth state would encode in a status column what two operational
    columns already encode better. v1 uses the three states above and no more.
```

### Media reference lifecycle (IMP005-SPEC-05/06)

```
The closed field vocabulary for `cms_media_references.field_path` (any new media-bearing CMS field
MUST be added here, and its omission is a review blocker):

  field_path            reference_kind   written by
  'body_html'           BODY_TOKEN       PageService/ArticleService draft write + PublicationService
  'og_image_asset_id'   SEO_IMAGE        PageService/ArticleService draft write + PublicationService

Reference rows are ACTIVE while the named revision's payload refers to the asset — which includes
every SUPERSEDED revision forever, because superseding changes which revision is current, not what
the old revision's body said. A row becomes RELEASED ONLY when a later edit of THAT SAME revision
replaces the payload position it described. Supersession, retirement and archiving of the owning
content NEVER release a reference row (IMP005-REAUDIT-R1-04; section 19 "What a reference status
means"), and there is no archive/release operation on a reference row in v1 — the operator-facing
operations act on ASSETS, and the only transition a reference row ever takes is the one its own
revision's payload edit causes. Release never deletes the row (section 27).
```

### Attachment protocol (write side; must not lose to a concurrent delete)

```
When content/revision references media, ALL of the following happen in ONE service-owned
transaction, in this order. The numbering below IS the tier order of the shared lock table that
follows — an earlier section of this document described this flow as locking only the asset, which
was the IMP005-REAUDIT-R1-04 gap: without the tier-3 identity/revision lock, a draft edit could
commit a payload and its reference rows out of step with a concurrent edit of the same draft.

  0. DETERMINE THE LOCK SET (IMP005-REAUDIT-R2-04 — this step did not exist, and its absence was a
     real safety hole):
        LOCKED_ASSETS = { asset ids named by the revision's EXISTING cms_media_references rows
                          (ACTIVE or RELEASED, any field_path) }
                        UNION
                        { asset ids named by the PROPOSED payload (every data-media token +
                          og_image_asset_id) }
        ordered by id ASCENDING. Both sets are read BEFORE the transaction's first lock and are
        therefore HINTS ONLY (section 26 COMMON RULE); the sets are recomputed and re-verified
        under the locks at step 4.
        WHY THE UNION AND NOT JUST THE PROPOSAL: an edit that REMOVES the last reference to an
        asset must still lock that asset and its reference row, because the row it is about to
        RELEASE belongs to it. Locking only the proposed set lets a concurrent archive/purge of
        the dropped asset interleave with this transaction's release of the old reference — the
        two operations then race over a row that neither of them locked, and the reference state
        can end up disagreeing with the payload. The removal case is precisely the case a
        proposal-only lock set loses, and it is the common one (replacing an image is a removal
        plus an addition).
        WHY THE UNION AND NOT JUST THE EXISTING SET: an added asset is not yet referenced, so an
        existing-only set would attach to an asset a concurrent purge had already selected.
        TIERS 1-2 MAY BE SKIPPED IF AND ONLY IF:
            existing reference set = EMPTY  AND  proposed reference set = EMPTY
        — i.e. the revision neither refers to any asset now nor will refer to one afterwards, and
        the union is provably empty. Any other combination MUST take tiers 1 and 2 over the full
        union, including the "removal to zero" case (existing non-empty, proposed empty), which is
        NOT a media-free edit and must not be treated as one. Empty UNION empty is the only skip;
        there is no size threshold, no "small edit" exemption, and no cache-based substitute for
        the union computation.
  1. LOCK the media asset rows in LOCKED_ASSETS — TIER 1 (`SELECT ... FOR UPDATE` by asset id,
     ids ASCENDING). The asset row is the serialization point for "can this asset be attached?";
  2. VERIFY attachability FOR THE PROPOSED SUBSET ONLY: status = ACTIVE. ARCHIVED/PURGED =>
     reject `media_asset_not_attachable` (this is what makes an in-flight delete safe: the writer
     cannot attach an asset whose delete already concluded it was unused, because that delete
     holds the asset lock until commit and has already moved the row to ARCHIVED).
     ASSETS PRESENT IN THE EXISTING SET BUT NOT IN THE PROPOSED SET (the ones being dropped) ARE
     NOT REJECTED for being non-ACTIVE — they are locked because their reference row must be
     reconciled, not because they are being attached, and dropping a reference to an already
     ARCHIVED or already PURGED asset is a legitimate and expected edit. Lock them, release their
     rows at step 7, do not validate attachability on them. Conflating the two would make it
     impossible to clean a draft that had come to reference a retired asset — a deadlock in the
     operator's favour, which is not a safety property.
  3. LOCK TIER 2 — the existing `cms_media_references` rows for those asset ids AND for the
     revision being written, ids ASCENDING. This is the set this transaction is about to
     reconcile; taking the tier in the shared order (rather than only reading it) is what stops a
     concurrent reconcile of the same revision from interleaving;
  4. LOCK TIER 3 — the content IDENTITY row (cms_pages / cms_articles, lockForUpdate), then the
     REVISION row (cms_content_revisions), ids ASCENDING. THEN, under those locks:
       - RE-VERIFY the candidate revision's identity is this identity (the composite ownership FKs
         of section 13 make a mismatch unwritable, but the service still checks so the operator
         gets `revision_owner_mismatch` rather than a driver exception — section 11), and
       - CHECK THE CONCURRENCY TOKEN (step 5), and
       - RE-VERIFY attachability of every asset against the now-locked rows, not the step-2 read.
  5. CHECK THE DRAFT CONCURRENCY TOKEN — optimistic compare-and-swap on
     `cms_content_revisions.edit_version`, implemented as LOCK-THEN-COMPARE-THEN-WRITE (the
     row is already locked at step 4, and section 11's bulk-write policy forbids the
     `WHERE edit_version = ?` statement form, so the comparison is made in the service against
     the locked value rather than in the UPDATE's predicate):
       the request carries `expected_edit_version`; with the revision row held by
       `SELECT ... FOR UPDATE`, compare the locked `edit_version` to it; if they differ, the
       draft moved underneath this editor: reject 409 `draft_edit_conflict`, roll back, and do
       NOT retry automatically — the loser's payload is discarded, never merged silently, and the
       editor is told to reload. If they match, write the payload and set
       `edit_version = locked value + 1` through the hydrated model instance (section 11 layer 1:
       a model-path write, which is what keeps the model guard on the path).
       The row lock makes the compare race-free for the duration of the transaction, so the
       guarantee is identical to a predicate-based CAS while keeping every revision write on the
       guarded path. The loser is deterministic: the transaction that commits the payload first
       owns the version the second one will read and be rejected by.
       (A pessimistic alternative — hold a row lock for the whole edit — was rejected: an edit
       session spans human think-time, and a held MySQL row lock across it is exactly the
       long-transaction pattern this specification prohibits elsewhere. Optimistic versioning costs
       one column and one comparison.)
       Consequence for the pair (payload, references): because the version bump and the reference
       reconcile happen in the SAME transaction, there is no interleaving in which a losing
       editor's reference rows survive alongside a winning payload. The reference rows can never
       desynchronize from the payload they describe, which is the specific failure
       "last-write-wins" was going to allow.
  6. WRITE/REPLACE the content reference (payload column and/or body token inside sanitized HTML);
  7. PERSIST the normalized `cms_media_references` rows for exactly the references present in the
     stored payload — releasing any that the new payload no longer contains and that belong to
     THIS revision, inserting the new ones (existence-unique, section 13). Reference rows belonging
     to any OTHER revision, including a SUPERSEDED one, are NEVER touched by a draft edit
     (section 19 reference-status semantics);
  8. COMMIT together. Payload, reference rows and the edit_version bump are atomic: no commit
     point exists at which stored content references an asset with no reference row, or a
     reference row points at content that no longer mentions it, or a payload is at version N
     while its references were reconciled for version N-1.

  A delete that started BEFORE this transaction reaches step 1 completes first and this attach is
  rejected at step 2. A delete that starts AFTER this commit re-checks references under the lock
  (below) and finds the new ACTIVE reference, so it is blocked. There is no interleaving that
  attaches a purged asset or archives an attached one.

  MEDIA-FREE EXPOSURE: a draft-edit request takes tier 3 and the edit_version check (steps 4-5)
  ALWAYS, whether or not media is involved — the concurrency control is about the revision, not
  about media, and a media-free save must not be able to clobber a media-bearing one.
  Tiers 1-2 are taken whenever LOCKED_ASSETS (the existing UNION proposed set, step 0) is
  non-empty, and skipped ONLY when that union is provably empty. In particular an edit whose
  PROPOSED payload has no media but whose EXISTING references do (the removal-to-zero case) is NOT
  media-free: it must lock the dropped assets to reconcile their rows safely (step 0).
```

### Shared lock order — ALL media writers and deleters (IMP005-SPEC-06)

```
ONE deterministic order, used identically by attachment, publication, reference mutation,
logical archive and physical purge. Every one of these operations takes locks in this sequence:

  1. cms_media_assets            (asset rows, ids ASCENDING)
  2. cms_media_references        (reference rows for those asset ids, ids ASCENDING; when the
                                  operation is bulk, the whole set is locked in one ascending pass)
  3. content identity + revision (cms_pages / cms_articles, then cms_content_revisions, each by id
                                  ASCENDING)
  4. cms_paths                   (claims touched, by id ASCENDING)
  5. cms_homepage_assignment     (the singleton row, last — always last so it can never be a
                                  deadlock contributor against the other orders)

RULES:
  - NEVER acquire a lock on an earlier tier after holding a later tier. An operation that needs
    an earlier tier after starting a later one must release and restart.
  - All multi-row locking is id-ASCENDING (deadlock-safe sequencing, same discipline IMP-004
    established for its transaction placement).
  - Content-side operations that touch media (publish, rename) still take tier 1-2 locks even
    though they usually do not modify the asset, because the reference recheck must be under the
    asset lock — a shared lock (`LOCK IN SHARE MODE`) is sufficient where the operation only
    verifies attachability, and the same ORDER is what prevents deadlock.
  - SQLite test parity: SQLite serializes whole-database writes, so it cannot demonstrate these
    interleavings. These are MySQL-8-marked tests (section 29) and SQLite results never count as
    proof of the locking semantics.

DELETION (logical archive) under the same order — IMP005-REAUDIT-R1-04 corrected version:
  lock asset (tier 1) -> lock its ACTIVE reference rows (tier 2) -> RECHECK the reference count
              from `cms_media_references` (never from HTML, never from the tier-1 read)
              -> record `prior_references` = HAS_ACTIVE | NONE from that recheck
              -> transition status to ARCHIVED + audit -> commit.
  Archive is NOT gated on the recheck result (section 19 "Media ARCHIVE" is the rule: references
  end attachability, they do not block archiving). The recheck is performed under the lock because
  its RESULT IS AUDIT EVIDENCE and because it must not race with a concurrent attach that would
  make the recorded count wrong — a stale `prior_references: NONE` on an asset that was in fact
  being attached at that moment is a false statement in the audit record.
  Locking tiers 1-2 only (no tier 3) is sufficient here and is safe: the archive mutates no
  content row, and every content-side operation that could add a reference takes tier 1 FIRST, so
  it either holds the asset lock (this archive waits) or has not reached it yet (this archive
  blocks it at step 2 of the attachment protocol). An operation may NOT skip tier 1 to "just
  check the references" at tier 2 — that reopens the check-then-act window.
  Two concurrent archives of the same asset serialize on the asset lock; the second sees
  status=ARCHIVED and is an idempotent no-op (no second audit event).
 PURGE (physical) under the same order: eligibility is keyed on ACTIVE REFERENCE COUNT, which is a
  fact about tiers 1-2 alone — purge therefore takes tiers 1 and 2 and rechecks under them, and
  takes NO tier-3 lock. That is deliberate and it is the reason the reference-status rule above
  matters: because a reference row of a PUBLISHED or SUPERSEDED revision is ACTIVE forever, "zero
  ACTIVE references" already implies "no preserved history refers to it", so purge never needs to
  inspect the owning revision's state to know that. Adding a tier-3 read here would reintroduce an
  ordering dependency between purge and content locks for no additional safety. Taking tier 1 first
  is what makes attach-vs-purge impossible to interleave in either direction (matrix row below).
```

### Media cleanup model — three distinct cases (IMP005-SPEC-06)

```
The prior rule ("archives rows whose OWNER CONTENT is ARCHIVED/absent AND no references remain")
referred to an `owner content` column that does not exist in any schema in this specification, and
it conflated three different situations. Eligibility is now defined ONLY in terms of actual schema
and reference data:

CASE A — ORPHAN FILESYSTEM FILE (file on disk, NO database row)
  Cause: upload crashed before row persistence; or the file was written and the insert transaction
  rolled back (section 26 upload flow writes bytes first by design — the bounded, documented
  residue this case exists to reclaim).
  Detection: reconciliation scan of the CMS media tree on the 'public' disk:
    - enumerate `content/{yyyy}/{mm}/*` for the configured window (default: current month plus the
      previous month — a bounded scan, not a full-tree walk, so it is cheap on shared hosting);
    - for each file, look up `cms_media_assets.stored_filename`. No row => candidate.
    - stored_filename is GENERATED (ULID + validated extension) and unique, so the filename is a
      reliable join key; anything not matching the generated-name grammar at all is reported, not
      deleted (an unexpected file in the media tree is an investigation signal, not garbage).
  Grace period: a file is only eligible once its filesystem mtime is older than
    config `cms.media.orphan_grace_hours` (default 24). ENGINEERING CHOICE, explicitly CONFIGURABLE
    and explicitly NOT a legal retention duration — it exists so a slow in-flight upload is never
    reclaimed out from under itself. No legal/retention claim is made or implied.
  Action: delete the file. No DB row exists, so no state transition or audit record is possible —
    an orphan file is not a CMS asset. The event is an APPLICATION LOG record
    (`media_orphan_file_removed`, with path + size + mtime), never a fabricated audit row.
  Retry: unlink failure is logged (`media_orphan_cleanup_failed`) and simply re-detected on the
    next run — eligibility is re-derived from filesystem+DB state every time, so retry needs no
    bookkeeping table and the operation is idempotent by construction.
  Error reporting: per-run summary logged (scanned / removed / failed / refused-bad-name); any
    `refused` count is surfaced distinctly because it can indicate tampering.

CASE B — UNREFERENCED MEDIA ASSET (DB row exists, ZERO ACTIVE references)
  Eligibility (ALL must hold, re-verified under lock at execution time):
    - status = ARCHIVED (an ACTIVE asset is never purged silently; archive is an operator act), AND
    - NO ACTIVE row in `cms_media_references` for this asset. This single condition IS the
      historical-rendering guarantee: a reference belonging to a PUBLISHED or SUPERSEDED revision
      is ACTIVE permanently (section 19 "What a reference status means"), so an asset with zero
      ACTIVE references has, by construction, no preserved payload that needs it. The former
      separate clause ("AND NO reference at all, ACTIVE or RELEASED, from a PUBLISHED or SUPERSEDED
      revision") is REMOVED — under the corrected status semantics it restates this condition
      rather than adding to it, and keeping it as a second rule implied the first was insufficient.
      The historical reference count is therefore INCLUDED in this check, as the remediation
      requires, rather than being a special case bolted onto it, AND
    - archived_at older than config `cms.media.purge_grace_days` (default 7; configurable
      operational bound, NOT a legal retention duration), AND
    - no authorized hold applies (a future Q28-style hold; if none exists the check passes).
  A RELEASED row does NOT block purge, and never did: it records that a payload position was
  replaced, not that content still needs the bytes.
  Flow (order is normative — section 26 "media purge" transaction):
    candidate selection (indexed query, bounded batch)
      -> lock asset row (tier 1)
      -> RECHECK references under the lock (tier 2) — never trust the candidate query's answer
      -> verify grace/eligibility again against the locked row
      -> PHYSICAL deletion of the file FIRST
      -> on success: DB state transition (status=PURGED, purged_at) + audit
         `content.media.purged` -> commit
      -> on filesystem failure: DO NOT transition the row; increment purge_attempts, record
         last_purge_error, log `media_purge_failed`, leave the row ARCHIVED so it is re-selected
         next run.
  Filesystem-failure safety: the file is removed BEFORE the DB evidence changes, and the DB row is
  the retry capability. Deleting/updating the row first would lose the only record that a file
  still needs removing, stranding bytes with no evidence trail. This ordering is a requirement,
  not a preference.
  Note on atomicity: the filesystem is not transactional, so the commit-after-unlink window is
  acknowledged: unlink succeeds, DB commit fails => row stays ARCHIVED with a missing file. The
  next run re-selects it, unlink reports "already gone", is treated as success, and the row
  transitions. This is why purge MUST tolerate a missing file as success and why purge_attempts
  exists (bounded, reportable).

CASE C — REFERENCED MEDIA ASSET (>= 1 ACTIVE reference row)
   Must not be PURGED — this is the whole of section 19's historical-rendering guarantee, and it is
   the operation that case B's recheck exists to protect. It MAY be logically ARCHIVED (section 19
   "Media ARCHIVE"): archiving neither breaks the existing references nor removes the bytes, and the
   previous text here ("must not be logically archivable while any non-ARCHIVED content references
   it") is REMOVED as the other half of the IMP005-REAUDIT-R1-04 contradiction. The rejection code
   `media_referenced` consequently belongs to PURGE eligibility, never to archive.
   Rendering continues normally. Reference rows and bytes are both retained indefinitely in v1.

Scheduled cleanup is a SEPARATE concern from the resolver: cleanup is the only CMS path that
removes bytes, and it runs under the SYSTEM PRINCIPAL authority rules below — never as an
unauthenticated job and never with a Human role impersonated.
```

### Cleanup actor / authority (reuses IMP-004/IMP-003; no new actor kind)

```
  - Reference seeding registers `content.media_cleanup` in system_principals AND its linked
    principals row using the SAME mechanism IMP-005 already uses for `content.scheduler`
    (section 12). NO new actor kind is invented: actor_principal_kind = 'system' already exists
    in the IMP-004 taxonomy, and `execution_context`/'pre_principal_system' are NOT used here —
    a canonical Principal exists for this job.
  - EXPLICIT SYSTEM OPERATION IDENTIFIER: `content.media_cleanup` (the catalog key) executed via
    console command `content:cleanup-media`. The operation identifier is recorded in audit
    metadata as `system_operation` and must be one of the closed set
    {content.scheduler, content.media_cleanup} — it is not a free-text field.
  - SCOPE, NOT UNRESTRICTED AUTHORITY: the principal holds an explicit, separately granted
    ORGANIZATION-scope `content.archive` permission (the destructive capability that already
    governs media archive/purge per section 23). Catalog membership is not authority; without the
    grant the job denies itself and reports. It holds NO content.publish, NO content.update, NO
    audit.read, and no other domain's permissions.
  - CURRENT-STATE CHECKS: the cleanup service authorizes through the canonical IMP-003 evaluator
    exactly like a Human request (`Authenticated` via the container-resolved System Principal AND
    permission AND scope AND no security restriction). Every eligibility condition of case B is
    re-verified inside the locked transaction; the candidate query is only a hint.
  - NO HUMAN ROLE IMPERSONATION: the audit actor on `content.media.purged` is the System Principal
    (`actor_principal_kind='system'`, `actor_principal_id = principals.id` of the cataloged
    cleanup principal — never a `system_principals` catalog id, never a Human's id). The Human who
    archived the asset earlier is recorded on THAT event, and is optionally surfaced as
    `previously_archived_by_principal_id` metadata on the purge event; it is never the actor.
  - Canonical audit actor rules of IMP-004 apply unchanged; IMP-004 is consumed, not forked.

Private files: OUT — all v1 CMS media is public marketing content; receipt/compliance/identity
  documents belong to Documents/Identity domains which already carry their own controlled-delivery
  obligations (SECURITY-ARCHITECTURE: no permanent public URLs for sensitive files — CMS simply
  holds no sensitive files in v1).
```

## 20. Rich text & content sanitization (stored-XSS wall) — IMP005-SPEC-09

```
Content model: body_html — semantic HTML subset produced by an admin editor (editor library itself
  OUT of scope/undecided — not named here; any editor must round-trip through the same server
  sanitizer). No page-builder block JSON in v1 (section 4).
```

### The ONE media placeholder contract (normative; no alternatives permitted)

```
  Managed media is embedded by PLACEHOLDER, never by URL. Exactly two placeholder forms exist:

  IMAGE / DOCUMENT EMBED (inline, in-flow):
    <img data-media="01J9ZK3V7Q4XW2N8M5R6T7B1C2" alt="Volunteers distributing iftar boxes">

  DOCUMENT LINK (pdf, i.e. non-inline media):
    <a data-media="01J9ZK3V7Q4XW2N8M5R6T7B1C2">Download the appeal booklet</a>

  - `data-media` is the ONLY token attribute. Its value MUST match, exactly and case-sensitively,
    the 26-character Crockford base32 ULID grammar: ^[0-9A-HJKMNP-TV-Z]{26}$
    A value that is not a syntactically valid ULID is REJECTED at write (422), not stripped —
    a stripped token silently loses an author's image, so an invalid token must fail loudly.
  - `src` is FORBIDDEN on every `img` in stored CMS content. An author-supplied `src` is removed by
    the sanitizer and the write is rejected with an explicit error; the ONLY `src` that ever
    appears in output is emitted by the renderer from a resolved asset. (The prior text's
    "img: src(data-media token)" was ambiguous about whether src was allowed and is superseded by
    this rule.)
  - `href` is FORBIDDEN on an `a` that carries `data-media` (the token IS the destination), and
    `href` remains subject to the scheme rules below on ordinary links.
  - No other attribute carries a media reference. No <figure data-media>, no class-encoded ids,
    no query-string hints — one contract, one parser.

  ROUND TRIP (the invariant the §29 tests prove end to end):
    editor input placeholder
      -> sanitizer (token PRESERVED verbatim; all other disallowed markup removed; unknown/
         malformed token -> 422, nothing stored)
      -> stored body_html (still the placeholder: no URL persisted inside content, so a storage
         path or slug change can never strand an embedded asset)
      -> MediaTokenResolver (token -> asset lookup -> approved public URL)
      -> safe rendered output (single emitted <img src="..." alt="..."> from server-controlled
         values only)
    The renderer — not the editor — is the only thing that ever produces a media URL.

  WRITE-TIME MEDIA TOKEN VALIDATION (every write path, inside the mutation transaction):
    1. syntax: token value matches the ULID grammar, else 422;
    2. existence: a cms_media_assets row with that ULID exists, else reject `media_asset_unknown`;
    3. attachable state: asset status = ACTIVE, else reject `media_asset_not_attachable`
       (ARCHIVED/PURGED assets cannot be attached to new content — section 19);
    4. ownership/reference creation: an ACTIVE cms_media_references row is written for
       (asset, this revision, field_path='body_html') in the SAME transaction (section 13);
    5. og_image_asset_id is validated identically (existence + ACTIVE + reference row with
       field_path='og_image_asset_id', reference_kind='SEO_IMAGE') — it is not a lesser path;
    6. removal: when an edit drops a token, the corresponding reference row for THAT revision is
       RELEASED in the same transaction; references belonging to other (published/superseded)
       revisions are untouched — which is precisely why a draft edit can never strand history.

  SANITIZER ALLOW-LIST (explicit, and the media attributes are named because they are the fix):
    tags:  p br h1-h4 ul ol li a img strong em blockquote table thead tbody tr th td
           figure figcaption code pre hr span
    attrs by tag:
      img: data-media (REQUIRED, ULID grammar), alt (required on published content, section 19),
           title (allowed — plain text), width, height (unsigned integers only)
           FORBIDDEN: src, srcset, longdesc, style, loading, onclick and every on*
      a:   href (scheme rules below) OR data-media — never both; title; target (_blank only, and
           the renderer always adds rel="noopener noreferrer"; stored content stores neither)
      figure/figcaption, list/table cells, span/code/pre: the global attribute set only
    global attrs: class (from a fixed admin-side list), id (slug-normalized per segment rules,
           optional, unique per document)
    href schemes: https, http, mailto, tel; RELATIVE paths starting '/' only if the normalized
      path is not reserved-prefixed AND is not '/'; REJECT javascript:, data:, file:, vbscript:,
      about:, and any scheme not listed. Relative URLs pointing at reserved routes are rejected.
    EVENT ATTRIBUTES (on*) stripped/rejected; <script> <style> <iframe> <object> <embed> <form>
    <input> <base> <link> <meta> <svg> <math> rejected outright (no iframe embeds in v1 —
    malicious-iframe vector eliminated at storage layer).
    Length caps (config): body 200 KB; final rendered output must never echo raw client HTML.
    SANITIZER/RESOLVER ROUND-TRIP GUARANTEE: sanitizing an already-sanitized stored body is a
    no-op (idempotent), so a placeholder can never be destroyed by a re-save, an editor reload, or
    a rollback-by-copy — proven by the §29 corpus.

Implementation mechanism: dedicated well-maintained sanitizer library (e.g. HTMLPurifier class —
  pure PHP, shared-host compatible, no infra) vs native DOMDocument allowlist walker — chosen at
  implementation with CHANGE-CONTROL dependency-justification evidence either way; the SECURITY
  review gate treats this as the highest-risk component and requires the §29 XSS test corpus to
  pass identically on both engines. Whichever is chosen, the token contract above is the
  specification — the library config must implement IT, not the other way round.
```

Defense in depth at render: templates escape non-sanitized fields (titles/captions are PLAIN TEXT —
sanitizer HTML is only ever stored in body_html); a Content-Security-Policy baseline is a
deployment obligation noted for the website stage (documented, not implemented here).

## 21. Neutral destinations; navigation deferred (Q30)

IMP-005 exposes Page/Article stable ULIDs, canonical slugs/paths and neutral destinations
`{content_id, content_kind, canonical_path, title}` for currently published content. It stores no
menu rows, labels/order/hierarchy/layout configuration or theme-specific rendering options.
No MenuService, NavigationBuilder, menu builder or composition engine. IMP-006 owns menu/navigation
presentation, theme selection, templates and Section/Component/Block presentation structures;
their database design and implementation are outside IMP-005.

Navigation visibility never grants or restricts backend authorization. Destinations are links,
not access tokens; protected resources always apply their own server-side authorization.

## 22. Authorization

```
LOCKED canonical formula (RBAC-ARCHITECTURE "Canonical Authorization Evaluation"), applied per
capability with CMS-appropriate terms:
  Authenticated (IMP-002, identity.active) AND Permission (content.*, section 23)
  AND Applicable Data Scope — ORGANIZATION (single-org; CMS content is organization-owned,
      scope_id NULL per ScopeType::requiresNullScopeId; no OWN/FUNDRAISER/PARTNER/CAMPAIGN scope
      exists for CMS resources in this baseline — see section 16 domain-scope conclusion)
  AND Ownership/Subject rule — CMS subject is the content row itself; no per-user ownership
  AND Business Authority — NONE required: no locked authority type maps to content work
      (BUSINESS-AUTHORITY-MODEL lists only financial/refund/withdrawal/distribution/zakat/
      partner_verifier/beneficiary_verifier; inventing a 'content_authority' type is FORBIDDEN)
  AND Valid Resource State — transition guards (section 10) e.g. publish only from DRAFT/RETIRED
  AND Required Approval State — inapplicable to CMS v1 per Q29; no reviewer workflow
  AND Authentication Assurance — STANDARD for all CMS capabilities (AUTHENTICATION-ASSURANCE's
      high-risk categories are financial/security/authority configuration; CMS publishing is not
      listed; do not require ELEVATED without a policy source)
  AND No Security Restriction (IMP-003 evaluator handles restriction uniformly).
Default DENY (LOCKED). No role-name checks in policies (LOCKED: "Super Admin != automatic";
role name must never appear in a CMS policy predicate). Super Admin receives content.* only
through the EXISTING seeded explicit grants (IMP-003/IMP-004 pattern: RbacRoleSeeder attaches the
registry's non-audit permissions as role_permissions — an explicit grant artifact, not an
evaluator bypass); this specification inherits that mechanism unchanged and adds no bypass.
Client-side UI gating is presentation only; every endpoint re-evaluates server-side (LOCKED).
```

## 23. Permissions (smallest coherent set)

Registration: `ContentPermissionRegistry` module-owned registry entries merged into the existing
registry/seeder mechanism (exactly how IMP-004 registered audit.read.*; per IMP-003, domain
stages register only their own permissions). Prefix `content` = owning module semantics
(Content Experience) and matches the locked reserved audit namespace — NOT `cms.*`.

```
content.view             read/manage-list pages, articles, revisions, history, media (read/edit
                         browse plane)                    — READ
content.create           create pages/articles (+drafts)  — EDIT
content.update           edit drafts, replace draft revision, slug/title changes on non-published,
                         media metadata (cannot alter a bound scheduled draft; a draft save must
                         carry `expected_edit_version` and a stale one is 409 — section 19) — EDIT
content.publish          publish / unpublish / re-publish; schedule configuration/change/
                         cancellation and guarded System execution; homepage assignment             — PUBLICATION (separate from edit)
content.archive          archive (terminal lifecycle of a Page/Article) + media LOGICAL archive
                         + governed path/redirect release — DESTRUCTIVE (separate from publish).
                         It is the capability the cleanup System Principal is explicitly granted for
                         PURGE eligibility checks (section 19); purge itself is a system operation,
                         not something a Human invokes per-asset.
content.media.upload     upload intake only               — EDIT-plane, kept separate (upload is
                         the security-sensitive write)
```

Rejected as unnecessary v1: per-entity permission explosion (page/article/media/menu triples),
seo.* (SEO fields ride the revision under content.update/publish), `cms.page.delete` (hard delete
does not exist), editorial-review, menu/theme and language/translation permissions.
Four planes kept distinct:
read / edit / publish / destructive.

## 24. Search, API, frontend contracts (boundaries)

```
SEARCH (IMP-026 owns the framework):
  IMP-005 declares the searchable-content contract ONLY:
    entities: page(title, body, meta_description), article(title, excerpt, body, article_type)
    predicate: CURRENTLY PUBLISHED revisions only
    no index building, no queue infrastructure, no search UI, no Elasticsearch (LOCKED hosting).
    A later stage may add FULLTEXT on revision columns behind a compatibility migration.
API (IMP-025 owns REST):
  NO /api/v1 content endpoints in IMP-005. Same-origin only:
    - admin backoffice: Inertia routes under /admin/content/* (fixed design system)
    - public: server-rendered catch-all + media token resolution (no JSON API contract created)
  The section 18 service layer IS the future REST surface's boundary — controllers stay thin so
  IMP-025 can bind to the same services without new business logic.
FRONTEND (public website): IMP-005 defines the content payload contract for rendering
  (PublishedContent: stable ULID/kind, title, sanitized body with unresolved media tokens, meta set,
  canonical path, Article article_type); single-locale, no translated payload or locale parameter
  and NOTHING about templates/themes (IMP-006). Mobile-first rules belong to the website/theme
  stages. No page-builder UI (unauthorized).
```

## 25. HUMAN DECISIONS - FINAL / LOCKED

Provenance: the Human supplied all five final decisions directly in the task
"IMP-005 CMS - HUMAN DECISION MATERIALIZATION PATCH". These are Human decisions, not engineering
choices. Q29-Q33 materialize them in the Level 1 Human Decision Register. Earlier options are
superseded and define no conditional implementation scope.

| Decision | Status | Final baseline | Register |
|---|---|---|---|
| HD-IMP005-01 | FINAL / LOCKED | No dedicated editorial approval workflow; author/edit separate from publish | Q29 |
| HD-IMP005-02 | FINAL / LOCKED | Menu/navigation presentation deferred to IMP-006 | Q30 |
| HD-IMP005-03 | FINAL / LOCKED | SINGLE-LOCALE CONTENT BASELINE; no speculative multilingual infrastructure | Q31 |
| HD-IMP005-04 | FINAL / LOCKED | Minimal scheduled publish/unpublish through Laravel Scheduler + Cron | Q32 |
| HD-IMP005-05 | FINAL / LOCKED | Article canonical; News classification; no duplicated mechanisms | Q33 |

```
Human Decisions Required: 0
OPEN HUMAN DECISIONS: 0
Specification: PATCHED (IMP005-SPEC-01..10 + IMP005-REAUDIT-R1-01..05) — PENDING CODEX RE-AUDIT
Implementation: NOT AUTHORIZED
```

Timestamp/state and classification representation are engineering details under these Human
choices. Implementation, merge, push and IMP-006 work require separate authorization.

## 26. Concurrency & transaction boundaries

```
Every CMS mutation runs in ONE service-owned DB::transaction (MUTATION_ATOMIC append inside it for
CRITICAL events; NON_CRITICAL appends inside too but non-propagating on failure per IMP-004).
Transaction Ownership Invariant (IMP-004, LOCKED): services whose capabilities can emit
DENIAL_DURABLE events must own the outermost transaction — all CMS write services follow the same
entry check DB::transactionLevel() === 0 (house contract consistency, mirrors RbacMutationGuard;
a violation raises TransactionOwnershipViolationException, never a business error type).
Named transactional flows (IMP005-SPEC-01..06 reconciliation; the former wording — "slug move +
slug_history write", "homepage assign: lock both rows and flip flags", "delete-media" — described
columns and tables this schema no longer has and is replaced whole):

  COMMON RULE: every flow below is ONE service-owned transaction, and every flow locks in the
  single shared order of section 19 (1 media assets, id ASC -> 2 media references, id ASC ->
  3 content identity then revision, id ASC -> 4 cms_paths claims, id ASC -> 5 the homepage
  singleton row). Any flow that needs a tier it already passed must release and restart; no flow
  may take an earlier lock after a later one. Reads performed BEFORE the first lock (e.g.
  "which assets does this revision reference?") are HINTS ONLY and every condition is re-verified
  under the lock.

  1. CONTENT PUBLISH (manual; the same transaction shape serves re-publish and replacement):
       pre-read (UNLOCKED, hint only): the candidate revision and the UNION of the asset ids its
         existing cms_media_references rows name and the asset ids its proposed payload names
         (section 19 step 0) — the publication branch is likewise selected from rows read here
         and RE-SELECTED under the lock at tier 4
       -> lock tier 1: those media assets, ids ASCENDING
       -> lock tier 2: their reference rows, ids ASCENDING
       -> lock tier 3: the content IDENTITY row (lockForUpdate), then the candidate revision and
          the CURRENTLY published revision if one exists (ids ascending)
       -> validate authority (canonical evaluator, section 22) AND resource state (section 10)
          AND, under the held locks, that every referenced asset is still status=ACTIVE
          (attachability re-check, section 19) AND that the candidate revision is still the
          identity's live draft (or a valid RETIRED re-publish target)
       -> transition publication state: candidate revision DRAFT|RETIRED-target -> PUBLISHED
          (sets published_at FOR THE FIRST TIME for that revision — a revision's published_at is
          never rewritten afterwards, including when an already-published revision is re-exposed
          after its owner was retired; see section 11 "Publication timestamps"), previous PUBLISHED
          revision -> SUPERSEDED (sets superseded_at), identity published_revision_id pointer moves;
          latest_draft_revision_id cleared
       -> lock tier 4 / update path state BY SELECTED BRANCH (section 14 "Claim / rename / release
          mechanics" is normative for all three; IMP005-REAUDIT-R2-02). The branch is chosen AFTER
          the identity and its ACTIVE CURRENT claim are locked, from the locked rows:
            A FIRST PUBLICATION (no CURRENT claim for this owner) -> INSERT one ACTIVE CURRENT row
            B SAME-PATH REPLACEMENT / RETIRED RE-PUBLICATION (existing CURRENT claim at the target
              path) -> INSERT NOTHING; retain the SAME ROW and UPDATE its revision_id to the newly
              published revision (the CURRENT-claim provenance rule, section 14 branch B step 6)
            C RENAME (existing CURRENT claim at a different path) -> UPDATE old CURRENT -> REDIRECT
              FIRST (frees the UNIQUE(active_current_owner) slot; old path stays reserved), THEN
              INSERT the new ACTIVE CURRENT row. Inserting before converting is IMPOSSIBLE — it
              collides with the still-CURRENT old claim on UNIQUE(active_current_owner), and MySQL 8
              has no deferred unique constraints to excuse it.
          A duplicate-key failure in A or C rolls the WHOLE flow back, so in branch C the conversion
          is undone with it and the old path is never left orphaned as a REDIRECT. Branch B has no
          insert and therefore no key race. See section 14 "FAILURE SAFETY".
       -> update reference state if applicable: revision media references written/released for
          THIS revision only (section 19 attachment protocol)
       -> tier 5 (homepage singleton) is NOT taken by publish; assignment is its own operation
       -> write audit according to the event's classification (section 12)
       -> COMMIT (all-or-nothing; a path-collision duplicate-key aborts the whole flow)
     Failure at any step rolls back state, pointer, claim, references and audit together.
     PUBLISH WITH NO MEDIA: tiers 1-2 are simply not taken when the union set is EMPTY — that is,
     when the candidate revision has no existing reference rows AND its payload names no asset.
     A publication that REMOVES a reference (existing set non-empty, proposed empty) is not a
     no-media publication and takes tiers 1-2 over the existing assets, exactly as section 19
     step 0 requires. The relative order of the tiers a flow does take is unchanged, which is all
     the shared order requires.
     Consequence accepted by design: a publish holds its assets' locks briefly, so a concurrent
     archive of the same asset waits rather than racing. Media sets in v1 are small; this is not
     a throughput concern, and correctness does not depend on it.

  2. PATH CHANGE (rename of live published content, or a governed release):
       WHICH PATH OPERATION APPLIES IS A BRANCH CHOICE, NOT ALWAYS AN INSERT
       (IMP005-REAUDIT-R2-02; section 14 branches A/B/C are normative). This flow 2 is branch C.
       Branch A (first publication) is flow 1 minus the conversion; branch B (same-path
       replacement, and the re-exposure of RETIRED content whose claim was never released) is
       flow 1 with NO cms_paths INSERT at all — the existing ACTIVE CURRENT row is retained and
       its revision_id is UPDATED to the newly published revision. A same-path publication must
       never attempt a second CURRENT insert for the path its own owner already holds.
       A rename is NOT a standalone operation — it is the path portion of flow 1 (a rename of live
       content only happens by publishing a revision whose slug_snapshot differs), so it inherits
       flow 1's locks, guards and audit exactly. Ordered so no constraint is violated at any
       intermediate step (section 14 RENAME is the normative statement of this order and of why
       the reverse order cannot work):
         -> lock the content identity (tier 3)
         -> lock the owner's ACTIVE CURRENT claim (tier 4)
         -> normalize + validate the target (bounds, reserved registry; the availability pre-check
            is a UX convenience, never the reservation — UNIQUE is)
         -> CONVERT the old claim CURRENT -> REDIRECT (status stays ACTIVE: the old path remains
            reserved; active_current_owner becomes NULL on that row, freeing the owner slot)
         -> INSERT the new ACTIVE CURRENT claim
         -> move the identity pointer + supersede the previous revision (flow 1's own steps)
         -> audit exactly ONE content.<kind>.published carrying previous_path + path +
            redirect_created
         -> COMMIT
       If the INSERT fails on UNIQUE(active_path) the transaction rolls back, which restores the
       old claim as CURRENT automatically (step 4's UPDATE is inside the same transaction). There
       is no compensating repair, no retry, and no externally visible moment in which the old path
       is a redirect to nothing. The client receives 409 path_conflict.
       A governed RELEASE is a separate, explicitly authorized mutation
       (PathService::release(), content.archive) setting status=RELEASED and retaining the row; it
       is audited as content.path.released. It is NEVER a side effect of retire or archive
       (section 13 RESERVATION POLICY). Two path changes to the same owner serialize on the
       identity row lock, then on the owner's claim rows in id-ascending order.

  3. MEDIA ATTACH / DRAFT EDIT WITH MEDIA: exactly the section 19 attachment protocol —
     tier 1 lock assets -> verify ACTIVE/attachable -> tier 2 lock the revision's existing
     reference rows -> tier 3 lock identity THEN revision, compare `expected_edit_version`
     (optimistic compare-and-swap; a stale version is 409 `draft_edit_conflict` and rolls back) ->
     write payload + reconcile `cms_media_references` + bump `edit_version` -> COMMIT. Payload,
     reference rows and the version token have no commit point at which they disagree, and a
     losing editor cannot leave its reference rows behind a winning payload. A media-free draft
     edit takes tier 3 and the version check only.
     THE DRAFT-EDIT CASE IS NOT A SECOND FLOW. Editing a draft is this flow whether or not the
     edit changes media, because the concurrency token and the reference reconciliation are the
     same two concerns.
  4. MEDIA LOGICAL ARCHIVE (operator "retire this asset"): lock asset (tier 1) -> lock its ACTIVE
     reference rows (tier 2) -> RECHECK references from cms_media_references (never HTML) ->
     RECORD `prior_references` from that recheck -> transition status to ARCHIVED -> audit ->
     COMMIT. References do NOT block this operation (section 19); they block PURGE. Filesystem
     untouched (bytes are reclaimed only by the purge path below).
     MEDIA PHYSICAL PURGE (cleanup job) is NOT a single-transaction flow: it is lock asset ->
     recheck under lock -> verify eligibility + grace -> unlink file -> on success transition
     status=PURGED + audit + commit; on filesystem failure DO NOT transition, record
     purge_attempts/last_purge_error and leave the row ARCHIVED for retry (section 19 case B).
     The DB row is the retry capability, so it must never be updated or removed before the file is.

  5. SCHEDULED PUBLISH: THE SAME transaction as flow 1, with the identical lock order, identical
     guards, and identical path/reference/audit steps. The ONLY differences: the actor is the
     cataloged content.scheduler System Principal (actor_principal_kind='system',
     actor_principal_id=principals.id — never the catalog row id, section 12), the event carries
     the schedule source fields (schedule_version, scheduled_at, scheduled_revision_id,
     scheduled_by_principal_id, system_operation = content.scheduler — the five keys the section 12
     contract makes conditional on actor=system, emitted ALL FIVE or NONE); exactly ONE canonical
     event per execution, chosen by the section 12 expired-window table (a real transition emits
     published/unpublished, a consumed no-transition window emits schedule_expired with its
     `outcome`, a stale re-run emits nothing); plus the additional pre-commit guards of section 10
     (re-read schedule_version; source Human still eligible; target still owned and not SUPERSEDED;
     state/version guards make a duplicate execution a no-op that consumes nothing).

  6. MEDIA UPLOAD INTAKE (the one flow deliberately NOT fully transactional):
       validate (section 19 pipeline, all server-side) -> compute sha256
       -> WRITE BYTES to the generated `content/{yyyy}/{mm}/{ulid}.{ext}` path
       -> insert the cms_media_assets row (status=ACTIVE) + audit `content.media.uploaded` -> COMMIT
       Bytes are written BEFORE the row because the insert is the thing that must be atomic with
       audit, while the filesystem is not transactional. The known consequence — a failed or
       crashed insert leaves a file with no row — is bounded, documented, and reclaimed by cleanup
       CASE A (section 19). The reverse order (row first, bytes second) would strand a row whose
       file never existed, which is worse: a row is authoritative evidence and a file is not.
       No reference row is written at upload time (nothing references the asset yet).
  7. HOMEPAGE ASSIGNMENT / REPLACEMENT / CLEAR:
       lock the singleton row (tier 5; it always exists, so the lock target always exists)
       -> re-read the current designee AFTER acquiring
       -> verify expected_page_id (or null) matches, else 409 homepage_assignment_conflict
       -> verify the new designee is a real Page identity in a state that permits designation
          (ARCHIVED may not be designated; unpublished MAY be, but then resolves to null —
          section 8: a designation grants no visibility)
       -> write page_id / assigned_by_principal_id / assigned_at -> audit content.homepage.assigned
       -> COMMIT. There is no empty-slot window in any interleaving: the row exists throughout and
       the swap is a single UPDATE of a single row, which is exactly why Pattern B was chosen over
       the generated-discriminator alternative (section 13).

```

Concurrency matrix (normative; each row is a §29 test):

| competing operations | lock / constraint that decides | loser behavior | retry? |
|---|---|---|---|
| Human publish vs Human publish (same identity) | identity row lockForUpdate (tier 3); UNDER that lock, re-verify the candidate revision is still the identity's live draft (or a valid RETIRED re-publish target) — the same pessimistic re-read flow 1 (section 26) already performs, not a client-supplied optimistic token | second writer, after acquiring the lock, finds the candidate no longer valid (pointer/state moved) -> 409 stale_publication | NO automatic retry; operator re-selects |
| Human publish vs scheduler publish | SAME identity row lock — both use flow 1/5, so they serialize on it. Whichever commits first wins; a manual publish atomically CANCELLED the pending intent (section 10), and a scheduler that then re-reads a bumped schedule_version no-ops | scheduler loses silently as a NO-OP (consumes nothing, emits no duplicate event); a Human loses with 409 | NO for the scheduler no-op (nothing to retry); NO for the Human (reports) |
| scheduler duplicate execution (overlapping/re-run cron) | schedule_version re-read + consumed timestamps + state guard inside the identity lock | second execution sees deadline consumed / state advanced -> NO-OP, no second audit event | N/A (idempotent by construction) |
| homepage assign vs homepage assign (no homepage yet) | the SINGLETON ROW `cms_homepage_assignment` id=1, lockForUpdate — it always exists from reference seeding, so there is never an empty slot to race for; CHECK (id=1) makes two rows impossible | the blocked transaction re-reads after acquiring, finds the slot occupied -> 409 homepage_assignment_conflict reporting the current designee | NO automatic retry (a silent retry would be a destructive surprise); explicit expected_page_id is the deliberate-replacement path |
| Page path vs Article path (same normalized string) | `cms_paths` UNIQUE(active_path) — one index spanning both kinds | the later commit receives the duplicate-key error, its whole transaction rolls back -> 409 path_conflict | NO automatic retry; the client surfaces the suggested alternative |
| current content path vs redirect/history path | same UNIQUE(active_path) — a REDIRECT claim holds the slot exactly as a CURRENT claim does, so shadowing is impossible by construction | 409 path_conflict; reuse requires an explicit governed RELEASE first (section 14) | NO |
| media attach vs media LOGICAL ARCHIVE (ASYMMETRIC — IMP005-REAUDIT-R2-04) | asset row lock (tier 1) is the serialization point | ARCHIVE FIRST -> the later attach is REJECTED `media_asset_not_attachable` (attach verifies status=ACTIVE under the lock). ATTACH FIRST -> the archive still SUCCEEDS (references never block archive) and records `prior_references` = HAS_ACTIVE. So exactly one order lets both operations through; the claim that both succeed in either order was wrong. The archive NEVER fails because of a reference and the attach NEVER succeeds against an archived asset | NO for the rejected attach (durable state, not transient); N/A for the archive |
| draft edit REMOVES the last reference to asset A vs archive/purge of A | both take tier 1 on A (the edit's lock set is existing UNION proposed, section 19 step 0), then tier 2 on the reference rows, in id-ascending order | deterministic by lock acquisition: edit-first -> the reference row is RELEASED under lock, then archive succeeds with `prior_references` = NONE and purge becomes eligible on its own later run (never in this transaction); archive/purge-first -> the edit still completes (dropping a reference to a non-ACTIVE asset is allowed, section 19 step 2), and the purge's recheck had found the still-ACTIVE row so it REFUSED. Final reference state always matches the committed payload; no purge happens while the reference row is logically present inside a live transaction | NO |
| media attach vs media PHYSICAL PURGE | same tier-1 asset lock; attach verifies status=ACTIVE, purge verifies zero ACTIVE references UNDER that same lock | if the purge won: attach rejected `media_asset_not_attachable` (status PURGED). If the attach won: purge's under-lock recheck (case B) finds the new ACTIVE reference and REFUSES — the asset stays ARCHIVED, no unlink happens, no purge event is written. **A newly attached asset can never be purged by the run that raced it** | NO — neither outcome is transient; both are reported |
| media archive/purge vs media archive/purge (same asset) | same asset row lock, ids ascending | second sees status already ARCHIVED/PURGED -> idempotent NO-OP, no duplicate audit event | N/A |
| PATH RENAME vs a concurrent claim of the NEW path (IMP005-REAUDIT-R1-01) | `UNIQUE(active_path)` at rename step 5 — the pre-check at step 3 is NOT the decider | the rename's INSERT fails; the whole transaction rolls back, which UNDOES step 4's CURRENT→REDIRECT conversion, so the old path is restored as CURRENT with no compensating code. Loser gets 409 path_conflict; the namespace is exactly as it was | NO automatic retry, and NO manual repair (rollback already did it) |
| PATH RENAME vs PATH RENAME (same owner) | identity row lock (tier 3, rename step 1), then the owner's ACTIVE CURRENT claim (tier 4) | the second re-reads the owner's CURRENT claim after acquiring, finds the path already moved, and its expected-path precondition fails -> 409 stale_publication. It does NOT convert a claim it no longer owns, and UNIQUE(active_current_owner) makes a two-CURRENT outcome unrepresentable even if the check were omitted | NO |
| PATH RENAME vs release of the OLD path (same owner) | tier 3 identity lock serializes both; the release then targets a claim whose purpose has moved to REDIRECT | deterministic: rename wins the lock -> release sees purpose=REDIRECT and proceeds as a redirect release; release wins -> rename's step 2 finds no ACTIVE CURRENT claim to convert and fails 409 stale_publication. Either order leaves one consistent namespace | NO |
| Page pointer -> another Page's revision (IMP005-REAUDIT-R1-02) | composite FK `(cms_pages.id, cms_pages.published_revision_id) -> cms_content_revisions(page_id, id)` | the write ERRORS at the storage layer (errno 1452), the service's own pre-write check has already rejected it with `revision_owner_mismatch` (422), and the transaction rolls back including its audit append | NO — not a transient condition |
| Article pointer -> a Page's revision, and Page pointer -> an Article's revision | the twin composite FKs; an Article-owned revision has page_id NULL and a Page-owned revision has article_id NULL, so neither can satisfy the other kind's parent key | rejected by the engine (and by the service check first). Unrepresentable state, not an error to be handled | NO |
| scheduled publish bound to a mismatched revision | `scheduled_revision_id` composite FK at CONFIGURATION time (a bad binding cannot be saved), plus the executor's under-lock re-validation (section 10: target must still belong to the identity and not be SUPERSEDED) | configuration attempt 422 `revision_owner_mismatch`; if the binding was valid when made but the revision was superseded since, the executor NO-OPS and consumes nothing rather than publishing another identity's content | NO for the 422; N/A for the no-op (idempotent) |
| cms_paths claim -> revision owned by a different identity | composite FK `(page_id, revision_id)` / `(article_id, revision_id)` on cms_paths -> cms_content_revisions | the INSERT ERRORS; the publication transaction rolls back (claim + pointer + lifecycle + audit together), so no orphan claim survives | NO |
| media reference -> revision owned by a different identity | composite FK `(owner_page_id, owner_revision_id)` / `(owner_article_id, owner_revision_id)` on cms_media_references | the INSERT ERRORS inside the attach transaction; payload, references and version bump all roll back together, leaving no reference pointing at content that does not describe it | NO |
| TWO CONCURRENT DRAFT EDITS, same revision (IMP005-REAUDIT-R1-04) | optimistic compare-and-swap on `cms_content_revisions.edit_version` (`WHERE id = ? AND edit_version = ?`), taken under the tier-3 revision lock | deterministic loser: whichever UPDATE reaches the row second affects 0 rows -> 409 `draft_edit_conflict`, full rollback, loser's payload AND its reference reconcile discarded, editor told to reload. The winner's payload and its reference rows and its version bump are one atomic unit — **no last-write-wins and no payload/reference desynchronization is possible** | NO automatic retry and NO automatic merge (a silent merge of two editors is worse than a conflict) |
| concurrent draft edit vs publication of the same revision | tier 3 identity + revision locks; publish advances `state` and clears `latest_draft_revision_id` | the later draft edit's edit_version check fails AND its under-lock state re-read finds state != DRAFT -> 409 `draft_edit_conflict` (never a payload write to a PUBLISHED row) | NO |
| revision replacement vs revision replacement (two drafts published concurrently) | identity lock then revision locks, ids ascending; plus active-draft unique (`active_draft_page_id`) | only one candidate can be the identity's published revision at commit; the loser's expected-revision check fails -> 409 stale_publication; a second simultaneous DRAFT is impossible at the DB layer | NO |

Cross-cutting: deadlocks are prevented by the single shared lock order above, not by detection or
by retry-on-error. Two transactions that touch overlapping media sets take the same tiers in the
same ascending order, so the classic inverse-order deadlock pattern cannot arise. Where an
operation touches no media, it simply does not take tiers 1-2 — which is safe because the order
is a constraint on the RELATIVE sequence of the tiers it does take.

Concurrency conflicts summary: no CMS conflict is resolved by last-writer-wins on content or path
state; every competing write either serializes behind a lock and re-verifies, or is rejected with a
typed 409 and reported. The only silent losers are the idempotent NO-OPs (scheduler re-execution,
duplicate archive) where a second effect would itself be the bug.

## 27. Deletion / archive semantics

```
Five different operations were previously described with overlapping words ("delete", "deactivate",
"archive"). They are separated here, and the vocabulary below is NORMATIVE for the whole
specification, the code, and the admin UI labels:

  term          technical meaning                        who triggers it        reversible?
  WITHDRAW      PUBLISHED -> RETIRED (unpublish)         content.publish        yes (re-publish)
  ARCHIVE       content identity -> ARCHIVED             content.archive        NO (terminal)
                (terminal lifecycle state; data stays)
  LOGICAL       media status ACTIVE -> ARCHIVED          content.archive        NO
  ARCHIVE       (row + bytes + references all retained)
  PURGE         media file bytes removed from            cleanup job only,      NO
                filesystem; status -> PURGED, row kept    (System Principal)
  RELEASE       cms_paths claim row ACTIVE -> RELEASED,   explicit governed      N/A (the row
                (no longer reserves the slot)             release ONLY           survives either
                cms_media_references row ACTIVE ->        (paths); auto on      way)
                RELEASED (payload no longer refers)       draft rewrites
                                                          (references)

  The bare word "delete" is NOT a technical term in IMP-005 and must not appear as a method,
  route, permission, status or event name for any CMS row: no managed content row, revision, media
  row, path claim or reference row is ever DELETEd in v1. A reviewer finding a `delete()` call on
  any of these is finding a BLOCKER, not a naming nit.

1. PAGE / ARTICLE ARCHIVE
   Sets identity status=ARCHIVED. NO hard delete exists in v1 (LOCKED-adjacent: "Preserve financial
   history" is financial, but applied to content by the same no-destruction-of-history house
   principle + the immutability of published revisions). The identity row, its revisions, its
   reference rows and its path rows all REMAIN. Effects at commit:
      - its path claims are UNAFFECTED IN STATUS: the ACTIVE CURRENT claim and any ACTIVE REDIRECT
        claims REMAIN ACTIVE AND REMAIN RESERVED (IMP005-REAUDIT-R1-01). The former rule — "its
        ACTIVE CURRENT path claim RELEASES, and its ACTIVE REDIRECT claims RELEASE too" — is
        REMOVED: it made a content lifecycle transition an implicit namespace decision, and it
        contradicted sections 13/14. The cost of keeping them reserved is a namespace that
        accumulates unresolvable-but-unusable paths; the cost of releasing them is that a retired
        organization URL can be silently re-pointed at unrelated content with no human act in the
        record. The first is chosen. ARCHIVE RELEASES NOTHING. The archive event records the
        resulting path facts (path, redirect_claim_count) instead — section 12;
      - it stops resolving publicly (404, per the section 14 resolution table — because the owner is
        not PUBLISHED, not because the claims disappeared) and is excluded from default admin
        listings;
      - if it is the homepage designee, the designation is CLEARED in the same transaction (a
        dangling designation to archived content would make HomepageContentResolver return null
        forever with nobody able to see why; clearing is explicit, audited, and the audit event
        records `homepage_designation` = `CLEARED_BY_ARCHIVE` in metadata);
      - ARCHIVED is terminal: restoring means creating new content, not flipping the status back
        (section 10). No "restore" event, no restore chain, no invented un-archive path.
      - Its media references are UNAFFECTED: they stay ACTIVE and continue to block purge
        (section 19 case C). Archiving content does not make its media disposable; only the media's
        own reference state decides that.

2. REVISION PRESERVATION
   Revision rows are NEVER deleted, by any code path, in any v1 flow (section 11). Superseding
   flips lifecycle metadata only; the payload of every revision ever published stays readable to
   admins behind content.view. "Append-only" means payload append-only, not row-immutable — the
   distinction section 11 exists to make.

3. MEDIA LOGICAL ARCHIVE
   The operator-facing action. Sets status=ARCHIVED under the section 19 lock order after
   re-checking `cms_media_references`. Bytes stay on disk; reference rows stay. The file becomes
   unattachable to new content and hidden from the picker, but content that already references it
   still renders it — archiving is not an immediate withdrawal of an asset from live pages, and
   pretending otherwise would be a silent content break. (Withdrawing a visible asset from live
   content requires editing the content that references it.)

4. MEDIA PHYSICAL PURGE
   NOT an operator action. Performed only by the cleanup job, only on CASE B assets (section 19),
   only after the configured operational grace, and NEVER on media referenced by any PUBLISHED or
   SUPERSEDED revision — which is what keeps the historical-rendering guarantee true in section 19.
   The row is retained forever as evidence (status=PURGED, purged_at set); only the bytes go.
   Filesystem failure leaves the row ARCHIVED and retryable (section 26 flow 4).

5. PATH REDIRECT / HISTORY RETENTION
   `cms_paths` rows are NEVER deleted. A rename converts a claim's PURPOSE (CURRENT -> REDIRECT);
   the row stays ACTIVE and therefore KEEPS RESERVING the path — the former wording here ("the old
   path is freed because the unique index ignores inactive rows") is REMOVED: it contradicted the
   section 14 reuse policy and would have permitted redirect shadowing. An old path stops reserving
   its slot ONLY via an explicit governed RELEASE (status=RELEASED, row retained). That release is
   never automatic: it does not happen on RETIRE, it does not happen on ARCHIVE, and it does not
   happen on a timer (section 13 RESERVATION POLICY). The former "which happens automatically when
   the owner is ARCHIVED" clause is REMOVED as part of IMP005-REAUDIT-R1-01. RELEASED rows persist
   permanently and are what makes "has this path ever been used?" answerable.
   `cms_media_references` rows follow the same retention rule (ACTIVE -> RELEASED, never deleted);
   note the reference STATUS vocabulary is deliberately narrower than the path one — a reference
   row can only be ACTIVE or RELEASED, never ARCHIVED/PURGED, because "archived"/"purged" describe
   the ASSET and "released" describes this row's own payload relationship (section 19).

Legal retention durations: NONE asserted anywhere in this specification. Q17 governs retention; a
future retention stage may extend purge to history-bearing media under a Q28-style governed
evidence flow, which is explicitly NOT built in v1 (section 19). The `cms.media.orphan_grace_hours`
and `cms.media.purge_grace_days` values in section 19 are operational cleanup bounds — configurable
ENGINEERING CHOICE, not legal or compliance durations, and deliberately not Human Decisions.
```

## 28. Security threat model (CMS-specific; mitigations normative for implementation)

```
stored XSS                 server allowlist sanitizer on every write + render-escape of plain-text
                           fields + no iframe/embed/object/svg at storage (section 20)
malicious upload           byte-sniffed MIME+extension matrix, executables/SVG rejected, generated
                           ULID filenames, engine-disabled storage serving (section 19)
route takeover (managed slug shadowing system route)
                           reserved-prefix registry (write-time + boot-time assertion) + catch-all
                           registered last + feature tests on every protected prefix (section 14)
broken access control      canonical AND-chain via policies; default DENY; no role shortcuts;
                           every mutation re-checked server-side (section 22)
mass assignment            explicit $fillable + FormRequest whitelists; principal/pointer columns
                           (published_revision_id, latest_draft_revision_id, scheduled_revision_id,
                           status, schedule_version, and the whole
                           cms_homepage_assignment row — page_id/assigned_by/assigned_at included)
                           are NEVER mass-assignable —
                           set only by services. ON THE REVISION TABLE THE RULE IS STRONGER THAN
                           NON-FILLABLE: every lifecycle column (state, published_at, superseded_at,
                           state_changed_by_principal_id, edit_version) and every ownership column
                           (page_id, article_id, revision_no, author_principal_id) is non-fillable,
                           because `$fillable` governs mass assignment ONLY and has no effect on a
                           direct `$model->attr = x` write or on a builder UPDATE — so mass-
                           assignment hygiene is listed here as one control, not as the immutability
                           story (section 11 layers 1-5 own that, and AC-27 forbids conflating them).
                           Request input can never carry any of them regardless of route shape.
unsafe HTML                section 20 allowlist; fail-closed parse
SSRF via remote media/import
                           no remote-URL import feature exists in v1 (capability deleted at design
                           time — strongest mitigation)
open redirect              redirect targets are system-written internal paths only (sections 14,
                           21); no CMS menu configuration
privilege escalation through publish
                           publish is its own permission + canonical audit + resource-state guards;
                           publishing grants no other domain authority (section 7 structural rule)
IDOR                      ULID public ids; policy checks before any BIGINT-keyed lookup; enumeration
                           tests (section 29 security set)
CSRF                      existing session/Inertia CSRF posture (IMP-001/002 middleware) on all
                           admin mutations; no GET state-change endpoints
content spoofing (unauthorized public change)
                           server authorization + revision binding + section 12 canonical audit attribution
                           (actor principal id always)
unsafe SVG                 rejected upload (section 19)
excessive file upload      size ceilings + throttle per principal + cleanup scheduler bounds
revision/history disclosure
                           drafts & history admin-only behind content.view; public resolver
                           exposes ONLY current-published content (no status echoes on 404)
 audit-metadata leakage     IMP-004 metadata allow-list + hard-prohibited keys apply; content bodies
                           never enter audit metadata (section 12)
 namespace squatting / retired-URL hijack
                           section 13 RESERVATION POLICY: no lifecycle transition and no timer
                           releases a path, so withdrawing or archiving content cannot hand its
                           published URL to unrelated content; only an explicit audited release can
                           (IMP005-REAUDIT-R1-01)
 cross-identity revision wiring (a pointer or reference made to name another identity's, or the
 other kind's, revision — the primitive that would let a Page serve an Article's body)
                           composite foreign keys make the state unrepresentable at the storage
                           layer, so no code path, importer or raw SQL statement can produce it
                           (section 13 "Ownership-exact FKs"; tests O1-O10)
 revision payload tampering via a path the model guard cannot see (query-builder / DB::table bulk
                           UPDATE, which does NOT fire model events — see section 11 "Bulk updates")
                           prohibited at the call-site level and verified by a source-scanning test
                           (R4a/R4e), plus the write boundary (no generic update surface exists),
                           plus per-operation field whitelists, plus byte-identity assertions
                           (R4b-R4d). NOT claimed to be blocked by model events, because that claim
                           is false; DB grants remain the outer boundary (section 11 layer 9).
 silent editorial overwrite (two admins, one draft)
                           edit_version compare-and-swap -> 409 draft_edit_conflict; the loser's
                           payload AND its media reference changes are discarded together, so a lost
                           edit is visible rather than half-applied (section 19; M12/M13)
```

## 29. Test strategy (specified BEFORE implementation; required, not optional)

```
UNIT
  PathService normalization (unicode/IDN-ish input, PER-SEGMENT collapse, length, rejects); reserved
  registry membership incl. boot-assert invariants; lifecycle transition table (every legal +
  illegal edge); sanitizer corpus (section 20 vectors); permission registry shape (codes match
  naming convention, module tags); media validation matrix (extension x mime x fake-file
  permutations); revision payload/lifecycle per-operation FIELD WHITELISTS (section 11 layer 2 —
  the unit test asserts the whitelist SETS, e.g. that publish()'s writable set contains no payload
  column and editDraft()'s contains no lifecycle column; it does NOT test the model guard against
  a bulk update, because the model guard is not reachable from there — see R4); the
  path_change/redirect_created derivation (section 12 key definitions);
  ULID generation.
FEATURE
  page CRUD happy/denied; publish/unpublish/republish/archive; scheduled publish executor (Q32);
  rollback-by-copy; PATH RENAME leaves a RESERVED redirect and 301-resolves to the owner's new
  current path; 404 for retired AND for archived (both still RESERVED, neither resolving);
  Article/NEWS classification flows; upload intake (valid png/jpg/pdf + rejected .php, .svg,
  mismatched mime, oversized, dimensions); media logical archive SUCCEEDS while ACTIVE references
  exist and the archived asset still renders through its existing references while a NEW attach to
  it is rejected; purge refused while any ACTIVE reference exists; admin listing
  pagination/filters; media token resolution in public render payload; draft-edit optimistic
  version conflict surfaced as 409 rather than silently resolved; homepage designation +
  HomepageContentResolver returns the published payload / null when undesignated or unpublished /
  null when the designee is not PUBLISHED.

CODEX COUNTEREXAMPLE TESTS — PASS 2 ADDITIONS (IMP005-REAUDIT-R1-01..05). These are listed first
because they are the tests that CLOSE THE CURRENT GATE; the P-0 series below them remains required
and is not superseded:

  PATH RENAME EXECUTABILITY (IMP005-REAUDIT-R1-01)
    P8  MYSQL 8 REQUIRED — published content at CURRENT /old renamed to /new, end to end, under
        the REAL constraints: assert the transaction COMMITS (this is the test that would have
        failed against the old insert-first ordering, which collided with
        UNIQUE(active_current_owner)); afterwards exactly one ACTIVE CURRENT claim exists for the
        owner and it is /new; the /old row is purpose=REDIRECT, status=ACTIVE; the identity
        pointer names the new revision; the old revision is SUPERSEDED; exactly one
        content.<kind>.published event exists carrying previous_path=/old, path=/new,
        path_change=RENAMED, redirect_created=1.
    P9  MYSQL 8 REQUIRED — rename COLLISION: another identity already holds /new. The rename's
        step-5 INSERT fails; assert the WHOLE transaction rolled back — the /old claim is still
        purpose=CURRENT with its ORIGINAL revision_id, the identity pointer is unchanged, no
        revision is SUPERSEDED, no audit event exists, and the response is 409 path_conflict.
        This is the "no intermediate broken state" proof: specifically assert there is NO
        committed or committed-then-repaired state in which /old is a redirect to nothing.
    P9b rename collision with NO compensating repair: assert the implementation's rollback path
        issues no UPDATE restoring the old claim (the test observes statement count via a query
        log; the restore is InnoDB's undo, not an application write).
    P10 RETIRED content keeps its reservations: withdraw a published page, then attempt to claim
        its path with a different identity -> 409 path_conflict; the retired page's own path still
        does NOT resolve (404); re-publishing the retired page returns it to the same path.
    P11 ARCHIVED content keeps its reservations: archive a page with a rename history; assert its
        CURRENT and REDIRECT claims are ALL still status=ACTIVE, that each path still refuses a
        third-party claim, and that each resolves 404. Assert NO content.path.released event was
        emitted by the archive (this is the direct test that automatic release is gone).
    P12 governed release is the only un-reservation: explicitly release the /old REDIRECT, assert
        content.path.released (reason=GOVERNED_RELEASE) is emitted once, and only THEN is /old
        claimable.
  REVISION OWNERSHIP INTEGRITY (IMP005-REAUDIT-R1-02) — the four mixing classes plus the two
  derived relations; each is asserted at BOTH layers (the service's classified rejection AND the
  database's refusal when the service is bypassed):
    O1  Page A + revision owned by Page B -> rejected (published_revision_id).
    O2  Article A + revision owned by Article B -> rejected.
    O3  Page + revision owned by an Article -> rejected (cross-type).
    O4  Article + revision owned by a Page -> rejected (cross-type).
    O5  the same four classes for latest_draft_revision_id.
    O6  the same four classes for scheduled_revision_id, asserted at SCHEDULE CONFIGURATION time
        (a mismatched binding cannot be stored, so the scheduler can never be pointed at another
        identity's revision) — and the executor's no-op path when a once-valid binding became
        SUPERSEDED.
    O7  cms_paths row whose revision_id belongs to a different identity, or to the other identity
        KIND -> rejected at insert.
    O8  cms_media_references row whose owner_revision_id is not owned by its owner_page_id /
        owner_article_id -> rejected at insert.
    O9  DB-BYPASS VARIANT, MYSQL 8 REQUIRED for O1-O8: attempt the same writes by raw SQL /
        DB::table(), bypassing every service and model guard, and assert the ENGINE still refuses
        them (foreign-key failure). This is the test that distinguishes "structurally
        unrepresentable" from "validated by the code that was supposed to validate it" — a guard
        that only works when called cannot pass O9.
    O10 the positive control: a correctly-owned pointer writes successfully on BOTH engines (so
        O1-O9 cannot be passing merely because the fixture writes are all invalid).
  REVISION WRITE BOUNDARY (IMP005-REAUDIT-R1-03) — replaces the false R4 expectation:
    R4a STRUCTURAL: no prohibited builder/Query-Builder mutation of cms_content_revisions exists in
        application code. A source-scanning test over app/ asserts zero call sites matching
        Revision::query()->update / ::where(...)->update / DB::table('cms_content_revisions')
        ->update / ->delete / DB::statement('UPDATE cms_content_revisions ...') outside the four
        whitelisted RevisionService/PublicationService methods, and zero mass-assignment surface for
        lifecycle columns. (This is the ONLY test that can catch a bulk update, because the model
        guard is not on that path.)
    R4b SUPPORTED-API IMPOSSIBILITY: no sequence of supported service calls changes a published
        revision's payload — exercise every supported operation (publish, replacement publish,
        scheduled publish, supersede, archive, homepage assign, path release, media archive, draft
        edit of a DIFFERENT revision, rollback-by-copy) and byte-compare the published payload after
        each.
    R4c LIFECYCLE-ONLY TRANSITIONS: publish()/supersede() write ONLY their whitelisted lifecycle
        columns (assert the update statement's column set, not just the outcome) and an attempt to
        smuggle a payload column through them is rejected.
    R4d REPLACEMENT CREATES/USES A NEW REVISION: a replacement publication never mutates the
        outgoing revision's payload; the new content is a distinct row, and the outgoing row's
        payload is byte-identical before and after (asserts the "rollback means copy-forward" rule
        rather than an edit).
    R4e NEGATIVE CONTROL ON THE TEST ITSELF: a deliberately planted prohibited call site in a test
        fixture IS reported by R4a's scanner. Without this, R4a could pass by never matching
        anything.
  MEDIA ARCHIVE / PURGE / CONCURRENCY (IMP005-REAUDIT-R1-04)
    M9   HISTORICAL-ONLY LOGICAL ARCHIVE IS ALLOWED: an asset referenced only by a SUPERSEDED
         revision is logically archived successfully, its reference row stays ACTIVE, and
         `prior_references` = HAS_ACTIVE on the event (asserts the unified archive rule and the
         evidence key together).
    M10  HISTORICAL-ONLY PURGE IS REJECTED: the same asset cannot be purged; the reference row
         belonging to the SUPERSEDED revision is what blocks it. Pair with M9 to prove the
         distinction is archive-vs-purge, not a contradiction.
    M11  ARCHIVED ASSET STILL RENDERS, CANNOT BE RE-ATTACHED: after archive, the public render of
         the referencing published content still resolves the asset (200 on its URL), while a new
         draft embed of the same ULID is rejected media_asset_not_attachable, and an og_image
         swap to it is rejected identically.
    M12  TWO CONCURRENT DRAFT EDITS, MYSQL 8 REQUIRED: both editors load edit_version 5; A saves
         -> 6; B saves with expected 5 -> 409 draft_edit_conflict and B's payload AND B's
         reference-row changes are absent; a re-read shows exactly A's content with A's references.
         Assert determinism (which editor loses is decided by commit order, not by luck) and that
         NO last-write-wins path exists — including the case where B's edit changed DIFFERENT
         fields than A's, which a merging implementation would silently absorb.
    M13  PAYLOAD/REFERENCE/VERSION ATOMICITY: a draft edit that fails at the reference-insert step
         (forced FK failure) rolls the payload write and the version bump back too — assert
         edit_version is UNCHANGED, since a bumped version with rolled-back references would
         falsely invalidate the other editor's still-correct draft.
    M14  ARCHIVE VS DRAFT EDIT LOCK ORDER (MySQL 8): a concurrent archive and draft edit touching
         the same asset+revision always complete without deadlock, in the section 19 tier order
         (assert no deadlock/lock-wait error, and that the archive's recorded prior_references
         matches the reference state at its own commit point).
    M15  ATTACH VS PURGE (MySQL 8): a purge that has selected a candidate asset, followed by a
         committed attach, must NOT delete the file — the under-lock recheck (case B) decides, and
         `purge_attempts` is not incremented for a correctly refused purge.
    M16  REFERENCE STATUS IS NOT A LIFECYCLE: supersede the revision that references an asset and
         assert its reference row is STILL ACTIVE (never released by supersession), while a later
         edit of a DRAFT that drops a token releases that draft's row only.

CODEX COUNTEREXAMPLE TESTS (each one closes a specific re-audit finding; a missing test here means
the finding is NOT remediated, regardless of the prose above):

  HOMEPAGE (IMP005-SPEC-02)
    H1 two concurrent INITIAL assignments (no homepage exists) cannot produce two homepages —
       the singleton row is the race target, so this asserts one commit + one 409, and asserts the
       post-condition "count(rows)=1 AND count(non-null page_id)<=1" rather than trusting the
       service's own answer.
    H1b assignment is impossible to double-apply through the service AND through a direct row
       write attempt (the CHECK (id=1) rejects a second row).
    H2 replace with correct expected_page_id succeeds; replace with stale expected_page_id -> 409.
    H3 designating an ARCHIVED page is rejected; designating an UNPUBLISHED page is allowed but the
       resolver returns null (a designation grants no visibility).
    H4 archiving the current designee clears the designation in the same transaction and records
       homepage_designation = CLEARED_BY_ARCHIVE on the archive event (and asserts that NO
       standalone content.homepage.assigned event was emitted for that clear).
  PATHS (IMP005-SPEC-03)
    P1 Page vs Article claiming the same normalized path -> exactly one wins, the other 409
       (proves the SHARED namespace; the old three-index design passed this on each table
       individually and still allowed the collision).
    P2 Article path vs an existing REDIRECT path -> rejected.
    P3 Page path vs an existing REDIRECT path -> rejected.
    P4 two concurrent CROSS-ENTITY claims from two connections -> one commit, one duplicate-key
       409, and no partial state (pointer unchanged, no SUPERSEDED flip, no orphan claim row).
    P5 historical path reuse: after a governed RELEASE the same path is claimable; BEFORE release
       it is not, even by its own previous owner.
    P6 rename never creates a moment where the old path is free (assert the old path still
       301-resolves and still rejects a third-party claim, in the same committed state).
    P7 UNIQUE(active_current_owner): an identity cannot hold two ACTIVE CURRENT claims.
  REVISIONS (IMP005-SPEC-04)
    R1 publication replacement: previous PUBLISHED -> SUPERSEDED with superseded_at set, pointer
       moved, and BOTH rows' payloads byte-identical to before the transition.
    R2 supersede preserves historical content: the superseded revision still reads back its exact
       title/body/SEO payload, and is renderable to an admin behind content.view.
    R3 immutable payload: any attempt to change body_html/title/SEO on a PUBLISHED or SUPERSEDED
       revision is rejected (service guard + model guard, tested separately) — valid for writes
       that pass through a hydrated model, which is where both guards live.
    R4 — REPLACED (IMP005-REAUDIT-R1-03). The former R4 asserted that a direct bypass attempt via
       `Revision::query()->update([...])` would be "rejected by the model/guard layer where it
       passes through Eloquent". THAT EXPECTATION IS FALSE AND IS REMOVED: an Eloquent builder
       update issues one UPDATE without hydrating models, so model events do not fire and the
       guard is never reached. A test written to that expectation would either have been written to
       pass trivially or would fail on first run and get the guard weakened. The executable
       replacement is the R4a-R4e set above, which tests the boundary that actually exists
       (call-site prohibition + supported-API impossibility + whitelisted lifecycle transitions).
    R5 scheduled replacement uses the same publication transaction and produces the same
       SUPERSEDED transition as a manual one (asserts equivalence, not just success).
    R6 lifecycle transition through the service is allowed while the same field change attempted
       as a payload edit is not (proves the payload/lifecycle split is real, not cosmetic).
    R7 revision rows are never deleted by any flow, including archive (assert count unchanged).
  MEDIA (IMP005-SPEC-05 / IMP005-SPEC-06)
    M1 an asset referenced ONLY by og_image_asset_id (never embedded in any body) CANNOT be purged
       while that reference is ACTIVE, and IS correctly counted by every reference query — closes
       the exact blind spot the body-token scan had. (Its archive outcome is governed by the
       corrected rule in M9/M11: logical archive is PERMITTED here; the historic "cannot be
       archived while the referencing content is live" expectation was removed with
       IMP005-REAUDIT-R1-04 and must not be re-added.)
    M2 an asset referenced only by a SUPERSEDED (historical) revision CANNOT be physically purged,
       while being logically archivable — asserts the historical-rendering guarantee. (M9/M10 are
       the sharper versions of the same distinction.)
    M3  attach vs archive, BOTH DIRECTIONS, WITH THE CORRECT ASYMMETRY
        (IMP005-REAUDIT-R2-04 — the previous claim that "both operations succeed in either order"
        is FALSE and is replaced; archive and attach are not symmetric):
          (a) ATTACH FIRST, then ARCHIVE: attach succeeds; the subsequent archive SUCCEEDS
              (references never block archive), the existing reference row remains valid, the
              asset still renders through it, and the event records prior_references = HAS_ACTIVE.
          (b) ARCHIVE FIRST, then ATTACH: archive succeeds; the subsequent attach is REJECTED
              `media_asset_not_attachable` (an ARCHIVED asset is never newly attachable).
        So the outcome depends on the ORDER, and only one of the two orders is fully
        unobstructed. Assert all three facts in each direction, not just the terminal status.
        attach vs PURGE remains its own pair of cases (M4/M15) with a genuinely different rule:
        purge-then-attach rejects the attach; attach-then-purge is refused by the under-lock
        recheck.
    M4 zero-reference recheck under lock: a candidate query that returns an asset, followed by a
       concurrent attach, must NOT purge (the recheck, not the query, decides).
    M5 orphan FILE with no DB row is reclaimed after the configured grace, and a file whose name
       does not match the generated-name grammar is REPORTED, not deleted.
    M6 filesystem purge failure: unlink fails -> purge leaves status=ARCHIVED, increments purge_attempts, records
       last_purge_error, and the row is re-selected next run; DB evidence is never updated first.
    M6b a purge whose unlink succeeded but whose commit failed self-heals on the next run
       (missing file treated as success).
    M7 reference rows are written in the SAME transaction as the referencing payload (assert no
       committed state where a body/OG reference exists with no row, or a row with no reference).
    M8 a draft edit that drops a token releases THAT revision's reference only; a superseded
       revision's reference to the same asset remains ACTIVE/retained.
  AUDIT (IMP005-SPEC-07)
    A1 every one of the 20 registered events is emitted by its mapped mutation (table-driven
       assertion over the section 12 inventory — including media.updated, media.archived,
       media.purged, homepage.assigned, path.released, the four added/renamed by that remediation,
       and the three expired-window outcomes routed per the expired-window semantics).
    A2 media metadata update emits content.media.updated and NOT content.media.uploaded.
    A3 homepage assignment emits exactly one content.homepage.assigned with the correct operation;
       a clear caused by an archive emits NO standalone homepage event.
    A4 a rename emits exactly ONE published event carrying previous_path + path (no companion
       path.claimed/path.renamed event exists to double-emit).
    A4b a FIRST publication omits previous_revision_id AND previous_path, and sets
       path_change=UNCHANGED / redirect_created=0 — the keys are ABSENT, and the test asserts
       array_key_missing, not null-equality.
    A4c a same-path replacement publishes WITH previous_revision_id and WITHOUT previous_path.
    A5 every NON_CRITICAL content event forced-failure -> business COMMITS and an
       `audit_write_failed` report is observable (asserts REPORTED, not merely "did not throw").
  AUDIT METADATA CONTRACT (IMP005-REAUDIT-R1-05)
    A6  REQUIRED-KEY PRESENCE: table-driven over all 20 events — for each event, drive its
        canonical mutation and assert EVERY key in that event's REQUIRED column is present. This
        is the test that makes the contract table load-bearing rather than documentary.
    A6b CONDITIONAL PAIRING: for the published event family, assert the conditional keys behave:
        first publish omits previous_revision_id; rename emits previous_path AND
        path_change=RENAMED AND redirect_created=1 TOGETHER; same-path replacement emits none of
        the two but does emit previous_revision_id. A partial combination (e.g. redirect_created=1
        with no previous_path) is a failure.
    A7  NO-NULL RULE: across every event emitted by the entire test suite, assert that no
        metadata value is null. Implemented as a generic post-condition on captured events, not
        per-event, so a future key cannot quietly reintroduce nulls. Pair with A7b: for each
        conditional key, exercise the NOT-applicable case and assert OMISSION (the key absent)
        rather than a null or an empty-string sentinel.
    A8  MANUAL PUBLISH EMITS NO SCHEDULE KEYS and scheduled publish emits ALL FOUR together:
        a Human publish must not be forced to fabricate schedule metadata (assert all four keys
        absent), and a scheduled one must carry schedule_version + scheduled_at +
        scheduled_revision_id + scheduled_by_principal_id — asserting the all-or-none pairing,
        not merely presence.
    A9  SCHEDULE-CANCEL OMISSION: cancelling a schedule that had only publish_at omits
        unpublish_at rather than nulling it; a full cancel omits both; operation=CANCELLED omits
        the three bound-target keys.
    A10 EVENT-COUNT SEMANTICS ARE DETERMINISTIC: assert the section 12 "exactly one" rule as
        SCOPED — count ONLY content.* events per committed mutation (one), and separately assert
        that a DENIED CMS attempt produces ZERO content.* events while the IMP-004
        security.authorization.denied record still exists. This is the test that proves the rule
        means "one canonical business-mutation event" and not "one audit row of any kind".
    A10b an expired-window NO-TRANSITION execution emits exactly ONE schedule_expired with the
        correct `outcome` and emits NO published/unpublished event; an executed transition emits
        exactly ONE transition event and NO schedule_expired.
    A11 TYPE CONFORMANCE: for every event, assert each metadata value's PHP type matches the
        declared contract type ('int'|'string'|'array'), INCLUDING that no boolean is ever passed
        (booleans fail IMP-004's is_int check and would raise at runtime) and that no
        DateTime/Carbon instance is ever passed for publish_at/scheduled_at (objects are rejected).
        A helper asserting `is_int($v) || is_string($v) || (is_array($v) && array_is_list($v))`
        over every captured value is the cheap catch-all for all 20 events.
    A11b FLAT-LIST RULE: `fields_changed`, `path_claim_retained` are flat lists; assert an
        associative or nested array is rejected (registry-level IMP-004 behavior), so a future
        "richer" metadata shape cannot be smuggled in under an existing key.
    A12 CAPPED/COUNTED BOUNDS: path/redirect facts are carried as single values or int counts;
        assert every emitted payload serializes inside IMP-004's METADATA_MAX_BYTES bound, so no
        content event can fail at write time merely because a page accumulated redirects.
    A13 PROHIBITED-CONTENT CHECK: no event's metadata contains body_html, raw HTML, a filesystem
        path, or a financial identifier — asserted by scanning all captured values for an HTML
        tag pattern and for the reserved financial column names, on top of IMP-004's own
        hard-prohibited KEY list (which is about keys, not values, and therefore cannot catch a
        body pasted under an allowed key such as a `string` field).
  SLUGS / PATHS (IMP005-SPEC-08)
    S1 nested normalization: "/news/My First Story" -> "/news/my-first-story" (and explicitly NOT
       "news-my-first-story").
    S2 "/forgot-password" is reserved and unclaimable (the route that the previous handwritten list
       missed).
    S2b a NEWLY registered dummy application route becomes reserved without any CMS-side change
       (proves the registry is DERIVED, not handwritten).
    S3 total normalized path length boundary: 191 chars accepted, 192 rejected at the SERVICE with
       422 before any DB error; per-segment 100 boundary; depth 3 boundary.
    S4 traversal-shaped input (".", "..", "%2e", backslash, NUL) is REJECTED, never sanitized into
       a claimable path.
  SANITIZER / TOKEN (IMP005-SPEC-09)
    T1 a valid data-media placeholder survives sanitize -> store -> reload -> resolve and emerges
       as exactly one server-emitted <img src=...>.
    T2 an invalid/nonexistent token is REJECTED at write with 422 and nothing is stored.
    T3 an author-supplied `src` on img is rejected (not silently stripped-and-saved).
    T4 sanitizer idempotence: re-sanitizing stored body_html is a no-op (placeholder cannot be
       destroyed by a re-save or a rollback-by-copy).

AUTHORIZATION (mandatory matrix per DEFINITION-OF-DONE "Mandatory RBAC Tests", applied to each
  content.* capability): authorized->ALLOW; unauthenticated->DENY; wrong permission->DENY;
  correct permission wrong scope->DENY; wrong resource state->DENY; missing identity.active->DENY;
  unrelated Partner/Fundraiser context ->DENY; SUPER ADMIN WITHOUT explicit content.publish
  grant attempt publish -> DENY (proves no evaluator bypass).
  Cleanup authority: the content.media_cleanup System Principal WITHOUT its explicit
  content.archive grant purges NOTHING and reports; it can never publish or edit content (it holds
  no such permission) and never impersonates the archiving Human.
SECURITY
  stored-XSS payloads (event handlers, javascript: hrefs, svg/script/iframe bodies, polyglot
  files); protected-route collision create (path 'admin', 'api/v1', 'campaign/x', 'login',
  'forgot-password'...) -> 422; attempt direct storage URL of archived media; IDOR (ULID
  enumeration, foreign draft ids); publish without permission via forged request payloads
  (pointer/status/homepage fields stripped); CSRF absence -> 419; CRITICAL security denial
  forced-failure -> DENY remains DENY + error reported; every NON_CRITICAL content event
  forced-failure -> business SURVIVES + error reported (A5).
AUDIT INTEGRATION
  as per section 12 inventory + the A-series above: every authorized mutation -> exact registry
  event (event_type+version+criticality+metadata allow-list compliance) via canonical sink; every
  unauthorized attempt -> NO business mutation (+ security.authorization.denied where the IMP-004
  denial path applies); criticality behaves per registry (no caller override); unregistered CMS
  event attempt -> hard error.
HUMAN DECISION COVERAGE
  editor with create/update but no publish cannot publish/schedule/reschedule/cancel/assign
  homepage; publisher within scope succeeds without reviewer queue/approval. Wrong scope is denied.
  Future publish stays invisible until successful due execution; due unpublish withdraws;
  overlapping/repeated cron is idempotent; next cron catches missed work; both-deadlines-missed
  never briefly exposes expired content. Archived/superseded/canceled/stale/manual-conflicting
  intent cannot force a transition. Bound draft edits are rejected; inactive System/source Human
  and missing grants block execution. Check UTC conversion, offset input, canonical System FK,
  source metadata, rollback recovery and no replay after NON_CRITICAL audit failure.
  NEWS filtering uses published Article classification, rejects invalid values, shares all Article
  policies/lifecycle/revision/audit/SEO/scheduling. No News-specific authority or lifecycle path.
  CMS cannot configure theme navigation; no menu/theme tables/services/permissions. No localization
  endpoints/tables/columns/workflows. Navigation never substitutes backend authorization.
  `/` is never registered by CMS code: a test asserts the application's `/` route still renders the
  application handler after a homepage designation exists, and that no cms route registration
  targets `/`.
DATABASE MATRIX
  the whole suite on SQLite (default) AND MySQL 8.x (disposable test DB per house convention);
  no test may rely on SQLite-only semantics for any §13 constraint.
  ENGINE REQUIREMENT MARKING (task §34): SQLite serializes whole-database writes, so it CANNOT
  demonstrate row-lock interleavings. The following MUST run on real MySQL 8 and MUST be marked
  (e.g. `@group mysql8` / skipped-with-reason on SQLite) rather than reported as passing there,
  because a SQLite green would be false evidence:
    H1, P1, P4, P7            (concurrent claims; unique-index race outcomes)
    P8, P9                    (rename COMMITS under the real UNIQUE set; collision rollback leaves
                                the old CURRENT claim intact — IMP005-REAUDIT-R1-01)
    M3, M4, M6, M12, M14, M15 (attach-vs-purge; recheck-under-lock; purge retry; concurrent draft
                                edits; archive-vs-edit lock order; attach-vs-purge)
    R1, R5                    (concurrent publication / scheduler-vs-manual serialization)
    O1..O8 DB-BYPASS VARIANT, O9 (engine-refusal assertions are engine behaviour, and the composite
                                FK must be proven enforced on the production engine, not only on
                                the test engine)
    every §26 concurrency-matrix row's test
  The following must pass on BOTH engines (deterministic constraint checks, no concurrency):
    H1b, P2, P3, P5, P6, P10, P11, P12, P7(as a constraint assertion), R2, R3, R4b, R4c, R4d, R6,
    R7, M1, M2, M5, M7, M8, M9, M10, M11, M13, M16, O1..O8 (service-layer variants), O10, and all
    S/T/A-series tests (A6..A13 are pure payload-shape assertions and are engine-independent).
    R4a and R4e are source-scanning tests and are engine-independent by nature.
  MySQL-specific emphasis additionally: CHECK constraint enforcement, STORED generated-column
  uniques (active_path / active_current_owner / active_draft_*), the COMPOSITE foreign keys of
  IMP005-REAUDIT-R1-02 (including that a NULL pointer column leaves them inert, which is what
  makes "not yet published" representable), utf8mb4 per-segment transliteration
  edges, gap/next-key locking behaviour around INSERT-on-duplicate.
  SQLite PARITY NOTE (must be verified, not assumed): SQLite enforces composite foreign keys only
  when `PRAGMA foreign_keys` is ON. The §29 matrix therefore includes an assertion that the SQLite
  test connection has it enabled, so O-series tests are not vacuously green there. If it is off in
  a Laravel SQLite connection, that is a test-infrastructure defect to fix, not a reason to trust
  the constraint.
REGRESSION
  permission seeder idempotency; audit registry counts (new entries additive-only, versions
  never edited); migration fresh-run on both engines; reserved-registry boot assertion; sanitizer
  corpus re-run on every dependency bump.
```

## 30. Performance / observability notes

```
Expected volume is small (org-authored content). Required indexes per §13; resolver lookup is
one indexed path fetch against `cms_paths` (+ the REDIRECT claim fetch only on a CURRENT miss,
which is the same single indexed lookup on the same table — there is no second table to join). No cache layer mandated
(v1 correctness without cache invalidation complexity; shared hosting has file cache available —
later stages may add). Audit growth handled by IMP-004 indexes unchanged. Media listing ordered
by created_at. No queue requirement anywhere (cron only).
```

## 31. Shared-hosting compatibility — assessment: PASS BY DESIGN

```
Leverages (LOCKED-approved stack): Laravel Storage local/public disks; Laravel Scheduler via cron
(publish poll + media cleanup); database sessions/queue/cache as already configured; MySQL 8.x;
compiled Inertia assets; pure-PHP sanitizer option.
Requires NOTHING banned: no Redis/WebSockets/PM2/Supervisor/Node runtime/Elasticsearch/Kafka/
microservices/external search cluster/mandatory subdomain. The only external surface additions are
the catch-all web route (same app) and storage symlink serving (standard Laravel deploy).
```

## 32. Acceptance criteria (implementation stage — verifiable at review)

```
AC-01  All §13 tables exist via migrations with the stated constraints on BOTH engines; no
       schema migration touches non-cms_* tables; registry/permission and linked scheduler
       principal/grant reference data use established seed mechanisms only.
AC-02  Lifecycle transitions behave exactly per §10 including rejection of illegal edges;
       scheduling and missed-window recovery are included; no reviewer workflow/SCHEDULED status.
AC-03  Every §12 event is registered (correct axes) and emitted ONLY via canonical sink; no
       caller-side criticality/visibility/strategy supply exists in code.
AC-04  content.* permissions registered per §23; policies per §22; zero role-name predicates
       (grep-proven in review).
AC-05  Managed slugs can never claim reserved prefixes (unit+feature+boot assertion all green).
AC-06  Sanitizer blocks every §28 XSS vector sample; stored bodies are always post-sanitization
       (bypass attempts return 422, nothing raw persists).
AC-07  Media pipeline rejects executables/SVG/mismatched-mime/oversized per §19; only generated
       filenames reach disk; referenced media MAY be logically archived (references end
       attachability, they do not block archive — §19, and AC-28 is the same rule: these two
       criteria must never diverge again) while every existing reference stays resolvable and
       renderable; media with ANY ACTIVE reference are NEVER physically purged (historical
       included, because a historical revision's reference row is ACTIVE permanently); cleanup
       scheduler reclaims orphan files and unreferenced archived assets. The superseded wording
       "referenced-media ARCHIVE blocked" is REMOVED here; it was the last surviving copy of the
       rule §19 abandoned in pass 2.
AC-08  No FK from any cms_* table to financial/business-domain tables; no CMS code path reads or
       writes ledger/payment/donation state or mutates RBAC runtime authority (structure test/grep
       gate); consuming the canonical RBAC evaluator and explicit deploy-time seeding is required.
AC-09  Theme boundary: zero files under any theme/template-render path are created; the public
       payload is presentation-neutral (§24 contract).
AC-10  Full §29 suite passes on SQLite + MySQL 8.x; Pint + type-check + build + composer audit
       clean, per stage-finalization house evidence pattern.
AC-11  Admin screens function inside the fixed backoffice with no theme hooks (visual review +
       absence of theme API usage).
AC-12  IMP-004 invariants preserved: Transaction Ownership check present in every write service.
AC-13  Q29 permission separation holds; no reviewer queue, workflow engine or approval matrix.
AC-14  Q30/Q31 exclusions hold in schema/services/permissions/payloads: no CMS navigation/theme
       configuration or speculative localization; stable neutral destinations are exposed.
AC-15  Q32 version/source/revision binding, System authorization, UTC due evaluation, concurrency,
       idempotency and missed-cron recovery pass the section 29 negative-path tests.
AC-16  Q33 ARTICLE/NEWS validation/filtering uses the single Article revision/lifecycle path;
       no taxonomy/News entity; canonical business-domain data remains outside CMS.
AC-17  Every content event has section 12 classification/failure coverage; ordinary content commits
       survive reported NON_CRITICAL audit failures; security denial semantics stay unchanged.
AC-18  HD-IMP005-01..05 remain FINAL / LOCKED; Human Decisions Required: 0;
       OPEN HUMAN DECISIONS: 0. Review and Human implementation authorization are separate gates.
AC-19  Homepage: route `/` is still registered solely by application code and renders the
       application handler; no CMS route exists for it; `cms_paths` contains no row for `/`;
       `cms_homepage_assignment` cannot hold two rows (CHECK id=1 verified by a deliberate
       second-row insert attempt); the H-series in §29 is green.
AC-20  Path namespace: `cms_paths` is the ONLY place a public path is recorded (no path/slug column
       remains on cms_pages or cms_articles); UNIQUE(active_path) and UNIQUE(active_current_owner)
       exist as STORED-generated-column uniques on BOTH engines; the P-series in §29 is green,
       including the cross-entity and redirect-collision cases.
AC-21  Revisions: the payload/lifecycle split in §11 is enforced by the model guard exactly as
       specified (per-state writable sets); a publication replacement performs its two lifecycle
       UPDATEs and ZERO payload UPDATEs (asserted by R1/R3); no revision row is ever deleted (R7).
AC-22  Media references: every media relationship in §19's closed field vocabulary has a
       `cms_media_references` row; no deletion/archive/purge decision reads stored HTML (grep gate:
       no body parsing in the media decision path); the M-series in §29 is green, including M1 (OG
       only) and M2 (history only), which the pre-remediation design would have passed incorrectly.
AC-23  Lock discipline: every §26 flow acquires locks in the §19 shared tier order (asserted by a
        structural review of each service plus the §26 matrix tests); no flow performs
        check-then-act on media or paths outside a held lock. THE ATTACHMENT FLOW INCLUDING
        TIER 3 (identity + revision) — the omission IMP005-REAUDIT-R1-04 found — is specifically
        verified, and the archive/purge flows are verified to take tier 1 BEFORE any reference read.
AC-24  Cleanup: orphan-file case A and unreferenced-asset case B are separately implemented and
        separately tested; purge never precedes its own reference recheck; filesystem failure never
        loses retry evidence; the cleanup principal holds exactly content.archive and nothing more.
AC-25  PATH RENAME IS EXECUTABLE ON MYSQL 8 (IMP005-REAUDIT-R1-01): the rename commits under the
        real UNIQUE(active_path) + UNIQUE(active_current_owner) set with the conversion-before-insert
        ordering (§14 RENAME); P8/P9 are green on MySQL 8; no code path inserts a new CURRENT claim
        while the owner's old CURRENT claim is still CURRENT; no compensating-repair write exists on
        the collision path; and the reservation policy is unified — retire and archive release
        NOTHING (P10/P11 green, and a source scan shows `PathService::release()` is called by no
        lifecycle transition).
AC-26  REVISION OWNERSHIP IS STRUCTURAL (IMP005-REAUDIT-R1-02): the composite FKs of §13 exist on
        BOTH engines for every identity→revision pointer, for cms_paths.revision_id and for
        cms_media_references.owner_revision_id; the candidate keys they reference exist; and the
        O-series (including the O9 raw-SQL bypass variant on MySQL) is green. No revision pointer is
        expressible as a plain single-column FK.
AC-27  REVISION IMMUTABILITY CLAIMS ARE TRUE (IMP005-REAUDIT-R1-03): no test, comment or document in
        the repository claims that model events intercept an Eloquent/Query Builder bulk update;
        the R4a source-scan test exists, is engine-independent, and passes its own R4e planted
        positive control; `RevisionService`/`PublicationService` expose no generic update or bulk
        surface; and the four per-operation field whitelists match §11 exactly.
AC-28  MEDIA RULES ARE CONSISTENT (IMP005-REAUDIT-R1-04): exactly one archive rule and one purge
        rule exist in the specification AND in the code — logical archive is never blocked by
        references, purge is blocked by any ACTIVE reference including a historical one; archived
        assets still render through existing references and reject new attachments;
        `cms_media_references.status` has no value other than ACTIVE|RELEASED; and the M9-M16 tests
        are green.
AC-29  AUDIT METADATA IS DETERMINISTIC (IMP005-REAUDIT-R1-05): every registered content.* event's
        allow-list matches its §12 contract row (keys and declared types), the A6-A13 series is
        green, NO content.* metadata value is ever null (A7), manual publishes carry no schedule
        keys and scheduled ones carry all of them (A8), and each scheduled execution emits exactly
        one event per the §12 expired-window table (A10/A10b). Criticality remains NON_CRITICAL
        throughout with Q26/Q27/Q28 unchanged.
```

## 33. Definition of Done (IMP-005)

```
Standard DoD (docs/00-governance/DEFINITION-OF-DONE.md) plus:
[x] All five HD-IMP005-01..05 FINAL / LOCKED by Human and materialized; open decisions: 0
[x] Codex Pass-0 findings IMP005-SPEC-01..10 all PATCHED (none self-marked RESOLVED); disposition
    recorded in docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md
[x] Codex Re-Audit Pass-1 findings IMP005-REAUDIT-R1-01..05 all PATCHED (none self-marked
    RESOLVED); disposition recorded in
    docs/audits/IMP-005-SPECIFICATION-REMEDIATION-2.md
[ ] §32 acceptance criteria all green; negative-path authorization + audit tests included
[ ] Domain docs materialized: docs/06-domains/cms/CMS.md — a Level-5-adjacent document derived
    from this specification; it does NOT create a Level 3 baseline and claims none
[ ] IMP-006 contract note appended to this file if the content payload contract changes during
    implementation (boundary drift requires Codex attention)
[ ] Independent Codex review PASS with 0 open BLOCKER/MAJOR; Human Stage Gate approval recorded;
    finalization record under docs/audits/IMP-005-FINALIZATION.md
```

## 34. Forbidden changes (recited from locked sources; violations = BLOCKER)

```
Locked decisions that this stage must not alter: Single Organization; Centralized Settlement;
Modular Monolith; Single Laravel App; Single root domain; Same-origin /api/v1; Ledger truth;
Theme=presentation-only; Admin fixed design system; Partner != tenant; shared-hosting mandate;
default-DENY; append-only audit (IMP-004); Q26-Q28; permission naming convention; no inventing
business rules, rates, approval limits, accounting entries, or legal retention durations.
```

## 35. Implementation handoff

```
To: Qwen 3.8 Flash (qwen/qwen3.8-flash) in Command Code GOAT — AFTER all of:
  1. Independent review of THIS specification (Codex, CODING: NO) completes clean;
  2. HD-IMP005-01..05 are FINAL / LOCKED and materialized (section 25; completed);
  3. Human grants IMP-005 implementation authorization (explicit act; this document's existence
     is NOT authorization);
  4. Subsequent material specification changes receive appropriate change control/review.
Branch at implementation: impl/005-cms (BRANCHING-POLICY naming). Commit discipline: conventional,
coherent slices (migrations+models -> services+policies -> audit registry -> admin UI -> public
resolver -> tests). Reviewer focus list: sanitizer corpus, reserved-route tests, audit criticality
table, §13 generated-column uniqueness on both engines, Transaction Ownership Invariant usage.
Stop conditions (AGENTS.md): any HD-adjacent ambiguity discovered mid-build -> STOP + report;
anything requiring a Level 1-4 change -> ACR, never silent drift; do not begin IMP-006 work.
```

---

```
Document:  docs/implementation/IMP-005-cms.md
Task:      IMP-005 SPECIFICATION REMEDIATION PASS 1 AND PASS 2 (documentation only — no code, no
           migrations, no tests, no IMP-006 work)
Spec state: PATCHED (IMP005-SPEC-01..10; IMP005-REAUDIT-R1-01..05) — PENDING CODEX RE-AUDIT
Remediation records: docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md
                     docs/audits/IMP-005-SPECIFICATION-REMEDIATION-2.md
IMP-005 implementation: NOT AUTHORIZED
Merge / push: NOT AUTHORIZED
```
