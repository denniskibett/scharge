<?php

namespace App\Http\Middleware;

use App\Support\Brand;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/**
 * White label (docs/WHITE_LABEL.md): shares $brand with every view, and on a company's own address
 *  - "/" (scharge's marketing site) goes to the sign-in page or the dashboard, and "Register" is not offered;
 *  - only that company's people (and scharge staff) can be signed in: anyone else is signed out.
 */
class ApplyBrand
{
    public function handle(Request $request, Closure $next)
    {
        $own = Brand::onOwnDomain();
        $brand = $own ?? Brand::current();
        View::share('brand', $brand);
        View::share('brandOwnDomain', (bool) $own);

        if ($own) {
            $user = Auth::user();
            if ($user && (int) $user->company_id !== (int) $own->company_id && ! $user->hasRole('sysadmin')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'This account is not part of '.$own->display_name.'. Sign in at scharge.sharet.africa instead.']);
            }
            if ($request->is('/')) {
                return redirect()->route($user ? 'dashboard' : 'login');
            }
            if ($request->routeIs('register', 'register.*')) {
                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
