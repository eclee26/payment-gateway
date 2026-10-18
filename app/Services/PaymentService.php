<?php

namespace App\Services;

use App\Enums\CaptureMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Processor\PaymentProcessor;
use App\Support\Card;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentProcessor $processor,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * Authorize a card payment, capturing immediately unless the capture
     * method is manual. The raw card number is used only in memory.
     *
     * @param  array{amount: int, currency: string, capture_method?: string, description?: string|null, metadata?: array<string, mixed>|null, card: array{number: string, exp_month: int, exp_year: int, cvc: string}}  $data
     */
    public function create(Merchant $merchant, array $data): Payment
    {
        $number = Card::normalize($data['card']['number']);
        $currency = strtoupper($data['currency']);
        $captureMethod = CaptureMethod::from($data['capture_method'] ?? CaptureMethod::Automatic->value);

        $result = $this->processor->authorize($number, $data['amount'], $currency);

        $status = match (true) {
            ! $result->approved => PaymentStatus::Failed,
            $captureMethod === CaptureMethod::Manual => PaymentStatus::RequiresCapture,
            default => PaymentStatus::Succeeded,
        };

        return DB::transaction(function () use ($merchant, $data, $number, $currency, $captureMethod, $result, $status) {
            $payment = $merchant->payments()->create([
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => $status,
                'capture_method' => $captureMethod,
                'amount_captured' => $status === PaymentStatus::Succeeded ? $data['amount'] : 0,
                'amount_refunded' => 0,
                'card_brand' => Card::brand($number),
                'card_last4' => substr($number, -4),
                'card_exp_month' => $data['card']['exp_month'],
                'card_exp_year' => $data['card']['exp_year'],
                'card_fingerprint' => Card::fingerprint($number),
                'failure_code' => $result->declineCode,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            match ($status) {
                PaymentStatus::Succeeded => $this->webhooks->dispatch($merchant, 'payment.succeeded', $payment),
                PaymentStatus::Failed => $this->webhooks->dispatch($merchant, 'payment.failed', $payment),
                default => null,
            };

            return $payment;
        });
    }

    /**
     * Capture an authorized payment, optionally for less than the full amount.
     */
    public function capture(Payment $payment, ?int $amount = null): Payment
    {
        return DB::transaction(function () use ($payment, $amount) {
            $payment = $this->lock($payment);

            if ($payment->status !== PaymentStatus::RequiresCapture) {
                throw PaymentException::invalidState('capture', $payment->status->value);
            }

            $amount ??= $payment->amount;

            if ($amount > $payment->amount) {
                throw PaymentException::amountTooLarge('Capture amount exceeds the authorized amount.');
            }

            $payment->update([
                'amount_captured' => $amount,
                'status' => PaymentStatus::Succeeded,
            ]);

            $this->webhooks->dispatch($payment->merchant, 'payment.captured', $payment);

            return $payment;
        });
    }

    /**
     * Release an authorization that has not been captured.
     */
    public function cancel(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lock($payment);

            if ($payment->status !== PaymentStatus::RequiresCapture) {
                throw PaymentException::invalidState('cancel', $payment->status->value);
            }

            $payment->update(['status' => PaymentStatus::Canceled]);

            $this->webhooks->dispatch($payment->merchant, 'payment.canceled', $payment);

            return $payment;
        });
    }

    /**
     * Refund some or all of the captured amount.
     */
    public function refund(Payment $payment, ?int $amount = null, ?string $reason = null): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason) {
            $payment = $this->lock($payment);

            if (! $payment->status->isRefundable()) {
                throw PaymentException::invalidState('refund', $payment->status->value);
            }

            $amount ??= $payment->refundableAmount();

            if ($amount > $payment->refundableAmount()) {
                throw PaymentException::amountTooLarge('Refund amount exceeds the refundable amount of '.$payment->refundableAmount().'.');
            }

            $refund = $payment->refunds()->make([
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'succeeded',
            ]);
            $refund->merchant()->associate($payment->merchant_id);
            $refund->save();

            $payment->amount_refunded += $amount;
            $payment->status = $payment->refundableAmount() === 0
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;
            $payment->save();

            $this->webhooks->dispatch($payment->merchant, 'refund.succeeded', $refund);

            return $refund;
        });
    }

    /**
     * Re-read the payment with a row lock so concurrent captures or
     * refunds against the same payment are serialized.
     */
    private function lock(Payment $payment): Payment
    {
        return Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
    }
}
