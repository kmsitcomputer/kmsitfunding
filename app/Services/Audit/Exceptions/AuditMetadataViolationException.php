<?php

namespace App\Services\Audit\Exceptions;

/**
 * Metadata presented to the AuditWriter violated the event's registry-declared
 * allow-list contract: an unknown key, a non-scalar/object value, a hard-prohibited
 * secret category, or an oversized payload. Allow-list violations RAISE — they are
 * never silently dropped (IMP-004 "Redaction / Safe Serialization").
 */
class AuditMetadataViolationException extends \RuntimeException {}
