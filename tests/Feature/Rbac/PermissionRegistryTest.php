<?php

namespace Tests\Feature\Rbac;

use App\Services\Rbac\PermissionRegistry;
use Tests\TestCase;

/**
 * CR-001-B — proves the THEME_PREVIEW permission constant is registered
 * per Section 30 / B-RECON §5.1.
 */
class PermissionRegistryTest extends TestCase
{
    public function test_theme_preview_constant_is_registered(): void
    {
        $this->assertSame('theme.preview', PermissionRegistry::THEME_PREVIEW);
        $this->assertArrayHasKey(PermissionRegistry::THEME_PREVIEW, PermissionRegistry::definitions());
    }
}
