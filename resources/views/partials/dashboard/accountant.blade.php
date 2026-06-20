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

        <!-- Pending Deposits Alert Banner -->
        <div x-show="pendingDepositsCount > 0" class="mt-4 mb-4">
            <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <svg class="h-8 w-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-400">
                                <span x-text="pendingDepositsCount"></span> Pending Deposit(s) Awaiting Approval
                            </p>
                            <p class="text-xs text-yellow-700 dark:text-yellow-500">Manual top-ups and transaction messages need your verification</p>
                        </div>
                    </div>
                    <button @click="activeTab = 'pending'" 
                        class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Review Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-1">
                        <!-- Pending Deposits Tab -->
                        <button @click="activeTab = 'pending'" 
                            :class="activeTab === 'pending' ? 'border-yellow-500 text-yellow-600 dark:text-yellow-400 border-b-2 -mb-px bg-yellow-50 dark:bg-yellow-900/10' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                            class="px-4 py-2 text-sm font-medium transition-colors relative inline-flex items-center gap-2 rounded-t-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Pending Deposits
                            <span x-show="pendingDepositsCount > 0" 
                                class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                <span x-text="pendingDepositsCount"></span>
                            </span>
                        </button>
                        
                        <!-- Recent Transactions Tab -->
                        <button @click="activeTab = 'transactions'" 
                            :class="activeTab === 'transactions' ? 'border-blue-500 text-blue-600 dark:text-blue-400 border-b-2 -mb-px bg-blue-50 dark:bg-blue-900/10' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                            class="px-4 py-2 text-sm font-medium transition-colors relative inline-flex items-center gap-2 rounded-t-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                            </svg>
                            Recent Transactions
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                <span x-text="transactionsCount"></span>
                            </span>
                        </button>
                        
                        <!-- Overdue Invoices Tab -->
                        <button @click="activeTab = 'overdue'" 
                            :class="activeTab === 'overdue' ? 'border-red-500 text-red-600 dark:text-red-400 border-b-2 -mb-px bg-red-50 dark:bg-red-900/10' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                            class="px-4 py-2 text-sm font-medium transition-colors relative inline-flex items-center gap-2 rounded-t-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Overdue Invoices
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                <span x-text="overdueCount"></span>
                            </span>
                        </button>
                        
                        <!-- Revenue Analytics Tab -->
                        <button @click="activeTab = 'revenue'" 
                            :class="activeTab === 'revenue' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px bg-purple-50 dark:bg-purple-900/10' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                            class="px-4 py-2 text-sm font-medium transition-colors relative inline-flex items-center gap-2 rounded-t-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Revenue Analytics
                        </button>
                    </div>
                </div>
                
                <div class="p-5">
                    <!-- Pending Deposits Tab -->
                    <div x-show="activeTab === 'pending'">
                        @include('partials.table.table-transactions', [
                            'transactions' => $pendingTransactions ?? [],
                            'showActions' => true,
                            'emptyMessage' => 'No pending deposits awaiting approval'
                        ])
                    </div>
                    
                    <!-- Recent Transactions Tab -->
                    <div x-show="activeTab === 'transactions'">
                        @include('partials.table.table-transactions', [
                            'transactions' => $allTransactions ?? [],
                            'showActions' => false,
                            'emptyMessage' => 'No transactions found'
                        ])
                    </div>
                    
                    <!-- Overdue Invoices Tab -->
                    <div x-show="activeTab === 'overdue'">
                        @include('partials.table.table-invoices', [
                            'mappedInvoices' => collect($overdueInvoices ?? []), 
                            'mappedActiveTenancies' => collect()
                        ])
                    </div>
                    
                    <!-- Revenue Analytics Tab -->
                    <div x-show="activeTab === 'revenue'">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Monthly Revenue Chart -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Monthly Revenue</h3>
                                <canvas id="revenueChart" height="250"></canvas>
                            </div>
                            
                            <!-- Payment Methods -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Methods</h3>
                                <canvas id="paymentMethodsChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function accountantDashboard() {
    return {
        activeTab: 'pending',
        currentDate: new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        
        // Counts - initialize with PHP data
        pendingDepositsCount: {{ count($pendingTransactions ?? []) }},
        transactionsCount: {{ count($allTransactions ?? []) }},
        overdueCount: {{ count($overdueInvoices ?? []) }},
        
        // Transaction Data - initialize with PHP data
        pendingTransactions: @json($pendingTransactions ?? []),
        allTransactions: @json($allTransactions ?? []),
        loading: false,
        
        init() {
            console.log('Accountant Dashboard loaded');
            console.log('Pending Transactions:', this.pendingTransactions.length);
            console.log('All Transactions:', this.allTransactions.length);
            console.log('Pending Count:', this.pendingDepositsCount);
            console.log('Transactions Count:', this.transactionsCount);
            
            // Listen for refresh events from table component
            window.addEventListener('refresh-transactions', () => {
                this.loadAllTransactions();
                this.loadPendingTransactions();
            });
            
            // Listen for approve/reject events from table component
            window.addEventListener('approve-transaction', (event) => {
                this.approveTransaction(event.detail);
            });
            
            window.addEventListener('reject-transaction', (event) => {
                this.rejectTransaction(event.detail);
            });
            
            window.addEventListener('view-transaction', (event) => {
                console.log('View transaction:', event.detail);
                this.viewTransaction(event.detail);
            });
            
            // Initialize charts if revenue tab is active
            if (this.activeTab === 'revenue') {
                this.initCharts();
            }
        },
        
        async loadPendingTransactions() {
            this.loading = true;
            try {
                const response = await fetch('/api/wallet/pending-deposits', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    this.pendingTransactions = data.data || [];
                    this.pendingDepositsCount = this.pendingTransactions.length;
                    console.log('Pending updated:', this.pendingDepositsCount);
                }
            } catch (error) {
                console.error('Error loading pending deposits:', error);
            } finally {
                this.loading = false;
            }
        },
        
        async loadAllTransactions() {
            this.loading = true;
            try {
                const response = await fetch('/api/wallet/transactions?per_page=1000&include_pending=true', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    this.allTransactions = data.data || [];
                    this.transactionsCount = this.allTransactions.length;
                    console.log('All transactions updated:', this.transactionsCount);
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
            } finally {
                this.loading = false;
            }
        },
        
        async approveTransaction(tx) {
            if (!confirm(`Approve deposit of KES ${this.formatNumber(tx.amount)} for ${tx.tenant_name}?`)) return;
            
            try {
                const response = await fetch(`/api/wallet/approve-deposit/${tx.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert('Deposit approved successfully!');
                    await this.loadPendingTransactions();
                    await this.loadAllTransactions();
                    window.dispatchEvent(new CustomEvent('wallet-updated', {
                        detail: { refresh: true }
                    }));
                } else {
                    alert(data.error || 'Failed to approve deposit');
                }
            } catch (error) {
                console.error('Error approving deposit:', error);
                alert('An error occurred');
            }
        },
        
        async rejectTransaction(tx) {
            if (!confirm(`Reject deposit of KES ${this.formatNumber(tx.amount)} for ${tx.tenant_name}?`)) return;
            
            try {
                const response = await fetch(`/api/wallet/reject-deposit/${tx.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert('Deposit rejected successfully');
                    await this.loadPendingTransactions();
                    await this.loadAllTransactions();
                } else {
                    alert(data.error || 'Failed to reject deposit');
                }
            } catch (error) {
                console.error('Error rejecting deposit:', error);
                alert('An error occurred');
            }
        },
        
        viewTransaction(tx) {
            const details = [
                'Transaction Details',
                '━━━━━━━━━━━━━━━━━━━━━',
                `Amount: KES ${this.formatNumber(tx.amount)}`,
                `Tenant: ${tx.tenant_name || 'N/A'}`,
                `Unit: ${tx.tenant_unit || 'N/A'}`,
                `Method: ${tx.payment_method || 'N/A'}`,
                `Reference: ${tx.reference || 'N/A'}`,
                `Date: ${this.formatDate(tx.created_at)}`,
                `Status: ${tx.is_pending ? 'Pending Approval' : 'Completed'}`,
                tx.notes ? `Message: ${tx.notes}` : '',
                tx.bill_month ? `Bill Month: ${this.formatMonth(tx.bill_month)}` : '',
                tx.phone_number ? `Phone: ${tx.phone_number}` : '',
            ].filter(Boolean).join('\n');
            
            alert(details);
        },
        
        formatNumber(value) {
            return parseFloat(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-KE', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        formatMonth(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-KE', { year: 'numeric', month: 'long' });
        },
        
        initCharts() {
            setTimeout(() => {
                const revenueData = @json($monthlyRevenue ?? []);
                const paymentMethods = @json($paymentMethods ?? []);
                
                const ctx1 = document.getElementById('revenueChart');
                if (ctx1 && Object.keys(revenueData).length > 0) {
                    new Chart(ctx1, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(revenueData),
                            datasets: [{
                                label: 'Revenue (KES)',
                                data: Object.values(revenueData),
                                backgroundColor: 'rgba(16, 185, 129, 0.6)',
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'KES ' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                const ctx2 = document.getElementById('paymentMethodsChart');
                if (ctx2 && Object.keys(paymentMethods).length > 0) {
                    const colors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];
                    const labels = Object.keys(paymentMethods);
                    const data = Object.values(paymentMethods);
                    
                    new Chart(ctx2, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: colors.slice(0, labels.length),
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                }
                            }
                        }
                    });
                }
            }, 300);
        }
    };
}
</script>
@endsection