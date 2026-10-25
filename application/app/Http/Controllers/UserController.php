<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::orderBy('name')->orderBy('id')->paginate(20)]);
    }

    public function create(): View
    {
        return view('users.form', ['account' => new User, 'roles' => Role::cases()]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request) {
                $this->lockAccountsForWrite($request);
                $data = $request->validated();
                $data['password'] = Hash::make($data['password']);
                $account = new User;
                $account->forceFill($data)->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'This username is already in use.']);
        }

        return redirect()->route('users.index')->with('status', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['account' => $user, 'roles' => Role::cases()]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        try {
            $account = DB::transaction(function () use ($request, $user) {
                $accounts = $this->lockAccountsForWrite($request, [$user->id]);
                $account = $accounts->get($user->id);
                abort_unless($account, 404);
                $data = $request->safe()->except(['password']);
                if ($request->filled('password')) {
                    $data['password'] = Hash::make($request->validated('password'));
                }
                $account->forceFill($data);
                $account->auth_version++;
                $account->remember_token = null;
                $account->save();

                return $account;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'This username is already in use.']);
        }

        if ($account->is($request->user())) {
            $request->session()->regenerate();
            $request->session()->put('auth_version', $account->auth_version);
        }

        return redirect()->route('users.index')->with('status', 'Account updated. Previous sessions have been revoked.');
    }

    private function lockAccountsForWrite(SaveUserRequest $request, array $targetIds = []): Collection
    {
        // Lock actor and target in the same order for reciprocal administrator edits.
        // An active administrator is therefore preserved throughout every write.
        $accounts = User::whereIn('id', [$request->user()->id, ...$targetIds])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $actor = $accounts->get($request->user()->id);
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize('manage-users');
        abort_unless($actor->auth_version === $request->session()->get('auth_version'), 403);

        return $accounts;
    }
}
