<?php

namespace Tests\Unit;

use App\Models\PolicyLeadTimeConfig;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CR-001-B (Schema #12, HD-CR001-03) — PolicyLeadTimeConfig singleton
 * coverage, mirroring App\Models\Theme\ThemeActivation's own migration
 * singleton pattern.
 */
class PolicyLeadTimeConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_singleton_row_is_seeded_with_a_null_lead_time(): void
    {
        $config = PolicyLeadTimeConfig::current();

        $this->assertSame(1, $config->id);
        $this->assertNull($config->lead_time_days);
    }

    public function test_the_singleton_row_can_be_updated_in_place(): void
    {
        $config = PolicyLeadTimeConfig::current();
        $config->forceFill(['lead_time_days' => 14])->save();

        $this->assertSame(14, PolicyLeadTimeConfig::current()->lead_time_days);
        $this->assertSame(1, PolicyLeadTimeConfig::query()->count());
    }

    public function test_a_second_row_is_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        PolicyLeadTimeConfig::query()->insert([
            'id' => 2,
            'lead_time_days' => 7,
            'updated_at' => now(),
        ]);
    }

    public function test_required_lead_time_days_fails_closed_when_unconfigured(): void
    {
        $config = PolicyLeadTimeConfig::current();

        $this->expectException(\LogicException::class);
        $config->requiredLeadTimeDays();
    }

    public function test_required_lead_time_days_returns_the_configured_value(): void
    {
        $config = PolicyLeadTimeConfig::current();
        $config->forceFill(['lead_time_days' => 30])->save();

        $this->assertSame(30, $config->fresh()->requiredLeadTimeDays());
    }
}
