@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6" x-data="estateTable()" x-init="init()">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pb-3 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6">
        <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Estates Overview</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                    <span x-text="filteredEstates.length"></span> estates
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center">
                    <label for="entriesPerPage" class="text-sm text-gray-500 dark:text-gray-400 mr-2 hidden sm:inline">Show:</label>
                    <div class="relative">
                        <select 
                            x-model="entriesPerPage" 
                            @change="updateTable()"
                            id="entriesPerPage" 
                            class="appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 pr-8"
                        >
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-400 dark:text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="relative flex-1 min-w-[150px]">
                    <input 
                        type="text" 
                        x-model="searchTerm"
                        @input.debounce.300ms="filterEstates()"
                        id="estateSearch" 
                        placeholder="Search estates..." 
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 pl-10"
                    >
                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <button 
                    @click="openCreateModal()"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-medium text-gray-500 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Estate
                </button>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="min-w-full" id="estatesTable">
                <!-- Desktop table header -->
                <thead class="hidden sm:table-header-group">
                    <tr class="border-gray-100 border-y dark:border-gray-800">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('name')">
                            <div class="flex items-center justify-between">
                                <span>Name</span>
                                <span class="sort-icon text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path x-show="sortColumn === 'name' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        <path x-show="sortColumn === 'name' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 15l-7-7-7 7" />
                                        <path x-show="sortColumn !== 'name'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('location')">
                            <div class="flex items-center justify-between">
                                <span>Location</span>
                                <span class="sort-icon text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path x-show="sortColumn === 'location' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        <path x-show="sortColumn === 'location' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 15l-7-7-7 7" />
                                        <path x-show="sortColumn !== 'location'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('units_count')">
                            <div class="flex items-center justify-between">
                                <span>Units</span>
                                <span class="sort-icon text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path x-show="sortColumn === 'units_count' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        <path x-show="sortColumn === 'units_count' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 15l-7-7-7 7" />
                                        <path x-show="sortColumn !== 'units_count'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                
                <!-- Mobile table header -->
                <thead class="sm:hidden">
                    <tr class="border-gray-100 border-y dark:border-gray-800">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estate
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="estatesTableBody">
                    <template x-for="estate in paginatedEstates" :key="estate.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                            <!-- Desktop cells -->
                            <td class="py-3 hidden sm:table-cell">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <a :href="`/estates/${estate.id}`">
                                            <span class="text-blue-600 font-medium" x-text="estate.name.charAt(0).toUpperCase()"></span>
                                        </a>    
                                    </div>
                                    <div>
                                        <a :href="`/estates/${estate.id}`" class="estate-name">
                                            <p class="font-medium text-gray-800 text-sm dark:text-white/90" x-text="estate.name"></p>
                                        </a>
                                        <span class="text-gray-500 text-xs dark:text-gray-400 estate-units">
                                            <span x-text="estate.units_count"></span> units
                                        </span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-3 hidden sm:table-cell">
                                <div class="text-sm text-gray-500 estate-location" x-text="estate.location"></div>
                            </td>
                            
                            <td class="py-3 hidden sm:table-cell">
                                <div class="text-sm text-gray-500" x-text="estate.units_count"></div>
                            </td>
                            
                            <td class="py-3 text-right">
                                <div class="flex justify-end space-x-3">
                                    <!-- View -->
                                    <a :href="`/estates/${estate.id}`" class="text-blue-600 hover:text-blue-900" title="View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </a>

                                    <!-- Edit -->
                                    <button @click="openEditModal(estate)" class="text-green-600 hover:text-green-900" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>

                                    <!-- Delete -->
                                    <button @click="confirmDelete(estate)" class="text-red-600 hover:text-red-900" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            
                            <!-- Mobile cells (simplified view) -->
                            <td class="py-3 sm:hidden">
                                <div class="flex items-center gap-3">
                                    <div class="h-[40px] w-[40px] overflow-hidden rounded-md bg-blue-100 flex items-center justify-center">
                                        <span class="text-blue-600 font-medium" x-text="estate.name.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <a :href="`/estates/${estate.id}`" class="estate-name">
                                            <p class="font-medium text-gray-800 text-sm dark:text-white/90" x-text="estate.name"></p>
                                        </a>
                                        <span class="text-gray-500 text-xs dark:text-gray-400 estate-units">
                                            <span x-text="estate.units_count"></span> units • <span x-text="estate.location"></span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-3 sm:hidden text-right">
                                <div class="flex justify-end space-x-2">
                                    <a :href="`/estates/${estate.id}`" class="text-blue-600 hover:text-blue-900 inline-block" title="View">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </a>
                                    
                                    <button @click="openEditModal(estate)" class="text-green-600 hover:text-green-900 inline-block" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    
                                    <button @click="confirmDelete(estate)" class="text-red-600 hover:text-red-900 inline-block" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    
                    <tr x-show="filteredEstates.length === 0">
                        <td colspan="4" class="py-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No estates found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">
                                Try adjusting your search or filter criteria
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="flex flex-col items-center justify-between px-2 py-4 sm:flex-row sm:px-0">
                <div class="hidden sm:flex">
                    <p class="text-sm text-gray-700 dark:text-gray-400">
                        Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                        <span x-text="filteredEstates.length"></span> results
                    </p>
                </div>
                <div class="flex-1 flex justify-between sm:justify-end">
                    <button 
                        @click="prevPage()" 
                        :disabled="currentPage === 1"
                        class="relative inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <div id="paginationNumbers" class="hidden sm:flex">
                        <template x-for="page in visiblePages" :key="page">
                            <button 
                                @click="goToPage(page)"
                                :class="{
                                    'relative inline-flex items-center px-4 py-2 text-sm font-medium': true,
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
</div>

<!-- Create Estate Modal -->
<div x-data="createEstateModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
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
                    Add New Estate
                </h4>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Estate Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            placeholder="Enter estate name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.name" x-text="errors.name[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Location
                        </label>
                        <input
                            type="text"
                            x-model="form.location"
                            placeholder="Enter estate location"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.location" x-text="errors.location[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Close
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Create Estate</span>
                        <span x-show="loading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Estate Modal -->
<div x-data="editEstateModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
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
                    Edit Estate
                </h4>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Estate Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            placeholder="Enter estate name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.name" x-text="errors.name[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Location
                        </label>
                        <input
                            type="text"
                            x-model="form.location"
                            placeholder="Enter estate location"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.location" x-text="errors.location[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Close
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Update Estate</span>
                        <span x-show="loading">Updating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Estate Modal -->
<div x-data="deleteEstateModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
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

            <div class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                
                <h4 class="mb-2 text-lg font-medium text-gray-800 dark:text-white/90">
                    Delete Estate
                </h4>
                
                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete "<span x-text="estate?.name" class="font-medium"></span>"? 
                    This action cannot be undone and will also delete all associated units.
                </p>

                <div class="flex items-center justify-center gap-3">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteEstate()"
                        :disabled="loading"
                        type="button"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-red-600 shadow-theme-xs hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Delete Estate</span>
                        <span x-show="loading">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function estateTable() {
    return {
        // Data - passed from controller
        estates: [],
        filteredEstates: [],
        paginatedEstates: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        
        // Modal instances will be set in init
        createModal: null,
        editModal: null,
        deleteModal: null,
        
        init() {
            // Set estates data from data attribute
            const estatesElement = document.getElementById('estates-data');
            if (estatesElement) {
                this.estates = JSON.parse(estatesElement.textContent);
            }
            
            this.filteredEstates = [...this.estates];
            this.updateTable();
            
            // Initialize modals
            this.$nextTick(() => {
                this.createModal = Alpine.$data(document.querySelector('[x-data="createEstateModal()"]'));
                this.editModal = Alpine.$data(document.querySelector('[x-data="editEstateModal()"]'));
                this.deleteModal = Alpine.$data(document.querySelector('[x-data="deleteEstateModal()"]'));
            });
        },
        
        filterEstates() {
            if (!this.searchTerm.trim()) {
                this.filteredEstates = [...this.estates];
            } else {
                const term = this.searchTerm.toLowerCase();
                this.filteredEstates = this.estates.filter(estate => {
                    return (
                        estate.name.toLowerCase().includes(term) ||
                        (estate.location && estate.location.toLowerCase().includes(term)) ||
                        estate.units_count.toString().includes(term)
                    );
                });
            }
            
            this.sortEstates();
            this.updateTable();
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            
            this.sortEstates();
            this.updateTable();
        },
        
        sortEstates() {
            this.filteredEstates.sort((a, b) => {
                let aValue, bValue;
                
                if (this.sortColumn === 'units_count') {
                    aValue = a.units_count;
                    bValue = b.units_count;
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
            this.totalPages = Math.ceil(this.filteredEstates.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            
            this.paginatedEstates = this.filteredEstates.slice(startIndex, endIndex);
            this.showingStart = this.filteredEstates.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredEstates.length);
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
        
        openCreateModal() {
            if (this.createModal) {
                this.createModal.open();
            }
        },
        
        openEditModal(estate) {
            if (this.editModal) {
                this.editModal.open(estate);
            }
        },
        
        confirmDelete(estate) {
            if (this.deleteModal) {
                this.deleteModal.open(estate);
            }
        }
    };
}

function createEstateModal() {
    return {
        isModalOpen: false,
        form: {
            name: '',
            location: ''
        },
        errors: {},
        loading: false,
        
        open() {
            this.resetForm();
            this.isModalOpen = true;
        },
        
        resetForm() {
            this.form = {
                name: '',
                location: ''
            };
            this.errors = {};
            this.loading = false;
        },
        
        async submitForm() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch('{{ route("estates.store") }}', {
                    method: 'POST',
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
                    this.showNotification('Estate created successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    this.errors = data.errors || {};
                    this.showNotification(data.message || 'Failed to create estate.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            alert(message);
        }
    };
}

function editEstateModal() {
    return {
        isModalOpen: false,
        estate: null,
        form: {
            name: '',
            location: ''
        },
        errors: {},
        loading: false,
        
        open(estate) {
            this.estate = estate;
            this.form = {
                name: estate.name,
                location: estate.location || ''
            };
            this.errors = {};
            this.loading = false;
            this.isModalOpen = true;
        },
        
        async submitForm() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch(`/estates/${this.estate.id}`, {
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
                    this.showNotification('Estate updated successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    this.errors = data.errors || {};
                    this.showNotification(data.message || 'Failed to update estate.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            alert(message);
        }
    };
}

function deleteEstateModal() {
    return {
        isModalOpen: false,
        estate: null,
        loading: false,
        
        open(estate) {
            this.estate = estate;
            this.loading = false;
            this.isModalOpen = true;
        },
        
        async deleteEstate() {
            this.loading = true;
            
            try {
                const response = await fetch(`/estates/${this.estate.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.isModalOpen = false;
                    this.showNotification('Estate deleted successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    this.showNotification(data.message || 'Failed to delete estate.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            alert(message);
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

<!-- Hidden data element for Alpine.js -->
<script type="application/json" id="estates-data">
@json($estatesData)
</script>

@endsection