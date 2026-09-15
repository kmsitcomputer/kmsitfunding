<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 1 (schema + models) smoke coverage. Not the §29 test matrix
 * (that is slice 6, once services/policies exist) — this only proves the
 * composite ownership FKs and generated-column invariants from
 * docs/implementation/IMP-005-cms.md section 13 are real at the DB layer,
 * matching the O1/O9-style intent for the schema that exists so far.
 */
class SchemaConstraintsTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_a_page_can_point_at_a_revision_it_owns(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $page = CmsPage::create([
            'title' => 'About Us',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        $revision = CmsContentRevision::create([
            'page_id' => $page->id,
            'revision_no' => 1,
            'title' => 'About Us',
            'body_html' => '<p>Hello</p>',
            'author_principal_id' => $actor->id,
            'authored_at' => now(),
        ]);

        $page->forceFill(['published_revision_id' => $revision->id])->save();

        $this->assertSame($revision->id, $page->fresh()->published_revision_id);
    }

    public function test_a_page_cannot_point_at_a_revision_owned_by_another_page(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $pageA = CmsPage::create([
            'title' => 'Page A',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        $pageB = CmsPage::create([
            'title' => 'Page B',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        $revisionOwnedByA = CmsContentRevision::create([
            'page_id' => $pageA->id,
            'revision_no' => 1,
            'title' => 'Page A',
            'body_html' => '<p>Hello</p>',
            'author_principal_id' => $actor->id,
            'authored_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $pageB->forceFill(['published_revision_id' => $revisionOwnedByA->id])->save();
    }

    public function test_a_revision_cannot_be_owned_by_both_a_page_and_an_article(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $page = CmsPage::create([
            'title' => 'Page A',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        $this->expectException(QueryException::class);

        CmsContentRevision::create([
            'page_id' => $page->id,
            'article_id' => 1,
            'revision_no' => 1,
            'title' => 'Invalid',
            'body_html' => '<p>Hello</p>',
            'author_principal_id' => $actor->id,
            'authored_at' => now(),
        ]);
    }

    public function test_only_one_active_draft_revision_per_page_is_allowed(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $page = CmsPage::create([
            'title' => 'Page A',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        CmsContentRevision::create([
            'page_id' => $page->id,
            'revision_no' => 1,
            'title' => 'Draft one',
            'body_html' => '<p>1</p>',
            'author_principal_id' => $actor->id,
            'authored_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        CmsContentRevision::create([
            'page_id' => $page->id,
            'revision_no' => 2,
            'title' => 'Draft two',
            'body_html' => '<p>2</p>',
            'author_principal_id' => $actor->id,
            'authored_at' => now(),
        ]);
    }

    public function test_homepage_assignment_singleton_row_exists_and_is_the_only_row(): void
    {
        $assignment = CmsHomepageAssignment::find(1);

        $this->assertNotNull($assignment);
        $this->assertNull($assignment->page_id);

        $this->expectException(QueryException::class);

        // forceFill (not create()) so 'id' — guarded, since only the seeded
        // row=1 may ever legitimately exist — reaches the insert, proving
        // the CHECK(id=1) singleton constraint itself, not just the guard.
        (new CmsHomepageAssignment)
            ->forceFill(['id' => 2, 'updated_at' => now()])
            ->save();
    }
}
