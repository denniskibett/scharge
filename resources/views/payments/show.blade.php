@extends('layouts.app')

@section('content')
<div x-data="paymentShow" x-init="init()">
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Payment Details
        </h3>
        <div class="flex items-center gap-2 mt-2">
          <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            ID: #{{ $payment->id }}
          </span>
          <span :class="getStatusColor()" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium">
            {{ ucfirst($payment->payment_method) }}
          </span>
          <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-900/20 dark:text-green-400">
            {{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($payment->amount, 2) }}
          </span>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <a href="{{ route('payments.edit', $payment) }}" 
           class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 whitespace-nowrap text-yellow-700 transition-colors hover:bg-yellow-100 hover:text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400 dark:hover:bg-yellow-900/30 dark:hover:text-yellow-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="text-yellow-600 dark:text-yellow-400">
            <path d="M13.5858 3.58579C14.3668 2.80474 15.6332 2.80474 16.4142 3.58579C17.1953 4.36684 17.1953 5.63317 16.4142 6.41421L15.6213 7.20711L12.7929 4.37868L13.5858 3.58579Z" fill="currentColor"/>
            <path d="M11.3787 5.79289L3 14.1716V17H5.82842L14.2071 8.62132L11.3787 5.79289Z" fill="currentColor"/>
          </svg>
          Edit Payment
        </a>
        
        <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="inline">
          @csrf @method('DELETE')
          <button type="submit" 
                  @click.prevent="confirmDelete()"
                  class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 whitespace-nowrap text-red-700 transition-colors hover:bg-red-100 hover:text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30 dark:hover:text-red-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="text-red-600 dark:text-red-400">
              <path d="M8.75 4.25C8.75 3.83579 9.08579 3.5 9.5 3.5H10.5C10.9142 3.5 11.25 3.83579 11.25 4.25V4.75H8.75V4.25Z" fill="currentColor"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M6 5.75V15.25C6 16.3546 6.89543 17.25 8 17.25H12C13.1046 17.25 14 16.3546 14 15.25V5.75H6ZM8.75 7.25C8.75 6.83579 9.08579 6.5 9.5 6.5H10.5C10.9142 6.5 11.25 6.83579 11.25 7.25V13.25C11.25 13.6642 10.9142 14 10.5 14H9.5C9.08579 14 8.75 13.6642 8.75 13.25V7.25Z" fill="currentColor"/>
              <path d="M5.25 5H14.75V4.25C14.75 3.2835 13.9665 2.5 13 2.5H7C6.0335 2.5 5.25 3.2835 5.25 4.25V5Z" fill="currentColor"/>
            </svg>
            Delete Payment
          </button>
        </form>
      </div>
    </div>

    <!-- Payment Details -->
    <div class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
          <!-- Payer Information -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-500">
                <path d="M8 8C9.933 8 11.5 6.433 11.5 4.5C11.5 2.567 9.933 1 8 1C6.067 1 4.5 2.567 4.5 4.5C4.5 6.433 6.067 8 8 8Z" fill="currentColor"/>
                <path d="M8 9.5C5.9975 9.5 1 10.5025 1 12.5V14.5C1 14.7761 1.22386 15 1.5 15H14.5C14.7761 15 15 14.7761 15 14.5V12.5C15 10.5025 10.0025 9.5 8 9.5Z" fill="currentColor"/>
              </svg>
              Payer Information
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Payer Name</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payment->payer_name ?? 'N/A' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Tenant</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ optional($payment->payer)->name ?? 'N/A' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Tenancy</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ optional($payment->tenancy)->id ? 'Tenancy #' . $payment->tenancy->id : 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Payment Information -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-green-500">
                <path d="M14.5 4H1.5C0.671573 4 0 4.67157 0 5.5V10.5C0 11.3284 0.671573 12 1.5 12H14.5C15.3284 12 16 11.3284 16 10.5V5.5C16 4.67157 15.3284 4 14.5 4Z" fill="currentColor"/>
                <path d="M2 6H14V7H2V6Z" fill="white"/>
              </svg>
              Payment Information
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Amount</span>
                <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                  {{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($payment->amount, 2) }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Payment Method</span>
                <span :class="getMethodColor()" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium">
                  {{ ucfirst($payment->payment_method) }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Paid To</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payment->paid_to ?? '-' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
          <!-- Invoice Information -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-purple-500">
                <path d="M14 2H2C1.44772 2 1 2.44772 1 3V13C1 13.5523 1.44772 14 2 14H14C14.5523 14 15 13.5523 15 13V3C15 2.44772 14.5523 2 14 2Z" fill="currentColor"/>
                <path d="M4 5H12V6H4V5Z" fill="white"/>
                <path d="M4 8H12V9H4V8Z" fill="white"/>
                <path d="M4 11H8V12H4V11Z" fill="white"/>
              </svg>
              Invoice Information
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Invoice Number</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">
                  @if($payment->invoice)
                    #{{ $payment->invoice->invoice_number ?? $payment->invoice->id }}
                  @else
                    -
                  @endif
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Invoice Status</span>
                @if($payment->invoice)
                  <span :class="getInvoiceStatusColor()" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium">
                    {{ ucfirst($payment->invoice->status) }}
                  </span>
                @else
                  <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                @endif
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Invoice Amount</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">
                  @if($payment->invoice)
                    {{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($payment->invoice->total_amount ?? 0, 2) }}
                  @else
                    -
                  @endif
                </span>
              </div>
            </div>
          </div>

          <!-- Transaction Details -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-500">
                <path d="M8 0C3.58172 0 0 3.58172 0 8C0 12.4183 3.58172 16 8 16C12.4183 16 16 12.4183 16 8C16 3.58172 12.4183 0 8 0Z" fill="currentColor"/>
                <path d="M8 4V8L10 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Transaction Details
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Transaction ID</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90 font-mono">{{ $payment->transaction_id ?? '-' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Payment Date</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatDate('{{ $payment->payment_datetime }}')"></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Payment Month</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payment->payment_month }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Transaction Message -->
      @if($payment->transaction_message)
      <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
        <h4 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-gray-500">
            <path d="M14 2H2C0.89543 2 0 2.89543 0 4V12C0 13.1046 0.89543 14 2 14H14C15.1046 14 16 13.1046 16 12V4C16 2.89543 15.1046 2 14 2Z" fill="currentColor"/>
            <path d="M3 5H13V6H3V5Z" fill="white"/>
            <path d="M3 8H10V9H3V8Z" fill="white"/>
          </svg>
          Transaction Message
        </h4>
        <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
          {{ $payment->transaction_message }}
        </p>
      </div>
      @endif

      <!-- Invoice Items (if available) -->
      @if($payment->invoice && $payment->invoice->items->count() > 0)
      <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
        <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-purple-500">
            <path d="M13 1H3C1.89543 1 1 1.89543 1 3V13C1 14.1046 1.89543 15 3 15H13C14.1046 15 15 14.1046 15 13V3C15 1.89543 14.1046 1 13 1Z" fill="currentColor"/>
            <path d="M4 5H12V6H4V5Z" fill="white"/>
            <path d="M4 8H12V9H4V8Z" fill="white"/>
            <path d="M4 11H8V12H4V11Z" fill="white"/>
          </svg>
          Invoice Items
        </h4>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              @foreach($payment->invoice->items as $item)
              <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">
                  <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                    {{ ucfirst($item->item_type) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $item->description ?? '-' }}</td>
                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white/90">
                  {{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($item->amount, 2) }}
                </td>
              </tr>
              @endforeach
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white/90 text-right">Total:</td>
                <td class="px-4 py-3 text-sm font-semibold text-gray-800 dark:text-white/90">
                  {{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($payment->invoice->items->sum('amount'), 2) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      @endif

      <!-- Timestamps -->
      <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Created At</p>
          <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatDate('{{ $payment->created_at }}')"></p>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Updated At</p>
          <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatDate('{{ $payment->updated_at }}')"></p>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Payment Age</p>
          <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="calculatePaymentAge()"></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('paymentShow', () => ({
    init() {
      console.log('Payment show page loaded');
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

    calculatePaymentAge() {
      const paymentDate = new Date('{{ $payment->payment_datetime }}');
      const now = new Date();
      const diffTime = Math.abs(now - paymentDate);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if (diffDays === 0) return 'Today';
      if (diffDays === 1) return 'Yesterday';
      if (diffDays < 30) return `${diffDays} days ago`;
      if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
      return `${Math.floor(diffDays / 365)} years ago`;
    },

    getStatusColor() {
      const method = '{{ $payment->payment_method }}';
      switch(method) {
        case 'mpesa':
          return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
        case 'bank':
          return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'cash':
          return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
        default:
          return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
      }
    },

    getMethodColor() {
      const method = '{{ $payment->payment_method }}';
      switch(method) {
        case 'mpesa':
          return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
        case 'bank':
          return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'cash':
          return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
        default:
          return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
      }
    },

    getInvoiceStatusColor() {
      const status = '{{ $payment->invoice->status ?? "" }}';
      switch(status) {
        case 'paid':
          return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
        case 'unpaid':
          return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
        case 'partial':
          return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
        case 'draft':
          return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
        default:
          return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
      }
    },

    confirmDelete() {
      const paymentId = '{{ $payment->id }}';
      const payerName = '{{ $payment->payer_name ?? "N/A" }}';
      const amount = '{{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($payment->amount, 2) }}';
      
      if (confirm(`Are you sure you want to delete payment #${paymentId} from ${payerName} (${amount})? This action cannot be undone.`)) {
        // Submit the form
        const form = document.querySelector('form[action*="/payments/' + paymentId + '"]');
        if (form) {
          form.submit();
        }
      }
    },

    printDetails() {
      window.print();
    },

    copyTransactionId() {
      const transactionId = '{{ $payment->transaction_id }}';
      if (transactionId && transactionId !== '-') {
        navigator.clipboard.writeText(transactionId).then(() => {
          // Show success message (you could add a toast notification here)
          alert('Transaction ID copied to clipboard!');
        });
      }
    }
  }));
});
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }
  
  body {
    background: white !important;
    color: black !important;
  }
  
  .border, .card, .rounded-lg {
    border: 1px solid #ddd !important;
  }
  
  .bg-gray-50, .bg-white, .bg-gray-100 {
    background: white !important;
  }
  
  .text-gray-800, .text-gray-700, .text-gray-900 {
    color: black !important;
  }
}

/* Custom scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
  height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #555;
}

.dark .overflow-x-auto::-webkit-scrollbar-track {
  background: #374151;
}

.dark .overflow-x-auto::-webkit-scrollbar-thumb {
  background: #6b7280;
}

.dark .overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}
</style>
@endsection