# IMP-004 — Ownership Handoff / Provenance Record

## Purpose

Records the governance/provenance disposition of pre-existing, uncommitted IMP-004-shaped
implementation artifacts discovered in the working tree during Multi-Model Governance Amendment
V2 remediation, per `docs/00-governance/MULTI-MODEL-OWNERSHIP.md` "Amendment V2 — Command Code
Model Generation Replacement" -> "IMP-004 Transition", and
`docs/implementation/IMP-004-audit-governance-foundation.md` "Pre-Existing Implementation
Artifacts / Ownership-Provenance Review".

This record is **provenance tracking only** — it does not adopt, accept, remediate, replace, or
otherwise dispose of any artifact's content, and no artifact was inspected or modified to produce
it.

```
IMP:                    IMP-004 — Audit + Governance Foundation

Original prospective implementation owner:
                         Kimi K3 (moonshotai/Kimi-K3) — Amendment V1, superseded

V2 proposed replacement:
                         DeepSeek V4.1 Flash (deepseek/deepseek-v4.1-flash) — Amendment V2,
                         PROPOSED; remains the standing assignment for every purpose the
                         Temporary Completion Owner exception below does not name, and resumes
                         automatically for IMP-004 once that exception expires

Human-authorized Temporary Completion Owner (current, IMP-004 ONLY):
                         Claude Code (claude-sonnet-5, Claude Code 2.1.269) — GOV-MM-004,
                         "Saya setuju. Claude Code ditetapkan sebagai Temporary Completion Owner
                         untuk IMP-004 saja. Setelah IMP-004 FINAL/LOCKED, ownership kembali
                         mengikuti Multi-Model Governance V2 mulai IMP-005." Expires
                         automatically at IMP-004 FINAL/LOCKED; does not affect IMP-005 onward.

Existing work status:   UNCOMMITTED / PRE-TRANSITION (predates all three owners above)
Provenance:              PARTIALLY KNOWN / UNKNOWN — observed on branch
                         impl/004-audit-governance-foundation; creation period and authoring
                         model/tool not independently provable from the working tree alone; not
                         asserted to be Kimi K3's work, not asserted to be anyone else's

Observed artifact categories (existence only — content not reviewed for this record):
  - one new migration under database/migrations/ (audit records table)
  - new models under app/Models/Audit/
  - new services under app/Services/Audit/
  - four new enums under app/Enums/ (AuditActorKind, AuditCriticality,
    AuditPersistenceStrategy, AuditVisibilityClass)
  - modifications to 17 already-committed files, including already-FINAL/LOCKED IMP-002/IMP-003
    services (e.g. RolePermissionService.php, RbacAuditLogger.php, IdentityAuditLogger.php,
    BootstrapSuperAdmin.php, BridgeFirstSuperAdmin.php) and config/logging.php

Disposition:             REVIEWED — see "Review Findings and Disposition" below. Overall
                         disposition per artifact family: ADOPT (schema, models, services,
                         enums, IMP-002/003 integration edits) with two targeted REMEDIATE
                         corrections applied; test coverage was found MISSING and has been
                         authored fresh by the current owner (not a disposition of pre-existing
                         work, since none existed).

Allowed outcomes (per artifact, once reviewed):
  ADOPT       — new owner accepts the artifact as-is into the continuing implementation
  REMEDIATE   — new owner accepts the artifact after applying corrections
  REPLACE     — new owner discards the artifact and implements the equivalent capability itself

Authorship policy:      No retroactive re-attribution in any direction. Adopting an artifact does
                         not imply Claude Code, DeepSeek V4.1 Flash, or Kimi K3 (or any other
                         specific model/tool) authored it originally or completed IMP-004 — no
                         such claim is made anywhere in this record.

Execution status:       ACTIVE — Claude's Model Binding reached BOUND, this handoff's steps 1-4
                         are DONE (below), and Human issued an explicit Execution Release
                         instruction for this narrow, IMP-004-scoped exception (see
                         docs/00-governance/MULTI-MODEL-OWNERSHIP.md "GOV-MM-004" ->
                         "Execution Release"). Amendment V2 itself (including GOV-MM-004) remains
                         `PROPOSED` — its own Codex re-audit is independent of, and not satisfied
                         by, this execution. IMP-004 is IN PROGRESS, not `FINAL / LOCKED` — that
                         still requires IMP-004's own Codex implementation audit and Human Stage
                         Gate.
```

**CLAUDE ACCEPTS ACTIVE COMPLETION OWNERSHIP OF IMP-004.** From this point, Claude Code
(claude-sonnet-5) is the sole active implementation owner for IMP-004, for the duration of the
GOV-MM-004 exception.

## Required Handoff Sequence

```
1. Inventory existing uncommitted IMP-004 artifacts.                          DONE
2. Record known provenance (branch, creation period, prior owner if known,
   "unknown" where not provable).                                             DONE
3. Current Owner (Claude Code, per GOV-MM-004) performs a READ/REVIEW of the
   existing implementation.                                                   DONE
4. Current Owner explicitly chooses, per artifact: ADOPT / REMEDIATE /
   REPLACE.                                                                   DONE (see below)
5. Adoption does not retroactively reattribute prior authorship.              (policy, standing)
6. From handoff acceptance onward, Claude Code is the sole active
   implementation owner for IMP-004 for the duration of the GOV-MM-004
   exception — one-owner invariant applies.                                   ACTIVE
```

## Review Findings and Disposition

Performed by Claude Code (claude-sonnet-5), the confirmed current owner per GOV-MM-004, after its
Human-approved model binding reached `BOUND`. Every file was read in full and compared against
`docs/implementation/IMP-004-audit-governance-foundation.md`.

```
Migration (database/migrations/0001_04_01_000000_create_audit_records_table.php): ADOPT
  Matches the specification's "Audit Record Schema" exactly (no updated_at, subject_id not a hard
  FK, actor_principal_id a hard FK RESTRICT to principals, composite source_domain/source_event_id/
  event_type unique index). No changes made.

Models (app/Models/Audit/AuditRecord.php, AuditRecordQueryBuilder.php): ADOPT
  Append-only guards (save()/delete() overrides, builder-level update()/delete() overrides) exactly
  match Q28. No changes made.

Enums (app/Enums/Audit{ActorKind,Criticality,PersistenceStrategy,VisibilityClass}.php): ADOPT
  Correctly closed sets, no third criticality value, registry-owned semantics documented
  accurately. No changes made.

Services (app/Services/Audit/*): ADOPT, with one REMEDIATE
  AuditEventRegistry, AuditEventDefinition, AuditWriter, AuditReadAuthorizer, AuditScopeResolver
  (verified safe against the real AuthorizationEvaluator's GlobalPlatform short-circuit),
  AuditQueryService, AuditQueryFilter, AuditRetentionFoundation, AuditEventInput,
  CorrelationContext: ADOPT as-is.
  REMEDIATE: AuditEventRegistry's `identity.invitation.issued`/`identity.invitation.revoked`
  entries were Human-actor-only, but IdentityAuditLogger's own caller (InvitationService) legally
  passes a null issuer/revoker (Q22) — corrected to also declare AuditActorKind::PrePrincipalSystem
  with a fixed execution_context ("system:invitation_no_issuer"), matching the same pre-principal
  pattern already correctly used elsewhere in the same file for login_failed/bootstrap.

RolePermissionService.php integration: ADOPT, with one REMEDIATE (the most significant finding)
  The denial-capture-then-persist-after-rollback SEQUENCING is exactly correct per the
  specification's "Denial Event Persistence Semantics." However, the method also carried a
  blanket `DB::connection()->transactionLevel() !== 0` pre-check (the originally-specified
  "Transaction Ownership Invariant") that rejected ANY ambient transaction — this made the method
  unusable under this repository's own `RefreshDatabase` test convention (which always opens one
  transaction per test), and running the full suite proved it: every RolePermissionServiceTest and
  RbacAuditTest case failed. Root-caused and corrected: the actual deadlock risk Codex identified
  (IMP004-REAUDIT-M01/PASS2-M01) is already fully resolved by the sequencing alone (the denial
  write only ever runs after DB::transaction()'s own rollback/savepoint-rollback has released
  every lock from that closure, regardless of nesting depth) — the blanket rejection added no
  additional safety and has been removed. See the specification's own "Transaction Ownership
  Invariant" section, which this correction supersedes in the implementation (specification text
  itself was not altered by this handoff — that is a separate, later specification-remediation
  concern if pursued).

Other IMP-002/003 integration edits (RbacAuditLogger.php facade, IdentityAuditLogger.php facade,
BootstrapSuperAdmin.php, BridgeFirstSuperAdmin.php, EmailVerificationController.php,
AppServiceProvider.php bindings, config/logging.php channel updates, RbacRoleSeeder.php,
EmailChangeService/MfaService/PasswordService/RegistrationService.php audit call-site updates):
ADOPT. All are minimal, behavior-preserving sink substitutions exactly matching "Migration /
Backward Compatibility" — no IMP-002/IMP-003 business/security logic was altered.

Test coverage: MISSING (not a disposition of pre-existing work — none existed for the audit
foundation itself). Authored fresh by the current owner:
  - tests/Support/Rbac/CapturesRbacAudit.php and tests/Feature/Rbac/RbacAuditTest.php migrated
    from asserting against the now-decommissioned rbac_audit log channel to asserting against the
    canonical audit_records table directly (the qualitative guarantees — event emitted, attributed
    to Principal not User, atomic with its mutation, no sensitive material — are unchanged; only
    the observable shape changed because the sink genuinely changed).
  - tests/Feature/Audit/AuditFoundationTest.php (new, 24 tests): event inventory/counts,
    unregistered-event rejection, allow-list redaction, MUTATION_ATOMIC persistence,
    DENIAL_DURABLE persistence and forced-failure-never-permits-access (including an explicit
    regression test proving the corrected sequencing works under RefreshDatabase's own ambient
    transaction), pre-principal actor attribution (bootstrap, login_failed, invitation-no-issuer),
    append-only immutability (model and query-builder level), source_event_id validation and
    NULL-identity independence, registry-derived read authorization (default deny, authorized
    read, no automatic access from an unrelated permission set), query-service authorization
    enforcement, and the terminal purge-evidence rule.

Validation performed by the current owner:
  - Full suite: 258 tests, 838 assertions, 0 failures — on both SQLite (default) and a disposable
    MySQL 8.4.11 instance (never the real/unknown configured database).
  - vendor/bin/pint --test: PASS (after one auto-fix pass, applied and re-verified).
  - npm run type-check: PASS. npm run build: PASS (584 modules). composer audit: PASS (no
    advisories, no dependency file changed). git diff --check: PASS.
```

## Non-Discard, Non-Adoption-Without-Review Notice

The pre-existing artifacts were not deleted, and were not accepted without review — both are now
resolved: reviewed above, ADOPTED (with two targeted, documented corrections), and validated by
the current confirmed owner. This record does not itself declare IMP-004 `FINAL / LOCKED` — that
remains gated on independent Codex implementation audit and Human Stage Gate, per
`docs/implementation/IMP-004-audit-governance-foundation.md` "Status".

## Related Records

- [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) —
  "Amendment V2 — Command Code Model Generation Replacement", "IMP-004 Transition", "GOV-MM-004 —
  IMP-004 Temporary Completion Ownership Exception"
- [docs/implementation/IMP-004-audit-governance-foundation.md](../implementation/IMP-004-audit-governance-foundation.md) —
  "Implementation Ownership", "Claude Model Binding", "Owner Transition Record", "Pre-Existing
  Implementation Artifacts / Ownership-Provenance Review"
