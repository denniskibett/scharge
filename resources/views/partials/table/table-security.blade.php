@props(['logs' => [], 'showActions' => true])

<div class="space-y-4">
    <!-- Header with Create Button -->
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Security Access Logs</h3>
        @if($showActions && auth()->user()->hasAnyRole(['super_admin', 'admin', 'security']))
        <button @click="openSecuritySlideOver()" 
                class="inline-flex items-center px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Access Log
        </button>
        @endif
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Person Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Access Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                    @if($showActions)
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($logs as $log)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ \Carbon\Carbon::parse($log['datetime'])->format('M d, Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        {{ $log['unit_number'] }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $log['person_name'] }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if($log['access_type'] === 'entry') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                            @elseif($log['access_type'] === 'exit') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                            @elseif($log['access_type'] === 'delivery') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                            @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                            @endif">
                            {{ ucfirst($log['access_type']) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($log['status'] === 'granted')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                Granted
                            </span>
                        @elseif($log['status'] === 'pending')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                Pending
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                Denied
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $log['verified_by'] ?? 'System' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ $log['notes'] ?? '-' }}">
                        {{ Str::limit($log['notes'] ?? '-', 50) }}
                    </td>
                    @if($showActions)
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="viewSecurityLog({{ $log['id'] }})" 
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                                    title="View Details">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'security']))
                            <button onclick="editSecurityLog({{ $log['id'] }})" 
                                    class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 transition-colors"
                                    title="Edit Log">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button onclick="deleteSecurityLog({{ $log['id'] }})" 
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                    title="Delete Log">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $showActions ? 8 : 7 }}" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p>No access logs found.</p>
                            @if($showActions && auth()->user()->hasAnyRole(['super_admin', 'admin', 'security']))
                            <button @click="openSecuritySlideOver()" 
                                    class="mt-3 text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                Create your first access log
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE/EDIT SLIDE-OVER MODAL -->
<div x-show="showSecuritySlideOver" 
     x-cloak
     x-transition:enter="transform transition ease-in-out duration-300"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transform transition ease-in-out duration-300"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     class="fixed inset-0 z-[99999] overflow-hidden" 
     style="display: none;">
    
    <!-- Backdrop with 50% opacity frosted glass -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" 
         @click="closeSecuritySlideOver()"></div>
    
    <!-- Slide-over Panel -->
    <div class="absolute inset-y-0 right-0 max-w-full flex">
        <div class="w-screen max-w-md">
            <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-xl overflow-y-auto">
                <!-- Header -->
                <div class="px-4 py-6 bg-gradient-to-r from-brand-500 to-brand-600 sm:px-6">
                    <div class="flex items-start justify-between">
                        <h2 class="text-lg font-medium text-white" id="slide-over-title">
                            <span x-text="securityForm.id ? 'Edit Access Log' : 'Create New Access Log'"></span>
                        </h2>
                        <div class="ml-3 h-7 flex items-center">
                            <button @click="closeSecuritySlideOver()" class="rounded-md text-white hover:text-gray-200 focus:outline-none">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitSecurityLog" class="flex-1 flex flex-col">
                    <div class="flex-1 px-4 py-6 sm:px-6">
                        <div class="space-y-6">
                            <!-- Unit Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Unit *
                                </label>
                                <select x-model="securityForm.unit_id" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Unit</option>
                                    @foreach($units ?? [] as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->unit_number }} - {{ $unit->estate->name ?? '' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Person Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Person Name *
                                </label>
                                <input type="text" 
                                       x-model="securityForm.person_name" 
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Full name of the person">
                            </div>

                            <!-- Access Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Access Type *
                                </label>
                                <select x-model="securityForm.access_type" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white">
                                    <option value="entry">Entry</option>
                                    <option value="exit">Exit</option>
                                    <option value="delivery">Delivery</option>
                                    <option value="visitor">Visitor</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Status *
                                </label>
                                <select x-model="securityForm.status" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white">
                                    <option value="granted">Granted</option>
                                    <option value="denied">Denied</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>

                            <!-- Date & Time -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Date & Time *
                                </label>
                                <input type="datetime-local" 
                                       x-model="securityForm.datetime" 
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <!-- Verified By -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Verified By
                                </label>
                                <input type="text" 
                                       x-model="securityForm.verified_by" 
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white"
                                       placeholder="Who verified this access">
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Notes
                                </label>
                                <textarea x-model="securityForm.notes" 
                                          rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Additional notes..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex-shrink-0 px-4 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-end space-x-3">
                            <button type="button"
                                    @click="closeSecuritySlideOver()"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="submitting"
                                    class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <span x-show="!submitting" x-text="securityForm.id ? 'Update Log' : 'Create Log'"></span>
                                <span x-show="submitting" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- VIEW MODAL (Center with frosted bg) -->
<div x-show="showSecurityViewModal" 
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform scale-95"
     x-transition:enter-end="opacity-100 transform scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform scale-100"
     x-transition:leave-end="opacity-0 transform scale-95"
     class="fixed inset-0 z-[99999] overflow-y-auto" 
     style="display: none;">
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Frosted backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            Access Log Details
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Date & Time</p>
                                <p class="text-sm text-gray-900 dark:text-white" x-text="viewLog.datetime_formatted"></p>
                            </div>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Unit</p>
                                <p class="text-sm text-gray-900 dark:text-white" x-text="viewLog.unit_number"></p>
                            </div>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Person Name</p>
                                <p class="text-sm text-gray-900 dark:text-white" x-text="viewLog.person_name"></p>
                            </div>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Access Type</p>
                                <p class="text-sm text-gray-900 dark:text-white" x-text="viewLog.access_type"></p>
                            </div>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                                <p class="text-sm" :class="viewLog.status === 'granted' ? 'text-green-600' : (viewLog.status === 'pending' ? 'text-yellow-600' : 'text-red-600')" x-text="viewLog.status"></p>
                            </div>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Verified By</p>
                                <p class="text-sm text-gray-900 dark:text-white" x-text="viewLog.verified_by || 'System'"></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Notes</p>
                                <p class="text-sm text-gray-900 dark:text-white" x-text="viewLog.notes || '-'"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button"
                        @click="closeSecurityViewModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:mt-0 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div x-show="showSecurityDeleteModal" 
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform scale-95"
     x-transition:enter-end="opacity-100 transform scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform scale-100"
     x-transition:leave-end="opacity-0 transform scale-95"
     class="fixed inset-0 z-[99999] overflow-y-auto" 
     style="display: none;">
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Delete Access Log
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Are you sure you want to delete this access log? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button"
                        @click="confirmDeleteSecurityLog()"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </button>
                <button type="button"
                        @click="closeSecurityDeleteModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:mt-0 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>