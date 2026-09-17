# ADR-001 — Theme content_list/card_grid content_kind extended to program/campaign

## Status

ACCEPTED

Human approval evidence is recorded in full under "Approval" below, per
[docs/00-governance/CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)'s AI Approval Boundary
rule — this status was not set by an AI agent's own judgment.

## Context

IMP-006 Theme Engine is FINAL/LOCKED (`docs/audits/IMP-006-FINALIZATION.md`). IMP-007 Campaign +
Program + Fund is still under active implementation/presentation remediation at the time of this
amendment — its Human Stage Gate has NOT yet been approved (`docs/implementation/
IMP-007-campaign-program-fund.md` remains the approved specification only) — its specification's
own Non-Goals explicitly deferred any Theme integration to "a separate, future LOCKED-CONTRACT
proposal against IMP-006's closed content_kind list". A Human Browser Review of the running
IMP-007 implementation found the public homepage unacceptably sparse and asked for a root-cause
audit; that audit found `ComponentConfigValidator`'s closed `content_kind` enum
(`page|article` only) was the structural reason Program/Campaign content could not be surfaced
through the existing, generic `content_list`/`card_grid` Theme components — reported as
`LOCKED IMP-006 CHANGE CONTROL REQUIRED` per the governing "PATCH — DO NOT REWRITE" discipline.
See [docs/decisions/ACR-001-theme-content-projection.md](../decisions/ACR-001-theme-content-projection.md)
for the full problem/alternatives/impact analysis this decision is based on.

## Decision

`App\Services\Theme\ComponentConfigValidator`'s `content_list` schema, and `card_grid`'s
`content_kind` field (active when `mode=content_list`), now validate `content_kind` against the
closed list `page|article|program|campaign` — additive only, still a fixed `in:` enum, still
default-deny for any other value.

`App\Services\Theme\PublicRenderer::renderContentList()` resolves `program`/`campaign` via two
IMP-007-owned, read-only projection resolvers — `App\Services\Campaign\ProgramProjectionResolver`
(new) and `App\Services\Campaign\CampaignProjectionResolver` (already existed in the IMP-007
implementation, deliberately unwired pending this exact change control) — never by querying the
`programs`/`campaigns` tables directly from Theme code. The pre-existing `page`/`article` branch
is unchanged.

Domain ownership is unchanged and re-affirmed: IMP-005 still owns canonical Page/Article/Media
truth; IMP-006 still owns presentation only; IMP-007 still owns canonical Program/Campaign/Fund
truth. No Program/Campaign business data is copied into any `theme_*` table or component JSON —
every render calls the owning domain's resolver fresh.

Campaign donation-eligibility (HD-IMP007-03) is computed exclusively by the canonical
`App\Services\Campaign\CampaignEligibilityResolver`, called from within
`CampaignProjectionResolver` — never duplicated as a date calculation inside `PublicRenderer` or
any Vue component. Visibility in the projection is status-only (`PUBLISHED`), matching the
existing public Campaign index page's own precedent; a not-currently-eligible published Campaign
still appears, with `metadata.is_donation_eligible: false` for the presentation layer to reflect.

No IMP-008 (Donation) concept is introduced anywhere in this change — no collected amount, donor
count, progress percentage, or financial balance is ever included in the projection. IMP-008
remains NOT STARTED.

## Alternatives

See [ACR-001](../decisions/ACR-001-theme-content-projection.md) "Alternatives" — a separate
Campaign-specific Theme system and copying business data into Theme configuration were both
rejected as directly contrary to the Human authorization's explicit constraints.

## Consequences

**Easier**: an admin can now feature Programs and/or Campaigns on the homepage (or any Theme
template) using the exact same, already-familiar `content_list`/`card_grid` admin UI used for
Pages/Articles — no new admin surface, no new component type, no new page-builder concept.

**Harder / to watch**: `PublicRenderer` (an IMP-006 file) now has a compile-time dependency on
two IMP-007 classes. This is a deliberate, narrow coupling (read-only projection consumption,
identical in spirit to IMP-006 already consuming IMP-005's `ContentResolverService`/
`MediaTokenResolver`), not a merge of the two domains' write paths or authorization models. A
future IMP-006 change must continue to treat these two call sites as an explicit, documented
external dependency rather than "just more Theme code."

## Security Impact

None weakened. The `content_kind` enum remains closed/default-deny — an unregistered value (e.g.
`donation`, or any string resembling a class/service/SQL identifier) is rejected by the same
`Validator::make(...)->fails()` path as before, at the same enforcement point
(`ComponentConfigValidator::assertValid()`, called before every save and again at theme
activation). The resolver invoked per `content_kind` is fixed application code selected by a
`match`-equivalent `if` chain in `PublicRenderer` — the stored config value is never used to
construct a class name, call a dynamic method, or build a raw query. No new `v-html` usage was
introduced in `ThemeRender.vue`'s content_list rendering (only escaped Vue interpolation and
`:src`/`:href` bindings).

## Database Impact

None. No migration. No new table, column, or foreign key on any `theme_*`, `programs`, or
`campaigns` table.

## Financial Impact

None. `target_amount_minor`/`currency` (already-existing IMP-007 columns, HD-IMP007-02) may be
displayed via the existing `Money` value object's `format()` — no new financial computation, no
Ledger/Payment/Donation concept, no aggregation across Campaigns.

## Migration Impact

None.

## Approval

```
Approval Authority:         Human (session directive)
Human Approver:             The Human directing this Claude Code session
Approval Date:              2026-09-16
Approval Evidence:          Verbatim message: "HUMAN CHANGE CONTROL AUTHORIZATION / IMP-006
                            THEME ENGINE — TARGETED CONTENT PROJECTION AMENDMENT / FOR IMP-007
                            INTEGRATION ... Saya setuju. The reported locked-contract change is
                            explicitly authorized. CHANGE CONTROL SCOPE: TARGETED / ADDITIVE
                            ONLY ..." — full text preserved in this conversation's transcript and
                            summarized in ACR-001's "Human Approval Record".
Approved Reference:          docs/decisions/ACR-001-theme-content-projection.md
```

## References

- Related ACR: [docs/decisions/ACR-001-theme-content-projection.md](../decisions/ACR-001-theme-content-projection.md)
- Related Master Requirements: [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §6
- Related Implementation Task(s): [docs/implementation/IMP-006-theme-engine.md](../implementation/IMP-006-theme-engine.md), [docs/implementation/IMP-007-campaign-program-fund.md](../implementation/IMP-007-campaign-program-fund.md)
- Amended files: `app/Services/Theme/ComponentConfigValidator.php`, `app/Services/Theme/PublicRenderer.php`, `resources/js/Pages/Public/ThemeRender.vue`
- New files: `app/Services/Campaign/ProgramProjectionResolver.php`; `App\Services\Campaign\CampaignProjectionResolver` (pre-existing, now wired)
- Tests: `tests/Unit/Theme/ComponentConfigValidatorTest.php`, `tests/Feature/Theme/ThemeCampaignProjectionTest.php`
