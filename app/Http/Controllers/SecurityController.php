<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\Security\Models\SecurityLog;
use App\Modules\Security\Models\Visitor;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Estate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SecurityController extends Controller
{
    // ============================================
    // 📊 DASHBOARD
    // ============================================
    
    /**
     * Display security dashboard with filters
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $units = Unit::with('estate')->get();
        
        // Build the query with filters
        $query = SecurityLog::with(['unit', 'visitor', 'verifiedByUser', 'estate'])
            ->latest('access_time');
        
        // Filter by date range
        if ($request->date_from) {
            $query->whereDate('access_time', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('access_time', '<=', $request->date_to);
        }
        
        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        $accessLogs = $query->get();
        
        $pendingLogs = SecurityLog::where('status', 'pending')
            ->with(['unit', 'visitor', 'verifiedByUser'])
            ->latest('access_time')
            ->get();
            
        $todayLogs = SecurityLog::whereDate('access_time', Carbon::today())
            ->with(['unit', 'visitor', 'verifiedByUser'])
            ->latest('access_time')
            ->get();
        
        // Create the roleData array that the view expects
        $roleData = [
            'accessLogs' => $accessLogs,
            'pendingLogs' => $pendingLogs,
            'todayLogs' => $todayLogs,
            'units' => $units,
        ];
        
        // Return the security view
        return view('security.index', compact('roleData'));
    }
    
    // ============================================
    // 📋 VIEW ALL LOGS
    // ============================================
    
    /**
     * View all security logs with filtering
     */
    public function viewLogs(Request $request)
    {
        try {
            $query = SecurityLog::with(['visitor', 'unit', 'tenant', 'verifiedByUser', 'estate'])
                ->latest('access_time');
            
            // Filter by date
            if ($request->date) {
                $query->whereDate('access_time', $request->date);
            }
            
            // Filter by estate
            if ($request->estate_id) {
                $query->where('estate_id', $request->estate_id);
            }
            
            // Filter by status
            if ($request->status && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            $logs = $query->paginate(20);
            $estates = Estate::all();
            
            return view('security.logs', compact('logs', 'estates'));
        } catch (\Exception $e) {
            Log::error('View Logs Error: ' . $e->getMessage());
            
            return view('security.logs', [
                'logs' => collect(),
                'estates' => Estate::all(),
                'error' => 'Error loading logs: ' . $e->getMessage()
            ]);
        }
    }
    
    // ============================================
    // 📊 STATS API
    // ============================================
    
    /**
     * Get security statistics for the dashboard
     */
    public function getStats()
    {
        try {
            $today = now()->toDateString();
            
            $stats = [
                'currently_inside' => SecurityLog::where('status', 'approved')
                    ->whereNull('exit_time')
                    ->count(),
                'today_visitors' => SecurityLog::whereDate('access_time', $today)->count(),
                'pending_approvals' => SecurityLog::where('status', 'pending')->count(),
                'today_check_ins' => SecurityLog::whereDate('access_time', $today)
                    ->where('access_type', 'entry')
                    ->count(),
                'today_check_outs' => SecurityLog::whereDate('exit_time', $today)
                    ->whereNotNull('exit_time')
                    ->count(),
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Stats Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading stats'
            ], 500);
        }
    }
    
    // ============================================
    // 📥 CHECK-IN / CHECK-OUT API
    // ============================================
    
    /**
     * Get all currently checked-in visitors
     */
    public function getCheckedIn()
    {
        try {
            $visitors = SecurityLog::with(['unit', 'visitor', 'tenant.user'])
                ->where('status', 'approved')
                ->whereNull('exit_time')
                ->orderBy('access_time', 'desc')
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'visitor_id' => $log->visitor_id,
                        'name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? 'Unknown'),
                        'phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? null),
                        'host_name' => $log->tenant->user->name ?? $log->tenant->name ?? 'N/A',
                        'unit_number' => $log->unit->unit_number ?? 'N/A',
                        'purpose' => $log->purpose ?? $log->access_type,
                        'check_in_time' => $log->access_time,
                        'status' => $log->status,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'visitors' => $visitors
            ]);
        } catch (\Exception $e) {
            Log::error('Get Checked In Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading checked-in visitors'
            ], 500);
        }
    }
    
    /**
     * Check out a visitor
     */
    public function checkOut(Request $request)
    {
        try {
            $request->validate([
                'visitor_id' => 'required|exists:security,id',
            ]);
            
            $log = SecurityLog::find($request->visitor_id);
            
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found'
                ], 404);
            }
            
            if ($log->exit_time) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor already checked out'
                ], 400);
            }
            
            $log->exit_time = now();
            $log->duration_hours = now()->diffInHours($log->access_time);
            $log->status = 'completed';
            $log->save();
            
            // Update visitor visit count
            if ($log->visitor_id) {
                $visitor = Visitor::find($log->visitor_id);
                if ($visitor) {
                    $visitor->increment('visit_count');
                    $visitor->last_visit_at = now();
                    $visitor->save();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Visitor checked out successfully',
                'log' => $log
            ]);
        } catch (\Exception $e) {
            Log::error('Check Out Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking out visitor: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ============================================
    // 📝 QUICK ENTRY
    // ============================================
    
    /**
     * Show Quick Entry form
     */
    public function quickEntryView()
    {
        try {
            $estates = Estate::all();
            $units = Unit::with('estate')->get();
            return view('security.quick-entry', compact('estates', 'units'));
        } catch (\Exception $e) {
            Log::error('Quick Entry View Error: ' . $e->getMessage());
            return redirect()->route('security.index')->with('error', 'Error loading form');
        }
    }
    
    /**
     * Store Quick Entry
     */
    public function quickEntryStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'unit_id' => 'required|exists:units,id',
                'purpose' => 'required|string|max:255',
                'id_number' => 'nullable|string|max:50',
                'id_type' => 'nullable|string|max:50',
                'company' => 'nullable|string|max:255',
                'vehicle_registration' => 'nullable|string|max:50',
                'vehicle_type' => 'nullable|string|max:50',
                'vehicle_model' => 'nullable|string|max:100',
                'vehicle_color' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
                'visitor_id' => 'nullable|exists:visitors,id',
            ]);
            
            // Check if visitor_id is provided (existing visitor)
            if (!empty($validated['visitor_id'])) {
                $visitor = Visitor::find($validated['visitor_id']);
                if ($visitor) {
                    $visitor->update([
                        'name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'id_number' => $validated['id_number'] ?? $visitor->id_number,
                        'id_type' => $validated['id_type'] ?? $visitor->id_type,
                        'company' => $validated['company'] ?? $visitor->company,
                    ]);
                }
            } else {
                // Check if visitor already exists by phone
                $visitor = Visitor::where('phone', $validated['phone'])->first();
                
                if (!$visitor) {
                    $visitor = Visitor::create([
                        'name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'id_number' => $validated['id_number'] ?? null,
                        'id_type' => $validated['id_type'] ?? null,
                        'company' => $validated['company'] ?? null,
                        'visitor_type' => 'one_time',
                        'is_registered' => 0,
                        'is_active' => 1,
                        'company_id' => auth()->user()->company_id ?? null,
                        'estate_id' => Unit::find($validated['unit_id'])->estate_id ?? null,
                    ]);
                } else {
                    if ($visitor->name !== $validated['name']) {
                        $visitor->update(['name' => $validated['name']]);
                    }
                }
            }
            
            // Get unit and tenant
            $unit = Unit::find($validated['unit_id']);
            $tenant = $unit->activeTenancy?->tenant;
            
            // Create security log
            $log = SecurityLog::create([
                'company_id' => auth()->user()->company_id ?? null,
                'estate_id' => $unit->estate_id ?? null,
                'unit_id' => $validated['unit_id'],
                'tenant_id' => $tenant->id ?? null,
                'visitor_id' => $visitor->id,
                'visitor_name_snapshot' => $validated['name'],
                'visitor_phone_snapshot' => $validated['phone'],
                'visitor_id_number_snapshot' => $validated['id_number'] ?? null,
                'visitor_company_snapshot' => $validated['company'] ?? null,
                'vehicle_registration_snapshot' => $validated['vehicle_registration'] ?? null,
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'vehicle_model' => $validated['vehicle_model'] ?? null,
                'vehicle_color' => $validated['vehicle_color'] ?? null,
                'access_type' => 'entry',
                'status' => 'approved',
                'access_time' => now(),
                'purpose' => $validated['purpose'],
                'notes' => $validated['notes'] ?? null,
                'verified_by_user_id' => auth()->id(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Visitor checked in successfully',
                'visitor' => $visitor,
                'log' => $log
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Quick Entry Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ============================================
    // 📋 FULL ENTRY
    // ============================================
    
    /**
     * Show Full Entry form
     */
    public function fullEntryView()
    {
        try {
            $estates = Estate::all();
            $units = Unit::with('estate')->get();
            return view('security.full-entry', compact('estates', 'units'));
        } catch (\Exception $e) {
            Log::error('Full Entry View Error: ' . $e->getMessage());
            return redirect()->route('security.index')->with('error', 'Error loading form');
        }
    }
    
    /**
     * Store Full Entry
     */
    public function fullEntryStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'id_number' => 'required|string|max:50',
                'id_type' => 'required|string|max:50',
                'unit_id' => 'required|exists:units,id',
                'purpose' => 'required|string|max:255',
                'visitor_type' => 'required|in:family,employee,contractor,regular_guest,delivery,maintenance,one_time',
                'company' => 'nullable|string|max:255',
                'relationship' => 'nullable|string|max:255',
                'vehicle_registration' => 'nullable|string|max:50',
                'vehicle_type' => 'nullable|string|max:50',
                'vehicle_model' => 'nullable|string|max:100',
                'vehicle_color' => 'nullable|string|max:50',
                'additional_personnel' => 'nullable|array',
                'additional_vehicles' => 'nullable|array',
                'expected_arrival' => 'nullable|date',
                'expected_departure' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);
            
            // Check if visitor already exists
            $visitor = Visitor::where('phone', $validated['phone'])
                ->orWhere('id_number', $validated['id_number'])
                ->first();
            
            if (!$visitor) {
                $visitor = Visitor::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'id_number' => $validated['id_number'],
                    'id_type' => $validated['id_type'],
                    'company' => $validated['company'] ?? null,
                    'visitor_type' => $validated['visitor_type'],
                    'relationship' => $validated['relationship'] ?? null,
                    'is_registered' => 1,
                    'is_active' => 1,
                    'company_id' => auth()->user()->company_id ?? null,
                    'estate_id' => Unit::find($validated['unit_id'])->estate_id ?? null,
                ]);
            } else {
                $visitor->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'id_number' => $validated['id_number'],
                    'id_type' => $validated['id_type'],
                    'company' => $validated['company'] ?? null,
                    'visitor_type' => $validated['visitor_type'],
                    'relationship' => $validated['relationship'] ?? null,
                ]);
            }
            
            // Get unit and tenant
            $unit = Unit::find($validated['unit_id']);
            $tenant = $unit->activeTenancy?->tenant;
            
            // Create security log
            $log = SecurityLog::create([
                'company_id' => auth()->user()->company_id ?? null,
                'estate_id' => $unit->estate_id ?? null,
                'unit_id' => $validated['unit_id'],
                'tenant_id' => $tenant->id ?? null,
                'visitor_id' => $visitor->id,
                'visitor_name_snapshot' => $validated['name'],
                'visitor_phone_snapshot' => $validated['phone'],
                'visitor_id_number_snapshot' => $validated['id_number'],
                'visitor_company_snapshot' => $validated['company'] ?? null,
                'vehicle_registration_snapshot' => $validated['vehicle_registration'] ?? null,
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'vehicle_model' => $validated['vehicle_model'] ?? null,
                'vehicle_color' => $validated['vehicle_color'] ?? null,
                'access_type' => 'entry',
                'status' => 'approved',
                'access_time' => now(),
                'purpose' => $validated['purpose'],
                'notes' => $validated['notes'] ?? null,
                'verified_by_user_id' => auth()->id(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Visitor registered and checked in successfully',
                'visitor' => $visitor,
                'log' => $log
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Full Entry Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ============================================
    // 📊 REPORTS
    // ============================================
    
    /**
     * Daily Report View
     */
    public function dailyReport(Request $request)
    {
        try {
            $date = $request->get('date', now()->toDateString());
            $dateObj = Carbon::parse($date);
            
            $visitors = SecurityLog::whereDate('access_time', $date)
                ->with(['unit', 'visitor', 'tenant.user'])
                ->orderBy('access_time', 'desc')
                ->get();
                
            $stats = [
                'total' => $visitors->count(),
                'checked_in' => $visitors->where('status', 'approved')->whereNull('exit_time')->count(),
                'checked_out' => $visitors->whereNotNull('exit_time')->count(),
                'pending' => $visitors->where('status', 'pending')->count(),
                'by_purpose' => $visitors->groupBy('purpose')->map->count(),
                'by_access_type' => $visitors->groupBy('access_type')->map->count(),
            ];
            
            return view('security.reports.daily', compact('visitors', 'stats', 'date', 'dateObj'));
        } catch (\Exception $e) {
            Log::error('Daily Report Error: ' . $e->getMessage());
            return redirect()->route('security.index')->with('error', 'Error loading daily report');
        }
    }
    
    /**
     * Trends Report View
     */
    public function trendsReport(Request $request)
    {
        try {
            $period = $request->get('period', 30);
            $startDate = now()->subDays($period);
            
            $dailyTrends = SecurityLog::where('access_time', '>=', $startDate)
                ->select(
                    DB::raw('DATE(access_time) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(CASE WHEN status = "approved" AND exit_time IS NULL THEN 1 END) as active'),
                    DB::raw('COUNT(CASE WHEN exit_time IS NOT NULL THEN 1 END) as completed')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();
                
            $purposeBreakdown = SecurityLog::where('access_time', '>=', $startDate)
                ->select('purpose', DB::raw('COUNT(*) as count'))
                ->groupBy('purpose')
                ->get();
                
            $accessTypeBreakdown = SecurityLog::where('access_time', '>=', $startDate)
                ->select('access_type', DB::raw('COUNT(*) as count'))
                ->groupBy('access_type')
                ->get();
                
            $topVisitors = SecurityLog::where('access_time', '>=', $startDate)
                ->select('visitor_name_snapshot', DB::raw('COUNT(*) as visit_count'))
                ->whereNotNull('visitor_name_snapshot')
                ->groupBy('visitor_name_snapshot')
                ->orderBy('visit_count', 'desc')
                ->limit(20)
                ->get();
            
            return view('security.reports.trends', compact(
                'dailyTrends', 
                'purposeBreakdown', 
                'accessTypeBreakdown', 
                'topVisitors', 
                'period',
                'startDate'
            ));
        } catch (\Exception $e) {
            Log::error('Trends Report Error: ' . $e->getMessage());
            return redirect()->route('security.index')->with('error', 'Error loading trends report');
        }
    }
    
    // ============================================
    // 📋 SINGLE LOG (API)
    // ============================================
    
    /**
     * Get single security log (API)
     */
    public function show($id)
    {
        try {
            $log = SecurityLog::with(['unit', 'visitor', 'estate'])->findOrFail($id);
            
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
                    'person_name' => $log->visitor_name_snapshot ?? ($log->visitor->full_name ?? $log->person_name ?? 'N/A'),
                    'visitor_phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? 'N/A'),
                    'access_type' => $log->access_type,
                    'access_type_label' => $log->access_type_label,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'verified_by' => $log->approved_by ?? $log->verified_by ?? 'System',
                    'notes' => $log->notes,
                    'purpose' => $log->purpose,
                    'vehicle' => $log->vehicle_registration_snapshot ?? null,
                    'vehicle_type' => $log->vehicle_type ?? null,
                    'vehicle_model' => $log->vehicle_model ?? null,
                    'vehicle_color' => $log->vehicle_color ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Show Log Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading log details'
            ], 500);
        }
    }
    
    // ============================================
    // 📋 OTHER LOGS & CRUD
    // ============================================
    
    // Store security log (API)
    public function store(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Store Log Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Update security log (API)
    public function update(Request $request, $id)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Update Log Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Delete security log (API)
    public function destroy($id)
    {
        try {
            $log = SecurityLog::findOrFail($id);
            
            // Check authorization
            if (!auth()->user()->hasAnyRole(['super_admin', 'admin', 'security'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $log->delete();
            
            return response()->json(['success' => true, 'message' => 'Security log deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Delete Log Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Quick entry by phone or ID number
    public function quickEntry(Request $request)
    {
        try {
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
                'visitor_name_snapshot' => $visitor->name,
                'person_name' => $visitor->name,
                'access_type' => $request->access_type,
                'status' => $visitor->is_pre_approved ? 'approved' : 'pending',
                'access_time' => now(),
                'purpose' => $request->purpose ?? $visitor->visitor_type,
                'created_by' => auth()->id()
            ]);
            
            // Update visitor stats
            $visitor->increment('total_visits');
            
            return response()->json([
                'success' => true,
                'message' => $log->status === 'approved' ? 'Access granted. Welcome!' : 'Access request submitted for approval.',
                'visitor' => [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'type' => $visitor->visitor_type_label,
                    'is_registered' => $visitor->is_registered,
                ],
                'log' => $log
            ]);
        } catch (\Exception $e) {
            Log::error('Quick Entry API Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // ============================================
    // 🏢 ESTATE / UNIT / TENANT API ENDPOINTS
    // ============================================

    /**
     * Get estates for the current user's company
     */
    public function getEstates()
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Get Estates Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading estates'], 500);
        }
    }

    /**
     * Get units for a specific estate with active tenancies
     */
    public function getUnitsByEstate(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Get Units Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading units'], 500);
        }
    }

    /**
     * Get tenants for a specific unit
     */
    public function getTenantsByUnit(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Get Tenants Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading tenants'], 500);
        }
    }

    /**
     * Get visitors for a specific tenant
     */
    public function getVisitorsByTenant(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Get Visitors Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading visitors'], 500);
        }
    }

    /**
     * Search for a visitor by phone or ID number
     */
    public function searchVisitor(Request $request)
    {
        try {
            $searchTerm = $request->get('q');
            
            if (empty($searchTerm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search term is required'
                ], 400);
            }
            
            // Search by phone or ID number
            $visitor = Visitor::where('phone', $searchTerm)
                ->orWhere('id_number', $searchTerm)
                ->first();
            
            if (!$visitor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'visitor' => [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'phone' => $visitor->phone,
                    'email' => $visitor->email,
                    'id_number' => $visitor->id_number,
                    'id_type' => $visitor->id_type,
                    'company' => $visitor->company,
                    'visitor_type' => $visitor->visitor_type,
                    'visitor_type_label' => $visitor->visitor_type_label,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Search Visitor Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error searching visitor'], 500);
        }
    }

    // ============================================
    // 📋 ACCESS RECORDS (Alias)
    // ============================================

    /**
     * Access records view
     */
    public function accessRecords(Request $request)
    {
        return $this->viewLogs($request);
    }

    /**
     * Tenant access logs
     */
    public function tenantAccessLogs(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user->hasRole('tenant') || !$user->tenant || !$user->tenant->activeTenancy) {
                return redirect()->route('security.index')
                    ->with('error', 'You do not have permission to view tenant logs.');
            }
            
            $unitId = $user->tenant->activeTenancy->unit_id;
            
            $query = SecurityLog::where('unit_id', $unitId)
                ->with(['visitor', 'tenant', 'verifiedByUser'])
                ->latest('access_time');
            
            if ($request->date) {
                $query->whereDate('access_time', $request->date);
            }
            
            if ($request->status && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            $logs = $query->paginate(20);
            
            return view('security.tenant-logs', compact('logs'));
        } catch (\Exception $e) {
            Log::error('Tenant Logs Error: ' . $e->getMessage());
            return redirect()->route('security.index')->with('error', 'Error loading tenant logs');
        }
    }

    /**
     * Report incident
     */
    public function reportIncident(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Incident reported successfully'
        ]);
    }

    /**
     * Logs view (alias for viewLogs)
     */
    public function logs(Request $request)
    {
        return $this->viewLogs($request);
    }

    /**
     * Get security logs by tenant
     */
    public function getSecurityLogsByTenant(Request $request)
    {
        try {
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
                        'visitor_name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? $log->person_name),
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
        } catch (\Exception $e) {
            Log::error('Get Security Logs By Tenant Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading logs'], 500);
        }
    }

    /**
     * Get security data for the modal
     */
    public function getSecurityData()
    {
        try {
            $user = auth()->user();
            $companyId = $user->company_id;
            
            $estates = Estate::where('company_id', $companyId)
                ->orderBy('name')
                ->get()
                ->map(function($estate) {
                    return [
                        'id' => $estate->id,
                        'name' => $estate->name,
                    ];
                });
            
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
        } catch (\Exception $e) {
            Log::error('Get Security Data Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading data'], 500);
        }
    }

    /**
     * Register new visitor (one-time or recurring)
     */
    public function registerVisitor(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
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
                    'name' => $request->name,
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
        } catch (\Exception $e) {
            Log::error('Register Visitor Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get security logs for display (API)
     */
    public function getLogs(Request $request)
    {
        try {
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
                        'visitor_name' => $log->visitor_name_snapshot ?? ($log->visitor->name ?? $log->person_name),
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
        } catch (\Exception $e) {
            Log::error('Get Logs API Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading logs'], 500);
        }
    }

    /**
     * Approve/deny access
     */
    public function updateLogStatus(Request $request, SecurityLog $log)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Update Log Status Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}