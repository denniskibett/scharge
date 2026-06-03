@extends('layouts.app')

@section('content')
<!-- Include all modal partials -->
@include('partials.modal.units-create-modal')
@include('partials.modal.units-edit-modal')
@include('partials.modal.units-show-modal')
@include('partials.modal.units-delete-modal')
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

<!-- Stats Cards Row -->
<div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-lg bg-blue-50 p-4 shadow-sm dark:bg-blue-900/20">
        <div class="text-sm text-gray-600 dark:text-gray-400">Total Units</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $totalUnits }}</div>
    </div>
    <div class="rounded-lg bg-green-50 p-4 shadow-sm dark:bg-green-900/20">
        <div class="text-sm text-gray-600 dark:text-gray-400">Occupied</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $occupiedCount }}</div>
    </div>
    <div class="rounded-lg bg-orange-50 p-4 shadow-sm dark:bg-orange-900/20">
        <div class="text-sm text-gray-600 dark:text-gray-400">Vacant</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $vacantCount }}</div>
    </div>
    <div class="rounded-lg bg-purple-50 p-4 shadow-sm dark:bg-purple-900/20">
        <div class="text-sm text-gray-600 dark:text-gray-400">Monthly Total Potential</div>
        <div class="text-2xl font-bold text-gray-800 dark:text-white">KES {{ number_format($monthlyTotalChargesPotential, 2) }}</div>
    </div>
</div>

<!-- Include the units table partial -->
@include('partials.table.table-units', [
    'unitsData' => $unitsData ?? [],
    'totalUnits' => $totalUnits,
    'occupiedCount' => $occupiedCount,
    'vacantCount' => $vacantCount,
    'estates' => $estates ?? [],
    'currentEstate' => $estate ?? null,
    'showEstateFilter' => true,
    'showEstateColumn' => true,
    'showUtilityColumns' => true,
    'showTotalColumn' => true,
    'hideEstateFilter' => false
])

<!-- Hidden data for Alpine.js (kept for backward compatibility) -->
<script type="application/json" id="units-data">
@json($unitsData ?? [])
</script>
@endsection