<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay Invoice #{{ $invoice->id }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .loader {
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid #2563eb;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-pending { background-color: #fef3c7; border-color: #f59e0b; }
        .status-success { background-color: #d1fae5; border-color: #10b981; }
        .status-failed { background-color: #fee2e2; border-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50">
    <div x-data="publicInvoicePayment()" x-init="init()" class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Pay Invoice</h1>
                <p class="text-gray-500 mt-1">Secure payment via M-Pesa</p>
            </div>

            <!-- Main Card -->
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8">
                <!-- Invoice Details -->
                <div class="border-b border-gray-200 pb-6 mb-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Invoice #</p>
                            <p class="text-xl font-bold text-gray-800">#{{ $invoice->id }}</p>
                        </div>
                        <div>
                            <span class="inline-flex px-3 py-1 text-sm rounded-full 
                                {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 
                                   ($invoice->status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 
                                   'bg-red-100 text-red-800') }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                        <div>
                            <p class="text-gray-500">Tenant</p>
                            <p class="font-medium text-gray-800">{{ $invoice->tenancy?->tenant?->user?->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Unit</p>
                            <p class="font-medium text-gray-800">{{ $invoice->tenancy?->unit?->unit_number ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Billing Month</p>
                            <p class="font-medium text-gray-800">{{ $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') : '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Estate</p>
                            <p class="font-medium text-gray-800">{{ $invoice->tenancy?->unit?->estate?->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Amount Section -->
                <div class="bg-blue-50 rounded-xl p-6 mb-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-blue-600">Total Amount</p>
                            <p class="text-3xl font-bold text-blue-800">KES {{ number_format($invoice->total_amount, 2) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-green-600">Paid</p>
                            <p class="text-lg font-bold text-green-700">KES {{ number_format($invoice->total_amount - $balanceDue, 2) }}</p>
                        </div>
                    </div>
                    @if($balanceDue > 0)
                    <div class="mt-3 pt-3 border-t border-blue-200 flex justify-between">
                        <span class="text-sm text-blue-600">Balance Due</span>
                        <span class="text-lg font-bold text-red-600">KES {{ number_format($balanceDue, 2) }}</span>
                    </div>
                    @endif
                </div>

                <!-- Payment Form -->
                @if($invoice->status !== 'paid')
                <form @submit.prevent="submitPayment()" class="space-y-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                            📱 M-Pesa Phone Number *
                        </label>
                        <input 
                            type="tel" 
                            id="phone"
                            x-model="phone"
                            placeholder="0712345678"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-1">Enter the phone number registered with M-Pesa</p>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Amount to Pay *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-500">KES</span>
                            <input 
                                type="number" 
                                id="amount"
                                x-model="amount"
                                step="0.01"
                                min="0.01"
                                max="{{ $balanceDue }}"
                                :class="{'border-red-300 focus:border-red-500': amount > {{ $balanceDue }}}"
                                class="w-full pl-16 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                required
                            >
                        </div>
                        <div class="flex justify-between mt-1">
                            <p class="text-xs text-gray-500">Max: KES {{ number_format($balanceDue, 2) }}</p>
                            <button 
                                type="button"
                                @click="amount = {{ $balanceDue }}"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                            >
                                Pay Full Balance
                            </button>
                        </div>
                    </div>

                    <!-- Error Messages -->
                    <div x-show="errorMessage" x-transition 
                         class="bg-red-50 text-red-800 p-4 rounded-lg text-sm">
                        <span x-text="errorMessage"></span>
                    </div>

                    <!-- M-Pesa Status -->
                    <div x-show="status.show" x-transition
                         class="p-4 rounded-lg border text-sm"
                         :class="status.class">
                        <div class="flex items-center gap-3">
                            <div x-show="status.loading" class="loader flex-shrink-0"></div>
                            <div x-html="status.icon" x-show="!status.loading" class="text-2xl"></div>
                            <div>
                                <p class="font-medium" x-text="status.title"></p>
                                <p class="text-sm opacity-90" x-text="status.message"></p>
                            </div>
                        </div>
                        <div x-show="status.progress" class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500" 
                                     :style="'width: ' + status.progress + '%'"
                                     :class="status.progressClass"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit"
                        x-show="!status.show || status.status === 'failed'"
                        x-bind:disabled="loading || !phone || !amount || parseFloat(amount) > {{ $balanceDue }}"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading">💳 Pay with M-Pesa</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </form>
                @else
                <div class="bg-green-50 text-green-800 p-6 rounded-lg text-center">
                    <p class="text-2xl mb-2">✅</p>
                    <p class="text-lg font-bold">Invoice Already Paid</p>
                    <p class="text-sm">This invoice has been fully paid. Thank you!</p>
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="text-center mt-8">
                <p class="text-sm text-gray-500">
                    {{ config('app.name') }} - Secure Payment Gateway
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    You will receive an STK Push on your phone to confirm the payment
                </p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('publicInvoicePayment', () => ({
            phone: '{{ $phone ?? '' }}',
            amount: '{{ $balanceDue }}',
            loading: false,
            errorMessage: '',
            invoiceId: {{ $invoice->id }},
            checkoutId: null,
            status: {
                show: false,
                status: 'pending',
                title: '',
                message: '',
                icon: '',
                class: '',
                loading: false,
                progress: 0,
                progressClass: '',
            },
            pollInterval: null,
            pollAttempts: 0,
            maxPollAttempts: 30,

            init() {
                // Auto-focus the phone input
                setTimeout(() => {
                    const phoneInput = document.getElementById('phone');
                    if (phoneInput) phoneInput.focus();
                }, 500);

                // Check for any existing payment status on page load
                const urlParams = new URLSearchParams(window.location.search);
                const existingCheckout = urlParams.get('checkout');
                if (existingCheckout) {
                    this.checkoutId = existingCheckout;
                    this.startPolling();
                }
            },

            async submitPayment() {
                this.errorMessage = '';
                this.loading = true;

                const amount = parseFloat(this.amount);
                const maxAmount = {{ $balanceDue }};

                if (!this.phone) {
                    this.errorMessage = 'Please enter your M-Pesa phone number';
                    this.loading = false;
                    return;
                }

                if (!amount || amount <= 0) {
                    this.errorMessage = 'Please enter a valid amount';
                    this.loading = false;
                    return;
                }

                if (amount > maxAmount) {
                    this.errorMessage = `Amount cannot exceed KES ${maxAmount.toFixed(2)}`;
                    this.loading = false;
                    return;
                }

                try {
                    const response = await fetch(`/public/invoice/${this.invoiceId}/pay`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            phone: this.phone,
                            amount: amount
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.checkoutId = data.checkout_request_id;
                        // Update URL with checkout ID for refresh persistence
                        const url = new URL(window.location);
                        url.searchParams.set('checkout', this.checkoutId);
                        window.history.pushState({}, '', url);
                        
                        this.showStatus('pending', '📱 STK Push Sent!', 
                            `Please check your phone (${this.phone}) and enter your M-Pesa PIN.`,
                            'bg-yellow-50 text-yellow-800 border-yellow-200'
                        );
                        this.startPolling();
                    } else {
                        this.errorMessage = data.message || 'Failed to initiate payment. Please try again.';
                    }
                } catch (error) {
                    console.error('Payment error:', error);
                    this.errorMessage = 'An error occurred. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            showStatus(status, title, message, className) {
                this.status.show = true;
                this.status.status = status;
                this.status.title = title;
                this.status.message = message;
                this.status.class = className || 'bg-gray-50 text-gray-800 border-gray-200';
                this.status.loading = status === 'pending';
                this.status.icon = status === 'success' ? '✅' : (status === 'failed' ? '❌' : '');
                this.status.progress = status === 'pending' ? 0 : (status === 'success' ? 100 : 0);
                this.status.progressClass = status === 'pending' ? 'bg-blue-600' : 
                                            (status === 'success' ? 'bg-green-600' : 'bg-red-600');
            },

            startPolling() {
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                }

                this.pollAttempts = 0;
                
                // Update progress every 2 seconds
                this.pollInterval = setInterval(() => {
                    this.pollAttempts++;
                    this.status.progress = Math.min((this.pollAttempts / this.maxPollAttempts) * 100, 95);
                    
                    this.checkStatus();
                    
                    if (this.pollAttempts >= this.maxPollAttempts) {
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                        this.status.progress = 100;
                        this.status.loading = false;
                    }
                }, 6000);
            },

            async checkStatus() {
                if (!this.checkoutId) return;

                try {
                    const response = await fetch(`/public/invoice/${this.invoiceId}/status/${this.checkoutId}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (data.success && data.status === 'completed') {
                        // Payment successful
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                        this.status.progress = 100;
                        this.status.loading = false;
                        
                        this.showStatus('success', '✅ Payment Successful!', 
                            'Your payment has been confirmed. Thank you!',
                            'bg-green-50 text-green-800 border-green-200'
                        );
                        
                        // Reload the page to show updated status after a delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 6000);
                    } else if (data.status === 'failed' || (data.success === false && data.status === 'failed')) {
                        // Payment failed
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                        this.status.progress = 100;
                        this.status.loading = false;
                        
                        this.showStatus('failed', '❌ Payment Failed', 
                            data.message || 'Payment was not successful. Please try again.',
                            'bg-red-50 text-red-800 border-red-200'
                        );
                    }
                    // Otherwise keep polling (pending)
                } catch (error) {
                    console.error('Status check error:', error);
                    // Don't stop polling on network errors
                }
            }
        }));
    });
    </script>
</body>
</html>