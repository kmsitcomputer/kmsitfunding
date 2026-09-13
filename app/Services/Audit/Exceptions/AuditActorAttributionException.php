<?php

namespace App\Services\Audit\Exceptions;

/**
 * Actor attribution presented to the AuditWriter violated the event's registry
 * contract: a missing Principal where the entry requires one, a Principal where
 * the entry declares a pre-principal-only attribution, or an unresolved/unpersisted
 * Principal. Deterministic execution-context attribution can never be bypassed
 * into an ordinary NULL (IMP004-SPEC-M02).
 */
class AuditActorAttributionException extends \RuntimeException {}
