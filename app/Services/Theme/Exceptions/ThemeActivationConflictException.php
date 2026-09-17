<?php

namespace App\Services\Theme\Exceptions;

/**
 * Optimistic/pessimistic activation conflict — a concurrent activation
 * already changed the singleton pointer, or the candidate theme failed
 * validated-activation (docs/implementation/IMP-006-theme-engine.md
 * section 8/24). Mirrors HomepageAssignmentConflictException's role.
 */
class ThemeActivationConflictException extends \RuntimeException {}
