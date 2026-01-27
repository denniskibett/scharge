@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6" x-data="estateShow()" x-init="init()">
    <!-- Estate Info Card -->
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 pb-6 pt-6 dark:border-gray-800 dark:bg-white/[0.03] mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90 mb-2">{{ $estate->name }}</h1>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-gray-600 dark:text-gray-400">{{ $estate->location }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-gray-600 dark:text-gray-400">{{ $totalUnits }} Units</span>
                    </div>
                </div>
            </div>
            
            {{-- <div class="mt-4 sm:mt-0 flex gap-3">
              
            </div> --}}
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Units</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            {{ $totalUnits }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Occupied Units</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            {{ $occupiedCount }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Vacant Units</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            {{ $vacantCount }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Monthly Rent Potential</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            KES {{ number_format($monthlyRentPotential, 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.table.table-units', ['units' => $estate->units, 'estate' => $estate])
    {{-- @include('partials.table.table-units', [
            'unitsData' => $unitsData,
            'estate' => $estate,
            'totalUnits' => $totalUnits,
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount
        ])
    </div> --}}

@endsection