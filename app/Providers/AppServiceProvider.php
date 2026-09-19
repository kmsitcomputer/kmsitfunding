<?php

namespace App\Providers;

use App\Services\Audit\AuditEventRegistry;
use App\Services\Audit\AuditScopeResolver;
use App\Services\Audit\CorrelationContext;
use App\Services\Campaign\CampaignAuditEventRegistrar;
use App\Services\Campaign\CampaignScopeResolver;
use App\Services\Campaign\FundScopeResolver;
use App\Services\Campaign\ProgramScopeResolver;
use App\Services\Content\ContentAuditEventRegistrar;
use App\Services\Content\ContentScopeResolver;
use App\Services\Donation\DonationAuditEventRegistrar;
use App\Services\Donation\DonationScopeResolver;
use App\Services\Donation\RecurringPlanScopeResolver;
use App\Services\Rbac\OwnUserScopeResolver;
use App\Services\Rbac\ScopeResolverRegistry;
use App\Services\Theme\ThemeAuditEventRegistrar;
use App\Services\Theme\ThemeScopeResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Google2FA::class);

        // IMP-003: only currently-implemented ScopeResolvers are registered
        // — a concrete scope type with no resolver here is rejected at
        // write time (fail-closed), never silently allowed
        // (`IMP003-IMPL-M02`). A future domain stage adds its own resolver.
        $this->app->singleton(ScopeResolverRegistry::class, fn () => new ScopeResolverRegistry([
            new OwnUserScopeResolver,
            // IMP-004: audit-read scoping — every current canonical audit
            // event is registry-classified at GLOBAL_PLATFORM scope.
            new AuditScopeResolver,
            // IMP-005: CMS content scoping — organization-owned, single-org
            // platform, scope_id always NULL.
            new ContentScopeResolver,
            // IMP-006: Theme Engine scoping — identical shape to
            // ContentScopeResolver.
            new ThemeScopeResolver,
            // IMP-007: Program/Campaign/Fund scoping — identical shape to
            // ContentScopeResolver/ThemeScopeResolver (see
            // docs/implementation/IMP-007-campaign-program-fund.md section 6
            // for why ORGANIZATION, not the locked Program/Campaign/Fund
            // ScopeType values, is used in v1).
            new ProgramScopeResolver,
            new CampaignScopeResolver,
            new FundScopeResolver,
            // IMP-008: Donation/Recurring-Plan ORGANIZATION scoping —
            // identical shape to ContentScopeResolver/ThemeScopeResolver.
            // (The donor OWN path uses DonationOwnScopeResolver, which is
            // instantiated directly by the Policies — like every domain
            // resolver — and is deliberately NOT registered here: the
            // registry is keyed by ScopeType and OWN already maps to
            // OwnUserScopeResolver, which must keep its User-resource
            // semantics for any other consumer.)
            new DonationScopeResolver,
            new RecurringPlanScopeResolver,
        ]));

        // IMP-004: canonical audit event registry + correlation foundation,
        // request-scoped singletons (mirroring AssuranceService resolution).
        // IMP-005: the 20 content.* events are registered here via the
        // registry's own public register() method — AuditEventRegistry's
        // own file (its registerCanonicalEvents()) is never touched.
        // IMP-006: the 12 theme.* events are registered the same way.
        // IMP-007: the program.*/campaign.*/fund.* events are registered
        // the same way.
        // IMP-008: the donation.* events are registered the same way.
        $this->app->singleton(AuditEventRegistry::class, function () {
            $registry = new AuditEventRegistry;
            (new ContentAuditEventRegistrar)->register($registry);
            (new ThemeAuditEventRegistrar)->register($registry);
            (new CampaignAuditEventRegistrar)->register($registry);
            (new DonationAuditEventRegistrar)->register($registry);

            return $registry;
        });
        $this->app->singleton(CorrelationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }
}
