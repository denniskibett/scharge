@extends('layouts.app')

@section('title', 'SMS Settings')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">⚙️ SMS Settings</h1>
        <p class="text-gray-500 mt-1">Configure and test your KenyaSMS integration.</p>
    </div>

    @if(session('info'))
        <div class="mb-6 rounded-xl bg-blue-50 border-l-4 border-blue-500 p-4 text-blue-800 shadow-sm">
            {{ session('info') }}
        </div>
    @endif

    <!-- Tabs -->
    <div class="flex border-b border-gray-200 mb-6">
        <button onclick="showTab('general')" id="tab-general-btn" class="py-2 px-4 text-sm font-medium border-b-2 border-brand-500 text-brand-600 transition">
            📡 General
        </button>
        <button onclick="showTab('placeholders')" id="tab-placeholders-btn" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600 transition">
            🔤 Placeholders Guide
        </button>
        <button onclick="showTab('help')" id="tab-help-btn" class="py-2 px-4 text-sm font-medium border-b-2 border-transparent hover:text-brand-600 transition">
            🆘 Help & Troubleshooting
        </button>
    </div>

    <!-- Tab 1: General Settings -->
    <div id="tab-general" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- API Key -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">🔑 API Key</label>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $apiKeyConfigured ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $apiKeyConfigured ? '✓ Configured' : '✗ Not configured' }}
                            </span>
                            <span class="text-xs text-gray-500">Set via <code class="bg-gray-200 px-1 rounded">KENYASMS_KEY</code> in .env</span>
                        </div>
                    </div>

                    <!-- Credit Balance (ADDED) -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">💰 SMS Credit Balance</label>
                        @if($apiKeyConfigured)
                            @if($balanceInfo['success'])
                                <div class="text-2xl font-bold text-green-600">{{ number_format($balanceInfo['balance'], 2) }} {{ $balanceInfo['currency'] ?? 'KES' }}</div>
                                <p class="text-xs text-gray-500 mt-1">Available credits</p>
                            @else
                                <div class="text-red-600">Failed to fetch balance: {{ $balanceInfo['error'] ?? 'Unknown error' }}</div>
                                <p class="text-xs text-gray-500 mt-1">Check your API key or network</p>
                            @endif
                        @else
                            <div class="text-gray-500">API key not configured</div>
                            <p class="text-xs text-gray-500 mt-1">Set <code>KENYASMS_KEY</code> in .env to see balance</p>
                        @endif
                    </div>

                    <!-- Sender ID -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">📤 Sender ID</label>
                        <div class="font-mono text-sm">{{ $senderId }}</div>
                        <p class="text-xs text-gray-500 mt-1">Set via <code class="bg-gray-200 px-1 rounded">KENYASMS_SENDER_ID</code></p>
                    </div>

                    <!-- Default Message Type -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">📨 Default Message Type</label>
                        <div class="font-mono text-sm">{{ ucfirst($defaultType) }}</div>
                        <p class="text-xs text-gray-500 mt-1">Set via <code class="bg-gray-200 px-1 rounded">KENYASMS_DEFAULT_TYPE</code></p>
                    </div>

                    <!-- Sandbox Mode -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">🧪 Sandbox Mode</label>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sandbox ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                {{ $sandbox ? 'Active (No real SMS sent)' : 'Inactive (Live mode)' }}
                            </span>
                            <span class="text-xs text-gray-500">To change, edit <code class="bg-gray-200 px-1 rounded">KENYASMS_SANDBOX=true/false</code> and restart server.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test SMS Card -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">📱 Test SMS</h3>
                <p class="text-gray-500 text-sm mb-4">Send a test message to verify your API key and configuration.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" id="test_phone" placeholder="e.g., 0712345678 or 254712345678" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="text-xs text-gray-500 mt-1">Kenyan number only</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Test Message</label>
                        <textarea id="test_message" rows="2" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Hello, this is a test message from your property management system."></textarea>
                    </div>
                    <button type="button" onclick="sendTestSms()" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded-xl shadow-sm transition">
                        ✈️ Send Test SMS
                    </button>
                    <div id="testResult" class="mt-3 text-sm hidden"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Placeholders Guide -->
    <div id="tab-placeholders" class="hidden space-y-6">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">📝 Available Placeholders</h3>
                <p class="text-gray-500 text-sm mb-4">Use these placeholders in your SMS templates. They will be replaced automatically when sending to tenants.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @php
                        $placeholders = ['{{name}}', '{{phone}}', '{{unit_number}}', '{{estate_name}}', '{{water_bill}}', '{{water_consumption}}', '{{month}}', '{{due_date}}', '{{total}}'];
                    @endphp
                    @foreach($placeholders as $placeholder)
                    <div class="bg-gray-50 rounded-lg p-3 flex justify-between items-center">
                        <code class="font-mono text-sm">{{ $placeholder }}</code>
                        <button onclick="copyToClipboard('{{ addslashes($placeholder) }}')" class="text-brand-600 text-xs hover:underline">Copy</button>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 bg-blue-50 p-3 rounded-lg text-sm text-blue-800">
                    💡 Tip: You can also create custom placeholders by modifying the controller.
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Help & Troubleshooting -->
    <div id="tab-help" class="hidden space-y-6">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">🆘 Need Help?</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border rounded-xl p-4">
                        <div class="font-semibold text-brand-600">🔑 How to get an API key?</div>
                        <p class="text-sm text-gray-600 mt-1">Register at <a href="https://kenyasms.com" target="_blank" class="text-brand-500 hover:underline">KenyaSMS.com</a>, purchase SMS credits, and copy your API key from the dashboard.</p>
                    </div>
                    <div class="border rounded-xl p-4">
                        <div class="font-semibold text-brand-600">📤 Sender ID approval</div>
                        <p class="text-sm text-gray-600 mt-1">Sender ID must be approved by KenyaSMS. Use a short, recognizable name (e.g., SHARETENT).</p>
                    </div>
                    <div class="border rounded-xl p-4">
                        <div class="font-semibold text-brand-600">🧪 Sandbox vs Live</div>
                        <p class="text-sm text-gray-600 mt-1">In sandbox mode, messages are logged but not actually sent – perfect for testing. Set <code>KENYASMS_SANDBOX=false</code> in .env to send real SMS.</p>
                    </div>
                    <div class="border rounded-xl p-4">
                        <div class="font-semibold text-brand-600">❌ Messages not being sent?</div>
                        <p class="text-sm text-gray-600 mt-1">Check your API key, ensure you have SMS credits, and that the recipient numbers are valid Kenyan phone numbers (format 254...).</p>
                    </div>
                </div>
                <div class="mt-4 bg-yellow-50 p-3 rounded-lg text-sm text-yellow-800">
                    ⚠️ If you change <code>.env</code> settings, you must restart the server (<code>composer run dev</code> or <code>php artisan serve</code>).
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 text-center text-xs text-gray-400">
        Powered by KenyaSMS API v1
    </div>
</div>

<script>
    function showTab(tabName) {
        var general = document.getElementById('tab-general');
        var placeholders = document.getElementById('tab-placeholders');
        var help = document.getElementById('tab-help');
        if (general) general.classList.add('hidden');
        if (placeholders) placeholders.classList.add('hidden');
        if (help) help.classList.add('hidden');
        
        var btnGeneral = document.getElementById('tab-general-btn');
        var btnPlaceholders = document.getElementById('tab-placeholders-btn');
        var btnHelp = document.getElementById('tab-help-btn');
        if (btnGeneral) {
            btnGeneral.classList.remove('border-brand-500', 'text-brand-600');
            btnGeneral.classList.add('border-transparent');
        }
        if (btnPlaceholders) {
            btnPlaceholders.classList.remove('border-brand-500', 'text-brand-600');
            btnPlaceholders.classList.add('border-transparent');
        }
        if (btnHelp) {
            btnHelp.classList.remove('border-brand-500', 'text-brand-600');
            btnHelp.classList.add('border-transparent');
        }
        
        if (tabName === 'general') {
            if (general) general.classList.remove('hidden');
            if (btnGeneral) btnGeneral.classList.add('border-brand-500', 'text-brand-600');
        } else if (tabName === 'placeholders') {
            if (placeholders) placeholders.classList.remove('hidden');
            if (btnPlaceholders) btnPlaceholders.classList.add('border-brand-500', 'text-brand-600');
        } else if (tabName === 'help') {
            if (help) help.classList.remove('hidden');
            if (btnHelp) btnHelp.classList.add('border-brand-500', 'text-brand-600');
        }
    }

    function sendTestSms() {
        var phone = document.getElementById('test_phone').value;
        var message = document.getElementById('test_message').value;
        var resultDiv = document.getElementById('testResult');

        if (!phone || !message) {
            resultDiv.className = 'mt-3 text-sm text-red-600';
            resultDiv.innerText = 'Please enter both phone number and message.';
            resultDiv.classList.remove('hidden');
            return;
        }

        var cleaned = phone.replace(/\D/g, '');
        if (!cleaned.startsWith('254') && cleaned.length === 9) {
            cleaned = '254' + cleaned;
        }
        if (!cleaned.startsWith('254') || cleaned.length !== 12) {
            resultDiv.className = 'mt-3 text-sm text-red-600';
            resultDiv.innerText = 'Invalid Kenyan phone number. Use format 0712345678 or 254712345678.';
            resultDiv.classList.remove('hidden');
            return;
        }

        resultDiv.className = 'mt-3 text-sm text-blue-600';
        resultDiv.innerText = 'Sending test SMS...';
        resultDiv.classList.remove('hidden');

        fetch('{{ route("sms.send-custom") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                phone: cleaned,
                message: message,
                message_type: 'transactional'
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                resultDiv.className = 'mt-3 text-sm text-green-600';
                resultDiv.innerText = '✅ Test SMS sent successfully!';
            } else {
                resultDiv.className = 'mt-3 text-sm text-red-600';
                resultDiv.innerText = '❌ Failed to send: ' + (data.error || 'Unknown error');
            }
        })
        .catch(function(err) {
            resultDiv.className = 'mt-3 text-sm text-red-600';
            resultDiv.innerText = '❌ Error: ' + err.message;
        });
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied: ' + text);
        }).catch(function() {
            alert('Could not copy. Select manually.');
        });
    }
</script>
@endsection