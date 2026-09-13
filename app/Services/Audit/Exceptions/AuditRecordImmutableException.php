<?php

namespace App\Services\Audit\Exceptions;

/**
 * An ordinary application path attempted to UPDATE or DELETE a persisted
 * canonical audit record. Audit records are append-only operational evidence
 * (Q28): corrections never rewrite history, and the only future deletion path
 * is a separately-authorized governed retention purge (not built by IMP-004).
 */
class AuditRecordImmutableException extends \RuntimeException {}
