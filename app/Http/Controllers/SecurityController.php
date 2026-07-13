<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\Security\Models\SecurityLog;
use App\Modules\Security\Models\Visitor;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Estate;
use App\Models\Tenancy;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SecurityController extends Controller
{
    /**
     * Display security dashboard
     */
    public function index()
    {
        $user = auth()->user();
        $units = Unit::with('estate')->get();
        $company = \App\Models\Company::find($user->company_id);
        
        // Get security logs based on role
        if ($user->hasAnyRole(['super_admin', 'admin', 'security'])) {
            $accessLogs = SecurityLog::with(['unit', 'visitor', 'verifiedByUser'])
                ->latest('access_time')
                ->get()
                ->map(function($log) {
                    return $this->formatLogForView($log);
                });
            
            $pendingLogs = SecurityLog::where('status', 'pending')
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get()
                ->map(function($log) {
                    return $this->formatLogForView($log);
                });
                
            $todayLogs = SecurityLog::whereDate('access_time', Carbon::today())
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get()
                ->map(function($log) {
                    return $this->formatLogForView($log);
                });
        } else if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            // Tenants only see logs for their unit
            $unitId = $user->tenant->activeTenancy->unit_id;
            $accessLogs = SecurityLog::where('unit_id', $unitId)
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get()
                ->map(function($log) {
                    return $this->formatLogForView($log);
                });
                
            $pendingLogs = collect();
            $todayLogs = SecurityLog::where('unit_id', $unitId)
                ->whereDate('access_time', Carbon::today())
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get()
                ->map(function($log) {
                    return $this->formatLogForView($log);
                });
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
        
        return view('partials.dashboard.security', compact('roleData', 'company'));
    }
    
    /**
     * Format log for view
     */
    private function formatLogForView($log)
    {
        return (object) [
            'id' => $log->id,
            'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y g:i A') : 'N/A',
            'unit_number' => $log->unit->unit_number ?? 'N/A',
            'person_name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? 'Unknown'),
            'visitor_phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? null),
            'access_type' => $log->access_type,
            'access_type_label' => $log->access_type_label,
            'status' => $log->status,
            'status_label' => $log->status_label,
            'verified_by' => $log->verifiedByUser->name ?? $log->approved_by ?? 'System',
            'purpose' => $log->purpose,
            'notes' => $log->notes,
        ];
    }

    /**
     * Get security logs for display (API)
     */
    public function getLogs(Request $request)
    {
        $user = auth()->user();
        $query = SecurityLog::with(['unit', 'visitor', 'verifiedByUser'])
            ->latest('access_time');
        
        // Role-based filtering
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $query->where('unit_id', $user->tenant->activeTenancy->unit_id);
        } elseif (!$user->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        // Apply filters
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }
        
        if ($request->filled('estate_id')) {
            $query->where('estate_id', $request->estate_id);
        }
        
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('access_type') && $request->access_type !== 'all') {
            $query->where('access_type', $request->access_type);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('access_time', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('access_time', '<=', $request->date_to);
        }
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('visitor_name_snapshot', 'like', '%' . $request->search . '%')
                  ->orWhere('visitor_phone_snapshot', 'like', '%' . $request->search . '%')
                  ->orWhere('purpose', 'like', '%' . $request->search . '%');
            });
        }
        
        $limit = $request->limit ?? 20;
        $logs = $query->paginate($limit);
        
        return response()->json([
            'success' => true,
            'data' => $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y g:i A') : 'N/A',
                    'unit_number' => $log->unit->unit_number ?? 'N/A',
                    'person_name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? 'Unknown'),
                    'visitor_phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? null),
                    'access_type' => $log->access_type,
                    'access_type_label' => $log->access_type_label,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'verified_by' => $log->verifiedByUser->name ?? $log->approved_by ?? 'System',
                    'purpose' => $log->purpose,
                    'notes' => $log->notes,
                ];
            }),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'total_pages' => $logs->lastPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage()
            ]
        ]);
    }
    
    /**
     * Get single security log
     */
    public function show($id)
    {
        try {
            $log = SecurityLog::with(['unit', 'visitor', 'verifiedByUser', 'estate'])->findOrFail($id);
            
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
                    'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y H:i') : 'N/A',
                    'unit_id' => $log->unit_id,
                    'unit_number' => $log->unit->unit_number ?? 'N/A',
                    'estate_name' => $log->estate->name ?? 'N/A',
                    'person_name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? 'Unknown'),
                    'visitor_phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? null),
                    'visitor_id_number' => $log->visitor_id_number_snapshot ?? ($log->visitor->id_number ?? null),
                    'visitor_company' => $log->visitor_company_snapshot ?? ($log->visitor->company ?? null),
                    'vehicle_registration' => $log->vehicle_registration_snapshot,
                    'access_type' => $log->access_type,
                    'access_type_label' => $log->access_type_label,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'status_badge_class' => $log->status_color,
                    'verified_by' => $log->verifiedByUser->name ?? $log->approved_by ?? 'System',
                    'notes' => $log->notes,
                    'purpose' => $log->purpose,
                    'created_at' => $log->created_at ? $log->created_at->format('M d, Y H:i') : 'N/A',
                    'updated_at' => $log->updated_at ? $log->updated_at->format('M d, Y H:i') : 'N/A',
                    'images' => $log->images,
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('show Error:', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store security log
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_id' => 'required|exists:units,id',
            'visitor_name' => 'required|string|max:255',
            'access_type' => 'required|in:entry,exit,delivery,guest,maintenance,contractor,emergency,moving,inspection',
            'status' => 'required|in:pending,approved,denied,completed',
            'access_time' => 'required|date',
            'purpose' => 'nullable|string',
            'notes' => 'nullable|string',
            'visitor_id' => 'nullable|exists:visitors,id',
            'visitor_phone' => 'nullable|string|max:20',
            'visitor_id_number' => 'nullable|string|max:50',
            'visitor_company' => 'nullable|string|max:255',
            'vehicle_registration' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $user = auth()->user();
        $unit = Unit::find($request->unit_id);
        
        $log = SecurityLog::create([
            'company_id' => $user->company_id,
            'estate_id' => $unit->estate_id,
            'unit_id' => $request->unit_id,
            'tenant_id' => $unit->activeTenancy?->tenant_id,
            'visitor_id' => $request->visitor_id,
            'verified_by_user_id' => $user->id,
            'visitor_name_snapshot' => $request->visitor_name,
            'visitor_phone_snapshot' => $request->visitor_phone,
            'visitor_id_number_snapshot' => $request->visitor_id_number,
            'visitor_company_snapshot' => $request->visitor_company,
            'vehicle_registration_snapshot' => $request->vehicle_registration,
            'access_type' => $request->access_type,
            'status' => $request->status,
            'access_time' => Carbon::parse($request->access_time),
            'purpose' => $request->purpose,
            'notes' => $request->notes,
            'approved_by' => $request->status === 'approved' ? $user->name : null,
            'approved_at' => $request->status === 'approved' ? now() : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        // Update visitor visit count if visitor exists
        if ($request->visitor_id) {
            Visitor::where('id', $request->visitor_id)->increment('visit_count');
        }
        
        return response()->json([
            'success' => true, 
            'log' => $log, 
            'message' => 'Security log created successfully'
        ]);
    }
    
    /**
     * Update security log
     */
    public function update(Request $request, $id)
    {
        $log = SecurityLog::findOrFail($id);
        
        // Check authorization
        if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'unit_id' => 'required|exists:units,id',
            'visitor_name' => 'required|string|max:255',
            'access_type' => 'required|in:entry,exit,delivery,guest,maintenance,contractor,emergency,moving,inspection',
            'status' => 'required|in:pending,approved,denied,completed,expired',
            'access_time' => 'required|date',
            'purpose' => 'nullable|string',
            'notes' => 'nullable|string',
            'visitor_id' => 'nullable|exists:visitors,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $user = auth()->user();
        $unit = Unit::find($request->unit_id);
        
        $log->update([
            'estate_id' => $unit->estate_id,
            'unit_id' => $request->unit_id,
            'tenant_id' => $unit->activeTenancy?->tenant_id,
            'visitor_id' => $request->visitor_id,
            'visitor_name_snapshot' => $request->visitor_name,
            'visitor_phone_snapshot' => $request->visitor_phone ?? $log->visitor_phone_snapshot,
            'visitor_id_number_snapshot' => $request->visitor_id_number ?? $log->visitor_id_number_snapshot,
            'visitor_company_snapshot' => $request->visitor_company ?? $log->visitor_company_snapshot,
            'vehicle_registration_snapshot' => $request->vehicle_registration ?? $log->vehicle_registration_snapshot,
            'access_type' => $request->access_type,
            'status' => $request->status,
            'access_time' => Carbon::parse($request->access_time),
            'purpose' => $request->purpose,
            'notes' => $request->notes,
            'approved_by' => $request->status === 'approved' ? ($log->approved_by ?? $user->name) : $log->approved_by,
            'approved_at' => $request->status === 'approved' ? ($log->approved_at ?? now()) : $log->approved_at,
        ]);
        
        return response()->json([
            'success' => true, 
            'log' => $log, 
            'message' => 'Security log updated successfully'
        ]);
    }
    
    /**
     * Delete security log
     */
    public function destroy($id)
    {
        $log = SecurityLog::findOrFail($id);
        
        // Check authorization
        if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $log->delete();
        
        return response()->json([
            'success' => true, 
            'message' => 'Security log deleted successfully'
        ]);
    }
    
    /**
     * Approve/deny access
     */
    public function updateLogStatus(Request $request, $id)
    {
        $log = SecurityLog::findOrFail($id);
        
        // Check authorization
        if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'status' => 'required|in:approved,denied,completed,pending',
            'notes' => 'nullable|string',
        ]);
        
        $user = auth()->user();
        $log->update([
            'status' => $request->status,
            'approved_by' => $user->name,
            'approved_at' => now(),
            'notes' => $request->notes ?? $log->notes,
        ]);
        
        if ($request->status === 'approved' && $log->visitor) {
            $log->visitor->increment('visit_count');
        }
        
        return response()->json([
            'success' => true,
            'message' => "Access {$request->status} successfully",
            'log' => $log
        ]);
    }
    
    /**
     * Quick entry by phone or ID number (Original - kept for backward compatibility)
     */
    public function quickEntryOriginal(Request $request)
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
        $user = auth()->user();
        
        // Create security log
        $log = SecurityLog::create([
            'company_id' => $user->company_id,
            'estate_id' => $unit->estate_id,
            'unit_id' => $unit->id,
            'tenant_id' => $unit->activeTenancy?->tenant_id,
            'visitor_id' => $visitor->id,
            'verified_by_user_id' => $user->id,
            'visitor_name_snapshot' => $visitor->name,
            'visitor_phone_snapshot' => $visitor->phone,
            'visitor_id_number_snapshot' => $visitor->id_number,
            'visitor_company_snapshot' => $visitor->company,
            'vehicle_registration_snapshot' => $request->vehicle_registration ?? null,
            'access_type' => $request->access_type,
            'status' => $visitor->is_pre_approved ? 'approved' : 'pending',
            'access_time' => now(),
            'purpose' => $request->purpose ?? $visitor->visitor_type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        // Update visitor stats
        $visitor->increment('visit_count');
        
        return response()->json([
            'success' => true,
            'message' => $log->status === 'approved' ? 'Access granted. Welcome!' : 'Access request submitted for approval.',
            'visitor' => [
                'id' => $visitor->id,
                'name' => $visitor->name,
                'type' => $visitor->visitor_type ?? 'Guest',
                'is_registered' => $visitor->is_registered ?? false,
            ],
            'log' => $log
        ]);
    }
    
    /**
     * Register new visitor
     */
    public function registerVisitor(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'id_number' => 'nullable|string',
            'id_type' => 'nullable|string',
            'visitor_type' => 'nullable|string|in:family,employee,contractor,regular_guest,delivery,maintenance,one_time',
            'relationship' => 'nullable|string',
            'company' => 'nullable|string',
            'vehicles' => 'nullable|array',
            'is_registered' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:today',
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
                'name' => $request->name,
                'visitor_type' => $request->visitor_type ?? $visitor->visitor_type,
                'relationship' => $request->relationship ?? $visitor->relationship,
                'company' => $request->company ?? $visitor->company,
                'is_registered' => $request->is_registered ?? $visitor->is_registered,
                'valid_from' => $request->valid_from ?? $visitor->valid_from,
                'valid_until' => $request->valid_until ?? $visitor->valid_until,
            ]);
            
            if ($request->vehicles) {
                $visitor->vehicles = json_encode($request->vehicles);
                $visitor->save();
            }
            
            $message = 'Visitor information updated successfully';
        } else {
            // Create new visitor
            $visitor = Visitor::create([
                'company_id' => auth()->user()->company_id,
                'estate_id' => $request->estate_id ?? null,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'id_number' => $request->id_number,
                'id_type' => $request->id_type ?? 'national_id',
                'visitor_type' => $request->visitor_type ?? 'one_time',
                'relationship' => $request->relationship,
                'company' => $request->company,
                'vehicles' => $request->vehicles ? json_encode($request->vehicles) : null,
                'is_registered' => $request->is_registered ?? false,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'is_active' => true,
                'is_blacklisted' => false,
                'visit_count' => 0,
            ]);
            
            $message = 'Visitor registered successfully';
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'visitor' => $visitor
        ]);
    }
    
    /**
     * Get estates for the current user's company
     */
    public function getEstates()
    {
        $user = auth()->user();
        $companyId = $user->company_id;
        
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
     * Get units for a specific estate
     */
    public function getUnitsByEstate(Request $request)
    {
        $request->validate([
            'estate_id' => 'required|exists:estates,id',
        ]);
        
        $units = Unit::where('estate_id', $request->estate_id)
            ->where('is_active', true)
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
        $unit = $tenant->activeTenancy?->unit;
        
        $visitors = Visitor::where('registered_by_tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($visitor) use ($unit) {
                $recentLog = SecurityLog::where('visitor_id', $visitor->id)
                    ->where('unit_id', $unit?->id)
                    ->latest('access_time')
                    ->first();
                
                return [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'phone' => $visitor->phone,
                    'id_number' => $visitor->id_number,
                    'visitor_type' => $visitor->visitor_type,
                    'company' => $visitor->company,
                    'vehicles' => $visitor->vehicles,
                    'is_active' => $visitor->is_active ?? true,
                    'is_blacklisted' => $visitor->is_blacklisted ?? false,
                    'valid_until' => $visitor->valid_until,
                    'visit_count' => $visitor->visit_count ?? 0,
                    'last_visit_at' => $visitor->last_visit_at,
                    'recent_log' => $recentLog ? [
                        'access_time' => $recentLog->access_time,
                        'status' => $recentLog->status,
                    ] : null,
                ];
            });
        
        return response()->json([
            'success' => true,
            'visitors' => $visitors,
        ]);
    }
    
    /**
     * Get security data for dropdowns
     */
    public function getSecurityData()
    {
        $user = auth()->user();
        $companyId = $user->company_id;
        
        if ($user->hasRole('sysadmin')) {
            $estates = Estate::orderBy('name')->get();
        } else {
            $estates = Estate::where('company_id', $companyId)->orderBy('name')->get();
        }
        
        $unitsQuery = Unit::where('is_active', true);
        if (!$user->hasRole('sysadmin')) {
            $unitsQuery->where('company_id', $companyId);
        }
        $units = $unitsQuery->with('estate')->get();
        
        return response()->json([
            'success' => true,
            'estates' => $estates->map(function($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                ];
            }),
            'units' => $units->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_id' => $unit->estate_id,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                ];
            }),
        ]);
    }

    // ============================================
    // CURRENTLY IN METHODS
    // ============================================

    /**
     * Get all people currently IN the estate
     */
    public function currentlyIn(Request $request)
    {
        try {
            $user = auth()->user();
            $companyId = $user->company_id;
            $estateId = $request->estate_id ?? null;
            $people = collect();

            // 1. Get active tenants (those with active tenancy)
            $tenants = Tenant::where('company_id', $companyId)
                ->whereHas('activeTenancy', function($query) use ($estateId) {
                    $query->where('status', 'active');
                    if ($estateId) {
                        $query->where('estate_id', $estateId);
                    }
                })
                ->with(['user', 'activeTenancy.unit'])
                ->get();

            foreach ($tenants as $tenant) {
                $unit = $tenant->activeTenancy?->unit;
                $people->push([
                    'id' => 'tenant_' . $tenant->id,
                    'person_name' => $tenant->user->name ?? 'Unknown Resident',
                    'unit_number' => $unit->unit_number ?? 'N/A',
                    'visitor_phone' => $tenant->user->phone ?? null,
                    'access_time' => $tenant->activeTenancy?->created_at ?? now(),
                    'access_time_formatted' => Carbon::parse($tenant->activeTenancy?->created_at ?? now())->format('M d, Y g:i A'),
                    'duration' => $this->calculateDuration($tenant->activeTenancy?->created_at ?? now()),
                    'vehicle' => null,
                    'purpose' => 'Resident',
                    'is_tenant' => true,
                    'security_log_id' => null,
                    'visiting' => null,
                    'check_out_url' => null,
                ]);
            }

            // 2. Get visitors currently in (exit_time IS NULL)
            $visitorLogs = SecurityLog::where('company_id', $companyId)
                ->where('access_type', 'entry')
                ->whereNull('exit_time')
                ->whereNotIn('status', ['denied', 'expired'])
                ->with(['unit', 'visitor', 'tenant.user'])
                ->get();

            foreach ($visitorLogs as $log) {
                if ($log->visitor && $log->visitor->is_blacklisted) {
                    continue;
                }

                $name = $log->visitor_name_snapshot 
                    ?? ($log->visitor->name ?? 'Unknown Visitor');
                
                $phone = $log->visitor_phone_snapshot 
                    ?? ($log->visitor->phone ?? null);
                
                $vehicle = $log->vehicle_registration_snapshot;

                $unitNumber = $log->unit->unit_number ?? 'N/A';
                
                $visiting = null;
                if ($log->tenant && $log->tenant->user) {
                    $visiting = $log->tenant->user->name;
                } elseif ($log->unit && $log->unit->activeTenancy && $log->unit->activeTenancy->tenant && $log->unit->activeTenancy->tenant->user) {
                    $visiting = $log->unit->activeTenancy->tenant->user->name;
                }

                $purpose = $log->purpose ?? ($log->visitor->visitor_type ?? 'Guest');

                $people->push([
                    'id' => $log->id,
                    'person_name' => $name,
                    'unit_number' => $unitNumber,
                    'visitor_phone' => $phone,
                    'access_time' => $log->access_time,
                    'access_time_formatted' => $log->access_time ? $log->access_time->format('M d, Y g:i A') : 'N/A',
                    'duration' => $this->calculateDuration($log->access_time),
                    'vehicle' => $vehicle,
                    'purpose' => $purpose,
                    'is_tenant' => false,
                    'security_log_id' => $log->id,
                    'visiting' => $visiting,
                    'check_out_url' => route('security.checkout', $log->id),
                ]);
            }

            $people = $people->sortByDesc('access_time')->values();
            $residentsCount = $people->where('is_tenant', true)->count();
            $visitorsCount = $people->where('is_tenant', false)->count();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'people' => $people,
                    'total' => $people->count(),
                    'residents_count' => $residentsCount,
                    'visitors_count' => $visitorsCount,
                ]);
            }

            return view('security.currently-in', [
                'people' => $people,
                'total' => $people->count(),
                'residentsCount' => $residentsCount,
                'visitorsCount' => $visitorsCount,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in currentlyIn: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error loading currently IN data: ' . $e->getMessage(),
                    'people' => [],
                    'total' => 0,
                ], 500);
            }

            return back()->with('error', 'Error loading currently IN data: ' . $e->getMessage());
        }
    }

    /**
     * Check out a person (visitor) from the estate
     */
    public function checkOut(Request $request, $id)
    {
        try {
            $log = SecurityLog::findOrFail($id);

            $user = auth()->user();
            if (!$user->hasAnyRole(['super_admin', 'admin', 'security'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - You do not have permission to check out visitors.'
                ], 403);
            }

            if ($log->exit_time !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'This person has already been checked out.'
                ], 400);
            }

            $log->update([
                'exit_time' => now(),
                'status' => 'completed',
                'notes' => ($log->notes ? $log->notes . ' | ' : '') . 'Checked out by ' . $user->name . ' at ' . now()->format('Y-m-d H:i:s'),
            ]);

            if ($log->visitor_id) {
                $visitor = Visitor::find($log->visitor_id);
                if ($visitor) {
                    $visitor->update(['last_visit_at' => now()]);
                    $visitor->increment('visit_count');
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Person checked out successfully.',
                'log' => $log,
                'checked_out_at' => now()->format('M d, Y g:i A')
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in checkOut: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error checking out: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate duration from access time to now
     */
    private function calculateDuration($accessTime)
    {
        if (!$accessTime) return 'N/A';
        
        $now = now();
        $diff = $now->diff($accessTime);
        
        if ($diff->d > 0) {
            return $diff->d . 'd ' . $diff->h . 'h ' . $diff->i . 'm';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        } elseif ($diff->i > 0) {
            return $diff->i . 'm';
        } else {
            return 'Just now';
        }
    }

    /**
     * Quick entry - register visitor and create security log (FINAL FIXED VERSION)
     */
    public function quickEntry(Request $request)
    {
        try {
            Log::info('Quick Entry Request Data:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'person_name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'id_number' => 'nullable|string|max:50',
                'visitor_type' => 'nullable|string|in:family,employee,contractor,regular_guest,delivery,maintenance,one_time',
                'estate_id' => 'required|exists:estates,id',
                'unit_id' => 'required|exists:units,id',
                'access_type' => 'required|in:entry,exit,delivery,guest,maintenance,contractor,emergency,moving,inspection',
                'status' => 'nullable|in:pending,approved,denied',
                'purpose' => 'nullable|string',
                'notes' => 'nullable|string',
                'visitor_id' => 'nullable|exists:visitors,id',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $unit = Unit::findOrFail($request->unit_id);

            // Find existing visitor
            $visitor = null;
            
            if ($request->visitor_id) {
                $visitor = Visitor::find($request->visitor_id);
            }
            
            if (!$visitor && $request->phone) {
                $visitor = Visitor::where('phone', $request->phone)
                    ->where('company_id', $user->company_id)
                    ->first();
            }
            
            if (!$visitor && $request->id_number) {
                $visitor = Visitor::where('id_number', $request->id_number)
                    ->where('company_id', $user->company_id)
                    ->first();
            }

            // Create new visitor if not exists
            if (!$visitor) {
                $visitor = Visitor::create([
                    'company_id' => $user->company_id,
                    'estate_id' => $request->estate_id,
                    'name' => $request->person_name,
                    'phone' => $request->phone,
                    'email' => $request->email ?? null,
                    'id_number' => $request->id_number ?? null,
                    'id_type' => 'national_id',
                    'visitor_type' => $request->visitor_type ?? 'one_time',
                    'is_active' => true,
                    'is_blacklisted' => false,
                    'is_registered' => false,
                    'visit_count' => 0,
                ]);
                Log::info('Visitor created with ID: ' . $visitor->id);
            } else {
                // Update existing visitor
                $visitor->update([
                    'estate_id' => $request->estate_id,
                    'name' => $request->person_name,
                    'phone' => $request->phone,
                    'email' => $request->email ?? $visitor->email,
                    'id_number' => $request->id_number ?? $visitor->id_number,
                    'visitor_type' => $request->visitor_type ?? $visitor->visitor_type,
                ]);
                Log::info('Updated existing visitor: ' . $visitor->id);
            }

            // Check blacklist
            if ($visitor->is_blacklisted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied - Visitor is blacklisted.'
                ], 403);
            }

            // Create security log
            $status = $request->status ?? 'approved';
            $log = SecurityLog::create([
                'company_id' => $user->company_id,
                'estate_id' => $request->estate_id,
                'unit_id' => $request->unit_id,
                'tenant_id' => $unit->activeTenancy?->tenant_id,
                'visitor_id' => $visitor->id,
                'verified_by_user_id' => $user->id,
                'visitor_name_snapshot' => $visitor->name ?? $request->person_name,
                'visitor_phone_snapshot' => $visitor->phone ?? $request->phone,
                'visitor_id_number_snapshot' => $visitor->id_number ?? $request->id_number,
                'visitor_company_snapshot' => $visitor->company ?? null,
                'vehicle_registration_snapshot' => $request->vehicle_registration ?? null,
                'access_type' => $request->access_type,
                'status' => $status,
                'access_time' => now(),
                'purpose' => $request->purpose ?? $request->access_type,
                'notes' => $request->notes,
                'approved_by' => $status === 'approved' ? $user->name : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            Log::info('Security log created with ID: ' . $log->id);

            // Update visitor stats
            $visitor->increment('visit_count');
            $visitor->update(['last_visit_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => '✅ Visitor checked in successfully!',
                'log' => $log,
                'visitor' => [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in quickEntry: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for visitor by phone, ID number, or name
     */
    public function searchVisitor(Request $request)
    {
        try {
            $request->validate([
                'lookup_by' => 'required|in:phone,id_number,name',
                'lookup_value' => 'required|string|min:2',
            ]);

            $user = auth()->user();
            $companyId = $user->company_id;
            $lookupBy = $request->lookup_by;
            $lookupValue = $request->lookup_value;

            $query = Visitor::where('company_id', $companyId);

            if ($lookupBy === 'phone') {
                $query->where('phone', 'like', '%' . $lookupValue . '%');
            } elseif ($lookupBy === 'id_number') {
                $query->where('id_number', 'like', '%' . $lookupValue . '%');
            } elseif ($lookupBy === 'name') {
                $query->where('name', 'like', '%' . $lookupValue . '%');
            }

            $query->where('is_blacklisted', false);
            $visitor = $query->first();

            if ($visitor) {
                return response()->json([
                    'success' => true,
                    'visitor' => [
                        'id' => $visitor->id,
                        'name' => $visitor->name,
                        'phone' => $visitor->phone,
                        'email' => $visitor->email,
                        'id_number' => $visitor->id_number,
                        'visitor_type' => $visitor->visitor_type,
                        'estate_id' => $visitor->estate_id,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Visitor not found'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in searchVisitor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching visitor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get estates data for quick entry dropdown
     */
    public function getEstatesData()
    {
        try {
            $user = auth()->user();
            $companyId = $user->company_id;

            $estates = Estate::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'estates' => $estates
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getEstatesData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading estates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all units data for quick entry dropdown
     */
    public function getAllUnitsData()
    {
        try {
            $user = auth()->user();
            $companyId = $user->company_id;

            $units = Unit::where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['estate'])
                ->get()
                ->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'estate_id' => $unit->estate_id,
                        'estate_name' => $unit->estate->name ?? 'N/A',
                    ];
                });

            return response()->json([
                'success' => true,
                'units' => $units
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getAllUnitsData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading units: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load initial data for security module
     */
    public function loadData()
    {
        try {
            $user = auth()->user();
            $companyId = $user->company_id;

            $estates = Estate::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $units = Unit::where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['estate'])
                ->get()
                ->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'estate_id' => $unit->estate_id,
                        'estate_name' => $unit->estate->name ?? 'N/A',
                    ];
                });

            return response()->json([
                'success' => true,
                'estates' => $estates,
                'units' => $units,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in loadData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security logs by tenant
     */
    public function getSecurityLogsByTenant(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        try {
            $tenant = Tenant::with(['activeTenancy.unit'])->find($request->tenant_id);
            $unitId = $tenant->activeTenancy?->unit_id;

            if (!$unitId) {
                return response()->json([
                    'success' => true,
                    'logs' => [],
                    'message' => 'No active unit found for this tenant'
                ]);
            }

            $logs = SecurityLog::where('unit_id', $unitId)
                ->orWhere('tenant_id', $request->tenant_id)
                ->with(['visitor', 'verifiedByUser'])
                ->orderBy('access_time', 'desc')
                ->limit(50)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'visitor_name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? 'Unknown'),
                        'access_type' => $log->access_type_label,
                        'status' => $log->status_label,
                        'access_time' => $log->access_time ? $log->access_time->format('M d, Y g:i A') : 'N/A',
                        'verified_by' => $log->verifiedByUser->name ?? $log->approved_by ?? 'System',
                    ];
                });

            return response()->json([
                'success' => true,
                'logs' => $logs,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getSecurityLogsByTenant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading security logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for checkout (visitors currently in)
     */
    public function searchCheckout(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|min:2',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        try {
            $user = auth()->user();
            $companyId = $user->company_id;

            $query = SecurityLog::where('company_id', $companyId)
                ->where('access_type', 'entry')
                ->whereNull('exit_time')
                ->where('status', '!=', 'denied')
                ->where('status', '!=', 'expired')
                ->with(['unit', 'visitor']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('visitor_name_snapshot', 'like', '%' . $search . '%')
                        ->orWhere('visitor_phone_snapshot', 'like', '%' . $search . '%')
                        ->orWhere('vehicle_registration_snapshot', 'like', '%' . $search . '%');
                });
            }

            if ($request->filled('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            $logs = $query->limit(50)->get();

            return response()->json([
                'success' => true,
                'people' => $logs->map(function($log) {
                    return [
                        'id' => $log->id,
                        'name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? 'Unknown'),
                        'phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? null),
                        'unit_number' => $log->unit->unit_number ?? 'N/A',
                        'access_time' => $log->access_time ? $log->access_time->format('M d, Y g:i A') : 'N/A',
                        'vehicle' => $log->vehicle_registration_snapshot,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in searchCheckout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new visitor (FINAL FIXED VERSION - matches database schema)
     */
    public function storeVisitor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'id_number' => 'nullable|string|max:50|unique:visitors,id_number',
                'id_type' => 'nullable|string|max:50',
                'visitor_type' => 'required|string|in:family,employee,contractor,regular_guest,delivery,maintenance,one_time',
                'company' => 'nullable|string|max:255',
                'estate_id' => 'required|exists:estates,id',
                'is_registered' => 'boolean',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date',
                'notes' => 'nullable|string',
                'relationship' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            
            // Create visitor - matches visitors table schema
            $visitor = Visitor::create([
                'company_id' => $user->company_id,
                'estate_id' => $request->estate_id,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'id_number' => $request->id_number,
                'id_type' => $request->id_type ?? 'national_id',
                'visitor_type' => $request->visitor_type,
                'company' => $request->company,
                'is_registered' => $request->is_registered ?? false,
                'is_active' => true,
                'is_blacklisted' => false,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'notes' => $request->notes,
                'relationship' => $request->relationship,
                'visit_count' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Visitor registered successfully!',
                'visitor' => $visitor
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in storeVisitor: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error creating visitor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single visitor
     */
    public function getVisitor($id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'visitor' => [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'phone' => $visitor->phone,
                    'email' => $visitor->email,
                    'id_number' => $visitor->id_number,
                    'id_type' => $visitor->id_type,
                    'visitor_type' => $visitor->visitor_type,
                    'visitor_type_label' => $visitor->visitor_type_label,
                    'company' => $visitor->company,
                    'estate_id' => $visitor->estate_id,
                    'estate_name' => $visitor->estate->name ?? 'N/A',
                    'vehicles' => $visitor->vehicles,
                    'is_active' => $visitor->is_active,
                    'is_blacklisted' => $visitor->is_blacklisted,
                    'is_registered' => $visitor->is_registered,
                    'valid_from' => $visitor->valid_from,
                    'valid_until' => $visitor->valid_until,
                    'visit_count' => $visitor->visit_count ?? 0,
                    'last_visit_at' => $visitor->last_visit_at,
                    'created_at' => $visitor->created_at,
                    'notes' => $visitor->notes,
                    'relationship' => $visitor->relationship,
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getVisitor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Visitor not found'
            ], 404);
        }
    }

    /**
     * Update visitor (FINAL FIXED VERSION - matches database schema)
     */
    public function updateVisitor(Request $request, $id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'id_number' => 'nullable|string|max:50|unique:visitors,id_number,' . $id,
                'id_type' => 'nullable|string|max:50',
                'visitor_type' => 'required|string|in:family,employee,contractor,regular_guest,delivery,maintenance,one_time',
                'company' => 'nullable|string|max:255',
                'estate_id' => 'required|exists:estates,id',
                'is_active' => 'boolean',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date',
                'notes' => 'nullable|string',
                'relationship' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update visitor - matches visitors table schema
            $visitor->update([
                'estate_id' => $request->estate_id,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'id_number' => $request->id_number,
                'id_type' => $request->id_type ?? 'national_id',
                'visitor_type' => $request->visitor_type,
                'company' => $request->company,
                'is_active' => $request->is_active ?? $visitor->is_active,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'notes' => $request->notes,
                'relationship' => $request->relationship,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Visitor updated successfully!',
                'visitor' => $visitor
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in updateVisitor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating visitor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete visitor
     */
    public function deleteVisitor($id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            $visitor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Visitor deleted successfully!'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in deleteVisitor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting visitor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle blacklist status
     */
    public function toggleBlacklist(Request $request, $id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            $visitor->is_blacklisted = !$visitor->is_blacklisted;
            
            if ($visitor->is_blacklisted) {
                $visitor->blacklist_reason = $request->reason ?? 'No reason provided';
            } else {
                $visitor->blacklist_reason = null;
            }
            
            $visitor->save();

            return response()->json([
                'success' => true,
                'message' => $visitor->is_blacklisted ? 'Visitor blacklisted!' : 'Visitor removed from blacklist!',
                'is_blacklisted' => $visitor->is_blacklisted
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in toggleBlacklist: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating blacklist status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get visitor visit history
     */
    public function getVisitorHistory($id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            
            $logs = SecurityLog::where('visitor_id', $id)
                ->with(['unit', 'verifiedByUser'])
                ->orderBy('access_time', 'desc')
                ->limit(50)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'access_type' => $log->access_type_label,
                        'status' => $log->status_label,
                        'access_time' => $log->access_time ? $log->access_time->format('M d, Y g:i A') : 'N/A',
                        'unit_number' => $log->unit->unit_number ?? 'N/A',
                        'verified_by' => $log->verifiedByUser->name ?? $log->approved_by ?? 'System',
                    ];
                });

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'total_visits' => $visitor->visit_count ?? 0
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getVisitorHistory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading visitor history: ' . $e->getMessage()
            ], 500);
        }
    }
}