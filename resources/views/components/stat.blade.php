@props(['label', 'value', 'hint' => null, 'icon' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-5'.($href ? ' transition hover:border-brand-300 hover:shadow-md' : '')]) }}>
    @if ($icon)
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
            <x-icon :name="$icon" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <p class="mt-1 truncate text-2xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 truncate text-xs text-slate-500">{{ $hint }}</p>
        @endif
        {{ $slot }}
    </div>
</{{ $tag }}>
