<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Webhooks') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-medium text-gray-900">{{ __('Endpoint') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Events are POSTed as JSON and signed with the X-Gateway-Signature header: t=<timestamp>,v1=HMAC-SHA256(secret, "<timestamp>.<body>").') }}</p>

                <form method="POST" action="{{ route('dashboard.webhooks.update') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PUT')
                    <div class="flex-1">
                        <x-input-label for="webhook_url" :value="__('URL')" />
                        <x-text-input id="webhook_url" name="webhook_url" type="url" class="mt-1 block w-full" :value="old('webhook_url', $merchant->webhook_url)" placeholder="https://example.com/webhooks/gateway" />
                        <x-input-error :messages="$errors->get('webhook_url')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-medium text-gray-900">{{ __('Signing secret') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Rotating immediately invalidates the old secret.') }}</p>
                <form method="POST" action="{{ route('dashboard.webhooks.rotate-secret') }}" class="mt-4" x-data @submit="confirm('Rotate the webhook secret? Your endpoint must be updated with the new one.') || $event.preventDefault()">
                    @csrf
                    <x-secondary-button type="submit">{{ __('Rotate and reveal secret') }}</x-secondary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="p-6 pb-0 font-medium text-gray-900">{{ __('Recent deliveries') }}</h3>
                @if ($deliveries->isEmpty())
                    <p class="p-6 text-sm text-gray-500">{{ __('No deliveries yet.') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Event') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Attempts') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Last status') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Result') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Created') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($deliveries as $delivery)
                                    <tr>
                                        <td class="px-4 py-3 font-mono">{{ $delivery->event_type }}</td>
                                        <td class="px-4 py-3">{{ $delivery->attempts }}</td>
                                        <td class="px-4 py-3">{{ $delivery->last_response_status ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $delivery->delivered_at ? __('Delivered') : ($delivery->last_error ?? __('Pending')) }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $delivery->created_at->toDayDateTimeString() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
