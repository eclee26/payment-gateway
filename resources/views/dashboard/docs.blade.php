@php
    $endpoints = $samples->endpoints();
    $guides = [
        'quickstart' => 'Quickstart',
        'postman' => 'Postman',
        'authentication' => 'Authentication',
        'idempotency' => 'Idempotency',
        'payment-methods' => 'Payment methods',
        'errors' => 'Errors',
        'test-cards' => 'Test cards',
        'webhooks' => 'Webhooks',
    ];
    $methodBadge = [
        'GET' => 'bg-green-100 text-green-800',
        'POST' => 'bg-indigo-100 text-indigo-800',
    ];
    $firstPaymentBody = [
        'payment_method' => 'CARD',
        'amount' => 2500,
        'currency' => 'USD',
        'card' => ['number' => '4242424242424242', 'exp_month' => 12, 'exp_year' => now()->year + 4, 'cvc' => '123'],
    ];
    $firstPayment = $samples->call('POST', '/api/v1/payments', $firstPaymentBody);
    $retryPayment = $samples->call('POST', '/api/v1/payments', $firstPaymentBody, 'order-1001');
    $secretEnv = implode(PHP_EOL, [
        '# macOS / Linux / WSL',
        'export GATEWAY_SECRET="sk_your_secret"',
        '',
        '# Windows PowerShell',
        '$env:GATEWAY_SECRET = "sk_your_secret"',
    ]);
    $card = 'bg-white shadow-sm sm:rounded-lg p-6';
    $h2 = 'group flex items-center gap-2 text-lg font-semibold text-gray-900';
    $h3 = 'mt-6 font-medium text-gray-900';
    $p = 'mt-2 text-sm text-gray-600';
    $th = 'px-3 py-2 text-start font-medium text-gray-500';
    $td = 'px-3 py-2 align-top';
    $inlineCode = 'rounded bg-gray-100 px-1 py-0.5 font-mono text-xs text-gray-800';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('API documentation') }}</h2>
            <span class="rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700">{{ $baseUrl }}/api/v1</span>
        </div>
    </x-slot>

    <div class="py-12" x-data="apiDocs('curl')">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 lg:grid lg:grid-cols-[13rem_1fr] lg:gap-8">
            {{-- Sidebar --}}
            <nav class="hidden lg:block" aria-label="{{ __('Documentation') }}">
                <div class="sticky top-6 space-y-6 text-sm">
                    <div>
                        <div class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Guides') }}</div>
                        <ul class="mt-2 space-y-1">
                            @foreach ($guides as $id => $label)
                                <li><a href="#{{ $id }}" class="block rounded px-2 py-1 text-gray-600 hover:bg-gray-100 hover:text-gray-900">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <div class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('API reference') }}</div>
                        <ul class="mt-2 space-y-1">
                            @foreach ($endpoints as $endpoint)
                                <li>
                                    <a href="#{{ $endpoint['id'] }}" class="flex items-center gap-2 rounded px-2 py-1 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                                        <span class="w-9 shrink-0 rounded px-1 text-center font-mono text-[10px] font-semibold {{ $methodBadge[$endpoint['method']] }}">{{ $endpoint['method'] }}</span>
                                        <span class="truncate">{{ $endpoint['summary'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="min-w-0 space-y-6">
                {{-- Language picker: switches every code sample on the page --}}
                <div class="flex flex-wrap items-center gap-2 bg-white shadow-sm sm:rounded-lg px-6 py-3 text-sm">
                    <span class="text-gray-500">{{ __('Show code in') }}</span>
                    @foreach (\App\Support\ApiDocSamples::LANGUAGES as $lang => $label)
                        <button type="button" @click="lang = '{{ $lang }}'"
                                :class="lang === '{{ $lang }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                :aria-pressed="lang === '{{ $lang }}'"
                                class="rounded-full px-3 py-1 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-400">{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Quickstart --}}
                <section id="quickstart" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Quickstart') }} <a href="#quickstart" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">{{ __('Make your first payment in four steps. Payments run against a simulated processor, so no real money moves.') }}</p>

                    <ol class="mt-4 space-y-6">
                        <li>
                            <h3 class="font-medium text-gray-900">1. {{ __('Get an API key') }}</h3>
                            @if ($keyId)
                                <p class="{{ $p }}">
                                    {{ __('The samples on this page are pre-filled with your newest active key,') }}
                                    <code class="{{ $inlineCode }}">{{ $keyId }}</code>.
                                    {{ __('Its secret was shown once when you created it; if you lost it, create a new key on the') }}
                                    <a href="{{ route('dashboard.api-keys.index') }}" class="text-indigo-600 hover:underline">{{ __('API keys') }}</a> {{ __('page.') }}
                                </p>
                            @else
                                <div class="mt-2 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
                                    {{ __('You have no active API key yet.') }}
                                    <a href="{{ route('dashboard.api-keys.index') }}" class="font-medium underline">{{ __('Create one') }}</a>
                                    {{ __('and this page will fill in your key ID automatically.') }}
                                </div>
                            @endif
                        </li>
                        <li>
                            <h3 class="font-medium text-gray-900">2. {{ __('Put the secret in an environment variable') }}</h3>
                            <p class="{{ $p }}">{{ __('Never commit it or ship it in browser or mobile code. Call the API from your server only.') }}</p>
                            <x-docs.code class="mt-3" :code="$secretEnv" />
                        </li>
                        <li>
                            <h3 class="font-medium text-gray-900">3. {{ __('Add the signing helper') }}</h3>
                            <p class="{{ $p }}">{{ __('Every request must be signed (see Authentication). This helper does it for you; the rest of this page uses it.') }}</p>
                            <x-docs.code-tabs :samples="$samples->helper()" />
                        </li>
                        <li>
                            <h3 class="font-medium text-gray-900">4. {{ __('Create a payment') }}</h3>
                            <p class="{{ $p }}">{{ __('You should get HTTP 201 with "status": "succeeded". It then appears on your') }} <a href="{{ route('dashboard.payments.index') }}" class="text-indigo-600 hover:underline">{{ __('Payments') }}</a> {{ __('page.') }}</p>
                            <x-docs.code-tabs :samples="$firstPayment" />
                        </li>
                    </ol>
                </section>

                {{-- Postman --}}
                <section id="postman" class="{{ $card }} scroll-mt-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="{{ $h2 }}">{{ __('Postman collection') }} <a href="#postman" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                            <p class="{{ $p }}">{{ __('Every endpoint, ready to send. A pre-request script signs each request and adds a fresh Idempotency-Key to POSTs, so there is nothing to compute by hand.') }}</p>
                        </div>
                        {{-- data-turbo="false": let the browser download the file instead of Turbo fetching it. --}}
                        <a href="{{ route('dashboard.docs.postman') }}" download data-turbo="false"
                           class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            {{ __('Download collection') }}
                        </a>
                    </div>
                    <ol class="mt-4 list-decimal space-y-1 ps-5 text-sm text-gray-600">
                        <li>{{ __('In Postman, choose Import and drop in the downloaded file.') }}</li>
                        <li>
                            {{ __('Open the collection\'s Variables tab and paste your API key secret into the Current value of') }} <code class="{{ $inlineCode }}">secret</code>.
                            {{ __('The file never contains it; base_url and key_id are already filled in') }}@if (! $keyId) {{ __('(create an API key first, then download again)') }}@endif.
                        </li>
                        <li>{{ __('Send "Create payment": it saves payment_id for the requests after it. Or run the whole collection in the Runner; every request checks its expected status.') }}</li>
                    </ol>
                </section>

                {{-- Authentication --}}
                <section id="authentication" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Authentication') }} <a href="#authentication" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">{{ __('Each request is signed with HMAC-SHA256 using your API key secret. The helper above builds these headers:') }}</p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50"><tr><th class="{{ $th }}">{{ __('Header') }}</th><th class="{{ $th }}">{{ __('Value') }}</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="{{ $td }} font-mono">X-Api-Key</td><td class="{{ $td }}">{{ __('Your key ID (key_…).') }}</td></tr>
                                <tr><td class="{{ $td }} font-mono">X-Timestamp</td><td class="{{ $td }}">{{ __('Current Unix time in seconds, within ±:seconds s of server time.', ['seconds' => config('gateway.signature_tolerance')]) }}</td></tr>
                                <tr><td class="{{ $td }} font-mono">X-Nonce</td><td class="{{ $td }}">{{ __('Unique per request: 16–64 characters of A–Z, a–z, 0–9 or "-". Reused nonces are rejected.') }}</td></tr>
                                <tr><td class="{{ $td }} font-mono">X-Signature</td><td class="{{ $td }}"><code class="{{ $inlineCode }}">v1=</code> {{ __('followed by the lowercase hex HMAC-SHA256 of the string to sign.') }}</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="{{ $h3 }}">{{ __('String to sign') }}</h3>
                    <p class="{{ $p }}">{{ __('Five values joined with a newline. Sign the path exactly as you send it, including the query string. With no body, hash the empty string.') }}</p>
                    <x-docs.code class="mt-3" :code="implode(PHP_EOL, ['<timestamp>', '<nonce>', '<METHOD in uppercase>', '<path including ?query, e.g. /api/v1/payments?limit=10>', '<lowercase hex SHA-256 of the raw body>'])" />

                    <div class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                        <span class="font-medium text-gray-900">{{ __('Getting 401 authentication_failed?') }}</span>
                        {{ __('The error is deliberately generic. Check that your clock is in sync, that each request uses a new nonce, that you sign the exact bytes you send (re-serializing JSON after signing breaks it), that the path includes /api/v1 and any query string, and that the key is not revoked. Repeated failures from one IP are temporarily blocked.') }}
                    </div>
                </section>

                {{-- Idempotency --}}
                <section id="idempotency" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Idempotency') }} <a href="#idempotency" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">{{ __('Every POST needs an Idempotency-Key header (1–255 printable characters). Use one per logical operation, such as an order ID, and send the same key when you retry after a timeout or network error.') }}</p>
                    <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-gray-600">
                        <li>{{ __('Same key, same request: you get the original response again with Idempotent-Replayed: true. Nothing is charged or refunded twice.') }}</li>
                        <li>{{ __('Same key, different body or endpoint: 422 idempotency_key_reused.') }}</li>
                        <li>{{ __('5xx responses are not stored, so you can retry them with the same key.') }}</li>
                        <li>{{ __('Keys are scoped to your account and expire after :hours hours.', ['hours' => config('gateway.idempotency_ttl_hours')]) }}</li>
                    </ul>
                    <h3 class="{{ $h3 }}">{{ __('Retrying safely') }}</h3>
                    <x-docs.code-tabs :samples="$retryPayment" />
                </section>

                {{-- Errors --}}
                <section id="errors" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Errors') }} <a href="#errors" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">{{ __('Errors always share this shape. Validation errors add an "errors" object keyed by field. Branch on "code", not on "message".') }}</p>
                    <x-docs.code class="mt-3" :code="\App\Support\ApiDocSamples::json(['error' => ['type' => 'invalid_request_error', 'code' => 'invalid_state', 'message' => 'Cannot capture a payment with status [succeeded].']])" />
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50"><tr><th class="{{ $th }}">{{ __('Status') }}</th><th class="{{ $th }}">{{ __('Code') }}</th><th class="{{ $th }}">{{ __('What to do') }}</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="{{ $td }}">400</td><td class="{{ $td }} font-mono">idempotency_key_required</td><td class="{{ $td }}">{{ __('Add an Idempotency-Key header to the POST.') }}</td></tr>
                                <tr><td class="{{ $td }}">401</td><td class="{{ $td }} font-mono">authentication_failed</td><td class="{{ $td }}">{{ __('Fix the signature; see the checklist under Authentication.') }}</td></tr>
                                <tr><td class="{{ $td }}">402</td><td class="{{ $td }} font-mono">card_declined, insufficient_funds, expired_card</td><td class="{{ $td }}">{{ __('Card declined (type card_error). Ask the customer for another card.') }}</td></tr>
                                <tr><td class="{{ $td }}">402</td><td class="{{ $td }}">{{ __('Provider decline code') }}</td><td class="{{ $td }}">{{ __('Non-card payment declined (type payment_error). Offer another payment method.') }}</td></tr>
                                <tr><td class="{{ $td }}">404</td><td class="{{ $td }} font-mono">resource_missing</td><td class="{{ $td }}">{{ __('No such payment on your account. Check the ID.') }}</td></tr>
                                <tr><td class="{{ $td }}">409</td><td class="{{ $td }} font-mono">idempotency_request_in_progress</td><td class="{{ $td }}">{{ __('The first request with this key is still running. Retry shortly.') }}</td></tr>
                                <tr><td class="{{ $td }}">415</td><td class="{{ $td }} font-mono">unsupported_media_type</td><td class="{{ $td }}">{{ __('Send the body as JSON with Content-Type: application/json.') }}</td></tr>
                                <tr><td class="{{ $td }}">422</td><td class="{{ $td }} font-mono">validation_failed</td><td class="{{ $td }}">{{ __('Fix the fields listed in "errors".') }}</td></tr>
                                <tr><td class="{{ $td }}">422</td><td class="{{ $td }} font-mono">payment_method_unavailable</td><td class="{{ $td }}">{{ __('That payment_method is not enabled on your account; see Payment methods.') }}</td></tr>
                                <tr><td class="{{ $td }}">422</td><td class="{{ $td }} font-mono">invalid_state</td><td class="{{ $td }}">{{ __('The action is not allowed in the payment\'s current status. Fetch it to check.') }}</td></tr>
                                <tr><td class="{{ $td }}">422</td><td class="{{ $td }} font-mono">amount_too_large</td><td class="{{ $td }}">{{ __('Capture or refund less; the message says how much is available.') }}</td></tr>
                                <tr><td class="{{ $td }}">422</td><td class="{{ $td }} font-mono">idempotency_key_reused</td><td class="{{ $td }}">{{ __('Use a new key for a different request.') }}</td></tr>
                                <tr><td class="{{ $td }}">429</td><td class="{{ $td }} font-mono">rate_limited, too_many_failed_attempts</td><td class="{{ $td }}">{{ __('Wait for the Retry-After header. Limit: :count requests per minute.', ['count' => config('gateway.rate_limit')]) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- Payment methods --}}
                <section id="payment-methods" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Payment methods') }} <a href="#payment-methods" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">{{ __('Every payment names a payment_method. Each method you have enabled is processed by one provider (bank or PSP) assigned to your account; the API is the same whichever provider it is. See') }} <a href="{{ route('dashboard.payment-methods.index') }}" class="text-indigo-600 hover:underline">{{ __('Payment methods') }}</a> {{ __('for what is enabled and the fee per method. A method that is not enabled returns 422 payment_method_unavailable.') }}</p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50"><tr><th class="{{ $th }}">payment_method</th><th class="{{ $th }}">{{ __('Flow') }}</th><th class="{{ $th }}">next_action.type</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="{{ $td }} font-mono">CARD</td><td class="{{ $td }}">{{ __('Immediate: succeeded, requires_capture or failed. Requires "card".') }}</td><td class="{{ $td }}">—</td></tr>
                                <tr><td class="{{ $td }} font-mono">QRPH</td><td class="{{ $td }}">{{ __('Show the QR code; the customer scans it in their banking or wallet app.') }}</td><td class="{{ $td }} font-mono">qr_code (qr_string)</td></tr>
                                <tr><td class="{{ $td }} font-mono">GCASH, MAYA, INSTAPAY, PESONET</td><td class="{{ $td }}">{{ __('Redirect the customer to url to approve the payment.') }}</td><td class="{{ $td }} font-mono">redirect (url)</td></tr>
                                <tr><td class="{{ $td }} font-mono">ECPAY</td><td class="{{ $td }}">{{ __('Give the customer the reference to pay at an outlet.') }}</td><td class="{{ $td }} font-mono">display_reference (reference)</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="{{ $p }}">{{ __('Methods other than CARD accept PHP only, cannot use capture_method, and must not include "card". They start in requires_action; when the provider reports the outcome the payment moves to succeeded or failed and you receive a payment.succeeded or payment.failed webhook. fee_amount is the provider fee in minor units.') }}</p>
                    <h3 class="{{ $h3 }}">{{ __('Example: GCash') }}</h3>
                    @php($wallet = $samples->walletExample())
                    <x-docs.code-tabs :samples="$wallet['call']" />
                    <x-docs.code class="mt-3" :code="$wallet['response']" />
                    @if (config('gateway.simulator_enabled'))
                        <p class="{{ $p }}">{{ __('Testing: open a requires_action payment on your Payments page and use "Simulate success" or "Simulate failure" to act as the provider.') }}</p>
                    @endif
                </section>

                {{-- Test cards --}}
                <section id="test-cards" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Test cards') }} <a href="#test-cards" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">{{ __('Use any future expiry date and any 3-digit CVC. Any other Luhn-valid number is approved. Supported currencies: :list.', ['list' => implode(', ', config('gateway.currencies'))]) }}</p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50"><tr><th class="{{ $th }}">{{ __('Number') }}</th><th class="{{ $th }}">{{ __('Result') }}</th><th class="{{ $th }}"></th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach (['4242424242424242' => null] + \App\Services\Processor\SimulatedProcessor::DECLINES as $number => $code)
                                    <tr x-data="copyable">
                                        <td class="{{ $td }} font-mono"><span x-ref="code">{{ $number }}</span></td>
                                        <td class="{{ $td }}">
                                            @if ($code)
                                                {{ __('Declined') }}: <code class="{{ $inlineCode }}">{{ $code }}</code>
                                            @else
                                                {{ __('Approved') }}
                                            @endif
                                        </td>
                                        <td class="{{ $td }} text-end">
                                            <button type="button" @click="copy" class="text-xs font-medium text-indigo-600 hover:underline" x-text="copied ? 'Copied!' : 'Copy'">Copy</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- Webhooks --}}
                <section id="webhooks" class="{{ $card }} scroll-mt-6">
                    <h2 class="{{ $h2 }}">{{ __('Webhooks') }} <a href="#webhooks" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a></h2>
                    <p class="{{ $p }}">
                        {{ __('Set an HTTPS endpoint on the') }}
                        <a href="{{ route('dashboard.webhooks.edit') }}" class="text-indigo-600 hover:underline">{{ __('Webhooks') }}</a>
                        {{ __('page, which also shows recent deliveries. Respond with any 2xx status; other responses are retried after 10s, 1m, 5m, 30m and 2h.') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['payment.succeeded', 'payment.failed', 'payment.captured', 'payment.canceled', 'refund.succeeded'] as $event)
                            <code class="{{ $inlineCode }}">{{ $event }}</code>
                        @endforeach
                    </div>

                    <h3 class="{{ $h3 }}">{{ __('What a delivery looks like') }}</h3>
                    <x-docs.code class="mt-3" :code="implode(PHP_EOL, ['POST /your/webhook/endpoint', 'Content-Type: application/json', 'X-Gateway-Event: payment.succeeded', 'X-Gateway-Signature: t=1760778000,v1=5f2b…', '', \App\Support\ApiDocSamples::json(['id' => 'evt_01j9z3p4q6r8s0t2u4v6w8x0y2', 'type' => 'payment.succeeded', 'created' => 1760778000, 'data' => ['object' => ['id' => 'pay_…', 'object' => 'payment', 'status' => 'succeeded']]])])" />

                    <h3 class="{{ $h3 }}">{{ __('Verify the signature') }}</h3>
                    <p class="{{ $p }}">{{ __('Compute HMAC-SHA256 of "<t>.<raw body>" with your webhook signing secret and compare it with v1 in constant time. Reject old timestamps, and use the event id to skip duplicates. The cURL tab sends a correctly signed test event to your local endpoint.') }}</p>
                    <x-docs.code-tabs :samples="$samples->webhookReceiver()" :labels="['curl' => 'cURL (test delivery)'] + \App\Support\ApiDocSamples::LANGUAGES" />
                </section>

                {{-- API reference --}}
                <div class="pt-4">
                    <h2 class="text-xl font-semibold text-gray-900">{{ __('API reference') }}</h2>
                    <p class="{{ $p }}">
                        {{ __('Paths are relative to') }} <code class="{{ $inlineCode }}">{{ $baseUrl }}/api/v1</code>.
                        {{ __('Amounts are integers in minor units (2500 = 25.00 USD). Replace the example payment ID with one of yours.') }}
                    </p>
                </div>

                @foreach ($endpoints as $endpoint)
                    <section id="{{ $endpoint['id'] }}" class="{{ $card }} scroll-mt-6">
                        <h2 class="{{ $h2 }}">
                            <span class="rounded px-2 py-0.5 font-mono text-xs font-semibold {{ $methodBadge[$endpoint['method']] }}">{{ $endpoint['method'] }}</span>
                            <span class="font-mono text-base">{{ $endpoint['path'] }}</span>
                            <a href="#{{ $endpoint['id'] }}" class="text-gray-300 opacity-0 group-hover:opacity-100" aria-hidden="true">#</a>
                        </h2>
                        <p class="mt-1 text-sm font-medium text-gray-700">{{ $endpoint['summary'] }}</p>
                        <p class="{{ $p }}">{{ $endpoint['description'] }}</p>

                        @if ($endpoint['params'])
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50"><tr><th class="{{ $th }}">{{ __('Parameter') }}</th><th class="{{ $th }}">{{ __('Type') }}</th><th class="{{ $th }}">{{ __('Description') }}</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($endpoint['params'] as [$name, $type, $description])
                                            <tr><td class="{{ $td }} font-mono">{{ $name }}</td><td class="{{ $td }} text-gray-500">{{ $type }}</td><td class="{{ $td }}">{{ $description }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($endpoint['id'] === 'create-payment')
                            <h3 class="{{ $h3 }}">{{ __('Payment statuses') }}</h3>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <tbody class="divide-y divide-gray-100">
                                        <tr><td class="{{ $td }} font-mono">requires_action</td><td class="{{ $td }}">{{ __('Waiting for the customer to complete next_action (methods other than CARD).') }}</td></tr>
                                        <tr><td class="{{ $td }} font-mono">requires_capture</td><td class="{{ $td }}">{{ __('Authorized, waiting for capture or cancel (manual capture only).') }}</td></tr>
                                        <tr><td class="{{ $td }} font-mono">succeeded</td><td class="{{ $td }}">{{ __('Funds captured.') }}</td></tr>
                                        <tr><td class="{{ $td }} font-mono">failed</td><td class="{{ $td }}">{{ __('Declined; see failure_code.') }}</td></tr>
                                        <tr><td class="{{ $td }} font-mono">canceled</td><td class="{{ $td }}">{{ __('Authorization released, or pending payment abandoned.') }}</td></tr>
                                        <tr><td class="{{ $td }} font-mono">partially_refunded</td><td class="{{ $td }}">{{ __('Part of the captured amount was refunded.') }}</td></tr>
                                        <tr><td class="{{ $td }} font-mono">refunded</td><td class="{{ $td }}">{{ __('The full captured amount was refunded.') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="mt-4 grid gap-4 xl:grid-cols-2">
                            <div class="min-w-0">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Request') }}</h3>
                                <x-docs.code-tabs :samples="$endpoint['call']" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Response') }} <span class="ms-1 rounded bg-green-100 px-1.5 py-0.5 font-mono text-green-800 normal-case">{{ $endpoint['status'] }}</span></h3>
                                <x-docs.code class="mt-3" :code="$endpoint['response']" />
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
