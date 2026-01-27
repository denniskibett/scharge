

@extends('layouts.app')

@section('content')
    <!-- Breadcrumb Start -->
    <div x-data="{ pageName: `Tenants Management`}">
        @include('partials.breadcrumb')
    </div>
    <!-- Breadcrumb End -->
<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Tenants Management</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all tenants and their information</p>
            </div>
            <div class="flex items-center gap-3">
                <button 
                    @click="openCreateModal()"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-blue-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Tenant
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-white/90 mt-1">{{ $totalTenants }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                        <span class="text-blue-600 font-bold">👤</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active Tenants</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-white/90 mt-1">{{ $activeTenants }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                        <span class="text-green-600 font-bold">🏠</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Without Unit</p>
                        <p class="text-2xl font-bold text-gray-800 dark:text-white/90 mt-1">{{ $vacantTenants }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-orange-100 flex items-center justify-center">
                        <span class="text-orange-600 font-bold">🚫</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.table.table-tenants', ['tenants' => $tenantsData])
</div>


@endsection