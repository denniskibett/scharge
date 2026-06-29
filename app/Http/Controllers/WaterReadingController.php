<?php
// app/Http/Controllers/WaterReadingController.php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WaterReadingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get readings with unit and estate relationships
        $query = WaterReading::with(['unit.estate'])
            ->orderBy('reading_date', 'desc');
        
        // Filter based on user role
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $unitId = $user->tenant->activeTenancy->unit_id;
            $query->where('unit_id', $unitId);
        }
        
        $readings = $query->get();
        
        // Group readings by unit and prepare display data
        $groupedReadings = $readings->groupBy('unit_id')->map(function($unitReadings, $unitId) {
            $unit = $unitReadings->first()->unit;
            $sortedReadings = $unitReadings->sortByDesc('reading_date');
            $latestReading = $sortedReadings->first();
            
            // Get the previous reading (second most recent)
            $previousReading = $sortedReadings->count() > 1 ? $sortedReadings->skip(1)->first() : null;
            
            // Calculate gaps
            $gaps = [];
            $lastDate = null;
            $ascendingReadings = $unitReadings->sortBy('reading_date');
            foreach ($ascendingReadings as $reading) {
                if ($lastDate) {
                    $monthsDiff = Carbon::parse($lastDate)->diffInMonths(Carbon::parse($reading->reading_date));
                    if ($monthsDiff > 1) {
                        $gaps[] = [
                            'from' => Carbon::parse($lastDate)->format('Y-m'),
                            'to' => Carbon::parse($reading->reading_date)->format('Y-m'),
                            'months_missing' => $monthsDiff - 1
                        ];
                    }
                }
                $lastDate = $reading->reading_date;
            }
            
            $billingType = $unit->water_billing_type ?? 'consumption';
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            
            // Calculate consumption for the latest reading
            $consumption = $latestReading->consumption;
            if ($consumption == 0 && $billingType === 'consumption') {
                $consumption = $latestReading->current_reading - $latestReading->previous_reading;
            }
            
            $charge = $latestReading->charge;
            if ($charge == 0) {
                if ($billingType === 'flat') {
                    $charge = $unit->water_charge ?? 0;
                } else {
                    $charge = max(0, $consumption) * $rate;
                }
            }
            
            return [
                'id' => $latestReading->id,
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number ?? 'N/A',
                'estate_name' => $unit->estate->name ?? 'N/A',
                'previous_reading' => (float) ($previousReading ? $previousReading->current_reading : $unit->previous_water_reading ?? 0),
                'current_reading' => (float) $latestReading->current_reading,
                'consumption' => (float) max(0, $consumption),
                'charge' => (float) max(0, $charge),
                'reading_date' => $latestReading->reading_date->format('Y-m-d'),
                'last_reading_date' => $latestReading->reading_date->format('Y-m-d'),
                'rate' => (float) $rate,
                'water_billing_type' => $billingType,
                'total_readings' => $unitReadings->count(),
                'has_gaps' => count($gaps) > 0,
                'gaps' => $gaps,
                'needs_reading' => $unit->needsWaterReading(),
                'estate_id' => $unit->estate_id,
                'unit_type' => $unit->unit_type,
            ];
        })->values();
        
        // Sort by unit_number
        $groupedReadings = $groupedReadings->sortBy('unit_number')->values();
        
        // Get units list for the modal
        $units = Unit::with('estate')
            ->where('is_active', true)
            ->orderBy('unit_number')
            ->get()
            ->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'estate_id' => $unit->estate_id,
                    'unit_type' => $unit->unit_type,
                    'water_billing_type' => $unit->water_billing_type ?? 'consumption',
                    'water_charge' => (float) ($unit->water_charge ?? 0),
                    'custom_water_rate' => (float) ($unit->custom_water_rate ?? 0),
                    'current_water_reading' => (float) ($unit->current_water_reading ?? 0),
                    'previous_water_reading' => (float) ($unit->previous_water_reading ?? 0),
                    'last_reading_date' => $unit->last_reading_date,
                ];
            });
        
        // Get estates for the bulk mode dropdown
        $estates = \App\Models\Estate::orderBy('name')->get();
        
        return view('water.index', compact('groupedReadings', 'units', 'estates'));
    }

    public function getBulkReadings(Request $request)
    {
        $unitIds = explode(',', $request->get('unit_ids', ''));
        $startMonth = $request->get('start_month');
        $endMonth = $request->get('end_month');
        
        if (empty($unitIds) || !$startMonth || !$endMonth) {
            return response()->json(['success' => true, 'readings' => []]);
        }
        
        $startDate = Carbon::parse($startMonth . '-01')->startOfMonth();
        $endDate = Carbon::parse($endMonth . '-01')->endOfMonth();
        
        $readings = WaterReading::whereIn('unit_id', $unitIds)
            ->whereBetween('reading_date', [$startDate, $endDate])
            ->get()
            ->map(function($reading) {
                return [
                    'id' => $reading->id,
                    'unit_id' => $reading->unit_id,
                    'current_reading' => (float) $reading->current_reading,
                    'previous_reading' => (float) $reading->previous_reading,
                    'consumption' => (float) $reading->consumption,
                    'charge' => (float) $reading->charge,
                    'reading_date' => $reading->reading_date->format('Y-m-d'),
                    'month' => $reading->reading_date->format('Y-m')
                ];
            });
        
        return response()->json([
            'success' => true,
            'readings' => $readings
        ]);
    }

    public function getUnitReadingsForMonthRange(Request $request, $unitId)
    {
        $startMonth = $request->get('start_month');
        $endMonth = $request->get('end_month');
        $endBefore = $request->get('end_before');
        
        $query = WaterReading::where('unit_id', $unitId);
        
        if ($startMonth && $endMonth) {
            $startDate = Carbon::parse($startMonth . '-01')->startOfMonth();
            $endDate = Carbon::parse($endMonth . '-01')->endOfMonth();
            $query->whereBetween('reading_date', [$startDate, $endDate]);
        }
        
        if ($endBefore) {
            $query->where('reading_date', '<', $endBefore);
        }
        
        $readings = $query->orderBy('reading_date', 'asc')->get()->map(function($reading) {
            return [
                'id' => $reading->id,
                'unit_id' => $reading->unit_id,
                'current_reading' => (float) $reading->current_reading,
                'previous_reading' => (float) $reading->previous_reading,
                'consumption' => (float) $reading->consumption,
                'charge' => (float) $reading->charge,
                'reading_date' => $reading->reading_date->format('Y-m-d'),
            ];
        });
        
        return response()->json([
            'success' => true,
            'readings' => $readings
        ]);
    }

    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];
        
        try {
            foreach ($validated['unit_ids'] as $unitId) {
                $unit = Unit::find($unitId);
                if (!$unit) {
                    $results['failed'][] = ['unit_id' => $unitId, 'reason' => 'Unit not found'];
                    continue;
                }
                
                $readingDate = Carbon::parse($validated['reading_date']);
                
                // Check if reading already exists for this month
                $existingReading = WaterReading::where('unit_id', $unit->id)
                    ->whereYear('reading_date', $readingDate->year)
                    ->whereMonth('reading_date', $readingDate->month)
                    ->first();
                
                if ($existingReading) {
                    $results['skipped'][] = [
                        'unit_id' => $unitId,
                        'unit_number' => $unit->unit_number,
                        'reason' => 'Reading already exists for this month'
                    ];
                    continue;
                }
                
                // Get the most recent reading for this unit
                $latestReading = WaterReading::where('unit_id', $unit->id)
                    ->orderBy('reading_date', 'desc')
                    ->first();
                
                $previousReading = $latestReading 
                    ? $latestReading->current_reading 
                    : ($unit->current_water_reading ?? 0);
                
                $currentReading = $validated['current_reading'];
                
                if ($currentReading < $previousReading) {
                    $results['failed'][] = [
                        'unit_id' => $unitId,
                        'unit_number' => $unit->unit_number,
                        'reason' => 'Current reading less than previous'
                    ];
                    continue;
                }
                
                $consumption = $currentReading - $previousReading;
                $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                
                if ($unit->water_billing_type === 'flat') {
                    $charge = $unit->water_charge ?? 0;
                    $consumption = 0;
                } else {
                    $charge = $consumption * $rate;
                }
                
                // Create reading record
                WaterReading::create([
                    'unit_id' => $unit->id,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'consumption' => $consumption,
                    'rate_applied' => $rate,
                    'charge' => $charge,
                    'billing_type' => $unit->water_billing_type ?? 'consumption',
                    'reading_date' => $validated['reading_date'],
                    'recorded_by' => auth()->id(),
                    'notes' => $validated['notes'] ?? null
                ]);
                
                // CRITICAL: Update the units table with the last two readings
                $this->updateUnitWithLatestReadings($unit);
                
                $results['success'][] = [
                    'unit_id' => $unitId,
                    'unit_number' => $unit->unit_number
                ];
            }
            
            DB::commit();
            
            $message = count($results['success']) . ' reading(s) recorded successfully.';
            if (count($results['skipped']) > 0) {
                $message .= ' ' . count($results['skipped']) . ' skipped (already exist).';
            }
            if (count($results['failed']) > 0) {
                $message .= ' ' . count($results['failed']) . ' failed.';
            }
            
            return response()->json([
                'success' => count($results['success']) > 0,
                'message' => $message,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error saving bulk readings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record readings: ' . $e->getMessage()
            ], 500);
        }
    }

public function storeBulkMatrix(Request $request)
{
    $validated = $request->validate([
        'readings' => 'required|array|min:1',
        'readings.*.unit_id' => 'required|exists:units,id',
        'readings.*.current_reading' => 'required|numeric|min:0',
        'readings.*.reading_date' => 'required|date',
        'readings.*.existing_reading_id' => 'nullable|exists:water_readings,id',
        'notes' => 'nullable|string'
    ]);
    
    DB::beginTransaction();
    
    $results = [
        'success' => [],
        'failed' => [],
        'updated' => [],
        'skipped' => []
    ];
    
    try {
        foreach ($validated['readings'] as $readingData) {
            $unit = Unit::find($readingData['unit_id']);
            if (!$unit) {
                $results['failed'][] = [
                    'unit_id' => $readingData['unit_id'],
                    'reason' => 'Unit not found',
                    'month' => $readingData['reading_date']
                ];
                continue;
            }
            
            $readingDate = Carbon::parse($readingData['reading_date']);
            $newReading = $readingData['current_reading'];
            
            // Get the previous reading value
            // First, check if there's a reading before this date
            $readingBeforeThis = WaterReading::where('unit_id', $unit->id)
                ->where('reading_date', '<', $readingDate)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $previousReading = $readingBeforeThis 
                ? $readingBeforeThis->current_reading 
                : ($unit->current_water_reading ?? 0);
            
            // Validate reading is not less than previous
            if ($newReading < $previousReading) {
                $results['failed'][] = [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'month' => $readingDate->format('Y-m'),
                    'reason' => "Current reading ($newReading) less than previous ($previousReading)"
                ];
                continue;
            }
            
            $consumption = $newReading - $previousReading;
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            
            if ($unit->water_billing_type === 'flat') {
                $charge = $unit->water_charge ?? 0;
                $consumption = 0;
            } else {
                $charge = $consumption * $rate;
            }
            
            // CRITICAL FIX: Check if we have an existing_reading_id to update
            if (isset($readingData['existing_reading_id']) && !empty($readingData['existing_reading_id'])) {
                // UPDATE existing reading
                $existingReading = WaterReading::find($readingData['existing_reading_id']);
                if ($existingReading) {
                    $existingReading->update([
                        'previous_reading' => $previousReading,
                        'current_reading' => $newReading,
                        'consumption' => $consumption,
                        'rate_applied' => $rate,
                        'charge' => $charge,
                        'reading_date' => $readingDate,
                        'notes' => $validated['notes'] ?? $existingReading->notes
                    ]);
                    $results['updated'][] = [
                        'unit_id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'month' => $readingDate->format('Y-m')
                    ];
                } else {
                    $results['failed'][] = [
                        'unit_id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'month' => $readingDate->format('Y-m'),
                        'reason' => 'Existing reading not found'
                    ];
                }
            } else {
                // CHECK if a reading already exists for this month (by date)
                $existingByDate = WaterReading::where('unit_id', $unit->id)
                    ->whereYear('reading_date', $readingDate->year)
                    ->whereMonth('reading_date', $readingDate->month)
                    ->first();
                
                if ($existingByDate) {
                    $results['skipped'][] = [
                        'unit_id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'month' => $readingDate->format('Y-m'),
                        'reason' => 'Reading already exists for this month (use existing_reading_id to update)'
                    ];
                    continue;
                }
                
                // CREATE new reading
                WaterReading::create([
                    'unit_id' => $unit->id,
                    'previous_reading' => $previousReading,
                    'current_reading' => $newReading,
                    'consumption' => $consumption,
                    'rate_applied' => $rate,
                    'charge' => $charge,
                    'billing_type' => $unit->water_billing_type ?? 'consumption',
                    'reading_date' => $readingDate,
                    'recorded_by' => auth()->id(),
                    'notes' => $validated['notes'] ?? null
                ]);
                $results['success'][] = [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'month' => $readingDate->format('Y-m')
                ];
            }
        }
        
        // Update all affected units with latest readings
        $affectedUnitIds = array_unique(array_merge(
            array_column($results['success'], 'unit_id'),
            array_column($results['updated'], 'unit_id')
        ));
        
        foreach ($affectedUnitIds as $unitId) {
            $unit = Unit::find($unitId);
            if ($unit) {
                $this->updateUnitWithLatestReadings($unit);
            }
        }
        
        DB::commit();
        
        $message = count($results['success']) . ' reading(s) created successfully.';
        if (count($results['updated']) > 0) {
            $message .= ' ' . count($results['updated']) . ' reading(s) updated.';
        }
        if (count($results['skipped']) > 0) {
            $message .= ' ' . count($results['skipped']) . ' skipped.';
        }
        if (count($results['failed']) > 0) {
            $message .= ' ' . count($results['failed']) . ' failed.';
        }
        
        return response()->json([
            'success' => count($results['success']) > 0 || count($results['updated']) > 0,
            'message' => $message,
            'results' => $results
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error saving bulk matrix readings: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to record readings: ' . $e->getMessage()
        ], 500);
    }
}

    public function storeMultiMonth(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'start_month' => 'required|date_format:Y-m',
            'end_month' => 'required|date_format:Y-m|after_or_equal:start_month',
            'monthly_readings' => 'required|array|min:1',
            'monthly_readings.*.reading_date' => 'required|date',
            'monthly_readings.*.current_reading' => 'required|numeric|min:0',
            'monthly_readings.*.existing_reading_id' => 'nullable|exists:water_readings,id',
            'notes' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        
        try {
            $unit = Unit::findOrFail($validated['unit_id']);
            $results = [];
            
            // Sort readings by date
            $readings = collect($validated['monthly_readings'])->sortBy('reading_date')->values();
            
            // Get the reading before the first in our range
            $firstDate = Carbon::parse($readings[0]['reading_date']);
            $readingBeforeFirst = WaterReading::where('unit_id', $unit->id)
                ->where('reading_date', '<', $firstDate)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $previousReading = $readingBeforeFirst 
                ? $readingBeforeFirst->current_reading 
                : ($unit->current_water_reading ?? 0);
            
            foreach ($readings as $index => $monthReading) {
                $readingDate = Carbon::parse($monthReading['reading_date']);
                $newReading = $monthReading['current_reading'];
                
                if ($newReading < $previousReading) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Reading for {$readingDate->format('M Y')} is less than previous reading"
                    ], 422);
                }
                
                $consumption = $newReading - $previousReading;
                $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                
                if ($unit->water_billing_type === 'flat') {
                    $charge = $unit->water_charge ?? 0;
                    $consumption = 0;
                } else {
                    $charge = $consumption * $rate;
                }
                
                // Check if reading already exists for this month
                $existingReading = WaterReading::where('unit_id', $unit->id)
                    ->whereYear('reading_date', $readingDate->year)
                    ->whereMonth('reading_date', $readingDate->month)
                    ->first();
                
                if ($existingReading && !isset($monthReading['existing_reading_id'])) {
                    // Skip - reading exists but not marked for update
                    $results[] = [
                        'month' => $readingDate->format('Y-m'),
                        'status' => 'skipped',
                        'message' => 'Reading already exists'
                    ];
                    $previousReading = $newReading;
                    continue;
                }
                
                if ($existingReading && isset($monthReading['existing_reading_id'])) {
                    // Update existing reading
                    $existingReading->update([
                        'previous_reading' => $previousReading,
                        'current_reading' => $newReading,
                        'consumption' => $consumption,
                        'rate_applied' => $rate,
                        'charge' => $charge,
                        'notes' => $validated['notes'] ?? $existingReading->notes
                    ]);
                    $results[] = [
                        'month' => $readingDate->format('Y-m'),
                        'status' => 'updated',
                        'reading' => $newReading,
                        'consumption' => $consumption,
                        'charge' => $charge
                    ];
                } else {
                    // Create new reading
                    WaterReading::create([
                        'unit_id' => $unit->id,
                        'previous_reading' => $previousReading,
                        'current_reading' => $newReading,
                        'consumption' => $consumption,
                        'rate_applied' => $rate,
                        'charge' => $charge,
                        'billing_type' => $unit->water_billing_type ?? 'consumption',
                        'reading_date' => $readingDate->format('Y-m-d'),
                        'recorded_by' => auth()->id(),
                        'notes' => $validated['notes'] ?? null
                    ]);
                    $results[] = [
                        'month' => $readingDate->format('Y-m'),
                        'status' => 'created',
                        'reading' => $newReading,
                        'consumption' => $consumption,
                        'charge' => $charge
                    ];
                }
                
                $previousReading = $newReading;
            }
            
            // CRITICAL: Update the units table with the last two readings
            $this->updateUnitWithLatestReadings($unit);
            
            DB::commit();
            
            $created = count(array_filter($results, fn($r) => $r['status'] === 'created'));
            $updated = count(array_filter($results, fn($r) => $r['status'] === 'updated'));
            $skipped = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));
            
            $message = "$created reading(s) created";
            if ($updated > 0) $message .= ", $updated updated";
            if ($skipped > 0) $message .= ", $skipped skipped";
            $message .= " successfully";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'results' => $results,
                'total_consumption' => collect($results)->sum('consumption'),
                'total_charge' => collect($results)->sum('charge')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error saving multi-month readings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record readings: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateUnitWithLatestReadings(Unit $unit)
    {
        // Get the last two readings ordered by date (newest first)
        $lastTwoReadings = WaterReading::where('unit_id', $unit->id)
            ->orderBy('reading_date', 'desc')
            ->take(2)
            ->get();
        
        if ($lastTwoReadings->count() >= 2) {
            // We have at least two readings: most recent = current, second most recent = previous
            $mostRecent = $lastTwoReadings[0];
            $secondMostRecent = $lastTwoReadings[1];
            
            $unit->update([
                'previous_water_reading' => $secondMostRecent->current_reading,
                'current_water_reading' => $mostRecent->current_reading,
                'last_reading_date' => $mostRecent->reading_date
            ]);
        } elseif ($lastTwoReadings->count() == 1) {
            // Only one reading exists: use that as current, set previous to 0
            $mostRecent = $lastTwoReadings[0];
            
            $unit->update([
                'previous_water_reading' => 0,
                'current_water_reading' => $mostRecent->current_reading,
                'last_reading_date' => $mostRecent->reading_date
            ]);
        }
        // If no readings, do nothing
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        
        try {
            $unit = Unit::findOrFail($validated['unit_id']);
            $readingDate = Carbon::parse($validated['reading_date']);
            
            // Check if reading exists for this month
            $existingReading = WaterReading::where('unit_id', $unit->id)
                ->whereYear('reading_date', $readingDate->year)
                ->whereMonth('reading_date', $readingDate->month)
                ->first();
            
            if ($existingReading) {
                return response()->json([
                    'success' => false,
                    'duplicate' => true,
                    'message' => 'A water reading already exists for this month.',
                    'existing_reading' => [
                        'id' => $existingReading->id,
                        'reading_date' => $existingReading->reading_date->format('Y-m-d'),
                        'current_reading' => (float) $existingReading->current_reading,
                        'previous_reading' => (float) $existingReading->previous_reading,
                        'consumption' => (float) $existingReading->consumption,
                        'charge' => (float) $existingReading->charge,
                        'recorded_by' => $existingReading->recorded_by ? optional($existingReading->recordedBy)->name : 'System'
                    ]
                ], 409);
            }
            
            // Get the most recent reading for this unit to use as previous
            $latestReading = WaterReading::where('unit_id', $unit->id)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $previousReading = $latestReading 
                ? $latestReading->current_reading 
                : ($unit->current_water_reading ?? 0);
            
            $currentReading = $validated['current_reading'];
            
            if ($currentReading < $previousReading) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current reading cannot be less than previous reading'
                ], 422);
            }
            
            $consumption = $currentReading - $previousReading;
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            
            if ($unit->water_billing_type === 'flat') {
                $charge = $unit->water_charge ?? 0;
                $consumption = 0;
            } else {
                $charge = $consumption * $rate;
            }
            
            WaterReading::create([
                'unit_id' => $unit->id,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'consumption' => $consumption,
                'rate_applied' => $rate,
                'charge' => $charge,
                'billing_type' => $unit->water_billing_type ?? 'consumption',
                'reading_date' => $validated['reading_date'],
                'recorded_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null
            ]);
            
            // Update the units table with the last two readings
            $this->updateUnitWithLatestReadings($unit);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Water reading recorded successfully',
                'unit' => $unit->fresh()
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error saving water reading: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record reading: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reconcile(Request $request, WaterReading $reading)
    {
        $validated = $request->validate([
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        
        try {
            $unit = Unit::findOrFail($reading->unit_id);
            
            // Store old values for audit
            $oldReading = $reading->current_reading;
            $newReading = $validated['current_reading'];
            
            // Get the previous reading (the reading before this one)
            $previousReadingInHistory = WaterReading::where('unit_id', $unit->id)
                ->where('reading_date', '<', $reading->reading_date)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $previousReading = $previousReadingInHistory 
                ? $previousReadingInHistory->current_reading 
                : ($unit->previous_water_reading ?? 0);
            
            if ($newReading < $previousReading) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current reading cannot be less than previous reading'
                ], 422);
            }
            
            $consumption = $newReading - $previousReading;
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            
            if ($unit->water_billing_type === 'flat') {
                $charge = $unit->water_charge ?? 0;
                $consumption = 0;
            } else {
                $charge = $consumption * $rate;
            }
            
            $reading->update([
                'previous_reading' => $previousReading,
                'current_reading' => $newReading,
                'consumption' => $consumption,
                'charge' => $charge,
                'reading_date' => $validated['reading_date'],
                'notes' => $validated['notes'] ?? $reading->notes
            ]);
            
            // Get the latest reading for this unit
            $latestReading = WaterReading::where('unit_id', $unit->id)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $previousReadingForUnit = WaterReading::where('unit_id', $unit->id)
                ->where('reading_date', '<', $latestReading->reading_date)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $unit->update([
                'previous_water_reading' => $previousReadingForUnit ? $previousReadingForUnit->current_reading : 0,
                'current_water_reading' => $latestReading->current_reading,
                'last_reading_date' => $latestReading->reading_date
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Water reading reconciled successfully',
                'reading' => $reading,
                'unit' => $unit,
                'old_reading' => $oldReading,
                'new_reading' => $newReading
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error reconciling water reading: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reconcile reading: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLastReading($unitId)
    {
        $unit = Unit::findOrFail($unitId);
        
        // Get the latest reading from water_readings table
        $latestReading = WaterReading::where('unit_id', $unitId)
            ->orderBy('reading_date', 'desc')
            ->first();
        
        return response()->json([
            'success' => true,
            'previous_reading' => (float) ($latestReading ? $latestReading->current_reading : ($unit->current_water_reading ?? 0)),
            'last_reading_date' => $latestReading ? $latestReading->reading_date->format('Y-m-d') : ($unit->last_reading_date ? $unit->last_reading_date->format('Y-m-d') : null),
            'current_rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
            'billing_type' => $unit->water_billing_type ?? 'consumption',
            'flat_rate' => (float) ($unit->water_charge ?? 0)
        ]);
    }

    public function getUnitWaterHistory($unitId)
    {
        $unit = Unit::with('estate')->findOrFail($unitId);
        
        $allReadings = WaterReading::where('unit_id', $unitId)
            ->orderBy('reading_date', 'asc')
            ->get();
        
        if ($allReadings->isEmpty()) {
            return response()->json([
                'success' => true,
                'unit' => [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'water_billing_type' => $unit->water_billing_type ?? 'consumption',
                    'water_charge' => (float) ($unit->water_charge ?? 0),
                    'custom_water_rate' => (float) ($unit->custom_water_rate ?? 0)
                ],
                'history' => [],
                'stats' => [
                    'total_readings' => 0,
                    'average_consumption' => 0,
                    'total_consumption' => 0,
                    'total_charges' => 0,
                    'highest_consumption' => 0,
                    'lowest_consumption' => 0,
                    'last_reading_date' => 'No readings',
                    'billing_type' => $unit->water_billing_type ?? 'consumption',
                    'current_rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
                    'has_initial_reading' => false,
                    'initial_reading' => null
                ],
                'initial_reading' => null
            ]);
        }
        
        $initialReading = $allReadings->first();
        $subsequentReadings = $allReadings->skip(1);
        
        $history = $subsequentReadings->sortByDesc('reading_date')->values()->map(function($reading) {
            $recordedBy = null;
            if ($reading->recorded_by) {
                $user = \App\Models\User::find($reading->recorded_by);
                $recordedBy = $user ? $user->name : 'System';
            }
            
            return [
                'id' => $reading->id,
                'previous_reading' => (float) $reading->previous_reading,
                'current_reading' => (float) $reading->current_reading,
                'consumption' => (float) $reading->consumption,
                'charge' => (float) $reading->charge,
                'rate_applied' => (float) $reading->rate_applied,
                'reading_date' => $reading->reading_date->format('Y-m-d'),
                'reading_date_formatted' => $reading->reading_date->format('M d, Y'),
                'billing_type' => $reading->billing_type,
                'recorded_by' => $reading->recorded_by,
                'recorded_by_name' => $recordedBy,
                'notes' => $reading->notes,
                'is_initial' => false
            ];
        });
        
        $stats = [
            'total_readings' => $subsequentReadings->count(),
            'average_consumption' => $subsequentReadings->count() > 0 ? (float) $subsequentReadings->avg('consumption') : 0,
            'total_consumption' => (float) $subsequentReadings->sum('consumption'),
            'total_charges' => (float) $subsequentReadings->sum('charge'),
            'highest_consumption' => $subsequentReadings->count() > 0 ? (float) $subsequentReadings->max('consumption') : 0,
            'lowest_consumption' => $subsequentReadings->count() > 0 ? (float) $subsequentReadings->min('consumption') : 0,
            'last_reading_date' => $unit->last_reading_date ? $unit->last_reading_date->format('M d, Y') : 'No readings',
            'billing_type' => $unit->water_billing_type ?? 'consumption',
            'current_rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
            'has_initial_reading' => true,
            'initial_reading' => [
                'id' => $initialReading->id,
                'reading_date' => $initialReading->reading_date->format('M d, Y'),
                'current_reading' => (float) $initialReading->current_reading,
                'previous_reading' => (float) $initialReading->previous_reading,
                'consumption' => 0,
                'charge' => (float) $initialReading->charge,
                'is_initial' => true
            ]
        ];
        
        return response()->json([
            'success' => true,
            'unit' => [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_name' => $unit->estate->name ?? 'N/A',
                'water_billing_type' => $unit->water_billing_type ?? 'consumption',
                'water_charge' => (float) ($unit->water_charge ?? 0),
                'custom_water_rate' => (float) ($unit->custom_water_rate ?? 0)
            ],
            'history' => $history,
            'stats' => $stats,
            'initial_reading' => $stats['initial_reading']
        ]);
    }

    public function getUnitReadings(Unit $unit)
    {
        $readings = WaterReading::where('unit_id', $unit->id)
            ->orderBy('reading_date', 'desc')
            ->paginate(20);
        
        return response()->json($readings);
    }

    public function generateReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'estate_id' => 'nullable|exists:estates,id',
            'unit_id' => 'nullable|exists:units,id'
        ]);
        
        $query = WaterReading::with(['unit.estate'])
            ->whereBetween('reading_date', [$validated['start_date'], $validated['end_date']]);
        
        if ($validated['estate_id'] ?? false) {
            $query->whereHas('unit', fn($q) => $q->where('estate_id', $validated['estate_id']));
        }
        
        if ($validated['unit_id'] ?? false) {
            $query->where('unit_id', $validated['unit_id']);
        }
        
        $readings = $query->get();
        
        return response()->json([
            'success' => true,
            'summary' => [
                'total_consumption' => (float) $readings->sum('consumption'),
                'total_charges' => (float) $readings->sum('charge'),
                'average_consumption' => (float) $readings->avg('consumption'),
                'total_readings' => $readings->count(),
                'period' => [
                    'start' => $validated['start_date'],
                    'end' => $validated['end_date']
                ]
            ],
            'readings' => $readings->map(function($reading) {
                return [
                    'id' => $reading->id,
                    'unit_number' => $reading->unit->unit_number,
                    'estate_name' => $reading->unit->estate->name,
                    'previous_reading' => (float) $reading->previous_reading,
                    'current_reading' => (float) $reading->current_reading,
                    'consumption' => (float) $reading->consumption,
                    'charge' => (float) $reading->charge,
                    'reading_date' => $reading->reading_date->format('Y-m-d')
                ];
            })
        ]);
    }

    public function statement(Unit $unit)
    {
        $unit->load('estate');
        
        $allReadings = WaterReading::where('unit_id', $unit->id)
            ->orderBy('reading_date', 'asc')
            ->get();
        
        if ($allReadings->isEmpty()) {
            $readings = collect();
            $stats = [
                'total_readings' => 0,
                'average_consumption' => 0,
                'total_consumption' => 0,
                'total_charges' => 0,
                'has_initial_reading' => false,
                'initial_reading' => [
                    'reading_date' => '',
                    'current_reading' => 0,
                    'consumption' => 0
                ]
            ];
            return view('water.show', compact('unit', 'readings', 'stats'));
        }
        
        $initialReading = $allReadings->first();
        $subsequentReadings = $allReadings->skip(1);
        
        $readings = $subsequentReadings->sortByDesc('reading_date')->values()->map(function($reading) {
            $recordedBy = null;
            if ($reading->recorded_by) {
                $user = \App\Models\User::find($reading->recorded_by);
                $recordedBy = $user ? $user->name : 'System';
            }
            
            return (object) [
                'id' => $reading->id,
                'previous_reading' => (float) $reading->previous_reading,
                'current_reading' => (float) $reading->current_reading,
                'consumption' => (float) $reading->consumption,
                'charge' => (float) $reading->charge,
                'rate_applied' => (float) $reading->rate_applied,
                'reading_date' => $reading->reading_date->format('Y-m-d'),
                'billing_type' => $reading->billing_type,
                'recorded_by_name' => $recordedBy,
                'notes' => $reading->notes
            ];
        });
        
        // Use ARRAY format instead of object for initial_reading
        $stats = [
            'total_readings' => $subsequentReadings->count(),
            'average_consumption' => $subsequentReadings->count() > 0 ? (float) $subsequentReadings->avg('consumption') : 0,
            'total_consumption' => (float) $subsequentReadings->sum('consumption'),
            'total_charges' => (float) $subsequentReadings->sum('charge'),
            'has_initial_reading' => true,
            'initial_reading' => [
                'reading_date' => $initialReading->reading_date->format('M d, Y'),
                'current_reading' => (float) $initialReading->current_reading,
                'consumption' => 0,
                'previous_reading' => (float) $initialReading->previous_reading
            ]
        ];
        
        return view('water.show', compact('unit', 'readings', 'stats'));
    }

    public function getUnitsWithWaterReadings(Request $request)
    {
        $estateId = $request->get('estate_id');
        
        $query = Unit::with('estate')
            ->where('is_active', true);
        
        if ($estateId) {
            $query->where('estate_id', $estateId);
        }
        
        $units = $query->get()->map(function($unit) {
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_name' => $unit->estate->name ?? 'N/A',
                'estate_id' => $unit->estate_id,
                'unit_type' => $unit->unit_type,
                'water_billing_type' => $unit->water_billing_type ?? 'consumption',
                'water_charge' => (float) ($unit->water_charge ?? 0),
                'custom_water_rate' => (float) ($unit->custom_water_rate ?? 0),
                'current_water_reading' => (float) ($unit->current_water_reading ?? 0),
                'previous_water_reading' => (float) ($unit->previous_water_reading ?? 0),
                'last_reading_date' => $unit->last_reading_date,
                'water_rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
            ];
        });
        
        return response()->json([
            'success' => true,
            'units' => $units,
            'total' => $units->count()
        ]);
    }

    /**
     * Auto-fill missing months for a unit
     * Takes the last reading and propagates it forward to fill gaps
     */
    public function autoFillMissingMonths(Request $request, Unit $unit)
    {
        try {
            $validated = $request->validate([
                'start_month' => 'required|date_format:Y-m',
                'end_month' => 'required|date_format:Y-m|after_or_equal:start_month',
            ]);
            
            $startDate = Carbon::parse($validated['start_month'] . '-01');
            $endDate = Carbon::parse($validated['end_month'] . '-01');
            
            // Get existing readings within this range
            $existingReadings = WaterReading::where('unit_id', $unit->id)
                ->whereBetween('reading_date', [$startDate, $endDate])
                ->orderBy('reading_date', 'asc')
                ->get();
            
            // Get the reading before the start date
            $lastReading = WaterReading::where('unit_id', $unit->id)
                ->where('reading_date', '<', $startDate)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            $currentReading = $lastReading ? $lastReading->current_reading : ($unit->current_water_reading ?? 0);
            $createdCount = 0;
            
            DB::beginTransaction();
            
            // Generate readings for each month in the range
            $currentMonth = $startDate->copy();
            while ($currentMonth <= $endDate) {
                // Check if reading exists for this month
                $exists = $existingReadings->contains(function($reading) use ($currentMonth) {
                    return $reading->reading_date->format('Y-m') === $currentMonth->format('Y-m');
                });
                
                if (!$exists) {
                    // Use the same reading value (no consumption)
                    $previousReading = $currentReading;
                    
                    // Calculate consumption (should be 0 since we're copying)
                    $consumption = 0;
                    $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                    $charge = $unit->water_billing_type === 'flat' 
                        ? ($unit->water_charge ?? 0) 
                        : 0;
                    
                    WaterReading::create([
                        'unit_id' => $unit->id,
                        'previous_reading' => $previousReading,
                        'current_reading' => $currentReading,
                        'consumption' => $consumption,
                        'rate_applied' => $rate,
                        'charge' => $charge,
                        'billing_type' => $unit->water_billing_type ?? 'consumption',
                        'reading_date' => $currentMonth->format('Y-m-d'),
                        'recorded_by' => auth()->id(),
                        'notes' => 'Auto-filled missing month',
                    ]);
                    
                    $createdCount++;
                }
                
                $currentMonth->addMonth();
            }
            
            // Update the unit with latest readings
            $this->updateUnitWithLatestReadings($unit);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Auto-filled {$createdCount} missing month(s) for unit {$unit->unit_number}",
                'created_count' => $createdCount
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Auto-fill error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error auto-filling months: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-fill missing months for all units in an estate
     */
    public function autoFillEstate(Request $request)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'month' => 'required|date_format:Y-m',
        ]);
        
        $estate = Estate::findOrFail($validated['estate_id']);
        $month = Carbon::parse($validated['month'] . '-01');
        
        $units = Unit::where('estate_id', $estate->id)
            ->where('is_active', true)
            ->get();
        
        $results = [];
        
        foreach ($units as $unit) {
            // Check if this unit has a reading for this month
            $hasReading = WaterReading::where('unit_id', $unit->id)
                ->whereYear('reading_date', $month->year)
                ->whereMonth('reading_date', $month->month)
                ->exists();
            
            if (!$hasReading) {
                // Get the last reading before this month
                $lastReading = WaterReading::where('unit_id', $unit->id)
                    ->where('reading_date', '<', $month)
                    ->orderBy('reading_date', 'desc')
                    ->first();
                
                $currentReading = $lastReading ? $lastReading->current_reading : ($unit->current_water_reading ?? 0);
                $previousReading = $currentReading;
                
                WaterReading::create([
                    'unit_id' => $unit->id,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'consumption' => 0,
                    'rate_applied' => $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50,
                    'charge' => $unit->water_billing_type === 'flat' ? ($unit->water_charge ?? 0) : 0,
                    'billing_type' => $unit->water_billing_type ?? 'consumption',
                    'reading_date' => $month->format('Y-m-d'),
                    'recorded_by' => auth()->id(),
                    'notes' => 'Auto-filled missing month for estate-wide fill',
                ]);
                
                $this->updateUnitWithLatestReadings($unit);
                
                $results[] = [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'status' => 'filled',
                    'reading' => $currentReading
                ];
            } else {
                $results[] = [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'status' => 'exists'
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Processed {$results->count()} units",
            'results' => $results
        ]);
    }

}