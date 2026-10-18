<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates merchant API requests signed with HMAC-SHA256.
 *
 * Required headers:
 *   X-Api-Key:   the public key ID (key_…)
 *   X-Timestamp: unix seconds, within the configured tolerance
 *   X-Nonce:     unique per request (16–64 chars of [A-Za-z0-9-])
 *   X-Signature: v1=<hex HMAC-SHA256 of the string to sign>
 */
class VerifyHmacSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $throttleKey = 'gateway:auth-failures:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, config('gateway.auth_failure_limit'))) {
            return ApiError::response(429, 'rate_limit_error', 'too_many_failed_attempts', 'Too many failed authentication attempts.');
        }

        $keyId = $request->header('X-Api-Key');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (! is_string($keyId) || ! is_string($timestamp) || ! is_string($nonce) || ! is_string($signature)
            || ! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > config('gateway.signature_tolerance')
            || ! preg_match('/^[A-Za-z0-9-]{16,64}$/', $nonce)
            || ! preg_match('/^v1=([a-f0-9]{64})$/', $signature, $matches)) {
            return $this->fail($request, $throttleKey, $keyId);
        }

        $apiKey = ApiKey::with('merchant')->where('key_id', $keyId)->first();

        // Compute an HMAC even for unknown keys so response timing does not
        // reveal which key IDs exist.
        $expected = hash_hmac(
            'sha256',
            static::stringToSign($timestamp, $nonce, $request->method(), $request->getRequestUri(), $request->getContent()),
            $apiKey?->secret ?? 'unknown-key',
        );

        if (! hash_equals($expected, $matches[1]) || $apiKey === null || $apiKey->isRevoked()) {
            return $this->fail($request, $throttleKey, $keyId);
        }

        // Checked only after the signature is valid, so attackers cannot
        // burn a merchant's nonces.
        if (! Cache::add("gateway:nonce:{$apiKey->id}:{$nonce}", true, config('gateway.nonce_ttl'))) {
            return $this->fail($request, $throttleKey, $keyId);
        }

        if ($apiKey->last_used_at === null || $apiKey->last_used_at->lt(now()->subMinute())) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('merchant', $apiKey->merchant);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    /**
     * Build the canonical string that both client and server sign.
     */
    public static function stringToSign(string $timestamp, string $nonce, string $method, string $requestUri, string $body): string
    {
        return implode("\n", [$timestamp, $nonce, strtoupper($method), $requestUri, hash('sha256', $body)]);
    }

    /**
     * Reject the request with a deliberately generic error.
     */
    private function fail(Request $request, string $throttleKey, mixed $keyId): Response
    {
        RateLimiter::hit($throttleKey, 60);

        Log::warning('API authentication failed', [
            'key_id' => is_string($keyId) ? substr($keyId, 0, 64) : null,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return ApiError::response(401, 'authentication_error', 'authentication_failed', 'Request authentication failed.');
    }
}
