<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsMediaAsset;
use App\Services\Content\Exceptions\MediaValidationException;
use App\Services\Content\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 10 (MediaService upload intake + logical archive)
 * coverage — docs/implementation/IMP-005-cms.md section 19.
 */
class MediaServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media.disk'));
    }

    private function service(): MediaService
    {
        return app(MediaService::class);
    }

    public function test_valid_image_upload_succeeds_and_stores_a_generated_filename(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $asset = $this->service()->upload($file, $uploader);

        $this->assertInstanceOf(CmsMediaAsset::class, $asset);
        $this->assertSame('jpg', $asset->extension);
        $this->assertSame('image/jpeg', $asset->mime_type);
        $this->assertSame('ACTIVE', $asset->status);
        $this->assertSame($uploader->id, $asset->uploaded_by_principal_id);
        $this->assertSame(100, $asset->width);
        $this->assertSame(100, $asset->height);
        $this->assertNotSame('photo.jpg', $asset->stored_filename, 'filename is GENERATED, never user input');
        $this->assertStringEndsWith('.jpg', $asset->stored_filename);

        Storage::disk(config('media.disk'))->assertExists('content/'.now()->format('Y/m').'/'.$asset->stored_filename);
    }

    public function test_valid_pdf_upload_succeeds(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $file = UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n%fake pdf body for testing\n%%EOF");

        $asset = $this->service()->upload($file, $uploader);

        $this->assertSame('pdf', $asset->extension);
        $this->assertSame('application/pdf', $asset->mime_type);
    }

    public function test_svg_upload_is_rejected(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $file = UploadedFile::fake()->createWithContent('evil.svg', '<svg><script>alert(1)</script></svg>');

        $this->expectException(MediaValidationException::class);
        $this->service()->upload($file, $uploader);
    }

    public function test_php_upload_disguised_as_image_extension_is_rejected(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        // Client-side extension says .jpg, but the actual bytes are PHP —
        // MIME is sniffed from bytes, never trusted from the filename.
        $file = UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>');

        $this->expectException(MediaValidationException::class);
        $this->service()->upload($file, $uploader);
    }

    public function test_pdf_without_valid_header_is_rejected(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $file = UploadedFile::fake()->createWithContent('fake.pdf', 'not actually a pdf')->mimeType('application/pdf');

        $this->expectException(MediaValidationException::class);
        $this->service()->upload($file, $uploader);
    }

    public function test_oversized_image_is_rejected(): void
    {
        config(['media.allowed_types.jpg.max_bytes' => 1024]);
        $uploader = $this->makeUnauthorizedActor();
        $file = UploadedFile::fake()->image('big.jpg', 100, 100)->size(2000);

        $this->expectException(MediaValidationException::class);
        $this->service()->upload($file, $uploader);
    }

    public function test_oversized_image_dimensions_are_rejected(): void
    {
        config(['media.max_image_dimension' => 50]);
        $uploader = $this->makeUnauthorizedActor();
        $file = UploadedFile::fake()->image('huge.jpg', 100, 100);

        $this->expectException(MediaValidationException::class);
        $this->service()->upload($file, $uploader);
    }

    public function test_duplicate_hash_is_detected_but_not_blocked(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $service = $this->service();

        $first = $service->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);
        $duplicateFound = $service->findActiveDuplicate($first->sha256);

        $this->assertNotNull($duplicateFound);
        $this->assertSame($first->id, $duplicateFound->id);
    }

    public function test_archive_transitions_active_to_archived(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $asset = $this->service()->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);

        $archived = $this->service()->archive($asset, $uploader);

        $this->assertSame('ARCHIVED', $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertSame($uploader->id, $archived->archived_by_principal_id);
    }

    public function test_re_archiving_an_already_archived_asset_is_an_idempotent_no_op(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $asset = $this->service()->upload(UploadedFile::fake()->image('a.jpg', 10, 10), $uploader);
        $first = $this->service()->archive($asset, $uploader);

        $second = $this->service()->archive($asset->fresh(), $uploader);

        $this->assertSame($first->archived_at->toDateTimeString(), $second->archived_at->toDateTimeString());
    }
}
