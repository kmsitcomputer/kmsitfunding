<?php

namespace Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CODEX-CR001C-01 UI micro-remediation — rendered template contract.
 *
 * The repository has no Vue component-test framework (no vitest/jest/
 *
 * @vue/test-utils in package.json), and this patch must not introduce a
 * new test stack. These tests therefore verify the compiled SFC template
 * source directly: mutation controls exist ONLY inside `v-if="isDraft"`
 * branches, non-DRAFT renders a read-only notice + read-only display of
 * current config, and `isDraft` derives strictly from theme.status.
 *
 * Backend persisted-state lifecycle tests (SiteDesignDraftOnlyTest) prove
 * the enforcement; these prove the presentation gating.
 */
class SiteDesignReadOnlyTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function menusTemplate(): string
    {
        return file_get_contents(resource_path('js/Pages/Admin/SiteDesign/Menus.vue'));
    }

    private function brandingTemplate(): string
    {
        return file_get_contents(resource_path('js/Pages/Admin/SiteDesign/Branding.vue'));
    }

    public function test_menus_defines_draft_derived_state(): void
    {
        $this->assertMatchesRegularExpression(
            "/isDraft\s*=\s*computed\(\(\)\s*=>\s*props\.theme\.status\s*===\s*'DRAFT'\)/",
            $this->menusTemplate()
        );
    }

    public function test_menus_new_menu_card_requires_draft(): void
    {
        $template = $this->menusTemplate();

        $this->assertStringContainsString('data-testid="new-menu-card"', $template);
        $this->assertMatchesRegularExpression(
            '/<Card v-if="isDraft"[^>]*data-testid="new-menu-card"/',
            $template
        );
    }

    public function test_menus_item_mutation_controls_require_draft(): void
    {
        $template = $this->menusTemplate();

        $this->assertMatchesRegularExpression(
            '/<div v-if="isDraft && editingUlid !== item\.ulid"[^>]*data-testid="item-actions"/',
            $template
        );
        $this->assertMatchesRegularExpression(
            '/<div v-if="isDraft"[^>]*data-testid="new-item-form"/',
            $template
        );
        $this->assertStringNotContainsString('<Button variant="ghost" @click="startEdit(item)">Edit</Button>', preg_replace('/<div v-if="isDraft[^"]*"[^>]*data-testid="item-actions">.*?<\/div>/s', '', $template) ?? $template);
    }

    public function test_menus_inline_edit_form_requires_draft(): void
    {
        $this->assertMatchesRegularExpression(
            '/<template v-if="isDraft && editingUlid === item\.ulid">/',
            $this->menusTemplate()
        );
    }

    public function test_menus_renders_readonly_notice_for_non_draft(): void
    {
        $template = $this->menusTemplate();

        $this->assertMatchesRegularExpression(
            '/<p v-if="!isDraft"[^>]*data-testid="site-design-readonly"/',
            $template
        );
    }

    public function test_menus_still_displays_readonly_information(): void
    {
        $template = $this->menusTemplate();

        $this->assertStringContainsString('{{ item.label }}', $template);
        $this->assertStringContainsString('{{ item.destination_type }}', $template);
        $this->assertStringContainsString("{{ item.visible_desktop ? 'on' : 'off' }}", $template);
        $this->assertStringContainsString("{{ item.visible_mobile ? 'on' : 'off' }}", $template);
        $this->assertStringContainsString('{{ menu.name }}', $template);
    }

    public function test_branding_defines_draft_derived_state(): void
    {
        $this->assertMatchesRegularExpression(
            "/isDraft\s*=\s*computed\(\(\)\s*=>\s*props\.theme\.status\s*===\s*'DRAFT'\)/",
            $this->brandingTemplate()
        );
    }

    public function test_branding_mutation_forms_require_draft(): void
    {
        $template = $this->brandingTemplate();

        $this->assertMatchesRegularExpression(
            '/<Card v-if="isDraft"[^>]*data-testid="branding-form"/',
            $template
        );
        $this->assertMatchesRegularExpression(
            '/<Card v-if="isDraft"[^>]*data-testid="logo-upload-card"/',
            $template
        );
    }

    public function test_branding_renders_readonly_notice_and_current_config(): void
    {
        $template = $this->brandingTemplate();

        $this->assertMatchesRegularExpression(
            '/<p v-if="!isDraft"[^>]*data-testid="site-design-readonly"/',
            $template
        );
        $this->assertMatchesRegularExpression(
            '/<Card v-if="!isDraft"[^>]*data-testid="branding-readonly"/',
            $template
        );
        $this->assertStringContainsString('{{ logoAssetName ?? \'None\' }}', $template);
        $this->assertStringContainsString('{{ faviconAssetName ?? \'None\' }}', $template);
    }

    public function test_no_unconditional_save_buttons_remain_on_branding(): void
    {
        $template = $this->brandingTemplate();
        $scriptEnd = strpos($template, '</script>');

        $this->assertNotFalse($scriptEnd);

        $markup = substr($template, (int) $scriptEnd);

        $draftCardPos = strpos($markup, 'data-testid="branding-form"');
        $savePos = strpos($markup, '>Save appearance</Button>');

        $this->assertNotFalse($draftCardPos);
        $this->assertNotFalse($savePos);
        $this->assertGreaterThan($draftCardPos, $savePos);

        $between = substr($markup, (int) $draftCardPos, (int) $savePos - (int) $draftCardPos);
        $this->assertStringNotContainsString('v-if="!isDraft"', $between);
    }
}
