<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
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
        $query = User::with(['role', 'company']);
        
        // Filter by role
        if ($request->has('role_id') && $request->role_id) {
            $query->where('role_id', $request->role_id);
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
        
        $users = $query->orderBy('created_at', 'desc')->paginate(20);
        
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
        $roles = Role::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        return view('admin.users.create', compact('roles', 'companies'));
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
            'company_id' => 'nullable|exists:companies,id',
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
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'role_id' => $request->role_id,
                'company_id' => $request->company_id,
                'status' => $request->status ?? 0,
            ]);

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
        $user->load(['role', 'company']);
        
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
            'company_id' => 'nullable|exists:companies,id',
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
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role_id' => $request->role_id,
                'company_id' => $request->company_id,
                'status' => $request->status ?? $user->status,
            ]);

            // Update password if provided
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
                $user->save();
            }

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
                'company_id' => 'required|exists:companies,id',
                'role_id' => 'nullable|exists:roles,id'
            ]);

            $company = Company::find($validated['company_id']);
            
            // Start transaction
            DB::beginTransaction();

            try {
                // Assign user to company
                $user->company_id = $company->id;
                
                // If role_id is provided, assign the role
                if (isset($validated['role_id'])) {
                    $role = Role::find($validated['role_id']);
                    $user->role_id = $role->id;
                }
                
                // IMPORTANT: Set email_verified_at to now so user is considered verified
                // This removes them from the pending users list
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
     * Get list of available roles
     */
    public function getRoles(Request $request)
    {
        try {
            $roles = Role::where('name', '!=', 'sysadmin') // Exclude sysadmin from selection
                ->orderBy('name')
                ->get(['id', 'name', 'description']);
            
            return response()->json([
                'success' => true,
                'roles' => $roles
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching roles'
            ], 500);
        }
    }

    /**
     * Get list of users for dropdown/selection
     */
    public function getUsers(Request $request)
    {
        try {
            $query = User::select('id', 'name', 'email', 'role_id', 'company_id')
                ->with(['role', 'company']);
            
            // Filter by role
            if ($request->has('role_id') && $request->role_id) {
                $query->where('role_id', $request->role_id);
            }
            
            // Filter by company
            if ($request->has('company_id') && $request->company_id) {
                $query->where('company_id', $request->company_id);
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
            
            return response()->json([
                'success' => true,
                'users' => $users
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users'
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
}