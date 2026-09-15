<?php

namespace App\Services\Content\Exceptions;

/**
 * An owner (Page/Article) already has an active DRAFT revision — section 11
 * "Exactly ONE active DRAFT revision per owner at a time". The DB's
 * active_draft_page_id/active_draft_article_id unique indexes are the
 * authority; this exception is the classified 409 a service check produces
 * BEFORE hitting that index, per section 11's own "diagnostic layer vs
 * authority" distinction.
 */
class ActiveDraftExistsException extends \RuntimeException {}
