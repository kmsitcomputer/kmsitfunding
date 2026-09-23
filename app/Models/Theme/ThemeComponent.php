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

    /**
     * CR-001-D F-04 — persist timestamps with microsecond precision so the
     * Page Builder stale-edit comparison is meaningful. Schema already uses
     * dateTime(..., 6); without this Eloquent writes Y-m-d H:i:s (.000000).
     * No schema change, no migration.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

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
