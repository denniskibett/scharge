{{-- resources/views/partials/table/table-subscriptions-company.blade.php --}}
<!-- Company Subscriptions Table - Shows which companies are subscribed to which plans -->
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="companySubscriptionsTable()" x-init="init()">
    <!-- Table Header -->
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <div class="flex -mb-px space-x-6 overflow-x-auto">
        <button type="button" @click="filterStatus = 'all'; currentPage = 1" :class="filterStatus === 'all' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'" class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
          All
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><span x-text="statusCounts.all"></span></span>
        </button>
        <button type="button" @click="filterStatus = 'active'; currentPage = 1" :class="filterStatus === 'active' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'" class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
          Active
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"><span x-text="statusCounts.active"></span></span>
        </button>
        <button type="button" @click="filterStatus = 'trial'; currentPage = 1" :class="filterStatus === 'trial' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'" class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
          Trial
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"><span x-text="statusCounts.trial"></span></span>
        </button>
        <button type="button" @click="filterStatus = 'cancelled'; currentPage = 1" :class="filterStatus === 'cancelled' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'" class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
          Cancelled
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><span x-text="statusCounts.cancelled"></span></span>
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="text" placeholder="Search company or plan..." x-model="searchQuery" @input="currentPage = 1" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"/>
        </div>
        <a href="{{ route('admin.subscriptions.index') }}" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
          Manage All
        </a>
      </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="flex items-center justify-center px-4 py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-brand-500"></div>
      <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">Loading company subscriptions...</span>
    </div>

    <!-- Error State -->
    <div x-show="!loading && error" class="px-4 py-12 text-center">
      <svg class="mx-auto h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <h3 class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">Error Loading Subscriptions</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="errorMessage || 'Could not load company subscriptions. Please check your connection.'"></p>
      <button type="button" @click="loadSubscriptions()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition mt-4">Retry</button>
    </div>

    <!-- Table Content -->
    <div x-show="!loading && !error">
      <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('company_name')">
              <div class="flex items-center gap-1">
                <span>Company</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortBy === 'company_name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortBy === 'company_name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('plan_name')">
              <div class="flex items-center gap-1">
                <span>Plan</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortBy === 'plan_name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortBy === 'plan_name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('status')">
              <div class="flex items-center gap-1">
                <span>Status</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortBy === 'status' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortBy === 'status' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('unit_count')">
              <div class="flex items-center gap-1">
                <span>Units</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortBy === 'unit_count' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortBy === 'unit_count' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('monthly_price')">
              <div class="flex items-center gap-1">
                <span>Monthly Price</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortBy === 'monthly_price' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortBy === 'monthly_price' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Billing</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Ends At</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
            <template x-for="subscription in paginatedSubscriptions" :key="subscription.id">
              <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="p-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                      <span class="text-sm font-medium text-purple-600" x-text="subscription.company_name?.charAt(0) || '?'"></span>
                    </div>
                    <div>
                      <a :href="'/admin/subscriptions/company/' + subscription.company_id" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90" x-text="subscription.company_name"></a>
                      <p class="text-xs text-gray-500 dark:text-gray-400" x-text="subscription.company_email"></p>
                    </div>
                  </div>
                </td>
                <td class="p-4 whitespace-nowrap">
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="subscription.plan_name"></p>
                  <p class="text-xs text-gray-500 dark:text-gray-400" x-text="subscription.billing_cycle"></p>
                </td>
                <td class="p-4 whitespace-nowrap">
                  <span :class="subscription.status_color" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" x-text="subscription.status_label"></span>
                </td>
                <td class="p-4 whitespace-nowrap">
                  <p class="text-sm text-gray-500 dark:text-gray-400" x-text="subscription.unit_count || 0"></p>
                  <p class="text-xs text-gray-500 dark:text-gray-400" x-text="subscription.pricing_type === 'per_unit' ? 'per unit pricing' : 'fixed pricing'"></p>
                </td>
                <td class="p-4 whitespace-nowrap">
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">KES <span x-text="Number(subscription.monthly_price || 0).toLocaleString()"></span></p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">/month</p>
                </td>
                <td class="p-4 whitespace-nowrap">
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="subscription.auto_renew ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'" x-text="subscription.auto_renew ? 'Auto-renew' : 'Manual'"></span>
                </td>
                <td class="p-4 whitespace-nowrap">
                  <p class="text-sm text-gray-500 dark:text-gray-400" x-text="subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : 'Never'"></p>
                  <p x-show="subscription.days_remaining > 0" class="text-xs" :class="subscription.days_remaining < 7 ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'">
                    <span x-text="subscription.days_remaining"></span> days remaining
                  </p>
                  <p x-show="subscription.days_remaining <= 0 && subscription.ends_at" class="text-xs text-red-500">Expired</p>
                </td>
                <td class="p-4 whitespace-nowrap text-right">
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>

                  </button>
                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                    <a :href="'/admin/subscriptions/company/' + subscription.company_id" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</a>
                    <button type="button" @click="manageSubscription(subscription.id); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Manage</button>
                    <button type="button" x-show="subscription.status === 'active' || subscription.status === 'trial'" @click="cancelSubscription(subscription.id); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Cancel</button>
                    <button type="button" x-show="subscription.status === 'cancelled'" @click="resumeSubscription(subscription.id); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Resume</button>
                  </div>
                </div>
                </td>
              </tr>
            </template>

            <template x-if="filteredSubscriptions.length === 0">
              <tr>
              <td colspan="8" class="p-4 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No company subscriptions found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Companies can subscribe to plans from the admin panel.</p>
              </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div x-show="filteredSubscriptions.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
      <div class="pb-3 sm:pb-0">
        <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
          Showing
          <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + (paginatedSubscriptions.length ? 1 : 0)"></span>
          to
          <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + paginatedSubscriptions.length"></span>
          of
          <span class="text-gray-800 dark:text-white/90" x-text="filteredSubscriptions.length"></span>
        </span>
      </div>
      <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
        <button type="button" @click="previousPage()" :disabled="currentPage === 1" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
          <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/></svg></span>
        </button>
        <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
        <ul class="hidden items-center gap-0.5 sm:flex">
          <template x-for="page in visiblePages" :key="page">
            <li><a href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a></li>
          </template>
        </ul>
        <button type="button" @click="nextPage()" :disabled="currentPage === totalPages" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
          <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/></svg></span>
        </button>
      </div>
    </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('companySubscriptionsTable', () => ({
        subscriptions: [],
        sortBy: 'company_name',
        sortDirection: 'asc',
        currentPage: 1,
        itemsPerPage: 10,
        filterStatus: 'all',
        searchQuery: '',
        loading: true,
        error: false,
        errorMessage: '',
        
        get statusCounts() {
            return {
                all: this.subscriptions.length,
                active: this.subscriptions.filter(s => s.status === 'active').length,
                trial: this.subscriptions.filter(s => s.status === 'trial').length,
                cancelled: this.subscriptions.filter(s => s.status === 'cancelled').length,
            };
        },
        
        get filteredSubscriptions() {
            let filtered = this.subscriptions;
            if (this.filterStatus !== 'all') {
                filtered = filtered.filter(s => s.status === this.filterStatus);
            }
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(s => 
                    s.company_name?.toLowerCase().includes(query) ||
                    s.plan_name?.toLowerCase().includes(query) ||
                    s.company_email?.toLowerCase().includes(query)
                );
            }
            return filtered;
        },
        
        get sortedSubscriptions() {
            return this.filteredSubscriptions.slice().sort((a, b) => {
                let valA = a[this.sortBy];
                let valB = b[this.sortBy];
                if (typeof valA === 'string') {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }
                if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        get paginatedSubscriptions() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.sortedSubscriptions.slice(start, start + this.itemsPerPage);
        },
        
        get totalPages() {
            return Math.ceil(this.filteredSubscriptions.length / this.itemsPerPage);
        },
        
        get visiblePages() {
            const pages = [];
            const maxVisible = 5;
            let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
            let end = Math.min(this.totalPages, start + maxVisible - 1);
            if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
        
        async init() {
            await this.loadSubscriptions();
        },
        
        async loadSubscriptions() {
            this.loading = true;
            this.error = false;
            this.errorMessage = '';
            
            try {
                console.log('Loading company subscriptions...');
                const response = await fetch('/admin/subscriptions/api/company-subscriptions', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('Company subscriptions response:', result);
                
                if (result.success) {
                    this.subscriptions = result.subscriptions || [];
                    console.log('Loaded ' + this.subscriptions.length + ' company subscriptions');
                } else {
                    throw new Error(result.message || 'Failed to load subscriptions');
                }
                
            } catch (error) {
                console.error('Error fetching company subscriptions:', error);
                this.error = true;
                this.errorMessage = error.message || 'Could not load company subscriptions. Please check your connection.';
                this.subscriptions = [];
            } finally {
                this.loading = false;
            }
        },
        
        sort(field) {
            if (this.sortBy === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = field;
                this.sortDirection = 'asc';
            }
        },
        
        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },
        
        nextPage() { 
            if (this.currentPage < this.totalPages) this.currentPage++; 
        },
        
        previousPage() { 
            if (this.currentPage > 1) this.currentPage--; 
        },
        
        manageSubscription(subscriptionId) {
            (window.spaVisit || ((u) => { window.location.href = u; }))(`/admin/subscriptions/subscription/${subscriptionId}`);
        },
        
        async cancelSubscription(subscriptionId) {
            if (!confirm('Are you sure you want to cancel this subscription?')) return;
            
            try {
                const response = await fetch(`/admin/subscriptions/subscription/${subscriptionId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ immediate: false })
                });
                
                const result = await response.json();
                if (result.success) {
                    await this.loadSubscriptions();
                    alert(result.message || 'Subscription cancelled successfully');
                } else {
                    alert(result.message || 'Error cancelling subscription');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error cancelling subscription: ' + error.message);
            }
        },
        
        async resumeSubscription(subscriptionId) {
            try {
                const response = await fetch(`/admin/subscriptions/subscription/${subscriptionId}/resume`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                
                const result = await response.json();
                if (result.success) {
                    await this.loadSubscriptions();
                    alert(result.message || 'Subscription resumed successfully');
                } else {
                    alert(result.message || 'Error resuming subscription');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error resuming subscription: ' + error.message);
            }
        }
    }));
});
</script>