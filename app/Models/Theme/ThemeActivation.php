<?php

namespace App\Models\Theme;

use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-006 — the single-row "currently active theme" pointer
 * (docs/implementation/IMP-006-theme-engine.md section 8/24), mirroring
 * App\Models\Cms\CmsHomepageAssignment exactly.
 */
class ThemeActivation extends Model
{
    protected $table = 'theme_activation';

    public $incrementing = false;

    const CREATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function activeTheme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'active_theme_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'assigned_by_principal_id');
    }
}
