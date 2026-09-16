<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;

/**
 * IMP-006 — thin sink wrapper over the canonical AuditWriter, mirroring
 * App\Services\Content\ContentAuditLogger exactly (docs/implementation/
 * IMP-006-theme-engine.md section 19). One method per theme.* event — all
 * 12 registered in ThemeAuditEventRegistrar are wired to a real call site.
 */
class ThemeAuditLogger
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function recordThemeCreated(int $themeId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.created', 'theme', $themeId, $metadata, $actor);
    }

    public function recordThemeUpdated(int $themeId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.updated', 'theme', $themeId, $metadata, $actor);
    }

    public function recordThemeActivated(int $themeId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.activated', 'theme', $themeId, $metadata, $actor);
    }

    public function recordThemeDeactivated(int $themeId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.deactivated', 'theme', $themeId, $metadata, $actor);
    }

    public function recordThemeArchived(int $themeId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.archived', 'theme', $themeId, $metadata, $actor);
    }

    public function recordTemplateUpdated(int $templateId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.template.updated', 'theme_template', $templateId, $metadata, $actor);
    }

    public function recordSectionUpdated(int $sectionId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.section.updated', 'theme_section', $sectionId, $metadata, $actor);
    }

    public function recordComponentUpdated(int $componentId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.component.updated', 'theme_component', $componentId, $metadata, $actor);
    }

    public function recordNavigationUpdated(int $menuId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.navigation.updated', 'theme_navigation_menu', $menuId, $metadata, $actor);
    }

    public function recordBrandingUpdated(int $brandingConfigId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.branding.updated', 'theme_branding_config', $brandingConfigId, $metadata, $actor);
    }

    public function recordAssetUploaded(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.asset.uploaded', 'theme_asset', $assetId, $metadata, $actor);
    }

    public function recordAssetArchived(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('theme.asset.archived', 'theme_asset', $assetId, $metadata, $actor);
    }

    private function emit(string $eventType, string $subjectType, int $subjectId, array $metadata, Principal $actor): void
    {
        $this->writer->record(new AuditEventInput(
            eventType: $eventType,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: $metadata,
        ));
    }
}
