<!-- Tenants Table -->
<div x-data="tenantTable()" x-init="init()">
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Table Header -->
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">All Tenants</h3>
      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="text" x-model="searchTerm" @input.debounce.300ms="filterTenants()" id="tenantSearch" placeholder="Search tenants..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <div class="relative">
          <select x-model="entriesPerPage" @change="updateTable()" id="entriesPerPage" aria-label="Per page" class="dark:bg-dark-900 h-11 w-full min-w-[80px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
          <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
        </div>
        <button type="button" @click="openCreateModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
          Add Tenant
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="w-full overflow-x-auto">
      <table class="data-table w-full table-auto" id="tenantsTable">
        <!-- Desktop table header -->
        <thead class="hidden sm:table-header-group">
          <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('name')">
              <div class="flex items-center gap-1">
                <span>Tenant</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('email')">
              <div class="flex items-center gap-1">
                <span>Email</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'email' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'email' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('phone')">
              <div class="flex items-center gap-1">
                <span>Phone</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'phone' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'phone' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('current_unit')">
              <div class="flex items-center gap-1">
                <span>Current Unit</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'current_unit' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'current_unit' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('tenancies_count')">
              <div class="flex items-center gap-1">
                <span>Tenancies</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'tenancies_count' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'tenancies_count' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('created_at')">
              <div class="flex items-center gap-1">
                <span>Joined</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'created_at' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'created_at' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>

        <!-- Mobile table header -->
        <thead class="sm:hidden">
          <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Tenant</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800" id="tenantsTableBody">
          <template x-for="tenant in paginatedTenants" :key="tenant.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <!-- Desktop cells -->
              <td class="hidden p-4 whitespace-nowrap sm:table-cell">
                <div class="flex items-center gap-3">
                  <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                    <span class="text-sm font-medium text-purple-600" x-text="tenant.name?.charAt(0)?.toUpperCase() || 'T'"></span>
                  </div>
                  <div>
                    <a :href="`/tenants/${tenant.id}`" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">
                      <span x-text="tenant.name"></span>
                    </a>
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="tenant.email"></p>
                  </div>
                </div>
              </td>
              <td class="hidden p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 sm:table-cell" x-text="tenant.email"></td>
              <td class="hidden p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 sm:table-cell" x-text="tenant.phone || '-'"></td>
              <td class="hidden p-4 whitespace-nowrap sm:table-cell">
                <template x-if="tenant.current_unit">
                  <div>
                    <a :href="`/units/${tenant.current_unit.id}`" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">
                      <span x-text="tenant.current_unit.unit_number"></span>
                    </a>
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="tenant.current_unit.property_name"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Since <span x-text="tenant.current_unit.move_in_date_formatted"></span></p>
                  </div>
                </template>
                <template x-if="!tenant.current_unit">
                  <span class="text-sm text-gray-500 dark:text-gray-400">No active tenancy</span>
                </template>
              </td>
              <td class="hidden p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 sm:table-cell" x-text="tenant.tenancies_count"></td>
              <td class="hidden p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 sm:table-cell" x-text="tenant.created_at_formatted"></td>
              <td class="hidden p-4 whitespace-nowrap text-right sm:table-cell">
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>

                  </button>
                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                    <a :href="`/tenants/${tenant.id}`" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</a>
                    <button type="button" @click="openEditModal(tenant); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                    <button type="button" @click="confirmDelete(tenant); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                  </div>
                </div>
              </td>

              <!-- Mobile cells (simplified view) -->
              <td class="p-4 sm:hidden">
                <div class="flex items-center gap-3">
                  <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                    <span class="text-sm font-medium text-purple-600" x-text="tenant.name?.charAt(0)?.toUpperCase() || 'T'"></span>
                  </div>
                  <div>
                    <a :href="`/tenants/${tenant.id}`" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">
                      <span x-text="tenant.name"></span>
                    </a>
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="tenant.email"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      <span x-text="tenant.current_unit ? tenant.current_unit.unit_number : 'No unit'"></span>
                      &middot;
                      <span x-text="tenant.tenancies_count + ' tenancies'"></span>
                    </p>
                  </div>
                </div>
              </td>
              <td class="p-4 text-right sm:hidden">
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>

                  </button>
                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                    <a :href="`/tenants/${tenant.id}`" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</a>
                    <button type="button" @click="openEditModal(tenant); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                    <button type="button" @click="confirmDelete(tenant); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                  </div>
                </div>
              </td>
            </tr>
          </template>

          <tr x-show="filteredTenants.length === 0">
            <td colspan="7" class="p-4 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tenants found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">Try adjusting your search or filter criteria</p>
              </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
      <div class="pb-3 sm:pb-0">
        <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
          Showing
          <span class="text-gray-800 dark:text-white/90" x-text="showingStart"></span>
          to
          <span class="text-gray-800 dark:text-white/90" x-text="showingEnd"></span>
          of
          <span class="text-gray-800 dark:text-white/90" x-text="filteredTenants.length"></span>
        </span>
      </div>
      <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
        <button type="button" @click="prevPage()" :disabled="currentPage === 1" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
          <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/></svg></span>
        </button>
        <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
        <ul id="paginationNumbers" class="hidden items-center gap-0.5 sm:flex">
          <template x-for="page in visiblePages" :key="page">
            <li><a x-show="page !== '...'" href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a><span x-show="page === '...'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 dark:text-gray-400">...</span></li>
          </template>
        </ul>
        <button type="button" @click="nextPage()" :disabled="currentPage === totalPages" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
          <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/></svg></span>
        </button>
      </div>
    </div>
  </div>
</div>


<!-- Hidden data element for Alpine.js -->
<script type="application/json" id="tenants-data">
@json($tenantsData)
</script>

<!-- Single Create Tenant Modal -->
<div x-data="createTenantModal()" x-cloak>
    <div x-show="isModalOpen" x-cloak @click="isModalOpen = false"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>
    <div x-show="isModalOpen" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Add New Tenant
                </h4>

                <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Email will be generated automatically from name and phone.
                        <br><span class="text-xs text-gray-500">Example: john.doe.7890@tenant.com</span>
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Full Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="John Doe"
                        />
                        <p x-show="errors.name" x-text="errors.name[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Phone Number *
                        </label>
                        <input
                            type="tel"
                            x-model="form.phone"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="0712345678"
                        />
                        <p x-show="errors.phone" x-text="errors.phone[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <!-- Tenancy Section (Optional) -->
                    <div class="col-span-2 mt-2">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="h-4 w-4 rounded-full bg-blue-100 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign to Unit (Optional)</h5>
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Unit
                        </label>
                        <div class="relative">
                            <select
                                x-model="form.unit_id"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            >
                                <option value="">Select a unit (optional)</option>
                                <template x-for="unit in units" :key="unit.id">
                                    <option :value="unit.id" x-text="`${unit.unit_number} - ${unit.property_name}`"></option>
                                </template>
                            </select>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            If assigned, tenancy will start on Jan 1, 2025
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Close
                    </button>
                    
                    <!-- Switch to Bulk Add -->
                    <button
                        type="button"
                        @click="switchToBulk()"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-green-600 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-green-800 dark:border-gray-700 dark:bg-gray-800 dark:text-green-400 dark:hover:bg-white/[0.03] dark:hover:text-green-300 sm:w-auto"
                    >
                        Add Multiple
                    </button>
                    
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Create Tenant</span>
                        <span x-show="loading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Create Tenant Modal -->
<div x-data="bulkCreateTenantModal()" x-cloak>
    <div x-show="isModalOpen" x-cloak @click="isModalOpen = false"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>
    <div x-show="isModalOpen" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 42rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Bulk Add Tenants
                </h4>

                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <h5 class="mb-2 text-sm font-medium text-blue-700 dark:text-blue-300">Quick Add Multiple Tenants</h5>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Add multiple tenants at once. Only name and phone are required. You can assign each tenant to a different unit.
                        <br><span class="text-xs text-gray-500">Emails will be generated automatically. Example: john.doe.7890@tenant.com</span>
                        <br><span class="text-xs text-gray-500">Move-in date will be set to Jan 1, 2025 for all assigned units.</span>
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- Table Header -->
                    <div class="grid grid-cols-12 gap-3 text-xs font-medium text-gray-500 dark:text-gray-400 px-2">
                        <div class="col-span-4">Name *</div>
                        <div class="col-span-3">Phone *</div>
                        <div class="col-span-4">Unit (Optional)</div>
                        <div class="col-span-1"></div>
                    </div>

                    <!-- Tenant Rows -->
                    <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                        <template x-for="(tenant, index) in form.tenants" :key="index">
                            <div class="grid grid-cols-12 gap-3 items-center">
                                <!-- Name -->
                                <div class="col-span-4">
                                    <input
                                        type="text"
                                        x-model="tenant.name"
                                        :required="index === 0"
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        :placeholder="`Tenant ${index + 1} Name`"
                                    />
                                </div>
                                
                                <!-- Phone -->
                                <div class="col-span-3">
                                    <input
                                        type="tel"
                                        x-model="tenant.phone"
                                        :required="index === 0"
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        :placeholder="`07XXXXXXX`"
                                    />
                                </div>
                                
                                <!-- Unit -->
                                <div class="col-span-4">
                                    <select
                                        x-model="tenant.unit_id"
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    >
                                        <option value="">Select unit (optional)</option>
                                        <template x-for="unit in units" :key="unit.id">
                                            <option :value="unit.id" x-text="`${unit.unit_number} - ${unit.property_name}`"></option>
                                        </template>
                                    </select>
                                </div>
                                
                                <!-- Remove Button -->
                                <div class="col-span-1 flex items-center justify-center">
                                    <button
                                        type="button"
                                        @click="removeTenant(index)"
                                        class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50"
                                        x-show="index > 0"
                                        title="Remove this tenant"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Quick Actions -->
                    <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="space-x-3">
                            <button
                                type="button"
                                @click="addTenant()"
                                class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Another Row
                            </button>
                            
                            <button
                                type="button"
                                @click="assignAllToUnit()"
                                class="inline-flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-2.5 text-sm font-medium text-green-700 shadow-theme-xs transition-colors hover:bg-green-100 hover:text-green-800 dark:border-green-700 dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/30"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                Assign All to Same Unit
                            </button>
                        </div>
                        
                        <button
                            type="button"
                            @click="switchToSingle()"
                            class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                        >
                            ← Back to Single Add
                        </button>
                    </div>
                    
                    <!-- Unit Selection for All -->
                    <div x-show="showBulkUnitAssign" class="mt-4 p-4 border border-green-200 dark:border-green-800 rounded-lg bg-green-50 dark:bg-green-900/10">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="h-4 w-4 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <h5 class="text-sm font-medium text-green-700 dark:text-green-300">
                                    Assign All Tenants to Same Unit
                                </h5>
                            </div>
                            <button
                                type="button"
                                @click="showBulkUnitAssign = false"
                                class="text-green-600 hover:text-green-800"
                            >
                                Cancel
                            </button>
                        </div>
                        <select
                            x-model="bulkUnitId"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-green-300 bg-white px-4 py-2.5 text-sm text-green-800 shadow-theme-xs placeholder:text-green-400 focus:border-green-300 focus:outline-hidden focus:ring-3 focus:ring-green-500/10 dark:border-green-700 dark:bg-gray-900 dark:text-green-400 dark:placeholder:text-green-300"
                            @change="applyBulkUnit()"
                        >
                            <option value="">Select a unit for all tenants</option>
                            <template x-for="unit in units" :key="unit.id">
                                <option :value="unit.id" x-text="`${unit.unit_number} - ${unit.property_name}`"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-green-600 dark:text-green-400">
                            This will assign the selected unit to ALL tenants in the list above.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-green-600 shadow-theme-xs hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Create All Tenants (<span x-text="form.tenants.length"></span>)</span>
                        <span x-show="loading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Tenant Modal (Simplified) -->
<div x-data="editTenantModal()" x-cloak>
    <div x-show="isModalOpen" x-cloak @click="isModalOpen = false"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>
    <div x-show="isModalOpen" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Edit Tenant
                </h4>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Full Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.name" x-text="errors.name[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Email *
                        </label>
                        <input
                            type="email"
                            x-model="form.email"
                            required
                            @blur="checkEmail()"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.email" x-text="errors.email[0]" class="mt-1 text-sm text-red-600"></p>
                        <p x-show="emailError" x-text="emailError" class="mt-1 text-sm text-red-600"></p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Phone Number *
                        </label>
                        <input
                            type="tel"
                            x-model="form.phone"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        <p x-show="errors.phone" x-text="errors.phone[0]" class="mt-1 text-sm text-red-600"></p>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Close
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-blue-600 shadow-theme-xs hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Update Tenant</span>
                        <span x-show="loading">Updating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Tenant Modal -->
<div x-data="deleteTenantModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
        <div 
            @click.outside="isModalOpen = false"
            class="relative w-full max-w-[584px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10"
        >
            <!-- close btn -->
            <button
                @click="isModalOpen = false"
                class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg
                    class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"
                    />
                </svg>
            </button>

            <div class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                
                <h4 class="mb-2 text-lg font-medium text-gray-800 dark:text-white/90">
                    Delete Tenant
                </h4>
                
                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete the tenant 
                    "<span x-text="tenant?.name" class="font-medium"></span>"? 
                </p>

                <div class="flex items-center justify-center gap-3">
                    <button
                        @click="isModalOpen = false"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteTenant()"
                        :disabled="loading"
                        type="button"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-red-600 shadow-theme-xs hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Delete Tenant</span>
                        <span x-show="loading">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data element for Alpine.js -->
<script type="application/json" id="tenants-data">
@json($tenantsData)
</script>

<script type="application/json" id="vacant-units-data">
@json($vacantUnits)
</script>

<script>
function tenantTable() {
    return {
        // Data
        tenants: [],
        filteredTenants: [],
        paginatedTenants: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        
        // Modal instances
        createModal: null,
        bulkCreateModal: null,
        editModal: null,
        deleteModal: null,
        
        init() {
            // Load tenants data
            const tenantsElement = document.getElementById('tenants-data');
            if (tenantsElement) {
                this.tenants = JSON.parse(tenantsElement.textContent);
            }
            
            this.filteredTenants = [...this.tenants];
            this.updateTable();
            
            // Initialize modals after DOM is ready
            this.$nextTick(() => {
                this.createModal = Alpine.$data(document.querySelector('[x-data="createTenantModal()"]'));
                this.bulkCreateModal = Alpine.$data(document.querySelector('[x-data="bulkCreateTenantModal()"]'));
                this.editModal = Alpine.$data(document.querySelector('[x-data="editTenantModal()"]'));
                this.deleteModal = Alpine.$data(document.querySelector('[x-data="deleteTenantModal()"]'));
            });
        },
        
        filterTenants() {
            if (!this.searchTerm.trim()) {
                this.filteredTenants = [...this.tenants];
            } else {
                const term = this.searchTerm.toLowerCase();
                this.filteredTenants = this.tenants.filter(tenant => {
                    return (
                        (tenant.name && tenant.name.toLowerCase().includes(term)) ||
                        (tenant.email && tenant.email.toLowerCase().includes(term)) ||
                        (tenant.phone && tenant.phone.includes(term)) ||
                        (tenant.phone2 && tenant.phone2.includes(term)) ||
                        (tenant.current_unit && tenant.current_unit.unit_number && 
                         tenant.current_unit.unit_number.toLowerCase().includes(term)) ||
                        (tenant.current_unit && tenant.current_unit.property_name && 
                         tenant.current_unit.property_name.toLowerCase().includes(term))
                    );
                });
            }
            
            this.sortTenants();
            this.updateTable();
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            
            this.sortTenants();
            this.updateTable();
        },
        
        sortTenants() {
            this.filteredTenants.sort((a, b) => {
                let aValue, bValue;
                
                // Handle current_unit sorting
                if (this.sortColumn === 'current_unit') {
                    aValue = a.current_unit?.unit_number || '';
                    bValue = b.current_unit?.unit_number || '';
                } else {
                    aValue = a[this.sortColumn]?.toString().toLowerCase() || '';
                    bValue = b[this.sortColumn]?.toString().toLowerCase() || '';
                }
                
                // Handle numeric sorting for tenancies_count
                if (this.sortColumn === 'tenancies_count') {
                    aValue = parseInt(aValue) || 0;
                    bValue = parseInt(bValue) || 0;
                    
                    if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                    if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                }
                
                // Handle date sorting
                if (this.sortColumn === 'created_at') {
                    aValue = a.created_at ? new Date(a.created_at) : new Date(0);
                    bValue = b.created_at ? new Date(b.created_at) : new Date(0);
                    
                    if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                    if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                }
                
                // Regular string sorting
                if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredTenants.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            
            this.paginatedTenants = this.filteredTenants.slice(startIndex, endIndex);
            this.showingStart = this.filteredTenants.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredTenants.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            
            if (total <= 1) return [1];
            
            pages.push(1);
            
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            
            if (start > 2) {
                pages.push('...');
            }
            
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) {
                    pages.push(i);
                }
            }
            
            if (end < total - 1) {
                pages.push('...');
            }
            
            if (total > 1) {
                pages.push(total);
            }
            
            return pages;
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateTable();
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.updateTable();
            }
        },
        
        goToPage(page) {
            if (page !== '...') {
                this.currentPage = parseInt(page);
                this.updateTable();
            }
        },
        
        openCreateModal() {
            if (this.createModal) {
                this.createModal.open();
            }
        },
        
        openBulkCreateModal() {
            if (this.bulkCreateModal) {
                this.bulkCreateModal.open();
            }
        },
        
        openEditModal(tenant) {
            if (this.editModal) {
                this.editModal.open(tenant);
            }
        },
        
        confirmDelete(tenant) {
            if (this.deleteModal) {
                this.deleteModal.open(tenant);
            }
        }
    };
}

function createTenantModal() {
    return {
        isModalOpen: false,
        units: [],
        form: {
            name: '',
            phone: '',
            unit_id: null
        },
        errors: {},
        loading: false,
        
        open() {
            this.resetForm();
            this.loadVacantUnits();
            this.isModalOpen = true;
        },
        
        resetForm() {
            this.form = {
                name: '',
                phone: '',
                unit_id: null
            };
            this.errors = {};
            this.loading = false;
        },
        
        loadVacantUnits() {
            const unitsElement = document.getElementById('vacant-units-data');
            if (unitsElement) {
                this.units = JSON.parse(unitsElement.textContent);
            }
        },
        
        switchToBulk() {
            this.isModalOpen = false;
            const bulkModal = Alpine.$data(document.querySelector('[x-data="bulkCreateTenantModal()"]'));
            if (bulkModal) {
                // Pre-populate bulk form with current single entry
                bulkModal.form.tenants = [{
                    name: this.form.name,
                    phone: this.form.phone
                }];
                bulkModal.open();
            }
        },
        
        async submitForm() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch('/tenants', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.isModalOpen = false;
                    this.showNotification('Tenant created successfully! Email: ' + data.tenant?.user?.email, 'success');
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1500);
                } else {
                    this.errors = data.errors || {};
                    this.showNotification(data.message || 'Failed to create tenant.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-3 rounded-lg text-white shadow-lg z-99999 ${
                type === 'success' ? 'bg-green-600' : 'bg-red-600'
            }`;
            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="${type === 'success' ? 'M5 13l4 4L19 7' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z'}">
                        </path>
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    };
}

function bulkCreateTenantModal() {
    return {
        isModalOpen: false,
        units: [],
        form: {
            tenants: [
                { name: '', phone: '', unit_id: '' }
            ]
        },
        showBulkUnitAssign: false,
        bulkUnitId: '',
        errors: {},
        loading: false,
        
        open() {
            this.resetForm();
            this.loadVacantUnits();
            this.isModalOpen = true;
        },
        
        resetForm() {
            this.form = {
                tenants: [
                    { name: '', phone: '', unit_id: '' }
                ]
            };
            this.showBulkUnitAssign = false;
            this.bulkUnitId = '';
            this.errors = {};
            this.loading = false;
        },
        
        addTenant() {
            this.form.tenants.push({ name: '', phone: '', unit_id: '' });
        },
        
        removeTenant(index) {
            if (this.form.tenants.length > 1) {
                this.form.tenants.splice(index, 1);
            }
        },
        
        loadVacantUnits() {
            const unitsElement = document.getElementById('vacant-units-data');
            if (unitsElement) {
                this.units = JSON.parse(unitsElement.textContent);
            }
        },
        
        assignAllToUnit() {
            this.showBulkUnitAssign = true;
            this.bulkUnitId = '';
        },
        
        applyBulkUnit() {
            if (this.bulkUnitId) {
                // Apply the selected unit to all tenants
                this.form.tenants.forEach(tenant => {
                    tenant.unit_id = this.bulkUnitId;
                });
                this.showNotification(`All tenants assigned to selected unit`, 'success');
            }
        },
        
        switchToSingle() {
            this.isModalOpen = false;
            const singleModal = Alpine.$data(document.querySelector('[x-data="createTenantModal()"]'));
            if (singleModal && this.form.tenants.length > 0) {
                singleModal.form = {
                    name: this.form.tenants[0].name,
                    phone: this.form.tenants[0].phone,
                    unit_id: this.form.tenants[0].unit_id || null
                };
                singleModal.open();
            }
        },
        
        async submitForm() {
            // Validate at least one tenant has name and phone
            const validTenants = this.form.tenants.filter(t => t.name.trim() && t.phone.trim());
            
            if (validTenants.length === 0) {
                this.showNotification('Please add at least one tenant with name and phone.', 'error');
                return;
            }
            
            // Prepare data for submission
            const tenantsData = validTenants.map(tenant => ({
                name: tenant.name.trim(),
                phone: tenant.phone.trim(),
                unit_id: tenant.unit_id || null
            }));
            
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch('/tenants/bulk-store', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ tenants: tenantsData })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.isModalOpen = false;
                    
                    // Show success message with created emails
                    let successMessage = `${data.created?.length || 0} tenants created successfully!`;
                    
                    if (data.created && data.created.length > 0) {
                        // Group by unit assignment for better display
                        const withUnit = data.created.filter(t => t.unit).map(t => `• ${t.tenant}: ${t.email} → ${t.unit}`);
                        const withoutUnit = data.created.filter(t => !t.unit).map(t => `• ${t.tenant}: ${t.email} (no unit)`);
                        
                        successMessage += '\n\n';
                        
                        if (withUnit.length > 0) {
                            successMessage += 'With unit assignment:\n' + withUnit.join('\n');
                        }
                        
                        if (withoutUnit.length > 0) {
                            if (withUnit.length > 0) successMessage += '\n\n';
                            successMessage += 'Without unit assignment:\n' + withoutUnit.join('\n');
                        }
                    }
                    
                    this.showNotification(successMessage, 'success');
                    
                    // Show any errors if they occurred
                    if (data.errors && data.errors.length > 0) {
                        setTimeout(() => {
                            const errorMessages = data.errors.map(e => `Row ${e.index + 1} (${e.tenant}): ${e.error}`).join('\n');
                            this.showNotification(`Some tenants could not be created:\n${errorMessages}`, 'error');
                        }, 500);
                    }
                    
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 2000);
                } else {
                    this.errors = data.errors || {};
                    this.showNotification(data.message || 'Failed to create tenants.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-3 rounded-lg text-white shadow-lg z-99999 ${
                type === 'success' ? 'bg-green-600' : 'bg-red-600'
            }`;
            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="${type === 'success' ? 'M5 13l4 4L19 7' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z'}">
                        </path>
                    </svg>
                    <span class="whitespace-pre-line">${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
    };
}

function editTenantModal() {
    return {
        isModalOpen: false,
        tenant: null,
        form: {
            name: '',
            email: '',
            phone: ''
        },
        errors: {},
        loading: false,
        
        open(tenant) {
            this.tenant = tenant;
            this.form = {
                name: tenant.name || '',
                email: tenant.email || '',
                phone: tenant.phone || ''
            };
            this.errors = {};
            this.loading = false;
            this.isModalOpen = true;
        },
        
        async submitForm() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch(`/tenants/${this.tenant.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.isModalOpen = false;
                    this.showNotification('Tenant updated successfully!', 'success');
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1000);
                } else {
                    this.errors = data.errors || {};
                    this.showNotification(data.message || 'Failed to update tenant.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            alert(message);
        }
    };
}

function deleteTenantModal() {
    return {
        isModalOpen: false,
        tenant: null,
        loading: false,
        
        open(tenant) {
            this.tenant = tenant;
            this.loading = false;
            this.isModalOpen = true;
        },
        
        async deleteTenant() {
            this.loading = true;
            
            try {
                const response = await fetch(`/tenants/${this.tenant.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.isModalOpen = false;
                    this.showNotification('Tenant deleted successfully!', 'success');
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1000);
                } else {
                    this.showNotification(data.message || 'Failed to delete tenant.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        showNotification(message, type = 'success') {
            alert(message);
        }
    };
}

// Global functions to open modals
window.openCreateModal = function() {
    const modal = Alpine.$data(document.querySelector('[x-data="createTenantModal()"]'));
    if (modal) modal.open();
};

window.openBulkCreateModal = function() {
    const modal = Alpine.$data(document.querySelector('[x-data="bulkCreateTenantModal()"]'));
    if (modal) modal.open();
};
</script>

<style>
[x-cloak] { display: none !important; }
.modal-close-btn {
    backdrop-filter: blur(32px);
}
.z-99999 {
    z-index: 99999;
}
.z-999 {
    z-index: 999;
}
</style>