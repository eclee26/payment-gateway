# Payment Gateway

A small, security-focused payment gateway built on Laravel 12. Merchants sign
up on a web dashboard, create API keys, and call a JSON API to authorize,
capture, cancel and refund card payments against a **simulated processor**.
No real money moves.

> **Not PCI-DSS compliant.** Raw card numbers and CVCs are never stored, logged
> or returned, but accepting them server-side still puts this app in PCI scope.
> A real deployment would tokenize cards with a vault or hosted fields.

## Running it

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose run --rm vite sh -c "npm install && npm run build"   # dashboard CSS/JS
```

- Dashboard: http://localhost:8000 (register, then open **API keys**)
- Tests: `docker compose exec app php artisan test`

## Security features

| Feature | Where |
|---|---|
| HMAC-SHA256 request signing, constant-time compare | `app/Http/Middleware/VerifyHmacSignature.php` |
| Replay protection: ±300s timestamp window plus single-use nonces | same |
| Generic 401s, per-IP throttling of failed authentication | same |
| Idempotency keys, atomic with the request's own writes | `app/Http/Middleware/EnsureIdempotency.php` |
| Row locks on capture, cancel and refund (no double capture or over-refund) | `app/Services/PaymentService.php` |
| Per-merchant rate limiting | `app/Providers/AppServiceProvider.php` |
| Merchant-scoped lookups: other merchants' records return 404 | `app/Http/Controllers/Api/ApiController.php` |
| Card data: Luhn and expiry validation; only brand, last4 and keyed fingerprint stored | `app/Http/Requests/Api/CreatePaymentRequest.php`, `app/Support/Card.php` |
| API secrets and webhook secrets encrypted at rest, shown once | `app/Models/ApiKey.php`, `app/Models/Merchant.php` |
| Signed outgoing webhooks with retry and backoff | `app/Jobs/SendWebhook.php` |
| Webhook SSRF guard: public HTTPS only, IP pinned against DNS rebinding | `app/Support/SafeUrl.php` |
| Security headers, CSP, `Cache-Control: no-store` on the API | `app/Http/Middleware/SecurityHeaders.php` |
| Password re-confirmation before any credential change | `routes/web.php` |

## API

Base URL: `http://localhost:8000/api/v1`. Amounts are integers in minor units
(cents). Every request must be signed. Every `POST` needs an `Idempotency-Key`.

### Signing a request

| Header | Value |
|---|---|
| `X-Api-Key` | your key ID (`key_…`) |
| `X-Timestamp` | current unix time in seconds |
| `X-Nonce` | unique per request, 16–64 chars of `[A-Za-z0-9-]` (a UUID works) |
| `X-Signature` | `v1=` + hex HMAC-SHA256 of the string below, keyed with your secret |

```
<timestamp>\n<nonce>\n<METHOD>\n<path including ?query>\n<hex sha256 of raw body>
```

For a request with no body, hash the empty string.

PowerShell example (Windows PowerShell 5.1 or later):

```powershell
$keyId  = 'key_...'
$secret = 'sk_...'
$path   = '/api/v1/payments'
$body   = '{"amount":2500,"currency":"USD","card":{"number":"4242424242424242","exp_month":12,"exp_year":2030,"cvc":"123"}}'

$ts    = [string][DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
$nonce = [guid]::NewGuid().ToString()
$sha   = [Security.Cryptography.SHA256]::Create()
$bodyHash = -join ($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($body)) | ForEach-Object { $_.ToString('x2') })
$toSign   = "$ts`n$nonce`nPOST`n$path`n$bodyHash"
$hmac     = New-Object Security.Cryptography.HMACSHA256 (,[Text.Encoding]::UTF8.GetBytes($secret))
$sig      = -join ($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($toSign)) | ForEach-Object { $_.ToString('x2') })

Invoke-RestMethod -Method Post -Uri "http://localhost:8000$path" -ContentType 'application/json' -Body $body -Headers @{
    'X-Api-Key' = $keyId; 'X-Timestamp' = $ts; 'X-Nonce' = $nonce; 'X-Signature' = "v1=$sig"
    'Idempotency-Key' = [guid]::NewGuid().ToString()
}
```

### Endpoints

| Method | Path | Notes |
|---|---|---|
| `POST` | `/payments` | `amount`, `currency`, `card{number,exp_month,exp_year,cvc}`, optional `capture_method` (`automatic` or `manual`), `description`, `metadata` |
| `GET` | `/payments` | cursor-paginated, `?limit=1..100` |
| `GET` | `/payments/{id}` | |
| `POST` | `/payments/{id}/capture` | optional `amount` (at most the authorized amount) |
| `POST` | `/payments/{id}/cancel` | only while `requires_capture` |
| `POST` | `/payments/{id}/refunds` | optional `amount` (default: the remaining amount), `reason` |
| `GET` | `/payments/{id}/refunds` | |

Errors always use the shape `{"error": {"type", "code", "message"}}`. A declined
card returns `402` with the failed payment under `payment`.

### Idempotency

Retrying a `POST` with the same `Idempotency-Key` returns the original
response with `Idempotent-Replayed: true`, and nothing runs twice. Reusing a key
with a different body or endpoint returns `422 idempotency_key_reused`. Keys are
scoped per merchant and kept for 24 hours. Responses with status 5xx are not
stored, so those requests can be retried safely.

### Test cards

| Number | Result |
|---|---|
| `4242 4242 4242 4242` | approved |
| `4000 0000 0000 0002` | `card_declined` |
| `4000 0000 0000 9995` | `insufficient_funds` |
| `4000 0000 0000 0069` | `expired_card` |

## Webhooks

Configure an HTTPS endpoint under **Webhooks** in the dashboard. Events:
`payment.succeeded`, `payment.failed`, `payment.captured`, `payment.canceled`,
`refund.succeeded`. Each event is `POST`ed with:

```
X-Gateway-Signature: t=<unix>,v1=<hex HMAC-SHA256(webhook_secret, "<t>.<raw body>")>
```

Verify the signature with a constant-time compare, and reject events whose `t`
is too old. Deliveries that don't return 2xx are retried after 10s, 1m, 5m,
30m and 2h. Private, loopback and link-local addresses are always refused.
