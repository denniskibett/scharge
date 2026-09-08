@extends('layouts.app')

@section('title', 'Staff Management')

@section('content')
<div class="container-fluid px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Staff Management</h1>
        <a href="{{ route('staff.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Add Staff
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <p class="text-gray-500 dark:text-gray-400">Staff management page coming soon.</p>
        <!-- You can add a staff table here later -->
    </div>
</div>
@endsection