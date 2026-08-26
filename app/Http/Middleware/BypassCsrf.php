<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BypassCsrf
{
    /**
     * Handle an incoming request.
     *
     * This middleware completely bypasses CSRF protection for routes it's applied to.
     * It should ONLY be used for external callback URLs like M-Pesa callbacks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // This middleware bypasses CSRF protection
        // It should ONLY be used for external callback URLs
        return $next($request);
    }
}