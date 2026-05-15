@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-4">
    <div class="bg-white rounded-2xl border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Water Meter Reading <span class="text-sm text-gray-500">(Bulk Mode Only)</span></h4>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('water.readings.bulk') }}" id="bulkReadingForm">
                @csrf
                <div class="mb-6 flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[180px]">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Select Estate</label>
                        <select name="estate" id="estateSelect" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                            <option value="all">All Estates</option>
                            @foreach($estates as $estate)
                                <option value="{{ $estate->id }}" {{ $estateId == $estate->id ? 'selected' : '' }}>
                                    {{ $estate->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Reading Month</label>
                        <input type="month" name="month" id="monthSelect" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" value="{{ $selectedMonth }}">
                    </div>
                    <div>
                        <button type="button" id="applyFilters" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Apply</button>
                    </div>
                </div>

                @if($unitsWithPrev->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3">Property</th>
                                <th class="px-4 py-3">Previous Reading (m³)</th>
                                <th class="px-4 py-3">Current Reading (m³) for {{ \Carbon\Carbon::parse($selectedMonth)->format('F Y') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unitsWithPrev as $unit)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $unit->unit_number }}</td>
                                <td class="px-4 py-3">{{ $unit->property_name }}<br><small class="text-gray-500">{{ $unit->estate_name }}</small></td>
                                <td class="px-4 py-3">{{ number_format($unit->previous_reading, 2) }} m³</td>
                                <td class="px-4 py-3">
                                    <input type="number" 
                                           name="readings[{{ $unit->id }}][current_reading]" 
                                           class="dark:bg-dark-900 w-32 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" 
                                           step="0.01" 
                                           min="0" 
                                           placeholder="Enter reading">
                                    <input type="hidden" name="readings[{{ $unit->id }}][unit_id]" value="{{ $unit->id }}">
                                    <input type="hidden" name="readings[{{ $unit->id }}][reading_date]" value="{{ $selectedMonth }}-01">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Save All Readings</button>
                    <a href="{{ route('dashboard') }}" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">Cancel</a>
                </div>
                @else
                <div class="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
                    No units need reading for the selected month and estate. All occupied units have a reading for {{ \Carbon\Carbon::parse($selectedMonth)->format('F Y') }}.
                </div>
                @endif
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('applyFilters')?.addEventListener('click', function() {
        const estate = document.getElementById('estateSelect').value;
        const month = document.getElementById('monthSelect').value;
        window.location.href = '{{ route("water.readings.bulk.form") }}?estate=' + estate + '&month=' + month;
    });
</script>
@endsection