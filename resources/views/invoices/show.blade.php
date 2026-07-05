@extends('layouts.app')

@section('content')
<div x-data="invoiceItemsPage" x-init="init()" x-cloak>
  <div class="flex h-full flex-col gap-6 sm:gap-5 xl:flex-row">
    <!-- Invoice Sidebar Start -->
    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] xl:w-1/5 no-print">
      <div class="relative mb-5 w-full">
        <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2">
            <svg class="fill-gray-500 dark:fill-gray-400" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z" fill=""/>
            </svg>
          </span>
          <input 
            x-model="searchTerm" 
            @input.debounce.300ms="filterItems()"
            type="text" 
            placeholder="Search items..." 
            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 pl-12 pr-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900 dark:bg-white/[0.03] dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800">
        </div>
      </div>

      <!-- Tenancy Info Sidebar -->
      <div class="space-y-4">
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Invoice Details</h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Invoice #:</span>
              <span class="font-medium text-gray-800 dark:text-white/90">#{{ $invoice->id }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Status:</span>
              <span class="inline-flex px-2 py-0.5 text-xs rounded-full" 
                    :class="getStatusClass('{{ $invoice->status }}')">
                {{ ucfirst($invoice->status) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Created:</span>
              <span class="text-gray-700 dark:text-gray-300">{{ $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Billing Month:</span>
              <span class="text-gray-700 dark:text-gray-300">{{ $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') : '-' }}</span>
            </div>
            @if($waterSyncStatus ?? 'none' !== 'none')
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
              <span class="text-gray-500 dark:text-gray-400">Water Sync:</span>
              <span class="inline-flex px-2 py-0.5 text-xs rounded-full" 
                    :class="getWaterSyncStatusClass('{{ $waterSyncStatus ?? 'none' }}')">
                {{ $waterSyncStatus === 'synced' ? '✓ Synced' : ($waterSyncStatus === 'pending' ? '⏳ Pending' : '⚠️ Needs Review') }}
              </span>
            </div>
            @endif
          </div>
        </div>

        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tenant Information</h4>
          <div class="space-y-2 text-sm">
            <div>
              <span class="text-gray-500 dark:text-gray-400">Name:</span>
              <p class="font-medium text-gray-800 dark:text-white/90">{{ optional($invoice->tenancy->tenant->user)->name ?? 'N/A' }}</p>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Unit:</span>
              <p class="text-gray-700 dark:text-gray-300">{{ optional($invoice->tenancy->unit)->unit_number ?? 'N/A' }}</p>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">Estate:</span>
              <p class="text-gray-700 dark:text-gray-300">{{ optional(optional($invoice->tenancy->unit)->estate)->name ?? 'N/A' }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Payment Summary</h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Total Amount:</span>
              <span class="font-medium text-gray-800 dark:text-white/90">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Amount Paid:</span>
              <span class="font-medium text-green-600 dark:text-green-400">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($paidAmount ?? 0, 2) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Balance Due:</span>
              <span class="font-medium text-red-600 dark:text-red-400">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format(max(0, $invoice->total_amount - ($paidAmount ?? 0)), 2) }}</span>
            </div>
          </div>
        </div>

        <!-- Payment Details Section -->
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Payment Details</h4>
          <div class="space-y-3 text-sm max-h-60 overflow-y-auto">
            @if($invoice->payments->isNotEmpty())
              @foreach($invoice->payments as $payment)
                <div class="border-b border-gray-200 dark:border-gray-700 pb-3 last:border-0 last:pb-0">
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Amount:</span>
                    <span class="font-medium text-green-600 dark:text-green-400">
                      {{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($payment->amount, 2) }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Method:</span>
                    <span class="font-medium text-gray-800 dark:text-white/90">
                      {{ $payment->payment_method_label ?? $payment->payment_method }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Transaction Ref:</span>
                    <span class="font-mono text-xs text-gray-700 dark:text-gray-300 truncate max-w-[130px]" 
                          title="{{ $payment->external_reference ?? $payment->transaction_reference }}">
                      {{ $payment->external_reference ?: substr($payment->transaction_reference ?? 'N/A', 0, 8) . '...' }}
                    </span>
                  </div>
                  @if($payment->external_reference)
                    <div class="flex justify-between">
                      <span class="text-gray-500 dark:text-gray-400">External Ref:</span>
                      <span class="font-mono text-xs text-blue-600 dark:text-blue-400 truncate max-w-[130px]" 
                            title="{{ $payment->external_reference }}">
                        {{ $payment->external_reference }}
                      </span>
                    </div>
                  @endif
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Date:</span>
                    <span class="text-gray-700 dark:text-gray-300 text-xs">
                      {{ $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-' }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Status:</span>
                    <span class="inline-flex px-2 py-0.5 text-xs rounded-full {{ $payment->status_badge['class'] ?? 'bg-gray-100 text-gray-800' }}">
                      {{ $payment->status_badge['label'] ?? ucfirst($payment->status) }}
                    </span>
                  </div>
                  @if($payment->is_reconciled)
                    <div class="flex justify-between">
                      <span class="text-gray-500 dark:text-gray-400">Reconciled:</span>
                      <span class="text-xs text-blue-600 dark:text-blue-400">✓ Yes</span>
                    </div>
                  @endif
                </div>
              @endforeach
              <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Total Paid:</span>
                  <span class="font-bold text-green-600 dark:text-green-400">
                    {{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($paidAmount ?? 0, 2) }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Balance Due:</span>
                  <span class="font-bold text-red-600 dark:text-red-400">
                    {{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format(max(0, $invoice->total_amount - ($paidAmount ?? 0)), 2) }}
                  </span>
                </div>
              </div>
            @else
              <p class="text-sm text-gray-500 dark:text-gray-400">No payments recorded yet.</p>
            @endif
          </div>
        </div>
      </div>
    </div>
    <!-- Invoice Sidebar End -->

    <!-- Invoice Mainbox Start -->
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] xl:w-4/5">
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800 no-print">
        <h3 class="text-theme-xl font-medium text-gray-800 dark:text-white/90">
          Invoice Details
        </h3>
        <div class="flex gap-2">
          @if($invoice->status !== 'paid')
          <button 
            @click="openAddItemModal()"
            class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="none">
              <path d="M10 4.16667V15.8333M4.16667 10H15.8333" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Add Item
          </button>
          @endif
          <button 
            @click="printInvoice()"
            class="flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
            <svg class="fill-current" width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M6.99578 4.08398C6.58156 4.08398 6.24578 4.41977 6.24578 4.83398V6.36733H13.7542V5.62451C13.7542 5.42154 13.672 5.22724 13.5262 5.08598L12.7107 4.29545C12.5707 4.15983 12.3835 4.08398 12.1887 4.08398H6.99578ZM15.2542 6.36902V5.62451C15.2542 5.01561 15.0074 4.43271 14.5702 4.00891L13.7547 3.21839C13.3349 2.81151 12.7733 2.58398 12.1887 2.58398H6.99578C5.75314 2.58398 4.74578 3.59134 4.74578 4.83398V6.36902C3.54391 6.41522 2.58374 7.40415 2.58374 8.61733V11.3827C2.58374 12.5959 3.54382 13.5848 4.74561 13.631V15.1665C4.74561 16.4091 5.75297 17.4165 6.99561 17.4165H13.0041C14.2467 17.4165 15.2541 16.4091 15.2541 15.1665V13.6311C16.456 13.585 17.4163 12.596 17.4163 11.3827V8.61733C17.4163 7.40414 16.4561 6.41521 15.2542 6.36902ZM4.74561 11.6217V12.1276C4.37292 12.084 4.08374 11.7671 4.08374 11.3827V8.61733C4.08374 8.20312 4.41953 7.86733 4.83374 7.86733H15.1663C15.5805 7.86733 15.9163 8.20312 15.9163 8.61733V11.3827C15.9163 11.7673 15.6269 12.0842 15.2541 12.1277V11.6217C15.2541 11.2075 14.9183 10.8717 14.5041 10.8717H5.49561C5.08139 10.8717 4.74561 11.2075 4.74561 11.6217ZM6.24561 12.3717V15.1665C6.24561 15.5807 6.58139 15.9165 6.99561 15.9165H13.0041C13.4183 15.9165 13.7541 15.5807 13.7541 15.1665V12.3717H6.24561Z" fill=""/>
            </svg>
            Print
          </button>
        </div>
      </div>

      <!-- Printable Content Start -->
      <div id="printable-content">
        <div class="p-5 xl:p-8">
          <!-- Invoice Header - From/To -->
          <div class="mb-9 flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">From</span>
              <h5 class="mb-2 text-base font-semibold text-gray-800 dark:text-white/90">
                {{ optional(optional($invoice->tenancy->unit)->estate)->name ?? 'N/A' }}
              </h5>
              <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ optional(optional($invoice->tenancy->unit)->estate)->address ?? 'Address not available' }}
              </p>
              <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Issued On:</span>
              <span class="block text-sm text-gray-500 dark:text-gray-400">
                {{ $invoice->created_at ? $invoice->created_at->format('d M, Y') : '-' }}
              </span>
            </div>

            <div class="h-px w-full bg-gray-200 dark:bg-gray-800 sm:h-[158px] sm:w-px"></div>

            <div class="sm:text-right">
              <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">To</span>
              <h5 class="mb-2 text-base font-semibold text-gray-800 dark:text-white/90">
                {{ optional($invoice->tenancy->tenant->user)->name ?? 'N/A' }}
              </h5>
              <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ optional($invoice->tenancy->tenant)->address ?? 'Address not available' }}
              </p>
              <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Due On:</span>
              <span class="block text-sm text-gray-500 dark:text-gray-400">
                {{ $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->endOfMonth()->format('d M, Y') : '-' }}
              </span>
            </div>
          </div>

          <!-- Invoice Table -->
          <div class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="max-w-full overflow-x-auto">
              <div class="min-w-[800px]">
                <!-- table header -->
                <div class="grid grid-cols-12 px-5 py-3 bg-gray-50 dark:bg-gray-800/50">
                  <div class="col-span-1 flex items-center">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">#</p>
                  </div>
                  <div class="col-span-3 flex items-center">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Item Type</p>
                  </div>
                  <div class="col-span-4 flex items-center">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Description</p>
                  </div>
                  <div class="col-span-2 flex items-center justify-end">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Amount</p>
                  </div>
                  <div class="col-span-2 flex items-center justify-end">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-400">Water Usage</p>
                  </div>
                </div>

                <!-- table body -->
                <template x-for="(item, idx) in filteredItems" :key="item.id">
                  <div class="grid grid-cols-12 border-t border-gray-100 px-5 py-3.5 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30">
                    <div class="col-span-1 flex items-center">
                      <p class="text-theme-sm text-gray-500 dark:text-gray-400" x-text="idx + 1"></p>
                    </div>
                    <div class="col-span-3 flex items-center">
                      <span :class="getItemTypeColor(item.item_type)" class="text-theme-xs rounded-full px-2 py-0.5 font-medium" x-text="capitalize(item.item_type)"></span>
                    </div>
                    <div class="col-span-4 flex items-center">
                      <p class="text-theme-sm text-gray-500 dark:text-gray-400" x-text="item.description || '-'"></p>
                    </div>
                    <div class="col-span-2 flex items-center justify-end">
                      <p class="text-right text-theme-sm font-medium text-gray-700 dark:text-gray-300" x-text="formatCurrency(item.amount)"></p>
                    </div>
                    <div class="col-span-2 flex items-center justify-end">
                      <template x-if="item.item_type === 'water'">
                        <div class="flex items-center gap-2">
                          <span x-show="item.water_units_used > 0" class="text-theme-sm text-blue-600 dark:text-blue-400" x-text="item.water_units_used + ' m³'"></span>
                          <span x-show="!item.water_units_used || item.water_units_used === 0" class="text-theme-sm text-yellow-600 dark:text-yellow-400" title="Water usage not recorded">⚠️ Not synced</span>
                        </div>
                      </template>
                      <template x-if="item.item_type !== 'water'">
                        <span class="text-theme-sm text-gray-400 dark:text-gray-500">—</span>
                      </template>
                    </div>
                  </div>
                </template>

                <!-- No items message -->
                <div x-show="filteredItems.length === 0" class="border-t border-gray-100 px-5 py-8 text-center dark:border-gray-800">
                  <p class="text-sm text-gray-500 dark:text-gray-400">No invoice items found. Click "Add Item" to add items.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Summary Section -->
          <div class="my-6 border-b border-gray-100 pb-6 dark:border-gray-800">
            <div class="flex justify-end">
              <div class="w-full sm:w-80 space-y-3">
                <div class="flex justify-between py-2">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Subtotal:</p>
                  <p class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="formatCurrency(calculateSubtotal())"></p>
                </div>
                <div class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-800">
                  <p class="text-base font-semibold text-gray-800 dark:text-white/90">Total:</p>
                  <p class="text-base font-semibold text-green-600 dark:text-green-400">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($invoice->total_amount, 2) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Payment History Section -->
        <!--  <div class="mb-6">-->
        <!--    <h4 class="text-base font-semibold text-gray-800 dark:text-white/90 mb-3">Payment History</h4>-->
        <!--    @if($invoice->payments->isNotEmpty())-->
        <!--      <div class="overflow-x-auto">-->
        <!--        <table class="w-full text-sm">-->
        <!--          <thead class="bg-gray-50 dark:bg-gray-800/50">-->
        <!--            <tr>-->
        <!--              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Amount</th>-->
        <!--              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Method</th>-->
        <!--              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Transaction Ref</th>-->
        <!--              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">External Ref</th>-->
        <!--              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>-->
        <!--              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>-->
        <!--            </tr>-->
        <!--          </thead>-->
        <!--          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">-->
        <!--            @foreach($invoice->payments as $payment)-->
        <!--              <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">-->
        <!--                <td class="px-4 py-2 font-medium text-green-600 dark:text-green-400">-->
        <!--                  {{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($payment->amount, 2) }}-->
        <!--                </td>-->
        <!--                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">-->
        <!--                  {{ $payment->payment_method_label ?? $payment->payment_method }}-->
        <!--                </td>-->
        <!--                <td class="px-4 py-2">-->
        <!--                  <span class="font-mono text-xs text-gray-600 dark:text-gray-400" title="{{ $payment->transaction_reference }}">-->
        <!--                    {{ $payment->transaction_reference ? substr($payment->transaction_reference, 0, 8) . '...' : '—' }}-->
        <!--                  </span>-->
        <!--                </td>-->
        <!--                <td class="px-4 py-2">-->
        <!--                  @if($payment->external_reference)-->
        <!--                    <span class="font-mono text-xs text-blue-600 dark:text-blue-400" title="{{ $payment->external_reference }}">-->
        <!--                      {{ $payment->external_reference }}-->
        <!--                    </span>-->
        <!--                  @else-->
        <!--                    <span class="text-gray-400">—</span>-->
        <!--                  @endif-->
        <!--                </td>-->
        <!--                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">-->
        <!--                  {{ $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-' }}-->
        <!--                </td>-->
        <!--                <td class="px-4 py-2">-->
        <!--                  <span class="inline-flex px-2 py-0.5 text-xs rounded-full {{ $payment->status_badge['class'] ?? 'bg-gray-100 text-gray-800' }}">-->
        <!--                    {{ $payment->status_badge['label'] ?? ucfirst($payment->status) }}-->
        <!--                  </span>-->
        <!--                  @if($payment->is_reconciled)-->
        <!--                    <span class="ml-1 inline-flex px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">✓</span>-->
        <!--                  @endif-->
        <!--                </td>-->
        <!--              </tr>-->
        <!--            @endforeach-->
        <!--          </tbody>-->
        <!--          <tfoot class="bg-gray-50 dark:bg-gray-800/50">-->
        <!--            <tr>-->
        <!--              <td colspan="1" class="px-4 py-2 font-bold text-gray-800 dark:text-white/90">Total Paid</td>-->
        <!--              <td colspan="5" class="px-4 py-2 font-bold text-green-600 dark:text-green-400">-->
        <!--                {{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($paidAmount ?? 0, 2) }}-->
        <!--              </td>-->
        <!--            </tr>-->
        <!--          </tfoot>-->
        <!--        </table>-->
        <!--      </div>-->
        <!--    @else-->
        <!--      <p class="text-sm text-gray-500 dark:text-gray-400">No payments recorded for this invoice.</p>-->
        <!--    @endif-->
        <!--  </div>-->
        <!--</div>-->
      </div>

      <!-- Action Buttons -->
      @if($invoice->status !== 'paid')
      <div class="flex items-center justify-end gap-3 p-5 pt-0 no-print">
        <button 
          @click="processPayment()"
          class="flex items-center justify-center rounded-lg bg-success-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs hover:bg-success-600">
          Proceed to Payment
        </button>
      </div>
      @endif
    </div>
    <!-- Invoice Mainbox End -->
  </div>

  <!-- Add/Edit Item Modal -->
  <div x-show="isModalOpen" 
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       x-cloak
       class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
    <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" @click="closeModal()"></div>
    <div class="relative w-full max-w-[600px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8">
      <button @click="closeModal()" class="group absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
        <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z"/>
        </svg>
      </button>

      <form @submit.prevent="submitItemForm">
        @csrf
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="modalTitle"></h4>

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
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Item Type *</label>
            <select x-model="formData.item_type" required class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
              <option value="">Select Item Type</option>
              <option value="rent">Rent</option>
              <option value="water">Water</option>
              <option value="service">Service Charge</option>
              <option value="garbage">Garbage Collection</option>
              <option value="security">Security</option>
              <option value="other">Other</option>
            </select>
          </div>
          
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
            <input x-model="formData.description" type="text" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="e.g., Monthly Rent, Water Bill, etc."/>
          </div>
          
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Amount *</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <span class="text-gray-500 dark:text-gray-400">{{ \App\Helpers\SystemHelper::currencySymbol() }}</span>
              </div>
              <input x-model="formData.amount" @blur="formatAmount()" type="number" step="0.01" min="0.01" required class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-8 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="0.00"/>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end w-full gap-3 mt-6">
          <button @click="closeModal()" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
          <button type="submit" class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 sm:w-auto" x-text="isEditMode ? 'Update Item' : 'Add Item'"></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Include Payment Create Modal Component -->
@include('partials.modal.payment-create-modal')

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('invoiceItemsPage', () => ({
    items: @json($invoice->items),
    searchTerm: '',
    filteredItems: [],
    isModalOpen: false,
    isEditMode: false,
    currentItemId: null,
    modalTitle: 'Add Invoice Item',
    formData: {
      item_type: '',
      description: '',
      amount: ''
    },
    formErrors: [],
    waterSyncStatus: '{{ $waterSyncStatus ?? 'none' }}',
    invoiceId: {{ $invoice->id }},
    
    init() {
      this.filteredItems = this.items;
    },
    
    filterItems() {
      if (!this.searchTerm.trim()) {
        this.filteredItems = this.items;
        return;
      }
      const term = this.searchTerm.toLowerCase();
      this.filteredItems = this.items.filter(item => 
        item.description?.toLowerCase().includes(term) ||
        item.item_type?.toLowerCase().includes(term)
      );
    },
    
    calculateSubtotal() {
      return this.filteredItems.reduce((sum, item) => sum + parseFloat(item.amount), 0);
    },
    
    formatCurrency(amount) {
      const symbol = "{{ \App\Helpers\SystemHelper::currencySymbol() }}";
      if (!amount) return symbol + " 0.00";
      return symbol + " " + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    
    capitalize(text) {
      if (!text) return '';
      return text.charAt(0).toUpperCase() + text.slice(1);
    },
    
    getItemTypeColor(itemType) {
      const colors = {
        'rent': 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
        'water': 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
        'service': 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
        'garbage': 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
        'security': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/20 dark:text-cyan-400',
        'other': 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
      };
      return colors[itemType] || colors['other'];
    },
    
    getStatusClass(status) {
      const classes = {
        'paid': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'unpaid': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'partial': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-800/50 dark:text-gray-400'
      };
      return classes[status] || classes['draft'];
    },
    
    getWaterSyncStatusClass(status) {
      const classes = {
        'synced': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'needs_review': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'none': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
      };
      return classes[status] || classes['none'];
    },
    
    openAddItemModal() {
      this.isEditMode = false;
      this.modalTitle = 'Add Invoice Item';
      this.currentItemId = null;
      this.formData = { item_type: '', description: '', amount: '' };
      this.formErrors = [];
      this.isModalOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    openEditModal(item) {
      this.isEditMode = true;
      this.modalTitle = 'Edit Invoice Item';
      this.currentItemId = item.id;
      this.formData = {
        item_type: item.item_type,
        description: item.description || '',
        amount: item.amount
      };
      this.formErrors = [];
      this.isModalOpen = true;
      document.body.style.overflow = 'hidden';
    },
    
    closeModal() {
      this.isModalOpen = false;
      this.formErrors = [];
      document.body.style.overflow = '';
    },
    
    formatAmount() {
      if (this.formData.amount) {
        const value = parseFloat(this.formData.amount);
        if (!isNaN(value)) this.formData.amount = value.toFixed(2);
        else this.formData.amount = '';
      }
    },
    
    async submitItemForm() {
      this.formErrors = [];
      if (!this.formData.item_type) this.formErrors.push('Please select an item type');
      if (!this.formData.amount || parseFloat(this.formData.amount) <= 0) this.formErrors.push('Please enter a valid amount greater than 0');
      if (this.formErrors.length > 0) return;
      
      try {
        let url = '{{ route("invoices.items.store", $invoice) }}';
        let method = 'POST';
        let body = {
          item_type: this.formData.item_type,
          description: this.formData.description,
          amount: this.formData.amount
        };
        
        if (this.isEditMode) {
          url = `{{ route('invoices.items.update', ['invoice' => $invoice->id, 'item' => '__ITEM_ID__']) }}`.replace('__ITEM_ID__', this.currentItemId);
          method = 'PUT';
          body = {
            description: this.formData.description,
            amount: this.formData.amount,
            item_type: this.formData.item_type
          };
        }
        
        const response = await fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
          },
          body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
          this.closeModal();
          window.location.reload();
        } else {
          this.formErrors = [data.message || 'Failed to save item'];
        }
      } catch (error) {
        console.error('Error saving item:', error);
        this.formErrors = ['An error occurred. Please try again.'];
      }
    },
    
    confirmDelete(item) {
      if (confirm(`Are you sure you want to delete "${item.description || item.item_type}" item?`)) {
        this.deleteItem(item.id);
      }
    },
    
    async deleteItem(itemId) {
      try {
        const response = await fetch(`{{ route('invoices.items.destroy', ['invoice' => $invoice->id, 'item' => '__ITEM_ID__']) }}`.replace('__ITEM_ID__', itemId), {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
          }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
          window.location.reload();
        } else {
          alert(data.message || 'Failed to delete item');
        }
      } catch (error) {
        console.error('Error deleting item:', error);
        alert('An error occurred while deleting the item');
      }
    },
    
    processPayment() {
      if (window.paymentCreateModal) {
        window.paymentCreateModal.openModal({
          id: {{ $invoice->id }},
          tenancy_id: {{ $invoice->tenancy_id }},
          tenant_name: '{{ addslashes(optional($invoice->tenancy->tenant->user)->name ?? 'N/A') }}',
          unit_number: '{{ addslashes(optional($invoice->tenancy->unit)->unit_number ?? 'N/A') }}',
          estate_name: '{{ addslashes(optional(optional($invoice->tenancy->unit)->estate)->name ?? 'N/A') }}',
          total_amount: {{ $invoice->total_amount }},
          outstanding_balance: {{ max(0, $invoice->total_amount - ($paidAmount ?? 0)) }},
          billing_month: '{{ $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : '-' }}'
        });
      } else {
        window.dispatchEvent(new CustomEvent('open-payment-modal', {
          detail: {
            invoice_id: {{ $invoice->id }},
            tenancy_id: {{ $invoice->tenancy_id }},
            tenant_name: '{{ addslashes(optional($invoice->tenancy->tenant->user)->name ?? 'N/A') }}',
            unit_number: '{{ addslashes(optional($invoice->tenancy->unit)->unit_number ?? 'N/A') }}',
            estate_name: '{{ addslashes(optional(optional($invoice->tenancy->unit)->estate)->name ?? 'N/A') }}',
            total_amount: {{ $invoice->total_amount }},
            outstanding_balance: {{ max(0, $invoice->total_amount - ($paidAmount ?? 0)) }},
            billing_month: '{{ $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : '-' }}'
          }
        }));
      }
    },
    
    printInvoice() {
      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Invoice #{{ $invoice->id }}</title>
          <meta charset="utf-8">
          <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; line-height: 1.5; background: white; padding: 40px; margin: 0; }
            .print-invoice-container { max-width: 900px; margin: 0 auto; background: white; }
            .print-header { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 2px solid #e5e7eb; }
            .print-invoice-title { font-size: 28px; font-weight: 800; color: #111827; letter-spacing: -0.02em; }
            .print-invoice-number { font-size: 14px; color: #6b7280; margin-top: 4px; }
            .print-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
            .print-status-paid { background: #dcfce7; color: #166534; }
            .print-status-unpaid { background: #fee2e2; color: #991b1b; }
            .print-status-partial { background: #fef3c7; color: #92400e; }
            .print-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
            .print-status-paid .print-status-dot { background: #16a34a; }
            .print-status-unpaid .print-status-dot { background: #dc2626; }
            .print-status-partial .print-status-dot { background: #d97706; }
            .print-from-to { display: flex; justify-content: space-between; margin-bottom: 40px; }
            .print-section { flex: 1; }
            .print-section-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 12px; }
            .print-entity-name { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px; }
            .print-address { font-size: 13px; color: #4b5563; margin-bottom: 16px; line-height: 1.4; }
            .print-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
            .print-value { font-size: 13px; font-weight: 500; color: #111827; }
            .print-table { width: 100%; border-collapse: collapse; margin: 24px 0; }
            .print-table th { text-align: left; padding: 12px 8px; background: #f9fafb; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
            .print-table td { padding: 14px 8px; font-size: 13px; color: #374151; border-bottom: 1px solid #f0f0f0; }
            .print-table tr:last-child td { border-bottom: none; }
            .print-amount-cell { text-align: right; font-weight: 500; }
            .print-item-type { display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
            .print-type-rent { background: #e9d5ff; color: #6b21a5; }
            .print-type-water { background: #dbeafe; color: #1e40af; }
            .print-type-service { background: #dcfce7; color: #166534; }
            .print-type-garbage { background: #ffedd5; color: #9a3412; }
            .print-type-security { background: #cffafe; color: #155e75; }
            .print-type-other { background: #f3f4f6; color: #374151; }
            .print-summary { margin-top: 32px; border-top: 2px solid #e5e7eb; padding-top: 24px; display: flex; justify-content: flex-end; }
            .print-summary-box { width: 280px; }
            .print-summary-row { display: flex; justify-content: space-between; padding: 8px 0; }
            .print-summary-label { font-size: 13px; color: #6b7280; }
            .print-summary-amount { font-size: 13px; font-weight: 500; color: #374151; }
            .print-total-row { border-top: 1px solid #e5e7eb; margin-top: 4px; padding-top: 12px; }
            .print-total-row .print-summary-label, .print-total-row .print-summary-amount { font-size: 16px; font-weight: 700; color: #111827; }
            .print-payment-info { margin-top: 32px; background: #f9fafb; padding: 16px; border-radius: 8px; }
            .print-payment-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            .print-payment-table th { text-align: left; padding: 8px 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
            .print-payment-table td { padding: 8px 6px; font-size: 12px; color: #374151; border-bottom: 1px solid #f3f4f6; }
            .print-payment-table .print-amount { font-weight: 600; }
            .print-paid-amount { color: #16a34a; font-weight: 700; }
            .print-balance-amount { color: #dc2626; font-weight: 700; }
            .print-footer { margin-top: 48px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 24px; }
            .print-table tr { page-break-inside: avoid; }
            .no-print-print { display: none; }
          </style>
        </head>
        <body>
          <div class="print-invoice-container">
            <!-- Header -->
            <div class="print-header">
              <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                  <div class="print-invoice-title">INVOICE</div>
                  <div class="print-invoice-number">#{{ $invoice->id }}</div>
                </div>
                <div>
                  <div class="print-status-badge print-status-{{ $invoice->status === 'paid' ? 'paid' : ($invoice->status === 'partial' ? 'partial' : 'unpaid') }}">
                    <span class="print-status-dot"></span>
                    {{ strtoupper($invoice->status) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- From / To -->
            <div class="print-from-to">
              <div class="print-section">
                <div class="print-section-title">FROM</div>
                <div class="print-entity-name">{{ optional(optional($invoice->tenancy->unit)->estate)->name ?? 'N/A' }}</div>
                <div class="print-address">{{ optional(optional($invoice->tenancy->unit)->estate)->address ?? 'Address not available' }}</div>
                <div class="print-label">ISSUED ON</div>
                <div class="print-value">{{ $invoice->created_at ? $invoice->created_at->format('d M, Y') : '-' }}</div>
              </div>
              <div class="print-section" style="text-align: right;">
                <div class="print-section-title">TO</div>
                <div class="print-entity-name">{{ optional($invoice->tenancy->tenant->user)->name ?? 'N/A' }}</div>
                <div class="print-address">{{ optional($invoice->tenancy->tenant)->address ?? 'Address not available' }}</div>
                <div class="print-label">DUE ON</div>
                <div class="print-value">{{ $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->endOfMonth()->format('d M, Y') : '-' }}</div>
              </div>
            </div>

            <!-- Items -->
            <table class="print-table">
              <thead>
                <tr>
                  <th style="width: 8%">#</th>
                  <th style="width: 22%">ITEM TYPE</th>
                  <th style="width: 40%">DESCRIPTION</th>
                  <th style="width: 15%; text-align: right">AMOUNT</th>
                  <th style="width: 15%; text-align: right">WATER USAGE</th>
                </tr>
              </thead>
              <tbody>
                @forelse($invoice->items as $index => $item)
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>
                    <span class="print-item-type print-type-{{ $item->item_type === 'rent' ? 'rent' : ($item->item_type === 'water' ? 'water' : ($item->item_type === 'service' ? 'service' : ($item->item_type === 'garbage' ? 'garbage' : ($item->item_type === 'security' ? 'security' : 'other')))) }}">
                      {{ ucfirst($item->item_type) }}
                    </span>
                  </td>
                  <td>{{ $item->description ?? '-' }}</td>
                  <td class="print-amount-cell">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($item->amount, 2) }}</td>
                  <td class="print-amount-cell">
                    @if($item->item_type === 'water')
                      @if($item->water_units_used > 0)
                        {{ number_format($item->water_units_used, 2) }} m³
                      @else
                        <span style="color: #d97706;">Not synced</span>
                      @endif
                    @else
                      —
                    @endif
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" style="text-align: center; padding: 40px; color: #9ca3af;">No items on this invoice</td>
                </tr>
                @endforelse
              </tbody>
            </table>

            <!-- Summary -->
            <div class="print-summary">
              <div class="print-summary-box">
                <div class="print-summary-row">
                  <span class="print-summary-label">Subtotal</span>
                  <span class="print-summary-amount">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($invoice->total_amount, 2) }}</span>
                </div>
                <div class="print-summary-row print-total-row">
                  <span class="print-summary-label">TOTAL</span>
                  <span class="print-summary-amount">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($invoice->total_amount, 2) }}</span>
                </div>
              </div>
            </div>

            <!-- Payment History -->
            <div style="margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
              <h4 style="font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 12px;">Payment History</h4>
              @if($invoice->payments->isNotEmpty())
                <table class="print-payment-table">
                  <thead>
                    <tr>
                      <th>Amount</th>
                      <th>Method</th>
                      <th>Transaction Ref</th>
                      <th>External Ref</th>
                      <th>Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($invoice->payments as $payment)
                    <tr>
                      <td class="print-amount" style="color: #16a34a;">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($payment->amount, 2) }}</td>
                      <td>{{ $payment->payment_method_label ?? $payment->payment_method }}</td>
                      <td style="font-family: monospace; font-size: 11px;">{{ $payment->transaction_reference ? substr($payment->transaction_reference, 0, 8) . '...' : '—' }}</td>
                      <td style="font-family: monospace; font-size: 11px; color: #2563eb;">{{ $payment->external_reference ?? '—' }}</td>
                      <td>{{ $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-' }}</td>
                      <td>
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600; background: {{ $payment->status === 'completed' ? '#dcfce7' : '#fef3c7' }}; color: {{ $payment->status === 'completed' ? '#166534' : '#92400e' }};">
                          {{ $payment->status_badge['label'] ?? ucfirst($payment->status) }}
                        </span>
                        @if($payment->is_reconciled)
                          <span style="display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 9px; font-weight: 600; background: #dbeafe; color: #1e40af; margin-left: 4px;">✓</span>
                        @endif
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="5" style="padding-top: 12px; font-weight: 700; text-align: right; border-top: 2px solid #e5e7eb;">Total Paid</td>
                      <td style="padding-top: 12px; font-weight: 700; color: #16a34a; border-top: 2px solid #e5e7eb;">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format($paidAmount ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                      <td colspan="5" style="padding-top: 4px; font-weight: 700; text-align: right;">Balance Due</td>
                      <td style="padding-top: 4px; font-weight: 700; color: #dc2626;">{{ \App\Helpers\SystemHelper::currencySymbol() }} {{ number_format(max(0, $invoice->total_amount - ($paidAmount ?? 0)), 2) }}</td>
                    </tr>
                  </tfoot>
                </table>
              @else
                <p style="color: #6b7280; font-size: 13px;">No payments recorded for this invoice.</p>
              @endif
            </div>

            <!-- Footer -->
            <div class="print-footer">
              Thank you for your business
            </div>
          </div>
          <script>
            window.onload = function() {
              window.print();
              setTimeout(function() { window.close(); }, 500);
            };
          <\/script>
        </body>
        </html>
      `);
      printWindow.document.close();
    }
  }));
});
</script>

<style>
[x-cloak] { display: none !important; }
@media print {
  .no-print { display: none !important; }
  body { background: white; padding: 0; margin: 0; }
  .xl\:w-4\/5 { width: 100% !important; }
}
.z-99999 { z-index: 99999 !important; }
.backdrop-blur-\[32px\] { backdrop-filter: blur(32px); }
</style>
@endsection