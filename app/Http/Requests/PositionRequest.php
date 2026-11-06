<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
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
        $position = $this->route('position');
        $positionId = $position instanceof Position ? $position->position_id : null;

        return [
            'department_id' => ['required', 'integer', 'exists:departments,department_id'],
            'position_code' => [
                'required', 'string', 'max:50',
                Rule::unique('positions', 'position_code')->ignore($positionId, 'position_id'),
            ],
            'position_title' => ['required', 'string', 'max:150'],
            'salary_grade' => ['nullable', 'integer', 'between:1,33'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }
}
