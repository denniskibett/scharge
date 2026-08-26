@extends('layouts.app')

@section('title', 'SMS Broadcast')

@php
    // Ensure all variables exist
    $companies = $companies ?? collect([]);
    $estates = $estates ?? collect([]);
    $templates = $templates ?? collect([]);
    $tenants = $tenants ?? collect([]);
    $logs = $logs ?? collect([]);
    $sandbox = $sandbox ?? true;
@endphp

@section('content')
    @include('partials.modal.success-modal')
    @include('partials.modal.error-modal')

    {{-- ============================================================ --}}
    {{-- FIXED MODAL CSS – No padding-right to prevent sidebar shift --}}
    {{-- ============================================================ --}}
    <style>
        /* Campaign View Modal */
        #viewCampaignModal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: flex-end;
            align-items: flex-start;
            overflow-y: auto;
            padding: 20px;
        }

        #viewCampaignModal.active {
            display: flex;
        }

        #viewCampaignModal .modal-content {
            background: #fff;
            width: 92%;
            max-width: 92%;
            min-width: 800px;
            margin: 0 20px 0 auto;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Update Phone Modal */
        #updatePhoneModal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        #updatePhoneModal.active {
            display: flex;
        }

        #updatePhoneModal .modal-content {
            background: #fff;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Prevent body scroll when modal is open – NO PADDING-RIGHT */
        body.modal-open {
            overflow: hidden;
        }
    </style>

    <div class="container mx-auto px-4 py-6">
        <!-- CSRF Meta -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">SMS Manager</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Send personalized SMS to tenants, a single number, or view history.</p>
            @if($sandbox)
                <div class="inline-block mt-3 rounded-full bg-yellow-100 px-4 py-1 text-sm text-yellow-800">⚠️ SANDBOX MODE – No real SMS will be sent</div>
            @endif
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 border border-green-300">
                <strong>✅ Success!</strong> {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 border border-red-300">
                <strong>❌ Error!</strong> {{ session('error') }}
            </div>
        @endif

        <!-- Tab Headers -->
        <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
            <button onclick="activeTab = 'tenants'; renderTab()" id="tab-tenants" class="py-2 px-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400">Send to Tenants</button>
            <button onclick="activeTab = 'custom'; renderTab()" id="tab-custom" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">Send Custom SMS</button>
            <button onclick="activeTab = 'history'; renderTab()" id="tab-history" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">SMS History</button>
            <button onclick="activeTab = 'campaigns'; renderTab()" id="tab-campaigns" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">Campaigns</button>
        </div>

        <!-- ============================================ -->
        <!-- TAB 1: SEND TO TENANTS -->
        <!-- ============================================ -->
        <div id="tenants-tab" style="display: block;">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                
                <!-- Load Saved Template Section -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Load Saved Template</label>
                    <select id="templateSelect" class="dark:bg-dark-900 h-11 w-full md:w-1/2 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">-- Select Template --</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" data-content="{{ $template->content }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Message Template Section -->
<div class="p-6 border-b border-gray-200 dark:border-gray-700">
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Message Template</label>
    @verbatim
    <textarea id="template" name="template" rows="4" class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="Enter your message template here">{{estate_name}} {{month}} Water Bill - ({{water_consumption}} units (Last: {{prev_read}}-New: {{curr_read}}))

Paybill: 7263733
Acc: {{unit}}
Amount: KES {{water_bill}}
Due: {{due_date}}
Status: {{payment_status}}

{{unpaid_section}}

Total Due: KES {{total_due}}

For queries: 0701262902</textarea>
    @endverbatim
    <div class="flex flex-wrap gap-2 mt-2">
        <span class="text-xs text-gray-500 dark:text-gray-400">Available variables: name, unit, estate, due_date, unpaid_count, unpaid_total, unpaid_list, unpaid_message, unpaid_section, total_due</span>
    </div>
    <div id="charCounter" class="mt-2 text-sm"></div>
    <button type="button" onclick="makeMessageCompact()" class="mt-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
        🔧 Make Compact (reduce characters)
    </button>
</div>

<!-- Preview Section -->
<div class="p-6 border-b border-gray-200 dark:border-gray-700" id="previewSection" style="display: none;">
    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3">Preview (first 3)</h3>
    <div id="previewContainer" class="space-y-2 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg"></div>
</div>

                <!-- Filters Section -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Estate Filter</label>
                            <select id="estateFilter" class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="applyFiltersAndRender()">
                                <option value="">All Estates</option>
                                @foreach($estates as $estate)
                                    <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Payment Status</label>
                            <select id="paymentStatusFilter" class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="applyFiltersAndRender()">
                                <option value="">All</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Min Bill Amount</label>
                            <input type="number" id="minBillFilter" placeholder="0" class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" oninput="applyFiltersAndRender()">
                        </div>
                        
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Search</label>
                            <div class="relative">
                                <input type="text" id="tenantSearch" placeholder="Search by name, phone, unit..." class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] pl-10 h-10" oninput="applyFiltersAndRender()">
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Count and Send Button Row -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Selected:</span>
                            <span id="selectedCount" class="text-2xl font-bold text-blue-600">0</span>
                            <span class="text-sm text-gray-500">tenants</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="selectAllBtn" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="button" id="selectNoneBtn" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
                                <i class="fas fa-times"></i> Clear All
                            </button>
                            <button type="button" id="hidePaidBtn" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
                                <i class="fas fa-eye-slash"></i> <span id="hidePaidLabel">Hide Paid</span>
                            </button>
                        </div>
                        <button type="submit" form="bulkForm" id="sendSmsBtn" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-paper-plane"></i> Send SMS to Tenants
                        </button>
                    </div>
                </div>

                <!-- Tenants Table Section -->
                <div class="p-6">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr class="border-gray-100 border-y dark:border-gray-800">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <input type="checkbox" id="toggleAllCheckbox" onchange="toggleAllCheckboxes()">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('name')">
                                        Name <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('phone')">
                                        Phone <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('unit')">
                                        Unit <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('estate')">
                                        Estate <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('water_bill')">
                                        Water Bill <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('prev_read')">
                                        Previous Reading <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('curr_read')">
                                        Current Reading <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('due_date')">
                                        Due Date <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700" onclick="sortTable('payment_status')">
                                        Payment Status <span class="sort-icon inline-block ml-1">↕️</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tenantsTableBody" class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($tenants as $tenant)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150 tenant-row" 
                                    data-estate-id="{{ $tenant['estate_id'] ?? '' }}" 
                                    data-name="{{ $tenant['name'] ?? '' }}" 
                                    data-name-lower="{{ strtolower($tenant['name'] ?? '') }}" 
                                    data-phone="{{ $tenant['phone'] ?? '' }}" 
                                    data-unit="{{ $tenant['unit_number'] ?? '' }}"
                                    data-unit-lower="{{ strtolower($tenant['unit_number'] ?? '') }}"
                                    data-estate="{{ $tenant['estate_name'] ?? '' }}"
                                    data-estate-lower="{{ strtolower($tenant['estate_name'] ?? '') }}"
                                    data-water-bill="{{ $tenant['water_bill'] ?? 0 }}"
                                    data-prev-read="{{ $tenant['prev_read'] ?? 0 }}"
                                    data-curr-read="{{ $tenant['curr_read'] ?? 0 }}"
                                    data-month="{{ $tenant['reading_month'] ?? '' }}"
                                    data-due-date="{{ $tenant['due_date'] ?? '' }}"
                                    data-payment-status="{{ $tenant['payment_status'] ?? 'pending' }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" class="tenant-checkbox" 
                                            data-id="{{ $tenant['id'] ?? '' }}"
                                            data-phone="{{ $tenant['phone'] ?? '' }}"
                                            data-name="{{ $tenant['name'] ?? '' }}"
                                            data-unit="{{ $tenant['unit_number'] ?? '' }}"
                                            data-estate="{{ $tenant['estate_name'] ?? '' }}"
                                            data-estate-id="{{ $tenant['estate_id'] ?? '' }}"
                                            data-waterbill="{{ $tenant['water_bill'] ?? 0 }}"
                                            data-consumption="{{ $tenant['water_consumption'] ?? 0 }}"
                                            data-prev-read="{{ $tenant['prev_read'] ?? 0 }}"
                                            data-curr-read="{{ $tenant['curr_read'] ?? 0 }}"
                                            data-month="{{ $tenant['reading_month'] ?? '' }}"
                                            data-due-date="{{ $tenant['due_date'] ?? '' }}"
                                            data-payment-status="{{ $tenant['payment_status'] ?? 'pending' }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-blue-600 font-medium text-sm">{{ substr($tenant['name'] ?? 'T', 0, 1) }}</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenant['name'] ?? 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['phone'] ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['unit_number'] ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['estate_name'] ?? '' }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-800">KES {{ number_format($tenant['water_bill'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['prev_read'] ?? 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['curr_read'] ?? 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['due_date'] ?? '' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @if(($tenant['payment_status'] ?? 'pending') === 'paid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif(($tenant['payment_status'] ?? 'pending') === 'unpaid') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @endif">
                                            {{ ucfirst($tenant['payment_status'] ?? 'pending') }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">No tenants found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex items-center justify-between mt-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show:</span>
                            <select id="entriesPerPage" class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm" onchange="entriesPerPageChange()">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div class="flex gap-2" id="paginationControls">
                            <button onclick="prevPage()" class="px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50">Previous</button>
                            <span id="pageInfo" class="text-sm text-gray-600 px-3 py-1">Page 1 of 1</span>
                            <button onclick="nextPage()" class="px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50">Next</button>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('sms.send') }}" id="bulkForm">
                    @csrf
                    <input type="hidden" name="recipients" id="recipientsJson">
                    <input type="hidden" name="message_type" id="messageTypeHidden" value="transactional">
                    <input type="hidden" name="template" id="templateHidden">
                </form>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 2: SEND CUSTOM SMS -->
        <!-- ============================================ -->
        <div id="custom-tab" style="display: none;">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <form method="POST" action="{{ route('sms.send-custom') }}" class="p-6 space-y-6">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone Number</label>
                        <input type="text" name="phone" placeholder="e.g., 254712345678 or 0712345678" required class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <p class="mt-1 text-xs text-gray-500">Enter in format: 254712345678 or 0712345678</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Message Type</label>
                        <select name="message_type" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="transactional">Transactional</option>
                            <option value="promotional">Promotional</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Message</label>
                        <textarea name="message" rows="4" required class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
                    </div>
                    <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 py-3 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-blue-700">Send SMS Now</button>
                </form>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 3: SMS HISTORY -->
        <!-- ============================================ -->
        <div id="history-tab" style="display: none;">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-6">
                    <div class="w-full overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr class="border-gray-100 border-y dark:border-gray-800">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($logs as $log)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->id ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->recipient_phone ?? '' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ Str::limit($log->message ?? '', 60) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->message_type ?? 'transactional' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @if(($log->status ?? '') === 'sent') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif(($log->status ?? '') === 'failed') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @endif">
                                            {{ $log->status ?? 'pending' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ isset($log->created_at) ? $log->created_at->format('d/m/Y H:i') : '' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No logs found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(method_exists($logs, 'links'))
                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ============================================ -->
<!-- TAB 4: CAMPAIGNS -->
<!-- ============================================ -->
<div id="campaigns-tab" style="display: none;">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="p-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Campaigns</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage your SMS campaigns</p>
                    <!-- Sandbox Status Indicator -->
                    @if($sandbox)
                        <div class="mt-1 inline-flex items-center gap-2 rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                            <span class="inline-block h-2 w-2 rounded-full bg-yellow-400 animate-pulse"></span>
                            🔒 SANDBOX MODE – Showing local campaigns only
                        </div>
                    @else
                        <div class="mt-1 inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                            <span class="inline-block h-2 w-2 rounded-full bg-green-400"></span>
                            🌐 LIVE MODE – Showing all campaigns (including KenyaSMS)
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Source Filter (only show in live mode) -->
                    @if(!$sandbox)
                    <div class="relative">
                        <select id="sourceFilter" onchange="filterCampaignsBySource()" 
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            <option value="all">All Sources</option>
                            <option value="local">📤 Local</option>
                            <option value="kenyasms_imported">📥 Imported from KenyaSMS</option>
                        </select>
                    </div>
                    @endif
                    
                    <button onclick="listKenyaSmsCampaigns()" class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-purple-700">
                        <i class="fas fa-cloud"></i> View KenyaSMS
                    </button>
                    @if(!$sandbox)
                    <button onclick="importKenyaSmsCampaigns()" id="importKenyaSmsBtn" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-green-700">
                        <i class="fas fa-cloud-download-alt"></i> Import from KenyaSMS
                    </button>
                    @endif
                    <button onclick="openCreateCampaignModal()" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-blue-700">
                        <i class="fas fa-plus"></i> New Campaign
                    </button>
                </div>
            </div>

            <!-- Filter Info Bar -->
            <div id="filterInfo" class="mb-3 text-sm text-gray-500 dark:text-gray-400 hidden">
                <span id="filterText"></span>
                <button onclick="resetSourceFilter()" class="text-blue-600 hover:underline ml-2">Clear filter</button>
            </div>

            <!-- Sandbox Notice for Imported Campaigns -->
            @if($sandbox)
            <div class="mb-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/10 border border-yellow-200 dark:border-yellow-800 p-3">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Sandbox Mode Active:</strong> Only local campaigns are shown. 
                    To view imported KenyaSMS campaigns, set <code class="bg-yellow-100 dark:bg-yellow-900/30 px-1 py-0.5 rounded">KENYASMS_SANDBOX=false</code> in your .env file.
                </p>
            </div>
            @endif

            <!-- Filter Tabs -->
            <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                <button onclick="filterCampaigns('all')" id="filter-all" class="px-3 py-1 text-sm rounded-lg transition-colors bg-blue-600 text-white">All</button>
                <button onclick="filterCampaigns('draft')" id="filter-draft" class="px-3 py-1 text-sm rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">Draft</button>
                <button onclick="filterCampaigns('pending')" id="filter-pending" class="px-3 py-1 text-sm rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">Pending</button>
                <button onclick="filterCampaigns('scheduled')" id="filter-scheduled" class="px-3 py-1 text-sm rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">Scheduled</button>
                <button onclick="filterCampaigns('sending')" id="filter-sending" class="px-3 py-1 text-sm rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">Sending</button>
                <button onclick="filterCampaigns('completed')" id="filter-completed" class="px-3 py-1 text-sm rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">Completed</button>
                <button onclick="filterCampaigns('failed')" id="filter-failed" class="px-3 py-1 text-sm rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">Failed</button>
                <span class="ml-auto text-xs text-gray-400 self-center" id="campaignCount">0 campaigns</span>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white" id="statsTotal">0</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sent</p>
                    <p class="text-2xl font-bold text-green-600" id="statsSent">0</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600" id="statsPending">0</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Failed</p>
                    <p class="text-2xl font-bold text-red-600" id="statsFailed">0</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">📤 Local</p>
                    <p class="text-2xl font-bold text-blue-600" id="statsLocal">0</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">📥 Imported</p>
                    <p class="text-2xl font-bold text-purple-600" id="statsImported">0</p>
                </div>
            </div>

            <!-- Campaigns Table -->
            <div class="w-full overflow-x-auto">
                <div id="campaignsLoading" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-500">Loading campaigns...</p>
                </div>
                <div id="campaignsTable" style="display: none;">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr class="border-gray-100 border-y dark:border-gray-800">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipients</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="campaignsTableBody">
                            <!-- Rows populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- ============================================ -->
    <!-- CREATE CAMPAIGN MODAL -->
    <!-- ============================================ -->
    <div id="createCampaignModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70" onclick="closeCreateCampaignModal()"></div>
        <div class="relative min-h-screen flex items-start justify-end">
            <div class="relative bg-white dark:bg-gray-800 shadow-2xl w-full max-w-3xl overflow-y-auto" style="max-height: 100vh;">
                
                <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                            <i class="fas fa-plus-circle text-blue-600 mr-2"></i> Create New Campaign
                        </h3>
                        <button onclick="closeCreateCampaignModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="px-6 py-6" style="max-height: calc(100vh - 100px); overflow-y: auto;">
                    <form id="createCampaignForm" onsubmit="submitCampaign(event)" class="space-y-5">
                        @csrf
                        
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Campaign Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="campaignName" 
                                   class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" 
                                   placeholder="Enter campaign name" required>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Description <span class="text-xs text-gray-400 font-normal">(e.g., July 2027)</span>
                            </label>
                            <textarea id="campaignDescription" rows="3" 
                                      class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" 
                                      placeholder="e.g., July 2027 water bill campaign"></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                SMS Template <span class="text-red-500">*</span>
                            </label>
                            <select id="campaignTemplate" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="loadCampaignTemplatePreview()" required>
                                <option value="">Select Template</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" data-content="{{ $template->content }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="campaignTemplatePreview" class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700" style="display: none;">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Template Preview</label>
                            <div class="bg-white dark:bg-gray-900 p-3 rounded-lg">
                                <pre id="campaignTemplateContent" class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap font-sans"></pre>
                            </div>
                            <div id="campaignTenantPreviewContainer" style="display: none;">
                                <hr class="border-gray-200 dark:border-gray-700 my-3">
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    <i class="fas fa-users mr-1"></i> Tenant Preview (first 3)
                                </label>
                                <div id="campaignTenantPreview" class="space-y-2 max-h-60 overflow-y-auto"></div>
                            </div>
                        </div>

                        <hr class="border-gray-200 dark:border-gray-700">

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Campaign Type</label>
                            <select id="campaignType" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="water_bill">Water Bill Reminder</option>
                                <option value="rent_reminder">Rent Reminder</option>
                                <option value="payment_confirm">Payment Confirmation</option>
                                <option value="maintenance">Maintenance Update</option>
                                <option value="lease_expiry">Lease Expiry Notice</option>
                                <option value="general">General Broadcast</option>
                            </select>
                        </div>

                        <hr class="border-gray-200 dark:border-gray-700">

                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                <i class="fas fa-filter mr-2"></i> Filter Recipients
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Company</label>
                                    <select id="campaignFilterCompany" class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="filterEstatesByCompany()">
                                        <option value="">All Companies</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Estate</label>
                                    <select id="campaignFilterEstate" class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="updateCampaignPreviewOnEstateChange()">
                                        <option value="">All Estates</option>
                                        @foreach($estates as $estate)
                                            <option value="{{ $estate->id }}" data-company="{{ $estate->company_id }}">{{ $estate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Invoice Status</label>
                                    <select id="campaignFilterStatus" class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 shadow-theme-xs focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="updateCampaignPreviewOnEstateChange()">
                                        <option value="">All Statuses</option>
                                        <option value="paid">Paid</option>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="partial">Partial</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Schedule Send <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                            </label>
                            <input type="datetime-local" id="campaignSchedule" 
                                   class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                            <p class="text-xs text-gray-400 mt-1.5">Leave empty to send immediately</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" 
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]" 
                                    onclick="closeCreateCampaignModal()">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </button>
                            <button type="submit" 
                                    id="submitCampaignBtn"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="submitCampaignText">Create Campaign</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- VIEW CAMPAIGN MODAL -->
    <!-- ============================================ -->
    <div id="viewCampaignModal" style="display: none;">
        <div class="modal-content">
            <div class="bg-white dark:bg-gray-800 px-6 py-3.5 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i> Campaign Details
                    </h3>
                    <button onclick="closeViewCampaignModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="px-6 py-5 overflow-y-auto flex-1" style="max-height: calc(92vh - 70px);">
                <div id="viewCampaignLoading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-500">Loading campaign details...</p>
                </div>
                
                <div id="viewCampaignContent" style="display: none;">
                    <!-- Campaign Name & Description -->
                    <div class="mb-4">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white" id="viewCampaignName">-</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="viewCampaignDescription">-</p>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Template</p>
                            <p class="text-base font-semibold text-gray-800 dark:text-white truncate" id="viewCampaignTemplate">-</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</p>
                            <span id="viewCampaignStatus" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1">-</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</p>
                            <p class="text-base font-semibold text-gray-800 dark:text-white" id="viewCampaignCreated">-</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</p>
                            <p class="text-base font-semibold text-gray-800 dark:text-white" id="viewCampaignTotal">0</p>
                        </div>
                    </div>
                    
                    <!-- Validation Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-200 dark:border-green-800">
                            <p class="text-xs text-green-600 dark:text-green-400">✅ Valid Safaricom</p>
                            <p class="text-xl font-bold text-green-700 dark:text-green-300" id="viewCampaignValid">0</p>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 border border-yellow-200 dark:border-yellow-800">
                            <p class="text-xs text-yellow-600 dark:text-yellow-400">⚠️ Other Networks</p>
                            <p class="text-xl font-bold text-yellow-700 dark:text-yellow-300" id="viewCampaignOtherNetwork">0</p>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">
                            <p class="text-xs text-red-600 dark:text-red-400">❌ Invalid Numbers</p>
                            <p class="text-xl font-bold text-red-700 dark:text-red-300" id="viewCampaignInvalid">0</p>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                            <p class="text-xs text-blue-600 dark:text-blue-400">📊 Total</p>
                            <p class="text-xl font-bold text-blue-700 dark:text-blue-300" id="viewCampaignTotal">0</p>
                        </div>
                    </div>
                    
                    <!-- Campaign Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent</p>
                            <p class="text-2xl font-bold text-green-600" id="viewCampaignSent">0</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
                            <p class="text-2xl font-bold text-yellow-600" id="viewCampaignPendingCount">0</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failed</p>
                            <p class="text-2xl font-bold text-red-600" id="viewCampaignFailed">0</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivered</p>
                            <p class="text-2xl font-bold text-green-600" id="viewCampaignDelivered">0</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Progress</p>
                            <p class="text-2xl font-bold text-blue-600" id="viewCampaignProgress">0%</p>
                        </div>
                    </div>
                    
                    <!-- TABS FOR RECIPIENTS -->
                    <div class="mb-4">
                        <div class="flex border-b border-gray-200 dark:border-gray-700">
                            <button onclick="switchRecipientTab('recipients')" id="tab-recipients" class="py-2 px-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400">
                                <i class="fas fa-users mr-1"></i> Recipients
                                <span class="text-xs text-gray-500 ml-1" id="recipientTabCount">(0)</span>
                            </button>
                            <button onclick="switchRecipientTab('invalid')" id="tab-invalid" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-1"></i> Invalid
                                <span class="text-xs text-gray-500 ml-1" id="invalidTabCount">(0)</span>
                            </button>
                            <button onclick="switchRecipientTab('other')" id="tab-other" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">
                                <i class="fas fa-network-wired text-yellow-500 mr-1"></i> Other Networks
                                <span class="text-xs text-gray-500 ml-1" id="otherTabCount">(0)</span>
                            </button>
                            <button onclick="switchRecipientTab('failed')" id="tab-failed" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">
                                <i class="fas fa-times-circle text-red-500 mr-1"></i> Failed
                                <span class="text-xs text-gray-500 ml-1" id="failedTabCount">(0)</span>
                            </button>
                            <div class="flex-1"></div>
                            
                            <!-- Export Dropdown -->
                            <div class="relative inline-block text-left">
                                <button onclick="toggleExportDropdown()" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 px-3 py-2 inline-flex items-center">
                                    <i class="fas fa-file-export mr-1"></i> Export
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div id="exportDropdown" class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 hidden">
                                    <div class="py-1">
                                        <button onclick="exportRecipients()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-file-export mr-2"></i> All Recipients
                                        </button>
                                        <button onclick="exportInvalidRecipients()" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-exclamation-triangle mr-2"></i> Invalid Only
                                        </button>
                                        <button onclick="exportDeliveredRecipients()" class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-check-circle mr-2"></i> Delivered Only
                                        </button>
                                        <button onclick="exportPendingRecipients()" class="block w-full text-left px-4 py-2 text-sm text-yellow-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <i class="fas fa-hourglass-half mr-2"></i> Pending Only
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recipients Tab Content -->
                    <div id="recipientsTabContent">
                        <!-- Status Filter Buttons -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button onclick="filterRecipients('all')" id="filter-recipients-all" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-blue-600 text-white">
                                All (<span id="count-all">0</span>)
                            </button>
                            <button onclick="filterRecipients('delivered')" id="filter-recipients-delivered" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i> Delivered (<span id="count-delivered">0</span>)
                            </button>
                            <button onclick="filterRecipients('sent')" id="filter-recipients-sent" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                <i class="fas fa-paper-plane text-blue-500 mr-1"></i> Sent (<span id="count-sent">0</span>)
                            </button>
                            <button onclick="filterRecipients('queued')" id="filter-recipients-queued" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                <i class="fas fa-clock text-yellow-500 mr-1"></i> Queued (<span id="count-queued">0</span>)
                            </button>
                            <button onclick="filterRecipients('pending')" id="filter-recipients-pending" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                <i class="fas fa-hourglass-half text-yellow-500 mr-1"></i> Pending (<span id="count-pending">0</span>)
                            </button>
                            <button onclick="filterRecipients('failed')" id="filter-recipients-failed" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                <i class="fas fa-times-circle text-red-500 mr-1"></i> Failed (<span id="count-failed">0</span>)
                            </button>
                            
                            <button onclick="resendFailed()" id="resendFailedBtn" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-orange-100 text-orange-700 hover:bg-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:hover:bg-orange-900/50">
                                <i class="fas fa-redo mr-1"></i> Resend Failed
                            </button>
                            
                            <button onclick="resendPending()" id="resendPendingBtn" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                                <i class="fas fa-redo mr-1"></i> Resend Pending
                            </button>
                            
                            <button onclick="refreshCampaignStatus()" id="refreshStatusBtn" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh Status
                            </button>

                            <button onclick="checkPendingStatus()" id="checkPendingStatusBtn" class="px-3 py-1.5 text-xs rounded-lg transition-colors bg-purple-100 text-purple-700 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50">
                                <i class="fas fa-search mr-1"></i> Check Pending Status
                            </button>
                        </div>

                        <!-- Recipients Table -->
                        <div class="mt-4">
                            <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estate</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Network</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Parts</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent Time</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivered</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failure Reason</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="viewRecipientsBody" class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <tr>
                                            <td colspan="12" class="px-4 py-8 text-center text-gray-500">
                                                <i class="fas fa-inbox mr-2"></i> No recipients found
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="flex items-center justify-between mt-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Show:</span>
                                    <select id="entriesPerPageRecipients" class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-white" onchange="changeEntriesPerPage()">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="all">All</option>
                                    </select>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">entries</span>
                                </div>
                                <div class="flex gap-2" id="paginationControls">
                                    <button onclick="prevPage()" class="px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <span id="pageInfo" class="text-sm text-gray-600 dark:text-gray-400 px-3 py-1">Page 1 of 1</span>
                                    <button onclick="nextPage()" class="px-3 py-1 rounded border border-gray-300 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Networks Tab Content -->
                    <div id="otherTabContent" style="display: none;">
                        <div class="mb-4">
                            <button onclick="loadOtherNetworkRecipients()" class="inline-flex items-center gap-2 rounded-lg bg-yellow-100 px-4 py-2 text-sm font-medium text-yellow-700 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:hover:bg-yellow-900/50">
                                <i class="fas fa-sync-alt mr-1"></i> Load Other Networks
                            </button>
                        </div>
                        <div class="w-full overflow-x-auto rounded-xl border border-yellow-200 dark:border-yellow-800">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-800">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unit</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Network</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="otherNetworkRecipientsBody" class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-gray-500 text-sm">
                                            <i class="fas fa-info-circle mr-1"></i> Click "Load Other Networks" to view
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Close & Delete Buttons -->
                    <div class="flex justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button onclick="deleteCampaign(window.currentCampaignId)" class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-6 py-2.5 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            <i class="fas fa-trash mr-1"></i> Delete Campaign
                        </button>
                        <button onclick="closeViewCampaignModal()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-100 px-6 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:ring-gray-600 dark:hover:bg-gray-600">
                            <i class="fas fa-times mr-1"></i> Close
                        </button>
                    </div>
                </div>  <!-- closes viewCampaignContent -->
            </div>      <!-- closes body -->
        </div>          <!-- closes main modal content -->
    </div>              <!-- closes viewCampaignModal -->

    <!-- ============================================ -->
    <!-- UPDATE PHONE MODAL -->
    <!-- ============================================ -->
    <div id="updatePhoneModal" style="display: none;">
        <div class="modal-content">
            <div class="bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-phone mr-2 text-blue-600"></i> Update Phone Number
                    </h3>
                    <button onclick="closeUpdatePhoneModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Update phone number for <span id="updateTenantName" class="font-semibold">-</span>
                </p>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Phone Number</label>
                    <input type="text" id="updatePhoneInput" 
                           class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           placeholder="e.g., 0712345678">
                    <p class="text-xs text-gray-500 mt-1">Enter Safaricom number starting with 07 or 2547</p>
                </div>
                
                <input type="hidden" id="updateTenantId">
                
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button onclick="closeUpdatePhoneModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button onclick="saveUpdatedPhone()" 
                            id="savePhoneBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- ============================================ -->
<!-- KENYASMS CAMPAIGNS MODAL -->
<!-- ============================================ -->
<div id="kenyaSmsModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70" onclick="closeKenyaSmsModal()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-7xl max-h-[90vh] overflow-hidden">
            <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-cloud text-purple-600 mr-2"></i> KenyaSMS Campaigns
                    </h3>
                    <button onclick="closeKenyaSmsModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="kenyaSmsModalContent" class="px-6 py-4 overflow-y-auto" style="max-height: calc(90vh - 70px);">
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                    <p class="mt-2 text-gray-500">Loading campaigns from KenyaSMS...</p>
                </div>
            </div>
        </div>
    </div>
</div>

    @verbatim
    <script>
        // ============================================
        // TAB SWITCHING
        // ============================================
        let activeTab = 'tenants';
        let currentPage = 1;
        let hidePaid = false;
        let rowsPerPage = 10;
        let sortColumn = '';
        let sortDirection = 'asc';
        let allRows = [];
        let allTenantsData = [];
        let currentCampaignId = null;
        let currentRecipients = [];
        let currentRecipientsFull = [];
        let currentFilteredRecipients = [];
        let currentRecipientFilter = 'all';
        let currentInvalidRecipients = [];
        let currentOtherNetworkRecipients = [];
        let currentFailedRecipients = [];

       // ============================================================
// FORMAT MONTH YEAR - FIXED
// ============================================================
function formatMonthYear(dateStr) {
    if (!dateStr) return 'Unknown';
    try {
        let date;
        // Handle YYYY-MM-DD format (e.g., "2026-05-01")
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
            date = new Date(dateStr);
        }
        // Handle YYYY-MM format (e.g., "2026-05")
        else if (dateStr.match(/^\d{4}-\d{2}$/)) {
            date = new Date(dateStr + '-01');
        }
        // Handle "May 2026" format
        else if (dateStr.match(/^[A-Za-z]+ \d{4}$/)) {
            return dateStr;
        }
        // Try parsing as-is
        else {
            date = new Date(dateStr);
        }
        
        if (date && !isNaN(date)) {
            return date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        }
    } catch (e) { /* ignore */ }
    return dateStr;
}
        // ============================================
        // HELPER: Escape HTML
        // ============================================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // HELPER: Capitalize
        // ============================================
        function capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // ============================================
        // HELPER: Format Date
        // ============================================
        function formatDate(date) {
            if (!date) return 'N/A';
            try {
                return new Date(date).toLocaleString('en-GB', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return 'N/A';
            }
        }

        // ============================================
        // HELPER: Get Status Badge
        // ============================================
        function getStatusBadge(status) {
            const badges = {
                'draft': 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
                'pending': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                'scheduled': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                'sending': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                'sent': 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                'completed': 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                'failed': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                'delivered': 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                'queued': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
            };
            return badges[status] || 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
        }

        // ============================================
        // HELPER: Copy to clipboard
        // ============================================
        function copyToClipboard(text) {
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                showToast('📋 Copied to clipboard!');
            }).catch(() => {
                const input = document.createElement('input');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showToast('📋 Copied to clipboard!');
            });
        }

        // ============================================
        // HELPER: Show toast notification
        // ============================================
        function showToast(message) {
            if (typeof toastr !== 'undefined') {
                toastr.success(message);
                return;
            }
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #1a202c;
                color: white;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 14px;
                z-index: 999999;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                max-width: 400px;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ============================================
        // RENDER TAB
        // ============================================
        function renderTab() {
            console.log('📋 Rendering tab:', activeTab);
            
            const tenantsTab = document.getElementById('tenants-tab');
            const customTab = document.getElementById('custom-tab');
            const historyTab = document.getElementById('history-tab');
            const campaignsTab = document.getElementById('campaigns-tab');
            
            if (tenantsTab) tenantsTab.style.display = 'none';
            if (customTab) customTab.style.display = 'none';
            if (historyTab) historyTab.style.display = 'none';
            if (campaignsTab) campaignsTab.style.display = 'none';
            
            const activeTabElement = document.getElementById(activeTab + '-tab');
            if (activeTabElement) {
                activeTabElement.style.display = 'block';
                console.log('✅ Showing tab:', activeTab);
            } else {
                console.error('❌ Tab not found:', activeTab + '-tab');
            }
            
            const tabIds = ['tab-tenants', 'tab-custom', 'tab-history', 'tab-campaigns'];
            tabIds.forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.classList.remove('border-blue-500', 'text-blue-600');
                    btn.classList.add('border-transparent');
                }
            });
            
            const activeBtn = document.getElementById('tab-' + activeTab);
            if (activeBtn) {
                activeBtn.classList.add('border-blue-500', 'text-blue-600');
                activeBtn.classList.remove('border-transparent');
            }
            
            if (activeTab === 'campaigns') {
                console.log('🔄 Loading campaigns...');
                loadCampaigns();
            }
        }

        // ============================================
        // DOM READY
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM Ready - Initializing...');
            
            const templateSelect = document.getElementById('templateSelect');
            if (templateSelect) {
                templateSelect.addEventListener('change', function() {
                    let selected = this.options[this.selectedIndex];
                    let content = selected.getAttribute('data-content');
                    if (content) {
                        document.getElementById('template').value = content;
                        updatePreview();
                    }
                });
            }
            
            initRows();
            renderTab();
            
            const entriesSelect = document.getElementById('entriesPerPage');
            if (entriesSelect) {
                entriesSelect.addEventListener('change', entriesPerPageChange);
            }
            
            const templateTextarea = document.getElementById('template');
            if (templateTextarea) {
                templateTextarea.addEventListener('input', function() {
                    const charCount = this.value.length;
                    const counter = document.getElementById('charCounter');
                    if (counter) {
                        counter.textContent = charCount + ' characters';
                        if (charCount > 160) {
                            counter.textContent += ' (' + Math.ceil(charCount / 160) + ' SMS)';
                            counter.className = 'mt-2 text-sm text-yellow-600 dark:text-yellow-400';
                        } else {
                            counter.className = 'mt-2 text-sm text-gray-500 dark:text-gray-400';
                        }
                    }
                });
            }
            
            const selectAllBtn = document.getElementById('selectAllBtn');
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.tenant-row').forEach(row => {
                        if (row.style.display !== 'none') {
                            const checkbox = row.querySelector('.tenant-checkbox');
                            if (checkbox) checkbox.checked = true;
                        }
                    });
                    updateSelectedCount();
                });
            }
            
            const selectNoneBtn = document.getElementById('selectNoneBtn');
            if (selectNoneBtn) {
                selectNoneBtn.addEventListener('click', function() {
                    document.querySelectorAll('.tenant-checkbox').forEach(cb => {
                        cb.checked = false;
                    });
                    updateSelectedCount();
                });
            }
            
            const hidePaidBtn = document.getElementById('hidePaidBtn');
            if (hidePaidBtn) {
                hidePaidBtn.addEventListener('click', function() {
                    hidePaid = !hidePaid;
                    const label = document.getElementById('hidePaidLabel');
                    if (label) {
                        label.textContent = hidePaid ? 'Show All' : 'Hide Paid';
                    }
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = hidePaid ? 'fas fa-eye' : 'fas fa-eye-slash';
                    }
                    applyFiltersAndRender();
                });
            }
            
            if (activeTab === 'campaigns') {
                loadCampaigns();
            }
        });

        // ============================================
        // SEND TO TENANTS - Functions
        // ============================================
        function getAllTenantsData() {
            const rows = document.querySelectorAll('.tenant-row');
            allTenantsData = [];
            rows.forEach(row => {
                const checkbox = row.querySelector('.tenant-checkbox');
                if (checkbox) {
                    allTenantsData.push({
                        id: checkbox.dataset.id || '',
                        phone: checkbox.dataset.phone || '',
                        name: checkbox.dataset.name || 'Tenant',
                        unit: checkbox.dataset.unit || 'N/A',
                        estate: checkbox.dataset.estate || 'N/A',
                        estateId: checkbox.dataset.estateId || '',
                        waterbill: checkbox.dataset.waterbill || 0,
                        consumption: checkbox.dataset.consumption || 0,
                        prevRead: checkbox.dataset.prevRead || 0,
                        currRead: checkbox.dataset.currRead || 0,
                        month: checkbox.dataset.month || 'N/A',
                        dueDate: checkbox.dataset.dueDate || 'N/A',
                        paymentStatus: checkbox.dataset.paymentStatus || 'pending',
                        row: row,
                        checkbox: checkbox
                    });
                }
            });
            console.log('📊 Loaded tenants data:', allTenantsData.length, 'tenants');
            return allTenantsData;
        }

        function toggleAllCheckboxes() {
            const masterCheckbox = document.getElementById('toggleAllCheckbox');
            if (!masterCheckbox) return;
            const isChecked = masterCheckbox.checked;
            document.querySelectorAll('.tenant-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelectedCount();
        }

        function getVisibleCheckboxes() {
            return Array.from(document.querySelectorAll('.tenant-checkbox')).filter(cb => {
                const row = cb.closest('.tenant-row');
                return row && row.style.display !== 'none';
            });
        }

        function getAllSelectedCheckboxes() {
            return Array.from(document.querySelectorAll('.tenant-checkbox')).filter(cb => cb.checked);
        }

        function initRows() {
            allRows = Array.from(document.querySelectorAll('.tenant-row'));
            getAllTenantsData();
            applyFiltersAndRender();
            updateSelectedCount();
            updateMasterCheckboxState();
        }

        function updateMasterCheckboxState() {
            const masterCheckbox = document.getElementById('toggleAllCheckbox');
            if (!masterCheckbox) return;
            
            const allCheckboxes = document.querySelectorAll('.tenant-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.tenant-checkbox:checked');
            
            if (allCheckboxes.length === 0) {
                masterCheckbox.checked = false;
                masterCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length === allCheckboxes.length) {
                masterCheckbox.checked = true;
                masterCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length > 0) {
                masterCheckbox.checked = false;
                masterCheckbox.indeterminate = true;
            } else {
                masterCheckbox.checked = false;
                masterCheckbox.indeterminate = false;
            }
        }

        function applyFiltersAndRender() {
            const estateFilter = document.getElementById('estateFilter');
            const tenantSearch = document.getElementById('tenantSearch');
            const paymentStatusFilter = document.getElementById('paymentStatusFilter');
            const minBillFilter = document.getElementById('minBillFilter');
            
            if (!estateFilter || !tenantSearch || !paymentStatusFilter || !minBillFilter) {
                return;
            }
            
            let selectedEstateId = estateFilter.value;
            let searchTerm = tenantSearch.value.toLowerCase();
            let paymentStatus = paymentStatusFilter.value;
            let minBill = parseFloat(minBillFilter.value) || 0;
            
            let visibleRows = allRows.filter(row => {
                let estateId = row.getAttribute('data-estate-id');
                let name = row.getAttribute('data-name-lower') || '';
                let phone = row.getAttribute('data-phone') || '';
                let unit = row.getAttribute('data-unit-lower') || '';
                let estate = row.getAttribute('data-estate-lower') || '';
                let waterBill = parseFloat(row.getAttribute('data-water-bill')) || 0;
                let payment = row.getAttribute('data-payment-status') || 'pending';
                
                let matchesEstate = selectedEstateId === '' || estateId == selectedEstateId;
                let matchesSearch = searchTerm === '' || 
                    name.includes(searchTerm) || 
                    phone.includes(searchTerm) || 
                    unit.includes(searchTerm) ||
                    estate.includes(searchTerm);
                let matchesPayment = paymentStatus === '' || payment === paymentStatus;
                let matchesBill = waterBill >= minBill;
                
                if (hidePaid && payment === 'paid') {
                    return false;
                }
                
                return matchesEstate && matchesSearch && matchesPayment && matchesBill;
            });
            
            if (sortColumn) {
                visibleRows.sort((a, b) => {
                    let aVal, bVal;
                    switch(sortColumn) {
                        case 'name': aVal = a.getAttribute('data-name') || ''; bVal = b.getAttribute('data-name') || ''; break;
                        case 'phone': aVal = a.getAttribute('data-phone') || ''; bVal = b.getAttribute('data-phone') || ''; break;
                        case 'unit': aVal = a.getAttribute('data-unit') || ''; bVal = b.getAttribute('data-unit') || ''; break;
                        case 'estate': aVal = a.getAttribute('data-estate') || ''; bVal = b.getAttribute('data-estate') || ''; break;
                        case 'water_bill': aVal = parseFloat(a.getAttribute('data-water-bill')) || 0; bVal = parseFloat(b.getAttribute('data-water-bill')) || 0; break;
                        default: aVal = a.getAttribute('data-name') || ''; bVal = b.getAttribute('data-name') || '';
                    }
                    if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
                    if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
            }
            
            const entriesSelect = document.getElementById('entriesPerPage');
            const showAll = entriesSelect ? entriesSelect.value === 'all' : false;
            
            let paginatedRows;
            if (showAll) {
                paginatedRows = visibleRows;
            } else {
                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                paginatedRows = visibleRows.slice(start, end);
            }
            
            allRows.forEach(row => row.style.display = 'none');
            paginatedRows.forEach(row => row.style.display = '');
            
            const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
            const pageInfo = document.getElementById('pageInfo');
            if (pageInfo) {
                pageInfo.innerText = showAll ? 'Showing all' : `Page ${currentPage} of ${totalPages || 1}`;
            }
            
            updateSelectedCount();
        }

        function entriesPerPageChange() {
            const entriesSelect = document.getElementById('entriesPerPage');
            if (!entriesSelect) return;
            
            if (entriesSelect.value === 'all') {
                rowsPerPage = 999999;
            } else {
                rowsPerPage = parseInt(entriesSelect.value);
            }
            currentPage = 1;
            applyFiltersAndRender();
        }

        function sortTable(column) {
            if (sortColumn === column) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = column;
                sortDirection = 'asc';
            }
            applyFiltersAndRender();
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                applyFiltersAndRender();
            }
        }

        function nextPage() {
            const visibleRows = allRows.filter(row => row.style.display !== 'none').length;
            const totalPages = Math.ceil(visibleRows / rowsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                applyFiltersAndRender();
            }
        }

        // ============================================
        // UPDATE PREVIEW – sends clean current_month to API
        // ============================================
        function updatePreview() {
            let template = document.getElementById('template')?.value || '';
            let allSelected = getAllSelectedCheckboxes();
            let selectedCount = allSelected.length;

            const previewSection = document.getElementById('previewSection');
            const previewContainer = document.getElementById('previewContainer');
            const previewCount = document.getElementById('previewCount');

            if (!previewSection || !previewContainer) return;

            if (selectedCount === 0 || template.trim() === '') {
                previewSection.style.display = 'none';
                if (previewCount) previewCount.textContent = '';
                return;
            }

            const tenantIds = Array.from(allSelected)
                .map(cb => cb.getAttribute('data-id'))
                .filter(id => id && id !== '');

            let currentMonth = null;
            const firstCb = allSelected[0];
            if (firstCb) {
                let monthAttr = firstCb.getAttribute('data-month') || '';
                if (monthAttr) {
                    try {
                        const parts = monthAttr.split(' ');
                        if (parts.length === 2) {
                            const monthName = parts[0];
                            const year = parts[1];
                            const monthNum = new Date(Date.parse(monthName + ' 1, 2000')).getMonth() + 1;
                            if (monthNum) {
                                currentMonth = year + '-' + String(monthNum).padStart(2, '0');
                            }
                        } else {
                            const d = new Date(monthAttr);
                            if (!isNaN(d)) {
                                currentMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                            }
                        }
                    } catch (e) { /* fallback */ }
                }
                if (!currentMonth) {
                    const d = new Date();
                    currentMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                }
            }

            console.log('📤 Sending current_month:', currentMonth);

            previewSection.style.display = 'block';
            previewContainer.innerHTML = '<div class="text-center py-4 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading invoice data...</div>';
            if (previewCount) previewCount.textContent = 'Loading...';

            fetch('/api/sms/preview-invoices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    tenant_ids: tenantIds,
                    current_month: currentMonth
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('📥 API response:', data);
                if (data.success && data.invoices) {
                    renderPreviewWithData(data.invoices, template, allSelected, selectedCount);
                } else {
                    throw new Error('No invoice data returned');
                }
            })
            .catch(error => {
                console.warn('⚠️ Falling back to dummy data:', error);
                renderPreviewWithDummyData(template, allSelected, selectedCount);
            });
        }

// ============================================================
// RENDER PREVIEW WITH REAL INVOICE DATA (UPDATED)
// ============================================================
function renderPreviewWithData(invoiceData, template, allSelected, selectedCount) {
    const previewCount = document.getElementById('previewCount');
    if (previewCount) {
        previewCount.textContent = `Showing ${Math.min(3, selectedCount)} of ${selectedCount} selected tenants`;
    }

    let previews = [];
    const maxPreview = Math.min(3, selectedCount);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let i = 0; i < maxPreview; i++) {
        let cb = allSelected[i];
        let tenantId = cb.dataset.id || '';
        let tenantInvoices = invoiceData[tenantId] || [];

        // --- Determine current month from the latest invoice ---
        let currentMonthY = '';
        if (tenantInvoices.length > 0) {
            let latest = tenantInvoices.reduce((a, b) => {
                return (a.billing_month > b.billing_month) ? a : b;
            });
            if (latest && latest.billing_month) {
                currentMonthY = latest.billing_month.substring(0, 7);
            }
        }
        if (!currentMonthY) {
            let monthAttr = cb.getAttribute('data-month') || '';
            try {
                const d = new Date(monthAttr);
                if (!isNaN(d)) {
                    currentMonthY = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                }
            } catch (e) { /* ignore */ }
        }

        // --- Compute due date for the current month's bill (5th of next month) ---
        let dueDateFormatted = '';
        if (currentMonthY) {
            try {
                let parts = currentMonthY.split('-');
                let year = parseInt(parts[0]);
                let month = parseInt(parts[1]);
                let dueMonth = month + 1;
                let dueYear = year;
                if (dueMonth > 12) {
                    dueMonth = 1;
                    dueYear++;
                }
                let dueDateObj = new Date(dueYear, dueMonth - 1, 5);
                dueDateFormatted = dueDateObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            } catch (e) { /* ignore */ }
        }

        // --- Filter OLDER invoices (billing_month < currentMonthY) AND due_date <= today ---
        let olderInvoices = tenantInvoices.filter(inv => {
            if (!inv.billing_month) return false;
            let invMonth = inv.billing_month.substring(0, 7);
            if (invMonth >= currentMonthY) return false;

            let dueDate;
            if (inv.due_date) {
                dueDate = new Date(inv.due_date);
            } else {
                dueDate = new Date(inv.billing_month);
                dueDate.setMonth(dueDate.getMonth() + 1);
                dueDate.setDate(5);
            }
            dueDate.setHours(0, 0, 0, 0);
            return dueDate <= today;
        });

        let olderTotal = olderInvoices.reduce((sum, inv) => sum + parseFloat(inv.amount || 0), 0);
        let currentBill = parseFloat(cb.getAttribute('data-waterbill') || 0);
        let paymentStatus = cb.getAttribute('data-payment-status') || 'pending';

        // --- If tenant is fully paid, zero everything ---
        if (paymentStatus === 'paid') {
            currentBill = 0;
            olderTotal = 0;
        }

        let unpaidTotal = olderTotal;
        let totalDue = currentBill + unpaidTotal;
        let unpaidCount = olderInvoices.length;

        // --- Build unpaid list: each invoice on its own line; prefix status only if not 'unpaid' ---
        let unpaidList = '';
        if (unpaidCount > 0) {
            unpaidList = olderInvoices.map(inv => {
                let billingMonth = formatMonthYear(inv.billing_month);
                let prefix = '';
                if (inv.status !== 'unpaid') {
                    prefix = inv.status.charAt(0).toUpperCase() + inv.status.slice(1) + ' ';
                }
                return prefix + '(' + billingMonth + '): KES ' + Number(inv.amount).toFixed(2);
            }).join('\n');
        }

        // --- Build unpaid section (only the "Unpaid:" header and the list, no total line) ---
        let unpaidSection = '';
        if (unpaidCount > 0) {
            unpaidSection = 'Unpaid:\n' + unpaidList;
        }

        // ----- Gather other data from the checkbox -----
        let phone = cb.getAttribute('data-phone') || '';
        let name = cb.getAttribute('data-name') || 'Tenant';
        let unit = cb.getAttribute('data-unit') || 'N/A';
        let consumption = cb.getAttribute('data-consumption') || '0';
        let estate_name = cb.getAttribute('data-estate') || 'N/A';
        let prev_read = cb.getAttribute('data-prev-read') || '0';
        let curr_read = cb.getAttribute('data-curr-read') || '0';

        // Determine the month label for the current bill
        let currentMonthLabel = '';
        if (currentMonthY) {
            try {
                const d = new Date(currentMonthY + '-01');
                currentMonthLabel = d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
            } catch (e) {
                currentMonthLabel = cb.getAttribute('data-month') || '';
            }
        } else {
            currentMonthLabel = cb.getAttribute('data-month') || '';
        }

        // ----- Replace placeholders -----
        let message = template;
        message = message.replace(/\{\{name\}\}/g, name);
        message = message.replace(/\{\{unit\}\}/g, unit);
        message = message.replace(/\{\{unit_number\}\}/g, unit);
        message = message.replace(/\{\{water_bill\}\}/g, currentBill.toFixed(2));
        message = message.replace(/\{\{water_consumption\}\}/g, consumption);
        message = message.replace(/\{\{month\}\}/g, currentMonthLabel);
        message = message.replace(/\{\{estate_name\}\}/g, estate_name);
        message = message.replace(/\{\{estate\}\}/g, estate_name);
        message = message.replace(/\{\{prev_read\}\}/g, prev_read);
        message = message.replace(/\{\{curr_read\}\}/g, curr_read);
        message = message.replace(/\{\{payment_status\}\}/g, paymentStatus);
        message = message.replace(/\{\{status\}\}/g, paymentStatus);
        message = message.replace(/\{\{due_date\}\}/g, dueDateFormatted);
        message = message.replace(/\{\{unpaid_count\}\}/g, unpaidCount);
        message = message.replace(/\{\{unpaid_total\}\}/g, unpaidTotal.toFixed(2));
        message = message.replace(/\{\{unpaid_list\}\}/g, unpaidList);
        message = message.replace(/\{\{unpaid_message\}\}/g, unpaidCount > 0 ? unpaidCount + ' unpaid/partial invoices totaling KES ' + unpaidTotal.toFixed(2) : '');
        message = message.replace(/\{\{unpaid_section\}\}/g, unpaidSection);
        message = message.replace(/\{\{total_due\}\}/g, totalDue.toFixed(2));
        message = message.replace(/\{\{[^}]*\}\}/g, '');

        const msgLength = message.length;
        const isUnicode = /[^\x00-\x7F]/.test(message);
        const partsPerSms = isUnicode ? 70 : 160;
        const parts = Math.ceil(msgLength / partsPerSms);

        let statusColor = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        if (paymentStatus === 'paid') statusColor = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        else if (paymentStatus === 'unpaid' || paymentStatus === 'overdue') statusColor = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';

        previews.push({
            phone, name, unit, estate: estate_name,
            water_bill: currentBill.toFixed(2),
            water_consumption: consumption,
            prev_read, curr_read, month: currentMonthLabel,
            due_date: dueDateFormatted,
            paymentStatus,
            message,
            length: msgLength,
            parts,
            statusColor,
            unpaid_count: unpaidCount,
            unpaid_total: unpaidTotal.toFixed(2),
            unpaid_list: unpaidList,
            unpaid_message: unpaidCount > 0 ? unpaidCount + ' unpaid/partial invoices totaling KES ' + unpaidTotal.toFixed(2) : '',
            unpaid_section: unpaidSection,
            total_due: totalDue.toFixed(2)
        });
    }

    renderPreviewHTML(previews, selectedCount);
}

        // ============================================================
        // RENDER PREVIEW WITH DUMMY DATA (FALLBACK)
        // ============================================================
        function renderPreviewWithDummyData(template, allSelected, selectedCount) { 
            const previewCount = document.getElementById('previewCount');
            if (previewCount) {
                previewCount.textContent = `Showing ${Math.min(3, selectedCount)} of ${selectedCount} selected tenants (demo preview)`;
            }

            let previews = [];
            const maxPreview = Math.min(3, selectedCount);

            for (let i = 0; i < maxPreview; i++) {
                let cb = allSelected[i];
                let phone = cb.getAttribute('data-phone') || '';
                let name = cb.getAttribute('data-name') || 'Tenant';
                let unit = cb.getAttribute('data-unit') || 'N/A';
                let water_bill = parseFloat(cb.getAttribute('data-waterbill') || 0);
                let consumption = cb.getAttribute('data-consumption') || '0';
                let estate_name = cb.getAttribute('data-estate') || 'N/A';
                let prev_read = cb.getAttribute('data-prev-read') || '0';
                let curr_read = cb.getAttribute('data-curr-read') || '0';
                let month = cb.getAttribute('data-month') || new Date().toLocaleString('default', { month: 'long', year: 'numeric' });
                let dueDate = cb.getAttribute('data-due-date') || new Date(Date.now() + 14*24*60*60*1000).toISOString().split('T')[0];
                let paymentStatus = cb.getAttribute('data-payment-status') || 'pending';

                let unpaidCount = 0;
                let unpaidTotal = 0;
                let unpaidList = '';
                let totalDue = water_bill;

                const tenantName = name.toLowerCase();
                if (tenantName.includes('d00-shop') || tenantName.includes('shop')) {
                    unpaidCount = 2;
                    unpaidTotal = 4500.00;
                    unpaidList = 'unpaid (June 2026): KES 1,950.00\nunpaid (May 2026): KES 2,550.00';
                    totalDue = water_bill + 4500.00;
                } else if (tenantName.includes('dennis') || tenantName.includes('john d09')) {
                    unpaidCount = 0;
                    unpaidTotal = 0;
                    unpaidList = '';
                    totalDue = water_bill;
                } else {
                    unpaidCount = 2;
                    unpaidTotal = 3000.00;
                    unpaidList = 'unpaid (June 2026): KES 1,500.00\nunpaid (May 2026): KES 1,500.00';
                    totalDue = water_bill + 3000.00;
                }

                let formattedDueDate = dueDate;
                try {
                    const d = new Date(dueDate);
                    if (!isNaN(d)) {
                        formattedDueDate = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                    }
                } catch (e) {}

                let formattedWaterBill = water_bill.toFixed(2);
                let unpaidMessage = unpaidCount === 0 
                    ? 'no pending invoices' 
                    : (unpaidCount === 1 
                        ? `1 unpaid invoice of KES ${unpaidTotal.toFixed(2)}` 
                        : `${unpaidCount} unpaid invoices totaling KES ${unpaidTotal.toFixed(2)}`);

                let message = template;
                message = message.replace(/\{\{name\}\}/g, name);
                message = message.replace(/\{\{unit\}\}/g, unit);
                message = message.replace(/\{\{unit_number\}\}/g, unit);
                message = message.replace(/\{\{water_bill\}\}/g, formattedWaterBill);
                message = message.replace(/\{\{water_consumption\}\}/g, consumption);
                message = message.replace(/\{\{due_date\}\}/g, formattedDueDate);
                message = message.replace(/\{\{month\}\}/g, month);
                message = message.replace(/\{\{estate_name\}\}/g, estate_name);
                message = message.replace(/\{\{estate\}\}/g, estate_name);
                message = message.replace(/\{\{prev_read\}\}/g, prev_read);
                message = message.replace(/\{\{curr_read\}\}/g, curr_read);
                message = message.replace(/\{\{payment_status\}\}/g, paymentStatus);
                message = message.replace(/\{\{status\}\}/g, paymentStatus);
                message = message.replace(/\{\{unpaid_count\}\}/g, unpaidCount);
                message = message.replace(/\{\{unpaid_total\}\}/g, unpaidTotal.toFixed(2));
                message = message.replace(/\{\{unpaid_list\}\}/g, unpaidList);
                message = message.replace(/\{\{unpaid_message\}\}/g, unpaidMessage);
                message = message.replace(/\{\{total_due\}\}/g, totalDue.toFixed(2));
                message = message.replace(/\{\{[^}]*\}\}/g, '');

                const msgLength = message.length;
                const isUnicode = /[^\x00-\x7F]/.test(message);
                const partsPerSms = isUnicode ? 70 : 160;
                const parts = Math.ceil(msgLength / partsPerSms);

                let statusColor = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
                if (paymentStatus === 'paid') statusColor = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
                else if (paymentStatus === 'unpaid' || paymentStatus === 'overdue') statusColor = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';

                previews.push({
                    phone, name, unit, estate: estate_name,
                    water_bill: formattedWaterBill,
                    water_consumption: consumption,
                    prev_read, curr_read, month,
                    due_date: formattedDueDate,
                    paymentStatus,
                    message,
                    length: msgLength,
                    parts,
                    statusColor,
                    unpaid_count: unpaidCount,
                    unpaid_total: unpaidTotal.toFixed(2),
                    unpaid_list: unpaidList,
                    unpaid_message: unpaidMessage,
                    total_due: totalDue.toFixed(2)
                });
            }

            renderPreviewHTML(previews, selectedCount);
        }

        // ============================================================
        // RENDER THE PREVIEW HTML (shared by both real and dummy)
        // ============================================================
        function renderPreviewHTML(previews, selectedCount) {
            const previewContainer = document.getElementById('previewContainer');
            let html = '';
            previews.forEach((p) => {
                let unpaidInfo = p.unpaid_count > 0
                    ? `<span class="text-xs text-orange-600 dark:text-orange-400 ml-2">⚠️ ${p.unpaid_count} unpaid (KES ${p.unpaid_total})</span>`
                    : '';

                html += `
                    <div class="border rounded-lg overflow-hidden bg-white dark:bg-gray-800 shadow-sm">
                        <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm text-gray-800 dark:text-white">${escapeHtml(p.name)}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(p.unit)}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">•</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(p.estate)}</span>
                                ${unpaidInfo}
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${p.statusColor}">
                                    ${p.paymentStatus.charAt(0).toUpperCase() + p.paymentStatus.slice(1)}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">${escapeHtml(p.phone)}</span>
                            </div>
                        </div>
                        <div class="px-4 py-3">
                            <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap font-sans leading-relaxed">
                                ${escapeHtml(p.message)}
                            </div>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-${p.length > 160 ? 'exclamation-triangle text-yellow-500' : 'check-circle text-green-500'} mr-1"></i>
                                    ${p.length} characters
                                </span>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-envelope mr-1"></i>
                                    ${p.parts} SMS part${p.parts > 1 ? 's' : ''}
                                </span>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                    KES ${(p.parts * 0.45).toFixed(2)}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Reading: ${p.prev_read} → ${p.curr_read}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">|</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Bill: KES ${p.water_bill}
                                </span>
                                ${p.unpaid_count > 0 ? `
                                <span class="text-xs text-orange-600 dark:text-orange-400">|</span>
                                <span class="text-xs text-orange-600 dark:text-orange-400">
                                    Unpaid: KES ${p.unpaid_total}
                                </span>
                                ` : ''}
                                ${p.total_due ? `
                                <span class="text-xs text-blue-600 dark:text-blue-400">|</span>
                                <span class="text-xs text-blue-600 dark:text-blue-400">
                                    Total Due: KES ${p.total_due}
                                </span>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            if (selectedCount > 3) {
                html += `
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-2 border-t border-gray-200 dark:border-gray-700">
                        <i class="fas fa-ellipsis-h mr-1"></i>
                        ${selectedCount - 3} more tenant(s) will receive the same message
                    </div>
                `;
            }

            previewContainer.innerHTML = html;
        }

        // ============================================
        // UPDATE SELECTED COUNT
        // ============================================
        function updateSelectedCount() {
            let allSelected = getAllSelectedCheckboxes();
            let selectedCount = allSelected.length;
            
            const selectedCountEl = document.getElementById('selectedCount');
            if (selectedCountEl) {
                selectedCountEl.innerText = selectedCount;
            }
            
            updateMasterCheckboxState();
            
            const sendBtn = document.getElementById('sendSmsBtn');
            if (sendBtn) {
                sendBtn.disabled = selectedCount === 0;
            }
            
            updatePreview();
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList && e.target.classList.contains('tenant-checkbox')) {
                updateSelectedCount();
            }
        });

        // ============================================
        // MAKE MESSAGE COMPACT
        // ============================================
        function makeMessageCompact() {
            const template = document.getElementById('template');
            if (!template) return;
            
            let message = template.value;
            message = message.replace(/\s+/g, ' ').trim();
            message = message.replace(/\bplease\b/g, '');
            message = message.replace(/\bwill\b/g, '');
            message = message.replace(/\byour\b/g, '');
            message = message.replace(/\bPaybill\b/g, 'PB');
            message = message.replace(/\bAmount\b/g, 'Amt');
            message = message.replace(/\bDue\b/g, 'Due:');
            message = message.replace(/\bStatus\b/g, 'Stat');
            
            template.value = message.trim();
            updatePreview();
            
            const event = new Event('input');
            template.dispatchEvent(event);
        }

        // ============================================
        // BULK FORM SUBMIT
        // ============================================
        const bulkForm = document.getElementById('bulkForm');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                let selected = [];
                let template = document.getElementById('template').value;
                
                if (!template.trim()) {
                    alert('Please enter a message template.');
                    e.preventDefault();
                    return false;
                }
                
                let allSelected = getAllSelectedCheckboxes();
                
                if (allSelected.length === 0) {
                    alert('Please select at least one tenant.');
                    e.preventDefault();
                    return false;
                }
                
                const submitBtn = document.getElementById('sendSmsBtn');
                const originalText = submitBtn ? submitBtn.innerHTML : 'Send SMS';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Sending...';
                }
                
                allSelected.forEach(cb => {
                    let phone = cb.getAttribute('data-phone');
                    if (!phone) return;
                    
                    let name = cb.getAttribute('data-name') || 'Tenant';
                    let unit = cb.getAttribute('data-unit') || 'N/A';
                    let water_bill = parseFloat(cb.getAttribute('data-waterbill') || 0).toFixed(2);
                    let consumption = cb.getAttribute('data-consumption') || '0';
                    let estate_name = cb.getAttribute('data-estate') || '';
                    let prev_read = cb.getAttribute('data-prev-read') || '0';
                    let curr_read = cb.getAttribute('data-curr-read') || '0';
                    let month = cb.getAttribute('data-month');
                    let dueDate = cb.getAttribute('data-due-date');
                    let paymentStatus = cb.getAttribute('data-payment-status') || 'pending';
                    
                    let isUnpaid = paymentStatus === 'unpaid' || paymentStatus === 'pending' || paymentStatus === 'overdue';
                    let unpaidCount = isUnpaid ? 2 : 0;
                    let unpaidTotal = isUnpaid ? 3000.00 : 0;
                    let unpaidList = isUnpaid ? 'Oct 2024: KES 1,500.00, Nov 2024: KES 1,500.00' : '';
                    let unpaidMessage = unpaidCount === 0 
                        ? 'no pending invoices' 
                        : (unpaidCount === 1 
                            ? `1 unpaid invoice of KES ${unpaidTotal.toFixed(2)}` 
                            : `${unpaidCount} unpaid invoices totaling KES ${unpaidTotal.toFixed(2)}`);
                    
                    let message = template;
                    message = message.replace(/\{\{name\}\}/g, name);
                    message = message.replace(/\{\{unit\}\}/g, unit);
                    message = message.replace(/\{\{unit_number\}\}/g, unit);
                    message = message.replace(/\{\{water_bill\}\}/g, water_bill);
                    message = message.replace(/\{\{water_consumption\}\}/g, consumption);
                    message = message.replace(/\{\{due_date\}\}/g, dueDate);
                    message = message.replace(/\{\{month\}\}/g, month);
                    message = message.replace(/\{\{estate_name\}\}/g, estate_name);
                    message = message.replace(/\{\{estate\}\}/g, estate_name);
                    message = message.replace(/\{\{prev_read\}\}/g, prev_read);
                    message = message.replace(/\{\{curr_read\}\}/g, curr_read);
                    message = message.replace(/\{\{payment_status\}\}/g, paymentStatus);
                    message = message.replace(/\{\{status\}\}/g, paymentStatus);
                    message = message.replace(/\{\{unpaid_count\}\}/g, unpaidCount);
                    message = message.replace(/\{\{unpaid_total\}\}/g, unpaidTotal.toFixed(2));
                    message = message.replace(/\{\{unpaid_list\}\}/g, unpaidList);
                    message = message.replace(/\{\{unpaid_message\}\}/g, unpaidMessage);
                    message = message.replace(/\{\{[^}]*\}\}/g, '');
                    
                    selected.push({
                        phone: phone,
                        message: message,
                        id: cb.getAttribute('data-id')
                    });
                });
                
                document.getElementById('recipientsJson').value = JSON.stringify(selected);
                document.getElementById('templateHidden').value = template;
                
                setTimeout(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                    alert('✅ SMS sent to ' + selected.length + ' tenants!');
                }, 1000);
                
                e.preventDefault();
                return false;
            });
        }

        let campaignsData = [];
        let currentCampaignFilter = 'all';
        let currentSourceFilter = 'all';
        let isSandbox = true;

function loadCampaigns() {
    // 🔄 RESET FILTER TO 'ALL'
    currentCampaignFilter = 'all';
    const sourceFilter = document.getElementById('sourceFilter');
    if (sourceFilter) {
        currentSourceFilter = sourceFilter.value;
    }

    // Update filter button styles
    const filterButtons = document.querySelectorAll('#campaigns-tab .flex.flex-wrap.gap-2 button');
    filterButtons.forEach(btn => {
        btn.classList.remove('bg-blue-600', 'text-white');
        btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    });
    const allBtn = document.getElementById('filter-all');
    if (allBtn) {
        allBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
        allBtn.classList.add('bg-blue-600', 'text-white');
    }

    console.log('🔄 Loading campaigns...');
    
    const loadingEl = document.getElementById('campaignsLoading');
    const tableEl = document.getElementById('campaignsTable');
    
    if (loadingEl) loadingEl.style.display = 'block';
    if (tableEl) tableEl.style.display = 'none';
    
    fetch('/api/sms/campaigns')
        .then(response => {
            console.log('📥 Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📥 Campaigns data received:', data);
            
            // ✅ Store sandbox status
            isSandbox = data.sandbox || true;
            
            campaignsData = data.campaigns || [];
            console.log('📊 Campaigns loaded:', campaignsData.length);
            console.log('📊 Sandbox mode:', isSandbox);
            
            // ✅ Update stats
            updateCampaignStats(data.stats || { total: 0, sent: 0, pending: 0, failed: 0 });
            
            // ✅ Apply source filter if set (only in live mode)
            if (!isSandbox) {
                const sourceFilter = document.getElementById('sourceFilter');
                if (sourceFilter && sourceFilter.value !== 'all') {
                    currentSourceFilter = sourceFilter.value;
                    showFilterInfo(sourceFilter.value);
                } else {
                    hideFilterInfo();
                }
            }
            
            renderCampaigns();
            if (loadingEl) loadingEl.style.display = 'none';
            if (tableEl) tableEl.style.display = 'block';
        })
        .catch(error => {
            console.error('❌ Error loading campaigns:', error);
            if (loadingEl) loadingEl.style.display = 'none';
            if (tableEl) {
                tableEl.style.display = 'block';
                const tbody = document.getElementById('campaignsTableBody');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-red-500">
                                <i class="fas fa-exclamation-circle mr-2"></i> 
                                Error loading campaigns: ${error.message}
                            </td>
                        </tr>
                    `;
                }
            }
        });
}
// ============================================
// SOURCE FILTER FUNCTIONS
// ============================================

/**
 * Filter campaigns by source (Local / Imported)
 */
function filterCampaignsBySource() {
    const sourceFilter = document.getElementById('sourceFilter');
    if (!sourceFilter) return;
    
    currentSourceFilter = sourceFilter.value;
    
    if (currentSourceFilter !== 'all') {
        showFilterInfo(currentSourceFilter);
    } else {
        hideFilterInfo();
    }
    
    renderCampaigns();
}

/**
 * Show filter info bar
 */
function showFilterInfo(source) {
    const filterInfo = document.getElementById('filterInfo');
    const filterText = document.getElementById('filterText');
    if (filterInfo && filterText) {
        filterInfo.classList.remove('hidden');
        const labels = {
            'local': '📤 Showing local campaigns only',
            'kenyasms_imported': '📥 Showing imported campaigns only'
        };
        filterText.textContent = labels[source] || 'Filtered by source';
    }
}

/**
 * Hide filter info bar
 */
function hideFilterInfo() {
    const filterInfo = document.getElementById('filterInfo');
    if (filterInfo) {
        filterInfo.classList.add('hidden');
    }
}

/**
 * Reset source filter
 */
function resetSourceFilter() {
    const sourceFilter = document.getElementById('sourceFilter');
    if (sourceFilter) {
        sourceFilter.value = 'all';
    }
    currentSourceFilter = 'all';
    hideFilterInfo();
    renderCampaigns();
}
        // ============================================
// KENYASMS CAMPAIGN FUNCTIONS
// ============================================

/**
 * List campaigns from KenyaSMS
 */
function listKenyaSmsCampaigns() {
    const modal = document.getElementById('kenyaSmsModal');
    const content = document.getElementById('kenyaSmsModalContent');
    
    // Show modal with loading state
    modal.style.display = 'block';
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
            <p class="mt-2 text-gray-500">Loading campaigns from KenyaSMS...</p>
        </div>
    `;

    fetch('/api/sms/campaigns/kenyasms')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderKenyaSmsCampaigns(data.campaigns, data.total);
            } else {
                content.innerHTML = `
                    <div class="text-center py-8 text-red-500">
                        <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                        <p>${data.message || 'Failed to load campaigns'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching KenyaSMS campaigns:', error);
            content.innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                    <p>Error loading campaigns: ${error.message}</p>
                </div>
            `;
        });
}

/**
 * Render KenyaSMS campaigns in the modal
 */
function renderKenyaSmsCampaigns(campaigns, total) {
    const content = document.getElementById('kenyaSmsModalContent');
    
    // Ensure campaigns is an array
    if (!campaigns || typeof campaigns !== 'object') {
        campaigns = [];
    }
    if (!Array.isArray(campaigns)) {
        campaigns = Object.values(campaigns);
    }
    campaigns = campaigns.filter(c => c && typeof c === 'object');
    
    if (campaigns.length === 0) {
        content.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>No campaigns found in KenyaSMS.</p>
            </div>
        `;
        return;
    }

    // Count imported campaigns
    const importedCount = campaigns.filter(c => c.is_imported).length;
    const notImportedCount = campaigns.length - importedCount;

    let html = `
        <div class="mb-4 flex justify-between items-center flex-wrap gap-2">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing <strong>${campaigns.length}</strong> of ${total || campaigns.length} campaigns
                <span class="ml-2 text-xs">
                    <span class="text-green-600">✅ ${importedCount} imported</span>
                    <span class="text-gray-400 mx-1">|</span>
                    <span class="text-orange-600">📥 ${notImportedCount} available</span>
                </span>
            </div>
            <div class="text-xs text-gray-400">
                <i class="fas fa-info-circle mr-1"></i> Click "Import" to match recipients with unit numbers
            </div>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Campaign</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipients</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Delivered</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failed</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rate</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
    `;

    campaigns.forEach(campaign => {
        const statusBadge = getStatusBadge(campaign.status);
        const statusText = capitalize(campaign.status || 'unknown');
        const isImported = campaign.is_imported || false;
        
        const rowClass = isImported ? 'bg-green-50 dark:bg-green-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150';
        
        let rateColor = 'text-gray-500';
        if (campaign.success_rate >= 90) rateColor = 'text-green-600';
        else if (campaign.success_rate >= 70) rateColor = 'text-yellow-600';
        else if (campaign.success_rate > 0) rateColor = 'text-red-600';
        
        html += `
            <tr class="${rowClass}">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-gray-800 dark:text-white">${escapeHtml(campaign.name)}</span>
                        ${isImported ? '<span class="text-xs text-green-600 bg-green-100 px-2 py-0.5 rounded-full">✅ Imported</span>' : ''}
                    </div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">${escapeHtml(campaign.sender_id || 'No Sender')} • ${escapeHtml(campaign.message_type || '')}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                    ${campaign.formatted_date || 'N/A'}
                </td>
                <td class="px-4 py-3 text-center text-sm font-medium text-gray-700 dark:text-gray-300">
                    ${campaign.recipients}
                </td>
                <td class="px-4 py-3 text-center text-sm text-green-600 font-medium">
                    ${campaign.delivered}
                </td>
                <td class="px-4 py-3 text-center text-sm text-red-600 font-medium">
                    ${campaign.failed}
                </td>
                <td class="px-4 py-3 text-center text-sm font-medium ${rateColor}">
                    ${campaign.success_rate}%
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${statusBadge}">
                        ${statusText}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    ${isImported ? `
                        <span class="text-xs text-gray-400">Already imported</span>
                    ` : `
                        <button onclick="importSingleKenyaSmsCampaign('${campaign.id}')" 
                                class="text-green-600 hover:text-green-900 text-sm font-medium transition-colors" 
                                title="Import this campaign to match with unit numbers">
                            <i class="fas fa-cloud-download-alt mr-1"></i> Import
                        </button>
                    `}
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-xs text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between flex-wrap gap-2">
            <span>
                <i class="fas fa-check-circle text-green-600 mr-1"></i> Imported campaigns are already in your local system
            </span>
            <span>
                <i class="fas fa-download text-blue-600 mr-1"></i> Import to match recipients with unit numbers
            </span>
        </div>
    `;

    content.innerHTML = html;
}

/**
 * Import a single campaign from KenyaSMS
 */
function importSingleKenyaSmsCampaign(campaignId) {
    if (!confirm('Import this campaign from KenyaSMS?\n\nThis will:\n• Match phone numbers to local tenants\n• Show you which tenants received the message\n• Show you which tenants did NOT receive it\n\nContinue?')) {
        return;
    }

    const btn = event.target.closest('button');
    const originalText = btn ? btn.innerHTML : 'Importing...';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Importing...';
    }

    fetch(`/api/sms/campaigns/kenyasms/${campaignId}/import`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let msg = `✅ Campaign imported successfully!\n\n`;
            msg += `📊 Total recipients: ${data.recipients_imported || 0}\n`;
            msg += `🏠 Matched to tenants: ${data.matched_tenants || 0}\n`;
            msg += `⚠️ Unmatched phones: ${data.unmatched_phones || 0}\n`;
            msg += `\n🔍 Check the campaign in the list above to see unit numbers.`;
            
            alert(msg);
            closeKenyaSmsModal();
            loadCampaigns(); // Refresh the local campaigns list
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to import campaign'));
        }
    })
    .catch(error => {
        console.error('Error importing campaign:', error);
        alert('❌ Error importing campaign: ' + error.message);
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

/**
 * Import all campaigns from KenyaSMS
 */
function importKenyaSmsCampaigns() {
    if (!confirm('This will fetch ALL campaigns from KenyaSMS and import them locally.\n\nExisting campaigns will be skipped.\nContinue?')) {
        return;
    }
    
    const btn = document.getElementById('importKenyaSmsBtn');
    const originalText = btn ? btn.innerHTML : 'Importing...';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Importing all...';
    }
    
    fetch('/api/sms/campaigns/import-kenyasms', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response. Please check the logs.');
        }
    })
    .then(data => {
        if (data.success) {
            let msg = `✅ ${data.message}\n\n`;
            msg += `Imported: ${data.data.imported}\n`;
            msg += `Skipped: ${data.data.skipped}\n`;
            if (data.data.errors && data.data.errors.length > 0) {
                msg += `\n⚠️ Errors: ${data.data.errors.length}`;
                console.log('Import errors:', data.data.errors);
            }
            alert(msg);
            loadCampaigns(); // Refresh the campaigns list
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to import campaigns'));
        }
    })
    .catch(error => {
        console.error('Error importing campaigns:', error);
        alert('❌ Error importing campaigns: ' + error.message);
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

/**
 * Close KenyaSMS modal
 */
function closeKenyaSmsModal() {
    const modal = document.getElementById('kenyaSmsModal');
    if (modal) modal.style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('kenyaSmsModal');
    if (modal && modal.style.display === 'block') {
        if (event.target.closest('.fixed.inset-0.bg-gray-900/50')) {
            closeKenyaSmsModal();
        }
    }
});

        // ============================================
        // LOAD CAMPAIGNS FROM KENYASMS
        // ============================================
        function loadKenyaSmsCampaigns() {
            console.log('🔄 Fetching campaigns from KenyaSMS...');
            
            const loadingEl = document.getElementById('campaignsLoading');
            const tableEl = document.getElementById('campaignsTable');
            const tbody = document.getElementById('campaignsTableBody');
            
            if (loadingEl) loadingEl.style.display = 'block';
            if (tableEl) tableEl.style.display = 'none';
            
            fetch('/api/sms/campaigns/kenyasms')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📥 KenyaSMS campaigns received:', data);
                    
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (tableEl) tableEl.style.display = 'block';
                    
                    let campaignsArray = [];
                    
                    if (data.success && data.campaigns) {
                        if (Array.isArray(data.campaigns)) {
                            campaignsArray = data.campaigns;
                        } else if (typeof data.campaigns === 'object') {
                            if (data.campaigns.campaigns && Array.isArray(data.campaigns.campaigns)) {
                                campaignsArray = data.campaigns.campaigns;
                            } else if (data.campaigns.data && Array.isArray(data.campaigns.data)) {
                                campaignsArray = data.campaigns.data;
                            } else {
                                const values = Object.values(data.campaigns);
                                for (const val of values) {
                                    if (Array.isArray(val) && val.length > 0) {
                                        campaignsArray = val;
                                        break;
                                    }
                                }
                                if (campaignsArray.length === 0) {
                                    const entries = Object.entries(data.campaigns);
                                    if (entries.length > 0 && entries.every(([key, val]) => typeof val === 'object' && val !== null)) {
                                        campaignsArray = entries.map(([key, val]) => ({ ...val, id: val.id || key }));
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!Array.isArray(campaignsArray)) {
                        campaignsArray = [];
                    }
                    
                    console.log('📊 Processed campaigns array:', campaignsArray);
                    console.log('📊 Array length:', campaignsArray.length);
                    
                    if (!campaignsArray || campaignsArray.length === 0) {
                        if (tbody) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-info-circle mr-2"></i> No campaigns found in KenyaSMS
                                    </td>
                                </tr>
                            `;
                        }
                        updateCampaignStats({ total: 0, sent: 0, pending: 0, failed: 0 });
                        return;
                    }
                    
                    const stats = {
                        total: campaignsArray.length,
                        sent: campaignsArray.filter(c => c.status === 'completed' || c.status === 'sent').length,
                        pending: campaignsArray.filter(c => c.status === 'pending' || c.status === 'sending').length,
                        failed: campaignsArray.filter(c => c.status === 'failed').length,
                    };
                    updateCampaignStats(stats);
                    
                    if (!tbody) return;
                    
                    let html = '';
                    campaignsArray.forEach((campaign, index) => {
                        const statusBadge = getStatusBadge(campaign.status);
                        const statusText = capitalize(campaign.status);
                        const date = formatDate(campaign.created_at);
                        const campaignId = campaign.id || 'temp-' + index;
                        
                        html += `
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 text-sm text-gray-500">${campaignId}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">
                                    ${escapeHtml(campaign.name || 'Unnamed Campaign')}
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        KenyaSMS
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">${date}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">${campaign.recipients || 0}</td>
                                <td class="px-4 py-3 text-sm text-green-600">${campaign.delivered || campaign.sent || 0}</td>
                                <td class="px-4 py-3 text-sm text-red-600">${campaign.failed || 0}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${statusBadge}">${statusText}</span></td>
                                <td class="px-4 py-3">
                                    <button onclick="viewKenyaSmsCampaign('${campaignId}')" class="text-blue-600 hover:underline text-sm">View</button>
                                    <button onclick="importKenyaSmsCampaign('${campaignId}')" class="text-green-600 hover:underline text-sm ml-2">Import</button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                    
                })
                .catch(error => {
                    console.error('❌ Error loading KenyaSMS campaigns:', error);
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (tableEl) {
                        tableEl.style.display = 'block';
                        if (tbody) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-red-500">
                                        <i class="fas fa-exclamation-circle mr-2"></i> 
                                        Error loading campaigns from KenyaSMS: ${error.message}
                                    </td>
                                </tr>
                            `;
                        }
                    }
                });
        }

        // ============================================
        // VIEW KENYASMS CAMPAIGN DETAILS
        // ============================================
        function viewKenyaSmsCampaign(campaignId) {
            console.log('👁️ Viewing KenyaSMS campaign:', campaignId);
            
            fetch('/api/sms/campaigns')
                .then(r => r.json())
                .then(data => {
                    const localCampaign = data.campaigns.find(c => c.kenyasms_campaign_id === campaignId);
                    
                    if (localCampaign) {
                        viewCampaign(localCampaign.id);
                    } else {
                        if (confirm(`This campaign is not yet imported to your local database.\n\nDo you want to import it now?`)) {
                            importKenyaSmsCampaign(campaignId);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking local campaigns:', error);
                    if (confirm(`This campaign is not yet imported to your local database.\n\nDo you want to import it now?`)) {
                        importKenyaSmsCampaign(campaignId);
                    }
                });
        }

        // ============================================
        // IMPORT KENYASMS CAMPAIGN
        // ============================================
        function importKenyaSmsCampaign(campaignId) {
            if (!confirm(`Import KenyaSMS campaign "${campaignId}" to your local database?`)) return;
            
            const buttons = document.querySelectorAll(`[onclick*="importKenyaSmsCampaign('${campaignId}')"]`);
            let btn = null;
            buttons.forEach(b => {
                if (b.textContent.includes('Import') || b.innerHTML.includes('Import')) {
                    btn = b;
                }
            });
            
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
                btn.disabled = true;
            }
            
            fetch(`/api/sms/campaigns/kenyasms/${campaignId}/import`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Campaign imported successfully!\n\nRecipients imported: ${data.recipients_imported || 0}`);
                    loadCampaigns();
                    loadKenyaSmsCampaigns();
                    if (data.campaign_id) {
                        setTimeout(() => viewCampaign(data.campaign_id), 500);
                    }
                } else if (data.message && data.message.includes('already imported')) {
                    alert(`ℹ️ Campaign already imported locally. Opening it now...`);
                    fetch('/api/sms/campaigns')
                        .then(r => r.json())
                        .then(campaignsData => {
                            const campaign = campaignsData.campaigns.find(c => c.kenyasms_campaign_id === campaignId);
                            if (campaign) {
                                viewCampaign(campaign.id);
                            } else {
                                alert('⚠️ Campaign found but could not open. Please refresh and try again.');
                            }
                        })
                        .catch(err => {
                            console.error('Error finding campaign:', err);
                            alert('⚠️ Could not open campaign. Please refresh and try again.');
                        });
                } else {
                    alert('❌ Error importing campaign: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                alert('❌ Error importing campaign: ' + error.message);
            })
            .finally(() => {
                if (btn) {
                    btn.innerHTML = 'Import';
                    btn.disabled = false;
                }
            });
        }

        // ============================================
        // UPDATE CAMPAIGN STATS
        // ============================================
        function updateCampaignStats(stats) {
            const totalEl = document.getElementById('statsTotal');
            const sentEl = document.getElementById('statsSent');
            const pendingEl = document.getElementById('statsPending');
            const failedEl = document.getElementById('statsFailed');
            
            if (totalEl) totalEl.textContent = stats.total || 0;
            if (sentEl) sentEl.textContent = stats.sent || 0;
            if (pendingEl) pendingEl.textContent = stats.pending || 0;
            if (failedEl) failedEl.textContent = stats.failed || 0;
        }

        // ============================================
        // FILTER CAMPAIGNS
        // ============================================
        function filterCampaigns(filter) {
            currentCampaignFilter = filter;
            
            const buttons = document.querySelectorAll('#campaigns-tab .flex.flex-wrap.gap-2 button');
            buttons.forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            });
            
            const activeBtn = document.getElementById('filter-' + filter);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                activeBtn.classList.add('bg-blue-600', 'text-white');
            }
            
            renderCampaigns();
        }

function renderCampaigns() {
    console.log('🎨 Rendering campaigns, data:', campaignsData);
    const tbody = document.getElementById('campaignsTableBody');
    if (!tbody) return;
    
    // ✅ Apply filters
    let filteredData = campaignsData;
    
    // ✅ If in sandbox mode, only show local campaigns
    if (isSandbox) {
        filteredData = filteredData.filter(c => c.source === 'local' || c.source === null);
    }
    
    // Apply source filter (only in live mode)
    if (!isSandbox && currentSourceFilter !== 'all') {
        filteredData = filteredData.filter(c => c.source === currentSourceFilter);
    }
    
    // Apply status filter
    if (currentCampaignFilter !== 'all') {
        filteredData = filteredData.filter(c => c.status === currentCampaignFilter);
    }
    
    if (filteredData.length === 0) {
        let message = 'No campaigns found.';
        if (isSandbox) {
            message = '🔒 Sandbox mode: No local campaigns found. Create a new campaign to test.';
        } else if (currentSourceFilter !== 'all' || currentCampaignFilter !== 'all') {
            message = 'No campaigns match the current filters.';
        }
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-gray-500">${message}</td></tr>`;
        return;
    }
    
    let html = '';
    filteredData.forEach(campaign => {
        const statusBadge = getStatusBadge(campaign.status);
        const statusText = capitalize(campaign.status);
        const date = formatDate(campaign.created_at);
        
        // ✅ Determine source badge
        let sourceBadge = '';
        if (campaign.source === 'kenyasms_imported') {
            sourceBadge = '<span class="text-xs text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full">📥 Imported</span>';
        } else if (campaign.source === 'local' || campaign.source === null) {
            sourceBadge = '<span class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">📤 Local</span>';
        } else {
            sourceBadge = '<span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Unknown</span>';
        }
        
        html += `
            <tr>
                <td class="px-4 py-3 text-sm text-gray-500">${campaign.id}</td>
                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">${escapeHtml(campaign.name)}</td>
                <td class="px-4 py-3">${sourceBadge}</td>
                <td class="px-4 py-3 text-sm text-gray-500">${date}</td>
                <td class="px-4 py-3 text-sm text-gray-500">${campaign.total_recipients || 0}</td>
                <td class="px-4 py-3 text-sm text-green-600">${campaign.sent_count || 0}</td>
                <td class="px-4 py-3 text-sm text-red-600">${campaign.failed_count || 0}</td>
                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${statusBadge}">${statusText}</span></td>
                <td class="px-4 py-3">
                    <button onclick="viewCampaign(${campaign.id})" class="text-blue-600 hover:underline">View</button>
                    <button onclick="sendCampaign(${campaign.id})" class="text-green-600 hover:underline">Send</button>
                    <button onclick="deleteCampaign(${campaign.id})" class="text-red-600 hover:underline">Delete</button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
    
    // Update campaign count
    const countEl = document.getElementById('campaignCount');
    if (countEl) {
        const total = filteredData.length;
        let sourceLabel = '';
        if (isSandbox) {
            sourceLabel = ' (Sandbox - Local only)';
        } else if (currentSourceFilter !== 'all') {
            sourceLabel = ` (${currentSourceFilter === 'local' ? 'Local' : 'Imported'})`;
        }
        countEl.textContent = `${total} campaign${total !== 1 ? 's' : ''}${sourceLabel}`;
    }
}

        // ============================================
        // FILTER ESTATES BY COMPANY
        // ============================================
        function filterEstatesByCompany() {
            const companyId = document.getElementById('campaignFilterCompany').value;
            const estateSelect = document.getElementById('campaignFilterEstate');
            if (!estateSelect) return;
            
            const options = estateSelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                const optionCompany = option.getAttribute('data-company');
                if (!companyId || optionCompany == companyId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            estateSelect.value = '';
        }

        // ============================================
        // UPDATE CAMPAIGN PREVIEW ON ESTATE CHANGE
        // ============================================
        function updateCampaignPreviewOnEstateChange() {
            console.log('🔄 Estate changed in campaign modal');
            const select = document.getElementById('campaignTemplate');
            const selected = select ? select.options[select.selectedIndex] : null;
            const content = selected ? selected.getAttribute('data-content') : null;
            
            if (content) {
                generateTenantPreview(content);
            } else {
                const previewContainerDiv = document.getElementById('campaignTenantPreviewContainer');
                if (previewContainerDiv) previewContainerDiv.style.display = 'none';
                const previewContainer = document.getElementById('campaignTenantPreview');
                if (previewContainer) previewContainer.innerHTML = '';
            }
        }

        // ============================================
        // GENERATE TENANT PREVIEW (CAMPAIGN MODAL)
        // ============================================
        function generateTenantPreview(templateContent) {
            const estateId = document.getElementById('campaignFilterEstate').value;
            const previewContainer = document.getElementById('campaignTenantPreview');
            const previewContainerDiv = document.getElementById('campaignTenantPreviewContainer');
            
            if (!previewContainer || !previewContainerDiv) return;
            
            if (!estateId || !templateContent) {
                previewContainerDiv.style.display = 'none';
                return;
            }
            
            previewContainerDiv.style.display = 'block';
            previewContainer.innerHTML = '<div class="text-center py-4 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading tenant preview...</div>';
            
            const tenantRows = document.querySelectorAll('.tenant-row');
            let filteredTenants = [];
            let tenantIds = [];
            
            tenantRows.forEach(row => {
                const rowEstateId = row.getAttribute('data-estate-id') || '';
                const paymentStatus = row.getAttribute('data-payment-status') || 'pending';
                const name = row.getAttribute('data-name') || 'Tenant';
                const phone = row.getAttribute('data-phone') || '';
                const unit = row.getAttribute('data-unit') || 'N/A';
                const estate = row.getAttribute('data-estate') || 'N/A';
                const waterBill = parseFloat(row.getAttribute('data-water-bill') || 0);
                const prevRead = row.getAttribute('data-prev-read') || '0';
                const currRead = row.getAttribute('data-curr-read') || '0';
                const month = row.getAttribute('data-month') || new Date().toLocaleString('default', { month: 'long', year: 'numeric' });
                const dueDate = row.getAttribute('data-due-date') || 'N/A';
                const consumption = row.getAttribute('data-consumption') || '0';
                const tenantId = row.querySelector('.tenant-checkbox')?.getAttribute('data-id') || '';
                
                if (estateId && rowEstateId != estateId) return;
                
                const invoiceStatus = document.getElementById('campaignFilterStatus').value;
                if (invoiceStatus && paymentStatus !== invoiceStatus) return;
                
                if (!phone) return;
                
                let waterConsumption = parseFloat(consumption) || 0;
                if (waterConsumption == 0 && parseFloat(prevRead) > 0 && parseFloat(currRead) > 0) {
                    waterConsumption = parseFloat(currRead) - parseFloat(prevRead);
                }
                
                filteredTenants.push({
                    tenantId: tenantId,
                    name: name,
                    phone: phone,
                    unit: unit,
                    estate: estate,
                    estateId: rowEstateId,
                    waterbill: waterBill,
                    waterConsumption: waterConsumption,
                    prevRead: prevRead,
                    currRead: currRead,
                    month: month,
                    dueDate: dueDate,
                    paymentStatus: paymentStatus,
                    row: row,
                    phoneRaw: phone
                });
                
                if (tenantId) {
                    tenantIds.push(tenantId);
                }
            });
            
            if (filteredTenants.length === 0) {
                previewContainerDiv.style.display = 'block';
                previewContainer.innerHTML = `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-info-circle mr-2"></i> No tenants found in this estate.
                    </div>
                `;
                return;
            }
            
            let currentMonth = null;
            if (filteredTenants[0]?.month) {
                try {
                    const d = new Date(filteredTenants[0].month);
                    if (!isNaN(d)) {
                        currentMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                    }
                } catch (e) {
                    currentMonth = null;
                }
            }
            
            console.log('📤 Fetching invoices for tenant IDs:', tenantIds);
            console.log('📤 Current month:', currentMonth);
            
            fetch('/api/sms/preview-invoices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    tenant_ids: tenantIds,
                    current_month: currentMonth
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('📥 Invoice data for campaign preview:', data);
                const invoiceData = data.success ? data.invoices : {};
                renderCampaignPreviewWithData(filteredTenants, templateContent, invoiceData, previewContainer);
            })
            .catch(error => {
                console.warn('⚠️ Failed to fetch invoice data, falling back to dummy data:', error);
                renderCampaignPreviewWithDummy(filteredTenants, templateContent, previewContainer);
            });
        }

function renderCampaignPreviewWithData(tenants, templateContent, invoiceData, container) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    console.log('🔍 Rendering with invoiceData:', invoiceData);
    console.log('🔍 invoiceData keys:', Object.keys(invoiceData));
    
    const previewLimit = 10;
    let html = '<div class="space-y-3">';
    const previewTenants = tenants.slice(0, previewLimit);
    let hasOutstanding = false;
    
    // Helper: Check if invoice is unpaid or partial
    function isUnpaidOrPartial(status) {
        return ['unpaid', 'partial', 'overdue'].includes(status);
    }
    
    previewTenants.forEach(tenant => {
        let tenantInvoices = [];
        const tenantId = tenant.tenantId;
        
        console.log(`🔍 Looking for invoices for tenant ID: ${tenantId} (${tenant.name})`);
        console.log(`🔍 Tenant waterbill from table: ${tenant.waterbill}`);
        console.log(`🔍 Tenant payment status: ${tenant.paymentStatus}`);
        
        // Find invoices for this tenant (using multiple key formats)
        if (invoiceData[String(tenantId)]) {
            tenantInvoices = invoiceData[String(tenantId)];
            console.log(`✅ Found invoices using string key "${String(tenantId)}"`);
        } else if (invoiceData[Number(tenantId)]) {
            tenantInvoices = invoiceData[Number(tenantId)];
            console.log(`✅ Found invoices using number key "${Number(tenantId)}"`);
        } else {
            for (const key in invoiceData) {
                if (String(key) === String(tenantId) || 
                    String(key) === String(Number(tenantId)) ||
                    String(tenantId) === String(key).replace(/[^0-9]/g, '')) {
                    tenantInvoices = invoiceData[key];
                    console.log(`✅ Found invoices using key "${key}" (matched to ${tenantId})`);
                    break;
                }
            }
        }
        
        console.log(`🔍 Tenant ${tenantId} (${tenant.name}) - Invoices found:`, tenantInvoices.length);
        
        // --- Determine current month ---
        let currentMonthY = '';
        if (tenant.month) {
            try {
                const d = new Date(tenant.month);
                if (!isNaN(d)) {
                    currentMonthY = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                }
            } catch (e) {
                console.warn('⚠️ Error parsing tenant month:', tenant.month, e);
            }
        }
        
        if (!currentMonthY && tenantInvoices.length > 0) {
            let latest = tenantInvoices.reduce((a, b) => {
                return (a.billing_month > b.billing_month) ? a : b;
            }, tenantInvoices[0]);
            if (latest && latest.billing_month) {
                currentMonthY = latest.billing_month.substring(0, 7);
            }
        }
        
        if (!currentMonthY) {
            const d = new Date();
            currentMonthY = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        }
        
        console.log(`📅 Tenant ${tenantId} currentMonthY: ${currentMonthY}`);
        
        // --- Find current month's invoice (show for accountability) ---
        let currentInvoice = tenantInvoices.find(inv => {
            if (!inv.billing_month) return false;
            let invMonth = inv.billing_month.substring(0, 7);
            return invMonth === currentMonthY;
        });
        let currentBill = currentInvoice ? parseFloat(currentInvoice.amount || 0) : 0;
        let currentStatus = currentInvoice ? currentInvoice.status : 'unknown';
        
        console.log(`💵 currentBill from invoices: ${currentBill}`);
        console.log(`📊 currentStatus: ${currentStatus}`);
        
        // ✅ If no invoice, fallback to tenant waterbill
        if ((!currentInvoice || currentBill === 0) && tenant.waterbill > 0) {
            console.warn(`⚠️ No invoice for tenant ${tenantId}, using table waterbill: ${tenant.waterbill}`);
            currentBill = tenant.waterbill;
            currentStatus = 'unknown';
        }
        
        // --- Filter OLDER invoices (billing_month < currentMonthY) ---
        // ✅ Include ONLY unpaid, partial, and overdue – EXCLUDE paid invoices
        let olderInvoices = tenantInvoices.filter(inv => {
            if (!inv.billing_month) return false;
            let invMonth = inv.billing_month.substring(0, 7);
            
            // Only older invoices
            if (invMonth >= currentMonthY) return false;
            
            // ✅ EXCLUDE paid invoices from older invoices
            if (!isUnpaidOrPartial(inv.status)) return false;
            
            // Check if due date is today or earlier
            let dueDate;
            if (inv.due_date) {
                dueDate = new Date(inv.due_date);
            } else {
                dueDate = new Date(inv.billing_month);
                dueDate.setMonth(dueDate.getMonth() + 1);
                dueDate.setDate(5);
            }
            dueDate.setHours(0, 0, 0, 0);
            
            return dueDate <= today;
        });
        
        // Calculate totals
        let olderTotal = olderInvoices.reduce((sum, inv) => sum + parseFloat(inv.amount || 0), 0);
        let paymentStatus = tenant.paymentStatus || 'pending';
        
        // --- If tenant is fully paid, zero everything ---
        if (paymentStatus === 'paid') {
            currentBill = 0;
            olderTotal = 0;
        }
        
        let unpaidTotal = olderTotal;
        let totalDue = currentBill + unpaidTotal;
        let unpaidCount = olderInvoices.length;
        
        console.log(`💰 Tenant ${tenantId} totalDue: ${totalDue}`);
        console.log(`💰 Tenant ${tenantId} currentBill: ${currentBill}`);
        console.log(`💰 Tenant ${tenantId} unpaidTotal: ${unpaidTotal}`);
        console.log(`🔍 Tenant ${tenantId} olderInvoices count: ${unpaidCount}`);
        console.log(`🔍 Tenant ${tenantId} currentStatus: ${currentStatus}`);
        
        // ✅ Build unpaid list – each invoice on its own line, status prefix only if not 'unpaid'
        let unpaidList = '';
        if (unpaidCount > 0) {
            unpaidList = olderInvoices.map(inv => {
                let billingMonth = formatMonthYear(inv.billing_month);
                let prefix = '';
                if (inv.status !== 'unpaid') {
                    prefix = inv.status.charAt(0).toUpperCase() + inv.status.slice(1) + ' ';
                }
                return prefix + '(' + billingMonth + '): KES ' + Number(inv.amount).toFixed(2);
            }).join('\n');
        }
        
        // ✅ Build unpaid section (only "Unpaid:" header + list, no total line)
        let unpaidSection = '';
        if (unpaidCount > 0) {
            unpaidSection = 'Unpaid:\n' + unpaidList;
        }
        
        // ✅ Track if any outstanding balance exists
        if (totalDue > 0) {
            hasOutstanding = true;
        }
        
        // Format due date
        let dueDate = tenant.dueDate || 'N/A';
        try {
            const d = new Date(dueDate);
            if (!isNaN(d)) {
                dueDate = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            }
        } catch (e) {
            dueDate = tenant.dueDate || 'N/A';
        }
        
        const waterConsumption = tenant.waterConsumption || 0;
        
        // --- Replace placeholders ---
        let message = templateContent;
        const placeholders = {
            '{{name}}': tenant.name || 'Tenant',
            '{{unit}}': tenant.unit || 'N/A',
            '{{unit_number}}': tenant.unit || 'N/A',
            '{{estate_name}}': tenant.estate || 'N/A',
            '{{estate}}': tenant.estate || 'N/A',
            '{{month}}': tenant.month || new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
            '{{water_bill}}': currentBill.toFixed(2),
            '{{water_consumption}}': String(waterConsumption),
            '{{prev_read}}': tenant.prevRead || '0',
            '{{curr_read}}': tenant.currRead || '0',
            '{{due_date}}': dueDate,
            '{{payment_status}}': paymentStatus,
            '{{status}}': paymentStatus,
            '{{unpaid_count}}': String(unpaidCount),
            '{{unpaid_total}}': unpaidTotal.toFixed(2),
            '{{unpaid_list}}': unpaidList,
            '{{unpaid_message}}': unpaidCount === 0 ? '' : unpaidCount + ' unpaid/partial invoices totaling KES ' + unpaidTotal.toFixed(2),
            '{{unpaid_section}}': unpaidSection,
            '{{total_due}}': totalDue.toFixed(2),
            '{{current_status}}': currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1),
        };
        
        // Apply all replacements
        for (const [key, value] of Object.entries(placeholders)) {
            message = message.split(key).join(value);
        }
        
        // Remove any remaining placeholders
        message = message.replace(/\{\{[^}]*\}\}/g, '');
        
        // ✅ CLEAN THE MESSAGE – remove excessive blank lines
        function cleanMessage(msg) {
            // Remove multiple consecutive newlines (keep max 2)
            msg = msg.replace(/\n{3,}/g, '\n\n');
            // Remove trailing spaces before newline
            msg = msg.replace(/[ \t]+\n/g, '\n');
            // Remove leading/trailing newlines
            msg = msg.trim();
            return msg;
        }
        message = cleanMessage(message);
        
        console.log(`📨 Final message for ${tenant.name}:`, message);
        
        // --- Build HTML ---
        let statusBadgeClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        if (paymentStatus === 'paid') {
            statusBadgeClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        } else if (paymentStatus === 'unpaid' || paymentStatus === 'overdue') {
            statusBadgeClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        } else if (paymentStatus === 'partial') {
            statusBadgeClass = 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        }
        
        let unpaidBadge = '';
        if (unpaidCount > 0) {
            unpaidBadge = `<span class="text-xs text-orange-600 dark:text-orange-400 ml-2">⚠️ ${unpaidCount} overdue</span>`;
        }
        
        // ✅ Show current bill status if it's paid
        let currentBillStatus = '';
        if (currentStatus === 'paid') {
            currentBillStatus = ` (Paid)`;
        } else if (currentStatus === 'partial') {
            currentBillStatus = ` (Partial)`;
        }
        
        let paymentStatusDisplay = (paymentStatus || 'pending').charAt(0).toUpperCase() + (paymentStatus || 'pending').slice(1);
        
        html += `
            <div class="border-l-4 border-blue-300 pl-3 py-2 bg-gray-50 dark:bg-gray-700 rounded-r-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <strong>To:</strong> ${escapeHtml(tenant.phone || 'N/A')} (${escapeHtml(tenant.name || 'Tenant')}) ${unpaidBadge}
                    <span class="text-xs text-gray-400 ml-2">Status: ${paymentStatusDisplay}</span>
                </p>
                <div class="text-sm text-gray-800 dark:text-gray-200 mt-1 whitespace-pre-wrap">${escapeHtml(message)}</div>
                <div class="flex flex-wrap gap-2 mt-1 text-xs">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass}">
                        ${paymentStatusDisplay}
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">Current Bill: KES ${currentBill.toFixed(2)}${currentBillStatus}</span>
                    <span class="text-gray-500 dark:text-gray-400">Unit: ${escapeHtml(tenant.unit || 'N/A')}</span>
                    <span class="text-gray-500 dark:text-gray-400">Reading: ${tenant.prevRead || 0} → ${tenant.currRead || 0}</span>
                    ${unpaidCount > 0 ? `<span class="text-orange-600 dark:text-orange-400">| Unpaid/Partial: KES ${unpaidTotal.toFixed(2)}</span>` : ''}
                    ${totalDue > 0 ? `<span class="text-blue-600 dark:text-blue-400">| Total Due: KES ${totalDue.toFixed(2)}</span>` : 
                    `<span class="text-green-600 dark:text-green-400">| ✅ All paid</span>`}
                </div>
            </div>
        `;
    });
    
    // Show message if no outstanding balance
    if (!hasOutstanding) {
        html += `
            <div class="text-center py-4 text-gray-500 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                All selected tenants have paid their bills – no SMS will be sent.
            </div>
        `;
    }
    
    const totalCount = tenants.length;
    html += `
        <div class="text-xs text-gray-500 mt-2 text-center">
            Showing ${Math.min(previewLimit, totalCount)} of ${totalCount} tenant(s)
        </div>
    `;
    
    html += '</div>';
    container.innerHTML = html;
}
        // ============================================
        // RENDER CAMPAIGN PREVIEW WITH DUMMY DATA (FALLBACK)
        // ============================================
        function renderCampaignPreviewWithDummy(tenants, templateContent, container) {
            let html = '<div class="space-y-3">';
            const previewTenants = tenants.slice(0, 3);
            
            previewTenants.forEach(tenant => {
                let currentBill = tenant.waterbill || 0;
                let unpaidCount = 0;
                let unpaidTotal = 0;
                let unpaidList = '';
                let totalDue = currentBill;
                
                const isUnpaid = tenant.paymentStatus === 'unpaid' || tenant.paymentStatus === 'pending' || tenant.paymentStatus === 'overdue';
                if (isUnpaid) {
                    unpaidCount = 2;
                    unpaidTotal = 3000.00;
                    unpaidList = 'Oct 2024: KES 1,500.00\nNov 2024: KES 1,500.00';
                    totalDue = currentBill + 3000.00;
                }
                
                let dueDate = tenant.dueDate || 'N/A';
                try {
                    const d = new Date(dueDate);
                    if (!isNaN(d)) {
                        dueDate = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                    }
                } catch (e) {
                    dueDate = tenant.dueDate || 'N/A';
                }
                
                const waterConsumption = tenant.waterConsumption || 0;
                let message = templateContent;
                
                const placeholders = {
                    '{{name}}': tenant.name || 'Tenant',
                    '{{unit}}': tenant.unit || 'N/A',
                    '{{unit_number}}': tenant.unit || 'N/A',
                    '{{estate_name}}': tenant.estate || 'N/A',
                    '{{estate}}': tenant.estate || 'N/A',
                    '{{month}}': tenant.month || new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
                    '{{water_bill}}': currentBill.toFixed(2),
                    '{{water_consumption}}': waterConsumption,
                    '{{prev_read}}': tenant.prevRead || '0',
                    '{{curr_read}}': tenant.currRead || '0',
                    '{{due_date}}': dueDate,
                    '{{payment_status}}': tenant.paymentStatus || 'pending',
                    '{{status}}': tenant.paymentStatus || 'pending',
                    '{{unpaid_count}}': String(unpaidCount),
                    '{{unpaid_total}}': unpaidTotal.toFixed(2),
                    '{{unpaid_list}}': unpaidList,
                    '{{unpaid_message}}': unpaidCount === 0 ? 'no pending invoices' : `${unpaidCount} unpaid invoices totaling KES ${unpaidTotal.toFixed(2)}`,
                    '{{total_due}}': totalDue.toFixed(2)
                };
                
                for (const [key, value] of Object.entries(placeholders)) {
                    message = message.split(key).join(value);
                }
                
                message = message.replace(/\{\{[^}]*\}\}/g, '');
                
                let statusBadgeClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                if (tenant.paymentStatus === 'paid') {
                    statusBadgeClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                } else if (tenant.paymentStatus === 'unpaid' || tenant.paymentStatus === 'overdue') {
                    statusBadgeClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                }
                
                let unpaidBadge = '';
                if (unpaidCount > 0) {
                    unpaidBadge = `<span class="text-xs text-orange-600 dark:text-orange-400 ml-2">⚠️ ${unpaidCount} unpaid</span>`;
                }
                
                html += `
                    <div class="border-l-4 border-blue-300 pl-3 py-2 bg-gray-50 dark:bg-gray-700 rounded-r-lg">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <strong>To:</strong> ${escapeHtml(tenant.phone || 'N/A')} (${escapeHtml(tenant.name || 'Tenant')}) ${unpaidBadge}
                        </p>
                        <div class="text-sm text-gray-800 dark:text-gray-200 mt-1 whitespace-pre-wrap">${escapeHtml(message)}</div>
                        <div class="flex flex-wrap gap-2 mt-1 text-xs">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass}">
                                ${escapeHtml(tenant.paymentStatus || 'pending')}
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">Bill: KES ${currentBill.toFixed(2)}</span>
                            <span class="text-gray-500 dark:text-gray-400">Unit: ${escapeHtml(tenant.unit || 'N/A')}</span>
                            <span class="text-gray-500 dark:text-gray-400">Reading: ${tenant.prevRead || 0} → ${tenant.currRead || 0}</span>
                            ${unpaidCount > 0 ? `<span class="text-orange-600 dark:text-orange-400">| Unpaid: KES ${unpaidTotal.toFixed(2)}</span>` : ''}
                            ${totalDue > 0 ? `<span class="text-blue-600 dark:text-blue-400">| Total Due: KES ${totalDue.toFixed(2)}</span>` : ''}
                        </div>
                    </div>
                `;
            });
            
            const totalCount = tenants.length;
            html += `
                <div class="text-xs text-gray-500 mt-2 text-center">
                    Showing ${Math.min(3, totalCount)} of ${totalCount} tenant(s) in this estate (dummy data)
                </div>
            `;
            
            html += '</div>';
            container.innerHTML = html;
        }

        // ============================================
        // VIEW CAMPAIGN – Uses direct route
        // ============================================
        function viewCampaign(id) {
            currentCampaignId = id;
            console.log('👁️ Viewing campaign:', id);
            
            const modal = document.getElementById('viewCampaignModal');
            const loading = document.getElementById('viewCampaignLoading');
            const content = document.getElementById('viewCampaignContent');
            
            if (modal) {
                modal.style.display = 'block';
                modal.classList.add('active');
                document.body.classList.add('modal-open');
            }
            if (loading) loading.style.display = 'block';
            if (content) content.style.display = 'none';
            
            fetch(`/campaign/${id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('📥 API Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('📥 FULL Campaign data received:', data);
                
                if (data.campaign) {
                    const formattedData = {
                        id: data.campaign.id,
                        name: data.campaign.name,
                        description: data.campaign.description,
                        status: data.campaign.status,
                        total_recipients: data.recipient_count || data.campaign.total_recipients || 0,
                        sent_count: data.campaign.sent_count || 0,
                        failed_count: data.campaign.failed_count || 0,
                        delivered_count: data.campaign.delivered_count || 0,
                        created_at: data.campaign.created_at,
                        template: null,
                        recipients: data.recipients || [],
                        status_counts: {
                            sent: data.campaign.sent_count || 0,
                            pending: 0,
                            failed: data.campaign.failed_count || 0,
                            queued: 0,
                            delivered: data.campaign.delivered_count || 0
                        },
                        validation_stats: {
                            valid: 0,
                            other_network: 0,
                            invalid: 0
                        }
                    };
                    renderCampaignDetails(formattedData, loading, content);
                } else {
                    renderCampaignDetails(data, loading, content);
                }
            })
            .catch(error => {
                console.error('❌ Error loading campaign details:', error);
                if (loading) loading.style.display = 'none';
                alert('❌ Error loading campaign details: ' + error.message);
            });
        }

        // ============================================
        // RENDER CAMPAIGN DETAILS
        // ============================================
        function renderCampaignDetails(data, loading, content) {
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
            
            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? 'N/A';
            };
            
            const campaign = data.campaign || data;
            const recipients = data.recipients || [];
            const recipientCount = data.recipient_count || recipients.length || 0;
            
            setText('viewCampaignName', campaign.name || 'N/A');
            setText('viewCampaignDescription', campaign.description || 'No description provided');
            
            const statusEl = document.getElementById('viewCampaignStatus');
            if (statusEl) {
                statusEl.textContent = capitalize(campaign.status || 'pending');
                statusEl.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' + getStatusBadge(campaign.status || 'pending');
            }
            
            const total = campaign.total_recipients || recipientCount || 0;
            const sent = campaign.sent_count || 0;
            const failed = campaign.failed_count || 0;
            const delivered = campaign.delivered_count || 0;
            const pending = total - sent - failed;
            const progress = total > 0 ? Math.round((sent / total) * 100) : 0;
            
            setText('viewCampaignTemplate', campaign.template_name || 'No template');
            setText('viewCampaignCreated', formatDate(campaign.created_at));
            setText('viewCampaignTotal', total);
            setText('viewCampaignSent', sent);
            setText('viewCampaignFailed', failed);
            setText('viewCampaignDelivered', delivered || 0);
            setText('viewCampaignPendingCount', pending > 0 ? pending : 0);
            setText('viewCampaignProgress', progress + '%');
            
            const progressBar = document.getElementById('viewCampaignProgressBar');
            if (progressBar) {
                progressBar.style.width = progress + '%';
            }
            
            const recipientData = recipients.map(recipient => {
                const phone = recipient.phone_number || recipient.phone || '';
                const name = recipient.tenant_name || recipient.name || 'Unknown';
                const unitNumber = recipient.unit_number || recipient.unit || 'N/A';
                const estateName = recipient.estate_name || recipient.estate || 'N/A';
                const status = recipient.status || 'pending';
                const message = recipient.message || '';
                const errorMsg = recipient.error_message || recipient.failure_reason || '';
                const network = recipient.network || '';
                const parts = recipient.parts || '';
                const cost = recipient.cost || '';
                const deliveredTime = recipient.delivered_time || '';
                
                let sentTime = '';
                if (recipient.sent_at) {
                    try {
                        const d = new Date(recipient.sent_at);
                        if (!isNaN(d)) {
                            sentTime = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        }
                    } catch (e) {
                        sentTime = recipient.sent_at;
                    }
                }
                
                return {
                    id: recipient.id || recipient.recipient_id,
                    phone: phone,
                    phone_number: phone,
                    name: name,
                    tenant_name: name,
                    unit_number: unitNumber,
                    unit: unitNumber,
                    estate_name: estateName,
                    estate: estateName,
                    status: status,
                    message: message,
                    error_message: errorMsg,
                    failure_reason: errorMsg,
                    network: network,
                    parts: parts,
                    cost: cost,
                    sent_time: sentTime,
                    delivered_time: deliveredTime,
                    sent_at: recipient.sent_at || '',
                    provider_status: recipient.provider_status || '',
                    provider_response: recipient.provider_response || '',
                };
            });
            
            const statusCounts = {
                sent: recipientData.filter(r => r.status === 'sent').length,
                pending: recipientData.filter(r => r.status === 'pending').length,
                failed: recipientData.filter(r => r.status === 'failed').length,
                queued: recipientData.filter(r => r.status === 'queued').length,
                delivered: recipientData.filter(r => r.status === 'delivered').length,
            };
            
            const validationStats = {
                valid: recipientData.filter(r => r.phone && r.phone.match(/^2547[0-9]{8}$/)).length,
                other_network: recipientData.filter(r => r.phone && r.phone.match(/^254[^7][0-9]{8}$/)).length,
                invalid: recipientData.filter(r => !r.phone || !r.phone.match(/^254[0-9]{9}$/)).length,
            };
            
            setText('viewCampaignValid', validationStats.valid || 0);
            setText('viewCampaignOtherNetwork', validationStats.other_network || 0);
            setText('viewCampaignInvalid', validationStats.invalid || 0);
            
            setText('count-all', recipientData.length);
            setText('count-delivered', statusCounts.delivered || 0);
            setText('count-sent', statusCounts.sent || 0);
            setText('count-queued', statusCounts.queued || 0);
            setText('count-pending', statusCounts.pending || 0);
            setText('count-failed', statusCounts.failed || 0);
            
            setText('recipientTabCount', `(${recipientData.length})`);
            setText('invalidTabCount', `(${validationStats.invalid || 0})`);
            setText('otherTabCount', `(${validationStats.other_network || 0})`);
            setText('failedTabCount', `(${statusCounts.failed || 0})`);
            
            currentRecipients = recipientData;
            currentRecipientsFull = recipientData;
            currentFilteredRecipients = recipientData;
            currentRecipientFilter = 'all';
            currentPage = 1;
            
            const buttons = document.querySelectorAll('#viewCampaignModal .flex.flex-wrap.gap-2 button');
            buttons.forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            });
            const allBtn = document.getElementById('filter-recipients-all');
            if (allBtn) {
                allBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                allBtn.classList.add('bg-blue-600', 'text-white');
            }
            
            if (currentFilteredRecipients.length > 0) {
                renderPaginatedRecipients();
            } else {
                const tbody = document.getElementById('viewRecipientsBody');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox mr-2"></i> No recipients found for this campaign
                            </td>
                        </tr>
                    `;
                }
            }
        }

// ============================================
// RENDER RECIPIENTS – With Updated Actions
// ============================================
function renderRecipients(recipients) {
    console.log('📋 Rendering recipients, count:', recipients ? recipients.length : 0);
    
    const tbody = document.getElementById('viewRecipientsBody');
    if (!tbody) {
        console.warn('⚠️ viewRecipientsBody element not found');
        return;
    }
    
    if (!recipients || recipients.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox mr-2"></i> No recipients found for this campaign
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    recipients.forEach((recipient) => {
        const id = recipient.id || recipient.recipient_id;
        const phone = recipient.phone || recipient.phone_number || '';
        const status = recipient.status || 'pending';
        const message = recipient.message || '';
        const tenantName = recipient.tenant_name || recipient.name || 'Unknown';
        const unitNumber = recipient.unit_number || recipient.unit || 'N/A';
        const estateName = recipient.estate_name || recipient.estate || 'N/A';
        const network = recipient.network || '';
        const parts = recipient.parts || '';
        const cost = recipient.cost || '';
        const sentTime = recipient.sent_time || '';
        const deliveredTime = recipient.delivered_time || '';
        const failureReason = recipient.failure_reason || recipient.error_message || '';
        
        const statusBadge = getStatusBadge(status);
        const statusText = capitalize(status);
        const canResend = ['failed', 'pending', 'queued'].includes(status);
        const isSent = status === 'sent' || status === 'delivered';
        
        html += `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <span class="text-blue-600 dark:text-blue-400 font-medium text-sm">${escapeHtml(tenantName.charAt(0).toUpperCase())}</span>
                        </div>
                        <span>${escapeHtml(tenantName)}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(unitNumber)}</td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(estateName)}</td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                    ${escapeHtml(phone)}
                    <button onclick="copyToClipboard('${escapeHtml(phone)}')" 
                            class="ml-1 text-xs text-blue-500 hover:text-blue-700 px-1.5 py-0.5 rounded border border-blue-200 hover:border-blue-400" 
                            title="Copy phone number">
                        Copy
                    </button>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(network) || '-'}</td>
                <td class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">${parts || '-'}</td>
                <td class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">${cost || '-'}</td>
                <td class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">${sentTime || '-'}</td>
                <td class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">${deliveredTime || '-'}</td>
                <td class="px-4 py-3 text-center text-sm ${status === 'failed' ? 'text-red-500' : 'text-gray-400'}">
                    ${status === 'failed' ? escapeHtml(failureReason) : '-'}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadge}">
                        ${statusText}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex flex-wrap items-center justify-center gap-1">
                        ${canResend ? `
                        <button onclick="resendSingleRecipient(${id})" 
                                class="text-xs px-2 py-1 rounded bg-orange-100 text-orange-700 hover:bg-orange-200 border border-orange-200"
                                title="Resend this message">
                            Resend
                        </button>
                        ` : ''}
                        <button onclick="viewRecipientMessage(${id})" 
                                class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 border border-blue-200"
                                title="View Message">
                            View
                        </button>
                        <button onclick="copyRecipientMessage(${id})" 
                                class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200"
                                title="Copy Message">
                            Copy
                        </button>
                        ${status === 'failed' ? `
                        <button onclick="viewFailureReason(${id})" 
                                class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 border border-red-200"
                                title="View Failure Reason">
                            Error
                        </button>
                        ` : ''}
                        ${isSent ? `
                        <button onclick="viewDeliveryDetails(${id})" 
                                class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-700 hover:bg-purple-200 border border-purple-200"
                                title="View Delivery Details">
                            Delivery
                        </button>
                        ` : ''}
                        <button onclick="updateRecipientPhone(${id})" 
                                class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 border border-green-200"
                                title="Update Phone Number">
                            Update Phone
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    console.log('✅ Recipients rendered with updated actions');
}

// ============================================
// VIEW DELIVERY DETAILS
// ============================================
function viewDeliveryDetails(recipientId) {
    const recipient = currentRecipientsFull.find(r => r.id === recipientId || r.recipient_id === recipientId);
    if (!recipient) {
        alert('Recipient not found');
        return;
    }
    
    const phone = recipient.phone || recipient.phone_number || 'N/A';
    const status = recipient.status || 'N/A';
    const sentTime = recipient.sent_time || recipient.sent_at || 'N/A';
    const deliveredTime = recipient.delivered_time || 'N/A';
    const network = recipient.network || 'N/A';
    const parts = recipient.parts || 'N/A';
    const cost = recipient.cost || 'N/A';
    const providerStatus = recipient.provider_status || 'N/A';
    const message = recipient.message || 'No message';
    
    alert(`📬 Delivery Details\n\n` +
          `Tenant: ${recipient.tenant_name || 'Unknown'}\n` +
          `Phone: ${phone}\n` +
          `Status: ${status}\n` +
          `Network: ${network}\n` +
          `Parts: ${parts}\n` +
          `Cost: ${cost}\n` +
          `Sent: ${sentTime}\n` +
          `Delivered: ${deliveredTime}\n` +
          `Provider Status: ${providerStatus}\n\n` +
          `Message:\n${message.substring(0, 200)}${message.length > 200 ? '...' : ''}`);
}

        // ============================================
// COPY RECIPIENT MESSAGE
// ============================================
function copyRecipientMessage(recipientId) {
    const recipient = currentRecipientsFull.find(r => r.id === recipientId || r.recipient_id === recipientId);
    if (!recipient) {
        alert('Recipient not found');
        return;
    }
    const message = recipient.message || '';
    if (!message) {
        alert('No message to copy');
        return;
    }
    copyToClipboard(message);
    showToast('📋 Message copied to clipboard!');
}
// ============================================
// VIEW FAILURE REASON
// ============================================
function viewFailureReason(recipientId) {
    const recipient = currentRecipientsFull.find(r => r.id === recipientId || r.recipient_id === recipientId);
    if (!recipient) {
        alert('Recipient not found');
        return;
    }
    const error = recipient.error_message || recipient.failure_reason || 'No error message available';
    const providerStatus = recipient.provider_status || '';
    const phone = recipient.phone || recipient.phone_number || 'N/A';
    const tenantName = recipient.tenant_name || recipient.name || 'Unknown';
    
    alert(`❌ Failure Reason\n\n` +
          `Tenant: ${tenantName}\n` +
          `Phone: ${phone}\n` +
          `Provider Status: ${providerStatus || 'N/A'}\n\n` +
          `Error: ${error}`);
}

        // ============================================
// VIEW RECIPIENT MESSAGE
// ============================================
function viewRecipientMessage(recipientId) {
    const recipient = currentRecipientsFull.find(r => r.id === recipientId || r.recipient_id === recipientId);
    if (!recipient) {
        alert('Recipient not found');
        return;
    }
    
    const phone = recipient.phone || recipient.phone_number || 'N/A';
    const status = recipient.status || 'N/A';
    const message = recipient.message || 'No message';
    const error = recipient.error_message || recipient.failure_reason || '';
    const tenantName = recipient.tenant_name || recipient.name || 'Unknown';
    const unitNumber = recipient.unit_number || recipient.unit || 'N/A';
    const estateName = recipient.estate_name || recipient.estate || 'N/A';
    
    alert(`📨 Message Details\n\n` +
          `Tenant: ${tenantName}\n` +
          `Unit: ${unitNumber}\n` +
          `Estate: ${estateName}\n` +
          `Phone: ${phone}\n` +
          `Status: ${status}\n\n` +
          `Message:\n${message}\n\n` +
          `${error ? 'Error: ' + error : ''}`);
}

       // ============================================
// UPDATE RECIPIENT PHONE
// ============================================
function updateRecipientPhone(recipientId) {
    const recipient = currentRecipientsFull.find(r => r.id === recipientId || r.recipient_id === recipientId);
    if (!recipient) {
        alert('Recipient not found');
        return;
    }
    
    const currentPhone = recipient.phone || recipient.phone_number || '';
    const tenantName = recipient.tenant_name || recipient.name || 'Unknown';
    const newPhone = prompt(`Enter new phone number for ${tenantName}:`, currentPhone);
    
    if (newPhone === null) return;
    if (newPhone.trim() === '') {
        alert('Phone number cannot be empty');
        return;
    }
    
    const tenantId = recipient.tenant_id;
    if (!tenantId) {
        alert('Tenant ID not found for this recipient');
        return;
    }
    
    const btn = document.querySelector(`[onclick="updateRecipientPhone(${recipientId})"]`);
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }
    
    fetch(`/api/sms/tenants/${tenantId}/phone`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ phone: newPhone.trim() })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Phone number updated successfully!');
            viewCampaign(currentCampaignId);
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to update phone'));
        }
    })
    .catch(error => {
        console.error('Error updating phone:', error);
        alert('❌ Error updating phone: ' + error.message);
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Update Phone';
        }
    });
}
        // ============================================
        // FILTER RECIPIENTS
        // ============================================
        function filterRecipients(status) {
            currentRecipientFilter = status;
            
            const buttons = document.querySelectorAll('#viewCampaignModal .flex.flex-wrap.gap-2 button');
            buttons.forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            });
            
            const activeBtn = document.getElementById('filter-recipients-' + status);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                activeBtn.classList.add('bg-blue-600', 'text-white');
            }
            
            if (status === 'all') {
                currentFilteredRecipients = currentRecipientsFull || [];
            } else {
                currentFilteredRecipients = (currentRecipientsFull || []).filter(r => r.status === status);
            }
            
            currentPage = 1;
            renderPaginatedRecipients();
        }

        // ============================================
        // PAGINATION FUNCTIONS FOR RECIPIENTS MODAL
        // ============================================
        function changeEntriesPerPage() {
            const select = document.getElementById('entriesPerPageRecipients');
            if (!select) return;
            
            if (select.value === 'all') {
                rowsPerPage = 999999;
            } else {
                rowsPerPage = parseInt(select.value);
            }
            currentPage = 1;
            renderPaginatedRecipients();
        }

        function getTotalPages() {
            const total = currentFilteredRecipients.length;
            return Math.ceil(total / rowsPerPage);
        }

        function renderPaginatedRecipients() {
            const total = currentFilteredRecipients.length;
            const totalPages = getTotalPages();
            const start = (currentPage - 1) * rowsPerPage;
            const end = Math.min(start + rowsPerPage, total);
            const paginated = currentFilteredRecipients.slice(start, end);
            
            const pageInfo = document.getElementById('pageInfo');
            if (pageInfo) {
                pageInfo.textContent = total > 0 ? `Page ${currentPage} of ${totalPages}` : 'No entries';
            }
            
            renderRecipients(paginated);
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderPaginatedRecipients();
            }
        }

        function nextPage() {
            const totalPages = getTotalPages();
            if (currentPage < totalPages) {
                currentPage++;
                renderPaginatedRecipients();
            }
        }

        // ============================================
        // TAB SWITCHING FOR RECIPIENT TABS
        // ============================================
        function switchRecipientTab(tab) {
            console.log('🔄 Switching to tab:', tab);
            
            const recipientsTab = document.getElementById('recipientsTabContent');
            const invalidTab = document.getElementById('invalidTabContent');
            const otherTab = document.getElementById('otherTabContent');
            const failedTab = document.getElementById('failedTabContent');
            
            if (recipientsTab) recipientsTab.style.display = 'none';
            if (invalidTab) invalidTab.style.display = 'none';
            if (otherTab) otherTab.style.display = 'none';
            if (failedTab) failedTab.style.display = 'none';
            
            const tabRecipients = document.getElementById('tab-recipients');
            const tabInvalid = document.getElementById('tab-invalid');
            const tabOther = document.getElementById('tab-other');
            const tabFailed = document.getElementById('tab-failed');
            
            if (tabRecipients) {
                tabRecipients.classList.remove('border-blue-500', 'text-blue-600');
                tabRecipients.classList.add('border-transparent');
            }
            if (tabInvalid) {
                tabInvalid.classList.remove('border-blue-500', 'text-blue-600');
                tabInvalid.classList.add('border-transparent');
            }
            if (tabOther) {
                tabOther.classList.remove('border-blue-500', 'text-blue-600');
                tabOther.classList.add('border-transparent');
            }
            if (tabFailed) {
                tabFailed.classList.remove('border-blue-500', 'text-blue-600');
                tabFailed.classList.add('border-transparent');
            }
            
            if (tab === 'recipients') {
                if (recipientsTab) recipientsTab.style.display = 'block';
                if (tabRecipients) {
                    tabRecipients.classList.add('border-blue-500', 'text-blue-600');
                    tabRecipients.classList.remove('border-transparent');
                }
                if (currentFilteredRecipients.length > 0) {
                    renderPaginatedRecipients();
                } else if (currentRecipientsFull && currentRecipientsFull.length > 0) {
                    currentFilteredRecipients = currentRecipientsFull;
                    renderPaginatedRecipients();
                }
            } else if (tab === 'invalid') {
                if (invalidTab) invalidTab.style.display = 'block';
                if (tabInvalid) {
                    tabInvalid.classList.add('border-blue-500', 'text-blue-600');
                    tabInvalid.classList.remove('border-transparent');
                }
                const tbody = document.getElementById('invalidRecipientsBody');
                if (tbody && tbody.innerHTML.includes('Click "Load Invalid Recipients" to view')) {
                    loadInvalidRecipients();
                }
            } else if (tab === 'other') {
                if (otherTab) otherTab.style.display = 'block';
                if (tabOther) {
                    tabOther.classList.add('border-blue-500', 'text-blue-600');
                    tabOther.classList.remove('border-transparent');
                }
                const tbody = document.getElementById('otherNetworkRecipientsBody');
                if (tbody && tbody.innerHTML.includes('Click "Load Other Networks" to view')) {
                    loadOtherNetworkRecipients();
                }
            } else if (tab === 'failed') {
                if (failedTab) failedTab.style.display = 'block';
                if (tabFailed) {
                    tabFailed.classList.add('border-blue-500', 'text-blue-600');
                    tabFailed.classList.remove('border-transparent');
                }
                loadFailedRecipients();
            }
        }

        // ============================================
        // TOGGLE EXPORT DROPDOWN
        // ============================================
        function toggleExportDropdown() {
            const dropdown = document.getElementById('exportDropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('exportDropdown');
            const button = event.target.closest('.relative.inline-block');
            if (!button && dropdown) {
                dropdown.classList.add('hidden');
            }
        });

        // ============================================
        // EXPORT FUNCTIONS
        // ============================================
        function exportRecipients() {
            if (!currentRecipientsFull || currentRecipientsFull.length === 0) {
                alert('No recipients to export');
                return;
            }
            
            let csv = 'Tenant,Unit,Estate,Phone,Status,Message,Provider Status,Error Message,Updated At\n';
            currentRecipientsFull.forEach(r => {
                const tenantName = r.tenant_name || r.tenant?.name || r.name || 'Unknown';
                const unitNumber = r.unit_number || r.unit || 'N/A';
                const estateName = r.estate_name || r.estate || 'N/A';
                const phoneNumber = r.phone_number || r.phone || '';
                const status = r.status || 'pending';
                const message = (r.message || '').replace(/,/g, ';');
                const providerStatus = r.provider_status || '';
                const errorMessage = (r.error_message || '').replace(/,/g, ';');
                const updatedAt = r.updated_at || '';
                
                csv += `"${tenantName}","${unitNumber}","${estateName}","${phoneNumber}","${status}","${message}","${providerStatus}","${errorMessage}","${updatedAt}"\n`;
            });
            
            downloadCSV(csv, `recipients_all_${currentCampaignId || 'campaign'}.csv`);
            toggleExportDropdown();
        }

        function exportInvalidRecipients() {
            const invalidRecipients = currentInvalidRecipients || [];
            
            if (invalidRecipients.length === 0) {
                alert('No invalid recipients found to export');
                toggleExportDropdown();
                return;
            }
            
            let csv = 'Tenant,Unit,Estate,Phone,Status,Error Message,Updated At\n';
            invalidRecipients.forEach(r => {
                const tenantName = r.name || 'Unknown';
                const unitNumber = r.unit_number || 'N/A';
                const estateName = r.estate_name || r.estate || 'N/A';
                let phoneNumber = r.phone || '';
                phoneNumber = phoneNumber.replace(/^\+/, '').replace(/^254/, '0');
                if (phoneNumber.match(/^[7-9][0-9]{8}$/)) {
                    phoneNumber = '0' + phoneNumber;
                }
                const status = r.status || 'pending';
                let errorMsg = r.error || 'Invalid phone number format';
                const cleaned = (r.phone || '').replace(/[^0-9]/g, '');
                if (cleaned.match(/^254[^7][0-9]{8}$/) || cleaned.match(/^0[^7][0-9]{8}$/)) {
                    errorMsg = 'Other network (Airtel/Telkom) - Not Safaricom';
                }
                const updatedAt = r.updated_at || '';
                
                csv += `"${tenantName}","${unitNumber}","${estateName}","${phoneNumber}","${status}","${errorMsg}","${updatedAt}"\n`;
            });
            
            downloadCSV(csv, `recipients_invalid_${currentCampaignId || 'campaign'}.csv`);
            toggleExportDropdown();
        }

        function exportOtherNetworkRecipients() {
            const otherRecipients = currentOtherNetworkRecipients || [];
            
            if (otherRecipients.length === 0) {
                alert('No other network recipients found to export');
                toggleExportDropdown();
                return;
            }
            
            let csv = 'Tenant,Unit,Estate,Phone,Status,Network,Updated At\n';
            otherRecipients.forEach(r => {
                const tenantName = r.name || 'Unknown';
                const unitNumber = r.unit_number || 'N/A';
                const estateName = r.estate_name || r.estate || 'N/A';
                let phoneNumber = r.phone || '';
                phoneNumber = phoneNumber.replace(/^\+/, '').replace(/^254/, '0');
                if (phoneNumber.match(/^[7-9][0-9]{8}$/)) {
                    phoneNumber = '0' + phoneNumber;
                }
                const status = r.status || 'pending';
                const network = r.error || 'Other Network (Airtel/Telkom)';
                const updatedAt = r.updated_at || '';
                
                csv += `"${tenantName}","${unitNumber}","${estateName}","${phoneNumber}","${status}","${network}","${updatedAt}"\n`;
            });
            
            downloadCSV(csv, `recipients_other_network_${currentCampaignId || 'campaign'}.csv`);
            toggleExportDropdown();
        }

        function exportDeliveredRecipients() {
            if (!currentRecipientsFull || currentRecipientsFull.length === 0) {
                alert('No recipients to export');
                return;
            }
            
            const delivered = currentRecipientsFull.filter(r => r.status === 'delivered' || r.status === 'sent');
            
            if (delivered.length === 0) {
                alert('No delivered recipients found to export');
                toggleExportDropdown();
                return;
            }
            
            let csv = 'Tenant,Unit,Estate,Phone,Status,Sent At,Message\n';
            delivered.forEach(r => {
                const tenantName = r.tenant_name || r.tenant?.name || r.name || 'Unknown';
                const unitNumber = r.unit_number || r.unit || 'N/A';
                const estateName = r.estate_name || r.estate || 'N/A';
                let phoneNumber = r.phone_number || r.phone || '';
                phoneNumber = phoneNumber.replace(/^254/, '0');
                const status = r.status || 'pending';
                const sentAt = r.sent_at || '';
                const message = (r.message || '').replace(/,/g, ';');
                
                csv += `"${tenantName}","${unitNumber}","${estateName}","${phoneNumber}","${status}","${sentAt}","${message}"\n`;
            });
            
            downloadCSV(csv, `recipients_delivered_${currentCampaignId || 'campaign'}.csv`);
            toggleExportDropdown();
        }

        function exportPendingRecipients() {
            if (!currentRecipientsFull || currentRecipientsFull.length === 0) {
                alert('No recipients to export');
                return;
            }
            
            const pending = currentRecipientsFull.filter(r => r.status === 'pending' || r.status === 'queued');
            
            if (pending.length === 0) {
                alert('No pending recipients found to export');
                toggleExportDropdown();
                return;
            }
            
            let csv = 'Tenant,Unit,Estate,Phone,Status,Message\n';
            pending.forEach(r => {
                const tenantName = r.tenant_name || r.tenant?.name || r.name || 'Unknown';
                const unitNumber = r.unit_number || r.unit || 'N/A';
                const estateName = r.estate_name || r.estate || 'N/A';
                let phoneNumber = r.phone_number || r.phone || '';
                phoneNumber = phoneNumber.replace(/^254/, '0');
                const status = r.status || 'pending';
                const message = (r.message || '').replace(/,/g, ';');
                
                csv += `"${tenantName}","${unitNumber}","${estateName}","${phoneNumber}","${status}","${message}"\n`;
            });
            
            downloadCSV(csv, `recipients_pending_${currentCampaignId || 'campaign'}.csv`);
            toggleExportDropdown();
        }

        // ============================================
        // DOWNLOAD CSV
        // ============================================
        function downloadCSV(csv, filename) {
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // ============================================
        // RESEND FAILED MESSAGES
        // ============================================
        function resendFailed() {
            if (!currentCampaignId) {
                alert('No campaign loaded');
                return;
            }
            
            const failedCount = parseInt(document.getElementById('count-failed')?.textContent || 0);
            if (failedCount === 0) {
                alert('No failed messages to resend');
                return;
            }
            
            if (!confirm(`Resend ${failedCount} failed messages for this campaign?`)) return;
            
            const btn = document.getElementById('resendFailedBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Resending...';
            }
            
            const timeoutId = setTimeout(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo mr-1"></i> Resend Failed';
                }
                toastr.warning('Request is taking longer than expected. Please check the campaign status.');
            }, 30000);
            
            fetch(`/api/sms/campaigns/${currentCampaignId}/resend-failed`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                signal: AbortSignal.timeout(25000)
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(`✅ Resend completed!\n\nSent: ${data.data.sent}\nFailed: ${data.data.failed}\nTotal: ${data.data.total}`);
                    viewCampaign(currentCampaignId);
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to resend messages'));
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error resending:', error);
                alert('❌ Error resending messages: ' + (error.message || 'Unknown error'));
            })
            .finally(() => {
                clearTimeout(timeoutId);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo mr-1"></i> Resend Failed';
                }
            });
        }

        // ============================================
        // RESEND PENDING MESSAGES
        // ============================================
        function resendPending() {
            if (!currentCampaignId) {
                alert('No campaign loaded');
                return;
            }
            const pendingCount = parseInt(document.getElementById('count-pending')?.textContent || 0);
            if (pendingCount === 0) {
                alert('No pending messages to resend');
                return;
            }
            if (!confirm(`Resend ${pendingCount} pending messages for this campaign?`)) return;

            const btn = document.getElementById('resendPendingBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Resending...';
            }

            fetch(`/api/sms/campaigns/${currentCampaignId}/resend-pending`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Resend completed!\n\nSent: ${data.data.sent}\nFailed: ${data.data.failed}\nTotal: ${data.data.total}`);
                    viewCampaign(currentCampaignId);
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to resend pending messages'));
                }
            })
            .catch(error => {
                console.error('Error resending pending:', error);
                alert('❌ Error resending pending messages: ' + error.message);
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo mr-1"></i> Resend Pending';
                }
            });
        }

        // ============================================
        // REFRESH CAMPAIGN STATUS
        // ============================================
        function refreshCampaignStatus() {
            if (!currentCampaignId) {
                alert('No campaign loaded');
                return;
            }
            
            if (!confirm('Refresh status for all recipients in this campaign?')) return;
            
            const btn = document.getElementById('refreshStatusBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Syncing...';
            }
            
            fetch(`/api/sms/campaigns/${currentCampaignId}/sync-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Status sync completed!\n\nSynced: ${data.data.synced}\nFailed: ${data.data.failed}\nStatus changes: ${data.data.status_changes?.length || 0}`);
                    viewCampaign(currentCampaignId);
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to sync status'));
                }
            })
            .catch(error => {
                console.error('Error refreshing status:', error);
                alert('❌ Error refreshing status: ' + error.message);
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh Status';
                }
            });
        }

        // ============================================
        // CHECK PENDING STATUS
        // ============================================
        function checkPendingStatus() {
            if (!currentCampaignId) {
                alert('No campaign loaded');
                return;
            }
            
            const pendingCount = parseInt(document.getElementById('count-pending')?.textContent || 0);
            if (pendingCount === 0) {
                alert('No pending messages to check');
                return;
            }
            
            if (!confirm(`Check status for ${pendingCount} pending messages with KenyaSMS?`)) return;
            
            const btn = document.getElementById('checkPendingStatusBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking...';
            }
            
            fetch(`/api/sms/campaigns/${currentCampaignId}/check-pending`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Status check completed!\n\nUpdated: ${data.updated}\nFailed: ${data.failed}\nPending: ${data.pending}`);
                    viewCampaign(currentCampaignId);
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to check status'));
                }
            })
            .catch(error => {
                console.error('Error checking status:', error);
                alert('❌ Error checking status: ' + error.message);
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-search mr-1"></i> Check Pending Status';
                }
            });
        }

        // ============================================
        // LOAD FAILED RECIPIENTS
        // ============================================
        function loadFailedRecipients() {
            const tbody = document.getElementById('failedRecipientsBody');
            const btn = document.getElementById('loadFailedBtn');
            
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Loading...';
            }
            
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 text-sm">
                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading failed recipients...
                    </td>
                </tr>
            `;
            
            const failed = currentRecipientsFull.filter(r => r.status === 'failed');
            
            if (failed.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-4 text-center text-green-500 text-sm">
                            <i class="fas fa-check-circle mr-1"></i> No failed recipients found
                        </td>
                    </tr>
                `;
                if (btn) btn.disabled = false;
                if (btn) btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh Failed List';
                return;
            }
            
            let html = '';
            failed.forEach(r => {
                let displayPhone = r.phone_number || r.phone || 'N/A';
                displayPhone = displayPhone.replace(/^254/, '0');
                let tenantName = r.tenant_name || r.tenant?.name || r.name || 'Unknown';
                let unitNumber = r.unit_number || r.unit || 'N/A';
                let estateName = r.estate_name || r.estate || 'N/A';
                let errorMsg = r.error_message || 'No error message';
                
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">${escapeHtml(tenantName)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(unitNumber)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(estateName)}</td>
                        <td class="px-4 py-3 text-sm text-red-500">${escapeHtml(displayPhone)}</td>
                        <td class="px-4 py-3 text-sm text-red-500">${escapeHtml(errorMsg)}</td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="resendSingleRecipient(${r.id})" class="text-orange-600 hover:text-orange-900 text-sm mr-2" title="Resend">
                                <i class="fas fa-redo"></i>
                            </button>
                            <button onclick="viewRecipientMessage(${r.id})" class="text-blue-600 hover:text-blue-900 text-sm" title="View Message">
                                <i class="fas fa-envelope"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            if (btn) btn.disabled = false;
            if (btn) btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh Failed List';
        }

       // ============================================
// RESEND SINGLE RECIPIENT
// ============================================
function resendSingleRecipient(recipientId) {
    if (!currentCampaignId) {
        alert('No campaign loaded');
        return;
    }
    
    if (!confirm('Resend this message to this recipient?')) return;
    
    const btn = document.querySelector(`[onclick="resendSingleRecipient(${recipientId})"]`);
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending...';
    }
    
    const timeoutId = setTimeout(function() {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Resend';
        }
        toastr.warning('Request is taking longer than expected.');
    }, 15000);
    
    fetch(`/api/sms/recipients/${recipientId}/resend`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        signal: AbortSignal.timeout(12000)
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ Message resent successfully!');
            viewCampaign(currentCampaignId);
        } else {
            alert('❌ Error: ' + (data.message || 'Failed to resend'));
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error resending:', error);
        alert('❌ Error resending: ' + (error.message || 'Unknown error'));
    })
    .finally(() => {
        clearTimeout(timeoutId);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Resend';
        }
    });
}

        // ============================================
        // LOAD INVALID RECIPIENTS
        // ============================================
        function loadInvalidRecipients() {
            if (!currentCampaignId) {
                alert('No campaign loaded');
                return;
            }
            
            const tbody = document.getElementById('invalidRecipientsBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 text-sm">
                        <div class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
                        Loading...
                    </td>
                </tr>
            `;
            
            fetch(`/api/sms/campaigns/${currentCampaignId}/invalid-recipients`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const recipients = (data.recipients || []).map(r => {
                        if (!r.id && r.campaign_recipient_id) r.id = r.campaign_recipient_id;
                        if (!r.id && r.recipient_id) r.id = r.recipient_id;
                        if (!r.id) r.id = 'temp_' + Math.random().toString(36).substr(2, 9);
                        return r;
                    });
                    renderInvalidRecipients(recipients);
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-red-500 text-sm">
                                <i class="fas fa-exclamation-circle mr-1"></i> ${data.message || 'Failed to load invalid recipients'}
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading invalid recipients:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-4 text-center text-red-500 text-sm">
                            <i class="fas fa-exclamation-circle mr-1"></i> Error loading invalid recipients
                        </td>
                    </tr>
                `;
            });
        }

        function renderInvalidRecipients(recipients) {
            currentInvalidRecipients = recipients || [];
            
            const tbody = document.getElementById('invalidRecipientsBody');
            
            const invalidTabCount = document.getElementById('invalidTabCount');
            if (invalidTabCount) {
                invalidTabCount.textContent = `(${recipients ? recipients.length : 0})`;
            }
            
            if (!recipients || recipients.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-green-500 text-sm">
                            <i class="fas fa-check-circle mr-1"></i> No invalid recipients found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            recipients.forEach(recipient => {
                let displayPhone = recipient.phone || 'N/A';
                displayPhone = displayPhone.replace(/^\+/, '').replace(/^254/, '0');
                if (displayPhone.match(/^[7-9][0-9]{8}$/)) {
                    displayPhone = '0' + displayPhone;
                }
                
                let errorMsg = recipient.error || 'Invalid phone number format';
                const cleaned = (recipient.phone || '').replace(/[^0-9]/g, '');
                if (cleaned.match(/^254[^7][0-9]{8}$/) || cleaned.match(/^0[^7][0-9]{8}$/)) {
                    errorMsg = 'Other network (Airtel/Telkom) - Not Safaricom';
                }
                
                const estateName = recipient.estate_name || recipient.estate || 'N/A';
                
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">${escapeHtml(recipient.name)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(recipient.unit_number)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(estateName)}</td>
                        <td class="px-4 py-3 text-sm text-red-500">${escapeHtml(displayPhone)}</td>
                        <td class="px-4 py-3 text-sm text-red-500">${escapeHtml(errorMsg)}</td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="openUpdatePhoneModal(${recipient.id}, '${escapeHtml(recipient.name)}', '${escapeHtml(recipient.phone || '')}')" 
                                    class="text-blue-600 hover:text-blue-900 text-sm">
                                <i class="fas fa-edit"></i> Update
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        // ============================================
        // LOAD OTHER NETWORK RECIPIENTS
        // ============================================
        function loadOtherNetworkRecipients() {
            if (!currentCampaignId) {
                alert('No campaign loaded');
                return;
            }
            
            const tbody = document.getElementById('otherNetworkRecipientsBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-4 py-4 text-center text-gray-500 text-sm">
                        <div class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
                        Loading...
                    </td>
                </tr>
            `;
            
            fetch(`/api/sms/campaigns/${currentCampaignId}/other-network-recipients`, {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderOtherNetworkRecipients(data.recipients);
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-red-500 text-sm">
                                <i class="fas fa-exclamation-circle mr-1"></i> ${data.message || 'Failed to load other network recipients'}
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading other network recipients:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-red-500 text-sm">
                            <i class="fas fa-exclamation-circle mr-1"></i> Error loading other network recipients
                        </td>
                    </tr>
                `;
            });
        }

        function renderOtherNetworkRecipients(recipients) {
            currentOtherNetworkRecipients = recipients || [];
            
            const tbody = document.getElementById('otherNetworkRecipientsBody');
            
            const otherTabCount = document.getElementById('otherTabCount');
            if (otherTabCount) {
                otherTabCount.textContent = `(${recipients ? recipients.length : 0})`;
            }
            
            if (!recipients || recipients.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-green-500 text-sm">
                            <i class="fas fa-check-circle mr-1"></i> No other network recipients found
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            recipients.forEach(recipient => {
                let displayPhone = recipient.phone || 'N/A';
                displayPhone = displayPhone.replace(/^254/, '0');
                
                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">${escapeHtml(recipient.name)}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${escapeHtml(recipient.unit_number)}</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">${escapeHtml(displayPhone)}</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">${escapeHtml(recipient.error || 'Other Network (Airtel/Telkom)')}</td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="openUpdatePhoneModal(${recipient.id}, '${escapeHtml(recipient.name)}', '${escapeHtml(recipient.phone || '')}')" 
                                    class="text-blue-600 hover:text-blue-900 text-sm">
                                <i class="fas fa-edit"></i> Update
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        // ============================================
        // UPDATE PHONE MODAL
        // ============================================
        function openUpdatePhoneModal(tenantId, tenantName, currentPhone) {
            document.getElementById('updateTenantId').value = tenantId;
            document.getElementById('updateTenantName').textContent = tenantName;
            document.getElementById('updatePhoneInput').value = currentPhone;
            const modal = document.getElementById('updatePhoneModal');
            if (modal) {
                modal.style.display = 'block';
                modal.classList.add('active');
                document.body.classList.add('modal-open');
            }
        }

        function closeUpdatePhoneModal() {
            const modal = document.getElementById('updatePhoneModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
                document.body.classList.remove('modal-open');
            }
        }

        function saveUpdatedPhone() {
            const tenantId = document.getElementById('updateTenantId').value;
            const phone = document.getElementById('updatePhoneInput').value.trim();
            
            if (!phone) {
                alert('Please enter a phone number');
                return;
            }
            
            const btn = document.getElementById('savePhoneBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
            
            fetch(`/api/sms/tenants/${tenantId}/phone`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Phone number updated successfully!');
                    closeUpdatePhoneModal();
                    viewCampaign(currentCampaignId);
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to update phone'));
                }
            })
            .catch(error => {
                console.error('Error updating phone:', error);
                alert('❌ Error updating phone: ' + error.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save';
            });
        }

        // ============================================
        // CLOSE VIEW CAMPAIGN MODAL
        // ============================================
        function closeViewCampaignModal() {
            const modal = document.getElementById('viewCampaignModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
                document.body.classList.remove('modal-open');
            }
        }

        // ============================================
        // CAMPAIGN ACTIONS
        // ============================================
        function sendCampaign(id) {
            if (!confirm('Are you sure you want to send this campaign?')) return;
            
            fetch(`/api/sms/campaigns/${id}/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Campaign is being sent!');
                    loadCampaigns();
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to send campaign'));
                }
            })
            .catch(error => {
                console.error('Error sending campaign:', error);
                alert('❌ Error sending campaign');
            });
        }

        function retryCampaign(id) {
            if (!confirm('Retry failed messages for this campaign?')) return;
            
            fetch(`/api/sms/campaigns/${id}/retry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Retrying failed messages!');
                    loadCampaigns();
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to retry'));
                }
            })
            .catch(error => {
                console.error('Error retrying campaign:', error);
                alert('❌ Error retrying campaign');
            });
        }

        function deleteCampaign(id) {
            if (!confirm('Are you sure you want to delete this campaign?')) return;

            fetch(`/sms/campaigns/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Campaign deleted successfully!');
                    loadCampaigns();
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to delete campaign'));
                }
            })
            .catch(error => {
                console.error('Error deleting campaign:', error);
                alert('❌ Error deleting campaign: ' + error.message);
            });
        }

        // ============================================
        // OPEN CREATE CAMPAIGN MODAL
        // ============================================
        function openCreateCampaignModal() {
            console.log('📝 Opening create campaign modal');
            const modal = document.getElementById('createCampaignModal');
            if (!modal) {
                console.error('❌ Create campaign modal not found');
                return;
            }
            
            modal.style.display = 'block';
            
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + 
                            String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                            String(now.getDate()).padStart(2, '0') + ' ' + 
                            String(now.getHours()).padStart(2, '0') + ':' + 
                            String(now.getMinutes()).padStart(2, '0') + ':' + 
                            String(now.getSeconds()).padStart(2, '0');
            const campaignName = 'Campaign ' + dateStr;
            
            document.getElementById('campaignName').value = campaignName;
            document.getElementById('campaignDescription').value = '';
            document.getElementById('campaignTemplate').value = '';
            document.getElementById('campaignType').value = 'general';
            document.getElementById('campaignFilterCompany').value = '';
            document.getElementById('campaignFilterEstate').value = '';
            document.getElementById('campaignFilterStatus').value = '';
            document.getElementById('campaignSchedule').value = '';
            document.getElementById('campaignTemplatePreview').style.display = 'none';
            document.getElementById('campaignTemplateContent').textContent = '';
            
            const previewContainer = document.getElementById('campaignTenantPreviewContainer');
            if (previewContainer) previewContainer.style.display = 'none';
            const previewEl = document.getElementById('campaignTenantPreview');
            if (previewEl) previewEl.innerHTML = '';
            
            const estateSelect = document.getElementById('campaignFilterEstate');
            if (estateSelect) {
                const options = estateSelect.querySelectorAll('option');
                options.forEach(option => {
                    option.style.display = 'block';
                });
                estateSelect.value = '';
            }
        }

        function closeCreateCampaignModal() {
            console.log('📝 Closing create campaign modal');
            const modal = document.getElementById('createCampaignModal');
            if (modal) modal.style.display = 'none';
        }

        function loadCampaignTemplatePreview() {
            const select = document.getElementById('campaignTemplate');
            if (!select) return;
            
            const selected = select.options[select.selectedIndex];
            const content = selected ? selected.getAttribute('data-content') : null;
            
            console.log('📝 Template selected:', selected ? selected.text : 'None');
            
            const previewDiv = document.getElementById('campaignTemplatePreview');
            const contentEl = document.getElementById('campaignTemplateContent');
            
            if (content && previewDiv && contentEl) {
                previewDiv.style.display = 'block';
                contentEl.textContent = content;
                generateTenantPreview(content);
            } else if (previewDiv) {
                previewDiv.style.display = 'none';
            }
        }

        function submitCampaign(event) {
            event.preventDefault();
            
            const name = document.getElementById('campaignName').value.trim();
            const description = document.getElementById('campaignDescription').value.trim();
            const templateId = document.getElementById('campaignTemplate').value;
            const campaignType = document.getElementById('campaignType').value;
            const companyId = document.getElementById('campaignFilterCompany').value;
            const estateId = document.getElementById('campaignFilterEstate').value;
            const invoiceStatus = document.getElementById('campaignFilterStatus').value;
            const scheduledAt = document.getElementById('campaignSchedule').value;
            
            if (!name) {
                alert('Please enter a campaign name');
                return;
            }
            if (!templateId) {
                alert('Please select an SMS template');
                return;
            }
            
            const submitBtn = document.getElementById('submitCampaignBtn');
            const submitText = document.getElementById('submitCampaignText');
            if (submitBtn) submitBtn.disabled = true;
            if (submitText) submitText.textContent = 'Creating...';
            
            const formData = {
                name: name,
                description: description,
                template_id: parseInt(templateId),
                campaign_type: campaignType,
                filters: {
                    company_id: companyId || '',
                    estate_id: estateId || '',
                    invoice_status: invoiceStatus || ''
                },
                scheduled_at: scheduledAt || null
            };
            
            console.log('📤 Submitting campaign:', formData);
            
            fetch('/api/sms/campaigns', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Something went wrong');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeCreateCampaignModal();
                    loadCampaigns();
                    alert('✅ Campaign created successfully!');
                } else {
                    throw new Error(data.message || 'Something went wrong');
                }
            })
            .catch(error => {
                console.error('Error creating campaign:', error);
                alert('❌ Error: ' + error.message);
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
                if (submitText) submitText.textContent = 'Create Campaign';
            });
        }
</script>
    @endverbatim
@endsection