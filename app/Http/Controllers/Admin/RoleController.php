<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index()
    {
        $roles = Role::withCount('users')
            ->orderBy('name')
            ->get();

        // Get permissions from Spatie
        $permissions = Permission::all()
            ->groupBy(function ($permission) {
                return explode('.', $permission->name)[0] ?? 'other';
            });

        // Get role constants for predefined roles
        $roleConstants = $this->getRoleConstants();

        return view('roles.index', compact('roles', 'permissions', 'roleConstants'));
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->loadCount('users');
        $users = $role->users()->with('company')->limit(50)->get();
        
        $permissions = Permission::all()
            ->groupBy(function ($permission) {
                return explode('.', $permission->name)[0] ?? 'other';
            });

        return view('roles.show', compact('role', 'users', 'permissions'));
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);

            // Store description in a custom field (if you have one)
            if ($request->has('description') && $request->description) {
                $role->description = $request->description;
                $role->save();
            }

            if ($request->has('permissions') && !empty($request->permissions)) {
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                $role->syncPermissions($permissions);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully!',
                'role' => $role->loadCount('users'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        try {
            DB::beginTransaction();

            // Check if role is protected
            if ($this->isProtectedRole($role->name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This role is protected and cannot be modified.',
                ], 422);
            }

            $role->update([
                'name' => $request->name,
            ]);

            // Store description if you have a custom field
            if ($request->has('description')) {
                $role->description = $request->description;
                $role->save();
            }

            if ($request->has('permissions')) {
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                $role->syncPermissions($permissions);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully!',
                'role' => $role->loadCount('users'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role)
    {
        try {
            // Check if role is protected (cannot delete system roles)
            if ($this->isProtectedRole($role->name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This role is protected and cannot be deleted.',
                ], 422);
            }

            // Check if role has users
            if ($role->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role with assigned users. Please reassign users first.',
                ], 422);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get role details for AJAX.
     */
    public function getRole(Role $role)
    {
        $role->loadCount('users');
        $permissions = Permission::all();

        return response()->json([
            'success' => true,
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description ?? '',
                'users_count' => $role->users_count,
                'is_protected' => $this->isProtectedRole($role->name),
                'permissions' => $role->permissions->pluck('id')->toArray(),
            ],
            'all_permissions' => $permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'group' => explode('.', $permission->name)[0] ?? 'other',
                ];
            }),
        ]);
    }

    /**
     * List roles for dropdown/select (AJAX endpoint).
     */
    public function list(Request $request)
    {
        try {
            $roles = Role::orderBy('name')
                ->when($request->has('search'), function ($query) use ($request) {
                    $search = $request->search;
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description ?? ucfirst(str_replace('_', ' ', $role->name)) . ' role',
                        'users_count' => $role->users()->count(),
                        'is_protected' => $this->isProtectedRole($role->name),
                        'initial' => strtoupper(substr($role->name, 0, 1)),
                    ];
                });

            return response()->json([
                'success' => true,
                'roles' => $roles,
                'total' => $roles->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a role is protected (system role).
     */
    private function isProtectedRole(string $roleName): bool
    {
        $protectedRoles = [
            'sysadmin',
            'admin',
            'property_manager',
            'accountant',
            'meter_reader',
            'cleaning_staff',
            'maintenance',
            'security',
            'tenant',
            'guest'
        ];

        return in_array($roleName, $protectedRoles);
    }

    /**
     * Get role constants for predefined roles.
     */
    private function getRoleConstants(): array
    {
        return [
            'sysadmin' => [
                'name' => 'System Administrator',
                'description' => 'Full system access with all permissions',
                'color' => 'danger',
                'icon' => 'shield-check',
                'permissions' => ['all'],
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full company access with all permissions',
                'color' => 'danger',
                'icon' => 'user-shield',
                'permissions' => ['all_company'],
            ],
            'property_manager' => [
                'name' => 'Property Manager',
                'description' => 'Manage properties, units, tenants, and tenancies',
                'color' => 'primary',
                'icon' => 'building',
                'permissions' => ['property', 'estates', 'units', 'tenants', 'tenancies'],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Manage finances, invoices, payments, and expenses',
                'color' => 'success',
                'icon' => 'calculator',
                'permissions' => ['finance', 'invoices', 'payments', 'payees', 'expenses'],
            ],
            'meter_reader' => [
                'name' => 'Meter Reader',
                'description' => 'Record and manage water meter readings',
                'color' => 'info',
                'icon' => 'droplet',
                'permissions' => ['water', 'water_readings'],
            ],
            'cleaning_staff' => [
                'name' => 'Cleaning Staff',
                'description' => 'Manage cleaning tasks and schedules',
                'color' => 'warning',
                'icon' => 'broom',
                'permissions' => ['maintenance'],
            ],
            'maintenance' => [
                'name' => 'Maintenance Staff',
                'description' => 'Manage maintenance requests and tasks',
                'color' => 'warning',
                'icon' => 'wrench',
                'permissions' => ['maintenance'],
            ],
            'security' => [
                'name' => 'Security Staff',
                'description' => 'Manage security logs and access control',
                'color' => 'secondary',
                'icon' => 'shield',
                'permissions' => ['security', 'security_logs'],
            ],
            'tenant' => [
                'name' => 'Tenant',
                'description' => 'View property, finances, and maintenance',
                'color' => 'dark',
                'icon' => 'user',
                'permissions' => ['property', 'finance', 'maintenance', 'security'],
            ],
            'guest' => [
                'name' => 'Guest',
                'description' => 'Limited read-only access',
                'color' => 'light',
                'icon' => 'user',
                'permissions' => ['dashboard'],
            ],
        ];
    }

    /**
     * Seed default roles and permissions (utility method).
     */
    public function seedDefaultRoles(Request $request)
    {
        try {
            DB::beginTransaction();

            // Define default roles
            $defaultRoles = [
                'sysadmin', 'admin', 'property_manager', 'accountant',
                'meter_reader', 'cleaning_staff', 'maintenance', 'security',
                'tenant', 'guest'
            ];

            $createdRoles = [];
            foreach ($defaultRoles as $roleName) {
                $role = Role::firstOrCreate(
                    ['name' => $roleName, 'guard_name' => 'web'],
                    ['description' => ucfirst(str_replace('_', ' ', $roleName)) . ' role']
                );
                $createdRoles[] = $role->name;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default roles seeded successfully!',
                'roles' => $createdRoles,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error seeding default roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to seed default roles: ' . $e->getMessage(),
            ], 500);
        }
    }
}