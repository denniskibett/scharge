<!-- PAYMENT MODAL (Create & Edit combined) -->
<div x-data="paymentModal" x-init="init()">
  <!-- Backdrop -->
  <template x-if="isOpen">
    <div 
      @click="closeModal"
      class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
    ></div>
  </template>

  <!-- Slideover Modal Content - Right Side -->
  <div x-show="isOpen" 
       x-transition:enter="transition ease-out duration-300 transform"
       x-transition:enter-start="translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-200 transform"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="translate-x-full"
       x-cloak
       class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
       style="width: 42rem; max-width: calc(100% - 2rem);">    
    
    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
      <h4 class="text-lg font-medium text-gray-800 dark:text-white/90" x-text="isEditMode ? 'Edit Payment' : 'Record Payment'"></h4>
      <button
        @click="closeModal"
        class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
      >
        <svg class="fill-current" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/>
        </svg>
      </button>
    </div>

    <form @submit.prevent="submitForm" class="p-6 lg:p-8">
      @csrf
      <input type="hidden" name="_method" x-model="formMethod">

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

      <template x-if="successMessage">
        <div class="mb-6 rounded-lg bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400">
          <p x-text="successMessage"></p>
        </div>
      </template>

      <!-- Tenant Selection -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tenant *</label>
        <select
          x-model="form.tenant_id"
          required
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">Select Tenant</option>
          @foreach($tenants ?? [] as $tenant)
            <option value="{{ $tenant['id'] }}">{{ $tenant['name'] }} ({{ $tenant['unit_number'] ?? 'No Unit' }})</option>
          @endforeach
        </select>
      </div>

      <!-- Invoice Selection -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Invoice (Optional)</label>
        <select
          x-model="form.invoice_id"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">-- Select Invoice (Auto-allocate if empty) --</option>
          <template x-for="invoice in availableInvoices" :key="invoice.id">
            <option :value="invoice.id" x-text="invoice.label"></option>
          </template>
        </select>
      </div>

      <!-- Amount -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
        <div class="relative">
          <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <span class="text-gray-500 dark:text-gray-400">{{ \App\Helpers\SystemHelper::currencySymbol() }}</span>
          </div>
          <input
            type="number"
            step="0.01"
            x-model="form.amount"
            required
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            placeholder="0.00"
          />
        </div>
      </div>

      <!-- Payment Method -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method *</label>
        <select
          x-model="form.payment_method"
          required
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="wallet">Wallet Balance</option>
          <option value="mpesa_stk">M-Pesa STK Push</option>
          <option value="mpesa_paybill">M-Pesa Paybill</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="cash">Cash</option>
          <option value="manual_topup">Manual Top-up</option>
          <option value="message_paste">Transaction Message</option>
        </select>
      </div>

      <!-- Transaction Reference -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction Reference</label>
        <input
          type="text"
          x-model="form.transaction_reference"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 font-mono shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          placeholder="e.g., TXN-12345 or M-Pesa code"
        />
      </div>

      <!-- External Reference (for M-Pesa/Bank) -->
      <div class="mb-5" x-show="form.payment_method !== 'wallet'">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">External Reference</label>
        <input
          type="text"
          x-model="form.external_reference"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          placeholder="M-Pesa receipt number or Bank transaction ID"
        />
      </div>

      <!-- Status (for admin/accountant) -->
      <div class="mb-5" x-show="!isTenant">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
        <select
          x-model="form.status"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
          <option value="failed">Failed</option>
          <option value="cancelled">Cancelled</option>
          <option value="refunded">Refunded</option>
        </select>
      </div>

      <!-- Payment Date -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Date *</label>
        <input
          type="datetime-local"
          x-model="form.payment_datetime"
          required
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
      </div>

      <!-- Notes -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Notes (Optional)</label>
        <textarea
          x-model="form.notes"
          rows="3"
          class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          placeholder="Additional notes about this payment..."
        ></textarea>
      </div>

      <!-- Footer Buttons -->
      <div class="sticky bottom-0 mt-8 flex items-center justify-end gap-3 border-t border-gray-200 bg-white pt-4 dark:border-gray-700 dark:bg-gray-900">
        <button
          @click="closeModal"
          type="button"
          class="flex justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="loading"
          class="flex justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span x-show="!loading" x-text="isEditMode ? 'Update Payment' : 'Create Payment'"></span>
          <span x-show="loading">Processing...</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('paymentModal', () => ({
    isOpen: false,
    isEditMode: false,
    isTenant: {{ auth()->user()->hasRole('tenant') ? 'true' : 'false' }},
    currentPaymentId: null,
    availableInvoices: @json($invoices ?? []),
    form: {
      tenant_id: '',
      invoice_id: '',
      amount: '',
      payment_method: 'mpesa_stk',
      transaction_reference: '',
      external_reference: '',
      status: 'pending',
      payment_datetime: new Date().toISOString().slice(0, 16),
      notes: ''
    },
    formMethod: 'POST',
    formErrors: [],
    successMessage: '',
    loading: false,
    
    init() {
      window.paymentCreateModal = this;
      window.paymentEditModal = this;
    },
    
    openCreateModal() {
      this.isEditMode = false;
      this.currentPaymentId = null;
      this.formMethod = 'POST';
      this.resetForm();
      this.isOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    openEditModal(payment) {
      this.isEditMode = true;
      this.currentPaymentId = payment.id;
      this.formMethod = 'PUT';
      this.form = {
        tenant_id: payment.tenant_id || '',
        invoice_id: payment.invoice_id || '',
        amount: payment.amount || '',
        payment_method: payment.payment_method || 'mpesa_stk',
        transaction_reference: payment.transaction_reference || '',
        external_reference: payment.external_reference || '',
        status: payment.status || 'pending',
        payment_datetime: payment.payment_datetime || payment.created_at ? new Date(payment.created_at).toISOString().slice(0, 16) : new Date().toISOString().slice(0, 16),
        notes: payment.meta?.notes || ''
      };
      this.isOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isOpen = false;
      this.isEditMode = false;
      this.currentPaymentId = null;
      this.formErrors = [];
      this.successMessage = '';
      this.loading = false;
      document.body.style.overflow = '';
    },
    
    resetForm() {
      this.form = {
        tenant_id: '',
        invoice_id: '',
        amount: '',
        payment_method: 'mpesa_stk',
        transaction_reference: '',
        external_reference: '',
        status: 'pending',
        payment_datetime: new Date().toISOString().slice(0, 16),
        notes: ''
      };
      this.formErrors = [];
      this.successMessage = '';
    },
    
    async submitForm() {
      this.formErrors = [];
      this.successMessage = '';
      
      if (!this.form.tenant_id) {
        this.formErrors.push('Please select a tenant');
        return;
      }
      
      if (!this.form.amount || parseFloat(this.form.amount) <= 0) {
        this.formErrors.push('Please enter a valid amount');
        return;
      }
      
      if (!this.form.payment_datetime) {
        this.formErrors.push('Please select payment date');
        return;
      }
      
      this.loading = true;
      
      try {
        const url = this.isEditMode ? `/payments/${this.currentPaymentId}` : '/payments';
        const method = this.isEditMode ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
          },
          body: JSON.stringify(this.form)
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
          this.successMessage = this.isEditMode ? 'Payment updated successfully!' : 'Payment created successfully!';
          setTimeout(() => {
            this.closeModal();
            window.location.reload();
          }, 1500);
        } else {
          this.formErrors = [data.message || data.error || 'Failed to save payment'];
        }
      } catch (error) {
        console.error('Error:', error);
        this.formErrors = ['An error occurred. Please try again.'];
      } finally {
        this.loading = false;
      }
    }
  }));
});
</script>