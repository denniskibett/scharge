{{-- resources/views/tenant/wallet.blade.php --}}
@extends('layouts.app')

@section('content')
<div>
    <div>
        {{-- Wallet Component --}}
        <div x-data="tenantWalletComponent()" x-init="init()" class="space-y-6">
            
            {{-- Balance Card --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Available Balance</p>
                            <h2 class="text-4xl font-bold text-gray-900 dark:text-white mt-1">
                                KES <span x-text="formatNumber(balance)"></span>
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Wallet ID: <span class="font-mono" x-text="walletId"></span>
                            </p>
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            <button @click="openDepositModal()" 
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Deposit
                            </button>
                            
                            <button @click="openWithdrawModal()" 
                                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                </svg>
                                Withdraw
                            </button>
                            
                            <button @click="openTransferModal()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                Transfer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Pending Invoices Section --}}
            <div x-show="pendingInvoices.length > 0" x-cloak
                class="bg-yellow-50 dark:bg-yellow-900/20 rounded-2xl border border-yellow-200 dark:border-yellow-800 overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-400 mb-4">Pending Payments</h3>
                    <div class="space-y-3">
                        <template x-for="invoice in pendingInvoices" :key="invoice.id">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 bg-white dark:bg-gray-800 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white" x-text="'Invoice #' + invoice.id"></p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400" x-text="'Due: ' + formatMonth(invoice.billing_month)"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-gray-900 dark:text-white" x-text="'KES ' + formatNumber(invoice.remaining_amount)"></p>
                                    <button @click="payInvoice(invoice.id, invoice.remaining_amount)" 
                                        class="mt-1 text-sm text-green-600 hover:text-green-700 font-medium">
                                        Pay Now →
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            
            {{-- Transaction History --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Transaction History</h3>
                        
                        {{-- Filters --}}
                        <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                            <select x-model="filterType" @change="loadTransactions()"
                                class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="all">All Types</option>
                                <option value="deposit">Deposits</option>
                                <option value="withdraw">Withdrawals</option>
                            </select>
                            
                            <input type="date" x-model="filterFrom" @change="loadTransactions()"
                                class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            
                            <input type="date" x-model="filterTo" @change="loadTransactions()"
                                class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            
                            <button @click="exportTransactions()" 
                                class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Export
                            </button>
                        </div>
                </div>
                
                <div class="w-full overflow-x-auto">
                    <table class="data-table w-full table-auto">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Date</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Type</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Description</th>
                                <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="tx in transactions" :key="tx.id">
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="formatDate(tx.created_at)"></td>
                                    <td class="p-4 whitespace-nowrap">
                                        <span x-show="tx.type === 'deposit'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Deposit</span>
                                        <span x-show="tx.type === 'withdraw'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Withdrawal</span>
                                        <span x-show="tx.is_pending" class="ml-1 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Pending</span>
                                    </td>
                                    <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <span x-text="tx.meta?.description || tx.description || '—'"></span>
                                        <span x-show="tx.is_pending" class="block text-xs text-yellow-600 dark:text-yellow-400">Awaiting approval</span>
                                        <span x-show="tx.notes" class="block text-xs text-gray-500 dark:text-gray-400" x-text="tx.notes"></span>
                                    </td>
                                    <td class="p-4 whitespace-nowrap text-right">
                                        <span x-show="tx.type === 'deposit'" class="text-green-600 dark:text-green-400 font-medium">
                                            + KES <span x-text="formatNumber(tx.amount)"></span>
                                        </span>
                                        <span x-show="tx.type !== 'deposit'" class="text-red-600 dark:text-red-400 font-medium">
                                            - KES <span x-text="formatNumber(tx.amount)"></span>
                                        </span>
                                        <span x-show="tx.is_pending" class="block text-xs text-yellow-600 dark:text-yellow-400">(Pending)</span>
                                    </td>
                                </tr>
                            </template>
                            
                            <tr x-show="transactions.length === 0">
                                <td colspan="4" class="p-4 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No transactions found</h3>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                {{-- Pagination --}}
                <div x-show="transactions.length > 0" class="px-5 py-4 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing <span x-text="((currentPage - 1) * perPage) + 1"></span> to 
                            <span x-text="Math.min(currentPage * perPage, totalCount)"></span> of 
                            <span x-text="totalCount"></span> entries
                        </p>
                        <div class="flex gap-2">
                            <button @click="prevPage()" :disabled="currentPage === 1"
                                class="shadow-theme-xs rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                Previous
                            </button>
                            <span class="px-3 py-1 text-sm" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
                            <button @click="nextPage()" :disabled="currentPage === totalPages"
                                class="shadow-theme-xs rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    
    {{-- Deposit Modal --}}
    <div x-show="showDepositModal" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="showDepositModal = false"
         class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" style="display: none;"></div>
    <div x-show="showDepositModal" x-cloak
         x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
         style="display: none; width: 32rem; max-width: calc(100% - 2rem);">
      <div class="p-6 lg:p-10">
        <button type="button" @click="showDepositModal = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
          <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/></svg>
        </button>
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Deposit Funds</h4>
                <form @submit.prevent="submitDeposit">
                    @csrf
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount (KES)</label>
                        <input type="number" x-model="depositAmount" step="1" min="1" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method</label>
                        <select x-model="depositMethod" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="card">Card Payment</option>
                        </select>
                    </div>
                    <div x-show="depositMethod === 'mpesa'" class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">M-Pesa Phone Number</label>
                        <input type="tel" x-model="depositPhone" placeholder="0712345678" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" @click="showDepositModal = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Cancel</button>
                        <button type="submit" :disabled="depositLoading"
                            class="rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs hover:bg-green-700 disabled:opacity-50">
                            <span x-show="!depositLoading">Deposit</span>
                            <span x-show="depositLoading">Processing...</span>
                        </button>
                    </div>
                </form>
      </div>
    </div>
    
    {{-- Withdraw Modal --}}
    <div x-show="showWithdrawModal" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="showWithdrawModal = false"
         class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" style="display: none;"></div>
    <div x-show="showWithdrawModal" x-cloak
         x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
         style="display: none; width: 32rem; max-width: calc(100% - 2rem);">
      <div class="p-6 lg:p-10">
        <button type="button" @click="showWithdrawModal = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
          <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/></svg>
        </button>
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Withdraw Funds</h4>
                <form @submit.prevent="submitWithdraw">
                    @csrf
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount (KES)</label>
                        <input type="number" x-model="withdrawAmount" step="1" min="1" :max="balance" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Destination</label>
                        <input type="text" x-model="withdrawDestination" placeholder="Bank account / M-Pesa number" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" @click="showWithdrawModal = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Cancel</button>
                        <button type="submit" :disabled="withdrawLoading"
                            class="rounded-lg bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs hover:bg-red-700 disabled:opacity-50">
                            <span x-show="!withdrawLoading">Withdraw</span>
                            <span x-show="withdrawLoading">Processing...</span>
                        </button>
                    </div>
                </form>
      </div>
    </div>
    
    {{-- Transfer Modal --}}
    <div x-show="showTransferModal" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="showTransferModal = false"
         class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" style="display: none;"></div>
    <div x-show="showTransferModal" x-cloak
         x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
         style="display: none; width: 32rem; max-width: calc(100% - 2rem);">
      <div class="p-6 lg:p-10">
        <button type="button" @click="showTransferModal = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
          <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/></svg>
        </button>
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Transfer Money</h4>
                <form @submit.prevent="submitTransfer">
                    @csrf
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Recipient Email</label>
                        <input type="email" x-model="transferEmail" placeholder="user@example.com" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount (KES)</label>
                        <input type="number" x-model="transferAmount" step="1" min="1" :max="balance" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description (Optional)</label>
                        <textarea x-model="transferDescription" rows="2"
                            class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                    </div>
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" @click="showTransferModal = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Cancel</button>
                        <button type="submit" :disabled="transferLoading"
                            class="rounded-lg bg-blue-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs hover:bg-blue-700 disabled:opacity-50">
                            <span x-show="!transferLoading">Transfer</span>
                            <span x-show="transferLoading">Processing...</span>
                        </button>
                    </div>
                </form>
      </div>
    </div>
        </div>{{-- /tenantWalletComponent: the deposit, withdraw and transfer forms sit inside it so their buttons can open them --}}
</div>

<script>
function tenantWalletComponent() {
    return {
        balance: {{ $balance ?? 0 }},
        walletId: '{{ $wallet->uuid ?? 'N/A' }}',
        transactions: [],
        pendingInvoices: @json($pendingInvoices ?? []),
        
        // Filters
        filterType: 'all',
        filterFrom: '',
        filterTo: '',
        
        // Pagination
        currentPage: 1,
        perPage: 15,
        totalCount: 0,
        totalPages: 0,
        
        // Modal states
        showDepositModal: false,
        showWithdrawModal: false,
        showTransferModal: false,
        
        // Form data
        depositAmount: '',
        depositMethod: 'mpesa',
        depositPhone: '',
        depositLoading: false,
        
        withdrawAmount: '',
        withdrawDestination: '',
        withdrawLoading: false,
        
        transferEmail: '',
        transferAmount: '',
        transferDescription: '',
        transferLoading: false,
        
        init() {
            this.loadTransactions();
        },
        
        async loadTransactions() {
            try {
                const params = new URLSearchParams({
                    page: this.currentPage,
                    per_page: this.perPage,
                    type: this.filterType,
                    from_date: this.filterFrom,
                    to_date: this.filterTo
                });
                
                const response = await fetch(`/api/wallet/transactions?${params}`);
                const data = await response.json();
                
                this.transactions = data.data || [];
                this.totalCount = data.total || 0;
                this.totalPages = data.last_page || 1;
                this.currentPage = data.current_page || 1;
            } catch (error) {
                console.error('Error loading transactions:', error);
            }
        },
        
        formatNumber(value) {
            return parseFloat(value).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        
        formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-KE', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        formatMonth(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-KE', { year: 'numeric', month: 'long' });
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadTransactions();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadTransactions();
            }
        },
        
        openDepositModal() {
            this.depositAmount = '';
            this.depositMethod = 'mpesa';
            this.depositPhone = '';
            this.showDepositModal = true;
        },
        
        async submitDeposit() {
            if (!this.depositAmount || this.depositAmount <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            this.depositLoading = true;
            
            const formData = new FormData();
            formData.append('amount', this.depositAmount);
            formData.append('payment_method', this.depositMethod);
            if (this.depositPhone) formData.append('phone_number', this.depositPhone);
            
            try {
                const response = await fetch('{{ route("tenant.wallet.deposit") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.error || 'Deposit failed');
                    this.showDepositModal = false;
                }
            } catch (error) {
                alert('An error occurred');
            } finally {
                this.depositLoading = false;
            }
        },
        
        openWithdrawModal() {
            this.withdrawAmount = '';
            this.withdrawDestination = '';
            this.showWithdrawModal = true;
        },
        
        async submitWithdraw() {
            if (!this.withdrawAmount || this.withdrawAmount <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            if (this.withdrawAmount > this.balance) {
                alert('Amount exceeds available balance');
                return;
            }
            
            if (!this.withdrawDestination) {
                alert('Please enter destination');
                return;
            }
            
            this.withdrawLoading = true;
            
            const formData = new FormData();
            formData.append('amount', this.withdrawAmount);
            formData.append('destination', this.withdrawDestination);
            
            try {
                const response = await fetch('{{ route("tenant.wallet.withdraw") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.error || 'Withdrawal failed');
                    this.showWithdrawModal = false;
                }
            } catch (error) {
                alert('An error occurred');
            } finally {
                this.withdrawLoading = false;
            }
        },
        
        openTransferModal() {
            this.transferEmail = '';
            this.transferAmount = '';
            this.transferDescription = '';
            this.showTransferModal = true;
        },
        
        async submitTransfer() {
            if (!this.transferEmail) {
                alert('Please enter recipient email');
                return;
            }
            
            if (!this.transferAmount || this.transferAmount <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            if (this.transferAmount > this.balance) {
                alert('Amount exceeds available balance');
                return;
            }
            
            this.transferLoading = true;
            
            const formData = new FormData();
            formData.append('recipient_email', this.transferEmail);
            formData.append('amount', this.transferAmount);
            formData.append('description', this.transferDescription);
            
            try {
                const response = await fetch('{{ route("tenant.wallet.transfer") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.error || 'Transfer failed');
                    this.showTransferModal = false;
                }
            } catch (error) {
                alert('An error occurred');
            } finally {
                this.transferLoading = false;
            }
        },
        
        async payInvoice(invoiceId, amount) {
            const payAmount = prompt(`Enter amount to pay (KES ${this.formatNumber(amount)}):`, amount);
            if (!payAmount || payAmount <= 0) return;
            
            const formData = new FormData();
            formData.append('amount', payAmount);
            
            try {
                const response = await fetch(`{{ url('/wallet/pay-invoice') }}/${invoiceId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.error || 'Payment failed');
                }
            } catch (error) {
                alert('An error occurred');
            }
        },
        
        async exportTransactions() {
            window.location.href = `{{ route("tenant.wallet.transactions.export") }}?type=${this.filterType}&from_date=${this.filterFrom}&to_date=${this.filterTo}`;
        }
    };
}
</script>
@endsection