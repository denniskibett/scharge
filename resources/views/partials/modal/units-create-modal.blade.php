<!-- CREATE UNIT SLIDEOVER MODAL with Mass Add Support and Utility Charges -->
<div x-data="unitCreateModal" x-init="init()">
  <!-- Backdrop with 50% opacity and frost effect -->
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
       class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white dark:bg-gray-900 shadow-2xl z-99999 overflow-y-auto">
    <div class="p-6 lg:p-10">
      <!-- close btn -->
      <button
        @click="closeModal()"
        class="group absolute right-3 top-3 z-99999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
      >
        <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
        </svg>
      </button>

      <form @submit.prevent="submitForm()" x-ref="createUnitForm" novalidate>
        <div class="flex items-center justify-between mb-6">
          <h4 class="text-lg font-medium text-gray-800 dark:text-white/90">
            Add New Unit(s)
          </h4>
          
          <!-- Mode Toggle -->
          <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
            <button
              type="button"
              @click="mode = 'single'"
              :class="mode === 'single' ? 'bg-white text-gray-800 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
              class="rounded-md px-4 py-2 text-sm font-medium transition-all"
            >
              Single Unit
            </button>
            <button
              type="button"
              @click="mode = 'mass'"
              :class="mode === 'mass' ? 'bg-white text-gray-800 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
              class="rounded-md px-4 py-2 text-sm font-medium transition-all"
            >
              Mass Add
            </button>
          </div>
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

        <!-- Single Unit Mode -->
        <div x-show="mode === 'single'" x-cloak>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <!-- Estate (Hidden if pre-selected) -->
            <div x-show="!preSelectedEstateId">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estate *</label>
              <select 
                x-model="formData.estate_id"
                name="estate_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Estate</option>
                @foreach($estates ?? [] as $estate)
                  <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                @endforeach
              </select>
            </div>

            <!-- Unit Number -->
            <div :class="preSelectedEstateId ? 'md:col-span-2' : ''">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Unit Number *</label>
              <input 
                x-model="formData.unit_number"
                type="text"
                name="unit_number"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="e.g., HAR-101"
              />
            </div>

            <!-- Unit Type -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Unit Type *</label>
              <select 
                x-model="formData.unit_type"
                name="unit_type"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Type</option>
                <option value="Studio">Studio</option>
                <option value="Bedsitter">Bedsitter</option>
                <option value="One Bedroom">One Bedroom</option>
                <option value="Two Bedroom">Two Bedroom</option>
                <option value="Three Bedroom">Three Bedroom</option>
                <option value="Apartment">Apartment</option>
                <option value="House">House</option>
                <option value="Office">Office</option>
                <option value="Shop">Shop</option>
              </select>
            </div>

            <!-- Rent Amount -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Monthly Rent *</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input 
                  x-model="formData.rent_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  name="rent_amount"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Water Charge -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Water Charge (KES)</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input 
                  x-model="formData.water_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  name="water_charge"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Service Charge -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Service Charge (KES)</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input 
                  x-model="formData.service_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  name="service_charge"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Garbage Charge -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Garbage Charge (KES)</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input 
                  x-model="formData.garbage_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  name="garbage_charge"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Security Charge -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Security Charge (KES)</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input 
                  x-model="formData.security_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  name="security_charge"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Status -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status *</label>
              <select 
                x-model="formData.status"
                name="status"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="vacant">Vacant</option>
                <option value="occupied">Occupied</option>
              </select>
            </div>

            <!-- Total Summary -->
            <div class="col-span-1 md:col-span-2">
              <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Monthly Charges Summary</h5>
                <div class="grid grid-cols-2 gap-2 text-sm">
                  <div class="text-gray-600 dark:text-gray-400">Rent:</div>
                  <div class="text-right font-medium text-gray-800 dark:text-gray-200">
                    KES <span x-text="parseFloat(formData.rent_amount || 0).toFixed(2)"></span>
                  </div>
                  
                  <div class="text-gray-600 dark:text-gray-400">Water:</div>
                  <div class="text-right font-medium text-gray-800 dark:text-gray-200">
                    KES <span x-text="parseFloat(formData.water_charge || 0).toFixed(2)"></span>
                  </div>
                  
                  <div class="text-gray-600 dark:text-gray-400">Service Charge:</div>
                  <div class="text-right font-medium text-gray-800 dark:text-gray-200">
                    KES <span x-text="parseFloat(formData.service_charge || 0).toFixed(2)"></span>
                  </div>
                  
                  <div class="text-gray-600 dark:text-gray-400">Garbage:</div>
                  <div class="text-right font-medium text-gray-800 dark:text-gray-200">
                    KES <span x-text="parseFloat(formData.garbage_charge || 0).toFixed(2)"></span>
                  </div>
                  
                  <div class="text-gray-600 dark:text-gray-400">Security:</div>
                  <div class="text-right font-medium text-gray-800 dark:text-gray-200">
                    KES <span x-text="parseFloat(formData.security_charge || 0).toFixed(2)"></span>
                  </div>
                  
                  <div class="border-t border-gray-300 dark:border-gray-700 mt-2 pt-2 font-semibold text-gray-800 dark:text-gray-200">Total Monthly:</div>
                  <div class="border-t border-gray-300 dark:border-gray-700 mt-2 pt-2 text-right font-semibold text-blue-600 dark:text-blue-400">
                    KES <span x-text="(parseFloat(formData.rent_amount || 0) + parseFloat(formData.water_charge || 0) + parseFloat(formData.service_charge || 0) + parseFloat(formData.garbage_charge || 0) + parseFloat(formData.security_charge || 0)).toFixed(2)"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Mass Add Mode -->
        <div x-show="mode === 'mass'" x-cloak>
          <div class="mb-6 p-4 bg-blue-50 rounded-lg dark:bg-blue-900/20">
            <p class="text-sm text-blue-700 dark:text-blue-300">
              <strong>Tip:</strong> You can add multiple units at once. Each unit will be created with the same settings including utility charges.
            </p>
          </div>

          <!-- Units to Add -->
          <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                Units to Add *
              </label>
              <button type="button" @click="addUnitField()" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                + Add Another Unit
              </button>
            </div>
            
            <div class="space-y-3" x-ref="unitsContainer">
              <template x-for="(unit, index) in units" :key="index">
                <div class="flex items-center gap-3">
                  <div class="flex-1">
                    <input
                      type="text"
                      x-model="unit.unit_number"
                      placeholder="Unit number (e.g., 101, A1, etc.)"
                      class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                  </div>
                  <button 
                    type="button" 
                    @click="removeUnitField(index)"
                    class="text-red-600 hover:text-red-800"
                    :disabled="units.length === 1"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </template>
            </div>
          </div>

          <!-- Common Fields for Mass Add -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <div class="col-span-1" x-show="!preSelectedEstateId">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estate *</label>
              <select
                x-model="commonFields.estate_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Estate</option>
                @foreach($estates ?? [] as $estate)
                  <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Unit Type *
              </label>
              <select
                x-model="commonFields.unit_type"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Type</option>
                <option value="Studio">Studio</option>
                <option value="Bedsitter">Bedsitter</option>
                <option value="One Bedroom">One Bedroom</option>
                <option value="Two Bedroom">Two Bedroom</option>
                <option value="Three Bedroom">Three Bedroom</option>
                <option value="Apartment">Apartment</option>
                <option value="House">House</option>
                <option value="Office">Office</option>
                <option value="Shop">Shop</option>
              </select>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Monthly Rent *
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input
                  type="number"
                  x-model="commonFields.rent_amount"
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
              </div>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Water Charge (KES)
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input
                  type="number"
                  x-model="commonFields.water_charge"
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
              </div>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Service Charge (KES)
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input
                  type="number"
                  x-model="commonFields.service_charge"
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
              </div>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Garbage Charge (KES)
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input
                  type="number"
                  x-model="commonFields.garbage_charge"
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
              </div>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Security Charge (KES)
              </label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">KES</span>
                </div>
                <input
                  type="number"
                  x-model="commonFields.security_charge"
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
              </div>
            </div>

            <div class="col-span-1">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Status *
              </label>
              <select
                x-model="commonFields.status"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="vacant">Vacant</option>
                <option value="occupied">Occupied</option>
              </select>
            </div>

            <div class="col-span-1 md:col-span-2">
              <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-800">
                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Summary</h5>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  <p>Will create <span x-text="units.length"></span> units with:</p>
                  <ul class="mt-2 space-y-1">
                    <li>Type: <span x-text="commonFields.unit_type || 'Not selected'" class="font-medium"></span></li>
                    <li>Rent: KES <span x-text="parseFloat(commonFields.rent_amount || 0).toFixed(2)" class="font-medium"></span></li>
                    <li>Water: KES <span x-text="parseFloat(commonFields.water_charge || 0).toFixed(2)" class="font-medium"></span></li>
                    <li>Service: KES <span x-text="parseFloat(commonFields.service_charge || 0).toFixed(2)" class="font-medium"></span></li>
                    <li>Garbage: KES <span x-text="parseFloat(commonFields.garbage_charge || 0).toFixed(2)" class="font-medium"></span></li>
                    <li>Security: KES <span x-text="parseFloat(commonFields.security_charge || 0).toFixed(2)" class="font-medium"></span></li>
                    <li class="pt-2 font-semibold text-gray-800 dark:text-gray-200">
                      Total Monthly: KES <span x-text="(parseFloat(commonFields.rent_amount || 0) + parseFloat(commonFields.water_charge || 0) + parseFloat(commonFields.service_charge || 0) + parseFloat(commonFields.garbage_charge || 0) + parseFloat(commonFields.security_charge || 0)).toFixed(2)"></span>
                    </li>
                    <li>Status: <span x-text="commonFields.status ? commonFields.status.charAt(0).toUpperCase() + commonFields.status.slice(1) : 'Vacant'" class="font-medium"></span></li>
                  </ul>
                </div>
              </div>
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
            <span x-show="!loading">
              <span x-show="mode === 'single'">Save Unit</span>
              <span x-show="mode === 'mass'">Create <span x-text="units.length"></span> Units</span>
            </span>
            <span x-show="loading">Processing...</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('unitCreateModal', () => ({
    isOpen: false,
    mode: 'single', // 'single' or 'mass'
    preSelectedEstateId: null,
    
    // Single unit form data
    formData: {
      estate_id: '',
      unit_number: '',
      unit_type: '',
      rent_amount: '',
      water_charge: '',
      service_charge: '',
      garbage_charge: '',
      security_charge: '',
      status: 'vacant'
    },
    
    // Mass add form data
    units: [{ unit_number: '' }],
    commonFields: {
      estate_id: '',
      unit_type: '',
      rent_amount: '',
      water_charge: '',
      service_charge: '',
      garbage_charge: '',
      security_charge: '',
      status: 'vacant'
    },
    
    formErrors: [],
    loading: false,
    
    init() {
      window.unitCreateModal = this;
    },
    
    openModal(estateId = null) {
      this.preSelectedEstateId = estateId;
      this.resetForm();
      this.isOpen = true;
      document.body.style.overflow = 'hidden';
      
      // If estate is pre-selected, set the estate_id
      if (estateId) {
        this.formData.estate_id = estateId;
        this.commonFields.estate_id = estateId;
      }
    },
    
    closeModal() {
      this.isOpen = false;
      this.formErrors = [];
      this.loading = false;
      document.body.style.overflow = '';
    },
    
    resetForm() {
      this.mode = 'single';
      this.formData = {
        estate_id: this.preSelectedEstateId || '',
        unit_number: '',
        unit_type: '',
        rent_amount: '',
        water_charge: '',
        service_charge: '',
        garbage_charge: '',
        security_charge: '',
        status: 'vacant'
      };
      this.units = [{ unit_number: '' }];
      this.commonFields = {
        estate_id: this.preSelectedEstateId || '',
        unit_type: '',
        rent_amount: '',
        water_charge: '',
        service_charge: '',
        garbage_charge: '',
        security_charge: '',
        status: 'vacant'
      };
      this.formErrors = [];
    },
    
    addUnitField() {
      this.units.push({ unit_number: '' });
    },
    
    removeUnitField(index) {
      if (this.units.length > 1) {
        this.units.splice(index, 1);
      }
    },
    
    validateSingleForm() {
      const errors = [];
      
      if (!this.preSelectedEstateId && !this.formData.estate_id) {
        errors.push('Please select an estate');
      }
      
      if (!this.formData.unit_number || this.formData.unit_number.trim() === '') {
        errors.push('Please enter a unit number');
      }
      
      if (!this.formData.unit_type) {
        errors.push('Please select a unit type');
      }
      
      if (!this.formData.rent_amount || parseFloat(this.formData.rent_amount) <= 0) {
        errors.push('Please enter a valid rent amount greater than 0');
      }
      
      // Validate utilities (optional, but must be numeric if provided)
      if (this.formData.water_charge && isNaN(parseFloat(this.formData.water_charge))) {
        errors.push('Water charge must be a valid number');
      }
      
      if (this.formData.service_charge && isNaN(parseFloat(this.formData.service_charge))) {
        errors.push('Service charge must be a valid number');
      }
      
      if (this.formData.garbage_charge && isNaN(parseFloat(this.formData.garbage_charge))) {
        errors.push('Garbage charge must be a valid number');
      }
      
      if (this.formData.security_charge && isNaN(parseFloat(this.formData.security_charge))) {
        errors.push('Security charge must be a valid number');
      }
      
      return errors;
    },
    
    validateMassForm() {
      const errors = [];
      
      if (!this.preSelectedEstateId && !this.commonFields.estate_id) {
        errors.push('Please select an estate');
      }
      
      const invalidUnits = this.units.filter(unit => !unit.unit_number.trim());
      if (invalidUnits.length > 0) {
        errors.push('Please fill in all unit numbers');
      }
      
      if (!this.commonFields.unit_type) {
        errors.push('Please select a unit type');
      }
      
      if (!this.commonFields.rent_amount || parseFloat(this.commonFields.rent_amount) <= 0) {
        errors.push('Please enter a valid rent amount greater than 0');
      }
      
      // Validate utilities (optional, but must be numeric if provided)
      if (this.commonFields.water_charge && isNaN(parseFloat(this.commonFields.water_charge))) {
        errors.push('Water charge must be a valid number');
      }
      
      if (this.commonFields.service_charge && isNaN(parseFloat(this.commonFields.service_charge))) {
        errors.push('Service charge must be a valid number');
      }
      
      if (this.commonFields.garbage_charge && isNaN(parseFloat(this.commonFields.garbage_charge))) {
        errors.push('Garbage charge must be a valid number');
      }
      
      if (this.commonFields.security_charge && isNaN(parseFloat(this.commonFields.security_charge))) {
        errors.push('Security charge must be a valid number');
      }
      
      return errors;
    },
    
    async submitForm() {
      this.loading = true;
      this.formErrors = [];
      
      try {
        if (this.mode === 'single') {
          // Validate single form
          const errors = this.validateSingleForm();
          if (errors.length > 0) {
            this.formErrors = errors;
            this.loading = false;
            const modalContent = document.querySelector('.overflow-y-auto');
            if (modalContent) modalContent.scrollTop = 0;
            return;
          }
          
          // Submit single unit
          const response = await fetch('/units', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
              'Accept': 'application/json'
            },
            body: JSON.stringify(this.formData)
          });
          
          const data = await response.json();
          
          if (response.ok) {
            this.closeModal();
            if (window.successModal) {
              window.successModal.show('Unit Created', `Unit ${this.formData.unit_number} has been created successfully.`);
            } else {
              alert(`Success: Unit ${this.formData.unit_number} created successfully.`);
            }
          } else {
            if (data.errors) {
              const errorMessages = Object.values(data.errors).flat();
              if (window.errorModal) {
                window.errorModal.show('Validation Error', 'Please check the following errors:', errorMessages);
              } else {
                this.formErrors = errorMessages;
              }
            } else {
              if (window.errorModal) {
                window.errorModal.show('Error', data.message || 'Failed to create unit');
              } else {
                this.formErrors = [data.message || 'Failed to create unit'];
              }
            }
            const modalContent = document.querySelector('.overflow-y-auto');
            if (modalContent) modalContent.scrollTop = 0;
          }
        } else {
          // Validate mass form
          const errors = this.validateMassForm();
          if (errors.length > 0) {
            this.formErrors = errors;
            this.loading = false;
            const modalContent = document.querySelector('.overflow-y-auto');
            if (modalContent) modalContent.scrollTop = 0;
            return;
          }
          
          // Submit multiple units
          let created = 0;
          let failed = [];
          let failedDetails = [];
          
          for (let unit of this.units) {
            try {
              const response = await fetch('/units', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                  'Accept': 'application/json'
                },
                body: JSON.stringify({
                  estate_id: this.commonFields.estate_id,
                  unit_number: unit.unit_number.trim(),
                  unit_type: this.commonFields.unit_type,
                  rent_amount: this.commonFields.rent_amount,
                  water_charge: this.commonFields.water_charge || 0,
                  service_charge: this.commonFields.service_charge || 0,
                  garbage_charge: this.commonFields.garbage_charge || 0,
                  security_charge: this.commonFields.security_charge || 0,
                  status: this.commonFields.status
                })
              });
              
              if (response.ok) {
                created++;
              } else {
                const data = await response.json();
                failed.push(`${unit.unit_number}`);
                failedDetails.push(`${unit.unit_number}: ${data.message || 'Unknown error'}`);
              }
            } catch (error) {
              failed.push(`${unit.unit_number}`);
              failedDetails.push(`${unit.unit_number}: ${error.message}`);
            }
          }
          
          this.closeModal();
          
          if (created > 0) {
            let message = `Successfully created ${created} unit(s).`;
            if (failed.length > 0) {
              message += ` Failed to create ${failed.length} unit(s).`;
            }
            if (window.successModal) {
              window.successModal.show('Units Created', message);
            } else {
              alert(`Success: ${message}`);
            }
            
            if (failedDetails.length > 0 && window.errorModal) {
              setTimeout(() => {
                window.errorModal.show('Partial Success', `Some units failed to create:`, failedDetails);
              }, 2000);
            }
          } else if (failedDetails.length > 0) {
            if (window.errorModal) {
              window.errorModal.show('Error', 'Failed to create units', failedDetails);
            } else {
              alert(`Error: Failed to create units\n\n${failedDetails.join('\n')}`);
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
        this.loading = false;
      } finally {
        this.loading = false;
        if (this.formErrors.length === 0) {
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        }
      }
    }
  }));
});
</script>