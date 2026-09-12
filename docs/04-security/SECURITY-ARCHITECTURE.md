# Security Architecture

```
Source Status:          Previously Approved / Final / Locked External Baseline
Materialization Status: Repository Copy (locked baseline principles only)
Semantic Change:        NONE
```

This repository artifact materializes an already-approved external baseline. It does not create
a new architecture decision. It was materialized during IMP-001 Readiness Remediation Pass 1,
under explicit Human authorization (Decision 1 — Level 1-3 Materialization).

Per [docs/00-governance/DOCUMENT-AUTHORITY.md](../00-governance/DOCUMENT-AUTHORITY.md), this is
Level 3 — Locked Architecture Baseline.

## Principles

```
DENY by default
Server authoritative (client/frontend is never the authority for a security decision)
Defense in depth
Privileged authentication assurance (higher-risk actions require stronger assurance)
Sensitive file controlled delivery (no direct/static serving of sensitive files)
No permanent public URLs for sensitive files
Webhook verification + replay protection
System Principal (identity for system-initiated actions)
Integration Principal (identity for external-integration-initiated actions)
API token authority intersection (a token's effective authority is the intersection of the
  token's own grant and its principal's authority, never a union)
Financial separation of duties
```

## Explicit Non-Scope for This Materialization

The concepts "Privileged authentication assurance" and "API token authority intersection" above
are principles, not fully specified models. A detailed Authentication Assurance model (assurance
levels, step-up triggers, etc.) was not supplied for materialization in this pass — see
[docs/audits/IMP-001-READINESS-REMEDIATION.md](../audits/IMP-001-READINESS-REMEDIATION.md) for
the record of which artifacts were and were not materialized. Do not invent assurance levels or
token-intersection mechanics beyond the principle stated here.

No authentication implementation exists yet — see
[docs/implementation/IMP-001-core-project-foundation.md](../implementation/IMP-001-core-project-foundation.md)
"§23 AUTHENTICATION BOUNDARY" (deferred to IMP-002).

See [docs/04-security/SECURITY-INVARIANTS.md](SECURITY-INVARIANTS.md) for the invariants derived
from these principles.
