@props(['readings' => [], 'showActions' => true, 'showConsumption' => true, 'units' => []])

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="readingsTable()" x-init="init()">
    <!-- Table Header -->
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Water Meter Readings</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Track and manage water consumption readings per unit</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <!-- Status Filters -->
            <div class="hidden h-11 items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 lg:inline-flex dark:bg-gray-900">
                <button @click="filterStatus = 'all'; currentPage = 1" :class="filterStatus === 'all' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    All (<span x-text="statusCounts.all"></span>)
                </button>
                <button @click="filterStatus = 'read'; currentPage = 1" :class="filterStatus === 'read' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    Read (<span x-text="statusCounts.read"></span>)
                </button>
                <button @click="filterStatus = 'unread'; currentPage = 1" :class="filterStatus === 'unread' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                    Unread (<span x-text="statusCounts.unread"></span>)
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
                <button @click="openCreateReadingModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Record Reading
                </button>
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
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Unit</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Estate</th>
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Previous (m³)</th>
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Current (m³)</th>
                    @if($showConsumption)
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Consumption (m³)</th>
                    <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Charge (KES)</th>
                    @endif
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Last Reading Date</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Status</th>
                    @if($showActions)
                    <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                <template x-for="reading in paginatedReadings" :key="reading.id || reading.unit_id">
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="reading.unit_number"></span>
                            <span x-show="reading.billing_type === 'flat'" class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">Flat</span>
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
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="reading.last_reading_date ? formatDate(reading.last_reading_date) : 'Never'"></span>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            <span :class="getReadingStatusBadge(reading.needs_reading)" class="px-2 py-1 text-xs font-medium rounded-full">
                                <span x-text="reading.needs_reading ? 'Needs Reading' : 'Up to Date'"></span>
                            </span>
                        </td>
                        @if($showActions)
                        <td class="p-4 whitespace-nowrap">
                            <div class="flex gap-2">
                                <button @click="viewHistory(reading.unit_id, reading.unit_number)" class="text-blue-500 hover:text-blue-600 text-sm font-medium" title="View History">
                                    History
                                </button>
                                <button @click="openCreateReadingModal(reading.unit_id)" class="text-green-500 hover:text-green-600 text-sm font-medium" title="Record Reading">
                                    Record
                                </button>
                                <button @click="openUpdateReadingModal(reading.unit_id)" class="text-brand-500 hover:text-brand-600 text-sm font-medium" title="Update Reading">
                                    Update
                                </button>
                            </div>
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
        readings: [],
        searchQuery: '',
        filterStatus: 'all',
        sortBy: 'reading_date',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 10,
        loading: false,
        units: @json($units ?? []),
        
        init() {
            // Normalize incoming readings - handle both array and object formats
            let rawReadings = @json($readings);
            
            // CRITICAL DEBUG: Log what we received
            console.log('========== TABLE DEBUG ==========');
            console.log('Raw readings type:', Array.isArray(rawReadings) ? 'array' : typeof rawReadings);
            console.log('Raw readings count:', rawReadings.length);
            console.log('First 3 raw readings:', rawReadings.slice(0, 3));
            
            if (rawReadings.length === 0) {
                console.warn('No readings received!');
                this.readings = [];
                return;
            }
            
            // Process each reading to ensure consistent format
            this.readings = rawReadings.map((reading, index) => {
                // Debug first reading
                if (index === 0) {
                    console.log('Sample raw reading structure:', reading);
                    console.log('Reading keys:', Object.keys(reading));
                }
                
                // Handle if reading is an array (from controller mapping) or object (Eloquent model)
                const getValue = (key, defaultValue = null) => {
                    if (reading && typeof reading === 'object') {
                        // Try direct property first
                        if (reading[key] !== undefined) return reading[key];
                        // Try nested paths (for Eloquent models)
                        if (key.includes('.')) {
                            const parts = key.split('.');
                            let value = reading;
                            for (const part of parts) {
                                if (value && typeof value === 'object' && value[part] !== undefined) {
                                    value = value[part];
                                } else {
                                    return defaultValue;
                                }
                            }
                            return value;
                        }
                        return defaultValue;
                    }
                    return defaultValue;
                };
                
                // Extract unit number from various possible locations
                let unitNumber = getValue('unit_number');
                if (!unitNumber && reading.unit) {
                    unitNumber = reading.unit.unit_number;
                }
                if (!unitNumber) unitNumber = 'N/A';
                
                // Extract estate name from various possible locations
                let estateName = getValue('estate_name');
                if (!estateName && reading.unit && reading.unit.estate) {
                    estateName = reading.unit.estate.name;
                }
                if (!estateName && reading.estate) {
                    estateName = reading.estate.name || reading.estate;
                }
                if (!estateName) estateName = 'N/A';
                
                const previousReading = parseFloat(getValue('previous_reading', 0));
                let currentReading = parseFloat(getValue('current_reading', 0));
                
                // If current_reading is null/undefined, set to 0 for pending readings
                if (isNaN(currentReading)) currentReading = 0;
                
                let consumption = parseFloat(getValue('consumption', 0));
                if (isNaN(consumption)) consumption = 0;
                
                if (consumption === 0 && currentReading > 0) {
                    consumption = Math.max(0, currentReading - previousReading);
                }
                
                const billingType = getValue('water_billing_type', getValue('billing_type', 'consumption'));
                let charge = parseFloat(getValue('charge', 0));
                if (isNaN(charge)) charge = 0;
                
                if (billingType === 'flat') {
                    const waterCharge = parseFloat(getValue('water_charge', 0));
                    charge = isNaN(waterCharge) ? 0 : waterCharge;
                } else if (charge === 0 && consumption > 0) {
                    const rate = parseFloat(getValue('custom_water_rate', getValue('rate', 50)));
                    charge = consumption * (isNaN(rate) ? 50 : rate);
                }
                
                const lastReadingDate = getValue('last_reading_date') || getValue('reading_date');
                const needsReading = getValue('needs_reading', !lastReadingDate);
                
                const normalizedReading = {
                    id: getValue('id', null),
                    unit_id: getValue('unit_id', getValue('id', null)),
                    unit_number: unitNumber,
                    estate_name: estateName,
                    previous_reading: previousReading,
                    current_reading: currentReading,
                    consumption: consumption,
                    charge: charge,
                    last_reading_date: lastReadingDate,
                    reading_date: lastReadingDate,
                    needs_reading: needsReading,
                    billing_type: billingType,
                    water_billing_type: billingType,
                    water_charge: parseFloat(getValue('water_charge', 0)),
                    custom_water_rate: parseFloat(getValue('custom_water_rate', 0)),
                    rate: parseFloat(getValue('rate', 50)),
                    status: getValue('status', 'occupied'),
                    unit_type: getValue('unit_type', null),
                    recorded_by_name: getValue('recorded_by_name', null)
                };
                
                // Debug first normalized reading
                if (index === 0) {
                    console.log('Normalized reading:', normalizedReading);
                }
                
                return normalizedReading;
            });
            
            console.log('Final normalized readings count:', this.readings.length);
            console.log('Readings with needs_reading=true:', this.readings.filter(r => r.needs_reading).length);
            console.log('Readings with needs_reading=false:', this.readings.filter(r => !r.needs_reading).length);
            console.log('================================');
            
            // Force Alpine to update the view
            this.$nextTick(() => {
                console.log('Table render complete, displaying', this.filteredReadings.length, 'readings');
            });
        },
        
        // ... rest of your existing methods remain exactly the same ...
        get statusCounts() {
            const counts = { all: this.readings.length, read: 0, unread: 0 };
            this.readings.forEach(reading => {
                if (reading.needs_reading) {
                    counts.unread++;
                } else {
                    counts.read++;
                }
            });
            return counts;
        },
        
        get filteredReadings() {
            let filtered = this.readings;
            
            console.log('Filtering - Current filterStatus:', this.filterStatus);
            console.log('Total readings before filter:', filtered.length);
            console.log('Readings with needs_reading=true:', filtered.filter(r => r.needs_reading).length);
            console.log('Readings with needs_reading=false:', filtered.filter(r => !r.needs_reading).length);
            
            // Apply status filter
            if (this.filterStatus === 'read') {
                filtered = filtered.filter(reading => !reading.needs_reading);
                console.log('After "read" filter:', filtered.length);
            } else if (this.filterStatus === 'unread') {
                filtered = filtered.filter(reading => reading.needs_reading === true);
                console.log('After "unread" filter:', filtered.length);
            } else {
                console.log('Showing all readings');
            }
            
            // Apply search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(reading => 
                    (reading.unit_number && reading.unit_number.toLowerCase().includes(query)) ||
                    (reading.estate_name && reading.estate_name.toLowerCase().includes(query))
                );
                console.log('After search filter:', filtered.length);
            }
            
            // Apply sorting
            filtered = [...filtered].sort((a, b) => {
                let valA = a[this.sortBy];
                let valB = b[this.sortBy];
                
                if (this.sortBy === 'reading_date' || this.sortBy === 'last_reading_date') {
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
            
            console.log('Final filtered count:', filtered.length);
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
            if (consumption > 30) return 'high';
            if (consumption > 5) return 'normal';
            return 'low';
        },
        
        getConsumptionClass(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'text-red-600 dark:text-red-400 font-semibold';
            if (status === 'normal') return 'text-yellow-600 dark:text-yellow-400';
            return 'text-green-600 dark:text-green-400';
        },
        
        getReadingStatusBadge(needsReading) {
            if (needsReading) {
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            }
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
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
        
        openUpdateReadingModal(unitId) {
            if (window.openCreateReadingModal) {
                window.openCreateReadingModal(unitId);
            } else {
                alert('Modal system not available. Please refresh the page.');
            }
        },
        
        openCreateReadingModal(unitId = null) {
            if (window.openCreateReadingModal) {
                if (unitId) {
                    window.openCreateReadingModal(unitId);
                } else {
                    window.openCreateReadingModal();
                }
            } else {
                alert('Modal system not available. Please refresh the page.');
            }
        },
        
        viewHistory(unitId, unitNumber) {
            window.location.href = `/water/unit/${unitId}/statement`;
        },
        
        exportData() {
            const headers = ['Unit', 'Estate', 'Previous (m³)', 'Current (m³)', 'Consumption (m³)', 'Charge (KES)', 'Last Reading Date', 'Status'];
            const rows = this.filteredReadings.map(r => [
                r.unit_number,
                r.estate_name,
                this.formatNumber(r.previous_reading),
                this.formatNumber(r.current_reading),
                this.formatNumber(r.consumption),
                this.formatNumber(r.charge),
                this.formatDate(r.last_reading_date),
                r.needs_reading ? 'Needs Reading' : 'Up to Date'
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

<!-- Include the separate Water Reading Modal -->
@include('partials.modal.modal-create-reading')