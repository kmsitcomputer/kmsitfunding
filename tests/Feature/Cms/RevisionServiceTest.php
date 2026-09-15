<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Services\Content\Exceptions\ActiveDraftExistsException;
use App\Services\Content\Exceptions\ContentSanitizationException;
use App\Services\Content\Exceptions\DraftEditConflictException;
use App\Services\Content\Exceptions\RevisionValidationException;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 4 (RevisionService) coverage — docs/implementation/
 * IMP-005-cms.md section 11 write boundary + section 19 step 5 concurrency.
 */
class RevisionServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function service(): RevisionService
    {
        return app(RevisionService::class);
    }

    private function makePage(): CmsPage
    {
        $actor = $this->makeUnauthorizedActor();

        return CmsPage::create([
            'title' => 'Untitled',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    private function makeArticle(): CmsArticle
    {
        $actor = $this->makeUnauthorizedActor();

        return CmsArticle::create([
            'title' => 'Untitled',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    public function test_create_draft_for_page_sets_expected_defaults(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $revision = $this->service()->createDraft($page, [
            'title' => 'About Us',
            'body_html' => '<p>Hello</p>',
        ], $author);

        $this->assertSame($page->id, $revision->page_id);
        $this->assertNull($revision->article_id);
        $this->assertSame(1, $revision->revision_no);
        $this->assertSame('DRAFT', $revision->state);
        $this->assertSame(0, $revision->edit_version);
        $this->assertNull($revision->article_type);
        $this->assertNull($revision->slug_snapshot);
        $this->assertSame($author->id, $revision->author_principal_id);
    }

    public function test_create_draft_for_article_defaults_article_type_to_article(): void
    {
        $author = $this->makeUnauthorizedActor();
        $article = $this->makeArticle();

        $revision = $this->service()->createDraft($article, [
            'title' => 'Breaking News',
            'body_html' => '<p>...</p>',
        ], $author);

        $this->assertSame('ARTICLE', $revision->article_type);
    }

    public function test_create_draft_for_article_accepts_explicit_news_classification(): void
    {
        $author = $this->makeUnauthorizedActor();
        $article = $this->makeArticle();

        $revision = $this->service()->createDraft($article, [
            'title' => 'Breaking News',
            'body_html' => '<p>...</p>',
            'article_type' => 'NEWS',
        ], $author);

        $this->assertSame('NEWS', $revision->article_type);
    }

    public function test_create_draft_rejects_article_type_on_a_page(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $this->expectException(RevisionValidationException::class);
        $this->service()->createDraft($page, [
            'title' => 'About Us',
            'body_html' => '<p>x</p>',
            'article_type' => 'ARTICLE',
        ], $author);
    }

    public function test_create_draft_rejects_invalid_article_type(): void
    {
        $author = $this->makeUnauthorizedActor();
        $article = $this->makeArticle();

        $this->expectException(RevisionValidationException::class);
        $this->service()->createDraft($article, [
            'title' => 'x',
            'body_html' => '<p>x</p>',
            'article_type' => 'OPINION',
        ], $author);
    }

    public function test_create_draft_rejects_a_second_active_draft_for_the_same_owner(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        $this->expectException(ActiveDraftExistsException::class);
        $this->service()->createDraft($page, ['title' => 'v2', 'body_html' => '<p>2</p>'], $author);
    }

    public function test_revision_numbers_increment_per_owner(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $first = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        // No PublicationService yet in this slice — simulate the revision
        // having left DRAFT (the only way a second draft becomes legal)
        // via a direct test-only lifecycle write, matching how PublicationService
        // itself would set these columns.
        $first->forceFill(['state' => 'PUBLISHED', 'published_at' => now()])->save();

        $second = $this->service()->createDraft($page, ['title' => 'v2', 'body_html' => '<p>2</p>'], $author);

        $this->assertSame(2, $second->revision_no);
    }

    public function test_edit_draft_updates_payload_and_increments_edit_version(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $revision = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        $updated = $this->service()->editDraft($revision, ['title' => 'v1 edited'], expectedEditVersion: 0);

        $this->assertSame('v1 edited', $updated->title);
        $this->assertSame(1, $updated->edit_version);
    }

    public function test_edit_draft_rejects_stale_expected_edit_version(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $revision = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        $this->service()->editDraft($revision, ['title' => 'v2'], expectedEditVersion: 0);

        $this->expectException(DraftEditConflictException::class);
        // Second editor still thinks it's at version 0 (stale read).
        $this->service()->editDraft($revision, ['title' => 'v3 (loser)'], expectedEditVersion: 0);
    }

    public function test_edit_draft_rejects_editing_a_non_draft_revision(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $revision = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);
        $revision->forceFill(['state' => 'PUBLISHED', 'published_at' => now()])->save();

        $this->expectException(RevisionValidationException::class);
        $this->service()->editDraft($revision, ['title' => 'illegal edit'], expectedEditVersion: 0);
    }

    public function test_edit_draft_never_touches_lifecycle_columns(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $revision = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        $updated = $this->service()->editDraft($revision, ['title' => 'v1 edited'], expectedEditVersion: 0);

        $this->assertSame('DRAFT', $updated->state);
        $this->assertNull($updated->published_at);
        $this->assertNull($updated->superseded_at);
    }

    public function test_rollback_by_copy_creates_a_new_draft_and_never_rewrites_history(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $original = $this->service()->createDraft($page, [
            'title' => 'Historical Title',
            'body_html' => '<p>Historical body</p>',
        ], $author);
        $original->forceFill(['state' => 'PUBLISHED', 'published_at' => now()])->save();

        $copy = $this->service()->rollbackByCopy($original->fresh(), $author);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame('Historical Title', $copy->title);
        $this->assertSame('<p>Historical body</p>', $copy->body_html);
        $this->assertSame('DRAFT', $copy->state);
        $this->assertSame(2, $copy->revision_no);

        // The historical row itself is byte-identical to before.
        $this->assertSame('Historical Title', $original->fresh()->title);
        $this->assertSame('PUBLISHED', $original->fresh()->state);
    }

    public function test_rollback_by_copy_rejects_when_an_active_draft_already_exists(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $original = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);
        $original->forceFill(['state' => 'PUBLISHED', 'published_at' => now()])->save();

        $this->service()->createDraft($page, ['title' => 'v2 draft', 'body_html' => '<p>2</p>'], $author);

        $this->expectException(ActiveDraftExistsException::class);
        $this->service()->rollbackByCopy($original->fresh(), $author);
    }

    public function test_current_draft_relation_finds_the_active_draft(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $this->assertNull($page->currentDraft);

        $revision = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        $this->assertSame($revision->id, $page->currentDraft()->first()->id);
    }

    public function test_create_draft_sanitizes_body_html_and_rejects_a_script_tag(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $this->expectException(ContentSanitizationException::class);
        $this->service()->createDraft($page, [
            'title' => 'v1',
            'body_html' => '<p>hi</p><script>alert(1)</script>',
        ], $author);
    }

    public function test_create_draft_stores_the_sanitized_body_not_the_raw_input(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();

        $revision = $this->service()->createDraft($page, [
            'title' => 'v1',
            'body_html' => '<p onclick="alert(1)">hi</p>',
        ], $author);

        $this->assertStringNotContainsString('onclick', $revision->body_html);
        $this->assertStringContainsString('hi', $revision->body_html);
    }

    public function test_edit_draft_sanitizes_body_html_on_edit(): void
    {
        $author = $this->makeUnauthorizedActor();
        $page = $this->makePage();
        $revision = $this->service()->createDraft($page, ['title' => 'v1', 'body_html' => '<p>1</p>'], $author);

        $this->expectException(ContentSanitizationException::class);
        $this->service()->editDraft($revision, ['body_html' => '<iframe src="https://evil.example"></iframe>'], expectedEditVersion: 0);
    }
}
