@extends('layouts.app')

@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

@section('title', 'SMS Broadcast')

@section('content')
<div class="container mx-auto px-4 py-6">
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
    <div class="flex flex-wrap border-b border-gray-200 dark:border-gray-700 mb-6">
        <button onclick="activeTab = 'tenants'; renderTab()" id="tab-tenants" class="py-2 px-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400">Send to Tenants</button>
        <button onclick="activeTab = 'custom'; renderTab()" id="tab-custom" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">Send Custom SMS</button>
        <button onclick="activeTab = 'history'; renderTab()" id="tab-history" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400">SMS History</button>
        <a href="{{ route('sms.campaigns.index') }}" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-500">Campaigns</a>
        <a href="{{ route('sms.campaigns.history') }}" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-500">Campaign History</a>
    </div>

    <!-- Tab 1: Send to Tenants -->
    <div id="tenants-tab" style="display: block;">
        <!-- Main Card -->
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
                <textarea id="template" name="template" rows="4" class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="Hi {{name}}, please pay your {{month}} water bill by {{due_date}}. Paybill 7263733 Acc {{unit}} KES {{water_bill}}">Hi {{name}}, please pay your {{month}} water bill by {{due_date}}. Paybill 7263733 Acc {{unit}} KES {{water_bill}}</textarea>
                @endverbatim
                <div class="flex flex-wrap gap-2 mt-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Available variables: name, unit, water_bill, due_date, month, estate_name, prev_read, curr_read, water_consumption, payment_status, status</span>
                </div>
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
                    <div class="flex gap-2">
                        <button type="button" id="selectAllBtn" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">Select All</button>
                        <button type="button" id="selectNoneBtn" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">Clear All</button>
                    </div>
                    <button type="submit" form="bulkForm" id="sendSmsBtn" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Send SMS to Tenants
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
                            @foreach($tenants as $tenant)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150 tenant-row" 
                                data-estate-id="{{ $tenant['estate_id'] }}" 
                                data-name="{{ $tenant['name'] }}" 
                                data-name-lower="{{ strtolower($tenant['name']) }}" 
                                data-phone="{{ $tenant['phone'] }}" 
                                data-unit="{{ $tenant['unit_number'] }}"
                                data-unit-lower="{{ strtolower($tenant['unit_number']) }}"
                                data-estate="{{ $tenant['estate_name'] }}"
                                data-estate-lower="{{ strtolower($tenant['estate_name']) }}"
                                data-water-bill="{{ $tenant['water_bill'] }}"
                                data-prev-read="{{ $tenant['prev_read'] ?? 0 }}"
                                data-curr-read="{{ $tenant['curr_read'] ?? 0 }}"
                                data-month="{{ $tenant['reading_month'] }}"
                                data-due-date="{{ $tenant['due_date'] }}"
                                data-payment-status="{{ $tenant['payment_status'] ?? 'pending' }}">
                                <td class="px-4 py-3">
                                    <input type="checkbox" class="tenant-checkbox" 
                                        data-id="{{ $tenant['id'] }}"
                                        data-phone="{{ $tenant['phone'] }}"
                                        data-name="{{ $tenant['name'] }}"
                                        data-unit="{{ $tenant['unit_number'] }}"
                                        data-estate="{{ $tenant['estate_name'] }}"
                                        data-estate-id="{{ $tenant['estate_id'] }}"
                                        data-waterbill="{{ $tenant['water_bill'] }}"
                                        data-consumption="{{ $tenant['water_consumption'] }}"
                                        data-prev-read="{{ $tenant['prev_read'] ?? 0 }}"
                                        data-curr-read="{{ $tenant['curr_read'] ?? 0 }}"
                                        data-month="{{ $tenant['reading_month'] }}"
                                        data-due-date="{{ $tenant['due_date'] }}"
                                        data-payment-status="{{ $tenant['payment_status'] ?? 'pending' }}">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                            <span class="text-blue-600 font-medium text-sm">{{ substr($tenant['name'], 0, 1) }}</span>
                                        </div>
                                        <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tenant['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['phone'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['unit_number'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['estate_name'] }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-800">KES {{ number_format($tenant['water_bill'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['prev_read'] ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['curr_read'] ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant['due_date'] }}</td>
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
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

            <!-- Bulk Form - includes template field -->
            <form method="POST" action="{{ route('sms.send') }}" id="bulkForm">
                @csrf
                <input type="hidden" name="recipients" id="recipientsJson">
                <input type="hidden" name="message_type" id="messageTypeHidden" value="transactional">
                <input type="hidden" name="template" id="templateHidden">
            </form>
        </div>
    </div>

    <!-- Tab 2: Send Custom SMS -->
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

    <!-- Tab 3: SMS History -->
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
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $log->id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $log->recipient_phone }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ Str::limit($log->message, 60) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $log->message_type ?? 'transactional' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($log->status === 'sent') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($log->status === 'failed') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @endif">
                                        {{ $log->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No logs found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    let activeTab = 'tenants';
    let currentPage = 1;
    let rowsPerPage = 10;
    let sortColumn = '';
    let sortDirection = 'asc';
    let allRows = [];
    let allTenantsData = [];
    
    function renderTab() {
        document.getElementById('tenants-tab').style.display = 'none';
        document.getElementById('custom-tab').style.display = 'none';
        document.getElementById('history-tab').style.display = 'none';
        
        document.getElementById('tenants-tab').style.display = activeTab === 'tenants' ? 'block' : 'none';
        document.getElementById('custom-tab').style.display = activeTab === 'custom' ? 'block' : 'none';
        document.getElementById('history-tab').style.display = activeTab === 'history' ? 'block' : 'none';
        
        document.querySelectorAll('#tab-tenants, #tab-custom, #tab-history').forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent');
        });
        let activeBtn = document.getElementById(`tab-${activeTab}`);
        if (activeBtn) {
            activeBtn.classList.add('border-blue-500', 'text-blue-600');
            activeBtn.classList.remove('border-transparent');
        }
    }
    
    // Template selection
    document.getElementById('templateSelect')?.addEventListener('change', function() {
        let selected = this.options[this.selectedIndex];
        let content = selected.getAttribute('data-content');
        if (content) {
            document.getElementById('template').value = content;
            updatePreview();
        }
    });
    
    // Get all tenant data from the page
    function getAllTenantsData() {
        const rows = document.querySelectorAll('.tenant-row');
        allTenantsData = [];
        rows.forEach(row => {
            const checkbox = row.querySelector('.tenant-checkbox');
            if (checkbox) {
                allTenantsData.push({
                    id: checkbox.dataset.id,
                    phone: checkbox.dataset.phone,
                    name: checkbox.dataset.name,
                    unit: checkbox.dataset.unit,
                    estate: checkbox.dataset.estate,
                    estateId: checkbox.dataset.estateId,
                    waterbill: checkbox.dataset.waterbill,
                    consumption: checkbox.dataset.consumption,
                    prevRead: checkbox.dataset.prevRead,
                    currRead: checkbox.dataset.currRead,
                    month: checkbox.dataset.month,
                    dueDate: checkbox.dataset.dueDate,
                    paymentStatus: checkbox.dataset.paymentStatus,
                    row: row,
                    checkbox: checkbox
                });
            }
        });
        return allTenantsData;
    }
    
    // Toggle ALL checkboxes (including hidden ones)
    function toggleAllCheckboxes() {
        const masterCheckbox = document.getElementById('toggleAllCheckbox');
        const isChecked = masterCheckbox.checked;
        
        // Select ALL checkboxes on the page (including hidden ones)
        document.querySelectorAll('.tenant-checkbox').forEach(cb => {
            cb.checked = isChecked;
        });
        
        updateSelectedCount();
    }
    
    // Get all checkboxes that are currently visible
    function getVisibleCheckboxes() {
        return Array.from(document.querySelectorAll('.tenant-checkbox')).filter(cb => {
            const row = cb.closest('.tenant-row');
            return row && row.style.display !== 'none';
        });
    }
    
    // Get ALL selected checkboxes (including those not visible)
    function getAllSelectedCheckboxes() {
        return Array.from(document.querySelectorAll('.tenant-checkbox')).filter(cb => cb.checked);
    }
    
    // Initialize all rows
    function initRows() {
        allRows = Array.from(document.querySelectorAll('.tenant-row'));
        getAllTenantsData();
        applyFiltersAndRender();
        updateSelectedCount();
        
        // Update master checkbox state based on all checkboxes
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
        let selectedEstateId = document.getElementById('estateFilter').value;
        let searchTerm = document.getElementById('tenantSearch').value.toLowerCase();
        let paymentStatus = document.getElementById('paymentStatusFilter').value;
        let minBill = parseFloat(document.getElementById('minBillFilter').value) || 0;
        
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
            
            return matchesEstate && matchesSearch && matchesPayment && matchesBill;
        });
        
        // Apply sorting
        if (sortColumn) {
            visibleRows.sort((a, b) => {
                let aVal, bVal;
                switch(sortColumn) {
                    case 'name':
                        aVal = a.getAttribute('data-name') || '';
                        bVal = b.getAttribute('data-name') || '';
                        break;
                    case 'phone':
                        aVal = a.getAttribute('data-phone') || '';
                        bVal = b.getAttribute('data-phone') || '';
                        break;
                    case 'unit':
                        aVal = a.getAttribute('data-unit') || '';
                        bVal = b.getAttribute('data-unit') || '';
                        break;
                    case 'estate':
                        aVal = a.getAttribute('data-estate') || '';
                        bVal = b.getAttribute('data-estate') || '';
                        break;
                    case 'water_bill':
                        aVal = parseFloat(a.getAttribute('data-water-bill')) || 0;
                        bVal = parseFloat(b.getAttribute('data-water-bill')) || 0;
                        break;
                    default:
                        aVal = a.getAttribute('data-name') || '';
                        bVal = b.getAttribute('data-name') || '';
                }
                if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        }
        
        // Check if "All" is selected
        const entriesSelect = document.getElementById('entriesPerPage');
        const showAll = entriesSelect.value === 'all';
        
        let paginatedRows;
        if (showAll) {
            paginatedRows = visibleRows;
        } else {
            // Apply pagination
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            paginatedRows = visibleRows.slice(start, end);
        }
        
        // Hide all rows first
        allRows.forEach(row => row.style.display = 'none');
        // Show paginated rows
        paginatedRows.forEach(row => row.style.display = '');
        
        // Update pagination info
        const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
        document.getElementById('pageInfo').innerText = showAll ? 'Showing all' : `Page ${currentPage} of ${totalPages || 1}`;
        
        updateSelectedCount();
    }
    
    function entriesPerPageChange() {
        const entriesSelect = document.getElementById('entriesPerPage');
        if (entriesSelect.value === 'all') {
            rowsPerPage = 999999; // Show all
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
    
    // ✅ FIXED: updatePreview function - uses data from checkbox, not current date
    function updatePreview() {
        let template = document.getElementById('template')?.value || '';
        let allSelected = getAllSelectedCheckboxes();
        let selectedCount = allSelected.length;
        
        if (selectedCount > 0 && template.trim() !== '') {
            document.getElementById('previewSection').style.display = 'block';
            let previews = [];
            
            for (let i = 0; i < Math.min(3, selectedCount); i++) {
                let cb = allSelected[i];
                let phone = cb.getAttribute('data-phone');
                let name = cb.getAttribute('data-name');
                let unit = cb.getAttribute('data-unit');
                let water_bill = parseFloat(cb.getAttribute('data-waterbill')).toFixed(2);
                let consumption = cb.getAttribute('data-consumption');
                let estate_name = cb.getAttribute('data-estate');
                let prev_read = cb.getAttribute('data-prev-read');
                let curr_read = cb.getAttribute('data-curr-read');
                
                // ✅ FIX: Use data from checkbox, NOT new Date()
                let month = cb.getAttribute('data-month');
                let dueDate = cb.getAttribute('data-due-date');
                let paymentStatus = cb.getAttribute('data-payment-status') || 'pending';
                
                let message = template;
                message = message.replace(/\{\{name\}\}/g, name);
                message = message.replace(/\{\{unit\}\}/g, unit);
                message = message.replace(/\{\{unit_number\}\}/g, unit);
                message = message.replace(/\{\{water_bill\}\}/g, water_bill);
                message = message.replace(/\{\{water_consumption\}\}/g, consumption);
                message = message.replace(/\{\{due_date\}\}/g, dueDate);
                message = message.replace(/\{\{month\}\}/g, month);
                message = message.replace(/\{\{estate_name\}\}/g, estate_name);
                message = message.replace(/\{\{prev_read\}\}/g, prev_read);
                message = message.replace(/\{\{curr_read\}\}/g, curr_read);
                message = message.replace(/\{\{payment_status\}\}/g, paymentStatus);
                message = message.replace(/\{\{status\}\}/g, paymentStatus);
                
                previews.push({ phone: phone, message: message, name: name });
            }
            
            let html = '';
            previews.forEach(p => {
                html += '<div class="border-l-4 border-blue-300 pl-3 py-1">' +
                            '<p class="text-xs text-gray-500"><strong>To:</strong> ' + p.phone + ' (' + p.name + ')</p>' +
                            '<p class="text-sm text-gray-800"><strong>Message:</strong> ' + p.message + '</p>' +
                        '</div>';
            });
            document.getElementById('previewContainer').innerHTML = html;
        } else {
            document.getElementById('previewSection').style.display = 'none';
        }
    }
    
    function updateSelectedCount() {
        let allSelected = getAllSelectedCheckboxes();
        let selectedCount = allSelected.length;
        let totalVisible = getVisibleCheckboxes().length;
        
        document.getElementById('selectedCount').innerText = selectedCount;
        
        // Update master checkbox
        updateMasterCheckboxState();
        
        // Update send button
        const sendBtn = document.getElementById('sendSmsBtn');
        if (sendBtn) {
            sendBtn.disabled = selectedCount === 0;
        }
        
        updatePreview();
    }
    
    // Event listeners for checkboxes
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('tenant-checkbox')) {
            updateSelectedCount();
        }
    });
    
    // ✅ FIXED: Form submission with loading state
    document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
        let selected = [];
        let template = document.getElementById('template').value;
        
        if (!template.trim()) {
            alert('Please enter a message template.');
            e.preventDefault();
            return false;
        }
        
        // Get ALL selected checkboxes (not just visible ones)
        let allSelected = getAllSelectedCheckboxes();
        
        if (allSelected.length === 0) {
            alert('Please select at least one tenant.');
            e.preventDefault();
            return false;
        }
        
        // ✅ Show loading state on the button
        const submitBtn = document.getElementById('sendSmsBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Sending... Please wait';
        submitBtn.classList.add('opacity-75', 'cursor-wait');
        
        // ✅ Show a status message
        let statusDiv = document.getElementById('sendingStatus');
        if (!statusDiv) {
            statusDiv = document.createElement('div');
            statusDiv.id = 'sendingStatus';
            statusDiv.className = 'mb-4 rounded-lg bg-blue-100 p-4 text-blue-800 border border-blue-300';
            // Insert it above the filters section
            const filtersSection = document.querySelector('.p-6.border-b.border-gray-200.dark\\:border-gray-700');
            if (filtersSection) {
                filtersSection.parentNode.insertBefore(statusDiv, filtersSection);
            }
        }
        statusDiv.innerHTML = '⏳ Sending SMS messages to ' + allSelected.length + ' tenant(s)... Please wait.';
        statusDiv.style.display = 'block';
        
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
            
            // ✅ Use data from checkbox for month and due date
            let month = cb.getAttribute('data-month');
            let dueDate = cb.getAttribute('data-due-date');
            let paymentStatus = cb.getAttribute('data-payment-status') || 'pending';
            
            // Build the message with variables
            let message = template;
            message = message.replace(/\{\{name\}\}/g, name);
            message = message.replace(/\{\{unit\}\}/g, unit);
            message = message.replace(/\{\{unit_number\}\}/g, unit);
            message = message.replace(/\{\{water_bill\}\}/g, water_bill);
            message = message.replace(/\{\{water_consumption\}\}/g, consumption);
            message = message.replace(/\{\{due_date\}\}/g, dueDate);
            message = message.replace(/\{\{month\}\}/g, month);
            message = message.replace(/\{\{estate_name\}\}/g, estate_name);
            message = message.replace(/\{\{prev_read\}\}/g, prev_read);
            message = message.replace(/\{\{curr_read\}\}/g, curr_read);
            message = message.replace(/\{\{payment_status\}\}/g, paymentStatus);
            message = message.replace(/\{\{status\}\}/g, paymentStatus);
            
            selected.push({
                phone: phone,
                message: message,
                id: cb.getAttribute('data-id'),
                variables: {
                    name: name,
                    unit: unit,
                    unit_number: unit,
                    water_bill: water_bill,
                    water_consumption: consumption,
                    due_date: dueDate,
                    month: month,
                    estate_name: estate_name,
                    prev_read: prev_read,
                    curr_read: curr_read,
                    payment_status: paymentStatus,
                    status: paymentStatus
                }
            });
        });
        
        // Set the recipients JSON
        document.getElementById('recipientsJson').value = JSON.stringify(selected);
        document.getElementById('templateHidden').value = template;
        
        // ✅ Store reference to button for response handling
        window.submitBtn = submitBtn;
        window.originalText = originalText;
        window.statusDiv = statusDiv;
        
        return true;
    });
    
    // ✅ Reset button state after page load (for success/error messages)
    document.addEventListener('DOMContentLoaded', function() {
        // Check if there's a flash message
        const successMessage = document.querySelector('.bg-green-100');
        const errorMessage = document.querySelector('.bg-red-100');
        
        if (successMessage || errorMessage) {
            // Reset the button state
            const submitBtn = document.getElementById('sendSmsBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Send SMS to Tenants';
                submitBtn.classList.remove('opacity-75', 'cursor-wait');
            }
            
            // Hide the status message
            const statusDiv = document.getElementById('sendingStatus');
            if (statusDiv) {
                statusDiv.style.display = 'none';
            }
        }
        
        initRows();
        renderTab();
        
        // Recalculate when entries per page changes
        document.getElementById('entriesPerPage')?.addEventListener('change', entriesPerPageChange);
    });
    
    // Make functions global
    window.sortTable = sortTable;
    window.prevPage = prevPage;
    window.nextPage = nextPage;
    window.applyFiltersAndRender = applyFiltersAndRender;
    window.toggleAllCheckboxes = toggleAllCheckboxes;
    window.entriesPerPageChange = entriesPerPageChange;
    window.getAllSelectedCheckboxes = getAllSelectedCheckboxes;
    window.updateSelectedCount = updateSelectedCount;
    window.updateMasterCheckboxState = updateMasterCheckboxState;
</script>

<style>
    [x-cloak] { display: none !important; }
    .sort-icon { opacity: 0.5; font-size: 10px; }
    th:hover .sort-icon { opacity: 1; }
</style>
@endsection