@php($checked = collect(old('permissions', $granted)))

<x-app-layout :title="$role->exists ? __('Edit role :name', ['name' => $role->name]) : __('Create role')">
    <x-slot name="breadcrumbs">
        <a href="{{ route('admin.roles.index') }}" class="hover:text-slate-700">{{ __('Roles & permissions') }}</a>
        <x-icon name="chevron-right" class="size-4 text-slate-300" />
        <span class="font-medium text-slate-700">{{ $role->exists ? $role->name : __('New') }}</span>
    </x-slot>

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="card divide-y divide-slate-100">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Name') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Shown wherever roles are listed, e.g. "Support" or "Finance".') }}</p>
            </header>
            <div class="max-w-xl md:col-span-2">
                <x-input-label for="name" :value="__('Role name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $role->name)" required autofocus maxlength="64" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Permissions') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Users with this role see a sidebar item for each area they can view.') }}</p>
                @unless (Auth::user()->isSuperAdmin())
                    <p class="mt-3 text-xs text-slate-500">{{ __('You can only grant permissions you hold yourself.') }}</p>
                @endunless
            </header>
            <div class="space-y-4 md:col-span-2">
                <x-input-error :messages="array_merge($errors->get('permissions'), $errors->get('permissions.*'))" />

                @foreach (\App\Support\Permissions::GROUPS as $group => $permissions)
                    <fieldset x-data class="rounded-xl border border-slate-200">
                        <legend class="sr-only">{{ $group }}</legend>
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-2.5">
                            <span class="text-sm font-semibold text-slate-900">{{ __($group) }}</span>
                            <button type="button" class="text-xs font-medium text-brand-600 hover:text-brand-500"
                                    @click="const boxes = [...$el.closest('fieldset').querySelectorAll('input[type=checkbox]:not(:disabled)')]; const on = boxes.some(b => ! b.checked); boxes.forEach(b => b.checked = on)">
                                {{ __('Toggle all') }}
                            </button>
                        </div>
                        <div class="grid gap-1 p-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission => $label)
                                @php($grantable = Auth::user()->can($permission))
                                <label @class(['flex items-start gap-3 rounded-lg px-2 py-2', 'cursor-pointer hover:bg-slate-50' => $grantable, 'opacity-50' => ! $grantable])>
                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" class="form-checkbox mt-0.5" @checked($checked->contains($permission)) @disabled(! $grantable)>
                                    <span>
                                        <span class="block text-sm text-slate-800">{{ __($label) }}</span>
                                        <span class="block font-mono text-xs text-slate-400">{{ $permission }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 bg-slate-50/60 px-6 py-4">
            <a href="{{ route('admin.roles.index') }}" class="btn-ghost">{{ __('Cancel') }}</a>
            <x-primary-button>{{ $role->exists ? __('Save changes') : __('Create role') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
