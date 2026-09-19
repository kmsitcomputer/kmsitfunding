# ADR-002 — IMP-004 `AuditActorKind::Unauthenticated` scope widened for registered successful guest actions

## Status

ACCEPTED

Human approval evidence is recorded in full under "Approval" below, per
[docs/00-governance/CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)'s AI Approval Boundary
rule — this status was not set by an AI agent's own judgment.

## Context

IMP-004 Audit + Governance Foundation is `FINAL / LOCKED`. Its "Canonical Actor (Pre-Principal
Cases)" section (`docs/implementation/IMP-004-audit-governance-foundation.md`) defines
`AuditActorKind::Unauthenticated` (`actor_principal_id` NULL, a fixed registry-supplied
`execution_context` constant) and scopes it as "Legitimate ONLY for an event that is itself
evidence of a failed/incomplete authentication attempt" — its only existing registered use is
`identity.session.login_failed`.

During IMP-008 (Donation) specification, this exact restriction became a real problem: a guest
donor completing a Donation successfully, with no Principal, needs a `donation.created` audit
event that is honest about having no verified identity — but is not itself evidence of a *failed*
authentication attempt (the opposite: a legitimate, successful, intentionally-unauthenticated
public action). Reusing `System` would misrepresent a human-originated action as
automation-originated. Inventing a new actor kind (e.g. `GuestSubmission`) was considered and
rejected as unnecessary, since a mechanism already exists and is structurally sufficient — only
its documented scope is too narrow.

This was raised to the Human as OQ-008-06 in `docs/implementation/IMP-008-donation.md`'s Open
Human Decisions (specification commit `883f889`), with the full technical analysis (existing enum
evidence, options, consequences) delivered as this session's "IMP-008 — Human Decision Brief."
The Human decided directly (HD-IMP008-06, verbatim below), authorizing this amendment without a
separately-drafted ACR first — mirroring the direct-Human-decision precedent already established
in this repository for GOV-MM-004/GOV-MM-005
(`docs/00-governance/MULTI-MODEL-OWNERSHIP.md`), where the Human's own decision message already
supplied the full scope/rationale a drafted ACR would otherwise exist to capture.

## Decision

`AuditActorKind::Unauthenticated` (unchanged enum value — no new case added, no existing case
removed) MAY now be legitimately used for **two** categories of registered event, not one:

```
1. Evidence of a failed/incomplete authentication attempt (unchanged — the ONLY category
   previously authorized; identity.session.login_failed remains its example).
2. A legitimate, successful action performed through an explicitly registered public
   unauthenticated entry point (NEW — authorized by this amendment).
```

This is **not** a general-purpose "system did something" or "nobody was authenticated" bucket —
the existing registry discipline is unchanged and fully preserved:

```
- Only an explicitly registered (event_type, event_version) entry may declare
  actor_principal_kind = 'unauthenticated' — never inferred, never caller-selectable.
- execution_context remains a FIXED, registry-supplied constant per registered entry point —
  never free-form caller input, exactly as already locked.
- actor_principal_id remains NULL for every unauthenticated-kind row, exactly as already locked.
- No caller-supplied arbitrary actor identity is ever trusted — unchanged.
- Category 2 usage requires the registering IMP's own AuditEventRegistrar to declare
  AuditActorKind::Unauthenticated in that event's actorKinds array with its own dedicated
  executionContext constant (e.g. "http:donation:guest_created" for IMP-008's donation.created) —
  it is never reused across unrelated entry points.
```

First registered Category 2 use: `donation.created` (IMP-008, when the acting party is a guest —
i.e. `donor_principal_id` is null on the Donation being recorded), with `execution_context =
"http:donation:guest_created"`. No other IMP-008 event uses Category 2 — `donation.succeeded`,
`donation.failed`, and `donation.expired` are System-actor events regardless of whether the
underlying Donation was created by a guest or an authenticated donor (see IMP-008 §Audit
Requirements); `donation.cancelled` is Human-actor only, since guest self-service cancellation is
not supported (HD-IMP008-05B) — there is no guest-actor `donation.cancelled` event to register.

## Alternatives

```
A. Reuse AuditActorKind::System for guest-originated events, with guest_name/guest_email in the
   metadata payload instead of the actor identity.
   REJECTED — misrepresents a human-originated action as automation-originated; would make audit
   review less accurate, not more, and README/registry documentation would need to explain the
   exception anyway, defeating the simplicity the option was meant to offer.

B. Add a new AuditActorKind case (e.g. GuestSubmission) scoped precisely to "unauthenticated but
   successful, human-originated action."
   REJECTED as unnecessary — Unauthenticated already models exactly this shape (no
   actor_principal_id, a fixed registered execution_context); adding a second, near-duplicate case
   would fragment the same underlying representation into two enum values with no behavioral
   difference between them, which is a heavier and more invasive IMP-004 change than widening one
   sentence of documented scope on the existing value.

C. Leave IMP-004 untouched and have IMP-008 invent its own separate, informal actor-representation
   convention for donation.created outside the AuditEventRegistry's actor-kind mechanism.
   REJECTED — this is exactly the "parallel audit mechanism" IMP-008's own specification (§Audit
   Requirements) already commits not to build, and would violate Q28's single-canonical-audit-sink
   principle.
```

## Consequences

**Easier**: `donation.created` (and any future IMP's similarly-shaped "legitimate unauthenticated
public action" event) can be recorded honestly — no verified identity claimed, no misrepresentation
as System automation, no new enum surface to learn. The pattern is now reusable by a later IMP
without each one needing its own bespoke ADR, provided each new use remains its own explicitly
registered (event_type, execution_context) pair under the same discipline.

**Harder / to watch**: `AuditActorKind::Unauthenticated` now carries two distinct legitimate
meanings (failed-auth-evidence vs. successful-guest-action) distinguished only by which specific
`execution_context` constant a given row carries, not by the actor kind alone. A future reviewer
reading raw `actor_principal_kind = 'unauthenticated'` rows must consult `execution_context` to
know which category applies — this is a documented, not hidden, distinction (both this ADR and the
patched IMP-004 section state it explicitly), but it is a real interpretive step that did not
previously exist when the value had exactly one meaning.

## Security Impact

None weakened. `actor_principal_id` remains NULL for every unauthenticated-kind row in both
categories — no unverified identity is ever promoted to a trusted position. The registry-closed,
per-event-registration discipline for `execution_context` is unchanged: a caller still cannot
invent or select a context value; only a registered event's own pre-declared constant is ever
written. No new field, no new write path, no new read-authorization change — `AUDIT_READ_SECURITY`
vs `AUDIT_READ`/`AUDIT_READ_FINANCIAL_REFERENCE` visibility classes are governed by each event's own
`AuditVisibilityClass`, unaffected by this amendment.

## Database Impact

None. No migration, no schema change, no new column. `audit_events.actor_principal_kind` already
accepts the `'unauthenticated'` string value; this amendment changes only which *registered
events* may declare it and why, documented in IMP-004's own specification text, not in the schema.

## Financial Impact

None directly. `donation.created` separately carries `hasFinancialReference: true` per IMP-008's
own Audit Requirements (amount/currency context) — unaffected by, and unrelated to, this actor-kind
scope amendment.

## Migration Impact

None.

## Approval

```
Approval Authority:         Human (Final Authority)
Human Approver:             The Human directing this Claude Code session
Approval Date:               2026-09-19
Approval Evidence:          Verbatim: "HD-IMP008-06 — FINAL / LOCKED. Guest-originated successful
                            Donation actions use: AuditActorKind::Unauthenticated with
                            actor_principal_id = NULL and a fixed, registry-controlled execution
                            context appropriate to the registered Donation entry point. This Human
                            Decision explicitly authorizes a controlled amendment to the locked
                            IMP-004 audit contract so that AuditActorKind::Unauthenticated may
                            represent BOTH: 1. the already-authorized failed/incomplete
                            authentication cases; AND 2. legitimate successful actions performed
                            through explicitly registered public unauthenticated entry points.
                            This is NOT authorization to make Unauthenticated a generic bucket.
                            Every permitted event/entry point MUST remain explicitly registered
                            and constrained. Do NOT use System to misrepresent the guest as the
                            actor. Do NOT introduce a new GuestSubmission actor kind. Apply the
                            required governance/change-control procedure for the locked IMP-004
                            contract before relying on this widened scope." — full text preserved
                            in this conversation's transcript.
Approved Reference:          docs/implementation/IMP-008-donation.md "Open Human Decisions" ->
                            OQ-008-06 (as resolved); this session's "IMP-008 — Human Decision
                            Brief" (OQ-008-06 analysis) served as the AI technical
                            analysis/recommendation this decision is based on, in place of a
                            separately drafted ACR, per the direct-Human-decision precedent already
                            established by GOV-MM-004/GOV-MM-005.
```

## References

- Related Human Decision: HD-IMP008-06, recorded in
  [docs/implementation/IMP-008-donation.md](../implementation/IMP-008-donation.md) "Open Human
  Decisions" (resolved) and "Audit Requirements".
- Related Implementation Task(s):
  [docs/implementation/IMP-004-audit-governance-foundation.md](../implementation/IMP-004-audit-governance-foundation.md)
  "Canonical Actor (Pre-Principal Cases)" (amended by this ADR — see that section's own amendment
  note), [docs/implementation/IMP-008-donation.md](../implementation/IMP-008-donation.md).
- Amended files: `docs/implementation/IMP-004-audit-governance-foundation.md` (documentation only —
  no code file changes; `app/Enums/AuditActorKind.php` is unchanged, no new case added).
- Precedent for direct-Human-decision amendment without a separately drafted ACR:
  [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md)
  GOV-MM-004, GOV-MM-005.
