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
        $user = $request->authenticate();

        $request->session()->regenerate();

        // Password is correct but the user is not logged in until they pass
        // the TOTP challenge.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                TwoFactorChallengeController::SESSION_USER => $user->getKey(),
                TwoFactorChallengeController::SESSION_REMEMBER => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        // No TOTP yet: EnsureTwoFactorEnabled sends them to setup.
        Auth::guard('web')->login($user, $request->boolean('remember'));

        return redirect()->intended(route('dashboard', absolute: false));
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
