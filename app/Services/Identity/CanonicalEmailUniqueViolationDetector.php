<?php

namespace App\Services\Identity;

use Illuminate\Database\QueryException;

/**
 * IMP002-IMPL-M01 — driver-aware classifier that distinguishes the exact
 * canonical `users.email` unique-constraint violation from every other
 * `QueryException` (deadlocks, foreign-key failures, unrelated unique
 * violations, connection errors, syntax errors). Unrelated exceptions must
 * propagate unchanged — this class never widens a real DB failure into a
 * false "email already in use" outcome.
 *
 * Driver support is intentionally limited to what this project actually
 * runs: MySQL 8.x in production, SQLite for testing (see
 * docs/audits/IMP-002-IMPLEMENTATION-REMEDIATION-1.md, "M01" and "MySQL").
 */
class CanonicalEmailUniqueViolationDetector
{
    public function isCanonicalEmailUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $driverMessage = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        // MySQL: SQLSTATE 23000, driver error 1062 ("Duplicate entry ... for
        // key 'users_email_unique'" or equivalent index name).
        if ($sqlState === '23000' && (int) $driverCode === 1062) {
            return $this->mentionsUsersEmailIndex($driverMessage);
        }

        // SQLite: SQLSTATE 23000, driver error 19 ("UNIQUE constraint failed:
        // users.email").
        if ($sqlState === '23000' && (int) $driverCode === 19) {
            return $this->mentionsUsersEmailIndex($driverMessage);
        }

        return false;
    }

    private function mentionsUsersEmailIndex(string $message): bool
    {
        return str_contains($message, 'users_email_unique')
            || str_contains($message, 'users.email');
    }
}
