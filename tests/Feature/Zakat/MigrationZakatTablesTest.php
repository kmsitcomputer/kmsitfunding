<?php

namespace Tests\Feature\Zakat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CR-001-B (Schema #5-#12) — proves all 8 new tables exist with the
 * approved column set. Structural existence only — no business value is
 * asserted.
 */
class MigrationZakatTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_zakat_types_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('zakat_types', [
            'id', 'ulid', 'code', 'name', 'calculation_method_ref', 'is_active', 'created_at', 'updated_at',
        ]));
    }

    public function test_zakat_policies_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('zakat_policies', [
            'id', 'ulid', 'zakat_type_id', 'version', 'rate', 'nisab_basis', 'source_ref',
            'effective_from', 'effective_until', 'status', 'created_by_principal_id', 'updated_by_principal_id',
        ]));
    }

    public function test_nisab_policies_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('nisab_policies', [
            'id', 'ulid', 'basis_code', 'gram_equivalent', 'source_ref', 'effective_from', 'effective_until', 'status',
        ]));
    }

    public function test_gold_price_references_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('gold_price_references', [
            'id', 'ulid', 'amount_minor', 'currency', 'as_of_date', 'source_ref', 'created_by_principal_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('gold_price_references', 'updated_at'));
    }

    public function test_zakat_calculation_snapshots_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('zakat_calculation_snapshots', [
            'id', 'ulid', 'zakat_type_id', 'zakat_policy_id', 'nisab_policy_id', 'gold_price_reference_id',
            'input', 'result', 'computed_at', 'acting_principal_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('zakat_calculation_snapshots', 'updated_at'));
    }

    public function test_fidyah_policies_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('fidyah_policies', [
            'id', 'ulid', 'version', 'rate_amount_minor', 'currency', 'source_ref', 'effective_from', 'effective_until', 'status',
        ]));
    }

    public function test_fidyah_calculation_snapshots_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('fidyah_calculation_snapshots', [
            'id', 'ulid', 'fidyah_policy_id', 'input', 'result', 'computed_at', 'acting_principal_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('fidyah_calculation_snapshots', 'updated_at'));
    }

    public function test_policy_lead_time_configs_table_has_expected_columns_and_singleton_row(): void
    {
        $this->assertTrue(Schema::hasColumns('policy_lead_time_configs', [
            'id', 'lead_time_days', 'updated_by_principal_id', 'updated_at',
        ]));
        $this->assertSame(1, DB::table('policy_lead_time_configs')->count());
        $this->assertNull(DB::table('policy_lead_time_configs')->value('lead_time_days'));
    }
}
