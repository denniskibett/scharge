<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SecurityLog;
use App\Models\Visitor;
use App\Models\Unit;
use App\Models\Tenant;
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
            $logs = SecurityLog::with(['unit', 'visitor'])
                ->latest('access_time')
                ->paginate(20);
                
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
            $logs = SecurityLog::where('unit_id', $unitId)
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->paginate(20);
                
            $pendingLogs = collect();
            $todayLogs = SecurityLog::where('unit_id', $unitId)
                ->whereDate('access_time', Carbon::today())
                ->with(['unit', 'visitor'])
                ->latest('access_time')
                ->get();
        } else {
            $logs = collect();
            $pendingLogs = collect();
            $todayLogs = collect();
        }
        
        return view('security.dashboard', compact('logs', 'pendingLogs', 'todayLogs', 'units'));
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
}