@extends('layouts.app')

@section('title', 'SMS Manager')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    @include('partials.sms-header', ['sandbox' => $sandbox ?? false, 'internationalCount' => $internationalCount ?? 0])

    @if(session('success'))
        @include('partials.alert.success', ['message' => session('success')])
    @endif
    @if(session('error'))
        @include('partials.alert.error', ['message' => session('error')])
    @endif
    @if(session('info'))
        @include('partials.alert.info', ['message' => session('info')])
    @endif

    <!-- Tab Navigation -->
    <div class="flex border-b border-gray-200 mb-6">
        <button onclick="switchTab('tenants')" id="tab-tenants" 
            class="tab-button py-2 px-4 text-sm font-medium border-b-2 border-brand-500 text-brand-600">
            📢 Send to Tenants
        </button>
        <button onclick="switchTab('custom')" id="tab-custom" 
            class="tab-button py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600">
            ✉️ Send Custom SMS
        </button>
        <button onclick="switchTab('history')" id="tab-history" 
            class="tab-button py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600">
            📜 SMS History
        </button>
        <button onclick="switchTab('campaigns')" id="tab-campaigns" 
            class="tab-button py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600">
            📊 Campaigns
        </button>
        <button onclick="openTemplateModal()" 
            class="ml-auto py-2 px-4 text-sm font-medium bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            + Templates
        </button>
    </div>

    <!-- Tab 1: Send to Tenants -->
    <div id="tenants-tab" class="tab-content" style="display: block;">
        @include('partials.form.form-sms-broadcast', [
            'templates' => $templates ?? [],
            'estates' => $estates ?? [],
            'tenants' => $tenants ?? []
        ])
    </div>

    <!-- Tab 2: Send Custom SMS -->
    <div id="custom-tab" class="tab-content" style="display: none;">
        @include('partials.form.form-sms-custom', ['templates' => $templates ?? []])
    </div>

    <!-- Tab 3: SMS History -->
    <div id="history-tab" class="tab-content" style="display: none;">
        @include('partials.table.table-sms-logs', ['logs' => $logs ?? collect()])
    </div>

    <!-- Tab 4: Campaigns -->
    <div id="campaigns-tab" class="tab-content" style="display: none;">
        @include('partials.table.table-sms-campaign', ['campaigns' => $campaigns ?? collect()])
    </div>
</div>

<!-- Modals -->
@include('partials.modal.sms-template-create-modal')
@include('partials.modal.sms-campaign-create-modal')
@include('partials.modal.confirmation-modal')

@push('scripts')
<script>
let activeTab = 'tenants';

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    document.getElementById(`${tab}-tab`).style.display = 'block';
    
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('border-brand-500', 'text-brand-600');
        btn.classList.add('border-transparent');
    });
    const activeBtn = document.getElementById(`tab-${tab}`);
    if (activeBtn) {
        activeBtn.classList.add('border-brand-500', 'text-brand-600');
        activeBtn.classList.remove('border-transparent');
    }
}

// Broadcast form logic
let checkboxes = document.querySelectorAll('.tenant-checkbox');
let selectedCountSpan = document.getElementById('selectedCount');
let toggleAllCheckbox = document.getElementById('toggleAllCheckbox');
let templateTextarea = document.getElementById('template');
let previewSection = document.getElementById('previewSection');
let previewContainer = document.getElementById('previewContainer');
let estateFilter = document.getElementById('estateFilter');

function filterByEstate() {
    let selectedEstateId = estateFilter?.value;
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
    let template = templateTextarea?.value || '';
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
                        '<p class="text-sm text-gray-800">' + p.message + '</p>' +
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

function resendFailedCampaign(campaignId) {
    if (confirm('Resend failed messages for this campaign?')) {
        fetch(`/sms/campaigns/${campaignId}/resend-failed`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json()).then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to resend: ' + data.error);
            }
        }).catch(error => {
            alert('Error resending messages');
        });
    }
}

// Event Listeners
document.getElementById('templateSelect')?.addEventListener('change', function() {
    let selected = this.options[this.selectedIndex];
    let content = selected.getAttribute('data-content');
    if (content) {
        document.getElementById('template').value = content;
        updatePreview();
    }
});

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

checkboxes.forEach(cb => cb.addEventListener('change', updateSelectedCount));
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
    
    let campaignName = prompt('Enter campaign name (optional):', 'Campaign ' + new Date().toLocaleString());
    if (campaignName === null) {
        e.preventDefault();
        return false;
    }
    document.getElementById('campaignName').value = campaignName || 'Campaign ' + new Date().toLocaleString();
    
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

filterByEstate();
</script>
@endpush
@endsection