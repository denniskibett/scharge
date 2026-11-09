@extends('layouts.app')

@section('title', 'SMS Logs')

@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

@section('content')
@include('partials.card.sms-history')
<div x-data="smsLogsTable()" x-init="init()">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">SMS Logs</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of
                    <span x-text="filteredLogs.length"></span> logs
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
          <input type="search" x-model="searchTerm" @input.debounce.300ms="filterLogs()" id="logSearch" placeholder="Search by phone or message..." aria-label="Search by phone or message..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
                <button type="button" @click="openFilters()" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                    Filters
                    <span x-show="activeFiltersCount > 0" class="ml-1 rounded-full bg-brand-500 px-1.5 py-0.5 text-xs text-white" x-text="activeFiltersCount"></span>
                </button>
                <a href="{{ route('sms.logs.export', request()->query()) }}" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Export CSV
                </a>
            </div>
        </div>

        <!-- Filter Panel (Slide Down) -->
        <div x-show="showFilters" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2"
             class="border-b border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/50">
            <form @submit.prevent="applyFilters()">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                        <input type="date" x-model="filters.start_date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                        <input type="date" x-model="filters.end_date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select x-model="filters.status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            <option value="">All Statuses</option>
                            <option value="sent">Sent/Delivered</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                        <input type="text" x-model="filters.phone" placeholder="e.g., 2547..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" @click="resetFilters()" class="rounded-lg bg-gray-500 px-4 py-2 text-sm text-white hover:bg-gray-600 transition">Reset Filters</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 transition">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading logs...</span>
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
                    <h3 class="text-sm font-medium text-red-800">Error loading logs</h3>
                    <div class="mt-1 text-sm text-red-700" x-text="errorMessage"></div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div x-show="!loading && !error" class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('id')">
                            <div class="flex items-center gap-1">
                                <span>ID</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'id' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'id' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'id'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('recipient_phone')">
                            <div class="flex items-center gap-1">
                                <span>Phone</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'recipient_phone' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'recipient_phone' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'recipient_phone'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Message</th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('status')">
                            <div class="flex items-center gap-1">
                                <span>Status</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'status' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'status' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'status'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Provider ID</th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Failure Reason</th>
                        <th scope="col" class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('created_at')">
                            <div class="flex items-center gap-1">
                                <span>Sent At</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'created_at' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'created_at' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'created_at'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                    </tr>
                </thead>
                
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    <template x-for="log in paginatedLogs" :key="log.id">
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="log.id"></td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono" x-text="log.recipient_phone"></td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div x-text="log.message && log.message.length > 50 ? log.message.substring(0, 50) + '...' : (log.message || '')"></div>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <span x-show="log.status === 'sent' || log.status === 'delivered'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Delivered</span>
                                <span x-show="log.status === 'pending'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Pending</span>
                                <span x-show="log.status === 'failed'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                                <span x-show="log.status !== 'sent' && log.status !== 'delivered' && log.status !== 'pending' && log.status !== 'failed'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300" x-text="log.status"></span>
                            </td>
                            <td class="p-4 whitespace-nowrap font-mono text-xs text-gray-500 dark:text-gray-400" x-text="log.provider_message_id || '-'"></td>
                            <td class="p-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400" x-text="log.failure_reason ? (log.failure_reason.length > 30 ? log.failure_reason.substring(0, 30) + '...' : log.failure_reason) : '-'"></td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="log.created_at ? new Date(log.created_at).toLocaleString() : '-'"></td>
                        </tr>
                    </template>
                    
                    <!-- Empty State -->
                    <tr x-show="filteredLogs.length === 0">
                        <td colspan="7" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No SMS logs found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">Try adjusting your search or filter criteria</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="!searchTerm && activeFiltersCount > 0">Try resetting your filters</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
        </div>

        <!-- Pagination -->
        <div x-show="filteredLogs.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
        <div class="pb-3 sm:pb-0">
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Showing <span class="text-gray-800 dark:text-white/90" x-text="showingStart"></span>
            to <span class="text-gray-800 dark:text-white/90" x-text="showingEnd"></span>
            of <span class="text-gray-800 dark:text-white/90" x-text="filteredLogs.length"></span>
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

<script>
function smsLogsTable() {
    return {
        logs: @json($logs->items()),
        filteredLogs: [],
        paginatedLogs: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'id',
        sortDirection: 'desc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        loading: false,
        error: false,
        errorMessage: '',
        showFilters: false,
        filters: {
            start_date: '{{ request('start_date') }}',
            end_date: '{{ request('end_date') }}',
            status: '{{ request('status') }}',
            phone: '{{ request('phone') }}'
        },
        
        init() {
            this.filteredLogs = [...this.logs];
            this.applyFiltersFromUrl();
            this.updateTable();
            console.log('SMS Logs Table initialized', this.logs.length, 'logs');
        },
        
        applyFiltersFromUrl() {
            let filtered = [...this.logs];
            
            if (this.filters.start_date) {
                filtered = filtered.filter(log => log.created_at && log.created_at.split('T')[0] >= this.filters.start_date);
            }
            if (this.filters.end_date) {
                filtered = filtered.filter(log => log.created_at && log.created_at.split('T')[0] <= this.filters.end_date);
            }
            if (this.filters.status) {
                filtered = filtered.filter(log => log.status === this.filters.status);
            }
            if (this.filters.phone) {
                filtered = filtered.filter(log => log.recipient_phone && log.recipient_phone.includes(this.filters.phone));
            }
            
            this.filteredLogs = filtered;
            this.updateTable();
        },
        
        get activeFiltersCount() {
            let count = 0;
            if (this.filters.start_date) count++;
            if (this.filters.end_date) count++;
            if (this.filters.status) count++;
            if (this.filters.phone) count++;
            return count;
        },
        
        openFilters() {
            this.showFilters = !this.showFilters;
        },
        
        applyFilters() {
            this.applyFiltersFromUrl();
            this.showFilters = false;
            this.currentPage = 1;
            this.updateTable();
            
            // Update URL with filter parameters
            const params = new URLSearchParams();
            if (this.filters.start_date) params.append('start_date', this.filters.start_date);
            if (this.filters.end_date) params.append('end_date', this.filters.end_date);
            if (this.filters.status) params.append('status', this.filters.status);
            if (this.filters.phone) params.append('phone', this.filters.phone);
            
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({}, '', newUrl);
        },
        
        resetFilters() {
            this.filters = {
                start_date: '',
                end_date: '',
                status: '',
                phone: ''
            };
            this.applyFilters();
        },
        
        filterLogs() {
            if (!this.searchTerm.trim()) {
                this.filteredLogs = [...this.logs];
                this.applyFiltersFromUrl();
            } else {
                const term = this.searchTerm.toLowerCase();
                let filtered = [...this.logs];
                
                // Apply URL filters first
                if (this.filters.start_date) {
                    filtered = filtered.filter(log => log.created_at && log.created_at.split('T')[0] >= this.filters.start_date);
                }
                if (this.filters.end_date) {
                    filtered = filtered.filter(log => log.created_at && log.created_at.split('T')[0] <= this.filters.end_date);
                }
                if (this.filters.status) {
                    filtered = filtered.filter(log => log.status === this.filters.status);
                }
                if (this.filters.phone) {
                    filtered = filtered.filter(log => log.recipient_phone && log.recipient_phone.includes(this.filters.phone));
                }
                
                // Then apply search
                this.filteredLogs = filtered.filter(log => {
                    return (
                        (log.recipient_phone && log.recipient_phone.toLowerCase().includes(term)) ||
                        (log.message && log.message.toLowerCase().includes(term))
                    );
                });
            }
            
            this.sortLogs();
            this.updateTable();
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortLogs();
            this.updateTable();
        },
        
        sortLogs() {
            this.filteredLogs.sort((a, b) => {
                let aValue = a[this.sortColumn] || '';
                let bValue = b[this.sortColumn] || '';
                
                if (this.sortColumn === 'created_at') {
                    aValue = new Date(aValue).getTime() || 0;
                    bValue = new Date(bValue).getTime() || 0;
                }
                
                if (typeof aValue === 'string') {
                    aValue = aValue.toLowerCase();
                    bValue = bValue.toLowerCase();
                }
                
                if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredLogs.length / this.entriesPerPage) || 1;
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedLogs = this.filteredLogs.slice(startIndex, endIndex);
            this.showingStart = this.filteredLogs.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredLogs.length);
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
        }
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.modal-close-btn { backdrop-filter: blur(32px); }
.z-99999 { z-index: 99999; }
.z-999 { z-index: 999; }

/* Remove default browser select arrow on some browsers */
select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
</style>
@endsection