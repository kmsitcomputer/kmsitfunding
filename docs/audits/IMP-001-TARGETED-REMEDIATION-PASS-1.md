# IMP-001 Targeted Remediation Pass 1

Task:
IMP-001 Targeted Remediation Pass 1

Branch:
impl/001-core-foundation (verified via `git branch --show-current` before any change)

## Codex Finding Addressed

```
Premature Identity / Authentication implementation
```

Codex's independent implementation review found that Implementation Pass 1 carried the Laravel
skeleton's default Identity/Authentication scaffolding (`users` table, `password_reset_tokens`
table, `App\Models\User`, `UserFactory`, and a seeded credential-bearing default user) into
IMP-001, even though Identity/Authentication is explicitly deferred to IMP-002 (see
[docs/implementation/IMP-001-core-project-foundation.md](../implementation/IMP-001-core-project-foundation.md)
§23 "AUTHENTICATION IS OUT OF SCOPE"). The seeded user additionally carried a known default
password (`'password'`, from the stock `UserFactory`), which is a security finding in its own
right.

## Files Changed

```
D  app/Models/User.php
D  database/factories/UserFactory.php
D  database/migrations/0001_01_01_000000_create_users_table.php
A  database/migrations/0001_01_01_000000_create_sessions_table.php
M  database/seeders/DatabaseSeeder.php
M  config/auth.php
```

No other file was touched.

## What Changed and Why

- **`app/Models/User.php` removed.** It existed only as stock Laravel Identity/Auth scaffolding,
  not because IMP-001 needs an Identity model. Identity modeling is deferred to IMP-002; no
  replacement User/Account/Principal model was introduced.
- **`database/factories/UserFactory.php` removed.** It existed only to construct instances of
  the now-removed `User` model, and was the source of the known default password
  (`Hash::make('password')`). No alternative identity factory was created.
- **`database/migrations/0001_01_01_000000_create_users_table.php` removed, replaced by
  `database/migrations/0001_01_01_000000_create_sessions_table.php`** (same timestamp prefix, so
  migration ordering/history is unaffected). The original file created three tables in one
  migration (`users`, `password_reset_tokens`, `sessions`); only the `sessions` table creation
  was carried over verbatim (including its unconstrained, nullable `user_id` column — it was
  never a real foreign key to begin with, so `sessions` remains fully usable with no `users`
  table present). The `users` and `password_reset_tokens` `Schema::create()` calls were dropped.
  No new/speculative identity column was added anywhere.
- **`database/seeders/DatabaseSeeder.php` patched to a neutral empty seeder** (`run(): void { //
  }`), removing the `User::factory()->create([...])` call that seeded a credential-bearing
  `test@example.com` / `password` user, and the now-unused `use App\Models\User;` /
  `WithoutModelEvents` imports.
- **`config/auth.php` patched**, not removed. Its `use App\Models\User;` import and the
  `'model' => env('AUTH_MODEL', User::class)` hardcoded default were the only project-specific
  references coupling the framework's dormant auth configuration to a model that no longer
  exists. Changed to `'model' => env('AUTH_MODEL')` (no hardcoded class-string default) with a
  comment explaining why. Everything else in the file — the `guards`/`providers`/`passwords`
  array *structure*, the `'passwords.users.table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE',
  'password_reset_tokens')` default — was left untouched: this is neutral framework capability
  (Laravel ships this file for every application, whether or not auth is implemented yet), it is
  never invoked by any route/controller in this repository (`grep` confirms no
  `Auth::`/`Password::`/guard usage anywhere in `app/`, `routes/`), and removing it would not
  reduce any actual coupling — it would just be redesigning IMP-002's future config today, which
  is explicitly out of scope for this remediation.

## Database — Final Migration Set

```
0001_01_01_000000_create_sessions_table.php   sessions
0001_01_01_000001_create_cache_table.php      cache, cache_locks
0001_01_01_000002_create_jobs_table.php       jobs, job_batches, failed_jobs
```

Verified by running `php artisan migrate:fresh --force` against an ephemeral in-memory SQLite
connection (`DB_CONNECTION=sqlite DB_DATABASE=:memory:`, passed as one-off environment overrides
for this single verification command only — `.env` itself still targets `mysql` per the locked
baseline, and no file was changed to do this check): exactly three migrations ran, creating
exactly `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. No
`users` or `password_reset_tokens` table was created. No business table
(donation/payment/ledger/campaign/program/fund/commission/withdrawal/refund/partner/fundraiser/
beneficiary/distribution) exists — confirmed both by this migration run and by
`grep -rniE "donation|payment|ledger|campaign|program|fund|commission|withdrawal|refund|partner|
fundraiser|beneficiary|distribution" app/ database/ routes/ resources/js/ config/`, which returned
no matches.

## Identity / Authentication — Final State

```
Identity Schema Implemented:   NO
Authentication Implemented:    NO
RBAC Implemented:              NO

users table:                   NO
password_reset_tokens table:   NO

User model:                    NO
User factory:                  NO
default User seeder:           NO
```

`config/auth.php` remains present as inert, unused framework capability (no guard, provider, or
password broker is invoked anywhere in application code) — this is the "framework authentication
capability != implemented project authentication" distinction: the file's *structure* is
Laravel's standard scaffolding for every application, not a sign that this project has
implemented Identity/Auth.

## Security

```
Known default password:          NONE (Hash::make('password') fixture removed with UserFactory)
Real secrets committed:          NONE (repo-wide grep for AWS/PEM/Stripe-style key patterns and
                                  hardcoded password-like literals found nothing outside
                                  gitignored, generated bootstrap/cache/services.php, which only
                                  contains a service-provider class-name map, not a credential)
.env tracked:                    NO (git check-ignore confirms .env remains ignored)
Production debug can be false:   YES (unchanged from Implementation Pass 1)
Private storage remains private: YES (unchanged from Implementation Pass 1 — no storage-related
                                  file was touched)
```

## Regression Tests (Actual Commands, Actual Results)

```
php artisan about (boot check)     PASS — application boots; no "Class App\Models\User not
                                    found" or equivalent error; Database driver shown as mysql
                                    (config baseline unchanged)
php artisan test                   PASS (3 tests, 11 assertions — unchanged count and content
                                    from Implementation Pass 1; FoundationSmokeTest does not use
                                    User/authentication)
vendor/bin/pint --test             PASS (no fixes needed)
npm run type-check (vue-tsc)        PASS (no output = no errors)
npm run build (vite build)          PASS (564 modules; identical asset shape to Implementation
                                    Pass 1 — Foundation page, app bundle, CSS)
git diff --check                    PASS
```

## Previously Passed Areas — Unchanged

```
Laravel 13.31.0:            UNCHANGED
Vue 3 / Inertia 3:          UNCHANGED
TypeScript 5.9.3:           UNCHANGED (not touched, not revisited)
MySQL live verification:    DEFERRED NON-BLOCKING (unchanged; no MySQL server was started or
                             required for this remediation)
/api/v1 foundation:         DEFERRED NON-BLOCKING (unchanged; no placeholder route was added)
Shared hosting compatibility: PASS (unchanged — no Docker/Redis/Supervisor/PM2/Node-runtime/
                             WebSocket/separate-API-server requirement was introduced)
```

## Architecture

```
Locked architecture changed:                NO
Level 1-3 authoritative documents changed:   NO (docs/03-database/, docs/04-security/,
                                             docs/05-rbac/ were re-read for constraints; none
                                             were edited)
New Human Decision introduced:               NO
```

## Result

```
IMP-001 Implementation:   COMPLETE — PENDING TARGETED INDEPENDENT RE-AUDIT
IMP-001 Final:            NO
Merge to master:          NOT AUTHORIZED
```
