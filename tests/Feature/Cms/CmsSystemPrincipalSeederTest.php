<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\SystemPrincipal;
use App\Policies\ContentPagePolicy;
use App\Policies\MediaPolicy;
use Database\Seeders\CmsSystemPrincipalSeeder;
use Database\Seeders\RbacPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 17 (CMS System Principal seeding) coverage — section 12/19:
 * bounded authority per job, catalog membership alone is not authority.
 */
class CmsSystemPrincipalSeederTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacPermissionSeeder::class);
    }

    private function principalFor(string $systemCode): Principal
    {
        $systemPrincipal = SystemPrincipal::where('code', $systemCode)->firstOrFail();

        return Principal::where('system_principal_id', $systemPrincipal->id)->firstOrFail();
    }

    public function test_seeding_creates_both_system_principals_with_linked_principals(): void
    {
        $this->seed(CmsSystemPrincipalSeeder::class);

        $this->assertNotNull(SystemPrincipal::where('code', 'content.scheduler')->first());
        $this->assertNotNull(SystemPrincipal::where('code', 'content.media_cleanup')->first());
        $this->assertNotNull($this->principalFor('content.scheduler'));
        $this->assertNotNull($this->principalFor('content.media_cleanup'));
    }

    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(CmsSystemPrincipalSeeder::class);
        $this->seed(CmsSystemPrincipalSeeder::class);

        $this->assertSame(1, SystemPrincipal::where('code', 'content.scheduler')->count());
        $this->assertSame(
            1,
            PrincipalRoleAssignment::where('principal_id', $this->principalFor('content.scheduler')->id)->count()
        );
    }

    public function test_scheduler_principal_can_publish_but_not_archive_or_update(): void
    {
        $this->seed(CmsSystemPrincipalSeeder::class);
        $scheduler = $this->principalFor('content.scheduler');

        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);

        $policy = new ContentPagePolicy;
        $this->assertTrue($policy->publish($scheduler, $page));
        $this->assertFalse($policy->archive($scheduler, $page), 'bounded authority: no content.archive');
        $this->assertFalse($policy->update($scheduler, $page), 'bounded authority: no content.update');
    }

    public function test_media_cleanup_principal_can_archive_but_not_publish_or_upload(): void
    {
        $this->seed(CmsSystemPrincipalSeeder::class);
        $cleanup = $this->principalFor('content.media_cleanup');

        $actor = $this->makeUnauthorizedActor();
        $asset = new CmsMediaAsset;
        $asset->forceFill([
            'stored_filename' => 'x.jpg', 'original_filename' => 'x.jpg', 'mime_type' => 'image/jpeg',
            'extension' => 'jpg', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64),
            'uploaded_by_principal_id' => $actor->id,
        ])->save();
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);

        $mediaPolicy = new MediaPolicy;
        $pagePolicy = new ContentPagePolicy;

        $this->assertTrue($pagePolicy->archive($cleanup, $page));
        $this->assertFalse($pagePolicy->publish($cleanup, $page), 'no content.publish — never impersonates the archiving Human');
        $this->assertFalse($mediaPolicy->upload($cleanup), 'no content.media.upload');
    }
}
