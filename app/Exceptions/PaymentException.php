<?php

namespace App\Exceptions;

use App\Support\ApiError;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A payment operation that was refused because of the payment's state
 * or the requested amount. Rendered as a 4xx API error.
 */
class PaymentException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidState(string $action, string $status): self
    {
        return new self('invalid_state', "Cannot {$action} a payment with status [{$status}].");
    }

    public static function amountTooLarge(string $message): self
    {
        return new self('amount_too_large', $message);
    }

    public static function methodUnavailable(string $method): self
    {
        return new self('payment_method_unavailable', "The payment method [{$method}] is not enabled for this account.");
    }

    public static function providerDeclined(string $action, ?string $code): self
    {
        return new self('provider_declined', "The provider refused to {$action} the payment".($code ? " ({$code})." : '.'), 402);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): JsonResponse
    {
        return ApiError::response($this->status, 'invalid_request_error', $this->errorCode, $this->getMessage());
    }
}
