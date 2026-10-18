<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects request bodies that are not JSON, so form-encoded payloads
 * (and the browser-originated requests that send them) never reach the API.
 */
class RequireJson
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getContent() !== '' && ! $request->isJson()) {
            return ApiError::response(415, 'invalid_request_error', 'unsupported_media_type', 'Request bodies must be application/json.');
        }

        return $next($request);
    }
}
