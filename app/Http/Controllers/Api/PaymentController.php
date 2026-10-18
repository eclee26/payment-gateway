<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Requests\Api\CapturePaymentRequest;
use App\Http\Requests\Api\CreatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * List the merchant's payments, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = min(max((int) $request->query('limit', 25), 1), 100);

        return PaymentResource::collection(
            $this->merchant($request)->payments()->latest()->latest('id')->cursorPaginate($limit)
        );
    }

    /**
     * Authorize (and by default capture) a new card payment.
     */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($this->merchant($request), $request->validated());

        if ($payment->status === PaymentStatus::Failed) {
            return ApiError::response(402, 'card_error', $payment->failure_code, 'The card was declined.', [
                'payment' => PaymentResource::make($payment)->resolve($request),
            ]);
        }

        return PaymentResource::make($payment)->response()->setStatusCode(201);
    }

    /**
     * Retrieve one of the merchant's payments.
     */
    public function show(Request $request, string $payment): PaymentResource
    {
        return PaymentResource::make($this->findPayment($request, $payment));
    }

    /**
     * Capture an authorized payment.
     */
    public function capture(CapturePaymentRequest $request, string $payment): PaymentResource
    {
        return PaymentResource::make(
            $this->payments->capture($this->findPayment($request, $payment), $request->validated('amount'))
        );
    }

    /**
     * Cancel an authorized, uncaptured payment.
     */
    public function cancel(Request $request, string $payment): PaymentResource
    {
        return PaymentResource::make(
            $this->payments->cancel($this->findPayment($request, $payment))
        );
    }
}
