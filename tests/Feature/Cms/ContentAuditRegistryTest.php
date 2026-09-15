<?php

namespace Tests\Feature\Cms;

use App\Enums\AuditCriticality;
use App\Enums\AuditVisibilityClass;
use App\Enums\ScopeType;
use App\Services\Audit\AuditEventRegistry;
use Tests\TestCase;

/**
 * IMP-005 slice 12 (content.* audit registration) coverage — proves the 20
 * events from docs/implementation/IMP-005-cms.md section 12 are registered
 * with the correct shared axes and a few event-specific allow-list details,
 * and that AuditEventRegistry's own file was not touched to do it.
 */
class ContentAuditRegistryTest extends TestCase
{
    private const EVENT_TYPES = [
        'content.page.created', 'content.page.updated', 'content.page.published',
        'content.page.unpublished', 'content.page.archived',
        'content.article.created', 'content.article.updated', 'content.article.published',
        'content.article.unpublished', 'content.article.archived',
        'content.page.schedule_updated', 'content.article.schedule_updated',
        'content.page.schedule_expired', 'content.article.schedule_expired',
        'content.media.uploaded', 'content.media.updated', 'content.media.archived', 'content.media.purged',
        'content.homepage.assigned', 'content.path.released',
    ];

    public function test_all_twenty_content_events_are_registered(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (self::EVENT_TYPES as $eventType) {
            $this->assertNotNull($registry->find($eventType, 1), "{$eventType}@1 is not registered");
        }
    }

    public function test_every_content_event_is_non_critical_general_organization_scoped(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (self::EVENT_TYPES as $eventType) {
            $definition = $registry->find($eventType, 1);

            $this->assertSame(AuditCriticality::NonCritical, $definition->criticality, "{$eventType} criticality");
            $this->assertNull($definition->persistenceStrategy, "{$eventType} persistence strategy");
            $this->assertSame(AuditVisibilityClass::General, $definition->visibilityClass, "{$eventType} visibility");
            $this->assertSame(ScopeType::Organization, $definition->scopeType, "{$eventType} scope");
            $this->assertFalse($definition->subjectIdNullable, "{$eventType} subject_id must not be nullable");
            $this->assertSame([], $definition->financialReferenceFields, "{$eventType} must carry no financial reference");
        }
    }

    public function test_page_published_carries_homepage_designated_but_article_published_does_not(): void
    {
        $registry = app(AuditEventRegistry::class);

        $this->assertArrayHasKey('homepage_designated', $registry->find('content.page.published', 1)->metadataAllowList);
        $this->assertArrayNotHasKey('homepage_designated', $registry->find('content.article.published', 1)->metadataAllowList);
    }

    public function test_article_events_carry_article_type_page_events_do_not(): void
    {
        $registry = app(AuditEventRegistry::class);

        $this->assertArrayHasKey('article_type', $registry->find('content.article.created', 1)->metadataAllowList);
        $this->assertArrayNotHasKey('article_type', $registry->find('content.page.created', 1)->metadataAllowList);
    }

    public function test_media_purged_has_no_optional_or_conditional_keys(): void
    {
        $registry = app(AuditEventRegistry::class);
        $definition = $registry->find('content.media.purged', 1);

        $this->assertSame(
            ['asset_ulid', 'purge_attempts', 'active_references_verified_absent', 'previously_archived_by_principal_id', 'system_operation'],
            array_keys($definition->metadataAllowList)
        );
    }

    public function test_no_content_event_declares_a_hard_prohibited_or_body_key(): void
    {
        $registry = app(AuditEventRegistry::class);

        foreach (self::EVENT_TYPES as $eventType) {
            $keys = array_keys($registry->find($eventType, 1)->metadataAllowList);

            $this->assertNotContains('body_html', $keys, "{$eventType} must never allow body_html");
            $this->assertNotContains('password', $keys, "{$eventType} must never allow password");
        }
    }
}
