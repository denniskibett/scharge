@extends('layouts.app')

@section('title', 'Account Manager Details')

@section('content')
<div class="flex flex-col gap-5 p-6">
    <!-- Header Card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Account Manager Details
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium">{{ $accountManager->user->name ?? 'N/A' }}</span>
                    <span class="mx-2">•</span>
                    {{ $accountManager->title ?? 'Account Manager' }}
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('admin.account-managers.edit', $accountManager->id) }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </a>
                <a href="{{ route('admin.account-managers.index') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Companies -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Companies</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90 mt-1">{{ $stats->total_companies ?? 0 }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/15">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Estates -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Estates</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90 mt-1">{{ $stats->total_estates ?? 0 }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 dark:bg-green-500/15">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Tenants -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90 mt-1">{{ $stats->total_tenants ?? 0 }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-500/15">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90 mt-1">
                        KES {{ number_format($stats->total_revenue ?? 0, 0) }}
                    </h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-50 dark:bg-yellow-500/15">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Companies Managed -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">
                Companies Managed
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ isset($stats->companies) ? count($stats->companies) : 0 }} companies)
                </span>
            </h4>
        </div>
        <div class="pt-4">
            @if(isset($stats->companies) && count($stats->companies) > 0)
                <div class="grid grid-cols-1 gap-4">
                    @foreach($stats->companies as $company)
                        <div class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div class="flex-1">
                                    <h5 class="text-base font-semibold text-gray-800 dark:text-white/90">
                                        {{ $company->name }}
                                    </h5>
                                    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Estates</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $company->total_estates ?? 0 }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Units</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $company->total_units ?? 0 }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Tenants</p>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $company->total_tenants ?? 0 }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Revenue</p>
                                            <p class="text-sm font-medium text-green-600 dark:text-green-400">
                                                KES {{ number_format($company->total_revenue ?? 0, 0) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('estates.index', ['company_id' => $company->id]) }}" 
                                       class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                        View Estates
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400">No companies assigned to this account manager</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Personal Information -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Personal Information</h4>
        </div>
        <div class="pt-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dl class="space-y-2">
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Name</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $accountManager->user->name ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $accountManager->user->email ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Title</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white">{{ $accountManager->title ?? 'Account Manager' }}</dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <dl class="space-y-2">
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                            <dd>
                                @if($accountManager->is_active)
                                    <span class="inline-block rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Active</span>
                                @else
                                    <span class="inline-block rounded-full bg-error-50 px-2 py-1 text-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Inactive</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Primary</dt>
                            <dd>
                                @if($accountManager->is_primary)
                                    <span class="inline-block rounded-full bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Primary</span>
                                @else
                                    <span class="inline-block rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-500 dark:bg-gray-500/15 dark:text-gray-400">Secondary</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="text-sm text-gray-800 dark:text-white">{{ $accountManager->created_at ? $accountManager->created_at->format('M d, Y') : 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.account-managers.edit', $accountManager->id) }}" 
               class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Account Manager
            </a>
            <form action="{{ route('admin.account-managers.destroy', $accountManager->id) }}" 
                  method="POST" 
                  class="inline"
                  onsubmit="return confirm('Are you sure you want to delete this account manager? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-red-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Account Manager
                </button>
            </form>
        </div>
    </div>
</div>
@endsection