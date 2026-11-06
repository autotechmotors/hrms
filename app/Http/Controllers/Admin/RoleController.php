<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount('employees', 'permissions')->orderBy('name')->paginate(20);

        return view('admin.roles.index', [
            'roles' => $roles,
            'canEdit' => request()->user()?->hasPermission('roles.edit') ?? false,
            'stats' => [
                'total' => Role::query()->count(),
                'active' => Role::query()->where('is_active', true)->count(),
                'parent' => Role::query()->where('has_parent', true)->where('is_active', true)->count(),
                'child' => Role::query()->where('has_child', true)->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.form', [
            'role' => null,
            'groups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->string('name')->toString(),
            'slug' => Str::slug($request->string('slug')->toString()),
            'description' => $request->input('description'),
            'is_system' => false,
            'requires_mfa' => $request->boolean('requires_mfa'),
            'has_parent' => $request->boolean('has_parent'),
            'has_child' => $request->boolean('has_child'),
            'is_active' => $request->boolean('is_active'),
        ]);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Role saved.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.form', [
            'role' => $role->load('permissions'),
            'groups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update([
            'name' => $request->string('name')->toString(),
            'slug' => $role->is_system ? $role->slug : Str::slug($request->string('slug')->toString()),
            'description' => $request->input('description'),
            'requires_mfa' => $request->boolean('requires_mfa'),
            'has_parent' => $request->boolean('has_parent'),
            'has_child' => $request->boolean('has_child'),
            'is_active' => $role->slug === 'super-admin' ? true : $request->boolean('is_active'),
        ]);

        if ($role->slug !== 'super-admin') {
            $role->permissions()->sync($request->input('permissions', []));
        }

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        if ($role->users()->exists()) {
            return back()->with('error', 'Remove this role from its users before deleting it.');
        }
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }
}
