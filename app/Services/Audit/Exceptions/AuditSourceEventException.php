<?php

namespace App\Services\Audit\Exceptions;

/**
 * Source-event idempotency contract violation: a partial pair
 * (source_event_id present with source_domain NULL), a source_domain the
 * registry does not know / the event's entry does not declare, or a source
 * identifier supplied for an event with no idempotency policy. Rejected BEFORE
 * any insert is attempted — never left to DB NULL-uniqueness semantics
 * (IMP004-REAUDIT-m01).
 */
class AuditSourceEventException extends \RuntimeException {}
