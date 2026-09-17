<?php

namespace Tests\Feature\Cms;

use App\Enums\AuditCriticality;
use App\Models\Cms\CmsPage;
use App\Services\Audit\AuditEventRegistry;
use App\Services\Content\PageService;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP005-FINAL-GATE-03 closure evidence — docs/audits/IMP-005-FINALIZATION-
 * CANDIDATE.md section 8. A prior pass flagged "no CMS write service
 * implements the IMP-004 Transaction Ownership Invariant" as a MAJOR gap.
 * Re-reading the invariant's ORIGINAL, authoritative definition
 * (docs/implementation/IMP-004-audit-governance-foundation.md, "Transaction
 * Ownership Invariant (IMP004-PASS2-M01)") shows it is explicitly
 * conditional: "Any service method CAPABLE OF EMITTING A DENIAL_DURABLE
 * EVENT must own the outermost transaction" — a guard whose entire textual
 * rationale is protecting an INDEPENDENTLY-durable denial write from a
 * caller's ambient transaction/rollback. No content.* event is DENIAL_DURABLE
 * (every one of the 20 registered events uses NonCritical criticality and a
 * null persistence strategy — ContentAuditEventRegistrar's own class doc
 * comment and every AuditEventDefinition call site), and no CMS write
 * service anywhere in app/Services/Content/** calls the RBAC-owned
 * denial-persistence path (`security.authorization.denied` is emitted
 * exclusively by RolePermissionService, an IMP-003/004 concern, never by any
 * CMS service). The invariant's precondition is therefore never satisfied by
 * a CMS write service, so its consequent (the entry guard) does not apply —
 * this is proven structurally below, not merely reasoned about.
 *
 * This is also WHY the guard must not be added mechanically: PageService::
 * create() opens its own DB::transaction() and, INSIDE it, calls
 * RevisionService::createDraft(), which ALSO opens its own DB::transaction()
 * — an intentional, currently-safe nested (savepoint) composition. A blind
 * transactionLevel()===0 guard on createDraft() would break this legitimate,
 * already-tested composition, which is a second, independent line of
 * evidence the guard was never meant to apply here.
 */
class TransactionOwnershipInvariantTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_no_content_event_is_registered_denial_durable_or_mutation_atomic(): void
    {
        $registry = app(AuditEventRegistry::class);

        $contentEventTypes = [
            'content.page.created', 'content.page.updated', 'content.page.published',
            'content.page.unpublished', 'content.page.archived',
            'content.article.created', 'content.article.updated', 'content.article.published',
            'content.article.unpublished', 'content.article.archived',
            'content.page.schedule_updated', 'content.article.schedule_updated',
            'content.page.schedule_expired', 'content.article.schedule_expired',
            'content.media.uploaded', 'content.media.updated', 'content.media.archived',
            'content.media.purged', 'content.homepage.assigned', 'content.path.released',
        ];

        $this->assertCount(20, $contentEventTypes, 'sanity: section 12\'s complete v1 inventory is 20 events');

        foreach ($contentEventTypes as $eventType) {
            $definition = $registry->find($eventType, 1);
            $this->assertNotNull($definition, "{$eventType} must be registered");
            $this->assertSame(
                AuditCriticality::NonCritical,
                $definition->criticality,
                "{$eventType} must be NonCritical — a CRITICAL/DENIAL_DURABLE content.* event would "
                .'require re-opening IMP005-FINAL-GATE-03 and adding the Transaction Ownership guard.'
            );
            $this->assertNull(
                $definition->persistenceStrategy,
                "{$eventType} must have a null persistence strategy (neither MUTATION_ATOMIC nor "
                ."DENIAL_DURABLE) — see this test class's doc comment."
            );
        }
    }

    public function test_page_service_create_nests_correctly_inside_an_already_open_ambient_transaction(): void
    {
        // Simulates exactly the scenario the Transaction Ownership Invariant
        // exists to guard against for a DENIAL_DURABLE-capable service — an
        // external caller already holding the connection's transaction open.
        // PageService::create() (and, nested inside it, RevisionService::
        // createDraft()) is NOT DENIAL_DURABLE-capable, so per the invariant's
        // own precondition it must NOT reject this — it must compose safely
        // via Laravel's savepoint nesting instead.
        $actor = $this->makeUnauthorizedActor();
        $levelInsideAmbientTransaction = null;
        $page = null;

        DB::transaction(function () use ($actor, &$levelInsideAmbientTransaction, &$page) {
            $levelInsideAmbientTransaction = DB::connection()->transactionLevel();

            // PageService::create() opens its OWN DB::transaction(), and
            // RevisionService::createDraft() (called from inside it) opens a
            // THIRD, further-nested one — if either rejected an ambient
            // transaction the way a DENIAL_DURABLE-capable method must, this
            // call would throw here instead of returning a fully-populated page.
            $page = app(PageService::class)->create([
                'title' => 'Nested Composition Page',
                'body_html' => '<p>x</p>',
            ], $actor);

            $this->assertSame('DRAFT', $page->status);
            $this->assertNotNull($page->currentDraft()->first(), 'RevisionService::createDraft() must have run to completion');
        });

        $this->assertGreaterThan(0, $levelInsideAmbientTransaction, 'RefreshDatabase\'s own wrapper transaction must already be open');
        $this->assertDatabaseHas('cms_pages', ['title' => 'Nested Composition Page']);
        $this->assertNotNull($page, 'the nested composition must have returned a real, usable Page instance, not merely avoided an exception');
    }

    public function test_a_failure_inside_the_ambient_transaction_after_a_cms_mutation_rolls_everything_back_together(): void
    {
        // Proves the composed (ambient-transaction + CMS-service-transaction)
        // unit is ATOMIC end to end even without a dedicated ownership guard:
        // a later failure in the OUTER transaction rolls back the CMS
        // mutation too, exactly as Laravel's savepoint semantics guarantee.
        $actor = $this->makeUnauthorizedActor();

        try {
            DB::transaction(function () use ($actor) {
                app(PageService::class)->create([
                    'title' => 'Will Be Rolled Back',
                    'body_html' => '<p>x</p>',
                ], $actor);

                throw new \RuntimeException('simulated failure in the ambient (outer) caller, after the CMS mutation');
            });
            $this->fail('expected the outer transaction to roll back');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure in the ambient (outer) caller, after the CMS mutation', $e->getMessage());
        }

        $this->assertDatabaseMissing('cms_pages', ['title' => 'Will Be Rolled Back']);
    }

    public function test_publication_service_still_composes_correctly_nested_inside_an_ambient_transaction(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);

        DB::transaction(function () use ($page, $draft, $actor) {
            $published = app(PublicationService::class)->publish($page, $draft, $actor, '/nested-publish');
            $this->assertSame('PUBLISHED', $published->status);
        });

        $this->assertSame('PUBLISHED', $page->fresh()->status);
    }
}
