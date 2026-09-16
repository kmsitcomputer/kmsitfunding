<?php

namespace Tests\Feature\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Campaign\Program;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\FundService;
use App\Services\Campaign\ProgramService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — proves data-integrity constraints (docs/implementation/
 * IMP-007-campaign-program-fund.md section 12/18) are real DATABASE
 * invariants, not merely application-level pre-checks, mirroring
 * ThemeSchemaConstraintsTest's "UNIQUE, not just a pre-check" discipline.
 * AC-007-009/010.
 */
class CampaignSchemaConstraintsTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_program_referenced_by_a_campaign_cannot_be_deleted_at_the_database_level(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'P'], $actor);
        app(CampaignService::class)->create(['name' => 'C', 'program_ulid' => $program->ulid], $actor);

        $this->expectException(QueryException::class);
        Program::query()->whereKey($program->id)->delete();
    }

    public function test_fund_referenced_by_a_campaign_cannot_be_deleted_at_the_database_level(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);

        $this->expectException(QueryException::class);
        Fund::query()->whereKey($fund->id)->delete();
    }

    public function test_campaign_slug_uniqueness_is_enforced_by_the_database_not_only_the_service(): void
    {
        $actor = $this->makeUnauthorizedActor();
        app(CampaignService::class)->create(['name' => 'A', 'slug' => 'dup-slug'], $actor);

        $this->expectException(QueryException::class);

        Campaign::query()->insert([
            'ulid' => (string) Str::ulid(),
            'name' => 'B',
            'slug' => 'dup-slug',
            'status' => 'DRAFT',
            'edit_version' => 0,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fund_code_uniqueness_is_enforced_by_the_database(): void
    {
        $actor = $this->makeUnauthorizedActor();
        app(FundService::class)->create(['name' => 'A', 'code' => 'unique-code'], $actor);

        $this->expectException(QueryException::class);

        Fund::query()->insert([
            'ulid' => (string) Str::ulid(),
            'name' => 'B',
            'code' => 'UNIQUE-CODE',
            'status' => 'ACTIVE',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
