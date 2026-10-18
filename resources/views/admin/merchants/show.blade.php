<x-app-layout :title="$merchant->name" :subtitle="$merchant->user->email">
    <x-slot name="breadcrumbs">
        <a href="{{ route('admin.merchants.index') }}" class="hover:text-slate-700">{{ __('Merchants') }}</a>
        <x-icon name="chevron-right" class="size-4 text-slate-300" />
        <span class="font-medium text-slate-700">{{ $merchant->name }}</span>
    </x-slot>

    @can('merchant-providers.assign')
        <x-slot name="actions">
            <a href="{{ route('admin.merchants.payment-methods.edit', $merchant) }}" class="btn-primary">{{ __('Assign providers') }}</a>
        </x-slot>
    @endcan

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Payments')" :value="number_format($merchant->payments_count)" icon="banknotes" />
        <x-stat :label="__('Active API keys')" :value="number_format($merchant->api_keys_count)" icon="key" />
        <x-stat :label="__('Webhook endpoint')" :value="$merchant->webhook_url ? __('Configured') : __('Not set')" :hint="$merchant->webhook_url" icon="bolt" />
        <x-stat :label="__('Member since')" :value="$merchant->created_at->toFormattedDateString()" icon="building-storefront" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-1">
            <div class="card-header"><h2 class="card-title">{{ __('Captured volume') }}</h2></div>
            <div class="card-body">
                @forelse ($volume as $row)
                    <div class="flex items-baseline justify-between py-1.5">
                        <span class="text-sm text-slate-500">{{ $row->currency }} · {{ trans_choice(':count payment|:count payments', $row->payments, ['count' => $row->payments]) }}</span>
                        <span class="font-semibold tabular-nums text-slate-900">{{ \App\Support\Money::format((int) $row->captured, $row->currency) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No captured payments yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">{{ __('Payment methods') }}</h2></div>
            @if ($merchant->paymentMethodAssignments->isEmpty())
                <x-empty-state icon="credit-card" :title="__('None assigned')" :description="__('This merchant cannot accept payments until providers are assigned.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>{{ __('Method') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Pricing') }}</th></tr></thead>
                        <tbody>
                            @foreach ($merchant->paymentMethodAssignments as $assignment)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $assignment->payment_method->label() }}</td>
                                    <td>{{ $assignment->providerPaymentMethod->provider->name }}</td>
                                    <td class="text-slate-500">{{ $assignment->providerPaymentMethod->pricingLabel() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">{{ __('Recent payments') }}</h2></div>
        @if ($payments->isEmpty())
            <x-empty-state icon="banknotes" :title="__('No payments yet')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th class="!text-end">{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="font-mono text-xs">{{ $payment->id }}</td>
                                <td class="text-end tabular-nums">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                                <td><x-payment-status :status="$payment->status" /></td>
                                <td>{{ $payment->payment_method->label() }}</td>
                                <td>{{ $payment->provider?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-slate-500">{{ $payment->created_at->toDayDateTimeString() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
