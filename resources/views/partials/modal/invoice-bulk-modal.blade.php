@php
    if (!isset($mappedActiveTenancies)) {
        $mappedActiveTenancies = App\Models\Tenancy::where('status', 'active')
            ->with('tenant.user', 'unit')
            ->get()
            ->map(function($tenancy) {
                return [
                    'id' => $tenancy->id,
                    'tenant_name' => $tenancy->tenant->user->name ?? 'Unknown',
                    'unit_number' => $tenancy->unit->unit_number ?? 'No Unit',
                ];
            });
    }
@endphp
<!-- BULK INVOICE SLIDEOVER MODAL (Bulk Create + Bulk Missing) -->
<div x-data="bulkInvoiceModal" x-init="init()" x-cloak>
  <!-- Backdrop -->
  <template x-if="isOpen">
    <div 
      @click="closeModal"
      class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-[99999]"
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
       class="fixed top-0 right-0 h-full w-full max-w-4xl bg-white dark:bg-gray-900 shadow-2xl z-[99999] overflow-y-auto">
    <div class="p-6 lg:p-8">
      <!-- close btn -->
      <button
        @click="closeModal"
        class="group absolute right-3 top-3 z-[99999] flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
      >
        <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
        </svg>
      </button>

      <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Bulk Invoice Management</h4>

      <!-- Tab Navigation -->
      <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
        <button 
          @click="activeTab = 'create'" 
          :class="activeTab === 'create' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">
          <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
          </svg>
          Bulk Create
        </button>
        <button 
          @click="activeTab = 'missing'" 
          :class="activeTab === 'missing' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">
          <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          Missing Invoices
        </button>
      </div>

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

      <!-- ==================== TAB 1: BULK CREATE ==================== -->
      <div x-show="activeTab === 'create'">
        <form @submit.prevent="submitBulkCreate">
          @csrf
          
          <!-- Invoice Type -->
          <div class="mb-6">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Invoice Type *
            </label>
            <select
              x-model="createForm.invoice_type"
              @change="onInvoiceTypeChange"
              required
              class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
              <option value="monthly">Monthly</option>
              <option value="move_in">Move In</option>
              <option value="move_out">Move Out</option>
            </select>
          </div>

          <!-- Billing Month -->
          <div class="mb-6">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Billing Month *
            </label>
            <input
              type="month"
              x-model="createForm.billing_month"
              @change="onBillingMonthChange"
              required
              class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
          </div>

          <!-- Target Selection -->
          <div class="mb-6">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Apply To *
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                     :class="createForm.apply_to === 'bulk' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                <input type="radio" x-model="createForm.apply_to" value="bulk" class="mr-3">
                <div>
                  <p class="font-medium text-gray-700 dark:text-gray-300">All Tenancies</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">All active tenancies</p>
                </div>
              </label>
              
              <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                     :class="createForm.apply_to === 'single' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                <input type="radio" x-model="createForm.apply_to" value="single" class="mr-3">
                <div>
                  <p class="font-medium text-gray-700 dark:text-gray-300">Single Tenancy</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">One specific tenancy</p>
                </div>
              </label>
              
              <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                     :class="createForm.apply_to === 'multiple' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                <input type="radio" x-model="createForm.apply_to" value="multiple" class="mr-3">
                <div>
                  <p class="font-medium text-gray-700 dark:text-gray-300">Multiple Tenancies</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Select specific units</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Single Tenancy Selection -->
          <div class="mb-6" x-show="createForm.apply_to === 'single'">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Select Tenancy *
            </label>
            <select
              x-model="createForm.tenancy_id"
              class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
              <option value="">Select Tenancy</option>
              <template x-for="tenancy in activeTenanciesData" :key="tenancy.id">
                <option :value="tenancy.id" x-text="tenancy.tenant_name + ' - ' + tenancy.unit_number"></option>
              </template>
            </select>
          </div>

          <!-- Multiple Tenancy Selection -->
          <div class="mb-6" x-show="createForm.apply_to === 'multiple'">
            <div class="flex items-center justify-between mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                Select Tenancies *
              </label>
              <div class="flex gap-2">
                <button type="button" @click="selectAllTenancies" class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                  Select All
                </button>
                <button type="button" @click="clearTenancySelection" class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                  Clear All
                </button>
              </div>
            </div>
            
            <div class="text-sm mb-2" x-show="getSelectedTenancyCount() > 0">
              <span class="text-gray-600 dark:text-gray-400">Selected:</span>
              <span class="font-medium ml-2" x-text="getSelectedTenancyCount()"></span>
              <span class="text-gray-500 dark:text-gray-500 ml-1">tenancy(ies)</span>
            </div>
            
            <div class="border border-gray-200 rounded-lg max-h-60 overflow-y-auto dark:border-gray-700">
              <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <template x-for="tenancy in activeTenanciesData" :key="tenancy.id">
                  <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                    <input type="checkbox" 
                           x-model="createForm.selected_tenancies" 
                           :value="tenancy.id" 
                           @change="toggleTenancySelection(tenancy.id)"
                           class="mr-3 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600">
                    <div class="flex-1">
                      <p class="font-medium text-gray-700 dark:text-gray-300" x-text="tenancy.tenant_name"></p>
                      <p class="text-sm text-gray-500 dark:text-gray-400" x-text="'Unit: ' + tenancy.unit_number"></p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">active</span>
                  </label>
                </template>
              </div>
            </div>
          </div>

          <!-- Check Existing Invoices Button -->
          <div class="mb-6" x-show="createFormValid">
            <button type="button" 
                    @click="checkExistingInvoices"
                    :disabled="isCheckingInvoices"
                    class="w-full py-2.5 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
              <span x-show="!isCheckingInvoices">🔍 Check Existing Invoices for Selected Month</span>
              <span x-show="isCheckingInvoices">Checking...</span>
            </button>
            
            <!-- Check Results -->
            <div x-show="checkResults" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <div class="ml-3">
                  <h5 class="text-sm font-medium text-blue-800 dark:text-blue-300">Invoice Check Results</h5>
                  <div class="mt-1 grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="text-blue-700 dark:text-blue-400">Already have invoices:</span>
                      <span class="ml-2 font-medium" x-text="checkResults.existing_count"></span>
                    </div>
                    <div>
                      <span class="text-green-700 dark:text-green-400">Will create for:</span>
                      <span class="ml-2 font-medium" x-text="checkResults.remaining_count"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Invoice Items Section -->
          <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                Invoice Items *
              </label>
              <button type="button" @click="addItem" class="text-sm text-brand-500 hover:text-brand-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Another Item
              </button>
            </div>
            
            <div class="space-y-4">
              <template x-for="(item, index) in createForm.items" :key="item.id">
                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                  <div class="flex justify-between items-start mb-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Item <span x-text="index + 1"></span></span>
                    <button type="button" @click="removeItem(index)" x-show="createForm.items.length > 1" class="text-gray-400 hover:text-red-500">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Item Type *</label>
                      <select x-model="item.item_type" @change="updateItemDescription(index)" :required="createForm.invoice_type === 'monthly'" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="">Select Item Type</option>
                        <option value="rent">Rent</option>
                        <option value="water">Water</option>
                        <option value="service">Service Charge</option>
                        <option value="garbage">Garbage</option>
                        <option value="security">Security</option>
                        <option value="other">Other</option>
                      </select>
                    </div>
                    <div>
                      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
                      <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                          <span class="text-gray-500 dark:text-gray-400">{{ SystemHelper::currencySymbol() }}</span>
                        </div>
                        <input type="number" step="0.01" min="0.01" x-model="item.amount" required class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="0.00"/>
                      </div>
                    </div>
                  </div>
                  <div class="mt-3">
                    <textarea x-model="item.description" rows="2" class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="Description will be auto-generated..."></textarea>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Summary -->
          <div class="mb-6 rounded-lg bg-gray-50 dark:bg-gray-800 p-4" x-show="getTotalAmount() > 0 && createForm.billing_month">
            <h5 class="font-medium text-gray-800 dark:text-white/90 mb-2">Summary</h5>
            <p class="text-sm text-gray-600 dark:text-gray-400">Total Amount per Tenancy: <span class="font-medium">{{ SystemHelper::currencySymbol() }}<span x-text="getTotalAmount().toFixed(2)"></span></span></p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Number of Items: <span class="font-medium" x-text="createForm.items.length"></span></p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Number of Tenancies: <span class="font-medium" x-text="getTenancyCount()"></span></p>
          </div>

          <div class="flex items-center justify-end w-full gap-3 mt-6">
            <button @click="closeModal" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
            <button type="submit" :disabled="isLoading || !createFormValid" class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
              <span x-show="!isLoading">Create Invoices</span>
              <span x-show="isLoading">Creating...</span>
            </button>
          </div>
        </form>
      </div>

      <!-- ==================== TAB 2: MISSING INVOICES ==================== -->
      <div x-show="activeTab === 'missing'">
        <div class="mb-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Select tenancies to generate missing invoices for</p>
            </div>
            <div class="flex gap-2">
              <button type="button" @click="selectAllMissingTenancies" class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Select All</button>
              <button type="button" @click="clearMissingTenancySelection" class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Clear All</button>
            </div>
          </div>

          <!-- Tenancy Selection for Missing Invoices -->
          <div class="border border-gray-200 rounded-lg max-h-80 overflow-y-auto dark:border-gray-700 mb-6">
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
              <template x-for="tenancy in activeTenanciesData" :key="tenancy.id">
                <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                  <input type="checkbox" 
                         x-model="missingForm.selected_tenancies" 
                         :value="tenancy.id" 
                         @change="loadMissingMonthsForTenancy(tenancy.id)" 
                         class="mr-3 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600">
                  <div class="flex-1">
                    <p class="font-medium text-gray-700 dark:text-gray-300" x-text="tenancy.tenant_name"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400" x-text="'Unit: ' + tenancy.unit_number"></p>
                  </div>
                  <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">active</span>
                </label>
              </template>
            </div>
          </div>

          <!-- Missing Months Display -->
          <div x-show="missingForm.selected_tenancies.length > 0 && missingMonthsData.length > 0" class="mt-4">
            <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Missing Months</h5>
            <div class="space-y-4 max-h-96 overflow-y-auto">
              <template x-for="tenancy in missingMonthsData" :key="tenancy.id">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                  <div class="flex justify-between items-center mb-3">
                    <span class="font-medium text-gray-800 dark:text-white/90" x-text="tenancy.tenant_name + ' - ' + tenancy.unit_number"></span>
                    <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400" x-text="tenancy.missing_months.length + ' missing month(s)'"></span>
                  </div>
                  <div class="space-y-2">
                    <template x-for="month in tenancy.missing_months" :key="month.value">
                      <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800/50 rounded">
                        <div class="flex items-center gap-3">
                          <input type="checkbox" x-model="tenancy.selected_months" :value="month.value" class="rounded border-gray-300">
                          <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="month.label"></span>
                        </div>
                        <div x-show="showWaterSection && tenancy.water_readings && tenancy.water_readings[month.value]" class="flex items-center gap-2">
                          <input type="number" step="0.01" x-model="tenancy.water_readings[month.value].current" @change="updateWaterReading(tenancy.id, month.value)" placeholder="Current reading" class="w-32 rounded border border-gray-300 px-2 py-1 text-xs dark:bg-gray-800">
                          <span class="text-xs text-gray-500">Charge: KES <span x-text="formatNumber(tenancy.water_readings[month.value]?.charge || 0)"></span></span>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <div x-show="missingForm.selected_tenancies.length > 0 && missingMonthsData.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            No missing months found for selected tenancies.
          </div>
        </div>

        <div class="flex items-center justify-end w-full gap-3 mt-6">
          <button @click="closeModal" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
          <button @click="generateMissingInvoices" :disabled="missingGenerating || missingForm.selected_tenancies.length === 0" class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-yellow-500 shadow-theme-xs hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
            <span x-show="!missingGenerating">Generate Missing Invoices</span>
            <span x-show="missingGenerating">Generating...</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('bulkInvoiceModal', () => ({
    isOpen: false,
    activeTab: 'create',
    formErrors: [],
    isLoading: false,
    isCheckingInvoices: false,
    missingGenerating: false,
    checkResults: null,
    activeTenanciesData: @json($mappedActiveTenancies),
    showWaterSection: false,
    
    // Bulk Create Form
    createForm: {
      invoice_type: 'monthly',
      billing_month: '',
      apply_to: 'bulk',
      tenancy_id: '',
      selected_tenancies: [],
      items: [{ id: 1, item_type: '', amount: '', description: '' }]
    },
    nextItemId: 2,
    
    // Missing Invoices Form
    missingForm: {
      selected_tenancies: []
    },
    missingMonthsData: [],
    
    init() {
      const today = new Date();
      const year = today.getFullYear();
      const month = String(today.getMonth() + 1).padStart(2, '0');
      this.createForm.billing_month = `${year}-${month}`;
      window.bulkInvoiceModal = this;
    },
    
    get createFormValid() {
      const basicValid = this.createForm.invoice_type && this.createForm.billing_month && this.createForm.items.length > 0;
      const itemsValid = this.createForm.items.every(item => item.item_type && item.amount && parseFloat(item.amount) > 0);
      if (this.createForm.apply_to === 'single') return basicValid && itemsValid && this.createForm.tenancy_id;
      if (this.createForm.apply_to === 'multiple') return basicValid && itemsValid && this.createForm.selected_tenancies.length > 0;
      return basicValid && itemsValid;
    },
    
    formatNumber(value) { 
      return parseFloat(value || 0).toFixed(2); 
    },
    
    formatMonth(monthString) { 
      if (!monthString) return ''; 
      const [year, month] = monthString.split('-'); 
      return new Date(year, month - 1).toLocaleDateString('en-US', { year: 'numeric', month: 'long' }); 
    },
    
    getTotalAmount() { 
      return this.createForm.items.reduce((total, item) => total + (parseFloat(item.amount) || 0), 0); 
    },
    
    getTenancyCount() {
      if (this.createForm.apply_to === 'bulk') return this.activeTenanciesData.length;
      if (this.createForm.apply_to === 'single') return this.createForm.tenancy_id ? 1 : 0;
      return this.createForm.selected_tenancies.length;
    },
    
    getSelectedTenancyCount() { 
      return this.createForm.selected_tenancies.length; 
    },
    
    onInvoiceTypeChange() { 
      this.createForm.items.forEach(item => { item.item_type = ''; }); 
      this.updateItemDescriptions(); 
      this.resetCheckResults(); 
    },
    
    onBillingMonthChange() { 
      this.updateItemDescriptions(); 
      this.resetCheckResults(); 
    },
    
    updateItemDescription(index) {
      const item = this.createForm.items[index];
      if (!item.item_type) { 
        item.description = ''; 
        return; 
      }
      let description = '';
      if (this.createForm.invoice_type === 'monthly') {
        const labels = { rent: 'Monthly Rent', water: 'Water Charges', service: 'Service Charge', garbage: 'Garbage Collection', security: 'Security Service', other: 'Other Charges' };
        description = labels[item.item_type] || item.item_type + ' Charges';
      } else if (this.createForm.invoice_type === 'move_in') {
        description = 'Move In Charges';
      } else if (this.createForm.invoice_type === 'move_out') {
        description = 'Move Out Charges';
      }
      if (this.createForm.billing_month) {
        description += ` for ${this.formatMonth(this.createForm.billing_month)}`;
      }
      item.description = description;
    },
    
    updateItemDescriptions() { 
      this.createForm.items.forEach((_, index) => this.updateItemDescription(index)); 
    },
    
    addItem() { 
      this.createForm.items.push({ id: this.nextItemId++, item_type: '', amount: '', description: '' }); 
      this.resetCheckResults(); 
    },
    
    removeItem(index) { 
      if (this.createForm.items.length > 1) { 
        this.createForm.items.splice(index, 1); 
        this.resetCheckResults(); 
      } 
    },
    
    toggleTenancySelection(tenancyId) {
      const index = this.createForm.selected_tenancies.indexOf(tenancyId);
      if (index === -1) {
        this.createForm.selected_tenancies.push(tenancyId);
      } else {
        this.createForm.selected_tenancies.splice(index, 1);
      }
      this.resetCheckResults();
    },
    
    selectAllTenancies() { 
      this.createForm.selected_tenancies = this.activeTenanciesData.map(t => t.id); 
      this.resetCheckResults(); 
    },
    
    clearTenancySelection() { 
      this.createForm.selected_tenancies = []; 
      this.resetCheckResults(); 
    },
    
    resetCheckResults() { 
      this.checkResults = null; 
    },
    
    async checkExistingInvoices() {
      if (!this.createForm.invoice_type || !this.createForm.billing_month) { 
        alert('Please select invoice type and billing month first'); 
        return; 
      }
      
      let tenancyIds = [];
      if (this.createForm.apply_to === 'bulk') {
        tenancyIds = this.activeTenanciesData.map(t => t.id);
      } else if (this.createForm.apply_to === 'single' && this.createForm.tenancy_id) {
        tenancyIds = [this.createForm.tenancy_id];
      } else if (this.createForm.apply_to === 'multiple' && this.createForm.selected_tenancies.length > 0) {
        tenancyIds = this.createForm.selected_tenancies;
      } else { 
        alert('Please select tenancies to check'); 
        return; 
      }
      
      this.isCheckingInvoices = true;
      try {
        const response = await fetch('{{ route("invoices.check.existing") }}', {
          method: 'POST', 
          headers: { 
            'Content-Type': 'application/json', 
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 
            'Accept': 'application/json' 
          },
          body: JSON.stringify({ 
            tenancy_ids: tenancyIds, 
            invoice_type: this.createForm.invoice_type, 
            billing_month: this.createForm.billing_month, 
            item_type: this.createForm.items[0]?.item_type 
          })
        });
        const result = await response.json();
        if (result.success) {
          this.checkResults = result;
        } else {
          alert('Error checking existing invoices: ' + (result.message || 'Unknown error'));
        }
      } catch (error) { 
        console.error('Error checking existing invoices:', error); 
        alert('An error occurred while checking existing invoices');
      } finally { 
        this.isCheckingInvoices = false; 
      }
    },
    
    async submitBulkCreate() {
      if (!this.createFormValid) { 
        alert('Please fill in all required fields'); 
        return; 
      }
      
      let tenancyIds = [];
      if (this.createForm.apply_to === 'bulk') {
        tenancyIds = this.activeTenanciesData.map(t => t.id);
      } else if (this.createForm.apply_to === 'single') {
        tenancyIds = [this.createForm.tenancy_id];
      } else {
        tenancyIds = this.createForm.selected_tenancies;
      }
      
      if (this.checkResults && this.checkResults.remaining_tenancy_ids) {
        tenancyIds = this.checkResults.remaining_tenancy_ids;
      }
      
      if (tenancyIds.length === 0) { 
        alert('No tenancies to create invoices for'); 
        return; 
      }
      
      this.isLoading = true;
      try {
        const results = [];
        for (const item of this.createForm.items) {
          for (const tenancyId of tenancyIds) {
            const response = await fetch('{{ route("invoices.bulk.create") }}', {
              method: 'POST', 
              headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 
                'Accept': 'application/json' 
              },
              body: JSON.stringify({ 
                invoice_type: this.createForm.invoice_type, 
                item_type: item.item_type, 
                amount: item.amount, 
                billing_month: this.createForm.billing_month, 
                apply_to: 'single', 
                tenancy_id: tenancyId, 
                description: item.description 
              })
            });
            results.push(await response.json());
          }
        }
        const successCount = results.filter(r => r.success).length;
        alert(`Created ${successCount} invoices successfully!`);
        this.closeModal();
        setTimeout(() => window.location.reload(), 1500);
      } catch (error) { 
        console.error('Error creating bulk invoices:', error); 
        alert('An error occurred while creating invoices');
      } finally { 
        this.isLoading = false; 
      }
    },
    
    // Missing Invoices Methods
    async loadMissingMonthsForTenancy(tenancyId) {
      if (!this.missingForm.selected_tenancies.includes(tenancyId)) {
        this.missingMonthsData = this.missingMonthsData.filter(t => t.id !== tenancyId);
        return;
      }
      
      try {
        const tenancyInfo = this.activeTenanciesData.find(t => t.id === tenancyId);
        const response = await fetch(`/tenancies/${tenancyId}/billing-history`);
        const data = await response.json();
        
        if (data.success) {
          const existingData = this.missingMonthsData.find(t => t.id === tenancyId);
          const missingMonths = (data.missing_months || []).map(m => ({ 
            value: m, 
            label: this.formatMonth(m) 
          }));
          
          if (existingData) {
            existingData.missing_months = missingMonths;
            existingData.selected_months = [];
          } else {
            this.missingMonthsData.push({ 
              id: tenancyId, 
              tenant_name: tenancyInfo?.tenant_name || 'Unknown',
              unit_number: tenancyInfo?.unit_number || '',
              missing_months: missingMonths, 
              selected_months: [], 
              water_readings: {} 
            });
          }
        }
      } catch (error) { 
        console.error('Error loading missing months:', error); 
      }
    },
    
    selectAllMissingTenancies() { 
      this.missingForm.selected_tenancies = this.activeTenanciesData.map(t => t.id);
      this.activeTenanciesData.forEach(t => this.loadMissingMonthsForTenancy(t.id));
    },
    
    clearMissingTenancySelection() { 
      this.missingForm.selected_tenancies = []; 
      this.missingMonthsData = []; 
    },
    
    updateWaterReading(tenancyId, month) {
      const tenancy = this.missingMonthsData.find(t => t.id === tenancyId);
      if (tenancy && tenancy.water_readings && tenancy.water_readings[month]) {
        const reading = tenancy.water_readings[month];
        reading.consumption = Math.max(0, reading.current - (reading.previous || 0));
        reading.charge = reading.consumption * 10;
      }
    },
    
    async generateMissingInvoices() {
      this.missingGenerating = true;
      try {
        const invoicesToGenerate = [];
        for (const tenancy of this.missingMonthsData) {
          if (tenancy.selected_months && tenancy.selected_months.length > 0) {
            invoicesToGenerate.push({ 
              tenancy_id: tenancy.id, 
              months: tenancy.selected_months 
            });
          }
        }
        
        if (invoicesToGenerate.length === 0) { 
          alert('No months selected'); 
          this.missingGenerating = false; 
          return; 
        }
        
        alert(`Generating invoices for ${invoicesToGenerate.length} tenancies...`);
        this.closeModal();
        setTimeout(() => window.location.reload(), 2000);
      } catch (error) { 
        console.error('Error generating missing invoices:', error); 
        alert('An error occurred');
      } finally { 
        this.missingGenerating = false; 
      }
    },
    
    openModal(tab = 'create') { 
      this.isOpen = true; 
      this.activeTab = tab;
      document.body.style.overflow = 'hidden'; 
    },
    
    closeModal() { 
      this.isOpen = false; 
      this.formErrors = []; 
      document.body.style.overflow = ''; 
    }
  }));
});
</script>