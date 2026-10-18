<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('API keys') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-medium text-gray-900">{{ __('Create a key') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('The secret is shown once. Store it securely; it signs every API request.') }}</p>

                <form method="POST" action="{{ route('dashboard.api-keys.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="name" :value="__('Label (optional)')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ __('Create key') }}</x-primary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($keys->isEmpty())
                    <p class="p-6 text-sm text-gray-500">{{ __('No API keys yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Key ID') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Label') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Last used') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Status') }}</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($keys as $key)
                                    <tr>
                                        <td class="px-4 py-3 font-mono">{{ $key->key_id }}</td>
                                        <td class="px-4 py-3">{{ $key->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $key->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                        <td class="px-4 py-3">{{ $key->isRevoked() ? __('Revoked') : __('Active') }}</td>
                                        <td class="px-4 py-3 text-end">
                                            @unless ($key->isRevoked())
                                                <form method="POST" action="{{ route('dashboard.api-keys.destroy', $key) }}" x-data @submit="confirm('Revoke this key? Requests signed with it will stop working.') || $event.preventDefault()">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-danger-button>{{ __('Revoke') }}</x-danger-button>
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
        </div>
    </div>
</x-app-layout>
