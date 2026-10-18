<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CreateRefundRequest;
use App\Http\Resources\RefundResource;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * List refunds for one of the merchant's payments.
     */
    public function index(Request $request, string $payment): JsonResponse
    {
        return response()->json([
            'data' => RefundResource::collection(
                $this->findPayment($request, $payment)->refunds()->latest()->get()
            ),
        ]);
    }

    /**
     * Refund some or all of a captured payment.
     */
    public function store(CreateRefundRequest $request, string $payment): JsonResponse
    {
        $refund = $this->payments->refund(
            $this->findPayment($request, $payment),
            $request->validated('amount'),
            $request->validated('reason'),
        );

        return RefundResource::make($refund)->response()->setStatusCode(201);
    }
}
