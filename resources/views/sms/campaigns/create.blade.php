{{-- resources/views/sms/campaigns/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Create Campaign')

@section('content')
<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>

<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-black dark:text-white">Create Campaign</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Create a new water bill SMS campaign</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('sms.campaigns.index') }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
            <a href="{{ route('sms.campaigns.index') }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                Cancel
            </a>
        </div>
    </div>

    <!-- ====== ALERT MESSAGES ====== -->
    @if(session('success'))
    <div class="mb-4 rounded-sm border-l-4 border-success bg-success/10 p-4 text-success">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <strong>Success!</strong> {{ session('success') }}
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 rounded-sm border-l-4 border-danger bg-danger/10 p-4 text-danger">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <strong>Error!</strong> {{ session('error') }}
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 rounded-sm border-l-4 border-danger bg-danger/10 p-4 text-danger">
        <strong>Validation Errors:</strong>
        <ul class="list-disc pl-4 text-sm mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    <!-- ====== END ALERT MESSAGES ====== -->

    <!-- Form -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <form action="{{ route('sms.campaigns.store') }}" method="POST" class="p-6" id="campaignForm">
            @csrf

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column -->
                <div>
                    <!-- Campaign Name -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Campaign Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark"
                               placeholder="e.g., July 2026 Water Bill Campaign">
                    </div>

                    <!-- Estate -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Estate <span class="text-danger">*</span></label>
                        <select name="estate_id" id="estate_id" required class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark" onchange="updatePreview()">
                            <option value="">Select Estate</option>
                            @foreach($estates as $estate)
                                <option value="{{ $estate->id }}" {{ old('estate_id') == $estate->id ? 'selected' : '' }}>
                                    {{ $estate->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Billing Month -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Billing Month <span class="text-danger">*</span></label>
                        <input type="text" name="billing_month" id="billing_month" value="{{ old('billing_month') }}" required
                               class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark"
                               placeholder="e.g., July 2026" onchange="updatePreview()">
                    </div>

                    <!-- Sender ID -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Sender ID</label>
                        <input type="text" name="sender_id" value="{{ old('sender_id', 'SHARETENT') }}"
                               class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark"
                               placeholder="SHARETENT" maxlength="11">
                        <p class="mt-1 text-xs text-gray-500">Maximum 11 characters</p>
                    </div>

                    <!-- Message Type -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Message Type</label>
                        <select name="message_type" class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
                            <option value="transactional" {{ old('message_type') == 'transactional' ? 'selected' : '' }}>Transactional</option>
                            <option value="promotional" {{ old('message_type') == 'promotional' ? 'selected' : '' }}>Promotional</option>
                        </select>
                    </div>

                    <!-- Schedule -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Schedule (Optional)</label>
                        <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                               class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to send immediately</p>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Template Selector -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Load Template</label>
                        <select id="templateSelect" class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark" onchange="loadTemplate()">
                            <option value="">-- Select Template --</option>
                            @foreach($templates ?? [] as $template)
                                <option value="{{ $template->id }}" data-content="{{ $template->content }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select a template to auto-fill the message</p>
                    </div>

                    <!-- Message -->
                    <div class="mb-4">
                        <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Message <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="6" required
                                  class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark"
                                  placeholder="Enter your message. Use placeholders like estate_name, unit, water_bill etc." oninput="updatePreview()">{{ old('message') }}</textarea>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span>Available placeholders:</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('estate_name')">@{{ estate_name }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('month')">@{{ month }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('unit')">@{{ unit }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('water_bill')">@{{ water_bill }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('water_consumption')">@{{ water_consumption }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('due_date')">@{{ due_date }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('payment_status')">@{{ payment_status }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('prev_read')">@{{ prev_read }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('curr_read')">@{{ curr_read }}</span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800 cursor-pointer hover:bg-primary/20" onclick="insertPlaceholder('tenant_name')">@{{ tenant_name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div id="previewSection" class="mb-4 rounded border border-stroke p-4 dark:border-strokedark" style="display: none;">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-black dark:text-white">📱 Message Preview</h4>
                    <span class="text-xs text-gray-500">Showing preview for first tenant</span>
                </div>
                <div id="previewContent" class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                        <span class="text-gray-400">Select an estate and enter a message to see preview...</span>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs">
                    <span class="text-gray-500">Characters: <span id="charCount" class="font-medium text-black dark:text-white">0</span></span>
                    <span class="text-gray-500">Segments: <span id="segmentCount" class="font-medium text-black dark:text-white">0</span></span>
                    <span class="text-gray-500">Estimated Cost: <span id="estimatedCost" class="font-medium text-black dark:text-white">KES 0.00</span></span>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-4 rounded border border-stroke p-4 dark:border-strokedark">
                <h4 class="mb-3 text-sm font-semibold text-black dark:text-white">Filters</h4>
                
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm text-gray-500">Payment Status</label>
                        <select name="filters[payment_status]" class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
                            <option value="">All</option>
                            <option value="unpaid" {{ old('filters.payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="paid" {{ old('filters.payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="pending" {{ old('filters.payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-gray-500">Min Bill Amount</label>
                        <input type="number" name="filters[min_bill_amount]" value="{{ old('filters.min_bill_amount') }}"
                               class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark"
                               placeholder="0" step="0.01">
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center gap-3">
                <button type="submit" id="submitBtn" class="inline-flex items-center rounded-md bg-primary px-6 py-2.5 text-sm text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg id="submitIcon" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span id="submitText">Create Campaign</span>
                </button>
                <a href="{{ route('sms.campaigns.index') }}" class="rounded-md border border-stroke px-6 py-2.5 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Sample tenant data for preview
    let sampleTenant = {
        name: 'John Doe',
        unit: 'A-101',
        estate_name: 'Bloomfield Apartments',
        water_bill: '1500.00',
        water_consumption: '25',
        due_date: '2026-08-05',
        month: 'July 2026',
        prev_read: '100',
        curr_read: '125',
        payment_status: 'pending',
        tenant_name: 'John Doe'
    };

    // Load template into message field
    function loadTemplate() {
        const select = document.getElementById('templateSelect');
        const selectedOption = select.options[select.selectedIndex];
        const content = selectedOption.getAttribute('data-content');
        
        if (content) {
            document.getElementById('message').value = content;
            updatePreview();
        }
    }

    // Insert placeholder at cursor position
    function insertPlaceholder(placeholder) {
        const textarea = document.getElementById('message');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const placeholderText = '{{' + placeholder + '}}';
        
        textarea.value = text.substring(0, start) + placeholderText + text.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + placeholderText.length;
        updatePreview();
    }

    // Update preview with sample data
    function updatePreview() {
        const message = document.getElementById('message').value;
        const estateSelect = document.getElementById('estate_id');
        const billingMonth = document.getElementById('billing_month').value;
        const selectedEstate = estateSelect.options[estateSelect.selectedIndex]?.text || 'Unknown Estate';
        
        const previewSection = document.getElementById('previewSection');
        const previewContent = document.getElementById('previewContent');
        
        if (!message.trim() || !estateSelect.value) {
            previewSection.style.display = 'none';
            return;
        }

        previewSection.style.display = 'block';

        // Update sample tenant data with form values
        const data = {
            ...sampleTenant,
            estate_name: selectedEstate,
            month: billingMonth || sampleTenant.month,
            tenant_name: sampleTenant.name
        };

        // Replace placeholders
        let preview = message;
        preview = preview.replace(/\{\{estate_name\}\}/g, data.estate_name);
        preview = preview.replace(/\{\{month\}\}/g, data.month);
        preview = preview.replace(/\{\{unit\}\}/g, data.unit);
        preview = preview.replace(/\{\{water_bill\}\}/g, data.water_bill);
        preview = preview.replace(/\{\{water_consumption\}\}/g, data.water_consumption);
        preview = preview.replace(/\{\{due_date\}\}/g, data.due_date);
        preview = preview.replace(/\{\{prev_read\}\}/g, data.prev_read);
        preview = preview.replace(/\{\{curr_read\}\}/g, data.curr_read);
        preview = preview.replace(/\{\{payment_status\}\}/g, data.payment_status);
        preview = preview.replace(/\{\{status\}\}/g, data.payment_status);
        preview = preview.replace(/\{\{tenant_name\}\}/g, data.tenant_name);
        preview = preview.replace(/\{\{name\}\}/g, data.tenant_name);

        // Display preview with styled message
        previewContent.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold">S</div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-semibold text-black dark:text-white">SHARETENT</span>
                        <span class="text-xs text-gray-400">Today</span>
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">${preview}</div>
                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-400">
                        <span>📱 To: ${data.unit} - ${data.tenant_name}</span>
                    </div>
                </div>
            </div>
        `;

        // Update character count and segments
        const charCount = preview.length;
        const segmentCount = Math.ceil(charCount / 160);
        const cost = (segmentCount * 0.60).toFixed(2);
        
        document.getElementById('charCount').textContent = charCount;
        document.getElementById('segmentCount').textContent = segmentCount;
        document.getElementById('estimatedCost').textContent = 'KES ' + cost;
    }

    // Loading state on form submit
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('campaignForm');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const submitIcon = document.getElementById('submitIcon');

        if (form) {
            form.addEventListener('submit', function() {
                // Disable button
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                
                // Change text and icon to loading spinner
                submitText.textContent = 'Creating...';
                submitIcon.innerHTML = `
                    <svg class="mr-2 h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                `;
            });
        }

        // Update preview on page load if there's a message
        const message = document.getElementById('message');
        if (message && message.value.trim()) {
            updatePreview();
        }
    });

    // Make functions globally accessible
    window.loadTemplate = loadTemplate;
    window.insertPlaceholder = insertPlaceholder;
    window.updatePreview = updatePreview;
</script>

<style>
    .cursor-pointer {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .cursor-pointer:hover {
        transform: scale(1.05);
        background-color: rgba(59, 130, 246, 0.2) !important;
    }
</style>
@endsection