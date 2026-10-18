<?php

namespace App\Services\Processor;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * A driver for one upstream provider's API. Every bank or PSP has its own
 * API; each gets an implementation of this interface, so the rest of the
 * gateway (and the merchant-facing API) never changes per provider.
 */
interface PaymentProcessor
{
    /**
     * Start a charge: authorize a card, or create a QR / wallet / transfer
     * request that the customer completes later.
     */
    public function charge(ChargeRequest $request): ProcessorResult;

    /**
     * Capture some or all of an authorized card payment.
     */
    public function capture(Payment $payment, int $amount): ProcessorResult;

    /**
     * Void an authorization or abandon a pending customer action.
     */
    public function cancel(Payment $payment): ProcessorResult;

    /**
     * Return funds for a completed payment.
     */
    public function refund(Payment $payment, int $amount): ProcessorResult;

    /**
     * Verify and decode a callback the provider sent to the gateway.
     * Returns null when the request is not authentic or not understood.
     */
    public function parseCallback(Request $request): ?ProviderCallback;
}
