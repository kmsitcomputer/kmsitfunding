<?php

namespace Tests\Feature\Cms;

use App\Services\Content\MediaService;
use App\Services\Content\MediaTokenResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 11 (MediaTokenResolver) coverage.
 */
class MediaTokenResolverTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media.disk'));
    }

    private function resolver(): MediaTokenResolver
    {
        return app(MediaTokenResolver::class);
    }

    public function test_resolves_an_active_asset_to_a_url(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);

        $url = $this->resolver()->resolveUrl($asset->ulid);

        $this->assertNotNull($url);
        $this->assertStringContainsString($asset->stored_filename, $url);
    }

    public function test_resolves_an_archived_asset_still_renderable(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $service = app(MediaService::class);
        $asset = $service->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);
        $service->archive($asset, $uploader);

        $this->assertNotNull($this->resolver()->resolveUrl($asset->ulid));
    }

    public function test_purged_asset_resolves_to_null(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);
        $asset->forceFill(['status' => 'PURGED', 'purged_at' => now()])->save();

        $this->assertNull($this->resolver()->resolveUrl($asset->ulid));
    }

    public function test_unknown_token_resolves_to_null(): void
    {
        $this->assertNull($this->resolver()->resolveUrl('01J9ZK3V7Q4XW2N8M5R6T7B1C2'));
    }

    public function test_malformed_token_resolves_to_null_not_an_exception(): void
    {
        $this->assertNull($this->resolver()->resolveUrl('not-a-ulid'));
    }
}
