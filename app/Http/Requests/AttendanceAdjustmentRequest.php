<?php

namespace App\Http\Requests;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceAdjustmentRequest extends FormRequest
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
        return [
            'attendance_id' => ['required', 'integer', 'exists:attendance_records,attendance_id'],
            'requested_attendance_status' => ['required', Rule::in([
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_HALF_DAY,
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_ON_LEAVE,
                AttendanceRecord::STATUS_HOLIDAY,
                AttendanceRecord::STATUS_REST_DAY,
            ])],
            'requested_credited_days' => ['required', 'numeric', 'between:0,2'],
            'adjustment_reason' => ['required', 'string'],
        ];
    }
}
