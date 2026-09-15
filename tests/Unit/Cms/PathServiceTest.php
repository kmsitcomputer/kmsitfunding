<?php

namespace Tests\Unit\Cms;

use App\Services\Content\Exceptions\PathValidationException;
use App\Services\Content\PathService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * IMP-005 slice 3 (PathService read/validate side) coverage, mirroring
 * docs/implementation/IMP-005-cms.md section 29's UNIT list for this
 * component: normalization, bounds, and derived reserved-registry
 * membership (S1-S4, S2b).
 */
class PathServiceTest extends TestCase
{
    private function service(): PathService
    {
        return app(PathService::class);
    }

    public function test_nested_segments_are_preserved_not_flattened(): void
    {
        $this->assertSame(
            '/news/my-first-story',
            $this->service()->normalize('/news/My First Story/')
        );
    }

    public function test_lowercase_and_hyphen_collapse(): void
    {
        $this->assertSame('/about-us', $this->service()->normalize('About   Us'));
    }

    public function test_traversal_dot_segment_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize('/news/../admin');
    }

    public function test_bare_dot_segment_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize('/news/./x');
    }

    public function test_percent_encoded_traversal_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize('/news/%2e%2e/admin');
    }

    public function test_backslash_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize('/news\\admin');
    }

    public function test_nul_byte_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize("/news/\0admin");
    }

    public function test_windows_device_name_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize('/CON');
    }

    public function test_segment_that_normalizes_to_empty_is_rejected(): void
    {
        $this->expectException(PathValidationException::class);
        $this->service()->normalize('/!!!/x');
    }

    public function test_total_length_boundary(): void
    {
        // Three segments (depth 3, default max_depth) each <= 100 chars
        // (default max_segment_length), summing to just under/over the
        // 191-character TOTAL bound.
        $service = $this->service();

        $seg = str_repeat('a', 63);
        $ok = "/{$seg}/{$seg}/".str_repeat('a', 61); // 1+63+1+63+1+61 = 190
        $this->assertSame(190, strlen($ok));
        $this->assertTrue($service->isWithinBounds($service->normalize($ok)));

        $tooLong = "/{$seg}/{$seg}/{$seg}"; // 1+63+1+63+1+63 = 192
        $this->assertSame(192, strlen($tooLong));
        $this->assertFalse($service->isWithinBounds($service->normalize($tooLong)));
    }

    public function test_segment_length_boundary(): void
    {
        config(['cms.path.max_segment_length' => 10]);
        $service = $this->service();

        $service->normalize('/'.str_repeat('a', 10));

        $this->expectException(PathValidationException::class);
        $service->normalize('/'.str_repeat('a', 11));
    }

    public function test_depth_boundary(): void
    {
        config(['cms.path.max_depth' => 3]);
        $service = $this->service();

        $service->normalize('/a/b/c');

        $this->expectException(PathValidationException::class);
        $service->normalize('/a/b/c/d');
    }

    public function test_protected_prefix_is_reserved_at_every_depth(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isReserved('/admin'));
        $this->assertTrue($service->isReserved('/admin/settings/deep'));
    }

    public function test_live_route_first_segment_is_reserved(): void
    {
        // /forgot-password is a real registered route (routes/web.php) —
        // the literal regression case section 29's S2 names.
        $this->assertTrue($this->service()->isReserved('/forgot-password'));
    }

    public function test_a_newly_registered_dummy_route_becomes_reserved_without_any_cms_change(): void
    {
        Route::get('/totally-new-dummy-route-'.uniqid(), fn () => null)->name('dummy.route');

        $service = $this->service();
        $uri = collect(Route::getRoutes())->last()->uri();

        $this->assertTrue($service->isReserved('/'.$uri));
    }

    public function test_full_validate_rejects_a_reserved_path_with_path_reserved_reason(): void
    {
        $service = $this->service();

        try {
            $service->validate('/admin/anything');
            $this->fail('Expected PathValidationException');
        } catch (PathValidationException $e) {
            $this->assertSame('path_reserved', $e->reason);
        }
    }

    public function test_full_validate_returns_normalized_path_for_unreserved_input(): void
    {
        $this->assertSame('/about-us', $this->service()->validate('About Us'));
    }
}
