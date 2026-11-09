<!-- resources/views/partials/modal/security-crud-modal.blade.php -->
<div x-data="securityCrudModal" x-init="init()" x-cloak>
    <!-- Backdrop -->
    <div x-show="showFormModal" x-cloak @click="closeFormModal()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>

    <!-- Create/Edit panel (slides in from the right) -->
    <div x-show="showFormModal" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeFormModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

                    <form @submit.prevent="submitForm">
                        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="form.id ? 'Edit Access Log' : 'Create New Access Log'"></h4>
                        <div>
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Unit *</label>
                                    <select x-model="form.unit_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                        <option value="">Select Unit</option>
                                        @foreach($units ?? [] as $unit)
                                            {{-- <option value="{{ $unit['id'] ?? '' }}">{{ $unit['unit_number'] ?? 'N/A' }} - {{ $unit['property'] ?? 'No Property' }}</option> --}}
                                            <option value="{{ $unit['id'] }}">{{ $unit['unit_number'] }} - {{ $unit['property_name'] ?? '' }}</option>

                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Person Name *</label>
                                    <input type="text" x-model="form.person_name" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="Full name">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Access Type *</label>
                                    <select x-model="form.access_type" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                        <option value="entry">Entry</option>
                                        <option value="exit">Exit</option>
                                        <option value="delivery">Delivery</option>
                                        <option value="guest">Guest</option>
                                        <option value="contractor">Contractor</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status *</label>
                                    <select x-model="form.status" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                        <option value="granted">Granted</option>
                                        <option value="denied">Denied</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date & Time *</label>
                                    <input type="datetime-local" x-model="form.datetime" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Verified By</label>
                                    <input type="text" x-model="form.verified_by" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="Who verified this access?">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                                    <textarea x-model="form.notes" rows="4" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="Additional notes..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-end w-full gap-3 mt-6">
                                <button type="button" @click="closeFormModal()" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
                                <button type="submit" :disabled="submitting" class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                                    <span x-show="!submitting" x-text="form.id ? 'Update' : 'Create'"></span>
                                    <span x-show="submitting">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </form>
        </div>
    </div>

    <!-- View Modal -->
    <div x-show="showViewModal" x-transition class="fixed inset-0 z-[99999] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px]" @click="closeViewModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-lg w-full p-6">
                <h3 class="text-lg font-medium mb-4">Access Log Details</h3>
                <div class="space-y-3">
                    <div><p class="text-xs text-gray-500">Date & Time</p><p class="text-sm" x-text="viewData.datetime_formatted"></p></div>
                    <div><p class="text-xs text-gray-500">Unit</p><p class="text-sm" x-text="viewData.unit_number"></p></div>
                    <div><p class="text-xs text-gray-500">Person Name</p><p class="text-sm" x-text="viewData.person_name"></p></div>
                    <div><p class="text-xs text-gray-500">Access Type</p><p class="text-sm" x-text="viewData.access_type"></p></div>
                    <div><p class="text-xs text-gray-500">Status</p><p class="text-sm" x-text="viewData.status"></p></div>
                    <div><p class="text-xs text-gray-500">Verified By</p><p class="text-sm" x-text="viewData.verified_by || 'System'"></p></div>
                    <div><p class="text-xs text-gray-500">Notes</p><p class="text-sm" x-text="viewData.notes || '-'"></p></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button @click="closeViewModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('securityCrudModal', () => ({
        showFormModal: false,
        showViewModal: false,
        submitting: false,
        form: { id: null, unit_id: '', person_name: '', access_type: 'entry', status: 'pending', datetime: '', verified_by: '', notes: '' },
        viewData: {},
        
        init() { window.securityCrudModal = this; },
        
        openModal() { this.resetForm(); this.showFormModal = true; document.body.style.overflow = 'hidden'; },
        closeFormModal() { this.showFormModal = false; document.body.style.overflow = ''; },
        closeViewModal() { this.showViewModal = false; document.body.style.overflow = ''; },
        
        resetForm() {
            this.form = { id: null, unit_id: '', person_name: '', access_type: 'entry', status: 'pending', datetime: new Date().toISOString().slice(0, 16), verified_by: '', notes: '' };
        },
        
        editLog(logId) { 
            // TODO: Fetch and populate form
            alert('Edit log ' + logId); 
            this.openModal(); 
        },
        
        viewLog(logId) { 
            // TODO: Fetch and show view
            this.showViewModal = true; 
            document.body.style.overflow = 'hidden'; 
        },
        
        confirmDelete(logId) { 
            if (confirm('Delete this log?')) alert('Delete log ' + logId); 
        },
        
        approveLog(logId) { 
            if (confirm('Approve this access?')) alert('Approve log ' + logId); 
        },
        
        denyLog(logId) { 
            if (confirm('Deny this access?')) alert('Deny log ' + logId); 
        },
        
        async submitForm() {
            this.submitting = true;
            await new Promise(r => setTimeout(r, 500));
            alert(this.form.id ? 'Log updated' : 'Log created');
            this.submitting = false;
            this.closeFormModal();
            (window.schargeRefresh || (() => location.reload()))();
        }
    }));
});
</script>