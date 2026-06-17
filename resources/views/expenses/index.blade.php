@extends('layouts.app')

@section('content')
<!-- Include all modal partials -->
@include('partials.modal.expenses-create-modal')
@include('partials.modal.expenses-edit-modal')
@include('partials.modal.expenses-show-modal')
@include('partials.modal.expenses-delete-modal')
@include('partials.modal.categories-create-modal')

<div x-data="expensesPage" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Expenses
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Manage all property expenses
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
            @input.debounce.300ms="filterExpenses()"
            type="text" 
            placeholder="Search expenses..." 
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <div class="flex gap-2">
          <button 
            @click="window.expenseCreateModal?.openModal()"
            class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 whitespace-nowrap text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Add Expense
          </button>
          <button 
            @click="window.categoryCreateModal?.openModal()"
            class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 whitespace-nowrap text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M4 4H16V6H4V4Z" fill="currentColor"/>
              <path d="M4 8H16V10H4V8Z" fill="currentColor"/>
              <path d="M10 12H16V14H10V12Z" fill="currentColor"/>
            </svg>
            Add Category
          </button>
        </div>
      </div>
    </div>
    
    <div class="custom-scrollbar overflow-x-auto">
      <table class="w-full table-auto">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr class="border-b border-gray-200 dark:border-gray-700">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estate</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payee</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payments</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <template x-if="filteredExpenses.length === 0">
            <tr>
              <td colspan="8" class="px-4 py-8 text-center">
                <div class="flex flex-col items-center justify-center">
                  <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <span class="text-sm text-gray-500 dark:text-gray-400">No expenses found.</span>
                </div>
              </td>
            </tr>
          </template>
          
          <template x-for="expense in filteredExpenses" :key="expense.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800">
              <!-- Estate - FIXED -->
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-white/90" x-text="expense.estate?.name || 'N/A'"></td>
              
              <!-- Payee - FIXED -->
              <td class="px-4 py-3 whitespace-nowrap">
                <a
                  href="javascript:void(0)"
                  @click="window.payeeShowModal?.openModal({id: expense.payee_id, name: expense.payee?.name, type: expense.payee?.type})"
                  class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                  x-text="expense.payee?.name || 'N/A'"
                ></a>
              </td>
              
              <!-- Category - FIXED -->
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-white/90" x-text="expense.category?.name || 'N/A'"></td>
              
              <!-- Amount -->
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatCurrency(expense.amount)"></td>
              
              <!-- Date -->
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-white/90" x-text="formatDate(expense.expense_date)"></td>
              
              <!-- Status -->
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="{
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400': expense.status === 'pending',
                        'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400': expense.status === 'paid'
                      }">
                  <span x-text="expense.status.charAt(0).toUpperCase() + expense.status.slice(1)"></span>
                </span>
              </td>
              
              <!-- Payments -->
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300" x-text="expense.payments?.length || 0"></span>
              </td>
              
              <!-- Actions -->
              <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <button 
                    @click="window.expenseShowModal?.openModal(expense)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View
                  </button>
                  <button 
                    @click="window.expenseEditModal?.openModal(expense)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-yellow-500 hover:bg-yellow-50 hover:text-yellow-700 dark:text-yellow-400 dark:hover:bg-yellow-500/5 dark:hover:text-yellow-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                  </button>
                  <button 
                    @click="window.expenseDeleteModal?.openModal(expense)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-red-500 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/5 dark:hover:text-red-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                  </button>
                </div>
              </td>
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
            <span class="text-gray-800 dark:text-white/90" x-text="filteredExpenses.length"></span>
            expenses
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
  Alpine.data('expensesPage', () => ({
    expenses: @json($expenses),
    searchTerm: '',
    filteredExpenses: [],
    
    init() {
      this.filteredExpenses = this.expenses;
    },
    
    filterExpenses() {
      if (!this.searchTerm.trim()) {
        this.filteredExpenses = this.expenses;
        return;
      }
      
      const term = this.searchTerm.toLowerCase();
      this.filteredExpenses = this.expenses.filter(expense => {
        return (expense.estate?.name?.toLowerCase().includes(term) ||
                expense.payee?.name?.toLowerCase().includes(term) ||
                expense.category?.name?.toLowerCase().includes(term) ||
                expense.status?.toLowerCase().includes(term) ||
                expense.amount?.toString().includes(term));
      });
    },
    
    get totalAmount() {
      return this.filteredExpenses.reduce((sum, expense) => sum + (expense.amount || 0), 0);
    },
    
    formatCurrency(amount) {
      const symbol = "{{ SystemHelper::currencySymbol() }} ";
      if (!amount) return symbol + "0.00";
      return symbol + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
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
</style>
@endpush