<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin › Companies › {company} › Modules: sysadmins switch a paid module on or off for one company (optionally until
 * a date) or hand it back to the plan; and set which modules a plan includes. Every change is logged.
 */
class CompanyModuleController extends Controller
{
    public function update(Request $request, int $company, string $module)
    {
        abort_unless(Modules::installed(), 503, 'Run database/schema/2026_11_20_company_modules.sql first.');
        abort_unless(DB::table('companies')->where('id', $company)->exists(), 404);
        abort_unless(in_array($module, Modules::paid(), true), 404);
        $data = $request->validate([
            'state' => 'required|in:plan,on,off',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'note' => 'nullable|string|max:255',
        ]);

        $state = Modules::set($company, $module, $data['state'], $data['expires_at'] ?? null, $data['note'] ?? null, $request->user()->id);

        return response()->json(['ok' => true, 'module' => $module, 'state' => self::present($module, $state),
            'log' => self::logs($company, 1)[0] ?? null,
            'message' => Modules::label($module).': '.($state['on'] ? 'on' : 'off').($state['source'] === 'plan' ? ' (plan default)' : '').'.']);
    }

    /** Billing lock grace: until a date (or end it now with no date). */
    public function grace(Request $request, int $company)
    {
        abort_unless(Modules::installed(), 503, 'Run database/schema/2026_11_20_company_modules.sql first.');
        abort_unless(DB::table('companies')->where('id', $company)->exists(), 404);
        $data = $request->validate(['until' => 'nullable|date|after_or_equal:today', 'note' => 'nullable|string|max:255']);
        \App\Support\BillingLock::setGrace($company, $data['until'] ?? null, $data['note'] ?? null, $request->user()->id);

        return response()->json(['ok' => true, 'billing' => \App\Support\BillingLock::status($company), 'log' => self::logs($company, 1)[0] ?? null,
            'message' => ($data['until'] ?? null) ? 'Grace until '.\Illuminate\Support\Carbon::parse($data['until'])->format('j M Y').'.' : 'Grace ended.']);
    }

    /** Price per unit for the company's first bill (App\Support\FirstBill). Empty clears it. Logged like module changes. */
    public function price(Request $request, int $company)
    {
        abort_unless(\App\Support\FirstBill::installed(), 503, 'Run database/schema/2026_11_23_first_bill.sql first.');
        $before = DB::table('companies')->where('id', $company)->value('price_per_unit');
        abort_unless(DB::table('companies')->where('id', $company)->exists(), 404);
        $data = $request->validate(['price' => 'nullable|numeric|min:1|max:100000']);
        $price = isset($data['price']) ? round((float) $data['price'], 2) : null;
        $status = \App\Support\FirstBill::setPrice($company, $price, $request->user()->id);
        $log = null;
        if (Modules::installed()) {
            DB::table('company_module_logs')->insert(['company_id' => $company, 'module' => 'price_per_unit',
                'from_state' => $before === null ? 'none' : (string) (float) $before, 'to_state' => $price === null ? 'none' : (string) $price,
                'note' => $price === null ? 'Price cleared' : 'Price per unit KES '.number_format($price, 2), 'user_id' => $request->user()->id, 'created_at' => now()]);
            $log = self::logs($company, 1)[0] ?? null;
        }

        return response()->json(['ok' => true, 'first_bill' => $status, 'log' => $log,
            'message' => $price === null ? 'Price cleared.' : 'Price saved: KES '.number_format($price, 2).' per unit. '.$status['note']]);
    }

    /** The plan's module list: "all" (no list: every module) or the keys ticked. */
    public function plan(Request $request, int $plan)
    {
        $data = $request->validate(['all' => 'boolean', 'modules' => 'array', 'modules.*' => 'string']);
        $row = DB::table('subscription_plans')->where('id', $plan)->first(['id', 'name', 'features']);
        abort_unless($row, 404);
        $features = $row->features;
        for ($i = 0; is_string($features) && $i < 2; $i++) {
            $features = json_decode($features, true);
        }
        $features = is_array($features) ? $features : [];
        $before = array_key_exists('modules', $features) ? implode(',', (array) $features['modules']) : 'all';
        if (! empty($data['all'])) {
            unset($features['modules']);
        } else {
            $features['modules'] = array_values(array_intersect(Modules::paid(), $data['modules'] ?? []));
        }
        $after = array_key_exists('modules', $features) ? implode(',', $features['modules']) : 'all';
        DB::table('subscription_plans')->where('id', $plan)->update(['features' => json_encode($features), 'updated_at' => now()]);
        if (Modules::installed() && $before !== $after) {
            DB::table('company_module_logs')->insert(['company_id' => 0, 'module' => 'plan:'.$plan, 'from_state' => mb_substr($before, 0, 20),
                'to_state' => mb_substr($after, 0, 20), 'note' => mb_substr('Plan "'.$row->name.'": '.$after, 0, 255), 'user_id' => $request->user()->id, 'created_at' => now()]);
        }
        Modules::flush();

        return response()->json(['ok' => true, 'modules' => $features['modules'] ?? null, 'message' => 'Plan modules saved.']);
    }

    /** A module row for the Modules tab. */
    public static function present(string $key, array $s): array
    {
        $by = $s['changed_by'] ? DB::table('users')->where('id', $s['changed_by'])->value('name') : null;

        return [
            'key' => $key, 'label' => Modules::label($key), 'blurb' => Modules::CATALOGUE[$key]['blurb'] ?? '',
            'on' => $s['on'], 'source' => $s['source'], 'plan' => $s['plan'],
            'state' => $s['source'] === 'override' ? ($s['on'] ? 'on' : 'off') : 'plan',
            'expires_at' => $s['source'] === 'override' && $s['expires_at'] ? substr((string) $s['expires_at'], 0, 10) : '',
            'expired' => $s['expired'],
            'changed' => $s['changed_at'] ? trim(($by ?: 'Someone').' · '.\Illuminate\Support\Carbon::parse($s['changed_at'])->format('j M Y, H:i')) : '',
            'note' => $s['note'] ?? '',
        ];
    }

    /** Latest changes for a company. */
    public static function logs(int $company, int $limit = 30): array
    {
        if (! Modules::installed()) {
            return [];
        }

        return DB::table('company_module_logs')->leftJoin('users', 'users.id', '=', 'company_module_logs.user_id')
            ->where('company_module_logs.company_id', $company)->orderByDesc('company_module_logs.id')->limit($limit)
            ->get(['company_module_logs.*', 'users.name as user_name'])
            ->map(fn ($l) => [
                'at' => \Illuminate\Support\Carbon::parse($l->created_at)->format('j M Y, H:i'),
                'who' => $l->user_name ?: 'Someone',
                'module' => $l->module === \App\Support\BillingLock::GRACE ? 'Billing grace' : Modules::label($l->module),
                'change' => ['plan' => 'Plan default', 'on' => 'On', 'off' => 'Off'][$l->from_state] ?? $l->from_state,
                'to' => (['plan' => 'Plan default', 'on' => 'On', 'off' => 'Off'][$l->to_state] ?? $l->to_state)
                    .($l->expires_at ? ' until '.\Illuminate\Support\Carbon::parse($l->expires_at)->format('j M Y') : ''),
                'note' => $l->note ?? '',
            ])->all();
    }
}
