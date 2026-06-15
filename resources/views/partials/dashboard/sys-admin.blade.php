{{-- resources/views/dashboard/sys-admin.blade.php --}}
@extends('layouts.app')

@section('title', 'System Administration')

@section('content')
<div x-data="sysAdminDashboard()" x-init="init()" x-cloak>
    <div class="container-fluid px-4 py-4">
        
        <!-- Welcome Card - SysAdmin Style -->
        <div class="row mb-6">
            <div class="col-12">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-700 p-6 shadow-lg">
                    <div class="absolute inset-0 opacity-10">
                        <svg class="absolute -right-20 -top-20 h-64 w-64 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    
                    <div class="relative">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-white">System Administration</h2>
                                        <p class="text-purple-100 mt-1" x-text="currentDate"></p>
                                        <p class="text-purple-50 text-sm mt-2">Manage companies, subscriptions, and system-wide settings</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-sm text-purple-100">Logged in as</p>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm">
                                            {{ Auth::user()->name }}
                                        </span>
                                    </div>
                                    <img src="{{ Auth::user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->name) . '&background=ffffff&color=7c3aed' }}" 
                                         alt="avatar" class="h-14 w-14 rounded-full border-2 border-white shadow-lg">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Companies</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ number_format($stats['total_companies'] ?? 0) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/15">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        <span>Active: {{ number_format($stats['active_companies'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                        <span>Pending: {{ number_format($stats['pending_companies'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Users</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ number_format($stats['total_users'] ?? 0) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        <span>Verified: {{ number_format($stats['verified_users'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                        <span>Pending: {{ number_format($stats['pending_verification_users'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Platform Metrics</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ number_format($stats['total_units'] ?? 0) }} Units
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4V4z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                    {{ number_format($stats['total_tenants'] ?? 0) }} Tenants | 
                    KES {{ number_format($stats['total_revenue'] ?? 0, 0) }} Revenue
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Monthly Recurring Revenue</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            KES {{ number_format($stats['monthly_recurring_revenue'] ?? 0, 0) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/15">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                    Platform-wide MRR
                </div>
            </div>
        </div>

        @include('partials.card.card-revenue-trends', ['revenueTrends' => $stats['revenue_trends'] ?? []])

        <!-- Tab Navigation -->
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeTab = 'companies'" :class="activeTab === 'companies' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            Companies
                        </button>
                        <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Pending Users
                        </button>
                        <button @click="activeTab = 'subscriptions'" :class="activeTab === 'subscriptions' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Subscriptions
                        </button>
                        <button @click="activeTab = 'system'" :class="activeTab === 'system' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            System Settings
                        </button>
                    </div>
                </div>
                
                <div class="p-5">
                    <!-- Companies Tab -->
                    <div x-show="activeTab === 'companies'">
                        @include('partials.table.table-companies', ['companies' => $companies ?? []])
                    </div>
                    
                    <!-- Pending Users Tab -->
                    <div x-show="activeTab === 'users'">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($pendingUsers ?? [] as $user)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <span class="text-sm font-medium">{{ substr($user['name'], 0, 1) }}</span>
                                                </div>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $user['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $user['email'] }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                {{ ucfirst($user['role_name']) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            @if($user['company_name'])
                                                {{ $user['company_name'] }}
                                            @else
                                                <span class="text-gray-400">Not assigned</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $user['created_at_formatted'] }}</td>
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <button onclick="verifyUser({{ $user['id'] }})" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 text-sm">
                                                    Verify
                                                </button>
                                                <button onclick="assignCompany({{ $user['id'] }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                                                    Assign Company
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium">No pending users</h3>
                                            <p class="mt-1 text-sm">All users have been verified and assigned to companies.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Subscriptions Tab -->
                    <div x-show="activeTab === 'subscriptions'">
                        @include('partials.table.table-subscriptions')
                    </div>
                    
                    <!-- System Settings Tab -->
                    <div x-show="activeTab === 'system'">
                        <div class="space-y-6">
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800/50">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">System Configuration</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Water Rate (KES/m³)</label>
                                        <input type="number" id="defaultWaterRate" value="{{ $systemSettings['default_water_rate'] ?? 50 }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice Due Days</label>
                                        <input type="number" id="dueDays" value="{{ $systemSettings['invoice_due_days'] ?? 30 }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Late Fee Percentage</label>
                                        <input type="number" id="lateFee" value="{{ $systemSettings['late_fee_percentage'] ?? 5 }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maintenance SLA (Days)</label>
                                        <input type="number" id="maintenanceSla" value="{{ $systemSettings['maintenance_sla_days'] ?? 3 }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white px-3 py-2">
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <button onclick="saveSystemSettings()" class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-purple-700 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                                        </svg>
                                        Save Settings
                                    </button>
                                </div>
                            </div>
                            
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800/50">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">System Health</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl dark:bg-gray-900/50">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Database Status</p>
                                            <p class="text-green-600 font-medium">Connected</p>
                                        </div>
                                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl dark:bg-gray-900/50">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Cache Status</p>
                                            <p class="text-green-600 font-medium">Operational</p>
                                        </div>
                                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl dark:bg-gray-900/50">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Queue Worker</p>
                                            <p class="text-green-600 font-medium">Running</p>
                                        </div>
                                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
function sysAdminDashboard() {
    return {
        activeTab: 'companies',
        currentDate: new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        
        init() {
            console.log('SysAdmin Dashboard loaded');
        }
    };
}

function verifyUser(userId) {
    if (confirm('Verify this user? They will be able to log in and access the system.')) {
        fetch(`/admin/users/${userId}/verify`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert('User verified successfully!');
                  location.reload();
              } else {
                  alert(data.message || 'Failed to verify user');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              alert('An error occurred while verifying the user');
          });
    }
}

function assignCompany(userId) {
    const companyId = prompt('Enter Company ID to assign this user to:');
    if (companyId && !isNaN(companyId)) {
        fetch(`/admin/users/${userId}/assign-company`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ company_id: parseInt(companyId) })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert('Company assigned successfully!');
                  location.reload();
              } else {
                  alert(data.message || 'Failed to assign company');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              alert('An error occurred while assigning the company');
          });
    } else if (companyId) {
        alert('Please enter a valid Company ID (number)');
    }
}

function saveSystemSettings() {
    const settings = {
        default_water_rate: document.getElementById('defaultWaterRate')?.value || 50,
        invoice_due_days: document.getElementById('dueDays')?.value || 30,
        late_fee_percentage: document.getElementById('lateFee')?.value || 5,
        maintenance_sla_days: document.getElementById('maintenanceSla')?.value || 3
    };
    
    fetch('/system/update', {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(settings)
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              alert('Settings saved successfully!');
          } else {
              alert(data.message || 'Failed to save settings');
          }
      })
      .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while saving settings');
      });
}
</script>
@endsection