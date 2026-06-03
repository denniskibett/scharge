@extends('layouts.app')

@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

@section('content')
<div class="container mx-auto px-4 py-6" 
     x-data="smsTemplateTable()" 
     x-init="init()">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pb-3 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6">
        <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">SMS Templates</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                    <span x-text="filteredTemplates.length"></span> templates
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
                        @input.debounce.300ms="filterTemplates()"
                        id="templateSearch" 
                        placeholder="Search templates..." 
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
                    Add Template
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading templates...</span>
        </div>

        <!-- Error State -->
        <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4 my-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error loading templates</h3>
                    <div class="mt-1 text-sm text-red-700" x-text="errorMessage"></div>
                    <button @click="loadTemplates()" class="mt-2 text-sm font-medium text-red-800 hover:text-red-900">Try again →</button>
                </div>
            </div>
        </div>

        <!-- Templates Table -->
        <div x-show="!loading && !error" class="w-full overflow-x-auto">
            <table class="min-w-full" id="templatesTable">
                <thead class="hidden sm:table-header-group">
                    <tr class="border-gray-100 border-y dark:border-gray-800">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('name')">
                            <div class="flex items-center justify-between">
                                <span>Name</span>
                                <span class="sort-icon text-gray-400 ml-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path x-show="sortColumn === 'name' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        <path x-show="sortColumn === 'name' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        <path x-show="sortColumn !== 'name'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Content
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Placeholders
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                
                <tbody>
                    <template x-for="template in paginatedTemplates" :key="template.id">
                        <tr class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <span class="text-blue-600 font-medium" x-text="template.name ? template.name.charAt(0).toUpperCase() : 'T'"></span>
                                    </div>
                                    <div>
                                        <p class="font-smalls text-gray-800 text-sm dark:text-white/90" x-text="template.name"></p>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="text-xs text-gray-500" x-text="template.content && template.content.length > 60 ? template.content.substring(0, 60) + '...' : (template.content || '')"></div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="placeholder in (template.placeholders || [])" :key="placeholder">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                            <span x-text="placeholder"></span>
                                        </span>
                                    </template>
                                    <span x-show="!template.placeholders || template.placeholders.length === 0" class="text-sm text-gray-400">No placeholders</span>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end space-x-3">
                                    <button @click="openEditModal(template)" class="text-green-600 hover:text-green-900" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>

                                    <button @click="confirmDelete(template)" class="text-red-600 hover:text-red-900" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    
                    <!-- Empty State -->
                    <tr x-show="filteredTemplates.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No templates found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">Try adjusting your search or filter criteria</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="!searchTerm && templates.length === 0">Click "Add Template" to create your first SMS template</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="flex flex-col items-center justify-between px-2 py-4 sm:flex-row sm:px-0" x-show="filteredTemplates.length > 0">
                <div class="hidden sm:flex">
                    <p class="text-sm text-gray-700 dark:text-gray-400">
                        Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                        <span x-text="filteredTemplates.length"></span> results
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

<!-- CREATE/EDIT TEMPLATE SLIDEOVER MODAL -->
<div x-data="templateModal()" x-init="init()">
    <template x-if="isOpen">
        <div @click="closeModal()" class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>
    </template>

    <div x-show="isOpen" 
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        x-cloak
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 38rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-8">
            <button @click="closeModal()" class="group absolute right-3 top-3 z-99999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="isEditMode ? 'Edit Template' : 'Add New Template'"></h4>

                <template x-if="formErrors.length > 0">
                    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc pl-5">
                            <template x-for="error in formErrors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Template Name *</label>
                        <input type="text" x-model="form.name" placeholder="Enter template name" required class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Template Content *</label>
                        @verbatim
                        <textarea x-model="form.content" @input="extractPlaceholders" rows="6" placeholder="Enter SMS content. Use {{ placeholder }} for dynamic content." required class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Use double curly braces for placeholders: {{ customer_name }}, {{ amount }}, etc.</p>
                        @endverbatim
                    </div>

                    <div x-show="detectedPlaceholders.length > 0">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Detected Placeholders</label>
                        <div class="flex flex-wrap gap-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <template x-for="placeholder in detectedPlaceholders" :key="placeholder">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    <span x-text="placeholder"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button @click="closeModal()" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
                    <button type="submit" :disabled="loading" class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                        <span x-show="!loading" x-text="isEditMode ? 'Update Template' : 'Create Template'"></span>
                        <span x-show="loading" x-text="isEditMode ? 'Updating...' : 'Creating...'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE TEMPLATE MODAL (Centered) -->
<div x-data="deleteTemplateModal()" x-init="init()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" @click="closeModal()"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8">
            <button @click="closeModal()" class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <div class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                
                <h4 class="mb-2 text-lg font-medium text-gray-800 dark:text-white/90">Delete Template</h4>
                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">Are you sure you want to delete "<span x-text="template?.name" class="font-medium"></span>"? This action cannot be undone.</p>

                <div class="flex items-center justify-center gap-3">
                    <button @click="closeModal()" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
                    <button @click="deleteTemplate()" :disabled="loading" type="button" class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-red-600 shadow-theme-xs hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                        <span x-show="!loading">Delete Template</span>
                        <span x-show="loading">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function smsTemplateTable() {
    return {
        templates: @json($templates->items()),
        filteredTemplates: [],
        paginatedTemplates: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        loading: false,
        error: false,
        errorMessage: '',
        
        init() {
            this.filteredTemplates = [...this.templates];
            this.updateTable();
            
            // Make this instance globally available
            window.smsTemplateTable = this;
            
            // Log for debugging
            console.log('SMS Template Table initialized', this.templates.length, 'templates');
        },
        
        filterTemplates() {
            if (!this.searchTerm.trim()) {
                this.filteredTemplates = [...this.templates];
            } else {
                const term = this.searchTerm.toLowerCase();
                this.filteredTemplates = this.templates.filter(template => {
                    return (
                        (template.name && template.name.toLowerCase().includes(term)) ||
                        (template.content && template.content.toLowerCase().includes(term)) ||
                        (template.placeholders && template.placeholders.some(p => p.toLowerCase().includes(term)))
                    );
                });
            }
            
            this.sortTemplates();
            this.updateTable();
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortTemplates();
            this.updateTable();
        },
        
        sortTemplates() {
            this.filteredTemplates.sort((a, b) => {
                let aValue, bValue;
                if (this.sortColumn === 'content') {
                    aValue = (a.content || '').toLowerCase();
                    bValue = (b.content || '').toLowerCase();
                } else {
                    aValue = (a[this.sortColumn] || '').toLowerCase();
                    bValue = (b[this.sortColumn] || '').toLowerCase();
                }
                if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredTemplates.length / this.entriesPerPage) || 1;
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedTemplates = this.filteredTemplates.slice(startIndex, endIndex);
            this.showingStart = this.filteredTemplates.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredTemplates.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 1) return [1];
            pages.push(1);
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('...');
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) pages.push(i);
            }
            if (end < total - 1) pages.push('...');
            if (total > 1) pages.push(total);
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
            console.log('Opening create modal');
            if (window.templateModal) {
                window.templateModal.openModal();
            } else {
                console.error('templateModal not found');
            }
        },
        
        openEditModal(template) {
            console.log('Opening edit modal for:', template.name);
            if (window.templateModal) {
                window.templateModal.openModal(template);
            } else {
                console.error('templateModal not found');
            }
        },
        
        confirmDelete(template) {
            console.log('Confirm delete for:', template.name);
            if (window.deleteTemplateModal) {
                window.deleteTemplateModal.openModal(template);
            } else {
                console.error('deleteTemplateModal not found');
                // Fallback to browser confirm
                if (confirm(`Delete template "${template.name}"?`)) {
                    this.deleteTemplateDirect(template.id);
                }
            }
        },
        
        async deleteTemplateDirect(templateId) {
            try {
                const baseUrl = '{{ url("/sms/templates") }}';
                const response = await fetch(`${baseUrl}/${templateId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    this.removeTemplate(templateId);
                    if (window.successModal) {
                        window.successModal.simple('Template Deleted', 'Template deleted successfully!');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        },
        
        addTemplate(template) {
            this.templates.unshift(template);
            this.filterTemplates();
            this.updateTable();
        },
        
        updateTemplate(updatedTemplate) {
            const index = this.templates.findIndex(t => t.id === updatedTemplate.id);
            if (index !== -1) {
                this.templates[index] = updatedTemplate;
                this.filterTemplates();
                this.updateTable();
            }
        },
        
        removeTemplate(templateId) {
            this.templates = this.templates.filter(t => t.id !== templateId);
            this.filterTemplates();
            this.updateTable();
        }
    };
}

function templateModal() {
    return {
        isOpen: false,
        isEditMode: false,
        currentTemplate: null,
        form: {
            name: '',
            content: ''
        },
        detectedPlaceholders: [],
        formErrors: [],
        loading: false,
        
        init() {
            window.templateModal = this;
            console.log('Template Modal initialized');
        },
        
        openModal(template = null) {
            console.log('Template modal opening', template ? 'edit mode' : 'create mode');
            this.resetForm();
            if (template) {
                this.isEditMode = true;
                this.currentTemplate = template;
                this.form = {
                    name: template.name || '',
                    content: template.content || ''
                };
                this.extractPlaceholders();
            } else {
                this.isEditMode = false;
                this.currentTemplate = null;
            }
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.isOpen = false;
            this.resetForm();
            document.body.style.overflow = '';
        },
        
        resetForm() {
            this.form = { name: '', content: '' };
            this.detectedPlaceholders = [];
            this.formErrors = [];
            this.loading = false;
        },
        
        extractPlaceholders() {
            if (!this.form.content) {
                this.detectedPlaceholders = [];
                return;
            }
            const regex = /\{\{(.*?)\}\}/g;
            const matches = [...this.form.content.matchAll(regex)];
            this.detectedPlaceholders = [...new Set(matches.map(m => m[1].trim()))];
        },
        
        async submitForm() {
            this.loading = true;
            this.formErrors = [];
            
            const baseUrl = '{{ url("/sms/templates") }}';
            const url = this.isEditMode ? `${baseUrl}/${this.currentTemplate.id}` : baseUrl;
            const method = this.isEditMode ? 'PUT' : 'POST';
            
            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();
                
                if (response.ok && data.success !== false) {
                    if (this.isEditMode) {
                        if (window.smsTemplateTable) {
                            window.smsTemplateTable.updateTemplate(data.template);
                        }
                        if (window.successModal) {
                            window.successModal.simple('Template Updated', `Template "${this.form.name}" has been updated successfully!`);
                        }
                    } else {
                        if (window.smsTemplateTable) {
                            window.smsTemplateTable.addTemplate(data.template);
                        }
                        if (window.successModal) {
                            window.successModal.simple('Template Created', `Template "${this.form.name}" has been created successfully!`);
                        }
                    }
                    this.closeModal();
                } else {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat();
                        if (window.errorModal) {
                            window.errorModal.show(this.isEditMode ? 'Update Failed' : 'Creation Failed', 'Please correct the following errors:', errorMessages);
                        } else {
                            this.formErrors = errorMessages;
                        }
                    } else {
                        const errorMsg = data.message || (this.isEditMode ? 'Failed to update template' : 'Failed to create template');
                        if (window.errorModal) {
                            window.errorModal.show(this.isEditMode ? 'Update Failed' : 'Creation Failed', errorMsg);
                        } else {
                            this.formErrors = [errorMsg];
                        }
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                if (window.errorModal) {
                    window.errorModal.show('Error', 'An unexpected error occurred. Please try again.');
                } else {
                    this.formErrors = ['An unexpected error occurred. Please try again.'];
                }
            } finally {
                this.loading = false;
            }
        }
    };
}

function deleteTemplateModal() {
    return {
        isModalOpen: false,
        template: null,
        loading: false,
        
        init() {
            window.deleteTemplateModal = this;
            console.log('Delete Template Modal initialized');
        },
        
        openModal(template) {
            console.log('Delete modal opening for:', template.name);
            this.template = template;
            this.loading = false;
            this.isModalOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            console.log('Delete modal closing');
            this.isModalOpen = false;
            this.template = null;
            this.loading = false;
            document.body.style.overflow = '';
        },
        
        async deleteTemplate() {
            console.log('Deleting template:', this.template.name);
            this.loading = true;
            
            try {
                const baseUrl = '{{ url("/sms/templates") }}';
                const response = await fetch(`${baseUrl}/${this.template.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    if (window.smsTemplateTable) {
                        window.smsTemplateTable.removeTemplate(this.template.id);
                    }
                    this.closeModal();
                    if (window.successModal) {
                        window.successModal.simple('Template Deleted', data.message || 'Template deleted successfully!');
                    }
                } else {
                    const errorMsg = data.message || 'Failed to delete template.';
                    if (window.errorModal) {
                        window.errorModal.show('Deletion Failed', errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                if (window.errorModal) {
                    window.errorModal.show('Error', 'An unexpected error occurred. Please try again.');
                } else {
                    alert('An error occurred. Please try again.');
                }
            } finally {
                this.loading = false;
            }
        }
    };
}

// Initialize everything when DOM is ready
document.addEventListener('alpine:init', () => {
    console.log('Alpine initialized');
});

// Force initialization after page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM ready, waiting for Alpine...');
        setTimeout(() => {
            console.log('Checking modals:', {
                templateModal: !!window.templateModal,
                deleteTemplateModal: !!window.deleteTemplateModal,
                smsTemplateTable: !!window.smsTemplateTable
            });
        }, 500);
    });
} else {
    setTimeout(() => {
        console.log('Checking modals:', {
            templateModal: !!window.templateModal,
            deleteTemplateModal: !!window.deleteTemplateModal,
            smsTemplateTable: !!window.smsTemplateTable
        });
    }, 500);
}
</script>

<style>
[x-cloak] { display: none !important; }
.modal-close-btn { backdrop-filter: blur(32px); }
.z-99999 { z-index: 99999; }
.z-999 { z-index: 999; }
</style>
@endsection