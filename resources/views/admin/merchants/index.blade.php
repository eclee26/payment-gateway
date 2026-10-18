<x-app-layout :title="__('Merchants')" :subtitle="__('Merchant accounts and the provider that processes each of their payment methods.')">
    <div class="card overflow-hidden">
        <form method="GET" action="{{ route('admin.merchants.index') }}" class="card-header">
            <div class="flex w-full gap-3">
                <div class="relative flex-1">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('Search merchant or owner email') }}" class="form-input pl-9">
                </div>
                <button type="submit" class="btn-secondary">{{ __('Search') }}</button>
            </div>
        </form>

        @if ($merchants->isEmpty())
            <x-empty-state icon="building-storefront" :title="__('No merchants yet.')" :description="__('Merchants appear here after they register.')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Merchant') }}</th>
                            <th>{{ __('Payment methods') }}</th>
                            <th class="!text-end">{{ __('Payments') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($merchants as $merchant)
                            <tr>
                                <td>
                                    @can('merchants.view')
                                        <a href="{{ route('admin.merchants.show', $merchant) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $merchant->name }}</a>
                                    @else
                                        <span class="font-medium text-slate-900">{{ $merchant->name }}</span>
                                    @endcan
                                    <div class="text-xs text-slate-500">{{ $merchant->user->email }}</div>
                                </td>
                                <td>
                                    <div class="flex max-w-xl flex-wrap gap-1.5">
                                        @forelse ($merchant->paymentMethodAssignments as $assignment)
                                            <span class="badge-gray">{{ $assignment->payment_method->label() }} <span class="text-slate-400">→</span> {{ $assignment->providerPaymentMethod->provider->name }}</span>
                                        @empty
                                            <span class="badge-amber">{{ __('None assigned') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="text-end tabular-nums">{{ number_format($merchant->payments_count) }}</td>
                                <td class="text-end">
                                    <div class="flex justify-end gap-1">
                                        @can('merchants.view')
                                            <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn-ghost btn-sm">{{ __('View') }}</a>
                                        @endcan
                                        @can('merchant-providers.assign')
                                            <a href="{{ route('admin.merchants.payment-methods.edit', $merchant) }}" class="btn-secondary btn-sm">{{ __('Assign') }}</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($merchants->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">{{ $merchants->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
