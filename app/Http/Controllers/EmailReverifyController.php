<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reports\PortfolioReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Re-verify email": makes someone confirm their email with a fresh code at their next sign-in
 * (for accounts whose KYC email was never checked properly). Allowed for sysadmins, for people who
 * manage the team (staff) or tenants (tenants) of the same company.
 */
class EmailReverifyController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        abort_unless(Schema::hasColumn('users', 'email_confirmed_at'), 503, 'Email confirmation is not set up yet.');
        $actor = $request->user();
        abort_unless($this->allowed($actor, $user), 403, 'You cannot ask this person to re-verify.');

        $user->forceFill(['email_confirmed_at' => null])->save();
        Cache::forget('email-otp:'.$user->id);
        Log::info('Email re-verification requested', ['user' => $user->id, 'by' => $actor->id]);

        $message = "{$user->name} will be asked to confirm {$user->email} with a code the next time they sign in.";

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('success', $message);
    }

    private function allowed($actor, User $target): bool
    {
        if ($actor->hasRole('sysadmin')) {
            return true;
        }
        if ($actor->is($target)) {
            return true; // anyone may re-check their own email
        }
        $ids = PortfolioReport::companyIdsFor($actor) ?? [];
        if ($target->hasRole('tenant')) {
            $companyId = $target->company_id ?? DB::table('tenants')->where('user_id', $target->id)->value('company_id');

            return $actor->can('tenants.manage') && in_array((int) $companyId, $ids, true);
        }

        return $actor->can('team.manage') && in_array((int) $target->company_id, $ids, true) && ! $target->hasRole('sysadmin');
    }
}
