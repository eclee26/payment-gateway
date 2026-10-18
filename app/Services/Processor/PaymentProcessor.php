<?php

namespace App\Services\Processor;

interface PaymentProcessor
{
    /**
     * Ask the card network to authorize (reserve) the amount on the card.
     */
    public function authorize(string $cardNumber, int $amount, string $currency): ProcessorResult;
}
