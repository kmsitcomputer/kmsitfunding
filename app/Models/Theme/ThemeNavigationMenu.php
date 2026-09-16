<?php

namespace App\Models\Theme;

use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-006 — named navigation menu (docs/implementation/
 * IMP-006-theme-engine.md section 14, Q30).
 */
class ThemeNavigationMenu extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function topLevelItems(): HasMany
    {
        return $this->hasMany(ThemeNavigationItem::class)->whereNull('parent_id')->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ThemeNavigationItem::class)->orderBy('position');
    }
}
