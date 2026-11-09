{{-- resources/views/partials/modal/companies-create-modal.blade.php --}}
<!-- Companies Create/Edit Slide-over Modal -->
<div x-data="companiesCreateModal()" x-init="init()">
    <!-- Backdrop -->
    <div x-show="showModal" x-cloak @click="closeModal()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>

    <!-- Slide-over panel (slides in from the right) -->
    <div x-show="showModal" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 42rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <!-- Modal Body -->
            <form @submit.prevent="saveCompany">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="modalTitle"></h4>
                    <div class="space-y-6">
                        <!-- Company Details -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Company Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Company Name <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="formData.name" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="e.g., Acme Corp"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Registration Number</label>
                                    <input type="text" x-model="formData.registration_number" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="e.g., REG-2024-001">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email</label>
                                    <input type="email" x-model="formData.email" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="contact@company.com">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Phone</label>
                                    <input type="text" x-model="formData.phone" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="+254 700 000 000">
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Address</label>
                                <textarea x-model="formData.address" rows="2" 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                    placeholder="Street address, city, country"></textarea>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center space-x-3">
                                <div class="relative inline-flex items-center">
                                    <input type="checkbox" x-model="formData.is_active" 
                                        class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-2 focus:ring-blue-500 transition">
                                    <label class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Company is visible and operational</span>
                            </div>
                        </div>

                        <!-- Admin User Section (Only for create mode) -->
                        <div x-show="modalMode === 'create'" class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800/30">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                Admin User
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Create an administrator user for this company</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="newAdminUser.first_name" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="John"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="newAdminUser.last_name" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="Doe"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email <span class="text-red-500">*</span></label>
                                    <input type="email" x-model="newAdminUser.email" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="admin@company.com"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Phone</label>
                                    <input type="text" x-model="newAdminUser.phone" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="+254 700 000 000">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password <span class="text-red-500">*</span></label>
                                    <input type="password" x-model="newAdminUser.password" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                        placeholder="••••••••"
                                        required
                                        minlength="8">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minimum 8 characters</p>
                                </div>
                            </div>
                        </div>

                        <!-- Company Preview -->
                        <div x-show="formData.name" class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800/30">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"><x-icon name="clipboard-document-list" class="inline-block h-4 w-4 shrink-0 align-[-0.15em]" /> Company Preview:</p>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Name:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="formData.name || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Registration:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="formData.registration_number || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Email:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="formData.email || '—'"></span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Phone:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="formData.phone || '—'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end w-full gap-3 mt-6">
                        <button type="button" @click="closeModal()" 
                            class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving" 
                            class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                            <span x-show="!saving" x-text="modalMode === 'create' ? 'Create Company' : 'Save Changes'"></span>
                            <span x-show="saving" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('companiesCreateModal', () => ({
        showModal: false,
        modalMode: 'create',
        editingId: null,
        saving: false,
        formData: {
            name: '',
            registration_number: '',
            email: '',
            phone: '',
            address: '',
            is_active: true
        },
        newAdminUser: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            password: ''
        },
        
        get modalTitle() {
            return this.modalMode === 'create' ? 'Create New Company' : 'Edit Company';
        },
        
        init() {
            window.companiesCreateModal = this;
        },
        
        openModal(companyId = null) {
            if (companyId) {
                this.modalMode = 'edit';
                this.editingId = companyId;
                this.loadCompany(companyId);
            } else {
                this.modalMode = 'create';
                this.editingId = null;
                this.resetForm();
            }
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.showModal = false;
            this.resetForm();
            document.body.style.overflow = '';
        },
        
        resetForm() {
            this.formData = {
                name: '',
                registration_number: '',
                email: '',
                phone: '',
                address: '',
                is_active: true
            };
            this.newAdminUser = {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                password: ''
            };
            this.saving = false;
        },
        
        async loadCompany(companyId) {
            try {
                const response = await fetch(`/admin/companies/${companyId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Failed to load company data');
                }
                
                const data = await response.json();
                this.formData = {
                    name: data.name || '',
                    registration_number: data.registration_number || '',
                    email: data.email || '',
                    phone: data.phone || '',
                    address: data.address || '',
                    is_active: data.is_active !== undefined ? data.is_active : true
                };
            } catch (error) {
                console.error('Error loading company:', error);
                alert('Error loading company data. Please try again.');
            }
        },
        
        async saveCompany() {
            // Validate required fields
            if (!this.formData.name.trim()) {
                alert('Company Name is required');
                return;
            }
            
            // Validate admin user fields for create mode
            if (this.modalMode === 'create') {
                if (!this.newAdminUser.first_name.trim() || !this.newAdminUser.last_name.trim()) {
                    alert('Please fill in the admin user\'s first and last name');
                    return;
                }
                if (!this.newAdminUser.email.trim()) {
                    alert('Please enter the admin user\'s email');
                    return;
                }
                if (!this.newAdminUser.password || this.newAdminUser.password.length < 8) {
                    alert('Please enter a password with at least 8 characters for the admin user');
                    return;
                }
            }
            
            this.saving = true;
            
            const url = this.modalMode === 'create' ? '/admin/companies' : `/admin/companies/${this.editingId}`;
            const method = this.modalMode === 'create' ? 'POST' : 'PUT';
            
            let payload = { ...this.formData };
            if (this.modalMode === 'create') {
                payload.admin_user = { ...this.newAdminUser };
            }
            
            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.closeModal();
                    // Reload the page to refresh data
                    if (this.modalMode === 'create' && result.credentials) {
                        alert(`${result.message}\n\nAdmin User Credentials:\nEmail: ${result.credentials.email}\nPassword: ${result.credentials.password}\n\nPlease save these credentials!`);
                    } else {
                        alert(result.message || (this.modalMode === 'create' ? 'Company created successfully!' : 'Company updated successfully!'));
                    }
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.message || 'Error saving company');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving company. Please try again.');
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>