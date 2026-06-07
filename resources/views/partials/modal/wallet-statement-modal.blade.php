{{-- resources/views/partials/modal/wallet-add-card-modal.blade.php --}}
<div x-data="walletAddCardModal()" x-init="init()" class="relative z-99999">
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
            <form @submit.prevent="submitCard">
                @csrf
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Add New Card</h4>

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

                {{-- Card Type Selection --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Card Type *</label>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all"
                            :class="cardType === 'visa' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'"
                            @click="cardType = 'visa'">
                            <input type="radio" x-model="cardType" value="visa" class="hidden">
                            <img src="{{ asset('src/images/payment-gateway/visa.png') }}" class="h-8 w-12" alt="Visa">
                            <span class="text-sm font-medium">Visa</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all"
                            :class="cardType === 'mastercard' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'"
                            @click="cardType = 'mastercard'">
                            <input type="radio" x-model="cardType" value="mastercard" class="hidden">
                            <img src="{{ asset('src/images/payment-gateway/mastercard.png') }}" class="h-8 w-12" alt="Mastercard">
                            <span class="text-sm font-medium">Mastercard</span>
                        </label>
                    </div>
                </div>

                {{-- Card Number --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Card Number *</label>
                    <input type="text" x-model="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19"
                        @input="formatCardNumber"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm font-mono text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <p class="mt-1 text-xs text-gray-500">Enter 16-digit card number</p>
                </div>

                {{-- Cardholder Name --}}
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cardholder Name *</label>
                    <input type="text" x-model="cardholderName" placeholder="Name as it appears on the card"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                {{-- Expiry and CVC Row --}}
                <div class="mb-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Expiry Date *</label>
                        <input type="text" x-model="expiry" placeholder="MM/YY" maxlength="5"
                            @input="formatExpiry"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm font-mono text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">CVC/CVV *</label>
                        <input type="password" x-model="cvc" placeholder="123" maxlength="4"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm font-mono text-gray-800 shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                </div>

                {{-- Set as Default --}}
                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="setAsDefault" class="rounded text-brand-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Set as default payment method</span>
                    </label>
                </div>

                {{-- Card Preview --}}
                <div class="mb-6 p-4 bg-gradient-to-r from-gray-800 to-gray-900 rounded-xl">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z"/>
                            </svg>
                            <span class="text-white text-xs">Credit Card</span>
                        </div>
                        <img :src="cardType === 'visa' ? '{{ asset('src/images/payment-gateway/visa.png') }}' : '{{ asset('src/images/payment-gateway/mastercard.png') }}'" class="h-8" alt="">
                    </div>
                    <div class="mb-4">
                        <p class="text-white/60 text-xs mb-1">Card Number</p>
                        <p class="text-white font-mono text-lg tracking-wider" x-text="displayCardNumber || '•••• •••• •••• ••••'"></p>
                    </div>
                    <div class="flex justify-between">
                        <div>
                            <p class="text-white/60 text-xs mb-1">Cardholder Name</p>
                            <p class="text-white text-sm uppercase" x-text="cardholderName || 'YOUR NAME'"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-white/60 text-xs mb-1">Expires</p>
                            <p class="text-white text-sm" x-text="expiry || 'MM/YY'"></p>
                        </div>
                    </div>
                </div>

                {{-- Billing Address Section --}}
                <div class="mb-4 border-t border-gray-200 dark:border-gray-800 pt-4">
                    <h5 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Billing Address</h5>
                    
                    <div class="mb-3">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Street Address *</label>
                        <input type="text" x-model="billingAddress" placeholder="Street address, P.O. Box"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    
                    <div class="mb-3">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">City *</label>
                        <input type="text" x-model="billingCity" placeholder="City"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">State/Province</label>
                            <input type="text" x-model="billingState" placeholder="State/Province"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Postal Code *</label>
                            <input type="text" x-model="billingPostalCode" placeholder="Postal code"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Country *</label>
                        <select x-model="billingCountry"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Select Country</option>
                            <option value="KE">Kenya</option>
                            <option value="US">United States</option>
                            <option value="UK">United Kingdom</option>
                            <option value="CA">Canada</option>
                            <option value="AU">Australia</option>
                        </select>
                    </div>
                </div>

                {{-- Terms --}}
                <div class="mb-6">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" x-model="acceptTerms" class="mt-0.5 rounded text-brand-500">
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            I confirm that I am authorized to use this card and agree to the 
                            <a href="#" class="text-brand-500 hover:underline">Terms of Service</a>
                        </span>
                    </label>
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="closeModal"
                        class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-900">
                        Cancel
                    </button>
                    <button type="submit" :disabled="loading || !isFormValid"
                        class="flex-1 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">
                        <span x-show="!loading">Add Card</span>
                        <span x-show="loading">Adding...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('walletAddCardModal', () => ({
        isOpen: false,
        cardType: 'visa',
        cardNumber: '',
        displayCardNumber: '',
        cardholderName: '',
        expiry: '',
        cvc: '',
        setAsDefault: false,
        billingAddress: '',
        billingCity: '',
        billingState: '',
        billingPostalCode: '',
        billingCountry: 'KE',
        acceptTerms: false,
        formErrors: [],
        loading: false,

        get isFormValid() {
            if (!this.cardNumber || this.cardNumber.replace(/\s/g, '').length < 16) return false;
            if (!this.cardholderName) return false;
            if (!this.expiry || !this.expiry.match(/^(0[1-9]|1[0-2])\/([0-9]{2})$/)) return false;
            if (!this.cvc || this.cvc.length < 3) return false;
            if (!this.billingAddress) return false;
            if (!this.billingCity) return false;
            if (!this.billingPostalCode) return false;
            if (!this.billingCountry) return false;
            if (!this.acceptTerms) return false;
            return true;
        },

        init() {
            window.walletAddCardModal = this;
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
            this.cardType = 'visa';
            this.cardNumber = '';
            this.displayCardNumber = '';
            this.cardholderName = '';
            this.expiry = '';
            this.cvc = '';
            this.setAsDefault = false;
            this.billingAddress = '';
            this.billingCity = '';
            this.billingState = '';
            this.billingPostalCode = '';
            this.billingCountry = 'KE';
            this.acceptTerms = false;
            this.formErrors = [];
        },

        formatCardNumber() {
            let value = this.cardNumber.replace(/\s/g, '').replace(/[^\d]/g, '');
            let formatted = '';
            for (let i = 0; i < value.length && i < 16; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            this.cardNumber = formatted;
            this.displayCardNumber = formatted || '•••• •••• •••• ••••';
        },

        formatExpiry() {
            let value = this.expiry.replace(/[^\d]/g, '');
            if (value.length >= 2) {
                let month = parseInt(value.substring(0, 2));
                if (month > 12) month = 12;
                let monthStr = month.toString().padStart(2, '0');
                this.expiry = monthStr + '/' + value.substring(2, 4);
            } else {
                this.expiry = value;
            }
        },

        async submitCard() {
            this.formErrors = [];
            
            if (!this.isFormValid) {
                this.formErrors = ['Please fill in all required fields correctly'];
                return;
            }

            this.loading = true;

            // Simulate API call - replace with actual endpoint
            setTimeout(() => {
                this.loading = false;
                this.closeModal();
                
                const newCard = {
                    id: Date.now(),
                    cardType: this.cardType,
                    cardNumber: this.cardNumber.slice(-4),
                    cardholderName: this.cardholderName,
                    expiry: this.expiry,
                    cvc: this.cvc,
                    active: true
                };
                
                if (window.walletComponent && window.walletComponent.cards) {
                    window.walletComponent.cards.push(newCard);
                }
                
                if (window.successModal) {
                    window.successModal.show('Success!', 'Card added successfully.');
                } else {
                    alert('Card added successfully!');
                }
            }, 1500);
        }
    }));
});
</script>