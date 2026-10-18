<?php

namespace App\Services\Processor;

use App\Enums\PaymentMethod;

/**
 * Everything a provider driver needs to start a charge. The card number
 * is only set for card payments and only ever held in memory.
 */
final class ChargeRequest
{
    public function __construct(
        public readonly PaymentMethod $method,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $cardNumber = null,
    ) {}
}
