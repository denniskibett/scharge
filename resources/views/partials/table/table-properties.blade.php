@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

<div x-data="propertyTable()" x-init="init()">
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Properties</h3>

      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="text" x-model="searchTerm" @input.debounce.300ms="filterProperties()" id="propertySearch" placeholder="Search properties..."
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <div class="relative">
          <select x-model.number="entriesPerPage" @change="updateTable()" id="entriesPerPage" aria-label="Rows per page"
            class="dark:bg-dark-900 h-11 w-full min-w-[80px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
          <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
        </div>
        <button type="button" @click="openCreateModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
          Add Property
        </button>
      </div>
    </div>

    <div class="w-full overflow-x-auto">
      <table class="data-table w-full table-auto" id="propertiesTable">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('name')">
              <div class="flex items-center gap-1">
                <span>Name</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'name' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'name' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('location')">
              <div class="flex items-center gap-1">
                <span>Location</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'location' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'location' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer" @click="sortBy('units_count')">
              <div class="flex items-center gap-1">
                <span>Units</span>
                <span class="flex flex-col gap-0.5">
                  <svg :class="sortColumn === 'units_count' && sortDirection === 'asc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 0.585167C4.21057 0.300808 3.78943 0.300807 3.59038 0.585166L1.05071 4.21327C0.81874 4.54466 1.05582 5 1.46033 5H6.53967C6.94418 5 7.18126 4.54466 6.94929 4.21327L4.40962 0.585167Z" fill="currentColor"/></svg>
                  <svg :class="sortColumn === 'units_count' && sortDirection === 'desc' ? 'text-brand-500' : 'text-gray-300'" width="8" height="5" viewBox="0 0 8 5" fill="none"><path d="M4.40962 4.41483C4.21057 4.69919 3.78943 4.69919 3.59038 4.41483L1.05071 0.786732C0.81874 0.455343 1.05582 0 1.46033 0H6.53967C6.94418 0 7.18126 0.455342 6.94929 0.786731L4.40962 4.41483Z" fill="currentColor"/></svg>
                </span>
              </div>
            </th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800" id="propertiesTableBody">
          <template x-for="property in paginatedProperties" :key="property.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                    <span class="text-sm font-medium text-purple-600" x-text="property.name.charAt(0).toUpperCase()"></span>
                  </div>
                  <div>
                    <a :href="`/properties/${property.id}`" class="property-name text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90" x-text="property.name"></a>
                    <p class="property-units text-xs text-gray-500 dark:text-gray-400"><span x-text="property.units_count"></span> units</p>
                  </div>
                </div>
              </td>
              <td class="p-4 whitespace-nowrap">
                <div class="property-location text-sm text-gray-500 dark:text-gray-400" x-text="property.location"></div>
              </td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="property.units_count"></td>
              <td class="p-4 whitespace-nowrap text-right">
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative inline-block" @click.outside="open = false" @scroll.window="open = false">
                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                  </button>
                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 text-left dark:border-gray-800">
                    <a :href="`/properties/${property.id}`" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</a>
                    <button type="button" @click="openEditModal(property); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                    <button type="button" @click="confirmDelete(property); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          <tr x-show="filteredProperties.length === 0">
            <td colspan="4" class="px-4 py-12 text-center">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No properties found</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">Try adjusting your search or filter criteria</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col items-center justify-between border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row">
      <div class="pb-3 sm:pb-0">
        <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
          Showing
          <span class="text-gray-800 dark:text-white/90" x-text="showingStart"></span>
          to
          <span class="text-gray-800 dark:text-white/90" x-text="showingEnd"></span>
          of
          <span class="text-gray-800 dark:text-white/90" x-text="filteredProperties.length"></span>
        </span>
      </div>
      <div class="flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-4 sm:w-auto sm:justify-normal sm:bg-transparent sm:p-0 dark:bg-white/[0.03] dark:sm:bg-transparent">
        <button type="button" @click="prevPage()" :disabled="currentPage === 1" class="shadow-theme-xs flex items-center gap-2 rounded-lg border border-gray-300 bg-white p-2 text-gray-700 hover:bg-gray-50 hover:text-gray-800 sm:p-2.5 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
          <span><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M2.58203 9.99868C2.58174 10.1909 2.6549 10.3833 2.80152 10.53L7.79818 15.5301C8.09097 15.8231 8.56584 15.8233 8.85883 15.5305C9.15183 15.2377 9.152 14.7629 8.85921 14.4699L5.13911 10.7472L16.6665 10.7472C17.0807 10.7472 17.4165 10.4114 17.4165 9.99715C17.4165 9.58294 17.0807 9.24715 16.6665 9.24715L5.14456 9.24715L8.85919 5.53016C9.15199 5.23717 9.15184 4.7623 8.85885 4.4695C8.56587 4.1767 8.09099 4.17685 7.79819 4.46984L2.84069 9.43049C2.68224 9.568 2.58203 9.77087 2.58203 9.99715C2.58203 9.99766 2.58203 9.99817 2.58203 9.99868Z" fill=""/></svg></span>
        </button>
        <span class="block text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400" x-text="'Page ' + currentPage + ' of ' + totalPages"></span>
        <ul id="paginationNumbers" class="hidden items-center gap-0.5 sm:flex">
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
</div>


<!-- CREATE PROPERTY SLIDEOVER MODAL -->
<div x-data="createPropertyModal()" x-init="init()">
    <!-- Backdrop -->
    <template x-if="isOpen">
        <div 
            @click="closeModal()"
            class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
    </template>

    <!-- Modal Content - Slides from Right -->
    <div x-show="isOpen" 
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        x-cloak
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <!-- close btn -->
            <button
                @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Add New Property
                </h4>

                <!-- Form Errors -->
                <template x-if="duplicates.length > 0">
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        <p class="mb-3 font-medium">This building may already be on scharge (perhaps a BnB host registered a unit in it).</p>
                        <template x-for="m in duplicates" :key="m.id">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/70 p-3 dark:bg-white/5">
                                <div>
                                    <p class="font-semibold" x-text="m.name"></p>
                                    <p class="text-xs" x-text="(m.location || 'No location') + ' · ' + m.units + ' unit(s) · ' + (m.managed ? 'managed by another company' : 'not managed yet')"></p>
                                </div>
                                <button type="button" @click="claim(m)" :disabled="loading"
                                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800 disabled:opacity-50">Claim this building</button>
                            </div>
                        </template>
                        <textarea x-model="claimMessage" rows="2" maxlength="500" placeholder="Optional note for scharge, e.g. we have managed this block since 2024"
                                  class="mt-1 w-full rounded-lg border border-amber-200 bg-white p-2 text-xs text-gray-800 dark:bg-gray-900 dark:text-white/90"></textarea>
                        <button type="button" @click="confirmNew()" class="mt-2 text-xs font-medium underline">It is a different building: create it anyway</button>
                    </div>
                </template>

                <template x-if="formErrors.length > 0">
                    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc pl-5">
                            <template x-for="error in formErrors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Property Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            placeholder="Enter property name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Location
                        </label>
                        <input
                            type="text"
                            x-model="form.location"
                            placeholder="Enter property location"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <!-- Utility Charges Section -->
                    <div class="col-span-1 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4">Default Utility Charges</h5>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">These charges will be applied to all new units in this property by default</p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Water Rate (per unit)
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.water_rate"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cost per water unit consumed (e.g., 150.00 per unit)</p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Service Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.service_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Garbage Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.garbage_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Security Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.security_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="closeModal()"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Create Property</span>
                        <span x-show="loading">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT PROPERTY SLIDEOVER MODAL -->
<div x-data="editPropertyModal()" x-init="init()">
    <!-- Backdrop -->
    <template x-if="isOpen">
        <div 
            @click="closeModal()"
            class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
    </template>

    <!-- Modal Content - Slides from Right -->
    <div x-show="isOpen" 
         x-transition:enter="transition transform ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         x-cloak
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <!-- close btn -->
            <button
                @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Edit Property
                </h4>

                <!-- Form Errors -->
                <template x-if="formErrors.length > 0">
                    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc pl-5">
                            <template x-for="error in formErrors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Property Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            placeholder="Enter property name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Location
                        </label>
                        <input
                            type="text"
                            x-model="form.location"
                            placeholder="Enter property location"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <!-- Utility Charges Section -->
                    <div class="col-span-1 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4">Default Utility Charges</h5>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">These charges will be applied to all new units in this property by default</p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Water Rate (per unit)
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.water_rate"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cost per water unit consumed (e.g., 150.00 per unit)</p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Service Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.service_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Garbage Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.garbage_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Security Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.security_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="closeModal()"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Update Property</span>
                        <span x-show="loading">Updating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE PROPERTY MODAL (Centered) -->
<div x-data="deletePropertyModal()" x-show="isModalOpen" x-cloak>
    <div class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" @click="closeModal()"></div>
        <div 
            class="relative w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8"
        >
            <!-- close btn -->
            <button
                @click="closeModal()"
                class="group absolute right-3 top-3 z-999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <div class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                
                <h4 class="mb-2 text-lg font-medium text-gray-800 dark:text-white/90">
                    Delete Property
                </h4>
                
                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete "<span x-text="property?.name" class="font-medium"></span>"? 
                    This action cannot be undone and will also delete all associated units.
                </p>

                <div class="flex items-center justify-center gap-3">
                    <button
                        @click="closeModal()"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteProperty()"
                        :disabled="loading"
                        type="button"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-red-600 shadow-theme-xs hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Delete Property</span>
                        <span x-show="loading">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function propertyTable() {
    return {
        properties: [],
        filteredProperties: [],
        paginatedProperties: [],
        currentPage: 1,
        entriesPerPage: 10,
        searchTerm: '',
        sortColumn: 'name',
        sortDirection: 'asc',
        showingStart: 1,
        showingEnd: 10,
        totalPages: 1,
        
        createModal: null,
        editModal: null,
        deleteModal: null,
        
        init() {
            const propertiesElement = document.getElementById('properties-data');
            if (propertiesElement) {
                this.properties = JSON.parse(propertiesElement.textContent);
            }
            
            this.filteredProperties = [...this.properties];
            this.updateTable();
            
            this.$nextTick(() => {
                this.createModal = Alpine.$data(document.querySelector('[x-data="createPropertyModal()"]'));
                this.editModal = Alpine.$data(document.querySelector('[x-data="editPropertyModal()"]'));
                this.deleteModal = Alpine.$data(document.querySelector('[x-data="deletePropertyModal()"]'));
            });
        },
        
        filterProperties() {
            if (!this.searchTerm.trim()) {
                this.filteredProperties = [...this.properties];
            } else {
                const term = this.searchTerm.toLowerCase();
                this.filteredProperties = this.properties.filter(property => {
                    return (
                        property.name.toLowerCase().includes(term) ||
                        (property.location && property.location.toLowerCase().includes(term)) ||
                        property.units_count.toString().includes(term)
                    );
                });
            }
            
            this.sortProperties();
            this.updateTable();
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortProperties();
            this.updateTable();
        },
        
        sortProperties() {
            this.filteredProperties.sort((a, b) => {
                let aValue, bValue;
                if (this.sortColumn === 'units_count') {
                    aValue = a.units_count;
                    bValue = b.units_count;
                } else {
                    aValue = a[this.sortColumn]?.toString().toLowerCase() || '';
                    bValue = b[this.sortColumn]?.toString().toLowerCase() || '';
                }
                if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        updateTable() {
            this.totalPages = Math.ceil(this.filteredProperties.length / this.entriesPerPage);
            const startIndex = (this.currentPage - 1) * this.entriesPerPage;
            const endIndex = startIndex + this.entriesPerPage;
            this.paginatedProperties = this.filteredProperties.slice(startIndex, endIndex);
            this.showingStart = this.filteredProperties.length ? startIndex + 1 : 0;
            this.showingEnd = Math.min(endIndex, this.filteredProperties.length);
        },
        
        get visiblePages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 1) return [1];
            pages.push(1);
            let start = Math.max(2, current - 1);
            let end = Math.min(total - 1, current + 1);
            if (start > 2) pages.push('...');
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) pages.push(i);
            }
            if (end < total - 1) pages.push('...');
            if (total > 1) pages.push(total);
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
                this.createModal.openModal();
            }
        },
        
        openEditModal(property) {
            if (this.editModal) {
                this.editModal.openModal(property);
            }
        },
        
        confirmDelete(property) {
            if (this.deleteModal) {
                this.deleteModal.openModal(property);
            }
        }
    };
}

function createPropertyModal() {
    return propertyDuplicateActions({
        isOpen: false,
        form: {
            name: '',
            location: '',
            water_rate: '',
            service_charge: '',
            garbage_charge: '',
            security_charge: ''
        },
        formErrors: [],
        duplicates: [],
        claimMessage: '',
        loading: false,
        
        init() {
            window.createPropertyModal = this;
        },
        
        openModal() {
            this.resetForm();
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.duplicates = []; this.claimMessage = '';
            this.isOpen = false;
            this.formErrors = [];
            this.loading = false;
            document.body.style.overflow = '';
        },
        
        resetForm() {
            this.form = {
                name: '',
                location: '',
                water_rate: '',
                service_charge: '',
                garbage_charge: '',
                security_charge: ''
            };
            this.formErrors = [];
            this.loading = false;
        },
        
        async submitForm() {
            this.loading = true;
            this.formErrors = [];
            
            try {
                const response = await fetch('{{ route("properties.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();

                if (response.status === 409 && data.duplicate) {
                    this.duplicates = data.matches || [];
                    return;
                }
                
                if (response.ok) {
                    this.closeModal();
                    if (window.successModal) {
                        window.successModal.simple('Property Created', `Property "${this.form.name}" has been created successfully!`);
                    }
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1500);
                } else {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat();
                        if (window.errorModal) {
                            window.errorModal.show('Creation Failed', 'Please correct the following errors:', errorMessages);
                        } else {
                            this.formErrors = errorMessages;
                        }
                    } else {
                        if (window.errorModal) {
                            window.errorModal.show('Creation Failed', data.message || 'Failed to create property');
                        } else {
                            this.formErrors = [data.message || 'Failed to create property'];
                        }
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                if (window.errorModal) {
                    window.errorModal.show('Error', 'An unexpected error occurred. Please try again.');
                } else {
                    this.formErrors = ['An unexpected error occurred. Please try again.'];
                }
            } finally {
                this.loading = false;
            }
        }
    });
}


// Duplicate-building helpers, mixed into createPropertyModal()
function propertyDuplicateActions(vm) {
    vm.confirmNew = function () { this.form.confirm_new = 1; this.duplicates = []; this.submitForm(); };
    vm.claim = async function (m) {
        this.loading = true;
        try {
            const r = await fetch('{{ route("property-claims.store") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ property_id: m.id, message: this.claimMessage })
            });
            const d = await r.json();
            this.closeModal();
            if (r.ok && window.successModal) window.successModal.simple('Claim sent', d.message);
            else if (window.errorModal) window.errorModal.show('Claim not sent', d.message || 'Please try again.');
        } finally { this.loading = false; }
    };
    return vm;
}

function editPropertyModal() {
    return {
        isOpen: false,
        property: null,
        form: {
            name: '',
            location: '',
            water_rate: '',
            service_charge: '',
            garbage_charge: '',
            security_charge: ''
        },
        formErrors: [],
        loading: false,
        
        init() {
            window.editPropertyModal = this;
        },
        
        openModal(property) {
            this.property = property;
            this.form = {
                name: property.name,
                location: property.location || '',
                water_rate: property.water_rate || '',
                service_charge: property.service_charge || '',
                garbage_charge: property.garbage_charge || '',
                security_charge: property.security_charge || ''
            };
            this.formErrors = [];
            this.loading = false;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.isOpen = false;
            this.property = null;
            this.formErrors = [];
            this.loading = false;
            document.body.style.overflow = '';
        },
        
        async submitForm() {
            this.loading = true;
            this.formErrors = [];
            
            try {
                const response = await fetch(`/properties/${this.property.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.closeModal();
                    if (window.successModal) {
                        window.successModal.simple('Property Updated', `Property "${this.form.name}" has been updated successfully!`);
                    }
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1500);
                } else {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat();
                        if (window.errorModal) {
                            window.errorModal.show('Update Failed', 'Please correct the following errors:', errorMessages);
                        } else {
                            this.formErrors = errorMessages;
                        }
                    } else {
                        if (window.errorModal) {
                            window.errorModal.show('Update Failed', data.message || 'Failed to update property');
                        } else {
                            this.formErrors = [data.message || 'Failed to update property'];
                        }
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                if (window.errorModal) {
                    window.errorModal.show('Error', 'An unexpected error occurred. Please try again.');
                } else {
                    this.formErrors = ['An unexpected error occurred. Please try again.'];
                }
            } finally {
                this.loading = false;
            }
        }
    };
}

function deletePropertyModal() {
    return {
        isModalOpen: false,
        property: null,
        loading: false,
        
        openModal(property) {
            this.property = property;
            this.loading = false;
            this.isModalOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.isModalOpen = false;
            this.property = null;
            this.loading = false;
            document.body.style.overflow = '';
        },
        
        async deleteProperty() {
            this.loading = true;
            
            try {
                const response = await fetch(`/properties/${this.property.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.closeModal();
                    if (window.successModal) {
                        window.successModal.simple('Property Deleted', data.message || 'Property deleted successfully!');
                    }
                    setTimeout(() => {
                        (window.schargeRefresh || (() => location.reload()))();
                    }, 1500);
                } else {
                    if (window.errorModal) {
                        window.errorModal.show('Deletion Failed', data.message || 'Failed to delete property.');
                    } else {
                        alert(data.message || 'Failed to delete property.');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                if (window.errorModal) {
                    window.errorModal.show('Error', 'An unexpected error occurred. Please try again.');
                } else {
                    alert('An error occurred. Please try again.');
                }
            } finally {
                this.loading = false;
            }
        }
    };
}
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

<!-- Hidden data element for Alpine.js -->
<script type="application/json" id="properties-data">
@json($propertiesData)
</script>