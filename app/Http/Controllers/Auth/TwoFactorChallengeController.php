<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second login step for users with TOTP enabled. The password step stores
 * the user id in the session; the user is only logged in here.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_USER = 'login.id';

    public const SESSION_REMEMBER = 'login.remember';

    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->challengedUser($request) === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:50'],
        ]);

        $throttleKey = 'two-factor:'.$user->getKey().'|'.$request->ip();
        $this->ensureIsNotRateLimited($request, $throttleKey);

        $passed = filled($validated['code'] ?? null)
            ? $twoFactor->verify($user, $validated['code'])
            : $twoFactor->consumeRecoveryCode($user, $validated['recovery_code']);

        if (! $passed) {
            RateLimiter::hit($throttleKey);

            $field = filled($validated['code'] ?? null) ? 'code' : 'recovery_code';

            throw ValidationException::withMessages([
                $field => __('The provided two-factor code was invalid.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->pull(self::SESSION_REMEMBER, false);
        $request->session()->forget(self::SESSION_USER);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function challengedUser(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_USER);

        $user = $id === null ? null : User::find($id);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    private function ensureIsNotRateLimited(Request $request, string $key): void
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
