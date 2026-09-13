<?php

namespace App\Services\Audit\Exceptions;

/**
 * IMP004-IMPL-M01: raised when a DENIAL_DURABLE-capable method is invoked
 * while an ambient transaction is already open on its connection — a
 * calling-contract violation, never an authorization outcome. This is
 * distinct in kind from an authorization denial and MUST NOT be persisted
 * as (or confused with) a `security.authorization.denied` audit event.
 *
 * Test code exercising this path must not rely on an ambient transaction
 * being present at test-body start (e.g. Illuminate\Foundation\Testing\
 * RefreshDatabase's per-test wrapper) — use
 * Illuminate\Foundation\Testing\DatabaseTruncation for those test classes
 * instead, so `DB::connection()->transactionLevel()` genuinely starts at 0.
 */
class TransactionOwnershipViolationException extends \RuntimeException {}
