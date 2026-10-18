<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\ApiError;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes POST requests safe to retry using an Idempotency-Key header.
 *
 * The key row and the request's own writes share one transaction, so:
 * - a concurrent duplicate blocks on the unique index until the first
 *   request commits, then replays its stored response;
 * - a crash or 5xx rolls back both the work and the key, so the client
 *   can retry safely and no payment exists without a stored response.
 */
class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || ! preg_match('/^[\x21-\x7E]{1,255}$/', $key)) {
            return ApiError::response(400, 'invalid_request_error', 'idempotency_key_required', 'A valid Idempotency-Key header (1-255 printable characters) is required.');
        }

        $merchant = $request->attributes->get('merchant');
        $requestHash = hash('sha256', $request->method()."\n".$request->getRequestUri()."\n".$request->getContent());

        DB::beginTransaction();

        try {
            $record = IdempotencyKey::create([
                'merchant_id' => $merchant->id,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->getRequestUri(),
                'request_hash' => $requestHash,
            ]);
        } catch (UniqueConstraintViolationException) {
            DB::rollBack();

            return $this->replay($merchant->id, $key, $requestHash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            DB::rollBack();

            return $response;
        }

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'completed_at' => now(),
        ]);

        DB::commit();

        return $response;
    }

    /**
     * Return the stored response for a key that was already used.
     */
    private function replay(int $merchantId, string $key, string $requestHash): Response
    {
        $existing = IdempotencyKey::where('merchant_id', $merchantId)->where('key', $key)->first();

        if ($existing === null || $existing->completed_at === null) {
            return ApiError::response(409, 'idempotency_error', 'idempotency_request_in_progress', 'A request with this Idempotency-Key is still being processed.');
        }

        if (! hash_equals($existing->request_hash, $requestHash)) {
            return ApiError::response(422, 'idempotency_error', 'idempotency_key_reused', 'This Idempotency-Key was already used with a different request.');
        }

        return response($existing->response_body, $existing->response_status, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
