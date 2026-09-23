# ADR-004 — Page Builder block schema extension (additive ComponentConfigValidator fields)

## Status

ACCEPTED

Human approval evidence is recorded in full under "Approval" below, per
[docs/00-governance/CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)'s AI Approval Boundary
rule — this status was not set by an AI agent's own judgment.

## Context

IMP-006 Theme Engine is FINAL/LOCKED. CR-001-C Site Design (operator abstraction over the Theme
Engine) is FINAL/LOCKED (`docs/audits/CR-001-C-FINALIZATION.md`). CR-001-D Visual Page Builder
(`docs/implementation/CR-001-D-visual-page-builder.md`) introduces an operator-facing block
orchestration layer over the existing canonical Theme services — one Block equals one Section
containing exactly one Component — with zero schema changes and zero modifications to
`PublicRenderer.php` (CR-001-E ownership, READ-ONLY for D).

Three block-library requirements cannot be expressed with the current closed
`ComponentConfigValidator::SCHEMAS` set:

1. Campaign Carousel needs a presentation-only display hint on the existing `content_list` type
   (`grid` vs `carousel`) — same projection dataset, only Vue rendering differs (rendered by
   CR-001-E, not D).
2. Donation / Quick Donation CTA needs a presentation-only intent label on the existing
   `cta_button` type (`general` vs `donation` vs `zakat`) — styling/icon hint only, never routing
   or business logic.
3. Safe Custom Content needs a third `rich_text` source variant (`custom_html` + `body_html`)
   sanitized through the existing IMP-005 `ContentSanitizer` at write time — no new sanitizer,
   no new library.

This is the exact governance situation ADR-001 already resolved for its own additive
`content_kind` extension (`page|article` → `page|article|program|campaign`): extending the
closed validator set — even additively — is a Locked-Architecture-level change requiring its
own ADR before implementation. This ADR records the already-approved Human Decision
HD-CR001D-01; it does NOT introduce a new Human Decision.

## Decision

`App\Services\Theme\ComponentConfigValidator`'s existing schemas gain three additive, optional,
closed-enum fields — nothing removed, nothing renamed, nothing made stricter:

1. `content_list.display_mode`: `['nullable', 'string', 'in:grid,carousel']` — frontend default
   `grid` (registry `default_config`, not validator behavior).
2. `cta_button.intent`: `['nullable', 'string', 'in:general,donation,zakat']` — presentation
   metadata only; never read by any backend code for routing or business logic.
3. `rich_text.source` enum extended from `in:cms_content,caption` to
   `in:cms_content,caption,custom_html`, plus conditional
   `body_html`: `['required_if:source,custom_html', 'nullable', 'string']` — sanitized via the
   existing `App\Services\Content\ContentSanitizer::sanitize()` at write time inside
   `ThemeComponentService::create()`/`update()` before persistence, including writes from
   Page Builder, Advanced/Debug, and other canonical callers. Input config is validated first,
   only `rich_text` + `source=custom_html` is sanitized, and that sanitized config is validated
   again because sanitization can leave an empty required body. `ContentSanitizationException`
   fails closed (422), with no create/update persisted. `PageBuilderBlockService` delegates to
   this single canonical enforcement point and does not sanitize a second time. This bounded
   F-01 security extension enforces the existing HD-CR001D-01 invariant; no new Human Decision.

All three remain fixed `in:` enums — default-deny for any other value, enforced at the same
existing enforcement point (`ComponentConfigValidator::assertValid()`, called before every
save and again at theme activation). The validator remains the sole authoritative validator;
nothing in Page Builder bypasses or duplicates it.

Compatibility with existing component types is preserved: every existing persisted component
config lacks these fields, and every new rule is `nullable`/conditional, so all existing
configs continue to validate unchanged. `PublicRenderer` is untouched by D — rendering of
`display_mode=carousel`, `source=custom_html` output, and `intent` styling is an explicit
CR-001-E handoff (`docs/implementation/CR-001-D-visual-page-builder.md` §52), not implemented
here.

## Alternatives

A genuinely new component `type` per block-library entry (e.g. `carousel`, `donation_cta`,
`custom_html`) was rejected: it would fork the closed type set, require renderer dispatch
arms in D's scope (owned by CR-001-E), and contradict the "PATCH — DO NOT REWRITE" discipline
when the existing types already carry the needed semantics plus one optional hint field each.

Storing operator HTML without sanitization, or with a new sanitizer/library, was rejected by
the Human approval itself: Safe Custom Content MUST reuse the existing `ContentSanitizer` —
no new sanitizer or library unless separately authorized in the future.

## Consequences

**Easier**: the Page Builder block library (Hero, Banner, Rich Text, Campaign/Program/Article
grids, Carousel variants, Statistics, ZISWAF service cards, Gallery, Partners, Donation CTA,
Safe Custom Content, structural Zakat Calculator CTA) maps entirely onto the existing 9
closed component types — zero new types, zero schema changes.

**Harder / to watch**: CR-001-E must add the corresponding render-time arms
(`display_mode=carousel` carousel markup, `body_html` output for `source=custom_html`,
`intent` styling hint) when it integrates. Until then the new fields persist inertly —
stored, validated, sanitized, but rendered with existing behavior. A future 4th `intent` value
or 3rd `display_mode` value requires its own additive change through this same governance
path, not an ad hoc operator-supplied string.

Rollback/compatibility semantics: removing this ADR's fields later (reverting the validator
to the pre-D schemas) leaves already-persisted configs carrying unknown keys. Those keys are
validated-but-unfiltered: `ComponentConfigValidator::assertValid()` validates only known
fields, while canonical callers persist the original config array — including any unknown
keys it contains. This is pre-existing IMP-006 behavior (F-15, accepted non-gating debt),
not something CR-001-D introduces or expands into executable behavior: unknown keys are
never interpreted as routes, types, templates, or code by any backend path. Any future
canonical normalization/filtering of stored configs requires its own separate governed
change-control. No migration is involved in either direction.

## Security Impact

None weakened. Every new field is allowlisted/closed-enum only — no arbitrary executable
configuration of any kind:

- No arbitrary script, JavaScript, event handlers, PHP, Blade, Vue, SQL, or arbitrary
  `<iframe>` is permitted anywhere in this contract. `body_html` passes through the reused
  `ContentSanitizer` allowlist (`DANGEROUS_TAGS` rejects `script`/`style`/`iframe`/`object`/
  `embed`/`form`/`input`; event-handler attributes are stripped; `href` schemes limited to
  `https,http,mailto,tel`; `<img>` requires a valid `data-media` ULID token; 200KB
  `MAX_BYTES` bound) — the identical wall IMP-005 already enforces for CMS body HTML.
- `cta_button.intent` is never interpreted as a route, business rule, or routing decision.
  Destinations remain the canonical `SYSTEM_ROUTE`/`CMS_CONTENT`/`EXTERNAL_URL` union
  validated exactly as today.
- Server-side validation is mandatory for every field; nothing is client-only.

## Database Impact

None. No migration. No new table, column, or foreign key on any `theme_*` table. The
`rich_text.source` enum extension is application-level validation only; no DB column change.

## Financial Impact

None. No Donation routing, no Payment creation, no Zakat calculation, no business transition
is performed. `intent` carries presentation meaning only.

## Migration Impact

None.

## Approval

```
Approval Authority:         Human (CR-001-D Human Spec Gate)
Human Approver:             The Human directing the CR-001-D session
Approval Date:              2026-09-22 (spec §54 records HD-CR001D-01 APPROVED)
Approval Evidence:          docs/implementation/CR-001-D-visual-page-builder.md §54
                            (HD-CR001D-01 — RESOLVED / APPROVED additive/allowlisted/
                            sanitized, with binding Human-mandated constraints carried
                            through §11/§12.1/§14/§43); governed recon
                            docs/ai-handoff/CR-001/D-RECON.md §4/§33.
Approved Reference:          HD-CR001D-01 (authorizes this ADR's pre-approved content scope;
                             it does not itself constitute the ADR)
```

## References

- Related spec: [docs/implementation/CR-001-D-visual-page-builder.md](../implementation/CR-001-D-visual-page-builder.md) §11–§14, §43, §52, §54
- Related recon: [docs/ai-handoff/CR-001/D-RECON.md](../ai-handoff/CR-001/D-RECON.md) §11–§18, §33
- Precedent ADR: [ADR-001-theme-content-projection-amendment.md](./ADR-001-theme-content-projection-amendment.md)
- Amended file: `app/Services/Theme/ComponentConfigValidator.php`
- New files: `app/Services/Theme/PageBuilderBlockService.php`, `app/Services/Theme/PageBuilderBlockRegistry.php`, `app/Http/Controllers/Admin/PageBuilderController.php`
- Handoff owner for rendering: CR-001-E (`PublicRenderer.php` untouched by D)
- Tests: `tests/Unit/Theme/ComponentConfigValidatorTest.php`, `tests/Feature/Theme/PageBuilder*Test.php`
