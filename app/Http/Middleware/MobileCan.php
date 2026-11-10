<?php

namespace App\Http\Middleware;

use App\Support\MobileAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * scharge app routes: `MobileCan::class.':staff,invoices.view'`. "staff" = company or scharge staff (never a tenant
 * acting on company data); "tenant" = a tenant on their own records. The permission comes from App\Support\Access
 * through Spatie (MobileAccess::can), and fails closed.
 */
class MobileCan
{
    public function handle(Request $request, Closure $next, string $who, ?string $permission = null)
    {
        $user = $request->user();
        $ok = match ($who) {
            'staff' => MobileAccess::isStaff($user),
            'tenant' => MobileAccess::isTenant($user),
            default => false,
        };
        if ($ok && $permission) {
            $ok = MobileAccess::can($user, $permission);
        }

        return $ok ? $next($request) : response()->json(['error' => 'not_allowed',
            'message' => 'Your role does not allow this. Ask your admin to change your access.', 'needs' => $permission], 403);
    }
}
