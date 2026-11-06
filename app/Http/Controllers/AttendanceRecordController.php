<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    public function index(Request $request): View
    {
        $employeeId = $request->integer('employee_id') ?: null;
        $month = $request->string('month')->toString() ?: null;

        $records = AttendanceRecord::query()
            ->with('employee')
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->when($month !== null, function ($query) use ($month): void {
                [$year, $monthNumber] = array_pad(explode('-', $month), 2, null);
                $query->whereYear('attendance_date', $year)->whereMonth('attendance_date', $monthNumber);
            })
            ->orderByDesc('attendance_date')
            ->paginate(20)
            ->withQueryString();

        return view('attendance-records.index', [
            'records' => $records,
            'employees' => Employee::orderBy('last_name')->get(),
            'employeeId' => $employeeId,
            'month' => $month,
        ]);
    }

    public function create(Request $request): View
    {
        return view('attendance-records.create', [
            'record' => new AttendanceRecord([
                'employee_id' => $request->integer('employee_id') ?: null,
                'attendance_date' => now()->toDateString(),
                'attendance_status' => AttendanceRecord::STATUS_PRESENT,
                'credited_days' => 1,
                'source' => 'MANUAL',
            ]),
            'employees' => Employee::orderBy('last_name')->get(),
        ]);
    }

    public function store(AttendanceRecordRequest $request): RedirectResponse
    {
        AttendanceRecord::create($request->validated());

        return redirect()
            ->route('attendance-records.index')
            ->with('status', 'Attendance record saved.');
    }

    public function edit(AttendanceRecord $attendanceRecord): View
    {
        return view('attendance-records.edit', [
            'record' => $attendanceRecord,
            'employees' => Employee::orderBy('last_name')->get(),
        ]);
    }

    public function update(AttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $attendanceRecord->update($request->validated());

        return redirect()
            ->route('attendance-records.index')
            ->with('status', 'Attendance record updated.');
    }

    public function destroy(AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $attendanceRecord->delete();

        return redirect()
            ->route('attendance-records.index')
            ->with('status', 'Attendance record deleted.');
    }
}
