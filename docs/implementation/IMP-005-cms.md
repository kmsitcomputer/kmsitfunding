# IMP-005 — CMS (Content Experience)

## Status

```
Stage:                        IMP-005 — CMS
Document State:               SPECIFICATION — REMEDIATION PASS 1 APPLIED — PENDING CODEX RE-AUDIT
Codex Audit (Pass 0):         BLOCKER 0 / MAJOR 7 / MINOR 3 / EDITORIAL 0 / HUMAN DECISION 0 —
                              FAIL — IMP-005 SPECIFICATION REQUIRES REMEDIATION
                              (7 of the 10 findings are implementation-gate)
Findings IMP005-SPEC-01..10:  ALL PATCHED — NONE self-marked RESOLVED. Per-finding disposition:
                              docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md
Implementation Authorization: NOT GRANTED (remains gated on Codex re-audit + separate Human act)
Repository Baseline:          master @ f1aef3d59c2f5fa9361890bed8afa138f134680f
                              (IMP-000..IMP-004 FINAL / LOCKED — see
                              docs/audits/IMP-004-FINALIZATION.md and prior finalization records)
Human Decisions:              HD-IMP005-01..05 answered FINAL / LOCKED by the Human Authority and
                              materialized as Q29-Q33 in docs/01-requirements/HUMAN-DECISION-REGISTER.md
                              — this specification reflects those answers (section 25); the
                              remediation pass changed NONE of them
Human Decisions Required:     0
OPEN HUMAN DECISIONS:         0
This document changes:        NO code, NO migrations, NO routes, NO tests, NO production config.
                              Documentation-only remediation (PASS 1 raised no new Human decision).
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
one UTC cutoff per run; published_on is display metadata, never the scheduling clock.

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
without briefly publishing: leave DRAFT/RETIRED nonpublic or retire existing PUBLISHED content;
clear both timestamps, emit schedule_expired, plus unpublished only when retirement occurred.

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

PAYLOAD columns (IMMUTABLE once the row has been published):
   revision_no, owner linkage (page_id/article_id), title, article_type, slug_snapshot,
   body_html, meta_title, meta_description, og_title, og_description, og_image_asset_id,
   no_index, author_principal_id, authored_at
   - While state = DRAFT the payload is editable by content.update (it is the active draft).
   - From the moment state leaves DRAFT (PUBLISHED or SUPERSEDED) the payload is FROZEN for the
     lifetime of the row: no service, no admin screen, no importer and no scheduled job may change
     any payload column. Editing a published page means creating a NEW DRAFT revision and
     publishing it. Rollback means copying an old payload into a NEW revision.

LIFECYCLE columns (service-mutable, never payload):
   state (DRAFT | PUBLISHED | SUPERSEDED), published_at, superseded_at,
   state_changed_by_principal_id (who/what performed the transition — a lifecycle fact, not origin
   attribution; see "Editor / timestamp attribution" below)
   - Mutable ONLY through PublicationService transitions inside its own transaction
     (section 26). No other writer touches them; they are never mass-assignable and never
     accepted from request input.

Revision identity vs pointer:
   The identity row's published_revision_id is the ONLY statement of "what is currently published".
   A revision's state column is derived bookkeeping kept in the same transaction as that pointer
   swap: exactly one revision per owner may be PUBLISHED, and it is the one the pointer names.
   If the two ever disagree, the pointer is authoritative and the disagreement is a defect.
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

Enforcement layers (stated explicitly so nobody guesses, and so no reviewer reads AuditRecord
semantics into CMS):

```
1. SERVICE: the only code path allowed to UPDATE a revision row is PublicationService (lifecycle
   columns) and RevisionService (payload columns, guarded by state = DRAFT). Both re-read the row
   under lock inside the transaction and re-verify state before writing.
2. MODEL: an immutability guard on the revision model rejects any dirty attribute outside the
   declared writable set for the current state. The sets are closed and exhaustive:
     DRAFT      -> payload columns (any) + lifecycle {state, published_at,
                   state_changed_by_principal_id} and only as a single publish transition
                   (state must become PUBLISHED; published_at must be set in the same write)
     PUBLISHED  -> lifecycle {state, superseded_at, state_changed_by_principal_id} and only as a
                   single supersede transition (state must become SUPERSEDED); NO payload column,
                   NO published_at change
     SUPERSEDED -> NOTHING. Terminal. Any dirty attribute at all is an exception.
   A violation raises an application invariant exception, mirroring IMP-004's application-level
   append-only guard for AuditRecord.
3. MASS ASSIGNMENT: every payload column is set only by services from validated DTOs;
   state/published_at/superseded_at/state_changed_by_principal_id are never fillable
   (section 28 mass-assignment row).
4. NOT a database trigger. Consistent with IMP-004's approach, immutability is an application
   invariant + regression tests (section 29), not trigger code. This is a deliberate difference
   from AuditRecord only in degree, not in kind: both are application-enforced.
5. Do NOT copy AuditRecord's "no UPDATE whatsoever" rule onto CMS revisions: audit rows are
   evidence of things that happened, revision rows are live publication targets. The lifecycle
   column must be mutable or publication cannot work — which is precisely the contradiction this
   section removes.
```

Direct-mutation bypass: `Revision::query()->update([...])` / `->save()` on a non-DRAFT payload,
`DB::table()` writes, or any attempt to change a published body/title/SEO payload is rejected by
layer 2 and is covered by negative tests (section 29). A raw-SQL bypass cannot be blocked by
application code and is not claimed to be: DB grants are the deployment boundary, and the audit
trail plus payload history make an out-of-band change detectable, not silently unrecoverable.

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
                      HTML NEVER enter metadata; every allow-list below is CLOSED (a key not
                      listed is rejected by the registry, exactly as IMP-004 specifies)
```

| event_type | actor kinds | subject_type / subject_id | metadata allow-list (closed) |
|---|---|---|---|
| `content.page.created` | human | cms_page / pages.id | `revision_id`, `slug_snapshot` |
| `content.page.updated` | human | cms_page / pages.id | `revision_id`, `slug_snapshot`, `fields_changed` (array; closed vocabulary: `title`, `body_html`, `meta_title`, `meta_description`, `og_title`, `og_description`, `og_image_asset_id`, `no_index`, `slug_snapshot`) |
| `content.page.published` | human, system | cms_page / pages.id | `revision_id`, `previous_revision_id`, `from_status`, `to_status`, `path`, `previous_path`, `homepage_designated` (0/1), + schedule source fields when actor=system (below) |
| `content.page.unpublished` | human, system | cms_page / pages.id | `revision_id`, `from_status`, `to_status`, `path`, + schedule source fields when actor=system |
| `content.page.archived` | human | cms_page / pages.id | `from_status`, `to_status`, `paths_released` (array of path strings, capped at 20 then `paths_released_count`), `homepage_designation_cleared` (0/1) |
| `content.article.created` | human | cms_article / articles.id | `revision_id`, `slug_snapshot`, `article_type` |
| `content.article.updated` | human | cms_article / articles.id | as page.updated, plus `article_type` |
| `content.article.published` | human, system | cms_article / articles.id | as page.published, plus `article_type` (no `homepage_designated`: Articles are never designated) |
| `content.article.unpublished` | human, system | cms_article / articles.id | as page.unpublished, plus `article_type` |
| `content.article.archived` | human | cms_article / articles.id | as page.archived, plus `article_type` |
| `content.page.schedule_updated` | human | cms_page / pages.id | `operation` (closed: `CONFIGURED`/`CHANGED`/`CANCELLED`), `publish_at`, `unpublish_at`, `schedule_version`, `scheduled_revision_id`, `scheduled_at`, `scheduled_by_principal_id` |
| `content.article.schedule_updated` | human | cms_article / articles.id | same, plus `article_type` |
| `content.page.schedule_expired` | system | cms_page / pages.id | `schedule_version`, `publish_at`, `unpublish_at`, `retired` (0/1), `system_operation` (`content.scheduler`) |
| `content.article.schedule_expired` | system | cms_article / articles.id | same, plus `article_type` |
| `content.media.uploaded` | human | cms_media_asset / media_assets.id | `asset_ulid`, `mime_type`, `extension`, `size_bytes`, `sha256`, `duplicate_asset_ulid` (nullable — set when a same-hash ACTIVE asset already exists) |
| `content.media.updated` | human | cms_media_asset / media_assets.id | `asset_ulid`, `fields_changed` (array; closed vocabulary: `alt_text`, `caption`, `original_filename`) |
| `content.media.archived` | human | cms_media_asset / media_assets.id | `asset_ulid`, `prior_references` (closed: `NONE` / `RELEASED_ONLY`; `ACTIVE` references block the operation, so it can never appear here) |
| `content.media.purged` | system | cms_media_asset / media_assets.id | `asset_ulid`, `purge_attempts`, `historical_reference_verified_absent` (0/1; must be 1 for a lawful purge), `previously_archived_by_principal_id`, `system_operation` (`content.media_cleanup`) |
| `content.homepage.assigned` | human | cms_homepage_assignment / the fixed singleton id (1) | `operation` (closed: `ASSIGNED`/`REPLACED`/`CLEARED`), `previous_page_id`, `page_id`, `page_ulid`, `expected_page_id`, `previous_expected_matched` (0/1) |
| `content.path.released` | human | cms_path / paths.id | `path`, `purpose` (`CURRENT`/`REDIRECT`), `owner_type` (`cms_page`/`cms_article`), `owner_id`, `reason` (`GOVERNED_RELEASE`) |

Schedule source fields (used ONLY by events whose actor is system, omitted otherwise):
`schedule_version`, `scheduled_at`, `scheduled_revision_id`, `scheduled_by_principal_id`.

Mutation → event completeness map (this table is the audit-side proof for section 29; each
mutation maps to EXACTLY ONE event, so there are no duplicate emissions to reconcile):

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
Schedule expire            -> content.page.schedule_expired Article expire      -> content.article.schedule_expired
Media upload               -> content.media.uploaded
Media metadata edit        -> content.media.updated         (the previously-missing event:
                                                             alt/caption renames were mutating
                                                             audited state with no event)
Media logical archive      -> content.media.archived
Media physical purge       -> content.media.purged          (distinct from archive: different
                                                             actor kind, different effect,
                                                             different evidence — section 27)
Homepage designate/replace -> content.homepage.assigned     (cleared as part of an ARCHIVE is
                                                             recorded on the ARCHIVE event, not as
                                                             a second event — see below)
Path claim                 -> NO separate event: it occurs only inside publish, so it is carried
                             by *.published (`path` / `previous_path`). A claim with no publication
                             does not exist (section 14: claims exist only for published paths).
Path rename                -> NO separate event: carried by *.published as
                             `previous_path` -> `path`.
Redirect row creation      -> NO separate event: the rename above IS the redirect creation.
Path governed release      -> content.path.released         (the ONLY standalone path mutation)
Auto-release on archive    -> NO separate event: carried by *.archived `paths_released`.
Homepage clear on archive  -> NO separate event: carried by *.archived
                             `homepage_designation_cleared`.
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

Registry entry shape (what each of the 20 registrations above must carry — the axes are the
common list plus that row's columns; no per-event prose is needed because nothing varies beyond
them):

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
required metadata      exactly the row's closed allow-list; keys not listed are rejected
optional metadata      none beyond the allow-list (an event carries nothing "extra for context")
```

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
- Schedule_updated has Human actor and operation (configured/rescheduled/cancelled); registry
  metadata allow-list includes the schedule source/version/revision/timestamps above. Scheduled
  publication/unpublication/expiry requires these source fields; unrelated manual events omit them.
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
fields and due indexes; created_by/updated_by attribution). is_homepage and the path column are
ABSENT — Articles are never homepage-designated and their public path is likewise a cms_paths
claim. The former config-driven '<article_prefix>/' namespace scoping is REMOVED: with a single
shared cms_paths namespace there is no separate article namespace to prefix, and a config prefix
would be a second, unenforced copy of the path rule. An article's path is simply the claim the
publisher chose for it (e.g. /news/ramadan-appeal).
extra columns:   excerpt (string 511, nullable), published_on (date nullable) — display metadata,
                 maintained by the publish transaction alongside the pointer move (not free-write),
ENGINEERING CHOICE minimal set.
```

### `cms_content_revisions`
```
purpose            versioned content PAYLOAD + the lifecycle columns that drive publication.
                   Payload is frozen once published; lifecycle moves by service transition only
                   (section 11). The former "append-only once PUBLISHED/SUPERSEDED" wording that
                   contradicted the SUPERSEDED flip is replaced by that split.
pk                 id BIGINT UNSIGNED AUTO_INCREMENT
owner              page_id (FK cms_pages RESTRICT, nullable) XOR article_id (FK cms_articles
                   RESTRICT, nullable); CHECK (page_id IS NULL) <> (article_id IS NULL)
                   — strong referential integrity kept with real FKs (DB-ARCHITECTURE) instead of
                     soft polymorphic (type,id) pair; SQLite and MySQL 8 both enforce CHECK
payload columns    revision_no (BIGINT, per-owner increasing, immutable),
                   title (string 255 — versioned: titles change with revisions),
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
lifecycle columns  state (string 16: DRAFT|PUBLISHED|SUPERSEDED), published_at (nullable),
                   superseded_at (nullable), state_changed_by_principal_id FK principals
                   RESTRICT NULL — written ONLY by PublicationService (section 11)
generated keys     page_key    = COALESCE(page_id, 0)   STORED
                   article_key = COALESCE(article_id, 0) STORED
                   (needed because a UNIQUE index over a nullable FK column cannot detect
                    duplicates in MySQL/SQLite: two NULLs never collide. Coalescing to 0 makes the
                    owner dimension total, so the uniqueness below is real on BOTH engines.)
unique             (page_key, article_key, revision_no) — per-owner revision numbering is unique;
                   single-active-draft: generated active_draft_page_id (= page_id WHEN
                   state='DRAFT' ELSE NULL) UNIQUE and active_draft_article_id (= article_id WHEN
                   state='DRAFT' ELSE NULL) UNIQUE — NULLs do not collide on a unique index, so
                   exactly one DRAFT per owner is DB-enforced on both engines, plus a service-level
                   guard inside the mutation transaction; validated in the §29 matrix
indexes            (state), (article_key, state), (published_at)
immutability       application-enforced and STATE-CONDITIONAL (section 11): PUBLISHED/SUPERSEDED
                   rows reject payload updates; SUPERSEDED rows reject all updates; every revision
                   row rejects DELETE on every v1 path. Implemented as a model-level guard
                   enumerating the writable attribute set per state — NOT a DB trigger — consistent
                   with IMP-004's application-invariant approach, proven by §29 regression tests.
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
                   revision_id FK cms_content_revisions RESTRICT NULL — the revision whose
                     publication created this claim (provenance; for REDIRECT rows, the revision
                     that HELD this path before it was superseded)
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
release            a claim moves ACTIVE -> RELEASED (row retained, never deleted) ONLY by an
                     authorized service transition: owner ARCHIVED (section 27), or an explicit
                     governed redirect release (section 14 path-reuse policy)
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
                     treats them differently)
                   field_path (string 64 NOT NULL — which payload position refers: 'body_html',
                     'og_image_asset_id'; the closed vocabulary is defined in section 19 and any
                     new media-bearing field MUST be added to it, which is what makes the model
                     complete-by-construction rather than "whatever we remembered to scan")
                   reference_kind (string 16 NOT NULL: BODY_TOKEN | SEO_IMAGE)
                   status (string 16 NOT NULL: ACTIVE | RELEASED), released_at nullable
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
                   archived_at nullable, purged_at nullable,
                   purge_attempts INT UNSIGNED NOT NULL default 0, last_purge_error (string 511
                     nullable) — retry evidence for the filesystem-failure path (section 19);
                     these are operational counters on an asset row, NOT balances, and are
                     excluded from the no-mutable-balance concern
status_rules       ACTIVE   attachable and publicly renderable
                   ARCHIVED logically deleted (section 27): never attachable to new content, files
                     still on disk, references retained
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
Behavior retained verbatim: REDIRECT rows are written ONLY by the publish/slug-change services
(system-written — never user-supplied old paths); resolution follows the owner's ACTIVE CURRENT
claim (301); when the owner is RETIRED or ARCHIVED the claim releases and the old path 404s (no
dangling redirects to non-content).
```

### Path history & reuse policy (deterministic v1 baseline)
```
Question answered: may an old path such as /old-article be reused later?

BASELINE: an old path REMAINS RESERVED for as long as its REDIRECT claim exists.
  - A REDIRECT claim occupies the same UNIQUE(active_path) slot as a CURRENT claim, so while the
    redirect is ACTIVE nothing — not the original owner, not another Page, not an Article, not a
    concurrent transaction — can claim that path. Redirect shadowing is impossible by construction
    rather than by convention.
  - Reuse becomes possible ONLY after an explicitly governed RELEASE of the redirect/history claim
    (section 19/27 `PathService::release()` under content.archive, audited as
    content.path.released). Release sets status=RELEASED and retains the row forever (history rows
    are never deleted, section 27); the released path is then claimable by any CMS content, and a
    new claim is a NEW row (the old row keeps its identity as the historical record).
  - Automatic release occurs when the owner is ARCHIVED: its CURRENT claim releases AND its
    REDIRECT claims release (there is no longer a destination to point at, and the prior
    "deactivates and 404s" behavior is preserved).
  - No silent expiry: v1 has NO time-based auto-release of redirects and NO invented retention
    duration (section 27 — no legal durations asserted anywhere in this specification). A grace
    period for redirect expiry is therefore NOT specified; if one is ever wanted it is a config
    value plus its own change control, not an implementation-time guess.
  - Reuse after release is safe and deterministic: it can never resurrect a live redirect because
    live redirects cannot be double-claimed, and the historical RELEASED row for the same string is
    a distinct row that does not collide (UNIQUE applies to active_path, which is NULL when
    RELEASED).
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
Claim / rename / release mechanics (the cms_paths operations, all inside the publish transaction):
  CLAIM    insert ACTIVE CURRENT row for (path, owner, revision). UNIQUE(active_path) rejects a
           duplicate claim; the loser gets 409 path_conflict and does NOT retry automatically.
           Creating content's first revision path and publishing at a brand-new path are the same
           operation.
  RENAME   one transaction, in this order so the unique index is never violated mid-flight:
           (1) INSERT the ACTIVE CURRENT claim for the NEW path (fails fast on collision, leaving
           the old claim untouched); (2) UPDATE the owner's old claim row from purpose=CURRENT to
           purpose=REDIRECT with revision_id set to the superseded revision — the row stays ACTIVE,
           so the old path REMAINS RESERVED (section 7 policy above) and is never momentarily free;
           (3) point the identity's published_revision_id at the new revision; (4) freeze the old
           revision to SUPERSEDED; (5) audit. A rename of a never-published draft is just a payload
           edit (the draft has no claim; claims exist only for published paths).
  RELEASE  ACTIVE -> RELEASED with released_at, by an authorized service transition only
           (owner archived, or explicit governed release). Audited as content.path.released.
           RELEASED rows are retained permanently and are what makes "was this path ever used?"
           answerable.
CONCURRENCY: two transactions claiming the same path — whichever commits second receives the
  duplicate-key error on UNIQUE(active_path) and rolls back its whole transaction (claim + pointer
  + lifecycle flip + audit together); the error is translated to 409 path_conflict. No
  check-then-insert window exists: the insert IS the check. Cross-entity (Page vs Article) and
  current-vs-redirect collisions are the SAME case, which is exactly why they are now covered.
Publication behavior: content with no ACTIVE CURRENT claim, or in DRAFT/RETIRED/ARCHIVED state,
  resolves 404 (never 410/500 disclosure). Draft/access control is server-side only.
Redirect status: 301 to the owner's ACTIVE CURRENT path while the redirect claim is ACTIVE; 404
  when the owner is RETIRED (no destination) or the claim is RELEASED. Targets are always internal,
  system-computed from published content — no user-authored redirect target (open-redirect class
  eliminated by construction).
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
                                 sanitization hook, media attachment protocol section 19)
  PublicationService             publish / unpublish / retire / archive / republish; the revision
                                 pointer-swap + claim transaction (section 26); scheduled-transition
                                 executor invoked by the Laravel Scheduler (Q32; console command
                                 content:run-scheduled-transitions, running as the cataloged
                                 content.scheduler System Principal — section 12 attribution);
                                 homepage assignment here (singleton row guard, section 13)
  RevisionService                revision reads/history listing; rollback-by-copy (creates new
                                 draft revision, never rewrites history; payload-frozen rows are
                                 read-only, section 11)
  PathService                    THE single path authority: per-segment normalization + reserved-
                                 registry check + claim/rename/release against `cms_paths`
                                 (section 14). Used by the services above; no other service composes,
                                 compares, or writes paths. Renamed from the former "SlugService":
                                 the design no longer manages a per-entity slug column, it manages
                                 the shared namespace, and the name must not imply otherwise.
  MediaService                   upload intake (validation pipeline section 19), logical archive,
                                 physical purge, metadata update, and reference QUERIES.
                                 References are read from `cms_media_references` (section 13) and
                                 written only via the attachment protocol (section 19). The former
                                 wording — "reference = media ULID token present in published/draft
                                 body_html, token scan service-local" — is REMOVED: it is precisely
                                 the IMP005-SPEC-05 defect (a token scan cannot see
                                 og_image_asset_id or historical-revision usage). No cached mutable
                                 counter column, per the "no mutable balance" house principle.
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
  blocked while any ACTIVE reference exists from non-ARCHIVED content (draft,
  published, or superseded). The check is a single indexed query on `cms_media_references`
  (section 13) — it is NOT a body-HTML scan, which was the prior design's defect: a body-token-only
  scan could not see `og_image_asset_id` and could not see historical revisions at all, so an asset
  that was demonstrably in use could be judged unused. On success (NON_CRITICAL audit
  `content.media.archived`) row -> ARCHIVED; the file stays on disk and reference rows are
  RETAINED (they are the reason it stays); physical removal happens only via the purge rules below.

Historical rendering guarantee vs deletion (section 27 reconciliation):
  CMS preserves published history (sections 11/27: revision rows are never deleted, and
  SUPERSEDED revisions remain viewable to admins behind content.view). An asset required to render
  preserved history therefore MUST NOT be physically purged while that history exists.
  - media referenced by any PUBLISHED or SUPERSEDED revision is NEVER physically purged in IMP-005,
    regardless of the status of its owner content. It may only be LOGICALLY ARCHIVED (hidden from
    the picker, unattachable to new content) with its bytes retained.
  - physical purge in v1 is therefore limited to assets that were NEVER used by published or
    superseded content: abandoned uploads, and assets referenced only by drafts that were replaced.
  - the two operations are named differently everywhere in this specification — "archive" (logical,
    DB state + admin operation) and "purge" (physical, filesystem deletion + DB state PURGED).
    The word "delete" is not used for either as a technical term.
  - A future capability to purge history-bearing media requires its own retention/Human decision
    (Q28-governed flow) and a change-control amendment; it is explicitly NOT built here.
```

### Media reference lifecycle (IMP005-SPEC-05/06)

```
The closed field vocabulary for `cms_media_references.field_path` (any new media-bearing CMS field
MUST be added here, and its omission is a review blocker):

  field_path            reference_kind   written by
  'body_html'           BODY_TOKEN       PageService/ArticleService draft write + PublicationService
  'og_image_asset_id'   SEO_IMAGE        PageService/ArticleService draft write + PublicationService

Reference rows are ACTIVE while the referencing revision is the object's active draft or a
published/superseded revision, and become RELEASED only when (a) a later edit of the SAME revision
drops that reference, or (b) an authorized archive/release transitions them. Release never deletes
the row (section 27).
```

### Attachment protocol (write side; must not lose to a concurrent delete)

```
When content/revision references media, ALL of the following happen in ONE service-owned
transaction, in this order:

  1. LOCK the relevant media asset row(s) (`SELECT ... FOR UPDATE` by asset id, ids ascending)
     — the asset row is the serialization point for "can this asset be attached?";
  2. VERIFY the asset exists and its state is attachable: status = ACTIVE. ARCHIVED/PURGED =>
     reject `media_asset_not_attachable` (this is what makes an in-flight delete safe: the writer
     cannot attach an asset whose delete already concluded it was unused, because that delete
     holds the asset lock until commit and has already moved the row to ARCHIVED);
  3. WRITE/REPLACE the content reference (payload column and/or body token inside sanitized HTML);
  4. PERSIST the normalized `cms_media_references` rows for exactly the references present in the
     stored payload — releasing any that the new payload no longer contains and that belong to
     THIS revision, inserting the new ones (existence-unique, section 13);
  5. COMMIT together. Payload, references and audit are atomic: no commit point exists at which
    stored content references an asset with no reference row, or a reference row points at
    content that no longer mentions it.

  A delete that started BEFORE this transaction reaches step 1 completes first and this attach is
  rejected at step 2. A delete that starts AFTER this commit re-checks references under the lock
  (below) and finds the new ACTIVE reference, so it is blocked. There is no interleaving that
  attaches a purged asset or archives an attached one.
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

DELETION (logical archive) under the same order:
  lock asset -> RECHECK all ACTIVE references from `cms_media_references` (never from HTML)
             -> verify deletable state (no ACTIVE reference from non-ARCHIVED content; if any,
                reject `media_referenced` listing the referencing identities)
             -> mark ARCHIVED (archived_at, status) + audit -> commit.
  NO check-then-delete window exists: the check happens after the lock is held and the transition
  happens before it is released. Two concurrent deletes of the same asset serialize on the asset
  lock; the second sees status=ARCHIVED and is an idempotent no-op (no second audit event).
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

CASE B — UNREFERENCED MEDIA ASSET (DB row exists, zero permitted references)
  Eligibility (ALL must hold, re-verified under lock at execution time):
    - status = ARCHIVED (an ACTIVE asset is never purged silently; archive is an operator act), AND
    - NO ACTIVE row in `cms_media_references`, AND
    - NO reference at all (ACTIVE or RELEASED) from a PUBLISHED or SUPERSEDED revision —
      the historical-rendering rule above, which is what keeps case B strictly narrower than
      "not currently used", AND
    - archived_at older than config `cms.media.purge_grace_days` (default 7; configurable
      operational bound, NOT a legal retention duration), AND
    - no authorized hold applies (a future Q28-style hold; if none exists the check passes).
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

CASE C — REFERENCED MEDIA ASSET
  Must not be purged, and must not be logically archivable while any non-ARCHIVED content
  references it (rejected `media_referenced`). Rendering continues normally. Reference rows and
  bytes are both retained indefinitely in v1.

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
                         media metadata (cannot alter a bound scheduled draft) — EDIT
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
Specification: PATCHED (IMP005-SPEC-01..10) — PENDING CODEX RE-AUDIT
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
       pre-read (UNLOCKED, hint only): the candidate revision and the asset ids its
         cms_media_references rows name
       -> lock tier 1: those media assets, ids ASCENDING
       -> lock tier 2: their reference rows, ids ASCENDING
       -> lock tier 3: the content IDENTITY row (lockForUpdate), then the candidate revision and
          the CURRENTLY published revision if one exists (ids ascending)
       -> validate authority (canonical evaluator, section 22) AND resource state (section 10)
          AND, under the held locks, that every referenced asset is still status=ACTIVE
          (attachability re-check, section 19) AND that the candidate revision is still the
          identity's live draft (or a valid RETIRED re-publish target)
       -> transition publication state: candidate revision DRAFT|RETIRED-target -> PUBLISHED
          (sets published_at), previous PUBLISHED revision -> SUPERSEDED (sets superseded_at),
          identity published_revision_id pointer moves; latest_draft_revision_id cleared
       -> lock tier 4 / update path state: CLAIM the candidate's path, or RENAME (claim new +
          convert the owner's old CURRENT claim to REDIRECT, section 14) — never a moment where
          the new path is unclaimed or the old path is freed
       -> update reference state if applicable: revision media references written/released for
          THIS revision only (section 19 attachment protocol)
       -> tier 5 (homepage singleton) is NOT taken by publish; assignment is its own operation
       -> write audit according to the event's classification (section 12)
       -> COMMIT (all-or-nothing; a path-collision duplicate-key aborts the whole flow)
     Failure at any step rolls back state, pointer, claim, references and audit together.
     PUBLISH WITH NO MEDIA: tiers 1-2 are simply not taken (the candidate names no assets); the
     relative order of the remaining tiers is unchanged, which is all the shared order requires.
     Consequence accepted by design: a publish holds its assets' locks briefly, so a concurrent
     archive of the same asset waits rather than racing. Media sets in v1 are small; this is not
     a throughput concern, and correctness does not depend on it.

  2. PATH CHANGE (rename of live published content, or a governed release):
       atomic claim + redirect + (optionally) release in ONE transaction, ordered so the unique
       index is never violated mid-flight: insert the new ACTIVE CURRENT claim FIRST (a collision
       fails fast and leaves the old claim untouched), then convert the owner's old claim row from
       purpose=CURRENT to purpose=REDIRECT (status stays ACTIVE — the old path REMAINS RESERVED),
       then move pointers, then audit. A governed release of a redirect is a separate authorized
       mutation (PathService::release(), content.archive) that sets status=RELEASED and retains the
       row; it is never a side effect of something else. Two path changes to the same owner
       serialize on the owner's claim rows in id-ascending order.

  3. MEDIA ATTACH: exactly the section 19 attachment protocol — lock asset -> verify ACTIVE and
     attachable -> write content reference -> persist normalized cms_media_references rows ->
     COMMIT together. Payload and reference rows have no commit point at which they disagree.

  4. MEDIA LOGICAL ARCHIVE (operator "remove"): lock asset -> RECHECK references from
     cms_media_references (never HTML) -> verify deletable state -> transition status to ARCHIVED
     -> audit -> COMMIT. Filesystem untouched (bytes are reclaimed only by flow 5).
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
     scheduled_by_principal_id), and the additional pre-commit guards of section 10 (re-read
     schedule_version; source Human still eligible; target still owned and not SUPERSEDED;
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
| Human publish vs Human publish (same identity) | identity row lockForUpdate (tier 3) + optimistic `expected_current_revision_id` | second writer, after acquiring, finds the pointer/state moved -> 409 stale_publication | NO automatic retry; operator re-selects |
| Human publish vs scheduler publish | SAME identity row lock — both use flow 1/5, so they serialize on it. Whichever commits first wins; a manual publish atomically CANCELLED the pending intent (section 10), and a scheduler that then re-reads a bumped schedule_version no-ops | scheduler loses silently as a NO-OP (consumes nothing, emits no duplicate event); a Human loses with 409 | NO for the scheduler no-op (nothing to retry); NO for the Human (reports) |
| scheduler duplicate execution (overlapping/re-run cron) | schedule_version re-read + consumed timestamps + state guard inside the identity lock | second execution sees deadline consumed / state advanced -> NO-OP, no second audit event | N/A (idempotent by construction) |
| homepage assign vs homepage assign (no homepage yet) | the SINGLETON ROW `cms_homepage_assignment` id=1, lockForUpdate — it always exists from reference seeding, so there is never an empty slot to race for; CHECK (id=1) makes two rows impossible | the blocked transaction re-reads after acquiring, finds the slot occupied -> 409 homepage_assignment_conflict reporting the current designee | NO automatic retry (a silent retry would be a destructive surprise); explicit expected_page_id is the deliberate-replacement path |
| Page path vs Article path (same normalized string) | `cms_paths` UNIQUE(active_path) — one index spanning both kinds | the later commit receives the duplicate-key error, its whole transaction rolls back -> 409 path_conflict | NO automatic retry; the client surfaces the suggested alternative |
| current content path vs redirect/history path | same UNIQUE(active_path) — a REDIRECT claim holds the slot exactly as a CURRENT claim does, so shadowing is impossible by construction | 409 path_conflict; reuse requires an explicit governed RELEASE first (section 14) | NO |
| media attach vs media archive/purge | asset row lock (tier 1) is the serialization point; attach verifies status=ACTIVE UNDER that lock | if the archive/purge won: attach rejected `media_asset_not_attachable`; if attach won: the archive/purge recheck finds the new ACTIVE reference and rejects `media_referenced` | NO — neither outcome is transient; both are reported |
| media archive/purge vs media archive/purge (same asset) | same asset row lock, ids ascending | second sees status already ARCHIVED/PURGED -> idempotent NO-OP, no duplicate audit event | N/A |
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
  RELEASE       cms_paths / cms_media_references         content.archive        N/A (the row
                claim row ACTIVE -> RELEASED, retained     (auto) or explicit    survives either way)
                (no longer reserves the slot)              governed release

  The bare word "delete" is NOT a technical term in IMP-005 and must not appear as a method,
  route, permission, status or event name for any CMS row: no managed content row, revision, media
  row, path claim or reference row is ever DELETEd in v1. A reviewer finding a `delete()` call on
  any of these is finding a BLOCKER, not a naming nit.

1. PAGE / ARTICLE ARCHIVE
   Sets identity status=ARCHIVED. NO hard delete exists in v1 (LOCKED-adjacent: "Preserve financial
   history" is financial, but applied to content by the same no-destruction-of-history house
   principle + the immutability of published revisions). The identity row, its revisions, its
   reference rows and its path rows all REMAIN. Effects at commit:
     - its ACTIVE CURRENT path claim RELEASES, and its ACTIVE REDIRECT claims RELEASE too (there is
       no longer a destination to point at — a live redirect to archived content would 404 at the
       end of a 301, which is worse than a direct 404 at the old path);
     - it stops resolving publicly and is excluded from default admin listings;
     - if it is the homepage designee, the designation is CLEARED in the same transaction (a
       dangling designation to archived content would make HomepageContentResolver return null
       forever with nobody able to see why; clearing is explicit, audited, and the audit event
       records `homepage_designation_cleared` in metadata);
     - ARCHIVED is terminal: restoring means creating new content, not flipping the status back
       (section 10). No "restore" event, no restore chain, no invented un-archive path.

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
   section 14 reuse policy and would have permitted redirect shadowing. An old path stops
   reserving its slot only via an explicit RELEASE (status=RELEASED, row retained), which happens
   automatically when the owner is ARCHIVED, or by a governed release of a redirect. RELEASED rows
   persist permanently and are what makes "has this path ever been used?" answerable.
   `cms_media_references` rows follow the same rule: ACTIVE -> RELEASED, never deleted.

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
                           (published_revision_id, latest_draft_revision_id, status, and the whole
                           cms_homepage_assignment row — page_id/assigned_by/assigned_at included)
                           are NEVER mass-assignable —
                           set only by services
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
```

## 29. Test strategy (specified BEFORE implementation; required, not optional)

```
UNIT
  PathService normalization (unicode/IDN-ish input, PER-SEGMENT collapse, length, rejects); reserved
  registry membership incl. boot-assert invariants; lifecycle transition table (every legal +
  illegal edge); sanitizer corpus (section 20 vectors); permission registry shape (codes match
  naming convention, module tags); media validation matrix (extension x mime x fake-file
  permutations); revision immutability guard (section 11 layers 1-2); homepage singleton invariant;
  ULID generation.
FEATURE
  page CRUD happy/denied; publish/unpublish/republish/archive; scheduled publish executor (Q32);
  rollback-by-copy; PATH RENAME leaves a RESERVED redirect and 301-resolves to the owner's new
  current path; 404 for retired; Article/NEWS classification flows; upload intake (valid png/jpg/pdf
  + rejected .php, .svg, mismatched mime, oversized, dimensions); media archive blocked while an
  ACTIVE reference exists then allowed; admin listing pagination/filters; media token resolution in
  public render payload; homepage designation + HomepageContentResolver returns the published
  payload / null when undesignated or unpublished / null when the designee is not PUBLISHED.

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
       homepage_designation_cleared on the archive event.
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
       revision is rejected (service guard + model guard, tested separately).
    R4 direct bypass attempt via Revision::query()->update([...]) and via DB::table('cms_content_revisions')
       payload write -> rejected by the model/guard layer where it passes through Eloquent; the
       raw-SQL path is asserted as OUT OF SCOPE for application enforcement (documented boundary,
       not a claimed defense).
    R5 scheduled replacement uses the same publication transaction and produces the same
       SUPERSEDED transition as a manual one (asserts equivalence, not just success).
    R6 lifecycle transition through the service is allowed while the same field change attempted
       as a payload edit is not (proves the payload/lifecycle split is real, not cosmetic).
    R7 revision rows are never deleted by any flow, including archive (assert count unchanged).
  MEDIA (IMP005-SPEC-05 / IMP005-SPEC-06)
    M1 an asset referenced ONLY by og_image_asset_id (never embedded in any body) CANNOT be
       archived while the referencing content is live, and CANNOT be purged — closes the exact
       blind spot the body-token scan had.
    M2 an asset referenced only by a SUPERSEDED (historical) revision CANNOT be physically purged,
       while being logically archivable — asserts the historical-rendering guarantee.
    M3 attach vs archive/purge race both directions: archive-then-attach -> attach rejected
       media_asset_not_attachable; attach-then-archive -> archive rejected media_referenced.
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
       media.purged, homepage.assigned, path.released, the four added/renamed by this remediation).
    A2 media metadata update emits content.media.updated and NOT content.media.uploaded.
    A3 homepage assignment emits exactly one content.homepage.assigned with the correct operation;
       a clear caused by an archive emits NO standalone homepage event.
    A4 a rename emits exactly ONE published event carrying previous_path + path (no companion
       path.claimed/path.renamed event exists to double-emit).
    A5 every NON_CRITICAL content event forced-failure -> business COMMITS and an
       `audit_write_failed` report is observable (asserts REPORTED, not merely "did not throw").
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
    M3, M4, M6                (attach-vs-archive; recheck-under-lock; purge retry)
    R1, R5                    (concurrent publication / scheduler-vs-manual serialization)
    every §26 concurrency-matrix row's test
  The following must pass on BOTH engines (deterministic constraint checks, no concurrency):
    H1b, P2, P3, P5, P6, R2, R3, R4, R6, R7, M1, M2, M5, M7, M8, and all S/T tests.
  MySQL-specific emphasis additionally: CHECK constraint enforcement, STORED generated-column
  uniques (active_path / active_current_owner / active_draft_*), utf8mb4 per-segment transliteration
  edges, gap/next-key locking behaviour around INSERT-on-duplicate.
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
       filenames reach disk; referenced-media ARCHIVE blocked and history-referenced media never
       purged; cleanup scheduler reclaims orphan files and unreferenced archived assets.
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
       check-then-act on media or paths outside a held lock.
AC-24  Cleanup: orphan-file case A and unreferenced-asset case B are separately implemented and
       separately tested; purge never precedes its own reference recheck; filesystem failure never
       loses retry evidence; the cleanup principal holds exactly content.archive and nothing more.
```

## 33. Definition of Done (IMP-005)

```
Standard DoD (docs/00-governance/DEFINITION-OF-DONE.md) plus:
[x] All five HD-IMP005-01..05 FINAL / LOCKED by Human and materialized; open decisions: 0
[x] Codex Pass-0 findings IMP005-SPEC-01..10 all PATCHED (none self-marked RESOLVED); disposition
    recorded in docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md
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
Task:      IMP-005 SPECIFICATION REMEDIATION PASS 1 (documentation only — no code, no migrations,
           no tests, no IMP-006 work)
Spec state: PATCHED (IMP005-SPEC-01..10) — PENDING CODEX RE-AUDIT
Remediation record: docs/audits/IMP-005-SPECIFICATION-REMEDIATION-1.md
IMP-005 implementation: NOT AUTHORIZED
Merge / push: NOT AUTHORIZED
```
