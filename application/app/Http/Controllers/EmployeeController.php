<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelClassification;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);
        $search = $request->query('q', '') ?? '';
        abort_unless(is_string($search) && mb_check_encoding($search, 'UTF-8') && mb_strlen($search) <= 100, 422, 'Search must be text of at most 100 characters.');
        $search = trim($search);
        $query = Employee::query();
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($query) use ($pattern) {
                foreach (['employee_number', 'first_name', 'middle_name', 'last_name', 'position', 'office'] as $column) {
                    $query->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return view('employees.index', [
            'employees' => $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(20)->appends(['q' => $search]),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', ['employee' => new Employee, 'classifications' => PersonnelClassification::cases()]);
    }

    public function store(SaveEmployeeRequest $request): RedirectResponse
    {
        try {
            $employee = DB::transaction(function () use ($request) {
                $this->authorizeWriter($request);

                return Employee::create($request->validated());
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'This employee number is already in use.']);
        }

        return redirect()->route('employees.show', $employee)->with('status', 'Employee created.');
    }

    public function show(Employee $employee): View
    {
        Gate::authorize('view', $employee);

        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('employees.form', ['employee' => $employee, 'classifications' => PersonnelClassification::cases()]);
    }

    public function update(SaveEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $employee) {
                $this->authorizeWriter($request, $employee);
                $current = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                if ($current->record_version !== (int) $request->validated('record_version')) {
                    throw ValidationException::withMessages(['record_version' => 'This employee was changed by another user. Reload the saved record before editing again.']);
                }
                $current->fill($request->safe()->except('record_version'));
                $current->record_version++;
                $current->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'This employee number is already in use.']);
        }

        return redirect()->route('employees.show', $employee)->with('status', 'Employee updated.');
    }

    private function authorizeWriter(SaveEmployeeRequest $request, ?Employee $employee = null): void
    {
        $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize($employee ? 'update' : 'create', $employee ?? Employee::class);
        abort_unless($actor->auth_version === $request->session()->get('auth_version'), 403);
    }
}
