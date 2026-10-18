<x-app-layout :title="$provider->exists ? __('Edit :name', ['name' => $provider->name]) : __('Add provider')">
    <x-slot name="breadcrumbs">
        <a href="{{ route('admin.providers.index') }}" class="hover:text-slate-700">{{ __('Providers') }}</a>
        <x-icon name="chevron-right" class="size-4 text-slate-300" />
        <span class="font-medium text-slate-700">{{ $provider->exists ? $provider->name : __('New') }}</span>
    </x-slot>

    <form method="POST" action="{{ $provider->exists ? route('admin.providers.update', $provider) : route('admin.providers.store') }}" class="space-y-6">
        @csrf
        @if ($provider->exists)
            @method('PUT')
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Provider details') }}</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-3">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $provider->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" type="text" class="mt-1 font-mono" :value="old('code', $provider->code)" required :disabled="$provider->exists" />
                    <p class="form-help">{{ $provider->exists ? __('Fixed: providers post callbacks to a URL containing it.') : __('Lowercase slug, used in the callback URL and API responses. Cannot be changed later.') }}</p>
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="driver" :value="__('Driver')" />
                    @if ($provider->exists)
                        <x-text-input id="driver" type="text" class="mt-1 font-mono" :value="$provider->driver" disabled />
                        <p class="form-help">{{ __('Fixed: existing payments use this driver.') }}</p>
                    @else
                        <select id="driver" name="driver" class="form-input mt-1">
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver }}" @selected(old('driver', $provider->driver) === $driver)>{{ $driver }}</option>
                            @endforeach
                        </select>
                    @endif
                    <x-input-error :messages="$errors->get('driver')" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-checkbox" @checked(old('is_active', $provider->is_active))>
                    {{ __('Active') }}
                    <span class="text-slate-400">— {{ __('inactive providers cannot be assigned or charged') }}</span>
                </label>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ __('Payment methods and pricing') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Fee = percentage (basis points, 250 = 2.50%) + fixed amount in minor units (centavos for PHP).') }}</p>
                </div>
            </div>
            <x-input-error :messages="$errors->get('methods')" class="px-5 pt-3" />
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Offered') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Fee (bps)') }}</th>
                            <th>{{ __('Fixed fee (minor units)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($methods as $method)
                            @php($row = $pricing[$method->value] ?? null)
                            <tr>
                                <td>
                                    <input type="checkbox" name="methods[{{ $method->value }}][enabled]" value="1" class="form-checkbox" @checked(old("methods.{$method->value}.enabled", $row?->is_active))>
                                </td>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $method->label() }}</span>
                                    <span class="ms-1 font-mono text-xs text-slate-400">{{ $method->value }}</span>
                                </td>
                                <td>
                                    <x-text-input type="number" min="0" max="10000" name="methods[{{ $method->value }}][fee_bps]" class="w-28" :value="old('methods.'.$method->value.'.fee_bps', $row?->fee_bps ?? 0)" />
                                    <x-input-error :messages="$errors->get('methods.'.$method->value.'.fee_bps')" class="mt-1" />
                                </td>
                                <td>
                                    <x-text-input type="number" min="0" name="methods[{{ $method->value }}][fee_fixed]" class="w-32" :value="old('methods.'.$method->value.'.fee_fixed', $row?->fee_fixed ?? 0)" />
                                    <x-input-error :messages="$errors->get('methods.'.$method->value.'.fee_fixed')" class="mt-1" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.providers.index') }}" class="btn-ghost">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
