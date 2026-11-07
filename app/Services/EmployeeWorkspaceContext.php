<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class EmployeeWorkspaceContext
{
    public function employeeId(): ?int
    {
        $data = request()->validate(['employee_id' => ['nullable', 'integer', Rule::exists('employees', 'employee_id')->whereNull('deleted_at')]]);

        return isset($data['employee_id']) ? (int) $data['employee_id'] : null;
    }

    public function employmentId(): ?int
    {
        $data = request()->validate(['employment_id' => ['nullable', 'integer', 'exists:employment_records,employment_id']]);

        return isset($data['employment_id']) ? (int) $data['employment_id'] : null;
    }

    /** @return array{workspaceEmployee: ?Employee} */
    public function viewData(?int $employeeId = null, ?int $employmentId = null): array
    {
        if (! request()->boolean('employee_workspace') || ! request()->user()->can('employees.view')) {
            return ['workspaceEmployee' => null];
        }

        if ($employeeId === null && $employmentId !== null) {
            $employeeId = EmploymentRecord::findOrFail($employmentId)->employee_id;
        }

        return ['workspaceEmployee' => $employeeId !== null ? Employee::findOrFail($employeeId) : null];
    }

    public function redirect(int $employeeId, string $section, string $message): ?RedirectResponse
    {
        if (! request()->boolean('employee_workspace') || ! request()->user()->can('employees.view')) {
            return null;
        }

        return redirect()->to(route('employees.show', $employeeId).'#'.$section)->with('status', $message);
    }
}
