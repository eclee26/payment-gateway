@props(['code'])

<div x-data="copyable" {{ $attributes->merge(['class' => 'relative']) }}>
    <button type="button" @click="copy"
            class="absolute end-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs font-medium text-gray-100 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :aria-label="copied ? 'Copied' : 'Copy to clipboard'">
        <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
    </button>
    <pre class="overflow-x-auto rounded-md bg-gray-900 p-4 pe-16 font-mono text-xs leading-relaxed text-gray-100"><code x-ref="code">{{ $code }}</code></pre>
</div>
