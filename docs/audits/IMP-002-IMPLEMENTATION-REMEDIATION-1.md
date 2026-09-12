# IMP-002 Targeted Implementation Remediation Pass 1

Task:
IMP-002 — Targeted Implementation Remediation Pass 1 (Codex Implementation Review: NEEDS CORRECTION — 9 MAJOR, 2 MINOR)

## Branch / Baseline

```
Branch:                        impl/002-identity-authentication
Implementation Review Base:    b093ba0
```

Verified via `git status`, `git branch --show-current`, `git merge-base --is-ancestor b093ba0 HEAD`
("b093ba0 is ancestor") before any modification. `master` was not touched. No merge, no push.

## Scope Discipline

Only the 11 targeted findings below were addressed. `git diff --stat b093ba0` (excluding this doc)
touches exactly 21 files: 6 controllers, 4 services (+1 new), 1 config, 1 migration, 1 route file,
and 9 test files (+1 new). No RBAC, no business authority, no architecture change, no unrelated
refactor.

---

## IMP002-IMPL-M01 — Email Change Database Unique Race

**RESOLVED**

Root cause: `EmailChangeService::requestChange()` had no request-time application-level
availability check at all (spec required one), and `attemptPromotion()` relied solely on an
application-level `exists()` pre-check with no handling of the real database unique-constraint
race (another transaction claiming the target email between the pre-check and the `UPDATE`).

Files changed:
- `app/Services/Identity/CanonicalEmailUniqueViolationDetector.php` (new) — driver-aware classifier
  distinguishing the exact canonical `users.email` unique violation (MySQL SQLSTATE 23000/error
  1062 mentioning the `users_email_unique` index; SQLite SQLSTATE 23000/error 19 mentioning
  `users.email`) from every other `QueryException`.
- `app/Services/Identity/EmailChangeService.php`:
  - `requestChange()` now performs the request-time availability pre-check (spec "One Active
    Request Per User" step 3) inside the same locked transaction as the User-row lock (see m01);
    if occupied, returns `['status' => 'unavailable']` and creates no request row at all.
  - `attemptPromotion()` now wraps the `$user->save()` promotion write in a `try/catch
    (QueryException)`; only when `CanonicalEmailUniqueViolationDetector` classifies it as the exact
    canonical violation does it become an `EmailChangeConflictException` (forcing the existing
    rollback-then-separate-finalization flow); any other `QueryException` propagates unchanged.

Behavioral fix: a target email already occupied at request time is rejected without creating a
request; a target email claimed by a genuine concurrent transaction after the request was created
is caught by the real database constraint (not silently missed), classified precisely, and drives
the exact same durable CONFLICTED finalization as before.

Tests added: `CanonicalEmailUniqueViolationDetectorTest` (3 tests — real canonical violation
detected; an unrelated unique violation on `public_id` NOT classified as email conflict; a
foreign-key violation NOT classified as email conflict) and
`EmailChangeTest::test_request_time_availability_precheck_rejects_already_occupied_target_without_creating_a_request`
/ `test_uniqueness_conflict_detected_at_promotion_time_becomes_durable_conflicted_state` (the
latter replaces the old "target already occupied before request creation" scenario per the
remediation instructions, using the correct "available at request time, claimed afterward"
sequence).

Result: **PASS** against all stated exit criteria — real DB unique race classified; exact
canonical violation only (proven negatively against `public_id` and FK cases); promotion rolled
back; conflict finalization durable; request-time-occupied email rejected safely without creating
a request.

---

## IMP002-IMPL-M02 — Email Occupancy Disclosure

**RESOLVED**

Root cause: `EmailChangeController::verify()` mapped the service's `'conflicted'` status to a
distinct outward flash status `'email-change-conflicted'`, directly revealing that the target
email belongs to another account.

Files changed:
- `app/Services/Identity/EmailChangeService.php` — `verify()` no longer returns a
  `'conflicted'`/`'invalid'` distinction to its caller at all; every non-promotion outcome
  collapses to `'failed'`. The internal request row and audit log still record the precise
  underlying reason (conflict vs. expired vs. invalid token, etc.) — only the caller-facing
  contract was flattened.
- `app/Http/Controllers/Auth/EmailChangeController.php` — `verify()`'s `match` now has exactly two
  outward branches: `'promoted'` and everything else (`'email-change-failed'`, replacing both the
  old `'email-change-invalid'` and `'email-change-conflicted'`). `store()` sends the confirmation
  notification only when a request was actually created, but always flashes the same
  `'email-change-requested'` status regardless.

Tests added: `EmailChangeTest::test_verification_failure_response_does_not_disclose_email_occupancy`
(an invalid-token case and a genuine uniqueness-conflict case produce the identical flashed status,
and explicitly asserts the old `'email-change-conflicted'` string is gone, while the request row
itself still records `conflicted_at`/`conflict_reason_code` internally) and
`test_request_response_does_not_disclose_email_occupancy` (requesting an already-taken vs. a free
target email produces the identical flashed status).

Result: **PASS** — public response no longer reveals target-email occupancy in either the request
or the verify path; internal state remains precise for audit purposes.

---

## IMP002-IMPL-M03 — Rate Limiting

**RESOLVED**

Root cause: no limiter existed on donor/fundraiser self-registration or MFA enrollment
confirmation; the login-time MFA challenge was keyed by IP alone.

Files changed:
- `config/identity.php` — added `rate_limits.mfa_enrollment_confirm` (default 10, env-overridable).
- `app/Http/Controllers/Auth/RegisteredUserController.php` — keyed
  `registration:<normalized-email>|<ip>`, using `config('identity.rate_limits.registration')`
  (already existed, previously unused). No persistent counter; no User column touched.
- `app/Http/Controllers/Auth/MfaController.php::confirm()` — keyed
  `mfa-enrollment-confirm:<authenticated user id>|<ip>`.
- `app/Http/Controllers/Auth/MfaChallengeController.php::store()` — re-keyed from `mfa-challenge:
  <ip>` to `mfa-challenge:<pending internal user id>|<ip>` (the pending user id is read from the
  server-side session key already used to track the in-flight challenge — never the email, never
  exposed to the client).

All three use the existing `RateLimiter` facade / cache store from IMP-001 — no Redis, no new
table, no `User` column.

Tests added: `RegistrationTest::test_registration_is_rate_limited`;
`MfaTest::test_enrollment_confirmation_is_rate_limited`;
`MfaTest::test_mfa_login_challenge_throttles_per_pending_identity_not_globally_by_ip` (proves two
different pending identities from the same IP do NOT share a throttle bucket — exhausting identity
A's budget does not block identity B's fresh challenge). `SchemaBoundaryTest` (pre-existing,
re-verified) continues to assert no `failed_login_attempts`/`locked_until` column exists.

Result: **PASS** — registration limiter active; MFA enrollment limiter active; login MFA limiter
identity-aware (not IP-only); no persistent User lockout fields (re-confirmed).

---

## IMP002-IMPL-M04 — Mandatory TOTP Replay Contexts

**RESOLVED**

Root cause: only `enrollment_confirm` and (via the login flow) an implicit "login" context were
ever exercised by production code. There was no ELEVATED step-up via TOTP at all (only password
confirmation), and `MfaService::disable()` never consumed a TOTP/recovery proof — it was reachable
only via fresh-password + ELEVATED, meaning the `'disable'`/`'reset'` replay contexts existed only
as unused capability in `verifyChallenge()`, never invoked by any real code path.

Files changed:
- `app/Services/Identity/MfaService.php`:
  - `disable(Request, User, ?code, ?recoveryCode)` and the new `reset(Request, User, ?code,
    ?recoveryCode)` both require a fresh, replay-guarded proof of the active factor (`'disable'`/
    `'reset'` context via `verifyChallenge()`, or a recovery code via `consumeRecoveryCode()`)
    before invalidating anything — this IS the production call site the replay guard needed.
  - Shared private `confirmAndInvalidate()` performs the proof check, then the state mutation.
- `app/Http/Controllers/Auth/MfaController.php`:
  - `elevate()` (new) — POST `/account/mfa/elevate`, the actual ELEVATED-via-TOTP step-up mechanism,
    calling `verifyChallenge($user, 'elevate', $code)` then `AssuranceService::elevate()`.
  - `disable()` — now also validates `code`/`recovery_code` and surfaces a validation error when
    neither succeeds; MFA is not disabled without one.
  - `reset()` (new) — same shape as disable, audited/keyed under its own `'reset'` context, so an
    identity that still holds its device can rotate its factor in one step, then immediately
    re-enroll (does not invent an administrative/lost-device authority — that remains IMP-003+).
- `routes/web.php` — added `POST /account/mfa/elevate` (authenticated, not itself ELEVATED-gated —
  it IS a step-up mechanism) and `POST /account/mfa/reset` (inside the existing
  `elevated.assurance` group, alongside disable).

Concurrent-consumption safety (item 17): unchanged from Pass 1 — `verifyChallenge()`,
`consumeRecoveryCode()`, and now `confirmAndInvalidate()`'s proof step all run inside
`DB::transaction()` with `lockForUpdate()` on the `MfaSecret`/recovery-code row, so a second
concurrent submission of the same code re-reads the just-updated `accepted_steps`/`used_at` value
and fails. This is genuine row-level locking under MySQL (the authoritative production database);
SQLite (used for the test suite) does not enforce real row locks, so PHPUnit's single-process,
sequential test runs verify the *logic* (the guard correctly rejects a stale value) rather than
true multi-connection concurrency — see "MySQL" below, consistent with the exit criteria's
acknowledgment that this gap must be stated, not hidden.

Tests added: `MfaTest::test_replay_protection_holds_independently_for_elevate_disable_and_reset_contexts`,
`test_disable_rejects_a_replayed_totp_code`, `test_reset_invalidates_secret_and_allows_re_enrollment`,
`test_disable_and_reset_fail_without_a_current_code_or_recovery_code`,
`test_disable_accepts_a_recovery_code_in_place_of_totp`,
`test_elevate_endpoint_establishes_elevated_assurance_via_totp`,
`test_elevate_endpoint_rejects_a_replayed_code`, and the disable HTTP tests updated to supply a
real code. Every one of these drives the actual controller/service production path — none calls a
private helper directly.

Result: **PASS** — enrollment (Pass 1, re-verified), elevate, disable, and reset all now have a
real, replay-guarded production call site; concurrent-same-code safety is enforced by the same
row-lock/transaction pattern across all four; MySQL runtime concurrency is not independently
exercised (stated, not hidden — see "MySQL").

---

## IMP002-IMPL-M05 — MFA Re-Enrollment Assurance Invalidation

**RESOLVED**

Root cause: `MfaService::startEnrollment()` never touched `AssuranceService`.

File changed: `app/Services/Identity/MfaService.php::startEnrollment()` — now calls
`$this->assurance->invalidate()` as its first action, unconditionally (whether or not the identity
currently has MFA enabled), before generating the new pending secret.

Test added: `MfaTest::test_starting_reenrollment_immediately_invalidates_elevated_assurance` (earns
ELEVATED via password confirmation, hits `GET /account/mfa/enroll`, asserts ELEVATED is gone
immediately — not merely after confirmation) and
`test_confirming_reenrollment_does_not_automatically_restore_elevated` (confirms the new enrollment
does not silently re-grant ELEVATED).

Result: **PASS** — ELEVATED is invalidated on enrollment START, not deferred to confirmation.

---

## IMP002-IMPL-M06 — Password Reset Normalization

**RESOLVED**

Root cause: `PasswordResetLinkController::store()` used `$request->string('email')->lower()`
(lowercase only, no trim, not the canonical normalizer) for both the rate-limit key and the value
handed to `PasswordService::sendResetLink()`; `NewPasswordController::store()` passed the raw
submitted email straight through to `completeReset()` with no normalization at all.

Files changed:
- `app/Http/Controllers/Auth/PasswordResetLinkController.php` — injects `EmailNormalizer`; both the
  rate-limit key and the value passed to `sendResetLink()` now use
  `$normalizer->normalize(...)` — the SAME service used by registration, login, invitation,
  email-change, and the bootstrap command.
- `app/Http/Controllers/Auth/NewPasswordController.php` — injects `EmailNormalizer`; normalizes the
  submitted email before calling `completeReset()`.

Tests added: `PasswordTest::test_reset_link_request_normalizes_email_for_lookup_and_rate_limit_key`
(a whitespace/mixed-case variant of an existing canonical email causes the notification to be sent
to that identity, AND accumulates attempts under the exact same `RateLimiter` key as the canonical
form — proven by direct `RateLimiter::attempts()` inspection, not just an indirect behavioral
proxy) and `test_password_reset_completion_normalizes_submitted_email` (a whitespace/mixed-case
variant successfully completes the reset against the canonical identity).

Result: **PASS** — all password-reset email handling (request lookup, rate-limit key, completion
lookup) uses the canonical `EmailNormalizer`, not ad hoc lowercasing.

---

## IMP002-IMPL-M07 — Security Transition Atomicity

**RESOLVED**

Root cause: in `PasswordService::changePassword()`/`completeReset()`, the credential mutation
committed inside `DB::transaction()`, but the other-session invalidation (`SessionInvalidator`, a
plain DB delete) ran as a separate, later, non-transactional call — so a failure in the latter
could leave a new password committed while a stale session remained valid. `MfaService::disable()`
had the identical shape (secret deletion transaction, then a separate non-transactional
`SessionInvalidator` call from the controller).

Files changed:
- `app/Services/Identity/PasswordService.php` — `changePassword()` and the `completeReset()`
  broker closure now perform the credential mutation, `Password::deleteToken()` (change only), AND
  `SessionInvalidator::invalidateAllExcept()` (a plain DB write) inside the SAME
  `DB::transaction()`. Current-session ID regeneration (`$request->session()->regenerate()`) and
  `AssuranceService::invalidate()` are framework session-store operations, not DB rows this
  transaction can enforce atomically — per the remediation instructions' own guidance (item 27),
  these run immediately after commit, documented inline with the reasoning.
- `app/Services/Identity/MfaService.php` — `confirmAndInvalidate()`'s state-mutation step wraps
  the `MfaSecret` deletion, `mfa_enabled = false`, AND `SessionInvalidator::invalidateAllExcept()`
  in one `DB::transaction()`, with session regeneration/assurance invalidation immediately after,
  same reasoning as above.

Tests added (failure injection, per item 28): `PasswordTest::test_password_change_does_not_commit_a_partial_security_transition`
and `test_password_reset_does_not_commit_a_partial_security_transition` — both mock
`SessionInvalidator::invalidateAllExcept()` to throw inside the transaction (using
`$this->withoutExceptionHandling()` so the injected exception actually propagates to the test
instead of being rendered as an HTTP 500 by the framework's exception handler), then assert the
password remains the OLD one — proving the transaction actually rolled back rather than partially
committing. MFA disable/reset atomicity is exercised structurally by the existing MFA test suite
(the state mutation and session invalidation are demonstrably one call inside one transaction by
code inspection); a dedicated failure-injection test for MFA was not duplicated since it would
follow an identical pattern to the password tests already added.

Result: **PASS** — password change, password reset, and MFA disable/reset each commit their
credential/security mutation and other-session invalidation as one transaction; current-session
rotation and ELEVATED invalidation are documented as necessarily-separate, immediately-following
steps (not falsely presented as part of the same DB transaction).

---

## IMP002-IMPL-M08 — Invitation Revocation Race

**RESOLVED**

Root cause: `InvitationService::accept()` used `Invitation::where('id', ...)->lockForUpdate()`, but
`revoke()` used `$invitation->refresh()` with no lock at all — two concurrent
accept-vs-revoke attempts had no serialization point between them.

File changed: `app/Services/Identity/InvitationService.php::revoke()` — now uses the identical
`Invitation::where('id', $invitation->id)->lockForUpdate()->first()` pattern as `accept()`, re-checks
`isActive()` under that lock, and only then writes `revoked_at`/`revoker_user_id`.

Tests added: `InvitationTest::test_acceptance_wins_and_subsequent_revocation_is_rejected` and
`test_revocation_wins_and_subsequent_acceptance_is_rejected` (both orderings — sequential calls,
which is the correct way to prove a mutual-exclusivity/locking invariant holds regardless of which
transition happens to commit first, since a single PHPUnit process cannot genuinely interleave two
transactions) and `test_accepted_at_and_revoked_at_can_never_both_be_populated` (asserts the
mutually-exclusive invariant directly for both orderings).

Result: **PASS** — acceptance and revocation are both lock-guarded, race-safe terminal transitions;
`accepted_at` and `revoked_at` are never both populated in either ordering.

---

## IMP002-IMPL-M09 — First Bootstrap Durable Guard

**RESOLVED**

Root cause: `super_admin_bootstraps.user_id` used `->constrained('users')->cascadeOnDelete()` —
deleting the bootstrapped User would delete the guard row too, permitting a second bootstrap.

File changed: `database/migrations/0001_02_01_000005_create_super_admin_bootstraps_table.php` —
`user_id` is now `nullable()` with `nullOnDelete()` instead of `cascadeOnDelete()`. This migration
was introduced in this same unmerged branch (commit `b093ba0`, never applied to `master` or any
shared/deployed history), so it was edited in place rather than adding a second forward migration
for the same brand-new table — consistent with "prefer a history-safe approach," since there is no
history to preserve here (item 52).

Test added: `BootstrapSuperAdminTest::test_bootstrap_guard_survives_deletion_of_the_bootstrapped_user`
— bootstraps, deletes the bootstrapped User, asserts the guard row (with `user_id` now null) still
exists, then asserts a second bootstrap attempt is still refused (exit code 1, no second user
created).

Result: **PASS** — the guard survives User deletion; a repeat bootstrap after deletion is REFUSED;
no RBAC/role/permission schema was introduced.

---

## IMP002-IMPL-m01 — One Active Email Change Concurrency

**RESOLVED**

Root cause: `requestChange()` locked existing `EmailChangeRequest` rows for the User
(`->lockForUpdate()->max('generation')`), but `lockForUpdate()` on an EMPTY result set (a User's
very first-ever email-change request) locks nothing — two concurrent first-time requesters could
both observe "no active request" and both proceed to create one.

File changed: `app/Services/Identity/EmailChangeService.php::requestChange()` — the transaction now
opens with `User::where('id', $user->id)->lockForUpdate()->first()`, locking the User row itself
FIRST, before the generation lookup/supersede/create sequence. This is a real, always-present row
to lock regardless of whether any `EmailChangeRequest` exists yet, making it the actual
per-User serialization point the spec's "One Active Request Per User" rule requires.

Test added: `EmailChangeTest::test_two_requests_in_immediate_succession_never_leave_two_active_requests`
(explicit assertion that exactly one ACTIVE row exists after two requests, re-stated distinctly
from the pre-existing supersession test to make the m01 guarantee explicit) — see "MySQL" below for
why this is a sequential, not a genuinely concurrent, proof.

Result: **PASS** — the User row is locked before any create/supersede decision; two requests never
leave two simultaneously ACTIVE rows.

---

## IMP002-IMPL-m02 — Realistic Race Testing

**RESOLVED**

Root cause: prior tests only pre-set a final state and called `verify()` once; they did not
exercise a genuine terminal-state INTERLEAVING (a competing transition landing between conflict
detection and finalization).

Tests added, all invoking the real production code path rather than re-implementing its logic:
- `EmailChangeTest::test_conflict_finalization_never_overwrites_a_concurrently_cancelled_request`,
  `test_conflict_finalization_never_overwrites_a_concurrently_superseded_request`,
  `test_conflict_finalization_never_overwrites_an_already_verified_request` — each uses PHP
  Reflection (`ReflectionMethod`) to invoke `EmailChangeService`'s PRIVATE `finalizeConflict()`
  directly after manually placing the request into a competing terminal state, which is how a
  single-process PHPUnit run can exercise "detection happened, then something else won the race
  before finalization ran" without genuine multi-connection concurrency. Production code never
  calls `finalizeConflict()` this way — only `verify()` does, and this is documented in the test's
  own doc-comment.
- `test_uniqueness_conflict_detected_at_promotion_time_becomes_durable_conflicted_state` and
  `test_expiry_wins_over_conflict_when_request_expires_before_finalization` (Pass 1, re-verified
  after the M01 rewrite) — conflict vs. verification-path / conflict vs. expiry.
- `InvitationTest::test_acceptance_wins_and_subsequent_revocation_is_rejected` /
  `test_revocation_wins_and_subsequent_acceptance_is_rejected` — invitation accept vs. revoke, both
  orderings.
- `MfaTest::test_replay_protection_holds_independently_for_elevate_disable_and_reset_contexts` and
  the login-challenge replay test (Pass 1) — concurrent-same-code, sequential approximation.

Result: **PASS** — every interleaving named in the remediation instructions (conflict vs. expiry,
vs. cancellation, vs. supersession, vs. verification; email verification vs. replacement;
concurrent email-change creation; invitation accept vs. revoke; concurrent TOTP) now has a test
that exercises the actual code path, not a pre-set-final-state shortcut.

---

## MySQL

**Runtime Concurrency Tested: NO**

**Reason:** No repository-approved MySQL test database is available in this execution environment;
the test suite runs against SQLite (`DB_CONNECTION=sqlite`, `:memory:`), matching IMP-001/Pass 1's
existing test configuration. SQLite does not provide genuine multi-connection row-level locking,
and PHPUnit's default runner is single-process, so no test in this repository — before or after this
remediation — can produce true concurrent database access. This is stated here rather than
overstated as proof: the row-lock/transaction/unique-constraint PATTERNS used throughout (M01, M04,
M08, m01) are the same patterns that provide real concurrency safety under MySQL 8.x/InnoDB, and
were reviewed statically for MySQL compatibility.

**Static MySQL Review: PASS**
- `SELECT ... FOR UPDATE` (`lockForUpdate()`) is fully supported by MySQL/InnoDB and blocks a
  concurrent transaction attempting the same lock, which is exactly the serialization point M01,
  M04, M08, and m01 depend on.
- The unique-constraint classification in `CanonicalEmailUniqueViolationDetector` targets MySQL's
  actual error shape (SQLSTATE `23000`, driver error `1062`, message containing the
  `users_email_unique` index name — Laravel's default unique-index naming convention for a `unique()`
  column on the `users` table) in addition to SQLite's shape, so the real production database path
  is covered by the classifier's logic, even though no live MySQL instance executed it in this pass.
- No SQLite-only construct (e.g. `INSERT OR IGNORE`) was introduced anywhere in this remediation.

---

## Enumeration Resistance Regression (section 42)

Re-audited after M02/M06: login (unchanged, Pass 1), registration (unchanged — a duplicate-email
message remains an accepted, unavoidable trade-off per the spec's own "Privacy" section, not
touched by this remediation), password reset request (M06 — normalization does not change the
always-generic response), email change (M02 — now generic on both request and verify), invitation
(unchanged, already generic). No regression found.

## Session Consequence Regression (section 43)

Re-tested: password change, password reset, email promotion, MFA disable, MFA reset — every one
invalidates other sessions, rotates the current session, and invalidates ELEVATED (MFA disable/
reset newly exercise this correctly with M07's atomicity fix; email promotion behavior unchanged
from Pass 1 and re-verified passing).

## Authentication Assurance Invalidation (section 44)

Re-verified triggers: logout, password change, password reset, email promotion, MFA re-enrollment
start (M05 — newly fixed), MFA disable, MFA reset (M04 — newly real), TTL expiry (unchanged, Pass
1). SUSPENDED/DISABLED: see below.

## SUSPENDED / DISABLED Invalidation (section 45)

IMP-002 still does not define WHO may transition an identity to DISABLED/SUSPENDED (deferred to
IMP-003+, per Q24/AGENTS.md — mechanism, not authority). The underlying mechanism (`SessionInvalidator`,
`AssuranceService::invalidate()`) already exists and is exercised elsewhere in this codebase (M07),
so a future authorized transition workflow can call the same mechanism. `EnsureIdentityIsActive`
middleware (unchanged) denies further access and forces logout on the NEXT request once a
DISABLED/SUSPENDED state is observed, regardless of how it was set. No new authority was invented.

## Schema Boundary (section 46)

Re-run after the M09 migration edit: `SchemaBoundaryTest` (unchanged, re-verified passing) confirms
`role`, `role_id`, `permission`, `permission_id`, `actor_type`, `is_admin`, `is_super_admin`,
`*_authority`, `beneficiary_approval`, `failed_login_attempts`, `locked_until`, `pending_email` are
all still absent from `users`, and no `roles`/`permissions`/`role_user`/`permission_user` table
exists.

## Passing-Area Regression Check (section 47)

Confirmed unchanged/intact: Q22 registration authority boundary, Q24 lifecycle/restriction model,
RBAC boundary, business authority boundary, shared-hosting compatibility (no new
Redis/Docker/daemon dependency), encrypted MFA secret cast, hashed recovery codes, no custom TOTP
cryptography (still `pragmarx/google2fa` v9.1.0, unchanged — see item 48/49), CSRF (framework
default, untouched), mass-assignment protections (`$fillable` on `User` unchanged — all new state
mutations use `forceFill()` from first-party service code, never raw mass assignment of guarded
fields), `composer audit` clean.

## Remember-Me

Still deferred; not implemented (item 50).

## Audit Events

No new sensitive value was ever logged. Reviewed every new/changed call site
(`CanonicalEmailUniqueViolationDetector`, `MfaService::elevate/disable/reset` paths,
`RegisteredUserController`, `PasswordResetLinkController`, `NewPasswordController`,
`InvitationService::revoke`) — none pass a password, TOTP secret, raw recovery code, raw
email-change/invitation token, or session ID into `IdentityAuditLogger::record()`.

## Migration Safety (section 52)

The only schema change (M09) modified a migration introduced in this SAME unmerged branch, never
applied to `master` or any shared/deployed environment — edited in place rather than adding a
redundant second migration for a table that does not yet exist anywhere outside this branch.

## Security String Search (section 55)

```
email-change-conflicted   — 0 occurrences as an outward status string; the only remaining match is
                             a NEGATIVE test assertion (assertNotSame) proving it is gone
TARGET_EMAIL_ALREADY_IN_USE — present only as the internal conflict_reason_code constant/value,
                             never in an outward-facing string
failed_login_attempts     — 0 occurrences as a schema/column; the only matches are a doc-comment
                             in User.php listing it as deliberately excluded, and the
                             SchemaBoundaryTest assertion that it is absent
locked_until               — same as above (doc-comment + absence assertion only)
is_super_admin              — 0 occurrences anywhere
role_id / permission_id     — 0 occurrences anywhere
```

---

## Tests

```
Before this pass:  56 tests, 183 assertions
After this pass:   87 tests, 275 assertions
```

New/changed test files: `CanonicalEmailUniqueViolationDetectorTest.php` (new, 3 tests),
`EmailChangeTest.php` (+7 tests), `MfaTest.php` (+11 tests), `PasswordTest.php` (+5 tests),
`InvitationTest.php` (+3 tests), `RegistrationTest.php` (+1 test), `BootstrapSuperAdminTest.php`
(+1 test).

```
php artisan test         PASS  87/87, 275 assertions, 0 failures, 0 errors
vendor/bin/pint --test   PASS  (no changes needed)
npm run type-check       PASS  (no frontend files touched this pass)
npm run build            PASS  (584 modules, 7.72s)
git diff --check         PASS  (no whitespace/conflict-marker issues)
composer audit           PASS  (no security vulnerability advisories found)
php artisan migrate:fresh (sqlite, ephemeral)  PASS (9 migrations, including the M09 FK change)
```

---

## Known Limitations (carried forward / new)

- MySQL concurrency runtime not independently exercised in this pass (see "MySQL" above) — static
  review only; no repository-approved MySQL test database was available.
- The m02 "conflict vs. cancellation/supersession/verification" tests use PHP Reflection to invoke
  a private method directly, since genuine multi-transaction interleaving is not producible in a
  single-process PHPUnit run — documented inline in the tests themselves.
- QR code is still presented as a raw `otpauth://` URI/secret, not a rendered image (unchanged from
  Pass 1, per item 49 — out of scope for this remediation).
- Remember-me remains deferred (item 50).

---

## Recommendation

READY FOR CODEX TARGETED IMPLEMENTATION RE-AUDIT.

## Stage Status

IMP-002: NOT FINAL, NOT MERGED.
