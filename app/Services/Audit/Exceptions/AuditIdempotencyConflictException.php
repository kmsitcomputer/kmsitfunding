<?php

namespace App\Services\Audit\Exceptions;

/**
 * The same scoped key (source_domain, source_event_id, event_type) was reused
 * with an incompatible immutable payload/core attribution — an idempotency
 * conflict, rejected loudly. The original row is never silently overwritten
 * (audit rows are immutable).
 */
class AuditIdempotencyConflictException extends \RuntimeException {}
