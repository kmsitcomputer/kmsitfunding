# IMP-000 — Repository Bootstrap & Implementation Governance

## Status

FINAL / LOCKED

This status reflects the completed chain: implementation work → independent audit → remediation
(3 passes) → targeted re-audit (3 passes, final result PASS) → Human approval ("saya setuju",
2026-09-12). It was reached by recording a Human decision, not by AI self-declaration — see
[docs/audits/IMP-000-INDEPENDENT-AUDIT.md](../audits/IMP-000-INDEPENDENT-AUDIT.md) for the full
audit chronology and [docs/audits/IMP-000-STAGE-GATE.md](../audits/IMP-000-STAGE-GATE.md) for the
dedicated stage-gate approval record. This FINAL/LOCKED status applies to IMP-000 only — see
"IMP-000 Gate vs. IMP-001 Readiness" below.

## Objective

Establish repository foundation, governance, and documentation authority before any AI coding
agent begins building the application.

## Source Requirements

Program-level directive: bootstrap governance prior to feature work. No Master Requirements
entries are implemented by this task.

## Architecture References

None yet locked beyond the baseline decisions captured in [AGENTS.md](../../AGENTS.md) under
"Locked Decisions."

## Scope

- Repository initialization (git).
- `docs/` directory structure across governance, requirements, architecture, database, security,
  RBAC, domains, api, testing, deployment, ADR, decisions, implementation, audits.
- `AGENTS.md` baseline.
- Branching/worktree policy, Definition of Ready/Done, testing gate, review severity, merge gate,
  architecture change-control documents.
- Implementation specification template.
- Pull request and issue templates.

## Out of Scope

- Laravel application bootstrap (PHP, Vue, Inertia, TypeScript, Tailwind, Vite, MySQL baseline) —
  this is IMPLEMENTATION 01.
- Any business feature, migration, or business logic.
- Master Requirements content itself (Q1-Q20) — to be authored under
  `docs/01-requirements/MASTER-REQUIREMENTS.md` as a follow-up.

## Affected Domains

None (governance/repository scaffolding only).

## Business Rules

None implemented at this stage.

## Security Requirements

None implemented at this stage. `.gitignore` must exclude `.env` and other secret material once
IMPLEMENTATION 01 introduces them.

## Database Impact

None.

## API Impact

None.

## UI Impact

None.

## Financial Impact

None.

## Idempotency

Not applicable.

## Concurrency

Not applicable — establishes the no-concurrent-ownership rule for future tasks (see
[BRANCHING-POLICY.md](../00-governance/BRANCHING-POLICY.md)).

## Error Handling

Not applicable.

## Tests Required

None (no code). Future stages introduce the testing gate defined in
[DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md).

## Acceptance Criteria

```
[x] Source-of-truth hierarchy defined
[x] Repository structure defined
[x] AGENTS.md baseline defined
[x] AI responsibilities defined
[x] Branch/worktree rules defined
[x] Task specification format defined
[x] Definition of Ready defined
[x] Definition of Done defined
[x] Test gate defined
[x] Review severity defined
[x] Merge gate defined
[x] Architecture change-control defined
[x] Financial implementation rules preserved
[x] Security/RBAC rules preserved
[x] Human decision boundary preserved
```

## Forbidden Changes

IMP-000 is governance/bootstrap work, but all existing locked product, architecture, database,
security, RBAC, and financial decisions remain binding throughout it. Forbidden changes include:

- altering Q1-Q20 Human Decision Register entries;
- changing Single Organization;
- changing Centralized Settlement;
- changing Modular Monolith;
- weakening Ledger accounting invariants;
- making Fund an authoritative mutable balance;
- making Partner a tenant or settlement owner;
- changing Commission semantics;
- weakening security/RBAC boundaries;
- changing same-origin API architecture;
- making unsupported infrastructure mandatory;
- weakening shared-hosting compatibility;
- implementing business features outside IMP-000 scope.

No forbidden change occurred during this task: no business feature, schema, or code was
introduced, and the Locked Decisions list in [AGENTS.md](../../AGENTS.md) was not altered.

## External Verification

None.

## Definition of Done

```
[x] Governance documents committed under docs/00-governance/
[x] AGENTS.md committed at repository root
[x] Implementation specification template committed under docs/implementation/
[x] PR and issue templates committed under .github/
[x] Repository initialized as a git repository
```

## Gate

```
IMPLEMENTATION WORK:          COMPLETE
INDEPENDENT AUDIT:            COMPLETE — see docs/audits/IMP-000-INDEPENDENT-AUDIT.md
REMEDIATION (PASSES 1-3):     COMPLETE
TARGETED RE-AUDIT (PASS 3):   PASS
ARCHITECTURE REGRESSION:      PASS
GOVERNANCE RE-AUDIT GATE:     PASS
FINAL HUMAN APPROVAL:         APPROVED ("saya setuju", 2026-09-12)
UNRESOLVED BLOCKER:           0
UNRESOLVED MAJOR:             0
GATE-IMPACT MINOR:            0
IMP-000 FINAL GATE:           PASSED
IMP-001:                      NOT YET AUTHORIZED
```

This FINAL/LOCKED status was reached because an independent targeted re-audit (Pass 3) found
zero unresolved BLOCKER/MAJOR/gate-impact-MINOR items, and a Human then explicitly approved
IMP-000. No AI agent declared this approval — see
[docs/audits/IMP-000-STAGE-GATE.md](../audits/IMP-000-STAGE-GATE.md) for the dedicated approval
record and [docs/audits/IMP-000-INDEPENDENT-AUDIT.md](../audits/IMP-000-INDEPENDENT-AUDIT.md) for
the full chronology. Reaching this gate does not, by itself, authorize IMP-001 — see the next
section.

## IMP-000 Gate vs. IMP-001 Readiness

IMP-000 reaching its final gate does NOT automatically authorize IMP-001. IMP-001 requires,
independently:

1. its own implementation specification;
2. a Definition of Ready evaluation against
   [docs/00-governance/DEFINITION-OF-DONE.md](../00-governance/DEFINITION-OF-DONE.md);
3. identification of relevant authoritative references;
4. no unresolved blocking Human Decision;
5. required Human authorization according to
   [docs/00-governance/IMPLEMENTATION-GOVERNANCE.md](../00-governance/IMPLEMENTATION-GOVERNANCE.md).

No IMP-001 specification is implemented by this remediation task.

## Next Stage (Not Yet Authorized)

IMPLEMENTATION 01 — Core Project Foundation: Laravel project bootstrap, PHP/Laravel
compatibility, Vue 3, Inertia 3, TypeScript, Tailwind 4, Vite, MySQL baseline, environment
example, testing baseline, modular application folder convention, base CI/static-check
conventions where practical, shared-hosting compatibility. No business feature implementation.
This remains the anticipated next stage only — it is NOT authorized until IMP-000's final gate
and its own Definition of Ready are satisfied.
