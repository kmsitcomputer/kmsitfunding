<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Rbac\Principal;
use App\Services\Content\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 22 — HTTP-boundary coverage for the CMS admin UI (Article
 * management), mirroring PageControllerTest exactly plus article_type
 * coverage (Q33/HD-IMP005-05: News is Article classification).
 */
class ArticleControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeArticle(Principal $owner, string $status = 'DRAFT'): CmsArticle
    {
        return CmsArticle::create([
            'title' => 'Test Article',
            'status' => $status,
            'created_by_principal_id' => $owner->id,
            'updated_by_principal_id' => $owner->id,
        ]);
    }

    public function test_authorized_user_can_list_articles(): void
    {
        $actor = $this->makeAuthorizedActor();
        $this->makeArticle($actor);

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/articles')
            ->assertOk();
    }

    public function test_unauthorized_user_is_forbidden_from_listing_articles(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)
            ->get('/admin/content/articles')
            ->assertForbidden();
    }

    public function test_authorized_user_can_create_an_article_with_a_draft_defaulting_to_article_type(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/articles', [
            'title' => 'Breaking News',
            'body_html' => '<p>Hello world</p>',
        ]);

        $article = CmsArticle::where('title', 'Breaking News')->firstOrFail();
        $response->assertRedirect("/admin/content/articles/{$article->ulid}");
        $this->assertSame('ARTICLE', $article->currentDraft()->first()->article_type);
    }

    public function test_authorized_user_can_create_an_article_with_explicit_news_type(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/articles', [
            'title' => 'Breaking News',
            'body_html' => '<p>Hello world</p>',
            'article_type' => 'NEWS',
        ]);

        $article = CmsArticle::where('title', 'Breaking News')->firstOrFail();
        $response->assertRedirect("/admin/content/articles/{$article->ulid}");
        $this->assertSame('NEWS', $article->currentDraft()->first()->article_type);
    }

    public function test_create_rejects_an_invalid_article_type_as_a_validation_error(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/articles', [
            'title' => 'Bad type',
            'body_html' => '<p>x</p>',
            'article_type' => 'OPINION',
        ]);

        $response->assertSessionHasErrors('article_type');
        $this->assertDatabaseMissing('cms_articles', ['title' => 'Bad type']);
    }

    public function test_unauthorized_user_is_forbidden_from_creating_an_article(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->actingAs($actor->humanUser)->post('/admin/content/articles', [
            'title' => 'Breaking News',
            'body_html' => '<p>Hello world</p>',
        ])->assertForbidden();
    }

    public function test_creating_an_article_rejects_a_script_tag_as_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();

        $response = $this->actingAs($actor->humanUser)->post('/admin/content/articles', [
            'title' => 'XSS attempt',
            'body_html' => '<p>hi</p><script>alert(1)</script>',
        ]);

        $response->assertSessionHasErrors('body_html');
        $this->assertDatabaseMissing('cms_articles', ['title' => 'XSS attempt']);
    }

    public function test_edit_article_is_bound_by_ulid_not_numeric_id(): void
    {
        $actor = $this->makeAuthorizedActor();
        $article = $this->makeArticle($actor);

        $this->actingAs($actor->humanUser)->get("/admin/content/articles/{$article->ulid}")->assertOk();
        $this->actingAs($actor->humanUser)->get("/admin/content/articles/{$article->id}")->assertNotFound();
    }

    public function test_authorized_user_can_update_the_draft(): void
    {
        $actor = $this->makeAuthorizedActor();
        $article = $this->makeArticle($actor);
        app(RevisionService::class)->createDraft($article, [
            'title' => 'v1', 'body_html' => '<p>1</p>',
        ], $actor);

        $response = $this->actingAs($actor->humanUser)->patch("/admin/content/articles/{$article->ulid}", [
            'title' => 'v1 edited',
            'expected_edit_version' => 0,
        ]);

        $response->assertRedirect("/admin/content/articles/{$article->ulid}");
        $this->assertSame('v1 edited', $article->currentDraft()->first()->title);
    }

    public function test_update_with_a_stale_expected_edit_version_returns_a_validation_error_not_a_500(): void
    {
        $actor = $this->makeAuthorizedActor();
        $article = $this->makeArticle($actor);
        app(RevisionService::class)->createDraft($article, [
            'title' => 'v1', 'body_html' => '<p>1</p>',
        ], $actor);

        $this->actingAs($actor->humanUser)->patch("/admin/content/articles/{$article->ulid}", [
            'title' => 'v2', 'expected_edit_version' => 0,
        ])->assertRedirect();

        $response = $this->actingAs($actor->humanUser)->patch("/admin/content/articles/{$article->ulid}", [
            'title' => 'v3 (loser)', 'expected_edit_version' => 0,
        ]);

        $response->assertSessionHasErrors('expected_edit_version');
    }

    public function test_unauthorized_user_is_forbidden_from_updating(): void
    {
        $owner = $this->makeAuthorizedActor();
        $article = $this->makeArticle($owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)->patch("/admin/content/articles/{$article->ulid}", [
            'title' => 'hijacked', 'expected_edit_version' => 0,
        ])->assertForbidden();
    }

    public function test_authorized_user_can_publish_unpublish_and_archive_an_article(): void
    {
        $actor = $this->makeAuthorizedActor();
        $article = $this->makeArticle($actor);
        app(RevisionService::class)->createDraft($article, [
            'title' => 'v1', 'body_html' => '<p>1</p>',
        ], $actor);

        $this->actingAs($actor->humanUser)
            ->post("/admin/content/articles/{$article->ulid}/publish", ['path' => '/news/v1'])
            ->assertRedirect("/admin/content/articles/{$article->ulid}");
        $this->assertSame('PUBLISHED', $article->fresh()->status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/content/articles/{$article->ulid}/unpublish")
            ->assertRedirect("/admin/content/articles/{$article->ulid}");
        $this->assertSame('RETIRED', $article->fresh()->status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/content/articles/{$article->ulid}/archive")
            ->assertRedirect('/admin/content/articles');
        $this->assertSame('ARCHIVED', $article->fresh()->status);
    }

    public function test_unauthorized_user_is_forbidden_from_publishing(): void
    {
        $owner = $this->makeAuthorizedActor();
        $article = $this->makeArticle($owner);
        $unauthorized = $this->makeUnauthorizedActor();

        $this->actingAs($unauthorized->humanUser)
            ->post("/admin/content/articles/{$article->ulid}/publish", ['path' => '/news/v1'])
            ->assertForbidden();
    }
}
