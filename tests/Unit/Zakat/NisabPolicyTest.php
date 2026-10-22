<?php

namespace Tests\Unit\Zakat;

use App\Models\Zakat\NisabPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #7) — NisabPolicy versioned reference model coverage.
 */
class NisabPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_valid_row(): void
    {
        $policy = NisabPolicy::create([
            'ulid' => (string) Str::ulid(),
            'basis_code' => 'GOLD_GRAM',
            'gram_equivalent' => '85.0000',
            'effective_from' => '2026-01-01',
            'status' => 'DRAFT',
        ]);

        $this->assertSame('GOLD_GRAM', $policy->basis_code);
        $this->assertSame('85.0000', (string) $policy->gram_equivalent);
        $this->assertNotEmpty($policy->ulid);
    }
}
