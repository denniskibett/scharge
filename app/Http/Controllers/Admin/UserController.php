<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\User;
use App\Models\Company;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'company']);
        
        // Filter by role (using Spatie's role relationship)
        if ($request->has('role') && $request->role) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }
        
        // Filter by role ID (for backward compatibility)
        if ($request->has('role_id') && $request->role_id) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('id', $request->role_id);
            });
        }
        
        // Filter by company
        if ($request->has('company_id') && $request->company_id) {
            $query->where('company_id', $request->company_id);
        }
        
        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }
        
        // Filter by verified status
        if ($request->has('verified') && $request->verified !== '') {
            if ($request->verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }
        
        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }
        
        // "Select all" across pages asks for every matching id (it got the HTML page back and failed).
        if ($request->query('select_all') === 'true') {
            return response()->json(['ids' => (clone $query)->pluck('id')->all()]);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Add role names to each user for display
        $users->getCollection()->transform(function ($user) {
            $user->primary_role = $user->getRoleNames()->first();
            $user->role_names = $user->getRoleNames()->toArray();
            return $user;
        });
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'users' => $users
            ]);
        }
        
        $roles = Role::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        
        return view('admin.users.index', compact('users', 'roles', 'companies'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        // People are added from the slide-over on the Users page.
        return redirect()->route('users.index');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'nullable|exists:roles,id',
            'company_id' => ['nullable', new \App\Rules\CustomerCompany],
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'company_id' => $request->company_id,
                'status' => $request->status ?? 0,
            ]);

            // Assign role using Spatie
            if ($request->role_id) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $user->assignRole($role->name);
                }
            } else {
                // Default to 'guest' role if no role specified
                $guestRole = Role::where('name', 'guest')->first();
                if ($guestRole) {
                    $user->assignRole($guestRole->name);
                }
            }

            DB::commit();

            // Reload with roles
            $user->load('roles');
            $user->primary_role = $user->getRoleNames()->first();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User created successfully!',
                    'user' => $user
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'User created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('User creation error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating user: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Error creating user: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['roles', 'company']);
        $user->primary_role = $user->getRoleNames()->first();
        $user->role_names = $user->getRoleNames()->toArray();
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'user' => $user
            ]);
        }
        
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $user->load('roles');
        $user->primary_role = $user->getRoleNames()->first();
        
        $roles = Role::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'roles', 'companies'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role_id' => 'nullable|exists:roles,id',
            'company_id' => ['nullable', new \App\Rules\CustomerCompany],
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Prevent updating sysadmin role
            if ($user->hasRole('sysadmin') && $request->role_id) {
                $newRole = Role::find($request->role_id);
                if ($newRole && $newRole->name !== 'sysadmin') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot change sysadmin role.'
                    ], 422);
                }
            }

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company_id' => $request->company_id,
                'status' => $request->status ?? $user->status,
            ]);

            // Update password if provided
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
                $user->save();
            }

            // Sync roles using Spatie
            if ($request->role_id) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            } else {
                // If no role specified, keep existing roles
                // Or uncomment to remove all roles:
                // $user->syncRoles([]);
            }

            DB::commit();

            $user->load('roles');
            $user->primary_role = $user->getRoleNames()->first();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User updated successfully!',
                    'user' => $user
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'User updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('User update error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating user: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Error updating user: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user)
    {
        try {
            // Prevent deleting sysadmin users
            if ($user->hasRole('sysadmin')) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete a system administrator.'
                    ], 400);
                }
                return back()->with('error', 'Cannot delete a system administrator.');
            }

            $userName = $user->name;
            $user->delete();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "User '{$userName}' deleted successfully!"
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('success', "User '{$userName}' deleted successfully!");

        } catch (\Exception $e) {
            \Log::error('User deletion error: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error deleting user: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Error deleting user: ' . $e->getMessage());
        }
    }

    /**
     * Verify a user (set email_verified_at)
     */
    public function verify(Request $request, User $user)
    {
        try {
            // Only sysadmin can verify users
            if (!auth()->user()->hasRole('sysadmin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only system administrators can verify users.'
                ], 403);
            }

            // Don't verify if already verified
            if ($user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already verified.'
                ], 400);
            }

            // Set email_verified_at to now
            $user->email_verified_at = now();
            $user->status = 0; // 0 = active
            // Activating also counts as verifying the email, so they don't land on the pending page.
            $user->email_verified_at ??= now();
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'email_confirmed_at') && empty($user->email_confirmed_at)) {
                $user->email_confirmed_at = now();
            }
            $user->save();

            return response()->json([
                'success' => true,
                'message' => "User '{$user->name}' verified successfully! They can now log in."
            ]);

        } catch (\Exception $e) {
            \Log::error('User verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying the user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a user to a company with a role
     */
    public function assignCompany(Request $request, User $user)
    {
        try {
            // Only sysadmin can assign companies
            if (!auth()->user()->hasRole('sysadmin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only system administrators can assign users to companies.'
                ], 403);
            }

            $validated = $request->validate([
                'company_id' => ['required', new \App\Rules\CustomerCompany],
                'role_id' => 'nullable|exists:roles,id'
            ]);

            $company = Company::find($validated['company_id']);
            
            // Start transaction
            DB::beginTransaction();

            try {
                // Assign user to company
                $user->company_id = $company->id;
                
                // Assign role using Spatie
                if (isset($validated['role_id'])) {
                    $role = Role::find($validated['role_id']);
                    if ($role) {
                        $user->syncRoles([$role->name]);
                    }
                }
                
                // Set email_verified_at to now so user is verified
                if (is_null($user->email_verified_at)) {
                    $user->email_verified_at = now();
                }
                
                $user->status = 0; // Active
                $user->save();

                // Also update the tenant if this user is a tenant
                if ($user->hasRole('tenant') && $user->tenant) {
                    $user->tenant->company_id = $company->id;
                    $user->tenant->save();
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "User '{$user->name}' assigned to '{$company->name}' and verified successfully!"
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', array_merge(...array_values($e->errors())))
            ], 422);
        } catch (\Exception $e) {
            \Log::error('User assignment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while assigning the user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of available roles (AJAX endpoint)
     */
    public function getRoles(Request $request)
    {
        try {
            $roles = Role::orderBy('name')
                ->when($request->has('exclude'), function ($query) use ($request) {
                    $exclude = explode(',', $request->exclude);
                    return $query->whereNotIn('name', $exclude);
                })
                ->get(['id', 'name'])
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'label' => ucfirst(str_replace('_', ' ', $role->name)),
                        'description' => \App\Support\RoleDescriptions::for($role),
                        'initial' => strtoupper(substr($role->name, 0, 1)),
                    ];
                });
            
            return response()->json([
                'success' => true,
                'roles' => $roles
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of users for dropdown/selection (AJAX endpoint)
     */
    public function getUsers(Request $request)
    {
        try {
            $query = User::select('id', 'name', 'email', 'company_id')
                ->with(['roles', 'company']);
            
            // Filter by role name (using Spatie)
            if ($request->has('role') && $request->role) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }
            
            // Filter by role ID (using Spatie)
            if ($request->has('role_id') && $request->role_id) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('id', $request->role_id);
                });
            }
            
            // Filter by company
            if ($request->has('company_id') && $request->company_id) {
                $query->where('company_id', $request->company_id);
            }
            
            // Filter by status
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }
            
            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            
            $users = $query->orderBy('name')->limit(50)->get();
            
            // Add role names for display
            $users->transform(function ($user) {
                $user->primary_role = $user->getRoleNames()->first();
                $user->role_names = $user->getRoleNames()->toArray();
                return $user;
            });
            
            return response()->json([
                'success' => true,
                'users' => $users
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend a user (set status = 1)
     */
    public function suspend(Request $request, User $user)
    {
        try {
            if (!auth()->user()->hasRole('sysadmin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 403);
            }

            // Don't suspend sysadmin users
            if ($user->hasRole('sysadmin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot suspend a system administrator.'
                ], 400);
            }

            $user->status = 1; // 1 = inactive/suspended
            $user->save();

            return response()->json([
                'success' => true,
                'message' => "User '{$user->name}' has been suspended."
            ]);

        } catch (\Exception $e) {
            \Log::error('User suspension error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a user (set status = 0)
     */
    public function activate(Request $request, User $user)
    {
        try {
            if (!auth()->user()->hasRole('sysadmin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 403);
            }

            $user->status = 0; // 0 = active
            $user->save();

            return response()->json([
                'success' => true,
                'message' => "User '{$user->name}' has been activated."
            ]);

        } catch (\Exception $e) {
            \Log::error('User activation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete users
     */
    public function bulkDelete(Request $request)
    {
        try {
            if (!auth()->user()->hasRole('sysadmin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 403);
            }

            $request->validate([
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id'
            ]);

            $userIds = $request->user_ids;
            
            // Don't delete sysadmin users
            $sysadminUsers = User::role('sysadmin')->whereIn('id', $userIds)->pluck('id')->toArray();
            $validIds = array_diff($userIds, $sysadminUsers);
            
            if (empty($validIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid users to delete (sysadmin users are protected).'
                ], 400);
            }

            $deleted = User::whereIn('id', $validIds)->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} users deleted successfully."
            ]);

        } catch (\Exception $e) {
            \Log::error('Bulk delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /** Users page bulk actions (sysadmin): activate, deactivate, change role, change company, delete. Sysadmins are never changed. */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:activate,deactivate,change_role,change_company,delete',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'role_id' => 'required_if:action,change_role|nullable|integer|exists:roles,id',
            'company_id' => ['required_if:action,change_company', 'nullable', new \App\Rules\CustomerCompany],
        ]);
        if ($data['action'] === 'delete') {
            return $this->bulkDelete($request);
        }
        $protected = User::role('sysadmin')->whereIn('id', $data['user_ids'])->pluck('id')->all();
        $users = User::whereIn('id', array_diff($data['user_ids'], $protected))->get();
        $role = $data['action'] === 'change_role' ? \Spatie\Permission\Models\Role::findById((int) $data['role_id'], 'web') : null;
        if ($role && $role->name === 'sysadmin') {
            return response()->json(['success' => false, 'error' => 'Sysadmin can only be given one person at a time, from their page.'], 422);
        }
        foreach ($users as $user) {
            match ($data['action']) {
                'activate' => $user->forceFill(['status' => 0])->save(),
                'deactivate' => $user->forceFill(['status' => 1])->save(),
                'change_role' => $user->syncRoles([$role->name]),
                'change_company' => $user->forceFill(['company_id' => (int) $data['company_id']])->save(),
            };
        }
        $skipped = count($protected) ? ' ('.count($protected).' sysadmin left unchanged)' : '';

        return response()->json(['success' => true, 'message' => $users->count().' users updated'.$skipped.'.']);
    }

    /** CSV of the users the Users page is showing (same filters), or of the ticked ones. */
    public function export(Request $request)
    {
        $q = User::query()->with(['roles:id,name', 'company:id,name']);
        if ($ids = array_filter(explode(',', (string) $request->ids))) {
            $q->whereIn('id', array_map('intval', $ids));
        }
        if ($s = trim((string) $request->search)) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }
        if ($request->filled('role_id')) {
            $q->whereHas('roles', fn ($r) => $r->where('id', (int) $request->role_id));
        }
        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->company_id);
        }
        if ($request->filled('status')) {
            $q->where('status', (int) $request->status);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Company', 'Status', 'Created']);
            $q->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $u) {
                    fputcsv($out, [$u->id, $u->name, $u->email, $u->phone, $u->roles->pluck('name')->implode(', '), $u->company?->name, (int) $u->status === 1 ? 'Inactive' : 'Active', optional($u->created_at)->format('Y-m-d')]);
                }
            });
            fclose($out);
        }, 'scharge-users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
