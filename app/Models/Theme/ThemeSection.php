<?php

namespace App\Models\Theme;

use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-006 — Section identity (docs/implementation/IMP-006-theme-engine.md
 * section 10). Placement (which Template, at what position) lives in the
 * theme_template_sections pivot, not here — see ThemeTemplate::sections().
 */
class ThemeSection extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_reusable' => 'boolean',
            'visible' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(ThemeTemplate::class, 'theme_template_sections')
            ->withPivot('position');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ThemeComponent::class)->orderBy('position');
    }
}
