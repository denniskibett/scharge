@extends('layouts.app')

@section('title', 'SMS Broadcast')

@section('content')
<div class="container-fluid px-4 py-4" x-data="smsBroadcast()" x-init="init()" x-cloak>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">SMS Broadcast (Personalized)</h1>
        <p class="text-gray-500">Send personalized SMS to tenants using a template with variables, or send a custom SMS to any phone number.</p>
        @if($sandbox)
            <div class="mt-2 inline-block rounded-lg bg-yellow-100 px-3 py-1 text-sm text-yellow-800">⚠️ SANDBOX MODE – No real SMS will be sent</div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800">{{ session('error') }}</div>
    @endif

    <!-- SECTION 1: Send to Tenants (existing) -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-lg font-semibold mb-4">Send to Tenants</h3>
        <form method="POST" action="{{ route('sms.send') }}" @submit.prevent="submitForm">
            @csrf

            <!-- Template -->
            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-gray-700">Message Template</label>
                <textarea name="template" rows="4" class="w-full rounded-lg border border-gray-300 p-3 text-sm focus:border-brand-500 focus:outline-none" 
                    x-model="template" required></textarea>
                <p class="mt-1 text-xs text-gray-500">Available variables: <span x-text="availableVariables.join(', ')"></span></p>
                <p class="mt-1 text-xs text-gray-400">Use &#123;&#123;variable&#125;&#125; for placeholders.</p>
            </div>

            <!-- Message Type -->
            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-gray-700">Message Type</label>
                <select name="message_type" class="rounded-lg border border-gray-300 p-2 text-sm">
                    <option value="transactional">Transactional (bills, OTPs – higher priority)</option>
                    <option value="promotional">Promotional (announcements – cannot send 8pm-8am)</option>
                </select>
            </div>

            <!-- Recipient Selection -->
            <div class="mb-5">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button type="button" @click="selectAll()" class="rounded-lg bg-brand-500 px-3 py-1 text-sm text-white">Select All</button>
                    <button type="button" @click="selectNone()" class="rounded-lg bg-gray-500 px-3 py-1 text-sm text-white">Clear All</button>
                    <button type="button" @click="showEstateFilter = !showEstateFilter" class="rounded-lg bg-blue-500 px-3 py-1 text-sm text-white">Filter by Estate</button>
                </div>

                <div x-show="showEstateFilter" class="mb-3 flex gap-2">
                    <select x-model="selectedEstateId" class="rounded-lg border border-gray-300 p-2 text-sm">
                        <option value="">All Estates</option>
                        @foreach($estates as $estate)
                            <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="overflow-x-auto border rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-3"><input type="checkbox" @change="toggleAll($event)" :checked="selectedTenantIds.length === filteredTenants.length && filteredTenants.length"></th>
                                <th class="p-3 text-left">Name</th>
                                <th class="p-3 text-left">Phone</th>
                                <th class="p-3 text-left">Unit</th>
                                <th class="p-3 text-left">Estate</th>
                                <th class="p-3 text-left">Last Water Bill</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="tenant in filteredTenants" :key="tenant.id">
                                <tr class="border-t">
                                    <td class="p-3">
                                        <input type="checkbox" :value="tenant.id" @change="toggleTenant(tenant.id, $event)" :checked="selectedTenantIds.includes(tenant.id)">
                                    </td>
                                    <td class="p-3" x-text="tenant.name"></td>
                                    <td class="p-3" x-text="tenant.phone"></td>
                                    <td class="p-3" x-text="tenant.unit_number"></td>
                                    <td class="p-3" x-text="tenant.estate_name"></td>
                                    <td class="p-3">KES <span x-text="tenant.water_bill.toFixed(2)"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-sm">Selected: <span x-text="selectedTenantIds.length"></span> tenants</p>
            </div>

            <!-- Preview -->
            <div class="mb-5" x-show="selectedTenantIds.length && template">
                <h3 class="text-md font-semibold mb-2">Preview (first 3)</h3>
                <div class="space-y-2">
                    <template x-for="(preview, idx) in previews" :key="idx">
                        <div class="rounded-lg bg-gray-50 p-3 text-sm">
                            <p><strong>To:</strong> <span x-text="preview.phone"></span></p>
                            <p><strong>Message:</strong> <span x-text="preview.message"></span></p>
                        </div>
                    </template>
                </div>
            </div>

            <input type="hidden" name="recipients" :value="JSON.stringify(recipientsPayload)">
            <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-white hover:bg-brand-600" :disabled="selectedTenantIds.length === 0">
                <span x-show="selectedTenantIds.length > 0">Send SMS to Tenants</span>
                <span x-show="selectedTenantIds.length === 0">Select tenants first</span>
            </button>
        </form>
    </div>

    <!-- SECTION 2: Send to a Single Custom Number -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] mt-6">
        <h3 class="text-lg font-semibold mb-4">Send to a Single Phone Number</h3>
        <form method="POST" action="{{ route('sms.send.custom') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input type="text" name="phone" placeholder="e.g., 0712345678 or 254712345678" required class="w-full rounded-lg border border-gray-300 p-2">
                    <p class="text-xs text-gray-500 mt-1">Kenyan number only</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message Type</label>
                    <select name="message_type" class="w-full rounded-lg border border-gray-300 p-2">
                        <option value="transactional">Transactional (bills/OTPs – higher priority)</option>
                        <option value="promotional">Promotional (announcements)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                <textarea name="message" rows="3" class="w-full rounded-lg border border-gray-300 p-2" required placeholder="Enter your message here..."></textarea>
            </div>
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-white hover:bg-brand-600">
                Send SMS Now
            </button>
        </form>
    </div>
</div>

<script>
function smsBroadcast() {
    return {
        tenants: @json($tenants),
        template: 'Hi @{{name}}, your water bill for @{{month}} is KES @{{water_bill}}. Please pay by @{{due_date}}.',
        selectedTenantIds: [],
        showEstateFilter: false,
        selectedEstateId: '',
        
        get filteredTenants() {
            if (this.selectedEstateId) {
                return this.tenants.filter(t => t.estate_id == this.selectedEstateId);
            }
            return this.tenants;
        },
        
        get availableVariables() {
            return ['name', 'phone', 'unit_number', 'estate_name', 'water_bill', 'water_consumption', 'month', 'due_date'];
        },
        
        get recipientsPayload() {
            return this.tenants
                .filter(t => this.selectedTenantIds.includes(t.id))
                .map(t => ({
                    phone: t.phone,
                    variables: {
                        name: t.name,
                        phone: t.phone,
                        unit_number: t.unit_number,
                        estate_name: t.estate_name,
                        water_bill: t.water_bill.toFixed(2),
                        water_consumption: t.water_consumption,
                        month: new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
                        due_date: new Date(new Date().setDate(10)).toLocaleDateString('en-CA')
                    }
                }));
        },
        
        get previews() {
            return this.recipientsPayload.slice(0,3).map(r => {
                let msg = this.template;
                for (const [k,v] of Object.entries(r.variables)) {
                    msg = msg.replace(new RegExp('{{' + k + '}}', 'g'), v);
                }
                return { phone: r.phone, message: msg };
            });
        },
        
        selectAll() {
            this.selectedTenantIds = this.filteredTenants.map(t => t.id);
        },
        
        selectNone() {
            this.selectedTenantIds = [];
        },
        
        toggleAll(e) {
            if (e.target.checked) {
                this.selectAll();
            } else {
                this.selectNone();
            }
        },
        
        toggleTenant(id, event) {
            if (event.target.checked) {
                if (!this.selectedTenantIds.includes(id)) {
                    this.selectedTenantIds.push(id);
                }
            } else {
                this.selectedTenantIds = this.selectedTenantIds.filter(i => i !== id);
            }
        },
        
        submitForm(e) {
            if (this.selectedTenantIds.length === 0) {
                alert('Please select at least one tenant.');
                e.preventDefault();
                return;
            }
            if (this.recipientsPayload.length === 0) {
                alert('No valid recipients with phone numbers. Check that the selected tenants have valid Kenyan phone numbers.');
                e.preventDefault();
                return;
            }
            if (!confirm('Are you sure you want to send this SMS to ' + this.recipientsPayload.length + ' tenant(s)?')) {
                e.preventDefault();
                return;
            }
            e.target.closest('form').submit();
        },
        
        init() {}
    };
}
</script>
<style>[x-cloak]{display:none;}</style>
@endsection