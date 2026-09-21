<?php

namespace App\Models;

use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CR-001-B (Schema #12, HD-CR001-03) — the single-row configurable minimum
 * effective-date lead time for Zakat/Fidyah policy versions
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 63), mirroring App\Models\Theme\ThemeActivation's singleton
 * pattern exactly (fixed id=1, DB-level CHECK constraint). Standalone at
 * `app/Models/` — cross-cutting policy governance, not scoped to Zakat or
 * Fidyah alone. `lead_time_days` is seeded NULL by its migration; this
 * class never assumes or hard-codes a duration.
 */
class PolicyLeadTimeConfig extends Model
{
    protected $table = 'policy_lead_time_configs';

    public $incrementing = false;

    const CREATED_AT = null;

    protected $guarded = ['id'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'updated_by_principal_id');
    }

    public static function current(): self
    {
        return static::query()->findOrFail(1);
    }

    /**
     * CODEX-CR001B-02 remediation: UNCONFIGURED (NULL) must fail closed —
     * it must never be silently read as "0 days" / "immediate activation".
     * Callers that need to gate a policy activation on the lead time (see
     * App\Services\Zakat\ZakatPolicyVersioningService /
     * App\Services\Fidyah\FidyahPolicyVersioningService) call this instead
     * of reading `lead_time_days` directly.
     */
    public function requiredLeadTimeDays(): int
    {
        if ($this->lead_time_days === null) {
            throw new \LogicException(
                'Policy lead time is not configured; policy activation is not permitted until an '.
                'authorized actor sets a lead time (fail-closed per HD-CR001-03).'
            );
        }

        return $this->lead_time_days;
    }
}
