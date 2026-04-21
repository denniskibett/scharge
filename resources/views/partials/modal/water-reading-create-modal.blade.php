<!-- RECORD WATER READING MODAL - Unit Selection + Reading Entry -->
<div x-data="recordWaterReadingModal" x-init="init()">
    <!-- Backdrop with 50% opacity and frost effect -->
    <template x-if="isOpen">
        <div 
            @click="closeModal()"
            class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
    </template>

    <!-- Modal Content - Slides from Right -->
    <div x-show="isOpen" 
         x-transition:enter="transition transform ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         x-cloak
         class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white dark:bg-gray-900 shadow-2xl z-99999 overflow-y-auto">
        <div class="p-6 lg:p-10">
            <!-- Close Button -->
            <button
                @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg class="transition-colors fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="submitReading">
                @csrf
                <h4 class="mb-2 text-lg font-medium text-gray-800 dark:text-white/90">
                    Record Water Meter Reading
                </h4>
                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                    Select a unit and enter the current water meter reading
                </p>

                <!-- Form Errors -->
                <template x-if="formErrors.length > 0">
                    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc pl-5">
                            <template x-for="error in formErrors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <!-- Step 1: Unit Selection -->
                <div x-show="!selectedUnit" class="space-y-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Select Unit *
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                x-model="unitSearch"
                                @input="filterUnits"
                                placeholder="Search by unit number or estate..."
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            />
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Units List -->
                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-96 overflow-y-auto dark:border-gray-700">
                        <template x-for="unit in filteredUnits" :key="unit.id">
                            <div 
                                @click="selectUnit(unit)"
                                class="p-4 hover:bg-gray-50 cursor-pointer transition-colors dark:hover:bg-gray-800"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-800 dark:text-white/90" x-text="unit.unit_number"></p>
                                        <p class="text-sm text-gray-500" x-text="unit.estate_name"></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400">Billing Type</span>
                                        <p class="text-sm font-medium" :class="unit.water_billing_type === 'consumption' ? 'text-blue-600' : 'text-green-600'">
                                            <span x-text="unit.water_billing_type === 'consumption' ? 'Consumption-based' : 'Flat Rate'"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-xs text-gray-500">
                                    <div>
                                        <span>Current Reading:</span>
                                        <span class="ml-1 font-medium text-gray-700" x-text="formatNumber(unit.current_water_reading) + ' m³'"></span>
                                    </div>
                                    <div>
                                        <span>Last Reading:</span>
                                        <span class="ml-1 font-medium text-gray-700" x-text="unit.last_reading_date ? formatDate(unit.last_reading_date) : 'Never'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <div x-show="filteredUnits.length === 0 && !loadingUnits" class="p-8 text-center text-gray-500">
                            No units found matching your search.
                        </div>
                        
                        <div x-show="loadingUnits" class="p-8 text-center text-gray-500">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-brand-500 mx-auto"></div>
                            <p class="mt-2">Loading units...</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Reading Entry -->
                <div x-show="selectedUnit" class="space-y-6">
                    <!-- Selected Unit Info -->
                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Selected Unit</p>
                                <p class="text-lg font-semibold text-gray-800 dark:text-white" x-text="selectedUnit.unit_number"></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400" x-text="selectedUnit.estate_name"></p>
                            </div>
                            <button 
                                type="button"
                                @click="selectedUnit = null"
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                            >
                                Change Unit
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Previous Reading:</span>
                                <span class="ml-2 font-medium" x-text="formatNumber(selectedUnit.previous_water_reading) + ' m³'"></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Current Reading:</span>
                                <span class="ml-2 font-medium" x-text="formatNumber(selectedUnit.current_water_reading) + ' m³'"></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Last Reading Date:</span>
                                <span class="ml-2 font-medium" x-text="selectedUnit.last_reading_date ? formatDate(selectedUnit.last_reading_date) : 'Never'"></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Billing Type:</span>
                                <span class="ml-2 font-medium" :class="selectedUnit.water_billing_type === 'consumption' ? 'text-blue-600' : 'text-green-600'" 
                                      x-text="selectedUnit.water_billing_type === 'consumption' ? 'Consumption-based' : 'Flat Rate'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Current Reading Input -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Current Reading (m³) *
                        </label>
                        <input 
                            type="number" 
                            step="0.01"
                            x-model="readingData.current_reading"
                            @input="calculateConsumption"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            placeholder="Enter current meter reading"
                        />
                    </div>

                    <!-- Consumption (Auto-calculated) -->
                    <div x-show="selectedUnit.water_billing_type === 'consumption'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Consumption (m³)
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                step="0.01"
                                x-model="readingData.consumption"
                                readonly
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm text-gray-600 shadow-theme-xs cursor-not-allowed dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                            />
                        </div>
                        <p x-show="readingData.consumption < 0" class="mt-1 text-xs text-red-500">
                            ⚠️ Warning: Current reading is less than previous reading!
                        </p>
                    </div>

                    <!-- Estimated Charge -->
                    <div x-show="selectedUnit.water_billing_type === 'consumption'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Estimated Charge (KES)
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                step="0.01"
                                x-model="readingData.estimated_charge"
                                readonly
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700 shadow-theme-xs cursor-not-allowed dark:border-gray-700 dark:bg-green-900/20 dark:text-green-400"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Water rate: KES <span x-text="formatNumber(selectedUnit.water_rate)"></span> per m³
                        </p>
                    </div>

                    <!-- Reading Date -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Reading Date *
                        </label>
                        <input 
                            type="date"
                            x-model="readingData.reading_date"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Notes (Optional)
                        </label>
                        <textarea 
                            x-model="readingData.notes"
                            rows="3"
                            class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            placeholder="Any issues or notes about this reading..."
                        ></textarea>
                    </div>

                    <!-- Summary -->
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                        <h5 class="font-medium text-gray-800 dark:text-white/90 mb-2">Summary</h5>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Unit:</span>
                                <span class="ml-2 font-medium" x-text="selectedUnit.unit_number"></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Reading Date:</span>
                                <span class="ml-2 font-medium" x-text="readingData.reading_date"></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Previous Reading:</span>
                                <span class="ml-2 font-medium" x-text="formatNumber(selectedUnit.previous_water_reading) + ' m³'"></span>
                            </div>
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Current Reading:</span>
                                <span class="ml-2 font-medium" x-text="formatNumber(readingData.current_reading) + ' m³'"></span>
                            </div>
                            <div x-show="selectedUnit.water_billing_type === 'consumption'">
                                <span class="text-gray-600 dark:text-gray-400">Consumption:</span>
                                <span class="ml-2 font-medium" x-text="formatNumber(readingData.consumption) + ' m³'"></span>
                            </div>
                            <div x-show="selectedUnit.water_billing_type === 'consumption'">
                                <span class="text-gray-600 dark:text-gray-400">Estimated Charge:</span>
                                <span class="ml-2 font-medium text-green-600">KES <span x-text="formatNumber(readingData.estimated_charge)"></span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="closeModal()"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting || (selectedUnit && (!readingData.current_reading || !readingData.reading_date))"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!isSubmitting">Submit Reading</span>
                        <span x-show="isSubmitting">
                            <svg class="animate-spin h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('recordWaterReadingModal', () => ({
        isOpen: false,
        isSubmitting: false,
        loadingUnits: false,
        formErrors: [],
        
        // Units data
        allUnits: [],
        filteredUnits: [],
        unitSearch: '',
        selectedUnit: null,
        
        // Reading data
        readingData: {
            current_reading: '',
            consumption: 0,
            estimated_charge: 0,
            reading_date: '',
            notes: ''
        },
        
        init() {
            // Set default reading date to today
            this.readingData.reading_date = new Date().toISOString().split('T')[0];
            window.recordWaterReadingModal = this;
            console.log('Record Water Reading Modal initialized');
        },
        
        openModal() {
            console.log('Opening Record Water Reading Modal');
            this.isOpen = true;
            this.resetForm();
            this.fetchUnits();
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.isOpen = false;
            this.formErrors = [];
            this.isSubmitting = false;
            document.body.style.overflow = '';
        },
        
        resetForm() {
            this.selectedUnit = null;
            this.unitSearch = '';
            this.readingData = {
                current_reading: '',
                consumption: 0,
                estimated_charge: 0,
                reading_date: new Date().toISOString().split('T')[0],
                notes: ''
            };
            this.formErrors = [];
        },
        
        async fetchUnits() {
            this.loadingUnits = true;
            
            try {
                // Fetch units from the API endpoint that returns units with water data
                const response = await fetch('/api/units/with-water-readings', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Failed to fetch units');
                }
                
                const data = await response.json();
                
                // Handle different response formats
                if (data.units) {
                    this.allUnits = data.units;
                } else if (data.unitsData) {
                    this.allUnits = data.unitsData;
                } else if (Array.isArray(data)) {
                    this.allUnits = data;
                } else {
                    // Fallback: try to get from units endpoint
                    const unitsResponse = await fetch('/units?format=json', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const unitsData = await unitsResponse.json();
                    this.allUnits = unitsData.unitsData || unitsData.units || [];
                }
                
                // Format the units data
                this.allUnits = this.allUnits.map(unit => ({
                    id: unit.id,
                    unit_number: unit.unit_number,
                    estate_name: unit.estate_name || unit.estate?.name || 'N/A',
                    previous_water_reading: unit.previous_water_reading || unit.previous_reading || 0,
                    current_water_reading: unit.current_water_reading || unit.current_reading || 0,
                    last_reading_date: unit.last_reading_date,
                    water_billing_type: unit.water_billing_type || 'consumption',
                    water_rate: unit.water_rate || unit.custom_water_rate || 50
                }));
                
                this.filteredUnits = [...this.allUnits];
                console.log('Units loaded:', this.allUnits.length);
            } catch (error) {
                console.error('Error fetching units:', error);
                this.formErrors = ['Failed to load units. Please refresh the page.'];
            } finally {
                this.loadingUnits = false;
            }
        },
        
        filterUnits() {
            if (!this.unitSearch.trim()) {
                this.filteredUnits = [...this.allUnits];
                return;
            }
            
            const search = this.unitSearch.toLowerCase();
            this.filteredUnits = this.allUnits.filter(unit => 
                (unit.unit_number && unit.unit_number.toLowerCase().includes(search)) ||
                (unit.estate_name && unit.estate_name.toLowerCase().includes(search))
            );
        },
        
        selectUnit(unit) {
            this.selectedUnit = unit;
            this.readingData.current_reading = unit.current_water_reading || '';
            this.calculateConsumption();
        },
        
        calculateConsumption() {
            const current = parseFloat(this.readingData.current_reading) || 0;
            const previous = parseFloat(this.selectedUnit?.previous_water_reading) || 0;
            
            if (current >= previous) {
                this.readingData.consumption = current - previous;
            } else {
                this.readingData.consumption = 0;
            }
            
            // Calculate estimated charge
            const rate = parseFloat(this.selectedUnit?.water_rate) || 50;
            this.readingData.estimated_charge = this.readingData.consumption * rate;
        },
        
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        async submitReading() {
            if (!this.selectedUnit) {
                this.formErrors = ['Please select a unit first'];
                return;
            }
            
            if (!this.readingData.current_reading || parseFloat(this.readingData.current_reading) <= 0) {
                this.formErrors = ['Please enter a valid current reading'];
                return;
            }
            
            if (parseFloat(this.readingData.current_reading) < parseFloat(this.selectedUnit.previous_water_reading)) {
                this.formErrors = ['Current reading cannot be less than previous reading'];
                return;
            }
            
            if (!this.readingData.reading_date) {
                this.formErrors = ['Please select a reading date'];
                return;
            }
            
            this.isSubmitting = true;
            this.formErrors = [];
            
            try {
                const response = await fetch(`/units/${this.selectedUnit.id}/water-reading`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        current_reading: this.readingData.current_reading,
                        reading_date: this.readingData.reading_date,
                        notes: this.readingData.notes
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    this.closeModal();
                    
                    // Show success message
                    if (window.successModal) {
                        window.successModal.show('Success', data.message || 'Water reading submitted successfully!');
                    } else {
                        alert('Water reading submitted successfully!');
                    }
                    
                    // Reload the page to show updated data
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    this.formErrors = [data.message || data.error || 'Failed to submit reading'];
                }
            } catch (error) {
                console.error('Error submitting reading:', error);
                this.formErrors = ['An error occurred. Please try again.'];
            } finally {
                this.isSubmitting = false;
            }
        }
    }));
});
</script>