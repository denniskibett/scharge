<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

/**
 * scharge API: "Authorization: Bearer sk_…". Use as `api.key` or `api.key:collect` to require a scope.
 * A key acts for its company only.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $scope = null)
    {
        $key = ApiKey::findByPlain($request->bearerToken());

        if (! $key || ! $key->enabled || ! $key->company_id) {
            return response()->json(['error' => 'invalid_api_key', 'message' => 'Send a valid, enabled key as "Authorization: Bearer sk_…".'], 401);
        }
        if (! $key->ipAllowed($request->ip())) {
            return response()->json(['error' => 'ip_not_allowed', 'message' => 'This key cannot be used from '.$request->ip().'.'], 403);
        }
        if ($scope && ! $key->allows($scope)) {
            return response()->json(['error' => 'insufficient_scope', 'message' => "This key needs the \"{$scope}\" scope."], 403);
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
