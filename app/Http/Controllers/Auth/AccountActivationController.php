<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\EmailOtp;
use App\Services\Expenses\BusinessBooks;
use App\Services\KycLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-serve activation for someone who signed up on their own (no business yet): once their email is confirmed
 * they name their business and its kind, it is created (or, when the CRM already has it as a prospect, converted
 * to a customer, keeping its account manager) and they become its admin (a short-stay host gets the bnb_host role).
 */
class AccountActivationController extends Controller
{
    public const KINDS = ['manager' => 'Property / building manager', 'bnb_host' => 'Airbnb / short-stay host', 'developer' => 'Developer'];

    public static function emailConfirmed($user): bool
    {
        return filled($user->email_confirmed_at) || (filled($user->email_verified_at) && ! EmailOtp::active());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->company_id || $user->hasAnyRole(['sysadmin', 'account_manager'])) {
            return redirect()->route('dashboard');
        }
        if (! self::emailConfirmed($user)) {
            return redirect()->route('email-code.show')->with('error', 'Confirm your email first. We sent you a code.');
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'kind' => ['required', 'in:'.implode(',', array_keys(self::KINDS))],
            'phone' => ['required', 'string', 'regex:/^(\+?254|0)?[17]\d{8}$/'],
        ], ['phone.regex' => 'Enter a Kenyan mobile number, e.g. 0712 345 678.']);

        $name = trim(preg_replace('/\s+/', ' ', $data['business_name']));
        $phone = '254'.substr(preg_replace('/\D/', '', $data['phone']), -9);
        $all = Company::withoutGlobalScope('customers');
        $same = (clone $all)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($same && ($same->lifecycle ?? 'customer') !== 'prospect') {
            return back()->withInput()->withErrors(['business_name' => "{$name} already uses scharge. Ask its admin to add you to the team."]);
        }

        $company = DB::transaction(function () use ($user, $same, $name, $data, $phone) {
            $fields = ['name' => $name, 'email' => $user->email, 'phone' => $phone, 'is_active' => true];
            $extra = array_filter([
                'kind' => $data['kind'],
                'lifecycle' => 'customer',
                'source' => $same ? null : 'signup',
            ], fn ($v, $col) => $v !== null && Schema::hasColumn('companies', $col), ARRAY_FILTER_USE_BOTH);

            if ($same) {   // the CRM already had them as a prospect: convert, keep its account manager
                $same->forceFill(array_merge(['is_active' => true, 'email' => $same->email ?: $user->email, 'phone' => $same->phone ?: $phone], $extra))->save();
                $company = $same;
            } else {
                $company = (new Company)->forceFill(array_merge($fields, $extra));
                $company->save();
            }

            // Short-stay hosts get the host role (their own dashboard); everyone else is their business's admin.
            $role = \Spatie\Permission\Models\Role::findOrCreate($data['kind'] === 'bnb_host' ? 'bnb_host' : 'admin', 'web');
            $user->forceFill(array_filter([
                'company_id' => $company->id,
                'role_id' => Schema::hasColumn('users', 'role_id') ? $role->id : null,
                'phone' => $user->phone ?: $phone,
                'status' => 0,
                'email_verified_at' => $user->email_verified_at ?? $user->email_confirmed_at ?? now(),
            ], fn ($v) => $v !== null))->save();
            $user->syncRoles([$role->name]);

            return $company;
        });

        BusinessBooks::ensureDefaults($company->id);
        KycLog::record('account.activated', $user, ['company_id' => $company->id, 'kind' => $data['kind'], 'converted_prospect' => (bool) $same]);

        return redirect()->route('dashboard')->with('success', "Welcome to scharge. {$company->name} is ready.");
    }
}
