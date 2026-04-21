@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.tenants-edit-modal')
@include('partials.modal.tenants-delete-modal')
@include('partials.modal.invoice-create-modal')
@include('partials.modal.payment-create-modal')
@include('partials.modal.success-modal')


<div x-data="tenantShow" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header with Title and Actions -->
    <div class="flex flex-col justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center dark:border-gray-800">
      <!-- Left side - Title and ID/Status -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Tenant Details
        </h3>
        <div class="flex items-center gap-2 mt-1">
          <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            ID: #{{ $tenant->id }}
          </span>
          @if($activeTenancy)
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
            <span class="w-1.5 h-1.5 rounded-full bg-green-600 mr-1"></span>
            Active
          </span>
          @else
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">
            <span class="w-1.5 h-1.5 rounded-full bg-orange-600 mr-1"></span>
            Without Unit
          </span>
          @endif
        </div>
      </div>

      <!-- Right side - Action Buttons -->
      <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('tenants.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back
        </a>
        <button 
          @click="openEditModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-2 text-sm font-medium text-yellow-700 shadow-theme-xs transition hover:bg-yellow-100 dark:border-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400 dark:hover:bg-yellow-900/30">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
          </svg>
          Edit
        </button>
        <button 
          @click="openDeleteModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 shadow-theme-xs transition hover:bg-red-100 dark:border-red-700 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
          </svg>
          Delete
        </button>
      </div>
    </div>

    <!-- Tenant Details -->
    <div class="p-6">
      <!-- Tenant Info Header with Avatar - SEPARATE CARD -->
      <div class="bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-xl p-6 mb-6 border border-gray-200 dark:border-gray-700">
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 h-20 w-20 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center border-2 border-purple-300 dark:border-purple-700">
            <span class="text-purple-600 dark:text-purple-400 font-bold text-3xl">
              {{ strtoupper(substr($tenant->user->name ?? 'T', 0, 1)) }}
            </span>
          </div>
          <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $tenant->user->name ?? 'N/A' }}</h2>
            <div class="flex flex-wrap gap-4 mt-2 text-sm text-gray-600 dark:text-gray-400">
              <div class="flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>{{ $tenant->user->email ?? 'N/A' }}</span>
              </div>
              <div class="flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <span>{{ $tenant->user->phone ?? 'N/A' }}</span>
              </div>
            </div>
          </div>
          @if($activeTenancy)
          <div class="text-right bg-white dark:bg-gray-800 rounded-lg px-4 py-2 border border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400">Monthly Rent</p>
            <p class="text-xl font-bold text-green-600 dark:text-green-400">
              {{ SystemHelper::currencySymbol() }} {{ number_format($activeTenancy->unit->rent_amount ?? 0, 2) }}
            </p>
          </div>
          @endif
        </div>
      </div>

      <!-- Stats Cards - SEPARATE ROW -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Tenancies Card -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Tenancies</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $totalTenancies }}</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-blue-200 dark:bg-blue-700 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
          </div>
          <div class="mt-2 text-xs text-blue-600 dark:text-blue-400">
            Lifetime tenancies
          </div>
        </div>

        <!-- Active Tenancies Card -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-green-600 dark:text-green-400">Active Tenancies</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $activeTenancies }}</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-green-200 dark:bg-green-700 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 dark:text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
          <div class="mt-2 text-xs text-green-600 dark:text-green-400">
            Currently active
          </div>
        </div>

        <!-- Past Tenancies Card -->
        <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Past Tenancies</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $pastTenancies }}</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
          <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
            Completed tenancies
          </div>
        </div>

        <!-- Estates Lived Card -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl p-4 border border-purple-200 dark:border-purple-800">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Estates Lived</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $estates->count() }}</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-purple-200 dark:bg-purple-700 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 dark:text-purple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
          </div>
          <div class="mt-2 text-xs text-purple-600 dark:text-purple-400">
            Different estates
          </div>
        </div>
      </div>

      <!-- Financial Summary Cards - SEPARATE ROW (if active tenancy) -->
      @if($activeTenancy)
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Current Unit Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Current Unit</p>
              <a href="{{ route('units.show', $activeTenancy->unit_id) }}" class="text-base font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 truncate block">
                {{ $activeTenancy->unit->unit_number ?? 'N/A' }}
              </a>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $activeTenancy->unit->estate->name ?? '' }}</p>
            </div>
          </div>
        </div>
        
        <!-- Move-in Date Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Move-in Date</p>
              <p class="text-base font-bold text-gray-900 dark:text-white truncate">{{ \Carbon\Carbon::parse($activeTenancy->move_in_date)->format('M d, Y') }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ \Carbon\Carbon::parse($activeTenancy->move_in_date)->diffForHumans(now(), true) }}</p>
            </div>
          </div>
        </div>
        
        <!-- Total Paid Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Total Paid</p>
              <p class="text-base font-bold text-green-600 dark:text-green-400 truncate">{{ SystemHelper::currencySymbol() }} {{ number_format($totalPaid, 2) }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Lifetime payments</p>
            </div>
          </div>
        </div>
        
        <!-- Current Balance Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full {{ $totalBalance > 0 ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900' }} flex items-center justify-center flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $totalBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">Current Balance</p>
              <p class="text-base font-bold {{ $totalBalance > 0 ? 'text-red-600' : 'text-green-600' }} truncate">
                {{ SystemHelper::currencySymbol() }} {{ number_format($totalBalance, 2) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Pending payments</p>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Tabs Section - Pushed to Left (SINGLE x-data) -->
      <div x-data="{ activeTab: 'invoices' }">
        <!-- Tabs Header -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
          <nav class="flex -mb-px space-x-8">
            <button 
              @click="activeTab = 'invoices'" 
              :class="activeTab === 'invoices' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
              class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"
            >
              Invoices
            </button>
            <button 
              @click="activeTab = 'payments'" 
              :class="activeTab === 'payments' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
              class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"
            >
              Payments
            </button>
            <button 
              @click="activeTab = 'tenancies'" 
              :class="activeTab === 'tenancies' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
              class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"
            >
              Tenancy History
            </button>
            <button 
              @click="activeTab = 'details'" 
              :class="activeTab === 'details' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
              class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"
            >
              Tenant Details
            </button>
          </nav>
        </div>

        <!-- Tabs Content - Using Reusable Partials -->
        <div class="mt-6">
          <!-- Invoices Tab -->
          <div x-show="activeTab === 'invoices'" x-cloak>
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Invoice History
              </h4>
              @if($activeTenancy)
              <button 
                @click="window.invoiceCreateModal?.openModal({{ $activeTenancy->id }})"
                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Invoice
              </button>
              @endif
            </div>
            @include('partials.table.table-invoices', [
              'invoices' => $invoices, 
              'showActions' => true, 
              'showTenant' => false, 
              'showUnit' => true,
              'tenancy' => $tenant->tenancies->first() ?? null,
              'duration' => null,
              'unitTenancyHistory' => collect()
            ])
          </div>

          <!-- Payments Tab -->
          <div x-show="activeTab === 'payments'" x-cloak>
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Payment History
              </h4>
              @if($activeTenancy)
              <button 
                @click="window.paymentCreateModal?.openModal({{ $activeTenancy->id }})"
                class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white shadow-theme-xs transition hover:bg-purple-700"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Payment
              </button>
              @endif
            </div>
            @include('partials.table.table-payments', [
              'payments' => $payments, 
              'showInvoice' => true, 
              'showTenant' => false
            ])
          </div>

          <!-- Tenancy History Tab -->
          <div x-show="activeTab === 'tenancies'" x-cloak>
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              Tenancy History
            </h4>
            @include('partials.table.table-tenancy', [
              'tenancies' => $tenant->tenancies,
              'showTenant' => false,
              'showActions' => false,
              'showSpent' => true,
              'payments' => $payments,
              'invoices' => $invoices
            ])
          </div>

          <!-- Tenant Details Tab -->
          <div x-show="activeTab === 'details'" x-cloak>
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Personal Information
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
              <!-- Personal Information -->
              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->user->name ?? 'N/A' }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Email Address</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->user->email ?? 'N/A' }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Phone Number</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->user->phone ?? 'N/A' }}</p>
                </div>
                @if($tenant->user->phone2)
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Secondary Phone</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->user->phone2 }}</p>
                </div>
                @endif
              </div>

              <!-- Additional Information -->
              <div class="space-y-4">
                @if($tenant->id_number)
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">ID Number</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->id_number }}</p>
                </div>
                @endif
                
                @if($tenant->emergency_contact)
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Emergency Contact</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->emergency_contact }}</p>
                </div>
                @endif
                
                @if($tenant->notes)
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Notes</p>
                  <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                    {{ $tenant->notes }}
                  </p>
                </div>
                @endif

                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Member Since</p>
                  <p class="text-base font-medium text-gray-900 dark:text-white">{{ $tenant->created_at->format('F d, Y') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('tenantShow', () => ({
    init() {
      console.log('Tenant show page loaded');
    },

    getTenantData() {
      return {
        id: {{ $tenant->id }},
        name: '{{ addslashes($tenant->user->name) }}',
        email: '{{ $tenant->user->email }}',
        phone: '{{ $tenant->user->phone }}',
        phone2: '{{ $tenant->user->phone2 }}',
        id_number: '{{ $tenant->id_number }}',
        emergency_contact: '{{ addslashes($tenant->emergency_contact) }}',
        notes: '{{ addslashes($tenant->notes) }}',
        user_id: {{ $tenant->user_id }}
      };
    },

    openEditModal() {
      if (window.tenantEditModal) {
        window.tenantEditModal.openModal(this.getTenantData());
      }
    },

    openDeleteModal() {
      if (window.tenantDeleteModal) {
        window.tenantDeleteModal.openModal(this.getTenantData());
      }
    }
  }));
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection