<?php

namespace App\Http\Requests;

use App\Models\AttendanceRecord;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRecordRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,employee_id'],
            'attendance_date' => ['required', 'date', $this->uniqueAttendancePerDay()],
            'attendance_status' => ['required', Rule::in([
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_HALF_DAY,
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_ON_LEAVE,
                AttendanceRecord::STATUS_HOLIDAY,
                AttendanceRecord::STATUS_REST_DAY,
            ])],
            'credited_days' => ['required', 'numeric', 'between:0,2'],
            'source' => ['nullable', 'string', 'max:30'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    /**
     * One attendance record per employee per day. Compares on the date part so
     * the rule behaves the same on MySQL and SQLite.
     */
    private function uniqueAttendancePerDay(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $record = $this->route('attendance_record');
            $recordId = $record instanceof AttendanceRecord ? $record->attendance_id : null;

            $exists = AttendanceRecord::query()
                ->where('employee_id', $this->input('employee_id'))
                ->whereDate('attendance_date', $value)
                ->when($recordId !== null, fn ($query) => $query->where('attendance_id', '!=', $recordId))
                ->exists();

            if ($exists) {
                $fail('This employee already has an attendance record for the selected date.');
            }
        };
    }
}
