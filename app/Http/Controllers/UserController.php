<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['roles', 'employee'])
            ->orderBy('username')
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create', $this->formData(new User([
            'account_status' => User::STATUS_ACTIVE,
        ])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($this->payload($request));
        $user->roles()->sync($this->roleSync($request->input('roles', [])));

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User account created.');
    }

    public function show(User $user): View
    {
        $user->load(['employee', 'roles', 'userRoles.assignedBy']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('users.edit', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($this->payload($request));
        $user->roles()->sync($this->roleSync($request->input('roles', [])));

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User account updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'User account deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UserRequest $request): array
    {
        $data = $request->safe()->except(['password', 'roles']);

        if ($request->filled('password')) {
            $data['password_hash'] = $request->string('password')->toString();
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $roleIds
     * @return array<int, array<string, mixed>>
     */
    private function roleSync(array $roleIds): array
    {
        $sync = [];

        foreach ($roleIds as $roleId) {
            $sync[(int) $roleId] = [
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ];
        }

        return $sync;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        return [
            'account' => $user,
            'roles' => Role::orderBy('role_name')->get(),
            'employees' => Employee::orderBy('last_name')->get(),
            'assignedRoleIds' => $user->exists ? $user->roles->pluck('role_id')->all() : [],
        ];
    }
}
