<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
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
    </head>
    <body class="h-full font-sans text-slate-900 antialiased">
        <div class="flex min-h-full">
            {{-- Brand panel --}}
            <div class="relative hidden w-0 flex-1 overflow-hidden bg-slate-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,theme(colors.brand.600/.45),transparent_60%),radial-gradient(ellipse_at_bottom_right,theme(colors.sky.500/.25),transparent_55%)]"></div>

                <a href="{{ url('/') }}" class="relative flex items-center gap-3">
                    <x-application-logo class="size-9 text-brand-500" />
                    <span class="text-lg font-semibold text-white">{{ config('app.name', 'Payment Gateway') }}</span>
                </a>

                <div class="relative max-w-md">
                    <h2 class="text-3xl font-semibold tracking-tight text-white">{{ __('One API for every way your customers pay.') }}</h2>
                    <p class="mt-4 text-slate-300">{{ __('Cards, QR Ph, e-wallets and bank transfers, routed to the right provider with transparent pricing.') }}</p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-300">
                        <li class="flex items-center gap-3"><x-icon name="shield-check" class="text-brand-400" /> {{ __('Two-factor authentication on every account') }}</li>
                        <li class="flex items-center gap-3"><x-icon name="key" class="text-brand-400" /> {{ __('HMAC-signed API requests') }}</li>
                        <li class="flex items-center gap-3"><x-icon name="bolt" class="text-brand-400" /> {{ __('Real-time webhooks') }}</li>
                    </ul>
                </div>

                <p class="relative text-xs text-slate-500">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            </div>

            {{-- Form panel --}}
            <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
                <div class="mx-auto w-full max-w-sm lg:w-96">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 lg:hidden">
                        <x-application-logo class="size-9 text-brand-600" />
                        <span class="text-lg font-semibold">{{ config('app.name', 'Payment Gateway') }}</span>
                    </a>

                    @if ($title)
                        <div class="mt-8 lg:mt-0">
                            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
                            @if ($subtitle)
                                <p class="mt-2 text-sm text-slate-500">{{ $subtitle }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
