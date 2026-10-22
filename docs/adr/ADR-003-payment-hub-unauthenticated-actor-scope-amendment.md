# ADR-003 — IMP-009 `AuditActorKind::Unauthenticated` scope widened for two registered guest
Payment Hub events

## Status

ACCEPTED

Human approval evidence is recorded in full under "Approval" below, per
[docs/00-governance/CHANGE-CONTROL.md](../00-governance/CHANGE-CONTROL.md)'s AI Approval Boundary
rule — this status was not set by an AI agent's own judgment.

## Context

IMP-004 Audit + Governance Foundation is `FINAL / LOCKED`. Its "Canonical Actor (Pre-Principal
Cases)" section (`docs/implementation/IMP-004-audit-governance-foundation.md`) defines
`AuditActorKind::Unauthenticated` (`actor_principal_id` NULL, a fixed registry-supplied
`execution_context` constant). [ADR-002](ADR-002-donation-unauthenticated-actor-scope-amendment.md)
already widened this actor kind's legitimate use from "evidence of a failed/incomplete
authentication attempt" alone to also cover "a legitimate, successful action performed through an
explicitly registered public unauthenticated entry point" — but ADR-002 registered exactly ONE
Category-2 use (`donation.created`, IMP-008's guest-donation-creation event) and is explicit that
"no other IMP-008 event uses this widened category... any further use requires its own future ADR,
not silent reuse" (ADR-002 "Decision", restated in
[docs/implementation/IMP-008-donation.md](../implementation/IMP-008-donation.md) "Forbidden
Changes").

IMP-009 (Payment Hub) has the identical structural need at two of its own entry points: a guest
donor creating a Payment Attempt for their own guest Donation (`payment.attempt_created`), and a
guest donor submitting Manual Bank Transfer proof-of-transfer evidence
(`manual_transfer.evidence_submitted`). Both are legitimate, successful, intentionally-
unauthenticated public actions with no Principal to attribute — the exact shape ADR-002's Category
2 already models — but ADR-002's own text does not, by itself, authorize a second IMP's reuse of
that category without its own ADR. This was raised in
[docs/implementation/IMP-009-payment-hub.md](../implementation/IMP-009-payment-hub.md) as
HD-IMP009-12 (originally analyzed as part of the specification's "Architectural Escalation" /
"New ADR Required" finding accompanying that document's initial submission for Human Spec
Approval). The Human decided directly (HD-IMP009-12, verbatim below), authorizing this narrowly-
scoped amendment without a separately drafted ACR first — mirroring the same direct-Human-decision
precedent ADR-002 itself already established (GOV-MM-004/GOV-MM-005,
`docs/00-governance/MULTI-MODEL-OWNERSHIP.md`), where the Human's own decision message already
supplied the full scope/rationale a drafted ACR would otherwise exist to capture.

## Decision

`AuditActorKind::Unauthenticated` (unchanged enum value — no new case added, no existing case
removed; ADR-002's Category 1/Category 2 taxonomy is unchanged and unexpanded in kind) is
authorized for exactly **two additional, explicitly enumerated** registered events, and no others:

```
1. payment.attempt_created         execution_context = "http:payment:guest_attempt_created"
2. manual_transfer.evidence_submitted
                                    execution_context = "http:payment:guest_manual_transfer_evidence_submitted"
```

Both are Category 2 uses under ADR-002's own taxonomy (legitimate, successful actions through an
explicitly registered public unauthenticated entry point — never Category 1's failed-authentication
evidence). This ADR does **not** reopen or generalize ADR-002's mechanism itself; it is an
enumeration of two additional permitted (event_type, execution_context) pairs, governed by the SAME
discipline ADR-002 already locked and restated here for this ADR's own scope, unchanged:

```
- Only an explicitly registered (event_type, event_version) entry may declare
  actor_principal_kind = 'unauthenticated' — never inferred, never caller-selectable.
- execution_context remains a FIXED, registry-supplied constant per registered entry point —
  never free-form caller input.
- actor_principal_id remains NULL for every unauthenticated-kind row, exactly as already locked.
- No caller-supplied arbitrary actor identity is ever trusted.
- Each event's own AuditEventRegistrar (IMP-009's, mirroring IMP-008's CampaignAuditEventRegistrar/
  DonationAuditEventRegistrar shape) declares AuditActorKind::Unauthenticated in that event's
  actorKinds array with its own dedicated executionContext constant from the enumerated list
  above — never reused across unrelated entry points, never extended to a third IMP-009 event
  without its own further amendment.
```

**No other IMP-009 event uses this widened category.** `payment.succeeded`, `payment.failed`,
`payment.expired`, `payment.cancelled` are Integration/System-actor events regardless of whether
the underlying Payment/Donation was guest- or authenticated-donor-originated (see IMP-009 §Audit).
`manual_transfer.approved`/`manual_transfer.rejected` are Human-actor only (an admin decision,
never a guest action). This ADR explicitly does NOT create a generic "unauthenticated Payment Hub
event" bucket — a future IMP-009 event needing this treatment requires its own further amendment
to this ADR (or a new ADR), exactly mirroring the constraint ADR-002 itself imposed on IMP-009's
own reuse here.

## Alternatives

```
A. Amend ADR-002 itself to generically cover "any future IMP's similarly-shaped guest event."
   REJECTED — this is precisely what the Human's decision (HD-IMP009-12) explicitly forbids ("Do
   NOT broaden ADR-002 generically"). A generic amendment would convert a narrowly-scoped,
   per-event-registered mechanism into an open-ended bucket any future IMP could claim without its
   own review, defeating the auditability purpose the enumerated-scope discipline exists to serve.

B. Reuse AuditActorKind::System for these two guest-originated events, with guest identity folded
   into the payload instead of the actor identity.
   REJECTED for the same reason ADR-002 rejected it for donation.created — misrepresents a
   human-originated action as automation-originated, and would make audit review less accurate.

C. Add a new AuditActorKind case scoped to IMP-009 specifically (e.g. a Payment-domain-specific
   guest actor kind).
   REJECTED as unnecessary — Unauthenticated already models exactly this shape (no
   actor_principal_id, a fixed registered execution_context); a domain-specific duplicate enum case
   would fragment identical representations across IMPs with no behavioral difference, which
   ADR-002 already rejected for the same structural reason (its own Alternative B).

D. Leave IMP-004/ADR-002 untouched and have IMP-009 invent its own separate, informal actor-
   representation convention for these two events outside the AuditEventRegistry's actor-kind
   mechanism.
   REJECTED — this is exactly the "parallel audit mechanism" both IMP-008's and IMP-009's own
   specifications commit not to build, and would violate Q28's single-canonical-audit-sink
   principle.
```

## Consequences

**Easier**: `payment.attempt_created` and `manual_transfer.evidence_submitted` can be recorded
honestly for a guest donor — no verified identity claimed, no misrepresentation as System
automation, no new enum surface. The established ADR-002 pattern is proven reusable by a second IMP
without inventing a parallel mechanism, provided each new use goes through its own explicit,
narrowly-scoped ADR amendment exactly as this one does.

**Harder / to watch**: `AuditActorKind::Unauthenticated` now has FOUR registered (event_type,
execution_context) pairs across two IMPs (one under ADR-002, two under this ADR, plus ADR-002's
original `identity.session.login_failed`) rather than two. A future reviewer must consult the
combined enumerated list across BOTH ADRs (not just one) to know the complete set of legitimate
uses — this is a documented, not hidden, distinction, but it is a real, growing interpretive
surface that any future IMP wanting a THIRD Category-2 use must account for by amending or
superseding these ADRs explicitly, never by silent analogy.

## Security Impact

None weakened. `actor_principal_id` remains NULL for every unauthenticated-kind row across all
four registered uses — no unverified identity is ever promoted to a trusted position. The
registry-closed, per-event-registration discipline for `execution_context` is unchanged: a caller
still cannot invent or select a context value; only a registered event's own pre-declared constant
is ever written. No new field, no new write path, no new read-authorization change —
`AUDIT_READ_FINANCIAL_REFERENCE`/`AUDIT_READ` visibility classes are governed by each event's own
`AuditVisibilityClass`, unaffected by this amendment (both `payment.attempt_created` and
`manual_transfer.evidence_submitted` carry `hasFinancialReference: true` per IMP-009's own Audit
Requirements, gating read access exactly as every other financially-adjacent event already does).

## Database Impact

None. No migration, no schema change, no new column. `audit_events.actor_principal_kind` already
accepts the `'unauthenticated'` string value; this amendment changes only which additional
*registered events* may declare it and why, documented in this ADR and in IMP-009's own
specification text, not in the schema.

## Financial Impact

None directly. Both amended events separately carry `hasFinancialReference: true` per IMP-009's own
Audit Requirements (amount/currency/provider context) — unaffected by, and unrelated to, this
actor-kind scope amendment.

## Migration Impact

None.

## Approval

```
Approval Authority:         Human (Final Authority)
Human Approver:             The Human directing this Claude Code session
Approval Date:               2026-09-20
Approval Evidence:          Verbatim (HD-IMP009-12, "IMP-009 — HUMAN DECISION INTEGRATION + ADR"
                            task): "Create a NEW IMP-009-specific ADR for guest Payment audit
                            semantics. Do NOT broaden ADR-002 generically. For explicitly approved
                            guest IMP-009 events such as: payment.attempt_created,
                            manual_transfer.evidence_submitted, use:
                            AuditActorKind::Unauthenticated, principal = NULL, with fixed/
                            registered execution context consistent with IMP-004. The ADR must
                            enumerate its allowed event scope. It MUST NOT create a generic
                            unauthenticated audit bucket applicable to unrelated domains." — full
                            text preserved in this conversation's transcript.
Approved Reference:          docs/implementation/IMP-009-payment-hub.md "Human Decisions
                            (Resolved)" -> HD-IMP009-12; the specification's original "New ADR
                            Required: YES" finding (initial submission report) is the AI technical
                            analysis this decision responds to, in place of a separately drafted
                            ACR, per the direct-Human-decision precedent ADR-002 itself already
                            established.
```

## References

- Related Human Decision: HD-IMP009-12, recorded in
  [docs/implementation/IMP-009-payment-hub.md](../implementation/IMP-009-payment-hub.md) "Human
  Decisions (Resolved)" and "Audit".
- Related ADR (amended pattern, not amended scope): [ADR-002](ADR-002-donation-unauthenticated-actor-scope-amendment.md).
- Related Implementation Task(s):
  [docs/implementation/IMP-004-audit-governance-foundation.md](../implementation/IMP-004-audit-governance-foundation.md)
  "Canonical Actor (Pre-Principal Cases)" (its enumerated legitimate-use list now spans this ADR in
  addition to ADR-002 — see that section's own amendment note at implementation time),
  [docs/implementation/IMP-009-payment-hub.md](../implementation/IMP-009-payment-hub.md).
- Amended files: `docs/implementation/IMP-004-audit-governance-foundation.md` (documentation only,
  at implementation time — no code file changes; `app/Enums/AuditActorKind.php` is unchanged, no
  new case added).
- Precedent for direct-Human-decision amendment without a separately drafted ACR:
  [ADR-002](ADR-002-donation-unauthenticated-actor-scope-amendment.md) "Approval";
  [docs/00-governance/MULTI-MODEL-OWNERSHIP.md](../00-governance/MULTI-MODEL-OWNERSHIP.md)
  GOV-MM-004, GOV-MM-005.
