<?php

namespace App\Models\Cms;

use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-005 — the single-row homepage CONTENT designation (docs/implementation/
 * IMP-005-cms.md section 13 "cms_homepage_assignment"). A designation, not a
 * route (section 8). The one row (id=1) is seeded by its migration; this
 * model never creates or deletes rows, only updates the singleton — see
 * $incrementing = false (the PK is a fixed 1, not an AUTO_INCREMENT value).
 */
class CmsHomepageAssignment extends Model
{
    // Singular: Eloquent's default pluralization would guess
    // "cms_homepage_assignments", but this is deliberately a single-row
    // singleton table (section 13) named without the trailing 's'.
    protected $table = 'cms_homepage_assignment';

    public $incrementing = false;

    // No created_at column exists (the row is seeded once by its migration,
    // never created again) — tell Eloquent not to expect or write one.
    const CREATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'assigned_by_principal_id');
    }
}
