<?php

/**
 * IMP-009 remediation §28 — test-only bootstrap hardening.
 *
 * The developer shell exports real environment variables
 * (APP_ENV=local, DB_CONNECTION=mysql, DB_DATABASE=kmsitdonation).
 * PHPUnit's `<env force="true">` applies via putenv(), which does NOT
 * update PHP's $_SERVER superglobal — and Laravel's env() reads
 * $_SERVER first. Without this file, the suite silently inherits the
 * DEVELOPMENT database (empirically observed: config resolved to
 * APP_ENV=local / mysql / kmsitdonation inside PHPUnit).
 *
 * This bootstrap hard-forces the intended default test environment
 * (testing / sqlite / :memory:) across $_SERVER, $_ENV and putenv
 * BEFORE the application boots. Test-only: production/application DB
 * configuration is untouched. Explicit MySQL tests keep using their
 * own disposable-database override mechanisms.
 */
foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
] as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

require dirname(__DIR__).'/vendor/autoload.php';
