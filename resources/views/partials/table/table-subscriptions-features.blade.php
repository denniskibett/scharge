<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Features &amp; Benefits</h3>
        <div class="flex flex-wrap items-center gap-3 lg:justify-end">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($planData['business_features'] ?? []) }} features included</span>
            <button onclick="window.subscriptionsFeaturesModal?.openModal({{ $planData['id'] }})"
                class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Manage
            </button>
        </div>
    </div>

    <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Feature</th>
                </tr>
            </thead>
            <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($planData['business_features'] ?? [] as $feature)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ is_array($feature) ? ($feature['name'] ?? $feature[0] ?? '') : $feature }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No features listed for this plan</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use Manage to add the features this plan includes.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
