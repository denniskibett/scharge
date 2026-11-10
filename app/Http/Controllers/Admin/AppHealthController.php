<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\App\AppSettings;
use App\Services\App\PushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** scharge staff › App health (docs/APP_DATA.md): app and Android versions in use, push coverage, crash reports. */
class AppHealthController extends Controller
{
    public function index(Request $request): View
    {
        $active = fn () => DB::table('mobile_sessions')->where('last_used_at', '>=', now()->subDays(30));

        return view('admin.app.index', [
            'phones' => $active()->count(),
            'withPush' => $active()->whereNotNull('push_token')->count(),
            'pushOn' => PushService::enabled(),
            'minVersion' => AppSettings::minVersion(),
            'versions' => $active()->selectRaw("COALESCE(app_version, 'not reported') v, COUNT(*) n")->groupBy('v')->orderByDesc('n')->get(),
            'os' => $active()->selectRaw("COALESCE(os_version, 'not reported') v, COUNT(*) n")->groupBy('v')->orderByDesc('n')->limit(10)->get(),
            'models' => $active()->selectRaw("COALESCE(device_model, 'not reported') v, COUNT(*) n")->groupBy('v')->orderByDesc('n')->limit(10)->get(),
            'errors' => DB::table('app_errors')->where('resolved', $request->boolean('resolved'))->orderByDesc('last_seen_at')->limit(50)->get(),
            'showResolved' => $request->boolean('resolved'),
        ]);
    }

    public function resolve(Request $request, int $id): RedirectResponse
    {
        DB::table('app_errors')->where('id', $id)->update(['resolved' => true, 'updated_at' => now()]);

        return back()->with('success', 'Marked as fixed. It reopens if it happens again.');
    }
}
