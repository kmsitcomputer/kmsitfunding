<?php

namespace Tests\Feature\Theme;

use App\Services\Campaign\CampaignLifecycleService;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\FundService;
use App\Services\Campaign\ProgramService;
use App\Services\Content\PageService;
use App\Services\Content\PublicationService;
use App\Services\Theme\ThemeActivationService;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 targeted content-projection amendment (Human change control —
 * see docs/adr/ADR-001-theme-content-projection-amendment.md and
 * docs/decisions/ACR-001-theme-content-projection.md). Proves the locked
 * Theme Engine's content_list component can consume IMP-007's Program/
 * Campaign canonical read projections without IMP-006 ever querying
 * `programs`/`campaigns` directly and without duplicating HD-IMP007-03's
 * eligibility calculation.
 */
class ThemeCampaignProjectionTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function activateHomeTemplateWithContentList(string $contentKind, array $overrides = []): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'Custom'], $actor);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'content_list', array_merge([
            'content_kind' => $contentKind, 'limit' => 6, 'order' => 'latest',
        ], $overrides), $actor);
        app(ThemeActivationService::class)->activate($theme, $actor);
    }

    public function test_content_list_renders_a_published_program(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'Education for All', 'summary' => 'Helping children learn.'], $actor);
        app(ProgramService::class)->publish($program, $actor);

        $this->activateHomeTemplateWithContentList('program');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p
            ->where('template.sections.0.components.0.props.0.title', 'Education for All')
            ->where('template.sections.0.components.0.props.0.summary', 'Helping children learn.')
            ->where('template.sections.0.components.0.props.0.url', url("/programs/{$program->slug}"))
        );
    }

    public function test_content_list_returns_an_empty_array_when_no_programs_are_published(): void
    {
        $this->activateHomeTemplateWithContentList('program');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->where('template.sections.0.components.0.props', []));
    }

    public function test_content_list_renders_a_published_campaign_with_normalized_projection(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create([
            'name' => 'Emergency Relief', 'summary' => 'Support families in need.',
            'target_amount_minor' => 10000000, 'currency' => 'IDR',
        ], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        app(CampaignLifecycleService::class)->submit($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->approve($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->publish($campaign->fresh(), $actor);

        $this->activateHomeTemplateWithContentList('campaign');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p
            ->where('template.sections.0.components.0.props.0.title', 'Emergency Relief')
            ->where('template.sections.0.components.0.props.0.metadata.formatted_target_amount', 'IDR 100.000,00')
            ->where('template.sections.0.components.0.props.0.metadata.is_donation_eligible', true)
        );
    }

    public function test_content_list_returns_an_empty_array_when_no_campaigns_are_published(): void
    {
        $this->activateHomeTemplateWithContentList('campaign');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->where('template.sections.0.components.0.props', []));
    }

    public function test_content_list_excludes_an_unpublished_campaign(): void
    {
        $actor = $this->makeUnauthorizedActor();
        app(CampaignService::class)->create(['name' => 'Still Draft'], $actor);

        $this->activateHomeTemplateWithContentList('campaign');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->where('template.sections.0.components.0.props', []));
    }

    /**
     * HD-IMP007-03: a PUBLISHED campaign outside its period still appears
     * (visibility is status-only) but with is_donation_eligible=false —
     * the canonical CampaignEligibilityResolver decides this, never a
     * date calculation duplicated in Theme code.
     */
    public function test_content_list_shows_a_published_campaign_that_has_not_started_yet_as_not_eligible(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'Future Campaign', 'starts_at' => now()->addDays(10)->toDateTimeString()], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        app(CampaignLifecycleService::class)->submit($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->approve($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->publish($campaign->fresh(), $actor);

        $this->activateHomeTemplateWithContentList('campaign');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p
            ->where('template.sections.0.components.0.props.0.title', 'Future Campaign')
            ->where('template.sections.0.components.0.props.0.metadata.is_donation_eligible', false)
        );
    }

    public function test_content_list_shows_a_published_campaign_that_has_ended_as_not_eligible(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'Ended Campaign', 'ends_at' => now()->subDays(5)->toDateTimeString()], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        app(CampaignLifecycleService::class)->submit($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->approve($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->publish($campaign->fresh(), $actor);

        $this->activateHomeTemplateWithContentList('campaign');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p
            ->where('template.sections.0.components.0.props.0.title', 'Ended Campaign')
            ->where('template.sections.0.components.0.props.0.metadata.is_donation_eligible', false)
        );
    }

    public function test_campaign_projection_url_is_a_safe_same_origin_link(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'Safe URL Campaign'], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        app(CampaignLifecycleService::class)->submit($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->approve($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->publish($campaign->fresh(), $actor);

        $this->activateHomeTemplateWithContentList('campaign');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(function (Assert $p) {
            $p->where('template.sections.0.components.0.props.0.url', url('/campaigns/safe-url-campaign'));
        });
    }

    /**
     * Regression: the original page/article content_list branch (IMP-006
     * locked, unchanged) still resolves exactly as before this amendment.
     */
    public function test_content_list_page_regression_unaffected_by_the_amendment(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $page = app(PageService::class)->create(['title' => 'Regression Page', 'body_html' => '<p>x</p>'], $actor);
        app(PublicationService::class)->publish($page->fresh(), $page->fresh()->currentDraft, $actor, '/regression-page');

        $this->activateHomeTemplateWithContentList('page');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p
            ->where('template.sections.0.components.0.props.0.title', 'Regression Page')
            ->where('template.sections.0.components.0.props.0.url', '/regression-page')
        );
    }
}
