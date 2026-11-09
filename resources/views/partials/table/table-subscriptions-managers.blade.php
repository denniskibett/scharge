<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Account Managers</h3>
        <div class="flex flex-wrap items-center gap-3 lg:justify-end">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $accountManagers->count() }} managers assigned to this region</span>
            <button onclick="window.subscriptionsManagersModal?.openModal({{ $planId }})"
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
                    <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Manager</th>
                    <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Title</th>
                    <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Region</th>
                    <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                @if($accountManagers && $accountManagers->count() > 0)
                    @foreach($accountManagers as $manager)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                        <span class="text-sm font-medium text-purple-600">{{ strtoupper(substr($manager->user?->name ?? 'U', 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $manager->user?->name ?? 'Unknown' }}</span>
                                            @if($manager->is_primary)
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Primary</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $manager->user?->email ?? '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $manager->title ?? 'Account Manager' }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $manager->region_name ?? 'N/A' }}</td>
                            <td class="p-4 whitespace-nowrap text-right">
                                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                    <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                                        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                                    </button>
                                    <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                        <button type="button" onclick="window.subscriptionsManagersModal?.openModal({{ $planId }}, {{ $manager->id }})" @click="open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                                        <button type="button" onclick="removeManager({{ $planId }}, {{ $manager->id }})" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Remove</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="4" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No account managers assigned to this region</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use Assign to add one.</p>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<script>
function removeManager(planId, managerId) {
    if (!confirm('Remove this account manager from the plan?')) return;
    
    fetch(`/admin/subscriptions/plans/${planId}/managers/${managerId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            (window.schargeRefresh || (() => location.reload()))();
        } else {
            alert(data.message || 'Error removing manager');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing manager');
    });
}
</script>