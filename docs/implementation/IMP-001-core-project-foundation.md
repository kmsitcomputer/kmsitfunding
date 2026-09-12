# IMPLEMENTATION 01 — CORE PROJECT FOUNDATION

## Document Control

```text
Task ID: IMP-001
Stage: IMPLEMENTATION 01
Title: Core Project Foundation
Document Type: Implementation Specification
Status: DRAFT — REMEDIATED (Pass 1) — PENDING TARGETED READINESS RE-AUDIT
Implementation Authorization: NOT AUTHORIZED
Coding Authorization: NO
Predecessor: IMP-000 FINAL / LOCKED
Working Branch: impl/001-core-foundation (not merged to master)
```

This specification defines the technical foundation of the Modern Digital Philanthropy Platform.

It MUST NOT introduce philanthropy business-domain implementation.

---

# 1. OBJECTIVE

Establish a production-oriented Laravel application foundation that later implementation stages can safely build upon.

IMP-001 shall establish:

- Laravel application baseline;
- PHP/runtime baseline;
- frontend application baseline;
- Vue + Inertia integration;
- TypeScript baseline;
- Tailwind CSS baseline;
- Vite build pipeline;
- MySQL baseline;
- environment configuration structure;
- modular-monolith code organization;
- queue baseline;
- cache baseline;
- session baseline;
- scheduler baseline;
- storage baseline;
- logging/error-handling baseline;
- development quality tooling;
- testing foundation;
- shared-hosting-compatible runtime assumptions;
- repository checks required by later stages.

IMP-001 is infrastructure/foundation work.

It is NOT a business-feature stage.

---

# 2. AUTHORITATIVE INPUTS

Before implementation, the implementing agent MUST read the repository-local authoritative sources required by `AGENTS.md`.

At minimum:

```text
AGENTS.md

docs/00-governance/
docs/01-requirements/
docs/02-architecture/
docs/03-database/
docs/04-security/
docs/05-rbac/

docs/adr/
docs/decisions/

docs/implementation/
```

Specifically identify and read repository-local documents representing:

```text
Human Decision Register
Master Requirements
Master Architecture
Database Architecture
Security Architecture
Authentication + RBAC Architecture
Implementation Governance
Document Authority
Definition of Done
Change Control
Branching Policy
```

These have been materialized (see
[docs/audits/IMP-001-READINESS-REMEDIATION.md](../audits/IMP-001-READINESS-REMEDIATION.md) and
[docs/audits/IMP-001-B01-MATERIALIZATION-PASS-2.md](../audits/IMP-001-B01-MATERIALIZATION-PASS-2.md)
for the full record) at:

```text
docs/01-requirements/HUMAN-DECISION-REGISTER.md
docs/01-requirements/MASTER-REQUIREMENTS.md
docs/02-architecture/MASTER-ARCHITECTURE.md
docs/02-architecture/MODULE-OWNERSHIP.md
docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md
docs/03-database/DATABASE-ARCHITECTURE.md
docs/03-database/DATABASE-INVARIANTS.md
docs/04-security/SECURITY-ARCHITECTURE.md
docs/04-security/SECURITY-INVARIANTS.md
docs/05-rbac/RBAC-ARCHITECTURE.md
docs/05-rbac/DATA-SCOPE-MODEL.md
docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md
docs/05-rbac/AUTHENTICATION-ASSURANCE.md
```

This specification references those documents rather than duplicating their content. Do not
assume filenames if repository structure differs.

Resolve them from the actual repository.

---

# 3. AUTHORITY RULE

Repository document authority established by IMP-000 remains binding.

Conceptually:

```text
Human Decisions
        ↓
Master Requirements
        ↓
Locked Architecture Baseline
        ↓
Approved Decisions / Amendments
        ↓
Implementation Specification
        ↓
Governance / Operating Instructions
        ↓
Implementation Artifacts
```

Code and tests are implementation artifacts.

They MUST conform to higher-authority sources.

If this IMP-001 specification conflicts with a higher-authority source:

```text
STOP
→ identify conflict
→ report exact evidence
→ do not silently resolve
```

---

# 4. LOCKED PLATFORM BASELINE

IMP-001 MUST preserve the established architecture.

Application architecture:

```text
Single Organization
        ↓
Single Laravel Application
        ↓
Modular Monolith
        ↓
Single Root Domain
        │
        ├── Web
        ├── Admin
        ├── Portals
        └── /api/v1/*
```

Web and API MUST share application/domain logic.

Do NOT create independent business implementations for Web and API.

---

# 5. TARGET TECHNOLOGY BASELINE

Implementation shall validate the repository and currently supported package ecosystem before selecting exact dependency versions.

Target direction:

```text
PHP              8.3 compatible baseline
Laravel          13.x
MySQL            8.x

Vue              3
Inertia.js       3
TypeScript       enabled
Tailwind CSS     4
Vite             current compatible version

Node.js          build/development dependency
Composer         PHP dependency management
npm              frontend dependency management
```

Lucide may be prepared as the default icon system if required by the locked project baseline.

Do NOT invent arbitrary framework replacements.

Do NOT replace Vue with React.

Do NOT replace Inertia with a separate SPA architecture.

Do NOT introduce Nuxt.

Do NOT introduce a mandatory separate API server.

---

# 6. VERSION VERIFICATION RULE

Before installing or upgrading dependencies, implementation MUST verify that the PHP runtime and
Composer are accessible and version-verifiable, and inspect Node/npm versions.

Global PATH configuration is NOT mandatory. A verified invocation through an explicit full
executable path is equivalent, for readiness purposes, to a bare-command invocation. For example,
these are semantically equivalent:

```bash
php -v
```

and:

```bash
<verified-php-executable> -v
```

The same applies to Composer (which itself requires a working PHP invocation to run its
`composer.phar`, whether that PHP is found via PATH or via an explicit path). Whichever mechanism
is used, the actual executable path/invocation and the resulting version MUST be recorded — do
not invent a version, and do not require a specific PATH arrangement as a precondition of
readiness. See
[docs/audits/IMP-001-READINESS-REMEDIATION.md](../audits/IMP-001-READINESS-REMEDIATION.md) for the
verified executables and versions recorded for this environment.

Required baseline checks:

```bash
<php>       -v
<composer>  --version
node        --version
npm         --version
```

and available project dependency state.

If the repository is already initialized, inspect:

```text
composer.json
composer.lock
package.json
package-lock.json
vite.config.*
```

Do NOT blindly recreate an initialized project.

Use versions mutually compatible with the locked baseline and actual environment.

If Laravel 13.x or another requested major dependency is incompatible with the available environment:

```text
STOP
```

Report the incompatibility.

Do NOT silently downgrade the locked framework baseline.

---

# 7. BACKEND FOUNDATION

The Laravel foundation shall use normal framework conventions unless a locked architecture document requires otherwise.

Expected framework-level areas include:

```text
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
tests/
```

Do NOT prematurely create business modules simply to fill directories.

---

# 8. MODULAR MONOLITH FOUNDATION

Prepare a structure capable of supporting the locked module architecture without implementing the modules themselves.

Conceptual future domains include:

```text
Identity
Content Experience
Campaign & Program
Donation
Fundraising & Attribution
Commission
Partner
Payment
Finance & Fund
Ledger
Reconciliation
Approval
Philanthropy Products
Beneficiary & Distribution
Documents & Communication
Governance & Platform Services
```

IMP-001 MUST NOT implement these business domains.

It may establish a neutral organizational convention such as application/domain/infrastructure boundaries only when consistent with the locked Master Architecture.

Avoid speculative abstraction.

Do not create empty hundreds of classes.

---

# 9. ROUTING FOUNDATION

Prepare Laravel routing for later use.

Expected conceptual separation:

```text
Web routes
API routes
Console/Scheduler
```

Future API namespace:

```text
/api/v1/*
```

IMP-001 may establish the API version routing foundation.

It MUST NOT implement business API resources.

Allowed examples:

```text
health/readiness endpoint
minimal non-business application shell
```

if permitted by security architecture.

Do NOT create:

```text
Donation API
Payment API
Ledger API
Commission API
Partner API
```

during IMP-001.

---

# 10. FRONTEND FOUNDATION

Establish:

```text
Vue 3
Inertia.js
TypeScript
Tailwind CSS 4
Vite
```

The foundation should provide:

```text
application bootstrap
root layout capability
frontend asset pipeline
TypeScript compilation
Tailwind compilation
Vite production build
```

A minimal application shell is allowed.

Do NOT implement full:

```text
Public Website
Donor Portal
Fundraiser Portal
Partner Portal
Admin Dashboard
CMS UI
Campaign UI
Donation Checkout
```

Those belong to later stages.

---

# 11. THEME BOUNDARY

IMP-001 MUST preserve:

```text
Public / Donor / Fundraiser / Partner
→ future themeable presentation

Admin / Super Admin
→ fixed backoffice
```

Do NOT implement Theme Engine during IMP-001.

Do NOT create arbitrary runtime PHP/JS theme execution.

Theme remains presentation-only.

---

# 12. DATABASE FOUNDATION

Database target:

```text
MySQL 8.x
```

IMP-001 may establish framework infrastructure migrations required for Laravel operation.

Examples may include framework-supported tables for:

```text
queue
failed jobs
cache
sessions
```

only if actually selected for database-backed operation.

IMP-001 MUST NOT create philanthropy business tables.

Forbidden during this stage include business migrations for:

```text
donations
payments
payment_attempts
ledger_accounts
ledger_journals
ledger_entries
funds
campaigns
commission_entitlements
withdrawals
refunds
partners
beneficiaries
distributions
```

and other business-domain tables.

---

# 13. DATABASE HARD RULES

Later business schema must follow the locked Database Architecture.

IMP-001 MUST NOT weaken those rules.

Important preserved principles:

```text
BIGINT unsigned internal identity
ULID public/API-safe identity where specified
DECIMAL for money
Never FLOAT/DOUBLE for money

Donation != Payment
Payment != Ledger
Reconciliation != Ledger
Fund != mutable accounting balance
Commission != wallet balance
```

No speculative business schema in IMP-001.

---

# 14. ENVIRONMENT CONFIGURATION

Prepare standard Laravel environment configuration.

Repository may contain:

```text
.env.example
```

It MUST NOT contain production secrets.

Configuration shall support later integration through environment variables.

Never commit:

```text
database passwords
payment gateway secrets
Stripe secrets
Tripay secrets
Xendit secrets
Moota credentials
WhatsApp access tokens
SMTP passwords
private keys
production API tokens
```

---

# 15. QUEUE FOUNDATION

Production baseline:

```text
Database-backed Queue
```

because production MUST remain shared-hosting compatible.

Do NOT make the following mandatory:

```text
Redis
RabbitMQ
Kafka
Supervisor
Horizon
permanent daemon
```

Queue processing must be compatible with later cron-driven execution.

IMP-001 only establishes queue infrastructure.

It does not implement business jobs.

---

# 16. CACHE FOUNDATION

Use a shared-hosting-compatible cache baseline.

Allowed baseline:

```text
database
or
file
```

depending on actual locked configuration.

Redis MUST NOT become a mandatory production dependency.

---

# 17. SESSION FOUNDATION

Use a production-suitable Laravel session driver consistent with shared hosting.

Candidate baseline:

```text
database
```

if consistent with actual project configuration.

Session security configuration must remain compatible with the locked Security Architecture.

Do not implement Authentication during IMP-001.

---

# 18. SCHEDULER FOUNDATION

Prepare Laravel Scheduler capability.

Production assumption:

```text
Hosting Cron
      ↓
Laravel Scheduler
```

No permanent scheduler daemon may be required.

IMP-001 may provide documentation for the expected cron entry.

Do not schedule philanthropy business processes yet.

---

# 19. STORAGE FOUNDATION

Establish Laravel Storage conventions capable of later separating:

```text
Public CMS Media
Private Application Files
Sensitive Documents
Generated Documents
Exports
```

IMP-001 does NOT need to implement all document workflows.

Security principle must already be preserved:

```text
PRIVATE / HIGHLY_SENSITIVE
!=
permanent public URL
```

Do not expose sensitive storage through public symlinks.

---

# 20. LOGGING FOUNDATION

Use Laravel logging conventions.

Production logging MUST NOT expose:

```text
passwords
tokens
secrets
payment credentials
sensitive personal data
beneficiary documents
full private financial payloads
```

IMP-001 does not implement full Audit Log architecture.

Operational logs and future Audit Logs remain distinct concepts.

---

# 21. ERROR HANDLING

Production foundation must support:

```text
APP_DEBUG=false
```

Production errors must not expose:

```text
stack traces
environment values
SQL credentials
API credentials
filesystem secrets
```

Development environment may use appropriate debugging behavior.

---

# 22. SECURITY FOUNDATION

IMP-001 must preserve the locked security architecture.

Baseline principles:

```text
DENY by default
server authoritative
framework validation
CSRF protection for web state changes
secure session configuration
no arbitrary executable user content
no secrets in frontend bundle
no sensitive permanent public files
```

Do NOT implement the complete RBAC system yet.

Do NOT invent temporary authorization shortcuts that later stages would have to remove.

---

# 23. AUTHENTICATION BOUNDARY

Authentication belongs to:

```text
IMP-002 — Identity + Authentication
```

Therefore IMP-001 MUST NOT implement:

```text
login
registration
password reset
email verification
MFA
OAuth
social login
authentication roles
```

unless a framework bootstrap package introduces unavoidable scaffolding.

If scaffolding would introduce those features, STOP and report before accepting it.

Prefer the smallest foundation necessary.

---

# 24. RBAC BOUNDARY

RBAC belongs to:

```text
IMP-003 — RBAC + Scope + Business Authority
```

IMP-001 MUST NOT create speculative:

```text
roles
permissions
business authorities
approval authorities
scope assignments
```

Do not install an RBAC package merely because it is popular.

Package choice belongs to the relevant stage unless already locked.

---

# 25. FINANCIAL HARD STOP

IMP-001 MUST NOT implement financial behavior.

Forbidden:

```text
Ledger posting
Financial Consequence
Payment confirmation
Gateway callback
Moota reconciliation
Commission calculation
Withdrawal balance
Refund execution
Operational Fee calculation
Fund balance
Accounting entries
```

The locked financial chain remains:

```text
Business Use Case
      ↓
Business State Transition
      ↓
Authorized Financial Consequence
      ↓
Ledger Posting Contract
      ↓
Journal
      ↓
Double-Entry Entries
```

IMP-001 must not create shortcuts around this future boundary.

---

# 26. EXTERNAL INTEGRATION BOUNDARY

Do NOT configure real:

```text
Tripay
Xendit
Stripe
Moota
WhatsApp Cloud API
```

during IMP-001.

No production credentials.

No mock business integrations unless specifically required for a foundation-level test.

---

# 27. SHARED HOSTING COMPATIBILITY

Production foundation MUST be compatible with:

```text
Apache or LiteSpeed
PHP
MySQL
Cron
Laravel Storage
compiled frontend assets
```

Production MUST NOT require:

```text
Docker
Redis Server
Supervisor
PM2
Node.js runtime
WebSocket server
Elasticsearch
Kafka
RabbitMQ
separate API server
```

These technologies may not become mandatory dependencies.

---

# 28. NODE.JS BOUNDARY

Node.js is required for development/build tooling.

Production deployment should consume compiled assets.

Conceptually:

```text
Development / CI
Node + npm
    ↓
Vite Build
    ↓
Compiled Assets
    ↓
Production PHP Application
```

Production web requests must not require a Node process.

---

# 29. DEVELOPMENT QUALITY TOOLING

Inspect existing Laravel/project tooling first.

Establish a reasonable foundation for:

```text
PHP formatting
frontend formatting
linting
TypeScript validation
automated tests
production build verification
```

Prefer framework-standard and minimally necessary tools.

Do not create a large tooling stack without demonstrated need.

---

# 30. TEST FOUNDATION

Establish automated test capability.

At minimum verify:

```text
PHP test runner works
Laravel application boots in test environment
frontend TypeScript check works
frontend production build works
```

Where appropriate establish a minimal foundation smoke test.

Do NOT write fake business tests for domains not implemented.

---

# 31. FOUNDATION SMOKE TESTS

Examples of acceptable IMP-001 tests:

```text
Application boots successfully.
Environment is test-safe.
Base route responds as expected.
API version foundation resolves if created.
Database connection/test configuration behaves correctly.
Queue infrastructure configuration is valid.
Production asset build succeeds.
```

Tests must not require real external provider credentials.

---

# 32. CI / CHECK CONTRACT

IMP-001 should establish or document repeatable checks appropriate to the repository.

Conceptually:

```bash
composer validate
PHP formatter/check
PHP tests
frontend lint
TypeScript check
npm production build
git diff --check
```

Use actual installed tools.

Do NOT document commands for packages that are not installed.

---

# 33. HEALTH / READINESS

A minimal health/readiness mechanism may be established if consistent with Laravel/framework conventions.

It must not expose:

```text
environment secrets
database credentials
internal paths
stack traces
provider credentials
```

Public health output should be minimal.

---

# 34. OUT OF SCOPE

Explicitly OUT OF SCOPE:

```text
Authentication
RBAC
Business Authority
Approval Engine

CMS
Theme Engine
Public Website implementation

Campaign
Program business logic
Fund business logic

Donation
Recurring Donation

Payment Hub
Manual Transfer
Tripay
Xendit
Stripe

Ledger
Financial Consequences
Operational Fees

Fundraiser
Attribution
Commission
Withdrawal
Refund

Moota / Reconciliation

Partner

Zakat
Wakaf
Fidyah
Qurban

Beneficiary
Distribution
Impact

Receipt
Compliance Documents

WhatsApp notifications

Business REST API
Business Reporting
Business Search
```

---

# 35. PROHIBITED ACTIONS

The implementation agent MUST NOT:

```text
invent business rules
invent commission rates
invent operational fee rates
invent accounting entries
invent approval thresholds
invent retention periods
invent FX rates
invent legal requirements
invent Zakat rules
```

Also prohibited:

```text
microservice conversion
multi-tenant Partner architecture
separate settlement system
generic fundraiser wallet
mutable Fund balance as accounting truth
Payment → Ledger direct shortcut
Moota → Ledger direct shortcut
provider callback → Ledger direct shortcut
```

---

# 36. EXPECTED IMPLEMENTATION DELIVERABLES

After authorization, IMP-001 is expected to produce only foundation artifacts such as:

```text
Laravel application baseline
Composer dependency baseline
frontend dependency baseline
Vue/Inertia bootstrap
TypeScript configuration
Tailwind configuration
Vite configuration
MySQL environment baseline
queue/cache/session infrastructure
scheduler foundation
storage conventions
logging/error baseline
test foundation
quality/check tooling
shared-hosting deployment notes for foundation
```

Exact files MUST be determined from the actual repository and compatible framework conventions.

Do not predeclare files that the installed framework does not use.

---

# 37. IMPLEMENTATION REPORT REQUIREMENT

Claude Code implementation report must eventually contain:

```text
# IMP-001 IMPLEMENTATION RESULT

Status:
COMPLETE / BLOCKED

Environment Detected:
...

Dependencies Added:
...

Dependencies Changed:
...

Files Created:
...

Files Modified:
...

Database Migrations Added:
...

Business Tables Added:
NONE

Business Logic Added:
NONE

Authentication Added:
NO

RBAC Added:
NO

Financial Logic Added:
NO

External Integrations Added:
NO

Tests Added:
...

Checks Executed:
...

Test Results:
...

Production Build:
PASS / FAIL

Shared Hosting Compatibility:
PASS / FAIL

Architecture Deviations:
NONE / ...

Human Decision Required:
NO / YES

Ready for Independent Review:
YES / NO
```

---

# 38. DEFINITION OF READY — IMP-001

IMP-001 may become READY only if all conditions below are satisfied.

## Objective

```text
[x] Foundation objective defined.
```

## Scope

```text
[x] Technical foundation scope defined.
[x] Business-domain implementation excluded.
```

## Architecture

```text
[x] Single Laravel application preserved.
[x] Modular monolith preserved.
[x] Same-origin API architecture preserved.
[x] Shared-hosting compatibility preserved.
```

## Database

```text
[x] MySQL baseline defined.
[x] Framework infrastructure migrations allowed.
[x] Business schema explicitly prohibited.
```

## Security

```text
[x] Security baseline identified.
[x] Secret handling defined.
[x] Sensitive storage boundary defined.
[x] Authentication deferred.
[x] RBAC deferred.
```

## Finance

```text
[x] Financial implementation explicitly prohibited.
[x] Ledger boundary preserved.
```

## Frontend

```text
[x] Vue/Inertia/TypeScript/Tailwind/Vite direction defined.
```

## Runtime

```text
[x] PHP/MySQL/Cron/compiled-assets production model defined.
[x] No mandatory Redis/Supervisor/Node runtime.
```

## Testing

```text
[x] Foundation testing requirements defined.
[x] Production build verification required.
```

## Acceptance Criteria

```text
[x] Defined below.
```

## Human Decisions

```text
[x] No new business decision identified by this specification.
```

Provisional DoR result:

```text
READY FOR INDEPENDENT READINESS REVIEW
```

This is NOT implementation authorization.

---

# 39. ACCEPTANCE CRITERIA

IMP-001 implementation may later be accepted only when all applicable criteria pass.

### AC-001
Laravel application boots successfully in supported development/test environment.

### AC-002
Installed Laravel major version matches the locked baseline or implementation is BLOCKED and escalated rather than silently downgraded.

### AC-003
PHP dependency installation is reproducible from lockfile.

### AC-004
Vue 3 + Inertia + TypeScript application foundation builds successfully.

### AC-005
Tailwind CSS foundation compiles successfully.

### AC-006
Vite production build completes successfully.

### AC-007
Production does not require Node.js runtime.

### AC-008
MySQL configuration baseline exists without committed production credentials.

### AC-009
Selected queue implementation is shared-hosting compatible.

### AC-010
Selected cache/session baseline is shared-hosting compatible.

### AC-011
Laravel Scheduler can be invoked through normal cron-compatible mechanism.

### AC-012
Private/sensitive storage is not automatically exposed through public web storage.

### AC-013
Production error/debug configuration can operate without exposing sensitive diagnostic information.

### AC-014
Automated PHP tests execute successfully.

### AC-015
TypeScript validation/check succeeds.

### AC-016
Configured frontend lint/format checks succeed where installed.

### AC-017
No philanthropy business tables are introduced.

### AC-018
No authentication implementation is introduced.

### AC-019
No RBAC/business-authority implementation is introduced.

### AC-020
No financial-domain implementation is introduced.

### AC-021
No real Payment/Moota/WhatsApp provider integration is introduced.

### AC-022
No architecture decision from the locked baseline is silently changed.

### AC-023
No production secret is committed.

### AC-024
Foundation remains deployable under the locked shared-hosting model.

### AC-025
Implementation receives independent review before stage gate closure.

---

# 40. REQUIRED INDEPENDENT READINESS REVIEW

Before Claude Code receives coding authorization, Codex must independently review this specification against the actual repository.

Codex should verify:

```text
authoritative source mapping
repository state
framework state
environment assumptions
dependency assumptions
scope boundaries
architecture compatibility
security compatibility
database boundaries
shared-hosting compatibility
testability
acceptance criteria
Definition of Ready
```

Codex must specifically detect whether repository reality creates a conflict not visible in this specification.

---

# 41. READINESS GATE

Possible outcomes:

```text
PASS
NEEDS CORRECTION
HUMAN DECISION REQUIRED
```

Only:

```text
PASS
```

permits IMP-001 implementation authorization.

Even after PASS, record the stage authorization before coding according to IMP-000 governance.

---

# 42. CURRENT STATUS

```text
IMP-000:
FINAL / LOCKED

IMP-001 Specification:
DRAFT / REMEDIATED (Readiness Remediation Pass 1 — see
docs/audits/IMP-001-READINESS-REMEDIATION.md)

IMP-001 Definition of Ready:
READY FOR TARGETED INDEPENDENT RE-AUDIT

Independent Readiness Review:
PENDING (targeted re-audit against Readiness Remediation Pass 1)

IMP-001 Implementation:
NOT AUTHORIZED

Business Feature Coding:
NOT AUTHORIZED
```

This status was reached through Readiness Remediation Pass 1 (materialization of the Level 1-3
authoritative baseline referenced in §2, branch remediation, and environment verification). It is
not a self-authorization — implementation remains NOT AUTHORIZED pending Codex's targeted
readiness re-audit and, if that passes, explicit Human/stage authorization per
[docs/00-governance/IMPLEMENTATION-GOVERNANCE.md](../00-governance/IMPLEMENTATION-GOVERNANCE.md).

STOP.

Do not implement IMP-001 yet.
