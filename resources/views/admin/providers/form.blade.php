<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $provider->exists ? __('Edit :name', ['name' => $provider->name]) : __('Add provider') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ $provider->exists ? route('admin.providers.update', $provider) : route('admin.providers.store') }}" class="space-y-6">
                @csrf
                @if ($provider->exists)
                    @method('PUT')
                @endif

                <div class="bg-white shadow-sm sm:rounded-lg p-6 grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $provider->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="code" :value="__('Code')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono disabled:bg-gray-100" :value="old('code', $provider->code)" required :disabled="$provider->exists" />
                        <p class="mt-1 text-xs text-gray-500">{{ $provider->exists ? __('Fixed: providers post callbacks to a URL containing it.') : __('Lowercase slug, used in the callback URL and API responses. Cannot be changed later.') }}</p>
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="driver" :value="__('Driver')" />
                        @if ($provider->exists)
                            <x-text-input id="driver" type="text" class="mt-1 block w-full font-mono bg-gray-100" :value="$provider->driver" disabled />
                            <p class="mt-1 text-xs text-gray-500">{{ __('Fixed: existing payments use this driver.') }}</p>
                        @else
                            <select id="driver" name="driver" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver }}" @selected(old('driver', $provider->driver) === $driver)>{{ $driver }}</option>
                                @endforeach
                            </select>
                        @endif
                        <x-input-error :messages="$errors->get('driver')" class="mt-2" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_active', $provider->is_active))>
                        {{ __('Active') }}
                    </label>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <h3 class="p-6 pb-0 font-medium text-gray-900">{{ __('Payment methods and pricing') }}</h3>
                    <p class="px-6 mt-1 text-sm text-gray-500">{{ __('Fee = percentage (basis points, 250 = 2.50%) + fixed amount in minor units (centavos for PHP).') }}</p>
                    <x-input-error :messages="$errors->get('methods')" class="px-6 mt-2" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Offered') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Method') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Fee (bps)') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Fixed fee (minor units)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($methods as $method)
                                    @php($row = $pricing[$method->value] ?? null)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="methods[{{ $method->value }}][enabled]" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old("methods.{$method->value}.enabled", $row?->is_active))>
                                        </td>
                                        <td class="px-4 py-3">{{ $method->label() }} <span class="font-mono text-xs text-gray-500">{{ $method->value }}</span></td>
                                        <td class="px-4 py-3">
                                            <x-text-input type="number" min="0" max="10000" name="methods[{{ $method->value }}][fee_bps]" class="w-28" :value="old('methods.'.$method->value.'.fee_bps', $row?->fee_bps ?? 0)" />
                                            <x-input-error :messages="$errors->get('methods.'.$method->value.'.fee_bps')" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-text-input type="number" min="0" name="methods[{{ $method->value }}][fee_fixed]" class="w-32" :value="old('methods.'.$method->value.'.fee_fixed', $row?->fee_fixed ?? 0)" />
                                            <x-input-error :messages="$errors->get('methods.'.$method->value.'.fee_fixed')" class="mt-1" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('admin.providers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
