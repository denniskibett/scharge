@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.expenses-edit-modal')
@include('partials.modal.expenses-delete-modal')
@include('partials.modal.expense-payments-create-modal')
@include('partials.modal.expense-payments-edit-modal')
@include('partials.modal.expense-payments-delete-modal')

<div x-data="expenseShow" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Expense Details
        </h3>
        <div class="flex items-center gap-2 mt-2">
          <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            ID: #{{ $expense->id }}
          </span>
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
            @if($expense->status == 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
            @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 @endif">
            {{ ucfirst($expense->status) }}
          </span>
          <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
            {{ $expense->category->name }}
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
          Edit Expense
        </button>
        
        <button 
          @click="openDeleteModal()"
          class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 whitespace-nowrap text-red-700 transition-colors hover:bg-red-100 hover:text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30 dark:hover:text-red-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="text-red-600 dark:text-red-400">
            <path d="M8.75 4.25C8.75 3.83579 9.08579 3.5 9.5 3.5H10.5C10.9142 3.5 11.25 3.83579 11.25 4.25V4.75H8.75V4.25Z" fill="currentColor"/>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M6 5.75V15.25C6 16.3546 6.89543 17.25 8 17.25H12C13.1046 17.25 14 16.3546 14 15.25V5.75H6ZM8.75 7.25C8.75 6.83579 9.08579 6.5 9.5 6.5H10.5C10.9142 6.5 11.25 6.83579 11.25 7.25V13.25C11.25 13.6642 10.9142 14 10.5 14H9.5C9.08579 14 8.75 13.6642 8.75 13.25V7.25Z" fill="currentColor"/>
            <path d="M5.25 5H14.75V4.25C14.75 3.2835 13.9665 2.5 13 2.5H7C6.0335 2.5 5.25 3.2835 5.25 4.25V5Z" fill="currentColor"/>
          </svg>
          Delete Expense
        </button>
      </div>
    </div>

    <!-- Expense Details -->
    <div class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Details -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-500">
                <path d="M2 2H14V4H2V2Z" fill="currentColor"/>
                <path d="M2 6H14V8H2V6Z" fill="currentColor"/>
                <path d="M2 10H14V12H2V10Z" fill="currentColor"/>
                <path d="M2 14H10V16H2V14Z" fill="currentColor"/>
              </svg>
              Expense Information
            </h4>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Estate</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $expense->estate->name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Payee</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $expense->payee->name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Category</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $expense->category->name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Amount</p>
                <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ SystemHelper::currencySymbol() }}{{ number_format($expense->amount, 2) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Date</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                  @if($expense->status == 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                  @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 @endif">
                  {{ ucfirst($expense->status) }}
                </span>
              </div>
            </div>
            @if($expense->description)
            <div class="mt-4">
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Description</p>
              <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                {{ $expense->description }}
              </p>
            </div>
            @endif
          </div>

          <!-- Payments List -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-purple-500">
                  <path d="M14.5 4H1.5C0.671573 4 0 4.67157 0 5.5V10.5C0 11.3284 0.671573 12 1.5 12H14.5C15.3284 12 16 11.3284 16 10.5V5.5C16 4.67157 15.3284 4 14.5 4Z" fill="currentColor"/>
                  <path d="M2 6H14V7H2V6Z" fill="white"/>
                </svg>
                Payments
              </h4>
              <button 
                @click="window.expensePaymentCreateModal?.openModal(getExpenseData())"
                class="inline-flex items-center gap-1 rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="none">
                  <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Add Payment
              </button>
            </div>
            
            @if($expense->payments->count() > 0)
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Paid By</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaction ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                  @foreach($expense->payments as $payment)
                  <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="px-4 py-3 text-sm font-medium text-green-600 dark:text-green-400">{{ SystemHelper::currencySymbol() }}{{ number_format($payment->amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ strtoupper($payment->payment_method) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ $payment->paid_by }}</td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90 font-mono">{{ $payment->transaction_id ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">{{ \Carbon\Carbon::parse($payment->payment_datetime)->format('M d, Y H:i') }}</td>
                    <td class="px-4 py-3 text-sm">
                      <div class="flex items-center gap-2">
                        <button 
                          @click="window.expensePaymentEditModal?.openModal(@json([
                              'id' => $payment->id,
                              'expense_id' => $expense->id,
                              'amount' => $payment->amount,
                              'payment_method' => $payment->payment_method,
                              'paid_by' => $payment->paid_by,
                              'transaction_id' => $payment->transaction_id,
                              'transaction_message' => $payment->transaction_message,
                              'payment_datetime' => $payment->payment_datetime
                          ]))"
                          class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                          </svg>
                        </button>
                        <button 
                          @click="window.expensePaymentDeleteModal?.openModal(@json([
                              'id' => $payment->id,
                              'expense_id' => $expense->id,
                              'amount' => $payment->amount
                          ]))"
                          class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @else
            <div class="text-center py-8">
              <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              <p class="text-sm text-gray-500 dark:text-gray-400">No payments yet</p>
            </div>
            @endif
          </div>
        </div>

        <!-- Right Column - Summary -->
        <div class="space-y-6">
          <!-- Payment Summary -->
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-green-500">
                <path d="M2 2H4V14H2V2Z" fill="currentColor"/>
                <path d="M6 6H8V14H6V6Z" fill="currentColor"/>
                <path d="M10 9H12V14H10V9Z" fill="currentColor"/>
              </svg>
              Payment Summary
            </h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Expense</span>
                <span class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }}{{ number_format($expense->amount, 2) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Paid Amount</span>
                <span class="text-lg font-semibold text-green-600 dark:text-green-400">{{ SystemHelper::currencySymbol() }}{{ number_format($expense->payments->sum('amount'), 2) }}</span>
              </div>
              <div class="border-t border-gray-200 dark:border-gray-700 my-2 pt-2">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Remaining</span>
                  <span class="text-lg font-semibold {{ ($expense->amount - $expense->payments->sum('amount')) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                    {{ SystemHelper::currencySymbol() }}{{ number_format($expense->amount - $expense->payments->sum('amount'), 2) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Progress Bar -->
          @php
            $paidPercentage = $expense->amount > 0 ? ($expense->payments->sum('amount') / $expense->amount) * 100 : 0;
          @endphp
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-500">
                <path d="M1 8C1 4.13401 4.13401 1 8 1C11.866 1 15 4.13401 15 8C15 11.866 11.866 15 8 15C4.13401 15 1 11.866 1 8Z" fill="currentColor"/>
                <path d="M8 4V8L11 11" stroke="white" stroke-width="2"/>
              </svg>
              Payment Progress
            </h4>
            <div class="space-y-2">
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Progress</span>
                <span class="font-medium text-gray-800 dark:text-white/90">{{ number_format($paidPercentage, 1) }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div class="bg-green-600 h-2.5 rounded-full dark:bg-green-500" style="width: {{ $paidPercentage }}%"></div>
              </div>
              <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ $expense->payments->count() }} payments</span>
                <span>{{ $expense->payments->count() }} transaction{{ $expense->payments->count() != 1 ? 's' : '' }}</span>
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
                <span class="text-sm text-gray-500 dark:text-gray-400">Created</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $expense->created_at ? \Carbon\Carbon::parse($expense->created_at)->format('M d, Y H:i') : '-' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Updated</span>
                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $expense->updated_at ? \Carbon\Carbon::parse($expense->updated_at)->format('M d, Y H:i') : '-' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('expenseShow', () => ({
    init() {
      console.log('Expense show page loaded');
    },

    getExpenseData() {
      return {
        id: {{ $expense->id }},
        estate_id: {{ $expense->estate_id }},
        payee_id: {{ $expense->payee_id }},
        expense_category_id: {{ $expense->expense_category_id }},
        amount: {{ $expense->amount }},
        expense_date: '{{ $expense->expense_date }}',
        status: '{{ $expense->status }}',
        description: '{{ addslashes($expense->description) }}'
      };
    },

    openEditModal() {
      if (window.expenseEditModal) {
        window.expenseEditModal.openModal(this.getExpenseData());
      }
    },

    openDeleteModal() {
      if (window.expenseDeleteModal) {
        window.expenseDeleteModal.openModal(this.getExpenseData());
      }
    }
  }));
});
</script>
@endsection