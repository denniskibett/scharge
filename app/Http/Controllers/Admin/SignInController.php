<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\GeoIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * scharge staff › Sign-in locations (docs/SIGN_IN_LOCATIONS.md): where people sign in from (country, city), on which
 * channel (website, app, reader app), by company and role, and failed attempts. Sysadmins see everyone; account
 * managers see the companies they own.
 */
class SignInController extends Controller
{
    public const RANGES = [1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days', 180 => '180 days'];

    public const CHANNELS = ['web' => 'Website', 'app' => 'Mobile app', 'reader_app' => 'Reader app'];

    public function index(Request $request): View
    {
        $days = array_key_exists((int) $request->query('days'), self::RANGES) ? (int) $request->query('days') : 30;
        $channel = array_key_exists((string) $request->query('channel'), self::CHANNELS) ? $request->query('channel') : null;
        $companyId = $request->integer('company') ?: null;

        $base = fn () => $this->scope($request, DB::table('login_events as l'))
            ->where('l.created_at', '>=', now()->subDays($days)->startOfDay())
            ->when($channel, fn ($q) => $q->where('l.channel', $channel))
            ->when($companyId, fn ($q) => $q->where('l.company_id', $companyId));
        $ok = fn () => $base()->where('l.success', true);

        return view('admin.sign-ins.index', [
            'days' => $days, 'channel' => $channel, 'companyId' => $companyId,
            'totals' => [
                'sign_ins' => $ok()->count(),
                'people' => $ok()->distinct()->count('l.user_id'),
                'countries' => $ok()->whereNotNull('l.country_code')->distinct()->count('l.country_code'),
                'failed' => $base()->where('l.success', false)->count(),
                'unknown' => $ok()->whereNull('l.country_code')->count(),
            ],
            'countries' => $ok()->selectRaw("COALESCE(l.country, l.country_code, 'Unknown') as place, COUNT(*) as n, COUNT(DISTINCT l.user_id) as people")
                ->groupBy('place')->orderByDesc('n')->limit(20)->get(),
            'cities' => $ok()->whereNotNull('l.city')->selectRaw("l.city, l.region, l.country_code, COUNT(*) as n, COUNT(DISTINCT l.user_id) as people")
                ->groupBy('l.city', 'l.region', 'l.country_code')->orderByDesc('n')->limit(25)->get(),
            'companies' => $ok()->leftJoin('companies as c', 'c.id', '=', 'l.company_id')
                ->selectRaw("COALESCE(c.name, 'No company (tenants, leads)') as name, l.company_id, COUNT(*) as n, COUNT(DISTINCT l.user_id) as people, COUNT(DISTINCT l.city) as cities")
                ->groupBy('l.company_id', 'c.name')->orderByDesc('n')->limit(25)->get(),
            'channelCounts' => $ok()->selectRaw('l.channel, COUNT(*) as n')->groupBy('l.channel')->pluck('n', 'channel'),
            'failedByPlace' => $base()->where('l.success', false)->selectRaw("COALESCE(l.country, l.country_code, 'Unknown') as place, COUNT(*) as n, COUNT(DISTINCT l.ip) as ips")
                ->groupBy('place')->orderByDesc('n')->limit(10)->get(),
            'recent' => $base()->leftJoin('users as u', 'u.id', '=', 'l.user_id')->leftJoin('companies as c', 'c.id', '=', 'l.company_id')
                ->select('l.*', 'u.name as user_name', 'c.name as company_name')->orderByDesc('l.id')->limit(50)->get(),
            'companyOptions' => $this->scope($request, DB::table('login_events as l'))->join('companies as c', 'c.id', '=', 'l.company_id')
                ->select('c.id', 'c.name')->distinct()->orderBy('c.name')->get(),
            'geoReady' => is_file(GeoIp::path()),
        ]);
    }

    private function scope(Request $request, $query)
    {
        $user = $request->user();

        return $user->hasRole('sysadmin') ? $query
            : $query->whereIn('l.company_id', DB::table('companies')->where('owner_id', $user->id)->select('id'));
    }
}
