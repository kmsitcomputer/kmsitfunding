<?php

namespace App\Providers;

use App\Services\Audit\AuditEventRegistry;
use App\Services\Audit\AuditScopeResolver;
use App\Services\Audit\CorrelationContext;
use App\Services\Rbac\OwnUserScopeResolver;
use App\Services\Rbac\ScopeResolverRegistry;
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
        ]));

        // IMP-004: canonical audit event registry + correlation foundation,
        // request-scoped singletons (mirroring AssuranceService resolution).
        $this->app->singleton(AuditEventRegistry::class);
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
