<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins do not operate a merchant account. Send them to the admin area
 * instead of the merchant dashboard (where EnsureUserHasMerchant would
 * otherwise create a merchant for them). Login and verification redirect
 * to the dashboard, so this is also how admins land in the admin area.
 */
class RedirectAdminsToAdminArea
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->hasRole('admin') && ! $user->hasRole('merchant')) {
            return redirect()->route('admin.home');
        }

        return $next($request);
    }
}
