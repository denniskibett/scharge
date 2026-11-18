{{-- One Playbook modal per page. Open it with: $dispatch('open-playbook', { url: '{{ route('crm.companies.playbook', $company) }}' }) --}}
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
    @close-playbook.window="open = false"
    @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-400/50 p-4 backdrop-blur-[32px]" @click.self="open = false" role="dialog" aria-modal="true" aria-label="Sales playbook">
        <div class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-900">
            <div x-show="loading" class="p-10 text-center text-sm text-gray-500">Loading playbook…</div>
            <div x-show="!loading" x-html="html"></div>
        </div>
    </div>
</div>
