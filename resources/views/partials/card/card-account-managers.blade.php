{{-- resources/views/partials/card/card-account-managers.blade.php --}}
<div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Account Managers</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Manage account managers assigned to companies</p>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <a href="{{ route('admin.account-managers.create') }}" 
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Account Manager
        </a>
    </div>
</div>

@if(session('success'))
    <div class="mt-4 rounded-xl bg-success-50 p-4 text-success-700 dark:bg-success-500/15 dark:text-success-400">
        <span class="text-sm">{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="mt-4 rounded-xl bg-error-50 p-4 text-error-700 dark:bg-error-500/15 dark:text-error-400">
        <span class="text-sm">{{ session('error') }}</span>
    </div>
@endif