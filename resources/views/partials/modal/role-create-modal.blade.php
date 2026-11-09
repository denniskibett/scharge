<!-- ROLE FORM MODAL (Create & Edit) -->
<div x-data="roleFormModal()" x-init="init()">
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

            <!-- Body -->
            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="isEditMode ? 'Edit Role' : 'Create New Role'"></h4>
                    <div class="space-y-6">
                        <!-- Role ID (hidden, for edit mode) -->
                        <input type="hidden" x-model="roleId">
                        
                        <!-- Role Name -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Role Name *
                            </label>
                            <input type="text" 
                                x-model="form.name"
                                required
                                placeholder="e.g., property_manager"
                                :disabled="isProtected"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:opacity-50 disabled:cursor-not-allowed">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="isProtected">Protected roles cannot be renamed</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="!isProtected">Use underscores for multi-word roles (e.g., property_manager)</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Description
                            </label>
                            <textarea x-model="form.description" 
                                rows="3"
                                placeholder="Describe the role's purpose..."
                                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
                        </div>

                        <!-- Permissions -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Assign Permissions
                            </label>
                            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700 max-h-80 overflow-y-auto">
                                <!-- Loading State -->
                                <div x-show="loadingPermissions" class="flex items-center justify-center py-8">
                                    <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                
                                <!-- Permissions List -->
                                <div x-show="!loadingPermissions">
                                    <template x-for="(perms, group) in permissionsByGroup" :key="group">
                                        <div class="mb-4 last:mb-0">
                                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" 
                                                x-text="group.toTitleCase() + ' Permissions'">
                                            </h4>
                                            <div class="space-y-1.5">
                                                <template x-for="permission in perms" :key="permission.id">
                                                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/30 transition cursor-pointer">
                                                        <input type="checkbox" 
                                                            x-model="form.permissions"
                                                            :value="permission.id"
                                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                                                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="permission.name"></span>
                                                        <span class="text-xs text-gray-400 ml-auto" x-text="permission.group"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <p x-show="Object.keys(permissionsByGroup).length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                        No permissions available. Please create permissions first.
                                    </p>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="form.permissions.length"></span> permission(s) selected
                            </p>
                        </div>

                        <!-- Protected Role Notice (Edit Mode) -->
                        <div x-show="isEditMode && isProtected" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800/30 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-400">
                                        Protected Role
                                    </p>
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                        This is a system role. The name cannot be changed, but you can modify permissions.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- User Count (Edit Mode) -->
                        <div x-show="isEditMode && userCount > 0" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/30 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-blue-800 dark:text-blue-400">
                                        <span x-text="userCount"></span> user(s) have this role
                                    </p>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Updating permissions will affect all users with this role.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end w-full gap-3 mt-6">
                        <button type="button" @click="closeModal()" 
                            class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving || !form.name"
                            class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                            <span x-show="!saving" class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path x-show="!isEditMode" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    <path x-show="isEditMode" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span x-text="isEditMode ? 'Update Role' : 'Create Role'"></span>
                            </span>
                            <span x-show="saving" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isEditMode ? 'Updating...' : 'Creating...'"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('roleFormModal', () => ({
        showModal: false,
        saving: false,
        loadingPermissions: false,
        isEditMode: false,
        isProtected: false,
        roleId: null,
        roleName: '',
        userCount: 0,
        form: {
            name: '',
            description: '',
            permissions: []
        },
        permissionsByGroup: {},
        allPermissions: [],
        
        init() {
            window.roleFormModal = this;
            this.loadPermissions();
        },
        
        async loadPermissions() {
            this.loadingPermissions = true;
            try {
                // Try to get permissions from the page data first
                const permissions = @json($permissions ?? []);
                if (Object.keys(permissions).length > 0) {
                    this.permissionsByGroup = permissions;
                    // Flatten for easier access
                    this.allPermissions = [];
                    for (const [group, perms] of Object.entries(permissions)) {
                        for (const perm of perms) {
                            this.allPermissions.push({
                                ...perm,
                                group: group
                            });
                        }
                    }
                } else {
                    // Fetch from API if not available
                    const response = await fetch('/admin/roles/permissions', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (response.ok) {
                        const data = await response.json();
                        this.permissionsByGroup = data.permissions || {};
                        this.allPermissions = data.all || [];
                    }
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
                // Don't show error modal here as it might be confusing
            } finally {
                this.loadingPermissions = false;
            }
        },
        
        openCreateModal() {
            this.isEditMode = false;
            this.isProtected = false;
            this.roleId = null;
            this.roleName = '';
            this.userCount = 0;
            this.form = {
                name: '',
                description: '',
                permissions: []
            };
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        openEditModal(role) {
            this.isEditMode = true;
            this.roleId = role.id;
            this.roleName = role.name.replace('_', ' ').toTitleCase();
            this.isProtected = role.is_protected || false;
            this.userCount = role.users_count || 0;
            this.form = {
                name: role.name,
                description: role.description || '',
                permissions: role.permissions || []
            };
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.showModal = false;
            this.saving = false;
            document.body.style.overflow = '';
        },
        
        validateForm() {
            if (!this.form.name || this.form.name.trim() === '') {
                window.alertModal.showWarning(
                    'Validation Error',
                    'Please enter a role name.'
                );
                return false;
            }
            
            // Check if name contains spaces (should use underscores)
            if (this.form.name.includes(' ')) {
                window.alertModal.showWarning(
                    'Invalid Role Name',
                    'Role name should not contain spaces. Use underscores instead (e.g., property_manager).'
                );
                return false;
            }
            
            // Check if name is lowercase
            if (this.form.name !== this.form.name.toLowerCase()) {
                window.alertModal.showWarning(
                    'Invalid Role Name',
                    'Role name should be lowercase. Use underscores for multi-word roles.'
                );
                return false;
            }
            
            return true;
        },
        
        async submitForm() {
            if (!this.validateForm()) {
                return;
            }
            
            this.saving = true;
            
            try {
                const url = this.isEditMode ? `/roles/${this.roleId}` : '/roles';
                const method = this.isEditMode ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.closeModal();
                    
                    const title = this.isEditMode ? 'Role Updated' : 'Role Created';
                    const message = result.message || (this.isEditMode ? 'Role updated successfully!' : 'Role created successfully!');
                    
                    window.alertModal.showSuccess(title, message);
                    
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1500);
                } else {
                    window.alertModal.showError(
                        this.isEditMode ? 'Update Failed' : 'Creation Failed',
                        result.message || 'An error occurred. Please try again.'
                    );
                }
            } catch (error) {
                console.error('Error:', error);
                window.alertModal.showError(
                    'System Error',
                    'An unexpected error occurred. Please try again.'
                );
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>