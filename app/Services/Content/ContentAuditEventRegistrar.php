<?php

namespace App\Services\Content;

use App\Enums\AuditActorKind;
use App\Enums\AuditCriticality;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\AuditEventDefinition;
use App\Services\Audit\AuditEventRegistry;

/**
 * IMP-005 — registers the 20 canonical content.* events (docs/implementation/
 * IMP-005-cms.md section 12) into the locked IMP-004 AuditEventRegistry via
 * its own public register() method. Deliberately NOT added inside
 * AuditEventRegistry::registerCanonicalEvents() itself — that file stays
 * untouched; this class is the CMS-owned extension point, consuming IMP-004
 * rather than forking it, exactly as MULTI-MODEL-OWNERSHIP's "additive
 * registration is not an architecture change" principle allows.
 *
 * Every content.* event: criticality NON_CRITICAL, persistence strategy
 * null (neither MUTATION_ATOMIC nor DENIAL_DURABLE), visibility GENERAL,
 * scope ORGANIZATION (this repository has no ScopeType::Organization
 * registry precedent yet — CMS is the first; matches ContentScopeResolver,
 * slice 2), subjectIdNullable false (every content.* event has a real
 * subject). None carries a financial reference. source_event_id / source
 * domain are omitted for all twenty — local producer, no external identity
 * to deduplicate against (business idempotency is state + schedule_version +
 * consumed timestamps, not audit deduplication, section 12).
 */
final class ContentAuditEventRegistrar
{
    public function register(AuditEventRegistry $registry): void
    {
        $NC = AuditCriticality::NonCritical;
        $GENERAL = AuditVisibilityClass::General;
        $ORG = ScopeType::Organization;
        $H = [AuditActorKind::Human];
        $HS = [AuditActorKind::Human, AuditActorKind::System];
        $S = [AuditActorKind::System];

        $publishedAllowList = [
            'revision_id' => 'int',
            'from_status' => 'string',
            'to_status' => 'string',
            'path' => 'string',
            'path_change' => 'string',
            'previous_revision_id' => 'int',
            'previous_path' => 'string',
            'redirect_created' => 'int',
            'schedule_version' => 'int',
            'scheduled_at' => 'string',
            'scheduled_revision_id' => 'int',
            'scheduled_by_principal_id' => 'int',
            'system_operation' => 'string',
        ];

        $unpublishedAllowList = [
            'revision_id' => 'int',
            'from_status' => 'string',
            'to_status' => 'string',
            'path' => 'string',
            'path_change' => 'string',
            'schedule_version' => 'int',
            'scheduled_at' => 'string',
            'scheduled_revision_id' => 'int',
            'scheduled_by_principal_id' => 'int',
            'system_operation' => 'string',
        ];

        $archivedAllowList = [
            'from_status' => 'string',
            'to_status' => 'string',
            'redirect_claims_retained' => 'int',
            'path_claim_retained' => 'string',
        ];

        $scheduleUpdatedAllowList = [
            'operation' => 'string',
            'schedule_version' => 'int',
            'publish_at' => 'string',
            'unpublish_at' => 'string',
            'scheduled_revision_id' => 'int',
            'scheduled_at' => 'string',
            'scheduled_by_principal_id' => 'int',
        ];

        $scheduleExpiredAllowList = [
            'schedule_version' => 'int',
            'outcome' => 'string',
            'publish_at' => 'string',
            'unpublish_at' => 'string',
        ];

        $definitions = [
            new AuditEventDefinition('content.page.created', 1, $NC, null, $GENERAL, 'cms_page', false, [
                'revision_id' => 'int',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.page.updated', 1, $NC, null, $GENERAL, 'cms_page', false, [
                'revision_id' => 'int',
                'fields_changed' => 'array',
                'slug_snapshot' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.page.published', 1, $NC, null, $GENERAL, 'cms_page', false, [
                ...$publishedAllowList,
                'homepage_designated' => 'int',
            ], $HS, null, false, $ORG),

            new AuditEventDefinition('content.page.unpublished', 1, $NC, null, $GENERAL, 'cms_page', false, $unpublishedAllowList, $HS, null, false, $ORG),

            new AuditEventDefinition('content.page.archived', 1, $NC, null, $GENERAL, 'cms_page', false, [
                ...$archivedAllowList,
                'homepage_designation' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.article.created', 1, $NC, null, $GENERAL, 'cms_article', false, [
                'revision_id' => 'int',
                'article_type' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.article.updated', 1, $NC, null, $GENERAL, 'cms_article', false, [
                'revision_id' => 'int',
                'fields_changed' => 'array',
                'slug_snapshot' => 'string',
                'article_type' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.article.published', 1, $NC, null, $GENERAL, 'cms_article', false, [
                ...$publishedAllowList,
                'article_type' => 'string',
            ], $HS, null, false, $ORG),

            new AuditEventDefinition('content.article.unpublished', 1, $NC, null, $GENERAL, 'cms_article', false, [
                ...$unpublishedAllowList,
                'article_type' => 'string',
            ], $HS, null, false, $ORG),

            new AuditEventDefinition('content.article.archived', 1, $NC, null, $GENERAL, 'cms_article', false, [
                ...$archivedAllowList,
                'article_type' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.page.schedule_updated', 1, $NC, null, $GENERAL, 'cms_page', false, $scheduleUpdatedAllowList, $H, null, false, $ORG),

            new AuditEventDefinition('content.article.schedule_updated', 1, $NC, null, $GENERAL, 'cms_article', false, [
                ...$scheduleUpdatedAllowList,
                'article_type' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.page.schedule_expired', 1, $NC, null, $GENERAL, 'cms_page', false, $scheduleExpiredAllowList, $S, null, false, $ORG),

            new AuditEventDefinition('content.article.schedule_expired', 1, $NC, null, $GENERAL, 'cms_article', false, [
                ...$scheduleExpiredAllowList,
                'article_type' => 'string',
            ], $S, null, false, $ORG),

            new AuditEventDefinition('content.media.uploaded', 1, $NC, null, $GENERAL, 'cms_media_asset', false, [
                'asset_ulid' => 'string',
                'mime_type' => 'string',
                'extension' => 'string',
                'size_bytes' => 'int',
                'sha256' => 'string',
                'duplicate_asset_ulid' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.media.updated', 1, $NC, null, $GENERAL, 'cms_media_asset', false, [
                'asset_ulid' => 'string',
                'fields_changed' => 'array',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.media.archived', 1, $NC, null, $GENERAL, 'cms_media_asset', false, [
                'asset_ulid' => 'string',
                'prior_references' => 'string',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.media.purged', 1, $NC, null, $GENERAL, 'cms_media_asset', false, [
                'asset_ulid' => 'string',
                'purge_attempts' => 'int',
                'active_references_verified_absent' => 'int',
                'previously_archived_by_principal_id' => 'int',
                'system_operation' => 'string',
            ], $S, null, false, $ORG),

            new AuditEventDefinition('content.homepage.assigned', 1, $NC, null, $GENERAL, 'cms_homepage_assignment', false, [
                'operation' => 'string',
                'previous_expected_matched' => 'int',
                'page_id' => 'int',
                'page_ulid' => 'string',
                'previous_page_id' => 'int',
                'expected_page_id' => 'int',
            ], $H, null, false, $ORG),

            new AuditEventDefinition('content.path.released', 1, $NC, null, $GENERAL, 'cms_path', false, [
                'path' => 'string',
                'purpose' => 'string',
                'owner_type' => 'string',
                'owner_id' => 'int',
                'reason' => 'string',
            ], $H, null, false, $ORG),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }
}
