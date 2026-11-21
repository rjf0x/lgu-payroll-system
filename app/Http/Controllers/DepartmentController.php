<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::query()
            ->withCount('positions')
            ->orderBy('department_name')
            ->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.create', [
            'department' => new Department(['status' => 'ACTIVE']),
        ]);
    }

    public function store(DepartmentRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new Department, $request->validated(), $request->user());

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department created.');
    }

    public function show(Department $department): View
    {
        $department->load(['positions' => fn ($query) => $query->orderBy('position_title')]);

        return view('departments.show', compact('department'));
    }

    public function edit(Department $department): View
    {
        return view('departments.edit', compact('department'));
    }

    public function update(DepartmentRequest $request, Department $department, MasterDataMutationService $mutations): RedirectResponse
    {
        $department = $mutations->update($department, $request->validated(), $request->user());

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department updated.');
    }

    public function destroy(Department $department, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($department, auth()->user());

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department deleted.');
    }
}
