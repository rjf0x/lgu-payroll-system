<?php

namespace App\Http\Requests;

use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $leaveType = $this->route('leave_type');
        $leaveTypeId = $leaveType instanceof LeaveType ? $leaveType->leave_type_id : null;

        return [
            'leave_name' => [
                'required', 'string', 'max:100',
                Rule::unique('leave_types', 'leave_name')->ignore($leaveTypeId, 'leave_type_id'),
            ],
            'description' => ['nullable', 'string'],
            'default_credit' => ['required', 'numeric', 'min:0'],
            'is_paid' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }
}
