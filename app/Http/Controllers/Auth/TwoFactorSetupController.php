<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mandatory TOTP enrolment. The pending secret lives in the session until
 * the user proves their authenticator app produces valid codes for it.
 */
class TwoFactorSetupController extends Controller
{
    private const SESSION_SECRET = 'two-factor.pending-secret';

    public function create(Request $request, TwoFactorAuthenticator $twoFactor): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.security');
        }

        $secret = $request->session()->get(self::SESSION_SECRET);

        if ($secret === null) {
            $secret = $twoFactor->generateSecret();
            $request->session()->put(self::SESSION_SECRET, $secret);
        }

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrCode' => $twoFactor->qrCodeSvg($user, $secret),
        ]);
    }

    public function store(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.security');
        }

        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $secret = $request->session()->get(self::SESSION_SECRET);

        if ($secret === null || ! $twoFactor->verify($user, $request->input('code'), $secret)) {
            throw ValidationException::withMessages([
                'code' => __('The code did not match. Check the time on your device and try again.'),
            ]);
        }

        $codes = $twoFactor->enable($user, $secret);
        $request->session()->forget(self::SESSION_SECRET);

        return redirect()->route('two-factor.recovery-codes')->with('recoveryCodes', $codes);
    }

    /**
     * The recovery codes, shown once right after they are generated.
     */
    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recoveryCodes');

        if ($codes === null) {
            return redirect()->route('profile.security');
        }

        return view('auth.two-factor-recovery-codes', ['codes' => $codes]);
    }

    /**
     * Replace the recovery codes. Behind password.confirm.
     */
    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        $codes = $twoFactor->generateRecoveryCodes();

        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('two-factor.recovery-codes')->with('recoveryCodes', $codes);
    }

    public function security(Request $request): View
    {
        return view('profile.security', ['user' => $request->user()]);
    }
}
