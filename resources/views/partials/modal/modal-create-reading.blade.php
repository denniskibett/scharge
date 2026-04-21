<!-- Create Water Reading Modal -->
<div id="createReadingModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <!-- Modal Panel -->
        <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">New Water Meter Reading</h3>
                <button onclick="closeCreateReadingModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form id="createReadingForm" class="p-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Unit</label>
                    <select id="unit_id" name="unit_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                        <option value="">Select Unit</option>
                        @foreach($units ?? [] as $unit)
                        <option value="{{ $unit['id'] }}">{{ $unit['unit_number'] }} - {{ $unit['estate_name'] }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Previous Reading (m³)</label>
                    <input type="number" id="previous_reading" name="previous_reading" step="0.01" readonly class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-700">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Reading (m³)</label>
                    <input type="number" id="current_reading" name="current_reading" step="0.01" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                </div>
                
                <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-400">
                        Consumption: <span id="consumption_display">0.00</span> m³
                    </p>
                </div>
                
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeCreateReadingModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600">
                        Save Reading
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateReadingModal() {
    document.getElementById('createReadingModal').classList.remove('hidden');
}

function closeCreateReadingModal() {
    document.getElementById('createReadingModal').classList.add('hidden');
    document.getElementById('createReadingForm').reset();
}

document.getElementById('unit_id')?.addEventListener('change', async function() {
    const unitId = this.value;
    if (unitId) {
        const response = await fetch(`/units/${unitId}/last-reading`);
        const data = await response.json();
        document.getElementById('previous_reading').value = data.previous_reading || 0;
    }
});

document.getElementById('current_reading')?.addEventListener('input', function() {
    const previous = parseFloat(document.getElementById('previous_reading').value) || 0;
    const current = parseFloat(this.value) || 0;
    const consumption = Math.max(0, current - previous);
    document.getElementById('consumption_display').innerText = consumption.toFixed(2);
});

document.getElementById('createReadingForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const response = await fetch('/meter-readings', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(Object.fromEntries(formData))
    });
    
    if (response.ok) {
        location.reload();
    } else {
        alert('Error saving reading');
    }
});
</script>