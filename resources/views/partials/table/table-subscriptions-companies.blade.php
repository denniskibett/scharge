<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Companies Using This Plan</h3>
        <div class="flex flex-wrap items-center gap-3 lg:justify-end">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $companies->count() }} companies actively subscribed</span>
            <button onclick="window.subscriptionsCompaniesModal?.openModal({{ $planId }})"
                class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Assign
            </button>
        </div>
    </div>

    <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Company</th>
                    <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                    <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                @if($companies && $companies->count() > 0)
                    @foreach($companies as $company)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                        <span class="text-sm font-medium text-purple-600">{{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.companies.show', $company->id) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $company->name ?? 'N/A' }}</a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $company->email ?? '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                            </td>
                            <td class="p-4 whitespace-nowrap text-right">
                                <a href="{{ route('admin.companies.show', $company->id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">View</a>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="3" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No companies currently using this plan</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Companies will appear here once they subscribe.</p>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
