@extends('layouts.app')

@section('content')
<div x-data="invoicePage()">
    <!-- Overview -->
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-800 dark:text-white/90">Overview</h2>
            </div>
            <div class="flex gap-3">
                <button @click="generateAllInvoices" :disabled="isGeneratingAll" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="animate-spin" x-show="isGeneratingAll" x-cloak>
                        <path d="M10 3C6.13401 3 3 6.13401 3 10C3 10.2761 2.77614 10.5 2.5 10.5C2.22386 10.5 2 10.2761 2 10C2 5.58172 5.58172 2 10 2C14.4183 2 18 5.58172 18 10C18 14.4183 14.4183 18 10 18C9.72386 18 9.5 17.7761 9.5 17.5C9.5 17.2239 9.72386 17 10 17C13.866 17 17 13.866 17 10C17 6.13401 13.866 3 10 3Z" fill="white"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" x-show="!isGeneratingAll" x-cloak>
                        <path d="M5 10.0002H15.0006M10.0002 5V15.0006" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span x-text="isGeneratingAll ? 'Generating...' : 'Generate Pending Invoices'"></span>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-1 rounded-xl border border-gray-200 sm:grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-y-0 dark:divide-gray-800 dark:border-gray-800">
            <div class="border-b p-5 sm:border-r lg:border-b-0">
                <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Draft</p>
                <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalDraft, 2) }}</h3>
            </div>
            <div class="border-b p-5 lg:border-b-0">
                <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Unpaid</p>
                <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalUnpaid, 2) }}</h3>
            </div>
            <div class="border-b p-5 sm:border-r sm:border-b-0">
                <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Partially Paid</p>
                <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalPartial, 2) }}</h3>
            </div>
            <div class="p-5">
                <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Paid</p>
                <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalPaid, 2) }}</h3>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="invoiceTable()" x-init="init()">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Invoices</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Your most recent invoices list</p>
            </div>
            <div class="flex gap-3.5">
                <div class="hidden flex-col gap-3 sm:flex sm:flex-row sm:items-center">
                                         <div class="hidden h-11 items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 lg:inline-flex dark:bg-gray-900">
                        <button @click="filterStatus = 'unpaid'; currentPage = 1" :class="filterStatus === 'unpaid' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                            Unpaid (<span x-text="statusCounts.unpaid"></span>)
                        </button>
                        <button @click="filterStatus = 'draft'; currentPage = 1" :class="filterStatus === 'draft' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                            Draft (<span x-text="statusCounts.draft"></span>)
                        </button>
                        <button @click="filterStatus = 'partial'; currentPage = 1" :class="filterStatus === 'partial' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                            Partial (<span x-text="statusCounts.partial"></span>)
                        </button>
                        <button @click="filterStatus = 'paid'; currentPage = 1" :class="filterStatus === 'paid' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                            Paid (<span x-text="statusCounts.paid"></span>)
                        </button>
                        <button @click="filterStatus = 'All'; currentPage = 1" :class="filterStatus === 'All' ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' : 'text-gray-500 dark:text-gray-400'" class="text-theme-sm h-10 rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white">
                            All Invoices  (<span x-text="statusCounts.all"></span>)
                        </button>
                    </div>

                    <div class="relative">
                        <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/>
                            </svg>
                        </span>
                        <input type="text" placeholder="Search invoices..." x-model="searchQuery" @input="currentPage = 1" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"/>
                    </div>


                    
                    <button @click="$dispatch('open-create-invoice-modal')" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M5 10.0002H15.0006M10.0002 5V15.0006" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Create Invoice
                    </button>

                    <!-- In your table header section, add this button next to the existing "Create Invoice" button -->
                    <button @click="$dispatch('open-bulk-invoice-modal')" class="bg-purple-500 shadow-theme-xs hover:bg-purple-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M10 5V15M5 10H15" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Bulk Create
                    </button>
                    
                </div>
            </div>
        </div>
        

        
        <div class="custom-scrollbar overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <th class="p-4 whitespace-nowrap">
                            <div class="flex w-full cursor-pointer items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                                        <span class="relative">
                                            <input type="checkbox" class="sr-only" @change="toggleSelectAll" :checked="isAllSelected"/>
                                            <span :class="isAllSelected ? 'border-brand-500 bg-brand-500' : 'bg-transparent border-gray-300 dark:border-gray-700'" class="flex h-4 w-4 items-center justify-center rounded-sm border-[1.25px]">
                                                <span :class="isAllSelected ? '' : 'opacity-0'">
                                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                                        <path d="M10 3L4.5 8.5L2 6" stroke="white" stroke-width="1.6666" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                    <p class="text-theme-xs font-medium text-gray-700 dark:text-gray-400">Invoice #</p>
                                </div>
                            </div>
                        </th>
                        <th class="cursor-pointer p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400" @click="sort('tenant_name')">
                            <div class="flex items-center gap-3">
                                <p class="text-theme-xs font-medium text-gray-700 dark:text-gray-400">Tenant</p>
                                <span class="flex flex-col gap-0.5">
                                    <svg :class="sortBy === 'tenant_name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/>
                                    </svg>
                                    <svg :class="sortBy === 'tenant_name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/>
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th class="cursor-pointer p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400" @click="sort('created_at')">
                            <div class="flex items-center gap-3">
                                <p class="text-theme-xs font-medium text-gray-700 dark:text-gray-400">Created Date</p>
                                <span class="flex flex-col gap-0.5">
                                    <svg :class="sortBy === 'created_at' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/>
                                    </svg>
                                    <svg :class="sortBy === 'created_at' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/>
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th class="cursor-pointer p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400" @click="sort('billing_month')">
                            <div class="flex items-center gap-3">
                                <p class="text-theme-xs font-medium text-gray-700 dark:text-gray-400">Billing Month</p>
                                <span class="flex flex-col gap-0.5">
                                    <svg :class="sortBy === 'billing_month' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/>
                                    </svg>
                                    <svg :class="sortBy === 'billing_month' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/>
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400" @click="sort('total_amount')">
                            <div class="flex items-center gap-3">
                                <p class="text-theme-xs font-medium text-gray-700 dark:text-gray-400">Total</p>
                                <span class="flex flex-col gap-0.5">
                                    <svg :class="sortBy === 'total_amount' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/>
                                    </svg>
                                    <svg :class="sortBy === 'total_amount' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/>
                                    </svg>
                                </span>
                            </div>
                        </th>
                        <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">
                            Status
                        </th>
                        <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap">
                                <div class="group flex items-center gap-3">
                                    <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                                        <span class="relative">
                                            <input type="checkbox" class="sr-only" :checked="selected.includes(invoice.id)" @change="toggleRow(invoice.id)"/>
                                            <span :class="selected.includes(invoice.id) ? 'border-brand-500 bg-brand-500' : 'bg-transparent border-gray-300 dark:border-gray-700'" class="flex h-4 w-4 items-center justify-center rounded-sm border-[1.25px]">
                                                <span :class="selected.includes(invoice.id) ? '' : 'opacity-0'">
                                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                                        <path d="M10 3L4.5 8.5L2 6" stroke="white" stroke-width="1.6666" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                    <!-- Invoice link -->
                                    <a
                                        :href="`/invoices/${invoice.id}`"
                                        class="text-theme-xs font-medium text-gray-700 group-hover:underline dark:text-gray-400"
                                        x-text="'#' + invoice.id"
                                    ></a>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400" x-text="invoice.tenant_name || '-'"></span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Unit: ' + (invoice.unit_number || '-')"></p>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <p class="text-sm text-gray-700 dark:text-gray-400" x-text="invoice.created_at_formatted"></p>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <p class="text-sm text-gray-700 dark:text-gray-400" x-text="invoice.billing_month_formatted || '-'"></p>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-400" x-text="'{{ SystemHelper::currencySymbol() }} ' + formatCurrency(invoice.total_amount)"></p>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <span :class="getStatusClass(invoice.status)" class="text-theme-xs rounded-full px-2 py-0.5 font-medium" x-text="formatStatus(invoice.status)"></span>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <template x-if="invoice.status !== 'paid'">
                                        <button @click="showPaymentModal(invoice)" class="text-xs text-success-500 hover:text-success-600">
                                            Pay
                                        </button>
                                    </template>
                                    <button @click="generateInvoiceForTenancy(invoice.tenancy_id)" :disabled="isGenerating === invoice.tenancy_id" class="text-xs text-brand-500 hover:text-brand-600 disabled:opacity-50">
                                        <span x-text="isGenerating === invoice.tenancy_id ? 'Generating...' : 'Generate'"></span>
                                    </button>
                                    <div x-data="dropdown()" class="relative">
                                        <button @click="toggle" class="text-gray-500 dark:text-gray-400">
                                            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/>
                                            </svg>
                                        </button>
                                        <div x-show="open" @click.outside="open = false" class="shadow-theme-lg dark:bg-gray-dark absolute right-0 z-10 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800" x-ref="dropdown">
                                            <a :href="'/invoices/' + invoice.id" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                                View
                                            </a>
                                            <form :action="'/invoices/' + invoice.id" method="POST" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" onclick="return confirm('Delete this invoice?')" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 sm:flex-row dark:border-gray-800">
            <div class="pb-3 sm:pb-0">
                <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
                    Showing
                    <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + (paginatedInvoices.length ? 1 : 0)"></span>
                    to
                    <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + paginatedInvoices.length"></span>
                    of
                    <span class="text-gray-800 dark:text-white/90" x-text="filteredInvoices.length"></span>
                </span>
            </div>
            <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
                <button class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200" @click="previousPage" :disabled="currentPage === 1" :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''">
                    <span>
                        <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/>
                        </svg>
                    </span>
                </button>
                <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
                <ul class="hidden items-center gap-0.5 sm:flex">
                    <template x-for="page in visiblePages" :key="page">
                        <li>
                            <a href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a>
                        </li>
                    </template>
                    <template x-if="visiblePages[visiblePages.length-1] < totalPages">
                        <li>
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 dark:text-gray-400">...</span>
                        </li>
                    </template>
                    <template x-if="visiblePages[visiblePages.length-1] < totalPages">
                        <li>
                            <a href="#" @click.prevent="goToPage(totalPages)" class="hover:bg-brand-500 flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white" x-text="totalPages"></a>
                        </li>
                    </template>
                </ul>
                <button class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200" @click="nextPage" :disabled="currentPage === totalPages" :class="currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''">
                    <span>
                        <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/>
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Create Invoice Modal -->
<div x-data="createInvoiceModal()" 
     x-show="showModal" 
     x-cloak
     @open-create-invoice-modal.window="showModal = true"
     @close-create-invoice-modal.window="showModal = false">
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div 
            @click.outside="showModal = false"
            class="relative w-full max-w-[800px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10 max-h-[90vh] overflow-y-auto"
        >
            <!-- close btn -->
            <button
                @click="showModal = false"
                class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <form @submit.prevent="submitInvoice()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Create Invoice
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <!-- Tenancy Selection -->
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Tenancy *
                        </label>
                        <div class="space-y-2">
                            <select
                                x-model="form.tenancy_id"
                                @change="onTenancyChange"
                                required
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            >
                                <option value="">Select Tenancy</option>
                                @foreach($activeTenancies as $tenancy)
                                <option value="{{ $tenancy->id }}">
                                    {{ $tenancy->tenant->user->name ?? 'Unknown' }} - {{ $tenancy->unit->unit_number ?? 'No Unit' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Invoice Type Selection (Based on your database enum) -->
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Invoice Type *
                        </label>
                        <select
                            x-model="form.invoice_type"
                            @change="onInvoiceTypeChange"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="">Select Invoice Type</option>
                            <option value="move_in">Move In</option>
                            <option value="monthly">Monthly</option>
                            <option value="move_out">Move Out</option>
                        </select>
                    </div>

                    <!-- Item Type Selection (Based on your database enum) -->
                    <div class="col-span-1" x-show="form.invoice_type === 'monthly'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Item Type *
                        </label>
                        <select
                            x-model="form.item_type"
                            @change="onItemTypeChange"
                            :required="form.invoice_type === 'monthly'"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="">Select Item Type</option>
                            <option value="rent">Rent</option>
                            <option value="power">Power/Electricity</option>
                            <option value="internet">Internet</option>
                            <option value="water">Water</option>
                            <option value="security">Security</option>
                            <option value="garbage">Garbage</option>
                        </select>
                    </div>

                    <!-- Specific Service Selection (Group similar services) -->
                    <div class="col-span-1" x-show="form.item_type && ['power', 'internet', 'water', 'gas'].includes(form.item_type)">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Service Category
                        </label>
                        <div class="mt-2">
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                Utility
                            </span>
                        </div>
                    </div>

                    <div class="col-span-1" x-show="form.item_type && ['security', 'garbage', 'cleaning', 'maintenance'].includes(form.item_type)">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Service Category
                        </label>
                        <div class="mt-2">
                            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                                Service Charge
                            </span>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Description *
                        </label>
                        <input
                            type="text"
                            x-model="form.description"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="e.g., Monthly Rent, Water Charges, etc."
                        />
                    </div>

                    <!-- Billing Month -->
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Billing Month *
                        </label>
                        <input
                            type="month"
                            x-model="form.billing_month"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <!-- Amount -->
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Amount *
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">{{ SystemHelper::currencySymbol() }}</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                x-model="form.amount"
                                required
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                placeholder="0.00"
                            />
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Status *
                        </label>
                        <select
                            x-model="form.status"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="draft">Draft</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Notes (Optional)
                        </label>
                        <textarea
                            x-model="form.notes"
                            rows="3"
                            class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="Add any additional notes..."
                        ></textarea>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mb-6 rounded-lg bg-gray-50 dark:bg-gray-800 p-4 mt-6" x-show="form.amount && form.billing_month">
                    <h5 class="font-medium text-gray-800 dark:text-white/90 mb-2">Summary</h5>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Invoice Type: <span class="font-medium capitalize" x-text="getInvoiceTypeLabel()"></span>
                    </p>
                    <p x-show="form.item_type" class="text-sm text-gray-600 dark:text-gray-400">
                        Item Type: <span class="font-medium capitalize" x-text="getItemTypeLabel()"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Description: <span class="font-medium" x-text="form.description"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Amount: <span class="font-medium">{{ SystemHelper::currencySymbol() }} <span x-text="form.amount"></span></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Billing Month: <span class="font-medium" x-text="form.billing_month"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Status: <span class="font-medium capitalize" x-text="form.status"></span>
                    </p>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="showModal = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!isLoading">Create Invoice</span>
                        <span x-show="isLoading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Invoice Modal - ENHANCED VERSION WITH MULTIPLE TENANCY SELECTION -->
<div x-data="bulkInvoiceModal()" 
     x-show="showModal" 
     x-cloak
     @open-bulk-invoice-modal.window="showModal = true"
     @close-bulk-invoice-modal.window="showModal = false">
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div 
            @click.outside="showModal = false"
            class="relative w-full max-w-[1000px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10 max-h-[90vh] overflow-y-auto"
        >
            <!-- close btn -->
            <button
                @click="showModal = false"
                class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/>
                </svg>
            </button>

            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                Create Bulk Invoices
            </h4>

            <form @submit.prevent="submitBulkInvoice()">
                <!-- Invoice Type -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Invoice Type *
                    </label>
                    <select
                        x-model="form.invoice_type"
                        @change="onInvoiceTypeChange"
                        required
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    >
                        <option value="monthly">Monthly</option>
                        <option value="move_in">Move In</option>
                        <option value="move_out">Move Out</option>
                    </select>
                </div>

                <!-- Billing Month -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Billing Month *
                    </label>
                    <input
                        type="month"
                        x-model="form.billing_month"
                        @change="onBillingMonthChange"
                        required
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                </div>

                <!-- Target Selection -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Apply To *
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                               :class="form.apply_to === 'bulk' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" x-model="form.apply_to" value="bulk" class="mr-3">
                            <div>
                                <p class="font-medium text-gray-700 dark:text-gray-300">All Tenancies</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">All active tenancies</p>
                            </div>
                        </label>
                        
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                               :class="form.apply_to === 'single' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" x-model="form.apply_to" value="single" class="mr-3">
                            <div>
                                <p class="font-medium text-gray-700 dark:text-gray-300">Single Tenancy</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">One specific tenancy</p>
                            </div>
                        </label>
                        
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                               :class="form.apply_to === 'multiple' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" x-model="form.apply_to" value="multiple" class="mr-3">
                            <div>
                                <p class="font-medium text-gray-700 dark:text-gray-300">Multiple Tenancies</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Select specific units</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Single Tenancy Selection -->
                <div class="mb-6" x-show="form.apply_to === 'single'">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Select Tenancy *
                    </label>
                    <select
                        x-model="form.tenancy_id"
                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    >
                        <option value="">Select Tenancy</option>
                        @foreach($activeTenancies as $tenancy)
                        <option value="{{ $tenancy->id }}">
                            {{ $tenancy->tenant->user->name ?? 'Unknown' }} - {{ $tenancy->unit->unit_number ?? 'No Unit' }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Multiple Tenancy Selection -->
                <div class="mb-6" x-show="form.apply_to === 'multiple'">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Select Tenancies *
                        </label>
                        <div class="flex gap-2">
                            <button type="button" @click="selectAllTenancies" class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                Select All
                            </button>
                            <button type="button" @click="clearTenancySelection" class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                Clear All
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-sm mb-2" x-show="getSelectedTenancyCount() > 0">
                        <span class="text-gray-600 dark:text-gray-400">Selected:</span>
                        <span class="font-medium ml-2" x-text="getSelectedTenancyCount()"></span>
                        <span class="text-gray-500 dark:text-gray-500 ml-1">tenancy(ies)</span>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg max-h-60 overflow-y-auto dark:border-gray-700">
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($activeTenancies as $tenancy)
                            <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                <input type="checkbox" 
                                       x-model="form.selected_tenancies" 
                                       :value="{{ $tenancy->id }}" 
                                       @change="toggleTenancySelection({{ $tenancy->id }})"
                                       class="mr-3 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-700 dark:text-gray-300">
                                        {{ $tenancy->tenant->user->name ?? 'Unknown' }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Unit: {{ $tenancy->unit->unit_number ?? 'No Unit' }}
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    {{ $tenancy->status }}
                                </span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Check Existing Invoices Button -->
                <div class="mb-6" x-show="formValid">
                    <button type="button" 
                            @click="checkExistingInvoices"
                            :disabled="isCheckingInvoices"
                            class="w-full py-2.5 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        <span x-show="!isCheckingInvoices">🔍 Check Existing Invoices for Selected Month</span>
                        <span x-show="isCheckingInvoices">Checking...</span>
                    </button>
                    
                    <!-- Check Results -->
                    <div x-show="checkResults" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h5 class="text-sm font-medium text-blue-800 dark:text-blue-300">
                                    Invoice Check Results
                                </h5>
                                <div class="mt-1 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-blue-700 dark:text-blue-400">Already have invoices:</span>
                                        <span class="ml-2 font-medium" x-text="checkResults.existing_count"></span>
                                    </div>
                                    <div>
                                        <span class="text-green-700 dark:text-green-400">Will create for:</span>
                                        <span class="ml-2 font-medium" x-text="checkResults.remaining_count"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Invoice Items *
                        </label>
                        <button type="button" @click="addItem" class="text-sm text-brand-500 hover:text-brand-600 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Another Item
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <template x-for="(item, index) in form.items" :key="item.id">
                            <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Item <span x-text="index + 1"></span></span>
                                    <button type="button" @click="removeItem(index)" x-show="form.items.length > 1" class="text-gray-400 hover:text-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Item Type -->
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Item Type *
                                        </label>
                                        <select
                                            x-model="item.item_type"
                                            @change="onItemTypeChange(index)"
                                            :required="form.invoice_type === 'monthly'"
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        >
                                            <option value="">Select Item Type</option>
                                            <template x-for="itemType in availableItemTypes" :key="itemType">
                                                <option :value="itemType" x-text="getItemTypeLabel(itemType)"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <!-- Amount -->
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Amount *
                                        </label>
                                        <div class="relative">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span class="text-gray-500 dark:text-gray-400">{{ SystemHelper::currencySymbol() }}</span>
                                            </div>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                x-model="item.amount"
                                                required
                                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                placeholder="0.00"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Service Category Display -->
                                <div class="mt-3" x-show="item.item_type">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Service Category
                                    </label>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                                            :class="{
                                                'bg-blue-100 text-blue-800': getServiceCategory(item.item_type) === 'Utility',
                                                'bg-green-100 text-green-800': getServiceCategory(item.item_type) === 'Service Charge',
                                                'bg-purple-100 text-purple-800': getServiceCategory(item.item_type) === 'Rent',
                                                'bg-gray-100 text-gray-800': getServiceCategory(item.item_type) === 'Other'
                                            }">
                                            <span x-text="getServiceCategory(item.item_type)"></span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="mt-3">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Description
                                    </label>
                                    <textarea
                                        x-model="item.description"
                                        rows="2"
                                        class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        placeholder="Description will be auto-generated..."
                                    ></textarea>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mb-6 rounded-lg bg-gray-50 dark:bg-gray-800 p-4" x-show="getTotalAmount() > 0 && form.billing_month">
                    <h5 class="font-medium text-gray-800 dark:text-white/90 mb-2">Summary</h5>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Invoice Type: <span class="font-medium capitalize" x-text="getInvoiceTypeLabel()"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Billing Month: <span class="font-medium" x-text="formatMonth(form.billing_month)"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Number of Items: <span class="font-medium" x-text="form.items.length"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Total Amount per Tenancy: <span class="font-medium">{{ SystemHelper::currencySymbol() }}<span x-text="getTotalAmount().toFixed(2)"></span></span>
                    </p>
                    
                    <template x-if="form.apply_to === 'bulk'">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Will be applied to: <span class="font-medium">All active tenancies</span>
                        </p>
                    </template>
                    
                    <template x-if="form.apply_to === 'single' && form.tenancy_id">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Will be applied to: <span class="font-medium">Selected tenancy only</span>
                        </p>
                    </template>
                    
                    <template x-if="form.apply_to === 'multiple' && getSelectedTenancyCount() > 0">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Will be applied to: <span class="font-medium" x-text="getSelectedTenancyCount() + ' selected tenancies'"></span>
                        </p>
                    </template>
                    
                    <template x-if="checkResults">
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Based on check:</span>
                                <span class="text-green-600 dark:text-green-400 ml-2" x-text="checkResults.remaining_count"></span> will receive invoices
                                <template x-if="checkResults.existing_count > 0">
                                    <span class="text-gray-500 dark:text-gray-500"> (skipping <span x-text="checkResults.existing_count"></span> that already exist)</span>
                                </template>
                            </p>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="showModal = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isLoading || !formValid"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!isLoading">
                            Create Invoice<span x-show="form.apply_to !== 'single'">s</span>
                        </span>
                        <span x-show="isLoading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div x-data="paymentModal()" 
     x-show="showModal" 
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-black/50">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-lg bg-white dark:bg-gray-800">
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Process Payment</h3>
                    <button @click="closeModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-6 rounded-lg bg-gray-50 dark:bg-gray-900 p-4" x-show="selectedInvoice">
                    <h4 class="font-medium text-gray-800 dark:text-white/90 mb-2" x-text="'Invoice #' + selectedInvoice.id"></h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Tenant: <span x-text="selectedInvoice.tenant_name" class="font-medium"></span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Total Amount: <span class="font-medium" x-text="'{{ SystemHelper::currencySymbol() }} ' + formatCurrency(selectedInvoice.total_amount)"></span>
                    </p>
                </div>
                
                <form @submit.prevent="submitPayment">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Amount *</label>
                            <input type="number" step="0.01" x-model="form.amount" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Date *</label>
                            <input type="date" x-model="form.payment_date" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method *</label>
                            <select x-model="form.payment_method" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm" required>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Reference Number (Optional)</label>
                            <input type="text" x-model="form.reference_number" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes (Optional)</label>
                            <textarea x-model="form.notes" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex justify-end gap-3">
                        <button type="button" @click="closeModal()" class="rounded-lg border border-gray-300 dark:border-gray-700 px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">Cancel</button>
                        <button type="submit" :disabled="isLoading" class="rounded-lg bg-success-500 px-6 py-2 text-sm font-medium text-white disabled:opacity-50">
                            <span x-text="isLoading ? 'Processing...' : 'Process Payment'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Database enums configuration - THESE MUST BE DEFINED BEFORE Alpine components
const invoiceTypeEnum = ['move_in', 'monthly', 'move_out'];
const itemTypeEnum = ['rent', 'power', 'internet', 'water', 'security', 'garbage'];
const statusEnum = ['draft', 'unpaid', 'partial', 'paid'];

// Item type labels
const itemTypeLabels = {
    rent: "Rent",
    power: "Power/Electricity",
    internet: "Internet",
    water: "Water",
    security: "Security",
    garbage: "Garbage Collection"
};

// Invoice type labels
const invoiceTypeLabels = {
    move_in: "Move In",
    monthly: "Monthly",
    move_out: "Move Out"
};

// Status labels
const statusLabels = {
    draft: "Draft",
    unpaid: "Unpaid",
    partial: "Partial",
    paid: "Paid"
};

// Service category mapping
const serviceCategories = {
    utility: {
        label: "Utility",
        items: ['water', 'power', 'internet']
    },
    service_charge: {
        label: "Service Charge",
        items: ['security', 'garbage']
    },
    rent: {
        label: "Rent",
        items: ['rent']
    }
};

// Map invoices in controller and pass them as JSON
const invoicesData = @json($mappedInvoices);

// Map active tenancies for modal
const activeTenanciesData = @json($activeTenancies);

document.addEventListener('alpine:init', () => {
    // Main page component
    Alpine.data('invoicePage', () => ({
        isGeneratingAll: false,
        
        async generateAllInvoices() {
            this.isGeneratingAll = true;
            
            try {
                const response = await fetch('{{ route("invoices.generate.all") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Generated ${result.generated_count} invoices. ${result.already_generated} already existed.`);
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to generate invoices');
                }
            } catch (error) {
                console.error('Error generating invoices:', error);
                alert('An error occurred while generating invoices');
            } finally {
                this.isGeneratingAll = false;
            }
        }
    }));
    
    // Invoice table component
    Alpine.data('invoiceTable', () => ({
        invoices: invoicesData,
        selected: [],
        sortBy: 'created_at',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 10,
        filterStatus: 'All',
        searchQuery: '',
        isGenerating: null,
        
        init() {
            console.log('Invoice table initialized with', this.invoices.length, 'invoices');
        },
        
        get statusCounts() {
            return {
                draft: this.invoices.filter(i => i.status === 'draft').length,
                unpaid: this.invoices.filter(i => i.status === 'unpaid').length,
                partial: this.invoices.filter(i => i.status === 'partial').length,
                paid: this.invoices.filter(i => i.status === 'paid').length,
                all: this.invoices.length
            };
        },
        
        formatCurrency(value) {
            return parseFloat(value).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        
        formatStatus(status) {
            return status.charAt(0).toUpperCase() + status.slice(1);
        },
        
        getStatusClass(status) {
            const classes = {
                'paid': 'bg-success-50 dark:bg-success-500/15 text-success-700 dark:text-success-500',
                'unpaid': 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
                'partial': 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500',
                'draft': 'bg-gray-100 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'
            };
            return classes[status] || 'bg-gray-100 text-gray-600';
        },
        
        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selected = [];
            } else {
                this.selected = this.paginatedInvoices.map((i) => i.id);
            }
        },
        
        toggleRow(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter((i) => i !== id);
            } else {
                this.selected.push(id);
            }
        },
        
        get isAllSelected() {
            return (
                this.paginatedInvoices.length > 0 &&
                this.paginatedInvoices.every((i) => this.selected.includes(i.id))
            );
        },
        
        sort(field) {
            if (this.sortBy === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = field;
                this.sortDirection = 'asc';
            }
        },
        
        get filteredInvoices() {
            let filtered = this.invoices;
            
            // Filter by status
            if (this.filterStatus !== 'All') {
                filtered = filtered.filter(i => i.status === this.filterStatus);
            }
            
            // Filter by search query
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(i => 
                    (i.tenant_name && i.tenant_name.toLowerCase().includes(query)) ||
                    (i.unit_number && i.unit_number.toString().toLowerCase().includes(query)) ||
                    (i.invoice_type && i.invoice_type.toLowerCase().includes(query)) ||
                    (i.billing_month_formatted && i.billing_month_formatted.toLowerCase().includes(query)) ||
                    (i.total_amount && i.total_amount.toString().toLowerCase().includes(query)) ||
                    (i.status && i.status.toLowerCase().includes(query))
                );
            }
            
            return filtered;
        },
        
        get sortedInvoices() {
            return this.filteredInvoices.slice().sort((a, b) => {
                let valA = a[this.sortBy];
                let valB = b[this.sortBy];
                
                // Handle dates
                if (this.sortBy === 'created_at' || this.sortBy === 'billing_month') {
                    valA = valA ? new Date(valA).getTime() : 0;
                    valB = valB ? new Date(valB).getTime() : 0;
                }
                
                // Handle numbers
                if (this.sortBy === 'total_amount') {
                    valA = parseFloat(valA) || 0;
                    valB = parseFloat(valB) || 0;
                }
                
                // Handle strings
                if (typeof valA === 'string') {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }
                
                if (valA === null || valA === undefined) return 1;
                if (valB === null || valB === undefined) return -1;
                
                if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        get paginatedInvoices() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.sortedInvoices.slice(start, end);
        },
        
        get totalPages() {
            return Math.ceil(this.filteredInvoices.length / this.itemsPerPage);
        },
        
        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        
        previousPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        
        get visiblePages() {
            const pages = [];
            const maxVisible = 5;
            let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
            let end = Math.min(this.totalPages, start + maxVisible - 1);
            
            if (end - start + 1 < maxVisible) {
                start = Math.max(1, end - maxVisible + 1);
            }
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            
            return pages;
        },
        
        async generateInvoiceForTenancy(tenancyId) {
            this.isGenerating = tenancyId;
            
            try {
                const response = await fetch('{{ route("invoices.generate.single") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ tenancy_id: tenancyId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Invoice generated successfully!');
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to generate invoice');
                }
            } catch (error) {
                console.error('Error generating invoice:', error);
                alert('An error occurred while generating the invoice');
            } finally {
                this.isGenerating = null;
            }
        },
        
        showPaymentModal(invoice) {
            const paymentModal = document.querySelector('[x-data*="paymentModal"]');
            if (paymentModal && paymentModal.__x) {
                paymentModal.__x.$data.openModal(invoice);
            }
        }
    }));
    
    // Create Invoice Modal component - UPDATED TO USE DATABASE ENUMS
    Alpine.data('createInvoiceModal', () => ({
        showModal: false,
        form: {
            tenancy_id: '',
            invoice_type: 'monthly',
            item_type: 'rent',
            description: '',
            billing_month: '',
            amount: '',
            status: 'unpaid',
            notes: ''
        },
        isLoading: false,
        
        init() {
            // Set default billing month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            this.form.billing_month = `${year}-${month}`;
            this.generateDescription();
        },
        
        get formValid() {
            const isValid = this.form.tenancy_id && 
                          this.form.invoice_type && 
                          this.form.amount && 
                          this.form.billing_month &&
                          this.form.description;
            
            // Only require item_type for monthly invoices
            if (this.form.invoice_type === 'monthly') {
                return isValid && this.form.item_type;
            }
            
            return isValid;
        },
        
        onTenancyChange() {
            if (this.form.tenancy_id && this.form.item_type === 'rent') {
                const selectedTenancy = activeTenanciesData.find(t => t.id == this.form.tenancy_id);
                if (selectedTenancy && selectedTenancy.rent_amount > 0) {
                    this.form.amount = selectedTenancy.rent_amount;
                    this.generateDescription();
                }
            }
        },
        
        onInvoiceTypeChange() {
            // Reset item_type when invoice type changes (only monthly invoices have item_type)
            if (this.form.invoice_type !== 'monthly') {
                this.form.item_type = '';
            } else {
                this.form.item_type = 'rent';
            }
            this.generateDescription();
        },
        
        onItemTypeChange() {
            this.generateDescription();
            
            // Set default amount for rent
            if (this.form.item_type === 'rent' && this.form.tenancy_id) {
                const selectedTenancy = activeTenanciesData.find(t => t.id == this.form.tenancy_id);
                if (selectedTenancy && selectedTenancy.rent_amount > 0) {
                    this.form.amount = selectedTenancy.rent_amount;
                }
            }
        },
        
        generateDescription() {
            let description = '';
            
            if (this.form.invoice_type === 'monthly' && this.form.item_type) {
                const itemLabel = itemTypeLabels[this.form.item_type] || this.form.item_type;
                
                switch(this.form.item_type) {
                    case 'rent':
                        description = 'Monthly Rent';
                        break;
                    case 'water':
                    case 'power':
                    case 'internet':
                        description = itemLabel + ' Charges';
                        break;
                    case 'security':
                    case 'garbage':
                        description = itemLabel + ' Service Charge';
                        break;
                    default:
                        description = itemLabel + ' Charges';
                }
            } else if (this.form.invoice_type === 'move_in') {
                description = 'Move In Charges';
            } else if (this.form.invoice_type === 'move_out') {
                description = 'Move Out Charges';
            }
            
            this.form.description = description;
        },
        
        getInvoiceTypeLabel() {
            if (!this.form.invoice_type) return '';
            return invoiceTypeLabels[this.form.invoice_type] || this.form.invoice_type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        },
        
        getItemTypeLabel() {
            if (!this.form.item_type) return '';
            return itemTypeLabels[this.form.item_type] || this.form.item_type.charAt(0).toUpperCase() + this.form.item_type.slice(1);
        },
        
        async submitInvoice() {
            if (!this.formValid) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Validate item_type for monthly invoices
            if (this.form.invoice_type === 'monthly' && !this.form.item_type) {
                alert('Please select an item type for monthly invoices');
                return;
            }
            
            // Validate that item_type is valid for invoice_type
            if (this.form.invoice_type === 'monthly' && !itemTypeEnum.includes(this.form.item_type)) {
                alert(`Invalid item type. Valid options: ${itemTypeEnum.join(', ')}`);
                return;
            }
            
            this.isLoading = true;
            
            try {
                // Prepare data for the store endpoint
                const invoiceData = {
                    tenancy_id: this.form.tenancy_id,
                    invoice_type: this.form.invoice_type,
                    item_type: this.form.item_type,
                    description: this.form.description,
                    billing_month: this.form.billing_month,
                    amount: this.form.amount,
                    status: this.form.status,
                    notes: this.form.notes
                };
                
                const response = await fetch('{{ route("invoices.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(invoiceData)
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    alert('Invoice created successfully!');
                    this.showModal = false;
                    this.resetForm();
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to create invoice');
                }
            } catch (error) {
                console.error('Error creating invoice:', error);
                alert('An error occurred while creating the invoice');
            } finally {
                this.isLoading = false;
            }
        },
        
        resetForm() {
            this.form = {
                tenancy_id: '',
                invoice_type: 'monthly',
                item_type: 'rent',
                description: '',
                billing_month: '',
                amount: '',
                status: 'unpaid',
                notes: ''
            };
            
            // Reset to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            this.form.billing_month = `${year}-${month}`;
            this.generateDescription();
        }
    }));

    // Bulk Invoice Modal component - ENHANCED VERSION WITH MULTIPLE ITEMS
    Alpine.data('bulkInvoiceModal', () => ({
        showModal: false,
        form: {
            invoice_type: 'monthly',
            billing_month: '',
            apply_to: 'bulk',
            tenancy_id: '',
            items: [
                {
                    id: 1,
                    item_type: '',
                    amount: '',
                    description: ''
                }
            ]
        },
        nextItemId: 2,
        isLoading: false,
        
        init() {
            // Set default billing month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            this.form.billing_month = `${year}-${month}`;
            this.updateItemDescriptions();
        },
        
        get formValid() {
            // Check basic form validity
            const basicValid = this.form.invoice_type && 
                            this.form.billing_month &&
                            this.form.items.length > 0;
            
            // Check items validity
            const itemsValid = this.form.items.every(item => 
                item.item_type && 
                item.amount && 
                parseFloat(item.amount) > 0
            );
            
            // If applying to single tenancy, require tenancy_id
            if (this.form.apply_to === 'single') {
                return basicValid && itemsValid && this.form.tenancy_id;
            }
            
            return basicValid && itemsValid;
        },
        
        // Get available item types based on invoice type
        get availableItemTypes() {
            if (this.form.invoice_type === 'monthly') {
                return itemTypeEnum; // ['rent', 'power', 'internet', 'water', 'security', 'garbage']
            }
            return [];
        },
        
        onInvoiceTypeChange() {
            // Reset all item types when invoice type changes
            this.form.items.forEach(item => {
                item.item_type = '';
            });
            this.updateItemDescriptions();
        },
        
        onItemTypeChange(index) {
            this.updateItemDescription(index);
        },
        
        updateItemDescription(index) {
            if (!this.form.items[index]) return;
            
            const item = this.form.items[index];
            if (!item.item_type) {
                item.description = '';
                return;
            }
            
            let description = '';
            const itemLabel = itemTypeLabels[item.item_type] || item.item_type;
            
            if (this.form.invoice_type === 'monthly') {
                switch(item.item_type) {
                    case 'rent':
                        description = 'Monthly Rent';
                        break;
                    case 'water':
                    case 'power':
                    case 'internet':
                        description = itemLabel + ' Charges';
                        break;
                    case 'security':
                    case 'garbage':
                        description = itemLabel + ' Service Charge';
                        break;
                    default:
                        description = itemLabel + ' Charges';
                }
            } else if (this.form.invoice_type === 'move_in') {
                description = 'Move In Charges';
            } else if (this.form.invoice_type === 'move_out') {
                description = 'Move Out Charges';
            }
            
            if (this.form.billing_month) {
                description += ` for ${this.formatMonth(this.form.billing_month)}`;
            }
            
            item.description = description;
        },
        
        updateItemDescriptions() {
            this.form.items.forEach((_, index) => {
                this.updateItemDescription(index);
            });
        },
        
        addItem() {
            this.form.items.push({
                id: this.nextItemId++,
                item_type: '',
                amount: '',
                description: ''
            });
        },
        
        removeItem(index) {
            if (this.form.items.length > 1) {
                this.form.items.splice(index, 1);
            }
        },
        
        getTotalAmount() {
            return this.form.items.reduce((total, item) => {
                return total + (parseFloat(item.amount) || 0);
            }, 0);
        },
        
        getServiceCategory(itemType) {
            if (!itemType) return '';
            
            const utilityItems = ['water', 'power', 'internet'];
            const serviceChargeItems = ['security', 'garbage'];
            
            if (utilityItems.includes(itemType)) {
                return 'Utility';
            } else if (serviceChargeItems.includes(itemType)) {
                return 'Service Charge';
            } else if (itemType === 'rent') {
                return 'Rent';
            }
            
            return 'Other';
        },
        
        getInvoiceTypeLabel() {
            if (!this.form.invoice_type) return '';
            return invoiceTypeLabels[this.form.invoice_type] || this.form.invoice_type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        },
        
        getItemTypeLabel(itemType) {
            if (!itemType) return '';
            return itemTypeLabels[itemType] || 
                itemType.charAt(0).toUpperCase() + itemType.slice(1);
        },
        
        formatMonth(monthString) {
            if (!monthString) return '';
            const [year, month] = monthString.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long'
            });
        },
        
        async submitBulkInvoice() {
            if (!this.formValid) {
                alert('Please fill in all required fields and ensure all items have valid data');
                return;
            }
            
            // Validate all items
            for (const item of this.form.items) {
                if (this.form.invoice_type === 'monthly' && !item.item_type) {
                    alert('Please select an item type for all monthly invoice items');
                    return;
                }
                
                if (item.item_type && !itemTypeEnum.includes(item.item_type)) {
                    alert(`Invalid item type "${item.item_type}". Valid options: ${itemTypeEnum.join(', ')}`);
                    return;
                }
            }
            
            // Confirmation for bulk operations
            if (this.form.apply_to === 'bulk') {
                const tenancyCount = activeTenanciesData.length;
                const itemCount = this.form.items.length;
                const totalAmount = this.getTotalAmount().toFixed(2);
                
                const confirmation = confirm(
                    `You are about to create invoices for ALL ${tenancyCount} active tenancies.\n\n` +
                    `Invoice Type: ${this.getInvoiceTypeLabel()}\n` +
                    `Number of Items: ${itemCount}\n` +
                    `Total Amount per Tenancy: {{ SystemHelper::currencySymbol() }}${totalAmount}\n` +
                    `Billing Month: ${this.formatMonth(this.form.billing_month)}\n\n` +
                    `Total invoices to create: ${tenancyCount}\n` +
                    `Are you sure you want to continue?`
                );
                
                if (!confirmation) {
                    return;
                }
            }
            
            this.isLoading = true;
            
            try {
                // Prepare data for bulk creation - send each item separately
                const results = [];
                
                for (const item of this.form.items) {
                    const bulkData = {
                        invoice_type: this.form.invoice_type,
                        item_type: this.form.invoice_type === 'monthly' ? item.item_type : null,
                        amount: item.amount,
                        billing_month: this.form.billing_month,
                        apply_to: this.form.apply_to,
                        tenancy_id: this.form.apply_to === 'single' ? this.form.tenancy_id : null,
                        description: item.description || this.generateItemDescription(item)
                    };
                    
                    // Clean up data - remove null values
                    Object.keys(bulkData).forEach(key => {
                        if (bulkData[key] === null || bulkData[key] === '') {
                            delete bulkData[key];
                        }
                    });
                    
                    const response = await fetch('{{ route("invoices.bulk.create") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(bulkData)
                    });
                    
                    const result = await response.json();
                    results.push(result);
                    
                    // If single item fails, stop processing
                    if (!response.ok && this.form.apply_to === 'single') {
                        throw new Error(result.message || 'Failed to create invoice');
                    }
                }
                
                // Process results
                const successResults = results.filter(r => r.success);
                const failedResults = results.filter(r => !r.success);
                
                if (successResults.length > 0) {
                    const totalCreated = successResults.reduce((sum, r) => sum + (r.created_count || r.count || 0), 0);
                    const totalSkipped = successResults.reduce((sum, r) => sum + (r.skipped_count || 0), 0);
                    
                    let message = '';
                    if (this.form.apply_to === 'bulk') {
                        message = `Bulk invoice creation completed!\n\n` +
                                `✅ Created: ${totalCreated} invoice(s)\n` +
                                `⏭️ Skipped (already exists): ${totalSkipped} tenancy(ies)\n` +
                                `❌ Failed: ${failedResults.length} item(s)`;
                    } else {
                        message = `✅ ${successResults.length} item(s) created successfully!\n` +
                                `❌ ${failedResults.length} item(s) failed`;
                    }
                    
                    alert(message);
                    
                    this.showModal = false;
                    this.resetForm();
                    
                    // Only reload if items were created
                    if (totalCreated > 0 || (this.form.apply_to === 'single' && successResults.length > 0)) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                    
                } else {
                    alert('All items failed to create. Please try again.');
                }
                
            } catch (error) {
                console.error('Error creating bulk invoices:', error);
                alert('An error occurred while creating invoices: ' + error.message);
            } finally {
                this.isLoading = false;
            }
        },
        
        generateItemDescription(item) {
            if (!item.item_type) return '';
            
            let description = '';
            const itemLabel = itemTypeLabels[item.item_type] || item.item_type;
            
            if (this.form.invoice_type === 'monthly') {
                switch(item.item_type) {
                    case 'rent':
                        description = 'Monthly Rent';
                        break;
                    case 'water':
                    case 'power':
                    case 'internet':
                        description = itemLabel + ' Charges';
                        break;
                    case 'security':
                    case 'garbage':
                        description = itemLabel + ' Service Charge';
                        break;
                    default:
                        description = itemLabel + ' Charges';
                }
            } else if (this.form.invoice_type === 'move_in') {
                description = 'Move In Charges';
            } else if (this.form.invoice_type === 'move_out') {
                description = 'Move Out Charges';
            }
            
            if (this.form.billing_month) {
                description += ` for ${this.formatMonth(this.form.billing_month)}`;
            }
            
            return description;
        },
        
        resetForm() {
            this.form = {
                invoice_type: 'monthly',
                billing_month: '',
                apply_to: 'bulk',
                tenancy_id: '',
                items: [
                    {
                        id: 1,
                        item_type: '',
                        amount: '',
                        description: ''
                    }
                ]
            };
            this.nextItemId = 2;
            
            // Reset billing month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            this.form.billing_month = `${year}-${month}`;
            this.updateItemDescriptions();
        },
        
        // Helper method to get tenancy name by ID
        getTenancyName(tenancyId) {
            if (!tenancyId) return '';
            const tenancy = activeTenanciesData.find(t => t.id == tenancyId);
            return tenancy ? `${tenancy.tenant?.user?.name || 'Unknown'} - ${tenancy.unit?.unit_number || 'No Unit'}` : '';
        }
    }));
    
    // Bulk Invoice Modal component - ENHANCED VERSION WITH MULTIPLE TENANCY SELECTION
    Alpine.data('bulkInvoiceModal', () => ({
        showModal: false,
        form: {
            invoice_type: 'monthly',
            billing_month: '',
            apply_to: 'bulk', // 'bulk', 'single', 'multiple'
            tenancy_id: '',
            selected_tenancies: [],
            items: [
                {
                    id: 1,
                    item_type: '',
                    amount: '',
                    description: ''
                }
            ]
        },
        nextItemId: 2,
        isLoading: false,
        isCheckingInvoices: false,
        checkResults: null,
        
        init() {
            // Set default billing month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            this.form.billing_month = `${year}-${month}`;
            this.updateItemDescriptions();
        },
        
        get formValid() {
            // Check basic form validity
            const basicValid = this.form.invoice_type && 
                            this.form.billing_month &&
                            this.form.items.length > 0;
            
            // Check items validity
            const itemsValid = this.form.items.every(item => 
                item.item_type && 
                item.amount && 
                parseFloat(item.amount) > 0
            );
            
            // Check tenancy selection based on apply_to
            if (this.form.apply_to === 'single') {
                return basicValid && itemsValid && this.form.tenancy_id;
            } else if (this.form.apply_to === 'multiple') {
                return basicValid && itemsValid && this.form.selected_tenancies.length > 0;
            }
            
            // For 'bulk', no specific tenancy selection needed
            return basicValid && itemsValid;
        },
        
        // Get available item types based on invoice type
        get availableItemTypes() {
            if (this.form.invoice_type === 'monthly') {
                return itemTypeEnum; // ['rent', 'power', 'internet', 'water', 'security', 'garbage']
            }
            return [];
        },
        
        onInvoiceTypeChange() {
            // Reset all item types when invoice type changes
            this.form.items.forEach(item => {
                item.item_type = '';
            });
            this.updateItemDescriptions();
            this.resetCheckResults();
        },
        
        onBillingMonthChange() {
            this.updateItemDescriptions();
            this.resetCheckResults();
        },
        
        onItemTypeChange(index) {
            this.updateItemDescription(index);
            this.resetCheckResults();
        },
        
        updateItemDescription(index) {
            if (!this.form.items[index]) return;
            
            const item = this.form.items[index];
            if (!item.item_type) {
                item.description = '';
                return;
            }
            
            let description = '';
            const itemLabel = itemTypeLabels[item.item_type] || item.item_type;
            
            if (this.form.invoice_type === 'monthly') {
                switch(item.item_type) {
                    case 'rent':
                        description = 'Monthly Rent';
                        break;
                    case 'water':
                    case 'power':
                    case 'internet':
                        description = itemLabel + ' Charges';
                        break;
                    case 'security':
                    case 'garbage':
                        description = itemLabel + ' Service Charge';
                        break;
                    default:
                        description = itemLabel + ' Charges';
                }
            } else if (this.form.invoice_type === 'move_in') {
                description = 'Move In Charges';
            } else if (this.form.invoice_type === 'move_out') {
                description = 'Move Out Charges';
            }
            
            if (this.form.billing_month) {
                description += ` for ${this.formatMonth(this.form.billing_month)}`;
            }
            
            item.description = description;
        },
        
        updateItemDescriptions() {
            this.form.items.forEach((_, index) => {
                this.updateItemDescription(index);
            });
        },
        
        addItem() {
            this.form.items.push({
                id: this.nextItemId++,
                item_type: '',
                amount: '',
                description: ''
            });
            this.resetCheckResults();
        },
        
        removeItem(index) {
            if (this.form.items.length > 1) {
                this.form.items.splice(index, 1);
                this.resetCheckResults();
            }
        },
        
        toggleTenancySelection(tenancyId) {
            const index = this.form.selected_tenancies.indexOf(tenancyId);
            if (index === -1) {
                this.form.selected_tenancies.push(tenancyId);
            } else {
                this.form.selected_tenancies.splice(index, 1);
            }
            this.resetCheckResults();
        },
        
        selectAllTenancies() {
            this.form.selected_tenancies = activeTenanciesData.map(t => t.id);
            this.resetCheckResults();
        },
        
        clearTenancySelection() {
            this.form.selected_tenancies = [];
            this.resetCheckResults();
        },
        
        getSelectedTenancyCount() {
            return this.form.selected_tenancies.length;
        },
        
        getSelectedTenancyNames() {
            return this.form.selected_tenancies.map(id => {
                const tenancy = activeTenanciesData.find(t => t.id == id);
                return tenancy ? `${tenancy.unit_number} - ${tenancy.tenant_name}` : `Tenancy ${id}`;
            }).join(', ');
        },
        
        getTotalAmount() {
            return this.form.items.reduce((total, item) => {
                return total + (parseFloat(item.amount) || 0);
            }, 0);
        },
        
        getServiceCategory(itemType) {
            if (!itemType) return '';
            
            const utilityItems = ['water', 'power', 'internet'];
            const serviceChargeItems = ['security', 'garbage'];
            
            if (utilityItems.includes(itemType)) {
                return 'Utility';
            } else if (serviceChargeItems.includes(itemType)) {
                return 'Service Charge';
            } else if (itemType === 'rent') {
                return 'Rent';
            }
            
            return 'Other';
        },
        
        getInvoiceTypeLabel() {
            if (!this.form.invoice_type) return '';
            return invoiceTypeLabels[this.form.invoice_type] || this.form.invoice_type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        },
        
        getItemTypeLabel(itemType) {
            if (!itemType) return '';
            return itemTypeLabels[itemType] || 
                itemType.charAt(0).toUpperCase() + itemType.slice(1);
        },
        
        formatMonth(monthString) {
            if (!monthString) return '';
            const [year, month] = monthString.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long'
            });
        },
        
        resetCheckResults() {
            this.checkResults = null;
        },
        
        async checkExistingInvoices() {
            // Validate before checking
            if (!this.form.invoice_type || !this.form.billing_month) {
                alert('Please select invoice type and billing month first');
                return;
            }
            
            // For monthly invoices, check if all items have item_type
            if (this.form.invoice_type === 'monthly') {
                for (const item of this.form.items) {
                    if (!item.item_type) {
                        alert('Please select item type for all items before checking');
                        return;
                    }
                }
            }
            
            // Determine which tenancies to check based on apply_to
            let tenancyIds = [];
            
            if (this.form.apply_to === 'bulk') {
                // All active tenancies
                tenancyIds = activeTenanciesData.map(t => t.id);
            } else if (this.form.apply_to === 'single' && this.form.tenancy_id) {
                // Single tenancy
                tenancyIds = [this.form.tenancy_id];
            } else if (this.form.apply_to === 'multiple' && this.form.selected_tenancies.length > 0) {
                // Multiple selected tenancies
                tenancyIds = this.form.selected_tenancies;
            } else {
                alert('Please select tenancies to check');
                return;
            }
            
            if (tenancyIds.length === 0) {
                alert('No tenancies selected to check');
                return;
            }
            
            this.isCheckingInvoices = true;
            
            try {
                // Prepare data for checking
                const checkData = {
                    tenancy_ids: tenancyIds,
                    invoice_type: this.form.invoice_type,
                    billing_month: this.form.billing_month
                };
                
                // For monthly invoices, we need to check each item separately
                if (this.form.invoice_type === 'monthly') {
                    checkData.item_type = this.form.items[0].item_type; // Check for first item
                }
                
                const response = await fetch('{{ route("invoices.check.existing") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(checkData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.checkResults = result;
                    
                    // Show results
                    let message = `Invoice Check Results:\n\n`;
                    message += `📋 Total Tenancies Checked: ${tenancyIds.length}\n`;
                    message += `✅ Already Have Invoices: ${result.existing_count}\n`;
                    message += `🆕 Will Create For: ${result.remaining_count}`;
                    
                    if (result.remaining_count > 0) {
                        message += `\n\nRemaining Tenancies:\n`;
                        result.remaining_tenancies.slice(0, 5).forEach(tenancy => {
                            message += `- ${tenancy.unit_number} (${tenancy.tenant_name})\n`;
                        });
                        
                        if (result.remaining_count > 5) {
                            message += `... and ${result.remaining_count - 5} more`;
                        }
                    }
                    
                    alert(message);
                    
                } else {
                    alert('Error checking existing invoices: ' + (result.message || 'Unknown error'));
                }
                
            } catch (error) {
                console.error('Error checking existing invoices:', error);
                alert('An error occurred while checking existing invoices');
            } finally {
                this.isCheckingInvoices = false;
            }
        },
        
        async submitBulkInvoice() {
            if (!this.formValid) {
                alert('Please fill in all required fields and ensure all items have valid data');
                return;
            }
            
            // Validate all items
            for (const item of this.form.items) {
                if (this.form.invoice_type === 'monthly' && !item.item_type) {
                    alert('Please select an item type for all monthly invoice items');
                    return;
                }
                
                if (item.item_type && !itemTypeEnum.includes(item.item_type)) {
                    alert(`Invalid item type "${item.item_type}". Valid options: ${itemTypeEnum.join(', ')}`);
                    return;
                }
            }
            
            // Determine which tenancies to process
            let tenancyIds = [];
            let targetDescription = '';
            
            if (this.form.apply_to === 'bulk') {
                // All active tenancies
                tenancyIds = activeTenanciesData.map(t => t.id);
                targetDescription = `ALL ${tenancyIds.length} active tenancies`;
            } else if (this.form.apply_to === 'single') {
                // Single tenancy
                tenancyIds = [this.form.tenancy_id];
                const tenancy = activeTenanciesData.find(t => t.id == this.form.tenancy_id);
                targetDescription = `1 tenancy (${tenancy?.unit_number || 'Selected'})`;
            } else if (this.form.apply_to === 'multiple') {
                // Multiple selected tenancies
                tenancyIds = this.form.selected_tenancies;
                targetDescription = `${tenancyIds.length} selected tenancies`;
            }
            
            // Confirmation dialog
            const itemCount = this.form.items.length;
            const totalAmount = this.getTotalAmount().toFixed(2);
            
            const confirmation = confirm(
                `You are about to create invoices for ${targetDescription}.\n\n` +
                `Invoice Type: ${this.getInvoiceTypeLabel()}\n` +
                `Billing Month: ${this.formatMonth(this.form.billing_month)}\n` +
                `Number of Items per Tenancy: ${itemCount}\n` +
                `Total Amount per Tenancy: {{ SystemHelper::currencySymbol() }}${totalAmount}\n` +
                `Total Invoices to Create: ${tenancyIds.length}\n\n` +
                `Are you sure you want to continue?`
            );
            
            if (!confirmation) {
                return;
            }
            
            this.isLoading = true;
            
            try {
                // Prepare data for bulk creation - send each item separately
                const results = [];
                const createdInvoices = [];
                const skippedTenancies = [];
                
                // First, check which tenancies already have invoices
                if (this.checkResults) {
                    // Use pre-checked results
                    tenancyIds = this.checkResults.remaining_tenancy_ids;
                    skippedTenancies = this.checkResults.existing_tenancy_ids;
                }
                
                // Process each item for each tenancy
                for (const item of this.form.items) {
                    for (const tenancyId of tenancyIds) {
                        try {
                            const bulkData = {
                                invoice_type: this.form.invoice_type,
                                item_type: this.form.invoice_type === 'monthly' ? item.item_type : null,
                                amount: item.amount,
                                billing_month: this.form.billing_month,
                                apply_to: 'single', // Always single for this approach
                                tenancy_id: tenancyId,
                                description: item.description || this.generateItemDescription(item)
                            };
                            
                            // Clean up data - remove null values
                            Object.keys(bulkData).forEach(key => {
                                if (bulkData[key] === null || bulkData[key] === '') {
                                    delete bulkData[key];
                                }
                            });
                            
                            const response = await fetch('{{ route("invoices.bulk.create") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify(bulkData)
                            });
                            
                            const result = await response.json();
                            results.push({...result, tenancyId});
                            
                            if (result.success && result.invoice) {
                                createdInvoices.push({
                                    tenancyId,
                                    invoiceId: result.invoice.id
                                });
                            }
                            
                        } catch (error) {
                            console.error(`Error creating invoice for tenancy ${tenancyId}:`, error);
                        }
                    }
                }
                
                // Process and show results
                const successResults = results.filter(r => r.success);
                const failedResults = results.filter(r => !r.success);
                
                if (successResults.length > 0) {
                    const uniqueCreatedTenancies = [...new Set(successResults.map(r => r.tenancyId))];
                    
                    let message = `Invoice Creation Completed!\n\n`;
                    
                    if (skippedTenancies.length > 0) {
                        message += `⏭️ Skipped (already existed): ${skippedTenancies.length} tenancy(ies)\n`;
                    }
                    
                    message += `✅ Created: ${uniqueCreatedTenancies.length} tenancy(ies)\n`;
                    message += `📊 Total Items Created: ${successResults.length}\n`;
                    
                    if (failedResults.length > 0) {
                        message += `❌ Failed: ${failedResults.length} item(s)\n`;
                        
                        // Show first few errors
                        const uniqueFailedTenancies = [...new Set(failedResults.map(r => r.tenancyId))];
                        if (uniqueFailedTenancies.length > 0) {
                            message += `\nFailed for tenancies: ${uniqueFailedTenancies.slice(0, 3).join(', ')}`;
                            if (uniqueFailedTenancies.length > 3) {
                                message += ` and ${uniqueFailedTenancies.length - 3} more`;
                            }
                        }
                    }
                    
                    alert(message);
                    
                    this.showModal = false;
                    this.resetForm();
                    
                    // Reload the page to show new invoices
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                    
                } else {
                    alert('No invoices were created. All items failed or were skipped.');
                }
                
            } catch (error) {
                console.error('Error creating bulk invoices:', error);
                alert('An error occurred while creating invoices: ' + error.message);
            } finally {
                this.isLoading = false;
            }
        },
        
        generateItemDescription(item) {
            if (!item.item_type) return '';
            
            let description = '';
            const itemLabel = itemTypeLabels[item.item_type] || item.item_type;
            
            if (this.form.invoice_type === 'monthly') {
                switch(item.item_type) {
                    case 'rent':
                        description = 'Monthly Rent';
                        break;
                    case 'water':
                    case 'power':
                    case 'internet':
                        description = itemLabel + ' Charges';
                        break;
                    case 'security':
                    case 'garbage':
                        description = itemLabel + ' Service Charge';
                        break;
                    default:
                        description = itemLabel + ' Charges';
                }
            } else if (this.form.invoice_type === 'move_in') {
                description = 'Move In Charges';
            } else if (this.form.invoice_type === 'move_out') {
                description = 'Move Out Charges';
            }
            
            if (this.form.billing_month) {
                description += ` for ${this.formatMonth(this.form.billing_month)}`;
            }
            
            return description;
        },
        
        resetForm() {
            this.form = {
                invoice_type: 'monthly',
                billing_month: '',
                apply_to: 'bulk',
                tenancy_id: '',
                selected_tenancies: [],
                items: [
                    {
                        id: 1,
                        item_type: '',
                        amount: '',
                        description: ''
                    }
                ]
            };
            this.nextItemId = 2;
            this.checkResults = null;
            
            // Reset billing month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            this.form.billing_month = `${year}-${month}`;
            this.updateItemDescriptions();
        }
    }));
    
    // Dropdown component
    Alpine.data('dropdown', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        }
    }));
});
</script>
<style>
[x-cloak] { display: none !important; }
</style>
@endsection