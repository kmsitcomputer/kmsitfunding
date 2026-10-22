<?php

namespace App\Models\Payment\Concerns;

use Illuminate\Support\Str;

trait GeneratesPaymentUlid
{
    protected static function bootGeneratesPaymentUlid(): void
    {
        static::creating(function ($model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }
}
