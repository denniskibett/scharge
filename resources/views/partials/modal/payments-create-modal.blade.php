<!-- PAYMENT MODAL (Create & Edit combined) - ACCOUNTANT DIRECT PAYMENT -->
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

      <!-- Invoice Summary (when opened from invoice) -->
      <div x-show="preSelectedInvoice" class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
        <h5 class="mb-2 text-sm font-semibold text-blue-800 dark:text-blue-400">📄 Invoice Details</h5>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-gray-600 dark:text-gray-400">Invoice #:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="preSelectedInvoice?.invoice_number"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Tenant:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="preSelectedInvoice?.tenant_name"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Unit:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="preSelectedInvoice?.unit_number"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Billing Month:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="preSelectedInvoice?.billing_month_formatted"></p>
          </div>
          <div class="col-span-2">
            <span class="text-gray-600 dark:text-gray-400">Remaining Amount:</span>
            <p class="font-semibold text-brand-600 dark:text-brand-400" x-text="formatCurrency(preSelectedInvoice?.remaining_amount)"></p>
          </div>
        </div>
        
        <!-- Invoice Items Breakdown -->
        <div x-show="preSelectedInvoice?.items?.length > 0" class="mt-3 pt-2 border-t border-blue-200 dark:border-blue-800">
          <p class="text-xs font-semibold text-blue-800 dark:text-blue-400 mb-2">Invoice Items:</p>
          <div class="space-y-1">
            <template x-for="item in preSelectedInvoice?.items" :key="item.id">
              <div class="flex justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-400" x-text="item.description"></span>
                <span class="font-medium text-gray-800 dark:text-white/90" x-text="formatCurrency(item.amount)"></span>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Tenant Selection (hidden when invoice pre-selected) -->
      <div class="mb-5" x-show="!preSelectedInvoice">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Select Tenant *</label>
        <select
          x-model="form.tenant_id"
          @change="loadTenantInvoices"
          :required="!preSelectedInvoice"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">Select Tenant</option>
          @foreach($tenants ?? [] as $tenant)
            <option value="{{ $tenant['id'] }}" data-unit="{{ $tenant['unit_number'] }}">{{ $tenant['name'] }} ({{ $tenant['unit_number'] ?? 'No Unit' }})</option>
          @endforeach
        </select>
      </div>

      <!-- Invoice Selection (hidden when invoice pre-selected) -->
      <div class="mb-5" x-show="!preSelectedInvoice && tenantInvoices.length > 0">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Select Invoice to Pay *</label>
        <select
          x-model="form.invoice_id"
          @change="selectInvoiceForPayment"
          :required="!preSelectedInvoice"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">Select Invoice</option>
          <template x-for="invoice in tenantInvoices" :key="invoice.id">
            <option :value="invoice.id" x-text="invoice.invoice_number + ' - ' + formatCurrency(invoice.remaining_amount) + ' (' + invoice.status + ')'"></option>
          </template>
        </select>
      </div>

      <!-- Hidden inputs for pre-selected invoice values -->
      <input type="hidden" x-model="form.tenant_id" x-if="preSelectedInvoice">
      <input type="hidden" x-model="form.invoice_id" x-if="preSelectedInvoice">

      <div class="mb-5" x-show="!preSelectedInvoice && tenantInvoices.length === 0 && form.tenant_id">
        <div class="rounded-lg bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
          No pending invoices found for this tenant.
        </div>
      </div>

      <!-- Amount -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Amount *</label>
        <div class="relative">
          <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <span class="text-gray-500 dark:text-gray-400">{{ \App\Helpers\SystemHelper::currencySymbol() }}</span>
          </div>
          <input
            type="number"
            step="0.01"
            x-model="form.amount"
            min="0.01"
            required
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            placeholder="0.00"
          />
        </div>
        <p class="mt-1 text-xs text-gray-500">
          Invoice due: <span x-text="formatCurrency(selectedInvoiceRemaining)"></span>
          <span x-show="parseFloat(form.amount) > selectedInvoiceRemaining" class="text-green-600 ml-2">
            Excess will be added to wallet
          </span>
        </p>
      </div>

      <!-- Payment Method -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method *</label>
        <select
          x-model="form.payment_method"
          required
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="cash">💰 Cash</option>
          <option value="bank_transfer">🏦 Bank Transfer</option>
          <option value="mpesa_paybill">📱 M-Pesa Paybill</option>
          <option value="manual_topup">📝 Manual Top-up</option>
        </select>
      </div>

      <!-- External Reference -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Reference / Receipt Number</label>
        <input
          type="text"
          x-model="form.external_reference"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          placeholder="Receipt number, M-Pesa code, or Bank transaction ID"
        />
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

      <!-- Summary -->
      <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
        <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3">Payment Summary</h5>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Total Payment:</span>
            <span class="font-semibold text-brand-600 dark:text-brand-400" x-text="formatCurrency(form.amount)"></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Invoice Amount Due:</span>
            <span class="text-gray-800 dark:text-white/90" x-text="formatCurrency(selectedInvoiceRemaining)"></span>
          </div>
          
          <!-- Show excess calculation -->
          <template x-if="parseFloat(form.amount) > selectedInvoiceRemaining">
            <div class="flex justify-between text-green-600">
              <span>Excess (Added to Wallet):</span>
              <span class="font-semibold" x-text="formatCurrency(parseFloat(form.amount) - selectedInvoiceRemaining)"></span>
            </div>
          </template>
          
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Payment Method:</span>
            <span class="text-gray-800 dark:text-white/90" x-text="getPaymentMethodLabel(form.payment_method)"></span>
          </div>
          <div class="flex justify-between" x-show="form.external_reference">
            <span class="text-gray-600 dark:text-gray-400">Reference:</span>
            <span class="text-gray-800 dark:text-white/90 font-mono text-xs" x-text="form.external_reference"></span>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
            <div class="flex justify-between">
              <span class="text-gray-700 dark:text-gray-300">Invoice Status After:</span>
              <span x-show="selectedInvoiceRemaining <= parseFloat(form.amount)" class="text-green-600">Fully Paid</span>
              <span x-show="selectedInvoiceRemaining > parseFloat(form.amount)" class="text-yellow-600">Partially Paid</span>
            </div>
            <div class="flex justify-between mt-1" x-show="parseFloat(form.amount) > selectedInvoiceRemaining">
              <span class="text-gray-700 dark:text-gray-300">Wallet Balance Added:</span>
              <span class="text-green-600" x-text="formatCurrency(parseFloat(form.amount) - selectedInvoiceRemaining)"></span>
            </div>
          </div>
        </div>
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
          :disabled="loading || !isFormValid"
          class="flex justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span x-show="!loading" x-text="isEditMode ? 'Update Payment' : 'Process Payment'"></span>
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
    preSelectedInvoice: null,
    selectedInvoiceData: null,
    tenantInvoices: [],
    form: {
      tenant_id: '',
      invoice_id: '',
      amount: '',
      payment_method: 'cash',
      external_reference: '',
      payment_datetime: new Date().toISOString().slice(0, 16),
      notes: ''
    },
    formMethod: 'POST',
    formErrors: [],
    successMessage: '',
    loading: false,
    
    get maxAmount() {
      return this.selectedInvoiceData?.remaining_amount || 0;
    },
    
    get selectedInvoiceRemaining() {
      return this.selectedInvoiceData?.remaining_amount || 0;
    },
    
    get isFormValid() {
      if (!this.form.tenant_id && !this.preSelectedInvoice) return false;
      if (!this.form.invoice_id && !this.preSelectedInvoice) return false;
      if (!this.form.amount || parseFloat(this.form.amount) <= 0) return false;
      // Remove the check that prevents overpayment - allow any amount >= 0.01
      // if (parseFloat(this.form.amount) > this.selectedInvoiceRemaining) return false;
      if (!this.form.payment_datetime) return false;
      if (!this.form.payment_method) return false;
      return true;
    },
    
    init() {
      window.paymentCreateModal = this;
    },
    
    openCreateModal() {
      this.isEditMode = false;
      this.currentPaymentId = null;
      this.preSelectedInvoice = null;
      this.selectedInvoiceData = null;
      this.formMethod = 'POST';
      this.resetForm();
      this.isOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    openPaymentModalForInvoice(invoice) {
        console.log('=== openPaymentModalForInvoice called ===');
        console.log('Received invoice data:', invoice);
        
        if (!invoice || !invoice.id) {
            console.error('Invalid invoice data:', invoice);
            alert('Could not process payment: Invalid invoice data');
            return;
        }
        
        this.isEditMode = false;
        this.currentPaymentId = null;
        this.preSelectedInvoice = invoice;
        this.selectedInvoiceData = invoice;
        this.formMethod = 'POST';
        
        // Populate form with invoice data
        this.form.tenant_id = invoice.tenant_id;
        this.form.invoice_id = invoice.id;
        this.form.amount = invoice.remaining_amount || invoice.total_amount || 0;
        this.form.payment_datetime = new Date().toISOString().slice(0, 16);
        this.form.external_reference = '';
        this.form.notes = '';
        this.form.payment_method = 'cash';
        
        console.log('Form populated:', this.form);
        console.log('Tenant ID:', this.form.tenant_id);
        console.log('Invoice ID:', this.form.invoice_id);
        console.log('Amount:', this.form.amount);
        
        // Fetch additional invoice details if needed
        if (invoice.id) {
            this.fetchInvoiceDetails(invoice.id);
        }
        
        this.isOpen = true;
        document.body.style.overflow = 'hidden';
    },

    async fetchInvoiceDetails(invoiceId) {
        console.log('Fetching invoice details for ID:', invoiceId);
        
        if (!invoiceId) {
            console.error('No invoice ID provided for fetching details');
            return;
        }
        
        try {
            const response = await fetch(`/invoices/${invoiceId}/details`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('Fetch response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Invoice details response:', data);
            
            if (data.success) {
                this.selectedInvoiceData = data.invoice;
                
                // Update form with invoice data
                this.form.tenant_id = data.invoice.tenant_id;
                this.form.invoice_id = data.invoice.id;
                this.form.amount = data.invoice.remaining_amount;
                
                // Also update preSelectedInvoice to show correct data
                if (this.preSelectedInvoice) {
                    this.preSelectedInvoice = {
                        ...this.preSelectedInvoice,
                        ...data.invoice
                    };
                }
            } else {
                console.warn('Failed to fetch invoice details:', data.error);
            }
        } catch (error) {
            console.error('Error fetching invoice details:', error);
            this.formErrors = ['Could not load invoice details. Please try again.'];
        }
    },
    
    openEditModal(payment) {
      this.isEditMode = true;
      this.currentPaymentId = payment.id;
      this.formMethod = 'PUT';
      this.form = {
        tenant_id: payment.tenant_id || '',
        invoice_id: payment.invoice_id || '',
        amount: payment.amount || '',
        payment_method: payment.payment_method || 'cash',
        external_reference: payment.external_reference || '',
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
      this.preSelectedInvoice = null;
      this.selectedInvoiceData = null;
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
        payment_method: 'cash',
        external_reference: '',
        payment_datetime: new Date().toISOString().slice(0, 16),
        notes: ''
      };
      this.tenantInvoices = [];
      this.selectedInvoiceData = null;
      this.formErrors = [];
      this.successMessage = '';
    },
    
    async loadTenantInvoices() {
      if (!this.form.tenant_id) {
        this.tenantInvoices = [];
        return;
      }
      
      try {
        const response = await fetch(`/payments/tenant/${this.form.tenant_id}/invoices`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const data = await response.json();
        if (data.success) {
          this.tenantInvoices = data.invoices;
        }
      } catch (error) {
        console.error('Error loading tenant invoices:', error);
      }
    },
    
    selectInvoiceForPayment() {
      const selected = this.tenantInvoices.find(inv => inv.id == this.form.invoice_id);
      if (selected) {
        this.selectedInvoiceData = selected;
        this.form.amount = selected.remaining_amount;
      }
    },
    
    getPaymentMethodLabel(method) {
      const labels = {
        'cash': 'Cash',
        'bank_transfer': 'Bank Transfer',
        'mpesa_paybill': 'M-Pesa Paybill',
        'manual_topup': 'Manual Top-up'
      };
      return labels[method] || method;
    },
    
    formatCurrency(amount) {
      const symbol = "{{ \App\Helpers\SystemHelper::currencySymbol() ?? 'KES ' }}";
      if (!amount && amount !== 0) return symbol + "0.00";
      return symbol + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    
async submitForm() {
  this.formErrors = [];
  this.successMessage = '';
  
  const tenantId = this.preSelectedInvoice?.tenant_id || this.form.tenant_id;
  const invoiceId = this.preSelectedInvoice?.id || this.form.invoice_id;
  
  if (!tenantId) {
    this.formErrors.push('Please select a tenant');
    return;
  }
  
  if (!invoiceId) {
    this.formErrors.push('Please select an invoice to pay');
    return;
  }
  
  if (!this.form.amount || parseFloat(this.form.amount) <= 0) {
    this.formErrors.push('Please enter a valid amount');
    return;
  }
  
  // Allow overpayment - show warning, don't block
  if (parseFloat(this.form.amount) > this.selectedInvoiceRemaining) {
    const excess = parseFloat(this.form.amount) - this.selectedInvoiceRemaining;
    if (!confirm(`Amount exceeds invoice due by ${this.formatCurrency(excess)}. This excess will be added to the tenant's wallet balance. Continue?`)) {
      return;
    }
  }
  
  if (!this.form.payment_datetime) {
    this.formErrors.push('Please select payment date');
    return;
  }
  
  if (!this.form.payment_method) {
    this.formErrors.push('Please select payment method');
    return;
  }
  
  this.loading = true;
  
  try {
    let url, method, body;
    
    if (this.isEditMode) {
      url = `/payments/${this.currentPaymentId}`;
      method = 'PUT';
      body = JSON.stringify({
        status: this.form.status,
        is_reconciled: this.form.status === 'completed' ? 1 : 0,
        notes: this.form.notes
      });
    } else {
      url = '/payments';
      method = 'POST';
      body = JSON.stringify({
        tenant_id: tenantId,
        invoice_id: invoiceId,
        amount: parseFloat(this.form.amount),
        payment_method: this.form.payment_method,
        external_reference: this.form.external_reference,
        payment_datetime: this.form.payment_datetime,
        notes: this.form.notes
      });
    }
    
    console.log('Sending payment request:', { url, method, body });
    
    const response = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json'
      },
      body: body
    });
    
    const data = await response.json();
    console.log('Payment response:', data);
    
    if (response.ok && data.success) {
      if (this.isEditMode) {
        this.successMessage = 'Payment updated successfully!';
      } else {
        // Build success message based on what happened
        const amountPaid = data.data?.amount_paid_to_invoice || parseFloat(this.form.amount);
        const amountToWallet = data.data?.amount_added_to_wallet || 0;
        
        if (amountToWallet > 0 && amountPaid > 0) {
          this.successMessage = `Payment successful! KES ${this.formatNumber(amountPaid)} paid to invoice, KES ${this.formatNumber(amountToWallet)} added to wallet.`;
        } else if (amountPaid > 0) {
          this.successMessage = `Payment successful! KES ${this.formatNumber(amountPaid)} paid to invoice.`;
        } else {
          this.successMessage = `KES ${this.formatNumber(amountToWallet)} added to wallet balance.`;
        }
      }
      
      // Dispatch wallet update event
      if (data.data?.wallet_balance !== undefined) {
        const updateEvent = new CustomEvent('wallet-updated', {
          detail: { new_balance: data.data.wallet_balance }
        });
        window.dispatchEvent(updateEvent);
        document.dispatchEvent(updateEvent);
      }
      
      setTimeout(() => {
        this.closeModal();
        window.location.reload();
      }, 2000);
    } else {
      this.formErrors = [data.message || data.error || 'Failed to process payment'];
    }
  } catch (error) {
    console.error('Error:', error);
    this.formErrors = ['An error occurred. Please try again.'];
  } finally {
    this.loading = false;
  }
},

formatNumber(value) {
  if (!value && value !== 0) return '0.00';
  return parseFloat(value).toLocaleString('en-KE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}
  }));
});
</script>