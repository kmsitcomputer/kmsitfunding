<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\Log;

/**
 * RBAC/Scope/Business-Authority audit emission points (see "Audit Contract").
 * Same deferred-persistence pattern as
 * App\Services\Identity\IdentityAuditLogger: the canonical Audit domain
 * (IMP-004, MODULE-OWNERSHIP.md §16) does not exist yet, so this records to
 * a dedicated log channel now — a later stage can replace the sink without
 * changing any call site.
 *
 * MUST NEVER be called with a credential, token, or raw exception detail in
 * $context.
 */
class RbacAuditLogger
{
    public function record(string $event, array $context = []): void
    {
        Log::channel('rbac_audit')->info($event, $context);
    }
}
