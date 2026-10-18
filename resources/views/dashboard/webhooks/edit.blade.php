<x-app-layout :title="__('Webhooks')" :subtitle="__('Get notified when payments change state.')">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ __('Endpoint') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Events are POSTed as JSON and signed with the X-Gateway-Signature header: t=<timestamp>,v1=HMAC-SHA256(secret, "<timestamp>.<body>").') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('dashboard.webhooks.update') }}" class="card-body space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="webhook_url" :value="__('URL')" />
                    <x-text-input id="webhook_url" name="webhook_url" type="url" class="mt-1" :value="old('webhook_url', $merchant->webhook_url)" placeholder="https://example.com/webhooks/gateway" />
                    <x-input-error :messages="$errors->get('webhook_url')" class="mt-2" />
                </div>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ __('Signing secret') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Rotating immediately invalidates the old secret.') }}</p>
                </div>
            </div>
            <div class="card-body">
                {{-- Full page load: the response shows the secret once and is not a redirect, which Turbo can't render. --}}
                <form method="POST" action="{{ route('dashboard.webhooks.rotate-secret') }}" data-turbo="false" x-data @submit="confirm('Rotate the webhook secret? Your endpoint must be updated with the new one.') || $event.preventDefault()">
                    @csrf
                    <button type="submit" class="btn-secondary">
                        <x-icon name="arrow-path" class="size-4" /> {{ __('Rotate and reveal secret') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">{{ __('Recent deliveries') }}</h2></div>
        @if ($deliveries->isEmpty())
            <x-empty-state icon="bolt" :title="__('No deliveries yet.')" :description="__('Deliveries appear here once payments start changing state.')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Event') }}</th>
                            <th>{{ __('Attempts') }}</th>
                            <th>{{ __('Last status') }}</th>
                            <th>{{ __('Result') }}</th>
                            <th>{{ __('Created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td class="font-mono text-xs">{{ $delivery->event_type }}</td>
                                <td class="tabular-nums">{{ $delivery->attempts }}</td>
                                <td>{{ $delivery->last_response_status ?? '—' }}</td>
                                <td>
                                    @if ($delivery->delivered_at)
                                        <span class="badge-green">{{ __('Delivered') }}</span>
                                    @elseif ($delivery->last_error)
                                        <span class="badge-red" title="{{ $delivery->last_error }}">{{ Str::limit($delivery->last_error, 40) }}</span>
                                    @else
                                        <span class="badge-amber">{{ __('Pending') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-slate-500">{{ $delivery->created_at->toDayDateTimeString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
