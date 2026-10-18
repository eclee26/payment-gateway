<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TOTP is mandatory. A logged-in user who has not set it up yet can only
 * reach the setup pages (and log out) until they do.
 */
class EnsureTwoFactorEnabled
{
    /** Routes a user without TOTP may still use. */
    private const EXEMPT = [
        'two-factor.setup',
        'two-factor.setup.store',
        'two-factor.recovery-codes',
        'logout',
        'verification.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasTwoFactorEnabled() || $request->routeIs(self::EXEMPT)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Two-factor authentication must be set up.');
        }

        return redirect()->guest(route('two-factor.setup'));
    }
}
