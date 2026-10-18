<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role,
            'granted' => [],
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $role = DB::transaction(function () use ($request) {
            $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
            $role->syncPermissions($request->validated('permissions') ?? []);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} created.");
    }

    public function edit(Request $request, Role $role): View
    {
        $this->ensureEditable($request, $role);

        return view('admin.roles.form', [
            'role' => $role,
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureEditable($request, $role);

        DB::transaction(function () use ($request, $role) {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions') ?? []);
        });

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->ensureEditable($request, $role);

        if ($role->users()->exists()) {
            return back()->with('error', "Role {$role->name} is still assigned to users. Move them to another role first.");
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} deleted.");
    }

    /**
     * System roles are fixed, and non-super-admins can only change roles
     * whose permissions they hold themselves.
     */
    private function ensureEditable(Request $request, Role $role): void
    {
        abort_if(in_array($role->name, Permissions::SYSTEM_ROLES, true), 403, 'System roles cannot be changed.');
        abort_unless(Permissions::holdsAll($request->user(), $role->permissions->pluck('name')->all()), 403);
    }
}
