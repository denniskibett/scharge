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

                {{-- Success Message --}}
                <div x-show="successMessage" x-transition
                    class="mb-6 rounded-lg bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-400">
                    <p x-text="successMessage"></p>
                </div>

                {{-- Recipient Type Toggle --}}
                <div class="mb-4 flex gap-2 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <button type="button" @click="recipientType = 'internal'"
                        class="flex-1 py-2 text-sm font-medium rounded-md transition-all"
                        :class="recipientType === 'internal' ? 'bg-white dark:bg-gray-900 text-brand-600 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'">
                        Internal Transfer
                    </button>
                    <button type="button" @click="recipientType = 'external'"
                        class="flex-1 py-2 text-sm font-medium rounded-md transition-all"
                        :class="recipientType === 'external' ? 'bg-white dark:bg-gray-900 text-brand-600 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'">
                        External Transfer
                    </button>
                </div>

                {{-- Internal Transfer: Select Recipient --}}
                <div x-show="recipientType === 'internal'" class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Select Recipient *</label>
                    <div class="relative" x-data="{ open: false, selected: null }">
                        <button type="button" @click="open = !open"
                            class="flex h-11 w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-normal text-gray-700 shadow-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <div class="flex items-center gap-3">
                                <template x-if="selected">
                                    <>
                                        <img :src="selected.avatar" class="h-8 w-8 rounded-full object-cover" alt="">
                                        <div class="text-left">
                                            <p class="font-medium" x-text="selected.name"></p>
                                            <p class="text-xs text-gray-400" x-text="selected.email"></p>
                                        </div>
                                    </>
                                </template>
                                <template x-if="!selected">
                                    <span class="text-gray-400">Choose a contact</span>
                                </template>
                            </div>
                            <svg :class="open ? 'rotate-180' : ''" class="transition-transform" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M4.79102 8.021L9.99935 13.2293L15.2077 8.021" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.outside="open = false"
                            class="absolute left-0 z-10 mt-2 w-full rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900 max-h-64 overflow-y-auto">
                            <template x-for="contact in contacts" :key="contact.id">
                                <button type="button" @click="selected = contact; selectedRecipientId = contact.id; open = false"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                    <img :src="contact.avatar" class="h-8 w-8 rounded-full object-cover" alt="">
                                    <div class="text-left">
                                        <p class="font-medium" x-text="contact.name"></p>
                                        <p class="text-xs text-gray-400" x-text="contact.email"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- External Transfer: Bank/Paybill Details --}}
                <div x-show="recipientType === 'external'">
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transfer Type *</label>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2">
                                <input type="radio" x-model="externalType" value="bank" class="text-brand-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Bank Account</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" x-model="externalType" value="paybill" class="text-brand-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Paybill</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" x-model="externalType" value="mpesa" class="text-brand-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">M-Pesa</span>
                            </label>
                        </div>
                    </div>

                    {{-- Bank Transfer Fields --}}
                    <div x-show="externalType === 'bank'">
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Bank Name *</label>
                            <input type="text" x-model="bankName" placeholder="e.g., Equity Bank"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Account Number *</label>
                            <input type="text" x-model="accountNumber" placeholder="Enter account number"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Account Name *</label>
                            <input type="text" x-model="accountName" placeholder="Enter account holder name"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                    </div>

                    {{-- Paybill Fields --}}
                    <div x-show="externalType === 'paybill'">
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Business Number *</label>
                            <input type="text" x-model="paybillNumber" placeholder="e.g., 247247"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Account Number/Reference *</label>
                            <input type="text" x-model="paybillRef" placeholder="Enter account number or reference"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                    </div>

                    {{-- M-Pesa Fields --}}
                    <div x-show="externalType === 'mpesa'">
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone Number *</label>
                            <input type="tel" x-model="mpesaPhone" placeholder="0712345678"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                        <div class="mb-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Recipient Name</label>
                            <input type="text" x-model="mpesaName" placeholder="Enter recipient name"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                    </div>
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
                    <p x-show="amountExceedsBalance" class="mt-1 text-xs text-red-600">Amount exceeds available balance of KES <span x-text="availableBalance"></span></p>
                </div>

                {{-- Available Balance --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Available Balance:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">KES <span x-text="availableBalance"></span></span>
                    </div>
                </div>

                {{-- Description/Reference --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description / Reference</label>
                    <textarea x-model="description" rows="2" placeholder="Optional: Add a note or reference for this payment"
                        class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                </div>

                {{-- Schedule Payment Option --}}
                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="schedulePayment" class="rounded text-brand-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Schedule for later</span>
                    </label>
                </div>

                {{-- Schedule Date (conditional) --}}
                <div x-show="schedulePayment" class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Schedule Date *</label>
                    <input type="datetime-local" x-model="scheduledDate"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                {{-- Payment Summary --}}
                <div class="mb-6 p-4 bg-brand-50 dark:bg-brand-900/20 rounded-lg">
                    <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3">Payment Summary</h5>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Amount:</span>
                            <span class="font-medium text-gray-900 dark:text-white">KES <span x-text="formatNumber(amount)"></span></span>
                        </div>
                        <div class="flex justify-between" x-show="recipientType === 'internal' && selectedRecipient">
                            <span class="text-gray-600 dark:text-gray-400">Recipient:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="selectedRecipient?.name"></span>
                        </div>
                        <div class="flex justify-between" x-show="recipientType === 'external' && externalType === 'bank'">
                            <span class="text-gray-600 dark:text-gray-400">Bank:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="bankName"></span>
                        </div>
                        <div class="flex justify-between" x-show="schedulePayment">
                            <span class="text-gray-600 dark:text-gray-400">Scheduled for:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="formatDate(scheduledDate)"></span>
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
                        <span x-show="!loading">
                            <span x-text="schedulePayment ? 'Schedule Payment' : 'Send Money'"></span>
                        </span>
                        <span x-show="loading">Processing...</span>
                    </button>
                </div>

                {{-- Confirmation PIN Modal (simulated) --}}
                <div x-show="showPinModal" x-cloak class="fixed inset-0 z-9999999 flex items-center justify-center bg-black/50">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-96 max-w-[90%]">
                        <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Confirm Transaction</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter your PIN to confirm payment of KES <span x-text="formatNumber(amount)"></span></p>
                        <input type="password" x-model="pin" placeholder="Enter 4-digit PIN" maxlength="4"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 text-center text-2xl tracking-widest focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <div class="flex gap-3 mt-6">
                            <button @click="showPinModal = false" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm">Cancel</button>
                            <button @click="confirmPayment" class="flex-1 rounded-lg bg-brand-500 px-4 py-2 text-sm text-white">Confirm</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('walletPaymentModal', () => ({
        isOpen: false,
        recipientType: 'internal',
        externalType: 'bank',
        selectedRecipient: null,
        selectedRecipientId: null,
        amount: '',
        description: '',
        schedulePayment: false,
        scheduledDate: '',
        bankName: '',
        accountNumber: '',
        accountName: '',
        paybillNumber: '',
        paybillRef: '',
        mpesaPhone: '',
        mpesaName: '',
        formErrors: [],
        successMessage: '',
        loading: false,
        showPinModal: false,
        pin: '',
        availableBalance: 19857.00,
        
        contacts: [
            { id: 1, name: 'John Doe', email: 'john.doe@example.com', avatar: '{{ asset("src/images/user/user-02.jpg") }}' },
            { id: 2, name: 'Jane Smith', email: 'jane.smith@example.com', avatar: '{{ asset("src/images/user/user-03.jpg") }}' },
            { id: 3, name: 'Michael Brown', email: 'michael.brown@example.com', avatar: '{{ asset("src/images/user/user-04.jpg") }}' },
            { id: 4, name: 'Sarah Wilson', email: 'sarah.wilson@example.com', avatar: '{{ asset("src/images/user/user-05.jpg") }}' },
            { id: 5, name: 'David Lee', email: 'david.lee@example.com', avatar: '{{ asset("src/images/user/user-06.jpg") }}' }
        ],

        get amountExceedsBalance() {
            return parseFloat(this.amount || 0) > this.availableBalance;
        },

        get isFormValid() {
            if (this.amountExceedsBalance) return false;
            if (!this.amount || parseFloat(this.amount) <= 0) return false;
            
            if (this.recipientType === 'internal') {
                if (!this.selectedRecipientId) return false;
            } else {
                if (this.externalType === 'bank') {
                    if (!this.bankName || !this.accountNumber || !this.accountName) return false;
                } else if (this.externalType === 'paybill') {
                    if (!this.paybillNumber || !this.paybillRef) return false;
                } else if (this.externalType === 'mpesa') {
                    if (!this.mpesaPhone) return false;
                }
            }
            
            if (this.schedulePayment && !this.scheduledDate) return false;
            
            return true;
        },

        init() {
            window.walletPaymentModal = this;
        },

        openModal() {
            this.isOpen = true;
            this.resetForm();
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.isOpen = false;
            this.showPinModal = false;
            document.body.style.overflow = '';
        },

        resetForm() {
            this.recipientType = 'internal';
            this.externalType = 'bank';
            this.selectedRecipient = null;
            this.selectedRecipientId = null;
            this.amount = '';
            this.description = '';
            this.schedulePayment = false;
            this.scheduledDate = '';
            this.bankName = '';
            this.accountNumber = '';
            this.accountName = '';
            this.paybillNumber = '';
            this.paybillRef = '';
            this.mpesaPhone = '';
            this.mpesaName = '';
            this.formErrors = [];
            this.successMessage = '';
            this.pin = '';
        },

        validateAmount() {
            if (this.amountExceedsBalance) {
                this.formErrors = ['Amount exceeds available balance'];
            } else {
                this.formErrors = [];
            }
        },

        formatNumber(value) {
            return parseFloat(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleString();
        },

        async submitPayment() {
            this.formErrors = [];
            
            if (!this.isFormValid) {
                this.formErrors = ['Please fill in all required fields'];
                return;
            }

            // Show PIN confirmation modal
            this.showPinModal = true;
        },

        async confirmPayment() {
            if (!this.pin || this.pin.length < 4) {
                this.formErrors = ['Please enter your 4-digit PIN'];
                return;
            }

            this.loading = true;
            this.showPinModal = false;

            // Simulate API call - replace with actual endpoint
            setTimeout(() => {
                this.loading = false;
                this.successMessage = this.schedulePayment 
                    ? `Payment of KES ${this.formatNumber(this.amount)} scheduled successfully!`
                    : `Payment of KES ${this.formatNumber(this.amount)} sent successfully!`;
                
                setTimeout(() => {
                    this.closeModal();
                    if (window.successModal) {
                        window.successModal.show('Success!', this.successMessage);
                    }
                }, 1500);
            }, 1500);
        }
    }));
});
</script>