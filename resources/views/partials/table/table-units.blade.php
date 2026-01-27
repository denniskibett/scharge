    <!-- Units Table with Tabs in Header -->
    <div x-data="unitsTable()" x-init="init()" class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-8">
        <!-- Table Header with Tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 dark:border-gray-700">
            <!-- Tabs -->
            <div class="flex -mb-px">
                <button
                    @click="activeTab = 'all'"
                    :class="activeTab === 'all' 
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400' 
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="inline-flex items-center px-6 py-4 text-sm font-medium border-b-2 transition-colors"
                >
                    All Units
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ $totalUnits }}
                    </span>
                </button>
                
                <button
                    @click="activeTab = 'occupied'"
                    :class="activeTab === 'occupied' 
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400' 
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="inline-flex items-center px-6 py-4 text-sm font-medium border-b-2 transition-colors"
                >
                    Occupied
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        {{ $occupiedCount }}
                    </span>
                </button>
                
                <button
                    @click="activeTab = 'vacant'"
                    :class="activeTab === 'vacant' 
                        ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400' 
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="inline-flex items-center px-6 py-4 text-sm font-medium border-b-2 transition-colors"
                >
                    Vacant
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                        {{ $vacantCount }}
                    </span>
                </button>
            </div>

            
            
            <!-- Search and Controls -->
            <div class="px-6 py-4 flex items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <input 
                        type="text" 
                        x-model="searchTerm"
                        @input.debounce.300ms="filterUnits()"
                        placeholder="Search units..." 
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 pl-10"
                    >
                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                
                </div>

                
                
                <div class="flex items-center">
                    <label for="entriesPerPage" class="text-sm text-gray-500 dark:text-gray-400 mr-2 hidden sm:inline">Show:</label>
                    <div class="relative">
                        <select 
                            x-model="entriesPerPage" 
                            @change="updateTable()"
                            id="entriesPerPage" 
                            class="appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 pr-8"
                        >
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-400 dark:text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>
                 <button 
                    @click="openCreateUnitModal()"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-blue-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Unit
                </button>
            </div>
            
        </div>

        <!-- Table Content -->
        <div class="w-full overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sortBy('unit_number')">
                            <div class="flex items-center justify-between">
                                <span>Unit Number</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" :class="sortColumn === 'unit_number' ? 'text-blue-500' : 'text-gray-300'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'unit_number' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn === 'unit_number' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 15l-7-7-7 7" />
                                    <path x-show="sortColumn !== 'unit_number'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rent Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Tenant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="unit in paginatedUnits" :key="unit.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <a :href="`/units/${unit.id}`" class="text-blue-600 font-medium">
                                            <span x-text="unit.unit_number?.charAt(0) || 'U'"></span>
                                        </a>
                                    </div>
                                    <div>
                                        <a :href="`/units/${unit.id}`" class="font-medium text-gray-800 text-sm dark:text-white/90 hover:text-blue-600">
                                            <span x-text="unit.unit_number"></span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500 dark:text-gray-400" x-text="unit.unit_type"></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    KES <span x-text="formatCurrency(unit.rent_amount)"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <template x-if="unit.active_tenancy && unit.active_tenancy.tenant">
                                    <div>
                                        <a :href="`/tenants/${unit.active_tenancy.tenant_id}`" 
                                           class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                            <span x-text="unit.active_tenancy.tenant.name"></span>
                                        </a>
                                    </div>
                                </template>
                                <template x-if="!unit.active_tenancy || !unit.active_tenancy.tenant">
                                    <span class="text-sm text-gray-400">Vacant</span>
                                </template>
                            </td>
                            <td class="px-6 py-4">
                                <template x-if="unit.active_tenancy && unit.active_tenancy.tenant">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <div x-text="unit.active_tenancy.tenant.phone || '-'"></div>
                                        <div x-text="unit.active_tenancy.tenant.phone2 || ''" class="text-xs text-gray-400"></div>
                                    </div>
                                </template>
                                <template x-if="!unit.active_tenancy || !unit.active_tenancy.tenant">
                                    <span class="text-sm text-gray-400">-</span>
                                </template>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium" :class="unit.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                    KES <span x-text="formatCurrency(unit.balance || 0)"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                      :class="unit.status === 'occupied' 
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                        : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'">
                                    <span x-text="unit.status.charAt(0).toUpperCase() + unit.status.slice(1)"></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end space-x-2">
                                    <button @click="viewUnit(unit)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                    <button @click="openEditUnitModal(unit)" class="text-green-600 hover:text-green-900 dark:text-green-400" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button @click="deleteUnit(unit)" class="text-red-600 hover:text-red-900 dark:text-red-400" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    
                    <template x-if="filteredUnits.length === 0">
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No units found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">
                                    Try adjusting your search criteria
                                </p>
                                <div class="mt-4">
                                    <button 
                                        @click="openCreateUnitModal()"
                                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Add Unit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="flex flex-col items-center justify-between px-4 py-4 border-t border-gray-200 dark:border-gray-700 sm:flex-row">
                <div class="hidden sm:flex">
                    <p class="text-sm text-gray-700 dark:text-gray-400">
                        Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                        <span x-text="filteredUnits.length"></span> results
                    </p>
                </div>
                <div class="flex-1 flex justify-between sm:justify-end w-full sm:w-auto">
                    <button 
                        @click="prevPage()" 
                        :disabled="currentPage === 1"
                        class="relative inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <div class="hidden sm:flex mx-4">
                        <template x-for="page in visiblePages" :key="page">
                            <button 
                                @click="goToPage(page)"
                                :class="{
                                    'relative inline-flex items-center px-3 py-2 text-sm font-medium': true,
                                    'bg-blue-600 text-white': currentPage === page,
                                    'text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700': currentPage !== page && page !== '...',
                                    'cursor-default': page === '...'
                                }"
                                x-text="page"
                            ></button>
                        </template>
                    </div>
                    <button 
                        @click="nextPage()" 
                        :disabled="currentPage === totalPages"
                        class="relative ml-3 inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden data for Alpine.js -->
<script type="application/json" id="estate-units-data">
@json($unitsData ?? [])
</script>

<!-- Create Unit Modal (Mass Add) -->
<div x-data="createUnitModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto modal z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div 
            @click.outside="isModalOpen = false"
            class="relative w-full max-w-[800px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10"
        >
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Add Units
                </h4>

                <div class="mb-6 p-4 bg-blue-50 rounded-lg dark:bg-blue-900/20">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>Tip:</strong> You can add multiple units at once. Each unit will be created with the same rent amount and status.
                    </p>
                </div>

                <!-- Units to Add -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Units to Add
                        </label>
                        <button type="button" @click="addUnitField()" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                            + Add Another Unit
                        </button>
                    </div>
                    
                    <div class="space-y-3" x-ref="unitsContainer">
                        <template x-for="(unit, index) in units" :key="index">
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <input
                                        type="text"
                                        x-model="unit.unit_number"
                                        placeholder="Unit number (e.g., 101, A1, etc.)"
                                        required
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    />
                                </div>
                                <button 
                                    type="button" 
                                    @click="removeUnitField(index)"
                                    class="text-red-600 hover:text-red-800"
                                    :disabled="units.length === 1"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Common Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Unit Type *
                        </label>
                        <select
                            x-model="commonFields.unit_type"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="One Bedroom" selected>One Bedroom</option>
                            <option value="Two Bedroom">Two Bedroom</option>
                            <option value="Studio">Studio</option>
                            <option value="Bedsitter">Bedsitter</option>
                            <option value="Apartment">Apartment</option>
                            <option value="House">House</option>
                            <option value="Office">Office</option>
                            <option value="Shop">Shop</option>
                        </select>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Monthly Rent *
                        </label>
                        <input
                            type="number"
                            x-model="commonFields.rent_amount"
                            placeholder="0.00"
                            min="0"
                            step="0.01"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Status *
                        </label>
                        <select
                            x-model="commonFields.status"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="vacant" selected>Vacant</option>
                            <option value="occupied">Occupied</option>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Summary</h5>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <p>Will create <span x-text="units.length"></span> units with:</p>
                                <ul class="mt-2 space-y-1">
                                    <li>Type: <span x-text="commonFields.unit_type" class="font-medium"></span></li>
                                    <li>Rent: KES <span x-text="commonFields.rent_amount || '0.00'" class="font-medium"></span></li>
                                    <li>Status: <span x-text="commonFields.status.charAt(0).toUpperCase() + commonFields.status.slice(1)" class="font-medium"></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading || units.some(u => !u.unit_number.trim())"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Create <span x-text="units.length"></span> Units</span>
                        <span x-show="loading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Unit Modal -->
<div x-data="editUnitModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto modal z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div 
            @click.outside="isModalOpen = false"
            class="relative w-full max-w-[584px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10"
        >
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Edit Unit
                </h4>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Unit Number *
                        </label>
                        <input
                            type="text"
                            x-model="form.unit_number"
                            placeholder="e.g., HAR-248-1"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.unit_number" x-text="errors.unit_number[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Unit Type
                        </label>
                        <select
                            x-model="form.unit_type"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="One Bedroom">One Bedroom</option>
                            <option value="Two Bedroom">Two Bedroom</option>
                            <option value="Studio">Studio</option>
                            <option value="Bedsitter">Bedsitter</option>
                            <option value="Apartment">Apartment</option>
                            <option value="House">House</option>
                            <option value="Office">Office</option>
                            <option value="Shop">Shop</option>
                        </select>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Monthly Rent *
                        </label>
                        <input
                            type="number"
                            x-model="form.rent_amount"
                            placeholder="e.g., 10000"
                            min="0"
                            step="0.01"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.rent_amount" x-text="errors.rent_amount[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Status
                        </label>
                        <select
                            x-model="form.status"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="vacant">Vacant</option>
                            <option value="occupied">Occupied</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Update Unit</span>
                        <span x-show="loading">Updating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Alert Modal -->
<div x-data="{ isModalOpen: false, message: '' }" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto modal z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div 
            @click.outside="isModalOpen = false"
            class="relative w-full max-w-[600px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10"
        >
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                        fill="currentColor"
                    />
                </svg>
            </button>

            <div class="text-center">
                <div class="relative flex items-center justify-center z-1 mb-7">
                    <svg
                        class="fill-green-50 dark:fill-green-500/15"
                        width="90"
                        height="90"
                        viewBox="0 0 90 90"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M34.364 6.85053C38.6205 -2.28351 51.3795 -2.28351 55.636 6.85053C58.0129 11.951 63.5594 14.6722 68.9556 13.3853C78.6192 11.0807 86.5743 21.2433 82.2185 30.3287C79.7862 35.402 81.1561 41.5165 85.5082 45.0122C93.3019 51.2725 90.4628 63.9451 80.7747 66.1403C75.3648 67.3661 71.5265 72.2695 71.5572 77.9156C71.6123 88.0265 60.1169 93.6664 52.3918 87.3184C48.0781 83.7737 41.9219 83.7737 37.6082 87.3184C29.8831 93.6664 18.3877 88.0266 18.4428 77.9156C18.4735 72.2695 14.6352 67.3661 9.22531 66.1403C-0.462787 63.9451 -3.30193 51.2725 4.49185 45.0122C8.84391 41.5165 10.2138 35.402 7.78151 30.3287C3.42572 21.2433 11.3808 11.0807 21.0444 13.3853C26.4406 14.6722 31.9871 11.951 34.364 6.85053Z"
                            fill=""
                            fill-opacity=""
                        />
                    </svg>

                    <span
                        class="absolute -translate-x-1/2 -translate-y-1/2 left-1/2 top-1/2"
                    >
                        <svg
                            class="fill-green-600 dark:fill-green-500"
                            width="38"
                            height="38"
                            viewBox="0 0 38 38"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M5.9375 19.0004C5.9375 11.7854 11.7864 5.93652 19.0014 5.93652C26.2164 5.93652 32.0653 11.7854 32.0653 19.0004C32.0653 26.2154 26.2164 32.0643 19.0014 32.0643C11.7864 32.0643 5.9375 26.2154 5.9375 19.0004ZM19.0014 2.93652C10.1296 2.93652 2.9375 10.1286 2.9375 19.0004C2.9375 27.8723 10.1296 35.0643 19.0014 35.0643C27.8733 35.0643 35.0653 27.8723 35.0653 19.0004C35.0653 10.1286 27.8733 2.93652 19.0014 2.93652ZM24.7855 17.0575C25.3713 16.4717 25.3713 15.522 24.7855 14.9362C24.1997 14.3504 23.25 14.3504 22.6642 14.9362L17.7177 19.8827L15.3387 17.5037C14.7529 16.9179 13.8031 16.9179 13.2173 17.5037C12.6316 18.0894 12.6316 19.0392 13.2173 19.625L16.657 23.0647C16.9383 23.346 17.3199 23.504 17.7177 23.504C18.1155 23.504 18.4971 23.346 18.7784 23.0647L24.7855 17.0575Z"
                                fill=""
                            />
                        </svg>
                    </span>
                </div>

                <h4
                    class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90 sm:text-title-sm"
                    x-text="message.title || 'Success!'"
                ></h4>
                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400" x-text="message.text || 'Operation completed successfully.'"></p>

                <div class="flex items-center justify-center w-full gap-3 mt-7">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-green-600 shadow-theme-xs hover:bg-green-700 sm:w-auto"
                    >
                        Okay, Got It
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function unitsTable() {
    return {
        // Data from controller
        units: [],
        filteredUnits: [],
        paginatedUnits: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'unit_number',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        activeTab: 'all',
        
        // Modal references
        createModal: null,
        editModal: null,
        successModal: null,
        
        init() {
            // Get units data from hidden element
            const unitsElement = document.getElementById('estate-units-data');
            if (unitsElement) {
                this.units = JSON.parse(unitsElement.textContent);
            }
            
            // Initialize modals
            this.$nextTick(() => {
                this.createModal = Alpine.$data(document.querySelector('[x-data="createUnitModal()"]'));
                this.editModal = Alpine.$data(document.querySelector('[x-data="editUnitModal()"]'));
                this.successModal = Alpine.$data(document.querySelector('[x-data*="isModalOpen: false, message: \'\'"]'));
            });
            
            this.filterUnits();
            this.updateTable();
        },
        
        filterUnits() {
            let filtered = [...this.units];
            
            // Apply tab filter
            if (this.activeTab === 'occupied') {
                filtered = filtered.filter(unit => unit.status === 'occupied');
            } else if (this.activeTab === 'vacant') {
                filtered = filtered.filter(unit => unit.status === 'vacant');
            }
            
            // Apply search filter
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(unit => {
                    return (
                        unit.unit_number?.toLowerCase().includes(term) ||
                        unit.unit_type?.toLowerCase().includes(term) ||
                        (unit.active_tenancy?.tenant?.name?.toLowerCase() || '').includes(term) ||
                        (unit.active_tenancy?.tenant?.phone?.toLowerCase() || '').includes(term)
                    );
                });
            }
            
            this.filteredUnits = filtered;
            this.sortUnits();
            this.updateTable();
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            
            this.sortUnits();
            this.updateTable();
        },
        
        sortUnits() {
            this.filteredUnits.sort((a, b) => {
                let aValue, bValue;
                
                if (this.sortColumn === 'rent_amount' || this.sortColumn === 'balance') {
                    aValue = parseFloat(a[this.sortColumn]) || 0;
                    bValue = parseFloat(b[this.sortColumn]) || 0;
                } else {
                    aValue = a[this.sortColumn]?.toString().toLowerCase() || '';
                    bValue = b[this.sortColumn]?.toString().toLowerCase() || '';
                }
                
                if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredUnits.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            
            this.paginatedUnits = this.filteredUnits.slice(startIndex, endIndex);
            this.showingStart = this.filteredUnits.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredUnits.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            
            if (total <= 1) return [1];
            
            pages.push(1);
            
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            
            if (start > 2) {
                pages.push('...');
            }
            
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) {
                    pages.push(i);
                }
            }
            
            if (end < total - 1) {
                pages.push('...');
            }
            
            if (total > 1) {
                pages.push(total);
            }
            
            return pages;
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateTable();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.updateTable();
            }
        },
        
        goToPage(page) {
            if (page !== '...') {
                this.currentPage = parseInt(page);
                this.updateTable();
            }
        },
        
        formatCurrency(amount) {
            return new Intl.NumberFormat('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        },
        
        openCreateUnitModal() {
            if (this.createModal) {
                this.createModal.open();
            }
        },
        
        openEditUnitModal(unit) {
            if (this.editModal) {
                this.editModal.open(unit);
            }
        },
        
        viewUnit(unit) {
            window.location.href = `/units/${unit.id}`;
        },
        
        deleteUnit(unit) {
            if (confirm(`Are you sure you want to delete unit ${unit.unit_number}?`)) {
                fetch(`/units/${unit.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (this.successModal) {
                            this.successModal.message = { 
                                title: 'Unit Deleted', 
                                text: `Unit ${unit.unit_number} has been deleted successfully.` 
                            };
                            this.successModal.isModalOpen = true;
                        }
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        },
        
        showSuccess(title, text) {
            if (this.successModal) {
                this.successModal.message = { title, text };
                this.successModal.isModalOpen = true;
            }
        }
    };
}

function createUnitModal() {
    return {
        isModalOpen: false,
        units: [{ unit_number: '' }],
        commonFields: {
            estate_id: {{ $estate->id ?? 'null' }},
            unit_type: 'One Bedroom',
            rent_amount: '0',
            status: 'vacant'
        },
        errors: {},
        loading: false,
        
        open() {
            this.resetForm();
            this.isModalOpen = true;
        },
        
        resetForm() {
            this.units = [{ unit_number: '' }];
            this.commonFields = {
                estate_id: {{ $estate->id ?? 'null' }},
                unit_type: 'One Bedroom',
                rent_amount: '0',
                status: 'vacant'
            };
            this.errors = {};
            this.loading = false;
        },
        
        addUnitField() {
            this.units.push({ unit_number: '' });
        },
        
        removeUnitField(index) {
            if (this.units.length > 1) {
                this.units.splice(index, 1);
            }
        },
        
        async submitForm() {
            // Validate that all units have unit numbers
            const invalidUnits = this.units.filter(unit => !unit.unit_number.trim());
            if (invalidUnits.length > 0) {
                alert('Please fill in all unit numbers.');
                return;
            }
            
            this.loading = true;
            this.errors = {};
            
            try {
                let created = 0;
                let errors = [];
                
                // Create each unit
                for (let unit of this.units) {
                    try {
                        const response = await fetch('{{ route("units.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                estate_id: this.commonFields.estate_id,
                                unit_number: unit.unit_number.trim(),
                                unit_type: this.commonFields.unit_type,
                                rent_amount: this.commonFields.rent_amount,
                                status: this.commonFields.status
                            })
                        });
                        
                        if (response.ok) {
                            created++;
                        } else {
                            const data = await response.json();
                            errors.push(`Failed to create unit ${unit.unit_number}: ${data.message || 'Unknown error'}`);
                        }
                    } catch (error) {
                        errors.push(`Error creating unit ${unit.unit_number}: ${error.message}`);
                    }
                }
                
                this.isModalOpen = false;
                
                const successModal = Alpine.$data(document.querySelector('[x-data*="isModalOpen: false, message: \'\'"]'));
                if (successModal) {
                    if (errors.length === 0) {
                        successModal.message = { 
                            title: 'Units Created Successfully', 
                            text: `Successfully created ${created} units.` 
                        };
                    } else {
                        successModal.message = { 
                            title: 'Units Creation Completed', 
                            text: `Created ${created} units. ${errors.length} failed. ${errors.join(', ')}` 
                        };
                    }
                    successModal.isModalOpen = true;
                }
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
            } catch (error) {
                console.error('Error:', error);
            } finally {
                this.loading = false;
            }
        }
    };
}

function editUnitModal() {
    return {
        isModalOpen: false,
        unit: null,
        form: {
            unit_number: '',
            unit_type: 'One Bedroom',
            rent_amount: '',
            status: 'vacant'
        },
        errors: {},
        loading: false,
        
        open(unit) {
            this.unit = unit;
            this.form = {
                unit_number: unit.unit_number,
                unit_type: unit.unit_type || 'One Bedroom',
                rent_amount: unit.rent_amount,
                status: unit.status
            };
            this.errors = {};
            this.loading = false;
            this.isModalOpen = true;
        },
        
        async submitForm() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch(`/units/${this.unit.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.isModalOpen = false;
                    const successModal = Alpine.$data(document.querySelector('[x-data*="isModalOpen: false, message: \'\'"]'));
                    if (successModal) {
                        successModal.message = { 
                            title: 'Unit Updated', 
                            text: `Unit ${this.form.unit_number} has been updated successfully.` 
                        };
                        successModal.isModalOpen = true;
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.errors = data.errors || {};
                }
            } catch (error) {
                console.error('Error:', error);
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.modal-close-btn {
    backdrop-filter: blur(32px);
}
.z-99999 {
    z-index: 99999;
}
.z-999 {
    z-index: 999;
}
</style>
