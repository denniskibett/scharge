<?php

namespace App\Services\Analytics;

use App\Support\SiteAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Records sign-ins and failed attempts with their approximate location (docs/SIGN_IN_LOCATIONS.md). Never breaks a sign-in. */
class SignIns
{
    public function __construct(private GeoIp $geo)
    {
    }

    public function record(?object $user, Request $request, string $channel, ?string $method = null, bool $success = true): void
    {
        try {
            if (! Schema::hasTable('login_events')) {
                return;
            }
            $ua = (string) $request->userAgent();
            DB::table('login_events')->insert([
                'user_id' => $user?->id,
                'company_id' => $user?->company_id,
                'role' => $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() : null,
                'success' => $success,
                'channel' => $channel,
                'method' => $method,
                'ip' => $request->ip(),
                'device' => $channel === 'web' ? SiteAnalytics::device($ua, 0) : 'mobile',
                'browser' => $channel === 'web' ? SiteAnalytics::browser($ua) : ($request->input('platform') === 'ios' ? 'iOS app' : 'Android app'),
                'created_at' => now(),
            ] + $this->geo->locate($request->ip(), $request->header('CF-IPCountry')));
        } catch (\Throwable $e) {
            Log::warning('Sign-in not recorded', ['error' => $e->getMessage()]);
        }
    }
}
