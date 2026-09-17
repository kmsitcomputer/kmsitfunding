<?php

namespace App\Models\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * IMP-006 — Theme identity + lifecycle (docs/implementation/
 * IMP-006-theme-engine.md section 7/8). status: DRAFT|ACTIVE|INACTIVE.
 * Activation itself is owned exclusively by ThemeActivationService via the
 * theme_activation singleton (section 24) — this model never flips its own
 * status column outside that service.
 */
class Theme extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_system_default' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ThemeTemplate::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ThemeSection::class);
    }

    public function navigationMenus(): HasMany
    {
        return $this->hasMany(ThemeNavigationMenu::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ThemeAsset::class);
    }

    public function branding(): HasOne
    {
        return $this->hasOne(ThemeBrandingConfig::class);
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
