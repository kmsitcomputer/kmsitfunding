<?php

namespace App\Http\Middleware;

use App\Services\Identity\AssuranceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a sensitive route so it requires ELEVATED Authentication Assurance
 * (see "Authentication Assurance"). ELEVATED itself grants no permission —
 * this middleware only gates on the assurance state, never on authorization.
 */
class RequireElevatedAssurance
{
    public function __construct(private readonly AssuranceService $assurance) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->assurance->isElevated()) {
            abort(403, 'This action requires a recent credential confirmation.');
        }

        return $next($request);
    }
}
