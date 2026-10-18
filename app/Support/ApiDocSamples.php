<?php

namespace App\Support;

use App\Enums\PaymentMethod;

/**
 * Generates the copy-paste code samples on the dashboard API docs page.
 *
 * Every language gets the same signing helper and the same calls, built
 * from one endpoint list, so the samples cannot drift apart.
 */
class ApiDocSamples
{
    /**
     * Languages offered in the docs, in tab order.
     *
     * @var array<string, string>
     */
    public const LANGUAGES = [
        'curl' => 'cURL',
        'php' => 'PHP',
        'node' => 'Node.js',
        'python' => 'Python',
    ];

    public const EXAMPLE_PAYMENT_ID = 'pay_01j9z3k5m8q2r4t6v8x0y2z4a6';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $keyId,
    ) {}

    /**
     * A reusable request-signing helper for each language.
     *
     * @return array<string, string>
     */
    public function helper(): array
    {
        return array_map(fn (string $code) => strtr($code, [
            '__BASE_URL__' => $this->baseUrl,
            '__KEY_ID__' => $this->keyId,
        ]), [
            'curl' => <<<'BASH'
# Requires bash (or zsh), curl and openssl.
# Keep the secret out of source control, e.g.: export GATEWAY_SECRET=sk_...
GATEWAY_URL="__BASE_URL__"
GATEWAY_KEY_ID="__KEY_ID__"

# Usage: gw METHOD PATH [JSON_BODY]
gw() {
  local method="$1" req_path="$2" body="${3:-}"
  local ts nonce body_hash signature
  ts=$(date +%s)
  nonce=$(openssl rand -hex 16)
  body_hash=$(printf '%s' "$body" | openssl dgst -sha256 -hex | sed 's/^.* //')
  signature=$(printf '%s\n%s\n%s\n%s\n%s' "$ts" "$nonce" "$method" "$req_path" "$body_hash" \
    | openssl dgst -sha256 -hmac "$GATEWAY_SECRET" -hex | sed 's/^.* //')

  local args=(-sS -X "$method" "$GATEWAY_URL$req_path"
    -H "Accept: application/json"
    -H "X-Api-Key: $GATEWAY_KEY_ID"
    -H "X-Timestamp: $ts"
    -H "X-Nonce: $nonce"
    -H "X-Signature: v1=$signature")
  if [ "$method" = "POST" ]; then
    # Set IDEMPOTENCY_KEY yourself to retry a request safely.
    args+=(-H "Idempotency-Key: ${IDEMPOTENCY_KEY:-$(openssl rand -hex 16)}")
  fi
  if [ -n "$body" ]; then
    args+=(-H "Content-Type: application/json" --data-raw "$body")
  fi
  curl "${args[@]}"
}
BASH,
            'php' => <<<'PHP'
<?php
// Requires PHP 8.1+ with the curl extension.
// Keep the secret out of source control: read it from the environment.
const GATEWAY_URL = '__BASE_URL__';
const GATEWAY_KEY_ID = '__KEY_ID__';

function gatewayRequest(string $method, string $path, string $body = '', ?string $idempotencyKey = null): array
{
    $method = strtoupper($method);
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $toSign = implode("\n", [$timestamp, $nonce, $method, $path, hash('sha256', $body)]);
    $signature = hash_hmac('sha256', $toSign, getenv('GATEWAY_SECRET'));

    $headers = [
        'Accept: application/json',
        'X-Api-Key: '.GATEWAY_KEY_ID,
        "X-Timestamp: $timestamp",
        "X-Nonce: $nonce",
        "X-Signature: v1=$signature",
    ];
    if ($method === 'POST') {
        // Pass your own key (e.g. an order ID) to retry a request safely.
        $headers[] = 'Idempotency-Key: '.($idempotencyKey ?? bin2hex(random_bytes(16)));
    }
    if ($body !== '') {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init(GATEWAY_URL.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);

    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), json_decode($response, true)];
}
PHP,
            'node' => <<<'JS'
// Requires Node.js 18+ (built-in fetch). Save as an ES module (.mjs).
// Keep the secret out of source control: read it from the environment.
import { createHash, createHmac, randomUUID } from 'node:crypto';

const GATEWAY_URL = '__BASE_URL__';
const GATEWAY_KEY_ID = '__KEY_ID__';

export async function gatewayRequest(method, path, body = '', idempotencyKey = randomUUID()) {
  method = method.toUpperCase();
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const nonce = randomUUID();
  const bodyHash = createHash('sha256').update(body).digest('hex');
  const toSign = [timestamp, nonce, method, path, bodyHash].join('\n');
  const signature = createHmac('sha256', process.env.GATEWAY_SECRET).update(toSign).digest('hex');

  const headers = {
    Accept: 'application/json',
    'X-Api-Key': GATEWAY_KEY_ID,
    'X-Timestamp': timestamp,
    'X-Nonce': nonce,
    'X-Signature': `v1=${signature}`,
  };
  // Pass your own idempotencyKey (e.g. an order ID) to retry a request safely.
  if (method === 'POST') headers['Idempotency-Key'] = idempotencyKey;
  if (body) headers['Content-Type'] = 'application/json';

  const response = await fetch(GATEWAY_URL + path, { method, headers, body: body || undefined });
  return { status: response.status, data: await response.json() };
}
JS,
            'python' => <<<'PY'
# Requires Python 3.8+ and `pip install requests`.
# Keep the secret out of source control: read it from the environment.
import hashlib
import hmac
import json
import os
import time
import uuid

import requests

GATEWAY_URL = "__BASE_URL__"
GATEWAY_KEY_ID = "__KEY_ID__"


def gateway_request(method, path, body="", idempotency_key=None):
    method = method.upper()
    timestamp = str(int(time.time()))
    nonce = uuid.uuid4().hex
    body_hash = hashlib.sha256(body.encode()).hexdigest()
    to_sign = "\n".join([timestamp, nonce, method, path, body_hash])
    signature = hmac.new(os.environ["GATEWAY_SECRET"].encode(), to_sign.encode(), hashlib.sha256).hexdigest()

    headers = {
        "Accept": "application/json",
        "X-Api-Key": GATEWAY_KEY_ID,
        "X-Timestamp": timestamp,
        "X-Nonce": nonce,
        "X-Signature": f"v1={signature}",
    }
    if method == "POST":
        # Pass your own idempotency_key (e.g. an order ID) to retry a request safely.
        headers["Idempotency-Key"] = idempotency_key or uuid.uuid4().hex
    if body:
        headers["Content-Type"] = "application/json"

    response = requests.request(method, GATEWAY_URL + path, headers=headers, data=body.encode() or None, timeout=30)
    return response.status_code, response.json()
PY,
        ]);
    }

    /**
     * A call to the API using the helper, in each language. Pass an
     * idempotency key to show a safely retryable request.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, string>
     */
    public function call(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        $json = $body === null ? null : static::json($body);

        $args = static fn (array $parts) => implode(', ', array_filter($parts, fn ($part) => $part !== null));
        $bodyArg = fn (string $wrap) => $json === null ? null : sprintf($wrap, $json);
        // A key without a body still needs the body position filled.
        $emptyBody = fn (string $empty) => $json === null && $idempotencyKey !== null ? $empty : null;

        return [
            'curl' => ($idempotencyKey === null ? '' : "IDEMPOTENCY_KEY={$idempotencyKey} ")
                ."gw {$method} '{$path}'".($json === null ? '' : " '{$json}'"),
            'php' => '[$status, $data] = gatewayRequest('.$args([
                "'{$method}'", "'{$path}'",
                $bodyArg("'%s'") ?? $emptyBody("''"),
                $idempotencyKey === null ? null : "'{$idempotencyKey}'",
            ]).');',
            'node' => 'const { status, data } = await gatewayRequest('.$args([
                "'{$method}'", "'{$path}'",
                $bodyArg('JSON.stringify(%s)') ?? $emptyBody("''"),
                $idempotencyKey === null ? null : "'{$idempotencyKey}'",
            ]).');',
            'python' => 'status, data = gateway_request('.$args([
                "\"{$method}\"", "\"{$path}\"",
                $bodyArg('json.dumps(%s)'),
                $idempotencyKey === null ? null : "idempotency_key=\"{$idempotencyKey}\"",
            ]).')',
        ];
    }

    /**
     * Receiving and verifying a webhook, in each language. The cURL tab
     * instead simulates a signed delivery to a local endpoint.
     *
     * @return array<string, string>
     */
    public function webhookReceiver(): array
    {
        return [
            'curl' => <<<'BASH'
# Simulate a signed delivery to your own endpoint while developing.
# export GATEWAY_WEBHOOK_SECRET=whsec_...
ts=$(date +%s)
payload='{"id":"evt_test","type":"payment.succeeded","created":'"$ts"',"data":{"object":{"id":"pay_test","object":"payment","status":"succeeded"}}}'
signature=$(printf '%s.%s' "$ts" "$payload" | openssl dgst -sha256 -hmac "$GATEWAY_WEBHOOK_SECRET" -hex | sed 's/^.* //')

curl -sS -X POST http://localhost:3000/webhooks/gateway \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Event: payment.succeeded" \
  -H "X-Gateway-Signature: t=$ts,v1=$signature" \
  --data-raw "$payload"
BASH,
            'php' => <<<'PHP'
<?php
// Use the raw body: re-encoding parsed JSON changes the bytes and breaks the signature.
$payload = file_get_contents('php://input');
$header = $_SERVER['HTTP_X_GATEWAY_SIGNATURE'] ?? '';

if (! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $header, $m)) {
    http_response_code(400);
    exit;
}

$expected = hash_hmac('sha256', $m[1].'.'.$payload, getenv('GATEWAY_WEBHOOK_SECRET'));
if (! hash_equals($expected, $m[2]) || abs(time() - (int) $m[1]) > 300) {
    http_response_code(400);
    exit;
}

$event = json_decode($payload, true);
// Deliveries can repeat: skip events whose $event['id'] you have already handled.
http_response_code(200);
PHP,
            'node' => <<<'JS'
import express from 'express';
import { createHmac, timingSafeEqual } from 'node:crypto';

const app = express();

// express.raw keeps the exact bytes that were signed.
app.post('/webhooks/gateway', express.raw({ type: 'application/json' }), (req, res) => {
  const match = /^t=(\d+),v1=([a-f0-9]{64})$/.exec(req.get('X-Gateway-Signature') ?? '');
  if (!match) return res.sendStatus(400);
  const [, timestamp, received] = match;

  const expected = createHmac('sha256', process.env.GATEWAY_WEBHOOK_SECRET)
    .update(`${timestamp}.${req.body}`)
    .digest('hex');
  const fresh = Math.abs(Date.now() / 1000 - Number(timestamp)) <= 300;
  if (!fresh || !timingSafeEqual(Buffer.from(expected), Buffer.from(received))) return res.sendStatus(400);

  const event = JSON.parse(req.body);
  // Deliveries can repeat: skip events whose event.id you have already handled.
  res.sendStatus(200);
});

app.listen(3000);
JS,
            'python' => <<<'PY'
import hashlib
import hmac
import os
import re
import time

from flask import Flask, abort, request

app = Flask(__name__)


@app.post("/webhooks/gateway")
def gateway_webhook():
    payload = request.get_data()  # raw bytes, exactly as signed
    match = re.fullmatch(r"t=(\d+),v1=([a-f0-9]{64})", request.headers.get("X-Gateway-Signature", ""))
    if not match:
        abort(400)
    timestamp, received = match.groups()

    expected = hmac.new(
        os.environ["GATEWAY_WEBHOOK_SECRET"].encode(), timestamp.encode() + b"." + payload, hashlib.sha256
    ).hexdigest()
    if not hmac.compare_digest(expected, received) or abs(time.time() - int(timestamp)) > 300:
        abort(400)

    event = request.get_json()
    # Deliveries can repeat: skip events whose event["id"] you have already handled.
    return "", 200
PY,
        ];
    }

    /**
     * The API reference: one entry per endpoint, with a ready-to-run call.
     * "request" holds the raw [path, body] used for the call samples and
     * the Postman collection.
     *
     * @return list<array{id: string, method: string, path: string, summary: string, description: string, params: list<array{0: string, 1: string, 2: string}>, request: array{0: string, 1?: array<string, mixed>}, call: array<string, string>, status: int, response: string}>
     */
    public function endpoints(): array
    {
        return array_map(
            fn (array $endpoint) => $endpoint + ['call' => $this->call($endpoint['method'], ...$endpoint['request'])],
            $this->endpointDefinitions(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function endpointDefinitions(): array
    {
        $id = self::EXAMPLE_PAYMENT_ID;
        $payment = $this->examplePayment();
        $captured = array_merge($payment, ['status' => 'succeeded', 'amount_captured' => 2000]);
        $refund = [
            'id' => 're_01j9z3m2n4p6r8s0t2v4w6x8y0',
            'object' => 'refund',
            'payment_id' => $id,
            'amount' => 500,
            'reason' => 'requested_by_customer',
            'status' => 'succeeded',
            'created_at' => '2026-10-18T09:05:00+00:00',
        ];
        $createBody = [
            'payment_method' => 'CARD',
            'amount' => 2500,
            'currency' => 'USD',
            'capture_method' => 'manual',
            'description' => 'Order #1001',
            'metadata' => ['order_id' => '1001'],
            'card' => ['number' => '4242424242424242', 'exp_month' => 12, 'exp_year' => now()->year + 4, 'cvc' => '123'],
        ];

        return [
            [
                'id' => 'create-payment',
                'method' => 'POST',
                'path' => '/payments',
                'summary' => 'Create a payment',
                'description' => 'Start a payment with the provider assigned to your account for the payment method. Cards are authorized: with capture_method "automatic" (the default) the funds are captured at once; with "manual" the payment waits in requires_capture until you capture or cancel it. Other methods return 201 in requires_action with a next_action for the customer (see Payment methods). A decline returns 402 with the failed payment under "payment".',
                'params' => [
                    ['payment_method', 'string, required', 'One of: '.implode(', ', array_column(PaymentMethod::cases(), 'value')).'. Must be enabled on your account.'],
                    ['amount', 'integer, required', '1 to '.number_format(config('gateway.max_amount')).', in minor units.'],
                    ['currency', 'string, required', 'One of: '.implode(', ', config('gateway.currencies')).'. Only PHP for methods other than CARD.'],
                    ['capture_method', 'string', 'CARD only. "automatic" (default) or "manual".'],
                    ['description', 'string', 'Up to 255 characters.'],
                    ['metadata', 'object', 'Up to 20 string values of up to 500 characters each.'],
                    ['card.number', 'string, CARD only', '13–19 digits passing the Luhn check. Spaces and dashes are allowed.'],
                    ['card.exp_month', 'integer, CARD only', '1–12.'],
                    ['card.exp_year', 'integer, CARD only', 'Four digits; the card must not be expired.'],
                    ['card.cvc', 'string, CARD only', '3 or 4 digits. Never stored.'],
                ],
                'request' => ['/api/v1/payments', $createBody],
                'status' => 201,
                'response' => static::json($payment),
            ],
            [
                'id' => 'list-payments',
                'method' => 'GET',
                'path' => '/payments',
                'summary' => 'List payments',
                'description' => 'Your payments, newest first. Cursor-paginated: request links.next until it is null.',
                'params' => [
                    ['limit', 'integer, query', '1–100, default 25.'],
                ],
                'request' => ['/api/v1/payments?limit=10'],
                'status' => 200,
                'response' => static::json([
                    'data' => [$payment],
                    'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
                    'meta' => ['path' => $this->baseUrl.'/api/v1/payments', 'per_page' => 10, 'next_cursor' => null, 'prev_cursor' => null],
                ]),
            ],
            [
                'id' => 'get-payment',
                'method' => 'GET',
                'path' => '/payments/{id}',
                'summary' => 'Retrieve a payment',
                'description' => 'One payment by ID. Payments that belong to other merchants return 404.',
                'params' => [],
                'request' => ["/api/v1/payments/{$id}"],
                'status' => 200,
                'response' => static::json($payment),
            ],
            [
                'id' => 'capture-payment',
                'method' => 'POST',
                'path' => '/payments/{id}/capture',
                'summary' => 'Capture a payment',
                'description' => 'Capture a requires_capture payment. Send a smaller amount for a partial capture (the rest is released), or no body to capture everything.',
                'params' => [
                    ['amount', 'integer', 'Up to the authorized amount. Defaults to the full amount.'],
                ],
                'request' => ["/api/v1/payments/{$id}/capture", ['amount' => 2000]],
                'status' => 200,
                'response' => static::json($captured),
            ],
            [
                'id' => 'cancel-payment',
                'method' => 'POST',
                'path' => '/payments/{id}/cancel',
                'summary' => 'Cancel a payment',
                'description' => 'Release a requires_capture authorization, or abandon a requires_action payment the customer has not completed. No body. Captured payments cannot be canceled; refund them instead.',
                'params' => [],
                'request' => ["/api/v1/payments/{$id}/cancel"],
                'status' => 200,
                'response' => static::json(array_merge($payment, ['status' => 'canceled'])),
            ],
            [
                'id' => 'create-refund',
                'method' => 'POST',
                'path' => '/payments/{id}/refunds',
                'summary' => 'Refund a payment',
                'description' => 'Refund a succeeded or partially_refunded payment. The total refunded can never exceed the captured amount.',
                'params' => [
                    ['amount', 'integer', 'Defaults to everything still refundable.'],
                    ['reason', 'string', 'duplicate, fraudulent or requested_by_customer.'],
                ],
                'request' => ["/api/v1/payments/{$id}/refunds", ['amount' => 500, 'reason' => 'requested_by_customer']],
                'status' => 201,
                'response' => static::json($refund),
            ],
            [
                'id' => 'list-refunds',
                'method' => 'GET',
                'path' => '/payments/{id}/refunds',
                'summary' => 'List refunds',
                'description' => 'All refunds for a payment, newest first.',
                'params' => [],
                'request' => ["/api/v1/payments/{$id}/refunds"],
                'status' => 200,
                'response' => static::json(['data' => [$refund]]),
            ],
        ];
    }

    /**
     * A GCASH payment: the request in each language and the pending
     * response with the next_action the customer must complete.
     *
     * @return array{call: array<string, string>, response: string}
     */
    public function walletExample(): array
    {
        $body = ['payment_method' => 'GCASH', 'amount' => 50000, 'currency' => 'PHP', 'description' => 'Order #1002'];

        return [
            'call' => $this->call('POST', '/api/v1/payments', $body),
            'response' => static::json(array_merge($this->examplePayment(), [
                'id' => 'pay_01j9z3p4r6t8v0x2z4b6d8f0h2',
                'payment_method' => 'GCASH',
                'provider' => 'sim_beta',
                'amount' => 50000,
                'fee_amount' => 1150,
                'currency' => 'PHP',
                'status' => 'requires_action',
                'capture_method' => 'automatic',
                'card' => null,
                'next_action' => ['type' => 'redirect', 'url' => 'https://simulator.invalid/sim_beta/gcash/sim_01j9z3p4r6t8v0x2z4b6d8f0h3'],
                'expires_at' => '2026-10-18T09:30:00+00:00',
                'description' => 'Order #1002',
                'metadata' => (object) [],
            ])),
        ];
    }

    /**
     * Pretty-printed JSON as used throughout the docs.
     *
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function examplePayment(): array
    {
        return [
            'id' => self::EXAMPLE_PAYMENT_ID,
            'object' => 'payment',
            'payment_method' => 'CARD',
            'provider' => 'sim_alpha',
            'amount' => 2500,
            'amount_captured' => 0,
            'amount_refunded' => 0,
            'fee_amount' => 1573,
            'currency' => 'USD',
            'status' => 'requires_capture',
            'capture_method' => 'manual',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => now()->year + 4],
            'next_action' => null,
            'expires_at' => null,
            'failure_code' => null,
            'description' => 'Order #1001',
            'metadata' => ['order_id' => '1001'],
            'created_at' => '2026-10-18T09:00:00+00:00',
        ];
    }
}
