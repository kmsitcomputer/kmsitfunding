<?php

namespace App\Models\Theme;

use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-006 — Component/Block instances (docs/implementation/
 * IMP-006-theme-engine.md section 11). `config` is validated server-side
 * against its `type`'s schema BEFORE every save by
 * App\Services\Theme\ComponentConfigValidator — this model never accepts an
 * unvalidated config write from a controller directly.
 */
class ThemeComponent extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ThemeSection::class, 'theme_section_id');
    }
}
