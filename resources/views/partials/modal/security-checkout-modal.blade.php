<!-- resources/views/partials/modal/security-checkout-modal.blade.php -->
<div x-data="securityCheckOutModal" x-init="init()" x-cloak>
    <!-- Modal Overlay -->
    <div 
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-hidden"
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

        <!-- Slide-in Panel from Right -->
        <div 
            class="fixed right-0 top-0 h-full w-full max-w-2xl bg-white dark:bg-gray-900 shadow-2xl"
            x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <!-- Modal Header -->
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                        <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🚪 Check Out</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Search for someone currently in the estate</p>
                    </div>
                </div>
                <button @click="closeModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="h-[calc(100%-73px)] overflow-y-auto p-6">
                <!-- Search Section -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                    <h4 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">🔍 Find Person to Check Out</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search By</label>
                            <select x-model="searchBy" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="name">Name</option>
                                <option value="unit">Unit Number</option>
                                <option value="phone">Phone Number</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search Value</label>
                            <div class="flex gap-2">
                                <input 
                                    type="text" 
                                    x-model="searchValue"
                                    placeholder="Enter value to search..."
                                    @keyup.enter="searchLogs()"
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                                <button 
                                    @click="searchLogs()"
                                    :disabled="!searchValue || searching"
                                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    <span x-show="!searching">Search</span>
                                    <span x-show="searching" class="flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Searching...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Section -->
                <div x-show="results.length > 0" class="space-y-3">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Found <span x-text="results.length"></span> person(s) currently in the estate
                    </h4>
                    
                    <template x-for="log in results" :key="log.id">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/80">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="log.person_name"></span>
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                            ● IN
                                        </span>
                                    </div>
                                    <div class="mt-1 grid grid-cols-2 gap-1 text-sm text-gray-600 dark:text-gray-400 sm:grid-cols-4">
                                        <span><strong>Unit:</strong> <span x-text="log.unit_number"></span></span>
                                        <span><strong>Phone:</strong> <span x-text="log.visitor_phone || 'N/A'"></span></span>
                                        <span><strong>Check-in:</strong> <span x-text="log.access_time_formatted"></span></span>
                                        <span><strong>Duration:</strong> <span x-text="log.duration"></span></span>
                                    </div>
                                    <div x-show="log.vehicle" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        <span><strong>Vehicle:</strong> <span x-text="log.vehicle"></span></span>
                                    </div>
                                    <div x-show="log.purpose" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        <span><strong>Purpose:</strong> <span x-text="log.purpose"></span></span>
                                    </div>
                                </div>
                                <div class="mt-3 sm:mt-0">
                                    <button 
                                        @click="checkOut(log.id)"
                                        :disabled="checkingOut"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                    >
                                        <span x-show="!checkingOut">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
                                            </svg>
                                        </span>
                                        <span x-show="checkingOut" class="flex items-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                        <span x-show="!checkingOut">Check Out</span>
                                        <span x-show="checkingOut">Processing...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- No Results -->
                <div x-show="searched && results.length === 0" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">No results found</p>
                            <p class="text-sm text-yellow-700 dark:text-yellow-400">No one is currently checked in with that search criteria.</p>
                        </div>
                    </div>
                </div>

                <!-- Initial Message -->
                <div x-show="!searched && results.length === 0" class="text-center py-8">
                    <svg class="h-16 w-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">Search for someone to check out</p>
                    <p class="text-xs text-gray-400">Search by name, unit number, or phone</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', function() {
    Alpine.data('securityCheckOutModal', function() {
        return {
            open: false,
            searching: false,
            checkingOut: false,
            searched: false,
            searchBy: 'name',
            searchValue: '',
            results: [],
            
            init() {
                window.securityCheckOutModal = this;
                console.log('✅ Check Out Modal initialized');
            },
            
            openModal() {
                console.log('✅ Opening Check Out Modal');
                this.open = true;
                this.resetForm();
            },
            
            closeModal() {
                console.log('✅ Closing Check Out Modal');
                this.open = false;
                this.resetForm();
            },
            
            resetForm() {
                this.searchValue = '';
                this.results = [];
                this.searched = false;
                this.searching = false;
                this.checkingOut = false;
            },
            
            async searchLogs() {
                if (!this.searchValue) return;
                
                this.searching = true;
                this.searched = true;
                
                try {
                    // Search for logs where status is 'approved' and exit_time is null (still IN)
                    const response = await fetch(`/security/checkout/search?search_by=${this.searchBy}&search_value=${this.searchValue}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        this.results = data.logs;
                        console.log('✅ Search results:', this.results.length);
                    } else {
                        this.results = [];
                        alert(data.message || 'Error searching');
                    }
                } catch (error) {
                    console.error('❌ Search error:', error);
                    this.results = [];
                    alert('Error searching. Please try again.');
                } finally {
                    this.searching = false;
                }
            },
            
            async checkOut(logId) {
                if (!confirm('Confirm check out for this person?')) return;
                
                this.checkingOut = true;
                
                try {
                    const response = await fetch(`/security/checkout/${logId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('✅ Person checked out successfully!');
                        this.closeModal();
                        window.location.reload();
                    } else {
                        alert('❌ Error: ' + (data.message || 'Failed to check out'));
                    }
                } catch (error) {
                    console.error('❌ Check out error:', error);
                    alert('❌ Error checking out. Please try again.');
                } finally {
                    this.checkingOut = false;
                }
            }
        };
    });
});

// Make it globally available
window.securityCheckOutModal = null;
</script>

<style>
[x-cloak] { display: none !important; }
</style>