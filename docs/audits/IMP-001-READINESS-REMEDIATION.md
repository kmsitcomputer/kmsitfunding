# IMP-001 Readiness Remediation Record

Task:
IMP-001 Readiness Remediation Pass 1

## Findings Addressed

```
IMP001-RDY-B01 — BLOCKER — Missing repository-local authoritative Level 1-3 baseline
IMP001-RDY-B02 — BLOCKER — PHP/Composer environment verification unavailable through literal
                            PATH invocation
IMP001-RDY-M01 — MAJOR   — Unapproved IMP-001 draft committed directly to stable master
```

## Human Authorization

The Project Human Authority explicitly approved three remediation decisions before this pass
began:

1. **Level 1-3 Materialization** — materialize the already-approved external baseline into the
   repository, materialization only (no new architecture, no reinterpretation, no semantic
   change, no new Human Decisions, no modification of Q1-Q20 or locked stages 0-19). Where
   source content was unavailable, STOP FOR THAT ARTIFACT rather than reconstruct from guesses.
2. **Branch Remediation** — keep `master` as the temporary canonical stable branch, create
   `impl/001-core-foundation` as the IMP-001 working branch, defer `master` -> `main` rename, and
   restore `master` via a non-destructive revert commit (no reset/rebase/rewrite/force-push).
3. **PHP/Composer Access** — environment verification may use a verified full-path executable
   invocation; global PATH configuration is not mandatory. No PHP/Composer install/upgrade in
   this pass.

## IMP001-RDY-M01 — Branch Remediation (Resolved)

Verified before mutation (`git status`, `git branch -vv`, `git log --oneline --decorate --graph`,
`git remote -v`, `git show --stat aa515b7`):

- `aa515b7` existed on `master` as the only commit ahead of the approved IMP-000 baseline
  (`d6cb2f3`), adding only `docs/implementation/IMP-001-core-project-foundation.md`.
- No remote is configured.

Actions taken:

```
git branch impl/001-core-foundation aa515b7      # preserve the draft, non-destructively
git checkout master
git revert --no-edit aa515b7                     # -> 8d7e4b5
```

Result:

```
master (8d7e4b5)                    tree == d6cb2f3 (approved IMP-000 baseline), verified via
                                     `git diff d6cb2f3 master` returning no output
impl/001-core-foundation (aa515b7)  preserves the IMP-001 draft commit
```

History was not rewritten; no reset/rebase/amend/force-push was used. `master` now reflects a
normal linear history: IMP-000 baseline -> IMP-001 draft -> revert of that draft.

Status: RESOLVED.

## IMP001-RDY-B02 — Environment Verification (Resolved)

PHP is not on this machine's PATH (ServBay does not add it). Composer's shell wrapper at
`/c/ProgramData/ComposerSetup/bin/composer` itself shells out to a bare `php`, so it also fails
without PATH. Both are reachable through explicit full-path invocation:

```
PHP Executable:        C:\ServBay\packages\php\8.3\php.exe
PHP Version:           PHP 8.3.33 (cli) (NTS Visual C++ 2019 x64)
                       (ServBay also provides 8.1, 8.2, and a "current" symlink -> 8.4; 8.3 was
                       selected/verified here as it matches the IMP-001 §5 target baseline "PHP
                       8.3 compatible baseline")

Composer Invocation:   C:\ServBay\packages\php\8.3\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar
Composer Version:      Composer 2.10.3 (2026-08-27), running on PHP 8.3.33

Node Version:          v23.11.1   (/c/ServBay/bin/node)
npm Version:           10.9.2     (/c/ServBay/packages/node/current/npm)
Git Version:           git version 2.55.0.windows.3 (/mingw64/bin/git)
```

Global PATH Required: NO — all of the above were verified through explicit executable paths where
PATH lookup failed, per the Human's Decision 3. No PHP/Composer/Node/npm install or upgrade was
performed.

Status: RESOLVED. `docs/implementation/IMP-001-core-project-foundation.md` §6 was patched to
require "PHP runtime is accessible and version-verifiable" / "Composer is accessible and
version-verifiable" rather than a specific PATH arrangement.

## IMP001-RDY-B01 — Level 1-3 Materialization (Partially Resolved)

The following artifacts were materialized, each tagged `Source Status: Previously Approved /
Final / Locked External Baseline`, `Materialization Status: Repository Copy`, `Semantic Change:
NONE`, using only content explicitly supplied in this remediation's Human authorization:

```
docs/01-requirements/HUMAN-DECISION-REGISTER.md      — Q1-Q20 + superseded Q3-A
docs/02-architecture/MASTER-ARCHITECTURE.md          — locked principles + application shape
docs/02-architecture/FINANCIAL-POSTING-BOUNDARY.md   — canonical posting chain + invariants
docs/03-database/DATABASE-ARCHITECTURE.md            — MySQL 8.x, identity, money, integrity
docs/03-database/DATABASE-INVARIANTS.md              — idempotency, immutability, reproducibility
docs/04-security/SECURITY-ARCHITECTURE.md            — DENY-by-default, principals, token rule
docs/04-security/SECURITY-INVARIANTS.md              — invariant restatement of the above
docs/05-rbac/RBAC-ARCHITECTURE.md                    — authorization AND-chain + distinctions
```

The following target artifacts were **NOT materialized** because no source content beyond a bare
name/mention was supplied in this remediation's authorization, and the authorization explicitly
requires stopping for such an artifact rather than reconstructing it from guesses:

```
docs/01-requirements/MASTER-REQUIREMENTS.md    — only the Human Decision Register (Q1-Q20) was
                                                  supplied; a separate Master Requirements
                                                  narrative was not
docs/02-architecture/MODULE-OWNERSHIP.md       — only the shared Web/API logic principle was
                                                  supplied; no per-module ownership boundaries
docs/05-rbac/DATA-SCOPE-MODEL.md               — "Applicable Data Scope" appears only as a term
                                                  inside the RBAC AND-chain; no scope taxonomy
docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md       — "Required Business Authority" appears only as a
                                                  term inside the RBAC AND-chain; no authority
                                                  catalog
docs/05-rbac/AUTHENTICATION-ASSURANCE.md       — "Required Authentication Assurance" / "privileged
                                                  authentication assurance" appear only as terms;
                                                  no assurance-level model
```

Each materialized document that references one of these gaps says so explicitly and points back
to this record, so a reader does not mistake the absence for an oversight.

Status: PARTIALLY RESOLVED — the blocker is resolved for the artifacts where source content
existed; the four artifacts above remain genuinely blocked pending real source content from the
Human Authority or the external approved baseline. This is reported, not silently closed.

## Housekeeping

`.commandcode/` was inspected (`.commandcode/taste/taste.md` and a self-referential nested copy,
~8KB total) and found to be local tool-generated scaffolding with no project-authoritative
content. Added to `.gitignore` as `/.commandcode/`. The directory itself was not deleted.

## Traceability

`docs/implementation/IMP-001-core-project-foundation.md` was patched (§6, Document Control, §42)
to reference this record and the materialized Level 1-3 documents, rather than duplicating their
content.

## Result

```
IMP-000:                         FINAL / LOCKED (unaffected)
IMP-001 Specification:           DRAFT / REMEDIATED
IMP-001 Definition of Ready:     READY FOR TARGETED INDEPENDENT RE-AUDIT
IMP-001 Implementation:          NOT AUTHORIZED
Business Feature Coding:         NOT AUTHORIZED
Architecture Changed:            NO
Business Migrations Added:       NO
Authentication/RBAC Added:       NO
Financial Logic Added:           NO
Human Decisions Changed:         NO
```
