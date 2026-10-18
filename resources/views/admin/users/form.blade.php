@php($editingSelf = $user->exists && $user->is(Auth::user()))

<x-app-layout :title="$user->exists ? __('Edit :name', ['name' => $user->name]) : __('Add user')">
    <x-slot name="breadcrumbs">
        <a href="{{ route('admin.users.index') }}" class="hover:text-slate-700">{{ __('Users') }}</a>
        <x-icon name="chevron-right" class="size-4 text-slate-300" />
        <span class="font-medium text-slate-700">{{ $user->exists ? $user->name : __('New') }}</span>
    </x-slot>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card divide-y divide-slate-100">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Account') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Their name and sign-in email.') }}</p>
            </header>
            <div class="max-w-xl space-y-5 md:col-span-2">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $user->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user->email)" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Password') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $user->exists ? __('Leave blank to keep the current password.') : __('Share it with them securely. They set up two-factor authentication at first sign-in.') }}
                </p>
            </header>
            <div class="max-w-xl space-y-5 md:col-span-2">
                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" name="password" type="password" class="mt-1" autocomplete="new-password" :required="! $user->exists" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1" autocomplete="new-password" :required="! $user->exists" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Role') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Decides what they can see and do. Merchants get the merchant portal; every other role gets the admin area.') }}</p>
            </header>
            <div class="max-w-xl md:col-span-2">
                @if ($editingSelf)
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        {{ __('You are :role. You cannot change your own role.', ['role' => $currentRole]) }}
                    </p>
                @else
                    <fieldset class="space-y-2">
                        <legend class="sr-only">{{ __('Role') }}</legend>
                        @foreach ($roles as $role)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50">
                                <input type="radio" name="role" value="{{ $role->name }}" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('role', $currentRole) === $role->name) required>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-900">{{ $role->name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        @if ($role->name === \App\Support\Permissions::SUPER_ADMIN)
                                            {{ __('Every permission, including roles and other super-admins.') }}
                                        @elseif ($role->name === \App\Support\Permissions::MERCHANT)
                                            {{ __('Merchant portal: payments, API keys and webhooks.') }}
                                        @elseif ($role->permissions->isEmpty())
                                            {{ __('No permissions.') }}
                                        @else
                                            {{ $role->permissions->pluck('name')->sort()->join(', ') }}
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    @if ($user->exists && $currentRole && ! $roles->contains('name', $currentRole))
                        <p class="form-help">{{ __('Their current role (:role) is not one you can assign.', ['role' => $currentRole]) }}</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 bg-slate-50/60 px-6 py-4">
            <a href="{{ route('admin.users.index') }}" class="btn-ghost">{{ __('Cancel') }}</a>
            <x-primary-button>{{ $user->exists ? __('Save changes') : __('Create user') }}</x-primary-button>
        </div>
    </form>

    @if ($user->exists)
        <div class="card">
            <div class="grid gap-6 p-6 md:grid-cols-3">
                <header>
                    <h2 class="text-base font-semibold text-slate-900">{{ __('Two-factor authentication') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Reset it if they lost their device. They must set it up again at their next sign-in.') }}</p>
                </header>
                <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                    @if ($user->hasTwoFactorEnabled())
                        <span class="badge-green">{{ __('Enabled') }}</span>
                        <form method="POST" action="{{ route('admin.users.reset-two-factor', $user) }}" onsubmit="return confirm('{{ __('Reset two-factor authentication for :email?', ['email' => $user->email]) }}')">
                            @csrf
                            <button type="submit" class="btn-secondary">
                                <x-icon name="arrow-path" class="size-4" /> {{ __('Reset two-factor') }}
                            </button>
                        </form>
                    @else
                        <span class="badge-amber">{{ __('Not set up') }}</span>
                        <span class="text-sm text-slate-500">{{ __('They will be asked to set it up when they sign in.') }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
