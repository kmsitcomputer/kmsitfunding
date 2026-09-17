<?php

namespace Tests\Unit\Theme;

use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\NavigationDestinationValidator;
use Tests\TestCase;

/**
 * IMP-006 — navigation destination closed-union validation
 * (docs/implementation/IMP-006-theme-engine.md section 14/21).
 */
class NavigationDestinationValidatorTest extends TestCase
{
    private function validator(): NavigationDestinationValidator
    {
        return new NavigationDestinationValidator;
    }

    public function test_accepts_a_real_registered_system_route(): void
    {
        $this->validator()->assertValid(['destination_type' => 'SYSTEM_ROUTE', 'destination_route' => 'login']);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_an_unregistered_system_route(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid(['destination_type' => 'SYSTEM_ROUTE', 'destination_route' => 'this.route.does.not.exist']);
    }

    public function test_accepts_a_valid_https_external_url(): void
    {
        $this->validator()->assertValid(['destination_type' => 'EXTERNAL_URL', 'destination_external_url' => 'https://example.com/page']);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_a_javascript_scheme_external_url(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid(['destination_type' => 'EXTERNAL_URL', 'destination_external_url' => 'javascript:alert(document.cookie)']);
    }

    public function test_rejects_a_data_scheme_external_url(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid(['destination_type' => 'EXTERNAL_URL', 'destination_external_url' => 'data:text/html,<script>alert(1)</script>']);
    }

    public function test_accepts_a_well_formed_cms_content_destination(): void
    {
        $this->validator()->assertValid([
            'destination_type' => 'CMS_CONTENT',
            'destination_content_kind' => 'page',
            'destination_content_ulid' => str_repeat('A', 26),
        ]);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_an_invalid_content_kind(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid([
            'destination_type' => 'CMS_CONTENT',
            'destination_content_kind' => 'campaign',
            'destination_content_ulid' => str_repeat('A', 26),
        ]);
    }

    public function test_rejects_an_unrecognized_destination_type(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid(['destination_type' => 'ARBITRARY_TYPE']);
    }
}
