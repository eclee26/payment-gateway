<x-app-layout :title="$merchant->name" :subtitle="__('Your account at a glance.')">
    <x-slot name="actions">
        <a href="{{ route('dashboard.docs') }}" class="btn-secondary">
            <x-icon name="book-open" class="size-4" /> {{ __('API docs') }}
        </a>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-stat :label="__('Active API keys')" :value="$activeKeys" icon="key" :href="route('dashboard.api-keys.index')" :hint="__('Manage keys')" />
        <x-stat :label="__('Webhook endpoint')" :value="$merchant->webhook_url ?? __('Not configured')" icon="bolt" :href="route('dashboard.webhooks.edit')" :hint="__('Configure webhooks')" />
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">{{ __('Recent payments') }}</h2>
            <a href="{{ route('dashboard.payments.index') }}" class="link text-sm">{{ __('View all') }}</a>
        </div>
        @include('dashboard.payments._table')
    </div>
</x-app-layout>
