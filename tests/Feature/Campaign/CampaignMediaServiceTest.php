<?php

namespace Tests\Feature\Campaign;

use App\Services\Campaign\CampaignMediaService;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\Exceptions\CampaignMediaValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — Campaign media upload/validation/archive, mirroring
 * ThemeAssetServiceTest's adversarial coverage exactly. AC-007-015.
 */
class CampaignMediaServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('campaign.disk'));
    }

    private function service(): CampaignMediaService
    {
        return app(CampaignMediaService::class);
    }

    private function campaign()
    {
        return app(CampaignService::class)->create(['name' => 'C'], $this->makeUnauthorizedActor());
    }

    public function test_upload_stores_a_valid_png(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->campaign();

        $asset = $this->service()->upload($campaign, UploadedFile::fake()->image('cover.png', 100, 100), $actor);

        $this->assertSame('ACTIVE', $asset->status);
        $this->assertSame($campaign->id, $asset->campaign_id);
        $month = now()->format('Y/m');
        Storage::disk(config('campaign.disk'))->assertExists("campaign/{$month}/{$asset->stored_filename}");
        $this->assertDatabaseHas('audit_records', ['event_type' => 'campaign.media.uploaded', 'subject_id' => $asset->id]);
    }

    public function test_upload_rejects_svg(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->campaign();

        $this->expectException(CampaignMediaValidationException::class);
        $this->service()->upload($campaign, UploadedFile::fake()->createWithContent('x.svg', '<svg><script>alert(1)</script></svg>'), $actor);
    }

    public function test_upload_rejects_oversized_files(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->campaign();

        $this->expectException(CampaignMediaValidationException::class);
        $this->service()->upload($campaign, UploadedFile::fake()->image('big.png', 100, 100)->size(6 * 1024), $actor);
    }

    public function test_upload_rejects_a_php_file_disguised_with_an_image_extension(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->campaign();

        $this->expectException(CampaignMediaValidationException::class);
        $this->service()->upload($campaign, UploadedFile::fake()->createWithContent('x.png', '<?php echo "pwned"; ?>'), $actor);
    }

    public function test_upload_rejects_an_oversized_image_dimension(): void
    {
        // Override the dimension ceiling to a tiny value rather than
        // generating a genuinely huge fake image (memory-prohibitive in the
        // test process) — proves the same dimension-bomb-guard code path.
        config(['campaign.max_image_dimension' => 10]);
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->campaign();

        $this->expectException(CampaignMediaValidationException::class);
        $this->service()->upload($campaign, UploadedFile::fake()->image('huge.png', 100, 100), $actor);
    }

    public function test_archive_is_idempotent(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->campaign();
        $asset = $this->service()->upload($campaign, UploadedFile::fake()->image('cover.png', 50, 50), $actor);

        $this->service()->archive($asset, $actor);
        $archived = $this->service()->archive($asset->fresh(), $actor);

        $this->assertSame('ARCHIVED', $archived->status);
    }
}
