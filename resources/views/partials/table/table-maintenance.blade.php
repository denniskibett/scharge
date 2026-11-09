@props(['requests' => []])

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="maintenanceTable()" x-init="init()">
  <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
    <div class="-mb-px flex shrink-0 space-x-6">
      <button type="button" @click="filterStatus = 'all'; currentPage = 1"
        :class="filterStatus === 'all' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        All
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="statusCounts.all"></span>
      </button>
      <button type="button" @click="filterStatus = 'open'; currentPage = 1"
        :class="filterStatus === 'open' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        Open
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200" x-text="statusCounts.open"></span>
      </button>
      <button type="button" @click="filterStatus = 'in_progress'; currentPage = 1"
        :class="filterStatus === 'in_progress' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        In Progress
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200" x-text="statusCounts.in_progress"></span>
      </button>
      <button type="button" @click="filterStatus = 'resolved'; currentPage = 1"
        :class="filterStatus === 'resolved' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        Resolved
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" x-text="statusCounts.resolved"></span>
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
      <div class="relative">
        <select x-model="filterPriority" @change="currentPage = 1" aria-label="Priority"
          class="dark:bg-dark-900 h-11 w-full min-w-[140px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
          <option value="all">All Priorities</option>
          <option value="emergency">Emergency</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
      </div>
      <div class="relative">
        <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
        <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search requests..."
          class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
      </div>
      <button type="button" @click="exportRequests()" class="shadow-theme-xs inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20"><path d="M10 1.66667V12.5M10 12.5L13.3333 9.16667M10 12.5L6.66667 9.16667M4.16667 15H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.16667 18.3333H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Export
      </button>
      <button type="button" @click="openCreateModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        New Request
      </button>
    </div>
  </div>

  <div x-show="loading" class="flex items-center justify-center py-12">
    <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-brand-500"></div>
    <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">Loading requests...</span>
  </div>

  <div class="w-full overflow-x-auto" x-show="!loading">
    <table class="data-table w-full table-auto">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-800">
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('request_number')">
            <div class="flex items-center gap-1">
              <span>Request #</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'request_number' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'request_number' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('unit_number')">
            <div class="flex items-center gap-1">
              <span>Unit</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'unit_number' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'unit_number' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Tenant</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Title</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('priority')">
            <div class="flex items-center gap-1">
              <span>Priority</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'priority' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'priority' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('status')">
            <div class="flex items-center gap-1">
              <span>Status</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'status' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'status' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('created_at')">
            <div class="flex items-center gap-1">
              <span>Reported</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'created_at' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'created_at' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
        <template x-for="request in paginatedRequests" :key="request.id">
          <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
            <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="request.request_number"></td>
            <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="request.unit_number"></td>
            <td class="p-4 whitespace-nowrap">
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                  <span class="text-sm font-medium text-purple-600" x-text="(request.tenant_name || 'T').charAt(0).toUpperCase()"></span>
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="request.tenant_name"></span>
              </div>
            </td>
            <td class="p-4">
              <div class="max-w-xs">
                <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="request.title"></span>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="request.description"></p>
              </div>
            </td>
            <td class="p-4 whitespace-nowrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="request.priority_color">
                <svg x-show="request.priority === 'emergency'" class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <svg x-show="request.priority === 'high'" class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                <svg x-show="request.priority === 'medium'" class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"></path></svg>
                <svg x-show="request.priority === 'low'" class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                <span x-text="request.priority_label"></span>
              </span>
            </td>
            <td class="p-4 whitespace-nowrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="request.status_color" x-text="request.status_label"></span>
            </td>
            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="formatDate(request.created_at)"></td>
            <td class="p-4 whitespace-nowrap text-right">
              <div x-data="{ open: false, x: 0, y: 0 }" class="relative inline-block" @click.outside="open = false" @scroll.window="open = false">
                <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                  <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                </button>
                <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 text-left dark:border-gray-800">
                  <button type="button" @click="viewRequestModal(request.id); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</button>
                  <button type="button" @click="openEditModal(request.id); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Update</button>
                </div>
              </div>
            </td>
          </tr>
        </template>
        <tr x-show="filteredRequests.length === 0">
          <td colspan="8" class="px-4 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No maintenance requests</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No maintenance requests match your criteria.</p>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div x-show="!loading && filteredRequests.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
    <div class="pb-3 sm:pb-0">
      <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
        Showing
        <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + (paginatedRequests.length ? 1 : 0)"></span>
        to
        <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + paginatedRequests.length"></span>
        of
        <span class="text-gray-800 dark:text-white/90" x-text="filteredRequests.length"></span>
      </span>
    </div>
    <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
      <button type="button" @click="previousPage()" :disabled="currentPage === 1" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
        <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/></svg></span>
      </button>
      <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
      <ul class="hidden items-center gap-0.5 sm:flex">
        <template x-for="(page, i) in visiblePages" :key="i">
          <li>
            <a href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a>
          </li>
        </template>
      </ul>
      <button type="button" @click="nextPage()" :disabled="currentPage === totalPages || totalPages === 0" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
        <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/></svg></span>
      </button>
    </div>
  </div>
</div>


<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('maintenanceTable', () => ({
        requests: @json($requests),
        searchQuery: '',
        filterStatus: 'all',
        filterPriority: 'all',
        sortBy: 'created_at',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 10,
        loading: false,
        
        init() {
            this.requests = this.requests.map(req => ({
                ...req,
                priority_label: this.getPriorityLabel(req.priority),
                priority_color: this.getPriorityColor(req.priority),
                status_label: this.getStatusLabel(req.status),
                status_color: this.getStatusColor(req.status),
                request_number: req.request_number || '#' + String(req.id).padStart(6, '0')
            }));
        },
        
        get statusCounts() {
            const counts = { all: this.requests.length, open: 0, in_progress: 0, resolved: 0 };
            this.requests.forEach(req => {
                if (req.status === 'open') counts.open++;
                else if (req.status === 'in_progress') counts.in_progress++;
                else if (req.status === 'resolved') counts.resolved++;
            });
            return counts;
        },

        viewRequestModal(id) {
            if (window.maintenanceViewModal) {
                window.maintenanceViewModal.openModal(id);
            }
        },
        
        get filteredRequests() {
            let filtered = this.requests;
            
            if (this.filterStatus !== 'all') {
                filtered = filtered.filter(req => req.status === this.filterStatus);
            }
            if (this.filterPriority !== 'all') {
                filtered = filtered.filter(req => req.priority === this.filterPriority);
            }
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(req => 
                    (req.unit_number && req.unit_number.toLowerCase().includes(query)) ||
                    (req.tenant_name && req.tenant_name.toLowerCase().includes(query)) ||
                    (req.title && req.title.toLowerCase().includes(query)) ||
                    (req.request_number && req.request_number.toLowerCase().includes(query))
                );
            }
            
            filtered = [...filtered].sort((a, b) => {
                let valA = a[this.sortBy];
                let valB = b[this.sortBy];
                if (this.sortBy === 'created_at') {
                    valA = valA ? new Date(valA).getTime() : 0;
                    valB = valB ? new Date(valB).getTime() : 0;
                }
                if (typeof valA === 'string') {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }
                if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
            return filtered;
        },
        
        get paginatedRequests() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredRequests.slice(start, start + this.itemsPerPage);
        },
        
        get totalPages() {
            return Math.ceil(this.filteredRequests.length / this.itemsPerPage);
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
        
        getPriorityLabel(priority) {
            const labels = { emergency: 'Emergency', high: 'High', medium: 'Medium', low: 'Low' };
            return labels[priority] || 'Medium';
        },
        
        getPriorityColor(priority) {
            const colors = {
                emergency: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                medium: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                low: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
            };
            return colors[priority] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        },
        
        getStatusLabel(status) {
            const labels = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved' };
            return labels[status] || status;
        },
        
        getStatusColor(status) {
            const colors = {
                resolved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                open: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
            };
            return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        },
        
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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
            if (page >= 1 && page <= this.totalPages) this.currentPage = page;
        },
        
        nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
        previousPage() { if (this.currentPage > 1) this.currentPage--; },
        
        openCreateModal() {
            if (window.maintenanceModal) {
                window.maintenanceModal.openModal();
            }
        },
        
        viewRequest(id) {
            (window.spaVisit || ((u) => { window.location.href = u; }))(`/maintenance/${id}`);
        },
        
        updateRequest(id) {
            (window.spaVisit || ((u) => { window.location.href = u; }))(`/maintenance/${id}/edit`);
        },

        openEditModal(id) {
            // Fetch the data for this maintenance request
            fetch(`/maintenance/${id}/edit-data`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Dispatch event to open the modal with edit data
                        window.dispatchEvent(new CustomEvent('open-maintenance-edit', {
                            detail: data.request
                        }));
                    }
                })
                .catch(error => console.error('Error fetching maintenance data:', error));
        },
        
        exportRequests() {
            const headers = ['Request #', 'Unit', 'Tenant', 'Title', 'Description', 'Priority', 'Status', 'Reported Date'];
            const rows = this.filteredRequests.map(r => [
                r.request_number,
                r.unit_number,
                r.tenant_name,
                r.title,
                r.description,
                r.priority_label,
                r.status_label,
                this.formatDate(r.created_at)
            ]);
            const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `maintenance-requests-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
    }));
});
</script>

<!-- Include Maintenance Create Modal -->
{{-- @include('partials.modal.maintenance-create-modal', ['units' => $units ?? []]) --}}

<!-- Include Maintenance Modals -->
{{-- @include('partials.modal.maintenance-create-modal', ['units' => $units ?? [], 'currentUnit' => $currentUnit ?? null]) --}}
@include('partials.modal.maintenance-create-modal', [
    'units' => $units ?? [], 
    'properties' => $properties ?? [],
    'currentUnit' => $currentUnit ?? null
])
@include('partials.modal.maintenance-view-modal')

<style>
[x-cloak] { display: none !important; }
.custom-scrollbar { scrollbar-width: thin; }
.custom-scrollbar::-webkit-scrollbar { height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; }
.dark .custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; }
</style>