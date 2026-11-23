<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\EmailOtp;
use App\Services\Expenses\BusinessBooks;
use App\Services\KycLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Self-serve activation for someone who signed up on their own (no business yet): once their email is confirmed
 * they name their business and its kind, it is created (or, when the CRM already has it as a prospect, converted
 * to a customer, keeping its account manager) and they become its admin (a short-stay host gets the bnb_host role).
 * The sign-up form asks the same three questions (RegisteredUserController), so this happens by itself once the
 * email is confirmed (finishSignup); the pending page form is the fallback.
 */
class AccountActivationController extends Controller
{
    public const KINDS = ['manager' => 'Property / building manager', 'bnb_host' => 'Airbnb / short-stay host', 'developer' => 'Developer', 'agency' => 'Agent / agency (I market and let homes for landlords)'];

    public static function emailConfirmed($user): bool
    {
        return filled($user->email_confirmed_at) || (filled($user->email_verified_at) && ! EmailOtp::active());
    }

    /** The business questions asked at sign-up and on the pending page. */
    public static function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'kind' => ['required', 'in:'.implode(',', array_keys(self::KINDS))],
            'phone' => ['required', 'string', 'regex:/^(\+?254|0)?[17]\d{8}$/'],
        ];
    }

    public const MESSAGES = ['phone.regex' => 'Enter a Kenyan mobile number, e.g. 0712 345 678.'];

    public static function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /** The CRM prospect or customer already using this business name, if any. */
    public static function existing(string $name): ?Company
    {
        return Company::withoutGlobalScope('customers')->whereRaw('LOWER(name) = ?', [mb_strtolower(self::cleanName($name))])->first();
    }

    /** Null when the name is free (or only a CRM prospect); otherwise the message for the form. */
    public static function nameTaken(string $name): ?string
    {
        $same = self::existing($name);

        return $same && ($same->lifecycle ?? 'customer') !== 'prospect'
            ? self::cleanName($name).' already uses scharge. Ask its admin to add you to the team.' : null;
    }

    /**
     * The business details given on the sign-up form wait here until the email is confirmed (no column for them
     * on users). Kept 30 days; the pending page asks again if they are gone.
     */
    public static function remember($user, array $data): void
    {
        Cache::put('signup-business:'.$user->id, Arr::only($data, ['business_name', 'kind', 'phone']), now()->addDays(30));
    }

    public static function remembered($user): ?array
    {
        $data = Cache::get('signup-business:'.$user->id);

        return is_array($data) && ! Validator::make($data, self::rules())->fails() ? $data : null;
    }

    /**
     * Sets up the business from the details given at sign-up, as soon as the email is confirmed, so nobody lands
     * on an empty dashboard. Returns the company, or null when there is nothing to do yet.
     */
    public static function finishSignup($user): ?Company
    {
        if (! $user || $user->company_id || $user->hasAnyRole(['sysadmin', 'account_manager']) || ! self::emailConfirmed($user)) {
            return null;
        }
        $data = self::remembered($user);
        if (! $data || self::nameTaken($data['business_name'])) {
            return null;   // the pending page asks (again), with the name pre-filled
        }
        try {
            $company = self::activate($user, $data);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        Cache::forget('signup-business:'.$user->id);

        return $company;
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

        $data = $request->validate(self::rules(), self::MESSAGES);
        if ($taken = self::nameTaken($data['business_name'])) {
            return back()->withInput()->withErrors(['business_name' => $taken]);
        }
        $company = self::activate($user, $data);
        Cache::forget('signup-business:'.$user->id);

        return redirect()->route('dashboard')->with('success', "Welcome to scharge. {$company->name} is ready.");
    }

    /** Creates the business (or converts the CRM prospect of the same name) and makes this user its admin. */
    public static function activate($user, array $data): Company
    {
        $name = self::cleanName($data['business_name']);
        $phone = '254'.substr(preg_replace('/\D/', '', $data['phone']), -9);
        $same = self::existing($name);

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
        // Every new customer has an account manager from the first minute, with a welcome call on their calendar.
        try {
            \App\Modules\CRM\Support\Onboarding::welcome($company, $user);
        } catch (\Throwable $e) {
            report($e);
        }

        return $company;
    }
}
