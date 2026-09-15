<?php

namespace Tests\Feature\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Policies\ContentArticlePolicy;
use App\Policies\ContentPagePolicy;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 2 (authorization) coverage. Proves the three policies
 * (docs/implementation/IMP-005-cms.md section 18/22/23) route through the
 * canonical AuthorizationEvaluator correctly: an actor holding every
 * registered permission at GLOBAL_PLATFORM+ELEVATED passes every gate, an
 * actor holding nothing fails every gate, and the publish/archive
 * resourceStatePredicate rejects an ARCHIVED resource even for the
 * authorized actor.
 */
class ContentAuthorizationTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makePage(string $status = 'DRAFT'): CmsPage
    {
        $actor = $this->makeUnauthorizedActor();

        return CmsPage::create([
            'title' => 'Test Page',
            'status' => $status,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    private function makeArticle(string $status = 'DRAFT'): CmsArticle
    {
        $actor = $this->makeUnauthorizedActor();

        return CmsArticle::create([
            'title' => 'Test Article',
            'status' => $status,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
    }

    private function makeMediaAsset(): CmsMediaAsset
    {
        $actor = $this->makeUnauthorizedActor();

        // stored_filename is guarded (server-generated, section 19) — forceFill it
        // directly rather than through the model's own create() guard.
        $asset = new CmsMediaAsset;
        $asset->forceFill([
            'stored_filename' => 'test-'.uniqid().'.png',
            'original_filename' => 'test.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 1024,
            'sha256' => str_repeat('a', 64),
            'uploaded_by_principal_id' => $actor->id,
        ])->save();

        return $asset;
    }

    public function test_content_page_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new ContentPagePolicy;
        $page = $this->makePage();

        $this->assertTrue($policy->view($authorized));
        $this->assertFalse($policy->view($unauthorized));

        $this->assertTrue($policy->viewPage($authorized, $page));
        $this->assertFalse($policy->viewPage($unauthorized, $page));

        $this->assertTrue($policy->create($authorized));
        $this->assertFalse($policy->create($unauthorized));

        $this->assertTrue($policy->update($authorized, $page));
        $this->assertFalse($policy->update($unauthorized, $page));

        $this->assertTrue($policy->publish($authorized, $page));
        $this->assertFalse($policy->publish($unauthorized, $page));

        $this->assertTrue($policy->archive($authorized, $page));
        $this->assertFalse($policy->archive($unauthorized, $page));
    }

    public function test_content_page_policy_rejects_publish_and_archive_on_archived_page_even_for_authorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $policy = new ContentPagePolicy;
        $archivedPage = $this->makePage('ARCHIVED');

        $this->assertFalse($policy->publish($authorized, $archivedPage));
        $this->assertFalse($policy->archive($authorized, $archivedPage));
    }

    public function test_content_page_policy_allows_publish_from_draft_retired_and_published(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $policy = new ContentPagePolicy;

        $this->assertTrue($policy->publish($authorized, $this->makePage('DRAFT')));
        $this->assertTrue($policy->publish($authorized, $this->makePage('RETIRED')));
        $this->assertTrue($policy->publish($authorized, $this->makePage('PUBLISHED')));
    }

    public function test_content_article_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new ContentArticlePolicy;
        $article = $this->makeArticle();

        $this->assertTrue($policy->view($authorized));
        $this->assertFalse($policy->view($unauthorized));

        $this->assertTrue($policy->viewArticle($authorized, $article));
        $this->assertFalse($policy->viewArticle($unauthorized, $article));

        $this->assertTrue($policy->create($authorized));
        $this->assertFalse($policy->create($unauthorized));

        $this->assertTrue($policy->update($authorized, $article));
        $this->assertFalse($policy->update($unauthorized, $article));

        $this->assertTrue($policy->publish($authorized, $article));
        $this->assertFalse($policy->publish($unauthorized, $article));

        $this->assertTrue($policy->archive($authorized, $article));
        $this->assertFalse($policy->archive($unauthorized, $article));
    }

    public function test_content_article_policy_rejects_publish_and_archive_on_archived_article_even_for_authorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $policy = new ContentArticlePolicy;
        $archivedArticle = $this->makeArticle('ARCHIVED');

        $this->assertFalse($policy->publish($authorized, $archivedArticle));
        $this->assertFalse($policy->archive($authorized, $archivedArticle));
    }

    public function test_media_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new MediaPolicy;
        $asset = $this->makeMediaAsset();

        $this->assertTrue($policy->upload($authorized));
        $this->assertFalse($policy->upload($unauthorized));

        $this->assertTrue($policy->manage($authorized, $asset));
        $this->assertFalse($policy->manage($unauthorized, $asset));
    }
}
