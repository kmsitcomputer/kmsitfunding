# IMP-002 Targeted Implementation Remediation Pass 2

Task:
IMP-002 — Targeted Implementation Remediation Pass 2 (Codex Targeted Re-Audit: NEEDS CORRECTION — 2 MAJOR, 2 MINOR)

## Branch / Baseline

```
Branch:              impl/002-identity-authentication
Remediation Base:    65e96e6
```

Verified via `git branch --show-current` and `git merge-base --is-ancestor 65e96e6 HEAD` ("65e96e6
is ancestor") before any modification. `master` was not touched. No merge, no push.

## Scope Discipline

Only the 4 targeted findings below were addressed:
`IMP002-REAUDIT-M01`, `IMP002-IMPL-M07`, `IMP002-IMPL-m01`, `IMP002-IMPL-m02`.
`git diff --stat 65e96e6` touches exactly 8 files: 1 controller, 3 services, 1 config, 3 test
files. No RBAC, no business authority, no architecture change. Previously-resolved findings
(M01–M06, M08, M09 from Pass 1) were not reopened or rewritten — the only prior-pass files touched
are `EmailChangeService.php`, `MfaService.php`, and `PasswordService.php`, and each change is
additive/reordering, not a rewrite of their resolved logic.

---

## IMP002-REAUDIT-M01 — TOTP Production Paths Not Rate-Limited

**RESOLVED**

Root cause: Pass 1 added the ELEVATED step-up, disable, and reset TOTP-verification production
paths (closing M04) but did not add rate limiting to any of the three — only enrollment
confirmation and the login challenge were throttled.

Files changed:
- `config/identity.php` — added `rate_limits.mfa_elevate`, `rate_limits.mfa_disable`,
  `rate_limits.mfa_reset` (defaults: 5 each, env-overridable).
- `app/Http/Controllers/Auth/MfaController.php`:
  - `elevate()` — keyed `mfa-elevate:<user id>|<ip>`; hits on failure, clears on success.
  - `disable()` — keyed `mfa-disable:<user id>|<ip>`; throttles the TOTP/recovery-code proof
    itself (fresh password + ELEVATED alone does not protect that codespace from brute-force).
  - `reset()` — keyed `mfa-reset:<user id>|<ip>`.

All three use the existing `RateLimiter` facade / cache store — no Redis, no new table, no `User`
column. Keys are flow-prefixed and identity-aware (authenticated user id, never email), never
IP-only, and never shared across flows.

Tests added: `MfaTest::test_elevate_endpoint_is_rate_limited`,
`test_disable_endpoint_is_rate_limited`, `test_reset_endpoint_is_rate_limited`,
`test_totp_rate_limit_buckets_are_independent_per_user_and_per_flow` (exhausting user A's
`elevate` bucket does not affect A's own `disable` bucket or user B's `elevate` bucket),
`test_successful_elevate_clears_the_rate_limit_bucket` (a success clears the counter rather than
leaving it to expire naturally). Each also asserts no persistent lifecycle mutation
(`mfa_enabled` remains true when disable/reset is merely throttled).

Result: **PASS** — enrollment (unchanged), login (unchanged), ELEVATED, disable, and reset are all
now throttled; identity-aware; IP-aware; flow-aware (independent buckets); no persistent lockout
state introduced.

---

## IMP002-IMPL-M07 — ELEVATED Invalidation Fail-Open After DB Commit

**RESOLVED**

Root cause: in `PasswordService::changePassword()`/`completeReset()` and
`MfaService::confirmAndInvalidate()`, the sequence after the DB transaction committed was
`$request->session()->regenerate(); $this->assurance->invalidate();` — regenerate() FIRST, then
assurance invalidation. If `regenerate()` throws, `invalidate()` never runs, and a retained current
session could continue carrying pre-transition ELEVATED assurance even though the credential/MFA
mutation had already committed.

Files changed (a pure reordering — no other logic changed):
- `app/Services/Identity/PasswordService.php` — `changePassword()` and the `completeReset()`
  broker closure now call `$this->assurance->invalidate()` BEFORE
  `$request->session()->regenerate()`.
- `app/Services/Identity/MfaService.php` — `confirmAndInvalidate()` (shared by `disable()` and
  `reset()`) — same reordering.
- `app/Services/Identity/EmailChangeService.php` — `attemptPromotion()` reordered identically for
  consistency, even though both calls already sit inside the same DB transaction there (a
  `regenerate()` failure already rolled back the whole promotion regardless of ordering in that
  one case — see inline comment).

Why this closes the gap: `AssuranceService::invalidate()` mutates the CURRENT request's in-memory
session attributes (`Session::forget(...)`) synchronously. Once that call has run, the ELEVATED
keys are gone from the attribute bag that Laravel's session-termination middleware will persist at
the end of the request — regardless of whether the subsequent `regenerate()` call succeeds or
throws. The invariant no longer depends on `regenerate()` succeeding.

Tests added (failure injection, all at the SERVICE level using a Request whose session is the
exact same default-driver Store instance the `AssuranceService`'s `Session` facade calls resolve
to — see `requestWithFailingSessionRegeneration()` in both test files — with `regenerate()`
replaced by a throwing Mockery partial mock so ELEVATED state set/read through the facade and
through the request agree):
- `PasswordTest::test_password_change_leaves_elevated_absent_when_session_regeneration_fails`
- `PasswordTest::test_password_reset_leaves_elevated_absent_when_session_regeneration_fails`
- `MfaTest::test_disable_leaves_elevated_absent_when_session_regeneration_fails`
- `MfaTest::test_reset_leaves_elevated_absent_when_session_regeneration_fails`

Each proves: (1) the credential/security mutation is committed (password rehashed / MFA secret
gone) despite the injected failure, and (2) `AssuranceService::isElevated()` is `false` afterward —
the regeneration failure never leaves ELEVATED behind. This is a stronger, more direct proof than
Pass 1's `SessionInvalidator`-failure tests (which proved the earlier DB-transaction atomicity, a
different failure point).

Result: **PASS** — password change, password reset, MFA disable, and MFA reset all invalidate
ELEVATED before the fallible session-rotation call; a rotation failure never leaves ELEVATED
active; other-session invalidation (a DB write, already inside the earlier transaction) is
unaffected by this reordering.

---

## IMP002-IMPL-m01 — Email Change Lock Order (continued)

**RESOLVED**

Two distinct defects were found and fixed:

**1. Opposing lock order.** `requestChange()` locked `User` first, then `EmailChangeRequest`
(via the generation lookup). `attemptPromotion()` locked `EmailChangeRequest` first (explicit
`SELECT ... FOR UPDATE`) and only acquired the `User` row's lock implicitly and LATER, via the
promotion `UPDATE` statement — the opposite order. Two concurrent transactions taking opposite
lock orders on the same two resources is a classic MySQL deadlock pattern.

File changed: `app/Services/Identity/EmailChangeService.php::attemptPromotion()` — now opens with
`User::where('id', $user->id)->lockForUpdate()->firstOrFail()` FIRST, then locks the
`EmailChangeRequest` row, exactly matching `requestChange()`'s order. The rest of the method now
operates on the locked `$lockedUser` instance (promotion write, session invalidation, audit) for
consistency. No token-binding semantics or unique-race handling changed.

**2. Active-only supersession.** Both `requestChange()`'s supersede-update and
`attemptPromotion()`'s defensive supersede-update matched rows solely on the four terminal columns
being null, with no `expires_at` check — an already-EXPIRED, unresolved request could be silently
rewritten to SUPERSEDED by a later request/promotion, which the spec's terminal-state model
(EXPIRED is determined purely by `now >= expires_at`, independent of any write) does not permit.

File changed: both `UPDATE ... SET superseded_at = now()` queries in
`EmailChangeService.php` now add `->where('expires_at', '>', now())`, so an expired row is
structurally excluded, not merely accidentally spared.

Tests added: `EmailChangeTest::test_expired_request_is_never_rewritten_to_superseded_by_a_new_request`
(a request expires, then a second request is created — the first remains EXPIRED,
`superseded_at` stays null).

Result: **PASS** — one consistent User → EmailChangeRequest lock order across request creation,
replacement, and promotion; an EXPIRED request is never rewritten to SUPERSEDED; unique-race
handling (M01, Pass 1) and token-binding semantics are unchanged and re-verified passing.

---

## IMP002-IMPL-m02 — Race Test Quality (continued)

**RESOLVED**

Root cause: Pass 1's interleaving tests (via reflection into `finalizeConflict()`) were a good
start but the conflict-vs-expiry scenario still only pre-set `expires_at` into the past before
calling `verify()` once, rather than exercising the actual rollback → (expiry happens) →
finalization BOUNDARY; and there was no test proving an EXPIRED request is protected from a
DIFFERENT kind of overwrite (supersession, not just conflict-finalization), nor an explicit test of
"verification commits first, then a replacement is created" (only the reverse ordering existed).

Tests added, all exercising the real private methods via reflection rather than re-implementing
their logic — new internal test seam: a `callAttemptPromotion()` helper (mirroring the existing
`callFinalizeConflict()` helper) invokes `EmailChangeService::attemptPromotion()` directly, so a
test can pause exactly between rollback and finalization and mutate state in between:
- `test_conflict_rollback_then_expiry_then_finalization_leaves_request_expired` — calls
  `attemptPromotion()` (returns `'conflict'`, nothing persisted), THEN expires the request, THEN
  calls `finalizeConflict()` — proving the actual boundary, not a pre-set final state.
- `test_expired_request_is_never_rewritten_to_superseded_by_a_new_request` (m01, see above; also
  closes an m02 gap — "conflict vs expiry" is not the only place EXPIRED must be protected).
- `test_verification_committed_first_then_replacement_created_afterward` (Case A: verification
  wins, then a replacement is created — the old request remains VERIFIED, never rewritten).
- `test_replacement_committed_first_then_verification_of_old_token_is_rejected` (Case B: the
  reverse ordering, re-stated explicitly for clarity alongside Case A).
- `MfaTest::test_replay_guard_conditional_update_rejects_stale_timestep_deterministically` —
  invokes `MfaService::verifyWithReplayGuard()` (the actual atomic conditional-update operation)
  directly via reflection, proving the conditional transition itself (not just its two observable
  call-site outcomes) rejects a stale time-step deterministically, and that the persisted
  `accepted_steps` marker is the real time-step integer, not a boolean (re-confirming the Pass 1
  M04 fix at the mechanism level).

Regarding true concurrency (invitation race, concurrent request creation, concurrent TOTP): no
repository-approved MySQL test database was available in this environment (see "MySQL" below), so
these remain sequential/deterministic-transition proofs, honestly documented as such rather than
presented as genuine concurrency proof. Invitation race tests (Pass 1) were left untouched, per
instruction — already PASS.

Result: **PASS** — every named interleaving (conflict vs. expiry via the actual rollback/
finalization boundary; conflict vs. cancellation/supersession/verification, Pass 1; verification
vs. replacement, both orderings explicitly; expired-vs-supersession) is now exercised against the
real private-method boundary rather than a pre-set final state; the TOTP atomic conditional-update
mechanism itself is tested directly; true MySQL-connection parallelism remains unexercised and is
stated as such, not hidden.

---

## MySQL

**Runtime Concurrency Tested: NO**

A local MySQL 8 instance (ServBay, `127.0.0.1:3306`) is reachable in this environment. Per this
task's own instruction (section 28): creating a disposable test database is permitted only when
"clearly authorized by repository governance." A search of `docs/00-governance/`,
`docs/adr/`, and `docs/decisions/` found no policy authorizing or describing a MySQL test-database
provisioning process for this repository (the existing test suite has always run against SQLite
`:memory:`, per IMP-001/Pass 1). Absent that explicit authorization, no test database was created,
per the instruction's own fallback ("DO NOT CREATE IT ... document: MySQL concurrency runtime
still unavailable").

**Static MySQL Review: PASS**
- Lock acquisition order is now consistently User → EmailChangeRequest across
  `requestChange()`/`attemptPromotion()` — the specific pattern MySQL/InnoDB deadlock detection
  is most reliably avoided by, since two transactions taking locks in the same order cannot form a
  cycle.
- `SELECT ... FOR UPDATE` (`lockForUpdate()`) is real row-level locking under InnoDB and is what
  makes both the email-change serialization and the TOTP replay-guard conditional update atomic
  under genuine concurrent access; SQLite does not provide this, which is why the test suite proves
  the conditional LOGIC deterministically rather than true concurrent execution.
- The unique-constraint classifier (`CanonicalEmailUniqueViolationDetector`, Pass 1, unchanged)
  targets MySQL's real error shape (SQLSTATE 23000 / error 1062 / `users_email_unique`) in addition
  to SQLite's.
- No deadlock exception is converted into a business state anywhere in this codebase — a
  `QueryException` that is not the classified canonical email-unique violation propagates
  unchanged (Pass 1, re-verified; unaffected by this pass's changes).
- Session-table deletion (`SessionInvalidator`) remains a plain, unconditional `DELETE ... WHERE
  user_id = ? AND id != ?` — no row-lock ordering concern beyond what's already covered by the
  User-row lock preceding it in every calling transaction.

---

## Q21–Q25

No semantic change to any of Q21–Q25. Q23 (MFA) is specifically re-verified PASS now that every
production TOTP-verification path (enrollment, login, elevate, disable, reset) is both
replay-guarded (Pass 1) AND rate-limited (this pass).

## Regression Check

Re-verified passing, unchanged from Pass 1: email unique-race classification and generic conflict
response (M01/M02), password-reset canonical normalization (M06), MFA replay protection across all
four contexts (M04) and re-enrollment assurance invalidation (M05), invitation accept/revoke race
safety (M08, untouched), bootstrap guard durability (M09, untouched), RBAC boundary, business
authority boundary, shared-hosting compatibility (no new dependency).

## Tests

```
Before this pass:  87 tests, 275 assertions
After this pass:   101 tests, 327 assertions
```

New/changed test files: `EmailChangeTest.php` (+4 tests), `MfaTest.php` (+8 tests),
`PasswordTest.php` (+3 tests). No test was deleted or weakened.

```
php artisan test         PASS  101/101, 327 assertions, 0 failures, 0 errors
vendor/bin/pint --test   FAIL initially (2 files: import ordering / fully-qualified-strict-types
                         in the two new test helpers) — fixed via `vendor/bin/pint` (formatting
                         only, no behavioral change) BEFORE this final run; `pint --test` now PASS
npm run type-check       PASS  (no frontend files touched this pass)
npm run build            PASS  (584 modules, 2.32s)
git diff --check         PASS  (no whitespace/conflict-marker issues)
composer audit           PASS  (no security vulnerability advisories found)
php artisan migrate:fresh (sqlite, ephemeral)  PASS (9 migrations)
```

## Security Invariants (final check)

```
No ELEVATED state survives sensitive transition failure boundary   CONFIRMED (M07 tests)
All production TOTP verification endpoints throttled                CONFIRMED (M01 tests)
No two active email-change requests per User                        CONFIRMED (m01 tests, Pass 1+2)
Expired request never rewritten to SUPERSEDED                       CONFIRMED (m01/m02 tests)
Consistent User → EmailChangeRequest lock order                     CONFIRMED (code + static review)
No RBAC leakage                                                     CONFIRMED (SchemaBoundaryTest)
No persistent LOCKED state                                          CONFIRMED (SchemaBoundaryTest)
No plaintext MFA/recovery secrets                                   CONFIRMED (unchanged, Pass 1)
```

## Known Limitations (carried forward / unchanged)

- MySQL concurrency runtime not independently exercised — a reachable local MySQL instance exists,
  but creating a test database against it was not clearly authorized by repository governance, so
  it was not done (per this task's own explicit fallback instruction).
- QR code still presented as raw `otpauth://` URI/secret, not a rendered image (unchanged, out of
  scope).
- Remember-me remains deferred.

## Recommendation

READY FOR CODEX TARGETED IMPLEMENTATION RE-AUDIT PASS 2.

## Stage Status

IMP-002: NOT FINAL, NOT MERGED.
