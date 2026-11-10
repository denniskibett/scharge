<?php

namespace App\Http\Middleware;

use App\Models\MobileSession;
use App\Support\CompanyAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * scharge app: "Authorization: Bearer mob_…" (one per phone, App\Models\MobileSession). Signs the person in for this
 * request only, so the same rules as the dashboard apply: the company scopes (CompanyAccess) and Spatie permissions.
 */
class AuthenticateMobile
{
    public function handle(Request $request, Closure $next)
    {
        $session = MobileSession::findByPlain($request->bearerToken());
        $user = $session?->user;

        if (! $session || ! $user) {
            return response()->json(['error' => 'signed_out', 'message' => 'Please sign in again.'], 401);
        }
        if ((int) $user->status === 1) { // 1 = inactive
            $session->delete();

            return response()->json(['error' => 'account_inactive', 'message' => 'This account is switched off. Contact your admin.'], 401);
        }

        Auth::setUser($user);
        CompanyAccess::flush();
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile_session', $session);

        // The app sends its version, Android/iOS version and phone model on every call (docs/APP_DATA.md).
        $device = array_filter(['app_version' => mb_substr((string) $request->header('X-App-Version'), 0, 20),
            'os_version' => mb_substr((string) $request->header('X-OS-Version'), 0, 40), 'device_model' => mb_substr((string) $request->header('X-Device-Model'), 0, 80)]);
        $changed = collect($device)->contains(fn ($v, $k) => $session->{$k} !== $v);
        if ($changed || ! $session->last_used_at || $session->last_used_at->lt(now()->subMinute())) {
            $session->forceFill(['last_used_at' => now(), 'last_ip' => $request->ip()] + $device)->saveQuietly();
        }
        if (\App\Services\App\AppSettings::tooOld($device['app_version'] ?? null)) {
            return response()->json(['error' => 'update_required', 'message' => 'Please update the scharge app to keep using it.',
                'min_version' => \App\Services\App\AppSettings::minVersion(), 'download_url' => url('/downloads/scharge-app.apk')], 426);
        }

        return $next($request);
    }
}
