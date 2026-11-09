<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\RealEmail;
use App\Services\EmailOtp;
use App\Services\KycLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The "confirm your email" screen: enter the emailed code, send another, or fix a wrong address. */
class EmailCodeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (! EmailOtp::needs($user)) {
            return redirect()->intended(route('dashboard'));
        }
        // First visit: send a code straight away.
        if (EmailOtp::wait($user) === 0 && ! $request->session()->has('email_code_sent')) {
            $r = EmailOtp::send($user);
            $request->session()->put('email_code_sent', true);
            session()->flash($r['ok'] ? 'status' : 'error', $r['message']);
        }

        return view('auth.email-code', ['email' => $user->email, 'wait' => EmailOtp::wait($user), 'digits' => (int) config('kyc.email_otp.digits', 6)]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $r = EmailOtp::verify($request->user(), (string) $request->input('code'));
        if (! $r['ok']) {
            return back()->withErrors(['code' => $r['message']]);
        }
        $request->session()->forget('email_code_sent');

        return redirect()->intended(route('dashboard'))->with('status', $r['message']);
    }

    public function resend(Request $request)
    {
        $r = EmailOtp::send($request->user());

        return back()->with($r['ok'] ? 'status' : 'error', $r['message']);
    }

    /** Wrong address on the account: change it and send the code there. */
    public function change(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['email' => ['required', 'email', 'max:190', new RealEmail, Rule::unique('users', 'email')->ignore($user->id)]]);
        KycLog::record('email.changed', $user, ['from' => $user->email, 'email' => strtolower($data['email'])]);
        $user->forceFill(['email' => strtolower($data['email']), 'email_confirmed_at' => null])->save();
        $r = EmailOtp::send($user, force: true);

        return back()->with($r['ok'] ? 'status' : 'error', $r['message']);
    }
}
