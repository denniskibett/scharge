<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pages for the people who run a property, not for its residents. A tenant holds security.view so they can
 * issue gate passes (/gate), but the guard's log, reports and lookups under /security show every resident's
 * visitors and contacts. A user whose only roles are tenant / guest is turned away; staff are not affected.
 */
class StaffOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->getRoleNames()->diff(['tenant', 'guest'])->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This is for property staff.'], 403);
            }
            abort(403, 'This is for property staff. Your gate passes are under Gate.');
        }

        return $next($request);
    }
}
