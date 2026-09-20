<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * IMP-009 remediation §28 — test-harness hardening proof: the standard
 * `php vendor/bin/phpunit` invocation resolves the intended default
 * test environment even when the parent shell exports development DB
 * settings (DB_CONNECTION=mysql / DB_DATABASE=kmsitdonation).
 * phpunit.xml forces these values; PHPUnit must never silently
 * inherit the development database.
 */
class TestHarnessEnvironmentTest extends TestCase
{
    public function test_phpunit_resolves_the_intended_sqlite_memory_default(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
