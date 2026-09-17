<?php

namespace App\Services\Content\Exceptions;

/**
 * Optimistic concurrency failure on RevisionService::editDraft() — the
 * caller's `expected_edit_version` no longer matches the locked row's
 * current `edit_version` (docs/implementation/IMP-005-cms.md section 19
 * step 5 / section 26 flow 3). 409 draft_edit_conflict: the loser's
 * payload is discarded, never silently merged; the editor reloads.
 */
class DraftEditConflictException extends \RuntimeException {}
