{{-- resources/views/partials/modal/subscriptions-companies-create.blade.php --}}
<!-- Companies Assign Modal -->
<div x-data="subscriptionsCompaniesModal()" x-init="init()">
    <!-- Backdrop -->
    <div x-show="showModal" x-cloak @click="closeModal()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>

    <!-- Slide-over panel (slides in from the right) -->
    <div x-show="showModal" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Assign Companies</h4>
            <div>
                    <div class="space-y-4">
                        <!-- Company Selection with Unit Count Display -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Companies</label>
                            <div class="space-y-2 max-h-80 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <template x-for="company in availableCompanies" :key="company.id">
                                    <label class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded cursor-pointer">
                                        <input type="checkbox" :value="company.id" x-model="selectedCompanies"
                                            class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="company.name"></span>
                                                <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">
                                                    <span x-text="company.unit_count || 0"></span> units
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500" x-text="company.email"></p>
                                        </div>
                                    </label>
                                </template>
                                <p x-show="availableCompanies.length === 0" class="text-center text-gray-500 py-8">
                                    No available companies found. All companies are already subscribed to this plan.
                                </p>
                            </div>
                        </div>

                        <!-- Unit Count Summary -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Selected Companies</span>
                                <span class="text-sm font-bold text-purple-600 dark:text-purple-400" x-text="selectedCompanies.length"></span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                                <span class="text-sm font-bold text-gray-800 dark:text-white" x-text="totalUnits"></span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Est. Monthly Revenue</span>
                                <span class="text-sm font-bold text-green-600 dark:text-green-400">
                                    KES <span x-text="estimatedRevenue.toLocaleString()"></span>
                                </span>
                            </div>
                        </div>

                        <!-- Billing Cycle -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Billing Cycle</label>
                            <select x-model="billingCycle" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly (10% discount)</option>
                            </select>
                        </div>

                        <!-- Auto Renew -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Auto Renew</label>
                            <select x-model="autoRenew" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500">
                                <option :value="true">Yes</option>
                                <option :value="false">No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button @click="closeModal()" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
                    <button @click="assignCompanies()" :disabled="saving || selectedCompanies.length === 0" 
                        class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                        <span x-show="!saving">Assign to Plan</span>
                        <span x-show="saving">
                            <svg class="animate-spin inline-block h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Assigning...
                        </span>
                    </button>
                </div>
            </div>
        </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('subscriptionsCompaniesModal', () => ({
        showModal: false,
        planId: null,
        saving: false,
        availableCompanies: [],
        selectedCompanies: [],
        billingCycle: 'monthly',
        autoRenew: true,
        pricePerUnit: 0,
        
        // Computed: Total units from selected companies
        get totalUnits() {
            let total = 0;
            this.selectedCompanies.forEach(companyId => {
                const company = this.availableCompanies.find(c => c.id === companyId);
                if (company) {
                    total += parseInt(company.unit_count || 0);
                }
            });
            return total;
        },
        
        // Computed: Estimated monthly revenue
        get estimatedRevenue() {
            return this.totalUnits * (parseFloat(this.pricePerUnit) || 0);
        },
        
        init() {
            window.subscriptionsCompaniesModal = this;
        },
        
        async openModal(planId) {
            this.planId = planId;
            this.selectedCompanies = [];
            this.billingCycle = 'monthly';
            this.autoRenew = true;
            this.pricePerUnit = 0;
            await this.loadAvailableCompanies();
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.showModal = false;
            document.body.style.overflow = '';
        },
        
        async loadAvailableCompanies() {
            try {
                const response = await fetch(`/admin/subscriptions/api/plans/${this.planId}/available-companies`);
                const data = await response.json();
                this.availableCompanies = data.companies || [];
                this.pricePerUnit = data.price_per_unit || 0;
                
                // Debug: Log the data to verify
                console.log('Available companies:', this.availableCompanies);
                console.log('Price per unit:', this.pricePerUnit);
            } catch (error) {
                console.error('Error loading companies:', error);
            }
        },
        
        async assignCompanies() {
            if (this.selectedCompanies.length === 0) return;
            
            this.saving = true;
            try {
                // Build the companies array with unit counts
                const companies = this.selectedCompanies.map(companyId => {
                    const company = this.availableCompanies.find(c => c.id === companyId);
                    return {
                        company_id: companyId,
                        unit_count: company ? parseInt(company.unit_count || 0) : 0
                    };
                });
                
                // Debug: Log the payload
                console.log('Assigning companies:', {
                    companies: companies,
                    billing_cycle: this.billingCycle,
                    auto_renew: this.autoRenew
                });
                
                const response = await fetch(`/admin/subscriptions/plans/${this.planId}/assign-companies`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        companies: companies,
                        billing_cycle: this.billingCycle,
                        auto_renew: this.autoRenew
                    })
                });
                
                const result = await response.json();
                console.log('Assign result:', result);
                
                if (result.success) {
                    this.closeModal();
                    // Show success message with details
                    alert(`${result.message}\n\nTotal Units: ${result.total_units}\nTotal Revenue: KES ${(result.total_revenue || 0).toLocaleString()}`);
                    // Refresh the page to show updated data
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.message || 'Error assigning companies');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error assigning companies: ' + error.message);
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>