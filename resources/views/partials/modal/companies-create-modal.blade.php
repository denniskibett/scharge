{{-- resources/views/partials/modal/companies-create-modal.blade.php --}}
<div x-data="companiesCreateModal()" x-init="init()" x-cloak>
    <div x-show="showModal" class="fixed inset-0 z-99999 overflow-hidden" style="display: none;">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>
        <div class="fixed inset-y-0 right-0 max-w-full flex">
            <div class="relative w-screen max-w-md">
                <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold" x-text="modalTitle"></h2>
                            <button @click="closeModal" class="text-white hover:text-gray-200">✕</button>
                        </div>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="saveCompany" class="flex-1">
                        <div class="px-6 py-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
                                <input type="text" x-model="formData.name" class="w-full rounded-lg border-gray-300" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                                <input type="text" x-model="formData.registration_number" class="w-full rounded-lg border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" x-model="formData.email" class="w-full rounded-lg border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" x-model="formData.phone" class="w-full rounded-lg border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <textarea x-model="formData.address" rows="2" class="w-full rounded-lg border-gray-300"></textarea>
                            </div>
                            
                            <!-- Admin User Section (Only for create mode) -->
                            <div x-show="modalMode === 'create'" class="border-t pt-4 mt-4">
                                <h4 class="font-medium text-gray-900 mb-3">Create Admin User</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                        <input type="text" x-model="newAdminUser.first_name" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                        <input type="text" x-model="newAdminUser.last_name" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                        <input type="email" x-model="newAdminUser.email" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                        <input type="password" x-model="newAdminUser.password" class="w-full rounded-lg border-gray-300" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                            <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <span x-text="modalMode === 'create' ? 'Create Company' : 'Save Changes'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('companiesCreateModal', () => ({
        showModal: false,
        modalMode: 'create',
        editingId: null,
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
            // Register globally
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
        },
        
        closeModal() {
            this.showModal = false;
            this.resetForm();
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
        },
        
        async loadCompany(companyId) {
            try {
                const response = await fetch(`/admin/companies/${companyId}`);
                const data = await response.json();
                this.formData = {
                    name: data.name,
                    registration_number: data.registration_number || '',
                    email: data.email || '',
                    phone: data.phone || '',
                    address: data.address || '',
                    is_active: data.is_active
                };
            } catch (error) {
                console.error('Error loading company:', error);
                alert('Error loading company data');
            }
        },
        
        async saveCompany() {
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
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.closeModal();
                    // Reload the table data
                    if (window.companiesTable) {
                        await window.companiesTable.loadCompanies();
                    }
                    if (this.modalMode === 'create' && result.credentials) {
                        alert(`${result.message}\n\nAdmin User Credentials:\nEmail: ${result.credentials.email}\nPassword: ${result.credentials.password}\n\nPlease save these credentials!`);
                    } else {
                        alert(result.message);
                    }
                } else {
                    alert(result.message || 'Error saving company');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving company');
            }
        }
    }));
});
</script>