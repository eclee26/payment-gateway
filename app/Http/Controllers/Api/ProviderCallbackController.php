<?php

namespace App\Http\Controllers\Api;

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
        $callback = $this->processors->for($provider)->parseCallback($request);

        if ($callback === null) {
            return ApiError::response(401, 'authentication_error', 'invalid_callback', 'The callback could not be verified.');
        }

        if ($this->payments->handleProviderCallback($provider, $callback) === null) {
            return ApiError::response(404, 'invalid_request_error', 'resource_missing', 'No such payment.');
        }

        return response()->json(['received' => true]);
    }
}
