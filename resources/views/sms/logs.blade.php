@extends('layouts.app')

@section('title', 'SMS Logs')

@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

@section('content')
<div class="container mx-auto px-4 py-6" x-data="smsLogsTable()" x-init="init()">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pb-3 pt-4 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6">
        <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">SMS Logs</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                    <span x-text="filteredLogs.length"></span> logs
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
                
                <div class="relative flex-1 min-w-[200px]">
                    <input 
                        type="text" 
                        x-model="searchTerm"
                        @input.debounce.300ms="filterLogs()"
                        id="logSearch" 
                        placeholder="Search by phone or message..." 
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 pl-10"
                    >
                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Filter Button -->
                <button 
                    @click="openFilters()"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-medium text-gray-500 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filters
                    <span x-show="activeFiltersCount > 0" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-blue-500 text-white" x-text="activeFiltersCount"></span>
                </button>

                <a href="{{ route('sms.logs.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg bg-green-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
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
             class="mb-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
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
        <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4 my-4">
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
            <table class="min-w-full">
                <thead>
                    <tr class="border-gray-100 border-y dark:border-gray-800">
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('id')">
                            <div class="flex items-center gap-1">
                                <span>ID</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'id' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'id' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'id'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('recipient_phone')">
                            <div class="flex items-center gap-1">
                                <span>Phone</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'recipient_phone' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'recipient_phone' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'recipient_phone'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('status')">
                            <div class="flex items-center gap-1">
                                <span>Status</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path x-show="sortColumn === 'status' && sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    <path x-show="sortColumn === 'status' && sortDirection === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    <path x-show="sortColumn !== 'status'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider ID</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failure Reason</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('created_at')">
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
                
                <tbody>
                    <template x-for="log in paginatedLogs" :key="log.id">
                        <tr class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90" x-text="log.id"></td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-400" x-text="log.recipient_phone"></td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                <div x-text="log.message && log.message.length > 50 ? log.message.substring(0, 50) + '...' : (log.message || '')"></div>
                            </td>
                            <td class="px-4 py-3">
                                <span x-show="log.status === 'sent' || log.status === 'delivered'" class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Delivered</span>
                                <span x-show="log.status === 'pending'" class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                <span x-show="log.status === 'failed'" class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                                <span x-show="log.status !== 'sent' && log.status !== 'delivered' && log.status !== 'pending' && log.status !== 'failed'" class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800" x-text="log.status"></span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 font-mono" x-text="log.provider_message_id || '-'"></td>
                            <td class="px-4 py-3 text-xs text-gray-500" x-text="log.failure_reason ? (log.failure_reason.length > 30 ? log.failure_reason.substring(0, 30) + '...' : log.failure_reason) : '-'"></td>
                            <td class="px-4 py-3 text-sm text-gray-500" x-text="log.created_at ? new Date(log.created_at).toLocaleString() : '-'"></td>
                        </tr>
                    </template>
                    
                    <!-- Empty State -->
                    <tr x-show="filteredLogs.length === 0">
                        <td colspan="7" class="px-4 py-12 text-center">
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
            
            <!-- Pagination -->
            <div class="flex flex-col items-center justify-between px-2 py-4 sm:flex-row sm:px-0" x-show="filteredLogs.length > 0">
                <div class="hidden sm:flex">
                    <p class="text-sm text-gray-700 dark:text-gray-400">
                        Showing <span x-text="showingStart"></span> to <span x-text="showingEnd"></span> of 
                        <span x-text="filteredLogs.length"></span> results
                    </p>
                </div>
                <div class="flex-1 flex justify-between sm:justify-end items-center gap-2">
                    <button 
                        @click="prevPage()" 
                        :disabled="currentPage === 1"
                        class="relative inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="{'cursor-not-allowed opacity-50': currentPage === 1}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    
                    <div class="flex items-center gap-1">
                        <template x-for="page in visiblePages" :key="page">
                            <button 
                                @click="goToPage(page)"
                                :class="{
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors': true,
                                    'bg-blue-600 text-white': currentPage === page,
                                    'text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800': currentPage !== page && page !== '...',
                                    'cursor-default': page === '...'
                                }"
                                x-text="page"
                            ></button>
                        </template>
                    </div>
                    
                    <button 
                        @click="nextPage()" 
                        :disabled="currentPage === totalPages"
                        class="relative inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="{'cursor-not-allowed opacity-50': currentPage === totalPages}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
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