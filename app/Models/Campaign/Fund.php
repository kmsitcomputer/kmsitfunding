<?php

namespace App\Models\Campaign;

use App\Models\Campaign\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-007 — Fund: the designation/restriction context for money
 * (docs/implementation/IMP-007-campaign-program-fund.md section 8). NOT a
 * payment transaction, ledger account, mutable balance, or wallet.
 * status: ACTIVE|ARCHIVED.
 */
class Fund extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
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
