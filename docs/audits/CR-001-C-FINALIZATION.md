# CR-001-C CMS / Site Design Foundation — Finalization

## Status

FINAL / LOCKED

## CR

CR-001 — Public Experience, Visual CMS, ZISWAF UX & Admin Experience Architecture V2

## Phase

C — CMS / Site Design Foundation

## Human CR-001-C Phase Gate

APPROVED

## Upstream Gates (pre-existing, not renegotiated by this finalization)

| Gate | Status | Reference |
|---|---|---|
| Architecture Gate (v2) | APPROVED / LOCKED | `cc8de3341f31451b93877f20a4299609d5b5e7e5` |
| CR-001-B | FINAL / LOCKED | `6806bf22d733de30a8798bebed558f54b1457768` |
| CR-001-C Recon (Qwen) | FINAL / HUMAN-APPROVED | `docs/ai-handoff/CR-001/C-RECON.md` |
| CR-001-C Execution Routing | FINAL / HUMAN-APPROVED | `docs/ai-handoff/CR-001/EXECUTION-ROUTING-C-TO-J.md` |

This finalization does not amend, rewrite, or squash any upstream commit.

## Implementation Status

IMPLEMENTED. Primary implementation writer: `meta/muse-spark-1.3-contributor`,
per `docs/ai-handoff/CR-001/C-EVIDENCE.md`. Consolidated remediation
(G-01, G-02, N-06, N-07) and final Codex gating remediation
(CODEX-CR001C-01/02/03) applied and verified — see Evidence Trail.

## DeepSeek Independent Technical Review

CLOSED — diff-first review completed per the CR-001-C standard flow; no
unresolved findings blocking this phase gate.

## Codex Final Semantic / Closure Audit

CLOSED

| Finding | Status |
|---|---|
| CODEX-CR001C-01 (DRAFT-only mutations) | CLOSED |
| CODEX-CR001C-02 (faithful ACTIVE → DRAFT cloning) | CLOSED |
| CODEX-CR001C-03 (operator publish action) | CLOSED |
| G-01 (Branding logo/favicon operator UX) | CLOSED |
| G-02 (Navigation item management) | CLOSED |

## Final Full Regression

```
Tests:       1272
Passed:      1269
Failed:      0
Errors:      0
Skipped:     3 (expected pre-existing MySQL/concurrency-gated)
Assertions:  4103
Exit status: 0
```

Theme suite: **146 / 146 PASS**, 578 assertions.

Type-check: PASS. Build: PASS. `vendor/bin/pint --test`: PASS.
`composer audit`: PASS (no advisories). `git diff --check`: PASS.

## Findings Summary

| Severity | Count |
|---|---|
| BLOCKER | 0 |
| MAJOR | 0 |

New Human Decisions required: **0**. Gating regression: **0**.

## Accepted Non-Gating Baseline (preserved, not remediated)

- N-01: transaction/file-I/O architecture unchanged (no redesign).
- N-02: legacy `visible` semantics unchanged.
- N-03: no CR-001-E rendering implemented.
- N-04: no publish concurrency expansion.
- N-05: no broad UI polish.
- N-08: clone asset scope unchanged (ACTIVE-only copy per C-RECON §24.1A.D).
- N-09: general navigation-tree hardening out of scope for this pass.

## Implemented Scope

- `App\Http\Controllers\Admin\SiteDesignController` — thin orchestration over
  canonical Theme/CMS services (index, branding, logo upload, menus,
  navigation items, clone-to-draft, preview, publish).
- `App\Services\Theme\SiteDesignCloneService` — atomic copy-on-write
  ACTIVE→DRAFT aggregate clone (templates/sections/components, navigation,
  branding, theme assets) with filesystem compensation on failure; source
  aggregate never mutated.
- `App\Services\Theme\ThemeNavigationService::createItem()` — extended to
  accept/persist `visible_desktop`/`visible_mobile` (default TRUE when
  omitted); `updateItem()` added (canonical navigation item edit: label,
  destination, parent, visibility; cross-menu/too-deep/self-parent
  rejected). No schema change.
- `resources/js/Pages/Admin/SiteDesign/{Overview,Branding,Menus,Preview}.vue`
  — operator-facing Site Design admin surface; DRAFT-only mutation
  affordances, read-only presentation for non-DRAFT themes.
- `routes/web.php` — additive `admin/site-design/*` route group (append-only;
  no existing route modified or removed).
- Test suites: `SiteDesignCloneServiceTest`, `SiteDesignControllerTest`,
  `ThemeNavigationVisibilityTest`, `SiteDesignBrandingTest`,
  `SiteDesignNavigationItemTest`, `SiteDesignCloneFailureTest`,
  `SiteDesignLeastPrivilegeTest`, `SiteDesignDraftOnlyTest`,
  `SiteDesignCloneFidelityTest`, `SiteDesignPublishActionTest`,
  `SiteDesignReadOnlyTemplateTest`.

## Explicit Exclusions

- `PublicRenderer.php` — **NOT modified by CR-001-C** (owner: CR-001-E;
  the dirty diff present against it in the working tree is pre-existing
  protected FE-CHK-009 work, untouched by this phase).
- Visual Page Builder (CR-001-D) — not started, not authorized by this gate.
- Any FE-CHK-009 dirty working-tree file (public donation/payment
  controllers, `PublicCampaignController`, `PublicProgramController`,
  public Vue pages/components, `DonationHttpTest`/`PaymentHttpTest`,
  POST-IMP-009 implementation doc) — preserved untouched.
- `CODEX-FE009-01` remediation — reserved for CR-001-J.
- Schema/migrations — zero.

## Other Status

CODEX-FE009-01: **OPEN / VALID / UNRESOLVED** — owner **CR-001-J**. CR-001-C
has zero intersection with the affected FE-CHK-009 files.

FE-CHK-009: **PRESERVED** — its dirty working-tree files remain unstaged and
untouched by this finalization.

CR-001-D: **NOT STARTED** — not authorized by this gate.

IMP-010 (Ledger): **BLOCKED** — unaffected by this finalization.

Database modified: **NO**. Schema changes: **0**. Migrations: **0**.

## Evidence Trail

Full implementation and remediation history:
`docs/ai-handoff/CR-001/C-EVIDENCE.md`.

Recon file map (Qwen, Human-approved):
`docs/ai-handoff/CR-001/C-RECON.md`.

Execution routing (Human-approved, C through J):
`docs/ai-handoff/CR-001/EXECUTION-ROUTING-C-TO-J.md`.

## Finalization Commit

`b72323dc832369dcfe5ae377fe5aaa50ae7ce9f2` — `feat(site-design): finalize CR-001-C foundation`

## Push / Remote Verification

`<PENDING — push to origin/master and `local HEAD == origin/master`
verification occur after this evidence commit; see the CR-001-C
finalization task record for the resulting push/remote-verification
result>`
