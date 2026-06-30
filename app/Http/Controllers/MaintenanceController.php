<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Maintenance;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Estate;
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
        
        // Get units and estates for the modals
        $units = Unit::where('company_id', $user->company_id)
            ->with('estate')
            ->where('is_active', true)
            ->get();
        
        $estates = Estate::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();
        
        $currentUnit = null;
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $currentUnit = $user->tenant->activeTenancy->unit->load('estate');
        }
        
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
        
        return view('maintenance.index', compact('requests', 'units', 'estates', 'currentUnit'));
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
            'images' => 'nullable|array',
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
            'request' => $maintenance->load(['unit', 'tenant.user'])
        ]);
    }

    /**
     * Show the form for editing a maintenance request
     * This is called when the user clicks "Edit" from the table
     */
    public function edit(Maintenance $maintenance)
    {
        $maintenance->load(['unit', 'tenant.user', 'assignedStaff']);
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $maintenance->id,
                    'unit_id' => $maintenance->unit_id,
                    'unit_number' => $maintenance->unit->unit_number ?? 'N/A',
                    'tenant_name' => $maintenance->tenant->user->name ?? 'N/A',
                    'name' => $maintenance->name,
                    'description' => $maintenance->description,
                    'category' => $maintenance->category,
                    'priority' => $maintenance->priority,
                    'status' => $maintenance->status,
                    'duration' => $maintenance->duration,
                    'admin_notes' => $maintenance->admin_notes,
                    'resolution_notes' => $maintenance->resolution_notes,
                    'scheduled_date' => $maintenance->scheduled_date,
                    'completed_date' => $maintenance->completed_date,
                    'cost' => $maintenance->cost,
                    'assigned_to' => $maintenance->assigned_to,
                    'created_at' => $maintenance->created_at,
                    'updated_at' => $maintenance->updated_at,
                ]
            ]);
        }
        
        // For non-AJAX requests, redirect to index with the maintenance ID for modal opening
        return redirect()->route('maintenance.index')->with('edit_id', $maintenance->id);
    }

    /**
     * Update a maintenance request
     * This handles both AJAX and form submissions
     */
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|in:plumbing,electrical,hvac,appliance,structural,pest_control,cleaning,other',
            'duration' => 'nullable|string',
        ]);
        
        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_date'] = $validated['completed_date'] ?? Carbon::now();
        }
        
        $maintenance->update($validated);
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Maintenance request updated successfully',
                'request' => $maintenance->load(['unit', 'tenant.user', 'assignedStaff'])
            ]);
        }
        
        return redirect()->route('maintenance.index')->with('success', 'Maintenance request updated successfully');
    }

    /**
     * Get maintenance request data for editing in modal
     */
    public function getEditData($id)
    {
        $maintenance = Maintenance::with(['unit', 'tenant.user', 'assignedStaff'])->findOrFail($id);
        
        // Get staff users for assignment dropdown
        $staffUsers = User::whereHas('role', function($q) {
            $q->whereIn('name', ['admin', 'super_admin', 'property_manager', 'maintenance']);
        })->select('id', 'name', 'email')->get();
        
        return response()->json([
            'success' => true,
            'request' => [
                'id' => $maintenance->id,
                'unit_id' => $maintenance->unit_id,
                'unit_number' => $maintenance->unit->unit_number ?? 'N/A',
                'tenant_name' => $maintenance->tenant->user->name ?? 'N/A',
                'tenant_id' => $maintenance->tenant_id,
                'name' => $maintenance->name,
                'description' => $maintenance->description,
                'category' => $maintenance->category,
                'category_label' => $this->getCategoryLabel($maintenance->category),
                'priority' => $maintenance->priority,
                'priority_label' => ucfirst($maintenance->priority),
                'priority_color' => $this->getPriorityColor($maintenance->priority),
                'status' => $maintenance->status,
                'status_label' => ucfirst(str_replace('_', ' ', $maintenance->status)),
                'status_color' => $this->getStatusColor($maintenance->status),
                'duration' => $maintenance->duration,
                'admin_notes' => $maintenance->admin_notes,
                'resolution_notes' => $maintenance->resolution_notes,
                'scheduled_date' => $maintenance->scheduled_date,
                'scheduled_date_formatted' => $maintenance->scheduled_date ? $maintenance->scheduled_date->format('Y-m-d') : null,
                'completed_date' => $maintenance->completed_date,
                'completed_date_formatted' => $maintenance->completed_date ? $maintenance->completed_date->format('Y-m-d') : null,
                'cost' => $maintenance->cost,
                'assigned_to' => $maintenance->assigned_to,
                'assigned_to_name' => $maintenance->assignedStaff?->name ?? null,
                'created_at' => $maintenance->created_at,
                'created_at_formatted' => $maintenance->created_at ? $maintenance->created_at->format('M d, Y') : null,
                'updated_at' => $maintenance->updated_at,
                'request_number' => $maintenance->request_number,
            ],
            'staff_users' => $staffUsers,
            'statuses' => ['open', 'in_progress', 'pending_parts', 'completed', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'emergency'],
            'categories' => ['plumbing', 'electrical', 'hvac', 'appliance', 'structural', 'pest_control', 'cleaning', 'other'],
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
                    'priority_color' => $this->getPriorityColor($req->priority),
                    'status' => ucfirst(str_replace('_', ' ', $req->status)),
                    'status_color' => $this->getStatusColor($req->status),
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
                    'priority_label' => ucfirst($request->priority),
                    'priority_color' => $this->getPriorityColor($request->priority),
                    'status' => $request->status,
                    'status_label' => ucfirst(str_replace('_', ' ', $request->status)),
                    'status_color' => $this->getStatusColor($request->status),
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

    // Helper methods for status/priority colors
    private function getPriorityColor($priority)
    {
        $colors = [
            'emergency' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'low' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        ];
        return $colors[$priority] ?? 'bg-gray-100 text-gray-800';
    }

    private function getStatusColor($status)
    {
        $colors = [
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'pending_parts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
            'open' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
        ];
        return $colors[$status] ?? 'bg-gray-100 text-gray-800';
    }

    private function getCategoryLabel($category)
    {
        $labels = [
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'hvac' => 'HVAC',
            'appliance' => 'Appliance',
            'structural' => 'Structural',
            'pest_control' => 'Pest Control',
            'cleaning' => 'Cleaning',
            'other' => 'Other'
        ];
        return $labels[$category] ?? ucfirst($category);
    }
}