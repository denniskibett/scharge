@extends('layouts.app')

@section('content')
<div x-data="paymentsPage" x-init="init()" x-cloak>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row lg:items-center dark:border-gray-800">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
          Tenant Payments
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Manage and track all tenant payments
        </p>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/>
            </svg>
          </span>
          <input 
            x-model="searchTerm" 
            @input.debounce.300ms="filterPayments()"
            type="text" 
            placeholder="Search payments..." 
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <div>
          <button 
            @click="openModal('create')"
            class="hover:text-dark-900 shadow-theme-xs relative flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 whitespace-nowrap text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Add Payment
          </button>
        </div>
      </div>
    </div>
    
    <div class="custom-scrollbar overflow-x-auto">
      <table class="w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            <th class="p-4 whitespace-nowrap">
              <p class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Payer</p>
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Invoice
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Amount
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Payment Method
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Transaction ID
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Paid To
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              Payment Date
            </th>
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
              <div class="relative">
                <span class="sr-only">Actions</span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          <template x-if="filteredPayments.length === 0">
            <tr>
              <td colspan="8" class="p-4 text-center">
                <span class="text-sm text-gray-500 dark:text-gray-400">No payments found.</span>
              </td>
            </tr>
          </template>
          
          <template x-for="payment in filteredPayments" :key="payment.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4 whitespace-nowrap">
                  <a
                      :href="`{{ url('payments') }}/${payment.id}`"
                      class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                      x-text="payment.payer_name || 'N/A'"
                  ></a>
              </td>

              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="payment.invoice_label ? payment.invoice_label : '-'"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-400" x-text="formatCurrency(payment.amount)"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-500 text-theme-xs rounded-full px-2 py-0.5 font-medium" x-text="capitalize(payment.payment_method)"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-700 dark:text-gray-400" x-text="payment.transaction_id || '-'"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-700 dark:text-gray-400" x-text="payment.paid_to || '-'"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <span class="text-sm text-gray-700 dark:text-gray-400" x-text="formatDate(payment.payment_datetime)"></span>
              </td>
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <button 
                    @click="openModal('show', payment)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                    View
                  </button>
                  <button 
                    @click="openModal('edit', payment)"
                    class="text-theme-xs flex items-center gap-1 rounded-lg px-3 py-2 text-left font-medium text-yellow-500 hover:bg-yellow-50 hover:text-yellow-700 dark:text-yellow-400 dark:hover:bg-yellow-500/5 dark:hover:text-yellow-300">
                    Edit
                  </button>
                  <form :action="`/payments/${payment.id}`" method="POST" class="inline">
                    @csrf @method('DELETE')
                    <button 
                      type="submit" 
                      @click.prevent="confirmDelete(payment)"
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
    
    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
      <div class="flex items-center justify-between">
        <div>
          <span class="block text-sm font-medium text-gray-500 dark:text-gray-400">
            Total of
            <span class="text-gray-800 dark:text-white/90" x-text="filteredPayments.length"></span>
            payments
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- MODALS -->
  <div x-data="modalController" x-init="init()">
    <!-- CREATE PAYMENT MODAL -->
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
        class="relative w-full max-w-[800px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10 max-h-[90vh] overflow-y-auto"
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

        <form id="createPaymentForm" action="{{ route('payments.store') }}" method="POST">
          @csrf
          <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
            Add New Payment
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
            <!-- Tenant Payer Selection -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tenant (Payer) *</label>
              <select 
                x-model="formData.tenancy_id"
                name="tenancy_id"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Tenant</option>
                @foreach($users as $user)
                  <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                @endforeach
              </select>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Invoice
              </label>
              <select 
                x-model="formData.invoice_id"
                name="invoice_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">N/A</option>
                @foreach($invoices as $invoice)
                  <option value="{{ $invoice['id'] }}">
                    {{ $invoice['label'] }}
                  </option>
                @endforeach
              </select>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">$</span>
                </div>
                <input 
                  x-model="formData.amount"
                  @blur="formatAmount()"
                  type="number"
                  step="0.01"
                  name="amount"
                  required
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method *</label>
              <select 
                x-model="formData.payment_method"
                name="payment_method"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="mpesa">Mpesa</option>
                <option value="bank">Bank</option>
                <option value="cash">Cash</option>
              </select>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction ID</label>
              <input 
                x-model="formData.transaction_id"
                name="transaction_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction Message</label>
              <textarea 
                x-model="formData.transaction_message"
                name="transaction_message"
                rows="2"
                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              ></textarea>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Paid To</label>
              <input 
                x-model="formData.paid_to"
                name="paid_to"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payer Name</label>
              <input 
                x-model="formData.payer_name"
                name="payer_name"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Date *</label>
              <input 
                x-model="formData.payment_datetime"
                @change="updatePaymentMonth()"
                type="datetime-local"
                name="payment_datetime"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Month</label>
              <input 
                x-model="formData.payment_month"
                name="payment_month"
                readonly
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
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
              Save Payment
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT PAYMENT MODAL -->
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
        class="relative w-full max-w-[800px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10 max-h-[90vh] overflow-y-auto"
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

        <form id="editPaymentForm" :action="`/payments/${currentPayment?.id}`" method="POST">
          @csrf @method('PUT')
          <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
            Edit Payment
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
            <!-- Tenant Payer Selection -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tenant (Payer) *</label>
              <select 
                x-model="formData.tenancy_id"
                name="tenancy_id"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">Select Tenant</option>
                @foreach($users as $user)
                  <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                @endforeach
              </select>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Invoice</label>
              <select 
                x-model="formData.invoice_id"
                name="invoice_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="">N/A</option>
                @foreach($invoices as $invoice)
                  <option value="{{ $invoice['id'] }}">{{ $invoice['label'] }}</option>
                @endforeach
              </select>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <span class="text-gray-500 dark:text-gray-400">$</span>
                </div>
                <input 
                  x-model="formData.amount"
                  @blur="formatAmount()"
                  type="number"
                  step="0.01"
                  name="amount"
                  required
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                  placeholder="0.00"
                />
              </div>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method *</label>
              <select 
                x-model="formData.payment_method"
                name="payment_method"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              >
                <option value="mpesa">Mpesa</option>
                <option value="bank">Bank</option>
                <option value="cash">Cash</option>
              </select>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction ID</label>
              <input 
                x-model="formData.transaction_id"
                name="transaction_id"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div class="md:col-span-2">
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Transaction Message</label>
              <textarea 
                x-model="formData.transaction_message"
                name="transaction_message"
                rows="2"
                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              ></textarea>
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Paid To</label>
              <input 
                x-model="formData.paid_to"
                name="paid_to"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payer Name</label>
              <input 
                x-model="formData.payer_name"
                name="payer_name"
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Date *</label>
              <input 
                x-model="formData.payment_datetime"
                @change="updatePaymentMonth()"
                type="datetime-local"
                name="payment_datetime"
                required
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
            </div>
            
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Payment Month</label>
              <input 
                x-model="formData.payment_month"
                name="payment_month"
                readonly
                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
              />
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
              Update Payment
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- SHOW PAYMENT MODAL -->
    <div x-show="isOpen && modalType === 'show'" 
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
        class="relative w-full max-w-[800px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-10 max-h-[90vh] overflow-y-auto"
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

        <div>
          <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
            Payment Details
          </h4>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Payer</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.payer_name || 'N/A'"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Invoice</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.invoice_label || '-'"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Amount</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="formatCurrency(currentPayment?.amount)"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Payment Method</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="capitalize(currentPayment?.payment_method)"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Transaction ID</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.transaction_id || '-'"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Transaction Message</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.transaction_message || '-'"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Paid To</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.paid_to || '-'"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Payer Name</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.payer_name || '-'"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Payment Date</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="formatDate(currentPayment?.payment_datetime)"></p>
            </div>
            <div>
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Payment Month</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="currentPayment?.payment_month || '-'"></p>
            </div>
            <div x-show="currentPayment?.created_at" class="md:col-span-2">
              <p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Created At</p>
              <p class="text-sm text-gray-800 dark:text-white/90" x-text="formatDate(currentPayment?.created_at)"></p>
            </div>
          </div>

          <div class="flex items-center justify-end w-full gap-3 mt-6">
            <button
              @click="closeModal()"
              type="button"
              class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Define Alpine components inline to ensure they're available when the page loads
document.addEventListener('alpine:init', () => {
  // Main payments page controller
  Alpine.data('paymentsPage', () => ({
    // Data - Passed from controller
    payments: @json($paymentsData),
    searchTerm: '',
    filteredPayments: [],
    
    // Initialize
    init() {
      this.filteredPayments = this.payments;
      console.log('Payments loaded:', this.payments.length);
    },
    
    // Methods
    filterPayments() {
      if (!this.searchTerm.trim()) {
        this.filteredPayments = this.payments;
        return;
      }
      
      const term = this.searchTerm.toLowerCase();
      this.filteredPayments = this.payments.filter(payment => {
        return Object.values(payment).some(value => 
          String(value).toLowerCase().includes(term)
        );
      });
    },
    
  formatCurrency(amount) {
      const symbol = "{{ SystemHelper::currencySymbol() }} "; // Blade inject
      if (!amount) return symbol + "0.00";
      // Add commas to the integer part
      return symbol + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },
    
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    
    capitalize(text) {
      if (!text) return '';
      return text.charAt(0).toUpperCase() + text.slice(1);
    },
    
    confirmDelete(payment) {
      if (confirm(`Are you sure you want to delete payment from ${payment.payer_name}?`)) {
        // Submit the form
        const form = document.querySelector(`form[action*="/payments/${payment.id}"]`);
        if (form) {
          form.submit();
        }
      }
    },
    
    openModal(type, payment = null) {
      if (window.modalController) {
        window.modalController.openModal(type, payment);
      }
    }
  }));
  
  // Modal controller
  Alpine.data('modalController', () => ({
    isOpen: false,
    modalType: null,
    modalTitle: '',
    currentPayment: null,
    formData: {
      tenancy_id: '', // Changed from user_id to tenancy_id
      invoice_id: '',
      amount: '',
      payment_method: 'mpesa',
      transaction_id: '',
      transaction_message: '',
      paid_to: '',
      payer_name: '',
      payment_datetime: '',
      payment_month: ''
    },
    formErrors: [],
    
    init() {
      // Store reference globally for access
      window.modalController = this;
      
      // Set default values
      const now = new Date();
      const currentDateTime = now.toISOString().slice(0, 16);
      const currentMonth = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      
      this.formData.payment_datetime = currentDateTime;
      this.formData.payment_month = currentMonth;
    },
    
    openModal(type, payment = null) {
      this.modalType = type;
      this.currentPayment = payment;
      this.formErrors = [];
      
      switch(type) {
        case 'create':
          this.modalTitle = 'Add New Payment';
          this.resetForm();
          break;
        case 'edit':
          this.modalTitle = 'Edit Payment';
          if (payment) {
            this.formData = {
              tenancy_id: payment.tenancy_id || '', // Changed from user_id
              invoice_id: payment.invoice_id || '',
              amount: payment.amount || '',
              payment_method: payment.payment_method || 'mpesa',
              transaction_id: payment.transaction_id || '',
              transaction_message: payment.transaction_message || '',
              paid_to: payment.paid_to || '',
              payer_name: payment.payer_name || '',
              payment_datetime: this.formatDateForInput(payment.payment_datetime),
              payment_month: payment.payment_month || ''
            };
          }
          break;
        case 'show':
          this.modalTitle = 'Payment Details';
          break;
      }
      
      this.isOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isOpen = false;
      this.modalType = null;
      this.currentPayment = null;
      this.formErrors = [];
      document.body.style.overflow = '';
    },
    
    resetForm() {
      const now = new Date();
      const currentDateTime = now.toISOString().slice(0, 16);
      const currentMonth = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      
      this.formData = {
        tenancy_id: '', // Changed from user_id
        invoice_id: '',
        amount: '',
        payment_method: 'mpesa',
        transaction_id: '',
        transaction_message: '',
        paid_to: '',
        payer_name: '',
        payment_datetime: currentDateTime,
        payment_month: currentMonth
      };
    },
    
    formatDateForInput(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toISOString().slice(0, 16);
    },
    
    updatePaymentMonth() {
      if (this.formData.payment_datetime) {
        try {
          const date = new Date(this.formData.payment_datetime);
          if (!isNaN(date.getTime())) {
            this.formData.payment_month = date.toLocaleDateString('en-US', { 
              month: 'long', 
              year: 'numeric' 
            });
          }
        } catch (e) {
          console.error('Error updating payment month:', e);
        }
      }
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
      
      if (!this.formData.tenancy_id) {
        this.formErrors.push('Please select a tenant');
      }
      
      if (!this.formData.amount || parseFloat(this.formData.amount) <= 0) {
        this.formErrors.push('Please enter a valid amount greater than 0');
      }
      
      if (!this.formData.payment_datetime) {
        this.formErrors.push('Please select a payment date');
      }
      
      if (!this.formData.payment_method) {
        this.formErrors.push('Please select a payment method');
      }
      
      if (this.formErrors.length > 0) {
        // Scroll to top to show errors
        this.$el.scrollTop = 0;
      }
      
      return this.formErrors.length === 0;
    },
    
    submitForm() {
      if (!this.validateForm()) {
        return false;
      }
      
      // Submit the form
      const form = this.$el.querySelector('form');
      if (form) {
        // Allow the form to submit normally
        return true;
      }
      return false;
    },
    
    formatCurrency(amount) {
        const symbol = "{{ SystemHelper::currencySymbol() }} "; // Blade inject
        if (!amount) return symbol + "0.00";
        // Add commas to the integer part
        return symbol + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

/* Modal styles */
.z-99999 {
  z-index: 99999 !important;
}

.backdrop-blur-\[32px\] {
  backdrop-filter: blur(32px);
}
</style>
@endpush