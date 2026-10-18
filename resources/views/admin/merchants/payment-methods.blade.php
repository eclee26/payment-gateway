<x-app-layout :title="__('Payment methods for :name', ['name' => $merchant->name])" :subtitle="__('Choose which provider processes each payment method for this merchant. Existing payments keep the provider they were created with.')">
    <x-slot name="breadcrumbs">
        <a href="{{ route('admin.merchants.index') }}" class="hover:text-slate-700">{{ __('Merchants') }}</a>
        <x-icon name="chevron-right" class="size-4 text-slate-300" />
        <span class="font-medium text-slate-700">{{ $merchant->name }}</span>
    </x-slot>

    <form method="POST" action="{{ route('admin.merchants.payment-methods.update', $merchant) }}" class="card overflow-hidden">
        @csrf
        @method('PUT')

        <x-input-error :messages="$errors->get('assignments')" class="px-5 pt-4" />

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Provider (pricing)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($methods as $method)
                        @php($selected = (string) old("assignments.{$method->value}", $current[$method->value] ?? ''))
                        <tr>
                            <td>
                                <span class="font-medium text-slate-900">{{ $method->label() }}</span>
                                <span class="ms-1 font-mono text-xs text-slate-400">{{ $method->value }}</span>
                            </td>
                            <td>
                                <select name="assignments[{{ $method->value }}]" class="form-input max-w-md">
                                    <option value="">{{ __('Not enabled') }}</option>
                                    @foreach ($options[$method->value] ?? [] as $option)
                                        <option value="{{ $option->id }}" @selected($selected === (string) $option->id)>{{ $option->provider->name }} ({{ $option->pricingLabel() }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('assignments.'.$method->value)" class="mt-1" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <a href="{{ route('admin.merchants.index') }}" class="btn-ghost">{{ __('Back') }}</a>
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
