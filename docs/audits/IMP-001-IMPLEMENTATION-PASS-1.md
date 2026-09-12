# IMP-001 Implementation Pass 1

Task:
IMP-001 Implementation Pass 1

## Branch

```
Branch:            impl/001-core-foundation
Starting Commit:   83c0c4f (docs(IMP-001): materialize remaining B01 authoritative baseline (Pass 2))
```

Verified via `git status`, `git branch --show-current`, `git branch -vv`,
`git log --oneline --decorate --graph -20` before any modification (see IMP-001
IMPLEMENTATION PASS 1 final report for the exact output). `master` was not touched.

## Environment

```
PHP:        8.3.33 (C:\ServBay\packages\php\8.3\php.exe)
Composer:   2.10.3
Laravel:    13.31.0
Node:       v23.11.1
npm:        10.9.2
Vue:        3.5.42
Inertia:    @inertiajs/vue3 3.7.1, inertiajs/inertia-laravel 3.3.4
TypeScript: 5.9.3
Tailwind:   4.3.3
Vite:       8.3.0
```

## Framework Compatibility Gate

`composer show laravel/framework --all` against packagist.org confirmed `laravel/framework`
requires `php: ^8.3`; the verified PHP 8.3.33 satisfies that constraint. Laravel 13.31.0 (the
latest 13.x release at the time of this pass) installed cleanly. No downgrade to Laravel 12/11
was needed or performed.

## Bootstrap Method

`composer create-project laravel/laravel` was run in an isolated scratch directory (outside the
repository), NOT against the repository root, to avoid any risk of a destructive/interactive
installer step touching `.git/`, `docs/`, `AGENTS.md`, `README.md`, `CHANGELOG.md`,
`CONTRIBUTING.md`, or `.gitignore`. Inertia (server + client), Vue, TypeScript, and Tailwind
tooling were added there, then only the Laravel/frontend framework files were copied into the
repository root — explicitly excluding the skeleton's own `README.md`, `AGENTS.md`, and
`CLAUDE.md` (which would have overwritten or conflicted with this repository's governance
documents of the same name) and excluding `vendor/`, `node_modules/`, `.env`, and the generated
`public/build/` output (regenerated in place afterward). `composer install` and `npm ci` were
then run directly in the repository root from the copied lockfiles to verify installation is
reproducible from the lockfiles themselves, not just a copy of a prior `vendor/`/`node_modules/`.

## Foundation Implemented

```
Laravel 13.31.0                          YES
Vue 3 + Inertia 3 (server + client)       YES
TypeScript                                YES
Tailwind CSS 4                            YES (ships with the Laravel 13 skeleton by default)
Vite 8                                    YES
MySQL configuration baseline              YES (.env.example: DB_CONNECTION=mysql)
Queue foundation (database-backed)        YES (QUEUE_CONNECTION=database, jobs/job_batches/
                                               failed_jobs migration present)
Cache foundation (database-backed)        YES (CACHE_STORE=database, cache table migration
                                               present)
Session foundation (database-backed)      YES (SESSION_DRIVER=database, sessions table is part
                                               of the framework's default users migration)
Scheduler foundation                      YES (Laravel Scheduler is framework-standard; invoked
                                               via `* * * * * php artisan schedule:run` cron
                                               entry — no scheduled tasks were added)
Storage foundation                        YES (see "Storage Boundary" below)
Logging/error foundation                  YES (framework default; APP_DEBUG defaults to false
                                               when unset, true only in local .env.example)
Testing foundation                        YES (PHPUnit + a named foundation smoke test)
```

## What Was Added, Concretely

- `bootstrap/app.php`: registered `HandleInertiaRequests` in the `web` middleware group. No
  other middleware/auth/RBAC wiring.
- `routes/web.php`: single `GET /` route rendering the Inertia `Foundation` page with one prop
  (`appName`). No other routes were added; no `/api/v1` scaffolding was added because the
  specification treats it as optional/deferred and no security-reviewed contract exists yet to
  version — adding an empty versioned placeholder was judged more likely to invite premature
  business-route additions than to help, so it was left for a later stage to introduce
  alongside an actual endpoint.
- `resources/js/app.ts`, `resources/js/Pages/Foundation.vue`, `resources/views/app.blade.php`:
  minimal Inertia bootstrap and a neutral foundation page ("Foundation stage — no business
  features implemented yet."). No CMS/theme/portal UI.
- `vite.config.ts`, `tsconfig.json`: TypeScript + Vue + Tailwind4 + Laravel Vite plugin wiring.
  The skeleton's optional `bunny()` webfont plugin (which fetches a font from an external CDN at
  build time) was deliberately not used, to keep the production build free of an external
  network dependency at this foundation stage; `app.css`'s `--font-sans` was adjusted to a
  system font stack accordingly.
- `.env.example`: `APP_NAME` set to the project name; `DB_CONNECTION` switched from the
  skeleton's default `sqlite` to `mysql` with placeholder host/port/database/username and an
  empty password, per the locked MySQL 8.x target. No other defaults were changed — queue,
  cache, and session were already `database` by default in the Laravel 13 skeleton.
- `.gitignore`: merged the Laravel skeleton's ignore rules into the existing repository
  `.gitignore` (editor directories, `*.log`, `_ide_helper.php`, `/auth.json`, the sqlite
  database file, etc.) rather than replacing the file.
- `tests/Feature/ExampleTest.php` replaced with `tests/Feature/FoundationSmokeTest.php` (see
  "Tests" below); `tests/Unit/ExampleTest.php` (framework default, asserts `true === true`) was
  left as-is.

## Infrastructure Migrations Added

```
0001_01_01_000000_create_users_table.php   (framework default: users, password_reset_tokens,
                                             sessions — unmodified skeleton output)
0001_01_01_000001_create_cache_table.php   (cache, cache_locks)
0001_01_01_000002_create_jobs_table.php    (jobs, job_batches, failed_jobs)
```

Business Migrations Added: **NO**. No donation/payment/ledger/fund/campaign/commission/
withdrawal/refund/partner/beneficiary/distribution table was created. The `users` table is the
framework's own unavoidable placeholder (needed for `HasFactory`/`Notifiable` on the stock
`App\Models\User`, and for the bundled `sessions` table) — it is not wired to any
authentication route, controller, or business logic. `grep -rniE
"donation|payment|ledger|commission|refund|withdrawal|beneficiary" app/ database/ routes/
resources/js/ config/` returned no matches.

## Scope Verification

```
Authentication implemented (routes/controllers/UI):  NO
RBAC implemented:                                     NO
CMS implemented:                                      NO
Theme Engine implemented:                             NO
Campaign / Donation / Payment / Ledger / Commission /
Withdrawal / Refund / Reconciliation / Moota /
Partner / Philanthropy Products / Beneficiary /
Distribution / Business Notification implemented:     NO (none present anywhere in app/,
                                                       database/, routes/, resources/js/)
```

`php artisan route:list` shows exactly: `GET|HEAD /` (the foundation page), two Inertia DevTools
routes (framework-bundled, local-environment-only debugging endpoints, not application code),
`GET|HEAD/PUT storage/{path}` (framework's built-in local-disk serve/upload route — see "Storage
Boundary" below), and `GET|HEAD up` (framework health check). No auth, RBAC, or business route
exists.

## Storage Boundary

The Laravel 13 skeleton's default `local` disk (root `storage/app/private`) has `'serve' =>
true`, which registers a framework route at `/storage/{path}` (this is new default framework
behavior, not something added by this implementation). Reading
`vendor/laravel/framework/.../ServeFile.php` confirms that for any disk not configured with
`'visibility' => 'public'` (the `local` disk has no such key, so it defaults to `'private'`),
access requires `$request->hasValidRelativeSignature()` — i.e. a cryptographically signed,
non-guessable URL — not a bare permanent public path. The `public` disk (`storage/app/public`,
`visibility: public`) is the only disk intended for public assets, and `php artisan
storage:link` was deliberately NOT run, since no public content exists yet — so
`public/storage` does not exist and nothing is exposed. This satisfies "sensitive/private files
must NOT rely on permanent public URLs."

## Tests

`tests/Feature/FoundationSmokeTest.php` (new, replaces the framework's default
`ExampleTest.php`):

- `test_root_route_renders_foundation_page`: asserts `GET /` returns 200 and is an Inertia
  response rendering the `Foundation` component.
- `test_health_endpoint_responds_successfully`: asserts `GET /up` returns 200 and does not leak
  `config('app.key')` in the response body.

`tests/Unit/ExampleTest.php` (framework default, unmodified) also runs. No business-logic test
was written, per scope.

## Checks Executed (Actual Commands, Actual Results)

```
composer validate --no-check-publish                 PASS ("./composer.json is valid")
composer install (from lockfile, repo root)           PASS (reproducible; no updates needed)
npm ci (from lockfile, repo root)                     PASS (89 packages)
npm run build (vite build)                            PASS (564 modules, manifest.json + 3
                                                            asset files emitted)
npm run type-check (vue-tsc --noEmit)                 PASS (no output = no type errors)
vendor/bin/pint --test                                PASS (after one auto-fix to
                                                            bootstrap/app.php import ordering,
                                                            re-verified clean)
php artisan test                                      PASS (3 tests, 11 assertions)
```

### Dependency/Environment Incident Found and Resolved

`npm install typescript` initially resolved to `typescript@7.0.2` (the current npm `latest` tag
at time of install). `vue-tsc@3.3.11` failed against it with
`ERR_PACKAGE_PATH_NOT_EXPORTED: Package subpath './lib/tsc' is not defined by "exports"` — a
real, verified incompatibility between the latest `vue-tsc` and the newest TypeScript major, not
an assumption. Resolved by pinning `typescript` to `^5` (resolved: 5.9.3), which `vue-tsc`
supports; `npm run type-check` then passed cleanly, and `npm run build` was re-verified
afterward. This is a tooling/version resolution, not an architecture change — TypeScript itself
remains enabled per the specification, no specific major version was locked by IMP-001.

## Shared-Hosting Verification

```
Requires Docker:                 NO
Requires Redis:                  NO (queue/cache/session all default to `database`)
Requires Supervisor:              NO
Requires PM2:                     NO
Requires Node in production:      NO (Vite build output is static assets served by PHP/Apache;
                                  `npm run build` is a development/CI-time step only)
Requires WebSockets:               NO
Requires a separate API server:    NO (no `/api/v1` scaffolding was added in this pass)
Cron-compatible scheduler:          YES (`php artisan schedule:run` via a one-line cron entry;
                                    no scheduler daemon)
```

## Security

```
Real secrets committed:            NO (grepped tracked file types for AWS/PEM/Stripe-style key
                                    patterns; none found; .env itself is git-ignored and was not
                                    inspected for secrets beyond confirming it's untracked)
.env tracked:                      NO (git check-ignore confirms `.env` is ignored;
                                    `.env.example` contains no real values)
APP_DEBUG can be false:            YES (config/app.php defaults to `false` when APP_DEBUG is
                                    unset; .env.example sets `true` for local development only)
Sensitive storage private:         YES (see "Storage Boundary" above)
phpinfo/debug endpoints exposed:   NO
Authorization bypass added:        NO (no authorization code was added at all)
```

## Architecture

```
Locked architecture changed:              NO
New Human Decision introduced:            NO
Financial Posting Boundary changed:       NO
Authoritative Level 1-3 documents edited: NO
```

## Known Deferred Items

- `/api/v1` routing/versioning foundation was not added — judged safer to defer to the stage
  that actually needs it (so it ships with a real, reviewed contract) than to add an empty
  placeholder now. This is a judgment call within IMP-001's own "may establish only minimal
  routing/foundation capability if the specification requires it" language, not a scope
  reduction of a mandatory item.
- No MySQL server was available in this environment to run `php artisan migrate` against a real
  MySQL database; the configuration baseline (AC-008) was verified structurally
  (`.env.example`, `config/database.php` defaults) rather than against a live connection. PHP
  tests run against SQLite in-memory via `phpunit.xml`'s environment overrides, which is
  standard Laravel testing practice and does not depend on the production DB_CONNECTION value.
- Frontend lint (ESLint/Prettier) was not configured — the Laravel 13 + Tailwind 4 skeleton does
  not include it by default, and IMP-001 §24 does not mandate a specific linter, only "evaluate/
  setup where appropriate." Pint (PHP) and vue-tsc (TypeScript) cover the two languages actually
  used; a JS/Vue linter can be added by a later stage if needed.
