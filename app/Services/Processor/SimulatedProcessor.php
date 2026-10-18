<?php

namespace App\Services\Processor;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A fake provider. Cards are approved or declined at once (specific test
 * card numbers decline); every other payment method waits for a signed
 * callback, which the dashboard simulator can send. No real money moves.
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

    public function __construct(private readonly Provider $provider) {}

    public function charge(ChargeRequest $request): ProcessorResult
    {
        if (! $request->method->isAsync()) {
            if (isset(self::DECLINES[$request->cardNumber])) {
                return ProcessorResult::declined(self::DECLINES[$request->cardNumber]);
            }

            return ProcessorResult::approved('sim_'.Str::lower((string) Str::ulid()));
        }

        $reference = 'sim_'.Str::lower((string) Str::ulid());

        return ProcessorResult::requiresAction($reference, $this->nextAction($request->method, $reference));
    }

    public function capture(Payment $payment, int $amount): ProcessorResult
    {
        return ProcessorResult::approved($payment->provider_reference);
    }

    public function cancel(Payment $payment): ProcessorResult
    {
        return ProcessorResult::approved($payment->provider_reference);
    }

    public function refund(Payment $payment, int $amount): ProcessorResult
    {
        return ProcessorResult::approved($payment->provider_reference);
    }

    /**
     * Callbacks are JSON {"reference", "status", "decline_code"?} signed
     * with X-Simulator-Signature: hex HMAC-SHA256 of the raw body.
     */
    public function parseCallback(Request $request): ?ProviderCallback
    {
        $body = $request->getContent();
        $expected = static::sign($body);

        if (! hash_equals($expected, (string) $request->header('X-Simulator-Signature'))) {
            return null;
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ! is_string($data['reference'] ?? null) || ! in_array($data['status'] ?? null, ['succeeded', 'failed'], true)) {
            return null;
        }

        return new ProviderCallback(
            $data['reference'],
            $data['status'] === 'succeeded',
            $data['status'] === 'failed' ? (string) ($data['decline_code'] ?? 'payment_failed') : null,
        );
    }

    /**
     * Sign a callback body the way the simulated provider does.
     */
    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('gateway.simulator_callback_secret'));
    }

    /**
     * @return array<string, mixed>
     */
    private function nextAction(PaymentMethod $method, string $reference): array
    {
        $base = 'https://simulator.invalid/'.$this->provider->code;

        return match ($method) {
            PaymentMethod::Qrph => ['type' => 'qr_code', 'qr_string' => '00020101021228'.strtoupper(substr(hash('sha256', $reference), 0, 40))],
            PaymentMethod::Ecpay => ['type' => 'display_reference', 'reference' => strtoupper(substr(hash('sha256', $reference), 0, 12))],
            default => ['type' => 'redirect', 'url' => $base.'/'.strtolower($method->value).'/'.$reference],
        };
    }
}
