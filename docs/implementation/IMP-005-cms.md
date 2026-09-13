# IMP-005 — CMS (Content Experience)

## Status

```
Stage:                        IMP-005 — CMS
Document State:               SPECIFICATION — HUMAN DECISIONS RESOLVED (Q29-Q33) —
                              READY FOR INDEPENDENT SPECIFICATION REVIEW
Implementation Authorization: NOT GRANTED
Repository Baseline:          master @ f1aef3d59c2f5fa9361890bed8afa138f134680f
                              (IMP-000..IMP-004 FINAL / LOCKED — see
                              docs/audits/IMP-004-FINALIZATION.md and prior finalization records)
Human Decisions:              HD-IMP005-01..05 answered FINAL / LOCKED by the Human Authority and
                              materialized as Q29-Q33 in docs/01-requirements/HUMAN-DECISION-REGISTER.md
                              — this specification is patched to reflect those answers (section 25)
Human Decisions Required:     0
OPEN HUMAN DECISIONS:         0
This document changes:        NO code, NO migrations, NO routes, NO tests, NO production config.
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
  mediaTokenResolver -> maps embedded media ULID tokens in content to current public URLs
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
  A. System page            — route + rendering owned by code (auth, portals, admin, later
                              campaign detail). CMS cannot create, replace, override, or wrap it.
  B. CMS-managed page       — identity exists ONLY in cms_pages; path claimed at runtime through
                              the catch-all resolver registered LAST in web routes.
  C. Content in system page — CMS data injected into a system page (e.g. a later "about blurb"
                              on a campaign page). Mechanism deferred: no approved requirement
                              yet (section 16); when it exists it flows business-domain -> code
                              -> view, never CMS overriding a system route.
  D. Fully dynamic page     — B rendered later through IMP-006 templates.
```

Hard guarantees (each test-enforced, section 29):

```
- Route registration order: the managed-content catch-all is registered after every system route
  and can only ever shadow nothing.
- Reserved-prefix validation: page slugs (including nested paths) may never begin with a reserved
  first segment (section 14). The reserved set is a code registry (single source), seeded from
  MASTER-REQUIREMENTS §3 prefixes + IMP-002 auth routes + framework paths (storage, up,
  favicon.ico, robots.txt, sitemap.xml) — validated at write time, not merely UI-hinted.
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
  MediaAsset      uploaded content media                      -> cms_media_assets
  SlugHistory     redirect-on-slug-change (section 14)        -> cms_slug_history
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
- Content BODY + SEO meta live on the revision (immutable once published), so what the public saw
  at any moment is reconstructable (DB invariants' historical reproducibility principle applied to
  content; CMS revisions are NOT financial records and carry no ledger semantics).
- Exactly ONE active DRAFT revision per owner at a time (mutable); zero or one PUBLISHED pointer.
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

## 11. Revision model

```
Content identity      cms_pages / cms_articles row (ULID public id)
Revision identity     cms_content_revisions row (BIGINT internal id + per-owner revision_no)
Draft revision        one mutable active DRAFT revision per owner
Published revision    the row identity.published_revision_id points at; IMMUTABLE
                      (published revisions are never edited — editing a published page means
                      creating a new DRAFT revision, then publishing it)
Editor / timestamp    created_by_principal_id, updated_by_principal_id (FK -> principals, IMP-003),
                      created_at/updated_at
Change history        revision rows (append-only for published) + canonical audit events (section 12)
Rollback              creates a NEW revision copying the chosen historical revision's content,
                      then follows the normal publish path — history is never rewritten, deleted,
                      or pointer-regressed silently (append-only discipline honored without
                      borrowing ledger semantics)
```

Replace-semantics (publish): one transaction — previous PUBLISHED row stays as-is, gets
`state = SUPERSEDED`; new row flips DRAFT -> PUBLISHED; identity pointer moves; audit appended.
Published revision `state` values: `PUBLISHED | SUPERSEDED` (plus `DRAFT` while active draft).

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

CMS event classification (ENGINEERING CHOICE applying Q26/IMP-004):

| event_type (v1) | crit. | strategy | subject | justification / failure consequence |
|---|---|---|---|---|
| `content.page.created` | NON_CRITICAL | neither | page | Draft creation changes no authority; commit may survive reported audit loss. |
| `content.page.updated` | NON_CRITICAL | neither | page | Draft edits preserve public history; no security/governance authority mutation. |
| `content.page.published` | NON_CRITICAL | neither | page | Generic public content grants no backend authority; revision evidence persists, so reported audit loss need not block publishing. |
| `content.page.unpublished` | NON_CRITICAL | neither | page | Ordinary content withdrawal changes no authority; audit outage must not prevent withdrawal. |
| `content.page.archived` | NON_CRITICAL | neither | page | Terminal state preserves identities/revisions; it does not destroy security or financial evidence. |
| `content.article.created` | NON_CRITICAL | neither | article | Editorial draft creation including NEWS has no authority consequence. |
| `content.article.updated` | NON_CRITICAL | neither | article | Draft/classification edits retain history; classification is not authorization. |
| `content.article.published` | NON_CRITICAL | neither | article | Editorial publication including NEWS has the same non-authority consequence as Page. |
| `content.article.unpublished` | NON_CRITICAL | neither | article | Editorial withdrawal can safely commit during reported audit failure. |
| `content.article.archived` | NON_CRITICAL | neither | article | Retains identity/history; terminal content state alone is not security-critical evidence. |
| `content.media.uploaded` | NON_CRITICAL | neither | media asset | Validated public media intake grants no authority; validation remains fail-closed independently. |
| `content.media.deleted` | NON_CRITICAL | neither | media asset | Only unreferenced public CMS media is removed; row remains and sensitive/evidentiary files are outside CMS. |
| `content.page.schedule_updated` | NON_CRITICAL | neither | page | Authorized content intent/source persists in the row even if audit fails; creates no principal authority. |
| `content.article.schedule_updated` | NON_CRITICAL | neither | article | Same bounded durable intent including NEWS; reported audit loss can be accepted. |
| `content.page.schedule_expired` | NON_CRITICAL | neither | page | Consumes an expired window without disclosure or security/authority mutation. |
| `content.article.schedule_expired` | NON_CRITICAL | neither | article | Same expired-window handling for Article, with no separate News authority. |

All listed content mutations may commit on reported NON_CRITICAL audit persistence failure.
Public visibility and terminal state alone do not justify CRITICAL inflation. Security-critical
unauthorized attempts retain `security.authorization.denied`, CRITICAL/DENIAL_DURABLE: evidence
concerns attempted unauthorized authority use; DENY stays DENY on audit failure. IMP-004 is unchanged.
Registry actor constraints: Human for authoring/schedule_updated; Human or System for publish/
unpublish and media cleanup; System for schedule_expired. No menu or separate News events.

Explicitly NOT registered: `cms.page.deleted_or_archived` as a hard-delete event (hard deletes of
managed content are forbidden, section 27); `content.revision.*` beyond publish flows (revision
creation is covered by created/updated; publication is the trust moment); content *view* telemetry
events (no requirement; avoids surveillance noise); page/revision "restored" (ARCHIVED is terminal).

Per-event definition template (all registry-owned axes must be present; example for
`content.page.published`):

```
event_type            content.page.published
event_version         1
criticality           NON_CRITICAL
persistence_strategy  neither (null; IMP-004 non-critical semantics)
visibility_class      general   (read requires audit.read; no financial refs — never set those fields)
subject_type          cms_page  (subject_id = page BIGINT id; not nullable)
actor kinds           human principal (authenticated admin actor) OR system principal for the
                      Q32 scheduler-executed transition (see "Scheduled transition attribution"
                      below); kinds fixed by the registry entry, never by the caller
required metadata allow-list:
    revision_id (int)  from_status (string)  to_status (string)  slug (string)  is_homepage (int 0/1)
    schedule_version (int), scheduled_at (UTC string), scheduled_revision_id (int),
    publish_at/unpublish_at (UTC strings where applicable), scheduled_by_principal_id (int; required for scheduled transitions)
    — content body / media bytes are NEVER metadata; hard-prohibited-key rule applies unchanged
assurance at write    STANDARD unless security policy classifies publish as high-risk later
                      (AUTHENTICATION-ASSURANCE's enumerated high-risk categories do not include
                      content publishing; do not force ELEVATED without a policy source)
source_event_id       omitted (local producer, like the 28 migrated events — no external producer
                      identity to deduplicate against)
```

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

Non-critical persistence follows IMP-004 §"Non-Critical Events" exactly (best-effort, logged as
`audit_write_failed`, never queued, never blocks the authoring flow).

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
purpose            managed page identity + lifecycle
pk                 id BIGINT UNSIGNED AUTO_INCREMENT
ulid               CHAR(26) UNIQUE NOT NULL (public identifier)
columns            title (string 255), status (string 16: DRAFT|PUBLISHED|RETIRED|ARCHIVED),
                   latest_draft_revision_id (FK cms_content_revisions, nullable, RESTRICT —
                     draft rows are archived with the page, never orphan-deleted, so RESTRICT
                     holds for pointer columns),
                   published_revision_id (FK, nullable, RESTRICT),
                   slug (string 191, normalized path, NULL allowed only for a published homepage
                     assigned to '/' — see is_homepage),
                   is_homepage (TINYINT bool default 0) + generated column homepage_uniq
                   (nullable, = id WHEN is_homepage=1) UNIQUE — at most one homepage, enforced by
                   DB on both engines (unique index ignores NULLs),
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
unique             (slug) among non-ARCHIVED rows via DB: enforced with a nullable generated
                   "active_slug" column (= slug unless status=ARCHIVED) + UNIQUE index, so an
                   archived page frees its slug while keeping history — MySQL 8 & SQLite 3.35+
                   both support this; validated in the §29 matrix
indexes            (status), (updated_at), (publish_at, id), (unpublish_at, id), active_slug unique (above)
soft-delete        NONE — ARCHIVED status is the lifecycle; no deleted_at duplication
security           ULID used in URLs/admin payloads; BIGINT id stays internal (IDOR surface reduced)
```

### `cms_articles`
```
Same shape as cms_pages (title, status, pointers, scheduling timestamps/source/version fields
and due indexes; is_homepage/homepage_uniq absent; slug unique among non-ARCHIVED
scoped to the article namespace: active_slug stores '<article_prefix>/'.slug with prefix from
config — prefix itself must avoid reserved first segments at config boot).
extra columns:   excerpt (string 511, nullable), published_on (date nullable) — display metadata,
ENGINEERING CHOICE minimal set.
```

### `cms_content_revisions`
```
purpose            versioned content body + SEO meta; append-only once PUBLISHED/SUPERSEDED
pk                 id BIGINT
owner              page_id (FK cms_pages RESTRICT, nullable) XOR article_id (FK cms_articles
                   RESTRICT, nullable); CHECK (page_id IS NULL) <> (article_id IS NULL)
                   — strong referential integrity kept with real FKs (DB-ARCHITECTURE) instead of
                     soft polymorphic (type,id) pair; SQLite enforces CHECK as well
columns            revision_no (BIGINT, per-owner increasing), state (DRAFT|PUBLISHED|SUPERSEDED),
                   title (string 255 — versioned: titles change with revisions),
                   article_type (string 16; ARTICLE|NEWS for Article, NULL for Page),
                   CHECK: Page owner => NULL; Article owner => ARTICLE or NEWS; new Article
                   drafts default ARTICLE, service + DB validation rejects unknown values,
                   slug (string 191; requested canonical slug snapshot, validated by SlugService;
                     publication moves identity.slug atomically; frozen with a scheduled draft),
                   body_html (LONGTEXT, sanitized per section 20),
                   meta_title (string 255 null), meta_description (string 511 null),
                   og_title (string 255 null), og_description (string 511 null),
                   og_image_asset_id (FK cms_media_assets RESTRICT null),
                   no_index (TINYINT bool default 0),
                   created_by_principal_id FK, updated_by_principal_id FK
unique             (page_id, revision_no) / (article_id, revision_no);
                   single-active-draft: generated col active_owner (owner col value WHEN
                   state=DRAFT else NULL) pair-unique — validated on both engines, plus
                   service-level guard in the mutation transaction
indexes            (state), (article_id, state)
immutability       application-enforced: PUBLISHED/SUPERSEDED rows reject update/delete (model-level
                   guard mirroring the AuditRecord immutability pattern — NOT a DB trigger;
                   documented as application invariant + regression tests, consistent with
                   IMP-004's approach); revisions are NEVER deleted by any v1 path
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
                   status (ACTIVE|ARCHIVED), uploaded_by_principal_id FK principals RESTRICT,
                   archived_at nullable
indexes            (sha256), (status), (mime_type), (uploaded_by, archived_at)
deletion           archive-only row lifecycle (section 27); physical purge separate, never FK-linked
```

### Article classification (Q33; minimal engineering representation)

Article exposes `article_type` through its selected revision. Public listing/filtering uses only
the published revision's ARTICLE/NEWS value; draft classification cannot change public listings.
News shares Article revisions, publication, authorization, audit, slugs, scheduling, lifecycle
and SEO. No categories/pivot, generic taxonomy engine, News table or NewsService is required.
Additional classification values need a justified specification/change-control update. This
unification covers generic editorial content only; classification carries no security authority.

### `cms_slug_history`
```
purpose            preserve inbound links when a page/article slug changes (section 14)
pk                 id
columns            old_path (string 191 UNIQUE among active rows — same generated-nullable trick),
                   owner page_id/article_id FK RESTRICT (exactly one, CHECK),
                   from_revision_id FK RESTRICT, active (TINYINT), replaced_at nullable
rules              rows are written ONLY by the publish/slug-change services (system-written —
                   never user-supplied old paths); resolution follows the CURRENT published slug of
                   the owner (301); when owner is RETIRED/ARCHIVED the history row deactivates and
                   the old path 404s (no dangling redirects to non-content)
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

## 14. Slugs & routes

```
Normalization (deterministic, code-owned service):
  lowercase; trim; Unicode NFKD -> ASCII transliteration (fallback: drop non-ASCII);
  [^a-z0-9]+ -> '-'; collapse/repeat-strip hyphens; max 191 segments total (fits unique-index
  budget); reject-only (no silent "did you mean" auto-save): if result is empty or collides,
  the create/update returns 422 with the suggested alternative shown by the client.
Uniqueness scope:
  pages — global across all active (non-ARCHIVED) pages (single root domain => one global path
  namespace); articles — global within the configured article prefix; no locale uniqueness scope;
  slug_history old_paths — global among active rows.
  DB-enforced per section 13 generated-column uniques; service re-checks inside the transaction.
Reserved (NEVER claimable, code registry, validated on write AND asserted at boot):
  all MASTER-REQUIREMENTS §3 prefixes (admin, api, donor, fundraiser, partner, campaign, zakat,
  wakaf, fidyah, qurban) + IMP-002 routes (login, register, logout, mfa, password, reset-password,
  invitations, verify-email, account, dashboard) + framework/ops (storage, up, _ignition, build,
  favicon.ico, robots.txt, sitemap.xml) + HTTP-method fragments (api/v1). Adding system routes in
  later stages REQUIRES updating the same registry (documented obligation).
Nested page paths: slug may contain '/' segments (e.g. programs/education); every first segment
  checked against reserved set; depth limit 3 (ENGINEERING CHOICE, validation only).
Slug change on published content: publishing a new revision whose slug differs atomically
  (same transaction): moves identity slug, writes cms_slug_history row for the old path, appends
  audit. Redirect resolution: 301 to the CURRENT published URL of the owner while active;
  410/404 otherwise. No user-authored redirect targets (open-redirect class eliminated by
  construction — targets are always internal, system-computed from published content).
Publication behavior: unpublished content resolves 404 (never 410/500 disclosure); draft/access
  control is server-side only.
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

  PageService / ArticleService   create/update identity + active draft revision (guard:
                                 permissions via policies, slug validation, sanitization hook)
  PublicationService             publish / unpublish / retire / archive / republish; revision swap
                                 transaction (section 26); scheduled-transition executor invoked by
                                 the Laravel Scheduler (Q32; console command
                                 content:run-scheduled-transitions, running as the cataloged
                                 content.scheduler System Principal — section 12 attribution);
                                 homepage assignment here (singleton guard)
  RevisionService                revision reads/history listing; rollback-by-copy (creates new
                                 draft revision, never rewrites history)
  SlugService                    normalization + reserved-registry check + uniqueness check +
                                 slug-history write coordination (used by the three services above;
                                 no other service composes slugs)
  MediaService                   upload intake (validation pipeline section 19), archive,
                                 reference queries (reference = media ULID token present in
                                 published/draft body_html — token scan service-local, no cached
                                 mutable counter column, per "no mutable balance" house principle)
  ContentSanitizer               input body -> allow-listed body (section 20); pure, unit-tested
  ContentResolverService         public read path: path -> published page (or slug_history 301)
                                 -> presentation-neutral payload for web/theme contract
Controllers (thin): Http/Controllers/Admin/Content/* (Inertia, fixed backoffice) and
Http/Controllers/Content/PublicContentController (catch-all resolver + media token resolution).
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
Media deletion: blocked while ANY non-ARCHIVED page/article (draft or published) body references
  the media token (query at delete time — authoritative check inside the delete transaction with
  row locks on referencing identity rows); on delete success (NON_CRITICAL audit
  content.media.deleted) row -> ARCHIVED, file purged later by the cleanup scheduler.
Orphan cleanup: scheduled command (cron, shared-hosting compatible) archives rows whose owner
  content is ARCHIVED/absent AND no references remain, then physically deletes files after a
  configurable operational grace period (default 7 days; a config value, explicitly NOT a legal
  retention statement).
Private files: OUT — all v1 CMS media is public marketing content; receipt/compliance/identity
  documents belong to Documents/Identity domains which already carry their own controlled-delivery
  obligations (SECURITY-ARCHITECTURE: no permanent public URLs for sensitive files — CMS simply
  holds no sensitive files in v1).
```

## 20. Rich text & content sanitization (stored-XSS wall)

```
Content model: body_html — semantic HTML subset produced by an admin editor (editor library itself
  OUT of scope/undecided — not named here; any editor must round-trip through the same server
  sanitizer). No page-builder block JSON in v1 (section 4); media embeds appear in the body ONLY
  as opaque tokens: data-media="ULID" placeholder elements — resolved to URLs at render by
  ContentResolverService (deterministic references, no persisted public URL inside content, slug
  independence).
Server-side sanitization on EVERY write path (create/update/rollback-copy/import-none-exists),
  fail-closed — an unparseable body is rejected, never stored raw:
  allowlist tags:  p br h1-h4 ul ol li a img strong em blockquote table thead tbody tr th td
                   figure figcaption code pre hr span(div only if class-stripped-safe)
  allowlist attrs: a: href(title, target); img: src(data-media token), alt, width, height;
                   global: class (from a fixed admin-side list), id (slug-normalized, optional)
  href schemes: https, http, mailto, tel; RELATIVE paths starting '/' only if not reserved-
  prefixed; REJECT javascript:, data: (except sanitizer-internal placeholder), file:, vuln schemes;
  EVENT ATTRIBUTES (on*) stripped/rejected; <script> <style> <iframe> <object> <embed> <form>
  <input> <base> <link> <meta> <svg> <math> rejected outright (no iframe embeds in v1 —
  malicious-iframe vector eliminated at storage layer).
  Length caps (config): body 200 KB; final rendered output must never echo raw client HTML.
Defense in depth at render: templates escape non-sanitized fields (titles/captions are PLAIN TEXT —
  sanitizer HTML is only ever stored in body_html); a Content-Security-Policy baseline is a
  deployment obligation noted for the website stage (documented, not implemented here).
Implementation mechanism: dedicated well-maintained sanitizer library (e.g. HTMLPurifier class —
  pure PHP, shared-host compatible, no infra) vs native DOMDocument allowlist walker — chosen at
  implementation with CHANGE-CONTROL dependency-justification evidence either way; the SECURITY
  review gate treats this as the highest-risk component and requires the §29 XSS test corpus to
  pass identically on both engines.
```

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
content.archive          archive (terminal lifecycle) + media delete/replace-with-removal —
                         DESTRUCTIVE (separate from publish)
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
Specification: READY FOR INDEPENDENT SPECIFICATION REVIEW
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
Named transactional flows:
  publish/republish: lock identity row (lockForUpdate) -> guards -> revision state flips + pointer
    swap (+ slug move + slug_history write, all-or-nothing) -> audit append -> commit.
  unpublish/archive/delete-media: lock identity -> state/pointer/archive -> history deactivation
    (archive) or reference re-check (media delete, locking referencing rows in stable
    id-ascending order — deadlock-safe sequencing mirrors IMP-004 "Transaction Placement") ->
    audit -> commit.
  homepage assign: lock both rows (old + new) in id order -> flip flags -> commit.
  schedule configuration/execution: lock identity -> version/revision + authorization guards ->
    source/deadlines or due transition/consumption -> canonical audit -> commit (section 10).
  upload: media row insert + audit -> commit (file written to disk BEFORE the row; a failed
    transaction leaves an orphan file which the cleanup scheduler reclaims — documented, bounded).
Concurrency conflicts (two tabs publish same page): last-writer rejected on revision-state
  mismatch (optimistic check: expected current revision id from request; stale => 409).
```

## 27. Deletion / archive semantics

```
Pages/articles: NO hard delete in v1 (LOCKED-adjacent: "Preserve financial history" is financial;
  applied to content by the same no-destruction-of-history house principle + the immutability of
  published revisions). ARCHIVED is the terminal state; archived rows + their revisions stay.
Revisions: never deleted (append-only, section 11).
Media: row ARCHIVED; physical file removed by the cleanup scheduler after the operational grace
  period (config); deleting referenced media is rejected (section 19).
slug_history: deactivated, not deleted (freeing the old path is allowed because the ACTIVE-path
  unique index ignores inactive rows; history rows persist for redirect auditability).
Restoration: from RETIRED only (re-publish); ARCHIVED is terminal by design — no invented
  "restore audit chain".
Legal retention durations: none asserted anywhere in this spec (Q17 governs; a future retention
  policy stage may add purge under Q28-style governed evidence flow — CMS content purge is NOT
  built in v1).
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
                           (published_revision_id, is_homepage, status) are NEVER mass-assignable —
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
  SlugService normalization (unicode/IDN-ish input, collapse, length, rejects); reserved registry
  membership incl. boot-assert invariants; lifecycle transition table (every legal + illegal edge);
  sanitizer corpus (section 20 vectors); permission registry shape (codes match naming convention,
  module tags); media validation matrix (extension x mime x fake-file permutations); revision
  immutability guards; homepage singleton rule; ULID generation.
FEATURE
  page CRUD happy/denied; publish/unpublish/republish/archive; scheduled publish executor
  (Q32); rollback-by-copy; slug change writes history + 301 resolution; 404 for retired;
  Article/NEWS classification flows; upload intake (valid png/jpg/pdf + rejected .php, .svg, mismatched
  mime, oversized, dimensions); media delete blocked-while-referenced then allowed; admin listing pagination/filters; media token resolution in public render payload.
AUTHORIZATION (mandatory matrix per DEFINITION-OF-DONE "Mandatory RBAC Tests", applied to each
  content.* capability): authorized->ALLOW; unauthenticated->DENY; wrong permission->DENY;
  correct permission wrong scope->DENY; wrong resource state->DENY; missing identity.active->DENY;
  unrelated Partner/Fundraiser context ->DENY; SUPER ADMIN WITHOUT explicit content.publish
  grant attempt publish -> DENY (proves no evaluator bypass).
SECURITY
  stored-XSS payloads (event handlers, javascript: hrefs, svg/script/iframe bodies, polyglot
  files); protected-route collision create (slug 'admin', 'api/v1', 'campaign/x', 'login'...) ->
  422; attempt direct storage URL of archived media; IDOR (ULID enumeration, foreign draft ids);
  publish without permission via forged request payloads (pointer/status fields stripped);
  CSRF absence -> 419; CRITICAL security denial forced-failure -> DENY remains DENY + error
  reported; every NON_CRITICAL content event forced-failure -> business SURVIVES + error logged.
AUDIT INTEGRATION (task §35)
  every authorized mutation -> exact registry event (event_type+version+criticality+metadata
  allow-list compliance) via canonical sink; every unauthorized attempt -> NO business mutation
  (+ security.authorization.denied where the IMP-004 denial path applies); criticality behaves
  per registry (no caller override); unregistered CMS event attempt -> hard error.
HUMAN DECISION COVERAGE
  editor with create/update but no publish cannot publish/schedule/reschedule/cancel; publisher
  within scope succeeds without reviewer queue/approval. Wrong scope is denied.
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
DATABASE MATRIX
  the whole suite on SQLite (default) AND MySQL 8.x (disposable test DB per house convention);
  MySQL-specific emphasis: CHECK constraint enforcement, generated-column unique tricks,
  concurrent publish (two connections -> one 409), utf8mb4 slug transliteration edges,
  row-lock concurrent schedule/manual-publication tests; no test may rely on SQLite-only semantics for any §13
  constraint.
REGRESSION
  permission seeder idempotency; audit registry counts (new entries additive-only, versions
  never edited); migration fresh-run on both engines.
```

## 30. Performance / observability notes

```
Expected volume is small (org-authored content). Required indexes per §13; resolver lookup is
one indexed path fetch (+ optional slug_history fetch only on miss). No caching layer mandated
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
       filenames reach disk; referenced-media delete blocked; cleanup scheduler reclaims orphans.
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
```

## 33. Definition of Done (IMP-005)

```
Standard DoD (docs/00-governance/DEFINITION-OF-DONE.md) plus:
[x] All five HD-IMP005-01..05 FINAL / LOCKED by Human and materialized; open decisions: 0
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
Task:      IMP-005 READINESS + IMPLEMENTATION SPECIFICATION (no code, no migrations, no tests)
Spec state: READY FOR INDEPENDENT SPECIFICATION REVIEW
IMP-005 implementation: NOT AUTHORIZED
```
