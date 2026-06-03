@extends('layouts.app')

@section('title', 'SMS Broadcast')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 text-center md:text-left">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">📱 SMS Manager</h1>
        <p class="text-gray-500 mt-2">Send personalized SMS to tenants, a single number, or view history.</p>
        @if($sandbox)
            <div class="inline-block mt-3 rounded-full bg-yellow-100 px-4 py-1 text-sm text-yellow-800">⚠️ SANDBOX MODE – No real SMS will be sent</div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl bg-green-50 border-l-4 border-green-500 p-4 text-green-800 shadow-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 rounded-xl bg-red-50 border-l-4 border-red-500 p-4 text-red-800 shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    @if(isset($internationalCount) && $internationalCount > 0)
        <div class="mb-6 rounded-xl bg-yellow-50 border-l-4 border-yellow-500 p-4 text-yellow-800 shadow-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            {{ $internationalCount }} tenant(s) with international numbers were excluded from the list because KenyaSMS only supports Kenyan numbers.
        </div>
    @endif

    <!-- Tab Headers -->
    <div class="flex border-b border-gray-200 mb-6">
        <button onclick="activeTab = 'tenants'; renderTab()" id="tab-tenants" class="py-2 px-4 text-sm font-medium border-b-2 border-brand-500 text-brand-600">📢 Send to Tenants</button>
        <button onclick="activeTab = 'custom'; renderTab()" id="tab-custom" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600">✉️ Send Custom SMS</button>
        <button onclick="activeTab = 'history'; renderTab()" id="tab-history" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600">📜 SMS History</button>
        <button onclick="activeTab = 'campaigns'; renderTab()" id="tab-campaigns" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600">📊 Campaigns</button>
    </div>

    <!-- Tab 1: Send to Tenants -->
    <div id="tenants-tab" style="display: block;">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <form method="POST" action="{{ route('sms.send') }}" id="bulkForm" class="p-6">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">📋 Load Saved Template</label>
                    <select id="templateSelect" class="w-full rounded-xl border-gray-300 shadow-sm">
                        <option value="">-- Select Template --</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" data-content="{{ $template->content }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">✏️ Message Template</label>
                    <textarea id="template" name="template" rows="5" class="w-full rounded-xl border-gray-300 shadow-sm" placeholder="Hi @{{name}}, please pay your @{{month}} water bill by @{{due_date}}. Paybill 7263733 Acc @{{unit}} KES @{{water_bill}}">Hi @{{name}}, please pay your @{{month}} water bill by @{{due_date}}. Paybill 7263733 Acc @{{unit}} KES @{{water_bill}}</textarea>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="text-xs text-gray-500">Available variables: name, unit, water_bill, due_date, month, estate_name, prev_read, curr_read, water_consumption</span>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">⚙️ Message Type</label>
                    <select name="message_type" class="rounded-xl border-gray-300 shadow-sm w-full md:w-1/2">
                        <option value="transactional">Transactional (bills/OTPs – high priority)</option>
                        <option value="promotional">Promotional (announcements – restricted hours)</option>
                    </select>
                </div>

                <div class="mb-6">
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button type="button" id="selectAllBtn" class="bg-brand-600 hover:bg-brand-700 text-white px-3 py-1 rounded-lg text-sm">Select All</button>
                        <button type="button" id="selectNoneBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded-lg text-sm">Clear All</button>
                    </div>

                    <!-- Estate filter – always visible -->
                    <div class="mb-3">
                        <select id="estateFilter" class="rounded-xl border-gray-300 shadow-sm">
                            <option value="">All Estates</option>
                            @foreach($estates as $estate)
                                <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="overflow-x-auto border rounded-xl shadow-sm">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr><th class="p-3"><input type="checkbox" id="toggleAllCheckbox"></th><th class="p-3 text-left">Name</th><th class="p-3 text-left">Phone</th><th class="p-3 text-left">Unit</th><th class="p-3 text-left">Estate</th><th class="p-3 text-left">Water Bill</th></td>
                            </thead>
                            <tbody id="tenantsTableBody">
                                @foreach($tenants as $tenant)
                                <tr class="border-t hover:bg-gray-50" data-estate-id="{{ $tenant['estate_id'] }}">
                                    <td class="p-3"><input type="checkbox" class="tenant-checkbox" 
                                        data-id="{{ $tenant['id'] }}"
                                        data-phone="{{ $tenant['phone'] }}"
                                        data-name="{{ $tenant['name'] }}"
                                        data-unit="{{ $tenant['unit'] }}"
                                        data-estate="{{ $tenant['estate_name'] }}"
                                        data-estate-id="{{ $tenant['estate_id'] }}"
                                        data-waterbill="{{ $tenant['water_bill'] }}"
                                        data-consumption="{{ $tenant['water_consumption'] }}"
                                        data-prev_read="{{ $tenant['prev_read'] }}"
                                        data-curr_read="{{ $tenant['curr_read'] }}"></td>
                                    <td class="p-3">{{ $tenant['name'] }}</td>
                                    <td class="p-3">{{ $tenant['phone'] }}</td>
                                    <td class="p-3">{{ $tenant['unit_number'] }}</td>
                                    <td class="p-3">{{ $tenant['estate_name'] }}</td>
                                    <td class="p-3">KES {{ number_format($tenant['water_bill'], 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">✅ Selected: <span id="selectedCount">0</span> tenants</p>
                </div>

                <div id="previewSection" class="mb-6" style="display: none;">
                    <h3 class="text-md font-semibold text-gray-800 mb-2">📄 Preview (first 3)</h3>
                    <div id="previewContainer" class="space-y-2 bg-gray-50 p-4 rounded-xl"></div>
                </div>

                <input type="hidden" name="recipients" id="recipientsJson">
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-bold py-3 px-8 rounded-xl shadow-md">📲 Send SMS to Tenants</button>
            </form>
        </div>
    </div>

    <!-- Tab 2: Send Custom SMS -->
    <div id="custom-tab" style="display: none;">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <form method="POST" action="{{ route('sms.send-custom') }}" class="p-6 space-y-6">
                @csrf
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">📞 Phone Number</label><input type="text" name="phone" placeholder="e.g., 254712345678" required class="w-full rounded-xl border-gray-300 shadow-sm"></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">⚙️ Message Type</label><select name="message_type" class="w-full rounded-xl border-gray-300 shadow-sm"><option value="transactional">Transactional</option><option value="promotional">Promotional</option></select></div>
                <div><label class="block text-sm font-semibold text-gray-700 mb-2">💬 Message</label><textarea name="message" rows="4" class="w-full rounded-xl border-gray-300 shadow-sm" required></textarea></div>
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-bold py-3 px-8 rounded-xl shadow-md">✉️ Send SMS Now</button>
            </form>
        </div>
    </div>

    <!-- Tab 3: SMS History -->
    <div id="history-tab" style="display: none;">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50"><tr><th>ID</th><th>Phone</th><th>Message</th><th>Status</th><th>Sent At</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr class="border-t"><td class="p-2">{{ $log->id }}<\/td><td class="p-2">{{ $log->recipient_phone }}<\/td><td class="p-2">{{ Str::limit($log->message, 60) }}<\/td><td class="p-2"><span class="badge">{{ $log->status }}</span><\/td><td class="p-2">{{ $log->created_at->format('d/m/Y H:i') }}<\/td><\/tr>
                        @empty
                        <tr><td colspan="5" class="p-3 text-center">No logs found.∂n<\/td><\/tr>
                        @endforelse
                    </tbody>
                <\/table>
            </div>
            <div class="mt-4">{{ $logs->links() }}</div>
        </div>
    </div>

    <!-- Tab 4: Campaigns -->
    <div id="campaigns-tab" style="display: none;">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50"><tr><th>ID</th><th>Name</th><th>Date</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($campaigns ?? [] as $campaign)
                        <tr class="border-t"><td class="p-2">{{ $campaign->id }}<\/td><td class="p-2">{{ $campaign->name }}<\/td><td class="p-2">{{ $campaign->created_at->format('d/m/Y H:i') }}<\/td><td class="p-2">{{ $campaign->total_recipients }}<\/td><td class="p-2">{{ $campaign->sent_count }}<\/td><td class="p-2">{{ $campaign->failed_count }}<\/td><td class="p-2"><span class="badge">{{ $campaign->status }}</span><\/td><td class="p-2"><a href="{{ route('sms.campaigns.show', $campaign->id) }}" class="text-blue-600">View</a><\/td><\/tr>
                        @empty
                        <tr><td colspan="8">No campaigns yet.∂n<\/td><\/tr>
                        @endforelse
                    </tbody>
                <\/table>
            </div>
            <div class="mt-4">{{ ($campaigns ?? collect())->links() }}</div>
        </div>
    </div>
</div>

<script>
    let activeTab = 'tenants';
    function renderTab() {
        document.getElementById('tenants-tab').style.display = 'none';
        document.getElementById('custom-tab').style.display = 'none';
        document.getElementById('history-tab').style.display = 'none';
        document.getElementById('campaigns-tab').style.display = 'none';
        document.getElementById('tenants-tab').style.display = activeTab === 'tenants' ? 'block' : 'none';
        document.getElementById('custom-tab').style.display = activeTab === 'custom' ? 'block' : 'none';
        document.getElementById('history-tab').style.display = activeTab === 'history' ? 'block' : 'none';
        document.getElementById('campaigns-tab').style.display = activeTab === 'campaigns' ? 'block' : 'none';
        
        document.querySelectorAll('#tab-tenants, #tab-custom, #tab-history, #tab-campaigns').forEach(btn => {
            btn.classList.remove('border-brand-500', 'text-brand-600');
            btn.classList.add('border-transparent');
        });
        let activeBtn = document.getElementById(`tab-${activeTab}`);
        if (activeBtn) {
            activeBtn.classList.add('border-brand-500', 'text-brand-600');
            activeBtn.classList.remove('border-transparent');
        }
    }
    
    document.getElementById('templateSelect')?.addEventListener('change', function() {
        let selected = this.options[this.selectedIndex];
        let content = selected.getAttribute('data-content');
        if (content) {
            document.getElementById('template').value = content;
            updatePreview();
        }
    });
    
    let checkboxes = document.querySelectorAll('.tenant-checkbox');
    let selectedCountSpan = document.getElementById('selectedCount');
    let toggleAllCheckbox = document.getElementById('toggleAllCheckbox');
    let templateTextarea = document.getElementById('template');
    let previewSection = document.getElementById('previewSection');
    let previewContainer = document.getElementById('previewContainer');
    let estateFilter = document.getElementById('estateFilter');
    
    function filterByEstate() {
        let selectedEstateId = estateFilter.value;
        let rows = document.querySelectorAll('#tenantsTableBody tr');
        rows.forEach(row => {
            let estateId = row.getAttribute('data-estate-id');
            if (selectedEstateId === '' || estateId == selectedEstateId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        updateSelectedCount();
    }
    
    function updatePreview() {
        let template = templateTextarea ? templateTextarea.value : '';
        let visibleCheckboxes = Array.from(checkboxes).filter(cb => {
            let row = cb.closest('tr');
            return row.style.display !== 'none';
        });
        let checkedBoxes = visibleCheckboxes.filter(cb => cb.checked);
        let selectedCount = checkedBoxes.length;
        
        if (selectedCount > 0 && template.trim() !== '') {
            previewSection.style.display = 'block';
            let previews = [];
            let now = new Date();
            let dueDate = new Date(now.getFullYear(), now.getMonth(), 5).toLocaleDateString('en-CA');
            let month = new Date(now.getFullYear(), now.getMonth() - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
            
            for (let i = 0; i < Math.min(3, selectedCount); i++) {
                let cb = checkedBoxes[i];
                let phone = cb.getAttribute('data-phone');
                let name = cb.getAttribute('data-name');
                let unit = cb.getAttribute('data-unit');
                let water_bill = parseFloat(cb.getAttribute('data-waterbill')).toFixed(2);
                let consumption = cb.getAttribute('data-consumption');
                let prev_read = cb.getAttribute('data-prev_read');
                let curr_read = cb.getAttribute('data-curr_read');
                let estate_name = cb.getAttribute('data-estate');
                
                let message = template;
                message = message.replace(/\{\{name\}\}/g, name);
                message = message.replace(/\{\{unit\}\}/g, unit);
                message = message.replace(/\{\{unit_number\}\}/g, unit);
                message = message.replace(/\{\{water_bill\}\}/g, water_bill);
                message = message.replace(/\{\{water_consumption\}\}/g, consumption);
                message = message.replace(/\{\{due_date\}\}/g, dueDate);
                message = message.replace(/\{\{month\}\}/g, month);
                message = message.replace(/\{\{prev_read\}\}/g, prev_read);
                message = message.replace(/\{\{curr_read\}\}/g, curr_read);
                message = message.replace(/\{\{estate_name\}\}/g, estate_name);
                
                previews.push({ phone: phone, message: message });
            }
            
            let html = '';
            previews.forEach(p => {
                html += '<div class="border-l-4 border-brand-300 pl-3 py-1">' +
                            '<p class="text-xs text-gray-500"><strong>To:</strong> ' + p.phone + '</p>' +
                            '<p class="text-sm text-gray-800"><strong>Message:</strong> ' + p.message + '</p>' +
                        '</div>';
            });
            previewContainer.innerHTML = html;
        } else {
            previewSection.style.display = 'none';
        }
    }
    
    function updateSelectedCount() {
        let visibleCheckboxes = Array.from(checkboxes).filter(cb => {
            let row = cb.closest('tr');
            return row.style.display !== 'none';
        });
        let checked = visibleCheckboxes.filter(cb => cb.checked).length;
        if (selectedCountSpan) selectedCountSpan.innerText = checked;
        if (toggleAllCheckbox) {
            toggleAllCheckbox.checked = (checked === visibleCheckboxes.length && visibleCheckboxes.length > 0);
        }
        updatePreview();
    }
    
    checkboxes.forEach(cb => cb.addEventListener('change', updateSelectedCount));
    if (toggleAllCheckbox) {
        toggleAllCheckbox.addEventListener('change', function() {
            let visibleCheckboxes = Array.from(checkboxes).filter(cb => {
                let row = cb.closest('tr');
                return row.style.display !== 'none';
            });
            visibleCheckboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }
    document.getElementById('selectAllBtn')?.addEventListener('click', () => {
        let visibleCheckboxes = Array.from(checkboxes).filter(cb => {
            let row = cb.closest('tr');
            return row.style.display !== 'none';
        });
        visibleCheckboxes.forEach(cb => cb.checked = true);
        updateSelectedCount();
    });
    document.getElementById('selectNoneBtn')?.addEventListener('click', () => {
        let visibleCheckboxes = Array.from(checkboxes).filter(cb => {
            let row = cb.closest('tr');
            return row.style.display !== 'none';
        });
        visibleCheckboxes.forEach(cb => cb.checked = false);
        updateSelectedCount();
    });
    templateTextarea?.addEventListener('input', updatePreview);
    estateFilter?.addEventListener('change', filterByEstate);
    
    document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
        let selected = [];
        let template = document.getElementById('template').value;
        if (!template.trim()) {
            alert('Please enter a message template.');
            e.preventDefault();
            return false;
        }
        
        let now = new Date();
        let dueDate = new Date(now.getFullYear(), now.getMonth(), 5).toLocaleDateString('en-CA');
        let month = new Date(now.getFullYear(), now.getMonth() - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
        
        let visibleCheckboxes = Array.from(checkboxes).filter(cb => {
            let row = cb.closest('tr');
            return row.style.display !== 'none';
        });
        let checkedBoxes = visibleCheckboxes.filter(cb => cb.checked);
        
        checkedBoxes.forEach(cb => {
            let phone = cb.getAttribute('data-phone');
            if (!phone) return;
            let name = cb.getAttribute('data-name');
            let unit = cb.getAttribute('data-unit');
            let water_bill = parseFloat(cb.getAttribute('data-waterbill')).toFixed(2);
            let consumption = cb.getAttribute('data-consumption');
            let prev_read = cb.getAttribute('data-prev_read');
            let curr_read = cb.getAttribute('data-curr_read');
            let estate_name = cb.getAttribute('data-estate');
            
            selected.push({
                phone: phone,
                variables: {
                    name: name,
                    unit: unit,
                    unit_number: unit,
                    water_bill: water_bill,
                    water_consumption: consumption,
                    due_date: dueDate,
                    month: month,
                    prev_read: prev_read,
                    curr_read: curr_read,
                    estate_name: estate_name
                }
            });
        });
        
        if (selected.length === 0) {
            alert('Please select at least one tenant.');
            e.preventDefault();
            return false;
        }
        
        document.getElementById('recipientsJson').value = JSON.stringify(selected);
        return true;
    });
    
    // Initial filter and count
    filterByEstate();
    renderTab();
</script>
<style>
    .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; background: #e2e8f0; }
</style>
@endsection