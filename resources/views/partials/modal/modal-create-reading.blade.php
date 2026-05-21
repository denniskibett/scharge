<!-- Create Water Reading Modal - Slideover -->
<div id="createReadingModal" class="fixed inset-0 z-999999 hidden" style="isolation: isolate;" aria-labelledby="slideover-title" role="dialog" aria-modal="true">
    <!-- Backdrop with fade -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
    
    <!-- Slideover Panel - slides in from right -->
    <div class="fixed inset-y-0 right-0 max-w-full flex">
        <div class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 38rem; max-width: calc(100% - 2rem);" id="slideoverPanel">
            
            <div class="h-full flex flex-col bg-white dark:bg-gray-900 shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="slideover-title">
                            Water Meter Reading
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            <span id="modeDescription">Record water meter readings for units</span>
                        </p>
                    </div>
                    <button onclick="closeCreateReadingModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Mode Toggle - HIDE for meter_reader, SHOW for others -->
                <div id="modeToggleContainer" class="p-4 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                        <button type="button" onclick="setMode('single')" id="modeSingleBtn" class="mode-btn flex-1 rounded-md px-4 py-2 text-sm font-medium transition-all bg-white text-gray-800 shadow-sm dark:bg-gray-700 dark:text-white">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Single Unit
                        </button>
                        <button type="button" onclick="setMode('bulk')" id="modeBulkBtn" class="mode-btn flex-1 rounded-md px-4 py-2 text-sm font-medium transition-all text-gray-600 dark:text-gray-400">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Bulk Reading
                        </button>
                        <button type="button" onclick="setMode('multimonth')" id="modeMultiMonthBtn" class="mode-btn flex-1 rounded-md px-4 py-2 text-sm font-medium transition-all text-gray-600 dark:text-gray-400">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Multi-Month (Single Unit)
                        </button>
                    </div>
                </div>
                
                <!-- Form Content -->
                <form id="createReadingForm" class="flex-1 flex flex-col overflow-y-auto">
                    @csrf
                    <div id="formContent" class="flex-1 p-4 space-y-4"></div>
                    
                    <!-- Footer with buttons -->
                    <div class="border-t border-gray-200 dark:border-gray-800 p-4">
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeCreateReadingModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                Cancel
                            </button>
                            <button type="submit" id="submitBtn" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600">
                                Save Reading
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Check if user is meter_reader (role_id = 5 or role name = 'meter_reader')
const userRole = @json(auth()->user()->role->name ?? '');
const isMeterReader = userRole === 'meter_reader';

// Global variables
let currentMode = isMeterReader ? 'bulk' : 'single';
let currentSelectedUnit = null;
let allUnits = [];
let bulkSelectedUnits = [];
let currentPreviousReading = 0;
let currentRate = 50;
let currentBillingType = 'consumption';
let bulkMonthRange = [];
let bulkReadingsMatrix = [];

// Initialize modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    fetchUnits();
    
    // For meter readers, hide mode toggle and set to bulk
    if (isMeterReader) {
        const modeToggle = document.getElementById('modeToggleContainer');
        if (modeToggle) {
            modeToggle.style.display = 'none';
        }
        document.getElementById('modeDescription').innerText = 'Record water meter readings for multiple units - Enter readings for current month only';
    }
});

// Fetch units for the modal
async function fetchUnits() {
    try {
        const response = await fetch('/api/units/with-water-readings');
        const data = await response.json();
        
        if (data.units) {
            allUnits = data.units;
        } else if (Array.isArray(data)) {
            allUnits = data;
        } else {
            const select = document.getElementById('unit_id');
            if (select) {
                allUnits = Array.from(select.options).slice(1).map(opt => ({
                    id: opt.value,
                    unit_number: opt.text.split(' - ')[0],
                    estate_name: opt.dataset.estate,
                    estate_id: opt.dataset.estateId,
                    previous_water_reading: parseFloat(opt.dataset.previousReading) || 0,
                    current_water_reading: parseFloat(opt.dataset.currentReading) || 0,
                    water_billing_type: opt.dataset.waterBillingType || 'consumption',
                    water_rate: parseFloat(opt.dataset.rate) || 50,
                    custom_water_rate: parseFloat(opt.dataset.customRate) || null,
                    last_reading_date: opt.dataset.lastReadingDate || null
                }));
            }
        }
    } catch (error) {
        console.error('Error fetching units:', error);
    }
}

// Set mode and render appropriate form
function setMode(mode) {
    currentMode = mode;
    
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.classList.remove('bg-white', 'text-gray-800', 'shadow-sm', 'dark:bg-gray-700', 'dark:text-white');
        btn.classList.add('text-gray-600', 'dark:text-gray-400');
    });
    
    const activeBtn = document.getElementById(`mode${mode.charAt(0).toUpperCase() + mode.slice(1)}Btn`);
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-600', 'dark:text-gray-400');
        activeBtn.classList.add('bg-white', 'text-gray-800', 'shadow-sm', 'dark:bg-gray-700', 'dark:text-white');
    }
    
    currentSelectedUnit = null;
    bulkSelectedUnits = [];
    bulkMonthRange = [];
    bulkReadingsMatrix = [];
    
    renderForm();
}

function renderForm() {
    const container = document.getElementById('formContent');
    if (!container) return;
    
    if (currentMode === 'single') {
        container.innerHTML = renderSingleMode();
        attachSingleModeEvents();
    } else if (currentMode === 'bulk') {
        container.innerHTML = renderMeterReaderBulkMode();
        attachMeterReaderBulkEvents();
    } else if (currentMode === 'multimonth') {
        container.innerHTML = renderMultiMonthMode();
        attachMultiMonthEvents();
    }
}

// ==================== METER READER BULK MODE ====================
function renderMeterReaderBulkMode() {
    return `
        <!-- Estate Selection -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                Select Estate
            </label>
            <select id="bulkEstateId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                <option value="">All Estates</option>
                @foreach($estates ?? [] as $estate)
                <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                @endforeach
            </select>
        </div>
        
        <!-- Reading Month (current month by default) -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Reading Month
            </label>
            <input type="month" id="readingMonth" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800" value="{{ date('Y-m') }}">
            <p class="text-xs text-gray-500 mt-1">Select the month for these readings</p>
        </div>
        
        <!-- Notes -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Notes (Optional)
            </label>
            <textarea id="bulkNotes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800" placeholder="General notes for these readings..."></textarea>
        </div>
        
        <!-- Readings Table -->
        <div class="border border-gray-200 rounded-lg overflow-hidden dark:border-gray-700">
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                <h5 class="font-medium text-gray-800 dark:text-white/90">
                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Units Needing Reading
                </h5>
                <p class="text-xs text-gray-500 mt-1">Only units without a reading for the selected month are shown</p>
            </div>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <div id="bulkMatrixContainer" class="p-4">
                    <div class="text-center text-gray-500 py-8">
                        Select an estate to load units needing reading
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ==================== METER READER BULK EVENT HANDLERS ====================
function attachMeterReaderBulkEvents() {
    const estateSelect = document.getElementById('bulkEstateId');
    const readingMonth = document.getElementById('readingMonth');
    
    if (estateSelect) {
        estateSelect.addEventListener('change', loadMeterReaderMatrix);
    }
    if (readingMonth) {
        readingMonth.addEventListener('change', loadMeterReaderMatrix);
    }
    
    // Load initial matrix
    loadMeterReaderMatrix();
}

async function loadMeterReaderMatrix() {
    const estateId = document.getElementById('bulkEstateId').value;
    const readingMonth = document.getElementById('readingMonth').value;
    
    if (!readingMonth) return;
    
    // Generate single month
    const month = {
        value: readingMonth,
        label: new Date(readingMonth + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'short' })
    };
    
    bulkMonthRange = [month];
    
    // Filter units by estate
    let units = [...allUnits];
    if (estateId) {
        units = units.filter(u => u.estate_id == estateId);
    }
    
    if (units.length === 0) {
        document.getElementById('bulkMatrixContainer').innerHTML = `
            <div class="text-center text-gray-500 py-8">
                No units found for the selected estate.
            </div>
        `;
        return;
    }
    
    // FETCH EXISTING READINGS FOR THIS MONTH
    const unitIds = units.map(u => u.id).join(',');
    let existingReadings = {};
    
    try {
        const response = await fetch(`/water/api/water/readings/bulk?unit_ids=${unitIds}&start_month=${readingMonth}&end_month=${readingMonth}`);
        const data = await response.json();
        
        if (data.success && data.readings) {
            data.readings.forEach(reading => {
                existingReadings[reading.unit_id] = reading;
            });
        }
    } catch (error) {
        console.error('Error fetching existing readings:', error);
    }
    
    // Filter out units that ALREADY HAVE a reading for this month
    const unitsNeedingReading = units.filter(unit => !existingReadings[unit.id]);
    
    if (unitsNeedingReading.length === 0) {
        document.getElementById('bulkMatrixContainer').innerHTML = `
            <div class="text-center text-green-500 py-8">
                <svg class="inline w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p>All units have been recorded for ${month.label}!</p>
                <p class="text-sm mt-1">Great job! No pending readings for this month.</p>
            </div>
        `;
        return;
    }
    
    // Initialize readings matrix for units needing reading
    bulkReadingsMatrix = [];
    unitsNeedingReading.forEach(unit => {
        const unitReadings = [];
        
        // Get the previous reading (last recorded reading)
        let previousReadingValue = unit.current_water_reading || 0;
        
        unitReadings.push({
            month: month.value,
            monthLabel: month.label,
            reading: 0,
            previousReading: previousReadingValue,
            consumption: 0,
            charge: 0,
            exists: false,
            readingId: null,
            modified: false
        });
        
        bulkReadingsMatrix.push({
            unitId: unit.id,
            unitNumber: unit.unit_number,
            estateName: unit.estate_name,
            waterRate: unit.custom_water_rate || unit.water_rate || 50,
            billingType: unit.water_billing_type || 'consumption',
            flatRate: unit.water_charge || 0,
            initialReading: unit.current_water_reading || 0,
            readings: unitReadings
        });
    });
    
    renderMeterReaderMatrix();
}

function renderMeterReaderMatrix() {
    const container = document.getElementById('bulkMatrixContainer');
    if (!container) return;
    
    if (bulkReadingsMatrix.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                No data to display. Please select an estate.
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="sticky top-0 bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="border border-gray-300 dark:border-gray-700 px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 sticky left-0 bg-gray-100 dark:bg-gray-800 z-10">
                            Unit
                        </th>
                        <th class="border border-gray-300 dark:border-gray-700 px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 min-w-[200px]">
                            ${bulkMonthRange[0].label}
                            <span class="block text-xs text-gray-400">Current Reading (m³)</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    bulkReadingsMatrix.forEach(unitData => {
        const reading = unitData.readings[0];
        const displayValue = reading.reading === 0 ? '' : reading.reading.toFixed(2);
        
        html += `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-800 dark:text-white/90 sticky left-0 bg-white dark:bg-gray-900 z-10">
                    ${unitData.unitNumber}
                    <span class="block text-xs text-gray-500">${unitData.estateName}</span>
                    <span class="block text-xs text-blue-500 mt-1">Prev: ${reading.previousReading.toFixed(2)} m³</span>
                </td>
                <td class="border border-gray-300 dark:border-gray-700 px-2 py-1">
                    <div class="flex flex-col space-y-1">
                        <input type="number" 
                            step="0.01" 
                            value="${displayValue}"
                            placeholder="Enter reading"
                            data-unit-id="${unitData.unitId}"
                            data-month="${reading.month}"
                            data-idx="0"
                            onchange="updateMeterReaderReading(this)"
                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800"
                        >
                        ${reading.reading > 0 ? `
                        <div class="text-xs text-gray-500 flex justify-between">
                            <span class="text-green-600">Cons: ${reading.consumption.toFixed(2)} m³</span>
                            <span class="text-blue-600">KES ${reading.charge.toFixed(0)}</span>
                        </div>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">
            <p>
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <strong>Note:</strong> Previous reading shown for reference. Enter current meter reading for ${bulkMonthRange[0].label}.
            </p>
        </div>
    `;
    
    container.innerHTML = html;
}

function updateMeterReaderReading(input) {
    const unitId = parseInt(input.dataset.unitId);
    const month = input.dataset.month;
    const newReading = parseFloat(input.value) || 0;
    
    const unitIndex = bulkReadingsMatrix.findIndex(u => u.unitId === unitId);
    if (unitIndex === -1) return;
    
    const unit = bulkReadingsMatrix[unitIndex];
    const reading = unit.readings[0];
    const previousReading = reading.previousReading;
    
    if (newReading < previousReading && previousReading > 0) {
        showToast(`Reading cannot be less than previous reading (${previousReading.toFixed(2)})`, 'error');
        input.value = '';
        return;
    }
    
    const consumption = newReading - previousReading;
    const rate = unit.waterRate;
    
    let charge = 0;
    if (unit.billingType === 'flat') {
        charge = unit.flatRate;
        reading.consumption = 0;
    } else {
        reading.consumption = consumption > 0 ? consumption : 0;
        charge = reading.consumption * rate;
    }
    
    reading.reading = newReading;
    reading.charge = charge;
    reading.modified = true;
    
    // Update the display
    const cell = input.closest('td');
    const displayValue = reading.reading === 0 ? '' : reading.reading.toFixed(2);
    
    cell.innerHTML = `
        <div class="flex flex-col space-y-1">
            <input type="number" 
                step="0.01" 
                value="${displayValue}"
                placeholder="Enter reading"
                data-unit-id="${unit.unitId}"
                data-month="${reading.month}"
                data-idx="0"
                onchange="updateMeterReaderReading(this)"
                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800"
            >
            ${reading.reading > 0 ? `
            <div class="text-xs text-gray-500 flex justify-between">
                <span class="text-green-600">Cons: ${reading.consumption.toFixed(2)} m³</span>
                <span class="text-blue-600">KES ${reading.charge.toFixed(0)}</span>
            </div>
            ` : ''}
        </div>
    `;
}

// ==================== BULK MODE EVENT HANDLERS (for non-meter-readers) ====================
function attachNewBulkModeEvents() {
    const estateSelect = document.getElementById('bulkEstateId');
    const startMonth = document.getElementById('bulkStartMonth');
    const endMonth = document.getElementById('bulkEndMonth');
    const distributionMethod = document.getElementById('bulkDistributionMethod');
    
    const today = new Date();
    const sixMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 5, 1);
    
    if (startMonth && !startMonth.value) {
        startMonth.value = sixMonthsAgo.toISOString().slice(0, 7);
    }
    if (endMonth && !endMonth.value) {
        endMonth.value = today.toISOString().slice(0, 7);
    }
    
    if (distributionMethod) {
        distributionMethod.addEventListener('change', toggleBulkAutoSettings);
        toggleBulkAutoSettings();
    }
    
    if (estateSelect) estateSelect.addEventListener('change', loadBulkMatrix);
    if (startMonth) startMonth.addEventListener('change', loadBulkMatrix);
    if (endMonth) endMonth.addEventListener('change', loadBulkMatrix);
    
    loadBulkMatrix();
}

function toggleBulkAutoSettings() {
    const method = document.getElementById('bulkDistributionMethod').value;
    const incrementSettings = document.getElementById('autoIncrementSettings');
    const percentageSettings = document.getElementById('autoPercentageSettings');
    
    if (method === 'auto_increment') {
        incrementSettings.classList.remove('hidden');
        percentageSettings.classList.add('hidden');
    } else if (method === 'auto_percentage') {
        incrementSettings.classList.add('hidden');
        percentageSettings.classList.remove('hidden');
    } else {
        incrementSettings.classList.add('hidden');
        percentageSettings.classList.add('hidden');
    }
}

async function loadBulkMatrix() {
    const estateId = document.getElementById('bulkEstateId').value;
    const startMonth = document.getElementById('bulkStartMonth').value;
    const endMonth = document.getElementById('bulkEndMonth').value;
    
    if (!startMonth || !endMonth) return;
    
    const months = [];
    const start = new Date(startMonth + '-01');
    const end = new Date(endMonth + '-01');
    const current = new Date(start);
    
    while (current <= end) {
        const year = current.getFullYear();
        const month = String(current.getMonth() + 1).padStart(2, '0');
        months.push({
            value: `${year}-${month}`,
            label: current.toLocaleDateString('en-US', { year: 'numeric', month: 'short' })
        });
        current.setMonth(current.getMonth() + 1);
    }
    
    bulkMonthRange = months;
    
    let units = [...allUnits];
    if (estateId) {
        units = units.filter(u => u.estate_id == estateId);
    }
    
    if (units.length === 0) {
        document.getElementById('bulkMatrixContainer').innerHTML = `
            <div class="text-center text-gray-500 py-8">
                No units found for the selected estate.
            </div>
        `;
        return;
    }
    
    const unitIds = units.map(u => u.id).join(',');
    let existingReadings = {};
    
    try {
        const response = await fetch(`/water/api/water/readings/bulk?unit_ids=${unitIds}&start_month=${startMonth}&end_month=${endMonth}`);
        const data = await response.json();
        
        if (data.success && data.readings) {
            data.readings.forEach(reading => {
                if (!existingReadings[reading.unit_id]) {
                    existingReadings[reading.unit_id] = {};
                }
                existingReadings[reading.unit_id][reading.month] = reading;
            });
        }
    } catch (error) {
        console.error('Error fetching existing readings:', error);
    }
    
    bulkReadingsMatrix = [];
    units.forEach(unit => {
        const unitReadings = [];
        
        months.forEach((month, idx) => {
            const existingReading = existingReadings[unit.id]?.[month.value];
            
            let readingValue = 0;
            let exists = !!existingReading;
            let readingId = null;
            
            if (existingReading) {
                readingValue = existingReading.current_reading;
                readingId = existingReading.id;
            }
            
            unitReadings.push({
                month: month.value,
                monthLabel: month.label,
                reading: readingValue,
                previousReading: 0,
                consumption: 0,
                charge: 0,
                exists: exists,
                readingId: readingId,
                modified: false
            });
        });
        
        let previousValue = unit.current_water_reading || 0;
        for (let idx = 0; idx < unitReadings.length; idx++) {
            if (idx === 0) {
                unitReadings[idx].previousReading = previousValue;
            } else {
                if (unitReadings[idx].reading > 0) {
                    unitReadings[idx].previousReading = unitReadings[idx - 1].reading;
                } else {
                    unitReadings[idx].previousReading = unitReadings[idx - 1].reading || previousValue;
                }
            }
            
            if (unitReadings[idx].reading > 0) {
                const rate = unit.custom_water_rate || unit.water_rate || 50;
                const consumption = unitReadings[idx].reading - unitReadings[idx].previousReading;
                unitReadings[idx].consumption = consumption > 0 ? consumption : 0;
                
                if (unit.water_billing_type === 'flat') {
                    unitReadings[idx].charge = unit.water_charge || 0;
                    unitReadings[idx].consumption = 0;
                } else {
                    unitReadings[idx].charge = unitReadings[idx].consumption * rate;
                }
            }
        }
        
        bulkReadingsMatrix.push({
            unitId: unit.id,
            unitNumber: unit.unit_number,
            estateName: unit.estate_name,
            waterRate: unit.custom_water_rate || unit.water_rate || 50,
            billingType: unit.water_billing_type || 'consumption',
            flatRate: unit.water_charge || 0,
            initialReading: unit.current_water_reading || 0,
            readings: unitReadings
        });
    });
    
    renderBulkMatrix();
}

function renderBulkMatrix() {
    const container = document.getElementById('bulkMatrixContainer');
    if (!container) return;
    
    if (bulkReadingsMatrix.length === 0 || bulkMonthRange.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                No data to display. Please select an estate and month range.
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="sticky top-0 bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="border border-gray-300 dark:border-gray-700 px-3 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 sticky left-0 bg-gray-100 dark:bg-gray-800 z-10">
                            Unit
                        </th>
                        ${bulkMonthRange.map(month => `
                            <th class="border border-gray-300 dark:border-gray-700 px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 min-w-[100px]">
                                ${month.label}
                                <span class="block text-xs text-gray-400">Reading (m³)</span>
                            </th>
                        `).join('')}
                    </tr>
                </thead>
                <tbody>
    `;
    
    bulkReadingsMatrix.forEach(unitData => {
        html += `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-800 dark:text-white/90 sticky left-0 bg-white dark:bg-gray-900 z-10">
                    ${unitData.unitNumber}
                    <span class="block text-xs text-gray-500">${unitData.estateName}</span>
                </td>
        `;
        
        unitData.readings.forEach((reading, idx) => {
            const existingClass = reading.exists ? 'bg-yellow-50 dark:bg-yellow-900/20' : '';
            const displayValue = reading.reading === 0 && !reading.exists ? '' : reading.reading.toFixed(2);
            
            html += `
                <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 ${existingClass}">
                    <div class="flex flex-col space-y-1">
                        <input type="number" 
                            step="0.01" 
                            value="${displayValue}"
                            placeholder="Enter reading"
                            data-unit-id="${unitData.unitId}"
                            data-month="${reading.month}"
                            data-idx="${idx}"
                            onchange="updateMatrixReading(this)"
                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800"
                        >
                        ${reading.reading > 0 && !reading.exists ? `
                        <div class="text-xs text-gray-500 flex justify-between">
                            <span>Prev: ${reading.previousReading.toFixed(2)}</span>
                            <span class="text-green-600">Cons: ${Math.max(0, reading.consumption).toFixed(2)}</span>
                            <span class="text-blue-600">KES ${reading.charge.toFixed(0)}</span>
                        </div>
                        ` : ''}
                        ${reading.exists ? '<span class="text-xs text-yellow-600">Existing (will update)</span>' : ''}
                    </div>
                </td>
            `;
        });
        
        html += `</tr>`;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-sm">
            <p>
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <strong>Note:</strong> Empty cells require manual entry. Yellow highlighted cells are existing readings that will be updated.
            </p>
        </div>
    `;
    
    container.innerHTML = html;
}

function updateMatrixReading(input) {
    const unitId = parseInt(input.dataset.unitId);
    const month = input.dataset.month;
    const newReading = parseFloat(input.value) || 0;
    const readingIndex = parseInt(input.dataset.idx);
    
    const unitIndex = bulkReadingsMatrix.findIndex(u => u.unitId === unitId);
    if (unitIndex === -1) return;
    
    const unit = bulkReadingsMatrix[unitIndex];
    const reading = unit.readings[readingIndex];
    const previousReading = readingIndex === 0 
        ? (unit.initialReading || 0) 
        : unit.readings[readingIndex - 1].reading;
    
    if (newReading < previousReading && previousReading > 0) {
        showToast(`Reading cannot be less than previous reading (${previousReading.toFixed(2)})`, 'error');
        input.value = reading.reading === 0 ? '' : reading.reading.toFixed(2);
        return;
    }
    
    const consumption = newReading - previousReading;
    const rate = unit.waterRate;
    
    let charge = 0;
    if (unit.billingType === 'flat') {
        charge = unit.flatRate;
    } else {
        charge = (consumption > 0 ? consumption : 0) * rate;
    }
    
    const wasExisting = reading.exists;
    reading.reading = newReading;
    reading.consumption = consumption > 0 ? consumption : 0;
    reading.charge = charge;
    reading.modified = true;
    
    const cell = input.closest('td');
    const displayValue = reading.reading === 0 ? '' : reading.reading.toFixed(2);
    
    cell.innerHTML = `
        <div class="flex flex-col space-y-1">
            <input type="number" 
                step="0.01" 
                value="${displayValue}"
                placeholder="Enter reading"
                data-unit-id="${unit.unitId}"
                data-month="${reading.month}"
                data-idx="${readingIndex}"
                onchange="updateMatrixReading(this)"
                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800"
            >
            ${reading.reading > 0 ? `
            <div class="text-xs text-gray-500 flex justify-between">
                <span>Prev: ${previousReading.toFixed(2)}</span>
                <span class="text-green-600">Cons: ${reading.consumption.toFixed(2)}</span>
                <span class="text-blue-600">KES ${reading.charge.toFixed(0)}</span>
            </div>
            ` : ''}
            ${wasExisting ? '<span class="text-xs text-yellow-600">Existing (will update)</span>' : ''}
        </div>
    `;
    
    if (readingIndex + 1 < unit.readings.length) {
        const nextReading = unit.readings[readingIndex + 1];
        if (nextReading.reading > 0) {
            const nextPreviousReading = newReading;
            const nextConsumption = nextReading.reading - nextPreviousReading;
            if (unit.billingType !== 'flat') {
                nextReading.consumption = nextConsumption > 0 ? nextConsumption : 0;
                nextReading.charge = nextReading.consumption * unit.waterRate;
            }
            
            const nextInput = document.querySelector(`input[data-unit-id="${unit.unitId}"][data-month="${nextReading.month}"]`);
            if (nextInput) {
                const nextCell = nextInput.closest('td');
                if (nextCell) {
                    const nextDisplayValue = nextReading.reading === 0 ? '' : nextReading.reading.toFixed(2);
                    nextCell.innerHTML = `
                        <div class="flex flex-col space-y-1">
                            <input type="number" 
                                step="0.01" 
                                value="${nextDisplayValue}"
                                placeholder="Enter reading"
                                data-unit-id="${unit.unitId}"
                                data-month="${nextReading.month}"
                                data-idx="${readingIndex + 1}"
                                onchange="updateMatrixReading(this)"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800"
                            >
                            ${nextReading.reading > 0 ? `
                            <div class="text-xs text-gray-500 flex justify-between">
                                <span>Prev: ${nextPreviousReading.toFixed(2)}</span>
                                <span class="text-green-600">Cons: ${nextReading.consumption.toFixed(2)}</span>
                                <span class="text-blue-600">KES ${nextReading.charge.toFixed(0)}</span>
                            </div>
                            ` : ''}
                            ${nextReading.exists ? '<span class="text-xs text-yellow-600">Existing (will update)</span>' : ''}
                        </div>
                    `;
                }
            }
        }
    }
}

function applyAutoIncrementToMatrix() {
    const method = document.getElementById('bulkDistributionMethod').value;
    if (method !== 'auto_increment') return;
    
    const startReading = parseFloat(document.getElementById('autoStartReading').value) || 0;
    const increment = parseFloat(document.getElementById('autoIncrement').value) || 0;
    
    bulkReadingsMatrix.forEach(unit => {
        let currentReading = startReading;
        unit.readings.forEach((reading, idx) => {
            reading.reading = idx === 0 ? startReading : currentReading + increment;
            currentReading = reading.reading;
            reading.consumption = reading.reading - reading.previousReading;
            if (reading.consumption < 0) reading.consumption = 0;
            reading.charge = reading.consumption * unit.waterRate;
        });
    });
    
    renderBulkMatrix();
}

function applyAutoPercentageToMatrix() {
    const method = document.getElementById('bulkDistributionMethod').value;
    if (method !== 'auto_percentage') return;
    
    const startReading = parseFloat(document.getElementById('percentStartReading').value) || 0;
    const percentage = parseFloat(document.getElementById('percentIncrease').value) || 0;
    
    bulkReadingsMatrix.forEach(unit => {
        let currentReading = startReading;
        unit.readings.forEach((reading, idx) => {
            reading.reading = idx === 0 ? startReading : currentReading * (1 + percentage / 100);
            currentReading = reading.reading;
            reading.consumption = reading.reading - reading.previousReading;
            if (reading.consumption < 0) reading.consumption = 0;
            reading.charge = reading.consumption * unit.waterRate;
        });
    });
    
    renderBulkMatrix();
}

// ==================== SINGLE MODE EVENT HANDLERS ====================
function renderSingleMode() {
    return `
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                Select Unit
            </label>
            <select id="unit_id" name="unit_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                <option value="">Select Unit</option>
                @foreach($units ?? [] as $unit)
                <option value="{{ $unit['id'] }}" 
                    data-estate="{{ $unit['estate_name'] }}" 
                    data-rate="{{ $unit['custom_water_rate'] ?? ($unit['water_charge'] ?? 50) }}" 
                    data-water-billing-type="{{ $unit['water_billing_type'] ?? 'consumption' }}"
                    data-flat-rate="{{ $unit['water_charge'] ?? 0 }}"
                    data-previous-reading="{{ $unit['current_water_reading'] ?? 0 }}"
                    data-last-reading-date="{{ $unit['last_reading_date'] ?? '' }}">
                    {{ $unit['unit_number'] }} - {{ $unit['estate_name'] }} ({{ $unit['unit_type'] ?? 'N/A' }})
                </option>
                @endforeach
            </select>
        </div>
        
        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Billing Type:
                </span>
                <span id="billing_type_badge" class="px-2 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Select Unit</span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span id="billing_note">Choose a unit to see billing type</span>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                Current Reading (m³)
            </label>
            <input type="number" id="current_reading" name="current_reading" step="0.01" required 
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs text-gray-500 mt-1">
                <svg class="inline w-3 h-3 text-red-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                Must be greater than or equal to previous reading
            </p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Reading Date
            </label>
            <input type="date" id="reading_date" name="reading_date" required 
                value="{{ date('Y-m-d') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Notes (Optional)
            </label>
            <textarea id="notes" name="notes" rows="2" 
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800"
                placeholder="Any additional notes about this reading..."></textarea>
        </div>
        
        <div id="previous_reading_info" class="hidden bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
            <p class="text-sm text-blue-800 dark:text-blue-400 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Previous Reading: <span id="previous_reading_display" class="font-semibold">0.00</span> m³
            </p>
            <p class="text-xs text-blue-700 dark:text-blue-300">
                <svg class="inline w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Last reading date: <span id="last_reading_date_display">Not available</span>
            </p>
        </div>
        
        <div id="calculation_results" class="hidden bg-green-50 dark:bg-green-900/20 p-3 rounded-lg space-y-1">
            <p class="text-sm text-green-800 dark:text-green-400">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                </svg>
                Consumption: <span id="consumption_display" class="font-semibold">0.00</span> m³
            </p>
            <p class="text-sm text-green-800 dark:text-green-400">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Rate: KES <span id="rate_display">0.00</span> / m³
            </p>
            <p class="text-sm text-green-800 dark:text-green-400">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Total Charge: KES <span id="charge_display" class="font-semibold">0.00</span>
            </p>
        </div>
    `;
}

function attachSingleModeEvents() {
    const unitSelect = document.getElementById('unit_id');
    const currentReading = document.getElementById('current_reading');
    const readingDate = document.getElementById('reading_date');
    
    if (unitSelect) {
        unitSelect.addEventListener('change', handleUnitChange);
    }
    if (currentReading) {
        currentReading.addEventListener('input', calculateReading);
    }
    
    if (readingDate && !readingDate.value) {
        readingDate.value = new Date().toISOString().split('T')[0];
    }
}

function attachMultiMonthEvents() {
    const unitSelect = document.getElementById('mm_unit_id');
    const startMonth = document.getElementById('start_month');
    const endMonth = document.getElementById('end_month');
    const startingReading = document.getElementById('starting_reading');
    const distributionMethod = document.getElementById('distribution_method');
    
    const today = new Date();
    const sixMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 5, 1);
    
    if (startMonth && !startMonth.value) {
        startMonth.value = sixMonthsAgo.toISOString().slice(0, 7);
    }
    if (endMonth && !endMonth.value) {
        endMonth.value = today.toISOString().slice(0, 7);
    }
    
    if (unitSelect) unitSelect.addEventListener('change', handleMultiMonthUnitChange);
    if (startMonth) startMonth.addEventListener('change', generateMonthRange);
    if (endMonth) endMonth.addEventListener('change', generateMonthRange);
    if (startingReading) startingReading.addEventListener('input', calculateMultiMonthReadings);
    if (distributionMethod) distributionMethod.addEventListener('change', () => {
        calculateMultiMonthReadings();
        toggleManualEditing();
    });
    
    if (unitSelect && unitSelect.value) handleMultiMonthUnitChange();
    else generateMonthRange();
}

function renderMultiMonthMode() {
    return `
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                Select Unit
            </label>
            <select id="mm_unit_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                <option value="">Select Unit</option>
                @foreach($units ?? [] as $unit)
                <option value="{{ $unit['id'] }}" 
                    data-rate="{{ $unit['custom_water_rate'] ?? ($unit['water_charge'] ?? 50) }}"
                    data-current-reading="{{ $unit['current_water_reading'] ?? 0 }}">
                    {{ $unit['unit_number'] }} - {{ $unit['estate_name'] }}
                </option>
                @endforeach
            </select>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Start Month
                </label>
                <input type="month" id="start_month" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    End Month
                </label>
                <input type="month" id="end_month" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                Starting Reading (m³)
            </label>
            <input type="number" id="starting_reading" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs text-gray-500 mt-1">Current reading: <span id="mm_current_reading_display">0.00</span> m³</p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Distribution Method
            </label>
            <select id="distribution_method" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                <option value="equal">Equal Distribution (Even split across months)</option>
                <option value="increasing">Increasing (Gradual increase each month)</option>
                <option value="decreasing">Decreasing (Gradual decrease each month)</option>
                <option value="manual">Manual Entry</option>
            </select>
        </div>
        
        <div class="border border-gray-200 rounded-lg overflow-hidden dark:border-gray-700">
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reading (m³)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consumption</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Est. Charge (KES)</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyReadingsTable" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                    <tfoot id="monthlyReadingsFooter" class="bg-gray-100 dark:bg-gray-800 font-semibold"></tfoot>
                </table>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Notes (Optional)
            </label>
            <textarea id="mm_notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800" placeholder="General notes for these readings..."></textarea>
        </div>
    `;
}

async function handleUnitChange() {
    const select = document.getElementById('unit_id');
    const selectedOption = select.options[select.selectedIndex];
    const unitId = select.value;
    
    if (unitId && selectedOption) {
        const billingType = selectedOption.dataset.waterBillingType || 'consumption';
        const rate = parseFloat(selectedOption.dataset.rate) || 50;
        const flatRate = parseFloat(selectedOption.dataset.flatRate) || 0;
        const previousReading = parseFloat(selectedOption.dataset.previousReading) || 0;
        const lastReadingDate = selectedOption.dataset.lastReadingDate || '';
        
        const billingTypeBadge = document.getElementById('billing_type_badge');
        const billingNote = document.getElementById('billing_note');
        
        if (billingType === 'flat') {
            billingTypeBadge.innerHTML = 'Flat Rate';
            billingTypeBadge.className = 'px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400';
            billingNote.innerHTML = `Water charge is a fixed amount of KES ${flatRate.toFixed(2)} regardless of consumption.`;
            document.getElementById('rate_display').innerText = flatRate.toFixed(2);
        } else {
            billingTypeBadge.innerHTML = 'Consumption Based';
            billingTypeBadge.className = 'px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
            billingNote.innerHTML = `Water charge calculated based on usage at KES ${rate.toFixed(2)} per m³.`;
            document.getElementById('rate_display').innerText = rate.toFixed(2);
        }
        
        const previousInfoDiv = document.getElementById('previous_reading_info');
        if (previousInfoDiv) {
            previousInfoDiv.classList.remove('hidden');
            document.getElementById('previous_reading_display').innerText = previousReading.toFixed(2);
            if (lastReadingDate) {
                document.getElementById('last_reading_date_display').innerText = new Date(lastReadingDate).toLocaleDateString();
            } else {
                document.getElementById('last_reading_date_display').innerText = 'First reading - no previous';
            }
        }
        
        window.currentPreviousReading = previousReading;
        window.currentBillingType = billingType;
        window.currentRate = billingType === 'flat' ? flatRate : rate;
        
        const currentReadingInput = document.getElementById('current_reading');
        if (currentReadingInput && currentReadingInput.value) calculateReading();
    } else {
        document.getElementById('previous_reading_info').classList.add('hidden');
        document.getElementById('calculation_results').classList.add('hidden');
        document.getElementById('billing_type_badge').innerHTML = 'Select Unit';
        document.getElementById('billing_type_badge').className = 'px-2 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
        document.getElementById('billing_note').innerHTML = 'Choose a unit to see billing type';
    }
}

function calculateReading() {
    const previous = window.currentPreviousReading || 0;
    const currentInput = document.getElementById('current_reading');
    const current = parseFloat(currentInput.value) || 0;
    
    if (current < previous) {
        currentInput.setCustomValidity('Current reading must be greater than or equal to previous reading');
        return;
    } else {
        currentInput.setCustomValidity('');
    }
    
    const consumption = Math.max(0, current - previous);
    let charge = 0;
    
    if (window.currentBillingType === 'flat') {
        charge = window.currentRate;
        document.getElementById('consumption_display').innerText = '0.00';
    } else {
        charge = consumption * window.currentRate;
        document.getElementById('consumption_display').innerText = consumption.toFixed(2);
    }
    
    document.getElementById('charge_display').innerText = charge.toFixed(2);
    document.getElementById('calculation_results').classList.remove('hidden');
}

async function handleMultiMonthUnitChange() {
    const select = document.getElementById('mm_unit_id');
    const selectedOption = select.options[select.selectedIndex];
    const currentReading = parseFloat(selectedOption?.dataset.currentReading) || 0;
    document.getElementById('mm_current_reading_display').innerText = currentReading.toFixed(2);
    generateMonthRange();
}

function generateMonthRange() {
    const startMonth = document.getElementById('start_month').value;
    const endMonth = document.getElementById('end_month').value;
    if (!startMonth || !endMonth) return;
    
    const start = new Date(startMonth + '-01');
    const end = new Date(endMonth + '-01');
    if (start > end) { alert('Start month must be before end month'); return; }
    
    const months = [];
    const current = new Date(start);
    while (current <= end) {
        const year = current.getFullYear();
        const month = String(current.getMonth() + 1).padStart(2, '0');
        months.push({ month: `${year}-${month}`, reading: 0, consumption: 0, estimated_charge: 0 });
        current.setMonth(current.getMonth() + 1);
    }
    window.monthlyReadingsData = months;
    calculateMultiMonthReadings();
}

function calculateMultiMonthReadings() {
    if (!window.monthlyReadingsData || window.monthlyReadingsData.length === 0) return;
    
    const select = document.getElementById('mm_unit_id');
    const selectedOption = select.options[select.selectedIndex];
    const currentReading = parseFloat(document.getElementById('mm_current_reading_display')?.innerText) || 0;
    const startingReading = parseFloat(document.getElementById('starting_reading').value) || 0;
    const totalConsumption = startingReading - currentReading;
    const rate = parseFloat(selectedOption?.dataset.rate) || 50;
    const distributionMethod = document.getElementById('distribution_method').value;
    const monthsCount = window.monthlyReadingsData.length;
    
    if (totalConsumption <= 0 || monthsCount === 0) {
        window.monthlyReadingsData.forEach(item => { item.consumption = 0; item.estimated_charge = 0; });
        renderMonthlyReadingsTable();
        return;
    }
    
    if (distributionMethod === 'equal') {
        const perMonth = totalConsumption / monthsCount;
        let cumulative = currentReading;
        window.monthlyReadingsData.forEach((item, idx) => { cumulative += perMonth; item.reading = cumulative; item.consumption = perMonth; item.estimated_charge = perMonth * rate; });
    } else if (distributionMethod === 'increasing') {
        const base = (totalConsumption * 2) / (monthsCount * (monthsCount + 1));
        let cumulative = currentReading;
        window.monthlyReadingsData.forEach((item, idx) => { const consumption = base * (idx + 1); cumulative += consumption; item.reading = cumulative; item.consumption = consumption; item.estimated_charge = consumption * rate; });
    } else if (distributionMethod === 'decreasing') {
        const base = (totalConsumption * 2) / (monthsCount * (monthsCount + 1));
        let cumulative = currentReading;
        window.monthlyReadingsData.forEach((item, idx) => { const consumption = base * (monthsCount - idx); cumulative += consumption; item.reading = cumulative; item.consumption = consumption; item.estimated_charge = consumption * rate; });
    }
    renderMonthlyReadingsTable();
}

function renderMonthlyReadingsTable() {
    const tbody = document.getElementById('monthlyReadingsTable');
    const tfoot = document.getElementById('monthlyReadingsFooter');
    if (!tbody || !window.monthlyReadingsData) return;
    
    const isManual = document.getElementById('distribution_method').value === 'manual';
    
    tbody.innerHTML = window.monthlyReadingsData.map((item, idx) => `
        <tr>
            <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white/90">${item.month}</td>
            <td class="px-4 py-3">
                <input type="number" step="0.01" value="${item.reading.toFixed(2)}" data-idx="${idx}" ${isManual ? 'onchange="updateManualReading(this)"' : 'readonly'} class="w-32 rounded-lg border border-gray-300 px-2 py-1 text-sm ${!isManual ? 'bg-gray-100 dark:bg-gray-800' : ''} focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700">
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">${item.consumption.toFixed(2)} m³</td>
            <td class="px-4 py-3 text-sm font-medium text-green-600 dark:text-green-400">KES ${item.estimated_charge.toFixed(2)}</td>
        </tr>
    `).join('');
    
    const totalConsumption = window.monthlyReadingsData.reduce((sum, i) => sum + i.consumption, 0);
    const totalCharge = window.monthlyReadingsData.reduce((sum, i) => sum + i.estimated_charge, 0);
    
    if (tfoot) {
        tfoot.innerHTML = `
            <tr>
                <td class="px-4 py-3 text-sm">Total</td>
                <td class="px-4 py-3 text-sm">-</td>
                <td class="px-4 py-3 text-sm">${totalConsumption.toFixed(2)} m³</td>
                <td class="px-4 py-3 text-sm text-green-700 dark:text-green-300">KES ${totalCharge.toFixed(2)}</td>
            </tr>
        `;
    }
}

function updateManualReading(input) {
    const idx = parseInt(input.dataset.idx);
    const newReading = parseFloat(input.value) || 0;
    
    if (idx >= 0 && window.monthlyReadingsData) {
        window.monthlyReadingsData[idx].reading = newReading;
        
        const select = document.getElementById('mm_unit_id');
        const selectedOption = select.options[select.selectedIndex];
        const currentReading = parseFloat(document.getElementById('mm_current_reading_display')?.innerText) || 0;
        const rate = parseFloat(selectedOption?.dataset.rate) || 50;
        
        let cumulative = currentReading;
        for (let i = 0; i <= idx; i++) cumulative = window.monthlyReadingsData[i].reading;
        
        for (let i = idx + 1; i < window.monthlyReadingsData.length; i++) {
            if (window.monthlyReadingsData[i].reading < cumulative) window.monthlyReadingsData[i].reading = cumulative;
            cumulative = window.monthlyReadingsData[i].reading;
        }
        
        let prevReading = currentReading;
        for (let i = 0; i < window.monthlyReadingsData.length; i++) {
            const consumption = window.monthlyReadingsData[i].reading - prevReading;
            window.monthlyReadingsData[i].consumption = consumption > 0 ? consumption : 0;
            window.monthlyReadingsData[i].estimated_charge = window.monthlyReadingsData[i].consumption * rate;
            prevReading = window.monthlyReadingsData[i].reading;
        }
        
        renderMonthlyReadingsTable();
    }
}

function toggleManualEditing() {
    const isManual = document.getElementById('distribution_method').value === 'manual';
    const readingInputs = document.querySelectorAll('#monthlyReadingsTable input');
    readingInputs.forEach(input => {
        if (isManual) {
            input.removeAttribute('readonly');
            input.classList.remove('bg-gray-100', 'dark:bg-gray-800');
        } else {
            input.setAttribute('readonly', 'readonly');
            input.classList.add('bg-gray-100', 'dark:bg-gray-800');
        }
    });
}

// ==================== FORM SUBMISSION ====================
document.getElementById('createReadingForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = 'Saving...';
    
    try {
        let response;
        
        if (currentMode === 'single') {
            const unitId = document.getElementById('unit_id').value;
            const currentReading = document.getElementById('current_reading').value;
            const readingDate = document.getElementById('reading_date').value;
            const notes = document.getElementById('notes').value;
            
            if (!unitId || !currentReading || !readingDate) {
                alert('Please fill in all required fields');
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
                return;
            }
            
            response = await fetch('/water/readings', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    unit_id: unitId,
                    current_reading: parseFloat(currentReading),
                    reading_date: readingDate,
                    notes: notes
                })
            });
        } 
        else if (currentMode === 'bulk') {
            if (bulkReadingsMatrix.length === 0) {
                alert('No units loaded. Please select an estate.');
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
                return;
            }
            
            const notes = document.getElementById('bulkNotes')?.value || '';
            let readingMonth;
            
            if (isMeterReader) {
                readingMonth = document.getElementById('readingMonth').value;
            } else {
                readingMonth = document.getElementById('bulkStartMonth')?.value;
            }
            
            const bulkData = [];
            for (const unit of bulkReadingsMatrix) {
                for (const reading of unit.readings) {
                    if (reading.reading > 0) {
                        const readingData = {
                            unit_id: unit.unitId,
                            current_reading: reading.reading,
                            reading_date: `${reading.month}-01`,
                            notes: notes
                        };
                        
                        if (reading.readingId) {
                            readingData.existing_reading_id = reading.readingId;
                        }
                        
                        bulkData.push(readingData);
                    }
                }
            }
            
            if (bulkData.length === 0) {
                alert('No readings entered. Please enter at least one reading.');
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
                return;
            }
            
            response = await fetch('/water/readings/bulk-matrix', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    readings: bulkData,
                    notes: notes
                })
            });
        } 
        else if (currentMode === 'multimonth') {
            const unitId = document.getElementById('mm_unit_id').value;
            const startMonth = document.getElementById('start_month').value;
            const endMonth = document.getElementById('end_month').value;
            const startingReading = document.getElementById('starting_reading').value;
            const distributionMethod = document.getElementById('distribution_method').value;
            const notes = document.getElementById('mm_notes').value;
            
            if (!unitId || !startMonth || !endMonth || !startingReading) {
                alert('Please fill in all required fields');
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
                return;
            }
            
            const monthlyData = window.monthlyReadingsData.map(item => ({
                reading_date: `${item.month}-01`,
                current_reading: item.reading,
                consumption: item.consumption,
                estimated_charge: item.estimated_charge
            }));
            
            response = await fetch(`/water/readings/multi-month`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    unit_id: unitId,
                    start_month: startMonth,
                    end_month: endMonth,
                    starting_reading: parseFloat(startingReading),
                    monthly_readings: monthlyData,
                    distribution_method: distributionMethod,
                    notes: notes
                })
            });
        }
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'Water reading(s) recorded successfully!', 'success');
            
            // For meter readers, reload the matrix to show remaining units
            if (isMeterReader && currentMode === 'bulk') {
                await loadMeterReaderMatrix();
                showToast('Reading saved! Units with readings will disappear from the list.', 'success');
            } else {
                closeCreateReadingModal();
                if (window.refreshReadingsTable) {
                    setTimeout(() => window.refreshReadingsTable(), 500);
                } else {
                    setTimeout(() => window.location.reload(), 500);
                }
            }
        } else {
            showToast(data.message || 'Error saving reading(s)', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = originalText;
    }
});

function showToast(message, type = 'success') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white flex items-center gap-2 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    toast.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            ${type === 'success' 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>'}
        </svg>
        ${message}
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

window.openCreateReadingModal = function(unitId = null) {
    const modal = document.getElementById('createReadingModal');
    const panel = document.getElementById('slideoverPanel');
    
    if (modal && panel) {
        if (isMeterReader) {
            setMode('bulk');
        } else if (unitId) {
            setMode('single');
        } else {
            setMode('single');
        }
        
        if (unitId && !isMeterReader) {
            setTimeout(() => {
                const unitSelect = document.getElementById('unit_id');
                if (unitSelect) {
                    unitSelect.value = unitId;
                    const event = new Event('change');
                    unitSelect.dispatchEvent(event);
                }
            }, 100);
        }
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            panel.classList.remove('translate-x-full');
            panel.classList.add('translate-x-0');
        }, 10);
        
        document.body.style.overflow = 'hidden';
    }
}

function closeCreateReadingModal() {
    const modal = document.getElementById('createReadingModal');
    const panel = document.getElementById('slideoverPanel');
    
    if (modal && panel) {
        panel.classList.remove('translate-x-0');
        panel.classList.add('translate-x-full');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }
}

document.getElementById('createReadingModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateReadingModal();
    }
});
</script>