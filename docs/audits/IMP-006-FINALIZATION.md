# IMP-006 — Finalization Record

## Summary

```
IMP:                                IMP-006 — Theme Engine (Presentation System)

Standing Amendment V2 assignment:   Qwen 3.8 Flash — UNCHANGED for any other IMP.
IMP-006-SPECIFIC OVERRIDE:          Human, explicit, this stage only.

Implementation Owner (this stage):  Claude Code
Bound Model:                        Claude Sonnet 5
Exact Model ID:                     claude-sonnet-5
Execution Mode:                     SINGLE AI AGENT — IMP-006 ONLY

Human Model Approval:               "Saya setuju. IMP-006 Claude Model Approved... MODEL BINDING
                                     STATUS: BOUND."
Human Implementation Authorization: "Saya setuju. IMP-006 Implementation Authorized."
Human Stage Gate:                   APPROVED ("Saya setuju. IMP-006 Stage Gate Approved.")
Human Browser Visual Review:        PASS

Specification branch:               spec/006-theme-engine
Specification commit:               ea1193c
Implementation branch:              impl/006-theme-engine
Implementation final HEAD:          1870984

Merge status:                       NOT PERFORMED THIS PASS — see "Merge / Push Authorization"
Push status:                        NOT PERFORMED THIS PASS — see "Merge / Push Authorization"
```

## Sequence to Stage Gate

```
1.  Human ownership override: IMP-006 reassigned from the standing Amendment V2 prospective
    owner (Qwen 3.8 Flash) to Claude Code, for IMP-006 only — no change to the global ownership
    matrix, no effect on IMP-007 or any other stage.
2.  Model binding: Claude Code version 2.1.270 / Claude Sonnet 5 (claude-sonnet-5) resolved from
    runtime environment evidence and Human-approved before any substantive specification work
    began.
3.  Specification phase: repository/architecture discovery (governance hierarchy, Q30/
    HD-IMP005-02, IMP-005 §§6-8/21/24 boundary text, existing frontend conventions, existing
    RBAC/audit patterns, existing media validation pipeline — all quoted verbatim, not
    paraphrased from memory) followed by drafting docs/implementation/IMP-006-theme-engine.md.
4.  Specification self-audit (single-agent discipline: specification -> stop editing -> read-only
    audit -> freeze findings -> patch -> re-audit): found and patched ~30 internal
    cross-reference errors (a systematic section-numbering offset), two ambiguous
    own-document-vs-IMP-005 citations, one clarity issue, and one real content gap (a claimed
    "ownership table reproduced in full" that was never actually included). Final tally:
    BLOCKER 0, MAJOR 0, MINOR 0, EDITORIAL 3 (all patched).
5.  Human Implementation Authorization: "IMP-006 Implementation Authorized" — explicit, separate
    from the specification PASS itself, per the standing instruction that a specification PASS
    alone never authorizes implementation.
6.  Implementation: existing-code audit (confirmed greenfield — nothing theme-related existed
    anywhere in the repository outside IMP-005's own boundary-documentation cross-references);
    schema (9 tables + the theme_activation singleton, mirroring cms_homepage_assignment
    exactly); models; services (ThemeService, ThemeActivationService, ThemeTemplateService,
    ThemeSectionService, ThemeComponentService, ThemeNavigationService, ThemeBrandingService,
    ThemeAssetService); validators (ComponentConfigValidator — the closed 9-type schema and the
    enforcement point for "configuration is data, never code" — NavigationDestinationValidator/
    Resolver, BrandingConfigValidator); theme.* authorization (ThemePolicy, ThemeScopeResolver)
    and audit (ThemeAuditEventRegistrar/Logger, 12 events) integration; the public rendering
    pipeline IMP-005 §8 described but explicitly left unbuilt (PublicContentController,
    PublicRenderer, with full section-22 rendering-failure fallback handling); admin UI
    (ThemeController + Theme/Index.vue + Theme/Show.vue) in the same fixed backoffice design
    system IMP-005's admin screens use; ThemeSystemDefaultSeeder (the code-shipped,
    never-deletable fallback theme).
7.  Tests: 61 IMP-006-specific tests (authorization — including the single-organization-baseline
    "cross-scope" analog and security-restricted/disabled-identity denial, mirroring IMP-005's
    own re-audited MediaPolicy discipline — activation/lock-order behavior, CRUD, asset
    validation matrix, schema-constraint proof that concurrency guarantees are real database
    invariants not just application pre-checks, and public-rendering-pipeline end-to-end
    coverage). Two pre-existing tests updated for expected, disclosed behavior changes
    (AuditFoundationTest's registry count; FoundationSmokeTest's root-route assertion, since the
    retired static Foundation.vue placeholder no longer backs `/`).
8.  Self-audit of the implementation (read-only re-read against the approved specification):
    found and patched two real defects — `card_grid`'s `content_list` mode returning the wrong
    prop shape (missing resolved URLs), and a pre-existing MySQL-only test-infrastructure bug in
    IMP-004's `TruncatesInMemorySqlite` (not introduced by IMP-006, only exposed by it: blanket
    table truncation silently and permanently deleted singleton-table seed rows for the rest of
    a `phpunit` process, breaking later unrelated tests) — reproduced deterministically and
    confirmed fixed (126/126, then the full 590-test MySQL suite green).
9.  MySQL verification: full application suite (not merely IMP-006's own tests) run against real
    MySQL 8 both before and after the fix in step 8, confirming no regression and no fabricated
    PASS.
10. Human Final Technical Report review + Human Browser Visual Review: PASS.
11. Human Stage Gate: APPROVED ("Saya setuju. IMP-006 Stage Gate Approved.").
12. This finalization record.
```

## Final Finding Disposition

```
BLOCKER:                  0
MAJOR:                    0
MINOR:                    2 (accepted by Human as non-gate-impacting v1 limitations — recorded
                             here permanently, not represented as never having existed, per the
                             Human's explicit instruction):
                             1. Admin UI component configuration is edited as raw JSON in a
                                textarea rather than a bespoke form per component type. Satisfies
                                the specification's "no page-builder, ordinary admin forms"
                                constraint literally; less polished than a bespoke-per-type form
                                would be.
                             2. Theme activation's concurrency guarantee is verified via real
                                MySQL row-locking, a real singleton CHECK constraint, and real
                                unique constraints (ThemeSchemaConstraintsTest) — but no dedicated
                                dual-process live-race test was authored specifically for theme
                                activation (mirroring the sequential-simulation pattern IMP-005
                                itself used for most of its own concurrency tests).
EDITORIAL:                0
OPEN HUMAN DECISIONS:     0
GATE-IMPACT FINDINGS:     0
```

## Locked Human Decisions / Contracts — Preserved

```
Q30 (HD-IMP005-02)                                    — PASS / PRESERVED — navigation presentation
                                                          implemented per its exact terms; navigation
                                                          visibility never substitutes for backend
                                                          authorization (tested).
IMP-005 §§6-8/21/24 boundary                          — PASS / PRESERVED — no IMP-005 file
                                                          modified; Theme Engine consumes IMP-005's
                                                          read contracts (ContentResolverService,
                                                          HomepageContentResolver,
                                                          MediaTokenResolver) read-only.
Presentation-only boundary (section 6)                — PASS — no FK from any theme_* table to a
                                                          financial/business-domain table; no
                                                          business/authorization authority in any
                                                          Theme Engine construct.
Transaction Ownership Invariant (IMP-004, LOCKED)     — PASS — preserved via the same structural
                                                          proof IMP-005's finalization established
                                                          (no theme.* event is DENIAL_DURABLE-
                                                          capable; the entry guard does not apply
                                                          to any IMP-006 write service).
Audit criticality (all theme.* NonCritical)           — PASS
Q26/Q27/Q28                                            — PASS / PRESERVED — no new audit read scope,
                                                          no new retention/purge mechanism.
Shared-hosting compatibility                          — PASS — no Redis/Supervisor/PM2/WebSocket/
                                                          mandatory Node-runtime-in-production
                                                          introduced; Vite remains build-time only.
```

## Final Test / Quality Evidence

```
SQLite:              589 passed, 1 pre-existing unrelated skip, 0 failures, 1850 assertions
MySQL 8:             590 passed, 0 skipped, 0 failures, 1858 assertions (real local MySQL 8
                     instance — the actual dev database, verified twice: before and after the
                     TruncatesInMemorySqlite fix, confirming no regression)
Pint:                PASS
TypeScript:          PASS (npm run type-check)
Vite:                PASS (production build succeeds)
Composer audit:      PASS (no security advisories)
git diff --check:    PASS
Human browser review: PASS (Human-performed; this agent did not and does not claim to have
                     driven a browser session)
```

## Ownership / Scope Note

Identical in kind to IMP-005's own note: IMP-006's implementation ownership was assigned to
Claude Code by direct, explicit Human override of the standing Amendment V2 prospective
assignment (Qwen 3.8 Flash), with a SEPARATE, exact-model-binding approval step preceding any
substantive work. That override authorized model binding, specification, implementation,
remediation, and self-audit — it did not, and does not, extend to merge or push authority. See
"Merge / Push Authorization" below.

## Merge / Push Authorization

Per `docs/00-governance/AI-WORKFLOW.md`, "Per-Task Loop", step 8 (verbatim): **"Human reviews and
merges."** This is the same standing, general workflow rule cited in IMP-005's own finalization
record and has not been superseded, narrowed, or reassigned for IMP-006 by any Human instruction
in this session — the Human Stage Gate approval recorded above is a distinct step from step 8's
merge action.

`docs/00-governance/BRANCHING-POLICY.md` records a configured remote (`origin`, corrected during
IMP-005's own finalization pass — see that document's "Bootstrap Repository State"). A configured
remote changes nothing about WHO may authorize a push; it only means a push is technically
possible once separately authorized.

**Conclusion: merge requires separate, explicit Human authorization (or Human-performed action)
per AI-WORKFLOW.md step 8; push likewise requires separate, explicit Human authorization.**
Neither was performed as part of this finalization record.

## Status

```
IMP-006 status:  STAGE GATE APPROVED — FINALIZATION PENDING (merge)
IMP-007:         NOT STARTED
```

## Related Records

- [docs/implementation/IMP-006-theme-engine.md](../implementation/IMP-006-theme-engine.md) — the
  approved specification (self-audited, SPEC-AUDIT-01/02/03 all patched).
- [docs/implementation/IMP-005-cms.md](../implementation/IMP-005-cms.md) — the LOCKED specification
  this stage's contracts are consumed from, unmodified.
- [docs/audits/IMP-005-FINALIZATION.md](IMP-005-FINALIZATION.md) — the precedent finalization
  record this document mirrors, including the identical merge/push authorization analysis.
- [docs/00-governance/AI-WORKFLOW.md](../00-governance/AI-WORKFLOW.md) — the governing merge/push
  procedure cited above.
- [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md) — the
  standing Amendment V2 assignment this stage's ownership override does not alter.
