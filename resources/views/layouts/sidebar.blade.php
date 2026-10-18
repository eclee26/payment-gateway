<div class="flex grow flex-col overflow-y-auto bg-slate-900 px-4 pb-4">
    <a href="{{ url('/') }}" class="flex h-16 shrink-0 items-center gap-3 px-2">
        <x-application-logo class="size-8 text-brand-500" />
        <span class="text-base font-semibold text-white">{{ config('app.name', 'Payment Gateway') }}</span>
    </a>

    <nav class="mt-4 flex flex-1 flex-col gap-y-6" aria-label="Sidebar">
        @foreach ($navigation as $section)
            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __($section['label']) }}</p>
                <ul role="list" class="mt-2 space-y-1">
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs($item['active']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" @class(['sidebar-link group', 'sidebar-link-active' => $active]) @if ($active) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" @class(['text-white' => $active, 'text-slate-400 group-hover:text-white' => ! $active]) />
                                {{ __($item['label']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div class="mt-auto">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link group w-full">
                    <x-icon name="logout" class="text-slate-400 group-hover:text-white" />
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </nav>
</div>
