<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Invalidates a User's OTHER active sessions by deleting their rows from
 * IMP-001's existing database-backed `sessions` table — reused, not replaced
 * or duplicated (see "Session Model").
 */
class SessionInvalidator
{
    public function invalidateAllExcept(User $user, ?string $currentSessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }

    public function invalidateAll(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
