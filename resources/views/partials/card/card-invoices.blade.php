<!-- Overview Cards -->
<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-800 dark:text-white/90">Overview</h2>
        </div>
        <div class="flex gap-3">
            <button @click="openBulkMissingModal()" class="bg-yellow-500 shadow-theme-xs hover:bg-yellow-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Generate Pending
            </button>
        </div>
    </div>
    <div class="grid grid-cols-1 rounded-xl border border-gray-200 sm:grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-y-0 dark:divide-gray-800 dark:border-gray-800">
        <div class="border-b p-5 sm:border-r lg:border-b-0">
            <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Draft</p>
            <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalDraft ?? 0, 2) }}</h3>
        </div>
        <div class="border-b p-5 lg:border-b-0">
            <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Unpaid</p>
            <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalUnpaid ?? 0, 2) }}</h3>
        </div>
        <div class="border-b p-5 sm:border-r sm:border-b-0">
            <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Partially Paid</p>
            <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalPartial ?? 0, 2) }}</h3>
        </div>
        <div class="p-5">
            <p class="mb-1.5 text-sm text-gray-400 dark:text-gray-500">Paid</p>
            <h3 class="text-3xl text-gray-800 dark:text-white/90">{{ SystemHelper::currencySymbol() }} {{ number_format($totalPaid ?? 0, 2) }}</h3>
        </div>
    </div>
</div>