<!-- CREATE PAYMENT SLIDEOVER MODAL -->
<div x-data="paymentCreateModal" x-init="init()">
  <!-- Backdrop -->
  <template x-if="isOpen">
    <div 
      @click="closeModal"
      class="fixed inset-0 bg-gray-700/30 backdrop-blur-md backdrop-saturate-150 transition-opacity z-[99999]"
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
    <!-- Header -->
    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
      <h4 class="text-lg font-medium text-gray-800 dark:text-white/90">
        Record Payment
      </h4>
      <button
        @click="closeModal"
        class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
      >
        <svg class="fill-current" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/>
        </svg>
      </button>
    </div>

    <!-- Form Content -->
    <form @submit.prevent="submitForm" class="p-6 lg:p-8">
      @csrf

      <!-- Status Banner for Tenant Users -->
      <div class="mb-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
        <div class="flex items-start gap-3">
          <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <strong>How it works:</strong> Paste your M-Pesa or Bank transaction message below. 
            Our system will automatically extract payment details. An accountant will verify 
            and confirm your payment within 24 hours.
          </div>
        </div>
      </div>

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

      <!-- Tenancy Details Section -->
      <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <h5 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Tenancy Details</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <span class="text-gray-500 dark:text-gray-400">Tenant Name:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="tenancyDetails.tenant_name || '-'"></p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Unit Number:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="tenancyDetails.unit_number || '-'"></p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Estate:</span>
            <p class="font-medium text-gray-800 dark:text-white/90" x-text="tenancyDetails.estate_name || '-'"></p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-400">Outstanding Balance:</span>
            <p class="font-medium text-red-600 dark:text-red-400" x-text="formatCurrency(tenancyDetails.outstanding_balance)"></p>
          </div>
        </div>
      </div>

      <!-- INVOICE SELECTION (for accountant/partial payments) -->
      <div class="mb-5" x-show="!isTenant">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
          Select Invoice (Optional - leave blank for manual allocation)
        </label>
        <select
          x-model="form.invoice_id"
          class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
          <option value="">-- Auto-allocate to oldest invoice --</option>
          @foreach($invoices ?? [] as $invoiceItem)
            <option value="{{ $invoiceItem['id'] }}">
              {{ $invoiceItem['label'] }} - {{ $invoiceItem['total_amount'] ?? 0 }}
            </option>
          @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Leave empty to allocate to oldest outstanding invoice</p>
      </div>

      <!-- MAIN INPUT: Transaction Message -->
      <div class="mb-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
          Transaction Message *
        </label>
        <textarea
          x-model="form.transaction_message"
          @input="parseTransactionMessage"
          rows="4"
          class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 font-mono"
          placeholder="Paste your M-Pesa or Bank transaction message here...

Example M-Pesa:
UEB6O3QQTT Confirmed. Ksh800.00 sent to KPLC PREPAID for account 37228776227 on 11/5/26 at 1:05 PM

Example Bank Transfer:
Pesalink transfer of KES 189,600.00 to A/c 01136011289100-Danaff Kenya Company Limited on 04/05/2026 11:16 processed successfully. Transaction Ref ID:403004159452"
        ></textarea>
      </div>

      <!-- AUTO-PARSED FIELDS (readonly, show extracted data) -->
      <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
        <h5 class="mb-3 text-sm font-semibold text-green-800 dark:text-green-400">📝 Extracted Payment Details</h5>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-600 dark:text-gray-400">Amount:</span>
            <p class="font-medium text-green-700 dark:text-green-400" x-text="parsed.amount ? formatCurrency(parsed.amount) : '—'"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Transaction ID:</span>
            <p class="font-medium font-mono text-green-700 dark:text-green-400" x-text="parsed.transaction_id || '—'"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Payment Method:</span>
            <p class="font-medium text-green-700 dark:text-green-400">
              <span x-show="parsed.payment_method === 'mpesa'" class="flex items-center gap-1">📱 M-Pesa</span>
              <span x-show="parsed.payment_method === 'bank'" class="flex items-center gap-1">🏦 Bank Transfer</span>
              <span x-show="!parsed.payment_method" class="text-gray-500">—</span>
            </p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Payment Date:</span>
            <p class="font-medium text-green-700 dark:text-green-400" x-text="parsed.payment_date || '—'"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Payment Month:</span>
            <p class="font-medium text-green-700 dark:text-green-400" x-text="parsed.payment_month || '—'"></p>
          </div>
          <div>
            <span class="text-gray-600 dark:text-gray-400">Payer Name:</span>
            <p class="font-medium text-green-700 dark:text-green-400" x-text="parsed.payer_name || '—'"></p>
          </div>
        </div>
        <div class="mt-3 pt-2 border-t border-green-200 dark:border-green-800">
          <p class="text-xs text-green-700 dark:text-green-400">
            <span x-show="parsed.fully_parsed" class="text-green-600">✓ All fields extracted successfully</span>
            <span x-show="!parsed.fully_parsed && form.transaction_message" class="text-amber-600">⚠️ Could not fully parse this message. Please check and edit fields below if needed.</span>
          </p>
        </div>
      </div>

      <!-- MANUAL EDIT FIELDS (for corrections) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="col-span-2">
          <p class="text-sm text-gray-500 mb-2">✏️ Edit fields below if extraction was incorrect</p>
        </div>

        <!-- Amount -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Payment Amount *
          </label>
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

        <!-- Transaction ID -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Transaction ID *
          </label>
          <input
            type="text"
            x-model="form.transaction_id"
            required
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 font-mono shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            placeholder="e.g., UEB6O3QQTT or 403004159452"
          />
        </div>

        <!-- Payment Method -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Payment Method *
          </label>
          <select
            x-model="form.payment_method"
            required
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          >
            <option value="mpesa">M-Pesa</option>
            <option value="bank">Bank Transfer</option>
            <option value="cash">Cash</option>
          </select>
        </div>

        <!-- Payment Date -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Payment Date *
          </label>
          <input
            type="datetime-local"
            x-model="form.payment_datetime"
            required
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          />
        </div>

        <!-- Payment Month -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Payment Month (Bill Month) *
          </label>
          <input
            type="month"
            x-model="form.payment_month"
            required
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
          />
          <p class="mt-1 text-xs text-gray-500">The month this payment is FOR (e.g., if paying April rent, select April)</p>
        </div>

        <!-- Payer Name -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Payer Name
          </label>
          <input
            type="text"
            x-model="form.payer_name"
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            placeholder="Sender/M-Pesa account name"
          />
        </div>

        <!-- Paid To -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Paid To
          </label>
          <input
            type="text"
            x-model="form.paid_to"
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            placeholder="Receiver (e.g., KPLC PREPAID or Company Name)"
          />
        </div>
      </div>

      <!-- Hidden/Readonly for tenant - they can't edit invoice directly -->
      <input type="hidden" x-model="form.invoice_id" x-show="isTenant">

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
          :disabled="loading || !form.transaction_message || !form.amount || !form.transaction_id || !form.payment_method"
          class="flex justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span x-show="!loading">Submit Payment for Verification</span>
          <span x-show="loading">Submitting...</span>
        </button>
      </div>
      
      <p class="text-center text-xs text-gray-400 mt-4">
        Payment will be reviewed by an accountant before confirmation
      </p>
    </form>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('paymentCreateModal', () => ({
    isOpen: false,
    isTenant: {{ auth()->user()->hasRole('tenant') ? 'true' : 'false' }},
    invoiceData: null,
    tenancyId: null,
    tenancyDetails: {
      tenant_name: '',
      unit_number: '',
      estate_name: '',
      total_amount: 0,
      outstanding_balance: 0,
      billing_month: ''
    },
    form: {
      invoice_id: '',
      amount: '',
      payment_method: 'mpesa',
      transaction_id: '',
      transaction_message: '',
      paid_to: '',
      payer_name: '',
      payment_datetime: new Date().toISOString().slice(0, 16),
      payment_month: new Date().toISOString().slice(0, 7)
    },
    parsed: {
      amount: null,
      transaction_id: null,
      payment_method: null,
      payment_date: null,
      payment_month: null,
      payer_name: null,
      paid_to: null,
      fully_parsed: false
    },
    formErrors: [],
    loading: false,
    
    init() {
      window.paymentCreateModal = this;
    },
    
    openModal(data) {
      console.log('Opening modal with data:', data);
      this.invoiceData = data;
      this.tenancyId = data.tenancy_id;
      this.isOpen = true;
      this.resetForm();
      
      // Set tenant name as default payer name
      if (data.tenant_name) {
        this.form.payer_name = data.tenant_name;
      }
      
      this.tenancyDetails = {
        tenant_name: data.tenant_name || '',
        unit_number: data.unit_number || '',
        estate_name: data.estate_name || '',
        total_amount: data.total_amount || 0,
        outstanding_balance: data.outstanding_balance || data.total_amount || 0,
        billing_month: data.billing_month || ''
      };
      
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isOpen = false;
      this.invoiceData = null;
      this.tenancyId = null;
      this.formErrors = [];
      this.loading = false;
      document.body.style.overflow = '';
    },
    
    resetForm() {
      this.form = {
        invoice_id: '',
        amount: '',
        payment_method: 'mpesa',
        transaction_id: '',
        transaction_message: '',
        paid_to: '',
        payer_name: this.tenancyDetails.tenant_name || '',
        payment_datetime: new Date().toISOString().slice(0, 16),
        payment_month: new Date().toISOString().slice(0, 7)
      };
      this.parsed = {
        amount: null,
        transaction_id: null,
        payment_method: null,
        payment_date: null,
        payment_month: null,
        payer_name: null,
        paid_to: null,
        fully_parsed: false
      };
      this.formErrors = [];
      this.loading = false;
    },
    
    parseTransactionMessage() {
      const message = this.form.transaction_message || '';
      
      // Reset parsed values
      this.parsed = {
        amount: null,
        transaction_id: null,
        payment_method: null,
        payment_date: null,
        payment_month: null,
        payer_name: null,
        paid_to: null,
        fully_parsed: false
      };
      
      if (!message.trim()) return;
      
      // Detect payment method
      const isMpesa = /mpesa|M-PESA|Ksh|KSh/i.test(message);
      const isBank = /pesalink|bank transfer|RTGS|EFT/i.test(message);
      
      if (isMpesa) {
        this.parsed.payment_method = 'mpesa';
        this.form.payment_method = 'mpesa';
        this.parseMpesaMessage(message);
      } else if (isBank) {
        this.parsed.payment_method = 'bank';
        this.form.payment_method = 'bank';
        this.parseBankMessage(message);
      }
      
      // Check if we have all required fields
      this.parsed.fully_parsed = !!(this.parsed.amount && this.parsed.transaction_id && this.parsed.payment_date);
      
      // Auto-populate form fields from parsed data
      if (this.parsed.amount) this.form.amount = this.parsed.amount;
      if (this.parsed.transaction_id) this.form.transaction_id = this.parsed.transaction_id;
      if (this.parsed.payment_date) this.form.payment_datetime = this.parsed.payment_date;
      if (this.parsed.payment_month) this.form.payment_month = this.parsed.payment_month;
      if (this.parsed.payer_name && !this.form.payer_name) this.form.payer_name = this.parsed.payer_name;
      if (this.parsed.paid_to) this.form.paid_to = this.parsed.paid_to;
    },
    
    parseMpesaMessage(message) {
      // Extract Transaction ID (first word, usually uppercase alphanumeric)
      const txnIdMatch = message.match(/^([A-Z0-9]{8,12})\s/);
      if (txnIdMatch) {
        this.parsed.transaction_id = txnIdMatch[1];
      } else {
        // Alternative: look for Confirmed. code
        const altTxnMatch = message.match(/Confirmed\.\s*([A-Z0-9]+)/i);
        if (altTxnMatch) this.parsed.transaction_id = altTxnMatch[1];
      }
      
      // Extract Amount - matches Ksh800.00 or KES 800 or KSh 800.00
      const amountMatch = message.match(/Ksh?s?\s*([\d,]+\.?\d*)|KES\s*([\d,]+\.?\d*)/i);
      if (amountMatch) {
        let amountStr = amountMatch[1] || amountMatch[2];
        if (amountStr) {
          this.parsed.amount = parseFloat(amountStr.replace(/,/g, ''));
        }
      }
      
      // Extract Payment Date - M-Pesa format: "on 11/5/26 at 1:05 PM"
      const dateMatch = message.match(/on\s+(\d{1,2}\/\d{1,2}\/\d{2,4})\s+at\s+(\d{1,2}:\d{2}\s*(?:AM|PM))/i);
      if (dateMatch) {
        const dateStr = dateMatch[1];
        const timeStr = dateMatch[2];
        const parsedDate = this.parseMpesaDate(dateStr, timeStr);
        if (parsedDate) {
          this.parsed.payment_date = parsedDate;
          // Extract month for payment_month (YYYY-MM)
          const dateObj = new Date(parsedDate);
          if (!isNaN(dateObj.getTime())) {
            this.parsed.payment_month = `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}`;
          }
        }
      }
      
      // Extract Paid To / Paybill name
      const paidToMatch = message.match(/sent to\s+([A-Z\s]+?)(?:\s+for|\s+on|\s+\.|$)/i);
      if (paidToMatch) {
        this.parsed.paid_to = paidToMatch[1].trim();
      }
    },
    
    parseBankMessage(message) {
      // Extract Transaction Ref ID - FIXED SYNTAX
      const refMatch = message.match(/Transaction Ref ID:?\s*(\d+)/i);
      if (refMatch) {
        this.parsed.transaction_id = refMatch[1];
      }
      
      // Extract Amount - format "KES 189,600.00"
      const amountMatch = message.match(/KES\s*([\d,]+\.?\d*)/i);
      if (amountMatch) {
        this.parsed.amount = parseFloat(amountMatch[1].replace(/,/g, ''));
      }
      
      // Extract Date - format "on 04/05/2026 11:16"
      const dateMatch = message.match(/on\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{1,2}:\d{2})/i);
      if (dateMatch) {
        const dateStr = dateMatch[1]; // DD/MM/YYYY format
        const timeStr = dateMatch[2];
        const parsedDate = this.parseBankDate(dateStr, timeStr);
        if (parsedDate) {
          this.parsed.payment_date = parsedDate;
          const dateObj = new Date(parsedDate);
          if (!isNaN(dateObj.getTime())) {
            this.parsed.payment_month = `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}`;
          }
        }
      }
      
      // Extract Paid To (Account name)
      const paidToMatch = message.match(/to A\/c\s+\d+-([A-Za-z\s]+?)(?:\s+on|\s+\.|$)/i);
      if (paidToMatch) {
        this.parsed.paid_to = paidToMatch[1].trim();
      }
    },
    
    parseMpesaDate(dateStr, timeStr) {
      // M-Pesa date format: DD/MM/YY
      const parts = dateStr.split('/');
      if (parts.length === 3) {
        let day = parts[0].padStart(2, '0');
        let month = parts[1].padStart(2, '0');
        let year = parts[2];
        
        // Handle 2-digit year
        if (year.length === 2) {
          year = '20' + year;
        }
        
        // Parse time
        let timeMatch = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
        if (timeMatch) {
          let hour = parseInt(timeMatch[1]);
          const minute = timeMatch[2];
          const ampm = timeMatch[3].toUpperCase();
          
          if (ampm === 'PM' && hour !== 12) hour += 12;
          if (ampm === 'AM' && hour === 12) hour = 0;
          
          const hourStr = String(hour).padStart(2, '0');
          const isoString = `${year}-${month}-${day}T${hourStr}:${minute}`;
          return isoString;
        }
      }
      return null;
    },
    
    parseBankDate(dateStr, timeStr) {
      // Bank date format: DD/MM/YYYY
      const parts = dateStr.split('/');
      if (parts.length === 3) {
        const day = parts[0].padStart(2, '0');
        const month = parts[1].padStart(2, '0');
        const year = parts[2];
        
        const isoString = `${year}-${month}-${day}T${timeStr}`;
        return isoString;
      }
      return null;
    },
    
    formatCurrency(amount) {
      const symbol = "{{ \App\Helpers\SystemHelper::currencySymbol() }}";
      if (!amount && amount !== 0) return symbol + " 0.00";
      return symbol + " " + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    
    validateForm() {
      this.formErrors = [];
      
      if (!this.form.transaction_message.trim()) {
        this.formErrors.push('Please paste your transaction message');
      }
      
      if (!this.form.amount || parseFloat(this.form.amount) <= 0) {
        this.formErrors.push('Please enter a valid amount');
      }
      
      if (!this.form.transaction_id.trim()) {
        this.formErrors.push('Transaction ID is required (extracted from message)');
      }
      
      if (!this.form.payment_method) {
        this.formErrors.push('Please select payment method');
      }
      
      if (!this.form.payment_datetime) {
        this.formErrors.push('Please select payment date');
      }
      
      if (!this.form.payment_month) {
        this.formErrors.push('Please select payment month');
      }
      
      return this.formErrors.length === 0;
    },
    
    async submitForm() {
        if (!this.validateForm()) {
            const modalContent = document.querySelector('.fixed.inset-y-0.right-0');
            if (modalContent) modalContent.scrollTop = 0;
            return;
        }
        
        this.loading = true;
        
        try {
            const response = await fetch('/payments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    tenancy_id: this.tenancyId,
                    invoice_id: this.form.invoice_id || null,
                    amount: this.form.amount,
                    payment_method: this.form.payment_method,
                    reference_number: this.form.transaction_id,  // This is the M-Pesa code/bank ref
                    transaction_message: this.form.transaction_message,
                    paid_to: this.form.paid_to,
                    payer_name: this.form.payer_name,
                    payment_datetime: this.form.payment_datetime,
                    payment_month: this.form.payment_month
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                this.closeModal();
                const message = this.isTenant 
                    ? 'Payment submitted for verification! An accountant will review and confirm your payment within 24 hours.'
                    : 'Payment recorded successfully!';
                alert(message);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                this.formErrors = [data.message || 'Failed to submit payment'];
                const modalContent = document.querySelector('.fixed.inset-y-0.right-0');
                if (modalContent) modalContent.scrollTop = 0;
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