@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Units Management</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all units across all estates.</p>
        
        <!-- Debug: Check if unitsData exists -->
        @if(isset($unitsData) && count($unitsData) > 0)
            <p class="text-sm text-green-600">Data loaded: {{ count($unitsData) }} units</p>
        @else
            <p class="text-sm text-red-600">No units data found!</p>
        @endif
    </div>

    @include('partials.table.table-units')
</div>
@endsection