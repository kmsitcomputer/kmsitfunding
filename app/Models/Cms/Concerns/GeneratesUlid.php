<?php

namespace App\Models\Cms\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a public ULID identifier on creation for CMS identity/asset models
 * that expose a `ulid` column separate from their internal BIGINT primary
 * key (docs/implementation/IMP-005-cms.md section 13: "Public identifiers
 * are ULIDs ... internal BIGINT PKs and FKs never leave the server layer").
 * Not Laravel's built-in HasUlids trait, which makes the ULID the primary
 * key itself — these models keep a BIGINT PK for composite-FK ownership.
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
