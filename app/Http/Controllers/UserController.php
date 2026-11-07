<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountAccessService;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AccountAccessService $access, private readonly AuditLogger $audit) {}

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
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($this->payload($request));
            $user->roles()->sync($this->roleSync($request->validated('roles')));
            $this->audit->log('USER_CREATED', 'User Management', (int) auth()->id(), 'users', (string) $user->user_id, newValues: $this->access->auditValues($user));

            return $user;
        });

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
        DB::transaction(function () use ($request, $user): void {
            $administrators = $this->access->lockAdministrators();
            $target = User::whereKey($user->user_id)->lockForUpdate()->firstOrFail();
            $this->access->ensureAdministratorRemains($administrators, $target, $request->validated('account_status'), $request->validated('roles'));
            $oldValues = $this->access->auditValues($target);
            $target->update($this->payload($request));
            $target->roles()->sync($this->roleSync($request->validated('roles')));
            $this->audit->log('USER_UPDATED', 'User Management', (int) auth()->id(), 'users', (string) $target->user_id, $oldValues, $this->access->auditValues($target) + ['password_changed' => $request->filled('password')]);
        });

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User account updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        try {
            DB::transaction(function () use ($user): void {
                $administrators = $this->access->lockAdministrators();
                $target = User::whereKey($user->user_id)->lockForUpdate()->firstOrFail();
                $this->access->ensureAdministratorRemains($administrators, $target, User::STATUS_INACTIVE, []);
                $oldValues = $this->access->auditValues($target);
                $target->delete();
                $this->audit->log('USER_DELETED', 'User Management', (int) auth()->id(), 'users', (string) $user->user_id, oldValues: $oldValues);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return back()->with('error', 'This account is referenced by records and cannot be deleted. Deactivate it instead.');
        }

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
