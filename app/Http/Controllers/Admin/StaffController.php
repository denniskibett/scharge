<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StaffController extends Controller
{
    /**
     * Display a listing of staff (users with staff roles).
     */
    public function index(Request $request)
    {
        Log::info('Staff filter request', $request->all());

        $staffRoleNames = ['admin', 'property_manager', 'accountant', 'security', 'maintenance', 'meter_reader', 'cleaning_staff'];
        $staffRoleIds = Role::whereIn('name', $staffRoleNames)->pluck('id')->toArray();

        $query = User::with(['role', 'company'])
            ->whereIn('role_id', $staffRoleIds);

        // ============================================================
        // FILTERS
        // ============================================================
        if ($request->filled('role_name')) {
            $role = Role::where('name', $request->role_name)->first();
            if ($role) {
                $query->where('role_id', $role->id);
            } else {
                $query->whereRaw('1 = 0');
            }
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
        // 🆕 DATE RANGE FILTERS
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
        // 🆕 SELECT ALL ACROSS PAGES
        // ============================================================
        if ($request->has('select_all') && $request->select_all === 'true') {
            $allIds = $query->pluck('id')->toArray();
            return response()->json(['ids' => $allIds]);
        }

        // ============================================================
        // 🆕 STATS ONLY (for AJAX update)
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

        Log::info('Staff query SQL', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
        Log::info('Staff count', ['total' => $staff->total()]);

        $roles = Role::whereIn('name', $staffRoleNames)->orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

        if ($request->wantsJson()) {
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
        $roles = Role::whereIn('name', ['admin', 'property_manager', 'accountant', 'security', 'maintenance', 'meter_reader', 'cleaning_staff'])
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
        'email'    => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
        'phone'    => 'nullable|string|max:20',
        'role_id'  => 'required|exists:roles,id',
        'company_id' => 'nullable|exists:companies,id',
        'status'   => 'boolean',
    ]);

    if ($validator->fails()) {
        // Always return JSON for AJAX requests
        return response()->json([
            'success' => false,
            'errors'  => $validator->errors()->all()
        ], 422);
    }

    try {
        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'phone'      => $request->phone,
            'role_id'    => $request->role_id,
            'company_id' => $request->company_id,
            'status'     => $request->status ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member created successfully!',
            'user'    => $user
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
     * AJAX filter endpoint.
     */
    public function filter(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Bulk actions.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'action'     => 'required|in:activate,deactivate,change_role,change_company,delete',
        ]);

        $userIds = $request->user_ids;
        $action = $request->action;

        try {
            switch ($action) {
                case 'activate':
                    User::whereIn('id', $userIds)->update(['status' => 0]);
                    $message = 'Staff activated successfully.';
                    break;
                case 'deactivate':
                    User::whereIn('id', $userIds)->update(['status' => 1]);
                    $message = 'Staff deactivated successfully.';
                    break;
                case 'change_role':
                    $request->validate(['role_id' => 'required|exists:roles,id']);
                    User::whereIn('id', $userIds)->update(['role_id' => $request->role_id]);
                    $message = 'Role updated successfully.';
                    break;
                case 'change_company':
                    $request->validate(['company_id' => 'required|exists:companies,id']);
                    User::whereIn('id', $userIds)->update(['company_id' => $request->company_id]);
                    $message = 'Company updated successfully.';
                    break;
                case 'delete':
                    User::whereIn('id', $userIds)->delete();
                    $message = 'Staff deleted successfully.';
                    break;
                default:
                    return response()->json(['error' => 'Invalid action.'], 400);
            }

            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            Log::error('Bulk action error: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    /**
     * Quick update.
     */
    public function quickUpdate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'status' => 'required|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($id);
        $user->update($request->only(['name', 'email', 'phone', 'role_id', 'company_id', 'status']));
        $user->load(['role', 'company']);

        $rowHtml = view('admin.staff.partials._row', compact('user'))->render();

        return response()->json([
            'success' => true,
            'message' => 'Staff updated successfully.',
            'row'     => $rowHtml,
        ]);
    }

    /**
     * 🆕 Export to CSV (enhanced with selected IDs, date range, and last login).
     */
    public function export(Request $request)
    {
        $staffRoleNames = ['admin', 'property_manager', 'accountant', 'security', 'maintenance', 'meter_reader', 'cleaning_staff'];
        $staffRoleIds = Role::whereIn('name', $staffRoleNames)->pluck('id')->toArray();

        $query = User::with(['role', 'company'])->whereIn('role_id', $staffRoleIds);

        // Apply filters (same as index)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }
        if ($request->filled('role_name')) {
            $role = Role::where('name', $request->role_name)->first();
            if ($role) {
                $query->where('role_id', $role->id);
            }
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->company_id);
        }
        if ($request->filled('status') && $request->status !== '') {
            $query->where('status', (int) $request->status);
        }
        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // 🆕 Export only selected IDs
        if ($request->filled('ids')) {
            $ids = explode(',', $request->ids);
            $query->whereIn('id', $ids);
        }

        $users = $query->get();

        $filename = 'staff-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($users) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            // 🆕 Added "Last Login" column
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Company', 'Status', 'Created At', 'Last Login']);
            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone ?? '',
                    $user->role->name ?? 'N/A',
                    $user->company->name ?? 'N/A',
                    $user->status == 0 ? 'Active' : ($user->status == 1 ? 'Inactive' : 'Pending'),
                    $user->created_at->format('Y-m-d H:i'),
                    $user->last_login_at?->format('Y-m-d H:i') ?? 'Never',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show single staff profile.
     */
    public function show($id)
    {
        $user = User::with(['role', 'company'])->findOrFail($id);
        return view('admin.staff.show', compact('user'));
    }

    /**
     * Delete staff.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($user->hasRole('sysadmin')) {
            return response()->json(['success' => false, 'message' => 'Cannot delete sysadmin.'], 400);
        }
        $user->delete();
        return response()->json(['success' => true, 'message' => 'Staff deleted.']);
    }
}