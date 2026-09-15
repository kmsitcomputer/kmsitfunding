<?php

namespace App\Services\Content\Exceptions;

/**
 * A homepage assignment/replacement/clear request's `expectedPageId` did not
 * match the current designee, re-checked under the singleton row's lock
 * (docs/implementation/IMP-005-cms.md section 26 flow 7). 409
 * homepage_assignment_conflict — never an automatic retry (silently
 * winning a replacement race is a destructive surprise, section 13).
 */
class HomepageAssignmentConflictException extends \RuntimeException {}
