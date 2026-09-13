<?php

namespace App\Http\Middleware;

use App\Services\Identity\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Q24 — if an authenticated identity's Identity Lifecycle or Security
 * Restriction changes to DISABLED/SUSPENDED mid-session, this middleware
 * denies further access rather than trusting a stale authenticated session.
 */
class EnsureIdentityIsActive
{
    public function __construct(private readonly AuthenticationService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAuthenticate()) {
            $this->auth->logout($request);

            abort(403, 'This account is not currently available.');
        }

        return $next($request);
    }
}
