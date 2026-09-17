<?php

namespace Tests\Feature\Cms;

use App\Services\Content\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 23 — HTTP-boundary coverage for the CMS admin UI (Media
 * library).
 */
class MediaControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media.disk'));
    }

    public function test_authorized_user_can_list_media(): void
    {
        $actor = $this->makeAuthorizedActor();
        app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $actor);

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/media')
            ->assertOk();
    }

    public function test_unauthorized_user_is_forbidden_from_listing_media(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/media')
            ->assertForbidden();
    }

    public function test_authorized_user_can_upload_media(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 10, 10),
            'alt_text' => 'A photo',
        ]);

        $response->assertRedirect('/admin/content/media');
        $this->assertDatabaseHas('cms_media_assets', ['original_filename' => 'photo.jpg', 'alt_text' => 'A photo']);
    }

    public function test_uploading_an_svg_is_rejected_as_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/media', [
            'file' => UploadedFile::fake()->createWithContent('evil.svg', '<svg><script>alert(1)</script></svg>'),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_unauthorized_user_is_forbidden_from_uploading(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)->post('/admin/content/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 10, 10),
        ])->assertForbidden();
    }

    public function test_authorized_user_can_update_metadata(): void
    {
        $actor = $this->makeAuthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $actor);

        $response = $this->actingAs($actor->humanUser)->patch("/admin/content/media/{$asset->ulid}", [
            'alt_text' => 'Updated alt',
        ]);

        $response->assertRedirect('/admin/content/media');
        $this->assertSame('Updated alt', $asset->fresh()->alt_text);
    }

    public function test_unauthorized_user_is_forbidden_from_updating_metadata(): void
    {
        $owner = $this->makeAuthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)->patch("/admin/content/media/{$asset->ulid}", [
            'alt_text' => 'hijacked',
        ])->assertForbidden();
    }

    public function test_media_route_is_bound_by_ulid_not_numeric_id(): void
    {
        $actor = $this->makeAuthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $actor);

        $this->actingAs($actor->humanUser)->patch("/admin/content/media/{$asset->id}", [
            'alt_text' => 'x',
        ])->assertNotFound();
    }

    public function test_authorized_user_can_archive_media(): void
    {
        $actor = $this->makeAuthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $actor);

        $response = $this->actingAs($actor->humanUser)->post("/admin/content/media/{$asset->ulid}/archive");

        $response->assertRedirect('/admin/content/media');
        $this->assertSame('ARCHIVED', $asset->fresh()->status);
    }

    public function test_unauthorized_user_is_forbidden_from_archiving(): void
    {
        $owner = $this->makeAuthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)
            ->post("/admin/content/media/{$asset->ulid}/archive")
            ->assertForbidden();
    }
}
