<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Support\RoleDescriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles & permissions (pattern from Sharet Africa). The page is a shell; roles, the permission
 * catalogue (App\Support\Access, grouped by area) and stats come as JSON. Built-in roles keep their
 * names (code checks them) but their permissions can be changed; custom roles can be added.
 */
class RoleController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->wantsJson()) {
            return view('roles.index');
        }

        $roles = Role::withCount('users')->with('permissions:id')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get(['id', 'name']);
        $areas = Access::AREAS;

        return response()->json([
            'roles' => $roles->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'label' => $this->label($r->name), 'description' => RoleDescriptions::for($r),
                'users' => $r->users_count, 'protected' => in_array($r->name, Access::BUILT_IN, true),
                'permissions' => $r->permissions->pluck('id'),
            ])->values(),
            'groups' => $permissions->groupBy(fn ($p) => explode('.', $p->name)[0])
                ->sortBy(fn ($items, $area) => array_search($area, array_keys($areas)) === false ? 99 : array_search($area, array_keys($areas)))
                ->map(fn ($items, $area) => [
                    'key' => $area, 'label' => $areas[$area] ?? $this->label($area),
                    'permissions' => $items->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'label' => match (explode('.', $p->name, 2)[1] ?? '') {
                        'view' => 'Open and read', 'manage' => 'Create and edit', 'delete' => 'Delete', default => $this->label($p->name),
                    }])->sortBy(fn ($p) => array_search(explode('.', $p['name'], 2)[1] ?? '', Access::ACTIONS))->values(),
                ])->values(),
            'stats' => [
                'roles' => $roles->count(), 'roles_hint' => $roles->where('users_count', '>', 0)->count().' in use',
                'custom' => $roles->reject(fn ($r) => in_array($r->name, Access::BUILT_IN, true))->count(), 'custom_hint' => 'Made by your team',
                'permissions' => $permissions->count(), 'permissions_hint' => count($areas).' areas',
                'people' => DB::table('model_has_roles')->distinct('model_id')->count('model_id'), 'people_hint' => 'Have at least one role',
            ],
        ]);
    }

    public function show(Role $role)
    {
        return redirect()->route('roles.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')],
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ], ['name.regex' => 'Use lowercase letters, numbers and underscores, e.g. finance_officer.']);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['success' => true, 'message' => 'Role created.', 'role' => ['id' => $role->id]], 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);
        if (in_array($role->name, Access::BUILT_IN, true) && $data['name'] !== $role->name) {
            return response()->json(['success' => false, 'message' => 'Built-in roles cannot be renamed.', 'errors' => ['name' => ['Built-in roles cannot be renamed.']]], 422);
        }
        if ($role->name === 'sysadmin') {
            return response()->json(['success' => false, 'message' => 'The system admin always has every permission.'], 422);
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['success' => true, 'message' => 'Saved. People with this role see the change on their next page.']);
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, Access::BUILT_IN, true)) {
            return response()->json(['success' => false, 'message' => 'Built-in roles cannot be deleted.'], 422);
        }
        if ($role->users()->exists()) {
            return response()->json(['success' => false, 'message' => 'Move the people with this role to another role first.'], 422);
        }
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    public function getRole(Role $role)
    {
        return response()->json(['success' => true, 'role' => ['id' => $role->id, 'name' => $role->name, 'description' => RoleDescriptions::for($role),
            'users_count' => $role->users()->count(), 'is_protected' => in_array($role->name, Access::BUILT_IN, true),
            'permissions' => $role->permissions->pluck('id')]]);
    }

    private function label(string $name): string
    {
        return ucfirst(str_replace(['_', '.', '-'], ' ', $name));
    }
}
