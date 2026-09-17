# ACR-001 — Extend Theme content_list/card_grid content_kind to include program/campaign

## Problem

The public homepage (and any other Theme-rendered page) had no way to feature Program or
Campaign content, because `ComponentConfigValidator`'s `content_list` (and `card_grid`'s
`mode=content_list`) schema enforced a closed `content_kind` enum of `page|article` only —
IMP-006's own locked validation contract. A Human Browser Review of the running IMP-007
implementation found the resulting homepage unacceptably sparse for a philanthropy platform and
explicitly asked whether wiring the already-built `CampaignProjectionResolver` into an existing
generic Theme component was a compatible extension.

## Existing Rule

`App\Services\Theme\ComponentConfigValidator::SCHEMAS['content_list']['content_kind']` and
`SCHEMAS['card_grid']['content_kind']` (when `mode=content_list`) both validate against
`in:page,article` — a closed, fixed list (docs/implementation/IMP-006-theme-engine.md section
11/21, "Theme configuration is data, never code"). This is IMP-006 LOCKED, FINAL.

## Proposed Change

Add `program` and `campaign` as two additional, explicit values in that same closed `in:` list —
no other validation behavior changes. `PublicRenderer::renderContentList()` gains two new
branches (`program`, `campaign`) that delegate to IMP-007's own canonical read projections
(`ProgramProjectionResolver`, `CampaignProjectionResolver` — the latter already existed,
explicitly built in the IMP-007 implementation but deliberately left unwired pending exactly this
change control) rather than querying `programs`/`campaigns` directly from Theme code. The
existing `page`/`article` branch is untouched.

## Alternatives

1. **Build a separate "Campaign Theme" system** — rejected: duplicates IMP-006 entirely, directly
   contrary to the Human authorization's explicit "Do NOT create a separate Campaign-specific
   Theme Engine."
2. **Copy Campaign/Program data into Theme tables/JSON at publish time** — rejected: would make
   Theme configuration the source of truth for business data, violating IMP-007's own domain
   ownership and the Human authorization's explicit "MUST NOT copy Campaign or Program business
   truth into Theme tables or JSON configuration."
3. **Leave the homepage without Program/Campaign content** — the status quo prior to this ACR;
   insufficient per the Human's own Browser Review finding.
4. **(Chosen) Widen the closed `content_kind` enum by exactly two values, resolved via
   already-built IMP-007 projection resolvers** — smallest change that satisfies the requirement
   while preserving every other locked guarantee.

## Impact

- **Requirements**: satisfies the Human Browser Review's homepage-composition finding.
- **Database**: none. No new table, column, or migration. No Theme table gains a Campaign/Program
  foreign key or JSON reference — the `content_kind` string is the only stored configuration, and
  it is resolved fresh at every render.
- **Security**: none weakened — the enum remains closed/default-deny; a value outside
  `page,article,program,campaign` still fails validation exactly as before. No new SQL, model
  class, or service identifier can be injected via Theme configuration — the resolver class used
  per kind is fixed application code, never derived from the stored config value.
- **API**: two new fields possible in `content_list`/`card_grid` component props
  (`image_url`, `metadata`) — additive only; existing `ulid`/`title`/`url` fields for `page`/
  `article` are unchanged.
- **Finance**: none. Campaign cards may show the existing non-authoritative `target_amount_minor`
  (via `Money::format()`) and `HD-IMP007-03` donation-eligibility — never a collected amount,
  donor count, progress percentage, or any IMP-008 concept (IMP-008 does not exist).
- **Testing**: new adversarial validator tests (accepts program/campaign, still rejects an
  unregistered kind), new projection-rendering tests (Program/Campaign happy path, empty result,
  unpublished exclusion, future/ended-period eligibility via the canonical
  `CampaignEligibilityResolver` — never a duplicated date calculation), and an explicit
  page-content_list regression test proving the original branch is unaffected.

## Migration Impact

None — no schema change.

## Change Classification

- LOCKED ARCHITECTURE (amends a locked IMP-006 validation contract)

## Human Decision Required

YES

## Recommendation

AI-drafted recommendation (already delivered in full, prior to this ACR being drafted, as the
"IMP-006 TARGETED CONTENT PROJECTION AMENDMENT" section of the Human Browser Review response):
approve as a **targeted, additive-only** amendment — extend the closed enum by exactly two
values, delegate to the already-built IMP-007 projection resolvers, keep every other IMP-006
guarantee (closed component-type set, closed destination union, audit/authorization architecture)
completely unchanged. Do not reopen IMP-006 broadly.

## Human Approval Record

```
Status:                     APPROVED
Approver:                   (the Human directing this session)
Approver Type:              HUMAN
Decision:                   "Saya setuju. The reported locked-contract change is explicitly
                             authorized." — full message: "HUMAN CHANGE CONTROL AUTHORIZATION /
                             IMP-006 THEME ENGINE — TARGETED CONTENT PROJECTION AMENDMENT / FOR
                             IMP-007 INTEGRATION", specifying CHANGE CONTROL SCOPE: TARGETED /
                             ADDITIVE ONLY, and explicitly listing the authorized change
                             (content_kind: page/article/program/campaign), domain-ownership
                             preservation rules, the availability/no-fabrication constraints, and
                             the required test coverage.
Decision Date:               2026-09-16 (session date)
Evidence / Reference:         This conversation's Human message beginning "# HUMAN CHANGE CONTROL
                             AUTHORIZATION" / "# IMP-006 THEME ENGINE — TARGETED CONTENT
                             PROJECTION AMENDMENT"; recorded verbatim in
                             docs/adr/ADR-001-theme-content-projection-amendment.md.
```

## References

- Related ADR: [docs/adr/ADR-001-theme-content-projection-amendment.md](../adr/ADR-001-theme-content-projection-amendment.md)
- Related Master Requirements: [docs/01-requirements/MASTER-REQUIREMENTS.md](../01-requirements/MASTER-REQUIREMENTS.md) §6 (Campaign/Program/Fund domains)
- Related Implementation Task(s): IMP-006 (`docs/implementation/IMP-006-theme-engine.md`), IMP-007
  (`docs/implementation/IMP-007-campaign-program-fund.md`)
