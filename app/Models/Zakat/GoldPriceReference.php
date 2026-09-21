<?php

namespace App\Models\Zakat;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CR-001-B (Schema #8) — dated, admin-entered gold price snapshot
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 20/55): "admin-entered historical snapshots, never a live float
 * API trust". `amount_minor` follows canonical money discipline (Section
 * 64) — integer minor units, never FLOAT/DOUBLE. No `updated_at` — a new
 * price is always a new row, never a mutation of a historical one.
 */
class GoldPriceReference extends Model
{
    use GeneratesUlid;

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'as_of_date' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'created_by_principal_id');
    }
}
