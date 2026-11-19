<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;

/**
 * Web pages of a paid module the company does not have (App\Support\Modules): a friendly "not on your plan" page
 * (JSON for fetch calls, back with a message for forms), never the generic error page. scharge staff pass.
 * The scharge app and the public API run the same check in AuthenticateMobile / AuthenticateApiKey.
 */
class CheckModule
{
    public function handle(Request $request, Closure $next)
    {
        return Modules::deny($request) ?? $next($request);
    }
}
