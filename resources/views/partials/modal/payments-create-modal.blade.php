<!-- CREATE PAYMENT SLIDEOVER MODAL -->
<div x-data="paymentCreateModal" x-init="init()">
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
      <!-- close btn -->
      <button
        @click="closeModal()"
        class="group absolute right-3 top-3 z-99999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
      >
        <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
        </svg>
      </button>

      <form @submit.prevent="submitPayment">
        @csrf
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
          Add New Payment
        </h4>

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

        <!-- Loading State -->
        <div x-show="isLoading" class="mb-6 p-4 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-brand-500 border-t-transparent"></div>
          <p class="mt-2 text-sm text-gray-500">Loading data...</p>
        </div>

        <div x-show="!isLoading">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <!-- Tenant Payer Selection -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tenant (Payer) *</label>
              <select 
                x-model="formData.tenancy_id"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Tenant</option>
                <template x-for="user in users" :key="user.id">
                  <option :value="user.id" x-text="user.name"></option>
                </template>
              </select>
            </div>
            
<!-- Invoice Selection -->
<div>
  <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Invoice (Optional)</label>
  <select 
    x-model="formData.invoice_id"
    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
  >
    <option value="">No Invoice (General Payment)</option>
    <template x-for="invoice in invoices" :key="invoice.id">
      <option :value="invoice.id" x-text="invoice.label"></option>
    </template>
  </select>
</div>
            
            <!-- Amount -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">{{ SystemHelper::currencySymbol() }}</span>
                </div>
                <input 
                  x-model="formData.amount"
                  @blur="formatAmount()"
                  type="number"
                  step="0.01"
                  required
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>
            
            <!-- Payment Method -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method *</label>
              <select 
                x-model="formData.payment_method"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="mpesa">Mpesa</option>
                <option value="bank">Bank Transfer</option>
                <option value="cash">Cash</option>
              </select>
            </div>
            
            <!-- Transaction ID -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction ID</label>
              <input 
                x-model="formData.transaction_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="e.g., Mpesa confirmation code"
              />
            </div>
            
            <!-- Paid To -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Paid To</label>
              <input 
                x-model="formData.paid_to"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="Company name"
              />
            </div>
            
            <!-- Payer Name -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payer Name</label>
              <input 
                x-model="formData.payer_name"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="Name of person making payment"
              />
            </div>
            
            <!-- Payment Date -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Date *</label>
              <input 
                x-model="formData.payment_datetime"
                @change="updatePaymentMonth()"
                type="datetime-local"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <!-- Payment Month (Auto-generated) -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Month</label>
              <input 
                x-model="formData.payment_month"
                readonly
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 cursor-not-allowed"
              />
            </div>
            
            <!-- Transaction Message (Full width) -->
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction Message / Notes</label>
              <textarea 
                x-model="formData.transaction_message"
                rows="3"
                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="Additional notes about this payment..."
              ></textarea>
            </div>
          </div>

          <!-- Summary -->
          <div class="mt-6 rounded-lg bg-gray-50 dark:bg-gray-800 p-4" x-show="formData.amount && formData.payment_method">
            <h5 class="font-medium text-gray-800 dark:text-white/90 mb-2">Summary</h5>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Amount: <span class="font-medium">{{ SystemHelper::currencySymbol() }} <span x-text="parseFloat(formData.amount || 0).toFixed(2)"></span></span>
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Method: <span class="font-medium capitalize" x-text="formData.payment_method"></span>
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Payment Month: <span class="font-medium" x-text="formData.payment_month"></span>
            </p>
          </div>
        </div>

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
            :disabled="isSubmitting"
            class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
          >
            <span x-show="!isSubmitting">Save Payment</span>
            <span x-show="isSubmitting">Processing...</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('paymentCreateModal', () => ({
    isOpen: false,
    isLoading: false,
    isSubmitting: false,
    formData: {
      tenancy_id: '',
      invoice_id: '',
      amount: '',
      payment_method: 'mpesa',
      transaction_id: '',
      transaction_message: '',
      paid_to: '',
      payer_name: '',
      payment_datetime: '',
      payment_month: ''
    },
    formErrors: [],
    users: [],
    invoices: [],
    
    init() {
      // Set default values
      const now = new Date();
      const currentDateTime = now.toISOString().slice(0, 16);
      const currentMonth = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      
      this.formData.payment_datetime = currentDateTime;
      this.formData.payment_month = currentMonth;
      
      // Make modal accessible globally
      window.paymentCreateModal = this;
      
      // Fetch initial data
      this.fetchData();
    },
    
    async fetchData() {
      this.isLoading = true;
      
      try {
        const response = await fetch('{{ route("payments.create-data") }}');
        const data = await response.json();
        
        if (data.success) {
          this.users = data.users;
          this.invoices = data.invoices;
        }
      } catch (error) {
        console.error('Error fetching payment data:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    openModal(invoiceData = null) {
      this.isOpen = true;
      this.resetForm();
      
      if (invoiceData) {
        this.formData.invoice_id = invoiceData.id;
        this.formData.tenancy_id = invoiceData.tenancy_id;
        this.formData.payer_name = invoiceData.tenant_name;
      }
      
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isOpen = false;
      this.formErrors = [];
      document.body.style.overflow = '';
    },
    
    resetForm() {
      const now = new Date();
      const currentDateTime = now.toISOString().slice(0, 16);
      const currentMonth = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      
      this.formData = {
        tenancy_id: '',
        invoice_id: '',
        amount: '',
        payment_method: 'mpesa',
        transaction_id: '',
        transaction_message: '',
        paid_to: '',
        payer_name: '',
        payment_datetime: currentDateTime,
        payment_month: currentMonth
      };
    },
    
    updatePaymentMonth() {
      if (this.formData.payment_datetime) {
        try {
          const date = new Date(this.formData.payment_datetime);
          if (!isNaN(date.getTime())) {
            this.formData.payment_month = date.toLocaleDateString('en-US', { 
              month: 'long', 
              year: 'numeric' 
            });
          }
        } catch (e) {
          console.error('Error updating payment month:', e);
        }
      }
    },
    
    formatAmount() {
      if (this.formData.amount) {
        const value = parseFloat(this.formData.amount);
        if (!isNaN(value)) {
          this.formData.amount = value.toFixed(2);
        } else {
          this.formData.amount = '';
        }
      }
    },
    
    validateForm() {
      this.formErrors = [];
      
      if (!this.formData.tenancy_id) {
        this.formErrors.push('Please select a tenant');
      }
      
      if (!this.formData.amount || parseFloat(this.formData.amount) <= 0) {
        this.formErrors.push('Please enter a valid amount greater than 0');
      }
      
      if (!this.formData.payment_datetime) {
        this.formErrors.push('Please select a payment date');
      }
      
      if (!this.formData.payment_method) {
        this.formErrors.push('Please select a payment method');
      }
      
      return this.formErrors.length === 0;
    },
    
    async submitPayment() {
      if (!this.validateForm()) {
        // Scroll to top to show errors
        const modalContent = document.querySelector('.overflow-y-auto');
        if (modalContent) {
          modalContent.scrollTop = 0;
        }
        return;
      }
      
      this.isSubmitting = true;
      
      try {
        const response = await fetch('{{ route("payments.store") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
          },
          body: JSON.stringify(this.formData)
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
          this.closeModal();
          
          if (window.successModal) {
            window.successModal.show('Success', data.message);
          } else {
            alert(data.message || 'Payment created successfully!');
          }
          
          setTimeout(() => window.location.reload(), 1500);
        } else {
          if (data.errors) {
            this.formErrors = Object.values(data.errors).flat();
          } else {
            this.formErrors = [data.message || 'Failed to create payment'];
          }
        }
      } catch (error) {
        console.error('Error creating payment:', error);
        this.formErrors = ['An error occurred. Please try again.'];
      } finally {
        this.isSubmitting = false;
      }
    }
  }));
});
</script>