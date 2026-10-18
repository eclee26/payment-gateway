<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\User;
use App\Services\TwoFactor\TwoFactorAuthenticator;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $users = User::with(['roles.permissions', 'merchant'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->role($role))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'filters' => $validated,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', [
            'user' => new User,
            'roles' => Permissions::assignableRoles($request->user()),
            'currentRole' => null,
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            $user->syncRoles([$request->validated('role')]);

            return $user;
        });

        return redirect()->route('admin.users.index')
            ->with('status', "User {$user->email} created. They will set up two-factor authentication at first sign-in.");
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless(Permissions::canManageUser($request->user(), $user), 403);

        return view('admin.users.form', [
            'user' => $user->load('roles'),
            'roles' => Permissions::assignableRoles($request->user()),
            'currentRole' => $user->roles->first()?->name,
        ]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->fill($request->safe()->only(['name', 'email']));

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
            }

            $user->save();

            if ($request->has('role') && $request->validated('role') !== null) {
                $user->syncRoles([$request->validated('role')]);
            }
        });

        return redirect()->route('admin.users.index')->with('status', "User {$user->email} updated.");
    }

    /**
     * Clear the user's TOTP, e.g. after a lost phone. They must set it up
     * again on their next request.
     */
    public function resetTwoFactor(Request $request, User $user, TwoFactorAuthenticator $twoFactor): RedirectResponse
    {
        abort_unless(Permissions::canManageUser($request->user(), $user), 403);

        $twoFactor->reset($user);

        return back()->with('status', "Two-factor authentication reset for {$user->email}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(Permissions::canManageUser($request->user(), $user), 403);

        $error = match (true) {
            $user->is($request->user()) => 'You cannot delete your own account here. Use your profile page instead.',
            Permissions::isLastSuperAdmin($user) => 'This is the only super-admin and cannot be deleted.',
            // Deleting the user cascades to the merchant and its payments.
            (bool) $user->merchant?->payments()->exists() => 'This merchant has payments. Their history must be kept, so the user cannot be deleted.',
            default => null,
        };

        if ($error !== null) {
            return back()->with('error', $error);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User {$user->email} deleted.");
    }
}
