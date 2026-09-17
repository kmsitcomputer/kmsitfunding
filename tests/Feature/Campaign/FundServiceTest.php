<?php

namespace Tests\Feature\Campaign;

use App\Models\Campaign\Campaign;
use App\Services\Campaign\Exceptions\FundValidationException;
use App\Services\Campaign\FundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — Fund CRUD. Fund is a designation/restriction context only — no
 * amount/balance is ever stored here (docs/implementation/
 * IMP-007-campaign-program-fund.md section 6). AC-007-010/016.
 */
class FundServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_create_persists_an_active_fund_and_records_audit_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'General', 'code' => 'general'], $actor);

        $this->assertSame('ACTIVE', $fund->status);
        $this->assertSame('GENERAL', $fund->code);
        $this->assertDatabaseHas('audit_records', ['event_type' => 'fund.created', 'subject_id' => $fund->id]);
    }

    public function test_create_rejects_a_duplicate_code_case_insensitively(): void
    {
        $actor = $this->makeUnauthorizedActor();
        app(FundService::class)->create(['name' => 'A', 'code' => 'dup'], $actor);

        $this->expectException(FundValidationException::class);
        app(FundService::class)->create(['name' => 'B', 'code' => 'DUP'], $actor);
    }

    public function test_archiving_a_fund_referenced_by_a_published_campaign_succeeds_and_does_not_affect_the_campaign(): void
    {
        // AC-007-016: archival always succeeds and never retroactively
        // invalidates an existing (even PUBLISHED) Campaign's historical
        // fund_id.
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'A', 'code' => 'a'], $actor);

        $campaign = new Campaign;
        $campaign->forceFill([
            'ulid' => (string) Str::ulid(),
            'name' => 'C',
            'slug' => 'c-'.uniqid(),
            'fund_id' => $fund->id,
            'status' => 'PUBLISHED',
            'edit_version' => 0,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
        $campaign->save();

        $archived = app(FundService::class)->archive($fund, $actor);

        $this->assertSame('ARCHIVED', $archived->status);
        $this->assertSame($fund->id, $campaign->fresh()->fund_id, 'Archiving must not clear an existing Campaign reference.');
        $this->assertSame('PUBLISHED', $campaign->fresh()->status, 'Archiving a Fund must not affect the referencing Campaign status.');
        $this->assertDatabaseHas('audit_records', ['event_type' => 'fund.archived', 'subject_id' => $fund->id]);
    }

    public function test_archive_is_idempotent(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'A', 'code' => 'a'], $actor);

        app(FundService::class)->archive($fund, $actor);
        $archivedAgain = app(FundService::class)->archive($fund->fresh(), $actor);

        $this->assertSame('ARCHIVED', $archivedAgain->status);
    }
}
