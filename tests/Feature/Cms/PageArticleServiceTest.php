<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Services\Content\ArticleService;
use App\Services\Content\Exceptions\DraftEditConflictException;
use App\Services\Content\Exceptions\RevisionValidationException;
use App\Services\Content\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 6 (PageService/ArticleService) coverage.
 */
class PageArticleServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_page_create_creates_identity_and_initial_draft_together(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $page = app(PageService::class)->create([
            'title' => 'About Us',
            'body_html' => '<p>Hello</p>',
        ], $actor);

        $this->assertInstanceOf(CmsPage::class, $page);
        $this->assertSame('About Us', $page->title);
        $this->assertSame('DRAFT', $page->status);

        $draft = $page->currentDraft()->first();
        $this->assertNotNull($draft);
        $this->assertSame('About Us', $draft->title);
        $this->assertSame('<p>Hello</p>', $draft->body_html);
    }

    public function test_page_update_edits_the_current_draft(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(PageService::class);
        $page = $service->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);

        $updated = $service->update($page, ['title' => 'v1 edited'], expectedEditVersion: 0, actor: $actor);

        $this->assertSame('v1 edited', $updated->title);
        $this->assertSame(1, $updated->edit_version);
    }

    public function test_page_update_rejects_stale_edit_version(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(PageService::class);
        $page = $service->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);

        $service->update($page, ['title' => 'v2'], expectedEditVersion: 0, actor: $actor);

        $this->expectException(DraftEditConflictException::class);
        $service->update($page, ['title' => 'v3 (loser)'], expectedEditVersion: 0, actor: $actor);
    }

    public function test_page_update_rejects_when_no_active_draft_exists(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'x',
            'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        $this->expectException(RevisionValidationException::class);
        app(PageService::class)->update($page, ['title' => 'y'], expectedEditVersion: 0, actor: $actor);
    }

    public function test_article_create_defaults_to_article_classification(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $article = app(ArticleService::class)->create([
            'title' => 'Breaking',
            'body_html' => '<p>...</p>',
        ], $actor);

        $this->assertInstanceOf(CmsArticle::class, $article);
        $this->assertSame('ARTICLE', $article->currentDraft()->first()->article_type);
    }

    public function test_article_create_accepts_news_classification(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $article = app(ArticleService::class)->create([
            'title' => 'Breaking',
            'body_html' => '<p>...</p>',
            'article_type' => 'NEWS',
        ], $actor);

        $this->assertSame('NEWS', $article->currentDraft()->first()->article_type);
    }

    public function test_article_update_edits_the_current_draft(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(ArticleService::class);
        $article = $service->create(['title' => 'v1', 'body_html' => '<p>1</p>'], $actor);

        $updated = $service->update($article, ['title' => 'v1 edited'], expectedEditVersion: 0, actor: $actor);

        $this->assertSame('v1 edited', $updated->title);
    }
}
