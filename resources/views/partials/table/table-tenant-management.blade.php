@props(['tenants' => [], 'showActions' => true, 'showTenancyInfo' => true])

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
  <div class="w-full overflow-x-auto">
    <table class="data-table w-full table-auto">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-800">
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Tenant</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Contact</th>
          @if($showTenancyInfo)
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Current Unit</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Move-in Date</th>
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Monthly Rent</th>
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Balance</th>
          @endif
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
          @if($showActions)
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
          @endif
        </tr>
      </thead>
      <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
        @forelse($tenants as $tenant)
        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
          <td class="p-4 whitespace-nowrap">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                <span class="text-sm font-medium text-purple-600">{{ substr($tenant['name'] ?? 'T', 0, 1) }}</span>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenant['name'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant['email'] }}</p>
              </div>
            </div>
          </td>
          <td class="p-4 whitespace-nowrap">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tenant['phone'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant['email'] }}</p>
          </td>
          @if($showTenancyInfo)
          <td class="p-4 whitespace-nowrap">
            @if($tenant['current_unit'] ?? false)
              <span class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ $tenant['current_unit']['unit_number'] }}</span>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant['current_unit']['property_name'] }}</p>
            @else
              <span class="text-sm text-gray-500 dark:text-gray-400">No active tenancy</span>
            @endif
          </td>
          <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            {{ $tenant['current_unit']['move_in_date_formatted'] ?? '-' }}
          </td>
          <td class="p-4 whitespace-nowrap text-right text-sm font-medium text-gray-800 dark:text-white/90">
            KES {{ number_format($tenant['current_unit']['rent_amount'] ?? 0, 2) }}
          </td>
          <td class="p-4 whitespace-nowrap text-right text-sm font-medium {{ ($tenant['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
            KES {{ number_format($tenant['balance'] ?? 0, 2) }}
          </td>
          @endif
          <td class="p-4 whitespace-nowrap">
            @if($tenant['current_unit'] ?? false)
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
            @else
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
            @endif
          </td>
          @if($showActions)
          <td class="p-4 whitespace-nowrap text-right">
          <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
            <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
              <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>

            </button>
            <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
              <button type="button" onclick="viewTenant({{ $tenant['id'] }})" @click="open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</button>
              <button type="button" onclick="editTenant({{ $tenant['id'] }})" @click="open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
              <button type="button" onclick="viewInvoices({{ $tenant['id'] }})" @click="open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Invoices</button>
              <button type="button" onclick="deleteTenant({{ $tenant['id'] }})" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
            </div>
          </div>
          </td>
          @endif
        </tr>
        @empty
        <tr>
          <td colspan="{{ 2 + ($showTenancyInfo ? 4 : 0) + 1 + ($showActions ? 1 : 0) }}" class="p-4 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tenants found</h3>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
