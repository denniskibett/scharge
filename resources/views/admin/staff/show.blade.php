@extends('layouts.app')

@section('title', 'Staff Profile - ' . ($user->name ?? 'Unknown'))

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('admin.staff.index') }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
            <i class="fas fa-arrow-left mr-2"></i> Back to Staff
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-1">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-6 text-center">
                    <div class="mx-auto h-24 w-24 rounded-full bg-blue-100 flex items-center justify-center">
                        <span class="text-4xl font-bold text-blue-600">{{ substr($user->name ?? 'U', 0, 1) }}</span>
                    </div>
                    <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">{{ $user->name ?? 'Unknown' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $user->role->name ?? 'No Role')) }}</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                        @if($user->status == 0) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($user->status == 1) bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                        @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @endif">
                        {{ $user->status == 0 ? 'Active' : ($user->status == 1 ? 'Inactive' : 'Pending') }}
                    </span>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 p-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Email</span>
                        <span class="text-gray-800 dark:text-white">{{ $user->email ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Phone</span>
                        <span class="text-gray-800 dark:text-white">{{ $user->phone ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Company</span>
                        <span class="text-gray-800 dark:text-white">{{ $user->company->name ?? 'No Company' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Joined</span>
                        <span class="text-gray-800 dark:text-white">{{ $user->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Card -->
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Staff Details</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Comprehensive view of staff member information.</p>
                </div>
                <div class="p-6">
                    <!-- You can add more details here -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Role Description</p>
                            <p class="mt-1 text-sm text-gray-800 dark:text-white">{{ $user->role->description ?? 'No description available.' }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Access Level</p>
                            <p class="mt-1 text-sm text-gray-800 dark:text-white">
                                @if($user->role->name == 'admin')
                                    Full system access
                                @elseif($user->role->name == 'property_manager')
                                    Property management access
                                @elseif($user->role->name == 'accountant')
                                    Financial access
                                @else
                                    Limited access
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection