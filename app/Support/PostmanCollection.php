<?php

namespace App\Support;

/**
 * Builds a Postman (v2.1) collection for the API from the same endpoint list
 * as the docs page. A collection-level pre-request script signs every
 * request, so the merchant only has to paste their secret.
 */
class PostmanCollection
{
    /**
     * Signs each request exactly as VerifyHmacSignature expects. Wrapped in
     * a function because Postman reuses one script scope across a run, so
     * top-level const declarations would collide from the second request on.
     */
    private const SIGNING_SCRIPT = <<<'JS'
// Signs every request in this collection. See "Authentication" in the API docs.
(() => {
const CryptoJS = require('crypto-js');

const secret = pm.variables.get('secret');
if (!secret) {
    throw new Error('Set the "secret" collection variable to your API key secret (sk_...). Use its Current value so it is never synced or exported.');
}

const method = pm.request.method.toUpperCase();
const url = pm.variables.replaceIn(pm.request.url.toString());
const path = url.replace(/^[a-z]+:\/\/[^/]+/i, ''); // path + query, as sent
const body = pm.request.body && pm.request.body.mode === 'raw'
    ? pm.variables.replaceIn(pm.request.body.raw || '')
    : '';
const timestamp = Math.floor(Date.now() / 1000).toString();
const nonce = pm.variables.replaceIn('{{$guid}}');
const bodyHash = CryptoJS.SHA256(body).toString(CryptoJS.enc.Hex);
const signature = CryptoJS.HmacSHA256([timestamp, nonce, method, path, bodyHash].join('\n'), secret)
    .toString(CryptoJS.enc.Hex);

pm.request.headers.upsert({ key: 'X-Api-Key', value: pm.variables.get('key_id') });
pm.request.headers.upsert({ key: 'X-Timestamp', value: timestamp });
pm.request.headers.upsert({ key: 'X-Nonce', value: nonce });
pm.request.headers.upsert({ key: 'X-Signature', value: 'v1=' + signature });
pm.request.headers.upsert({ key: 'Accept', value: 'application/json' });

// Every POST needs an Idempotency-Key. Set the idempotency_key variable to
// replay a request safely; leave it empty to get a fresh key each time.
if (method === 'POST') {
    pm.request.headers.upsert({
        key: 'Idempotency-Key',
        value: pm.variables.get('idempotency_key') || pm.variables.replaceIn('{{$guid}}'),
    });
}
})();
JS;

    public function __construct(
        private readonly ApiDocSamples $samples,
        private readonly string $baseUrl,
        private readonly string $keyId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $endpoints = collect($this->samples->endpoints())->keyBy('id');
        $declinedBody = array_replace_recursive($endpoints['create-payment']['request'][1], [
            'capture_method' => 'automatic',
            'card' => ['number' => '4000000000000002'],
        ]);

        return [
            'info' => [
                'name' => 'Payment Gateway API',
                'description' => "Requests are signed automatically by the collection's pre-request script.\n\n"
                    ."1. Open the collection's Variables tab and paste your API key secret into the Current value of `secret`.\n"
                    ."2. Send \"Create payment\" first: it stores `payment_id` for the requests after it.\n"
                    ."3. Or run the whole collection in the Runner; every request checks its expected status.\n\n"
                    .'Full docs: '.$this->baseUrl.'/dashboard/docs',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'event' => [
                ['listen' => 'prerequest', 'script' => ['type' => 'text/javascript', 'exec' => explode("\n", self::SIGNING_SCRIPT)]],
            ],
            'variable' => [
                ['key' => 'base_url', 'value' => $this->baseUrl, 'type' => 'string'],
                ['key' => 'key_id', 'value' => $this->keyId, 'type' => 'string'],
                // Deliberately empty: the secret is never part of the download.
                ['key' => 'secret', 'value' => '', 'type' => 'secret'],
                ['key' => 'payment_id', 'value' => '', 'type' => 'string'],
                ['key' => 'idempotency_key', 'value' => '', 'type' => 'string'],
            ],
            'item' => [
                $this->folder('Payments', 'Authorize, inspect and capture a payment.', [
                    $this->item($endpoints['create-payment'], 201, storesPaymentId: true),
                    $this->item($endpoints['get-payment'], 200),
                    $this->item($endpoints['list-payments'], 200),
                    $this->item($endpoints['capture-payment'], 200),
                ]),
                $this->folder('Refunds', 'Refund the payment captured in the Payments folder.', [
                    $this->item($endpoints['create-refund'], 201),
                    $this->item($endpoints['list-refunds'], 200),
                ]),
                $this->folder('Cancel an authorization', 'Authorize a new payment, then release it without capturing.', [
                    $this->item($endpoints['create-payment'], 201, storesPaymentId: true, name: 'Create payment (manual capture)'),
                    $this->item($endpoints['cancel-payment'], 200),
                ]),
                $this->folder('Test cards', 'Simulated declines. See "Test cards" in the API docs for more numbers.', [
                    $this->item(
                        array_merge($endpoints['create-payment'], ['request' => ['/api/v1/payments', $declinedBody]]),
                        402,
                        name: 'Declined card (card_declined)',
                    ),
                ]),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function folder(string $name, string $description, array $items): array
    {
        return ['name' => $name, 'description' => $description, 'item' => $items];
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @return array<string, mixed>
     */
    private function item(array $endpoint, int $expectedStatus, bool $storesPaymentId = false, ?string $name = null): array
    {
        [$path, $body] = $endpoint['request'] + [1 => null];
        $path = str_replace(ApiDocSamples::EXAMPLE_PAYMENT_ID, '{{payment_id}}', $path);
        [$pathOnly, $query] = array_pad(explode('?', $path, 2), 2, null);

        $tests = ["pm.test('responds with HTTP {$expectedStatus}', () => pm.response.to.have.status({$expectedStatus}));"];
        if ($storesPaymentId) {
            $tests[] = '// Later requests use {{payment_id}}.';
            $tests[] = "if (pm.response.code === {$expectedStatus}) pm.collectionVariables.set('payment_id', pm.response.json().id);";
        }

        $request = [
            'method' => $endpoint['method'],
            'header' => $body === null ? [] : [['key' => 'Content-Type', 'value' => 'application/json']],
            'url' => array_filter([
                'raw' => '{{base_url}}'.$path,
                'host' => ['{{base_url}}'],
                'path' => array_values(array_filter(explode('/', $pathOnly))),
                'query' => $query === null ? null : collect(explode('&', $query))
                    ->map(fn ($pair) => array_combine(['key', 'value'], array_pad(explode('=', $pair, 2), 2, '')))
                    ->all(),
            ], fn ($value) => $value !== null),
            'description' => $endpoint['description'],
        ];

        if ($body !== null) {
            $request['body'] = [
                'mode' => 'raw',
                'raw' => ApiDocSamples::json($body),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        return [
            'name' => $name ?? $endpoint['summary'],
            'event' => [['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => $tests]]],
            'request' => $request,
        ];
    }
}
