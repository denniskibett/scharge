<!-- CREATE TENANCY SLIDEOVER MODAL -->
<div x-data="tenancyCreateModal" x-init="init()">
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
      class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
      style="width: 38rem; max-width: calc(100% - 2rem);">
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

      <form @submit.prevent="submitForm">
        @csrf
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
          Add New Tenancy
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
          <!-- Tenant Selection -->
          <div class="col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Tenant *
            </label>
            <div class="space-y-3">
              <select
                x-model="form.tenant_selection"
                @change="onTenantSelectionChange"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Tenant</option>
                <option value="existing">Existing Tenant</option>
                <option value="new">Create New Tenant</option>
              </select>
              
              <!-- Existing Tenant Selection -->
              <div x-show="form.tenant_selection === 'existing'" x-cloak>
                <select
                  x-model="form.tenant_id"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                >
                  <option value="">Select Existing Tenant</option>
                  @foreach($availableUsers ?? [] as $user)
                    <option value="{{ $user['tenant_id'] }}">
                      {{ $user['name'] }} ({{ $user['phone'] ?? 'No Phone' }})
                      @if(isset($user['has_ended_tenancy']) && $user['has_ended_tenancy'])
                        - Previous Tenant
                      @endif
                    </option>
                  @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Showing tenants who don't have an active tenancy
                </p>
              </div>

              <!-- New Tenant Fields -->
              <div x-show="form.tenant_selection === 'new'" class="space-y-3" x-cloak>
                <input
                  type="text"
                  x-model="form.new_tenant_name"
                  placeholder="Tenant Full Name"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                <input
                  type="text"
                  x-model="form.new_tenant_phone"
                  placeholder="Phone Number"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                <input
                  type="email"
                  x-model="form.new_tenant_email"
                  placeholder="Email (Optional)"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
              </div>
            </div>
          </div>

          <!-- Unit Selection -->
          <div class="col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Unit *
            </label>
            <select
              x-model="form.unit_id"
              @change="onUnitChange"
              required
              class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
              <option value="">Select Unit</option>
              @foreach($vacantUnits ?? [] as $unit)
                <option value="{{ $unit['id'] }}" data-rent="{{ $unit['rent_amount'] ?? 0 }}">
                  {{ $unit['unit_number'] }} - {{ $unit['estate_name'] ?? 'No Estate' }} ({{ $unit['unit_type'] }})
                </option>
              @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Only vacant units are shown
            </p>
          </div>

          <!-- Move-in Date -->
          <div class="col-span-1">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Move-in Date *
            </label>
            <input
              type="date"
              x-model="form.move_in_date"
              required
              class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
          </div>

          <!-- Rent Amount (Auto-filled from unit) -->
          <div class="col-span-1">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Rent Amount (KES)
            </label>
            <input
              type="text"
              x-model="form.rent_amount"
              readonly
              class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
            />
          </div>

          <!-- Notes -->
          <div class="col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Notes (Optional)
            </label>
            <textarea
              x-model="form.notes"
              rows="3"
              class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              placeholder="Any additional notes about this tenancy..."
            ></textarea>
          </div>
        </div>

        <!-- Summary -->
        <div class="mt-4 p-3 bg-blue-50 rounded-lg dark:bg-blue-900/20" x-show="form.unit_id && form.move_in_date">
          <p class="text-sm text-blue-700 dark:text-blue-300">
            <strong>Summary:</strong> Creating tenancy for 
            <span x-text="getTenantSummary()"></span>
            in unit <span x-text="getUnitSummary()"></span>
            starting <span x-text="form.move_in_date"></span>
          </p>
        </div>

        <div class="flex items-center justify-end w-full gap-3 mt-6">
          <button
            @click="closeModal"
            type="button"
            class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="loading || !isFormValid"
            class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
          >
            <span x-show="!loading">Create Tenancy</span>
            <span x-show="loading">Creating...</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('tenancyCreateModal', () => ({
    isOpen: false,
    form: {
      tenant_selection: '',
      tenant_id: '',
      new_tenant_name: '',
      new_tenant_phone: '',
      new_tenant_email: '',
      unit_id: '',
      move_in_date: '',
      rent_amount: '',
      notes: ''
    },
    formErrors: [],
    loading: false,
    units: @json($vacantUnits ?? []),
    
    init() {
      window.tenancyCreateModal = this;
      // Set default date to today
      const today = new Date().toISOString().split('T')[0];
      this.form.move_in_date = today;
    },
    
    openModal() {
      this.isOpen = true;
      this.resetForm();
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isOpen = false;
      this.formErrors = [];
      this.loading = false;
      document.body.style.overflow = '';
    },
    
    resetForm() {
      const today = new Date().toISOString().split('T')[0];
      this.form = {
        tenant_selection: '',
        tenant_id: '',
        new_tenant_name: '',
        new_tenant_phone: '',
        new_tenant_email: '',
        unit_id: '',
        move_in_date: today,
        rent_amount: '',
        notes: ''
      };
      this.formErrors = [];
      this.loading = false;
    },
    
    get isFormValid() {
      // Check tenant selection
      if (!this.form.tenant_selection) return false;
      
      if (this.form.tenant_selection === 'existing' && !this.form.tenant_id) return false;
      
      if (this.form.tenant_selection === 'new') {
        if (!this.form.new_tenant_name || !this.form.new_tenant_name.trim()) return false;
        if (!this.form.new_tenant_phone || !this.form.new_tenant_phone.trim()) return false;
      }
      
      // Check unit and date
      if (!this.form.unit_id) return false;
      if (!this.form.move_in_date) return false;
      
      return true;
    },
    
    onTenantSelectionChange() {
      if (this.form.tenant_selection !== 'existing') {
        this.form.tenant_id = '';
        this.form.new_tenant_name = '';
        this.form.new_tenant_phone = '';
        this.form.new_tenant_email = '';
      }
    },
    
    onUnitChange() {
      // Find selected unit and update rent amount
      const selectedUnit = this.units.find(u => u.id == this.form.unit_id);
      if (selectedUnit) {
        this.form.rent_amount = selectedUnit.rent_amount || 0;
      }
    },
    
    getTenantSummary() {
      if (this.form.tenant_selection === 'existing' && this.form.tenant_id) {
        const selectedOption = document.querySelector(`select[x-model="form.tenant_id"] option[value="${this.form.tenant_id}"]`);
        return selectedOption ? selectedOption.text.split('(')[0].trim() : 'selected tenant';
      } else if (this.form.tenant_selection === 'new' && this.form.new_tenant_name) {
        return this.form.new_tenant_name;
      }
      return 'tenant';
    },
    
    getUnitSummary() {
      const selectedUnit = this.units.find(u => u.id == this.form.unit_id);
      return selectedUnit ? selectedUnit.unit_number : 'selected unit';
    },
    
    validateForm() {
      this.formErrors = [];
      
      // Validate tenant selection
      if (!this.form.tenant_selection) {
        this.formErrors.push('Please select tenant option');
      } else if (this.form.tenant_selection === 'existing' && !this.form.tenant_id) {
        this.formErrors.push('Please select an existing tenant');
      } else if (this.form.tenant_selection === 'new') {
        if (!this.form.new_tenant_name || !this.form.new_tenant_name.trim()) {
          this.formErrors.push('Please enter tenant name');
        }
        if (!this.form.new_tenant_phone || !this.form.new_tenant_phone.trim()) {
          this.formErrors.push('Please enter tenant phone');
        }
        if (this.form.new_tenant_email && !this.isValidEmail(this.form.new_tenant_email)) {
          this.formErrors.push('Please enter a valid email address');
        }
      }
      
      if (!this.form.unit_id) {
        this.formErrors.push('Please select a unit');
      }
      
      if (!this.form.move_in_date) {
        this.formErrors.push('Please select move-in date');
      }
      
      return this.formErrors.length === 0;
    },
    
    isValidEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    async submitForm() {
      if (!this.validateForm()) {
        // Scroll to top to show errors
        const modalContent = this.$el.closest('.overflow-y-auto');
        if (modalContent) {
          modalContent.scrollTop = 0;
        }
        return;
      }
      
      this.loading = true;
      
      try {
        // Prepare the data based on tenant selection
        const postData = {
          unit_id: this.form.unit_id,
          move_in_date: this.form.move_in_date,
          notes: this.form.notes
        };
        
        if (this.form.tenant_selection === 'existing') {
          postData.tenant_id = this.form.tenant_id;
        } else {
          postData.new_tenant_name = this.form.new_tenant_name;
          postData.new_tenant_phone = this.form.new_tenant_phone;
          if (this.form.new_tenant_email) {
            postData.new_tenant_email = this.form.new_tenant_email;
          }
        }
        
        const response = await fetch('{{ route("tenancies.store") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: JSON.stringify(postData)
        });
        
        const data = await response.json();
        
        if (response.ok) {
          this.closeModal();
          
          // Show success message
          if (window.successModal) {
            window.successModal.show(
              'Success!', 
              data.message || 'Tenancy created successfully'
            );
          } else {
            alert('Tenancy created successfully!');
          }
          
          // Reload after a short delay
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          this.formErrors = [data.message || 'Failed to create tenancy'];
          
          // Scroll to show errors
          const modalContent = this.$el.closest('.overflow-y-auto');
          if (modalContent) {
            modalContent.scrollTop = 0;
          }
        }
      } catch (error) {
        console.error('Error:', error);
        this.formErrors = ['An error occurred. Please try again.'];
      } finally {
        this.loading = false;
      }
    }
  }));
});
</script>