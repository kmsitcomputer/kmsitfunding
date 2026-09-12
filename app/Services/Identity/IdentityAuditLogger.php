<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Identity/Authentication audit emission points (see "Audit Events" in
 * docs/implementation/IMP-002-identity-authentication.md).
 *
 * The canonical Audit domain (owned by Governance & Platform Services,
 * MODULE-OWNERSHIP.md §16) does not exist yet — no stage has built its
 * storage/query mechanism. This class is the approved, deferred-persistence
 * emission abstraction IMP-002 is responsible for: it records every audited
 * event to a dedicated log channel now, so a later stage can replace the
 * sink (e.g. a database-backed Audit log) without changing any call site.
 *
 * MUST NEVER be called with a verification token, password, TOTP secret,
 * recovery code, or raw database/exception detail in $context.
 */
class IdentityAuditLogger
{
    public function record(string $event, ?User $user, array $context = []): void
    {
        Log::channel('identity_audit')->info($event, array_merge([
            'user_id' => $user?->id,
            'user_public_id' => $user?->public_id,
        ], $context));
    }
}
