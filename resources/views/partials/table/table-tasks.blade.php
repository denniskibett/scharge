@props(['tasks' => [], 'taskType' => 'cleaning'])

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
  <div class="w-full overflow-x-auto">
    <table class="data-table w-full table-auto">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-800">
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Unit</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Property</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Task Type</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Description</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Priority</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Assigned To</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Due Date</th>
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
        @forelse($tasks as $task)
        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
          <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
            {{ $task['unit_number'] }}
          </td>
          <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            {{ $task['property_name'] }}
          </td>
          <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            {{ ucfirst($task['type']) }}
          </td>
          <td class="p-4 max-w-xs truncate text-sm text-gray-500 dark:text-gray-400">
            {{ $task['description'] }}
          </td>
          <td class="p-4 whitespace-nowrap">
            @if($task['priority'] === 'emergency')
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Emergency</span>
            @elseif($task['priority'] === 'high')
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">High</span>
            @elseif($task['priority'] === 'medium')
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Medium</span>
            @else
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Low</span>
            @endif
          </td>
          <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            {{ $task['assigned_to'] ?? 'Unassigned' }}
          </td>
          <td class="p-4 whitespace-nowrap">
            @if($task['status'] === 'completed')
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Completed</span>
            @elseif($task['status'] === 'in_progress')
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">In Progress</span>
            @else
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Pending</span>
            @endif
          </td>
          <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            {{ isset($task['due_date']) ? \Carbon\Carbon::parse($task['due_date'])->format('M d, Y') : 'N/A' }}
          </td>
          <td class="p-4 whitespace-nowrap text-right">
            @if($task['status'] !== 'completed')
          <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
            <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
              <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>

            </button>
            <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
              <button type="button" onclick="viewTask({{ $task['id'] }})" @click="open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</button>
              <button type="button" onclick="markTaskComplete({{ $task['id'] }})" @click="open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Complete</button>
            </div>
          </div>
            @else
            <button type="button" onclick="viewTask({{ $task['id'] }})" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">View</button>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="p-4 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No {{ $taskType }} tasks found</h3>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
