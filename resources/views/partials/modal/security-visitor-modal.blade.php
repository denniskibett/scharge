<!-- resources/views/partials/modal/security-visitor-modal.blade.php -->
<div x-data="securityVisitorModal()" x-init="init()" x-cloak>
    <div 
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="closeModal()"></div>

        <div class="flex min-h-screen items-center justify-center p-4">
            <div 
                class="relative w-full max-w-6xl rounded-2xl bg-white dark:bg-gray-900 shadow-2xl"
                x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
                x-transition:enter-start="scale-95 opacity-0"
                x-transition:enter-end="scale-100 opacity-100"
                x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
                x-transition:leave-start="scale-100 opacity-100"
                x-transition:leave-end="scale-95 opacity-0"
                @click.stop
            >
                <!-- Header -->
                <div class="sticky top-0 z-20 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900 rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                            <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">👥 Visitor Management</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="totalVisitors"></span> visitors registered
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            @click="openCreateModal()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-700"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Visitor
                        </button>
                        <button 
                            @click="loadData()"
                            :disabled="loading"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-600 disabled:opacity-50"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh
                        </button>
                        <button @click="closeModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="max-h-[calc(100vh-200px)] overflow-y-auto p-6">
                    <!-- Loading -->
                    <div x-show="loading" class="flex items-center justify-center py-12">
                        <svg class="h-8 w-8 animate-spin text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="ml-3 text-gray-600">Loading visitors...</span>
                    </div>

                    <!-- Filters -->
                    <div x-show="!loading" class="mb-4 flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-[200px]">
                            <span class="absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input 
                                type="text" 
                                x-model="filters.search"
                                @input.debounce.300ms="loadData()"
                                placeholder="Search by name, phone, ID, or company..." 
                                class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                        </div>
                        <select 
                            x-model="filters.visitor_type"
                            @change="loadData()"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">All Types</option>
                            <option value="guest">Guest</option>
                            <option value="family">Family</option>
                            <option value="employee">Employee</option>
                            <option value="contractor">Contractor</option>
                            <option value="delivery">Delivery</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="one_time">One-time</option>
                        </select>
                        <select 
                            x-model="filters.status"
                            @change="loadData()"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="blacklisted">Blacklisted</option>
                        </select>
                        <button 
                            @click="filters = {search: '', visitor_type: '', status: ''}; loadData()"
                            x-show="filters.search || filters.visitor_type || filters.status"
                            class="rounded-lg bg-gray-200 px-3 py-2 text-sm hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                        >
                            Clear Filters
                        </button>
                    </div>

                    <!-- Table -->
                    <div x-show="!loading && visitors.length > 0" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Unit</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Visits</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <template x-for="(visitor, index) in visitors" :key="visitor.id">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        <td class="px-4 py-3 text-sm text-gray-500" x-text="index + 1"></td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="visitor.full_name"></span>
                                                <span x-show="visitor.company" class="text-xs text-gray-500" x-text="visitor.company"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-text="visitor.phone"></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400" x-text="visitor.visitor_type_label"></span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-text="visitor.unit_number"></td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-text="visitor.visit_count || 0"></td>
                                        <td class="px-4 py-3">
                                            <span x-show="visitor.is_blacklisted" class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                                ⛔ Blacklisted
                                            </span>
                                            <span x-show="!visitor.is_blacklisted && visitor.is_active" class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                ✅ Active
                                            </span>
                                            <span x-show="!visitor.is_blacklisted && !visitor.is_active" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">
                                                ⚠️ Inactive
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button @click="viewVisitor(visitor.id)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400" title="View">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                                <button @click="editVisitor(visitor.id)" class="text-green-600 hover:text-green-800 dark:text-green-400" title="Edit">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button @click="toggleBlacklist(visitor.id)" :class="visitor.is_blacklisted ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800'" :title="visitor.is_blacklisted ? 'Remove from blacklist' : 'Blacklist'">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                    </svg>
                                                </button>
                                                <button @click="deleteVisitor(visitor.id)" class="text-red-600 hover:text-red-800 dark:text-red-400" title="Delete">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- No Results -->
                    <div x-show="!loading && visitors.length === 0" class="text-center py-12">
                        <svg class="h-16 w-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No visitors found</p>
                        <p class="text-xs text-gray-400">Click "Add Visitor" to register a new visitor</p>
                    </div>

                    <!-- Pagination -->
                    <div x-show="!loading && visitors.length > 0" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            Showing <span x-text="((currentPage - 1) * perPage) + 1"></span> to 
                            <span x-text="Math.min(currentPage * perPage, totalVisitors)"></span> of 
                            <span x-text="totalVisitors"></span> visitors
                        </div>
                        <div class="flex gap-2">
                            <button @click="prevPage()" :disabled="currentPage <= 1" class="px-3 py-1 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-800">Previous</button>
                            <span class="px-3 py-1 text-sm" x-text="currentPage + ' / ' + totalPages"></span>
                            <button @click="nextPage()" :disabled="currentPage >= totalPages" class="px-3 py-1 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-800">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Form -->
    <div x-show="showForm" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" style="display: none;">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showForm = false"></div>
            <div class="relative w-full max-w-2xl rounded-2xl bg-white dark:bg-gray-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold" x-text="formMode === 'edit' ? 'Edit Visitor' : 'Add New Visitor'"></h3>
                    <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="max-h-[calc(100vh-250px)] overflow-y-auto p-6">
                    <form @submit.prevent="saveVisitor()">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name *</label>
                                <input type="text" x-model="formData.first_name" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                                <input type="text" x-model="formData.last_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone *</label>
                                <input type="text" x-model="formData.phone" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                <input type="email" x-model="formData.email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Number</label>
                                <input type="text" x-model="formData.id_number" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Type</label>
                                <select x-model="formData.id_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <option value="national_id">National ID</option>
                                    <option value="passport">Passport</option>
                                    <option value="driver_license">Driver's License</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Visitor Type *</label>
                                <select x-model="formData.visitor_type" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <option value="guest">Guest</option>
                                    <option value="family">Family</option>
                                    <option value="employee">Employee</option>
                                    <option value="contractor">Contractor</option>
                                    <option value="delivery">Delivery</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="one_time">One-time</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company</label>
                                <input type="text" x-model="formData.company" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estate *</label>
                                <select x-model="formData.estate_id" @change="loadUnitsByEstate()" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <option value="">Select Estate</option>
                                    <template x-for="estate in estates" :key="estate.id">
                                        <option :value="estate.id" x-text="estate.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Unit *</label>
                                <select x-model="formData.unit_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <option value="">Select Unit</option>
                                    <template x-for="unit in filteredUnits" :key="unit.id">
                                        <option :value="unit.id" x-text="unit.unit_number"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Registration</label>
                                <input type="text" x-model="formData.vehicle_registration" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Model</label>
                                <input type="text" x-model="formData.vehicle_model" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Color</label>
                                <input type="text" x-model="formData.vehicle_color" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valid Until</label>
                                <input type="date" x-model="formData.valid_until" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                <textarea x-model="formData.notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-3">
                            <button type="button" @click="showForm = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Cancel</button>
                            <button type="submit" :disabled="submitting" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                                <span x-show="!submitting">Save Visitor</span>
                                <span x-show="submitting">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div x-show="showView" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" style="display: none;">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showView = false"></div>
            <div class="relative w-full max-w-3xl rounded-2xl bg-white dark:bg-gray-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold">Visitor Details</h3>
                    <button @click="showView = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="max-h-[calc(100vh-200px)] overflow-y-auto p-6">
                    <div x-show="viewingVisitor">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div><strong>Name:</strong> <span x-text="viewingVisitor.full_name"></span></div>
                            <div><strong>Phone:</strong> <span x-text="viewingVisitor.phone"></span></div>
                            <div><strong>Email:</strong> <span x-text="viewingVisitor.email || 'N/A'"></span></div>
                            <div><strong>ID Number:</strong> <span x-text="viewingVisitor.id_number || 'N/A'"></span></div>
                            <div><strong>Type:</strong> <span x-text="viewingVisitor.visitor_type_label"></span></div>
                            <div><strong>Company:</strong> <span x-text="viewingVisitor.company || 'N/A'"></span></div>
                            <div><strong>Unit:</strong> <span x-text="viewingVisitor.unit_number"></span></div>
                            <div><strong>Estate:</strong> <span x-text="viewingVisitor.estate_name"></span></div>
                            <div><strong>Vehicle:</strong> <span x-text="viewingVisitor.vehicle_registration || 'N/A'"></span></div>
                            <div><strong>Visits:</strong> <span x-text="viewingVisitor.visit_count || 0"></span></div>
                            <div><strong>Status:</strong> 
                                <span x-show="viewingVisitor.is_blacklisted" class="text-red-600">Blacklisted</span>
                                <span x-show="!viewingVisitor.is_blacklisted && viewingVisitor.is_active" class="text-green-600">Active</span>
                                <span x-show="!viewingVisitor.is_blacklisted && !viewingVisitor.is_active" class="text-gray-600">Inactive</span>
                            </div>
                            <div><strong>Valid Until:</strong> <span x-text="viewingVisitor.valid_until || 'N/A'"></span></div>
                            <div class="sm:col-span-2"><strong>Notes:</strong> <span x-text="viewingVisitor.notes || 'No notes'"></span></div>
                        </div>
                        
                        <div class="mt-6">
                            <h4 class="mb-3 text-sm font-semibold">Visit History</h4>
                            <div x-show="historyLoading" class="text-center py-4">Loading history...</div>
                            <div x-show="history.length === 0 && !historyLoading" class="text-center py-4 text-gray-500">No visit history</div>
                            <div x-show="history.length > 0" class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Date</th>
                                            <th class="px-3 py-2 text-left">Type</th>
                                            <th class="px-3 py-2 text-left">Unit</th>
                                            <th class="px-3 py-2 text-left">Status</th>
                                            <th class="px-3 py-2 text-left">Verified By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="log in history" :key="log.id">
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <td class="px-3 py-2" x-text="log.access_time"></td>
                                                <td class="px-3 py-2" x-text="log.access_type"></td>
                                                <td class="px-3 py-2" x-text="log.unit_number"></td>
                                                <td class="px-3 py-2">
                                                    <span :class="log.status === 'Approved' ? 'text-green-600' : log.status === 'Pending' ? 'text-yellow-600' : 'text-red-600'" x-text="log.status"></span>
                                                </td>
                                                <td class="px-3 py-2" x-text="log.verified_by"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button @click="showView = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
.max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar {
    width: 6px;
}
.max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}
.max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 8px;
}
.max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
.dark .max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-track {
    background: #1f1f1f;
}
.dark .max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-thumb {
    background: #404040;
}
.dark .max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-thumb:hover {
    background: #555555;
}
</style>

<script>
document.addEventListener('alpine:init', function() {
    Alpine.data('securityVisitorModal', function() {
        return {
            open: false,
            loading: false,
            submitting: false,
            visitors: [],
            totalVisitors: 0,
            currentPage: 1,
            totalPages: 1,
            perPage: 20,
            filters: {
                search: '',
                visitor_type: '',
                status: ''
            },
            estates: [],
            allUnits: [],
            filteredUnits: [],
            showForm: false,
            showView: false,
            formMode: 'create',
            formData: {},
            viewingVisitor: null,
            history: [],
            historyLoading: false,
            
            async init() {
                window.securityVisitorModal = this;
                await this.loadEstatesAndUnits();
                console.log('✅ Visitor Management Modal initialized');
            },
            
            openModal() {
                this.open = true;
                document.body.style.overflow = 'hidden';
                this.loadData();
            },
            
            closeModal() {
                this.open = false;
                document.body.style.overflow = 'auto';
                this.visitors = [];
            },
            
            openCreateModal() {
                this.formMode = 'create';
                this.formData = {
                    first_name: '',
                    last_name: '',
                    phone: '',
                    email: '',
                    id_number: '',
                    id_type: 'national_id',
                    visitor_type: 'guest',
                    company: '',
                    estate_id: '',
                    unit_id: '',
                    vehicle_registration: '',
                    vehicle_model: '',
                    vehicle_color: '',
                    valid_until: '',
                    notes: ''
                };
                this.filteredUnits = [];
                this.showForm = true;
            },
            
            async editVisitor(id) {
                try {
                    const response = await fetch(`/security/visitors/${id}`);
                    const data = await response.json();
                    if (data.success) {
                        this.formMode = 'edit';
                        this.formData = data.visitor;
                        this.formData.estate_id = data.visitor.estate_id;
                        this.formData.unit_id = data.visitor.unit_id;
                        this.loadUnitsByEstate();
                        this.showForm = true;
                    }
                } catch (error) {
                    console.error('Error loading visitor:', error);
                }
            },
            
            async viewVisitor(id) {
                try {
                    this.showView = true;
                    this.viewingVisitor = null;
                    this.history = [];
                    this.historyLoading = true;
                    
                    const response = await fetch(`/security/visitors/${id}`);
                    const data = await response.json();
                    if (data.success) {
                        this.viewingVisitor = data.visitor;
                    }
                    
                    const historyResponse = await fetch(`/security/visitors/${id}/history`);
                    const historyData = await historyResponse.json();
                    if (historyData.success) {
                        this.history = historyData.logs || [];
                    }
                    this.historyLoading = false;
                } catch (error) {
                    console.error('Error loading visitor details:', error);
                    this.historyLoading = false;
                }
            },
            
            async toggleBlacklist(id) {
                const visitor = this.visitors.find(v => v.id === id);
                if (!visitor) return;
                
                const action = visitor.is_blacklisted ? 'remove from blacklist' : 'blacklist';
                if (!confirm(`Are you sure you want to ${action} this visitor?`)) return;
                
                const reason = visitor.is_blacklisted ? '' : prompt('Reason for blacklisting:') || 'No reason provided';
                
                try {
                    const response = await fetch(`/security/visitors/${id}/blacklist`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ reason: reason })
                    });
                    const data = await response.json();
                    if (data.success) {
                        alert(data.message);
                        this.loadData();
                    }
                } catch (error) {
                    console.error('Error toggling blacklist:', error);
                }
            },
            
            async deleteVisitor(id) {
                if (!confirm('Are you sure you want to delete this visitor?')) return;
                
                try {
                    const response = await fetch(`/security/visitors/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        alert(data.message);
                        this.loadData();
                    }
                } catch (error) {
                    console.error('Error deleting visitor:', error);
                }
            },
            
            async saveVisitor() {
                if (!this.formData.first_name) {
                    alert('Please enter the first name');
                    return;
                }
                if (!this.formData.phone) {
                    alert('Please enter the phone number');
                    return;
                }
                if (!this.formData.visitor_type) {
                    alert('Please select a visitor type');
                    return;
                }
                if (!this.formData.estate_id) {
                    alert('Please select an estate');
                    return;
                }
                if (!this.formData.unit_id) {
                    alert('Please select a unit');
                    return;
                }

                this.submitting = true;
                const url = this.formMode === 'edit' 
                    ? `/security/visitors/${this.formData.id}` 
                    : '/security/visitors';
                const method = this.formMode === 'edit' ? 'PUT' : 'POST';
                
                try {
                    const response = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(this.formData)
                    });
                    
                    const data = await response.json();
                    console.log('Save response:', data);
                    
                    if (data.success) {
                        alert(data.message || 'Visitor saved successfully!');
                        this.showForm = false;
                        this.loadData();
                    } else {
                        let errorMsg = 'Failed to save visitor';
                        if (data.errors) {
                            const errors = Object.values(data.errors).flat().join('\n');
                            errorMsg += '\n\n' + errors;
                        } else if (data.message) {
                            errorMsg = data.message;
                        }
                        alert(errorMsg);
                    }
                } catch (error) {
                    console.error('Error saving visitor:', error);
                    alert('Error saving visitor. Please try again.');
                } finally {
                    this.submitting = false;
                }
            },
            
            async loadData() {
                this.loading = true;
                try {
                    const params = new URLSearchParams({
                        page: this.currentPage,
                        limit: this.perPage,
                        ...this.filters
                    });
                    const response = await fetch(`/security/visitors?${params}`);
                    const data = await response.json();
                    if (data.success) {
                        this.visitors = data.data || [];
                        this.totalVisitors = data.pagination?.total || 0;
                        this.totalPages = data.pagination?.total_pages || 1;
                    }
                } catch (error) {
                    console.error('Error loading visitors:', error);
                } finally {
                    this.loading = false;
                }
            },
            
            async loadEstatesAndUnits() {
                try {
                    // Load estates from database
                    const estateResponse = await fetch('/security/estates-data');
                    const estateData = await estateResponse.json();
                    if (estateData.success) {
                        this.estates = estateData.estates;
                    } else {
                        // Fallback to hardcoded if API fails
                        this.estates = [
                            { id: 1, name: 'Danaff Towers' },
                            { id: 2, name: 'Bloomfield Apartments' }
                        ];
                    }

                    // Load all units from database
                    const unitResponse = await fetch('/security/all-units-data');
                    const unitData = await unitResponse.json();
                    if (unitData.success) {
                        this.allUnits = unitData.units;
                    } else {
                        // Fallback to empty array if API fails
                        this.allUnits = [];
                    }
                    
                    console.log('✅ Loaded', this.estates.length, 'estates and', this.allUnits.length, 'units from database');
                } catch (error) {
                    console.error('Error loading estates and units:', error);
                    // Fallback to hardcoded values
                    this.estates = [
                        { id: 1, name: 'Danaff Towers' },
                        { id: 2, name: 'Bloomfield Apartments' }
                    ];
                    this.allUnits = [];
                }
            },
            
            loadUnitsByEstate() {
                if (this.formData.estate_id) {
                    this.filteredUnits = this.allUnits.filter(
                        unit => unit.estate_id == this.formData.estate_id
                    );
                    this.formData.unit_id = '';
                } else {
                    this.filteredUnits = [];
                }
            },
            
            prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadData();
                }
            },
            
            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadData();
                }
            }
        };
    });
});

window.securityVisitorModal = null;
</script>