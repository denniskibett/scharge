<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $guard = \App\Services\KycLog::verdict(email: (string) $request->input('email'));
        if (! $guard['allow']) {
            \App\Services\KycLog::record('signup.blocked', null, ['email' => (string) $request->input('email'), 'reasons' => $guard['reasons'], 'form' => 'register']);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Too many failed attempts from your network. Please try again in an hour or contact support.']);
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class, new \App\Rules\RealEmail],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        \App\Services\KycLog::record('signup.ok', $user, ['form' => 'register']);
        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
