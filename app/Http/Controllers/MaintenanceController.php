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
        $user = auth()->user();
        $query = Maintenance::with(['unit', 'tenant.user', 'assignedStaff']);
        
        // Apply company filter for non-sysadmin users
        if (!$user->hasRole('sysadmin') && $user->company_id) {
            $query->whereHas('unit', function($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }
        
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
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:plumbing,electrical,hvac,appliance,structural,pest_control,cleaning,other',
            'priority' => 'required|in:low,medium,high,emergency',
            'duration' => 'nullable|string',
            'images' => 'nullable',
        ]);

        $user = Auth::user();
        
        // Get tenant properly
        $tenant = Tenant::where('user_id', $user->id)->first();

        if (!$tenant) {
            // fallback: derive tenant from unit
            $unit = Unit::with('tenant')->find($validated['unit_id']);
            $tenantId = $unit?->tenant?->id;
        } else {
            $tenantId = $tenant->id;
        }

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant assigned to this unit'
            ], 422);
        }

        // Generate request number
        $lastId = Maintenance::max('id') ?? 0;
        $requestNumber = 'MT-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);

        // Get company_id from the unit
        $unit = Unit::find($validated['unit_id']);
        $companyId = $unit?->company_id ?? $user->company_id;

        // Create record
        $maintenance = Maintenance::create([
            'company_id' => $companyId,
            'estate_id' => $unit?->estate_id,
            'unit_id' => $validated['unit_id'],
            'tenant_id' => $tenantId,
            'assigned_to' => null,
            'request_number' => $requestNumber,
            'duration' => $validated['duration'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'admin_notes' => null,
            'resolution_notes' => null,
            'scheduled_date' => null,
            'completed_date' => null,
            'cost' => null,
            'images' => json_encode([]),
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
        
        $maintenance->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Maintenance request updated successfully',
            'request' => $maintenance
        ]);
    }

    public function show(Maintenance $maintenance)
    {
        $maintenance->load(['unit', 'tenant.user', 'assignedStaff']);
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $maintenance
            ]);
        }
        
        return view('maintenance.show', compact('maintenance'));
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
                'completed_date' => $maintenance->completed_date,
            ]
        ]);
    }
}