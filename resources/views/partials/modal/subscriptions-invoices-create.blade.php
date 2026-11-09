{{-- resources/views/partials/modal/subscriptions-invoices-create.blade.php --}}
<!-- Invoices Create/Edit Modal -->
<div x-data="subscriptionsInvoicesModal()" x-init="init()">
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
        style="width: 42rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="saveInvoice">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="editing ? 'Edit Invoice' : 'Generate Invoice'"></h4>
                    <div class="space-y-4">
                        <!-- Company Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company <span class="text-red-500">*</span></label>
                            <select x-model="form.company_id" @change="onCompanyChange()" required 
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500">
                                <option value="">Select Company</option>
                                <template x-for="company in companies" :key="company.id">
                                    <option :value="company.id" x-text="company.name + ' (' + company.unit_count + ' units)'"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select a company to auto-calculate invoice amount</p>
                        </div>

                        <!-- Billing Period with Flatpickr -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Billing Period <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-gray-400">Start Date</label>
                                    <input type="text" x-ref="billingStart" 
                                        x-model="form.billing_start"
                                        @change="updateAmount()"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 cursor-pointer"
                                        placeholder="Select start date"
                                        readonly>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-gray-400">End Date</label>
                                    <input type="text" x-ref="billingEnd"
                                        x-model="form.billing_end"
                                        @change="updateAmount()"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 cursor-pointer"
                                        placeholder="Select end date"
                                        readonly>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <span x-show="form.billing_start && form.billing_end">
                                    <span x-text="calculateMonths(form.billing_start, form.billing_end)"></span> month(s)
                                </span>
                            </p>
                        </div>

                        <!-- Company Details Summary -->
                        <div x-show="selectedCompany" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800/30">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Plan</p>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white" x-text="planName"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Price Per Unit / Month</p>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white">KES <span x-text="Number(pricePerUnit).toLocaleString()"></span></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Active Units</p>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white" x-text="selectedCompany?.unit_count || 0"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Billing Months</p>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white" x-text="billingMonths"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Invoice Amount (Auto-calculated, LOCKED) -->
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800/30">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Amount (KES)</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Auto-calculated based on units and billing period</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                        KES <span x-text="Number(calculatedAmount).toLocaleString()"></span>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 border-t border-green-200 dark:border-green-800/30 pt-2">
                                <span x-show="selectedCompany && billingMonths > 0">
                                    {{-- Formula: price_per_unit * unit_count * months --}}
                                    <span x-text="Number(pricePerUnit).toLocaleString()"></span> × 
                                    <span x-text="selectedCompany?.unit_count || 0"></span> units × 
                                    <span x-text="billingMonths"></span> month(s) = 
                                    <strong class="text-green-700 dark:text-green-300">KES <span x-text="Number(calculatedAmount).toLocaleString()"></span></strong>
                                </span>
                                <span x-show="!selectedCompany || billingMonths === 0" class="text-gray-400">Select a company and billing period to calculate</span>
                            </div>
                        </div>

                        <!-- Due Date with Flatpickr -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date <span class="text-red-500">*</span></label>
                            <input type="text" x-ref="dueDate"
                                x-model="form.due_date"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 cursor-pointer"
                                placeholder="Select due date"
                                readonly>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Due date must be in the future</p>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select x-model="form.status" @change="onStatusChange()" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500">
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>

                        <!-- Payment Method (shown only for paid status) -->
                        <div x-show="form.status === 'paid'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                            <select x-model="form.payment_method" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500">
                                <option value="">Select Payment Method</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                </form>

                <!-- Footer -->
                <div class="flex items-center justify-between w-full gap-3 mt-6">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span class="text-red-500">*</span> Required fields
                    </div>
                    <div class="flex gap-3">
                        <button @click="closeModal()" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
                        <button @click="saveInvoice()" 
                            :disabled="saving || !form.company_id || !form.amount || !form.due_date || !form.billing_start || !form.billing_end" 
                            class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                            <span x-show="!saving" x-text="editing ? 'Update Invoice' : 'Generate Invoice'"></span>
                            <span x-show="saving" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
</div>

<style>
/* Keep date pickers above the slide-over panel (z-999999) */
.flatpickr-calendar.open {
    z-index: 9999999 !important;
}

/* Flatpickr dark mode support */
.dark .flatpickr-calendar {
    background: #1f2937;
    border-color: #374151;
    color: #e5e7eb;
}
.dark .flatpickr-day {
    color: #e5e7eb;
}
.dark .flatpickr-day.selected {
    background: #8b5cf6;
    border-color: #8b5cf6;
}
.dark .flatpickr-day:hover {
    background: #374151;
}
.dark .flatpickr-months .flatpickr-month {
    color: #e5e7eb;
}
.dark .flatpickr-current-month .flatpickr-monthDropdown-months {
    color: #e5e7eb;
}
.dark .flatpickr-weekday {
    color: #9ca3af;
}
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('subscriptionsInvoicesModal', () => ({
        showModal: false,
        planId: null,
        editing: false,
        editId: null,
        saving: false,
        companies: [],
        pricePerUnit: 0,
        planName: '',
        flatpickrInstances: [],
        
        form: {
            company_id: '',
            amount: 0,
            billing_start: '',
            billing_end: '',
            due_date: '',
            status: 'pending',
            payment_method: '',
        },
        
        // Computed: Get selected company data
        get selectedCompany() {
            return this.companies.find(c => c.id === parseInt(this.form.company_id));
        },
        
        // Computed: Calculate billing months
        get billingMonths() {
            if (!this.form.billing_start || !this.form.billing_end) return 0;
            const start = new Date(this.form.billing_start);
            const end = new Date(this.form.billing_end);
            
            let months = (end.getFullYear() - start.getFullYear()) * 12;
            months += end.getMonth() - start.getMonth();
            
            if (months < 0) return 0;
            
            if (months === 0 && end > start) {
                const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                if (diffDays >= 15) {
                    months = 1;
                }
            }
            
            return months;
        },
        
        // Computed: Calculate amount
        get calculatedAmount() {
            console.log('Calculating amount...', {
                selectedCompany: this.selectedCompany,
                pricePerUnit: this.pricePerUnit,
                billingMonths: this.billingMonths
            });
            
            if (!this.selectedCompany || !this.pricePerUnit || this.billingMonths === 0) {
                return 0;
            }
            const units = parseInt(this.selectedCompany.unit_count) || 0;
            const amount = Math.round(units * parseFloat(this.pricePerUnit) * this.billingMonths);
            
            return amount;
        },
        
        getFirstDayOfMonth(date) {
            const d = new Date(date);
            return new Date(d.getFullYear(), d.getMonth(), 1);
        },
        
        getLastDayOfMonth(date) {
            const d = new Date(date);
            return new Date(d.getFullYear(), d.getMonth() + 1, 0);
        },
        
        formatDate(date) {
            return date.toISOString().split('T')[0];
        },
        
        init() {
            window.subscriptionsInvoicesModal = this;
        },
        
        initFlatpickr() {
            if (this.flatpickrInstances.length) {
                this.flatpickrInstances.forEach(fp => fp.destroy());
                this.flatpickrInstances = [];
            }
            
            const self = this;
            const now = new Date();
            const defaultStart = self.getFirstDayOfMonth(now);
            const defaultEnd = self.getLastDayOfMonth(now);
            const defaultDue = new Date(now.getFullYear(), now.getMonth() + 1, 15);
            
            // Billing Start Date
            const billingStartPicker = flatpickr(this.$refs.billingStart, {
                dateFormat: 'Y-m-d',
                defaultDate: defaultStart,
                onChange: function(selectedDates, dateStr) {
                    if (selectedDates.length > 0) {
                        const firstDay = self.getFirstDayOfMonth(selectedDates[0]);
                        const formattedDate = self.formatDate(firstDay);
                        self.form.billing_start = formattedDate;
                        self.$refs.billingStart.value = formattedDate;
                        self.updateAmount();
                    }
                }
            });
            this.flatpickrInstances.push(billingStartPicker);
            
            // Billing End Date
            const billingEndPicker = flatpickr(this.$refs.billingEnd, {
                dateFormat: 'Y-m-d',
                defaultDate: defaultEnd,
                onChange: function(selectedDates, dateStr) {
                    if (selectedDates.length > 0) {
                        const lastDay = self.getLastDayOfMonth(selectedDates[0]);
                        const formattedDate = self.formatDate(lastDay);
                        self.form.billing_end = formattedDate;
                        self.$refs.billingEnd.value = formattedDate;
                        self.updateAmount();
                    }
                }
            });
            this.flatpickrInstances.push(billingEndPicker);
            
            // Due Date
            const dueDatePicker = flatpickr(this.$refs.dueDate, {
                dateFormat: 'Y-m-d',
                defaultDate: defaultDue,
                onChange: function(selectedDates, dateStr) {
                    self.form.due_date = dateStr;
                }
            });
            this.flatpickrInstances.push(dueDatePicker);
        },
        
        async loadCompanies() {
            try {
                const response = await fetch(`/admin/subscriptions/api/plans/${this.planId}/available-companies`);
                const data = await response.json();
                this.companies = data.companies || [];
                this.pricePerUnit = data.price_per_unit || 0;
                this.planName = data.plan_name || '';
                
                console.log('Loaded companies:', this.companies);
                console.log('Price per unit:', this.pricePerUnit);
            } catch (error) {
                console.error('Error loading companies:', error);
                try {
                    const response = await fetch('/admin/subscriptions/api/companies');
                    const data = await response.json();
                    this.companies = data.companies || [];
                } catch (fallbackError) {
                    console.error('Fallback error:', fallbackError);
                }
            }
        },
        
        openModal(planId, invoiceId = null) {
            this.planId = planId;
            this.editing = !!invoiceId;
            this.editId = invoiceId;
            this.pricePerUnit = 0;
            this.planName = '';
            
            this.loadCompanies().then(() => {
                if (this.editing) {
                    this.loadInvoice(invoiceId);
                } else {
                    this.resetForm();
                    const now = new Date();
                    const firstDay = this.getFirstDayOfMonth(now);
                    const lastDay = this.getLastDayOfMonth(now);
                    const dueDate = new Date(now.getFullYear(), now.getMonth() + 1, 15);
                    
                    this.form.billing_start = this.formatDate(firstDay);
                    this.form.billing_end = this.formatDate(lastDay);
                    this.form.due_date = this.formatDate(dueDate);
                    this.updateAmount();
                }
                
                this.showModal = true;
                document.body.style.overflow = 'hidden';
                
                setTimeout(() => {
                    this.initFlatpickr();
                    if (this.$refs.billingStart) {
                        this.$refs.billingStart.value = this.form.billing_start;
                    }
                    if (this.$refs.billingEnd) {
                        this.$refs.billingEnd.value = this.form.billing_end;
                    }
                    if (this.$refs.dueDate) {
                        this.$refs.dueDate.value = this.form.due_date;
                    }
                }, 100);
            });
        },
        
        closeModal() {
            this.showModal = false;
            document.body.style.overflow = '';
            this.resetForm();
            if (this.flatpickrInstances.length) {
                this.flatpickrInstances.forEach(fp => fp.destroy());
                this.flatpickrInstances = [];
            }
        },
        
        resetForm() {
            this.form = {
                company_id: '',
                amount: 0,
                billing_start: '',
                billing_end: '',
                due_date: '',
                status: 'pending',
                payment_method: '',
            };
        },
        
        onCompanyChange() {
            console.log('Company changed:', this.form.company_id);
            this.updateAmount();
        },
        
        onStatusChange() {
            if (this.form.status !== 'paid') {
                this.form.payment_method = '';
            }
        },
        
        updateAmount() {
            console.log('Updating amount...');
            const amount = this.calculatedAmount;
            console.log('Calculated amount:', amount);
            this.form.amount = amount;
            
            // Debug: Check if amount is set
            console.log('Form amount after update:', this.form.amount);
        },
        
        calculateMonths(startDate, endDate) {
            if (!startDate || !endDate) return 0;
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            let months = (end.getFullYear() - start.getFullYear()) * 12;
            months += end.getMonth() - start.getMonth();
            
            if (months < 0) return 0;
            
            if (months === 0 && end > start) {
                const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                if (diffDays >= 15) {
                    months = 1;
                }
            }
            
            return months;
        },
        
        async loadInvoice(invoiceId) {
            try {
                const response = await fetch(`/admin/subscriptions/api/invoices/${invoiceId}`);
                const data = await response.json();
                this.form = {
                    company_id: data.company_id || '',
                    amount: data.amount || 0,
                    billing_start: data.billing_start || '',
                    billing_end: data.billing_end || '',
                    due_date: data.due_date || '',
                    status: data.status || 'pending',
                    payment_method: data.payment_method || '',
                };
            } catch (error) {
                console.error('Error loading invoice:', error);
            }
        },
        
        async saveInvoice() {
            console.log('Saving invoice...');
            console.log('Form data:', this.form);
            
            // Validate
            if (!this.form.company_id) {
                alert('Please select a company');
                return;
            }
            if (!this.form.billing_start || !this.form.billing_end) {
                alert('Please select a billing period');
                return;
            }
            if (!this.form.due_date) {
                alert('Please select a due date');
                return;
            }
            
            // Ensure amount is calculated
            this.form.amount = this.calculatedAmount;
            
            if (this.form.amount <= 0) {
                alert('Invoice amount must be greater than 0. Check units and billing period.');
                return;
            }
            
            this.saving = true;
            try {
                const url = this.editing 
                    ? `/admin/subscriptions/invoices/${this.editId}`
                    : `/admin/subscriptions/plans/${this.planId}/invoices`;
                const method = this.editing ? 'PUT' : 'POST';
                
                const payload = {
                    company_id: this.form.company_id,
                    amount: this.form.amount,
                    billing_start: this.form.billing_start,
                    billing_end: this.form.billing_end,
                    due_date: this.form.due_date,
                    status: this.form.status,
                    payment_method: this.form.payment_method || null,
                };
                
                console.log('Saving invoice payload:', payload);
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                console.log('Save result:', result);
                
                if (result.success) {
                    this.closeModal();
                    alert(result.message || (this.editing ? 'Invoice updated successfully!' : 'Invoice generated successfully!'));
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.message || 'Error saving invoice');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving invoice: ' + error.message);
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>