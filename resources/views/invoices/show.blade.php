@extends('layouts.app')

@section('content')
<div x-data="invoiceItemsPage" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <!-- Header -->
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Invoice Items
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Manage items for Invoice #{{ $invoice->id }} - {{ optional($invoice->tenancy->tenant)->name ?? 'N/A' }}
        </p>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <!-- Search -->
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/>
            </svg>
          </span>
          <input 
            x-model="searchTerm" 
            @input.debounce.300ms="filterItems()"
            type="text" 
            placeholder="Search items..." 
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        
        <!-- Add Item Button -->
        <button 
          @click="openModal('create')"
          class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 whitespace-nowrap text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Add Item
        </button>
      </div>
    </div>
    
    <!-- Items Table -->
    <div class="custom-scrollbar overflow-x-auto">
      <table class="w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Item Type
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Description
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Amount
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              <div class="relative">
                <span class="sr-only">Actions</span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          <template x-if="filteredItems.length === 0">
            <tr>
              <td colspan="4" class="p-4 text-center">
                <span class="text-sm text-gray-500 dark:text-gray-400">No items found.</span>
              </td>
            </tr>
          </template>
          
          <template x-for="item in filteredItems" :key="item.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap">
                <span :class="getItemTypeColor(item.item_type)" class="text-theme-xs rounded-full px-2 py-0.5 font-medium" x-text="capitalize(item.item_type)"></span>
              </td>
              <td class="p-4">
                <span class="text-sm text-gray-700 dark:text-gray-400" x-text="item.description || '-'"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-400" x-text="formatCurrency(item.amount)"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <button 
                    @click="openModal('edit', item)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-yellow-500 hover:bg-yellow-50 hover:text-yellow-700 dark:text-yellow-400 dark:hover:bg-yellow-500/5 dark:hover:text-yellow-300">
                    Edit
                  </button>
                  <form :action="`/invoices/${item.id}`" method="POST" class="inline">
                    @csrf @method('DELETE')
                    <button 
                      type="submit" 
                      @click.prevent="confirmDelete(item)"
                      class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-red-500 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/5 dark:hover:text-red-300">
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    
    <!-- Summary Footer -->
    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Total of
            <span class="text-gray-800 dark:text-white/90" x-text="filteredItems.length"></span>
            items
          </span>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            Subtotal:
            <span class="ml-2 font-medium text-gray-800 dark:text-white/90" x-text="formatCurrency(calculateSubtotal())"></span>
          </div>
          <div class="text-sm font-semibold text-green-600 dark:text-green-400">
            Total:
            <span class="ml-2">{{ \App\Helpers\SystemHelper::currencySymbol() }}{{ number_format($invoice->total_amount, 2) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODALS -->
  <div x-data="modalController" x-init="init()">
    <!-- CREATE ITEM MODAL -->
    <div x-show="isOpen && modalType === 'create'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
      <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
      <div 
        @click.outside="closeModal()"
        class="relative w-full max-w-[600px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8 max-h-[90vh] overflow-y-auto"
      >
        <!-- close btn -->
        <button
          @click="closeModal()"
          class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
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

        <form action="{{ route('invoices.store') }}" method="POST">
          @csrf
          <!-- Hidden invoice_id field -->
          <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
          
          <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
            Add Invoice Item
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

          <div class="space-y-5">
            <!-- Invoice Info (readonly) -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Invoice</label>
              <div class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 flex items-center">
                #{{ $invoice->id }} - {{ optional($invoice->tenancy->tenant)->name ?? 'N/A' }} ({{ optional($invoice->tenancy->unit)->unit_number ?? 'No Unit' }})
              </div>
            </div>
            
            <!-- Item Type -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Item Type *</label>
              <select 
                x-model="formData.item_type"
                name="item_type"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Item Type</option>
                <option value="rent">Rent</option>
                <option value="utility">Utility</option>
                <option value="service_charge">Service Charge</option>
                <option value="internet">Internet</option>
                <option value="water">Water</option>
                <option value="power">Power/Electricity</option>
                <option value="security">Security</option>
                <option value="garbage">Garbage</option>
                <option value="cleaning">Cleaning</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
            
            <!-- Description -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
              <input 
                x-model="formData.description"
                type="text"
                name="description"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="e.g., Monthly Rent, Water Bill, etc."
              />
            </div>
            
            <!-- Amount -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">{{ \App\Helpers\SystemHelper::currencySymbol() }}</span>
                </div>
                <input 
                  x-model="formData.amount"
                  @blur="formatAmount()"
                  type="number"
                  step="0.01"
                  min="0.01"
                  name="amount"
                  required
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
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
              class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 sm:w-auto"
            >
              Add Item
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT ITEM MODAL -->
    <div x-show="isOpen && modalType === 'edit'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
      <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"></div>
      <div 
        @click.outside="closeModal()"
        class="relative w-full max-w-[600px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8 max-h-[90vh] overflow-y-auto"
      >
        <!-- close btn -->
        <button
          @click="closeModal()"
          class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
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

        <form :action="`/invoice-items/${currentItem?.id}`" method="POST">
          @csrf @method('PUT')
          <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
            Edit Item
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

          <div class="space-y-5">
            <!-- Invoice Info (readonly) -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Invoice</label>
              <div class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 flex items-center">
                <span x-text="getInvoiceLabel(currentItem?.invoice_id)"></span>
              </div>
            </div>
            
            <!-- Item Type -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Item Type *</label>
              <select 
                x-model="formData.item_type"
                name="item_type"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Item Type</option>
                <option value="rent">Rent</option>
                <option value="utility">Utility</option>
                <option value="service_charge">Service Charge</option>
                <option value="internet">Internet</option>
                <option value="water">Water</option>
                <option value="power">Power/Electricity</option>
                <option value="security">Security</option>
                <option value="garbage">Garbage</option>
                <option value="cleaning">Cleaning</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
            
            <!-- Description -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
              <input 
                x-model="formData.description"
                type="text"
                name="description"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                placeholder="e.g., Monthly Rent, Water Bill, etc."
              />
            </div>
            
            <!-- Amount -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">{{ \App\Helpers\SystemHelper::currencySymbol() }}</span>
                </div>
                <input 
                  x-model="formData.amount"
                  @blur="formatAmount()"
                  type="number"
                  step="0.01"
                  min="0.01"
                  name="amount"
                  required
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
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
              class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 sm:w-auto"
            >
              Update Item
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Define Alpine components inline to ensure they're available when the page loads
document.addEventListener('alpine:init', () => {
  // Main invoice items page controller
  Alpine.data('invoiceItemsPage', () => ({
    // Data - Passed from controller
    items: @json($invoice->items),
    
    invoices: @json($invoices ?? []), // Pass invoices from controller if needed
    
    searchTerm: '',
    filteredItems: [],
    
    // Initialize
    init() {
      this.filteredItems = this.items;
      console.log('Invoice items loaded:', this.items.length);
    },
    
    // Methods
    filterItems() {
      if (!this.searchTerm.trim()) {
        this.filteredItems = this.items;
        return;
      }
      
      const term = this.searchTerm.toLowerCase();
      this.filteredItems = this.items.filter(item => {
        return Object.values(item).some(value => 
          String(value).toLowerCase().includes(term)
        );
      });
    },
    
    calculateSubtotal() {
      return this.filteredItems.reduce((sum, item) => sum + parseFloat(item.amount), 0);
    },
    
    formatCurrency(amount) {
      const symbol = "{{ \App\Helpers\SystemHelper::currencySymbol() }} "; // Blade inject
      if (!amount) return symbol + "0.00";
      // Add commas to the integer part
      return symbol + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    
    capitalize(text) {
      if (!text) return '';
      return text.charAt(0).toUpperCase() + text.slice(1);
    },
    
    getItemTypeColor(itemType) {
      switch(itemType) {
        case 'rent':
          return 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400';
        case 'utility':
          return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'service_charge':
          return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
        case 'internet':
        case 'water':
        case 'power':
          return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/20 dark:text-cyan-400';
        case 'security':
        case 'garbage':
        case 'cleaning':
          return 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400';
        default:
          return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
      }
    },
    
    confirmDelete(item) {
      if (confirm(`Are you sure you want to delete "${item.description || item.item_type}" item?`)) {
        // Submit the form
        const form = document.querySelector(`form[action*="/invoices/${item.id}"]`);
        if (form) {
          form.submit();
        }
      }
    },
    
    openModal(type, item = null) {
      if (window.modalController) {
        window.modalController.openModal(type, item);
      }
    }
  }));
  
  // Modal controller
  Alpine.data('modalController', () => ({
    isOpen: false,
    modalType: null,
    modalTitle: '',
    currentItem: null,
    formData: {
      invoice_id: '{{ $invoice->id }}',
      item_type: '',
      description: '',
      amount: ''
    },
    formErrors: [],
    
    init() {
      // Store reference globally for access
      window.modalController = this;
    },
    
    openModal(type, item = null) {
      this.modalType = type;
      this.currentItem = item;
      this.formErrors = [];
      
      switch(type) {
        case 'create':
          this.modalTitle = 'Add Invoice Item';
          this.resetForm();
          break;
        case 'edit':
          this.modalTitle = 'Edit Item';
          if (item) {
            this.formData = {
              invoice_id: item.invoice_id || '{{ $invoice->id }}',
              item_type: item.item_type || '',
              description: item.description || '',
              amount: item.amount || ''
            };
          }
          break;
      }
      
      this.isOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isOpen = false;
      this.modalType = null;
      this.currentItem = null;
      this.formErrors = [];
      document.body.style.overflow = '';
    },
    
    resetForm() {
      this.formData = {
        invoice_id: '{{ $invoice->id }}',
        item_type: '',
        description: '',
        amount: ''
      };
    },
    
    formatAmount() {
      if (this.formData.amount) {
        const value = parseFloat(this.formData.amount);
        if (!isNaN(value)) {
          this.formData.amount = value.toFixed(2);
        } else {
          this.formData.amount = '';
        }
      }
    },
    
    validateForm() {
      this.formErrors = [];
      
      if (!this.formData.item_type) {
        this.formErrors.push('Please select an item type');
      }
      
      if (!this.formData.amount || parseFloat(this.formData.amount) <= 0) {
        this.formErrors.push('Please enter a valid amount greater than 0');
      }
      
      if (this.formErrors.length > 0) {
        // Scroll to top to show errors
        this.$el.scrollTop = 0;
      }
      
      return this.formErrors.length === 0;
    },
    
    getInvoiceLabel(invoiceId) {
      // This would need invoices data passed from controller
      const invoices = @json($invoices ?? []);
      const invoice = invoices.find(i => i.id == invoiceId);
      return invoice ? `#${invoice.id} - ${invoice.tenant_name || 'N/A'}` : 'Invoice #' + invoiceId;
    }
  }));
});
</script>
@endsection

@push('styles')
<style>
[x-cloak] {
  display: none !important;
}

.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: #9ca3af #f3f4f6;
}

.custom-scrollbar::-webkit-scrollbar {
  height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #9ca3af;
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

.dark .custom-scrollbar {
  scrollbar-color: #4b5563 #1f2937;
}

.dark .custom-scrollbar::-webkit-scrollbar-track {
  background: #1f2937;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: #4b5563;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

/* Modal styles */
.z-99999 {
  z-index: 99999 !important;
}

.backdrop-blur-\[32px\] {
  backdrop-filter: blur(32px);
}
</style>
@endpush