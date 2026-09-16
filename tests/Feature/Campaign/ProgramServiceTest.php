<?php

namespace Tests\Feature\Campaign;

use App\Services\Campaign\Exceptions\CampaignValidationException;
use App\Services\Campaign\ProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — Program CRUD + simple lifecycle. AC-007-001.
 */
class ProgramServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_create_persists_a_draft_program_and_records_audit_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'Education Program'], $actor);

        $this->assertSame('DRAFT', $program->status);
        $this->assertNotEmpty($program->ulid);
        $this->assertSame($actor->id, $program->created_by_principal_id);
        $this->assertDatabaseHas('audit_records', ['event_type' => 'program.created', 'subject_id' => $program->id]);
    }

    public function test_create_rejects_a_duplicate_slug(): void
    {
        $actor = $this->makeUnauthorizedActor();
        app(ProgramService::class)->create(['name' => 'A', 'slug' => 'dup'], $actor);

        $this->expectException(CampaignValidationException::class);
        app(ProgramService::class)->create(['name' => 'B', 'slug' => 'dup'], $actor);
    }

    public function test_update_requires_matching_edit_version(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'A'], $actor);

        $this->expectException(CampaignValidationException::class);
        app(ProgramService::class)->update($program, ['name' => 'B'], 999, $actor);
    }

    public function test_update_increments_edit_version_and_records_audit_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'A'], $actor);

        $updated = app(ProgramService::class)->update($program, ['name' => 'B'], 0, $actor);

        $this->assertSame('B', $updated->name);
        $this->assertSame(1, $updated->edit_version);
        $this->assertDatabaseHas('audit_records', ['event_type' => 'program.updated', 'subject_id' => $program->id]);
    }

    public function test_publish_unpublish_and_archive_lifecycle(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(ProgramService::class);
        $program = $service->create(['name' => 'A'], $actor);

        $published = $service->publish($program, $actor);
        $this->assertSame('PUBLISHED', $published->status);

        $unpublished = $service->unpublish($published, $actor);
        $this->assertSame('DRAFT', $unpublished->status);

        $archived = $service->archive($unpublished, $actor);
        $this->assertSame('ARCHIVED', $archived->status);
    }

    public function test_archive_of_already_archived_program_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(ProgramService::class);
        $program = $service->create(['name' => 'A'], $actor);
        $service->archive($program, $actor);

        $this->expectException(CampaignValidationException::class);
        $service->archive($program->fresh(), $actor);
    }
}
