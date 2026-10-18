<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $merchant->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">{{ __('Active API keys') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $activeKeys }}</div>
                    <a href="{{ route('dashboard.api-keys.index') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">{{ __('Manage keys') }}</a>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">{{ __('Webhook endpoint') }}</div>
                    <div class="mt-1 truncate text-gray-900">{{ $merchant->webhook_url ?? __('Not configured') }}</div>
                    <a href="{{ route('dashboard.webhooks.edit') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">{{ __('Configure webhooks') }}</a>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between p-6 pb-0">
                    <h3 class="font-medium text-gray-900">{{ __('Recent payments') }}</h3>
                    <a href="{{ route('dashboard.payments.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('View all') }}</a>
                </div>
                <div class="pt-4">
                    @include('dashboard.payments._table')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
