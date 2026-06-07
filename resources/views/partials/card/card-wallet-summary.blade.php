{{-- resources/views/partials/card/card-wallet-summary.blade.php --}}
<div x-data="walletComponent()" x-init="init()" class="space-y-5">
    {{-- Two Column Layout --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        {{-- Wallet Column --}}
        <div class="xl:col-span-6 2xl:col-span-6">
            <div class="rounded-[18px] border border-gray-200 bg-gray-100 p-1.5 dark:border-gray-800 dark:bg-white/3">
                <div class="rounded-xl bg-white p-6 pb-8 dark:bg-gray-900">
                    {{-- Header with Currency and Date Selectors --}}
                    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row">
                        <div>
                            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">My Wallet</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Overview of your current funds</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            {{-- Currency Dropdown --}}
                            <div x-data="{ openCurrency: false }" @click.outside="openCurrency = false" class="relative">
                                <button @click="openCurrency = !openCurrency"
                                    class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-2.5 text-sm font-medium text-gray-700 shadow-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    <span class="size-4 rounded-full inline-flex items-center justify-center bg-gray-200 dark:bg-gray-700 text-[10px] font-bold" x-text="selectedCurrency.charAt(0)"></span>
                                    <span x-text="selectedCurrency"></span>
                                    <svg :class="openCurrency ? 'rotate-180' : ''" width="18" height="18"
                                        viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.3125 7.21875L9 11.9063L13.6875 7.21875" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                                <div x-show="openCurrency"
                                    class="absolute right-0 z-50 mt-1.5 w-44 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                    <template x-for="currency in currencies" :key="currency.code">
                                        <button @click="selectedCurrency = currency.code; openCurrency = false; fetchExchangeRates()"
                                            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                                            :class="selectedCurrency === currency.code ? 'bg-gray-100 dark:bg-white/5 font-medium' : 'font-normal'">
                                            <span class="size-4 rounded-full inline-flex items-center justify-center bg-gray-200 dark:bg-gray-700 text-[10px] font-bold" x-text="currency.code.charAt(0)"></span>
                                            <span x-text="currency.code"></span>
                                            <span class="text-xs text-gray-400" x-text="currency.name"></span>
                                            <svg x-show="selectedCurrency === currency.code" class="ml-auto" width="14"
                                                height="14" viewBox="0 0 14 14" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.625 7L5.25 9.625L11.375 3.5" stroke="#465FFF"
                                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Period Dropdown with Flatpickr --}}
                            <div x-data="{ openDate: false, selectedDate: 'June 2025' }" @click.outside="openDate = false"
                                class="relative">
                                <button @click="openDate = !openDate"
                                    class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    <span x-text="selectedDate"></span>
                                    <svg :class="openDate ? 'rotate-180' : ''" width="18" height="18"
                                        viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.3125 7.21875L9 11.9063L13.6875 7.21875" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                                <div x-show="openDate"
                                    class="absolute right-0 z-50 mt-1.5 w-38 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                    <template x-for="period in periods" :key="period">
                                        <button @click="selectedDate = period; openDate = false"
                                            class="w-full rounded-lg px-2.5 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                            <span x-text="period"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Main Balance Section --}}
                    <div class="flex flex-col flex-wrap items-start justify-between gap-4 border-b border-dashed border-gray-200 pb-6 dark:border-gray-800 sm:flex-row sm:items-end">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Balance</p>
                            <h3 class="mt-1 text-3xl font-medium text-gray-800 dark:text-white/90">
                                <span x-text="selectedCurrency"></span> <span x-text="formatNumber(convertedBalance)"></span>
                            </h3>
                            <p class="mt-2 flex items-center gap-1.5 text-sm font-normal text-gray-500 dark:text-gray-400">
                                <span class="text-success-600 flex items-center gap-1 font-medium">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7.9974 2.66602L7.9974 13.3336M4 6.66334L7.99987 2.66602L12 6.66334"
                                            stroke="#039855" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span x-text="balanceChange"></span>%
                                </span>
                                than last month
                            </p>
                        </div>
                        <div class="ml-auto w-25 sm:w-[150px]">
                            <div id="wallet-spark-chart"></div>
                        </div>
                    </div>

                    {{-- Expense Breakdown --}}
                    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Spent</p>
                            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <span x-text="selectedCurrency"></span> <span x-text="formatNumber(convertedSpent)"></span>
                            </p>
                            <span class="text-success-600 text-xs">+2.5%</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Rent</p>
                            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <span x-text="selectedCurrency"></span> <span x-text="formatNumber(convertedRent)"></span>
                            </p>
                            <span class="text-error-600 text-xs">-0%</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Water Bill</p>
                            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <span x-text="selectedCurrency"></span> <span x-text="formatNumber(convertedWater)"></span>
                            </p>
                            <span class="text-error-600 text-xs">+5%</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Electricity</p>
                            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <span x-text="selectedCurrency"></span> <span x-text="formatNumber(convertedElectricity)"></span>
                            </p>
                            <span class="text-success-600 text-xs">-12%</span>
                        </div>
                    </div>

                    {{-- Account Details Row --}}
                    <div class="mt-6 flex flex-col gap-2 border-t border-dashed border-gray-200 pt-6 dark:border-gray-800 sm:flex-row sm:items-center">
                        <p class="shrink-0 text-sm text-gray-700 dark:text-gray-400">Primary Account:</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="shrink-0 text-lg font-medium text-gray-700 dark:text-gray-400">
                                •••• •••• •••• 5332
                            </p>
                            <div x-data="{ copied: false }" class="shrink-0">
                                <button @click="copied = true; navigator.clipboard.writeText('•••• •••• •••• 5332'); setTimeout(() => copied = false, 2000)"
                                    class="relative flex h-8 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    <svg x-show="!copied" width="20" height="20" fill="none" viewBox="0 0 20 20">
                                        <path d="M14.1559 14.1628H7.08724C6.39688 14.1628 5.83724 13.6032 5.83724 12.9128V5.84416M14.1559 14.1628V15.4161C14.1559 16.1065 13.5963 16.6661 12.9059 16.6661H4.58398C3.89363 16.6661 3.33398 16.1065 3.33398 15.4161V7.09416C3.33398 6.4038 3.89363 5.84416 4.58398 5.84416H5.83724M14.1559 14.1628H15.4144C16.1048 14.1628 16.6644 13.6032 16.6644 12.9128V4.58398C16.6644 3.89363 16.1048 3.33398 15.4144 3.33398H7.08724C6.39688 3.33398 5.83724 3.89363 5.83724 4.58398V5.84416"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <svg x-show="copied" class="text-success-500" width="20" height="20" fill="none"
                                        viewBox="0 0 20 20">
                                        <path d="M16.6668 5L7.50016 14.1667L3.3335 10" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>
                            <button @click="openStatementModal"
                                class="flex h-8 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                See Details
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap gap-3 px-3.5 pt-5 pb-4">
                    <button @click="openDepositModal"
                        class="bg-brand-500 hover:bg-brand-600 flex h-11 flex-1 shrink-0 items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.9968 5.00356L5 15.0003M14.9977 12.4949L14.9953 5.00214L7.49917 4.99951" stroke="white"
                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Deposit
                    </button>
                    <button @click="openPaymentModal"
                        class="flex h-11 flex-1 shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5.00095 14.9963L14.9977 4.99954M5 7.50539L5.00238 14.9981L12.4985 15.0007"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Pay
                    </button>
                    <button @click="openScheduleModal"
                        class="flex h-11 flex-1 shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 10.0002H15.0006M10.0002 5V15.0006" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Schedule
                    </button>
                    <button @click="openStatementModal"
                        class="flex h-11 flex-1 shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.5 5.83333L10 10L17.5 5.83333M2.5 14.1667L10 18.3333L17.5 14.1667M2.5 10L10 14.1667L17.5 10"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Statement
                    </button>
                </div>
            </div>
        </div>

        {{-- Credit Cards Column --}}
        <div class="xl:col-span-6 2xl:col-span-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">My Cards</h3>
                    <button @click="openAddCardModal"
                        class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-2.5 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-900">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 10.0002H15.0006M10.0002 5V15.0006" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Add Card
                    </button>
                </div>

                {{-- Card Slider - Single card at a time --}}
                <div class="swiper my-cards-slider relative">
                    <div class="swiper-wrapper">
                        <template x-for="card in cards" :key="card.id">
                            <div class="swiper-slide">
                                <div class="relative flex flex-col gap-7 overflow-hidden rounded-[14px] p-6 min-h-[280px]"
                                    :class="card.bgClass">
                                    {{-- Card Vector Background (Fallback) --}}
                                    <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-white/10 to-transparent rounded-bl-[60px]"></div>
                                    
                                    {{-- Card Header --}}
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-4">
                                            {{-- Chip Icon --}}
                                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="32" height="32" rx="6" fill="url(#chipGradient)"/>
                                                <path d="M12 12L16 8L20 12L16 16L12 12Z" fill="white" fill-opacity="0.8"/>
                                                <defs>
                                                    <linearGradient id="chipGradient" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                                                        <stop stop-color="#FFD700"/>
                                                        <stop offset="1" stop-color="#FFA500"/>
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                            
                                            {{-- Status Badge --}}
                                            <span class="flex h-6 shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="card.active ? 'bg-success-500/10 text-success-500' : 'bg-white/10 text-gray-400'"
                                                x-text="card.active ? 'Active' : 'Inactive'"></span>
                                        </div>
                                        <div>
                                            {{-- Card Type Icon Fallback --}}
                                            <div class="h-8 w-12 rounded bg-white/10 flex items-center justify-center">
                                                <span class="text-white text-xs font-bold" x-text="card.cardType === 'mastercard' ? 'MC' : 'VISA'"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Cardholder Name --}}
                                    <div>
                                        <h3 class="text-base font-normal text-white" x-text="card.cardholderName"></h3>
                                    </div>
                                    
                                    {{-- Card Details Footer --}}
                                    <div class="flex justify-between gap-4">
                                        <div class="flex-1">
                                            <p class="text-xs text-white/60">Card Number</p>
                                            <p class="text-base font-normal text-white font-mono tracking-wide" x-text="'•••• •••• •••• ' + card.cardNumber"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-white/60">EXP</p>
                                            <p class="text-base font-normal text-white font-mono" x-text="card.expiry"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-white/60">CVC</p>
                                            <p class="text-base font-normal text-white font-mono" x-text="card.cvc"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Virtual Card and Navigation Section --}}
                <div class="flex items-center justify-between border-b border-dashed border-gray-200 pt-4 pb-6 dark:border-gray-800">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-white/90">Virtual Card</h3>
                    <div class="flex gap-1.5">
                        <button id="card-slider-prev"
                            class="flex h-8 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-900">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9.58464 3.83325L5.41797 7.99992L9.58464 12.1666" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button id="card-slider-next"
                            class="flex h-8 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-900">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.91797 12.1666L10.0846 7.99992L5.91797 3.83325" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Transactions Table - Full Width --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/3">
        <div class="mb-4 flex flex-col gap-5 px-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Transactions</h3>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2">
                        <svg class="fill-gray-500 dark:fill-gray-400" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z" fill=""/>
                        </svg>
                    </span>
                    <input type="text" x-model="searchQuery" placeholder="Search transactions..."
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-[42px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                </div>
                <button @click="openStatementModal"
                    class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                    <svg class="fill-white stroke-current dark:fill-gray-800" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.5 5.83333L10 10L17.5 5.83333M2.5 14.1667L10 18.3333L17.5 14.1667M2.5 10L10 14.1667L17.5 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Full Statement
                </button>
            </div>
        </div>

        <div class="custom-scrollbar max-w-full overflow-x-auto">
            <table class="min-w-full">
                <thead class="border-y border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left whitespace-nowrap">
                            <span class="text-theme-xs block font-medium text-gray-500 dark:text-gray-400">Transaction ID</span>
                        </th>
                        <th class="px-6 py-3 text-left whitespace-nowrap">
                            <span class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Description</span>
                        </th>
                        <th class="px-6 py-3 text-left whitespace-nowrap">
                            <span class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Category</span>
                        </th>
                        <th class="px-6 py-3 text-left whitespace-nowrap">
                            <span class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Date</span>
                        </th>
                        <th class="px-6 py-3 text-right whitespace-nowrap">
                            <span class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Amount</span>
                        </th>
                        <th class="px-6 py-3 text-center whitespace-nowrap">
                            <span class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Status</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="transaction in paginatedTransactions" :key="transaction.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-theme-sm font-medium text-gray-700 dark:text-gray-400" x-text="'TXN-' + transaction.id.toString().padStart(6, '0')"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-gray-200 shadow-xs dark:border-gray-800 bg-gray-100 dark:bg-gray-800">
                                        <svg class="size-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90" x-text="transaction.title"></p>
                                        <p class="text-theme-xs text-gray-500 dark:text-gray-400" x-text="transaction.description"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="getCategoryClass(transaction.category)" x-text="transaction.category">
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-theme-sm text-gray-700 dark:text-gray-400" x-text="transaction.date"></span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <p class="text-theme-sm font-medium"
                                    :class="transaction.type === 'credit' ? 'text-success-600' : 'text-error-600'">
                                    <span x-text="transaction.type === 'credit' ? '+' : '-'"></span>
                                    <span x-text="selectedCurrency"></span> <span x-text="formatNumber(convertAmount(transaction.amount))"></span>
                                </p>
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="transaction.status === 'completed' ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500' : 
                                           (transaction.status === 'pending' ? 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400' : 
                                           'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500')"
                                    x-text="transaction.status">
                                </span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Empty State --}}
        <div x-show="filteredTransactions.length === 0" class="py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No transactions found matching your search.</p>
        </div>

        {{-- Pagination --}}
        <div x-show="filteredTransactions.length > 0" class="flex items-center justify-between border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Showing <span x-text="((currentPage - 1) * itemsPerPage) + 1"></span> to 
                <span x-text="Math.min(currentPage * itemsPerPage, filteredTransactions.length)"></span> of 
                <span x-text="filteredTransactions.length"></span> entries
            </p>
            <div class="flex gap-2">
                <button @click="prevPage" :disabled="currentPage === 1"
                    class="rounded-lg border border-gray-300 px-3 py-1 text-sm disabled:opacity-50 dark:border-gray-700">
                    Previous
                </button>
                <span class="px-3 py-1 text-sm" x-text="`Page ${currentPage} of ${totalPages}`"></span>
                <button @click="nextPage" :disabled="currentPage === totalPages"
                    class="rounded-lg border border-gray-300 px-3 py-1 text-sm disabled:opacity-50 dark:border-gray-700">
                    Next
                </button>
            </div>
        </div>
    </div>

    {{-- Include Modal Partials --}}
    @include('partials.modal.wallet-deposit-modal')
    @include('partials.modal.wallet-payment-modal')
    @include('partials.modal.wallet-schedule-modal')
    @include('partials.modal.wallet-statement-modal')
    @include('partials.modal.wallet-add-card-modal')
</div>

<script>
function walletComponent() {
    return {
        // Currency & Exchange Rates
        selectedCurrency: 'KES',
        exchangeRates: { USD: 0.0076, EUR: 0.0070, GBP: 0.0060, JPY: 1.18, KES: 1 },
        currencies: [
            { code: 'KES', name: 'Kenyan Shilling' },
            { code: 'USD', name: 'US Dollar' },
            { code: 'EUR', name: 'Euro' },
            { code: 'GBP', name: 'British Pound' },
            { code: 'JPY', name: 'Japanese Yen' },
        ],
        periods: ['June 2025', 'May 2025', 'Apr 2025', 'Q1 2025', 'Last 30 Days', 'Last 7 Days'],

        // Wallet Data (KES base)
        baseBalance: 19857.00,
        baseSpent: 3831.00,
        baseRent: 1200.00,
        baseWater: 85.50,
        baseElectricity: 210.30,
        balanceChange: 3.2,

        // Cards Data - Single card per slide
        cards: [
            { id: 1, cardholderName: 'Musharof Chy', cardNumber: '4983', expiry: '09/29', cvc: '659', active: true, cardType: 'mastercard', bgClass: 'bg-gradient-to-br from-gray-800 to-gray-900 dark:from-gray-900 dark:to-gray-950' },
            // { id: 2, cardholderName: 'Jhon Wick', cardNumber: '1234', expiry: '12/28', cvc: '123', active: true, cardType: 'visa', bgClass: 'bg-gradient-to-br from-blue-800 to-indigo-900' },
            // { id: 3, cardholderName: 'Adward John', cardNumber: '5678', expiry: '10/27', cvc: '987', active: false, cardType: 'mastercard', bgClass: 'bg-gradient-to-br from-gray-600 to-gray-800 dark:from-gray-700 dark:to-gray-900' }
        ],

        // Recent Transactions
        recentTransactions: [
            { id: 1, title: 'Payment Received', description: 'Cashback from Stellar Rewards', amount: 120.00, type: 'credit', date: 'Mar 20, 2025', category: 'Income', status: 'completed' },
            { id: 2, title: 'Netflix Subscription', description: 'September subscription charge', amount: 36.24, type: 'debit', date: 'Sep 18, 2025', category: 'Entertainment', status: 'completed' },
            { id: 3, title: 'Money received', description: 'Payment received via PayPal', amount: 590.00, type: 'credit', date: 'Feb 12, 2025', category: 'Income', status: 'completed' },
            { id: 4, title: 'Google Ads', description: 'Payment received from google ads', amount: 236.24, type: 'credit', date: 'Jan 28, 2025', category: 'Income', status: 'pending' },
            { id: 5, title: 'Money received', description: 'Payment received via PayPal', amount: 1093.00, type: 'credit', date: 'Jan 10, 2025', category: 'Income', status: 'completed' },
            { id: 6, title: 'Electricity Bill', description: 'KPLC Monthly Payment', amount: 210.30, type: 'debit', date: 'Jan 5, 2025', category: 'Utilities', status: 'completed' },
            { id: 7, title: 'Rent Payment', description: 'Monthly Rent - Apartment', amount: 1200.00, type: 'debit', date: 'Jan 1, 2025', category: 'Housing', status: 'completed' },
            { id: 8, title: 'Water Bill', description: 'Nairobi Water Company', amount: 85.50, type: 'debit', date: 'Dec 28, 2024', category: 'Utilities', status: 'completed' }
        ],

        // Pagination and Search
        currentPage: 1,
        itemsPerPage: 10,
        searchQuery: '',

        // Computed converted values
        get convertedBalance() { return this.baseBalance * (this.exchangeRates[this.selectedCurrency] || 1); },
        get convertedSpent() { return this.baseSpent * (this.exchangeRates[this.selectedCurrency] || 1); },
        get convertedRent() { return this.baseRent * (this.exchangeRates[this.selectedCurrency] || 1); },
        get convertedWater() { return this.baseWater * (this.exchangeRates[this.selectedCurrency] || 1); },
        get convertedElectricity() { return this.baseElectricity * (this.exchangeRates[this.selectedCurrency] || 1); },

        // Filtered transactions based on search
        get filteredTransactions() {
            let filtered = [...this.recentTransactions];
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(t => 
                    t.title.toLowerCase().includes(query) || 
                    t.description.toLowerCase().includes(query) ||
                    t.category.toLowerCase().includes(query)
                );
            }
            filtered.sort((a, b) => new Date(b.date) - new Date(a.date));
            return filtered;
        },

        get totalPages() {
            return Math.ceil(this.filteredTransactions.length / this.itemsPerPage);
        },

        get paginatedTransactions() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredTransactions.slice(start, start + this.itemsPerPage);
        },

        init() {
            this.fetchExchangeRates();
            this.initSwiper();
            this.initFlatpickr();
        },

        getCategoryClass(category) {
            const classes = {
                'Income': 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                'Housing': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                'Utilities': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                'Entertainment': 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
            };
            return classes[category] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
        },

        async fetchExchangeRates() {
            try {
                const response = await fetch('https://api.exchangerate-api.com/v4/latest/KES');
                const data = await response.json();
                if (data.rates) {
                    this.exchangeRates = {
                        KES: 1,
                        USD: data.rates.USD,
                        EUR: data.rates.EUR,
                        GBP: data.rates.GBP,
                        JPY: data.rates.JPY
                    };
                }
            } catch (error) {
                console.warn('Exchange rate API failed, using fallback rates:', error);
            }
        },

        convertAmount(amount) {
            return amount * (this.exchangeRates[this.selectedCurrency] || 1);
        },

        formatNumber(value) {
            return new Intl.NumberFormat('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value || 0);
        },

        initSwiper() {
            setTimeout(() => {
                if (typeof Swiper !== 'undefined' && document.querySelector('.my-cards-slider')) {
                    if (this.swiperInstance) {
                        this.swiperInstance.destroy(true, true);
                    }
                    
                    this.swiperInstance = new Swiper('.my-cards-slider', {
                        slidesPerView: 1,
                        spaceBetween: 20,
                        navigation: { 
                            nextEl: '#card-slider-next', 
                            prevEl: '#card-slider-prev' 
                        },
                        loop: this.cards.length > 1,
                        autoplay: this.cards.length > 1 ? { delay: 5000, disableOnInteraction: false } : false,
                        speed: 400
                    });
                }
            }, 100);
        },

        initFlatpickr() {
            setTimeout(() => {
                const dateInputs = document.querySelectorAll('.flatpickr-date');
                if (dateInputs.length && typeof flatpickr !== 'undefined') {
                    dateInputs.forEach(input => {
                        flatpickr(input, {
                            dateFormat: 'Y-m-d',
                            allowInput: true
                        });
                    });
                }
            }, 100);
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        // Modal Triggers
        openDepositModal() { if (window.walletDepositModal) window.walletDepositModal.openModal(); },
        openPaymentModal() { if (window.walletPaymentModal) window.walletPaymentModal.openModal(); },
        openScheduleModal() { if (window.walletScheduleModal) window.walletScheduleModal.openModal(); },
        openStatementModal() { if (window.walletStatementModal) window.walletStatementModal.openModal(); },
        openAddCardModal() { if (window.walletAddCardModal) window.walletAddCardModal.openModal(); }
    };
}
</script>