<?php

namespace App\Models\Theme;

use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * IMP-006 — Template identity + content-kind assignment
 * (docs/implementation/IMP-006-theme-engine.md section 9).
 */
class ThemeTemplate extends Model
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

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(ThemeSection::class, 'theme_template_sections')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
