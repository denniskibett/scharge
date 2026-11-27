<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Services\DemoOnboarding;
use App\Modules\CRM\Support\SetupMode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

/** Done-for-you onboarding after a demo: set the account up, the client's welcome link, open full access. */
class SetupController extends Controller
{
    /** Signed 14-day link from the "account ready" email: a fresh set-password link, until they have signed in once. */
    public function welcome(Request $request, User $user)
    {
        $signedIn = Schema::hasTable('login_events') && DB::table('login_events')->where('user_id', $user->id)->where('success', true)->exists();
        if ($signedIn || ! $user->email) {
            return redirect()->route('login')->with('status', 'Your account is ready. Sign in with '.$user->email.', or use Forgot your password.');
        }

        return redirect()->route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
    }

    /** The client's button (signed link from the demo thank-you page and their booking email): what will be set up. */
    public function start(Request $request, int $company)
    {
        $c = $this->startable($company);
        $user = $c->profile('setup.user_id') ? User::find($c->profile('setup.user_id')) : null;

        return view('home.setup-start', ['company' => $c, 'done' => (bool) $c->profile('setup.started_at'), 'user' => $user,
            'contact' => \App\Modules\CRM\Models\CrmFollowUp::where('company_id', $c->id)->where('type', 'contact')->whereNotNull('user_id')->latest('id')->first()?->contact,
            'properties' => (array) $c->profile('prospect_properties', []), 'manager' => $c->owner, 'action' => $request->fullUrl()]);
    }

    /** The client pressed "Create my scharge account": set it up, then straight on to choosing their password. */
    public function startConfirm(Request $request, int $company, DemoOnboarding $onboarding)
    {
        $c = $this->startable($company);
        if ($c->profile('setup.started_at')) {
            $user = User::find($c->profile('setup.user_id'));

            return $user ? redirect(DemoOnboarding::welcomeUrl($user)) : redirect()->route('login');
        }
        $r = $onboarding->setUp($c, null, true);
        app(\App\Modules\CRM\Services\ContactService::class)->log($c->fresh(), 'note', 'The client created their account', 'They pressed "Create my scharge account" on their demo link.', [], null, null);
        if (! $r['user']) {
            return redirect($request->fullUrl())->with('error', 'We could not create your login: '.implode(' ', $r['problems']).' Your account manager will call you.');
        }

        return redirect(DemoOnboarding::welcomeUrl($r['user']));
    }

    private function startable(int $id): Company
    {
        $c = Company::withoutGlobalScope('customers')->find($id);
        abort_unless($c && ($c->profile('prospect_properties') || $c->profile('setup.started_at')), 404);

        return $c;
    }

    /** CRM: set the account up from the demo data and send the welcome email with the to-do PDF. */
    public function setUp(Request $request, Company $crmCompany, DemoOnboarding $onboarding)
    {
        abort_if($crmCompany->profile('setup.started_at'), 422, 'This account is already set up.');
        $r = $onboarding->setUp($crmCompany, $request->user(), ! $request->boolean('no_send'));
        $msg = 'Account set up: '.count($r['properties']).' propert'.(count($r['properties']) === 1 ? 'y' : 'ies')
            .($r['user'] ? ', login for '.$r['user']->email : ', no login').($r['sent'] ? ', welcome email and PDF sent.' : '.')
            .($r['problems'] ? ' Check: '.implode(' ', $r['problems']) : '');

        return back()->with($r['problems'] ? 'warning' : 'success', $msg);
    }

    /** CRM: setup is done, the client's team gets every page. */
    public function finish(Request $request, Company $crmCompany)
    {
        SetupMode::finish($crmCompany, $request->user()->id);
        app(\App\Modules\CRM\Services\ContactService::class)->log($crmCompany, 'note', 'Setup finished: full access opened', null, [], null, $request->user()->id);

        return back()->with('success', 'Full access opened for '.$crmCompany->name.'.');
    }

    /** CRM: download the onboarding PDF that was sent. */
    public function pdf(Company $crmCompany)
    {
        $path = $crmCompany->profile('setup.pdf');
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($path, 'scharge-onboarding-'.$crmCompany->id.'.pdf');
    }
}
