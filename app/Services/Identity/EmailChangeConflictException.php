<?php

namespace App\Services\Identity;

/**
 * Internal control-flow signal used to force a promotion transaction to roll
 * back completely on a target-email uniqueness collision (P2-m01) — never
 * exposed to the caller as a public error; the outward result is the generic
 * "conflicted"/"invalid" status from EmailChangeService::verify().
 */
class EmailChangeConflictException extends \RuntimeException {}
