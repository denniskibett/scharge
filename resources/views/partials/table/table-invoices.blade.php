<!-- Invoices Table - Pure Alpine.js Component -->
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="invoiceTable()" x-init="init()" x-cloak>
  <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
    <div class="-mb-px flex shrink-0 space-x-6">
      <button type="button" @click="filterStatus = 'All'; currentPage = 1"
        :class="filterStatus === 'All' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        All
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="statusCounts.all"></span>
      </button>
      <button type="button" @click="filterStatus = 'unpaid'; currentPage = 1"
        :class="filterStatus === 'unpaid' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        Unpaid
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200" x-text="statusCounts.unpaid"></span>
      </button>
      <button type="button" @click="filterStatus = 'partial'; currentPage = 1"
        :class="filterStatus === 'partial' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        Partial
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200" x-text="statusCounts.partial"></span>
      </button>
      <button type="button" @click="filterStatus = 'paid'; currentPage = 1"
        :class="filterStatus === 'paid' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        Paid
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" x-text="statusCounts.paid"></span>
      </button>
      <button type="button" @click="filterStatus = 'draft'; currentPage = 1"
        :class="filterStatus === 'draft' ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
        class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
        Draft
        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="statusCounts.draft"></span>
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
      <div class="relative">
        <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
        <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search invoices..."
          class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
      </div>
      <div class="relative">
        <select x-model.number="itemsPerPage" @change="currentPage = 1" aria-label="Rows per page"
          class="dark:bg-dark-900 h-11 w-full min-w-[80px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
          <option value="250">250</option>
          <option value="500">500</option>
          <option value="1000">1000</option>
        </select>
        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
      </div>
      <button type="button" @click="openCreateInvoiceModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Create Invoice
      </button>
      <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
        <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" aria-label="More actions" title="More actions"
          class="shadow-theme-xs inline-flex h-11 w-11 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
          <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
        </button>
        <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 176}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-44 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
          <button type="button" @click="openBulkInvoiceModal(); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Bulk create invoices</button>
          <button type="button" @click="openWaterReconciliationModal(); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Reconcile water</button>
        </div>
      </div>
    </div>
  </div>

  <div x-show="loading" class="flex items-center justify-center py-12">
    <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-brand-500"></div>
    <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">Loading invoices...</span>
  </div>

  <div class="w-full overflow-x-auto" x-show="!loading">
    <table class="data-table w-full table-auto">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-800">
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-3">
              <label class="flex cursor-pointer items-center select-none">
                <span class="relative">
                  <input type="checkbox" class="sr-only" @change="toggleSelectAll" :checked="isAllSelected"/>
                  <span :class="isAllSelected ? 'border-brand-500 bg-brand-500' : 'bg-transparent border-gray-300 dark:border-gray-700'" class="flex h-4 w-4 items-center justify-center rounded-sm border-[1.25px]">
                    <span :class="isAllSelected ? '' : 'opacity-0'">
                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M10 3L4.5 8.5L2 6" stroke="white" stroke-width="1.6666" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                  </span>
                </span>
              </label>
              <span>Invoice #</span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('tenant_name')">
            <div class="flex items-center gap-1">
              <span>Tenant</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'tenant_name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'tenant_name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('created_at')">
            <div class="flex items-center gap-1">
              <span>Created</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'created_at' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'created_at' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('billing_month')">
            <div class="flex items-center gap-1">
              <span>Billing Month</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'billing_month' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'billing_month' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sort('total_amount')">
            <div class="flex items-center gap-1">
              <span>Total</span>
              <span class="flex flex-col gap-0.5">
                <svg :class="sortBy === 'total_amount' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                <svg :class="sortBy === 'total_amount' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
              </span>
            </div>
          </th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
          <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Water Sync</th>
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
        <template x-for="invoice in paginatedInvoices" :key="invoice.id">
          <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
            <td class="p-4 whitespace-nowrap">
              <div class="flex items-center gap-3">
                <label class="flex cursor-pointer items-center select-none">
                  <span class="relative">
                    <input type="checkbox" class="sr-only" :checked="selected.includes(invoice.id)" @change="toggleRow(invoice.id)"/>
                    <span :class="selected.includes(invoice.id) ? 'border-brand-500 bg-brand-500' : 'bg-transparent border-gray-300 dark:border-gray-700'" class="flex h-4 w-4 items-center justify-center rounded-sm border-[1.25px]">
                      <span :class="selected.includes(invoice.id) ? '' : 'opacity-0'">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M10 3L4.5 8.5L2 6" stroke="white" stroke-width="1.6666" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </span>
                    </span>
                  </span>
                </label>
                <a :href="'/invoices/' + invoice.id" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400" x-text="'#' + invoice.id"></a>
              </div>
            </td>
            <td class="p-4 whitespace-nowrap">
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                  <span class="text-sm font-medium text-purple-600" x-text="(invoice.tenant_name || 'T').charAt(0).toUpperCase()"></span>
                </div>
                <div>
                  <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="invoice.tenant_name || '-'"></span>
                  <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Unit: ' + (invoice.unit_number || '-')"></p>
                </div>
              </div>
            </td>
            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="invoice.created_at_formatted"></td>
            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="invoice.billing_month_formatted || '-'"></td>
            <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90" x-text="currencySymbol + ' ' + formatCurrency(invoice.total_amount)"></td>
            <td class="p-4 whitespace-nowrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="getStatusClass(invoice.status)" x-text="formatStatus(invoice.status)"></span>
            </td>
            <td class="p-4 whitespace-nowrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="getWaterSyncClass(invoice)" x-text="getWaterSyncText(invoice)"></span>
            </td>
            <td class="p-4 whitespace-nowrap text-right">
              <div x-data="{ open: false, x: 0, y: 0 }" class="relative inline-block" @click.outside="open = false" @scroll.window="open = false">
                <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                  <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                </button>
                <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 text-left dark:border-gray-800">
                  <a :href="'/invoices/' + invoice.id" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</a>
                  <template x-if="invoice.status !== 'paid'">
                    <button type="button" @click="openPaymentModal(invoice); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-green-600 hover:bg-green-50 hover:text-green-700 dark:text-green-400 dark:hover:bg-green-500/10">Pay</button>
                  </template>
                  <template x-if="invoice.status !== 'paid'">
                    <button type="button" @click="generateInvoiceForTenancy(invoice.tenancy_id); open = false" :disabled="isGenerating === invoice.tenancy_id" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300 disabled:opacity-50" x-text="isGenerating === invoice.tenancy_id ? 'Generating...' : 'Generate'"></button>
                  </template>
                  <template x-if="invoice.status !== 'paid'">
                    <button type="button" @click="openEditInvoiceModal(invoice); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                  </template>
                  <template x-if="invoice.water_status === 'pending' || invoice.water_status === 'needs_review'">
                    <button type="button" @click="reconcileSingleInvoice(invoice); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Sync water</button>
                  </template>
                  <template x-if="invoice.status !== 'paid'">
                    <button type="button" @click="openDeleteModal(invoice); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                  </template>
                </div>
              </div>
            </td>
          </tr>
        </template>
        <tr x-show="filteredInvoices.length === 0">
          <td colspan="8" class="px-4 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invoices found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters or search, or create a new invoice.</p>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div x-show="!loading && filteredInvoices.length > 0" class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
    <div class="pb-3 sm:pb-0">
      <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
        Showing
        <span class="text-gray-800 dark:text-white/90" x-text="((currentPage - 1) * itemsPerPage) + 1"></span>
        to
        <span class="text-gray-800 dark:text-white/90" x-text="Math.min(currentPage * itemsPerPage, filteredInvoices.length)"></span>
        of
        <span class="text-gray-800 dark:text-white/90" x-text="filteredInvoices.length"></span>
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


<!-- Include Modals AFTER the table content but ensure they are in the DOM -->
@include('partials.modal.alert-modal')
@include('partials.modal.invoice-create-modal')
@include('partials.modal.invoice-bulk-modal', ['mappedActiveTenancies' => $mappedActiveTenancies ?? collect()])
@include('partials.modal.payments-create-modal', ['invoices' => $paymentInvoices ?? []])
@include('partials.modal.invoice-delete-modal')
@include('partials.modal.water-reconciliation-modal', ['properties' => $properties ?? []])

<script>
// Global data for preloaded invoices - MUST be defined BEFORE Alpine components
var invoicesData = @json($mappedInvoices ?? []);
var activeTenanciesData = @json($mappedActiveTenancies ?? []);

// Enums and Data - Pure JavaScript, no PHP dependencies
var invoiceTypeEnum = ['move_in', 'monthly', 'move_out'];
var itemTypeEnum = ['rent', 'power', 'internet', 'water', 'security', 'garbage', 'service', 'other'];
var statusEnum = ['draft', 'unpaid', 'partial', 'paid'];

var itemTypeLabels = {
    rent: "Rent",
    power: "Power/Electricity",
    internet: "Internet",
    water: "Water",
    security: "Security",
    garbage: "Garbage Collection",
    service: "Service Charge",
    other: "Other"
};

var invoiceTypeLabels = {
    move_in: "Move In",
    monthly: "Monthly",
    move_out: "Move Out"
};

var statusLabels = {
    draft: "Draft",
    unpaid: "Unpaid",
    partial: "Partial",
    paid: "Paid"
};

var currencySymbol = "{{ SystemHelper::currencySymbol() }}";
var csrfToken = "{{ csrf_token() }}";

document.addEventListener('alpine:init', () => {
    // Main Page Component
    Alpine.data('invoicePage', () => ({
        openCreateInvoiceModal() {
            if (window.invoiceCreateModal) {
                window.invoiceCreateModal.openModal(null);
            }
        },
        openBulkInvoiceModal() {
            if (window.bulkInvoiceModal) {
                window.bulkInvoiceModal.openModal();
            }
        },
        openBulkMissingModal() {
            if (window.bulkInvoiceModal) {
                window.bulkInvoiceModal.openModal('missing');
            }
        },
        openWaterReconciliationModal() {
            if (window.waterReconciliationModal) {
                window.waterReconciliationModal.open();
            }
        }
    }));
    
    // Invoice Table Component - Pure Alpine with AJAX data fetching
    Alpine.data('invoiceTable', () => ({
        // State
        invoices: [],
        selected: [],
        sortBy: 'billing_month',
        sortDirection: 'desc',
        currentPage: 1,
        itemsPerPage: 10,
        filterStatus: 'All',
        searchQuery: '',
        isGenerating: null,
        loading: true,
        currencySymbol: currencySymbol,
        updateInProgress: false,
        reconciling: null,
        
        // Computed Properties
        get statusCounts() {
            return {
                draft: this.invoices.filter(i => i.status === 'draft').length,
                unpaid: this.invoices.filter(i => i.status === 'unpaid').length,
                partial: this.invoices.filter(i => i.status === 'partial').length,
                paid: this.invoices.filter(i => i.status === 'paid').length,
                all: this.invoices.length
            };
        },
        
        get filteredInvoices() {
            let filtered = this.invoices;
            if (this.filterStatus !== 'All') {
                filtered = filtered.filter(i => i.status === this.filterStatus);
            }
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(i => 
                    (i.tenant_name && i.tenant_name.toLowerCase().includes(query)) ||
                    (i.unit_number && i.unit_number.toString().toLowerCase().includes(query)) ||
                    (i.billing_month_formatted && i.billing_month_formatted.toLowerCase().includes(query)) ||
                    (i.total_amount && i.total_amount.toString().toLowerCase().includes(query))
                );
            }
            return filtered;
        },
        
        get sortedInvoices() {
            return this.filteredInvoices.slice().sort((a, b) => {
                let valA = a[this.sortBy];
                let valB = b[this.sortBy];
                if (this.sortBy === 'created_at' || this.sortBy === 'billing_month') {
                    valA = valA ? new Date(valA).getTime() : 0;
                    valB = valB ? new Date(valB).getTime() : 0;
                }
                if (this.sortBy === 'total_amount') {
                    valA = parseFloat(valA) || 0;
                    valB = parseFloat(valB) || 0;
                }
                if (typeof valA === 'string') {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }
                if (valA === null || valA === undefined) return 1;
                if (valB === null || valB === undefined) return -1;
                if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        get paginatedInvoices() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.sortedInvoices.slice(start, start + this.itemsPerPage);
        },
        
        get totalPages() {
            return Math.ceil(this.filteredInvoices.length / this.itemsPerPage);
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
        
        get isAllSelected() {
            return this.paginatedInvoices.length > 0 && this.paginatedInvoices.every(i => this.selected.includes(i.id));
        },
        
        // Methods
        async init() {
            await this.fetchInvoices();
            
            // Listen for payment success events
            window.addEventListener('payment-success', (event) => {
                console.log('Payment success event received:', event.detail);
                this.refreshInvoice(event.detail);
            });
            
            // Listen for global payment success
            document.addEventListener('payment-success', (event) => {
                console.log('Document payment success event received:', event.detail);
                this.refreshInvoice(event.detail);
            });
        },
        
        async fetchInvoices() {
            this.loading = true;
            try {
                // Try to get data from the page first (if preloaded)
                if (typeof invoicesData !== 'undefined' && invoicesData && invoicesData.length > 0) {
                    this.invoices = invoicesData;
                } else {
                    // Fallback: Fetch from API
                    const response = await fetch('/api/invoices', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (response.ok) {
                        const result = await response.json();
                        this.invoices = result.data || result.invoices || [];
                    } else {
                        console.warn('Could not fetch invoices, using empty array');
                        this.invoices = [];
                    }
                }
            } catch (error) {
                console.error('Error fetching invoices:', error);
                this.invoices = [];
            } finally {
                this.loading = false;
            }
        },
        
        async refreshInvoice(paymentData) {
            if (this.updateInProgress) return;
            this.updateInProgress = true;
            
            try {
                if (paymentData && paymentData.invoice_id) {
                    const response = await fetch(`/invoices/${paymentData.invoice_id}/details`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success && result.invoice) {
                            const index = this.invoices.findIndex(i => i.id === result.invoice.id);
                            if (index !== -1) {
                                this.invoices[index] = {
                                    ...this.invoices[index],
                                    ...result.invoice,
                                    status: result.invoice.status,
                                    total_amount: result.invoice.total_amount,
                                    balance: result.invoice.balance || 0,
                                    paid_amount: result.invoice.paid_amount || 0
                                };
                            } else {
                                await this.fetchInvoices();
                            }
                        } else {
                            await this.fetchInvoices();
                        }
                    } else {
                        await this.fetchInvoices();
                    }
                } else {
                    await this.fetchInvoices();
                }
                
                this.$forceUpdate();
                this.showNotification('Payment processed successfully!', 'success');
                
            } catch (error) {
                console.error('Error refreshing invoice:', error);
                await this.fetchInvoices();
            } finally {
                this.updateInProgress = false;
            }
        },
        
        showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} transition-all duration-300 transform translate-y-0`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        },
        
        formatCurrency(value) {
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        
        formatStatus(status) {
            return status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
        },
        
        getStatusClass(status) {
            const classes = {
                'paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'unpaid': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'partial': 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
            };
            return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        },
        
        getWaterSyncClass(invoice) {
            const status = invoice.water_status || 'none';
            const classes = {
                'synced': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'pending': 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                'needs_review': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'none': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
            };
            return classes[status] || classes['none'];
        },
        
        getWaterSyncText(invoice) {
            const status = invoice.water_status || 'none';
            const labels = {
                'synced': 'Synced',
                'pending': 'Pending',
                'needs_review': 'Review',
                'none': '—'
            };
            return labels[status] || labels['none'];
        },
        
        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selected = [];
            } else {
                this.selected = this.paginatedInvoices.map(i => i.id);
            }
        },
        
        toggleRow(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(i => i !== id);
            } else {
                this.selected.push(id);
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
        
        async generateInvoiceForTenancy(tenancyId) {
            this.isGenerating = tenancyId;
            try {
                const response = await fetch('{{ route("invoices.generate.single") }}', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ tenancy_id: tenancyId })
                });
                const result = await response.json();
                if (result.success) {
                    this.showNotification('Invoice generated successfully!', 'success');
                    await this.fetchInvoices();
                } else {
                    alert(result.message || 'Failed to generate invoice');
                }
            } catch (error) {
                console.error('Error generating invoice:', error);
                alert('An error occurred while generating the invoice');
            } finally {
                this.isGenerating = null;
            }
        },
        
        openPaymentModal(invoice) {
            if (!invoice.id) {
                alert('Cannot process payment: Invoice ID is missing');
                return;
            }
            
            if (window.paymentCreateModal && typeof window.paymentCreateModal.openPaymentModalForInvoice === 'function') {
                window.paymentCreateModal.openPaymentModalForInvoice(invoice);
            } else {
                const checkInterval = setInterval(() => {
                    if (window.paymentCreateModal && typeof window.paymentCreateModal.openPaymentModalForInvoice === 'function') {
                        clearInterval(checkInterval);
                        window.paymentCreateModal.openPaymentModalForInvoice(invoice);
                    }
                }, 100);
                
                setTimeout(() => {
                    clearInterval(checkInterval);
                    if (!window.paymentCreateModal || typeof window.paymentCreateModal.openPaymentModalForInvoice !== 'function') {
                        alert('Payment system is not ready. Please refresh the page and try again.');
                    }
                }, 5000);
            }
        },
        
        openCreateInvoiceModal() {
            if (window.invoiceCreateModal) {
                window.invoiceCreateModal.openModal(null);
            }
        },
        
        openEditInvoiceModal(invoice) {
            if (window.invoiceCreateModal) {
                window.invoiceCreateModal.openModal(invoice.tenancy_id);
                setTimeout(() => {
                    if (window.invoiceCreateModal && invoice.billing_month) {
                        window.invoiceCreateModal.form.billing_month = invoice.billing_month.slice(0, 7);
                        window.invoiceCreateModal.form.items = (invoice.items || []).map(item => ({
                            description: item.description,
                            item_type: item.item_type,
                            amount: item.amount,
                            id: item.id
                        }));
                        window.invoiceCreateModal.isEditMode = true;
                        window.invoiceCreateModal.form.id = invoice.id;
                    }
                }, 100);
            }
        },
        
        openDeleteModal(invoice) {
            if (window.invoiceDeleteModal) {
                window.invoiceDeleteModal.openModal(invoice);
            }
        },
        
        openBulkInvoiceModal() {
            if (window.bulkInvoiceModal) {
                window.bulkInvoiceModal.openModal();
            }
        },

        openWaterReconciliationModal() {
            if (window.waterReconciliationModal) {
                window.waterReconciliationModal.open();
            }
        },
        
async reconcileSingleInvoice(invoice) {
    if (!confirm(`Reconcile water charges for invoice #${invoice.id}? This will sync with meter readings.`)) {
        return;
    }
    
    this.reconciling = invoice.id;
    
    try {
        // Get the billing month from the invoice
        let month = invoice.billing_month || invoice.billing_month_formatted;
        
        if (!month) {
            alert('No billing month found for this invoice');
            return;
        }
        
        // FIX: Handle different date formats
        // If it's already in Y-m format (2026-05)
        if (month.match(/^\d{4}-\d{2}$/)) {
            // Already in correct format
        } 
        // If it's in Y-m-d format (2026-05-01) - from database
        else if (month.match(/^\d{4}-\d{2}-\d{2}$/)) {
            month = month.substring(0, 7); // Extract Y-m
        }
        // If it's formatted like "Jan 2025" - from formatted display
        else if (month.includes(' ') && month.length < 15) {
            const parts = month.split(' ');
            const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const monthIndex = monthNames.findIndex(m => m.toLowerCase() === parts[0].toLowerCase());
            if (monthIndex !== -1) {
                month = parts[1] + '-' + String(monthIndex + 1).padStart(2, '0');
            } else {
                alert('Could not parse billing month format: ' + month);
                return;
            }
        }
        // If it's a Date object or timestamp
        else if (typeof month === 'number' || month.includes('T')) {
            const date = new Date(month);
            if (!isNaN(date.getTime())) {
                month = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            } else {
                alert('Invalid billing month format');
                return;
            }
        }
        // Fallback: try to extract from any string
        else if (typeof month === 'string') {
            // Try to find YYYY-MM pattern
            const match = month.match(/(\d{4})-(\d{2})/);
            if (match) {
                month = match[1] + '-' + match[2];
            } else {
                alert('Could not parse billing month format: ' + month);
                return;
            }
        }
        
        // Validate the month format
        if (!month.match(/^\d{4}-\d{2}$/)) {
            alert('Invalid billing month format after parsing: ' + month);
            return;
        }
        
        console.log('Reconciling invoice #' + invoice.id + ' for month: ' + month);
        
        const response = await fetch('{{ route("invoices.bulk-reconcile") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                billing_month: month,
                invoice_ids: [invoice.id]
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const result = data.results && data.results[0];
            if (result) {
                if (result.status === 'updated') {
                    this.showNotification(`Invoice #${invoice.id}: Water charge updated from KES ${result.old_charge} to KES ${result.new_charge}`, 'success');
                } else if (result.status === 'already_correct') {
                    this.showNotification(`Invoice #${invoice.id}: Water charge already correct (KES ${result.charge})`, 'info');
                } else if (result.status === 'no_reading') {
                    this.showNotification(`Invoice #${invoice.id}: No water reading found for ${month}`, 'warning');
                }
            }
            await this.fetchInvoices();
        } else {
            alert(data.message || 'Reconciliation failed');
        }
    } catch (error) {
        console.error('Error reconciling invoice:', error);
        alert('Error reconciling water charges: ' + error.message);
    } finally {
        this.reconciling = null;
    }
}
    }));
    
    // Dropdown Component
    Alpine.data('dropdown', () => ({
        open: false,
        toggle() { this.open = !this.open; }
    }));
});
</script>

<style>
[x-cloak] { display: none !important; }
.custom-scrollbar { scrollbar-width: thin; }
.custom-scrollbar::-webkit-scrollbar { height: 8px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; }
.dark .custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; }
.z-99999 { z-index: 99999 !important; }
.backdrop-blur-\[32px\] { backdrop-filter: blur(32px); }
</style>