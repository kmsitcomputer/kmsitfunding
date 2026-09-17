<?php

namespace App\Services\Theme;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventRegistry;

/**
 * IMP-006 — registers the 12 canonical theme.* events (docs/implementation/
 * IMP-006-theme-engine.md section 19) into the locked IMP-004
 * AuditEventRegistry, mirroring App\Services\Content\
 * ContentAuditEventRegistrar exactly. Every theme.* event: criticality
 * NonCritical, persistence strategy null (neither MUTATION_ATOMIC nor
 * DENIAL_DURABLE — see the specification's section 20 Transaction Ownership
 * Invariant analysis, which depends on this classification holding),
 * visibility GENERAL, scope ORGANIZATION, subjectIdNullable false.
 */
final class ThemeAuditEventRegistrar
{
    public function register(AuditEventRegistry $registry): void
    {
        $NC = AuditCriticality::NonCritical;
        $GENERAL = AuditVisibilityClass::General;
        $ORG = ScopeType::Organization;
        $H = [AuditActorKind::Human];

        $definitions = [
            new AuditEventDefinition('theme.created', 1, $NC, null, $GENERAL, 'theme', false, [
                'name' => 'string', 'slug' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.updated', 1, $NC, null, $GENERAL, 'theme', false, [
                'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.activated', 1, $NC, null, $GENERAL, 'theme', false, [
                'previous_theme_id' => 'int',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.deactivated', 1, $NC, null, $GENERAL, 'theme', false, [
                'replaced_by_theme_id' => 'int',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.archived', 1, $NC, null, $GENERAL, 'theme', false, [
                'from_status' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.template.updated', 1, $NC, null, $GENERAL, 'theme_template', false, [
                'theme_id' => 'int', 'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.section.updated', 1, $NC, null, $GENERAL, 'theme_section', false, [
                'theme_id' => 'int', 'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.component.updated', 1, $NC, null, $GENERAL, 'theme_component', false, [
                'theme_section_id' => 'int', 'type' => 'string', 'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.navigation.updated', 1, $NC, null, $GENERAL, 'theme_navigation_menu', false, [
                'theme_id' => 'int', 'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.branding.updated', 1, $NC, null, $GENERAL, 'theme_branding_config', false, [
                'theme_id' => 'int', 'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.asset.uploaded', 1, $NC, null, $GENERAL, 'theme_asset', false, [
                'asset_ulid' => 'string', 'theme_id' => 'int', 'mime_type' => 'string',
                'extension' => 'string', 'size_bytes' => 'int', 'sha256' => 'string',
                'duplicate_asset_ulid' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('theme.asset.archived', 1, $NC, null, $GENERAL, 'theme_asset', false, [
                'asset_ulid' => 'string',
            ], $H, null, false, $ORG),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }
}
