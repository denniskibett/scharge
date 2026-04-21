@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.tenants-create-modal')
@include('partials.modal.tenants-edit-modal')
@include('partials.modal.tenants-show-modal')
@include('partials.modal.tenants-delete-modal')
@include('partials.modal.success-modal')

<div x-data="tenantsTable" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Table Header with Tabs, Filters and Buttons all on same line -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between border-b border-gray-200 dark:border-gray-700 px-5 py-4 gap-4">
      <!-- Tabs -->
      <div class="flex -mb-px space-x-6">
        <button
          @click="activeTab = 'all'; filterTenants()"
          :class="activeTab === 'all' 
            ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
          class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
        >
          All Tenants
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
            {{ $totalTenants }}
          </span>
        </button>
        
        <button
          @click="activeTab = 'active'; filterTenants()"
          :class="activeTab === 'active' 
            ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
          class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
        >
          Active
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
            {{ $activeTenants }}
          </span>
        </button>
        
        <button
          @click="activeTab = 'vacant'; filterTenants()"
          :class="activeTab === 'vacant' 
            ? 'border-brand-500 text-brand-500 dark:text-brand-400 dark:border-brand-400' 
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
          class="inline-flex items-center py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
        >
          Without Unit
          <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
            {{ $vacantTenants }}
          </span>
        </button>
      </div>

      <!-- Filters, Search and Buttons -->
      <div class="flex flex-wrap items-center gap-3">
        <!-- Search Bar -->
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/>
            </svg>
          </span>
          <input 
            type="text" 
            x-model="searchTerm"
            @input.debounce.300ms="filterTenants()"
            placeholder="Search tenants..." 
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[250px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
          >
        </div>

        <!-- Entries Per Page -->
        <div class="relative">
          <select 
            x-model="entriesPerPage" 
            @change="updateTable()"
            class="dark:bg-dark-900 h-11 w-full min-w-[80px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
          >
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/>
            </svg>
          </span>
        </div>

        <!-- Add Tenant Button -->
        <button 
          @click="window.tenantCreateModal?.openModal()"
          class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          Add Tenant
        </button>
      </div>
    </div>

    <!-- Table Content -->
    <div class="w-full overflow-x-auto">
      <table class="w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('name')">
              <div class="flex items-center gap-1">
                <span>Tenant</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/>
                  </svg>
                  <svg :class="sortColumn === 'name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/>
                  </svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Contact</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Current Unit</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Tenancies</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('created_at')">
              <div class="flex items-center gap-1">
                <span>Joined</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'created_at' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/>
                  </svg>
                  <svg :class="sortColumn === 'created_at' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/>
                  </svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          <template x-for="tenant in paginatedTenants" :key="tenant.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="flex-shrink-0 h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                    <span class="text-purple-600 font-medium text-sm" x-text="getInitials(tenant.name)"></span>
                  </div>
                  <div>
                    <a :href="`/tenants/${tenant.id}`" class="font-medium text-gray-800 text-sm dark:text-white/90 hover:text-blue-600">
                      <span x-text="tenant.name"></span>
                    </a>
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="tenant.email || '-'"></p>
                  </div>
                </div>
              </td>
              <td class="p-4 whitespace-nowrap">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  <div x-text="tenant.phone || '-'"></div>
                  <div x-show="tenant.phone2" class="text-xs" x-text="tenant.phone2"></div>
                </div>
              </td>
              <td class="p-4 whitespace-nowrap">
                <template x-if="tenant.current_unit">
                  <div>
                    <a :href="`/units/${tenant.current_unit.id}`" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">
                      <span x-text="tenant.current_unit.unit_number"></span>
                    </a>
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="tenant.current_unit.estate_name"></p>
                  </div>
                </template>
                <template x-if="!tenant.current_unit">
                  <span class="text-sm text-gray-400">No unit assigned</span>
                </template>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                  <span x-text="tenant.tenancies_count || 0"></span>
                </span>
              </td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="tenant.created_at_formatted"></td>
              <td class="p-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium" 
                      :class="tenant.current_unit ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'">
                  <span x-text="tenant.current_unit ? 'Active' : 'Without Unit'"></span>
                </span>
              </td>
              <td class="p-4 whitespace-nowrap text-right">
                <div x-data="dropdown()" class="relative">
                  <button
                    @click="toggle"
                    class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                  >
                    <svg
                      class="fill-current"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z"
                        fill=""
                      />
                    </svg>
                  </button>
                  <div
                    x-show="open"
                    @click.outside="open = false"
                    class="shadow-theme-lg dark:bg-gray-dark absolute right-0 z-10 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800"
                    x-ref="dropdown"
                    x-cloak
                  >
                    <a
                      :href="`/tenants/${tenant.id}`"
                      class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    >
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      View
                    </a>
                    <button
                      @click="window.tenantEditModal?.openModal(tenant); open = false"
                      class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    >
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                      Edit
                    </button>
                    <button
                      @click="window.tenantDeleteModal?.openModal(tenant); open = false"
                      class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                    >
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Delete
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          
          <template x-if="filteredTenants.length === 0">
            <tr>
              <td colspan="7" class="p-4 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tenants found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">
                  Try adjusting your search criteria
                </p>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      
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
          <button
            @click="prevPage()"
            :disabled="currentPage === 1"
            class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span>
              <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/>
              </svg>
            </span>
          </button>
          <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
          <ul class="hidden items-center gap-0.5 sm:flex">
            <template x-for="page in visiblePages" :key="page">
              <li>
                <a href="#" @click.prevent="goToPage(page)" :class="page === currentPage ? 'bg-brand-500 text-white' : 'hover:bg-brand-500 text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white'" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium" x-text="page"></a>
              </li>
            </template>
            <template x-if="visiblePages[visiblePages.length-1] < totalPages">
              <li>
                <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 dark:text-gray-400">...</span>
              </li>
            </template>
            <template x-if="visiblePages[visiblePages.length-1] < totalPages">
              <li>
                <a href="#" @click.prevent="goToPage(totalPages)" class="hover:bg-brand-500 flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium text-gray-700 hover:text-white dark:text-gray-400 dark:hover:text-white" x-text="totalPages"></a>
              </li>
            </template>
          </ul>
          <button
            @click="nextPage()"
            :disabled="currentPage === totalPages"
            class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span>
              <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M17.4165 9.9986C17.4168 10.1909 17.3437 10.3832 17.197 10.53L12.2004 15.5301C11.9076 15.8231 11.4327 15.8233 11.1397 15.5305C10.8467 15.2377 10.8465 14.7629 11.1393 14.4699L14.8594 10.7472L3.33203 10.7472C2.91782 10.7472 2.58203 10.4114 2.58203 9.99715C2.58203 9.58294 2.91782 9.24715 3.33203 9.24715L14.854 9.24715L11.1393 5.53016C10.8465 5.23717 10.8467 4.7623 11.1397 4.4695C11.4327 4.1767 11.9075 4.17685 12.2003 4.46984L17.1578 9.43049C17.3163 9.568 17.4165 9.77087 17.4165 9.99715C17.4165 9.99763 17.4165 9.99812 17.4165 9.9986Z" fill=""/>
              </svg>
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden data for Alpine.js -->
<script type="application/json" id="tenants-data">
@json($tenantsData ?? [])
</script>

<script>
function tenantsTable() {
    return {
        // Data from controller
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
        activeTab: 'all',
        
        init() {
            // Get tenants data from hidden element
            const tenantsElement = document.getElementById('tenants-data');
            if (tenantsElement) {
                this.tenants = JSON.parse(tenantsElement.textContent);
                console.log('Tenants loaded:', this.tenants.length);
            }
            
            this.filterTenants();
            this.updateTable();
        },
        
        get totalTenants() {
            return this.tenants.length;
        },
        
        get activeCount() {
            return this.tenants.filter(t => t.current_unit).length;
        },
        
        get vacantCount() {
            return this.tenants.filter(t => !t.current_unit).length;
        },
        
        filterTenants() {
            let filtered = [...this.tenants];
            
            // Apply tab filter
            if (this.activeTab === 'active') {
                filtered = filtered.filter(tenant => tenant.current_unit);
            } else if (this.activeTab === 'vacant') {
                filtered = filtered.filter(tenant => !tenant.current_unit);
            }
            
            // Apply search filter
            if (this.searchTerm.trim()) {
                const term = this.searchTerm.toLowerCase();
                filtered = filtered.filter(tenant => {
                    return (
                        (tenant.name && tenant.name.toLowerCase().includes(term)) ||
                        (tenant.email && tenant.email.toLowerCase().includes(term)) ||
                        (tenant.phone && tenant.phone.toLowerCase().includes(term)) ||
                        (tenant.current_unit?.unit_number && tenant.current_unit.unit_number.toLowerCase().includes(term))
                    );
                });
            }
            
            this.filteredTenants = filtered;
            this.sortTenants();
            this.updateTable();
            this.currentPage = 1;
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
                
                // Handle date sorting
                if (this.sortColumn === 'created_at') {
                    aValue = a.created_at ? new Date(a.created_at) : null;
                    bValue = b.created_at ? new Date(b.created_at) : null;
                    
                    if (!aValue && !bValue) return 0;
                    if (!aValue) return this.sortDirection === 'asc' ? 1 : -1;
                    if (!bValue) return this.sortDirection === 'asc' ? -1 : 1;
                    
                    if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                    if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                }
                
                // Handle string sorting
                aValue = a[this.sortColumn]?.toString().toLowerCase() || '';
                bValue = b[this.sortColumn]?.toString().toLowerCase() || '';
                
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
            
            // Reset to first page if current page is out of bounds
            if (this.currentPage > this.totalPages && this.totalPages > 0) {
                this.currentPage = 1;
                this.updateTable();
            }
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
        
        getInitials(name) {
            if (!name) return 'T';
            return name.charAt(0).toUpperCase();
        }
    };
}

// Dropdown component
function dropdown() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        }
    };
}

// Make functions available globally
window.tenantsTable = tenantsTable;
window.dropdown = dropdown;
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection