<?php

namespace App\Services;

use App\Enums\CallbackOutcome;
use App\Enums\CaptureMethod;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\Refund;
use App\Services\Processor\ChargeRequest;
use App\Services\Processor\PaymentProcessor;
use App\Services\Processor\ProcessorManager;
use App\Services\Processor\ProcessorResult;
use App\Services\Processor\ProviderCallback;
use App\Support\Card;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly ProcessorManager $processors,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * Start a payment with the provider the merchant has assigned to the
     * payment method. Cards are authorized (and captured unless the capture
     * method is manual); other methods wait in requires_action until the
     * provider reports the outcome. The raw card number is used only in memory.
     *
     * @param  array{payment_method: string, amount: int, currency: string, capture_method?: string, description?: string|null, metadata?: array<string, mixed>|null, card?: array{number: string, exp_month: int, exp_year: int, cvc: string}}  $data
     */
    public function create(Merchant $merchant, array $data): Payment
    {
        $method = PaymentMethod::from($data['payment_method']);
        $pricing = $merchant->providerFor($method) ?? throw PaymentException::methodUnavailable($method->value);
        $provider = $pricing->provider;

        $number = $method === PaymentMethod::Card ? Card::normalize($data['card']['number']) : null;
        $currency = strtoupper($data['currency']);
        $captureMethod = CaptureMethod::from($data['capture_method'] ?? CaptureMethod::Automatic->value);

        $result = $this->processors->for($provider)->charge(
            new ChargeRequest($method, $data['amount'], $currency, $number)
        );

        $status = match (true) {
            ! $result->approved => PaymentStatus::Failed,
            $result->isPendingAction() => PaymentStatus::RequiresAction,
            $captureMethod === CaptureMethod::Manual => PaymentStatus::RequiresCapture,
            default => PaymentStatus::Succeeded,
        };

        return DB::transaction(function () use ($merchant, $data, $method, $provider, $pricing, $number, $currency, $captureMethod, $result, $status) {
            $payment = $merchant->payments()->create([
                'payment_method' => $method,
                'provider_id' => $provider->id,
                'provider_reference' => $result->providerReference,
                'amount' => $data['amount'],
                'currency' => $currency,
                'status' => $status,
                'capture_method' => $captureMethod,
                'amount_captured' => $status === PaymentStatus::Succeeded ? $data['amount'] : 0,
                'amount_refunded' => 0,
                'fee_amount' => $status === PaymentStatus::Failed ? 0 : $pricing->feeFor($data['amount']),
                'card_brand' => $number ? Card::brand($number) : null,
                'card_last4' => $number ? substr($number, -4) : null,
                'card_exp_month' => $number ? $data['card']['exp_month'] : null,
                'card_exp_year' => $number ? $data['card']['exp_year'] : null,
                'card_fingerprint' => $number ? Card::fingerprint($number) : null,
                'failure_code' => $result->declineCode,
                'next_action' => $result->nextAction,
                'expires_at' => $status === PaymentStatus::RequiresAction ? now()->addMinutes(config('gateway.pending_payment_ttl_minutes')) : null,
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

            $this->callProvider($payment, 'capture', fn ($processor) => $processor->capture($payment, $amount));

            $payment->update([
                'amount_captured' => $amount,
                'status' => PaymentStatus::Succeeded,
            ]);

            $this->webhooks->dispatch($payment->merchant, 'payment.captured', $payment);

            return $payment;
        });
    }

    /**
     * Release an authorization that has not been captured, or abandon a
     * payment that is still waiting on the customer.
     */
    public function cancel(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = $this->lock($payment);

            if (! in_array($payment->status, [PaymentStatus::RequiresCapture, PaymentStatus::RequiresAction], true)) {
                throw PaymentException::invalidState('cancel', $payment->status->value);
            }

            return $this->release($payment);
        });
    }

    /**
     * Cancel requires_action payments whose customer did not complete them
     * in time. A provider that refuses the cancel (e.g. the customer is
     * paying right now) keeps the payment pending for its callback.
     * Returns how many payments were expired.
     */
    public function expirePending(): int
    {
        $expired = 0;

        Payment::where('status', PaymentStatus::RequiresAction)
            ->where('expires_at', '<=', now())
            ->select('id')
            ->eachById(function (Payment $candidate) use (&$expired) {
                try {
                    DB::transaction(function () use ($candidate, &$expired) {
                        $payment = $this->lock($candidate);

                        if ($payment->status === PaymentStatus::RequiresAction && $payment->expires_at?->isPast()) {
                            $this->release($payment, 'expired');
                            $expired++;
                        }
                    });
                } catch (PaymentException $e) {
                    Log::warning('Provider refused to expire a pending payment.', ['payment_id' => $candidate->id, 'error' => $e->getMessage()]);
                }
            });

        return $expired;
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

            $this->callProvider($payment, 'refund', fn ($processor) => $processor->refund($payment, $amount));

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
     * Apply a verified provider callback to the payment it refers to.
     * Only pending payments change. A repeat of an outcome already applied
     * is harmless; an outcome contradicting the payment's final state
     * (success after cancel or expiry, failure after success) is left
     * unapplied and logged, since money may have moved.
     */
    public function handleProviderCallback(Provider $provider, ProviderCallback $callback): CallbackOutcome
    {
        return DB::transaction(function () use ($provider, $callback) {
            $payment = Payment::where('provider_id', $provider->id)
                ->where('provider_reference', $callback->providerReference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return CallbackOutcome::NotFound;
            }

            if ($payment->status !== PaymentStatus::RequiresAction) {
                $consistent = $callback->succeeded
                    ? in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)
                    : in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Canceled], true);

                if ($consistent) {
                    return CallbackOutcome::AlreadyApplied;
                }

                Log::error('Provider callback contradicts the payment status; reconcile with the provider.', [
                    'payment_id' => $payment->id,
                    'provider' => $provider->code,
                    'provider_reference' => $callback->providerReference,
                    'payment_status' => $payment->status->value,
                    'callback_outcome' => $callback->succeeded ? 'succeeded' : 'failed',
                ]);

                return CallbackOutcome::Conflict;
            }

            if ($callback->succeeded) {
                $payment->update([
                    'status' => PaymentStatus::Succeeded,
                    'amount_captured' => $payment->amount,
                    'next_action' => null,
                ]);
                $this->webhooks->dispatch($payment->merchant, 'payment.succeeded', $payment);
            } else {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'failure_code' => $callback->declineCode,
                    'fee_amount' => 0,
                    'next_action' => null,
                ]);
                $this->webhooks->dispatch($payment->merchant, 'payment.failed', $payment);
            }

            return CallbackOutcome::Applied;
        });
    }

    /**
     * Cancel a locked, cancelable payment with its provider. The fee is
     * dropped because nothing was collected.
     */
    private function release(Payment $payment, ?string $reason = null): Payment
    {
        $this->callProvider($payment, 'cancel', fn ($processor) => $processor->cancel($payment));

        $payment->update([
            'status' => PaymentStatus::Canceled,
            'failure_code' => $reason ?? $payment->failure_code,
            'fee_amount' => 0,
            'next_action' => null,
        ]);

        $this->webhooks->dispatch($payment->merchant, 'payment.canceled', $payment);

        return $payment;
    }

    /**
     * Run an operation against the provider that processed the payment,
     * refusing the change locally if the provider declines it. Payments
     * created before providers existed have no provider to call.
     *
     * @param  callable(PaymentProcessor): ProcessorResult  $operation
     */
    private function callProvider(Payment $payment, string $action, callable $operation): void
    {
        if ($payment->provider === null) {
            return;
        }

        $result = $operation($this->processors->for($payment->provider));

        if (! $result->approved) {
            throw PaymentException::providerDeclined($action, $result->declineCode);
        }
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
