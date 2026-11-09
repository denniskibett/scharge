@extends('layouts.app')

@section('content')
@include('partials.card.payees')
<!-- Include all modal partials -->
@include('partials.modal.payees-create-modal')
@include('partials.modal.payees-edit-modal')
@include('partials.modal.payees-show-modal')
@include('partials.modal.payees-delete-modal')

<div x-data="payeesPage" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Payees</h3>

      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input 
            x-model="searchTerm" 
            @input.debounce.300ms="filterPayees()"
            type="text" 
            placeholder="Search payees..." 
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <button @click="window.payeeCreateModal?.openModal()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Add Payee
        </button>
      </div>
    </div>

    <div class="w-full overflow-x-auto">
      <table class="data-table w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-800">
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Name</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Type</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">ID Number</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Phone</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Email</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">KRA PIN</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">NSSF</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">SHA</th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Expenses</th>
            <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          <template x-if="filteredPayees.length === 0">
            <tr>
              <td colspan="10" class="p-4 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No payees found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchTerm">Try adjusting your search.</p>
              </td>
            </tr>
          </template>
          
          <template x-for="payee in filteredPayees" :key="payee.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap">
                <a
                  :href="`{{ url('payees') }}/${payee.id}`"
                  @click="window.payeeShowModal?.openModal(payee)"
                  class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400"
                  x-text="payee.name"
                ></a>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="{
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': payee.type === 'staff',
                        'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200': payee.type === 'vendor',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': payee.type === 'utility'
                      }">
                  <span x-text="payee.type.charAt(0).toUpperCase() + payee.type.slice(1)"></span>
                </span>
                <span x-show="payee.company_id === null" class="ml-1 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300" title="Shared by every business">Shared</span>
              </td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="payee.id_number || '-'"></td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="payee.phone || '-'"></td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="payee.email || '-'"></td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="payee.kra_pin || '-'"></td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="payee.nssf_number || '-'"></td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="payee.sha_number || '-'"></td>
              <td class="p-4 whitespace-nowrap">
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300" x-text="payee.expenses_count || 0"></span>
              </td>
              <td class="p-4 whitespace-nowrap text-right">
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                  </button>
                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                    <button type="button" @click="window.payeeShowModal?.openModal(payee); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">View</button>
                    <button type="button" x-show="payee.company_id !== null || canEditShared" @click="window.payeeEditModal?.openModal(payee); open = false" class="text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">Edit</button>
                    <button type="button" x-show="payee.company_id !== null || canEditShared" @click="window.payeeDeleteModal?.openModal(payee); open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    
    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
      <div class="flex items-center justify-between">
        <div>
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Total of
            <span class="text-gray-800 dark:text-white/90" x-text="filteredPayees.length"></span>
            payees
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('payeesPage', () => ({
    payees: @json($payees),
    canEditShared: @json(\App\Services\Expenses\BusinessBooks::companyIds(auth()->user()) === null),
    searchTerm: '',
    filteredPayees: [],
    
    init() {
      this.filteredPayees = this.payees;
    },
    
    filterPayees() {
      if (!this.searchTerm.trim()) {
        this.filteredPayees = this.payees;
        return;
      }
      
      const term = this.searchTerm.toLowerCase();
      this.filteredPayees = this.payees.filter(payee => {
        return (payee.name?.toLowerCase().includes(term) ||
                payee.type?.toLowerCase().includes(term) ||
                payee.phone?.toLowerCase().includes(term) ||
                payee.email?.toLowerCase().includes(term) ||
                payee.id_number?.toLowerCase().includes(term) ||
                payee.kra_pin?.toLowerCase().includes(term) ||
                payee.nssf_number?.toLowerCase().includes(term) ||
                payee.sha_number?.toLowerCase().includes(term));
      });
    },
    
    capitalize(text) {
      if (!text) return '';
      return text.charAt(0).toUpperCase() + text.slice(1);
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
.z-99998 {
  z-index: 99998 !important;
}
.z-99999 {
  z-index: 99999 !important;
}
.backdrop-blur-\[32px\] {
  backdrop-filter: blur(32px);
}
.translate-x-full {
  transform: translateX(100%);
}
.translate-x-0 {
  transform: translateX(0);
}
.scale-95 {
  transform: scale(0.95);
}
.scale-100 {
  transform: scale(1);
}
</style>
@endpush