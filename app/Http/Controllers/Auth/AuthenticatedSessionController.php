<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
            $request->session()->regenerate();

            $user = Auth::user();
            $role = 'guest';

            try {
                if ($user && method_exists($user, 'roles') && $user->roles->isNotEmpty()) {
                    $role = $user->roles->first()->name ?? 'guest';
                }
            } catch (\Exception $e) {
                $role = 'guest';
            }

            switch ($role) {
                case 'meter_reader':
                    return redirect()->route('water.index');
                case 'accountant':
                    return redirect()->route('accountant.dashboard');
                case 'admin':
                case 'super_admin':
                case 'sysadmin':
                    return redirect()->route('admin.dashboard');
                case 'tenant':
                    return redirect()->route('tenant.dashboard');
                case 'security':
                    return redirect('/security');
                case 'property_manager':
                    return redirect()->route('property-manager.dashboard');
                case 'maintenance':
                    return redirect()->route('maintenance.dashboard');
                case 'cleaning_staff':
                    return redirect()->route('cleaning.dashboard');
                case 'account_manager':
                    return redirect()->route('admin.dashboard');
                default:
                    return redirect()->intended(route('dashboard', absolute: false));
            }

        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return back()->withErrors([
                'email' => 'Login failed. Please try again.',
            ]);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}