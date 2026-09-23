<?php

namespace Tests\Unit\Fidyah;

use App\Models\Fidyah\FidyahPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #10) — FidyahPolicy versioned model coverage.
 */
class FidyahPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_valid_row_with_integer_rate_amount_minor(): void
    {
        $policy = FidyahPolicy::create([
            'ulid' => (string) Str::ulid(),
            'version' => 1,
            'rate_amount_minor' => 3500000,
            'currency' => 'IDR',
            'effective_from' => '2026-01-01',
            'status' => 'DRAFT',
        ]);

        $this->assertIsInt($policy->rate_amount_minor);
        $this->assertSame(3500000, $policy->rate_amount_minor);
    }

    public function test_multiple_versions_may_coexist(): void
    {
        FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3000000, 'currency' => 'IDR',
            'effective_from' => '2025-01-01', 'effective_until' => '2025-12-31', 'status' => 'DRAFT',
        ]);
        FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 2, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertSame(2, FidyahPolicy::count());
    }
}
