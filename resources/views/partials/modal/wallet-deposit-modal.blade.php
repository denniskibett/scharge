{{-- resources/views/partials/modal/wallet-deposit-modal.blade.php --}}
<div x-data="walletDepositModal()" x-init="init()" class="relative z-99999">
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
            <form @submit.prevent="submitDeposit">
                @csrf
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Deposit Funds</h4>

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

                {{-- Amount --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 dark:text-gray-400">{{ $currencySymbol ?? 'KES' }}</span>
                        </div>
                        <input type="number" step="0.01" x-model="amount" placeholder="0.00" required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-16 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method *</label>
                    <div class="relative" x-data="{ open: false, selected: 'M-Pesa' }">
                        <button type="button" @click="open = !open"
                            class="flex h-11 w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-normal text-gray-700 shadow-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <div class="flex items-center gap-3">
                                <img :src="getPaymentIcon(selected)" class="h-6 w-6" alt="">
                                <span x-text="selected"></span>
                            </div>
                            <svg :class="open ? 'rotate-180' : ''" class="transition-transform" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M4.79102 8.021L9.99935 13.2293L15.2077 8.021" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.outside="open = false"
                            class="absolute left-0 z-10 mt-2 w-full rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                            <button type="button" @click="selected = 'M-Pesa'; open = false"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                <img src="{{ asset('src/images/payment-gateway/mpesa.png') }}" class="h-6 w-6" alt="">
                                M-Pesa
                            </button>
                            <button type="button" @click="selected = 'Bank Transfer'; open = false"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                <img src="{{ asset('src/images/payment-gateway/bank.png') }}" class="h-6 w-6" alt="">
                                Bank Transfer
                            </button>
                            <button type="button" @click="selected = 'Card'; open = false"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                <img src="{{ asset('src/images/payment-gateway/mastercard.png') }}" class="h-6 w-6" alt="">
                                Credit/Debit Card
                            </button>
                        </div>
                    </div>
                </div>

                {{-- M-Pesa Phone Number (conditional) --}}
                <div x-show="selectedPaymentMethod === 'M-Pesa'" class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">M-Pesa Phone Number *</label>
                    <input type="tel" x-model="phoneNumber" placeholder="0712345678"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                {{-- Bank/Card Details (simplified) --}}
                <div x-show="selectedPaymentMethod === 'Bank Transfer'" class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Reference Number</label>
                    <input type="text" x-model="reference" placeholder="Optional reference"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                {{-- Summary --}}
                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Deposit Amount:</span>
                        <span class="text-lg font-bold text-brand-600 dark:text-brand-400">
                            KES <span x-text="formatNumber(amount)"></span>
                        </span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="closeModal"
                        class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-900">
                        Cancel
                    </button>
                    <button type="submit" :disabled="loading"
                        class="flex-1 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">
                        <span x-show="!loading">Deposit</span>
                        <span x-show="loading">Processing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('walletDepositModal', () => ({
        isOpen: false,
        amount: '',
        selectedPaymentMethod: 'M-Pesa',
        phoneNumber: '',
        reference: '',
        formErrors: [],
        loading: false,

        init() {
            window.walletDepositModal = this;
        },

        openModal() {
            this.isOpen = true;
            this.resetForm();
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },

        resetForm() {
            this.amount = '';
            this.selectedPaymentMethod = 'M-Pesa';
            this.phoneNumber = '';
            this.reference = '';
            this.formErrors = [];
        },

        getPaymentIcon(method) {
            const icons = {
                'M-Pesa': '{{ asset("src/images/payment-gateway/mpesa.png") }}',
                'Bank Transfer': '{{ asset("src/images/payment-gateway/bank.png") }}',
                'Card': '{{ asset("src/images/payment-gateway/mastercard.png") }}'
            };
            return icons[method] || icons['M-Pesa'];
        },

        formatNumber(value) {
            return parseFloat(value || 0).toFixed(2);
        },

        async submitDeposit() {
            this.formErrors = [];
            if (!this.amount || parseFloat(this.amount) <= 0) {
                this.formErrors.push('Please enter a valid amount');
                return;
            }
            if (this.selectedPaymentMethod === 'M-Pesa' && !this.phoneNumber) {
                this.formErrors.push('Please enter your M-Pesa phone number');
                return;
            }

            this.loading = true;
            // Simulate API call - replace with actual endpoint
            setTimeout(() => {
                this.loading = false;
                this.closeModal();
                if (window.successModal) {
                    window.successModal.show('Success!', `Deposit of KES ${this.formatNumber(this.amount)} initiated successfully.`);
                } else {
                    alert(`Deposit of KES ${this.formatNumber(this.amount)} initiated.`);
                }
            }, 1500);
        }
    }));
});
</script>