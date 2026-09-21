<?php

namespace App\Models\Theme;

use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-006 — one navigation entry (docs/implementation/IMP-006-theme-engine.md
 * section 14). `destination_type` is the closed union
 * SYSTEM_ROUTE|CMS_CONTENT|EXTERNAL_URL — see
 * App\Services\Theme\NavigationDestinationResolver for how exactly one of
 * the destination_* columns is interpreted per type. One level of nesting
 * only: `children()` is never itself expected to hold rows with a non-null
 * parent (enforced by ThemeNavigationService, not the schema).
 */
class ThemeNavigationItem extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'visible_desktop' => 'boolean',
            'visible_mobile' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(ThemeNavigationMenu::class, 'theme_navigation_menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
