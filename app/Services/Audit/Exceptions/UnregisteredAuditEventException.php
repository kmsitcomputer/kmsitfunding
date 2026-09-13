<?php

namespace App\Services\Audit\Exceptions;

/**
 * An unregistered (event_type, event_version) pair was presented to the
 * canonical AuditWriter. Unregistered canonical events are REJECTED — a hard
 * error at write time, never silently accepted (Q26 default fail-closed).
 */
class UnregisteredAuditEventException extends \RuntimeException {}
