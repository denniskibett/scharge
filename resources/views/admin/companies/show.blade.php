{{-- resources/views/admin/companies/show.blade.php --}}
@extends('layouts.app')

@section('title', $company->name)

@section('content')
<div x-data="companyShowPage()" x-init="init()" class="container-fluid px-4 py-4">
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
        
        <!-- Estates -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Estates</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalEstates">{{ $stats['totalEstates'] ?? 0 }}</h3>
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
                
                <!-- Estates Tab -->
                <button @click="activeTab = 'estates'; loadEstates()" :class="activeTab === 'estates' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Estates <span class="ml-1 text-xs" x-text="stats.totalEstates">0</span>
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
            
            <!-- Estates Tab -->
            <div x-show="activeTab === 'estates'" x-cloak>
                <div x-data="estatesTable()" x-init="loadEstates()">
                    @include('partials.table.table-estates')
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
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tenant Invoices</h3>
                    <div class="flex gap-2">
                        <input type="text" x-model="invoiceSearch" placeholder="Search invoices..." class="rounded-lg border-gray-300 px-3 py-1 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <select x-model="invoiceStatusFilter" class="rounded-lg border-gray-300 px-3 py-1 text-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                                <tr>
                                    <td class="px-4 py-3 text-sm" x-text="'#' + invoice.id"></td>
                                    <td class="px-4 py-3 text-sm" x-text="invoice.tenant_name"></td>
                                    <td class="px-4 py-3 text-sm" x-text="invoice.unit_number"></td>
                                    <td class="px-4 py-3 text-sm font-medium" x-text="formatCurrency(invoice.total_amount)"></td>
                                    <td class="px-4 py-3">
                                        <span :class="getInvoiceStatusClass(invoice.status)" class="px-2 py-1 text-xs rounded-full" x-text="invoice.status"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm" x-text="formatDate(invoice.created_at)"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredInvoices.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No invoices found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div x-show="filteredInvoices.length > 0" class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-gray-500">Showing <span x-text="invoiceShowingStart"></span> to <span x-text="invoiceShowingEnd"></span> of <span x-text="filteredInvoices.length"></span></div>
                    <div class="flex gap-2">
                        <button @click="invoicePrevPage()" :disabled="invoiceCurrentPage === 1" class="px-3 py-1 border rounded disabled:opacity-50">Previous</button>
                        <button @click="invoiceNextPage()" :disabled="invoiceCurrentPage === invoiceTotalPages" class="px-3 py-1 border rounded disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
    <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEditModal = false"></div>
    <div class="fixed inset-y-0 right-0 max-w-full flex">
        <div class="relative w-screen max-w-md">
            <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto dark:bg-gray-900">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-white">Edit Company</h2>
                        <button @click="showEditModal = false" class="text-white hover:text-gray-200">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="saveCompany" class="flex-1">
                    <div class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name *</label>
                            <input type="text" x-model="editForm.name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registration Number</label>
                            <input type="text" x-model="editForm.registration_number" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax ID</label>
                            <input type="text" x-model="editForm.tax_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" x-model="editForm.email" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" x-model="editForm.phone" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <textarea x-model="editForm.address" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="editForm.is_active" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div x-show="showAddStaffModal" x-cloak class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
    <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddStaffModal = false"></div>
    <div class="fixed inset-y-0 right-0 max-w-full flex">
        <div class="relative w-screen max-w-md">
            <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto dark:bg-gray-900">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-white">Add Staff Member</h2>
                        <button @click="showAddStaffModal = false" class="text-white hover:text-gray-200">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="addStaff" class="flex-1">
                    <div class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name *</label>
                            <input type="text" x-model="newStaff.first_name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name *</label>
                            <input type="text" x-model="newStaff.last_name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                            <input type="email" x-model="newStaff.email" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" x-model="newStaff.phone" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role *</label>
                            <select x-model="newStaff.role_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                                <option value="">Select Role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role->id }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Temporary Password *</label>
                            <input type="password" x-model="newStaff.password" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                            <p class="text-xs text-gray-500 mt-1">User should change this after first login</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button type="button" @click="showAddStaffModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                        <button type="submit" :disabled="addingStaff" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                            <span x-show="!addingStaff">Add Staff</span>
                            <span x-show="addingStaff">Adding...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
const csrfToken = "{{ csrf_token() }}";
const companyId = {{ $company->id }};
const currencySymbol = "KES";
const initialStats = @json($stats);
const initialEstatesData = @json($estatesData);
const initialTenanciesData = @json($tenanciesData);

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
                'estate_manager': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
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
                'estate_manager': 'Estate Manager',
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

// Estates Table Component
function estatesTable() {
    return {
        estates: [],
        filteredEstates: [],
        paginatedEstates: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        loading: false,
        
        async loadEstates() {
            this.loading = true;
            try {
                const response = await fetch(`/admin/companies/${companyId}/estates`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.estates = data.estates || [];
                // Update stats
                if (window.companyShowPage) {
                    window.companyShowPage.stats.totalEstates = this.estates.length;
                }
                this.filterEstates();
            } catch (error) {
                console.error('Error loading estates:', error);
                this.estates = [];
            } finally {
                this.loading = false;
            }
        },
        
        filterEstates() {
            let filtered = [...this.estates];
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(e => 
                    e.name?.toLowerCase().includes(term) ||
                    e.location?.toLowerCase().includes(term)
                );
            }
            this.filteredEstates = filtered;
            this.sortEstates();
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
            this.sortEstates();
            this.updateTable();
        },
        
        sortEstates() {
            this.filteredEstates.sort((a, b) => {
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
            this.totalPages = Math.ceil(this.filteredEstates.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedEstates = this.filteredEstates.slice(startIndex, endIndex);
            this.showingStart = this.filteredEstates.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredEstates.length);
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
                    e.estate_name?.toLowerCase().includes(term) ||
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
            totalEstates: {{ $stats['totalEstates'] ?? 0 }},
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
                    window.location.reload();
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
        
        loadEstates() {
            // Handled by estatesTable component
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