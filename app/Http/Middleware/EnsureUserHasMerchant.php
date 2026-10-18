<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every dashboard user operates exactly one merchant account; create it
 * on first visit so the dashboard can always rely on it existing.
 */
class EnsureUserHasMerchant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->merchant === null) {
            $user->setRelation('merchant', $user->merchant()->create(['name' => $user->name]));
        }

        return $next($request);
    }
}
