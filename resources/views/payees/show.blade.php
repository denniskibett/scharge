@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.payees-edit-modal')
@include('partials.modal.payees-delete-modal')

<div x-data="payeeShow" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Payee Details
        </h3>
        <div class="flex items-center gap-2 mt-2">
          <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            ID: #{{ $payee->id }}
          </span>
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
            @if($payee->type == 'staff') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
            @elseif($payee->type == 'vendor') bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400
            @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 @endif">
            {{ ucfirst($payee->type) }}
          </span>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <button 
          @click="openEditModal()"
          class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 whitespace-nowrap text-yellow-700 transition-colors hover:bg-yellow-100 hover:text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400 dark:hover:bg-yellow-900/30 dark:hover:text-yellow-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="text-yellow-600 dark:text-yellow-400">
            <path d="M13.5858 3.58579C14.3668 2.80474 15.6332 2.80474 16.4142 3.58579C17.1953 4.36684 17.1953 5.63317 16.4142 6.41421L15.6213 7.20711L12.7929 4.37868L13.5858 3.58579Z" fill="currentColor"/>
            <path d="M11.3787 5.79289L3 14.1716V17H5.82842L14.2071 8.62132L11.3787 5.79289Z" fill="currentColor"/>
          </svg>
          Edit Payee
        </button>
        
        <button 
          @click="openDeleteModal()"
          class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 whitespace-nowrap text-red-700 transition-colors hover:bg-red-100 hover:text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30 dark:hover:text-red-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="text-red-600 dark:text-red-400">
            <path d="M8.75 4.25C8.75 3.83579 9.08579 3.5 9.5 3.5H10.5C10.9142 3.5 11.25 3.83579 11.25 4.25V4.75H8.75V4.25Z" fill="currentColor"/>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M6 5.75V15.25C6 16.3546 6.89543 17.25 8 17.25H12C13.1046 17.25 14 16.3546 14 15.25V5.75H6ZM8.75 7.25C8.75 6.83579 9.08579 6.5 9.5 6.5H10.5C10.9142 6.5 11.25 6.83579 11.25 7.25V13.25C11.25 13.6642 10.9142 14 10.5 14H9.5C9.08579 14 8.75 13.6642 8.75 13.25V7.25Z" fill="currentColor"/>
            <path d="M5.25 5H14.75V4.25C14.75 3.2835 13.9665 2.5 13 2.5H7C6.0335 2.5 5.25 3.2835 5.25 4.25V5Z" fill="currentColor"/>
          </svg>
          Delete Payee
        </button>
      </div>
    </div>

    <!-- Payee Details -->
    <div class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
          <!-- Basic Information -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-500">
                <path d="M8 8C9.933 8 11.5 6.433 11.5 4.5C11.5 2.567 9.933 1 8 1C6.067 1 4.5 2.567 4.5 4.5C4.5 6.433 6.067 8 8 8Z" fill="currentColor"/>
                <path d="M8 9.5C5.9975 9.5 1 10.5025 1 12.5V14.5C1 14.7761 1.22386 15 1.5 15H14.5C14.7761 15 15 14.7761 15 14.5V12.5C15 10.5025 10.0025 9.5 8 9.5Z" fill="currentColor"/>
              </svg>
              Basic Information
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Name</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payee->name }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Type</span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                  @if($payee->type == 'staff') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                  @elseif($payee->type == 'vendor') bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400
                  @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 @endif">
                  {{ ucfirst($payee->type) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Contact Information -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-green-500">
                <path d="M2.5 2H5.5L6.5 5L4.7 6.3C5.6 8.1 7.1 9.6 8.9 10.5L10.2 8.7L13 9.7V12.7C13 13.2 12.6 13.6 12.1 13.6C6.7 13.6 2.2 9.1 2.2 3.7C2.2 3.2 2.6 2.8 3.1 2.8H2.5V2Z" fill="currentColor"/>
              </svg>
              Contact Information
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Phone</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payee->phone ?? '-' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Email</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payee->email ?? '-' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
          <!-- Statistics -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-purple-500">
                <path d="M2 2H4V14H2V2Z" fill="currentColor"/>
                <path d="M6 6H8V14H6V6Z" fill="currentColor"/>
                <path d="M10 9H12V14H10V9Z" fill="currentColor"/>
              </svg>
              Statistics
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Expenses</span>
                <span class="text-lg font-semibold text-purple-600 dark:text-purple-400">{{ $payee->expenses->count() }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Amount</span>
                <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                  {{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($payee->expenses->sum('amount'), 2) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Timestamps -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-500">
                <path d="M8 0C3.58172 0 0 3.58172 0 8C0 12.4183 3.58172 16 8 16C12.4183 16 16 12.4183 16 8C16 3.58172 12.4183 0 8 0Z" fill="currentColor"/>
                <path d="M8 4V8L10 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Timestamps
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Created At</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payee->created_at ? \Carbon\Carbon::parse($payee->created_at)->format('M d, Y H:i') : '-' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Updated At</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $payee->updated_at ? \Carbon\Carbon::parse($payee->updated_at)->format('M d, Y H:i') : '-' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Expenses List -->
      @if($payee->expenses->count() > 0)
      <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
        <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-purple-500">
            <path d="M13 1H3C1.89543 1 1 1.89543 1 3V13C1 14.1046 1.89543 15 3 15H13C14.1046 15 15 14.1046 15 13V3C15 1.89543 14.1046 1 13 1Z" fill="currentColor"/>
            <path d="M4 5H12V6H4V5Z" fill="white"/>
            <path d="M4 8H12V9H4V8Z" fill="white"/>
            <path d="M4 11H8V12H4V11Z" fill="white"/>
          </svg>
          Expenses
        </h4>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estate</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              @foreach($payee->expenses as $expense)
              <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $expense->estate->name }}</td>
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $expense->category->name }}</td>
                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($expense->amount, 2) }}</td>
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                    @if($expense->status == 'paid') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                    @elseif($expense->status == 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                    @else bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 @endif">
                    {{ ucfirst($expense->status) }}
                  </span>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('payeeShow', () => ({
    init() {
      console.log('Payee show page loaded');
    },

    getPayeeData() {
      return {
        id: {{ $payee->id }},
        name: '{{ $payee->name }}',
        type: '{{ $payee->type }}',
        phone: '{{ $payee->phone }}',
        email: '{{ $payee->email }}',
        expenses_count: {{ $payee->expenses->count() }},
        created_at: '{{ $payee->created_at }}',
        updated_at: '{{ $payee->updated_at }}'
      };
    },

    openEditModal() {
      if (window.payeeEditModal) {
        window.payeeEditModal.openModal(this.getPayeeData());
      }
    },

    openDeleteModal() {
      if (window.payeeDeleteModal) {
        window.payeeDeleteModal.openModal(this.getPayeeData());
      }
    }
  }));
});
</script>
@endsection