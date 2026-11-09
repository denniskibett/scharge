@extends('layouts.app')

@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

@section('content')
@include('partials.card.sms-templates')
<div 
     x-data="smsTemplateTable()" 
     x-init="init()">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">SMS Templates</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of
                    <span x-text="filteredTemplates.length"></span> templates
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <div class="relative">
                    <select x-model="entriesPerPage" @change="updateTable()" id="entriesPerPage" aria-label="Rows per page" class="dark:bg-dark-900 h-11 w-full min-w-[80px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
                </div>
                <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="search" x-model="searchTerm" @input.debounce.300ms="filterTemplates()" id="templateSearch" placeholder="Search templates..." aria-label="Search templates..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
                <button type="button" @click="openCreateModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add Template</button>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading templates...</span>
        </div>

        <!-- Error State -->
        <div x-show="error" class="border-b border-red-200 bg-red-50 p-4">
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
            <table class="data-table w-full table-auto" id="templatesTable">
                <thead class="hidden sm:table-header-group">
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('name')">
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
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                            Content
                        </th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                            Placeholders
                        </th>
                        <th scope="col" class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    <template x-for="template in paginatedTemplates" :key="template.id">
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                        <span class="text-sm font-medium text-purple-600" x-text="template.name ? template.name.charAt(0).toUpperCase() : 'T'"></span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="template.name"></p>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div x-text="template.content && template.content.length > 60 ? template.content.substring(0, 60) + '...' : (template.content || '')"></div>
                            </td>
                            
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="placeholder in (template.placeholders || [])" :key="placeholder">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            <span x-text="placeholder"></span>
                                        </span>
                                    </template>
                                    <span x-show="!template.placeholders || template.placeholders.length === 0" class="text-sm text-gray-400">No placeholders</span>
                                </div>
                            </td>
                            
                            <td class="p-4 whitespace-nowrap text-right">
                                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions"><svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg></button>
                                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                    <button type="button" @click="open = false; openEditModal(template)" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                                    <button type="button" @click="open = false; confirmDelete(template)" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                                  </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    
                    <!-- Empty State -->
                    <tr x-show="filteredTemplates.length === 0">
                        <td colspan="4" class="p-4 text-center">
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
            
        </div>

        <!-- Pagination -->
        <div x-show="filteredTemplates.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
        <div class="pb-3 sm:pb-0">
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Showing <span class="text-gray-800 dark:text-white/90" x-text="showingStart"></span>
            to <span class="text-gray-800 dark:text-white/90" x-text="showingEnd"></span>
            of <span class="text-gray-800 dark:text-white/90" x-text="filteredTemplates.length"></span>
          </span>
        </div>
        <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
          <button type="button" @click="prevPage()" :disabled="currentPage === 1" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Previous page"><span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/>
              </svg></span></button>
          <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
          <ul class="hidden items-center gap-0.5 sm:flex">
            <template x-for="(page, i) in visiblePages" :key="i">
              <li><template x-if="page === '...'"><span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 dark:text-gray-400">...</span></template>
              <template x-if="page !== '...'"><a href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a></template></li>
            </template>
          </ul>
          <button type="button" @click="nextPage()" :disabled="currentPage === totalPages" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Next page"><span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/>
              </svg></span></button>
        </div>
      </div>
    </div>
</div>

<!-- CREATE/EDIT TEMPLATE SLIDEOVER MODAL -->
<div x-data="templateModal()" x-init="init()">
    <template x-if="isOpen">
        <div @click="closeModal()" class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
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
        style="width: 42rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
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
                    <button type="submit" :disabled="loading" class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
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
            <button @click="closeModal()" class="group absolute right-3 top-3 z-999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
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