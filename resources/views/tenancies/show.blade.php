@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.tenancies-edit-modal')
@include('partials.modal.tenancies-delete-modal')
@include('partials.modal.success-modal')
@include('partials.modal.invoice-create-modal')
@include('partials.modal.payment-create-modal', ['invoices' => $invoices ?? []])

<div x-data="tenancyShow" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header with Title and Action Buttons -->
    <div class="flex flex-col justify-between gap-4 border-b border-gray-200 px-6 py-5 sm:flex-row sm:items-center dark:border-gray-800">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
          Tenancy Details
        </h1>
        <div class="flex items-center gap-2 mt-2">
          <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            ID: #{{ $tenancy->id }}
          </span>
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
            @if($tenancy->status == 'active') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
            @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400 @endif">
            {{ ucfirst($tenancy->status) }}
          </span>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('tenancies.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Tenancies
        </a>
        
        @if($tenancy->status === 'active')
        <button 
          @click="window.invoiceCreateModal?.openModal({{ $tenancy->id }}, { rent_amount: {{ $tenancy->unit->rent_amount ?? 0 }} })"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Generate Invoice
        </button>
        
        <button 
          @click="window.paymentCreateModal?.openModal({{ $tenancy->id }})"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          Record Payment
        </button>
        @endif
        
        <button 
          @click="openEditModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
          </svg>
          Edit Tenancy
        </button>
        
        <button 
          @click="openDeleteModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 shadow-theme-xs transition hover:bg-red-100 dark:border-red-700 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
          </svg>
          Delete Tenancy
        </button>
      </div>
    </div>

    <!-- Tenancy Information Card -->
    <div class="px-6 pt-6">
      <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
        <div class="flex items-start gap-5">
          <!-- Tenant Avatar -->
          <div class="flex-shrink-0 h-20 w-20 rounded-full bg-gradient-to-r from-purple-500 to-pink-600 flex items-center justify-center">
            <span class="text-white font-bold text-2xl">{{ substr($tenancy->tenant->user->name ?? 'T', 0, 1) }}</span>
          </div>
          <div class="flex-1">
            <a href="{{ route('tenants.show', $tenancy->tenant_id) }}" class="text-2xl font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline">
              {{ $tenancy->tenant->user->name ?? 'Unknown Tenant' }}
            </a>
            <div class="flex flex-wrap items-center gap-3 mt-2">
              <span class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                {{ $tenancy->tenant->user->phone ?? 'No phone' }}
              </span>
              @if($tenancy->tenant->user->email)
              <span class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                {{ $tenancy->tenant->user->email }}
              </span>
              @endif
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-500 dark:text-gray-400">Monthly Rent</p>
            <p class="text-xl font-bold text-green-600 dark:text-green-400">
              KES {{ number_format($tenancy->unit->rent_amount ?? 0, 2) }}
            </p>
          </div>
        </div>

        <!-- Three Column Layout: Unit Info, Utility Charges, Move Dates -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
          <!-- Unit Information -->
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              Unit Information
            </h4>
            <div class="space-y-3">
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Unit Number</p>
                <a href="{{ route('units.show', $tenancy->unit) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">
                  {{ $tenancy->unit->unit_number ?? 'N/A' }}
                </a>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Unit Type</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenancy->unit->unit_type ?? 'N/A' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Estate</p>
                <a href="{{ route('estates.show', $tenancy->unit->estate) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">
                  {{ $tenancy->unit->estate->name ?? 'N/A' }}
                </a>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Location</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenancy->unit->estate->location ?? 'N/A' }}</p>
              </div>
            </div>
          </div>

          <!-- Utility Charges -->
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              Utility Charges
            </h4>
            <div class="space-y-3">
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Water Charge</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($tenancy->unit->water_charge ?? 0, 2) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Service Charge</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($tenancy->unit->service_charge ?? 0, 2) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Garbage Charge</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($tenancy->unit->garbage_charge ?? 0, 2) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Security Charge</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($tenancy->unit->security_charge ?? 0, 2) }}</p>
              </div>
            </div>
          </div>

          <!-- Move Dates -->
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Tenancy Period
            </h4>
            <div class="space-y-3">
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Move-in Date</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date)->format('M d, Y') : 'N/A' }}</p>
              </div>
              @if($tenancy->move_out_date)
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Move-out Date</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ \Carbon\Carbon::parse($tenancy->move_out_date)->format('M d, Y') }}</p>
              </div>
              @else
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Duration</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $duration }}</p>
              </div>
              @endif
            </div>
          </div>
        </div>

        <!-- Total Monthly Payment Section -->
        @php
          $totalMonthlyPayment = ($tenancy->unit->rent_amount ?? 0) + 
                                 ($tenancy->unit->water_charge ?? 0) + 
                                 ($tenancy->unit->service_charge ?? 0) + 
                                 ($tenancy->unit->garbage_charge ?? 0) + 
                                 ($tenancy->unit->security_charge ?? 0);
        @endphp
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
          <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-1">Total Monthly Payment</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Rent + All Utility Charges</p>
              </div>
              <div class="text-right">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                  KES {{ number_format($totalMonthlyPayment, 2) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Financial Summary Row (compact) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Invoiced</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">KES {{ number_format($totalInvoiced ?? 0, 2) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Paid</p>
            <p class="text-sm font-semibold text-green-600">KES {{ number_format($totalPaid ?? 0, 2) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Outstanding Balance</p>
            <p class="text-sm font-semibold {{ ($balance ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
              KES {{ number_format($balance ?? 0, 2) }}
            </p>
          </div>
        </div>

        <!-- Notes Section (if any) -->
        @if($tenancy->notes)
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Notes</p>
          <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">{{ $tenancy->notes }}</p>
        </div>
        @endif
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="px-6 pt-6">
      <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="flex gap-6 -mb-px">
          <button
            @click="activeTab = 'tenancy-details'"
            :class="activeTab === 'tenancy-details' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Tenancy Details
          </button>
          <button
            @click="activeTab = 'invoices'"
            :class="activeTab === 'invoices' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Invoices
            <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
              {{ $tenancy->invoices->count() }}
            </span>
          </button>
          <button
            @click="activeTab = 'payments'"
            :class="activeTab === 'payments' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Payments
            <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
              {{ $tenancy->payments->count() }}
            </span>
          </button>
          <button
            @click="activeTab = 'unit-history'"
            :class="activeTab === 'unit-history' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Unit Tenancy History
            <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
              {{ $unitTenancyHistory->count() }}
            </span>
          </button>
        </nav>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="p-6">
      <!-- Tenancy Details Tab -->
      <div x-show="activeTab === 'tenancy-details'" x-cloak>
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4">Tenancy Information</h4>
              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Tenancy ID</p>
                  <p class="text-base font-medium text-gray-800 dark:text-white/90">#{{ $tenancy->id }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
                  <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                    @if($tenancy->status == 'active') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                    @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400 @endif">
                    {{ ucfirst($tenancy->status) }}
                  </span>
                </div>
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Duration</p>
                  <p class="text-base font-medium text-gray-800 dark:text-white/90">
                    @php
                      $moveIn = \Carbon\Carbon::parse($tenancy->move_in_date);
                      $moveOut = $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date) : null;
                    @endphp
                    @if($moveOut)
                      {{ $moveIn->format('M d, Y') }} - {{ $moveOut->format('M d, Y') }}
                      <span class="text-sm text-gray-500">({{ $moveIn->diffForHumans($moveOut, true) }})</span>
                    @else
                      {{ $moveIn->format('M d, Y') }} - Present
                      <span class="text-sm text-gray-500">({{ $moveIn->diffForHumans(now(), true) }})</span>
                    @endif
                  </p>
                </div>
                @if($tenancy->notes)
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Notes</p>
                  <p class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-2 rounded">{{ $tenancy->notes }}</p>
                </div>
                @endif
              </div>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4">Financial Summary</h4>
              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Monthly Rent</p>
                  <p class="text-base font-bold text-green-600 dark:text-green-400">KES {{ number_format($tenancy->unit->rent_amount ?? 0, 2) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Monthly Payment</p>
                  <p class="text-base font-bold text-blue-600 dark:text-blue-400">KES {{ number_format($totalMonthlyPayment, 2) }}</p>
                  <p class="text-xs text-gray-500">(Rent + All Utilities)</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Expected Rent</p>
                  @php
                    $monthsOccupied = $moveIn->diffInMonths($moveOut ?? now());
                    $totalExpected = $totalMonthlyPayment * ($monthsOccupied + 1);
                  @endphp
                  <p class="text-base font-medium text-gray-800 dark:text-white/90">KES {{ number_format($totalExpected, 2) }}</p>
                  <p class="text-xs text-gray-500">Over {{ $monthsOccupied + 1 }} month(s)</p>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4">Timestamps</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Created At</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenancy->created_at ? \Carbon\Carbon::parse($tenancy->created_at)->format('M d, Y H:i') : '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Last Updated</p>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenancy->updated_at ? \Carbon\Carbon::parse($tenancy->updated_at)->format('M d, Y H:i') : '-' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Invoices Tab -->
      <div x-show="activeTab === 'invoices'" x-cloak>
        @if($tenancy->invoices->count() > 0)
          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-800">
                 <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice #</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Month</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Paid</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                 </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($tenancy->invoices as $invoice)
                @php
                  $paidOnInvoice = $invoice->payments->sum('amount');
                  $balance = $invoice->total_amount - $paidOnInvoice;
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                      #{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                    </a>
                   </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {{ \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') }}
                   </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                    KES {{ number_format($invoice->total_amount, 2) }}
                   </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">
                    KES {{ number_format($paidOnInvoice, 2) }}
                   </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                    KES {{ number_format($balance, 2) }}
                   </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                      {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                         ($invoice->status === 'partial' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                         'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                      {{ ucfirst($invoice->status) }}
                    </span>
                    </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                      View
                    </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
             </table>
          </div>
        @else
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Invoices</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No invoices have been generated for this tenancy yet.</p>
            @if($tenancy->status === 'active')
            <div class="mt-6">
              <button 
                @click="window.invoiceCreateModal?.openModal({{ $tenancy->id }}, { rent_amount: {{ $tenancy->unit->rent_amount ?? 0 }} })"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create First Invoice
              </button>
            </div>
            @endif
          </div>
        @endif
      </div>

      <!-- Payments Tab -->
      <div x-show="activeTab === 'payments'" x-cloak>
        @if($tenancy->payments->count() > 0)
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800">
                   <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                   </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                  @foreach($tenancy->payments as $payment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        #{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}
                       </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="text-sm text-blue-600 hover:text-blue-900 dark:text-blue-400">
                          INV-{{ str_pad($payment->invoice_id, 6, '0', STR_PAD_LEFT) }}
                        </a>
                       </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">
                        KES {{ number_format($payment->amount, 2) }}
                       </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                       </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ ucfirst($payment->payment_method) }}
                       </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $payment->reference_number ?? '-' }}
                       </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="{{ route('payments.show', $payment) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                          View
                        </a>
                       </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Payments</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No payment records found for this tenancy.</p>
            @if($tenancy->status === 'active')
            <div class="mt-6">
              <button 
                @click="window.paymentCreateModal?.openModal({{ $tenancy->id }})"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Record First Payment
              </button>
            </div>
            @endif
          </div>
        @endif
      </div>

      <!-- Unit Tenancy History Tab -->
      <div x-show="activeTab === 'unit-history'" x-cloak>
        @if($unitTenancyHistory->count() > 0)
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800">
                   <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-in Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-out Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                   </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                  @foreach($unitTenancyHistory as $historyTenancy)
                    @php
                      $moveIn = \Carbon\Carbon::parse($historyTenancy->move_in_date);
                      $moveOut = $historyTenancy->move_out_date ? \Carbon\Carbon::parse($historyTenancy->move_out_date) : null;
                      $duration = $moveOut ? $moveIn->diffForHumans($moveOut, true) : $moveIn->diffForHumans(now(), true);
                      $tenantName = optional($historyTenancy->tenant->user)->name ?? 'Unknown Tenant';
                      $tenantInitial = $tenantName[0] ?? 'T';
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $historyTenancy->id === $tenancy->id ? 'bg-blue-50 dark:bg-blue-900/10' : '' }}">
                      <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                          <div class="flex-shrink-0 h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                            <span class="text-purple-600 font-medium text-sm">{{ $tenantInitial }}</span>
                          </div>
                          <div>
                            <a href="{{ route('tenants.show', $historyTenancy->tenant_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400 hover:underline">
                              {{ $tenantName }}
                            </a>
                            @if($historyTenancy->id === $tenancy->id)
                              <span class="ml-2 text-xs text-blue-500">(Current)</span>
                            @endif
                          </div>
                        </div>
                       </td>
                      <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $moveIn->format('M d, Y') }}</td>
                      <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $moveOut ? $moveOut->format('M d, Y') : '-' }}</td>
                      <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $duration }}</td>
                      <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                          {{ $historyTenancy->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                          {{ ucfirst($historyTenancy->status) }}
                        </span>
                       </td>
                      <td class="px-6 py-4 text-right">
                        <a href="{{ route('tenancies.show', $historyTenancy) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                          View
                        </a>
                       </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Tenancy History</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No other tenancy records found for this unit.</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('tenancyShow', () => ({
    activeTab: 'tenancy-details',
    
    init() {
      console.log('Tenancy show page loaded');
    },

    getTenancyData() {
      return {
        id: {{ $tenancy->id }},
        tenant_name: '{{ addslashes($tenancy->tenant->user->name ?? 'Unknown') }}',
        tenant_phone: '{{ $tenancy->tenant->user->phone ?? '' }}',
        tenant_id: {{ $tenancy->tenant_id }},
        unit_id: {{ $tenancy->unit_id }},
        unit_number: '{{ addslashes($tenancy->unit->unit_number ?? '') }}',
        estate_name: '{{ addslashes($tenancy->unit->estate->name ?? '') }}',
        move_in_date: '{{ $tenancy->move_in_date }}',
        move_out_date: '{{ $tenancy->move_out_date }}',
        status: '{{ $tenancy->status }}'
      };
    },

    openEditModal() {
      if (window.tenancyEditModal) {
        window.tenancyEditModal.openModal(this.getTenancyData());
      }
    },

    openDeleteModal() {
      if (window.tenancyDeleteModal) {
        window.tenancyDeleteModal.openModal(this.getTenancyData());
      }
    }
  }));
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection