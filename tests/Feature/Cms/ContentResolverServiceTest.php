<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Services\Content\ContentResolverService;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 15 (ContentResolverService) coverage — docs/implementation/
 * IMP-005-cms.md section 14's "RESOLUTION AND 404 BEHAVIOR" table.
 */
class ContentResolverServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function resolver(): ContentResolverService
    {
        return app(ContentResolverService::class);
    }

    private function publishedPage(string $path): CmsPage
    {
        $actor = $this->makeUnauthorizedActor();
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
        $draft = app(RevisionService::class)->createDraft($page, ['title' => 'Hello', 'body_html' => '<p>Body</p>'], $actor);

        return app(PublicationService::class)->publish($page, $draft, $actor, $path);
    }

    public function test_current_published_path_resolves_to_content(): void
    {
        $this->publishedPage('/about-us');

        $result = $this->resolver()->resolve('/about-us');

        $this->assertSame('found', $result->type);
        $this->assertSame('page', $result->content->kind);
        $this->assertSame('Hello', $result->content->title);
        $this->assertSame('<p>Body</p>', $result->content->bodyHtml);
        $this->assertSame('/about-us', $result->content->canonicalPath);
    }

    public function test_article_resolves_with_article_type(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $article = CmsArticle::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ]);
        $draft = app(RevisionService::class)->createDraft($article, ['title' => 'News', 'body_html' => '<p>x</p>', 'article_type' => 'NEWS'], $actor);
        app(PublicationService::class)->publish($article, $draft, $actor, '/news/x');

        $result = $this->resolver()->resolve('/news/x');

        $this->assertSame('article', $result->content->kind);
        $this->assertSame('NEWS', $result->content->articleType);
    }

    public function test_renamed_path_redirects_to_current_path(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage('/old-path');
        $draft2 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft2, $actor, '/new-path');

        $result = $this->resolver()->resolve('/old-path');

        $this->assertSame('redirect', $result->type);
        $this->assertSame('/new-path', $result->redirectTo);
    }

    public function test_unclaimed_path_is_not_found(): void
    {
        $this->assertSame('not_found', $this->resolver()->resolve('/never-claimed')->type);
    }

    public function test_retired_owner_is_not_found_but_path_stays_reserved(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage('/x');
        app(PublicationService::class)->unpublish($page->fresh(), $actor);

        $this->assertSame('not_found', $this->resolver()->resolve('/x')->type);
        $this->assertSame('ACTIVE', CmsPath::where('path', '/x')->value('status'), 'reservation is unaffected (section 13)');
    }

    public function test_archived_owner_is_not_found(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage('/x');
        app(PublicationService::class)->unpublish($page->fresh(), $actor);
        app(PublicationService::class)->archive($page->fresh(), $actor);

        $this->assertSame('not_found', $this->resolver()->resolve('/x')->type);
    }

    public function test_a_redirect_whose_owner_has_no_current_claim_is_not_found(): void
    {
        // Contrived edge case per section 14's own resolution table: a
        // REDIRECT row survives even if its owner's CURRENT claim were
        // somehow gone (not reachable via normal flows, but the resolver
        // must still degrade to 404, not throw).
        $actor = $this->makeUnauthorizedActor();
        $page = $this->publishedPage('/old');
        $draft2 = app(RevisionService::class)->createDraft($page->fresh(), ['title' => 'v2', 'body_html' => '<p>2</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $draft2, $actor, '/new');
        CmsPath::where('page_id', $page->id)->where('purpose', 'CURRENT')->delete();

        $this->assertSame('not_found', $this->resolver()->resolve('/old')->type);
    }

    public function test_malformed_incoming_path_degrades_to_not_found_not_an_exception(): void
    {
        $this->assertSame('not_found', $this->resolver()->resolve('/../etc/passwd')->type);
    }
}
