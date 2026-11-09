{{-- resources/views/partials/modal/wallet-statement-modal.blade.php --}}
<div x-data="walletStatementModal()" x-init="init()">
    <!-- Backdrop -->
    <div x-show="isOpen" x-cloak @click="closeModal()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>

    <!-- Slide-over panel (slides in from the right) -->
    <div x-show="isOpen" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 42rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Account Statement</h4>

            {{-- Body --}}
            <div>
                {{-- Date Range Filter --}}
                <div class="mb-6 flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">From Date</label>
                        <input type="date" x-model="fromDate" @change="loadStatement"
                            class="rounded-lg border border-gray-300 px-4 py-2 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">To Date</label>
                        <input type="date" x-model="toDate" @change="loadStatement"
                            class="rounded-lg border border-gray-300 px-4 py-2 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    <button @click="exportStatement"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm text-white hover:bg-brand-600">
                        Export CSV
                    </button>
                </div>

                {{-- Summary Cards --}}
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Opening Balance</p>
                        <p class="text-xl font-semibold text-gray-800 dark:text-white/90">
                            KES <span x-text="formatNumber(summary.opening_balance)"></span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-green-50 p-4 dark:bg-green-900/20">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Credits</p>
                        <p class="text-xl font-semibold text-green-600">
                            KES <span x-text="formatNumber(summary.total_credits)"></span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-red-50 p-4 dark:bg-red-900/20">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Debits</p>
                        <p class="text-xl font-semibold text-red-600">
                            KES <span x-text="formatNumber(summary.total_debits)"></span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-brand-50 p-4 dark:bg-brand-900/20">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Closing Balance</p>
                        <p class="text-xl font-semibold text-brand-600">
                            KES <span x-text="formatNumber(summary.closing_balance)"></span>
                        </p>
                    </div>
                </div>

                {{-- Loading State --}}
                <div x-show="loading" class="py-12 text-center">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"></div>
                    <p class="mt-2 text-sm text-gray-500">Loading statement...</p>
                </div>

                {{-- Transactions Table --}}
                <div x-show="!loading" class="overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead class="border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="tx in transactions" :key="tx.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-sm text-gray-700" x-text="formatDate(tx.created_at)"></td>
                                    <td class="px-4 py-3 text-sm text-gray-700" x-text="tx.description || tx.type"></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="tx.type === 'deposit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                            x-text="tx.type === 'deposit' ? 'Credit' : 'Debit'">
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium"
                                        :class="tx.type === 'deposit' ? 'text-green-600' : 'text-red-600'">
                                        <span x-show="tx.type === 'deposit'">+</span>
                                        <span x-show="tx.type !== 'deposit'">-</span>
                                        KES <span x-text="formatNumber(tx.amount)"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="transactions.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    No transactions found for this period
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end w-full gap-3 mt-6">
                <button @click="closeModal"
                    class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('walletStatementModal', () => ({
        isOpen: false,
        loading: false,
        fromDate: '',
        toDate: '',
        transactions: [],
        summary: {
            opening_balance: 0,
            total_credits: 0,
            total_debits: 0,
            closing_balance: 0
        },

        init() {
            window.walletStatementModal = this;
            const today = new Date();
            this.toDate = today.toISOString().split('T')[0];
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            this.fromDate = firstDay.toISOString().split('T')[0];
        },

        async openModal() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            await this.loadStatement();
        },

        closeModal() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },

        async loadStatement() {
            this.loading = true;
            try {
                const response = await fetch(`/api/wallet/statement?from_date=${this.fromDate}&to_date=${this.toDate}`);
                const data = await response.json();
                if (data.success) {
                    this.transactions = data.transactions;
                    this.summary = data.summary;
                }
            } catch (error) {
                console.error('Error loading statement:', error);
            } finally {
                this.loading = false;
            }
        },

        async exportStatement() {
            try {
                window.location.href = `/wallet/transactions/export?from_date=${this.fromDate}&to_date=${this.toDate}`;
            } catch (error) {
                console.error('Error exporting statement:', error);
            }
        },

        formatNumber(value) {
            return parseFloat(value || 0).toLocaleString('en-KE', {
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
                day: 'numeric'
            });
        }
    }));
});
</script>