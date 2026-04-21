@props(['readings' => [], 'showActions' => true, 'showConsumption' => true])

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="readingsTable()" x-init="init()">
    <!-- Table Header -->
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Water Meter Readings</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Track and manage water consumption readings</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <!-- Status/Consumption Filters -->
            <div class="hidden h-11 items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 lg:inline-flex dark:bg-gray-900">
                <button @click="filterStatus = 'all'; currentPage = 1" :class="filterStatus === 'all' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    All (<span x-text="statusCounts.all"></span>)
                </button>
                <button @click="filterStatus = 'high'; currentPage = 1" :class="filterStatus === 'high' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    High (<span x-text="statusCounts.high"></span>)
                </button>
                <button @click="filterStatus = 'normal'; currentPage = 1" :class="filterStatus === 'normal' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    Normal (<span x-text="statusCounts.normal"></span>)
                </button>
                <button @click="filterStatus = 'low'; currentPage = 1" :class="filterStatus === 'low' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    Low (<span x-text="statusCounts.low"></span>)
                </button>
            </div>

            <!-- Search -->
            <div class="relative">
                <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                    <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/>
                    </svg>
                </span>
                <input type="text" placeholder="Search by unit or estate..." x-model="searchQuery" @input="currentPage = 1" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"/>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex gap-2">
                <!-- Record Reading Button -->
                <button @click="openCreateReadingModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Record Reading
                </button>
                
                <!-- Export Button -->
                <button @click="exportData()" class="border border-gray-300 shadow-theme-xs hover:bg-gray-50 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 transition dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                    <svg class="fill-current" width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path d="M10 1.66667V12.5M10 12.5L13.3333 9.16667M10 12.5L6.66667 9.16667M4.16667 15H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4.16667 18.3333H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Export
                </button>
            </div>
        </div>
    </div>
    
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-500"></div>
        <span class="ml-3 text-gray-500">Loading readings...</span>
    </div>
    
    <!-- Table Content -->
    <div x-show="!loading" class="custom-scrollbar overflow-x-auto">
        <table class="w-full table-auto">
            <thead>
                <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">
                        Unit
                    </th>
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">
                        Estate
                    </th>
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400 cursor-pointer hover:text-gray-900" @click="sort('previous_reading')">
                        <div class="flex items-center justify-end gap-2">
                            <span>Previous (m³)</span>
                            <span class="flex flex-col gap-0.5">
                                <svg :class="sortBy === 'previous_reading' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                                <svg :class="sortBy === 'previous_reading' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                            </span>
                        </div>
                    </th>
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400 cursor-pointer hover:text-gray-900" @click="sort('current_reading')">
                        <div class="flex items-center justify-end gap-2">
                            <span>Current (m³)</span>
                            <span class="flex flex-col gap-0.5">
                                <svg :class="sortBy === 'current_reading' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                                <svg :class="sortBy === 'current_reading' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                            </span>
                        </div>
                    </th>
                    @if($showConsumption)
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400 cursor-pointer hover:text-gray-900" @click="sort('consumption')">
                        <div class="flex items-center justify-end gap-2">
                            <span>Consumption (m³)</span>
                            <span class="flex flex-col gap-0.5">
                                <svg :class="sortBy === 'consumption' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                                <svg :class="sortBy === 'consumption' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                            </span>
                        </div>
                    </th>
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">
                        Charge (KES)
                    </th>
                    @endif
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400 cursor-pointer hover:text-gray-900" @click="sort('reading_date')">
                        <div class="flex items-center gap-2">
                            <span>Reading Date</span>
                            <span class="flex flex-col gap-0.5">
                                <svg :class="sortBy === 'reading_date' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                                <svg :class="sortBy === 'reading_date' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                            </span>
                        </div>
                    </th>
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Status</th>
                    @if($showActions)
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                <template x-for="reading in paginatedReadings" :key="reading.unit_id">
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="reading.unit_number"></span>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="reading.estate_name"></span>
                        </td>
                        <td class="p-4 whitespace-nowrap text-right">
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="formatNumber(reading.previous_reading)"></span>
                        </td>
                        <td class="p-4 whitespace-nowrap text-right">
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatNumber(reading.current_reading)"></span>
                        </td>
                        @if($showConsumption)
                        <td class="p-4 whitespace-nowrap text-right">
                            <span :class="getConsumptionClass(reading.consumption)" class="text-sm font-medium" x-text="formatNumber(reading.consumption)"></span>
                        </td>
                        <td class="p-4 whitespace-nowrap text-right">
                            <span class="text-sm font-medium text-blue-600 dark:text-blue-400">KES <span x-text="formatNumber(reading.charge)"></span></span>
                        </td>
                        @endif
                        <td class="p-4 whitespace-nowrap">
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="formatDate(reading.reading_date)"></span>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            <span :class="getStatusBadgeClass(reading.consumption)" class="px-2 py-1 text-xs font-medium rounded-full">
                                <span x-text="getStatusText(reading.consumption)"></span>
                            </span>
                        </td>
                        @if($showActions)
                        <td class="p-4 whitespace-nowrap">
                            <button @click="openReadingModal(reading.unit_id)" class="text-brand-500 hover:text-brand-600 text-sm font-medium">
                                Update Reading
                            </button>
                        </td>
                        @endif
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    
    <!-- Empty State -->
    <div x-show="!loading && filteredReadings.length === 0" class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No readings found</h3>
        <p class="mt-1 text-sm text-gray-500">No water meter readings match your criteria.</p>
    </div>
    
    <!-- Pagination -->
    <div x-show="!loading && filteredReadings.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 sm:flex-row dark:border-gray-800">
        <div class="pb-3 sm:pb-0">
            <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
                Showing <span x-text="((currentPage - 1) * itemsPerPage) + (paginatedReadings.length ? 1 : 0)"></span>
                to <span x-text="((currentPage - 1) * itemsPerPage) + paginatedReadings.length"></span>
                of <span x-text="filteredReadings.length"></span>
            </span>
        </div>
        <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
            <button class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200" @click="previousPage" :disabled="currentPage === 1">
                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/>
                </svg>
            </button>
            <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
            <ul class="hidden items-center gap-0.5 sm:flex">
                <template x-for="page in visiblePages" :key="page">
                    <li><a href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a></li>
                </template>
            </ul>
            <button class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200" @click="nextPage" :disabled="currentPage === totalPages">
                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('readingsTable', () => ({
        readings: @json($readings),
        searchQuery: '',
        filterStatus: 'all',
        sortBy: 'reading_date',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 10,
        loading: false,
        
        init() {
            // Process readings to add computed fields
            this.readings = this.readings.map(reading => {
                const consumption = (reading.current_reading || 0) - (reading.previous_reading || 0);
                const rate = reading.rate || 50;
                return {
                    ...reading,
                    consumption: Math.max(0, consumption),
                    charge: Math.max(0, consumption) * rate
                };
            });
        },
        
        get statusCounts() {
            const counts = { all: this.readings.length, high: 0, normal: 0, low: 0 };
            this.readings.forEach(reading => {
                const status = this.getConsumptionStatus(reading.consumption);
                if (status === 'high') counts.high++;
                else if (status === 'normal') counts.normal++;
                else counts.low++;
            });
            return counts;
        },
        
        get filteredReadings() {
            let filtered = this.readings;
            
            // Apply status filter
            if (this.filterStatus !== 'all') {
                filtered = filtered.filter(reading => 
                    this.getConsumptionStatus(reading.consumption) === this.filterStatus
                );
            }
            
            // Apply search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(reading => 
                    (reading.unit_number && reading.unit_number.toLowerCase().includes(query)) ||
                    (reading.estate_name && reading.estate_name.toLowerCase().includes(query))
                );
            }
            
            // Apply sorting
            filtered = [...filtered].sort((a, b) => {
                let valA = a[this.sortBy];
                let valB = b[this.sortBy];
                
                if (this.sortBy === 'reading_date') {
                    valA = valA ? new Date(valA).getTime() : 0;
                    valB = valB ? new Date(valB).getTime() : 0;
                }
                
                if (typeof valA === 'string') {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }
                
                if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
            
            return filtered;
        },
        
        get paginatedReadings() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredReadings.slice(start, start + this.itemsPerPage);
        },
        
        get totalPages() {
            return Math.ceil(this.filteredReadings.length / this.itemsPerPage);
        },
        
        get visiblePages() {
            const pages = [];
            const maxVisible = 5;
            let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
            let end = Math.min(this.totalPages, start + maxVisible - 1);
            if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
        
        getConsumptionStatus(consumption) {
            if (consumption > 20) return 'high';
            if (consumption > 0) return 'normal';
            return 'low';
        },
        
        getConsumptionClass(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'text-red-600 dark:text-red-400';
            if (status === 'normal') return 'text-yellow-600 dark:text-yellow-400';
            return 'text-green-600 dark:text-green-400';
        },
        
        getStatusBadgeClass(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            if (status === 'normal') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        },
        
        getStatusText(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'High Usage';
            if (status === 'normal') return 'Normal Usage';
            return 'Low Usage';
        },
        
        formatNumber(value) {
            if (value === undefined || value === null) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        sort(field) {
            if (this.sortBy === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = field;
                this.sortDirection = 'asc';
            }
        },
        
        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) this.currentPage = page;
        },
        
        nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
        previousPage() { if (this.currentPage > 1) this.currentPage--; },
        
        openReadingModal(unitId) {
            if (unitId && window.waterReadingModal) {
                window.waterReadingModal.openModal(unitId);
            }
        },
        
        openCreateReadingModal() {
            // This opens a modal to select a unit first, or directly opens the water reading modal
            // For now, we'll open a prompt or a selection modal
            // You can enhance this to show a unit selector dropdown
            
            // Simple approach: Ask for unit ID (you can replace with a proper unit selector modal)
            const unitId = prompt('Enter Unit ID to record reading:');
            if (unitId && !isNaN(unitId)) {
                if (window.waterReadingModal) {
                    window.waterReadingModal.openModal(parseInt(unitId));
                } else {
                    alert('Water reading modal not available');
                }
            } else if (unitId) {
                alert('Please enter a valid Unit ID');
            }
        },
        
        exportData() {
            // Simple CSV export
            const headers = ['Unit', 'Estate', 'Previous (m³)', 'Current (m³)', 'Consumption (m³)', 'Charge (KES)', 'Reading Date', 'Status'];
            const rows = this.filteredReadings.map(r => [
                r.unit_number,
                r.estate_name,
                this.formatNumber(r.previous_reading),
                this.formatNumber(r.current_reading),
                this.formatNumber(r.consumption),
                this.formatNumber(r.charge),
                this.formatDate(r.reading_date),
                this.getStatusText(r.consumption)
            ]);
            
            const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `water-readings-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
    }));
});
</script>

<!-- Include Water Reading Modal -->
@include('partials.modal.water-reading-create-modal')