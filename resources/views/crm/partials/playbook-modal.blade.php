{{-- One Playbook slide-over per page (opens from the right). Open it with: $dispatch('open-playbook', { url: '{{ route('crm.companies.playbook', $company) }}' }) --}}
<div x-data="{
        open: false, html: '', loading: false,
        async load(url) {
            this.open = true; this.loading = true; this.html = '';
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                this.html = res.ok ? await res.text() : '<p class=\'p-6 text-sm text-red-600\'>Could not load the playbook (' + res.status + ').</p>';
            } catch (e) { this.html = '<p class=\'p-6 text-sm text-red-600\'>Could not load the playbook.</p>'; }
            this.loading = false;
        },
    }"
    @open-playbook.window="load($event.detail.url)"
    @if (session('open_playbook')) x-init="load(@js(session('open_playbook')))" @endif
    @close-playbook.window="open = false"
    @keydown.escape.window="open = false">
    {{-- Right-side slide-over --}}
    <div x-show="open" x-cloak x-transition:leave="duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-100" class="fixed inset-0 z-99999" role="dialog" aria-modal="true" aria-label="Sales playbook">
        <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="open = false"></div>
        <aside x-show="open"
               x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
               class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-gray-900">
            <div x-show="loading" class="p-10 text-center text-sm text-gray-500">Loading playbook…</div>
            <div x-show="!loading" x-html="html" class="flex min-h-0 flex-1 flex-col"></div>
        </aside>
    </div>
</div>
