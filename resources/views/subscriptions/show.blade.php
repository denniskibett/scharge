{{-- resources/views/subscriptions/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Subscription Details - ' . ($company->name ?? 'Company'))

@section('content')
<div x-data="subscriptionShow()" x-init="init()">
    <div class="container-fluid px-4 py-4">
        
        <!-- Breadcrumb -->
        <nav class="flex mb-4 text-sm" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <a href="{{ route('admin.subscriptions.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white ml-1 md:ml-2">Subscriptions</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-gray-500 dark:text-gray-400 ml-1 md:ml-2">{{ $company->name ?? 'Company Details' }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Company Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-2xl p-6 shadow-lg mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                        <span class="text-2xl font-bold text-white">{{ substr($company->name ?? 'C', 0, 2) }}</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">{{ $company->name ?? 'Company' }}</h1>
                        <p class="text-purple-100 text-sm">{{ $company->email ?? 'No email' }} · {{ $company->phone ?? 'No phone' }}</p>
                    </div>
                </div>
                <div class="mt-4 md:mt-0 flex items-center gap-3">
                    @if($currentSubscription)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm">
                            <span class="w-2 h-2 rounded-full bg-green-400 mr-2"></span>
                            {{ ucfirst($currentSubscription->status) }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm">
                            <span class="w-2 h-2 rounded-full bg-yellow-400 mr-2"></span>
                            No Active Plan
                        </span>
                    @endif
                    <a href="{{ route('admin.companies.show', $company) }}" class="text-white/80 hover:text-white text-sm transition">
                        View Company →
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Current Plan</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ $currentSubscription?->plan?->name ?? 'No Plan' }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/15">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Monthly Price</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            KES {{ number_format($currentPrice ?? 0, 0) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    @if($pricingType === 'per_unit')
                        {{ $pricePerUnit ?? 0 }} × {{ $unitCount ?? 0 }} units
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Active Units</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ number_format($unitCount ?? 0) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    {{ $unitCount ?? 0 }} total units
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Billing Cycle</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ ucfirst($currentSubscription?->billing_cycle ?? 'N/A') }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/15">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    @if($currentSubscription?->auto_renew)
                        <span class="text-green-600">Auto-renew</span>
                    @else
                        <span class="text-red-600">Manual renew</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Subscription Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Current Subscription Card -->
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Current Subscription</h3>
                    </div>
                    <div class="p-6">
                        @if($currentSubscription)
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Plan</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentSubscription->plan->name ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                                        <span class="inline-flex px-2 py-0.5 text-xs rounded-full font-medium
                                            @if($currentSubscription->status === 'active') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                            @elseif($currentSubscription->status === 'trial') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                            @elseif($currentSubscription->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                            @elseif($currentSubscription->status === 'past_due') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400
                                            @endif">
                                            {{ ucfirst($currentSubscription->status) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Billing Cycle</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ ucfirst($currentSubscription->billing_cycle) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Auto Renew</p>
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ $currentSubscription->auto_renew ? 'Yes' : 'No' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Start Date</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentSubscription->starts_at ? $currentSubscription->starts_at->format('M d, Y') : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">End Date</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentSubscription->ends_at ? $currentSubscription->ends_at->format('M d, Y') : 'Never' }}</p>
                                    </div>
                                    @if($currentSubscription->trial_ends_at)
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Trial Ends</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $currentSubscription->trial_ends_at->format('M d, Y') }}</p>
                                    </div>
                                    @endif
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Unit Count</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ number_format($currentSubscription->unit_count ?? 0) }}</p>
                                    </div>
                                </div>

                                @if($currentSubscription->plan && $currentSubscription->plan->features)
                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Plan Features</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($currentSubscription->plan->features as $feature)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                {{ $feature }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Active Subscription</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This company doesn't have an active subscription.</p>
                                <div class="mt-4">
                                    <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                                        Browse Plans
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Subscription History -->
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Subscription History</h3>
                    </div>
                    <div class="p-6">
                        @if($subscriptionHistory && $subscriptionHistory->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-2">Plan</th>
                                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-2">Status</th>
                                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-2">Cycle</th>
                                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-2">Started</th>
                                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-2">Ended</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($subscriptionHistory as $subscription)
                                            <tr>
                                                <td class="py-3 text-sm text-gray-900 dark:text-white">{{ $subscription->plan->name ?? 'N/A' }}</td>
                                                <td class="py-3">
                                                    <span class="inline-flex px-2 py-0.5 text-xs rounded-full font-medium
                                                        @if($subscription->status === 'active') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                        @elseif($subscription->status === 'trial') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                                        @elseif($subscription->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                                        @elseif($subscription->status === 'past_due') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                                        @elseif($subscription->status === 'expired') bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400
                                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400
                                                        @endif">
                                                        {{ ucfirst($subscription->status) }}
                                                    </span>
                                                </td>
                                                <td class="py-3 text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($subscription->billing_cycle) }}</td>
                                                <td class="py-3 text-sm text-gray-700 dark:text-gray-300">{{ $subscription->starts_at ? $subscription->starts_at->format('M d, Y') : 'N/A' }}</td>
                                                <td class="py-3 text-sm text-gray-700 dark:text-gray-300">{{ $subscription->ends_at ? $subscription->ends_at->format('M d, Y') : 'Never' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No subscription history available.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column - Actions & Invoices -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Quick Actions</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        @if($currentSubscription)
                            <a href="#" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Renew Subscription
                            </a>
                            <a href="#" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Change Plan
                            </a>
                            <button onclick="confirmCancel()" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Cancel Subscription
                            </button>
                        @else
                            <a href="{{ route('admin.subscriptions.index') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Subscribe to Plan
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Recent Invoices -->
                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Invoices</h3>
                    </div>
                    <div class="p-6">
                        @if($invoices && $invoices->count() > 0)
                            <div class="space-y-3">
                                @foreach($invoices->take(5) as $invoice)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">#{{ $invoice->invoice_number ?? $invoice->id }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->created_at ? $invoice->created_at->format('M d, Y') : 'N/A' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">KES {{ number_format($invoice->amount ?? 0, 0) }}</p>
                                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full font-medium
                                                @if($invoice->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                @elseif($invoice->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                                @elseif($invoice->status === 'failed') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400
                                                @endif">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                                @if($invoices->count() > 5)
                                    <a href="#" class="text-sm text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300">
                                        View all invoices →
                                    </a>
                                @endif
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">No invoices available.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function subscriptionShow() {
    return {
        init() {
            console.log('Subscription show page loaded');
        }
    };
}

function confirmCancel() {
    if (confirm('Are you sure you want to cancel this subscription? This action can be undone.')) {
        // Handle cancellation
        alert('Subscription cancelled successfully!');
    }
}
</script>

@endsection