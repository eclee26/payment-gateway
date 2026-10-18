<?php

namespace App\Services\Processor;

/**
 * A fake acquirer. Specific test card numbers trigger declines; every
 * other valid card is approved. No real money moves.
 */
class SimulatedProcessor implements PaymentProcessor
{
    /**
     * Test card numbers that are declined, mapped to their decline code.
     *
     * @var array<string, string>
     */
    public const DECLINES = [
        '4000000000000002' => 'card_declined',
        '4000000000009995' => 'insufficient_funds',
        '4000000000000069' => 'expired_card',
    ];

    public function authorize(string $cardNumber, int $amount, string $currency): ProcessorResult
    {
        if (isset(self::DECLINES[$cardNumber])) {
            return ProcessorResult::declined(self::DECLINES[$cardNumber]);
        }

        return ProcessorResult::approved();
    }
}
