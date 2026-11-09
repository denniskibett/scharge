{{-- resources/views/partials/dashboard/sys-admin.blade.php --}}
@extends('layouts.app')

@section('title', 'System Administration')

@section('content')
<div x-data="sysAdminDashboard()" x-init="init()" x-cloak>
    <div>
        
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

        @include('partials.dashboard.analytics-summary')

        <!-- Subscription Stats Card -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Active Subscriptions</span>
                    <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                        {{ number_format($subscriptionStats['active'] ?? 0) }}
                    </h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/15">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs">
                <div class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    <span>Active: {{ number_format($subscriptionStats['active'] ?? 0) }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    <span>Trial: {{ number_format($subscriptionStats['trial'] ?? 0) }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                    <span>Past Due: {{ number_format($subscriptionStats['past_due'] ?? 0) }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span>Cancelled: {{ number_format($subscriptionStats['cancelled'] ?? 0) }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-gray-500"></span>
                    <span>Expired: {{ number_format($subscriptionStats['expired'] ?? 0) }}</span>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">MRR: <strong class="text-gray-900 dark:text-white">KES {{ number_format($subscriptionStats['total_mrr'] ?? 0, 0) }}</strong></span>
                    <span class="text-gray-500">Monthly: {{ number_format($subscriptionStats['monthly_cycle'] ?? 0) }}</span>
                    <span class="text-gray-500">Yearly: {{ number_format($subscriptionStats['yearly_cycle'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        <!-- Revenue by Plan Chart -->
        @if(!empty($subscriptionStats['revenue_by_plan']) && count($subscriptionStats['revenue_by_plan']) > 0)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 mb-6">
            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90 mb-4">Revenue by Plan</h4>
            <div class="space-y-3">
                @foreach($subscriptionStats['revenue_by_plan'] as $plan)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $plan['plan_name'] }}</span>
                        <span class="text-gray-600 dark:text-gray-400">
                            {{ number_format($plan['count']) }} companies · 
                            {{ number_format($plan['avg_units'] ?? 0) }} avg units · 
                            KES {{ number_format($plan['revenue'] ?? 0, 0) }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                        @php
                            $percentage = $maxRevenue > 0 ? min(100, (($plan['revenue'] ?? 0) / $maxRevenue) * 100) : 0;
                        @endphp
                        <div class="bg-purple-600 h-2 rounded-full" 
                            style="width: {{ max(0, $percentage) }}%">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Tab Navigation -->
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <!-- Tab 1: Companies -->
                        <button @click="activeTab = 'companies'" :class="activeTab === 'companies' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            Companies
                        </button>
                        
                        <!-- Tab 2: Pending Users -->
                        <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Pending Users
                        </button>
                        
                        <!-- Tab 3: Subscription Plans -->
                        <button @click="activeTab = 'subscription_plans'" :class="activeTab === 'subscription_plans' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Plans
                        </button>
                        
                        <!-- Tab 4: Company Subscriptions -->
                        <button @click="activeTab = 'company_subscriptions'" :class="activeTab === 'company_subscriptions' ? 'border-purple-500 text-purple-600 dark:text-purple-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            Company Subs
                        </button>
                    </div>
                </div>
                
                <div class="p-5">
                    <!-- Companies Tab Content -->
                    <div x-show="activeTab === 'companies'">
                        @include('partials.table.table-companies', ['companies' => $companies ?? []])
                    </div>
                    
                    <!-- Pending Users Tab Content -->
                    <div x-show="activeTab === 'users'">
                        <div class="w-full overflow-x-auto">
                            <table class="data-table w-full table-auto">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-800">
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">User</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Email</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Role</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Company</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Registered</th>
                                        <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                                    @forelse($pendingUsers ?? [] as $user)
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <td class="p-4 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                                    <span class="text-sm font-medium text-purple-600">{{ substr($user['name'], 0, 1) }}</span>
                                                </div>
                                                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $user['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user['email'] }}</td>
                                        <td class="p-4 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                {{ ucfirst($user['role_name']) }}
                                            </span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            @if($user['company_name'])
                                                {{ $user['company_name'] }}
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">Not assigned</span>
                                            @endif
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user['created_at_formatted'] }}</td>
                                        <td class="p-4 whitespace-nowrap text-right">
                                            <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                              <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                                                <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                                              </button>
                                              <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                                <button type="button" @click="verifyUser({{ $user['id'] }}); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Verify</button>
                                                <button type="button" @click="openAssignModal({{ $user['id'] }}, '{{ addslashes($user['name']) }}', '{{ addslashes($user['email']) }}', '{{ addslashes($user['role_name']) }}'); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Assign company</button>
                                                <button type="button" @click="suspendUser({{ $user['id'] }}, '{{ addslashes($user['name']) }}'); open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Suspend</button>
                                              </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No pending users</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All users have been verified and assigned to companies.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- SUBSCRIPTION PLANS TAB CONTENT -->
                    <div x-show="activeTab === 'subscription_plans'">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Subscription Plans</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Manage available subscription plans and pricing tiers</p>
                            </div>
                            <a href="{{ route('admin.subscriptions.plans.index') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Manage Plans
                            </a>
                        </div>
                        @include('partials.table.table-subscriptions')
                    </div>
                    
                    <!-- COMPANY SUBSCRIPTIONS TAB CONTENT -->
                    <div x-show="activeTab === 'company_subscriptions'">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Company Subscriptions</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">View which companies are subscribed to which plans</p>
                            </div>
                            <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                View All
                            </a>
                        </div>
                        
                        <!-- Server-side rendered table -->
                        <div class="w-full overflow-x-auto">
                            <table class="data-table w-full table-auto">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-800">
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Company</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Plan</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Billing Cycle</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Starts</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Ends</th>
                                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Auto Renew</th>
                                        <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                                    @forelse($activeSubscriptions ?? [] as $subscription)
                                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                            {{ $subscription['company_name'] ?? 'N/A' }}
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                {{ $subscription['plan_name'] ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                                @if($subscription['status'] === 'active') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                @elseif($subscription['status'] === 'trial') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                @elseif($subscription['status'] === 'past_due') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                                @elseif($subscription['status'] === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                                @endif">
                                                {{ ucfirst($subscription['status'] ?? 'N/A') }}
                                            </span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span class="capitalize">{{ $subscription['billing_cycle'] ?? 'N/A' }}</span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $subscription['starts_at'] ?? '-' }}
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $subscription['ends_at'] ?? 'Never' }}
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                                @if($subscription['auto_renew']) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                                @endif">
                                                {{ $subscription['auto_renew'] ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap text-right text-sm">
                                            <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                              <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                                                <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                                              </button>
                                              <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                                @if($subscription['company_id'])
                                                <a href="{{ route('admin.companies.show', $subscription['company_id']) }}" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View company</a>
                                                @endif
                                                <button type="button" onclick="cancelSubscription({{ $subscription['id'] }})" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Cancel</button>
                                              </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="p-4 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No active subscriptions</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No companies have active subscriptions yet.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Modals -->
@include('partials.modal.alert-modal')
@include('partials.modal.user-assign-modal')


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
        },
        
        verifyUser(userId) {
            if (confirm('Verify this user? They will be able to log in and access the system.')) {
                fetch(`/admin/users/${userId}/verify`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User verified successfully!');
                        (window.schargeRefresh || (() => location.reload()))();
                    } else {
                        alert(data.message || 'Failed to verify user');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while verifying the user');
                });
            }
        },
        
        suspendUser(userId, userName) {
            if (confirm(`Are you sure you want to suspend "${userName}"? They will not be able to log in.`)) {
                fetch(`/admin/users/${userId}/suspend`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'User suspended successfully!');
                        (window.schargeRefresh || (() => location.reload()))();
                    } else {
                        alert(data.message || 'Failed to suspend user');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while suspending the user');
                });
            }
        },
        
        openAssignModal(userId, userName, userEmail, userRole) {
            if (window.userAssignModal) {
                window.userAssignModal.openModal(userId, userName, userEmail, userRole);
            } else {
                console.error('User assign modal not found');
                alert('Modal not loaded. Please refresh the page and try again.');
            }
        }
    };
}

function cancelSubscription(subscriptionId) {
    if (confirm('Are you sure you want to cancel this subscription?')) {
        fetch(`/admin/subscriptions/subscription/${subscriptionId}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Subscription cancelled successfully!');
                (window.schargeRefresh || (() => location.reload()))();
            } else {
                alert(data.message || 'Failed to cancel subscription');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cancelling the subscription');
        });
    }
}
</script>
@endsection