{{-- resources/views/partials/modal/wallet-payment-modal.blade.php --}}
<div x-data="walletPaymentModal()" x-init="init()" class="relative z-99999">
    {{-- Backdrop --}}
    <template x-if="isOpen">
        <div @click="closeModal"
            class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-999999"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"></div>
    </template>

    {{-- Slide-over Content --}}
    <div x-show="isOpen"
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        x-cloak
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 42rem; max-width: calc(100% - 2rem);">

        <button @click="closeModal"
            class="group fixed right-3 top-3 z-9999999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
            </svg>
        </button>

        <div class="p-6 lg:p-8">
            <form @submit.prevent="submitPayment">
                @csrf
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Send Money</h4>

                {{-- Error Messages --}}
                <template x-if="formErrors.length > 0">
                    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc pl-5">
                            <template x-for="error in formErrors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Recipient Email --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Recipient Email *</label>
                    <input type="email" x-model="recipientEmail" placeholder="recipient@example.com"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                {{-- Amount --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 dark:text-gray-400">KES</span>
                        </div>
                        <input type="number" step="0.01" x-model="amount" placeholder="0.00" required
                            @input="validateAmount"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-16 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <p x-show="amountExceedsBalance" class="mt-1 text-xs text-red-600">Amount exceeds available balance</p>
                </div>

                {{-- Available Balance --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Available Balance:</span>
                        <span class="font-semibold text-gray-900 dark:text-white" x-text="formattedAvailableBalance"></span>
                    </div>
                </div>

                {{-- Description --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description (Optional)</label>
                    <textarea x-model="description" rows="2" placeholder="Add a note or reference for this transfer"
                        class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                </div>

                {{-- PIN Confirmation --}}
                <div class="mb-6">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirm with PIN *</label>
                    <input type="password" x-model="pin" placeholder="Enter 4-digit PIN" maxlength="4"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-center text-2xl tracking-widest text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                {{-- Transfer Summary --}}
                <div class="mb-6 p-4 bg-brand-50 dark:bg-brand-900/20 rounded-lg">
                    <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3">Transfer Summary</h5>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Amount:</span>
                            <span class="font-medium text-gray-900 dark:text-white">KES <span x-text="formatNumber(amount)"></span></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Recipient:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="recipientEmail || '—'"></span>
                        </div>
                        <div class="border-t border-brand-200 dark:border-brand-800 pt-2 mt-2">
                            <div class="flex justify-between font-semibold">
                                <span>Total Debit:</span>
                                <span class="text-brand-600 dark:text-brand-400">KES <span x-text="formatNumber(amount)"></span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="closeModal"
                        class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-900">
                        Cancel
                    </button>
                    <button type="submit" :disabled="loading || !isFormValid"
                        class="flex-1 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!loading">Send Money</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('walletPaymentModal', () => ({
        isOpen: false,
        recipientEmail: '',
        amount: '',
        description: '',
        pin: '',
        formErrors: [],
        loading: false,
        availableBalance: 0,

        get amountExceedsBalance() {
            return parseFloat(this.amount || 0) > this.availableBalance;
        },

        get formattedAvailableBalance() {
            return 'KES ' + this.formatNumber(this.availableBalance);
        },

        get isFormValid() {
            if (this.amountExceedsBalance) return false;
            if (!this.amount || parseFloat(this.amount) <= 0) return false;
            if (!this.recipientEmail || !this.recipientEmail.includes('@')) return false;
            if (!this.pin || this.pin.length < 4) return false;
            return true;
        },

        init() {
            window.walletPaymentModal = this;
        },

        async openModal() {
            this.isOpen = true;
            this.resetForm();
            await this.fetchBalance();
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },

        resetForm() {
            this.recipientEmail = '';
            this.amount = '';
            this.description = '';
            this.pin = '';
            this.formErrors = [];
        },

        async fetchBalance() {
            try {
                const response = await fetch('/api/wallet/balance');
                const data = await response.json();
                if (data.success) {
                    this.availableBalance = data.balance;
                }
            } catch (error) {
                console.error('Error fetching balance:', error);
            }
        },

        validateAmount() {
            if (this.amountExceedsBalance) {
                this.formErrors = ['Amount exceeds available balance'];
            } else {
                this.formErrors = [];
            }
        },

        formatNumber(value) {
            return parseFloat(value || 0).toLocaleString('en-KE', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
        },

        async submitPayment() {
            this.formErrors = [];
            
            if (!this.isFormValid) {
                if (this.amountExceedsBalance) {
                    this.formErrors.push('Amount exceeds available balance');
                }
                if (!this.recipientEmail) {
                    this.formErrors.push('Please enter recipient email');
                }
                if (!this.pin || this.pin.length < 4) {
                    this.formErrors.push('Please enter your 4-digit PIN');
                }
                if (!this.amount || parseFloat(this.amount) <= 0) {
                    this.formErrors.push('Please enter a valid amount');
                }
                return;
            }

            this.loading = true;

            try {
                if (window.walletComponent && typeof window.walletComponent.processTransfer === 'function') {
                    const result = await window.walletComponent.processTransfer(
                        this.recipientEmail,
                        this.amount,
                        this.description,
                        this.pin
                    );
                    
                    if (result.success) {
                        setTimeout(() => {
                            this.closeModal();
                        }, 1500);
                    } else {
                        this.formErrors = [result.error];
                    }
                } else {
                    // Fallback to direct API call
                    const response = await fetch('/api/wallet/transfer', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            recipient_email: this.recipientEmail,
                            amount: this.amount,
                            description: this.description,
                            pin: this.pin
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        if (window.walletComponent) {
                            window.walletComponent.walletBalance = result.data.new_balance;
                            await window.walletComponent.loadTransactions();
                        }
                        setTimeout(() => {
                            this.closeModal();
                        }, 1500);
                    } else {
                        this.formErrors = [result.error];
                    }
                }
            } catch (error) {
                console.error('Transfer error:', error);
                this.formErrors = ['An error occurred. Please try again.'];
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>