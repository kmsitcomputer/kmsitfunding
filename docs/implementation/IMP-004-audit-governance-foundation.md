# IMP-004 — Audit + Governance Foundation

## Status

READY (for independent Codex specification review — see "Implementation Ownership" below;
implementation itself is NOT authorized)

## Implementation Ownership

Per [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) —
no implicit/default model substitution is permitted; if the assigned model/owner is unavailable,
stop and report rather than substituting silently.

```
Stage Type:   NORMAL IMPLEMENTATION STAGE
```

```
Primary Implementation Owner:  Kimi K3
Primary Model:                 Kimi K3
Exact Model ID:                moonshotai/Kimi-K3
Execution Environment:         Command Code GOAT
Specialist Reviewer(s):        DeepSeek V4 Pro (deepseek/deepseek-v4-pro) — READ ONLY
Independent Formal Reviewer:   Codex (CODING: NO)
Human Approval Authority:      Human
Concurrent Editing:            PROHIBITED
```

IMP-004 is not one of the Mission-Critical Claude Stages (IMP-009/010/011/014/015/016/027) — the
GOV-MM-002 Claude Per-IMP Model Binding block does not apply to this stage.

**`IMP-004 IMPLEMENTATION IS NOT AUTHORIZED.`** Creation and Codex/Human approval of this
specification does not authorize Kimi K3 (or any model) to modify application code. Implementation
requires a later, separate, explicit Human statement equivalent to:
`Saya setuju. IMP-004 Implementation Authorized.` Approval of HD-IMP004-01/02/03 (Q26-Q28) must
not be read as that authorization — those decisions govern *what* IMP-004 must build; they do not
authorize *building* it.

---

## Objective

Establish ONE canonical, durable mechanism for security/governance audit evidence — replacing
`IdentityAuditLogger`/`RbacAuditLogger`'s deferred, log-file-only scaffolding — and a small set of
generic governance-evidence primitives that later domains' own governance concerns (policy
versioning, approval evidence) may build on. IMP-004 must not become an application-logging
framework, an observability platform, a financial ledger, a payment event store, a reconciliation
source of truth, an approval workflow engine, or a reporting/search or REST API implementation.

## Source Requirements

- [MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) — names `Audit` as a
  domain (no further detail supplied there).
- [MODULE-OWNERSHIP.md](../02-architecture/MODULE-OWNERSHIP.md) §16 "Governance & Platform
  Services" — owns `Audit, Privacy, Retention, Search, Reporting, Currency Governance`; Search +
  Reporting is separately assigned to IMP-026; Currency Governance has no evidence tying it to
  IMP-004 (see "Out of Scope").
- [HUMAN-DECISION-REGISTER.md](../01-requirements/HUMAN-DECISION-REGISTER.md) Q16
  (Configurable Privacy Model — mechanism only), Q17 (Configurable Retention Policy Matrix —
  mechanism only), **Q26, Q27, Q28** (this stage's own new decisions, governing failure
  semantics, read authorization, and retention/purge respectively).
- [docs/implementation/IMP-004-audit-governance-readiness.md](IMP-004-audit-governance-readiness.md) —
  the full repository-grounded readiness analysis this specification resolves.

## Architecture References

[DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md) (hierarchy),
[SECURITY-ARCHITECTURE.md](../04-security/SECURITY-ARCHITECTURE.md) /
[SECURITY-INVARIANTS.md](../04-security/SECURITY-INVARIANTS.md) (no audit-specific rule existed
prior to Q26-Q28 — this specification is now the authoritative Level 5 detail beneath them),
[DATABASE-ARCHITECTURE.md](../03-database/DATABASE-ARCHITECTURE.md) /
[DATABASE-INVARIANTS.md](../03-database/DATABASE-INVARIANTS.md),
[RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md),
[DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md),
[BUSINESS-AUTHORITY-MODEL.md](../05-rbac/BUSINESS-AUTHORITY-MODEL.md),
[AUTHENTICATION-ASSURANCE.md](../05-rbac/AUTHENTICATION-ASSURANCE.md).

## Scope

**In scope (canonical audit foundation):**

```
- Canonical audit event taxonomy (namespaced, versionable, registered — see "Event Taxonomy")
- Canonical AuditWriter/AuditSink persistence contract, replacing the log-channel scaffolding
- Actor attribution via canonical Principal (human/System/Integration), never raw users.id
- Subject/resource attribution (typed subject_type/subject_id)
- Criticality classification (CRITICAL default; explicit NON_CRITICAL opt-out per event)
- Transactional, fail-closed persistence for CRITICAL events (Q26)
- Safe, allow-list metadata serialization (F-02/§16)
- Append-only persistence; no update/delete surface in normal operation (Q28)
- Audit-read authorization foundation: Permission + Domain-Aware Scope (Q27)
- Basic query/filter foundation (actor, subject, event_type, date range, correlation ID) —
  backend only, no UI
- Migration of the 28 existing Identity + RBAC events onto the canonical sink (§"Existing Event
  Migration")
- request_id/correlation_id foundation (no current consumer requires it yet; built anyway per
  readiness §4.7/§SHOULD)
- Generic policy-version-evidence field/reference primitive (structural only; no policy engine)
```

## Out of Scope

```
- Approval workflow mechanics, routing, thresholds (Module 12 — a different, later module)
- Any Payment/Ledger/Commission/Withdrawal/Refund/Reconciliation business logic — Ledger remains
  the sole financial source of truth (AGENTS.md "Locked Decisions")
- Search/Reporting implementation and its UI (IMP-026)
- Any admin/audit-viewing UI (a later, separately-scoped stage may build one on top of the query
  foundation this spec defines)
- REST API surface (IMP-025)
- Currency Governance (no MODULE-OWNERSHIP/HDR/roadmap evidence ties it to IMP-004 specifically;
  flagged, not assumed — if Human intends it in scope, that requires an explicit decision, not an
  inference from this spec)
- Actual retention durations, actual privacy field-level rules (Q16/Q17 mechanism only)
- Cryptographic tamper-evidence / hash-chaining (not requested by any reviewed authority; "immutable
  through authorized application operations" only, not a stronger tamper-proof claim — §17)
```

## Affected Domains

Module 16 (Governance & Platform Services) — new. Consumers/dependencies: Module 1 (Identity,
IMP-002), RBAC (IMP-003) for actor/permission/scope reuse. Future consumers (not implemented
here): Modules 8/9/10/11/12/6 and IMP-009/010/011/014/015/016/017/023/027/030.

## Business Rules

Authoritative for this stage: **Q26, Q27, Q28** (see "Human Decision Register" above), plus the
pre-existing Q16/Q17 mechanism-level locks, plus every already-locked cross-cutting rule this
stage must not violate: `Ledger is accounting source of truth`, `Super Admin != automatic
Financial Authority` (both from `AGENTS.md`/`BUSINESS-AUTHORITY-MODEL.md`).

## Security Requirements

- **Authentication**: audit-emitting code runs in the context of an already-authenticated
  request/job/CLI invocation; IMP-004 does not add a new authentication mechanism.
- **Authorization (write)**: any code path may emit an audit event through the canonical
  `AuditWriter` — write access is not gated by a Permission (the mutation being audited is
  already authorized by its own domain's rules; the audit write is a side effect of an already-
  authorized action, never a separately-permissioned action in its own right, except at the
  meta-level of "can this code call the writer at all" which is an engineering/DI concern, not
  an end-user permission).
- **Authorization (read)**: full canonical formula per Q27 — see "Audit Read Authorization".
- **Scope**: [DATA-SCOPE-MODEL.md](../05-rbac/DATA-SCOPE-MODEL.md)'s existing taxonomy
  (`OWN, FUNDRAISER, PARTNER, CAMPAIGN, PROGRAM, FUND, BENEFICIARY_CASE, ASSIGNED_WORK,
  ORGANIZATION, GLOBAL_PLATFORM`) is reused as-is for audit-read scoping, keyed off each
  record's `subject_type`/`subject_id`.
- **Sensitive data**: allow-list metadata serialization is mandatory default (Q26/F-02, see
  "Redaction / Safe Serialization"); the enumerated secret categories in that section must never
  be persisted under any circumstance, defense-in-depth blacklist notwithstanding.

## Database Impact

See "Proposed Schema" below. No migration is created by this specification document itself —
migration files are an implementation-time artifact, written during IMP-004 implementation
(when authorized), not during specification.

## API Impact

None. No REST endpoint is added by this stage (owned later by IMP-025). Internal service
contracts only (`AuditWriter` interface, query service).

## UI Impact

None. No admin UI is built by this stage.

## Financial Impact

None directly. Hard boundary restated: `AUDIT != LEDGER`, `AUDIT != FINANCIAL CONSEQUENCE`,
`AUDIT != PAYMENT`, `AUDIT != RECONCILIATION`. An audit record may *reference* a future
financial resource/event by identifier, but audit is never an alternate financial source of
truth — Ledger remains sole authority for accounting facts.

## Idempotency (IMP004-SPEC-M06)

`source_event_id` exists so a **trusted producer** with its own stable source-of-truth identity
for an occurrence (e.g. a future webhook handler receiving a provider's own event ID, once
IMP-009+ exists) can safely call the canonical `AuditWriter` more than once for logically the
same occurrence — a legitimate retry — without producing a duplicate canonical row. It is:

```
- OPTIONAL: most events (including all 28 currently migrated Identity/RBAC events) have no
  natural external source identity and simply omit it.
- TRUSTED-PRODUCER-SUPPLIED: only the emitting service itself supplies it (from its own upstream
  source), never derived from unvalidated end-user request input.
- NORMALIZED: trimmed, case-preserved as supplied by the producer (case sensitivity is the
  producer's own concern — IMP-004 does not reinterpret it).
- BOUNDED: a fixed maximum length (an implementation-time parameter, not invented here as a
  specific byte count without evidence — mirrors "oversized metadata" being similarly deferred).
- NOT globally unique by itself — see uniqueness scope below.
```

**Uniqueness scope**: `UNIQUE (source_domain, source_event_id, event_type)` — never a bare
global-`source_event_id` uniqueness rule, because two entirely unrelated producers (or the same
producer emitting two different canonical event types from one upstream occurrence) could
otherwise collide on an identifier neither controls relative to the other. `source_domain` is a
new, small, registry-known string identifying the trusted producer namespace (e.g. `identity`,
`rbac`, and reserved for a future `payment_provider:<name>`-style value) — supplied by the
emitting service, not the end caller, exactly like `event_type` itself.

**Duplicate (legitimate retry) behavior**: given the SAME scoped key
(`source_domain` + `source_event_id` + `event_type`) submitted again for what the producer
asserts is semantically the same canonical audit emission, `AuditWriter` returns/references the
EXISTING canonical audit record — it does **not** create a duplicate row, and does **not** throw
merely because this is a legitimate retry.

**Conflicting reuse behavior**: given the SAME scoped key but an incompatible event
identity/immutable payload (e.g. the same `source_domain`+`source_event_id`+`event_type` but
materially different core attribution — a different `subject_id`, for instance — indicating the
producer reused an identifier incorrectly rather than legitimately retried), `AuditWriter`
**rejects** the write as an idempotency conflict (a distinct, clearly-named exception, never
silently overwriting the original row — audit rows are never updated, per "Immutability").

**Null behavior**: when `source_event_id` is null (the ordinary case for the 28 already-migrated
events), no uniqueness constraint applies at all — the composite unique index is defined so that
NULL values do not collide with each other (standard SQL NULL-distinct unique-index semantics,
already relied upon nowhere else problematically in this schema).

IMP-004 does not implement retry logic itself — it only ensures the schema/contract does not make
reliable future retry-safe integration impossible (readiness §20).

## Concurrency

Concurrent mutations each carry their own DB transaction (per Q26, for CRITICAL events); audit
rows are simple inserts, not updated in place, so there is no update-conflict surface. The
primary concurrency risk is duplicate-row creation under retry — addressed by the idempotency
key above, not by locking.

## Error Handling

Per Q26: for a CRITICAL event, `AuditWriter` failure propagates as an exception that the calling
service's own DB transaction is expected to be wrapping (mirroring the existing IMP-003 pattern
exactly) — `AuditWriter` itself does not open/manage the enclosing transaction; the calling
service does, exactly as `RolePermissionService::grant()` etc. already do today. For a
`NON_CRITICAL` event, `AuditWriter` failure is caught by the writer itself, does not propagate to
the caller, and is reported through the ordinary application error-logging path (never silently
swallowed with no trace at all) — see "Non-Critical Events".

## Tests Required

See "Required Test Plan" below (§ mirrors the originating instruction's §31-32 exactly).

## Acceptance Criteria

```
FUNCTIONAL:      all 29 canonical active/target events (28 migrated + 1 new denial event) persist
                 via the canonical AuditWriter with identical semantic content to today's
                 log-channel payloads, plus the 5 reserved catalog events registered but unemitted
SECURITY:        no audit record ever contains a hard-prohibited secret category (§Redaction)
AUTHORIZATION:   audit read enforces the full Q27 formula plus registry-derived visibility class
                 (§Registry-Derived Visibility Class); Super Admin has no unearned automatic access
DATA INTEGRITY:  no application code path can UPDATE or DELETE a persisted audit record absent an
                 authorized purge path; source_event_id idempotency behaves exactly per §Idempotency
ROLLBACK:        every CRITICAL event (including email_verified) proves forced-audit-failure
                 rollback; the denial event proves DENY always propagates regardless of its own
                 audit-persistence outcome, via an independent, transaction-surviving boundary
ACTOR INTEGRITY: pre-Principal cases (unauthenticated, pre_principal_system) never fabricate a
                 human actor and are unreachable for any event whose registry entry forbids them
REDACTION:       allow-list mechanism verified against a comprehensive negative-data test set
PRIVACY:         Q16 mechanism-level configurability is structurally present
DATABASE:        migrations apply cleanly on MySQL 8.x (disposable instance) with the same
                 evidence bar as IMP-003; SQLite regression suite passes
MYSQL:           fresh migration, constraint (including the composite source_event_id uniqueness),
                 and concurrency behavior independently verified on a disposable MySQL 8.x database
GOVERNANCE:      Codex independent re-audit passes with 0 BLOCKER/MAJOR before Human Stage Gate
SHARED HOSTING:  no new mandatory infrastructure dependency (the denial-event independent
                 connection is a second connection to the same database, not new infrastructure)
```

## Forbidden Changes

No change to: `Ledger is accounting source of truth`; any RBAC authorization formula element;
any IMP-002/IMP-003 already-tested transactional/authorization behavior (migration must be
behavior-preserving, see "Migration / Backward Compatibility"); Q1-Q25; any Locked Decision in
`AGENTS.md`.

## External Verification

Actual retention periods (Q17), actual privacy field-level rules (Q16), and any
external legal/compliance retention requirement are explicitly deferred — `EXTERNAL
VERIFICATION` per `AGENTS.md`'s "External Verification Rule" — this specification builds only
the structural capacity to apply such rules later, never invents the rules themselves.

## Definition of Done

Per [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md), plus the
IMP-004-specific criteria in "Definition of Done (IMP-004-Specific)" below.

---

# Detailed Specification

## Canonical Actor

Audit attribution uses the canonical `Principal` model from IMP-003 where one legitimately
exists, but **not every audit event has an authenticated human Principal at the moment it is
emitted** (IMP004-SPEC-M02) — an unknown-email failed login and the first Q25 bootstrap step both
occur with no resolvable canonical Principal. The specification distinguishes:

```
ACTOR PRINCIPAL       — a resolved, canonical App\Models\Rbac\Principal (human/system/
                         integration), used whenever one legitimately exists at emission time.
EXECUTION ORIGIN /
  CONTEXT             — a controlled, registry-defined, non-Principal attribution used ONLY for
                         the specific, explicitly registered cases where no canonical Principal
                         can legitimately exist yet — never a fallback for an ordinary
                         programming-error null, and never caller-suppliable as an arbitrary
                         string.
```

Schema (supersedes the earlier NOT NULL draft):

```
actor_principal_id     BIGINT unsigned, FK -> principals.id, NULLABLE
actor_principal_kind   ENUM/string: 'human' | 'system' | 'integration' | 'unauthenticated' |
                        'pre_principal_system'
                        ('human'/'system'/'integration' mirror App\Enums\PrincipalKind, reused
                        not reinvented; 'unauthenticated'/'pre_principal_system' are NEW,
                        audit-only values with no PrincipalKind equivalent — they never appear in
                        `principals.principal_kind`, only on the audit row)
execution_context      string, NULLABLE — REQUIRED (NOT NULL) precisely when
                        actor_principal_id IS NULL; NULL/unused when a real Principal is present
```

### Canonical Actor (Pre-Principal Cases)

```
actor_principal_kind = 'human' | 'system' | 'integration'
    -> actor_principal_id MUST be non-null (enforced by the writer/registry, not merely
       convention) — this is the ordinary case, covering every event except the two below.

actor_principal_kind = 'unauthenticated'
    -> actor_principal_id MUST be null.
    -> execution_context is a FIXED, registry-supplied constant identifying the unauthenticated
       entry point (e.g. "http:login_attempt") — never free-form caller input.
    -> Legitimate ONLY for an event that is itself evidence of a failed/incomplete
       authentication attempt (currently: identity.session.login_failed). An attempted
       identifier (e.g. the normalized email that was typed) MAY appear in that event's own
       allow-listed metadata as data-about-the-attempt — it is never promoted to
       actor_principal_id, because it is unverified.

actor_principal_kind = 'pre_principal_system'
    -> actor_principal_id MUST be null.
    -> execution_context is a FIXED, registry-supplied constant identifying the specific trusted,
       deterministic, operator-invoked execution path that legitimately runs before any
       canonical Principal can exist (currently: "cli:identity:bootstrap-super-admin", for
       identity.bootstrap.first_super_admin_completed — the Q25 first-bootstrap CLI command,
       which by definition runs before BridgeFirstSuperAdmin ever creates the first Principal).
       This is NOT a general-purpose "system did something" bucket — it is registered per
       specific, named, already-locked execution path, never opened up for arbitrary future use
       without its own registry entry.

Application/calling code NEVER supplies `execution_context` as a free string parameter — it is
resolved by the AuditWriter/registry from a small, closed, per-event-registration set of allowed
values (mirroring how `event_type` itself is validated against the registry). A caller cannot
invent a new execution_context value merely by passing one — only a registered event that
declares actor_principal_kind IN ('unauthenticated','pre_principal_system') may use one, and only
its own pre-declared constant.

No caller-supplied arbitrary actor identity is ever trusted: for the ordinary
human/system/integration cases, `actor_principal_id` is always container/context-resolved (the
authenticated Principal, the current job's System Principal, etc.), never taken from unvalidated
request input — this restates and extends IMP-003's already-proven "stale/forged caller model"
defense (see "Security Negative Tests").
```

For human actors, `actor_principal_id` is sufficient to resolve the acting `User` at read time
via the existing `principals.human_user_id` FK — no denormalized name/email snapshot is stored on
the audit record itself (avoids unnecessary PII duplication; see "Historical Durability" for the
one exception). For System/Integration actors, the same pair deterministically identifies the
originating scheduled job/queue/integration identity — exactly the shape already proven correct
by IMP-003's `non_human_principal_deactivated` event (`kind` + catalog ID + `principal_id`).

No impersonation/delegation field is added — no approved impersonation capability exists in any
reviewed architecture document; inventing one here would be exactly the kind of policy invention
this specification must not perform. No new authority/permission semantics are created by the
`unauthenticated`/`pre_principal_system` actor kinds — they exist solely to make attribution
deterministic and honest; they never confer, imply, or check any authorization outcome.

## Event Taxonomy

Canonical event identifiers are **dot-namespaced, lowercase, stable, versioned**:

```
<domain>.<subject>.<action>[.<qualifier>]

Examples (existing events, renamed onto the canonical taxonomy — see migration mapping below):
  identity.user.created
  identity.session.login_succeeded
  identity.session.login_failed
  rbac.role.assigned
  rbac.role_permission.granted
  rbac.principal.deactivated
```

Rules:

```
- Every canonical event identifier MUST be registered in a single, explicit registry (an enum or
  equivalent registry class, mirroring the existing App\Services\Rbac\PermissionRegistry
  pattern) before it can be emitted — an unregistered event_type is a hard error at write time,
  never silently accepted as an arbitrary free-form string.
- event_version is a small integer, starting at 1, incremented only when an event's REQUIRED
  metadata shape, criticality, or visibility classification changes incompatibly (additive
  optional fields do not require a version bump). A version increment always creates a NEW,
  additional registry entry for `(event_type, new_version)` — it never edits the existing entry
  for `(event_type, old_version)` in place. A record written under an old version remains
  interpretable exactly as it was at write time (its criticality, metadata contract, and
  visibility classification are permanently pinned to the version it was written with — see
  "Historical Reproducibility of Visibility"); a future version never rewrites, reinterprets, or
  reclassifies a historical record written under a prior version. Do not bump the version merely
  because implementing code changed — only an incompatible CONTRACT change (shape/criticality/
  visibility) warrants a new version.
- Namespace segments are reserved per domain (identity, rbac, security, admin — populated now;
  approval, financial, payment, ledger, content, campaign, donation, partner, fundraiser,
  beneficiary, distribution, integration, system — reserved for later domains to register their
  own events into, NOT pre-populated by IMP-004).
- IMP-004 does not invent events for domains that do not exist yet.
```

## Existing Event Migration

**Verified repository inventory (IMP004-SPEC-M01)** — counted directly from every
`->record('...')` call site under `app/Services/Identity/`, `app/Http/Controllers/Auth/`,
`app/Console/Commands/BootstrapSuperAdmin.php`, `app/Services/Rbac/`, and
`app/Console/Commands/BridgeFirstSuperAdmin.php`:

```
Identity existing events            = 19
RBAC existing events                = 9
-----------------------------------------
Existing runtime inventory          = 28

New denial event (F-01)             = 1
-----------------------------------------
Canonical active/target events      = 29

Reserved-but-deferred catalog events = 5
-----------------------------------------
Total registry entries (incl. reserved) = 34
```

All 28 currently-implemented Identity + RBAC events, mapped onto the canonical taxonomy. Every
mapped event's `actor_requirement` is `actor_principal_id + actor_principal_kind` per "Canonical
Actor" above (omitted per-row for brevity, except where "Canonical Actor (Pre-Principal Cases)"
below overrides it); "Required Metadata" lists only fields beyond that pair and
`subject_type`/`subject_id`.

### Identity (19 events, IMP-002)

| Existing Event | Canonical Event | Criticality | Failure Semantics | Subject | Required Metadata |
|---|---|---|---|---|---|
| `identity_created` | `identity.user.created` | CRITICAL | fail-closed | `user`/user id | — |
| `self_registration_completed` | `identity.user.self_registered` | CRITICAL | fail-closed | `user` | — |
| `invitation_issued` | `identity.invitation.issued` | CRITICAL | fail-closed | `invitation` | — |
| `invitation_revoked` | `identity.invitation.revoked` | CRITICAL | fail-closed | `invitation` | — |
| `invitation_accepted` | `identity.invitation.accepted` | CRITICAL | fail-closed | `invitation` | — |
| `login_succeeded` | `identity.session.login_succeeded` | NON_CRITICAL | fail-open + report | `user` | — |
| `login_failed` | `identity.session.login_failed` | NON_CRITICAL | fail-open + report | `user` (nullable — unknown email attempts) † | `reason` |
| `logout` | `identity.session.logout` | NON_CRITICAL | fail-open + report | `user` | — |
| `password_changed` | `identity.credential.password_changed` | CRITICAL | fail-closed | `user` | — |
| `password_reset_completed` | `identity.credential.password_reset_completed` | CRITICAL | fail-closed | `user` | — |
| `email_change_requested` | `identity.email.change_requested` | CRITICAL | fail-closed | `user` | — |
| `email_change_completed` | `identity.email.change_completed` | CRITICAL | fail-closed | `user` | — |
| `email_change_conflicted` | `identity.email.change_conflicted` | CRITICAL | fail-closed | `user` | `conflict_reason_code` |
| `email_verified` | `identity.email.verified` | **CRITICAL** (was NON_CRITICAL — see IMP004-SPEC-M03) | **fail-closed** | `user` | — |
| `mfa_enrolled` | `identity.mfa.enrolled` | CRITICAL | fail-closed | `user` | — |
| `mfa_recovery_code_used` | `identity.mfa.recovery_code_used` | CRITICAL | fail-closed | `user` | — |
| `mfa_recovery_codes_regenerated` | `identity.mfa.recovery_codes_regenerated` | CRITICAL | fail-closed | `user` | — |
| `mfa_reset_or_disabled` | `identity.mfa.reset_or_disabled` | CRITICAL | fail-closed | `user` | — |
| `first_super_admin_bootstrap_completed` | `identity.bootstrap.first_super_admin_completed` | CRITICAL | fail-closed | `user` | — ‡ |

† `login_failed`'s actor is `UNAUTHENTICATED`, never a resolved human Principal — see "Canonical
Actor (Pre-Principal Cases)" below. This applies regardless of whether the attempted email
resolves to a real `user` (subject present) or not (subject `NULL`): authentication itself
failed, so nothing proves who was typing, independent of whether the target account exists.

‡ `first_super_admin_bootstrap_completed`'s actor is `PRE_PRINCIPAL_SYSTEM`
(`execution_context = cli:identity:bootstrap-super-admin`), not the newly-created `user` (which
is this event's *subject*, not its actor) — no canonical Principal exists for anyone at this
point in the Q25 bootstrap sequence. See "Canonical Actor (Pre-Principal Cases)" below.

### RBAC (9 events, IMP-003)

| Existing Event | Canonical Event | Criticality | Failure Semantics | Subject | Required Metadata |
|---|---|---|---|---|---|
| `role_assigned` | `rbac.role.assigned` | CRITICAL | fail-closed (already proven) | `principal_role_assignment` | `role_id, scope_type, scope_id` |
| `role_revoked` | `rbac.role.revoked` | CRITICAL | fail-closed (already proven) | `principal_role_assignment` | `role_id` |
| `authority_assigned` | `rbac.authority.assigned` | CRITICAL | fail-closed (already proven) | `authority_assignment` | `authority_type_id, scope_type, scope_id` |
| `authority_revoked` | `rbac.authority.revoked` | CRITICAL | fail-closed (already proven) | `authority_assignment` | `authority_type_id` |
| `role_permission_granted` | `rbac.role_permission.granted` | CRITICAL | fail-closed (already proven) | `role_permission` | `role_id, permission_id` |
| `role_permission_revoked` | `rbac.role_permission.revoked` | CRITICAL | fail-closed (already proven) | `role_permission` | `role_id, permission_id` |
| `human_principal_tombstoned_user_deleted` | `rbac.principal.human_tombstoned` | CRITICAL | fail-closed (already proven) | `principal` | — |
| `non_human_principal_deactivated` | `rbac.principal.non_human_deactivated` | CRITICAL | fail-closed (already proven) | `principal` | `kind, {system\|integration}_principal_id` |
| `super_admin_canonically_authorized` | `rbac.super_admin.canonically_authorized` | CRITICAL | fail-closed | `principal` | `granted[], not_granted[]` |

### F-01 Disposition — Specification-Only Events

`role_registered, role_retired, permission_registered, permission_deprecated,
authority_type_registered, business_authority_assigned, authorization_denied_security_critical`
were named in IMP-003's "Audit Contract" but never implemented. Disposition:

```
role_registered / role_retired / permission_registered / permission_deprecated /
  authority_type_registered:
    DEFER. These would audit CATALOG changes (new Role/Permission/AuthorityType rows or
    retirement/deprecation), not PRINCIPAL-facing mutations. No current code path creates these
    events because Role/Permission/AuthorityType rows today are created only via seeders
    (RbacRoleSeeder etc.) with no acting Principal — there is nothing meaningful to attribute yet.
    IMP-004 registers these canonical event identifiers in the taxonomy (reserved, not emitted)
    so a FUTURE admin-facing catalog-management capability (not part of this stage) can emit them
    without a taxonomy change. No security-critical evidence gap exists today because no runtime
    path creates/retires/deprecates these rows outside of deploy-time seeding.

business_authority_assigned:
    RENAME, do not re-implement separately. This is the same event already implemented as
    `authority_assigned` (rbac.authority.assigned above) — AuthorityAssignmentService's single
    event covers both "authority" and "business authority" in the spec's own terminology (see
    BUSINESS-AUTHORITY-MODEL.md: Authority Type IS the business-authority concept). The IMP-003
    spec text names this differently from what was implemented; this specification treats
    `rbac.authority.assigned`/`rbac.authority.revoked` as the canonical, singular events — the
    IMP-003 spec's naming is superseded by this migration mapping, not implemented as a second,
    duplicate event.

authorization_denied_security_critical:
    IMPLEMENT. This is the one genuine coverage gap identified by readiness F-01 — no event of
    any kind exists today for a security-critical DENY (e.g. a self-escalation attempt in
    RolePermissionService::grant(), a Financial Authority check failure on an actually-attempted
    financial action once such checks exist). Canonical event: `security.authorization.denied`
    (namespace `security`, not `rbac`, since this concern is cross-cutting beyond RBAC alone).
    Required metadata: `attempted_action, denial_reason` (e.g. `self_escalation`,
    `financial_authority_check_failed`), never the target resource's sensitive field values (per
    the existing IMP-003 "Audit Contract" prohibition, preserved here).
    Emission call sites are added to the ALREADY-EXISTING IMP-002/IMP-003 services (e.g.
    RolePermissionService's self-escalation throw site) as part of the migration in this stage —
    this does not reopen or alter IMP-003's authorization LOGIC (the DENY decision itself, its
    exception type, and its message are all UNCHANGED), it only adds an audit emission call
    alongside an existing throw, consistent with "PATCH, DO NOT REWRITE". Its exact persistence
    and failure-precedence semantics (IMP004-SPEC-M04) are specified separately in "Denial Event
    Persistence Semantics" below — a denial has no authorized mutation to roll back, so it is
    explicitly NOT described as "critical/fail-closed" in the same sense as a mutation event; see
    that section for the deterministic model.
```

### F-02 Disposition — Redaction Strategy

**Resolved: safe allow-list serialization is the default strategy** (per Q26/this specification,
matching the originating instruction's explicit resolution). See "Redaction / Safe Serialization"
below for the mechanism.

### F-03 Disposition — Identity-Side Fail-Closed Proof Gap

**Resolved by requirement, not by claim.** This specification requires (see "Required Test
Plan") that every event marked CRITICAL in the Identity migration table above gets its own
forced-audit-failure rollback test, mirroring the 4 existing `RbacAuditTest` patterns exactly —
implementation is not considered done until this parity exists. This document does not claim the
proof already exists (it does not); it specifies the obligation to create it.

## Criticality Classification

```
CRITICAL      (default for any unclassified event, per Q26)
NON_CRITICAL  (explicit opt-out only, per event registration in the taxonomy registry)
```

**Criticality is owned exclusively by the canonical event registry, never by the runtime
caller.** An emitting call site selects/requests a canonical `event_type` — it does not, and
cannot, pass a `criticality` argument to `AuditWriter::record()`; the writer looks criticality up
from the registry entry for that `event_type`+`event_version`, identically to how it resolves
metadata allow-list, actor requirements, and visibility class (see "Registry-Derived Visibility
Class"). An unregistered `event_type` is rejected outright (see "Event Taxonomy"); a registered
event with no explicit criticality declaration defaults to `CRITICAL`/fail-closed, per Q26 —
there is no code path by which a caller can downgrade an event's criticality at the call site.

CRITICAL mutation pattern (already proven correct by IMP-003; required for every CRITICAL event
in the migration tables above):

```
BEGIN TRANSACTION
    business/authority mutation
    canonical audit append   <- AuditWriter::record(), same transaction, no queue
COMMIT
-- on AuditWriter failure: exception propagates, enclosing transaction rolls back --
```

No asynchronous/queued persistence is permitted for a CRITICAL event — this would silently weaken
the Q26 guarantee. `AuditWriter` for a CRITICAL event is a synchronous, in-request/in-job DB write
in the same connection/transaction as the mutation it accompanies.

## Non-Critical Events

For a `NON_CRITICAL` event (e.g. `identity.session.login_succeeded`, ordinary telemetry-like
events that are not themselves an authority/security mutation), `AuditWriter::record()` catches
its own persistence failure internally and:

```
1. does NOT propagate the exception to the caller (the underlying business operation, e.g. a
   successful login, is NOT rolled back or blocked by an audit-persistence hiccup);
2. reports the failure through the existing operational/application log (Laravel's default log
   stack), tagged distinctly (e.g. "audit_write_failed" context) so it is visible to operations —
   never silently swallowed with zero trace;
3. does NOT retry indefinitely and does NOT queue the failed write for later replay (no
   Redis/Kafka — shared-hosting compatible; a lost non-critical event is an accepted, bounded,
   reported risk, not a silent one).
```

## Denial Event Persistence Semantics (IMP004-SPEC-M04)

`security.authorization.denied` is neither CRITICAL nor NON_CRITICAL in the mutation sense —
there is **no authorized business mutation to roll back**, because the entire point of the event
is that nothing was authorized to happen. Describing it with the CRITICAL mutation pattern
("mutation + audit in one transaction, rollback on audit failure") would be meaningless (there is
nothing to roll back) and describing it as NON_CRITICAL ("fail-open silently") would risk making
exactly the highest-value security evidence the least reliable. This specification gives it its
own, third, deterministic contract:

```
1. The original authorization check determines DENY. This decision, its exception type, and its
   message are entirely owned by the calling service (e.g. RolePermissionService) and are NEVER
   altered by anything in this section.

2. Before the denial exception is allowed to propagate to the caller, the emitting code attempts
   to persist `security.authorization.denied` through the canonical AuditWriter, using an
   INDEPENDENT persistence boundary — a separate DB connection/transaction scope from whatever
   enclosing transaction the denying service may currently be inside (see "Transaction Placement"
   below). This is a deliberate, named exception to the "same transaction as the mutation" rule:
   there IS no mutation transaction to join for a denial (or, if there is an enclosing
   transaction from a caller that is ABOUT to roll back specifically because this denial is being
   thrown, joining it would guarantee the audit row never survives — the opposite of what a
   denial record exists for).

3. access result = DENY, ALWAYS, unconditionally, regardless of step 2's outcome:
   - If the audit persistence in step 2 SUCCEEDS: the original authorization denial (exception/
     domain-equivalent result) propagates to the caller UNCHANGED.
   - If the audit persistence in step 2 FAILS: the original authorization denial STILL propagates
     to the caller UNCHANGED. Access NEVER becomes allowed because the audit write failed — a
     failure of the evidence-recording step must never be interpreted as, or converted into, a
     more permissive outcome.

4. If step 2 fails, that failure is NOT silent: it is reported through an approved operational/
   security failure path (e.g. a dedicated `security_audit_failures` log channel, or the ordinary
   application error log tagged distinctly, mirroring "Non-Critical Events" §2's reporting
   discipline) — visible to operations/monitoring, and available for a test assertion to observe
   — but this reporting happens ALONGSIDE the unchanged denial propagation in step 3, never
   INSTEAD of it, and never by raising a second, different exception that could replace or mask
   the original denial in the caller's own exception-handling logic.
```

### Transaction Placement

If the code path that produces the denial is itself running inside an enclosing DB transaction
(e.g. `RolePermissionService::grant()`'s self-escalation check fires partway through that
method's `DB::transaction()` closure), the denial-audit write in step 2 above uses a **separate,
independently-committing connection scope** (a second named Laravel database connection to the
same physical database — a standard, shared-hosting-compatible Laravel feature, not new
infrastructure) rather than a nested transaction/savepoint on the SAME connection. This matters
because a savepoint-based "nested transaction" on the same connection is still rolled back if the
outer transaction rolls back — and the outer transaction WILL roll back here, specifically
because the thrown denial exception unwinds it. Using an independent connection means the denial
record commits immediately and durably, regardless of what the enclosing (about-to-be-rolled-
back) transaction ultimately does. No distributed transaction, message queue, or other new
infrastructure is introduced — this is a single additional same-database connection, resolved by
`AuditWriter` internally for this one event category only.

## Audit Record Schema (Proposed — No Migration Created)

| Field | Type | Nullable | Purpose | Privacy | Index | FK Behavior | Historical Durability |
|---|---|---|---|---|---|---|---|
| `id` | BIGINT unsigned PK | NO | identity | none | PK | — | permanent |
| `event_type` | string (registry-validated) | NO | taxonomy key | none | yes (with `occurred_at`) | — | permanent |
| `event_version` | unsigned tinyint | NO | shape versioning | none | no | — | permanent |
| `criticality` | enum `critical`/`non_critical` | NO | §Criticality | none | no | — | permanent |
| `occurred_at` | datetime (µs precision) | NO | when the fact occurred | none | yes | — | permanent |
| `actor_principal_id` | BIGINT unsigned, nullable | YES (see M02) | §Canonical Actor | low (an ID, not PII itself) | yes (with `occurred_at`) | FK -> `principals.id`, RESTRICT (never cascade-delete audit on Principal removal — Principal rows are tombstoned, never hard-deleted, per IMP-003); NULL only when `actor_principal_kind` is `unauthenticated`/`pre_principal_system` | permanent — Principal tombstoning does not remove the FK target row |
| `actor_principal_kind` | enum human/system/integration/unauthenticated/pre_principal_system | NO | §Canonical Actor | none | no | — | permanent |
| `execution_context` | string, nullable | YES (NOT NULL exactly when `actor_principal_id` IS NULL) | §Canonical Actor (Pre-Principal Cases) | none — a fixed registry constant, never free text | no | — | permanent |
| `subject_type` | string | NO | §Record Structure | none | yes (with `subject_id`, `occurred_at`) | — | permanent |
| `subject_id` | BIGINT unsigned, nullable | YES | §Record Structure | depends on subject | yes | no FK constraint (subject can be any domain's table; a polymorphic reference is by design not enforced at the DB layer, consistent with not letting Audit dictate every future domain's own migration order) | permanent; deliberately NOT a hard FK so a subject's own table lifecycle never constrains audit durability |
| `request_id` | ULID/string, nullable | YES | §Correlation | none | yes | — | permanent |
| `correlation_id` | ULID/string, nullable | YES | §Correlation | none | yes | — | permanent |
| `authentication_assurance` | enum STANDARD/ELEVATED, nullable | YES | security-relevant queries | none | no | — | permanent |
| `policy_version_ref` | string, nullable | YES | §Governance Foundation policy-version hook | none | no | — | permanent |
| `source_domain` | string, nullable | YES | §Idempotency/M06 — identifies the trusted producer namespace (e.g. `identity`, `rbac`, a future `payment_provider:xyz`) | none | yes (composite, see M06) | — | permanent |
| `source_event_id` | string, nullable | YES | §Idempotency/M06 | none | yes (composite `UNIQUE(source_domain, source_event_id, event_type)`, not a bare unique) | — | permanent |
| `metadata` | JSON | YES | allow-listed event-specific fields, per §Redaction | varies per event — governed by the allow-list itself | no (not indexed; queried fields must be promoted to a real column, not searched inside JSON) | — | permanent |
| `created_at` | datetime | NO | row-insert timestamp (distinct from `occurred_at` in case of any future non-instantaneous ingestion) | none | no | — | permanent |

No `updated_at` column — the row is never updated (see "Immutability"). No `before`/`after`
columns are added generically; a specific event MAY carry `before`/`after` values inside its own
allow-listed `metadata` shape when that event's registry entry explicitly permits it (opt-in per
event, never a blanket capability).

## Historical Durability

`actor_principal_id` is a hard FK to `principals.id` — safe because `principals` rows are never
hard-deleted (tombstoned only, per IMP-003's `Principal::tombstoned_at`/`isTombstoned()`), so the
FK target always exists. `subject_id` is intentionally NOT a hard FK (see schema table) — a
subject's own domain table may have entirely different lifecycle/deletion rules IMP-004 must not
constrain. No PII is duplicated onto the audit row beyond the two ID/kind fields already required
for attribution; if a future event's allow-listed metadata needs a human-readable snapshot (e.g.
"role name at time of grant" for readability after a Role is renamed), that snapshot is itself
subject to the same allow-list discipline as every other metadata field, on a per-event opt-in
basis — never a default.

## Redaction / Safe Serialization

**Default: ALLOW-LIST.** Every registered event type declares its own metadata shape (a small,
explicit list of permitted field names and their types) in the taxonomy registry. `AuditWriter`
rejects (raises, does not silently drop) any metadata key not present in that event's declared
allow-list. This is a structural change from the current blacklist-only precedent (F-02): no
raw Eloquent model, HTTP request, exception, header set, session, or arbitrary payload is ever
passed wholesale into `metadata` — every field is named explicitly by the call site and validated
against the registry before persistence.

Hard-prohibited categories (never permitted in ANY event's allow-list, enforced as a registry-
level constraint, not merely call-site discipline): plaintext passwords, password confirmation,
MFA/TOTP secrets, recovery codes, session identifiers/cookies, CSRF tokens, API bearer tokens,
API credentials, provider secrets, webhook secrets, private keys, payment credentials,
`Authorization` header values. A hashed secret is explicitly NOT exempted from this list merely
by virtue of being hashed (per the originating instruction) — no field whose value derives from
one of these categories is added to any event's allow-list, hashed or not.

## Immutability

No Eloquent `update()`/`save()` (beyond initial `create()`) or `delete()` surface is exposed by
the canonical audit Model for normal application code — mirroring the existing
`retired_at`/`deprecated_at`/`deactivated_at` "deliberately not mass-assignable, `forceFill()`
only in an authorized service" pattern, but going further: no service at all performs a
`forceFill()`-based update on an audit row in normal operation, only `create()`/insert. This is
**"immutable through authorized application operations"** — not a cryptographic tamper-evidence
claim; no hash-chaining or external attestation is implemented by this stage (not requested by
any reviewed authority, and out of scope). DB-layer protection (e.g. revoking UPDATE/DELETE grants
for the application's DB user on this table) is a deployment-time hardening option to document,
not a schema-level constraint this specification mandates for MySQL/shared-hosting portability
reasons — recorded as a `SHOULD`, not a `MUST`.

## Retention Compatibility (Structural Only)

No deletion API exists in normal operation (Q28). The only future deletion path is a **separate,
explicitly-authorized retention-purge mechanism** — not built by this stage — gated by:

```
authorized retention policy (versioned, referencing policy_version_ref-style evidence)
  -> eligibility determination (per-policy, per-record)
    -> authorized controlled purge process (a distinct, explicitly-permissioned operation,
       never a generic delete())
      -> purge governance evidence — itself an audit event (e.g. `governance.audit.purged`,
         CRITICAL, recording WHAT policy authorized it, WHO ran it, WHEN, and a count/reference
         to what was purged — never the purged content itself, to avoid the record defeating its
         own purpose)
```

If an applicable hold exists (a future concept, not built here, but the field/mechanism should
not be foreclosed), purge must check for it and refuse if present. IMP-004 builds the audit
event shape for "a purge happened" and the absence of a delete API — it does NOT build the policy
engine, the eligibility evaluator, or the hold mechanism themselves; those are explicitly
`DEFERRED` per §Proposed Deliverables in the readiness document.

### Terminal Purge-Evidence Rule (IMP004-SPEC-m01)

Without an explicit terminal rule, `governance.audit.purged` (the purge governance evidence event
itself) would eventually become old enough to be, itself, eligible for the very retention
policy/purge batch process that created it — producing unbounded recursive purge-evidence-of-
purge-evidence generation. This specification prevents that deterministically:

```
A governance.audit.purged record is EXCLUDED, permanently and unconditionally, from the
eligibility set of the SAME retention policy/purge batch process that produced it — it is never
a candidate for purge under the policy it is evidence of.

governance.audit.purged records are instead subject to their OWN, SEPARATELY AUTHORIZED retention
classification (a distinct policy_version_ref / registry classification from whatever ordinary
events a given policy governs) — this specification does not invent that separate classification
or its duration; it only requires that one exist and be distinct before any purge capability is
built (a future implementation-time obligation, not resolved by this document).
```

No arbitrary permanent retention duration is invented by this rule, and no legal retention number
is created — the rule only prevents the specific recursive/self-consuming failure mode; the
actual retention period for purge-evidence records remains exactly as deferred as every other
retention duration under Q17.

## Audit Read Authorization

Full canonical formula (per Q27), reusing [RBAC-ARCHITECTURE.md](../05-rbac/RBAC-ARCHITECTURE.md)
"Canonical Authorization Evaluation" verbatim:

```
Authenticated
AND Applicable Subject Context
AND Permission                          (registry-derived — see "Registry-Derived Visibility
                                          Class" below; never chosen by the reading caller)
AND Domain-Aware Scope                  (existing DATA-SCOPE-MODEL.md taxonomy, keyed off
                                          subject_type/subject_id)
AND Ownership/Subject Rule where applicable
AND Business Authority where applicable  (e.g. a financial-reference record may require an
                                          Authority Type beyond plain Permission — see below)
AND Authentication Assurance where required
AND No Security Restriction
```

Default: **DENY**.

### Registry-Derived Visibility Class (IMP004-SPEC-M05)

Every registered `(event_type, event_version)` pair — never a bare `event_type` alone, so a
future version bump can change classification going forward without altering how an OLD, already
-written record is read (see "Historical Reproducibility of Visibility" below) — declares, as
part of its registry entry, a fixed, non-caller-selectable set of read requirements:

```
visibility_class                (e.g. GENERAL, SECURITY, PRIVACY_SENSITIVE — a closed list, not
                                 free text)
required_permission             (which audit.read.* permission this event's BASE visibility_class
                                 requires — see permission family below)
scope_resolver                  (which DATA-SCOPE-MODEL.md scope type applies to this event's
                                 subject_type, e.g. GLOBAL_PLATFORM for RBAC catalog events)
subject/domain_resolver         (how to derive the applicable scope instance from subject_id,
                                 where applicable)
authentication_assurance_requirement (STANDARD or ELEVATED, where the event's sensitivity
                                 warrants requiring the READER to hold ELEVATED assurance, not
                                 just the original actor)
financial_reference_fields      (a list of this event's OWN allow-listed metadata field names —
                                 from "Redaction / Safe Serialization" — that, WHEN POPULATED on
                                 a given instance, additionally require
                                 audit.read.financial_reference for that specific record,
                                 regardless of the event's base visibility_class)
```

The reading code (the query/authorization service) NEVER supplies or chooses any of the above —
it looks them up from the registry using the persisted `event_type`+`event_version`, exactly as
`event_type` itself is already registry-validated at write time. A caller cannot request "treat
this record as GENERAL visibility" — the classification is a property of what was registered for
that event, not a runtime parameter.

**Permission family** (registered in the existing `PermissionRegistry` pattern, not a competing
mechanism): `audit.read` (baseline — satisfies any event whose registry entry declares
`visibility_class: GENERAL` and has no populated `financial_reference_fields`),
`audit.read.security` (required whenever `visibility_class: SECURITY`), and
`audit.read.financial_reference` (required whenever `visibility_class: FINANCIAL_REFERENCE`, OR
whenever ANY of that event's registry-declared `financial_reference_fields` is non-null on the
specific record being read — this applies uniformly regardless of whether the financial
reference is the record's primary subject, a secondary subject, or buried in allow-listed
metadata; there is no code path that only checks the primary `subject_type`). **Super Admin is
not granted any of these three by default** — each requires its own explicit Role/Permission
grant through the existing IMP-003 `RolePermissionService`, exactly like any other permission.

**`audit.read.financial_reference` is explicitly NOT financial business authority.** It grants
only the ability to see that an authorized audit record contains a reference to a financial
resource (e.g. a future `payment_id`) — it never grants, implies, or substitutes for the ability
to inspect or operate on the financial domain resource itself. Actually reading/acting on that
financial resource still requires the source financial domain's own scope and Business Authority
check, entirely independent of this permission, per the unchanged Q27 formula above.

### Historical Reproducibility of Visibility

Because classification is keyed to `(event_type, event_version)` and the registry's own version
history is immutable code (a new classification requires a NEW `event_version`, never an edit to
an existing version's entry — see "Event Versioning"), an already-written record's effective
visibility never silently changes because someone edits today's registry — reclassifying a
concern requires shipping a new version and does not retroactively alter how old rows, still
tagged with the old version, are read. `visibility_class` etc. are NOT persisted redundantly on
every row; they are deterministically resolved from the row's own already-persisted
`event_type`+`event_version` at read time, against that version's permanently-fixed registry
entry — this avoids duplicating authorization policy data across millions of rows while still
guaranteeing reproducibility.

## Correlation Foundation

`request_id` (per-HTTP-request/per-job, generated once at the entry point) and `correlation_id`
(may span multiple requests/jobs/events that logically belong together — e.g. a future
webhook-retry chain) are both plain opaque string/ULID values — never used as an authorization or
authentication credential, never derived from or exposing any secret. IMP-004 establishes
resolution (a request-scoped container binding, mirroring how `AssuranceService` is resolved
today) so future HTTP requests, queue jobs, and integrations can obtain a stable identifier
without each reinventing one; it does not connect this foundation to any currently-nonexistent
Payment/Ledger/Reconciliation flow.

## Request Context

Captured, per event where an event's own allow-list opts in: `request_id`, `correlation_id`,
route name, HTTP method, IP address (only where the emitting event's registry entry explicitly
authorizes it — e.g. `identity.session.login_failed` may reasonably carry it; most events do
not), and a normalized (not raw) user-agent string. CLI/job/integration executions carry an
analogous non-HTTP context (command name, job class, integration identifier) instead. Raw request
bodies are never persisted, under any circumstance — this is not event-specific, it is an
absolute rule, mirroring the "Redaction" section's absolute prohibitions.

## Authentication Integration (IMP-002 Migration)

The 19 events in the Identity migration table above move onto the canonical `AuditWriter`,
replacing `IdentityAuditLogger`'s direct `Log::channel('identity_audit')` call — **no change to
any IMP-002 authentication business rule**. Per Q26/F-03, every event marked CRITICAL in that
table requires a forced-failure rollback test (currently absent — this is new required test
coverage, not a claim of existing coverage). `login_failed`/`login_succeeded`/`logout` remain
`NON_CRITICAL` — an ordinary failed login attempt is explicitly NOT elevated to
transactional-authority-mutation status merely by migrating sinks, consistent with the
originating instruction's caution against that exact mistake. `email_verified` is now
**CRITICAL** (IMP004-SPEC-M03 — email verification is a persistent Identity state mutation, a
deterministic application of Q26, not an exception to it): the `email_verified_at` write and the
canonical audit append are one atomic transaction; a forced audit-append failure rolls back the
verification state change, leaving no partial/orphan verification and no orphan audit record.

## RBAC Integration (IMP-003 Migration)

The 9 events in the RBAC migration table above move onto the canonical `AuditWriter`. **No
regression to any IMP-003 authorization invariant is permitted** — the existing 4
forced-audit-failure rollback tests in `RbacAuditTest` must continue to pass unchanged in
substance (their assertions target the same transactional guarantee; only the underlying sink
changes from `Log::channel('rbac_audit')` to the canonical writer). The new
`security.authorization.denied` event (F-01 disposition) is added as a NEW emission call site at
the existing self-escalation-denial throw site in `RolePermissionService::grant()` — this adds an
audit call, it does not alter the DENY decision/exception/message IMP-003 already implements and
that is already tested.

## Bootstrap (Q25 Preservation)

`first_super_admin_bootstrap_completed` (IdentityAuditLogger) and
`super_admin_canonically_authorized` (RbacAuditLogger, via `BridgeFirstSuperAdmin`) both migrate
onto the canonical sink as CRITICAL events, exactly as already classified in the migration
tables. No retroactive historical fabrication is performed — historical log-file entries already
written before this migration are NOT backfilled into the new canonical table; migration applies
prospectively to new events only, consistent with GOV-MM-002's own "prospective only" pattern
for a different concern. Q25's substantive rules (no public bootstrap endpoint, no default
credentials, no plaintext secret ever in the audit payload) are unchanged and re-verified by the
existing `SuperAdminBridgeTest`/bootstrap test coverage, which is not weakened by this migration.

## Audit Query Foundation

Backend-only. Minimum filters: `event_type` (single or namespace-prefix match), `actor_principal_id`,
`subject_type`+`subject_id`, `occurred_at` range, `correlation_id`, `criticality`. All queries are
paginated (bounded page size, no unbounded "return everything" endpoint) and always pass through
the Audit Read Authorization formula above before returning any row — there is no
authorization-bypassing "internal" query path exposed to a controller. This foundation exists to
make IMP-026 (Search/Reporting) and any future admin UI possible later; it does not itself
implement either.

## Financial Boundary

Restated explicitly, per instruction: `AUDIT != LEDGER`; `AUDIT != FINANCIAL CONSEQUENCE`;
`AUDIT != PAYMENT`; `AUDIT != RECONCILIATION`. An audit record may reference a future financial
resource by ID (e.g. a future `payment_id` inside an allow-listed metadata field once IMP-009
exists) but never carries a monetary amount as an authoritative value, never substitutes for a
Ledger entry, and is never read by any future financial-reporting logic as if it were the Ledger.

## Approval Boundary

IMP-004 provides only the generic `policy_version_ref` field and the general event/subject
attribution shape a future Approval Instance/Decision could reference. It implements no Approval
Policy, routing, threshold, or matrix logic — those remain Module 12's own, later, separate
concern.

## Database Requirements

MySQL 8.x target (locked). BIGINT unsigned keys throughout, consistent with existing convention.
JSON column for `metadata` only (no other JSON usage). No generated columns proposed — no
evidence of need. Timestamp precision: microsecond, consistent with `occurred_at` needing
fine-grained ordering under concurrent writes. Append-only strategy avoids most concurrency
concerns (§Concurrency). Expected write volume is the primary future performance risk (§Growth) —
addressed by the index set in the schema table, not by premature partitioning. No infrastructure
beyond MySQL is introduced (no Redis/Kafka/Elasticsearch) — shared-hosting compatible.

## SQLite + MySQL Test Matrix

```
SQLite:  primary/default regression suite — every functional, authorization, redaction, and
         (where transaction semantics are DB-driver-independent) rollback test runs here first
         and fast, exactly as established across IMP-002/IMP-003.

MySQL 8.x (disposable instance only — never the real/unknown configured database): mandatory
         runtime evidence for: migration application; actual FK/constraint enforcement;
         transaction rollback behavior under InnoDB; concurrency/locking behavior for concurrent
         audit-row insertion; JSON column behavior for `metadata`; unique-index behavior for
         `source_event_id`. A green SQLite suite alone does NOT substitute for this evidence —
         same bar already established and evidenced in docs/audits/IMP-003-FINALIZATION.md
         "MySQL Runtime".
```

## Required Test Plan

```
EVENT INVENTORY:
  - all 28 existing (19 Identity + 9 RBAC) events have a canonical mapping registered
  - the new security.authorization.denied event is registered
  - the 5 reserved catalog events (role_registered, role_retired, permission_registered,
    permission_deprecated, authority_type_registered) are registered but confirmed NOT emitted
    by any current runtime path (a test asserting no emission call site exists for them, or
    equivalently that they never appear in captured audit output during the full suite run)
  - no two existing runtime events map to the same canonical event_type (no duplicate mapping)

PERSISTENCE:
  - canonical event append succeeds and persists exactly the declared fields
  - an unregistered event_type is rejected (hard error, not silently accepted)
  - a metadata key outside an event's declared allow-list is rejected

ACTOR:
  - human Principal attribution correctness
  - System Principal attribution correctness
  - Integration Principal attribution correctness

PRE-PRINCIPAL ACTOR (IMP004-SPEC-M02):
  - unknown-email login_failed persists with actor_principal_kind = unauthenticated,
    actor_principal_id = NULL, and no fabricated human actor
  - first_super_admin_bootstrap_completed persists with actor_principal_kind =
    pre_principal_system, execution_context = the fixed bootstrap constant, and
    actor_principal_id = NULL
  - a CRITICAL event whose registry entry does NOT declare unauthenticated/pre_principal_system
    as an allowed actor kind rejects an attempt to write it with a NULL actor_principal_id
    (deterministic execution-context attribution cannot be bypassed into an ordinary NULL)

CRITICALITY OWNERSHIP:
  - a call site cannot override a registered event's criticality
  - an unclassified/unregistered event defaults to CRITICAL and is rejected until registered

EMAIL VERIFICATION (IMP004-SPEC-M03):
  - successful verification persists state (`email_verified_at`) + audit atomically
  - forced audit-append failure rolls the verification state back
  - no partial verification state after a forced failure
  - no orphan audit record after rollback

ATOMICITY (CRITICAL mutation events):
  - critical mutation + audit success -> both persist
  - forced audit-write failure -> mutation rolls back entirely (extend existing RBAC pattern to
    every Identity CRITICAL event per F-03's disposition)
  - no partial critical mutation ever observable after a forced failure

DENIAL EVENT (IMP004-SPEC-M04):
  - a self-escalation denial remains denied (unchanged exception/message) and produces no
    business mutation, with security.authorization.denied persisted successfully
  - forced security.authorization.denied persistence failure still results in DENY — access
    never becomes allowed
  - the forced persistence failure is reported through the operational/security failure path,
    not silently dropped
  - the denial audit record survives even though the enclosing transaction (if any) rolls back
    due to the thrown denial exception (proves the independent persistence boundary)

IMMUTABILITY:
  - unauthorized update attempt is rejected/has no effect
  - arbitrary delete attempt is rejected/has no effect

REDACTION:
  - every hard-prohibited secret category (§Redaction) is absent from every persisted event,
    including the new canonical sink (extends RbacAuditTest's existing pattern)
  - a metadata value outside the declared allow-list cannot bypass rejection via any encoding
  - sensitive HTTP headers/raw payloads are never persisted

VISIBILITY / AUTHORIZATION (read) (IMP004-SPEC-M05):
  - default DENY for an actor with no audit.read.* permission
  - authorized, correctly-scoped read succeeds
  - unauthorized read is denied
  - cross-scope read attempt is denied
  - Super Admin without an explicit audit.read.* grant is denied (proves no automatic access)
  - baseline `audit.read` does NOT expose a SECURITY-classified event
  - baseline `audit.read` does NOT expose a record whose registry-declared
    financial_reference_fields are populated, even when the event's own base visibility_class is
    GENERAL — proves the metadata-level financial-reference check applies regardless of location
  - a financial reference present as the PRIMARY subject invokes the same
    audit.read.financial_reference requirement as one present only in metadata (proves uniform
    treatment "regardless of location")
  - source-domain/applicable scope is still enforced even when the higher-sensitivity permission
    is held (holding audit.read.financial_reference alone does not bypass scope)
  - audit.read.financial_reference does not grant any ability to read/operate on the referenced
    financial domain resource itself

IDENTITY INTEGRATION:
  - all 19 migrated events persist via the canonical sink with identical semantic content to
    today's log-channel payloads
  - CRITICAL Identity events (including email_verified) roll back their mutation on forced audit
    failure (new coverage, closing F-03)

RBAC INTEGRATION:
  - all 9 migrated events persist via the canonical sink
  - existing 4 forced-failure rollback guarantees continue to pass unchanged in substance
  - the new security.authorization.denied event fires on a self-escalation denial

TRANSACTION ROLLBACK:
  - an audit row inserted inside a transaction that is later rolled back for an unrelated reason
    does not survive (standard DB transaction behavior, explicitly tested rather than assumed)

SOURCE_EVENT_ID (IMP004-SPEC-M06):
  - a legitimate retry (same source_domain + source_event_id + event_type) returns/references the
    existing canonical audit record, no duplicate row created
  - the same source_event_id in a DIFFERENT valid producer scope (different source_domain or
    different event_type) does not collide with an unrelated record
  - a conflicting reuse (same scoped key, incompatible core attribution) is rejected as an
    idempotency conflict, not silently accepted or silently overwritten
  - a NULL source_event_id never participates in the uniqueness constraint

CONCURRENCY:
  - two concurrent CRITICAL mutations for different subjects both persist correctly with no
    cross-contamination
  - two concurrent writes with the same scoped source_event_id resolve deterministically to one
    canonical row (no race producing two rows)

PURGE EVIDENCE (IMP004-SPEC-m01):
  - a governance.audit.purged record is excluded from the eligibility set of the same purge batch
    that produced it (no recursive self-qualification)

MYSQL:
  - mandatory disposable MySQL 8.x runtime evidence per "SQLite + MySQL Test Matrix" above
```

## Security Negative Tests

```
- actor spoofing (a caller-supplied, not container-resolved, Principal ID is never trusted —
  mirrors IMP-003's existing "stale caller-supplied model" defense pattern)
- Principal spoofing / forged Principal ID (reload+validate, do not trust a passed-in ID alone)
- execution_context spoofing (a caller attempting to supply an arbitrary, non-registered
  execution_context string, or to claim `unauthenticated`/`pre_principal_system` for an event
  whose registry entry does not permit that actor kind, is rejected — see "Canonical Actor
  (Pre-Principal Cases)")
- criticality/visibility override attempt (a caller attempting to pass an explicit criticality or
  visibility_class value is rejected/ignored — both are registry-derived only, see "Criticality
  Classification" and "Registry-Derived Visibility Class")
- subject spoofing where relevant (a caller cannot attribute an event to a subject_id it has no
  relationship to, where the emitting service itself is responsible for supplying the correct ID)
- unregistered event_type rejected
- metadata injection (attempting to smuggle a non-allow-listed key)
- secret leakage (see REDACTION tests above)
- IDOR on audit read (requesting another actor's/scope's record directly by ID)
- cross-scope access (see AUTHORIZATION tests above)
- audit record mutation attempt (update/delete)
- arbitrary/administrative deletion attempt absent an authorized purge path
- oversized metadata payload (a bound must exist; exact limit is an implementation-time
  parameter, not invented here as a specific number without evidence)
- malformed JSON in metadata input
- security-restriction bypass (an actor under a persistent security restriction, per Q24, cannot
  read audit data any more than they can perform any other restricted action)
```

## Performance / Growth

Bounded metadata size (exact byte limit is an implementation-time parameter). Index strategy
matches the query foundation's filters (§Audit Query Foundation, schema table). No
Elasticsearch. No premature partitioning — if write volume evidence during implementation
justifies it, that is an implementation-time engineering decision within this specification's
MySQL/shared-hosting constraints, not a new architecture question.

## Observability Boundary

`AUDIT TRAIL != APPLICATION LOGGING != METRICS != TRACING`. Operational/application logging
(Laravel's default stack) may report the audit subsystem's OWN failures (e.g. a NON_CRITICAL
event's persistence failure, per "Non-Critical Events") — it never substitutes for a required
durable CRITICAL audit record. IMP-004 does not build a metrics or tracing system.

## Migration / Backward Compatibility

No flag-day rewrite. `IdentityAuditLogger`/`RbacAuditLogger`'s call sites are updated to call the
new canonical `AuditWriter` (via the migration mapping tables above) — their existing method
signatures are preserved where possible, or replaced with a compatible facade, so calling
services (already-tested IMP-002/IMP-003 code) require minimal changes beyond the sink
substitution itself, consistent with "PATCH, DO NOT REWRITE". The `identity_audit`/`rbac_audit`
log channels MAY remain temporarily as a secondary, best-effort operational log during migration
(useful for cross-checking during the transition) but are not the canonical source once
migration completes — there must be exactly ONE canonical durable audit source at the end of
this stage, never two competing canonical records for the same event.

## Definition of Done (IMP-004-Specific)

In addition to [DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md)'s generic
checklist:

```
[ ] Canonical audit persistence exists (schema, model, AuditWriter contract)
[ ] Canonical taxonomy/registry exists, with 29 canonical active/target events registered
    (28 migrated — 19 Identity + 9 RBAC — + 1 new: security.authorization.denied) plus the 5
    reserved-but-unemitted catalog events (34 total registry entries including reserved)
[ ] Identity (19) + RBAC (9) integration completed per the migration tables
[ ] Pre-Principal actor cases (unauthenticated, pre_principal_system) supported and tested,
    with no fabricated human actor and no arbitrary caller-supplied execution_context (M02)
[ ] email_verified reclassified CRITICAL, with atomic state+audit persistence and forced-failure
    rollback coverage (M03)
[ ] Denial-event persistence semantics implemented and tested: DENY always propagates unchanged,
    audit failure never permits access, failure is reported not silent, independent persistence
    boundary survives the enclosing transaction's rollback (M04)
[ ] Registry-derived visibility class implemented: criticality and visibility are never
    caller-supplied; financial-reference visibility applies uniformly wherever a financial
    reference appears (primary subject, secondary subject, or metadata); audit.read.financial_
    reference confirmed to grant no financial-domain business authority (M05)
[ ] source_event_id idempotency implemented exactly per the composite
    (source_domain, source_event_id, event_type) scope: legitimate retry deduplicates, conflicting
    reuse is rejected, NULL never collides (M06)
[ ] Terminal purge-evidence rule implemented: governance.audit.purged records excluded from their
    own producing purge batch's eligibility set (m01)
[ ] Critical atomicity demonstrated for every CRITICAL event (new Identity-side + email_verified +
    unchanged RBAC coverage)
[ ] Allow-list redaction demonstrated (F-02 resolved in code, not only in this specification)
[ ] Audit-read authorization demonstrated (Q27; default DENY; no automatic Super Admin access)
[ ] Immutability demonstrated (no update/delete surface)
[ ] SQLite suite PASS
[ ] MySQL 8.x disposable runtime suite PASS
[ ] Quality checks PASS (Pint, TypeScript, build, composer audit, git diff --check)
[ ] Independent Codex audit PASS, 0 unresolved BLOCKER/MAJOR
[ ] Human Stage Gate approved
[ ] IMP-000/001/002/003 FINAL/LOCKED evidence unmodified
[ ] Ledger/Payment/Commission/Approval/Search-Reporting/API remain unimplemented by this stage
```

## Finding Disposition (Specification Remediation Pass 1)

Resolution status for the Codex specification-audit findings addressed by this remediation pass.
None of these should be read as Codex having already accepted the resolution — each is
**PATCHED — PENDING INDEPENDENT RE-AUDIT** until Codex's own re-audit confirms it.

```
IMP004-SPEC-M01  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): "Existing Event Migration" (verified inventory block: 19 Identity + 9 RBAC = 28
  existing, +1 new denial event = 29 canonical active/target, +5 reserved = 34 total registry
  entries), "Identity (19 events, IMP-002)" table heading, "Authentication Integration"
  (19-event reference), "Required Test Plan" EVENT INVENTORY group, "Definition of Done
  (IMP-004-Specific)". Counts verified directly against every `->record(...)` call site in
  app/Services/Identity/, app/Http/Controllers/Auth/, app/Console/Commands/
  BootstrapSuperAdmin.php, app/Services/Rbac/, and app/Console/Commands/
  BridgeFirstSuperAdmin.php — matches Codex's reported counts exactly; no repository-evidence
  discrepancy was found.

IMP004-SPEC-M02  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): "Canonical Actor" (rewritten), "Canonical Actor (Pre-Principal Cases)" (new),
  "Audit Record Schema" (actor_principal_id now nullable, actor_principal_kind extended,
  execution_context column added), Identity migration table footnotes † and ‡, "Required Test
  Plan" PRE-PRINCIPAL ACTOR group, "Security Negative Tests" (execution_context spoofing).

IMP004-SPEC-M03  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): Identity migration table (`email_verified` row), "Authentication Integration",
  "Required Test Plan" EMAIL VERIFICATION group, "Definition of Done (IMP-004-Specific)".

IMP004-SPEC-M04  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): "F-01 Disposition" (`authorization_denied_security_critical` entry rewritten),
  "Denial Event Persistence Semantics" (new, including "Transaction Placement"), "Required Test
  Plan" DENIAL EVENT group, "Definition of Done (IMP-004-Specific)".

IMP004-SPEC-M05  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): "Audit Read Authorization" (rewritten with "Registry-Derived Visibility Class" and
  "Historical Reproducibility of Visibility"), "Audit Record Schema" (source_domain/visibility
  notes), "Required Test Plan" VISIBILITY / AUTHORIZATION group, "Definition of Done
  (IMP-004-Specific)".

IMP004-SPEC-M06  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): "Idempotency" (rewritten with exact composite-uniqueness/retry/conflict/null
  semantics), "Audit Record Schema" (`source_domain` column added, `source_event_id` uniqueness
  corrected to composite), "Required Test Plan" SOURCE_EVENT_ID group, "Definition of Done
  (IMP-004-Specific)".

IMP004-SPEC-m01  PATCHED — PENDING INDEPENDENT RE-AUDIT
  Section(s): "Retention Compatibility (Structural Only)" → "Terminal Purge-Evidence Rule" (new),
  "Required Test Plan" PURGE EVIDENCE group, "Definition of Done (IMP-004-Specific)".
```

No new Human Decision was introduced by this remediation pass — all seven findings were
remediable within the existing Q26/Q27/Q28 authority and the pre-existing Level 1-4 baseline,
consistent with Codex's own `HUMAN DECISION REQUIRED = 0` in the original audit.
