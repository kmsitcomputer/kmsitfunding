<?php

namespace Tests\Feature\Theme;

use App\Services\Theme\Exceptions\ThemeAssetValidationException;
use App\Services\Theme\ThemeAssetService;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 — theme asset upload/validation/archive coverage
 * (docs/implementation/IMP-006-theme-engine.md section 17), mirroring
 * IMP-005's MediaServiceTest discipline.
 */
class ThemeAssetServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('theme.disk'));
    }

    private function service(): ThemeAssetService
    {
        return app(ThemeAssetService::class);
    }

    private function theme()
    {
        return app(ThemeService::class)->create(['name' => 'T'], $this->makeUnauthorizedActor());
    }

    public function test_upload_stores_a_valid_png(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->theme();

        $asset = $this->service()->upload($theme, UploadedFile::fake()->image('logo.png', 100, 100), $actor);

        $this->assertSame('ACTIVE', $asset->status);
        $this->assertSame($theme->id, $asset->theme_id);
        $month = now()->format('Y/m');
        Storage::disk(config('theme.disk'))->assertExists("theme/{$month}/{$asset->stored_filename}");
    }

    public function test_upload_rejects_svg(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->theme();

        $this->expectException(ThemeAssetValidationException::class);
        $this->service()->upload($theme, UploadedFile::fake()->createWithContent('logo.svg', '<svg><script>alert(1)</script></svg>'), $actor);
    }

    public function test_upload_rejects_oversized_files(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->theme();

        $this->expectException(ThemeAssetValidationException::class);
        $this->service()->upload($theme, UploadedFile::fake()->image('big.png', 100, 100)->size(3 * 1024), $actor);
    }

    public function test_upload_rejects_a_php_file_disguised_with_an_image_extension(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->theme();

        $this->expectException(ThemeAssetValidationException::class);
        $this->service()->upload($theme, UploadedFile::fake()->createWithContent('logo.png', '<?php echo "pwned"; ?>'), $actor);
    }

    public function test_archive_is_idempotent(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->theme();
        $asset = $this->service()->upload($theme, UploadedFile::fake()->image('logo.png', 50, 50), $actor);

        $this->service()->archive($asset, $actor);
        $archived = $this->service()->archive($asset->fresh(), $actor);

        $this->assertSame('ARCHIVED', $archived->status);
    }
}
