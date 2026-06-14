{{-- resources/views/partials/dashboard/accountant.blade.php --}}
@extends('layouts.app')

@section('title', 'Accountant Dashboard')

@section('content')
<div x-data="accountantDashboard()" x-init="init()">
    <div class="container-fluid px-4 py-4">
        
        <!-- Welcome Card -->
        <div class="row mb-6">
            <div class="col-12">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-green-600 to-teal-600 p-6 shadow-lg">
                    <div class="absolute inset-0 opacity-10">
                        <svg class="absolute -right-20 -top-20 h-64 w-64 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    
                    <div class="relative">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-white">Accountant Dashboard</h2>
                                        <p class="text-green-100 mt-1" x-text="currentDate"></p>
                                        <div class="mt-2 flex items-center gap-2 text-green-100 text-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            <span>Company: <strong>{{ $company->name ?? 'N/A' }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <div class="text-right">
                                    <p class="text-sm text-green-100">Your Role</p>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm">
                                        Accountant
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
       @include('partials.card.card-dashboard', ['cardData' => array_merge($stats, ['user_role' => 'accountant'])])

        <!-- Tabs -->
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeTab = 'overdue'" :class="activeTab === 'overdue' ? 'border-green-500 text-green-600 dark:text-green-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Overdue Invoices ({{ collect($roleData['overdueInvoices'] ?? [])->count() }})
                        </button>
                        <button @click="activeTab = 'transactions'" :class="activeTab === 'transactions' ? 'border-green-500 text-green-600 dark:text-green-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Recent Transactions ({{ collect($roleData['recentTransactions'] ?? [])->count() }})
                        </button>
                        <button @click="activeTab = 'revenue'" :class="activeTab === 'revenue' ? 'border-green-500 text-green-600 dark:text-green-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Revenue Analytics
                        </button>
                    </div>
                </div>
                
                <div class="p-5">
                    <div x-show="activeTab === 'overdue'">
                        @include('partials.table.table-invoices', ['mappedInvoices' => collect($roleData['overdueInvoices'] ?? []), 'mappedActiveTenancies' => collect()])
                    </div>
                    <div x-show="activeTab === 'transactions'">
                        @include('partials.table.table-payments', ['payments' => $roleData['recentTransactions'] ?? [], 'showActions' => true, 'showTenant' => true])
                    </div>
                    <div x-show="activeTab === 'revenue'">
                        @include('partials.chart.revenue-chart', ['monthlyRevenue' => $monthlyRevenue ?? []])
                        @include('partials.chart.payment-methods', ['paymentMethods' => $paymentMethods ?? []])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function accountantDashboard() {
    return {
        activeTab: 'overdue',
        currentDate: new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        
        init() {
            console.log('Accountant Dashboard loaded');
        }
    };
}
</script>
@endsection