{{-- One PDF slide-over per CRM page (included from crm.partials.nav). Open it with: $dispatch('open-pdf', { url: '…', title: '…' }).
     The PDF shows inside; "Open in new tab" and "Download" sit in the header. --}}
<div x-data="{ open: false, url: '', title: '' }"
     @open-pdf.window="url = $event.detail.url; title = $event.detail.title || 'PDF'; open = true"
     @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-99999" role="dialog" aria-modal="true" :aria-label="title">
        <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 modal-overlay" @click="open = false"></div>
        <aside x-show="open"
               x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
               class="absolute inset-y-0 right-0 flex w-full max-w-3xl flex-col bg-white shadow-2xl dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h2 class="mr-auto min-w-0 truncate text-sm font-semibold text-gray-800 dark:text-white/90" x-text="title"></h2>
                <a :href="url" target="_blank" rel="noopener" data-no-spa class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                    <x-icon name="arrow-top-right-on-square" class="h-4 w-4" /> Open in new tab
                </a>
                <a :href="url + (url.includes('?') ? '&' : '?') + 'download=1'" data-no-spa class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600">
                    <x-icon name="arrow-down-tray" class="h-4 w-4" /> Download
                </a>
                <button type="button" @click="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5" aria-label="Close">
                    <x-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>
            <template x-if="open && url">
                <iframe :src="url" class="h-full w-full flex-1 bg-gray-100 dark:bg-gray-800" :title="title"></iframe>
            </template>
        </aside>
    </div>
</div>
