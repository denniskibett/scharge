{{-- resources/views/partials/modal/user-assign-modal.blade.php --}}
<!-- User Assign Company Slide-over Modal -->
<div x-data="userAssignModal()" x-init="init()">
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
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="assignUser">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Assign User to Company</h4>
                    <div class="space-y-6">
                        <!-- User Info -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-4">
                                <div class="h-14 w-14 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 text-xl font-bold">
                                    <span x-text="userInitial"></span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">User</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" x-text="userName"></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="userEmail"></p>
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 mt-1" x-text="userRole"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                Assign Role
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <template x-for="role in availableRoles" :key="role.id">
                                    <div @click="selectedRoleId = role.id" 
                                        :class="selectedRoleId === role.id ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20 border-blue-500' : 'hover:bg-gray-100 dark:hover:bg-gray-700/50'"
                                        class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer transition-all">
                                        <div class="h-8 w-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                            :class="selectedRoleId === role.id ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'">
                                            <span x-text="role.initial"></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="role.name"></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="role.description || 'No description'"></p>
                                        </div>
                                        <div x-show="selectedRoleId === role.id" class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                            <p x-show="availableRoles.length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                No roles available. Please create roles first.
                            </p>
                        </div>

                        <!-- Company Selection -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                Select Company
                            </h4>
                            
                            <!-- Search Input -->
                            <div class="relative mb-4">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" 
                                    x-model="searchQuery" 
                                    @input="filterCompanies()"
                                    placeholder="Search companies by name, email, or registration..."
                                    class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>

                            <!-- Loading State -->
                            <div x-show="loading" class="flex items-center justify-center py-8">
                                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>

                            <!-- Company List -->
                            <div x-show="!loading" class="space-y-2 max-h-60 overflow-y-auto">
                                <template x-if="filteredCompanies.length === 0">
                                    <div class="text-center py-8">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="searchQuery ? 'No companies match your search' : 'No companies available'"></p>
                                    </div>
                                </template>

                                <template x-for="company in filteredCompanies" :key="company.id">
                                    <div @click="selectCompany(company)" 
                                        :class="selectedCompanyId === company.id ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20 border-blue-500' : 'hover:bg-gray-100 dark:hover:bg-gray-700/50'"
                                        class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer transition-all">
                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                            <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                                <span class="text-blue-600 dark:text-blue-400 font-bold text-sm" x-text="company.initial"></span>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="company.name"></p>
                                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                    <span x-text="company.email"></span>
                                                    <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                                    <span x-text="company.registration_number || 'No Reg No'"></span>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-full" 
                                                        :class="company.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'"
                                                        x-text="company.is_active ? 'Active' : 'Inactive'">
                                                    </span>
                                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400" 
                                                        x-text="company.subscription_status ? company.subscription_status : 'No Subscription'">
                                                    </span>
                                                    <span class="text-gray-400 text-xs" x-text="company.users_count + ' users'"></span>
                                                    <span class="text-gray-400 text-xs" x-text="company.units_count + ' units'"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div x-show="selectedCompanyId === company.id" class="flex-shrink-0 ml-2">
                                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Selected Company Preview -->
                        <div x-show="selectedCompany" class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800/30">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Selected Company</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" x-text="selectedCompany?.name"></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="selectedCompany?.email"></p>
                                </div>
                                <button type="button" @click="selectedCompany = null; selectedCompanyId = null" class="text-gray-400 hover:text-red-500 transition">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Selected Role Preview -->
                        <div x-show="selectedRoleId" class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl p-4 border border-purple-200 dark:border-purple-800/30">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Selected Role</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white" x-text="selectedRoleName"></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="selectedRoleDescription"></p>
                                </div>
                                <button type="button" @click="selectedRoleId = null" class="text-gray-400 hover:text-red-500 transition">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end w-full gap-3 mt-6">
                        <button type="button" @click="closeModal()" 
                            class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">
                            Cancel
                        </button>
                        <button type="submit" :disabled="!selectedCompanyId || !selectedRoleId || saving" 
                            class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                            <span x-show="!saving" class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Assign User
                            </span>
                            <span x-show="saving" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Assigning...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('userAssignModal', () => ({
        showModal: false,
        userId: null,
        userName: '',
        userEmail: '',
        userRole: '',
        userInitial: '',
        searchQuery: '',
        companies: [],
        filteredCompanies: [],
        selectedCompanyId: null,
        selectedCompany: null,
        selectedRoleId: null,
        availableRoles: [],
        loading: false,
        saving: false,
        
        get selectedRoleName() {
            const role = this.availableRoles.find(r => r.id === this.selectedRoleId);
            return role ? role.name : '';
        },
        
        get selectedRoleDescription() {
            const role = this.availableRoles.find(r => r.id === this.selectedRoleId);
            return role ? (role.description || 'No description') : '';
        },
        
        init() {
            window.userAssignModal = this;
        },
        
        openModal(userId, userName, userEmail, userRole) {
            this.userId = userId;
            this.userName = userName || 'Unknown User';
            this.userEmail = userEmail || '';
            this.userRole = userRole || '';
            this.userInitial = this.userName ? this.userName.charAt(0).toUpperCase() : '?';
            this.selectedCompanyId = null;
            this.selectedCompany = null;
            this.selectedRoleId = null;
            this.searchQuery = '';
            this.showModal = true;
            document.body.style.overflow = 'hidden';

            // Load data when modal opens
            this.loadCompanies();
            this.loadRoles();
            
            // Reset filter
            this.filterCompanies();
        },
        
        closeModal() {
            this.showModal = false;
            document.body.style.overflow = '';
        },
        
        async loadCompanies() {
            this.loading = true;
            try {
                const response = await fetch('/admin/companies/data', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.companies = data.companies || data.data || [];
                    // Add initials
                    this.companies = this.companies.map(c => ({
                        ...c,
                        initial: c.name ? c.name.charAt(0).toUpperCase() : '?'
                    }));
                    this.filterCompanies();
                } else {
                    console.error('Failed to load companies');
                    this.companies = [];
                    window.alertModal.showError(
                        'Error Loading Companies',
                        'Failed to load companies. Please try again.'
                    );
                }
            } catch (error) {
                console.error('Error loading companies:', error);
                this.companies = [];
                window.alertModal.showError(
                    'Network Error',
                    'Could not load companies. Please check your connection.'
                );
            } finally {
                this.loading = false;
            }
        },
        
        async loadRoles() {
            try {
                const response = await fetch('/admin/roles/list', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.availableRoles = data.roles || data.data || [];
                    // Add initials
                    this.availableRoles = this.availableRoles.map(r => ({
                        ...r,
                        initial: r.name ? r.name.charAt(0).toUpperCase() : '?'
                    }));
                } else {
                    console.error('Failed to load roles');
                    this.availableRoles = [];
                    window.alertModal.showError(
                        'Error Loading Roles',
                        'Failed to load roles. Please try again.'
                    );
                }
            } catch (error) {
                console.error('Error loading roles:', error);
                this.availableRoles = [];
                window.alertModal.showError(
                    'Network Error',
                    'Could not load roles. Please check your connection.'
                );
            }
        },
        
        filterCompanies() {
            const query = this.searchQuery.toLowerCase().trim();
            if (!query) {
                this.filteredCompanies = this.companies;
                return;
            }
            
            this.filteredCompanies = this.companies.filter(company => {
                const searchable = [
                    company.name,
                    company.email,
                    company.registration_number,
                    company.phone
                ].filter(Boolean).join(' ').toLowerCase();
                return searchable.includes(query);
            });
        },
        
        selectCompany(company) {
            this.selectedCompanyId = company.id;
            this.selectedCompany = company;
        },
        
        validateSelection() {
            if (!this.selectedCompanyId) {
                window.alertModal.showWarning(
                    'Company Required',
                    'Please select a company to assign the user to.'
                );
                return false;
            }
            
            if (!this.selectedRoleId) {
                window.alertModal.showWarning(
                    'Role Required',
                    'Please select a role for the user.'
                );
                return false;
            }
            
            return true;
        },
        
        async assignUser() {
            if (!this.validateSelection()) {
                return;
            }
            
            this.saving = true;
            
            try {
                const response = await fetch(`/admin/users/${this.userId}/assign-company`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ 
                        company_id: this.selectedCompanyId,
                        role_id: this.selectedRoleId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const roleName = this.availableRoles.find(r => r.id === this.selectedRoleId)?.name || 'selected role';
                    
                    // Close modal and show success
                    this.closeModal();
                    
                    window.alertModal.showSuccess(
                        'User Assigned Successfully',
                        `User "${this.userName}" has been assigned to "${this.selectedCompany.name}" with role "${roleName}".`
                    );
                    
                    // Reload after user closes the success modal
                    const checkModalClosed = setInterval(() => {
                        if (!window.alertModal.isOpen) {
                            clearInterval(checkModalClosed);
                            (window.schargeRefresh || (() => location.reload()))();
                        }
                    }, 100);
                    
                } else {
                    window.alertModal.showError(
                        'Assignment Failed',
                        result.message || 'Failed to assign user. Please try again.'
                    );
                }
            } catch (error) {
                console.error('Error:', error);
                window.alertModal.showError(
                    'System Error',
                    'An unexpected error occurred while assigning the user. Please try again.'
                );
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