<!-- resources/views/partials/modal/security-currently-in-modal.blade.php -->
<div x-data="securityCurrentlyInModal" x-init="init()" x-cloak>
    <!-- Modal Overlay -->
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
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="closeModal()"></div>

        <!-- Modal Container - Centered with Scroll -->
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
                <!-- Modal Header - Sticky -->
                <div class="sticky top-0 z-20 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900 rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">📊 Currently IN the Estate</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="totalPeople"></span> people currently in the estate
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
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

                <!-- Modal Body - Scrollable -->
                <div class="max-h-[calc(100vh-200px)] overflow-y-auto p-6">
                    <!-- Loading -->
                    <div x-show="loading" class="flex items-center justify-center py-12">
                        <svg class="h-8 w-8 animate-spin text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="ml-3 text-gray-600">Loading...</span>
                    </div>

                    <!-- No Results -->
                    <div x-show="!loading && people.length === 0" class="text-center py-12">
                        <svg class="h-16 w-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No one is currently in the estate</p>
                        <p class="text-xs text-gray-400">Check back later</p>
                    </div>

                    <!-- People List -->
                    <div x-show="!loading && people.length > 0">
                        <!-- Stats Cards -->
                        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-sm border border-stroke bg-white p-3 shadow-default dark:border-strokedark dark:bg-boxdark">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-black dark:text-white">Total</span>
                                    <span class="text-xl font-bold text-primary" x-text="people.length"></span>
                                </div>
                            </div>
                            <div class="rounded-sm border border-stroke bg-white p-3 shadow-default dark:border-strokedark dark:bg-boxdark">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-black dark:text-white">Residents</span>
                                    <span class="text-xl font-bold text-success" x-text="residentsCount"></span>
                                </div>
                            </div>
                            <div class="rounded-sm border border-stroke bg-white p-3 shadow-default dark:border-strokedark dark:bg-boxdark">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-black dark:text-white">Visitors</span>
                                    <span class="text-xl font-bold text-warning" x-text="visitorsCount"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Search & Filter -->
                        <div class="mb-4 flex flex-wrap items-center gap-3 sticky top-0 bg-white dark:bg-gray-900 py-2 z-10">
                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input 
                                    type="text" 
                                    x-model="searchTerm"
                                    @input.debounce.300ms="filterPeople()"
                                    placeholder="Search by name, unit, or phone..." 
                                    class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                            </div>
                            <select 
                                x-model="filterUnit"
                                @change="filterPeople()"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="">All Units</option>
                                <template x-for="unit in uniqueUnits" :key="unit">
                                    <option :value="unit" x-text="unit"></option>
                                </template>
                            </select>
                            <span class="text-sm text-gray-500 whitespace-nowrap">
                                Showing <span x-text="filteredPeople.length"></span> of <span x-text="people.length"></span>
                            </span>
                        </div>

                        <!-- Table with Scrollable Body -->
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full table-auto">
                                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Person</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Unit</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Phone</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Checked In</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Duration</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Purpose</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <template x-for="(person, index) in filteredPeople" :key="person.id">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                            <td class="px-4 py-3 text-sm text-gray-500" x-text="index + 1"></td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="person.person_name"></span>
                                                    <span x-show="!person.is_tenant && person.visiting" class="text-xs text-gray-500 dark:text-gray-400">
                                                        Visiting: <span x-text="person.visiting"></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span x-show="person.is_tenant" class="inline-flex rounded-full bg-success/10 px-3 py-1 text-xs font-medium text-success">
                                                    Resident
                                                </span>
                                                <span x-show="!person.is_tenant" class="inline-flex rounded-full bg-warning/10 px-3 py-1 text-xs font-medium text-warning">
                                                    Visitor
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400" x-text="person.unit_number"></span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-text="person.visitor_phone || '-'"></td>
                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-text="person.access_time_formatted"></td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400" x-text="person.duration"></span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400" x-text="person.purpose || 'N/A'"></td>
                                            <td class="px-4 py-3 text-center">
                                                <button 
                                                    x-show="!person.is_tenant"
                                                    @click="checkOutPerson(person.id)"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                                                >
                                                    Check Out
                                                </button>
                                                <span x-show="person.is_tenant" class="text-xs text-gray-400">Auto</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Scroll Indicator -->
                        <div x-show="filteredPeople.length > 10" class="mt-2 text-center text-xs text-gray-400">
                            <span>↓ Scroll to see more people ↓</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }

/* Custom scrollbar styling for the modal body */
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

/* Dark mode scrollbar */
.dark .max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-track {
    background: #1f1f1f;
}

.dark .max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-thumb {
    background: #404040;
}

.dark .max-h-\[calc\(100vh-200px\)\]::-webkit-scrollbar-thumb:hover {
    background: #555555;
}

/* Sticky table header */
.sticky {
    position: sticky;
}
</style>

<script>
document.addEventListener('alpine:init', function() {
    Alpine.data('securityCurrentlyInModal', function() {
        return {
            open: false,
            loading: false,
            people: [],
            filteredPeople: [],
            searchTerm: '',
            filterUnit: '',
            residentsCount: 0,
            visitorsCount: 0,
            
            init() {
                window.securityCurrentlyInModal = this;
                console.log('✅ Currently IN Modal initialized');
            },
            
            openModal() {
                console.log('✅ Opening Currently IN Modal');
                this.open = true;
                document.body.style.overflow = 'hidden';
                this.loadData();
            },
            
            closeModal() {
                console.log('✅ Closing Currently IN Modal');
                this.open = false;
                document.body.style.overflow = 'auto';
                this.people = [];
                this.filteredPeople = [];
            },
            
            get totalPeople() {
                return this.people.length;
            },
            
            get uniqueUnits() {
                const units = this.people.map(p => p.unit_number).filter(Boolean);
                return [...new Set(units)].sort();
            },
            
            async loadData() {
                this.loading = true;
                
                try {
                    console.log('🔄 Loading currently in data...');
                    const response = await fetch('/security/currently-in', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    console.log('📦 Response:', data);
                    
                    if (data.success) {
                        this.people = data.people || [];
                        this.residentsCount = data.residents_count || 0;
                        this.visitorsCount = data.visitors_count || 0;
                        this.filterPeople();
                        console.log('✅ Loaded', this.people.length, 'people');
                    } else {
                        console.error('❌ Error from server:', data.message);
                        alert('Error loading data: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('❌ Error loading currently in:', error);
                    alert('Error loading data. Please check the console for details.');
                } finally {
                    this.loading = false;
                }
            },
            
            filterPeople() {
                if (!this.searchTerm && !this.filterUnit) {
                    this.filteredPeople = this.people;
                    return;
                }
                
                const term = this.searchTerm.toLowerCase();
                this.filteredPeople = this.people.filter(person => {
                    const matchesSearch = !term || 
                        (person.person_name && person.person_name.toLowerCase().includes(term)) ||
                        (person.unit_number && person.unit_number.toLowerCase().includes(term)) ||
                        (person.visitor_phone && person.visitor_phone.includes(term));
                    
                    const matchesUnit = !this.filterUnit || person.unit_number === this.filterUnit;
                    
                    return matchesSearch && matchesUnit;
                });
            },
            
            async checkOutPerson(id) {
                if (!id) {
                    alert('❌ Error: Invalid ID provided');
                    return;
                }
                
                if (!confirm('Check out this person?')) return;
                
                try {
                    const response = await fetch(`/security/checkout/${id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    
                    const data = await response.json();
                    console.log('📥 Checkout response:', data);
                    
                    if (data.success) {
                        alert('✅ Person checked out successfully!');
                        this.loadData(); // Refresh the list
                    } else {
                        alert('❌ Error: ' + (data.message || 'Failed to check out'));
                    }
                } catch (error) {
                    console.error('❌ Check out error:', error);
                    alert('❌ Error checking out. Please try again.');
                }
            }
        };
    });
});

window.securityCurrentlyInModal = null;
</script>