@extends('layouts.app')

@section('content')
<!-- Include all modal partials from the correct path -->
@include('partials.modal.payments-create-modal')
@include('partials.modal.payments-delete-modal')

<div x-data="paymentsPage" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Tenant Payments
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Manage and track all tenant payments
        </p>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/>
            </svg>
          </span>
          <input 
            x-model="searchTerm" 
            @input.debounce.300ms="filterPayments()"
            type="text" 
            placeholder="Search payments..." 
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <div>
          <button 
            @click="window.paymentCreateModal?.openCreateModal()"
            class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 whitespace-nowrap text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Add Payment
          </button>
        </div>
      </div>
    </div>
    
    <div class="custom-scrollbar overflow-x-auto">
      <table class="w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            <th class="p-4 whitespace-nowrap">
              <p class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Payer</p>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Invoice #
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Unit
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Amount
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Payment Method
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Reference
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Status
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Payment Date
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              <div class="relative">
                <span class="sr-only">Actions</span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          <template x-if="filteredPayments.length === 0">
            <tr>
              <td colspan="9" class="p-4 text-center">
                <span class="text-sm text-gray-500 dark:text-gray-400">No payments found.</span>
              </td>
            </tr>
          </template>
          
          <template x-for="payment in filteredPayments" :key="payment.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap">
                <div>
                  <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="payment.tenant_name || payment.payer_name || 'N/A'"></span>
                  <p class="text-xs text-gray-400 dark:text-gray-500" x-show="payment.unit_number" x-text="'Unit: ' + payment.unit_number"></p>
                </div>
               </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-700 dark:text-gray-400" x-text="payment.invoice_label || '-'"></span>
               </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="payment.paid_to || '-'"></span>
               </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="formatCurrency(payment.amount)"></span>
               </td>
              <td class="p-4 whitespace-nowrap">
                <span class="bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-500 text-theme-xs rounded-full px-2 py-0.5 font-medium" x-text="payment.payment_method_label || capitalize(payment.payment_method)"></span>
               </td>
              <td class="p-4 whitespace-nowrap">
                <div>
                  <span class="text-xs font-mono text-gray-500 dark:text-gray-400" x-text="payment.transaction_reference || '-'"></span>
                  <p class="text-xs text-gray-400 dark:text-gray-500" x-show="payment.external_reference" x-text="'Ext: ' + payment.external_reference"></p>
                </div>
               </td>
              <td class="p-4 whitespace-nowrap">
                <span 
                  class="text-theme-xs rounded-full px-2 py-0.5 font-medium"
                  :class="payment.status_badge?.class || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                  x-text="payment.status_badge?.label || capitalize(payment.status)"
                ></span>
               </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-700 dark:text-gray-400" x-text="formatDate(payment.payment_datetime || payment.created_at)"></span>
               </td>
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <button 
                    @click="window.paymentShowModal?.openModal(payment)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                    View
                  </button>
                  <button 
                    @click="window.paymentCreateModal?.openEditModal(payment)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-yellow-500 hover:bg-yellow-50 hover:text-yellow-700 dark:text-yellow-400 dark:hover:bg-yellow-500/5 dark:hover:text-yellow-300"
                    :disabled="payment.status === 'refunded' || payment.status === 'cancelled'">
                    Edit
                  </button>
                  <button 
                    @click="window.paymentDeleteModal?.openModal(payment)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-red-500 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/5 dark:hover:text-red-300"
                    :disabled="payment.status === 'refunded'">
                    Delete
                  </button>
                </div>
               </tr>
             </tr>
          </template>
        </tbody>
       </table>
    </div>
    
    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
      <div class="flex items-center justify-between">
        <div>
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Total of
            <span class="text-gray-800 dark:text-white/90" x-text="filteredPayments.length"></span>
            payments
          </span>
        </div>
        <div>
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Total Amount:
            <span class="text-gray-800 dark:text-white/90 font-semibold" x-text="formatCurrency(totalAmount)"></span>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('paymentsPage', () => ({
    payments: @json($paymentsData),
    searchTerm: '',
    filteredPayments: [],
    
    get totalAmount() {
      return this.filteredPayments.reduce((sum, payment) => sum + parseFloat(payment.amount || 0), 0);
    },
    
    init() {
      this.filteredPayments = this.payments;
    },
    
    filterPayments() {
      if (!this.searchTerm.trim()) {
        this.filteredPayments = this.payments;
        return;
      }
      
      const term = this.searchTerm.toLowerCase();
      this.filteredPayments = this.payments.filter(payment => {
        return (
          (payment.tenant_name && payment.tenant_name.toLowerCase().includes(term)) ||
          (payment.payer_name && payment.payer_name.toLowerCase().includes(term)) ||
          (payment.invoice_number && payment.invoice_number.toLowerCase().includes(term)) ||
          (payment.unit_number && payment.unit_number.toLowerCase().includes(term)) ||
          (payment.transaction_reference && payment.transaction_reference.toLowerCase().includes(term)) ||
          (payment.payment_method && payment.payment_method.toLowerCase().includes(term)) ||
          (payment.status && payment.status.toLowerCase().includes(term))
        );
      });
    },
    
    formatCurrency(amount) {
      const symbol = "{{ SystemHelper::currencySymbol() ?? 'KES ' }}";
      if (!amount && amount !== 0) return symbol + "0.00";
      return symbol + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    
    capitalize(text) {
      if (!text) return '';
      return text.charAt(0).toUpperCase() + text.slice(1).replace(/_/g, ' ');
    }
  }));
});
</script>
@endsection

@push('styles')
<style>
[x-cloak] {
  display: none !important;
}

.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: #9ca3af #f3f4f6;
}

.custom-scrollbar::-webkit-scrollbar {
  height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #9ca3af;
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

.dark .custom-scrollbar {
  scrollbar-color: #4b5563 #1f2937;
}

.dark .custom-scrollbar::-webkit-scrollbar-track {
  background: #1f2937;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: #4b5563;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

/* Z-index utilities */
.z-99998 {
  z-index: 99998 !important;
}

.z-99999 {
  z-index: 99999 !important;
}

/* Backdrop blur */
.backdrop-blur-\[32px\] {
  backdrop-filter: blur(32px);
}

/* Slideover transitions */
.translate-x-full {
  transform: translateX(100%);
}

.translate-x-0 {
  transform: translateX(0);
}

/* Scale transitions for centered modals */
.scale-95 {
  transform: scale(0.95);
}

.scale-100 {
  transform: scale(1);
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
@endpush