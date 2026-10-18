<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payment methods for :name', ['name' => $merchant->name]) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.merchants.payment-methods.update', $merchant) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <p class="p-6 pb-0 text-sm text-gray-500">{{ __('Choose which provider processes each payment method for this merchant. Existing payments keep the provider they were created with.') }}</p>
                    <x-input-error :messages="$errors->get('assignments')" class="px-6 mt-2" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Method') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Provider (pricing)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($methods as $method)
                                    @php($selected = (string) old("assignments.{$method->value}", $current[$method->value] ?? ''))
                                    <tr>
                                        <td class="px-4 py-3">{{ $method->label() }} <span class="font-mono text-xs text-gray-500">{{ $method->value }}</span></td>
                                        <td class="px-4 py-3">
                                            <select name="assignments[{{ $method->value }}]" class="block w-full max-w-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
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
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('admin.merchants.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
