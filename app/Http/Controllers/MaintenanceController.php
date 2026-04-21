<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Maintenance;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Maintenance::with(['unit', 'tenant.user', 'assignedStaff']);
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by priority
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }
        
        // Filter by unit
        if ($request->has('unit_id') && $request->unit_id) {
            $query->where('unit_id', $request->unit_id);
        }
        
        $requests = $query->latest()->paginate(20);
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $requests->map(function($req) {
                    return [
                        'id' => $req->id,
                        'request_number' => $req->request_number,
                        'name' => $req->name,
                        'description' => $req->description,
                        'category' => $req->category,
                        'priority' => $req->priority,
                        'status' => $req->status,
                        'unit_number' => $req->unit->unit_number ?? 'N/A',
                        'tenant_name' => $req->tenant->user->name ?? 'N/A',
                        'created_at' => $req->created_at,
                        'scheduled_date' => $req->scheduled_date,
                        'completed_date' => $req->completed_date,
                    ];
                }),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'total_pages' => $requests->lastPage(),
                    'total' => $requests->total()
                ]
            ]);
        }
        
        return view('maintenance.index', compact('requests'));
    }



public function store(Request $request)
{
    $validated = $request->validate([
        'unit_id' => 'required|exists:units,id',
        'name' => 'required|string|max:255', // ✅ match frontend
        'description' => 'required|string',
        'category' => 'required|in:plumbing,electrical,hvac,appliance,structural,pest_control,cleaning,other',
        'priority' => 'required|in:low,medium,high,emergency',
        'duration' => 'required|string',
        'images' => 'nullable',
    ]);

    // ✅ Get tenant properly
    $tenant = \App\Models\Tenant::where('user_id', Auth::id())->first();

    if (!$tenant) {
        // fallback: derive tenant from unit
        $unit = \App\Models\Unit::with('tenant')->find($validated['unit_id']);
        $tenantId = $unit?->tenant?->id;
    } else {
        $tenantId = $tenant->id;
    }

    // ❌ Still no tenant? STOP
    if (!$tenantId) {
        return response()->json([
            'success' => false,
            'message' => 'No tenant assigned to this unit'
        ], 422);
    }

    // ✅ Generate request number safely
    $lastId = \App\Models\Maintenance::max('id') ?? 0;
    $requestNumber = 'MT-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);

    // ✅ Create record
    $maintenance = \App\Models\Maintenance::create([
        'unit_id' => $validated['unit_id'],
        'tenant_id' => $tenantId,
        'name' => $validated['name'], // ✅ map correctly
        'description' => $validated['description'],
        'category' => $validated['category'],
        'priority' => $validated['priority'],
        'duration' => $validated['duration'],
        'status' => 'open',
        'request_number' => $requestNumber,
        'images' => json_encode([]), // you can improve this later
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Maintenance request submitted successfully',
        'request' => $maintenance
    ]);
}
    public function update(Request $request, Maintenance $maintenance)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:open,in_progress,pending_parts,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,emergency',
            'assigned_to' => 'nullable|exists:users,id',
            'admin_notes' => 'nullable|string',
            'resolution_notes' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
        ]);
        
        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_date'] = $validated['completed_date'] ?? Carbon::now();
        }
        
        $maintenanceRequest->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Maintenance request updated successfully',
            'request' => $maintenanceRequest
        ]);
    }

    public function show(Maintenance $maintenance)
    {
        $maintenance->load(['unit', 'tenant.user', 'assignedStaff']);
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $maintenanceRequest
            ]);
        }
        
        return view('maintenance.show', compact('maintenanceRequest'));
    }

    public function tenantRequests()
    {
        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json(['success' => true, 'data' => []]);
        }
        
        $requests = Maintenance::where('tenant_id', $tenant->id)
            ->with('unit')
            ->latest()
            ->get()
            ->map(function($req) {
                return [
                    'id' => $req->id,
                    'request_number' => $req->request_number,
                    'name' => $req->name,
                    'description' => $req->description,
                    'category' => $req->category_label,
                    'priority' => ucfirst($req->priority),
                    'priority_color' => $req->priority_color,
                    'status' => ucfirst(str_replace('_', ' ', $req->status)),
                    'status_color' => $req->status_color,
                    'unit_number' => $req->unit->unit_number,
                    'created_at' => $req->created_at->format('M d, Y'),
                    'scheduled_date' => $req->scheduled_date ? $req->scheduled_date->format('M d, Y') : null,
                    'completed_date' => $req->completed_date ? $req->completed_date->format('M d, Y') : null,
                    'resolution_notes' => $req->resolution_notes,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function getUnitHistory($unitId)
    {
        $user = auth()->user();
        
        // Only property managers and maintenance staff can view history
        if (!$user->hasAnyRole(['property_manager', 'maintenance', 'super_admin', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $requests = Maintenance::where('unit_id', $unitId)
            ->with('unit')
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'name' => $request->name,
                    'description' => $request->description,
                    'priority' => $request->priority,
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                    'resolution_notes' => $request->resolution_notes,
                ];
            });
        
        return response()->json([
            'success' => true,
            'requests' => $requests
        ]);
    }

    public function showJson($id)
    {
        $maintenance = Maintenance::with(['unit', 'tenant.user'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'request' => [
                'id' => $maintenance->id,
                'request_number' => $maintenance->request_number,
                'unit_number' => $maintenance->unit->unit_number ?? 'N/A',
                'tenant_name' => $maintenance->tenant->user->name ?? 'N/A',
                'name' => $maintenance->name,
                'description' => $maintenance->description,
                'priority' => $maintenance->priority,
                'status' => $maintenance->status,
                'created_at' => $maintenance->created_at,
                'resolved_at' => $maintenance->resolved_at,
            ]
        ]);
    }
}