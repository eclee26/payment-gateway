<x-app-layout :title="__('Roles & permissions')" :subtitle="__('Roles bundle permissions. Every user has exactly one role, and the sidebar shows only what their role allows.')">
    @can('roles.create')
        <x-slot name="actions">
            <a href="{{ route('admin.roles.create') }}" class="btn-primary">
                <x-icon name="plus" class="size-4" /> {{ __('Create role') }}
            </a>
        </x-slot>
    @endcan

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            @php($system = in_array($role->name, \App\Support\Permissions::SYSTEM_ROLES, true))
            <div class="card flex flex-col">
                <div class="card-header">
                    <div class="flex min-w-0 items-center gap-2">
                        <x-icon name="shield-check" @class(['text-brand-600' => $system, 'text-slate-400' => ! $system]) />
                        <h2 class="card-title truncate">{{ $role->name }}</h2>
                        @if ($system)
                            <span class="badge-gray">{{ __('System') }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500">{{ trans_choice(':count user|:count users', $role->users_count, ['count' => $role->users_count]) }}</span>
                </div>
                <div class="card-body flex-1">
                    @if ($role->name === \App\Support\Permissions::SUPER_ADMIN)
                        <p class="text-sm text-slate-600">{{ __('Holds every permission, including ones added later. Cannot be edited or deleted.') }}</p>
                    @elseif ($role->name === \App\Support\Permissions::MERCHANT)
                        <p class="text-sm text-slate-600">{{ __('Merchant portal access: payments, payment methods, API keys, webhooks and docs. Cannot be edited or deleted.') }}</p>
                    @elseif ($role->permissions->isEmpty())
                        <p class="text-sm text-slate-500">{{ __('No permissions granted.') }}</p>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($role->permissions->sortBy('name') as $permission)
                                <span class="badge-gray font-mono">{{ $permission->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                @unless ($system)
                    <div class="flex items-center justify-end gap-1 border-t border-slate-100 px-5 py-3">
                        @can('roles.update')
                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn-ghost btn-sm">
                                <x-icon name="pencil-square" class="size-4" /> {{ __('Edit') }}
                            </a>
                        @endcan
                        @can('roles.delete')
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('{{ __('Delete the :role role?', ['role' => $role->name]) }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700">
                                    <x-icon name="trash" class="size-4" /> {{ __('Delete') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                @endunless
            </div>
        @endforeach
    </div>
</x-app-layout>
