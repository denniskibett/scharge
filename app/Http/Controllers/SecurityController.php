<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SecurityLog;
use App\Models\Visitor;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Estate;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SecurityController extends Controller
{
    // Display security logs dashboard
    public function index()
    {
        $user = auth()->user();
        $units = Unit::with('estate')->get();
        
        // Get security logs based on role
        if ($user->hasAnyRole(['super_admin', 'admin', 'security'])) {
            $accessLogs = SecurityLog::with(['unit', 'visitor'])
                ->latest('access_time')
                ->get(); // Changed from paginate to get
            
            $pendingLogs = SecurityLog::where('status', 'pending')
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get();
                
            $todayLogs = SecurityLog::whereDate('access_time', Carbon::today())
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get();
        } else if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            // Tenants only see logs for their unit
            $unitId = $user->tenant->activeTenancy->unit_id;
            $accessLogs = SecurityLog::where('unit_id', $unitId)
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get();
                
            $pendingLogs = collect();
            $todayLogs = SecurityLog::where('unit_id', $unitId)
                ->whereDate('access_time', Carbon::today())
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get();
        } else {
            $accessLogs = collect();
            $pendingLogs = collect();
            $todayLogs = collect();
        }
        
        // Create the roleData array that your view expects
        $roleData = [
            'accessLogs' => $accessLogs,
            'pendingLogs' => $pendingLogs,
            'todayLogs' => $todayLogs,
            'units' => $units,
        ];
        
        return view('partials.dashboard.security', compact('roleData'));
    }
    
    // Get single security log (API)
    public function show($id)
    {
        $log = SecurityLog::with(['unit', 'visitor'])->findOrFail($id);
        
        // Check authorization
        $user = auth()->user();
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            if ($log->unit_id != $user->tenant->activeTenancy->unit_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }
        
        return response()->json([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'datetime' => $log->access_time,
                'datetime_formatted' => $log->access_time->format('M d, Y H:i'),
                'unit_id' => $log->unit_id,
                'unit_number' => $log->unit->unit_number ?? 'N/A',
                'person_name' => $log->visitor_name_snapshot ?? ($log->visitor->full_name ?? $log->person_name),
                'access_type' => $log->access_type,
                'access_type_label' => $log->access_type_label,
                'status' => $log->status,
                'verified_by' => $log->approved_by ?? $log->verified_by ?? 'System',
                'notes' => $log->notes,
                'purpose' => $log->purpose,
            ]
        ]);
    }
    
    // Store security log (API)
    public function store(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'person_name' => 'required|string|max:255',
            'access_type' => 'required|in:entry,exit,delivery,visitor,maintenance,guest,contractor',
            'status' => 'required|in:granted,denied,pending',
            'datetime' => 'required|date',
            'verified_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'purpose' => 'nullable|string',
        ]);
        
        $log = SecurityLog::create([
            'unit_id' => $request->unit_id,
            'person_name' => $request->person_name,
            'access_type' => $request->access_type,
            'status' => $request->status,
            'access_time' => Carbon::parse($request->datetime),
            'verified_by' => $request->verified_by ?? auth()->user()->name,
            'notes' => $request->notes,
            'purpose' => $request->purpose,
            'created_by' => auth()->id()
        ]);
        
        return response()->json(['success' => true, 'log' => $log, 'message' => 'Security log created successfully']);
    }
    
    // Update security log (API)
    public function update(Request $request, $id)
    {
        $log = SecurityLog::findOrFail($id);
        
        // Check authorization
        if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'person_name' => 'required|string|max:255',
            'access_type' => 'required|in:entry,exit,delivery,visitor,maintenance,guest,contractor',
            'status' => 'required|in:granted,denied,pending',
            'datetime' => 'required|date',
            'verified_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'purpose' => 'nullable|string',
        ]);
        
        $log->update([
            'unit_id' => $request->unit_id,
            'person_name' => $request->person_name,
            'access_type' => $request->access_type,
            'status' => $request->status,
            'access_time' => Carbon::parse($request->datetime),
            'verified_by' => $request->verified_by,
            'notes' => $request->notes,
            'purpose' => $request->purpose,
        ]);
        
        return response()->json(['success' => true, 'log' => $log, 'message' => 'Security log updated successfully']);
    }
    
    // Delete security log (API)
    public function destroy($id)
    {
        $log = SecurityLog::findOrFail($id);
        
        // Check authorization
        if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $log->delete();
        
        return response()->json(['success' => true, 'message' => 'Security log deleted successfully']);
    }
    
    // Quick entry by phone or ID number
    public function quickEntry(Request $request)
    {
        $request->validate([
            'lookup_by' => 'required|in:phone,id_number',
            'lookup_value' => 'required|string',
            'unit_id' => 'required|exists:units,id',
            'access_type' => 'required|in:entry,exit,delivery,guest,maintenance,contractor',
        ]);
        
        // Find existing visitor
        $visitor = Visitor::where($request->lookup_by, $request->lookup_value)->first();
        
        if (!$visitor) {
            return response()->json([
                'success' => false,
                'message' => 'Visitor not found. Please register them first.',
                'requires_registration' => true
            ], 404);
        }
        
        // Check blacklist
        if ($visitor->is_blacklisted) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied - Visitor is blacklisted.',
                'reason' => $visitor->blacklist_reason
            ], 403);
        }
        
        $unit = Unit::find($request->unit_id);
        $tenant = $unit->activeTenancy?->tenant;
        
        // Create security log
        $log = SecurityLog::create([
            'unit_id' => $unit->id,
            'visitor_id' => $visitor->id,
            'visitor_name_snapshot' => $visitor->full_name,
            'person_name' => $visitor->full_name,
            'access_type' => $request->access_type,
            'status' => $visitor->is_pre_approved ? 'granted' : 'pending',
            'access_time' => now(),
            'purpose' => $request->purpose ?? $visitor->visitor_type,
            'created_by' => auth()->id()
        ]);
        
        // Update visitor stats
        $visitor->increment('total_visits');
        
        return response()->json([
            'success' => true,
            'message' => $log->status === 'granted' ? 'Access granted. Welcome!' : 'Access request submitted for approval.',
            'visitor' => [
                'id' => $visitor->id,
                'name' => $visitor->full_name,
                'type' => $visitor->visitor_type_label,
                'is_registered' => $visitor->is_registered,
            ],
            'log' => $log
        ]);
    }
    
    // Register new visitor (one-time or recurring)
    public function registerVisitor(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'id_number' => 'nullable|string',
            'id_type' => 'nullable|string',
            'visitor_type' => 'required|in:family,employee,contractor,regular_guest,delivery,maintenance,one_time',
            'relationship' => 'required_if:visitor_type,family,employee|nullable|string',
            'company' => 'required_if:visitor_type,contractor|nullable|string',
            'vehicles' => 'nullable|array',
            'is_registered' => 'boolean',
            'valid_until' => 'nullable|date|after:today',
            'access_schedule' => 'nullable|array',
        ]);
        
        // Check if visitor already exists
        $visitor = null;
        if ($request->phone) {
            $visitor = Visitor::where('phone', $request->phone)->first();
        }
        if (!$visitor && $request->id_number) {
            $visitor = Visitor::where('id_number', $request->id_number)->first();
        }
        
        if ($visitor) {
            // Update existing visitor
            $visitor->update([
                'visitor_type' => $request->visitor_type,
                'relationship' => $request->relationship,
                'company' => $request->company,
                'is_registered' => $request->is_registered ?? $visitor->is_registered,
                'valid_until' => $request->valid_until ?? $visitor->valid_until,
                'access_schedule' => $request->access_schedule,
            ]);
            
            if ($request->vehicles) {
                $visitor->vehicles = $request->vehicles;
                $visitor->save();
            }
            
            $message = 'Visitor information updated successfully';
        } else {
            // Create new visitor
            $visitor = Visitor::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'id_number' => $request->id_number,
                'id_type' => $request->id_type,
                'visitor_type' => $request->visitor_type,
                'relationship' => $request->relationship,
                'company' => $request->company,
                'vehicles' => $request->vehicles,
                'is_registered' => $request->is_registered ?? false,
                'valid_until' => $request->valid_until,
                'access_schedule' => $request->access_schedule,
            ]);
            
            $message = 'Visitor registered successfully';
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'visitor' => $visitor
        ]);
    }
    
    // Get security logs for display (API)
    public function getLogs(Request $request)
    {
        $query = SecurityLog::with(['unit', 'visitor', 'creator'])
            ->latest('access_time');
        
        if ($request->unit_id) {
            $query->where('unit_id', $request->unit_id);
        }
        
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->date_from) {
            $query->whereDate('access_time', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->whereDate('access_time', '<=', $request->date_to);
        }
        
        $logs = $query->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'visitor_name' => $log->visitor_name_snapshot ?? $log->visitor->full_name ?? $log->person_name,
                    'visitor_type' => $log->visitor->visitor_type_label ?? 'One-time',
                    'unit_number' => $log->unit->unit_number,
                    'access_type' => $log->access_type_label,
                    'status' => ucfirst($log->status),
                    'status_color' => $log->status_color,
                    'access_time' => $log->access_time->format('M d, Y g:i A'),
                    'purpose' => $log->purpose,
                ];
            }),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'total_pages' => $logs->lastPage(),
                'total' => $logs->total()
            ]
        ]);
    }
    
    // Approve/deny access
    public function updateLogStatus(Request $request, SecurityLog $log)
    {
        // Check authorization
        if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'status' => 'required|in:granted,denied,completed',
            'notes' => 'nullable|string',
        ]);
        
        $log->update([
            'status' => $request->status,
            'approved_by' => auth()->user()->name,
            'approved_at' => now(),
            'notes' => $request->notes ?? $log->notes,
        ]);
        
        if ($request->status === 'granted' && $log->visitor) {
            $log->visitor->increment('total_visits');
        }
        
        return response()->json([
            'success' => true,
            'message' => "Access {$request->status} successfully",
            'log' => $log
        ]);
    }

    // Add these methods to app/Http/Controllers/SecurityController.php

/**
 * Get estates for the current user's company
 */
/**
 * Get estates for the current user's company
 */
public function getEstates()
{
    $user = auth()->user();
    $companyId = $user->company_id;
    
    // If user is sysadmin, get all estates, otherwise filter by company
    if ($user->hasRole('sysadmin')) {
        $estates = Estate::orderBy('name')->get();
    } else {
        $estates = Estate::where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }
    
    return response()->json([
        'success' => true, 
        'estates' => $estates->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
            ];
        })
    ]);
}

/**
 * Get units for a specific estate with active tenancies
 */
public function getUnitsByEstate(Request $request)
{
    $request->validate([
        'estate_id' => 'required|exists:estates,id',
    ]);
    
    $units = Unit::where('estate_id', $request->estate_id)
        ->with(['activeTenancy.tenant.user'])
        ->get()
        ->map(function($unit) {
            $tenant = $unit->activeTenancy?->tenant;
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'has_active_tenancy' => !is_null($tenant),
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name ?? 'Unknown',
                    'phone' => $tenant->user->phone ?? null,
                    'email' => $tenant->user->email ?? null,
                ] : null,
            ];
        });
    
    return response()->json(['success' => true, 'units' => $units]);
}

/**
 * Get tenants for a specific unit
 */
public function getTenantsByUnit(Request $request)
{
    $request->validate([
        'unit_id' => 'required|exists:units,id',
    ]);
    
    $unit = Unit::with(['activeTenancy.tenant.user', 'tenancies.tenant.user'])->find($request->unit_id);
    
    $tenants = collect();
    
    // Current/active tenant
    if ($unit->activeTenancy && $unit->activeTenancy->tenant) {
        $tenant = $unit->activeTenancy->tenant;
        $tenants->push([
            'id' => $tenant->id,
            'name' => $tenant->user->name ?? 'Unknown',
            'phone' => $tenant->user->phone ?? null,
            'email' => $tenant->user->email ?? null,
            'is_active' => true,
            'status' => 'Current Tenant',
        ]);
    }
    
    // Past tenants (optional - can be expanded)
    foreach ($unit->tenancies->where('status', '!=', 'active') as $tenancy) {
        if ($tenancy->tenant) {
            $tenants->push([
                'id' => $tenancy->tenant->id,
                'name' => $tenancy->tenant->user->name ?? 'Unknown',
                'phone' => $tenancy->tenant->user->phone ?? null,
                'email' => $tenancy->tenant->user->email ?? null,
                'is_active' => false,
                'status' => 'Past Tenant',
                'move_out_date' => $tenancy->move_out_date,
            ]);
        }
    }
    
    return response()->json(['success' => true, 'tenants' => $tenants]);
}

/**
 * Get visitors for a specific tenant
 */
public function getVisitorsByTenant(Request $request)
{
    $request->validate([
        'tenant_id' => 'required|exists:tenants,id',
    ]);
    
    $tenant = Tenant::with(['user'])->find($request->tenant_id);
    
    // Get unit for this tenant
    $unit = $tenant->activeTenancy?->unit;
    
    // Get visitors registered by this tenant
    $visitors = Visitor::where('registered_by_tenant_id', $tenant->id)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($visitor) use ($unit) {
            // Check if visitor has a security log for today/active
            $recentLog = SecurityLog::where('visitor_id', $visitor->id)
                ->where('unit_id', $unit?->id)
                ->latest('access_time')
                ->first();
            
            return [
                'id' => $visitor->id,
                'name' => $visitor->full_name,
                'phone' => $visitor->phone,
                'id_number' => $visitor->id_number,
                'visitor_type' => $visitor->visitor_type,
                'visitor_type_label' => $visitor->visitor_type_label,
                'company' => $visitor->company,
                'vehicles' => $visitor->vehicles,
                'is_active' => $visitor->is_active,
                'is_blacklisted' => $visitor->is_blacklisted,
                'valid_until' => $visitor->valid_until,
                'visit_count' => $visitor->visit_count,
                'last_visit_at' => $visitor->last_visit_at,
                'recent_log' => $recentLog ? [
                    'access_time' => $recentLog->access_time,
                    'status' => $recentLog->status,
                ] : null,
            ];
        });
    
    // Also get recent one-time visitors (not registered) for this unit
    $oneTimeVisitors = SecurityLog::where('unit_id', $unit?->id)
        ->whereNull('visitor_id')
        ->latest('access_time')
        ->take(10)
        ->get()
        ->map(function($log) {
            return [
                'id' => $log->id,
                'name' => $log->visitor_name_snapshot ?? $log->person_name,
                'phone' => $log->visitor_phone_snapshot,
                'id_number' => $log->visitor_id_number_snapshot,
                'company' => $log->visitor_company_snapshot,
                'vehicle' => $log->vehicle_registration_snapshot,
                'access_type' => $log->access_type_label,
                'access_time' => $log->access_time,
                'status' => $log->status,
                'is_one_time' => true,
            ];
        });
    
    return response()->json([
        'success' => true,
        'tenant' => [
            'id' => $tenant->id,
            'name' => $tenant->user->name ?? 'Unknown',
            'phone' => $tenant->user->phone ?? null,
            'email' => $tenant->user->email ?? null,
            'unit' => $unit ? [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
            ] : null,
        ],
        'visitors' => $visitors,
        'recent_one_time_visitors' => $oneTimeVisitors,
        'stats' => [
            'total_visitors' => $visitors->count(),
            'active_visitors' => $visitors->where('is_active', true)->count(),
            'total_visits' => $visitors->sum('visit_count'),
        ]
    ]);
}

// Add this method to SecurityController.php
public function getSecurityData()
{
    $user = auth()->user();
    $companyId = $user->company_id;
    
    // Get estates for the current company
    $estates = Estate::where('company_id', $companyId)
        ->orderBy('name')
        ->get()
        ->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
            ];
        });
    
    // Get all units with estates for the modal dropdowns
    $units = Unit::with('estate')
        ->where('company_id', $companyId)
        ->where('is_active', true)
        ->get()
        ->map(function($unit) {
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_name' => $unit->estate->name ?? 'N/A',
                'estate_id' => $unit->estate_id,
                'status' => $unit->status,
            ];
        });
    
    return response()->json([
        'success' => true,
        'estates' => $estates,
        'units' => $units
    ]);
}

public function getSecurityLogsByTenant(Request $request)
{
    $request->validate([
        'tenant_id' => 'required|exists:tenants,id',
        'limit' => 'nullable|integer|min:1|max:100',
    ]);
    
    $tenant = Tenant::find($request->tenant_id);
    $unit = $tenant->activeTenancy?->unit;
    
    if (!$unit) {
        return response()->json([
            'success' => true,
            'logs' => [],
            'message' => 'No active unit found for this tenant',
        ]);
    }
    
    $limit = $request->limit ?? 50;
    
    $logs = SecurityLog::where('unit_id', $unit->id)
        ->with(['visitor'])
        ->latest('access_time')
        ->limit($limit)
        ->get()
        ->map(function($log) {
            return [
                'id' => $log->id,
                'visitor_name' => $log->visitor_name_snapshot ?? ($log->visitor->full_name ?? $log->person_name),
                'visitor_phone' => $log->visitor_phone_snapshot ?? $log->visitor->phone ?? null,
                'visitor_type' => $log->visitor ? $log->visitor->visitor_type_label : 'One-time',
                'access_type' => $log->access_type_label,
                'access_time' => $log->access_time,
                'access_time_formatted' => $log->access_time->format('M d, Y H:i'),
                'exit_time' => $log->exit_time,
                'exit_time_formatted' => $log->exit_time ? $log->exit_time->format('M d, Y H:i') : null,
                'status' => $log->status,
                'status_badge_class' => $log->status_color,
                'purpose' => $log->purpose,
                'notes' => $log->notes,
                'verified_by' => $log->approved_by ?? 'System',
            ];
        });
    
    $stats = [
        'total_logs' => $logs->count(),
        'today_logs' => $logs->filter(function($log) {
            return $log['access_time']->isToday();
        })->count(),
        'pending_logs' => $logs->filter(function($log) {
            return $log['status'] === 'pending';
        })->count(),
        'approved_logs' => $logs->filter(function($log) {
            return $log['status'] === 'approved';
        })->count(),
        'denied_logs' => $logs->filter(function($log) {
            return $log['status'] === 'denied';
        })->count(),
    ];
    
    return response()->json([
        'success' => true,
        'logs' => $logs,
        'stats' => $stats,
        'unit' => [
            'id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'estate_name' => $unit->estate->name ?? 'N/A',
        ],
    ]);
}
}