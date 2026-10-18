<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallbackOutcome;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\PaymentService;
use App\Services\Processor\ProcessorManager;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives payment outcomes from upstream providers. Authenticity is
 * checked by the provider's own driver, since every provider signs its
 * callbacks differently.
 */
class ProviderCallbackController extends Controller
{
    public function __construct(
        private readonly ProcessorManager $processors,
        private readonly PaymentService $payments,
    ) {}

    public function __invoke(Request $request, Provider $provider): JsonResponse
    {
        // Providers whose driver is not registered (e.g. the simulator in
        // production) are treated as non-existent.
        if (! in_array($provider->driver, ProcessorManager::drivers(), true)) {
            return ApiError::response(404, 'invalid_request_error', 'resource_missing', 'No such provider.');
        }

        $callback = $this->processors->for($provider)->parseCallback($request);

        if ($callback === null) {
            return ApiError::response(401, 'authentication_error', 'invalid_callback', 'The callback could not be verified.');
        }

        return match ($this->payments->handleProviderCallback($provider, $callback)) {
            CallbackOutcome::Applied, CallbackOutcome::AlreadyApplied => response()->json(['received' => true]),
            // The charge runs inside the create request's transaction, so a fast
            // callback can arrive before the payment is committed. Ask the
            // provider to retry rather than dropping the outcome.
            CallbackOutcome::NotFound => ApiError::response(503, 'api_error', 'payment_not_ready', 'Unknown payment reference; retry later.')
                ->header('Retry-After', '5'),
            CallbackOutcome::Conflict => ApiError::response(409, 'invalid_request_error', 'payment_not_pending', 'The payment is no longer pending; the outcome was not applied.'),
        };
    }
}
