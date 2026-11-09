<?php

namespace App\Http\Middleware;

use App\Services\EmailOtp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in people whose email is not confirmed yet go to the code screen first (KYC).
 * Only while EmailOtp::active() (real mail configured). Sign-out, the code screen and API calls pass.
 */
class EnsureEmailConfirmed
{
    private const OPEN = ['email-code.*', 'logout', 'login', 'password.*', 'verification.*', 'search'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs(...self::OPEN) || ! EmailOtp::needs($user)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Confirm your email first.', 'redirect' => route('email-code.show')], 403)
            : redirect()->route('email-code.show');
    }
}
