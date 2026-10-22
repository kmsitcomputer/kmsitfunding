<?php

namespace App\Models\Zakat;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CR-001-B (Schema #7) — versioned Nisab threshold basis reference
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 20/55). `gram_equivalent` is a physical quantity, hence DECIMAL
 * — never FLOAT/DOUBLE, never amount_minor.
 */
class NisabPolicy extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gram_equivalent' => 'decimal:4',
            'effective_from' => 'date',
            'effective_until' => 'date',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'updated_by_principal_id');
    }
}
