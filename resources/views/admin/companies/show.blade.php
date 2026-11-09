{{-- resources/views/admin/companies/show.blade.php --}}
@extends('layouts.app')

@section('title', $company->name)

@section('content')
<div x-data="companyShowPage()" x-init="init()">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="flex justify-between items-center">
                <div>
                    <a href="{{ route('admin.companies.index') }}" class="text-blue-600 hover:text-blue-900 mb-2 inline-block">
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Companies
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $company->name }}</h1>
                    @if($company->registration_number)
                        <p class="text-gray-500 text-sm mt-1">Reg: {{ $company->registration_number }}</p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button @click="openEditModal()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Company
                    </button>
                    <button @click="toggleStatus()" :class="companyStatus ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition">
                        <span x-text="companyStatus ? 'Deactivate' : 'Activate'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <!-- Company Staff -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Staff</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalStaff">{{ $stats['totalStaff'] ?? 0 }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Properties -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Properties</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalProperties">{{ $stats['totalProperties'] ?? 0 }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Units -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Units</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalUnits">{{ $stats['totalUnits'] ?? 0 }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                    <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Tenants -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Tenancies</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalTenants">{{ $stats['totalTenants'] ?? 0 }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                    <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Invoices -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Invoices</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalInvoices">{{ $stats['totalInvoices'] ?? 0 }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                    <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Subscription -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Plan</span>
                    <h3 class="mt-2 text-lg font-bold text-gray-800 dark:text-white/90 truncate max-w-[100px]">
                        {{ $subscription?->plan->name ?? 'No Plan' }}
                    </h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900/30">
                    <svg class="h-6 w-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
            </div>
            @if($subscription?->ends_at)
                <div class="mt-2 text-xs text-gray-500">Expires: {{ $subscription->ends_at->format('M d, Y') }}</div>
            @endif
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
            <div class="flex flex-wrap gap-2">
                <!-- Info Tab -->
                <button @click="activeTab = 'info'" :class="activeTab === 'info' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Info
                </button>
                
                <!-- Subscription Invoices Tab (after Info) -->
                <button @click="activeTab = 'subscription_invoices'; loadSubscriptionInvoices()" :class="activeTab === 'subscription_invoices' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Sub Invoices
                </button>
                
                <!-- Staff Tab -->
                <button @click="activeTab = 'staff'; loadStaff()" :class="activeTab === 'staff' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    Staff <span class="ml-1 text-xs" x-text="stats.totalStaff">0</span>
                </button>
                
                <!-- Properties Tab -->
                <button @click="activeTab = 'properties'; loadProperties()" :class="activeTab === 'properties' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Properties <span class="ml-1 text-xs" x-text="stats.totalProperties">0</span>
                </button>
                
                <!-- Tenancies Tab (Active only) -->
                <button @click="activeTab = 'tenancies'; loadTenancies()" :class="activeTab === 'tenancies' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Tenancies <span class="ml-1 text-xs" x-text="stats.totalTenants">0</span>
                </button>
                
                <!-- Subscriptions Tab -->
                <button @click="activeTab = 'subscriptions'; loadSubscriptions()" :class="activeTab === 'subscriptions' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Subscriptions
                </button>
                
                <!-- Expenses Tab -->
                <button @click="activeTab = 'expenses'; loadExpenses()" :class="activeTab === 'expenses' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                    </svg>
                    Expenses
                </button>
                
                <!-- Invoices Tab (standard tenant invoices) -->
                <button @click="activeTab = 'invoices'; loadInvoices()" :class="activeTab === 'invoices' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    All Invoices
                </button>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div class="p-5">
            <!-- Company Info Tab -->
            <div x-show="activeTab === 'info'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Company Name</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->name }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Registration Number</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->registration_number ?? '-' }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tax ID / VAT</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->tax_id ?? '-' }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Email Address</label>
                            <p class="mt-1 text-gray-900 dark:text-white">
                                @if($company->email)
                                    <a href="mailto:{{ $company->email }}" class="text-blue-600 hover:text-blue-700">{{ $company->email }}</a>
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</label>
                            <p class="mt-1 text-gray-900 dark:text-white">
                                @if($company->phone)
                                    <a href="tel:{{ $company->phone }}" class="text-blue-600 hover:text-blue-700">{{ $company->phone }}</a>
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Physical Address</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->address ?? '-' }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Created Date</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->created_at->format('F d, Y H:i A') }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->updated_at->format('F d, Y H:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Subscription Invoices Tab -->
            <div x-show="activeTab === 'subscription_invoices'" x-cloak>
                <div x-data="subscriptionInvoicesTable()" x-init="loadSubscriptionInvoices()">
                    @include('partials.table.table-subscriptions-invoices', [
                        'invoices' => $subscriptionInvoices ?? collect(),
                        'planId' => $planId ?? null,
                        'totalAmount' => $totalInvoiceAmount ?? 0
                    ])
                </div>
            </div>
            
            <!-- Staff Tab -->
            <div x-show="activeTab === 'staff'" x-cloak>
                <div x-data="staffTable()" x-init="loadStaff()">
                    @include('partials.table.table-company-staff')
                </div>
            </div>
            
            <!-- Properties Tab -->
            <div x-show="activeTab === 'properties'" x-cloak>
                <div x-data="propertiesTable()" x-init="loadProperties()">
                    @include('partials.table.table-properties')
                </div>
            </div>
            
            <!-- Tenancies Tab -->
            <div x-show="activeTab === 'tenancies'" x-cloak>
                <div x-data="tenanciesTable()" x-init="loadTenancies()">
                    @include('partials.table.table-tenancy')
                </div>
            </div>
            
            <!-- Subscriptions Tab -->
            <div x-show="activeTab === 'subscriptions'" x-cloak>
                <div x-data="subscriptionsTable()" x-init="loadSubscriptions()">
                    @include('partials.table.table-subscriptions')
                </div>
            </div>
            
            <!-- Expenses Tab -->
            <div x-show="activeTab === 'expenses'" x-cloak>
                <div x-data="expensesTable()" x-init="loadExpenses()">
                    @include('partials.table.table-expenses')
                </div>
            </div>
            
            <!-- Invoices Tab (Standard tenant invoices) -->
            <div x-show="activeTab === 'invoices'" x-cloak>
              <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tenant Invoices</h3>
                    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                        <div class="relative">
                            <select x-model="invoiceStatusFilter" aria-label="Status" class="dark:bg-dark-900 h-11 w-full min-w-[140px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="">All Status</option>
                                <option value="paid">Paid</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="partial">Partial</option>
                                <option value="draft">Draft</option>
                            </select>
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
                        </div>
                        <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="search" x-model="invoiceSearch"  placeholder="Search invoices..." aria-label="Search invoices..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
                    </div>
                </div>
                <div class="w-full overflow-x-auto">
                    <table class="data-table w-full table-auto">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Invoice #</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Tenant</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Unit</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Amount</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                                <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="'#' + invoice.id"></td>
                                    <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="invoice.tenant_name"></td>
                                    <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="invoice.unit_number"></td>
                                    <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatCurrency(invoice.total_amount)"></td>
                                    <td class="p-4 whitespace-nowrap">
                                        <span :class="getInvoiceStatusClass(invoice.status)" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" x-text="invoice.status"></span>
                                    </td>
                                    <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="formatDate(invoice.created_at)"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredInvoices.length === 0">
                                <td colspan="6" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invoices found</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters or search.</p>
  </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div x-show="filteredInvoices.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
                    <div class="pb-3 sm:pb-0">
                        <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
                            Showing <span class="text-gray-800 dark:text-white/90" x-text="invoiceShowingStart"></span>
                            to <span class="text-gray-800 dark:text-white/90" x-text="invoiceShowingEnd"></span>
                            of <span class="text-gray-800 dark:text-white/90" x-text="filteredInvoices.length"></span>
                        </span>
                    </div>
                    <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
                        <button type="button" @click="invoicePrevPage()" :disabled="invoiceCurrentPage === 1" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Previous page"><span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/>
              </svg></span></button>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-400" x-text="'Page ' + invoiceCurrentPage + ' of ' + invoiceTotalPages"></span>
                        <button type="button" @click="invoiceNextPage()" :disabled="invoiceCurrentPage === invoiceTotalPages" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Next page"><span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/>
              </svg></span></button>
                    </div>
                </div>
              </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div @keydown.escape.window="showEditModal = false">
    <div x-show="showEditModal" x-cloak @click="showEditModal = false" class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    <div x-show="showEditModal" x-cloak x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="showEditModal = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Edit Company</h4>
                <form @submit.prevent="saveCompany">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Company Name *</label>
                            <input type="text" x-model="editForm.name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Registration Number</label>
                            <input type="text" x-model="editForm.registration_number" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tax ID</label>
                            <input type="text" x-model="editForm.tax_id" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                            <input type="email" x-model="editForm.email" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone</label>
                            <input type="text" x-model="editForm.phone" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Address</label>
                            <textarea x-model="editForm.address" rows="3" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="editForm.is_active" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end gap-3">
                        <button type="button" @click="showEditModal = false" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Cancel</button>
                        <button type="submit" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition disabled:opacity-50">Save Changes</button>
                    </div>
                </form>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div @keydown.escape.window="showAddStaffModal = false">
    <div x-show="showAddStaffModal" x-cloak @click="showAddStaffModal = false" class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    <div x-show="showAddStaffModal" x-cloak x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="showAddStaffModal = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Add Staff Member</h4>
                <form @submit.prevent="addStaff">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">First Name *</label>
                            <input type="text" x-model="newStaff.first_name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Last Name *</label>
                            <input type="text" x-model="newStaff.last_name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email *</label>
                            <input type="email" x-model="newStaff.email" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone</label>
                            <input type="text" x-model="newStaff.phone" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Role *</label>
                            <select x-model="newStaff.role_id" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required>
                                <option value="">Select Role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role->id }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Temporary Password *</label>
                            <input type="password" x-model="newStaff.password" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required>
                            <p class="text-xs text-gray-500 mt-1">User should change this after first login</p>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end gap-3">
                        <button type="button" @click="showAddStaffModal = false" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Cancel</button>
                        <button type="submit" :disabled="addingStaff" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition disabled:opacity-50">
                            <span x-show="!addingStaff">Add Staff</span>
                            <span x-show="addingStaff">Adding...</span>
                        </button>
                    </div>
                </form>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
var csrfToken = "{{ csrf_token() }}";
var companyId = {{ $company->id }};
var currencySymbol = "KES";
var initialStats = @json($stats);
var initialPropertiesData = @json($propertiesData);
var initialTenanciesData = @json($tenanciesData);

// Staff Table Component
function staffTable() {
    return {
        staff: [],
        filteredStaff: [],
        paginatedStaff: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        loading: false,
        
        async loadStaff() {
            this.loading = true;
            try {
                const response = await fetch(`/admin/companies/${companyId}/users`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.staff = data.users || [];
                // Update stats
                if (window.companyShowPage) {
                    window.companyShowPage.stats.totalStaff = this.staff.length;
                }
                this.filterStaff();
            } catch (error) {
                console.error('Error loading staff:', error);
                this.staff = [];
            } finally {
                this.loading = false;
            }
        },
        
        filterStaff() {
            let filtered = [...this.staff];
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(s => 
                    s.name?.toLowerCase().includes(term) ||
                    s.email?.toLowerCase().includes(term)
                );
            }
            this.filteredStaff = filtered;
            this.sortStaff();
            this.updateTable();
            this.currentPage = 1;
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortStaff();
            this.updateTable();
        },
        
        sortStaff() {
            this.filteredStaff.sort((a, b) => {
                let aVal = a[this.sortColumn] || '';
                let bVal = b[this.sortColumn] || '';
                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }
                if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredStaff.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedStaff = this.filteredStaff.slice(startIndex, endIndex);
            this.showingStart = this.filteredStaff.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredStaff.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 1) return [1];
            pages.push(1);
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('...');
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) pages.push(i);
            }
            if (end < total - 1) pages.push('...');
            if (total > 1) pages.push(total);
            return pages;
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateTable();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.updateTable();
            }
        },
        
        goToPage(page) {
            if (page !== '...') {
                this.currentPage = parseInt(page);
                this.updateTable();
            }
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        getInitials(name) {
            if (!name) return 'U';
            return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
        },
        
        getRoleBadge(role) {
            const classes = {
                'admin': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                'super_admin': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                'company_admin': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                'property_manager': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                'property_manager': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                'accountant': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-400',
                'meter_reader': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
                'cleaning_staff': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                'maintenance': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                'security': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                'tenant': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
            };
            return classes[role] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
        },
        
        formatRole(role) {
            const map = {
                'admin': 'Admin',
                'super_admin': 'Super Admin',
                'company_admin': 'Company Admin',
                'property_manager': 'Property Manager',
                'property_manager': 'Property Manager',
                'accountant': 'Accountant',
                'meter_reader': 'Meter Reader',
                'cleaning_staff': 'Cleaning Staff',
                'maintenance': 'Maintenance',
                'security': 'Security',
                'tenant': 'Tenant'
            };
            return map[role] || role;
        }
    };
}

// Properties Table Component
function propertiesTable() {
    return {
        properties: [],
        filteredProperties: [],
        paginatedProperties: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        loading: false,
        
        async loadProperties() {
            this.loading = true;
            try {
                const response = await fetch(`/admin/companies/${companyId}/properties`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.properties = data.properties || [];
                // Update stats
                if (window.companyShowPage) {
                    window.companyShowPage.stats.totalProperties = this.properties.length;
                }
                this.filterProperties();
            } catch (error) {
                console.error('Error loading properties:', error);
                this.properties = [];
            } finally {
                this.loading = false;
            }
        },
        
        filterProperties() {
            let filtered = [...this.properties];
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(e => 
                    e.name?.toLowerCase().includes(term) ||
                    e.location?.toLowerCase().includes(term)
                );
            }
            this.filteredProperties = filtered;
            this.sortProperties();
            this.updateTable();
            this.currentPage = 1;
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortProperties();
            this.updateTable();
        },
        
        sortProperties() {
            this.filteredProperties.sort((a, b) => {
                let aVal = a[this.sortColumn] || '';
                let bVal = b[this.sortColumn] || '';
                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }
                if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredProperties.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedProperties = this.filteredProperties.slice(startIndex, endIndex);
            this.showingStart = this.filteredProperties.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredProperties.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 1) return [1];
            pages.push(1);
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('...');
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) pages.push(i);
            }
            if (end < total - 1) pages.push('...');
            if (total > 1) pages.push(total);
            return pages;
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateTable();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.updateTable();
            }
        },
        
        goToPage(page) {
            if (page !== '...') {
                this.currentPage = parseInt(page);
                this.updateTable();
            }
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    };
}

// Subscription Invoices Table Component
function subscriptionInvoicesTable() {
    return {
        invoices: [],
        filteredInvoices: [],
        paginatedInvoices: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'created_at',
        sortDirection: 'desc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        loading: false,
        filterStatus: '',
        
        async loadSubscriptionInvoices() {
            this.loading = true;
            try {
                const response = await fetch(`/admin/companies/${companyId}/subscription-invoices`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.invoices = data.invoices || [];
                // Update total amount
                if (data.total_amount !== undefined) {
                    // Update the total amount display
                }
                this.filterInvoices();
            } catch (error) {
                console.error('Error loading subscription invoices:', error);
                this.invoices = [];
            } finally {
                this.loading = false;
            }
        },
        
        filterInvoices() {
            let filtered = [...this.invoices];
            
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(i => 
                    i.company_name?.toLowerCase().includes(term) ||
                    i.plan_name?.toLowerCase().includes(term) ||
                    i.invoice_number?.toLowerCase().includes(term)
                );
            }
            
            if (this.filterStatus) {
                filtered = filtered.filter(i => i.status === this.filterStatus);
            }
            
            this.filteredInvoices = filtered;
            this.sortInvoices();
            this.updateTable();
            this.currentPage = 1;
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortInvoices();
            this.updateTable();
        },
        
        sortInvoices() {
            this.filteredInvoices.sort((a, b) => {
                let aVal = a[this.sortColumn] || '';
                let bVal = b[this.sortColumn] || '';
                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }
                if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredInvoices.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedInvoices = this.filteredInvoices.slice(startIndex, endIndex);
            this.showingStart = this.filteredInvoices.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredInvoices.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 1) return [1];
            pages.push(1);
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('...');
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) pages.push(i);
            }
            if (end < total - 1) pages.push('...');
            if (total > 1) pages.push(total);
            return pages;
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateTable();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.updateTable();
            }
        },
        
        goToPage(page) {
            if (page !== '...') {
                this.currentPage = parseInt(page);
                this.updateTable();
            }
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        formatCurrency(value) {
            return 'KES ' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2 });
        }
    };
}

// Expenses Table Component
function expensesTable() {
    return {
        expenses: [],
        filteredExpenses: [],
        paginatedExpenses: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        filterCategory: '',
        filterStatus: '',
        sortColumn: 'expense_date',
        sortDirection: 'desc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        totalExpenses: 0,
        categories: [],
        loading: false,
        
        async loadExpenses() {
            this.loading = true;
            try {
                const response = await fetch(`/admin/companies/${companyId}/expenses`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.expenses = data.expenses || [];
                this.totalExpenses = data.total_expenses || 0;
                
                // Extract unique categories
                const cats = new Set();
                this.expenses.forEach(e => {
                    if (e.category_name) cats.add(e.category_name);
                });
                this.categories = Array.from(cats).sort();
                
                this.filterExpenses();
            } catch (error) {
                console.error('Error loading expenses:', error);
                this.expenses = [];
            } finally {
                this.loading = false;
            }
        },
        
        filterExpenses() {
            let filtered = [...this.expenses];
            
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(e => 
                    e.property_name?.toLowerCase().includes(term) ||
                    e.payee_name?.toLowerCase().includes(term) ||
                    e.description?.toLowerCase().includes(term) ||
                    e.category_name?.toLowerCase().includes(term)
                );
            }
            
            if (this.filterCategory) {
                filtered = filtered.filter(e => e.category_name === this.filterCategory);
            }
            
            if (this.filterStatus) {
                filtered = filtered.filter(e => e.status === this.filterStatus);
            }
            
            this.filteredExpenses = filtered;
            this.sortExpenses();
            this.updateTable();
            this.currentPage = 1;
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortExpenses();
            this.updateTable();
        },
        
        sortExpenses() {
            this.filteredExpenses.sort((a, b) => {
                let aVal = a[this.sortColumn];
                let bVal = b[this.sortColumn];
                
                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }
                
                if (aVal === null || aVal === undefined) return this.sortDirection === 'asc' ? 1 : -1;
                if (bVal === null || bVal === undefined) return this.sortDirection === 'asc' ? -1 : 1;
                
                if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredExpenses.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedExpenses = this.filteredExpenses.slice(startIndex, endIndex);
            this.showingStart = this.filteredExpenses.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredExpenses.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 1) return [1];
            pages.push(1);
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('...');
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) pages.push(i);
            }
            if (end < total - 1) pages.push('...');
            if (total > 1) pages.push(total);
            return pages;
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateTable();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.updateTable();
            }
        },
        
        goToPage(page) {
            if (page !== '...') {
                this.currentPage = parseInt(page);
                this.updateTable();
            }
        },
        
        formatCurrency(value) {
            return 'KES ' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
    };
}

// Main Component
document.addEventListener('alpine:init', () => {
    Alpine.data('companyShowPage', () => ({
        activeTab: 'info',
        showEditModal: false,
        showAddStaffModal: false,
        addingStaff: false,
        companyStatus: {{ $company->is_active ? 'true' : 'false' }},
        
        stats: {
            totalStaff: {{ $stats['totalStaff'] ?? 0 }},
            totalProperties: {{ $stats['totalProperties'] ?? 0 }},
            totalUnits: {{ $stats['totalUnits'] ?? 0 }},
            totalTenants: {{ $stats['totalTenants'] ?? 0 }},
            totalInvoices: {{ $stats['totalInvoices'] ?? 0 }},
            totalExpenses: {{ $stats['totalExpenses'] ?? 0 }},
            totalRevenue: {{ $stats['totalRevenue'] ?? 0 }}
        },
        
        editForm: {
            name: '{{ addslashes($company->name) }}',
            registration_number: '{{ $company->registration_number }}',
            tax_id: '{{ $company->tax_id }}',
            email: '{{ $company->email }}',
            phone: '{{ $company->phone }}',
            address: '{{ addslashes($company->address) }}',
            is_active: {{ $company->is_active ? 'true' : 'false' }}
        },
        
        newStaff: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            role_id: '',
            password: ''
        },
        
        // Invoice properties
        invoices: [],
        invoiceSearch: '',
        invoiceStatusFilter: '',
        invoiceCurrentPage: 1,
        invoiceItemsPerPage: 10,
        
        // Computed
        get filteredInvoices() {
            let filtered = this.invoices;
            if (this.invoiceSearch) {
                const search = this.invoiceSearch.toLowerCase();
                filtered = filtered.filter(i => 
                    i.tenant_name?.toLowerCase().includes(search) ||
                    i.unit_number?.toLowerCase().includes(search) ||
                    i.id.toString().includes(search)
                );
            }
            if (this.invoiceStatusFilter) {
                filtered = filtered.filter(i => i.status === this.invoiceStatusFilter);
            }
            return filtered;
        },
        
        get paginatedInvoices() {
            const start = (this.invoiceCurrentPage - 1) * this.invoiceItemsPerPage;
            return this.filteredInvoices.slice(start, start + this.invoiceItemsPerPage);
        },
        
        get invoiceTotalPages() {
            return Math.ceil(this.filteredInvoices.length / this.invoiceItemsPerPage);
        },
        
        get invoiceShowingStart() {
            return this.filteredInvoices.length ? (this.invoiceCurrentPage - 1) * this.invoiceItemsPerPage + 1 : 0;
        },
        
        get invoiceShowingEnd() {
            return Math.min(this.invoiceCurrentPage * this.invoiceItemsPerPage, this.filteredInvoices.length);
        },
        
        init() {
            // Make this component available globally
            window.companyShowPage = this;
        },
        
        formatCurrency(value) {
            return 'KES ' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        getInvoiceStatusClass(status) {
            const classes = {
                'paid': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                'unpaid': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                'partial': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
            };
            return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
        },
        
        openEditModal() {
            this.showEditModal = true;
        },
        
        openAddStaffModal() {
            this.newStaff = {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                role_id: '',
                password: ''
            };
            this.showAddStaffModal = true;
        },
        
        async toggleStatus() {
            const newStatus = !this.companyStatus;
            try {
                const response = await fetch(`/admin/companies/${companyId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ...this.editForm, is_active: newStatus })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.companyStatus = newStatus;
                    this.editForm.is_active = newStatus;
                    alert(`Company ${newStatus ? 'activated' : 'deactivated'} successfully!`);
                } else {
                    alert(result.message || 'Error updating company status');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating company status');
            }
        },
        
        async saveCompany() {
            try {
                const response = await fetch(`/admin/companies/${companyId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.editForm)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showEditModal = false;
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.message || 'Error updating company');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating company');
            }
        },
        
        async addStaff() {
            if (!this.newStaff.first_name || !this.newStaff.last_name || !this.newStaff.email || !this.newStaff.role_id || !this.newStaff.password) {
                alert('Please fill in all required fields.');
                return;
            }
            
            this.addingStaff = true;
            
            try {
                const response = await fetch(`/admin/companies/${companyId}/users`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.newStaff)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showAddStaffModal = false;
                    // Reload staff table
                    this.loadStaff();
                    alert('Staff member added successfully!');
                } else {
                    alert(result.message || 'Error adding staff member');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error adding staff member');
            } finally {
                this.addingStaff = false;
            }
        },
        
        // Tab loading methods
        loadStaff() {
            // Handled by staffTable component
        },
        
        loadProperties() {
            // Handled by propertiesTable component
        },
        
        loadTenancies() {
            // Handled by tenanciesTable component
        },
        
        loadSubscriptions() {
            // Handled by subscriptionsTable component
        },
        
        loadSubscriptionInvoices() {
            // Handled by subscriptionInvoicesTable component
        },
        
        loadExpenses() {
            // Handled by expensesTable component
        },
        
        async loadInvoices() {
            try {
                const response = await fetch(`/admin/companies/${companyId}/invoices`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await response.json();
                this.invoices = data.invoices || [];
                this.stats.totalInvoices = this.invoices.length;
                this.stats.totalRevenue = data.total_revenue || 0;
            } catch (error) {
                console.error('Error loading invoices:', error);
            }
        },
        
        invoicePrevPage() {
            if (this.invoiceCurrentPage > 1) {
                this.invoiceCurrentPage--;
            }
        },
        
        invoiceNextPage() {
            if (this.invoiceCurrentPage < this.invoiceTotalPages) {
                this.invoiceCurrentPage++;
            }
        }
    }));
});
</script>
@endsection