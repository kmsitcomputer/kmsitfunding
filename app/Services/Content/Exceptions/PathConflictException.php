<?php

namespace App\Services\Content\Exceptions;

/**
 * A path claim INSERT collided with UNIQUE(active_path) or
 * UNIQUE(active_current_owner) at the storage layer — the race authority
 * itself, not a pre-check (docs/implementation/IMP-005-cms.md section 14
 * "Availability is decided by the constraint, not by the pre-check"). The
 * whole publish transaction rolls back with it; on a rename this restores
 * the old CURRENT claim automatically (InnoDB's own undo, no compensating
 * write). 409 path_conflict.
 */
class PathConflictException extends \RuntimeException {}
