# IMP-002 Implementation Pass 1

Task:
IMP-002 — Identity + Authentication Implementation

## Branch

```
Branch:            impl/002-identity-authentication
Baseline Commit:   53a5f5a (docs(imp-002): close email conflict expiry race)
```

Verified via `git branch --show-current` and `git merge-base --is-ancestor 53a5f5a HEAD`
("ANCESTOR OK") before and after implementation. `master` was not touched. No merge and no push
were performed.

## Environment

```
PHP:        8.3.33 (C:\ServBay\packages\php\8.3\php.exe)
Laravel:    13.31.0
Node/npm:   as established by IMP-001
Vue:        3 + Inertia 3 + TypeScript + Tailwind CSS 4 + Vite (IMP-001 foundation, reused as-is)
```

## Human Authorization

"Saya setuju. IMP-002 Implementation Authorized." — coding/migrations/models/services/
controllers/middleware authorized; tests mandatory; dependency installation authorized only
where the reviewed spec required it (TOTP library). Merge NOT authorized. Push NOT authorized.
RBAC implementation NOT authorized. Architecture/business-decision change NOT authorized.

## Scope Implemented

- User identity persistence with normalized, unique email (`app/Models/User.php`,
  `app/Services/Identity/EmailNormalizer.php`)
- Email + password authentication with enumeration-resistant, constant-time-safe failure
  handling, rate limiting, and session regeneration
  (`app/Services/Identity/AuthenticationService.php`,
  `app/Http/Controllers/Auth/AuthenticatedSessionController.php`)
- Self-registration (Donor/Fundraiser), identity-only, no business authority granted
  (`app/Services/Identity/RegistrationService.php`,
  `app/Http/Controllers/Auth/RegisteredUserController.php`)
- Email verification via Laravel's signed-URL `MustVerifyEmail` contract
  (`app/Http/Controllers/Auth/EmailVerificationController.php`)
- Request-versioned email-change workflow with durable `CONFLICTED` terminal state and
  EXPIRED-wins-over-CONFLICTED race handling (`app/Services/Identity/EmailChangeService.php`,
  `app/Models/EmailChangeRequest.php`)
- Password reset/change with full session/assurance invalidation
  (`app/Services/Identity/PasswordService.php`, `app/Services/Identity/SessionInvalidator.php`)
- Invitation-based provisioning for Partner Representative, Internal Administrative Identity,
  and Super Admin — issuer/revoker attribution only, no authority granted
  (`app/Services/Identity/InvitationService.php`, `app/Models/Invitation.php`)
- Configurable TOTP MFA with mandatory replay protection
  (`app/Services/Identity/MfaService.php`, `app/Models/MfaSecret.php`)
- Session-scoped Authentication Assurance (STANDARD/ELEVATED) with TTL and explicit invalidation
  triggers (`app/Services/Identity/AssuranceService.php`)
- Identity Lifecycle (ACTIVE/DISABLED) + Security Restriction (NONE/SUSPENDED), enforced both at
  login and mid-session (`app/Http/Middleware/EnsureIdentityIsActive.php`)
- First Super Admin one-time CLI bootstrap
  (`app/Console/Commands/BootstrapSuperAdmin.php`, `app/Models/SuperAdminBootstrap.php`)
- Minimum-necessary Vue/Inertia pages for every above flow (`resources/js/Pages/Auth/*.vue`,
  `resources/js/Pages/Dashboard.vue` — a neutral authenticated landing page, not a business
  dashboard)

### Explicitly Out of Scope (not touched)

Roles, permissions, RBAC assignment engine, data scope, business/financial authority,
Fundraiser/Partner/Beneficiary business approval, Donation/Payment/Ledger/Commission or any other
business domain. No `roles`, `permissions`, `role_user`, or `permission_user` table exists
(enforced by `tests/Feature/Identity/SchemaBoundaryTest.php`).

## Database

New migrations (`database/migrations/0001_02_01_0000{00..05}_*.php`):

- `users` — `public_id` (ULID, unique), `email` (unique), `email_verified_at`, `password`,
  `lifecycle_state`, `security_restriction`, `mfa_enabled`, `phone`/`phone_verified_at`
  (nullable, unused this pass), `last_login_at`. Deliberately excludes `failed_login_attempts`,
  `locked_until`, `role`/`role_id`, `permission`/`permission_id`, `actor_type`, `is_admin`,
  `is_super_admin`, `*_authority`, `beneficiary_approval`, `pending_email`, `remember_token`.
- `password_reset_tokens` — standard Laravel `PasswordBroker` shape.
- `email_change_requests` — `generation`, `normalized_pending_email`, `verification_token_hash`,
  `expires_at`, and independent terminal-state timestamps (`verified_at`, `superseded_at`,
  `cancelled_at`, `conflicted_at`) plus `conflict_reason_code`.
- `invitations` — `public_id`, `intended_email`, `token_hash`, `invited_actor`, `expires_at`,
  `accepted_at`/`revoked_at`, `issuer_user_id`/`revoker_user_id`/`accepted_user_id` (all nullable
  FKs to `users`, `nullOnDelete`) — attribution only, no authority column.
- `mfa_secrets` — `secret`/`pending_secret` (application-level `encrypted` cast, not hashed, since
  the plaintext must be recoverable to generate/verify codes), `pending_secret_expires_at`,
  `recovery_codes` (json), `accepted_steps` (json) — the replay-protection ledger.
- `super_admin_bootstraps` — `lock_key` (unique) is the durable, race-safe one-time guard;
  `user_id`, `bootstrapped_at`.

All 6 new migrations plus the 3 pre-existing IMP-001 migrations run cleanly, in order, against an
ephemeral in-memory SQLite database (`php artisan migrate:fresh` under the `testing` environment).

## TOTP Dependency Selection

Package: `pragmarx/google2fa` `v9.1.0` (installed; see `composer.lock`).

Evidence gathered before installation:
- `composer show pragmarx/google2fa --all` — MIT license, actively maintained, GitHub-hosted, no
  external network service dependency (pure local RFC 6238 computation).
- `composer require pragmarx/google2fa:^9.0 --dry-run --no-interaction` — resolved cleanly with a
  single transitive dependency, `paragonie/constant_time_encoding` v3.1.3; no conflicting
  constraints; no security advisories reported.
- Read `vendor/pragmarx/google2fa/src/Google2FA.php` directly and confirmed
  `verifyKeyNewer(string $secret, string $key, int $oldTimestamp, ?int $window = null, ?int $timestamp = null)`
  exists and is documented by its own docblock as: "Useful if you need to ensure that a single key
  cannot be used twice" — i.e. it natively satisfies the mandatory replay-protection contract
  (M04) without any custom TOTP cryptography.
- No `getQRCodeUrl()`-style helper exists on the core package (that belongs to a separate Laravel
  wrapper package not installed here); the `otpauth://` URI is therefore assembled manually as
  plain string formatting in `MfaService::startEnrollment()` — this is URI assembly, not
  cryptography, and does not reintroduce the "no custom TOTP crypto" prohibition.

No custom TOTP/HOTP cryptography was written anywhere in this implementation.

## Security Controls

- **Enumeration resistance**: login always executes a `Hash::check()` even when no user is
  found (against a throwaway hash), and returns an identical generic message for "wrong password"
  and "unknown email"; password-reset-link requests always return the same generic session status
  regardless of whether the email exists.
- **Rate limiting**: login (by normalized email + IP) and MFA challenge (by IP) are throttled via
  `RateLimiter`, with limits sourced from `config/identity.php` (env-overridable). No persistent
  `failed_login_attempts`/`locked_until` column exists anywhere — all throttling state lives in
  the `RateLimiter`/cache layer only.
- **Session security**: session ID is regenerated on every successful login, on password
  change/reset, and on a promoted email change; `logout()` invalidates the session and rotates the
  CSRF token; `SessionInvalidator` deletes all *other* DB-backed session rows (never the current
  one) on password change/reset and on email-change promotion.
- **Authentication Assurance**: STANDARD/ELEVATED is tracked only in the session
  (`identity.assurance.elevated_at`/`elevated_until`), never a `User` column, with a finite TTL
  (`config('identity.elevated_assurance_ttl_minutes')`) and explicit invalidation on logout,
  password change, and MFA disable/reset.
- **MFA replay protection (mandatory, M04)**: `MfaSecret.accepted_steps` records, per sensitive
  context (`enrollment_confirm`, `login`, `disable`, ...), the last TOTP time-step accepted for
  that (user, context) pair, using `verifyKeyNewer()`. A pre-existing bug was found and fixed
  during this pass (see "Errors Found and Fixed" below) where the very first acceptance for a
  context was stored as boolean `true` rather than the real time-step integer, which would have
  silently disabled replay protection after the first use. All read/verify/consume paths in
  `MfaService` run inside `DB::transaction()` with `lockForUpdate()` so concurrent submissions of
  the same code resolve to exactly one success.
- **Identity Lifecycle / Security Restriction**: `User::canAuthenticate()` is the single
  authority checked both at login (`AuthenticationService`) and mid-session
  (`EnsureIdentityIsActive` middleware, so a mid-session DISABLE/SUSPEND takes effect immediately,
  not only on next login). No persistent `LOCKED` state exists.
- **Email-change race handling**: see "Email Change Race Semantics" below.
- **Invitation uniqueness race**: `InvitationService::accept()` re-checks email uniqueness inside
  the locked transaction immediately before creating the identity; a self-registration that wins
  the race causes the invitation acceptance to fail cleanly rather than create a duplicate
  identity (covered by `InvitationTest::test_uniqueness_race_never_creates_duplicate_identity`).
- **Secrets**: MFA secrets (`secret`, `pending_secret`) use Eloquent's `encrypted` cast
  (`Crypt`-backed, reversible — required so the value can be re-verified against future codes);
  recovery codes and password-reset tokens are one-way hashed (`Hash::make`); nothing sensitive
  (token, password, TOTP secret, recovery code) is ever passed to `IdentityAuditLogger::record()`
  — enforced by convention and reviewed line-by-line across all call sites.

## Email Change Race Semantics

`EmailChangeService`:
- `requestChange()` supersedes all prior active requests for the user unconditionally before
  creating a new one with an incremented `generation`, so an old token can never promote after a
  newer request exists.
- `verify()` -> `attemptPromotion()` runs the eligibility check, uniqueness check, and promotion
  inside a single `DB::transaction()`; a uniqueness collision throws
  `EmailChangeConflictException` from inside the closure, forcing a full rollback of the promotion
  attempt (nothing partial is ever persisted from that attempt).
- The conflict is recorded as a **separate**, subsequent transaction
  (`finalizeConflict()`), which re-checks `EmailChangeRequest::isActive()` (which itself accounts
  for expiry) under a row lock before writing `conflicted_at`/`conflict_reason_code`. If the
  request has independently expired (or reached any other terminal state) by the time
  finalization runs, this is a silent no-op — **EXPIRED wins over CONFLICTED** by construction,
  not by an explicit priority check. Covered by
  `EmailChangeTest::test_expiry_wins_over_conflict_when_request_expires_before_finalization`.
- A conflicted request is a durable terminal state: it can never be retried
  (`EmailChangeTest::test_uniqueness_conflict_leaves_canonical_email_unchanged_and_becomes_conflicted`),
  but the user may always start a brand-new, independent request afterward.
- The `ConfirmEmailChange` notification is routed to the **new** (pending) email address only, via
  `Notification::route('mail', ...)` — not the user's default (old) `Notifiable` address. An
  earlier draft incorrectly relied on a custom `routes()` method on the notification class, which
  Laravel does not call automatically; this was caught and corrected before the test pass (see
  "Errors Found and Fixed").

## Tests

```
tests/Feature/Identity/SchemaBoundaryTest.php        6 tests  — forbidden columns/tables absent
tests/Feature/Identity/RegistrationTest.php          4 tests
tests/Feature/Identity/LoginTest.php                 7 tests
tests/Feature/Identity/EmailVerificationTest.php      3 tests
tests/Feature/Identity/PasswordTest.php               5 tests
tests/Feature/Identity/EmailChangeTest.php            6 tests
tests/Feature/Identity/InvitationTest.php             7 tests
tests/Feature/Identity/MfaTest.php                   10 tests
tests/Feature/Identity/AssuranceTest.php               5 tests
tests/Feature/Identity/BootstrapSuperAdminTest.php     4 tests
```

Result (final run, this pass):

```
php artisan test
{"tool":"phpunit","result":"passed","tests":56,"passed":56,"assertions":183,"duration_ms":4218}
```

## Commands Executed and Results

```
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --force   PASS (9 migrations)
composer dump-autoload                                                       PASS (6939 classes)
php artisan route:list                                                       PASS (33 routes)
php artisan test                                                             PASS (56/56, 183 assertions)
vendor/bin/pint --test                                                       PASS (after 1 auto-fix pass)
vendor/bin/pint                                                              fixed 8 files (see below)
npm run type-check (vue-tsc --noEmit)                                        PASS (no errors)
npm run build (vite build)                                                   PASS (584 modules, 2.47s)
git diff --check --cached                                                    PASS (no whitespace/conflict markers)
```

## Errors Found and Fixed During This Pass

All of the following were found by actually running the test suite (not claimed from static
reasoning alone) and fixed before this report was written:

1. **`config/auth.php` `AUTH_MODEL` was unset** — left blank pending IMP-002 by IMP-001, and never
   updated once `App\Models\User` was created. This broke every code path that used the
   `PasswordBroker`/`EloquentUserProvider` (password reset request/completion), since Laravel
   attempted `new ''`. Fixed by defaulting the provider's `model` to `App\Models\User::class`.
   Root-cause, not test-only: this would have broken password reset in production.
2. **`User::canAuthenticate()` read stale in-memory attributes** — `lifecycle_state`,
   `security_restriction`, and `mfa_enabled` are intentionally excluded from `$fillable` (to
   prevent mass-assignment of security-relevant state), which meant a freshly-`create()`d instance
   had these attributes entirely *unset* in memory (not merely defaulted) until a `fresh()`/reload
   from the database. Any code using such an instance immediately (e.g. `Auth::login($user)`
   right after registration, or test helpers using `actingAs()`) saw `null` instead of the actual
   DB default, which made `canAuthenticate()` return `false` incorrectly. Fixed by adding a
   `protected $attributes = [...]` default on `User` mirroring the migration column defaults —
   this is the standard Eloquent mechanism for exactly this problem and is production-relevant, not
   test-only.
3. **`MfaService::verifyWithReplayGuard()` replay-protection bug** — the very first TOTP
   acceptance for a given `(user, context)` pair passed `$oldTimestamp = null` into
   `Google2FA::verifyKeyNewer()`. Per the library's own implementation, when `$oldTimestamp` is
   `null` it returns bare `true` instead of the matched time-step integer. That `true` was then
   stored as the "last accepted" marker; on the *second* verification, PHP's `true + 1` coerces to
   `2`, so the replay window check (`$oldTimestamp + 1`) effectively evaluated against `2`
   rather than a real Unix time-step, and the exact same code verified successfully a second time
   — a real, mandatory-requirement-violating replay bug, found only by the dedicated replay test
   actually failing. Fixed by seeding the "no prior acceptance" sentinel as `0` instead of `null`,
   which forces the library to always return the real matched time-step integer.
4. **`EmailVerificationTest::test_valid_signed_link_verifies_email`** used `Event::fake()`
   (faking *all* events) before creating the test user, which also suppressed the `User` model's
   own `creating` listener that assigns `public_id`, causing a `NOT NULL` constraint violation.
   Test-only fix: narrowed to `Event::fake(Verified::class)`.
5. Three test-only correctness fixes (not production code): (a) `LoginTest`/`RegistrationTest`
   helpers used to set `lifecycle_state`/`security_restriction` overrides via `User::create()`,
   which silently drops non-fillable attributes — switched to `forceFill()->save()`, which is the
   correct way for first-party test/service code to set these deliberately-guarded fields; (b)
   `LoginTest`'s generic-message comparison originally called `->get('email')` on
   `session('errors')`, which — with this Laravel version's JSON session serialization — comes
   back as a plain nested array rather than a `ViewErrorBag` object; extracted the message via the
   array shape directly instead; (c) `MfaTest`'s enrollment-replay test manually wrote
   `pending_secret` via a raw query-builder `update()`, bypassing the model's `encrypted` cast and
   corrupting the stored ciphertext; switched to going through the Eloquent model so the cast
   applies.
6. **`BootstrapSuperAdmin` command dropped `email_verified_at`** — passed to `User::create()`,
   which silently drops it (not fillable), leaving the bootstrapped Super Admin's email
   unverified. Fixed with an explicit `forceFill(['email_verified_at' => now()])->save()` after
   creation. `BootstrapSuperAdminTest::test_repeat_bootstrap_is_refused` was also corrected: the
   command checks the one-time lock *before* prompting for any credentials (better UX/security —
   no point asking for a password that will be rejected), so the test was updated to no longer
   expect the email/password questions on the refused second run; it only asserts the exit code
   and that no second user/bootstrap row was created.
7. **PSR-4 / Pint style** — `vendor/bin/pint` (auto-fix mode) reformatted 8 files
   (import ordering, brace placement, minor spacing) with no behavioral changes; `pint --test`
   passes clean after.

## Known Limitations / Deferred Items

- The MFA enrollment QR code is presented to the user as the raw `otpauth://` URI / plain secret
  text, not a rendered QR image — no QR-image-rendering dependency (e.g. `bacon/bacon-qr-code`)
  was added, since it was not required by the reviewed spec and would have expanded the dependency
  footprint beyond what MFA correctness requires. Any compatible authenticator app can still be
  configured manually from the displayed secret/URI.
- True concurrent, multi-process/multi-session HTTP race conditions (e.g. two simultaneous email-
  change verifications, two simultaneous invitation acceptances) are tested via direct
  service-layer transaction logic and controlled DB row state, not genuine parallel HTTP requests
  — PHPUnit's default single-process test runner cannot reproduce true multi-connection DB
  concurrency. The transaction/locking design (`lockForUpdate()`, unique constraints as the final
  backstop) is the actual race-safety mechanism; the tests verify its *logic*, not true concurrent
  execution.
- Remember-me ("stay logged in") was explicitly deferred per the reviewed spec and is not
  implemented.
- SMS-based MFA is explicitly out of scope per Q23 (TOTP is the sole baseline factor).

## Architecture / RBAC / Shared-Hosting Regression

- **Architecture**: no new services, queues, subdomains, or infrastructure dependency introduced.
  Sessions/cache/queue remain database-backed per IMP-001 (no Redis, no Docker).
- **RBAC boundary**: `tests/Feature/Identity/SchemaBoundaryTest.php` and
  `BootstrapSuperAdminTest::test_bootstrap_introduces_no_role_or_permission_schema` assert no
  `roles`/`permissions`/`role_user`/`permission_user` table and no forbidden `users` column exists.
  IMP-002 grants no authority anywhere — invitation acceptance and Super Admin bootstrap create an
  Identity only.
- **Shared hosting**: no new runtime dependency requires anything beyond what shared PHP hosting
  already provides (`pragmarx/google2fa` is pure PHP, no extensions beyond what Laravel already
  requires). No subdomain, no long-running process, no queue worker requirement was added (the
  `ShouldQueue` email-change notification runs on the existing IMP-001 database queue).

## Human Decisions

No new Human Decision was introduced or required. Q21–Q25 were implemented exactly as locked; no
ambiguity was encountered that required deviating from or reinterpreting any of them.

## Git

```
Branch:  impl/002-identity-authentication (unchanged from baseline; master untouched)
Merge:   NOT performed (not authorized)
Push:    NOT performed (not authorized)
```

68 files changed (58 new, 6 modified production/config files, 2 modified dependency lockfiles,
2 new+existing test-adjacent scaffolding). Full list in the commit itself.

## Recommendation

Implementation is functionally complete against the reviewed IMP-002 specification, all
mandatory tests pass, and all requested quality gates (Pint, TypeScript, Vite build,
`git diff --check`) pass clean. This report and the corresponding commit represent an
implementation pass, not a final human sign-off.

## Stage Gate

IMP-002: NOT FINAL, NOT MERGED. Awaiting human review and explicit merge authorization.
