@props(['samples', 'labels' => \App\Support\ApiDocSamples::LANGUAGES])

{{-- Language tabs bound to the page-wide `lang` from the apiDocs component. --}}
<div {{ $attributes->merge(['class' => 'mt-3 overflow-hidden rounded-md bg-gray-900']) }}>
    <div class="flex flex-wrap gap-1 border-b border-gray-700 px-2 pt-2" role="tablist">
        @foreach ($samples as $lang => $code)
            <button type="button" role="tab" @click="lang = '{{ $lang }}'"
                    :aria-selected="lang === '{{ $lang }}'"
                    :class="lang === '{{ $lang }}' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-gray-200'"
                    class="rounded-t px-3 py-1.5 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-400">
                {{ $labels[$lang] ?? $lang }}
            </button>
        @endforeach
    </div>

    @foreach ($samples as $lang => $code)
        <div x-show="lang === '{{ $lang }}'" x-cloak role="tabpanel">
            <x-docs.code :code="$code" class="[&>pre]:rounded-none" />
        </div>
    @endforeach
</div>
