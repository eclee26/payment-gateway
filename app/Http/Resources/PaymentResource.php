<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'object' => 'payment',
            'amount' => $this->amount,
            'amount_captured' => $this->amount_captured,
            'amount_refunded' => $this->amount_refunded,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'capture_method' => $this->capture_method->value,
            'card' => [
                'brand' => $this->card_brand,
                'last4' => $this->card_last4,
                'exp_month' => $this->card_exp_month,
                'exp_year' => $this->card_exp_year,
            ],
            'failure_code' => $this->failure_code,
            'description' => $this->description,
            'metadata' => $this->metadata ?? (object) [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
