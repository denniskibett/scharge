{{-- Repair request details: a right-side slide-over (open from the Repairs table's View). --}}
<div x-data="maintenanceViewModal()" x-init="init()" x-cloak>
    <div x-show="showModal" class="fixed inset-0 z-999999" role="dialog" aria-modal="true" aria-labelledby="mt-view-title" @keydown.escape.window="closeModal()" style="display: none;">
        <div x-show="showModal" x-transition.opacity.duration.200ms class="absolute inset-0 bg-gray-400/50 backdrop-blur-sm" @click="closeModal()"></div>
        <div x-show="showModal"
             x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-gray-900">
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800">
                <div class="min-w-0">
                    <h3 id="mt-view-title" class="text-lg font-semibold text-gray-800 dark:text-white/90">Repair request</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-text="request ? request.request_number : ''"></p>
                </div>
                <button type="button" @click="closeModal()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/5" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5">
                <div x-show="loading" class="py-12 text-center">
                    <div class="mx-auto h-8 w-8 animate-spin rounded-full border-b-2 border-brand-500"></div>
                    <p class="mt-2 text-sm text-gray-500">Loading…</p>
                </div>

                <div x-show="!loading && request" class="space-y-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="request?.status_color" class="rounded-full px-2.5 py-1 text-xs font-medium" x-text="request?.status_label"></span>
                        <span :class="request?.priority_color" class="rounded-full px-2.5 py-1 text-xs font-medium" x-text="(request?.priority_label || '') + ' priority'"></span>
                    </div>

                    <div>
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90" x-text="request?.title || request?.name"></h4>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300" x-text="request?.description"></p>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Unit</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90" x-text="request?.unit_number || '–'"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Tenant</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90" x-text="request?.tenant_name || '–'"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Category</dt><dd class="mt-0.5 text-sm text-gray-800 dark:text-white/90" x-text="request?.category_label || request?.category || '–'"></dd></div>
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Reported</dt><dd class="mt-0.5 text-sm text-gray-800 dark:text-white/90" x-text="request ? formatDate(request.created_at) : ''"></dd></div>
                        <div x-show="request?.scheduled_date"><dt class="text-xs text-gray-500 dark:text-gray-400">Scheduled</dt><dd class="mt-0.5 text-sm text-gray-800 dark:text-white/90" x-text="request?.scheduled_date ? formatDate(request.scheduled_date) : ''"></dd></div>
                        <div x-show="request?.completed_date"><dt class="text-xs text-gray-500 dark:text-gray-400">Completed</dt><dd class="mt-0.5 text-sm text-gray-800 dark:text-white/90" x-text="request?.completed_date ? formatDate(request.completed_date) : ''"></dd></div>
                    </dl>

                    <div x-show="request?.admin_notes" class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Notes</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300" x-text="request?.admin_notes"></p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-800">
                <button type="button" @click="closeModal()" class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Close</button>
                @if(auth()->user()->hasAnyRole(['sysadmin', 'admin', 'property_manager', 'maintenance']))
                <button type="button" @click="editRequest()" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Edit request</button>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('maintenanceViewModal', () => ({
        showModal: false,
        loading: false,
        request: null,
        requestId: null,
        
        init() {
            window.maintenanceViewModal = this;
        },
        
        async openModal(id) {
            this.requestId = id;
            this.showModal = true;
            this.loading = true;
            
            try {
                const response = await fetch(`/maintenance/${id}/json`);
                const data = await response.json();
                
                if (data.success) {
                    this.request = {
                        ...data.request,
                        priority_label: this.getPriorityLabel(data.request.priority),
                        priority_color: this.getPriorityColor(data.request.priority),
                        status_label: this.getStatusLabel(data.request.status),
                        status_color: this.getStatusColor(data.request.status),
                        category_label: this.getCategoryLabel(data.request.category),
                        request_number: data.request.request_number || '#' + String(data.request.id).padStart(6, '0')
                    };
                } else {
                    alert('Failed to load request details');
                    this.closeModal();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while loading the request');
                this.closeModal();
            } finally {
                this.loading = false;
            }
        },
        
        closeModal() {
            this.showModal = false;
            this.request = null;
            this.requestId = null;
        },
        
        editRequest() {
            if (this.requestId) {
                (window.spaVisit || ((u) => { window.location.href = u; }))(`/maintenance/${this.requestId}/edit`);
            }
        },
        
        getPriorityLabel(priority) {
            const labels = { emergency: 'Emergency', high: 'High', medium: 'Medium', low: 'Low' };
            return labels[priority] || 'Medium';
        },
        
        getPriorityColor(priority) {
            const colors = {
                emergency: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                high: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                low: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
            };
            return colors[priority] || 'bg-gray-100 text-gray-800';
        },
        
        getStatusLabel(status) {
            const labels = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', pending_parts: 'Pending Parts', cancelled: 'Cancelled' };
            return labels[status] || status;
        },
        
        getStatusColor(status) {
            const colors = {
                resolved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                pending_parts: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                open: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },
        
        getCategoryLabel(category) {
            const labels = {
                plumbing: 'Plumbing', electrical: 'Electrical', hvac: 'HVAC / AC',
                appliance: 'Appliance', structural: 'Structural', pest_control: 'Pest Control',
                cleaning: 'Cleaning', other: 'Other'
            };
            return labels[category] || category;
        },
        
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }));
});
</script>