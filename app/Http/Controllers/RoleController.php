<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions', 'users')->orderBy('name')->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role(),
            'permissions' => Permission::query()->orderBy('slug')->get()->groupBy(fn ($permission) => explode('.', $permission->slug)[0]),
            'selectedPermissions' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $role->load('permissions');
        $this->audit()->logCustom('Role created', 'role.created', [
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'auditable_label' => $role->name,
            'new_values' => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('slug')->all(),
            ],
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('admin.roles.form', [
            'role' => $role,
            'permissions' => Permission::query()->orderBy('slug')->get()->groupBy(fn ($permission) => explode('.', $permission->slug)[0]),
            'selectedPermissions' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $beforeValues = [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('slug')->all(),
        ];

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role->update(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $role->load('permissions');
        $this->audit()->logCustom('Role updated', 'role.updated', [
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'auditable_label' => $role->name,
            'old_values' => $beforeValues,
            'new_values' => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('slug')->all(),
            ],
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to one or more users.');
        }

        $beforeValues = [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('slug')->all(),
        ];
        $roleId = $role->id;
        $roleName = $role->name;
        $role->delete();
        $this->audit()->logCustom('Role deleted', 'role.deleted', [
            'auditable_type' => Role::class,
            'auditable_id' => $roleId,
            'auditable_label' => $roleName,
            'old_values' => $beforeValues,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }
}
