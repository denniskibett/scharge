@extends('layouts.app')

@section('title', 'SMS Broadcast')

@section('content')
<div class="container mx-auto px-4 py-8" x-data="smsBroadcast()" x-init="init()" x-cloak>
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
        <button @click="activeTab = 'tenants'" :class="{'border-brand-500 text-brand-600': activeTab === 'tenants'}" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600 transition">
            📢 Send to Tenants
        </button>
        <button @click="activeTab = 'custom'" :class="{'border-brand-500 text-brand-600': activeTab === 'custom'}" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600 transition">
            ✉️ Send Custom SMS
        </button>
        <button @click="activeTab = 'history'" :class="{'border-brand-500 text-brand-600': activeTab === 'history'}" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600 transition">
            📜 SMS History
        </button>
    </div>

    <!-- Tab 1: Send to Tenants -->
    <div x-show="activeTab === 'tenants'" x-cloak>
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <form method="POST" action="{{ route('sms.send') }}" @submit.prevent="submitForm" class="p-6">
                @csrf

                <!-- Template Dropdown -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">📋 Load Saved Template</label>
                    <select x-model="selectedTemplateId" @change="loadTemplate" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">-- Select Template --</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" data-content="{{ $template->content }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Choose a template to pre‑fill the message.</p>
                </div>

                <!-- Message Template -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">✏️ Message Template</label>
                    <textarea x-model="template" rows="5" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Hi @{{name}}, pay by @{{due_date}}: Water @{{water_bill}}, Sec 500, Garb 300 = Total @{{total}}. Pay via *334# or portal."></textarea>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="text-xs text-gray-500">Available variables:</span>
                        <template x-for="varName in availableVariables" :key="varName">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-gray-100 text-gray-700" x-text="'@{{' + varName + '}}'"></span>
                        </template>
                    </div>
                </div>

                <!-- Message Type -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">⚙️ Message Type</label>
                    <select name="message_type" class="rounded-xl border-gray-300 shadow-sm w-full md:w-1/2">
                        <option value="transactional">Transactional (bills/OTPs – high priority)</option>
                        <option value="promotional">Promotional (announcements – restricted hours)</option>
                    </select>
                </div>

                <!-- Recipient Selection -->
                <div class="mb-6">
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button type="button" @click="selectAll()" class="bg-brand-600 hover:bg-brand-700 text-white px-3 py-1 rounded-lg text-sm shadow-sm">Select All</button>
                        <button type="button" @click="selectNone()" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded-lg text-sm shadow-sm">Clear All</button>
                        <button type="button" @click="showEstateFilter = !showEstateFilter" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-sm shadow-sm">
                            Filter by Estate <span x-text="showEstateFilter ? '▲' : '▼'"></span>
                        </button>
                    </div>

                    <div x-show="showEstateFilter" class="mb-3 transition-all">
                        <select x-model="selectedEstateId" class="rounded-xl border-gray-300 shadow-sm">
                            <option value="">All Estates</option>
                            @foreach($estates as $estate)
                                <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="overflow-x-auto border rounded-xl shadow-sm">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="p-3"><input type="checkbox" @change="toggleAll($event)" :checked="selectedTenantIds.length === filteredTenants.length && filteredTenants.length"></th>
                                    <th class="p-3 text-left">Name</th>
                                    <th class="p-3 text-left">Phone</th>
                                    <th class="p-3 text-left">Unit</th>
                                    <th class="p-3 text-left">Estate</th>
                                    <th class="p-3 text-left">Last Bill</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="tenant in filteredTenants" :key="tenant.id">
                                    <tr class="border-t hover:bg-gray-50">
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
                    <p class="mt-2 text-sm text-gray-600">✅ Selected: <span x-text="selectedTenantIds.length" class="font-semibold"></span> tenants</p>
                </div>

                <!-- Preview -->
                <div class="mb-6" x-show="selectedTenantIds.length && template">
                    <h3 class="text-md font-semibold text-gray-800 mb-2">📄 Preview (first 3)</h3>
                    <div class="space-y-2 bg-gray-50 p-4 rounded-xl">
                        <template x-for="(preview, idx) in previews" :key="idx">
                            <div class="border-l-4 border-brand-300 pl-3 py-1">
                                <p class="text-xs text-gray-500"><strong>To:</strong> <span x-text="preview.phone"></span></p>
                                <p class="text-sm text-gray-800"><strong>Message:</strong> <span x-text="preview.message"></span></p>
                            </div>
                        </template>
                    </div>
                </div>

                <input type="hidden" name="recipients" :value="JSON.stringify(recipientsPayload)">
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-bold py-3 px-8 rounded-xl shadow-md transition duration-200 flex items-center justify-center gap-2" :disabled="sending">
                    <svg x-show="sending" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-show="!sending">📲 Send SMS to Tenants</span>
                    <span x-show="sending">Sending...</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Tab 2: Send Custom SMS -->
    <div x-show="activeTab === 'custom'" x-cloak>
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <form method="POST" action="{{ route('sms.send-custom') }}" class="p-6 space-y-6" id="customSmsForm" data-ajax="false" onsubmit="return true">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">📋 Load Saved Template (optional)</label>
                    <select id="customTemplateSelect" class="w-full rounded-xl border-gray-300 shadow-sm">
                        <option value="">-- Select Template --</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" data-content="{{ $template->content }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Select a template to pre‑fill the message, then edit if needed.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">📞 Phone Number</label>
                        <input type="text" name="phone" placeholder="e.g., 0712345678 or 254712345678" required class="w-full rounded-xl border-gray-300 shadow-sm">
                        <p class="text-xs text-gray-500 mt-1">Kenyan number only</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">⚙️ Message Type</label>
                        <select name="message_type" class="w-full rounded-xl border-gray-300 shadow-sm">
                            <option value="transactional">Transactional (bills/OTPs – high priority)</option>
                            <option value="promotional">Promotional (announcements)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">💬 Message</label>
                    <textarea name="message" id="customMessage" rows="4" class="w-full rounded-xl border-gray-300 shadow-sm" placeholder="Enter your message here..." required></textarea>
                </div>
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-bold py-3 px-8 rounded-xl shadow-md transition">✉️ Send SMS Now</button>
            </form>
        </div>
    </div>

    <!-- Tab 3: SMS History -->
    <div x-show="activeTab === 'history'" x-cloak>
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3">ID</th>
                            <th class="p-3">Phone</th>
                            <th class="p-3">Message</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Provider ID</th>
                            <th class="p-3">Failure Reason</th>
                            <th class="p-3">Sent At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3">{{ $log->id }}</td>
                            <td class="p-3">{{ $log->recipient_phone }}</td>
                            <td class="p-3">{{ Str::limit($log->message, 60) }}</td>
                            <td class="p-3"><span class="px-2 py-1 rounded-full text-xs {{ $log->status=='sent' ? 'bg-green-100 text-green-800' : ($log->status=='failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">{{ $log->status }}</span></td>
                            <td class="p-3">{{ $log->provider_message_id ?? '-' }}</td>
                            <td class="p-3">{{ Str::limit($log->failure_reason, 30) ?? '-' }}</td>
                            <td class="p-3">{{ $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="p-3 text-center text-gray-500">No logs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $logs->links() }}</div>
        </div>
    </div>
</div>

<script>
function smsBroadcast() {
    return {
        tenants: @json($tenants),
        templates: @json($templates),
        template: 'Hi @{{name}}, pay by @{{due_date}}: Water @{{water_bill}}, Sec 500, Garb 300 = Total @{{total}}. Pay via *334# or portal.',
        selectedTemplateId: '',
        selectedTenantIds: [],
        showEstateFilter: false,
        selectedEstateId: '',
        activeTab: 'tenants',
        sending: false,
        customTemplateId: '',
        customMessage: '',

        get filteredTenants() {
            if (this.selectedEstateId) {
                return this.tenants.filter(t => t.estate_id == this.selectedEstateId);
            }
            return this.tenants;
        },

        get availableVariables() {
            return ['name', 'phone', 'unit_number', 'unit', 'estate_name', 'water_bill', 'water_consumption', 'month', 'due_date', 'total'];
        },

        get recipientsPayload() {
            const now = new Date();
            // Due date: 5th of current month (YYYY-MM-DD)
            const dueDate = new Date(now.getFullYear(), now.getMonth(), 5);
            const dueDateString = dueDate.toLocaleDateString('en-CA');
            // Billing month: previous month (e.g., "May 2026")
            const billingMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const monthString = billingMonth.toLocaleString('default', { month: 'long', year: 'numeric' });

            return this.tenants
                .filter(t => this.selectedTenantIds.includes(t.id))
                .map(t => ({
                    phone: t.phone,
                    variables: {
                        name: t.name,
                        phone: t.phone,
                        unit_number: t.unit_number,
                        unit: t.unit_number,
                        estate_name: t.estate_name,
                        water_bill: t.water_bill.toFixed(2),
                        water_consumption: t.water_consumption,
                        month: monthString,
                        due_date: dueDateString,
                        total: t.total
                    }
                }));
        },

        get previews() {
            return this.recipientsPayload.slice(0,3).map(r => {
                let msg = this.template;
                for (const [k,v] of Object.entries(r.variables)) {
                    msg = msg.replace(new RegExp('@{{' + k + '}}', 'g'), v);
                }
                return { phone: r.phone, message: msg };
            });
        },

        loadTemplate() {
            if (!this.selectedTemplateId) return;
            const selected = this.templates.find(t => t.id == this.selectedTemplateId);
            if (selected) {
                this.template = selected.content;
            }
        },

        loadCustomTemplate() {
            if (!this.customTemplateId) return;
            const selected = this.templates.find(t => t.id == this.customTemplateId);
            if (selected) {
                this.customMessage = selected.content;
            }
        },

        selectAll() {
            this.selectedTenantIds = this.filteredTenants.map(t => t.id);
        },

        selectNone() {
            this.selectedTenantIds = [];
        },

        toggleAll(e) {
            if (e.target.checked) this.selectAll();
            else this.selectNone();
        },

        toggleTenant(id, event) {
            if (event.target.checked) {
                if (!this.selectedTenantIds.includes(id)) this.selectedTenantIds.push(id);
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
            if (!confirm('Send SMS to ' + this.recipientsPayload.length + ' tenant(s)?')) {
                e.preventDefault();
                return;
            }
            this.sending = true;
            e.target.closest('form').submit();
        },

        init() {}
    };
}
</script>

<script>
    (function() {
        const templateSelect = document.getElementById('customTemplateSelect');
        const messageArea = document.getElementById('customMessage');
        if (templateSelect && messageArea) {
            templateSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const content = selectedOption.getAttribute('data-content');
                if (content) messageArea.value = content;
            });
        }
    })();
</script>

<style>[x-cloak]{display:none;}</style>
@endsection