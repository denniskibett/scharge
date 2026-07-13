<!-- resources/views/partials/modal/security-crud-modal.blade.php -->
<div x-data="securityCrudModal" x-init="init()" x-cloak>
    <!-- Create/Edit Slide-over Modal -->
    <div x-show="showFormModal" 
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-0 z-[99999] overflow-hidden">
        
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="closeFormModal()"></div>
        
        <div class="absolute inset-y-0 right-0 max-w-full flex">
            <div class="w-screen max-w-md">
                <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-xl overflow-y-auto">
                    <!-- Header -->
                    <div class="px-4 py-6 bg-gradient-to-r from-brand-500 to-brand-600 sm:px-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-white">
                                    <span x-text="form.id ? '✏️ Edit Access Log #' + form.id : '📝 Create New Access Log'"></span>
                                </h2>
                                <p class="text-sm text-white/80 mt-1" x-show="form.id">
                                    Editing log created on <span x-text="form.created_at_formatted || 'N/A'"></span>
                                </p>
                            </div>
                            <button @click="closeFormModal()" class="rounded-md text-white hover:text-gray-200">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submitForm" class="flex-1 flex flex-col">
                        <div class="flex-1 px-4 py-6 sm:px-6">
                            <div class="space-y-6">
                                <!-- Unit -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Unit *</label>
                                    <select x-model="form.unit_id" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                        <option value="">Select Unit</option>
                                        <template x-for="unit in units" :key="unit.id">
                                            <option :value="unit.id" x-text="unit.unit_number + ' - ' + (unit.estate_name || 'N/A')"></option>
                                        </template>
                                    </select>
                                </div>

                                <!-- Person Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Person Name *</label>
                                    <input type="text" x-model="form.person_name" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="Full name">
                                </div>

                                <!-- Access Type -->
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

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status *</label>
                                    <select x-model="form.status" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                        <option value="approved">✅ Approved</option>
                                        <option value="denied">❌ Denied</option>
                                        <option value="pending">⏳ Pending</option>
                                        <option value="completed">✅ Completed</option>
                                    </select>
                                </div>

                                <!-- Date & Time - Read-only when editing -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Date & Time
                                        <span x-show="form.id" class="text-xs text-gray-400 ml-2">(Read-only)</span>
                                    </label>
                                    <input type="datetime-local" 
                                           x-model="form.datetime" 
                                           :readonly="form.id ? true : false" 
                                           :class="form.id ? 'bg-gray-100 cursor-not-allowed dark:bg-gray-600' : ''"
                                           required 
                                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                                </div>

                                <!-- Verified By - Read-only when editing -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Verified By
                                        <span x-show="form.id" class="text-xs text-gray-400 ml-2">(Read-only)</span>
                                    </label>
                                    <input type="text" 
                                           x-model="form.verified_by" 
                                           :readonly="form.id ? true : false" 
                                           :class="form.id ? 'bg-gray-100 cursor-not-allowed dark:bg-gray-600' : ''"
                                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" 
                                           placeholder="Who verified this access?">
                                </div>

                                <!-- Purpose -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Purpose</label>
                                    <input type="text" 
                                           x-model="form.purpose" 
                                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" 
                                           placeholder="Reason for visit">
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                                    <textarea x-model="form.notes" rows="4" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700" placeholder="Additional notes..."></textarea>
                                </div>

                                <!-- Info when editing -->
                                <div x-show="form.id" class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium">Created:</span> <span x-text="form.created_at_formatted || 'N/A'"></span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium">Last updated:</span> <span x-text="form.updated_at_formatted || 'N/A'"></span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium">Original Unit:</span> <span x-text="form.original_unit || 'N/A'"></span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium">Original Person:</span> <span x-text="form.original_person || 'N/A'"></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex-shrink-0 px-4 py-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-end space-x-3">
                                <button type="button" @click="closeFormModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</button>
                                <button type="submit" :disabled="submitting" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50">
                                    <span x-show="!submitting" x-text="form.id ? '💾 Update Log' : '➕ Create Log'"></span>
                                    <span x-show="submitting">⏳ Saving...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div x-show="showViewModal" x-transition class="fixed inset-0 z-[99999] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeViewModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium">📋 Access Log Details</h3>
                    <button @click="closeViewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Date & Time</p>
                        <p class="text-sm font-medium" x-text="viewData.datetime_formatted || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Unit</p>
                        <p class="text-sm font-medium" x-text="viewData.unit_number || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Person Name</p>
                        <p class="text-sm font-medium" x-text="viewData.person_name || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Access Type</p>
                        <p class="text-sm font-medium" x-text="viewData.access_type_label || viewData.access_type || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                        <p class="text-sm font-medium" x-text="viewData.status_label || viewData.status || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Verified By</p>
                        <p class="text-sm font-medium" x-text="viewData.verified_by || 'System'"></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Purpose</p>
                        <p class="text-sm font-medium" x-text="viewData.purpose || '-'"></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Notes</p>
                        <p class="text-sm font-medium" x-text="viewData.notes || '-'"></p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button @click="closeViewModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">Close</button>
                    <button @click="closeViewModal(); editLog(viewData.id)" class="px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600">
                        ✏️ Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-transition class="fixed inset-0 z-[99999] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeDeleteModal()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6 shadow-xl">
                <div class="flex items-center mb-4">
                    <svg class="h-8 w-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-red-600">⚠️ Confirm Delete</h3>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Are you sure you want to delete this access log?
                    <br>
                    <span class="text-sm text-gray-500">Log ID: #<span x-text="deleteId"></span></span>
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="closeDeleteModal()" class="px-4 py-2 text-gray-700 bg-white border rounded-lg dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</button>
                    <button @click="confirmDeleteLog()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        🗑️ Delete
                    </button>
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
        showDeleteModal: false,
        submitting: false,
        deleteId: null,
        units: [],
        form: { 
            id: null, 
            unit_id: '', 
            person_name: '', 
            access_type: 'entry', 
            status: 'pending', 
            datetime: '', 
            verified_by: '{{ auth()->user()->name ?? "Security Staff" }}', 
            purpose: '',
            notes: '',
            created_at_formatted: '',
            updated_at_formatted: '',
            original_unit: '',
            original_person: ''
        },
        viewData: {},
        
        init() { 
            window.securityCrudModal = this; 
            this.loadUnits();
            console.log('✅ Security CRUD Modal initialized');
        },
        
        loadUnits() {
            // ================================================
            // HARDCODED UNITS - Same as Quick Entry Modal
            // ================================================
            
            // Danaff Towers Units (estate_id: 1)
            const danaffUnits = [
                { id: 329, unit_number: 'D00-SHOP', estate_name: 'Danaff Towers' },
                { id: 11, unit_number: 'D01-01', estate_name: 'Danaff Towers' },
                { id: 12, unit_number: 'D01-02', estate_name: 'Danaff Towers' },
                { id: 4, unit_number: 'D01-03', estate_name: 'Danaff Towers' },
                { id: 5, unit_number: 'D01-04', estate_name: 'Danaff Towers' },
                { id: 6, unit_number: 'D01-05', estate_name: 'Danaff Towers' },
                { id: 7, unit_number: 'D01-06', estate_name: 'Danaff Towers' },
                { id: 8, unit_number: 'D01-07', estate_name: 'Danaff Towers' },
                { id: 9, unit_number: 'D01-08', estate_name: 'Danaff Towers' },
                { id: 10, unit_number: 'D01-09', estate_name: 'Danaff Towers' },
                { id: 1, unit_number: 'D01-10', estate_name: 'Danaff Towers' },
                { id: 2, unit_number: 'D01-11', estate_name: 'Danaff Towers' },
                { id: 13, unit_number: 'D01-12', estate_name: 'Danaff Towers' },
                { id: 14, unit_number: 'D01-13', estate_name: 'Danaff Towers' },
                { id: 15, unit_number: 'D01-14', estate_name: 'Danaff Towers' },
                { id: 16, unit_number: 'D01-15', estate_name: 'Danaff Towers' },
                { id: 3, unit_number: 'D01-16', estate_name: 'Danaff Towers' },
                { id: 17, unit_number: 'D02-01', estate_name: 'Danaff Towers' },
                { id: 18, unit_number: 'D02-02', estate_name: 'Danaff Towers' },
                { id: 19, unit_number: 'D02-03', estate_name: 'Danaff Towers' },
                { id: 20, unit_number: 'D02-04', estate_name: 'Danaff Towers' },
                { id: 21, unit_number: 'D02-05', estate_name: 'Danaff Towers' },
                { id: 22, unit_number: 'D02-06', estate_name: 'Danaff Towers' },
                { id: 23, unit_number: 'D02-07', estate_name: 'Danaff Towers' },
                { id: 24, unit_number: 'D02-08', estate_name: 'Danaff Towers' },
                { id: 25, unit_number: 'D02-09', estate_name: 'Danaff Towers' },
                { id: 26, unit_number: 'D02-10', estate_name: 'Danaff Towers' },
                { id: 27, unit_number: 'D02-11', estate_name: 'Danaff Towers' },
                { id: 28, unit_number: 'D02-12', estate_name: 'Danaff Towers' },
                { id: 29, unit_number: 'D02-13', estate_name: 'Danaff Towers' },
                { id: 30, unit_number: 'D02-14', estate_name: 'Danaff Towers' },
                { id: 31, unit_number: 'D02-15', estate_name: 'Danaff Towers' },
                { id: 32, unit_number: 'D02-16', estate_name: 'Danaff Towers' },
                { id: 102, unit_number: 'D03-01', estate_name: 'Danaff Towers' },
                { id: 103, unit_number: 'D03-02', estate_name: 'Danaff Towers' },
                { id: 104, unit_number: 'D03-03', estate_name: 'Danaff Towers' },
                { id: 105, unit_number: 'D03-04', estate_name: 'Danaff Towers' },
                { id: 106, unit_number: 'D03-05', estate_name: 'Danaff Towers' },
                { id: 107, unit_number: 'D03-06', estate_name: 'Danaff Towers' },
                { id: 108, unit_number: 'D03-07', estate_name: 'Danaff Towers' },
                { id: 109, unit_number: 'D03-08', estate_name: 'Danaff Towers' },
                { id: 110, unit_number: 'D03-09', estate_name: 'Danaff Towers' },
                { id: 111, unit_number: 'D03-10', estate_name: 'Danaff Towers' },
                { id: 112, unit_number: 'D03-11', estate_name: 'Danaff Towers' },
                { id: 113, unit_number: 'D03-12', estate_name: 'Danaff Towers' },
                { id: 114, unit_number: 'D03-13', estate_name: 'Danaff Towers' },
                { id: 115, unit_number: 'D03-14', estate_name: 'Danaff Towers' },
                { id: 116, unit_number: 'D03-15', estate_name: 'Danaff Towers' },
                { id: 117, unit_number: 'D03-16', estate_name: 'Danaff Towers' },
                { id: 33, unit_number: 'D04-01', estate_name: 'Danaff Towers' },
                { id: 45, unit_number: 'D04-02', estate_name: 'Danaff Towers' },
                { id: 36, unit_number: 'D04-03', estate_name: 'Danaff Towers' },
                { id: 37, unit_number: 'D04-04', estate_name: 'Danaff Towers' },
                { id: 38, unit_number: 'D04-05', estate_name: 'Danaff Towers' },
                { id: 39, unit_number: 'D04-06', estate_name: 'Danaff Towers' },
                { id: 35, unit_number: 'D04-07', estate_name: 'Danaff Towers' },
                { id: 41, unit_number: 'D04-08', estate_name: 'Danaff Towers' },
                { id: 42, unit_number: 'D04-09', estate_name: 'Danaff Towers' },
                { id: 43, unit_number: 'D04-10', estate_name: 'Danaff Towers' },
                { id: 34, unit_number: 'D04-11', estate_name: 'Danaff Towers' },
                { id: 40, unit_number: 'D04-12', estate_name: 'Danaff Towers' },
                { id: 46, unit_number: 'D04-13', estate_name: 'Danaff Towers' },
                { id: 47, unit_number: 'D04-14', estate_name: 'Danaff Towers' },
                { id: 52, unit_number: 'D04-15', estate_name: 'Danaff Towers' },
                { id: 44, unit_number: 'D04-16', estate_name: 'Danaff Towers' },
                { id: 54, unit_number: 'D05-01', estate_name: 'Danaff Towers' },
                { id: 86, unit_number: 'D05-02', estate_name: 'Danaff Towers' },
                { id: 120, unit_number: 'D05-03', estate_name: 'Danaff Towers' },
                { id: 121, unit_number: 'D05-04', estate_name: 'Danaff Towers' },
                { id: 122, unit_number: 'D05-05', estate_name: 'Danaff Towers' },
                { id: 123, unit_number: 'D05-06', estate_name: 'Danaff Towers' },
                { id: 124, unit_number: 'D05-07', estate_name: 'Danaff Towers' },
                { id: 125, unit_number: 'D05-08', estate_name: 'Danaff Towers' },
                { id: 126, unit_number: 'D05-09', estate_name: 'Danaff Towers' },
                { id: 127, unit_number: 'D05-10', estate_name: 'Danaff Towers' },
                { id: 128, unit_number: 'D05-11', estate_name: 'Danaff Towers' },
                { id: 129, unit_number: 'D05-12', estate_name: 'Danaff Towers' },
                { id: 130, unit_number: 'D05-13', estate_name: 'Danaff Towers' },
                { id: 131, unit_number: 'D05-14', estate_name: 'Danaff Towers' },
                { id: 132, unit_number: 'D05-15', estate_name: 'Danaff Towers' },
                { id: 133, unit_number: 'D05-16', estate_name: 'Danaff Towers' },
                { id: 134, unit_number: 'D06-01', estate_name: 'Danaff Towers' },
                { id: 135, unit_number: 'D06-02', estate_name: 'Danaff Towers' },
                { id: 136, unit_number: 'D06-03', estate_name: 'Danaff Towers' },
                { id: 137, unit_number: 'D06-04', estate_name: 'Danaff Towers' },
                { id: 138, unit_number: 'D06-05', estate_name: 'Danaff Towers' },
                { id: 139, unit_number: 'D06-06', estate_name: 'Danaff Towers' },
                { id: 140, unit_number: 'D06-07', estate_name: 'Danaff Towers' },
                { id: 141, unit_number: 'D06-08', estate_name: 'Danaff Towers' },
                { id: 142, unit_number: 'D06-09', estate_name: 'Danaff Towers' },
                { id: 143, unit_number: 'D06-10', estate_name: 'Danaff Towers' },
                { id: 144, unit_number: 'D06-11', estate_name: 'Danaff Towers' },
                { id: 145, unit_number: 'D06-12', estate_name: 'Danaff Towers' },
                { id: 146, unit_number: 'D06-13', estate_name: 'Danaff Towers' },
                { id: 147, unit_number: 'D06-14', estate_name: 'Danaff Towers' },
                { id: 148, unit_number: 'D06-15', estate_name: 'Danaff Towers' },
                { id: 149, unit_number: 'D06-16', estate_name: 'Danaff Towers' },
                { id: 150, unit_number: 'D07-01', estate_name: 'Danaff Towers' },
                { id: 151, unit_number: 'D07-02', estate_name: 'Danaff Towers' },
                { id: 152, unit_number: 'D07-03', estate_name: 'Danaff Towers' },
                { id: 153, unit_number: 'D07-04', estate_name: 'Danaff Towers' },
                { id: 154, unit_number: 'D07-05', estate_name: 'Danaff Towers' },
                { id: 155, unit_number: 'D07-06', estate_name: 'Danaff Towers' },
                { id: 156, unit_number: 'D07-07', estate_name: 'Danaff Towers' },
                { id: 157, unit_number: 'D07-08', estate_name: 'Danaff Towers' },
                { id: 158, unit_number: 'D07-09', estate_name: 'Danaff Towers' },
                { id: 159, unit_number: 'D07-10', estate_name: 'Danaff Towers' },
                { id: 160, unit_number: 'D07-11', estate_name: 'Danaff Towers' },
                { id: 161, unit_number: 'D07-12', estate_name: 'Danaff Towers' },
                { id: 162, unit_number: 'D07-13', estate_name: 'Danaff Towers' },
                { id: 163, unit_number: 'D07-14', estate_name: 'Danaff Towers' },
                { id: 164, unit_number: 'D07-15', estate_name: 'Danaff Towers' },
                { id: 165, unit_number: 'D07-16', estate_name: 'Danaff Towers' },
                { id: 166, unit_number: 'D08-01', estate_name: 'Danaff Towers' },
                { id: 167, unit_number: 'D08-02', estate_name: 'Danaff Towers' },
                { id: 168, unit_number: 'D08-03', estate_name: 'Danaff Towers' },
                { id: 169, unit_number: 'D08-04', estate_name: 'Danaff Towers' },
                { id: 170, unit_number: 'D08-05', estate_name: 'Danaff Towers' },
                { id: 171, unit_number: 'D08-06', estate_name: 'Danaff Towers' },
                { id: 172, unit_number: 'D08-07', estate_name: 'Danaff Towers' },
                { id: 173, unit_number: 'D08-08', estate_name: 'Danaff Towers' },
                { id: 174, unit_number: 'D08-09', estate_name: 'Danaff Towers' },
                { id: 175, unit_number: 'D08-10', estate_name: 'Danaff Towers' },
                { id: 176, unit_number: 'D08-11', estate_name: 'Danaff Towers' },
                { id: 177, unit_number: 'D08-12', estate_name: 'Danaff Towers' },
                { id: 178, unit_number: 'D08-13', estate_name: 'Danaff Towers' },
                { id: 179, unit_number: 'D08-14', estate_name: 'Danaff Towers' },
                { id: 180, unit_number: 'D08-15', estate_name: 'Danaff Towers' },
                { id: 181, unit_number: 'D08-16', estate_name: 'Danaff Towers' },
                { id: 182, unit_number: 'D09-01', estate_name: 'Danaff Towers' },
                { id: 183, unit_number: 'D09-02', estate_name: 'Danaff Towers' },
                { id: 184, unit_number: 'D09-03', estate_name: 'Danaff Towers' },
                { id: 185, unit_number: 'D09-04', estate_name: 'Danaff Towers' },
                { id: 186, unit_number: 'D09-05', estate_name: 'Danaff Towers' },
                { id: 187, unit_number: 'D09-06', estate_name: 'Danaff Towers' },
                { id: 188, unit_number: 'D09-07', estate_name: 'Danaff Towers' },
                { id: 189, unit_number: 'D09-08', estate_name: 'Danaff Towers' },
                { id: 190, unit_number: 'D09-09', estate_name: 'Danaff Towers' },
                { id: 191, unit_number: 'D09-10', estate_name: 'Danaff Towers' },
                { id: 192, unit_number: 'D09-11', estate_name: 'Danaff Towers' },
                { id: 193, unit_number: 'D09-12', estate_name: 'Danaff Towers' },
                { id: 194, unit_number: 'D09-13', estate_name: 'Danaff Towers' },
                { id: 195, unit_number: 'D09-14', estate_name: 'Danaff Towers' },
                { id: 196, unit_number: 'D09-15', estate_name: 'Danaff Towers' },
                { id: 197, unit_number: 'D09-16', estate_name: 'Danaff Towers' },
                { id: 198, unit_number: 'D10-01', estate_name: 'Danaff Towers' },
                { id: 199, unit_number: 'D10-02', estate_name: 'Danaff Towers' },
                { id: 200, unit_number: 'D10-03', estate_name: 'Danaff Towers' },
                { id: 201, unit_number: 'D10-04', estate_name: 'Danaff Towers' },
                { id: 202, unit_number: 'D10-05', estate_name: 'Danaff Towers' },
                { id: 203, unit_number: 'D10-06', estate_name: 'Danaff Towers' },
                { id: 204, unit_number: 'D10-07', estate_name: 'Danaff Towers' },
                { id: 205, unit_number: 'D10-08', estate_name: 'Danaff Towers' },
                { id: 206, unit_number: 'D10-09', estate_name: 'Danaff Towers' },
                { id: 207, unit_number: 'D10-10', estate_name: 'Danaff Towers' },
                { id: 208, unit_number: 'D10-11', estate_name: 'Danaff Towers' },
                { id: 209, unit_number: 'D10-12', estate_name: 'Danaff Towers' },
                { id: 210, unit_number: 'D10-13', estate_name: 'Danaff Towers' },
                { id: 211, unit_number: 'D10-14', estate_name: 'Danaff Towers' },
                { id: 212, unit_number: 'D10-15', estate_name: 'Danaff Towers' },
                { id: 213, unit_number: 'D10-16', estate_name: 'Danaff Towers' },
                { id: 214, unit_number: 'D11-01', estate_name: 'Danaff Towers' },
                { id: 215, unit_number: 'D11-02', estate_name: 'Danaff Towers' },
                { id: 216, unit_number: 'D11-03', estate_name: 'Danaff Towers' },
                { id: 217, unit_number: 'D11-04', estate_name: 'Danaff Towers' },
                { id: 218, unit_number: 'D11-05', estate_name: 'Danaff Towers' },
                { id: 219, unit_number: 'D11-06', estate_name: 'Danaff Towers' },
                { id: 220, unit_number: 'D11-07', estate_name: 'Danaff Towers' },
                { id: 221, unit_number: 'D11-08', estate_name: 'Danaff Towers' },
                { id: 222, unit_number: 'D11-09', estate_name: 'Danaff Towers' },
                { id: 223, unit_number: 'D11-10', estate_name: 'Danaff Towers' },
                { id: 224, unit_number: 'D11-11', estate_name: 'Danaff Towers' },
                { id: 225, unit_number: 'D11-12', estate_name: 'Danaff Towers' },
                { id: 226, unit_number: 'D11-13', estate_name: 'Danaff Towers' },
                { id: 227, unit_number: 'D11-14', estate_name: 'Danaff Towers' },
                { id: 228, unit_number: 'D11-15', estate_name: 'Danaff Towers' },
                { id: 229, unit_number: 'D11-16', estate_name: 'Danaff Towers' },
                { id: 230, unit_number: 'D12-01', estate_name: 'Danaff Towers' },
                { id: 231, unit_number: 'D12-02', estate_name: 'Danaff Towers' },
                { id: 232, unit_number: 'D12-03', estate_name: 'Danaff Towers' },
                { id: 233, unit_number: 'D12-04', estate_name: 'Danaff Towers' },
                { id: 234, unit_number: 'D12-05', estate_name: 'Danaff Towers' },
                { id: 235, unit_number: 'D12-06', estate_name: 'Danaff Towers' },
                { id: 236, unit_number: 'D12-07', estate_name: 'Danaff Towers' },
                { id: 237, unit_number: 'D12-08', estate_name: 'Danaff Towers' },
                { id: 238, unit_number: 'D12-09', estate_name: 'Danaff Towers' },
                { id: 239, unit_number: 'D12-10', estate_name: 'Danaff Towers' },
                { id: 240, unit_number: 'D12-11', estate_name: 'Danaff Towers' },
                { id: 241, unit_number: 'D12-12', estate_name: 'Danaff Towers' },
                { id: 242, unit_number: 'D12-13', estate_name: 'Danaff Towers' },
                { id: 243, unit_number: 'D12-14', estate_name: 'Danaff Towers' },
                { id: 244, unit_number: 'D12-15', estate_name: 'Danaff Towers' },
                { id: 245, unit_number: 'D12-16', estate_name: 'Danaff Towers' },
                { id: 246, unit_number: 'D13-01', estate_name: 'Danaff Towers' },
                { id: 247, unit_number: 'D13-02', estate_name: 'Danaff Towers' },
                { id: 248, unit_number: 'D13-03', estate_name: 'Danaff Towers' },
                { id: 249, unit_number: 'D13-04', estate_name: 'Danaff Towers' },
                { id: 250, unit_number: 'D13-05', estate_name: 'Danaff Towers' },
                { id: 251, unit_number: 'D13-06', estate_name: 'Danaff Towers' },
                { id: 252, unit_number: 'D13-07', estate_name: 'Danaff Towers' },
                { id: 253, unit_number: 'D13-08', estate_name: 'Danaff Towers' },
                { id: 254, unit_number: 'D13-09', estate_name: 'Danaff Towers' },
                { id: 255, unit_number: 'D13-10', estate_name: 'Danaff Towers' },
                { id: 256, unit_number: 'D13-11', estate_name: 'Danaff Towers' },
                { id: 257, unit_number: 'D13-12', estate_name: 'Danaff Towers' },
                { id: 258, unit_number: 'D13-13', estate_name: 'Danaff Towers' },
                { id: 259, unit_number: 'D13-14', estate_name: 'Danaff Towers' },
                { id: 260, unit_number: 'D13-15', estate_name: 'Danaff Towers' },
                { id: 261, unit_number: 'D13-16', estate_name: 'Danaff Towers' },
                { id: 262, unit_number: 'D14-01', estate_name: 'Danaff Towers' },
                { id: 263, unit_number: 'D14-02', estate_name: 'Danaff Towers' },
                { id: 264, unit_number: 'D14-03', estate_name: 'Danaff Towers' },
                { id: 265, unit_number: 'D14-04', estate_name: 'Danaff Towers' },
                { id: 266, unit_number: 'D14-05', estate_name: 'Danaff Towers' },
                { id: 267, unit_number: 'D14-06', estate_name: 'Danaff Towers' },
                { id: 268, unit_number: 'D14-07', estate_name: 'Danaff Towers' },
                { id: 269, unit_number: 'D14-08', estate_name: 'Danaff Towers' },
                { id: 270, unit_number: 'D14-09', estate_name: 'Danaff Towers' },
                { id: 271, unit_number: 'D14-10', estate_name: 'Danaff Towers' },
                { id: 272, unit_number: 'D14-11', estate_name: 'Danaff Towers' },
                { id: 273, unit_number: 'D14-12', estate_name: 'Danaff Towers' },
                { id: 274, unit_number: 'D14-13', estate_name: 'Danaff Towers' },
                { id: 275, unit_number: 'D14-14', estate_name: 'Danaff Towers' },
                { id: 276, unit_number: 'D14-15', estate_name: 'Danaff Towers' },
                { id: 277, unit_number: 'D14-16', estate_name: 'Danaff Towers' },
                { id: 284, unit_number: 'D15-01', estate_name: 'Danaff Towers' },
                { id: 295, unit_number: 'D15-02', estate_name: 'Danaff Towers' },
                { id: 296, unit_number: 'D15-03', estate_name: 'Danaff Towers' },
                { id: 297, unit_number: 'D15-04', estate_name: 'Danaff Towers' },
                { id: 298, unit_number: 'D15-05', estate_name: 'Danaff Towers' },
                { id: 299, unit_number: 'D15-06', estate_name: 'Danaff Towers' },
                { id: 300, unit_number: 'D15-07', estate_name: 'Danaff Towers' },
                { id: 301, unit_number: 'D15-08', estate_name: 'Danaff Towers' },
                { id: 302, unit_number: 'D15-09', estate_name: 'Danaff Towers' },
                { id: 303, unit_number: 'D15-10', estate_name: 'Danaff Towers' },
                { id: 304, unit_number: 'D15-11', estate_name: 'Danaff Towers' },
                { id: 305, unit_number: 'D15-12', estate_name: 'Danaff Towers' },
                { id: 306, unit_number: 'D15-13', estate_name: 'Danaff Towers' },
                { id: 307, unit_number: 'D15-14', estate_name: 'Danaff Towers' },
                { id: 308, unit_number: 'D15-15', estate_name: 'Danaff Towers' },
                { id: 309, unit_number: 'D15-16', estate_name: 'Danaff Towers' },
                { id: 278, unit_number: 'D16-01', estate_name: 'Danaff Towers' },
                { id: 279, unit_number: 'D16-02', estate_name: 'Danaff Towers' },
                { id: 280, unit_number: 'D16-03', estate_name: 'Danaff Towers' },
                { id: 281, unit_number: 'D16-04', estate_name: 'Danaff Towers' },
                { id: 53, unit_number: 'D16-05', estate_name: 'Danaff Towers' },
                { id: 282, unit_number: 'D16-06', estate_name: 'Danaff Towers' },
                { id: 283, unit_number: 'D16-07', estate_name: 'Danaff Towers' },
                { id: 285, unit_number: 'D16-08', estate_name: 'Danaff Towers' },
                { id: 286, unit_number: 'D16-09', estate_name: 'Danaff Towers' },
                { id: 287, unit_number: 'D16-10', estate_name: 'Danaff Towers' },
                { id: 288, unit_number: 'D16-11', estate_name: 'Danaff Towers' },
                { id: 289, unit_number: 'D16-12', estate_name: 'Danaff Towers' },
                { id: 290, unit_number: 'D16-13', estate_name: 'Danaff Towers' },
                { id: 291, unit_number: 'D16-14', estate_name: 'Danaff Towers' },
                { id: 292, unit_number: 'D16-15', estate_name: 'Danaff Towers' },
                { id: 293, unit_number: 'D16-16', estate_name: 'Danaff Towers' },
                { id: 48, unit_number: 'D17-01', estate_name: 'Danaff Towers' },
                { id: 49, unit_number: 'D17-02', estate_name: 'Danaff Towers' },
                { id: 50, unit_number: 'D17-03', estate_name: 'Danaff Towers' },
                { id: 51, unit_number: 'D17-04', estate_name: 'Danaff Towers' },
                { id: 314, unit_number: 'D17-05', estate_name: 'Danaff Towers' },
                { id: 315, unit_number: 'D17-06', estate_name: 'Danaff Towers' },
                { id: 316, unit_number: 'D17-07', estate_name: 'Danaff Towers' },
                { id: 317, unit_number: 'D17-08', estate_name: 'Danaff Towers' },
                { id: 318, unit_number: 'D17-09', estate_name: 'Danaff Towers' },
                { id: 319, unit_number: 'D17-10', estate_name: 'Danaff Towers' },
                { id: 320, unit_number: 'D17-11', estate_name: 'Danaff Towers' },
                { id: 321, unit_number: 'D17-12', estate_name: 'Danaff Towers' },
                { id: 322, unit_number: 'D17-13', estate_name: 'Danaff Towers' },
                { id: 323, unit_number: 'D17-14', estate_name: 'Danaff Towers' },
                { id: 324, unit_number: 'D17-15', estate_name: 'Danaff Towers' },
                { id: 325, unit_number: 'D17-16', estate_name: 'Danaff Towers' },
                { id: 326, unit_number: 'D17-17', estate_name: 'Danaff Towers' },
                { id: 327, unit_number: 'D17-18', estate_name: 'Danaff Towers' },
                { id: 328, unit_number: 'D17-19', estate_name: 'Danaff Towers' }
            ];

            // Bloomfield Apartments Units (estate_id: 2)
            const bloomfieldUnits = [
                { id: 419, unit_number: 'B00-SHOP', estate_name: 'Bloomfield Apartments' },
                { id: 330, unit_number: 'B01-01', estate_name: 'Bloomfield Apartments' },
                { id: 331, unit_number: 'B01-02', estate_name: 'Bloomfield Apartments' },
                { id: 332, unit_number: 'B01-03', estate_name: 'Bloomfield Apartments' },
                { id: 333, unit_number: 'B01-04', estate_name: 'Bloomfield Apartments' },
                { id: 334, unit_number: 'B01-05', estate_name: 'Bloomfield Apartments' },
                { id: 335, unit_number: 'B01-06', estate_name: 'Bloomfield Apartments' },
                { id: 336, unit_number: 'B01-07', estate_name: 'Bloomfield Apartments' },
                { id: 337, unit_number: 'B01-08', estate_name: 'Bloomfield Apartments' },
                { id: 338, unit_number: 'B02-01', estate_name: 'Bloomfield Apartments' },
                { id: 339, unit_number: 'B02-02', estate_name: 'Bloomfield Apartments' },
                { id: 340, unit_number: 'B02-03', estate_name: 'Bloomfield Apartments' },
                { id: 341, unit_number: 'B02-04', estate_name: 'Bloomfield Apartments' },
                { id: 342, unit_number: 'B02-05', estate_name: 'Bloomfield Apartments' },
                { id: 343, unit_number: 'B02-06', estate_name: 'Bloomfield Apartments' },
                { id: 344, unit_number: 'B02-07', estate_name: 'Bloomfield Apartments' },
                { id: 345, unit_number: 'B02-08', estate_name: 'Bloomfield Apartments' },
                { id: 346, unit_number: 'B03-01', estate_name: 'Bloomfield Apartments' },
                { id: 347, unit_number: 'B03-02', estate_name: 'Bloomfield Apartments' },
                { id: 348, unit_number: 'B03-03', estate_name: 'Bloomfield Apartments' },
                { id: 349, unit_number: 'B03-04', estate_name: 'Bloomfield Apartments' },
                { id: 350, unit_number: 'B03-05', estate_name: 'Bloomfield Apartments' },
                { id: 351, unit_number: 'B03-06', estate_name: 'Bloomfield Apartments' },
                { id: 352, unit_number: 'B03-07', estate_name: 'Bloomfield Apartments' },
                { id: 353, unit_number: 'B03-08', estate_name: 'Bloomfield Apartments' },
                { id: 354, unit_number: 'B04-01', estate_name: 'Bloomfield Apartments' },
                { id: 355, unit_number: 'B04-02', estate_name: 'Bloomfield Apartments' },
                { id: 356, unit_number: 'B04-03', estate_name: 'Bloomfield Apartments' },
                { id: 357, unit_number: 'B04-04', estate_name: 'Bloomfield Apartments' },
                { id: 358, unit_number: 'B04-05', estate_name: 'Bloomfield Apartments' },
                { id: 359, unit_number: 'B04-06', estate_name: 'Bloomfield Apartments' },
                { id: 360, unit_number: 'B04-07', estate_name: 'Bloomfield Apartments' },
                { id: 361, unit_number: 'B04-08', estate_name: 'Bloomfield Apartments' },
                { id: 362, unit_number: 'B05-01', estate_name: 'Bloomfield Apartments' },
                { id: 363, unit_number: 'B05-02', estate_name: 'Bloomfield Apartments' },
                { id: 364, unit_number: 'B05-03', estate_name: 'Bloomfield Apartments' },
                { id: 365, unit_number: 'B05-04', estate_name: 'Bloomfield Apartments' },
                { id: 366, unit_number: 'B05-05', estate_name: 'Bloomfield Apartments' },
                { id: 367, unit_number: 'B05-06', estate_name: 'Bloomfield Apartments' },
                { id: 368, unit_number: 'B05-07', estate_name: 'Bloomfield Apartments' },
                { id: 369, unit_number: 'B05-08', estate_name: 'Bloomfield Apartments' },
                { id: 370, unit_number: 'B06-01', estate_name: 'Bloomfield Apartments' },
                { id: 371, unit_number: 'B06-02', estate_name: 'Bloomfield Apartments' },
                { id: 372, unit_number: 'B06-03', estate_name: 'Bloomfield Apartments' },
                { id: 373, unit_number: 'B06-04', estate_name: 'Bloomfield Apartments' },
                { id: 374, unit_number: 'B06-05', estate_name: 'Bloomfield Apartments' },
                { id: 375, unit_number: 'B06-06', estate_name: 'Bloomfield Apartments' },
                { id: 376, unit_number: 'B06-07', estate_name: 'Bloomfield Apartments' },
                { id: 377, unit_number: 'B06-08', estate_name: 'Bloomfield Apartments' },
                { id: 378, unit_number: 'B07-01', estate_name: 'Bloomfield Apartments' },
                { id: 379, unit_number: 'B07-02', estate_name: 'Bloomfield Apartments' },
                { id: 380, unit_number: 'B07-03', estate_name: 'Bloomfield Apartments' },
                { id: 381, unit_number: 'B07-04', estate_name: 'Bloomfield Apartments' },
                { id: 382, unit_number: 'B07-05', estate_name: 'Bloomfield Apartments' },
                { id: 383, unit_number: 'B07-06', estate_name: 'Bloomfield Apartments' },
                { id: 384, unit_number: 'B07-07', estate_name: 'Bloomfield Apartments' },
                { id: 385, unit_number: 'B07-08', estate_name: 'Bloomfield Apartments' },
                { id: 386, unit_number: 'B08-01', estate_name: 'Bloomfield Apartments' },
                { id: 387, unit_number: 'B08-02', estate_name: 'Bloomfield Apartments' },
                { id: 388, unit_number: 'B08-03', estate_name: 'Bloomfield Apartments' },
                { id: 389, unit_number: 'B08-04', estate_name: 'Bloomfield Apartments' },
                { id: 390, unit_number: 'B08-05', estate_name: 'Bloomfield Apartments' },
                { id: 391, unit_number: 'B08-06', estate_name: 'Bloomfield Apartments' },
                { id: 392, unit_number: 'B08-07', estate_name: 'Bloomfield Apartments' },
                { id: 393, unit_number: 'B08-08', estate_name: 'Bloomfield Apartments' },
                { id: 394, unit_number: 'B09-01', estate_name: 'Bloomfield Apartments' },
                { id: 395, unit_number: 'B09-02', estate_name: 'Bloomfield Apartments' },
                { id: 396, unit_number: 'B09-03', estate_name: 'Bloomfield Apartments' },
                { id: 397, unit_number: 'B09-04', estate_name: 'Bloomfield Apartments' },
                { id: 398, unit_number: 'B09-05', estate_name: 'Bloomfield Apartments' },
                { id: 399, unit_number: 'B09-06', estate_name: 'Bloomfield Apartments' },
                { id: 400, unit_number: 'B09-07', estate_name: 'Bloomfield Apartments' },
                { id: 401, unit_number: 'B09-08', estate_name: 'Bloomfield Apartments' },
                { id: 402, unit_number: 'B10-01', estate_name: 'Bloomfield Apartments' },
                { id: 403, unit_number: 'B10-02', estate_name: 'Bloomfield Apartments' },
                { id: 404, unit_number: 'B10-03', estate_name: 'Bloomfield Apartments' },
                { id: 405, unit_number: 'B10-04', estate_name: 'Bloomfield Apartments' },
                { id: 406, unit_number: 'B10-05', estate_name: 'Bloomfield Apartments' },
                { id: 407, unit_number: 'B10-06', estate_name: 'Bloomfield Apartments' },
                { id: 408, unit_number: 'B10-07', estate_name: 'Bloomfield Apartments' },
                { id: 409, unit_number: 'B10-08', estate_name: 'Bloomfield Apartments' },
                { id: 420, unit_number: 'B103-1', estate_name: 'Bloomfield Apartments' },
                { id: 421, unit_number: 'B105-1', estate_name: 'Bloomfield Apartments' },
                { id: 410, unit_number: 'B11-01', estate_name: 'Bloomfield Apartments' },
                { id: 411, unit_number: 'B11-02', estate_name: 'Bloomfield Apartments' },
                { id: 412, unit_number: 'B11-03', estate_name: 'Bloomfield Apartments' },
                { id: 413, unit_number: 'B11-04', estate_name: 'Bloomfield Apartments' },
                { id: 414, unit_number: 'B11-05', estate_name: 'Bloomfield Apartments' },
                { id: 415, unit_number: 'B11-06', estate_name: 'Bloomfield Apartments' },
                { id: 416, unit_number: 'B11-07', estate_name: 'Bloomfield Apartments' },
                { id: 417, unit_number: 'B11-08', estate_name: 'Bloomfield Apartments' },
                { id: 418, unit_number: 'B11-09', estate_name: 'Bloomfield Apartments' }
            ];

            // Combine all units
            this.units = [...danaffUnits, ...bloomfieldUnits];
            
            console.log('✅ CRUD Modal - All units loaded!');
            console.log('📊 Total Units:', this.units.length);
            console.log('📊 Danaff Units:', danaffUnits.length);
            console.log('📊 Bloomfield Units:', bloomfieldUnits.length);
        },
        
        openModal() { 
            this.resetForm(); 
            this.showFormModal = true; 
            document.body.style.overflow = 'hidden'; 
        },
        
        closeFormModal() { 
            this.showFormModal = false; 
            document.body.style.overflow = ''; 
        },
        
        closeViewModal() { 
            this.showViewModal = false; 
            document.body.style.overflow = ''; 
        },
        
        closeDeleteModal() { 
            this.showDeleteModal = false; 
            document.body.style.overflow = ''; 
        },
        
        resetForm() {
            this.form = { 
                id: null, 
                unit_id: '', 
                person_name: '', 
                access_type: 'entry', 
                status: 'pending', 
                datetime: new Date().toISOString().slice(0, 16), 
                verified_by: '{{ auth()->user()->name ?? "Security Staff" }}', 
                purpose: '',
                notes: '',
                created_at_formatted: '',
                updated_at_formatted: '',
                original_unit: '',
                original_person: ''
            };
        },
        
        async editLog(logId) { 
            console.log('📝 Editing log:', logId);
            
            if (!logId) {
                alert('Invalid log ID');
                return;
            }
            
            try {
                const response = await fetch(`/security/logs/${logId}`);
                const data = await response.json();
                console.log('📝 Log data:', data);
                
                if (data.success) {
                    const log = data.log;
                    
                    // Populate form with existing data
                    this.form.id = log.id;
                    this.form.unit_id = log.unit_id || '';
                    this.form.person_name = log.person_name || '';
                    this.form.access_type = log.access_type || 'entry';
                    this.form.status = log.status || 'pending';
                    this.form.purpose = log.purpose || '';
                    this.form.notes = log.notes || '';
                    this.form.verified_by = log.verified_by || 'System';
                    this.form.created_at_formatted = log.created_at || '';
                    this.form.updated_at_formatted = log.updated_at || '';
                    
                    // Store original values for display
                    this.form.original_unit = log.unit_number || 'N/A';
                    this.form.original_person = log.person_name || 'N/A';
                    
                    // Format datetime for input
                    if (log.datetime) {
                        const dt = new Date(log.datetime);
                        this.form.datetime = dt.toISOString().slice(0, 16);
                    }
                    
                    // Open the modal with populated data
                    this.showFormModal = true;
                    document.body.style.overflow = 'hidden';
                    
                    console.log('✅ Form populated with data for editing');
                } else {
                    alert('Error loading log data: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading log:', error);
                alert('Error loading log data. Please check console for details.');
            }
        },
        
        async viewLog(logId) { 
            console.log('👁️ Viewing log:', logId);
            try {
                const response = await fetch(`/security/logs/${logId}`);
                const data = await response.json();
                
                if (data.success) {
                    this.viewData = data.log;
                    this.showViewModal = true; 
                    document.body.style.overflow = 'hidden'; 
                } else {
                    alert('Error loading log data');
                }
            } catch (error) {
                console.error('Error loading log:', error);
                alert('Error loading log data');
            }
        },
        
        confirmDelete(logId) { 
            this.deleteId = logId;
            this.showDeleteModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        async confirmDeleteLog() {
            try {
                const response = await fetch(`/security/logs/${this.deleteId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Log deleted successfully');
                    this.closeDeleteModal();
                    location.reload();
                } else {
                    alert('❌ Error deleting log: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error deleting log:', error);
                alert('❌ Error deleting log');
            }
        },
        
        async approveLog(logId) { 
            if (!confirm('Approve this access?')) return;
            
            try {
                const response = await fetch(`/security/logs/${logId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: 'approved' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Access approved');
                    location.reload();
                } else {
                    alert('❌ Error approving access');
                }
            } catch (error) {
                console.error('Error approving log:', error);
                alert('❌ Error approving access');
            }
        },
        
        async denyLog(logId) { 
            if (!confirm('Deny this access?')) return;
            
            try {
                const response = await fetch(`/security/logs/${logId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: 'denied' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Access denied');
                    location.reload();
                } else {
                    alert('❌ Error denying access');
                }
            } catch (error) {
                console.error('Error denying log:', error);
                alert('❌ Error denying access');
            }
        },
        
        async submitForm() {
            this.submitting = true;
            
            try {
                const url = this.form.id ? `/security/logs/${this.form.id}` : '/security/logs';
                const method = this.form.id ? 'PUT' : 'POST';
                
                const payload = {
                    unit_id: this.form.unit_id,
                    visitor_name: this.form.person_name,
                    access_type: this.form.access_type,
                    status: this.form.status,
                    access_time: this.form.datetime,
                    verified_by: this.form.verified_by,
                    purpose: this.form.purpose,
                    notes: this.form.notes,
                };
                
                console.log('📤 Submitting:', payload);
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                console.log('📥 Response:', data);
                
                if (data.success) {
                    alert(this.form.id ? '✅ Log updated successfully' : '✅ Log created successfully');
                    this.closeFormModal();
                    location.reload();
                } else {
                    alert('❌ Error: ' + (data.message || JSON.stringify(data.errors || 'Unknown error')));
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                alert('❌ Error submitting form');
            } finally {
                this.submitting = false;
            }
        }
    }));
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>