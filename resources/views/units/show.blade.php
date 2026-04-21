@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.units-edit-modal')
@include('partials.modal.units-delete-modal')
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')
@include('partials.modal.invoice-create-modal')
@include('partials.modal.units-water-reading-modal')

<div x-data="unitShow" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header with Title and Action Buttons -->
    <div class="flex flex-col justify-between gap-4 border-b border-gray-200 px-6 py-5 sm:flex-row sm:items-center dark:border-gray-800">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
          Unit Details
        </h1>
        <div class="flex items-center gap-2 mt-2">
          <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
            ID: #{{ $unit->id }}
          </span>
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
            @if($unit->status == 'occupied') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
            @else bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400 @endif">
            {{ ucfirst($unit->status) }}
          </span>
          <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
            {{ $unit->unit_type }}
          </span>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('units.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Units
        </a>
        
        @php $activeTenancy = $unit->tenancies->where('status', 'active')->first(); @endphp
        @if($activeTenancy)
        <button 
          @click="openInvoiceModal({{ $activeTenancy->id }})"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Generate Invoice
        </button>
        
        <!-- Water Meter Reading Button - Only show if water is configured -->
        @php
          $hasWaterConfig = ($unit->water_charge > 0) || ($unit->estate->water_rate > 0);
        @endphp
        @if($hasWaterConfig)
        <button 
          @click="openWaterReadingModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
          </svg>
          Record Water Reading
        </button>
        @endif
        
        <button 
          @click="openMaintenanceModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          Record Maintenance
        </button>
        @endif
        
        <button 
          @click="openEditModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
          </svg>
          Edit Unit
        </button>
        
        <button 
          @click="openDeleteModal()"
          class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 shadow-theme-xs transition hover:bg-red-100 dark:border-red-700 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
          </svg>
          Delete Unit
        </button>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="px-6 pt-4">
      <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="flex gap-6 -mb-px">
          <button
            @click="activeTab = 'unit-details'"
            :class="activeTab === 'unit-details' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Unit Details
          </button>
          <button
            @click="activeTab = 'current-tenant'"
            :class="activeTab === 'current-tenant' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Current Tenant
          </button>
          <button
            @click="activeTab = 'tenancy-history'"
            :class="activeTab === 'tenancy-history' 
              ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
            class="inline-flex items-center gap-2 py-3 px-1 text-sm font-medium border-b-2 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Tenancy History
            <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
              {{ $unit->tenancies->count() }}
            </span>
          </button>
        </nav>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="p-6">
      <!-- Unit Details Tab -->
      <div x-show="activeTab === 'unit-details'" x-cloak>
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
          <!-- Header Section -->
          <div class="flex items-start gap-5 mb-8">
            <div class="flex-shrink-0 h-24 w-24 rounded-full flex items-center justify-center bg-gradient-to-r from-blue-500 to-purple-600">
              <span class="text-white font-bold text-3xl">{{ substr($unit->estate->name ?? 'UN', 0, 2) }}</span>
            </div>
            <div class="flex-1">
              <h2 class="text-3xl font-bold text-gray-800 dark:text-white/90">{{ $unit->unit_number }}</h2>
              <p class="text-base text-gray-500 dark:text-gray-400 mt-1">{{ $unit->estate->name }}</p>
              <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">{{ $unit->estate->location ?? 'No location specified' }}</p>
              <div class="flex flex-wrap gap-4 mt-3">
                <div class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                  <span class="text-sm text-gray-600 dark:text-gray-400">{{ $unit->unit_type }}</span>
                </div>
              </div>
            </div>
            <div class="text-right">
              <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
              <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium mt-1
                @if($unit->status == 'occupied') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                @else bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400 @endif">
                {{ ucfirst($unit->status) }}
              </span>
            </div>
          </div>

          <!-- Determine which utility source to use -->
          @php
            // Check if unit has custom charges (overrides)
            $hasUnitWater = ($unit->water_charge ?? 0) > 0;
            $hasUnitService = ($unit->service_charge ?? 0) > 0;
            $hasUnitGarbage = ($unit->garbage_charge ?? 0) > 0;
            $hasUnitSecurity = ($unit->security_charge ?? 0) > 0;
            $hasUnitAny = $hasUnitWater || $hasUnitService || $hasUnitGarbage || $hasUnitSecurity;
            
            // Check if estate has default charges
            $hasEstateWater = ($unit->estate->water_rate ?? 0) > 0;
            $hasEstateService = ($unit->estate->service_charge ?? 0) > 0;
            $hasEstateGarbage = ($unit->estate->garbage_charge ?? 0) > 0;
            $hasEstateSecurity = ($unit->estate->security_charge ?? 0) > 0;
            $hasEstateAny = $hasEstateWater || $hasEstateService || $hasEstateGarbage || $hasEstateSecurity;
            
            // Determine source label
            $utilitySource = $hasUnitAny ? 'Unit-Specific Charges' : ($hasEstateAny ? 'Estate Default Charges' : 'No Utilities Configured');
            $showWaterMeter = ($hasUnitWater || $hasEstateWater);
            
            // Get effective values (unit overrides estate)
            $effectiveWater = $hasUnitWater ? $unit->water_charge : ($hasEstateWater ? $unit->estate->water_rate : 0);
            $effectiveService = $hasUnitService ? $unit->service_charge : ($hasEstateService ? $unit->estate->service_charge : 0);
            $effectiveGarbage = $hasUnitGarbage ? $unit->garbage_charge : ($hasEstateGarbage ? $unit->estate->garbage_charge : 0);
            $effectiveSecurity = $hasUnitSecurity ? $unit->security_charge : ($hasEstateSecurity ? $unit->estate->security_charge : 0);
          @endphp

          <!-- Three Column Layout -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Basic Information -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Basic Information
              </h4>
              <div class="space-y-3">
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Unit Number</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $unit->unit_number }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Unit Type</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $unit->unit_type }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Monthly Rent</p>
                  <p class="text-sm font-bold text-green-600 dark:text-green-400">KES {{ number_format($unit->rent_amount, 2) }}</p>
                </div>
              </div>
            </div>
            
            <!-- Utility Charges - Show with source indication -->
            @if($hasUnitAny || $hasEstateAny)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Utility Charges
                @if($hasUnitAny)
                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">Unit Override</span>
                @elseif($hasEstateAny)
                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Estate Default</span>
                @endif
              </h4>
              <div class="space-y-3">
                @if($effectiveWater > 0)
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Water Charge</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($effectiveWater, 2) }} / unit</p>
                  @if($hasEstateWater && !$hasUnitWater)
                  <p class="text-xs text-gray-400 mt-0.5">Inherited from estate</p>
                  @endif
                </div>
                @endif
                @if($effectiveService > 0)
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Service Charge</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($effectiveService, 2) }}</p>
                  @if($hasEstateService && !$hasUnitService)
                  <p class="text-xs text-gray-400 mt-0.5">Inherited from estate</p>
                  @endif
                </div>
                @endif
                @if($effectiveGarbage > 0)
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Garbage Charge</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($effectiveGarbage, 2) }}</p>
                  @if($hasEstateGarbage && !$hasUnitGarbage)
                  <p class="text-xs text-gray-400 mt-0.5">Inherited from estate</p>
                  @endif
                </div>
                @endif
                @if($effectiveSecurity > 0)
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Security Charge</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($effectiveSecurity, 2) }}</p>
                  @if($hasEstateSecurity && !$hasUnitSecurity)
                  <p class="text-xs text-gray-400 mt-0.5">Inherited from estate</p>
                  @endif
                </div>
                @endif
              </div>
            </div>
            @endif
            
            <!-- Estate Information -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Estate Information
              </h4>
              <div class="space-y-3">
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Estate Name</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $unit->estate->name }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Location</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $unit->estate->location ?? 'N/A' }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Units in Estate</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $unit->estate->units_count ?? $unit->estate->units->count() }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Monthly Payment Section -->
          <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6">
              <div class="flex items-center justify-between">
                <div>
                  <h4 class="text-base font-semibold text-gray-800 dark:text-white/90 mb-1">Total Monthly Payment</h4>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Rent + All Utility Charges</p>
                </div>
                <div class="text-right">
                  <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                    KES {{ number_format(($unit->rent_amount + $effectiveWater + $effectiveService + $effectiveGarbage + $effectiveSecurity), 2) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Water Meter Information Section - Only show if water is configured -->
          @if($showWaterMeter && (($unit->previous_water_reading ?? 0) > 0 || ($unit->current_water_reading ?? 0) > 0))
          <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                Water Meter Information
                @if($hasEstateWater && !$hasUnitWater)
                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full ml-2">Rate: KES {{ number_format($effectiveWater, 2) }}/unit</span>
                @elseif($hasUnitWater)
                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full ml-2">Custom Rate: KES {{ number_format($effectiveWater, 2) }}/unit</span>
                @endif
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Previous Reading</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ number_format($unit->previous_water_reading ?? 0, 2) }} units</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Current Reading</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ number_format($unit->current_water_reading ?? 0, 2) }} units</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Last Reading Date</p>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $unit->last_reading_date ? \Carbon\Carbon::parse($unit->last_reading_date)->format('M d, Y') : 'No reading taken' }}</p>
                </div>
              </div>
              @php
                $consumption = ($unit->current_water_reading ?? 0) - ($unit->previous_water_reading ?? 0);
                $estimatedCharge = max(0, $consumption) * $effectiveWater;
              @endphp
              @if($consumption > 0)
              <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pending Consumption</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ number_format($consumption, 2) }} units</p>
                  </div>
                  <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Estimated Water Charge</p>
                    <p class="text-sm font-bold text-blue-600 dark:text-blue-400">KES {{ number_format($estimatedCharge, 2) }}</p>
                  </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">This amount will be added to the next invoice when generated</p>
              </div>
              @endif
            </div>
          </div>
          @endif

          <!-- Timestamps -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Created At</p>
              <p class="text-xs font-medium text-gray-800 dark:text-white/90">{{ $unit->created_at ? \Carbon\Carbon::parse($unit->created_at)->format('M d, Y H:i') : '-' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Last Updated</p>
              <p class="text-xs font-medium text-gray-800 dark:text-white/90">{{ $unit->updated_at ? \Carbon\Carbon::parse($unit->updated_at)->format('M d, Y H:i') : '-' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Tenant Tab (unchanged) -->
      <div x-show="activeTab === 'current-tenant'" x-cloak>
        @php
          $activeTenancy = $unit->tenancies->where('status', 'active')->first();
        @endphp
        
        @if($activeTenancy && $activeTenancy->tenant && $activeTenancy->tenant->user)
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-5">
                <div class="flex-shrink-0 h-20 w-20 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                  <span class="text-white font-bold text-2xl">{{ substr($activeTenancy->tenant->user->name ?? 'T', 0, 2) }}</span>
                </div>
                <div>
                  <a href="{{ route('tenants.show', $activeTenancy->tenant_id) }}" class="text-xl font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline">
                    {{ $activeTenancy->tenant->user->name ?? 'Unknown Tenant' }}
                  </a>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tenant since {{ \Carbon\Carbon::parse($activeTenancy->move_in_date)->format('M d, Y') }}</p>
                  <div class="flex items-center gap-3 mt-2">
                    <span class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                      </svg>
                      {{ $activeTenancy->tenant->user->phone ?? 'No phone' }}
                    </span>
                    @if($activeTenancy->tenant->user->email)
                    <span class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                      {{ $activeTenancy->tenant->user->email }}
                    </span>
                    @endif
                  </div>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm text-gray-500 dark:text-gray-400">Current Balance</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">KES 0.00</p>
                <a href="{{ route('tenancies.show', $activeTenancy) }}" class="inline-flex items-center gap-2 mt-3 text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                  View Tenancy Details
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </a>
              </div>
            </div>
          </div>
        @else
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Active Tenant</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This unit is currently vacant. Add a tenant to get started.</p>
            <div class="mt-6">
              <a href="{{ route('tenancies.create', ['unit_id' => $unit->id]) }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Tenant
              </a>
            </div>
          </div>
        @endif
      </div>

      <!-- Tenancy History Tab (unchanged) -->
      <div x-show="activeTab === 'tenancy-history'" x-cloak>
        @php
          $tenanciesData = $unit->tenancies->map(function($tenancy) {
            return [
              'id' => $tenancy->id,
              'tenant_name' => optional($tenancy->tenant->user)->name ?? optional($tenancy->tenant)->name ?? 'Unknown Tenant',
              'tenant_phone' => optional($tenancy->tenant->user)->phone ?? optional($tenancy->tenant)->phone ?? '',
              'unit_number' => $tenancy->unit->unit_number ?? '',
              'unit_type' => $tenancy->unit->unit_type ?? '',
              'estate_name' => $tenancy->unit->estate->name ?? '',
              'estate_id' => $tenancy->unit->estate_id ?? null,
              'move_in_date' => $tenancy->move_in_date,
              'move_out_date' => $tenancy->move_out_date,
              'status' => $tenancy->status,
              'duration' => $tenancy->move_out_date 
                ? \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(\Carbon\Carbon::parse($tenancy->move_out_date), true)
                : \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(now(), true)
            ];
          })->toArray();
        @endphp
        
        @if($unit->tenancies->count() > 0)
          @include('partials.table.table-tenancy', [
            'tenanciesData' => $tenanciesData,
            'showTenantLink' => true,
            'showUnitLink' => false,
            'showActions' => true
          ])
        @else
          <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Tenancy History</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No tenancy records found for this unit.</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('unitShow', () => ({
    activeTab: 'unit-details',
    
    init() {
      console.log('Unit show page loaded');
    },

    getUnitData() {
      return {
        id: {{ $unit->id }},
        unit_number: @json($unit->unit_number),
        unit_type: @json($unit->unit_type),
        rent_amount: {{ $unit->rent_amount }},
        water_charge: {{ $unit->water_charge ?? 0 }},
        service_charge: {{ $unit->service_charge ?? 0 }},
        garbage_charge: {{ $unit->garbage_charge ?? 0 }},
        security_charge: {{ $unit->security_charge ?? 0 }},
        status: @json($unit->status),
        estate_name: @json($unit->estate->name),
        estate_id: {{ $unit->estate_id }},
        current_water_reading: {{ $unit->current_water_reading ?? 0 }},
        previous_water_reading: {{ $unit->previous_water_reading ?? 0 }},
        last_reading_date: @json($unit->last_reading_date),
        active_tenancy: @json($activeTenancy ? ['tenant' => ['name' => $activeTenancy->tenant->user->name ?? null]] : null)
      };
    },

    openEditModal() {
      if (window.unitEditModal) {
        window.unitEditModal.openModal(this.getUnitData());
      }
    },

    openDeleteModal() {
      if (window.unitDeleteModal) {
        window.unitDeleteModal.openModal(this.getUnitData());
      }
    },
    
    openWaterReadingModal() {
      if (window.unitWaterReadingModal) {
        const unitData = this.getUnitData();
        const waterRate = {{ $hasUnitWater ? $unit->water_charge : ($unit->estate->water_rate ?? 0) }};
        window.unitWaterReadingModal.openModal(unitData, waterRate);
      }
    },
    
    openInvoiceModal(tenancyId) {
      if (window.invoiceCreateModal) {
        window.invoiceCreateModal.openModal(tenancyId);
      }
    },
    
    openMaintenanceModal() {
      if (window.maintenanceModal) {
        window.maintenanceModal.openModal({{ $unit->id }});
      } else {
        window.successModal?.simple('Coming Soon', 'Maintenance recording feature will be available soon!');
      }
    }
  }));
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection