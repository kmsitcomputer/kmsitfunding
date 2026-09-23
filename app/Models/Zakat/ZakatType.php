<?php

namespace App\Models\Zakat;

use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CR-001-B (Schema #5) — Zakat type registry (docs/change-requests/
 * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 20/55).
 * `calculation_method_ref` is a reference key the future IMP-019 calculator
 * dispatches on — this model carries no formula or rate itself.
 */
class ZakatType extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function policies(): HasMany
    {
        return $this->hasMany(ZakatPolicy::class);
    }
}
