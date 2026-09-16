<?php

namespace App\Services\Campaign;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventRegistry;

/**
 * IMP-007 — registers the canonical program.* / campaign.* / fund.* events
 * (docs/implementation/IMP-007-campaign-program-fund.md section 17) into the
 * locked IMP-004 AuditEventRegistry, mirroring ThemeAuditEventRegistrar
 * exactly. All NonCritical, persistence strategy null, visibility GENERAL,
 * scope ORGANIZATION, subjectIdNullable false. `hasFinancialReference: true`
 * is set on fund.* events and campaign.fund_assigned only (HD-IMP007-02/
 * section 17) — these reference the financial-designation context even
 * though no Ledger posting occurs, requiring AUDIT_READ_FINANCIAL_REFERENCE
 * to read them.
 */
final class CampaignAuditEventRegistrar
{
    public function register(AuditEventRegistry $registry): void
    {
        $NC = AuditCriticality::NonCritical;
        $GENERAL = AuditVisibilityClass::General;
        $ORG = ScopeType::Organization;
        $H = [AuditActorKind::Human];

        $definitions = [
            new AuditEventDefinition('program.created', 1, $NC, null, $GENERAL, 'program', false, [
                'name' => 'string', 'slug' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('program.updated', 1, $NC, null, $GENERAL, 'program', false, [
                'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('program.published', 1, $NC, null, $GENERAL, 'program', false, [], $H, null, false, $ORG),

            new AuditEventDefinition('program.unpublished', 1, $NC, null, $GENERAL, 'program', false, [], $H, null, false, $ORG),

            new AuditEventDefinition('program.archived', 1, $NC, null, $GENERAL, 'program', false, [
                'from_status' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('program.media.uploaded', 1, $NC, null, $GENERAL, 'program_media_asset', false, [
                'asset_ulid' => 'string', 'program_id' => 'int', 'mime_type' => 'string',
                'extension' => 'string', 'size_bytes' => 'int', 'sha256' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('program.media.archived', 1, $NC, null, $GENERAL, 'program_media_asset', false, [
                'asset_ulid' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.created', 1, $NC, null, $GENERAL, 'campaign', false, [
                'name' => 'string', 'slug' => 'string', 'program_id' => 'int',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.updated', 1, $NC, null, $GENERAL, 'campaign', false, [
                'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.submitted', 1, $NC, null, $GENERAL, 'campaign', false, [], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.rejected', 1, $NC, null, $GENERAL, 'campaign', false, [
                'reason' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.approved', 1, $NC, null, $GENERAL, 'campaign', false, [], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.published', 1, $NC, null, $GENERAL, 'campaign', false, [
                'fund_id' => 'int',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.closed', 1, $NC, null, $GENERAL, 'campaign', false, [], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.fund_assigned', 1, $NC, null, $GENERAL, 'campaign', false, [
                'previous_fund_id' => 'int', 'new_fund_id' => 'int',
            ], $H, null, true, $ORG),

            new AuditEventDefinition('campaign.media.uploaded', 1, $NC, null, $GENERAL, 'campaign_media_asset', false, [
                'asset_ulid' => 'string', 'campaign_id' => 'int', 'mime_type' => 'string',
                'extension' => 'string', 'size_bytes' => 'int', 'sha256' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('campaign.media.archived', 1, $NC, null, $GENERAL, 'campaign_media_asset', false, [
                'asset_ulid' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('fund.created', 1, $NC, null, $GENERAL, 'fund', false, [
                'name' => 'string', 'code' => 'string',
            ], $H, null, true, $ORG),

            new AuditEventDefinition('fund.updated', 1, $NC, null, $GENERAL, 'fund', false, [
                'fields_changed' => 'array',
            ], $H, null, true, $ORG),

            new AuditEventDefinition('fund.archived', 1, $NC, null, $GENERAL, 'fund', false, [], $H, null, true, $ORG),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }
}
