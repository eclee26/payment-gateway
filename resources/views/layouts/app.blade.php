<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @stack('head')
    </head>
    <body class="h-full font-sans text-slate-900 antialiased">
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-full">
            {{-- Mobile drawer --}}
            <div x-show="sidebarOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
                <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60" @click="sidebarOpen = false"></div>
                <div class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw]"
                     x-show="sidebarOpen"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
                    <button type="button" class="absolute right-3 top-4 rounded-md p-1 text-slate-400 hover:text-white" @click="sidebarOpen = false">
                        <span class="sr-only">{{ __('Close sidebar') }}</span>
                        <x-icon name="x-mark" class="size-6" />
                    </button>
                    @include('layouts.sidebar')
                </div>
            </div>

            {{-- Desktop sidebar --}}
            <div class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-64 lg:flex-col">
                @include('layouts.sidebar')
            </div>

            <div class="lg:pl-64">
                {{-- Top bar --}}
                <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button type="button" class="-ml-1 rounded-md p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = true">
                        <span class="sr-only">{{ __('Open sidebar') }}</span>
                        <x-icon name="bars-3" class="size-6" />
                    </button>

                    <div class="min-w-0 flex-1">
                        @isset($breadcrumbs)
                            <nav class="flex items-center gap-1 text-sm text-slate-500" aria-label="Breadcrumb">{{ $breadcrumbs }}</nav>
                        @else
                            <span class="truncate text-sm font-medium text-slate-500">{{ $title }}</span>
                        @endisset
                    </div>

                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button type="button" class="flex items-center gap-2 rounded-lg p-1 pr-2 text-sm hover:bg-slate-100">
                                <span class="flex size-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                                    {{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden max-w-40 truncate font-medium text-slate-700 sm:block">{{ Auth::user()->name }}</span>
                                <x-icon name="chevron-down" class="size-4 text-slate-400" />
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-medium text-slate-900">{{ Auth::user()->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ Auth::user()->email }}</p>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('profile.security')">{{ __('Security') }}</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').requestSubmit();">
                                    {{ __('Log out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <main class="px-4 py-8 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-7xl space-y-6">
                        @if ($title || isset($actions))
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div class="min-w-0">
                                    <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
                                    @if ($subtitle)
                                        <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                                    @endif
                                </div>
                                @isset($actions)
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
                                @endisset
                            </div>
                        @endif

                        <x-flash />

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
