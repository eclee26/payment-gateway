<x-app-layout :title="__('API keys')" :subtitle="__('Keys authenticate and sign your API requests.')">
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('Create a key') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('The secret is shown once. Store it securely; it signs every API request.') }}</p>
            </div>
        </div>
        <div class="card-body">
            {{-- Full page load: the response shows the secret once and is not a redirect, which Turbo can't render. --}}
            <form method="POST" action="{{ route('dashboard.api-keys.store') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-turbo="false">
                @csrf
                <div class="flex-1">
                    <x-input-label for="name" :value="__('Label (optional)')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1" maxlength="100" placeholder="{{ __('e.g. Production server') }}" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <x-primary-button>
                    <x-icon name="plus" class="size-4" /> {{ __('Create key') }}
                </x-primary-button>
            </form>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">{{ __('Your keys') }}</h2></div>
        @if ($keys->isEmpty())
            <x-empty-state icon="key" :title="__('No API keys yet.')" :description="__('Create a key to start calling the API.')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Key ID') }}</th>
                            <th>{{ __('Label') }}</th>
                            <th>{{ __('Last used') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keys as $key)
                            <tr>
                                <td class="font-mono text-xs">{{ $key->key_id }}</td>
                                <td>{{ $key->name ?? '—' }}</td>
                                <td class="text-slate-500">{{ $key->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                <td>
                                    @if ($key->isRevoked())
                                        <span class="badge-gray">{{ __('Revoked') }}</span>
                                    @else
                                        <span class="badge-green">{{ __('Active') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @unless ($key->isRevoked())
                                        <form method="POST" action="{{ route('dashboard.api-keys.destroy', $key) }}" x-data @submit="confirm('Revoke this key? Requests signed with it will stop working.') || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700">{{ __('Revoke') }}</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
