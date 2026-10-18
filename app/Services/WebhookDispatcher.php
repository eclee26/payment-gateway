<?php

namespace App\Services;

use App\Http\Resources\PaymentResource;
use App\Http\Resources\RefundResource;
use App\Jobs\SendWebhook;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /**
     * Record an event for the merchant and queue its delivery once the
     * surrounding database transaction commits.
     */
    public function dispatch(Merchant $merchant, string $type, Payment|Refund $object): void
    {
        if (! $merchant->webhook_url) {
            return;
        }

        $data = $object instanceof Payment
            ? PaymentResource::make($object)->resolve()
            : RefundResource::make($object)->resolve();

        $delivery = $merchant->webhookDeliveries()->create([
            'event_type' => $type,
            'payload' => [
                'id' => 'evt_'.strtolower((string) Str::ulid()),
                'type' => $type,
                'created' => now()->timestamp,
                'data' => ['object' => $data],
            ],
        ]);

        SendWebhook::dispatch($delivery)->afterCommit();
    }
}
