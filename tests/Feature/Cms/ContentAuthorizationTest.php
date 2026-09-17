<?php

namespace Tests\Feature\Cms;

use App\Enums\IdentityLifecycle;
use App\Enums\ScopeType;
use App\Enums\SecurityRestriction;
use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Permission;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Policies\ContentArticlePolicy;
use App\Policies\ContentPagePolicy;
use App\Policies\MediaPolicy;
use App\Services\Rbac\PermissionRegistry;
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

    // --- IMP005-FINAL-GATE re-audit of the MediaPolicy::update()/archive()
    // fix (section 23; IMP-003 canonical AuthorizationEvaluator semantics) ---

    public function test_media_policy_update_and_archive_allow_authorized_and_deny_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new MediaPolicy;
        $asset = $this->makeMediaAsset();

        $this->assertTrue($policy->update($authorized, $asset));
        $this->assertFalse($policy->update($unauthorized, $asset));

        $this->assertTrue($policy->archive($authorized, $asset));
        $this->assertFalse($policy->archive($unauthorized, $asset));
    }

    public function test_media_policy_update_denies_content_view_only_actor(): void
    {
        // A viewer holds content.view (and, via makeMediaAsset()'s own
        // fixture setup, nothing else) but never content.update — proves
        // update() is gated on the EDIT-plane permission specifically, not
        // merely on being able to see the asset (the bug this pass fixed:
        // the old manage() checked content.view for what section 23 assigns
        // to content.update).
        $viewer = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'media_viewer_only', 'name' => 'Media Viewer Only']);
        $permission = Permission::firstOrCreate(['code' => PermissionRegistry::CONTENT_VIEW], ['description' => 'test']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        PrincipalRoleAssignment::create([
            'principal_id' => $viewer->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
        $asset = $this->makeMediaAsset();

        $this->assertFalse((new MediaPolicy)->update($viewer, $asset));
        $this->assertFalse((new MediaPolicy)->archive($viewer, $asset));
    }

    public function test_media_policy_update_and_archive_deny_a_grant_at_a_scope_type_content_never_requests(): void
    {
        // The analog of a "cross-scope attempt" in this single-organization
        // baseline (ContentScopeResolver: "no OWN/FUNDRAISER/PARTNER/CAMPAIGN
        // scope exists for CMS resources... no further discrimination is
        // possible or needed" — there is exactly one scope dimension,
        // ORGANIZATION, so the only meaningful negative case is a grant that
        // lives entirely outside it). A grant at OWN scope (a real, populated
        // ScopeType — never ORGANIZATION or GLOBAL_PLATFORM, which are the
        // only two scope types content.* checks accept) must not satisfy
        // MediaPolicy::update()/archive()'s requested ORGANIZATION scope.
        $actor = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'media_wrong_scope', 'name' => 'Media Wrong Scope']);
        foreach ([PermissionRegistry::CONTENT_UPDATE, PermissionRegistry::CONTENT_ARCHIVE] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        }
        PrincipalRoleAssignment::create([
            'principal_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Own->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
        $asset = $this->makeMediaAsset();

        $this->assertFalse((new MediaPolicy)->update($actor, $asset));
        $this->assertFalse((new MediaPolicy)->archive($actor, $asset));
    }

    public function test_media_policy_update_and_archive_deny_a_security_restricted_principal_even_with_valid_permission(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $asset = $this->makeMediaAsset();
        $this->assertTrue((new MediaPolicy)->update($authorized, $asset), 'sanity: grant works before restriction');

        $authorized->humanUser->forceFill(['security_restriction' => SecurityRestriction::Suspended])->save();

        $this->assertFalse((new MediaPolicy)->update($authorized, $asset));
        $this->assertFalse((new MediaPolicy)->archive($authorized, $asset));
    }

    public function test_media_policy_update_and_archive_deny_a_disabled_identity_even_with_valid_permission(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $asset = $this->makeMediaAsset();

        $authorized->humanUser->forceFill(['lifecycle_state' => IdentityLifecycle::Disabled])->save();

        $this->assertFalse((new MediaPolicy)->update($authorized, $asset));
        $this->assertFalse((new MediaPolicy)->archive($authorized, $asset));
    }
}
