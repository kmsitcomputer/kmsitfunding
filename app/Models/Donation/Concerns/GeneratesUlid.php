<?php

namespace App\Models\Donation\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a public ULID identifier on creation, mirroring
 * App\Models\Campaign\Concerns\GeneratesUlid exactly (module-boundary
 * discipline — duplicated rather than imported across domains).
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
