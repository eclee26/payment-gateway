<x-app-layout :title="__('Providers')" :subtitle="__('Upstream banks and PSPs, the payment methods each offers, and their pricing.')">
    @can('providers.manage')
        <x-slot name="actions">
            <a href="{{ route('admin.providers.create') }}" class="btn-primary">
                <x-icon name="plus" class="size-4" /> {{ __('Add provider') }}
            </a>
        </x-slot>
    @endcan

    <div class="card overflow-hidden">
        @if ($providers->isEmpty())
            <x-empty-state icon="server-stack" :title="__('No providers yet.')" :description="__('Add a provider to start routing payments.')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Payment methods (pricing)') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($providers as $provider)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900">{{ $provider->name }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ $provider->code }}</div>
                                </td>
                                <td><span class="badge-gray font-mono">{{ $provider->driver }}</span></td>
                                <td>
                                    <div class="flex max-w-xl flex-wrap gap-1.5">
                                        @forelse ($provider->paymentMethods->where('is_active', true) as $row)
                                            <span class="badge-gray">{{ $row->payment_method->label() }} <span class="text-slate-400">({{ $row->pricingLabel() }})</span></span>
                                        @empty
                                            <span class="text-slate-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    @if ($provider->is_active)
                                        <span class="badge-green">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge-gray">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('providers.manage')
                                        <a href="{{ route('admin.providers.edit', $provider) }}" class="btn-ghost btn-sm">
                                            <x-icon name="pencil-square" class="size-4" /> {{ __('Edit') }}
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
