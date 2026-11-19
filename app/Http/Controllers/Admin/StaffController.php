<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\User;
use App\Models\Company;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StaffController extends Controller
{
    /**
     * Get staff role names.
     */
    private function getStaffRoleNames(): array
    {
        return ['admin', 'property_manager', 'accountant', 'security', 'maintenance', 'meter_reader', 'cleaning_staff'];
    }

    /**
     * Display a listing of staff (users with staff roles).
     */
    public function index(Request $request)
    {
        Log::info('Staff filter request', $request->all());

        $staffRoleNames = $this->getStaffRoleNames();
        
        // Get users that have any of the staff roles using Spatie
        $query = User::with(['company'])
            ->whereHas('roles', function ($q) use ($staffRoleNames) {
                $q->whereIn('name', $staffRoleNames);
            });

        // ============================================================
        // FILTERS
        // ============================================================
        if ($request->filled('role_name')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role_name);
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->company_id);
        }

        if ($request->filled('status') && $request->status !== '') {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // ============================================================
        // DATE RANGE FILTERS
        // ============================================================
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ============================================================
        // SORTING
        // ============================================================
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // ============================================================
        // SELECT ALL ACROSS PAGES
        // ============================================================
        if ($request->has('select_all') && $request->select_all === 'true') {
            $allIds = $query->pluck('id')->toArray();
            return response()->json(['ids' => $allIds]);
        }

        // ============================================================
        // STATS ONLY (for AJAX update)
        // ============================================================
        if ($request->has('stats_only') && $request->stats_only === '1') {
            $total = $query->count();
            $active = (clone $query)->where('status', 0)->count();
            $pending = (clone $query)->where('status', 2)->count();
            $inactive = (clone $query)->where('status', 1)->count();
            return response()->json([
                'stats' => compact('total', 'active', 'pending', 'inactive')
            ]);
        }

        // ============================================================
        // PAGINATE
        // ============================================================
        $perPage = $request->get('per_page', 20);
        $staff = $query->paginate($perPage);

        Log::info('Staff count', ['total' => $staff->total()]);

        // Get roles for filter dropdown
        $roles = Role::whereIn('name', $staffRoleNames)->orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

        if ($request->wantsJson()) {
            // Add role names to each user for JSON response
            $staff->getCollection()->transform(function ($user) {
                $user->role_names = $user->getRoleNames()->toArray();
                $user->primary_role = $user->getRoleNames()->first();
                return $user;
            });
            
            return response()->json(['success' => true, 'staff' => $staff]);
        }

        $debugSql = $query->toSql();
        $debugBindings = $query->getBindings();

        return view('admin.staff.index', compact('staff', 'roles', 'companies', 'debugSql', 'debugBindings'));
    }

    /**
     * Show the form for creating a new staff member.
     */
    public function create()
    {
        $roles = Role::whereIn('name', $this->getStaffRoleNames())
            ->orderBy('name')
            ->get();
        $companies = Company::orderBy('name')->get();
        return view('admin.staff.create', compact('roles', 'companies'));
    }

    /**
     * Store a newly created staff member (AJAX + regular).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users', new \App\Rules\RealEmail],
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
            'role_id'  => 'required|exists:roles,id',
            'company_id' => ['nullable', new \App\Rules\CustomerCompany],
            'status'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->all()
            ], 422);
        }

        try {
            // Get the role
            $role = Role::findOrFail($request->role_id);

            // Create user
            $user = User::create([
                'name'       => $request->name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
                'phone'      => $request->phone,
                'company_id' => $request->company_id,
                'status'     => $request->status ?? 0,
            ]);

            // Assign role using Spatie
            $user->assignRole($role);

            return response()->json([
                'success' => true,
                'message' => 'Staff member created successfully!',
                'user'    => $user->load('company'),
                'role'    => $role->name,
            ]);

        } catch (\Exception $e) {
            \Log::error('Staff creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show single staff profile.
     */
    public function show($id)
    {
        $user = User::with(['company'])->findOrFail($id);
        $user->primary_role = $user->getRoleNames()->first();
        $user->role_names = $user->getRoleNames()->toArray();
        $user->all_roles = $user->roles;
        
        return view('admin.staff.show', compact('user'));
    }

    /**
     * Delete staff.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        if ($user->hasRole('sysadmin')) {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete sysadmin user.'
            ], 400);
        }
        
        $user->delete();
        
        return response()->json([
            'success' => true, 
            'message' => 'Staff deleted successfully.'
        ]);
    }

}