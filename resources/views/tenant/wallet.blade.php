{{-- resources/views/tenant/wallet.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction History</h3>
                        
                        {{-- Filters --}}
                        <div class="flex flex-wrap gap-3">
                            <select x-model="filterType" @change="loadTransactions()"
                                class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                                <option value="all">All Types</option>
                                <option value="deposit">Deposits</option>
                                <option value="withdraw">Withdrawals</option>
                            </select>
                            
                            <input type="date" x-model="filterFrom" @change="loadTransactions()"
                                class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            
                            <input type="date" x-model="filterTo" @change="loadTransactions()"
                                class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            
                            <button @click="exportTransactions()" 
                                class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-lg text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="tx in transactions" :key="tx.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" x-text="formatDate(tx.created_at)"></td>
                                    <td class="px-6 py-4">
                                        <span x-show="tx.type === 'deposit'" class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Deposit</span>
                                        <span x-show="tx.type === 'withdraw'" class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Withdrawal</span>
                                        <span x-show="tx.is_pending" class="ml-1 inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">Pending</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        <span x-text="tx.meta?.description || tx.description || '—'"></span>
                                        <span x-show="tx.is_pending" class="block text-xs text-yellow-600 dark:text-yellow-400">Awaiting approval</span>
                                        <span x-show="tx.notes" class="block text-xs text-gray-500 dark:text-gray-400" x-text="tx.notes"></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
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
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No transactions found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                {{-- Pagination --}}
                <div x-show="transactions.length > 0" class="px-6 py-4 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing <span x-text="((currentPage - 1) * perPage) + 1"></span> to 
                            <span x-text="Math.min(currentPage * perPage, totalCount)"></span> of 
                            <span x-text="totalCount"></span> entries
                        </p>
                        <div class="flex gap-2">
                            <button @click="prevPage()" :disabled="currentPage === 1"
                                class="px-3 py-1 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700">
                                Previous
                            </button>
                            <span class="px-3 py-1 text-sm" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
                            <button @click="nextPage()" :disabled="currentPage === totalPages"
                                class="px-3 py-1 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Deposit Modal --}}
    <div x-show="showDepositModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showDepositModal = false"></div>
            <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Deposit Funds</h3>
                <form @submit.prevent="submitDeposit">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (KES)</label>
                        <input type="number" x-model="depositAmount" step="1" min="1" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                        <select x-model="depositMethod" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="card">Card Payment</option>
                        </select>
                    </div>
                    <div x-show="depositMethod === 'mpesa'" class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">M-Pesa Phone Number</label>
                        <input type="tel" x-model="depositPhone" placeholder="0712345678" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showDepositModal = false"
                            class="flex-1 px-4 py-2 border rounded-lg">Cancel</button>
                        <button type="submit" :disabled="depositLoading"
                            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                            <span x-show="!depositLoading">Deposit</span>
                            <span x-show="depositLoading">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    {{-- Withdraw Modal --}}
    <div x-show="showWithdrawModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showWithdrawModal = false"></div>
            <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Withdraw Funds</h3>
                <form @submit.prevent="submitWithdraw">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (KES)</label>
                        <input type="number" x-model="withdrawAmount" step="1" min="1" :max="balance" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination</label>
                        <input type="text" x-model="withdrawDestination" placeholder="Bank account / M-Pesa number" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showWithdrawModal = false"
                            class="flex-1 px-4 py-2 border rounded-lg">Cancel</button>
                        <button type="submit" :disabled="withdrawLoading"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">
                            <span x-show="!withdrawLoading">Withdraw</span>
                            <span x-show="withdrawLoading">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    {{-- Transfer Modal --}}
    <div x-show="showTransferModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showTransferModal = false"></div>
            <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Transfer Money</h3>
                <form @submit.prevent="submitTransfer">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recipient Email</label>
                        <input type="email" x-model="transferEmail" placeholder="user@example.com" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (KES)</label>
                        <input type="number" x-model="transferAmount" step="1" min="1" :max="balance" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (Optional)</label>
                        <textarea x-model="transferDescription" rows="2"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showTransferModal = false"
                            class="flex-1 px-4 py-2 border rounded-lg">Cancel</button>
                        <button type="submit" :disabled="transferLoading"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            <span x-show="!transferLoading">Transfer</span>
                            <span x-show="transferLoading">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
                    window.location.reload();
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
                    window.location.reload();
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
                    window.location.reload();
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
                    window.location.reload();
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