<?php

namespace App\Models\Theme\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a public ULID identifier on creation, mirroring App\Models\Cms\
 * Concerns\GeneratesUlid exactly (docs/implementation/IMP-006-theme-engine.md
 * follows the same "internal BIGINT PKs never leave the server layer"
 * discipline IMP-005 established). Duplicated rather than imported across
 * modules to keep each module's own concerns self-contained.
 */
trait GeneratesUlid
{
    protected static function bootGeneratesUlid(): void
    {
        static::creating(function ($model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }
}
