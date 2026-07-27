@extends('layouts.app')

@section('title', 'Quick Entry')

@section('content')
<div class="flex flex-col gap-5 p-6">
    <!-- Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Quick Entry
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Fast check-in with only essential information
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('security.index') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Dashboard
                </a>
                <a href="{{ route('security.full-entry') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Full Entry
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="successMessage" class="hidden rounded-2xl border border-success-200 bg-success-50 p-4 text-success-700 dark:border-success-500/10 dark:bg-success-500/10 dark:text-success-400"></div>
    <div id="errorMessage" class="hidden rounded-2xl border border-error-200 bg-error-50 p-4 text-error-700 dark:border-error-500/10 dark:bg-error-500/10 dark:text-error-400"></div>

    <!-- Quick Entry Form -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Quick Check-In</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fill in the essential details below</p>
                </div>
                <!-- 🔍 VISITOR SEARCH -->
                <div class="mt-3 sm:mt-0">
                    <div class="flex gap-2">
                        <input type="text" id="searchVisitor" 
                               placeholder="Search by phone or ID..."
                               class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <button type="button" onclick="searchVisitor()" 
                                class="rounded-lg bg-brand-500 px-4 py-2 text-sm text-white hover:bg-brand-600">
                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                        <button type="button" onclick="clearSearch()" 
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Clear
                        </button>
                    </div>
                    <div id="searchResult" class="mt-2 hidden rounded-lg border border-blue-200 bg-blue-50 p-2 text-sm text-blue-700"></div>
                </div>
            </div>
        </div>
        <div class="pt-4">
            <form id="quickEntryForm">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    
                    <!-- Estate -->
                    <div>
                        <label for="estate_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Estate <span class="text-error-500">*</span>
                        </label>
                        <select id="estate_id" name="estate_id" required
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select Estate</option>
                        </select>
                    </div>

                    <!-- Unit -->
                    <div>
                        <label for="unit_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Unit <span class="text-error-500">*</span>
                        </label>
                        <select id="unit_id" name="unit_id" required
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select Unit</option>
                        </select>
                        <div id="noUnitsMsg" class="hidden mt-1 text-sm text-yellow-600 dark:text-yellow-400">
                            No units found for this estate
                        </div>
                    </div>

                    <!-- Visitor Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Visitor Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" required
                               placeholder="Enter full name"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone Number <span class="text-error-500">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" required
                               placeholder="e.g., 0712345678"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                    </div>

                    <!-- Purpose -->
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Purpose <span class="text-error-500">*</span>
                        </label>
                        <select id="purpose" name="purpose" required
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select Purpose</option>
                            <option value="visitor">Visitor</option>
                            <option value="delivery">Delivery</option>
                            <option value="meeting">Meeting</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="contractor">Contractor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Vehicle Registration -->
                    <div>
                        <label for="vehicle_registration" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Registration</label>
                        <input type="text" id="vehicle_registration" name="vehicle_registration"
                               placeholder="e.g., KDA 123A (optional)"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                    </div>

                    <!-- Vehicle Type -->
                    <div>
                        <label for="vehicle_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Type</label>
                        <select id="vehicle_type" name="vehicle_type"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select Vehicle Type</option>
                            <option value="Car">Car</option>
                            <option value="SUV">SUV</option>
                            <option value="Truck">Truck</option>
                            <option value="Van">Van</option>
                            <option value="Bus">Bus</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Vehicle Model -->
                    <div>
                        <label for="vehicle_model" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Model</label>
                        <input type="text" id="vehicle_model" name="vehicle_model"
                               placeholder="e.g., Toyota Camry"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                    </div>

                    <!-- Vehicle Color -->
                    <div>
                        <label for="vehicle_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Color</label>
                        <select id="vehicle_color" name="vehicle_color"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Select Color</option>
                            <option value="White">White</option>
                            <option value="Black">Black</option>
                            <option value="Silver">Silver</option>
                            <option value="Gray">Gray</option>
                            <option value="Red">Red</option>
                            <option value="Blue">Blue</option>
                            <option value="Green">Green</option>
                            <option value="Yellow">Yellow</option>
                            <option value="Orange">Orange</option>
                            <option value="Brown">Brown</option>
                            <option value="Gold">Gold</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Hidden field for visitor_id (for existing visitors) -->
                <input type="hidden" id="visitor_id" name="visitor_id" value="">

                <!-- Form Actions -->
                <div class="flex flex-col-reverse gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ route('security.index') }}" 
                       class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 dark:bg-brand-600 dark:hover:bg-brand-700">
                        <span id="btnText">Check In</span>
                        <span id="btnSpinner" class="hidden ml-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadEstates();
    
    document.getElementById('estate_id').addEventListener('change', function() {
        loadUnits(this.value);
    });
    
    document.getElementById('quickEntryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm();
    });

    // Allow Enter key to trigger search
    document.getElementById('searchVisitor').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchVisitor();
        }
    });
});

function showMessage(message, type) {
    const successEl = document.getElementById('successMessage');
    const errorEl = document.getElementById('errorMessage');
    
    if (type === 'success') {
        successEl.textContent = '✅ ' + message;
        successEl.classList.remove('hidden');
        errorEl.classList.add('hidden');
        setTimeout(() => successEl.classList.add('hidden'), 5000);
    } else {
        errorEl.textContent = '❌ ' + message;
        errorEl.classList.remove('hidden');
        successEl.classList.add('hidden');
        setTimeout(() => errorEl.classList.add('hidden'), 5000);
    }
}

function loadEstates() {
    fetch('/security/estates', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.estates) {
            const select = document.getElementById('estate_id');
            select.innerHTML = '<option value="">Select Estate</option>';
            data.estates.forEach(estate => {
                const option = document.createElement('option');
                option.value = estate.id;
                option.textContent = estate.name;
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading estates:', error);
        showMessage('Failed to load estates', 'error');
    });
}

function loadUnits(estateId) {
    const unitSelect = document.getElementById('unit_id');
    const noUnitsMsg = document.getElementById('noUnitsMsg');
    
    if (!estateId) {
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        noUnitsMsg.classList.add('hidden');
        return;
    }
    
    unitSelect.innerHTML = '<option value="">Loading units...</option>';
    noUnitsMsg.classList.add('hidden');
    
    fetch(`/security/units?estate_id=${estateId}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.units) {
            unitSelect.innerHTML = '<option value="">Select Unit</option>';
            if (data.units.length === 0) {
                noUnitsMsg.classList.remove('hidden');
            } else {
                data.units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.unit_number;
                    unitSelect.appendChild(option);
                });
            }
        }
    })
    .catch(error => {
        console.error('Error loading units:', error);
        unitSelect.innerHTML = '<option value="">Error loading units</option>';
        showMessage('Failed to load units', 'error');
    });
}

// 🔍 SEARCH VISITOR BY PHONE OR ID
function searchVisitor() {
    const searchTerm = document.getElementById('searchVisitor').value.trim();
    const resultDiv = document.getElementById('searchResult');
    
    if (!searchTerm) {
        showMessage('Please enter a phone number or ID to search', 'error');
        return;
    }
    
    // Show loading
    resultDiv.classList.remove('hidden');
    resultDiv.innerHTML = 'Searching...';
    resultDiv.className = 'mt-2 rounded-lg border border-blue-200 bg-blue-50 p-2 text-sm text-blue-700';
    
    fetch(`/security/visitors/search?q=${encodeURIComponent(searchTerm)}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.visitor) {
            const visitor = data.visitor;
            
            // Auto-populate form
            document.getElementById('name').value = visitor.name;
            document.getElementById('phone').value = visitor.phone;
            document.getElementById('visitor_id').value = visitor.id;
            
            // Show success
            resultDiv.innerHTML = `✅ Visitor found: <strong>${visitor.name}</strong> (${visitor.phone}) - ${visitor.visitor_type_label || 'Visitor'}`;
            resultDiv.className = 'mt-2 rounded-lg border border-green-200 bg-green-50 p-2 text-sm text-green-700';
            
            showMessage('Visitor found! Form auto-populated.', 'success');
            
        } else {
            resultDiv.innerHTML = '❌ No visitor found with that phone or ID. Please fill in the form manually.';
            resultDiv.className = 'mt-2 rounded-lg border border-yellow-200 bg-yellow-50 p-2 text-sm text-yellow-700';
            document.getElementById('visitor_id').value = '';
        }
    })
    .catch(error => {
        console.error('Error searching visitor:', error);
        resultDiv.innerHTML = '❌ Error searching. Please try again.';
        resultDiv.className = 'mt-2 rounded-lg border border-red-200 bg-red-50 p-2 text-sm text-red-700';
    });
}

function clearSearch() {
    document.getElementById('searchVisitor').value = '';
    document.getElementById('searchResult').classList.add('hidden');
    document.getElementById('visitor_id').value = '';
}

function submitForm() {
    const formData = {
        name: document.getElementById('name').value,
        phone: document.getElementById('phone').value,
        unit_id: document.getElementById('unit_id').value,
        purpose: document.getElementById('purpose').value,
        vehicle_registration: document.getElementById('vehicle_registration').value || null,
        vehicle_type: document.getElementById('vehicle_type').value || null,
        vehicle_model: document.getElementById('vehicle_model').value || null,
        vehicle_color: document.getElementById('vehicle_color').value || null,
        visitor_id: document.getElementById('visitor_id').value || null,
    };
    
    // Validate
    if (!formData.unit_id) {
        showMessage('Please select a unit', 'error');
        return;
    }
    if (!formData.name) {
        showMessage('Please enter visitor name', 'error');
        return;
    }
    if (!formData.phone) {
        showMessage('Please enter phone number', 'error');
        return;
    }
    if (!formData.purpose) {
        showMessage('Please select a purpose', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    submitBtn.disabled = true;
    btnText.textContent = 'Processing...';
    btnSpinner.classList.remove('hidden');
    
    fetch('/security/quick-entry', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        btnText.textContent = 'Check In';
        btnSpinner.classList.add('hidden');
        
        if (data.success) {
            showMessage(data.message, 'success');
            document.getElementById('quickEntryForm').reset();
            document.getElementById('unit_id').innerHTML = '<option value="">Select Unit</option>';
            document.getElementById('visitor_id').value = '';
            document.getElementById('searchResult').classList.add('hidden');
            setTimeout(() => {
                window.location.href = '/security';
            }, 1500);
        } else {
            showMessage(data.message || 'Failed to check in visitor', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitBtn.disabled = false;
        btnText.textContent = 'Check In';
        btnSpinner.classList.add('hidden');
        showMessage('An error occurred. Please try again.', 'error');
    });
}
</script>
@endsection