<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\SafeUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    public function __construct(public WebhookDelivery $delivery)
    {
        $this->tries = count(config('gateway.webhook_backoff')) + 1;
    }

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return config('gateway.webhook_backoff');
    }

    /**
     * Sign and POST the event to the merchant's webhook URL.
     *
     * Signature header: X-Gateway-Signature: t=<unix>,v1=<hex>
     * where v1 = HMAC-SHA256(webhook_secret, "<t>.<raw body>").
     */
    public function handle(): void
    {
        $delivery = $this->delivery;
        $merchant = $delivery->merchant;

        if ($delivery->delivered_at !== null || ! $merchant->webhook_url) {
            return;
        }

        $delivery->increment('attempts');

        // Re-check at send time: DNS may have changed since the URL was saved.
        $target = SafeUrl::resolve($merchant->webhook_url);

        if ($target === null) {
            $delivery->update(['last_error' => 'Webhook URL is not allowed.']);

            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $merchant->webhook_secret);

        try {
            $response = Http::timeout(config('gateway.webhook_timeout'))
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->withOptions(['curl' => [
                    CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"],
                ]])
                ->withHeaders([
                    'User-Agent' => 'PaymentGateway-Webhooks/1.0',
                    'X-Gateway-Event' => $delivery->event_type,
                    'X-Gateway-Signature' => "t={$timestamp},v1={$signature}",
                ])
                ->withBody($body, 'application/json')
                ->post($merchant->webhook_url);
        } catch (ConnectionException $e) {
            $delivery->update(['last_error' => 'Connection failed.']);

            throw $e;
        }

        $delivery->update([
            'last_response_status' => $response->status(),
            'last_error' => $response->successful() ? null : 'Non-2xx response.',
            'delivered_at' => $response->successful() ? now() : null,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook delivery {$delivery->id} returned HTTP {$response->status()}.");
        }
    }
}
