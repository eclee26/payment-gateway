<x-app-layout :title="__('Users')" :subtitle="__('Everyone who can sign in: staff and merchants.')">
    @can('users.create')
        <x-slot name="actions">
            <a href="{{ route('admin.users.create') }}" class="btn-primary">
                <x-icon name="plus" class="size-4" /> {{ __('Add user') }}
            </a>
        </x-slot>
    @endcan

    <div class="card overflow-hidden">
        <form method="GET" action="{{ route('admin.users.index') }}" class="card-header">
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search name or email') }}" class="form-input pl-9">
                </div>
                <select name="role" class="form-input sm:w-48" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? null) === $role)>{{ $role }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
            </div>
        </form>

        @if ($users->isEmpty())
            <x-empty-state icon="users" :title="__('No users found')" :description="__('Try a different search or role.')" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Two-factor') }}</th>
                            <th>{{ __('Joined') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                            {{ Str::upper(Str::substr($user->name, 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-900">
                                                {{ $user->name }}
                                                @if ($user->is(Auth::user()))
                                                    <span class="text-xs font-normal text-slate-400">({{ __('you') }})</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span @class([
                                            'badge-brand' => $role->name === \App\Support\Permissions::SUPER_ADMIN,
                                            'badge-blue' => $role->name === \App\Support\Permissions::MERCHANT,
                                            'badge-gray' => ! in_array($role->name, \App\Support\Permissions::SYSTEM_ROLES, true),
                                        ])>{{ $role->name }}</span>
                                    @empty
                                        <span class="text-slate-400">—</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($user->hasTwoFactorEnabled())
                                        <span class="badge-green">{{ __('Enabled') }}</span>
                                    @else
                                        <span class="badge-amber">{{ __('Not set up') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-slate-500">{{ $user->created_at?->toFormattedDateString() }}</td>
                                <td class="text-end">
                                    @if (\App\Support\Permissions::canManageUser(Auth::user(), $user))
                                        <div class="flex justify-end gap-1">
                                            @can('users.update')
                                                <a href="{{ route('admin.users.edit', $user) }}" class="btn-ghost btn-sm" title="{{ __('Edit') }}">
                                                    <x-icon name="pencil-square" class="size-4" /><span class="sr-only">{{ __('Edit') }}</span>
                                                </a>
                                            @endcan
                                            @can('users.delete')
                                                @unless ($user->is(Auth::user()))
                                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ __('Delete :email? This cannot be undone.', ['email' => $user->email]) }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700" title="{{ __('Delete') }}">
                                                            <x-icon name="trash" class="size-4" /><span class="sr-only">{{ __('Delete') }}</span>
                                                        </button>
                                                    </form>
                                                @endunless
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">{{ $users->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
