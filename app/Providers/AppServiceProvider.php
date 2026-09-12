<?php

namespace App\Providers;

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
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }
}
