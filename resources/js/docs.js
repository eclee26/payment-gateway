// Alpine components for the dashboard API docs page.

const LANGUAGE_KEY = 'docs-language';

export default function registerDocs(Alpine) {
    // Shared language for every code tab group; remembered per browser.
    Alpine.data('apiDocs', (defaultLanguage = 'curl') => ({
        lang: defaultLanguage,

        init() {
            try {
                this.lang = localStorage.getItem(LANGUAGE_KEY) || defaultLanguage;
            } catch {
                // Storage can be unavailable (private mode); keep the default.
            }

            this.$watch('lang', (value) => {
                try {
                    localStorage.setItem(LANGUAGE_KEY, value);
                } catch {
                    // Ignore: the choice just won't be remembered.
                }
            });
        },
    }));

    // Copy button for a code block; expects x-ref="code" on the block.
    Alpine.data('copyable', () => ({
        copied: false,

        async copy() {
            const text = this.$refs.code.textContent;

            try {
                await navigator.clipboard.writeText(text);
            } catch {
                // navigator.clipboard needs a secure context; fall back to a selection copy.
                const range = document.createRange();
                range.selectNodeContents(this.$refs.code);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                document.execCommand('copy');
                selection.removeAllRanges();
            }

            this.copied = true;
            setTimeout(() => (this.copied = false), 1500);
        },
    }));
}
