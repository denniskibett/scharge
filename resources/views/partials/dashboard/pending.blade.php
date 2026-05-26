{{-- resources/views/dashboard/pending.blade.php --}}
@extends('layouts.app')

@section('title', 'Account Pending Verification')

@section('content')
<div x-data="{ isModalOpen: true }">
    <!-- Full Screen Modal -->
    <div
        x-show="isModalOpen"
        class="fixed top-0 left-0 z-99999 flex h-screen w-full flex-col items-center justify-between overflow-x-hidden bg-white p-6 lg:p-10 dark:bg-gray-900"
        x-cloak
    >
        <div class="overflow-y-auto w-full max-w-2xl mx-auto">
            <!-- Icon based on status -->
            <div class="mb-6 text-center">
                @if($status === 'pending_both')
                    <div class="mx-auto w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                @elseif($status === 'pending_verification')
                    <div class="mx-auto w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                @elseif($status === 'inactive')
                    <div class="mx-auto w-24 h-24 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                    </div>
                @else
                    <div class="mx-auto w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                @endif
            </div>
            
            <!-- Title -->
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90 text-center mb-3">
                Account Pending Verification
            </h2>
            <p class="text-gray-600 dark:text-gray-400 text-center mb-6">
                {{ $message }}
            </p>
            
            <!-- User Info Card -->
            <div class="rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50 p-6 mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="h-14 w-14 rounded-full bg-gray-300 dark:bg-gray-700 flex items-center justify-center">
                        <span class="text-lg font-semibold text-gray-600 dark:text-gray-400">
                            {{ substr($user->name, 0, 2) }}
                        </span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Role:</span>
                        <span class="ml-2 font-medium text-gray-800 dark:text-white/90">
                            {{ ucfirst(str_replace('_', ' ', $user->role->name ?? 'N/A')) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Registered:</span>
                        <span class="ml-2 font-medium text-gray-800 dark:text-white/90">
                            {{ $user->created_at->format('M d, Y') }}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Status Badge -->
            <div class="text-center mb-8">
                @if($status === 'pending_both')
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Awaiting Verification & Company Assignment
                    </span>
                @elseif($status === 'pending_verification')
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Awaiting Email Verification
                    </span>
                @elseif($status === 'inactive')
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                        Account Deactivated
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Awaiting Company Assignment
                    </span>
                @endif
            </div>
            
            <!-- Sign Out Button (styled as Go Back Home) -->
            <div class="flex justify-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 hover:text-gray-800 transition-all duration-200 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Go Back Home
                    </button>
                </form>
            </div>
            
            <!-- Help Section -->
            <div class="text-center mt-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Need help? Contact support at 
                    <a href="mailto:support@sharet.africa" class="text-brand-600 hover:underline dark:text-brand-400">support@sharet.africa</a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection