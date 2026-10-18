<x-app-layout :title="__('Payment methods')" :subtitle="__('Send one of these as payment_method when creating a payment. Contact support to enable a method or change its provider.')">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($methods as $value => $entry)
            <div @class(['card p-5', 'opacity-60' => ! $entry['pricing']])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $entry['method']->label() }}</p>
                        <p class="font-mono text-xs text-slate-400">{{ $value }}</p>
                    </div>
                    @if ($entry['pricing'])
                        <span class="badge-green">{{ __('Enabled') }}</span>
                    @else
                        <span class="badge-gray">{{ __('Not enabled') }}</span>
                    @endif
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">{{ __('Provider') }}</dt>
                        <dd class="font-medium text-slate-800">{{ $entry['pricing']?->provider->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">{{ __('Fee') }}</dt>
                        <dd class="font-medium text-slate-800">{{ $entry['pricing']?->pricingLabel() ?? '—' }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-slate-500">{{ __('Currencies') }}</dt>
                        <dd class="text-slate-700">{{ implode(', ', $entry['method']->currencies()) }}</dd>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>
</x-app-layout>
