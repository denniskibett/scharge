@extends('layouts.app')

@section('title', 'Create Account Manager')

@section('content')
<div class="flex flex-col gap-5 p-6">
    <!-- Card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Create Account Manager
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Add a new account manager to manage properties
                </p>
            </div>
            <a href="{{ route('admin.account-managers.index') }}" 
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to List
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-error-200 bg-error-50 p-4 dark:border-error-500/10 dark:bg-error-500/10">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-error-600 dark:text-error-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h5 class="text-sm font-semibold text-error-800 dark:text-error-400">Please fix the following errors:</h5>
                    <ul class="mt-1 text-sm text-error-700 dark:text-error-300 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Form -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-5 dark:border-gray-800">
            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Account Manager Details</h4>
        </div>
        <div class="pt-5">
            <form action="{{ route('admin.account-managers.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- User Selection -->
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            User <span class="text-error-500">*</span>
                        </label>
                        <select name="user_id" id="user_id" 
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select User</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Company Selection -->
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Company <span class="text-error-500">*</span>
                        </label>
                        <select name="company_id" id="company_id" 
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select Company</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                        <input type="text" name="title" id="title" 
                               value="{{ old('title') }}" 
                               placeholder="e.g., Regional Manager"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                        @error('title')
                            <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Options</label>
                        <div class="mt-1.5 space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" name="is_primary" id="is_primary" value="1" 
                                       class="w-4 h-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" 
                                       {{ old('is_primary') ? 'checked' : '' }}>
                                <label for="is_primary" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Primary Account Manager</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" value="1" 
                                       class="w-4 h-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900" 
                                       {{ old('is_active') !== null ? (old('is_active') ? 'checked' : '') : 'checked' }}>
                                <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</label>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Primary managers are the main point of contact.</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col-reverse gap-3 mt-6 pt-5 border-t border-gray-200 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ route('admin.account-managers.index') }}" 
                       class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        Create Account Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection